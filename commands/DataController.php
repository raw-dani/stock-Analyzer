<?php

declare(strict_types=1);

namespace app\commands;

use app\jobs\FetchSymbolJob;
use app\models\Stock;
use app\services\MarketDataService;
use app\services\SignalEngineService;
use app\services\VolumeAnalyzerService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Console untuk pipeline data (task 2.4, 3.7 + refresh incremental).
 *
 * php yii data/fetch              # sync semua simbol aktif (via queue)
 * php yii data/fetch NVDA         # sync satu simbol
 * php yii data/sync NVDA          # sync langsung tanpa queue (debug)
 * php yii data/refresh-all        # refresh daily SEMUA simbol (incremental, cron harian)
 * php yii data/sync-intraday NVDA # sync intraday 1 simbol (incremental)
 * php yii data/sync-intraday-all  # sync intraday SEMUA simbol (cron 1-4 jam)
 * php yii data/analyze            # klasifikasi + agregasi + scoring (Modul 3+4)
 */
final class DataController extends Controller
{
    public ?string $from = null;
    public ?string $to = null;
    public string $interval = '1h';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['from', 'to', 'limit', 'offset', 'sleepMs', 'interval']);
    }

    public int $limit = 0;
    public int $offset = 0;
    public int $sleepMs = 0;

    /**
     * Fetch data harian dari provider aktif. Tanpa argumen = semua simbol aktif.
     */
    public function actionFetch(?string $symbol = null): int
    {
        $symbols = $symbol !== null
            ? [$symbol]
            : Stock::find()->select('symbol')->where(['active' => true])->column();

        if ($symbols === []) {
            $this->stdout("No active symbols. Jalankan `php yii market/refresh-stocklist` dulu.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $queue = Yii::$app->queue;
        $default = $this->from ?? date('Y-m-d', strtotime('-' . (int) (Yii::$app->params['historyDays'] ?? 400) . ' days'));

        foreach ($symbols as $sym) {
            $job = new FetchSymbolJob();
            $job->symbol = $sym;
            $job->fromDate = $this->from ?? $default;
            $job->toDate = $this->to;
            $queue->push($job);
            $this->stdout("Queued fetch: {$sym}\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /**
     * (Fallback manual) sync langsung tanpa queue — berguna untuk debugging.
     */
    public function actionSync(string $symbol): int
    {
        $service = new MarketDataService(Yii::$app->get('marketData')->get());
        $count = $service->syncSymbol($symbol, $this->from, $this->to);
        $this->stdout("Synced {$symbol}: {$count} bars\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Sync data intraday (1h) dari provider aktif untuk satu simbol.
     * Contoh: php yii data/sync-intraday NVDA --interval=1h --from=2024-01-01
     */
    public function actionSyncIntraday(string $symbol, ?string $interval = null): int
    {
        $interval ??= $this->interval;
        $provider = Yii::$app->get('marketData')->get();
        if (!method_exists($provider, 'getIntradayBars')) {
            $this->stdout("Provider aktif tidak mendukung intraday (getIntradayBars).\n", Console::FG_YELLOW);
            $this->stdout("Ganti provider ke yahoo_finance via halaman Settings.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $svc = new MarketDataService($provider);
        try {
            // Incremental bila --from/--to tak diisi (lanjut dari DB - 2 hari).
            $count = $svc->syncIntraday($symbol, $interval, $this->from, $this->to);
            $this->stdout("Synced intraday ({$interval}) {$symbol}: {$count} bars\n", Console::FG_GREEN);
        } catch (\Throwable $e) {
            $this->stdout("Gagal {$symbol}: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        return ExitCode::OK;
    }

    /**
     * Refresh harian SEMUA simbol aktif — incremental (delta dari max(date)).
     * Dijalankan via cron tiap hari setelah market close (NYSE ~04:00 WIB).
     * Contoh: php yii data/refresh-all [--limit=50] [--sleepMs=1500]
     */
    public function actionRefreshAll(?int $limit = null, ?int $sleepMs = null): int
    {
        $limit ??= $this->limit;
        $sleepMs ??= ($this->sleepMs ?: 1200);
        $q = Stock::find()->where(['active' => true])->orderBy(['symbol' => SORT_ASC]);
        if ($this->offset > 0) {
            $q->offset($this->offset);
        }
        if ($limit > 0) {
            $q->limit($limit);
        }
        $symbols = $q->select('symbol')->column();
        if ($symbols === []) {
            $this->stdout("No active symbols. Jalankan `php yii market/refresh-stocklist` dulu.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $svc = new MarketDataService(Yii::$app->get('marketData')->get());
        $ok = 0;
        $fail = [];
        foreach ($symbols as $i => $sym) {
            try {
                // Incremental: syncSymbol lanjut dari max(date)-5 hari bila --from kosong.
                $n = $svc->syncSymbol($sym, $this->from, $this->to);
                $this->stdout(sprintf('[%d/%d] %s: %d bars', $i + 1, count($symbols), $sym, $n) . "\n", Console::FG_GREEN);
                $ok++;
            } catch (\Throwable $e) {
                $this->stdout(sprintf('[%d/%d] %s GAGAL: %s', $i + 1, count($symbols), $sym, $e->getMessage()) . "\n", Console::FG_RED);
                $fail[] = $sym;
            }
            if ($sleepMs > 0 && $i < count($symbols) - 1) {
                usleep($sleepMs * 1000); // hormati rate limit Yahoo (~30/menit)
            }
        }
        $this->stdout("Done daily: {$ok} ok, " . count($fail) . ' gagal' . ($fail ? ' (' . implode(',', $fail) . ')' : '') . "\n", $fail ? Console::FG_YELLOW : Console::FG_GREEN);
        if ($ok > 0) {
            \app\services\ScannerCacheService::invalidateVersions();
        }
        return $fail ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Sync intraday (1h) SEMUA simbol aktif — incremental 30 hari default.
     * Dijalankan via cron tiap 1-4 jam pada jam market. Kuota Yahoo ~30 req/menit.
     * Contoh: php yii data/sync-intraday-all --interval=1h --limit=20
     */
    public function actionSyncIntradayAll(?string $interval = null, ?int $limit = null, ?int $sleepMs = null): int
    {
        $interval ??= $this->interval;
        $limit ??= $this->limit;
        $sleepMs ??= ($this->sleepMs ?: 2500);
        $provider = Yii::$app->get('marketData')->get();
        if (!method_exists($provider, 'getIntradayBars')) {
            $this->stdout("Provider aktif tidak mendukung intraday. Ganti ke yahoo_finance.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $q = Stock::find()->where(['active' => true])->orderBy(['symbol' => SORT_ASC]);
        if ($this->offset > 0) {
            $q->offset($this->offset);
        }
        if ($limit > 0) {
            $q->limit($limit);
        }
        $symbols = $q->select('symbol')->column();
        if ($symbols === []) {
            $this->stdout("No active symbols.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $svc = new MarketDataService($provider);
        $ok = 0;
        $fail = [];
        foreach ($symbols as $i => $sym) {
            try {
                $n = $svc->syncIntraday($sym, $interval, $this->from, $this->to);
                $this->stdout(sprintf('[%d/%d] %s: %d bars', $i + 1, count($symbols), $sym, $n) . "\n", Console::FG_GREEN);
                $ok++;
            } catch (\Throwable $e) {
                $this->stdout(sprintf('[%d/%d] %s GAGAL: %s', $i + 1, count($symbols), $sym, $e->getMessage()) . "\n", Console::FG_RED);
                $fail[] = $sym;
            }
            if ($sleepMs > 0 && $i < count($symbols) - 1) {
                usleep($sleepMs * 1000);
            }
        }
        $this->stdout("Done intraday: {$ok} ok, " . count($fail) . ' gagal' . ($fail ? ' (' . implode(',', $fail) . ')' : '') . "\n", $fail ? Console::FG_YELLOW : Console::FG_GREEN);
        if ($ok > 0) {
            \app\services\ScannerCacheService::invalidateVersions();
        }
        return $fail ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Pipeline klasifikasi buy/sell + agregasi mingguan + scoring (Modul 3+4).
     * Contoh: php yii data/analyze [--limit=50]
     */
    public function actionAnalyze(?string $symbol = null, ?int $limit = null): int
    {
        $limit ??= $this->limit;
        $analyzer = new VolumeAnalyzerService();
        $engine = new SignalEngineService();

        if ($symbol !== null) {
            $stocks = Stock::findAll(['symbol' => MarketDataService::normalizeSymbol($symbol)]);
        } else {
            $q = Stock::find()->where(['active' => true])->orderBy(['symbol' => SORT_ASC]);
            if ($this->offset > 0) {
                $q->offset($this->offset);
            }
            if ($limit > 0) {
                $q->limit($limit);
            }
            $stocks = $q->all();
        }

        $total = count($stocks);
        foreach ($stocks as $i => $stock) {
            $weeks = $analyzer->analyzeStock($stock->id);
            $scored = $engine->scoreStock($stock);
            $this->stdout(sprintf('[%d/%d] Analyzed %s: %d weeks, %d scored', $i + 1, $total, $stock->symbol, $weeks, $scored) . "\n", Console::FG_GREEN);
            // Jeda mikro (15ms) agar CPU tidak 100% dan halaman web tetap cepat responsif
            usleep(15000);
        }

        return ExitCode::OK;
    }

    /**
     * Sinkronisasi Batch Bergilir Otomatis (Next Batch of 50 Stocks).
     * Sangat cocok untuk cron tiap jam:
     * Putaran 1: Saham 1-50, Putaran 2: 51-100, dst.
     * Alur: Sync Daily Data -> Analisis Volume & Scoring -> Sync Intraday 1H.
     * Contoh: php yii data/refresh-next-batch [--limit=50]
     */
    public function actionRefreshNextBatch(?int $limit = null): int
    {
        $batchSize = $limit ?: ($this->limit > 0 ? $this->limit : 50);
        $totalStocks = (int) Stock::find()->where(['active' => true])->count();
        if ($totalStocks === 0) {
            $this->stdout("Tidak ada saham aktif. Jalankan php yii market/refresh-stocklist dulu.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        // Ambil offset terakhir dari state/setting
        $currentOffset = (int) \app\models\Setting::getValue('sync_rolling_batch_offset', 0);
        if ($currentOffset >= $totalStocks) {
            $currentOffset = 0;
        }

        $stocks = Stock::find()
            ->where(['active' => true])
            ->orderBy(['symbol' => SORT_ASC])
            ->offset($currentOffset)
            ->limit($batchSize)
            ->all();

        $count = count($stocks);
        $endIndex = $currentOffset + $count;
        $this->stdout(sprintf("=== MEMULAI BATCH BERGILIR: Saham #%d s/d #%d (Total %d dari %d saham aktif) ===\n", 
            $currentOffset + 1, $endIndex, $count, $totalStocks), Console::FG_CYAN, Console::BOLD);

        $provider = Yii::$app->get('marketData')->get();
        $svc = new MarketDataService($provider);
        $analyzer = new VolumeAnalyzerService();
        $engine = new SignalEngineService();
        $hasIntraday = method_exists($provider, 'getIntradayBars');

        $successCount = 0;
        $failSymbols = [];

        foreach ($stocks as $idx => $stock) {
            $sym = $stock->symbol;
            $num = $idx + 1;
            $this->stdout(sprintf("[%d/%d] Memproses %s (%s)... ", $num, $count, $sym, $stock->name), Console::FG_YELLOW);

            try {
                // 1. Tarik candle harian terbaru (incremental)
                $dailyBars = $svc->syncSymbol($sym, $this->from, $this->to);

                // 2. Analisis volume & scoring sinyal
                $weeks = $analyzer->analyzeStock($stock->id);
                $scored = $engine->scoreStock($stock);

                // 3. Tarik candle 1H intraday terbaru
                $intraBars = 0;
                if ($hasIntraday) {
                    try {
                        $intraBars = $svc->syncIntraday($sym, '1h', $this->from, $this->to);
                    } catch (\Throwable $ie) {
                        // toleransi kegagalan intraday agar batch tidak batal
                    }
                }

                $this->stdout(sprintf("OK (Daily: %d bars, Score: OK, 1H: %d bars)\n", $dailyBars, $intraBars), Console::FG_GREEN);
                $successCount++;
            } catch (\Throwable $e) {
                $this->stdout("GAGAL: " . $e->getMessage() . "\n", Console::FG_RED);
                $failSymbols[] = $sym;
            }

            // Jeda mikro antar saham (600ms) untuk menghormati rate limit API tanpa lemot
            usleep(600000);
        }

        // Update offset untuk eksekusi batch berikutnya
        $nextOffset = $endIndex >= $totalStocks ? 0 : $endIndex;
        \app\models\Setting::setValue('sync_rolling_batch_offset', (string) $nextOffset, 'State offset batch sinkronisasi bertahap');

        $this->stdout(sprintf("\n=== BATCH SELESAI: %d Sukses, %d Gagal. Offset berikutnya: #%d (%s) ===\n",
            $successCount, count($failSymbols), $nextOffset + 1, 
            $nextOffset === 0 ? 'Siklus penuh selesai, reset ke awal' : 'Lanjut jam berikutnya'
        ), $failSymbols ? Console::FG_YELLOW : Console::FG_GREEN, Console::BOLD);

        if ($successCount > 0) {
            \app\services\ScannerCacheService::invalidateVersions();
        }

        return ExitCode::OK;
    }
}
