<?php

/**
 * Smoke test schema & AR models (Modul 1 verifikasi).
 * Jalankan: php scripts/verify-schema.php
 */

define('YII_ENV', 'dev');
define('YII_DEBUG', true);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/console.php';
new yii\console\Application($config);

$db = Yii::$app->db;
$tables = $db->schema->getTableNames();
echo "Tables: " . implode(', ', $tables) . "\n\n";

$expected = ['user', 'stock', 'daily_price', 'weekly_analysis', 'signal', 'watchlist', 'watchlist_item', 'alert', 'alert_log', 'backtest_run', 'backtest_trade', 'migration'];
$missing = array_diff($expected, $tables);
if ($missing) {
    echo "MISSING TABLES: " . implode(', ', $missing) . "\n";
    exit(1);
}
echo "All expected tables present.\n";

// Smoke test AR: insert Stock + DailyPrice + WeeklyAnalysis
app\models\Stock::deleteAll(['symbol' => 'TEST']); // bersihkan sisa run sebelumnya
$stock = new app\models\Stock();
$stock->symbol = 'TEST';
$stock->name = 'Test Company';
$stock->exchange = 'NASDAQ';
if (!$stock->save()) {
    echo "Stock save FAILED: " . json_encode($stock->errors) . "\n";
    exit(1);
}

$bar = new app\models\DailyPrice();
$bar->stock_id = $stock->id;
$bar->date = '2026-08-03';
$bar->open = 100;
$bar->high = 105;
$bar->low = 98;
$bar->close = 104;
$bar->volume = 10_000_000;
$bar->buy_volume = 8_000_000;
$bar->sell_volume = 2_000_000;
$bar->save() or exit("DailyPrice save FAILED\n");

$weekly = new app\models\WeeklyAnalysis();
$weekly->stock_id = $stock->id;
$weekly->week_start = '2026-08-03';
$weekly->buy_volume = 8_000_000;
$weekly->sell_volume = 2_000_000;
$weekly->buy_ratio = 0.8;
$weekly->sell_ratio = 0.2;
$weekly->buy_sell_ratio = 4.0;
$weekly->close_price = 104;
$weekly->score = 87;
$weekly->signal = app\models\WeeklyAnalysis::SIGNAL_STRONG_BUY;
$weekly->save() or exit("WeeklyAnalysis save FAILED\n");

// relasi
$loaded = app\models\Stock::find()->where(['symbol' => 'TEST'])->with(['dailyPrices', 'weeklyAnalyses'])->one();
assert(count($loaded->dailyPrices) === 1);
assert(count($loaded->weeklyAnalyses) === 1);
assert($loaded->weeklyAnalyses[0]->stock->symbol === 'TEST');

// unique constraint: duplicate harus gagal
$dup = new app\models\DailyPrice();
$dup->stock_id = $stock->id;
$dup->date = '2026-08-03';
$dup->open = 1;
$dup->high = 1;
$dup->low = 1;
$dup->close = 1;
$dup->volume = 1;
try {
    $dup->save();
    echo "WARNING: unique constraint tidak berjalan!\n";
    exit(1);
} catch (\yii\db\IntegrityException $e) {
    echo "Unique constraint (stock_id+date) works.\n";
}

// validasi rules
$bad = new app\models\WeeklyAnalysis();
$bad->stock_id = $stock->id;
$bad->week_start = '2026-08-03'; // duplicate
$bad->close_price = 1;
$bad->score = 200; // invalid
$bad->signal = 'NOT_A_SIGNAL'; // invalid
$bad->save();
echo "Validation errors captured: score=" . (isset($bad->errors['score']) ? 'YES' : 'NO')
    . " signal=" . (isset($bad->errors['signal']) ? 'YES' : 'NO') . "\n";

// cleanup — di SQLite FK di-skip (addFkCompat), jadi hapus manual
$bar::deleteAll(['stock_id' => $stock->id]);
$weekly::deleteAll(['stock_id' => $stock->id]);
$stock->delete();
echo "Delete manual OK (remaining daily_price rows: " . app\models\DailyPrice::find()->where(['stock_id' => $stock->id])->count() . ")\n";
echo "\nALL SMOKE TESTS PASSED\n";
