<?php

/** @var yii\web\View $this */
/** @var \app\models\Stock $stock */
/** @var array|null $pattern */
/** @var int $timeframe */
/** @var int $lookback */
/** @var int $lookbackDays */
/** @var string $patternType */
/** @var array $timeframes */
/** @var array $patternTypes */
/** @var array $filterParams */
/** @var array $candles */

use yii\bootstrap5\Html;
use app\assets\ChartAsset;
use app\services\BullishPatternService;

ChartAsset::register($this);

$patternType = $patternType ?? ($pattern['pattern_type'] ?? BullishPatternService::PATTERN_ALL);
$lookbackDays = $lookbackDays ?? $lookback;

$badge = null;
if ($pattern !== null) {
    $badge = BullishPatternService::getPatternBadge($pattern['pattern_type'] ?? '');
    $patternTitle = $pattern['pattern_name'] ?? 'Bullish Continuation Pattern';
} else {
    $patternTitle = 'Analisis Pola Bullish';
}

$this->title = $patternTitle . ' - ' . $stock->symbol;
$this->params['breadcrumbs'][] = ['label' => 'Bullish Breakout Patterns', 'url' => array_merge(['bullish-pattern/index'], $filterParams)];
$this->params['breadcrumbs'][] = $stock->symbol;
$chartId = 'bullish-pattern-chart';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="h3 mb-0 fw-bold"><?= Html::encode($stock->symbol) ?></h1>
            <span class="text-muted fs-6"><?= Html::encode($stock->name) ?></span>
            <span class="badge bg-secondary"><?= Html::encode($stock->sector ?? 'Stock') ?></span>
            <span class="badge bg-light text-dark border"><?= Html::encode($stock->exchange ?? 'IDX') ?></span>
        </div>
        <div>
            <?php if ($pattern !== null && $badge): ?>
                <span class="badge <?= Html::encode($badge['class'] ?? 'text-white') ?> px-2 py-1 fs-6" style="background-color: <?= Html::encode($badge['color'] ?? '#0d6efd') ?>;">
                    <?= \app\helpers\SvgHelper::icon(str_replace('bi-', '', $badge['icon'] ?? 'graph-up')) ?> <?= Html::encode($badge['label'] ?? 'Bullish') ?>
                </span>
                <?php if (!empty($pattern['breakout'])): ?>
                    <span class="badge bg-success"><?= \app\helpers\SvgHelper::icon('lightning-charge-fill') ?> Breakout Aktif</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark"><?= \app\helpers\SvgHelper::icon('hourglass-split') ?> Setup Konsolidasi</span>
                <?php endif; ?>

                <?php if (!empty($pattern['bull_trap_detected'])): ?>
                    <span class="badge bg-danger animate__animated animate__flash"><?= \app\helpers\SvgHelper::icon('exclamation-triangle-fill') ?> Bull Trap Alert</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?= Html::a(\app\helpers\SvgHelper::icon('arrow-left') . ' Kembali ke Scanner', array_merge(['bullish-pattern/index'], $filterParams), ['class' => 'btn btn-outline-secondary']) ?>
    </div>
</div>

<!-- Form Parameter Deteksi Pola -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-light py-2">
        <strong class="small text-secondary"><?= \app\helpers\SvgHelper::icon('sliders') ?> Parameter Deteksi Pola Bullish</strong>
    </div>
    <div class="card-body">
        <?= Html::beginForm(['bullish-pattern/detail'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <?= Html::hiddenInput('symbol', $stock->symbol) ?>
            <div class="col-md-3 col-6">
                <label class="form-label small fw-bold">Timeframe Analisis</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-4 col-6">
                <label class="form-label small fw-bold">Tipe Pola Dicari</label>
                <?= Html::dropDownList('patternType', $patternType, $patternTypes, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small fw-bold">Lookback Periode (Candle)</label>
                <?= Html::input('number', 'lookback', $lookbackDays, ['class' => 'form-control form-control-sm', 'min' => 15, 'max' => 500]) ?>
            </div>
            <div class="col-md-2 col-6 text-end">
                <?= Html::submitButton(\app\helpers\SvgHelper::icon('arrow-repeat') . ' Update Analisis', ['class' => 'btn btn-sm btn-primary w-100']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<?php if ($pattern === null): ?>
    <div class="alert alert-info shadow-sm p-4 border-0">
        <div class="d-flex align-items-center mb-2">
            <?= \app\helpers\SvgHelper::icon('info-circle-fill', ['class' => 'fs-3 text-info me-3']) ?>
            <div>
                <h5 class="mb-0 fw-bold">Pola Bullish Tidak Terdeteksi</h5>
                <p class="mb-0 text-muted">Tidak ditemukan pola kelanjutan/breakout (Cup and Handle, Rectangle, Pennant, Flag, atau Ascending Triangle) yang memenuhi kriteria untuk <strong><?= Html::encode($stock->symbol) ?></strong> dengan parameter saat ini.</p>
            </div>
        </div>
        <div class="mt-3 ps-5">
            <p class="small text-muted mb-2">Tips untuk menemukan pola:</p>
            <ul class="small text-muted mb-3">
                <li>Ubah <strong>Tipe Pola Dicari</strong> menjadi <em>Semua Pola Bullish</em>.</li>
                <li>Sesuaikan <strong>Lookback</strong> (misal 60, 90, atau 120 hari) untuk menyesuaikan rentang formasi.</li>
                <li>Ubah <strong>Timeframe</strong> ke 1 Jam atau 4 Jam jika data intraday tersedia.</li>
            </ul>
            <?= Html::a(\app\helpers\SvgHelper::icon('search') . ' Reset ke Semua Pola &amp; Lookback 90', ['bullish-pattern/detail', 'symbol' => $stock->symbol, 'timeframe' => BullishPatternService::DEFAULT_TIMEFRAME, 'lookback' => 90, 'patternType' => BullishPatternService::PATTERN_ALL], ['class' => 'btn btn-sm btn-outline-primary']) ?>
        </div>
    </div>
<?php else: ?>
    <?= $this->render('_pattern_details', ['pattern' => $pattern]) ?>
<?php endif; ?>

<!-- Chart Candlestick & Volume dengan Mark Points, Resistance & Targets -->
<div class="card mt-4 shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold">
                <?= \app\helpers\SvgHelper::icon('graph-up', ['class' => 'text-primary']) ?> Grafik Pola <?= Html::encode($patternTitle) ?> &amp; Volume
            </h5>
            <small class="text-muted"><?= Html::encode($stock->symbol) ?> - Candlestick Dual-Grid dengan Level Beli, Target Jual &amp; Stop Loss</small>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border">Candles: <?= count($candles) ?></span>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($candles)): ?>
            <p class="text-muted mb-0">Data candle tidak tersedia untuk saham ini.</p>
        <?php else: ?>
            <div id="<?= $chartId ?>" style="height: 560px; width: 100%;"></div>

            <!-- Petunjuk Garis Level & Titik Penanda -->
            <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top small">
                <span class="fw-bold me-2 text-secondary">Legenda Level:</span>
                <span class="badge bg-primary" title="Level penembusan konfirmasi pola"><?= \app\helpers\SvgHelper::icon('dash-lg') ?> Garis Resistance Breakout</span>
                <span class="badge bg-info text-dark" title="Area ideal beli 0 - 3% dari resistance"><?= \app\helpers\SvgHelper::icon('dot') ?> Batas Golden Zone (+3%)</span>
                <span class="badge bg-secondary" title="Level lantai konsolidasi support"><?= \app\helpers\SvgHelper::icon('dash') ?> Garis Support Base</span>
                <span class="badge bg-success" title="Target Jual Pertama (50% Pattern Height)"><?= \app\helpers\SvgHelper::icon('dash') ?> TP1 (50%)</span>
                <span class="badge bg-success" style="background-color: #198754 !important;" title="Target Jual Utama (100% Measured Move)"><?= \app\helpers\SvgHelper::icon('dash-lg') ?> TP2 (100% Target)</span>
                <span class="badge text-white" style="background-color: #0f5132 !important;" title="Target Jual Ekstensi Fibonacci 161.8%"><?= \app\helpers\SvgHelper::icon('dot') ?> TP3 (161.8% Fib)</span>
                <span class="badge bg-warning text-dark" title="Stop Loss ketat di bawah resistance / handle"><?= \app\helpers\SvgHelper::icon('dash') ?> SL Ketat (Tight)</span>
                <span class="badge bg-danger" title="Stop Loss swing di bawah lantai pola (invalidation)"><?= \app\helpers\SvgHelper::icon('dash-lg') ?> SL Swing (Support)</span>
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

            // Anatomi Mark Points spesifik per tipe pola
            $markPoints = [];
            if ($pattern !== null) {
                $pType = $pattern['pattern_type'] ?? '';
                $pointDefs = [];

                if ($pType === BullishPatternService::PATTERN_CUP_AND_HANDLE) {
                    $pointDefs = [
                        ['left_rim_date', 'left_rim_price', 'Puncak Kiri (Left Rim)', '#0d6efd'],
                        ['cup_low_date', 'cup_low_price', 'Dasar Mangkok (Cup Low)', '#6f42c1'],
                        ['right_rim_date', 'right_rim_price', 'Puncak Kanan (Right Rim)', '#0d6efd'],
                        ['handle_low_date', 'handle_low_price', 'Lembah Gagang (Handle Low)', '#fd7e14'],
                    ];
                } elseif ($pType === BullishPatternService::PATTERN_BULLISH_FLAG) {
                    $pointDefs = [
                        ['pole_start_date', 'pole_start_price', 'Awal Tiang (Pole Base)', '#20c997'],
                        ['pole_peak_date', 'pole_peak_price', 'Puncak Tiang (Pole Peak)', '#0d6efd'],
                    ];
                } elseif ($pType === BullishPatternService::PATTERN_BULLISH_PENNANT) {
                    $pointDefs = [
                        ['pole_start_date', 'pole_start_price', 'Awal Tiang (Pole Base)', '#20c997'],
                        ['pole_peak_date', 'pole_peak_price', 'Puncak Tiang (Pole Peak)', '#0d6efd'],
                    ];
                } elseif ($pType === BullishPatternService::PATTERN_ASCENDING_TRIANGLE) {
                    $pointDefs = [
                        ['high1_date', 'ceiling_price', 'Atap 1 (Ceiling High)', '#0d6efd'],
                        ['high2_date', 'ceiling_price', 'Atap 2 (Ceiling High)', '#0d6efd'],
                        ['low_start_date', 'low_start_price', 'Support Awal (Low 1)', '#6c757d'],
                        ['low_recent_date', 'low_recent_price', 'Support Naik (Higher Low)', '#20c997'],
                    ];
                }

                foreach ($pointDefs as [$dk, $pk, $label, $color]) {
                    if (!empty($pattern[$dk]) && isset($pattern[$pk])) {
                        $idx = array_search($pattern[$dk], $dates, true);
                        if ($idx !== false) {
                            $markPoints[] = [
                                'name' => $label,
                                'coord' => [$dates[$idx], (float) $pattern[$pk]],
                                'value' => $label,
                                'itemStyle' => ['color' => $color],
                            ];
                        }
                    }
                }
            }

            // Garis Horizontal Kunci (Mark Lines)
            $markLines = [];
            if ($pattern !== null) {
                $levels = [
                    ['resistance_line', 'Resistance Breakout', '#0d6efd', 'solid', 2],
                    ['entry_zone_max', 'Golden Zone (+3%)', '#0dcaf0', 'dotted', 1.5],
                    ['support_base', 'Support Base', '#6c757d', 'dashed', 1.5],
                    ['tp1_price', 'TP1 (50%)', '#20c997', 'dashed', 1.5],
                    ['tp2_price', 'TP2 (100% Target)', '#198754', 'solid', 2],
                    ['tp3_price', 'TP3 (161.8% Fib)', '#0f5132', 'dotted', 1.5],
                    ['stop_loss_tight', 'SL Ketat (Tight)', '#fd7e14', 'dashed', 1.5],
                    ['stop_loss_swing', 'SL Swing (Support)', '#dc3545', 'solid', 1.5],
                ];

                foreach ($levels as [$key, $label, $color, $type, $width]) {
                    if (isset($pattern[$key]) && (float) $pattern[$key] > 0) {
                        $markLines[] = [
                            'name' => $label,
                            'yAxis' => (float) $pattern[$key],
                            'lineStyle' => ['color' => $color, 'type' => $type, 'width' => $width],
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
                    ['left' => '50', 'right' => '65', 'top' => '30', 'height' => '56%'],
                    ['left' => '50', 'right' => '65', 'top' => '70%', 'height' => '18%'],
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
                    [
                        'scale' => true,
                        'splitArea' => ['show' => true],
                    ],
                    [
                        'scale' => true,
                        'gridIndex' => 1,
                        'splitNumber' => 2,
                        'axisLabel' => ['show' => false],
                        'axisLine' => ['show' => false],
                        'axisTick' => ['show' => false],
                        'splitLine' => ['show' => false],
                    ],
                ],
                'dataZoom' => [
                    ['type' => 'inside', 'xAxisIndex' => [0, 1], 'start' => max(0, 100 - min(100, (int)($lookbackDays * 1.2))), 'end' => 100],
                    ['show' => true, 'xAxisIndex' => [0, 1], 'type' => 'slider', 'bottom' => '5', 'start' => max(0, 100 - min(100, (int)($lookbackDays * 1.2))), 'end' => 100],
                ],
                'series' => [
                    [
                        'name' => 'Harga (Candlestick)',
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
