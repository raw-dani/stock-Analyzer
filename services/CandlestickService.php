<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use Yii;

/**
 * Service Analisis & Scanner Pola Candlestick Paling Potensial dengan Konfirmasi Volume & Lokasi S/R (5-Layer Precision Engine).
 *
 * Menerapkan arsitektur 5 lapisan validasi kelembagaan (Institutional Precision):
 * 1. Candlestick Pattern Detection (Rasio Geometris Dinamis terhadap Range & ATR)
 * 2. Volume Confirmation Engine (RVOL 6 Kategori, Volume Spike/Climax, Dry-up, & Divergence)
 * 3. Market Structure & S/R Location Proximity (Jarak ATR terhadap Pivot, EMA20/50, SMA200, Fib Golden Pocket 61.8%-65%)
 * 4. Multi-Timeframe Validation (Tren Utama 1D vs Timeframe Entry 4H/2H/1H)
 * 5. Dedicated Scoring Engine (Matriks 7 Komponen 100% Bobot + Hard Filters untuk Mencegah False Signals)
 */
final class CandlestickService
{
    public const TIMEFRAME_1D = 24;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_1H = 1;

    public const PATTERN_HAMMER            = 'HAMMER';
    public const PATTERN_SHAKEOUT_LOW       = 'SHAKEOUT_LOW';
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

    // Status Konfirmasi Sinyal (Early vs Confirmed)
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_EARLY     = 'EARLY';
    public const STATUS_WATCHLIST = 'WATCHLIST';
    public const STATUS_INVALID   = 'INVALID';

    // Kategori Kualitas Setup Candlestick (Layer 5 Scoring)
    public const SETUP_EXCELLENT = 'EXCELLENT'; // Skor 85 - 100 (Prioritas Analisis)
    public const SETUP_STRONG    = 'STRONG';    // Skor 75 - 84 (Tunggu Entry Confirmation)
    public const SETUP_MODERATE  = 'MODERATE';  // Skor 60 - 74 (Watchlist)
    public const SETUP_WEAK      = 'WEAK';      // Skor 40 - 59 (Hindari Entry)
    public const SETUP_INVALID   = 'INVALID';   // Skor 0 - 39 (No Trade)

    // Kategori Volume RVOL
    public const VOL_VERY_LOW     = 'VERY_LOW';     // < 0.5x
    public const VOL_LOW          = 'LOW';          // 0.5 - 0.99x
    public const VOL_NORMAL       = 'NORMAL';       // 1.0 - 1.49x
    public const VOL_HIGH         = 'HIGH';         // 1.5 - 1.99x
    public const VOL_VERY_HIGH     = 'VERY_HIGH';     // 2.0 - 2.99x
    public const VOL_EXTREME      = 'EXTREME';      // >= 3.0x

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
            self::PATTERN_SHAKEOUT_LOW       => '⚡ Shakeout Low (Liquidity Sweep)',
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
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $patternFilter = (string) ($options['pattern'] ?? 'all');
        $volumeFilter = (string) ($options['volume_filter'] ?? 'all');
        $actionFilter = (string) ($options['action'] ?? 'all');
        $exchange = (string) ($options['exchange'] ?? '');
        $sector = (string) ($options['sector'] ?? '');
        $symbolSearch = strtoupper(trim((string) ($options['symbol'] ?? '')));
        $lookback = (int) ($options['lookback'] ?? self::DEFAULT_LOOKBACK);
        $minScore = (int) ($options['min_score'] ?? 0);
        $confirmationFilter = (string) ($options['confirmation_status'] ?? 'all');

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

            // Filter Status Konfirmasi (Early vs Confirmed)
            if ($confirmationFilter === 'confirmed_only' && ($analysis['confirmation_status'] ?? '') !== self::STATUS_CONFIRMED) {
                continue;
            }
            if ($confirmationFilter === 'early_only' && ($analysis['confirmation_status'] ?? '') !== self::STATUS_EARLY) {
                continue;
            }

            // Filter Minimal Skor
            if ($minScore > 0 && ($analysis['confidence_score'] ?? 0) < $minScore) {
                continue;
            }

            $results[] = $analysis;
        }

        return $results;
    }

    /**
     * Analisa mendalam candlestick untuk satu saham tertentu (5-Layer Validation Pipeline).
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

        // Ambil lilin Daily (1D) untuk validasi Multi-Timeframe (MTF Layer 4)
        $dailyCandles = ($timeframe === self::TIMEFRAME_1D) ? $candles : $this->dailyCandles($stockId, 60);

        $patternData = $this->detectBestPattern($candles, $dailyCandles, $timeframe);
        if ($patternData === null) {
            if (!empty($options['allow_neutral'])) {
                $patternData = $this->createNeutralPattern($candles, $dailyCandles, $timeframe);
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
     *
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $candles
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $dailyCandles
     * @param int $timeframe
     * @return array<string, mixed>
     */
    private function createNeutralPattern(array $candles, array $dailyCandles, int $timeframe): array
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

        $atr = $this->calculateAtr($candles, 14);
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
            'confirmation_status' => self::STATUS_WATCHLIST,
            'setup_quality' => self::SETUP_MODERATE,
            'decision_label' => 'WATCHLIST (KONSOLIDASI)',

            // 7-Component Score Matrix
            'score_matrix' => [
                'pattern_quality' => 50,
                'volume' => 50,
                'trend' => 50,
                'location' => 50,
                'mtf' => 50,
                'price_action' => 50,
                'volatility' => 50,
                'total' => 50,
            ],

            // Context & Location Details
            'location_context' => [
                'label' => 'Mid-Range Konsolidasi',
                'proximity' => 'Di antara Support & Resisten',
                'nearest_support' => $recentLow,
                'nearest_resistance' => $recentHigh,
                'dist_support_atr' => 1.5,
                'dist_resistance_atr' => 1.5,
                'near_key_level' => false,
            ],

            // Multi-Timeframe Context
            'mtf_context' => [
                'primary_tf' => '1D',
                'primary_trend' => 'NEUTRAL',
                'aligned' => true,
                'desc' => 'Timeframe harian bergerak mendatar.',
            ],

            // Volume Analysis
            'volume' => $cVol,
            'volume_sma20' => (int) round($volSMA20),
            'rvol' => $rvol,
            'volume_status' => $volAnalysis['status'],
            'volume_badge' => $volAnalysis['badge'],
            'volume_supports_bullish' => $volAnalysis['supports_bullish'],
            'volume_strength_label' => $volAnalysis['strength_label'],
            'volume_verdict' => $volAnalysis['verdict'],
            'volume_spike' => false,
            'volume_dryup' => false,
            'volume_divergence' => false,

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
     * Deteksi pola candlestick paling potensial pada bar-bar terkini (offset 0 -> 1 -> 2).
     *
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $candles
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $dailyCandles
     * @param int $timeframe
     * @return array<string, mixed>|null
     */
    private function detectBestPattern(array $candles, array $dailyCandles, int $timeframe): ?array
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

            // Sub-array sampai index untuk SMA Volume 20 & ATR
            $historyCandles = array_slice($candles, 0, $idx + 1);
            $volSMA20 = $this->calculateVolumeSMA($historyCandles, 20);

            // Deteksi pola pada bar ini
            $detected = $this->evaluateCandlestick(
                $curr,
                $prev,
                $prev2,
                $historyCandles,
                $dailyCandles,
                $volSMA20,
                $offset,
                $timeframe,
                $candles
            );

            if ($detected !== null) {
                return $detected;
            }
        }

        return null;
    }

    /**
     * Evaluasi matematis mendalam untuk setiap jenis pola candlestick (5-Layer Precision).
     *
     * @param array{open:float,high:float,low:float,close:float,volume:int,date:string} $curr
     * @param array{open:float,high:float,low:float,close:float,volume:int,date:string} $prev
     * @param array{open:float,high:float,low:float,close:float,volume:int,date:string} $prev2
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $history
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $dailyCandles
     * @param float $volSMA20
     * @param int $barOffset
     * @param int $timeframe
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $allCandles
     * @return array<string, mixed>|null
     */
    private function evaluateCandlestick(
        array $curr,
        array $prev,
        array $prev2,
        array $history,
        array $dailyCandles,
        float $volSMA20,
        int $barOffset,
        int $timeframe,
        array $allCandles
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
        $pVol = (int) $prev['volume'];

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

        // ATR 14 periode
        $atr = $this->calculateAtr($history, 14);

        // =========================================================
        // LAYER 3: MARKET STRUCTURE & TREND CONTEXT (Prior Trend)
        // =========================================================
        $shortHistory = array_slice($history, -9, 8);
        $firstClose = (float) ($shortHistory[0]['close'] ?? $cClose);
        $lastShortClose = (float) ($prev['close'] ?? $cClose);
        $trendSlope = ($lastShortClose - $firstClose) / max(0.001, $firstClose);

        // Evaluasi trendline kemiringan 6-8 bar sebelum pola
        $isShortDowntrend = $trendSlope <= -0.008; // Terjadi penurunan jelas sebelum pola
        $isShortUptrend = $trendSlope >= 0.008;   // Terjadi reli/kenaikan jelas sebelum pola
        $isSideways = abs($trendSlope) < 0.008;    // Sedang mendatar (sideways)

        // =========================================================
        // LAYER 2: ADVANCED VOLUME ANALYSIS & RVOL
        // =========================================================
        $rvol = round($cVol / max(1.0, $volSMA20), 2);
        $volAnalysis = $this->analyzeVolume($cVol, $volSMA20, $rvol, $isBullish);

        $isVolumeSpike = ($rvol >= 2.0);
        $isVolumeClimax = ($rvol >= 3.0);

        // Deteksi Volume Dry-Up (Penurunan volume saat pullback koreksi)
        $volSlice5 = array_slice(array_column($shortHistory, 'volume'), -4);
        $isVolumeDryUp = count($volSlice5) >= 3 && ($volSlice5[count($volSlice5) - 1] < $volSlice5[0] * 0.75);

        // Deteksi Volume Divergence (Harga buat Higher High tapi Volume melorot)
        $isVolumeDivergence = false;
        if ($isBullish && $cClose > $pClose && $cVol < ($pVol * 0.70)) {
            $isVolumeDivergence = true;
        }

        // =========================================================
        // LAYER 1: CANDLESTICK PATTERN DETECTION (Geometric & ATR)
        // =========================================================
        $patternCode = null;
        $patternName = null;
        $patternSubtype = null;
        $action = null;
        $patternDescription = null;
        $entryAdvice = '';
        $exitAdvice = '';
        $patternQualityScore = 70; // 0 - 100

        // 1. POLA MARUBOZU (Body >= 88%, Wick <= 6%, Body / ATR >= 0.8)
        $bodyAtrRatio = $atr > 0 ? ($cBody / $atr) : 1.0;
        if ($bodyRatio >= 0.88 && $upperRatio <= 0.06 && $lowerRatio <= 0.06 && $bodyAtrRatio >= 0.75) {
            if ($isBullish) {
                $patternCode = self::PATTERN_BULLISH_MARUBOZU;
                $patternName = 'Bullish Marubozu';
                $patternSubtype = 'Momentum Breakout Solid';
                $action = self::ACTION_STRONG_BUY;
                $patternDescription = 'Candle hijau pejal tanpa bayangan atas/bawah (Body/Range ≥88%, Body/ATR ≥0.75) mengindikasikan dominasi mutlak pembeli dari pembukaan hingga penutupan.';
                $entryAdvice = 'Entry agresif pada level close atau antri di batas 50% body candle Marubozu.';
                $exitAdvice = 'Pasang trailing stop di bawah Low candle Marubozu.';
                $patternQualityScore = 95;
            } else {
                $patternCode = self::PATTERN_BEARISH_MARUBOZU;
                $patternName = 'Bearish Marubozu';
                $patternSubtype = 'Tekanan Jual Masif (Dump)';
                $action = self::ACTION_STRONG_SELL;
                $patternDescription = 'Candle merah penuh tanpa perlawanan dari pembeli. Menandakan aksi distribusi institusional yang sangat agresif.';
                $entryAdvice = 'HINDARI ENTRY BELI.';
                $exitAdvice = 'Segera lakukan penjualan (Exit/Cut Loss) untuk menghindari penurunan lebih dalam.';
                $patternQualityScore = 95;
            }
        }

        // 2. POLA ENGULFING (Full Body Takeover dengan toleransi gap)
        if ($patternCode === null) {
            if (!$pIsBullish && $isBullish
                && $cOpen <= ($pClose * 1.004)
                && $cClose >= ($pOpen * 0.996)
                && $cBody >= ($pBody * 1.05)
            ) {
                $patternCode = self::PATTERN_BULLISH_ENGULFING;
                $patternName = 'Bullish Engulfing';
                $patternSubtype = 'Full Real Body Takeover (Reversal)';
                $action = self::ACTION_STRONG_BUY;
                $patternDescription = 'Candle bullish hijau menelan penuh badan candle bearish sebelumnya. Menandakan pergeseran momentum total dari penjual ke pembeli.';
                $entryAdvice = 'Masuk saat candle ditutup atau antri saat retest separuh badan candle engulfing.';
                $exitAdvice = 'Target profit di resistance terdekat (TP1). Stop loss di bawah low formasi engulfing.';
                $patternQualityScore = 90;
            } elseif ($pIsBullish && !$isBullish
                && $cOpen >= ($pClose * 0.996)
                && $cClose <= ($pOpen * 1.004)
                && $cBody >= ($pBody * 1.05)
            ) {
                $patternCode = self::PATTERN_BEARISH_ENGULFING;
                $patternName = 'Bearish Engulfing';
                $patternSubtype = 'Supply Menelan Demand di Puncak';
                $action = self::ACTION_STRONG_SELL;
                $patternDescription = 'Candle bearish merah menelan penuh badan candle bullish hijau sebelumnya. Sinyal kuat berakhirnya tren naik.';
                $entryAdvice = 'HINDARI PEMBELIAN.';
                $exitAdvice = 'Amankan keuntungan segera (Take Profit/Exit) sebelum koreksi tajam berlanjut.';
                $patternQualityScore = 90;
            }
        }

        // 3. POLA SHAKEOUT LOW (Liquidity Sweep & False Breakdown Pin Bar)
        if ($patternCode === null) {
            $isUndercutPrev = ($cLow < $pLow);
            $undercutPct = $pLow > 0 ? round((($pLow - $cLow) / $pLow) * 100, 2) : 0.0;
            $hasLongLowerWick = ($lowerRatio >= 0.45 || ($cLowerWick >= (1.5 * $cBody) && $cLowerWick > 0));
            $isReboundClose = ($cClose >= $pLow && ($isBullish || $cClose >= $pClose * 0.998));

            if ($isUndercutPrev && $undercutPct >= 0.2 && $undercutPct <= 5.0 && $hasLongLowerWick && $isReboundClose) {
                $patternCode = self::PATTERN_SHAKEOUT_LOW;
                $patternName = 'Shakeout Low (Liquidity Sweep)';
                $patternSubtype = 'Bear Trap Pin Rejection';
                $action = self::ACTION_STRONG_BUY;
                $patternDescription = "Harga sempat menusuk menembus level terendah bar sebelumnya sebesar {$undercutPct}% untuk membersihkan stop loss (shakeout), namun langsung diserap dengan ekor bawah panjang dan ditutup rebound ke atas.";
                $entryAdvice = "Waktu Beli Terbaik: Entry saat konfirmasi penolakan sweep terbukti valid (dekat level close saat ini).";
                $exitAdvice = "Pasang Stop Loss ketat tepat di bawah jarum sweep Low (\${$cLow}).";
                $patternQualityScore = 92;
            }
        }

        // 4. POLA HAMMER & INVERTED HAMMER
        if ($patternCode === null) {
            // Hammer: Lower shadow >= 2x body, upper shadow <= 0.5x body, body <= 35% range, close > 50% range
            if ($cLowerWick >= (2.0 * $cBody)
                && $cUpperWick <= (0.5 * $cBody)
                && $bodyRatio <= 0.35
                && $cClose > ($cLow + ($cRange * 0.50))
            ) {
                $patternCode = self::PATTERN_HAMMER;
                $patternName = 'Hammer';
                $patternSubtype = 'Penolakan Harga Bawah (Rejection Wick)';
                $action = self::ACTION_BUY;
                $patternDescription = 'Ekor bawah panjang (≥2x body) menunjukkan seller sempat menekan harga turun tajam, namun pembeli melakukan perlawanan kuat hingga harga ditutup di paruh atas range.';
                $entryAdvice = 'Waktu Beli Tepat: Masuk saat harga menembus level High Hammer atau beli di dekat batas penutupan.';
                $exitAdvice = 'Pasang Stop Loss ketat tepat di bawah jarum terendah (Low Hammer).';
                $patternQualityScore = 88;
            }
            // Inverted Hammer: Upper shadow >= 2x body, lower shadow <= 0.5x body, body <= 35% range, close < 50% range
            elseif ($cUpperWick >= (2.0 * $cBody)
                && $cLowerWick <= (0.5 * $cBody)
                && $bodyRatio <= 0.35
            ) {
                $patternCode = self::PATTERN_INVERTED_HAMMER;
                $patternName = 'Inverted Hammer';
                $patternSubtype = 'Percobaan Reversal Pembeli';
                $action = self::ACTION_BUY;
                $patternDescription = 'Upper shadow panjang menunjukkan minat pembeli mulai menguji harga atas di area dasar. Akumulasi awal potensi pembalikan arah.';
                $entryAdvice = 'Waktu Beli: Masuk bertahap saat candle berikutnya mengonfirmasi penembusan level High.';
                $exitAdvice = 'Stop Loss di bawah Low Inverted Hammer.';
                $patternQualityScore = 82;
            }
        }

        // 5. POLA SHOOTING STAR (Upper shadow >= 2x body, lower shadow <= 0.5x body, close < 50% range)
        if ($patternCode === null) {
            if ($cUpperWick >= (2.0 * $cBody)
                && $cLowerWick <= (0.5 * $cBody)
                && $bodyRatio <= 0.35
                && $cClose < ($cLow + ($cRange * 0.50))
            ) {
                $patternCode = self::PATTERN_SHOOTING_STAR;
                $patternName = 'Shooting Star';
                $patternSubtype = 'Penolakan Harga Atas (Bearish Rejection)';
                $action = self::ACTION_SELL;
                $patternDescription = 'Ekor atas panjang di area puncak menunjukkan pembeli gagal mempertahankan level tinggi dan seller menolak kenaikan harga secara agresif.';
                $entryAdvice = 'HINDARI ENTRY BELI (Potensi Bull Trap).';
                $exitAdvice = 'Waktu Jual Tepat: Ambil keuntungan (Take Profit) segera atau pasang stop order di bawah Low Shooting Star.';
                $patternQualityScore = 88;
            }
        }

        // 6. POLA 4 SPESIES DOJI (Body <= 10% range)
        if ($patternCode === null && $bodyRatio <= 0.10 && $cRange > 0.01 && $cVol > 0) {
            $patternCode = self::PATTERN_DOJI;

            if ($lowerRatio >= 0.65 && $upperRatio <= 0.10) {
                $patternName = 'Dragonfly Doji';
                $patternSubtype = 'Bullish Support Rejection';
                $action = self::ACTION_BUY;
                $patternDescription = 'Open, High, dan Close hampir sama di puncak range, dengan ekor bawah sangat panjang. Penolakan harga murah yang sangat kuat.';
                $entryAdvice = 'Waktu Beli: Masuk saat breakout High Dragonfly Doji.';
                $exitAdvice = 'Stop loss di bawah Low ekor panjang.';
                $patternQualityScore = 85;
            } elseif ($upperRatio >= 0.65 && $lowerRatio <= 0.10) {
                $patternName = 'Gravestone Doji';
                $patternSubtype = 'Bearish Resistance Rejection';
                $action = self::ACTION_SELL;
                $patternDescription = 'Open, Low, dan Close berada di dasar range dengan jarum atas sangat panjang. Upaya buyer terbantai oleh supply seller.';
                $entryAdvice = 'HINDARI PEMBELIAN.';
                $exitAdvice = 'Waktu Jual: Exit posisi long segera.';
                $patternQualityScore = 85;
            } elseif ($upperRatio >= 0.30 && $lowerRatio >= 0.30) {
                $patternName = 'Long-Legged Doji';
                $patternSubtype = 'Volatilitas Tinggi & Pertarungan Sengit';
                $action = self::ACTION_WATCH;
                $patternDescription = 'Bayangan atas dan bawah sama-sama panjang merefleksikan pertarungan sengit tanpa pemenang jelas.';
                $entryAdvice = 'WAKTU PANTAU: Tunggu arah resolusi breakout High atau Low Doji.';
                $exitAdvice = 'Amankan profit parsial jika sudah memegang barang.';
                $patternQualityScore = 75;
            } else {
                $patternName = 'Standard Neutral Doji';
                $patternSubtype = 'Indecision Keseimbangan Pasar';
                $action = self::ACTION_WATCH;
                $patternDescription = 'Keseimbangan sempurna antara penawaran dan permintaan. Sering menjadi penanda akhir dari sebuah tren (inflection point).';
                $entryAdvice = 'WAKTU WAIT & SEE: Konfirmasi candle berikutnya menentukan arah.';
                $exitAdvice = 'Pasang proteksi trailing stop.';
                $patternQualityScore = 70;
            }
        }

        // 7. POLA 3-BAR: MORNING STAR & EVENING STAR
        if ($patternCode === null) {
            $p2Open = (float) $prev2['open'];
            $p2Close = (float) $prev2['close'];
            $p2IsBullish = $p2Close >= $p2Open;
            $p2Range = max(0.0001, (float) $prev2['high'] - (float) $prev2['low']);
            $p2Body = abs($p2Close - $p2Open);

            // Morning Star
            if (!$p2IsBullish && ($p2Body / $p2Range >= 0.5)
                && ($pBody / $pRange <= 0.35)
                && $isBullish
                && $cClose >= (($p2Open + $p2Close) / 2)
            ) {
                $patternCode = self::PATTERN_MORNING_STAR;
                $patternName = 'Morning Star';
                $patternSubtype = 'Formasi 3-Candle Reversal Kuat';
                $action = self::ACTION_STRONG_BUY;
                $patternDescription = 'Bintang pagi terbentuk sempurna dari transisi bearish -> konsolidasi -> kebangkitan bullish masif.';
                $entryAdvice = 'Waktu Beli: Entry ideal pada penutupan candle ketiga atau pullback ringan.';
                $exitAdvice = 'Stop Loss di bawah Low candle bintang tengah.';
                $patternQualityScore = 92;
            }
            // Evening Star
            elseif ($p2IsBullish && ($p2Body / $p2Range >= 0.5)
                && ($pBody / $pRange <= 0.35)
                && !$isBullish
                && $cClose <= (($p2Open + $p2Close) / 2)
            ) {
                $patternCode = self::PATTERN_EVENING_STAR;
                $patternName = 'Evening Star';
                $patternSubtype = 'Formasi 3-Candle Reversal Puncak';
                $action = self::ACTION_STRONG_SELL;
                $patternDescription = 'Bintang senja menandakan kelelahan tren naik dan dimulainya gelombang penurunan tajam.';
                $entryAdvice = 'HINDARI PEMBELIAN.';
                $exitAdvice = 'Waktu Jual: Take profit segera sebelum koreksi berlanjut.';
                $patternQualityScore = 92;
            }
        }

        if ($patternCode === null) {
            return null;
        }

        // =========================================================
        // LAYER 3: S/R LOCATION & FIBONACCI GOLDEN POCKET PROXIMITY
        // =========================================================
        $locationEval = $this->evaluateLocationContext($cClose, $history, $atr);
        $distSupportAtr = $locationEval['dist_support_atr'];
        $distResistAtr = $locationEval['dist_resistance_atr'];
        $nearSupport = ($distSupportAtr <= 0.8 || $locationEval['near_golden_pocket']);
        $nearResistance = ($distResistAtr <= 0.8);

        // =========================================================
        // LAYER 4: MULTI-TIMEFRAME ALIGNMENT (Daily vs Entry TF)
        // =========================================================
        $mtfEval = $this->evaluateMultiTimeframe($dailyCandles, $cClose);

        // =========================================================
        // LAYER 4b: NEXT CANDLE CONFIRMATION (Early vs Confirmed)
        // =========================================================
        $confirmationStatus = self::STATUS_EARLY;
        $priceActionScore = 65;

        // Jika pola terjadi di bar kemarin (offset 1) atau 2 bar lalu (offset 2),
        // kita bisa memeriksa apakah lilin setelahnya (next candle) berhasil mengonfirmasi arah!
        if ($barOffset > 0) {
            $nextIdx = count($history) - $barOffset;
            $nextCandle = $allCandles[$nextIdx] ?? null;
            if ($nextCandle !== null) {
                $nextClose = (float) $nextCandle['close'];
                if (in_array($action, [self::ACTION_BUY, self::ACTION_STRONG_BUY], true)) {
                    if ($nextClose > $cHigh) {
                        $confirmationStatus = self::STATUS_CONFIRMED;
                        $priceActionScore = 95;
                    } elseif ($nextClose < $cLow) {
                        $confirmationStatus = self::STATUS_INVALID;
                        $priceActionScore = 20;
                    } else {
                        $confirmationStatus = self::STATUS_EARLY;
                        $priceActionScore = 70;
                    }
                } elseif (in_array($action, [self::ACTION_SELL, self::ACTION_STRONG_SELL], true)) {
                    if ($nextClose < $cLow) {
                        $confirmationStatus = self::STATUS_CONFIRMED;
                        $priceActionScore = 95;
                    } else {
                        $confirmationStatus = self::STATUS_EARLY;
                        $priceActionScore = 65;
                    }
                }
            }
        } else {
            // Bar terkini baru selesai terbentuk
            $confirmationStatus = self::STATUS_EARLY;
            $priceActionScore = ($isBullish && $cClose >= ($cLow + ($cRange * 0.75))) ? 85 : 70;
        }

        // =========================================================
        // LAYER 5: 7-COMPONENT DEDICATED SCORING MATRIX (100% Total)
        // =========================================================
        // 1. Pattern Quality (20%)
        $scorePattern = $patternQualityScore;

        // 2. Volume Confirmation (20%)
        $scoreVolume = match ($volAnalysis['status']) {
            self::VOL_SURGE => 95,
            self::VOL_SUPPORTIVE => 85,
            self::VOL_MODERATE => 65,
            self::VOL_WEAK => 30, // Volume kering
            self::VOL_DISTRIBUTION => in_array($action, [self::ACTION_SELL, self::ACTION_STRONG_SELL], true) ? 95 : 20,
            default => 50,
        };
        if ($isVolumeSpike && in_array($action, [self::ACTION_BUY, self::ACTION_STRONG_BUY], true)) {
            $scoreVolume = min(100, $scoreVolume + 5);
        }
        if ($isVolumeDryUp && $nearSupport) {
            $scoreVolume = min(100, $scoreVolume + 10); // Volume kering saat pullback di support adalah bullish!
        }
        if ($isVolumeDivergence) {
            $scoreVolume = max(30, $scoreVolume - 20); // Warning divergence
        }

        // 3. Trend Alignment (15%)
        $scoreTrend = 50;
        if (in_array($action, [self::ACTION_BUY, self::ACTION_STRONG_BUY], true)) {
            if ($patternCode === self::PATTERN_BULLISH_MARUBOZU) {
                $scoreTrend = $isShortUptrend ? 90 : 70; // Marubozu bagus saat breakout tren naik
            } else {
                $scoreTrend = $isShortDowntrend ? 92 : ($isSideways ? 45 : 30); // Hammer/Engulfing di downtrend bernilai tinggi
            }
        } else {
            $scoreTrend = $isShortUptrend ? 92 : ($isSideways ? 45 : 30); // Shooting Star di uptrend bernilai tinggi
        }

        // 4. S/R Location Proximity (15%)
        $scoreLocation = 50;
        if (in_array($action, [self::ACTION_BUY, self::ACTION_STRONG_BUY], true)) {
            if ($distSupportAtr <= 0.5 || $locationEval['near_golden_pocket']) {
                $scoreLocation = 95; // Tepat di Support / Golden Pocket
            } elseif ($distSupportAtr <= 1.0) {
                $scoreLocation = 80;
            } elseif ($distSupportAtr <= 2.0) {
                $scoreLocation = 60;
            } else {
                $scoreLocation = 35; // Terlalu jauh dari support
            }
        } else {
            if ($distResistAtr <= 0.5) {
                $scoreLocation = 95; // Tepat di Resisten
            } elseif ($distResistAtr <= 1.0) {
                $scoreLocation = 80;
            } else {
                $scoreLocation = 40;
            }
        }

        // 5. Multi-Timeframe Confirmation (15%)
        $scoreMtf = $mtfEval['score'];

        // 6. Price Action & Next Candle (10%)
        $scorePriceAction = $priceActionScore;

        // 7. Volatility Context (5%)
        $scoreVolatility = ($bodyAtrRatio >= 0.5 && $bodyAtrRatio <= 2.5) ? 90 : 60;

        // Total Weighted Score (0 - 100)
        $weightedTotal = (
            ($scorePattern * 0.20) +
            ($scoreVolume * 0.20) +
            ($scoreTrend * 0.15) +
            ($scoreLocation * 0.15) +
            ($scoreMtf * 0.15) +
            ($scorePriceAction * 0.10) +
            ($scoreVolatility * 0.05)
        );

        $confidenceScore = (int) round($weightedTotal);

        // Offset Penalty jika bukan lilin terakhir
        if ($barOffset > 0) {
            $confidenceScore = max(10, $confidenceScore - ($barOffset * 3));
        }

        // =========================================================
        // HARD FILTERS (GUARD-RAILS)
        // =========================================================
        // 1. Hammer di tengah sideways tanpa volume -> Dibatasi skor maks 55
        if ($patternCode === self::PATTERN_HAMMER && $isSideways && $rvol < 1.0) {
            $confidenceScore = min(55, $confidenceScore);
            $action = self::ACTION_WATCH;
        }

        // 2. Volume Kering (RVOL < 0.6) pada sinyal beli -> Turunkan status
        if ($rvol < 0.60 && in_array($action, [self::ACTION_BUY, self::ACTION_STRONG_BUY], true)) {
            $confidenceScore = min(58, $confidenceScore);
            $action = self::ACTION_WATCH;
        }

        // 3. Konfirmasi lilin berikutnya gagal (break down low)
        if ($confirmationStatus === self::STATUS_INVALID) {
            $confidenceScore = min(35, $confidenceScore);
            $action = self::ACTION_WATCH;
        }

        // Klasifikasi Kualitas Setup
        $setupQuality = match (true) {
            $confidenceScore >= 85 => self::SETUP_EXCELLENT,
            $confidenceScore >= 75 => self::SETUP_STRONG,
            $confidenceScore >= 60 => self::SETUP_MODERATE,
            $confidenceScore >= 40 => self::SETUP_WEAK,
            default => self::SETUP_INVALID,
        };

        $decisionLabel = match ($setupQuality) {
            self::SETUP_EXCELLENT => (str_contains($action, 'BUY') ? 'STRONG BUY (PRIORITAS ANALISIS)' : 'STRONG SELL'),
            self::SETUP_STRONG => (str_contains($action, 'BUY') ? 'BUY CANDIDATE (TERVALIDASI)' : 'SELL CANDIDATE'),
            self::SETUP_MODERATE => 'WATCHLIST (TUNGGU KONFIRMASI)',
            self::SETUP_WEAK => 'HINDARI ENTRY (SETUP LEMAH)',
            default => 'NO TRADE / INVALID',
        };

        // Kalkulasi Trading Plan (Entry, SL adaptif ATR, TP1, TP2, R:R)
        $tradingPlan = $this->calculateTradingPlan($curr, $prev, $action, $patternCode, $atr);

        // 5-Point Checklist Konfirmasi Dinamis
        $checklist = [
            [
                'title' => 'Formasi Pola Geometris Valid',
                'pass' => ($scorePattern >= 75),
                'desc' => "Pola {$patternName} memenuhi kaidah rasio matematis & range ATR ({$scorePattern}/100).",
            ],
            [
                'title' => 'Konfirmasi Volume & RVOL',
                'pass' => ($scoreVolume >= 70),
                'desc' => "RVOL {$rvol}x vs SMA20. {$volAnalysis['strength_label']} ({$scoreVolume}/100).",
            ],
            [
                'title' => 'Struktur Tren & Koreksi Mendukung',
                'pass' => ($scoreTrend >= 70),
                'desc' => in_array($action, [self::ACTION_BUY, self::ACTION_STRONG_BUY], true)
                    ? ($isShortDowntrend ? 'Didahului koreksi harga yang cukup.' : 'Perhatian: Pola muncul di luar downtrend ideal.')
                    : ($isShortUptrend ? 'Didahului reli kenaikan yang cukup.' : 'Perhatian: Pola muncul di luar uptrend ideal.'),
            ],
            [
                'title' => 'Kedekatan Lokasi Support / Resistance',
                'pass' => ($scoreLocation >= 70),
                'desc' => "Jarak ke level kunci: {$locationEval['proximity_label']} ({$scoreLocation}/100).",
            ],
            [
                'title' => 'Keselarasan Multi-Timeframe (1D)',
                'pass' => ($scoreMtf >= 70),
                'desc' => "Struktur tren 1D: {$mtfEval['trend_label']} ({$scoreMtf}/100).",
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
            'confirmation_status' => $confirmationStatus,
            'setup_quality' => $setupQuality,
            'decision_label' => $decisionLabel,

            // 7-Component Score Matrix Breakdown
            'score_matrix' => [
                'pattern_quality' => (int) round($scorePattern),
                'volume' => (int) round($scoreVolume),
                'trend' => (int) round($scoreTrend),
                'location' => (int) round($scoreLocation),
                'mtf' => (int) round($scoreMtf),
                'price_action' => (int) round($scorePriceAction),
                'volatility' => (int) round($scoreVolatility),
                'total' => $confidenceScore,
            ],

            // Context & Location Details
            'location_context' => [
                'label' => $locationEval['proximity_label'],
                'proximity' => $locationEval['desc'],
                'nearest_support' => $locationEval['support'],
                'nearest_resistance' => $locationEval['resistance'],
                'dist_support_atr' => $distSupportAtr,
                'dist_resistance_atr' => $distResistAtr,
                'near_key_level' => ($distSupportAtr <= 1.0 || $distResistAtr <= 1.0),
                'golden_pocket_price' => $locationEval['golden_pocket_price'] ?? null,
            ],

            // Multi-Timeframe Context
            'mtf_context' => [
                'primary_tf' => '1D',
                'primary_trend' => $mtfEval['trend_label'],
                'aligned' => ($scoreMtf >= 70),
                'desc' => $mtfEval['desc'],
            ],

            // Volume Analysis Data
            'volume' => $cVol,
            'volume_sma20' => (int) round($volSMA20),
            'rvol' => $rvol,
            'volume_status' => $volAnalysis['status'],
            'volume_badge' => $volAnalysis['badge'],
            'volume_supports_bullish' => $volAnalysis['supports_bullish'],
            'volume_strength_label' => $volAnalysis['strength_label'],
            'volume_verdict' => $volAnalysis['verdict'],
            'volume_spike' => $isVolumeSpike,
            'volume_climax' => $isVolumeClimax,
            'volume_dryup' => $isVolumeDryUp,
            'volume_divergence' => $isVolumeDivergence,

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
     * Evaluasi Lokasi S/R, EMA20, EMA50, SMA200, dan Golden Pocket Fibonacci (Layer 3).
     *
     * @param float $currClose
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $history
     * @param float $atr
     * @return array<string, mixed>
     */
    private function evaluateLocationContext(float $currClose, array $history, float $atr): array
    {
        $closes = array_column($history, 'close');
        $highs = array_column($history, 'high');
        $lows = array_column($history, 'low');

        $ema20Series = $this->calculateEmaSeries($closes, 20);
        $ema50Series = $this->calculateEmaSeries($closes, 50);
        $sma200Series = $this->calculateSmaSeries($closes, min(200, count($closes)));

        $currEma20 = end($ema20Series);
        $currEma50 = end($ema50Series);
        $currSma200 = end($sma200Series);

        // Support & Resisten Pivot Lokal 20 periode
        $recentSlice = array_slice($history, -25);
        $localSupport = min(array_column($recentSlice, 'low'));
        $localResistance = max(array_column($recentSlice, 'high'));

        // Fibonacci Golden Pocket (61.8% - 65%)
        $maxHigh = max($highs);
        $minLow = min($lows);
        $diff = max(0.01, $maxHigh - $minLow);
        $fib618 = $maxHigh - ($diff * 0.618);
        $fib650 = $maxHigh - ($diff * 0.650);
        $goldenPocketAvg = ($fib618 + $fib650) / 2;

        $nearGoldenPocket = ($currClose >= ($fib650 * 0.99) && $currClose <= ($fib618 * 1.01));

        // Jarak ke Support terdekat (dalam kelipatan ATR)
        $potentialSupports = array_filter([$localSupport, $currEma20, $currEma50, $currSma200, $goldenPocketAvg], fn($v) => $v !== null && $v <= $currClose);
        $nearestSupport = !empty($potentialSupports) ? max($potentialSupports) : $localSupport;
        $distSupportAtr = $atr > 0 ? round(($currClose - $nearestSupport) / $atr, 2) : 1.0;

        // Jarak ke Resisten terdekat (dalam kelipatan ATR)
        $potentialResistances = array_filter([$localResistance, $currEma20, $currEma50, $currSma200], fn($v) => $v !== null && $v >= $currClose);
        $nearestResistance = !empty($potentialResistances) ? min($potentialResistances) : $localResistance;
        $distResistAtr = $atr > 0 ? round(($nearestResistance - $currClose) / $atr, 2) : 1.0;

        $proxLabel = 'Mid-air (Relatif Jauh)';
        if ($nearGoldenPocket) {
            $proxLabel = '🎯 Golden Pocket Fib 61.8%-65%';
        } elseif ($distSupportAtr <= 0.5) {
            $proxLabel = '🛡️ Tepat di Support / EMA (' . $distSupportAtr . ' ATR)';
        } elseif ($distSupportAtr <= 1.0) {
            $proxLabel = 'Dekat Support (' . $distSupportAtr . ' ATR)';
        } elseif ($distResistAtr <= 0.5) {
            $proxLabel = '⚠️ Tepat di Resistance (' . $distResistAtr . ' ATR)';
        }

        return [
            'proximity_label' => $proxLabel,
            'desc' => "Support: \${$nearestSupport} ({$distSupportAtr} ATR), Resisten: \${$nearestResistance} ({$distResistAtr} ATR)",
            'support' => round($nearestSupport, 2),
            'resistance' => round($nearestResistance, 2),
            'dist_support_atr' => $distSupportAtr,
            'dist_resistance_atr' => $distResistAtr,
            'near_golden_pocket' => $nearGoldenPocket,
            'golden_pocket_price' => round($goldenPocketAvg, 2),
        ];
    }

    /**
     * Evaluasi Tren Multi-Timeframe Daily 1D (Layer 4).
     *
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $dailyCandles
     * @param float $currClose
     * @return array{score:int,trend_label:string,desc:string}
     */
    private function evaluateMultiTimeframe(array $dailyCandles, float $currClose): array
    {
        if (count($dailyCandles) < 20) {
            return ['score' => 60, 'trend_label' => 'NETRAL', 'desc' => 'Data Daily historis terbatas.'];
        }

        $dailyCloses = array_column($dailyCandles, 'close');
        $sma20Series = $this->calculateSmaSeries($dailyCloses, 20);
        $sma50Series = $this->calculateSmaSeries($dailyCloses, min(50, count($dailyCloses)));

        $dailySma20 = end($sma20Series);
        $dailySma50 = end($sma50Series);

        $isDailyUptrend = ($dailySma20 !== null && $dailySma50 !== null && $dailySma20 >= $dailySma50 && $currClose >= ($dailySma20 * 0.98));
        $isDailyDowntrend = ($dailySma20 !== null && $dailySma50 !== null && $dailySma20 < $dailySma50 && $currClose <= ($dailySma20 * 1.02));

        if ($isDailyUptrend) {
            return [
                'score' => 90,
                'trend_label' => 'BULLISH TREN UTAMA (1D)',
                'desc' => 'Tren Daily selaras: Harga di atas SMA20 & SMA20 > SMA50.',
            ];
        }

        if ($isDailyDowntrend) {
            return [
                'score' => 45,
                'trend_label' => 'BEARISH TREN UTAMA (1D)',
                'desc' => 'Tren Daily bearish: Membeli pola reversal di bawah tekanan downtrend mayor.',
            ];
        }

        return [
            'score' => 70,
            'trend_label' => 'SIDEWAYS / KONSOLIDASI (1D)',
            'desc' => 'Tren Daily berada di area netral konsolidasi.',
        ];
    }

    /**
     * Analisa Volume: Memperhitungkan secara eksplisit apakah volume mendukung kenaikan saham (Layer 2).
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
     * Hitung Average True Range (ATR) untuk pengukuran volatilitas harga lilin.
     *
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $candles
     * @param int $period
     * @return float
     */
    public function calculateAtr(array $candles, int $period = 14): float
    {
        $n = count($candles);
        if ($n < 2) {
            return 0.0;
        }

        $trValues = [];
        for ($i = 1; $i < $n; $i++) {
            $high = (float) $candles[$i]['high'];
            $low = (float) $candles[$i]['low'];
            $prevClose = (float) $candles[$i - 1]['close'];

            $tr = max($high - $low, abs($high - $prevClose), abs($low - $prevClose));
            $trValues[] = $tr;
        }

        $slice = array_slice($trValues, -$period);
        return count($slice) > 0 ? (array_sum($slice) / count($slice)) : 0.0;
    }

    /**
     * Kalkulasi Level Trading Plan Presisi (Entry, Stop Loss Dinamis ATR, TP1, TP2, RR Ratio).
     *
     * @param array{open:float,high:float,low:float,close:float,volume:int,date:string} $curr
     * @param array{open:float,high:float,low:float,close:float,volume:int,date:string} $prev
     * @param string $action
     * @param string $patternCode
     * @param float $atr
     * @return array<string, mixed>
     */
    private function calculateTradingPlan(array $curr, array $prev, string $action, string $patternCode, float $atr = 0.0): array
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

            // Stop loss tepat di bawah swing low pola dengan buffer dinamis ATR
            $patternLow = min($cLow, $pLow);
            if ($atr > 0.0) {
                $atrBuffer = $atr * 1.2;
                $effectiveBuffer = max($entry * 0.015, min($entry * 0.06, $atrBuffer));
                $stopLoss = round($patternLow - $effectiveBuffer, 2);
            } else {
                $stopLoss = round($patternLow * 0.99, 2);
            }

            // Batasi agar stop loss tidak melebihi entry dan tidak terlalu dekat
            if ($stopLoss >= $entry || ($entry - $stopLoss) < ($entry * 0.015)) {
                $stopLoss = round($entry * 0.975, 2);
            }

            $risk = max(0.01, $entry - $stopLoss);
            $stopLossPct = round((($entry - $stopLoss) / $entry) * 100, 2);

            // TP1: 1.6x risk, TP2: 2.6x risk
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
            $patternHigh = max($cHigh, (float) $prev['high']);
            if ($atr > 0.0) {
                $atrBuffer = $atr * 1.2;
                $effectiveBuffer = max($entry * 0.015, min($entry * 0.06, $atrBuffer));
                $stopLoss = round($patternHigh + $effectiveBuffer, 2);
            } else {
                $stopLoss = round($patternHigh * 1.01, 2);
            }
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
     * Helper perhitungan deret EMA.
     */
    private function calculateEmaSeries(array $values, int $period): array
    {
        $n = count($values);
        $result = [];
        if ($n < $period) {
            return array_fill(0, $n, null);
        }
        $multiplier = 2 / ($period + 1);
        $initial = array_slice($values, 0, $period);
        $prevEma = array_sum($initial) / $period;
        for ($i = 0; $i < $period - 1; $i++) {
            $result[$i] = null;
        }
        $result[$period - 1] = $prevEma;
        for ($i = $period; $i < $n; $i++) {
            $prevEma = (($values[$i] - $prevEma) * $multiplier) + $prevEma;
            $result[$i] = $prevEma;
        }
        return $result;
    }

    /**
     * Helper perhitungan deret SMA.
     */
    private function calculateSmaSeries(array $values, int $period): array
    {
        $n = count($values);
        $result = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i < $period - 1) {
                $result[$i] = null;
            } else {
                $slice = array_slice($values, $i - $period + 1, $period);
                $result[$i] = array_sum($slice) / $period;
            }
        }
        return $result;
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
            // fallback
        }

        return [$this->dailyCandles($stockId, $lookback), true];
    }

    /**
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
     * Agregasi lilin 1H -> 2H / 4H berdasarkan sesi harian yang konsisten.
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

        // Kelompokkan per sesi tanggal kalender (Y-m-d) agar batas candle 2H / 4H selalu konsisten
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
            self::PATTERN_SHAKEOUT_LOW => 'warning text-dark',
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
            self::PATTERN_SHAKEOUT_LOW => '⚡',
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
