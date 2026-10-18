<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Model untuk aset crypto (terpisah dari model Stock).
 *
 * @property int $id
 * @property string $symbol
 * @property string $base_asset
 * @property string $quote_asset
 * @property string $name
 * @property string|null $icon_url
 * @property bool $is_active
 * @property int $sort_order
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read CryptoPrice[] $prices
 * @property-read CryptoAnalysis[] $analyses
 */
class CryptoAsset extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%crypto_asset}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            [['symbol', 'base_asset', 'name'], 'required'],
            [['symbol'], 'string', 'max' => 20],
            [['symbol'], 'unique'],
            [['base_asset', 'quote_asset'], 'string', 'max' => 10],
            [['name'], 'string', 'max' => 100],
            [['icon_url'], 'string', 'max' => 255],
            [['is_active'], 'boolean'],
            [['sort_order'], 'integer'],
            [['quote_asset'], 'default', 'value' => 'USDT'],
            [['is_active'], 'default', 'value' => true],
            [['sort_order'], 'default', 'value' => 0],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'symbol' => 'Symbol',
            'base_asset' => 'Base Asset',
            'quote_asset' => 'Quote Asset',
            'name' => 'Nama Koin',
            'icon_url' => 'Icon URL',
            'is_active' => 'Aktif',
            'sort_order' => 'Urutan',
            'created_at' => 'Dibuat',
            'updated_at' => 'Diperbarui',
        ];
    }

    public function getPrices(): ActiveQuery
    {
        return $this->hasMany(CryptoPrice::class, ['crypto_id' => 'id'])
            ->orderBy(['bar_time' => SORT_ASC]);
    }

    public function getAnalyses(): ActiveQuery
    {
        return $this->hasMany(CryptoAnalysis::class, ['crypto_id' => 'id']);
    }

    public function getAnalysisForTimeframe(string $timeframe): ?CryptoAnalysis
    {
        return CryptoAnalysis::findOne([
            'crypto_id' => $this->id,
            'timeframe' => $timeframe,
        ]);
    }

    /**
     * Dapatkan logo default atau generate icon SVG/inisial jika icon_url kosong
     */
    public function getIconUrl(): string
    {
        if (!empty($this->icon_url)) {
            return $this->icon_url;
        }
        $sym = strtolower($this->base_asset);
        return "https://assets.coincap.io/assets/icons/{$sym}@2x.png";
    }

    public function __toString(): string
    {
        return $this->symbol;
    }
}
