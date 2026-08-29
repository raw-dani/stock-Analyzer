<?php

declare(strict_types=1);

use yii\db\Migration;

require_once __DIR__ . '/ForeignKeyAwareTrait.php';

/**
 * Tabel histori sinyal (desain §6 — task 1.4)
 */
class m240101_000004_create_signal_table extends Migration
{
    use ForeignKeyAwareTrait;

    public function safeUp(): void
    {
        $this->createTable('{{%signal}}', [
            'id' => $this->primaryKey(),
            'stock_id' => $this->integer()->notNull(),
            'weekly_id' => $this->integer()->null(),
            'date' => $this->date()->notNull(),
            'price' => $this->decimal(12, 4)->notNull(),
            'score' => $this->integer()->notNull(),
            'signal' => $this->string(16)->notNull(),
            'buy_ratio' => $this->decimal(6, 4)->notNull(),
            'rvol' => $this->decimal(8, 3)->notNull(),
            'volume_growth' => $this->decimal(8, 4)->null(),
            'reason' => $this->text()->null(), // JSON array alasan
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-signal-stock-date', '{{%signal}}', ['stock_id', 'date']);
        $this->createIndex('idx-signal-signal', '{{%signal}}', ['signal']);
        $this->addFkCompat('fk-signal-stock', '{{%signal}}', 'stock_id', '{{%stock}}', 'id');
        $this->addFkCompat('fk-signal-weekly', '{{%signal}}', 'weekly_id', '{{%weekly_analysis}}', 'id', 'SET NULL');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%signal}}');
    }
}
