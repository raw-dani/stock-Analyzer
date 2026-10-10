<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use Yii;

/**
 * Service Analisis & Scanner Pola Candlestick Paling Potensial dengan Konfirmasi Volume.
 *
 * Menganalisa pola-pola paling potensial untuk menentukan waktu beli dan jual yang tepat:
 * - Hammer & Inverted Hammer (Bullish Reversal pada area dip / support)
 * - Shooting Star (Bearish Reversal pada area resistance / top)
 * - Engulfing (Bullish Engulfing untuk akumulasi kuat & Bearish Engulfing untuk exit)
 * - Doji (Dragonfly, Gravestone, Long-Legged, Neutral untuk identifikasi titik balik tren)
 * - Marubozu (Bullish Marubozu momentum breakout & Bearish Marubozu distribusi)
 * - Morning Star & Evening Star (Pola 3-candle pembalikan arah klasik)
 *
 * Serta memperhitungkan secara komprehensif apakah volume mendukung kenaikan harga:
 * - Kalkulasi Relative Volume (RVOL) terhadap SMA20 Volume
 * - Deteksi Volume Surge (Big Player Accumulation) vs Fakeout (Volume Kering)
 * - Kalkulasi titik Entry, Stop Loss, Multi-Target TP (TP1 & TP2), serta Risk/Reward Ratio.
 * - Mendukung multi-timeframe: 1D (Daily), 4H, 2H, 1H.
 */
final class CandlestickService
{
    public const TIMEFRAME_1D = 24;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_1H = 1;

    public const PATTERN_HAMMER            = 'HAMMER';
    public const PATTERN_INVERTED_HAMMER   = 'INVERTED_HAMMER';
    public const PATTERN_SHOOTING_STAR     = 'SHOOTING_STAR';
    public const PATTERN_BULLISH_ENGULFING = 'BULLISH_ENGULFING';
    public const PATTERN_BEARISH_ENGULFING = 'BEARISH_ENGULFING';
    public const PATTERN_DOJI              = 'DOJI';
    public const PATTERN_BULLISH_MARUBOZU  = 'BULLISH_MARUBOZU';
    public const PATTERN_BEARISH_MARUBOZU  = 'BEARISH_MARUBOZU';
    public const PATTERN_MORNING_STAR      = 'MORNING_STAR';
    public const PATTERN_EVENING_STAR      = 'EVENING_STAR';

    public const ACTION_STRONG_BUY  = 'STRONG_BUY';
    public const ACTION_BUY         = 'BUY';
    public const ACTION_WATCH       = 'WATCH';
    public const ACTION_SELL        = 'SELL';
    public const ACTION_STRONG_SELL = 'STRONG_SELL';

    public const VOL_SURGE        = 'SURGE';
    public const VOL_SUPPORTIVE   = 'SUPPORTIVE';
    public const VOL_MODERATE     = 'MODERATE';
    public const VOL_WEAK         = 'WEAK';
    public const VOL_DISTRIBUTION = 'DISTRIBUTION';

    public const DEFAULT_LOOKBACK = 60;
    public const MIN_INTRADAY_BARS = 20;

    /**
     * Timeframes yang didukung.
     */
    public static function getTimeframes(): array
    {
        return [
            self::TIMEFRAME_1D => '1D (Harian)',
            self::TIMEFRAME_4H => '4H (4 Jam)',
            self::TIMEFRAME_2H => '2H (2 Jam)',
            self::TIMEFRAME_1H => '1H (1 Jam)',
        ];
    }

    /**
     * Daftar pola candlestick yang didukung.
     */
    public static function getPatterns(): array
    {
        return [
            self::PATTERN_HAMMER            => '🔨 Hammer (Bullish Reversal)',
            self::PATTERN_INVERTED_HAMMER   => '🔨 Inverted Hammer',
            self::PATTERN_SHOOTING_STAR     => '⭐ Shooting Star (Bearish Top)',
            self::PATTERN_BULLISH_ENGULFING => '🟢 Bullish Engulfing (Kuat Beli)',
            self::PATTERN_BEARISH_ENGULFING => '🔴 Bearish Engulfing (Kuat Jual)',
            self::PATTERN_DOJI              => '✝️ Doji (Indecision / Reversal)',
            self::PATTERN_BULLISH_MARUBOZU  => '⚡ Bullish Marubozu (Breakout)',
            self::PATTERN_BEARISH_MARUBOZU  => '🔻 Bearish Marubozu (Dump)',
            self::PATTERN_MORNING_STAR      => '🌅 Morning Star (3-Bar Reversal)',
            self::PATTERN_EVENING_STAR      => '🌆 Evening Star (3-Bar Reversal)',
        ];
    }

    /**
     * Scan seluruh saham aktif berdasarkan opsi filter.
     *
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function scanAll(array $options = []): array
    {
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $patternFilter = (string) ($options['pattern'] ?? 'all');
        $volumeFilter = (string) ($options['volume_filter'] ?? 'all');
        $actionFilter = (string) ($options['action'] ?? 'all');
        $exchange = (string) ($options['exchange'] ?? '');
        $sector = (string) ($options['sector'] ?? '');
        $symbolSearch = strtoupper(trim((string) ($options['symbol'] ?? '')));
        $lookback = (int) ($options['lookback'] ?? self::DEFAULT_LOOKBACK);

        $stockQuery = Stock::find()->where(['active' => true]);
        if ($exchange !== '') {
            $stockQuery->andWhere(['exchange' => $exchange]);
        }
        if ($sector !== '') {
            $stockQuery->andWhere(['sector' => $sector]);
        }
        if ($symbolSearch !== '') {
            $stockQuery->andWhere(['like', 'symbol', $symbolSearch]);
        }

        $stocks = $stockQuery->all();
        $results = [];

        foreach ($stocks as $stock) {
            $analysis = $this->analyzeStock((int) $stock->id, [
                'timeframe' => $timeframe,
                'lookback' => $lookback,
            ]);

            if ($analysis === null) {
                continue;
            }

            // Filter Pola
            if ($patternFilter !== 'all' && $analysis['pattern_code'] !== $patternFilter) {
                continue;
            }

            // Filter Volume
            if ($volumeFilter === 'supportive_only' && !$analysis['volume_supports_bullish']) {
                continue;
            }
            if ($volumeFilter === 'surge_only' && $analysis['volume_status'] !== self::VOL_SURGE) {
                continue;
            }

            // Filter Aksi
            if ($actionFilter === 'buy_only' && !in_array($analysis['action'], [self::ACTION_BUY, self::ACTION_STRONG_BUY], true)) {
                continue;
            }
            if ($actionFilter === 'sell_only' && !in_array($analysis['action'], [self::ACTION_SELL, self::ACTION_STRONG_SELL], true)) {
                continue;
            }
            if ($actionFilter === 'watch_only' && $analysis['action'] !== self::ACTION_WATCH) {
                continue;
            }

            $results[] = $analysis;
        }

        return $results;
    }

    /**
     * Analisa mendalam candlestick untuk satu saham tertentu.
     *
     * @param int $stockId
     * @param array<string, mixed> $options
     * @return array<string, mixed>|null
     */
    public function analyzeStock(int $stockId, array $options = []): ?array
    {
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $lookback = (int) ($options['lookback'] ?? self::DEFAULT_LOOKBACK);

        $stock = Stock::findOne($stockId);
        if ($stock === null) {
            return null;
        }

        [$candles, $fallbackDaily] = $this->loadCandles($stockId, $timeframe, $lookback);
        if (count($candles) < 20) {
            return null;
        }

        $patternData = $this->detectBestPattern($candles);
        if ($patternData === null) {
            if (!empty($options['allow_neutral'])) {
                $patternData = $this->createNeutralPattern($candles);
            } else {
                return null;
            }
        }

        $latest = end($candles);
        $prev = $candles[count($candles) - 2];
        $priceChangePct = $prev['close'] > 0
            ? round((($latest['close'] - $prev['close']) / $prev['close']) * 100, 2)
            : 0.0;

        return array_merge([
            'stock_id' => $stockId,
            'symbol' => $stock->symbol,
            'name' => $stock->name,
            'exchange' => $stock->exchange,
            'sector' => $stock->sector ?? 'Umum',
            'timeframe' => $timeframe,
            'timeframe_label' => self::getTimeframes()[$timeframe] ?? '1D',
            'is_fallback_daily' => $fallbackDaily,
            'last_price' => (float) $latest['close'],
            'price_change_pct' => $priceChangePct,
            'last_date' => $latest['date'],
            'candles' => $candles,
            'dates' => array_column($candles, 'date'),
        ], $patternData);
    }

    /**
     * Pola konsolidasi/netral default saat tidak ada formasi candlestick ekstrem pada 3 bar terakhir.
     * Memastikan halaman detail tetap menampilkan chart ECharts, level trading plan, dan analisa volume.
     *
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $candles
     * @return array<string, mixed>
     */
    private function createNeutralPattern(array $candles): array
    {
        $curr = end($candles);
        $prev = $candles[count($candles) - 2];

        $cOpen = (float) $curr['open'];
        $cHigh = (float) $curr['high'];
        $cLow = (float) $curr['low'];
        $cClose = (float) $curr['close'];
        $cVol = (int) $curr['volume'];

        $cRange = max(0.0001, $cHigh - $cLow);
        $cBody = abs($cClose - $cOpen);
        $cUpperWick = $cHigh - max($cOpen, $cClose);
        $cLowerWick = min($cOpen, $cClose) - $cLow;

        $bodyRatio = $cBody / $cRange;
        $upperRatio = $cUpperWick / $cRange;
        $lowerRatio = $cLowerWick / $cRange;
        $isBullish = $cClose >= $cOpen;

        $volSMA20 = $this->calculateVolumeSMA($candles, 20);
        $rvol = round($cVol / max(1.0, $volSMA20), 2);
        $volAnalysis = $this->analyzeVolume($cVol, $volSMA20, $rvol, $isBullish);

        $recentHigh = max(array_column(array_slice($candles, -20), 'high'));
        $recentLow = min(array_column(array_slice($candles, -20), 'low'));

        $entry = round($cClose, 2);
        $stopLoss = round($recentLow * 0.99, 2);
        if (($entry - $stopLoss) < ($entry * 0.015)) {
            $stopLoss = round($entry * 0.98, 2);
        }
        $risk = max(0.01, $entry - $stopLoss);
        $stopLossPct = round((($entry - $stopLoss) / $entry) * 100, 2);

        $target1 = round(max($entry * 1.03, (float) $recentHigh), 2);
        $target1Pct = round((($target1 - $entry) / $entry) * 100, 2);
        $target2 = round($target1 * 1.05, 2);
        $target2Pct = round((($target2 - $entry) / $entry) * 100, 2);
        $rrRatio = round(($target1 - $entry) / $risk, 1);

        $timingAdvice = "WAKTU PANTAU (WAIT & SEE): Saham sedang bergerak dalam rentang konsolidasi $" . number_format((float) $recentLow, 2) . " - $" . number_format((float) $recentHigh, 2) . ". Belum ada sinyal beli/jual agresif dari formasi candlestick. Tunggu terbentuknya pola pembalikan arah atau konfirmasi breakout resisten.";

        $checklist = [
            [
                'title' => 'Formasi Pola Candlestick',
                'pass' => false,
                'desc' => 'Sedang dalam rentang normal (Trading Range / Konsolidasi).',
            ],
            [
                'title' => 'Konfirmasi Volume Transaksi',
                'pass' => $volAnalysis['supports_bullish'],
                'desc' => "RVOL {$rvol}x vs SMA20. {$volAnalysis['strength_label']}.",
            ],
            [
                'title' => 'Kondisi Rentang Harga',
                'pass' => true,
                'desc' => 'Harga berada di antara support $' . number_format((float) $recentLow, 2) . ' dan resisten $' . number_format((float) $recentHigh, 2) . '.',
            ],
            [
                'title' => 'Risk / Reward Rasio',
                'pass' => ($rrRatio >= 1.5),
                'desc' => "Rasio potensi gain terhadap risiko adalah {$rrRatio}x.",
            ],
            [
                'title' => 'Ruang Gerak Menuju Resisten',
                'pass' => ($target1Pct >= 3.0),
                'desc' => "Jarak ke resisten terdekat adalah +{$target1Pct}%.",
            ],
        ];

        return [
            'pattern_code' => 'NORMAL_RANGE',
            'pattern_name' => 'Trading Range (Konsolidasi)',
            'pattern_subtype' => 'Rentang Normal (Belum Ada Pola Ekstrem)',
            'pattern_badge' => 'secondary',
            'pattern_icon' => '🕯️',
            'pattern_description' => 'Harga bergerak dalam rentang konsolidasi wajar tanpa pola candlestick ekstrem (seperti Hammer, Shooting Star, Engulfing, Marubozu, atau Doji) pada bar-bar terkini.',
            'action' => self::ACTION_WATCH,
            'action_badge' => 'warning text-dark',
            'bar_offset' => 0,
            'bar_date' => $curr['date'],
            'is_latest_bar' => true,
            'confidence_score' => 50,

            // Volume Analysis
            'volume' => $cVol,
            'volume_sma20' => (int) round($volSMA20),
            'rvol' => $rvol,
            'volume_status' => $volAnalysis['status'],
            'volume_badge' => $volAnalysis['badge'],
            'volume_supports_bullish' => $volAnalysis['supports_bullish'],
            'volume_strength_label' => $volAnalysis['strength_label'],
            'volume_verdict' => $volAnalysis['verdict'],

            // Trading Plan
            'entry_price' => $entry,
            'entry_range' => '$' . number_format((float) $recentLow, 2) . ' - $' . number_format($entry, 2),
            'stop_loss' => $stopLoss,
            'stop_loss_pct' => $stopLossPct,
            'target_1' => $target1,
            'target_1_pct' => $target1Pct,
            'target_2' => $target2,
            'target_2_pct' => $target2Pct,
            'rr_ratio' => $rrRatio,
            'timing_advice' => $timingAdvice,
            'entry_advice' => 'Tunggu terbentuknya pola candlestick pembalikan arah (Hammer / Bullish Engulfing) di area support sebelum membuka posisi beli.',
            'exit_advice' => 'Pasang trailing stop di bawah level support $' . number_format((float) $stopLoss, 2) . ' untuk melindungi modal.',

            // Candle Anatomy
            'candle_anatomy' => [
                'open' => $cOpen,
                'high' => $cHigh,
                'low' => $cLow,
                'close' => $cClose,
                'body_pct' => round($bodyRatio * 100, 1),
                'upper_wick_pct' => round($upperRatio * 100, 1),
                'lower_wick_pct' => round($lowerRatio * 100, 1),
                'is_bullish' => $isBullish,
            ],

            'checklist' => $checklist,
        ];
    }

    /**
     * Deteksi pola candlestick paling potensial pada bar-bar terkini.
     * Menguji bar mutakhir (offset 0), lalu bar kemarin (offset 1), lalu offset 2 jika belum expired.
     *
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $candles
     * @return array<string, mixed>|null
     */
    private function detectBestPattern(array $candles): ?array
    {
        $n = count($candles);
        if ($n < 20) {
            return null;
        }

        // Cek kandidat pola dari 3 candle terakhir (prioritas offset 0 -> 1 -> 2)
        for ($offset = 0; $offset <= 2; $offset++) {
            $idx = $n - 1 - $offset;
            if ($idx < 2) {
                break;
            }

            $curr = $candles[$idx];
            $prev = $candles[$idx - 1];
            $prev2 = $candles[$idx - 2];

            // Sub-array sampai index untuk SMA Volume 20
            $historyCandles = array_slice($candles, 0, $idx + 1);
            $volSMA20 = $this->calculateVolumeSMA($historyCandles, 20);

            // Deteksi pola pada bar ini
            $detected = $this->evaluateCandlestick($curr, $prev, $prev2, $historyCandles, $volSMA20, $offset);
            if ($detected !== null) {
                return $detected;
            }
        }

        return null;
    }

    /**
     * Evaluasi matematis mendalam untuk setiap jenis pola candlestick.
     *
     * @param array{open:float,high:float,low:float,close:float,volume:int,date:string} $curr
     * @param array{open:float,high:float,low:float,close:float,volume:int,date:string} $prev
     * @param array{open:float,high:float,low:float,close:float,volume:int,date:string} $prev2
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $history
     * @param float $volSMA20
     * @param int $barOffset
     * @return array<string, mixed>|null
     */
    private function evaluateCandlestick(
        array $curr,
        array $prev,
        array $prev2,
        array $history,
        float $volSMA20,
        int $barOffset
    ): ?array {
        $cOpen = (float) $curr['open'];
        $cHigh = (float) $curr['high'];
        $cLow = (float) $curr['low'];
        $cClose = (float) $curr['close'];
        $cVol = (int) $curr['volume'];

        $pOpen = (float) $prev['open'];
        $pHigh = (float) $prev['high'];
        $pLow = (float) $prev['low'];
        $pClose = (float) $prev['close'];

        $cRange = max(0.0001, $cHigh - $cLow);
        $cBody = abs($cClose - $cOpen);
        $cUpperWick = $cHigh - max($cOpen, $cClose);
        $cLowerWick = min($cOpen, $cClose) - $cLow;

        $pRange = max(0.0001, $pHigh - $pLow);
        $pBody = abs($pClose - $pOpen);

        $bodyRatio = $cBody / $cRange;
        $upperRatio = $cUpperWick / $cRange;
        $lowerRatio = $cLowerWick / $cRange;

        $isBullish = $cClose >= $cOpen;
        $pIsBullish = $pClose >= $pOpen;

        // Analisis tren jangka pendek (5-10 bar sebelumnya)
        $shortHistory = array_slice($history, -8, 6);
        $firstClose = (float) ($shortHistory[0]['close'] ?? $cClose);
        $lastShortClose = (float) ($prev['close'] ?? $cClose);
        $trendSlope = ($lastShortClose - $firstClose) / max(0.001, $firstClose);
        $isShortDowntrend = $trendSlope <= 0.005; // Sedang koreksi/turun
        $isShortUptrend = $trendSlope >= -0.005;  // Sedang naik/uptrend

        // Analisis Volume
        $rvol = round($cVol / max(1.0, $volSMA20), 2);
        $volAnalysis = $this->analyzeVolume($cVol, $volSMA20, $rvol, $isBullish);

        $patternCode = null;
        $patternName = null;
        $patternSubtype = null;
        $action = null;
        $patternDescription = null;
        $entryAdvice = '';
        $exitAdvice = '';
        $baseConfidence = 50;

        // ---------------------------------------------------------
        // 1. POLA MARUBOZU (SOLID BODY >= 85%, WICK <= 6%)
        // ---------------------------------------------------------
        if ($bodyRatio >= 0.85 && $upperRatio <= 0.06 && $lowerRatio <= 0.06 && $cBody >= ($pBody * 0.8)) {
            if ($isBullish) {
                $patternCode = self::PATTERN_BULLISH_MARUBOZU;
                $patternName = 'Bullish Marubozu';
                $patternSubtype = 'Momentum Breakout Solid';
                $action = self::ACTION_STRONG_BUY;
                $patternDescription = 'Candle hijau pejal tanpa bayangan atas/bawah mengindikasikan dominasi mutlak pembeli dari pembukaan hingga penutupan. Momentum beli sangat masif.';
                $entryAdvice = 'Entry agresif pada level close atau antri di batas 50% candle Marubozu.';
                $exitAdvice = 'Pasang trailing stop di bawah Low candle Marubozu.';
                $baseConfidence = 85;
            } else {
                $patternCode = self::PATTERN_BEARISH_MARUBOZU;
                $patternName = 'Bearish Marubozu';
                $patternSubtype = 'Tekanan Jual Masif (Dump)';
                $action = self::ACTION_STRONG_SELL;
                $patternDescription = 'Candle merah penuh tanpa perlawanan dari pembeli. Menandakan kepanikan atau aksi jual institusional yang agresif.';
                $entryAdvice = 'HINDARI ENTRY BELI.';
                $exitAdvice = 'Segera lakukan penjualan (Exit/Cut Loss) untuk menghindari penurunan lebih dalam.';
                $baseConfidence = 85;
            }
        }

        // ---------------------------------------------------------
        // 2. POLA ENGULFING (2-BAR FORMATION)
        // ---------------------------------------------------------
        if ($patternCode === null) {
            // Bullish Engulfing: Candle sebelumnya merah, candle ini hijau menelan candle merah
            if (!$pIsBullish && $isBullish
                && $cOpen <= ($pClose * 1.003)
                && $cClose >= ($pOpen * 0.997)
                && $cBody > ($pBody * 1.05)
                && $isShortDowntrend
            ) {
                $patternCode = self::PATTERN_BULLISH_ENGULFING;
                $patternName = 'Bullish Engulfing';
                $patternSubtype = 'Kekuatan Pembeli Menelan Tekanan Jual';
                $action = self::ACTION_STRONG_BUY;
                $patternDescription = 'Candle hijau besar membungkus penuh badan candle merah sebelumnya di area support/koreksi. Sinyal kuat pembalikan arah naik.';
                $entryAdvice = 'Waktu Beli Tepat: Masuk pada penutupan candle engulfing atau antri beli saat retest separuh body candle.';
                $exitAdvice = 'Target profit di resistance terdekat (TP1) dan resisten utama (TP2). Stop loss di bawah low formasi.';
                $baseConfidence = 80;
            }
            // Bearish Engulfing: Candle sebelumnya hijau, candle ini merah menelan candle hijau
            elseif ($pIsBullish && !$isBullish
                && $cOpen >= ($pClose * 0.997)
                && $cClose <= ($pOpen * 1.003)
                && $cBody > ($pBody * 1.05)
                && $isShortUptrend
            ) {
                $patternCode = self::PATTERN_BEARISH_ENGULFING;
                $patternName = 'Bearish Engulfing';
                $patternSubtype = 'Tekanan Jual Menelan Pembeli';
                $action = self::ACTION_STRONG_SELL;
                $patternDescription = 'Candle merah besar menelan candle hijau di area puncak (resistance). Sinyal kuat berakhirnya tren naik.';
                $entryAdvice = 'HINDARI PEMBELIAN.';
                $exitAdvice = 'Waktu Jual Tepat: Amankan keuntungan segera (Take Profit) sebelum koreksi tajam berlanjut.';
                $baseConfidence = 80;
            }
        }

        // ---------------------------------------------------------
        // 3. POLA HAMMER & INVERTED HAMMER (BULLISH REVERSAL)
        // ---------------------------------------------------------
        if ($patternCode === null) {
            // Hammer klasik: Lower shadow panjang >= 2x body, upper wick sangat kecil, di area koreksi/downtrend
            if ($cLowerWick >= (1.9 * $cBody)
                && $upperRatio <= 0.16
                && $bodyRatio >= 0.06
                && $bodyRatio <= 0.38
                && $isShortDowntrend
            ) {
                $patternCode = self::PATTERN_HAMMER;
                $patternName = 'Hammer';
                $patternSubtype = 'Penolakan Harga Bawah (Bullish Reversal)';
                $action = self::ACTION_BUY;
                $patternDescription = 'Ekor bawah panjang menunjukkan seller sempat menekan harga turun tajam, namun pembeli melakukan perlawanan kuat hingga harga kembali ke atas.';
                $entryAdvice = 'Waktu Beli Tepat: Entry saat harga menembus level High Hammer atau beli di dekat batas penutupan.';
                $exitAdvice = 'Pasang Stop Loss ketat tepat di bawah jarum terendah (Low Hammer).';
                $baseConfidence = 78;
            }
            // Inverted Hammer: Upper shadow panjang >= 2x body, lower wick kecil, di area koreksi
            elseif ($cUpperWick >= (1.9 * $cBody)
                && $lowerRatio <= 0.12
                && $bodyRatio >= 0.06
                && $bodyRatio <= 0.38
                && $isShortDowntrend
            ) {
                $patternCode = self::PATTERN_INVERTED_HAMMER;
                $patternName = 'Inverted Hammer';
                $patternSubtype = 'Percobaan Reversal Pembeli';
                $action = self::ACTION_BUY;
                $patternDescription = 'Pembeli mulai menguji harga atas di area dasar. Mengindikasikan potensi akumulasi awal untuk pembalikan arah.';
                $entryAdvice = 'Waktu Beli: Masuk bertahap saat candle berikutnya mengonfirmasi penembusan high.';
                $exitAdvice = 'Stop Loss di bawah Low Inverted Hammer.';
                $baseConfidence = 72;
            }
        }

        // ---------------------------------------------------------
        // 4. POLA SHOOTING STAR (BEARISH REVERSAL)
        // ---------------------------------------------------------
        if ($patternCode === null) {
            if ($cUpperWick >= (1.9 * $cBody)
                && $lowerRatio <= 0.14
                && $bodyRatio >= 0.06
                && $bodyRatio <= 0.38
                && $isShortUptrend
            ) {
                $patternCode = self::PATTERN_SHOOTING_STAR;
                $patternName = 'Shooting Star';
                $patternSubtype = 'Penolakan Harga Atas (Bearish Rejection)';
                $action = self::ACTION_SELL;
                $patternDescription = 'Ekor atas panjang di puncak rally menunjukkan pembeli gagal mempertahankan level tinggi dan seller menolak kenaikan harga.';
                $entryAdvice = 'JANGAN BELI (Potensi Bull Trap).';
                $exitAdvice = 'Waktu Jual Tepat: Ambil keuntungan (Take Profit) segera atau pasang stop order di bawah Low Shooting Star.';
                $baseConfidence = 78;
            }
        }

        // ---------------------------------------------------------
        // 5. POLA DOJI (BODY <= 10% DARI RANGE, VOLUME > 0)
        // ---------------------------------------------------------
        if ($patternCode === null && $bodyRatio <= 0.10 && $cRange > 0.01 && $cVol > 0) {
            $patternCode = self::PATTERN_DOJI;

            if ($lowerRatio >= 0.60 && $upperRatio <= 0.12) {
                $patternName = 'Dragonfly Doji';
                $patternSubtype = 'Bullish Pin Reversal';
                $action = self::ACTION_BUY;
                $patternDescription = 'Open, High, dan Close hampir sama di puncak range, dengan ekor bawah sangat panjang. Penolakan harga murah yang sangat kuat.';
                $entryAdvice = 'Waktu Beli: Masuk saat breakout High Dragonfly Doji.';
                $exitAdvice = 'Stop loss di bawah Low ekor panjang.';
                $baseConfidence = 75;
            } elseif ($upperRatio >= 0.60 && $lowerRatio <= 0.12) {
                $patternName = 'Gravestone Doji';
                $patternSubtype = 'Bearish Reversal Rejection';
                $action = self::ACTION_SELL;
                $patternDescription = 'Open, Low, dan Close berada di dasar range dengan jarum atas sangat panjang. Upaya buyer terbantai oleh supply seller.';
                $entryAdvice = 'HINDARI PEMBELIAN.';
                $exitAdvice = 'Waktu Jual: Exit posisi long segera.';
                $baseConfidence = 75;
            } elseif ($upperRatio >= 0.30 && $lowerRatio >= 0.30) {
                $patternName = 'Long-Legged Doji';
                $patternSubtype = 'Volatilitas Tinggi & Keraguan Pasar';
                $action = self::ACTION_WATCH;
                $patternDescription = 'Bayangan atas dan bawah sama-sama panjang merefleksikan pertarungan sengit antara buyer dan seller tanpa pemenang jelas.';
                $entryAdvice = 'WAKTU PANTAU: Tunggu arah breakout High atau Low Doji.';
                $exitAdvice = 'Amankan profit parsial jika sudah memegang barang.';
                $baseConfidence = 60;
            } else {
                $patternName = 'Neutral Doji';
                $patternSubtype = 'Indecision Keseimbangan Pasar';
                $action = self::ACTION_WATCH;
                $patternDescription = 'Titik impas antara penawaran dan permintaan. Sering menjadi penanda akhir dari sebuah tren (inflection point).';
                $entryAdvice = 'WAKTU WAIT & SEE: Konfirmasi candle berikutnya menentukan arah.';
                $exitAdvice = 'Pasang proteksi trailing stop.';
                $baseConfidence = 55;
            }
        }

        // ---------------------------------------------------------
        // 6. POLA 3-BAR: MORNING STAR & EVENING STAR (BONUS PRECISI)
        // ---------------------------------------------------------
        if ($patternCode === null) {
            $p2Open = (float) $prev2['open'];
            $p2Close = (float) $prev2['close'];
            $p2IsBullish = $p2Close >= $p2Open;
            $p2Range = max(0.0001, (float) $prev2['high'] - (float) $prev2['low']);
            $p2Body = abs($p2Close - $p2Open);

            // Morning Star: 1. Merah besar, 2. Candle kecil di bawah, 3. Hijau besar menutup > 50% candle 1
            if (!$p2IsBullish && ($p2Body / $p2Range >= 0.5)
                && ($pBody / $pRange <= 0.35)
                && $isBullish
                && $cClose >= (($p2Open + $p2Close) / 2)
                && $isShortDowntrend
            ) {
                $patternCode = self::PATTERN_MORNING_STAR;
                $patternName = 'Morning Star';
                $patternSubtype = 'Formasi 3-Candle Reversal Kuat';
                $action = self::ACTION_STRONG_BUY;
                $patternDescription = 'Bintang pagi terbentuk sempurna dari transisi bearish -> konsolidasi -> kebangkitan bullish masif.';
                $entryAdvice = 'Waktu Beli: Entry ideal pada penutupan candle ketiga atau pullback ringan.';
                $exitAdvice = 'Stop Loss di bawah Low candle bintang tengah.';
                $baseConfidence = 82;
            }
            // Evening Star: 1. Hijau besar, 2. Candle kecil di atas, 3. Merah besar menutup < 50% candle 1
            elseif ($p2IsBullish && ($p2Body / $p2Range >= 0.5)
                && ($pBody / $pRange <= 0.35)
                && !$isBullish
                && $cClose <= (($p2Open + $p2Close) / 2)
                && $isShortUptrend
            ) {
                $patternCode = self::PATTERN_EVENING_STAR;
                $patternName = 'Evening Star';
                $patternSubtype = 'Formasi 3-Candle Reversal Puncak';
                $action = self::ACTION_STRONG_SELL;
                $patternDescription = 'Bintang senja menandakan kelelahan tren naik dan dimulainya gelombang penurunan tajam.';
                $entryAdvice = 'HINDARI PEMBELIAN.';
                $exitAdvice = 'Waktu Jual: Take profit segera sebelum koreksi berlanjut.';
                $baseConfidence = 82;
            }
        }

        // Jika tidak ada pola yang signifikan terdeteksi
        if ($patternCode === null) {
            return null;
        }

        // Kalkulasi Trading Plan (Entry, SL, TP1, TP2, R:R)
        $tradingPlan = $this->calculateTradingPlan($curr, $prev, $action, $patternCode);

        // Kalkulasi Skor Keyakinan Total (0 - 100%)
        // Dipengaruhi oleh: Bobot Pola + Bobot Volume + Bobot R:R
        $volBonus = match ($volAnalysis['status']) {
            self::VOL_SURGE => 18,
            self::VOL_SUPPORTIVE => 10,
            self::VOL_MODERATE => 2,
            self::VOL_WEAK => -15, // Pinalti jika volume kering
            self::VOL_DISTRIBUTION => ($action === self::ACTION_SELL || $action === self::ACTION_STRONG_SELL) ? 15 : -20,
            default => 0,
        };

        $offsetPenalty = $barOffset * 4; // Candle terbaru lebih bernilai
        $confidenceScore = (int) max(10, min(99, $baseConfidence + $volBonus - $offsetPenalty));

        // 5-Point Checklist Konfirmasi
        $checklist = [
            [
                'title' => 'Formasi Pola Candlestick Valid',
                'pass' => true,
                'desc' => "Pola {$patternName} terbentuk sesuai kaidah rasio matematis.",
            ],
            [
                'title' => 'Konfirmasi Volume Transaksi',
                'pass' => $volAnalysis['supports_bullish'] || ($action === self::ACTION_SELL && $rvol >= 1.2),
                'desc' => "RVOL {$rvol}x vs SMA20. {$volAnalysis['strength_label']}.",
            ],
            [
                'title' => 'Kesesuaian Tren / Lokasi Level',
                'pass' => in_array($action, [self::ACTION_BUY, self::ACTION_STRONG_BUY], true) ? $isShortDowntrend : $isShortUptrend,
                'desc' => in_array($action, [self::ACTION_BUY, self::ACTION_STRONG_BUY], true) ? 'Pola terbentuk di area koreksi/support.' : 'Pola terbentuk di area puncak/resistance.',
            ],
            [
                'title' => 'Risk / Reward Rasio Sehat (>= 1.5x)',
                'pass' => ($tradingPlan['rr_ratio'] >= 1.5),
                'desc' => "Rasio potensi gain terhadap risiko adalah {$tradingPlan['rr_ratio']}x.",
            ],
            [
                'title' => 'Ruang Gerak Target Profit (> 3%)',
                'pass' => ($tradingPlan['target_1_pct'] >= 3.0),
                'desc' => "Potensi profit TP1 adalah +{$tradingPlan['target_1_pct']}%.",
            ],
        ];

        return [
            'pattern_code' => $patternCode,
            'pattern_name' => $patternName,
            'pattern_subtype' => $patternSubtype,
            'pattern_badge' => $this->getPatternBadgeClass($patternCode),
            'pattern_icon' => $this->getPatternIcon($patternCode),
            'pattern_description' => $patternDescription,
            'action' => $action,
            'action_badge' => $this->getActionBadgeClass($action),
            'bar_offset' => $barOffset,
            'bar_date' => $curr['date'],
            'is_latest_bar' => ($barOffset === 0),
            'confidence_score' => $confidenceScore,

            // Volume Analysis Data
            'volume' => $cVol,
            'volume_sma20' => (int) round($volSMA20),
            'rvol' => $rvol,
            'volume_status' => $volAnalysis['status'],
            'volume_badge' => $volAnalysis['badge'],
            'volume_supports_bullish' => $volAnalysis['supports_bullish'],
            'volume_strength_label' => $volAnalysis['strength_label'],
            'volume_verdict' => $volAnalysis['verdict'],

            // Trading Plan Levels
            'entry_price' => $tradingPlan['entry_price'],
            'entry_range' => $tradingPlan['entry_range'],
            'stop_loss' => $tradingPlan['stop_loss'],
            'stop_loss_pct' => $tradingPlan['stop_loss_pct'],
            'target_1' => $tradingPlan['target_1'],
            'target_1_pct' => $tradingPlan['target_1_pct'],
            'target_2' => $tradingPlan['target_2'],
            'target_2_pct' => $tradingPlan['target_2_pct'],
            'rr_ratio' => $tradingPlan['rr_ratio'],
            'timing_advice' => $tradingPlan['timing_advice'],
            'entry_advice' => $entryAdvice,
            'exit_advice' => $exitAdvice,

            // Candle Anatomy
            'candle_anatomy' => [
                'open' => $cOpen,
                'high' => $cHigh,
                'low' => $cLow,
                'close' => $cClose,
                'body_pct' => round($bodyRatio * 100, 1),
                'upper_wick_pct' => round($upperRatio * 100, 1),
                'lower_wick_pct' => round($lowerRatio * 100, 1),
                'is_bullish' => $isBullish,
            ],

            'checklist' => $checklist,
        ];
    }

    /**
     * Analisa Volume: Memperhitungkan secara eksplisit apakah volume mendukung kenaikan saham.
     *
     * @return array{status:string,badge:string,supports_bullish:bool,strength_label:string,verdict:string}
     */
    private function analyzeVolume(int $volume, float $volSMA20, float $rvol, bool $isBullish): array
    {
        if ($isBullish) {
            if ($rvol >= 2.0) {
                return [
                    'status' => self::VOL_SURGE,
                    'badge' => 'bg-success text-white',
                    'supports_bullish' => true,
                    'strength_label' => 'SANGAT MENDUKUNG (SURGE 🚀)',
                    'verdict' => "Kenaikan saham SANGAT DIDUKUNG oleh lonjakan volume masif ({$rvol}x di atas rata-rata 20 periode). Menunjukkan akumulasi institusional / Big Player agresif. Validitas kenaikan sangat kuat!",
                ];
            }
            if ($rvol >= 1.25) {
                return [
                    'status' => self::VOL_SUPPORTIVE,
                    'badge' => 'bg-primary text-white',
                    'supports_bullish' => true,
                    'strength_label' => 'MENDUKUNG (SOLID ✅)',
                    'verdict' => "Kenaikan saham DIDUKUNG oleh volume transaksi sehat ({$rvol}x SMA20). Partisipasi pembeli solid mengonfirmasi momentum penguatan harga.",
                ];
            }
            if ($rvol >= 0.85) {
                return [
                    'status' => self::VOL_MODERATE,
                    'badge' => 'bg-info text-dark',
                    'supports_bullish' => true,
                    'strength_label' => 'MODERAT (RATA-RATA ⚪)',
                    'verdict' => "Volume transaksi berada pada kisaran wajar ({$rvol}x SMA20). Kenaikan cukup stabil, namun tetap perhatikan level resistance kunci.",
                ];
            }
            return [
                'status' => self::VOL_WEAK,
                'badge' => 'bg-warning text-dark',
                'supports_bullish' => false,
                'strength_label' => 'KURANG MENDUKUNG (LEMAH ⚠️)',
                'verdict' => "PERINGATAN: Pola Bullish terbentuk TANPA dukungan volume ({$rvol}x SMA20 - Volume Kering). Kenaikan harga tanpa volume berisiko tinggi mengalami Fakeout / Bull Trap. Disarankan menunggu konfirmasi volume sebelum entry.",
            ];
        }

        // Bearish Candle / Rejection
        if ($rvol >= 1.5) {
            return [
                'status' => self::VOL_DISTRIBUTION,
                'badge' => 'bg-danger text-white',
                'supports_bullish' => false,
                'strength_label' => 'DISTRIBUSI BESAR (DUMP 🔻)',
                'verdict' => "Penjualan disertai volume transaksi besar ({$rvol}x SMA20). Mengindikasikan aksi distribusi institusi. Waspada penurunan lanjutan, sangat disarankan segera SELL / TAKE PROFIT.",
            ];
        }

        return [
            'status' => self::VOL_MODERATE,
            'badge' => 'bg-secondary text-white',
            'supports_bullish' => false,
            'strength_label' => 'REJEKSI RESISTANCE ⚠️',
            'verdict' => "Terjadi rejeksi harga di area resistance dengan volume ({$rvol}x SMA20). Disarankan mengamankan keuntungan (Take Profit).",
        ];
    }

    /**
     * Kalkulasi Level Trading Plan Presisi (Entry, Stop Loss, TP1, TP2, RR Ratio).
     *
     * @param array{open:float,high:float,low:float,close:float,volume:int,date:string} $curr
     * @param array{open:float,high:float,low:float,close:float,volume:int,date:string} $prev
     * @param string $action
     * @param string $patternCode
     * @return array<string, mixed>
     */
    private function calculateTradingPlan(array $curr, array $prev, string $action, string $patternCode): array
    {
        $cClose = (float) $curr['close'];
        $cHigh = (float) $curr['high'];
        $cLow = (float) $curr['low'];
        $pLow = (float) $prev['low'];

        $isBuySetup = in_array($action, [self::ACTION_BUY, self::ACTION_STRONG_BUY], true);

        if ($isBuySetup) {
            // Entry point
            $entry = match ($patternCode) {
                self::PATTERN_HAMMER, self::PATTERN_INVERTED_HAMMER => round(max($cClose, $cHigh * 1.001), 2),
                self::PATTERN_BULLISH_ENGULFING => round($cClose, 2),
                self::PATTERN_BULLISH_MARUBOZU => round($cClose, 2),
                default => round($cClose, 2),
            };

            // Stop loss tepat di bawah swing low pola dengan buffer 1%
            $patternLow = min($cLow, $pLow);
            $stopLoss = round($patternLow * 0.99, 2);

            // Buffer minimal 1.5% jika candle sangat kecil agar SL tidak tersentuh noise
            if (($entry - $stopLoss) < ($entry * 0.015)) {
                $stopLoss = round($entry * 0.98, 2);
            }

            $risk = max(0.01, $entry - $stopLoss);
            $stopLossPct = round((($entry - $stopLoss) / $entry) * 100, 2);

            // TP1: 1.5x risk, TP2: 2.5x risk
            $target1 = round($entry + (1.6 * $risk), 2);
            $target1Pct = round((($target1 - $entry) / $entry) * 100, 2);

            $target2 = round($entry + (2.6 * $risk), 2);
            $target2Pct = round((($target2 - $entry) / $entry) * 100, 2);

            $rrRatio = round(($target1 - $entry) / $risk, 1);

            $entryRange = '$' . number_format($stopLoss * 1.01, 2) . ' - $' . number_format($entry * 1.005, 2);
            $timingAdvice = "WAKTU BELI TEPAT: Masuk di kisaran {$entryRange} saat konfirmasi harga bertahan di atas Stop Loss \${$stopLoss} (-{$stopLossPct}%). Target Profit bertahap pada TP1 \${$target1} (+{$target1Pct}%) dan TP2 \${$target2} (+{$target2Pct}%).";
        } else {
            // Sell Setup
            $entry = round($cClose, 2);
            $stopLoss = round(max($cHigh, (float) $prev['high']) * 1.01, 2);
            $stopLossPct = round((($stopLoss - $entry) / $entry) * 100, 2);

            $risk = max(0.01, $stopLoss - $entry);
            $target1 = round(max(0.01, $entry - (1.5 * $risk)), 2);
            $target1Pct = round((($entry - $target1) / $entry) * 100, 2);
            $target2 = round(max(0.01, $entry - (2.5 * $risk)), 2);
            $target2Pct = round((($entry - $target2) / $entry) * 100, 2);

            $rrRatio = round($risk > 0 ? ($entry - $target1) / $risk : 1.0, 1);
            $entryRange = '$' . number_format($entry, 2);

            if ($action === self::ACTION_WATCH) {
                $highFmt = number_format($cHigh, 2);
                $lowFmt = number_format($cLow, 2);
                $timingAdvice = "WAKTU PANTAU (WAIT & SEE): Menunggu konfirmasi breakout candle berikutnya di atas \${$highFmt} untuk beli, atau jika breakdown di bawah \${$lowFmt} hindari transaksi.";
            } else {
                $timingAdvice = "WAKTU JUAL TEPAT: Segera lakukan Realisasi Keuntungan (Take Profit) / Exit di kisaran \${$entry}. Batasi kerugian jika harga berbalik di atas \${$stopLoss}.";
            }
        }

        return [
            'entry_price' => $entry,
            'entry_range' => $entryRange,
            'stop_loss' => $stopLoss,
            'stop_loss_pct' => $stopLossPct,
            'target_1' => $target1,
            'target_1_pct' => $target1Pct,
            'target_2' => $target2,
            'target_2_pct' => $target2Pct,
            'rr_ratio' => $rrRatio,
            'timing_advice' => $timingAdvice,
        ];
    }

    /**
     * Hitung SMA Volume 20 periode.
     *
     * @param array<int, array{volume:int}> $candles
     * @param int $period
     * @return float
     */
    private function calculateVolumeSMA(array $candles, int $period = 20): float
    {
        $slice = array_slice($candles, -$period);
        if (empty($slice)) {
            return 1.0;
        }
        $vols = array_column($slice, 'volume');
        return (float) (array_sum($vols) / count($vols));
    }

    /**
     * Load candle data dari database dengan fallback intraday -> daily.
     *
     * @return array{0: array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}>, 1: bool}
     */
    public function loadCandles(int $stockId, int $timeframe, int $lookback): array
    {
        if ($timeframe === self::TIMEFRAME_1D) {
            return [$this->dailyCandles($stockId, $lookback), false];
        }

        // Intraday logic
        try {
            $rows = IntradayPrice::find()
                ->where(['stock_id' => $stockId, 'timeframe' => IntradayPrice::TF_1H])
                ->orderBy(['datetime' => SORT_DESC])
                ->limit(max($lookback * 4, 80))
                ->all();

            if (count($rows) >= self::MIN_INTRADAY_BARS) {
                $base = [];
                foreach (array_reverse($rows) as $p) {
                    if ((int) $p->volume === 0 && abs((float) $p->high - (float) $p->low) < 0.0001) {
                        continue;
                    }
                    $base[] = [
                        'open' => (float) $p->open,
                        'high' => (float) $p->high,
                        'low' => (float) $p->low,
                        'close' => (float) $p->close,
                        'volume' => (int) $p->volume,
                        'date' => $p->datetime,
                    ];
                }
                $per = match ($timeframe) {
                    self::TIMEFRAME_4H => 4,
                    self::TIMEFRAME_2H => 2,
                    default => 1,
                };
                $aggregated = $this->aggregate($base, $per);
                return [array_slice($aggregated, -$lookback), false];
            }
        } catch (\Throwable $e) {
            Yii::warning("Intraday load error stock {$stockId}: " . $e->getMessage(), __METHOD__);
        }

        // Fallback harian
        return [$this->dailyCandles($stockId, $lookback), true];
    }

    /**
     * Ambil candle harian dari daily_price.
     *
     * @return array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}>
     */
    private function dailyCandles(int $stockId, int $lookback): array
    {
        $prices = DailyPrice::find()
            ->where(['stock_id' => $stockId])
            ->orderBy(['date' => SORT_DESC])
            ->limit($lookback)
            ->all();

        $out = [];
        foreach (array_reverse($prices) as $p) {
            if ((int) $p->volume === 0 && abs((float) $p->high - (float) $p->low) < 0.0001) {
                continue;
            }
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

    /**
     * Agregasi candle 1H -> 2H atau 4H.
     *
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $base
     * @param int $per
     * @return array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}>
     */
    private function aggregate(array $base, int $per): array
    {
        if ($per <= 1) {
            return $base;
        }

        // Kelompokkan per sesi tanggal kalender (Y-m-d) agar batas candle 2H / 4H selalu konsisten,
        // deterministik, dan identik antara halaman list dan halaman detail
        $byDate = [];
        foreach ($base as $c) {
            $day = substr($c['date'], 0, 10);
            $byDate[$day][] = $c;
        }

        $out = [];
        foreach ($byDate as $dayBars) {
            $chunk = [];
            foreach ($dayBars as $c) {
                $chunk[] = $c;
                if (count($chunk) >= $per) {
                    $out[] = $this->makeCandle($chunk);
                    $chunk = [];
                }
            }
            if (!empty($chunk)) {
                $out[] = $this->makeCandle($chunk);
            }
        }

        return $out;
    }

    /**
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $chunk
     * @return array{open:float,high:float,low:float,close:float,volume:int,date:string}
     */
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

    private function getPatternBadgeClass(string $code): string
    {
        return match ($code) {
            self::PATTERN_HAMMER, self::PATTERN_INVERTED_HAMMER => 'success',
            self::PATTERN_BULLISH_ENGULFING, self::PATTERN_MORNING_STAR => 'success',
            self::PATTERN_BULLISH_MARUBOZU => 'primary',
            self::PATTERN_SHOOTING_STAR, self::PATTERN_BEARISH_ENGULFING, self::PATTERN_BEARISH_MARUBOZU, self::PATTERN_EVENING_STAR => 'danger',
            self::PATTERN_DOJI => 'warning text-dark',
            default => 'secondary',
        };
    }

    private function getPatternIcon(string $code): string
    {
        return match ($code) {
            self::PATTERN_HAMMER, self::PATTERN_INVERTED_HAMMER => '🔨',
            self::PATTERN_SHOOTING_STAR => '⭐',
            self::PATTERN_BULLISH_ENGULFING => '🟢',
            self::PATTERN_BEARISH_ENGULFING => '🔴',
            self::PATTERN_BULLISH_MARUBOZU => '⚡',
            self::PATTERN_BEARISH_MARUBOZU => '🔻',
            self::PATTERN_DOJI => '✝️',
            self::PATTERN_MORNING_STAR => '🌅',
            self::PATTERN_EVENING_STAR => '🌆',
            default => '🕯️',
        };
    }

    private function getActionBadgeClass(string $action): string
    {
        return match ($action) {
            self::ACTION_STRONG_BUY => 'success',
            self::ACTION_BUY => 'success',
            self::ACTION_WATCH => 'warning text-dark',
            self::ACTION_SELL => 'danger',
            self::ACTION_STRONG_SELL => 'danger',
            default => 'secondary',
        };
    }
}
