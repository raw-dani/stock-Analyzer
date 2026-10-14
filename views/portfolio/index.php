<?php

/** @var yii\web\View $this */
/** @var app\models\PortfolioPosition[] $positions */
/** @var int $allCount */
/** @var int $profitCount */
/** @var int $lossCount */
/** @var float $totalInvested */
/** @var float $currentValue */
/** @var float $totalPlAmount */
/** @var float $totalPlPct */
/** @var string $tab */
/** @var app\models\Stock[] $availableStocks */
/** @var app\models\PortfolioPosition $newPosition */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Portofolio & Strategi Averaging';
$this->params['breadcrumbs'][] = $this->title;

$fmt = Yii::$app->formatter;
?>

<div class="portfolio-index">
    <!-- Header Page -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-briefcase-fill text-primary me-2"></i><?= Html::encode($this->title) ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 align-middle ms-2">
                    <i class="bi bi-person-fill me-1"></i><?= Html::encode(Yii::$app->user->identity->username ?? 'User') ?>
                </span>
            </h1>
            <p class="text-muted small mb-0">
                Portofolio pribadi Anda: Pantau status untung/rugi saham yang sudah dibeli, set target profit presisi (contoh: 3%), serta jalankan simulasi strategi <strong>Average Down</strong> terintegrasi sinyal teknikal.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= Url::to(['/portfolio/refresh-prices']) ?>" class="btn btn-outline-success btn-sm px-3 shadow-sm fw-semibold" title="Ambil quote harga realtime terkini dari Yahoo Finance">
                <i class="bi bi-arrow-repeat me-1"></i> Sinkronkan Harga Real-Time
            </a>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddPosition">
                <i class="bi bi-plus-circle-fill me-1"></i> Tambah Saham yang Dibeli
            </button>
        </div>
    </div>

    <!-- KPI Metric Cards Summary -->
    <div class="row g-3 mb-4">
        <!-- Total Nilai Portofolio -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm bg-light text-center h-100 py-2">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Total Nilai Portofolio</div>
                    <div class="h3 mb-0 fw-bold text-dark"><?= $fmt->asCurrency($currentValue) ?></div>
                    <div class="small text-muted mt-1">Modal: <?= $fmt->asCurrency($totalInvested) ?></div>
                </div>
            </div>
        </div>

        <!-- Total P/L (Untung/Rugi) -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm bg-<?= $totalPlAmount >= 0 ? 'success' : 'danger' ?> bg-opacity-10 text-center h-100 py-2 border-start border-<?= $totalPlAmount >= 0 ? 'success' : 'danger' ?> border-4">
                <div class="card-body p-3">
                    <div class="text-<?= $totalPlAmount >= 0 ? 'success' : 'danger' ?> small fw-semibold text-uppercase">Total Floating P/L</div>
                    <div class="h3 mb-0 fw-bold text-<?= $totalPlAmount >= 0 ? 'success' : 'danger' ?>">
                        <?= ($totalPlAmount >= 0 ? '+' : '') . $fmt->asCurrency($totalPlAmount) ?>
                    </div>
                    <div class="small text-<?= $totalPlAmount >= 0 ? 'success' : 'danger' ?> fw-semibold mt-1">
                        <?= ($totalPlPct >= 0 ? '+' : '') . $totalPlPct ?>%
                    </div>
                </div>
            </div>
        </div>

        <!-- Posisi Sedang Untung -->
        <div class="col-6 col-md-3">
            <a href="<?= Url::to(['/portfolio/index', 'tab' => 'profit']) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm bg-light text-center h-100 py-2 hover-card <?= $tab === 'profit' ? 'border border-success border-2' : '' ?>">
                    <div class="card-body p-3">
                        <div class="text-success small fw-semibold text-uppercase">Posisi Sedang Untung</div>
                        <div class="h3 mb-0 fw-bold text-success"><?= $profitCount ?> Saham</div>
                        <div class="small text-muted mt-1"><i class="bi bi-graph-up-arrow text-success"></i> Monitor Target Jual</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Posisi Sedang Minus (Butuh Averaging) -->
        <div class="col-6 col-md-3">
            <a href="<?= Url::to(['/portfolio/index', 'tab' => 'loss']) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm bg-light text-center h-100 py-2 hover-card <?= $tab === 'loss' ? 'border border-danger border-2' : '' ?>">
                    <div class="card-body p-3">
                        <div class="text-danger small fw-semibold text-uppercase">Posisi Minus (Perlu Average)</div>
                        <div class="h3 mb-0 fw-bold text-danger"><?= $lossCount ?> Saham</div>
                        <div class="small text-muted mt-1"><i class="bi bi-tools text-danger"></i> Hitung Averaging Down</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Filter Tabs Nav -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <ul class="nav nav-pills small">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'all' ? 'active' : '' ?>" href="<?= Url::to(['/portfolio/index', 'tab' => 'all']) ?>">
                    Semua Posisi (<?= $allCount ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'profit' ? 'active bg-success' : 'text-success' ?>" href="<?= Url::to(['/portfolio/index', 'tab' => 'profit']) ?>">
                    🟢 Sedang Untung (<?= $profitCount ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'loss' ? 'active bg-danger' : 'text-danger' ?>" href="<?= Url::to(['/portfolio/index', 'tab' => 'loss']) ?>">
                    🔴 Sedang Minus / Butuh Average (<?= $lossCount ?>)
                </a>
            </li>
        </ul>
    </div>

    <!-- Tabel Daftar Posisi Portofolio -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Simbol Saham</th>
                            <th class="text-end">Harga Beli (Avg)</th>
                            <th class="text-center">Jumlah Lembar</th>
                            <th class="text-end">Total Modal</th>
                            <th class="text-end">Harga Pasar Terkini</th>
                            <th class="text-end">Nilai Portofolio</th>
                            <th class="text-center">Status P/L (%)</th>
                            <th>Target Profit (TP)</th>
                            <th class="text-center" style="width: 140px;">Rekomendasi Aksi</th>
                            <th class="text-center" style="width: 120px;">Kelola</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($positions)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <div class="display-6 text-muted mb-2"><i class="bi bi-wallet2"></i></div>
                                    <h5 class="fw-bold text-secondary">Belum ada data saham di portofolio</h5>
                                    <p class="text-muted small mb-3">Tambahkan saham yang telah Anda beli untuk memantau status untung/rugi, target jual, dan simulasi averaging.</p>
                                    <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddPosition">
                                        <i class="bi bi-plus-circle"></i> Tambah Saham Sekarang
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_values($positions) as $i => $pos): ?>
                                <?php
                                $stock = $pos->stock;
                                $plAmount = $pos->getProfitLossAmount();
                                $plPct = $pos->getProfitLossPct();
                                $isProfit = $pos->isProfit();
                                $targetPrice = $pos->getTargetSellPrice();
                                $remainingPct = $pos->getRemainingToTargetPct();
                                $rowBg = $isProfit ? 'table-success bg-opacity-10' : ($pos->isLoss() ? 'table-danger bg-opacity-10' : '');
                                ?>
                                <tr class="<?= $rowBg ?>">
                                    <td class="text-muted small"><?= $i + 1 ?></td>
                                    <td>
                                        <div class="fw-bold fs-6">
                                            <?= Html::a(Html::encode($stock->symbol), ['/portfolio/detail', 'id' => $pos->id], ['class' => 'text-decoration-none text-primary']) ?>
                                            <span class="badge bg-light text-secondary border ms-1" style="font-size:0.65rem;"><?= Html::encode($stock->exchange) ?></span>
                                        </div>
                                        <div class="small text-muted text-truncate" style="max-width: 150px; font-size: 0.72rem;">
                                            <?= Html::encode($stock->name) ?>
                                        </div>
                                    </td>
                                    <td class="text-end fw-semibold">
                                        <?= $fmt->asCurrency($pos->buy_price) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary px-2 py-1"><?= $pos->getFormattedShares() ?> lbr</span>
                                    </td>
                                    <td class="text-end text-muted small">
                                        <?= $fmt->asCurrency($pos->getTotalCost()) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bold text-dark"><?= $fmt->asCurrency($pos->getCurrentPrice()) ?></div>
                                        <?php $q = $pos->getLiveQuoteDetails(); ?>
                                        <?php if ($q): ?>
                                            <div class="small fw-semibold <?= $q['change'] >= 0 ? 'text-success' : 'text-danger' ?>" style="font-size:0.68rem;">
                                                <?= $q['change'] >= 0 ? '+' : '' ?><?= number_format($q['change'], 2) ?> (<?= $q['change_pct'] >= 0 ? '+' : '' ?><?= $q['change_pct'] ?>%)
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?= $fmt->asCurrency($pos->getCurrentValue()) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $isProfit ? 'success' : ($pos->isLoss() ? 'danger' : 'secondary') ?> px-2 py-1 fs-7 shadow-sm">
                                            <?= ($plPct >= 0 ? '+' : '') . $plPct ?>%
                                        </span>
                                        <div class="small text-<?= $isProfit ? 'success' : 'danger' ?>" style="font-size: 0.72rem;">
                                            <?= ($plAmount >= 0 ? '+' : '') . $fmt->asCurrency($plAmount) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-between align-items-center small">
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-1">
                                                Target: +<?= $pos->target_profit_pct ?>%
                                            </span>
                                            <span class="fw-bold text-dark"><?= $fmt->asCurrency($targetPrice) ?></span>
                                        </div>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar bg-<?= $isProfit ? 'success' : 'secondary' ?>" role="progressbar" style="width: <?= $pos->getProgressToTargetPct() ?>%;"></div>
                                        </div>
                                        <div class="small text-muted mt-1" style="font-size: 0.70rem;">
                                            <?php if ($remainingPct <= 0): ?>
                                                 <span class="text-success fw-bold"><i class="bi bi-check-all"></i> Target Tercapai! Siap Jual</span>
                                            <?php else: ?>
                                                <span>Kurang +<?= $remainingPct ?>% ke Target ($<?= number_format($pos->getRemainingToTargetAmount(), 2) ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($isProfit): ?>
                                            <span class="badge bg-success bg-opacity-25 text-success border border-success py-1 px-2">
                                                <i class="bi bi-bullseye me-1"></i> Amankan Target
                                            </span>
                                        <?php elseif ($pos->isLoss()): ?>
                                            <a href="<?= Url::to(['/portfolio/detail', 'id' => $pos->id]) ?>" class="badge bg-danger bg-opacity-25 text-danger border border-danger text-decoration-none py-1 px-2">
                                                <i class="bi bi-calculator me-1"></i> Hitung Average
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary py-1 px-2">Impas</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <?= Html::a('<i class="bi bi-lightning-fill"></i> Strategi', ['/portfolio/detail', 'id' => $pos->id], ['class' => 'btn btn-primary btn-sm py-0 px-2 fw-semibold', 'title' => 'Buka Analisis Multi-Fitur & Simulasi Averaging']) ?>
                                            <?= Html::a('<i class="bi bi-trash"></i>', ['/portfolio/delete', 'id' => $pos->id], [
                                                'class' => 'btn btn-outline-danger btn-sm py-0 px-1',
                                                'title' => 'Hapus dari Portofolio',
                                                'data' => [
                                                    'confirm' => "Apakah Anda yakin ingin menghapus {$stock->symbol} dari portofolio?",
                                                    'method' => 'post',
                                                ],
                                            ]) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Posisi Saham Baru -->
<div class="modal fade" id="modalAddPosition" tabindex="-1" aria-labelledby="modalAddPositionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <?php $form = ActiveForm::begin(['action' => ['/portfolio/create'], 'method' => 'post']); ?>
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="modalAddPositionLabel">
                    <i class="bi bi-plus-circle-fill me-2"></i> Tambah Saham yang Sudah Dibeli
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Simbol Saham</label>
                    <input list="stockList" name="symbol" class="form-control text-uppercase fw-bold" placeholder="Cth: NVDA, AAPL, TSLA" required autofocus autocomplete="off">
                    <datalist id="stockList">
                        <?php foreach ($availableStocks as $stk): ?>
                            <option value="<?= Html::encode($stk->symbol) ?>"><?= Html::encode($stk->name) ?> (<?= Html::encode($stk->exchange) ?>)</option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text small">Pilih simbol saham yang ada di database aplikasi.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">Harga Beli Awal ($)</label>
                        <input type="number" step="0.0001" name="PortfolioPosition[buy_price]" class="form-control" placeholder="Cth: 125.50" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">Jumlah Lembar (Shares)</label>
                        <input type="number" step="any" min="0.0001" name="PortfolioPosition[shares]" class="form-control" placeholder="Cth: 100 atau 10.5" required>
                        <div class="form-text small" style="font-size:0.70rem;">Bisa bilangan bulat atau pecahan/desimal.</div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">Target Untung (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.1" name="PortfolioPosition[target_profit_pct]" class="form-control" value="3.0" required>
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text small">Contoh: 3% untuk target jual cepat.</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">Tanggal Pembelian</label>
                        <input type="date" name="PortfolioPosition[buy_date]" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Catatan / Alasan Entry (Opsional)</label>
                    <textarea name="PortfolioPosition[notes]" class="form-control" rows="2" placeholder="Cth: Beli saat Golden Cross MA20, stop loss di bawah $120"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">💾 Simpan ke Portofolio</button>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<style>
.hover-card {
    transition: transform 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.hover-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.1) !important;
}
</style>
