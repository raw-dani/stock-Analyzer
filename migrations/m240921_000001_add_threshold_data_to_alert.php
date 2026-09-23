<?php

declare(strict_types=1);

use yii\db\Migration;

require_once __DIR__ . '/ForeignKeyAwareTrait.php';

/**
 * Add threshold_data JSON column to alert table for RSI Double Bottom parameters.
 */
class m240921_000001_add_threshold_data_to_alert extends Migration
{
    use ForeignKeyAwareTrait;

    public function safeUp(): void
    {
        $this->addColumn('{{%alert}}', 'threshold_data', $this->json()->null()->defaultValue(null)->after('threshold'));
        $this->alterColumn('{{%alert}}', 'condition_type', $this->string(32)->notNull()->comment('buy_ratio|volume_growth|rvol|score|rsi_double_bottom'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%alert}}', 'threshold_data');
        $this->alterColumn('{{%alert}}', 'condition_type', $this->string(32)->notNull()->comment('buy_ratio|volume_growth|rvol|score'));
    }
}