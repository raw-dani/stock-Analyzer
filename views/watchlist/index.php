<?php

/** @var yii\web\View $this */
/** @var app\models\Watchlist[] $watchlists */

use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;

$this->title = 'Watchlist';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="watchlist-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Watchlist Saya</h1>
        <?= Html::a('➕ Buat Watchlist', ['create'], ['class' => 'btn btn-success btn-sm']) ?>
    </div>

    <?php if (empty($watchlists)): ?>
        <div class="alert alert-info">
            Belum ada watchlist. <?= Html::a('Buat pertama', ['create'], ['class' => 'alert-link']) ?>.
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($watchlists as $wl): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <?= Html::a(Html::encode($wl->name), ['view', 'id' => $wl->id]) ?>
                        <?php if (!empty($wl->description)): ?>
                            <small class="text-muted d-block mt-1"><?= Html::encode($wl->description) ?></small>
                        <?php endif ?>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <?= Html::a('✏️', ['update', 'id' => $wl->id], ['class' => 'btn btn-outline-primary']) ?>
                        <?= Html::a('🗑️', ['delete', 'id' => $wl->id], [
                            'class' => 'btn btn-outline-danger',
                            'data' => ['confirm' => 'Hapus watchlist ini?'],
                        ]) ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
