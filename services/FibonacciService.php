<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use Yii;

/**
 * Service Analisis & Scanner Fibonacci Retracement & Extension.
 *
 * Mengidentifikasi waktu & posisi beli dan jual terbaik melalui:
 * - Swing High & Swing Low Detection (Puncak & Lembah Gelombang Harga)
 * - Fibonacci Retracement Levels: 23.6%, 38.2%, 50.0%, 61.8% (Golden Pocket), 78.6%
 * - Fibonacci Extension Targets: 127.2%, 161.8% (Golden Target), 200.0%, 261.8%
 * - Waktu Beli Terbaik: Saat harga berada di Golden Pocket (50% - 61.8%) dengan konfirmasi pantulan
 * - Waktu Jual Terbaik: Saat harga mencapai ekstensi target profit 1.272 & 1.618 atau saat stop loss tersentuh.
 * - Multi-Timeframe: 1D (Daily), 4H, 2H, 1H.
 */
final class FibonacciService
{
    public const TIMEFRAME_1D = 24;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_1H = 1;

    public const SIGNAL_GOLDEN_POCKET = 'GOLDEN_POCKET'; // 50% - 61.8% Buy Zone (Waktu Beli Terbaik)
    public const SIGNAL_PULLBACK_382   = 'PULLBACK_382';   // 38.2% Strong Trend Dip
    public const SIGNAL_DEEP_DISCOUNT  = 'DEEP_DISCOUNT';  // 78.6% Deep Value Retest
    public const SIGNAL_BREAKOUT_HIGH  = 'BREAKOUT_HIGH';  // Menembus Swing High menuju ekstensi
    public const SIGNAL_TARGET_REACHED = 'TARGET_REACHED'; // Mencapai Ekstensi 1.272 / 1.618 (Waktu Jual Terbaik)
    public const SIGNAL_FIB_BREAKDOWN  = 'FIB_BREAKDOWN';  // Breakdown di bawah 78.6%/Swing Low (Exit / Stop Loss)
    public const SIGNAL_NEUTRAL        = 'NEUTRAL';

    public const DEFAULT_LOOKBACK = 60; // Jumlah candle pencarian swing

    /**
     * Daftar timeframe yang didukung.
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
     * Scan seluruh saham aktif untuk setup trading Fibonacci.
     *
     * @param array $options
     * @return array<int, array<string, mixed>>
     */
    public function scanAll(array $options = []): array
    {
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $strategy = (string) ($options['strategy'] ?? 'all');
        $minRr = (float) ($options['minRr'] ?? 0.0);
        $exchange = (string) ($options['exchange'] ?? '');
        $sector = (string) ($options['sector'] ?? '');
        $symbolSearch = strtoupper((string) ($options['symbol'] ?? ''));
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

            // Filter strategi
            if (!$this->matchesStrategy($analysis, $strategy)) {
                continue;
            }

            // Filter Min R:R
            if ($minRr > 0.0 && ($analysis['rr_ratio'] ?? 0.0) < $minRr) {
                continue;
            }

            $results[] = $analysis;
        }

        return $results;
    }

    /**
     * Analisis mendalam Fibonacci untuk satu saham.
     *
     * @param int $stockId
     * @param array $options
     * @return array<string, mixed>|null
     */
    public function analyzeStock(int $stockId, array $options = []): ?array
    {
        $stock = Stock::findOne($stockId);
        if ($stock === null) {
            return null;
        }

        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $lookback = (int) ($options['lookback'] ?? self::DEFAULT_LOOKBACK);

        $candles = $this->loadCandles($stockId, $timeframe, max($lookback, 40));
        $count = count($candles);
        if ($count < 15) {
            return null;
        }

        $dates = array_column($candles, 'date');
        $closes = array_column($candles, 'close');
        $highs = array_column($candles, 'high');
        $lows = array_column($candles, 'low');
        $volumes = array_column($candles, 'volume');

        $currIdx = $count - 1;
        $currClose = (float) $closes[$currIdx];
        $currLow = (float) $lows[$currIdx];
        $currHigh = (float) $highs[$currIdx];
        $currVol = (int) $volumes[$currIdx];

        // Hitung RVOL (Volume 20-candle average)
        $recentVols = array_slice($volumes, -20);
        $avgVol = count($recentVols) > 0 ? (array_sum($recentVols) / count($recentVols)) : 1.0;
        $rvol = $avgVol > 0 ? round($currVol / $avgVol, 2) : 1.0;

        // Deteksi Swing High dan Swing Low
        $swing = $this->findSwingPoints($candles);
        if ($swing === null) {
            return null;
        }

        $swingHigh = $swing['high'];
        $swingLow = $swing['low'];
        $swingHighDate = $swing['high_date'];
        $swingLowDate = $swing['low_date'];
        $isUptrendWave = $swing['is_uptrend'];

        $range = max(0.01, $swingHigh - $swingLow);

        // Kalkulasi Level Fibonacci Retracement
        // Dalam gelombang bullish: 0% = Swing High, 100% = Swing Low
        $fibLevels = [
            'fib_0' => round($swingHigh, 2),                              // 0.0% (Puncak Resisten)
            'fib_236' => round($swingHigh - ($range * 0.236), 2),        // 23.6%
            'fib_382' => round($swingHigh - ($range * 0.382), 2),        // 38.2%
            'fib_500' => round($swingHigh - ($range * 0.500), 2),        // 50.0% (Setengah Retest)
            'fib_618' => round($swingHigh - ($range * 0.618), 2),        // 61.8% (THE GOLDEN POCKET)
            'fib_786' => round($swingHigh - ($range * 0.786), 2),        // 78.6% (Deep Discount)
            'fib_1000' => round($swingLow, 2),                           // 100.0% (Dasar Support)
            // Fibonacci Extension Targets (Take Profit)
            'fib_1272' => round($swingHigh + ($range * 0.272), 2),       // 127.2% Extension (TP2)
            'fib_1618' => round($swingHigh + ($range * 0.618), 2),       // 161.8% Golden Extension (TP3)
            'fib_2000' => round($swingHigh + ($range * 1.000), 2),       // 200.0%
            'fib_2618' => round($swingHigh + ($range * 1.618), 2),       // 261.8% Super Extension
        ];

        // Hitung rasio retracement saat ini
        $currentRetraceRatio = ($swingHigh - $currClose) / $range;
        $currentRetracePct = round($currentRetraceRatio * 100, 1);

        // Evaluasi Setup Fibonacci
        $setup = $this->evaluateFibonacciSetup($currClose, $currLow, $currHigh, $fibLevels, $currentRetraceRatio, $isUptrendWave, $rvol);

        // Kalkulasi Level Trading Presisi (Entry, Stop Loss, TP1, TP2, TP3, R:R)
        $levels = $this->calculateTradeLevels($currClose, $currLow, $fibLevels, $setup['action'], $setup['signal']);

        // Checklist 5 Kriteria Validitas Fibonacci
        $checklist = $this->buildChecklist($currClose, $fibLevels, $currentRetraceRatio, $rvol, $levels['rr_ratio'], $isUptrendWave);

        return [
            'stock_id' => $stock->id,
            'symbol' => $stock->symbol,
            'name' => $stock->name,
            'exchange' => $stock->exchange,
            'sector' => $stock->sector,
            'current_price' => $currClose,
            'date' => $dates[$currIdx],
            'timeframe' => $timeframe,
            'rvol' => $rvol,
            // Data Swing
            'swing_high' => $swingHigh,
            'swing_low' => $swingLow,
            'swing_high_date' => $swingHighDate,
            'swing_low_date' => $swingLowDate,
            'is_uptrend_wave' => $isUptrendWave,
            'range' => round($range, 2),
            'retrace_ratio' => $currentRetraceRatio,
            'retrace_pct' => $currentRetracePct,
            // Sinyal & Rekomendasi
            'signal' => $setup['signal'],
            'action' => $setup['action'],
            'strategy_name' => $setup['strategy_name'],
            'strategy_badge' => $setup['strategy_badge'],
            'timing_recommendation' => $setup['timing_recommendation'],
            'description' => $setup['description'],
            // Level Presisi Beli & Jual
            'entry_price' => $levels['entry_price'],
            'entry_range' => $levels['entry_range'],
            'stop_loss' => $levels['stop_loss'],
            'stop_loss_pct' => $levels['stop_loss_pct'],
            'target_1' => $levels['target_1'], // 0.0% Swing High Retest
            'target_1_pct' => $levels['target_1_pct'],
            'target_2' => $levels['target_2'], // 1.272 Extension
            'target_2_pct' => $levels['target_2_pct'],
            'target_3' => $levels['target_3'], // 1.618 Golden Extension
            'target_3_pct' => $levels['target_3_pct'],
            'rr_ratio' => $levels['rr_ratio'],
            'fib_levels' => $fibLevels,
            'checklist' => $checklist,
            // Candle Data untuk ECharts
            'candles' => $candles,
            'dates' => $dates,
        ];
    }

    /**
     * Cari Swing High dan Swing Low utama pada jendela data candle.
     */
    private function findSwingPoints(array $candles): ?array
    {
        $count = count($candles);
        if ($count < 10) {
            return null;
        }

        // Cari High tertinggi dan Low terendah dalam jendela
        $maxHigh = -INF;
        $maxHighIdx = -1;
        $maxHighDate = '';

        $minLow = INF;
        $minLowIdx = -1;
        $minLowDate = '';

        foreach ($candles as $i => $c) {
            if ($c['high'] > $maxHigh) {
                $maxHigh = (float) $c['high'];
                $maxHighIdx = $i;
                $maxHighDate = $c['date'];
            }
            if ($c['low'] < $minLow) {
                $minLow = (float) $c['low'];
                $minLowIdx = $i;
                $minLowDate = $c['date'];
            }
        }

        if ($maxHigh <= $minLow || $maxHighIdx === $minLowIdx) {
            return null;
        }

        // Jika low terjadi sebelum high -> Gelombang tren naik (Uptrend wave)
        $isUptrend = ($minLowIdx < $maxHighIdx);

        return [
            'high' => round($maxHigh, 2),
            'high_idx' => $maxHighIdx,
            'high_date' => $maxHighDate,
            'low' => round($minLow, 2),
            'low_idx' => $minLowIdx,
            'low_date' => $minLowDate,
            'is_uptrend' => $isUptrend,
        ];
    }

    /**
     * Evaluasi Setup Fibonacci dan penentuan timing terbaik beli/jual.
     */
    private function evaluateFibonacciSetup(
        float $close,
        float $low,
        float $high,
        array $fib,
        float $retraceRatio,
        bool $isUptrend,
        float $rvol
    ): array {
        // 1. Ekstensi Target Tercapai (1.272x atau 1.618x) -> WAKTU JUAL TERBAIK / TAKE PROFIT
        if ($close >= $fib['fib_1618'] * 0.98) {
            return [
                'signal' => self::SIGNAL_TARGET_REACHED,
                'action' => 'TAKE_PROFIT',
                'strategy_name' => '1.618 Golden Extension Target',
                'strategy_badge' => 'warning text-dark',
                'timing_recommendation' => 'WAKTU JUAL TERBAIK (Amankan Keuntungan Penuh)',
                'description' => 'Harga telah mencapai target ekstensi emas 161.8%. Amankan keuntungan modal bertahap karena potensi jenuh beli (climax).',
            ];
        }

        if ($close >= $fib['fib_1272'] * 0.98 && $close < $fib['fib_1618']) {
            return [
                'signal' => self::SIGNAL_TARGET_REACHED,
                'action' => 'TAKE_PROFIT',
                'strategy_name' => '1.272 Extension Target',
                'strategy_badge' => 'warning text-dark',
                'timing_recommendation' => 'WAKTU JUAL BERTAHAP (Realisasi Sebagian)',
                'description' => 'Harga mencapai target ekspansi awal 127.2%. Amankan 50% profit dan pasang trailing stop.',
            ];
        }

        // 2. Breakout di atas Swing High (0.0%) -> Momentum Lanjutan
        if ($close > $fib['fib_0'] && $close < $fib['fib_1272'] * 0.98) {
            return [
                'signal' => self::SIGNAL_BREAKOUT_HIGH,
                'action' => 'BUY',
                'strategy_name' => 'Breakout Swing High (0.0% Level)',
                'strategy_badge' => 'info',
                'timing_recommendation' => 'WAKTU BELI MOMENTUM (Menuju Ekstensi)',
                'description' => 'Harga berhasil menembus puncak resisten Swing High. Potensi akselerasi menuju target ekspansi 1.272 dan 1.618.',
            ];
        }

        // 3. Breakdown di bawah 78.6% atau Swing Low -> WAKTU JUAL / CUT LOSS
        if ($close < $fib['fib_786'] * 0.99) {
            return [
                'signal' => self::SIGNAL_FIB_BREAKDOWN,
                'action' => 'SELL',
                'strategy_name' => 'Fibonacci Support Breakdown',
                'strategy_badge' => 'danger',
                'timing_recommendation' => 'WAKTU JUAL / CUT LOSS (Proteksi Modal)',
                'description' => 'Harga gagal bertahan di atas rasio pertahanan terakhir 78.6%. Struktur gelombang naik tidak valid, segera amankan modal.',
            ];
        }

        // 4. GOLDEN POCKET RETEST (50.0% - 61.8%) -> WAKTU BELI TERBAIK (THE SWEET SPOT!)
        // Area antara 50% dan 61.8% adalah rasio probabilitas tertinggi di dunia trading
        $inGoldenPocket = ($close <= $fib['fib_500'] * 1.015 && $close >= $fib['fib_618'] * 0.985);
        if ($inGoldenPocket) {
            return [
                'signal' => self::SIGNAL_GOLDEN_POCKET,
                'action' => 'BUY',
                'strategy_name' => '⭐ Golden Pocket Rebound (50% - 61.8%)',
                'strategy_badge' => 'success',
                'timing_recommendation' => 'WAKTU BELI TERBAIK (Golden Entry Zone)',
                'description' => 'Harga menguji zona emas Fibonacci 50% - 61.8%. Rasio Risk to Reward maksimal dengan batas stop loss sangat terukur.',
            ];
        }

        // 5. Shallow Pullback (38.2%) -> Trend Kuat
        $near382 = (abs($close - $fib['fib_382']) / $fib['fib_382'] <= 0.02);
        if ($near382 && $close >= $fib['fib_500']) {
            return [
                'signal' => self::SIGNAL_PULLBACK_382,
                'action' => 'BUY',
                'strategy_name' => 'Pullback 38.2% (Strong Momentum Dip)',
                'strategy_badge' => 'primary',
                'timing_recommendation' => 'WAKTU BELI SWING (Tren Kuat)',
                'description' => 'Koreksi dangkal di rasio 38.2%. Menandakan minat beli pasar sangat dominan, cocok untuk swing continuation.',
            ];
        }

        // 6. Deep Discount (78.6%) -> Value Entry
        $near786 = (abs($close - $fib['fib_786']) / $fib['fib_786'] <= 0.02);
        if ($near786 && $close >= $fib['fib_1000']) {
            return [
                'signal' => self::SIGNAL_DEEP_DISCOUNT,
                'action' => 'BUY',
                'strategy_name' => 'Deep Discount (78.6% Retest)',
                'strategy_badge' => 'secondary',
                'timing_recommendation' => 'WAKTU BELI VALUE (Harga Diskon)',
                'description' => 'Harga berada di level diskon dalam 78.6%. Cocok untuk akumulasi bertahap dengan stop loss ketat di bawah Swing Low.',
            ];
        }

        // Netral
        return [
            'signal' => self::SIGNAL_NEUTRAL,
            'action' => 'WAIT',
            'strategy_name' => 'Sedang Menuju Level Fib',
            'strategy_badge' => 'light text-dark',
            'timing_recommendation' => 'TUNGGU KONFIRMASI (Wait & See)',
            'description' => 'Harga berada di antara level-level kunci. Tunggu harga mendekati Golden Pocket atau mengonfirmasi pantulan.',
        ];
    }

    /**
     * Hitung level presisi: Entry Price, Stop Loss, Target TP1, TP2, TP3, dan Rasio R:R.
     */
    private function calculateTradeLevels(float $close, float $low, array $fib, string $action, string $signal): array
    {
        if ($action === 'SELL' || $action === 'TAKE_PROFIT') {
            return [
                'entry_price' => $close,
                'entry_range' => '$' . number_format($close, 2),
                'stop_loss' => round($close * 1.025, 2),
                'stop_loss_pct' => 2.5,
                'target_1' => round($fib['fib_618'], 2),
                'target_1_pct' => round(abs(($fib['fib_618'] - $close) / $close) * 100, 1),
                'target_2' => round($fib['fib_786'], 2),
                'target_2_pct' => round(abs(($fib['fib_786'] - $close) / $close) * 100, 1),
                'target_3' => round($fib['fib_1000'], 2),
                'target_3_pct' => round(abs(($fib['fib_1000'] - $close) / $close) * 100, 1),
                'rr_ratio' => 0.0,
            ];
        }

        $entry = $close;
        $entryLow = round(min($close, $fib['fib_618'] * 0.995), 2);
        $entryHigh = round(max($close, $fib['fib_500'] * 1.01), 2);
        $entryRange = '$' . number_format($entryLow, 2) . ' - $' . number_format($entryHigh, 2);

        // Stop loss: 1.5% di bawah rasio 78.6% atau di bawah Swing Low (100%)
        $slRef = ($signal === self::SIGNAL_DEEP_DISCOUNT) ? $fib['fib_1000'] : $fib['fib_786'];
        $stopLoss = round($slRef * 0.985, 2);
        if ($stopLoss >= $entry) {
            $stopLoss = round($entry * 0.97, 2); // default 3% max risk
        }

        $risk = max(0.01, $entry - $stopLoss);
        $stopLossPct = round(($risk / $entry) * 100, 1);

        // TP1: Level 0.0% (Swing High Retest)
        $target1 = round(max($fib['fib_0'], $entry * 1.04), 2);
        $target1Pct = round((($target1 - $entry) / $entry) * 100, 1);

        // TP2: Level 1.272 Extension
        $target2 = round(max($fib['fib_1272'], $entry * 1.09), 2);
        $target2Pct = round((($target2 - $entry) / $entry) * 100, 1);

        // TP3: Level 1.618 Golden Extension
        $target3 = round(max($fib['fib_1618'], $entry * 1.15), 2);
        $target3Pct = round((($target3 - $entry) / $entry) * 100, 1);

        $rrRatio = round(($target1 - $entry) / $risk, 1);

        return [
            'entry_price' => $entry,
            'entry_range' => $entryRange,
            'stop_loss' => $stopLoss,
            'stop_loss_pct' => $stopLossPct,
            'target_1' => $target1,
            'target_1_pct' => $target1Pct,
            'target_2' => $target2,
            'target_2_pct' => $target2Pct,
            'target_3' => $target3,
            'target_3_pct' => $target3Pct,
            'rr_ratio' => $rrRatio,
        ];
    }

    /**
     * Checklist 5 Validasi Sinyal Fibonacci.
     */
    private function buildChecklist(float $close, array $fib, float $ratio, float $rvol, float $rr, bool $isUptrend): array
    {
        return [
            [
                'title' => 'Struktur Gelombang Bullish',
                'desc' => 'Swing Low terjadi sebelum Swing High (tren gelombang naik).',
                'pass' => $isUptrend,
            ],
            [
                'title' => 'Area Retracement Bernilai Tinggi',
                'desc' => 'Harga terkoreksi sehat di area diskon 38.2% - 61.8% Golden Pocket.',
                'pass' => ($ratio >= 0.35 && $ratio <= 0.68),
            ],
            [
                'title' => 'Di Atas Level Batas Stop Loss',
                'desc' => 'Harga bertahan di atas level pertahanan terakhir (78.6% / 100%).',
                'pass' => ($close >= $fib['fib_786']),
            ],
            [
                'title' => 'Konfirmasi Volume Transaksi',
                'desc' => "Aktivitas transaksi memadai ({$rvol}x volume rata-rata).",
                'pass' => ($rvol >= 1.0),
            ],
            [
                'title' => 'Risk to Reward Menjanjikan (≥ 1.8x)',
                'desc' => "Potensi gain menuju puncak ({$rr}x) jauh lebih tinggi dibanding risiko cut loss.",
                'pass' => ($rr >= 1.8),
            ],
        ];
    }

    /**
     * Filter strategi scanner.
     */
    private function matchesStrategy(array $analysis, string $strategy): bool
    {
        if ($strategy === 'all' || $strategy === '') {
            return true;
        }
        if ($strategy === 'buy_all') {
            return $analysis['action'] === 'BUY';
        }
        if ($strategy === 'golden_pocket') {
            return $analysis['signal'] === self::SIGNAL_GOLDEN_POCKET;
        }
        if ($strategy === 'pullback_382') {
            return $analysis['signal'] === self::SIGNAL_PULLBACK_382;
        }
        if ($strategy === 'breakout_high') {
            return $analysis['signal'] === self::SIGNAL_BREAKOUT_HIGH;
        }
        if ($strategy === 'sell_all') {
            return in_array($analysis['action'], ['SELL', 'TAKE_PROFIT'], true);
        }
        if ($strategy === 'target_reached') {
            return $analysis['signal'] === self::SIGNAL_TARGET_REACHED;
        }
        if ($strategy === 'breakdown') {
            return $analysis['signal'] === self::SIGNAL_FIB_BREAKDOWN;
        }

        return true;
    }

    /**
     * Load candle data dengan dukungan 1D, 4H, 2H, 1H.
     */
    private function loadCandles(int $stockId, int $timeframe, int $lookback): array
    {
        if ($timeframe === self::TIMEFRAME_1D) {
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

        // Intraday logic dengan agregasi
        try {
            $rows = IntradayPrice::find()
                ->where(['stock_id' => $stockId])
                ->orderBy(['datetime' => SORT_DESC])
                ->limit($lookback * 4)
                ->all();

            if (count($rows) >= 20) {
                $base = [];
                foreach (array_reverse($rows) as $p) {
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
                $candles = $this->aggregate($base, $per);
                return array_slice($candles, -$lookback);
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return $this->loadCandles($stockId, self::TIMEFRAME_1D, $lookback);
    }

    /**
     * Agregasi bar 1H -> 2H/4H.
     */
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
            'high' => (float) max(array_column($chunk, 'high')),
            'low' => (float) min(array_column($chunk, 'low')),
            'close' => (float) end($chunk)['close'],
            'volume' => (int) array_sum(array_column($chunk, 'volume')),
            'date' => end($chunk)['date'],
        ];
    }
}
