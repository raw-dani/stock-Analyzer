<?php

declare(strict_types=1);

use yii\db\Migration;

require_once __DIR__ . '/ForeignKeyAwareTrait.php';

/**
 * Tabel harga harian OHLCV + hasil klasifikasi buy/sell volume (desain §6 — task 1.2)
 */
class m240101_000002_create_daily_price_table extends Migration
{
    use ForeignKeyAwareTrait;

    public function safeUp(): void
    {
        $this->createTable('{{%daily_price}}', [
            'id' => $this->primaryKey(),
            'stock_id' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'open' => $this->decimal(12, 4)->notNull(),
            'high' => $this->decimal(12, 4)->notNull(),
            'low' => $this->decimal(12, 4)->notNull(),
            'close' => $this->decimal(12, 4)->notNull(),
            'volume' => $this->bigInteger()->notNull(),
            'buy_volume' => $this->bigInteger()->null(),
            'sell_volume' => $this->bigInteger()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('udx-daily-stock-date', '{{%daily_price}}', ['stock_id', 'date'], true);
        $this->addFkCompat('fk-daily_price-stock', '{{%daily_price}}', 'stock_id', '{{%stock}}', 'id');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%daily_price}}');
    }
}
