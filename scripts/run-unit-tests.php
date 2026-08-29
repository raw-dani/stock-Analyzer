<?php

/**
 * Unit test VolumeAnalyzerService & SignalEngineService (task 3.8, 4.5).
 * Jalankan: php scripts/run-unit-tests.php
 * (Codeception wiring menyusul; runner ini deterministik & cepat tanpa DB.)
 */

define('YII_ENV', 'dev');
define('YII_DEBUG', true);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

new yii\console\Application(require __DIR__ . '/../config/console.php');

use app\services\SignalEngineService;
use app\services\VolumeAnalyzerService;
use app\services\dto\WeeklyIndicators;

$pass = 0;
$fail = 0;

function check(string $name, bool $cond): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  ok  {$name}\n";
    } else {
        $fail++;
        echo "FAIL  {$name}\n";
    }
}

echo "== Metode A: klasifikasi bar ==\n";
$c = VolumeAnalyzerService::classifyBar(100.0, 104.0, 10_000_000); // naik
check('close > open -> semua buy', $c['buy'] === 10_000_000.0 && $c['sell'] === 0.0);

$c = VolumeAnalyzerService::classifyBar(104.0, 101.0, 8_000_000); // turun
check('close < open -> semua sell', $c['buy'] === 0.0 && $c['sell'] === 8_000_000.0);

$c = VolumeAnalyzerService::classifyBar(100.0, 100.0, 6_000_000); // doji
check('doji -> 50/50', $c['buy'] === 3_000_000.0 && $c['sell'] === 3_000_000.0);

echo "== mondayOf ==\n";
check('senin tetap senin', VolumeAnalyzerService::mondayOf('2026-01-05') === '2026-01-05');
check('rabu -> senin sama minggu', VolumeAnalyzerService::mondayOf('2026-01-07') === '2026-01-05');
check('minggu -> senin 6 hari sebelumnya', VolumeAnalyzerService::mondayOf('2026-01-11') === '2026-01-05');

echo "== classify: boundary matrix ==\n";
check('79 -> BUY', SignalEngineService::classify(79) === 'BUY');
check('80 -> STRONG_BUY', SignalEngineService::classify(80) === 'STRONG_BUY');
check('64 -> WATCH', SignalEngineService::classify(64) === 'WATCH');
check('65 -> BUY', SignalEngineService::classify(65) === 'BUY');
check('49 -> WEAK', SignalEngineService::classify(49) === 'WEAK');
check('50 -> WATCH', SignalEngineService::classify(50) === 'WATCH');
check('34 -> SELL', SignalEngineService::classify(34) === 'SELL');
check('35 -> WEAK', SignalEngineService::classify(35) === 'WEAK');
check('0 -> SELL', SignalEngineService::classify(0) === 'SELL');
check('100 -> STRONG_BUY', SignalEngineService::classify(100) === 'STRONG_BUY');

echo "== evaluate: kombinasi threshold ==\n";
$engine = new SignalEngineService();

function makeInd(array $o = []): WeeklyIndicators
{
    $get = fn (string $k, $d) => array_key_exists($k, $o) ? $o[$k] : $d;
    return new WeeklyIndicators(
        stockId: 1,
        weekStart: '2026-08-03',
        buyVolume: $get('buy', 700_000),
        sellVolume: $get('sell', 300_000),
        buyRatio: $get('buyRatio', 0.70),
        sellRatio: $get('sellRatio', 0.30),
        buySellRatio: $get('buySellRatio', 2.33),
        volumeGrowth: $get('growth', 0.50),
        rvol: $get('rvol', 2.0),
        ma20: $get('ma20', 100.0),
        ma50: $get('ma50', 95.0),
        closePrice: $get('close', 105.0),
    );
}

// semua kondisi maksimal: 30+25+20+15+10 = 100
$r = $engine->evaluate(makeInd());
check('max condition -> 100 STRONG_BUY', $r->score === 100 && $r->signal === 'STRONG_BUY');
check('reasons count = 5', count($r->reasons) === 5);

// semua minimum: 10+10+10+0+0 = 30
$r = $engine->evaluate(makeInd(['buyRatio' => 0.55, 'growth' => 0.10, 'rvol' => 1.2, 'ma20' => 110.0, 'ma50' => 110.0, 'close' => 105.0]));
check('min condition -> 30 SELL', $r->score === 30 && $r->signal === 'SELL');

// tanpa growth & rvol (minggu pertama): hanya buy ratio 30 + trend 25 = 55 WATCH
$r = $engine->evaluate(makeInd(['growth' => null, 'rvol' => null, 'ma20' => 100.0, 'ma50' => 95.0]));
check('no growth/rvol -> 55 WATCH', $r->score === 55 && $r->signal === 'WATCH');

// di bawah threshold terendah semua: 0 SELL
$r = $engine->evaluate(makeInd(['buyRatio' => 0.50, 'growth' => 0.05, 'rvol' => 1.0, 'ma20' => 110.0, 'ma50' => 110.0]));
check('below all -> 0 SELL', $r->score === 0 && $r->signal === 'SELL');

// boundary tepat: buyRatio 0.5499 tidak dapat poin
$r = $engine->evaluate(makeInd(['buyRatio' => 0.5499]));
check('buyRatio 54.99% -> tanpa poin buy pressure', !str_contains(implode(' ', $r->reasons), 'Buy ratio'));

echo "\nRESULT: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
