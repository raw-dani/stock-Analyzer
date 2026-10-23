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

$this->title = 'Trading Plan Confluence: ' . $stock->symbol . ' — ' . $analysis['signal_title'];
$this->params['breadcrumbs'][] = ['label' => 'Strategy Confluence', 'url' => ['index']];
$this->params['breadcrumbs'][] = $stock->symbol;

$action = $analysis['action'];
$badgeColor = match ($action) {
    'STRONG_BUY' => 'success',
    'BUY' => 'primary',
    'HOLD' => 'info text-white',
    'SELL' => 'danger',
    default => 'secondary',
};

// Siapkan data untuk ECharts Candlestick, MA, Fibonacci, RSI, dan MACD
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

$sma20Data = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['sma20_series']);
$sma50Data = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['sma50_series']);
$ema20Data = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['ema20_series']);
$rsiData   = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['rsi_series']);
$macdData  = array_map(fn($v) => $v !== null ? round((float) $v, 3) : null, $analysis['macd_series']);
$macdSignalData = array_map(fn($v) => $v !== null ? round((float) $v, 3) : null, $analysis['macd_signal_series']);
$macdHistData   = array_map(fn($v) => $v !== null ? round((float) $v, 3) : null, $analysis['macd_hist_series']);

// Garis Fibonacci
$fibLevels = $analysis['fib_levels'];
$markLines = [
    [
        'yAxis' => $analysis['entry_price'],
        'name' => 'Entry Price',
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
        'name' => 'TP1 Target',
        'lineStyle' => ['color' => '#198754', 'type' => 'dashed', 'width' => 2],
        'label' => ['formatter' => 'TP1: $' . number_format($analysis['target_1'], 2) . ' (+' . $analysis['target_1_pct'] . '%)', 'position' => 'insideEndTop'],
    ],
    [
        'yAxis' => $analysis['target_2'],
        'name' => 'TP2 Target',
        'lineStyle' => ['color' => '#20c997', 'type' => 'dotted', 'width' => 1.5],
        'label' => ['formatter' => 'TP2: $' . number_format($analysis['target_2'], 2) . ' (+' . $analysis['target_2_pct'] . '%)', 'position' => 'insideEndTop'],
    ],
    // Level Fib 61.8% Golden Pocket
    [
        'yAxis' => $fibLevels['618'] ?? 0,
        'name' => 'Fib 61.8%',
        'lineStyle' => ['color' => '#ffc107', 'type' => 'dashed', 'width' => 1.5],
        'label' => ['formatter' => 'Fib 61.8% (Golden Pocket): $' . number_format($fibLevels['618'] ?? 0, 2), 'position' => 'insideStartTop'],
    ],
];
?>

<!-- Header Toolbar -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="h3 fw-bold text-dark mb-0">
                <?= Html::encode($stock->symbol) ?>
            </h1>
            <span class="badge bg-secondary"><?= Html::encode($stock->exchange) ?></span>
            <span class="badge bg-<?= $badgeColor ?> fs-6 shadow-sm"><?= Html::encode($analysis['action']) ?></span>
            <span class="badge bg-dark fs-6 shadow-sm">Skor Konfluensi: <?= $analysis['confluence_score'] ?>/100</span>
        </div>
        <p class="text-muted small mb-0">
            <?= Html::encode($stock->name) ?> &bull; Sektor: <strong><?= Html::encode($stock->sector ?: 'Umum') ?></strong> &bull; Higher TF (Harian): 
            <span class="fw-bold <?= $analysis['higher_tf_trend'] === 'BULLISH' ? 'text-success' : ($analysis['higher_tf_trend'] === 'BEARISH' ? 'text-danger' : 'text-muted') ?>">
                <?= $analysis['higher_tf_trend'] ?>
            </span>
        </p>
    </div>

    <!-- Pilihan Timeframe & Actions -->
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm shadow-sm" role="group">
            <?php foreach ($timeframes as $tfKey => $tfLabel): ?>
                <a href="<?= Url::to(['detail', 'symbol' => $stock->symbol, 'timeframe' => $tfKey]) ?>"
                   class="btn <?= ((int) $params['timeframe'] === (int) $tfKey) ? 'btn-primary active' : 'btn-outline-secondary' ?>">
                    <?= Html::encode($tfLabel) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <a href="<?= Url::to(['index']) ?>" class="btn btn-sm btn-outline-secondary">
            <?= \app\helpers\SvgHelper::icon('arrow-left') ?> Kembali ke Scanner
        </a>
        <?= \app\widgets\BlogQuickPostWidget::widget([
            'symbol' => $stock->symbol,
            'stockName' => $stock->name,
            'timeFrame' => (((int) $params['timeframe'] === 24) ? '1D' : "{$params['timeframe']}H"),
            'strategyName' => 'strategy-confluence',
            'category' => 'Confluence Strategy',
            'analysisDate' => date('Y-m-d'),
            'targetElementId' => 'confluenceChart',
            'metrics' => [
                'action' => $analysis['action'] ?? null,
                'confluence_score' => $analysis['confluence_score'] ?? null,
                'current_price' => $analysis['current_price'] ?? null,
                'entry_price' => $analysis['entry_price'] ?? null,
                'stop_loss' => $analysis['stop_loss'] ?? null,
                'target_1' => $analysis['target_1'] ?? null,
                'target_2' => $analysis['target_2'] ?? null,
                'risk_reward_ratio' => $analysis['risk_reward_ratio'] ?? null,
                'higher_tf_trend' => $analysis['higher_tf_trend'] ?? null,
            ],
        ]) ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Kolom Kiri: Chart Multi-Panel ECharts (Candlestick, Volume, RSI, MACD) -->
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark">
                    <?= \app\helpers\SvgHelper::icon('graph-up', ['class' => 'text-primary me-1']) ?> Multi-Indicator Technical Chart (Candles, MA, Fib, RSI &amp; MACD)
                </h5>
                <small class="text-muted">Interaktif Zoom &amp; Pan</small>
            </div>
            <div class="card-body p-2">
                <div id="confluenceChart" style="height: 640px; width: 100%;"></div>
                
                <div class="d-flex flex-wrap gap-3 mt-2 px-3 small text-muted border-top pt-2">
                    <span><span class="badge bg-primary">EMA 20</span> Dinamis Fast MA</span>
                    <span><span class="badge bg-warning text-dark">SMA 50</span> Dinamis Slow MA</span>
                    <span><span class="badge bg-warning-subtle text-warning border border-warning">Fib 61.8%</span> Golden Pocket</span>
                    <span><span class="badge bg-success">TP1 / TP2</span> Target Keuntungan</span>
                    <span><span class="badge bg-danger">SL</span> Batas Risiko Cut Loss</span>
                    <span><span class="badge bg-info text-dark">RSI 14</span> Area 30/70</span>
                    <span><span class="badge bg-secondary">MACD</span> Histogram Momentum</span>
                </div>
            </div>
        </div>

        <!-- 4 Pilar Indikator Detail Cards -->
        <div class="row g-3">
            <!-- Pilar 1: S/R & MA -->
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-white">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-semibold mb-1">
                            <?= \app\helpers\SvgHelper::icon('shield-shaded', ['class' => 'text-primary']) ?> Support &amp; MA
                        </div>
                        <div class="h5 fw-bold mb-0 text-dark">$<?= number_format($analysis['support'], 2) ?></div>
                        <div class="small text-muted mt-1">
                            EMA20: $<?= $analysis['ema_20'] ? number_format($analysis['ema_20'], 2) : '—' ?><br>
                            SMA50: $<?= $analysis['sma_50'] ? number_format($analysis['sma_50'], 2) : '—' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pilar 2: RSI 14 -->
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-white">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-semibold mb-1">
                            <?= \app\helpers\SvgHelper::icon('activity', ['class' => 'text-info']) ?> RSI (14)
                        </div>
                        <div class="h5 fw-bold mb-0 <?= $analysis['rsi'] <= 35 ? 'text-success' : ($analysis['rsi'] >= 70 ? 'text-danger' : 'text-primary') ?>">
                            <?= $analysis['rsi'] ?>
                        </div>
                        <div class="small mt-1">
                            <span class="badge <?= $analysis['rsi'] <= 30 ? 'bg-success' : ($analysis['rsi'] >= 70 ? 'bg-danger' : 'bg-light text-dark border') ?>">
                                <?= $analysis['rsi_status'] ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pilar 3: MACD -->
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-white">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-semibold mb-1">
                            <?= \app\helpers\SvgHelper::icon('soundwave', ['class' => 'text-warning']) ?> MACD (12,26,9)
                        </div>
                        <div class="h5 fw-bold mb-0 <?= $analysis['macd'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= $analysis['macd'] ?>
                        </div>
                        <div class="small mt-1">
                            <?php if ($analysis['macd_cross'] === 'BULLISH_CROSS'): ?>
                                <span class="badge bg-success-subtle text-success border border-success">Bullish Cross</span>
                            <?php elseif ($analysis['macd_cross'] === 'BEARISH_CROSS'): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger">Bearish Cross</span>
                            <?php else: ?>
                                <span class="text-muted">Signal: <?= $analysis['macd_signal'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pilar 4: Fibonacci -->
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-white">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-semibold mb-1">
                            <?= \app\helpers\SvgHelper::icon('bullseye', ['class' => 'text-danger']) ?> Fibonacci 61.8%
                        </div>
                        <div class="h5 fw-bold mb-0 text-dark"><?= $analysis['retrace_pct'] ?>% Retrace</div>
                        <div class="small mt-1">
                            <?php if ($analysis['in_golden_pocket']): ?>
                                <span class="badge bg-warning text-dark"><?= \app\helpers\SvgHelper::icon('star-fill') ?> Golden Pocket</span>
                            <?php else: ?>
                                <span class="text-muted">Swing High: $<?= number_format($analysis['swing_high'], 2) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Rencana Trading Presisi & Checklist Konfluensi -->
    <div class="col-12 col-xl-4">
        <!-- Rencana Trading Presisi -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <?= \app\helpers\SvgHelper::icon('calculator', ['class' => 'text-success me-1']) ?> Rencana Eksekusi Trading Presisi
                </h5>
            </div>
            <div class="card-body p-3">
                <div class="mb-3 p-3 bg-light rounded border">
                    <div class="small text-muted text-uppercase fw-semibold">Area Beli Rekomendasi (Entry Range)</div>
                    <div class="h4 fw-bold text-primary mb-1 font-monospace"><?= Html::encode($analysis['entry_range']) ?></div>
                    <div class="small text-muted">Beli bertahap saat harga menguji area support MA / Golden Pocket.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 border rounded border-danger bg-danger-subtle text-center">
                            <div class="small text-danger fw-semibold">Stop Loss (Batas Risiko)</div>
                            <div class="h5 fw-bold text-danger mb-0 font-monospace">$<?= number_format($analysis['stop_loss'], 2) ?></div>
                            <div class="small text-danger">-<?= $analysis['stop_loss_pct'] ?>% risiko</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded border-success bg-success-subtle text-center">
                            <div class="small text-success fw-semibold">Target 1 (TP1)</div>
                            <div class="h5 fw-bold text-success mb-0 font-monospace">$<?= number_format($analysis['target_1'], 2) ?></div>
                            <div class="small text-success">+<?= $analysis['target_1_pct'] ?>% potensi gain</div>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 border rounded border-info bg-info-subtle text-center">
                            <div class="small text-info-emphasis fw-semibold">Target 2 (TP2 Runner)</div>
                            <div class="h6 fw-bold text-info-emphasis mb-0 font-monospace">$<?= number_format($analysis['target_2'], 2) ?></div>
                            <div class="small text-info-emphasis">+<?= $analysis['target_2_pct'] ?>% potensi gain</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded border-primary bg-primary-subtle text-center">
                            <div class="small text-primary fw-semibold">Risk / Reward Ratio</div>
                            <div class="h5 fw-bold text-primary mb-0 font-monospace"><?= $analysis['rr_ratio'] ?>x</div>
                            <div class="small text-primary">Sangat Ideal</div>
                        </div>
                    </div>
                </div>

                <!-- Checklist Konfluensi -->
                <div class="border rounded p-3 bg-white">
                    <div class="small fw-bold text-dark text-uppercase mb-2">
                        <?= \app\helpers\SvgHelper::icon('check2-all', ['class' => 'text-primary']) ?> Faktor Konfluensi Terkonfirmasi (<?= count($analysis['signals_list']) ?> Indikator)
                    </div>
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach ($analysis['signals_list'] as $item): ?>
                            <li class="mb-2 d-flex align-items-start gap-2">
                                <?= \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success mt-1']) ?>
                                <span><?= Html::encode($item) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Tabel Level Fibonacci Lengkap -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-2 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><?= \app\helpers\SvgHelper::icon('layers', ['class' => 'text-secondary me-1']) ?> Level Fibonacci Retracement &amp; Ekstensi</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 small">
                    <tbody>
                        <tr><td>Swing High (0.0%)</td><td class="text-end fw-bold font-monospace">$<?= number_format($fibLevels['0'], 2) ?></td></tr>
                        <tr><td>Retracement 23.6%</td><td class="text-end font-monospace">$<?= number_format($fibLevels['236'], 2) ?></td></tr>
                        <tr><td>Retracement 38.2%</td><td class="text-end font-monospace">$<?= number_format($fibLevels['382'], 2) ?></td></tr>
                        <tr><td>Retracement 50.0%</td><td class="text-end font-monospace">$<?= number_format($fibLevels['500'], 2) ?></td></tr>
                        <tr class="table-warning fw-bold"><td>Golden Pocket (61.8%)</td><td class="text-end font-monospace">$<?= number_format($fibLevels['618'], 2) ?></td></tr>
                        <tr><td>Deep Discount (78.6%)</td><td class="text-end font-monospace">$<?= number_format($fibLevels['786'], 2) ?></td></tr>
                        <tr><td>Swing Low (100.0%)</td><td class="text-end fw-bold font-monospace">$<?= number_format($fibLevels['1000'], 2) ?></td></tr>
                        <tr class="table-success"><td>Ekstensi 1.272 (Target)</td><td class="text-end fw-bold font-monospace">$<?= number_format($fibLevels['1272'], 2) ?></td></tr>
                        <tr class="table-success"><td>Ekstensi 1.618 (Target Max)</td><td class="text-end fw-bold font-monospace">$<?= number_format($fibLevels['1618'], 2) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartDom = document.getElementById('confluenceChart');
    if (!chartDom || typeof echarts === 'undefined') return;

    const chart = echarts.init(chartDom);
    const dates = <?= json_encode($dates) ?>;
    const ohlc = <?= json_encode($ohlc) ?>;
    const volData = <?= json_encode($volData) ?>;
    const sma20 = <?= json_encode($sma20Data) ?>;
    const sma50 = <?= json_encode($sma50Data) ?>;
    const ema20 = <?= json_encode($ema20Data) ?>;
    const rsi = <?= json_encode($rsiData) ?>;
    const macd = <?= json_encode($macdData) ?>;
    const macdSignal = <?= json_encode($macdSignalData) ?>;
    const macdHist = <?= json_encode($macdHistData) ?>;
    const markLines = <?= json_encode($markLines) ?>;

    const option = {
        animation: false,
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'cross' }
        },
        axisPointer: { link: [{ xAxisIndex: 'all' }] },
        grid: [
            { left: '8%', right: '4%', top: '5%', height: '46%' },      // Candlestick + MA + Fib
            { left: '8%', right: '4%', top: '55%', height: '11%' },     // Volume
            { left: '8%', right: '4%', top: '69%', height: '11%' },     // RSI
            { left: '8%', right: '4%', top: '83%', height: '11%' }      // MACD
        ],
        xAxis: [
            { type: 'category', data: dates, gridIndex: 0, scale: true, axisLabel: { show: false } },
            { type: 'category', data: dates, gridIndex: 1, scale: true, axisLabel: { show: false } },
            { type: 'category', data: dates, gridIndex: 2, scale: true, axisLabel: { show: false } },
            { type: 'category', data: dates, gridIndex: 3, scale: true }
        ],
        yAxis: [
            { scale: true, gridIndex: 0, splitLine: { lineStyle: { color: '#f0f0f0' } } },
            { scale: true, gridIndex: 1, splitNumber: 2, axisLabel: { show: false } },
            { scale: true, gridIndex: 2, min: 0, max: 100, splitNumber: 2 },
            { scale: true, gridIndex: 3, splitNumber: 2 }
        ],
        dataZoom: [
            { type: 'inside', xAxisIndex: [0, 1, 2, 3], start: 30, end: 100 },
            { type: 'slider', xAxisIndex: [0, 1, 2, 3], start: 30, end: 100, bottom: '0%' }
        ],
        series: [
            // Candlestick (Panel 0)
            {
                name: 'OHLC',
                type: 'candlestick',
                data: ohlc,
                xAxisIndex: 0,
                yAxisIndex: 0,
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
            // EMA 20 (Panel 0)
            { name: 'EMA 20', type: 'line', data: ema20, xAxisIndex: 0, yAxisIndex: 0, smooth: true, showSymbol: false, lineStyle: { color: '#0d6efd', width: 1.5 } },
            // SMA 50 (Panel 0)
            { name: 'SMA 50', type: 'line', data: sma50, xAxisIndex: 0, yAxisIndex: 0, smooth: true, showSymbol: false, lineStyle: { color: '#ffc107', width: 1.5 } },
            // SMA 20 (Panel 0)
            { name: 'SMA 20', type: 'line', data: sma20, xAxisIndex: 0, yAxisIndex: 0, smooth: true, showSymbol: false, lineStyle: { color: '#6c757d', width: 1.2, type: 'dashed' } },
            // Volume (Panel 1)
            { name: 'Volume', type: 'bar', data: volData, xAxisIndex: 1, yAxisIndex: 1 },
            // RSI (Panel 2)
            {
                name: 'RSI(14)',
                type: 'line',
                data: rsi,
                xAxisIndex: 2,
                yAxisIndex: 2,
                smooth: true,
                showSymbol: false,
                lineStyle: { color: '#6f42c1', width: 1.5 },
                markLine: {
                    symbol: ['none', 'none'],
                    data: [
                        { yAxis: 70, lineStyle: { color: '#dc3545', type: 'dashed' }, label: { formatter: '70' } },
                        { yAxis: 30, lineStyle: { color: '#198754', type: 'dashed' }, label: { formatter: '30' } }
                    ]
                }
            },
            // MACD Line (Panel 3)
            { name: 'MACD', type: 'line', data: macd, xAxisIndex: 3, yAxisIndex: 3, showSymbol: false, lineStyle: { color: '#0d6efd', width: 1.2 } },
            // MACD Signal Line (Panel 3)
            { name: 'Signal', type: 'line', data: macdSignal, xAxisIndex: 3, yAxisIndex: 3, showSymbol: false, lineStyle: { color: '#dc3545', width: 1.2 } },
            // MACD Histogram (Panel 3)
            {
                name: 'Hist',
                type: 'bar',
                data: macdHist,
                xAxisIndex: 3,
                yAxisIndex: 3,
                itemStyle: {
                    color: function (params) {
                        return params.value >= 0 ? '#26a69a' : '#ef5350';
                    }
                }
            }
        ]
    };

    chart.setOption(option);
    window.addEventListener('resize', function () {
        chart.resize();
    });
});
</script>
