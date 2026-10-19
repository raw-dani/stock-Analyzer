<?php

declare(strict_types=1);

namespace app\services\crypto;

use app\models\CryptoAsset;
use app\models\CryptoPrice;
use yii\base\Component;
use Yii;

/**
 * Service Deteksi Pola Bullish Continuation & Breakout untuk Crypto (24/7):
 * 1. Cup and Handle (Cangkir & Pegangan)
 * 2. Bullish Flag (Bendera Bullish)
 * 3. Bullish Pennant (Pennant Bullish)
 * 4. Ascending Triangle (Segitiga Naik)
 * 5. Bullish Rectangle (Darvas Box Breakout)
 *
 * Dilengkapi konfirmasi Real Taker Buy Volume Binance, deteksi False Breakout (Bull Trap),
 * dan kalkulasi target profit TP1, TP2, Stop Loss adaptif, dan Risk-to-Reward Ratio.
 */
class CryptoBullishPatternService extends Component
{
    public const PATTERN_ALL = 'ALL';
    public const PATTERN_CUP_AND_HANDLE = 'CUP_AND_HANDLE';
    public const PATTERN_BULLISH_FLAG = 'BULLISH_FLAG';
    public const PATTERN_BULLISH_PENNANT = 'BULLISH_PENNANT';
    public const PATTERN_ASCENDING_TRIANGLE = 'ASCENDING_TRIANGLE';
    public const PATTERN_BULLISH_RECTANGLE = 'BULLISH_RECTANGLE';

    public const STATUS_BUY_ZONE = 'buy_zone';
    public const STATUS_RETEST = 'retest';
    public const STATUS_CONSOLIDATION_BOUNCE = 'consolidation_bounce';
    public const STATUS_FORMING = 'forming';
    public const STATUS_EXTENDED = 'extended';
    public const STATUS_BULL_TRAP = 'bull_trap';

    public static function getPatternTypes(): array
    {
        return [
            self::PATTERN_ALL => 'Semua Pola Bullish',
            self::PATTERN_CUP_AND_HANDLE => '☕ Cup and Handle',
            self::PATTERN_BULLISH_FLAG => '🚩 Bullish Flag',
            self::PATTERN_BULLISH_PENNANT => '📐 Bullish Pennant',
            self::PATTERN_ASCENDING_TRIANGLE => '🔺 Ascending Triangle',
            self::PATTERN_BULLISH_RECTANGLE => '📦 Bullish Rectangle (Darvas Box)',
        ];
    }

    public static function getStatusOptions(): array
    {
        return [
            '' => 'Semua Status Eksekusi',
            self::STATUS_BUY_ZONE => '🎯 Buy Zone (Breakout Segar + Volume)',
            self::STATUS_RETEST => '🔄 Retest (Pullback ke Level Breakout)',
            self::STATUS_CONSOLIDATION_BOUNCE => '⚡ Bounce dari Support Pola',
            self::STATUS_FORMING => '⏳ Sedang Membentuk (Mendekati Resisten)',
            self::STATUS_EXTENDED => '🚀 Extended (Sudah Terbang > 3.5%)',
            self::STATUS_BULL_TRAP => '⚠️ Bull Trap (Breakout Gagal / Rejection)',
        ];
    }

    /**
     * Scan seluruh koin crypto aktif untuk mendeteksi pola Bullish
     */
    public function scanAll(array $options = []): array
    {
        $timeframe = $options['timeframe'] ?? CryptoPrice::TIMEFRAME_1H;
        if (!array_key_exists($timeframe, CryptoPrice::SUPPORTED_TIMEFRAMES)) {
            $timeframe = CryptoPrice::TIMEFRAME_1H;
        }

        $patternType = (string)($options['patternType'] ?? self::PATTERN_ALL);
        $statusFilter = (string)($options['status'] ?? '');
        $minConfidence = (int)($options['minConfidence'] ?? 0);
        $minRr = (float)($options['minRr'] ?? 0.0);
        $search = strtoupper(trim((string)($options['search'] ?? '')));

        $coins = CryptoAsset::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC])
            ->all();

        $results = [];

        foreach ($coins as $coin) {
            if ($search !== '') {
                if (!str_contains(strtoupper($coin->symbol), $search) && !str_contains(strtoupper($coin->name), $search)) {
                    continue;
                }
            }

            $patterns = $this->detectPatterns($coin, $timeframe);
            if (empty($patterns)) {
                continue;
            }

            foreach ($patterns as $p) {
                if ($patternType !== self::PATTERN_ALL && $p['pattern_type'] !== $patternType) {
                    continue;
                }
                if ($statusFilter !== '' && $p['trade_status'] !== $statusFilter) {
                    continue;
                }
                if ($minConfidence > 0 && ($p['confidence'] ?? 0) < $minConfidence) {
                    continue;
                }
                if ($minRr > 0.0 && ($p['rr_ratio'] ?? 0.0) < $minRr) {
                    continue;
                }

                $results[] = $p;
            }
        }

        return $results;
    }

    /**
     * Deteksi pola-pola bullish pada satu koin crypto dengan filter presisi
     */
    public function detectPatterns(CryptoAsset $asset, string $timeframe = '1h'): array
    {
        /** @var CryptoPrice[] $bars */
        $bars = CryptoPrice::find()
            ->where(['crypto_id' => $asset->id, 'timeframe' => $timeframe])
            ->orderBy(['bar_time' => SORT_ASC])
            ->limit(200)
            ->all();

        if (count($bars) < 50) {
            $analyzer = new CryptoAnalyzerService();
            $analyzer->init();
            $analyzer->syncCoinBars($asset, $timeframe, 200);

            $bars = CryptoPrice::find()
                ->where(['crypto_id' => $asset->id, 'timeframe' => $timeframe])
                ->orderBy(['bar_time' => SORT_ASC])
                ->limit(200)
                ->all();
        }

        $n = count($bars);
        if ($n < 35) {
            return [];
        }

        $opens = [];
        $closes = [];
        $highs = [];
        $lows = [];
        $volumes = [];
        $buyVolumes = [];
        $dates = [];

        foreach ($bars as $b) {
            $opens[] = (float)$b->open;
            $closes[] = (float)$b->close;
            $highs[] = (float)$b->high;
            $lows[] = (float)$b->low;
            $volumes[] = (float)$b->volume;
            $buyVolumes[] = (float)$b->buy_volume;
            $dates[] = $b->bar_time;
        }

        $currIdx = $n - 1;
        $currOpen = $opens[$currIdx];
        $currClose = $closes[$currIdx];
        $currHigh = $highs[$currIdx];
        $currLow = $lows[$currIdx];
        $currVol = $volumes[$currIdx];
        $currBuyVol = $buyVolumes[$currIdx];

        $buyRatio = $currVol > 0 ? round(($currBuyVol / $currVol) * 100, 2) : 50.0;
        $volSlice = array_slice($volumes, -20);
        $avgVol = count($volSlice) > 0 ? (array_sum($volSlice) / count($volSlice)) : 1.0;
        $rvol = $avgVol > 0 ? round($currVol / $avgVol, 2) : 1.0;

        // Indikator Konteks Tren & Volatilitas
        $sma50 = $this->calculateSma($closes, 50);
        $atrSeries = $this->calculateAtr($highs, $lows, $closes, 14);
        $currAtr = !empty($atrSeries) ? end($atrSeries) : ($currClose * 0.02);

        $patterns = [];

        // 1. Cek Ascending Triangle (Ceiling datar + Higher Lows)
        $tri = $this->checkAscendingTriangle($closes, $highs, $lows, $n);
        if ($tri !== null) {
            $patterns[] = $this->buildPatternPayload($asset, $timeframe, self::PATTERN_ASCENDING_TRIANGLE, $tri, $currClose, $currOpen, $currHigh, $currLow, $buyRatio, $rvol, $currAtr, $sma50);
        }

        // 2. Cek Bullish Flag (Pole impulsif + Volume Dry-Up konsolidasi)
        $flag = $this->checkBullishFlag($closes, $highs, $lows, $volumes, $n);
        if ($flag !== null) {
            $patterns[] = $this->buildPatternPayload($asset, $timeframe, self::PATTERN_BULLISH_FLAG, $flag, $currClose, $currOpen, $currHigh, $currLow, $buyRatio, $rvol, $currAtr, $sma50);
        }

        // 3. Cek Bullish Pennant (Pole impulsif + Segitiga simetris menyempit)
        $pennant = $this->checkBullishPennant($closes, $highs, $lows, $volumes, $n);
        if ($pennant !== null) {
            $patterns[] = $this->buildPatternPayload($asset, $timeframe, self::PATTERN_BULLISH_PENNANT, $pennant, $currClose, $currOpen, $currHigh, $currLow, $buyRatio, $rvol, $currAtr, $sma50);
        }

        // 4. Cek Cup and Handle (Dasar mangkok U-shape + Handle tertib)
        $cup = $this->checkCupAndHandle($closes, $highs, $lows, $volumes, $n);
        if ($cup !== null) {
            $patterns[] = $this->buildPatternPayload($asset, $timeframe, self::PATTERN_CUP_AND_HANDLE, $cup, $currClose, $currOpen, $currHigh, $currLow, $buyRatio, $rvol, $currAtr, $sma50);
        }

        // 5. Cek Bullish Rectangle (Darvas Box pasca impulsive entry)
        $box = $this->checkBullishRectangle($closes, $highs, $lows, $n);
        if ($box !== null) {
            $patterns[] = $this->buildPatternPayload($asset, $timeframe, self::PATTERN_BULLISH_RECTANGLE, $box, $currClose, $currOpen, $currHigh, $currLow, $buyRatio, $rvol, $currAtr, $sma50);
        }

        return $patterns;
    }

    private function checkAscendingTriangle(array $closes, array $highs, array $lows, int $n): ?array
    {
        $lookback = min(45, $n);
        $recentHighs = array_slice($highs, -$lookback);
        $recentLows = array_slice($lows, -$lookback);

        $maxHigh = max($recentHighs);
        // Hitung pengujian resisten datar (jarak minimal 3 bar antar sentuhan)
        $touches = 0;
        $lastTouchIdx = -99;
        foreach ($recentHighs as $idx => $h) {
            if ($h >= $maxHigh * 0.988) {
                if ($idx - $lastTouchIdx >= 3) {
                    $touches++;
                    $lastTouchIdx = $idx;
                }
            }
        }
        if ($touches < 2) {
            return null;
        }

        // Cek low membuat struktur Higher Lows bertingkat
        $third = (int)($lookback / 3);
        $low1 = min(array_slice($recentLows, 0, $third));
        $low2 = min(array_slice($recentLows, $third, $third));
        $low3 = min(array_slice($recentLows, $third * 2));

        if ($low2 >= $low1 * 1.008 && $low3 >= $low2 * 1.008) {
            $patternHeight = $maxHigh - $low1;
            return [
                'breakout_level' => $maxHigh,
                'support_level' => $low3,
                'pattern_height' => $patternHeight,
                'description' => "Resistensi horizontal teruji {$touches}x dengan Higher Lows solid (+ " . round((($low3 - $low1) / $low1) * 100, 1) . "%).",
            ];
        }

        return null;
    }

    private function checkBullishFlag(array $closes, array $highs, array $lows, array $volumes, int $n): ?array
    {
        $lookback = min(40, $n);
        $sliceCloses = array_slice($closes, -$lookback);
        $sliceVols = array_slice($volumes, -$lookback);
        $sliceLows = array_slice($lows, -$lookback);
        $subCount = count($sliceCloses);

        // Cari tiang bendera (flag pole): lonjakan minimal 7% di awal segmen
        $poleStart = $sliceCloses[0];
        $poleTopIdx = 0;
        $poleTop = $poleStart;

        for ($i = 5; $i < min(20, $subCount - 4); $i++) {
            if ($sliceCloses[$i] > $poleTop) {
                $poleTop = $sliceCloses[$i];
                $poleTopIdx = $i;
            }
        }

        $poleGain = ($poleTop - $poleStart) / max(0.000001, $poleStart);
        if ($poleGain < 0.07) {
            return null; // Minimal kenaikan pole 7% untuk crypto
        }

        // Konsolidasi flag
        $flagLows = array_slice($sliceLows, $poleTopIdx);
        $flagMin = min($flagLows);

        // Retracement tidak boleh melebihi 45% dari pole height
        $poleHeight = $poleTop - $poleStart;
        if (($poleTop - $flagMin) > ($poleHeight * 0.45)) {
            return null;
        }

        // Validasi Volume Dry-Up: Rata-rata volume saat konsolidasi harus lebih kecil dibanding saat pole
        $poleVols = array_slice($sliceVols, 0, $poleTopIdx + 1);
        $flagVols = array_slice($sliceVols, $poleTopIdx + 1);
        $avgPoleVol = count($poleVols) > 0 ? (array_sum($poleVols) / count($poleVols)) : 1.0;
        $avgFlagVol = count($flagVols) > 0 ? (array_sum($flagVols) / count($flagVols)) : 1.0;

        $volumeDryUp = ($avgFlagVol <= $avgPoleVol * 0.90);

        return [
            'breakout_level' => $poleTop,
            'support_level' => $flagMin,
            'pattern_height' => $poleHeight,
            'volume_dry_up' => $volumeDryUp,
            'description' => 'Flagpole menguat +' . round($poleGain * 100, 1) . '% ' . ($volumeDryUp ? 'dengan volume dry-up konsolidasi sehat' : 'konsolidasi tertib') . '.',
        ];
    }

    private function checkBullishPennant(array $closes, array $highs, array $lows, array $volumes, int $n): ?array
    {
        $lookback = min(35, $n);
        $slice = array_slice($closes, -$lookback);
        $subCount = count($slice);

        $poleStart = $slice[0];
        $poleTop = max(array_slice($slice, 0, 14));
        $gain = ($poleTop - $poleStart) / max(0.000001, $poleStart);
        if ($gain < 0.06) {
            return null;
        }

        // Konsolidasi simetris: high makin rendah, low makin tinggi
        $recentHighs = array_slice($highs, -14);
        $recentLows = array_slice($lows, -14);
        $h1 = max(array_slice($recentHighs, 0, 7));
        $h2 = max(array_slice($recentHighs, 7));
        $l1 = min(array_slice($recentLows, 0, 7));
        $l2 = min(array_slice($recentLows, 7));

        $range1 = $h1 - $l1;
        $range2 = $h2 - $l2;

        if ($h2 <= $h1 && $l2 >= $l1 && $range2 < $range1 * 0.75) {
            return [
                'breakout_level' => $h1,
                'support_level' => $l2,
                'pattern_height' => $poleTop - $poleStart,
                'description' => 'Pennant simetris menyempit ' . round((1 - ($range2 / max(0.000001, $range1))) * 100) . '% pasca kenaikan +' . round($gain * 100, 1) . '%.',
            ];
        }

        return null;
    }

    private function checkCupAndHandle(array $closes, array $highs, array $lows, array $volumes, int $n): ?array
    {
        if ($n < 50) {
            return null;
        }

        $lookback = min(65, $n);
        $slice = array_slice($closes, -$lookback);

        $leftRim = max(array_slice($slice, 0, 18));
        $cupBottom = min(array_slice($slice, 18, 30));
        $rightRim = max(array_slice($slice, 32, 22));

        $cupDepth = ($leftRim - $cupBottom) / max(0.000001, $leftRim);
        if ($cupDepth < 0.08 || $cupDepth > 0.35) {
            return null;
        }

        // Right rim mendekati left rim (toleransi 5%)
        if (abs($rightRim - $leftRim) / $leftRim > 0.05) {
            return null;
        }

        // Handle: pullback tidak melebihi 40% kedalaman mangkok
        $handleLows = array_slice($lows, -12);
        $handleLow = min($handleLows);
        if (($rightRim - $handleLow) > (($leftRim - $cupBottom) * 0.40)) {
            return null;
        }

        return [
            'breakout_level' => max($leftRim, $rightRim),
            'support_level' => $handleLow,
            'pattern_height' => $leftRim - $cupBottom,
            'description' => 'Mangkok bulat kedalaman ' . round($cupDepth * 100, 1) . '% dengan pembentukan handle siap lepas landas.',
        ];
    }

    private function checkBullishRectangle(array $closes, array $highs, array $lows, int $n): ?array
    {
        $lookback = min(45, $n);
        $sliceH = array_slice($highs, -$lookback);
        $sliceL = array_slice($lows, -$lookback);
        $sliceC = array_slice($closes, -$lookback);

        // Pastikan masuk ke kotak dari bawah (trend naik sebelum konsolidasi)
        $priorTrendGain = ($sliceC[12] - $sliceC[0]) / max(0.000001, $sliceC[0]);
        if ($priorTrendGain < 0.035) {
            return null; // Menghindari falling box / bear shelf
        }

        $res = max(array_slice($sliceH, 12));
        $sup = min(array_slice($sliceL, 12));
        $rangePct = ($res - $sup) / max(0.000001, $sup);

        if ($rangePct < 0.04 || $rangePct > 0.16) {
            return null;
        }

        $touchRes = 0;
        $touchSup = 0;
        foreach (array_slice($sliceH, 12) as $h) {
            if ($h >= $res * 0.988) $touchRes++;
        }
        foreach (array_slice($sliceL, 12) as $l) {
            if ($l <= $sup * 1.012) $touchSup++;
        }

        if ($touchRes >= 2 && $touchSup >= 2) {
            return [
                'breakout_level' => $res,
                'support_level' => $sup,
                'pattern_height' => $res - $sup,
                'description' => "Kotak Darvas Box teruji {$touchRes}x resisten & {$touchSup}x support.",
            ];
        }

        return null;
    }

    private function buildPatternPayload(
        CryptoAsset $asset,
        string $timeframe,
        string $patternType,
        array $patternData,
        float $currClose,
        float $currOpen,
        float $currHigh,
        float $currLow,
        float $buyRatio,
        float $rvol,
        float $currAtr,
        ?float $sma50
    ): array {
        $breakoutPrice = $patternData['breakout_level'];
        $supportLevel = $patternData['support_level'];
        $patternHeight = $patternData['pattern_height'];

        $pctDiff = round((($currClose - $breakoutPrice) / max(0.000001, $breakoutPrice)) * 100, 2);

        $body = abs($currClose - $currOpen);
        $upperWick = $currHigh - max($currClose, $currOpen);
        $lowerWick = min($currClose, $currOpen) - $currLow;
        $isShootingStar = ($upperWick >= 1.3 * max(0.000001, $body)) && ($lowerWick <= 0.6 * max(0.000001, $body));

        // Deteksi False Breakout / Bull Trap:
        // Menembus resisten sesaat tapi ditolak kembali atau volume beli loyo
        $bullTrap = false;
        if ($currHigh >= $breakoutPrice) {
            if ($currClose < $breakoutPrice && $isShootingStar) {
                $bullTrap = true; // Penolakan sumbu atas tajam
            } elseif ($currClose >= $breakoutPrice && ($buyRatio < 46.0 || $rvol < 0.9)) {
                $bullTrap = true; // Breakout tanpa dukungan volume beli
            }
        }

        // Status eksekusi presisi real trading
        if ($bullTrap) {
            $tradeStatus = self::STATUS_BULL_TRAP;
        } elseif ($pctDiff >= 0.0 && $pctDiff <= 2.5 && $buyRatio >= 54.0) {
            $tradeStatus = self::STATUS_BUY_ZONE;
        } elseif ($pctDiff > 2.5) {
            $tradeStatus = self::STATUS_EXTENDED; // Sudah terbang, hindari FOMO
        } elseif ($pctDiff >= -1.5 && $pctDiff < 0.0) {
            $tradeStatus = self::STATUS_RETEST;
        } elseif (abs($currClose - $supportLevel) / max(0.000001, $supportLevel) <= 0.02) {
            $tradeStatus = self::STATUS_CONSOLIDATION_BOUNCE;
        } else {
            $tradeStatus = self::STATUS_FORMING;
        }

        // Confidence score (0-100) presisi
        $conf = 60;
        if ($rvol >= 1.3) $conf += 10;
        if ($rvol >= 2.0) $conf += 5;
        if ($buyRatio >= 60.0) $conf += 12;
        if (!empty($patternData['volume_dry_up'])) $conf += 8;
        if ($sma50 !== null && $currClose >= $sma50) $conf += 6;

        if ($tradeStatus === self::STATUS_BUY_ZONE || $tradeStatus === self::STATUS_RETEST) $conf += 8;
        if ($bullTrap) $conf -= 35;
        if ($sma50 !== null && $currClose < $sma50 * 0.97) $conf -= 12; // Lawan tren

        $conf = max(10, min(100, $conf));

        // Rencana Manajemen Risiko Spot Adaptif ATR (Risk 1.5% - 5.5%)
        $slCandidate = min($supportLevel, $currClose - ($currAtr * 0.8));
        $maxRiskPrice = $currClose * 0.945; // Max -5.5%
        $minRiskPrice = $currClose * 0.985; // Min -1.5%
        $stopLoss = round(max($maxRiskPrice, min($minRiskPrice, $slCandidate)), 6);

        $risk = max(0.000001, $currClose - $stopLoss);

        // TP1: Minimal 1.5x Risk atau Target Breakout + Pattern Height
        $tp1Candidate = max($currClose + ($risk * 1.5), $breakoutPrice + ($patternHeight * 0.8));
        $targetTp1 = round($tp1Candidate, 6);

        // TP2: Minimal 2.5x Risk atau Pattern Height x 1.618
        $tp2Candidate = max($currClose + ($risk * 2.5), $breakoutPrice + ($patternHeight * 1.618));
        $targetTp2 = round($tp2Candidate, 6);

        $reward = max(0.0, $targetTp1 - $currClose);
        $rr = round($reward / $risk, 2);

        return [
            'asset' => $asset,
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'timeframe' => $timeframe,
            'pattern_type' => $patternType,
            'pattern_name' => self::getPatternTypes()[$patternType] ?? $patternType,
            'current_price' => $currClose,
            'price_change_24h' => $asset->getAnalysisForTimeframe($timeframe)?->price_change_24h ?? 0.0,
            'breakout_price' => $breakoutPrice,
            'support_level' => $supportLevel,
            'pattern_height' => $patternHeight,
            'pattern_height_pct' => round(($patternHeight / max(0.000001, $supportLevel)) * 100, 2),
            'distance_to_breakout_pct' => $pctDiff,
            'trade_status' => $tradeStatus,
            'trade_status_label' => self::getStatusOptions()[$tradeStatus] ?? $tradeStatus,
            'buy_ratio' => $buyRatio,
            'rvol' => $rvol,
            'confidence' => $conf,
            'bull_trap_detected' => $bullTrap,
            'stop_loss' => $stopLoss,
            'target_tp1' => $targetTp1,
            'target_tp2' => $targetTp2,
            'potential_upside' => round((($targetTp1 - $currClose) / max(0.000001, $currClose)) * 100, 2),
            'risk_pct' => round((($currClose - $stopLoss) / max(0.000001, $currClose)) * 100, 2),
            'rr_ratio' => $rr,
            'description' => $patternData['description'] ?? '',
        ];
    }

    private function calculateSma(array $data, int $period): ?float
    {
        $n = count($data);
        if ($n < $period) return null;
        $slice = array_slice($data, -$period);
        return round(array_sum($slice) / $period, 6);
    }

    private function calculateAtr(array $highs, array $lows, array $closes, int $period = 14): array
    {
        $n = count($closes);
        if ($n < 2) return [];

        $tr = [];
        $tr[0] = $highs[0] - $lows[0];
        for ($i = 1; $i < $n; $i++) {
            $hL = $highs[$i] - $lows[$i];
            $hCp = abs($highs[$i] - $closes[$i - 1]);
            $lCp = abs($lows[$i] - $closes[$i - 1]);
            $tr[$i] = max($hL, $hCp, $lCp);
        }

        if ($n < $period) {
            return [round(array_sum($tr) / $n, 6)];
        }

        $atr = [];
        $firstAtr = array_sum(array_slice($tr, 0, $period)) / $period;
        for ($i = 0; $i < $period - 1; $i++) {
            $atr[$i] = null;
        }
        $atr[$period - 1] = round($firstAtr, 6);

        for ($i = $period; $i < $n; $i++) {
            $prev = $atr[$i - 1];
            $curr = (($prev * ($period - 1)) + $tr[$i]) / $period;
            $atr[$i] = round($curr, 6);
        }

        return array_values(array_filter($atr, fn($v) => $v !== null));
    }
}
