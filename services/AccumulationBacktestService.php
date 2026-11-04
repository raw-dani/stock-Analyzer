<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\Stock;
use Yii;

/**
 * AccumulationBacktestService
 *
 * Menguji performa parameter deteksi akumulasi historis dengan metode:
 * - Walk-Forward Validation (Pemisahan kronologis In-Sample Train vs Out-of-Sample Test)
 * - Anti-Lookahead Bias (Sinyal hanya dihitung dari data bar t dan sebelumnya; forward outcome dievaluasi t+1 s/d t+n)
 * - Metrik Forward Return: 3 hari (+3d), 5 hari (+5d), 10 hari (+10d)
 * - Maximum Adverse Excursion (MAE) & Maximum Favorable Excursion (MFE)
 * - Realistis: Return bersih setelah potongan fee transaksi (komisi beli + jual + slippage)
 * - Grid Exploration Parameter: RVOL, CMF, Persistence, Net Buy, OBV Slope, Window.
 */
final class AccumulationBacktestService
{
    /**
     * Parameter grid default untuk optimasi.
     */
    public const PARAM_GRID = [
        'rvol' => [1.5, 2.0, 2.5, 3.0],
        'cmf' => [0.05, 0.10, 0.20],
        'persistence' => [3, 5, 10],       // Hari berturut-turut kondisi bertahan
        'netBuyDays' => [3, 5, 10, 20],     // Jumlah bar lookback net buy dominan
        'obvSlope' => [5, 10, 20],          // Periode pengukuran arah slope OBV
        'window' => [5, 10, 20],            // Rentang lookback harga sideways/akumulasi
    ];

    /**
     * Jalankan backtesting validasi akumulasi pada satu atau banyak saham.
     *
     * @param array $options {
     *     symbol?: string|null,
     *     exchange?: string|null,
     *     rvolThreshold?: float,
     *     cmfThreshold?: float,
     *     persistenceDays?: int,
     *     netBuyLookback?: int,
     *     obvSlopePeriod?: int,
     *     accWindow?: int,
     *     feePct?: float,                // mis. 0.3% (0.15% beli + 0.15% jual)
     *     slippagePct?: float,           // mis. 0.1%
     *     splitRatio?: float,            // mis. 0.70 (70% Train In-Sample, 30% Test Out-of-Sample)
     *     maxStocks?: int
     * }
     */
    public function runBacktest(array $options = []): array
    {
        $symbol = !empty($options['symbol']) ? strtoupper(trim((string) $options['symbol'])) : null;
        $exchange = !empty($options['exchange']) ? trim((string) $options['exchange']) : null;

        $rvolThresh = (float) ($options['rvolThreshold'] ?? 2.0);
        $cmfThresh = (float) ($options['cmfThreshold'] ?? 0.10);
        $persistence = (int) ($options['persistenceDays'] ?? 3);
        $netBuyLb = (int) ($options['netBuyLookback'] ?? 5);
        $obvPeriod = (int) ($options['obvSlopePeriod'] ?? 10);
        $accWindow = (int) ($options['accWindow'] ?? 10);

        $feePct = (float) ($options['feePct'] ?? 0.30); // 0.3% per putaran (buy+sell)
        $slippagePct = (float) ($options['slippagePct'] ?? 0.10); // 0.1% slippage
        $totalCostPct = $feePct + $slippagePct;

        $splitRatio = (float) ($options['splitRatio'] ?? 0.70); // 70% train, 30% test
        $maxStocks = (int) ($options['maxStocks'] ?? 30);

        // Ambil saham target
        $stockQuery = Stock::find()->where(['active' => true]);
        if ($symbol !== null) {
            $stockQuery->andWhere(['symbol' => $symbol]);
        }
        if ($exchange !== null) {
            $stockQuery->andWhere(['exchange' => $exchange]);
        }
        $stocks = $stockQuery->limit($maxStocks)->all();

        $allTrainSignals = [];
        $allTestSignals = [];

        foreach ($stocks as $stk) {
            // Ambil semua data bar harian diurutkan kronologis (ASC)
            $bars = DailyPrice::find()
                ->where(['stock_id' => $stk->id])
                ->orderBy(['date' => SORT_ASC])
                ->asArray()
                ->all();

            $totalBars = count($bars);
            // Butuh minimal 35 bar untuk indikator awal + 10 bar forward testing
            if ($totalBars < 45) {
                continue;
            }

            // Hitung titik pemisah kronologis Walk-Forward (Train In-Sample vs Test Out-of-Sample)
            $splitIndex = (int) floor($totalBars * $splitRatio);

            // Pre-calculate series data
            $processed = $this->preprocessBars($bars);

            // Scan sinyal tanpa data masa depan (no lookahead bias)
            // Evaluasi bar dari indeks 25 hingga (totalBars - 11) agar bar t+10 selalu tersedia
            for ($i = 25; $i <= $totalBars - 11; $i++) {
                $isSignal = $this->evaluateSignalAtBar(
                    $processed,
                    $i,
                    $rvolThresh,
                    $cmfThresh,
                    $persistence,
                    $netBuyLb,
                    $obvPeriod,
                    $accWindow
                );

                if ($isSignal) {
                    $outcome = $this->calculateForwardOutcome($processed, $i, $totalCostPct);
                    $outcome['symbol'] = $stk->symbol;
                    $outcome['date'] = $bars[$i]['date'];
                    $outcome['entryPrice'] = (float) $bars[$i]['close'];

                    if ($i < $splitIndex) {
                        $allTrainSignals[] = $outcome;
                    } else {
                        $allTestSignals[] = $outcome;
                    }

                    // Lompat 3 bar untuk menghindari overlapping sinyal identik berturut-turut
                    $i += 2;
                }
            }
        }

        $trainMetrics = $this->aggregateMetrics($allTrainSignals);
        $testMetrics = $this->aggregateMetrics($allTestSignals);
        $combinedMetrics = $this->aggregateMetrics(array_merge($allTrainSignals, $allTestSignals));

        return [
            'params' => [
                'rvolThreshold' => $rvolThresh,
                'cmfThreshold' => $cmfThresh,
                'persistenceDays' => $persistence,
                'netBuyLookback' => $netBuyLb,
                'obvSlopePeriod' => $obvPeriod,
                'accWindow' => $accWindow,
                'feePct' => $feePct,
                'slippagePct' => $slippagePct,
                'totalCostPct' => $totalCostPct,
                'splitRatio' => $splitRatio,
                'symbol' => $symbol,
                'stocksTested' => count($stocks),
            ],
            'train' => $trainMetrics,
            'test' => $testMetrics,
            'combined' => $combinedMetrics,
            'sampleTrades' => array_slice(array_reverse($allTestSignals), 0, 30),
        ];
    }

    /**
     * Jalankan multi-parameter grid search untuk mencari kombinasi threshold terbaik secara obyektif.
     */
    public function runParameterComparison(array $options = []): array
    {
        $symbol = !empty($options['symbol']) ? strtoupper(trim((string) $options['symbol'])) : null;
        $exchange = !empty($options['exchange']) ? trim((string) $options['exchange']) : null;
        $maxStocks = (int) ($options['maxStocks'] ?? 15);

        // Ambil sampel kombinasi utama yang disarankan user
        $testVariations = [
            ['name' => 'Konservatif (Tinggi Filter)', 'rvol' => 2.5, 'cmf' => 0.15, 'pers' => 5, 'netBuy' => 10, 'obv' => 10, 'win' => 15],
            ['name' => 'Standar Akumulasi', 'rvol' => 2.0, 'cmf' => 0.10, 'pers' => 3, 'netBuy' => 5, 'obv' => 10, 'win' => 10],
            ['name' => 'Early Entry (Sensitif)', 'rvol' => 1.5, 'cmf' => 0.05, 'pers' => 3, 'netBuy' => 3, 'obv' => 5, 'win' => 5],
            ['name' => 'Ultra Breakout (Surge)', 'rvol' => 3.0, 'cmf' => 0.20, 'pers' => 5, 'netBuy' => 5, 'obv' => 5, 'win' => 10],
            ['name' => 'Deep Persistent Base', 'rvol' => 1.8, 'cmf' => 0.08, 'pers' => 10, 'netBuy' => 20, 'obv' => 20, 'win' => 20],
        ];

        $comparisonResults = [];

        foreach ($testVariations as $var) {
            $res = $this->runBacktest([
                'symbol' => $symbol,
                'exchange' => $exchange,
                'rvolThreshold' => $var['rvol'],
                'cmfThreshold' => $var['cmf'],
                'persistenceDays' => $var['pers'],
                'netBuyLookback' => $var['netBuy'],
                'obvSlopePeriod' => $var['obv'],
                'accWindow' => $var['win'],
                'maxStocks' => $maxStocks,
            ]);

            $comparisonResults[] = [
                'name' => $var['name'],
                'config' => $var,
                'trainCount' => $res['train']['totalSignals'],
                'testCount' => $res['test']['totalSignals'],
                'winRate5d' => $res['test']['winRate5d'],
                'avgNetReturn5d' => $res['test']['avgNetReturn5d'],
                'winRate10d' => $res['test']['winRate10d'],
                'avgNetReturn10d' => $res['test']['avgNetReturn10d'],
                'avgMae' => $res['test']['avgMae'],
                'avgMfe' => $res['test']['avgMfe'],
                'profitFactor' => $res['test']['profitFactor10d'],
            ];
        }

        return $comparisonResults;
    }

    /**
     * Hitung indikator secara runtun waktu pada setiap bar.
     */
    private function preprocessBars(array $bars): array
    {
        $closes = [];
        $highs = [];
        $lows = [];
        $opens = [];
        $volumes = [];
        $buyVolumes = [];
        $sellVolumes = [];

        foreach ($bars as $b) {
            $c = (float) $b['close'];
            $o = (float) $b['open'];
            $h = (float) $b['high'];
            $l = (float) $b['low'];
            $v = (float) $b['volume'];

            $bv = $b['buy_volume'] !== null ? (float) $b['buy_volume'] : ($c >= $o ? $v : 0.0);
            $sv = $b['sell_volume'] !== null ? (float) $b['sell_volume'] : ($c < $o ? $v : 0.0);

            $closes[] = $c;
            $opens[] = $o;
            $highs[] = $h;
            $lows[] = $l;
            $volumes[] = $v;
            $buyVolumes[] = $bv;
            $sellVolumes[] = $sv;
        }

        // Hitung OBV
        $obv = [0.0];
        $total = count($closes);
        for ($i = 1; $i < $total; $i++) {
            if ($closes[$i] > $closes[$i - 1]) {
                $obv[] = $obv[$i - 1] + $volumes[$i];
            } elseif ($closes[$i] < $closes[$i - 1]) {
                $obv[] = $obv[$i - 1] - $volumes[$i];
            } else {
                $obv[] = $obv[$i - 1];
            }
        }

        return [
            'bars' => $bars,
            'closes' => $closes,
            'opens' => $opens,
            'highs' => $highs,
            'lows' => $lows,
            'volumes' => $volumes,
            'buyVolumes' => $buyVolumes,
            'sellVolumes' => $sellVolumes,
            'obv' => $obv,
        ];
    }

    /**
     * Evaluasi sinyal akumulasi ketat pada bar indeks $idx (HANYA melihat bar 0 s/d $idx).
     */
    private function evaluateSignalAtBar(
        array $p,
        int $idx,
        float $rvolThresh,
        float $cmfThresh,
        int $persistence,
        int $netBuyLb,
        int $obvPeriod,
        int $accWindow
    ): bool {
        // 1. Cek RVOL terhadap rata-rata 20 bar sebelumnya
        $volSlice = array_slice($p['volumes'], max(0, $idx - 20), 20);
        $avgVol20 = !empty($volSlice) ? (array_sum($volSlice) / count($volSlice)) : 1.0;
        $currentVol = $p['volumes'][$idx];
        $currentRvol = $avgVol20 > 0 ? ($currentVol / $avgVol20) : 1.0;

        if ($currentRvol < $rvolThresh) {
            return false;
        }

        // 2. Cek CMF 20 bar (0 s/d $idx)
        $cmf = $this->calculateCmfAtBar($p, $idx, 20);
        if ($cmf < $cmfThresh) {
            return false;
        }

        // 3. Cek Persistence: Net buy berturut-turut selama minimal $persistence hari
        $consecutiveBuyDays = 0;
        for ($k = $idx; $k >= max(0, $idx - $persistence - 2); $k--) {
            if ($p['buyVolumes'][$k] > $p['sellVolumes'][$k]) {
                $consecutiveBuyDays++;
            } else {
                break;
            }
        }
        if ($consecutiveBuyDays < $persistence) {
            return false;
        }

        // 4. Cek Rasio Net Buy dalam rentang $netBuyLb hari terakhir
        $netBuyCount = 0;
        $startLb = max(0, $idx - $netBuyLb + 1);
        for ($k = $startLb; $k <= $idx; $k++) {
            if ($p['buyVolumes'][$k] > $p['sellVolumes'][$k]) {
                $netBuyCount++;
            }
        }
        // Minimal 60% hari dalam lookback merupakan Net Buy
        if ($netBuyCount < (int) ceil($netBuyLb * 0.60)) {
            return false;
        }

        // 5. Cek OBV Slope dalam $obvPeriod hari
        $obvStart = max(0, $idx - $obvPeriod);
        $obvDiff = $p['obv'][$idx] - $p['obv'][$obvStart];
        if ($obvDiff <= 0) {
            return false; // OBV tidak mengonfirmasi kenaikan
        }

        // 6. Cek rentang konsolidasi / Accumulation Window
        $winCloses = array_slice($p['closes'], max(0, $idx - $accWindow), $accWindow);
        if (!empty($winCloses)) {
            $maxWin = max($winCloses);
            $minWin = min($winCloses);
            $rangePct = $minWin > 0 ? (($maxWin - $minWin) / $minWin) * 100 : 0.0;
            // Rentang harga sideways/basis akumulasi tidak boleh terlalu liar (<= 15%)
            if ($rangePct > 15.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hitung CMF hanya sampai bar $idx.
     */
    private function calculateCmfAtBar(array $p, int $idx, int $period = 20): float
    {
        $start = max(0, $idx - $period + 1);
        $sumMfv = 0.0;
        $sumVol = 0.0;

        for ($k = $start; $k <= $idx; $k++) {
            $h = $p['highs'][$k];
            $l = $p['lows'][$k];
            $c = $p['closes'][$k];
            $v = $p['volumes'][$k];

            $range = $h - $l;
            if ($range > 0) {
                $mult = (($c - $l) - ($h - $c)) / $range;
                $sumMfv += ($mult * $v);
            }
            $sumVol += $v;
        }

        return $sumVol > 0 ? ($sumMfv / $sumVol) : 0.0;
    }

    /**
     * Hitung performa masa depan (t+3, t+5, t+10, MAE, MFE) dari bar t+1 ke depan.
     */
    private function calculateForwardOutcome(array $p, int $idx, float $costPct): array
    {
        $entryPrice = $p['closes'][$idx];

        // Forward returns
        $close3 = $p['closes'][$idx + 3] ?? $entryPrice;
        $close5 = $p['closes'][$idx + 5] ?? $entryPrice;
        $close10 = $p['closes'][$idx + 10] ?? $entryPrice;

        $ret3 = (($close3 - $entryPrice) / $entryPrice) * 100;
        $ret5 = (($close5 - $entryPrice) / $entryPrice) * 100;
        $ret10 = (($close10 - $entryPrice) / $entryPrice) * 100;

        // Net returns setelah potongan biaya
        $netRet3 = $ret3 - $costPct;
        $netRet5 = $ret5 - $costPct;
        $netRet10 = $ret10 - $costPct;

        // MAE (Maximum Adverse Excursion - penurunan terendah) & MFE (Maximum Favorable Excursion - kenaikan tertinggi) dalam 10 bar
        $minLow10 = $entryPrice;
        $maxHigh10 = $entryPrice;
        for ($k = $idx + 1; $k <= min(count($p['closes']) - 1, $idx + 10); $k++) {
            if ($p['lows'][$k] < $minLow10) {
                $minLow10 = $p['lows'][$k];
            }
            if ($p['highs'][$k] > $maxHigh10) {
                $maxHigh10 = $p['highs'][$k];
            }
        }

        $maePct = (($minLow10 - $entryPrice) / $entryPrice) * 100; // biasanya negatif
        $mfePct = (($maxHigh10 - $entryPrice) / $entryPrice) * 100; // biasanya positif

        return [
            'ret3d' => round($ret3, 2),
            'netRet3d' => round($netRet3, 2),
            'ret5d' => round($ret5, 2),
            'netRet5d' => round($netRet5, 2),
            'ret10d' => round($ret10, 2),
            'netRet10d' => round($netRet10, 2),
            'mae' => round($maePct, 2),
            'mfe' => round($mfePct, 2),
            'win3d' => $netRet3 > 0,
            'win5d' => $netRet5 > 0,
            'win10d' => $netRet10 > 0,
        ];
    }

    /**
     * Agregasi statistik kuantitatif untuk serangkaian trade/sinyal.
     */
    private function aggregateMetrics(array $signals): array
    {
        $n = count($signals);
        if ($n === 0) {
            return [
                'totalSignals' => 0,
                'winRate3d' => 0.0,
                'winRate5d' => 0.0,
                'winRate10d' => 0.0,
                'avgReturn3d' => 0.0,
                'avgNetReturn3d' => 0.0,
                'avgReturn5d' => 0.0,
                'avgNetReturn5d' => 0.0,
                'avgReturn10d' => 0.0,
                'avgNetReturn10d' => 0.0,
                'avgMae' => 0.0,
                'avgMfe' => 0.0,
                'profitFactor10d' => 0.0,
                'rewardRiskRatio' => 0.0,
            ];
        }

        $wins3 = count(array_filter($signals, fn($s) => $s['win3d']));
        $wins5 = count(array_filter($signals, fn($s) => $s['win5d']));
        $wins10 = count(array_filter($signals, fn($s) => $s['win10d']));

        $sumRet3 = array_sum(array_column($signals, 'ret3d'));
        $sumNetRet3 = array_sum(array_column($signals, 'netRet3d'));
        $sumRet5 = array_sum(array_column($signals, 'ret5d'));
        $sumNetRet5 = array_sum(array_column($signals, 'netRet5d'));
        $sumRet10 = array_sum(array_column($signals, 'ret10d'));
        $sumNetRet10 = array_sum(array_column($signals, 'netRet10d'));

        $sumMae = array_sum(array_column($signals, 'mae'));
        $sumMfe = array_sum(array_column($signals, 'mfe'));

        // Profit Factor 10d
        $grossProfits = 0.0;
        $grossLosses = 0.0;
        foreach ($signals as $s) {
            if ($s['netRet10d'] > 0) {
                $grossProfits += $s['netRet10d'];
            } else {
                $grossLosses += abs($s['netRet10d']);
            }
        }
        $profitFactor = $grossLosses > 0 ? round($grossProfits / $grossLosses, 2) : ($grossProfits > 0 ? 99.0 : 0.0);

        $avgMae = round($sumMae / $n, 2);
        $avgMfe = round($sumMfe / $n, 2);
        $rrRatio = abs($avgMae) > 0 ? round($avgMfe / abs($avgMae), 2) : 0.0;

        return [
            'totalSignals' => $n,
            'winRate3d' => round(($wins3 / $n) * 100, 1),
            'winRate5d' => round(($wins5 / $n) * 100, 1),
            'winRate10d' => round(($wins10 / $n) * 100, 1),
            'avgReturn3d' => round($sumRet3 / $n, 2),
            'avgNetReturn3d' => round($sumNetRet3 / $n, 2),
            'avgReturn5d' => round($sumRet5 / $n, 2),
            'avgNetReturn5d' => round($sumNetRet5 / $n, 2),
            'avgReturn10d' => round($sumRet10 / $n, 2),
            'avgNetReturn10d' => round($sumNetRet10 / $n, 2),
            'avgMae' => $avgMae,
            'avgMfe' => $avgMfe,
            'profitFactor10d' => $profitFactor,
            'rewardRiskRatio' => $rrRatio,
        ];
    }
}

