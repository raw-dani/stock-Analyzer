<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\StockLearningRun[] $runs */
/** @var string $symbolSearch */
/** @var string $strategyFilter */
/** @var string $statusFilter */
/** @var array $stats */
/** @var array $strategies */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Stock Learning Engine — Pelatihan Pola & Strategi Adaptif';
$this->params['breadcrumbs'][] = 'Stock Learning Engine';
?>

<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="fs-3 text-primary"><i class="bi bi-cpu"></i></span>
                <h1 class="h3 mb-0 fw-bold text-dark">Stock Learning Engine</h1>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">AI Genetic Optimizer</span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Melatih dan mencari parameter strategi trading paling optimal per kode saham melalui ratusan simulasi berulang (Walk-Forward Optimization & Anti-Overfitting).
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= Url::to(['/dashboard/index']) ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #0284c7 !important;">
                <div class="card-body p-3">
                    <span class="small text-muted fw-bold text-uppercase d-block mb-1">Total Model Dilatih</span>
                    <h3 class="fw-bold text-dark mb-0 font-monospace"><?= number_format($stats['total_trained']) ?></h3>
                    <small class="text-secondary">Sesi pembelajaran selesai</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <span class="small text-muted fw-bold text-uppercase d-block mb-1">Rata-rata OOS Win-Rate</span>
                    <h3 class="fw-bold text-success mb-0 font-monospace"><?= number_format((float) $stats['avg_oos_wr'], 1) ?>%</h3>
                    <small class="text-muted">Validasi data belum pernah dilihat (Out-of-Sample)</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <span class="small text-muted fw-bold text-uppercase d-block mb-1">Model Akurasi Tinggi (&ge; 65%)</span>
                    <h3 class="fw-bold text-warning mb-0 font-monospace"><?= number_format($stats['high_wr_count']) ?></h3>
                    <small class="text-muted">Kandidat strategi trading handal</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Train & Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-lightning-charge text-warning fs-5"></i>
                <h6 class="fw-bold mb-0 text-dark">Mulai Pembelajaran Baru untuk Kode Saham</h6>
            </div>
        </div>
        <div class="card-body p-3">
            <?= Html::beginForm(['/stock-learning/run'], 'post', ['class' => 'row g-3 align-items-end mb-3']) ?>
                <div class="col-md-3 col-12">
                    <label class="form-label small fw-bold text-muted">Kode Saham (Ticker)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <?= Html::input('text', 'symbol', '', ['class' => 'form-control text-uppercase font-monospace fw-bold', 'placeholder' => 'Contoh: FCX, AAPL, BBCA', 'required' => true]) ?>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small fw-bold text-muted">Strategi Basis</label>
                    <?= Html::dropDownList('strategy', 'stochastic', $strategies, ['class' => 'form-select form-select-sm']) ?>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-bold text-muted">Timeframe</label>
                    <?= Html::dropDownList('timeframe', 24, [24 => 'Daily (1D)', 4 => '4 Jam (4H)', 1 => '1 Jam (1H)'], ['class' => 'form-select form-select-sm']) ?>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small fw-bold text-muted">Jumlah Generasi</label>
                    <?= Html::dropDownList('generations', 35, [20 => '20 Gen (Cepat ~15s)', 35 => '35 Gen (Standar ~30s)', 60 => '60 Gen (Mendalam ~1m)'], ['class' => 'form-select form-select-sm']) ?>
                </div>
                <div class="col-md-2 col-6 text-end">
                    <?= Html::submitButton('<i class="bi bi-play-circle-fill me-1"></i> Mulai Belajar', ['class' => 'btn btn-primary btn-sm w-100 fw-bold shadow-sm']) ?>
                </div>
            <?= Html::endForm() ?>

            <hr class="my-3 text-muted opacity-25">

            <!-- Filter Table List -->
            <?= Html::beginForm(['/stock-learning/index'], 'get', ['class' => 'row g-2 align-items-center']) ?>
                <div class="col-auto">
                    <span class="small fw-bold text-muted"><i class="bi bi-funnel me-1"></i> Filter Histori:</span>
                </div>
                <div class="col-md-3 col-6">
                    <?= Html::input('text', 'symbol', $symbolSearch, ['class' => 'form-control form-control-sm', 'placeholder' => 'Cari simbol...']) ?>
                </div>
                <div class="col-md-3 col-6">
                    <?= Html::dropDownList('strategy', $strategyFilter, array_merge(['' => 'Semua Strategi'], $strategies), ['class' => 'form-select form-select-sm']) ?>
                </div>
                <div class="col-auto">
                    <?= Html::submitButton('<i class="bi bi-filter"></i> Filter', ['class' => 'btn btn-outline-secondary btn-sm px-3']) ?>
                    <?php if (!empty($symbolSearch) || !empty($strategyFilter)): ?>
                        <a href="<?= Url::to(['/stock-learning/index']) ?>" class="btn btn-outline-danger btn-sm ms-1">Reset</a>
                    <?php endif; ?>
                </div>
            <?= Html::endForm() ?>
        </div>
    </div>

    <!-- Runs Table List -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="bi bi-journal-text text-primary me-2"></i>Daftar Hasil Pembelajaran Parameter
            </h6>
            <span class="badge bg-secondary rounded-pill"><?= count($runs) ?> Sesi Ditemukan</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($runs)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-cpu fs-1 text-secondary opacity-50 mb-2 d-block"></i>
                    <h6 class="fw-bold">Belum Ada Sesi Pembelajaran</h6>
                    <p class="small text-muted mb-0">Masukkan kode saham pada form di atas untuk memulai simulasi optimasi.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase text-secondary">
                            <tr>
                                <th>Saham</th>
                                <th>Strategi Basis</th>
                                <th>Timeframe</th>
                                <th class="text-center">OOS Win-Rate</th>
                                <th class="text-center">In-Sample WR</th>
                                <th class="text-center">Profit Factor</th>
                                <th class="text-center">Max DD</th>
                                <th class="text-center">Total Trade</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($runs as $r): ?>
                                <?php
                                $statusBadge = match ($r->status) {
                                    'completed' => 'bg-success',
                                    'running' => 'bg-warning text-dark',
                                    'failed' => 'bg-danger',
                                    default => 'bg-secondary',
                                };
                                $oosWr = (float) ($r->oos_win_rate ?? 0);
                                $oosBadgeClass = ($oosWr >= 70.0) ? 'text-success fw-bold' : (($oosWr >= 55.0) ? 'text-primary' : 'text-danger');
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark font-monospace fs-6">
                                            <?= Html::encode($r->symbol) ?>
                                        </div>
                                        <small class="text-muted" style="font-size:0.75rem;">
                                            <?= date('d M Y H:i', $r->created_at) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= Html::encode($strategies[$r->strategy_type] ?? $r->strategy_type) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border">
                                            <?= match ((int)$r->timeframe) { 1 => '1 Jam (1H)', 4 => '4 Jam (4H)', default => 'Daily (1D)' } ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($r->oos_win_rate !== null && (int)$r->total_trades > 0): ?>
                                            <span class="font-monospace fs-6 <?= $oosBadgeClass ?>">
                                                <?= number_format((float) $r->oos_win_rate, 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border" style="font-size:0.68rem;">0 Trade</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center font-monospace text-muted small">
                                        <?= $r->is_win_rate !== null ? number_format((float) $r->is_win_rate, 1) . '%' : '-' ?>
                                    </td>
                                    <td class="text-center font-monospace small">
                                        <?= ($r->profit_factor !== null && (float)$r->profit_factor > 0) ? number_format((float) $r->profit_factor, 2) : '1.00' ?>
                                    </td>
                                    <td class="text-center font-monospace text-danger small">
                                        <?= ($r->max_drawdown !== null && (float)$r->max_drawdown > 0) ? '-' . number_format((float) $r->max_drawdown, 1) . '%' : '0.0%' ?>
                                    </td>
                                    <td class="text-center font-monospace small text-muted">
                                        <?= $r->total_trades ?? 0 ?> trade
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $statusBadge ?> px-2 py-1 text-uppercase" style="font-size:0.68rem;">
                                            <?= Html::encode($r->status) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= Url::to(['/stock-learning/detail', 'symbol' => $r->symbol]) ?>" class="btn btn-sm btn-outline-primary shadow-xs">
                                            <i class="bi bi-eye"></i> Detail &amp; Sinyal
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
