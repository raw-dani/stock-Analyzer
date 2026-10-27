<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\Stock $stock */
/** @var array $analysis */
/** @var array $params */
/** @var array $timeframes */

use app\assets\ChartAsset;
use app\services\StochasticService;
use yii\bootstrap5\Html;
use yii\helpers\Url;

ChartAsset::register($this);

$this->title = 'Trading Plan Stochastic: ' . $stock->symbol . ' — ' . $analysis['strategy_name'];
$this->params['breadcrumbs'][] = ['label' => 'Stochastic Strategy Scanner', 'url' => ['index']];
$this->params['breadcrumbs'][] = $stock->symbol;

$currentTimeframe = (int) ($params['timeframe'] ?? StochasticService::TIMEFRAME_1D);
$action = $analysis['action'];
$actionBadgeColor = match ($action) {
    StochasticService::ACTION_STRONG_BUY, StochasticService::ACTION_BUY => 'success',
    StochasticService::ACTION_TAKE_PROFIT => 'warning text-dark',
    StochasticService::ACTION_SELL => 'danger',
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

$kSeries = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['k_series']);
$dSeries = array_map(fn($v) => $v !== null ? round((float) $v, 2) : null, $analysis['d_series']);

// MarkLines level trading
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
        'yAxis' => $analysis['target_profit_1'],
        'name' => 'TP 1',
        'lineStyle' => ['color' => '#198754', 'type' => 'dashed', 'width' => 2],
        'label' => ['formatter' => 'TP1: $' . number_format($analysis['target_profit_1'], 2) . ' (+' . $analysis['target_profit_1_pct'] . '%)', 'position' => 'insideEndTop'],
    ],
    [
        'yAxis' => $analysis['target_profit_2'],
        'name' => 'TP 2',
        'lineStyle' => ['color' => '#0f766e', 'type' => 'dotted', 'width' => 1.5],
        'label' => ['formatter' => 'TP2: $' . number_format($analysis['target_profit_2'], 2) . ' (+' . $analysis['target_profit_2_pct'] . '%)', 'position' => 'insideEndTop'],
    ],
];

if (!empty($analysis['shakeout_low']['detected']) && !empty($analysis['shakeout_low']['support_level'])) {
    $markLines[] = [
        'yAxis' => $analysis['shakeout_low']['support_level'],
        'name' => 'Support (Reclaimed)',
        'lineStyle' => ['color' => '#f59e0b', 'type' => 'dotted', 'width' => 1.8],
        'label' => ['formatter' => 'Support Reclaimed: $' . number_format($analysis['shakeout_low']['support_level'], 2), 'position' => 'insideStartTop', 'color' => '#b45309'],
    ];
}

$chartId = 'stochastic-echarts-container';
?>

<!-- Header Title Bar Unified -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h1 class="h3 mb-0 fw-bold text-dark font-monospace">
                <?= Html::encode($stock->symbol) ?>
            </h1>
            <span class="badge bg-light text-secondary border fs-6"><?= Html::encode($stock->exchange ?: 'NASDAQ') ?></span>
            <span class="badge bg-<?= $actionBadgeColor ?> fs-6 px-3 py-1 shadow-sm">
                <?= Html::encode($action) ?>
            </span>
            <span class="badge bg-primary fs-6 px-3 py-1 shadow-sm">
                <?= \app\helpers\SvgHelper::icon('stopwatch') ?> <?= Html::encode($timeframes[$currentTimeframe] ?? 'Daily') ?>
            </span>
            <span class="badge bg-dark fs-6 px-3 py-1 shadow-sm">
                Stoch (14, 3, 3)
            </span>
            <?php if (!empty($analysis['shakeout_low']['detected'])): ?>
                <span class="badge bg-warning text-dark fs-6 px-3 py-1 shadow-sm border border-warning">
                    <?= \app\helpers\SvgHelper::icon('lightning-charge-fill') ?> Shakeout Low (-<?= $analysis['shakeout_low']['sweep_pct'] ?>%)
                </span>
            <?php endif; ?>
            <span class="badge bg-purple text-white fs-6 px-3 py-1 shadow-sm" style="background-color: #6f42c1;">
                %K: <?= number_format((float)$analysis['curr_k'], 1) ?> | %D: <?= number_format((float)$analysis['curr_d'], 1) ?>
            </span>
        </div>
        <p class="text-muted small mb-0">
            <?= Html::encode($stock->name) ?> &bull; Sektor: <strong><?= Html::encode($stock->sector ?? 'Umum') ?></strong>
            &bull; Waktu Data: <strong><?= Html::encode($analysis['dates'][count($analysis['dates']) - 1] ?? date('Y-m-d')) ?></strong>
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm shadow-sm" role="group">
            <?php foreach ($timeframes as $tfKey => $tfLabel): ?>
                <?= Html::a(
                    Html::encode($tfLabel),
                    ['/stochastic/detail', 'symbol' => $stock->symbol, 'timeframe' => $tfKey],
                    ['class' => 'btn btn-sm ' . ($currentTimeframe === $tfKey ? 'btn-primary' : 'btn-outline-secondary')]
                ) ?>
            <?php endforeach; ?>
        </div>

        <?= Html::a(
            \app\helpers\SvgHelper::icon('arrow-left') . ' Kembali ke Scanner',
            ['/stochastic/index', 'timeframe' => $currentTimeframe],
            ['class' => 'btn btn-sm btn-outline-secondary shadow-sm']
        ) ?>
        <?= \app\widgets\BlogQuickPostWidget::widget([
            'symbol' => $stock->symbol,
            'stockName' => $stock->name,
            'timeFrame' => (($currentTimeframe === 24 || $currentTimeframe === 0) ? '1D' : ($currentTimeframe . 'H')),
            'strategyName' => 'stochastic-oscillator',
            'analysisDate' => substr((string) ($analysis['dates'][count($analysis['dates']) - 1] ?? date('Y-m-d')), 0, 10),
            'targetElementId' => $chartId,
            'metrics' => [
                'current_price' => $analysis['entry_price'] ?? null,
                'stop_loss' => $analysis['stop_loss'] ?? null,
                'target_1' => $analysis['target_profit_1'] ?? null,
                'target_2' => $analysis['target_profit_2'] ?? null,
                'curr_k' => $analysis['curr_k'] ?? null,
                'curr_d' => $analysis['curr_d'] ?? null,
                'action' => $action ?? 'BUY',
                'strategy_name' => $analysis['strategy_name'] ?? null,
            ],
            'buttonClass' => 'btn btn-outline-warning text-dark btn-sm shadow-sm fw-bold',
        ]) ?>
    </div>
</div>

<!-- Highlight Cards: Entry, Stop Loss, Target TP1, Target TP2, R:R -->
<div class="row g-3 mb-4">
    <!-- Area Beli (Entry) Card -->
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm h-100 rounded-3" style="border-left: 4px solid var(--brand-primary) !important;">
            <div class="card-body p-3">
                <span class="small text-muted fw-bold text-uppercase d-block mb-1">Area Beli (Entry)</span>
                <h4 class="fw-bold text-primary mb-0 font-monospace">
                    $<?= number_format((float) $analysis['entry_price'], 2) ?>
                </h4>
                <small class="text-muted">Harga Pasar / Reclaim</small>
            </div>
        </div>
    </div>

    <!-- Stop Loss Card -->
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm h-100 rounded-3" style="border-left: 4px solid var(--brand-rose) !important;">
            <div class="card-body p-3">
                <span class="small text-muted fw-bold text-uppercase d-block mb-1">Stop Loss</span>
                <h4 class="fw-bold text-danger mb-0 font-monospace">
                    $<?= number_format((float) $analysis['stop_loss'], 2) ?>
                </h4>
                <small class="text-danger">-<?= $analysis['stop_loss_pct'] ?>% Risk</small>
            </div>
        </div>
    </div>

    <!-- Target Profit 1 Card -->
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm h-100 rounded-3" style="border-left: 4px solid var(--brand-emerald) !important;">
            <div class="card-body p-3">
                <span class="small text-muted fw-bold text-uppercase d-block mb-1">Target TP1</span>
                <h4 class="fw-bold text-success mb-0 font-monospace">
                    $<?= number_format((float) $analysis['target_profit_1'], 2) ?>
                </h4>
                <small class="text-success">+<?= $analysis['target_profit_1_pct'] ?>% Gain</small>
            </div>
        </div>
    </div>

    <!-- Target Profit 2 Card -->
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm h-100 rounded-3" style="border-left: 4px solid #0f766e !important;">
            <div class="card-body p-3">
                <span class="small text-muted fw-bold text-uppercase d-block mb-1">Target TP2 (Ekspansi)</span>
                <h4 class="fw-bold mb-0 font-monospace" style="color: #0f766e;">
                    $<?= number_format((float) $analysis['target_profit_2'], 2) ?>
                </h4>
                <small style="color: #0f766e;">+<?= $analysis['target_profit_2_pct'] ?>% Gain</small>
            </div>
        </div>
    </div>

    <!-- Risk:Reward / Score Card -->
    <div class="col-12 col-md-4 col-lg">
        <div class="card border-0 shadow-sm h-100 rounded-3" style="border-left: 4px solid var(--brand-amber) !important;">
            <div class="card-body p-3">
                <span class="small text-muted fw-bold text-uppercase d-block mb-1">Risk : Reward</span>
                <h4 class="fw-bold text-dark mb-0 font-monospace">
                    1 : <?= $analysis['risk_reward_ratio'] ?>
                </h4>
                <small class="text-warning-emphasis">Winrate Score: <?= $analysis['confidence_score'] ?>%</small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($analysis['shakeout_low']['detected'])): ?>
    <!-- Shakeout Low (Wyckoff Spring) Highlight Card -->
    <div class="alert alert-warning border border-warning shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning text-dark flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.5rem;">
            <?= \app\helpers\SvgHelper::icon('lightning-charge-fill') ?>
        </div>
        <div class="flex-grow-1">
            <div class="fw-bold text-dark d-flex align-items-center gap-2 flex-wrap">
                <span>Pola Shakeout Low (Wyckoff Spring Reclaim) Terkonfirmasi!</span>
                <span class="badge bg-danger text-white rounded-pill px-2 py-0" style="font-size:0.68rem;">HIGH WIN-RATE SETUP</span>
            </div>
            <div class="small text-muted mt-1">
                Harga sempat menembus level support <strong class="text-dark">$<?= number_format($analysis['shakeout_low']['support_level'], 2) ?></strong> hingga titik terendah <strong class="text-danger">$<?= number_format($analysis['shakeout_low']['shakeout_low'], 2) ?></strong> (-<?= $analysis['shakeout_low']['sweep_pct'] ?>%) untuk memicu <em>stop-loss</em> ritel, lalu langsung di-<em>reclaim</em> kembali oleh penyerapan akumulasi cerdas dengan Stochastic %K di level <strong><?= $analysis['curr_k'] ?></strong>. Stop loss diatur ketat tepat di bawah jarum <em>sweep low</em>.
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Main Content Grid -->
<div class="row g-4 mb-4">
    <!-- Interactive Chart (Dual-Panel: Candlestick + Stochastic) -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-primary"><?= \app\helpers\SvgHelper::icon('activity') ?></span>
                    <span class="fw-bold text-dark">Grafik Candlestick &amp; Stochastic Oscillator (14, 3, 3)</span>
                </div>
                <div class="d-flex align-items-center gap-3 small font-monospace">
                    <span style="color: #0284c7;"><i class="bi bi-circle-fill me-1" style="font-size:0.6rem;"></i>%K: <strong><?= number_format((float) $analysis['curr_k'], 1) ?></strong></span>
                    <span style="color: #f59e0b;"><i class="bi bi-circle-fill me-1" style="font-size:0.6rem;"></i>%D: <strong><?= number_format((float) $analysis['curr_d'], 1) ?></strong></span>
                    <span class="badge <?= $analysis['is_oversold'] ? 'bg-success' : ($analysis['is_overbought'] ? 'bg-danger' : 'bg-secondary') ?> px-2">
                        <?= Html::encode($analysis['zone']) ?>
                    </span>
                </div>
            </div>
            <div class="card-body p-2 position-relative" style="background-color: #ffffff;">
                <div id="<?= $chartId ?>" style="width: 100%; height: 560px;"></div>
            </div>
        </div>
    </div>

    <!-- Trade Plan & Signal Metrics Panel -->
    <div class="col-lg-4">
        <!-- Trade Setup Box -->
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #e2e8f0 !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark text-uppercase small mb-0 tracking-wider">Trading Plan Setup</h6>
                    <span class="badge bg-primary px-2 py-1 font-monospace">Win-Rate: <?= (int) $analysis['confidence_score'] ?>%</span>
                </div>

                <!-- Entry Price -->
                <div class="p-3 bg-white rounded-3 shadow-sm mb-2 border border-primary border-opacity-25">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-primary bg-opacity-10 text-primary fw-bold mb-1" style="font-size:0.68rem;">TITIK ENTRY</span>
                            <div class="fs-4 fw-bold text-dark font-monospace">$<?= number_format((float) $analysis['entry_price'], 2) ?></div>
                        </div>
                        <i class="bi bi-crosshair2 fs-2 text-primary opacity-50"></i>
                    </div>
                </div>

                <!-- Stop Loss & TP1 Grid -->
                <div class="row g-2 mb-2">
                    <!-- Stop Loss -->
                    <div class="col-6">
                        <div class="p-3 bg-white rounded-3 shadow-sm border border-danger border-opacity-25 h-100">
                            <span class="badge bg-danger bg-opacity-10 text-danger fw-bold mb-1" style="font-size:0.68rem;">STOP LOSS</span>
                            <div class="fs-5 fw-bold text-danger font-monospace">$<?= number_format((float) $analysis['stop_loss'], 2) ?></div>
                            <div class="text-danger small font-monospace" style="font-size:0.75rem;">-<?= number_format((float) $analysis['stop_loss_pct'], 1) ?>%</div>
                        </div>
                    </div>

                    <!-- TP 1 -->
                    <div class="col-6">
                        <div class="p-3 bg-white rounded-3 shadow-sm border border-success border-opacity-25 h-100">
                            <span class="badge bg-success bg-opacity-10 text-success fw-bold mb-1" style="font-size:0.68rem;">TARGET 1 (TP1)</span>
                            <div class="fs-5 fw-bold text-success font-monospace">$<?= number_format((float) $analysis['target_profit_1'], 2) ?></div>
                            <div class="text-success small font-monospace" style="font-size:0.75rem;">+<?= number_format((float) $analysis['target_profit_1_pct'], 1) ?>%</div>
                        </div>
                    </div>
                </div>

                <!-- TP 2 & Risk/Reward Grid -->
                <div class="row g-2">
                    <!-- TP 2 -->
                    <div class="col-6">
                        <div class="p-2 px-3 bg-white rounded-3 shadow-sm border">
                            <div class="text-muted small fw-semibold" style="font-size:0.70rem;">TARGET 2 (TP2):</div>
                            <div class="fw-bold text-dark font-monospace">$<?= number_format((float) $analysis['target_profit_2'], 2) ?></div>
                            <div class="text-success small font-monospace" style="font-size:0.70rem;">+<?= number_format((float) $analysis['target_profit_2_pct'], 1) ?>%</div>
                        </div>
                    </div>

                    <!-- R : R Ratio -->
                    <div class="col-6">
                        <div class="p-2 px-3 bg-white rounded-3 shadow-sm border">
                            <div class="text-muted small fw-semibold" style="font-size:0.70rem;">RISK/REWARD:</div>
                            <div class="fw-bold text-primary font-monospace fs-6">1 : <?= number_format((float) $analysis['risk_reward_ratio'], 1) ?></div>
                            <div class="text-muted small" style="font-size:0.70rem;">Rasio optimal</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Signal Analysis & Explanation Card -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold text-dark mb-0">
                    <?= \app\helpers\SvgHelper::icon('info-circle', ['class' => 'text-info me-2']) ?>Ulasan Sinyal &amp; Karakteristik
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-<?= Html::encode($analysis['strategy_badge']) ?> p-2 fs-6">
                            <?= Html::encode($analysis['strategy_name']) ?>
                        </span>
                    </div>
                    <p class="text-secondary small mb-0" style="line-height: 1.6;">
                        <?= Html::encode($analysis['description']) ?>
                    </p>
                </div>

                <hr class="my-3">

                <!-- Indicator Snapshot -->
                <div class="d-flex justify-content-between align-items-center mb-2 small">
                    <span class="text-muted">Garis %K Saat Ini:</span>
                    <span class="fw-bold font-monospace" style="color: #0284c7;"><?= number_format((float) $analysis['curr_k'], 2) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 small">
                    <span class="text-muted">Garis %D (Signal):</span>
                    <span class="fw-bold font-monospace" style="color: #f59e0b;"><?= number_format((float) $analysis['curr_d'], 2) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 small">
                    <span class="text-muted">Zona Posisi:</span>
                    <span class="badge <?= $analysis['is_oversold'] ? 'bg-success' : ($analysis['is_overbought'] ? 'bg-danger' : 'bg-secondary') ?>">
                        <?= Html::encode($analysis['zone']) ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 small">
                    <span class="text-muted">Relative Volume (RVOL):</span>
                    <span class="fw-bold font-monospace <?= ($analysis['rvol'] >= 1.2) ? 'text-success' : 'text-dark' ?>">
                        <?= number_format((float) $analysis['rvol'], 2) ?>x
                    </span>
                </div>
                <?php if (!empty($analysis['divergence']['type'])): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span class="text-muted">Divergensi:</span>
                        <span class="badge bg-info text-dark font-monospace"><?= Html::encode($analysis['divergence']['name']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($analysis['shakeout_low']['detected'])): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span class="text-muted">Shakeout Sweep Low:</span>
                        <span class="fw-bold font-monospace text-warning-emphasis">
                            $<?= number_format((float) $analysis['shakeout_low']['shakeout_low'], 2) ?> (-<?= $analysis['shakeout_low']['sweep_pct'] ?>%)
                        </span>
                    </div>
                <?php endif; ?>

                <div class="mt-4 pt-2 border-top">
                    <div class="small fw-bold text-muted mb-2">Panduan Aksi:</div>
                    <ul class="list-unstyled small text-muted mb-0 d-flex flex-column gap-2">
                        <li class="d-flex gap-2">
                            <i class="bi bi-check-circle-fill text-success flex-shrink-0 mt-1"></i>
                            <span>Beli di area <strong>$<?= number_format((float) $analysis['entry_price'], 2) ?></strong> dengan batas toleransi &plusmn;0.5%.</span>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-shield-x text-danger flex-shrink-0 mt-1"></i>
                            <span>Disiplin pasang Stop Loss di <strong>$<?= number_format((float) $analysis['stop_loss'], 2) ?></strong> (disiplin cut loss).</span>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-trophy-fill text-primary flex-shrink-0 mt-1"></i>
                            <span>Take profit parsial (50%) di TP1 <strong>$<?= number_format((float) $analysis['target_profit_1'], 2) ?></strong> dan sisa posisi di TP2 <strong>$<?= number_format((float) $analysis['target_profit_2'], 2) ?></strong>.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartDom = document.getElementById('<?= $chartId ?>');
    if (!chartDom || typeof echarts === 'undefined') return;

    const myChart = echarts.init(chartDom);

    const dates = <?= json_encode($dates) ?>;
    const ohlc = <?= json_encode($ohlc) ?>;
    const volData = <?= json_encode($volData) ?>;
    const kSeries = <?= json_encode($kSeries) ?>;
    const dSeries = <?= json_encode($dSeries) ?>;
    const markLines = <?= json_encode($markLines) ?>;

    const option = {
        animation: true,
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'cross'
            },
            borderWidth: 1,
            borderColor: '#ccc',
            padding: 10,
            textStyle: {
                color: '#000'
            }
        },
        axisPointer: {
            link: [{ xAxisIndex: 'all' }]
        },
        grid: [
            // Panel 1: Candlestick Price (Top 62%)
            {
                left: '60',
                right: '40',
                top: '30',
                height: '56%'
            },
            // Panel 2: Stochastic Oscillator (Bottom 26%)
            {
                left: '60',
                right: '40',
                top: '67%',
                height: '24%'
            }
        ],
        xAxis: [
            {
                type: 'category',
                data: dates,
                boundaryGap: true,
                axisLine: { onZero: false, lineStyle: { color: '#cbd5e1' } },
                axisLabel: { color: '#64748b', fontSize: 11 },
                splitLine: { show: false },
                min: 'dataMin',
                max: 'dataMax'
            },
            {
                type: 'category',
                gridIndex: 1,
                data: dates,
                boundaryGap: true,
                axisLine: { onZero: false, lineStyle: { color: '#cbd5e1' } },
                axisTick: { show: false },
                splitLine: { show: false },
                axisLabel: { show: false },
                min: 'dataMin',
                max: 'dataMax'
            }
        ],
        yAxis: [
            // Y-Axis Panel 1: Price
            {
                scale: true,
                splitArea: { show: false },
                splitLine: { show: true, lineStyle: { color: '#f1f5f9' } },
                axisLabel: {
                    color: '#64748b',
                    formatter: function (v) { return '$' + v.toFixed(2); }
                }
            },
            // Y-Axis Panel 2: Stochastic 0 - 100
            {
                gridIndex: 1,
                min: 0,
                max: 100,
                interval: 20,
                splitLine: { show: true, lineStyle: { color: '#f1f5f9' } },
                axisLabel: { color: '#64748b', fontSize: 10 }
            }
        ],
        dataZoom: [
            {
                type: 'inside',
                xAxisIndex: [0, 1],
                start: Math.max(0, 100 - Math.round((50 / Math.max(dates.length, 1)) * 100)),
                end: 100
            },
            {
                show: true,
                xAxisIndex: [0, 1],
                type: 'slider',
                top: '94%',
                height: 18,
                start: Math.max(0, 100 - Math.round((50 / Math.max(dates.length, 1)) * 100)),
                end: 100,
                borderColor: '#e2e8f0'
            }
        ],
        series: [
            // Series 1: Candlesticks
            {
                name: 'Price (OHLC)',
                type: 'candlestick',
                data: ohlc,
                itemStyle: {
                    color: '#10b981',
                    color0: '#ef4444',
                    borderColor: '#10b981',
                    borderColor0: '#ef4444'
                },
                markLine: {
                    symbol: ['none', 'none'],
                    data: markLines
                }
            },
            // Series 2: Stochastic %K
            {
                name: '%K (Fast/Main)',
                type: 'line',
                xAxisIndex: 1,
                yAxisIndex: 1,
                data: kSeries,
                showSymbol: false,
                smooth: true,
                lineStyle: {
                    color: '#0284c7',
                    width: 2.2
                },
                markLine: {
                    symbol: ['none', 'none'],
                    data: [
                        {
                            yAxis: 80,
                            name: 'Overbought (80)',
                            lineStyle: { color: '#ef4444', type: 'dashed', width: 1.2 },
                            label: { formatter: 'OB (80)', position: 'end', color: '#ef4444', fontSize: 10 }
                        },
                        {
                            yAxis: 50,
                            name: 'Midline (50)',
                            lineStyle: { color: '#94a3b8', type: 'dotted', width: 1 },
                            label: { formatter: '50', position: 'end', color: '#94a3b8', fontSize: 10 }
                        },
                        {
                            yAxis: 20,
                            name: 'Oversold (20)',
                            lineStyle: { color: '#10b981', type: 'dashed', width: 1.2 },
                            label: { formatter: 'OS (20)', position: 'end', color: '#10b981', fontSize: 10 }
                        }
                    ]
                }
            },
            // Series 3: Stochastic %D
            {
                name: '%D (Signal)',
                type: 'line',
                xAxisIndex: 1,
                yAxisIndex: 1,
                data: dSeries,
                showSymbol: false,
                smooth: true,
                lineStyle: {
                    color: '#f59e0b',
                    width: 1.8
                }
            }
        ]
    };

    myChart.setOption(option);
    window.addEventListener('resize', () => myChart.resize());
});
</script>
