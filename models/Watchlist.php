<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 */
class Watchlist extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%watchlist}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 64],
            [['description'], 'string', 'max' => 255],
            [['user_id'], 'integer'],
            [
                ['name'],
                'unique',
                'targetAttribute' => ['user_id', 'name'],
                'message' => 'Anda sudah memiliki watchlist dengan nama ini.',
            ],
        ];
    }

    public function getItems(): \yii\db\ActiveQuery
    {
        return $this->hasMany(WatchlistItem::class, ['watchlist_id' => 'id']);
    }

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(\app\models\User::class, ['id' => 'user_id']);
    }
}
