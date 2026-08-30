<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\grid\GridView;

$this->title = 'Backtesting';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::a('➕ Jalankan Backtest', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-sm table-hover align-middle'],
    'columns' => [
        'name',
        [
            'label' => 'Periode',
            'value' => fn ($m) => $m->start_date . ' → ' . $m->end_date,
        ],
        ['attribute' => 'trades', 'label' => 'Trades'],
        [
            'attribute' => 'win_rate',
            'label' => 'Win Rate',
            'value' => fn ($m) => $m->win_rate !== null ? Yii::$app->formatter->asPercent((float) $m->win_rate, 1) : '-',
        ],
        [
            'attribute' => 'avg_gain',
            'label' => 'Avg Gain',
            'value' => fn ($m) => $m->avg_gain !== null ? round((float) $m->avg_gain, 2) . '%' : '-',
        ],
        [
            'attribute' => 'avg_loss',
            'label' => 'Avg Loss',
            'value' => fn ($m) => $m->avg_loss !== null ? round((float) $m->avg_loss, 2) . '%' : '-',
        ],
        [
            'attribute' => 'profit_factor',
            'label' => 'PF',
            'value' => fn ($m) => $m->profit_factor !== null ? round((float) $m->profit_factor, 2) : '-',
        ],
        [
            'attribute' => 'max_drawdown',
            'label' => 'Max DD',
            'value' => fn ($m) => $m->max_drawdown !== null ? round((float) $m->max_drawdown, 2) . ' pts' : '-',
        ],
        [
            'label' => '',
            'value' => fn ($m) => Html::a('Detail', ['view', 'id' => $m->id], ['class' => 'btn btn-sm btn-outline-primary']),
            'format' => 'raw',
        ],
    ],
]) ?>
