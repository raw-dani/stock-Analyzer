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
use app\services\TripleBottomService;

ChartAsset::register($this);

$patternType = $patternType ?? ($pattern['pattern_type'] ?? TripleBottomService::PATTERN_ALL);
$patternTypes = $patternTypes ?? TripleBottomService::getPatternTypes();
$isIhs = ($pattern['pattern_type'] ?? '') === TripleBottomService::PATTERN_INVERSE_HS;
$isShakeout = ($pattern['pattern_type'] ?? '') === TripleBottomService::PATTERN_SHAKEOUT_LOW || !empty($pattern['is_shakeout_low']);
$patternTitle = $isIhs ? 'Inverted Head & Shoulders' : ($isShakeout ? 'Triple Bottom (Shakeout Low)' : 'Triple Bottom');
$this->title = $patternTitle . ' - ' . $stock->symbol;
$this->params['breadcrumbs'][] = ['label' => 'Reversal Scanner', 'url' => array_merge(['triple-bottom/index'], $filterParams)];
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
        <?php if ($pattern !== null): ?>
            <?php if ($isIhs): ?>
                <span class="badge" style="background-color: #6f42c1;"><?= \app\helpers\SvgHelper::icon('person-arms-up') ?> Inverted Head &amp; Shoulders</span>
            <?php elseif ($isShakeout): ?>
                <span class="badge bg-secondary text-white"><?= \app\helpers\SvgHelper::icon('lightning-charge') ?> Triple Bottom (Shakeout Low)</span>
            <?php else: ?>
                <span class="badge bg-primary"><?= \app\helpers\SvgHelper::icon('triangle-half') ?> Triple Bottom</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?= Html::a(\app\helpers\SvgHelper::icon('arrow-left') . ' Kembali ke Scanner', array_merge(['triple-bottom/index'], $filterParams), ['class' => 'btn btn-outline-secondary']) ?>
        <?= \app\widgets\BlogQuickPostWidget::widget([
            'symbol' => $stock->symbol,
            'stockName' => $stock->name,
            'timeFrame' => ($timeframe === 24 || $timeframe === 0 ? '1D' : "{$timeframe}H"),
            'strategyName' => 'triple-bottom',
            'analysisDate' => date('Y-m-d'),
            'targetElementId' => 'tb-chart',
            'metrics' => [
                'current_price' => $pattern['current_price'] ?? null,
                'neckline' => $pattern['neckline'] ?? null,
                'bottom1' => $pattern['bottom1'] ?? null,
                'bottom2' => $pattern['bottom2'] ?? null,
                'bottom3' => $pattern['bottom3'] ?? null,
                'pattern_type' => $patternTitle,
            ],
            'buttonClass' => 'btn btn-outline-warning text-dark btn-sm shadow-sm fw-bold',
        ]) ?>
    </div>
</div>

<!-- Form Parameter Deteksi Pola -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-light py-2">
        <strong class="small text-secondary"><?= \app\helpers\SvgHelper::icon('sliders') ?> Parameter Deteksi Pola <?= Html::encode($patternTitle) ?></strong>
    </div>
    <div class="card-body">
        <?= Html::beginForm(['triple-bottom/detail'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <?= Html::hiddenInput('symbol', $stock->symbol) ?>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Timeframe</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small fw-bold">Tipe Pola Dicari</label>
                <?= Html::dropDownList('patternType', $patternType, $patternTypes, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Lookback (candle)</label>
                <?= Html::input('number', 'lookback', $lookbackDays, ['class' => 'form-control form-control-sm', 'min' => 15, 'max' => 500]) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Toleransi (%)</label>
                <?= Html::input('number', 'tolerance', round($tolerancePercent, 1), ['class' => 'form-control form-control-sm', 'min' => 0.1, 'max' => 20, 'step' => 0.1]) ?>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small fw-bold">Neckline Min (%)</label>
                <?= Html::input('number', 'necklineMinDepth', round($necklineMinDepth * 100, 1), ['class' => 'form-control form-control-sm', 'min' => 0, 'max' => 50, 'step' => 0.1]) ?>
            </div>
            <div class="col-12 text-end">
                <?= Html::submitButton(\app\helpers\SvgHelper::icon('arrow-repeat') . ' Update Analisis', ['class' => 'btn btn-sm btn-primary px-3']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<?php if ($pattern === null): ?>
    <div class="alert alert-info shadow-sm">
        <h5><?= \app\helpers\SvgHelper::icon('info-circle-fill') ?> Pola Reversal Tidak Terdeteksi</h5>
        <p class="mb-0">Tidak ditemukan pola Triple Bottom atau Inverted Head &amp; Shoulders yang memenuhi syarat untuk <?= Html::encode($stock->symbol) ?> dengan parameter saat ini.</p>
    </div>
<?php else: ?>
    <?= $this->render('_pattern_details', ['pattern' => $pattern]) ?>
<?php endif; ?>

<!-- Chart Candlestick & Volume dengan Mark Points, Neckline & Targets -->
<div class="card mt-4 shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><?= \app\helpers\SvgHelper::icon('graph-up', ['class' => 'text-primary']) ?> Grafik Pola <?= Html::encode($patternTitle) ?> &amp; Volume (<?= Html::encode($stock->symbol) ?>)</h5>
        <small class="text-muted">Candlestick + Volume Akumulasi Dual-Grid</small>
    </div>
    <div class="card-body">
        <?php if (empty($candles)): ?>
            <p class="text-muted mb-0">Data candle tidak tersedia.</p>
        <?php else: ?>
            <div id="<?= $chartId ?>" style="height: 540px; width: 100%;"></div>
            <div class="d-flex flex-wrap gap-3 mt-2 small text-muted border-top pt-2">
                <?php if ($isIhs): ?>
                    <span><span class="badge bg-info text-dark">Bahu Kiri / Kanan</span> Titik Support Bahu</span>
                    <span><span class="badge text-white" style="background-color: #6f42c1;">Kepala (Head)</span> Lembah Terendah</span>
                <?php else: ?>
                    <span><span class="badge bg-info text-dark">L1 / L2 / L3</span> 3 Titik Lembah Support</span>
                <?php endif; ?>
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
                $pointDefs = $isIhs ? [
                    ['low1_date', 'low1_price', 'Bahu Kiri (LS)'],
                    ['low2_date', 'low2_price', 'Kepala (Head)'],
                    ['low3_date', 'low3_price', 'Bahu Kanan (RS)'],
                    ['peak1_date', 'peak1_price', 'Neckline Peak 1'],
                    ['peak2_date', 'peak2_price', 'Neckline Peak 2'],
                ] : [
                    ['low1_date', 'low1_price', 'L1 (Bottom 1)'],
                    ['low2_date', 'low2_price', 'L2 (Bottom 2)'],
                    ['low3_date', 'low3_price', 'L3 (Bottom 3)'],
                    ['peak1_date', 'peak1_price', 'Peak 1'],
                    ['peak2_date', 'peak2_price', 'Peak 2'],
                ];

                foreach ($pointDefs as [$dk, $pk, $label]) {
                    $idx = array_search($pattern[$dk] ?? null, $dates, true);
                    if ($idx !== false && $idx !== null) {
                        $isPeak = str_contains($label, 'Peak');
                        $isHead = str_contains($label, 'Head');
                        $markPoints[] = [
                            'name' => $label,
                            'coord' => [$dates[$idx], (float) $pattern[$pk]],
                            'value' => $label,
                            'itemStyle' => ['color' => $isPeak ? '#0d6efd' : ($isHead ? '#6f42c1' : '#0dcaf0')],
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
                    ['stop_loss', $isIhs ? 'Right Shoulder SL' : 'Swing SL', '#dc3545', 'solid'],
                ];
                if ($isIhs && isset($pattern['stop_loss_head'])) {
                    $levels[] = ['stop_loss_head', 'Head SL', '#6c757d', 'dotted'];
                }
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
