<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $watchlist_id
 * @property int $stock_id
 * @property int $created_at
 */
class WatchlistItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%watchlist_item}}';
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
            [['watchlist_id', 'stock_id'], 'required'],
            [['watchlist_id', 'stock_id'], 'integer'],
            [
                ['watchlist_id', 'stock_id'],
                'unique',
                'targetAttribute' => ['watchlist_id', 'stock_id'],
                'message' => 'Saham ini sudah ada di watchlist.',
            ],
        ];
    }

    public function getWatchlist(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Watchlist::class, ['id' => 'watchlist_id']);
    }

    public function getStock(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }

    /** WeeklyAnalysis terakhir untuk simbol ini (dipakai tabel watchlist) */
    public function getLatestWeekly(): \yii\db\ActiveQuery
    {
        return $this->hasOne(WeeklyAnalysis::class, ['stock_id' => 'stock_id'])
            ->orderBy(['week_start' => SORT_DESC]);
    }
}
