<?php

/** @var yii\web\View $this */
/** @var app\models\form\ScanFilterForm $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;
use yii\grid\GridView;

$this->title = 'Stock Scanner';
$this->params['breadcrumbs'][] = $this->title;

$isDaily = $searchModel->mode === 'daily';

$weeklyColumns = [
    ['class' => \yii\grid\SerialColumn::class, 'header' => '#', 'headerOptions' => ['style' => 'width: 40px']],
    [
        'attribute' => 'week_start',
        'label' => 'Date',
        'headerOptions' => ['style' => 'width: 110px'],
        'value' => function ($m) {
            return Yii::$app->formatter->asDate($m->week_start, 'MMM d, Y');
        },
    ],
    [
        'attribute' => 'symbol',
        'label' => 'Symbol',
        'headerOptions' => ['style' => 'width: 80px'],
        'value' => function ($m) {
            return Html::a(Html::encode($m->stock->symbol), ['/stock/view', 'symbol' => $m->stock->symbol]);
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'sector',
        'label' => 'Sector',
        'value' => function ($m) {
            return $m->stock->sector ?? '-';
        },
    ],
    [
        'attribute' => 'price',
        'label' => 'Price',
        'headerOptions' => ['class' => 'text-end'],
        'value' => function ($m) {
            return Yii::$app->formatter->asCurrency($m->close_price);
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'market_cap',
        'label' => 'Mkt Cap',
        'headerOptions' => ['class' => 'text-end'],
        'value' => function ($m) {
            return $m->stock->market_cap !== null ? Yii::$app->formatter->asVolume($m->stock->market_cap) : '-';
        },
    ],
    [
        'attribute' => 'buy_volume',
        'label' => 'Buy Vol',
        'headerOptions' => ['class' => 'text-end'],
        'value' => function ($m) {
            return $m->buy_volume !== null ? Yii::$app->formatter->asInteger($m->buy_volume) : '-';
        },
    ],
    [
        'attribute' => 'sell_volume',
        'label' => 'Sell Vol',
        'headerOptions' => ['class' => 'text-end'],
        'value' => function ($m) {
            return $m->sell_volume !== null ? Yii::$app->formatter->asInteger($m->sell_volume) : '-';
        },
    ],
    [
        'attribute' => 'volume_growth',
        'label' => 'Vol Growth',
        'headerOptions' => ['class' => 'text-end', 'style' => 'width: 100px'],
        'value' => function ($m) {
            if ($m->volume_growth === null) return '-';
            $color = $m->volume_growth >= 0.5 ? 'text-success' : ($m->volume_growth >= 0 ? 'text-warning' : 'text-danger');
            return Html::tag('span', Yii::$app->formatter->asPercent($m->volume_growth), ['class' => $color, 'fw-bold' => true]);
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'buy_ratio',
        'label' => 'Buy Ratio',
        'headerOptions' => ['class' => 'text-end', 'style' => 'width: 100px'],
        'value' => function ($m) {
            if ($m->buy_ratio === null) return '-';
            $color = $m->buy_ratio >= 0.7 ? 'text-success' : ($m->buy_ratio >= 0.5 ? 'text-warning' : 'text-danger');
            return Html::tag('span', Yii::$app->formatter->asRatioPercent($m->buy_ratio), ['class' => $color, 'fw-bold' => true]);
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'rvol',
        'label' => 'RVOL',
        'headerOptions' => ['class' => 'text-end', 'style' => 'width: 70px'],
        'value' => function ($m) {
            if ($m->rvol === null) return '-';
            $color = $m->rvol >= 1.5 ? 'text-success' : ($m->rvol >= 1 ? 'text-warning' : 'text-secondary');
            return Html::tag('span', round($m->rvol, 2) . 'x', ['class' => $color, 'fw-bold' => true]);
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'score',
        'label' => 'Score',
        'headerOptions' => ['class' => 'text-end', 'style' => 'width: 70px'],
        'value' => function ($m) {
            return $m->score !== null ? (int) $m->score : '-';
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'signal',
        'label' => 'Signal',
        'headerOptions' => ['style' => 'width: 120px'],
        'value' => function ($m) {
            return SignalBadge::widget(['signal' => $m->signal]);
        },
        'format' => 'raw',
    ],
];

$dailyColumns = [
    ['class' => \yii\grid\SerialColumn::class, 'header' => '#', 'headerOptions' => ['style' => 'width: 40px']],
    [
        'attribute' => 'date',
        'label' => 'Date',
        'headerOptions' => ['style' => 'width: 110px'],
        'value' => function ($m) {
            return Yii::$app->formatter->asDate($m->date, 'MMM d, Y');
        },
    ],
    [
        'attribute' => 'symbol',
        'label' => 'Symbol',
        'headerOptions' => ['style' => 'width: 80px'],
        'value' => function ($m) {
            return Html::a(Html::encode($m->stock->symbol), ['/stock/view', 'symbol' => $m->stock->symbol]);
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'sector',
        'label' => 'Sector',
        'value' => function ($m) {
            return $m->stock->sector ?? '-';
        },
    ],
    [
        'attribute' => 'close',
        'label' => 'Price',
        'headerOptions' => ['class' => 'text-end'],
        'value' => function ($m) {
            return Yii::$app->formatter->asCurrency($m->close);
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'volume',
        'label' => 'Volume',
        'headerOptions' => ['class' => 'text-end'],
        'value' => function ($m) {
            return $m->volume !== null ? Yii::$app->formatter->asInteger($m->volume) : '-';
        },
    ],
    [
        'label' => 'Mkt Cap',
        'headerOptions' => ['class' => 'text-end'],
        'value' => function ($m) {
            return $m->stock->market_cap !== null ? Yii::$app->formatter->asVolume($m->stock->market_cap) : '-';
        },
        'format' => 'raw',
    ],
    [
        'attribute' => 'buy_volume',
        'label' => 'Buy Vol',
        'headerOptions' => ['class' => 'text-end'],
        'value' => function ($m) {
            return $m->buy_volume !== null ? Yii::$app->formatter->asInteger($m->buy_volume) : '-';
        },
    ],
    [
        'attribute' => 'sell_volume',
        'label' => 'Sell Vol',
        'headerOptions' => ['class' => 'text-end'],
        'value' => function ($m) {
            return $m->sell_volume !== null ? Yii::$app->formatter->asInteger($m->sell_volume) : '-';
        },
    ],
    [
        'label' => 'Buy Ratio',
        'headerOptions' => ['class' => 'text-end', 'style' => 'width: 100px'],
        'value' => function ($m) {
            $total = $m->buy_volume + $m->sell_volume;
            if ($total <= 0 || $m->buy_volume === null) return '-';
            $ratio = $m->buy_volume / $total;
            $color = $ratio >= 0.7 ? 'text-success' : ($ratio >= 0.5 ? 'text-warning' : 'text-danger');
            return Html::tag('span', Yii::$app->formatter->asRatioPercent($ratio), ['class' => $color, 'fw-bold' => true]);
        },
        'format' => 'raw',
    ],
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <a href="<?= \yii\helpers\Url::to(['scanner/run-scan']) ?>" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" title="Fetch latest daily data + re-analyze + re-score all active stocks">
        <i class="bi bi-arrow-clockwise"></i> Daily Scan
    </a>
</div>

<?php foreach ((array) Yii::$app->session->getFlash('success') as $msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= Html::encode($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>
<?php foreach ((array) Yii::$app->session->getFlash('danger') as $msg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= Html::encode($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>

<?php if ($searchModel->hasErrors()): ?>
    <div class="alert alert-warning" role="alert">
        <strong>Filter tidak valid — hasil dikosongkan agar tidak menyesatkan.</strong>
        <ul class="mb-0 ps-3">
            <?php foreach ($searchModel->getErrors() as $attribute => $messages): ?>
                <?php foreach ($messages as $message): ?>
                    <li><?= Html::encode($message) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?= $this->render('_search', ['model' => $searchModel]) ?>

<div class="table-responsive">
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $isDaily ? null : $searchModel,
    'tableOptions' => ['class' => 'table table-sm table-hover align-middle mb-0'],
    'headerRowOptions' => ['class' => 'table-light'],
    'rowOptions' => function ($m) use ($isDaily) {
        if ($isDaily) {
            $total = (int) $m->buy_volume + (int) $m->sell_volume;
            $ratio = $total > 0 ? (int) $m->buy_volume / $total : 0.5;
            $cls = $ratio >= 0.7 ? 'table-success' : ($ratio >= 0.5 ? 'table-warning' : 'table-danger');
            return ['class' => $cls];
        }
        $map = [
            'STRONG_BUY' => 'table-success',
            'BUY'        => 'table-success',
            'WATCH'      => 'table-warning',
            'WEAK'       => 'table-secondary',
            'SELL'       => 'table-danger',
        ];
        return ['class' => $map[$m->signal] ?? ''];
    },
    'columns' => $isDaily ? $dailyColumns : $weeklyColumns,
]); ?>
</div>
