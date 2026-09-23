<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $stock_id
 * @property string $condition_type buy_ratio|volume_growth|rvol|score|rsi_double_bottom
 * @property string $operator
 * @property float $threshold
 * @property array|null $threshold_data JSON params untuk rsi_double_bottom (timeframe, lookback, tolerance, dll)
 * @property string $condition_logic
 * @property bool $active
 * @property int|null $last_triggered_at
 */
class Alert extends ActiveRecord
{
    public const CONDITION_BUY_RATIO = 'buy_ratio';
    public const CONDITION_VOLUME_GROWTH = 'volume_growth';
    public const CONDITION_RVOL = 'rvol';
    public const CONDITION_SCORE = 'score';
    public const CONDITION_RSI_DOUBLE_BOTTOM = 'rsi_double_bottom';

    public const CONDITIONS = [
        self::CONDITION_BUY_RATIO => 'Buy Ratio',
        self::CONDITION_VOLUME_GROWTH => 'Volume Growth',
        self::CONDITION_RVOL => 'RVOL',
        self::CONDITION_SCORE => 'Score',
        self::CONDITION_RSI_DOUBLE_BOTTOM => 'RSI Double Bottom',
    ];

    public const OPERATORS = [
        '>'  => '>',
        '<'  => '<',
        '>=' => '>=',
        '<=' => '<=',
        '='  => '=',
        'breakout'    => 'Breakout',
        'confidence'  => 'Confidence ≥',
        'divergence'  => 'Divergence',
        'found'       => 'Pattern Found',
    ];

    public static function tableName(): string
    {
        return '{{%alert}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['user_id', 'condition_type', 'operator', 'threshold'], 'required'],
            [['user_id', 'stock_id'], 'integer'],
            [['threshold'], 'number'],
            [['threshold_data'], 'safe'],
            [['condition_type'], 'in', 'range' => array_keys(self::CONDITIONS)],
            [['operator'], 'in', 'range' => array_keys(self::OPERATORS)],
            [['condition_logic'], 'in', 'range' => ['AND', 'OR']],
            [['condition_logic'], 'default', 'value' => 'AND'],
            [['active'], 'boolean'],
            [['active'], 'default', 'value' => true],
        ];
    }

    /**
     * Cek apakah satu nilai memenuhi kondisi alert ini.
     */
    public function matches(float $value): bool
    {
        return match ($this->operator) {
            '>' => $value > (float) $this->threshold,
            '<' => $value < (float) $this->threshold,
            '>=' => $value >= (float) $this->threshold,
            '<=' => $value <= (float) $this->threshold,
            '=' => abs($value - (float) $this->threshold) < 0.0001,
            default => false,
        };
    }

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(\app\models\User::class, ['id' => 'user_id']);
    }

    public function getStock(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }

    public function getLogs(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AlertLog::class, ['alert_id' => 'id']);
    }
}
