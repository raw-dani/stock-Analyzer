<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Tabel master saham (desain §6 — task 1.1)
 */
class m240101_000001_create_stock_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%stock}}', [
            'id' => $this->primaryKey(),
            'symbol' => $this->string(12)->notNull(),
            'name' => $this->string(255)->notNull(),
            'exchange' => $this->string(16)->notNull()->defaultValue('NASDAQ'),
            'sector' => $this->string(64)->null(),
            'industry' => $this->string(128)->null(),
            'market_cap' => $this->bigInteger()->null(),
            'active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('udx-stock-symbol', '{{%stock}}', ['symbol'], true);
        $this->createIndex('idx-stock-exchange-sector', '{{%stock}}', ['exchange', 'sector']);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%stock}}');
    }
}
