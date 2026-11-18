<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Model ActiveRecord untuk tabel stock_learning_generation.
 *
 * @property int $id
 * @property int $run_id
 * @property int $generation
 * @property float|null $best_win_rate
 * @property float|null $best_fitness
 * @property string|null $best_params
 * @property float|null $avg_fitness
 * @property float|null $convergence_delta
 * @property int $created_at
 *
 * @property-read StockLearningRun $run
 */
class StockLearningGeneration extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%stock_learning_generation}}';
    }

    public function rules(): array
    {
        return [
            [['run_id', 'generation', 'created_at'], 'required'],
            [['run_id', 'generation', 'created_at'], 'integer'],
            [['best_win_rate', 'best_fitness', 'avg_fitness', 'convergence_delta'], 'number'],
            [['best_params'], 'string'],
        ];
    }

    public function getRun(): ActiveQuery
    {
        return $this->hasOne(StockLearningRun::class, ['id' => 'run_id']);
    }

    public function getDecodedParams(): array
    {
        if (empty($this->best_params)) {
            return [];
        }
        $decoded = json_decode($this->best_params, true);
        return is_array($decoded) ? $decoded : [];
    }
}
