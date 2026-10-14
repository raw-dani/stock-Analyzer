<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;

/**
 * Service untuk deteksi pola Triple Bottom (3 lembah / W-W / reverse head-and-shoulders variant)
 * dan kalkulasi waktu/titik terbaik untuk Beli (Buy) dan Jual (Sell / Take Profit / Cut Loss).
 */
final class TripleBottomService
{
    public const TIMEFRAME_1D = 0;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_1H = 1;

    public const DEFAULT_TIMEFRAME = self::TIMEFRAME_1D;
    public const DEFAULT_LOOKBACK = 120;
    public const DEFAULT_TOLERANCE = 0.03; // 3% variasi antara 3 lembah
    public const DEFAULT_MIN_SEPARATION = 5; // minimal jarak bar antara low
    public const NECKLINE_MIN_DEPTH = 0.03; // minimal kedalaman neckline (3% di atas rata-rata bottom)
    public const STOP_LOSS_BUFFER = 0.02; // buffer 2% di bawah level support
    private const MIN_INTRADAY_BARS = 30;

    // Pattern Types
    public const PATTERN_ALL = 'ALL';
    public const PATTERN_TRIPLE_BOTTOM = 'TRIPLE_BOTTOM';
    public const PATTERN_INVERSE_HS = 'INVERSE_HEAD_AND_SHOULDERS';

    // False Breakout Risk Levels
    public const RISK_ALL = 'ALL';
    public const RISK_LOW = 'LOW';
    public const RISK_MEDIUM = 'MEDIUM';
    public const RISK_HIGH = 'HIGH';
    public const RISK_BULL_TRAP = 'BULL_TRAP';

    /**
     * Scan seluruh saham aktif untuk mendeteksi pola Triple Bottom dan Inverted Head & Shoulders
     * Mendukung pemanggilan positional maupun array options untuk kompatibilitas penuh.
     */
    public function scanAll(
        int|array $timeframe = self::DEFAULT_TIMEFRAME,
        ?int $lookback = null,
        ?float $tolerance = null,
        int $minSeparation = self::DEFAULT_MIN_SEPARATION,
        ?int $maxSeparation = null,
        bool $breakoutOnly = false,
        int $minConfidence = 0,
        ?float $necklineMinDepth = null,
        ?string $statusFilter = null,
        ?float $minRr = null,
        bool $volOnly = false,
        ?string $patternType = null,
        ?string $falseBreakoutRisk = null
    ): array {
        $singleSymbol = null;
        if (is_array($timeframe)) {
            $opts = $timeframe;
            $timeframe = (int) ($opts['timeframe'] ?? self::DEFAULT_TIMEFRAME);
            $lookback = isset($opts['lookback']) ? (int) $opts['lookback'] : $lookback;
            $tolerance = isset($opts['tolerance']) ? (float) $opts['tolerance'] : $tolerance;
            $minSeparation = isset($opts['minSeparation']) ? (int) $opts['minSeparation'] : $minSeparation;
            $maxSeparation = isset($opts['maxSeparation']) ? (int) $opts['maxSeparation'] : $maxSeparation;
            $breakoutOnly = isset($opts['breakoutOnly']) ? (bool) $opts['breakoutOnly'] : $breakoutOnly;
            $minConfidence = isset($opts['minConfidence']) ? (int) $opts['minConfidence'] : $minConfidence;
            $necklineMinDepth = isset($opts['necklineMinDepth']) ? (float) $opts['necklineMinDepth'] : $necklineMinDepth;
            $statusFilter = isset($opts['statusFilter']) ? (string) $opts['statusFilter'] : $statusFilter;
            $minRr = isset($opts['minRr']) ? (float) $opts['minRr'] : $minRr;
            $volOnly = isset($opts['volOnly']) ? (bool) $opts['volOnly'] : $volOnly;
            $patternType = isset($opts['patternType']) ? (string) $opts['patternType'] : $patternType;
            $falseBreakoutRisk = isset($opts['falseBreakoutRisk']) ? (string) $opts['falseBreakoutRisk'] : $falseBreakoutRisk;
            $singleSymbol = $opts['symbol'] ?? null;
        }

        $lookback ??= self::DEFAULT_LOOKBACK;
        $tolerance ??= self::DEFAULT_TOLERANCE;
        $necklineMinDepth ??= self::NECKLINE_MIN_DEPTH;
        $patternType ??= self::PATTERN_ALL;
        $falseBreakoutRisk ??= self::RISK_ALL;

        $this->validateOptions($timeframe, $lookback, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);
        $maxSeparation ??= (int) floor($lookback / 2);

        $results = [];
        $query = Stock::find()->where(['active' => true]);
        if ($singleSymbol !== null && $singleSymbol !== '') {
            $query->andWhere(['symbol' => $singleSymbol]);
        }
        $stocks = $query->all();

        \Yii::info("Bottom Patterns Scan started: " . count($stocks) . " stocks, timeframe={$timeframe}, lookback={$lookback}, patternType={$patternType}", 'app\services\triplebottom');

        foreach ($stocks as $stock) {
            $pattern = $this->detectPattern($stock->id, $timeframe, $lookback, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth, $patternType);
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
            if ($breakoutOnly && !$pattern['breakout']) {
                continue;
            }
            if ($pattern['confidence'] < $minConfidence) {
                continue;
            }
            if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
                if ($statusFilter === 'buy_zone' && ($pattern['trade_status'] ?? '') !== 'buy_zone') {
                    continue;
                }
                if ($statusFilter === 'retest' && ($pattern['trade_status'] ?? '') !== 'retest') {
                    continue;
                }
                if ($statusFilter === 'bottom3_bounce' && ($pattern['trade_status'] ?? '') !== 'bottom3_bounce') {
                    continue;
                }
                if ($statusFilter === 'approaching' && ($pattern['trade_status'] ?? '') !== 'approaching') {
                    continue;
                }
                if ($statusFilter === 'breakout' && !$pattern['breakout']) {
                    continue;
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
     * Deteksi pola Triple Bottom atau Inverted Head & Shoulders pada satu saham
     */
    public function detectPattern(
        int $stockId,
        int $timeframe = self::DEFAULT_TIMEFRAME,
        ?int $lookback = null,
        ?float $tolerance = null,
        int $minSeparation = self::DEFAULT_MIN_SEPARATION,
        ?int $maxSeparation = null,
        ?float $necklineMinDepth = null,
        ?string $patternType = null
    ): ?array {
        $lookback ??= self::DEFAULT_LOOKBACK;
        $tolerance ??= self::DEFAULT_TOLERANCE;
        $necklineMinDepth ??= self::NECKLINE_MIN_DEPTH;
        $patternType ??= self::PATTERN_ALL;
        $this->validateOptions($timeframe, $lookback, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);
        $maxSeparation ??= (int) floor($lookback / 2);

        [$candles, $approximated] = $this->loadCandles($stockId, $timeframe, $lookback);
        if (count($candles) < 15) {
            return null;
        }

        $pattern = $this->findTripleBottom(
            $candles,
            $tolerance,
            $minSeparation,
            $maxSeparation,
            $necklineMinDepth,
            $patternType
        );

        if ($pattern !== null) {
            $pattern['stock_id'] = $stockId;
            $pattern['timeframe'] = $timeframe;
            $pattern['approximated'] = $approximated;
            $pattern['data_source'] = ($timeframe !== self::TIMEFRAME_1D && $approximated) ? 'daily_fallback' : ($timeframe === self::TIMEFRAME_1D ? 'daily' : 'intraday');
        }

        return $pattern;
    }

    /**
     * Mengambil candles untuk keperluan visualisasi chart
     * @return array{0: array, 1: bool}
     */
    public function getAnalysisCandles(int $stockId, int $timeframe = self::DEFAULT_TIMEFRAME, ?int $lookback = null): array
    {
        $lookback ??= self::DEFAULT_LOOKBACK;
        return $this->loadCandles($stockId, $timeframe, $lookback);
    }

    /**
     * Algoritma deteksi 3 Lembah: Triple Bottom & Inverted Head and Shoulders (Bahu-Kepala-Bahu Terbalik)
     * Meliputi penanganan risiko False Breakout (Bull Trap), multi-target TP, dan Stop Loss adaptif.
     */
    private function findTripleBottom(
        array $candles,
        float $tolerance,
        int $minSeparation,
        int $maxSeparation,
        float $necklineMinDepth = self::NECKLINE_MIN_DEPTH,
        ?string $requestedPatternType = null
    ): ?array {
        $count = count($candles);
        if ($count < 15) {
            return null;
        }

        $requestedPatternType ??= self::PATTERN_ALL;

        // Cari semua titik lembah lokal (local minima) dengan filter pivot (window 1 bar kiri & kanan)
        $lows = [];
        for ($i = 1; $i < $count - 1; $i++) {
            if ($candles[$i]['low'] <= $candles[$i - 1]['low'] && $candles[$i]['low'] <= $candles[$i + 1]['low']) {
                $lows[] = [
                    'index' => $i,
                    'price' => (float) $candles[$i]['low'],
                    'date' => $candles[$i]['date'],
                ];
            }
        }

        $best = null;
        $lc = count($lows);

        // Cari kombinasi triplet: Low 1, Low 2, Low 3
        for ($i = 0; $i < $lc - 2; $i++) {
            for ($j = $i + 1; $j < $lc - 1; $j++) {
                $low1 = $lows[$i];
                $low2 = $lows[$j];
                $sep12 = $low2['index'] - $low1['index'];
                if ($sep12 < $minSeparation || $sep12 > $maxSeparation) {
                    continue;
                }

                for ($k = $j + 1; $k < $lc; $k++) {
                    $low3 = $lows[$k];
                    $sep23 = $low3['index'] - $low2['index'];
                    if ($sep23 < $minSeparation || $sep23 > $maxSeparation) {
                        continue;
                    }

                    $avgLow = ($low1['price'] + $low2['price'] + $low3['price']) / 3;
                    if ($avgLow <= 0) {
                        continue;
                    }

                    // 1. Evaluasi Pola Triple Bottom (3 Lembah Horizontal Setara)
                    $diff1 = abs($low1['price'] - $avgLow) / $avgLow;
                    $diff2 = abs($low2['price'] - $avgLow) / $avgLow;
                    $diff3 = abs($low3['price'] - $avgLow) / $avgLow;
                    $maxDiff = max($diff1, $diff2, $diff3);
                    $isTripleBottom = ($maxDiff <= $tolerance);

                    // 2. Evaluasi Pola Inverted Head and Shoulders (Bahu Kiri, Kepala Lebih Rendah, Bahu Kanan)
                    $headDropLeft = ($low1['price'] - $low2['price']) / max($low1['price'], 0.01);
                    $headDropRight = ($low3['price'] - $low2['price']) / max($low3['price'], 0.01);
                    $avgShoulder = ($low1['price'] + $low3['price']) / 2;
                    $shoulderDiff = abs($low1['price'] - $low3['price']) / max($avgShoulder, 0.01);
                    $shoulderTolerance = max($tolerance * 2.0, 0.08); // Toleransi simetri bahu 6-8%

                    $isInverseHs = (
                        $low2['price'] < $low1['price'] &&
                        $low2['price'] < $low3['price'] &&
                        $headDropLeft >= 0.025 &&
                        $headDropRight >= 0.025 &&
                        $headDropLeft <= 0.40 &&
                        $headDropRight <= 0.40 &&
                        $shoulderDiff <= $shoulderTolerance
                    );

                    // Filter berdasarkan tipe pola yang diminta
                    if ($requestedPatternType === self::PATTERN_TRIPLE_BOTTOM && !$isTripleBottom) {
                        continue;
                    }
                    if ($requestedPatternType === self::PATTERN_INVERSE_HS && !$isInverseHs) {
                        continue;
                    }
                    if (!$isTripleBottom && !$isInverseHs) {
                        continue;
                    }

                    // Klasifikasikan tipe pola yang terdeteksi
                    if ($isInverseHs && !$isTripleBottom) {
                        $patternType = self::PATTERN_INVERSE_HS;
                    } elseif ($isTripleBottom && !$isInverseHs) {
                        $patternType = self::PATTERN_TRIPLE_BOTTOM;
                    } else {
                        // Jika memenuhi keduanya, utamakan IHS jika kepala jelas lebih dalam (>= 3.5%)
                        $patternType = ($headDropLeft >= 0.035 && $headDropRight >= 0.035)
                            ? self::PATTERN_INVERSE_HS
                            : self::PATTERN_TRIPLE_BOTTOM;
                    }

                    // Cari 2 puncak perantara (Peak 1 antara L1 & L2, Peak 2 antara L2 & L3)
                    $peak1 = 0.0;
                    $peak1Date = '';
                    $peak1Idx = $low1['index'];
                    for ($p = $low1['index']; $p <= $low2['index']; $p++) {
                        if ($candles[$p]['high'] > $peak1) {
                            $peak1 = (float) $candles[$p]['high'];
                            $peak1Date = $candles[$p]['date'];
                            $peak1Idx = $p;
                        }
                    }

                    $peak2 = 0.0;
                    $peak2Date = '';
                    $peak2Idx = $low2['index'];
                    for ($p = $low2['index']; $p <= $low3['index']; $p++) {
                        if ($candles[$p]['high'] > $peak2) {
                            $peak2 = (float) $candles[$p]['high'];
                            $peak2Date = $candles[$p]['date'];
                            $peak2Idx = $p;
                        }
                    }

                    if ($peak1 <= 0 || $peak2 <= 0) {
                        continue;
                    }

                    // Neckline dihitung sebagai level tertinggi / rata-rata kedua peak
                    $neckline = max($peak1, $peak2);
                    $necklineAvg = ($peak1 + $peak2) / 2;

                    // Validasi kedalaman pola: Neckline harus cukup tinggi di atas support bottom
                    $supportBaseline = ($patternType === self::PATTERN_INVERSE_HS) ? $low2['price'] : $avgLow;
                    $depth = ($neckline - $supportBaseline) / max($supportBaseline, 0.01);
                    if ($depth < $necklineMinDepth) {
                        continue;
                    }

                    // Pastikan harga setelah Low 3 tidak jebol di bawah support dasar
                    $minSupport = min($low1['price'], $low2['price'], $low3['price']);
                    $invalSupport = ($patternType === self::PATTERN_INVERSE_HS)
                        ? $low2['price'] * (1 - self::STOP_LOSS_BUFFER)
                        : $minSupport * (1 - self::STOP_LOSS_BUFFER);

                    $broken = false;
                    for ($post = $low3['index'] + 1; $post < $count; $post++) {
                        if ($candles[$post]['close'] < $invalSupport) {
                            $broken = true;
                            break;
                        }
                    }
                    if ($broken) {
                        continue;
                    }

                    $currentClose = (float) end($candles)['close'];
                    $breakout = $currentClose > $neckline;

                    // Measured Move Height & Target Take Profit (TP)
                    // Pada Inverse H&S: tinggi diukur dari Neckline ke Head ($low2)
                    // Pada Triple Bottom: tinggi diukur dari Neckline ke rata-rata bottom
                    $patternHeight = ($patternType === self::PATTERN_INVERSE_HS) ? ($neckline - $low2['price']) : ($neckline - $avgLow);
                    $targetPrice = round($neckline + $patternHeight, 2); // 100% Measured move
                    $tp1 = round($neckline + ($patternHeight * 0.5), 2); // 50% Measured move
                    $tp2 = $targetPrice;
                    $tp3 = round($neckline + ($patternHeight * 1.618), 2); // Fib extension 161.8%

                    // Level Stop Loss (SL)
                    $stopLossTight = round($neckline * 0.98, 2); // 2% di bawah neckline (breakout buyers)
                    $stopLossHead = round($low2['price'] * (1 - self::STOP_LOSS_BUFFER), 2);
                    $stopLossRightShoulder = round($low3['price'] * (1 - self::STOP_LOSS_BUFFER), 2);
                    $stopLossSwing = ($patternType === self::PATTERN_INVERSE_HS)
                        ? $stopLossRightShoulder
                        : round($minSupport * (1 - self::STOP_LOSS_BUFFER), 2);

                    // Jarak harga saat ini ke Neckline (%)
                    $distanceToNeckline = round(($currentClose - $neckline) / max($neckline, 0.0001) * 100, 2);

                    // Analisa Volume
                    $low1Vol = (int) ($candles[$low1['index']]['volume'] ?? 0);
                    $low2Vol = (int) ($candles[$low2['index']]['volume'] ?? 0);
                    $low3Vol = (int) ($candles[$low3['index']]['volume'] ?? 0);

                    // Pola bottom ideal: Volume mengering di Low 2 / Low 3 (penjual habis)
                    $volDryUp = ($low3Vol < $low1Vol * 0.85) || ($low2Vol < $low1Vol * 0.9);

                    $vols = array_column($candles, 'volume');
                    $avgVol = count($vols) > 0 ? (array_sum($vols) / count($vols)) : 1;
                    $currentVol = (int) (end($candles)['volume'] ?? 0);
                    $rvol = $avgVol > 0 ? round($currentVol / $avgVol, 2) : 1.0;
                    $volConfirmed = ($breakout && $rvol >= 1.3) || $volDryUp;

                    // Evaluasi Sinyal Trading & Waktu Terbaik (Execution Directives)
                    if ($breakout) {
                        if ($distanceToNeckline >= 0 && $distanceToNeckline <= 3.0) {
                            $tradeStatus = 'buy_zone';
                            $tradeAction = 'BUY (Golden Breakout Zone)';
                            $actionDesc = 'Waktu ideal membeli! Harga baru saja breakout neckline dengan jarak aman (0 - 3%).';
                        } elseif ($distanceToNeckline > 3.0 && $distanceToNeckline <= 6.0) {
                            $tradeStatus = 'extended';
                            $tradeAction = 'HOLD / MODERATE (Sudah Naik +3-6%)';
                            $actionDesc = 'Harga sudah bergerak cukup jauh dari breakout. Hindari FOMO, beli bertahap atau tunggu retest.';
                        } elseif ($distanceToNeckline > 6.0) {
                            $tradeStatus = 'overextended';
                            $tradeAction = 'WAIT PULLBACK (>6% Extended)';
                            $actionDesc = 'JANGAN BELI SEKARANG! Rasio risiko tidak sehat. Tunggu harga pullback ke neckline atau amankan TP.';
                        } else {
                            $tradeStatus = 'retest';
                            $tradeAction = 'STRONG BUY (Pullback / Retest Neckline)';
                            $actionDesc = 'Titik entri probabilitas tertinggi! Harga retest ke neckline dan memantul kembali.';
                        }
                    } else {
                        // Belum breakout
                        $distFromSupport = ($currentClose - $minSupport) / max($minSupport, 0.0001) * 100;
                        if ($distanceToNeckline >= -3.5) {
                            $tradeStatus = 'approaching';
                            $tradeAction = 'PRE-BREAKOUT WATCH';
                            $actionDesc = 'Harga mendekati garis Neckline resistance. Pasang Buy Stop Order tepat di atas neckline.';
                        } elseif ($distFromSupport <= 3.5 && $low3['index'] >= $count - 8) {
                            $tradeStatus = 'bottom3_bounce';
                            $tradeAction = 'AGGRESSIVE BUY (Bottom 3 Bounce)';
                            $actionDesc = 'Peluang beli dini di lembah ke-3 dengan Stop Loss sangat rapat di bawah bottom.';
                        } else {
                            $tradeStatus = 'forming';
                            $tradeAction = 'FORMING (Menunggu Konfirmasi)';
                            $actionDesc = 'Pola sedang berkembang. Tunggu pantulan solid atau penembusan neckline.';
                        }
                    }

                    // Evaluasi False Breakout Risk (Bull Trap Engine)
                    $falseBreakout = $this->evaluateFalseBreakoutRisk(
                        $candles,
                        $low3['index'],
                        $neckline,
                        $currentClose,
                        $breakout,
                        $rvol,
                        $distanceToNeckline,
                        $stopLossTight
                    );

                    // Hitung skor confidence pola
                    $confidence = $this->calculateConfidence(
                        $patternType,
                        $low1,
                        $low2,
                        $low3,
                        $peak1,
                        $peak2,
                        $neckline,
                        $currentClose,
                        $breakout,
                        $maxDiff,
                        $headDropLeft,
                        $headDropRight,
                        $shoulderDiff,
                        $candles,
                        $falseBreakout['risk_level']
                    );

                    // Kalkulasi Risk / Reward Ratio (R:R)
                    $activeSl = in_array($tradeStatus, ['bottom3_bounce', 'forming', 'approaching'], true) ? $stopLossSwing : $stopLossTight;
                    $risk = max($currentClose - $activeSl, 0.01);
                    $reward = max($targetPrice - $currentClose, 0.0);
                    $rrRatio = round($reward / $risk, 2);

                    $tightRisk = max($currentClose - $stopLossTight, 0.01);
                    $rrTight = round(max($tp2 - $currentClose, 0.0) / $tightRisk, 2);

                    $swingRisk = max($currentClose - $stopLossSwing, 0.01);
                    $rrSwing = round(max($tp2 - $currentClose, 0.0) / $swingRisk, 2);

                    $candidate = [
                        'pattern_type' => $patternType,
                        'pattern_name' => $patternType === self::PATTERN_INVERSE_HS ? 'Inverted Head & Shoulders' : 'Triple Bottom',
                        'low1_price' => $low1['price'],
                        'low1_date' => $low1['date'],
                        'low1_index' => $low1['index'],
                        'low2_price' => $low2['price'],
                        'low2_date' => $low2['date'],
                        'low2_index' => $low2['index'],
                        'low3_price' => $low3['price'],
                        'low3_date' => $low3['date'],
                        'low3_index' => $low3['index'],
                        'trough1_price' => $low1['price'], // alias untuk PortfolioAveragingService
                        'left_shoulder_price' => $low1['price'],
                        'head_price' => $low2['price'],
                        'right_shoulder_price' => $low3['price'],
                        'head_drop_left_pct' => round($headDropLeft * 100, 1),
                        'head_drop_right_pct' => round($headDropRight * 100, 1),
                        'shoulder_diff_pct' => round($shoulderDiff * 100, 1),
                        'peak1_price' => $peak1,
                        'peak1_date' => $peak1Date,
                        'peak2_price' => $peak2,
                        'peak2_date' => $peak2Date,
                        'avg_bottom' => round($avgLow, 2),
                        'min_support' => round($minSupport, 2),
                        'neckline' => round($neckline, 2),
                        'neckline_avg' => round($necklineAvg, 2),
                        'current_price' => round($currentClose, 2),
                        'breakout' => $breakout,
                        'confidence' => $confidence,
                        'target_price' => $targetPrice,
                        'tp1_price' => $tp1,
                        'tp2_price' => $tp2,
                        'tp3_price' => $tp3,
                        'stop_loss' => $stopLossSwing,
                        'stop_loss_tight' => $stopLossTight,
                        'stop_loss_head' => $stopLossHead,
                        'stop_loss_right_shoulder' => ($patternType === self::PATTERN_INVERSE_HS ? $stopLossRightShoulder : null),
                        'risk_reward' => $rrRatio,
                        'rr_tight' => $rrTight,
                        'rr_swing' => $rrSwing,
                        'best_rr' => max($rrTight, $rrRatio),
                        'distance_neckline_pct' => $distanceToNeckline,
                        'pattern_height' => round($patternHeight, 2),
                        'pattern_height_pct' => round(($patternHeight / max($supportBaseline, 0.01)) * 100, 2),
                        'entry_zone_min' => round($neckline, 2),
                        'entry_zone_max' => round($neckline * 1.03, 2),
                        'trade_status' => $tradeStatus,
                        'trade_action' => $tradeAction,
                        'action_description' => $actionDesc,
                        'rvol' => $rvol,
                        'volume_dry_up' => $volDryUp,
                        'volume_confirmed' => $volConfirmed,
                        'total_span' => $low3['index'] - $low1['index'],
                        'span_12' => $sep12,
                        'span_23' => $sep23,
                        // False Breakout Risk Engine data:
                        'false_breakout_risk' => $falseBreakout['risk_level'],
                        'false_breakout_score' => $falseBreakout['risk_score'],
                        'bull_trap_detected' => $falseBreakout['bull_trap'],
                        'penetration_reversal' => $falseBreakout['penetration_reversal'],
                        'rejection_wick' => $falseBreakout['rejection_wick'],
                        'upper_wick_pct' => $falseBreakout['upper_wick_pct'],
                        'low_volume_breakout' => $falseBreakout['low_volume_breakout'],
                        'is_overextended' => $falseBreakout['overextended'],
                        'false_breakout_reasons' => $falseBreakout['risk_reasons'],
                        'mitigation_strategies' => $falseBreakout['mitigation_strategies'],
                    ];

                    if ($best === null || $candidate['confidence'] > $best['confidence']) {
                        $best = $candidate;
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Evaluasi Risiko False Breakout (Bull Trap / Gagal Breakout) & Rekomendasi Mitigasi
     */
    public function evaluateFalseBreakoutRisk(
        array $candles,
        int $low3Index,
        float $neckline,
        float $currentClose,
        bool $breakout,
        float $rvol,
        float $distanceToNeckline,
        float $stopLossTight
    ): array {
        $count = count($candles);
        $highestPostLow3 = 0.0;
        $hadBreakoutClose = false;

        for ($post = $low3Index + 1; $post < $count; $post++) {
            if ($candles[$post]['high'] > $highestPostLow3) {
                $highestPostLow3 = (float) $candles[$post]['high'];
            }
            if ($candles[$post]['close'] > $neckline) {
                $hadBreakoutClose = true;
            }
        }

        // 1. Bull Trap: Sempat closing di atas Neckline tapi harga anjlok kembali ke bawah Neckline
        $bullTrap = ($hadBreakoutClose && $currentClose < $neckline);

        // 2. Penetration Reversal: Sempat menusuk ke atas Neckline secara intraday namun closing di bawah
        $penetrationReversal = (!$hadBreakoutClose && $highestPostLow3 >= $neckline && $currentClose < $neckline);

        // 3. Rejection Upper Wick pada lilin terakhir (ekor atas >= 40% dari total range lilin)
        $latestCandle = end($candles);
        $latestRange = max(0.0001, (float) $latestCandle['high'] - (float) $latestCandle['low']);
        $latestUpperWick = (float) $latestCandle['high'] - max((float) $latestCandle['open'], (float) $latestCandle['close']);
        $upperWickRatio = $latestUpperWick / $latestRange;
        $hasRejectionWick = ($upperWickRatio >= 0.40 && (float) $latestCandle['high'] >= $neckline * 0.98);

        // 4. Low Volume Breakout: Breakout tanpa konfirmasi partisipasi institusi (RVOL < 1.0)
        $lowVolumeBreakout = ($breakout && $rvol < 1.0);

        // 5. Overextended Breakout (>6% di atas neckline tanpa istirahat)
        $isOverextended = ($breakout && $distanceToNeckline > 6.0);

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

        if ($breakout && $distanceToNeckline >= 0 && $distanceToNeckline <= 2.5 && $rvol >= 1.2) {
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
            $riskReasons[] = "Bull Trap Terdeteksi: Harga sempat menembus Neckline ($" . number_format($neckline, 2) . ") namun gagal bertahan dan anjlok kembali di bawahnya.";
            $mitigationTips[] = "JANGAN BELI SEKARANG. Tunggu candle baru berhasil ditutup kembali di atas Neckline sebelum entri.";
            $mitigationTips[] = "Jika Anda sudah memegang posisi, pasang Stop Loss disiplin di $" . number_format($stopLossTight, 2) . " untuk memotong kerugian dini.";
        } elseif ($penetrationReversal) {
            $riskReasons[] = "Rejection Resistance Intraday: Harga sempat menyentuh garis Neckline ($" . number_format($neckline, 2) . ") namun ditekan turun dan gagal closing di atas resistance.";
            $mitigationTips[] = "Gunakan strategi Buy Stop Order tepat di atas harga tertinggi ($" . number_format($highestPostLow3, 2) . ") untuk mengonfirmasi penembusan sejati.";
        }

        if ($hasRejectionWick) {
            $riskReasons[] = "Upper Wick Rejection (" . round($upperWickRatio * 100) . "%): Lilin terakhir memiliki ekor atas panjang, menandakan aksi ambil untung / penolakan kuat dari penjual.";
            $mitigationTips[] = "Tunggu lilin konfirmasi berikutnya menembus harga tertinggi ekor ($" . number_format((float)$latestCandle['high'], 2) . ") sebelum membuka posisi baru.";
        }

        if ($lowVolumeBreakout) {
            $riskReasons[] = "Low Volume Breakout (RVOL " . number_format($rvol, 1) . "x): Penembusan terjadi dengan volume di bawah rata-rata (kurang partisipasi institusi).";
            $mitigationTips[] = "Hindari langsung beli agresif. Tunggu fase Retest Neckline dengan volume mengering (dry-up) lalu pantulan bervolume tinggi.";
        }

        if ($isOverextended) {
            $riskReasons[] = "Overextended Breakout (+" . number_format($distanceToNeckline, 1) . "%): Harga telah bergerak terlalu jauh di atas titik breakout tanpa istirahat.";
            $mitigationTips[] = "Hindari FOMO beli di pucuk. Tunggu harga pullback kembali ke area Golden Zone Neckline ($" . number_format($neckline, 2) . " - $" . number_format($neckline * 1.03, 2) . ").";
        }

        if (empty($riskReasons)) {
            $riskReasons[] = "Breakout Sehat & Solid: Penembusan didukung struktur harga yang rapi dan tanpa tekanan jual penolakan yang membahayakan.";
            $mitigationTips[] = "Pasang Stop Loss disiplin 2% di bawah Neckline ($" . number_format($stopLossTight, 2) . ") dan jalankan trailing stop bertahap saat mencapai TP1.";
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
     * Hitung tingkat keyakinan (Confidence Score 0 - 100%)
     */
    private function calculateConfidence(
        string $patternType,
        array $low1,
        array $low2,
        array $low3,
        float $peak1,
        float $peak2,
        float $neckline,
        float $currentClose,
        bool $breakout,
        float $maxDiff,
        float $headDropLeft,
        float $headDropRight,
        float $shoulderDiff,
        array $candles,
        string $riskLevel = self::RISK_LOW
    ): int {
        $score = 40; // Base score untuk pola yang terdeteksi

        if ($patternType === self::PATTERN_INVERSE_HS) {
            // Kesimetrisan bahu kiri & kanan
            if ($shoulderDiff <= 0.02) {
                $score += 18;
            } elseif ($shoulderDiff <= 0.04) {
                $score += 12;
            } elseif ($shoulderDiff <= 0.08) {
                $score += 6;
            }

            // Kejelasan kedalaman kepala (Head prominence)
            if ($headDropLeft >= 0.04 && $headDropRight >= 0.04) {
                $score += 10;
            }
        } else {
            // Kesimetrisan 3 lembah horizontal (Triple Bottom)
            if ($maxDiff <= 0.01) {
                $score += 18;
            } elseif ($maxDiff <= 0.02) {
                $score += 12;
            } elseif ($maxDiff <= 0.03) {
                $score += 6;
            }
        }

        // Kesimetrisan puncak neckline (Peak 1 & Peak 2)
        $peakDiff = abs($peak1 - $peak2) / max(($peak1 + $peak2) / 2, 0.01);
        if ($peakDiff <= 0.02) {
            $score += 10;
        } elseif ($peakDiff <= 0.05) {
            $score += 5;
        }

        // Konfirmasi Breakout
        if ($breakout) {
            $score += 15;
        }

        // Analisa Volume
        $low1Vol = (int) ($candles[$low1['index']]['volume'] ?? 0);
        $low3Vol = (int) ($candles[$low3['index']]['volume'] ?? 0);
        if ($low1Vol > 0 && $low3Vol < $low1Vol * 0.85) {
            $score += 10; // Volume mengering di lembah ke-3
        }

        $vols = array_column($candles, 'volume');
        $avgVol = count($vols) > 0 ? (array_sum($vols) / count($vols)) : 1;
        $currentVol = (int) (end($candles)['volume'] ?? 0);
        if ($avgVol > 0 && ($currentVol / $avgVol) >= 1.3) {
            $score += 7; // Lonjakan volume
        }

        // Penalti bila risiko False Breakout tinggi
        if ($riskLevel === self::RISK_HIGH) {
            $score -= 15;
        } elseif ($riskLevel === self::RISK_MEDIUM) {
            $score -= 5;
        }

        return (int) min(100, max(20, $score));
    }

    /**
     * Memuat candle harian atau intraday
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
            \Yii::warning('Intraday unavailable (DB scan ' . $stockId . '): ' . $e->getMessage(), 'app\services\triplebottom');
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
            self::PATTERN_ALL => 'Semua Pola (Triple Bottom & Inverted H&S)',
            self::PATTERN_TRIPLE_BOTTOM => 'Triple Bottom (3 Lembah Rata)',
            self::PATTERN_INVERSE_HS => 'Inverted Head & Shoulders (Bahu-Kepala-Bahu Terbalik)',
        ];
    }

    public static function getFalseBreakoutFilters(): array
    {
        return [
            self::RISK_ALL => 'Semua Tingkat Risiko',
            self::RISK_LOW => '🟢 Risiko Rendah (Aman)',
            self::RISK_MEDIUM => '🟡 Risiko Sedang (Butuh Konfirmasi)',
            self::RISK_HIGH => '🔴 Risiko Tinggi (Waspada)',
            self::RISK_BULL_TRAP => '⚠️ Terdeteksi Bull Trap / Gagal Breakout',
        ];
    }

    private function validateOptions(
        int $timeframe,
        int $lookback,
        float $tolerance,
        int $minSeparation,
        ?int $maxSeparation,
        float $necklineMinDepth
    ): void {
        if (!array_key_exists($timeframe, self::getTimeframes())) {
            throw new \InvalidArgumentException("Timeframe tidak valid: {$timeframe}");
        }
        if ($lookback < 15 || $lookback > 500) {
            throw new \InvalidArgumentException("Lookback harus antara 15 dan 500 candle");
        }
        if ($tolerance <= 0 || $tolerance > 0.20) {
            throw new \InvalidArgumentException("Toleransi harus antara 0% dan 20%");
        }
        if ($minSeparation < 2) {
            throw new \InvalidArgumentException("Pemisahan minimal harus >= 2");
        }
        if ($maxSeparation !== null && $maxSeparation < $minSeparation) {
            throw new \InvalidArgumentException("Pemisahan maksimal harus >= minimal");
        }
        if ($necklineMinDepth < 0 || $necklineMinDepth > 0.50) {
            throw new \InvalidArgumentException("Kedalaman neckline minimal harus antara 0% dan 50%");
        }
    }
}
