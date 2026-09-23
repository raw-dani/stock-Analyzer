<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * Harga intraday OHLCV (base timeframe 1h).
 *
 * @property int $id
 * @property int $stock_id
 * @property string $datetime Y-m-d H:i:s
 * @property string $timeframe '1h'
 * @property float $open
 * @property float $high
 * @property float $low
 * @property float $close
 * @property int $volume
 */
class IntradayPrice extends ActiveRecord
{
    public const TF_1H = '1h';

    public static function tableName(): string
    {
        return '{{%intraday_price}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['stock_id', 'datetime', 'open', 'high', 'low', 'close', 'volume'], 'required'],
            [['stock_id', 'volume'], 'integer'],
            [['open', 'high', 'low', 'close'], 'number', 'min' => 0],
            [['volume'], 'integer', 'min' => 0],
            [['datetime'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
            [['timeframe'], 'string', 'max' => 4],
        ];
    }

    public function getStock(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }
}
