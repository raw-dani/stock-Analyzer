<?php
/** @var yii\web\View $this */
/** @var array $results */
/** @var int $timeframe */
/** @var int $minSellCount */
/** @var int $totalScanned */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Scanner Sinyal Jual & Exit Terpadu';
$this->params['breadcrumbs'][] = ['label' => 'Scanner', 'url' => ['/scanner/index']];
$this->params['breadcrumbs'][] = 'Sinyal Jual';

$tfList = [
    24 => 'Daily (1D)',
    4  => '4 Jam (4H)',
    2  => '2 Jam (2H)',
    1  => '1 Jam (1H)',
];
?>

<!-- Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">
            <?= \app\helpers\SvgHelper::icon('shield-exclamation', ['class' => 'text-danger me-2']) ?>
            Scanner Sinyal Jual &amp; Exit Terpadu
        </h1>
        <p class="text-muted small mb-0">
            Agregasi peringatan JUAL, TAKE PROFIT, dan BREAKDOWN dari seluruh 10 strategi. Menampilkan saham dengan &ge; <?= $minSellCount ?> sinyal jual aktif.
            Total dipindai: <strong><?= $totalScanned ?> saham aktif</strong>.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= Url::to(['/scanner/index']) ?>" class="btn btn-outline-secondary btn-sm shadow-xs">
            <?= \app\helpers\SvgHelper::icon('search') ?> Scanner Akumulasi
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="get" class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:auto;">
                <span class="input-group-text bg-light text-secondary border">
                    <?= \app\helpers\SvgHelper::icon('clock-history') ?> Timeframe
                </span>
                <select name="timeframe" class="form-select form-select-sm fw-semibold">
                    <?php foreach ($tfList as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $timeframe === $v ? 'selected' : '' ?>><?= Html::encode($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="input-group input-group-sm" style="width:auto;">
                <span class="input-group-text bg-light text-secondary border">Min Sinyal Jual</span>
                <select name="min_sell" class="form-select form-select-sm fw-semibold">
                    <?php foreach ([1, 2, 3, 4] as $v): ?>
                        <option value="<?= $v ?>" <?= $minSellCount === $v ? 'selected' : '' ?>>&ge; <?= $v ?> Sinyal</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-danger fw-bold rounded-pill px-3 shadow-xs">
                <?= \app\helpers\SvgHelper::icon('search') ?> Scan Sinyal Jual
            </button>
        </form>
    </div>
</div>

<!-- Hasil -->
<?php if (empty($results)): ?>
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body text-center py-5 text-muted">
        <?= \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success fs-1 mb-3']) ?>
        <h5 class="fw-bold text-success">Tidak Ada Sinyal Jual Terdeteksi</h5>
        <p class="small text-muted mb-0">Tidak ditemukan saham dengan &ge; <?= $minSellCount ?> sinyal jual aktif pada timeframe ini. Kondisi portofolio relatif stabil.</p>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <strong class="small text-secondary">
            <?= \app\helpers\SvgHelper::icon('exclamation-triangle-fill', ['class' => 'text-danger me-1']) ?>
            Ditemukan <?= count($results) ?> Saham Memerlukan Evaluasi Exit
        </strong>
        <span class="badge bg-danger rounded-pill px-3 py-1"><?= count($results) ?> Saham</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small text-uppercase">
                    <tr>
                        <th style="width: 20%;">Saham</th>
                        <th style="width: 12%;" class="text-center">Jumlah Sinyal</th>
                        <th style="width: 25%;">Rekomendasi Exit</th>
                        <th style="width: 28%;">Strategi Yang Memicu Jual</th>
                        <th style="width: 8%;">Regime</th>
                        <th style="width: 7%;" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                    <?php
                    $stock = $r['stock'];
                    $exit  = $r['exit_signals'];
                    $badge = $exit['badge'] ?? 'secondary';
                    $regimeBadge = match($r['market_regime']) { 'BULL' => 'success', 'BEAR' => 'danger', default => 'secondary' };
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark fs-6"><?= Html::encode($stock->symbol) ?></div>
                            <div class="text-muted small"><?= Html::encode($stock->name ?? '') ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $badge ?> fs-6 px-3 py-2 rounded-pill"><?= $r['sell_count'] ?></span>
                        </td>
                        <td>
                            <div class="small fw-bold text-<?= $badge ?> mb-1">
                                <?= Html::encode($exit['recommendation'] ?? '-') ?>
                            </div>
                            <div class="small text-muted" style="font-size:0.75rem;">
                                Weighted Buy: <?= $r['stats']['weighted_buy_ratio'] ?? 0 ?>% &bull; Sell Ratio: <?= $r['stats']['weighted_sell_ratio'] ?? 0 ?>%
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach (array_slice($exit['signals'] ?? [], 0, 4) as $sig): ?>
                                <?php
                                $urgencyClass = match($sig['urgency'] ?? 'LOW') {
                                    'URGENT' => 'bg-danger text-white',
                                    'HIGH'   => 'bg-danger-subtle text-danger border border-danger-subtle',
                                    default  => 'bg-warning-subtle text-dark border border-warning-subtle',
                                };
                                ?>
                                <span class="badge <?= $urgencyClass ?> small" style="font-size:0.72rem;">
                                    <?= Html::encode($sig['strategy']) ?>: <?= Html::encode($sig['action']) ?>
                                </span>
                                <?php endforeach; ?>
                                <?php if (count($exit['signals'] ?? []) > 4): ?>
                                <span class="text-muted small align-self-center" style="font-size:0.72rem;">+<?= count($exit['signals']) - 4 ?> lagi</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?= $regimeBadge ?>"><?= Html::encode($r['market_regime']) ?></span>
                        </td>
                        <td class="text-end">
                            <a href="<?= Url::to(['/stock/view', 'symbol' => $stock->symbol, 'timeframe' => $timeframe]) ?>#strategy-synthesis-section"
                               class="btn btn-sm btn-outline-danger rounded-pill fw-semibold shadow-xs" title="Buka Detail Analisis Saham">
                                <?= \app\helpers\SvgHelper::icon('graph-up') ?> Detail
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
