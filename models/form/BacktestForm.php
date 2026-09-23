<?php

declare(strict_types=1);

namespace app\models\form;

use app\services\RsiDoubleBottomService;
use yii\base\Model;

/**
 * Kriteria strategi backtest (task 11.1).
 * Entry: weekly_analysis yang memenuhi kriteria; exit: close N hari bursa setelah entry.
 */
final class BacktestForm extends Model
{
    public string $name = '';
    public string $strategy = 'signal'; // signal | rsi_double_bottom
    public ?float $minBuyRatio = null;      // 0..1
    public ?float $minRvol = null;          // >= 0
    public ?int $minScore = null;           // 0..100
    public int $holdingDays = 5;            // hari bursa (bar trading)
    public ?string $startDate = null;       // filter entry_date (Y-m-d), opsional
    public ?string $endDate = null;

    // RSI Double Bottom specific params
    public int $timeframe = RsiDoubleBottomService::TIMEFRAME_4H;
    public int $lookback = 150;
    public float $tolerance = 3.0;
    public int $rsiPeriod = 14;
    public float $maxRsi = 40.0;
    public int $minSeparation = 5;
    public int $maxSeparation = 30;
    public float $necklineMin = 2.0;
    public ?int $minConfidence = null;      // filter min confidence
    public bool $breakoutOnly = false;      // only trade breakout confirmed

    public function formName(): string
    {
        return 'BacktestForm';
    }

    public function rules(): array
    {
        return [
            [['strategy', 'holdingDays'], 'required'],
            [['strategy'], 'in', 'range' => ['signal', 'rsi_double_bottom']],
            [['holdingDays'], 'integer', 'min' => 1, 'max' => 60],
            [['minScore'], 'integer', 'min' => 0, 'max' => 100],
            [['minBuyRatio'], 'number', 'min' => 0, 'max' => 1],
            [['minRvol'], 'number', 'min' => 0],
            [['name'], 'string', 'max' => 128],
            [['startDate', 'endDate'], 'date', 'format' => 'php:Y-m-d'],
            [['startDate'], 'validateRange'],
            // RSI Double Bottom params
            [['timeframe'], 'in', 'range' => [RsiDoubleBottomService::TIMEFRAME_1H, RsiDoubleBottomService::TIMEFRAME_2H, RsiDoubleBottomService::TIMEFRAME_4H, RsiDoubleBottomService::TIMEFRAME_1D]],
            [['lookback'], 'integer', 'min' => 20, 'max' => 300],
            [['tolerance'], 'number', 'min' => 0.5, 'max' => 10.0],
            [['rsiPeriod'], 'integer', 'min' => 2, 'max' => 50],
            [['maxRsi'], 'number', 'min' => 0, 'max' => 100],
            [['minSeparation'], 'integer', 'min' => 1],
            [['maxSeparation'], 'integer', 'min' => 1],
            [['necklineMin'], 'number', 'min' => 0],
            [['minConfidence'], 'integer', 'min' => 0, 'max' => 100],
            [['breakoutOnly'], 'boolean'],
        ];
    }

    public function validateRange(string $attribute): void
    {
        if ($this->startDate !== null && $this->startDate !== ''
            && $this->endDate !== null && $this->endDate !== ''
            && $this->startDate > $this->endDate) {
            $this->addError($attribute, 'Start date harus ≤ end date.');
        }
    }

    public function resolvedName(): string
    {
        if ($this->name !== '') {
            return $this->name;
        }

        if ($this->strategy === 'rsi_double_bottom') {
            $parts = ['rsi_db'];
            if ($this->minConfidence !== null) {
                $parts[] = 'conf>=' . $this->minConfidence;
            }
            if ($this->breakoutOnly) {
                $parts[] = 'bo';
            }
            $parts[] = 'tf=' . RsiDoubleBottomService::getTimeframeLabel($this->timeframe);
            return implode('/', $parts) . ' hold=' . $this->holdingDays;
        }

        $parts = [];
        if ($this->minScore !== null) {
            $parts[] = 'score>=' . $this->minScore;
        }
        if ($this->minBuyRatio !== null) {
            $parts[] = 'br>=' . round($this->minBuyRatio, 2);
        }
        if ($this->minRvol !== null) {
            $parts[] = 'rvol>=' . round($this->minRvol, 2);
        }

        return ($parts === [] ? 'all-signal' : implode('/', $parts)) . ' hold=' . $this->holdingDays;
    }
}
