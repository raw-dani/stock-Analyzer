<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap-env.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';
$config = require __DIR__ . '/config/console.php';
new yii\console\Application($config);

$form = new \app\models\form\ScanFilterForm();
$form->load(['mode' => 'daily', 'minChangePct' => '2.0', 'minBuyRatio' => '0.65'], '');
echo 'Form Valid: ' . ($form->validate() ? 'YES' : 'NO') . PHP_EOL;
if ($form->hasErrors()) {
    print_r($form->getErrors());
}

$presets = [
    'Day Breakout (+2% & Acc >= 65%)' => ['mode' => 'daily', 'minChangePct' => '2.0', 'minBuyRatio' => '0.65'],
    'High Volume Surge (>= 70% Buy)' => ['mode' => 'daily', 'minBuyRatio' => '0.70', 'minVolume' => '500000'],
    'Buy On Absorption (< $50)' => ['mode' => 'daily', 'minBuyRatio' => '0.65', 'maxPrice' => '50'],
];

$service = new \app\services\ScannerService();
foreach ($presets as $name => $params) {
    $f = new \app\models\form\ScanFilterForm();
    $f->load($params, '');
    $dp = $service->search($f);
    echo "Preset '{$name}': Lolos {$dp->getTotalCount()} saham\n";
    $first = $dp->getModels()[0] ?? null;
    if ($first) {
        $tot = $first->buy_volume + $first->sell_volume;
        $ratio = $tot > 0 ? round(($first->buy_volume / $tot) * 100, 1) : 0;
        $gain = $first->open > 0 ? round((($first->close - $first->open) / $first->open) * 100, 2) : 0;
        echo "   Contoh: {$first->stock->symbol} ({$first->date}) - Close: \${$first->close}, Gain: {$gain}%, Buy Ratio: {$ratio}%\n";
    }
}