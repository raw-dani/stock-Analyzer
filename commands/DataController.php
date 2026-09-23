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
        return array_merge(parent::options($actionID), ['from', 'to', 'limit', 'sleepMs', 'interval']);
    }

    public int $limit = 0;
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
        return $fail ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Pipeline klasifikasi buy/sell + agregasi mingguan + scoring (Modul 3+4).
     */
    public function actionAnalyze(?string $symbol = null): int
    {
        $analyzer = new VolumeAnalyzerService();
        $engine = new SignalEngineService();

        $stocks = $symbol !== null
            ? Stock::findAll(['symbol' => MarketDataService::normalizeSymbol($symbol)])
            : Stock::findAll(['active' => true]);

        foreach ($stocks as $stock) {
            $weeks = $analyzer->analyzeStock($stock->id);
            $scored = $engine->scoreStock($stock);
            $this->stdout("Analyzed {$stock->symbol}: {$weeks} weeks, {$scored} scored\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}
