<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $viewMode */
/** @var string $sortBy */
/** @var string $modelMode */
/** @var array $groupedData */
/** @var app\models\StockLearningRun[] $runs */
/** @var string $symbolSearch */
/** @var string $strategyFilter */
/** @var string $statusFilter */
/** @var array $stats */
/** @var array $strategies */
/** @var array $screenerResults */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Stock Learning Engine — Pelatihan Pola & Strategi Adaptif';
$this->params['breadcrumbs'][] = 'Stock Learning Engine';
$viewMode = $viewMode ?? 'grouped';
$sortBy = $sortBy ?? 'profit_factor';
$modelMode = $modelMode ?? 'best_pf';
$screenerResults = $screenerResults ?? [
    'buy_signals' => [],
    'wait_signals' => [],
    'buy_count' => 0,
    'wait_count' => 0,
    'total_scanned' => 0,
    'scanned_at' => time(),
];
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
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #10b981 !important; background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%);">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-success fw-bold text-uppercase">Profit Factor Tertinggi</span>
                        <i class="bi bi-trophy-fill text-warning"></i>
                    </div>
                    <h3 class="fw-bold text-success mb-0 font-monospace">
                        <?= ($stats['max_profit_factor'] ?? 0) > 0 ? number_format((float) $stats['max_profit_factor'], 2) : '1.00' ?>
                    </h3>
                    <small class="text-muted">
                        <?php if (!empty($stats['max_pf_symbol']) && $stats['max_pf_symbol'] !== '-'): ?>
                            Saham <strong class="text-dark font-monospace"><?= Html::encode($stats['max_pf_symbol']) ?></strong> (Hasil Terbaik)
                        <?php else: ?>
                            Belum ada trade OOS
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #8b5cf6 !important;">
                <div class="card-body p-3">
                    <span class="small text-muted fw-bold text-uppercase d-block mb-1">Model Akurasi Tinggi (&ge; 60%)</span>
                    <h3 class="fw-bold mb-0 font-monospace" style="color: #8b5cf6;"><?= number_format($stats['high_wr_count']) ?></h3>
                    <small class="text-muted">Rata-rata OOS WR: <?= number_format((float) $stats['avg_oos_wr'], 1) ?>%</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #f59e0b !important; background: linear-gradient(180deg, #ffffff 0%, #fffbeb 100%);">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-warning fw-bold text-uppercase">Data Terbaru</span>
                        <i class="bi bi-clock-history text-warning"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-0 font-monospace" style="font-size: 1.25rem;">
                        <?= !empty($stats['latest_trained_at']) ? date('d M H:i', (int)$stats['latest_trained_at']) : '-' ?>
                    </h3>
                    <small class="text-muted">
                        <?php if (!empty($stats['latest_symbol']) && $stats['latest_symbol'] !== '-'): ?>
                            Saham <strong class="text-dark font-monospace"><?= Html::encode($stats['latest_symbol']) ?></strong> (Sesi Terakhir)
                        <?php else: ?>
                            Belum ada sesi
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Banner Notifikasi Sinyal Beli Aktif Hari Ini (AI Daily Screener) -->
    <?php if (!empty($screenerResults['buy_count']) && $screenerResults['buy_count'] > 0): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-3 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-left: 5px solid #10b981 !important;">
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success p-2 rounded-circle shadow-xs">
                    <i class="bi bi-radar text-white fs-5"></i>
                </span>
                <div>
                    <h6 class="fw-bold mb-1 text-success">
                        🔥 Sinyal Beli Aktif Terdeteksi Hari Ini (<?= $screenerResults['buy_count'] ?> Saham Berpotensi Naik)
                    </h6>
                    <p class="small text-muted mb-0">
                        Berdasarkan lilin penutupan bursa terakhir (04:00 - 05:00 WIB), terdapat <strong><?= $screenerResults['buy_count'] ?> saham</strong> dengan setup beli terkonfirmasi dari model AI terlatih.
                    </p>
                </div>
            </div>
            <a href="<?= Url::to(['/stock-learning/index', 'view' => 'screener']) ?>" class="btn btn-success btn-sm px-3 fw-bold rounded-pill shadow-xs">
                Buka AI Daily Screener <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    <?php endif; ?>

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
                    <span class="small fw-bold text-muted"><i class="bi bi-funnel me-1"></i> Filter &amp; Urutan:</span>
                </div>
                <div class="col-md-2 col-6">
                    <?= Html::input('text', 'symbol', $symbolSearch, ['class' => 'form-control form-control-sm', 'placeholder' => 'Cari simbol...']) ?>
                </div>
                <div class="col-md-2 col-6">
                    <?= Html::dropDownList('strategy', $strategyFilter, array_merge(['' => 'Semua Strategi'], $strategies), ['class' => 'form-select form-select-sm']) ?>
                </div>
                <div class="col-md-3 col-6">
                    <?= Html::dropDownList('sort', $sortBy, [
                        'profit_factor' => '💎 Profit Factor Tertinggi',
                        'latest' => '🕒 Data Terbaru (Sesi Terakhir)',
                        'win_rate' => '🎯 Best OOS Win-Rate',
                        'symbol' => '🔤 Simbol Saham (A-Z)',
                    ], ['class' => 'form-select form-select-sm']) ?>
                </div>
                <div class="col-md-3 col-6">
                    <?= Html::dropDownList('model_mode', $modelMode, [
                        'best_pf' => '🏆 Model PF Tertinggi',
                        'latest' => '⚡ Model Sesi Terakhir',
                    ], ['class' => 'form-select form-select-sm']) ?>
                </div>
                <div class="col-auto">
                    <?= Html::submitButton('<i class="bi bi-filter"></i> Terapkan', ['class' => 'btn btn-outline-secondary btn-sm px-3']) ?>
                    <?php if (!empty($symbolSearch) || !empty($strategyFilter) || $sortBy !== 'profit_factor' || $modelMode !== 'best_pf'): ?>
                        <a href="<?= Url::to(['/stock-learning/index', 'view' => $viewMode]) ?>" class="btn btn-outline-danger btn-sm ms-1" title="Reset filter &amp; urutan default">Reset</a>
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
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
            <!-- View Mode Switcher Pills -->
            <ul class="nav nav-pills card-header-pills">
                <li class="nav-item">
                    <a class="nav-link small py-1 px-3 fw-bold <?= $viewMode === 'screener' ? 'active bg-danger text-white' : 'text-danger' ?>" 
                       href="<?= Url::to(['/stock-learning/index', 'view' => 'screener']) ?>">
                        <i class="bi bi-radar me-1"></i> Sinyal Beli Hari Ini (AI Screener)
                        <?php if (!empty($screenerResults['buy_count']) && $screenerResults['buy_count'] > 0): ?>
                            <span class="badge bg-white text-danger ms-1 px-2 fw-bold"><?= $screenerResults['buy_count'] ?> Beli</span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted ms-1 px-2">0 Beli</span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link small py-1 px-3 fw-bold <?= $viewMode === 'grouped' ? 'active' : '' ?>" 
                       href="<?= Url::to(['/stock-learning/index', 'view' => 'grouped', 'sort' => $sortBy, 'model_mode' => $modelMode, 'symbol' => $symbolSearch, 'strategy' => $strategyFilter]) ?>">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> Ringkasan per Saham (Grouped)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link small py-1 px-3 fw-bold <?= $viewMode === 'history' ? 'active' : '' ?>" 
                       href="<?= Url::to(['/stock-learning/index', 'view' => 'history', 'sort' => $sortBy, 'model_mode' => $modelMode, 'symbol' => $symbolSearch, 'strategy' => $strategyFilter]) ?>">
                        <i class="bi bi-list-ul me-1"></i> Seluruh Riwayat Sesi (Audit Log)
                    </a>
                </li>
            </ul>

            <!-- Quick Sort / Screener Actions -->
            <?php if ($viewMode === 'screener'): ?>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <a href="<?= Url::to(['/stock-learning/index', 'view' => 'screener', 'refresh_screener' => 1]) ?>" class="btn btn-outline-primary btn-sm rounded-pill fw-bold shadow-xs">
                        <i class="bi bi-arrow-clockwise me-1"></i> Scan Ulang Sekarang
                    </a>
                    <span class="badge bg-danger rounded-pill">
                        <?= count($screenerResults['buy_signals'] ?? []) ?> Beli
                    </span>
                    <span class="badge bg-secondary rounded-pill font-monospace">
                        <?= $screenerResults['total_scanned'] ?? 0 ?> Saham Terlatih
                    </span>
                </div>
            <?php else: ?>
                <!-- Quick Sort Pills -->
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="small text-muted fw-bold d-none d-md-inline"><i class="bi bi-sort-down me-1"></i>Urutkan:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="<?= Url::to(['/stock-learning/index', 'view' => $viewMode, 'sort' => 'profit_factor', 'model_mode' => $modelMode, 'symbol' => $symbolSearch, 'strategy' => $strategyFilter]) ?>" 
                           class="btn btn-sm <?= $sortBy === 'profit_factor' ? 'btn-success fw-bold text-white shadow-xs' : 'btn-outline-secondary' ?>">
                            <i class="bi bi-trophy-fill text-warning me-1"></i> PF Tertinggi
                        </a>
                        <a href="<?= Url::to(['/stock-learning/index', 'view' => $viewMode, 'sort' => 'latest', 'model_mode' => $modelMode, 'symbol' => $symbolSearch, 'strategy' => $strategyFilter]) ?>" 
                           class="btn btn-sm <?= $sortBy === 'latest' ? 'btn-warning fw-bold text-dark shadow-xs' : 'btn-outline-secondary' ?>">
                            <i class="bi bi-clock-history me-1"></i> Data Terbaru
                        </a>
                        <a href="<?= Url::to(['/stock-learning/index', 'view' => $viewMode, 'sort' => 'win_rate', 'model_mode' => $modelMode, 'symbol' => $symbolSearch, 'strategy' => $strategyFilter]) ?>" 
                           class="btn btn-sm <?= $sortBy === 'win_rate' ? 'btn-primary fw-bold text-white shadow-xs' : 'btn-outline-secondary' ?>">
                            <i class="bi bi-bullseye me-1"></i> Win Rate
                        </a>
                    </div>

                    <span class="badge bg-secondary rounded-pill ms-1">
                        <?= $viewMode === 'grouped' ? count($groupedData) . ' Saham Unik' : count($runs) . ' Sesi Tercatat' ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php
            // Helper function untuk generate link sorting header kolom
            $makeSortUrl = function(string $col) use ($viewMode, $symbolSearch, $strategyFilter, $modelMode): string {
                return Url::to([
                    '/stock-learning/index',
                    'view' => $viewMode,
                    'sort' => $col,
                    'model_mode' => $modelMode,
                    'symbol' => $symbolSearch,
                    'strategy' => $strategyFilter,
                ]);
            };
            ?>

            <?php if ($viewMode === 'screener'): ?>
                <!-- TAMPILAN 3: AI DAILY SCREENER (Pasca 04:00 - 05:00 WIB) -->
                <div class="p-3 bg-light border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger p-2 rounded-circle shadow-xs">
                                <i class="bi bi-radar text-white fs-6"></i>
                            </span>
                            <h6 class="fw-bold mb-0 text-dark">AI Daily Screener — Hasil Pemindaian Pasca Penutupan Bursa</h6>
                        </div>
                        <small class="text-muted">
                            Data diperbarui setiap pukul 05:00 - 05:30 WIB otomatis via Cron Job. Waktu scan terakhir: 
                            <strong><?= !empty($screenerResults['scanned_at']) ? date('d M Y, H:i', (int)$screenerResults['scanned_at']) : '-' ?> WIB</strong>
                            (<?= $screenerResults['total_scanned'] ?? 0 ?> Saham Terlatih Dipindai)
                        </small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="<?= Url::to(['/stock-learning/index', 'view' => 'screener', 'refresh_screener' => 1]) ?>" class="btn btn-outline-primary btn-sm rounded-pill fw-bold shadow-xs">
                            <i class="bi bi-arrow-clockwise me-1"></i> Scan Ulang Sekarang
                        </a>
                    </div>
                </div>

                <div class="p-3 p-md-4">
                    <!-- Section BUY Signals -->
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success bg-opacity-10 text-success p-2 rounded-2">
                                <i class="bi bi-lightning-charge-fill fs-5"></i>
                            </span>
                            <div>
                                <h5 class="fw-bold mb-0 text-dark">Sinyal Beli Aktif Hari Ini (BUY Opportunities)</h5>
                                <small class="text-muted">Saham yang memenuhi seluruh parameter optimal hasil pelatihan AI untuk potensi kenaikan di sesi berikutnya.</small>
                            </div>
                        </div>
                        <span class="badge bg-success px-3 py-2 rounded-pill fs-6 font-monospace fw-bold">
                            <?= count($screenerResults['buy_signals'] ?? []) ?> Saham Siap Beli
                        </span>
                    </div>

                    <?php if (empty($screenerResults['buy_signals'])): ?>
                        <div class="card border border-warning border-opacity-25 rounded-4 p-4 text-center mb-4" style="background: linear-gradient(135deg, #fffdf5 0%, #fffbeb 100%);">
                            <div class="my-2">
                                <i class="bi bi-hourglass-split text-warning display-5"></i>
                            </div>
                            <h6 class="fw-bold text-dark mt-2 mb-1">Belum Ada Sinyal Beli yang Terpicu Hari Ini</h6>
                            <p class="text-muted small mx-auto mb-3" style="max-width: 620px;">
                                Seluruh model AI dari saham yang telah dilatih saat ini berstatus <strong>WAIT</strong> (harga belum menyentuh level jenuh jual atau belum terjadi golden cross). Kondisi ini wajar dan membuktikan model AI disiplin menjaga modal, hanya masuk ketika probabilitas tinggi.
                            </p>
                            <div>
                                <a href="#tableWaitList" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                                    <i class="bi bi-eye me-1"></i> Lihat Kondisi <?= count($screenerResults['wait_signals'] ?? []) ?> Saham Dalam Monitoring (WAIT)
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row g-3 mb-4">
                            <?php foreach ($screenerResults['buy_signals'] as $bs): ?>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden" style="border-top: 4px solid #10b981 !important; background: #ffffff;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <span class="h5 fw-bold text-dark font-monospace mb-0"><?= Html::encode($bs['symbol']) ?></span>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 ms-1 fw-bold">
                                                        <i class="bi bi-check-circle-fill me-1"></i> BUY
                                                    </span>
                                                    <small class="text-muted d-block text-truncate" style="max-width: 200px;"><?= Html::encode($bs['name']) ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.72rem;">
                                                        <?= Html::encode($bs['strategy_label']) ?>
                                                    </span>
                                                    <span class="badge bg-light text-secondary border px-1" style="font-size: 0.72rem;">1D</span>
                                                </div>
                                            </div>

                                            <!-- Trading Plan Box -->
                                            <div class="bg-light rounded-3 p-2 mb-3">
                                                <div class="row g-2 text-center">
                                                    <div class="col-4">
                                                        <small class="text-muted d-block" style="font-size: 0.7rem;">HARGA ENTRY</small>
                                                        <strong class="font-monospace text-dark">$<?= number_format((float)$bs['current_price'], 2) ?></strong>
                                                    </div>
                                                    <div class="col-4 border-start border-end">
                                                        <small class="text-success fw-bold d-block" style="font-size: 0.7rem;">TARGET (+<?= $bs['target_pct'] ?>%)</small>
                                                        <strong class="font-monospace text-success">$<?= number_format((float)$bs['target_price'], 2) ?></strong>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-danger fw-bold d-block" style="font-size: 0.7rem;">STOP LOSS (-<?= $bs['stop_loss_pct'] ?>%)</small>
                                                        <strong class="font-monospace text-danger">$<?= number_format((float)$bs['stop_loss'], 2) ?></strong>
                                                    </div>
                                                </div>
                                                <div class="text-center mt-2 pt-1 border-top border-secondary border-opacity-10">
                                                    <small class="text-muted" style="font-size: 0.75rem;">
                                                        Risk-to-Reward: <strong class="text-primary font-monospace">1 : <?= $bs['risk_reward'] ?></strong>
                                                    </small>
                                                </div>
                                            </div>

                                            <!-- Historical AI Track Record -->
                                            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                                                <small class="text-muted" style="font-size: 0.75rem;">OOS Win Rate:</small>
                                                <span class="fw-bold font-monospace <?= ($bs['oos_win_rate'] ?? 0) >= 60.0 ? 'text-success' : 'text-primary' ?>" style="font-size: 0.8rem;">
                                                    <?= $bs['oos_win_rate'] !== null ? number_format((float)$bs['oos_win_rate'], 1) . '%' : '0.0%' ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-3 px-1">
                                                <small class="text-muted" style="font-size: 0.75rem;">Profit Factor:</small>
                                                <span class="badge bg-success text-white px-2 py-1 font-monospace fw-bold" style="font-size: 0.75rem;">
                                                    <?= number_format((float)($bs['profit_factor'] ?? 1.0), 2) ?>
                                                </span>
                                            </div>

                                            <!-- Diagnostic Notes -->
                                            <?php if (!empty($bs['diagnostics']['summary'])): ?>
                                                <div class="alert alert-success bg-opacity-10 border border-success border-opacity-25 rounded-3 p-2 mb-3 small" style="font-size: 0.78rem;">
                                                    <i class="bi bi-check-circle me-1 text-success"></i> <?= Html::encode($bs['diagnostics']['summary']) ?>
                                                </div>
                                            <?php endif; ?>

                                            <a href="<?= Url::to(['/stock-learning/detail', 'symbol' => $bs['symbol'], 'run_id' => $bs['run_id']]) ?>" class="btn btn-outline-primary btn-sm w-100 fw-bold rounded-pill shadow-xs">
                                                Buka Detail Sesi &amp; Chart <i class="bi bi-arrow-right ms-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Table WAIT LIST -->
                    <div id="tableWaitList" class="mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning bg-opacity-10 text-dark p-2 rounded-2">
                                    <i class="bi bi-hourglass-split fs-6"></i>
                                </span>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">Daftar Saham Dalam Monitoring (Status: WAIT)</h6>
                                    <small class="text-muted">Saham yang telah memiliki model AI tetapi belum memenuhi syarat entry pada lilin terakhir.</small>
                                </div>
                            </div>
                            <span class="badge bg-light text-secondary border px-2 py-1 font-monospace">
                                <?= count($screenerResults['wait_signals'] ?? []) ?> Saham
                            </span>
                        </div>

                        <?php if (!empty($screenerResults['wait_signals'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead class="table-light small text-uppercase text-secondary">
                                        <tr>
                                            <th>Saham</th>
                                            <th class="text-end">Harga Terakhir</th>
                                            <th class="text-center">Strategi AI</th>
                                            <th class="text-center">Track Record (PF / WR)</th>
                                            <th>Syarat yang Masih Ditunggu</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($screenerResults['wait_signals'] as $ws): ?>
                                            <tr>
                                                <td>
                                                    <span class="fw-bold font-monospace text-dark"><?= Html::encode($ws['symbol']) ?></span>
                                                    <small class="text-muted d-block"><?= Html::encode($ws['name']) ?></small>
                                                </td>
                                                <td class="text-end font-monospace fw-bold">
                                                    $<?= number_format((float)$ws['current_price'], 2) ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-secondary border px-2 py-1">
                                                        <?= Html::encode($ws['strategy_label']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center font-monospace">
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 me-1">
                                                        PF <?= number_format((float)($ws['profit_factor'] ?? 1.0), 2) ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        WR <?= $ws['oos_win_rate'] !== null ? number_format((float)$ws['oos_win_rate'], 1) . '%' : '-' ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="text-secondary small d-block mb-1">
                                                        <i class="bi bi-clock me-1 text-warning"></i>
                                                        <?= Html::encode($ws['diagnostics']['summary'] ?? 'Menunggu kondisi terbentuk') ?>
                                                    </span>
                                                    <?php if (!empty($ws['diagnostics']['trigger_forecast'])): ?>
                                                        <small class="text-muted fst-italic" style="font-size: 0.75rem;">
                                                            💡 <?= Html::encode($ws['diagnostics']['trigger_forecast']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <a href="<?= Url::to(['/stock-learning/detail', 'symbol' => $ws['symbol'], 'run_id' => $ws['run_id']]) ?>" class="btn btn-sm btn-outline-secondary px-2 py-1 shadow-xs" title="Lihat Detail & Checklist Parameter">
                                                        <i class="bi bi-eye"></i> Detail
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panduan Otomatisasi Cron CyberPanel -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="card border border-info border-opacity-25 rounded-4 p-3 p-md-4" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-gear-fill text-primary fs-5"></i>
                                <h6 class="fw-bold mb-0 text-dark">Panduan Otomatisasi Screening Setiap Pukul 05:00 - 05:30 WIB (CyberPanel)</h6>
                            </div>
                            <p class="text-muted small mb-3">
                                Bursa saham AS (Wall Street) tutup pukul 16:00 EST = <strong>04:00 WIB (Musim Panas/EDT) atau 05:00 WIB (Musim Dingin/EST)</strong>.
                                Untuk memastikan data lilin harian sudah final dari Yahoo Finance dan langsung discan sebelum Anda bangun pagi:
                            </p>
                            <div class="bg-dark text-white font-monospace p-3 rounded-3 small mb-2 overflow-auto">
                                <div class="text-warning mb-1"># 1. Update candle harian seluruh saham setiap jam 05:00 WIB (Selasa - Sabtu):</div>
                                <div class="text-light mb-3">0 5 * * 2-6 /usr/local/bin/php /home/stock.pemain12.com/public_html/yii data/refresh-all > /dev/null 2>&1</div>
                                <div class="text-warning mb-1"># 2. Jalankan AI Stock Learning Screener setiap jam 05:30 WIB (Selasa - Sabtu):</div>
                                <div class="text-success">30 5 * * 2-6 /usr/local/bin/php /home/stock.pemain12.com/public_html/yii learning/screen --force=1 > /dev/null 2>&1</div>
                            </div>
                            <small class="text-secondary">
                                *Catatan: Mengapa Selasa s/d Sabtu? Karena hari perdagangan bursa AS (Senin-Jumat EST) berakhir pada hari Selasa s/d Sabtu subuh waktu Indonesia Barat (WIB).
                            </small>
                        </div>
                    </div>
                </div>
            <?php elseif ($viewMode === 'grouped'): ?>
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
                                    <th>
                                        <a href="<?= $makeSortUrl('symbol') ?>" class="text-decoration-none text-secondary d-inline-flex align-items-center gap-1">
                                            Saham
                                            <?php if ($sortBy === 'symbol'): ?>
                                                <i class="bi bi-arrow-down-short text-primary fs-6"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="text-center">Total Latihan</th>
                                    <th>Model Terpilih</th>
                                    <th>Timeframe</th>
                                    <th class="text-center">
                                        <a href="<?= $makeSortUrl('win_rate') ?>" class="text-decoration-none <?= $sortBy === 'win_rate' ? 'text-primary fw-bold' : 'text-secondary' ?> d-inline-flex align-items-center gap-1">
                                            Best OOS Win-Rate
                                            <?php if ($sortBy === 'win_rate'): ?>
                                                <i class="bi bi-arrow-down-short text-primary fs-6"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="text-center">In-Sample WR</th>
                                    <th class="text-center bg-success bg-opacity-10">
                                        <a href="<?= $makeSortUrl('profit_factor') ?>" class="text-decoration-none <?= $sortBy === 'profit_factor' ? 'text-success fw-bold' : 'text-secondary' ?> d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-trophy-fill text-warning me-1"></i>Profit Factor
                                            <?php if ($sortBy === 'profit_factor'): ?>
                                                <i class="bi bi-arrow-down-short text-success fs-6"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="text-center">Max Drawdown</th>
                                    <th>
                                        <a href="<?= $makeSortUrl('latest') ?>" class="text-decoration-none <?= $sortBy === 'latest' ? 'text-warning fw-bold' : 'text-secondary' ?> d-inline-flex align-items-center gap-1">
                                            Latihan Terakhir
                                            <?php if ($sortBy === 'latest'): ?>
                                                <i class="bi bi-arrow-down-short text-warning fs-6"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedData as $row): ?>
                                    <?php
                                    $best = $row['best_run'];
                                    $oosWr = (float) ($best->oos_win_rate ?? 0);
                                    $oosBadgeClass = ($oosWr >= 65.0) ? 'text-success fw-bold' : (($oosWr >= 50.0) ? 'text-primary' : 'text-danger');
                                    $pf = (float) ($best->profit_factor ?? 0);
                                    $isRecent = (time() - $row['latest_created_at']) < 86400;
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
                                        <td class="text-center font-monospace">
                                            <?php if ($best->oos_win_rate !== null): ?>
                                                <?php $bOosVal = (float)$best->oos_win_rate; ?>
                                                <span class="fs-6 fw-bold <?= $bOosVal >= 60.0 ? 'text-success' : ($bOosVal >= 40.0 ? 'text-primary' : 'text-danger') ?>">
                                                    <?= number_format($bOosVal, 1) ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border" style="font-size:0.68rem;" title="Tidak ada sinyal entry pada periode validasi Out-of-Sample">0 Trade (OOS)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center font-monospace text-muted small">
                                            <?= $best->is_win_rate !== null ? number_format((float) $best->is_win_rate, 1) . '%' : '-' ?>
                                        </td>
                                        <td class="text-center font-monospace">
                                            <?php if ($pf >= 1.50): ?>
                                                <span class="badge bg-success text-white px-2 py-1 shadow-xs fw-bold font-monospace">
                                                    <i class="bi bi-trophy-fill text-warning me-1"></i><?= number_format($pf, 2) ?>
                                                </span>
                                            <?php elseif ($pf >= 1.10): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 fw-bold font-monospace">
                                                    <?= number_format($pf, 2) ?>
                                                </span>
                                            <?php elseif ($pf >= 1.00): ?>
                                                <span class="text-secondary fw-semibold font-monospace small">
                                                    <?= number_format($pf, 2) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 font-monospace">
                                                    <?= number_format($pf, 2) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center font-monospace text-danger small">
                                            <?= ($best->max_drawdown !== null && (float)$best->max_drawdown > 0) ? '-' . number_format((float) $best->max_drawdown, 1) . '%' : '0.0%' ?>
                                        </td>
                                        <td>
                                            <div class="small font-monospace text-dark">
                                                <?= date('d M Y H:i', $row['latest_created_at']) ?>
                                            </div>
                                            <?php if ($isRecent): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.65rem;">
                                                    <i class="bi bi-lightning-fill me-1"></i>Hari ini
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= Url::to(['/stock-learning/detail', 'symbol' => $row['symbol'], 'run_id' => $row['best_run']->id]) ?>" class="btn btn-sm btn-primary shadow-xs fw-semibold">
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
                                    <th>
                                        <a href="<?= $makeSortUrl('latest') ?>" class="text-decoration-none <?= $sortBy === 'latest' ? 'text-warning fw-bold' : 'text-secondary' ?> d-inline-flex align-items-center gap-1">
                                            # ID / Waktu
                                            <?php if ($sortBy === 'latest'): ?>
                                                <i class="bi bi-arrow-down-short text-warning fs-6"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?= $makeSortUrl('symbol') ?>" class="text-decoration-none text-secondary d-inline-flex align-items-center gap-1">
                                            Saham
                                            <?php if ($sortBy === 'symbol'): ?>
                                                <i class="bi bi-arrow-down-short text-primary fs-6"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Strategi Basis</th>
                                    <th>Timeframe</th>
                                    <th class="text-center">
                                        <a href="<?= $makeSortUrl('win_rate') ?>" class="text-decoration-none <?= $sortBy === 'win_rate' ? 'text-primary fw-bold' : 'text-secondary' ?> d-inline-flex align-items-center gap-1">
                                            OOS Win-Rate
                                            <?php if ($sortBy === 'win_rate'): ?>
                                                <i class="bi bi-arrow-down-short text-primary fs-6"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="text-center">In-Sample WR</th>
                                    <th class="text-center bg-success bg-opacity-10">
                                        <a href="<?= $makeSortUrl('profit_factor') ?>" class="text-decoration-none <?= $sortBy === 'profit_factor' ? 'text-success fw-bold' : 'text-secondary' ?> d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-trophy-fill text-warning me-1"></i>Profit Factor
                                            <?php if ($sortBy === 'profit_factor'): ?>
                                                <i class="bi bi-arrow-down-short text-success fs-6"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
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
                                    $rPf = (float) ($r->profit_factor ?? 0);
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="text-muted small font-monospace d-block">#<?= $r->id ?></span>
                                            <small class="text-muted font-monospace" style="font-size:0.75rem;">
                                                <?= date('d M Y H:i', $r->created_at) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark font-monospace fs-6">
                                                <?= Html::encode($r->symbol) ?>
                                            </div>
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
                                        <td class="text-center font-monospace">
                                            <?php if ($r->oos_win_rate !== null): ?>
                                                <?php $rOosVal = (float)$r->oos_win_rate; ?>
                                                <span class="fs-6 fw-bold <?= $rOosVal >= 60.0 ? 'text-success' : ($rOosVal >= 40.0 ? 'text-primary' : 'text-danger') ?>">
                                                    <?= number_format($rOosVal, 1) ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border" style="font-size:0.68rem;" title="Tidak ada sinyal entry pada periode validasi Out-of-Sample">0 Trade (OOS)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center font-monospace text-muted small">
                                            <?= $r->is_win_rate !== null ? number_format((float) $r->is_win_rate, 1) . '%' : '-' ?>
                                        </td>
                                        <td class="text-center font-monospace">
                                            <?php if ($rPf >= 1.50): ?>
                                                <span class="badge bg-success text-white px-2 py-1 shadow-xs fw-bold font-monospace">
                                                    <i class="bi bi-trophy-fill text-warning me-1"></i><?= number_format($rPf, 2) ?>
                                                </span>
                                            <?php elseif ($rPf >= 1.10): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 fw-bold font-monospace">
                                                    <?= number_format($rPf, 2) ?>
                                                </span>
                                            <?php elseif ($rPf >= 1.00): ?>
                                                <span class="text-secondary fw-semibold font-monospace small">
                                                    <?= number_format($rPf, 2) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 font-monospace">
                                                    <?= number_format($rPf, 2) ?>
                                                </span>
                                            <?php endif; ?>
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
