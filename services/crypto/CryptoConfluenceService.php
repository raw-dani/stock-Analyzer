<?php

declare(strict_types=1);

namespace app\services\crypto;

use app\models\CryptoAsset;
use app\models\CryptoPrice;
use yii\base\Component;
use Yii;

/**
 * Service Analisis & Scanner Confluence Multi-Indikator untuk Crypto (24/7):
 * - Support & Resistance / Moving Average (SMA 20, 50, 200 & EMA 20, 50)
 * - RSI (Relative Strength Index) Wilder 14
 * - MACD (12, 26, 9) Crossover & Histogram Momentum
 * - Fibonacci Retracement & Golden Pocket (50% - 61.8%)
 * - Taker Buy Pressure Ratio (Aggressor Orderflow)
 * - Trading Plan Presisi: Entry, Stop Loss, Target TP1 & TP2, R:R
 */
class CryptoConfluenceService extends Component
{
    public const ACTION_STRONG_BUY = 'STRONG_BUY';
    public const ACTION_BUY        = 'BUY';
    public const ACTION_HOLD       = 'HOLD';
    public const ACTION_WAIT       = 'WAIT';
    public const ACTION_SELL       = 'SELL';

    private BinanceCryptoProvider $provider;

    public function init(): void
    {
        parent::init();
        $this->provider = new BinanceCryptoProvider();
        $this->provider->init();
    }

    /**
     * Preset filter strategi Confluence untuk Crypto
     */
    public static function getStrategyPresets(): array
    {
        return [
            'all' => 'Semua Sinyal Confluence',
            'confluence_buy' => '💎 High Confluence Buy (Skor ≥ 75)',
            'golden_fib_rsi' => '🎯 Golden Pocket (61.8%) + RSI Oversold',
            'macd_bullish_cross' => '🚀 MACD Bullish Cross + MA Support',
            'oversold_bounce' => '⚡ RSI Oversold (< 35) Reversal',
            'breakout_confluence' => '📈 MA & Fib Breakout + Buy Pressure',
            'sell_signals' => '🛑 Sinyal Jual / Overbought / Bearish',
        ];
    }

    /**
     * Scan seluruh koin crypto aktif dengan strategi Confluence
     */
    public function scanAll(array $options = []): array
    {
        $timeframe = $options['timeframe'] ?? CryptoPrice::TIMEFRAME_1H;
        if (!array_key_exists($timeframe, CryptoPrice::SUPPORTED_TIMEFRAMES)) {
            $timeframe = CryptoPrice::TIMEFRAME_1H;
        }

        $preset = (string)($options['strategy'] ?? 'all');
        $minScore = (int)($options['minScore'] ?? 0);
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

            $analysis = $this->analyzeAsset($coin, $timeframe);
            if ($analysis === null) {
                continue;
            }

            if ($minScore > 0 && ($analysis['confluence_score'] ?? 0) < $minScore) {
                continue;
            }

            if ($minRr > 0.0 && ($analysis['rr_ratio'] ?? 0.0) < $minRr) {
                continue;
            }

            if (!$this->matchesPreset($analysis, $preset)) {
                continue;
            }

            $results[] = $analysis;
        }

        return $results;
    }

    /**
     * Analisis komprehensif Confluence untuk 1 koin crypto
     */
    public function analyzeAsset(CryptoAsset $asset, string $timeframe = '1h'): ?array
    {
        /** @var CryptoPrice[] $bars */
        $bars = CryptoPrice::find()
            ->where(['crypto_id' => $asset->id, 'timeframe' => $timeframe])
            ->orderBy(['bar_time' => SORT_ASC])
            ->limit(200)
            ->all();

        // Jika bar kurang dari 50, sync dari Binance
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

        $count = count($bars);
        if ($count < 30) {
            return null;
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

        $currIdx = $count - 1;
        $prevIdx = $count - 2;

        $currClose = $closes[$currIdx];
        $prevClose = $closes[$prevIdx];
        $currHigh = $highs[$currIdx];
        $currLow = $lows[$currIdx];
        $currVol = $volumes[$currIdx];
        $currBuyVol = $buyVolumes[$currIdx];

        // 1. Taker Buy Pressure & RVOL
        $currOpen = (float)$bars[$currIdx]->open;
        $isGreen = $currClose >= $currOpen;
        $body = abs($currClose - $currOpen);
        $lowerWick = min($currClose, $currOpen) - $currLow;
        $upperWick = $currHigh - max($currClose, $currOpen);
        $isAbsorptionPinbar = ($lowerWick >= 1.2 * max(0.000001, $body)) && ($upperWick <= 0.8 * max(0.000001, $body));
        $isShootingStar = ($upperWick >= 1.4 * max(0.000001, $body)) && ($lowerWick <= 0.6 * max(0.000001, $body));

        $buyRatio = $currVol > 0 ? round(($currBuyVol / $currVol) * 100, 2) : 50.0;
        $volSlice = array_slice($volumes, -20);
        $avgVol = count($volSlice) > 0 ? (array_sum($volSlice) / count($volSlice)) : 1.0;
        $rvol = $avgVol > 0 ? round($currVol / $avgVol, 2) : 1.0;

        // 2. Moving Averages (SMA & EMA)
        $sma20Series = $this->sma($closes, 20);
        $sma50Series = $this->sma($closes, 50);
        $sma200Series = $this->sma($closes, min(200, $count));
        $ema20Series = $this->ema($closes, 20);
        $ema50Series = $this->ema($closes, 50);

        $currSma20 = $sma20Series[$currIdx];
        $currSma50 = $sma50Series[$currIdx];
        $currSma200 = $sma200Series[$currIdx];
        $currEma20 = $ema20Series[$currIdx];
        $currEma50 = $ema50Series[$currIdx];

        $maBullish = ($currEma20 !== null && $currSma50 !== null && $currEma20 > $currSma50);
        $priceAboveMa = ($currEma20 !== null && $currClose >= $currEma20 * 0.995);
        $macroBullish = ($currSma200 === null || $currClose >= $currSma200 * 0.99);

        // 3. RSI 14 (Wilder)
        $rsiSeries = $this->calculateRsi($closes, 14);
        $currRsi = $rsiSeries[$currIdx] ?? 50.0;
        $prevRsi = $rsiSeries[$prevIdx] ?? 50.0;
        $isRsiOversold = ($currRsi <= 35.0);
        $isRsiBullishZone = ($currRsi >= 45.0 && $currRsi <= 65.0);
        $isRsiOverbought = ($currRsi >= 70.0);

        // 4. MACD (12, 26, 9)
        $macdData = $this->calculateMacd($closes, 12, 26, 9);
        $currMacd = $macdData['macd'][$currIdx] ?? 0.0;
        $currSignal = $macdData['signal'][$currIdx] ?? 0.0;
        $currHist = $macdData['histogram'][$currIdx] ?? 0.0;
        $prevMacd = $macdData['macd'][$prevIdx] ?? 0.0;
        $prevSignal = $macdData['signal'][$prevIdx] ?? 0.0;
        $prevHist = $macdData['histogram'][$prevIdx] ?? 0.0;

        $isMacdBullishCross = ($prevMacd <= $prevSignal && $currMacd > $currSignal);
        $isMacdBearishCross = ($prevMacd >= $prevSignal && $currMacd < $currSignal);
        $isMacdHistExpanding = ($currHist > $prevHist && $currHist > 0);
        $isMacdPositive = ($currMacd > 0.0);

        // 5. Fibonacci Retracement Kronologis & Golden Pocket Realistis
        // Lookback 50 bar: cari swing low dan swing high secara urut waktu (chronological order)
        $fibLookback = min(50, $count);
        $subHighs = array_slice($highs, -$fibLookback);
        $subLows = array_slice($lows, -$fibLookback);

        $maxHighVal = max($subHighs);
        $minLowVal = min($subLows);
        $maxHighIdxRel = array_search($maxHighVal, $subHighs, true);
        $minLowIdxRel = array_search($minLowVal, $subLows, true);

        // Bullish impulse hanya valid jika Swing Low terjadi SEBELUM Swing High (impuls naik lalu pullback)
        $isBullishImpulse = ($minLowIdxRel !== false && $maxHighIdxRel !== false && $minLowIdxRel < $maxHighIdxRel && ($maxHighVal - $minLowVal) / max($minLowVal, 0.000001) >= 0.035);

        $swingHigh = $isBullishImpulse ? $maxHighVal : $currHigh;
        $swingLow = $isBullishImpulse ? $minLowVal : $currLow;
        $diff = max(0.000001, $swingHigh - $swingLow);

        $fibLevels = [
            'level_0' => round($swingHigh, 6),
            'level_236' => round($swingHigh - ($diff * 0.236), 6),
            'level_382' => round($swingHigh - ($diff * 0.382), 6),
            'level_500' => round($swingHigh - ($diff * 0.500), 6),
            'level_618' => round($swingHigh - ($diff * 0.618), 6),
            'level_786' => round($swingHigh - ($diff * 0.786), 6),
            'level_100' => round($swingLow, 6),
            'ext_1272' => round($swingHigh + ($diff * 0.272), 6),
            'ext_1618' => round($swingHigh + ($diff * 0.618), 6),
        ];

        // Golden Pocket (50% - 61.8% retracement dari gelombang impuls bullish)
        $inGoldenPocket = false;
        if ($isBullishImpulse) {
            $gpUpper = $fibLevels['level_500'] * 1.012;
            $gpLower = $fibLevels['level_618'] * 0.988;
            $inGoldenPocket = ($currClose >= $gpLower && $currClose <= $gpUpper);
        }

        // 6. ATR 14 untuk Stop Loss & Target Presisi Volatilitas Crypto
        $atrSeries = $this->calculateAtr($highs, $lows, $closes, 14);
        $currAtr = !empty($atrSeries) ? end($atrSeries) : ($currClose * 0.02);

        // 7. Confluence Scoring Presisi (0 - 100)
        $score = 50;
        $confluences = [];

        // Trend MA & Makro
        if ($maBullish && $priceAboveMa) {
            $score += 15;
            $confluences[] = 'MA Bullish Alignment (EMA20 > SMA50)';
        } elseif ($priceAboveMa) {
            $score += 8;
            $confluences[] = 'Harga Bertahan di Atas EMA20';
        } else {
            $score -= 10;
        }

        if (!$macroBullish) {
            $score -= 12; // Di bawah SMA 200 (bearish regime)
        } else {
            $score += 6;
            $confluences[] = 'Tren Makro Bullish (Di Atas SMA 200)';
        }

        // Candlestick Absorption / Rejection
        if ($isAbsorptionPinbar) {
            $score += 10;
            $confluences[] = 'Pinbar / Sumbu Bawah Rejection (Penyerapan Beli)';
        } elseif ($isShootingStar) {
            $score -= 15; // Rawan penolakan resisten / bull trap
        }

        // RSI Reversal Protection
        if ($isRsiOversold) {
            if ($currRsi > $prevRsi || $isAbsorptionPinbar || $isGreen) {
                $score += 16;
                $confluences[] = 'RSI Oversold Berbalik Naik (' . round($currRsi, 1) . ')';
            } else {
                $score -= 8; // Menghindari pisau jatuh
            }
        } elseif ($isRsiBullishZone && $currRsi > $prevRsi) {
            $score += 10;
            $confluences[] = 'RSI Bullish Momentum (' . round($currRsi, 1) . ')';
        } elseif ($isRsiOverbought) {
            $score -= 16;
        }

        // MACD
        if ($isMacdBullishCross) {
            $score += 18;
            $confluences[] = 'MACD Golden Cross Bullish Terkonfirmasi';
        } elseif ($isMacdHistExpanding) {
            $score += 8;
            $confluences[] = 'Histogram MACD Positif Menguat';
        } elseif ($isMacdBearishCross) {
            $score -= 16;
        }

        // Fibonacci Golden Pocket
        if ($inGoldenPocket) {
            $score += 18;
            $confluences[] = 'Fibonacci Golden Pocket 50% - 61.8% Terkonfirmasi';
        } elseif ($isBullishImpulse && $currClose >= $fibLevels['level_382'] && $currClose <= $fibLevels['level_500']) {
            $score += 8;
            $confluences[] = 'Fibonacci Healthy Retracement (38.2% - 50%)';
        }

        // Taker Buying Pressure Directional
        $isDumping = ($rvol >= 1.4 && !$isGreen && $buyRatio <= 43.0);
        if ($buyRatio >= 66.0) {
            $score += 14;
            $confluences[] = 'Dominasi Taker Buy Tinggi (' . $buyRatio . '%)';
        } elseif ($buyRatio >= 56.0) {
            $score += 7;
            $confluences[] = 'Buyer Taker Aktif (' . $buyRatio . '%)';
        } elseif ($buyRatio <= 42.0) {
            $score -= 15;
        }

        if ($rvol >= 1.4) {
            if ($buyRatio >= 56.0 && $isGreen) {
                $score += 10;
                $confluences[] = 'Volume Spike Akumulasi (' . $rvol . 'x RVOL)';
            } elseif ($isDumping) {
                $score -= 18; // Heavy sell dump
            }
        }

        $score = max(0, min(100, $score));

        // Action Sinyal Presisi Spot Trading
        $action = self::ACTION_HOLD;
        if ($score >= 76 && !$isDumping && !$isShootingStar && ($isRsiOversold || $inGoldenPocket || $isMacdBullishCross || $buyRatio >= 62.0)) {
            $action = self::ACTION_STRONG_BUY;
        } elseif ($score >= 64 && !$isDumping && !$isShootingStar) {
            $action = self::ACTION_BUY;
        } elseif ($score <= 36 || $isRsiOverbought || $isMacdBearishCross || $isDumping) {
            $action = self::ACTION_SELL;
        } elseif ($score < 50) {
            $action = self::ACTION_WAIT;
        }

        // 8. Rencana Trading Presisi Spot (ATR Dynamic Risk Management)
        // Stop Loss ditempatkan di bawah support/swing low dengan buffer 0.8x ATR
        // Dibatasi rentang sehat spot: minimal 1.5% dan maksimal 5.5% dari harga entry
        $slCandidate = min($fibLevels['level_786'], $currClose - ($currAtr * 1.0));
        $maxRiskPrice = $currClose * 0.945; // Maksimal risiko 5.5%
        $minRiskPrice = $currClose * 0.985; // Minimal risiko 1.5%

        $stopLoss = round(max($maxRiskPrice, min($minRiskPrice, $slCandidate)), 6);
        $risk = max(0.000001, $currClose - $stopLoss);

        $entryLow = round($currClose * 0.996, 6);
        $entryHigh = round($currClose * 1.004, 6);

        // TP1: Minimal 1.5x Risk atau Swing High
        $tp1Candidate = max($currClose + ($risk * 1.5), $swingHigh);
        $targetTp1 = round($tp1Candidate, 6);

        // TP2: Minimal 2.5x Risk atau Fib Extension 1.272
        $tp2Candidate = max($currClose + ($risk * 2.5), $fibLevels['ext_1272']);
        $targetTp2 = round($tp2Candidate, 6);

        $reward = max(0.0, $targetTp1 - $currClose);
        $rrRatio = round($reward / $risk, 2);

        return [
            'asset' => $asset,
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'timeframe' => $timeframe,
            'current_price' => $currClose,
            'price_change_24h' => $asset->getAnalysisForTimeframe($timeframe)?->price_change_24h ?? 0.0,
            'buy_ratio' => $buyRatio,
            'rvol' => $rvol,
            'confluence_score' => $score,
            'action' => $action,
            'confluences' => $confluences,
            // MA Matrix
            'ma_bullish' => $maBullish,
            'price_above_ma' => $priceAboveMa,
            'sma20' => $currSma20,
            'sma50' => $currSma50,
            'sma200' => $currSma200,
            'ema20' => $currEma20,
            'ema50' => $currEma50,
            'swing_high' => $swingHigh,
            'swing_low' => $swingLow,
            // RSI
            'rsi' => round($currRsi, 1),
            'rsi_status' => $isRsiOversold ? 'OVERSOLD' : ($isRsiOverbought ? 'OVERBOUGHT' : 'NEUTRAL'),
            // MACD
            'macd' => round($currMacd, 6),
            'macd_signal' => round($currSignal, 6),
            'macd_hist' => round($currHist, 6),
            'macd_cross' => $isMacdBullishCross ? 'BULLISH_CROSS' : ($isMacdBearishCross ? 'BEARISH_CROSS' : 'NONE'),
            // Fibonacci
            'in_golden_pocket' => $inGoldenPocket,
            'fib_levels' => $fibLevels,
            // Trading Plan
            'entry_range' => ['low' => $entryLow, 'high' => $entryHigh],
            'stop_loss' => $stopLoss,
            'target_tp1' => $targetTp1,
            'target_tp2' => $targetTp2,
            'rr_ratio' => $rrRatio,
            'potential_upside' => round((($targetTp1 - $currClose) / $currClose) * 100, 2),
            'risk_pct' => round((($currClose - $stopLoss) / $currClose) * 100, 2),
        ];
    }

    private function matchesPreset(array $item, string $preset): bool
    {
        return match ($preset) {
            'confluence_buy' => ($item['confluence_score'] ?? 0) >= 75 && in_array($item['action'], [self::ACTION_STRONG_BUY, self::ACTION_BUY], true),
            'golden_fib_rsi' => !empty($item['in_golden_pocket']) && ($item['rsi'] ?? 50) <= 45,
            'macd_bullish_cross' => ($item['macd_cross'] ?? '') === 'BULLISH_CROSS' && !empty($item['price_above_ma']),
            'oversold_bounce' => ($item['rsi'] ?? 50) <= 35,
            'breakout_confluence' => !empty($item['ma_bullish']) && ($item['rvol'] ?? 1) >= 1.3 && ($item['buy_ratio'] ?? 50) >= 58,
            'sell_signals' => $item['action'] === self::ACTION_SELL || ($item['rsi'] ?? 50) >= 70 || ($item['macd_cross'] ?? '') === 'BEARISH_CROSS',
            default => true,
        };
    }

    private function sma(array $data, int $period): array
    {
        $n = count($data);
        $res = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i < $period - 1) {
                $res[] = null;
            } else {
                $slice = array_slice($data, $i - $period + 1, $period);
                $res[] = round(array_sum($slice) / $period, 6);
            }
        }
        return $res;
    }

    private function ema(array $data, int $period): array
    {
        $n = count($data);
        $res = [];
        if ($n < $period) {
            return array_fill(0, $n, null);
        }

        $multiplier = 2 / ($period + 1);
        $firstSma = array_sum(array_slice($data, 0, $period)) / $period;

        for ($i = 0; $i < $period - 1; $i++) {
            $res[] = null;
        }
        $res[] = round($firstSma, 6);

        for ($i = $period; $i < $n; $i++) {
            $prevEma = $res[$i - 1];
            $currEma = (($data[$i] - $prevEma) * $multiplier) + $prevEma;
            $res[] = round($currEma, 6);
        }

        return $res;
    }

    private function calculateRsi(array $closes, int $period = 14): array
    {
        $n = count($closes);
        $res = array_fill(0, $n, 50.0);
        if ($n < $period + 1) {
            return $res;
        }

        $gains = 0.0;
        $losses = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            if ($diff >= 0) $gains += $diff; else $losses += abs($diff);
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;
        $rs = $avgLoss == 0 ? 100 : ($avgGain / $avgLoss);
        $res[$period] = round(100 - (100 / (1 + $rs)), 2);

        for ($i = $period + 1; $i < $n; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $g = $diff >= 0 ? $diff : 0.0;
            $l = $diff < 0 ? abs($diff) : 0.0;

            $avgGain = (($avgGain * ($period - 1)) + $g) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $l) / $period;
            $rs = $avgLoss == 0 ? 100 : ($avgGain / $avgLoss);
            $res[$i] = round(100 - (100 / (1 + $rs)), 2);
        }

        return $res;
    }

    private function calculateMacd(array $closes, int $fast = 12, int $slow = 26, int $signalPeriod = 9): array
    {
        $fastEma = $this->ema($closes, $fast);
        $slowEma = $this->ema($closes, $slow);
        $n = count($closes);

        $macdLine = [];
        $validMacd = [];
        $validIndices = [];

        for ($i = 0; $i < $n; $i++) {
            if ($fastEma[$i] !== null && $slowEma[$i] !== null) {
                $val = round($fastEma[$i] - $slowEma[$i], 6);
                $macdLine[$i] = $val;
                $validMacd[] = $val;
                $validIndices[] = $i;
            } else {
                $macdLine[$i] = 0.0;
            }
        }

        $signalLine = array_fill(0, $n, 0.0);
        $histogram = array_fill(0, $n, 0.0);

        if (count($validMacd) >= $signalPeriod) {
            $validSignal = $this->ema($validMacd, $signalPeriod);
            foreach ($validIndices as $pos => $origIdx) {
                if ($validSignal[$pos] !== null) {
                    $sig = $validSignal[$pos];
                    $signalLine[$origIdx] = $sig;
                    $histogram[$origIdx] = round($macdLine[$origIdx] - $sig, 6);
                }
            }
        }

        return [
            'macd' => $macdLine,
            'signal' => $signalLine,
            'histogram' => $histogram,
        ];
    }

    /**
     * Hitung Average True Range (ATR) untuk manajemen risiko adaptif
     * @return float[]
     */
    private function calculateAtr(array $highs, array $lows, array $closes, int $period = 14): array
    {
        $n = count($closes);
        if ($n < 2) {
            return [];
        }

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
