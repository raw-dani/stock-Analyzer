<?php

/** @var yii\web\View $this */
/** @var string $sector */
/** @var array<int, array{stock: app\models\Stock, weekly: app\models\WeeklyAnalysis}> $rows */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;

$this->title = $sector;
$this->params['breadcrumbs'][] = ['label' => 'Sector Heatmap', 'url' => ['index']];
$this->params['breadcrumbs'][] = $sector;
?>

<h1 class="h3 mb-3"><?= Html::encode($sector) ?> <small class="text-muted">— saham terurut score</small></h1>

<?php if ($rows === []): ?>
    <p class="text-muted">Belum ada weekly analysis untuk sektor ini.</p>
<?php else: ?>
<table class="table table-sm table-hover align-middle">
    <thead class="table-light">
        <tr>
            <th>Symbol</th><th>Harga</th><th>Buy Ratio</th><th>RVOL</th><th>Vol Growth</th><th>Score</th><th>Signal</th><th>Minggu</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): $w = $r['weekly']; ?>
            <tr>
                <td><?= Html::a(Html::encode($r['stock']->symbol), ['/stock/view', 'symbol' => $r['stock']->symbol]) ?></td>
                <td><?= $r['stock']->price !== null ? Yii::$app->formatter->asCurrency($r['stock']->price) : '-' ?></td>
                <td><?= Yii::$app->formatter->asRatioPercent((float) $w->buy_ratio) ?></td>
                <td><?= $w->rvol !== null ? round((float) $w->rvol, 2) . 'x' : '-' ?></td>
                <td><?= $w->volume_growth !== null ? Yii::$app->formatter->asPercent((float) $w->volume_growth) : '-' ?></td>
                <td><?= $w->score ?></td>
                <td><?= SignalBadge::widget(['signal' => $w->signal]) ?></td>
                <td class="text-muted small"><?= $w->week_start ?></td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<?= Html::a('← Kembali ke Heatmap', ['index'], ['class' => 'btn btn-link btn-sm']) ?>
