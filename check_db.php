<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';
$db = new PDO('sqlite:' . __DIR__ . '/app/data/stocks.db');
echo 'stock: ' . $db->query('SELECT COUNT(*) FROM stock')->fetchColumn() . PHP_EOL;
echo 'daily_price: ' . $db->query('SELECT COUNT(*) FROM daily_price')->fetchColumn() . PHP_EOL;
echo 'weekly_analysis: ' . $db->query('SELECT COUNT(*) FROM weekly_analysis')->fetchColumn() . PHP_EOL;
echo 'signal: ' . $db->query('SELECT COUNT(*) FROM signal')->fetchColumn() . PHP_EOL;
echo 'sample daily: ' . PHP_EOL;
$r = $db->query('SELECT symbol, date, close, volume FROM daily_price LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
foreach ($r as $row) { echo '  ' . $row['symbol'] . ' ' . $row['date'] . ' close=' . $row['close'] . ' vol=' . $row['volume'] . PHP_EOL; }