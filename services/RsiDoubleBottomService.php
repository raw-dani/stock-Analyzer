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
    private const MAX_LOOKBACK = 400;
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
     * Dilengkapi warm-up history penuh untuk kestabilan perhitungan Wilder RSI dan Moving Average.
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

        // Kalkulasi Wilder RSI dan Moving Average pada seluruh data candle yang tersedia
        // agar initial exponential decay stabil 100% sebelum dipotong ke lookback window.
        $allCloses = array_map(fn ($c) => (float) $c['close'], $candles);
        $allRsi = RsiService::calculate($allCloses, $period);
        $allEma20 = $this->calculateEma($allCloses, 20);
        $allMa50 = $this->calculateSma($allCloses, 50);

        if (count($candles) > $lookback) {
            $sliceOffset = count($candles) - $lookback;
            $candles = array_slice($candles, $sliceOffset);
            $rsi = array_slice($allRsi, $sliceOffset);
            $ema20 = array_slice($allEma20, $sliceOffset);
            $ma50 = array_slice($allMa50, $sliceOffset);
        } else {
            $rsi = $allRsi;
            $ema20 = $allEma20;
            $ma50 = $allMa50;
        }

        $found = $this->analyzeRsiStrategy($candles, $rsi, $ema20, $ma50, $tol, $maxRsi, $minSeparation, $maxSeparation, $necklineMin, $tf);
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
            $found['ema20_series'] = $ema20;
            $found['ma50_series'] = $ma50;
        }

        return $found;
    }

    /**
     * Hitung EMA (Exponential Moving Average) untuk dynamic support/resistance (terutama 2H).
     */
    private function calculateEma(array $data, int $period): array
    {
        $n = count($data);
        $ema = array_fill(0, $n, null);
        if ($n < $period) {
            return $ema;
        }
        $multiplier = 2.0 / ($period + 1.0);
        $sum = array_sum(array_slice($data, 0, $period));
        $ema[$period - 1] = round($sum / $period, 4);

        for ($i = $period; $i < $n; $i++) {
            $prev = $ema[$i - 1];
            $ema[$i] = round(((float)$data[$i] - $prev) * $multiplier + $prev, 4);
        }
        return $ema;
    }

    /**
     * Hitung SMA (Simple Moving Average) untuk trend filter.
     */
    private function calculateSma(array $data, int $period): array
    {
        $n = count($data);
        $sma = array_fill(0, $n, null);
        if ($n < $period) {
            if ($n >= 15) {
                $avg = array_sum($data) / $n;
                return array_fill(0, $n, round($avg, 4));
            }
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

    /**
     * Memuat candle teragregasi secara session-aware dan bebas noise tick snapshot.
     */
    private function loadCandles(int $stockId, int $tf, int $lookback): array
    {
        if ($tf === self::TIMEFRAME_1D) {
            return [$this->dailyCandles($stockId, max($lookback, 300)), false];
        }

        try {
            $rows = IntradayPrice::find()
                ->where(['stock_id' => $stockId, 'timeframe' => '1h'])
                ->orderBy(['datetime' => SORT_DESC])
                ->limit(max($lookback * 4, 1000))
                ->asArray()
                ->all();
        } catch (\Throwable $e) {
            \Yii::warning("Intraday data unavailable for RSI scan {$stockId}: " . $e->getMessage(), 'app\services\rsidoublebottom');
            return [$this->dailyCandles($stockId, max($lookback, 300)), true];
        }

        if (empty($rows)) {
            return [$this->dailyCandles($stockId, max($lookback, 300)), true];
        }

        $rows = array_reverse($rows);

        // Filter noise: buang quote snapshot flat yang ber-volume 0 dengan detik/menit ganjil
        $cleaned = [];
        foreach ($rows as $b) {
            $dt = $b['datetime'];
            $sec = (int) substr($dt, 17, 2);
            $min = (int) substr($dt, 14, 2);
            $vol = (int) $b['volume'];
            $isFlat = ((float)$b['open'] === (float)$b['close'] && (float)$b['high'] === (float)$b['low']);
            if ($vol === 0 && $isFlat && ($sec !== 0 || !in_array($min, [0, 30], true))) {
                continue;
            }
            $cleaned[] = $b;
        }

        if (count($cleaned) < 20) {
            return [$this->dailyCandles($stockId, max($lookback, 300)), true];
        }

        // Agregasi session-aware berbasis bursa AS (09:30 - 16:00 EST)
        if ($tf === self::TIMEFRAME_2H) {
            $candles = $this->aggregate2H($cleaned);
        } elseif ($tf === self::TIMEFRAME_4H) {
            $candles = $this->aggregate4H($cleaned);
        } elseif ($tf === self::TIMEFRAME_1H) {
            $candles = $this->aggregateHourly($cleaned);
        } else {
            $candles = $this->aggregate2H($cleaned);
        }

        if (count($candles) >= 15) {
            return [$candles, false];
        }

        return [$this->dailyCandles($stockId, max($lookback, 300)), true];
    }

    /**
     * Agregasi 1H: hindari duplikasi sub-jam pada jam yang sama.
     */
    private function aggregateHourly(array $bars): array
    {
        $buckets = [];
        foreach ($bars as $b) {
            $key = substr($b['datetime'], 0, 13) . ':00:00';
            $buckets[$key][] = $b;
        }
        $out = [];
        foreach ($buckets as $dt => $chunk) {
            $out[] = [
                'open' => (float) $chunk[0]['open'],
                'high' => max(array_column($chunk, 'high')),
                'low' => min(array_column($chunk, 'low')),
                'close' => (float) end($chunk)['close'],
                'volume' => array_sum(array_column($chunk, 'volume')),
                'date' => $dt,
            ];
        }
        return $out;
    }

    /**
     * Agregasi 2H Session-Aware:
     * Menjaga batas hari (day boundaries) dan membagi sesi trading AS (09:30-16:00) ke 4 blok teratur:
     * - 11:30 (Morning Open: 09:30 - 11:30)
     * - 13:30 (Midday: 11:30 - 13:30)
     * - 15:30 (Afternoon: 13:30 - 15:30)
     * - 16:00 (Closing Bell: 15:30 - 16:00)
     */
    private function aggregate2H(array $bars): array
    {
        $buckets = [];
        foreach ($bars as $b) {
            $date = substr($b['datetime'], 0, 10);
            $hour = (int) substr($b['datetime'], 11, 2);
            $min = (int) substr($b['datetime'], 14, 2);
            $totalMinutes = $hour * 60 + $min;

            if ($totalMinutes < 11 * 60 + 30) {
                $slot = '11:30:00';
            } elseif ($totalMinutes < 13 * 60 + 30) {
                $slot = '13:30:00';
            } elseif ($totalMinutes < 15 * 60 + 30) {
                $slot = '15:30:00';
            } else {
                $slot = '16:00:00';
            }

            $key = "{$date} {$slot}";
            $buckets[$key][] = $b;
        }

        $out = [];
        foreach ($buckets as $dt => $chunk) {
            $out[] = [
                'open' => (float) $chunk[0]['open'],
                'high' => max(array_column($chunk, 'high')),
                'low' => min(array_column($chunk, 'low')),
                'close' => (float) end($chunk)['close'],
                'volume' => array_sum(array_column($chunk, 'volume')),
                'date' => $dt,
            ];
        }
        return $out;
    }

    /**
     * Agregasi 4H Session-Aware:
     * Membagi hari trading AS ke 2 blok: 09:30-13:30 (Morning/Midday) dan 13:30-16:00 (Afternoon/Close).
     */
    private function aggregate4H(array $bars): array
    {
        $buckets = [];
        foreach ($bars as $b) {
            $date = substr($b['datetime'], 0, 10);
            $hour = (int) substr($b['datetime'], 11, 2);
            $min = (int) substr($b['datetime'], 14, 2);
            $totalMinutes = $hour * 60 + $min;

            if ($totalMinutes < 13 * 60 + 30) {
                $slot = '13:30:00';
            } else {
                $slot = '16:00:00';
            }

            $key = "{$date} {$slot}";
            $buckets[$key][] = $b;
        }

        $out = [];
        foreach ($buckets as $dt => $chunk) {
            $out[] = [
                'open' => (float) $chunk[0]['open'],
                'high' => max(array_column($chunk, 'high')),
                'low' => min(array_column($chunk, 'low')),
                'close' => (float) end($chunk)['close'],
                'volume' => array_sum(array_column($chunk, 'volume')),
                'date' => $dt,
            ];
        }
        return $out;
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

    /**
     * Analisis Strategi RSI Lengkap: Beli, Jual, Divergence, dan Pola Double Bottom/Top.
     * Dioptimalkan dengan filter kesegaran sinyal (freshness gate), konfirmasi EMA20 & SMA50,
     * serta kalibrasi presisi untuk timeframe 2H.
     */
    private function analyzeRsiStrategy(
        array $candles,
        array $rsi,
        array $ema20,
        array $ma50,
        float $tol,
        float $maxRsi,
        int $minSeparation,
        int $maxSeparation,
        float $necklineMin,
        int $tf = 24
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
        $curEma20 = $ema20[$n - 1] ?? null;
        $curMa50 = $ma50[$n - 1] ?? null;

        $isAboveEma20 = ($curEma20 !== null) ? ($curPrice >= $curEma20) : true;
        $isAboveMa50 = ($curMa50 !== null) ? ($curPrice >= $curMa50) : true;

        if ($isAboveEma20 && $isAboveMa50) {
            $trendBias = 'STRONG_BULLISH';
        } elseif ($isAboveMa50 || $isAboveEma20) {
            $trendBias = 'BULLISH';
        } elseif (!$isAboveEma20 && !$isAboveMa50) {
            $trendBias = 'BEARISH';
        } else {
            $trendBias = 'NEUTRAL';
        }

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

        // Deteksi Divergences & RSI Double Bottom Pattern dengan timeframe context
        $bearDiv = $this->detectBearishDivergence($candles, $rsi, $tf);
        $bullDiv = $this->detectBullishDivergence($candles, $rsi, $tf);
        $dbPattern = $this->findRecentDoubleBottom($candles, $rsi, $tol, $maxRsi, $minSeparation, $maxSeparation, $necklineMin, $tf);

        // Window RSI 5 bar terakhir
        $recentRsi5 = array_filter(array_slice($rsi, -5), fn ($v) => $v !== null);
        $minRsi5 = !empty($recentRsi5) ? min($recentRsi5) : $curRsi;
        $maxRsi5 = !empty($recentRsi5) ? max($recentRsi5) : $curRsi;

        // Evaluasi Sinyal Trading & Rekomendasi Waktu Beli / Waktu Jual Berbasis Presisi
        $signalType = 'NEUTRAL';
        $action = 'WAIT';
        $actionLabel = 'NEUTRAL / HOLD';
        $actionBadge = 'bg-secondary';
        $signalReason = 'RSI berada dalam rentang normal (konsolidasi/menunggu setup).';
        $confidence = 50;

        // Prioritas A: Sinyal Jual & Ambil Untung (Exit / Take Profit)
        if ($bearDiv !== null && !empty($bearDiv['is_fresh']) && ($curRsi >= 60.0 || ($prevRsi >= 65.0 && $curRsi < $prevRsi))) {
            $signalType = 'SELL_BEARISH_DIVERGENCE';
            $action = 'SELL_NOW';
            $actionLabel = 'SELL (BEARISH DIVERGENCE)';
            $actionBadge = 'bg-danger';
            $signalReason = 'Terdeteksi Bearish Divergence segar (harga buat higher-high tapi RSI melemah), momentum beli telah jenuh.';
            $confidence = 92;
        } elseif ($candleSignal === 'BEARISH' && $curRsi >= 62.0) {
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
            $confidence = 88;
        } elseif ($curRsi >= 75.0) {
            $signalType = 'SELL_OVERBOUGHT_EXTREME';
            $action = 'TAKE_PROFIT';
            $actionLabel = 'TAKE PROFIT (EXTREME OVERBOUGHT)';
            $actionBadge = 'bg-warning text-dark';
            $signalReason = 'RSI sangat tinggi (Overbought > 75). Area bahaya koreksi tajam, segera amankan keuntungan.';
            $confidence = 85;
        } elseif ($maxRsi5 >= 68.0 && $curRsi < $prevRsi && $curRsi < 63.0) {
            $signalType = 'SELL_MOMENTUM_LOSS';
            $action = 'SELL_NOW';
            $actionLabel = 'SELL (OVERBOUGHT REVERSAL)';
            $actionBadge = 'bg-danger';
            $signalReason = 'Pembalikan arah pasca Overbought. Momentum penurunan mulai mendominasi.';
            $confidence = 78;
        }
        // Prioritas B: Divergensi yang sudah berjalan jauh (Target Kenaikan Telah Tercapai)
        elseif ($bullDiv !== null && empty($bullDiv['is_fresh']) && $curRsi >= 64.0) {
            $signalType = 'TAKE_PROFIT_DIV_TARGET';
            $action = 'TAKE_PROFIT';
            $actionLabel = 'TAKE PROFIT (DIV TARGET)';
            $actionBadge = 'bg-warning text-dark';
            $signalReason = 'Target rally pasca Bullish Divergence telah tercapai! RSI telah melonjak ke area jenuh beli (' . round($curRsi, 1) . '). Kunci profit Anda.';
            $confidence = 86;
        } elseif ($bullDiv !== null && empty($bullDiv['is_fresh']) && $curRsi >= 54.0) {
            $signalType = 'HOLD_DIV_RALLY';
            $action = 'WAIT';
            $actionLabel = 'HOLD (RALLY IN PROGRESS)';
            $actionBadge = 'bg-info text-dark';
            $signalReason = 'Rally pasca Bullish Divergence (' . ($bullDiv['bars_ago'] ?? 0) . ' bar lalu) sedang berjalan. Pertahankan posisi dengan trailing stop.';
            $confidence = 76;
        }
        // Prioritas C: Sinyal Beli Segar (Fresh Bullish Divergence & Double Bottom Reversal)
        elseif ($bullDiv !== null && !empty($bullDiv['is_fresh']) && $curRsi <= 55.0 && ($curRsi > $prevRsi || $curRsi >= 30.0)) {
            $signalType = 'BUY_BULLISH_DIVERGENCE';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (BULLISH DIVERGENCE)';
            $actionBadge = 'bg-success';
            $signalReason = 'Peluang akumulasi terbaik! Bullish Divergence segar (' . ($bullDiv['bars_ago'] ?? 0) . ' bar lalu) di mana harga mencetak low baru tapi RSI rebound solid' . ($volumeSupportive ? ' didukung volume' : '') . '.';
            $confidence = $isAboveEma20 ? 95 : 88;
            if ($volumeSpike) $confidence = min(98, $confidence + 3);
        } elseif ($dbPattern !== null && !empty($dbPattern['is_shakeout_low']) && !empty($dbPattern['is_fresh_shakeout']) && ($curRsi > $prevRsi || !empty($dbPattern['breakout']))) {
            $signalType = 'BUY_SHAKEOUT_LOW';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (SHAKEOUT LOW)';
            $actionBadge = 'bg-success';
            $signalReason = 'Liquidity Sweep Terkonfirmasi! Terjadi Shakeout Low (-' . ($dbPattern['shakeout_pct'] ?? 0) . '%) di mana harga sempat undercut support lalu memantul solid.';
            $confidence = $isAboveEma20 ? 94 : 86;
            if ($volumeSpike) $confidence = min(98, $confidence + 3);
        } elseif ($isGoldenPocket && $curRsi > $prevRsi && $curRsi <= 48.0) {
            $signalType = 'BUY_FIB_GOLDEN_POCKET';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (FIB GOLDEN POCKET)';
            $actionBadge = 'bg-success';
            $signalReason = 'Konfluensi Emas! Rebound dari Fibonacci Retracement 50%-61.8% ($' . number_format($fib618, 2) . ') bersamaan dengan pembalikan RSI.';
            $confidence = $isAboveEma20 ? 92 : 84;
        } elseif ($candleSignal === 'BULLISH' && ($curRsi <= 40.0 || $prevRsi <= 35.0)) {
            $signalType = 'BUY_CANDLE_CONFIRMATION';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (' . strtoupper($candlePattern) . ')';
            $actionBadge = 'bg-success';
            $signalReason = 'Sinyal pembalikan kuat! Pola ' . $candlePattern . ' muncul di zona oversold didukung lonjakan RVOL ' . $rvol . 'x.';
            $confidence = $isAboveEma20 ? 92 : 82;
        } elseif ($prevRsi <= 30.0 && $curRsi > 30.0) {
            $signalType = 'BUY_OVERSOLD_CROSS';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (CROSS 30 UP)';
            $actionBadge = 'bg-success';
            $signalReason = 'Konfirmasi Reversal! RSI keluar dari area Oversold 30 ke atas. Tekanan jual mereda.';
            $confidence = $isAboveEma20 ? 90 : 82;
        } elseif ($dbPattern !== null && !empty($dbPattern['breakout']) && !empty($dbPattern['is_fresh_breakout']) && $curRsi <= 60.0) {
            $signalType = 'BUY_RSI_DOUBLE_BOTTOM';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (RSI DOUBLE BOTTOM)';
            $actionBadge = 'bg-success';
            $signalReason = 'Pola RSI Double Bottom terkonfirmasi dengan Breakout Neckline RSI secara segar.';
            $confidence = $dbPattern['confidence'] ?? 88;
        } elseif ($minRsi5 <= 33.0 && $curRsi > $prevRsi && $curRsi >= 32.0 && $curRsi <= 45.0) {
            $signalType = 'BUY_OVERSOLD_BOUNCE';
            $action = 'BUY_NOW';
            $actionLabel = 'BUY (OVERSOLD BOUNCE)';
            $actionBadge = 'bg-success';
            $signalReason = 'Pantulan awal dari area jenuh jual (RSI memantul naik dari <33).';
            $confidence = $isAboveEma20 ? 82 : 72;
        } elseif (($isAboveMa50 || $isAboveEma20) && $curRsi >= 40.0 && $curRsi <= 52.0 && $curRsi > $prevRsi && ($prevRsi <= 48.0 || $prev2Rsi <= 48.0)) {
            $signalType = 'BUY_PULLBACK_UPTREND';
            $action = 'BUY_PULLBACK';
            $actionLabel = 'BUY ON PULLBACK (UPTREND)';
            $actionBadge = 'bg-primary';
            $signalReason = 'Setup swing favorit! Saham dalam tren naik di atas EMA20/MA50, RSI memantul dari area support 40-50.';
            $confidence = 82;
        } elseif ($curRsi <= 30.0) {
            $signalType = 'WATCH_OVERSOLD';
            $action = 'ACCUMULATE';
            $actionLabel = 'OVERSOLD (SIAP PANTAU)';
            $actionBadge = 'bg-info text-dark';
            $signalReason = 'Saham sangat jenuh jual (RSI < 30). Siapkan dana untuk beli saat RSI mulai melengkung naik melewati 30.';
            $confidence = 68;
        } elseif ($prevRsi < 50.0 && $curRsi >= 50.0 && ($isAboveEma20 || $isAboveMa50)) {
            $signalType = 'BULLISH_MOMENTUM_50';
            $action = 'MOMENTUM_BUY';
            $actionLabel = 'MOMENTUM CROSS 50';
            $actionBadge = 'bg-primary';
            $signalReason = 'RSI menembus batas tengah 50 ke atas dengan konfirmasi tren bullish.';
            $confidence = 72;
        }

        // Tentukan Target Profit TP1, TP2, TP3 & Stop Loss Berbasis Multi-Strategi untuk Timeframe
        $is2H = ($tf === self::TIMEFRAME_2H);
        $slBuffer = $is2H ? 0.985 : 0.98; // 1.5% buffer di 2H
        $slMinPct = $is2H ? 0.975 : 0.965; // min 2.5% di 2H

        $slPrice = round(min($swingLow * $slBuffer, $curPrice * $slMinPct), 2);
        if ($slPrice >= $curPrice) {
            $slPrice = round($curPrice * $slMinPct, 2);
        }
        $riskAmount = max(0.01, $curPrice - $slPrice);

        // Kalkulasi Target Profit yang Akurat:
        // TP1 (Konservatif / Swing High / Ratio 1.5R - 1.8R): Kunci profit pertama
        $candidateTp1 = max($curPrice + ($riskAmount * 1.5), min($swingHigh, $curPrice + ($riskAmount * 1.8)));
        if ($candidateTp1 <= $curPrice) {
            $candidateTp1 = $curPrice + ($riskAmount * 1.6);
        }
        $tp1Price = round($candidateTp1, 2);

        // TP2 (Moderate / Breakout Resistance / Fibonacci Extension 1.272 atau 2.8R): Profit lanjutan
        $fibExt127 = $swingLow + (1.272 * $swingDiff);
        $candidateTp2 = max($tp1Price * 1.015, max($fibExt127, $curPrice + ($riskAmount * 2.8)));
        $tp2Price = round($candidateTp2, 2);

        // TP3 (Agresif / Golden Target Fibonacci Extension 1.618 atau 4.0R): Maksimal tren kenaikan
        $fibExt161 = $swingLow + (1.618 * $swingDiff);
        $candidateTp3 = max($tp2Price * 1.02, max($fibExt161, $curPrice + ($riskAmount * 4.0)));
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
            'ema20' => $curEma20 !== null ? round($curEma20, 2) : null,
            'is_above_ema20' => $isAboveEma20,
            'ma50' => $curMa50 !== null ? round($curMa50, 2) : null,
            'is_above_ma50' => $isAboveMa50,
            'trend_bias' => $trendBias,

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
            'divergence' => $bullDiv !== null ? $bullDiv : false,
            'bearish_divergence' => $bearDiv !== null ? $bearDiv : false,
            'is_bullish_divergence' => ($bullDiv !== null),
            'is_bearish_divergence' => ($bearDiv !== null),
            'divergence_details' => $bullDiv ?? $bearDiv,

            // Tingkat Harga & Risk/Reward
            'sl_price' => $slPrice,
            'sl_tight' => round($curPrice * 0.985, 2),
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
            'is_shakeout_low' => !empty($dbPattern['is_shakeout_low']),
            'shakeout_pct' => (float) ($dbPattern['shakeout_pct'] ?? 0.0),
        ];
    }

    /**
     * Deteksi Bullish Divergence (Price Lower-Low tapi RSI Higher-Low) dengan filter cycle integrity.
     */
    private function detectBullishDivergence(array $candles, array $rsi, int $tf = 24, int $window = 35): ?array
    {
        $n = count($candles);
        $start = max(2, $n - $window);
        $lows = [];

        for ($i = $start; $i < $n - 1; $i++) {
            if ($rsi[$i] === null || $rsi[$i - 1] === null || $rsi[$i + 1] === null) {
                continue;
            }
            if ($rsi[$i] <= $rsi[$i - 1] && $rsi[$i] <= $rsi[$i + 1] && $rsi[$i] < 48.0) {
                $lows[] = [
                    'index' => $i,
                    'price' => (float) $candles[$i]['low'],
                    'rsi' => (float) $rsi[$i],
                    'date' => $candles[$i]['date'],
                ];
            }
        }

        // Sertakan bar sebelum terakhir jika RSI sedang memantul naik
        $curRsi = $rsi[$n - 1];
        $prevRsi = $rsi[$n - 2] ?? null;
        if ($prevRsi !== null && $curRsi !== null && $prevRsi < 45.0 && $curRsi > $prevRsi) {
            $prevPrevRsi = $rsi[$n - 3] ?? $prevRsi;
            if ($prevRsi <= $prevPrevRsi) {
                $lows[] = [
                    'index' => $n - 2,
                    'price' => (float) $candles[$n - 2]['low'],
                    'rsi' => (float) $prevRsi,
                    'date' => $candles[$n - 2]['date'],
                ];
            }
        }

        // De-duplicate berdasarkan index
        $uniqueLows = [];
        foreach ($lows as $l) {
            $uniqueLows[$l['index']] = $l;
        }
        $lows = array_values($uniqueLows);
        usort($lows, fn ($a, $b) => $a['index'] <=> $b['index']);

        $cnt = count($lows);
        if ($cnt < 2) {
            return null;
        }

        $minSep = ($tf === self::TIMEFRAME_2H) ? 3 : (($tf === self::TIMEFRAME_4H) ? 3 : 4);
        $maxSep = ($tf === self::TIMEFRAME_2H) ? 24 : (($tf === self::TIMEFRAME_4H) ? 20 : 30);

        for ($a = $cnt - 2; $a >= max(0, $cnt - 4); $a--) {
            $l1 = $lows[$a];
            $l2 = $lows[$cnt - 1];
            $sep = $l2['index'] - $l1['index'];
            if ($sep >= $minSep && $sep <= $maxSep) {
                // Syarat: Harga Lembah 2 lebih rendah/setara Lembah 1, tapi RSI Lembah 2 lebih tinggi
                if ($l2['price'] <= $l1['price'] * 1.006 && $l2['rsi'] >= $l1['rsi'] + 0.8) {
                    $peakRsi = 0.0;
                    for ($k = $l1['index'] + 1; $k < $l2['index']; $k++) {
                        if ($rsi[$k] !== null && $rsi[$k] > $peakRsi) {
                            $peakRsi = $rsi[$k];
                        }
                    }

                    // Syarat integritas siklus koreksi: ada relief rebound (>=40.0) dan belum overbought (<=62.0)
                    if ($peakRsi >= 40.0 && $peakRsi <= 62.0) {
                        $barsAgo = ($n - 1) - $l2['index'];
                        return [
                            'type' => 'bullish',
                            'rsi1' => round($l1['rsi'], 2),
                            'rsi2' => round($l2['rsi'], 2),
                            'price1' => $l1['price'],
                            'price2' => $l2['price'],
                            'date1' => $l1['date'],
                            'date2' => $l2['date'],
                            'separation' => $sep,
                            'bars_ago' => $barsAgo,
                            'is_fresh' => ($barsAgo <= ($tf === self::TIMEFRAME_2H ? 5 : 4)),
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Deteksi Bearish Divergence (Price Higher-High tapi RSI Lower-High).
     */
    private function detectBearishDivergence(array $candles, array $rsi, int $tf = 24, int $window = 35): ?array
    {
        $n = count($candles);
        $start = max(2, $n - $window);
        $highs = [];

        for ($i = $start; $i < $n - 1; $i++) {
            if ($rsi[$i] === null || $rsi[$i - 1] === null || $rsi[$i + 1] === null) {
                continue;
            }
            if ($rsi[$i] >= $rsi[$i - 1] && $rsi[$i] >= $rsi[$i + 1] && $rsi[$i] > 54.0) {
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

        $minSep = ($tf === self::TIMEFRAME_2H) ? 3 : 4;
        $maxSep = ($tf === self::TIMEFRAME_2H) ? 24 : 30;

        for ($a = $cnt - 2; $a >= max(0, $cnt - 4); $a--) {
            $h1 = $highs[$a];
            $h2 = $highs[$cnt - 1];
            $sep = $h2['index'] - $h1['index'];
            if ($sep >= $minSep && $sep <= $maxSep) {
                // Syarat: Harga Puncak 2 lebih tinggi/setara Puncak 1, tapi RSI Puncak 2 lebih rendah
                if ($h2['price'] >= $h1['price'] * 0.994 && $h2['rsi'] <= $h1['rsi'] - 0.8) {
                    $troughRsi = 100.0;
                    for ($k = $h1['index'] + 1; $k < $h2['index']; $k++) {
                        if ($rsi[$k] !== null && $rsi[$k] < $troughRsi) {
                            $troughRsi = $rsi[$k];
                        }
                    }
                    if ($troughRsi <= 60.0 && $troughRsi >= 38.0) {
                        $barsAgo = ($n - 1) - $h2['index'];
                        return [
                            'type' => 'bearish',
                            'rsi1' => round($h1['rsi'], 2),
                            'rsi2' => round($h2['rsi'], 2),
                            'price1' => $h1['price'],
                            'price2' => $h2['price'],
                            'date1' => $h1['date'],
                            'date2' => $h2['date'],
                            'separation' => $sep,
                            'bars_ago' => $barsAgo,
                            'is_fresh' => ($barsAgo <= ($tf === self::TIMEFRAME_2H ? 5 : 4)),
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Cari RSI Double Bottom yang relevan/terbaru dengan evaluasi breakout freshness.
     */
    private function findRecentDoubleBottom(
        array $candles,
        array $rsi,
        float $tol,
        float $maxRsi,
        int $minSeparation,
        int $maxSeparation,
        float $necklineMin,
        int $tf = 24
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

                // Cek kesegaran breakout (breakout freshness)
                $breakoutBar = null;
                for ($k = $i2 + 1; $k < $n; $k++) {
                    if ($rsi[$k] !== null && $rsi[$k] > $neck) {
                        $breakoutBar = $k;
                        break;
                    }
                }
                $breakoutBarsAgo = $breakoutBar !== null ? ($n - 1 - $breakoutBar) : 999;
                $isFreshBreakout = ($breakout && $breakoutBarsAgo <= ($tf === self::TIMEFRAME_2H ? 4 : 5) && $cur <= 60.0);

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

                // Deteksi Shakeout Low: Harga Low 2 menusuk di bawah Low 1 (undercut terukur 0.2% - 5%)
                $isShakeoutLow = false;
                $shakeoutPct = 0.0;
                $isFreshShakeout = false;
                if ($p1 > 0 && $p2 < $p1 * 0.998) {
                    $undercut = ($p1 - $p2) / $p1;
                    if ($undercut <= 0.05) {
                        $isShakeoutLow = true;
                        $shakeoutPct = round($undercut * 100, 1);
                        $isFreshShakeout = (($n - 1 - $i2) <= ($tf === self::TIMEFRAME_2H ? 5 : 6));
                        $conf += 8;
                    }
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
                    'is_shakeout_low' => $isShakeoutLow,
                    'shakeout_pct' => $shakeoutPct,
                    'is_fresh_breakout' => $isFreshBreakout,
                    'is_fresh_shakeout' => $isFreshShakeout,
                    'bars_ago' => ($n - 1 - $i2),
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
