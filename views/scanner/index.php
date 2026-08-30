<?php

/** @var yii\web\View $this */
/** @var app\models\form\ScanFilterForm $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;
use yii\grid\GridView;

$this->title = 'Stock Scanner';
$this->params['breadcrumbs'][] = $this->title;
?>

<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<?= $this->render('_search', ['model' => $searchModel]) ?>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'tableOptions' => ['class' => 'table table-sm table-hover align-middle'],
    'columns' => [
        [
            'class' => \yii\grid\SerialColumn::class,
            'header' => 'Rank',
        ],
        [
            'attribute' => 'symbol',
            'label' => 'Symbol',
            'value' => function ($m) {
                return Html::a(Html::encode($m->stock->symbol), ['/stock/view', 'symbol' => $m->stock->symbol]);
            },
            'format' => 'raw',
        ],
        [
            'attribute' => 'price',
            'label' => 'Price',
            'value' => function ($m) {
                return $m->stock->price !== null ? Yii::$app->formatter->asCurrency($m->stock->price) : '-';
            },
            'format' => 'raw',
        ],
        [
            'attribute' => 'buy_volume',
            'label' => 'Buy Vol',
            'value' => function ($m) {
                return $m->buy_volume !== null ? Yii::$app->formatter->asInteger($m->buy_volume) : '-';
            },
        ],
        [
            'attribute' => 'sell_volume',
            'label' => 'Sell Vol',
            'value' => function ($m) {
                return $m->sell_volume !== null ? Yii::$app->formatter->asInteger($m->sell_volume) : '-';
            },
        ],
        [
            'attribute' => 'buy_ratio',
            'label' => 'Buy Ratio',
            'value' => function ($m) {
                return $m->buy_ratio !== null ? Yii::$app->formatter->asRatioPercent($m->buy_ratio) : '-';
            },
        ],
        [
            'attribute' => 'volume_growth',
            'label' => 'Vol Growth',
            'value' => function ($m) {
                return $m->volume_growth !== null ? Yii::$app->formatter->asPercent($m->volume_growth) : '-';
            },
        ],
        [
            'attribute' => 'rvol',
            'label' => 'RVOL',
            'value' => function ($m) {
                return $m->rvol !== null ? round($m->rvol, 2) . 'x' : '-';
            },
        ],
        [
            'attribute' => 'score',
            'label' => 'Score',
        ],
        [
            'attribute' => 'signal',
            'label' => 'Signal',
            'value' => function ($m) {
                return SignalBadge::widget(['signal' => $m->signal]);
            },
            'format' => 'raw',
        ],
    ],
]);
