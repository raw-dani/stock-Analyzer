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
 * Console untuk pipeline data (task 2.4, 3.7).
 *
 * php yii data/fetch              # sync semua simbol aktif (via queue)
 * php yii data/fetch NVDA         # sync satu simbol
 * php yii data/sync NVDA          # sync langsung tanpa queue (debug)
 * php yii data/analyze            # klasifikasi + agregasi + scoring (Modul 3+4)
 */
final class DataController extends Controller
{
    public ?string $from = null;
    public ?string $to = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['from', 'to']);
    }

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
