<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;

/**
 * Service Deteksi & Validasi Pola Double Bottom (W Reversal) Presisi Institusional.
 *
 * Menerapkan arsitektur 10 modul modular & pemisahan tegas antara:
 * 1. pattern_validity (kualitas geometri struktur W, prior downtrend, simetri, volume fase)
 * 2. trade_eligibility (kelayakan eksekusi: breakout vs early vs retest, R/R, buffer, overextension)
 *
 * Mendukung deteksi pivot swing (left/right window), normalisasi ATR(14),
 * evaluasi fase volume B1/B2/Breakout/Retest, kalkulasi 3 opsi entry,
 * Stop Loss dinamis, target multi-level TP1/TP2/TP3, position sizing, dan dual scoring.
 */
final class DoubleBottomService
{
    // Timeframe constants (dalam jam; 24 = harian)
    public const TIMEFRAME_1H = 1;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_1D = 24;

    // Default scanner terpusat
    public const DEFAULT_TIMEFRAME = self::TIMEFRAME_4H;
    public const DEFAULT_LOOKBACK = 80;         // candle window
    public const DEFAULT_TOLERANCE = 0.025;     // 2.5% perbedaan harga B1 vs B2
    public const DEFAULT_MIN_SEPARATION = 5;     // jarak minimum bar antar-bottom
    public const NECKLINE_MIN_DEPTH = 0.02;     // 2% di atas avg low atau >= 2.0x ATR

    // Status Pola & Eksekusi Lifecycle
    public const STATUS_DETECTED = 'DETECTED';
    public const STATUS_FORMING = 'FORMING';
    public const STATUS_EARLY_SETUP = 'EARLY_SETUP';
    public const STATUS_POTENTIAL = 'POTENTIAL';
    public const STATUS_BREAKOUT_CONFIRMED = 'BREAKOUT_CONFIRMED';
    public const STATUS_RETEST_PENDING = 'RETEST_PENDING';
    public const STATUS_RETEST_CONFIRMED = 'RETEST_CONFIRMED';
    public const STATUS_EXTENDED = 'EXTENDED';
    public const STATUS_OVEREXTENDED = 'OVEREXTENDED';
    public const STATUS_FAILED = 'FAILED';

    private const MIN_LOOKBACK = 10;
    private const MAX_LOOKBACK = 500;
    private const MIN_TOLERANCE = 0.001;
    private const MAX_TOLERANCE = 0.20;
    private const MIN_SEPARATION = 2;
    private const MIN_INTRADAY_BARS = 20;

    /**
     * Scan semua saham aktif untuk pola Double Bottom.
     */
    public function scanAll(
        int $timeframe = self::DEFAULT_TIMEFRAME,
        ?int $lookback = null,
        ?float $tolerance = null,
        int $minSeparation = self::DEFAULT_MIN_SEPARATION,
        ?int $maxSeparation = null,
        bool $breakoutOnly = false,
        int $minConfidence = 0,
        ?float $necklineMinDepth = null,
        ?string $statusFilter = null,
        ?float $minRr = null,
        bool $volOnly = false
    ): array {
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);
        $lookback ??= self::DEFAULT_LOOKBACK;
        $tolerance ??= self::DEFAULT_TOLERANCE;
        $necklineMinDepth ??= self::NECKLINE_MIN_DEPTH;
        $this->validateOptions($timeframe, $lookback, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);
        $maxSeparation ??= (int) floor($lookback * 0.75);

        $results = [];
        $stocks = Stock::find()->where(['active' => true])->all();

        foreach ($stocks as $stock) {
            $pattern = $this->detectPattern($stock->id, $timeframe, $lookback, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);
            if ($pattern === null) {
                continue;
            }

            if ($breakoutOnly && empty($pattern['breakout'])) {
                continue;
            }

            if ($pattern['confidence'] < $minConfidence) {
                continue;
            }

            // Filter status transaksi
            if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
                $st = $pattern['trade_status'] ?? '';
                if ($statusFilter === 'buy_zone' && $st !== 'buy_zone') {
                    continue;
                }
                if ($statusFilter === 'retest' && $st !== 'retest' && $pattern['lifecycle_status'] !== self::STATUS_RETEST_CONFIRMED) {
                    continue;
                }
                if ($statusFilter === 'early_setup' && $st !== 'early_setup' && $pattern['lifecycle_status'] !== self::STATUS_EARLY_SETUP) {
                    continue;
                }
                if ($statusFilter === 'breakout' && empty($pattern['breakout'])) {
                    continue;
                }
                if ($statusFilter === 'approaching' && $st !== 'approaching') {
                    continue;
                }
            }

            // Filter Risk/Reward minimum
            if ($minRr !== null && $minRr > 0) {
                $candRr = (float) ($pattern['best_rr'] ?? ($pattern['risk_reward'] ?? 0));
                if ($candRr < $minRr) {
                    continue;
                }
            }

            // Filter konfirmasi volume
            if ($volOnly && empty($pattern['volume_confirmed']) && empty($pattern['volume_dry_up'])) {
                continue;
            }

            $pattern['symbol'] = $stock->symbol;
            $pattern['stock_name'] = $stock->name;
            $pattern['sector'] = $stock->sector;
            $results[] = $pattern;
        }

        // Urutkan default berdasarkan trade_score / confidence tertinggi
        usort($results, fn ($a, $b) => ($b['trade_score'] ?? $b['confidence']) <=> ($a['trade_score'] ?? $a['confidence']));
        return $results;
    }

    /**
     * Deteksi pola double bottom presisi untuk 1 saham.
     */
    public function detectPattern(
        int $stockId,
        int $timeframe = self::DEFAULT_TIMEFRAME,
        ?int $lookback = null,
        ?float $tolerance = null,
        int $minSeparation = self::DEFAULT_MIN_SEPARATION,
        ?int $maxSeparation = null,
        ?float $necklineMinDepth = null
    ): ?array {
        $lookback ??= self::DEFAULT_LOOKBACK;
        $tolerance ??= self::DEFAULT_TOLERANCE;
        $necklineMinDepth ??= self::NECKLINE_MIN_DEPTH;
        $this->validateOptions($timeframe, $lookback, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);
        $maxSeparation ??= (int) floor($lookback * 0.75);

        [$candles, $approx] = $this->loadCandles($stockId, $timeframe, $lookback);
        if (count($candles) < 15) {
            return null;
        }

        // Ambil daily candles konteks untuk Multi-Timeframe (MTF) jika timeframe intraday
        $dailyCandles = ($timeframe !== self::TIMEFRAME_1D) ? $this->dailyCandles($stockId, 60) : $candles;

        $result = $this->findDoubleBottom($candles, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth, $dailyCandles);
        if ($result === null) {
            return null;
        }

        $result['stock_id'] = $stockId;
        $result['timeframe'] = $timeframe;
        $result['approximated'] = $approx;
        $result['data_source'] = $approx ? 'daily_fallback' : ($timeframe === self::TIMEFRAME_1D ? 'daily' : 'intraday');
        return $result;
    }

    private function validateOptions(int $timeframe, int $lookback, float $tolerance, int $minSeparation, ?int $maxSeparation, ?float $necklineMinDepth = null): void
    {
        if (!in_array($timeframe, [self::TIMEFRAME_1H, self::TIMEFRAME_2H, self::TIMEFRAME_4H, self::TIMEFRAME_1D], true)) {
            throw new \InvalidArgumentException('Timeframe harus salah satu dari 1H, 2H, 4H, atau 1D.');
        }
        if ($lookback < self::MIN_LOOKBACK || $lookback > self::MAX_LOOKBACK) {
            throw new \InvalidArgumentException('Lookback harus antara ' . self::MIN_LOOKBACK . ' dan ' . self::MAX_LOOKBACK . ' candle.');
        }
        if (!is_finite($tolerance) || $tolerance < self::MIN_TOLERANCE || $tolerance > self::MAX_TOLERANCE) {
            throw new \InvalidArgumentException('Tolerance harus antara 0.1% dan 20% (0.001 - 0.20).');
        }
        if ($minSeparation < self::MIN_SEPARATION) {
            throw new \InvalidArgumentException('Separasi minimum antar low adalah ' . self::MIN_SEPARATION . ' candle.');
        }
        if ($maxSeparation !== null && $maxSeparation < $minSeparation) {
            throw new \InvalidArgumentException('Separasi maksimum harus >= separasi minimum.');
        }
        if ($necklineMinDepth !== null && (!is_finite($necklineMinDepth) || $necklineMinDepth < 0 || $necklineMinDepth > 0.5)) {
            throw new \InvalidArgumentException('Neckline minimum depth harus antara 0% dan 50% (0 - 0.5).');
        }
    }

    /**
     * Inti Pencarian Pola Double Bottom.
     */
    private function findDoubleBottom(
        array $candles,
        float $tolerance,
        int $minSeparation,
        int $maxSeparation,
        float $necklineMinDepth = self::NECKLINE_MIN_DEPTH,
        array $dailyCandles = []
    ): ?array {
        $count = count($candles);
        if ($count < 5) {
            return null;
        }

        // 1. Hitung ATR(14)
        $atr = $this->calculateAtr($candles, 14);
        if ($atr <= 0) {
            $atr = max(end($candles)['close'] * 0.02, 0.01);
        }

        // 2. Deteksi Pivot Lows dan Pivot Highs (left 3, right 3 untuk granularitas, tanpa lookahead pada candle terakhir)
        $pivotsLow = $this->findPivots($candles, 'low', 3, 3);
        $pivotsHigh = $this->findPivots($candles, 'high', 3, 3);

        if (count($pivotsLow) < 2) {
            // Fallback local minima standar jika swing pivot terlalu ketat
            $pivotsLow = $this->findLocalExtremes($candles, 'low');
            $pivotsHigh = $this->findLocalExtremes($candles, 'high');
        }

        if (count($pivotsLow) < 2) {
            return null;
        }

        $best = null;
        $lc = count($pivotsLow);

        // Evaluasi semua pasangan B1 dan B2
        for ($i = 0; $i < $lc; $i++) {
            for ($j = $i + 1; $j < $lc; $j++) {
                $b1 = $pivotsLow[$i];
                $b2 = $pivotsLow[$j];

                $sep = $b2['index'] - $b1['index'];
                if ($sep < $minSeparation || $sep > $maxSeparation) {
                    continue;
                }

                $avgLow = ($b1['price'] + $b2['price']) / 2;
                if ($avgLow <= 0) {
                    continue;
                }

                $diffPrice = abs($b1['price'] - $b2['price']);
                $diffPct = $diffPrice / $avgLow;
                $diffAtr = $diffPrice / $atr;

                // Syarat toleransi: max_bottom_diff_pct (<= tolerance) ATAU max_bottom_diff_atr (<= 1.0 ATR)
                if ($diffPct > $tolerance && $diffAtr > 1.0) {
                    continue;
                }

                // 3. Cari Neckline (Puncak Tertinggi di antara B1 dan B2)
                $neckline = 0.0;
                $necklineDate = '';
                $necklineIdx = -1;
                for ($k = $b1['index']; $k <= $b2['index']; $k++) {
                    if ($candles[$k]['high'] > $neckline) {
                        $neckline = (float) $candles[$k]['high'];
                        $necklineDate = $candles[$k]['date'];
                        $necklineIdx = $k;
                    }
                }

                if ($neckline <= $avgLow) {
                    continue;
                }

                $patternHeight = $neckline - $avgLow;
                $necklineDepth = ($neckline - $avgLow) / $avgLow;

                // Syarat kedalaman: depth >= necklineMinDepth
                if ($necklineDepth < $necklineMinDepth) {
                    continue;
                }

                // 4. Pastikan Tidak Ada Breakdown Pasca-B2 (Candle tidak ditutup di bawah support B1/B2)
                $minSupport = min($b1['price'], $b2['price']);
                $breakdownBuffer = $atr * 0.3;
                $isBroken = false;
                for ($post = $b2['index'] + 1; $post < $count; $post++) {
                    if ($candles[$post]['close'] < ($minSupport - $breakdownBuffer)) {
                        $isBroken = true;
                        break;
                    }
                }
                if ($isBroken) {
                    continue;
                }

                // 5. Validasi Prior Downtrend sebelum B1 (30 bar sebelum B1)
                $priorDowntrend = $this->evaluatePriorDowntrend($candles, $b1['index']);

                // 6. Analisis Volume Fase
                $volAnalysis = $this->evaluateVolumePhases($candles, $b1['index'], $b2['index'], $necklineIdx);

                // 7. Evaluasi Breakout, Retest & Current Price Position
                $lastBar = end($candles);
                $currentClose = (float) $lastBar['close'];
                $currentHigh = (float) $lastBar['high'];
                $currentLow = (float) $lastBar['low'];

                $breakoutBuffer = $atr * 0.10;
                $isBreakout = ($currentClose > ($neckline + $breakoutBuffer));
                $distanceToNeckline = round(($currentClose - $neckline) / max($neckline, 0.0001) * 100, 2);

                // Cek apakah pernah terjadi breakout sebelumnya dan sekarang sedang retest
                $retestAnalysis = $this->evaluateRetest($candles, $neckline, $b2['index'], $atr);

                // Status Lifecycle & Action Directive
                $lifecycleStatus = self::STATUS_FORMING;
                $tradeStatus = 'forming';
                $tradeAction = 'WAIT (Forming)';

                if ($retestAnalysis['is_retesting']) {
                    $lifecycleStatus = self::STATUS_RETEST_CONFIRMED;
                    $tradeStatus = 'retest';
                    $tradeAction = 'BUY (Retest Neckline)';
                } elseif ($isBreakout) {
                    if ($distanceToNeckline >= 0 && $distanceToNeckline <= 3.5) {
                        $lifecycleStatus = self::STATUS_BREAKOUT_CONFIRMED;
                        $tradeStatus = 'buy_zone';
                        $tradeAction = 'BUY (Golden Zone)';
                    } elseif ($distanceToNeckline > 3.5 && $distanceToNeckline <= 6.5) {
                        $lifecycleStatus = self::STATUS_EXTENDED;
                        $tradeStatus = 'extended';
                        $tradeAction = 'MODERATE (Hati-hati Extended)';
                    } else {
                        $lifecycleStatus = self::STATUS_OVEREXTENDED;
                        $tradeStatus = 'overextended';
                        $tradeAction = 'WAIT PULLBACK (Overextended)';
                    }
                } else {
                    // Sebelum breakout: Cek apakah mendekati B2 (Early Setup) atau mendekati Neckline
                    $distFromB2 = ($currentClose - $b2['price']) / $b2['price'];
                    if ($b2['index'] >= ($count - 5) && $distFromB2 >= 0 && $distFromB2 <= 0.035) {
                        $lifecycleStatus = self::STATUS_EARLY_SETUP;
                        $tradeStatus = 'early_setup';
                        $tradeAction = 'EARLY BUY (Rejection di B2)';
                    } elseif ($distanceToNeckline >= -3.5 && $distanceToNeckline <= 0) {
                        $lifecycleStatus = self::STATUS_POTENTIAL;
                        $tradeStatus = 'approaching';
                        $tradeAction = 'PRE-BREAKOUT (Pantau Neckline)';
                    }
                }

                // 8. Evaluasi Multi-Timeframe (MTF) Context
                $mtfAnalysis = $this->evaluateMtfContext($dailyCandles);

                // 9. Kalkulasi 3 Skenario Entry, Stop Loss, dan Take Profit
                $tradeLevels = $this->calculateTradeLevels($b1['price'], $b2['price'], $neckline, $currentClose, $atr, $patternHeight, $retestAnalysis);

                // 10. Dual-Scoring Engine: Pattern Score (Struktur W) & Trade Score (Kelayakan Eksekusi)
                $patternScore = $this->calculatePatternScore($b1['price'], $b2['price'], $neckline, $diffPct, $diffAtr, $necklineDepth, $priorDowntrend, $volAnalysis);
                $tradeScore = $this->calculateTradeScore($patternScore, $lifecycleStatus, $isBreakout, $volAnalysis, $tradeLevels['best_rr'], $distanceToNeckline, $mtfAnalysis);

                // Confidence backward compatibility (kombinasi seimbang)
                $confidence = (int) round(($patternScore * 0.4) + ($tradeScore * 0.6));

                // Position Sizing Matrix (Berdasarkan modal standar $10,000 dan risiko akun 1.5%)
                $posSizing = $this->calculatePositionSizing($currentClose, $tradeLevels['sl_tight'], $tradeLevels['sl_swing']);

                $stopLossLegacy = $avgLow * (1 - 0.02);

                $cand = [
                    'low1_price' => $b1['price'],
                    'low1_date' => $b1['date'],
                    'low1_index' => $b1['index'],
                    'low2_price' => $b2['price'],
                    'low2_date' => $b2['date'],
                    'low2_index' => $b2['index'],
                    'neckline' => $neckline,
                    'neckline_date' => $necklineDate,
                    'neckline_index' => $necklineIdx,
                    'current_price' => $currentClose,
                    'atr' => round($atr, 3),
                    'pattern_height' => round($patternHeight, 2),
                    'bottom_diff_pct' => round($diffPct * 100, 2),
                    'bottom_diff_atr' => round($diffAtr, 2),
                    'breakout' => $isBreakout,
                    'lifecycle_status' => $lifecycleStatus,
                    'trade_status' => $tradeStatus,
                    'trade_action' => $tradeAction,
                    'pattern_score' => $patternScore,
                    'trade_score' => $tradeScore,
                    'confidence' => $confidence,

                    // Targets
                    'target_price' => $tradeLevels['tp2'],
                    'tp1_price' => $tradeLevels['tp1'],
                    'tp2_price' => $tradeLevels['tp2'],
                    'tp3_price' => $tradeLevels['tp3'],

                    // Stop Losses
                    'stop_loss' => $stopLossLegacy,
                    'stop_loss_tight' => $tradeLevels['sl_tight'],
                    'stop_loss_retest' => $tradeLevels['sl_retest'],
                    'stop_loss_swing' => $tradeLevels['sl_swing'],

                    // Entry Zones
                    'entry_zone_min' => round($neckline, 2),
                    'entry_zone_max' => round($neckline + ($atr * 0.5), 2),
                    'distance_neckline_pct' => $distanceToNeckline,

                    // 3 Spesifik Skenario Entry
                    'entries' => [
                        'early' => [
                            'entry' => round($b2['price'] + ($atr * 0.2), 2),
                            'sl' => round($tradeLevels['sl_swing'], 2),
                            'tp' => round($neckline, 2),
                            'rr' => round(($neckline - ($b2['price'] + ($atr * 0.2))) / max(($b2['price'] + ($atr * 0.2)) - $tradeLevels['sl_swing'], 0.01), 2),
                            'status' => ($lifecycleStatus === self::STATUS_EARLY_SETUP) ? 'ACTIVE' : 'PAST',
                        ],
                        'breakout' => [
                            'entry' => round($neckline + $breakoutBuffer, 2),
                            'sl' => round($tradeLevels['sl_tight'], 2),
                            'tp' => round($tradeLevels['tp2'], 2),
                            'rr' => $tradeLevels['rr_tight'],
                            'status' => $isBreakout ? 'TRIGGERED' : 'PENDING',
                        ],
                        'retest' => [
                            'entry' => round($neckline, 2),
                            'sl' => round($tradeLevels['sl_retest'], 2),
                            'tp' => round($tradeLevels['tp2'], 2),
                            'rr' => round(($tradeLevels['tp2'] - $neckline) / max($neckline - $tradeLevels['sl_retest'], 0.01), 2),
                            'status' => $retestAnalysis['is_retesting'] ? 'ACTIVE_ZONE' : 'WAITING',
                        ],
                    ],

                    // Volume Metrics
                    'low1_volume' => $volAnalysis['b1_vol'],
                    'low2_volume' => $volAnalysis['b2_vol'],
                    'rvol' => $volAnalysis['current_rvol'],
                    'breakout_rvol' => $volAnalysis['breakout_rvol'],
                    'volume_dry_up' => $volAnalysis['is_dry_up'],
                    'volume_dry_up_pct' => $volAnalysis['dry_up_pct'],
                    'volume_confirmed' => $volAnalysis['volume_confirmed'],

                    // Pattern Geometry & MTF
                    'pattern_type' => ($b2['price'] > $b1['price'] * 1.003) ? 'Higher Low' : (($b2['price'] < $b1['price'] * 0.997) ? 'Lower Low' : 'Twin Low'),
                    'pattern_span' => $sep,
                    'prior_downtrend' => $priorDowntrend,
                    'retest_analysis' => $retestAnalysis,
                    'mtf_context' => $mtfAnalysis,
                    'position_sizing' => $posSizing,

                    // Risk / Reward metrics
                    'rr_tight' => $tradeLevels['rr_tight'],
                    'rr_swing' => $tradeLevels['rr_swing'],
                    'best_rr' => $tradeLevels['best_rr'],
                    'risk_reward' => $isBreakout ? $tradeLevels['best_rr'] : null,
                ];

                if ($best === null || ($cand['trade_score'] + $cand['pattern_score']) > ($best['trade_score'] + $best['pattern_score'])) {
                    $best = $cand;
                }
            }
        }

        return $best;
    }

    /**
     * Hitung Average True Range (ATR).
     */
    private function calculateAtr(array $candles, int $period = 14): float
    {
        $count = count($candles);
        if ($count < 2) {
            return 1.0;
        }

        $tr = [];
        for ($i = 1; $i < $count; $i++) {
            $h = (float) $candles[$i]['high'];
            $l = (float) $candles[$i]['low'];
            $prevC = (float) $candles[$i - 1]['close'];

            $val = max($h - $l, abs($h - $prevC), abs($l - $prevC));
            $tr[] = $val;
        }

        $slice = array_slice($tr, -$period);
        return count($slice) > 0 ? (array_sum($slice) / count($slice)) : 1.0;
    }

    /**
     * Deteksi Pivot Swing (left/right window).
     */
    private function findPivots(array $candles, string $type, int $left, int $right): array
    {
        $count = count($candles);
        $pivots = [];

        // Pencegahan lookahead bias: index $i harus memiliki $right bar yang sudah tertutup
        for ($i = $left; $i < ($count - $right); $i++) {
            $isPivot = true;
            $val = (float) $candles[$i][$type];

            for ($l = 1; $l <= $left; $l++) {
                if ($type === 'low' && (float)$candles[$i - $l]['low'] <= $val) {
                    $isPivot = false;
                    break;
                }
                if ($type === 'high' && (float)$candles[$i - $l]['high'] >= $val) {
                    $isPivot = false;
                    break;
                }
            }
            if (!$isPivot) {
                continue;
            }

            for ($r = 1; $r <= $right; $r++) {
                if ($type === 'low' && (float)$candles[$i + $r]['low'] <= $val) {
                    $isPivot = false;
                    break;
                }
                if ($type === 'high' && (float)$candles[$i + $r]['high'] >= $val) {
                    $isPivot = false;
                    break;
                }
            }

            if ($isPivot) {
                $pivots[] = [
                    'index' => $i,
                    'price' => $val,
                    'date' => $candles[$i]['date'],
                ];
            }
        }

        return $pivots;
    }

    /**
     * Fallback pencarian local extremes 1-bar.
     */
    private function findLocalExtremes(array $candles, string $type): array
    {
        $count = count($candles);
        $extremes = [];
        for ($i = 1; $i < $count - 1; $i++) {
            if ($type === 'low' && $candles[$i]['low'] < $candles[$i - 1]['low'] && $candles[$i]['low'] < $candles[$i + 1]['low']) {
                $extremes[] = ['index' => $i, 'price' => (float)$candles[$i]['low'], 'date' => $candles[$i]['date']];
            } elseif ($type === 'high' && $candles[$i]['high'] > $candles[$i - 1]['high'] && $candles[$i]['high'] > $candles[$i + 1]['high']) {
                $extremes[] = ['index' => $i, 'price' => (float)$candles[$i]['high'], 'date' => $candles[$i]['date']];
            }
        }
        return $extremes;
    }

    /**
     * Evaluasi Tren Penurunan sebelum B1.
     */
    private function evaluatePriorDowntrend(array $candles, int $b1Idx): array
    {
        $lookback = min(30, $b1Idx);
        if ($lookback < 5) {
            return ['valid' => true, 'drop_pct' => 5.0, 'desc' => 'Lookback terbatas sebelum B1'];
        }

        $startIdx = $b1Idx - $lookback;
        $maxBeforeB1 = 0.0;
        for ($k = $startIdx; $k < $b1Idx; $k++) {
            if ($candles[$k]['high'] > $maxBeforeB1) {
                $maxBeforeB1 = (float)$candles[$k]['high'];
            }
        }

        $b1Price = (float)$candles[$b1Idx]['low'];
        $dropPct = ($maxBeforeB1 > 0) ? round(($maxBeforeB1 - $b1Price) / $maxBeforeB1 * 100, 1) : 0.0;
        $isValid = ($dropPct >= 7.0);

        return [
            'valid' => $isValid,
            'drop_pct' => $dropPct,
            'desc' => $isValid ? "Penurunan sehat {$dropPct}% mendahului B1" : "Penurunan dangkal ({$dropPct}%), risiko range sideways",
        ];
    }

    /**
     * Analisis Volume Berfase (B1, B2, Breakout).
     */
    private function evaluateVolumePhases(array $candles, int $b1Idx, int $b2Idx, int $necklineIdx): array
    {
        $volumes = array_column($candles, 'volume');
        $avgVol = count($volumes) > 0 ? (array_sum($volumes) / count($volumes)) : 1;
        $lastVol = (int) (end($candles)['volume'] ?? 0);
        $currentRvol = $avgVol > 0 ? round($lastVol / $avgVol, 2) : 1.0;

        $b1Vol = (int) ($candles[$b1Idx]['volume'] ?? 0);
        $b2Vol = (int) ($candles[$b2Idx]['volume'] ?? 0);

        // Supply Dry-up di B2 (volume B2 < 90% B1)
        $isDryUp = ($b1Vol > 0 && $b2Vol > 0 && $b2Vol < ($b1Vol * 0.90));
        $dryUpPct = ($b1Vol > 0) ? round(($b1Vol - $b2Vol) / $b1Vol * 100, 1) : 0.0;

        // Breakout Volume
        $breakoutRvol = $currentRvol;
        $volConfirmed = ($currentRvol >= 1.30) || $isDryUp;

        return [
            'b1_vol' => $b1Vol,
            'b2_vol' => $b2Vol,
            'avg_vol' => (int) $avgVol,
            'current_rvol' => $currentRvol,
            'breakout_rvol' => $breakoutRvol,
            'is_dry_up' => $isDryUp,
            'dry_up_pct' => $dryUpPct,
            'volume_confirmed' => $volConfirmed,
        ];
    }

    /**
     * Evaluasi Retest Pasca-Breakout.
     */
    private function evaluateRetest(array $candles, float $neckline, int $b2Idx, float $atr): array
    {
        $count = count($candles);
        $hasBrokenOut = false;
        $breakoutIdx = -1;

        for ($k = $b2Idx + 1; $k < $count - 1; $k++) {
            if ($candles[$k]['close'] > $neckline) {
                $hasBrokenOut = true;
                $breakoutIdx = $k;
                break;
            }
        }

        if (!$hasBrokenOut) {
            return ['is_retesting' => false, 'retest_low' => 0.0, 'desc' => 'Belum pernah breakout'];
        }

        $lastBar = end($candles);
        $currentClose = (float) $lastBar['close'];
        $currentLow = (float) $lastBar['low'];

        // Zona retest: Neckline - 0.15 ATR s/d Neckline + 0.35 ATR
        $zoneLow = $neckline - ($atr * 0.15);
        $zoneHigh = $neckline + ($atr * 0.35);

        $isRetesting = ($currentLow <= $zoneHigh && $currentClose >= $zoneLow);

        return [
            'is_retesting' => $isRetesting,
            'retest_low' => $currentLow,
            'desc' => $isRetesting ? 'Harga sedang retest zona neckline dari atas' : 'Tidak dalam retest',
        ];
    }

    /**
     * Evaluasi Multi-Timeframe (MTF) Context.
     */
    private function evaluateMtfContext(array $dailyCandles): array
    {
        if (count($dailyCandles) < 20) {
            return ['aligned' => true, 'bias' => 'BULLISH_BIAS', 'desc' => 'Data daily terbatas, bias netral'];
        }

        $closes = array_column($dailyCandles, 'close');
        $sma20 = array_sum(array_slice($closes, -20)) / 20;
        $currentDaily = (float) end($closes);

        $isBullish = ($currentDaily >= $sma20 * 0.98);

        return [
            'aligned' => $isBullish,
            'daily_sma20' => round($sma20, 2),
            'bias' => $isBullish ? 'BULLISH_ALIGNED' : 'NEUTRAL_TO_BEARISH',
            'desc' => $isBullish ? 'Konteks Daily mendukung kenaikan (di atas SMA 20)' : 'Daily masih di bawah SMA 20, batasi ekspektasi target',
        ];
    }

    /**
     * Hitung Level Trading: TP1, TP2, TP3, SL Tight, SL Swing, SL Retest.
     */
    private function calculateTradeLevels(
        float $b1,
        float $b2,
        float $neckline,
        float $currentClose,
        float $atr,
        float $patternHeight,
        array $retestAnalysis
    ): array {
        $minBottom = min($b1, $b2);

        // TP1: Proyeksi 100% Measured Move (Neckline + Tinggi W)
        $tp2 = round($neckline + $patternHeight, 2);
        // TP1 Konservatif: 50% dari tinggi pola
        $tp1 = round($neckline + ($patternHeight * 0.50), 2);
        // TP3 Runner: 1.618 Fib Extension
        $tp3 = round($neckline + ($patternHeight * 1.618), 2);

        // SL Swing: Bawah Low 1 & 2 dengan buffer ATR
        $slSwing = round($minBottom - ($atr * 0.35), 2);
        // SL Tight: Sedikit di bawah Neckline (2% atau 0.25 ATR)
        $slTight = round($neckline - max($atr * 0.25, $neckline * 0.02), 2);
        // SL Retest: Di bawah low retest
        $retestLow = $retestAnalysis['retest_low'] > 0 ? $retestAnalysis['retest_low'] : $neckline;
        $slRetest = round(min($retestLow, $neckline) - ($atr * 0.25), 2);

        // Risk / Reward
        $riskTight = max($currentClose - $slTight, 0.01);
        $rewardTp1 = max($tp1 - $currentClose, 0.0);
        $rewardTp2 = max($tp2 - $currentClose, 0.0);

        $rrTight = round($rewardTp2 / $riskTight, 2);

        $riskSwing = max($currentClose - $slSwing, 0.01);
        $rrSwing = round($rewardTp2 / $riskSwing, 2);

        $bestRr = max($rrTight, $rrSwing);

        return [
            'tp1' => $tp1,
            'tp2' => $tp2,
            'tp3' => $tp3,
            'sl_tight' => $slTight,
            'sl_swing' => $slSwing,
            'sl_retest' => $slRetest,
            'rr_tight' => $rrTight,
            'rr_swing' => $rrSwing,
            'best_rr' => $bestRr,
        ];
    }

    /**
     * Hitung Pattern Score (Kualitas Geometri W, simetri, volume fase).
     */
    private function calculatePatternScore(
        float $b1,
        float $b2,
        float $neckline,
        float $diffPct,
        float $diffAtr,
        float $necklineDepth,
        array $priorDowntrend,
        array $volAnalysis
    ): int {
        $score = 50;

        // 1. Simetri B1 vs B2 (maks +20)
        if ($diffPct < 0.01 || $diffAtr < 0.30) {
            $score += 20;
        } elseif ($diffPct < 0.02 || $diffAtr < 0.60) {
            $score += 15;
        } elseif ($diffPct < 0.025) {
            $score += 8;
        }

        // 2. Kedalaman Pantulan Neckline (maks +15)
        if ($necklineDepth > 0.05) {
            $score += 15;
        } elseif ($necklineDepth > 0.03) {
            $score += 10;
        } elseif ($necklineDepth > 0.02) {
            $score += 5;
        }

        // 3. Tren Turun Sebelumnya (maks +10)
        if (!empty($priorDowntrend['valid'])) {
            $score += 10;
        }

        // 4. Volume Karakteristik (B2 Dry-up atau Breakout Volume) (maks +10)
        if (!empty($volAnalysis['is_dry_up'])) {
            $score += 5;
        }
        if ($volAnalysis['current_rvol'] >= 1.5) {
            $score += 5;
        }

        return (int) min(100, max(20, $score));
    }

    /**
     * Hitung Trade Score (Kelayakan Eksekusi, R/R, Overextension).
     */
    private function calculateTradeScore(
        int $patternScore,
        string $status,
        bool $isBreakout,
        array $volAnalysis,
        float $bestRr,
        float $distNecklinePct,
        array $mtfAnalysis
    ): int {
        $score = 40;

        // Base dari pattern
        $score += (int) ($patternScore * 0.20);

        // Status eksekusi
        if ($status === self::STATUS_BREAKOUT_CONFIRMED || $status === self::STATUS_RETEST_CONFIRMED) {
            $score += 25;
        } elseif ($status === self::STATUS_EARLY_SETUP) {
            $score += 15;
        } elseif ($status === self::STATUS_POTENTIAL) {
            $score += 10;
        }

        // Volume Breakout konfirmasi
        if ($volAnalysis['current_rvol'] >= 1.5) {
            $score += 10;
        }

        // Risk / Reward
        if ($bestRr >= 2.5) {
            $score += 15;
        } elseif ($bestRr >= 2.0) {
            $score += 10;
        } elseif ($bestRr >= 1.5) {
            $score += 5;
        } else {
            $score -= 10; // Hard filter penalty R/R buruk
        }

        // Penalty overextended
        if ($distNecklinePct > 6.0) {
            $score -= 25;
        } elseif ($distNecklinePct > 4.0) {
            $score -= 10;
        }

        // MTF bonus
        if (!empty($mtfAnalysis['aligned'])) {
            $score += 5;
        }

        return (int) min(100, max(10, $score));
    }

    /**
     * Hitung Position Sizing otomatis untuk manajemen risiko.
     */
    private function calculatePositionSizing(float $entryPrice, float $slTight, float $slSwing): array
    {
        $accountCapital = 10000; // Contoh modal akun $10,000
        $riskPct = 0.015;        // Batas risiko 1.5% = $150
        $riskAmount = $accountCapital * $riskPct;

        $riskTightPerShare = max($entryPrice - $slTight, 0.05);
        $sharesTight = (int) floor($riskAmount / $riskTightPerShare);
        $capitalTight = round($sharesTight * $entryPrice, 2);

        $riskSwingPerShare = max($entryPrice - $slSwing, 0.05);
        $sharesSwing = (int) floor($riskAmount / $riskSwingPerShare);
        $capitalSwing = round($sharesSwing * $entryPrice, 2);

        return [
            'account_capital' => $accountCapital,
            'risk_amount' => $riskAmount,
            'shares_tight' => max($sharesTight, 1),
            'capital_tight' => $capitalTight,
            'shares_swing' => max($sharesSwing, 1),
            'capital_swing' => $capitalSwing,
        ];
    }

    /**
     * Muat candle untuk deteksi.
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

    public function getAnalysisCandles(int $stockId, int $timeframe = self::DEFAULT_TIMEFRAME, ?int $lookback = null): array
    {
        $lookback ??= self::DEFAULT_LOOKBACK;
        return $this->loadCandles($stockId, $timeframe, $lookback);
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

    /**
     * Hitung confidence score untuk pattern (0-100).
     * Dipertahankan untuk backward compatibility dengan unit test dan integrasi lama.
     */
    private function calculateConfidence(
        float $low1,
        float $low2,
        float $neckline,
        float $currentClose,
        bool $breakout,
        float $lowDiff,
        array $candles = [],
        int $low1Index = -1,
        int $low2Index = -1
    ): int {
        $score = 50;

        if ($lowDiff < 0.01) {
            $score += 15;
        } elseif ($lowDiff < 0.015) {
            $score += 10;
        } elseif ($lowDiff < 0.02) {
            $score += 5;
        }

        if ($breakout) {
            $score += 20;
        }

        $avgLow = ($low1 + $low2) / 2;
        $depth = ($avgLow > 0) ? (($neckline - $avgLow) / $avgLow) : 0;
        if ($depth > 0.05) {
            $score += 10;
        } elseif ($depth > 0.03) {
            $score += 5;
        }

        if ($currentClose > $neckline * 1.02) {
            $score += 5;
        }

        $volumes = array_column($candles, 'volume');
        $volumes = array_values(array_filter($volumes, fn ($v) => (int) $v > 0));
        if (count($volumes) >= 5) {
            $avgVol = array_sum($volumes) / count($volumes);
            if ($avgVol > 0 && $low2Index >= 0 && isset($candles[$low2Index]['volume'])) {
                if ((int) $candles[$low2Index]['volume'] < $avgVol * 0.9) {
                    $score += 5;
                }
            }
            if ($breakout) {
                $lastVol = (int) end($candles)['volume'];
                if ($lastVol > $avgVol * 1.5) {
                    $score += 5;
                }
            }
        }

        return min(100, max(0, $score));
    }

    public static function getTimeframeLabel(int $timeframe): string
    {
        return match ($timeframe) {
            self::TIMEFRAME_1H => '1 Hour',
            self::TIMEFRAME_2H => '2 Hours',
            self::TIMEFRAME_4H => '4 Hours',
            self::TIMEFRAME_1D => 'Daily',
            default => "{$timeframe}H",
        };
    }

    public static function getTimeframes(): array
    {
        return [
            self::TIMEFRAME_1H => '1 Hour',
            self::TIMEFRAME_2H => '2 Hours',
            self::TIMEFRAME_4H => '4 Hours',
            self::TIMEFRAME_1D => 'Daily',
        ];
    }
}
