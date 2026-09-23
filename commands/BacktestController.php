<?php

declare(strict_types=1);

namespace app\commands;

use app\models\form\BacktestForm;
use app\services\BacktesterService;
use app\services\RsiDoubleBottomService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * php yii backtest/run — jalankan backtest satu strategi (task 11.5).
 * php yii backtest/grid — grid search sederhana minScore × holdingDays (task 11.6).
 */
final class BacktestController extends Controller
{
    public $name = '';
    public $strategy = 'signal'; // signal | rsi_double_bottom
    public $minBuyRatio;
    public $minRvol;
    public $minScore;
    public $holdingDays = 5;
    public $startDate;
    public $endDate;

    // RSI Double Bottom params
    public $timeframe;
    public $lookback;
    public $tolerance;
    public $rsiPeriod;
    public $maxRsi;
    public $minSeparation;
    public $maxSeparation;
    public $necklineMin;
    public $minConfidence;
    public $breakoutOnly;

    public function options($actionID): array
    {
        return [
            'name', 'strategy',
            'minBuyRatio', 'minRvol', 'minScore',
            'holdingDays', 'startDate', 'endDate',
            'timeframe', 'lookback', 'tolerance', 'rsiPeriod', 'maxRsi',
            'minSeparation', 'maxSeparation', 'necklineMin',
            'minConfidence', 'breakoutOnly',
            'help'
        ];
    }

    public function actionRun(): int
    {
        $form = $this->makeForm();
        if (!$form->validate()) {
            $this->stderr("Form tidak valid: " . json_encode($form->errors) . "\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $run = (new BacktesterService())->run($form);
        $this->printRun($run);

        return ExitCode::OK;
    }

    /**
     * Grid search sederhana: kombinasi minScore × holdingDays (task 11.6).
     */
    public function actionGrid(): int
    {
        $service = new BacktesterService();
        $scores = [null, 50, 65, 80];
        $holdings = [5, 10, 20];

        $this->stdout("Grid search: minScore × holdingDays\n", Console::BOLD);
        $this->stdout(sprintf("%-22s %6s %8s %9s %9s %9s\n", 'Strategi', 'Trades', 'WinRate', 'AvgGain', 'AvgLoss', 'MaxDD'));

        foreach ($scores as $score) {
            foreach ($holdings as $holding) {
                $form = $this->makeForm();
                $form->minScore = $score;
                $form->holdingDays = $holding;
                if (!$form->validate()) {
                    continue;
                }

                $run = $service->run($form);
                $this->stdout(sprintf(
                    "%-22s %6d %8s %9s %9s %9s\n",
                    mb_substr($run->name, 0, 22),
                    $run->trades,
                    $run->win_rate !== null ? round((float) $run->win_rate * 100) . '%' : '-',
                    $run->avg_gain !== null ? round((float) $run->avg_gain, 2) : '-',
                    $run->avg_loss !== null ? round((float) $run->avg_loss, 2) : '-',
                    $run->max_drawdown !== null ? round((float) $run->max_drawdown, 2) : '-',
                ));
            }
        }

        $this->stdout("\nSemua run tersimpan di tabel backtest_run (lihat /backtest/index).\n", Console::FG_CYAN);

        return ExitCode::OK;
    }

    private function makeForm(): BacktestForm
    {
        $form = new BacktestForm();
        $form->name = (string) $this->name;
        $form->strategy = (string) $this->strategy;
        $form->minBuyRatio = $this->minBuyRatio !== null ? (float) $this->minBuyRatio : null;
        $form->minRvol = $this->minRvol !== null ? (float) $this->minRvol : null;
        $form->minScore = $this->minScore !== null ? (int) $this->minScore : null;
        $form->holdingDays = (int) $this->holdingDays;
        $form->startDate = $this->startDate ?: null;
        $form->endDate = $this->endDate ?: null;

        // RSI Double Bottom params
        if ($this->timeframe !== null) {
            $form->timeframe = (int) $this->timeframe;
        }
        if ($this->lookback !== null) {
            $form->lookback = (int) $this->lookback;
        }
        if ($this->tolerance !== null) {
            $form->tolerance = (float) $this->tolerance;
        }
        if ($this->rsiPeriod !== null) {
            $form->rsiPeriod = (int) $this->rsiPeriod;
        }
        if ($this->maxRsi !== null) {
            $form->maxRsi = (float) $this->maxRsi;
        }
        if ($this->minSeparation !== null) {
            $form->minSeparation = (int) $this->minSeparation;
        }
        if ($this->maxSeparation !== null) {
            $form->maxSeparation = (int) $this->maxSeparation;
        }
        if ($this->necklineMin !== null) {
            $form->necklineMin = (float) $this->necklineMin;
        }
        if ($this->minConfidence !== null) {
            $form->minConfidence = (int) $this->minConfidence;
        }
        if ($this->breakoutOnly !== null) {
            $form->breakoutOnly = (bool) $this->breakoutOnly;
        }

        return $form;
    }

    private function printRun(\app\models\BacktestRun $run): void
    {
        $this->stdout("\nRun #{$run->id}: {$run->name}\n", Console::BOLD);
        $this->stdout("  Periode      : {$run->start_date} s/d {$run->end_date}\n");
        $this->stdout("  Trades       : {$run->trades}\n");
        $this->stdout("  Win rate     : " . ($run->win_rate !== null ? round((float) $run->win_rate * 100, 1) . '%' : '-') . "\n");
        $this->stdout("  Avg gain     : " . ($run->avg_gain !== null ? round((float) $run->avg_gain, 2) . '%' : '-') . "\n");
        $this->stdout("  Avg loss     : " . ($run->avg_loss !== null ? round((float) $run->avg_loss, 2) . '%' : '-') . "\n");
        $this->stdout("  Profit factor: " . ($run->profit_factor !== null ? round((float) $run->profit_factor, 2) : '-') . "\n");
        $this->stdout("  Max drawdown : " . ($run->max_drawdown !== null ? round((float) $run->max_drawdown, 2) . ' pts' : '-') . "\n");

        foreach ($run->tradesRel as $i => $trade) {
            $symbol = $trade->stock->symbol ?? $trade->stock_id;
            $this->stdout(sprintf(
                "   %2d. %-6s %s -> %s  %8.4f -> %8.4f  %+7.2f%%\n",
                $i + 1,
                $symbol,
                $trade->entry_date,
                $trade->exit_date,
                (float) $trade->entry_price,
                (float) $trade->exit_price,
                (float) $trade->return_pct,
            ));
        }
    }
}
