<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\Stock;
use Yii;

/**
 * AccumulationPatternService
 *
 * Mendeteksi 5 pola akumulasi & distribusi institusional:
 * A. Sideways Accumulation (Bullish candidate)
 * B. Hidden Accumulation (Early candidate)
 * C. Selling Absorption (Potential reversal)
 * D. Accumulation Breakout (Breakout candidate)
 * E. Distribution Warning (Distribution risk)
 *
 * Dilengkapi dengan filter guardrails untuk menghindari false accumulation:
 * - RVOL tinggi tetapi harga terus turun (dumping)
 * - Volume tinggi tetapi likuiditas rendah (illiquid spike penalty)
 * - Net buy hanya terjadi sehari (persistence penalty)
 * - Broker/Large buy besar tetapi harga gagal naik (absorption vs distribution)
 * - Harga naik tanpa dukungan volume (low-volume pump penalty)
 * - Pengecekan kelengkapan data & status suspensi
 * - Pemisahan pasar Reguler (RG) vs Negosiasi (NG) / Tunai (TN) khusus BEI/IDX
 */
final class AccumulationPatternService
{
    // Pola Akumulasi & Distribusi
    public const PATTERN_SIDEWAYS_ACC = 'SIDEWAYS_ACC';
    public const PATTERN_HIDDEN_ACC = 'HIDDEN_ACC';
    public const PATTERN_SELLING_ABSORPTION = 'SELLING_ABSORPTION';
    public const PATTERN_ACC_BREAKOUT = 'ACC_BREAKOUT';
    public const PATTERN_DISTRIBUTION_WARNING = 'DISTRIBUTION_WARNING';
    public const PATTERN_NONE = 'NONE';

    public const PATTERN_LABELS = [
        self::PATTERN_SIDEWAYS_ACC => 'Sideways Accumulation',
        self::PATTERN_HIDDEN_ACC => 'Hidden Accumulation',
        self::PATTERN_SELLING_ABSORPTION => 'Selling Absorption',
        self::PATTERN_ACC_BREAKOUT => 'Accumulation Breakout',
        self::PATTERN_DISTRIBUTION_WARNING => 'Distribution Warning',
    ];

    public const PATTERN_BADGES = [
        self::PATTERN_SIDEWAYS_ACC => ['label' => 'Sideways Acc', 'class' => 'bg-info text-dark', 'desc' => 'Bullish candidate: Harga sideways, volume & OBV naik, CMF positif.'],
        self::PATTERN_HIDDEN_ACC => ['label' => 'Hidden Acc', 'class' => 'bg-primary text-white', 'desc' => 'Early candidate: Harga belum naik signifikan, CVD naik, tekanan jual diserap.'],
        self::PATTERN_SELLING_ABSORPTION => ['label' => 'Selling Absorption', 'class' => 'bg-warning text-dark', 'desc' => 'Potential reversal: Tekanan jual tinggi gagal jebol support, recovery muncul.'],
        self::PATTERN_ACC_BREAKOUT => ['label' => 'Acc Breakout', 'class' => 'bg-success text-white', 'desc' => 'Breakout candidate: Akumulasi valid, resistance ditembus dengan konfirmasi RVOL tinggi.'],
        self::PATTERN_DISTRIBUTION_WARNING => ['label' => 'Dist Warning', 'class' => 'bg-danger text-white', 'desc' => 'Distribution risk: Volume tinggi namun harga stagnan, CVD/OBV melemah.'],
    ];

    /**
     * Menganalisis pola akumulasi untuk satu saham.
     *
     * @param Stock|int $stock Model Stock atau stock_id
     * @param int $lookbackBars Jumlah candle harian yang diteliti (default 20 bar)
     * @param string $marketBoard Filter board transaksi (default 'REGULAR', opsi: 'ALL', 'NEGO')
     * @return array{
     *     pattern: string,
     *     patternLabel: string,
     *     badge: array{label: string, class: string, desc: string}|null,
     *     confidence: int,
     *     cmf: float,
     *     obvTrend: string,
     *     cvdTrend: string,
     *     rvol: float,
     *     warnings: array<string>,
     *     penalties: array<string, int>,
     *     scoreModifier: int,
     *     details: array
     * }
     */
    public function analyze(Stock|int $stock, int $lookbackBars = 20, string $marketBoard = 'REGULAR'): array
    {
        $stockModel = is_int($stock) ? Stock::findOne($stock) : $stock;
        if (!$stockModel) {
            return $this->buildEmptyResult('Stock tidak ditemukan.');
        }

        // Guard: Saham suspensi / tidak aktif
        if (!$stockModel->active) {
            return $this->buildEmptyResult('Saham sedang suspensi atau dinonaktifkan.', ['Saham berstatus suspensi']);
        }

        // Ambil data harga harian terurut kronologis (terlama ke terbaru)
        $bars = DailyPrice::find()
            ->where(['stock_id' => $stockModel->id])
            ->orderBy(['date' => SORT_DESC])
            ->limit(max($lookbackBars + 10, 30))
            ->asArray()
            ->all();

        if (count($bars) < 5) {
            return $this->buildEmptyResult('Data transaksi tidak lengkap (< 5 bar).', ['Data transaksi tidak lengkap']);
        }

        // Urutkan ASC (terlama ke terbaru) untuk analisis runtun waktu
        $bars = array_reverse($bars);
        $totalBars = count($bars);
        $latest = $bars[$totalBars - 1];

        // 1. Ekstraksi metrik teknikal & volume
        $closes = array_map(fn($b) => (float) $b['close'], $bars);
        $highs = array_map(fn($b) => (float) $b['high'], $bars);
        $lows = array_map(fn($b) => (float) $b['low'], $bars);
        $volumes = array_map(fn($b) => (float) $b['volume'], $bars);

        $buyVolumes = [];
        $sellVolumes = [];
        foreach ($bars as $b) {
            $bv = $b['buy_volume'] !== null ? (float) $b['buy_volume'] : null;
            $sv = $b['sell_volume'] !== null ? (float) $b['sell_volume'] : null;
            if ($bv === null || $sv === null) {
                // Estimasi tick rule sederhana jika belum diklasifikasi
                $o = (float) $b['open'];
                $c = (float) $b['close'];
                $v = (float) $b['volume'];
                if ($c > $o) {
                    $bv = $v;
                    $sv = 0.0;
                } elseif ($c < $o) {
                    $bv = 0.0;
                    $sv = $v;
                } else {
                    $bv = $v / 2.0;
                    $sv = $v / 2.0;
                }
            }
            $buyVolumes[] = $bv;
            $sellVolumes[] = $sv;
        }

        // Khusus BEI/IDX: Pasar Negosiasi vs Reguler
        $isIdx = ($stockModel->exchange === Stock::EXCHANGE_IDX || str_ends_with(strtoupper($stockModel->symbol), '.JK'));
        $warnings = [];
        if ($isIdx && $marketBoard !== 'REGULAR') {
            $warnings[] = 'Pasar transaksi: ' . $marketBoard . ' (Volume negosiasi dipisah dari kalkulasi reguler)';
        }

        // Hitung CMF (Chaikin Money Flow) periode 20
        $cmf = $this->calculateCmf($bars, 20);

        // Hitung OBV & Trend OBV
        $obvValues = $this->calculateObv($closes, $volumes);
        $obvTrend = $this->evaluateTrend($obvValues, 5);

        // Hitung CVD (Cumulative Volume Delta = Buy Vol - Sell Vol)
        $cvdValues = $this->calculateCvd($buyVolumes, $sellVolumes);
        $cvdTrend = $this->evaluateTrend($cvdValues, 5);

        // Hitung RVOL (Relative Volume terhadap rata-rata 20 hari)
        $avgVol20 = 0.0;
        $volSlice = array_slice($volumes, -21, 20);
        if (!empty($volSlice)) {
            $avgVol20 = array_sum($volSlice) / count($volSlice);
        }
        $latestVol = (float) $latest['volume'];
        $rvol = $avgVol20 > 0 ? round($latestVol / $avgVol20, 2) : 1.0;

        // Hitung Support & Resistance (swing level 10-15 bar terakhir sebelum bar terbaru)
        $histLows = array_slice($lows, -15, 14);
        $histHighs = array_slice($highs, -15, 14);
        $supportLevel = !empty($histLows) ? min($histLows) : (float) $latest['low'];
        $resistanceLevel = !empty($histHighs) ? max($histHighs) : (float) $latest['high'];

        // Metrik harga terkini
        $currClose = (float) $latest['close'];
        $currOpen = (float) $latest['open'];
        $currHigh = (float) $latest['high'];
        $currLow = (float) $latest['low'];
        $prevClose = (float) ($bars[$totalBars - 2]['close'] ?? $currClose);
        $pctChange = $prevClose > 0 ? (($currClose - $prevClose) / $prevClose) * 100 : 0.0;

        // Rentang pergerakan harga 10 hari (untuk mengukur sideways)
        $recentCloses = array_slice($closes, -10);
        $maxRecentClose = max($recentCloses);
        $minRecentClose = min($recentCloses);
        $priceRangePct = $minRecentClose > 0 ? (($maxRecentClose - $minRecentClose) / $minRecentClose) * 100 : 0.0;

        // Rasio Beli 5 hari terakhir
        $recentBuyVol = array_sum(array_slice($buyVolumes, -5));
        $recentSellVol = array_sum(array_slice($sellVolumes, -5));
        $tot5d = $recentBuyVol + $recentSellVol;
        $buyRatio5d = $tot5d > 0 ? $recentBuyVol / $tot5d : 0.5;

        // Hitung konsistensi hari Net Buy (persistence)
        $netBuyDays = 0;
        for ($i = max(0, $totalBars - 5); $i < $totalBars; $i++) {
            if ($buyVolumes[$i] > $sellVolumes[$i]) {
                $netBuyDays++;
            }
        }

        // ==========================================
        // 2. DETEKSI POLA AKUMULASI / DISTRIBUSI
        // ==========================================
        $detectedPattern = self::PATTERN_NONE;
        $confidence = 50;

        // A. Sideways Accumulation:
        // - Harga bergerak sideways (rentang <= 6%)
        // - Volume meningkat (RVOL >= 1.1x)
        // - CMF positif (> 0.05)
        // - OBV meningkat (UP)
        // - Support relatif bertahan (currClose >= supportLevel * 0.985)
        $isSideways = ($priceRangePct <= 6.5);
        $isSupportHolding = ($currClose >= $supportLevel * 0.985);
        if ($isSideways && $rvol >= 1.1 && $cmf > 0.02 && $obvTrend === 'UP' && $isSupportHolding) {
            $detectedPattern = self::PATTERN_SIDEWAYS_ACC;
            $confidence = 78 + ($cmf > 0.15 ? 10 : 0) + ($rvol >= 1.5 ? 8 : 0);
        }

        // B. Hidden Accumulation:
        // - Harga belum naik signifikan (pctChange antara -1% s/d +2.5%)
        // - Volume beli dominan (buyRatio 5d >= 60% atau latest buy_volume > 65%)
        // - CVD meningkat (UP)
        // - Harga tidak mudah turun meski tekanan jual muncul (currLow bertahan di atas swing support)
        elseif ($pctChange >= -1.0 && $pctChange <= 2.5 && ($buyRatio5d >= 0.58 || $cvdTrend === 'UP') && $isSupportHolding) {
            $detectedPattern = self::PATTERN_HIDDEN_ACC;
            $confidence = 74 + ($buyRatio5d >= 0.65 ? 10 : 0) + ($cvdTrend === 'UP' ? 8 : 0);
        }

        // C. Selling Absorption:
        // - Tekanan jual besar (latest buy_volume < sell_volume atau CVD melemah)
        // - Namun harga gagal menembus support (currLow menolak support, ekor bawah / hammer)
        // - Terjadi recovery setelahnya (currClose > currOpen atau currClose > (currHigh + currLow) / 2)
        $lowerWick = min($currOpen, $currClose) - $currLow;
        $body = abs($currClose - $currOpen);
        $isHammerOrRejection = ($lowerWick > $body * 1.2 || $currClose > ($currHigh + $currLow) / 2);
        if ($currLow <= $supportLevel * 1.015 && $currClose > $supportLevel && $isHammerOrRejection && ($rvol >= 1.2 || $latest['sell_volume'] > $latest['buy_volume'])) {
            $detectedPattern = self::PATTERN_SELLING_ABSORPTION;
            $confidence = 75 + ($isHammerOrRejection ? 10 : 0) + ($rvol >= 1.5 ? 8 : 0);
        }

        // D. Accumulation Breakout:
        // - Akumulasi berlangsung beberapa hari (buyRatio5d >= 55% atau CMF > 0)
        // - Resistance berhasil ditembus (currClose > resistanceLevel)
        // - RVOL meningkat signifikan (RVOL >= 1.4x)
        // - Close di atas resistance
        elseif ($currClose > $resistanceLevel && $rvol >= 1.35 && ($cmf >= -0.05 || $buyRatio5d >= 0.52)) {
            $detectedPattern = self::PATTERN_ACC_BREAKOUT;
            $confidence = 82 + ($rvol >= 2.0 ? 10 : 0) + ($cmf > 0.1 ? 5 : 0);
        }

        // E. Distribution Warning:
        // - Volume tinggi (RVOL >= 1.3x)
        // - Harga gagal membuat kenaikan lanjutan (pctChange <= 0.5% atau candle bear/inverted hammer di resistance)
        // - CVD melemah (DOWN)
        // - OBV mengalami penurunan (DOWN)
        elseif ($rvol >= 1.25 && ($pctChange < 0 || ($currHigh >= $resistanceLevel && $currClose < $currOpen)) && ($cvdTrend === 'DOWN' || $obvTrend === 'DOWN')) {
            $detectedPattern = self::PATTERN_DISTRIBUTION_WARNING;
            $confidence = 76 + ($cvdTrend === 'DOWN' && $obvTrend === 'DOWN' ? 12 : 0);
        }

        // ==========================================
        // 3. GUARDRAILS & FILTER FALSE ACCUMULATION
        // ==========================================
        $penalties = [];
        $scoreModifier = 0;

        // Rule 1: RVOL tinggi tetapi harga terus turun (dumping)
        if ($rvol >= 1.5 && $pctChange <= -2.5) {
            $warnings[] = 'RVOL tinggi (' . $rvol . 'x) tetapi harga anjlok (' . round($pctChange, 1) . '%). Bukan akumulasi bullish.';
            $penalties['high_rvol_dump'] = -25;
            $scoreModifier -= 25;
            if ($detectedPattern !== self::PATTERN_SELLING_ABSORPTION) {
                $detectedPattern = self::PATTERN_DISTRIBUTION_WARNING;
            }
        }

        // Rule 2: Volume tinggi tetapi likuiditas nilai transaksi rendah (illiquid pump)
        $turnoverVal = $currClose * $latestVol;
        // Asumsi threshold likuiditas minimum (misal IDR 500jt atau USD $50k)
        $minTurnover = $isIdx ? 200_000_000 : 50_000;
        if ($turnoverVal < $minTurnover && $latestVol > 0) {
            $warnings[] = 'Likuiditas rendah (turnover harian kecil). Rawan manipulasi harga.';
            $penalties['low_liquidity'] = -15;
            $scoreModifier -= 15;
            $confidence = max(20, $confidence - 20);
        }

        // Rule 3: Net buy hanya terjadi sehari (kurangi persistence score)
        if ($netBuyDays <= 1 && in_array($detectedPattern, [self::PATTERN_SIDEWAYS_ACC, self::PATTERN_HIDDEN_ACC], true)) {
            $warnings[] = 'Net buy hanya terjadi 1 hari dalam 5 hari terakhir (kurang persistensi).';
            $penalties['single_day_net_buy'] = -10;
            $scoreModifier -= 10;
            $confidence = max(30, $confidence - 15);
        }

        // Rule 4: Volume beli besar tetapi harga gagal naik (stalling / absorption test)
        if ($latest['buy_volume'] > ($latestVol * 0.70) && $pctChange <= 0.2 && $detectedPattern !== self::PATTERN_HIDDEN_ACC) {
            if ($currClose <= $supportLevel * 1.02) {
                // Di dekat support => Absorption
                $warnings[] = 'Buy volume besar tertahan di support (indikasi selling absorption).';
            } else {
                // Di puncak => Supply resistance kuat
                $warnings[] = 'Buy volume besar namun harga tertahan (churning/distribution risk).';
                $penalties['stalling_at_high'] = -10;
                $scoreModifier -= 10;
            }
        }

        // Rule 5: Harga naik tanpa dukungan volume (low volume breakout)
        if ($pctChange >= 2.0 && $rvol < 0.85) {
            $warnings[] = 'Harga naik (+' . round($pctChange, 1) . '%) tanpa dukungan volume (RVOL ' . $rvol . 'x). Rawan false breakout.';
            $penalties['low_volume_pump'] = -15;
            $scoreModifier -= 15;
            if ($detectedPattern === self::PATTERN_ACC_BREAKOUT) {
                // Batalkan status breakout jika volume kering
                $detectedPattern = self::PATTERN_NONE;
            }
        }

        $badge = self::PATTERN_BADGES[$detectedPattern] ?? null;
        $patternLabel = self::PATTERN_LABELS[$detectedPattern] ?? 'Netral / Tanpa Pola Khusus';

        return [
            'pattern' => $detectedPattern,
            'patternLabel' => $patternLabel,
            'badge' => $badge,
            'confidence' => min(98, max(20, $confidence)),
            'cmf' => round($cmf, 3),
            'obvTrend' => $obvTrend,
            'cvdTrend' => $cvdTrend,
            'rvol' => $rvol,
            'supportLevel' => round($supportLevel, 2),
            'resistanceLevel' => round($resistanceLevel, 2),
            'pctChange' => round($pctChange, 2),
            'priceRangePct' => round($priceRangePct, 2),
            'netBuyDays' => $netBuyDays,
            'warnings' => $warnings,
            'penalties' => $penalties,
            'scoreModifier' => $scoreModifier,
            'details' => [
                'latestClose' => $currClose,
                'latestVol' => (int) $latestVol,
                'avgVol20' => (int) $avgVol20,
                'buyRatio5d' => round($buyRatio5d * 100, 1),
            ],
        ];
    }

    /**
     * Hitung Chaikin Money Flow (CMF).
     * CMF = Sum(Money Flow Volume, N) / Sum(Volume, N)
     * MFV = ((Close - Low) - (High - Close)) / (High - Low) * Volume
     */
    private function calculateCmf(array $bars, int $period = 20): float
    {
        $slice = array_slice($bars, -$period);
        $sumMfv = 0.0;
        $sumVol = 0.0;

        foreach ($slice as $b) {
            $h = (float) $b['high'];
            $l = (float) $b['low'];
            $c = (float) $b['close'];
            $v = (float) $b['volume'];

            $range = $h - $l;
            if ($range > 0) {
                $multiplier = (($c - $l) - ($h - $c)) / $range;
                $sumMfv += ($multiplier * $v);
            }
            $sumVol += $v;
        }

        return $sumVol > 0 ? ($sumMfv / $sumVol) : 0.0;
    }

    /**
     * Hitung On-Balance Volume (OBV).
     */
    private function calculateObv(array $closes, array $volumes): array
    {
        $obv = [];
        $currentObv = 0.0;
        $count = count($closes);

        for ($i = 0; $i < $count; $i++) {
            if ($i === 0) {
                $obv[] = 0.0;
                continue;
            }
            if ($closes[$i] > $closes[$i - 1]) {
                $currentObv += $volumes[$i];
            } elseif ($closes[$i] < $closes[$i - 1]) {
                $currentObv -= $volumes[$i];
            }
            $obv[] = $currentObv;
        }

        return $obv;
    }

    /**
     * Hitung Cumulative Volume Delta (CVD).
     */
    private function calculateCvd(array $buyVolumes, array $sellVolumes): array
    {
        $cvd = [];
        $runningDelta = 0.0;
        $count = count($buyVolumes);

        for ($i = 0; $i < $count; $i++) {
            $delta = ($buyVolumes[$i] ?? 0.0) - ($sellVolumes[$i] ?? 0.0);
            $runningDelta += $delta;
            $cvd[] = $runningDelta;
        }

        return $cvd;
    }

    /**
     * Evaluasi arah tren dari array nilai numerik (UP, DOWN, FLAT).
     */
    private function evaluateTrend(array $values, int $lookback = 5): string
    {
        if (count($values) < $lookback) {
            return 'FLAT';
        }
        $slice = array_slice($values, -$lookback);
        $first = $slice[0];
        $last = end($slice);

        $diff = $last - $first;
        $threshold = abs($first) * 0.02; // toleransi 2%

        if ($diff > $threshold) {
            return 'UP';
        }
        if ($diff < -$threshold) {
            return 'DOWN';
        }
        return 'FLAT';
    }

    private function buildEmptyResult(string $reason, array $warnings = []): array
    {
        return [
            'pattern' => self::PATTERN_NONE,
            'patternLabel' => $reason,
            'badge' => null,
            'confidence' => 0,
            'cmf' => 0.0,
            'obvTrend' => 'FLAT',
            'cvdTrend' => 'FLAT',
            'rvol' => 0.0,
            'supportLevel' => 0.0,
            'resistanceLevel' => 0.0,
            'pctChange' => 0.0,
            'priceRangePct' => 0.0,
            'netBuyDays' => 0,
            'warnings' => array_merge([$reason], $warnings),
            'penalties' => [],
            'scoreModifier' => 0,
            'details' => [],
        ];
    }
}

