<?php

declare(strict_types=1);

namespace app\services\learning;

use app\services\RsiService;

/**
 * FitnessEvaluatorService
 *
 * Mesin simulasi trading cepat (High-Speed Backtest Simulator) yang mengevaluasi performa
 * satu kromosom parameter strategi pada dataset candle.
 *
 * Menghitung Fitness Score multi-objektif:
 * - Win Rate (%)
 * - Profit Factor (Gross Gain / Gross Loss)
 * - Maximum Drawdown (%)
 * - Trade Frequency (Penalti jika trade terlalu sedikit atau nol)
 */
final class FitnessEvaluatorService
{
    private const TRANSACTION_COST_PCT = 0.40; // 0.30% fee beli/jual + 0.10% slippage

    /**
     * Evaluasi satu kromosom pada dataset lilin dengan rentang indeks tertentu (Continuous Warmup Masking).
     *
     * @param array<string, mixed> $chromosome
     * @param array<int, array<string, mixed>> $candles Seluruh lilin urut ASC
     * @param string $strategy
     * @param int $startBarIndex Indeks bar awal untuk mengizinkan entry trade
     * @param int $endBarIndex Indeks bar akhir untuk mengizinkan entry trade
     * @param string $segmentLabel Label segmen ('ALL', 'IN_SAMPLE', 'OUT_OF_SAMPLE')
     * @return array{
     *     fitness: float,
     *     win_rate: float,
     *     total_trades: int,
     *     wins: int,
     *     losses: int,
     *     profit_factor: float,
     *     max_drawdown: float,
     *     sharpe_ratio: float,
     *     net_return_pct: float,
     *     trades: array<int, array<string, mixed>>
     * }
     */
    public function evaluate(
        array $chromosome,
        array $candles,
        string $strategy,
        int $startBarIndex = 0,
        int $endBarIndex = PHP_INT_MAX,
        string $segmentLabel = 'ALL'
    ): array {
        $n = count($candles);
        if ($n < 20) {
            return $this->emptyResult();
        }

        // 1. Ekstrak sinyal beli pada seluruh lilin kontinu (warmup penuh)
        $buySignals = $this->generateBuySignals($chromosome, $candles, $strategy);

        // 2. Simulasikan perdagangan dengan pembatasan jendela entry
        $trades = $this->simulateTrades($buySignals, $candles, $chromosome, $startBarIndex, $endBarIndex, $segmentLabel);

        if (empty($trades)) {
            return $this->emptyResult();
        }

        // 3. Kalkulasi metrik statistik performa
        return $this->calculateMetrics($trades);
    }

    /**
     * Menghasilkan array boolean sinyal beli per bar lilin.
     *
     * @param array<string, mixed> $params
     * @param array<int, array<string, mixed>> $candles
     * @param string $strategy
     * @return array<int, bool> index lilin => true jika sinyal BUY terpicu
     */
    public function generateBuySignals(array $params, array $candles, string $strategy): array
    {
        $n = count($candles);
        $signals = array_fill(0, $n, false);
        if ($n < 5) {
            return $signals;
        }
        $closes = array_column($candles, 'close');

        // Prekalkulasi Trend Filter (MA 50 atau adaptif jika candle < 70)
        $trendFilterActive = !empty($params['trend_filter']);
        $trendPeriod = min(50, max(10, (int) floor($n * 0.35)));
        $maTrend = $trendFilterActive ? $this->calculateSma($closes, $trendPeriod) : [];

        if ($strategy === ChromosomeFactory::STRATEGY_RSI) {
            $period = min((int) ($params['rsi_period'] ?? 14), max(5, (int) floor($n * 0.25)));
            $oversold = (float) ($params['oversold'] ?? 30.0);
            $rsi = RsiService::calculate($closes, $period);

            for ($i = $period + 2; $i < $n; $i++) {
                if ($rsi[$i] === null || $rsi[$i - 1] === null) {
                    continue;
                }

                // Sinyal 1 - Reversal: Memantul dari area oversold
                $reversalTrigger = ($rsi[$i - 1] <= $oversold && $rsi[$i] > $rsi[$i - 1]);

                // Sinyal 2 - Momentum Pullback: Memantul di zona support saat tren naik
                $momentumTrigger = ($rsi[$i - 1] <= ($oversold + 12.0) && $rsi[$i] > $rsi[$i - 1] && isset($maTrend[$i]) && $closes[$i] >= $maTrend[$i]);

                // Sinyal 3 - Centerline Breakout: RSI menembus ke atas level 50 saat tren naik
                $centerlineTrigger = ($rsi[$i - 1] <= 50.0 && $rsi[$i] > 50.0 && $rsi[$i] <= 60.0 && isset($maTrend[$i]) && $closes[$i] >= $maTrend[$i]);

                $rsiTrigger = $reversalTrigger || $momentumTrigger || $centerlineTrigger;

                // Trend filter: Harga saat ini di atas MA Trend
                $trendOk = !$trendFilterActive || (isset($maTrend[$i]) && $closes[$i] >= $maTrend[$i]);

                // Candle confirmation: Lilin penutupan hijau
                $candleOk = empty($params['candle_confirm']) || ($candles[$i]['close'] >= $candles[$i]['open']);

                if ($rsiTrigger && $trendOk && $candleOk) {
                    $signals[$i] = true;
                }
            }
        } elseif ($strategy === ChromosomeFactory::STRATEGY_MULTI) {
            // Multi-indikator Confluence: Stochastic + RSI + Moving Average
            $periodK = min((int) ($params['stoch_k'] ?? 14), max(5, (int) floor($n * 0.20)));
            $stochOs = (float) ($params['stoch_os'] ?? 20.0);
            [$kSeries, $dSeries] = $this->calculateStochastic($candles, $periodK, 3, 3);

            $rsiPeriod = min((int) ($params['rsi_period'] ?? 14), max(5, (int) floor($n * 0.20)));
            $rsiOs = (float) ($params['rsi_os'] ?? 30.0);
            $rsi = RsiService::calculate($closes, $rsiPeriod);

            $maFastPeriod = min((int) ($params['ma_fast'] ?? 10), max(5, (int) floor($n * 0.15)));
            $maSlowPeriod = min((int) ($params['ma_slow'] ?? 50), max($maFastPeriod + 5, (int) floor($n * 0.35)));

            $maFast = $this->calculateSma($closes, $maFastPeriod);
            $maSlow = $this->calculateSma($closes, $maSlowPeriod);
            $minSignals = (int) ($params['min_signals'] ?? 2);

            $start = max($periodK + 4, $rsiPeriod + 2, $maSlowPeriod);

            for ($i = $start; $i < $n; $i++) {
                $votes = 0;

                // Vote 1: Stoch oversold atau golden cross
                if ($kSeries[$i] !== null && $dSeries[$i] !== null) {
                    if ($kSeries[$i] <= $stochOs || ($kSeries[$i] > $dSeries[$i] && $kSeries[$i - 1] <= $dSeries[$i - 1])) {
                        $votes++;
                    }
                }

                // Vote 2: RSI oversold atau memantul
                if ($rsi[$i] !== null && ($rsi[$i] <= $rsiOs || ($rsi[$i] > $rsi[$i - 1] && $rsi[$i] < 55))) {
                    $votes++;
                }

                // Vote 3: MA Bullish (Fast > Slow atau harga memotong MA Fast)
                if (isset($maFast[$i], $maSlow[$i]) && $maFast[$i] > $maSlow[$i]) {
                    $votes++;
                }

                $trendOk = !$trendFilterActive || (isset($maTrend[$i]) && $closes[$i] >= $maTrend[$i]);

                if ($votes >= $minSignals && $trendOk) {
                    $signals[$i] = true;
                }
            }
        } else {
            // Default: Stochastic Oscillator
            $periodK = min((int) ($params['periodK'] ?? 14), max(5, (int) floor($n * 0.25)));
            $smoothK = (int) ($params['smoothK'] ?? 3);
            $periodD = (int) ($params['periodD'] ?? 3);
            $oversold = (float) ($params['oversold'] ?? 20.0);

            [$kSeries, $dSeries] = $this->calculateStochastic($candles, $periodK, $smoothK, $periodD);

            $start = $periodK + $smoothK + $periodD + 1;
            for ($i = $start; $i < $n; $i++) {
                if ($kSeries[$i] === null || $dSeries[$i] === null || $kSeries[$i - 1] === null || $dSeries[$i - 1] === null) {
                    continue;
                }

                // Sinyal Beli 1: Golden Cross di dekat/dalam oversold
                $goldenCross = ($kSeries[$i - 1] <= $dSeries[$i - 1] && $kSeries[$i] > $dSeries[$i] && $kSeries[$i] <= ($oversold + 15.0));

                // Sinyal Beli 2: Memantul dari dasar oversold
                $bounceOversold = ($kSeries[$i - 1] <= $oversold && $kSeries[$i] > $oversold);

                $stochTrigger = $goldenCross || $bounceOversold;
                $trendOk = !$trendFilterActive || (isset($maTrend[$i]) && $closes[$i] >= $maTrend[$i]);
                $candleOk = empty($params['candle_confirm']) || ($candles[$i]['close'] >= $candles[$i]['open']);

                if ($stochTrigger && $trendOk && $candleOk) {
                    $signals[$i] = true;
                }
            }
        }

        return $signals;
    }

    /**
     * Jalankan simulasi trading kronologis (Strictly Anti-Lookahead: Entry di Open bar i+1).
     *
     * @param array<int, bool> $signals
     * @param array<int, array<string, mixed>> $candles
     * @param array<string, mixed> $params
     * @param int $startBarIndex
     * @param int $endBarIndex
     * @param string $segmentLabel
     * @return array<int, array<string, mixed>>
     */
    private function simulateTrades(
        array $signals,
        array $candles,
        array $params,
        int $startBarIndex = 0,
        int $endBarIndex = PHP_INT_MAX,
        string $segmentLabel = 'ALL'
    ): array {
        $n = count($candles);
        $trades = [];
        $tpTargetPct = (float) ($params['tp_target_pct'] ?? 4.0);
        $slPct = (float) ($params['sl_pct'] ?? 2.0);
        $trailBep = !empty($params['trail_bep']);
        $maxHoldBars = 20; // Batas waktu hold maksimal

        $inPosition = false;
        $entryPrice = 0.0;
        $entryBar = 0;
        $currentSl = 0.0;
        $targetTpPrice = 0.0;

        for ($i = 0; $i < $n; $i++) {
            if ($inPosition) {
                $high = (float) $candles[$i]['high'];
                $low = (float) $candles[$i]['low'];
                $close = (float) $candles[$i]['close'];
                $barsHeld = $i - $entryBar;

                // Cek Trailing BEP: jika harga sudah capai 50% TP, geser SL ke Break-Even
                if ($trailBep && $high >= $entryPrice * (1.0 + ($tpTargetPct * 0.5 / 100.0))) {
                    $currentSl = max($currentSl, $entryPrice * 1.002);
                }

                $isExit = false;
                $exitPrice = $close;
                $exitReason = 'TIME_EXIT';

                // Cek Target Profit Hit
                if ($high >= $targetTpPrice) {
                    $exitPrice = $targetTpPrice;
                    $exitReason = 'TP_HIT';
                    $isExit = true;
                } elseif ($low <= $currentSl) {
                    // Cek Stop Loss Hit
                    $exitPrice = $currentSl;
                    $exitReason = 'SL_HIT';
                    $isExit = true;
                } elseif ($barsHeld >= $maxHoldBars || $i === $n - 1) {
                    // Max Hold Timeout Exit
                    $exitPrice = $close;
                    $exitReason = 'TIME_EXIT';
                    $isExit = true;
                }

                if ($isExit) {
                    // Kalkulasi PnL bersih setelah fee & slippage
                    $grossPnlPct = (($exitPrice - $entryPrice) / $entryPrice) * 100.0;
                    $netPnlPct = $grossPnlPct - self::TRANSACTION_COST_PCT;

                    $trades[] = [
                        'entry_bar' => $entryBar,
                        'entry_date' => $candles[$entryBar]['date'] ?? '',
                        'entry_price' => round($entryPrice, 2),
                        'exit_bar' => $i,
                        'exit_date' => $candles[$i]['date'] ?? '',
                        'exit_price' => round($exitPrice, 2),
                        'pnl_pct' => round($netPnlPct, 2),
                        'is_win' => $netPnlPct > 0.0,
                        'hold_bars' => $barsHeld,
                        'exit_reason' => $exitReason,
                        'segment' => $segmentLabel,
                    ];

                    $inPosition = false;
                }
            }

            // Entry Condition: Terbuka posisi baru di Open bar i jika sinyal bar i-1 aktif
            // Batasi entry HANYA di dalam jendela segmen [startBarIndex, endBarIndex)
            if (!$inPosition && $i > 0 && !empty($signals[$i - 1])) {
                if ($i >= $startBarIndex && $i < $endBarIndex) {
                    $entryPrice = (float) $candles[$i]['open'];
                    if ($entryPrice > 0.0) {
                        $inPosition = true;
                        $entryBar = $i;
                        $targetTpPrice = $entryPrice * (1.0 + ($tpTargetPct / 100.0));
                        $currentSl = $entryPrice * (1.0 - ($slPct / 100.0));
                    }
                }
            }
        }

        return $trades;
    }

    /**
     * Hitung metrik performa dan komposit fitness score.
     *
     * @param array<int, array{pnl_pct: float, is_win: bool, hold_bars: int}> $trades
     * @return array{
     *     fitness: float,
     *     win_rate: float,
     *     total_trades: int,
     *     wins: int,
     *     losses: int,
     *     profit_factor: float,
     *     max_drawdown: float,
     *     sharpe_ratio: float,
     *     net_return_pct: float
     * }
     */
    private function calculateMetrics(array $trades): array
    {
        $totalTrades = count($trades);
        $wins = 0;
        $grossGain = 0.0;
        $grossLoss = 0.0;
        $equity = 100.0;
        $peakEquity = 100.0;
        $maxDd = 0.0;
        $returns = [];

        foreach ($trades as $t) {
            $pnl = $t['pnl_pct'];
            $returns[] = $pnl;

            if ($t['is_win']) {
                $wins++;
                $grossGain += $pnl;
            } else {
                $grossLoss += abs($pnl);
            }

            $equity *= (1.0 + ($pnl / 100.0));
            if ($equity > $peakEquity) {
                $peakEquity = $equity;
            }
            $dd = (($peakEquity - $equity) / $peakEquity) * 100.0;
            if ($dd > $maxDd) {
                $maxDd = $dd;
            }
        }

        $winRate = ($wins / $totalTrades) * 100.0;
        $profitFactor = $grossLoss > 0.001 ? round($grossGain / $grossLoss, 2) : ($grossGain > 0 ? 10.0 : 1.0);
        $netReturnPct = round($equity - 100.0, 2);

        // Sharpe Ratio Sederhana
        $avgReturn = count($returns) > 0 ? array_sum($returns) / count($returns) : 0.0;
        $variance = 0.0;
        foreach ($returns as $r) {
            $variance += pow($r - $avgReturn, 2);
        }
        $stdDev = count($returns) > 1 ? sqrt($variance / (count($returns) - 1)) : 1.0;
        $sharpe = $stdDev > 0.0001 ? round(($avgReturn / $stdDev) * sqrt(min($totalTrades, 252)), 2) : 0.0;

        // Multi-Objective Fitness Function
        // 1. Win Rate Reward (0 - 40 pts)
        $winRateScore = ($winRate / 100.0) * 40.0;

        // 2. Profit Factor Reward (0 - 30 pts)
        $pfScore = min(30.0, ($profitFactor / 3.0) * 30.0);

        // 3. Drawdown Penalty (0 - 20 pts)
        $ddScore = max(0.0, 20.0 - ($maxDd * 0.8));

        // 4. Sample Size Penalty (hukum jika trade < 5 atau < 10)
        $tradeCountMultiplier = 1.0;
        if ($totalTrades < 4) {
            $tradeCountMultiplier = 0.2;
        } elseif ($totalTrades < 8) {
            $tradeCountMultiplier = 0.6;
        } elseif ($totalTrades < 12) {
            $tradeCountMultiplier = 0.85;
        }

        $fitness = round(($winRateScore + $pfScore + $ddScore) * $tradeCountMultiplier, 4);

        return [
            'fitness' => $fitness,
            'win_rate' => round($winRate, 2),
            'total_trades' => $totalTrades,
            'wins' => $wins,
            'losses' => $totalTrades - $wins,
            'profit_factor' => $profitFactor,
            'max_drawdown' => round($maxDd, 2),
            'sharpe_ratio' => $sharpe,
            'net_return_pct' => $netReturnPct,
            'trades' => $trades,
        ];
    }

    /**
     * Hitung Simple Moving Average (SMA).
     *
     * @param float[] $values
     * @param int $period
     * @return array<int, float|null>
     */
    private function calculateSma(array $values, int $period): array
    {
        $n = count($values);
        $out = array_fill(0, $n, null);
        if ($period < 1 || $n < $period) {
            return $out;
        }

        $sum = 0.0;
        for ($i = 0; $i < $period; $i++) {
            $sum += (float) $values[$i];
        }
        $out[$period - 1] = $sum / $period;

        for ($i = $period; $i < $n; $i++) {
            $sum += (float) $values[$i] - (float) $values[$i - $period];
            $out[$i] = $sum / $period;
        }

        return $out;
    }

    /**
     * Hitung %K dan %D Stochastic Oscillator.
     *
     * @param array<int, array<string, mixed>> $candles
     * @param int $periodK
     * @param int $smoothK
     * @param int $periodD
     * @return array{0: array<int, float|null>, 1: array<int, float|null>} [%K, %D]
     */
    private function calculateStochastic(array $candles, int $periodK, int $smoothK, int $periodD): array
    {
        $n = count($candles);
        $rawK = array_fill(0, $n, null);

        for ($i = $periodK - 1; $i < $n; $i++) {
            $slice = array_slice($candles, $i - $periodK + 1, $periodK);
            $highs = array_column($slice, 'high');
            $lows = array_column($slice, 'low');
            $highestHigh = max($highs);
            $lowestLow = min($lows);
            $range = $highestHigh - $lowestLow;

            $close = (float) $candles[$i]['close'];
            $rawK[$i] = $range > 0.00001 ? (($close - $lowestLow) / $range) * 100.0 : 50.0;
        }

        // Smooth %K
        $kSeries = array_fill(0, $n, null);
        for ($i = $periodK + $smoothK - 2; $i < $n; $i++) {
            $sub = array_slice($rawK, $i - $smoothK + 1, $smoothK);
            if (!in_array(null, $sub, true)) {
                $kSeries[$i] = array_sum($sub) / $smoothK;
            }
        }

        // %D
        $dSeries = array_fill(0, $n, null);
        for ($i = $periodK + $smoothK + $periodD - 3; $i < $n; $i++) {
            $sub = array_slice($kSeries, $i - $periodD + 1, $periodD);
            if (!in_array(null, $sub, true)) {
                $dSeries[$i] = array_sum($sub) / $periodD;
            }
        }

        return [$kSeries, $dSeries];
    }

    private function emptyResult(): array
    {
        return [
            'fitness' => 0.0,
            'win_rate' => 0.0,
            'total_trades' => 0,
            'wins' => 0,
            'losses' => 0,
            'profit_factor' => 0.0,
            'max_drawdown' => 0.0,
            'sharpe_ratio' => 0.0,
            'net_return_pct' => 0.0,
            'trades' => [],
        ];
    }
}
