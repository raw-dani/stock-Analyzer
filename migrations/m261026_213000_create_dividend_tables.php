<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Migration untuk tabel Dividend Hunter (riwayat pembayaran dividen & ringkasan metrik).
 */
class m261026_213000_create_dividend_tables extends Migration
{
    public function safeUp(): void
    {
        // 1. Tabel riwayat pembayaran dividen (tiap pembayaran per tanggal ex-dividend)
        $this->createTable('{{%stock_dividend}}', [
            'id' => $this->primaryKey(),
            'stock_id' => $this->integer()->notNull(),
            'symbol' => $this->string(16)->notNull(),
            'ex_date' => $this->string(10)->notNull(),
            'amount' => $this->decimal(10, 4)->notNull(),
            'record_date' => $this->string(10)->null(),
            'payment_date' => $this->string(10)->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-stock-div-sym', '{{%stock_dividend}}', ['symbol']);
        $this->createIndex('idx-stock-div-stock-id', '{{%stock_dividend}}', ['stock_id']);
        $this->createIndex('idx-stock-div-ex-date', '{{%stock_dividend}}', ['ex_date']);
        $this->createIndex('uidx-stock-div-sym-date', '{{%stock_dividend}}', ['symbol', 'ex_date'], true);

        // 2. Tabel ringkasan dividen & metrik screener per saham
        $this->createTable('{{%stock_dividend_summary}}', [
            'id' => $this->primaryKey(),
            'stock_id' => $this->integer()->notNull()->unique(),
            'symbol' => $this->string(16)->notNull()->unique(),
            'company_name' => $this->string(255)->null(),
            'sector' => $this->string(64)->null(),
            'current_price' => $this->decimal(12, 4)->notNull()->defaultValue(0),
            'ttm_dividend' => $this->decimal(10, 4)->notNull()->defaultValue(0),
            'dividend_yield' => $this->decimal(8, 2)->notNull()->defaultValue(0),
            'payout_frequency' => $this->string(20)->notNull()->defaultValue('quarterly'),
            'payouts_per_year' => $this->integer()->notNull()->defaultValue(4),
            'last_ex_date' => $this->string(10)->null(),
            'last_amount' => $this->decimal(10, 4)->null(),
            'estimated_next_ex_date' => $this->string(10)->null(),
            'consecutive_years' => $this->integer()->notNull()->defaultValue(1),
            'safety_score' => $this->string(32)->notNull()->defaultValue('Moderate'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-div-sum-yield', '{{%stock_dividend_summary}}', ['dividend_yield']);
        $this->createIndex('idx-div-sum-freq', '{{%stock_dividend_summary}}', ['payout_frequency']);
        $this->createIndex('idx-div-sum-next-date', '{{%stock_dividend_summary}}', ['estimated_next_ex_date']);
        $this->createIndex('idx-div-sum-sector', '{{%stock_dividend_summary}}', ['sector']);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%stock_dividend_summary}}');
        $this->dropTable('{{%stock_dividend}}');
    }
}
