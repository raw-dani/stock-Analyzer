<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use Yii;

/**
 * LiquiditySweepService
 *
 * Dedicated Engine untuk mendeteksi Shakeout Low / Liquidity Sweep dengan risiko terukur:
 * 1. Menentukan Multi-Reference Low (Swing Low, Support Zone, Lowest Low 10D/20D, Day/Week Low, Lower BB).
 * 2. Mengukur Penetration Depth & Reclaim Status.
 * 3. Menghitung Lower Wick Ratio (minimal 0.40) & Close Location Value (CLV > 0).
 * 4. Konfirmasi Volume (RVOL >= 1.5x atau Absorption footprint).
 * 5. Konfirmasi Follow-Through (Higher Low candle berikutnya / break minor high).
 * 6. Membedakan Liquidity Sweep Reversal vs Breakdown Bearish (Hard Gate).
 * 7. Liquidity Sweep Scoring (0-100) independen dengan 7 komponen terbobot.
 */
final class LiquiditySweepService
{
    // Status Sweep
    public const STATUS_CONFIRMED_SWEEP = 'CONFIRMED_SWEEP';
    public const STATUS_HIGH_QUALITY_SWEEP = 'HIGH_QUALITY_SWEEP';
    public const STATUS_POTENTIAL_SWEEP = 'POTENTIAL_SWEEP';
    public const STATUS_WEAK_SWEEP = 'WEAK_SWEEP';
    public const STATUS_PENDING_RECLAIM = 'PENDING_RECLAIM';
    public const STATUS_BREAKDOWN = 'BREAKDOWN';
    public const STATUS_NONE = 'NONE';

    // Bobot Komponen Scoring (Total 100%)
    public const WEIGHT_SUPPORT_QUALITY = 20;
    public const WEIGHT_PENETRATION = 10;
    public const WEIGHT_RECLAIM_STRENGTH = 20;
    public const WEIGHT_WICK_REJECTION = 15;
    public const WEIGHT_VOLUME = 15;
    public const WEIGHT_MOMENTUM = 10;
    public const WEIGHT_FOLLOW_THROUGH = 10;

    /**
     * Menganalisis Liquidity Sweep pada serangkaian candle OHLCV.
     *
     * @param array<int, array{open: float, high: float, low: float, close: float, volume: float, date?: string}> $candles
     * @param float|null $lowerBand Nilai Lower Bollinger Band saat ini (opsional)
     * @return array{
     *     status: string,
     *     statusLabel: string,
     *     statusBadge: array{label: string, class: string, desc: string},
     *     score: int,
     *     scoreCategory: string,
     *     isSweep: bool,
     *     isReclaimed: bool,
     *     isBreakdown: bool,
     *     referenceLow: float,
     *     referenceType: string,
     *     penetrationPct: float,
     *     reclaimPct: float,
     *     lowerWickRatio: float,
     *     clv: float,
     *     rvol: float,
     *     atr: float,
     *     entryTrigger: float,
     *     stopLoss: float,
     *     tp1: float,
     *     tp2: float,
     *     riskRewardRatio: float,
     *     components: array<string, int>,
     *     hardGateReason: string|null,
     *     details: array<string, mixed>
     * }
     */
    public function analyzeCandles(array $candles, ?float $lowerBand = null): array
    {
        $count = count($candles);
        if ($count < 25) {
            return $this->buildEmptyResult('Data candle tidak lengkap (< 25 candle).');
        }

        $curr = $candles[$count - 1];
        $prev = $candles[$count - 2];
        $prev2 = $candles[$count - 3] ?? null;

        $currOpen = (float) $curr['open'];
        $currHigh = (float) $curr['high'];
        $currLow = (float) $curr['low'];
        $currClose = (float) $curr['close'];
        $currVol = (float) $curr['volume'];

        // 1. Hitung ATR 14-periode untuk adaptasi volatilitas
        $atr = $this->calculateAtr($candles, 14);
        $atrSafe = max(0.0001, $atr);

        // 2. Tentukan Reference Liquidity Lows sebelum candle sweep
        // Kita teliti bar-bar sebelum bar terakhir (lookback 20 bar)
        $pastBars = array_slice($candles, max(0, $count - 26), 25);
        // Exclude candle saat ini agar tidak bias
        array_pop($pastBars);

        $refLows = $this->identifyReferenceLows($pastBars, $lowerBand);
        $primaryRef = $refLows['primary'];
        $refPrice = $primaryRef['price'];
        $refType = $primaryRef['type'];

        if ($refPrice <= 0) {
            return $this->buildEmptyResult('Tidak ditemukan reference low yang valid.');
        }

        // 3. Evaluasi Penetrasi & Reclaim (Hard Gate)
        $hasPenetrated = ($currLow < $refPrice);
        $penetrationAmount = $hasPenetrated ? ($refPrice - $currLow) : 0.0;
        $penetrationPct = round(($penetrationAmount / $refPrice) * 100, 2);

        $isReclaimed = ($currClose >= $refPrice);
        $reclaimPct = $isReclaimed ? round((($currClose - $refPrice) / $refPrice) * 100, 2) : 0.0;

        // 4. Hitung Metrik Candlestick: Lower Wick Ratio & CLV (Close Location Value)
        $candleRange = max(0.0001, $currHigh - $currLow);
        $body = abs($currClose - $currOpen);
        $lowerWick = min($currClose, $currOpen) - $currLow;
        $lowerWickRatio = round($lowerWick / $candleRange, 3);

        // CLV = ((Close - Low) - (High - Close)) / (High - Low), berkisar -1.0 s/d +1.0
        $clv = round((($currClose - $currLow) - ($currHigh - $currClose)) / $candleRange, 3);

        // 5. Hitung RVOL (Relative Volume terhadap rata-rata 20 candle)
        $volSlice = array_map(fn($c) => (float) $c['volume'], array_slice($candles, -22, 20));
        $avgVol20 = !empty($volSlice) ? (array_sum($volSlice) / count($volSlice)) : max(1.0, $currVol);
        $rvol = $avgVol20 > 0 ? round($currVol / $avgVol20, 2) : 1.0;

        // 6. Evaluasi RSI 14
        $rsi = $this->calculateRsi($candles, 14);

        // 7. Evaluasi Follow-through Confirmation
        // Cek apakah candle saat ini merupakan follow-through dari candle sweep sebelumnya (prev),
        // ATAU candle saat ini sendiri yang melakukan sweep & reclaim kuat.
        $hasFollowThrough = false;
        $followThroughReason = '';
        if ($prev['low'] < $refPrice && $currClose > $prev['high']) {
            $hasFollowThrough = true;
            $followThroughReason = 'Candle close di atas High candle sweep kemarin';
        } elseif ($currClose > $currOpen && $currLow > $prev['low'] && $prev['low'] < $refPrice) {
            $hasFollowThrough = true;
            $followThroughReason = 'Higher Low & candle hijau reversal';
        }

        // =========================================================
        // HARD GATES & BREAKDOWN DETECTION
        // =========================================================
        $isBreakdown = false;
        $hardGateReason = null;

        if (!$hasPenetrated) {
            // Harga belum/tidak menyapu reference low
            $hardGateReason = 'Harga low tidak menembus reference low.';
            return $this->buildEmptyResult($hardGateReason, $refPrice, $refType, $rvol, $atr);
        }

        // Jika low menembus support namun Close MASIH di bawah support
        if (!$isReclaimed) {
            if ($penetrationPct > 2.5 || $clv < -0.3 || ($currClose < $currOpen && $lowerWickRatio < 0.25)) {
                $isBreakdown = true;
                $hardGateReason = 'Breakdown confirmed: Harga ditutup di bawah support dengan tekanan jual kuat.';
                $status = self::STATUS_BREAKDOWN;
                $statusLabel = 'Breakdown Danger';
                $badge = ['label' => 'Breakdown', 'class' => 'bg-danger text-white', 'desc' => 'Harga tembus support tanpa reclaim. Rawan kelanjutan bearish!'];
            } else {
                $hardGateReason = 'Pending Reclaim: Support tersapu namun candle belum berhasil close di atas support.';
                $status = self::STATUS_PENDING_RECLAIM;
                $statusLabel = 'Pending Reclaim';
                $badge = ['label' => 'Pending Reclaim', 'class' => 'bg-warning text-dark', 'desc' => 'Low menembus support tapi belum close di atas support. Tunggu konfirmasi.'];
            }

            return [
                'status' => $status,
                'statusLabel' => $statusLabel,
                'statusBadge' => $badge,
                'score' => 25,
                'scoreCategory' => 'Invalid / No Signal',
                'isSweep' => false,
                'isReclaimed' => false,
                'isBreakdown' => $isBreakdown,
                'referenceLow' => round($refPrice, 2),
                'referenceType' => $refType,
                'penetrationPct' => $penetrationPct,
                'reclaimPct' => 0.0,
                'lowerWickRatio' => $lowerWickRatio,
                'clv' => $clv,
                'rvol' => $rvol,
                'atr' => round($atr, 2),
                'entryTrigger' => round($currHigh * 1.002, 2),
                'stopLoss' => round($currLow - ($atr * 0.5), 2),
                'tp1' => round($refPrice + ($atr * 1.5), 2),
                'tp2' => round($refPrice + ($atr * 3.0), 2),
                'riskRewardRatio' => 0.0,
                'components' => [],
                'hardGateReason' => $hardGateReason,
                'details' => [
                    'allReferenceLows' => $refLows['all'],
                    'rsi' => round($rsi, 1),
                ],
            ];
        }

        // =========================================================
        // LIQUIDITY SWEEP SCORING (Total 100%)
        // =========================================================
        $compSupportQuality = 0;
        $compPenetration = 0;
        $compReclaim = 0;
        $compWickRejection = 0;
        $compVolume = 0;
        $compMomentum = 0;
        $compFollowThrough = 0;

        // 1. Support Quality (20%): Berdasarkan tipe reference low
        if (str_contains($refType, 'Swing Low') || str_contains($refType, 'Cluster')) {
            $compSupportQuality = 20;
        } elseif (str_contains($refType, 'Lowest Low 20D') || str_contains($refType, 'Weekly Low')) {
            $compSupportQuality = 18;
        } elseif (str_contains($refType, 'Lowest Low 10D')) {
            $compSupportQuality = 15;
        } else {
            $compSupportQuality = 12; // Lower BB / Prev Day Low
        }

        // 2. Penetration Quality (10%): Penetrasi ideal adalah 0.2% s/d 2.5% (sweep bersih, bukan crash)
        if ($penetrationPct >= 0.3 && $penetrationPct <= 2.2) {
            $compPenetration = 10;
        } elseif ($penetrationPct > 2.2 && $penetrationPct <= 4.0) {
            $compPenetration = 7;
        } elseif ($penetrationPct < 0.3) {
            $compPenetration = 5;
        } else {
            $compPenetration = 3;
        }

        // 3. Reclaim Strength (20%): Kekuatan penutupan di atas support
        if ($currClose > $refPrice * 1.008 && $currClose >= $currOpen) {
            $compReclaim = 20;
        } elseif ($currClose >= $refPrice && $currClose >= $currOpen) {
            $compReclaim = 16;
        } elseif ($currClose >= $refPrice) {
            $compReclaim = 12;
        }

        // 4. Wick Rejection (15%): Lower Wick Ratio minimal 0.40 & CLV positif
        if ($lowerWickRatio >= 0.45 && $clv >= 0.3) {
            $compWickRejection = 15;
        } elseif ($lowerWickRatio >= 0.40 && $clv >= 0.0) {
            $compWickRejection = 12;
        } elseif ($lowerWickRatio >= 0.30) {
            $compWickRejection = 8;
        } else {
            $compWickRejection = 4;
        }

        // 5. Volume Confirmation (15%): Lonjakan volume / absorption
        if ($rvol >= 2.0) {
            $compVolume = 15;
        } elseif ($rvol >= 1.5) {
            $compVolume = 12;
        } elseif ($rvol >= 1.1) {
            $compVolume = 9;
        } else {
            $compVolume = 5; // Valid jika struktur reclaim sangat bersih
        }

        // 6. RSI / Momentum Divergence (10%)
        if ($rsi <= 40.0) {
            $compMomentum = 10; // Oversold sweet-spot
        } elseif ($rsi <= 50.0) {
            $compMomentum = 8;
        } elseif ($rsi <= 60.0) {
            $compMomentum = 5;
        }

        // 7. Follow-through Confirmation (10%)
        if ($hasFollowThrough) {
            $compFollowThrough = 10;
        } elseif ($currClose >= $currOpen && $clv >= 0.4) {
            $compFollowThrough = 7;
        } else {
            $compFollowThrough = 4;
        }

        $totalScore = $compSupportQuality + $compPenetration + $compReclaim + $compWickRejection + $compVolume + $compMomentum + $compFollowThrough;
        $totalScore = min(100, max(0, $totalScore));

        // Klasifikasi Skor
        if ($totalScore >= 85) {
            $status = self::STATUS_HIGH_QUALITY_SWEEP;
            $statusLabel = 'High Quality Sweep';
            $scoreCategory = 'High Quality Sweep';
            $badge = ['label' => 'A+ Sweep', 'class' => 'bg-success text-white', 'desc' => 'High Quality Sweep: Penolakan wick panjang, reclaim solid, RVOL tinggi.'];
        } elseif ($totalScore >= 70) {
            $status = self::STATUS_CONFIRMED_SWEEP;
            $statusLabel = 'Confirmed Sweep';
            $scoreCategory = 'Confirmed Sweep';
            $badge = ['label' => 'Confirmed Sweep', 'class' => 'bg-primary text-white', 'desc' => 'Confirmed Sweep: Reclaim support terbukti dengan rejection buyer.'];
        } elseif ($totalScore >= 55) {
            $status = self::STATUS_POTENTIAL_SWEEP;
            $statusLabel = 'Potential Sweep';
            $scoreCategory = 'Potential Sweep';
            $badge = ['label' => 'Potential Sweep', 'class' => 'bg-info text-dark', 'desc' => 'Potential Sweep: Cukup terkonfirmasi namun perlu konfirmasi candle berikutnya.'];
        } elseif ($totalScore >= 40) {
            $status = self::STATUS_WEAK_SWEEP;
            $statusLabel = 'Weak Sweep';
            $scoreCategory = 'Weak Sweep';
            $badge = ['label' => 'Weak Sweep', 'class' => 'bg-secondary text-white', 'desc' => 'Weak Sweep: Volume atau wick rejection relatif lemah.'];
        } else {
            $status = self::STATUS_NONE;
            $statusLabel = 'Invalid / No Signal';
            $scoreCategory = 'Invalid / No Signal';
            $badge = ['label' => 'Invalid', 'class' => 'bg-light text-muted border', 'desc' => 'Skor di bawah 40. Tidak memenuhi syarat entry.'];
        }

        // Trade Plan Adaptif Berbasis ATR
        // Entry agresif: Close bar sweep; Entry konservatif: High bar sweep + 0.2%
        $entryTrigger = round($currHigh * 1.002, 2);
        // Stop Loss presisi: Di bawah Low sweep - 0.2 ATR buffer
        $stopLoss = round($currLow - ($atr * 0.2), 2);
        $risk = max(0.01, $entryTrigger - $stopLoss);

        $tp1 = round($entryTrigger + ($risk * 1.5), 2); // 1.5R
        $tp2 = round($entryTrigger + ($risk * 3.0), 2); // 3.0R
        $riskRewardRatio = round(($tp1 - $entryTrigger) / $risk, 2);

        return [
            'status' => $status,
            'statusLabel' => $statusLabel,
            'statusBadge' => $badge,
            'score' => $totalScore,
            'scoreCategory' => $scoreCategory,
            'isSweep' => in_array($status, [self::STATUS_HIGH_QUALITY_SWEEP, self::STATUS_CONFIRMED_SWEEP, self::STATUS_POTENTIAL_SWEEP], true),
            'isReclaimed' => true,
            'isBreakdown' => false,
            'referenceLow' => round($refPrice, 2),
            'referenceType' => $refType,
            'penetrationPct' => $penetrationPct,
            'reclaimPct' => $reclaimPct,
            'lowerWickRatio' => $lowerWickRatio,
            'clv' => $clv,
            'rvol' => $rvol,
            'atr' => round($atr, 2),
            'entryTrigger' => $entryTrigger,
            'stopLoss' => $stopLoss,
            'tp1' => $tp1,
            'tp2' => $tp2,
            'riskRewardRatio' => $riskRewardRatio,
            'components' => [
                'supportQuality' => $compSupportQuality,
                'penetration' => $compPenetration,
                'reclaimStrength' => $compReclaim,
                'wickRejection' => $compWickRejection,
                'volume' => $compVolume,
                'momentum' => $compMomentum,
                'followThrough' => $compFollowThrough,
            ],
            'hardGateReason' => null,
            'details' => [
                'hasFollowThrough' => $hasFollowThrough,
                'followThroughReason' => $followThroughReason,
                'allReferenceLows' => $refLows['all'],
                'rsi' => round($rsi, 1),
                'currOpen' => $currOpen,
                'currHigh' => $currHigh,
                'currLow' => $currLow,
                'currClose' => $currClose,
            ],
        ];
    }

    /**
     * Identifikasi cluster reference low terdekat sebelum bar saat ini.
     */
    private function identifyReferenceLows(array $pastBars, ?float $lowerBand = null): array
    {
        $lows = array_map(fn($b) => (float) $b['low'], $pastBars);
        $count = count($lows);

        $prevBarLow = $lows[$count - 1] ?? 0.0;
        $low10 = min(array_slice($lows, -10));
        $low20 = min(array_slice($lows, -20));

        // Cari swing low terdekat (local minima)
        $swingLows = [];
        for ($i = 2; $i < $count - 2; $i++) {
            if ($lows[$i] < $lows[$i - 1] && $lows[$i] < $lows[$i - 2] && $lows[$i] < $lows[$i + 1] && $lows[$i] < $lows[$i + 2]) {
                $swingLows[] = $lows[$i];
            }
        }
        $recentSwingLow = !empty($swingLows) ? end($swingLows) : $low20;

        $allRefs = [
            ['type' => 'Recent Swing Low', 'price' => $recentSwingLow],
            ['type' => 'Lowest Low 10D', 'price' => $low10],
            ['type' => 'Lowest Low 20D', 'price' => $low20],
            ['type' => 'Previous Day Low', 'price' => $prevBarLow],
        ];

        if ($lowerBand !== null && $lowerBand > 0) {
            $allRefs[] = ['type' => 'Lower Bollinger Band', 'price' => round($lowerBand, 2)];
        }

        // Tentukan Primary Reference Low (paling dekat di atas low bar saat ini atau swing low utama)
        $primary = [
            'type' => 'Recent Swing Low',
            'price' => $recentSwingLow,
        ];

        return [
            'primary' => $primary,
            'all' => $allRefs,
        ];
    }

    private function calculateAtr(array $candles, int $period = 14): float
    {
        $count = count($candles);
        if ($count < 2) {
            return 1.0;
        }
        $trValues = [];
        for ($i = 1; $i < $count; $i++) {
            $h = (float) $candles[$i]['high'];
            $l = (float) $candles[$i]['low'];
            $pc = (float) $candles[$i - 1]['close'];
            $tr = max($h - $l, abs($h - $pc), abs($l - $pc));
            $trValues[] = $tr;
        }
        $slice = array_slice($trValues, -$period);
        return !empty($slice) ? (array_sum($slice) / count($slice)) : 1.0;
    }

    private function calculateRsi(array $candles, int $period = 14): float
    {
        $closes = array_map(fn($c) => (float) $c['close'], $candles);
        $count = count($closes);
        if ($count <= $period) {
            return 50.0;
        }

        $gains = 0.0;
        $losses = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            if ($diff > 0) {
                $gains += $diff;
            } else {
                $losses += abs($diff);
            }
        }
        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        for ($i = $period + 1; $i < $count; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $currentGain = $diff > 0 ? $diff : 0.0;
            $currentLoss = $diff < 0 ? abs($diff) : 0.0;
            $avgGain = (($avgGain * ($period - 1)) + $currentGain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $currentLoss) / $period;
        }

        if ($avgLoss <= 0.00001) {
            return 100.0;
        }
        $rs = $avgGain / $avgLoss;
        return 100.0 - (100.0 / (1.0 + $rs));
    }

    private function buildEmptyResult(
        string $reason,
        float $refPrice = 0.0,
        string $refType = 'None',
        float $rvol = 1.0,
        float $atr = 1.0
    ): array {
        return [
            'status' => self::STATUS_NONE,
            'statusLabel' => 'No Sweep Signal',
            'statusBadge' => ['label' => 'Netral', 'class' => 'bg-light text-muted border', 'desc' => $reason],
            'score' => 0,
            'scoreCategory' => 'No Signal',
            'isSweep' => false,
            'isReclaimed' => false,
            'isBreakdown' => false,
            'referenceLow' => round($refPrice, 2),
            'referenceType' => $refType,
            'penetrationPct' => 0.0,
            'reclaimPct' => 0.0,
            'lowerWickRatio' => 0.0,
            'clv' => 0.0,
            'rvol' => $rvol,
            'atr' => round($atr, 2),
            'entryTrigger' => 0.0,
            'stopLoss' => 0.0,
            'tp1' => 0.0,
            'tp2' => 0.0,
            'riskRewardRatio' => 0.0,
            'components' => [],
            'hardGateReason' => $reason,
            'details' => [],
        ];
    }
}
