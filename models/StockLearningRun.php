<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Model ActiveRecord untuk tabel stock_learning_run.
 *
 * @property int $id
 * @property int $stock_id
 * @property string $symbol
 * @property int $timeframe
 * @property string $strategy_type
 * @property int $generation_max
 * @property int $population_size
 * @property float|null $best_win_rate
 * @property float|null $best_fitness
 * @property string|null $best_params
 * @property float|null $is_win_rate
 * @property float|null $oos_win_rate
 * @property int|null $total_trades
 * @property float|null $profit_factor
 * @property float|null $max_drawdown
 * @property float|null $sharpe_ratio
 * @property string $status
 * @property string|null $error_message
 * @property int|null $runtime_seconds
 * @property int $total_evals
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Stock $stock
 * @property-read StockLearningGeneration[] $generations
 */
class StockLearningRun extends ActiveRecord
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    public static function tableName(): string
    {
        return '{{%stock_learning_run}}';
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
            [['stock_id', 'symbol', 'strategy_type'], 'required'],
            [['stock_id', 'timeframe', 'generation_max', 'population_size', 'total_trades', 'runtime_seconds', 'total_evals'], 'integer'],
            [['best_win_rate', 'best_fitness', 'is_win_rate', 'oos_win_rate', 'profit_factor', 'max_drawdown', 'sharpe_ratio'], 'number'],
            [['best_params', 'error_message'], 'string'],
            [['symbol'], 'string', 'max' => 16],
            [['strategy_type'], 'string', 'max' => 32],
            [['status'], 'string', 'max' => 16],
            [['status'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_COMPLETED, self::STATUS_FAILED]],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'stock_id' => 'Stock ID',
            'symbol' => 'Kode Saham',
            'timeframe' => 'Timeframe',
            'strategy_type' => 'Strategi',
            'generation_max' => 'Maks Generasi',
            'population_size' => 'Ukuran Populasi',
            'best_win_rate' => 'Win Rate Terbaik (%)',
            'best_fitness' => 'Skor Fitness',
            'best_params' => 'Parameter Optimal (JSON)',
            'is_win_rate' => 'In-Sample Win Rate (%)',
            'oos_win_rate' => 'Out-of-Sample Win Rate (%)',
            'total_trades' => 'Total Transaksi',
            'profit_factor' => 'Profit Factor',
            'max_drawdown' => 'Max Drawdown (%)',
            'sharpe_ratio' => 'Sharpe Ratio',
            'status' => 'Status',
            'runtime_seconds' => 'Waktu Komputasi (detik)',
            'total_evals' => 'Total Simulasi Backtest',
            'created_at' => 'Dibuat Pada',
            'updated_at' => 'Diperbarui Pada',
        ];
    }

    public function getStock(): ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }

    public function getGenerations(): ActiveQuery
    {
        return $this->hasMany(StockLearningGeneration::class, ['run_id' => 'id'])->orderBy(['generation' => SORT_ASC]);
    }

    /**
     * Dapatkan parameter optimal yang telah didecode dari JSON.
     *
     * @return array<string, mixed>
     */
    public function getDecodedParams(): array
    {
        if (empty($this->best_params)) {
            return [];
        }
        $decoded = json_decode($this->best_params, true);
        return is_array($decoded) ? $decoded : [];
    }
}
