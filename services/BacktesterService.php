<?php

declare(strict_types=1);

namespace app\services;

use app\models\form\BacktestForm;
use app\models\BacktestRun;
use app\models\BacktestTrade;
use app\models\DailyPrice;
use app\models\WeeklyAnalysis;
use Yii;

/**
 * Backtesting engine (task 11.2–11.4).
 *
 * Strategi: entry di close hari bursa pertama >= week_start pada weekly_analysis
 * yang memenuhi kriteria; exit di close N hari bursa (bar) setelahnya.
 * Metrik (task 11.3): trades, win rate, avg gain/loss, profit factor, max drawdown.
 * Hasil disimpan ke backtest_run + backtest_trade (task 11.4).
 */
final class BacktesterService
{
    /**
     * Jalankan backtest & simpan hasilnya.
     */
    public function run(BacktestForm $form): BacktestRun
    {
        $run = new BacktestRun();
        $run->name = $form->resolvedName();
        $run->params = json_encode([
            'minBuyRatio' => $form->minBuyRatio,
            'minRvol' => $form->minRvol,
            'minScore' => $form->minScore,
            'holdingDays' => $form->holdingDays,
            'startDate' => $form->startDate,
            'endDate' => $form->endDate,
        ]);
        $run->start_date = $form->startDate ?: $this->earliestWeek();
        $run->end_date = $form->endDate ?: date('Y-m-d');
        $run->trades = 0;
        $run->save(false);

        $trades = $this->simulate($form);
        foreach ($trades as $t) {
            $t->run_id = (int) $run->id;
            $t->save(false);
        }

        $this->applyMetrics($run, $trades);

        Yii::info(sprintf('Backtest "%s": %d trades.', $run->name, count($trades)), 'app\services\BacktesterService');

        return $run;
    }

    /**
     * Simulasi tanpa menyimpan (dipakai run() dan grid search).
     * @return BacktestTrade[]
     */
    public function simulate(BacktestForm $form): array
    {
        $query = WeeklyAnalysis::find()
            ->joinWith(['stock'])
            ->andWhere(['{{%stock}}.active' => true])
            ->orderBy(['{{%weekly_analysis}}.week_start' => SORT_ASC]);

        $query->andFilterWhere(['>=', '{{%weekly_analysis}}.buy_ratio', $form->minBuyRatio]);
        $query->andFilterWhere(['>=', '{{%weekly_analysis}}.rvol', $form->minRvol]);
        $query->andFilterWhere(['>=', '{{%weekly_analysis}}.score', $form->minScore]);
        $query->andFilterWhere(['>=', '{{%weekly_analysis}}.week_start', $form->startDate]);
        $query->andFilterWhere(['<=', '{{%weekly_analysis}}.week_start', $form->endDate]);

        $trades = [];
        $cache = [];

        foreach ($query->all() as $weekly) {
            $stockId = (int) $weekly->stock_id;
            if (!isset($cache[$stockId])) {
                $cache[$stockId] = $this->loadBars($stockId);
            }
            $trade = $this->makeTrade($cache[$stockId], $weekly, $form->holdingDays);
            if ($trade !== null) {
                $trades[] = $trade;
            }
        }

        return $trades;
    }

    /**
     * @return array{bars: array<int, array{date: string, close: float}>, index: array<string, int>}
     */
    private function loadBars(int $stockId): array
    {
        $rows = DailyPrice::find()
            ->select(['date', 'close'])
            ->where(['stock_id' => $stockId])
            ->orderBy(['date' => SORT_ASC])
            ->asArray()
            ->all();

        $bars = [];
        $index = [];
        foreach ($rows as $i => $r) {
            $bars[] = ['date' => $r['date'], 'close' => (float) $r['close']];
            $index[$r['date']] = $i;
        }

        return ['bars' => $bars, 'index' => $index];
    }

    private function makeTrade(array $data, WeeklyAnalysis $weekly, int $holdingDays): ?BacktestTrade
    {
        // entry: bar pertama dengan date >= week_start
        $entryIdx = null;
        foreach ($data['bars'] as $i => $bar) {
            if ($bar['date'] >= $weekly->week_start) {
                $entryIdx = $i;
                break;
            }
        }
        if ($entryIdx === null) {
            return null;
        }

        $exitIdx = $entryIdx + $holdingDays;
        if ($exitIdx >= count($data['bars'])) {
            return null; // data tidak cukup untuk exit
        }

        $entry = $data['bars'][$entryIdx];
        $exit = $data['bars'][$exitIdx];
        if ($entry['close'] <= 0) {
            return null;
        }

        $trade = new BacktestTrade();
        $trade->stock_id = (int) $weekly->stock_id;
        $trade->entry_date = $entry['date'];
        $trade->entry_price = $entry['close'];
        $trade->exit_date = $exit['date'];
        $trade->exit_price = $exit['close'];
        $trade->return_pct = round(($exit['close'] / $entry['close'] - 1) * 100, 4);

        return $trade;
    }

    /**
     * Hitung & simpan metrik ke run (task 11.3, 11.4).
     * @param BacktestTrade[] $trades
     */
    private function applyMetrics(BacktestRun $run, array $trades): void
    {
        $run->trades = count($trades);
        if ($trades === []) {
            $run->win_rate = null;
            $run->avg_gain = null;
            $run->avg_loss = null;
            $run->profit_factor = null;
            $run->max_drawdown = null;
            $run->save(false);

            return;
        }

        $returns = array_map(fn ($t) => (float) $t->return_pct, $trades);
        $wins = array_values(array_filter($returns, fn ($r) => $r > 0));
        $losses = array_values(array_filter($returns, fn ($r) => $r < 0));

        $run->win_rate = round(count($wins) / count($returns), 4);
        $run->avg_gain = $wins !== [] ? round(array_sum($wins) / count($wins), 4) : null;
        $run->avg_loss = $losses !== [] ? round(array_sum($losses) / count($losses), 4) : null;
        $sumLoss = array_sum($losses);
        $run->profit_factor = $sumLoss < 0 ? round(array_sum($wins) / abs($sumLoss), 4) : null;
        $run->max_drawdown = round($this->maxDrawdown($trades), 4);
        $run->save(false);
    }

    /**
     * Max drawdown equity curve sederhana: urutkan trade per exit_date,
     * akumulasi return, cari penurunan terbesar dari puncak (poin persen).
     * @param BacktestTrade[] $trades
     */
    private function maxDrawdown(array $trades): float
    {
        usort($trades, fn ($a, $b) => [$a->exit_date, $a->id] <=> [$b->exit_date, $b->id]);

        $equity = 0.0;
        $peak = 0.0;
        $maxDd = 0.0;
        foreach ($trades as $t) {
            $equity += (float) $t->return_pct;
            $peak = max($peak, $equity);
            $maxDd = max($maxDd, $peak - $equity);
        }

        return $maxDd;
    }

    private function earliestWeek(): string
    {
        $min = WeeklyAnalysis::find()->select('MIN(week_start)')->scalar();

        return $min !== null ? (string) $min : date('Y-m-d');
    }
}
