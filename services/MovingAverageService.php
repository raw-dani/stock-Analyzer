<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use Yii;

/**
 * Service Analisis & Scanner Moving Average.
 *
 * Mengidentifikasi posisi BELI dan JUAL yang presisi melalui:
 * - Golden Cross & Death Cross (Persilangan MA Crossover)
 * - Pullback ke Dynamic MA Support (Buy on Dip dengan risiko minimal)
 * - Breakout di atas MA dengan konfirmasi volume
 * - Overextended Climax (Area Take Profit / Jual Bertahap)
 * - Kalkulasi titik Entry, Stop Loss, Multi-Target TP (TP1 & TP2), serta Risk/Reward Ratio.
 */
final class MovingAverageService
{
    public const TIMEFRAME_1D = 24;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_1H = 1;

    public const PAIR_20_50 = '20_50';   // Swing Trading (MA20 & MA50)
    public const PAIR_50_200 = '50_200'; // Trend Investing (MA50 & MA200)
    public const PAIR_10_30 = '10_30';   // Momentum Cepat (MA10 & MA30)

    public const SIGNAL_STRONG_BUY = 'STRONG_BUY';
    public const SIGNAL_BUY = 'BUY';
    public const SIGNAL_PULLBACK_BUY = 'PULLBACK_BUY';
    public const SIGNAL_SELL = 'SELL';
    public const SIGNAL_TAKE_PROFIT = 'TAKE_PROFIT';
    public const SIGNAL_NEUTRAL = 'NEUTRAL';

    /**
     * Pilihan pasangan Moving Average yang didukung.
     */
    public static function getMaPairs(): array
    {
        return [
            self::PAIR_20_50 => 'MA20 / MA50 (Swing Trading Ideal)',
            self::PAIR_50_200 => 'MA50 / MA200 (Golden / Death Cross Utama)',
            self::PAIR_10_30 => 'MA10 / MA30 (Momentum Jangka Pendek)',
        ];
    }

    /**
     * Timeframes yang didukung.
     */
    public static function getTimeframes(): array
    {
        return [
            self::TIMEFRAME_1D => 'Daily (1 Hari)',
            self::TIMEFRAME_4H => '4 Jam (4H)',
            self::TIMEFRAME_2H => '2 Jam (2H)',
            self::TIMEFRAME_1H => '1 Jam (1H)',
        ];
    }

    /**
     * Scan seluruh saham aktif berdasarkan strategi Moving Average.
     *
     * @param array $options Filter options
     * @return array<int, array<string, mixed>>
     */
    public function scanAll(array $options = []): array
    {
        $maPair = $options['maPair'] ?? self::PAIR_20_50;
        $strategy = $options['strategy'] ?? 'all';
        $minRr = (float) ($options['minRr'] ?? 0.0);
        $exchange = $options['exchange'] ?? null;
        $sector = $options['sector'] ?? null;
        $symbolSearch = $options['symbol'] ?? null;
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $lookback = (int) ($options['lookback'] ?? 100);

        [$fastPeriod, $slowPeriod] = $this->parsePeriods($maPair);

        $stockQuery = Stock::find()->where(['active' => true]);
        if (!empty($exchange)) {
            $stockQuery->andWhere(['exchange' => $exchange]);
        }
        if (!empty($sector)) {
            $stockQuery->andWhere(['sector' => $sector]);
        }
        if (!empty($symbolSearch)) {
            $stockQuery->andWhere(['like', 'symbol', $symbolSearch]);
        }

        $stocks = $stockQuery->all();
        $results = [];

        foreach ($stocks as $stock) {
            $analysis = $this->analyzeStock((int) $stock->id, [
                'fastPeriod' => $fastPeriod,
                'slowPeriod' => $slowPeriod,
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
     * Analisis mendalam Moving Average untuk satu saham tertentu.
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

        $fastPeriod = (int) ($options['fastPeriod'] ?? 20);
        $slowPeriod = (int) ($options['slowPeriod'] ?? 50);
        $trendPeriod = 200;
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $lookback = (int) ($options['lookback'] ?? 120);

        $candles = $this->loadCandles($stockId, $timeframe, max($lookback, $slowPeriod + 20));
        $count = count($candles);
        if ($count < $slowPeriod + 2) {
            return null;
        }

        $closes = array_column($candles, 'close');
        $dates = array_column($candles, 'date');
        $volumes = array_column($candles, 'volume');

        $fastMaSeries = $this->sma($closes, $fastPeriod);
        $slowMaSeries = $this->sma($closes, $slowPeriod);
        $trendMaSeries = $this->sma($closes, $trendPeriod);

        $currIdx = $count - 1;
        $prevIdx = $count - 2;

        $currClose = (float) $closes[$currIdx];
        $prevClose = (float) $closes[$prevIdx];
        $currFast = $fastMaSeries[$currIdx];
        $prevFast = $fastMaSeries[$prevIdx];
        $currSlow = $slowMaSeries[$currIdx];
        $prevSlow = $slowMaSeries[$prevIdx];
        $currTrend = $trendMaSeries[$currIdx];

        if ($currFast === null || $currSlow === null || $prevFast === null || $prevSlow === null) {
            return null;
        }

        $currLow = (float) $candles[$currIdx]['low'];
        $currHigh = (float) $candles[$currIdx]['high'];
        $currVol = (int) $volumes[$currIdx];

        // Rata-rata volume 20 candle terakhir
        $recentVols = array_slice($volumes, -20);
        $avgVol = count($recentVols) > 0 ? (array_sum($recentVols) / count($recentVols)) : 1.0;
        $rvol = $avgVol > 0 ? round($currVol / $avgVol, 2) : 1.0;

        // Evaluasi Setup Strategi
        $setup = $this->evaluateSetup(
            $currClose,
            $prevClose,
            $currLow,
            $currHigh,
            $currFast,
            $prevFast,
            $currSlow,
            $prevSlow,
            $currTrend,
            $rvol
        );

        // Kalkulasi Posisi Presisi: Entry, Stop Loss, Target 1, Target 2, R:R
        $levels = $this->calculateTradingLevels($currClose, $currLow, $currHigh, $currFast, $currSlow, $setup['action']);

        // Persentase jarak harga ke MA
        $distFastPct = round((($currClose - $currFast) / $currFast) * 100, 2);
        $distSlowPct = round((($currClose - $currSlow) / $currSlow) * 100, 2);

        // Status Tren Pasar
        $trendStatus = $this->determineTrend($currClose, $currFast, $currSlow, $currTrend);

        // 5-Point Checklist Kualitas Sinyal
        $checklist = $this->buildChecklist($currClose, $currFast, $currSlow, $currTrend, $rvol, $levels['rr_ratio']);

        return [
            'stock_id' => $stock->id,
            'symbol' => $stock->symbol,
            'name' => $stock->name,
            'exchange' => $stock->exchange,
            'sector' => $stock->sector,
            'current_price' => $currClose,
            'date' => $dates[$currIdx],
            'fast_period' => $fastPeriod,
            'slow_period' => $slowPeriod,
            'fast_ma' => round($currFast, 2),
            'slow_ma' => round($currSlow, 2),
            'trend_ma' => $currTrend !== null ? round($currTrend, 2) : null,
            'dist_fast_pct' => $distFastPct,
            'dist_slow_pct' => $distSlowPct,
            'rvol' => $rvol,
            'trend_status' => $trendStatus,
            'signal' => $setup['signal'],
            'action' => $setup['action'],
            'strategy_name' => $setup['strategy_name'],
            'strategy_badge' => $setup['strategy_badge'],
            'description' => $setup['description'],
            // Level Presisi Beli & Jual
            'entry_price' => $levels['entry_price'],
            'entry_range' => $levels['entry_range'],
            'stop_loss' => $levels['stop_loss'],
            'stop_loss_pct' => $levels['stop_loss_pct'],
            'target_1' => $levels['target_1'],
            'target_1_pct' => $levels['target_1_pct'],
            'target_2' => $levels['target_2'],
            'target_2_pct' => $levels['target_2_pct'],
            'rr_ratio' => $levels['rr_ratio'],
            'checklist' => $checklist,
            // Candle data untuk ECharts
            'candles' => $candles,
            'dates' => $dates,
            'fast_ma_series' => $fastMaSeries,
            'slow_ma_series' => $slowMaSeries,
            'trend_ma_series' => $trendMaSeries,
        ];
    }

    /**
     * Evaluasi setup pola persilangan dan reaksi harga terhadap MA.
     */
    private function evaluateSetup(
        float $currClose,
        float $prevClose,
        float $currLow,
        float $currHigh,
        float $currFast,
        float $prevFast,
        float $currSlow,
        float $prevSlow,
        ?float $currTrend,
        float $rvol
    ): array {
        // 1. Golden Cross (Fast MA melintas naik di atas Slow MA)
        $isGoldenCross = ($prevFast <= $prevSlow && $currFast > $currSlow);
        if ($isGoldenCross) {
            $isStrong = ($rvol >= 1.3 && $currClose > $currFast);
            return [
                'signal' => $isStrong ? self::SIGNAL_STRONG_BUY : self::SIGNAL_BUY,
                'action' => 'BUY',
                'strategy_name' => 'Golden Cross (MA Crossover)',
                'strategy_badge' => 'success',
                'description' => "Fast MA menembus ke atas Slow MA. Konfirmasi momentum bullish awal tren.",
            ];
        }

        // 2. Death Cross (Fast MA melintas turun di bawah Slow MA)
        $isDeathCross = ($prevFast >= $prevSlow && $currFast < $currSlow);
        if ($isDeathCross) {
            return [
                'signal' => self::SIGNAL_SELL,
                'action' => 'SELL',
                'strategy_name' => 'Death Cross (Bearish Crossover)',
                'strategy_badge' => 'danger',
                'description' => "Fast MA memotong ke bawah Slow MA. Sinyal pembalikan arah tren melemah / bearish.",
            ];
        }

        // 3. Overextended Climax (Harga sudah naik terlalu jauh di atas MA20 > 10%) -> Zona Take Profit
        $distAboveFast = ($currClose - $currFast) / $currFast;
        if ($distAboveFast >= 0.10 && $currFast > $currSlow) {
            return [
                'signal' => self::SIGNAL_TAKE_PROFIT,
                'action' => 'TAKE_PROFIT',
                'strategy_name' => 'Overextended Climax (Area Take Profit)',
                'strategy_badge' => 'warning',
                'description' => "Harga berada " . round($distAboveFast * 100, 1) . "% di atas MA. Rawan mean-reversion, disarankan ambil profit bertahap.",
            ];
        }

        // 4. MA Breakdown (Harga jebol ke bawah MA Fast/Slow)
        if ($prevClose >= $currFast && $currClose < $currFast * 0.99 && $currClose < $currSlow) {
            return [
                'signal' => self::SIGNAL_SELL,
                'action' => 'SELL',
                'strategy_name' => 'MA Support Breakdown',
                'strategy_badge' => 'danger',
                'description' => "Harga gagal bertahan dan menembus garis support Moving Average ke bawah.",
            ];
        }

        // 5. Pullback Buy (Buy on Dip di Area Dinamis MA Support)
        // Syarat: Tren Naik (Fast > Slow), harga menguji area Fast MA (-0.8% s/d +1.5%) dan memantul
        $isUptrend = ($currFast > $currSlow && ($currTrend === null || $currClose >= $currTrend * 0.98));
        $nearFast = (abs($currClose - $currFast) / $currFast <= 0.02);
        $testedFast = ($currLow <= $currFast * 1.01 && $currClose >= $currFast * 0.995);

        if ($isUptrend && ($nearFast || $testedFast)) {
            return [
                'signal' => self::SIGNAL_PULLBACK_BUY,
                'action' => 'BUY',
                'strategy_name' => 'Pullback Buy (Support Retest)',
                'strategy_badge' => 'primary',
                'description' => "Harga menguji support dinamis MA saat tren naik. Posisi beli ideal dengan stop loss sangat terukur.",
            ];
        }

        // 6. MA Breakout (Harga menembus naik di atas Fast MA dari bawah)
        if ($prevClose < $prevFast && $currClose > $currFast && $rvol >= 1.2) {
            return [
                'signal' => self::SIGNAL_BUY,
                'action' => 'BUY',
                'strategy_name' => 'MA Breakout + Vol Surge',
                'strategy_badge' => 'info',
                'description' => "Harga menembus MA ke atas didukung lonjakan volume ({$rvol}x).",
            ];
        }

        // 7. Trending Healthy Bullish (Ride the Trend)
        if ($currClose > $currFast && $currFast > $currSlow) {
            return [
                'signal' => self::SIGNAL_BUY,
                'action' => 'HOLD',
                'strategy_name' => 'Bullish Trend Continuation',
                'strategy_badge' => 'secondary',
                'description' => "Struktur harga di atas MA. Tren naik sehat, pertahankan posisi dengan trailing stop.",
            ];
        }

        // Netral / Sideways
        return [
            'signal' => self::SIGNAL_NEUTRAL,
            'action' => 'WAIT',
            'strategy_name' => 'Sideways / Wait & See',
            'strategy_badge' => 'light text-dark',
            'description' => "Belum terbentuk setup persilangan atau pantulan yang optimal.",
        ];
    }

    /**
     * Hitung level presisi: Entry Price, Stop Loss, Target 1, Target 2, dan Risk/Reward.
     */
    private function calculateTradingLevels(
        float $close,
        float $low,
        float $high,
        float $fastMa,
        float $slowMa,
        string $action
    ): array {
        if ($action === 'SELL' || $action === 'TAKE_PROFIT') {
            return [
                'entry_price' => $close,
                'entry_range' => '$' . number_format($close, 2),
                'stop_loss' => round($close * 1.02, 2),
                'stop_loss_pct' => 2.0,
                'target_1' => round($fastMa, 2),
                'target_1_pct' => round(abs(($fastMa - $close) / $close) * 100, 1),
                'target_2' => round($slowMa, 2),
                'target_2_pct' => round(abs(($slowMa - $close) / $close) * 100, 1),
                'rr_ratio' => 0.0,
            ];
        }

        // Untuk BUY / PULLBACK_BUY / HOLD:
        $entry = $close;
        $entryLow = round(min($close, $fastMa * 0.995), 2);
        $entryHigh = round(max($close, $fastMa * 1.01), 2);
        $entryRange = '$' . number_format($entryLow, 2) . ' - $' . number_format($entryHigh, 2);

        // Stop loss ketat: 1.5% - 2.5% di bawah low lilin atau di bawah Fast MA
        $slRef = min($low, $fastMa);
        $stopLoss = round($slRef * 0.98, 2); // 2% di bawah level support MA
        if ($stopLoss >= $entry) {
            $stopLoss = round($entry * 0.97, 2); // default 3% risk buffer
        }

        $risk = max(0.01, $entry - $stopLoss);
        $stopLossPct = round(($risk / $entry) * 100, 1);

        // Target 1: Minimal 1.8x Risk
        $target1 = round($entry + ($risk * 1.8), 2);
        $target1Pct = round((($target1 - $entry) / $entry) * 100, 1);

        // Target 2: 3.0x Risk (Runner)
        $target2 = round($entry + ($risk * 3.0), 2);
        $target2Pct = round((($target2 - $entry) / $entry) * 100, 1);

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
            'rr_ratio' => $rrRatio,
        ];
    }

    /**
     * Tentukan status arah tren.
     */
    private function determineTrend(float $close, float $fast, float $slow, ?float $trend): string
    {
        if ($close > $fast && $fast > $slow && ($trend === null || $slow > $trend)) {
            return 'STRONG_UPTREND';
        }
        if ($close > $slow && $fast > $slow) {
            return 'UPTREND';
        }
        if ($close < $fast && $fast < $slow) {
            return 'DOWNTREND';
        }
        return 'SIDEWAYS';
    }

    /**
     * Checklist 5 kriteria keandalan setup trading.
     */
    private function buildChecklist(float $close, float $fast, float $slow, ?float $trend, float $rvol, float $rr): array
    {
        return [
            [
                'title' => 'Struktur Moving Average',
                'desc' => 'Fast MA berada di atas Slow MA (Struktur Bullish).',
                'pass' => $fast > $slow,
            ],
            [
                'title' => 'Posisi Harga Terhadap MA',
                'desc' => 'Harga berada di atas atau tepat menguji garis MA support.',
                'pass' => $close >= $fast * 0.99,
            ],
            [
                'title' => 'Tidak Overextended',
                'desc' => 'Jarak harga ke MA wajar (< 8%), bukan di pucuk climax.',
                'pass' => (($close - $fast) / $fast) < 0.08,
            ],
            [
                'title' => 'Konfirmasi Volume (RVOL ≥ 1.0x)',
                'desc' => "Aktivitas transaksi memadai ({$rvol}x volume rata-rata).",
                'pass' => $rvol >= 1.0,
            ],
            [
                'title' => 'Risk to Reward Memadai (≥ 1.5x)',
                'desc' => "Potensi profit ({$rr}x) lebih besar dibanding risiko cut loss.",
                'pass' => $rr >= 1.5,
            ],
        ];
    }

    /**
     * Filter apakah hasil analisis cocok dengan strategi yang dipilih.
     */
    private function matchesStrategy(array $analysis, string $strategy): bool
    {
        if ($strategy === 'all' || $strategy === '') {
            return true;
        }
        if ($strategy === 'buy_all') {
            return in_array($analysis['action'], ['BUY'], true);
        }
        if ($strategy === 'golden_cross') {
            return str_contains($analysis['strategy_name'], 'Golden Cross');
        }
        if ($strategy === 'pullback') {
            return $analysis['signal'] === self::SIGNAL_PULLBACK_BUY;
        }
        if ($strategy === 'breakout') {
            return str_contains($analysis['strategy_name'], 'Breakout');
        }
        if ($strategy === 'sell_all') {
            return in_array($analysis['action'], ['SELL', 'TAKE_PROFIT'], true);
        }
        if ($strategy === 'death_cross') {
            return str_contains($analysis['strategy_name'], 'Death Cross');
        }
        if ($strategy === 'take_profit') {
            return $analysis['action'] === 'TAKE_PROFIT';
        }

        return true;
    }

    /**
     * Hitung Simple Moving Average (SMA).
     * @return (float|null)[]
     */
    public function sma(array $values, int $period): array
    {
        $result = [];
        $window = [];
        foreach ($values as $v) {
            $window[] = (float) $v;
            if (count($window) > $period) {
                array_shift($window);
            }
            $result[] = count($window) < $period ? null : (array_sum($window) / count($window));
        }
        return $result;
    }

    /**
     * Load candle data dari database.
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

        // Intraday logic dengan fallback
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
     * Agregasi bar 1H -> 2H/4H (O=first, H=max, L=min, C=last, V=sum).
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $base
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

    /** @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $chunk */
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

    private function parsePeriods(string $pair): array
    {
        return match ($pair) {
            self::PAIR_50_200 => [50, 200],
            self::PAIR_10_30 => [10, 30],
            default => [20, 50],
        };
    }
}
