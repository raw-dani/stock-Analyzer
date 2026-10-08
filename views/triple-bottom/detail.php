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
/** @var array $candles */

use yii\bootstrap5\Html;
use app\assets\ChartAsset;

ChartAsset::register($this);

$this->title = 'Triple Bottom - ' . $stock->symbol;
$this->params['breadcrumbs'][] = ['label' => 'Triple Bottom Scanner', 'url' => array_merge(['triple-bottom/index'], $filterParams)];
$this->params['breadcrumbs'][] = $stock->symbol;
$lookbackDays = $lookbackDays ?? $lookback;
$tolerancePercent = $tolerancePercent ?? round($tolerance * 100, 1);
$chartId = 'tb-chart';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">
            <?= Html::encode($stock->symbol) ?> <small class="text-muted fs-6"><?= Html::encode($stock->name) ?></small>
        </h1>
        <span class="badge bg-secondary"><?= Html::encode($stock->sector ?? 'Stock') ?></span>
        <span class="badge bg-light text-dark border"><?= Html::encode($stock->exchange ?? '-') ?></span>
    </div>
    <div class="d-flex gap-2">
        <?= Html::a('<i class="bi bi-arrow-left"></i> Kembali ke Scanner', array_merge(['triple-bottom/index'], $filterParams), ['class' => 'btn btn-outline-secondary']) ?>
    </div>
</div>

<!-- Form Parameter Deteksi Pola -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-light py-2">
        <strong class="small text-secondary"><i class="bi bi-sliders"></i> Parameter Deteksi Pola Triple Bottom</strong>
    </div>
    <div class="card-body">
        <?= Html::beginForm(['triple-bottom/detail'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <?= Html::hiddenInput('symbol', $stock->symbol) ?>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Timeframe</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Lookback (candle)</label>
                <?= Html::input('number', 'lookback', $lookbackDays, ['class' => 'form-control form-control-sm', 'min' => 15, 'max' => 500]) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Toleransi Level (%)</label>
                <?= Html::input('number', 'tolerance', round($tolerancePercent, 1), ['class' => 'form-control form-control-sm', 'min' => 0.1, 'max' => 20, 'step' => 0.1]) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Pemisahan Min</label>
                <?= Html::input('number', 'minSeparation', $minSeparation, ['class' => 'form-control form-control-sm', 'min' => 2]) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Pemisahan Maks</label>
                <?= Html::input('number', 'maxSeparation', $maxSeparation, ['class' => 'form-control form-control-sm', 'min' => 2]) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Neckline Min (%)</label>
                <?= Html::input('number', 'necklineMinDepth', round($necklineMinDepth * 100, 1), ['class' => 'form-control form-control-sm', 'min' => 0, 'max' => 50, 'step' => 0.1]) ?>
            </div>
            <div class="col-12 text-end">
                <?= Html::submitButton('<i class="bi bi-arrow-repeat"></i> Update Analisis', ['class' => 'btn btn-sm btn-primary px-3']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<?php if ($pattern === null): ?>
    <div class="alert alert-info shadow-sm">
        <h5><i class="bi bi-info-circle-fill"></i> Pola Triple Bottom Tidak Terdeteksi</h5>
        <p class="mb-0">Tidak ditemukan 3 titik lembah yang memenuhi syarat untuk <?= Html::encode($stock->symbol) ?> dengan parameter saat ini.</p>
    </div>
<?php else: ?>
    <?= $this->render('_pattern_details', ['pattern' => $pattern]) ?>
<?php endif; ?>

<!-- Chart Candlestick & Volume dengan Mark Points L1, L2, L3, Neckline & Targets -->
<div class="card mt-4 shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="bi bi-graph-up text-primary"></i> Grafik Pola Triple Bottom &amp; Volume (<?= Html::encode($stock->symbol) ?>)</h5>
        <small class="text-muted">Candlestick + Volume Akumulasi Dual-Grid</small>
    </div>
    <div class="card-body">
        <?php if (empty($candles)): ?>
            <p class="text-muted mb-0">Data candle tidak tersedia.</p>
        <?php else: ?>
            <div id="<?= $chartId ?>" style="height: 540px; width: 100%;"></div>
            <div class="d-flex flex-wrap gap-3 mt-2 small text-muted border-top pt-2">
                <span><span class="badge bg-info text-dark">L1 / L2 / L3</span> 3 Titik Lembah Support</span>
                <span><span class="badge bg-primary">Neckline</span> Garis Resistance Konfirmasi</span>
                <span><span class="badge bg-success">TP1 / TP2</span> Target Penjualan</span>
                <span><span class="badge bg-danger">SL</span> Batas Kerugian (Stop Loss)</span>
            </div>
            <?php
            $dates = array_column($candles, 'date');
            $ohlc = array_map(
                fn ($c) => [(float) $c['open'], (float) $c['close'], (float) $c['low'], (float) $c['high']],
                $candles
            );
            $volSeriesData = array_map(function ($c) {
                $isBull = (float) $c['close'] >= (float) $c['open'];
                return [
                    'value' => (int) $c['volume'],
                    'itemStyle' => ['color' => $isBull ? '#26a69a' : '#ef5350'],
                ];
            }, $candles);

            $markPoints = [];
            if ($pattern !== null) {
                foreach ([
                    ['low1_date', 'low1_price', 'L1 (Bottom 1)'],
                    ['low2_date', 'low2_price', 'L2 (Bottom 2)'],
                    ['low3_date', 'low3_price', 'L3 (Bottom 3)'],
                    ['peak1_date', 'peak1_price', 'Peak 1'],
                    ['peak2_date', 'peak2_price', 'Peak 2'],
                ] as [$dk, $pk, $label]) {
                    $idx = array_search($pattern[$dk] ?? null, $dates, true);
                    if ($idx !== false && $idx !== null) {
                        $isPeak = str_starts_with($label, 'Peak');
                        $markPoints[] = [
                            'name' => $label,
                            'coord' => [$dates[$idx], (float) $pattern[$pk]],
                            'value' => $label,
                            'itemStyle' => ['color' => $isPeak ? '#0d6efd' : '#0dcaf0'],
                        ];
                    }
                }
            }

            $markLines = [];
            if ($pattern !== null) {
                $levels = [
                    ['neckline', 'Neckline', '#0d6efd', 'solid'],
                    ['tp1_price', 'TP1 (50%)', '#20c997', 'dashed'],
                    ['tp2_price', 'TP2 (100%)', '#198754', 'solid'],
                    ['tp3_price', 'TP3 (161.8%)', '#0f5132', 'dotted'],
                    ['stop_loss_tight', 'Tight SL', '#fd7e14', 'dashed'],
                    ['stop_loss', 'Swing SL', '#dc3545', 'solid'],
                ];
                foreach ($levels as [$key, $label, $color, $type]) {
                    if (isset($pattern[$key])) {
                        $markLines[] = [
                            'name' => $label,
                            'yAxis' => (float) $pattern[$key],
                            'lineStyle' => ['color' => $color, 'type' => $type, 'width' => 1.5],
                            'label' => ['formatter' => $label . ': {c}', 'position' => 'end'],
                        ];
                    }
                }
            }

            $chartOption = [
                'tooltip' => [
                    'trigger' => 'axis',
                    'axisPointer' => ['type' => 'cross'],
                ],
                'axisPointer' => ['link' => [['xAxisIndex' => 'all']]],
                'grid' => [
                    ['left' => '50', 'right' => '50', 'top' => '30', 'height' => '56%'],
                    ['left' => '50', 'right' => '50', 'top' => '70%', 'height' => '18%'],
                ],
                'xAxis' => [
                    [
                        'type' => 'category',
                        'data' => $dates,
                        'scale' => true,
                        'boundaryGap' => false,
                        'axisLine' => ['onZero' => false],
                        'splitLine' => ['show' => false],
                    ],
                    [
                        'type' => 'category',
                        'gridIndex' => 1,
                        'data' => $dates,
                        'scale' => true,
                        'boundaryGap' => false,
                        'axisLine' => ['onZero' => false],
                        'axisTick' => ['show' => false],
                        'splitLine' => ['show' => false],
                        'axisLabel' => ['show' => false],
                    ],
                ],
                'yAxis' => [
                    ['scale' => true, 'splitArea' => ['show' => true]],
                    ['scale' => true, 'gridIndex' => 1, 'splitNumber' => 2, 'axisLabel' => ['show' => false], 'axisLine' => ['show' => false], 'axisTick' => ['show' => false], 'splitLine' => ['show' => false]],
                ],
                'dataZoom' => [
                    ['type' => 'inside', 'xAxisIndex' => [0, 1], 'start' => 10, 'end' => 100],
                    ['show' => true, 'xAxisIndex' => [0, 1], 'type' => 'slider', 'bottom' => '5', 'start' => 10, 'end' => 100],
                ],
                'series' => [
                    [
                        'name' => 'Price',
                        'type' => 'candlestick',
                        'data' => $ohlc,
                        'itemStyle' => [
                            'color' => '#26a69a',
                            'color0' => '#ef5350',
                            'borderColor' => '#26a69a',
                            'borderColor0' => '#ef5350',
                        ],
                        'markPoint' => ['data' => $markPoints],
                        'markLine' => ['data' => $markLines, 'symbol' => 'none'],
                    ],
                    [
                        'name' => 'Volume',
                        'type' => 'bar',
                        'xAxisIndex' => 1,
                        'yAxisIndex' => 1,
                        'data' => $volSeriesData,
                    ],
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
