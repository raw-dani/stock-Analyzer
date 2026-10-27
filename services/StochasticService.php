<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use Yii;

/**
 * Service Analisis & Scanner Stochastic Oscillator Strategy.
 *
 * Mengidentifikasi momentum, titik jenuh jual/beli, dan pembalikan arah melalui:
 * - 🚀 Bullish Golden Cross in Oversold (%K memotong ke atas %D di area < 20)
 * - 🎯 Regular Bullish Divergence (Pembalikan arah harga naik yang kuat)
 * - 📈 Hidden Bullish Divergence (Konfirmasi kelanjutan tren naik / Buy on Dip)
 * - 🌊 Oversold Reversal (%K keluar dari area jenuh jual menuju ke atas level 20)
 * - ⚡ Bullish 50-Level Cross (Konfirmasi momentum ekspansi bullish)
 * - ⚠️ Bearish Death Cross in Overbought (%K memotong ke bawah %D di area > 80)
 * - 🔻 Regular Bearish Divergence (Peringatan pembalikan arah turun / Take Profit)
 * - 🔥 Overbought Exhaustion (Keluar dari area jenuh beli ke bawah level 80)
 *
 * Dilengkapi kalkulasi harga Entry, Stop Loss, Target Profit (TP1 & TP2), serta Risk/Reward Ratio.
 */
final class StochasticService
{
    public const TIMEFRAME_1D = 24;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_1H = 1;

    // Sinyal Utama
    public const SIGNAL_SHAKEOUT_LOW           = 'SHAKEOUT_LOW';
    public const SIGNAL_GOLDEN_CROSS_OVERSOLD  = 'GOLDEN_CROSS_OVERSOLD';
    public const SIGNAL_BULLISH_DIVERGENCE     = 'BULLISH_DIVERGENCE';
    public const SIGNAL_HIDDEN_BULLISH_DIV     = 'HIDDEN_BULLISH_DIV';
    public const SIGNAL_OVERSOLD_BOUNCE        = 'OVERSOLD_BOUNCE';
    public const SIGNAL_BULLISH_50_CROSS       = 'BULLISH_50_CROSS';
    public const SIGNAL_DEATH_CROSS_OVERBOUGHT = 'DEATH_CROSS_OVERBOUGHT';
    public const SIGNAL_BEARISH_DIVERGENCE     = 'BEARISH_DIVERGENCE';
    public const SIGNAL_OVERBOUGHT_REVERSAL    = 'OVERBOUGHT_REVERSAL';
    public const SIGNAL_NEUTRAL                = 'NEUTRAL';

    // Aksi Trading
    public const ACTION_STRONG_BUY  = 'STRONG_BUY';
    public const ACTION_BUY         = 'BUY';
    public const ACTION_TAKE_PROFIT = 'TAKE_PROFIT';
    public const ACTION_SELL        = 'SELL';
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
     * Daftar preset strategi untuk filter scanner.
     */
    public static function getStrategyPresets(): array
    {
        return [
            'all' => 'Semua Saham & Kondisi',
            'buy_all' => 'Semua Sinyal Beli (Buy Signals)',
            'shakeout_low' => '🛡️ Shakeout Low (False Breakdown & Reclaim)',
            'golden_cross_oversold' => '🚀 Golden Cross di Oversold (< 20)',
            'bullish_divergence' => '🎯 Regular Bullish Divergence',
            'hidden_bullish' => '📈 Hidden Bullish Divergence (Buy on Dip)',
            'oversold_zone' => '🌊 Berada di Area Oversold (%K < 20)',
            'bullish_50_cross' => '⚡ Cross Level 50 ke Atas',
            'sell_all' => 'Semua Sinyal Jual / Take Profit',
            'death_cross_overbought' => '⚠️ Death Cross di Overbought (> 80)',
            'bearish_divergence' => '🔻 Regular Bearish Divergence',
            'overbought_zone' => '🔥 Berada di Area Overbought (%K > 80)',
        ];
    }

    /**
     * Scan seluruh saham aktif berdasarkan parameter Stochastic Oscillator.
     *
     * @param array $options Filter options
     * @return array<int, array<string, mixed>>
     */
    public function scanAll(array $options = []): array
    {
        $strategy     = $options['strategy'] ?? 'all';
        $minRr        = (float) ($options['minRr'] ?? 0.0);
        $exchange     = $options['exchange'] ?? null;
        $sector       = $options['sector'] ?? null;
        $symbolSearch = $options['symbol'] ?? null;
        $timeframe    = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $periodK      = (int) ($options['periodK'] ?? 14);
        $smoothK      = (int) ($options['smoothK'] ?? 3);
        $periodD      = (int) ($options['periodD'] ?? 3);
        $lookback     = (int) ($options['lookback'] ?? 80);

        // Smart Version Caching
        $cache = Yii::$app->cache;
        $dataVersion = ScannerCacheService::getDataVersion($timeframe);
        $cacheKey = 'stochastic_scan_' . md5(json_encode([
            'ver' => $dataVersion,
            'strategy' => $strategy,
            'minRr' => $minRr,
            'exchange' => $exchange,
            'sector' => $sector,
            'symbol' => $symbolSearch,
            'tf' => $timeframe,
            'k' => $periodK,
            'sk' => $smoothK,
            'd' => $periodD,
            'lb' => $lookback,
        ]));

        if ($cache && !isset($options['nocache'])) {
            $cached = $cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $stockQuery = Stock::find()->where(['active' => true]);
        if (!empty($exchange)) {
            $stockQuery->andWhere(['exchange' => $exchange]);
        }
        if (!empty($sector) && $sector !== 'all') {
            $stockQuery->andWhere(['sector' => $sector]);
        }
        if (!empty($symbolSearch)) {
            $stockQuery->andWhere(['like', 'symbol', strtoupper(trim($symbolSearch))]);
        }

        $stocks = $stockQuery->orderBy(['symbol' => SORT_ASC])->all();
        $results = [];

        foreach ($stocks as $stock) {
            $analysis = $this->analyzeStock((int) $stock->id, [
                'timeframe' => $timeframe,
                'periodK' => $periodK,
                'smoothK' => $smoothK,
                'periodD' => $periodD,
                'lookback' => $lookback,
            ]);

            if ($analysis === null) {
                continue;
            }

            // Filter strategi
            if (!$this->matchesStrategyFilter($analysis, $strategy)) {
                continue;
            }

            // Filter Risk / Reward
            if ($minRr > 0.0 && ($analysis['risk_reward_ratio'] ?? 0.0) < $minRr) {
                continue;
            }

            $results[] = $analysis;
        }

        // Sorting default: Sinyal Beli terbaik di atas, lalu berdasarkan Win-Rate Score & RR
        usort($results, function ($a, $b) {
            $actionRank = [
                self::ACTION_STRONG_BUY => 5,
                self::ACTION_BUY => 4,
                self::ACTION_WATCH => 3,
                self::ACTION_TAKE_PROFIT => 2,
                self::ACTION_SELL => 1,
            ];
            $rA = $actionRank[$a['action']] ?? 0;
            $rB = $actionRank[$b['action']] ?? 0;
            if ($rA !== $rB) {
                return $rB <=> $rA;
            }
            $scoreA = $a['confidence_score'] ?? 0;
            $scoreB = $b['confidence_score'] ?? 0;
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }
            return ($b['risk_reward_ratio'] ?? 0) <=> ($a['risk_reward_ratio'] ?? 0);
        });

        if ($cache) {
            $cache->set($cacheKey, $results, 600); // 10 menit
        }

        return $results;
    }

    /**
     * Analisis Stochastic komprehensif untuk satu saham.
     *
     * @param int $stockId
     * @param array $options
     * @return array<string, mixed>|null
     */
    public function analyzeStock(int $stockId, array $options = []): ?array
    {
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $periodK   = (int) ($options['periodK'] ?? 14);
        $smoothK   = (int) ($options['smoothK'] ?? 3);
        $periodD   = (int) ($options['periodD'] ?? 3);
        $lookback  = (int) ($options['lookback'] ?? 80);

        $stock = Stock::findOne($stockId);
        if ($stock === null) {
            return null;
        }

        $minCandles = $periodK + $smoothK + $periodD + 10;
        [$candles, $isFallback] = $this->loadCandles($stockId, $timeframe, $lookback + $periodK, $minCandles);

        if (count($candles) < $minCandles) {
            return null;
        }

        $highs   = array_map(fn($c) => (float) $c['high'], $candles);
        $lows    = array_map(fn($c) => (float) $c['low'], $candles);
        $closes  = array_map(fn($c) => (float) $c['close'], $candles);
        $volumes = array_map(fn($c) => (int) $c['volume'], $candles);
        $dates   = array_map(fn($c) => (string) $c['date'], $candles);

        // Hitung seri Stochastic (%K dan %D)
        $stoch = $this->calculateStochastic($highs, $lows, $closes, $periodK, $smoothK, $periodD);
        $kSeries = $stoch['k'];
        $dSeries = $stoch['d'];

        $n = count($candles);
        $idx = $n - 1;

        $currCandle = $candles[$idx];
        $prevCandle = $candles[$idx - 1];

        $currClose = (float) $currCandle['close'];
        $currHigh  = (float) $currCandle['high'];
        $currLow   = (float) $currCandle['low'];
        $currOpen  = (float) $currCandle['open'];
        $currVol   = (int) $currCandle['volume'];

        $currK = $kSeries[$idx];
        $currD = $dSeries[$idx];
        $prevK = $kSeries[$idx - 1];
        $prevD = $dSeries[$idx - 1];

        if ($currK === null || $currD === null || $prevK === null || $prevD === null) {
            return null;
        }

        // Relative Volume (RVOL 20)
        $volSlice = array_slice($volumes, -21, 20);
        $avgVol20 = count($volSlice) > 0 ? (array_sum($volSlice) / count($volSlice)) : 1.0;
        $rvol = $avgVol20 > 0 ? round($currVol / $avgVol20, 2) : 1.0;

        // ATR 14
        $atr = $this->calculateAtr($candles, 14);

        // Deteksi Divergence
        $divergence = $this->detectDivergence($closes, $kSeries, 30);

        // Deteksi Pola Shakeout Low (False Breakdown & Reclaim)
        $shakeoutData = $this->detectShakeoutLow($candles, $kSeries, $dSeries, 30);

        // Evaluasi Sinyal & Rencana Trading
        $signalData = $this->evaluateSignal(
            $currK,
            $currD,
            $prevK,
            $prevD,
            $divergence,
            $shakeoutData,
            $rvol,
            $currClose,
            $currOpen
        );

        $tradePlan = $this->calculateTradePlan(
            $currClose,
            $currLow,
            $currHigh,
            $atr,
            $signalData['action'],
            $signalData['signal'],
            $shakeoutData
        );

        // Potong array seri untuk chart sesuai lookback agar visual rapi
        $chartCandles = array_slice($candles, -$lookback);
        $chartDates   = array_slice($dates, -$lookback);
        $chartK       = array_slice($kSeries, -$lookback);
        $chartD       = array_slice($dSeries, -$lookback);

        return [
            'stock_id' => $stock->id,
            'symbol' => $stock->symbol,
            'name' => $stock->name,
            'exchange' => $stock->exchange,
            'sector' => $stock->sector,
            'timeframe' => $timeframe,
            'is_fallback_tf' => $isFallback,
            'current_price' => $currClose,
            'change_pct' => $prevCandle['close'] > 0 ? round((($currClose - (float)$prevCandle['close']) / (float)$prevCandle['close']) * 100, 2) : 0.0,
            'volume' => $currVol,
            'rvol' => $rvol,
            'curr_k' => round($currK, 2),
            'curr_d' => round($currD, 2),
            'prev_k' => round($prevK, 2),
            'prev_d' => round($prevD, 2),
            'is_oversold' => $currK < 20.0,
            'is_overbought' => $currK > 80.0,
            'zone' => $currK < 20.0 ? 'OVERSOLD' : ($currK > 80.0 ? 'OVERBOUGHT' : 'NEUTRAL'),
            'divergence' => $divergence,
            'shakeout_low' => $shakeoutData,
            'signal' => $signalData['signal'],
            'action' => $signalData['action'],
            'strategy_name' => $signalData['strategy_name'],
            'strategy_badge' => $signalData['strategy_badge'],
            'description' => $signalData['description'],
            'confidence_score' => $signalData['confidence_score'],
            'entry_price' => $tradePlan['entry'],
            'stop_loss' => $tradePlan['stop_loss'],
            'stop_loss_pct' => $tradePlan['stop_loss_pct'],
            'target_profit_1' => $tradePlan['tp1'],
            'target_profit_1_pct' => $tradePlan['tp1_pct'],
            'target_profit_2' => $tradePlan['tp2'],
            'target_profit_2_pct' => $tradePlan['tp2_pct'],
            'risk_reward_ratio' => $tradePlan['risk_reward_ratio'],
            'candles' => $chartCandles,
            'dates' => $chartDates,
            'k_series' => $chartK,
            'd_series' => $chartD,
        ];
    }

    /**
     * Hitung seri Stochastic Oscillator (%K & %D).
     *
     * @param float[] $highs
     * @param float[] $lows
     * @param float[] $closes
     * @param int $periodK
     * @param int $smoothK
     * @param int $periodD
     * @return array{k: (float|null)[], d: (float|null)[]}
     */
    public function calculateStochastic(
        array $highs,
        array $lows,
        array $closes,
        int $periodK = 14,
        int $smoothK = 3,
        int $periodD = 3
    ): array {
        $n = count($closes);
        $fastK = array_fill(0, $n, null);
        $slowK = array_fill(0, $n, null);
        $slowD = array_fill(0, $n, null);

        if ($n < $periodK) {
            return ['k' => $slowK, 'd' => $slowD];
        }

        // 1. Hitung Fast %K = (Close - Lowest Low) / (Highest High - Lowest Low) * 100
        for ($i = $periodK - 1; $i < $n; $i++) {
            $sliceHighs = array_slice($highs, $i - $periodK + 1, $periodK);
            $sliceLows  = array_slice($lows, $i - $periodK + 1, $periodK);

            $hh = max($sliceHighs);
            $ll = min($sliceLows);
            $range = $hh - $ll;

            if ($range > 0.0) {
                $fastK[$i] = (($closes[$i] - $ll) / $range) * 100.0;
            } else {
                $fastK[$i] = 50.0;
            }
        }

        // 2. Hitung Slow %K = SMA dari Fast %K dengan periode smoothK
        for ($i = $periodK - 1 + $smoothK - 1; $i < $n; $i++) {
            $sum = 0.0;
            $valid = true;
            for ($j = 0; $j < $smoothK; $j++) {
                $val = $fastK[$i - $j];
                if ($val === null) {
                    $valid = false;
                    break;
                }
                $sum += $val;
            }
            if ($valid) {
                $slowK[$i] = round($sum / $smoothK, 2);
            }
        }

        // 3. Hitung Slow %D = SMA dari Slow %K dengan periode periodD
        for ($i = $periodK - 1 + $smoothK - 1 + $periodD - 1; $i < $n; $i++) {
            $sum = 0.0;
            $valid = true;
            for ($j = 0; $j < $periodD; $j++) {
                $val = $slowK[$i - $j];
                if ($val === null) {
                    $valid = false;
                    break;
                }
                $sum += $val;
            }
            if ($valid) {
                $slowD[$i] = round($sum / $periodD, 2);
            }
        }

        return ['k' => $slowK, 'd' => $slowD];
    }

    /**
     * Deteksi Regular & Hidden Divergence antara Harga dan Stochastic.
     *
     * @param float[] $closes
     * @param (float|null)[] $kSeries
     * @param int $lookback
     * @return array{type: string|null, name: string|null, quality: string|null}
     */
    public function detectDivergence(array $closes, array $kSeries, int $lookback = 30): array
    {
        $n = count($closes);
        if ($n < 25) {
            return ['type' => null, 'name' => null, 'quality' => null];
        }

        $currClose = $closes[$n - 1];
        $currK     = $kSeries[$n - 1];

        if ($currK === null) {
            return ['type' => null, 'name' => null, 'quality' => null];
        }

        // Cari swing low & swing high terdekat di lookback
        $searchLen = min($lookback, $n - 5);
        $minPriceIdx = null;
        $minPriceVal = PHP_FLOAT_MAX;
        $minKVal = PHP_FLOAT_MAX;

        for ($i = $n - $searchLen; $i < $n - 3; $i++) {
            if ($closes[$i] < $minPriceVal) {
                $minPriceVal = $closes[$i];
                $minPriceIdx = $i;
            }
        }

        if ($minPriceIdx !== null && isset($kSeries[$minPriceIdx]) && $kSeries[$minPriceIdx] !== null) {
            $prevK = $kSeries[$minPriceIdx];

            // 1. Regular Bullish Divergence: Harga buat Lower Low, tapi %K buat Higher Low di zona Oversold
            if ($currClose <= $minPriceVal * 1.01 && $currK >= $prevK + 5.0 && $prevK <= 30.0) {
                return [
                    'type' => 'BULLISH_REGULAR',
                    'name' => '🎯 Regular Bullish Divergence',
                    'quality' => 'HIGH',
                ];
            }

            // 2. Hidden Bullish Divergence: Harga buat Higher Low, tapi %K buat Lower Low (Uptrend continuation)
            if ($currClose > $minPriceVal * 1.02 && $currK < $prevK - 3.0 && $currK <= 45.0) {
                return [
                    'type' => 'BULLISH_HIDDEN',
                    'name' => '📈 Hidden Bullish Divergence',
                    'quality' => 'MEDIUM',
                ];
            }
        }

        // Cari swing high untuk Bearish Divergence
        $maxPriceVal = -PHP_FLOAT_MAX;
        $maxPriceIdx = null;
        for ($i = $n - $searchLen; $i < $n - 3; $i++) {
            if ($closes[$i] > $maxPriceVal) {
                $maxPriceVal = $closes[$i];
                $maxPriceIdx = $i;
            }
        }

        if ($maxPriceIdx !== null && isset($kSeries[$maxPriceIdx]) && $kSeries[$maxPriceIdx] !== null) {
            $prevHighK = $kSeries[$maxPriceIdx];

            // Regular Bearish Divergence: Harga buat Higher High, tapi %K buat Lower High di area Overbought
            if ($currClose >= $maxPriceVal * 0.99 && $currK <= $prevHighK - 5.0 && $prevHighK >= 70.0) {
                return [
                    'type' => 'BEARISH_REGULAR',
                    'name' => '🔻 Regular Bearish Divergence',
                    'quality' => 'HIGH',
                ];
            }
        }

        return ['type' => null, 'name' => null, 'quality' => null];
    }

    /**
     * Deteksi Pola Shakeout Low (Spring / False Breakdown & Reclaim).
     *
     * Karakteristik Wyckoff Spring / Shakeout Low:
     * 1. Terbentuk swing low support pada 6 - 32 candle sebelumnya ($supportLow).
     * 2. Pada candle terkini atau candle sebelumnya, harga mengalami downthrust menembus low support
     *    (terjadi liquidity sweep untuk memicu stop-loss ritel).
     * 3. Terjadi penolakan keras (reclaim): Harga ditutup kembali di atas support atau membentuk
     *    candle hammer/pin bar dengan lower wick panjang (>= 40% range candle).
     * 4. Konfirmasi Stochastic: Osilator berada di area jenuh jual (%K <= 35) dan mulai melengkung
     *    naik (%K > %D atau %K > prev %K) yang mengindikasikan akumulasi institusional / smart money.
     *
     * @param array<int, array<string, mixed>> $candles
     * @param (float|null)[] $kSeries
     * @param (float|null)[] $dSeries
     * @param int $lookback
     * @return array<string, mixed>
     */
    public function detectShakeoutLow(
        array $candles,
        array $kSeries,
        array $dSeries,
        int $lookback = 30
    ): array {
        $n = count($candles);
        if ($n < 20) {
            return ['detected' => false];
        }

        $idx = $n - 1;
        $currCandle = $candles[$idx];
        $prevCandle = $candles[$idx - 1];

        $currClose = (float) $currCandle['close'];
        $currLow   = (float) $currCandle['low'];
        $currHigh  = (float) $currCandle['high'];
        $currOpen  = (float) $currCandle['open'];
        $currRange = max(0.0001, $currHigh - $currLow);

        // Cari swing low support dalam 6 s/d 32 bar sebelum bar pengujian
        $searchStart = max(0, $n - min($lookback + 5, 35));
        $searchEnd   = $n - 3;
        if ($searchEnd <= $searchStart) {
            return ['detected' => false];
        }

        $minSupportLow = PHP_FLOAT_MAX;
        $minSupportIdx = null;

        for ($i = $searchStart; $i <= $searchEnd; $i++) {
            $lowVal = (float) $candles[$i]['low'];
            if ($lowVal < $minSupportLow) {
                $minSupportLow = $lowVal;
                $minSupportIdx = $i;
            }
        }

        if ($minSupportIdx === null || $minSupportLow <= 0) {
            return ['detected' => false];
        }

        $currK = $kSeries[$idx] ?? null;
        $currD = $dSeries[$idx] ?? null;
        $prevK = $kSeries[$idx - 1] ?? null;

        if ($currK === null || $currD === null) {
            return ['detected' => false];
        }

        // Skenario 1: Bar terkini (curr) melakukan sweep low dan reclaim
        $isSweepCurr = ($currLow < $minSupportLow);
        $isReclaimCurr = ($currClose >= $minSupportLow * 0.996);
        $lowerWickCurr = ($currClose >= $currOpen) ? ($currOpen - $currLow) : ($currClose - $currLow);
        $isHammerCurr = ($lowerWickCurr / $currRange >= 0.40);

        // Skenario 2: Bar kemarin (prev) yang melakukan sweep, dan bar terkini reclaim di atas support
        $prevLow = (float) $prevCandle['low'];
        $prevClose = (float) $prevCandle['close'];
        $isSweepPrev = ($prevLow < $minSupportLow);
        $isReclaimAfterPrev = ($currClose >= $minSupportLow && $currClose > $prevLow);

        $isShakeout = false;
        $shakeoutLow = $currLow;
        $shakeoutBar = 'CURRENT';

        if ($isSweepCurr && ($isReclaimCurr || $isHammerCurr)) {
            $isShakeout = true;
            $shakeoutLow = $currLow;
            $shakeoutBar = 'CURRENT';
        } elseif ($isSweepPrev && $isReclaimAfterPrev) {
            $isShakeout = true;
            $shakeoutLow = $prevLow;
            $shakeoutBar = 'PREVIOUS';
        }

        if (!$isShakeout) {
            return ['detected' => false];
        }

        // Konfirmasi Stochastic: Harus berada di zona jenuh jual (%K <= 35) atau baru keluar dari oversold
        $stochOversoldOrHook = ($currK <= 35.0 || ($prevK !== null && $prevK <= 25.0));
        if (!$stochOversoldOrHook) {
            return ['detected' => false];
        }

        $sweepPct = round((($minSupportLow - $shakeoutLow) / $minSupportLow) * 100, 2);

        return [
            'detected' => true,
            'support_level' => round($minSupportLow, 2),
            'shakeout_low' => round($shakeoutLow, 2),
            'reclaim_price' => round($currClose, 2),
            'sweep_pct' => $sweepPct,
            'shakeout_bar' => $shakeoutBar,
            'curr_k' => round($currK, 1),
            'curr_d' => round($currD, 1),
            'is_golden_cross' => ($currK > $currD),
        ];
    }

    /**
     * Evaluasi sinyal trading Stochastic.
     */
    private function evaluateSignal(
        float $currK,
        float $currD,
        float $prevK,
        float $prevD,
        array $divergence,
        array $shakeoutData,
        float $rvol,
        float $currClose,
        float $currOpen
    ): array {
        $isCrossUp   = ($prevK <= $prevD && $currK > $currD);
        $isCrossDown = ($prevK >= $prevD && $currK < $currD);
        $isBullishCandle = ($currClose >= $currOpen);

        // 1. SHAKEOUT LOW REVERSAL (SPRING / FALSE BREAKDOWN RECLAIM)
        if (!empty($shakeoutData['detected'])) {
            $isGc = ($currK > $currD);
            $isDiv = ($divergence['type'] === 'BULLISH_REGULAR');
            $conf = $isDiv ? 94 : ($isGc ? 92 : 87);
            $strategyName = $isDiv ? '🛡️ Shakeout Low + Bullish Divergence' : ($isGc ? '🛡️ Shakeout Low + Golden Cross' : '🛡️ Shakeout Low (Spring Reclaim)');
            $badge = $isGc ? 'success' : 'warning text-dark border border-warning';
            $barDesc = ($shakeoutData['shakeout_bar'] === 'CURRENT') ? 'pada candle saat ini' : 'pada candle sebelumnya';
            return [
                'signal' => self::SIGNAL_SHAKEOUT_LOW,
                'action' => self::ACTION_STRONG_BUY,
                'strategy_name' => $strategyName,
                'strategy_badge' => $badge,
                'confidence_score' => $conf,
                'description' => "Pola Shakeout Low (Wyckoff Spring) {$barDesc}: Harga menembus support $" . number_format($shakeoutData['support_level'], 2) . " ke titik terendah $" . number_format($shakeoutData['shakeout_low'], 2) . " (-{$shakeoutData['sweep_pct']}%) untuk memicu stop-loss ritel, namun langsung di-reclaim kembali ke $" . number_format($shakeoutData['reclaim_price'], 2) . " dengan osilator Stochastic di zona oversold (%K " . round($currK, 1) . "). Jebakan bear trap klasik dengan potensi pantulan sangat kuat.",
            ];
        }

        // 2. REGULAR BULLISH DIVERGENCE (Highest Quality Reversal)
        if ($divergence['type'] === 'BULLISH_REGULAR') {
            return [
                'signal' => self::SIGNAL_BULLISH_DIVERGENCE,
                'action' => self::ACTION_STRONG_BUY,
                'strategy_name' => 'Regular Bullish Divergence',
                'strategy_badge' => 'success',
                'confidence_score' => 88,
                'description' => "Harga membentuk Lower Low sementara Stochastic membentuk Higher Low di area jenuh jual (%K " . round($currK, 1) . "). Mengindikasikan penyerapan akumulasi cerdas dan peluang reversal sangat tinggi.",
            ];
        }

        // 2. BULLISH GOLDEN CROSS IN OVERSOLD (< 20 atau < 25)
        if ($isCrossUp && ($currK <= 25.0 || $prevK <= 20.0)) {
            $conf = ($rvol >= 1.2) ? 85 : 78;
            return [
                'signal' => self::SIGNAL_GOLDEN_CROSS_OVERSOLD,
                'action' => self::ACTION_STRONG_BUY,
                'strategy_name' => 'Golden Cross in Oversold (< 20)',
                'strategy_badge' => 'success',
                'confidence_score' => $conf,
                'description' => "Garis %K (" . round($currK, 1) . ") memotong ke atas garis %D (" . round($currD, 1) . ") tepat di zona Oversold jenuh jual. Sinyal beli konfirmasi pantulan reversal utama.",
            ];
        }

        // 3. HIDDEN BULLISH DIVERGENCE (Trend Continuation)
        if ($divergence['type'] === 'BULLISH_HIDDEN' && $isCrossUp) {
            return [
                'signal' => self::SIGNAL_HIDDEN_BULLISH_DIV,
                'action' => self::ACTION_BUY,
                'strategy_name' => 'Hidden Bullish Divergence (Buy on Dip)',
                'strategy_badge' => 'success',
                'confidence_score' => 80,
                'description' => "Harga menjaga Higher Low saat osilator Stochastic mengalami *reset* ke bawah. Menandakan koreksi sehat dalam tren naik (*Buy on Dip* optimal).",
            ];
        }

        // 4. OVERSOLD REVERSAL (Keluar dari zona < 20)
        if ($prevK <= 20.0 && $currK > 20.0 && $currK > $currD) {
            return [
                'signal' => self::SIGNAL_OVERSOLD_BOUNCE,
                'action' => self::ACTION_BUY,
                'strategy_name' => 'Oversold Recovery (Cross > 20)',
                'strategy_badge' => 'primary',
                'confidence_score' => 74,
                'description' => "Stochastic berhasil menembus keluar dari zona jenuh jual ke level " . round($currK, 1) . " dengan momentum beli yang menguat.",
            ];
        }

        // 5. BULLISH 50-CROSSOVER (Momentum Expansion)
        if ($prevK < 50.0 && $currK >= 50.0 && $currK > $currD && $isBullishCandle) {
            return [
                'signal' => self::SIGNAL_BULLISH_50_CROSS,
                'action' => self::ACTION_BUY,
                'strategy_name' => 'Midline 50 Breakout (Momentum)',
                'strategy_badge' => 'info text-dark',
                'confidence_score' => 70,
                'description' => "Stochastic melintasi level 50 dari bawah dengan dominasi pembeli. Mengonfirmasi kelanjutan fase bullish aktif.",
            ];
        }

        // 6. REGULAR BEARISH DIVERGENCE (Distribution Warning)
        if ($divergence['type'] === 'BEARISH_REGULAR') {
            return [
                'signal' => self::SIGNAL_BEARISH_DIVERGENCE,
                'action' => self::ACTION_TAKE_PROFIT,
                'strategy_name' => 'Regular Bearish Divergence',
                'strategy_badge' => 'danger',
                'confidence_score' => 84,
                'description' => "Harga mencetak Higher High namun Stochastic melemah membentuk Lower High di area jenuh beli (%K " . round($currK, 1) . "). Sinyal divergensi distribusi, amankan profit (*Take Profit*).",
            ];
        }

        // 7. BEARISH DEATH CROSS IN OVERBOUGHT (> 80 atau > 75)
        if ($isCrossDown && ($currK >= 75.0 || $prevK >= 80.0)) {
            return [
                'signal' => self::SIGNAL_DEATH_CROSS_OVERBOUGHT,
                'action' => self::ACTION_SELL,
                'strategy_name' => 'Death Cross in Overbought (> 80)',
                'strategy_badge' => 'danger',
                'confidence_score' => 82,
                'description' => "Garis %K (" . round($currK, 1) . ") menukik memotong ke bawah %D di zona Overbought jenuh beli. Tekanan jual mendominasi, disarankan keluar posisi atau pasang trailing stop ketat.",
            ];
        }

        // 8. OVERBOUGHT REVERSAL (Jatuh ke bawah level 80)
        if ($prevK >= 80.0 && $currK < 80.0 && $currK < $currD) {
            return [
                'signal' => self::SIGNAL_OVERBOUGHT_REVERSAL,
                'action' => self::ACTION_TAKE_PROFIT,
                'strategy_name' => 'Overbought Reversal (Drop < 80)',
                'strategy_badge' => 'warning text-dark',
                'confidence_score' => 75,
                'description' => "Stochastic kehilangan tenaga di puncak dan menembus kembali ke bawah level 80. Mengindikasikan awal fase koreksi harga.",
            ];
        }

        // 9. ZONA OVERSOLD SEDANG BERLANGSUNG
        if ($currK < 20.0) {
            return [
                'signal' => self::SIGNAL_NEUTRAL,
                'action' => self::ACTION_WATCH,
                'strategy_name' => 'Area Jenuh Jual (%K < 20)',
                'strategy_badge' => 'secondary',
                'confidence_score' => 60,
                'description' => "Harga berada di dalam zona jenuh jual ekstrim (%K " . round($currK, 1) . "). Tunggu konfirmasi Golden Cross sebelum entry beli.",
            ];
        }

        // 10. ZONA OVERBOUGHT SEDANG BERLANGSUNG
        if ($currK > 80.0) {
            return [
                'signal' => self::SIGNAL_NEUTRAL,
                'action' => self::ACTION_WATCH,
                'strategy_name' => 'Area Jenuh Beli (%K > 80)',
                'strategy_badge' => 'secondary',
                'confidence_score' => 60,
                'description' => "Harga berada di dalam zona jenuh beli tinggi (%K " . round($currK, 1) . "). Hindari *FOMO buy*, siapkan exit jika terjadi Death Cross.",
            ];
        }

        // 11. KONDISI NETRAL
        return [
            'signal' => self::SIGNAL_NEUTRAL,
            'action' => self::ACTION_WATCH,
            'strategy_name' => 'Konsolidasi Netral (20 - 80)',
            'strategy_badge' => 'secondary',
            'confidence_score' => 50,
            'description' => "Stochastic berada di rentang tengah yang wajar (%K " . round($currK, 1) . ", %D " . round($currD, 1) . "). Tidak ada anomali atau sinyal ekstrim saat ini.",
        ];
    }

    /**
     * Hitung Trade Plan: Titik Entry, Stop Loss, Target Profit 1, Target Profit 2, & RR Ratio.
     */
    private function calculateTradePlan(
        float $close,
        float $low,
        float $high,
        float $atr,
        string $action,
        string $signal,
        array $shakeoutData = []
    ): array {
        $entryPrice = $close;
        $bufferAtr = max($atr * 0.7, $close * 0.012);

        if ($signal === self::SIGNAL_SHAKEOUT_LOW && !empty($shakeoutData['detected']) && !empty($shakeoutData['shakeout_low'])) {
            // Stop loss tepat di bawah jarum sweep low shakeout dengan buffer tipis 0.8%
            $stopLoss = round(max(0.01, $shakeoutData['shakeout_low'] * 0.992), 2);
            $risk = max(0.01, $entryPrice - $stopLoss);
            $stopLossPct = round(($risk / $entryPrice) * 100, 2);

            // TP1 target 2.0x risk
            $tp1 = round($entryPrice + ($risk * 2.0), 2);
            $tp1Pct = round((($tp1 - $entryPrice) / $entryPrice) * 100, 2);

            // TP2 target ekspansi 3.5x risk
            $tp2 = round($entryPrice + ($risk * 3.5), 2);
            $tp2Pct = round((($tp2 - $entryPrice) / $entryPrice) * 100, 2);

            $rr = round(($tp1 - $entryPrice) / $risk, 2);
        } elseif ($action === self::ACTION_STRONG_BUY || $action === self::ACTION_BUY) {
            // Stop loss di bawah low terkini dengan buffer ATR
            $stopLoss = round(max(0.01, $low - $bufferAtr), 2);
            $risk = max(0.01, $entryPrice - $stopLoss);
            $stopLossPct = round(($risk / $entryPrice) * 100, 2);

            // TP1 target moderat (1.6x Risk)
            $tp1 = round($entryPrice + ($risk * 1.6), 2);
            $tp1Pct = round((($tp1 - $entryPrice) / $entryPrice) * 100, 2);

            // TP2 target ekspansi (2.8x Risk)
            $tp2 = round($entryPrice + ($risk * 2.8), 2);
            $tp2Pct = round((($tp2 - $entryPrice) / $entryPrice) * 100, 2);

            $rr = round(($tp1 - $entryPrice) / $risk, 2);
        } else {
            // Untuk sinyal Sell / Take profit / Watch
            $stopLoss = round($low * 0.98, 2);
            $stopLossPct = 2.0;
            $tp1 = round($entryPrice * 1.03, 2);
            $tp1Pct = 3.0;
            $tp2 = round($entryPrice * 1.06, 2);
            $tp2Pct = 6.0;
            $rr = 1.5;
        }

        return [
            'entry' => $entryPrice,
            'stop_loss' => $stopLoss,
            'stop_loss_pct' => $stopLossPct,
            'tp1' => $tp1,
            'tp1_pct' => $tp1Pct,
            'tp2' => $tp2,
            'tp2_pct' => $tp2Pct,
            'risk_reward_ratio' => $rr,
        ];
    }

    /**
     * Cocokkan hasil analisis dengan filter strategi.
     */
    private function matchesStrategyFilter(array $analysis, string $strategy): bool
    {
        return match ($strategy) {
            'buy_all' => in_array($analysis['action'], [self::ACTION_BUY, self::ACTION_STRONG_BUY], true),
            'shakeout_low' => ($analysis['signal'] === self::SIGNAL_SHAKEOUT_LOW || (!empty($analysis['shakeout_low']['detected']))),
            'golden_cross_oversold' => $analysis['signal'] === self::SIGNAL_GOLDEN_CROSS_OVERSOLD,
            'bullish_divergence' => $analysis['signal'] === self::SIGNAL_BULLISH_DIVERGENCE,
            'hidden_bullish' => $analysis['signal'] === self::SIGNAL_HIDDEN_BULLISH_DIV,
            'oversold_zone' => !empty($analysis['is_oversold']),
            'bullish_50_cross' => $analysis['signal'] === self::SIGNAL_BULLISH_50_CROSS,
            'sell_all' => in_array($analysis['action'], [self::ACTION_SELL, self::ACTION_TAKE_PROFIT], true),
            'death_cross_overbought' => $analysis['signal'] === self::SIGNAL_DEATH_CROSS_OVERBOUGHT,
            'bearish_divergence' => $analysis['signal'] === self::SIGNAL_BEARISH_DIVERGENCE,
            'overbought_zone' => !empty($analysis['is_overbought']),
            default => true,
        };
    }

    /**
     * Hitung Average True Range (ATR).
     */
    private function calculateAtr(array $candles, int $period = 14): float
    {
        $n = count($candles);
        if ($n < 2) {
            return 0.0;
        }

        $trValues = [];
        for ($i = 1; $i < $n; $i++) {
            $h = (float) $candles[$i]['high'];
            $l = (float) $candles[$i]['low'];
            $prevC = (float) $candles[$i - 1]['close'];

            $tr = max($h - $l, abs($h - $prevC), abs($l - $prevC));
            $trValues[] = $tr;
        }

        $slice = array_slice($trValues, -$period);
        return count($slice) > 0 ? (array_sum($slice) / count($slice)) : 0.0;
    }

    /**
     * Load candle data dari database.
     * @return array{0: array, 1: bool} [candles, isFallback]
     */
    private function loadCandles(int $stockId, int $timeframe, int $lookback, int $minRequired = 30): array
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
     * Agregasi candle intraday (e.g. 1H -> 4H / 2H).
     */
    private function aggregate(array $base, int $per): array
    {
        if ($per <= 1) {
            return $base;
        }
        $out = [];
        $chunks = array_chunk($base, $per);
        foreach ($chunks as $chunk) {
            $open = $chunk[0]['open'];
            $close = end($chunk)['close'];
            $date = end($chunk)['date'];
            $high = -PHP_FLOAT_MAX;
            $low = PHP_FLOAT_MAX;
            $vol = 0;
            foreach ($chunk as $b) {
                $high = max($high, $b['high']);
                $low = min($low, $b['low']);
                $vol += $b['volume'];
            }
            $out[] = [
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => $vol,
                'date' => $date,
            ];
        }
        return $out;
    }
}
