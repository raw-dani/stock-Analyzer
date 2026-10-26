<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Model riwayat pembayaran dividen individual.
 *
 * @property int $id
 * @property int $stock_id
 * @property string $symbol
 * @property string $ex_date
 * @property float $amount
 * @property string|null $record_date
 * @property string|null $payment_date
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Stock $stock
 */
class StockDividend extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%stock_dividend}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['stock_id', 'symbol', 'ex_date', 'amount'], 'required'],
            [['stock_id', 'created_at', 'updated_at'], 'integer'],
            [['amount'], 'number'],
            [['symbol'], 'string', 'max' => 16],
            [['ex_date', 'record_date', 'payment_date'], 'string', 'max' => 10],
            [['symbol', 'ex_date'], 'unique', 'targetAttribute' => ['symbol', 'ex_date']],
        ];
    }

    public function getStock(): ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }
}
