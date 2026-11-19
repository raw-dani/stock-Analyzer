<?php

declare(strict_types=1);

namespace app\services\learning;

use app\models\Stock;
use app\models\StockLearningGeneration;
use app\models\StockLearningRun;
use Yii;

/**
 * GeneticOptimizerService
 *
 * Mesin utama algoritma genetika (Genetic Algorithm) untuk mencari dan menemukan
 * parameter strategi trading terbaik (Buy/Sell Optimization) untuk satu kode saham.
 */
final class GeneticOptimizerService
{
    private FitnessEvaluatorService $evaluator;
    private WalkForwardValidator $validator;

    public function __construct(
        ?FitnessEvaluatorService $evaluator = null,
        ?WalkForwardValidator $validator = null
    ) {
        $this->evaluator = $evaluator ?? new FitnessEvaluatorService();
        $this->validator = $validator ?? new WalkForwardValidator();
    }

    /**
     * Jalankan proses pembelajaran untuk satu saham dan simpan ke database.
     *
     * @param Stock $stock
     * @param array{
     *     timeframe?: int,
     *     strategy?: string,
     *     generations?: int,
     *     populationSize?: int,
     *     splitRatio?: float,
     *     runId?: int|null
     * } $options
     * @param callable|null $progressCallback function(int $gen, int $maxGen, float $bestFitness, float $bestWr): void
     * @return array<string, mixed>
     */
    public function optimize(Stock $stock, array $options = [], ?callable $progressCallback = null): array
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '600');
        @set_time_limit(600);

        $startTime = microtime(true);
        $timeframe = (int) ($options['timeframe'] ?? WalkForwardValidator::TIMEFRAME_1D);
        $strategy = (string) ($options['strategy'] ?? ChromosomeFactory::STRATEGY_STOCHASTIC);
        $generations = (int) ($options['generations'] ?? 50);
        $populationSize = (int) ($options['populationSize'] ?? 30);
        $splitRatio = (float) ($options['splitRatio'] ?? 0.70);
        $runId = !empty($options['runId']) ? (int) $options['runId'] : null;

        // 1. Ambil & Pecah Data Lilin (In-Sample vs Out-of-Sample)
        $splitData = $this->validator->loadSplitCandles((int) $stock->id, $timeframe, 500, $splitRatio);
        $allCandles = $splitData['allCandles'];
        $totalBars = count($allCandles);
        $splitIndex = (int) floor($totalBars * $splitRatio);

        if ($totalBars < 30) {
            return [
                'success' => false,
                'message' => "Data historis {$stock->symbol} tidak mencukupi untuk pembelajaran (tersedia {$totalBars} candle, butuh minimal 30).",
            ];
        }

        // 2. Inisialisasi Populasi Awal
        $population = ChromosomeFactory::createInitialPopulation($strategy, $populationSize);
        $totalEvals = 0;

        $bestChromosome = null;
        $bestFitness = -1.0;
        $bestInSampleMetrics = [];
        $lastBestFitness = -1.0;
        $stagnantGenerations = 0;

        // 3. Loop Generasi
        for ($gen = 1; $gen <= $generations; $gen++) {
            $evaluatedPopulation = [];
            $fitnessSum = 0.0;

            // Evaluasi fitness seluruh individu di In-Sample dengan Continuous Warmup
            foreach ($population as $chromosome) {
                $metrics = $this->evaluator->evaluate($chromosome, $allCandles, $strategy, 0, $splitIndex, 'IN_SAMPLE');
                $totalEvals++;
                $f = (float) $metrics['fitness'];
                $fitnessSum += $f;

                $evaluatedPopulation[] = [
                    'chromosome' => $chromosome,
                    'metrics' => $metrics,
                    'fitness' => $f,
                ];

                if ($f > $bestFitness) {
                    $bestFitness = $f;
                    $bestChromosome = $chromosome;
                    $bestInSampleMetrics = $metrics;
                }
            }

            // Urutkan individu berdasarkan skor fitness (DESC)
            usort($evaluatedPopulation, fn($a, $b) => $b['fitness'] <=> $a['fitness']);

            $avgFitness = round($fitnessSum / count($evaluatedPopulation), 4);
            $convergenceDelta = round(abs($bestFitness - $lastBestFitness), 6);

            // Simpan log generasi ke DB jika ada runId
            if ($runId !== null) {
                try {
                    $genModel = new StockLearningGeneration();
                    $genModel->run_id = $runId;
                    $genModel->generation = $gen;
                    $genModel->best_win_rate = (float) ($evaluatedPopulation[0]['metrics']['win_rate'] ?? 0);
                    $genModel->best_fitness = $bestFitness;
                    $genModel->best_params = json_encode($evaluatedPopulation[0]['chromosome']);
                    $genModel->avg_fitness = $avgFitness;
                    $genModel->convergence_delta = $convergenceDelta;
                    $genModel->created_at = time();
                    $genModel->save(false);
                } catch (\Throwable $e) {
                    // Abaikan error log agar pembelajaran tidak terhenti
                }
            }

            if ($progressCallback !== null) {
                $progressCallback($gen, $generations, $bestFitness, (float) ($bestInSampleMetrics['win_rate'] ?? 0));
            }

            // Deteksi Early Stopping (jika tidak berkembang selama 15 generasi)
            if ($convergenceDelta < 0.0001) {
                $stagnantGenerations++;
            } else {
                $stagnantGenerations = 0;
            }

            if ($stagnantGenerations >= 15 && $gen >= 25) {
                break; // Konvergensi tercapai lebih awal
            }

            $lastBestFitness = $bestFitness;

            // Seleksi Generasi Berikutnya:
            // a. Elitisme: Top 20% langsung lolos tanpa modifikasi
            $eliteCount = max(2, (int) round($populationSize * 0.20));
            $newPopulation = [];
            for ($i = 0; $i < $eliteCount; $i++) {
                $newPopulation[] = $evaluatedPopulation[$i]['chromosome'];
            }

            // b. Sisa 80% dihasilkan via Tournament Selection + Crossover + Mutasi
            while (count($newPopulation) < $populationSize) {
                $parentA = $this->tournamentSelect($evaluatedPopulation, 3);
                $parentB = $this->tournamentSelect($evaluatedPopulation, 3);

                [$child1, $child2] = ChromosomeFactory::crossover($parentA, $parentB, $strategy);

                $child1 = ChromosomeFactory::mutate($child1, $strategy, 0.15);
                $child2 = ChromosomeFactory::mutate($child2, $strategy, 0.15);

                $newPopulation[] = $child1;
                if (count($newPopulation) < $populationSize) {
                    $newPopulation[] = $child2;
                }
            }

            $population = $newPopulation;
        }

        // 4. Validasi Independen Out-of-Sample (OOS) dengan Continuous Warmup
        $oosMetrics = [];
        if ($bestChromosome !== null && $splitIndex < $totalBars) {
            $oosMetrics = $this->evaluator->evaluate($bestChromosome, $allCandles, $strategy, $splitIndex, $totalBars, 'OUT_OF_SAMPLE');
        } else {
            $oosMetrics = $bestInSampleMetrics;
        }

        $runtime = (int) round(microtime(true) - $startTime);

        // 5. Evaluasi Sinyal Terkini (Current Market Setup)
        $currentSignal = $this->evaluateCurrentSignal($stock, $bestChromosome, $allCandles, $strategy);

        $result = [
            'success' => true,
            'stock' => $stock,
            'symbol' => $stock->symbol,
            'strategy' => $strategy,
            'timeframe' => $timeframe,
            'best_params' => $bestChromosome,
            'best_fitness' => $bestFitness,
            'is_metrics' => $bestInSampleMetrics,
            'oos_metrics' => $oosMetrics,
            'is_win_rate' => $bestInSampleMetrics['win_rate'] ?? 0.0,
            'oos_win_rate' => $oosMetrics['win_rate'] ?? 0.0,
            'total_evals' => $totalEvals,
            'runtime_seconds' => $runtime,
            'current_signal' => $currentSignal,
            'trades' => array_merge($bestInSampleMetrics['trades'] ?? [], $oosMetrics['trades'] ?? []),
        ];

        $oosTradeCount = count($oosMetrics['trades'] ?? []);
        $isTradeCount = count($bestInSampleMetrics['trades'] ?? []);

        // Update record StockLearningRun jika ada runId
        if ($runId !== null) {
            $run = StockLearningRun::findOne($runId);
            if ($run !== null) {
                $run->status = StockLearningRun::STATUS_COMPLETED;
                $run->best_params = json_encode($bestChromosome);
                $run->best_fitness = $bestFitness;

                // Jika OOS memiliki transaksi (>0 trade), gunakan metrik validasi independen OOS
                // Jika OOS tidak memiliki transaksi (0 trade), set oos_win_rate = null (bukan 0.0%) agar tidak menyesatkan
                if ($oosTradeCount > 0) {
                    $run->oos_win_rate = (float) $oosMetrics['win_rate'];
                    $run->best_win_rate = (float) $oosMetrics['win_rate'];
                    $run->profit_factor = (float) $oosMetrics['profit_factor'];
                    $run->max_drawdown = (float) $oosMetrics['max_drawdown'];
                    $run->sharpe_ratio = (float) $oosMetrics['sharpe_ratio'];
                } else {
                    $run->oos_win_rate = null;
                    $run->best_win_rate = (float) ($bestInSampleMetrics['win_rate'] ?? 0.0);
                    $run->profit_factor = (float) ($bestInSampleMetrics['profit_factor'] ?? 1.0);
                    $run->max_drawdown = (float) ($bestInSampleMetrics['max_drawdown'] ?? 0.0);
                    $run->sharpe_ratio = (float) ($bestInSampleMetrics['sharpe_ratio'] ?? 0.0);
                }

                $run->is_win_rate = (float) ($bestInSampleMetrics['win_rate'] ?? 0.0);
                $run->total_trades = $isTradeCount + $oosTradeCount;
                $run->total_evals = $totalEvals;
                $run->runtime_seconds = $runtime;
                $run->save(false);
            }
        }

        return $result;
    }

    /**
     * Tournament Selection: Memilih 1 individu terbaik dari K kandidat acak.
     *
     * @param array<int, array{chromosome: array, fitness: float}> $evaluated
     * @param int $k
     * @return array<string, mixed>
     */
    private function tournamentSelect(array $evaluated, int $k = 3): array
    {
        $best = null;
        $maxFitness = -PHP_FLOAT_MAX;
        $count = count($evaluated);

        for ($i = 0; $i < $k; $i++) {
            $idx = random_int(0, $count - 1);
            $candidate = $evaluated[$idx];
            if ($candidate['fitness'] > $maxFitness) {
                $maxFitness = $candidate['fitness'];
                $best = $candidate['chromosome'];
            }
        }

        return $best ?? $evaluated[0]['chromosome'];
    }

    /**
     * Cek sinyal trading saat ini (Current Market Setup) menggunakan best chromosome.
     *
     * @param Stock $stock
     * @param array<string, mixed> $params
     * @param array<int, array<string, mixed>> $allCandles
     * @param string $strategy
     * @return array<string, mixed>
     */
    private function evaluateCurrentSignal(Stock $stock, array $params, array $allCandles, string $strategy): array
    {
        $n = count($allCandles);
        if ($n < 2) {
            return [
                'action' => 'WAIT',
                'is_active' => false,
                'title' => 'Menunggu Data',
                'badge_class' => 'secondary',
                'confidence' => 0,
                'reason' => 'Data lilin tidak mencukupi'
            ];
        }

        $lastCandle = end($allCandles);
        $currentPrice = (float) $lastCandle['close'];
        $tpPct = (float) ($params['tp_target_pct'] ?? 4.0);
        $slPct = (float) ($params['sl_pct'] ?? 2.0);

        $targetPrice = round($currentPrice * (1.0 + ($tpPct / 100.0)), 2);
        $slPrice = round($currentPrice * (1.0 - ($slPct / 100.0)), 2);

        // Periksa apakah lilin terakhir benar-benar memicu sinyal beli
        $signals = $this->evaluator->generateBuySignals($params, $allCandles, $strategy);
        $isSignalActive = !empty($signals[$n - 1]) || !empty($signals[$n - 2]);
        $diagnostics = $this->evaluator->diagnoseCurrentSignal($params, $allCandles, $strategy);

        return [
            'action' => $isSignalActive ? 'BUY_ACTIVE' : 'WAIT',
            'is_active' => $isSignalActive,
            'title' => $isSignalActive ? 'Sinyal Beli Aktif Sekarang! (Entry Terkonfirmasi)' : 'Status Saat Ini: Menunggu Sinyal Terbentuk (WAIT)',
            'badge_class' => $isSignalActive ? 'success' : 'warning text-dark',
            'current_price' => $currentPrice,
            'target_price' => $targetPrice,
            'target_pct' => $tpPct,
            'stop_loss' => $slPrice,
            'stop_loss_pct' => $slPct,
            'risk_reward' => round($tpPct / max(0.1, $slPct), 2),
            'last_candle_date' => $lastCandle['date'] ?? null,
            'diagnostics' => $diagnostics,
        ];
    }
}
