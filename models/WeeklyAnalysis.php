<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * @property int $id
 * @property int $stock_id
 * @property string $week_start
 * @property int $buy_volume
 * @property int $sell_volume
 * @property float $buy_ratio
 * @property float $sell_ratio
 * @property float $buy_sell_ratio
 * @property float|null $volume_growth
 * @property float|null $rvol
 * @property float|null $ma20
 * @property float|null $ma50
 * @property float $close_price
 * @property int $score
 * @property string $signal
 */
class WeeklyAnalysis extends ActiveRecord
{
    public const SIGNAL_STRONG_BUY = 'STRONG_BUY';
    public const SIGNAL_BUY = 'BUY';
    public const SIGNAL_WATCH = 'WATCH';
    public const SIGNAL_WEAK = 'WEAK';
    public const SIGNAL_SELL = 'SELL';

    /** Semua tipe sinyal (dipakai GridView filter & badge) */
    public const SIGNALS = [
        self::SIGNAL_STRONG_BUY => 'STRONG BUY',
        self::SIGNAL_BUY => 'BUY',
        self::SIGNAL_WATCH => 'WATCH',
        self::SIGNAL_WEAK => 'WEAK',
        self::SIGNAL_SELL => 'SELL',
    ];

    public static function tableName(): string
    {
        return '{{%weekly_analysis}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['stock_id', 'week_start', 'close_price'], 'required'],
            [['stock_id', 'score'], 'integer'],
            [['score'], 'integer', 'min' => 0, 'max' => 100],
            [['buy_volume', 'sell_volume'], 'integer', 'min' => 0],
            [['buy_ratio', 'sell_ratio'], 'number', 'min' => 0, 'max' => 1],
            [['buy_sell_ratio'], 'number', 'min' => 0],
            [['volume_growth', 'rvol', 'ma20', 'ma50'], 'number'],
            [['close_price'], 'number', 'min' => 0],
            [['week_start'], 'date', 'format' => 'php:Y-m-d'],
            [['signal'], 'in', 'range' => array_keys(self::SIGNALS)],
        ];
    }

    public function getStock(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }
}
