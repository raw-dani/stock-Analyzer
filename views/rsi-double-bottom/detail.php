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
use app\assets\ChartAsset;

ChartAsset::register($this);

$this->title = 'RSI Double Bottom - ' . $stock->symbol;
$this->params['breadcrumbs'][] = ['label' => 'RSI Double Bottom Scanner', 'url' => ['/rsi-double-bottom/index']];
$this->params['breadcrumbs'][] = $stock->symbol;

$chartId = 'rsi-db-chart';
$candles = $pattern['candles'] ?? [];
$rsiSeries = $pattern['rsi_series'] ?? [];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">
            <?= Html::encode($stock->symbol) ?> <small class="text-muted fs-6 fw-normal"><?= Html::encode($stock->name) ?></small>
        </h1>
        <div class="d-flex align-items-center gap-2 mt-1">
            <span class="badge bg-secondary"><?= Html::encode($stock->sector ?? 'Stock') ?></span>
            <span class="badge bg-light text-dark border"><?= Html::encode($stock->exchange ?? '-') ?></span>
            <span class="badge bg-info text-dark">TF: <?= Html::encode($timeframes[$timeframe] ?? "{$timeframe}H") ?></span>
            <?php if ($pattern !== null): ?>
                <span class="badge <?= $pattern['action_badge'] ?? 'bg-primary' ?> px-2 py-1">
                    <?= Html::encode($pattern['action_label'] ?? 'PATTERN DETECTED') ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?= Html::a('<i class="bi bi-arrow-left"></i> Kembali ke Scanner', ['/rsi-double-bottom/index', 'timeframe' => $timeframe, 'lookback' => $lookback, 'tolerance' => $tolerance, 'rsiPeriod' => $rsiPeriod, 'maxRsi' => $maxRsi], ['class' => 'btn btn-outline-secondary']) ?>
    </div>
</div>

<!-- Parameter Tuning Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light py-2">
        <strong class="small text-secondary"><i class="bi bi-sliders"></i> Parameter Deteksi Pola RSI</strong>
    </div>
    <div class="card-body p-3">
        <?= Html::beginForm(['/rsi-double-bottom/detail'], 'get', ['class' => 'row g-2 align-items-end']) ?>
            <?= Html::hiddenInput('symbol', $stock->symbol) ?>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Timeframe</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Lookback (candles)</label>
                <?= Html::input('number', 'lookback', $lookback, ['class' => 'form-control form-control-sm', 'min' => 20, 'max' => 300]) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Tolerance (pts RSI)</label>
                <?= Html::input('number', 'tolerance', $tolerance, ['class' => 'form-control form-control-sm', 'min' => 0.5, 'max' => 10, 'step' => 0.5]) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">RSI Period</label>
                <?= Html::input('number', 'rsiPeriod', $rsiPeriod, ['class' => 'form-control form-control-sm', 'min' => 2, 'max' => 50]) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Max RSI Lembah</label>
                <?= Html::input('number', 'maxRsi', $maxRsi, ['class' => 'form-control form-control-sm', 'min' => 20, 'max' => 70, 'step' => 5]) ?>
            </div>
            <div class="col-md-2 col-12 text-end">
                <?= Html::submitButton('<i class="bi bi-arrow-repeat"></i> Update Analisis', ['class' => 'btn btn-sm btn-primary w-100']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<?php if ($pattern === null): ?>
    <div class="alert alert-info shadow-sm py-4 text-center">
        <i class="bi bi-info-circle display-6 d-block mb-2 text-info"></i>
        <h5 class="fw-bold">Pola RSI Double Bottom Tidak Ditemukan</h5>
        <p class="text-muted mb-0">Tidak ada dua lembah RSI yang memenuhi kriteria toleransi pada rentang candle saat ini.</p>
        <small class="text-muted">Tips: Coba perbesar nilai lookback atau longgarkan tolerance pada form di atas.</small>
    </div>
<?php else: ?>
    <!-- Pattern Details & Trade Execution Plan -->
    <?= $this->render('_pattern_details', ['pattern' => $pattern]) ?>

    <!-- Dual-Grid Interactive ECharts (Candlestick + RSI Oscillator) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-graph-up text-primary me-1"></i> Grafik Interaktif Harga &amp; Osilator RSI(14)
            </h5>
            <small class="text-muted">Sinkronisasi Candlestick + Level Pola RSI</small>
        </div>
        <div class="card-body">
            <?php if (empty($candles)): ?>
                <p class="text-muted mb-0 text-center py-4">Data candle tidak tersedia untuk grafik.</p>
            <?php else: ?>
                <div id="<?= $chartId ?>" style="height: 560px; width: 100%;"></div>
                <div class="d-flex flex-wrap gap-3 mt-2 small text-muted border-top pt-2">
                    <span><span class="badge bg-primary">N</span> Neckline Price Resistance</span>
                    <span><span class="badge bg-success">TP1 / TP2 / TP3</span> Target Keuntungan</span>
                    <span><span class="badge bg-danger">SL</span> Level Stop Loss</span>
                    <span><span class="badge bg-info text-dark">L1 / L2</span> Lembah RSI (Oversold)</span>
                    <span><span class="badge bg-warning text-dark">Neckline RSI</span> Batas Breakout RSI</span>
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

                // Format RSI series data aligned with dates
                $rsiData = [];
                foreach ($dates as $idx => $d) {
                    $rsiVal = $rsiSeries[$idx] ?? null;
                    $rsiData[] = $rsiVal !== null ? round((float) $rsiVal, 2) : null;
                }

                // Price MarkPoints
                $priceMarkPoints = [];
                // Marklines for Price Grid (Top)
                $priceMarkLines = [
                    [
                        'name' => 'Neckline Price',
                        'yAxis' => (float) $pattern['neckline_price'],
                        'lineStyle' => ['color' => '#0d6efd', 'type' => 'solid', 'width' => 1.5],
                        'label' => ['formatter' => 'Neckline: ${c}', 'position' => 'end'],
                    ],
                    [
                        'name' => 'TP1',
                        'yAxis' => (float) $pattern['tp1_price'],
                        'lineStyle' => ['color' => '#198754', 'type' => 'dashed', 'width' => 1.5],
                        'label' => ['formatter' => 'TP1: ${c}', 'position' => 'end'],
                    ],
                    [
                        'name' => 'TP2',
                        'yAxis' => (float) $pattern['tp2_price'],
                        'lineStyle' => ['color' => '#20c997', 'type' => 'dotted', 'width' => 1.5],
                        'label' => ['formatter' => 'TP2: ${c}', 'position' => 'end'],
                    ],
                    [
                        'name' => 'Stop Loss',
                        'yAxis' => (float) $pattern['sl_price'],
                        'lineStyle' => ['color' => '#dc3545', 'type' => 'solid', 'width' => 1.5],
                        'label' => ['formatter' => 'SL: ${c}', 'position' => 'end'],
                    ],
                ];

                // RSI Grid MarkPoints & MarkLines
                $rsiMarkPoints = [];
                $rsiMarkLines = [
                    [
                        'name' => 'Overbought 70',
                        'yAxis' => 70,
                        'lineStyle' => ['color' => '#dc3545', 'type' => 'dashed', 'width' => 1],
                        'label' => ['formatter' => '70', 'position' => 'end'],
                    ],
                    [
                        'name' => 'Oversold 30',
                        'yAxis' => 30,
                        'lineStyle' => ['color' => '#198754', 'type' => 'dashed', 'width' => 1],
                        'label' => ['formatter' => '30', 'position' => 'end'],
                    ],
                    [
                        'name' => 'Neckline RSI',
                        'yAxis' => (float) $pattern['neckline_rsi'],
                        'lineStyle' => ['color' => '#fd7e14', 'type' => 'solid', 'width' => 1.5],
                        'label' => ['formatter' => 'Neckline RSI: {c}', 'position' => 'end'],
                    ],
                ];

                foreach ([
                    ['rsi1_date', (float) $pattern['rsi1_value'], 'L1', '#0dcaf0'],
                    ['rsi2_date', (float) $pattern['rsi2_value'], 'L2', '#0dcaf0'],
                    ['neckline_date', (float) $pattern['neckline_rsi'], 'N', '#fd7e14'],
                ] as [$dk, $val, $lbl, $clr]) {
                    $dateStr = $pattern[$dk] ?? null;
                    if ($dateStr !== null) {
                        $idx = array_search($dateStr, $dates, true);
                        if ($idx !== false) {
                            $rsiMarkPoints[] = [
                                'name' => $lbl,
                                'coord' => [$dates[$idx], $val],
                                'value' => $lbl,
                                'itemStyle' => ['color' => $clr],
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
                        ['left' => '60', 'right' => '60', 'top' => '30', 'height' => '50%'],
                        ['left' => '60', 'right' => '60', 'top' => '65%', 'height' => '25%'],
                    ],
                    'xAxis' => [
                        [
                            'type' => 'category',
                            'data' => $dates,
                            'scale' => true,
                            'boundaryGap' => false,
                            'axisLine' => ['onZero' => false],
                            'splitLine' => ['show' => false],
                            'min' => 'dataMin',
                            'max' => 'dataMax',
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
                            'min' => 'dataMin',
                            'max' => 'dataMax',
                        ],
                    ],
                    'yAxis' => [
                        [
                            'scale' => true,
                            'splitArea' => ['show' => true],
                        ],
                        [
                            'scale' => false,
                            'gridIndex' => 1,
                            'min' => 10,
                            'max' => 90,
                            'interval' => 20,
                            'splitNumber' => 4,
                            'axisLabel' => ['formatter' => '{value}'],
                        ],
                    ],
                    'dataZoom' => [
                        [
                            'type' => 'inside',
                            'xAxisIndex' => [0, 1],
                            'start' => max(0, 100 - (int) ((50 / max(count($dates), 1)) * 100)),
                            'end' => 100,
                        ],
                        [
                            'show' => true,
                            'xAxisIndex' => [0, 1],
                            'type' => 'slider',
                            'top' => '93%',
                            'start' => max(0, 100 - (int) ((50 / max(count($dates), 1)) * 100)),
                            'end' => 100,
                        ],
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
                            'markPoint' => ['data' => $priceMarkPoints],
                            'markLine' => ['data' => $priceMarkLines, 'symbol' => ['none', 'none']],
                        ],
                        [
                            'name' => 'RSI (' . $rsiPeriod . ')',
                            'type' => 'line',
                            'xAxisIndex' => 1,
                            'yAxisIndex' => 1,
                            'data' => $rsiData,
                            'smooth' => true,
                            'lineStyle' => ['color' => '#8b5cf6', 'width' => 2],
                            'itemStyle' => ['color' => '#8b5cf6'],
                            'markPoint' => ['data' => $rsiMarkPoints],
                            'markLine' => ['data' => $rsiMarkLines, 'symbol' => ['none', 'none']],
                        ],
                    ],
                ];

                $chartJson = json_encode($chartOption, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                $this->registerJs(<<<JS
                (function() {
                    const dom = document.getElementById('{$chartId}');
                    if (!dom || typeof echarts === 'undefined') return;
                    const chart = echarts.init(dom);
                    chart.setOption({$chartJson});
                    window.addEventListener('resize', function() { chart.resize(); });
                })();
JS
                );
                ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Stock Information Card -->
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-building text-primary me-1"></i> Profil Saham <?= Html::encode($stock->symbol) ?></h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3 col-6">
                <span class="text-muted small d-block">Simbol / Kode</span>
                <strong class="fs-5 text-dark"><?= Html::encode($stock->symbol) ?></strong>
            </div>
            <div class="col-md-3 col-6">
                <span class="text-muted small d-block">Nama Perusahaan</span>
                <strong class="text-dark"><?= Html::encode($stock->name) ?></strong>
            </div>
            <div class="col-md-3 col-6">
                <span class="text-muted small d-block">Sektor Industri</span>
                <span class="badge bg-light text-dark border"><?= Html::encode($stock->sector ?? 'Unassigned') ?></span>
            </div>
            <div class="col-md-3 col-6">
                <span class="text-muted small d-block">Bursa Saham</span>
                <strong class="text-secondary"><?= Html::encode($stock->exchange ?? '-') ?></strong>
            </div>
        </div>
    </div>
</div>
