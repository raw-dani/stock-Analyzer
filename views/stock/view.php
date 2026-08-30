<?php

/** @var yii\web\View $this */
/** @var app\models\Stock $stock */
/** @var app\models\WeeklyAnalysis|null $currentWeekly */
/** @var yii\data\ActiveDataProvider $weeklyProvider */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;
use yii\widgets\DetailView;
use yii\grid\GridView;

$this->title = $stock->symbol;
$this->params['breadcrumbs'][] = ['label' => 'Stock', 'url' => ['/scanner/index']];
$this->params['breadcrumbs'][] = ['label' => $stock->symbol, 'url' => ['view', 'symbol' => $stock->symbol]];
?>

<div class="row mb-3">
    <div class="col">
        <h1 class="h3 d-inline">
            <?= Html::encode($stock->symbol) ?> <small class="text-muted"><?= Html::encode($stock->name) ?></small>
        </h1>
        <?= SignalBadge::widget(['signal' => $currentWeekly->signal ?? null]) ?>
    </div>
</div>

<?= DetailView::widget([
    'model'  => $stock,
    'attributes' => [
        'symbol',
        'name',
        'exchange',
        'sector',
        'industry',
        [
            'attribute' => 'price',
            'value' => $stock->price !== null ? Yii::$app->formatter->asCurrency($stock->price) : null,
        ],
    ],
    'options' => ['class' => 'table table-sm table-bordered'],
]) ?>

<?php if ($currentWeekly !== null): ?>
<div class="card mb-4">
    <div class="card-header">Weekly Analysis (<?= $currentWeekly->week_start ?>)</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2"><strong>Buy Ratio</strong><br><?= Yii::$app->formatter->asRatioPercent($currentWeekly->buy_ratio) ?></div>
            <div class="col-md-2"><strong>Sell Ratio</strong><br><?= Yii::$app->formatter->asRatioPercent($currentWeekly->sell_ratio) ?></div>
            <div class="col-md-2"><strong>Vol Growth</strong><br><?= $currentWeekly->volume_growth !== null ? Yii::$app->formatter->asPercent($currentWeekly->volume_growth) : '-' ?></div>
            <div class="col-md-2"><strong>RVOL</strong><br><?= $currentWeekly->rvol !== null ? round($currentWeekly->rvol, 2) . 'x' : '-' ?></div>
            <div class="col-md-2"><strong>Score</strong><br><?= $currentWeekly->score ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<div class="card mb-4">
    <div class="card-header">Price & Volume (candlestick + MA20/MA50)</div>
    <div class="card-body">
        <div id="chart-stock" style="width:100%;height:500px;"></div>
    </div>
</div>

<script>
(function () {
    var chartDom = document.getElementById('chart-stock');
    if (!chartDom || typeof echarts === 'undefined') { return; }

    var option = {
        tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
        grid:    { left: '3%', right: '1%', bottom: '15%', containLabel: true },
        xAxis:   { type: 'category', data: [] },
        yAxis: [
            { type: 'value', position: 'left',  name: 'Price' },
            { type: 'value', position: 'right', name: 'Volume' }
        ],
        series: []
    };

    var myChart = echarts.init(chartDom);
    myChart.setOption(option);

    fetch('<?= \yii\helpers\Url::to(['chart-data', 'symbol' => $stock->symbol]) ?>')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            option.xAxis.data = d.dates;
            option.series = [
                { name: 'Price',  type: 'candlestick', data: d.kline },
                { name: 'MA20',   type: 'line', yAxisIndex: 0, data: d.ma20, lineStyle: { color: '#ff6600', width: 1 } },
                { name: 'MA50',   type: 'line', yAxisIndex: 0, data: d.ma50, lineStyle: { color: '#0066ff', width: 1 } },
                {
                    name: 'Volume',
                    type: 'bar',
                    yAxisIndex: 1,
                    itemStyle: { color: '#91cc75' },
                    data: d.volumes.map(function (v) {
                        return {
                            value: v[1],
                            itemStyle: { color: v[0] === 0 ? '#ef5350' : '#91cc75' }
                        };
                    })
                }
            ];
            myChart.setOption(option);
        })
        .catch(function (err) { console.error('Chart data fetch error:', err); });
})();
</script>

<h4 class="h5">Weekly Analysis History</h4>
<?= GridView::widget([
    'dataProvider' => $weeklyProvider,
    'tableOptions' => ['class' => 'table table-sm'],
    'columns' => [
        'week_start',
        [
            'attribute' => 'buy_ratio',
            'format' => 'percent',
            'value' => function ($m) { return $m->buy_ratio !== null ? $m->buy_ratio : null; },
        ],
        [
            'attribute' => 'volume_growth',
            'format' => 'percent',
            'value' => function ($m) { return $m->volume_growth; },
        ],
        [
            'attribute' => 'rvol',
            'value' => function ($m) { return $m->rvol !== null ? round($m->rvol, 2) . 'x' : '-'; },
        ],
        'score',
        [
            'attribute' => 'signal',
            'value' => function ($m) { return SignalBadge::widget(['signal' => $m->signal]); },
            'format' => 'raw',
        ],
    ],
]) ?>
