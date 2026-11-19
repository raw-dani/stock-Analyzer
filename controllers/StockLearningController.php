<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Stock;
use app\models\StockLearningGeneration;
use app\models\StockLearningRun;
use app\services\learning\ChromosomeFactory;
use app\services\learning\GeneticOptimizerService;
use app\services\learning\WalkForwardValidator;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller untuk Stock Learning Engine:
 * Melatih model trading adaptif berbasis Genetic Algorithm per kode saham.
 */
class StockLearningController extends Controller
{
    /**
     * Halaman Utama: Daftar riwayat pembelajaran seluruh saham (Mendukung Grouped per Saham & All History).
     */
    public function actionIndex(): Response|string
    {
        $request = Yii::$app->request;
        $viewMode = $request->get('view', 'grouped'); // 'grouped' (default), 'screener', atau 'history'
        $sortBy = trim((string) $request->get('sort', 'profit_factor')); // 'profit_factor' (default), 'latest', 'win_rate', 'symbol'
        $modelMode = trim((string) $request->get('model_mode', 'best_pf')); // 'best_pf' (default) atau 'latest'
        $symbolSearch = trim((string) $request->get('symbol', ''));
        $strategyFilter = trim((string) $request->get('strategy', ''));
        $statusFilter = trim((string) $request->get('status', ''));

        // Screener Harian Otomatis
        $screener = new \app\services\learning\LearningScreenerService();
        if ($request->get('refresh_screener')) {
            $screener->screenAll(true);
            Yii::$app->session->setFlash('success', 'Screening harian AI berhasil diperbarui!');
            return $this->redirect(['index', 'view' => 'screener']);
        }
        $screenerResults = $screener->screenAll(false);

        // 1. Data Mode Grouped (1 Baris per Kode Saham — Menampilkan Model Terbaik / Terbaru)
        $groupedData = [];
        $symbolsQuery = StockLearningRun::find()
            ->select(['symbol', 'stock_id'])
            ->distinct();

        if (!empty($symbolSearch)) {
            $symbolsQuery->andWhere(['like', 'symbol', strtoupper($symbolSearch)]);
        }

        $distinctStocks = $symbolsQuery->asArray()->all();

        foreach ($distinctStocks as $ds) {
            $sym = $ds['symbol'];
            $stockId = (int) $ds['stock_id'];

            // Query run terbaik untuk simbol ini
            $bestRunQuery = StockLearningRun::find()
                ->where(['symbol' => $sym, 'status' => StockLearningRun::STATUS_COMPLETED]);

            if (!empty($strategyFilter)) {
                $bestRunQuery->andWhere(['strategy_type' => $strategyFilter]);
            }

            // Jika mode model adalah 'latest', ambil model completed terbaru
            if ($modelMode === 'latest') {
                $bestRun = (clone $bestRunQuery)->orderBy(['id' => SORT_DESC])->one();
            } else {
                // Default 'best_pf': Prioritaskan model dengan Profit Factor tertinggi, lalu OOS Win Rate tertinggi
                $bestRun = (clone $bestRunQuery)->orderBy([
                    'profit_factor' => SORT_DESC,
                    'oos_win_rate' => SORT_DESC,
                    'best_fitness' => SORT_DESC,
                    'id' => SORT_DESC,
                ])->one();
            }

            // Fallback jika belum ada yang completed atau filter tertentu
            if (!$bestRun) {
                $fallbackQuery = StockLearningRun::find()->where(['symbol' => $sym]);
                if (!empty($strategyFilter)) {
                    $fallbackQuery->andWhere(['strategy_type' => $strategyFilter]);
                }
                if (!empty($statusFilter)) {
                    $fallbackQuery->andWhere(['status' => $statusFilter]);
                }
                $bestRun = $fallbackQuery->orderBy(['id' => SORT_DESC])->one();
            }

            if (!$bestRun) {
                continue;
            }

            $totalSessions = StockLearningRun::find()->where(['symbol' => $sym])->count();
            $latestRun = StockLearningRun::find()->where(['symbol' => $sym])->orderBy(['id' => SORT_DESC])->one();
            $stock = Stock::findOne($stockId);

            $groupedData[] = [
                'symbol' => $sym,
                'stock' => $stock,
                'stock_name' => $stock ? $stock->name : '',
                'best_run' => $bestRun,
                'latest_run' => $latestRun,
                'total_sessions' => (int) $totalSessions,
                'latest_created_at' => (int) ($latestRun ? $latestRun->created_at : $bestRun->created_at),
            ];
        }

        // Urutkan groupedData sesuai opsi sorting yang dipilih user
        usort($groupedData, function ($a, $b) use ($sortBy) {
            if ($sortBy === 'latest') {
                // Saham yang baru saja dilatih berada di posisi paling atas
                return $b['latest_created_at'] <=> $a['latest_created_at'];
            } elseif ($sortBy === 'win_rate') {
                $wrA = (float) ($a['best_run']->oos_win_rate ?? 0);
                $wrB = (float) ($b['best_run']->oos_win_rate ?? 0);
                if ($wrA !== $wrB) {
                    return $wrB <=> $wrA;
                }
                $pfA = (float) ($a['best_run']->profit_factor ?? 0);
                $pfB = (float) ($b['best_run']->profit_factor ?? 0);
                if ($pfA !== $pfB) {
                    return $pfB <=> $pfA;
                }
                return $b['latest_created_at'] <=> $a['latest_created_at'];
            } elseif ($sortBy === 'symbol') {
                return strcmp($a['symbol'], $b['symbol']);
            } else {
                // Default: 'profit_factor' (Saham dengan Profit Factor tertinggi di paling atas)
                $pfA = (float) ($a['best_run']->profit_factor ?? 0);
                $pfB = (float) ($b['best_run']->profit_factor ?? 0);
                if ($pfA !== $pfB) {
                    return $pfB <=> $pfA;
                }
                $wrA = (float) ($a['best_run']->oos_win_rate ?? 0);
                $wrB = (float) ($b['best_run']->oos_win_rate ?? 0);
                if ($wrA !== $wrB) {
                    return $wrB <=> $wrA;
                }
                return $b['latest_created_at'] <=> $a['latest_created_at'];
            }
        });

        // 2. Data Mode History (Audit Trail Log Seluruh Percobaan)
        $historyQuery = StockLearningRun::find();
        if (!empty($symbolSearch)) {
            $historyQuery->andWhere(['like', 'symbol', strtoupper($symbolSearch)]);
        }
        if (!empty($strategyFilter)) {
            $historyQuery->andWhere(['strategy_type' => $strategyFilter]);
        }
        if (!empty($statusFilter)) {
            $historyQuery->andWhere(['status' => $statusFilter]);
        }

        if ($sortBy === 'profit_factor') {
            $historyQuery->orderBy(['profit_factor' => SORT_DESC, 'id' => SORT_DESC]);
        } elseif ($sortBy === 'win_rate') {
            $historyQuery->orderBy(['oos_win_rate' => SORT_DESC, 'profit_factor' => SORT_DESC, 'id' => SORT_DESC]);
        } else {
            // Default latest ID
            $historyQuery->orderBy(['id' => SORT_DESC]);
        }

        $historyRuns = $historyQuery->limit(80)->all();

        // Hitung statistik agregat (hanya yang memiliki transaksi trade)
        $totalTrained = StockLearningRun::find()->where(['status' => StockLearningRun::STATUS_COMPLETED])->count();
        $totalUniqueStocks = StockLearningRun::find()->select('symbol')->distinct()->count();
        $avgOosWr = StockLearningRun::find()
            ->where(['status' => StockLearningRun::STATUS_COMPLETED])
            ->andWhere(['>', 'total_trades', 0])
            ->average('oos_win_rate') ?: 0;
        $highWrCount = StockLearningRun::find()
            ->where(['status' => StockLearningRun::STATUS_COMPLETED])
            ->andWhere(['>=', 'oos_win_rate', 60.0])
            ->andWhere(['>', 'total_trades', 0])
            ->count();

        // Cari Profit Factor tertinggi di seluruh database
        $maxPfRun = StockLearningRun::find()
            ->where(['status' => StockLearningRun::STATUS_COMPLETED])
            ->andWhere(['>', 'total_trades', 0])
            ->orderBy(['profit_factor' => SORT_DESC, 'id' => SORT_DESC])
            ->one();
        $maxProfitFactor = $maxPfRun ? (float) $maxPfRun->profit_factor : 0.0;
        $maxPfSymbol = $maxPfRun ? $maxPfRun->symbol : '-';

        // Cari sesi latihan terbaru di seluruh database
        $latestRunOverall = StockLearningRun::find()->orderBy(['id' => SORT_DESC])->one();
        $latestTrainedAt = $latestRunOverall ? $latestRunOverall->created_at : null;
        $latestSymbol = $latestRunOverall ? $latestRunOverall->symbol : '-';

        return $this->render('index', [
            'viewMode' => $viewMode,
            'sortBy' => $sortBy,
            'modelMode' => $modelMode,
            'groupedData' => $groupedData,
            'runs' => $historyRuns,
            'symbolSearch' => $symbolSearch,
            'strategyFilter' => $strategyFilter,
            'statusFilter' => $statusFilter,
            'stats' => [
                'total_trained' => (int) $totalTrained,
                'total_unique_stocks' => (int) $totalUniqueStocks,
                'avg_oos_wr' => round((float) $avgOosWr, 1),
                'high_wr_count' => (int) $highWrCount,
                'max_profit_factor' => round($maxProfitFactor, 2),
                'max_pf_symbol' => $maxPfSymbol,
                'latest_trained_at' => $latestTrainedAt,
                'latest_symbol' => $latestSymbol,
            ],
            'strategies' => [
                ChromosomeFactory::STRATEGY_STOCHASTIC => 'Stochastic Oscillator',
                ChromosomeFactory::STRATEGY_RSI => 'RSI Momentum & Reversal',
                ChromosomeFactory::STRATEGY_MULTI => 'Multi-Indicator Confluence',
            ],
            'screenerResults' => $screenerResults,
        ]);
    }

    /**
     * Halaman Detail Pembelajaran per Kode Saham.
     */
    public function actionDetail(?string $symbol = null): Response|string
    {
        $request = Yii::$app->request;
        $symbol = $symbol ?? $request->get('symbol');
        if (empty($symbol)) {
            return $this->redirect(['index']);
        }
        $symbol = strtoupper(trim((string) $symbol));

        $stock = Stock::findOne(['symbol' => $symbol]);
        if ($stock === null) {
            throw new NotFoundHttpException("Saham {$symbol} tidak ditemukan di database.");
        }

        // Ambil run spesifik jika run_id diminta, atau run terbaik/terbaru untuk saham ini
        $runIdParam = (int) $request->get('run_id', 0);
        if ($runIdParam > 0) {
            $latestRun = StockLearningRun::findOne(['id' => $runIdParam, 'stock_id' => $stock->id]);
        } else {
            // Prioritas: model terbaik yang completed (profit factor & wr tertinggi)
            $latestRun = StockLearningRun::find()
                ->where(['stock_id' => $stock->id, 'status' => StockLearningRun::STATUS_COMPLETED])
                ->andWhere(['>', 'total_trades', 0])
                ->orderBy(['profit_factor' => SORT_DESC, 'oos_win_rate' => SORT_DESC, 'id' => SORT_DESC])
                ->one();

            if (!$latestRun) {
                $latestRun = StockLearningRun::find()
                    ->where(['stock_id' => $stock->id, 'status' => StockLearningRun::STATUS_COMPLETED])
                    ->orderBy(['best_fitness' => SORT_DESC, 'id' => SORT_DESC])
                    ->one();
            }

            if (!$latestRun) {
                $latestRun = StockLearningRun::find()
                    ->where(['stock_id' => $stock->id])
                    ->orderBy(['id' => SORT_DESC])
                    ->one();
            }

            // Jika ada run dan URL belum menyertakan run_id, redirect agar URL selalu eksplisit & jelas
            if ($latestRun !== null) {
                return $this->redirect(['detail', 'symbol' => $symbol, 'run_id' => (int) $latestRun->id]);
            }
        }

        // Ambil seluruh riwayat run untuk saham ini
        $allRunsForStock = StockLearningRun::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy(['id' => SORT_DESC])
            ->limit(20)
            ->all();

        $generations = [];
        $currentSignal = null;
        $simulatedTrades = [];

        if ($latestRun !== null && $latestRun->status === StockLearningRun::STATUS_COMPLETED) {
            $generations = StockLearningGeneration::find()
                ->where(['run_id' => $latestRun->id])
                ->orderBy(['generation' => SORT_ASC])
                ->asArray()
                ->all();

            // Hitung sinyal harga terkini & simulasi trade menggunakan parameter optimal
            $params = $latestRun->getDecodedParams();
            if (!empty($params)) {
                $validator = new WalkForwardValidator();
                $candles = $validator->loadAscCandles((int) $stock->id, (int) $latestRun->timeframe, 500);
                if (!empty($candles)) {
                    $evaluator = new \app\services\learning\FitnessEvaluatorService();
                    $n = count($candles);
                    $splitIndex = (int) floor($n * 0.70);

                    // Ambil trade lengkap (In-Sample & Out-of-Sample)
                    $isRes = $evaluator->evaluate($params, $candles, (string) $latestRun->strategy_type, 0, $splitIndex, 'IN_SAMPLE');
                    $oosRes = $evaluator->evaluate($params, $candles, (string) $latestRun->strategy_type, $splitIndex, $n, 'OUT_OF_SAMPLE');

                    $simulatedTrades = array_merge($isRes['trades'] ?? [], $oosRes['trades'] ?? []);
                    // Urutkan trade dari yang paling baru ke lama
                    usort($simulatedTrades, fn($a, $b) => ($b['entry_bar'] ?? 0) <=> ($a['entry_bar'] ?? 0));

                    $lastCandle = end($candles);
                    $currentPrice = (float) $lastCandle['close'];
                    $tpPct = (float) ($params['tp_target_pct'] ?? 4.0);
                    $slPct = (float) ($params['sl_pct'] ?? 2.0);

                    $buySignals = $evaluator->generateBuySignals($params, $candles, (string) $latestRun->strategy_type);
                    $isSignalActive = !empty($buySignals[$n - 1]) || !empty($buySignals[$n - 2]);
                    $diagnostics = $evaluator->diagnoseCurrentSignal($params, $candles, (string) $latestRun->strategy_type);

                    $currentSignal = [
                        'action' => $isSignalActive ? 'BUY_ACTIVE' : 'WAIT',
                        'is_active' => $isSignalActive,
                        'title' => $isSignalActive ? 'Sinyal Beli Aktif Sekarang! (Entry Terkonfirmasi)' : 'Status Saat Ini: Menunggu Sinyal Terbentuk (WAIT)',
                        'badge_class' => $isSignalActive ? 'success' : 'warning text-dark',
                        'current_price' => $currentPrice,
                        'target_price' => round($currentPrice * (1.0 + ($tpPct / 100.0)), 2),
                        'target_pct' => $tpPct,
                        'stop_loss' => round($currentPrice * (1.0 - ($slPct / 100.0)), 2),
                        'stop_loss_pct' => $slPct,
                        'risk_reward' => round($tpPct / max(0.1, $slPct), 2),
                        'last_candle_date' => $lastCandle['date'] ?? null,
                        'diagnostics' => $diagnostics,
                    ];
                }
            }
        }

        return $this->render('detail', [
            'stock' => $stock,
            'latestRun' => $latestRun,
            'generations' => $generations,
            'currentSignal' => $currentSignal,
            'simulatedTrades' => $simulatedTrades,
            'allRunsForStock' => $allRunsForStock,
            'strategies' => [
                ChromosomeFactory::STRATEGY_STOCHASTIC => 'Stochastic Oscillator',
                ChromosomeFactory::STRATEGY_RSI => 'RSI Momentum & Reversal',
                ChromosomeFactory::STRATEGY_MULTI => 'Multi-Indicator Confluence',
            ],
            'timeframes' => [
                24 => 'Daily (1D - Rekomendasi)',
                4  => '4 Jam (4H)',
                1  => '1 Jam (1H)',
            ],
        ]);
    }

    /**
     * Eksekusi pembelajaran untuk saham tertentu.
     */
    public function actionRun(?string $symbol = null)
    {
        $symbol = $symbol ?? Yii::$app->request->get('symbol') ?? Yii::$app->request->post('symbol');
        if (empty($symbol)) {
            Yii::$app->session->setFlash('error', 'Kode saham diperlukan.');
            return $this->redirect(['index']);
        }
        $symbol = strtoupper(trim((string) $symbol));

        $stock = Stock::findOne(['symbol' => $symbol]);
        if ($stock === null) {
            throw new NotFoundHttpException("Saham {$symbol} tidak ditemukan.");
        }

        $request = Yii::$app->request;
        $strategy = (string) ($request->post('strategy') ?? $request->get('strategy', ChromosomeFactory::STRATEGY_STOCHASTIC));
        $timeframe = (int) ($request->post('timeframe') ?? $request->get('timeframe', 24));
        $generations = (int) ($request->post('generations') ?? $request->get('generations', 35));
        $population = (int) ($request->post('population') ?? $request->get('population', 25));

        // Buat record session pembelajaran
        $run = new StockLearningRun();
        $run->stock_id = (int) $stock->id;
        $run->symbol = $symbol;
        $run->strategy_type = $strategy;
        $run->timeframe = $timeframe;
        $run->generation_max = $generations;
        $run->population_size = $population;
        $run->status = StockLearningRun::STATUS_RUNNING;
        $run->created_at = time();
        $run->updated_at = time();
        $run->save(false);

        $optimizer = new GeneticOptimizerService();
        $result = $optimizer->optimize($stock, [
            'strategy' => $strategy,
            'timeframe' => $timeframe,
            'generations' => $generations,
            'populationSize' => $population,
            'runId' => (int) $run->id,
        ]);

        if ($result['success']) {
            $oosTotal = (int) ($result['oos_metrics']['total_trades'] ?? 0);
            $oosWins = (int) ($result['oos_metrics']['wins'] ?? 0);
            $wrText = ($oosTotal > 0)
                ? "Out-of-Sample Win Rate: {$result['oos_win_rate']}% ({$oosWins} Menang / {$oosTotal} Trade)"
                : "In-Sample Win Rate: {$result['is_win_rate']}% (Periode OOS minim sinyal)";
            Yii::$app->session->setFlash('success', "Proses pembelajaran {$symbol} (Sesi #{$run->id}) berhasil! {$wrText}.");
        } else {
            Yii::$app->session->setFlash('warning', "Pembelajaran {$symbol}: " . ($result['message'] ?? 'Data historis terbatas.'));
        }

        return $this->redirect(['detail', 'symbol' => $symbol, 'run_id' => (int) $run->id]);
    }

    /**
     * Endpoint status AJAX polling untuk live monitoring run.
     */
    public function actionStatus(int $runId): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $run = StockLearningRun::findOne($runId);
        if ($run === null) {
            return $this->asJson(['success' => false, 'message' => 'Run not found']);
        }

        $latestGen = StockLearningGeneration::find()
            ->where(['run_id' => $run->id])
            ->orderBy(['generation' => SORT_DESC])
            ->one();

        return $this->asJson([
            'success' => true,
            'run_id' => $run->id,
            'symbol' => $run->symbol,
            'status' => $run->status,
            'current_gen' => $latestGen ? $latestGen->generation : 0,
            'max_gen' => $run->generation_max,
            'best_fitness' => $latestGen ? (float) $latestGen->best_fitness : 0.0,
            'best_win_rate' => $latestGen ? (float) $latestGen->best_win_rate : 0.0,
            'oos_win_rate' => $run->oos_win_rate,
            'is_completed' => ($run->status === StockLearningRun::STATUS_COMPLETED),
        ]);
    }
}
