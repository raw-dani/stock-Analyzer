<?php

declare(strict_types=1);

use yii\db\Migration;

require_once __DIR__ . '/ForeignKeyAwareTrait.php';

/**
 * Migration untuk tabel crypto: crypto_asset, crypto_price, dan crypto_analysis.
 * Terisolasi 100% dari modul saham.
 */
class m261018_173000_create_crypto_tables extends Migration
{
    use ForeignKeyAwareTrait;

    public function safeUp(): void
    {
        // 1. Tabel crypto_asset
        $this->createTable('{{%crypto_asset}}', [
            'id' => $this->primaryKey(),
            'symbol' => $this->string(20)->notNull(),
            'base_asset' => $this->string(10)->notNull(),
            'quote_asset' => $this->string(10)->notNull()->defaultValue('USDT'),
            'name' => $this->string(100)->notNull(),
            'icon_url' => $this->string(255)->null(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('udx-crypto_asset-symbol', '{{%crypto_asset}}', ['symbol'], true);
        $this->createIndex('idx-crypto_asset-active', '{{%crypto_asset}}', ['is_active', 'sort_order']);

        // 2. Tabel crypto_price
        $this->createTable('{{%crypto_price}}', [
            'id' => $this->bigPrimaryKey(),
            'crypto_id' => $this->integer()->notNull(),
            'timeframe' => $this->string(5)->notNull(),
            'bar_time' => $this->string(19)->notNull(),
            'open' => $this->decimal(18, 8)->notNull(),
            'high' => $this->decimal(18, 8)->notNull(),
            'low' => $this->decimal(18, 8)->notNull(),
            'close' => $this->decimal(18, 8)->notNull(),
            'volume' => $this->decimal(24, 8)->notNull(),
            'quote_volume' => $this->decimal(24, 4)->notNull()->defaultValue(0),
            'buy_volume' => $this->decimal(24, 8)->notNull()->defaultValue(0),
            'sell_volume' => $this->decimal(24, 8)->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('udx-crypto_price-bar', '{{%crypto_price}}', ['crypto_id', 'timeframe', 'bar_time'], true);
        $this->createIndex('idx-crypto_price-tf-time', '{{%crypto_price}}', ['crypto_id', 'timeframe', 'bar_time']);
        $this->addFkCompat('fk-crypto_price-crypto', '{{%crypto_price}}', 'crypto_id', '{{%crypto_asset}}', 'id');

        // 3. Tabel crypto_analysis
        $this->createTable('{{%crypto_analysis}}', [
            'id' => $this->primaryKey(),
            'crypto_id' => $this->integer()->notNull(),
            'timeframe' => $this->string(5)->notNull(),
            'current_price' => $this->decimal(18, 8)->notNull(),
            'price_change_24h' => $this->decimal(8, 2)->null(),
            'buy_ratio' => $this->decimal(5, 2)->notNull()->defaultValue(50.00),
            'rvol' => $this->decimal(6, 2)->null(),
            'rsi' => $this->decimal(5, 2)->null(),
            'ma20' => $this->decimal(18, 8)->null(),
            'ma50' => $this->decimal(18, 8)->null(),
            'score' => $this->integer()->notNull()->defaultValue(50),
            'signal' => $this->string(20)->notNull()->defaultValue('NEUTRAL'),
            'updated_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('udx-crypto_analysis-tf', '{{%crypto_analysis}}', ['crypto_id', 'timeframe'], true);
        $this->createIndex('idx-crypto_analysis-signal', '{{%crypto_analysis}}', ['signal', 'score']);
        $this->addFkCompat('fk-crypto_analysis-crypto', '{{%crypto_analysis}}', 'crypto_id', '{{%crypto_asset}}', 'id');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%crypto_analysis}}');
        $this->dropTable('{{%crypto_price}}');
        $this->dropTable('{{%crypto_asset}}');
    }
}
