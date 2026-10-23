<?php

/** @var yii\web\View $this */
/** @var app\models\Stock $stock */
/** @var array $analysis */
/** @var array $params */
/** @var array $maPairs */
/** @var array $timeframes */

use app\assets\ChartAsset;
use yii\bootstrap5\Html;
use yii\helpers\Url;

ChartAsset::register($this);

$this->title = 'Trading Plan MA: ' . $stock->symbol . ' — ' . $analysis['strategy_name'];
$this->params['breadcrumbs'][] = ['label' => 'Moving Average Signals', 'url' => ['index']];
$this->params['breadcrumbs'][] = $stock->symbol;

$action = $analysis['action'];
$badgeColor = match ($action) {
    'BUY' => 'success',
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

$fastMaSeries = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['fast_ma_series']);
$slowMaSeries = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['slow_ma_series']);
$trendMaSeries = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['trend_ma_series']);

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
    [
        'yAxis' => (float)($analysis['target_3'] ?? 0),
        'name' => 'TP3',
        'lineStyle' => ['color' => '#ffc107', 'type' => 'dotted', 'width' => 2],
        'label' => ['formatter' => 'TP3: $' . number_format((float)($analysis['target_3'] ?? 0), 2) . ' (+' . ($analysis['target_3_pct'] ?? 0) . '%)', 'position' => 'insideEndTop'],
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
                <?= Html::encode($analysis['ma_type'] ?? 'SMA') ?>: <?= Html::encode($maPairs[$params['maPair']] ?? 'MA20 / MA50') ?>
            </span>
            <span class="badge bg-purple text-white fs-6 px-3 py-1 shadow-sm" style="background-color: #6f42c1;">
                RSI(14): <?= number_format((float)($analysis['rsi'] ?? 50), 1) ?>
            </span>
            <?php if (!empty($analysis['candle_pattern'])): ?>
                <span class="badge <?= ($analysis['candle_signal'] ?? '') === 'BULLISH' ? 'bg-success' : 'bg-danger' ?> fs-6 px-3 py-1 shadow-sm">
                    <?= \app\helpers\SvgHelper::icon('reception-4') ?> <?= Html::encode($analysis['candle_pattern']) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($analysis['atr'])): ?>
                <span class="badge bg-info text-dark fs-6 px-3 py-1 shadow-sm">
                    ATR(14): <?= Yii::$app->formatter->asCurrency($analysis['atr']) ?>
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
            <?= Html::a(
                'SMA',
                ['/moving-average/detail', 'symbol' => $stock->symbol, 'timeframe' => $params['timeframe'], 'maPair' => $params['maPair'], 'maType' => 'sma'],
                ['class' => 'btn btn-sm ' . (($params['maType'] ?? 'sma') === 'sma' ? 'btn-dark' : 'btn-outline-dark')]
            ) ?>
            <?= Html::a(
                'EMA',
                ['/moving-average/detail', 'symbol' => $stock->symbol, 'timeframe' => $params['timeframe'], 'maPair' => $params['maPair'], 'maType' => 'ema'],
                ['class' => 'btn btn-sm ' . (($params['maType'] ?? 'sma') === 'ema' ? 'btn-dark' : 'btn-outline-dark')]
            ) ?>
        </div>
        <div class="btn-group btn-group-sm shadow-sm" role="group">
            <?php foreach ($timeframes as $tfKey => $tfLabel): ?>
                <?= Html::a(
                    Html::encode($tfLabel),
                    ['/moving-average/detail', 'symbol' => $stock->symbol, 'timeframe' => $tfKey, 'maPair' => $params['maPair'], 'maType' => $params['maType'] ?? 'sma'],
                    ['class' => 'btn btn-sm ' . ((int) $params['timeframe'] === (int) $tfKey ? 'btn-primary' : 'btn-outline-secondary')]
                ) ?>
            <?php endforeach; ?>
        </div>
        <a href="<?= Url::to(['/moving-average/index', 'timeframe' => $params['timeframe'], 'maPair' => $params['maPair'], 'maType' => $params['maType'] ?? 'sma']) ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
            <?= \app\helpers\SvgHelper::icon('arrow-left') ?> Kembali ke Scanner
        </a>
        <a href="<?= Url::to(['/stock/view', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-primary btn-sm shadow-sm">
            <?= \app\helpers\SvgHelper::icon('box-arrow-up-right') ?> Detail Saham
        </a>
        <?= \app\widgets\BlogQuickPostWidget::widget([
            'symbol' => $stock->symbol,
            'stockName' => $stock->name,
            'timeFrame' => ((int)$params['timeframe'] === 24 ? '1D' : ((int)$params['timeframe'] . 'H')),
            'strategyName' => 'moving-average',
            'analysisDate' => substr((string) ($analysis['date'] ?? date('Y-m-d')), 0, 10),
            'targetElementId' => 'ma-chart',
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

<?php if (!empty($analysis['is_fallback'])): ?>
    <div class="alert alert-warning py-2 px-3 small mb-3 shadow-sm d-flex align-items-center gap-2">
        <?= \app\helpers\SvgHelper::icon('exclamation-triangle-fill', ['class' => 'fs-5 text-warning']) ?>
        <div>
            <strong>Catatan Data Timeframe:</strong> Data bar intraday (<?= Html::encode($timeframes[$params['timeframe']] ?? '') ?>) untuk saham <?= Html::encode($stock->symbol) ?> belum mencukupi untuk periode MA yang dipilih, sehingga sistem secara otomatis menggunakan data historis harian (*Daily Fallback*).
        </div>
    </div>
<?php endif; ?>

<!-- Kartu Rencana Trading Presisi (Precision Trading Plan) -->
<div class="row g-2 mb-4">
    <!-- Entry Card -->
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10 h-100 border-start border-primary border-4">
            <div class="card-body p-3">
                <div class="text-primary small fw-semibold text-uppercase">Posisi Beli (Entry)</div>
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
                <div class="text-success small fw-semibold text-uppercase">TP1 (Konservatif)</div>
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
                <div class="text-success small fw-semibold text-uppercase">TP2 (Runner)</div>
                <div class="h4 mb-0 fw-bold text-success">
                    <?= Yii::$app->formatter->asCurrency($analysis['target_2']) ?>
                </div>
                <div class="small text-success mt-1">
                    <?= \app\helpers\SvgHelper::icon('trophy') ?> Target: <strong>+<?= $analysis['target_2_pct'] ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Target Profit 3 Card -->
    <div class="col-12 col-md-4 col-lg">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10 h-100 border-start border-warning border-4">
            <div class="card-body p-3">
                <div class="text-warning-emphasis small fw-semibold text-uppercase">TP3 (Moonbag)</div>
                <div class="h4 mb-0 fw-bold text-dark">
                    <?= !empty($analysis['target_3']) ? Yii::$app->formatter->asCurrency($analysis['target_3']) : '-' ?>
                </div>
                <div class="small text-success mt-1">
                    <?= \app\helpers\SvgHelper::icon('rocket-takeoff', ['class' => 'text-warning']) ?> Target: <strong>+<?= $analysis['target_3_pct'] ?? '0' ?>%</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Kolom Kiri: Chart Interaktif Candlestick & MA Lines -->
    <div class="col-lg-8 col-12">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark">
                    <?= \app\helpers\SvgHelper::icon('graph-up', ['class' => 'text-primary me-1']) ?> Chart Candlestick &amp; Level MA
                </h5>
                <div class="d-flex align-items-center gap-2 small">
                    <span class="badge bg-warning text-dark"><span style="color:#f39c12;">■</span> MA<?= $analysis['fast_period'] ?> (<?= Yii::$app->formatter->asCurrency($analysis['fast_ma']) ?>)</span>
                    <span class="badge bg-info text-white"><span style="color:#00c0ef;">■</span> MA<?= $analysis['slow_period'] ?> (<?= Yii::$app->formatter->asCurrency($analysis['slow_ma']) ?>)</span>
                    <?php if ($analysis['trend_ma'] !== null): ?>
                        <span class="badge bg-secondary text-white"><span style="color:#9b59b6;">■</span> MA200 (<?= Yii::$app->formatter->asCurrency($analysis['trend_ma']) ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-2">
                <div id="ma-echart" style="width: 100%; height: 460px;"></div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Checklist Sinyal & Rekomendasi Eksekusi -->
    <div class="col-lg-4 col-12">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <?= \app\helpers\SvgHelper::icon('shield-check', ['class' => 'text-success me-1']) ?> 5-Point Checklist Kualitas Sinyal
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
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <?= Html::encode($item['desc']) ?>
                                </div>
                            </div>
                            <span>
                                <?php if ($item['pass']): ?>
                                    <?= \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success fs-5']) ?>
                                <?php else: ?>
                                    <?= \app\helpers\SvgHelper::icon('x-circle', ['class' => 'text-muted fs-5']) ?>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="bg-light p-3 rounded mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold text-muted">Tipe Moving Average</span>
                        <span class="badge bg-dark fs-7">
                            <?= Html::encode($analysis['ma_type'] ?? 'SMA') ?>
                        </span>
                    </div>
                    <?php if (!empty($analysis['atr'])): ?>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-bold text-muted">Volatilitas ATR (14)</span>
                            <span class="badge bg-info text-dark fs-7">
                                <?= Yii::$app->formatter->asCurrency($analysis['atr']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
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
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-muted">Status Tren</span>
                        <span class="badge bg-secondary fs-7">
                            <?= Html::encode($analysis['trend_status']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body p-3">
                <h6 class="fw-bold text-dark mb-2">
                    <?= \app\helpers\SvgHelper::icon('lightbulb', ['class' => 'text-warning me-1']) ?> Rekomendasi Eksekusi Trading
                </h6>
                <p class="small text-muted mb-2">
                    <?= Html::encode($analysis['description']) ?>
                </p>
                <div class="small text-secondary">
                    <?php if ($action === 'BUY'): ?>
                        <div class="mb-2">
                            <strong>1. Area Beli:</strong> Pasang antrian beli bertahap pada zona <strong><?= Html::encode($analysis['entry_range']) ?></strong> (Ideal: <?= Yii::$app->formatter->asCurrency($analysis['entry_price']) ?>).
                        </div>
                        <div class="mb-2">
                            <strong>2. Disiplin Proteksi:</strong> Pasang stop loss ketat di <strong><?= Yii::$app->formatter->asCurrency($analysis['stop_loss']) ?></strong> (Risiko <?= $analysis['stop_loss_pct'] ?>%). Cut loss wajib dieksekusi jika harga ditutup di bawah level ini.
                        </div>
                        <div>
                            <strong>3. Realisasi Profit Bertingkat (Scaling Out):</strong>
                            <ul class="ps-3 mb-0 mt-1">
                                <li><strong>TP1 (<?= Yii::$app->formatter->asCurrency($analysis['target_1']) ?> / +<?= $analysis['target_1_pct'] ?>%):</strong> Jual 40%-50% porsi untuk mengunci profit awal dan amankan modal. Geser Stop Loss ke harga modal (Breakeven).</li>
                                <li><strong>TP2 (<?= Yii::$app->formatter->asCurrency($analysis['target_2']) ?> / +<?= $analysis['target_2_pct'] ?>%):</strong> Jual 30% porsi tambahan dan pasang Trailing Stop di garis MA Cepat (MA<?= $analysis['fast_period'] ?>).</li>
                                <li><strong>TP3 (<?= !empty($analysis['target_3']) ? Yii::$app->formatter->asCurrency($analysis['target_3']) : '-' ?> / +<?= $analysis['target_3_pct'] ?? '0' ?>%):</strong> Biarkan sisa porsi 20%-30% (Moonbag) berjalan hingga muncul sinyal pembalikan (candlestick reversal atau RSI > 75).</li>
                            </ul>
                        </div>
                    <?php elseif ($action === 'SELL'): ?>
                        Pola persilangan bearish terdeteksi. Segera kurangi eksposur atau keluar dari posisi untuk menghindari penurunan harga lebih lanjut.
                    <?php elseif ($action === 'TAKE_PROFIT'): ?>
                        Harga sudah mengalami ekstensi tinggi di atas rata-rata pergerakan. Lakukan penjualan bertahap (*scaling out*) untuk merealisasikan keuntungan modal.
                    <?php else: ?>
                        Pertahankan posisi dengan trailing stop di garis Moving Average. Pantau reaksi harga jika mendekati area support.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartDom = document.getElementById('ma-echart');
    if (!chartDom || typeof echarts === 'undefined') return;

    const myChart = echarts.init(chartDom);
    const dates = <?= json_encode($dates) ?>;
    const ohlc = <?= json_encode($ohlc) ?>;
    const volData = <?= json_encode($volData) ?>;
    const fastMa = <?= json_encode($fastMaSeries) ?>;
    const slowMa = <?= json_encode($slowMaSeries) ?>;
    const trendMa = <?= json_encode($trendMaSeries) ?>;
    const markLines = <?= json_encode($markLines) ?>;

    const option = {
        animation: true,
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'cross' }
        },
        legend: {
            data: ['Price', 'MA<?= $analysis['fast_period'] ?>', 'MA<?= $analysis['slow_period'] ?>', 'MA200', 'Volume'],
            top: 5
        },
        grid: [
            { left: '45', right: '55', top: '45', height: '62%' },
            { left: '45', right: '55', top: '75%', height: '16%' }
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
                name: 'MA<?= $analysis['fast_period'] ?>',
                type: 'line',
                data: fastMa,
                smooth: true,
                lineStyle: { opacity: 0.9, width: 2, color: '#f39c12' }
            },
            {
                name: 'MA<?= $analysis['slow_period'] ?>',
                type: 'line',
                data: slowMa,
                smooth: true,
                lineStyle: { opacity: 0.9, width: 2, color: '#00c0ef' }
            },
            {
                name: 'MA200',
                type: 'line',
                data: trendMa,
                smooth: true,
                lineStyle: { opacity: 0.7, width: 1.5, color: '#9b59b6' }
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
