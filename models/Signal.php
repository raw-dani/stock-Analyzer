<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * @property int $id
 * @property int $stock_id
 * @property int|null $weekly_id
 * @property string $date
 * @property float $price
 * @property int $score
 * @property string $signal
 * @property float $buy_ratio
 * @property float $rvol
 * @property float|null $volume_growth
 * @property string|null $reason JSON array alasan
 */
class Signal extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%signal}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['stock_id', 'date', 'price', 'score', 'signal', 'buy_ratio', 'rvol'], 'required'],
            [['stock_id', 'weekly_id', 'score'], 'integer'],
            [['score'], 'integer', 'min' => 0, 'max' => 100],
            [['price'], 'number', 'min' => 0],
            [['buy_ratio'], 'number', 'min' => 0, 'max' => 1],
            [['rvol'], 'number', 'min' => 0],
            [['volume_growth'], 'number'],
            [['date'], 'date', 'format' => 'php:Y-m-d'],
            [['signal'], 'in', 'range' => array_keys(WeeklyAnalysis::SIGNALS)],
            [['reason'], 'safe'],
        ];
    }

    /**
     * @return string[] daftar alasan sinyal
     */
    public function getReasonList(): array
    {
        if (empty($this->reason)) {
            return [];
        }
        $decoded = json_decode($this->reason, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getStock(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }

    public function getWeekly(): \yii\db\ActiveQuery
    {
        return $this->hasOne(WeeklyAnalysis::class, ['id' => 'weekly_id']);
    }
}
