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
    public const SIGNAL_SHAKEOUT_BUY = 'SHAKEOUT_BUY';
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

        $maType = strtolower((string) ($options['maType'] ?? 'sma'));
        if (!in_array($maType, ['sma', 'ema'], true)) {
            $maType = 'sma';
        }

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
                'maType' => $maType,
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

        if (!empty($options['maPair'])) {
            [$fastPeriod, $slowPeriod] = $this->parsePeriods($options['maPair']);
        } else {
            $fastPeriod = (int) ($options['fastPeriod'] ?? 20);
            $slowPeriod = (int) ($options['slowPeriod'] ?? 50);
        }
        $trendPeriod = 200;
        $timeframe = (int) ($options['timeframe'] ?? self::TIMEFRAME_1D);
        $lookback = (int) ($options['lookback'] ?? 120);
        $maType = strtolower((string) ($options['maType'] ?? 'sma'));
        if (!in_array($maType, ['sma', 'ema'], true)) {
            $maType = 'sma';
        }

        $minRequired = $slowPeriod + 2;
        $neededLookback = ($timeframe === self::TIMEFRAME_1D) ? max($lookback, $trendPeriod + 20) : max($lookback, $slowPeriod + 20);
        [$candles, $isFallback] = $this->loadCandles($stockId, $timeframe, $neededLookback, $minRequired);
        $count = count($candles);
        if ($count < $slowPeriod + 2) {
            return null;
        }

        $closes = array_column($candles, 'close');
        $dates = array_column($candles, 'date');
        $volumes = array_column($candles, 'volume');

        if ($maType === 'ema') {
            $fastMaSeries = $this->ema($closes, $fastPeriod);
            $slowMaSeries = $this->ema($closes, $slowPeriod);
            $trendMaSeries = $this->ema($closes, $trendPeriod);
        } else {
            $fastMaSeries = $this->sma($closes, $fastPeriod);
            $slowMaSeries = $this->sma($closes, $slowPeriod);
            $trendMaSeries = $this->sma($closes, $trendPeriod);
        }

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

        // Hitung ATR (14-periode) untuk level risiko dinamis
        $atr = $this->calculateAtr($candles, 14);

        // Tambahan Strategi 1: RSI (14-periode) untuk konfirmasi momentum dan filter false breakout
        $rsiSeries = RsiService::calculate($closes, 14);
        $currRsi = $rsiSeries[$currIdx] !== null ? round((float) $rsiSeries[$currIdx], 1) : 50.0;
        $prevRsi = $rsiSeries[$prevIdx] !== null ? round((float) $rsiSeries[$prevIdx], 1) : 50.0;

        // Tambahan Strategi 2: Candlestick Reversal Pattern Confirmation
        $candlePattern = null;
        $candleSignal = 'NONE';
        $cOpen = (float) $candles[$currIdx]['open'];
        $cClose = (float) $candles[$currIdx]['close'];
        $cHigh = (float) $candles[$currIdx]['high'];
        $cLow = (float) $candles[$currIdx]['low'];
        $cRange = max(0.001, $cHigh - $cLow);
        $cBody = abs($cClose - $cOpen);
        $lowerWick = min($cOpen, $cClose) - $cLow;
        $upperWick = $cHigh - max($cOpen, $cClose);

        $prevOpen = (float) ($candles[$prevIdx]['open'] ?? $cOpen);
        $prevBarClose = (float) ($candles[$prevIdx]['close'] ?? $cClose);

        if ($lowerWick >= ($cBody * 1.8) && $upperWick <= ($cBody * 0.7) && $cBody >= ($cRange * 0.15)) {
            $candlePattern = 'Hammer / Pinbar';
            $candleSignal = 'BULLISH';
        } elseif ($cClose > $cOpen && $prevBarClose < $prevOpen && $cOpen <= $prevBarClose && $cClose >= $prevOpen) {
            $candlePattern = 'Bullish Engulfing';
            $candleSignal = 'BULLISH';
        } elseif ($upperWick >= ($cBody * 1.8) && $lowerWick <= ($cBody * 0.7) && $cBody >= ($cRange * 0.15)) {
            $candlePattern = 'Shooting Star';
            $candleSignal = 'BEARISH';
        } elseif ($cClose < $cOpen && $prevBarClose > $prevOpen && $cOpen >= $prevBarClose && $cClose <= $prevOpen) {
            $candlePattern = 'Bearish Engulfing';
            $candleSignal = 'BEARISH';
        }

        // Evaluasi Setup Strategi dengan konfirmasi Slope, ATR, RSI & Candlestick
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
            $rvol,
            $atr,
            $currRsi,
            $candlePattern,
            $candleSignal
        );

        // Kalkulasi Posisi Presisi: Entry, Stop Loss berbasis ATR, Target TP1, TP2, TP3, R:R
        $levels = $this->calculateTradingLevels($currClose, $currLow, $currHigh, $currFast, $currSlow, $setup['action'], $atr);

        // Persentase jarak harga ke MA
        $distFastPct = round((($currClose - $currFast) / $currFast) * 100, 2);
        $distSlowPct = round((($currClose - $currSlow) / $currSlow) * 100, 2);

        // Status Tren Pasar
        $trendStatus = $this->determineTrend($currClose, $currFast, $currSlow, $currTrend);

        // 6-Point Checklist Kualitas Sinyal Multi-Strategi
        $checklist = $this->buildChecklist($currClose, $currFast, $currSlow, $currTrend, $rvol, $levels['rr_ratio'], $currRsi, $candleSignal);

        return [
            'stock_id' => $stock->id,
            'symbol' => $stock->symbol,
            'name' => $stock->name,
            'exchange' => $stock->exchange,
            'sector' => $stock->sector,
            'current_price' => $currClose,
            'date' => $dates[$currIdx],
            'ma_type' => strtoupper($maType),
            'atr' => round($atr, 2),
            'rsi' => $currRsi,
            'rsi_prev' => $prevRsi,
            'candle_pattern' => $candlePattern,
            'candle_signal' => $candleSignal,
            'fast_period' => $fastPeriod,
            'slow_period' => $slowPeriod,
            'fast_ma' => round($currFast, 2),
            'slow_ma' => round($currSlow, 2),
            'trend_ma' => $currTrend !== null ? round($currTrend, 2) : null,
            'dist_fast_pct' => $distFastPct,
            'dist_slow_pct' => $distSlowPct,
            'rvol' => $rvol,
            'trend_status' => $trendStatus,
            'is_shakeout_low' => $setup['is_shakeout_low'] ?? false,
            'shakeout_undercut_pct' => $setup['shakeout_undercut_pct'] ?? 0.0,
            'signal' => $setup['signal'],
            'action' => $setup['action'],
            'strategy_name' => $setup['strategy_name'],
            'strategy_badge' => $setup['strategy_badge'],
            'description' => $setup['description'],
            // Level Presisi Beli & Jual (TP1, TP2, TP3)
            'entry_price' => $levels['entry_price'],
            'entry_range' => $levels['entry_range'],
            'stop_loss' => $levels['stop_loss'],
            'stop_loss_pct' => $levels['stop_loss_pct'],
            'target_1' => $levels['target_1'],
            'target_1_pct' => $levels['target_1_pct'],
            'target_2' => $levels['target_2'],
            'target_2_pct' => $levels['target_2_pct'],
            'target_3' => $levels['target_3'],
            'target_3_pct' => $levels['target_3_pct'],
            'rr_ratio' => $levels['rr_ratio'],
            'checklist' => $checklist,
            'timeframe' => $timeframe,
            'timeframe_label' => self::getTimeframes()[$timeframe] ?? 'Daily',
            'is_fallback' => $isFallback,
            // Candle data untuk ECharts
            'candles' => $candles,
            'dates' => $dates,
            'fast_ma_series' => $fastMaSeries,
            'slow_ma_series' => $slowMaSeries,
            'trend_ma_series' => $trendMaSeries,
        ];
    }

    /**
     * Evaluasi setup pola persilangan dan reaksi harga terhadap MA didukung RSI & Candlestick.
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
        float $rvol,
        float $atr = 0.0,
        float $currRsi = 50.0,
        ?string $candlePattern = null,
        string $candleSignal = 'NONE'
    ): array {
        // MA Slope check: hindari false Golden Cross saat Slow MA sedang mengarah turun tajam (downward slope)
        $isSlowSlopeUp = ($currSlow >= $prevSlow * 0.9995);

        // 1. Golden Cross (Fast MA melintas naik di atas Slow MA)
        $isGoldenCross = ($prevFast <= $prevSlow && $currFast > $currSlow);
        if ($isGoldenCross) {
            $isStrong = ($rvol >= 1.3 && $currClose > $currFast && $isSlowSlopeUp && $currRsi >= 45.0 && $currRsi <= 68.0);
            $hasSlopeConfirmation = $isSlowSlopeUp;
            return [
                'signal' => $isStrong ? self::SIGNAL_STRONG_BUY : self::SIGNAL_BUY,
                'action' => 'BUY',
                'strategy_name' => $hasSlopeConfirmation ? 'Golden Cross (Konfirmasi Slope Positif)' : 'Golden Cross (Awal Crossover)',
                'strategy_badge' => 'success',
                'description' => $hasSlopeConfirmation
                    ? "Fast MA menembus ke atas Slow MA dengan kemiringan (slope) MA yang mulai mendatar/menanjak dan RSI sehat ({$currRsi}). Konfirmasi awal tren bullish valid."
                    : "Fast MA memotong ke atas Slow MA. Perhatikan konfirmasi lanjutan karena kemiringan Slow MA masih cenderung melandai.",
            ];
        }

        // 2. Candlestick Reversal Rebound di Support MA (Multi-Strategy Confirmation)
        $nearFast = (abs($currClose - $currFast) / $currFast <= 0.025);
        $testedFast = ($currLow <= $currFast * 1.015 && $currClose >= $currFast * 0.99);
        if ($candleSignal === 'BULLISH' && ($nearFast || $testedFast) && $currFast >= $currSlow * 0.98) {
            return [
                'signal' => self::SIGNAL_STRONG_BUY,
                'action' => 'BUY',
                'strategy_name' => 'MA Bounce + ' . $candlePattern,
                'strategy_badge' => 'success',
                'description' => "Pantulan kuat terkonfirmasi pola {$candlePattern} tepat di area support dinamis MA dengan momentum RSI ({$currRsi}).",
            ];
        }

        // 3. Death Cross (Fast MA melintas turun di bawah Slow MA)
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

        // 4. Overextended Climax (Harga sudah naik terlalu jauh di atas Fast MA > 10% atau > 2.5x ATR atau RSI > 72) -> Zona Take Profit
        $distAboveFast = ($currClose - $currFast) / $currFast;
        $isAtrClimax = ($atr > 0 && ($currClose - $currFast) > ($atr * 2.5));
        $isRsiOverbought = ($currRsi >= 72.0);
        if (($distAboveFast >= 0.10 || $isAtrClimax || $isRsiOverbought) && $currFast > $currSlow) {
            return [
                'signal' => self::SIGNAL_TAKE_PROFIT,
                'action' => 'TAKE_PROFIT',
                'strategy_name' => 'Overextended Climax (Area Take Profit)',
                'strategy_badge' => 'warning',
                'description' => "Harga berada " . round($distAboveFast * 100, 1) . "% di atas MA dan RSI jenuh beli ({$currRsi}). Rawan koreksi tajam, amankan profit bertahap (TP1/TP2/TP3).",
            ];
        }

        // 5. Candlestick Bearish Reversal di Puncak Tren (Shooting Star / Bearish Engulfing)
        if ($candleSignal === 'BEARISH' && ($currRsi >= 65.0 || $distAboveFast >= 0.06)) {
            return [
                'signal' => self::SIGNAL_SELL,
                'action' => 'SELL',
                'strategy_name' => 'Reversal Exit (' . $candlePattern . ')',
                'strategy_badge' => 'danger',
                'description' => "Pola pembalikan {$candlePattern} muncul di area resistance atas didukung RSI ({$currRsi}). Disarankan ambil untung atau kurangi posisi.",
            ];
        }

        // 6. MA Breakdown (Harga jebol ke bawah MA Fast/Slow)
        if ($prevClose >= $currFast && $currClose < $currFast * 0.99 && $currClose < $currSlow) {
            return [
                'signal' => self::SIGNAL_SELL,
                'action' => 'SELL',
                'strategy_name' => 'MA Support Breakdown',
                'strategy_badge' => 'danger',
                'description' => "Harga gagal bertahan dan menembus garis support Moving Average ke bawah.",
            ];
        }

        // 6b. Shakeout Low (Liquidity Sweep di Support Dinamis MA)
        // Karakteristik: Low sempat menembus / undercut MA support (0.2% - 4.0% di bawah MA),
        // tetapi harga langsung ditarik rebound cepat dan ditutup kembali di atas MA support (Bear Trap).
        $maRef = ($currFast > $currSlow) ? $currFast : $currSlow;
        $isShakeoutLow = false;
        $undercutPct = 0.0;
        if ($currLow < $maRef && $currClose >= $maRef) {
            $undercutPct = round((($maRef - $currLow) / $maRef) * 100, 2);
            if ($undercutPct >= 0.2 && $undercutPct <= 4.5 && $currRsi >= 35.0 && $currRsi <= 68.0) {
                $isShakeoutLow = true;
            }
        }

        if ($isShakeoutLow) {
            $hasHighVol = ($rvol >= 1.2);
            return [
                'signal' => self::SIGNAL_SHAKEOUT_BUY,
                'action' => 'BUY',
                'is_shakeout_low' => true,
                'shakeout_undercut_pct' => $undercutPct,
                'strategy_name' => '⚡ Shakeout Low (MA Bear Trap)',
                'strategy_badge' => 'warning text-dark',
                'description' => "Terjadi liquidity sweep / shakeout low: harga sempat menembus support MA sebesar {$undercutPct}% namun langsung ditarik rebound dan ditutup di atas MA (RVOL {$rvol}x). Indikasi kuat bear trap & akumulasi bandar.",
            ];
        }

        // 7. Pullback Buy (Buy on Dip di Area Dinamis MA Support)
        // Syarat: Tren Naik (Fast > Slow), harga menguji area Fast MA (-0.8% s/d +1.5%) dan memantul
        $isUptrend = ($currFast > $currSlow && ($currTrend === null || $currClose >= $currTrend * 0.98));

        if ($isUptrend && ($nearFast || $testedFast) && $currRsi >= 38.0 && $currRsi <= 60.0) {
            return [
                'signal' => self::SIGNAL_PULLBACK_BUY,
                'action' => 'BUY',
                'strategy_name' => 'Pullback Buy (Support Retest)',
                'strategy_badge' => 'primary',
                'description' => "Harga menguji support dinamis MA saat tren naik dengan RSI sehat ({$currRsi}). Posisi beli ideal dengan stop loss sangat terukur.",
            ];
        }

        // 8. MA Breakout (Harga menembus naik di atas Fast MA dari bawah)
        if ($prevClose < $prevFast && $currClose > $currFast && $rvol >= 1.2) {
            return [
                'signal' => self::SIGNAL_BUY,
                'action' => 'BUY',
                'strategy_name' => 'MA Breakout + Vol Surge',
                'strategy_badge' => 'info',
                'description' => "Harga menembus MA ke atas didukung lonjakan volume ({$rvol}x) dan penguatan RSI ({$currRsi}).",
            ];
        }

        // 9. Trending Healthy Bullish (Ride the Trend)
        if ($currClose > $currFast && $currFast > $currSlow) {
            return [
                'signal' => self::SIGNAL_BUY,
                'action' => 'HOLD',
                'strategy_name' => 'Bullish Trend Continuation',
                'strategy_badge' => 'secondary',
                'description' => "Struktur harga di atas MA. Tren naik sehat, pertahankan posisi dengan trailing stop menuju target TP2 / TP3.",
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
     * Hitung level presisi: Entry Price, Stop Loss (Dinamis ATR), Target TP1, TP2, TP3, dan Risk/Reward.
     */
    private function calculateTradingLevels(
        float $close,
        float $low,
        float $high,
        float $fastMa,
        float $slowMa,
        string $action,
        float $atr = 0.0
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
                'target_3' => round(min($slowMa * 0.97, $close * 0.92), 2),
                'target_3_pct' => round(abs((min($slowMa * 0.97, $close * 0.92) - $close) / $close) * 100, 1),
                'rr_ratio' => 0.0,
            ];
        }

        // Untuk BUY / PULLBACK_BUY / HOLD:
        $entry = $close;
        $entryLow = round(min($close, $fastMa * 0.995), 2);
        $entryHigh = round(max($close, $fastMa * 1.01), 2);
        $entryRange = '$' . number_format($entryLow, 2) . ' - $' . number_format($entryHigh, 2);

        // Stop loss: Gabungan support dinamis MA dan Volatilitas ATR (1.5x ATR buffer)
        $slRef = min($low, $fastMa);
        if ($atr > 0.0) {
            $atrBuffer = $atr * 1.5;
            // Batasi buffer ATR agar tidak terlalu sempit (< 1.5%) atau terlalu lebar (> 7%)
            $minBuffer = $entry * 0.015;
            $maxBuffer = $entry * 0.07;
            $effectiveBuffer = max($minBuffer, min($maxBuffer, $atrBuffer));
            $stopLoss = round($slRef - $effectiveBuffer, 2);
        } else {
            $stopLoss = round($slRef * 0.98, 2); // default 2% di bawah support MA
        }

        if ($stopLoss >= $entry) {
            $stopLoss = round($entry * 0.97, 2); // default 3% risk buffer jika level di atas entry
        }

        $risk = max(0.01, $entry - $stopLoss);
        $stopLossPct = round(($risk / $entry) * 100, 1);

        // Target 1: Minimal 1.8x Risk (Konservatif / Swing High)
        $target1 = round($entry + ($risk * 1.8), 2);
        $target1Pct = round((($target1 - $entry) / $entry) * 100, 1);

        // Target 2: 3.0x Risk (Runner)
        $target2 = round($entry + ($risk * 3.0), 2);
        $target2Pct = round((($target2 - $entry) / $entry) * 100, 1);

        // Target 3: 4.2x Risk (Moonbag / Ekstensi Tren Penuh)
        $target3 = round($entry + ($risk * 4.2), 2);
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
     * Bangun 6-Point Checklist Kualitas Sinyal Multi-Strategi.
     */
    private function buildChecklist(
        float $close,
        float $fast,
        float $slow,
        ?float $trend,
        float $rvol,
        float $rr,
        float $rsi = 50.0,
        string $candleSignal = 'NONE'
    ): array {
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
                'title' => 'Momentum RSI Sehat (40 - 68)',
                'desc' => "RSI berada di zona akumulasi ideal ({$rsi}), bukan overbought > 70.",
                'pass' => ($rsi >= 40.0 && $rsi <= 68.0),
            ],
            [
                'title' => 'Konfirmasi Candlestick / Rebound',
                'desc' => $candleSignal === 'BULLISH' ? 'Terdeteksi pola candlestick bullish reversal.' : 'Tidak terdapat rejection candle bearish ekstrem.',
                'pass' => ($candleSignal !== 'BEARISH'),
            ],
            [
                'title' => 'Konfirmasi Volume (RVOL ≥ 1.0x)',
                'desc' => "Aktivitas transaksi memadai ({$rvol}x volume rata-rata).",
                'pass' => $rvol >= 1.0,
            ],
            [
                'title' => 'Risk to Reward Memadai (≥ 1.5x)',
                'desc' => "Potensi profit TP1 ({$rr}x) lebih besar dibanding risiko cut loss.",
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
            return $analysis['signal'] === self::SIGNAL_PULLBACK_BUY || str_contains($analysis['strategy_name'], 'Pullback');
        }
        if ($strategy === 'shakeout_low') {
            return !empty($analysis['is_shakeout_low']) || $analysis['signal'] === self::SIGNAL_SHAKEOUT_BUY || str_contains($analysis['strategy_name'], 'Shakeout');
        }
        if ($strategy === 'candle_bounce') {
            return !empty($analysis['candle_pattern']) && ($analysis['candle_signal'] ?? '') === 'BULLISH';
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
     * Hitung Exponential Moving Average (EMA).
     * Bobot eksponensial lebih sensitif pada aksi harga terkini.
     * @return (float|null)[]
     */
    public function ema(array $values, int $period): array
    {
        $count = count($values);
        $result = array_fill(0, $count, null);
        if ($count < $period) {
            return $result;
        }

        // Nilai awal EMA adalah SMA dari periode pertama
        $initialSlice = array_slice($values, 0, $period);
        $prevEma = array_sum($initialSlice) / $period;
        $result[$period - 1] = $prevEma;

        $multiplier = 2.0 / ($period + 1);

        for ($i = $period; $i < $count; $i++) {
            $val = (float) $values[$i];
            $prevEma = ($val - $prevEma) * $multiplier + $prevEma;
            $result[$i] = $prevEma;
        }

        return $result;
    }

    /**
     * Hitung Average True Range (ATR) untuk pengukuran volatilitas harga.
     * @param array<int, array{open:float,high:float,low:float,close:float}> $candles
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
     * Load candle data dari database.
     * @return array{0: array, 1: bool} [candles, isFallback]
     */
    private function loadCandles(int $stockId, int $timeframe, int $lookback, int $minRequired = 32): array
    {
        if ($timeframe === self::TIMEFRAME_1D) {
            $prices = DailyPrice::find()
                ->where(['stock_id' => $stockId])
                ->orderBy(['date' => SORT_DESC])
                ->limit(max($lookback, 220))
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
            return [$out, false];
        }

        // Intraday logic dengan fallback ke DailyPrice jika data belum cukup
        try {
            $rows = IntradayPrice::find()
                ->where(['stock_id' => $stockId, 'timeframe' => IntradayPrice::TF_1H])
                ->orderBy(['datetime' => SORT_DESC])
                ->limit(max($lookback * 4, 160))
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
                // Jika bar hasil agregasi cukup untuk kalkulasi MA
                if (count($candles) >= $minRequired) {
                    return [array_slice($candles, -$lookback), false];
                }
            }
        } catch (\Throwable $e) {
            // fallback
        }

        [$daily, ] = $this->loadCandles($stockId, self::TIMEFRAME_1D, $lookback);
        return [$daily, true];
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

    public function parsePeriods(string $pair): array
    {
        return match ($pair) {
            self::PAIR_50_200 => [50, 200],
            self::PAIR_10_30 => [10, 30],
            default => [20, 50],
        };
    }
}
