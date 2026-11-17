<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use Yii;

/**
 * DoubleBottomBacktestService
 *
 * Menguji performa historis sinyal pola Double Bottom (W Reversal)
 * untuk satu kode saham secara empiris dan anti-lookahead bias.
 *
 * Fitur & Metrik Pengujian:
 * - Walk-Forward Validation (70% In-Sample Train / 30% Out-of-Sample Test)
 * - Anti-Lookahead Bias: Deteksi pola W hanya menggunakan bar t dan sebelumnya,
 *   evaluasi forward outcome dihitung dari candle t+1 s/d t+20 (atau hingga hit TP1/TP2/SL).
 * - Forward Return Horizons: +3 candle, +5 candle, +10 candle, +20 candle.
 * - MAE (Maximum Adverse Excursion) & MFE (Maximum Favorable Excursion).
 * - Realistis: Return bersih setelah biaya transaksi (fee beli + fee jual + slippage).
 * - Evaluasi Target: Rasio pencapaian target TP1 (50% pattern height), TP2 (100% height), dan Stop Loss.
 */
final class DoubleBottomBacktestService
{
    public const TIMEFRAME_1H = 1;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_1D = 24;

    /**
     * Jalankan backtesting Double Bottom untuk 1 saham.
     *
     * @param Stock $stock
     * @param array $options {
     *     timeframe?: int,
     *     lookbackCandles?: int,
     *     tolerancePercent?: float,
     *     minSeparation?: int,
     *     maxSeparation?: int,
     *     necklineMinDepth?: float,
     *     feePct?: float,
     *     slippagePct?: float,
     *     splitRatio?: float,
     * }
     * @return array<string, mixed>
     */
    public function runStockBacktest(Stock $stock, array $options = []): array
    {
        $timeframe = (int) ($options['timeframe'] ?? DoubleBottomService::DEFAULT_TIMEFRAME);
        $lookbackCandles = (int) ($options['lookbackCandles'] ?? 300);
        $tolerance = (float) (($options['tolerancePercent'] ?? 2.5) / 100);
        $minSeparation = (int) ($options['minSeparation'] ?? DoubleBottomService::DEFAULT_MIN_SEPARATION);
        $maxSeparation = (int) ($options['maxSeparation'] ?? 35);
        $necklineMinDepth = (float) ($options['necklineMinDepth'] ?? DoubleBottomService::NECKLINE_MIN_DEPTH);

        $feePct = (float) ($options['feePct'] ?? 0.30); // 0.30% buy + sell
        $slippagePct = (float) ($options['slippagePct'] ?? 0.10); // 0.10% slippage
        $totalCostPct = $feePct + $slippagePct;
        $splitRatio = (float) ($options['splitRatio'] ?? 0.70); // 70% In-Sample, 30% Out-of-Sample

        // 1. Ambil Candle Berurutan Kronologis (ASC)
        $candles = $this->loadAscCandles((int) $stock->id, $timeframe, $lookbackCandles);
        $totalBars = count($candles);

        if ($totalBars < 40) {
            return [
                'success' => false,
                'message' => "Data historis {$stock->symbol} tidak mencukupi untuk backtest (tersedia {$totalBars} candle, butuh minimal 40 candle).",
                'stock' => $stock,
            ];
        }

        // 2. Scan sinyal historis bar demi bar (Strictly anti-lookahead)
        $allSignals = [];
        $splitIndex = (int) floor($totalBars * $splitRatio);
        $startBar = 25; // Butuh minimal 25 bar untuk membentuk pivot L1, N, L2

        $lastDetectedL2 = -1;

        for ($i = $startBar; $i < $totalBars; $i++) {
            // Ambil snapshot candle sampai bar $i (tidak melihat masa depan)
            $sliceWindow = min(75, $i + 1);
            $sliceCandles = array_slice($candles, $i - $sliceWindow + 1, $sliceWindow);

            $pattern = $this->detectBarDoubleBottom($sliceCandles, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);
            if ($pattern === null) {
                continue;
            }

            // Hindari duplikasi sinyal pola yang sama
            $l2GlobalIdx = $i - ($sliceWindow - 1 - $pattern['low2_idx_in_slice']);
            if ($l2GlobalIdx <= $lastDetectedL2 + 3) {
                continue;
            }
            $lastDetectedL2 = $l2GlobalIdx;

            // Evaluasi Forward Outcome (t+1 s/d t+20)
            $evaluated = $this->evaluateForwardOutcome($candles, $i, $pattern, $totalCostPct);
            $evaluated['split_type'] = ($i < $splitIndex) ? 'train' : 'test';
            $allSignals[] = $evaluated;

            // Jeda cooling-off 3 bar
            $i += 2;
        }

        // 3. Agregasi Metrik Performa
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
                'timeframeLabel' => DoubleBottomService::getTimeframeLabel($timeframe),
                'lookbackCandles' => $lookbackCandles,
                'tolerancePercent' => round($tolerance * 100, 1),
                'minSeparation' => $minSeparation,
                'maxSeparation' => $maxSeparation,
                'totalBarsLoaded' => $totalBars,
                'splitRatio' => $splitRatio,
                'feePct' => $feePct,
                'slippagePct' => $slippagePct,
                'totalCostPct' => $totalCostPct,
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
     * Deteksi ada/tidaknya pembentukan pola Double Bottom pada potongan sliceCandles.
     */
    private function detectBarDoubleBottom(
        array $slice,
        float $tolerance,
        int $minSep,
        int $maxSep,
        float $necklineMinDepth
    ): ?array {
        $n = count($slice);
        if ($n < 15) {
            return null;
        }

        $currentClose = (float) $slice[$n - 1]['close'];
        $currentLow = (float) $slice[$n - 1]['low'];
        $currentHigh = (float) $slice[$n - 1]['high'];

        // Cari pivot low di dalam slice
        $pivotLows = [];
        for ($i = 2; $i < $n - 1; $i++) {
            $l = (float) $slice[$i]['low'];
            if ($l <= (float) $slice[$i - 1]['low'] && $l <= (float) $slice[$i - 2]['low'] && $l <= (float) $slice[$i + 1]['low']) {
                $pivotLows[] = ['index' => $i, 'price' => $l, 'date' => $slice[$i]['date']];
            }
        }

        // Bar terakhir juga bisa menjadi Bottom 2 jika sedang rebound
        $prevL = (float) $slice[$n - 2]['low'];
        if ($currentClose >= (float) $slice[$n - 1]['open'] && $prevL <= (float) $slice[$n - 3]['low']) {
            $pivotLows[] = ['index' => $n - 2, 'price' => $prevL, 'date' => $slice[$n - 2]['date']];
        }

        if (count($pivotLows) < 2) {
            return null;
        }

        // Periksa pasangan B1 dan B2 dari pivot terbaru
        $numLows = count($pivotLows);
        for ($j = $numLows - 1; $j >= 1; $j--) {
            $b2 = $pivotLows[$j];
            for ($k = $j - 1; $k >= 0; $k--) {
                $b1 = $pivotLows[$k];
                $sep = $b2['index'] - $b1['index'];
                if ($sep < $minSep || $sep > $maxSep) {
                    continue;
                }

                // Cek kesetaraan harga (Toleransi)
                $avgLow = ($b1['price'] + $b2['price']) / 2;
                $diffPct = abs($b1['price'] - $b2['price']) / max($avgLow, 0.01);
                if ($diffPct > $tolerance) {
                    continue;
                }

                // Cari neckline (peak tertinggi di antara B1 dan B2)
                $neckPrice = 0.0;
                $neckIdx = -1;
                for ($m = $b1['index'] + 1; $m < $b2['index']; $m++) {
                    $h = (float) $slice[$m]['high'];
                    if ($h > $neckPrice) {
                        $neckPrice = $h;
                        $neckIdx = $m;
                    }
                }

                if ($neckIdx === -1 || $neckPrice <= $avgLow * (1 + $necklineMinDepth)) {
                    continue;
                }

                $patternHeight = $neckPrice - $avgLow;
                $isBreakout = ($currentClose >= $neckPrice);
                $isEarly = ($currentClose < $neckPrice && ($n - 1 - $b2['index']) <= 5);

                if (!$isBreakout && !$isEarly) {
                    continue;
                }

                // Level Entry, Stop Loss, dan Target TP1 & TP2
                $entryPrice = $currentClose;
                $stopLoss = round(min($b1['price'], $b2['price']) * 0.985, 2);
                $tp1 = round($neckPrice + ($patternHeight * 0.50), 2);
                $tp2 = round($neckPrice + ($patternHeight * 1.00), 2);

                if ($tp1 <= $entryPrice) {
                    $tp1 = round($entryPrice + ($patternHeight * 0.6), 2);
                }
                if ($tp2 <= $tp1) {
                    $tp2 = round($tp1 + $patternHeight, 2);
                }

                $risk = max(0.01, $entryPrice - $stopLoss);
                $reward = max(0.01, $tp2 - $entryPrice);
                $rr = round($reward / $risk, 2);

                return [
                    'date' => $slice[$n - 1]['date'],
                    'entry_price' => $entryPrice,
                    'stop_loss' => $stopLoss,
                    'target_tp1' => $tp1,
                    'target_tp2' => $tp2,
                    'neckline_price' => round($neckPrice, 2),
                    'low1_price' => round($b1['price'], 2),
                    'low2_price' => round($b2['price'], 2),
                    'low2_idx_in_slice' => $b2['index'],
                    'pattern_height' => round($patternHeight, 2),
                    'is_breakout' => $isBreakout,
                    'is_early' => $isEarly,
                    'risk_reward' => $rr,
                    'risk_pct' => round((($entryPrice - $stopLoss) / $entryPrice) * 100, 2),
                    'tp1_gain_pct' => round((($tp1 - $entryPrice) / $entryPrice) * 100, 2),
                    'tp2_gain_pct' => round((($tp2 - $entryPrice) / $entryPrice) * 100, 2),
                    'signal_name' => $isBreakout ? 'Breakout Neckline W' : 'Early Rebound Lembah 2',
                    'badge_class' => $isBreakout ? 'success' : 'primary',
                ];
            }
        }

        return null;
    }

    /**
     * Evaluasi forward outcome (t+1 s/d t+20).
     */
    private function evaluateForwardOutcome(array $candles, int $signalIdx, array $pattern, float $totalCostPct): array
    {
        $totalBars = count($candles);
        $entryPrice = $pattern['entry_price'];
        $stopLoss = $pattern['stop_loss'];
        $tp1 = $pattern['target_tp1'];
        $tp2 = $pattern['target_tp2'];

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

            // Risk-first: Cek Hit SL
            if ($cLow <= $stopLoss && !$hitSl && !$hitTp1) {
                $hitSl = true;
                $exitPrice = $stopLoss;
                $exitReason = 'STOP_LOSS';
                $exitBar = $j;
                break;
            }

            // Cek Hit TP1
            if ($cHigh >= $tp1 && !$hitTp1) {
                $hitTp1 = true;
                $exitPrice = $tp1;
                $exitReason = 'TAKE_PROFIT_1';
                $exitBar = $j;
            }

            // Cek Hit TP2
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

        return array_merge($pattern, [
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
     * Hitung ringkasan statistik.
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

