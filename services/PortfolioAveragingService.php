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
    /**
     * Hitung simulasi skenario Averaging Down untuk posisi yang sedang rugi / minus.
     *
     * @param PortfolioPosition $position
     * @param float|null $supportPrice Harga support teknikal alternatif
     * @param array<string, mixed> $multiAnalysis Data analisis confluence & teknikal
     * @return array<string, mixed>
     */
    public function calculateDcaScenarios(PortfolioPosition $position, ?float $supportPrice = null, array $multiAnalysis = []): array
    {
        $currentPrice = $position->getCurrentPrice();
        $buyPrice = (float) $position->buy_price;
        $shares = (float) $position->shares;
        $targetPct = (float) $position->target_profit_pct;

        // Kenaikan yang dibutuhkan TANPA averaging untuk kembali ke modal awal (breakeven)
        $oldReboundToBreakeven = $currentPrice > 0 ? round((($buyPrice - $currentPrice) / $currentPrice) * 100, 2) : 0.0;
        $oldTargetSellPrice = $position->getTargetSellPrice();
        $oldReboundToTarget = $currentPrice > 0 ? round((($oldTargetSellPrice - $currentPrice) / $currentPrice) * 100, 2) : 0.0;

        // Tentukan Target Exit Cuan Cepat (Quick Exit): minimal +2% s/d +3% atau target user
        $quickExitPct = min(3.0, max(1.5, $targetPct));

        // Resistance terdekat di atas harga pasar saat ini
        $nearestResistance = $multiAnalysis['nearest_resistance'] ?? null;
        if ($nearestResistance !== null && $nearestResistance <= $currentPrice) {
            $nearestResistance = null;
        }

        // Harga Beli Rekomendasi Pintar (Smart Entry)
        $smartEntryPrice = ($supportPrice !== null && $supportPrice > 0 && $supportPrice < $currentPrice)
            ? $supportPrice
            : $currentPrice;

        // -------------------------------------------------------------
        // FITUR PINTAR: SMART 2-STEP RECOVERY PLAN (Cukup 1-2x Averaging)
        // -------------------------------------------------------------
        // Alokasi Piramida:
        // - Step 1: 1.0x shares (50% alokasi) di harga rebound terdekat / support 1
        // - Step 2: 1.5x shares (75% alokasi) jika koreksi menyentuh support kuat
        $smartPlan = $this->buildSmartRecoveryPlan($position, $smartEntryPrice, $quickExitPct, $nearestResistance, $multiAnalysis);

        $scenarios = [];

        // Skenario Unggulan 1: Smart 1-Step Quick Exit (Sekali Averaging Cuan Keluar)
        if (!empty($smartPlan['step1_scenario'])) {
            $scenarios['smart_step_1'] = $smartPlan['step1_scenario'];
        }

        // Skenario Unggulan 2: Smart 2-Step Complete Recovery (Jika Koreksi Lanjutan ke Support Kuat)
        if (!empty($smartPlan['step2_scenario'])) {
            $scenarios['smart_step_2'] = $smartPlan['step2_scenario'];
        }

        // Skenario Standar: Rasio 1:1 di Harga Saat Ini
        $scenarios['ratio_1'] = $this->buildScenario(
            'Rasio 1:1 (Konservatif)',
            'Beli porsi yang sama (1x modal awal) di harga pasar saat ini',
            $position,
            $currentPrice,
            $shares,
            'primary'
        );

        // Skenario Standar: Rasio 2:1 (Double Down)
        $scenarios['ratio_2'] = $this->buildScenario(
            'Rasio 2:1 (Moderat)',
            'Beli 2x lipat lembar awal untuk memotong kerugian lebih dari separuh',
            $position,
            $currentPrice,
            round($shares * 2, 4),
            'info'
        );

        // Skenario Khusus: Beli di Titik Support Kunci Presisi (jika support di bawah harga running)
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
            'quick_exit_pct' => $quickExitPct,
            'old_rebound_to_breakeven' => $oldReboundToBreakeven,
            'old_rebound_to_target' => $oldReboundToTarget,
            'smart_plan' => $smartPlan,
            'scenarios' => $scenarios,
        ];
    }

    /**
     * Membangun Smart 2-Step Recovery Plan agar user tidak averaging berkali-kali secara membabi buta.
     */
    private function buildSmartRecoveryPlan(
        PortfolioPosition $position,
        float $entryPrice,
        float $quickExitPct,
        ?float $nearestResistance,
        array $multiAnalysis
    ): array {
        $currentPrice = $position->getCurrentPrice();
        $buyPrice = (float) $position->buy_price;
        $shares = (float) $position->shares;

        // STEP 1: Eksekusi Averaging Pertama (1.0x Shares)
        // Hitung rata-rata baru jika beli 1x shares di $entryPrice
        $cost1 = round($entryPrice * $shares, 2);
        $totalShares1 = round($shares * 2, 4);
        $totalCost1 = round(($buyPrice * $shares) + $cost1, 2);
        $newAvg1 = $totalShares1 > 0 ? round($totalCost1 / $totalShares1, 2) : $buyPrice;

        // Target Exit Cuan:
        // Jika ada resistance teknikal terdekat di atas newAvg1 dan realistis, jadikan target exit resistance.
        // Jika tidak, gunakan $quickExitPct (misal +2% s/d +3%) di atas $newAvg1
        $exitTarget1 = round($newAvg1 * (1 + ($quickExitPct / 100)), 2);
        if ($nearestResistance !== null && $nearestResistance > $newAvg1 && $nearestResistance < $exitTarget1 * 1.05) {
            $exitTarget1 = round($nearestResistance, 2);
        }
        $reboundNeeded1 = $entryPrice > 0 ? round((($exitTarget1 - $entryPrice) / $entryPrice) * 100, 2) : 0.0;
        $bepRebound1 = $entryPrice > 0 ? round((($newAvg1 - $entryPrice) / $entryPrice) * 100, 2) : 0.0;
        $netProfit1 = round(($exitTarget1 * $totalShares1) - $totalCost1, 2);

        $step1Scenario = $this->buildScenario(
            '🌟 Smart Step 1: Rebound Exit Plan',
            'Cukup 1x average down (1x porsi awal). Modal rata-rata turun ke $' . number_format($newAvg1, 2) . '. Begitu saham mantul +' . $reboundNeeded1 . '%, langsung keluar dalam posisi UNTUNG!',
            $position,
            $entryPrice,
            $shares,
            'success'
        );

        // STEP 2: Cadangan Averaging Kedua (1.5x Shares) - JIKA harga turun lagi ke support berikutnya
        // Misal support kedua berada 3% - 5% di bawah support pertama
        $secondSupport = !empty($multiAnalysis['secondary_support_price'])
            ? (float) $multiAnalysis['secondary_support_price']
            : round($entryPrice * 0.95, 2);

        $addShares2 = round($shares * 1.5, 4);
        $cost2 = round($secondSupport * $addShares2, 2);
        // Total dari modal awal + step 1 + step 2
        $totalShares2 = round($totalShares1 + $addShares2, 4);
        $totalCost2 = round($totalCost1 + $cost2, 2);
        $newAvg2 = $totalShares2 > 0 ? round($totalCost2 / $totalShares2, 2) : $buyPrice;

        $exitTarget2 = round($newAvg2 * (1 + ($quickExitPct / 100)), 2);
        $reboundNeeded2 = $secondSupport > 0 ? round((($exitTarget2 - $secondSupport) / $secondSupport) * 100, 2) : 0.0;
        $bepRebound2 = $secondSupport > 0 ? round((($newAvg2 - $secondSupport) / $secondSupport) * 100, 2) : 0.0;
        $netProfit2 = round(($exitTarget2 * $totalShares2) - $totalCost2, 2);

        $step2Scenario = $this->buildScenario(
            '🛡️ Smart Step 2: Final Defensive Exit',
            'Amunisi terakhir (Maksimal 2x DCA). Beli di support kedua $' . number_format($secondSupport, 2) . ' sebanyak 1.5x lembar awal. Hanya butuh pantulan +' . $reboundNeeded2 . '% untuk keluar cuan.',
            $position,
            $secondSupport,
            $addShares2,
            'dark'
        );

        // Disiplin Stop Loss Plan jika breakdown support 2 (misal 2.5% di bawah support 2)
        $invalidationStopLoss = round($secondSupport * 0.97, 2);

        return [
            'recommended_buy_price' => $entryPrice,
            'recommended_buy_shares' => $shares,
            'recommended_sell_price' => $exitTarget1,
            'recommended_sell_profit' => $netProfit1,
            'recommended_rebound_needed' => $reboundNeeded1,
            'bep_rebound_needed' => $bepRebound1,
            'new_avg_price' => $newAvg1,
            'step1_scenario' => $step1Scenario,
            'step2_price' => $secondSupport,
            'step2_shares' => $addShares2,
            'step2_new_avg' => $newAvg2,
            'step2_sell_price' => $exitTarget2,
            'step2_sell_profit' => $netProfit2,
            'step2_rebound_needed' => $reboundNeeded2,
            'step2_scenario' => $step2Scenario,
            'stop_loss_price' => $invalidationStopLoss,
            'stop_loss_pct' => round((($invalidationStopLoss - $currentPrice) / max(0.01, $currentPrice)) * 100, 2),
            'nearest_resistance' => $nearestResistance,
            'verdict' => $multiAnalysis['verdict'] ?? 'TUNGGU KONFIRMASI PANTULAN',
            'verdict_badge' => $multiAnalysis['verdict_badge'] ?? 'warning',
            'confluence_score' => $multiAnalysis['confluence_score'] ?? 50,
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

        // Kumpulkan Resistance Levels untuk target exit pantulan
        $resistanceLevels = [];
        if (!empty($fibAnalysis['fibo_382']) && (float)$fibAnalysis['fibo_382'] > 0) {
            $resistanceLevels[] = (float) $fibAnalysis['fibo_382'];
        }
        if (!empty($fibAnalysis['fibo_236']) && (float)$fibAnalysis['fibo_236'] > 0) {
            $resistanceLevels[] = (float) $fibAnalysis['fibo_236'];
        }
        if (!empty($fibAnalysis['swing_high']) && (float)$fibAnalysis['swing_high'] > 0) {
            $resistanceLevels[] = (float) $fibAnalysis['swing_high'];
        }
        if (!empty($maAnalysis['fast_ma']) && (float)$maAnalysis['fast_ma'] > 0) {
            $resistanceLevels[] = (float) $maAnalysis['fast_ma'];
        }
        if (!empty($maAnalysis['slow_ma']) && (float)$maAnalysis['slow_ma'] > 0) {
            $resistanceLevels[] = (float) $maAnalysis['slow_ma'];
        }

        // Ambil harga support terdekat yang valid di bawah harga pasar saat ini
        $currentPrice = $stock->getPrice() ?? 0.0;
        $validSupports = array_values(array_unique(array_filter($supportLevels, fn($p) => $p > 0 && $p <= $currentPrice * 1.01)));
        sort($validSupports);
        $bestSupportPrice = !empty($validSupports) ? end($validSupports) : null;
        // Secondary support (support lapis kedua untuk Step 2)
        $secondarySupportPrice = count($validSupports) >= 2 ? $validSupports[count($validSupports) - 2] : null;

        // Ambil resistance terdekat di atas harga pasar saat ini
        $validResistances = array_values(array_unique(array_filter($resistanceLevels, fn($p) => $p > $currentPrice * 1.005)));
        sort($validResistances);
        $nearestResistance = !empty($validResistances) ? $validResistances[0] : null;

        return [
            'stock' => $stock,
            'confluence_score' => $confluenceScore,
            'verdict' => $verdict,
            'verdict_badge' => $verdictBadge,
            'verdict_class' => $verdictBadge,
            'verdict_explanation' => $verdictExplanation,
            'best_support_price' => $bestSupportPrice ? round((float) $bestSupportPrice, 2) : null,
            'secondary_support_price' => $secondarySupportPrice ? round((float) $secondarySupportPrice, 2) : null,
            'nearest_resistance' => $nearestResistance ? round((float) $nearestResistance, 2) : null,
            'signals' => $signals,
            'features' => $signals,
        ];
    }
}
