<?php

/** @var yii\web\View $this */
/** @var app\models\Stock $stock */
/** @var array $analysis */
/** @var array $params */
/** @var array $timeframes */

use app\assets\ChartAsset;
use yii\bootstrap5\Html;
use yii\helpers\Url;

ChartAsset::register($this);

$this->title = 'Trading Plan Bollinger Bands: ' . $stock->symbol . ' — ' . $analysis['strategy_name'];
$this->params['breadcrumbs'][] = ['label' => 'Bollinger Bands Strategy', 'url' => ['index']];
$this->params['breadcrumbs'][] = $stock->symbol;

$action = $analysis['action'];
$badgeColor = match ($action) {
    'STRONG_BUY', 'BUY' => 'success',
    'SELL' => 'danger',
    'TAKE_PROFIT' => 'warning text-dark',
    'HOLD' => 'primary',
    default => 'secondary',
};

// Siapkan data untuk ECharts
$dates = $analysis['dates'];
$ohlc = [];
$volData = [];

foreach ($analysis['candles'] as $c) {
    $ohlc[] = [(float) $c['open'], (float) $c['close'], (float) $c['low'], (float) $c['high']];
    $volData[] = [
        'value' => (int) $c['volume'],
        'itemStyle' => [
            'color' => ((float) $c['close'] >= (float) $c['open']) ? '#26a69a' : '#ef5350',
        ],
    ];
}

$upperSeries = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['upper_series']);
$middleSeries = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['middle_series']);
$lowerSeries = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['lower_series']);

// MarkLines untuk level trading
$markLines = [
    [
        'yAxis' => $analysis['entry_price'],
        'name' => 'Entry',
        'lineStyle' => ['color' => '#0d6efd', 'type' => 'solid', 'width' => 2],
        'label' => ['formatter' => 'Entry: $' . number_format($analysis['entry_price'], 2), 'position' => 'insideEndTop'],
    ],
    [
        'yAxis' => $analysis['stop_loss'],
        'name' => 'Stop Loss',
        'lineStyle' => ['color' => '#dc3545', 'type' => 'dashed', 'width' => 2],
        'label' => ['formatter' => 'SL: $' . number_format($analysis['stop_loss'], 2) . ' (-' . $analysis['stop_loss_pct'] . '%)', 'position' => 'insideEndBottom'],
    ],
    [
        'yAxis' => $analysis['target_1'],
        'name' => 'TP1',
        'lineStyle' => ['color' => '#198754', 'type' => 'dashed', 'width' => 2],
        'label' => ['formatter' => 'TP1: $' . number_format($analysis['target_1'], 2) . ' (+' . $analysis['target_1_pct'] . '%)', 'position' => 'insideEndTop'],
    ],
    [
        'yAxis' => $analysis['target_2'],
        'name' => 'TP2',
        'lineStyle' => ['color' => '#0f5132', 'type' => 'dotted', 'width' => 2],
        'label' => ['formatter' => 'TP2: $' . number_format($analysis['target_2'], 2) . ' (+' . $analysis['target_2_pct'] . '%)', 'position' => 'insideEndTop'],
    ],
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h1 class="h3 mb-0 fw-bold text-dark">
                <?= Html::encode($stock->symbol) ?>
            </h1>
            <span class="badge bg-light text-secondary border fs-6"><?= Html::encode($stock->exchange) ?></span>
            <span class="badge bg-<?= $badgeColor ?> fs-6 px-3 py-1 shadow-sm">
                <?= Html::encode($action) ?>
            </span>
            <span class="badge bg-primary fs-6 px-3 py-1 shadow-sm">
                <?= \app\helpers\SvgHelper::icon('stopwatch') ?> <?= Html::encode($timeframes[$params['timeframe']] ?? 'Daily') ?>
            </span>
            <span class="badge bg-dark fs-6 px-3 py-1 shadow-sm">
                BB (20, 2.0)
            </span>
            <?php if (!empty($analysis['is_shakeout_low'])): ?>
                <span class="badge bg-warning text-dark fs-6 px-3 py-1 shadow-sm border border-warning">
                    <?= \app\helpers\SvgHelper::icon('lightning-charge-fill') ?> Shakeout Low (-<?= $analysis['shakeout_undercut_pct'] ?>%)
                </span>
            <?php endif; ?>
            <span class="badge bg-purple text-white fs-6 px-3 py-1 shadow-sm" style="background-color: #6f42c1;">
                RSI(14): <?= number_format((float)($analysis['rsi'] ?? 50), 1) ?>
            </span>
            <?php if (!empty($analysis['candle_pattern'])): ?>
                <span class="badge <?= ($analysis['candle_signal'] ?? '') === 'BULLISH' ? 'bg-success' : 'bg-danger' ?> fs-6 px-3 py-1 shadow-sm">
                    <?= Html::encode($analysis['candle_pattern']) ?>
                </span>
            <?php endif; ?>
        </div>
        <p class="text-muted small mb-0">
            <?= Html::encode($stock->name) ?> &bull; Sektor: <strong><?= Html::encode($stock->sector ?? 'Umum') ?></strong>
            &bull; Waktu Data: <strong><?= Html::encode($analysis['date']) ?></strong>
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm shadow-sm" role="group">
            <?php foreach ($timeframes as $tfKey => $tfLabel): ?>
                <?= Html::a(
                    Html::encode($tfLabel),
                    ['/bollinger-band/detail', 'symbol' => $stock->symbol, 'timeframe' => $tfKey],
                    ['class' => 'btn btn-sm ' . ((int) $params['timeframe'] === $tfKey ? 'btn-primary' : 'btn-outline-secondary')]
                ) ?>
            <?php endforeach; ?>
        </div>

        <?= Html::a(
            \app\helpers\SvgHelper::icon('arrow-left') . ' Kembali ke Scanner',
            ['/bollinger-band/index', 'timeframe' => $params['timeframe']],
            ['class' => 'btn btn-sm btn-outline-secondary shadow-sm']
        ) ?>
        <?= \app\widgets\BlogQuickPostWidget::widget([
            'symbol' => $stock->symbol,
            'stockName' => $stock->name,
            'timeFrame' => ((int)$params['timeframe'] === 24 ? '1D' : ((int)$params['timeframe'] . 'H')),
            'strategyName' => 'bollinger-bands',
            'analysisDate' => substr((string) ($analysis['date'] ?? date('Y-m-d')), 0, 10),
            'targetElementId' => 'bb-chart',
            'metrics' => [
                'current_price' => $analysis['entry_price'] ?? null,
                'stop_loss' => $analysis['stop_loss'] ?? null,
                'target_1' => $analysis['target_1'] ?? null,
                'target_2' => $analysis['target_2'] ?? null,
                'rsi' => $analysis['rsi'] ?? null,
                'action' => $action ?? 'BUY',
                'candle_pattern' => $analysis['candle_pattern'] ?? null,
            ],
            'buttonClass' => 'btn btn-outline-warning text-dark btn-sm shadow-sm fw-bold',
        ]) ?>
    </div>
</div>

<!-- Highlight Cards: Entry, Stop Loss, Target TP1, Target TP2 -->
<div class="row g-3 mb-4">
    <!-- Area Beli (Entry) Card -->
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10 h-100 border-start border-primary border-4">
            <div class="card-body p-3">
                <div class="text-primary small fw-semibold text-uppercase">Titik Masuk Beli (Entry)</div>
                <div class="h4 mb-0 fw-bold text-primary">
                    <?= Yii::$app->formatter->asCurrency($analysis['entry_price']) ?>
                </div>
                <div class="small text-muted mt-1">
                    Zona: <strong><?= Html::encode($analysis['entry_range']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Stop Loss Card -->
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm bg-danger bg-opacity-10 h-100 border-start border-danger border-4">
            <div class="card-body p-3">
                <div class="text-danger small fw-semibold text-uppercase">Stop Loss (Batas Risiko)</div>
                <div class="h4 mb-0 fw-bold text-danger">
                    <?= Yii::$app->formatter->asCurrency($analysis['stop_loss']) ?>
                </div>
                <div class="small text-danger mt-1">
                    <?= \app\helpers\SvgHelper::icon('shield-x') ?> Maks Risiko: <strong>-<?= $analysis['stop_loss_pct'] ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Target Profit 1 Card -->
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 h-100 border-start border-success border-4">
            <div class="card-body p-3">
                <div class="text-success small fw-semibold text-uppercase">Target TP1 (Middle/Upper)</div>
                <div class="h4 mb-0 fw-bold text-success">
                    <?= Yii::$app->formatter->asCurrency($analysis['target_1']) ?>
                </div>
                <div class="small text-success mt-1">
                    <?= \app\helpers\SvgHelper::icon('graph-up-arrow') ?> Target: <strong>+<?= $analysis['target_1_pct'] ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Target Profit 2 Card -->
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 h-100 border-start border-success border-4">
            <div class="card-body p-3">
                <div class="text-success small fw-semibold text-uppercase">Target TP2 (Runner)</div>
                <div class="h4 mb-0 fw-bold text-success">
                    <?= Yii::$app->formatter->asCurrency($analysis['target_2']) ?>
                </div>
                <div class="small text-success mt-1">
                    <?= \app\helpers\SvgHelper::icon('trophy') ?> Target: <strong>+<?= $analysis['target_2_pct'] ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Bollinger BandWidth & %B Card -->
    <div class="col-12 col-md-4 col-lg">
        <div class="card border-0 shadow-sm bg-info bg-opacity-10 h-100 border-start border-info border-4">
            <div class="card-body p-3">
                <div class="text-info-emphasis small fw-semibold text-uppercase">%B &amp; Volatilitas</div>
                <div class="h4 mb-0 fw-bold text-dark">
                    %B: <?= $analysis['percent_b'] ?>
                </div>
                <div class="small text-muted mt-1">
                    BandWidth: <strong><?= $analysis['bandwidth'] ?>%</strong> <?= !empty($analysis['is_squeeze']) ? '<span class="badge bg-primary ms-1">Squeeze</span>' : '' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION PENTING: KAPAN WAKTU BELI & KAPAN WAKTU JUAL YANG TEPAT -->
<div class="row g-3 mb-4">
    <!-- WAKTU BELI YANG TEPAT -->
    <div class="col-md-6 col-12">
        <div class="card border-0 shadow-sm h-100 border-top border-success border-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-success">
                    <?= \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'me-1']) ?> Kapan Waktu Beli yang Tepat?
                </h5>
                <span class="badge bg-<?= $analysis['timing']['buy']['badge'] ?> px-2 py-1">
                    <?= Html::encode($analysis['timing']['buy']['status']) ?>
                </span>
            </div>
            <div class="card-body p-3">
                <div class="alert alert-success bg-success bg-opacity-10 border-0 mb-3">
                    <h6 class="fw-bold mb-1 text-success"><?= Html::encode($analysis['timing']['buy']['summary']) ?></h6>
                    <div class="small text-dark"><?= nl2br(Html::encode($analysis['timing']['buy']['trigger'])) ?></div>
                </div>

                <div class="mb-3">
                    <div class="small text-muted fw-bold text-uppercase">Area Harga Beli Ideal:</div>
                    <div class="fs-5 fw-bold text-dark"><?= Html::encode($analysis['timing']['buy']['ideal_price']) ?></div>
                </div>

                <div>
                    <div class="small text-muted fw-bold text-uppercase mb-1">Panduan Eksekusi Pembelian:</div>
                    <p class="small text-secondary mb-0">
                        <?= Html::encode($analysis['timing']['buy']['execution']) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- WAKTU JUAL YANG TEPAT -->
    <div class="col-md-6 col-12">
        <div class="card border-0 shadow-sm h-100 border-top border-danger border-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-danger">
                    <?= \app\helpers\SvgHelper::icon('exclamation-diamond-fill', ['class' => 'me-1']) ?> Kapan Waktu Jual yang Tepat?
                </h5>
                <span class="badge bg-<?= $analysis['timing']['sell']['badge'] ?> px-2 py-1">
                    <?= Html::encode($analysis['timing']['sell']['status']) ?>
                </span>
            </div>
            <div class="card-body p-3">
                <div class="alert alert-danger bg-danger bg-opacity-10 border-0 mb-3">
                    <h6 class="fw-bold mb-1 text-danger"><?= Html::encode($analysis['timing']['sell']['summary']) ?></h6>
                    <div class="small text-dark"><?= nl2br(Html::encode($analysis['timing']['sell']['trigger'])) ?></div>
                </div>

                <div class="mb-3">
                    <div class="small text-muted fw-bold text-uppercase">Level Harga Jual (Target / Exit):</div>
                    <div class="fs-5 fw-bold text-dark"><?= Html::encode($analysis['timing']['sell']['ideal_price']) ?></div>
                </div>

                <div>
                    <div class="small text-muted fw-bold text-uppercase mb-1">Panduan Eksekusi Penjualan:</div>
                    <p class="small text-secondary mb-0">
                        <?= Html::encode($analysis['timing']['sell']['execution']) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Kolom Kiri: Chart Interaktif Candlestick & Bollinger Bands -->
    <div class="col-lg-8 col-12">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark">
                    <?= \app\helpers\SvgHelper::icon('graph-up', ['class' => 'text-primary me-1']) ?> Chart Candlestick &amp; Bollinger Bands
                </h5>
                <div class="d-flex align-items-center gap-2 small">
                    <span class="badge bg-danger text-white"><span style="color:#ef5350;">■</span> Upper: <?= Yii::$app->formatter->asCurrency($analysis['upper_band']) ?></span>
                    <span class="badge bg-secondary text-white"><span style="color:#9e9e9e;">■</span> Middle: <?= Yii::$app->formatter->asCurrency($analysis['middle_band']) ?></span>
                    <span class="badge bg-success text-white"><span style="color:#26a69a;">■</span> Lower: <?= Yii::$app->formatter->asCurrency($analysis['lower_band']) ?></span>
                </div>
            </div>
            <div class="card-body p-2">
                <div id="bb-echart" style="width: 100%; height: 500px;"></div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Checklist Validasi & Parameter Indikator -->
    <div class="col-lg-4 col-12">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <?= \app\helpers\SvgHelper::icon('shield-check', ['class' => 'text-success me-1']) ?> 6-Point Validasi Sinyal
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="list-group list-group-flush">
                    <?php foreach ($analysis['checklist'] as $item): ?>
                        <div class="list-group-item px-0 py-2 border-bottom">
                            <div class="d-flex align-items-start gap-2">
                                <?php if ($item['pass']): ?>
                                    <span class="text-success mt-1"><?= \app\helpers\SvgHelper::icon('check-circle-fill') ?></span>
                                <?php else: ?>
                                    <span class="text-secondary mt-1"><?= \app\helpers\SvgHelper::icon('x-circle') ?></span>
                                <?php endif; ?>
                                <div>
                                    <div class="small fw-bold text-dark"><?= Html::encode($item['title']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= Html::encode($item['desc']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-3 pt-2 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold text-muted">Risk to Reward (R:R)</span>
                        <span class="badge bg-<?= $analysis['rr_ratio'] >= 2.0 ? 'success' : 'primary' ?> fs-7">
                            <?= $analysis['rr_ratio'] ?> : 1
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold text-muted">Aktivitas RVOL</span>
                        <span class="badge bg-light text-dark border fs-7">
                            <?= $analysis['rvol'] ?>x Volume
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold text-muted">Posisi %B</span>
                        <span class="badge bg-light text-dark border fs-7">
                            <?= $analysis['percent_b'] ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-muted">BandWidth (%)</span>
                        <span class="badge bg-secondary fs-7">
                            <?= $analysis['bandwidth'] ?>%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body p-3">
                <h6 class="fw-bold text-dark mb-2">
                    <?= \app\helpers\SvgHelper::icon('lightbulb', ['class' => 'text-warning me-1']) ?> Penjelasan Strategi
                </h6>
                <p class="small text-muted mb-2">
                    <?= Html::encode($analysis['description']) ?>
                </p>
                <div class="small text-secondary">
                    <strong>Prinsip Bollinger Bands:</strong>
                    <ul class="ps-3 mb-0 mt-1">
                        <li><strong>Beli di Lower Band / Shakeout:</strong> Manfaatkan *oversold dip* dan jebakan *liquidity sweep* dengan stop loss terukur.</li>
                        <li><strong>Jual di Upper Band:</strong> Amankan keuntungan saat harga menyentuh batas atas ekstrem sebelum terjadi pembalikan ke *mean* (SMA 20).</li>
                        <li><strong>Disiplin Cut Loss:</strong> Batasi kerugian jika harga ditutup di bawah level Stop Loss.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartDom = document.getElementById('bb-echart');
    if (!chartDom || typeof echarts === 'undefined') return;

    const myChart = echarts.init(chartDom);
    const dates = <?= json_encode($dates) ?>;
    const ohlc = <?= json_encode($ohlc) ?>;
    const volData = <?= json_encode($volData) ?>;
    const upperSeries = <?= json_encode($upperSeries) ?>;
    const middleSeries = <?= json_encode($middleSeries) ?>;
    const lowerSeries = <?= json_encode($lowerSeries) ?>;
    const markLines = <?= json_encode($markLines) ?>;

    const option = {
        animation: false,
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'cross' }
        },
        legend: {
            data: ['Candlestick', 'Upper Band', 'Middle Band (SMA 20)', 'Lower Band', 'Volume'],
            top: 5
        },
        grid: [
            { left: '8%', right: '8%', top: '10%', height: '60%' },
            { left: '8%', right: '8%', top: '75%', height: '18%' }
        ],
        xAxis: [
            {
                type: 'category',
                data: dates,
                scale: true,
                boundaryGap: false,
                axisLine: { onZero: false },
                splitLine: { show: false },
                min: 'dataMin',
                max: 'dataMax'
            },
            {
                type: 'category',
                gridIndex: 1,
                data: dates,
                scale: true,
                boundaryGap: false,
                axisLine: { onZero: false },
                axisTick: { show: false },
                splitLine: { show: false },
                axisLabel: { show: false },
                min: 'dataMin',
                max: 'dataMax'
            }
        ],
        yAxis: [
            {
                scale: true,
                splitArea: { show: true }
            },
            {
                scale: true,
                gridIndex: 1,
                splitNumber: 2,
                axisLabel: { show: false },
                axisLine: { show: false },
                axisTick: { show: false },
                splitLine: { show: false }
            }
        ],
        dataZoom: [
            {
                type: 'inside',
                xAxisIndex: [0, 1],
                start: 30,
                end: 100
            },
            {
                show: true,
                xAxisIndex: [0, 1],
                type: 'slider',
                bottom: 5,
                start: 30,
                end: 100
            }
        ],
        series: [
            {
                name: 'Candlestick',
                type: 'candlestick',
                data: ohlc,
                itemStyle: {
                    color: '#26a69a',
                    color0: '#ef5350',
                    borderColor: '#26a69a',
                    borderColor0: '#ef5350'
                },
                markLine: {
                    symbol: ['none', 'none'],
                    data: markLines
                }
            },
            {
                name: 'Upper Band',
                type: 'line',
                data: upperSeries,
                smooth: true,
                lineStyle: { opacity: 0.85, width: 1.5, color: '#ef5350' },
                showSymbol: false
            },
            {
                name: 'Middle Band (SMA 20)',
                type: 'line',
                data: middleSeries,
                smooth: true,
                lineStyle: { opacity: 0.85, width: 2, color: '#9e9e9e' },
                showSymbol: false
            },
            {
                name: 'Lower Band',
                type: 'line',
                data: lowerSeries,
                smooth: true,
                lineStyle: { opacity: 0.85, width: 1.5, color: '#26a69a' },
                showSymbol: false
            },
            {
                name: 'Volume',
                type: 'bar',
                xAxisIndex: 1,
                yAxisIndex: 1,
                data: volData
            }
        ]
    };

    myChart.setOption(option);
    window.addEventListener('resize', function () {
        myChart.resize();
    });
});
</script>
