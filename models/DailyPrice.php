<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * @property int $id
 * @property int $stock_id
 * @property string $date
 * @property float $open
 * @property float $high
 * @property float $low
 * @property float $close
 * @property int $volume
 * @property int|null $buy_volume
 * @property int|null $sell_volume
 */
class DailyPrice extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%daily_price}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['stock_id', 'date', 'open', 'high', 'low', 'close', 'volume'], 'required'],
            [['stock_id', 'volume', 'buy_volume', 'sell_volume'], 'integer'],
            [['open', 'high', 'low', 'close'], 'number', 'min' => 0],
            [['volume'], 'integer', 'min' => 0],
            [['date'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function getStock(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }
}
