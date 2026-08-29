<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * @property int $id
 * @property string $symbol
 * @property string $name
 * @property string $exchange
 * @property string|null $sector
 * @property string|null $industry
 * @property int|null $market_cap
 * @property bool $active
 * @property int $created_at
 * @property int $updated_at
 */
class Stock extends ActiveRecord
{
    public const EXCHANGE_NASDAQ = 'NASDAQ';
    public const EXCHANGE_NYSE = 'NYSE';
    public const EXCHANGE_AMEX = 'AMEX';

    public static function tableName(): string
    {
        return '{{%stock}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['symbol', 'name'], 'required'],
            [['symbol'], 'string', 'max' => 12],
            [['symbol'], 'unique'],
            [['symbol'], 'match', 'pattern' => '/^[A-Z.]+$/i',
                'message' => 'Symbol hanya boleh huruf dan titik.'],
            [['name'], 'string', 'max' => 255],
            [['exchange'], 'string', 'max' => 16],
            [['exchange'], 'in', 'range' => [self::EXCHANGE_NASDAQ, self::EXCHANGE_NYSE, self::EXCHANGE_AMEX]],
            [['sector'], 'string', 'max' => 64],
            [['industry'], 'string', 'max' => 128],
            [['market_cap'], 'integer', 'min' => 0],
            [['active'], 'boolean'],
            [['active'], 'default', 'value' => true],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'symbol' => 'Symbol',
            'name' => 'Company Name',
            'exchange' => 'Exchange',
            'sector' => 'Sector',
            'industry' => 'Industry',
            'market_cap' => 'Market Cap',
            'active' => 'Active',
        ];
    }

    public function getDailyPrices(): \yii\db\ActiveQuery
    {
        return $this->hasMany(DailyPrice::class, ['stock_id' => 'id'])
            ->orderBy(['date' => SORT_ASC]);
    }

    public function getWeeklyAnalyses(): \yii\db\ActiveQuery
    {
        return $this->hasMany(WeeklyAnalysis::class, ['stock_id' => 'id'])
            ->orderBy(['week_start' => SORT_DESC]);
    }

    public function getSignals(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Signal::class, ['stock_id' => 'id'])
            ->orderBy(['date' => SORT_DESC]);
    }

    public function getWatchlistItems(): \yii\db\ActiveQuery
    {
        return $this->hasMany(WatchlistItem::class, ['stock_id' => 'id']);
    }

    public function __toString(): string
    {
        return $this->symbol;
    }
}
