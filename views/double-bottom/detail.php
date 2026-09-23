<?php

/** @var yii\web\View $this */
/** @var \app\models\Stock $stock */
/** @var array|null $pattern */
/** @var int $timeframe */
/** @var int $lookbackDays */
/** @var float $tolerance */
/** @var array $timeframes */

use yii\bootstrap5\Html;

$this->title = 'Double Bottom - ' . $stock->symbol;
$this->params['breadcrumbs'][] = ['label' => 'Double Bottom Scanner', 'url' => ['double-bottom/index']];
$this->params['breadcrumbs'][] = $stock->symbol;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">
        <?= Html::encode($stock->symbol) ?> - Double Bottom Analysis
    </h1>
    <?= Html::a('Back to Scanner', ['double-bottom/index'], ['class' => 'btn btn-outline-secondary']) ?>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <?= Html::beginForm(['double-bottom/detail'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <?= Html::hiddenInput('symbol', $stock->symbol) ?>
            <div class="col-md-3">
                <label class="form-label fw-bold">Timeframe</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select']) ?>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Lookback (candles)</label>
                <?= Html::input('number', 'lookback', $lookbackDays, ['class' => 'form-control', 'min' => 5, 'max' => 500]) ?>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Tolerance (%)</label>
                <?= Html::input('number', 'tolerance', round($tolerance * 100, 1), ['class' => 'form-control', 'min' => 0.1, 'max' => 20, 'step' => 0.1]) ?>
            </div>
            <div class="col-md-3">
                <?= Html::submitButton('Analyze', ['class' => 'btn btn-primary w-100']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<?php if ($pattern === null): ?>
    <div class="alert alert-info">
        <h5>No Double Bottom Pattern Detected</h5>
        <p>No double bottom pattern was found for <?= Html::encode($stock->symbol) ?> with current settings.</p>
    </div>
<?php else: ?>
    <?= $this->render('_pattern_details', ['pattern' => $pattern]) ?>
<?php endif; ?>

<!-- Stock Info -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Stock Information</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong>Symbol:</strong> <?= Html::encode($stock->symbol) ?></div>
            <div class="col-md-3"><strong>Name:</strong> <?= Html::encode($stock->name) ?></div>
            <div class="col-md-3"><strong>Sector:</strong> <?= Html::encode($stock->sector ?? '-') ?></div>
            <div class="col-md-3"><strong>Exchange:</strong> <?= Html::encode($stock->exchange ?? '-') ?></div>
        </div>
    </div>
</div>