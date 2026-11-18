<?php

declare(strict_types=1);

use yii\db\Migration;

require_once __DIR__ . '/ForeignKeyAwareTrait.php';

/**
 * Migration untuk tabel Stock Learning Engine:
 * - stock_learning_run: Menyimpan histori & hasil optimasi strategi per saham
 * - stock_learning_generation: Menyimpan log evolusi tiap generasi (chart konvergensi)
 */
class m261118_150000_create_stock_learning_tables extends Migration
{
    use ForeignKeyAwareTrait;

    public function safeUp(): void
    {
        // 1. Tabel stock_learning_run
        $this->createTable('{{%stock_learning_run}}', [
            'id' => $this->primaryKey(),
            'stock_id' => $this->integer()->notNull(),
            'symbol' => $this->string(16)->notNull(),
            'timeframe' => $this->integer()->notNull()->defaultValue(24),
            'strategy_type' => $this->string(32)->notNull()->defaultValue('stochastic'),
            'generation_max' => $this->integer()->notNull()->defaultValue(50),
            'population_size' => $this->integer()->notNull()->defaultValue(30),
            'best_win_rate' => $this->decimal(5, 2)->null(),
            'best_fitness' => $this->decimal(8, 4)->null(),
            'best_params' => $this->text()->null(),
            'is_win_rate' => $this->decimal(5, 2)->null(),
            'oos_win_rate' => $this->decimal(5, 2)->null(),
            'total_trades' => $this->integer()->null(),
            'profit_factor' => $this->decimal(8, 4)->null(),
            'max_drawdown' => $this->decimal(5, 2)->null(),
            'sharpe_ratio' => $this->decimal(8, 4)->null(),
            'status' => $this->string(16)->notNull()->defaultValue('pending'),
            'error_message' => $this->text()->null(),
            'runtime_seconds' => $this->integer()->null(),
            'total_evals' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-learning_run-symbol-status', '{{%stock_learning_run}}', ['symbol', 'status']);
        $this->createIndex('idx-learning_run-stock', '{{%stock_learning_run}}', ['stock_id']);
        $this->createIndex('idx-learning_run-created', '{{%stock_learning_run}}', ['created_at']);
        $this->addFkCompat('fk-learning_run-stock', '{{%stock_learning_run}}', 'stock_id', '{{%stock}}', 'id');

        // 2. Tabel stock_learning_generation
        $this->createTable('{{%stock_learning_generation}}', [
            'id' => $this->primaryKey(),
            'run_id' => $this->integer()->notNull(),
            'generation' => $this->integer()->notNull(),
            'best_win_rate' => $this->decimal(5, 2)->null(),
            'best_fitness' => $this->decimal(8, 4)->null(),
            'best_params' => $this->text()->null(),
            'avg_fitness' => $this->decimal(8, 4)->null(),
            'convergence_delta' => $this->decimal(8, 6)->null(),
            'created_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-learning_gen-run_id', '{{%stock_learning_generation}}', ['run_id', 'generation']);
        $this->addFkCompat('fk-learning_gen-run', '{{%stock_learning_generation}}', 'run_id', '{{%stock_learning_run}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%stock_learning_generation}}');
        $this->dropTable('{{%stock_learning_run}}');
    }
}
