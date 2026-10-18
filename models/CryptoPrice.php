<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Model untuk candle OHLCV dan Taker Buy Volume crypto.
 *
 * @property int $id
 * @property int $crypto_id
 * @property string $timeframe
 * @property string $bar_time
 * @property float $open
 * @property float $high
 * @property float $low
 * @property float $close
 * @property float $volume
 * @property float $quote_volume
 * @property float $buy_volume
 * @property float $sell_volume
 * @property int $created_at
 *
 * @property-read CryptoAsset $cryptoAsset
 */
class CryptoPrice extends ActiveRecord
{
    public const TIMEFRAME_15M = '15m';
    public const TIMEFRAME_1H  = '1h';
    public const TIMEFRAME_2H  = '2h';
    public const TIMEFRAME_4H  = '4h';
    public const TIMEFRAME_1D  = '1d';

    public const SUPPORTED_TIMEFRAMES = [
        self::TIMEFRAME_15M => '15 Menit',
        self::TIMEFRAME_1H  => '1 Jam',
        self::TIMEFRAME_2H  => '2 Jam',
        self::TIMEFRAME_4H  => '4 Jam',
        self::TIMEFRAME_1D  => '1 Hari',
    ];

    public static function tableName(): string
    {
        return '{{%crypto_price}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'updatedAtAttribute' => false,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['crypto_id', 'timeframe', 'bar_time', 'open', 'high', 'low', 'close', 'volume'], 'required'],
            [['crypto_id'], 'integer'],
            [['timeframe'], 'string', 'max' => 5],
            [['timeframe'], 'in', 'range' => array_keys(self::SUPPORTED_TIMEFRAMES)],
            [['bar_time'], 'string', 'max' => 19],
            [['open', 'high', 'low', 'close', 'volume', 'quote_volume', 'buy_volume', 'sell_volume'], 'number'],
            [['buy_volume', 'sell_volume', 'quote_volume'], 'default', 'value' => 0],
        ];
    }

    public function getCryptoAsset(): ActiveQuery
    {
        return $this->hasOne(CryptoAsset::class, ['id' => 'crypto_id']);
    }

    /**
     * Hitung persentase buy volume agresif (taker buy)
     */
    public function getBuyRatio(): float
    {
        $total = (float)$this->volume;
        if ($total <= 0) {
            return 50.0;
        }
        return round(((float)$this->buy_volume / $total) * 100, 2);
    }
}
