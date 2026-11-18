<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use Yii;

/**
 * StochasticBacktestService
 *
 * Menguji performa historis sinyal trading Stochastic Oscillator
 * (Golden Cross in Oversold, Regular Bullish Divergence, Shakeout Low, Oversold Bounce)
 * untuk satu kode saham secara empiris, anti-lookahead bias, dan walk-forward testing.
 */
final class StochasticBacktestService
{
    public const TIMEFRAME_1H = 1;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_1D = 24;

    /**
     * Jalankan backtesting strategi Stochastic untuk 1 saham.
     *
     * @param Stock $stock
     * @param array $options {
     *     timeframe?: int,
     *     periodK?: int,
     *     smoothK?: int,
     *     periodD?: int,
     *     lookbackCandles?: int,
     *     feePct?: float,
     *     slippagePct?: float,
     *     splitRatio?: float,
     *     trendFilter?: bool,
     *     candleConfirmation?: bool,
     *     tpModel?: string,
     * }
     * @return array<string, mixed>
     */
    public function runStockBacktest(Stock $stock, array $options = []): array
    {
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $periodK = (int) ($options['periodK'] ?? 14);
        $smoothK = (int) ($options['smoothK'] ?? 3);
        $periodD = (int) ($options['periodD'] ?? 3);
        $lookbackCandles = (int) ($options['lookbackCandles'] ?? 300);
        $feePct = (float) ($options['feePct'] ?? 0.30);
        $slippagePct = (float) ($options['slippagePct'] ?? 0.10);
        $totalCostPct = $feePct + $slippagePct;
        $splitRatio = (float) ($options['splitRatio'] ?? 0.70);
        $trendFilter = (bool) ($options['trendFilter'] ?? false);
        $candleConfirmation = (bool) ($options['candleConfirmation'] ?? false);
        $tpModel = (string) ($options['tpModel'] ?? ($timeframe === self::TIMEFRAME_1D ? 'standard' : 'adaptive'));

        // 1. Ambil Candle Berurutan Kronologis (ASC)
        $candles = $this->loadAscCandles((int) $stock->id, $timeframe, $lookbackCandles);
        $totalBars = count($candles);

        if ($totalBars < $periodK + $smoothK + $periodD + 20) {
            return [
                'success' => false,
                'message' => "Data historis {$stock->symbol} tidak mencukupi untuk backtest Stochastic (tersedia {$totalBars} candle, butuh minimal " . ($periodK + $smoothK + $periodD + 20) . ').',
                'stock' => $stock,
            ];
        }

        // 2. Hitung Stochastic secara penuh di sepanjang bar
        $highs = array_map(fn ($c) => (float) $c['high'], $candles);
        $lows = array_map(fn ($c) => (float) $c['low'], $candles);
        $closes = array_map(fn ($c) => (float) $c['close'], $candles);

        $stochService = new StochasticService();
        $stoch = $stochService->calculateStochastic($highs, $lows, $closes, $periodK, $smoothK, $periodD);
        $kSeries = $stoch['k'];
        $dSeries = $stoch['d'];

        // 3. Scan sinyal historis bar demi bar (Strictly anti-lookahead)
        $allSignals = [];
        $splitIndex = (int) floor($totalBars * $splitRatio);
        $startBar = max($periodK + $smoothK + $periodD + 10, 25);

        for ($i = $startBar; $i < $totalBars; $i++) {
            $sliceCandles = array_slice($candles, 0, $i + 1);
            $sliceK = array_slice($kSeries, 0, $i + 1);
            $sliceD = array_slice($dSeries, 0, $i + 1);

            $signal = $this->evaluateBarSignal(
                $sliceCandles,
                $sliceK,
                $sliceD,
                $i,
                $trendFilter,
                $candleConfirmation,
                $tpModel
            );

            if ($signal === null) {
                continue;
            }

            // Evaluasi Forward Outcome (t+1 s/d t+20)
            $evaluated = $this->evaluateForwardOutcome($candles, $i, $signal, $totalCostPct, $tpModel);
            $evaluated['split_type'] = ($i < $splitIndex) ? 'train' : 'test';
            $allSignals[] = $evaluated;

            // Beri jeda/cooling-off 3 bar agar tidak duplicate sinyal beruntun
            $i += 2;
        }

        // 4. Agregasi Metrik Performa
        $trainSignals = array_values(array_filter($allSignals, fn ($s) => $s['split_type'] === 'train'));
        $testSignals = array_values(array_filter($allSignals, fn ($s) => $s['split_type'] === 'test'));

        $trainStats = $this->compileStats($trainSignals, $totalCostPct);
        $testStats = $this->compileStats($testSignals, $totalCostPct);
        $combinedStats = $this->compileStats($allSignals, $totalCostPct);

        return [
            'success' => true,
            'stock' => $stock,
            'params' => [
                'timeframe' => $timeframe,
                'timeframeLabel' => StochasticService::getTimeframes()[$timeframe] ?? "TF {$timeframe}",
                'periodK' => $periodK,
                'smoothK' => $smoothK,
                'periodD' => $periodD,
                'lookbackCandles' => $lookbackCandles,
                'totalBarsLoaded' => $totalBars,
                'splitRatio' => $splitRatio,
                'feePct' => $feePct,
                'slippagePct' => $slippagePct,
                'totalCostPct' => $totalCostPct,
                'trendFilter' => $trendFilter,
                'candleConfirmation' => $candleConfirmation,
                'tpModel' => $tpModel,
                'trainRange' => [
                    'start' => $candles[$startBar]['date'] ?? '',
                    'end' => $candles[min($splitIndex - 1, $totalBars - 1)]['date'] ?? '',
                    'bars' => $splitIndex - $startBar,
                ],
                'testRange' => [
                    'start' => $candles[min($splitIndex, $totalBars - 1)]['date'] ?? '',
                    'end' => $candles[$totalBars - 1]['date'] ?? '',
                    'bars' => $totalBars - $splitIndex,
                ],
            ],
            'train' => $trainStats,
            'test' => $testStats,
            'combined' => $combinedStats,
            'recentTrades' => array_slice(array_reverse($allSignals), 0, 20),
        ];
    }

    /**
     * Evaluasi ada/tidaknya sinyal Beli Stochastic pada bar ke-i (tanpa melihat masa depan).
     */
    private function evaluateBarSignal(
        array $candles,
        array $kSeries,
        array $dSeries,
        int $idx,
        bool $trendFilter = false,
        bool $candleConfirmation = false,
        string $tpModel = 'adaptive'
    ): ?array {
        $curCandle = $candles[$idx];
        $prevCandle = $candles[$idx - 1] ?? null;

        $curK = $kSeries[$idx] ?? null;
        $curD = $dSeries[$idx] ?? null;
        $prevK = $kSeries[$idx - 1] ?? null;
        $prevD = $dSeries[$idx - 1] ?? null;

        if ($curK === null || $curD === null || $prevK === null || $prevD === null || $prevCandle === null) {
            return null;
        }

        $close = (float) $curCandle['close'];
        $open  = (float) $curCandle['open'];
        $low   = (float) $curCandle['low'];
        $high  = (float) $curCandle['high'];

        // Filter Trend
        if ($trendFilter && $idx >= 30) {
            $recentCloses = array_map(fn ($c) => (float) $c['close'], array_slice($candles, $idx - 29, 30));
            $sma30 = array_sum($recentCloses) / 30;
            if ($close < ($sma30 * 0.92) && $close < (float) $prevCandle['close']) {
                return null;
            }
        }

        // Filter Konfirmasi Candle
        if ($candleConfirmation) {
            $isBullishBody = ($close >= $open);
            $isReclaimPrev = ($close > (float) $prevCandle['close']);
            if (!$isBullishBody && !$isReclaimPrev) {
                return null;
            }
        }

        $signalType = null;
        $signalName = null;
        $badgeClass = 'success';

        $isCrossUp = ($prevK <= $prevD && $curK > $curD);

        // 1. Shakeout Low / Liquidity Sweep (Wyckoff Spring)
        $stochService = new StochasticService();
        $shakeout = $stochService->detectShakeoutLow($candles, $kSeries, $dSeries, 30);
        if (!empty($shakeout['detected'])) {
            $signalType = 'SHAKEOUT_LOW';
            $signalName = 'Shakeout Low (-' . ($shakeout['sweep_pct'] ?? 0) . '%)';
            $badgeClass = 'warning text-dark';
        }
        // 2. Bullish Golden Cross in Oversold (< 25)
        elseif ($isCrossUp && ($curK <= 25.0 || $prevK <= 20.0)) {
            $signalType = 'GOLDEN_CROSS_OVERSOLD';
            $signalName = 'Stochastic GC in Oversold (%K ' . round($curK, 1) . ')';
            $badgeClass = 'success';
        }
        // 3. Regular Bullish Divergence
        else {
            $closes = array_map(fn ($c) => (float) $c['close'], $candles);
            $div = $stochService->detectDivergence($closes, $kSeries, 30);
            if ($div['type'] === 'BULLISH_REGULAR') {
                $signalType = 'BULLISH_DIVERGENCE';
                $signalName = 'Bullish Divergence (%K ' . round($curK, 1) . ')';
                $badgeClass = 'primary';
            }
            // 4. Oversold Recovery (Cross > 20)
            elseif ($prevK <= 20.0 && $curK > 20.0 && $curK > $curD) {
                $signalType = 'OVERSOLD_BOUNCE';
                $signalName = 'Stochastic Oversold Recovery (> 20)';
                $badgeClass = 'info text-dark';
            }
        }

        if ($signalType === null) {
            return null;
        }

        // Tentukan Target TP1, TP2 dan SL
        $windowLow = (float) $curCandle['low'];
        for ($k = max(0, $idx - 10); $k <= $idx; $k++) {
            $windowLow = min($windowLow, (float) $candles[$k]['low']);
        }
        $stopLoss = round(min($windowLow * 0.988, $close * 0.975), 2);
        $risk = max(0.01, $close - $stopLoss);

        if ($tpModel === 'adaptive') {
            $tp1 = round($close + ($risk * 1.2), 2);
            $tp2 = round($close + ($risk * 2.5), 2);
        } else {
            $tp1 = round($close + ($risk * 1.6), 2);
            $tp2 = round($close + ($risk * 2.8), 2);
        }

        return [
            'index' => $idx,
            'date' => $curCandle['date'],
            'entry_price' => $close,
            'stop_loss' => $stopLoss,
            'target_tp1' => $tp1,
            'target_tp2' => $tp2,
            'risk_pct' => round((($close - $stopLoss) / $close) * 100, 2),
            'tp1_gain_pct' => round((($tp1 - $close) / $close) * 100, 2),
            'tp2_gain_pct' => round((($tp2 - $close) / $close) * 100, 2),
            'k_at_signal' => round($curK, 2),
            'd_at_signal' => round($curD, 2),
            'signal_type' => $signalType,
            'signal_name' => $signalName,
            'badge_class' => $badgeClass,
        ];
    }

    /**
     * Evaluasi performa masa depan (forward outcome) t+1 s/d t+20.
     */
    private function evaluateForwardOutcome(
        array $candles,
        int $signalIdx,
        array $signal,
        float $totalCostPct,
        string $tpModel = 'adaptive'
    ): array {
        $totalBars = count($candles);
        $entryPrice = $signal['entry_price'];
        $stopLoss = $signal['stop_loss'];
        $tp1 = $signal['target_tp1'];
        $tp2 = $signal['target_tp2'];

        $horizons = [3, 5, 10, 20];
        $forwardReturns = [];
        $netReturns = [];
        $isPositive = [];

        foreach ($horizons as $h) {
            $targetIdx = $signalIdx + $h;
            if ($targetIdx < $totalBars) {
                $fClose = (float) $candles[$targetIdx]['close'];
                $ret = round((($fClose - $entryPrice) / $entryPrice) * 100, 2);
                $netRet = round($ret - $totalCostPct, 2);
                $forwardReturns["ret_{$h}d"] = $ret;
                $netReturns["net_{$h}d"] = $netRet;
                $isPositive["win_{$h}d"] = ($netRet > 0);
            } else {
                $forwardReturns["ret_{$h}d"] = null;
                $netReturns["net_{$h}d"] = null;
                $isPositive["win_{$h}d"] = null;
            }
        }

        // MAE & MFE
        $maxForward = min($totalBars - 1, $signalIdx + 20);
        $minLowForward = $entryPrice;
        $maxHighForward = $entryPrice;
        $hitTp1 = false;
        $hitTp2 = false;
        $hitSl = false;
        $exitPrice = $entryPrice;
        $exitReason = 'TIME_EXIT';
        $exitBar = $maxForward;

        for ($j = $signalIdx + 1; $j <= $maxForward; $j++) {
            $cHigh = (float) $candles[$j]['high'];
            $cLow = (float) $candles[$j]['low'];

            $minLowForward = min($minLowForward, $cLow);
            $maxHighForward = max($maxHighForward, $cHigh);

            $activeSl = ($hitTp1 && $tpModel === 'adaptive') ? $entryPrice : $stopLoss;

            if ($cLow <= $activeSl && !$hitSl) {
                $hitSl = true;
                $exitPrice = $activeSl;
                $exitReason = ($activeSl === $entryPrice) ? 'BREAK_EVEN' : 'STOP_LOSS';
                $exitBar = $j;
                break;
            }

            if ($cHigh >= $tp1 && !$hitTp1) {
                $hitTp1 = true;
                $exitPrice = $tp1;
                $exitReason = 'TAKE_PROFIT_1';
                $exitBar = $j;
            }

            if ($cHigh >= $tp2 && !$hitTp2) {
                $hitTp2 = true;
                $exitPrice = $tp2;
                $exitReason = 'TAKE_PROFIT_2';
                $exitBar = $j;
                break;
            }
        }

        $tradeGrossRet = round((($exitPrice - $entryPrice) / $entryPrice) * 100, 2);
        $tradeNetRet = round($tradeGrossRet - $totalCostPct, 2);

        $maePct = round((($minLowForward - $entryPrice) / $entryPrice) * 100, 2);
        $mfePct = round((($maxHighForward - $entryPrice) / $entryPrice) * 100, 2);

        return array_merge($signal, [
            'forward_returns' => $forwardReturns,
            'net_returns' => $netReturns,
            'is_positive' => $isPositive,
            'mae_pct' => $maePct,
            'mfe_pct' => $mfePct,
            'hit_tp1' => $hitTp1,
            'hit_tp2' => $hitTp2,
            'hit_sl' => $hitSl,
            'exit_price' => $exitPrice,
            'exit_reason' => $exitReason,
            'holding_bars' => max(1, $exitBar - $signalIdx),
            'trade_gross_ret' => $tradeGrossRet,
            'trade_net_ret' => $tradeNetRet,
            'is_winner' => ($tradeNetRet > 0),
        ]);
    }

    /**
     * Hitung ringkasan statistik dari kumpulan trade sinyal.
     */
    private function compileStats(array $signals, float $costPct): array
    {
        $count = count($signals);
        if ($count === 0) {
            return [
                'total_signals' => 0,
                'win_rate' => 0.0,
                'tp1_hit_rate' => 0.0,
                'tp2_hit_rate' => 0.0,
                'sl_hit_rate' => 0.0,
                'avg_net_return' => 0.0,
                'total_net_return' => 0.0,
                'profit_factor' => 0.0,
                'avg_mfe' => 0.0,
                'avg_mae' => 0.0,
                'ret_3d' => ['win_rate' => 0.0, 'avg_net' => 0.0],
                'ret_5d' => ['win_rate' => 0.0, 'avg_net' => 0.0],
                'ret_10d' => ['win_rate' => 0.0, 'avg_net' => 0.0],
                'ret_20d' => ['win_rate' => 0.0, 'avg_net' => 0.0],
            ];
        }

        $wins = count(array_filter($signals, fn ($s) => !empty($s['is_winner'])));
        $tp1Hits = count(array_filter($signals, fn ($s) => !empty($s['hit_tp1'])));
        $tp2Hits = count(array_filter($signals, fn ($s) => !empty($s['hit_tp2'])));
        $slHits = count(array_filter($signals, fn ($s) => !empty($s['hit_sl'])));

        $netReturns = array_column($signals, 'trade_net_ret');
        $sumNet = array_sum($netReturns);
        $avgNet = round($sumNet / $count, 2);

        $grossGains = array_filter($netReturns, fn ($v) => $v > 0);
        $grossLosses = array_map(fn ($v) => abs($v), array_filter($netReturns, fn ($v) => $v < 0));
        $sumGains = array_sum($grossGains);
        $sumLosses = array_sum($grossLosses);
        $profitFactor = $sumLosses > 0 ? round($sumGains / $sumLosses, 2) : ($sumGains > 0 ? 99.9 : 1.0);

        $avgMfe = round(array_sum(array_column($signals, 'mfe_pct')) / $count, 2);
        $avgMae = round(array_sum(array_column($signals, 'mae_pct')) / $count, 2);

        $horizonStats = [];
        foreach ([3, 5, 10, 20] as $h) {
            $validRets = [];
            $hWins = 0;
            foreach ($signals as $s) {
                $net = $s['net_returns']["net_{$h}d"] ?? null;
                if ($net !== null) {
                    $validRets[] = (float) $net;
                    if ($net > 0) {
                        $hWins++;
                    }
                }
            }
            $vCount = count($validRets);
            $horizonStats["ret_{$h}d"] = [
                'win_rate' => $vCount > 0 ? round(($hWins / $vCount) * 100, 1) : 0.0,
                'avg_net' => $vCount > 0 ? round(array_sum($validRets) / $vCount, 2) : 0.0,
            ];
        }

        return [
            'total_signals' => $count,
            'win_rate' => round(($wins / $count) * 100, 1),
            'tp1_hit_rate' => round(($tp1Hits / $count) * 100, 1),
            'tp2_hit_rate' => round(($tp2Hits / $count) * 100, 1),
            'sl_hit_rate' => round(($slHits / $count) * 100, 1),
            'avg_net_return' => $avgNet,
            'total_net_return' => round($sumNet, 2),
            'profit_factor' => $profitFactor,
            'avg_mfe' => $avgMfe,
            'avg_mae' => $avgMae,
            'ret_3d' => $horizonStats['ret_3d'],
            'ret_5d' => $horizonStats['ret_5d'],
            'ret_10d' => $horizonStats['ret_10d'],
            'ret_20d' => $horizonStats['ret_20d'],
        ];
    }

    /**
     * Memuat candle terurut ASC (Kronologis).
     */
    private function loadAscCandles(int $stockId, int $tf, int $limit): array
    {
        if ($tf === self::TIMEFRAME_1D) {
            $prices = DailyPrice::find()
                ->where(['stock_id' => $stockId])
                ->orderBy(['date' => SORT_DESC])
                ->limit($limit)
                ->asArray()
                ->all();
            return array_reverse($prices);
        }

        try {
            $rows = IntradayPrice::find()
                ->where(['stock_id' => $stockId, 'timeframe' => '1h'])
                ->orderBy(['datetime' => SORT_DESC])
                ->limit($limit * 3)
                ->asArray()
                ->all();

            if (!empty($rows)) {
                $rows = array_reverse($rows);
                if ($tf === self::TIMEFRAME_2H) {
                    return $this->aggregate2H($rows);
                } elseif ($tf === self::TIMEFRAME_4H) {
                    return $this->aggregate4H($rows);
                }
                $out = [];
                foreach ($rows as $r) {
                    $out[] = [
                        'open' => (float) $r['open'],
                        'high' => (float) $r['high'],
                        'low' => (float) $r['low'],
                        'close' => (float) $r['close'],
                        'volume' => (int) $r['volume'],
                        'date' => $r['datetime'],
                    ];
                }
                return $out;
            }
        } catch (\Throwable $e) {
            // fallback daily
        }

        $daily = DailyPrice::find()
            ->where(['stock_id' => $stockId])
            ->orderBy(['date' => SORT_DESC])
            ->limit($limit)
            ->asArray()
            ->all();
        return array_reverse($daily);
    }

    private function aggregate2H(array $bars): array
    {
        $out = [];
        $chunks = array_chunk($bars, 2);
        foreach ($chunks as $c) {
            $out[] = [
                'open' => (float) $c[0]['open'],
                'high' => max(array_column($c, 'high')),
                'low' => min(array_column($c, 'low')),
                'close' => (float) end($c)['close'],
                'volume' => array_sum(array_column($c, 'volume')),
                'date' => end($c)['datetime'],
            ];
        }
        return $out;
    }

    private function aggregate4H(array $bars): array
    {
        $out = [];
        $chunks = array_chunk($bars, 4);
        foreach ($chunks as $c) {
            $out[] = [
                'open' => (float) $c[0]['open'],
                'high' => max(array_column($c, 'high')),
                'low' => min(array_column($c, 'low')),
                'close' => (float) end($c)['close'],
                'volume' => array_sum(array_column($c, 'volume')),
                'date' => end($c)['datetime'],
            ];
        }
        return $out;
    }
}

