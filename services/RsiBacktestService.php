<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use Yii;

/**
 * RsiBacktestService
 *
 * Menguji performa historis sinyal trading RSI (Double Bottom, Bullish Divergence, Oversold Reversal)
 * untuk satu kode saham secara empiris dan anti-lookahead bias.
 *
 * Metrik yang diuji:
 * - Total Sinyal Muncul & Win Rate (%)
 * - Forward Return: +3 candle, +5 candle, +10 candle, +20 candle
 * - Maximum Favorable Excursion (MFE / Puncak Gain Potensial)
 * - Maximum Adverse Excursion (MAE / Risiko Drawdown Terdalam)
 * - Realistis: Return bersih setelah biaya transaksi (fee beli + fee jual + slippage)
 * - Evaluasi Target: Rasio tercapainya TP1, TP2, dan Stop Loss
 * - Walk-Forward Split: Memisahkan data Train (In-Sample) dan Test (Out-of-Sample)
 */
final class RsiBacktestService
{
    public const TIMEFRAME_1H = 1;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_1D = 24;

    /**
     * Jalankan backtesting strategi RSI untuk 1 saham.
     *
     * @param Stock $stock
     * @param array $options {
     *     timeframe?: int,
     *     rsiPeriod?: int,
     *     lookbackCandles?: int,
     *     feePct?: float,
     *     slippagePct?: float,
     *     splitRatio?: float,
     *     signalTypes?: array,
     * }
     * @return array<string, mixed>
     */
    public function runStockBacktest(Stock $stock, array $options = []): array
    {
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $rsiPeriod = (int) ($options['rsiPeriod'] ?? 14);
        $lookbackCandles = (int) ($options['lookbackCandles'] ?? 300);
        $feePct = (float) ($options['feePct'] ?? 0.30); // 0.30% buy + sell
        $slippagePct = (float) ($options['slippagePct'] ?? 0.10); // 0.10% slippage
        $totalCostPct = $feePct + $slippagePct;
        $splitRatio = (float) ($options['splitRatio'] ?? 0.70); // 70% In-Sample, 30% Out-of-Sample
        $trendFilter = (bool) ($options['trendFilter'] ?? false);
        $candleConfirmation = (bool) ($options['candleConfirmation'] ?? false);
        $tpModel = (string) ($options['tpModel'] ?? ($timeframe === self::TIMEFRAME_1D ? 'standard' : 'adaptive'));

        // 1. Ambil Candle Berurutan Kronologis (ASC)
        $candles = $this->loadAscCandles((int) $stock->id, $timeframe, $lookbackCandles);
        $totalBars = count($candles);

        if ($totalBars < $rsiPeriod + 25) {
            return [
                'success' => false,
                'message' => "Data historis {$stock->symbol} tidak mencukupi untuk backtest (tersedia {$totalBars} candle, butuh minimal " . ($rsiPeriod + 25) . ').',
                'stock' => $stock,
            ];
        }

        // 2. Hitung RSI secara penuh di sepanjang bar
        $closes = array_map(fn ($c) => (float) $c['close'], $candles);
        $rsiSeries = RsiService::calculate($closes, $rsiPeriod);

        // 3. Scan sinyal historis bar demi bar (Strictly anti-lookahead)
        $allSignals = [];
        $splitIndex = (int) floor($totalBars * $splitRatio);

        // Minimal butuh rsiPeriod + 10 bar untuk evaluasi awal
        $startBar = max($rsiPeriod + 10, 20);

        for ($i = $startBar; $i < $totalBars; $i++) {
            $sliceCandles = array_slice($candles, 0, $i + 1);
            $sliceRsi = array_slice($rsiSeries, 0, $i + 1);

            $signal = $this->evaluateBarSignal($sliceCandles, $sliceRsi, $i, $rsiPeriod, $trendFilter, $candleConfirmation, $tpModel);
            if ($signal === null) {
                continue;
            }

            // Evaluasi Forward Outcome (t+1 s/d t+20) dengan trailing BEP saat TP1 tercapai
            $evaluated = $this->evaluateForwardOutcome($candles, $i, $signal, $totalCostPct, $tpModel);
            $evaluated['split_type'] = ($i < $splitIndex) ? 'train' : 'test';
            $allSignals[] = $evaluated;

            // Beri jeda/cooling-off 3 bar agar tidak duplicate sinyal beruntun di candle yang sama
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
                'timeframeLabel' => RsiDoubleBottomService::getTimeframeLabel($timeframe),
                'rsiPeriod' => $rsiPeriod,
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
            'recentTrades' => array_slice(array_reverse($allSignals), 0, 20), // 20 trade terakhir
        ];
    }

    /**
     * Evaluasi ada/tidaknya sinyal Beli RSI pada bar ke-i (tanpa melihat masa depan).
     */
    private function evaluateBarSignal(
        array $candles,
        array $rsi,
        int $idx,
        int $period,
        bool $trendFilter = false,
        bool $candleConfirmation = false,
        string $tpModel = 'adaptive'
    ): ?array {
        $curCandle = $candles[$idx];
        $prevCandle = $candles[$idx - 1] ?? null;
        $prev2Candle = $candles[$idx - 2] ?? null;

        $curRsi = $rsi[$idx] ?? null;
        $prevRsi = $rsi[$idx - 1] ?? null;
        $prev2Rsi = $rsi[$idx - 2] ?? null;

        if ($curRsi === null || $prevRsi === null || $prevCandle === null) {
            return null;
        }

        $close = (float) $curCandle['close'];
        $open = (float) $curCandle['open'];
        $low = (float) $curCandle['low'];
        $high = (float) $curCandle['high'];
        $barRange = max(0.0001, $high - $low);
        $closePosInBar = ($close - $low) / $barRange;

        // Hitung RVOL terhadap rata-rata 20 bar sebelumnya (anti-lookahead)
        $volSlice = array_map(fn ($c) => (float) ($c['volume'] ?? 0), array_slice($candles, max(0, $idx - 20), 20));
        $avgVol20 = !empty($volSlice) ? max(1.0, array_sum($volSlice) / count($volSlice)) : 1.0;
        $rvol = ((float) ($curCandle['volume'] ?? 0)) / $avgVol20;

        // --- FILTER 1: Trend Protection Filter (Anti-Falling Knife) ---
        if ($trendFilter && $idx >= 35) {
            $recentCloses30 = array_map(fn ($c) => (float) $c['close'], array_slice($candles, $idx - 29, 30));
            $prevCloses30 = array_map(fn ($c) => (float) $c['close'], array_slice($candles, $idx - 34, 30));
            $sma30 = array_sum($recentCloses30) / 30;
            $sma30Prev = array_sum($prevCloses30) / 30;
            $isSma30Falling = $sma30 < $sma30Prev;

            $recentCloses20 = array_slice($recentCloses30, -20);
            $sma20 = array_sum($recentCloses20) / 20;

            // Jika tren menengah sedang jatuh tajam (>6% di bawah SMA30 yang menurun), tolak entry
            // kecuali ada konfirmasi volume & candle hijau kuat (capitulation reversal)
            if ($close < ($sma30 * 0.94) && $isSma30Falling) {
                $hasStrongReversalVolume = ($rvol >= 1.1 && $closePosInBar >= 0.50 && $close > $open);
                if (!$hasStrongReversalVolume) {
                    return null;
                }
            }
        }

        // --- FILTER 2: Bullish Reversal Confirmation ---
        if ($candleConfirmation) {
            $isBullishBody = ($close >= $open);
            $isReclaimPrev = ($close > (float) $prevCandle['close']);
            $hasStrongClose = ($closePosInBar >= 0.40);
            if ((!$isBullishBody && !$isReclaimPrev) || !$hasStrongClose) {
                return null; // Abaikan candle lemah / tekanan jual di penutupan
            }
        }

        $signalType = null;
        $signalName = null;
        $badgeClass = 'success';

        // 1. Bullish Divergence Reversal (Harga Low lebih rendah, tapi RSI membentuk Higher Low & sudah berbalik naik)
        $div = $this->checkBarBullishDivergence($candles, $rsi, $idx);
        if ($div !== null) {
            $signalType = 'BULLISH_DIVERGENCE';
            $signalName = 'Bullish Divergence (' . $div['desc'] . ')';
            $badgeClass = 'primary';
        }
        // 2. Oversold Cross Up 30 (RSI rebound menembus 30 ke atas dengan momentum >= +1.0)
        elseif ($prevRsi <= 30.5 && $curRsi > 30.5 && $curRsi <= 45.0 && ($curRsi - $prevRsi) >= 1.0) {
            $signalType = 'OVERSOLD_CROSS_UP';
            $signalName = 'RSI Oversold Cross Up (30)';
            $badgeClass = 'success';
        }
        // 3. Oversold Bounce Reversal (RSI memantul dari titik nadir < 33, naik >= +1.0, dan candle hijau)
        elseif ($prev2Rsi !== null && $prev2Rsi <= 33.0 && $curRsi > $prevRsi && ($curRsi - $prevRsi) >= 1.0 && $curRsi <= 45.0 && $close >= $open) {
            $signalType = 'OVERSOLD_BOUNCE';
            $signalName = 'RSI Oversold Bounce Reversal';
            $badgeClass = 'info text-dark';
        }
        // 4. RSI Double Bottom Pattern (W-Pattern) dengan konfirmasi pantulan naik
        else {
            $db = $this->checkBarDoubleBottom($candles, $rsi, $idx);
            if ($db !== null) {
                $signalType = 'RSI_DOUBLE_BOTTOM';
                $signalName = 'RSI Double Bottom Reversal';
                $badgeClass = 'warning text-dark';
            }
            // 5. Uptrend Pullback Bounce (Saham di atas SMA30 yang naik, RSI memantul dari zona support 38-48)
            elseif ($idx >= 35) {
                $recent30 = array_map(fn ($c) => (float) $c['close'], array_slice($candles, $idx - 29, 30));
                $sma30Now = array_sum($recent30) / 30;
                if ($close >= $sma30Now && $prevRsi >= 36.0 && $prevRsi <= 46.0 && $curRsi > $prevRsi + 1.0 && $curRsi <= 52.0 && $close >= $open) {
                    $signalType = 'UPTREND_PULLBACK';
                    $signalName = 'RSI Uptrend Pullback Bounce';
                    $badgeClass = 'primary';
                }
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

        // Tentukan Stop Loss berbasis Swing Low (6 bar) & 2.0x ATR, dibatasi 3.0% - 6.5%
        $windowLow = (float) $curCandle['low'];
        for ($k = max(0, $idx - 5); $k <= $idx; $k++) {
            $windowLow = min($windowLow, (float) $candles[$k]['low']);
        }
        $rawSl = min($windowLow * 0.99, $close - (2.0 * $atr14));
        $minSl = $close * 0.935; // Maksimal risiko 6.5%
        $maxSl = $close * 0.970; // Minimal ruang napas 3.0%
        $stopLoss = round(max($minSl, min($maxSl, $rawSl)), 2);
        $risk = max(0.01, $close - $stopLoss);

        if ($tpModel === 'adaptive') {
            // Adaptive TP untuk Swing & Intraday:
            // TP1 = 1.4R (realistis tercapai untuk amankan 50% posisi + geser BEP)
            // TP2 = 2.5R (target ekstensi lanjutan)
            $tp1 = round($close + ($risk * 1.4), 2);
            $tp2 = round($close + ($risk * 2.5), 2);
        } else {
            // Standard / Long-term Model
            $tp1 = round($close + ($risk * 1.8), 2);
            $tp2 = round($close + ($risk * 3.0), 2);
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
            'rsi_at_signal' => round($curRsi, 2),
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

        // Hitung MAE (Maximum Adverse Excursion) & MFE (Maximum Favorable Excursion) dalam 20 bar ke depan
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

            // Trailing Break-Even Rule: Jika TP1 sudah kena, SL sisa 50% posisi digeser ke Entry Price (BEP)
            $activeSl = $hitTp1 ? $entryPrice : $stopLoss;

            // Cek Hit SL
            if ($cLow <= $activeSl) {
                if ($hitTp1) {
                    // 50% sudah profit di TP1, 50% sisa keluar di BEP
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

            // Cek Hit TP1
            if ($cHigh >= $tp1 && !$hitTp1) {
                $hitTp1 = true;
                $exitReason = 'TAKE_PROFIT_1';
                $exitBar = $j;
            }

            // Cek Hit TP2
            if ($cHigh >= $tp2 && !$hitTp2) {
                $hitTp2 = true;
                $exitPrice = ($tp1 + $tp2) / 2.0;
                $exitReason = 'TAKE_PROFIT_2';
                $exitBar = $j;
                break;
            }
        }

        // Jika sampai bar ke-20 tidak hit TP2 maupun SL, gunakan harga penutupan bar ke-20 (atau blended 50% TP1 + 50% Close)
        if ($exitReason === 'TAKE_PROFIT_1') {
            $finalClose = (float) ($candles[$maxForward]['close'] ?? $tp1);
            $exitPrice = ($tp1 + $finalClose) / 2.0;
        } elseif ($exitReason === 'TIME_EXIT') {
            $exitPrice = (float) ($candles[$maxForward]['close'] ?? $entryPrice);
        }

        $tradeGrossRet = round((($exitPrice - $entryPrice) / $entryPrice) * 100, 2);
        $tradeNetRet = round($tradeGrossRet - $totalCostPct, 2);

        $maePct = round((($minLowForward - $entryPrice) / $entryPrice) * 100, 2); // Nilai negatif/drawdown
        $mfePct = round((($maxHighForward - $entryPrice) / $entryPrice) * 100, 2); // Nilai positif/peak gain

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

        // Forward Return Horizon Stats
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
                'count' => $vCount,
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
     * Cek Bullish Divergence pada bar $idx tanpa lookahead (dengan konfirmasi RSI berbalik naik).
     */
    private function checkBarBullishDivergence(array $candles, array $rsi, int $idx): ?array
    {
        $curRsi = $rsi[$idx] ?? null;
        $prevRsi = $rsi[$idx - 1] ?? null;
        if ($curRsi === null || $prevRsi === null || $curRsi > 45.0) {
            return null;
        }

        // Wajib konfirmasi RSI sudah mulai berbalik naik (mencegah entry saat masih jatuh bebas)
        if ($curRsi <= $prevRsi) {
            return null;
        }

        // Lookback 5 s/d 25 bar ke belakang untuk mencari swing trough sebelumnya
        $p2 = min(
            (float) $candles[$idx]['low'],
            (float) ($candles[$idx - 1]['low'] ?? $candles[$idx]['low'])
        );
        $r2 = (float) $curRsi;

        for ($k = $idx - 5; $k >= max(0, $idx - 25); $k--) {
            $prevR = $rsi[$k] ?? null;
            if ($prevR === null || $prevR > 42.0) {
                continue;
            }

            // Apakah bar $k adalah local trough RSI?
            $prevPrevR = $rsi[$k - 1] ?? 999;
            $nextR = $rsi[$k + 1] ?? 999;
            if ($prevR <= $prevPrevR && $prevR <= $nextR) {
                $p1 = (float) $candles[$k]['low'];
                $r1 = (float) $prevR;

                // Harga membuat Lower Low ($p2 < $p1), RSI membuat Higher Low ($r2 > $r1)
                if ($p2 < $p1 * 0.998 && $r2 >= $r1 + 1.5) {
                    return [
                        'desc' => "RSI: " . round($r1, 1) . ' -> ' . round($r2, 1),
                        'bars_ago' => $idx - $k,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Cek RSI Double Bottom pada bar $idx tanpa lookahead (dengan konfirmasi pantulan naik).
     */
    private function checkBarDoubleBottom(array $candles, array $rsi, int $idx): ?array
    {
        $curRsi = $rsi[$idx] ?? null;
        $prevRsi = $rsi[$idx - 1] ?? null;
        if ($curRsi === null || $prevRsi === null || $curRsi > 48.0 || $curRsi <= $prevRsi) {
            return null;
        }

        $r2 = (float) $curRsi;
        for ($k = $idx - 4; $k >= max(0, $idx - 20); $k--) {
            $r1 = $rsi[$k] ?? null;
            if ($r1 === null || $r1 > 42.0) {
                continue;
            }

            if (abs($r1 - $r2) <= 3.5) {
                // Ada puncak neckline di antara kedua trough
                $neck = 0.0;
                for ($m = $k + 1; $m < $idx; $m++) {
                    $neck = max($neck, (float) ($rsi[$m] ?? 0));
                }
                if ($neck >= max($r1, $r2) + 2.5) {
                    return [
                        'r1' => $r1,
                        'r2' => $r2,
                        'neck' => $neck,
                    ];
                }
            }
        }

        return null;
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

