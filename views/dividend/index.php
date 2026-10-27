<?php

declare(strict_types=1);

use app\models\StockDividendSummary;
use yii\bootstrap5\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var string $tab */
/** @var array $filters */
/** @var StockDividendSummary[] $screenerResults */
/** @var StockDividendSummary[] $calendarResults */
/** @var string[] $sectors */
/** @var array $stats */
/** @var float $calcCapital */
/** @var string $calcSymbol */
/** @var array $calcResult */
/** @var array $dividendStocksList */

$this->title = 'Dividend Hunter & Passive Income';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="dividend-hunter-index">
    <!-- Header Hero Banner -->
    <div class="card border-0 shadow-sm mb-4 text-white overflow-hidden" style="background: linear-gradient(135deg, #064e3b 0%, #047857 50%, #0f766e 100%); border-radius: 16px;">
        <div class="card-body p-4 p-lg-5 position-relative">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-white bg-opacity-15 text-white mb-3 shadow-sm" style="font-size: 0.82rem; backdrop-filter: blur(8px);">
                        <i class="bi bi-gem text-warning"></i>
                        <span>Smart Passive Income &amp; Dividend Screener</span>
                        <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.65rem;">PRO</span>
                    </div>
                    <h1 class="display-6 fw-bold mb-2 text-white">
                        💎 Dividend Hunter US Market
                    </h1>
                    <p class="lead mb-0 text-white-50" style="font-size: 1.05rem; max-width: 650px;">
                        Temukan saham dividen yield tinggi, pantau kalender Ex-Dividend untuk strategi <em>Dividend Capture</em>, dan simulasikan pertumbuhan modal jangka panjang dengan <strong>DRIP (Dividend Reinvestment Plan)</strong>.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <div class="d-inline-flex flex-column align-items-lg-end gap-2">
                        <button type="button" class="btn btn-warning text-dark fw-bold px-4 py-2 shadow-sm rounded-pill" id="btn-sync-all-div">
                            <i class="bi bi-arrow-repeat me-1" id="icon-sync-all"></i> Update Data Dividen
                        </button>
                        <span class="small text-white-50 font-monospace" style="font-size:0.75rem;">
                            Data real-time via Yahoo Finance
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4 Summary Metric Cards -->
    <div class="row g-3 mb-4">
        <!-- Metric 1: Total Saham Berdividen -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-4" style="background: #ffffff; border-left: 5px solid #10b981 !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.72rem; letter-spacing:0.5px;">Saham Berdividen</div>
                        <div class="h3 fw-bold text-dark mb-0 mt-1"><?= (int) ($stats['total_dividend_stocks'] ?? 0) ?> <span class="text-muted fw-normal" style="font-size:0.85rem;">simbol</span></div>
                        <div class="text-success small mt-1" style="font-size:0.78rem;">
                            <i class="bi bi-check2-circle me-1"></i>Aktif di database
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success" style="width: 52px; height: 52px; font-size: 1.5rem;">
                        <i class="bi bi-bank2"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 2: Rata-Rata Yield -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-4" style="background: #ffffff; border-left: 5px solid #059669 !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.72rem; letter-spacing:0.5px;">Rata-Rata Yield</div>
                        <div class="h3 fw-bold text-dark mb-0 mt-1"><?= number_format((float) ($stats['avg_yield'] ?? 0), 2) ?>%</div>
                        <div class="text-muted small mt-1" style="font-size:0.78rem;">
                            <i class="bi bi-graph-up-arrow text-success me-1"></i>Trailing 12-Month
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-emerald bg-opacity-10 text-success" style="width: 52px; height: 52px; font-size: 1.5rem; background-color: #ecfdf5; color: #047857;">
                        <i class="bi bi-percent"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 3: Saham Bulanan (Monthly Payers) -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-4" style="background: #ffffff; border-left: 5px solid #0284c7 !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.72rem; letter-spacing:0.5px;">Pembagi Bulanan</div>
                        <div class="h3 fw-bold text-dark mb-0 mt-1"><?= (int) ($stats['monthly_payers_count'] ?? 0) ?> <span class="text-muted fw-normal" style="font-size:0.85rem;">saham</span></div>
                        <div class="text-info small mt-1" style="font-size:0.78rem;">
                            <i class="bi bi-calendar3 me-1"></i>Gajian tiap bulan (e.g. O)
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info" style="width: 52px; height: 52px; font-size: 1.5rem;">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 4: Dividend Aristocrats -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-4" style="background: #ffffff; border-left: 5px solid #d97706 !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.72rem; letter-spacing:0.5px;">Dividend Aristocrats</div>
                        <div class="h3 fw-bold text-dark mb-0 mt-1"><?= (int) ($stats['aristocrats_count'] ?? 0) ?> <span class="text-muted fw-normal" style="font-size:0.85rem;">emiten</span></div>
                        <div class="text-warning small mt-1 text-truncate" style="font-size:0.78rem;">
                            <i class="bi bi-award me-1"></i>Track record dividen konsisten
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning" style="width: 52px; height: 52px; font-size: 1.5rem;">
                        <i class="bi bi-trophy"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs Navigation (Pills Modern) -->
    <ul class="nav nav-pills p-2 bg-white rounded-4 shadow-sm mb-4 border" id="dividendTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab === 'screener' ? 'active' : '' ?> fw-bold px-4 py-2 rounded-pill" id="screener-tab" data-bs-toggle="pill" data-bs-target="#tab-screener" type="button" role="tab" aria-selected="<?= $tab === 'screener' ? 'true' : 'false' ?>">
                <i class="bi bi-table me-2"></i>Dividend Screener &amp; Ranking
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab === 'calendar' ? 'active' : '' ?> fw-bold px-4 py-2 rounded-pill" id="calendar-tab" data-bs-toggle="pill" data-bs-target="#tab-calendar" type="button" role="tab" aria-selected="<?= $tab === 'calendar' ? 'true' : 'false' ?>">
                <i class="bi bi-calendar-event me-2"></i>Kalender Ex-Dividend &amp; Capture
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $tab === 'calculator' ? 'active' : '' ?> fw-bold px-4 py-2 rounded-pill" id="calculator-tab" data-bs-toggle="pill" data-bs-target="#tab-calculator" type="button" role="tab" aria-selected="<?= $tab === 'calculator' ? 'true' : 'false' ?>">
                <i class="bi bi-calculator me-2"></i>Kalkulator Passive Income &amp; DRIP
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="dividendTabsContent">

        <!-- ============================================================== -->
        <!-- TAB 1: DIVIDEND SCREENER & RANKING                            -->
        <!-- ============================================================== -->
        <div class="tab-pane fade <?= $tab === 'screener' ? 'show active' : '' ?>" id="tab-screener" role="tabpanel" aria-labelledby="screener-tab">
            <!-- Filter Bar Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3 p-lg-4">
                    <form method="get" action="<?= Url::to(['/dividend/index']) ?>" class="row g-3 align-items-end" id="screener-filter-form">
                        <input type="hidden" name="tab" value="screener">
                        
                        <!-- Search Box -->
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-dark mb-1">Cari Saham / Simbol:</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control form-control-sm border-start-0" placeholder="e.g. JNJ, O, KO, Apple..." value="<?= Html::encode($filters['search'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Min Yield Filter -->
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label small fw-bold text-dark mb-1">Min. Dividend Yield:</label>
                            <select name="min_yield" class="form-select form-select-sm">
                                <option value="" <?= ($filters['min_yield'] === '') ? 'selected' : '' ?>>Semua Yield (>0%)</option>
                                <option value="2" <?= ($filters['min_yield'] === '2') ? 'selected' : '' ?>>&gt; 2.0% (Standar)</option>
                                <option value="3" <?= ($filters['min_yield'] === '3') ? 'selected' : '' ?>>&gt; 3.0% (Menengah)</option>
                                <option value="5" <?= ($filters['min_yield'] === '5') ? 'selected' : '' ?>>&gt; 5.0% (Tinggi)</option>
                                <option value="7" <?= ($filters['min_yield'] === '7') ? 'selected' : '' ?>>&gt; 7.0% (Super High)</option>
                            </select>
                        </div>

                        <!-- Payout Frequency Filter -->
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label small fw-bold text-dark mb-1">Frekuensi Dividen:</label>
                            <select name="frequency" class="form-select form-select-sm">
                                <option value="all" <?= ($filters['frequency'] === 'all') ? 'selected' : '' ?>>Semua Frekuensi</option>
                                <option value="monthly" <?= ($filters['frequency'] === 'monthly') ? 'selected' : '' ?>>Bulanan (Monthly ~12x)</option>
                                <option value="quarterly" <?= ($filters['frequency'] === 'quarterly') ? 'selected' : '' ?>>Kuartalan (Quarterly ~4x)</option>
                                <option value="semi-annual" <?= ($filters['frequency'] === 'semi-annual') ? 'selected' : '' ?>>Semesteran (2x)</option>
                                <option value="annual" <?= ($filters['frequency'] === 'annual') ? 'selected' : '' ?>>Tahunan (1x)</option>
                            </select>
                        </div>

                        <!-- Safety Status Filter -->
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label small fw-bold text-dark mb-1">Kategori Keamanan:</label>
                            <select name="safety" class="form-select form-select-sm">
                                <option value="all" <?= ($filters['safety'] === 'all') ? 'selected' : '' ?>>Semua Kategori</option>
                                <option value="Aristocrat" <?= ($filters['safety'] === 'Aristocrat') ? 'selected' : '' ?>>👑 Dividend Aristocrat</option>
                                <option value="Safe" <?= ($filters['safety'] === 'Safe') ? 'selected' : '' ?>>🛡️ Safe &amp; Steady</option>
                                <option value="Moderate" <?= ($filters['safety'] === 'Moderate') ? 'selected' : '' ?>>⚖️ Moderate</option>
                                <option value="High Yield / Warning" <?= ($filters['safety'] === 'High Yield / Warning') ? 'selected' : '' ?>>⚠️ High Yield / Risk</option>
                            </select>
                        </div>

                        <!-- Sorting -->
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label small fw-bold text-dark mb-1">Urutkan Berdasarkan:</label>
                            <select name="sort" class="form-select form-select-sm">
                                <option value="yield_desc" <?= ($filters['sort'] === 'yield_desc') ? 'selected' : '' ?>>Yield Tertinggi</option>
                                <option value="payout_desc" <?= ($filters['sort'] === 'payout_desc') ? 'selected' : '' ?>>Nominal Payout Terbesar</option>
                                <option value="price_asc" <?= ($filters['sort'] === 'price_asc') ? 'selected' : '' ?>>Harga Termurah</option>
                                <option value="next_date_asc" <?= ($filters['sort'] === 'next_date_asc') ? 'selected' : '' ?>>Ex-Date Terdekat</option>
                                <option value="symbol_asc" <?= ($filters['sort'] === 'symbol_asc') ? 'selected' : '' ?>>Simbol (A-Z)</option>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="col-md-1 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold" title="Terapkan Filter">
                                <i class="bi bi-filter"></i> Filter
                            </button>
                            <a href="<?= Url::to(['/dividend/index', 'tab' => 'screener']) ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Screener Data Table Card -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success fw-bold px-2 py-1">
                            <?= count($screenerResults) ?> Emiten Ditemukan
                        </span>
                        <span class="text-muted small">Diurutkan berdasarkan pilihan filter</span>
                    </div>
                    <div class="small text-muted">
                        <span class="badge bg-light text-secondary border me-1"><i class="bi bi-info-circle me-1"></i>Klik tombol Refresh untuk update individual saham</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="table-screener">
                        <thead class="table-light text-muted small fw-semibold" style="font-size: 0.78rem;">
                            <tr>
                                <th class="ps-3 py-3" style="width: 140px;">SIMBOL &amp; NAMA</th>
                                <th class="text-end py-3">HARGA</th>
                                <th class="text-end py-3">DIVIDEND YIELD</th>
                                <th class="text-end py-3">TTM PAYOUT (1Y)</th>
                                <th class="text-center py-3">FREKUENSI</th>
                                <th class="text-center py-3">LAST EX-DATE</th>
                                <th class="text-center py-3">ESTIMASI NEXT EX-DATE</th>
                                <th class="text-center py-3">STATUS &amp; KEAMANAN</th>
                                <th class="text-center pe-3 py-3" style="width: 90px;">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="font-size: 0.88rem;">
                            <?php if (empty($screenerResults)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-2 text-muted d-block mb-2"></i>
                                        Belum ada data dividen yang cocok dengan kriteria filter.<br>
                                        <small class="text-secondary">Silakan klik tombol <strong>"Update Data Dividen"</strong> di atas untuk menyinkronkan data dari Yahoo Finance.</small>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($screenerResults as $item): ?>
                                    <?php
                                    $yield = (float) $item->dividend_yield;
                                    $yieldBadgeClass = match (true) {
                                        $yield >= 6.0 => 'bg-danger bg-opacity-10 text-danger border-danger',
                                        $yield >= 3.5 => 'bg-success bg-opacity-10 text-success border-success',
                                        $yield >= 1.5 => 'bg-primary bg-opacity-10 text-primary border-primary',
                                        default => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                                    };

                                    $safetyBadge = match ($item->safety_score) {
                                        StockDividendSummary::SAFETY_ARISTOCRAT => '<span class="badge bg-warning bg-opacity-15 text-dark border border-warning fw-semibold"><i class="bi bi-trophy-fill text-warning me-1"></i>Aristocrat</span>',
                                        StockDividendSummary::SAFETY_SAFE => '<span class="badge bg-success bg-opacity-15 text-success border border-success fw-semibold"><i class="bi bi-shield-check me-1"></i>Safe &amp; Steady</span>',
                                        StockDividendSummary::SAFETY_WARNING => '<span class="badge bg-danger bg-opacity-15 text-danger border border-danger fw-semibold" title="Yield sangat tinggi, waspadai risiko pemangkasan dividen!"><i class="bi bi-exclamation-triangle-fill me-1"></i>Yield Warning</span>',
                                        default => '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fw-semibold">Moderate</span>',
                                    };

                                    $freqLabel = match ($item->payout_frequency) {
                                        StockDividendSummary::FREQ_MONTHLY => '<span class="badge bg-primary rounded-pill px-2" style="font-size:0.72rem;">Bulanan (12x)</span>',
                                        StockDividendSummary::FREQ_QUARTERLY => '<span class="badge bg-light text-dark border rounded-pill px-2" style="font-size:0.72rem;">Kuartalan (4x)</span>',
                                        StockDividendSummary::FREQ_SEMI_ANNUAL => '<span class="badge bg-light text-dark border rounded-pill px-2" style="font-size:0.72rem;">Semester (2x)</span>',
                                        StockDividendSummary::FREQ_ANNUAL => '<span class="badge bg-light text-dark border rounded-pill px-2" style="font-size:0.72rem;">Tahunan (1x)</span>',
                                        default => '<span class="badge bg-light text-dark border rounded-pill px-2" style="font-size:0.72rem;">N/A</span>',
                                    };
                                    ?>
                                    <tr id="row-<?= Html::encode($item->symbol) ?>">
                                        <!-- Simbol & Nama -->
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="symbol-badge bg-light border fw-bold px-2 py-1 rounded font-monospace text-dark" style="font-size:0.85rem;">
                                                    <?= Html::encode($item->symbol) ?>
                                                </div>
                                                <div class="overflow-hidden">
                                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 170px;" title="<?= Html::encode($item->company_name) ?>">
                                                        <?= Html::encode($item->company_name ?: $item->symbol) ?>
                                                    </div>
                                                    <div class="text-muted small" style="font-size: 0.72rem;">
                                                        <?= Html::encode($item->sector ?: 'US Market') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Harga Saham -->
                                        <td class="text-end fw-bold text-dark font-monospace">
                                            $<?= number_format((float) $item->current_price, 2) ?>
                                        </td>

                                        <!-- Dividend Yield -->
                                        <td class="text-end">
                                            <span class="badge <?= $yieldBadgeClass ?> border font-monospace px-2 py-1 fs-6">
                                                <?= number_format($yield, 2) ?>%
                                            </span>
                                        </td>

                                        <!-- TTM Payout -->
                                        <td class="text-end font-monospace">
                                            <div class="fw-bold text-dark">$<?= number_format((float) $item->ttm_dividend, 2) ?> <span class="text-muted small" style="font-size:0.72rem;">/lembar</span></div>
                                            <?php if ($item->last_amount): ?>
                                                <div class="text-muted small" style="font-size:0.70rem;">Terakhir: $<?= number_format((float) $item->last_amount, 2) ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Frekuensi -->
                                        <td class="text-center">
                                            <?= $freqLabel ?>
                                        </td>

                                        <!-- Last Ex-Date -->
                                        <td class="text-center font-monospace text-secondary small">
                                            <?= $item->last_ex_date ? Html::encode($item->last_ex_date) : '—' ?>
                                        </td>

                                        <!-- Estimasi Next Ex-Date -->
                                        <td class="text-center font-monospace">
                                            <?php if ($item->estimated_next_ex_date): ?>
                                                <?php
                                                $daysDiff = (int) round((strtotime($item->estimated_next_ex_date) - time()) / 86400);
                                                $badgeDateClass = ($daysDiff >= 0 && $daysDiff <= 30) ? 'bg-success text-white' : 'bg-light text-dark border';
                                                ?>
                                                <span class="badge <?= $badgeDateClass ?> px-2 py-1" style="font-size:0.78rem;">
                                                    <i class="bi bi-calendar3 me-1"></i><?= Html::encode($item->estimated_next_ex_date) ?>
                                                </span>
                                                <?php if ($daysDiff >= 0): ?>
                                                    <div class="text-success small fw-semibold mt-1" style="font-size:0.68rem;">~<?= $daysDiff ?> hari lagi</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Safety Score -->
                                        <td class="text-center">
                                            <?= $safetyBadge ?>
                                        </td>

                                        <!-- Aksi -->
                                        <td class="text-center pe-3">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary btn-sync-single" data-symbol="<?= Html::encode($item->symbol) ?>" title="Sync data dividen <?= Html::encode($item->symbol) ?>">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-success btn-load-calc" data-symbol="<?= Html::encode($item->symbol) ?>" data-yield="<?= $yield ?>" data-price="<?= (float) $item->current_price ?>" title="Simulasikan di Kalkulator DRIP">
                                                    <i class="bi bi-calculator"></i>
                                                </button>
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

        <!-- ============================================================== -->
        <!-- TAB 2: KALENDER EX-DIVIDEND & CAPTURE STRATEGY                -->
        <!-- ============================================================== -->
        <div class="tab-pane fade <?= $tab === 'calendar' ? 'show active' : '' ?>" id="tab-calendar" role="tabpanel" aria-labelledby="calendar-tab">
            <!-- Alert Strategi Dividend Capture -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-primary border-4" style="background: #f8fafc;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="fs-2 text-primary"><i class="bi bi-lightbulb-fill"></i></div>
                        <div>
                            <h6 class="fw-bold text-dark mb-1">Panduan Strategi Dividend Capture &amp; Ex-Date</h6>
                            <p class="text-secondary small mb-2">
                                Untuk berhak mendapatkan dividen saham US, Anda harus membeli saham <strong>sebelum Ex-Dividend Date (pada Cum-Date)</strong> dan menahannya sampai market buka di tanggal Ex-Date.
                            </p>
                            <div class="d-flex gap-3 flex-wrap small text-muted">
                                <span><strong class="text-dark">1. Cum-Date:</strong> Hari terakhir beli saham agar berhak atas dividen.</span>
                                <span><strong class="text-dark">2. Ex-Date:</strong> Tanggal pemisahan hak dividen (pembelian pada atau setelah tanggal ini tidak dapat dividen periode tersebut).</span>
                                <span><strong class="text-dark">3. Pay-Date:</strong> Tanggal dana dividen masuk ke rekening / saldo broker Anda.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar Upcoming Table -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-calendar3 text-primary me-2"></i>Jadwal Ex-Dividend Terdekat (Upcoming Cycle)
                    </h6>
                    <span class="badge bg-light text-secondary border small">Estimasi berdasarkan siklus historis pembayaran</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small fw-semibold" style="font-size: 0.78rem;">
                            <tr>
                                <th class="ps-3 py-3">ESTIMASI EX-DATE</th>
                                <th class="py-3">COUNTDOWN</th>
                                <th class="py-3">SIMBOL &amp; NAMA EMITEN</th>
                                <th class="text-end py-3">HARGA SAHAM</th>
                                <th class="text-end py-3">ESTIMASI PAYOUT</th>
                                <th class="text-end py-3">DIVIDEND YIELD</th>
                                <th class="text-center py-3">FREKUENSI</th>
                                <th class="text-center pe-3 py-3">SIMULASI MODAL $5,000</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="font-size:0.88rem;">
                            <?php if (empty($calendarResults)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-calendar-x fs-2 text-muted d-block mb-2"></i>
                                        Belum ada jadwal kalender dividen yang tercatat. Silakan jalankan update data dividen terlebih dahulu.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($calendarResults as $cal): ?>
                                    <?php
                                    $exDateTs = strtotime($cal->estimated_next_ex_date);
                                    $daysRemaining = (int) round(($exDateTs - time()) / 86400);
                                    $yield = (float) $cal->dividend_yield;
                                    $price = (float) $cal->current_price;
                                    $estPerQuarter = $cal->payouts_per_year > 0 ? ((float) $cal->ttm_dividend / $cal->payouts_per_year) : 0;
                                    // Simulasi dividen per periode untuk modal $5000
                                    $simShares = $price > 0 ? floor(5000 / $price) : 0;
                                    $simDivReceive = $simShares * $estPerQuarter;
                                    ?>
                                    <tr>
                                        <!-- Estimasi Ex-Date -->
                                        <td class="ps-3 font-monospace fw-bold text-dark">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-calendar-check text-success"></i>
                                                <span><?= Html::encode($cal->estimated_next_ex_date) ?></span>
                                            </div>
                                        </td>

                                        <!-- Countdown -->
                                        <td>
                                            <?php if ($daysRemaining > 0): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success fw-bold px-2 py-1">
                                                    <?= $daysRemaining ?> hari lagi
                                                </span>
                                            <?php elseif ($daysRemaining === 0): ?>
                                                <span class="badge bg-warning text-dark fw-bold px-2 py-1">HARI INI</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1">Baru saja lewat</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Simbol & Nama -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-light text-dark border font-monospace fw-bold px-2 py-1">
                                                    <?= Html::encode($cal->symbol) ?>
                                                </span>
                                                <span class="fw-semibold text-dark text-truncate" style="max-width:200px;" title="<?= Html::encode($cal->company_name) ?>">
                                                    <?= Html::encode($cal->company_name ?: $cal->symbol) ?>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Harga -->
                                        <td class="text-end font-monospace fw-bold text-dark">
                                            $<?= number_format($price, 2) ?>
                                        </td>

                                        <!-- Estimasi Payout per Payout -->
                                        <td class="text-end font-monospace">
                                            <span class="fw-bold text-success">$<?= number_format($estPerQuarter, 2) ?></span>
                                            <span class="text-muted small">/lbr</span>
                                        </td>

                                        <!-- Dividend Yield -->
                                        <td class="text-end font-monospace">
                                            <span class="fw-bold text-dark"><?= number_format($yield, 2) ?>%</span>
                                        </td>

                                        <!-- Frekuensi -->
                                        <td class="text-center small text-muted">
                                            <?= ucfirst($cal->payout_frequency) ?>
                                        </td>

                                        <!-- Simulasi Cashflow $5000 -->
                                        <td class="text-end pe-3 font-monospace">
                                            <div class="fw-bold text-success">+$<?= number_format($simDivReceive, 2) ?></div>
                                            <div class="text-muted small" style="font-size:0.70rem;">dari <?= $simShares ?> lembar saham</div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- TAB 3: KALKULATOR PASSIVE INCOME & DRIP COMPOUND              -->
        <!-- ============================================================== -->
        <div class="tab-pane fade <?= $tab === 'calculator' ? 'show active' : '' ?>" id="tab-calculator" role="tabpanel" aria-labelledby="calculator-tab">
            <div class="row g-4">
                <!-- Kolom Kiri: Form Input & Slider Simulator -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="fw-bold text-dark mb-0">
                                <i class="bi bi-sliders text-primary me-2"></i>Parameter Investasi Dividen
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <!-- Pilihan Saham Target -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Pilih Saham Dividen Acuan:</label>
                                <select class="form-select" id="calc-stock-select">
                                    <option value="" data-yield="4.50" data-price="100.00">-- Custom Yield (Kustomisasi Bebas) --</option>
                                    <?php foreach ($dividendStocksList as $stk): ?>
                                        <option value="<?= Html::encode($stk['symbol']) ?>" 
                                            data-yield="<?= (float) $stk['dividend_yield'] ?>" 
                                            data-price="<?= (float) $stk['current_price'] ?>"
                                            <?= ($stk['symbol'] === ($calcSymbol ?? 'O')) ? 'selected' : '' ?>>
                                            <?= Html::encode($stk['symbol']) ?> - <?= Html::encode($stk['company_name']) ?> (Yield: <?= number_format((float) $stk['dividend_yield'], 2) ?>%, $<?= number_format((float) $stk['current_price'], 2) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Modal Investasi ($ USD) -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-bold text-dark mb-0">Modal Investasi Awal (USD):</label>
                                    <span class="fw-bold text-primary fs-5" id="calc-capital-display">$10,000</span>
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text bg-light fw-bold">$</span>
                                    <input type="number" class="form-control fw-bold font-monospace fs-5" id="calc-capital-input" value="10000" min="100" step="500">
                                </div>
                                <input type="range" class="form-range" id="calc-capital-slider" min="500" max="100000" step="500" value="10000">
                                <div class="d-flex justify-content-between text-muted small" style="font-size:0.72rem;">
                                    <span>$500</span>
                                    <span>$25,000</span>
                                    <span>$50,000</span>
                                    <span>$100,000</span>
                                </div>
                            </div>

                            <!-- Dividend Yield Slider -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-bold text-dark mb-0">Estimasi Dividend Yield (%):</label>
                                    <span class="fw-bold text-success fs-5" id="calc-yield-display">5.67%</span>
                                </div>
                                <input type="range" class="form-range" id="calc-yield-slider" min="0.5" max="15.0" step="0.1" value="5.67">
                                <div class="d-flex justify-content-between text-muted small" style="font-size:0.72rem;">
                                    <span>0.5% (Tech)</span>
                                    <span>4.0% (Dividend King)</span>
                                    <span>8.0% (REIT/Energy)</span>
                                    <span>15.0% (High Risk)</span>
                                </div>
                            </div>

                            <button type="button" class="btn btn-primary w-100 fw-bold py-2 shadow-sm rounded-pill" id="btn-recalculate">
                                <i class="bi bi-lightning-charge-fill me-1"></i> Hitung Proyeksi Passive Income
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Hasil Perhitungan & DRIP Compound Growth -->
                <div class="col-lg-7">
                    <!-- 3 Metric Boxes: Bulanan, Kuartalan, Tahunan -->
                    <div class="row g-3 mb-4">
                        <!-- Bulanan -->
                        <div class="col-sm-4">
                            <div class="card border-0 shadow-sm rounded-4 text-center p-3" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 1px solid #a7f3d0 !important;">
                                <div class="text-success small fw-semibold text-uppercase" style="font-size:0.72rem;">Dividen Bulanan</div>
                                <div class="h3 fw-bold text-dark my-1 font-monospace" id="res-monthly-income">
                                    $<?= number_format((float) ($calcResult['monthly_dividend'] ?? 0), 2) ?>
                                </div>
                                <div class="text-muted small" style="font-size:0.70rem;">rata-rata per bulan</div>
                            </div>
                        </div>

                        <!-- Kuartalan -->
                        <div class="col-sm-4">
                            <div class="card border-0 shadow-sm rounded-4 text-center p-3" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd !important;">
                                <div class="text-primary small fw-semibold text-uppercase" style="font-size:0.72rem;">Dividen Kuartalan</div>
                                <div class="h3 fw-bold text-dark my-1 font-monospace" id="res-quarterly-income">
                                    $<?= number_format((float) ($calcResult['quarterly_dividend'] ?? 0), 2) ?>
                                </div>
                                <div class="text-muted small" style="font-size:0.70rem;">setiap 3 bulan</div>
                            </div>
                        </div>

                        <!-- Tahunan -->
                        <div class="col-sm-4">
                            <div class="card border-0 shadow-sm rounded-4 text-center p-3" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 1px solid #fcd34d !important;">
                                <div class="text-warning-emphasis small fw-semibold text-uppercase" style="font-size:0.72rem;">Dividen Tahunan</div>
                                <div class="h3 fw-bold text-dark my-1 font-monospace" id="res-annual-income">
                                    $<?= number_format((float) ($calcResult['annual_dividend'] ?? 0), 2) ?>
                                </div>
                                <div class="text-muted small" style="font-size:0.70rem;">per tahun (12 bulan)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Proyeksi DRIP Compound Growth 10 Tahun -->
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">
                                    <i class="bi bi-graph-up-arrow text-success me-2"></i>Proyeksi Kekuatan DRIP (Reinvestasi Dividen)
                                </h6>
                                <div class="small text-muted" style="font-size:0.75rem;">Perbandingan nilai portofolio jika dividen diinvestasikan kembali vs dicairkan tunai</div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" id="table-drip-projection" style="font-size: 0.85rem;">
                                <thead class="table-light text-muted small fw-semibold">
                                    <tr>
                                        <th class="ps-3 py-2">TAHUN KE</th>
                                        <th class="text-end py-2">DIVIDEN TAHUNAN</th>
                                        <th class="text-end py-2">TANPA DRIP (CASH OUT)</th>
                                        <th class="text-end py-2 text-success">DENGAN DRIP (REINVEST)</th>
                                        <th class="text-end pe-3 py-2 text-warning">KEUNTUNGAN BONUS DRIP</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y font-monospace" id="drip-projection-body">
                                    <?php if (!empty($calcResult['timeline'])): ?>
                                        <?php foreach ($calcResult['timeline'] as $item): ?>
                                            <tr>
                                                <td class="ps-3 fw-bold text-dark">Tahun <?= $item['year'] ?></td>
                                                <td class="text-end">$<?= number_format((float) $item['drip_annual_income'], 2) ?></td>
                                                <td class="text-end text-muted">$<?= number_format((float) ($item['no_drip_portfolio_value'] + $item['no_drip_cumulative_income']), 2) ?></td>
                                                <td class="text-end fw-bold text-success">$<?= number_format((float) $item['drip_portfolio_value'], 2) ?></td>
                                                <td class="text-end pe-3 fw-bold text-warning-emphasis">+ $<?= number_format((float) $item['drip_bonus_gain'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Live Sync All Progress -->
<div class="modal fade" id="modalSyncDividendAll" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                    <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                    <span>Sinkronisasi Data Dividen Pasar</span>
                </h5>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Batas Jumlah Saham yang Di-sync:</label>
                    <select class="form-select form-select-sm" id="modal-sync-limit">
                        <option value="25" selected>⚡ 25 Saham Pertama (Rekomendasi Cepat ~3-5 dtk)</option>
                        <option value="50">📈 50 Saham Pertama (~10 dtk)</option>
                        <option value="100">🚀 100 Saham Pertama (~20 dtk)</option>
                        <option value="0">🌐 Seluruh Saham Aktif (~45 dtk)</option>
                    </select>
                </div>
                <div class="p-3 bg-light rounded-3 font-monospace small" id="sync-live-log" style="max-height: 180px; overflow-y: auto; font-size: 0.78rem;">
                    Klik tombol "Mulai Sync Sekarang" untuk mengambil data dividen terbaru dari Yahoo Finance...
                </div>
            </div>
            <div class="modal-footer border-top py-2 px-3 d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" id="btn-close-sync-modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm fw-bold px-4" id="btn-confirm-start-sync">
                    <i class="bi bi-play-fill me-1"></i> Mulai Sync Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- JAVASCRIPT LOGIC INTERAKTIF                                     -->
<!-- ============================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Sinkronisasi Tunggal per Saham (Tabel Screener)
    document.querySelectorAll('.btn-sync-single').forEach(btn => {
        btn.addEventListener('click', function () {
            const symbol = this.getAttribute('data-symbol');
            const icon = this.querySelector('i');
            const originalClass = icon.className;
            
            icon.className = 'spinner-border spinner-border-sm text-primary';
            this.disabled = true;

            fetch('<?= Url::to(['/dividend/sync-one']) ?>?symbol=' + encodeURIComponent(symbol))
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        icon.className = 'bi bi-check2 text-success';
                        setTimeout(() => {
                            window.location.reload();
                        }, 600);
                    } else {
                        alert('Gagal sync: ' + (data.message || 'Error tidak diketahui'));
                        icon.className = originalClass;
                        this.disabled = false;
                    }
                })
                .catch(err => {
                    alert('Error koneksi: ' + err.message);
                    icon.className = originalClass;
                    this.disabled = false;
                });
        });
    });

    // 2. Tombol Transfer Saham dari Screener ke Kalkulator
    document.querySelectorAll('.btn-load-calc').forEach(btn => {
        btn.addEventListener('click', function () {
            const sym = this.getAttribute('data-symbol');
            const yieldVal = parseFloat(this.getAttribute('data-yield')) || 5.0;
            const priceVal = parseFloat(this.getAttribute('data-price')) || 100.0;

            const tabTrigger = new bootstrap.Tab(document.getElementById('calculator-tab'));
            tabTrigger.show();

            const selectEl = document.getElementById('calc-stock-select');
            if (selectEl) {
                selectEl.value = sym;
            }
            const sliderYield = document.getElementById('calc-yield-slider');
            if (sliderYield) {
                sliderYield.value = yieldVal;
                document.getElementById('calc-yield-display').innerText = yieldVal.toFixed(2) + '%';
            }
            triggerCalculation();
        });
    });

    // 3. Modal Sync All
    const syncModalEl = document.getElementById('modalSyncDividendAll');
    const syncModal = new bootstrap.Modal(syncModalEl);
    const btnSyncAll = document.getElementById('btn-sync-all-div');
    const btnConfirmSync = document.getElementById('btn-confirm-start-sync');
    const syncLog = document.getElementById('sync-live-log');
    const btnCloseSyncModal = document.getElementById('btn-close-sync-modal');

    btnSyncAll.addEventListener('click', function () {
        syncModal.show();
    });

    btnConfirmSync.addEventListener('click', function () {
        const limit = document.getElementById('modal-sync-limit').value;
        btnConfirmSync.disabled = true;
        btnCloseSyncModal.disabled = true;
        syncLog.innerHTML = '> Menghubungkan ke server Yahoo Finance...\n> Mengambil riwayat dividen & harga pasar...';

        // Redirect via Settings CLI runner streaming URL or direct AJAX
        const targetUrl = '<?= Url::to(['/settings/run-command']) ?>?cmd=dividend/sync-all&limit=' + limit + '&stream=1';
        
        try {
            const evtSource = new EventSource(targetUrl);
            evtSource.onmessage = function (e) {
                try {
                    const d = JSON.parse(e.data);
                    if (d.type === 'output') {
                        syncLog.innerHTML += '\n' + d.line;
                        syncLog.scrollTop = syncLog.scrollHeight;
                    } else if (d.type === 'done') {
                        syncLog.innerHTML += '\n[SELESAI] ' + d.message;
                        evtSource.close();
                        btnConfirmSync.disabled = false;
                        btnCloseSyncModal.disabled = false;
                        setTimeout(() => {
                            window.location.reload();
                        }, 1200);
                    }
                } catch (err) {
                    syncLog.innerHTML += '\n' + e.data;
                }
            };
            evtSource.onerror = function () {
                syncLog.innerHTML += '\n[INFO] Proses sinkronisasi selesai atau terputus.';
                evtSource.close();
                btnConfirmSync.disabled = false;
                btnCloseSyncModal.disabled = false;
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            };
        } catch (err) {
            syncLog.innerHTML += '\n[ERROR] ' + err.message;
            btnConfirmSync.disabled = false;
            btnCloseSyncModal.disabled = false;
        }
    });

    // 4. Kalkulator DRIP Logic
    const capitalInput = document.getElementById('calc-capital-input');
    const capitalSlider = document.getElementById('calc-capital-slider');
    const capitalDisplay = document.getElementById('calc-capital-display');
    const yieldSlider = document.getElementById('calc-yield-slider');
    const yieldDisplay = document.getElementById('calc-yield-display');
    const stockSelect = document.getElementById('calc-stock-select');
    const btnRecalculate = document.getElementById('btn-recalculate');

    function formatNumber(num) {
        return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Sinkronisasi input modal & slider
    capitalInput.addEventListener('input', function () {
        const val = parseFloat(this.value) || 1000;
        capitalSlider.value = Math.min(val, 100000);
        capitalDisplay.innerText = '$' + formatNumber(val);
        triggerCalculation();
    });

    capitalSlider.addEventListener('input', function () {
        const val = parseFloat(this.value) || 10000;
        capitalInput.value = val;
        capitalDisplay.innerText = '$' + formatNumber(val);
        triggerCalculation();
    });

    yieldSlider.addEventListener('input', function () {
        const val = parseFloat(this.value) || 5.0;
        yieldDisplay.innerText = val.toFixed(2) + '%';
        triggerCalculation();
    });

    stockSelect.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const stockYield = parseFloat(opt.getAttribute('data-yield'));
        if (!isNaN(stockYield) && stockYield > 0) {
            yieldSlider.value = stockYield;
            yieldDisplay.innerText = stockYield.toFixed(2) + '%';
        }
        triggerCalculation();
    });

    btnRecalculate.addEventListener('click', triggerCalculation);

    function triggerCalculation() {
        const capital = parseFloat(capitalInput.value) || 10000;
        const yieldVal = parseFloat(yieldSlider.value) || 5.0;
        const symbolVal = stockSelect.value;

        // Hitung client-side secara instan tanpa delay
        const annualDiv = capital * (yieldVal / 100.0);
        const monthlyDiv = annualDiv / 12.0;
        const quarterlyDiv = annualDiv / 4.0;

        document.getElementById('res-monthly-income').innerText = '$' + formatNumber(monthlyDiv);
        document.getElementById('res-quarterly-income').innerText = '$' + formatNumber(quarterlyDiv);
        document.getElementById('res-annual-income').innerText = '$' + formatNumber(annualDiv);

        // Update tabel proyeksi 10 tahun
        const tbody = document.getElementById('drip-projection-body');
        if (!tbody) return;

        let html = '';
        let dripBalance = capital;
        let cumulativeNoDrip = 0;
        const appreciation = 0.04; // 4% per tahun asumsi pertumbuhan harga

        for (let y = 1; y <= 10; y++) {
            const noDripAnnual = capital * (yieldVal / 100.0);
            cumulativeNoDrip += noDripAnnual;
            const noDripPortValue = capital * Math.pow(1 + appreciation, y);

            const dripAnnual = dripBalance * (yieldVal / 100.0);
            dripBalance += dripAnnual;
            dripBalance *= (1 + appreciation);

            const bonusGain = dripBalance - (noDripPortValue + cumulativeNoDrip);

            html += `<tr>
                <td class="ps-3 fw-bold text-dark">Tahun ${y}</td>
                <td class="text-end">$${formatNumber(dripAnnual)}</td>
                <td class="text-end text-muted">$${formatNumber(noDripPortValue + cumulativeNoDrip)}</td>
                <td class="text-end fw-bold text-success">$${formatNumber(dripBalance)}</td>
                <td class="text-end pe-3 fw-bold text-warning-emphasis">+ $${formatNumber(bonusGain)}</td>
            </tr>`;
        }
        tbody.innerHTML = html;
    }
});
</script>
