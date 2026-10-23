<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * Model ActiveRecord untuk tabel {{%site_setting}}.
 * Menyimpan konfigurasi SEO, Webmaster, Google Analytics 4, GTM, dan OpenAI API.
 *
 * @property int $id
 * @property string $category
 * @property string $setting_key
 * @property string|null $setting_value
 * @property string|null $description
 * @property int $updated_at
 */
class SiteSetting extends ActiveRecord
{
    public const CATEGORY_SEO = 'seo';
    public const CATEGORY_ANALYTICS = 'analytics';
    public const CATEGORY_AI = 'ai';
    public const CATEGORY_GENERAL = 'general';

    private static array $runtimeCache = [];

    public static function tableName(): string
    {
        return '{{%site_setting}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => false,
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['setting_key'], 'required'],
            [['setting_key'], 'string', 'max' => 128],
            [['setting_key'], 'unique'],
            [['category'], 'string', 'max' => 64],
            [['category'], 'default', 'value' => self::CATEGORY_SEO],
            [['description'], 'string', 'max' => 255],
            [['setting_value'], 'string'],
        ];
    }

    /**
     * Dapatkan nilai pengaturan berdasarkan key (dengan runtime in-memory caching).
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$runtimeCache)) {
            return self::$runtimeCache[$key] !== null ? self::$runtimeCache[$key] : $default;
        }

        $val = static::find()->select(['setting_value'])->where(['setting_key' => $key])->scalar();
        if ($val === false || $val === null) {
            self::$runtimeCache[$key] = null;
            return $default;
        }

        self::$runtimeCache[$key] = (string) $val;
        return (string) $val;
    }

    /**
     * Simpan / perbarui nilai pengaturan.
     */
    public static function set(string $key, ?string $value, string $category = self::CATEGORY_SEO, ?string $desc = null): bool
    {
        $model = static::findOne(['setting_key' => $key]);
        if ($model === null) {
            $model = new static();
            $model->setting_key = $key;
            $model->category = $category;
            $model->description = $desc;
        }
        $model->setting_value = $value;
        if ($desc !== null) {
            $model->description = $desc;
        }

        $saved = $model->save();
        if ($saved) {
            self::$runtimeCache[$key] = $value;
        }
        return $saved;
    }

    /**
     * Dapatkan semua setting berdasarkan kategori dalam bentuk key => value array.
     */
    public static function getAllByCategory(string $category): array
    {
        $rows = static::find()->where(['category' => $category])->all();
        $result = [];
        foreach ($rows as $row) {
            $result[$row->setting_key] = $row->setting_value;
        }
        return $result;
    }

    /**
     * Reset runtime in-memory cache.
     */
    public static function flushCache(): void
    {
        self::$runtimeCache = [];
    }
}
