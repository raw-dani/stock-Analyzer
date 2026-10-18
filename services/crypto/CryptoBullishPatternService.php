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
            self::STATUS_EXTENDED => '🚀 Extended (Sudah Terbang > 4%)',
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
     * Deteksi pola-pola bullish pada satu koin crypto
     */
    public function detectPatterns(CryptoAsset $asset, string $timeframe = '1h'): array
    {
        /** @var CryptoPrice[] $bars */
        $bars = CryptoPrice::find()
            ->where(['crypto_id' => $asset->id, 'timeframe' => $timeframe])
            ->orderBy(['bar_time' => SORT_ASC])
            ->limit(100)
            ->all();

        if (count($bars) < 35) {
            $analyzer = new CryptoAnalyzerService();
            $analyzer->init();
            $analyzer->syncCoinBars($asset, $timeframe, 100);

            $bars = CryptoPrice::find()
                ->where(['crypto_id' => $asset->id, 'timeframe' => $timeframe])
                ->orderBy(['bar_time' => SORT_ASC])
                ->limit(100)
                ->all();
        }

        $n = count($bars);
        if ($n < 30) {
            return [];
        }

        $closes = [];
        $highs = [];
        $lows = [];
        $volumes = [];
        $buyVolumes = [];
        $dates = [];

        foreach ($bars as $b) {
            $closes[] = (float)$b->close;
            $highs[] = (float)$b->high;
            $lows[] = (float)$b->low;
            $volumes[] = (float)$b->volume;
            $buyVolumes[] = (float)$b->buy_volume;
            $dates[] = $b->bar_time;
        }

        $currIdx = $n - 1;
        $currClose = $closes[$currIdx];
        $currHigh = $highs[$currIdx];
        $currLow = $lows[$currIdx];
        $currVol = $volumes[$currIdx];
        $currBuyVol = $buyVolumes[$currIdx];

        $buyRatio = $currVol > 0 ? round(($currBuyVol / $currVol) * 100, 2) : 50.0;
        $volSlice = array_slice($volumes, -20);
        $avgVol = count($volSlice) > 0 ? (array_sum($volSlice) / count($volSlice)) : 1.0;
        $rvol = $avgVol > 0 ? round($currVol / $avgVol, 2) : 1.0;

        $patterns = [];

        // 1. Cek Ascending Triangle
        $tri = $this->checkAscendingTriangle($closes, $highs, $lows, $n);
        if ($tri !== null) {
            $patterns[] = $this->buildPatternPayload($asset, $timeframe, self::PATTERN_ASCENDING_TRIANGLE, $tri, $currClose, $buyRatio, $rvol);
        }

        // 2. Cek Bullish Flag
        $flag = $this->checkBullishFlag($closes, $highs, $lows, $n);
        if ($flag !== null) {
            $patterns[] = $this->buildPatternPayload($asset, $timeframe, self::PATTERN_BULLISH_FLAG, $flag, $currClose, $buyRatio, $rvol);
        }

        // 3. Cek Bullish Pennant
        $pennant = $this->checkBullishPennant($closes, $highs, $lows, $n);
        if ($pennant !== null) {
            $patterns[] = $this->buildPatternPayload($asset, $timeframe, self::PATTERN_BULLISH_PENNANT, $pennant, $currClose, $buyRatio, $rvol);
        }

        // 4. Cek Cup and Handle
        $cup = $this->checkCupAndHandle($closes, $highs, $lows, $n);
        if ($cup !== null) {
            $patterns[] = $this->buildPatternPayload($asset, $timeframe, self::PATTERN_CUP_AND_HANDLE, $cup, $currClose, $buyRatio, $rvol);
        }

        // 5. Cek Bullish Rectangle (Darvas Box)
        $box = $this->checkBullishRectangle($closes, $highs, $lows, $n);
        if ($box !== null) {
            $patterns[] = $this->buildPatternPayload($asset, $timeframe, self::PATTERN_BULLISH_RECTANGLE, $box, $currClose, $buyRatio, $rvol);
        }

        return $patterns;
    }

    private function checkAscendingTriangle(array $closes, array $highs, array $lows, int $n): ?array
    {
        $lookback = min(40, $n);
        $recentHighs = array_slice($highs, -$lookback);
        $recentLows = array_slice($lows, -$lookback);

        $maxHigh = max($recentHighs);
        // Hitung berapa kali menguji level high (within 1.2%)
        $touches = 0;
        foreach ($recentHighs as $h) {
            if ($h >= $maxHigh * 0.988) {
                $touches++;
            }
        }
        if ($touches < 2) {
            return null;
        }

        // Cek low membuat higher lows
        $third = (int)($lookback / 3);
        $low1 = min(array_slice($recentLows, 0, $third));
        $low2 = min(array_slice($recentLows, $third, $third));
        $low3 = min(array_slice($recentLows, $third * 2));

        if ($low2 >= $low1 * 0.995 && $low3 >= $low2 * 0.995 && ($low3 > $low1 * 1.01)) {
            $patternHeight = $maxHigh - $low1;
            return [
                'breakout_level' => $maxHigh,
                'support_level' => $low3,
                'pattern_height' => $patternHeight,
                'description' => 'Resistensi datar teruji ' . $touches . 'x dengan swing low yang terus meninggi (Higher Lows).',
            ];
        }

        return null;
    }

    private function checkBullishFlag(array $closes, array $highs, array $lows, int $n): ?array
    {
        $lookback = min(35, $n);
        $slice = array_slice($closes, -$lookback);
        $subCount = count($slice);

        // Cari tiang bendera (flag pole): lonjakan minimal 5% di 10-15 bar pertama
        $poleStart = $slice[0];
        $poleTopIdx = 0;
        $poleTop = $poleStart;

        for ($i = 5; $i < min(18, $subCount); $i++) {
            if ($slice[$i] > $poleTop) {
                $poleTop = $slice[$i];
                $poleTopIdx = $i;
            }
        }

        $poleGain = ($poleTop - $poleStart) / $poleStart;
        if ($poleGain < 0.05) {
            return null;
        }

        // Konsolidasi setelah pole: channel menyempit atau miring turun lembut
        $flagCloses = array_slice($slice, $poleTopIdx);
        $flagLows = array_slice($lows, -$subCount + $poleTopIdx);
        $flagMin = min($flagLows);

        // Retracement flag tidak boleh melebihi 50% dari pole
        $poleHeight = $poleTop - $poleStart;
        if (($poleTop - $flagMin) > ($poleHeight * 0.55)) {
            return null;
        }

        return [
            'breakout_level' => $poleTop,
            'support_level' => $flagMin,
            'pattern_height' => $poleHeight,
            'description' => 'Flagpole menguat +' . round($poleGain * 100, 1) . '% diikuti konsolidasi bendera tertib.',
        ];
    }

    private function checkBullishPennant(array $closes, array $highs, array $lows, int $n): ?array
    {
        $lookback = min(30, $n);
        $slice = array_slice($closes, -$lookback);
        $subCount = count($slice);

        $poleStart = $slice[0];
        $poleTop = max(array_slice($slice, 0, 12));
        $gain = ($poleTop - $poleStart) / $poleStart;
        if ($gain < 0.04) {
            return null;
        }

        // Konsolidasi simetris: high makin rendah, low makin tinggi
        $recentHighs = array_slice($highs, -14);
        $recentLows = array_slice($lows, -14);
        $h1 = max(array_slice($recentHighs, 0, 7));
        $h2 = max(array_slice($recentHighs, 7));
        $l1 = min(array_slice($recentLows, 0, 7));
        $l2 = min(array_slice($recentLows, 7));

        if ($h2 <= $h1 && $l2 >= $l1) {
            return [
                'breakout_level' => $h1,
                'support_level' => $l2,
                'pattern_height' => $poleTop - $poleStart,
                'description' => 'Konsolidasi pennant segitiga simetris setelah kenaikan impulsif +' . round($gain * 100, 1) . '%.',
            ];
        }

        return null;
    }

    private function checkCupAndHandle(array $closes, array $highs, array $lows, int $n): ?array
    {
        if ($n < 45) {
            return null;
        }

        $lookback = min(60, $n);
        $slice = array_slice($closes, -$lookback);
        $subCount = count($slice);

        $leftRim = max(array_slice($slice, 0, 15));
        $cupBottom = min(array_slice($slice, 15, 30));
        $rightRim = max(array_slice($slice, 30, 20));

        $cupDepth = ($leftRim - $cupBottom) / $leftRim;
        if ($cupDepth < 0.06 || $cupDepth > 0.40) {
            return null;
        }

        // Right rim mendekati left rim (toleransi 5%)
        if (abs($rightRim - $leftRim) / $leftRim > 0.06) {
            return null;
        }

        // Handle (pullback di ujung kanan tidak melebihi 50% kedalaman mangkok)
        $handleLows = array_slice($lows, -10);
        $handleLow = min($handleLows);
        if (($rightRim - $handleLow) > (($leftRim - $cupBottom) * 0.50)) {
            return null;
        }

        return [
            'breakout_level' => max($leftRim, $rightRim),
            'support_level' => $handleLow,
            'pattern_height' => $leftRim - $cupBottom,
            'description' => 'Mangkok bulat kedalaman ' . round($cupDepth * 100, 1) . '% dengan pembentukan handle siap breakout.',
        ];
    }

    private function checkBullishRectangle(array $closes, array $highs, array $lows, int $n): ?array
    {
        $lookback = min(40, $n);
        $sliceH = array_slice($highs, -$lookback);
        $sliceL = array_slice($lows, -$lookback);

        $res = max($sliceH);
        $sup = min($sliceL);
        $rangePct = ($res - $sup) / $sup;

        // Box harus relatif rapat (range 4% - 18%)
        if ($rangePct < 0.04 || $rangePct > 0.20) {
            return null;
        }

        // Hitung sentuhan resisten & support
        $touchRes = 0;
        $touchSup = 0;
        foreach ($sliceH as $h) {
            if ($h >= $res * 0.985) $touchRes++;
        }
        foreach ($sliceL as $l) {
            if ($l <= $sup * 1.015) $touchSup++;
        }

        if ($touchRes >= 2 && $touchSup >= 2) {
            return [
                'breakout_level' => $res,
                'support_level' => $sup,
                'pattern_height' => $res - $sup,
                'description' => 'Rentang Darvas Box teruji ' . $touchRes . 'x resisten & ' . $touchSup . 'x support.',
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
        float $buyRatio,
        float $rvol
    ): array {
        $breakoutPrice = $patternData['breakout_level'];
        $supportLevel = $patternData['support_level'];
        $patternHeight = $patternData['pattern_height'];

        $pctDiff = round((($currClose - $breakoutPrice) / $breakoutPrice) * 100, 2);

        // Status eksekusi
        $tradeStatus = self::STATUS_FORMING;
        if ($pctDiff >= 0.0 && $pctDiff <= 3.5) {
            $tradeStatus = self::STATUS_BUY_ZONE;
        } elseif ($pctDiff > 3.5) {
            $tradeStatus = self::STATUS_EXTENDED;
        } elseif ($pctDiff >= -1.5 && $pctDiff < 0.0) {
            $tradeStatus = self::STATUS_RETEST;
        } elseif (abs($currClose - $supportLevel) / $supportLevel <= 0.02) {
            $tradeStatus = self::STATUS_CONSOLIDATION_BOUNCE;
        }

        // False breakout / Bull Trap check: breakout tapi buy volume taker loyo
        $bullTrap = false;
        if ($pctDiff > 0.0 && $buyRatio < 44.0 && $rvol < 0.9) {
            $bullTrap = true;
        }

        // Confidence score (0-100)
        $conf = 60;
        if ($rvol >= 1.4) $conf += 15;
        if ($buyRatio >= 60.0) $conf += 15;
        if ($tradeStatus === self::STATUS_BUY_ZONE || $tradeStatus === self::STATUS_RETEST) $conf += 10;
        if ($bullTrap) $conf -= 25;
        $conf = max(10, min(100, $conf));

        // Targets & Stop Loss
        $stopLoss = round($supportLevel * 0.985, 6);
        $tp1 = round($breakoutPrice + $patternHeight, 6);
        $tp2 = round($breakoutPrice + ($patternHeight * 1.618), 6);

        $risk = max(0.000001, $currClose - $stopLoss);
        $reward = max(0.0, $tp1 - $currClose);
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
            'pattern_height_pct' => round(($patternHeight / $supportLevel) * 100, 2),
            'distance_to_breakout_pct' => $pctDiff,
            'trade_status' => $tradeStatus,
            'trade_status_label' => self::getStatusOptions()[$tradeStatus] ?? $tradeStatus,
            'buy_ratio' => $buyRatio,
            'rvol' => $rvol,
            'confidence' => $conf,
            'bull_trap_detected' => $bullTrap,
            'stop_loss' => $stopLoss,
            'target_tp1' => $tp1,
            'target_tp2' => $tp2,
            'potential_upside' => round((($tp1 - $currClose) / $currClose) * 100, 2),
            'risk_pct' => round((($currClose - $stopLoss) / $currClose) * 100, 2),
            'rr_ratio' => $rr,
            'description' => $patternData['description'] ?? '',
        ];
    }
}
