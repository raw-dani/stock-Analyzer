<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Model untuk hasil analisis teknikal & volume pressure crypto per timeframe.
 *
 * @property int $id
 * @property int $crypto_id
 * @property string $timeframe
 * @property float $current_price
 * @property float|null $price_change_24h
 * @property float $buy_ratio
 * @property float|null $rvol
 * @property float|null $rsi
 * @property float|null $ma20
 * @property float|null $ma50
 * @property int $score
 * @property string $signal
 * @property int $updated_at
 *
 * @property-read CryptoAsset $cryptoAsset
 */
class CryptoAnalysis extends ActiveRecord
{
    public const SIGNAL_STRONG_BUY = 'STRONG_BUY';
    public const SIGNAL_BUY        = 'BUY';
    public const SIGNAL_NEUTRAL    = 'NEUTRAL';
    public const SIGNAL_SELL       = 'SELL';

    public const SIGNALS = [
        self::SIGNAL_STRONG_BUY => 'STRONG BUY',
        self::SIGNAL_BUY        => 'BUY',
        self::SIGNAL_NEUTRAL    => 'NEUTRAL',
        self::SIGNAL_SELL       => 'SELL',
    ];

    public static function tableName(): string
    {
        return '{{%crypto_analysis}}';
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
            [['crypto_id', 'timeframe', 'current_price'], 'required'],
            [['crypto_id', 'score'], 'integer'],
            [['timeframe'], 'string', 'max' => 5],
            [['current_price', 'price_change_24h', 'buy_ratio', 'rvol', 'rsi', 'ma20', 'ma50'], 'number'],
            [['signal'], 'string', 'max' => 20],
            [['signal'], 'in', 'range' => array_keys(self::SIGNALS)],
            [['score'], 'integer', 'min' => 0, 'max' => 100],
            [['score'], 'default', 'value' => 50],
            [['signal'], 'default', 'value' => self::SIGNAL_NEUTRAL],
        ];
    }

    public function getCryptoAsset(): ActiveQuery
    {
        return $this->hasOne(CryptoAsset::class, ['id' => 'crypto_id']);
    }

    public function getSignalBadgeClass(): string
    {
        return match ($this->signal) {
            self::SIGNAL_STRONG_BUY => 'bg-success text-white',
            self::SIGNAL_BUY => 'bg-primary text-white',
            self::SIGNAL_NEUTRAL => 'bg-secondary text-white',
            self::SIGNAL_SELL => 'bg-danger text-white',
            default => 'bg-light text-dark',
        };
    }
}
