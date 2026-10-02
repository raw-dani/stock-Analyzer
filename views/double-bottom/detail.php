<?php

/** @var yii\web\View $this */
/** @var \app\models\Stock $stock */
/** @var array|null $pattern */
/** @var int $timeframe */
/** @var int $lookback */
/** @var int $lookbackDays */
/** @var float $tolerance */
/** @var float $tolerancePercent */
/** @var int $minSeparation */
/** @var int $maxSeparation */
/** @var float $necklineMinDepth */
/** @var array $timeframes */
/** @var array $filterParams */
/** @var array $candles candle analisa: open/high/low/close/volume/date */

use yii\bootstrap5\Html;
use app\assets\ChartAsset;

ChartAsset::register($this);

$this->title = 'Double Bottom - ' . $stock->symbol;
$this->params['breadcrumbs'][] = ['label' => 'Double Bottom Scanner', 'url' => array_merge(['double-bottom/index'], $filterParams)];
$this->params['breadcrumbs'][] = $stock->symbol;
$lookbackDays = $lookbackDays ?? $lookback;
$tolerancePercent = $tolerancePercent ?? round($tolerance * 100, 1);
$chartId = 'db-chart';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">
        <?= Html::encode($stock->symbol) ?> - Double Bottom Analysis
    </h1>
    <?= Html::a('Kembali ke Scanner', array_merge(['double-bottom/index'], $filterParams), ['class' => 'btn btn-outline-secondary']) ?>
</div>

<!-- Filter (mempertahankan state filter scanner) -->
<div class="card mb-4">
    <div class="card-body">
        <?= Html::beginForm(['double-bottom/detail'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <?= Html::hiddenInput('symbol', $stock->symbol) ?>
            <div class="col-md-2">
                <label class="form-label fw-bold">Timeframe</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select']) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Lookback (candle)</label>
                <?= Html::input('number', 'lookback', $lookbackDays, ['class' => 'form-control', 'min' => 5, 'max' => 500]) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Toleransi (%)</label>
                <?= Html::input('number', 'tolerance', round($tolerancePercent, 1), ['class' => 'form-control', 'min' => 0.1, 'max' => 20, 'step' => 0.1]) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Separasi min</label>
                <?= Html::input('number', 'minSeparation', $minSeparation, ['class' => 'form-control', 'min' => 2]) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Separasi maks</label>
                <?= Html::input('number', 'maxSeparation', $maxSeparation, ['class' => 'form-control', 'min' => 2]) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Neckline min (%)</label>
                <?= Html::input('number', 'necklineMinDepth', round($necklineMinDepth * 100, 1), ['class' => 'form-control', 'min' => 0, 'max' => 50, 'step' => 0.1]) ?>
            </div>
            <div class="col-md-12">
                <?= Html::submitButton('Analisis', ['class' => 'btn btn-primary']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<?php if ($pattern === null): ?>
    <div class="alert alert-info">
        <h5>Pola Double Bottom Tidak Terdeteksi</h5>
        <p>Tidak ada pola double bottom untuk <?= Html::encode($stock->symbol) ?> dengan pengaturan saat ini.</p>
    </div>
<?php else: ?>
    <?= $this->render('_pattern_details', ['pattern' => $pattern]) ?>
<?php endif; ?>

<!-- Chart Candlestick -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Chart Pola <?= Html::encode($stock->symbol) ?></h5>
    </div>
    <div class="card-body">
        <?php if (empty($candles)): ?>
            <p class="text-muted mb-0">Data candle tidak tersedia.</p>
        <?php else: ?>
            <div id="<?= $chartId ?>" style="height: 420px;"></div>
            <small class="text-muted">Penanda: L1/L2 = Low 1 &amp; Low 2, N = neckline, T = target, SL = stop loss.</small>
            <?php
            $dates = array_column($candles, 'date');
            $ohlc = array_map(
                fn ($c) => [(float) $c['open'], (float) $c['close'], (float) $c['low'], (float) $c['high']],
                $candles
            );
            $vols = array_map(fn ($c) => (int) $c['volume'], $candles);
            $markPoints = [];
            if ($pattern !== null) {
                foreach ([
                    ['low1_date', 'low1_price', 'L1'],
                    ['low2_date', 'low2_price', 'L2'],
                    ['neckline_date', 'neckline', 'N'],
                ] as [$dk, $pk, $label]) {
                    $idx = array_search($pattern[$dk] ?? null, $dates, true);
                    if ($idx !== false && $idx !== null) {
                        $markPoints[] = ['name' => $label, 'coord' => [$dates[$idx], $pattern[$pk]], 'value' => $label];
                    }
                }
            }
            $markLines = [];
            if ($pattern !== null) {
                foreach (['neckline' => 'N', 'target_price' => 'T', 'stop_loss' => 'SL'] as $k => $label) {
                    if (isset($pattern[$k])) {
                        $markLines[] = ['name' => $label, 'yAxis' => $pattern[$k], 'label' => ['formatter' => $label . ': {c}']];
                    }
                }
            }
            $chartOption = [
                'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'cross']],
                'xAxis' => ['type' => 'category', 'data' => $dates, 'scale' => true],
                'yAxis' => ['scale' => true],
                'dataZoom' => [['type' => 'inside'], ['type' => 'slider']],
                'series' => [
                    [
                        'type' => 'candlestick',
                        'data' => $ohlc,
                        'markPoint' => ['data' => $markPoints],
                        'markLine' => ['data' => $markLines, 'symbol' => 'none'],
                    ],
                    ['type' => 'bar', 'name' => 'Volume', 'data' => $vols, 'yAxisIndex' => 0],
                ],
            ];
            $this->registerJs(
                'var el = document.getElementById(' . json_encode($chartId) . ');'
                . 'if (el && window.echarts) { var ch = echarts.init(el); ch.setOption('
                . json_encode($chartOption) . '); window.addEventListener("resize", function(){ ch.resize(); }); }'
            );
            ?>
        <?php endif; ?>
    </div>
</div>

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