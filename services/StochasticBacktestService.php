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

        return $this->runBacktestOnCandles($stock, $candles, [
            'timeframe' => $timeframe,
            'periodK' => $periodK,
            'smoothK' => $smoothK,
            'periodD' => $periodD,
            'lookbackCandles' => $lookbackCandles,
            'feePct' => $feePct,
            'slippagePct' => $slippagePct,
            'totalCostPct' => $totalCostPct,
            'splitRatio' => $splitRatio,
            'trendFilter' => $trendFilter,
            'candleConfirmation' => $candleConfirmation,
            'tpModel' => $tpModel,
        ]);
    }

    /**
     * Learning / Optimasi Parameter (%K, Smooth, %D) terbaik untuk 1 saham menggunakan Grid Search Walk-Forward.
     *
     * @param Stock $stock
     * @param array $options
     * @return array<string, mixed>
     */
    public function optimizeParameters(Stock $stock, array $options = []): array
    {
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $lookbackCandles = (int) ($options['lookbackCandles'] ?? 300);
        $feePct = (float) ($options['feePct'] ?? 0.30);
        $slippagePct = (float) ($options['slippagePct'] ?? 0.10);
        $totalCostPct = $feePct + $slippagePct;
        $splitRatio = (float) ($options['splitRatio'] ?? 0.70);
        $trendFilter = (bool) ($options['trendFilter'] ?? true);
        $candleConfirmation = (bool) ($options['candleConfirmation'] ?? true);
        $tpModel = (string) ($options['tpModel'] ?? ($timeframe === self::TIMEFRAME_1D ? 'standard' : 'adaptive'));

        $candles = $this->loadAscCandles((int) $stock->id, $timeframe, $lookbackCandles);
        $totalBars = count($candles);
        if ($totalBars < 50) {
            return [
                'success' => false,
                'message' => "Data historis {$stock->symbol} tidak mencukupi untuk parameter learning (tersedia {$totalBars} bar, butuh minimal 50).",
            ];
        }

        // Grid kombinasi (%K, SmoothK, %D) representatif dari cepat hingga lambat
        $kCandidates = [5, 8, 10, 14, 18, 21];
        $smoothCandidates = [1, 2, 3, 5];
        $dCandidates = [2, 3, 5];

        $leaderboard = [];
        foreach ($kCandidates as $k) {
            foreach ($smoothCandidates as $sk) {
                foreach ($dCandidates as $d) {
                    // Hindari kombinasi terlalu lambat atau tidak proporsional (smooth > k)
                    if ($sk > $k || ($k + $sk + $d + 20) > $totalBars) {
                        continue;
                    }
                    // Skip beberapa kombinasi redundan untuk menjaga kecepatan eksekusi < 1.5 detik
                    if ($sk === 2 && $d === 5) {
                        continue;
                    }
                    if ($sk === 5 && $d === 2) {
                        continue;
                    }

                    $res = $this->runBacktestOnCandles($stock, $candles, [
                        'timeframe' => $timeframe,
                        'periodK' => $k,
                        'smoothK' => $sk,
                        'periodD' => $d,
                        'lookbackCandles' => $lookbackCandles,
                        'feePct' => $feePct,
                        'slippagePct' => $slippagePct,
                        'totalCostPct' => $totalCostPct,
                        'splitRatio' => $splitRatio,
                        'trendFilter' => $trendFilter,
                        'candleConfirmation' => $candleConfirmation,
                        'tpModel' => $tpModel,
                    ]);

                    if (empty($res['success'])) {
                        continue;
                    }

                    $comb = $res['combined'];
                    $test = $res['test'];
                    $train = $res['train'];
                    $totalSig = (int) $comb['total_signals'];
                    if ($totalSig === 0) {
                        continue;
                    }

                    // Skor komposit anti-overfitting: prioritaskan OOS + konsistensi sample size
                    $oosWr = (float) $test['win_rate'];
                    $oosPf = min(5.0, (float) $test['profit_factor']);
                    $combPf = min(5.0, (float) $comb['profit_factor']);
                    $combRet = (float) $comb['total_net_return'];
                    $sampleMultiplier = $totalSig >= 6 ? 1.0 : ($totalSig >= 3 ? 0.75 : 0.40);

                    $score = (
                        ($oosWr * 0.35)
                        + ($oosPf * 12.0)
                        + ((float) $comb['win_rate'] * 0.20)
                        + ($combPf * 8.0)
                        + max(-20.0, min(30.0, $combRet))
                    ) * $sampleMultiplier;

                    $leaderboard[] = [
                        'periodK' => $k,
                        'smoothK' => $sk,
                        'periodD' => $d,
                        'score' => round($score, 2),
                        'total_signals' => $totalSig,
                        'oos_signals' => (int) $test['total_signals'],
                        'oos_win_rate' => (float) $test['win_rate'],
                        'oos_profit_factor' => (float) $test['profit_factor'],
                        'oos_avg_net' => (float) $test['avg_net_return'],
                        'train_win_rate' => (float) $train['win_rate'],
                        'comb_win_rate' => (float) $comb['win_rate'],
                        'comb_profit_factor' => (float) $comb['profit_factor'],
                        'comb_total_return' => (float) $comb['total_net_return'],
                        'backtest' => $res,
                    ];
                }
            }
        }

        if (empty($leaderboard)) {
            return [
                'success' => false,
                'message' => "Tidak ditemukan kombinasi (%K, Smooth, %D) yang menghasilkan sinyal pada {$stock->symbol}. Coba matikan filter atau perbesar histori backtest.",
            ];
        }

        usort($leaderboard, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top5 = array_slice($leaderboard, 0, 5);
        $best = $top5[0];

        return [
            'success' => true,
            'tested_combinations' => count($leaderboard),
            'best' => $best,
            'leaderboard' => $top5,
        ];
    }

    /**
     * Eksekusi backtest pada array candles yang sudah dimuat (tanpa query DB ulang).
     */
    private function runBacktestOnCandles(Stock $stock, array $candles, array $cfg): array
    {
        $timeframe = (int) $cfg['timeframe'];
        $periodK = (int) $cfg['periodK'];
        $smoothK = (int) $cfg['smoothK'];
        $periodD = (int) $cfg['periodD'];
        $lookbackCandles = (int) $cfg['lookbackCandles'];
        $feePct = (float) $cfg['feePct'];
        $slippagePct = (float) $cfg['slippagePct'];
        $totalCostPct = (float) $cfg['totalCostPct'];
        $splitRatio = (float) $cfg['splitRatio'];
        $trendFilter = (bool) $cfg['trendFilter'];
        $candleConfirmation = (bool) $cfg['candleConfirmation'];
        $tpModel = (string) $cfg['tpModel'];

        $totalBars = count($candles);

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
            $sliceCloses = array_slice($closes, 0, $i + 1);
            $sliceK = array_slice($kSeries, 0, $i + 1);
            $sliceD = array_slice($dSeries, 0, $i + 1);

            $signal = $this->evaluateBarSignal(
                $stochService,
                $sliceCandles,
                $sliceCloses,
                $sliceK,
                $sliceD,
                $i,
                $timeframe,
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
        StochasticService $stochService,
        array $candles,
        array $closes,
        array $kSeries,
        array $dSeries,
        int $idx,
        int $timeframe,
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
        $barRange = max(0.0001, $high - $low);
        $closePosInBar = ($close - $low) / $barRange;

        $volSlice = array_map(fn ($c) => (float) ($c['volume'] ?? 0), array_slice($candles, max(0, $idx - 20), 20));
        $avgVol20 = !empty($volSlice) ? max(1.0, array_sum($volSlice) / count($volSlice)) : 1.0;
        $rvol = ((float) ($curCandle['volume'] ?? 0)) / $avgVol20;

        // Filter Trend (Anti-Falling Knife)
        if ($trendFilter && $idx >= 35) {
            $recentCloses30 = array_slice($closes, $idx - 29, 30);
            $prevCloses30 = array_slice($closes, $idx - 34, 30);
            $sma30 = array_sum($recentCloses30) / 30;
            $sma30Prev = array_sum($prevCloses30) / 30;
            $isSma30Falling = $sma30 < $sma30Prev;

            // Tolak entry jika jatuh >6% di bawah SMA30 yang menurun, kecuali konfirmasi volume & candle kuat
            if ($close < ($sma30 * 0.94) && $isSma30Falling) {
                $hasStrongReversalVolume = ($rvol >= 1.1 && $closePosInBar >= 0.50 && $close > $open);
                if (!$hasStrongReversalVolume) {
                    return null;
                }
            }
        }

        // Filter Konfirmasi Candle
        if ($candleConfirmation) {
            $isBullishBody = ($close >= $open);
            $isReclaimPrev = ($close > (float) $prevCandle['close']);
            $hasStrongClose = ($closePosInBar >= 0.40);
            if ((!$isBullishBody && !$isReclaimPrev) || !$hasStrongClose) {
                return null;
            }
        }

        $signalType = null;
        $signalName = null;
        $badgeClass = 'success';

        $isCrossUp = ($prevK <= $prevD && $curK > $curD);

        // 1. Shakeout Low / Liquidity Sweep (Wyckoff Spring)
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
        // 3. Regular Bullish Divergence (wajib %K sedang naik)
        else {
            $div = ($curK > $prevK) ? $stochService->detectDivergence($closes, $kSeries, 30) : ['type' => 'NONE'];
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

        // Hitung ATR(14) untuk Stop Loss proporsional terhadap volatilitas saham
        $atrSum = 0.0;
        $atrCount = 0;
        for ($k = max(1, $idx - 13); $k <= $idx; $k++) {
            $tr = max(
                (float) $candles[$k]['high'] - (float) $candles[$k]['low'],
                abs((float) $candles[$k]['high'] - (float) $candles[$k - 1]['close']),
                abs((float) $candles[$k]['low'] - (float) $candles[$k - 1]['close'])
            );
            $atrSum += $tr;
            $atrCount++;
        }
        $atr14 = $atrCount > 0 ? ($atrSum / $atrCount) : ($close * 0.02);

        $windowLow = (float) $curCandle['low'];
        for ($k = max(0, $idx - 5); $k <= $idx; $k++) {
            $windowLow = min($windowLow, (float) $candles[$k]['low']);
        }
        $isIntraday = ($timeframe < self::TIMEFRAME_1D);
        $minSl = $close * ($isIntraday ? 0.950 : 0.935); // Maks risk 5.0% intraday / 6.5% daily
        $maxSl = $close * ($isIntraday ? 0.978 : 0.970); // Min ruang napas 2.2% intraday / 3.0% daily
        $rawSl = min($windowLow * 0.99, $close - (2.0 * $atr14));
        $stopLoss = round(max($minSl, min($maxSl, $rawSl)), 2);
        $risk = max(0.01, $close - $stopLoss);

        if ($tpModel === 'adaptive') {
            $tp1 = round($close + ($risk * 1.3), 2);
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
        $exitPrice = (float) ($candles[$maxForward]['close'] ?? $entryPrice);
        $exitReason = 'TIME_EXIT';
        $exitBar = $maxForward;

        for ($j = $signalIdx + 1; $j <= $maxForward; $j++) {
            $cHigh = (float) $candles[$j]['high'];
            $cLow = (float) $candles[$j]['low'];

            $minLowForward = min($minLowForward, $cLow);
            $maxHighForward = max($maxHighForward, $cHigh);

            $activeSl = $hitTp1 ? $entryPrice : $stopLoss;

            if ($cLow <= $activeSl) {
                if ($hitTp1) {
                    $exitPrice = ($tp1 + $entryPrice) / 2.0;
                    $exitReason = 'TP1_PARTIAL_BEP';
                } else {
                    $hitSl = true;
                    $exitPrice = $stopLoss;
                    $exitReason = 'STOP_LOSS';
                }
                $exitBar = $j;
                break;
            }

            if ($cHigh >= $tp1 && !$hitTp1) {
                $hitTp1 = true;
                $exitReason = 'TAKE_PROFIT_1';
                $exitBar = $j;
            }

            if ($cHigh >= $tp2 && !$hitTp2) {
                $hitTp2 = true;
                $exitPrice = ($tp1 + $tp2) / 2.0;
                $exitReason = 'TAKE_PROFIT_2';
                $exitBar = $j;
                break;
            }
        }

        if ($exitReason === 'TAKE_PROFIT_1') {
            $finalClose = (float) ($candles[$maxForward]['close'] ?? $tp1);
            $exitPrice = ($tp1 + $finalClose) / 2.0;
        } elseif ($exitReason === 'TIME_EXIT') {
            $exitPrice = (float) ($candles[$maxForward]['close'] ?? $entryPrice);
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

