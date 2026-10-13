<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Tabel portofolio posisi saham yang sudah dibeli & target untung / averaging.
 */
class m261013_100000_create_portfolio_position_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%portfolio_position}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull()->defaultValue(100),
            'stock_id' => $this->integer()->notNull(),
            'buy_price' => $this->decimal(12, 4)->notNull(),
            'shares' => $this->decimal(12, 4)->notNull(),
            'target_profit_pct' => $this->decimal(6, 2)->notNull()->defaultValue(3.00),
            'buy_date' => $this->string(10)->null(),
            'notes' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-portfolio-user', '{{%portfolio_position}}', ['user_id']);
        $this->createIndex('idx-portfolio-stock', '{{%portfolio_position}}', ['stock_id']);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%portfolio_position}}');
    }
}
