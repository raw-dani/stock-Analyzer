<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Migrasi untuk menambahkan kolom category pada tabel blog_post,
 * dengan nilai default nama strategi teknikal (Title Case).
 */
class m261023_201500_add_category_to_blog_post_table extends Migration
{
    public function safeUp(): void
    {
        $tableSchema = $this->db->getTableSchema('{{%blog_post}}');
        if ($tableSchema !== null && !isset($tableSchema->columns['category'])) {
            $this->addColumn(
                '{{%blog_post}}',
                'category',
                $this->string(64)->notNull()->defaultValue('General')->after('strategy_name')
            );

            $this->createIndex('idx_blog_category', '{{%blog_post}}', 'category');

            // Populate kategori pada data yang sudah ada berdasarkan strategy_name
            $posts = (new \yii\db\Query())
                ->select(['id', 'strategy_name'])
                ->from('{{%blog_post}}')
                ->all($this->db);

            $map = [
                'rsi-double-bottom' => 'RSI Double Bottom',
                'double-bottom' => 'Double Bottom',
                'triple-bottom' => 'Triple Bottom',
                'moving-average' => 'Moving Average',
                'bollinger-band' => 'Bollinger Bands',
                'bollinger-bands' => 'Bollinger Bands',
                'bullish-pattern' => 'Bullish Pattern',
                'bullish-patterns' => 'Bullish Pattern',
                'volume-breakout' => 'Volume Breakout',
                'general' => 'Analisis Saham',
            ];

            foreach ($posts as $post) {
                $strat = strtolower(trim((string) $post['strategy_name']));
                $cat = $map[$strat] ?? ucwords(str_replace(['-', '_'], ' ', $strat));
                $this->update('{{%blog_post}}', ['category' => $cat], ['id' => $post['id']]);
            }
        }
    }

    public function safeDown(): void
    {
        $tableSchema = $this->db->getTableSchema('{{%blog_post}}');
        if ($tableSchema !== null && isset($tableSchema->columns['category'])) {
            $this->dropIndex('idx_blog_category', '{{%blog_post}}');
            $this->dropColumn('{{%blog_post}}', 'category');
        }
    }
}
