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
    public int $force = 0;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'strategy',
            'timeframe',
            'generations',
            'population',
            'runId',
            'force',
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

    /**
     * Screening otomatis seluruh saham dengan model AI terlatih pasca penutupan bursa.
     *
     * Contoh:
     *   php yii learning/screen
     *   php yii learning/screen --force=1
     *
     * @param int|null $force 1 untuk paksa evaluasi ulang tanpa cache
     * @return int
     */
    public function actionScreen(?int $force = null): int
    {
        $forceParam = $force !== null ? (bool)$force : (bool)$this->force;
        $screener = new \app\services\learning\LearningScreenerService();

        $this->stdout("=== AI Stock Learning Daily Screener ===\n", Console::FG_CYAN, Console::BOLD);
        $this->stdout("Memindai seluruh saham dengan model AI terlatih terhadap candle harian terbaru...\n");

        $start = microtime(true);
        $result = $screener->screenAll($forceParam);
        $duration = round(microtime(true) - $start, 2);

        $this->stdout(sprintf(
            "Hasil Pemindaian (%s | %s detik):\nTotal Model: %d | Sinyal Beli (BUY): %d | Menunggu (WAIT): %d\n\n",
            $result['scanned_at_formatted'],
            $duration,
            $result['total_scanned'],
            $result['buy_count'],
            $result['wait_count']
        ), Console::BOLD);

        if (!empty($result['buy_signals'])) {
            $this->stdout("🔥 REKOMENDASI SINYAL BELI HARI INI (BUY):\n", Console::FG_GREEN, Console::BOLD);
            $this->stdout(str_repeat('-', 90) . "\n");
            $this->stdout(sprintf("%-6s | %-10s | %-10s | %-10s | %-6s | %-12s | %-6s\n", "SIMBOL", "HARGA", "TARGET", "STOP LOSS", "R:R", "OOS WIN-RATE", "PF"));
            $this->stdout(str_repeat('-', 90) . "\n");

            foreach ($result['buy_signals'] as $s) {
                $this->stdout(sprintf(
                    "%-6s | $%-9.2f | $%-9.2f | $%-9.2f | 1:%-4.1f | %-12s | %-6s\n",
                    $s['symbol'],
                    $s['current_price'],
                    $s['target_price'],
                    $s['stop_loss'],
                    $s['risk_reward'],
                    $s['oos_win_rate'] !== null ? $s['oos_win_rate'] . '%' : '-',
                    $s['profit_factor'] ?? '-'
                ), Console::FG_GREEN);
            }
            $this->stdout(str_repeat('-', 90) . "\n\n");
        } else {
            $this->stdout("ℹ Tidak ada sinyal beli yang aktif hari ini dari saham-saham terlatih.\n\n", Console::FG_YELLOW);
        }

        if (!empty($result['wait_signals'])) {
            $this->stdout("⏳ SAHAM DALAM MONITORING (WAIT - Menunggu Syarat Terpenuhi):\n", Console::FG_GREY);
            foreach (array_slice($result['wait_signals'], 0, 10) as $w) {
                $note = $w['diagnostics']['summary'] ?? 'Menunggu syarat indikator terpenuhi';
                $this->stdout(sprintf("  • %-6s : $%-8.2f | %s\n", $w['symbol'], $w['current_price'], $note), Console::FG_GREY);
            }
            if (count($result['wait_signals']) > 10) {
                $this->stdout("  ... dan " . (count($result['wait_signals']) - 10) . " saham lainnya.\n", Console::FG_GREY);
            }
            $this->stdout("\n");
        }

        $this->stdout("✓ Selesai. Hasil screening tersimpan di cache dan siap diakses di web (/stock-learning).\n", Console::FG_CYAN);
        return ExitCode::OK;
    }
}
