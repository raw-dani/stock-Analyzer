<?php

/** @var yii\web\View $this */
/** @var app\models\Stock $stock */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string[] $chartDates */
/** @var float[] $chartBuyRatio */
/** @var int[] $chartScores */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;
use yii\grid\GridView;

$this->title = 'Signal History — ' . $stock->symbol;
$this->params['breadcrumbs'][] = ['label' => 'Signal History', 'url' => ['index']];
$this->params['breadcrumbs'][] = $stock->symbol;
?>

<div class="row mb-3">
    <div class="col">
        <h1 class="h3 d-inline">
            <?= Html::encode($stock->symbol) ?> <small class="text-muted"><?= Html::encode($stock->name) ?></small>
        </h1>
        <span class="text-muted small">— perkembangan buying pressure & score sinyal</span>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">Mini Chart — Buying Pressure &amp; Score per Tanggal Sinyal</div>
    <div class="card-body">
        <div id="chart-signal-history" style="width:100%;height:300px;"></div>
    </div>
</div>

<?php if (empty($chartDates)): ?>
    <p class="text-muted">Belum ada sinyal tersimpan untuk simbol ini.</p>
<?php else: ?>
<script>
(function () {
    var dom = document.getElementById('chart-signal-history');
    if (!dom || typeof echarts === 'undefined') { return; }

    var option = {
        tooltip: { trigger: 'axis' },
        legend: { data: ['Buy Ratio', 'Score'] },
        grid: { left: '3%', right: '4%', bottom: '10%', containLabel: true },
        xAxis: { type: 'category', data: <?= json_encode($chartDates) ?> },
        yAxis: [
            { type: 'value', position: 'left', name: 'Buy Ratio', min: 0, max: 1 },
            { type: 'value', position: 'right', name: 'Score', min: 0, max: 100 },
        ],
        series: [
            {
                name: 'Buy Ratio',
                type: 'line',
                yAxisIndex: 0,
                data: <?= json_encode($chartBuyRatio) ?>,
                lineStyle: { color: '#91cc75' },
                areaStyle: { color: 'rgba(145, 204, 117, 0.15)' },
            },
            {
                name: 'Score',
                type: 'line',
                yAxisIndex: 1,
                data: <?= json_encode($chartScores) ?>,
                lineStyle: { color: '#5470c6' },
            },
        ]
    };

    echarts.init(dom).setOption(option);
})();
</script>
<?php endif; ?>

<h4 class="h5">Riwayat Sinyal</h4>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-sm table-hover align-middle'],
    'columns' => [
        ['attribute' => 'date', 'format' => 'date'],
        ['attribute' => 'price', 'value' => fn ($m) => Yii::$app->formatter->asCurrency((float) $m->price)],
        'score',
        [
            'attribute' => 'signal',
            'value' => fn ($m) => SignalBadge::widget(['signal' => $m->signal]),
            'format' => 'raw',
        ],
        ['attribute' => 'buy_ratio', 'value' => fn ($m) => Yii::$app->formatter->asRatioPercent((float) $m->buy_ratio)],
        ['attribute' => 'rvol', 'value' => fn ($m) => round((float) $m->rvol, 2) . 'x'],
        [
            'attribute' => 'volume_growth',
            'value' => fn ($m) => $m->volume_growth !== null ? Yii::$app->formatter->asPercent((float) $m->volume_growth) : '-',
        ],
        [
            'label' => 'Reasons',
            'value' => function ($m) {
                $reasons = $m->getReasonList();
                return empty($reasons) ? '-' : Html::ul($reasons, ['class' => 'mb-0 ps-3 small', 'encode' => false]);
            },
            'format' => 'raw',
        ],
    ],
]) ?>
