<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var int $unreadCount */

use app\models\Alert;
use yii\bootstrap5\Html;
use yii\grid\GridView;

$this->title = 'Alerts';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <div class="btn-group btn-group-sm">
        <?= Html::a('🔔 Notifikasi' . ($unreadCount > 0 ? " ({$unreadCount})" : ''), ['notifications'], ['class' => 'btn btn-outline-primary']) ?>
        <?= Html::a('➕ Buat Alert', ['create'], ['class' => 'btn btn-primary']) ?>
    </div>
</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-sm table-hover align-middle'],
    'columns' => [
        [
            'label' => 'Simbol',
            'value' => fn ($m) => $m->stock ? Html::a(Html::encode($m->stock->symbol), ['/stock/view', 'symbol' => $m->stock->symbol]) : '(semua)',
            'format' => 'raw',
        ],
        [
            'label' => 'Kondisi',
            'value' => fn ($m) => sprintf('%s %s %s', Alert::CONDITIONS[$m->condition_type] ?? $m->condition_type, $m->operator, $m->condition_type === Alert::CONDITION_BUY_RATIO ? round((float) $m->threshold, 2) : (string) $m->threshold),
        ],
        [
            'attribute' => 'active',
            'label' => 'Status',
            'value' => fn ($m) => $m->active ? Html::tag('span', 'Aktif', ['class' => 'badge bg-success']) : Html::tag('span', 'Nonaktif', ['class' => 'badge bg-secondary']),
            'format' => 'raw',
        ],
        [
            'label' => 'Terakhir trigger',
            'value' => fn ($m) => $m->last_triggered_at ? Yii::$app->formatter->asDatetime((int) $m->last_triggered_at) : '-',
        ],
        [
            'label' => 'Aksi',
            'value' => fn ($m) =>
                Html::a('✏️', ['update', 'id' => $m->id], ['title' => 'Edit']) . ' ' .
                Html::a($m->active ? '⏸️' : '▶️', ['toggle', 'id' => $m->id], ['title' => $m->active ? 'Nonaktifkan' : 'Aktifkan']) . ' ' .
                Html::a('🗑️', ['delete', 'id' => $m->id], [
                    'title' => 'Hapus',
                    'data' => ['method' => 'post', 'confirm' => 'Hapus alert ini?'],
                ]),
            'format' => 'raw',
        ],
    ],
]) ?>
