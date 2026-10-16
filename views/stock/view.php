<?php

/** @var yii\web\View $this */
/** @var app\models\Stock $stock */
/** @var app\models\WeeklyAnalysis|null $currentWeekly */
/** @var app\models\Signal|null $currentSignal */
/** @var yii\data\ActiveDataProvider $weeklyProvider */
/** @var array|null $priceStats */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;
use yii\grid\GridView;
use yii\helpers\Url;

$this->title = $stock->symbol . ' - ' . $stock->name;
$this->params['breadcrumbs'][] = ['label' => 'Stocks', 'url' => ['/scanner/index']];
$this->params['breadcrumbs'][] = $stock->symbol;

$currentPrice = $priceStats['current_price'] ?? $stock->price ?? 0.0;
$change = $priceStats['change'] ?? 0.0;
$changePct = $priceStats['change_pct'] ?? 0.0;
$isUp = $change >= 0;
$changeClass = $isUp ? 'text-success' : 'text-danger';
$changeIcon = $isUp ? 'arrow-up' : 'arrow-down';
?>

<!-- Header Saham & Aksi Cepat -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h1 class="h3 mb-0 fw-bold text-dark"><?= Html::encode($stock->symbol) ?></h1>
            <span class="badge bg-light text-secondary border fs-6"><?= Html::encode($stock->exchange ?? 'US') ?></span>
            <?php if (!empty($stock->sector)): ?>
                <span class="badge bg-secondary fs-7"><?= Html::encode($stock->sector) ?></span>
            <?php endif; ?>
            <?= SignalBadge::widget(['signal' => $currentWeekly->signal ?? null]) ?>
        </div>
        <p class="text-muted small mb-0">
            <?= Html::encode($stock->name) ?>
            <?php if (!empty($stock->industry)): ?>
                &bull; Industri: <strong><?= Html::encode($stock->industry) ?></strong>
            <?php endif; ?>
            <?php if (!empty($priceStats['date'])): ?>
                &bull; Terakhir Diperbarui: <strong><?= Html::encode($priceStats['date']) ?></strong>
            <?php endif; ?>
        </p>
    </div>

    <!-- Hubungan Antar Modul (Cross-Module Fast Actions) -->
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="<?= Url::to(['/moving-average/detail', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-primary btn-sm shadow-sm" title="Lihat Setup Moving Average & Level Stop Loss/TP">
            <?= \app\helpers\SvgHelper::icon('graph-up') ?> Trading Plan MA
        </a>
        <a href="<?= Url::to(['/rsi-double-bottom/detail', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-info btn-sm shadow-sm" title="Lihat Analisis Pola RSI Double Bottom">
            <?= \app\helpers\SvgHelper::icon('activity') ?> Analisis RSI
        </a>
        <?php if (!Yii::$app->user->isGuest): ?>
            <a href="<?= Url::to(['/portfolio/index']) ?>" class="btn btn-outline-success btn-sm shadow-sm" title="Buka Portofolio">
                <?= \app\helpers\SvgHelper::icon('briefcase') ?> Portofolio
            </a>
        <?php endif; ?>
        <a href="<?= Url::to(['/scanner/index', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
            <?= \app\helpers\SvgHelper::icon('search') ?> Scanner
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Harga & Metrik Terkini -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-light h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase">Harga Terkini</div>
                <div class="h3 mb-0 fw-bold text-dark">
                    <?= Yii::$app->formatter->asCurrency($currentPrice) ?>
                </div>
                <div class="small <?= $changeClass ?> mt-1 fw-bold">
                    <?= \app\helpers\SvgHelper::icon($changeIcon) ?>
                    <?= ($change >= 0 ? '+' : '') . number_format($change, 2) ?> (<?= ($changePct >= 0 ? '+' : '') . number_format($changePct, 2) ?>%)
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-light h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase">Rentang Hari Ini (High / Low)</div>
                <div class="h5 mb-0 fw-bold text-dark mt-1">
                    <?php if (!empty($priceStats)): ?>
                        <?= Yii::$app->formatter->asCurrency($priceStats['high']) ?> <span class="text-muted fw-normal">/</span> <?= Yii::$app->formatter->asCurrency($priceStats['low']) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">
                    Open: <strong><?= !empty($priceStats['open']) ? Yii::$app->formatter->asCurrency($priceStats['open']) : '-' ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-light h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase">Volume Candle Harian</div>
                <div class="h5 mb-0 fw-bold text-dark mt-1">
                    <?= !empty($priceStats['volume']) ? number_format((int) $priceStats['volume']) : '-' ?>
                </div>
                <div class="small text-muted mt-1">
                    Weekly RVOL: <strong><?= $currentWeekly?->rvol !== null ? round($currentWeekly->rvol, 2) . 'x' : '-' ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-light h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase">Skor &amp; Sinyal Mingguan</div>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <div class="h4 mb-0 fw-bold text-dark"><?= $currentWeekly?->score ?? 0 ?>/100</div>
                    <div><?= SignalBadge::widget(['signal' => $currentWeekly?->signal ?? null]) ?></div>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-<?= ($currentWeekly?->score ?? 0) >= 65 ? 'success' : (($currentWeekly?->score ?? 0) >= 35 ? 'warning' : 'danger') ?>"
                         role="progressbar" style="width: <?= min(100, max(0, $currentWeekly?->score ?? 0)) ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($currentWeekly !== null): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
        <strong class="small text-secondary"><?= \app\helpers\SvgHelper::icon('calendar-week', ['class' => 'text-primary me-1']) ?> Analisis Mingguan (Minggu Dimulai: <?= Html::encode($currentWeekly->week_start) ?>)</strong>
        <span class="badge bg-light text-dark border">Confidence Score: <?= $currentWeekly->score ?>%</span>
    </div>
    <div class="card-body p-3">
        <div class="row g-3 text-center">
            <div class="col-4 col-md-2 border-end">
                <div class="text-muted small">Buy Ratio</div>
                <div class="fw-bold fs-6 text-success"><?= Yii::$app->formatter->asRatioPercent($currentWeekly->buy_ratio) ?></div>
            </div>
            <div class="col-4 col-md-2 border-end">
                <div class="text-muted small">Sell Ratio</div>
                <div class="fw-bold fs-6 text-danger"><?= Yii::$app->formatter->asRatioPercent($currentWeekly->sell_ratio) ?></div>
            </div>
            <div class="col-4 col-md-2 border-end">
                <div class="text-muted small">Vol Growth</div>
                <div class="fw-bold fs-6"><?= $currentWeekly->volume_growth !== null ? Yii::$app->formatter->asPercent($currentWeekly->volume_growth) : '-' ?></div>
            </div>
            <div class="col-6 col-md-3 border-end">
                <div class="text-muted small">Aktivitas RVOL</div>
                <div class="fw-bold fs-6 text-primary"><?= $currentWeekly->rvol !== null ? round($currentWeekly->rvol, 2) . 'x Volume Rata-rata' : '-' ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">Klasifikasi Sinyal</div>
                <div class="mt-1"><?= SignalBadge::widget(['signal' => $currentWeekly->signal]) ?></div>
            </div>
        </div>

        <?php if ($currentSignal !== null && count($currentSignal->getReasonList()) > 0): ?>
            <hr class="my-3">
            <div>
                <div class="small fw-bold text-muted mb-2"><?= \app\helpers\SvgHelper::icon('check2-circle', ['class' => 'text-success me-1']) ?> Alasan &amp; Konfirmasi Sinyal:</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($currentSignal->getReasonList() as $reason): ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 fw-normal small">
                            <?= \app\helpers\SvgHelper::icon('check-lg', ['class' => 'me-1']) ?> <?= Html::encode($reason) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Chart Candlestick & Moving Averages -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0 fw-bold text-dark">
            <?= \app\helpers\SvgHelper::icon('graph-up', ['class' => 'text-primary me-1']) ?> Grafik Harga &amp; Volume (Candlestick + MA20/MA50/MA200)
        </h5>
        <div class="d-flex align-items-center gap-2 small">
            <span class="badge bg-warning text-dark"><span style="color:#ff6600;">■</span> MA20</span>
            <span class="badge bg-primary text-white"><span style="color:#0066ff;">■</span> MA50</span>
            <span class="badge bg-secondary text-white"><span style="color:#9b59b6;">■</span> MA200</span>
            <span class="text-muted ms-2" style="font-size:0.75rem;"><?= \app\helpers\SvgHelper::icon('mouse') ?> Scroll untuk zoom</span>
        </div>
    </div>
    <div class="card-body p-3">
        <div id="chart-stock" style="width:100%;height:520px;"></div>
    </div>
</div>

<script>
(function () {
    var chartDom = document.getElementById('chart-stock');
    if (!chartDom || typeof echarts === 'undefined') { return; }

    var myChart = echarts.init(chartDom);
    myChart.showLoading();

    fetch('<?= Url::to(['chart-data', 'symbol' => $stock->symbol]) ?>')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            myChart.hideLoading();
            if (!d.dates || d.dates.length === 0) {
                chartDom.innerHTML = '<div class="text-center py-5 text-muted">Data historis belum tersedia.</div>';
                return;
            }

            var totalCount = d.dates.length;
            // Default tampilkan 70 candle terakhir
            var startPercent = totalCount > 70 ? Math.floor(((totalCount - 70) / totalCount) * 100) : 0;

            var option = {
                tooltip: {
                    trigger: 'axis',
                    axisPointer: { type: 'cross' }
                },
                grid: [
                    { left: '3%', right: '2%', top: '8%', height: '60%', containLabel: true },
                    { left: '3%', right: '2%', top: '74%', height: '18%', containLabel: true }
                ],
                xAxis: [
                    {
                        type: 'category',
                        data: d.dates,
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
                        data: d.dates,
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
                        start: startPercent,
                        end: 100
                    },
                    {
                        show: true,
                        xAxisIndex: [0, 1],
                        type: 'slider',
                        bottom: '2%',
                        start: startPercent,
                        end: 100
                    }
                ],
                series: [
                    {
                        name: 'Candlestick',
                        type: 'candlestick',
                        data: d.kline,
                        itemStyle: {
                            color: '#26a69a',
                            color0: '#ef5350',
                            borderColor: '#26a69a',
                            borderColor0: '#ef5350'
                        }
                    },
                    {
                        name: 'MA20',
                        type: 'line',
                        data: d.ma20,
                        smooth: true,
                        lineStyle: { color: '#ff6600', width: 1.5 }
                    },
                    {
                        name: 'MA50',
                        type: 'line',
                        data: d.ma50,
                        smooth: true,
                        lineStyle: { color: '#0066ff', width: 1.5 }
                    },
                    {
                        name: 'MA200',
                        type: 'line',
                        data: d.ma200 || [],
                        smooth: true,
                        lineStyle: { color: '#9b59b6', width: 1.5 }
                    },
                    {
                        name: 'Volume',
                        type: 'bar',
                        xAxisIndex: 1,
                        yAxisIndex: 1,
                        data: d.volumes.map(function (v) {
                            return {
                                value: v[1],
                                itemStyle: { color: v[0] === 1 ? '#26a69a' : '#ef5350' }
                            };
                        })
                    }
                ]
            };

            myChart.setOption(option);
            window.addEventListener('resize', function () { myChart.resize(); });
        })
        .catch(function (err) {
            myChart.hideLoading();
            console.error('Chart data fetch error:', err);
        });
})();
</script>

<!-- Trend Mingguan RVOL & Buying Pressure -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark">
            <?= \app\helpers\SvgHelper::icon('bar-chart-steps', ['class' => 'text-info me-1']) ?> Tren Mingguan RVOL &amp; Tekanan Beli (Buying Pressure)
        </h5>
    </div>
    <div class="card-body p-3">
        <div id="chart-weekly-trend" style="width:100%;height:320px;"></div>
    </div>
</div>

<script>
(function () {
    var trendDom = document.getElementById('chart-weekly-trend');
    if (!trendDom || typeof echarts === 'undefined') { return; }

    var trendChart = echarts.init(trendDom);
    trendChart.showLoading();

    fetch('<?= Url::to(['chart-data', 'symbol' => $stock->symbol]) ?>')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            trendChart.hideLoading();
            if (!d.weeklyDates || d.weeklyDates.length === 0) {
                trendDom.innerHTML = '<div class="text-center py-4 text-muted">Data mingguan belum tersedia.</div>';
                return;
            }

            var trendOpt = {
                tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
                grid: { left: '3%', right: '3%', bottom: '12%', containLabel: true },
                xAxis: { type: 'category', data: d.weeklyDates },
                yAxis: [
                    { type: 'value', position: 'left', name: 'RVOL' },
                    { type: 'value', position: 'right', name: 'Buy Pressure', min: 0, max: 1 }
                ],
                series: [
                    {
                        name: 'RVOL',
                        type: 'line',
                        yAxisIndex: 0,
                        data: d.weeklyRvol,
                        smooth: true,
                        lineStyle: { color: '#9a60b5', width: 2 },
                        areaStyle: { color: 'rgba(154, 96, 181, 0.12)' }
                    },
                    {
                        name: 'Buy Pressure',
                        type: 'bar',
                        yAxisIndex: 1,
                        data: d.weeklyBuyPressure.map(function (v) { return v !== null ? v : '-'; }),
                        itemStyle: { color: '#26a69a' }
                    }
                ]
            };

            trendChart.setOption(trendOpt);
            window.addEventListener('resize', function () { trendChart.resize(); });
        })
        .catch(function (err) {
            trendChart.hideLoading();
            console.error('Trend chart fetch error:', err);
        });
})();
</script>

<!-- Riwayat Analisis Mingguan -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h5 class="mb-0 fw-bold text-dark">
            <?= \app\helpers\SvgHelper::icon('clock-history', ['class' => 'text-secondary me-1']) ?> Riwayat Analisis Mingguan
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <?= GridView::widget([
                'dataProvider' => $weeklyProvider,
                'tableOptions' => ['class' => 'table table-hover align-middle mb-0'],
                'columns' => [
                    [
                        'attribute' => 'week_start',
                        'header' => 'Awal Minggu',
                        'format' => 'raw',
                        'value' => function ($m) {
                            return '<strong>' . Html::encode($m->week_start) . '</strong>';
                        },
                    ],
                    [
                        'attribute' => 'buy_ratio',
                        'header' => 'Buy Ratio',
                        'format' => 'percent',
                        'value' => function ($m) { return $m->buy_ratio; },
                    ],
                    [
                        'attribute' => 'sell_ratio',
                        'header' => 'Sell Ratio',
                        'format' => 'percent',
                        'value' => function ($m) { return $m->sell_ratio; },
                    ],
                    [
                        'attribute' => 'volume_growth',
                        'header' => 'Vol Growth',
                        'format' => 'percent',
                        'value' => function ($m) { return $m->volume_growth; },
                    ],
                    [
                        'attribute' => 'rvol',
                        'header' => 'RVOL',
                        'value' => function ($m) { return $m->rvol !== null ? round($m->rvol, 2) . 'x' : '-'; },
                    ],
                    [
                        'attribute' => 'score',
                        'header' => 'Skor',
                        'format' => 'raw',
                        'value' => function ($m) {
                            $badge = $m->score >= 65 ? 'success' : ($m->score >= 35 ? 'warning text-dark' : 'danger');
                            return '<span class="badge bg-' . $badge . '">' . $m->score . '/100</span>';
                        },
                    ],
                    [
                        'attribute' => 'signal',
                        'header' => 'Sinyal',
                        'value' => function ($m) { return SignalBadge::widget(['signal' => $m->signal]); },
                        'format' => 'raw',
                    ],
                ],
            ]) ?>
        </div>
    </div>
</div>
