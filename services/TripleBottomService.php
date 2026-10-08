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

    /**
     * Scan seluruh saham aktif untuk mendeteksi pola Triple Bottom
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
        $lookback ??= self::DEFAULT_LOOKBACK;
        $tolerance ??= self::DEFAULT_TOLERANCE;
        $necklineMinDepth ??= self::NECKLINE_MIN_DEPTH;
        $this->validateOptions($timeframe, $lookback, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);
        $maxSeparation ??= (int) floor($lookback / 2);

        $results = [];
        $stocks = Stock::find()->where(['active' => true])->all();

        \Yii::info("Triple Bottom Scan started: " . count($stocks) . " stocks, timeframe={$timeframe}, lookback={$lookback}", 'app\services\triplebottom');

        foreach ($stocks as $stock) {
            $pattern = $this->detectPattern($stock->id, $timeframe, $lookback, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);
            if ($pattern === null) {
                continue;
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
     * Deteksi pola Triple Bottom pada satu saham
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
            $necklineMinDepth
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
     * Algoritma deteksi 3 Lembah (Triple Bottom) & Neckline
     */
    private function findTripleBottom(
        array $candles,
        float $tolerance,
        int $minSeparation,
        int $maxSeparation,
        float $necklineMinDepth = self::NECKLINE_MIN_DEPTH
    ): ?array {
        $count = count($candles);
        if ($count < 15) {
            return null;
        }

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

                    // Cek toleransi variasi harga antar ketiga lembah
                    $diff1 = abs($low1['price'] - $avgLow) / $avgLow;
                    $diff2 = abs($low2['price'] - $avgLow) / $avgLow;
                    $diff3 = abs($low3['price'] - $avgLow) / $avgLow;
                    $maxDiff = max($diff1, $diff2, $diff3);

                    if ($maxDiff > $tolerance) {
                        continue;
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
                    // Menggunakan peak tertinggi sebagai level konfirmasi breakout definitif
                    $neckline = max($peak1, $peak2);
                    $necklineAvg = ($peak1 + $peak2) / 2;

                    // Validasi kedalaman pola: Neckline harus cukup tinggi di atas support bottom
                    $depth = ($neckline - $avgLow) / $avgLow;
                    if ($depth < $necklineMinDepth) {
                        continue;
                    }

                    // Pastikan harga setelah Low 3 tidak jebol di bawah support (toleransi breakdown 2%)
                    $minSupport = min($low1['price'], $low2['price'], $low3['price']);
                    $broken = false;
                    for ($post = $low3['index'] + 1; $post < $count; $post++) {
                        if ($candles[$post]['close'] < $minSupport * (1 - self::STOP_LOSS_BUFFER)) {
                            $broken = true;
                            break;
                        }
                    }
                    if ($broken) {
                        continue;
                    }

                    $currentClose = (float) end($candles)['close'];
                    $breakout = $currentClose > $neckline;

                    // Hitung skor confidence pola
                    $confidence = $this->calculateConfidence(
                        $low1,
                        $low2,
                        $low3,
                        $peak1,
                        $peak2,
                        $neckline,
                        $currentClose,
                        $breakout,
                        $maxDiff,
                        $candles
                    );

                    // Level Target Take Profit (TP)
                    $patternHeight = $neckline - $avgLow;
                    $targetPrice = round($neckline + $patternHeight, 2); // 100% Measured move
                    $tp1 = round($neckline + ($patternHeight * 0.5), 2); // 50% Measured move
                    $tp2 = $targetPrice;
                    $tp3 = round($neckline + ($patternHeight * 1.618), 2); // Fib extension 161.8%

                    // Level Stop Loss (SL)
                    $stopLossTight = round($neckline * 0.98, 2); // 2% di bawah neckline (untuk breakout / retest buyers)
                    $stopLossSwing = round($minSupport * (1 - self::STOP_LOSS_BUFFER), 2); // 2% di bawah lowest bottom (untuk swing/bounce buyers)

                    // Jarak harga saat ini ke Neckline (%)
                    $distanceToNeckline = round(($currentClose - $neckline) / max($neckline, 0.0001) * 100, 2);

                    // Analisa Volume
                    $low1Vol = (int) ($candles[$low1['index']]['volume'] ?? 0);
                    $low2Vol = (int) ($candles[$low2['index']]['volume'] ?? 0);
                    $low3Vol = (int) ($candles[$low3['index']]['volume'] ?? 0);
                    
                    // Pola Triple Bottom ideal: Volume mengering di Low 2 & Low 3 (penjual habis)
                    $volDryUp = ($low3Vol < $low1Vol * 0.85) || ($low2Vol < $low1Vol * 0.9);
                    
                    $vols = array_column($candles, 'volume');
                    $avgVol = count($vols) > 0 ? (array_sum($vols) / count($vols)) : 1;
                    $currentVol = (int) (end($candles)['volume'] ?? 0);
                    $rvol = $avgVol > 0 ? round($currentVol / $avgVol, 2) : 1.0;
                    $volConfirmed = ($breakout && $rvol >= 1.3) || $volDryUp;

                    // Evaluasi Sinyal Trading & Waktu Terbaik (Execution Directives)
                    // Status: buy_zone, retest, bottom3_bounce, extended, overextended, approaching, forming
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

                    // Kalkulasi Risk / Reward Ratio (R:R)
                    // Untuk breakout / golden zone: pakai Tight SL ke TP2
                    // Untuk bottom bounce: pakai Swing SL ke Neckline / TP1
                    $activeSl = in_array($tradeStatus, ['bottom3_bounce', 'forming', 'approaching'], true) ? $stopLossSwing : $stopLossTight;
                    $risk = max($currentClose - $activeSl, 0.01);
                    $reward = max($targetPrice - $currentClose, 0.0);
                    $rrRatio = round($reward / $risk, 2);

                    $tightRisk = max($currentClose - $stopLossTight, 0.01);
                    $rrTight = round(max($tp2 - $currentClose, 0.0) / $tightRisk, 2);

                    $swingRisk = max($currentClose - $stopLossSwing, 0.01);
                    $rrSwing = round(max($tp2 - $currentClose, 0.0) / $swingRisk, 2);

                    $candidate = [
                        'low1_price' => $low1['price'],
                        'low1_date' => $low1['date'],
                        'low1_index' => $low1['index'],
                        'low2_price' => $low2['price'],
                        'low2_date' => $low2['date'],
                        'low2_index' => $low2['index'],
                        'low3_price' => $low3['price'],
                        'low3_date' => $low3['date'],
                        'low3_index' => $low3['index'],
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
                        'risk_reward' => $rrRatio,
                        'rr_tight' => $rrTight,
                        'rr_swing' => $rrSwing,
                        'best_rr' => max($rrTight, $rrRatio),
                        'distance_neckline_pct' => $distanceToNeckline,
                        'pattern_height' => round($patternHeight, 2),
                        'pattern_height_pct' => round(($patternHeight / $avgLow) * 100, 2),
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
     * Hitung tingkat keyakinan (Confidence Score 0 - 100%)
     */
    private function calculateConfidence(
        array $low1,
        array $low2,
        array $low3,
        float $peak1,
        float $peak2,
        float $neckline,
        float $currentClose,
        bool $breakout,
        float $maxDiff,
        array $candles
    ): int {
        $score = 40; // Base score untuk 3 lembah yang terdeteksi

        // 1. Kesimetrisan level lembah (semakin rata, semakin kuat polanya)
        if ($maxDiff <= 0.01) {
            $score += 18; // variasi < 1%
        } elseif ($maxDiff <= 0.02) {
            $score += 12; // variasi < 2%
        } elseif ($maxDiff <= 0.03) {
            $score += 6;
        }

        // 2. Kesimetrisan puncak neckline (Peak 1 & Peak 2 setara)
        $peakDiff = abs($peak1 - $peak2) / max(($peak1 + $peak2) / 2, 0.01);
        if ($peakDiff <= 0.02) {
            $score += 10;
        } elseif ($peakDiff <= 0.04) {
            $score += 5;
        }

        // 3. Konfirmasi Breakout
        if ($breakout) {
            $score += 15;
        }

        // 4. Analisa Volume
        $low1Vol = (int) ($candles[$low1['index']]['volume'] ?? 0);
        $low3Vol = (int) ($candles[$low3['index']]['volume'] ?? 0);
        if ($low1Vol > 0 && $low3Vol < $low1Vol * 0.85) {
            $score += 10; // Volume mengering di Low 3
        }

        $vols = array_column($candles, 'volume');
        $avgVol = count($vols) > 0 ? (array_sum($vols) / count($vols)) : 1;
        $currentVol = (int) (end($candles)['volume'] ?? 0);
        if ($avgVol > 0 && ($currentVol / $avgVol) >= 1.3) {
            $score += 7; // Lonjakan volume saat mendekati / menembus neckline
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
