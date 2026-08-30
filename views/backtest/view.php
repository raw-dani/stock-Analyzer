<?php

/** @var yii\web\View $this */
/** @var app\models\BacktestRun $run */

use yii\bootstrap5\Html;

$this->title = 'Backtest #' . $run->id . ' — ' . $run->name;
$this->params['breadcrumbs'][] = ['label' => 'Backtesting', 'url' => ['index']];
$this->params['breadcrumbs'][] = '#' . $run->id;
?>

<h1 class="h3 mb-1"><?= Html::encode($this->title) ?></h1>
<p class="text-muted small">Kriteria: <code><?= Html::encode($run->params) ?></code></p>

<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="h4 mb-0"><?= $run->trades ?></div><small class="text-muted">Trades</small></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="h4 mb-0"><?= $run->win_rate !== null ? Yii::$app->formatter->asPercent((float) $run->win_rate, 1) : '-' ?></div><small class="text-muted">Win Rate</small></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="h4 mb-0"><?= $run->avg_gain !== null ? round((float) $run->avg_gain, 2) . '%' : '-' ?></div><small class="text-muted">Avg Gain</small></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="h4 mb-0"><?= $run->avg_loss !== null ? round((float) $run->avg_loss, 2) . '%' : '-' ?></div><small class="text-muted">Avg Loss</small></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="h4 mb-0"><?= $run->profit_factor !== null ? round((float) $run->profit_factor, 2) : '-' ?></div><small class="text-muted">Profit Factor</small></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body text-center"><div class="h4 mb-0"><?= $run->max_drawdown !== null ? round((float) $run->max_drawdown, 2) . ' pts' : '-' ?></div><small class="text-muted">Max Drawdown</small></div></div></div>
</div>

<h4 class="h5">Daftar Trades (<?= $run->start_date ?> → <?= $run->end_date ?>)</h4>
<table class="table table-sm table-hover align-middle">
    <thead class="table-light">
        <tr><th>#</th><th>Symbol</th><th>Entry</th><th>Entry Price</th><th>Exit</th><th>Exit Price</th><th>Return</th></tr>
    </thead>
    <tbody>
        <?php foreach ($run->tradesRel as $i => $t): ?>
            <tr class="<?= (float) $t->return_pct > 0 ? 'table-success' : ((float) $t->return_pct < 0 ? 'table-danger' : '') ?>">
                <td><?= $i + 1 ?></td>
                <td><?= $t->stock ? Html::a(Html::encode($t->stock->symbol), ['/stock/view', 'symbol' => $t->stock->symbol]) : $t->stock_id ?></td>
                <td><?= $t->entry_date ?></td>
                <td><?= Yii::$app->formatter->asCurrency((float) $t->entry_price) ?></td>
                <td><?= $t->exit_date ?></td>
                <td><?= Yii::$app->formatter->asCurrency((float) $t->exit_price) ?></td>
                <td><?= round((float) $t->return_pct, 2) ?>%</td>
            </tr>
        <?php endforeach ?>
        <?php if ($run->tradesRel === []): ?>
            <tr><td colspan="7" class="text-muted">Tidak ada trade — kriteria terlalu ketat atau data tidak cukup.</td></tr>
        <?php endif ?>
    </tbody>
</table>

<?= Html::a('← Kembali ke Backtesting', ['index'], ['class' => 'btn btn-link btn-sm']) ?>
