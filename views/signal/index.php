<?php

/** @var yii\web\View $this */
/** @var app\models\form\SignalFilterForm $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;
use yii\grid\GridView;

$this->title = 'Signal History';
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
            'attribute' => 'date',
            'format' => 'date',
            'filter' => false,
        ],
        [
            'attribute' => 'symbol',
            'label' => 'Symbol',
            'value' => function ($m) {
                return Html::a(Html::encode($m->stock->symbol), ['symbol', 'symbol' => $m->stock->symbol]);
            },
            'format' => 'raw',
        ],
        [
            'attribute' => 'price',
            'label' => 'Price',
            'value' => fn ($m) => Yii::$app->formatter->asCurrency((float) $m->price),
            'filter' => false,
        ],
        [
            'attribute' => 'score',
            'filter' => false,
        ],
        [
            'attribute' => 'signal',
            'label' => 'Signal',
            'value' => fn ($m) => SignalBadge::widget(['signal' => $m->signal]),
            'format' => 'raw',
        ],
        [
            'attribute' => 'buy_ratio',
            'label' => 'Buy Ratio',
            'value' => fn ($m) => Yii::$app->formatter->asRatioPercent((float) $m->buy_ratio),
            'filter' => false,
        ],
        [
            'attribute' => 'rvol',
            'label' => 'RVOL',
            'value' => fn ($m) => round((float) $m->rvol, 2) . 'x',
            'filter' => false,
        ],
        [
            'attribute' => 'volume_growth',
            'label' => 'Vol Growth',
            'value' => fn ($m) => $m->volume_growth !== null ? Yii::$app->formatter->asPercent((float) $m->volume_growth) : '-',
            'filter' => false,
        ],
    ],
]) ?>
