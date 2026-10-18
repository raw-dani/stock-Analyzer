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
            ->limit(100)
            ->all();

        // Jika bar kurang dari 35, sync dari Binance
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

        // 5. Fibonacci Retracement & Golden Pocket (Lookback 45 bar)
        $fibLookback = min(45, $count);
        $fibHighs = array_slice($highs, -$fibLookback);
        $fibLows = array_slice($lows, -$fibLookback);
        $swingHigh = count($fibHighs) > 0 ? max($fibHighs) : $currHigh;
        $swingLow = count($fibLows) > 0 ? min($fibLows) : $currLow;
        $diff = $swingHigh - $swingLow;

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

        // Cek apakah harga berada di Golden Pocket (50% - 61.8% retracement dengan toleransi 1.5%)
        $gpUpper = $fibLevels['level_500'] * 1.015;
        $gpLower = $fibLevels['level_618'] * 0.985;
        $inGoldenPocket = ($currClose >= $gpLower && $currClose <= $gpUpper);

        // 6. Confluence Scoring (0 - 100)
        $score = 50;
        $confluences = [];

        // Confluence MA
        if ($maBullish && $priceAboveMa) {
            $score += 15;
            $confluences[] = 'MA Bullish Alignment (EMA20 > SMA50)';
        } elseif ($priceAboveMa) {
            $score += 8;
            $confluences[] = 'Harga Bertahan di Atas EMA20';
        } else {
            $score -= 10;
        }

        // Confluence RSI
        if ($isRsiOversold) {
            $score += 18;
            $confluences[] = 'RSI Oversold (' . round($currRsi, 1) . ') - Potensi Pantulan';
        } elseif ($isRsiBullishZone && $currRsi > $prevRsi) {
            $score += 12;
            $confluences[] = 'RSI Bullish Momentum (' . round($currRsi, 1) . ')';
        } elseif ($isRsiOverbought) {
            $score -= 15;
        }

        // Confluence MACD
        if ($isMacdBullishCross) {
            $score += 18;
            $confluences[] = 'MACD Golden Cross Bullish Baru Terbentuk';
        } elseif ($isMacdHistExpanding) {
            $score += 10;
            $confluences[] = 'Histogram MACD Positif Menguat';
        } elseif ($isMacdBearishCross) {
            $score -= 15;
        }

        // Confluence Fibonacci
        if ($inGoldenPocket) {
            $score += 20;
            $confluences[] = 'Harga Berada di Fibonacci Golden Pocket (50% - 61.8%)';
        } elseif ($currClose >= $fibLevels['level_382'] && $currClose <= $fibLevels['level_500']) {
            $score += 10;
            $confluences[] = 'Fibonacci Healthy Retracement (38.2% - 50%)';
        }

        // Confluence Taker Buy Pressure
        if ($buyRatio >= 68.0) {
            $score += 15;
            $confluences[] = 'High Taker Buying Pressure (' . $buyRatio . '%)';
        } elseif ($buyRatio >= 58.0) {
            $score += 8;
            $confluences[] = 'Buyer Dominan (' . $buyRatio . '%)';
        } elseif ($buyRatio <= 40.0) {
            $score -= 15;
        }

        if ($rvol >= 1.5) {
            $score += 8;
            $confluences[] = 'Volume Spike (' . $rvol . 'x RVOL)';
        }

        $score = max(0, min(100, $score));

        // Action Sinyal
        $action = self::ACTION_HOLD;
        if ($score >= 78 && ($isRsiOversold || $inGoldenPocket || $isMacdBullishCross)) {
            $action = self::ACTION_STRONG_BUY;
        } elseif ($score >= 65) {
            $action = self::ACTION_BUY;
        } elseif ($score <= 35 || $isRsiOverbought || $isMacdBearishCross) {
            $action = self::ACTION_SELL;
        } elseif ($score < 50) {
            $action = self::ACTION_WAIT;
        }

        // 7. Trading Plan (Stop Loss & Target TP)
        $stopLoss = round(min($fibLevels['level_786'], $swingLow, $currClose * 0.96), 6);
        $entryLow = round($currClose * 0.995, 6);
        $entryHigh = round($currClose * 1.005, 6);
        $targetTp1 = round(max($fibLevels['level_236'], $swingHigh, $currClose * 1.04), 6);
        $targetTp2 = round(max($fibLevels['ext_1272'], $currClose * 1.08), 6);

        $risk = max(0.000001, $currClose - $stopLoss);
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
}
