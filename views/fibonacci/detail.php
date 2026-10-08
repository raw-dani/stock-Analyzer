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

$this->title = 'Trading Plan Fibonacci: ' . $stock->symbol . ' — ' . $analysis['strategy_name'];
$this->params['breadcrumbs'][] = ['label' => 'Fibonacci Strategy & Levels', 'url' => ['index']];
$this->params['breadcrumbs'][] = $stock->symbol;

$action = $analysis['action'];
$badgeColor = match ($action) {
    'BUY' => 'success',
    'SELL' => 'danger',
    'TAKE_PROFIT' => 'warning text-dark',
    'HOLD' => 'primary',
    default => 'secondary',
};

// Siapkan data untuk ECharts Candlestick & Volume
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

$fib = $analysis['fib_levels'];

// MarkLines untuk level Fibonacci Retracement & Extension
$markLines = [
    // Ekstensi TP
    [
        'yAxis' => $fib['fib_1618'],
        'name' => '1.618 Golden Extension',
        'lineStyle' => ['color' => '#d35400', 'type' => 'solid', 'width' => 2],
        'label' => ['formatter' => '🏆 1.618 TP3: $' . number_format($fib['fib_1618'], 2), 'position' => 'insideEndTop'],
    ],
    [
        'yAxis' => $fib['fib_1272'],
        'name' => '1.272 Extension',
        'lineStyle' => ['color' => '#9b59b6', 'type' => 'dashed', 'width' => 1.5],
        'label' => ['formatter' => '🎯 1.272 TP2: $' . number_format($fib['fib_1272'], 2), 'position' => 'insideEndTop'],
    ],
    // Retracement
    [
        'yAxis' => $fib['fib_0'],
        'name' => '0.0% Swing High',
        'lineStyle' => ['color' => '#e74c3c', 'type' => 'solid', 'width' => 1.5],
        'label' => ['formatter' => '0.0% High: $' . number_format($fib['fib_0'], 2), 'position' => 'insideEndTop'],
    ],
    [
        'yAxis' => $fib['fib_382'],
        'name' => '38.2% Dip',
        'lineStyle' => ['color' => '#3498db', 'type' => 'dotted', 'width' => 1],
        'label' => ['formatter' => '38.2%: $' . number_format($fib['fib_382'], 2), 'position' => 'insideEndTop'],
    ],
    [
        'yAxis' => $fib['fib_500'],
        'name' => '50.0% Retest',
        'lineStyle' => ['color' => '#f39c12', 'type' => 'dashed', 'width' => 1.5],
        'label' => ['formatter' => '50.0%: $' . number_format($fib['fib_500'], 2), 'position' => 'insideEndTop'],
    ],
    [
        'yAxis' => $fib['fib_618'],
        'name' => '61.8% Golden Pocket',
        'lineStyle' => ['color' => '#2ecc71', 'type' => 'solid', 'width' => 2.5],
        'label' => ['formatter' => '⭐ 61.8% Golden Pocket: $' . number_format($fib['fib_618'], 2), 'position' => 'insideEndBottom'],
    ],
    [
        'yAxis' => $fib['fib_786'],
        'name' => '78.6% Deep',
        'lineStyle' => ['color' => '#e67e22', 'type' => 'dashed', 'width' => 1],
        'label' => ['formatter' => '78.6%: $' . number_format($fib['fib_786'], 2), 'position' => 'insideEndBottom'],
    ],
    [
        'yAxis' => $fib['fib_1000'],
        'name' => '100.0% Swing Low',
        'lineStyle' => ['color' => '#34495e', 'type' => 'solid', 'width' => 1.5],
        'label' => ['formatter' => '100% Low: $' . number_format($fib['fib_1000'], 2), 'position' => 'insideEndBottom'],
    ],
    // Stop Loss & Entry
    [
        'yAxis' => $analysis['stop_loss'],
        'name' => 'Stop Loss',
        'lineStyle' => ['color' => '#dc3545', 'type' => 'dashed', 'width' => 2],
        'label' => ['formatter' => 'SL: $' . number_format($analysis['stop_loss'], 2) . ' (-' . $analysis['stop_loss_pct'] . '%)', 'position' => 'insideEndBottom'],
    ],
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="h3 mb-0 fw-bold text-dark">
                <?= Html::encode($stock->symbol) ?>
            </h1>
            <span class="badge bg-light text-secondary border fs-6"><?= Html::encode($stock->exchange) ?></span>
            <span class="badge bg-<?= $badgeColor ?> fs-6 px-3 py-1 shadow-sm">
                <?= Html::encode($action) ?>
            </span>
            <span class="badge bg-<?= $analysis['strategy_badge'] ?> fs-6 px-3 py-1 shadow-sm">
                <?= Html::encode($analysis['strategy_name']) ?>
            </span>
        </div>
        <p class="text-muted small mb-0">
            <?= Html::encode($stock->name) ?> &bull; Sektor: <strong><?= Html::encode($stock->sector ?? 'Umum') ?></strong>
            &bull; Tanggal Analisis: <?= Html::encode($analysis['date']) ?>
            &bull; Retracement: <strong><?= number_format($analysis['retrace_pct'], 1) ?>%</strong>
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm shadow-sm" role="group">
            <?php foreach ($timeframes as $tfKey => $tfLabel): ?>
                <?= Html::a(
                    Html::encode($tfLabel),
                    ['/fibonacci/detail', 'symbol' => $stock->symbol, 'timeframe' => $tfKey],
                    ['class' => 'btn btn-sm ' . ((int) $params['timeframe'] === (int) $tfKey ? 'btn-primary' : 'btn-outline-secondary')]
                ) ?>
            <?php endforeach; ?>
        </div>
        <a href="<?= Url::to(['/fibonacci/index', 'timeframe' => $params['timeframe']]) ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-arrow-left"></i> Kembali ke Scanner
        </a>
        <a href="<?= Url::to(['/stock/view', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-primary btn-sm shadow-sm">
            <i class="bi bi-box-arrow-up-right"></i> Detail Saham
        </a>
    </div>
</div>

<!-- Banner Timing Rekomendasi Beli / Jual -->
<div class="card border-0 shadow-sm mb-4 <?= ($action === 'BUY') ? 'bg-success bg-opacity-10 border-start border-success border-4' : (($action === 'TAKE_PROFIT') ? 'bg-warning bg-opacity-10 border-start border-warning border-4' : 'bg-danger bg-opacity-10 border-start border-danger border-4') ?>">
    <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="display-6 <?= ($action === 'BUY') ? 'text-success' : (($action === 'TAKE_PROFIT') ? 'text-warning-emphasis' : 'text-danger') ?>">
                <?php if ($action === 'BUY'): ?>
                    <i class="bi bi-bullseye"></i>
                <?php elseif ($action === 'TAKE_PROFIT'): ?>
                    <i class="bi bi-trophy-fill"></i>
                <?php else: ?>
                    <i class="bi bi-shield-x"></i>
                <?php endif; ?>
            </div>
            <div>
                <h5 class="fw-bold mb-1 <?= ($action === 'BUY') ? 'text-success' : (($action === 'TAKE_PROFIT') ? 'text-warning-emphasis' : 'text-danger') ?>">
                    <?= Html::encode($analysis['timing_recommendation']) ?>
                </h5>
                <p class="mb-0 small text-dark">
                    <?= Html::encode($analysis['description']) ?>
                </p>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-white text-dark border px-3 py-2 fs-7 shadow-sm">
                Harga Saat Ini: <strong>$<?= number_format($analysis['current_price'], 2) ?></strong>
            </span>
            <span class="badge bg-white text-dark border px-3 py-2 fs-7 shadow-sm">
                Retracement: <strong><?= number_format($analysis['retrace_pct'], 1) ?>%</strong>
            </span>
        </div>
    </div>
</div>

<!-- Kartu Rencana Trading Presisi (Precision Trading Plan) -->
<div class="row g-3 mb-4">
    <!-- Entry Card -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10 h-100 border-start border-primary border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-primary small fw-semibold text-uppercase">Posisi Beli (Entry)</div>
                <div class="h4 mb-0 fw-bold text-primary">
                    $<?= number_format($analysis['entry_price'], 2) ?>
                </div>
                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                    Area: <strong><?= Html::encode($analysis['entry_range']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Stop Loss Card -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-danger bg-opacity-10 h-100 border-start border-danger border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-danger small fw-semibold text-uppercase">Stop Loss (Batas Risiko)</div>
                <div class="h4 mb-0 fw-bold text-danger">
                    $<?= number_format($analysis['stop_loss'], 2) ?>
                </div>
                <div class="small text-danger mt-1" style="font-size: 0.72rem;">
                    <i class="bi bi-shield-x"></i> Risiko: <strong>-<?= $analysis['stop_loss_pct'] ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Target Profit 1 Card (Swing High) -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 h-100 border-start border-success border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-success small fw-semibold text-uppercase">TP1 (Swing High 0%)</div>
                <div class="h4 mb-0 fw-bold text-success">
                    $<?= number_format($analysis['target_1'], 2) ?>
                </div>
                <div class="small text-success mt-1" style="font-size: 0.72rem;">
                    <i class="bi bi-graph-up-arrow"></i> Gain: <strong>+<?= $analysis['target_1_pct'] ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Target Profit 2 Card (1.272 Ext) -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 h-100 border-start border-success border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-success small fw-semibold text-uppercase">TP2 (1.272 Ext)</div>
                <div class="h4 mb-0 fw-bold text-success">
                    $<?= number_format($analysis['target_2'], 2) ?>
                </div>
                <div class="small text-success mt-1" style="font-size: 0.72rem;">
                    <i class="bi bi-trophy"></i> Gain: <strong>+<?= $analysis['target_2_pct'] ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Target Profit 3 Card (1.618 Golden Ext) -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10 h-100 border-start border-warning border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-warning-emphasis small fw-semibold text-uppercase">TP3 (1.618 Golden)</div>
                <div class="h4 mb-0 fw-bold text-dark">
                    $<?= number_format($analysis['target_3'], 2) ?>
                </div>
                <div class="small text-warning-emphasis mt-1" style="font-size: 0.72rem;">
                    <i class="bi bi-star-fill"></i> Gain: <strong>+<?= $analysis['target_3_pct'] ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Risk:Reward Ratio Card -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-light h-100 border-start border-dark border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-muted small fw-semibold text-uppercase">Risk / Reward</div>
                <div class="h4 mb-0 fw-bold text-dark">
                    <?= $analysis['rr_ratio'] ?>x
                </div>
                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                    RVOL: <strong><?= $analysis['rvol'] ?>x</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Kolom Kiri: Chart Interaktif Candlestick & Fibonacci Overlays -->
    <div class="col-lg-8 col-12">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-graph-up text-primary me-1"></i> Chart Candlestick &amp; Level Fibonacci
                </h5>
                <div class="d-flex flex-wrap align-items-center gap-1 small">
                    <span class="badge" style="background-color: #2ecc71; color: #fff;">⭐ Golden Pocket 61.8%</span>
                    <span class="badge" style="background-color: #f39c12; color: #fff;">50.0% Half</span>
                    <span class="badge" style="background-color: #3498db; color: #fff;">38.2% Dip</span>
                    <span class="badge" style="background-color: #9b59b6; color: #fff;">TP2 (1.272)</span>
                    <span class="badge" style="background-color: #d35400; color: #fff;">TP3 (1.618)</span>
                </div>
            </div>
            <div class="card-body p-2">
                <div id="fib-echart" style="width: 100%; height: 480px;"></div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Detail Level Fibonacci & 5-Point Checklist -->
    <div class="col-lg-4 col-12">
        <!-- Tabel Level Fibonacci Presisi -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-layers text-primary me-1"></i> Level Kunci Retracement &amp; Extension
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 small">
                        <thead>
                            <tr class="table-light">
                                <th>Rasio</th>
                                <th>Harga Level</th>
                                <th>Status / Peran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>161.8%</strong></td>
                                <td class="fw-semibold text-danger">$<?= number_format($fib['fib_1618'], 2) ?></td>
                                <td><span class="badge bg-warning text-dark">TP3 / Climax Target</span></td>
                            </tr>
                            <tr>
                                <td><strong>127.2%</strong></td>
                                <td class="fw-semibold text-primary">$<?= number_format($fib['fib_1272'], 2) ?></td>
                                <td><span class="badge bg-info text-dark">TP2 / First Extension</span></td>
                            </tr>
                            <tr class="table-secondary">
                                <td><strong>0.0%</strong></td>
                                <td class="fw-bold">$<?= number_format($fib['fib_0'], 2) ?></td>
                                <td><span class="badge bg-dark">Swing High (TP1)</span></td>
                            </tr>
                            <tr>
                                <td><strong>23.6%</strong></td>
                                <td>$<?= number_format($fib['fib_236'], 2) ?></td>
                                <td><span class="text-muted">Koreksi Minimal</span></td>
                            </tr>
                            <tr>
                                <td><strong>38.2%</strong></td>
                                <td class="fw-semibold">$<?= number_format($fib['fib_382'], 2) ?></td>
                                <td><span class="badge bg-primary">Strong Trend Dip</span></td>
                            </tr>
                            <tr class="table-warning">
                                <td><strong>50.0%</strong></td>
                                <td class="fw-bold">$<?= number_format($fib['fib_500'], 2) ?></td>
                                <td><span class="badge bg-warning text-dark">Median Retest</span></td>
                            </tr>
                            <tr class="table-success">
                                <td><strong>61.8%</strong></td>
                                <td class="fw-bold text-success">$<?= number_format($fib['fib_618'], 2) ?></td>
                                <td><span class="badge bg-success">⭐ Golden Pocket</span></td>
                            </tr>
                            <tr>
                                <td><strong>78.6%</strong></td>
                                <td class="text-muted">$<?= number_format($fib['fib_786'], 2) ?></td>
                                <td><span class="badge bg-secondary">Deep Discount</span></td>
                            </tr>
                            <tr class="table-dark text-white">
                                <td><strong>100.0%</strong></td>
                                <td class="fw-bold text-white">$<?= number_format($fib['fib_1000'], 2) ?></td>
                                <td><span class="badge bg-light text-dark">Swing Low (Basis)</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 5-Point Checklist Kualitas Sinyal -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-shield-check text-success me-1"></i> 5-Point Checklist Fibonacci
                </h6>
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush small">
                    <?php foreach ($analysis['checklist'] as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start px-0 py-2 border-bottom">
                            <div class="me-2">
                                <div class="fw-bold <?= $item['pass'] ? 'text-dark' : 'text-muted' ?>">
                                    <?= Html::encode($item['title']) ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.73rem;">
                                    <?= Html::encode($item['desc']) ?>
                                </div>
                            </div>
                            <span>
                                <?php if ($item['pass']): ?>
                                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle text-muted fs-5"></i>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Panduan Aksi & Tips Manajemen Risiko -->
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body p-3">
                <h6 class="fw-bold text-dark mb-2">
                    <i class="bi bi-lightbulb text-warning me-1"></i> Panduan Eksekusi Trading
                </h6>
                <div class="small text-secondary">
                    <?php if ($action === 'BUY'): ?>
                        Pasang antrean beli bertahap pada zona emas <strong><?= Html::encode($analysis['entry_range']) ?></strong>.
                        Pasang proteksi stop loss ketat pada <strong>$<?= number_format($analysis['stop_loss'], 2) ?></strong>.
                        Amankan 50% profit di TP1 (<strong>$<?= number_format($analysis['target_1'], 2) ?></strong>) dan biarkan sisanya mengejar target ekstensi 1.272 &amp; 1.618.
                    <?php elseif ($action === 'TAKE_PROFIT'): ?>
                        Harga telah mencapai target ekstensi utama. Lakukan penjualan bertahap (50% - 100%) untuk mengunci profit sebelum gelombang pembalikan arah terjadi.
                    <?php elseif ($action === 'SELL'): ?>
                        Level pertahanan kritis 78.6% tertembus ke bawah. Segera lakukan cut loss pada <strong>$<?= number_format($analysis['stop_loss'], 2) ?></strong> untuk melindungi modal.
                    <?php else: ?>
                        Tunggu hingga harga menguji level Fibonacci kunci (38.2% atau 61.8%) dengan konfirmasi candle bullish sebelum masuk posisi.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartDom = document.getElementById('fib-echart');
    if (!chartDom || typeof echarts === 'undefined') return;

    const myChart = echarts.init(chartDom);
    const dates = <?= json_encode($dates) ?>;
    const ohlc = <?= json_encode($ohlc) ?>;
    const volData = <?= json_encode($volData) ?>;
    const markLines = <?= json_encode($markLines) ?>;

    const option = {
        animation: true,
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'cross' }
        },
        legend: {
            data: ['Price', 'Volume'],
            top: 5
        },
        grid: [
            { left: '50', right: '110', top: '40', height: '64%' },
            { left: '50', right: '110', top: '78%', height: '14%' }
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
                axisLabel: { show: false }
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
                splitLine: { show: false }
            }
        ],
        dataZoom: [
            { type: 'inside', xAxisIndex: [0, 1], start: 20, end: 100 },
            { show: true, xAxisIndex: [0, 1], type: 'slider', top: '94%', start: 20, end: 100 }
        ],
        series: [
            {
                name: 'Price',
                type: 'candlestick',
                data: ohlc,
                itemStyle: {
                    color: '#26a69a',
                    color0: '#ef5350',
                    borderColor: '#26a69a',
                    borderColor0: '#ef5350'
                },
                markLine: {
                    symbol: 'none',
                    data: markLines
                }
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
