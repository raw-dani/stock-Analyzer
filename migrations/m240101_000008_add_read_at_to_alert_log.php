<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Kolom read_at untuk in-app notification read/unread (task 10.5).
 */
class m240101_000008_add_read_at_to_alert_log extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%alert_log}}', 'read_at', $this->integer()->null()->after('channel'));
        $this->createIndex('idx-alert_log-read', '{{%alert_log}}', ['read_at']);
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%alert_log}}', 'read_at');
    }
}
