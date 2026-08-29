<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Tabel user (dibutuhkan FK watchlist & alert; dipakai login Modul 8.4)
 */
class m240101_000000_create_user_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(64)->notNull(),
            'email' => $this->string(255)->notNull(),
            'password_hash' => $this->string(255)->notNull(),
            'auth_key' => $this->string(32)->notNull(),
            'access_token' => $this->string(64)->null(),
            'status' => $this->smallInteger()->notNull()->defaultValue(10), // 10=active
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('udx-user-username', '{{%user}}', ['username'], true);
        $this->createIndex('udx-user-email', '{{%user}}', ['email'], true);
        $this->createIndex('udx-user-access_token', '{{%user}}', ['access_token'], true);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%user}}');
    }
}
