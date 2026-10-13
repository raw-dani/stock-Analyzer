<?php

declare(strict_types=1);

namespace app\services;

use app\models\PortfolioPosition;
use app\models\Stock;
use Yii;

/**
 * Service Analisis Portofolio, Target Keuntungan, dan Strategi Averaging Down Berbasis Multi-Fitur.
 */
final class PortfolioAveragingService
{
    private CandlestickService $candlestickService;
    private DoubleBottomService $doubleBottomService;
    private TripleBottomService $tripleBottomService;
    private MovingAverageService $movingAverageService;
    private FibonacciService $fibonacciService;
    private RsiDoubleBottomService $rsiDoubleBottomService;

    public function __construct(
        ?CandlestickService $candlestickService = null,
        ?DoubleBottomService $doubleBottomService = null,
        ?TripleBottomService $tripleBottomService = null,
        ?MovingAverageService $movingAverageService = null,
        ?FibonacciService $fibonacciService = null,
        ?RsiDoubleBottomService $rsiDoubleBottomService = null
    ) {
        $this->candlestickService = $candlestickService ?? new CandlestickService();
        $this->doubleBottomService = $doubleBottomService ?? new DoubleBottomService();
        $this->tripleBottomService = $tripleBottomService ?? new TripleBottomService();
        $this->movingAverageService = $movingAverageService ?? new MovingAverageService();
        $this->fibonacciService = $fibonacciService ?? new FibonacciService();
        $this->rsiDoubleBottomService = $rsiDoubleBottomService ?? new RsiDoubleBottomService();
    }

    /**
     * Hitung simulasi skenario Averaging Down untuk posisi yang sedang rugi / minus.
     *
     * @param PortfolioPosition $position
     * @param float|null $supportPrice Harga support teknikal alternatif
     * @return array<string, mixed>
     */
    public function calculateDcaScenarios(PortfolioPosition $position, ?float $supportPrice = null): array
    {
        $currentPrice = $position->getCurrentPrice();
        $buyPrice = (float) $position->buy_price;
        $shares = (float) $position->shares;
        $targetPct = (float) $position->target_profit_pct;

        // Kenaikan yang dibutuhkan TANPA averaging untuk kembali ke modal awal (breakeven)
        $oldReboundToBreakeven = $currentPrice > 0 ? round((($buyPrice - $currentPrice) / $currentPrice) * 100, 2) : 0.0;
        $oldTargetSellPrice = $position->getTargetSellPrice();
        $oldReboundToTarget = $currentPrice > 0 ? round((($oldTargetSellPrice - $currentPrice) / $currentPrice) * 100, 2) : 0.0;

        $scenarios = [];

        // Skenario 1: Beli Rasio 1:1 di Harga Saat Ini
        $scenarios['ratio_1'] = $this->buildScenario(
            'Rasio 1:1 (Konservatif)',
            'Beli jumlah lembar yang sama dengan posisi awal di harga saat ini',
            $position,
            $currentPrice,
            $shares,
            'primary'
        );

        // Skenario 2: Beli Rasio 2:1 (Double Down)
        $scenarios['ratio_2'] = $this->buildScenario(
            'Rasio 2:1 (Moderat)',
            'Beli 2x lipat lembar awal untuk memotong separuh kerugian',
            $position,
            $currentPrice,
            round($shares * 2, 4),
            'success'
        );

        // Skenario 3: Beli Rasio 3:1 (Pemulihan Cepat)
        $scenarios['ratio_3'] = $this->buildScenario(
            'Rasio 3:1 (Pemulihan Agresif)',
            'Beli 3x lipat lembar awal agar harga rata-rata turun drastis mendekati harga pasar',
            $position,
            $currentPrice,
            round($shares * 3, 4),
            'info'
        );

        // Skenario 4: Target Impas Cepat di Pantulan +10% (Bila posisi rugi dalam)
        if ($buyPrice > $currentPrice * 1.15) {
            $targetBe = round($currentPrice * 1.10, 2);
            $denom = $targetBe - $currentPrice;
            if ($denom > 0) {
                $neededShares = round($shares * ($buyPrice - $targetBe) / $denom, 4);
                if ($neededShares > 0 && $neededShares <= $shares * 30) {
                    $scenarios['target_rebound_10'] = $this->buildScenario(
                        'Target Impas di Pantulan +10%',
                        'Beli ' . PortfolioPosition::formatShares($neededShares) . ' lbr agar posisi langsung balik modal (impas) hanya dengan kenaikan +10% dari harga pasar saat ini',
                        $position,
                        $currentPrice,
                        $neededShares,
                        'dark'
                    );
                }
            }
        }

        // Skenario 5: Beli di Titik Support Kunci (jika ada support teknikal di bawah harga saat ini)
        if ($supportPrice !== null && $supportPrice > 0 && $supportPrice < $currentPrice) {
            $scenarios['support_buy'] = $this->buildScenario(
                'Beli di Support Presisi ($' . number_format($supportPrice, 2) . ')',
                'Antri beli di zona support teknikal terkuat (Golden Ratio / MA Support) dengan risiko minimal',
                $position,
                $supportPrice,
                $shares,
                'warning'
            );
        }

        return [
            'current_price' => $currentPrice,
            'buy_price' => $buyPrice,
            'shares' => $shares,
            'target_profit_pct' => $targetPct,
            'old_rebound_to_breakeven' => $oldReboundToBreakeven,
            'old_rebound_to_target' => $oldReboundToTarget,
            'scenarios' => $scenarios,
        ];
    }

    /**
     * Hitung simulasi average kustom berdasarkan input pengguna.
     */
    public function calculateCustomAverage(PortfolioPosition $position, float $addPrice, float $addShares): array
    {
        return $this->buildScenario(
            'Kalkulasi Kustom',
            'Simulasi berdasarkan jumlah lembar dan harga yang Anda tentukan',
            $position,
            $addPrice,
            $addShares,
            'dark'
        );
    }

    private function buildScenario(
        string $title,
        string $description,
        PortfolioPosition $position,
        float $addPrice,
        float $addShares,
        string $badgeClass
    ): array {
        $oldPrice = (float) $position->buy_price;
        $oldShares = (float) $position->shares;
        $oldCost = round($oldPrice * $oldShares, 2);
        $targetPct = (float) $position->target_profit_pct;

        $addCost = round($addPrice * $addShares, 2);
        $newTotalShares = round($oldShares + $addShares, 4);
        $newTotalCost = round($oldCost + $addCost, 2);
        $newAvgPrice = $newTotalShares > 0 ? round($newTotalCost / $newTotalShares, 2) : $oldPrice;

        $reductionAmount = round($oldPrice - $newAvgPrice, 2);
        $reductionPct = $oldPrice > 0 ? round(($reductionAmount / $oldPrice) * 100, 2) : 0.0;

        // Kenaikan dari addPrice agar impas (breakeven)
        $reboundToBreakevenPct = $addPrice > 0 ? round((($newAvgPrice - $addPrice) / $addPrice) * 100, 2) : 0.0;

        // Target jual baru untuk profit (misal 3%)
        $newTargetPrice = round($newAvgPrice * (1 + ($targetPct / 100)), 2);
        $reboundToTargetPct = $addPrice > 0 ? round((($newTargetPrice - $addPrice) / $addPrice) * 100, 2) : 0.0;
        $newTargetNetProfit = round(($newTargetPrice - $newAvgPrice) * $newTotalShares, 2);

        // Penilaian tingkat kemudahan pantulan
        $difficulty = match (true) {
            $reboundToTargetPct <= 5.0 => ['class' => 'success', 'text' => 'Pantulan Sangat Ringan (Mudah)'],
            $reboundToTargetPct <= 15.0 => ['class' => 'info text-white', 'text' => 'Pantulan Wajar (Normal)'],
            $reboundToTargetPct <= 30.0 => ['class' => 'warning text-dark', 'text' => 'Pantulan Menengah'],
            default => ['class' => 'secondary', 'text' => 'Butuh Reli Kuat'],
        };

        return [
            'title' => $title,
            'description' => $description,
            'badge_class' => $badgeClass,
            'add_price' => $addPrice,
            'add_shares' => $addShares,
            'add_cost' => $addCost,
            'new_total_shares' => $newTotalShares,
            'new_total_cost' => $newTotalCost,
            'new_avg_price' => $newAvgPrice,
            'reduction_amount' => $reductionAmount,
            'reduction_pct' => $reductionPct,
            'new_breakeven_price' => $newAvgPrice,
            'rebound_to_breakeven_pct' => $reboundToBreakevenPct,
            'new_target_price' => $newTargetPrice,
            'rebound_to_target_pct' => $reboundToTargetPct,
            'new_target_net_profit' => $newTargetNetProfit,
            'difficulty' => $difficulty,
        ];
    }

    /**
     * Agregasi analisis teknikal dari SEMUA fitur aplikasi:
     * Candlestick, Double Bottom, Triple Bottom, RSI, Moving Average, Fibonacci, dan Kesimpulan Confluence.
     *
     * @param Stock $stock
     * @return array<string, mixed>
     */
    public function getMultiFeatureAnalysis(Stock $stock): array
    {
        $stockId = (int) $stock->id;
        $signals = [];
        $supportLevels = [];
        $bullishPoints = 0;
        $totalFactors = 0;

        // 1. Candlestick Analysis (Pola Pembalikan Arah)
        $candleAnalysis = null;
        try {
            $candleAnalysis = $this->candlestickService->analyzeStock((int) $stockId, [
                'timeframe' => CandlestickService::TIMEFRAME_1D,
                'lookback' => 60,
            ]);
            if ($candleAnalysis !== null) {
                $totalFactors += 2;
                $patternName = $candleAnalysis['pattern_name'] ?? 'Netral';
                $patternType = $candleAnalysis['pattern_type'] ?? 'NEUTRAL';
                $isBullish = ($patternType === 'BULLISH' || str_contains($candleAnalysis['signal'] ?? '', 'BUY'));
                if ($isBullish) {
                    $bullishPoints += 2;
                }
                $signals['candlestick'] = [
                    'pattern_name' => $patternName,
                    'pattern_type' => $patternType,
                    'signal' => $candleAnalysis['signal'] ?? 'NEUTRAL',
                    'action' => $candleAnalysis['action'] ?? 'WAIT',
                    'is_bullish' => $isBullish,
                    'volume_confirmation' => $candleAnalysis['volume_confirmation'] ?? 'Normal',
                    'recommendation' => $candleAnalysis['recommendation'] ?? '',
                ];
            }
        } catch (\Throwable $e) {
            Yii::debug('Candlestick eval failed for portfolio: ' . $e->getMessage());
        }

        // 2. Double Bottom & Triple Bottom Analysis
        $doubleBottom = null;
        $tripleBottom = null;
        try {
            $dbRes = $this->doubleBottomService->scanAll(['symbol' => $stock->symbol, 'timeframe' => DoubleBottomService::TIMEFRAME_1D]);
            $doubleBottom = $dbRes[0] ?? null;
            if ($doubleBottom !== null) {
                $totalFactors += 2;
                $bullishPoints += 2;
                if (!empty($doubleBottom['trough1_price'])) {
                    $supportLevels[] = (float) $doubleBottom['trough1_price'];
                }
            }

            $tbRes = $this->tripleBottomService->scanAll(['symbol' => $stock->symbol, 'timeframe' => TripleBottomService::TIMEFRAME_1D]);
            $tripleBottom = $tbRes[0] ?? null;
            if ($tripleBottom !== null) {
                $totalFactors += 2;
                $bullishPoints += 2;
                if (!empty($tripleBottom['trough1_price'])) {
                    $supportLevels[] = (float) $tripleBottom['trough1_price'];
                }
            }

            $signals['bottom_patterns'] = [
                'has_double_bottom' => ($doubleBottom !== null),
                'double_bottom_status' => $doubleBottom['status'] ?? null,
                'has_triple_bottom' => ($tripleBottom !== null),
                'triple_bottom_status' => $tripleBottom['status'] ?? null,
            ];
        } catch (\Throwable $e) {
            Yii::debug('Bottom pattern eval failed: ' . $e->getMessage());
        }

        // 3. RSI Divergence & Oversold Monitor
        $rsiAnalysis = null;
        try {
            $rsiRes = $this->rsiDoubleBottomService->scanAll(['symbol' => $stock->symbol, 'tf' => RsiDoubleBottomService::TF_1D]);
            $rsiAnalysis = $rsiRes[0] ?? null;
            $rsiVal = $rsiAnalysis['rsi_current'] ?? 50.0;
            $totalFactors += 2;
            $isOversold = ($rsiVal <= 35.0);
            $hasDivergence = !empty($rsiAnalysis['divergence_type']);
            if ($isOversold || $hasDivergence) {
                $bullishPoints += 2;
            }
            $signals['rsi'] = [
                'rsi_current' => round((float) $rsiVal, 1),
                'is_oversold' => $isOversold,
                'divergence_type' => $rsiAnalysis['divergence_type'] ?? null,
                'status' => $isOversold ? 'Oversold (Jenuh Jual)' : ($rsiVal >= 70 ? 'Overbought (Jenuh Beli)' : 'Netral'),
            ];
        } catch (\Throwable $e) {
            Yii::debug('RSI eval failed: ' . $e->getMessage());
        }

        // 4. Moving Average Support & Tren
        $maAnalysis = null;
        try {
            $maAnalysis = $this->movingAverageService->analyzeStock($stockId, [
                'timeframe' => MovingAverageService::TIMEFRAME_1D,
                'maPair' => MovingAverageService::PAIR_20_50,
            ]);
            if ($maAnalysis !== null) {
                $totalFactors += 2;
                $maSignal = $maAnalysis['action'] ?? 'HOLD';
                if ($maSignal === 'BUY' || $maAnalysis['signal'] === MovingAverageService::SIGNAL_PULLBACK_BUY) {
                    $bullishPoints += 2;
                }
                if (!empty($maAnalysis['fast_ma'])) {
                    $supportLevels[] = (float) $maAnalysis['fast_ma'];
                }
                if (!empty($maAnalysis['slow_ma'])) {
                    $supportLevels[] = (float) $maAnalysis['slow_ma'];
                }
                $signals['moving_average'] = [
                    'fast_ma' => $maAnalysis['fast_ma'],
                    'slow_ma' => $maAnalysis['slow_ma'],
                    'trend_ma' => $maAnalysis['trend_ma'],
                    'signal' => $maAnalysis['signal'],
                    'action' => $maAnalysis['action'],
                    'trend_status' => $maAnalysis['trend_status'],
                    'strategy_name' => $maAnalysis['strategy_name'],
                ];
            }
        } catch (\Throwable $e) {
            Yii::debug('MA eval failed: ' . $e->getMessage());
        }

        // 5. Fibonacci Retracement Levels
        $fibAnalysis = null;
        try {
            $fibAnalysis = $this->fibonacciService->analyzeStock($stockId, [
                'timeframe' => FibonacciService::TIMEFRAME_1D,
            ]);
            if ($fibAnalysis !== null) {
                $totalFactors += 2;
                if (!empty($fibAnalysis['golden_pocket'])) {
                    $supportLevels[] = (float) $fibAnalysis['golden_pocket'];
                }
                if (!empty($fibAnalysis['fibo_618'])) {
                    $supportLevels[] = (float) $fibAnalysis['fibo_618'];
                }
                if (!empty($fibAnalysis['fibo_500'])) {
                    $supportLevels[] = (float) $fibAnalysis['fibo_500'];
                }
                if ($fibAnalysis['action'] === 'BUY') {
                    $bullishPoints += 2;
                }
                $signals['fibonacci'] = [
                    'swing_low' => $fibAnalysis['swing_low'] ?? null,
                    'swing_high' => $fibAnalysis['swing_high'] ?? null,
                    'fibo_500' => $fibAnalysis['fibo_500'] ?? null,
                    'fibo_618' => $fibAnalysis['fibo_618'] ?? null,
                    'fibo_786' => $fibAnalysis['fibo_786'] ?? null,
                    'golden_pocket' => $fibAnalysis['golden_pocket'] ?? null,
                    'signal' => $fibAnalysis['signal'] ?? 'NEUTRAL',
                    'action' => $fibAnalysis['action'] ?? 'WAIT',
                ];
            }
        } catch (\Throwable $e) {
            Yii::debug('Fibonacci eval failed: ' . $e->getMessage());
        }

        // Hitung Confluence Score (0 - 100%)
        $confluenceScore = $totalFactors > 0 ? (int) round(($bullishPoints / $totalFactors) * 100) : 50;

        // Tentukan Rekomendasi / Kesimpulan Averaging
        if ($confluenceScore >= 70) {
            $verdict = 'SANGAT DISARANKAN AVERAGE DOWN SEKARANG';
            $verdictBadge = 'success';
            $verdictExplanation = 'Terdeteksi konfirmasi pantulan teknikal yang kuat (Kombinasi Pola Candlestick / RSI Oversold / Support Fibonacci). Momentum pembalikan arah sangat potensial.';
        } elseif ($confluenceScore >= 45) {
            $verdict = 'TUNGGU KONFIRMASI PANTULAN (WAIT & SEE)';
            $verdictBadge = 'warning';
            $verdictExplanation = 'Harga sedang mendekati area support teknikal, namun sinyal candlestick / volume beli belum terkonfirmasi sempurna. Disarankan menunggu 1 candle hijau penutupan sebelum eksekusi average down.';
        } else {
            $verdict = 'HINDARI AVERAGE DOWN / WASPADA PENURUNAN LEBIH DALAM';
            $verdictBadge = 'danger';
            $verdictExplanation = 'Harga masih berada dalam tekanan jual kuat tanpa tanda-tanda support terbentuk. Melakukan averaging saat ini berisiko tinggi ("menangkap pisau jatuh"). Pasang batas Stop Loss ketat.';
        }

        // Ambil harga support terdekat yang valid di bawah harga pasar saat ini
        $currentPrice = $stock->getPrice() ?? 0.0;
        $validSupports = array_filter($supportLevels, fn($p) => $p > 0 && $p <= $currentPrice * 1.01);
        sort($validSupports);
        $bestSupportPrice = end($validSupports) ?: null;

        return [
            'stock' => $stock,
            'confluence_score' => $confluenceScore,
            'verdict' => $verdict,
            'verdict_badge' => $verdictBadge,
            'verdict_class' => $verdictBadge,
            'verdict_explanation' => $verdictExplanation,
            'best_support_price' => $bestSupportPrice ? round((float) $bestSupportPrice, 2) : null,
            'signals' => $signals,
            'features' => $signals,
        ];
    }
}
