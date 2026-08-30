<?php

declare(strict_types=1);

namespace app\services;

use app\models\Stock;
use app\models\WeeklyAnalysis;
use Yii;

/**
 * Sector Analysis (task 12.1): agregasi buy pressure per sektor
 * berdasarkan weekly_analysis terbaru tiap saham.
 * Hasil di-cache 15 menit (pola Modul 5.4).
 */
final class SectorAnalysisService
{
    private const CACHE_TTL = 900; // 15 menit

    /**
     * Agregasi per sektor, urut avg buy pressure desc.
     * @return array<int, array{sector: string, stocks: int, buyPressure: float, avgRvol: float, avgScore: float, buySignals: int}>
     */
    public function sectors(): array
    {
        return Yii::$app->cache->getOrSet('sector-analysis', fn () => $this->compute(), self::CACHE_TTL);
    }

    /**
     * Drill-down satu sektor: baris weekly terbaru per saham, urut score desc (task 12.3).
     * @return array<int, array{stock: Stock, weekly: WeeklyAnalysis}>
     */
    public function stocksBySector(string $sector): array
    {
        $rows = [];
        $stocks = Stock::find()
            ->where(['active' => true, 'sector' => $sector])
            ->orderBy(['symbol' => SORT_ASC])
            ->all();

        foreach ($stocks as $stock) {
            $weekly = WeeklyAnalysis::find()
                ->where(['stock_id' => $stock->id])
                ->orderBy(['week_start' => SORT_DESC])
                ->one();
            if ($weekly !== null) {
                $rows[] = ['stock' => $stock, 'weekly' => $weekly];
            }
        }

        usort($rows, fn ($a, $b) => $b['weekly']->score <=> $a['weekly']->score);

        return $rows;
    }

    /** @return string[] daftar sektor unik (non-null) */
    public function sectorList(): array
    {
        $list = Stock::find()
            ->select('sector')
            ->distinct()
            ->where(['active' => true])
            ->andWhere(['not', ['sector' => null]])
            ->orderBy(['sector' => SORT_ASC])
            ->column();

        return array_map('strval', $list);
    }

    private function compute(): array
    {
        $sectors = [];

        foreach ($this->sectorList() as $sector) {
            $stocks = Stock::find()
            ->select('id')
            ->where(['active' => true, 'sector' => $sector])
            ->column();
            $pressures = [];
            $rvols = [];
            $scores = [];
            $buySignals = 0;

            foreach ($stocks as $stockId) {
                $weekly = WeeklyAnalysis::find()
                    ->where(['stock_id' => (int) $stockId])
                    ->orderBy(['week_start' => SORT_DESC])
                    ->one();
                if ($weekly === null) {
                    continue;
                }

                $total = (int) $weekly->buy_volume + (int) $weekly->sell_volume;
                if ($total > 0) {
                    $pressures[] = (int) $weekly->buy_volume / $total;
                }
                if ($weekly->rvol !== null) {
                    $rvols[] = (float) $weekly->rvol;
                }
                $scores[] = (int) $weekly->score;
                if (in_array($weekly->signal, [WeeklyAnalysis::SIGNAL_STRONG_BUY, WeeklyAnalysis::SIGNAL_BUY], true)) {
                    $buySignals++;
                }
            }

            $sectors[] = [
                'sector' => (string) $sector,
                'stocks' => count($scores),
                'buyPressure' => $pressures !== [] ? round(array_sum($pressures) / count($pressures), 4) : null,
                'avgRvol' => $rvols !== [] ? round(array_sum($rvols) / count($rvols), 2) : null,
                'avgScore' => $scores !== [] ? round(array_sum($scores) / count($scores), 1) : null,
                'buySignals' => $buySignals,
            ];
        }

        usort($sectors, fn ($a, $b) => ($b['buyPressure'] ?? -1) <=> ($a['buyPressure'] ?? -1));

        return $sectors;
    }
}
