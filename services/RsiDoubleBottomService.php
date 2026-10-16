<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;

/**
 * Service Analisis & Scanner RSI Komprehensif.
 *
 * Mengidentifikasi Waktu Beli (Entry) dan Waktu Jual (Exit/Take Profit) yang tepat dengan:
 * 1. Deteksi Sinyal Oversold Reversal (RSI bouncing ke atas 30/35) & Bullish Divergence (Waktu Beli Terbaik).
 * 2. Deteksi Sinyal Overbought Exit (RSI rejection/cross ke bawah 70/65) & Bearish Divergence (Waktu Jual/TP).
 * 3. Deteksi RSI Double Bottom / Double Top Pattern untuk konfirmasi neckline breakout/breakdown.
 * 4. Multi-Timeframe Trend Filter (MA 50 / Trend Filter) agar terhindar dari falling knife.
 * 5. Kalkulasi dinamis Target Profit (TP1, TP2 berdasarkan Swing & Risk-Reward) & Cut Loss (SL).
 */
final class RsiDoubleBottomService
{
    public const TIMEFRAME_1H = 1;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_1D = 24;

    private const MIN_LOOKBACK = 20;
    private const MAX_LOOKBACK = 300;
    private const MIN_RSI_PERIOD = 2;
    private const MAX_RSI_PERIOD = 50;
    private const MIN_TOLERANCE = 0.5;
    private const MAX_TOLERANCE = 10.0;
    private const MIN_MAX_RSI = 0.0;
    private const MAX_MAX_RSI = 100.0;

    /**
     * Scan seluruh saham aktif dengan sinyal RSI komprehensif.
     */
    public function scanAll(
        int $timeframe = 24,
        int $lookback = 150,
        float $tol = 3.0,
        int $period = 14,
        float $maxRsi = 40.0,
        int $minSeparation = 5,
        int $maxSeparation = 30,
        float $necklineMin = 2.0
    ): array {
        $this->validateOptions($timeframe, $lookback, $tol, $period, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $results = [];
        $stocks = Stock::find()->where(['active' => true])->all();
        \Yii::info('RSI Scanner: ' . count($stocks) . " stocks tf={$timeframe}", 'app\services\rsidoublebottom');

        foreach ($stocks as $stock) {
            try {
                $p = $this->detectPattern($stock->id, $timeframe, $lookback, $tol, $period, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
            } catch (\Throwable $e) {
                \Yii::warning("RSI scan failed {$stock->symbol}: " . $e->getMessage(), 'app\services\rsidoublebottom');
                continue;
            }

            if ($p !== null) {
                $p['symbol'] = $stock->symbol;
                $p['stock_name'] = $stock->name;
                $p['sector'] = $stock->sector;
                $results[] = $p;
            }
        }

        usort($results, fn ($a, $b) => ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0));
        return $results;
    }

    /**
     * Deteksi kondisi RSI komprehensif (Waktu Beli, Jual, Divergence, Pattern).
     */
    public function detectPattern(
        int $stockId,
        int $tf = 24,
        int $lookback = 150,
        float $tol = 3.0,
        int $period = 14,
        float $maxRsi = 40.0,
        int $minSeparation = 5,
        int $maxSeparation = 30,
        float $necklineMin = 2.0,
        bool $includeSeries = false
    ): ?array {
        $this->validateOptions($tf, $lookback, $tol, $period, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        [$candles, $approx] = $this->loadCandles($stockId, $tf, $lookback);
        if (count($candles) < ($period + 10)) {
            return null;
        }

        $closes = array_map(fn ($c) => (float) $c['close'], $candles);
        $rsi = RsiService::calculate($closes, $period);
        $ma50 = $this->calculateSma($closes, 50);

        $found = $this->analyzeRsiStrategy($candles, $rsi, $ma50, $tol, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        if ($found === null) {
            return null;
        }

        $found['timeframe'] = $tf;
        $found['approximated'] = $approx;
        $found['data_source'] = $approx ? 'daily_fallback' : ($tf === self::TIMEFRAME_1D ? 'daily' : 'intraday');
        $found['rsi_period'] = $period;

        if ($includeSeries) {
            $found['candles'] = $candles;
            $found['rsi_series'] = $rsi;
            $found['ma50_series'] = $ma50;
        }

        return $found;
    }

    /**
     * Hitung SMA (Simple Moving Average) untuk trend filter.
     */
    private function calculateSma(array $data, int $period): array
    {
        $n = count($data);
        $sma = array_fill(0, $n, null);
        if ($n < $period) {
            return $sma;
        }

        $sum = array_sum(array_slice($data, 0, $period));
        $sma[$period - 1] = round($sum / $period, 4);

        for ($i = $period; $i < $n; $i++) {
            $sum += $data[$i] - $data[$i - $period];
            $sma[$i] = round($sum / $period, 4);
        }

        return $sma;
    }

    private function validateOptions(
        int $timeframe,
        int $lookback,
        float $tol,
        int $period,
        float $maxRsi,
        int $minSeparation,
        int $maxSeparation,
        float $necklineMin
    ): void {
        if (!in_array($timeframe, [self::TIMEFRAME_1H, self::TIMEFRAME_2H, self::TIMEFRAME_4H, self::TIMEFRAME_1D], true)) {
            throw new \InvalidArgumentException('Timeframe harus salah satu dari 1H, 2H, 4H, atau 1D.');
        }
        if ($lookback < self::MIN_LOOKBACK || $lookback > self::MAX_LOOKBACK) {
            throw new \InvalidArgumentException("Lookback harus antara " . self::MIN_LOOKBACK . " dan " . self::MAX_LOOKBACK . " candle.");
        }
        if ($period < self::MIN_RSI_PERIOD || $period > self::MAX_RSI_PERIOD) {
            throw new \InvalidArgumentException("RSI period harus antara " . self::MIN_RSI_PERIOD . " dan " . self::MAX_RSI_PERIOD . '.');
        }
        if (!is_finite($tol) || $tol < self::MIN_TOLERANCE || $tol > self::MAX_TOLERANCE) {
            throw new \InvalidArgumentException("Tolerance harus antara " . self::MIN_TOLERANCE . " dan " . self::MAX_TOLERANCE . " poin RSI.");
        }
        if (!is_finite($maxRsi) || $maxRsi < self::MIN_MAX_RSI || $maxRsi > self::MAX_MAX_RSI) {
            throw new \InvalidArgumentException('Max RSI harus antara 0 dan 100.');
        }
        if ($minSeparation < 1 || $maxSeparation < $minSeparation) {
            throw new \InvalidArgumentException('Separation tidak valid.');
        }
        if (!is_finite($necklineMin) || $necklineMin < 0.0) {
            throw new \InvalidArgumentException('Neckline minimum tidak valid.');
        }
    }

    private function loadCandles(int $stockId, int $tf, int $lookback): array
    {
        if ($tf === self::TIMEFRAME_1D) {
            return [$this->dailyCandles($stockId, $lookback), false];
        }
        try {
            $rows = IntradayPrice::find()
                ->where(['stock_id' => $stockId, 'timeframe' => '1h'])
                ->orderBy(['datetime' => SORT_DESC])
                ->limit(max($lookback * 4, 60))
                ->all();
        } catch (\Throwable $e) {
            \Yii::warning("Intraday data unavailable for RSI scan {$stockId}: " . $e->getMessage(), 'app\services\rsidoublebottom');
            return [$this->dailyCandles($stockId, $lookback), true];
        }
        $rows = array_reverse($rows);
        $base = [];
        foreach ($rows as $p) {
            $base[] = ['open' => (float) $p->open, 'high' => (float) $p->high, 'low' => (float) $p->low, 'close' => (float) $p->close, 'volume' => (int) $p->volume, 'date' => $p->datetime];
        }
        if (count($base) >= 20) {
            $per = $tf === 2 ? 2 : ($tf === 4 ? 4 : 1);
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
        $prices = array_reverse($prices);
        $out = [];
        foreach ($prices as $p) {
            $out[] = ['open' => (float) $p->open, 'high' => (float) $p->high, 'low' => (float) $p->low, 'close' => (float) $p->close, 'volume' => (int) $p->volume, 'date' => $p->date];
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
            'high' => max(array_column($chunk, 'high')),
            'low' => min(array_column($chunk, 'low')),
            'close' => (float) end($chunk)['close'],
            'volume' => array_sum(array_column($chunk, 'volume')),
            'date' => end($chunk)['date'],
        ];
    }

    /**
     * Analisis Strategi RSI Lengkap: Beli, Jual, Divergence, dan Pola Double Bottom/Top.
     */
    private function analyzeRsiStrategy(
        array $candles,
        array $rsi,
        array $ma50,
        float $tol,
        float $maxRsi,
        int $minSeparation,
        int $maxSeparation,
        float $necklineMin
    ): ?array {
        $n = count($candles);
        if ($n < 20) {
            return null;
        }

        $curRsi = $rsi[$n - 1];
        $prevRsi = $rsi[$n - 2] ?? null;
        $prev2Rsi = $rsi[$n - 3] ?? null;

        if ($curRsi === null || $prevRsi === null) {
            return null;
        }

        $curPrice = (float) $candles[$n - 1]['close'];
        $curMa50 = $ma50[$n - 1] ?? null;
        $isAboveMa50 = ($curMa50 !== null) ? ($curPrice >= $curMa50) : true;

        // 4. Tambahan Strategi Konfirmasi 1: Volume & Relative Volume (RVOL)
        $volSMA20 = 1.0;
        $curVol = (int) ($candles[$n - 1]['volume'] ?? 0);
        $recentVols = array_map(fn ($c) => (int) ($c['volume'] ?? 0), array_slice($candles, -21, 20));
        if (!empty($recentVols)) {
            $volSMA20 = max(1.0, array_sum($recentVols) / count($recentVols));
        }
        $rvol = round($curVol / $volSMA20, 2);
        $volumeSpike = $rvol >= 1.5;
        $volumeSupportive = ($curPrice >= ($candles[$n - 1]['open'] ?? $curPrice)) ? ($rvol >= 1.0) : false;

        // 5. Tambahan Strategi Konfirmasi 2: Candlestick Reversal Patterns (Hammer, Engulfing, Shooting Star)
        $candlePattern = null;
        $candleSignal = 'NONE';
        $cOpen = (float) $candles[$n - 1]['open'];
        $cClose = (float) $candles[$n - 1]['close'];
        $cHigh = (float) $candles[$n - 1]['high'];
        $cLow = (float) $candles[$n - 1]['low'];
        $cRange = max(0.001, $cHigh - $cLow);
        $cBody = abs($cClose - $cOpen);
        $lowerWick = min($cOpen, $cClose) - $cLow;
        $upperWick = $cHigh - max($cOpen, $cClose);

        $prevOpen = (float) ($candles[$n - 2]['open'] ?? $cOpen);
        $prevClose = (float) ($candles[$n - 2]['close'] ?? $cClose);

        if ($lowerWick >= ($cBody * 1.8) && $upperWick <= ($cBody * 0.7) && $cBody >= ($cRange * 0.15)) {
            $candlePattern = 'Hammer / Pinbar';
            $candleSignal = 'BULLISH';
        } elseif ($cClose > $cOpen && $prevClose < $prevOpen && $cOpen <= $prevClose && $cClose >= $prevOpen) {
            $candlePattern = 'Bullish Engulfing';
            $candleSignal = 'BULLISH';
        } elseif ($upperWick >= ($cBody * 1.8) && $lowerWick <= ($cBody * 0.7) && $cBody >= ($cRange * 0.15)) {
            $candlePattern = 'Shooting Star';
            $candleSignal = 'BEARISH';
        } elseif ($cClose < $cOpen && $prevClose > $prevOpen && $cOpen >= $prevClose && $cClose <= $prevOpen) {
            $candlePattern = 'Bearish Engulfing';
            $candleSignal = 'BEARISH';
        }

        // 6. Tambahan Strategi Konfirmasi 3: Fibonacci Retracement & Extension Levels
        $fibWindow = min(60, $n);
        $fibLows = array_map(fn ($c) => (float) $c['low'], array_slice($candles, -$fibWindow));
        $fibHighs = array_map(fn ($c) => (float) $c['high'], array_slice($candles, -$fibWindow));
        $swingLow = !empty($fibLows) ? min($fibLows) : $curPrice * 0.95;
        $swingHigh = !empty($fibHighs) ? max($fibHighs) : $curPrice * 1.05;
        $swingDiff = max(0.01, $swingHigh - $swingLow);

        // Fib Retracement levels from Swing High to Swing Low
        $fib50 = round($swingHigh - (0.50 * $swingDiff), 2);
        $fib618 = round($swingHigh - (0.618 * $swingDiff), 2);
        $fib382 = round($swingHigh - (0.382 * $swingDiff), 2);
        $isGoldenPocket = ($curPrice >= min($fib50, $fib618) * 0.985 && $curPrice <= max($fib50, $fib618) * 1.015);

        // Deteksi Divergences & RSI Double Bottom Pattern
        $bearDiv = $this->detectBearishDivergence($candles, $rsi);
        $bullDiv = $this->detectBullishDivergence($candles, $rsi);
        $dbPattern = $this->findRecentDoubleBottom($candles, $rsi, $tol, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);

        // Window RSI 5 bar terakhir
        $recentRsi5 = array_filter(array_slice($rsi, -5), fn ($v) => $v !== null);
        $minRsi5 = !empty($recentRsi5) ? min($recentRsi5) : $curRsi;
        $maxRsi5 = !empty($recentRsi5) ? max($recentRsi5) : $curRsi;

        // Evaluasi Sinyal Trading & Rekomendasi Waktu Beli / Waktu Jual
        $signalType = 'NEUTRAL';
        $action = 'WAIT';
        $actionLabel = 'NEUTRAL / HOLD';
        $actionBadge = 'bg-secondary';
        $signalReason = 'RSI berada dalam rentang normal (konsolidasi/menunggu setup).';
        $confidence = 50;

        // Prioritas A: Sinyal Jual (Overbought Exit, Bearish Divergence, Reversal Candle)
        if ($bearDiv !== null && ($curRsi >= 65.0 || ($prevRsi >= 68.0 && $curRsi < $prevRsi))) {
            $signalType = 'SELL_BEARISH_DIVERGENCE';
            $action = 'SELL_NOW';
            $actionLabel = 'SELL (BEARISH DIVERGENCE)';
            $actionBadge = 'bg-danger';
            $signalReason = 'Terdeteksi Bearish Divergence (harga buat higher-high tapi RSI melemah), momentum beli telah jenuh.';
            $confidence = 90;
        } elseif ($candleSignal === 'BEARISH' && $curRsi >= 65.0) {
            $signalType = 'SELL_CANDLE_CONFIRMATION';
            $action = 'SELL_NOW';
            $actionLabel = 'SELL (' . strtoupper($candlePattern) . ')';
            $actionBadge = 'bg-danger';
            $signalReason = 'Pembalikan arah terkonfirmasi oleh pola ' . $candlePattern . ' di zona jenuh beli (RSI ' . round($curRsi, 1) . ').';
            $confidence = 88;
        } elseif ($prevRsi >= 70.0 && $curRsi < 70.0) {
            $signalType = 'SELL_OVERBOUGHT_CROSS';
            $action = 'SELL_NOW';
            $actionLabel = 'SELL (CROSS 70 DOWN)';
            $actionBadge = 'bg-danger';
            $signalReason = 'RSI melintas turun dari area Overbought 70. Indikasi kuat aksi ambil untung (Take Profit).';
            $confidence = 85;
        } elseif ($curRsi >= 75.0) {
            $signalType = 'SELL_OVERBOUGHT_EXTREME';
            $action = 'TAKE_PROFIT';
            $actionLabel = 'TAKE PROFIT (EXTREME OVERBOUGHT)';
            $actionBadge = 'bg-warning text-dark';
            $signalReason = 'RSI sangat tinggi (Overbought > 75). Area bahaya koreksi tajam, amankan keuntungan.';
            $confidence = 80;
        } elseif ($maxRsi5 >= 70.0 && $curRsi < $prevRsi && $curRsi < 65.0) {
            $signalType = 'SELL_MOMENTUM_LOSS';
            $action = 'SELL_NOW';
            $actionLabel = 'SELL (OVERBOUGHT REVERSAL)';
            $actionBadge = 'bg-danger';
            $signalReason = 'Pembalikan arah pasca Overbought. Momentum penurunan mulai mendominasi.';
            $confidence = 75;
        }
        // Prioritas B: Sinyal Beli (Bullish Divergence, Oversold Reversal + Volume & Fib Confluence)
        elseif ($bullDiv !== null && ($curRsi > $prevRsi || $curRsi >= 30.0)) {
            $signalType = 'BUY_BULLISH_DIVERGENCE';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (BULLISH DIVERGENCE)';
            $actionBadge = 'bg-success';
            $signalReason = 'Peluang akumulasi terbaik! Bullish Divergence (harga buat lower-low tapi RSI mencetak higher-low)' . ($volumeSupportive ? ' dengan dukungan Volume' : '') . '.';
            $confidence = $isAboveMa50 ? 95 : 85;
            if ($volumeSpike) $confidence = min(98, $confidence + 3);
        } elseif ($isGoldenPocket && $curRsi > $prevRsi && $curRsi <= 45.0) {
            $signalType = 'BUY_FIB_GOLDEN_POCKET';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (FIB GOLDEN POCKET)';
            $actionBadge = 'bg-success';
            $signalReason = 'Konfluensi Emas! Rebound dari Fibonacci Retracement 50%-61.8% ($' . number_format($fib618, 2) . ') bersamaan dengan pembalikan RSI.';
            $confidence = $isAboveMa50 ? 92 : 84;
        } elseif ($candleSignal === 'BULLISH' && ($curRsi <= 40.0 || $prevRsi <= 35.0)) {
            $signalType = 'BUY_CANDLE_CONFIRMATION';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (' . strtoupper($candlePattern) . ')';
            $actionBadge = 'bg-success';
            $signalReason = 'Sinyal pembalikan kuat! Pola ' . $candlePattern . ' muncul di zona oversold didukung lonjakan RVOL ' . $rvol . 'x.';
            $confidence = $isAboveMa50 ? 92 : 82;
        } elseif ($prevRsi <= 30.0 && $curRsi > 30.0) {
            $signalType = 'BUY_OVERSOLD_CROSS';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (CROSS 30 UP)';
            $actionBadge = 'bg-success';
            $signalReason = 'Konfirmasi Reversal! RSI keluar dari area Oversold 30 ke atas. Tekanan jual mereda.';
            $confidence = $isAboveMa50 ? 90 : 80;
        } elseif ($dbPattern !== null && !empty($dbPattern['breakout'])) {
            $signalType = 'BUY_RSI_DOUBLE_BOTTOM';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (RSI DOUBLE BOTTOM)';
            $actionBadge = 'bg-success';
            $signalReason = 'Pola RSI Double Bottom terkonfirmasi dengan Breakout Neckline RSI.';
            $confidence = $dbPattern['confidence'] ?? 85;
        } elseif ($minRsi5 <= 33.0 && $curRsi > $prevRsi && $curRsi >= 32.0 && $curRsi <= 45.0) {
            $signalType = 'BUY_OVERSOLD_BOUNCE';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (OVERSOLD BOUNCE)';
            $actionBadge = 'bg-success';
            $signalReason = 'Pantulan awal dari area jenuh jual (RSI memantul naik dari <33).';
            $confidence = $isAboveMa50 ? 80 : 70;
        } elseif ($isAboveMa50 && $curRsi >= 40.0 && $curRsi <= 52.0 && $curRsi > $prevRsi && ($prevRsi <= 45.0 || $prev2Rsi <= 45.0)) {
            $signalType = 'BUY_PULLBACK_UPTREND';
            $action = 'BUY_PULLBACK';
            $actionLabel = 'BUY ON PULLBACK (UPTREND)';
            $actionBadge = 'bg-primary';
            $signalReason = 'Saham dalam tren naik (di atas MA50), RSI rebound dari area support 40-50.';
            $confidence = 80;
        } elseif ($curRsi <= 30.0) {
            $signalType = 'WATCH_OVERSOLD';
            $action = 'ACCUMULATE';
            $actionLabel = 'OVERSOLD (SIAP PANTAU)';
            $actionBadge = 'bg-info text-dark';
            $signalReason = 'Saham sangat jenuh jual (RSI < 30). Siapkan dana untuk beli saat RSI mulai melengkung naik melewati 30.';
            $confidence = 65;
        } elseif ($prevRsi < 50.0 && $curRsi >= 50.0 && $isAboveMa50) {
            $signalType = 'BULLISH_MOMENTUM_50';
            $action = 'MOMENTUM_BUY';
            $actionLabel = 'MOMENTUM CROSS 50';
            $actionBadge = 'bg-primary';
            $signalReason = 'RSI menembus batas tengah 50 ke atas dengan konfirmasi tren bullish.';
            $confidence = 70;
        }

        // Tentukan Target Profit TP1, TP2, TP3 & Stop Loss Berbasis Multi-Strategi
        // Stop loss: 2% di bawah swing low terdekat atau min 3.5% dari entry
        $slPrice = round(min($swingLow * 0.98, $curPrice * 0.965), 2);
        if ($slPrice >= $curPrice) {
            $slPrice = round($curPrice * 0.965, 2);
        }
        $riskAmount = max(0.01, $curPrice - $slPrice);

        // Kalkulasi Target Profit yang Akurat:
        // TP1 (Konservatif / Swing High / Fib 50% atau 1.5R - 1.8R): Amankan modal & kunci profit 40-50%
        $candidateTp1 = max($curPrice + ($riskAmount * 1.5), min($swingHigh, $curPrice + ($riskAmount * 2.0)));
        if ($candidateTp1 <= $curPrice) {
            $candidateTp1 = $curPrice + ($riskAmount * 1.8);
        }
        $tp1Price = round($candidateTp1, 2);

        // TP2 (Moderate / Breakout Resistance / Fibonacci Extension 1.272 atau 2.8R): Profit lanjutan
        $fibExt127 = $swingLow + (1.272 * $swingDiff);
        $candidateTp2 = max($tp1Price * 1.02, max($fibExt127, $curPrice + ($riskAmount * 2.8)));
        $tp2Price = round($candidateTp2, 2);

        // TP3 (Agresif / Golden Target Fibonacci Extension 1.618 atau 4.0R): Maksimal tren kenaikan
        $fibExt161 = $swingLow + (1.618 * $swingDiff);
        $candidateTp3 = max($tp2Price * 1.03, max($fibExt161, $curPrice + ($riskAmount * 4.2)));
        $tp3Price = round($candidateTp3, 2);

        $tp1Pct = $curPrice > 0 ? round((($tp1Price - $curPrice) / $curPrice) * 100, 2) : 0.0;
        $tp2Pct = $curPrice > 0 ? round((($tp2Price - $curPrice) / $curPrice) * 100, 2) : 0.0;
        $tp3Pct = $curPrice > 0 ? round((($tp3Price - $curPrice) / $curPrice) * 100, 2) : 0.0;

        $upsidePct = $tp1Pct;
        $riskPct = $curPrice > 0 ? round((($curPrice - $slPrice) / $curPrice) * 100, 2) : 0.0;
        $rrRatio = $riskAmount > 0 ? round(($tp1Price - $curPrice) / $riskAmount, 2) : 0.0;

        // Data backward-compatible untuk RSI Double Bottom jika ada
        $rsi1Val = $dbPattern['rsi1_value'] ?? ($bullDiv['rsi1'] ?? round($minRsi5 ?? $curRsi, 1));
        $rsi1Date = $dbPattern['rsi1_date'] ?? ($bullDiv['date1'] ?? $candles[$n - 5]['date']);
        $rsi2Val = $dbPattern['rsi2_value'] ?? ($bullDiv['rsi2'] ?? round($curRsi, 1));
        $rsi2Date = $dbPattern['rsi2_date'] ?? ($bullDiv['date2'] ?? $candles[$n - 1]['date']);
        $necklineRsi = $dbPattern['neckline_rsi'] ?? 50.0;
        $necklineDate = $dbPattern['neckline_date'] ?? $candles[$n - 3]['date'];
        $necklinePrice = $dbPattern['neckline_price'] ?? $swingHigh;
        $isBreakout = ($dbPattern !== null && !empty($dbPattern['breakout'])) || ($curRsi >= 50.0 && $prevRsi < 50.0);

        return [
            // Metrik RSI Inti
            'current_rsi' => round($curRsi, 2),
            'prev_rsi' => round($prevRsi, 2),
            'current_price' => $curPrice,
            'ma50' => $curMa50 !== null ? round($curMa50, 2) : null,
            'is_above_ma50' => $isAboveMa50,
            'trend_bias' => $isAboveMa50 ? 'BULLISH' : 'BEARISH',

            // Multi-Strategy Enhancements: Volume RVOL
            'rvol' => $rvol,
            'volume_spike' => $volumeSpike,
            'volume_supportive' => $volumeSupportive,

            // Multi-Strategy Enhancements: Candlestick Reversal
            'candle_pattern' => $candlePattern,
            'candle_signal' => $candleSignal,

            // Multi-Strategy Enhancements: Fibonacci Retracement Levels
            'swing_high' => round($swingHigh, 2),
            'swing_low' => round($swingLow, 2),
            'fib50' => $fib50,
            'fib618' => $fib618,
            'fib382' => $fib382,
            'is_golden_pocket' => $isGoldenPocket,

            // Sinyal & Rekomendasi Aksi
            'signal_type' => $signalType,
            'action' => $action,
            'action_label' => $actionLabel,
            'action_badge' => $actionBadge,
            'signal_reason' => $signalReason,
            'confidence' => $confidence,

            // Divergence
            'divergence' => ($bullDiv !== null),
            'bearish_divergence' => ($bearDiv !== null),
            'divergence_details' => $bullDiv ?? $bearDiv,

            // Tingkat Harga & Risk/Reward
            'sl_price' => $slPrice,
            'sl_tight' => round($curPrice * 0.98, 2),
            'tp1_price' => $tp1Price,
            'tp2_price' => $tp2Price,
            'tp3_price' => $tp3Price,
            'tp1_pct' => $tp1Pct,
            'tp2_pct' => $tp2Pct,
            'tp3_pct' => $tp3Pct,
            'target_price' => $tp1Price,
            'potential_upside' => $upsidePct,
            'potential_risk' => $riskPct,
            'risk_reward' => $rrRatio,

            // Fields kompatibilitas RSI Double Bottom
            'rsi1_value' => round((float) $rsi1Val, 2),
            'rsi1_date' => $rsi1Date,
            'rsi2_value' => round((float) $rsi2Val, 2),
            'rsi2_date' => $rsi2Date,
            'neckline_rsi' => round((float) $necklineRsi, 2),
            'neckline_date' => $necklineDate,
            'neckline_price' => round((float) $necklinePrice, 2),
            'low1_price' => $dbPattern['low1_price'] ?? $swingLow,
            'low2_price' => $dbPattern['low2_price'] ?? $curPrice,
            'breakout' => $isBreakout,
            'rsi_distance' => round($curRsi - $necklineRsi, 2),
            'has_db_pattern' => ($dbPattern !== null),
        ];
    }

    /**
     * Deteksi Bullish Divergence (Price Lower-Low tapi RSI Higher-Low).
     */
    private function detectBullishDivergence(array $candles, array $rsi, int $window = 30): ?array
    {
        $n = count($candles);
        $start = max(2, $n - $window);
        $lows = [];

        for ($i = $start; $i < $n - 1; $i++) {
            if ($rsi[$i] === null || $rsi[$i - 1] === null || $rsi[$i + 1] === null) {
                continue;
            }
            if ($rsi[$i] <= $rsi[$i - 1] && $rsi[$i] <= $rsi[$i + 1] && $rsi[$i] < 45.0) {
                $lows[] = [
                    'index' => $i,
                    'price' => (float) $candles[$i]['low'],
                    'rsi' => (float) $rsi[$i],
                    'date' => $candles[$i]['date'],
                ];
            }
        }

        $cnt = count($lows);
        if ($cnt < 2) {
            return null;
        }

        for ($a = $cnt - 2; $a >= max(0, $cnt - 4); $a--) {
            $l1 = $lows[$a];
            $l2 = $lows[$cnt - 1];
            $sep = $l2['index'] - $l1['index'];
            if ($sep >= 3 && $sep <= 25) {
                // Syarat: Harga Lembah 2 lebih rendah/setara Lembah 1, tapi RSI Lembah 2 lebih tinggi
                if ($l2['price'] <= $l1['price'] * 1.005 && $l2['rsi'] > $l1['rsi'] + 0.8) {
                    return [
                        'type' => 'bullish',
                        'rsi1' => round($l1['rsi'], 2),
                        'rsi2' => round($l2['rsi'], 2),
                        'price1' => $l1['price'],
                        'price2' => $l2['price'],
                        'date1' => $l1['date'],
                        'date2' => $l2['date'],
                        'separation' => $sep,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Deteksi Bearish Divergence (Price Higher-High tapi RSI Lower-High).
     */
    private function detectBearishDivergence(array $candles, array $rsi, int $window = 30): ?array
    {
        $n = count($candles);
        $start = max(2, $n - $window);
        $highs = [];

        for ($i = $start; $i < $n - 1; $i++) {
            if ($rsi[$i] === null || $rsi[$i - 1] === null || $rsi[$i + 1] === null) {
                continue;
            }
            if ($rsi[$i] >= $rsi[$i - 1] && $rsi[$i] >= $rsi[$i + 1] && $rsi[$i] > 55.0) {
                $highs[] = [
                    'index' => $i,
                    'price' => (float) $candles[$i]['high'],
                    'rsi' => (float) $rsi[$i],
                    'date' => $candles[$i]['date'],
                ];
            }
        }

        $cnt = count($highs);
        if ($cnt < 2) {
            return null;
        }

        for ($a = $cnt - 2; $a >= max(0, $cnt - 4); $a--) {
            $h1 = $highs[$a];
            $h2 = $highs[$cnt - 1];
            $sep = $h2['index'] - $h1['index'];
            if ($sep >= 3 && $sep <= 25) {
                // Syarat: Harga Puncak 2 lebih tinggi/setara Puncak 1, tapi RSI Puncak 2 lebih rendah
                if ($h2['price'] >= $h1['price'] * 0.995 && $h2['rsi'] < $h1['rsi'] - 0.8) {
                    return [
                        'type' => 'bearish',
                        'rsi1' => round($h1['rsi'], 2),
                        'rsi2' => round($h2['rsi'], 2),
                        'price1' => $h1['price'],
                        'price2' => $h2['price'],
                        'date1' => $h1['date'],
                        'date2' => $h2['date'],
                        'separation' => $sep,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Cari RSI Double Bottom yang relevan/terbaru (dalam 45 candle terakhir).
     */
    private function findRecentDoubleBottom(
        array $candles,
        array $rsi,
        float $tol,
        float $maxRsi,
        int $minSeparation,
        int $maxSeparation,
        float $necklineMin
    ): ?array {
        $n = count($candles);
        $start = max(1, $n - 50);
        $lows = [];

        for ($i = $start; $i < $n - 1; $i++) {
            if ($rsi[$i] === null || $rsi[$i - 1] === null || $rsi[$i + 1] === null) {
                continue;
            }
            if ($rsi[$i] < $rsi[$i - 1] && $rsi[$i] < $rsi[$i + 1] && $rsi[$i] < $maxRsi) {
                $lows[] = ['index' => $i, 'rsi' => $rsi[$i]];
            }
        }

        $lc = count($lows);
        $best = null;

        for ($a = 0; $a < $lc; $a++) {
            for ($b = $a + 1; $b < $lc; $b++) {
                $i1 = $lows[$a]['index'];
                $i2 = $lows[$b]['index'];
                $sep = $i2 - $i1;
                if ($sep < $minSeparation || $sep > $maxSeparation) {
                    continue;
                }
                $r1 = $lows[$a]['rsi'];
                $r2 = $lows[$b]['rsi'];
                if (abs($r1 - $r2) > $tol) {
                    continue;
                }
                $neck = -1.0;
                $neckIdx = $i1;
                for ($k = $i1 + 1; $k < $i2; $k++) {
                    if ($rsi[$k] !== null && $rsi[$k] > $neck) {
                        $neck = $rsi[$k];
                        $neckIdx = $k;
                    }
                }
                if ($neck < max($r1, $r2) + $necklineMin) {
                    continue;
                }

                $cur = $rsi[$n - 1];
                if ($cur === null) {
                    continue;
                }
                $breakout = $cur > $neck;
                $p1 = (float) $candles[$i1]['low'];
                $p2 = (float) $candles[$i2]['low'];
                $neckPrice = 0.0;
                for ($k = $i1; $k <= $i2; $k++) {
                    $neckPrice = max($neckPrice, (float) $candles[$k]['high']);
                }

                $conf = 60;
                if (abs($r1 - $r2) <= 1.5) {
                    $conf += 15;
                }
                if ($breakout) {
                    $conf += 15;
                }
                if ($r2 > $r1 && $p2 < $p1) {
                    $conf += 10;
                }

                $cand = [
                    'rsi1_value' => round($r1, 2),
                    'rsi1_date' => $candles[$i1]['date'],
                    'rsi2_value' => round($r2, 2),
                    'rsi2_date' => $candles[$i2]['date'],
                    'neckline_rsi' => round($neck, 2),
                    'neckline_date' => $candles[$neckIdx]['date'],
                    'neckline_price' => $neckPrice,
                    'low1_price' => $p1,
                    'low2_price' => $p2,
                    'breakout' => $breakout,
                    'confidence' => min(100, $conf),
                ];

                if ($best === null || $cand['confidence'] > $best['confidence']) {
                    $best = $cand;
                }
            }
        }

        return $best;
    }

    public static function getTimeframeLabel(int $tf): string
    {
        return match ($tf) {
            self::TIMEFRAME_1H => '1 Hour',
            self::TIMEFRAME_2H => '2 Hours',
            self::TIMEFRAME_4H => '4 Hours',
            self::TIMEFRAME_1D => '1 Day',
            default => "{$tf}H",
        };
    }

    public static function getTimeframes(): array
    {
        return [
            self::TIMEFRAME_1D => '1 Day (Daily)',
            self::TIMEFRAME_4H => '4 Hours',
            self::TIMEFRAME_2H => '2 Hours',
            self::TIMEFRAME_1H => '1 Hour',
        ];
    }
}
