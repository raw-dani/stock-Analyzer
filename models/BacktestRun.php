<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * @property int $id
 * @property string $name
 * @property string $params JSON kriteria strategi
 * @property string $start_date
 * @property string $end_date
 * @property int $trades
 * @property float|null $win_rate
 * @property float|null $avg_gain
 * @property float|null $avg_loss
 * @property float|null $profit_factor
 * @property float|null $max_drawdown
 */
class BacktestRun extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%backtest_run}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['name', 'params', 'start_date', 'end_date'], 'required'],
            [['name'], 'string', 'max' => 128],
            [['trades'], 'integer', 'min' => 0],
            [['win_rate', 'avg_gain', 'avg_loss', 'profit_factor', 'max_drawdown'], 'number'],
            [['start_date', 'end_date'], 'date', 'format' => 'php:Y-m-d'],
            [['params'], 'safe'],
        ];
    }

    /**
     * @return array kriteria strategi (decoded params)
     */
    public function getCriteria(): array
    {
        $decoded = json_decode($this->params, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getTradesRel(): \yii\db\ActiveQuery
    {
        return $this->hasMany(BacktestTrade::class, ['run_id' => 'id']);
    }
}
