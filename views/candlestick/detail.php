<?php

/** @var yii\web\View $this */
/** @var app\models\Stock $stock */
/** @var array $analysis */
/** @var array $params */
/** @var array $timeframes */
/** @var array $patterns */

use app\assets\ChartAsset;
use yii\bootstrap5\Html;
use yii\helpers\Url;

ChartAsset::register($this);

$this->title = 'Trading Plan Candlestick: ' . $stock->symbol . ' — ' . $analysis['pattern_name'];
$this->params['breadcrumbs'][] = ['label' => 'Candlestick Scanner', 'url' => ['index', 'timeframe' => $params['timeframe']]];
$this->params['breadcrumbs'][] = $stock->symbol;

$action = $analysis['action'];
$actionBadgeColor = match ($action) {
    'STRONG_BUY', 'BUY' => 'success',
    'STRONG_SELL', 'SELL' => 'danger',
    'WATCH' => 'warning text-dark',
    default => 'secondary',
};

// Data untuk ECharts Candlestick & Volume Sub-chart
$dates = $analysis['dates'];
$ohlc = [];
$volData = [];
$sma20VolData = [];

// Hitung moving average volume 20 untuk subchart
$vols = [];
foreach ($analysis['candles'] as $i => $c) {
    $ohlc[] = [(float) $c['open'], (float) $c['close'], (float) $c['low'], (float) $c['high']];
    $vol = (int) $c['volume'];
    $vols[] = $vol;

    $volData[] = [
        'value' => $vol,
        'itemStyle' => [
            'color' => ((float) $c['close'] >= (float) $c['open']) ? '#26a69a' : '#ef5350',
        ],
    ];

    $window = array_slice($vols, max(0, $i - 19), 20);
    $sma20VolData[] = round(array_sum($window) / count($window));
}

// MarkLines untuk level trading
$markLines = [
    [
        'yAxis' => $analysis['entry_price'],
        'name' => 'Entry',
        'lineStyle' => ['color' => '#0d6efd', 'type' => 'solid', 'width' => 2],
        'label' => ['formatter' => 'Entry: $' . number_format((float) $analysis['entry_price'], 2), 'position' => 'insideEndTop'],
    ],
    [
        'yAxis' => $analysis['stop_loss'],
        'name' => 'Stop Loss',
        'lineStyle' => ['color' => '#dc3545', 'type' => 'dashed', 'width' => 2],
        'label' => ['formatter' => 'SL: $' . number_format((float) $analysis['stop_loss'], 2) . ' (-' . $analysis['stop_loss_pct'] . '%)', 'position' => 'insideEndBottom'],
    ],
    [
        'yAxis' => $analysis['target_1'],
        'name' => 'TP1',
        'lineStyle' => ['color' => '#198754', 'type' => 'dashed', 'width' => 2],
        'label' => ['formatter' => 'TP1: $' . number_format((float) $analysis['target_1'], 2) . ' (+' . $analysis['target_1_pct'] . '%)', 'position' => 'insideEndTop'],
    ],
    [
        'yAxis' => $analysis['target_2'],
        'name' => 'TP2',
        'lineStyle' => ['color' => '#0f5132', 'type' => 'dotted', 'width' => 2],
        'label' => ['formatter' => 'TP2: $' . number_format((float) $analysis['target_2'], 2) . ' (+' . $analysis['target_2_pct'] . '%)', 'position' => 'insideEndTop'],
    ],
];

// Highlight candle pola yang terdeteksi
$patternDate = $analysis['bar_date'];
$markPoints = [
    [
        'name' => $analysis['pattern_name'],
        'coord' => [$patternDate, (float) $analysis['candle_anatomy']['high']],
        'value' => $analysis['pattern_name'],
        'symbol' => 'pin',
        'symbolSize' => 45,
        'itemStyle' => ['color' => str_contains($action, 'BUY') ? '#198754' : (str_contains($action, 'SELL') ? '#dc3545' : '#ffc107')],
        'label' => ['color' => '#fff', 'fontSize' => 10, 'fontWeight' => 'bold'],
    ],
];
?>

<!-- Header Saham & Navigasi Timeframe -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
            <h1 class="h3 mb-0 fw-bold text-dark">
                <?= Html::encode($stock->symbol) ?>
            </h1>
            <span class="fs-4 fw-bold text-dark ms-1">$<?= number_format((float) $analysis['last_price'], 2) ?></span>
            <?php if ($analysis['price_change_pct'] >= 0): ?>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-7">
                    <i class="bi bi-arrow-up-short"></i> +<?= number_format((float) $analysis['price_change_pct'], 2) ?>%
                </span>
            <?php else: ?>
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 fs-7">
                    <i class="bi bi-arrow-down-short"></i> <?= number_format((float) $analysis['price_change_pct'], 2) ?>%
                </span>
            <?php endif; ?>
            <span class="badge bg-light text-secondary border"><?= Html::encode($stock->exchange) ?></span>
            <span class="badge bg-<?= $actionBadgeColor ?> px-2 py-1 shadow-sm">
                <?= Html::encode($action) ?>
            </span>
            <span class="badge bg-<?= $analysis['pattern_badge'] ?> px-2 py-1 shadow-sm">
                <?= $analysis['pattern_icon'] ?> <?= Html::encode($analysis['pattern_name']) ?>
            </span>
        </div>
        <p class="text-muted small mb-0">
            <?= Html::encode($stock->name) ?> &bull; Sektor: <strong><?= Html::encode($stock->sector ?? 'Umum') ?></strong>
            &bull; Tanggal Bar Pola: <strong><?= Html::encode($analysis['bar_date']) ?></strong>
            <?php if (!empty($analysis['is_fallback_daily'])): ?>
                &bull; <span class="badge bg-warning text-dark">Data Intraday belum tersedia, analisis memakai data 1D Daily Fallback</span>
            <?php endif; ?>
        </p>
    </div>

    <!-- Timeframe Switcher Button Group -->
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm shadow-sm" role="group">
            <?php foreach ($timeframes as $tfKey => $tfLabel): ?>
                <?= Html::a(
                    Html::encode($tfLabel),
                    ['/candlestick/detail', 'symbol' => $stock->symbol, 'timeframe' => $tfKey],
                    ['class' => 'btn btn-sm ' . ((int) $params['timeframe'] === (int) $tfKey ? 'btn-primary' : 'btn-outline-secondary')]
                ) ?>
            <?php endforeach; ?>
        </div>
        <a href="https://www.tradingview.com/chart/?symbol=<?= !empty($stock->exchange) ? urlencode($stock->exchange . ':' . $stock->symbol) : urlencode($stock->symbol) ?>"
           target="_blank"
           class="btn btn-sm btn-outline-dark shadow-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i> TradingView
        </a>
        <?= Html::a(
            '<i class="bi bi-arrow-left"></i> Kembali ke Scanner',
            ['/candlestick/index', 'timeframe' => $params['timeframe']],
            ['class' => 'btn btn-sm btn-outline-secondary shadow-sm']
        ) ?>
    </div>
</div>

<!-- 6 KPI Metric Summary Cards -->
<div class="row g-3 mb-4">
    <!-- Card 1: Rekomendasi Waktu Transaksi -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-light h-100 border-start border-<?= $actionBadgeColor ?> border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-muted small fw-semibold text-uppercase">Waktu Transaksi</div>
                <div class="h4 mb-0 fw-bold text-<?= $actionBadgeColor ?>">
                    <?= Html::encode($action) ?>
                </div>
                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                    <?= str_contains($action, 'BUY') ? 'Waktu Beli Tepat' : (str_contains($action, 'SELL') ? 'Waktu Jual / Exit' : 'Pantau Breakout') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Entry Price -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10 h-100 border-start border-primary border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-primary small fw-semibold text-uppercase">Level Entry Ideal</div>
                <div class="h4 mb-0 fw-bold text-primary">
                    $<?= number_format((float) $analysis['entry_price'], 2) ?>
                </div>
                <div class="small text-primary mt-1" style="font-size: 0.72rem;">
                    Area: <strong><?= Html::encode($analysis['entry_range']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Stop Loss -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-danger bg-opacity-10 h-100 border-start border-danger border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-danger small fw-semibold text-uppercase">Stop Loss (Batas Risiko)</div>
                <div class="h4 mb-0 fw-bold text-danger">
                    $<?= number_format((float) $analysis['stop_loss'], 2) ?>
                </div>
                <div class="small text-danger mt-1" style="font-size: 0.72rem;">
                    <i class="bi bi-shield-x"></i> Batas Risiko: <strong>-<?= $analysis['stop_loss_pct'] ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Target Profit 1 -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 h-100 border-start border-success border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-success small fw-semibold text-uppercase">Target Profit 1 (TP1)</div>
                <div class="h4 mb-0 fw-bold text-success">
                    $<?= number_format((float) $analysis['target_1'], 2) ?>
                </div>
                <div class="small text-success mt-1" style="font-size: 0.72rem;">
                    <i class="bi bi-graph-up-arrow"></i> Potensi Gain: <strong>+<?= $analysis['target_1_pct'] ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 5: Target Profit 2 -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 h-100 border-start border-success border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-success small fw-semibold text-uppercase">Target Profit 2 (TP2)</div>
                <div class="h4 mb-0 fw-bold text-success">
                    $<?= number_format((float) $analysis['target_2'], 2) ?>
                </div>
                <div class="small text-success mt-1" style="font-size: 0.72rem;">
                    <i class="bi bi-trophy"></i> Potensi Gain: <strong>+<?= $analysis['target_2_pct'] ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 6: Risk / Reward Ratio & RVOL -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm bg-light h-100 border-start border-dark border-4">
            <div class="card-body p-2 p-md-3">
                <div class="text-muted small fw-semibold text-uppercase">Risk / Reward</div>
                <div class="h4 mb-0 fw-bold text-dark">
                    <?= $analysis['rr_ratio'] ?>x
                </div>
                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                    RVOL: <strong><?= $analysis['rvol'] ?>x</strong> (<?= Html::encode($analysis['volume_status']) ?>)
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Kolom Kiri (8 Kolom): Chart ECharts & Instruksi Eksekusi -->
    <div class="col-lg-8 col-12">
        <!-- Chart Candlestick & Volume Sub-chart -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-graph-up text-primary me-1"></i> Chart Candlestick &amp; Volume Sub-chart
                </h5>
                <div class="d-flex flex-wrap align-items-center gap-1 small">
                    <span class="badge bg-light text-dark border">Candle Hijau/Merah: OHLC</span>
                    <span class="badge bg-light text-primary border">Garis Biru: Entry</span>
                    <span class="badge bg-light text-danger border">Garis Merah: SL</span>
                    <span class="badge bg-light text-success border">Garis Hijau: TP</span>
                    <span class="badge bg-light text-secondary border">Garis Oranye: SMA20 Volume</span>
                </div>
            </div>
            <div class="card-body p-2">
                <div id="candlestick-echart" style="height: 520px; width: 100%;"></div>
            </div>
        </div>

        <!-- Panduan Eksekusi Waktu Beli & Waktu Jual -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-compass text-primary me-1"></i> Rekomendasi Waktu Transaksi &amp; Strategi Eksekusi
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="alert alert-<?= $actionBadgeColor ?> bg-opacity-10 border-0 mb-3">
                    <h6 class="fw-bold mb-1">
                        <i class="bi bi-info-circle-fill me-1"></i> Panduan Timing:
                    </h6>
                    <p class="mb-0 small fw-medium">
                        <?= Html::encode($analysis['timing_advice']) ?>
                    </p>
                </div>

                <div class="row g-3 small">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border h-100">
                            <h6 class="fw-bold text-success mb-2">
                                <i class="bi bi-box-arrow-in-down-right"></i> Langkah Waktu Beli (Entry Strategy):
                            </h6>
                            <p class="mb-2 text-secondary">
                                <?= Html::encode($analysis['entry_advice']) ?>
                            </p>
                            <ul class="list-unstyled mb-0 text-muted" style="font-size: 0.76rem;">
                                <li>&bull; Jangan mengejar harga (*chasing*) jika sudah melompat lebih dari 2% di atas level entry.</li>
                                <li>&bull; Pastikan volume saat menembus level High lebih tinggi dari volume bar sebelumnya.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border h-100">
                            <h6 class="fw-bold text-danger mb-2">
                                <i class="bi bi-box-arrow-up-right"></i> Langkah Waktu Jual (Exit &amp; Proteksi Modal):
                            </h6>
                            <p class="mb-2 text-secondary">
                                <?= Html::encode($analysis['exit_advice']) ?>
                            </p>
                            <ul class="list-unstyled mb-0 text-muted" style="font-size: 0.76rem;">
                                <li>&bull; Lakukan penjualan parsial (50%) di TP1 ($<?= number_format((float) $analysis['target_1'], 2) ?>).</li>
                                <li>&bull; Geser stop loss ke titik impas (*Breakeven SL*) untuk mengunci posisi tanpa risiko (*free-ride*).</li>
                                <li>&bull; Jual 100% sisa posisi jika menyentuh Stop Loss pada $<?= number_format((float) $analysis['stop_loss'], 2) ?>.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan (4 Kolom): Analisa Volume Mendalam & Anatomi Candle -->
    <div class="col-lg-4 col-12">
        <!-- Panel 1: Analisa Konfirmasi Volume Mendalam -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-activity text-primary me-1"></i> Analisa Konfirmasi Volume
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small">Status Konfirmasi:</span>
                    <span class="badge <?= $analysis['volume_badge'] ?> fs-6 px-2 py-1">
                        <?= Html::encode($analysis['volume_strength_label']) ?>
                    </span>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Relative Volume (RVOL):</span>
                    <span class="fw-bold fs-5 text-dark"><?= $analysis['rvol'] ?>x SMA20</span>
                </div>

                <div class="progress mb-3" style="height: 10px;">
                    <?php
                    $volWidth = min(100, (int) round(($analysis['rvol'] / 3.0) * 100));
                    $volColor = $analysis['rvol'] >= 2.0 ? 'bg-success' : ($analysis['rvol'] >= 1.2 ? 'bg-primary' : ($analysis['rvol'] >= 0.85 ? 'bg-info' : 'bg-warning'));
                    ?>
                    <div class="progress-bar <?= $volColor ?> progress-bar-striped progress-bar-animated"
                         role="progressbar"
                         style="width: <?= $volWidth ?>%;">
                    </div>
                </div>

                <div class="p-3 bg-light rounded border mb-3">
                    <div class="fw-bold small mb-1 <?= $analysis['volume_supports_bullish'] ? 'text-success' : 'text-danger' ?>">
                        <?php if ($analysis['volume_supports_bullish']): ?>
                            <i class="bi bi-check-circle-fill"></i> Volume MENDUKUNG Kenaikan Saham
                        <?php else: ?>
                            <i class="bi bi-exclamation-triangle-fill"></i> Volume TIDAK CUKUP Mendukung
                        <?php endif; ?>
                    </div>
                    <p class="small text-secondary mb-0" style="font-size: 0.78rem;">
                        <?= Html::encode($analysis['volume_verdict']) ?>
                    </p>
                </div>

                <div class="row g-2 text-center small">
                    <div class="col-6">
                        <div class="p-2 border rounded bg-white">
                            <span class="text-muted d-block" style="font-size: 0.7rem;">Volume Bar Pola</span>
                            <span class="fw-bold"><?= number_format((int) $analysis['volume']) ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-white">
                            <span class="text-muted d-block" style="font-size: 0.7rem;">SMA20 Volume Rata-rata</span>
                            <span class="fw-bold"><?= number_format((int) $analysis['volume_sma20']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel 2: Anatomi Candlestick yang Terdeteksi -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-aspect-ratio text-primary me-1"></i> Anatomi Candlestick: <?= Html::encode($analysis['pattern_name']) ?>
                </h6>
            </div>
            <div class="card-body p-3">
                <p class="small text-secondary mb-3">
                    <?= Html::encode($analysis['pattern_description']) ?>
                </p>

                <div class="table-responsive">
                    <table class="table table-sm table-borderless small mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">Open:</td>
                                <td class="fw-bold text-end">$<?= number_format((float) $analysis['candle_anatomy']['open'], 2) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">High:</td>
                                <td class="fw-bold text-end text-success">$<?= number_format((float) $analysis['candle_anatomy']['high'], 2) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Low:</td>
                                <td class="fw-bold text-end text-danger">$<?= number_format((float) $analysis['candle_anatomy']['low'], 2) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Close:</td>
                                <td class="fw-bold text-end">$<?= number_format((float) $analysis['candle_anatomy']['close'], 2) ?></td>
                            </tr>
                            <tr class="border-top">
                                <td class="text-muted">Persentase Body:</td>
                                <td class="fw-bold text-end"><?= $analysis['candle_anatomy']['body_pct'] ?>% dari total range</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Ekor Atas (Upper Wick):</td>
                                <td class="fw-bold text-end"><?= $analysis['candle_anatomy']['upper_wick_pct'] ?>%</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Ekor Bawah (Lower Wick):</td>
                                <td class="fw-bold text-end"><?= $analysis['candle_anatomy']['lower_wick_pct'] ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Panel 3: 5-Point Checklist Konfirmasi Sinyal -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-shield-check text-success me-1"></i> 5-Point Checklist Sinyal
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
                                <div class="text-muted" style="font-size: 0.72rem;">
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
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartDom = document.getElementById('candlestick-echart');
    if (!chartDom || typeof echarts === 'undefined') return;

    const myChart = echarts.init(chartDom);
    const dates = <?= json_encode($dates) ?>;
    const ohlc = <?= json_encode($ohlc) ?>;
    const volData = <?= json_encode($volData) ?>;
    const sma20VolData = <?= json_encode($sma20VolData) ?>;
    const markLines = <?= json_encode($markLines) ?>;
    const markPoints = <?= json_encode($markPoints) ?>;

    const option = {
        animation: true,
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'cross' }
        },
        legend: {
            data: ['Price Candlestick', 'Volume Bar', 'SMA 20 Volume'],
            top: 5
        },
        grid: [
            { left: '55', right: '110', top: '40', height: '62%' },
            { left: '55', right: '110', top: '75%', height: '16%' }
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
            { type: 'inside', xAxisIndex: [0, 1], start: 30, end: 100 },
            { show: true, xAxisIndex: [0, 1], type: 'slider', top: '93%', start: 30, end: 100 }
        ],
        series: [
            {
                name: 'Price Candlestick',
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
                },
                markPoint: {
                    data: markPoints
                }
            },
            {
                name: 'Volume Bar',
                type: 'bar',
                xAxisIndex: 1,
                yAxisIndex: 1,
                data: volData
            },
            {
                name: 'SMA 20 Volume',
                type: 'line',
                xAxisIndex: 1,
                yAxisIndex: 1,
                data: sma20VolData,
                smooth: true,
                lineStyle: {
                    color: '#f59e0b',
                    width: 1.5
                },
                symbol: 'none'
            }
        ]
    };

    myChart.setOption(option);
    window.addEventListener('resize', function () {
        myChart.resize();
    });
});
</script>
