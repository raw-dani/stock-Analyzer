<?php

/**
 * Generator fixture CSV sintetis deterministik (task 1.9).
 * Jalankan: php scripts/generate-fixture.php
 *
 * Simbol dengan seed ganjil diberi "akselerasi" volume & tren naik di 15 hari
 * terakhir — berguna untuk menguji signal engine (harus menghasilkan skor tinggi).
 */

$symbols = [
    'NVDA' => ['price' => 180.0, 'seed' => 7, 'name' => 'NVIDIA Corporation', 'exchange' => 'NASDAQ', 'sector' => 'Technology'],
    'AAPL' => ['price' => 220.0, 'seed' => 12, 'name' => 'Apple Inc.', 'exchange' => 'NASDAQ', 'sector' => 'Technology'],
    'XOM' => ['price' => 110.0, 'seed' => 23, 'name' => 'Exxon Mobil Corporation', 'exchange' => 'NYSE', 'sector' => 'Energy'],
];

$dir = __DIR__ . '/../data/csv';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

foreach ($symbols as $sym => $cfg) {
    mt_srand($cfg['seed']);
    $f = fopen("$dir/$sym.csv", 'w');
    fwrite($f, "date,open,high,low,close,volume\n");
    $price = $cfg['price'];
    $d = strtotime('2026-01-05'); // senin
    for ($i = 0; $i < 120; $i++) {
        if ((int) date('N', $d) > 5) {
            $d = strtotime('+3 days', $d); // skip weekend
        }
        $change = mt_rand(-250, 300) / 10000;
        $open = $price;
        $close = round($price * (1 + $change), 2);
        $high = round(max($open, $close) * (1 + mt_rand(0, 120) / 10000), 2);
        $low = round(min($open, $close) * (1 - mt_rand(0, 120) / 10000), 2);
        $vol = mt_rand(3000000, 60000000);
        if ($i >= 105 && $cfg['seed'] % 2 === 1) {
            $vol = (int) ($vol * 2.2);
            $close = round($close * 1.002, 2);
        }
        fwrite($f, date('Y-m-d', $d) . ',' . $open . ',' . $high . ',' . $low . ',' . $close . ',' . $vol . "\n");
        $price = $close;
        $d = strtotime('+1 day', $d);
    }
    fclose($f);
    echo "$sym fixture written ({$cfg['name']})\n";
}
