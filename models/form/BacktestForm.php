<?php

declare(strict_types=1);

namespace app\models\form;

use yii\base\Model;

/**
 * Kriteria strategi backtest (task 11.1).
 * Entry: weekly_analysis yang memenuhi kriteria; exit: close N hari bursa setelah entry.
 */
final class BacktestForm extends Model
{
    public string $name = '';
    public ?float $minBuyRatio = null;      // 0..1
    public ?float $minRvol = null;          // >= 0
    public ?int $minScore = null;           // 0..100
    public int $holdingDays = 5;            // hari bursa (bar trading)
    public ?string $startDate = null;       // filter entry_date (Y-m-d), opsional
    public ?string $endDate = null;

    public function formName(): string
    {
        return 'BacktestForm';
    }

    public function rules(): array
    {
        return [
            [['holdingDays'], 'required'],
            [['holdingDays'], 'integer', 'min' => 1, 'max' => 60],
            [['minScore'], 'integer', 'min' => 0, 'max' => 100],
            [['minBuyRatio'], 'number', 'min' => 0, 'max' => 1],
            [['minRvol'], 'number', 'min' => 0],
            [['name'], 'string', 'max' => 128],
            [['startDate', 'endDate'], 'date', 'format' => 'php:Y-m-d'],
            [['startDate'], 'validateRange'],
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
