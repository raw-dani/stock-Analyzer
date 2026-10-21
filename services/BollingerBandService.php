<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use Yii;

/**
 * Service Analisis & Scanner Bollinger Bands Strategy.
 *
 * Mengidentifikasi posisi BELI dan JUAL yang presisi melalui:
 * - ⚡ Shakeout Low (Liquidity sweep di bawah Lower Band / Support yang ditarik balik cepat)
 * - Lower Band Bounce (Reversal pantulan dari batas oversold Lower Band)
 * - Bollinger Band Squeeze Breakout (Ledakan volatilitas setelah konsolidasi bandwidth sempit)
 * - Walking the Upper Band (Tren kenaikan sangat kuat / Momentum continuation)
 * - Upper Band Rejection / Overbought Climax (Titik Jual / Take Profit optimal)
 * - Middle Band Breakdown (SMA 20 breakdown / Cut Loss & Exit)
 *
 * Dilengkapi kalkulasi harga Entry, Stop Loss, Target Profit (TP1 & TP2), serta Risk/Reward Ratio.
 */
final class BollingerBandService
{
    public const TIMEFRAME_1D = 24;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_1H = 1;

    // Sinyal Utama
    public const SIGNAL_SHAKEOUT_BUY     = 'SHAKEOUT_BUY';
    public const SIGNAL_LOWER_BOUNCE     = 'LOWER_BOUNCE';
    public const SIGNAL_SQUEEZE_BREAKOUT = 'SQUEEZE_BREAKOUT';
    public const SIGNAL_WALKING_UPPER    = 'WALKING_UPPER';
    public const SIGNAL_UPPER_REVERSAL   = 'UPPER_REVERSAL';
    public const SIGNAL_MIDDLE_BREAKDOWN = 'MIDDLE_BREAKDOWN';
    public const SIGNAL_LOWER_BREAKDOWN  = 'LOWER_BREAKDOWN';
    public const SIGNAL_NEUTRAL          = 'NEUTRAL';

    // Aksi Trading
    public const ACTION_BUY         = 'BUY';
    public const ACTION_STRONG_BUY  = 'STRONG_BUY';
    public const ACTION_SELL        = 'SELL';
    public const ACTION_TAKE_PROFIT = 'TAKE_PROFIT';
    public const ACTION_HOLD        = 'HOLD';
    public const ACTION_WATCH       = 'WATCH';

    /**
     * Timeframes yang didukung.
     */
    public static function getTimeframes(): array
    {
        return [
            self::TIMEFRAME_1D => 'Daily (1 Hari)',
            self::TIMEFRAME_4H => '4 Jam (4H)',
            self::TIMEFRAME_2H => '2 Jam (2H)',
            self::TIMEFRAME_1H => '1 Jam (1H)',
        ];
    }

    /**
     * Daftar preset strategi untuk filter.
     */
    public static function getStrategyPresets(): array
    {
        return [
            'all' => 'Semua Saham & Kondisi',
            'buy_all' => 'Semua Sinyal Beli (Buy Signals)',
            'shakeout_low' => '⚡ Shakeout Low (Liquidity Sweep)',
            'lower_bounce' => 'Pantulan Lower Band (Oversold Reversal)',
            'squeeze_breakout' => 'Squeeze Breakout (Ledakan Volatilitas)',
            'walking_upper' => 'Walking Upper Band (Strong Bullish Momentum)',
            'sell_all' => 'Sinyal Jual / Take Profit',
            'upper_reversal' => 'Upper Band Reversal (Take Profit Climax)',
            'middle_breakdown' => 'Breakdown Middle Band (Trailing Stop / Exit)',
        ];
    }

    /**
     * Scan seluruh saham aktif berdasarkan strategi Bollinger Bands.
     *
     * @param array $options Filter options
     * @return array<int, array<string, mixed>>
     */
    public function scanAll(array $options = []): array
    {
        $strategy = $options['strategy'] ?? 'all';
        $minRr = (float) ($options['minRr'] ?? 0.0);
        $exchange = $options['exchange'] ?? null;
        $sector = $options['sector'] ?? null;
        $symbolSearch = $options['symbol'] ?? null;
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $period = (int) ($options['period'] ?? 20);
        $multiplier = (float) ($options['multiplier'] ?? 2.0);
        $lookback = (int) ($options['lookback'] ?? 80);

        $stockQuery = Stock::find()->where(['active' => true]);
        if (!empty($exchange)) {
            $stockQuery->andWhere(['exchange' => $exchange]);
        }
        if (!empty($sector)) {
            $stockQuery->andWhere(['sector' => $sector]);
        }
        if (!empty($symbolSearch)) {
            $stockQuery->andWhere(['like', 'symbol', $symbolSearch]);
        }

        /** @var Stock[] $stocks */
        $stocks = $stockQuery->orderBy(['symbol' => SORT_ASC])->all();
        $results = [];

        foreach ($stocks as $stock) {
            $analysis = $this->analyzeStock((int) $stock->id, [
                'timeframe' => $timeframe,
                'period' => $period,
                'multiplier' => $multiplier,
                'lookback' => $lookback,
            ]);

            if ($analysis === null) {
                continue;
            }

            // Filter berdasarkan strategi
            if (!$this->matchesStrategyFilter($analysis, $strategy)) {
                continue;
            }

            // Filter minimum Risk to Reward
            if ($minRr > 0.0 && ($analysis['rr_ratio'] ?? 0.0) < $minRr) {
                continue;
            }

            $results[] = $analysis;
        }

        return $results;
    }

    /**
     * Analisis mendalam Bollinger Bands untuk 1 saham.
     *
     * @param int $stockId
     * @param array $options
     * @return array<string, mixed>|null
     */
    public function analyzeStock(int $stockId, array $options = []): ?array
    {
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $period = (int) ($options['period'] ?? 20);
        $multiplier = (float) ($options['multiplier'] ?? 2.0);
        $lookback = (int) ($options['lookback'] ?? 80);

        $stock = Stock::findOne($stockId);
        if ($stock === null) {
            return null;
        }

        $minCandles = $period + 25;
        [$candles, $isFallback] = $this->loadCandles($stockId, $timeframe, $lookback + $period, $minCandles);

        if (count($candles) < $period + 5) {
            return null;
        }

        $closes = array_map(fn($c) => (float) $c['close'], $candles);
        $volumes = array_map(fn($c) => (int) $c['volume'], $candles);

        // Hitung Bollinger Bands
        $bb = $this->calculateBollingerBands($closes, $period, $multiplier);

        $n = count($candles);
        $idx = $n - 1;

        $currCandle = $candles[$idx];
        $prevCandle = $candles[$idx - 1];

        $currClose = (float) $currCandle['close'];
        $currOpen  = (float) $currCandle['open'];
        $currHigh  = (float) $currCandle['high'];
        $currLow   = (float) $currCandle['low'];
        $currVol   = (int) $currCandle['volume'];

        $prevClose = (float) $prevCandle['close'];
        $prevOpen  = (float) $prevCandle['open'];
        $prevLow   = (float) $prevCandle['low'];
        $prevHigh  = (float) $prevCandle['high'];

        $currMiddle = $bb['middle'][$idx] ?? null;
        $currUpper  = $bb['upper'][$idx] ?? null;
        $currLower  = $bb['lower'][$idx] ?? null;
        $currBw     = $bb['bandwidth'][$idx] ?? null;
        $currPctB   = $bb['percent_b'][$idx] ?? null;

        $prevMiddle = $bb['middle'][$idx - 1] ?? null;
        $prevUpper  = $bb['upper'][$idx - 1] ?? null;
        $prevLower  = $bb['lower'][$idx - 1] ?? null;
        $prevPctB   = $bb['percent_b'][$idx - 1] ?? null;
        $prevBw     = $bb['bandwidth'][$idx - 1] ?? null;

        if ($currMiddle === null || $currUpper === null || $currLower === null) {
            return null;
        }

        // Hitung RVOL (Relative Volume terhadap SMA 20 Volume)
        $volSlice = array_slice($volumes, -21, 20);
        $avgVol20 = count($volSlice) > 0 ? (array_sum($volSlice) / count($volSlice)) : 1.0;
        $rvol = $avgVol20 > 0 ? round($currVol / $avgVol20, 2) : 1.0;

        // Hitung RSI 14
        $rsiSeries = $this->calculateRsi($closes, 14);
        $currRsi = $rsiSeries[$idx] ?? 50.0;
        $prevRsi = $rsiSeries[$idx - 1] ?? 50.0;

        // Hitung ATR 14
        $atr = $this->calculateAtr($candles, 14);

        // Deteksi Pola Candlestick sederhana (Hammer, Engulfing, Shooting Star, Doji)
        $candlePattern = $this->detectCandlePattern($currCandle, $prevCandle);

        // Deteksi apakah sedang dalam kondisi Squeeze
        $isSqueeze = false;
        $squeezeThreshold = 8.0; // % bandwidth sempit
        if ($currBw !== null && $currBw <= $squeezeThreshold) {
            $isSqueeze = true;
        }

        // Tentukan Sinyal & Rencana Trading
        $signalData = $this->evaluateSignal(
            $currCandle,
            $prevCandle,
            $currUpper,
            $currMiddle,
            $currLower,
            $currPctB,
            $currBw,
            $prevUpper,
            $prevMiddle,
            $prevLower,
            $prevPctB,
            $prevBw,
            $rvol,
            $currRsi,
            $candlePattern,
            $atr
        );

        $action = $signalData['action'];
        $signal = $signalData['signal'];
        $isShakeoutLow = $signalData['is_shakeout_low'] ?? false;
        $shakeoutUndercutPct = $signalData['shakeout_undercut_pct'] ?? 0.0;

        // Kalkulasi Rencana Trading: Entry, Stop Loss, Target TP1 & TP2
        $plan = $this->calculateTradePlan(
            $currClose,
            $currLow,
            $currHigh,
            $currMiddle,
            $currUpper,
            $currLower,
            $atr,
            $action,
            $signal,
            $isShakeoutLow
        );

        // Rekomendasi "Kapan Beli & Kapan Jual" eksplisit
        $timingAdvice = $this->buildTimingAdvice(
            $action,
            $signal,
            $plan,
            $currClose,
            $currMiddle,
            $currUpper,
            $currLower,
            $currPctB,
            $currBw,
            $isShakeoutLow,
            $shakeoutUndercutPct,
            $rvol,
            $currRsi,
            $candlePattern
        );

        // Checklist 6-Point Validasi
        $checklist = $this->buildChecklist(
            $currClose,
            $currUpper,
            $currMiddle,
            $currLower,
            $currPctB,
            $rvol,
            $currRsi,
            $isShakeoutLow,
            $plan['rr_ratio']
        );

        // Siapkan array data historis untuk chart ECharts
        $displayCandles = array_slice($candles, -$lookback);
        $displayDates = array_column($displayCandles, 'date');
        $displayUpper = array_slice($bb['upper'], -$lookback);
        $displayMiddle = array_slice($bb['middle'], -$lookback);
        $displayLower = array_slice($bb['lower'], -$lookback);
        $displayBw = array_slice($bb['bandwidth'], -$lookback);
        $displayPctB = array_slice($bb['percent_b'], -$lookback);
        $displayRsi = array_slice($rsiSeries, -$lookback);

        $priceChangePct = $prevClose > 0 ? round((($currClose - $prevClose) / $prevClose) * 100, 2) : 0.0;

        return [
            'stock_id' => $stock->id,
            'symbol' => $stock->symbol,
            'name' => $stock->name,
            'exchange' => $stock->exchange,
            'sector' => $stock->sector,
            'date' => $currCandle['date'],
            'timeframe' => $timeframe,
            'period' => $period,
            'multiplier' => $multiplier,
            'price' => $currClose,
            'price_change_pct' => $priceChangePct,
            'upper_band' => round($currUpper, 2),
            'middle_band' => round($currMiddle, 2),
            'lower_band' => round($currLower, 2),
            'bandwidth' => round($currBw ?? 0.0, 2),
            'percent_b' => round($currPctB ?? 0.0, 2),
            'is_squeeze' => $isSqueeze,
            'rvol' => $rvol,
            'volume' => $currVol,
            'avg_volume' => (int) round($avgVol20),
            'rsi' => round($currRsi, 1),
            'atr' => round($atr, 2),
            'candle_pattern' => $candlePattern['name'] ?? null,
            'candle_signal' => $candlePattern['signal'] ?? 'NEUTRAL',

            // Sinyal & Shakeout
            'signal' => $signal,
            'action' => $action,
            'is_shakeout_low' => $isShakeoutLow,
            'shakeout_undercut_pct' => $shakeoutUndercutPct,
            'strategy_name' => $signalData['strategy_name'],
            'strategy_badge' => $signalData['strategy_badge'],
            'description' => $signalData['description'],

            // Rencana Trading
            'entry_price' => $plan['entry_price'],
            'entry_range' => $plan['entry_range'],
            'stop_loss' => $plan['stop_loss'],
            'stop_loss_pct' => $plan['stop_loss_pct'],
            'target_1' => $plan['target_1'],
            'target_1_pct' => $plan['target_1_pct'],
            'target_2' => $plan['target_2'],
            'target_2_pct' => $plan['target_2_pct'],
            'rr_ratio' => $plan['rr_ratio'],

            // Timing Kapan Beli & Kapan Jual
            'timing' => $timingAdvice,
            'checklist' => $checklist,

            // Data untuk ECharts
            'candles' => $displayCandles,
            'dates' => $displayDates,
            'upper_series' => $displayUpper,
            'middle_series' => $displayMiddle,
            'lower_series' => $displayLower,
            'bw_series' => $displayBw,
            'pct_b_series' => $displayPctB,
            'rsi_series' => $displayRsi,
            'is_fallback' => $isFallback,
        ];
    }

    /**
     * Evaluasi sinyal Bollinger Bands dengan fitur Shakeout Low & Squeeze Breakout.
     */
    private function evaluateSignal(
        array $currCandle,
        array $prevCandle,
        float $upper,
        float $middle,
        float $lower,
        ?float $pctB,
        ?float $bw,
        float $prevUpper,
        float $prevMiddle,
        float $prevLower,
        ?float $prevPctB,
        ?float $prevBw,
        float $rvol,
        float $rsi,
        array $candlePattern,
        float $atr
    ): array {
        $currClose = (float) $currCandle['close'];
        $currOpen  = (float) $currCandle['open'];
        $currHigh  = (float) $currCandle['high'];
        $currLow   = (float) $currCandle['low'];

        $prevClose = (float) $prevCandle['close'];
        $prevOpen  = (float) $prevCandle['open'];
        $prevLow   = (float) $prevCandle['low'];

        // -------------------------------------------------------------
        // 1. ⚡ SHAKEOUT LOW (Liquidity Sweep / Bear Trap)
        // Karakteristik: Low candle sempat menembus / undercut Lower Band
        // sebesar 0.2% - 5.0% di bawah Lower Band, namun harga langsung
        // diserap kuat (volume absorption) dan ditutup KEMBALI DI ATAS Lower Band.
        // Sangat sering diiringi candle Hammer / Bullish Reversal atau RVOL >= 1.2x.
        // -------------------------------------------------------------
        $isShakeoutLow = false;
        $undercutPct = 0.0;

        if ($currLow < $lower && $currClose >= $lower) {
            $undercutPct = round((($lower - $currLow) / $lower) * 100, 2);
            if ($undercutPct >= 0.2 && $undercutPct <= 5.5 && $rsi <= 58.0) {
                // Konfirmasi: close >= open (green candle) ATAU ada tail panjang / pin bar
                $body = abs($currClose - $currOpen);
                $lowerWick = min($currClose, $currOpen) - $currLow;
                $isRejection = ($lowerWick >= $body * 1.2) || ($candlePattern['signal'] ?? '') === 'BULLISH' || $rvol >= 1.2;

                if ($isRejection) {
                    $isShakeoutLow = true;
                }
            }
        }

        if ($isShakeoutLow) {
            return [
                'signal' => self::SIGNAL_SHAKEOUT_BUY,
                'action' => self::ACTION_STRONG_BUY,
                'is_shakeout_low' => true,
                'shakeout_undercut_pct' => $undercutPct,
                'strategy_name' => '⚡ Shakeout Low (Liquidity Sweep)',
                'strategy_badge' => 'warning text-dark',
                'description' => "Terjadi liquidity sweep / bear trap di bawah Lower Band: harga sempat jebol {$undercutPct}% namun ditolak keras oleh buyer dan ditutup kembali di dalam band ($currClose >= $lower). Konfirmasi penyerapan volume (RVOL {$rvol}x). Peluang Entry Beli terbaik dengan Stop Loss sangat dekat.",
            ];
        }

        // -------------------------------------------------------------
        // 2. SQUEEZE BREAKOUT (Ledakan Volatilitas)
        // Karakteristik: Bandwidth sebelumnya sangat sempit (konsolidasi),
        // kemudian harga breakout menembus Middle Band atau Upper Band
        // didukung RVOL tinggi (>= 1.3x) dan candle hijau solid.
        // -------------------------------------------------------------
        $wasSqueezed = ($prevBw !== null && $prevBw <= 9.0) || ($bw !== null && $bw <= 9.0);
        $crossedAboveMiddle = ($prevClose <= $prevMiddle && $currClose > $middle);
        $crossedAboveUpper  = ($prevClose <= $prevUpper && $currClose > $upper);

        if ($wasSqueezed && ($crossedAboveMiddle || $crossedAboveUpper) && $currClose > $currOpen && $rvol >= 1.2) {
            return [
                'signal' => self::SIGNAL_SQUEEZE_BREAKOUT,
                'action' => self::ACTION_BUY,
                'is_shakeout_low' => false,
                'strategy_name' => 'Bollinger Squeeze Breakout',
                'strategy_badge' => 'primary',
                'description' => "Harga meledak breakout keluar dari fase konsolidasi sempit (BandWidth " . round($bw ?? 0, 1) . "%). Transaksi didukung lonjakan volume {$rvol}x. Fase awal pergerakan tren naik impulsif.",
            ];
        }

        // -------------------------------------------------------------
        // 3. LOWER BAND BOUNCE (Pantulan Reversal Oversold)
        // Karakteristik: Harga menguji atau menyentuh area Lower Band (%B <= 0.15 atau Low <= Lower Band),
        // lalu memantul dengan candle hijau / bullish reversal, RSI oversold (< 42).
        // -------------------------------------------------------------
        $nearLower = ($currLow <= $lower * 1.015 && $currClose > $lower);
        $isBullishCandle = ($currClose > $currOpen) || (($candlePattern['signal'] ?? '') === 'BULLISH');

        if ($nearLower && $isBullishCandle && $rsi <= 45.0) {
            return [
                'signal' => self::SIGNAL_LOWER_BOUNCE,
                'action' => self::ACTION_BUY,
                'is_shakeout_low' => false,
                'strategy_name' => 'Lower Band Bounce (Oversold Reversal)',
                'strategy_badge' => 'success',
                'description' => "Harga memantul sempurna dari batas Lower Band (Oversold, RSI " . round($rsi, 1) . "). Area beli dasar (*Buy on Support*) dengan potensi rebound ke garis tengah Middle Band (SMA 20).",
            ];
        }

        // -------------------------------------------------------------
        // 4. WALKING THE UPPER BAND (Strong Bullish Momentum)
        // Karakteristik: Harga terus merambat di atas atau sepanjang Upper Band
        // (%B >= 0.90) dan ditutup di atas Middle Band secara beruntun.
        // -------------------------------------------------------------
        if ($currClose >= $upper * 0.985 && $currClose > $currOpen && $rsi >= 60.0 && $rsi <= 75.0) {
            return [
                'signal' => self::SIGNAL_WALKING_UPPER,
                'action' => self::ACTION_HOLD,
                'is_shakeout_low' => false,
                'strategy_name' => 'Walking the Upper Band',
                'strategy_badge' => 'info text-dark',
                'description' => "Tren bullish sangat kuat: harga merayap menempel Upper Band dengan dorongan momentum tinggi. Layak dipertahankan (*Hold*) dengan trailing stop di Middle Band (SMA 20).",
            ];
        }

        // -------------------------------------------------------------
        // 5. UPPER BAND REJECTION / OVERBOUGHT CLIMAX (Take Profit / Sell)
        // Karakteristik: Harga sempat menembus Upper Band (%B >= 1.0 atau High > Upper),
        // namun ditolak (Shooting Star / Bearish Engulfing / jarum atas panjang)
        // dan RSI berada di zona Overbought (>= 70).
        // -------------------------------------------------------------
        $upperRejection = ($currHigh >= $upper && $currClose < $upper);
        $isBearishCandle = ($currClose < $currOpen) || (($candlePattern['signal'] ?? '') === 'BEARISH');

        if (($upperRejection || ($pctB !== null && $pctB >= 0.98)) && ($isBearishCandle || $rsi >= 70.0)) {
            return [
                'signal' => self::SIGNAL_UPPER_REVERSAL,
                'action' => self::ACTION_TAKE_PROFIT,
                'is_shakeout_low' => false,
                'strategy_name' => 'Upper Band Rejection (Take Profit)',
                'strategy_badge' => 'danger',
                'description' => "Harga mengalami penolakan tajam di Upper Band dalam kondisi jenuh beli (Overbought, RSI " . round($rsi, 1) . "). Waktu yang tepat untuk mengunci keuntungan (*Take Profit*) bertahap.",
            ];
        }

        // -------------------------------------------------------------
        // 6. MIDDLE BAND BREAKDOWN (Loss of Trend / Cut Loss Exit)
        // Karakteristik: Harga menembus ke bawah garis tengah Middle Band (SMA 20)
        // dengan candle merah solid. Sinyal perubahan tren dari bullish ke bearish.
        // -------------------------------------------------------------
        if ($prevClose >= $prevMiddle && $currClose < $middle * 0.995) {
            return [
                'signal' => self::SIGNAL_MIDDLE_BREAKDOWN,
                'action' => self::ACTION_SELL,
                'is_shakeout_low' => false,
                'strategy_name' => 'Middle Band Breakdown (Exit / Cut Loss)',
                'strategy_badge' => 'danger',
                'description' => "Harga kehilangan support utama dan jebol di bawah Middle Band (SMA 20). Tren jangka pendek melemah, disarankan keluar posisi (*Exit*) atau ketatkan trailing stop.",
            ];
        }

        // -------------------------------------------------------------
        // 7. LOWER BAND BREAKDOWN (Strong Bearish Sell)
        // -------------------------------------------------------------
        if ($currClose < $lower && $currClose < $currOpen && !$isShakeoutLow) {
            return [
                'signal' => self::SIGNAL_LOWER_BREAKDOWN,
                'action' => self::ACTION_SELL,
                'is_shakeout_low' => false,
                'strategy_name' => 'Lower Band Breakdown (Bearish Dump)',
                'strategy_badge' => 'dark',
                'description' => "Harga breakdown ke bawah Lower Band tanpa tanda-tanda penyerapan. Tekanan jual masih mendominasi (*Avoid / Cut Loss*).",
            ];
        }

        // 8. Kondisi Netral / Konsolidasi
        return [
            'signal' => self::SIGNAL_NEUTRAL,
            'action' => self::ACTION_WATCH,
            'is_shakeout_low' => false,
            'strategy_name' => 'Konsolidasi Dalam Band',
            'strategy_badge' => 'secondary',
            'description' => "Harga bergerak wajar di antara garis Middle Band dan Lower/Upper Band (%B " . round($pctB ?? 0.5, 2) . "). Tunggu konfirmasi pengujian Lower Band atau Squeeze Breakout.",
        ];
    }

    /**
     * Hitung Trade Plan: Titik Entry, Stop Loss, TP1 (Middle/Upper), TP2, & Risk/Reward.
     */
    private function calculateTradePlan(
        float $close,
        float $low,
        float $high,
        float $middle,
        float $upper,
        float $lower,
        float $atr,
        string $action,
        string $signal,
        bool $isShakeoutLow
    ): array {
        $entryPrice = $close;
        $bufferAtr = max($atr * 0.5, $close * 0.01);

        if ($action === self::ACTION_BUY || $action === self::ACTION_STRONG_BUY) {
            // Stop loss di bawah titik terendah terkini / di bawah Lower Band
            if ($isShakeoutLow) {
                // Untuk Shakeout Low: SL sangat presisi tepat 0.5% - 1% di bawah wick terbawah
                $stopLoss = round($low * 0.992, 2);
            } else {
                $stopLoss = round(min($low * 0.985, $lower - ($bufferAtr * 0.8)), 2);
            }

            // Target Profit 1: Garis Middle Band (SMA 20) jika masuk di Lower Band,
            // atau Upper Band jika masuk saat Squeeze Breakout
            if ($signal === self::SIGNAL_SQUEEZE_BREAKOUT || $close >= $middle) {
                $target1 = round($upper, 2);
                $target2 = round($upper + ($upper - $middle), 2);
            } else {
                $target1 = round($middle, 2);
                $target2 = round($upper, 2);
            }

            // Pastikan TP > Entry
            if ($target1 <= $entryPrice) {
                $target1 = round($entryPrice + ($entryPrice - $stopLoss) * 1.5, 2);
            }
            if ($target2 <= $target1) {
                $target2 = round($target1 + ($entryPrice - $stopLoss), 2);
            }

            $risk = max($entryPrice - $stopLoss, 0.01);
            $reward = max($target1 - $entryPrice, 0.01);
            $rrRatio = round($reward / $risk, 2);

            $entryMin = round(min($close, $close * 0.995), 2);
            $entryMax = round(max($close, $close * 1.008), 2);
            $entryRange = "$" . number_format($entryMin, 2) . " - $" . number_format($entryMax, 2);

            $slPct = round((($entryPrice - $stopLoss) / $entryPrice) * 100, 2);
            $tp1Pct = round((($target1 - $entryPrice) / $entryPrice) * 100, 2);
            $tp2Pct = round((($target2 - $entryPrice) / $entryPrice) * 100, 2);
        } else {
            // Untuk sinyal Sell / Take Profit / Neutral
            $stopLoss = round($middle * 0.98, 2);
            $target1 = round($upper, 2);
            $target2 = round($upper * 1.05, 2);
            $entryRange = "$" . number_format($close, 2);
            $slPct = round(abs(($close - $stopLoss) / $close) * 100, 2);
            $tp1Pct = round(abs(($target1 - $close) / $close) * 100, 2);
            $tp2Pct = round(abs(($target2 - $close) / $close) * 100, 2);
            $rrRatio = 1.0;
        }

        return [
            'entry_price' => $entryPrice,
            'entry_range' => $entryRange,
            'stop_loss' => $stopLoss,
            'stop_loss_pct' => $slPct,
            'target_1' => $target1,
            'target_1_pct' => $tp1Pct,
            'target_2' => $target2,
            'target_2_pct' => $tp2Pct,
            'rr_ratio' => $rrRatio,
        ];
    }

    /**
     * Membangun panduan waktu beli dan waktu jual yang sangat jelas bagi pengguna.
     */
    private function buildTimingAdvice(
        string $action,
        string $signal,
        array $plan,
        float $close,
        float $middle,
        float $upper,
        float $lower,
        ?float $pctB,
        ?float $bw,
        bool $isShakeoutLow,
        float $shakeoutUndercutPct,
        float $rvol,
        float $rsi,
        array $candlePattern
    ): array {
        $buyTiming = [];
        $sellTiming = [];

        // KAPAN WAKTU BELI YANG TEPAT?
        if ($isShakeoutLow) {
            $buyTiming = [
                'status' => 'WAKTU BELI TERBAIK (SEKARANG)',
                'badge' => 'success',
                'summary' => "Pola Shakeout Low (Liquidity Sweep) terkonfirmasi.",
                'trigger' => "Harga baru saja menusuk ke bawah Lower Band ({$shakeoutUndercutPct}%) untuk menjebak seller, lalu langsung ditarik rebound dan ditutup di dalam band.",
                'ideal_price' => $plan['entry_range'],
                'execution' => "Beli sekarang atau antre pada pullback kecil di sekitar harga penutupan. Pasang Stop Loss ketat di $" . number_format($plan['stop_loss'], 2) . ".",
            ];
        } elseif ($signal === self::SIGNAL_SQUEEZE_BREAKOUT) {
            $buyTiming = [
                'status' => 'WAKTU BELI BREAKOUT (MOMENTUM)',
                'badge' => 'primary',
                'summary' => "Breakout ledakan volatilitas setelah fase Squeeze.",
                'trigger' => "Bandwidth Bollinger menyempit ke " . round($bw ?? 0, 1) . "% lalu ditembus ke atas dengan RVOL {$rvol}x.",
                'ideal_price' => $plan['entry_range'],
                'execution' => "Masuk posisi saat breakout terkonfirmasi. Ikuti pergerakan hingga menguji batas Upper Band.",
            ];
        } elseif ($signal === self::SIGNAL_LOWER_BOUNCE) {
            $buyTiming = [
                'status' => 'WAKTU BELI REVERSAL (BUY ON SUPPORT)',
                'badge' => 'info text-dark',
                'summary' => "Pantulan dari area Lower Band (Oversold).",
                'trigger' => "Harga menyentuh batas bawah Bollinger Band dan membentuk candle reversal dengan RSI sehat (" . round($rsi, 1) . ").",
                'ideal_price' => $plan['entry_range'],
                'execution' => "Lakukan pembelian bertahap di area Lower Band. Target awal adalah Middle Band (SMA 20).",
            ];
        } else {
            $buyTiming = [
                'status' => 'TIDAK DISARANKAN BELI SAAT INI',
                'badge' => 'secondary',
                'summary' => "Harga berada di tengah band atau sudah mendekati area jenuh beli.",
                'trigger' => "Tunggu harga menguji Lower Band (di $" . number_format($lower, 2) . ") atau terjadi Squeeze Breakout baru.",
                'ideal_price' => "Tunggu area Lower Band ($" . number_format($lower, 2) . ")",
                'execution' => "Disiplin bersabar, masukkan saham ke dalam watchlist.",
            ];
        }

        // KAPAN WAKTU JUAL YANG TEPAT?
        if ($action === self::ACTION_TAKE_PROFIT || $signal === self::SIGNAL_UPPER_REVERSAL) {
            $sellTiming = [
                'status' => 'WAKTU JUAL TEPAT (TAKE PROFIT SEKARANG)',
                'badge' => 'danger',
                'summary' => "Harga berada di batas atas Upper Band dengan indikasi penolakan / jenuh beli.",
                'trigger' => "Harga menyentuh Upper Band ($" . number_format($upper, 2) . ") dengan %B " . round($pctB ?? 1.0, 2) . " dan RSI " . round($rsi, 1) . " (Overbought).",
                'ideal_price' => "$" . number_format($close, 2) . " - $" . number_format($upper, 2),
                'execution' => "Lakukan Take Profit / jual 50%-70% porsi untuk mengunci keuntungan. Amankan modal dari potensi pembalikan ke Middle Band.",
            ];
        } elseif ($action === self::ACTION_SELL || $signal === self::SIGNAL_MIDDLE_BREAKDOWN) {
            $sellTiming = [
                'status' => 'WAKTU JUAL / CUT LOSS (EXIT POSISI)',
                'badge' => 'danger',
                'summary' => "Support Middle Band (SMA 20) telah tertembus ke bawah.",
                'trigger' => "Harga ditutup di bawah Middle Band ($" . number_format($middle, 2) . "). Struktur tren jangka pendek berubah menjadi bearish.",
                'ideal_price' => "Exit di harga pasar saat ini ($" . number_format($close, 2) . ")",
                'execution' => "Tutup seluruh posisi atau eksekusi Cut Loss untuk menghindari penurunan lebih dalam menuju Lower Band.",
            ];
        } else {
            $sellTiming = [
                'status' => 'PANDUAN WAKTU JUAL (TARGET BERTAHAP)',
                'badge' => 'warning text-dark',
                'summary' => "Pasang target jual bertingkat untuk memaksimalkan keuntungan:",
                'trigger' => "1. Target TP1 di $" . number_format($plan['target_1'], 2) . " (+" . $plan['target_1_pct'] . "%): Jual 50% porsi saat menyentuh Middle/Upper Band.\n" .
                             "2. Target TP2 di $" . number_format($plan['target_2'], 2) . " (+" . $plan['target_2_pct'] . "%): Jual sisa 50% saat harga mengalami Upper Band Climax.\n" .
                             "3. Cut Loss wajib jika harga jebol di bawah $" . number_format($plan['stop_loss'], 2) . " (-" . $plan['stop_loss_pct'] . "%).",
                'ideal_price' => "TP1: $" . number_format($plan['target_1'], 2) . " | TP2: $" . number_format($plan['target_2'], 2),
                'execution' => "Pasang Good-Till-Cancel (GTC) sell limit di TP1 dan Trailing Stop otomatis di garis Middle Band.",
            ];
        }

        return [
            'buy' => $buyTiming,
            'sell' => $sellTiming,
        ];
    }

    /**
     * Checklist 6-Point Validasi Bollinger Bands.
     */
    private function buildChecklist(
        float $close,
        float $upper,
        float $middle,
        float $lower,
        ?float $pctB,
        float $rvol,
        float $rsi,
        bool $isShakeoutLow,
        float $rr
    ): array {
        return [
            [
                'title' => '⚡ Deteksi Shakeout Low / Support Reversal',
                'desc' => $isShakeoutLow ? 'Terjadi liquidity sweep dan snap-back instan di atas Lower Band.' : 'Harga berada di area pantulan support yang valid.',
                'pass' => $isShakeoutLow || ($close >= $lower && ($pctB ?? 0.5) <= 0.4),
            ],
            [
                'title' => 'Posisi Harga Terhadap Bands (%B)',
                'desc' => 'Harga tidak berada di zona ekstrem Overbought (%B < 0.85).',
                'pass' => ($pctB !== null && $pctB <= 0.85),
            ],
            [
                'title' => 'Momentum RSI Sehat (30 - 65)',
                'desc' => "RSI berada di zona aman (" . round($rsi, 1) . "), memiliki ruang kenaikan yang cukup.",
                'pass' => ($rsi >= 30.0 && $rsi <= 65.0),
            ],
            [
                'title' => 'Aktivitas Volume (RVOL ≥ 1.0x)',
                'desc' => "Aktivitas transaksi memadai ({$rvol}x volume rata-rata).",
                'pass' => $rvol >= 1.0,
            ],
            [
                'title' => 'Risk to Reward Sehat (R:R ≥ 1.5)',
                'desc' => "Rasio potensi keuntungan terhadap risiko mencapai {$rr}:1.",
                'pass' => $rr >= 1.5,
            ],
            [
                'title' => 'Ruang Kenaikan ke Middle/Upper Band',
                'desc' => 'Terdapat jarak keuntungan yang memadai menuju target resistance band terdekat.',
                'pass' => ($upper - $close) > ($close * 0.02),
            ],
        ];
    }

    /**
     * Hitung Bollinger Bands (SMA, Upper Band, Lower Band, BandWidth, %B).
     *
     * @param float[] $closes
     * @return array{middle: (float|null)[], upper: (float|null)[], lower: (float|null)[], bandwidth: (float|null)[], percent_b: (float|null)[]}
     */
    public function calculateBollingerBands(array $closes, int $period = 20, float $multiplier = 2.0): array
    {
        $count = count($closes);
        $middle = array_fill(0, $count, null);
        $upper  = array_fill(0, $count, null);
        $lower  = array_fill(0, $count, null);
        $bandwidth = array_fill(0, $count, null);
        $percentB  = array_fill(0, $count, null);

        if ($count < $period) {
            return [
                'middle' => $middle,
                'upper' => $upper,
                'lower' => $lower,
                'bandwidth' => $bandwidth,
                'percent_b' => $percentB,
            ];
        }

        for ($i = $period - 1; $i < $count; $i++) {
            $slice = array_slice($closes, $i - $period + 1, $period);
            $mean = array_sum($slice) / $period;

            // Standar Deviasi
            $variance = 0.0;
            foreach ($slice as $val) {
                $variance += ($val - $mean) ** 2;
            }
            $stdDev = sqrt($variance / $period);

            $u = $mean + ($multiplier * $stdDev);
            $l = $mean - ($multiplier * $stdDev);

            $middle[$i] = $mean;
            $upper[$i]  = $u;
            $lower[$i]  = $l;

            // BandWidth = ((Upper - Lower) / Middle) * 100
            if ($mean > 0) {
                $bandwidth[$i] = (($u - $l) / $mean) * 100;
            }

            // %B = (Close - Lower) / (Upper - Lower)
            $bandDiff = $u - $l;
            if ($bandDiff > 0) {
                $percentB[$i] = ($closes[$i] - $l) / $bandDiff;
            } else {
                $percentB[$i] = 0.5;
            }
        }

        return [
            'middle' => $middle,
            'upper' => $upper,
            'lower' => $lower,
            'bandwidth' => $bandwidth,
            'percent_b' => $percentB,
        ];
    }

    /**
     * Hitung RSI 14-period.
     *
     * @param float[] $closes
     * @return (float|null)[]
     */
    public function calculateRsi(array $closes, int $period = 14): array
    {
        $count = count($closes);
        $rsi = array_fill(0, $count, null);
        if ($count <= $period) {
            return $rsi;
        }

        $gains = [];
        $losses = [];
        for ($i = 1; $i <= $period; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $gains[] = max($diff, 0.0);
            $losses[] = max(-$diff, 0.0);
        }

        $avgGain = array_sum($gains) / $period;
        $avgLoss = array_sum($losses) / $period;

        $rs = $avgLoss > 0 ? ($avgGain / $avgLoss) : 100.0;
        $rsi[$period] = 100.0 - (100.0 / (1.0 + $rs));

        for ($i = $period + 1; $i < $count; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $currentGain = max($diff, 0.0);
            $currentLoss = max(-$diff, 0.0);

            $avgGain = (($avgGain * ($period - 1)) + $currentGain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $currentLoss) / $period;

            if ($avgLoss == 0.0) {
                $rsi[$i] = 100.0;
            } else {
                $rs = $avgGain / $avgLoss;
                $rsi[$i] = 100.0 - (100.0 / (1.0 + $rs));
            }
        }

        return $rsi;
    }

    /**
     * Hitung Average True Range (ATR).
     */
    public function calculateAtr(array $candles, int $period = 14): float
    {
        $n = count($candles);
        if ($n < 2) {
            return 0.0;
        }

        $trValues = [];
        for ($i = 1; $i < $n; $i++) {
            $high = (float) $candles[$i]['high'];
            $low = (float) $candles[$i]['low'];
            $prevClose = (float) $candles[$i - 1]['close'];

            $tr = max($high - $low, abs($high - $prevClose), abs($low - $prevClose));
            $trValues[] = $tr;
        }

        $slice = array_slice($trValues, -$period);
        return count($slice) > 0 ? (array_sum($slice) / count($slice)) : 0.0;
    }

    /**
     * Deteksi pola candlestick sederhana.
     */
    private function detectCandlePattern(array $curr, array $prev): array
    {
        $open  = (float) $curr['open'];
        $close = (float) $curr['close'];
        $high  = (float) $curr['high'];
        $low   = (float) $curr['low'];

        $prevOpen  = (float) $prev['open'];
        $prevClose = (float) $prev['close'];

        $range = max($high - $low, 0.0001);
        $body  = abs($close - $open);
        $upperWick = $high - max($open, $close);
        $lowerWick = min($open, $close) - $low;

        // Hammer / Pin Bar
        if ($lowerWick >= $body * 2.0 && $upperWick <= $body * 0.5 && $range > 0) {
            return ['name' => 'Hammer (Pin Bar)', 'signal' => 'BULLISH'];
        }

        // Bullish Engulfing
        if ($close > $open && $prevClose < $prevOpen && $close >= $prevOpen && $open <= $prevClose) {
            return ['name' => 'Bullish Engulfing', 'signal' => 'BULLISH'];
        }

        // Shooting Star
        if ($upperWick >= $body * 2.0 && $lowerWick <= $body * 0.5 && $range > 0) {
            return ['name' => 'Shooting Star', 'signal' => 'BEARISH'];
        }

        // Bearish Engulfing
        if ($close < $open && $prevClose > $prevOpen && $open >= $prevClose && $close <= $prevOpen) {
            return ['name' => 'Bearish Engulfing', 'signal' => 'BEARISH'];
        }

        // Doji
        if ($body <= $range * 0.1) {
            return ['name' => 'Doji', 'signal' => 'NEUTRAL'];
        }

        return ['name' => null, 'signal' => 'NEUTRAL'];
    }

    /**
     * Cocokkan hasil analisis dengan filter strategi.
     */
    private function matchesStrategyFilter(array $analysis, string $strategy): bool
    {
        return match ($strategy) {
            'buy_all' => in_array($analysis['action'], [self::ACTION_BUY, self::ACTION_STRONG_BUY], true),
            'shakeout_low' => !empty($analysis['is_shakeout_low']) || $analysis['signal'] === self::SIGNAL_SHAKEOUT_BUY,
            'lower_bounce' => $analysis['signal'] === self::SIGNAL_LOWER_BOUNCE,
            'squeeze_breakout' => $analysis['signal'] === self::SIGNAL_SQUEEZE_BREAKOUT,
            'walking_upper' => $analysis['signal'] === self::SIGNAL_WALKING_UPPER,
            'sell_all' => in_array($analysis['action'], [self::ACTION_SELL, self::ACTION_TAKE_PROFIT], true),
            'upper_reversal' => $analysis['signal'] === self::SIGNAL_UPPER_REVERSAL,
            'middle_breakdown' => $analysis['signal'] === self::SIGNAL_MIDDLE_BREAKDOWN,
            default => true,
        };
    }

    /**
     * Load candle data dari database.
     * @return array{0: array, 1: bool} [candles, isFallback]
     */
    private function loadCandles(int $stockId, int $timeframe, int $lookback, int $minRequired = 32): array
    {
        if ($timeframe === self::TIMEFRAME_1D) {
            $prices = DailyPrice::find()
                ->where(['stock_id' => $stockId])
                ->orderBy(['date' => SORT_DESC])
                ->limit(max($lookback, 180))
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
            return [$out, false];
        }

        try {
            $rows = IntradayPrice::find()
                ->where(['stock_id' => $stockId, 'timeframe' => IntradayPrice::TF_1H])
                ->orderBy(['datetime' => SORT_DESC])
                ->limit(max($lookback * 4, 160))
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
                if (count($candles) >= $minRequired) {
                    return [array_slice($candles, -$lookback), false];
                }
            }
        } catch (\Throwable $e) {
            // fallback to daily
        }

        [$daily, ] = $this->loadCandles($stockId, self::TIMEFRAME_1D, $lookback);
        return [$daily, true];
    }

    /**
     * Agregasi candle intraday.
     */
    private function aggregate(array $base, int $per): array
    {
        if ($per <= 1) {
            return $base;
        }
        $out = [];
        $chunk = [];
        foreach ($base as $c) {
            $chunk[] = $c;
            if (count($chunk) >= $per) {
                $out[] = $this->makeCandle($chunk);
                $chunk = [];
            }
        }
        if (count($chunk) > 0) {
            $out[] = $this->makeCandle($chunk);
        }
        return $out;
    }

    private function makeCandle(array $chunk): array
    {
        return [
            'open' => (float) $chunk[0]['open'],
            'high' => (float) max(array_column($chunk, 'high')),
            'low' => (float) min(array_column($chunk, 'low')),
            'close' => (float) end($chunk)['close'],
            'volume' => (int) array_sum(array_column($chunk, 'volume')),
            'date' => end($chunk)['date'],
        ];
    }
}
