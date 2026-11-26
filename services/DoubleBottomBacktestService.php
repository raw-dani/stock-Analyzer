<?php

declare(strict_types=1);

namespace app\services;

use app\models\Stock;
use Yii;

/**
 * DoubleBottomBacktestService (Optimized Institutional Edition)
 *
 * Menguji performa historis sinyal pola Double Bottom (W Reversal)
 * untuk satu kode saham secara empiris, robust, dan anti-lookahead bias.
 *
 * Fitur & Optimasi Utama:
 * - Opsi Entry Mode: 'breakout' (Breakout Neckline A+), 'all' (Breakout + Early), 'early' (Rejection B2).
 * - Multi-Timeframe Trend Filter & Anti-Falling-Knife.
 * - Volume Confirmation: Dry-up di Lembah 2 dan Breakout Volume Surge.
 * - Anti-Overlapping / Trade Cooldown: Mencegah duplikasi posisi beruntun pada pergerakan yang sama.
 * - Dynamic Trade Management: Mengunci 50% profit di TP1 dan menggeser SL ke Breakeven (BEP).
 * - Realistis: Exit harga aktual pada Time Exit (bar 20) dan perhitungan bersih setelah fee & slippage.
 */
final class DoubleBottomBacktestService
{
    public const TIMEFRAME_1H = 1;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_1D = 24;

    public const MODE_BREAKOUT = 'breakout';
    public const MODE_ALL      = 'all';
    public const MODE_EARLY    = 'early';

    private DoubleBottomService $dbService;

    public function __construct(?DoubleBottomService $dbService = null)
    {
        $this->dbService = $dbService ?? new DoubleBottomService();
    }

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
     *     entryMode?: string,
     *     trendFilter?: bool,
     *     volumeFilter?: bool,
     *     trailingSl?: bool,
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

        // Mode & Filter Lanjutan
        $entryMode = (string) ($options['entryMode'] ?? self::MODE_BREAKOUT);
        $trendFilter = (bool) ($options['trendFilter'] ?? true);
        $volumeFilter = (bool) ($options['volumeFilter'] ?? true);
        $trailingSl = (bool) ($options['trailingSl'] ?? true);
        $minRr = (float) ($options['minRr'] ?? 1.20);
        $maxBreakoutZone = (float) ($options['maxBreakoutZone'] ?? 3.5);

        // 1. Ambil Candle Berurutan Kronologis (ASC) melalui DoubleBottomService (termasuk session-aware intraday)
        [$candles, $approx] = $this->dbService->getAnalysisCandles((int) $stock->id, $timeframe, $lookbackCandles);
        $totalBars = count($candles);

        if ($totalBars < 40) {
            return [
                'success' => false,
                'message' => "Data historis {$stock->symbol} tidak mencukupi untuk backtest (tersedia {$totalBars} candle, butuh minimal 40 candle).",
                'stock' => $stock,
            ];
        }

        // Ambil konteks daily penuh jika timeframe intraday agar MTF tetap bebas lookahead
        $dailyCandlesAll = ($timeframe !== self::TIMEFRAME_1D)
            ? $this->dbService->getAnalysisCandles((int) $stock->id, self::TIMEFRAME_1D, max(200, (int) ceil($lookbackCandles / 2) + 60))[0]
            : [];

        // 2. Scan sinyal historis bar demi bar (Strictly anti-lookahead)
        $allSignals = [];
        $splitIndex = (int) floor($totalBars * $splitRatio);
        $startBar = 25; // Butuh minimal 25 bar untuk membentuk pivot L1, N, L2

        $currentTradeActiveUntil = -1;

        for ($i = $startBar; $i < $totalBars; $i++) {
            // Hindari eksekusi berulang jika transaksi sebelumnya masih aktif
            if ($i < $currentTradeActiveUntil) {
                continue;
            }

            // Ambil snapshot candle sampai bar $i (tidak melihat masa depan)
            $sliceWindow = min(80, $i + 1);
            $sliceCandles = array_slice($candles, $i - $sliceWindow + 1, $sliceWindow);

            $dailySlice = $sliceCandles;
            if ($timeframe !== self::TIMEFRAME_1D && !empty($dailyCandlesAll)) {
                $barDatePrefix = substr((string) ($candles[$i]['date'] ?? ''), 0, 10);
                $dailySlice = array_slice(
                    array_values(array_filter($dailyCandlesAll, fn ($dc) => ($dc['date'] ?? '') <= $barDatePrefix)),
                    -60
                );
            }

            $pattern = $this->detectBarDoubleBottom(
                $sliceCandles,
                $tolerance,
                $minSeparation,
                $maxSeparation,
                $necklineMinDepth,
                $entryMode,
                $trendFilter,
                $volumeFilter,
                $timeframe,
                $minRr,
                $maxBreakoutZone,
                $dailySlice
            );

            if ($pattern === null) {
                continue;
            }

            // Evaluasi Forward Outcome (t+1 s/d t+20) dengan trailing stop breakeven
            $evaluated = $this->evaluateForwardOutcome($candles, $i, $pattern, $totalCostPct, $trailingSl);
            $evaluated['split_type'] = ($i < $splitIndex) ? 'train' : 'test';
            $allSignals[] = $evaluated;

            // Kunci cooling-off: Jangan membuka posisi baru hingga trade selesai atau minimal 5 bar
            $holdingBars = max(4, (int) ($evaluated['holding_bars'] ?? 4));
            $currentTradeActiveUntil = $i + $holdingBars;
            $i += max(2, min(6, $holdingBars - 1));
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
                'entryMode' => $entryMode,
                'trendFilter' => $trendFilter,
                'volumeFilter' => $volumeFilter,
                'trailingSl' => $trailingSl,
                'minRr' => $minRr,
                'maxBreakoutZone' => $maxBreakoutZone,
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
     * Deteksi pembentukan pola Double Bottom pada potongan sliceCandles memakai mesin inti DoubleBottomService.
     */
    private function detectBarDoubleBottom(
        array $slice,
        float $tolerance,
        int $minSep,
        int $maxSep,
        float $necklineMinDepth,
        string $entryMode,
        bool $trendFilter,
        bool $volumeFilter,
        int $timeframe = DoubleBottomService::DEFAULT_TIMEFRAME,
        float $minRr = 1.20,
        float $maxBreakoutZone = 3.5,
        array $dailySlice = []
    ): ?array {
        $n = count($slice);
        if ($n < 20) {
            return null;
        }

        $pattern = $this->dbService->detectPatternFromCandles($slice, [
            'timeframe' => $timeframe,
            'tolerance' => $tolerance,
            'minSeparation' => $minSep,
            'maxSeparation' => $maxSep,
            'necklineMinDepth' => $necklineMinDepth,
            'requirePriorDowntrend' => $trendFilter,
            'maxBreakoutZone' => $maxBreakoutZone,
            'maxBreakoutAge' => 2,
        ], $dailySlice);

        if ($pattern === null) {
            return null;
        }

        $tradeStatus = $pattern['trade_status'] ?? '';
        $isBreakout = ($tradeStatus === 'buy_zone');
        $isRetest = ($tradeStatus === 'retest');
        $isEarly = ($tradeStatus === 'early_setup');

        // 1. Filter Entry Mode
        if ($entryMode === self::MODE_BREAKOUT && !$isBreakout) {
            return null;
        } elseif ($entryMode === self::MODE_EARLY && !$isEarly) {
            return null;
        } elseif (!$isBreakout && !$isRetest && !$isEarly) {
            return null;
        }

        $currentClose = (float) $slice[$n - 1]['close'];
        $currentOpen  = (float) $slice[$n - 1]['open'];
        $currentLow   = (float) $slice[$n - 1]['low'];
        $currentHigh  = (float) $slice[$n - 1]['high'];

        // 2. Anti-Falling-Knife untuk Early Setup
        $closes = array_column($slice, 'close');
        $sma20 = count($closes) >= 20 ? (array_sum(array_slice($closes, -20)) / 20) : $currentClose;
        if ($trendFilter && $isEarly && $currentClose < ($sma20 * 0.95)) {
            return null;
        }

        // 3. Filter Konfirmasi Volume
        $breakoutRvol = (float) ($pattern['breakout_rvol'] ?? ($pattern['rvol'] ?? 1.0));
        if ($volumeFilter) {
            if (($isBreakout || $isRetest) && $breakoutRvol < 1.15) {
                return null;
            }
            if ($isEarly) {
                if (empty($pattern['volume_dry_up'])) {
                    return null;
                }
                if ($currentClose <= $currentOpen) {
                    return null;
                }
            }
        }

        // 4. Level Entry, SL, dan Target TP1/TP2 Selaras dengan Live Scanner
        $entryPrice = $currentClose;
        $neckPrice = (float) $pattern['neckline'];
        $patternHeight = (float) $pattern['pattern_height'];

        if ($isBreakout) {
            $stopLoss = (float) $pattern['stop_loss_tight'];
        } elseif ($isRetest) {
            $stopLoss = (float) $pattern['stop_loss_retest'];
        } else {
            $stopLoss = (float) $pattern['stop_loss_swing'];
        }

        // Batasi risiko maksimal (Max SL Cap) agar terkendali
        $maxAllowedSlPct = ($timeframe >= 24) ? ($isEarly ? 0.07 : 0.05) : ($isEarly ? 0.045 : 0.035);
        if ($entryPrice > 0 && (($entryPrice - $stopLoss) / $entryPrice) > $maxAllowedSlPct) {
            $stopLoss = round($entryPrice * (1 - $maxAllowedSlPct), 2);
        }

        // Proteksi minimum risk 0.8% dari entry
        if (($entryPrice - $stopLoss) < ($entryPrice * 0.008)) {
            $stopLoss = round($entryPrice * 0.985, 2);
        }

        if ($isEarly) {
            // Setup Early masuk di B2: TP1 di Neckline, TP2 di 50% Measured Move
            $tp1 = round($neckPrice, 2);
            $tp2 = (float) $pattern['tp1_price'];
        } else {
            $tp1 = (float) $pattern['tp1_price'];
            $tp2 = (float) $pattern['tp2_price'];
        }

        if ($tp1 <= $entryPrice) {
            $tp1 = round($entryPrice + max($patternHeight * 0.50, $entryPrice * 0.02), 2);
        }
        if ($tp2 <= $tp1) {
            $tp2 = round($tp1 + max($patternHeight * 0.50, $entryPrice * 0.02), 2);
        }

        $risk = max(0.01, $entryPrice - $stopLoss);
        $fullRr = round(($tp2 - $entryPrice) / $risk, 2);

        if ($fullRr < $minRr) {
            return null;
        }

        $signalName = $isBreakout
            ? 'Breakout Neckline W'
            : ($isRetest ? 'Retest Neckline W' : 'Early Rebound Lembah 2');
        $badgeClass = $isBreakout ? 'success' : ($isRetest ? 'info text-dark' : 'primary');

        return [
            'date' => $slice[$n - 1]['date'],
            'entry_price' => $entryPrice,
            'stop_loss' => $stopLoss,
            'target_tp1' => $tp1,
            'target_tp2' => $tp2,
            'neckline_price' => round($neckPrice, 2),
            'low1_price' => round((float) $pattern['low1_price'], 2),
            'low2_price' => round((float) $pattern['low2_price'], 2),
            'low2_idx_in_slice' => (int) $pattern['low2_index'],
            'pattern_height' => round($patternHeight, 2),
            'is_breakout' => $isBreakout,
            'is_early' => $isEarly,
            'risk_reward' => $fullRr,
            'rvol' => round($breakoutRvol, 2),
            'risk_pct' => round((($entryPrice - $stopLoss) / max($entryPrice, 0.01)) * 100, 2),
            'tp1_gain_pct' => round((($tp1 - $entryPrice) / max($entryPrice, 0.01)) * 100, 2),
            'tp2_gain_pct' => round((($tp2 - $entryPrice) / max($entryPrice, 0.01)) * 100, 2),
            'signal_name' => $signalName,
            'badge_class' => $badgeClass,
        ];
    }

    /**
     * Evaluasi forward outcome (t+1 s/d t+20) dengan trailing stop breakeven.
     */
    private function evaluateForwardOutcome(
        array $candles,
        int $signalIdx,
        array $pattern,
        float $totalCostPct,
        bool $trailingSl
    ): array {
        $totalBars = count($candles);
        $entryPrice = $pattern['entry_price'];
        $stopLoss   = $pattern['stop_loss'];
        $tp1        = $pattern['target_tp1'];
        $tp2        = $pattern['target_tp2'];

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
            $cLow  = (float) $candles[$j]['low'];

            $minLowForward = min($minLowForward, $cLow);
            $maxHighForward = max($maxHighForward, $cHigh);

            // 1. Belum hit TP1: Evaluasi Stop Loss dasar
            if (!$hitTp1) {
                if ($cLow <= $stopLoss) {
                    $hitSl = true;
                    $exitPrice = $stopLoss;
                    $exitReason = 'STOP_LOSS';
                    $exitBar = $j;
                    break;
                }
                // Cek pencapaian TP1
                if ($cHigh >= $tp1) {
                    $hitTp1 = true;
                    $exitPrice = $tp1;
                    $exitReason = 'TAKE_PROFIT_1';
                    $exitBar = $j;
                    if ($trailingSl) {
                        // Kunci 50% profit. Geser SL sisa posisi ke level aman (Neckline atau Breakeven)
                        // agar tidak langsung tertutup oleh ekor candle / noise intraday
                        $necklineRef = (float) ($pattern['neckline_price'] ?? $entryPrice);
                        $stopLoss = max(round($necklineRef * 0.995, 2), round($entryPrice * 0.995, 2));
                    }
                }
            } else {
                // 2. Sudah hit TP1: Evaluasi SL trailing yang aktif saat bar ini dibuka (anti-lookahead intrabar)
                if ($cLow <= $stopLoss && $trailingSl) {
                    // 50% dieksekusi di TP1, 50% di trailing stop
                    $exitPrice = round(($tp1 + $stopLoss) / 2, 2);
                    $exitReason = 'TP1_TRAILING_BEP';
                    $exitBar = $j;
                    break;
                }
                // Cek pencapaian TP2 (Full Projection)
                if ($cHigh >= $tp2) {
                    $hitTp2 = true;
                    // Rata-rata 50% di TP1 dan 50% di TP2
                    $exitPrice = round(($tp1 + $tp2) / 2, 2);
                    $exitReason = 'TAKE_PROFIT_2';
                    $exitBar = $j;
                    break;
                }
                if ($trailingSl) {
                    // Naikkan trailing stop bertahap untuk melindungi bar berikutnya
                    $trailingLevel = round($cHigh - ($pattern['pattern_height'] * 0.40), 2);
                    if ($trailingLevel > $stopLoss) {
                        $stopLoss = $trailingLevel;
                    }
                }
            }
        }

        // Jika sampai 20 bar belum kena SL maupun TP2
        if (!$hitSl && !$hitTp2 && $exitReason !== 'TP1_TRAILING_BEP') {
            $finalClose = (float) $candles[$maxForward]['close'];
            if ($hitTp1) {
                // 50% di TP1, 50% di harga penutupan bar ke-20
                $exitPrice = round(($tp1 + $finalClose) / 2, 2);
                $exitReason = 'TAKE_PROFIT_1';
            } else {
                // Murni time exit pada harga aktual bar ke-20 (bukan harga 0%)
                $exitPrice = $finalClose;
                $exitReason = 'TIME_EXIT';
            }
            $exitBar = $maxForward;
        }

        $tradeGrossRet = round((($exitPrice - $entryPrice) / $entryPrice) * 100, 2);
        $tradeNetRet = round($tradeGrossRet - $totalCostPct, 2);

        $maePct = round((($minLowForward - $entryPrice) / $entryPrice) * 100, 2);
        $mfePct = round((($maxHighForward - $entryPrice) / $entryPrice) * 100, 2);

        return array_merge($pattern, [
            'forward_returns' => $forwardReturns,
            'net_returns'     => $netReturns,
            'is_positive'     => $isPositive,
            'mae_pct'         => $maePct,
            'mfe_pct'         => $mfePct,
            'hit_tp1'         => $hitTp1,
            'hit_tp2'         => $hitTp2,
            'hit_sl'          => $hitSl,
            'exit_price'      => $exitPrice,
            'exit_reason'     => $exitReason,
            'holding_bars'    => max(1, $exitBar - $signalIdx),
            'trade_gross_ret' => $tradeGrossRet,
            'trade_net_ret'   => $tradeNetRet,
            'is_winner'       => ($tradeNetRet > 0),
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
}
