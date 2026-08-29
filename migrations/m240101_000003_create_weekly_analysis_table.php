<?php

declare(strict_types=1);

use yii\db\Migration;

require_once __DIR__ . '/ForeignKeyAwareTrait.php';

/**
 * Tabel analisis mingguan + indikator + skor/sinyal (desain §6 — task 1.3)
 */
class m240101_000003_create_weekly_analysis_table extends Migration
{
    use ForeignKeyAwareTrait;

    public function safeUp(): void
    {
        $this->createTable('{{%weekly_analysis}}', [
            'id' => $this->primaryKey(),
            'stock_id' => $this->integer()->notNull(),
            'week_start' => $this->date()->notNull(),
            'buy_volume' => $this->bigInteger()->notNull()->defaultValue(0),
            'sell_volume' => $this->bigInteger()->notNull()->defaultValue(0),
            'buy_ratio' => $this->decimal(6, 4)->notNull()->defaultValue(0),
            'sell_ratio' => $this->decimal(6, 4)->notNull()->defaultValue(0),
            'buy_sell_ratio' => $this->decimal(8, 3)->notNull()->defaultValue(0),
            'volume_growth' => $this->decimal(8, 4)->null(),
            'rvol' => $this->decimal(8, 3)->null(),
            'ma20' => $this->decimal(12, 4)->null(),
            'ma50' => $this->decimal(12, 4)->null(),
            'close_price' => $this->decimal(12, 4)->notNull(),
            'score' => $this->integer()->notNull()->defaultValue(0),
            'signal' => $this->string(16)->notNull()->defaultValue('SELL'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('udx-weekly-stock-week', '{{%weekly_analysis}}', ['stock_id', 'week_start'], true);
        $this->createIndex('idx-weekly-signal', '{{%weekly_analysis}}', ['signal']);
        $this->createIndex('idx-weekly-score', '{{%weekly_analysis}}', ['score']);
        $this->addFkCompat('fk-weekly_analysis-stock', '{{%weekly_analysis}}', 'stock_id', '{{%stock}}', 'id');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%weekly_analysis}}');
    }
}
