<?php

/** @var yii\web\View $this */
/** @var app\models\Watchlist $watchlist */
/** @var app\models\WatchlistItem[] $items */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;

$this->title = $watchlist->name;
$this->params['breadcrumbs'][] = ['label' => 'Watchlist', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="watchlist-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0"><?= Html::encode($watchlist->name) ?></h1>
            <?php if (!empty($watchlist->description)): ?>
                <p class="text-muted mb-0"><?= Html::encode($watchlist->description) ?></p>
            <?php endif ?>
        </div>
        <div class="btn-group btn-group-sm">
            <?= Html::a('✏️ Edit', ['update', 'id' => $watchlist->id], ['class' => 'btn btn-outline-primary']) ?>
            <?= Html::a('🗑️ Hapus', ['delete', 'id' => $watchlist->id], [
                'class' => 'btn btn-outline-danger',
                'data' => ['confirm' => 'Hapus watchlist ini?'],
            ]) ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <label for="symbol-input" class="form-label mb-0">Tambah saham (symbol + Enter):</label>
            <form method="post" id="add-item-form" action="<?= \yii\helpers\Url::to(['add-item', 'id' => $watchlist->id]) ?>" class="d-flex gap-2">
                <input type="hidden" name="_csrf" value="<?= Yii::$app->request->getCsrfToken() ?>">
                <input type="text" name="symbol" id="symbol-input" class="form-control" placeholder="AAPL, NVDA, XOM..." autocomplete="off" required>
                <button type="submit" class="btn btn-success">+</button>
            </form>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="alert alert-warning">
            Belum ada saham di watchlist ini.
        </div>
    <?php else: ?>
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Symbol</th>
                    <th>Harga</th>
                    <th>Buy Ratio</th>
                    <th>RVOL</th>
                    <th>Score</th>
                    <th>Signal</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item):
                    $w = $item->latestWeekly;
                    $price = $item->stock ? $item->stock->price : null;
                ?>
                    <tr>
                        <td><?= Html::a(Html::encode($item->stock->symbol), ['/stock/view', 'symbol' => $item->stock->symbol]) ?></td>
                        <td><?= $price !== null ? Yii::$app->formatter->asCurrency($price) : '-' ?></td>
                        <td><?= $w && $w->buy_ratio !== null ? Yii::$app->formatter->asRatioPercent($w->buy_ratio) : '-' ?></td>
                        <td><?= $w && $w->rvol !== null ? round($w->rvol, 2) . 'x' : '-' ?></td>
                        <td><?= $w ? $w->score : '-' ?></td>
                        <td><?= SignalBadge::widget(['signal' => $w->signal ?? null]) ?></td>
                        <td class="text-end">
                            <?= Html::a('🗑️', ['remove-item', 'id' => $item->id], [
                                'class' => 'text-danger text-decoration-none',
                                'data' => ['method' => 'post', 'confirm' => 'Hapus ' . $item->stock->symbol . ' dari watchlist?'],
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>

    <?= Html::a('← Kembali ke Daftar', ['index'], ['class' => 'btn btn-link btn-sm']) ?>
</div>
