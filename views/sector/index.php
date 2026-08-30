<?php

/** @var yii\web\View $this */
/** @var array<int, array{sector: string, stocks: int, buyPressure: float|null, avgRvol: float|null, avgScore: float|null, buySignals: int}> $sectors */

use yii\bootstrap5\Html;

/** Warna heat berdasarkan rata-rata buy pressure */
function sectorColor(?float $p): string
{
    if ($p === null) {
        return 'bg-secondary';
    }

    return match (true) {
        $p >= 0.65 => 'bg-success',
        $p >= 0.55 => 'bg-success-subtle text-success-emphasis border border-success',
        $p >= 0.45 => 'bg-warning-subtle text-warning-emphasis border border-warning',
        $p >= 0.35 => 'bg-danger-subtle text-danger-emphasis border border-danger',
        default => 'bg-danger',
    };
}

$this->title = 'Sector Heatmap';
$this->params['breadcrumbs'][] = $this->title;
?>

<h1 class="h3 mb-1"><?= Html::encode($this->title) ?></h1>
<p class="text-muted small">Rata-rata buying pressure minggu terakhir per sektor. Klik sektor untuk drill-down saham.</p>

<?php if ($sectors === []): ?>
    <p class="text-muted">Belum ada data sektor.</p>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($sectors as $s): ?>
        <div class="col-md-4 col-lg-3">
            <a class="text-decoration-none" href="<?= Html::encode(\yii\helpers\Url::to(['view', 'sector' => $s['sector']])) ?>">
                <div class="card h-100 <?= sectorColor($s['buyPressure']) ?>">
                    <div class="card-body">
                        <div class="fw-bold text-truncate" title="<?= Html::encode($s['sector']) ?>"><?= Html::encode($s['sector']) ?></div>
                        <div class="display-6 fw-bold"><?= $s['buyPressure'] !== null ? Yii::$app->formatter->asRatioPercent($s['buyPressure']) : '-' ?></div>
                        <div class="small">
                            <?= $s['stocks'] ?> saham ·
                            RVOL <?= $s['avgRvol'] !== null ? round($s['avgRvol'], 2) . 'x' : '-' ?> ·
                            Score <?= $s['avgScore'] !== null ? $s['avgScore'] : '-' ?><br>
                            <?= $s['buySignals'] ?> sinyal BUY+
                        </div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach ?>
</div>
<?php endif ?>
