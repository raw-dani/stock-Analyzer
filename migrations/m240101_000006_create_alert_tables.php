<?php

declare(strict_types=1);

use yii\db\Migration;

require_once __DIR__ . '/ForeignKeyAwareTrait.php';

/**
 * Tabel alert + log notifikasi (desain §6 — task 1.6)
 */
class m240101_000006_create_alert_tables extends Migration
{
    use ForeignKeyAwareTrait;

    public function safeUp(): void
    {
        $this->createTable('{{%alert}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'stock_id' => $this->integer()->null(), // null = berlaku semua simbol
            'condition_type' => $this->string(32)->notNull(), // buy_ratio|volume_growth|rvol|score
            'operator' => $this->string(4)->notNull(), // > < >= <= =
            'threshold' => $this->decimal(12, 4)->notNull(),
            'condition_logic' => $this->string(8)->notNull()->defaultValue('AND'),
            'active' => $this->boolean()->notNull()->defaultValue(true),
            'last_triggered_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-alert-user-active', '{{%alert}}', ['user_id', 'active']);
        $this->addFkCompat('fk-alert-user', '{{%alert}}', 'user_id', '{{%user}}', 'id');
        $this->addFkCompat('fk-alert-stock', '{{%alert}}', 'stock_id', '{{%stock}}', 'id');

        $this->createTable('{{%alert_log}}', [
            'id' => $this->primaryKey(),
            'alert_id' => $this->integer()->notNull(),
            'stock_id' => $this->integer()->notNull(),
            'message' => $this->text()->notNull(),
            'channel' => $this->string(16)->notNull()->defaultValue('app'), // email|app|telegram
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-alert_log-alert', '{{%alert_log}}', ['alert_id', 'created_at']);
        $this->addFkCompat('fk-alert_log-alert', '{{%alert_log}}', 'alert_id', '{{%alert}}', 'id');
        $this->addFkCompat('fk-alert_log-stock', '{{%alert_log}}', 'stock_id', '{{%stock}}', 'id');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%alert_log}}');
        $this->dropTable('{{%alert}}');
    }
}
