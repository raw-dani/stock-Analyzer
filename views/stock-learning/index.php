<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $viewMode */
/** @var array $groupedData */
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
$viewMode = $viewMode ?? 'grouped';
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
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #0284c7 !important;">
                <div class="card-body p-3">
                    <span class="small text-muted fw-bold text-uppercase d-block mb-1">Total Saham Terlatih</span>
                    <h3 class="fw-bold text-dark mb-0 font-monospace"><?= number_format($stats['total_unique_stocks'] ?? count($groupedData)) ?></h3>
                    <small class="text-secondary"><?= number_format($stats['total_trained']) ?> total sesi simulasi</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <span class="small text-muted fw-bold text-uppercase d-block mb-1">Rata-rata OOS Win-Rate</span>
                    <h3 class="fw-bold text-success mb-0 font-monospace"><?= number_format((float) $stats['avg_oos_wr'], 1) ?>%</h3>
                    <small class="text-muted">Akurasi validasi independen</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #8b5cf6 !important;">
                <div class="card-body p-3">
                    <span class="small text-muted fw-bold text-uppercase d-block mb-1">Model Akurasi Tinggi (&ge; 60%)</span>
                    <h3 class="fw-bold mb-0 font-monospace" style="color: #8b5cf6;"><?= number_format($stats['high_wr_count']) ?></h3>
                    <small class="text-muted">Kandidat strategi trading handal</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <span class="small text-muted fw-bold text-uppercase d-block mb-1">Metode Optimasi</span>
                    <h3 class="fw-bold text-warning mb-0 font-monospace" style="font-size: 1.3rem;">GA + Walk-Forward</h3>
                    <small class="text-muted">Anti curve-fitting &amp; lookahead</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Train Card -->
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
                    <?= Html::dropDownList('timeframe', 24, [24 => 'Daily (1D - Rekomendasi)', 4 => '4 Jam (4H)', 1 => '1 Jam (1H)'], ['class' => 'form-select form-select-sm']) ?>
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
                <?= Html::hiddenInput('view', $viewMode) ?>
                <div class="col-auto">
                    <span class="small fw-bold text-muted"><i class="bi bi-funnel me-1"></i> Filter:</span>
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
                        <a href="<?= Url::to(['/stock-learning/index', 'view' => $viewMode]) ?>" class="btn btn-outline-danger btn-sm ms-1">Reset</a>
                    <?php endif; ?>
                </div>
            <?= Html::endForm() ?>
        </div>
    </div>

    <!-- Rekomendasi Cara Mendapatkan Hasil Paling Maksimal -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #f8fafc 0%, #f0f7ff 100%); border: 1px solid #bae6fd !important;">
        <div class="card-header bg-transparent py-3 border-bottom border-info border-opacity-25 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary p-2 rounded-circle shadow-xs">
                    <i class="bi bi-lightbulb-fill text-white fs-6"></i>
                </span>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Rekomendasi Cara Mendapatkan Hasil Paling Maksimal</h6>
                    <small class="text-muted">Untuk mendapatkan model dengan akurasi dan profitabilitas yang benar-benar handal:</small>
                </div>
            </div>
            <button class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-xs" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOptimizationGuide" aria-expanded="true" aria-controls="collapseOptimizationGuide">
                <i class="bi bi-chevron-down me-1"></i> Buka / Sembunyikan Panduan
            </button>
        </div>
        <div class="collapse show" id="collapseOptimizationGuide">
            <div class="card-body p-3 p-md-4">
                <div class="row g-3">
                    <!-- Poin 1 -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 border-0 rounded-3 shadow-xs p-3" style="background: #ffffff; border-top: 3px solid #0284c7 !important;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-2"><i class="bi bi-calendar-check fs-6"></i></span>
                                <h6 class="fw-bold text-dark mb-0 small">Prioritaskan Timeframe Daily (1D)</h6>
                            </div>
                            <ul class="text-muted small ps-3 mb-0" style="font-size: 0.81rem; line-height: 1.55;">
                                <li class="mb-1">Data Daily memiliki <strong>500+ hari historis (2 tahun penuh)</strong> di database Anda.</li>
                                <li>Pembelajaran di Daily jauh lebih stabil, tahan banting, dan teruji di berbagai siklus pasar (bullish, bearish, dan sideways).</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Poin 2 -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 border-0 rounded-3 shadow-xs p-3" style="background: #ffffff; border-top: 3px solid #10b981 !important;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success bg-opacity-10 text-success p-2 rounded-2"><i class="bi bi-sliders fs-6"></i></span>
                                <h6 class="fw-bold text-dark mb-0 small">Ganti Strategi Basis Sesuai Karakter Saham</h6>
                            </div>
                            <ul class="text-muted small ps-3 mb-0" style="font-size: 0.81rem; line-height: 1.55;">
                                <li class="mb-1">Jika saham sering <strong>swing / mantul di area jenuh</strong>: Pilih strategi <em>Stochastic Oscillator</em> atau <em>RSI Momentum</em>.</li>
                                <li>Jika saham <strong>tren kuat (trending)</strong>: Pilih strategi <em>Multi-Indicator Confluence</em>.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Poin 3 -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 border-0 rounded-3 shadow-xs p-3" style="background: #ffffff; border-top: 3px solid #f59e0b !important;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-warning bg-opacity-10 text-warning p-2 rounded-2"><i class="bi bi-graph-up-arrow fs-6"></i></span>
                                <h6 class="fw-bold text-dark mb-0 small">Cari Parameter Profit Factor Tinggi, Bukan Hanya Win Rate</h6>
                            </div>
                            <ul class="text-muted small ps-3 mb-0" style="font-size: 0.81rem; line-height: 1.55;">
                                <li class="mb-1">Cari parameter dengan Profit Factor tinggi, bukan hanya Win Rate.</li>
                                <li>Model dengan Win Rate 55% tetapi <strong>Profit Factor 2.50</strong> jauh lebih menguntungkan daripada Win Rate 75% tetapi sekali kalah menghabiskan semua profit.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Poin 4 -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 border-0 rounded-3 shadow-xs p-3" style="background: #ffffff; border-top: 3px solid #8b5cf6 !important;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-purple bg-opacity-10 text-purple p-2 rounded-2" style="color: #8b5cf6; background: rgba(139,92,246,0.1);"><i class="bi bi-arrow-repeat fs-6"></i></span>
                                <h6 class="fw-bold text-dark mb-0 small">Cukup Latih 2–3 Kali per Saham</h6>
                            </div>
                            <ul class="text-muted small ps-3 mb-0" style="font-size: 0.81rem; line-height: 1.55;">
                                <li class="mb-1">Jalankan 1 kali dengan <strong>35 generasi</strong>. Jika ingin hasil lebih mendalam, jalankan 1 kali lagi dengan <strong>60 generasi</strong>.</li>
                                <li>Sistem kini otomatis memilih <strong>Model Terbaik</strong> di antara percobaan-percobaan tersebut.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Container with Tab Switcher -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <!-- View Mode Switcher Pills -->
            <ul class="nav nav-pills card-header-pills">
                <li class="nav-item">
                    <a class="nav-link small py-1 px-3 fw-bold <?= $viewMode === 'grouped' ? 'active' : '' ?>" 
                       href="<?= Url::to(['/stock-learning/index', 'view' => 'grouped', 'symbol' => $symbolSearch, 'strategy' => $strategyFilter]) ?>">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> Ringkasan per Saham (Grouped)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link small py-1 px-3 fw-bold <?= $viewMode === 'history' ? 'active' : '' ?>" 
                       href="<?= Url::to(['/stock-learning/index', 'view' => 'history', 'symbol' => $symbolSearch, 'strategy' => $strategyFilter]) ?>">
                        <i class="bi bi-list-ul me-1"></i> Seluruh Riwayat Sesi (Audit Log)
                    </a>
                </li>
            </ul>

            <span class="badge bg-secondary rounded-pill">
                <?= $viewMode === 'grouped' ? count($groupedData) . ' Saham Unik' : count($runs) . ' Sesi Tercatat' ?>
            </span>
        </div>
        <div class="card-body p-0">
            <?php if ($viewMode === 'grouped'): ?>
                <!-- TAMPILAN 1: GROUPED PER KODE SAHAM (1 Baris per Saham) -->
                <?php if (empty($groupedData)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-cpu fs-1 text-secondary opacity-50 mb-2 d-block"></i>
                        <h6 class="fw-bold">Belum Ada Model Pembelajaran</h6>
                        <p class="small text-muted mb-0">Latih saham pertama Anda menggunakan form di atas.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase text-secondary">
                                <tr>
                                    <th>Saham</th>
                                    <th class="text-center">Total Latihan</th>
                                    <th>Model Terbaik</th>
                                    <th>Timeframe</th>
                                    <th class="text-center">Best OOS Win-Rate</th>
                                    <th class="text-center">In-Sample WR</th>
                                    <th class="text-center">Profit Factor</th>
                                    <th class="text-center">Max Drawdown</th>
                                    <th>Latihan Terakhir</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedData as $row): ?>
                                    <?php
                                    $best = $row['best_run'];
                                    $oosWr = (float) ($best->oos_win_rate ?? 0);
                                    $oosBadgeClass = ($oosWr >= 65.0) ? 'text-success fw-bold' : (($oosWr >= 50.0) ? 'text-primary' : 'text-danger');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark font-monospace fs-6">
                                                <?= Html::encode($row['symbol']) ?>
                                            </div>
                                            <div class="text-muted small text-truncate" style="max-width: 180px;">
                                                <?= Html::encode($row['stock_name']) ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1 font-monospace">
                                                <i class="bi bi-arrow-repeat me-1"></i><?= $row['total_sessions'] ?> Sesi
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= Html::encode($strategies[$best->strategy_type] ?? $best->strategy_type) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border">
                                                <?= match ((int)$best->timeframe) { 1 => '1 Jam (1H)', 4 => '4 Jam (4H)', default => 'Daily (1D)' } ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($best->oos_win_rate !== null && (int)$best->total_trades > 0): ?>
                                                <span class="font-monospace fs-6 <?= $oosBadgeClass ?>">
                                                    <?= number_format((float) $best->oos_win_rate, 1) ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border" style="font-size:0.68rem;">0 Trade</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center font-monospace text-muted small">
                                            <?= $best->is_win_rate !== null ? number_format((float) $best->is_win_rate, 1) . '%' : '-' ?>
                                        </td>
                                        <td class="text-center font-monospace small">
                                            <?= ($best->profit_factor !== null && (float)$best->profit_factor > 0) ? number_format((float) $best->profit_factor, 2) : '1.00' ?>
                                        </td>
                                        <td class="text-center font-monospace text-danger small">
                                            <?= ($best->max_drawdown !== null && (float)$best->max_drawdown > 0) ? '-' . number_format((float) $best->max_drawdown, 1) . '%' : '0.0%' ?>
                                        </td>
                                        <td class="small text-muted font-monospace">
                                            <?= date('d M Y H:i', $row['latest_created_at']) ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= Url::to(['/stock-learning/detail', 'symbol' => $row['symbol']]) ?>" class="btn btn-sm btn-primary shadow-xs fw-semibold">
                                                <i class="bi bi-eye-fill me-1"></i> Buka Model
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- TAMPILAN 2: AUDIT LOG SELURUH RIWAYAT SESI -->
                <?php if (empty($runs)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-clock-history fs-1 text-secondary opacity-50 mb-2 d-block"></i>
                        <h6 class="fw-bold">Belum Ada Sesi Pembelajaran</h6>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase text-secondary">
                                <tr>
                                    <th># ID</th>
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
                                    $oosBadgeClass = ($oosWr >= 65.0) ? 'text-success fw-bold' : (($oosWr >= 50.0) ? 'text-primary' : 'text-danger');
                                    ?>
                                    <tr>
                                        <td class="text-muted small font-monospace">#<?= $r->id ?></td>
                                        <td>
                                            <div class="fw-bold text-dark font-monospace fs-6">
                                                <?= Html::encode($r->symbol) ?>
                                            </div>
                                            <small class="text-muted" style="font-size:0.75rem;">
                                                <?= date('d M Y H:i:s', $r->created_at) ?>
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
                                            <a href="<?= Url::to(['/stock-learning/detail', 'symbol' => $r->symbol, 'run_id' => $r->id]) ?>" class="btn btn-sm btn-outline-primary shadow-xs">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
