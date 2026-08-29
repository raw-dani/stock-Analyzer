<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $run_id
 * @property int $stock_id
 * @property string $entry_date
 * @property float $entry_price
 * @property string $exit_date
 * @property float $exit_price
 * @property float $return_pct
 */
class BacktestTrade extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%backtest_trade}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => \yii\behaviors\TimestampBehavior::class,
                'updatedAtAttribute' => false,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['run_id', 'stock_id', 'entry_date', 'entry_price', 'exit_date', 'exit_price', 'return_pct'], 'required'],
            [['run_id', 'stock_id'], 'integer'],
            [['entry_price', 'exit_price'], 'number', 'min' => 0],
            [['return_pct'], 'number'],
            [['entry_date', 'exit_date'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function getRun(): \yii\db\ActiveQuery
    {
        return $this->hasOne(BacktestRun::class, ['id' => 'run_id']);
    }

    public function getStock(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }
}
