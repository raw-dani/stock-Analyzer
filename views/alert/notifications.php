<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var int $unreadCount */

use yii\bootstrap5\Html;
use yii\grid\GridView;

$this->title = 'Notifikasi Alert';
$this->params['breadcrumbs'][] = ['label' => 'Alerts', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?php if ($unreadCount > 0): ?>
        <?= Html::a("Tandai semua dibaca ({$unreadCount})", ['mark-all-read'], [
            'class' => 'btn btn-outline-primary btn-sm',
            'data' => ['method' => 'post'],
        ]) ?>
    <?php endif ?>
</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-sm table-hover align-middle'],
    'columns' => [
        [
            'header' => '',
            'value' => fn ($m) => $m->read_at === null
                ? Html::tag('span', '', ['class' => 'badge bg-primary rounded-circle', 'title' => 'Belum dibaca'])
                : '',
            'format' => 'raw',
        ],
        [
            'label' => 'Waktu',
            'value' => fn ($m) => Yii::$app->formatter->asDatetime((int) $m->created_at),
        ],
        [
            'label' => 'Simbol',
            'value' => fn ($m) => $m->stock ? Html::a(Html::encode($m->stock->symbol), ['/stock/view', 'symbol' => $m->stock->symbol]) : '-',
            'format' => 'raw',
        ],
        'message',
        [
            'class' => \yii\grid\ActionColumn::class,
            'template' => '{read}',
            'buttons' => [
                'read' => fn ($url, $m) => $m->read_at === null
                    ? Html::a('Tandai dibaca', ['read', 'id' => $m->id], ['class' => 'btn btn-sm btn-outline-secondary'])
                    : '<span class="text-muted small">dibaca</span>',
            ],
        ],
    ],
]) ?>

<?= Html::a('← Kembali ke Alerts', ['index'], ['class' => 'btn btn-link btn-sm']) ?>
