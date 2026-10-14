<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Model Riwayat Eksekusi Averaging Down & Transaksi Portofolio.
 *
 * @property int $id
 * @property int $position_id
 * @property int $user_id
 * @property string $action_type
 * @property string|null $scenario_name
 * @property float|null $old_price
 * @property float|null $new_price
 * @property float|null $old_shares
 * @property float|null $added_shares
 * @property float|null $new_shares
 * @property float|null $added_price
 * @property float|null $added_cost
 * @property string|null $notes
 * @property int $created_at
 *
 * @property-read PortfolioPosition $position
 * @property-read User $user
 */
class PortfolioHistory extends ActiveRecord
{
    public const ACTION_AVERAGE_DOWN = 'AVERAGE_DOWN';
    public const ACTION_BUY_INITIAL = 'BUY_INITIAL';
    public const ACTION_EDIT = 'EDIT';

    public static function tableName(): string
    {
        return '{{%portfolio_history}}';
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
            [['position_id', 'user_id', 'action_type'], 'required'],
            [['position_id', 'user_id', 'created_at'], 'integer'],
            [['old_price', 'new_price', 'old_shares', 'added_shares', 'new_shares', 'added_price', 'added_cost'], 'number'],
            [['notes'], 'string'],
            [['action_type'], 'string', 'max' => 32],
            [['scenario_name'], 'string', 'max' => 120],
        ];
    }

    public function getPosition(): ActiveQuery
    {
        return $this->hasOne(PortfolioPosition::class, ['id' => 'position_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
