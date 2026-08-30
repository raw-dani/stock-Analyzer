<?php

/** @var yii\web\View $this */
/** @var string|null $lastWeek */
/** @var int $marketVolume */
/** @var float $buyPressure */
/** @var app\models\WeeklyAnalysis[] $topBuy */
/** @var app\models\WeeklyAnalysis[] $topSell */
/** @var app\models\WeeklyAnalysis[] $topRanked */
/** @var app\models\Signal[] $recentSignals */
/** @var array $signalCount */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;

$this->title = 'Dashboard';
$fmt = Yii::$app->formatter;
?>
<h1 class="h3 mb-2">Dashboard</h1>
<p class="text-muted">Ringkasan pasar minggu <?= $lastWeek !== null ? Html::encode($lastWeek) : '-' ?></p>

<?php if ($lastWeek === null): ?>
    <div class="alert alert-warning">
        Belum ada data. Jalankan pipeline:
        <code>php yii market/refresh-stocklist && php yii data/fetch && php yii data/analyze</code>
    </div>
<?php return; endif ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">MARKET VOLUME (MINGGU INI)</div>
            <div class="kpi"><?= $fmt->asVolume($marketVolume) ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">BUY PRESSURE</div>
            <div class="kpi text-success"><?= $fmt->asRatioPercent($buyPressure) ?></div>
            <div class="progress" style="height:8px">
                <div class="progress-bar bg-success" style="width:<?= round($buyPressure * 100) ?>%"></div>
                <div class="progress-bar bg-danger" style="width:<?= round((1 - $buyPressure) * 100) ?>%"></div>
            </div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">DISTRIBUSI SINYAL</div>
            <?php foreach ($signalCount as $row): ?>
                <?= SignalBadge::widget(['signal' => $row['signal']]) ?> <?= $row['cnt'] ?>
            <?php endforeach ?>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card h-100"><div class="card-body">
            <h5 class="card-title text-success">Top Buying Pressure</h5>
            <table class="table table-sm mb-0">
                <?php foreach ($topBuy as $w): if (!empty($w->stock)): ?>
                <tr>
                    <td><?= Html::a(Html::encode($w->stock->symbol), ['/stock/view', 'symbol' => $w->stock->symbol]) ?></td>
                    <td><?= $fmt->asRatioPercent($w->buy_ratio) ?></td>
                    <td><?= SignalBadge::widget(['signal' => $w->signal]) ?></td>
                </tr>
                <?php endif; endforeach ?>
            </table>
        </div></div>
    </div>
    <div class="col-lg-3">
        <div class="card h-100"><div class="card-body">
            <h5 class="card-title text-danger">Top Selling Pressure</h5>
            <table class="table table-sm mb-0">
                <?php foreach ($topSell as $w): if (!empty($w->stock)): ?>
                <tr>
                    <td><?= Html::a(Html::encode($w->stock->symbol), ['/stock/view', 'symbol' => $w->stock->symbol]) ?></td>
                    <td><?= $fmt->asRatioPercent($w->buy_ratio) ?></td>
                    <td><?= SignalBadge::widget(['signal' => $w->signal]) ?></td>
                </tr>
                <?php endif; endforeach ?>
            </table>
        </div></div>
    </div>
    <div class="col-lg-3">
        <div class="card h-100"><div class="card-body">
            <h5 class="card-title">Top Ranked (Score)</h5>
            <table class="table table-sm mb-0">
                <?php foreach ($topRanked as $w): ?>
                <tr>
                    <td><?= Html::a(Html::encode($w->stock->symbol), ['/stock/view', 'symbol' => $w->stock->symbol]) ?></td>
                    <td class="fw-bold"><?= $w->score ?></td>
                    <td><?= SignalBadge::widget(['signal' => $w->signal]) ?></td>
                </tr>
                <?php endforeach ?>
            </table>
        </div></div>
    </div>
    <div class="col-lg-3">
        <div class="card h-100"><div class="card-body">
            <h5 class="card-title">Recent Signals</h5>
            <table class="table table-sm mb-0">
                <?php foreach ($recentSignals as $s): ?>
                <tr>
                    <td class="text-muted small"><?= Html::encode($s->date) ?></td>
                    <td><?= Html::a(Html::encode($s->stock->symbol), ['/stock/view', 'symbol' => $s->stock->symbol]) ?></td>
                    <td><?= SignalBadge::widget(['signal' => $s->signal]) ?></td>
                    <td class="fw-bold"><?= $s->score ?></td>
                </tr>
                <?php endforeach ?>
            </table>
        </div></div>
    </div>
</div>
