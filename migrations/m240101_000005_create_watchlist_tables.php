<?php

declare(strict_types=1);

use yii\db\Migration;

require_once __DIR__ . '/ForeignKeyAwareTrait.php';

/**
 * Tabel watchlist per user (desain §6 — task 1.5)
 */
class m240101_000005_create_watchlist_tables extends Migration
{
    use ForeignKeyAwareTrait;

    public function safeUp(): void
    {
        $this->createTable('{{%watchlist}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(64)->notNull(),
            'description' => $this->string(255)->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('udx-watchlist-user-name', '{{%watchlist}}', ['user_id', 'name'], true);
        $this->addFkCompat('fk-watchlist-user', '{{%watchlist}}', 'user_id', '{{%user}}', 'id');

        $this->createTable('{{%watchlist_item}}', [
            'id' => $this->primaryKey(),
            'watchlist_id' => $this->integer()->notNull(),
            'stock_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('udx-wlitem', '{{%watchlist_item}}', ['watchlist_id', 'stock_id'], true);
        $this->addFkCompat('fk-wlitem-watchlist', '{{%watchlist_item}}', 'watchlist_id', '{{%watchlist}}', 'id');
        $this->addFkCompat('fk-wlitem-stock', '{{%watchlist_item}}', 'stock_id', '{{%stock}}', 'id');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%watchlist_item}}');
        $this->dropTable('{{%watchlist}}');
    }
}
