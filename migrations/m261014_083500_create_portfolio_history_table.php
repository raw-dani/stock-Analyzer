<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%portfolio_history}}`.
 */
class m261014_083500_create_portfolio_history_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%portfolio_history}}', [
            'id' => $this->primaryKey(),
            'position_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'action_type' => $this->string(32)->notNull(),
            'scenario_name' => $this->string(120),
            'old_price' => $this->decimal(12, 4),
            'new_price' => $this->decimal(12, 4),
            'old_shares' => $this->decimal(12, 4),
            'added_shares' => $this->decimal(12, 4),
            'new_shares' => $this->decimal(12, 4),
            'added_price' => $this->decimal(12, 4),
            'added_cost' => $this->decimal(14, 4),
            'notes' => $this->text(),
            'created_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-portfolio-history-pos', '{{%portfolio_history}}', ['position_id']);
        $this->createIndex('idx-portfolio-history-user', '{{%portfolio_history}}', ['user_id']);
        $this->createIndex('idx-portfolio-history-created', '{{%portfolio_history}}', ['created_at']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%portfolio_history}}');
    }
}
