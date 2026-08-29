<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $alert_id
 * @property int $stock_id
 * @property string $message
 * @property string $channel app|email|telegram
 * @property int $created_at
 */
class AlertLog extends ActiveRecord
{
    public const CHANNEL_APP = 'app';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_TELEGRAM = 'telegram';

    public static function tableName(): string
    {
        return '{{%alert_log}}';
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
            [['alert_id', 'stock_id', 'message'], 'required'],
            [['alert_id', 'stock_id'], 'integer'],
            [['message'], 'string'],
            [['channel'], 'in', 'range' => [self::CHANNEL_APP, self::CHANNEL_EMAIL, self::CHANNEL_TELEGRAM]],
            [['channel'], 'default', 'value' => self::CHANNEL_APP],
        ];
    }

    public function getAlert(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Alert::class, ['id' => 'alert_id']);
    }

    public function getStock(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }
}
