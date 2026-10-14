<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;

/**
 * Service untuk deteksi Pola Kelanjutan & Breakout Bullish Klasik:
 * 1. Cup and Handle
 * 2. Bullish Rectangle (Darvas Box)
 * 3. Bullish Pennant
 * 4. Bullish Flag
 * 5. Ascending Triangle
 *
 * Menghitung titik entri Beli ideal (Breakout, Retest, Bounce),
 * multi-target Jual (TP1 50%, TP2 100%, TP3 161.8%), Stop Loss adaptif,
 * serta deteksi risiko False Breakout (Bull Trap).
 */
final class BullishPatternService
{
    public const TIMEFRAME_1D = 0;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_1H = 1;

    public const DEFAULT_TIMEFRAME = self::TIMEFRAME_1D;
    public const DEFAULT_LOOKBACK = 90;
    public const STOP_LOSS_BUFFER = 0.02; // buffer 2% di bawah level support
    private const MIN_INTRADAY_BARS = 30;

    // Pattern Types
    public const PATTERN_ALL = 'ALL';
    public const PATTERN_CUP_AND_HANDLE = 'CUP_AND_HANDLE';
    public const PATTERN_BULLISH_RECTANGLE = 'BULLISH_RECTANGLE';
    public const PATTERN_BULLISH_PENNANT = 'BULLISH_PENNANT';
    public const PATTERN_BULLISH_FLAG = 'BULLISH_FLAG';
    public const PATTERN_ASCENDING_TRIANGLE = 'ASCENDING_TRIANGLE';

    // Execution Timing Status
    public const STATUS_BUY_ZONE = 'buy_zone';
    public const STATUS_RETEST = 'retest';
    public const STATUS_CONSOLIDATION_BOUNCE = 'consolidation_bounce';
    public const STATUS_EXTENDED = 'extended';
    public const STATUS_OVEREXTENDED = 'overextended';
    public const STATUS_APPROACHING = 'approaching';
    public const STATUS_FORMING = 'forming';

    // False Breakout Risk Levels
    public const RISK_ALL = 'ALL';
    public const RISK_LOW = 'LOW';
    public const RISK_MEDIUM = 'MEDIUM';
    public const RISK_HIGH = 'HIGH';
    public const RISK_BULL_TRAP = 'BULL_TRAP';

    /**
     * Scan seluruh saham aktif untuk mendeteksi pola Bullish
     */
    public function scanAll(
        int|array $timeframe = self::DEFAULT_TIMEFRAME,
        ?int $lookback = null,
        ?string $patternType = null,
        ?string $statusFilter = null,
        ?float $minRr = null,
        bool $breakoutOnly = false,
        bool $volOnly = false,
        ?string $falseBreakoutRisk = null,
        int $minConfidence = 0
    ): array {
        $singleSymbol = null;
        if (is_array($timeframe)) {
            $opts = $timeframe;
            $timeframe = (int) ($opts['timeframe'] ?? self::DEFAULT_TIMEFRAME);
            $lookback = isset($opts['lookback']) ? (int) $opts['lookback'] : $lookback;
            $patternType = isset($opts['patternType']) ? (string) $opts['patternType'] : $patternType;
            $statusFilter = isset($opts['statusFilter']) ? (string) $opts['statusFilter'] : $statusFilter;
            $minRr = isset($opts['minRr']) ? (float) $opts['minRr'] : $minRr;
            $breakoutOnly = isset($opts['breakoutOnly']) ? (bool) $opts['breakoutOnly'] : $breakoutOnly;
            $volOnly = isset($opts['volOnly']) ? (bool) $opts['volOnly'] : $volOnly;
            $falseBreakoutRisk = isset($opts['falseBreakoutRisk']) ? (string) $opts['falseBreakoutRisk'] : $falseBreakoutRisk;
            $minConfidence = isset($opts['minConfidence']) ? (int) $opts['minConfidence'] : $minConfidence;
            $singleSymbol = $opts['symbol'] ?? null;
        }

        $lookback ??= self::DEFAULT_LOOKBACK;
        $patternType ??= self::PATTERN_ALL;
        $falseBreakoutRisk ??= self::RISK_ALL;
        $this->validateOptions($timeframe, $lookback);

        $results = [];
        $query = Stock::find()->where(['active' => true]);
        if ($singleSymbol !== null && $singleSymbol !== '') {
            $query->andWhere(['symbol' => $singleSymbol]);
        }
        $stocks = $query->all();

        \Yii::info("Bullish Patterns Scan started: " . count($stocks) . " stocks, timeframe={$timeframe}, lookback={$lookback}, patternType={$patternType}", 'app\services\bullishpattern');

        foreach ($stocks as $stock) {
            $pattern = $this->detectPattern($stock->id, $timeframe, $lookback, $patternType);
            if ($pattern === null) {
                continue;
            }

            if ($patternType !== self::PATTERN_ALL && ($pattern['pattern_type'] ?? '') !== $patternType) {
                continue;
            }

            if ($falseBreakoutRisk !== self::RISK_ALL) {
                if ($falseBreakoutRisk === self::RISK_BULL_TRAP) {
                    if (empty($pattern['bull_trap_detected'])) {
                        continue;
                    }
                } elseif (($pattern['false_breakout_risk'] ?? '') !== $falseBreakoutRisk) {
                    continue;
                }
            }

            if ($breakoutOnly && empty($pattern['breakout'])) {
                continue;
            }

            if ($pattern['confidence'] < $minConfidence) {
                continue;
            }

            if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
                if (($pattern['trade_status'] ?? '') !== $statusFilter) {
                    if ($statusFilter === 'breakout' && empty($pattern['breakout'])) {
                        continue;
                    } elseif ($statusFilter !== 'breakout') {
                        continue;
                    }
                }
            }

            if ($minRr !== null && $minRr > 0) {
                $candRr = (float) ($pattern['best_rr'] ?? ($pattern['risk_reward'] ?? 0));
                if ($candRr < $minRr) {
                    continue;
                }
            }

            if ($volOnly && empty($pattern['volume_confirmed']) && empty($pattern['volume_dry_up'])) {
                continue;
            }

            $pattern['symbol'] = $stock->symbol;
            $pattern['stock_name'] = $stock->name;
            $pattern['sector'] = $stock->sector;
            $results[] = $pattern;
        }

        usort($results, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);
        return $results;
    }

    /**
     * Deteksi pola Bullish pada satu saham
     */
    public function detectPattern(
        int $stockId,
        int $timeframe = self::DEFAULT_TIMEFRAME,
        ?int $lookback = null,
        ?string $patternType = null
    ): ?array {
        $lookback ??= self::DEFAULT_LOOKBACK;
        $patternType ??= self::PATTERN_ALL;
        $this->validateOptions($timeframe, $lookback);

        [$candles, $approximated] = $this->loadCandles($stockId, $timeframe, $lookback);
        if (count($candles) < 25) {
            return null;
        }

        $pattern = $this->findPatterns($candles, $patternType);
        if ($pattern !== null) {
            $pattern['stock_id'] = $stockId;
            $pattern['timeframe'] = $timeframe;
            $pattern['approximated'] = $approximated;
            $pattern['data_source'] = ($timeframe !== self::TIMEFRAME_1D && $approximated) ? 'daily_fallback' : ($timeframe === self::TIMEFRAME_1D ? 'daily' : 'intraday');
        }

        return $pattern;
    }

    /**
     * Mengambil candles untuk visualisasi chart
     * @return array{0: array, 1: bool}
     */
    public function getAnalysisCandles(int $stockId, int $timeframe = self::DEFAULT_TIMEFRAME, ?int $lookback = null): array
    {
        $lookback ??= self::DEFAULT_LOOKBACK;
        return $this->loadCandles($stockId, $timeframe, $lookback);
    }

    /**
     * Menjalankan mesin pencari 5 pola kelanjutan & breakout bullish
     */
    private function findPatterns(array $candles, ?string $requestedPattern = null): ?array
    {
        $count = count($candles);
        if ($count < 25) {
            return null;
        }

        $requestedPattern ??= self::PATTERN_ALL;

        // Cari titik-titik pivot swing high & swing low
        $swingHighs = [];
        $swingLows = [];
        for ($i = 2; $i < $count - 2; $i++) {
            if ($candles[$i]['high'] >= $candles[$i-1]['high'] && $candles[$i]['high'] >= $candles[$i-2]['high']
                && $candles[$i]['high'] >= $candles[$i+1]['high'] && $candles[$i]['high'] >= $candles[$i+2]['high']) {
                $swingHighs[] = ['index' => $i, 'price' => (float)$candles[$i]['high'], 'date' => $candles[$i]['date']];
            }
            if ($candles[$i]['low'] <= $candles[$i-1]['low'] && $candles[$i]['low'] <= $candles[$i-2]['low']
                && $candles[$i]['low'] <= $candles[$i+1]['low'] && $candles[$i]['low'] <= $candles[$i+2]['low']) {
                $swingLows[] = ['index' => $i, 'price' => (float)$candles[$i]['low'], 'date' => $candles[$i]['date']];
            }
        }

        $candidates = [];

        // 1. Deteksi Cup and Handle
        if ($requestedPattern === self::PATTERN_ALL || $requestedPattern === self::PATTERN_CUP_AND_HANDLE) {
            $ch = $this->detectCupAndHandle($candles, $swingHighs, $swingLows);
            if ($ch !== null) {
                $candidates[] = $ch;
            }
        }

        // 2. Deteksi Bullish Flag
        if ($requestedPattern === self::PATTERN_ALL || $requestedPattern === self::PATTERN_BULLISH_FLAG) {
            $flag = $this->detectBullishFlag($candles, $swingHighs, $swingLows);
            if ($flag !== null) {
                $candidates[] = $flag;
            }
        }

        // 3. Deteksi Bullish Pennant
        if ($requestedPattern === self::PATTERN_ALL || $requestedPattern === self::PATTERN_BULLISH_PENNANT) {
            $pennant = $this->detectBullishPennant($candles, $swingHighs, $swingLows);
            if ($pennant !== null) {
                $candidates[] = $pennant;
            }
        }

        // 4. Deteksi Bullish Rectangle
        if ($requestedPattern === self::PATTERN_ALL || $requestedPattern === self::PATTERN_BULLISH_RECTANGLE) {
            $rect = $this->detectBullishRectangle($candles, $swingHighs, $swingLows);
            if ($rect !== null) {
                $candidates[] = $rect;
            }
        }

        // 5. Deteksi Ascending Triangle
        if ($requestedPattern === self::PATTERN_ALL || $requestedPattern === self::PATTERN_ASCENDING_TRIANGLE) {
            $triangle = $this->detectAscendingTriangle($candles, $swingHighs, $swingLows);
            if ($triangle !== null) {
                $candidates[] = $triangle;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Pilih kandidat dengan confidence tertinggi
        usort($candidates, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        return $candidates[0];
    }

    /**
     * 1. Deteksi Cup and Handle
     */
    private function detectCupAndHandle(array $candles, array $swingHighs, array $swingLows): ?array
    {
        $count = count($candles);
        $currentClose = (float) end($candles)['close'];

        if (count($swingHighs) < 2) {
            return null;
        }

        $best = null;
        for ($h1 = 0; $h1 < count($swingHighs) - 1; $h1++) {
            $leftRim = $swingHighs[$h1];
            for ($h2 = $h1 + 1; $h2 < count($swingHighs); $h2++) {
                $rightRim = $swingHighs[$h2];
                $span = $rightRim['index'] - $leftRim['index'];
                if ($span < 12 || $span > 60) {
                    continue;
                }

                $rimDiff = abs($leftRim['price'] - $rightRim['price']) / max($leftRim['price'], 0.01);
                if ($rimDiff > 0.06) {
                    continue; // Rim harus mendekati level horizontal (toleransi 6%)
                }

                $rim = max($leftRim['price'], $rightRim['price']);

                // Temukan titik terendah mangkok (Cup Bottom)
                $cupLow = 999999.0;
                $cupLowIdx = $leftRim['index'];
                for ($p = $leftRim['index']; $p <= $rightRim['index']; $p++) {
                    if ($candles[$p]['low'] < $cupLow) {
                        $cupLow = (float) $candles[$p]['low'];
                        $cupLowIdx = $p;
                    }
                }

                $cupDepth = ($rim - $cupLow) / max($rim, 0.01);
                if ($cupDepth < 0.06 || $cupDepth > 0.45) {
                    continue; // Kedalaman mangkok wajar 6% - 45%
                }

                // Posisi bottom harus berada di tengah-tengah rentang
                if ($cupLowIdx <= $leftRim['index'] + 2 || $cupLowIdx >= $rightRim['index'] - 2) {
                    continue;
                }

                // Handle terbentuk setelah Right Rim
                if ($count - 1 <= $rightRim['index']) {
                    continue;
                }

                $handleLow = 999999.0;
                $handleLowIdx = $rightRim['index'];
                for ($p = $rightRim['index'] + 1; $p < $count; $p++) {
                    if ($candles[$p]['low'] < $handleLow) {
                        $handleLow = (float) $candles[$p]['low'];
                        $handleLowIdx = $p;
                    }
                }

                $cupHeight = $rim - $cupLow;
                $handleRetrace = ($rightRim['price'] - $handleLow) / max($cupHeight, 0.01);

                // Handle tidak boleh jatuh lebih dari 50% kedalaman mangkok
                if ($handleRetrace > 0.50 || $handleLow <= $cupLow) {
                    continue;
                }

                // Pastikan harga pasca-handle tidak jebol di bawah handle low
                $breakout = $currentClose > $rim;
                $patternHeight = $cupHeight;

                $targetPrice = round($rim + $patternHeight, 2);
                $tp1 = round($rim + ($patternHeight * 0.5), 2);
                $tp2 = $targetPrice;
                $tp3 = round($rim + ($patternHeight * 1.618), 2);

                $stopLossTight = round(max($handleLow * 0.99, $rim * 0.98), 2);
                $stopLossSwing = round($rim - ($patternHeight * 0.5), 2); // 50% retracement

                $candidate = $this->buildPatternPayload(
                    self::PATTERN_CUP_AND_HANDLE,
                    'Cup and Handle',
                    'bi-cup-hot-fill',
                    $candles,
                    $rim,
                    $handleLow,
                    $patternHeight,
                    $currentClose,
                    $breakout,
                    $stopLossTight,
                    $stopLossSwing,
                    $tp1,
                    $tp2,
                    $tp3,
                    $rightRim['index'],
                    [
                        'left_rim_price' => $leftRim['price'],
                        'left_rim_date' => $leftRim['date'],
                        'right_rim_price' => $rightRim['price'],
                        'right_rim_date' => $rightRim['date'],
                        'cup_low_price' => $cupLow,
                        'cup_low_date' => $candles[$cupLowIdx]['date'],
                        'handle_low_price' => $handleLow,
                        'handle_low_date' => $candles[$handleLowIdx]['date'],
                        'cup_depth_pct' => round($cupDepth * 100, 1),
                        'handle_retrace_pct' => round($handleRetrace * 100, 1),
                        'structure_desc' => 'Mangkok U-Shape kedalaman ' . round($cupDepth * 100, 1) . '%, Gagang (Handle) retrace ' . round($handleRetrace * 100, 1) . '%',
                    ]
                );

                if ($best === null || $candidate['confidence'] > $best['confidence']) {
                    $best = $candidate;
                }
            }
        }

        return $best;
    }

    /**
     * 2. Deteksi Bullish Flag (Tiang Bendera Kuat + Konsolidasi Channel Turun/Datar)
     */
    private function detectBullishFlag(array $candles, array $swingHighs, array $swingLows): ?array
    {
        $count = count($candles);
        $currentClose = (float) end($candles)['close'];

        $best = null;
        for ($peakIdx = $count - 4; $peakIdx >= max(8, $count - 25); $peakIdx--) {
            $peakPrice = (float) $candles[$peakIdx]['high'];
            for ($startIdx = max(0, $peakIdx - 18); $startIdx <= $peakIdx - 3; $startIdx++) {
                $startPrice = (float) $candles[$startIdx]['low'];
                $gain = ($peakPrice - $startPrice) / max($startPrice, 0.01);
                if ($gain < 0.07) {
                    continue; // Tiang bendera (flagpole) minimal rally +7%
                }

                $poleHeight = $peakPrice - $startPrice;
                $consLen = ($count - 1) - $peakIdx;
                if ($consLen < 4 || $consLen > 25) {
                    continue;
                }

                $consCandles = array_slice($candles, $peakIdx);
                $minConsLow = min(array_column($consCandles, 'low'));
                $retrace = ($peakPrice - $minConsLow) / max($poleHeight, 0.01);

                // Bendera tidak boleh retrace lebih dari 50% dari tiang
                if ($retrace > 0.50) {
                    continue;
                }

                // Garis resistance bendera (puncak konsolidasi)
                $flagResistance = $peakPrice;
                $breakout = $currentClose > $flagResistance * 0.995;

                $targetPrice = round($flagResistance + $poleHeight, 2);
                $tp1 = round($flagResistance + ($poleHeight * 0.5), 2);
                $tp2 = $targetPrice;
                $tp3 = round($flagResistance + ($poleHeight * 1.618), 2);

                $stopLossTight = round($minConsLow * 0.99, 2);
                $stopLossSwing = round($startPrice, 2);

                $candidate = $this->buildPatternPayload(
                    self::PATTERN_BULLISH_FLAG,
                    'Bullish Flag',
                    'bi-flag-fill',
                    $candles,
                    $flagResistance,
                    $minConsLow,
                    $poleHeight,
                    $currentClose,
                    $breakout,
                    $stopLossTight,
                    $stopLossSwing,
                    $tp1,
                    $tp2,
                    $tp3,
                    $peakIdx,
                    [
                        'pole_start_price' => $startPrice,
                        'pole_start_date' => $candles[$startIdx]['date'],
                        'pole_peak_price' => $peakPrice,
                        'pole_peak_date' => $candles[$peakIdx]['date'],
                        'flag_low_price' => $minConsLow,
                        'flagpole_gain_pct' => round($gain * 100, 1),
                        'flag_retrace_pct' => round($retrace * 100, 1),
                        'structure_desc' => 'Tiang reli +' . round($gain * 100, 1) . '%, Bendera koreksi wajar ' . round($retrace * 100, 1) . '%',
                    ]
                );

                if ($best === null || $candidate['confidence'] > $best['confidence']) {
                    $best = $candidate;
                }
            }
        }

        return $best;
    }

    /**
     * 3. Deteksi Bullish Pennant (Tiang Bendera + Segitiga Simetris Menguncup)
     */
    private function detectBullishPennant(array $candles, array $swingHighs, array $swingLows): ?array
    {
        $count = count($candles);
        $currentClose = (float) end($candles)['close'];

        $best = null;
        for ($peakIdx = $count - 4; $peakIdx >= max(8, $count - 22); $peakIdx--) {
            $peakPrice = (float) $candles[$peakIdx]['high'];
            for ($startIdx = max(0, $peakIdx - 16); $startIdx <= $peakIdx - 3; $startIdx++) {
                $startPrice = (float) $candles[$startIdx]['low'];
                $gain = ($peakPrice - $startPrice) / max($startPrice, 0.01);
                if ($gain < 0.06) {
                    continue;
                }

                $poleHeight = $peakPrice - $startPrice;
                $cons = array_slice($candles, $peakIdx);
                $consLen = count($cons);
                if ($consLen < 4 || $consLen > 22) {
                    continue;
                }

                $consLows = array_column($cons, 'low');
                $consHighs = array_column($cons, 'high');
                $minConsLow = min($consLows);
                $retrace = ($peakPrice - $minConsLow) / max($poleHeight, 0.01);
                if ($retrace > 0.50) {
                    continue;
                }

                $mid = (int) ($consLen / 2);
                $firstHalfHigh = max(array_slice($consHighs, 0, $mid));
                $secondHalfHigh = max(array_slice($consHighs, $mid));
                $firstHalfLow = min(array_slice($consLows, 0, $mid));
                $secondHalfLow = min(array_slice($consLows, $mid));

                $rangeFirst = $firstHalfHigh - $firstHalfLow;
                $rangeSecond = $secondHalfHigh - $secondHalfLow;

                // Cek kompresi range segitiga simetris (pennant apex)
                if (!($secondHalfHigh < $firstHalfHigh && $secondHalfLow > $firstHalfLow && $rangeSecond < $rangeFirst * 0.90)) {
                    continue;
                }

                $pennantResistance = $secondHalfHigh;
                $breakout = $currentClose > $pennantResistance;

                $targetPrice = round($pennantResistance + $poleHeight, 2);
                $tp1 = round($pennantResistance + ($poleHeight * 0.5), 2);
                $tp2 = $targetPrice;
                $tp3 = round($pennantResistance + ($poleHeight * 1.618), 2);

                $stopLossTight = round($secondHalfLow * 0.99, 2);
                $stopLossSwing = round($minConsLow * 0.98, 2);

                $compressionPct = round((1 - ($rangeSecond / max($rangeFirst, 0.01))) * 100, 1);

                $candidate = $this->buildPatternPayload(
                    self::PATTERN_BULLISH_PENNANT,
                    'Bullish Pennant',
                    'bi-triangle-fill',
                    $candles,
                    $pennantResistance,
                    $secondHalfLow,
                    $poleHeight,
                    $currentClose,
                    $breakout,
                    $stopLossTight,
                    $stopLossSwing,
                    $tp1,
                    $tp2,
                    $tp3,
                    $peakIdx,
                    [
                        'pole_start_price' => $startPrice,
                        'pole_start_date' => $candles[$startIdx]['date'],
                        'pole_peak_price' => $peakPrice,
                        'pole_peak_date' => $candles[$peakIdx]['date'],
                        'pennant_high_price' => $secondHalfHigh,
                        'pennant_low_price' => $secondHalfLow,
                        'flagpole_gain_pct' => round($gain * 100, 1),
                        'compression_pct' => $compressionPct,
                        'structure_desc' => 'Tiang +' . round($gain * 100, 1) . '%, Segitiga menguncup ' . $compressionPct . '% mendekati apex',
                    ]
                );

                if ($best === null || $candidate['confidence'] > $best['confidence']) {
                    $best = $candidate;
                }
            }
        }

        return $best;
    }

    /**
     * 4. Deteksi Bullish Rectangle (Darvas Box / Konsolidasi Kotak Horizontal)
     */
    private function detectBullishRectangle(array $candles, array $swingHighs, array $swingLows): ?array
    {
        $count = count($candles);
        $currentClose = (float) end($candles)['close'];

        $best = null;
        for ($boxLen = 12; $boxLen <= 35; $boxLen++) {
            if ($count < $boxLen + 10) {
                continue;
            }

            $box = array_slice($candles, -$boxLen);
            $boxHighs = array_column($box, 'high');
            $boxLows = array_column($box, 'low');
            $maxH = max($boxHighs);
            $minL = min($boxLows);
            $boxHeight = $maxH - $minL;
            $boxHeightPct = $boxHeight / max($minL, 0.01);

            if ($boxHeightPct < 0.03 || $boxHeightPct > 0.14) {
                continue;
            }

            // Hitung sentuhan batas atas dan bawah
            $upperTouches = 0;
            $lowerTouches = 0;
            foreach ($box as $b) {
                if ($b['high'] >= $maxH * 0.985) $upperTouches++;
                if ($b['low'] <= $minL * 1.015) $lowerTouches++;
            }

            if ($upperTouches < 2 || $lowerTouches < 2) {
                continue;
            }

            // Pastikan ada tren naik sebelum masuk kotak (prior uptrend)
            $preBox = array_slice($candles, -($boxLen + 15), 15);
            if (empty($preBox)) {
                continue;
            }
            $preLow = min(array_column($preBox, 'low'));
            if ($minL < $preLow * 1.03) {
                continue;
            }

            $breakout = $currentClose > $maxH;
            $patternHeight = $boxHeight;

            $targetPrice = round($maxH + $patternHeight, 2);
            $tp1 = round($maxH + ($patternHeight * 0.5), 2);
            $tp2 = $targetPrice;
            $tp3 = round($maxH + ($patternHeight * 1.618), 2);

            $stopLossTight = round($maxH * 0.98, 2);
            $stopLossSwing = round($minL * 0.98, 2);

            $candidate = $this->buildPatternPayload(
                self::PATTERN_BULLISH_RECTANGLE,
                'Bullish Rectangle',
                'bi-square-fill',
                $candles,
                $maxH,
                $minL,
                $patternHeight,
                $currentClose,
                $breakout,
                $stopLossTight,
                $stopLossSwing,
                $tp1,
                $tp2,
                $tp3,
                $count - $boxLen,
                [
                    'box_resistance' => round($maxH, 2),
                    'box_support' => round($minL, 2),
                    'box_height_pct' => round($boxHeightPct * 100, 1),
                    'upper_touches' => $upperTouches,
                    'lower_touches' => $lowerTouches,
                    'box_length' => $boxLen,
                    'structure_desc' => 'Kotak konsolidasi ' . $boxLen . ' bars, Range ' . round($boxHeightPct * 100, 1) . '%, ' . $upperTouches . 'x atap / ' . $lowerTouches . 'x lantai',
                ]
            );

            if ($best === null || $candidate['confidence'] > $best['confidence']) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * 5. Deteksi Ascending Triangle (Atap Datar Horizontal + Support Menanjak Naik)
     */
    private function detectAscendingTriangle(array $candles, array $swingHighs, array $swingLows): ?array
    {
        $count = count($candles);
        $currentClose = (float) end($candles)['close'];

        if (count($swingHighs) < 2 || count($swingLows) < 2) {
            return null;
        }

        $best = null;
        for ($h1 = 0; $h1 < count($swingHighs) - 1; $h1++) {
            for ($h2 = $h1 + 1; $h2 < count($swingHighs); $h2++) {
                $high1 = $swingHighs[$h1];
                $high2 = $swingHighs[$h2];
                $span = $high2['index'] - $high1['index'];
                if ($span < 6 || $span > 50) {
                    continue;
                }

                $ceilingDiff = abs($high1['price'] - $high2['price']) / max($high1['price'], 0.01);
                if ($ceilingDiff > 0.025) {
                    continue; // Atap resistensi harus horizontal rata
                }

                $ceil = max($high1['price'], $high2['price']);

                // Temukan swing lows yang berada dalam rentang
                $relevantLows = array_filter($swingLows, fn($l) => $l['index'] >= $high1['index'] - 2 && $l['index'] <= $high2['index'] + 10);
                $relevantLows = array_values($relevantLows);
                if (count($relevantLows) < 2) {
                    continue;
                }

                $lFirst = $relevantLows[0];
                $lLast = end($relevantLows);

                // Higher Lows: Low akhir harus lebih tinggi daripada Low awal (akumulasi buyer)
                if ($lLast['index'] <= $lFirst['index'] || $lLast['price'] <= $lFirst['price'] * 1.015) {
                    continue;
                }

                if ($lFirst['price'] >= $ceil * 0.985) {
                    continue; // Harus ada ruang tinggi segitiga
                }

                $triangleHeight = $ceil - $lFirst['price'];
                $breakout = $currentClose > $ceil;

                $targetPrice = round($ceil + $triangleHeight, 2);
                $tp1 = round($ceil + ($triangleHeight * 0.5), 2);
                $tp2 = $targetPrice;
                $tp3 = round($ceil + ($triangleHeight * 1.618), 2);

                $stopLossTight = round($ceil * 0.98, 2);
                $stopLossSwing = round($lLast['price'] * 0.985, 2);

                $slopePct = round((($lLast['price'] - $lFirst['price']) / max($lFirst['price'], 0.01)) * 100, 1);

                $candidate = $this->buildPatternPayload(
                    self::PATTERN_ASCENDING_TRIANGLE,
                    'Ascending Triangle',
                    'bi-triangle',
                    $candles,
                    $ceil,
                    $lLast['price'],
                    $triangleHeight,
                    $currentClose,
                    $breakout,
                    $stopLossTight,
                    $stopLossSwing,
                    $tp1,
                    $tp2,
                    $tp3,
                    $high1['index'],
                    [
                        'ceiling_price' => round($ceil, 2),
                        'high1_date' => $high1['date'],
                        'high2_date' => $high2['date'],
                        'low_start_price' => $lFirst['price'],
                        'low_start_date' => $lFirst['date'],
                        'low_recent_price' => $lLast['price'],
                        'low_recent_date' => $lLast['date'],
                        'triangle_slope_pct' => $slopePct,
                        'structure_desc' => 'Atap horizontal $' . number_format($ceil, 2) . ', Support naik +' . $slopePct . '% (Higher Lows)',
                    ]
                );

                if ($best === null || $candidate['confidence'] > $best['confidence']) {
                    $best = $candidate;
                }
            }
        }

        return $best;
    }

    /**
     * Membangun payload lengkap data pola, timing eksekusi, false breakout risk, dan R:R
     */
    private function buildPatternPayload(
        string $patternType,
        string $patternName,
        string $patternIcon,
        array $candles,
        float $breakoutResistance,
        float $supportBase,
        float $patternHeight,
        float $currentClose,
        bool $breakout,
        float $stopLossTight,
        float $stopLossSwing,
        float $tp1,
        float $tp2,
        float $tp3,
        int $patternStartIndex,
        array $anatomy = []
    ): array {
        $count = count($candles);
        $distanceToNeckline = round(($currentClose - $breakoutResistance) / max($breakoutResistance, 0.0001) * 100, 2);

        // Volume analysis
        $vols = array_column($candles, 'volume');
        $avgVol = count($vols) > 0 ? (array_sum($vols) / count($vols)) : 1;
        $currentVol = (int) (end($candles)['volume'] ?? 0);
        $rvol = $avgVol > 0 ? round($currentVol / $avgVol, 2) : 1.0;

        // Analisa Volume Dry Up di konsolidasi
        $recentVols = array_slice($vols, -5);
        $recentAvgVol = count($recentVols) > 0 ? (array_sum($recentVols) / count($recentVols)) : 1;
        $volDryUp = $recentAvgVol < $avgVol * 0.85;
        $volConfirmed = ($breakout && $rvol >= 1.3) || $volDryUp;

        // Status timing eksekusi
        if ($breakout) {
            if ($distanceToNeckline >= 0 && $distanceToNeckline <= 3.0) {
                $tradeStatus = self::STATUS_BUY_ZONE;
                $tradeAction = 'BUY (Golden Breakout Zone)';
                $actionDesc = 'Waktu ideal membeli! Harga baru saja breakout resistance dengan jarak aman (0 - 3%).';
            } elseif ($distanceToNeckline > 3.0 && $distanceToNeckline <= 6.0) {
                $tradeStatus = self::STATUS_EXTENDED;
                $tradeAction = 'HOLD / MODERATE (+3-6% Naik)';
                $actionDesc = 'Harga sudah bergerak cukup jauh dari breakout. Beli bertahap atau tunggu retest.';
            } elseif ($distanceToNeckline > 6.0) {
                $tradeStatus = self::STATUS_OVEREXTENDED;
                $tradeAction = 'WAIT PULLBACK (>6% Extended)';
                $actionDesc = 'JANGAN FOMO BELI DI PUCUK! Tunggu harga pullback kembali ke garis resistance.';
            } else {
                $tradeStatus = self::STATUS_RETEST;
                $tradeAction = 'STRONG BUY (Pullback / Retest Support)';
                $actionDesc = 'Peluang entri probabilitas tertinggi! Harga retest ke resistance (menjadi support baru) dan memantul.';
            }
        } else {
            if ($distanceToNeckline >= -3.0) {
                $tradeStatus = self::STATUS_APPROACHING;
                $tradeAction = 'PRE-BREAKOUT WATCH';
                $actionDesc = 'Harga mendekati batas atas resistance. Pasang Buy Stop Order tepat di atas resistance.';
            } elseif ($currentClose <= $supportBase * 1.03) {
                $tradeStatus = self::STATUS_CONSOLIDATION_BOUNCE;
                $tradeAction = 'AGGRESSIVE BUY (Support Bounce)';
                $actionDesc = 'Peluang beli dini di batas bawah pola dengan Stop Loss rapat di bawah support.';
            } else {
                $tradeStatus = self::STATUS_FORMING;
                $tradeAction = 'FORMING (Sedang Berkembang)';
                $actionDesc = 'Pola sedang berkonsolidasi. Tunggu sinyal penembusan atau pantulan tegas.';
            }
        }

        // False Breakout Risk Engine
        $falseBreakout = $this->evaluateFalseBreakoutRisk(
            $candles,
            $patternStartIndex,
            $breakoutResistance,
            $currentClose,
            $breakout,
            $rvol,
            $distanceToNeckline,
            $stopLossTight
        );

        // Confidence score
        $confidence = $this->calculateConfidence(
            $patternType,
            $breakout,
            $volConfirmed,
            $volDryUp,
            $rvol,
            $falseBreakout['risk_level'],
            $anatomy
        );

        // Risk / Reward Ratio
        $activeSl = in_array($tradeStatus, [self::STATUS_CONSOLIDATION_BOUNCE, self::STATUS_FORMING, self::STATUS_APPROACHING], true) ? $stopLossSwing : $stopLossTight;
        $risk = max($currentClose - $activeSl, 0.01);
        $reward = max($tp2 - $currentClose, 0.0);
        $rrRatio = round($reward / $risk, 2);

        $tightRisk = max($currentClose - $stopLossTight, 0.01);
        $rrTight = round(max($tp2 - $currentClose, 0.0) / $tightRisk, 2);
        $rrSwing = round(max($tp2 - $currentClose, 0.0) / max($currentClose - $stopLossSwing, 0.01), 2);

        return array_merge([
            'pattern_type' => $patternType,
            'pattern_name' => $patternName,
            'pattern_icon' => $patternIcon,
            'current_price' => round($currentClose, 2),
            'resistance_line' => round($breakoutResistance, 2),
            'support_base' => round($supportBase, 2),
            'pattern_height' => round($patternHeight, 2),
            'pattern_height_pct' => round(($patternHeight / max($supportBase, 0.01)) * 100, 2),
            'breakout' => $breakout,
            'distance_resistance_pct' => $distanceToNeckline,
            'target_price' => $tp2,
            'tp1_price' => $tp1,
            'tp2_price' => $tp2,
            'tp3_price' => $tp3,
            'stop_loss_tight' => $stopLossTight,
            'stop_loss_swing' => $stopLossSwing,
            'best_rr' => max($rrTight, $rrRatio),
            'rr_tight' => $rrTight,
            'rr_swing' => $rrSwing,
            'risk_reward' => $rrRatio,
            'entry_zone_min' => round($breakoutResistance, 2),
            'entry_zone_max' => round($breakoutResistance * 1.03, 2),
            'trade_status' => $tradeStatus,
            'trade_action' => $tradeAction,
            'action_description' => $actionDesc,
            'rvol' => $rvol,
            'volume_dry_up' => $volDryUp,
            'volume_confirmed' => $volConfirmed,
            'confidence' => $confidence,
            'false_breakout_risk' => $falseBreakout['risk_level'],
            'false_breakout_score' => $falseBreakout['risk_score'],
            'bull_trap_detected' => $falseBreakout['bull_trap'],
            'rejection_wick' => $falseBreakout['rejection_wick'],
            'upper_wick_pct' => $falseBreakout['upper_wick_pct'],
            'low_volume_breakout' => $falseBreakout['low_volume_breakout'],
            'is_overextended' => $falseBreakout['overextended'],
            'false_breakout_reasons' => $falseBreakout['risk_reasons'],
            'mitigation_strategies' => $falseBreakout['mitigation_strategies'],
        ], $anatomy);
    }

    /**
     * Evaluasi Risiko False Breakout (Bull Trap Protection Engine)
     */
    public function evaluateFalseBreakoutRisk(
        array $candles,
        int $patternStartIndex,
        float $resistance,
        float $currentClose,
        bool $breakout,
        float $rvol,
        float $distanceToResistance,
        float $stopLossTight
    ): array {
        $count = count($candles);
        $highestPostStart = 0.0;
        $hadBreakoutClose = false;

        for ($post = $patternStartIndex; $post < $count; $post++) {
            if ($candles[$post]['high'] > $highestPostStart) {
                $highestPostStart = (float) $candles[$post]['high'];
            }
            if ($candles[$post]['close'] > $resistance) {
                $hadBreakoutClose = true;
            }
        }

        $bullTrap = ($hadBreakoutClose && $currentClose < $resistance);
        $penetrationReversal = (!$hadBreakoutClose && $highestPostStart >= $resistance && $currentClose < $resistance);

        $latestCandle = end($candles);
        $latestRange = max(0.0001, (float) $latestCandle['high'] - (float) $latestCandle['low']);
        $latestUpperWick = (float) $latestCandle['high'] - max((float) $latestCandle['open'], (float) $latestCandle['close']);
        $upperWickRatio = $latestUpperWick / $latestRange;
        $hasRejectionWick = ($upperWickRatio >= 0.40 && (float) $latestCandle['high'] >= $resistance * 0.98);

        $lowVolumeBreakout = ($breakout && $rvol < 1.0);
        $isOverextended = ($breakout && $distanceToResistance > 6.0);

        $riskScore = 10;
        if ($bullTrap) {
            $riskScore += 50;
        } elseif ($penetrationReversal) {
            $riskScore += 30;
        }

        if ($hasRejectionWick) {
            $riskScore += 25;
        }

        if ($lowVolumeBreakout) {
            $riskScore += 25;
        }

        if ($isOverextended) {
            $riskScore += 20;
        }

        if ($breakout && $rvol >= 1.4 && !$hasRejectionWick) {
            $riskScore -= 15;
        }

        if ($breakout && $distanceToResistance >= 0 && $distanceToResistance <= 2.5 && $rvol >= 1.2) {
            $riskScore -= 10;
        }

        $riskScore = (int) min(95, max(5, $riskScore));

        if ($riskScore >= 60 || $bullTrap) {
            $riskLevel = self::RISK_HIGH;
        } elseif ($riskScore >= 35 || $penetrationReversal || $hasRejectionWick || $lowVolumeBreakout) {
            $riskLevel = self::RISK_MEDIUM;
        } else {
            $riskLevel = self::RISK_LOW;
        }

        $riskReasons = [];
        $mitigationTips = [];

        if ($bullTrap) {
            $riskReasons[] = "Bull Trap Terdeteksi: Harga sempat menembus garis resistance ($" . number_format($resistance, 2) . ") namun gagal bertahan dan anjlok kembali di bawahnya.";
            $mitigationTips[] = "JANGAN BELI SEKARANG. Tunggu lilin baru berhasil ditutup kembali di atas resistance sebelum membuka posisi.";
            $mitigationTips[] = "Bagi yang sudah terlanjur beli, pasang Stop Loss disiplin di $" . number_format($stopLossTight, 2) . " untuk memotong kerugian.";
        } elseif ($penetrationReversal) {
            $riskReasons[] = "Rejection Intraday: Harga sempat menusuk di atas resistance ($" . number_format($resistance, 2) . ") namun ditekan turun sebelum penutupan pasar.";
            $mitigationTips[] = "Gunakan strategi Buy Stop Order di atas harga tertinggi spike ($" . number_format($highestPostStart, 2) . ") untuk memastikan penembusan sejati.";
        }

        if ($hasRejectionWick) {
            $riskReasons[] = "Upper Wick Rejection (" . round($upperWickRatio * 100) . "%): Ekor atas panjang mengindikasikan tekanan jual masif di level resistance.";
            $mitigationTips[] = "Tunggu lilin konfirmasi berikutnya menembus harga tertinggi ekor sebelum masuk pasar.";
        }

        if ($lowVolumeBreakout) {
            $riskReasons[] = "Low Volume Breakout (RVOL " . number_format($rvol, 1) . "x): Penembusan terjadi dengan volume di bawah rata-rata (kurang partisipasi institusi).";
            $mitigationTips[] = "Hindari langsung beli agresif. Tunggu fase Retest Resistance dengan volume mengering lalu pantulan kuat.";
        }

        if ($isOverextended) {
            $riskReasons[] = "Overextended (+" . number_format($distanceToResistance, 1) . "%): Harga telah bergerak terlalu jauh tanpa istirahat.";
            $mitigationTips[] = "Hindari FOMO beli di pucuk. Tunggu harga pullback kembali ke area Golden Buy Zone ($" . number_format($resistance, 2) . " - $" . number_format($resistance * 1.03, 2) . ").";
        }

        if (empty($riskReasons)) {
            $riskReasons[] = "Breakout Sehat & Solid: Penembusan didukung struktur harga rapi dan tanpa tekanan penolakan berbahaya.";
            $mitigationTips[] = "Pasang Stop Loss disiplin di $" . number_format($stopLossTight, 2) . " dan jalankan trailing stop bertahap saat mencapai TP1.";
        }

        return [
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'bull_trap' => $bullTrap,
            'penetration_reversal' => $penetrationReversal,
            'rejection_wick' => $hasRejectionWick,
            'upper_wick_pct' => round($upperWickRatio * 100, 1),
            'low_volume_breakout' => $lowVolumeBreakout,
            'overextended' => $isOverextended,
            'risk_reasons' => $riskReasons,
            'mitigation_strategies' => $mitigationTips,
        ];
    }

    /**
     * Hitung Skor Keyakinan (Confidence Score 0 - 100%)
     */
    private function calculateConfidence(
        string $patternType,
        bool $breakout,
        bool $volConfirmed,
        bool $volDryUp,
        float $rvol,
        string $riskLevel,
        array $anatomy
    ): int {
        $score = 50; // Base score untuk pola yang terdeteksi

        if ($breakout) {
            $score += 15;
        }

        if ($volDryUp) {
            $score += 10;
        }

        if ($volConfirmed) {
            $score += 10;
        } elseif ($rvol >= 1.3) {
            $score += 8;
        }

        // Bonus spesifik pola
        switch ($patternType) {
            case self::PATTERN_CUP_AND_HANDLE:
                if (isset($anatomy['handle_retrace_pct']) && $anatomy['handle_retrace_pct'] <= 38.2) {
                    $score += 10; // Handle dangkal sangat bullish
                }
                break;
            case self::PATTERN_BULLISH_FLAG:
            case self::PATTERN_BULLISH_PENNANT:
                if (isset($anatomy['flagpole_gain_pct']) && $anatomy['flagpole_gain_pct'] >= 12.0) {
                    $score += 10; // Tiang reli sangat kuat
                }
                break;
            case self::PATTERN_BULLISH_RECTANGLE:
                if (isset($anatomy['upper_touches']) && $anatomy['upper_touches'] >= 3) {
                    $score += 8; // Banyak pengujian resistance
                }
                break;
            case self::PATTERN_ASCENDING_TRIANGLE:
                if (isset($anatomy['triangle_slope_pct']) && $anatomy['triangle_slope_pct'] >= 4.0) {
                    $score += 8;
                }
                break;
        }

        if ($riskLevel === self::RISK_HIGH) {
            $score -= 15;
        } elseif ($riskLevel === self::RISK_MEDIUM) {
            $score -= 5;
        }

        return (int) min(100, max(25, $score));
    }

    /**
     * Muat candle harga harian atau intraday
     * @return array{0: array, 1: bool}
     */
    private function loadCandles(int $stockId, int $timeframe, int $lookback): array
    {
        if ($timeframe === self::TIMEFRAME_1D) {
            return [$this->dailyCandles($stockId, $lookback), false];
        }

        try {
            $rows = IntradayPrice::find()
                ->where(['stock_id' => $stockId, 'timeframe' => IntradayPrice::TF_1H])
                ->orderBy(['datetime' => SORT_DESC])
                ->limit(max($lookback * 4, 80))
                ->all();
        } catch (\Throwable $e) {
            \Yii::warning('Intraday unavailable (DB scan ' . $stockId . '): ' . $e->getMessage(), 'app\services\bullishpattern');
            return [$this->dailyCandles($stockId, $lookback), true];
        }

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

        if (count($base) >= self::MIN_INTRADAY_BARS) {
            $per = $timeframe === self::TIMEFRAME_2H ? 2 : ($timeframe === self::TIMEFRAME_4H ? 4 : 1);
            return [array_slice($this->aggregate($base, $per), -$lookback), false];
        }

        return [$this->dailyCandles($stockId, $lookback), true];
    }

    private function dailyCandles(int $stockId, int $lookback): array
    {
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

    private function aggregate(array $bars, int $group): array
    {
        if ($group <= 1) {
            return $bars;
        }
        $out = [];
        $chunk = [];
        foreach ($bars as $b) {
            $chunk[] = $b;
            if (count($chunk) === $group) {
                $out[] = $this->mergeBarChunk($chunk);
                $chunk = [];
            }
        }
        if ($chunk !== []) {
            $out[] = $this->mergeBarChunk($chunk);
        }
        return $out;
    }

    private function mergeBarChunk(array $chunk): array
    {
        $first = $chunk[0];
        $last = end($chunk);
        $high = max(array_column($chunk, 'high'));
        $low = min(array_column($chunk, 'low'));
        $volume = (int) array_sum(array_column($chunk, 'volume'));
        return [
            'open' => (float) $first['open'],
            'high' => (float) $high,
            'low' => (float) $low,
            'close' => (float) $last['close'],
            'volume' => $volume,
            'date' => (string) $last['date'],
        ];
    }

    public static function getTimeframes(): array
    {
        return [
            self::TIMEFRAME_1D => '1 Day (Daily)',
            self::TIMEFRAME_4H => '4 Hours (Swing)',
            self::TIMEFRAME_2H => '2 Hours',
            self::TIMEFRAME_1H => '1 Hour (Intraday)',
        ];
    }

    public static function getTimeframeLabel(int $tf): string
    {
        return self::getTimeframes()[$tf] ?? "{$tf}m";
    }

    public static function getPatternTypes(): array
    {
        return [
            self::PATTERN_ALL => 'Semua 5 Pola Bullish',
            self::PATTERN_CUP_AND_HANDLE => '☕ Cup and Handle',
            self::PATTERN_BULLISH_RECTANGLE => '📦 Bullish Rectangle (Darvas Box)',
            self::PATTERN_BULLISH_PENNANT => '🔺 Bullish Pennant',
            self::PATTERN_BULLISH_FLAG => '🚩 Bullish Flag',
            self::PATTERN_ASCENDING_TRIANGLE => '📐 Ascending Triangle',
        ];
    }

    public static function getStatusOptions(): array
    {
        return [
            'all' => 'Semua Status Sinyal',
            self::STATUS_BUY_ZONE => '🟢 In Breakout Buy Zone (0-3%)',
            self::STATUS_RETEST => '🔄 Retest Support (Peluang Beli Reversal)',
            self::STATUS_CONSOLIDATION_BOUNCE => '⚡ Support Bounce (Beli Awal di Lantai Pola)',
            'breakout' => '🚀 Terkonfirmasi Breakout',
            self::STATUS_APPROACHING => '👀 Mendekati Garis Resistance',
        ];
    }

    public static function getFalseBreakoutFilters(): array
    {
        return [
            self::RISK_ALL => 'Semua Tingkat Risiko',
            self::RISK_LOW => '🟢 Risiko Rendah (Aman & Solid)',
            self::RISK_MEDIUM => '🟡 Risiko Sedang (Butuh Konfirmasi)',
            self::RISK_HIGH => '🔴 Risiko Tinggi (Waspada)',
            self::RISK_BULL_TRAP => '⚠️ Terdeteksi Bull Trap / Gagal Breakout',
        ];
    }

    public static function getPatternBadge(string $type): array
    {
        return match ($type) {
            self::PATTERN_CUP_AND_HANDLE => ['label' => 'Cup & Handle', 'color' => '#fd7e14', 'class' => 'text-white', 'icon' => 'bi-cup-hot-fill'],
            self::PATTERN_BULLISH_RECTANGLE => ['label' => 'Bullish Rectangle', 'color' => '#20c997', 'class' => 'text-white', 'icon' => 'bi-square-fill'],
            self::PATTERN_BULLISH_PENNANT => ['label' => 'Bullish Pennant', 'color' => '#0dcaf0', 'class' => 'text-dark', 'icon' => 'bi-triangle-fill'],
            self::PATTERN_BULLISH_FLAG => ['label' => 'Bullish Flag', 'color' => '#198754', 'class' => 'text-white', 'icon' => 'bi-flag-fill'],
            self::PATTERN_ASCENDING_TRIANGLE => ['label' => 'Ascending Triangle', 'color' => '#6f42c1', 'class' => 'text-white', 'icon' => 'bi-triangle'],
            default => ['label' => 'Bullish Pattern', 'color' => '#0d6efd', 'class' => 'text-white', 'icon' => 'bi-graph-up-arrow'],
        };
    }

    private function validateOptions(int $timeframe, int $lookback): void
    {
        if (!array_key_exists($timeframe, self::getTimeframes())) {
            throw new \InvalidArgumentException("Timeframe tidak valid: {$timeframe}");
        }
        if ($lookback < 15 || $lookback > 500) {
            throw new \InvalidArgumentException("Lookback harus antara 15 dan 500 candle");
        }
    }
}
