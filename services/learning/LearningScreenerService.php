<?php

declare(strict_types=1);

namespace app\services\learning;

use app\models\Stock;
use app\models\StockLearningRun;
use Yii;

/**
 * LearningScreenerService
 *
 * Bertanggung jawab melakukan screening harian otomatis pasca penutupan bursa (04:00 - 05:00 WIB)
 * untuk mendeteksi saham-saham mana saja yang sinyal belinya (BUY) aktif berdasarkan
 * parameter optimal dari model AI hasil pelatihan Stock Learning Engine.
 */
class LearningScreenerService
{
    public const CACHE_KEY = 'stock_learning_daily_screener_results';
    public const CACHE_DURATION = 86400; // 24 jam (diperbarui setiap subuh via cron)

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
     * Jalankan screening untuk seluruh saham yang memiliki model AI terlatih.
     *
     * @param bool $forceRefresh Jika true, abaikan cache dan evaluasi ulang lilin terkini
     * @return array{
     *     scanned_at: int,
     *     scanned_at_formatted: string,
     *     total_scanned: int,
     *     buy_count: int,
     *     wait_count: int,
     *     buy_signals: array<int, array<string, mixed>>,
     *     wait_signals: array<int, array<string, mixed>>
     * }
     */
    public function screenAll(bool $forceRefresh = false): array
    {
        if (!$forceRefresh) {
            $cached = Yii::$app->cache->get(self::CACHE_KEY);
            if (is_array($cached) && !empty($cached['scanned_at'])) {
                return $cached;
            }
        }

        // Ambil daftar seluruh stock_id unik yang memiliki model completed
        $stockIds = StockLearningRun::find()
            ->select('stock_id')
            ->where(['status' => StockLearningRun::STATUS_COMPLETED])
            ->distinct()
            ->column();

        $buyList = [];
        $waitList = [];

        foreach ($stockIds as $stockId) {
            $item = $this->evaluateStockModel((int) $stockId);
            if ($item === null) {
                continue;
            }

            if ($item['is_active']) {
                $buyList[] = $item;
            } else {
                $waitList[] = $item;
            }
        }

        // Urutkan sinyal beli: Profit Factor tertinggi, lalu OOS Win-Rate tertinggi
        usort($buyList, function ($a, $b) {
            $pfA = (float) ($a['profit_factor'] ?? 0);
            $pfB = (float) ($b['profit_factor'] ?? 0);
            if ($pfA !== $pfB) {
                return $pfB <=> $pfA;
            }
            $wrA = (float) ($a['oos_win_rate'] ?? 0);
            $wrB = (float) ($b['oos_win_rate'] ?? 0);
            return $wrB <=> $wrA;
        });

        // Urutkan sinyal tunggu: Profit Factor tertinggi
        usort($waitList, function ($a, $b) {
            $pfA = (float) ($a['profit_factor'] ?? 0);
            $pfB = (float) ($b['profit_factor'] ?? 0);
            if ($pfA !== $pfB) {
                return $pfB <=> $pfA;
            }
            return strcmp((string)$a['symbol'], (string)$b['symbol']);
        });

        $payload = [
            'scanned_at' => time(),
            'scanned_at_formatted' => date('Y-m-d H:i:s'),
            'total_scanned' => count($stockIds),
            'buy_count' => count($buyList),
            'wait_count' => count($waitList),
            'buy_signals' => $buyList,
            'wait_signals' => $waitList,
        ];

        Yii::$app->cache->set(self::CACHE_KEY, $payload, self::CACHE_DURATION);

        return $payload;
    }

    /**
     * Evaluasi model terbaik untuk satu saham terhadap data lilin harian terakhir.
     *
     * @param int $stockId
     * @return array<string, mixed>|null
     */
    public function evaluateStockModel(int $stockId): ?array
    {
        $stock = Stock::findOne($stockId);
        if ($stock === null) {
            return null;
        }

        // Pilih model terbaik untuk saham ini (prioritas PF > OOS WR > Fitness)
        $bestRun = StockLearningRun::find()
            ->where(['stock_id' => $stockId, 'status' => StockLearningRun::STATUS_COMPLETED])
            ->andWhere(['>', 'total_trades', 0])
            ->orderBy([
                'profit_factor' => SORT_DESC,
                'oos_win_rate' => SORT_DESC,
                'best_fitness' => SORT_DESC,
                'id' => SORT_DESC,
            ])
            ->one();

        if ($bestRun === null) {
            $bestRun = StockLearningRun::find()
                ->where(['stock_id' => $stockId, 'status' => StockLearningRun::STATUS_COMPLETED])
                ->orderBy(['best_fitness' => SORT_DESC, 'id' => SORT_DESC])
                ->one();
        }

        if ($bestRun === null) {
            return null;
        }

        $params = $bestRun->getDecodedParams();
        if (empty($params)) {
            return null;
        }

        $timeframe = (int) ($bestRun->timeframe ?: 24);
        $candles = $this->validator->loadAscCandles($stockId, $timeframe, 150);
        $n = count($candles);
        if ($n < 5) {
            return null;
        }

        $strategy = (string) $bestRun->strategy_type;
        $lastCandle = end($candles);
        $currentPrice = (float) $lastCandle['close'];
        $tpPct = (float) ($params['tp_target_pct'] ?? 4.0);
        $slPct = (float) ($params['sl_pct'] ?? 2.0);

        // Evaluasi apakah lilin terbaru memicu sinyal beli
        $buySignals = $this->evaluator->generateBuySignals($params, $candles, $strategy);
        $isSignalRecent = !empty($buySignals[$n - 1]) || !empty($buySignals[$n - 2]);
        $diagnostics = $this->evaluator->diagnoseCurrentSignal($params, $candles, $strategy);

        $isActive = ($isSignalRecent || !empty($diagnostics['is_active']));

        $strategyLabel = match ($strategy) {
            ChromosomeFactory::STRATEGY_RSI => 'RSI Momentum & Reversal',
            ChromosomeFactory::STRATEGY_MULTI => 'Multi-Indicator Confluence',
            default => 'Stochastic Oscillator',
        };

        return [
            'stock_id' => $stockId,
            'symbol' => $stock->symbol,
            'name' => $stock->name ?? $stock->symbol,
            'current_price' => $currentPrice,
            'is_active' => $isActive,
            'status' => $isActive ? 'BUY' : 'WAIT',
            'target_price' => round($currentPrice * (1.0 + ($tpPct / 100.0)), 2),
            'target_pct' => $tpPct,
            'stop_loss' => round($currentPrice * (1.0 - ($slPct / 100.0)), 2),
            'stop_loss_pct' => $slPct,
            'risk_reward' => round($tpPct / max(0.1, $slPct), 2),
            'last_candle_date' => $lastCandle['date'] ?? null,
            'strategy' => $strategy,
            'strategy_label' => $strategyLabel,
            'run_id' => (int) $bestRun->id,
            'timeframe' => $timeframe,
            'oos_win_rate' => $bestRun->oos_win_rate !== null ? (float) $bestRun->oos_win_rate : null,
            'in_sample_win_rate' => $bestRun->is_win_rate !== null ? (float) $bestRun->is_win_rate : null,
            'profit_factor' => $bestRun->profit_factor !== null ? (float) $bestRun->profit_factor : null,
            'diagnostics' => $diagnostics,
        ];
    }
}
