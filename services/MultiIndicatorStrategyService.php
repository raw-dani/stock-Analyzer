<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use Yii;

/**
 * Service Analisis Teknikal Multi-Indikator & Multi-Timeframe.
 *
 * Mengintegrasikan:
 * 1. Support & Resistance / Moving Average (SMA 20, 50, 200 & EMA 20, 50)
 * 2. RSI (Relative Strength Index) Wilder 14 - deteksi oversold (<30) & momentum
 * 3. MACD (12, 26, 9) - Crossover garis MACD & Signal line, histogram momentum
 * 4. Fibonacci Retracement - Golden Pocket (50% - 61.8%) & level penting
 * 5. Multi-Timeframe Analysis - Timeframe Utama (1D/Weekly) untuk arah tren + Timeframe Entry (1H/4H/2H) untuk timing presisi
 */
final class MultiIndicatorStrategyService
{
    public const TIMEFRAME_1D = 24;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_1H = 1;

    public const ACTION_STRONG_BUY = 'STRONG_BUY';
    public const ACTION_BUY        = 'BUY';
    public const ACTION_HOLD       = 'HOLD';
    public const ACTION_WAIT       = 'WAIT';
    public const ACTION_SELL       = 'SELL';

    /**
     * Timeframe yang didukung untuk scanner.
     */
    public static function getTimeframes(): array
    {
        return [
            self::TIMEFRAME_1D => 'Daily (1D - Tren Utama)',
            self::TIMEFRAME_4H => '4 Jam (4H - Swing Entry)',
            self::TIMEFRAME_2H => '2 Jam (2H - Momentum Entry)',
            self::TIMEFRAME_1H => '1 Jam (1H - Presisi Timing)',
        ];
    }

    /**
     * Filter Preset Strategi Gabungan.
     */
    public static function getStrategyPresets(): array
    {
        return [
            'all' => 'Semua Sinyal Gabungan',
            'confluence_buy' => '💎 High Confluence Buy (Skor ≥ 75)',
            'golden_fib_rsi' => '🎯 Golden Pocket (61.8%) + RSI Oversold',
            'macd_bullish_cross' => '🚀 MACD Bullish Cross + MA Support',
            'oversold_bounce' => '⚡ RSI Oversold (< 35) Reversal',
            'breakout_confluence' => '📈 MA & Fibonacci Breakout + Momentum',
            'sell_signals' => '🛑 Sinyal Jual / Overbought / Breakdown',
        ];
    }

    /**
     * Scan seluruh saham aktif dengan analisis gabungan multi-indikator.
     *
     * @param array $options
     * @return array<int, array<string, mixed>>
     */
    public function scanAll(array $options = []): array
    {
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $preset = (string) ($options['strategy'] ?? 'all');
        $minScore = (int) ($options['minScore'] ?? 0);
        $minRr = (float) ($options['minRr'] ?? 0.0);
        $exchange = (string) ($options['exchange'] ?? '');
        $sector = (string) ($options['sector'] ?? '');
        $symbolSearch = strtoupper((string) ($options['symbol'] ?? ''));

        $stockQuery = Stock::find()->where(['active' => true]);
        if ($exchange !== '') {
            $stockQuery->andWhere(['exchange' => $exchange]);
        }
        if ($sector !== '') {
            $stockQuery->andWhere(['sector' => $sector]);
        }
        if ($symbolSearch !== '') {
            $stockQuery->andWhere(['like', 'symbol', $symbolSearch]);
        }

        $stocks = $stockQuery->all();
        $results = [];

        foreach ($stocks as $stock) {
            $analysis = $this->analyzeStock((int) $stock->id, [
                'timeframe' => $timeframe,
            ]);

            if ($analysis === null) {
                continue;
            }

            // Filter skor minimal
            if ($minScore > 0 && ($analysis['confluence_score'] ?? 0) < $minScore) {
                continue;
            }

            // Filter Min R:R
            if ($minRr > 0.0 && ($analysis['rr_ratio'] ?? 0.0) < $minRr) {
                continue;
            }

            // Filter preset strategi
            if (!$this->matchesPreset($analysis, $preset)) {
                continue;
            }

            $results[] = $analysis;
        }

        return $results;
    }

    /**
     * Analisis komprehensif untuk satu saham:
     * - Multi-Timeframe (Daily Tren Utama + Selected Timeframe Entry)
     * - Support/Resistance & MA (SMA 20/50/200, EMA 20/50)
     * - RSI 14
     * - MACD (12, 26, 9)
     * - Fibonacci Retracement (0%, 23.6%, 38.2%, 50%, 61.8%, 78.6%, 100%) & Extension
     * - Kalkulasi Trading Plan Presisi: Entry Range, Stop Loss, Target TP1 & TP2, R:R
     *
     * @param int $stockId
     * @param array $options
     * @return array<string, mixed>|null
     */
    public function analyzeStock(int $stockId, array $options = []): ?array
    {
        $stock = Stock::findOne($stockId);
        if ($stock === null) {
            return null;
        }

        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $lookback = (int) ($options['lookback'] ?? 120);

        // 1. Ambil candle untuk timeframe aktif (timing entry)
        $entryCandles = $this->loadCandles($stockId, $timeframe, $lookback);
        if (count($entryCandles) < 40) {
            return null;
        }

        // 2. Ambil candle timeframe harian/Daily untuk konfirmasi tren besar
        $dailyCandles = $this->loadCandles($stockId, self::TIMEFRAME_1D, 120);

        // Ekstraksi array data harga
        $closes = array_map(fn($c) => (float) $c['close'], $entryCandles);
        $highs = array_map(fn($c) => (float) $c['high'], $entryCandles);
        $lows = array_map(fn($c) => (float) $c['low'], $entryCandles);
        $volumes = array_map(fn($c) => (int) $c['volume'], $entryCandles);
        $dates = array_column($entryCandles, 'date');

        $count = count($closes);
        $currIdx = $count - 1;
        $prevIdx = $count - 2;

        $currClose = $closes[$currIdx];
        $prevClose = $closes[$prevIdx];
        $currHigh = $highs[$currIdx];
        $currLow = $lows[$currIdx];
        $currVol = $volumes[$currIdx];

        // Rata-rata volume 20 candle & RVOL
        $volSlice = array_slice($volumes, -20);
        $avgVol = count($volSlice) > 0 ? (array_sum($volSlice) / count($volSlice)) : 1.0;
        $rvol = $avgVol > 0 ? round($currVol / $avgVol, 2) : 1.0;

        // ==========================================
        // 1. INDIKATOR: MOVING AVERAGE (SMA & EMA) & S/R
        // ==========================================
        $sma20Series = $this->sma($closes, 20);
        $sma50Series = $this->sma($closes, 50);
        $sma200Series = $this->sma($closes, 200);
        $ema20Series = $this->ema($closes, 20);
        $ema50Series = $this->ema($closes, 50);

        $currSma20 = $sma20Series[$currIdx];
        $currSma50 = $sma50Series[$currIdx];
        $currSma200 = $sma200Series[$currIdx];
        $currEma20 = $ema20Series[$currIdx];
        $currEma50 = $ema50Series[$currIdx];

        $prevSma20 = $sma20Series[$prevIdx];
        $prevSma50 = $sma50Series[$prevIdx];

        // Support & Resistance dinamis berbasis MA dan pivot lokal
        $recentLows = array_slice($lows, -30);
        $recentHighs = array_slice($highs, -30);
        $staticSupport = count($recentLows) > 0 ? min($recentLows) : $currLow;
        $staticResistance = count($recentHighs) > 0 ? max($recentHighs) : $currHigh;

        // Nilai support terdekat di bawah harga saat ini
        $potentialSupports = array_filter([$currEma20, $currSma50, $currSma200, $staticSupport], fn($v) => $v !== null && $v <= $currClose);
        $nearestSupport = !empty($potentialSupports) ? max($potentialSupports) : $currLow * 0.98;

        // Nilai resistance terdekat di atas harga saat ini
        $potentialResistances = array_filter([$currEma20, $currSma50, $currSma200, $staticResistance], fn($v) => $v !== null && $v > $currClose);
        $nearestResistance = !empty($potentialResistances) ? min($potentialResistances) : $currHigh * 1.05;

        // MA Bullish Alignment
        $maBullish = ($currEma20 !== null && $currSma50 !== null && $currEma20 > $currSma50);
        $priceAboveMa = ($currEma20 !== null && $currClose >= $currEma20 * 0.99);

        // ==========================================
        // 2. INDIKATOR: RSI (RELATIVE STRENGTH INDEX 14)
        // ==========================================
        $rsiSeries = RsiService::calculate($closes, 14);
        $currRsi = $rsiSeries[$currIdx] ?? 50.0;
        $prevRsi = $rsiSeries[$prevIdx] ?? 50.0;
        $rsiSlope = $currRsi - $prevRsi; // Momentum naik/turun

        $isRsiOversold = ($currRsi <= 35.0);
        $isRsiExtremeOversold = ($currRsi <= 30.0);
        $isRsiBullishZone = ($currRsi >= 45.0 && $currRsi <= 65.0);
        $isRsiOverbought = ($currRsi >= 70.0);

        // ==========================================
        // 3. INDIKATOR: MACD (12, 26, 9)
        // ==========================================
        $macdData = $this->calculateMacd($closes, 12, 26, 9);
        $macdSeries = $macdData['macd'];
        $macdSignalSeries = $macdData['signal'];
        $macdHistSeries = $macdData['histogram'];

        $currMacd = $macdSeries[$currIdx] ?? 0.0;
        $currMacdSignal = $macdSignalSeries[$currIdx] ?? 0.0;
        $currMacdHist = $macdHistSeries[$currIdx] ?? 0.0;
        $prevMacd = $macdSeries[$prevIdx] ?? 0.0;
        $prevMacdSignal = $macdSignalSeries[$prevIdx] ?? 0.0;
        $prevMacdHist = $macdHistSeries[$prevIdx] ?? 0.0;

        $isMacdBullishCross = ($prevMacd <= $prevMacdSignal && $currMacd > $currMacdSignal);
        $isMacdBearishCross = ($prevMacd >= $prevMacdSignal && $currMacd < $currMacdSignal);
        $isMacdHistogramExpanding = ($currMacdHist > $prevMacdHist && $currMacdHist > 0);
        $isMacdPositive = ($currMacd > 0.0);

        // ==========================================
        // 4. INDIKATOR: FIBONACCI RETRACEMENT & GOLDEN POCKET
        // ==========================================
        $fibData = $this->calculateFibonacciLevels($entryCandles, 50);
        $swingHigh = $fibData['swing_high'];
        $swingLow = $fibData['swing_low'];
        $retracePct = $fibData['retrace_pct'];
        $fibLevels = $fibData['levels'];

        $isInGoldenPocket = ($retracePct >= 50.0 && $retracePct <= 65.0);
        $isNearGoldenPocket = ($retracePct >= 45.0 && $retracePct <= 70.0);
        $isPullback382 = ($retracePct >= 35.0 && $retracePct < 48.0);
        $isDeepDiscount = ($retracePct > 65.0 && $retracePct <= 78.6);

        // ==========================================
        // 5. MULTI-TIMEFRAME CONFLUENCE (TREN BESAR vs ENTRY)
        // ==========================================
        $dailyCloses = array_map(fn($c) => (float) $c['close'], $dailyCandles);
        $dailySma50 = $this->sma($dailyCloses, 50);
        $dailyCount = count($dailyCloses);
        $dailyCurrClose = $dailyCloses[$dailyCount - 1] ?? $currClose;
        $dailyCurrMa50 = $dailySma50[$dailyCount - 1] ?? null;

        $higherTfTrend = 'SIDEWAYS';
        if ($dailyCurrMa50 !== null) {
            if ($dailyCurrClose > $dailyCurrMa50 * 1.01) {
                $higherTfTrend = 'BULLISH';
            } elseif ($dailyCurrClose < $dailyCurrMa50 * 0.99) {
                $higherTfTrend = 'BEARISH';
            }
        }

        // ==========================================
        // 6. SKOR KONFLUENSI (CONFLUENCE SCORING 0 - 100)
        // ==========================================
        $score = 0;
        $signalsList = [];

        // Kriteria 1: Multi-Timeframe Higher Trend (Bobot 20)
        if ($higherTfTrend === 'BULLISH') {
            $score += 20;
            $signalsList[] = 'Tren Harian (Higher TF) Bullish di atas MA50';
        }

        // Kriteria 2: Support / Moving Average (Bobot 20)
        if ($currClose >= $nearestSupport * 0.99 && $currClose <= $nearestSupport * 1.03) {
            $score += 20;
            $signalsList[] = 'Harga bertengger tepat di Dynamic Support MA';
        } elseif ($maBullish && $priceAboveMa) {
            $score += 15;
            $signalsList[] = 'Struktur Moving Average Bullish (EMA20 > SMA50)';
        }

        // Kriteria 3: RSI Momentum & Oversold (Bobot 20)
        if ($isRsiExtremeOversold) {
            $score += 20;
            $signalsList[] = "RSI Oversold Ekstrem ({$currRsi} < 30) - Potensi Pantulan";
        } elseif ($isRsiOversold) {
            $score += 18;
            $signalsList[] = "RSI Jenuh Jual ({$currRsi} <= 35)";
        } elseif ($rsiSlope > 0 && $currRsi >= 40 && $currRsi <= 60) {
            $score += 15;
            $signalsList[] = "RSI Momentum Reversal naik ({$currRsi})";
        }

        // Kriteria 4: MACD Signal (Bobot 20)
        if ($isMacdBullishCross) {
            $score += 20;
            $signalsList[] = 'MACD Bullish Crossover terkonfirmasi (Golden Cross)';
        } elseif ($isMacdHistogramExpanding) {
            $score += 15;
            $signalsList[] = 'Histogram MACD positif dan mengembang';
        } elseif ($currMacdHist > $prevMacdHist && $prevMacdHist < 0) {
            $score += 12;
            $signalsList[] = 'Momentum pelemahan MACD berbalik arah positif';
        }

        // Kriteria 5: Fibonacci Golden Pocket (Bobot 20)
        if ($isInGoldenPocket) {
            $score += 20;
            $signalsList[] = "Area Beli Emas Fibonacci 61.8% Golden Pocket ({$retracePct}%)";
        } elseif ($isNearGoldenPocket || $isPullback382) {
            $score += 14;
            $signalsList[] = "Koreksi Sehat Fibonacci Retracement ({$retracePct}%)";
        }

        // Bonus Volume (RVOL >= 1.2x)
        if ($rvol >= 1.2) {
            $score = min(100, $score + 5);
            $signalsList[] = "Konfirmasi Volume Mantap ({$rvol}x)";
        }

        // ==========================================
        // 7. PENETAPAN AKSI & SINYAL REKOMENDASI
        // ==========================================
        $action = self::ACTION_WAIT;
        $signalTitle = 'Wait & Observe';
        $badgeColor = 'secondary';

        if ($score >= 75) {
            $action = self::ACTION_STRONG_BUY;
            $signalTitle = 'Strong Confluence Buy';
            $badgeColor = 'success';
        } elseif ($score >= 60) {
            $action = self::ACTION_BUY;
            $signalTitle = 'Confluence Buy';
            $badgeColor = 'primary';
        } elseif ($isRsiOverbought || $isMacdBearishCross) {
            $action = self::ACTION_SELL;
            $signalTitle = 'Take Profit / Overbought';
            $badgeColor = 'danger';
        } elseif ($higherTfTrend === 'BULLISH' && $priceAboveMa) {
            $action = self::ACTION_HOLD;
            $signalTitle = 'Trend Following (Hold)';
            $badgeColor = 'info text-white';
        }

        // ==========================================
        // 8. KALKULASI TRADING PLAN PRESISI (ENTRY, SL, TP1, TP2, R:R)
        // ==========================================
        $entryPrice = $currClose;
        $entryLow = round(min($currClose, $nearestSupport * 0.995), 2);
        $entryHigh = round(max($currClose, $nearestSupport * 1.015), 2);
        $entryRange = '$' . number_format($entryLow, 2) . ' - $' . number_format($entryHigh, 2);

        // Stop Loss: 1.5% - 2% di bawah support terdekat atau swing low
        $slReference = min($nearestSupport, $currLow, $fibLevels['786'] ?? $currLow);
        $stopLoss = round($slReference * 0.98, 2);
        if ($stopLoss >= $entryPrice) {
            $stopLoss = round($entryPrice * 0.965, 2); // Default max 3.5% risk
        }

        $risk = max(0.01, $entryPrice - $stopLoss);
        $stopLossPct = round(($risk / $entryPrice) * 100, 2);

        // Target Profit 1: Minimal Swing High terdekat atau rasio R:R 1.8x
        $target1 = round(max($entryPrice + ($risk * 1.8), $swingHigh), 2);
        $target1Pct = round((($target1 - $entryPrice) / $entryPrice) * 100, 2);

        // Target Profit 2: Ekstensi Fibonacci 1.272 atau R:R 3.0x
        $fibExt1272 = round($swingLow + (($swingHigh - $swingLow) * 1.272), 2);
        $target2 = round(max($entryPrice + ($risk * 3.0), $fibExt1272), 2);
        $target2Pct = round((($target2 - $entryPrice) / $entryPrice) * 100, 2);

        $rrRatio = round(($target1 - $entryPrice) / $risk, 1);

        return [
            'stock_id' => $stock->id,
            'symbol' => $stock->symbol,
            'name' => $stock->name,
            'exchange' => $stock->exchange,
            'sector' => $stock->sector,
            'current_price' => $currClose,
            'date' => $dates[$currIdx],
            'timeframe' => $timeframe,
            // Skor & Sinyal
            'confluence_score' => $score,
            'action' => $action,
            'signal_title' => $signalTitle,
            'badge_color' => $badgeColor,
            'signals_list' => $signalsList,
            // Multi-Timeframe
            'higher_tf_trend' => $higherTfTrend,
            // Indikator 1: MA & S/R
            'sma_20' => $currSma20 !== null ? round($currSma20, 2) : null,
            'sma_50' => $currSma50 !== null ? round($currSma50, 2) : null,
            'sma_200' => $currSma200 !== null ? round($currSma200, 2) : null,
            'ema_20' => $currEma20 !== null ? round($currEma20, 2) : null,
            'ema_50' => $currEma50 !== null ? round($currEma50, 2) : null,
            'support' => round($nearestSupport, 2),
            'resistance' => round($nearestResistance, 2),
            // Indikator 2: RSI
            'rsi' => round($currRsi, 1),
            'rsi_status' => $currRsi <= 30 ? 'OVERSOLD' : ($currRsi >= 70 ? 'OVERBOUGHT' : 'NEUTRAL'),
            // Indikator 3: MACD
            'macd' => round($currMacd, 3),
            'macd_signal' => round($currMacdSignal, 3),
            'macd_hist' => round($currMacdHist, 3),
            'macd_cross' => $isMacdBullishCross ? 'BULLISH_CROSS' : ($isMacdBearishCross ? 'BEARISH_CROSS' : 'NONE'),
            // Indikator 4: Fibonacci
            'swing_high' => round($swingHigh, 2),
            'swing_low' => round($swingLow, 2),
            'retrace_pct' => $retracePct,
            'fib_levels' => $fibLevels,
            'in_golden_pocket' => $isInGoldenPocket,
            // Volume
            'rvol' => $rvol,
            // Trading Plan Presisi
            'entry_price' => $entryPrice,
            'entry_range' => $entryRange,
            'stop_loss' => $stopLoss,
            'stop_loss_pct' => $stopLossPct,
            'target_1' => $target1,
            'target_1_pct' => $target1Pct,
            'target_2' => $target2,
            'target_2_pct' => $target2Pct,
            'rr_ratio' => $rrRatio,
            // Candle Data untuk Chart
            'candles' => $entryCandles,
            'dates' => $dates,
            'sma20_series' => $sma20Series,
            'sma50_series' => $sma50Series,
            'ema20_series' => $ema20Series,
            'rsi_series' => $rsiSeries,
            'macd_series' => $macdSeries,
            'macd_signal_series' => $macdSignalSeries,
            'macd_hist_series' => $macdHistSeries,
        ];
    }

    /**
     * Filter apakah hasil analisis cocok dengan preset yang dipilih.
     */
    private function matchesPreset(array $analysis, string $preset): bool
    {
        return match ($preset) {
            'confluence_buy' => ($analysis['confluence_score'] >= 75),
            'golden_fib_rsi' => ($analysis['in_golden_pocket'] && $analysis['rsi'] <= 45),
            'macd_bullish_cross' => ($analysis['macd_cross'] === 'BULLISH_CROSS'),
            'oversold_bounce' => ($analysis['rsi'] <= 35),
            'breakout_confluence' => ($analysis['confluence_score'] >= 60 && $analysis['rvol'] >= 1.2),
            'sell_signals' => in_array($analysis['action'], [self::ACTION_SELL], true),
            default => true,
        };
    }

    /**
     * Hitung level Fibonacci Retracement & Extension dari swing high dan swing low.
     */
    private function calculateFibonacciLevels(array $candles, int $lookback = 50): array
    {
        $slice = array_slice($candles, -$lookback);
        $highs = array_map(fn($c) => (float) $c['high'], $slice);
        $lows = array_map(fn($c) => (float) $c['low'], $slice);
        $lastClose = (float) $slice[count($slice) - 1]['close'];

        $maxHigh = max($highs);
        $minLow = min($lows);
        $diff = max(0.01, $maxHigh - $minLow);

        $retracePct = round((($maxHigh - $lastClose) / $diff) * 100, 1);

        $levels = [
            '0'    => round($maxHigh, 2),
            '236'  => round($maxHigh - ($diff * 0.236), 2),
            '382'  => round($maxHigh - ($diff * 0.382), 2),
            '500'  => round($maxHigh - ($diff * 0.500), 2),
            '618'  => round($maxHigh - ($diff * 0.618), 2),
            '786'  => round($maxHigh - ($diff * 0.786), 2),
            '1000' => round($minLow, 2),
            '1272' => round($minLow + ($diff * 1.272), 2),
            '1618' => round($minLow + ($diff * 1.618), 2),
        ];

        return [
            'swing_high' => $maxHigh,
            'swing_low' => $minLow,
            'retrace_pct' => $retracePct,
            'levels' => $levels,
        ];
    }

    /**
     * Hitung MACD (Moving Average Convergence Divergence).
     * Default: Fast=12, Slow=26, Signal=9.
     */
    public function calculateMacd(array $closes, int $fast = 12, int $slow = 26, int $signal = 9): array
    {
        $fastEma = $this->ema($closes, $fast);
        $slowEma = $this->ema($closes, $slow);
        $n = count($closes);

        $macdLine = [];
        $macdValuesForSignal = [];

        for ($i = 0; $i < $n; $i++) {
            if ($fastEma[$i] !== null && $slowEma[$i] !== null) {
                $diff = $fastEma[$i] - $slowEma[$i];
                $macdLine[$i] = $diff;
                $macdValuesForSignal[] = $diff;
            } else {
                $macdLine[$i] = null;
            }
        }

        // Signal line adalah EMA dari garis MACD
        $signalEma = $this->emaDense($macdValuesForSignal, $signal);

        $signalLine = array_fill(0, $n, null);
        $histogram = array_fill(0, $n, null);

        $signalOffset = $n - count($signalEma);
        for ($i = 0; $i < count($signalEma); $i++) {
            $idx = $signalOffset + $i;
            $sigVal = $signalEma[$i];
            $signalLine[$idx] = $sigVal;

            if ($sigVal !== null && $macdLine[$idx] !== null) {
                $histogram[$idx] = round($macdLine[$idx] - $sigVal, 4);
            }
        }

        return [
            'macd' => $macdLine,
            'signal' => $signalLine,
            'histogram' => $histogram,
        ];
    }

    /**
     * Hitung Exponential Moving Average (EMA).
     */
    public function ema(array $values, int $period): array
    {
        $n = count($values);
        $result = array_fill(0, $n, null);
        if ($n < $period || $period <= 0) {
            return $result;
        }

        $multiplier = 2.0 / ($period + 1.0);
        $initialSlice = array_slice($values, 0, $period);
        $sma = array_sum($initialSlice) / $period;
        $result[$period - 1] = $sma;

        $prevEma = $sma;
        for ($i = $period; $i < $n; $i++) {
            $currVal = (float) $values[$i];
            $currentEma = (($currVal - $prevEma) * $multiplier) + $prevEma;
            $result[$i] = $currentEma;
            $prevEma = $currentEma;
        }

        return $result;
    }

    /**
     * EMA helper untuk array yang sudah padat (tanpa leading null).
     */
    private function emaDense(array $values, int $period): array
    {
        $n = count($values);
        $result = array_fill(0, $n, null);
        if ($n < $period || $period <= 0) {
            return $result;
        }

        $multiplier = 2.0 / ($period + 1.0);
        $initialSlice = array_slice($values, 0, $period);
        $sma = array_sum($initialSlice) / $period;
        $result[$period - 1] = $sma;

        $prevEma = $sma;
        for ($i = $period; $i < $n; $i++) {
            $currVal = (float) $values[$i];
            $currentEma = (($currVal - $prevEma) * $multiplier) + $prevEma;
            $result[$i] = $currentEma;
            $prevEma = $currentEma;
        }

        return $result;
    }

    /**
     * Hitung Simple Moving Average (SMA).
     */
    public function sma(array $values, int $period): array
    {
        $result = [];
        $window = [];
        foreach ($values as $v) {
            $window[] = (float) $v;
            if (count($window) > $period) {
                array_shift($window);
            }
            $result[] = count($window) < $period ? null : (array_sum($window) / count($window));
        }
        return $result;
    }

    /**
     * Muat candle data dengan fallback otomatis jika data intraday belum cukup.
     */
    private function loadCandles(int $stockId, int $timeframe, int $lookback): array
    {
        if ($timeframe === self::TIMEFRAME_1D) {
            $prices = DailyPrice::find()
                ->where(['stock_id' => $stockId])
                ->orderBy(['date' => SORT_DESC])
                ->limit($lookback)
                ->all();

            $out = [];
            foreach (array_reverse($prices) as $p) {
                $out[] = [
                    'open' => (float) $p->open,
                    'high' => (float) $p->high,
                    'low' => (float) $p->low,
                    'close' => (float) $p->close,
                    'volume' => (int) $p->volume,
                    'date' => $p->date,
                ];
            }
            return $out;
        }

        // Intraday logic dengan validasi kecukupan bar hasil agregasi
        try {
            $rows = IntradayPrice::find()
                ->where(['stock_id' => $stockId])
                ->orderBy(['datetime' => SORT_DESC])
                ->limit(max($lookback * 4, 120))
                ->all();

            if (count($rows) >= 20) {
                $base = [];
                foreach (array_reverse($rows) as $p) {
                    $base[] = [
                        'open' => (float) $p->open,
                        'high' => (float) $p->high,
                        'low' => (float) $p->low,
                        'close' => (float) $p->close,
                        'volume' => (int) $p->volume,
                        'date' => $p->datetime,
                    ];
                }
                $per = match ($timeframe) {
                    self::TIMEFRAME_4H => 4,
                    self::TIMEFRAME_2H => 2,
                    default => 1,
                };
                $candles = $this->aggregate($base, $per);
                if (count($candles) >= 50) {
                    return array_slice($candles, -$lookback);
                }
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return $this->loadCandles($stockId, self::TIMEFRAME_1D, $lookback);
    }

    /**
     * Agregasi lilin 1H -> 2H/4H.
     */
    private function aggregate(array $base, int $per): array
    {
        if ($per <= 1) {
            return $base;
        }
        $out = [];
        $n = count($base);
        for ($i = 0; $i < $n; $i += $per) {
            $chunk = array_slice($base, $i, $per);
            if (empty($chunk)) {
                continue;
            }
            $open = $chunk[0]['open'];
            $close = $chunk[count($chunk) - 1]['close'];
            $high = max(array_column($chunk, 'high'));
            $low = min(array_column($chunk, 'low'));
            $vol = array_sum(array_column($chunk, 'volume'));
            $date = $chunk[count($chunk) - 1]['date'];
            $out[] = [
                'open' => (float) $open,
                'high' => (float) $high,
                'low' => (float) $low,
                'close' => (float) $close,
                'volume' => (int) $vol,
                'date' => $date,
            ];
        }
        return $out;
    }
}
