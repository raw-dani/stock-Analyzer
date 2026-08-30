<?php

declare(strict_types=1);

use yii\db\Migration;
use yii\db\Expression;

/**
 * Modul 14 — Settings & Admin
 *  - Tambah kolom `role` di tabel user (RBAC sederhana: admin | user).
 *  - Tabel `setting` (key/value) untuk penyimpanan pengaturan yang dapat
 *    diubah admin lewat halaman settings (provider, threshold, channel).
 */
class m240101_000009_add_role_to_user_create_setting extends Migration
{
    public function safeUp(): void
    {
        // 14.2 RBAC: role per user
        $this->addColumn('{{%user}}', 'role', $this->string(16)->notNull()->defaultValue('user'));
        $this->createIndex('idx-user-role', '{{%user}}', 'role');

        // 14.1 Settings yang dapat disunting (override params)
        $this->createTable('{{%setting}}', [
            'id' => $this->primaryKey(),
            'key' => $this->string(64)->notNull(),
            'value' => $this->text()->notNull(),
            'label' => $this->string(128)->notNull(),
            'type' => $this->string(16)->notNull()->defaultValue('string'), // string|int|float|bool|json
            'group' => $this->string(32)->notNull()->defaultValue('general'), // provider|scoring|notification|system
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-setting-key', '{{%setting}}', 'key', true);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%setting}}');
        $this->dropIndex('idx-user-role', '{{%user}}');
        $this->dropColumn('{{%user}}', 'role');
    }
}