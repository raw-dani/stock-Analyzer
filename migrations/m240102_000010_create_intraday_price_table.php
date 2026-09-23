<?php

declare(strict_types=1);

use yii\db\Migration;

require_once __DIR__ . '/ForeignKeyAwareTrait.php';

/**
 * Tabel harga intraday OHLCV (base 1h) untuk scanner RSI multi-timeframe.
 */
class m240102_000010_create_intraday_price_table extends Migration
{
    use ForeignKeyAwareTrait;

    public function safeUp(): void
    {
        $this->createTable('{{%intraday_price}}', [
            'id' => $this->primaryKey(),
            'stock_id' => $this->integer()->notNull(),
            'datetime' => $this->dateTime()->notNull(),
            'timeframe' => $this->string(4)->notNull()->defaultValue('1h'),
            'open' => $this->decimal(12, 4)->notNull(),
            'high' => $this->decimal(12, 4)->notNull(),
            'low' => $this->decimal(12, 4)->notNull(),
            'close' => $this->decimal(12, 4)->notNull(),
            'volume' => $this->bigInteger()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('udx-intraday-stock-dt-tf', '{{%intraday_price}}', ['stock_id', 'datetime', 'timeframe'], true);
        $this->createIndex('idx-intraday-stock-tf-dt', '{{%intraday_price}}', ['stock_id', 'timeframe', 'datetime']);
        $this->addFkCompat('fk-intraday_price-stock', '{{%intraday_price}}', 'stock_id', '{{%stock}}', 'id');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%intraday_price}}');
    }
}
