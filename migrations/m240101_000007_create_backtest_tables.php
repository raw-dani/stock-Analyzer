<?php

declare(strict_types=1);

use yii\db\Migration;

require_once __DIR__ . '/ForeignKeyAwareTrait.php';

/**
 * Tabel backtest: run + trade (desain §6 — task 1.7, Phase 3)
 */
class m240101_000007_create_backtest_tables extends Migration
{
    use ForeignKeyAwareTrait;

    public function safeUp(): void
    {
        $this->createTable('{{%backtest_run}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(128)->notNull(),
            'params' => $this->text()->notNull(), // JSON: kriteria strategi, holding period
            'start_date' => $this->date()->notNull(),
            'end_date' => $this->date()->notNull(),
            'trades' => $this->integer()->notNull()->defaultValue(0),
            'win_rate' => $this->decimal(6, 4)->null(),
            'avg_gain' => $this->decimal(8, 4)->null(),
            'avg_loss' => $this->decimal(8, 4)->null(),
            'profit_factor' => $this->decimal(8, 4)->null(),
            'max_drawdown' => $this->decimal(8, 4)->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createTable('{{%backtest_trade}}', [
            'id' => $this->primaryKey(),
            'run_id' => $this->integer()->notNull(),
            'stock_id' => $this->integer()->notNull(),
            'entry_date' => $this->date()->notNull(),
            'entry_price' => $this->decimal(12, 4)->notNull(),
            'exit_date' => $this->date()->notNull(),
            'exit_price' => $this->decimal(12, 4)->notNull(),
            'return_pct' => $this->decimal(8, 4)->notNull(),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-btrun', '{{%backtest_trade}}', ['run_id', 'stock_id']);
        $this->addFkCompat('fk-bttrade-run', '{{%backtest_trade}}', 'run_id', '{{%backtest_run}}', 'id');
        $this->addFkCompat('fk-bttrade-stock', '{{%backtest_trade}}', 'stock_id', '{{%stock}}', 'id');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%backtest_trade}}');
        $this->dropTable('{{%backtest_run}}');
    }
}
