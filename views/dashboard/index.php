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
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">Dashboard</h1>
        <p class="text-muted mb-0">Ringkasan pasar minggu <?= $lastWeek !== null ? Html::encode($lastWeek) : '-' ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= \yii\helpers\Url::to(['scanner/run-scan']) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-clockwise"></i> Run Scan
        </a>
        <a href="<?= \yii\helpers\Url::to(['scanner/index']) ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-search"></i> Open Scanner
        </a>
    </div>
</div>

<?php if ($lastWeek === null): ?>
    <div class="alert alert-warning">
        Belum ada data. Jalankan pipeline:
        <code>php yii market/refresh-stocklist && php yii data/fetch && php yii data/analyze</code>
    </div>
<?php return; endif ?>
 
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">MARKET VOLUME (MINGGU INI)</div>
            <div class="kpi"><?= $fmt->asVolume($marketVolume) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">BUY PRESSURE</div>
            <div class="kpi text-success"><?= $fmt->asRatioPercent($buyPressure) ?></div>
            <div class="progress" style="height:8px">
                <div class="progress-bar bg-success" style="width:<?= round($buyPressure * 100) ?>%"></div>
                <div class="progress-bar bg-danger" style="width:<?= round((1 - $buyPressure) * 100) ?>%"></div>
            </div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">STRONG BUY / BUY SINYAL</div>
            <?php
                $signalMap = [];
                foreach ($signalCount as $row) {
                    $signalMap[$row['signal']] = (int) $row['cnt'];
                }
                $strongBuy = ($signalMap['STRONG_BUY'] ?? 0) + ($signalMap['BUY'] ?? 0);
                $totalSignals = array_sum($signalMap);
            ?>
            <div class="kpi text-success"><?= $strongBuy ?></div>
            <div class="text-muted small fw-normal">(dari <?= $totalSignals ?> total sinyal)</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">DISTRIBUSI SINYAL</div>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($signalCount as $row): ?>
                    <?= SignalBadge::widget(['signal' => $row['signal']]) ?>
                    <span class="badge bg-secondary fs-8"><?= $row['cnt'] ?></span>
                <?php endforeach ?>
            </div>
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
                    <td class="text-danger"><?= $fmt->asRatioPercent($w->sell_ratio) ?></td>
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
                <?php foreach ($topRanked as $w): if (!empty($w->stock)): ?>
                <tr>
                    <td><?= Html::a(Html::encode($w->stock->symbol), ['/stock/view', 'symbol' => $w->stock->symbol]) ?></td>
                    <td class="fw-bold"><?= (int) $w->score ?></td>
                    <td><?= SignalBadge::widget(['signal' => $w->signal]) ?></td>
                </tr>
                <?php endif; endforeach ?>
            </table>
        </div></div>
    </div>
    <div class="col-lg-3">
        <div class="card h-100"><div class="card-body">
            <h5 class="card-title">Recent Signals</h5>
            <table class="table table-sm mb-0">
                <?php foreach ($recentSignals as $s): if (!empty($s->stock)): ?>
                <tr>
                    <td class="text-muted small"><?= Html::encode($s->date) ?></td>
                    <td><?= Html::a(Html::encode($s->stock->symbol), ['/stock/view', 'symbol' => $s->stock->symbol]) ?></td>
                    <td><?= SignalBadge::widget(['signal' => $s->signal]) ?></td>
                    <td class="fw-bold"><?= (int) $s->score ?></td>
                </tr>
                <?php endif; endforeach ?>
            </table>
        </div></div>
    </div>
</div>
