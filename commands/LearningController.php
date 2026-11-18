<?php

declare(strict_types=1);

namespace app\commands;

use app\models\Stock;
use app\models\StockLearningRun;
use app\services\learning\ChromosomeFactory;
use app\services\learning\GeneticOptimizerService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Controller console untuk menjalankan proses Stock Learning Engine di background
 * maupun secara terjadwal/manual via CLI.
 */
class LearningController extends Controller
{
    public string $strategy = ChromosomeFactory::STRATEGY_STOCHASTIC;
    public int $timeframe = 24;
    public int $generations = 50;
    public int $population = 30;
    public ?int $runId = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'strategy',
            'timeframe',
            'generations',
            'population',
            'runId',
        ]);
    }

    /**
     * Jalankan proses pembelajaran untuk satu saham.
     *
     * Contoh:
     *   php yii learning/run AAPL --strategy=stochastic --generations=50
     *
     * @param string $symbol Kode saham (e.g. AAPL, BBCA.JK)
     * @return int ExitCode
     */
    public function actionRun(string $symbol): int
    {
        $symbol = strtoupper(trim($symbol));
        $strategy = $this->strategy;
        $timeframe = (int) $this->timeframe;
        $generations = (int) $this->generations;
        $population = (int) $this->population;
        $runId = $this->runId ? (int) $this->runId : null;
        $this->stdout("=== Stock Learning Engine ===\n", Console::FG_CYAN, Console::BOLD);
        $this->stdout("Target: {$symbol} | Strategi: {$strategy} | TF: {$timeframe} | Gen: {$generations} | Pop: {$population}\n");

        $stock = Stock::findOne(['symbol' => $symbol]);
        if ($stock === null) {
            $this->stderr("Error: Saham {$symbol} tidak ditemukan di database.\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        // Buat atau ambil record StockLearningRun
        $run = null;
        if ($runId !== null) {
            $run = StockLearningRun::findOne($runId);
        }

        if ($run === null) {
            $run = new StockLearningRun();
            $run->stock_id = (int) $stock->id;
            $run->symbol = $symbol;
            $run->timeframe = $timeframe;
            $run->strategy_type = $strategy;
            $run->generation_max = $generations;
            $run->population_size = $population;
            $run->status = StockLearningRun::STATUS_RUNNING;
            $run->created_at = time();
            $run->updated_at = time();
            $run->save(false);
            $runId = (int) $run->id;
        } else {
            $run->status = StockLearningRun::STATUS_RUNNING;
            $run->updated_at = time();
            $run->save(false);
        }

        $optimizer = new GeneticOptimizerService();

        try {
            $result = $optimizer->optimize($stock, [
                'timeframe' => $timeframe,
                'strategy' => $strategy,
                'generations' => $generations,
                'populationSize' => $population,
                'runId' => $runId,
            ], function ($gen, $max, $fitness, $wr) {
                $pct = round(($gen / $max) * 100);
                $this->stdout("  [{$pct}%] Gen {$gen}/{$max} - Best Fitness: {$fitness} | In-Sample WR: {$wr}%\n", Console::FG_GREY);
            });

            if (!$result['success']) {
                $this->stderr("Gagal: " . ($result['message'] ?? 'Unknown error') . "\n", Console::FG_RED);
                $run->status = StockLearningRun::STATUS_FAILED;
                $run->error_message = $result['message'] ?? 'Unknown error';
                $run->save(false);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $this->stdout("\n✓ Pembelajaran Selesai!\n", Console::FG_GREEN, Console::BOLD);
            $this->stdout("  • In-Sample Win-Rate   : {$result['is_win_rate']}%\n");
            $this->stdout("  • Out-of-Sample Win-Rate: {$result['oos_win_rate']}%\n");
            $this->stdout("  • Best Fitness Score   : {$result['best_fitness']}\n");
            $this->stdout("  • Waktu Komputasi      : {$result['runtime_seconds']} detik\n");
            $this->stdout("  • Parameter Optimal    : " . json_encode($result['best_params']) . "\n\n");

            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr("Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", Console::FG_RED);
            $run->status = StockLearningRun::STATUS_FAILED;
            $run->error_message = $e->getMessage();
            $run->save(false);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Tampilkan histori dan status pembelajaran terakhir.
     *
     * @param string|null $symbol
     * @return int
     */
    public function actionStatus(?string $symbol = null): int
    {
        $query = StockLearningRun::find()->orderBy(['id' => SORT_DESC])->limit(15);
        if ($symbol !== null) {
            $query->andWhere(['symbol' => strtoupper(trim($symbol))]);
        }

        $runs = $query->all();
        if (empty($runs)) {
            $this->stdout("Belum ada histori sesi pembelajaran.\n");
            return ExitCode::OK;
        }

        $this->stdout("Histori Pembelajaran Terakhir:\n", Console::BOLD);
        foreach ($runs as $r) {
            $statusColor = match ($r->status) {
                StockLearningRun::STATUS_COMPLETED => Console::FG_GREEN,
                StockLearningRun::STATUS_RUNNING => Console::FG_YELLOW,
                StockLearningRun::STATUS_FAILED => Console::FG_RED,
                default => Console::FG_GREY,
            };

            $this->stdout(sprintf(
                "#%-4d %-6s | %-10s | OOS WR: %-6s | IS WR: %-6s | PF: %-4s | Status: ",
                $r->id,
                $r->symbol,
                $r->strategy_type,
                $r->oos_win_rate !== null ? $r->oos_win_rate . '%' : '-',
                $r->is_win_rate !== null ? $r->is_win_rate . '%' : '-',
                $r->profit_factor ?? '-'
            ));
            $this->stdout(strtoupper($r->status) . "\n", $statusColor);
        }

        return ExitCode::OK;
    }
}
