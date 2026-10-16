<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\form\SettingsForm;
use app\models\IntradayPrice;
use app\models\User;
use app\services\MarketStatusService;
use app\services\SettingsService;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;

/**
 * Settings & Admin (Modul 14) — HANYA admin (RBAC 14.2).
 * actionIndex      : form pengaturan provider/threshold/channel (14.1) & panel CLI runner.
 * actionStatus     : status koneksi provider & waktu update terakhir (14.3).
 * actionRunCommand : eksekusi perintah console CLI (market/refresh-stocklist, data/refresh-all, data/sync-intraday-all).
 * actionSyncStatus : status sinkronisasi terbaru untuk polling UI.
 */
final class SettingsController extends Controller
{
    public const ALLOWED_COMMANDS = [
        'market/refresh-stocklist' => [
            'key' => 'market/refresh-stocklist',
            'title' => 'Refresh Stock List',
            'cli' => 'market/refresh-stocklist',
            'desc' => 'Perbarui seluruh daftar simbol saham (217+ saham) dari Yahoo Finance ke database lokal',
            'badge' => 'Cepat (~1-2 dtk)',
            'btnClass' => 'primary',
            'hasLimit' => false,
        ],
        'data/refresh-all' => [
            'key' => 'data/refresh-all',
            'title' => 'Refresh Daily Data (All Stocks)',
            'cli' => 'data/refresh-all',
            'desc' => 'Tarik dan perbarui data candle harian (Daily OHLCV) terbaru secara incremental untuk semua saham aktif',
            'badge' => 'Candle 1D',
            'btnClass' => 'success',
            'hasLimit' => true,
        ],
        'data/sync-intraday-all' => [
            'key' => 'data/sync-intraday-all',
            'title' => 'Sync Intraday 1H Data (All Stocks)',
            'cli' => 'data/sync-intraday-all',
            'desc' => 'Tarik dan perbarui data candle 1 jam (Intraday Bars) terbaru untuk analisis teknikal multi-timeframe',
            'badge' => 'Candle 1H',
            'btnClass' => 'warning text-dark',
            'hasLimit' => true,
        ],
        'data/analyze' => [
            'key' => 'data/analyze',
            'title' => 'Analyze Weekly Volume & Signals',
            'cli' => 'data/analyze',
            'desc' => 'Jalankan kalkulasi transaksi Metode A, akumulasi mingguan, RVOL, dan scoring sinyal untuk semua saham',
            'badge' => 'Analysis & Scoring',
            'btnClass' => 'info text-dark',
            'hasLimit' => false,
        ],
        'all' => [
            'key' => 'all',
            'title' => 'Jalankan Semua Sinkronisasi',
            'cli' => 'all',
            'desc' => 'Eksekusi berurutan: Refresh Stock List -> Refresh Daily Data -> Analisis Mingguan & Scoring -> Sync Intraday 1H Data',
            'badge' => 'Full Pipeline',
            'btnClass' => 'dark',
            'hasLimit' => true,
        ],
    ];

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            /** @var User|null $user */
                            $user = Yii::$app->user->identity;
                            return $user !== null && $user->isAdmin();
                        },
                    ],
                ],
            ],
        ];
    }

    public function actionIndex(): Response|string
    {
        $service = Yii::$container->get(SettingsService::class);
        $form = new SettingsForm();

        // Buang field kosong agar tidak menimpa properti numeric nullable
        if (Yii::$app->request->isPost && Yii::$app->request->post('SettingsForm')) {
            $post = Yii::$app->request->post();
            $form->load($post);
            if ($form->validate()) {
                $form->saveTo($service);
                Yii::$app->session->setFlash('success', 'Pengaturan disimpan.');
                return $this->redirect(['settings/index']);
            }
        } else {
            $form->loadCurrent($service);
        }

        $marketStatus = Yii::$container->get(MarketStatusService::class)->footerSummary();
        $marketStatus['lastIntradayAt'] = (int) (IntradayPrice::find()->max('updated_at') ?: 0);
        $marketStatus['lastIntradayDatetime'] = (string) (IntradayPrice::find()->max('datetime') ?: '');

        return $this->render('index', [
            'model' => $form,
            'status' => $marketStatus,
            'syncCommands' => self::ALLOWED_COMMANDS,
            'syncStatus' => self::getLastSyncStatus(),
            'phpExecutable' => self::getPhpExecutable(),
        ]);
    }

    public function actionStatus(): string
    {
        $status = Yii::$container->get(MarketStatusService::class)->footerSummary();

        return $this->render('status', ['status' => $status]);
    }

    /**
     * Jalankan perintah CLI console (market/refresh-stocklist, data/refresh-all, data/sync-intraday-all).
     */
    public function actionRunCommand(): Response
    {
        $req = Yii::$app->request;
        $cmdKey = (string) ($req->get('cmd') ?: $req->post('cmd', ''));
        $limit = (int) ($req->get('limit') ?: $req->post('limit', 0));
        $isStream = (bool) $req->get('stream', false);

        if (!isset(self::ALLOWED_COMMANDS[$cmdKey])) {
            if ($isStream) {
                header('Content-Type: text/event-stream');
                echo "data: " . json_encode(['type' => 'error', 'message' => 'Perintah tidak diizinkan.']) . "\n\n";
                exit;
            }
            Yii::$app->response->format = Response::FORMAT_JSON;
            return $this->asJson(['success' => false, 'message' => 'Perintah tidak valid atau tidak diizinkan.']);
        }

        $commandsToRun = [];
        if ($cmdKey === 'all') {
            $commandsToRun[] = ['cli' => 'market/refresh-stocklist', 'title' => '1/4: Refresh Stock List'];
            $commandsToRun[] = ['cli' => 'data/refresh-all' . ($limit > 0 ? " --limit={$limit}" : ''), 'title' => '2/4: Refresh Daily Data'];
            $commandsToRun[] = ['cli' => 'data/analyze', 'title' => '3/4: Analisis Volume Mingguan & Scoring'];
            $commandsToRun[] = ['cli' => 'data/sync-intraday-all' . ($limit > 0 ? " --limit={$limit}" : ''), 'title' => '4/4: Sync Intraday Data'];
        } else {
            $cli = self::ALLOWED_COMMANDS[$cmdKey]['cli'];
            if ($limit > 0 && self::ALLOWED_COMMANDS[$cmdKey]['hasLimit']) {
                $cli .= " --limit={$limit}";
            }
            $commandsToRun[] = ['cli' => $cli, 'title' => self::ALLOWED_COMMANDS[$cmdKey]['title']];
        }

        if ($isStream) {
            $this->handleSseExecution($commandsToRun, $cmdKey);
            exit;
        }

        // Direct non-streaming execution
        $result = $this->executeCommandsDirect($commandsToRun, $cmdKey);
        if ($req->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return $this->asJson($result);
        }

        Yii::$app->session->setFlash($result['success'] ? 'success' : 'danger', $result['message']);
        return $this->redirect(['settings/index']);
    }

    /**
     * Endpoint status sinkronisasi terbaru untuk polling / AJAX update.
     */
    public function actionSyncStatus(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $marketStatus = Yii::$container->get(MarketStatusService::class)->footerSummary();
        $marketStatus['lastIntradayAt'] = (int) (IntradayPrice::find()->max('updated_at') ?: 0);
        $marketStatus['lastIntradayDatetime'] = (string) (IntradayPrice::find()->max('datetime') ?: '');

        return $this->asJson([
            'success' => true,
            'market' => $marketStatus,
            'sync' => self::getLastSyncStatus(),
        ]);
    }

    /**
     * Eksekusi perintah via Server-Sent Events (SSE) untuk output terminal live.
     */
    private function handleSseExecution(array $commands, string $cmdKey): void
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ignore_user_abort(true);

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        for ($i = 0; $i < ob_get_level(); $i++) {
            @ob_end_flush();
        }
        ob_implicit_flush(true);

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $phpBin = self::getPhpExecutable();
        $yiiBin = Yii::getAlias('@app/yii');
        $appDir = Yii::getAlias('@app');
        $totalStartTime = microtime(true);
        $allOutput = '';
        $finalSuccess = true;

        echo "data: " . json_encode([
            'type' => 'start',
            'cmdKey' => $cmdKey,
            'title' => self::ALLOWED_COMMANDS[$cmdKey]['title'],
            'time' => date('H:i:s'),
            'total' => count($commands),
        ]) . "\n\n";
        @ob_flush();
        flush();

        foreach ($commands as $idx => $cmdInfo) {
            $cli = $cmdInfo['cli'];
            $title = $cmdInfo['title'];
            $fullCmd = sprintf('"%s" "%s" %s 2>&1', $phpBin, $yiiBin, $cli);

            echo "data: " . json_encode([
                'type' => 'cmd_start',
                'index' => $idx + 1,
                'title' => $title,
                'cli' => "php yii " . $cli,
            ]) . "\n\n";
            @ob_flush();
            flush();

            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w']];
            $process = proc_open($fullCmd, $descriptors, $pipes, $appDir);

            if (!is_resource($process)) {
                $errLine = "[ERROR] Gagal membuka proses: {$fullCmd}\n";
                $allOutput .= $errLine;
                echo "data: " . json_encode(['type' => 'output', 'line' => $errLine]) . "\n\n";
                $finalSuccess = false;
                break;
            }

            fclose($pipes[0]);
            while (!feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line !== false) {
                    $allOutput .= $line;
                    echo "data: " . json_encode(['type' => 'output', 'line' => $line]) . "\n\n";
                    @ob_flush();
                    flush();
                }
            }
            fclose($pipes[1]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                $finalSuccess = false;
                echo "data: " . json_encode([
                    'type' => 'cmd_fail',
                    'index' => $idx + 1,
                    'exitCode' => $exitCode,
                ]) . "\n\n";
                @ob_flush();
                flush();
                break;
            }
        }

        $duration = round(microtime(true) - $totalStartTime, 2);
        $statusText = $finalSuccess ? 'Selesai Sukses' : 'Gagal / Terhenti';

        // Simpan log ke file dan riwayat status
        self::recordSyncLog($cmdKey, $allOutput, $finalSuccess, $duration);

        echo "data: " . json_encode([
            'type' => 'done',
            'success' => $finalSuccess,
            'duration' => $duration,
            'message' => "Proses {$statusText} dalam {$duration} detik.",
        ]) . "\n\n";
        @ob_flush();
        flush();
    }

    /**
     * Eksekusi langsung non-streaming.
     */
    private function executeCommandsDirect(array $commands, string $cmdKey): array
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $phpBin = self::getPhpExecutable();
        $yiiBin = Yii::getAlias('@app/yii');
        $appDir = Yii::getAlias('@app');
        $totalStartTime = microtime(true);
        $allOutput = '';
        $finalSuccess = true;
        $finalExitCode = 0;

        foreach ($commands as $cmdInfo) {
            $cli = $cmdInfo['cli'];
            $fullCmd = sprintf('"%s" "%s" %s 2>&1', $phpBin, $yiiBin, $cli);
            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w']];
            $process = proc_open($fullCmd, $descriptors, $pipes, $appDir);

            if (!is_resource($process)) {
                $allOutput .= "[ERROR] Gagal membuka proses: {$fullCmd}\n";
                $finalSuccess = false;
                $finalExitCode = 1;
                break;
            }

            fclose($pipes[0]);
            while (!feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line !== false) {
                    $allOutput .= $line;
                }
            }
            fclose($pipes[1]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                $finalSuccess = false;
                $finalExitCode = $exitCode;
                break;
            }
        }

        $duration = round(microtime(true) - $totalStartTime, 2);
        self::recordSyncLog($cmdKey, $allOutput, $finalSuccess, $duration);

        return [
            'success' => $finalSuccess,
            'exitCode' => $finalExitCode,
            'output' => $allOutput,
            'duration' => $duration,
            'message' => $finalSuccess
                ? "Eksekusi berhasil diselesaikan dalam {$duration} detik."
                : "Eksekusi selesai dengan kode keluar: {$finalExitCode}",
        ];
    }

    /**
     * Catat log eksekusi ke runtime/logs/sync-commands.log dan file status JSON.
     */
    private static function recordSyncLog(string $cmdKey, string $output, bool $success, float $duration): void
    {
        try {
            $runtimePath = Yii::getAlias('@runtime');
            $logDir = $runtimePath . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $logFile = $logDir . '/sync-commands.log';
            $entry = sprintf(
                "[%s] CMD: %s | STATUS: %s | DURATION: %ss\nOUTPUT:\n%s\n%s\n",
                date('Y-m-d H:i:s'),
                $cmdKey,
                $success ? 'SUCCESS' : 'FAILED',
                $duration,
                trim($output),
                str_repeat('-', 60)
            );
            @file_put_contents($logFile, $entry, FILE_APPEND);

            $statusFile = $runtimePath . '/sync_status.json';
            $statusData = [
                'last_cmd' => $cmdKey,
                'last_title' => self::ALLOWED_COMMANDS[$cmdKey]['title'] ?? $cmdKey,
                'last_time' => date('Y-m-d H:i:s'),
                'last_success' => $success,
                'duration' => $duration,
            ];
            @file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            Yii::warning('Failed recording sync log: ' . $e->getMessage());
        }
    }

    /**
     * Baca status eksekusi terakhir dari file JSON status.
     */
    public static function getLastSyncStatus(): ?array
    {
        try {
            $statusFile = Yii::getAlias('@runtime') . '/sync_status.json';
            if (file_exists($statusFile)) {
                $raw = @file_get_contents($statusFile);
                if ($raw) {
                    return json_decode($raw, true) ?: null;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

    /**
     * Dapatkan path executable PHP yang valid.
     */
    public static function getPhpExecutable(): string
    {
        // 1. Cek dari environment (.env) jika diset secara eksplisit
        $envPhp = getenv('PHP_BINARY_PATH');
        if (!empty($envPhp) && file_exists($envPhp)) {
            return $envPhp;
        }

        // 2. Windows environment check
        if (DIRECTORY_SEPARATOR === '\\') {
            if (defined('PHP_BINARY') && str_ends_with(strtolower(PHP_BINARY), 'php.exe') && file_exists(PHP_BINARY)) {
                return PHP_BINARY;
            }
            if (defined('PHP_BINARY')) {
                $candidate = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php.exe';
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
            if (defined('PHP_BINDIR')) {
                $candidate = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe';
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
            return 'php';
        }

        // 3. Linux / Unix (khususnya CyberPanel OpenLiteSpeed)
        // Cari binary PHP 8.4, 8.3, 8.2, atau 8.1 milik CyberPanel LiteSpeed terlebih dahulu
        $linuxCandidates = [
            '/usr/local/lsws/lsphp84/bin/php',
            '/usr/local/lsws/lsphp83/bin/php',
            '/usr/local/lsws/lsphp82/bin/php',
            '/usr/local/lsws/lsphp81/bin/php',
            '/usr/local/lsws/lsphp80/bin/php',
            '/usr/bin/php8.4',
            '/usr/bin/php8.3',
            '/usr/bin/php8.2',
            '/usr/bin/php8.1',
            '/usr/bin/php8.0',
        ];

        foreach ($linuxCandidates as $candidate) {
            if (file_exists($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        // 4. Cek PHP_BINARY bawaan jika versinya >= PHP 8
        if (defined('PHP_BINARY') && file_exists(PHP_BINARY) && PHP_VERSION_ID >= 80000) {
            return PHP_BINARY;
        }

        return 'php';
    }
}