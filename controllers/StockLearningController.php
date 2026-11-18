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
     * Halaman Utama: Daftar riwayat pembelajaran seluruh saham.
     */
    public function actionIndex(): string
    {
        $request = Yii::$app->request;
        $symbolSearch = trim((string) $request->get('symbol', ''));
        $strategyFilter = trim((string) $request->get('strategy', ''));
        $statusFilter = trim((string) $request->get('status', ''));

        $query = StockLearningRun::find()->orderBy(['id' => SORT_DESC]);

        if (!empty($symbolSearch)) {
            $query->andWhere(['like', 'symbol', strtoupper($symbolSearch)]);
        }
        if (!empty($strategyFilter)) {
            $query->andWhere(['strategy_type' => $strategyFilter]);
        }
        if (!empty($statusFilter)) {
            $query->andWhere(['status' => $statusFilter]);
        }

        $runs = $query->limit(50)->all();

        // Hitung statistik agregat (hanya yang memiliki transaksi trade)
        $totalTrained = StockLearningRun::find()->where(['status' => StockLearningRun::STATUS_COMPLETED])->count();
        $avgOosWr = StockLearningRun::find()
            ->where(['status' => StockLearningRun::STATUS_COMPLETED])
            ->andWhere(['>', 'total_trades', 0])
            ->average('oos_win_rate') ?: 0;
        $highWrCount = StockLearningRun::find()
            ->where(['status' => StockLearningRun::STATUS_COMPLETED])
            ->andWhere(['>=', 'oos_win_rate', 65.0])
            ->andWhere(['>', 'total_trades', 0])
            ->count();

        return $this->render('index', [
            'runs' => $runs,
            'symbolSearch' => $symbolSearch,
            'strategyFilter' => $strategyFilter,
            'statusFilter' => $statusFilter,
            'stats' => [
                'total_trained' => (int) $totalTrained,
                'avg_oos_wr' => round((float) $avgOosWr, 1),
                'high_wr_count' => (int) $highWrCount,
            ],
            'strategies' => [
                ChromosomeFactory::STRATEGY_STOCHASTIC => 'Stochastic Oscillator',
                ChromosomeFactory::STRATEGY_RSI => 'RSI Momentum & Reversal',
                ChromosomeFactory::STRATEGY_MULTI => 'Multi-Indicator Confluence',
            ],
        ]);
    }

    /**
     * Halaman Detail Pembelajaran per Kode Saham.
     */
    public function actionDetail(?string $symbol = null): string
    {
        $symbol = $symbol ?? Yii::$app->request->get('symbol');
        if (empty($symbol)) {
            return $this->redirect(['index']);
        }
        $symbol = strtoupper(trim((string) $symbol));

        $stock = Stock::findOne(['symbol' => $symbol]);
        if ($stock === null) {
            throw new NotFoundHttpException("Saham {$symbol} tidak ditemukan di database.");
        }

        // Ambil run terbaru untuk saham ini
        $latestRun = StockLearningRun::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy(['id' => SORT_DESC])
            ->one();

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
            'strategies' => [
                ChromosomeFactory::STRATEGY_STOCHASTIC => 'Stochastic Oscillator',
                ChromosomeFactory::STRATEGY_RSI => 'RSI Momentum & Reversal',
                ChromosomeFactory::STRATEGY_MULTI => 'Multi-Indicator Confluence',
            ],
            'timeframes' => [
                24 => 'Daily (1 Hari)',
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
            Yii::$app->session->setFlash('success', "Proses pembelajaran {$symbol} berhasil! {$wrText}.");
        } else {
            Yii::$app->session->setFlash('warning', "Pembelajaran {$symbol}: " . ($result['message'] ?? 'Data historis terbatas.'));
        }

        return $this->redirect(['detail', 'symbol' => $symbol]);
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
