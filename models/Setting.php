<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * @property int $id
 * @property string $key
 * @property string $value
 * @property string $label
 * @property string $type string|int|float|bool|json
 * @property string $group provider|scoring|notification|system
 */
class Setting extends ActiveRecord
{
    public const TYPE_STRING = 'string';
    public const TYPE_INT = 'int';
    public const TYPE_FLOAT = 'float';
    public const TYPE_BOOL = 'bool';
    public const TYPE_JSON = 'json';

    public static function tableName(): string
    {
        return '{{%setting}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['key', 'value', 'label', 'type', 'group'], 'required'],
            [['key'], 'string', 'max' => 64],
            [['key'], 'unique'],
            [['label'], 'string', 'max' => 128],
            [['type'], 'in', 'range' => [
                self::TYPE_STRING, self::TYPE_INT, self::TYPE_FLOAT, self::TYPE_BOOL, self::TYPE_JSON,
            ]],
            [['group'], 'in', 'range' => ['provider', 'scoring', 'notification', 'system', 'general']],
        ];
    }

    public static function getValue(string $key): ?string
    {
        $raw = static::find()->select('value')->where(['key' => $key])->scalar();

        return ($raw === false || $raw === null) ? null : (string) $raw;
    }
}