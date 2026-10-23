<?php

declare(strict_types=1);

use yii\db\Migration;

require_once __DIR__ . '/ForeignKeyAwareTrait.php';

/**
 * Migrasi tabel blog_post dan site_setting untuk fitur Blog Info Saham AI Bilingual & SEO Management.
 */
class m261023_190000_create_blog_and_site_settings_tables extends Migration
{
    use ForeignKeyAwareTrait;

    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        // 1. Tabel site_setting untuk Pengaturan SEO, Webmaster, Google Analytics, GTM, & OpenAI
        if ($this->db->getTableSchema('{{%site_setting}}') === null) {
            $this->createTable('{{%site_setting}}', [
                'id' => $this->primaryKey(),
                'category' => $this->string(64)->notNull()->defaultValue('seo'), // 'seo', 'analytics', 'ai', 'general'
                'setting_key' => $this->string(128)->notNull()->unique(),
                'setting_value' => $this->text()->null(),
                'description' => $this->string(255)->null(),
                'updated_at' => $this->integer()->notNull(),
            ], $tableOptions);

            $this->createIndex('idx_setting_category', '{{%site_setting}}', 'category');

            // Seed default site settings
            $now = time();
            $defaultSettings = [
                ['seo', 'google_site_verification', '', 'Google Search Console Verification Meta Tag', $now],
                ['seo', 'bing_site_verification', '', 'Bing Webmaster Tools Verification Meta Tag', $now],
                ['seo', 'default_meta_keywords', 'analisa saham, rekomendasi saham, teknikal saham, trading plan, candlestick, double bottom', 'Default SEO Meta Keywords', $now],
                ['analytics', 'ga4_measurement_id', '', 'Google Analytics 4 Measurement ID (cth: G-XXXXXXXXXX)', $now],
                ['analytics', 'gtm_container_id', '', 'Google Tag Manager Container ID (cth: GTM-XXXXXXX)', $now],
                ['analytics', 'custom_head_scripts', '', 'Custom Scripts di dalam tag <head> (Meta Pixel, Clarity, dll)', $now],
                ['analytics', 'custom_body_scripts', '', 'Custom Scripts sebelum penutup tag </body>', $now],
                ['ai', 'openai_api_key', '', 'OpenAI API Secret Key (sk-...) untuk generate analisa blog otomatis', $now],
                ['ai', 'openai_model', 'gpt-6-luna', 'OpenAI Model yang digunakan (cth: gpt-6-luna, gpt-5.6-luna, gpt-5.4-nano, gpt-4o)', $now],
            ];

            foreach ($defaultSettings as $setting) {
                $this->insert('{{%site_setting}}', [
                    'category' => $setting[0],
                    'setting_key' => $setting[1],
                    'setting_value' => $setting[2],
                    'description' => $setting[3],
                    'updated_at' => $setting[4],
                ]);
            }
        }

        // 2. Tabel blog_post untuk Artikel Blog Info Saham Dwibahasa
        if ($this->db->getTableSchema('{{%blog_post}}') === null) {
            $this->createTable('{{%blog_post}}', [
                'id' => $this->primaryKey(),
                'stock_id' => $this->integer()->null(),
                'symbol' => $this->string(16)->notNull(),
                'time_frame' => $this->string(16)->notNull()->defaultValue('1D'),
                'strategy_name' => $this->string(64)->notNull()->defaultValue('general'),
                'analysis_date' => $this->date()->notNull(),
                'slug_id' => $this->string(255)->notNull()->unique(),
                'slug_en' => $this->string(255)->notNull()->unique(),
                'title_id' => $this->string(255)->notNull(),
                'title_en' => $this->string(255)->notNull(),
                'excerpt_id' => $this->text()->null(),
                'excerpt_en' => $this->text()->null(),
                'content_id' => 'LONGTEXT NOT NULL',
                'content_en' => 'LONGTEXT NOT NULL',
                'featured_image' => $this->string(255)->null(),
                'trading_plan' => $this->json()->null(),
                'tags' => $this->string(255)->null(),
                'meta_title_id' => $this->string(255)->null(),
                'meta_title_en' => $this->string(255)->null(),
                'meta_desc_id' => $this->string(320)->null(),
                'meta_desc_en' => $this->string(320)->null(),
                'author_id' => $this->integer()->notNull(),
                'status' => $this->string(20)->notNull()->defaultValue('published'),
                'views_count' => $this->integer()->notNull()->defaultValue(0),
                'created_at' => $this->integer()->notNull(),
                'updated_at' => $this->integer()->notNull(),
            ], $tableOptions);

            $this->createIndex(
                'idx_blog_symbol_tf_strat_date',
                '{{%blog_post}}',
                ['symbol', 'time_frame', 'strategy_name', 'analysis_date']
            );
            $this->createIndex('idx_blog_status_created', '{{%blog_post}}', ['status', 'created_at']);
            $this->createIndex('idx_blog_slug_id', '{{%blog_post}}', 'slug_id');
            $this->createIndex('idx_blog_slug_en', '{{%blog_post}}', 'slug_en');
        }

        // Foreign keys (via ForeignKeyAwareTrait)
        $this->addFkCompat(
            'fk_blog_post_author',
            '{{%blog_post}}',
            'author_id',
            '{{%user}}',
            'id',
            'CASCADE'
        );

        $this->addFkCompat(
            'fk_blog_post_stock',
            '{{%blog_post}}',
            'stock_id',
            '{{%stock}}',
            'id',
            'SET NULL'
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%blog_post}}');
        $this->dropTable('{{%site_setting}}');
    }
}
