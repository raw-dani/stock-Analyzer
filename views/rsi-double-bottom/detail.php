<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var \app\models\Stock $stock */
/** @var array|null $pattern */
/** @var int $timeframe */
/** @var int $lookback */
/** @var float $tolerance */
/** @var int $rsiPeriod */
/** @var float $maxRsi */
/** @var array $timeframes */

use yii\bootstrap5\Html;

$this->title = 'RSI Double Bottom - ' . $stock->symbol;
$this->params['breadcrumbs'][] = ['label' => 'RSI Double Bottom Scanner', 'url' => ['/rsi-double-bottom/index']];
$this->params['breadcrumbs'][] = $stock->symbol;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">
        <?= Html::encode($stock->symbol) ?> - RSI Double Bottom Analysis
    </h1>
    <?= Html::a('Back to Scanner', ['/rsi-double-bottom/index'], ['class' => 'btn btn-outline-secondary']) ?>
</div>

<div class="card mb-4"><div class="card-body">
<?= Html::beginForm(['/rsi-double-bottom/detail'], 'get', ['class' => 'row g-3 align-items-end']) ?>
    <?= Html::hiddenInput('symbol', $stock->symbol) ?>
    <div class="col-md-3">
        <label class="form-label fw-bold">Timeframe</label>
        <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select']) ?>
    </div>
    <div class="col-md-2">
        <label class="form-label fw-bold">Lookback (candles)</label>
        <?= Html::input('number', 'lookback', $lookback, ['class' => 'form-control', 'min' => 20, 'max' => 300]) ?>
    </div>
    <div class="col-md-2">
        <label class="form-label fw-bold">Tolerance (pts)</label>
        <?= Html::input('number', 'tolerance', $tolerance, ['class' => 'form-control', 'min' => 0.5, 'max' => 10, 'step' => 0.5]) ?>
    </div>
    <div class="col-md-2">
        <label class="form-label fw-bold">RSI Period</label>
        <?= Html::input('number', 'rsiPeriod', $rsiPeriod, ['class' => 'form-control', 'min' => 2, 'max' => 50]) ?>
    </div>
    <div class="col-md-2">
        <label class="form-label fw-bold">Max RSI (lembah)</label>
        <?= Html::input('number', 'maxRsi', $maxRsi, ['class' => 'form-control', 'min' => 20, 'max' => 70, 'step' => 5]) ?>
    </div>
    <div class="col-md-1">
        <?= Html::submitButton('Analyze', ['class' => 'btn btn-primary w-100']) ?>
    </div>
<?= Html::endForm() ?>
</div></div>

<?php if ($pattern === null): ?>
<div class="alert alert-info">
    <h5>No RSI Double Bottom Pattern Detected</h5>
    <p>No RSI double bottom pattern was found for <?= Html::encode($stock->symbol) ?> with current settings.</p>
    <p class="small text-muted">Tips: coba perbesar lookback, longgarkan tolerance, atau turunkan maxRsi.</p>
</div>
<?php else: ?>
    <?= $this->render('_pattern_details', ['pattern' => $pattern]) ?>
<?php endif; ?>

<div class="card mt-4"><div class="card-header"><h5 class="mb-0">Stock Information</h5></div><div class="card-body">
<div class="row">
    <div class="col-md-3"><strong>Symbol:</strong> <?= Html::encode($stock->symbol) ?></div>
    <div class="col-md-3"><strong>Name:</strong> <?= Html::encode($stock->name) ?></div>
    <div class="col-md-3"><strong>Sector:</strong> <?= Html::encode($stock->sector ?? '-') ?></div>
    <div class="col-md-3"><strong>Exchange:</strong> <?= Html::encode($stock->exchange ?? '-') ?></div>
</div></div></div>

<?php if ($pattern && ($pattern['approximated'] ?? false)): ?>
<div class="alert alert-warning mt-3">
    <strong>⚠ Approximated Mode.</strong> Intraday data (1h) belum tersedia untuk simbol ini, jadi pola dihasilkan dari agregasi daily_price. Hasil belum akurat untuk timeframe 1H/2H/4H. 
    <a href="https://query1.finance.yahoo.com" target="_blank">Sync intraday</a> via <code>php yii data/sync-intraday <?= Html::encode($stock->symbol) ?></code>.
</div>
<?php endif; ?>
