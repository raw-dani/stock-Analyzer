<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\WeeklyAnalysis;
use app\services\dto\WeeklyIndicators;
use yii\base\Component;

/**
 * Transaction Engine (Modul 3):
 * - Klasifikasi Metode A: close > open -> buy, close < open -> sell, doji -> 50/50
 * - Agregasi mingguan (week_start = senin ISO week)
 * - Indikator: buy_ratio, sell_ratio, buy_sell_ratio, volume_growth, rvol, ma20, ma50
 * Semua operasi idempotent (upsert by unique key).
 */
final class VolumeAnalyzerService extends Component
{
    /**
     * Klasifikasi Metode A untuk satu bar (task 3.1).
     *
     * @return array{buy: float, sell: float}
     */
    public static function classifyBar(float $open, float $close, int $volume): array
    {
        if ($close > $open) {
            return ['buy' => (float) $volume, 'sell' => 0.0];
        }
        if ($close < $open) {
            return ['buy' => 0.0, 'sell' => (float) $volume];
        }
        // doji: 50/50
        return ['buy' => $volume / 2.0, 'sell' => $volume / 2.0];
    }

    /**
     * Senin dari tanggal (ISO week start) — dihitung di PHP agar portabel
     * lintas driver DB (SQLite/MySQL/PostgreSQL).
     */
    public static function mondayOf(string $date): string
    {
        $ts = strtotime($date);
        $w = (int) date('N', $ts); // 1=Senin..7=Minggu
        return date('Y-m-d', $ts - ($w - 1) * 86400);
    }

    /**
     * Jalankan pipeline penuh untuk satu simbol (task 3.7).
     *
     * @return int jumlah minggu yang diproses
     */
    public function analyzeStock(int $stockId): int
    {
        $this->classifyDaily($stockId);
        return $this->aggregateWeekly($stockId);
    }

    /**
     * Klasifikasi semua daily_price yang buy_volume masih null.
     */
    public function classifyDaily(int $stockId): int
    {
        $rows = DailyPrice::find()
            ->where(['stock_id' => $stockId])
            ->andWhere(['buy_volume' => null])
            ->all();

        foreach ($rows as $row) {
            $c = self::classifyBar((float) $row->open, (float) $row->close, (int) $row->volume);
            $row->buy_volume = (int) round($c['buy']);
            $row->sell_volume = (int) $row->volume - $row->buy_volume;
            $row->save(false);
        }

        return count($rows);
    }

    /**
     * Agregasi mingguan + indikator -> upsert weekly_analysis.
     * Grouping per senin dilakukan di PHP (portabel; dataset per simbol kecil).
     *
     * @return int jumlah minggu yang diproses
     */
    public function aggregateWeekly(int $stockId): int
    {
        $rows = DailyPrice::find()
            ->select(['date', 'buy_volume', 'sell_volume', 'volume'])
            ->where(['stock_id' => $stockId])
            ->orderBy('date')
            ->asArray()
            ->all();

        $groups = [];
        foreach ($rows as $r) {
            $groups[self::mondayOf($r['date'])][] = $r;
        }
        ksort($groups);

        $count = 0;
        $prevVolume = null;
        $weeklyVolumes = [];

        foreach ($groups as $weekStart => $days) {
            $buy = array_sum(array_column($days, 'buy_volume'));
            $sell = array_sum(array_column($days, 'sell_volume'));
            $volume = array_sum(array_column($days, 'volume'));
            $weeklyVolumes[] = (int) $volume;

            $closePrice = (float) DailyPrice::find()
                ->select('close')
                ->where(['stock_id' => $stockId])
                ->andWhere(['between', 'date', $weekStart, date('Y-m-d', strtotime($weekStart . ' +6 days'))])
                ->orderBy(['date' => SORT_DESC])
                ->scalar();

            $total = (int) $buy + (int) $sell;
            $buyRatio = $total > 0 ? (int) $buy / $total : 0.5;
            $sellRatio = $total > 0 ? (int) $sell / $total : 0.5;
            $buySellRatio = (int) $sell > 0
                ? (int) $buy / (int) $sell
                : ((int) $buy > 0 ? 999.999 : 0.0);

            // volume_growth vs minggu sebelumnya (task 3.4)
            $volumeGrowth = ($prevVolume !== null && $prevVolume > 0)
                ? ((int) $volume - $prevVolume) / $prevVolume
                : null;

            // RVOL: volume / rata-rata 4 minggu sebelumnya (task 3.5)
            $prev4 = array_slice($weeklyVolumes, -5, 4);
            $rvol = (count($prev4) >= 2 && array_sum($prev4) > 0)
                ? (int) $volume / (array_sum($prev4) / count($prev4))
                : null;

            // MA20 / MA50 dari daily close hingga akhir minggu (task 3.6)
            $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
            $ma20 = $this->movingAverage($stockId, $weekEnd, 20);
            $ma50 = $this->movingAverage($stockId, $weekEnd, 50);

            $this->upsertWeekly(new WeeklyIndicators(
                stockId: $stockId,
                weekStart: $weekStart,
                buyVolume: (int) $buy,
                sellVolume: (int) $sell,
                buyRatio: round($buyRatio, 4),
                sellRatio: round($sellRatio, 4),
                buySellRatio: round($buySellRatio, 3),
                volumeGrowth: $volumeGrowth === null ? null : round($volumeGrowth, 4),
                rvol: $rvol === null ? null : round($rvol, 3),
                ma20: $ma20,
                ma50: $ma50,
                closePrice: $closePrice,
            ));
            $count++;
            $prevVolume = (int) $volume;
        }

        return $count;
    }

    private function movingAverage(int $stockId, string $untilDate, int $period): ?float
    {
        $closes = DailyPrice::find()
            ->select('close')
            ->where(['stock_id' => $stockId])
            ->andWhere(['<=', 'date', $untilDate])
            ->orderBy(['date' => SORT_DESC])
            ->limit($period)
            ->column();

        if (count($closes) < $period) {
            return null; // belum cukup data
        }
        return round(array_sum(array_map('floatval', $closes)) / $period, 4);
    }

    private function upsertWeekly(WeeklyIndicators $ind): void
    {
        $row = WeeklyAnalysis::findOne(['stock_id' => $ind->stockId, 'week_start' => $ind->weekStart]);
        if ($row === null) {
            $row = new WeeklyAnalysis();
            $row->stock_id = $ind->stockId;
            $row->week_start = $ind->weekStart;
            $row->score = 0;
            $row->signal = WeeklyAnalysis::SIGNAL_SELL; // diisi SignalEngineService (Modul 4)
        }
        $row->buy_volume = $ind->buyVolume;
        $row->sell_volume = $ind->sellVolume;
        $row->buy_ratio = $ind->buyRatio;
        $row->sell_ratio = $ind->sellRatio;
        $row->buy_sell_ratio = $ind->buySellRatio;
        $row->volume_growth = $ind->volumeGrowth;
        $row->rvol = $ind->rvol;
        $row->ma20 = $ind->ma20;
        $row->ma50 = $ind->ma50;
        $row->close_price = $ind->closePrice;
        $row->save(false);
    }
}
