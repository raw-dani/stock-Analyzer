<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Model ringkasan dividen saham untuk screener dan ranking.
 *
 * @property int $id
 * @property int $stock_id
 * @property string $symbol
 * @property string|null $company_name
 * @property string|null $sector
 * @property float $current_price
 * @property float $ttm_dividend
 * @property float $dividend_yield
 * @property string $payout_frequency
 * @property int $payouts_per_year
 * @property string|null $last_ex_date
 * @property float|null $last_amount
 * @property string|null $estimated_next_ex_date
 * @property int $consecutive_years
 * @property string $safety_score
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Stock $stock
 * @property-read StockDividend[] $dividends
 */
class StockDividendSummary extends ActiveRecord
{
    public const FREQ_MONTHLY = 'monthly';
    public const FREQ_QUARTERLY = 'quarterly';
    public const FREQ_SEMI_ANNUAL = 'semi-annual';
    public const FREQ_ANNUAL = 'annual';
    public const FREQ_NONE = 'none';

    public const SAFETY_ARISTOCRAT = 'Aristocrat';
    public const SAFETY_SAFE = 'Safe';
    public const SAFETY_MODERATE = 'Moderate';
    public const SAFETY_WARNING = 'High Yield / Warning';

    public static function tableName(): string
    {
        return '{{%stock_dividend_summary}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['stock_id', 'symbol'], 'required'],
            [['stock_id', 'payouts_per_year', 'consecutive_years', 'created_at', 'updated_at'], 'integer'],
            [['current_price', 'ttm_dividend', 'dividend_yield', 'last_amount'], 'number'],
            [['symbol'], 'string', 'max' => 16],
            [['company_name'], 'string', 'max' => 255],
            [['sector', 'safety_score'], 'string', 'max' => 64],
            [['payout_frequency'], 'string', 'max' => 20],
            [['last_ex_date', 'estimated_next_ex_date'], 'string', 'max' => 10],
            [['symbol'], 'unique'],
            [['stock_id'], 'unique'],
        ];
    }

    public function getStock(): ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }

    public function getDividends(): ActiveQuery
    {
        return $this->hasMany(StockDividend::class, ['symbol' => 'symbol'])->orderBy(['ex_date' => SORT_DESC]);
    }
}
