<?php

/** @var yii\web\View $this */
/** @var app\models\PortfolioPosition $position */
/** @var app\models\Stock $stock */
/** @var array $multiAnalysis */
/** @var array $dcaData */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Analisis Portofolio & Averaging: ' . $stock->symbol;
$this->params['breadcrumbs'][] = ['label' => 'Portofolio', 'url' => ['/portfolio/index']];
$this->params['breadcrumbs'][] = $stock->symbol;

$fmt = Yii::$app->formatter;
$currentPrice = $position->getCurrentPrice();
$buyPrice = (float) $position->buy_price;
$shares = (float) $position->shares;
$plAmount = $position->getProfitLossAmount();
$plPct = $position->getProfitLossPct();
$isProfit = $position->isProfit();
$isLoss = $position->isLoss();
$targetPrice = $position->getTargetSellPrice();
$targetPct = (float) $position->target_profit_pct;

$signals = $multiAnalysis['signals'] ?? [];
$candlestick = $signals['candlestick'] ?? null;
$bottomPatterns = $signals['bottom_patterns'] ?? null;
$rsi = $signals['rsi'] ?? null;
$ma = $signals['moving_average'] ?? null;
$fib = $signals['fibonacci'] ?? null;
?>

<div class="portfolio-detail">
    <!-- Header Nav & Title -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <h1 class="h3 mb-0 fw-bold text-dark">
                    <?= Html::encode($stock->symbol) ?>
                </h1>
                <span class="badge bg-light text-secondary border fs-6"><?= Html::encode($stock->exchange) ?></span>
                <span class="badge bg-<?= $isProfit ? 'success' : ($isLoss ? 'danger' : 'secondary') ?> fs-6 px-3 py-1 shadow-sm">
                    <?= $isProfit ? '🟢 UNTUNG (PROFIT)' : ($isLoss ? '🔴 MINUS (FLOATING LOSS)' : '⚪ IMPAS') ?>
                </span>
                <span class="badge bg-primary fs-6 px-3 py-1 shadow-sm">
                    Target: +<?= $targetPct ?>% ($<?= number_format($targetPrice, 2) ?>)
                </span>
            </div>
            <p class="text-muted small mb-0">
                <?= Html::encode($stock->name) ?> &bull; Sektor: <strong><?= Html::encode($stock->sector ?? 'Umum') ?></strong>
                &bull; Tanggal Beli: <?= Html::encode($position->buy_date ?? '—') ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= Url::to(['/portfolio/refresh-prices', 'id' => $position->id]) ?>" class="btn btn-outline-success btn-sm shadow-sm fw-semibold" title="Ambil quote harga realtime terbaru dari Yahoo Finance">
                <i class="bi bi-arrow-repeat me-1"></i> Update Harga Real-Time
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalEditPosition">
                <i class="bi bi-pencil-square"></i> Edit Posisi
            </button>
            <a href="<?= Url::to(['/portfolio/index']) ?>" class="btn btn-outline-primary btn-sm shadow-sm">
                <i class="bi bi-arrow-left"></i> Kembali ke Portofolio
            </a>
            <a href="<?= Url::to(['/stock/view', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-dark btn-sm shadow-sm">
                <i class="bi bi-box-arrow-up-right"></i> Fundamental Saham
            </a>
        </div>
    </div>

    <!-- Ringkasan Posisi Saham (4 KPI Cards) -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Harga Beli & Modal -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm bg-light h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Harga Beli Awal (Avg)</div>
                    <div class="h3 mb-0 fw-bold text-dark"><?= $fmt->asCurrency($buyPrice) ?></div>
                    <div class="small text-muted mt-1">
                        Jumlah: <strong><?= $position->getFormattedShares() ?> lembar</strong> (Modal: <?= $fmt->asCurrency($position->getTotalCost()) ?>)
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Harga Pasar Terkini -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm bg-light h-100 border-start border-primary border-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-primary small fw-semibold text-uppercase">Harga Pasar Terkini</div>
                        <?php $quote = $position->getLiveQuoteDetails(); ?>
                        <?php if ($quote && !empty($quote['is_live'])): ?>
                            <span class="badge bg-success bg-opacity-25 text-success border border-success" style="font-size: 0.65rem;" title="Data real-time Yahoo Finance">
                                <i class="bi bi-broadcast"></i> LIVE
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="h3 mb-0 fw-bold text-primary"><?= $fmt->asCurrency($currentPrice) ?></div>
                    <div class="small mt-1 text-muted">
                        <?php if ($quote): ?>
                            <span class="<?= $quote['change'] >= 0 ? 'text-success' : 'text-danger' ?> fw-semibold">
                                <?= $quote['change'] >= 0 ? '+' : '' ?><?= number_format($quote['change'], 2) ?> (<?= $quote['change_pct'] >= 0 ? '+' : '' ?><?= $quote['change_pct'] ?>%)
                            </span>
                            <span class="text-muted" style="font-size:0.70rem;">&bull; <?= date('H:i', strtotime($quote['datetime'])) ?></span>
                        <?php else: ?>
                            Nilai: <strong><?= $fmt->asCurrency($position->getCurrentValue()) ?></strong>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Status Floating P/L -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm bg-<?= $isProfit ? 'success' : 'danger' ?> bg-opacity-10 h-100 border-start border-<?= $isProfit ? 'success' : 'danger' ?> border-4">
                <div class="card-body p-3">
                    <div class="text-<?= $isProfit ? 'success' : 'danger' ?> small fw-semibold text-uppercase">Floating Profit / Loss</div>
                    <div class="h3 mb-0 fw-bold text-<?= $isProfit ? 'success' : 'danger' ?>">
                        <?= ($plAmount >= 0 ? '+' : '') . $fmt->asCurrency($plAmount) ?>
                    </div>
                    <div class="small text-<?= $isProfit ? 'success' : 'danger' ?> fw-semibold mt-1">
                        <?= ($plPct >= 0 ? '+' : '') . $plPct ?>% dari modal beli
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 4: Target Jual & Progress -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm bg-primary bg-opacity-10 h-100 border-start border-primary border-4">
                <div class="card-body p-3">
                    <div class="text-primary small fw-semibold text-uppercase">Target Jual (+<?= $targetPct ?>%)</div>
                    <div class="h3 mb-0 fw-bold text-primary"><?= $fmt->asCurrency($targetPrice) ?></div>
                    <div class="small text-muted mt-1">
                        Estimasi Cuan: <strong>+<?= $fmt->asCurrency($position->getTargetProfitAmount()) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Nav Tab Navigasi Fitur -->
    <ul class="nav nav-tabs nav-fill mb-3 bg-white rounded-top shadow-sm border" id="portfolioTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $isLoss ? 'active' : '' ?> fw-bold py-3 text-danger" id="averaging-tab" data-bs-toggle="tab" data-bs-target="#averaging-pane" type="button" role="tab">
                <i class="bi bi-tools text-danger me-2"></i> 1. Strategi &amp; Kalkulator Average Down (Ketika Minus)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= !$isLoss ? 'active' : '' ?> fw-bold py-3 text-success" id="target-tab" data-bs-toggle="tab" data-bs-target="#target-pane" type="button" role="tab">
                <i class="bi bi-bullseye text-success me-2"></i> 2. Target Untung &amp; Exit Plan (Jual Berapa?)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-3 text-primary" id="analysis-tab" data-bs-toggle="tab" data-bs-target="#analysis-pane" type="button" role="tab">
                <i class="bi bi-cpu-fill text-primary me-2"></i> 3. Pusat Analisis Multi-Fitur (Candle, RSI, MA, Fibo)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-3 text-secondary" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab">
                <i class="bi bi-clock-history text-secondary me-2"></i> 4. Riwayat Skenario Diterapkan (<?= count($histories ?? []) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="portfolioTabContent">
        <!-- ========================================== -->
        <!-- TAB 1: STRATEGI & KALKULATOR AVERAGE DOWN  -->
        <!-- ========================================== -->
        <div class="tab-pane fade <?= $isLoss ? 'show active' : '' ?>" id="averaging-pane" role="tabpanel">
            <!-- Alert Penjelasan Konsep Average Down -->
            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3 mb-4">
                <div class="display-6 text-info"><i class="bi bi-lightbulb-fill"></i></div>
                <div>
                    <h5 class="alert-heading fw-bold mb-1">Bagaimana Strategi Average Down Bisa Mengubah Posisi Minus Jadi Untung Cepat?</h5>
                    <p class="mb-0 small text-secondary">
                        Saat saham turun, membeli tambahan lembar pada harga yang lebih murah akan <strong>menurunkan harga rata-rata modal beli Anda secara drastis</strong>. 
                        Hasilnya: Anda <strong>tidak perlu menunggu saham naik tinggi</strong> ke harga beli lama untuk impas/cuan, melainkan hanya butuh sedikit pantulan teknikal (+2% s/d +5%) untuk langsung mencetak keuntungan target!
                    </p>
                </div>
            </div>

            <!-- Tabel Perbandingan Skenario Averaging Down -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-table text-primary me-2"></i> Simulasi <?= count($dcaData['scenarios']) ?> Skenario Averaging Down
                    </h5>
                    <span class="badge bg-light text-dark border">Target Untung: +<?= $targetPct ?>%</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase text-muted">
                                <tr>
                                    <th>Skenario</th>
                                    <th class="text-end">Harga Beli Tambahan</th>
                                    <th class="text-center">Lembar Tambahan</th>
                                    <th class="text-end">Modal Tambahan</th>
                                    <th class="text-end text-primary">Harga Rata-Rata Baru (Avg)</th>
                                    <th class="text-center">Turun Dari Beli Awal</th>
                                    <th class="text-end text-success">Target Jual Baru (+<?= $targetPct ?>%)</th>
                                    <th class="text-center">Kenaikan Yg Dibutuhkan</th>
                                    <th class="text-center">Aksi Cepat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Baris Kondisi Sekarang Tanpa Averaging -->
                                <tr class="table-warning bg-opacity-10">
                                    <td>
                                        <strong>Tanpa Averaging (Kondisi Saat Ini)</strong>
                                        <div class="small text-muted">Menunggu harga balik ke modal lama</div>
                                    </td>
                                    <td class="text-end">—</td>
                                    <td class="text-center"><?= $position->getFormattedShares() ?> lbr</td>
                                    <td class="text-end">—</td>
                                    <td class="text-end fw-bold"><?= $fmt->asCurrency($buyPrice) ?></td>
                                    <td class="text-center">0%</td>
                                    <td class="text-end fw-bold text-primary"><?= $fmt->asCurrency($targetPrice) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">Butuh naik +<?= $dcaData['old_rebound_to_target'] ?>%</span>
                                    </td>
                                    <td class="text-center text-muted small">Posisi Saat Ini</td>
                                </tr>

                                <?php foreach ($dcaData['scenarios'] as $scKey => $sc): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?= $sc['badge_class'] ?> mb-1"><?= Html::encode($sc['title']) ?></span>
                                            <div class="small text-muted" style="font-size:0.75rem;"><?= Html::encode($sc['description']) ?></div>
                                        </td>
                                        <td class="text-end fw-bold">
                                            <?= $fmt->asCurrency($sc['add_price']) ?>
                                        </td>
                                        <td class="text-center">
                                            +<?= \app\models\PortfolioPosition::formatShares($sc['add_shares']) ?> lbr
                                            <div class="small text-muted">(Total: <?= \app\models\PortfolioPosition::formatShares($sc['new_total_shares']) ?>)</div>
                                        </td>
                                        <td class="text-end text-muted">
                                            +<?= $fmt->asCurrency($sc['add_cost']) ?>
                                        </td>
                                        <td class="text-end fw-bold text-primary fs-6">
                                            <?= $fmt->asCurrency($sc['new_avg_price']) ?>
                                        </td>
                                        <td class="text-center text-success fw-bold">
                                            -<?= $sc['reduction_pct'] ?>%
                                            <div class="small text-muted">(-$<?= number_format($sc['reduction_amount'], 2) ?>)</div>
                                        </td>
                                        <td class="text-end fw-bold text-success fs-6">
                                            <?= $fmt->asCurrency($sc['new_target_price']) ?>
                                            <div class="small text-muted">Cuan: +<?= $fmt->asCurrency($sc['new_target_net_profit']) ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $sc['difficulty']['class'] ?? 'success' ?> px-2 py-1">
                                                Target: +<?= $sc['rebound_to_target_pct'] ?>%
                                            </span>
                                            <div class="small text-muted" style="font-size:0.70rem;">Impas di +<?= $sc['rebound_to_breakeven_pct'] ?>%</div>
                                            <div class="text-secondary fw-semibold" style="font-size:0.68rem;"><?= $sc['difficulty']['text'] ?? '' ?></div>
                                        </td>
                                        <td class="text-center">
                                            <?php $formApply = ActiveForm::begin(['action' => ['/portfolio/apply-average', 'id' => $position->id], 'method' => 'post']); ?>
                                                <input type="hidden" name="new_avg_price" value="<?= $sc['new_avg_price'] ?>">
                                                <input type="hidden" name="new_total_shares" value="<?= $sc['new_total_shares'] ?>">
                                                <input type="hidden" name="scenario_name" value="<?= Html::encode($sc['title']) ?>">
                                                <input type="hidden" name="add_price" value="<?= $sc['add_price'] ?>">
                                                <input type="hidden" name="add_shares" value="<?= $sc['add_shares'] ?>">
                                                <input type="hidden" name="add_cost" value="<?= $sc['add_cost'] ?>">
                                                <input type="hidden" name="notes" value="Eksekusi <?= Html::encode($sc['title']) ?> di harga $<?= $sc['add_price'] ?> (+<?= \app\models\PortfolioPosition::formatShares($sc['add_shares']) ?> lbr)">
                                                <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2 fw-semibold" onclick="return confirm('Terapkan harga rata-rata baru $<?= $sc['new_avg_price'] ?> dan total <?= \app\models\PortfolioPosition::formatShares($sc['new_total_shares']) ?> lembar ke posisi portofolio Anda?');">
                                                    <i class="bi bi-check-lg"></i> Terapkan
                                                </button>
                                            <?php ActiveForm::end(); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Kalkulator Simulasi Averaging Interaktif (Multi-Mode Live Calculator) -->
            <div class="card border-0 shadow-sm bg-light mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h5 class="fw-bold text-dark mb-0">
                                <i class="bi bi-calculator-fill text-primary me-2"></i> Kalkulator Averaging Interaktif Kustom
                            </h5>
                            <div class="small text-muted">Simulasikan pembelian tambahan berdasarkan modal uang, lembar saham, atau target pantulan cepat</div>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" id="calc-mode-group">
                            <input type="radio" class="btn-check" name="calc_mode" id="mode-shares" value="shares" checked autocomplete="off">
                            <label class="btn btn-outline-primary" for="mode-shares"><i class="bi bi-pie-chart me-1"></i> Jumlah Lembar</label>

                            <input type="radio" class="btn-check" name="calc_mode" id="mode-budget" value="budget" autocomplete="off">
                            <label class="btn btn-outline-primary" for="mode-budget"><i class="bi bi-cash me-1"></i> Budget Modal ($)</label>

                            <input type="radio" class="btn-check" name="calc_mode" id="mode-rebound" value="rebound" autocomplete="off">
                            <label class="btn btn-outline-primary" for="mode-rebound"><i class="bi bi-lightning-charge me-1"></i> Target Pantulan (%)</label>
                        </div>
                    </div>

                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold text-muted">Rencana Harga Beli ($)</label>
                            <input type="number" step="any" min="0.0001" id="custom-add-price" class="form-control fw-bold text-primary" value="<?= $currentPrice ?>">
                            <div class="d-flex gap-1 mt-1 flex-wrap">
                                <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-1" style="font-size:0.70rem;" id="btn-use-current-price" title="Gunakan harga real-time saat ini">
                                    Real: $<?= number_format($currentPrice, 2) ?>
                                </button>
                                <?php if (!empty($multiAnalysis['best_support_price'])): ?>
                                    <button type="button" class="btn btn-outline-info btn-xs py-0 px-1" style="font-size:0.70rem;" id="btn-use-support-price" title="Gunakan harga level support terkuat">
                                        Support: $<?= number_format((float)$multiAnalysis['best_support_price'], 2) ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Mode Lembar -->
                        <div class="col-md-3 col-6" id="input-shares-container">
                            <label class="form-label small fw-bold text-muted">Rencana Lembar Tambahan</label>
                            <input type="number" step="any" min="0.000001" id="custom-add-shares" class="form-control fw-bold" value="<?= $shares ?>">
                            <div class="form-text small" style="font-size:0.70rem;">Bisa pecahan/desimal</div>
                        </div>

                        <!-- Mode Budget Modal ($) -->
                        <div class="col-md-3 col-6 d-none" id="input-budget-container">
                            <label class="form-label small fw-bold text-muted">Budget Modal Tersedia ($)</label>
                            <input type="number" step="any" min="0.01" id="custom-budget" class="form-control fw-bold" value="<?= round($currentPrice * $shares, 2) ?>">
                            <div class="form-text small" style="font-size:0.70rem;">Alokasi dana Anda dalam USD</div>
                        </div>

                        <!-- Mode Target Pantulan (%) -->
                        <div class="col-md-3 col-6 d-none" id="input-rebound-container">
                            <label class="form-label small fw-bold text-muted">Target Pantulan ke Impas (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.5" min="0.1" id="custom-rebound-target" class="form-control fw-bold" value="5.0">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text small" style="font-size:0.70rem;">Saham mantul segini langsung BEP</div>
                        </div>

                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold text-muted">Target Untung Diinginkan (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0.1" id="custom-target-pct" class="form-control" value="<?= $targetPct ?>">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text small" style="font-size:0.70rem;">Target profit setelah averaging</div>
                        </div>

                        <div class="col-md-3 col-6">
                            <button type="button" id="btn-calc-custom" class="btn btn-outline-primary w-100 fw-bold">
                                <i class="bi bi-arrow-repeat"></i> Hitung Ulang
                            </button>
                        </div>
                    </div>

                    <!-- Output Hasil Live Calculator -->
                    <div class="p-3 bg-white rounded shadow-sm border mb-3">
                        <div class="row g-3 text-center align-middle">
                            <div class="col-6 col-md-3 border-end">
                                <div class="text-muted small">Modal &amp; Lembar Tambahan</div>
                                <div class="h5 mb-0 fw-bold text-dark" id="res-add-info">—</div>
                                <div class="small text-muted" id="res-total-shares-info" style="font-size:0.75rem;">—</div>
                            </div>
                            <div class="col-6 col-md-3 border-end">
                                <div class="text-muted small">Harga Modal Rata-Rata Baru</div>
                                <div class="h4 mb-0 fw-bold text-primary" id="res-new-avg">—</div>
                                <div class="small text-success fw-semibold" id="res-reduction-info" style="font-size:0.75rem;">—</div>
                            </div>
                            <div class="col-6 col-md-3 border-end">
                                <div class="text-muted small">Pantulan Dibutuhkan ke Impas</div>
                                <div class="h5 mb-0 fw-bold text-dark" id="res-bep-rebound">—</div>
                                <div class="small" id="res-difficulty-badge" style="font-size:0.75rem;">—</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted small">Target Jual (+<span id="lbl-target-pct"><?= $targetPct ?></span>%)</div>
                                <div class="h4 mb-0 fw-bold text-success" id="res-new-target">—</div>
                                <div class="small text-muted" id="res-profit-info" style="font-size:0.75rem;">—</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Terapkan Hasil Kustom ke Portofolio -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="text-muted small" id="res-summary-note">
                            <i class="bi bi-info-circle me-1"></i> Perhitungan di atas otomatis diperbarui secara live setiap ada input baru.
                        </div>
                        <?php $formApplyCustom = ActiveForm::begin([
                            'action' => ['/portfolio/apply-average', 'id' => $position->id],
                            'method' => 'post',
                            'options' => ['id' => 'form-apply-custom']
                        ]); ?>
                            <input type="hidden" name="new_avg_price" id="custom-form-new-avg" value="">
                            <input type="hidden" name="new_total_shares" id="custom-form-new-shares" value="">
                            <input type="hidden" name="scenario_name" value="Kalkulator Kustom Averaging">
                            <input type="hidden" name="add_price" id="custom-form-add-price" value="">
                            <input type="hidden" name="add_shares" id="custom-form-add-shares" value="">
                            <input type="hidden" name="add_cost" id="custom-form-add-cost" value="">
                            <input type="hidden" name="notes" id="custom-form-notes" value="">
                            <button type="submit" class="btn btn-success fw-bold shadow-sm" id="btn-apply-custom" onclick="return confirm('Terapkan harga rata-rata baru ini ke posisi portofolio Anda?');" disabled>
                                <i class="bi bi-check-circle-fill me-1"></i> Terapkan Hasil Kustom ke Portofolio
                            </button>
                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: RENCANA TARGET KEUNTUNGAN (PROFIT)  -->
        <!-- ========================================== -->
        <div class="tab-pane fade <?= !$isLoss ? 'show active' : '' ?>" id="target-pane" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="mb-0 fw-bold text-success">
                                <i class="bi bi-cash-stack me-2"></i> Rencana Penjualan Bertahap (Multi-Target TP)
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted small">
                                Berdasarkan harga beli Anda di <strong><?= $fmt->asCurrency($buyPrice) ?></strong>, berikut adalah target harga jual presisi dan proyeksi laba bersih:
                            </p>

                            <ul class="list-group list-group-flush mb-4">
                                <?php
                                $tpTiers = [
                                    ['label' => 'Target Cepat (Konservatif)', 'pct' => 3.0, 'badge' => 'primary'],
                                    ['label' => 'Target Swing Normal', 'pct' => 5.0, 'badge' => 'success'],
                                    ['label' => 'Target Momentum Bagus', 'pct' => 10.0, 'badge' => 'info text-white'],
                                    ['label' => 'Target Big Runner', 'pct' => 15.0, 'badge' => 'warning text-dark'],
                                ];
                                foreach ($tpTiers as $tier):
                                    $tpPrice = round($buyPrice * (1 + ($tier['pct'] / 100)), 2);
                                    $tpProfit = round(($tpPrice - $buyPrice) * $shares, 2);
                                    $needGain = $currentPrice > 0 ? round((($tpPrice - $currentPrice) / $currentPrice) * 100, 2) : 0;
                                    $isReached = ($currentPrice >= $tpPrice);
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3 border-bottom">
                                        <div>
                                            <span class="badge bg-<?= $tier['badge'] ?> mb-1">+<?= $tier['pct'] ?>% — <?= $tier['label'] ?></span>
                                            <div class="h5 mb-0 fw-bold text-dark"><?= $fmt->asCurrency($tpPrice) ?></div>
                                            <div class="small text-muted">
                                                <?php if ($isReached): ?>
                                                    <span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Sudah Terlampaui!</span>
                                                <?php else: ?>
                                                    <span>Kurang +<?= $needGain ?>% dari harga pasar saat ini</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="h5 mb-0 fw-bold text-success">+<?= $fmt->asCurrency($tpProfit) ?></div>
                                            <div class="small text-muted">Laba Bersih</div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="p-3 bg-light rounded small text-secondary">
                                <i class="bi bi-info-circle text-primary me-1"></i>
                                <strong>Tips Eksekusi Jual Profesional:</strong> Jual 50% porsi saham Anda saat Target 1 (3%) tercapai untuk mengunci keuntungan pasti, lalu geser batas Stop Loss ke titik impas (Breakeven) untuk 50% sisanya.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="mb-0 fw-bold text-dark">
                                <i class="bi bi-speedometer2 text-primary me-2"></i> Status Pencapaian Target Saat Ini
                            </h5>
                        </div>
                        <div class="card-body p-4 text-center">
                            <div class="display-5 fw-bold text-<?= $isProfit ? 'success' : 'secondary' ?> mb-2">
                                <?= ($plPct >= 0 ? '+' : '') . $plPct ?>%
                            </div>
                            <p class="text-muted small mb-4">
                                Posisi saat ini terhadap Target Profit yang ditetapkan (+<?= $targetPct ?>%)
                            </p>

                            <div class="progress mb-2" style="height: 25px;">
                                <div class="progress-bar bg-<?= $isProfit ? 'success' : 'secondary' ?> fw-bold" role="progressbar" style="width: <?= $position->getProgressToTargetPct() ?>%;">
                                    <?= $position->getProgressToTargetPct() ?>% Menuju Target
                                </div>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-4">
                                <span>Harga Beli: <?= $fmt->asCurrency($buyPrice) ?></span>
                                <span>Harga Sekarang: <?= $fmt->asCurrency($currentPrice) ?></span>
                                <span>Target Jual: <?= $fmt->asCurrency($targetPrice) ?></span>
                            </div>

                            <div class="card bg-light border-0 p-3 text-start small">
                                <h6 class="fw-bold mb-2"><i class="bi bi-shield-check text-success me-1"></i> Rekomendasi Tindakan:</h6>
                                <?php if ($isProfit && $currentPrice >= $targetPrice): ?>
                                    <p class="text-success fw-bold mb-1">🎉 TARGET PROFIT TELAH TERCAPAI!</p>
                                    <p class="text-muted mb-0">Harga saat ini (<?= $fmt->asCurrency($currentPrice) ?>) sudah menyentuh target <?= $targetPct ?>%. Sangat disarankan untuk segera merealisasikan keuntungan (Sell/Take Profit).</p>
                                <?php elseif ($isProfit): ?>
                                    <p class="text-primary fw-bold mb-1">📈 DALAM TREN UNTUNG MENUJU TARGET</p>
                                    <p class="text-muted mb-0">Posisi Anda sedang mengapung profit +<?= $plPct ?>%. Pertahankan posisi dengan trailing stop hingga menyentuh <?= $fmt->asCurrency($targetPrice) ?>.</p>
                                <?php else: ?>
                                    <p class="text-danger fw-bold mb-1">⚠️ SEDANG MINUS DARI HARGA BELI</p>
                                    <p class="text-muted mb-0">Harga saat ini di bawah harga modal beli. Gunakan <strong>Tab 1 (Kalkulator Average Down)</strong> dan <strong>Tab 3 (Analisis Multi-Fitur)</strong> untuk merencanakan waktu beli terbaik agar modal cepat kembali.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: PUSAT ANALISIS MULTI-FITUR LENGKAP  -->
        <!-- ========================================== -->
        <div class="tab-pane fade" id="analysis-pane" role="tabpanel">
            <!-- Verdict Kesimpulan Analisis Confluence -->
            <div class="card border-0 shadow-sm bg-<?= $multiAnalysis['verdict_badge'] ?> bg-opacity-10 border-start border-<?= $multiAnalysis['verdict_badge'] ?> border-5 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <span class="badge bg-<?= $multiAnalysis['verdict_badge'] ?> px-3 py-2 fs-6">
                            <?= Html::encode($multiAnalysis['verdict']) ?>
                        </span>
                        <div class="small fw-bold text-<?= $multiAnalysis['verdict_badge'] ?>">
                            Confluence Score: <strong><?= $multiAnalysis['confluence_score'] ?>% Bullish Reversal</strong>
                        </div>
                    </div>
                    <p class="text-dark fw-semibold mb-2">
                        <?= Html::encode($multiAnalysis['verdict_explanation']) ?>
                    </p>
                    <?php if (!empty($multiAnalysis['best_support_price'])): ?>
                        <div class="small text-secondary">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i> Area Support Beli Terbaik Berdasarkan Analisis: 
                            <strong class="text-primary fs-6"><?= $fmt->asCurrency($multiAnalysis['best_support_price']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 6 Feature Cards Grid -->
            <div class="row g-3">
                <!-- 1. Candlestick Scanner Analysis -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm border-top border-warning border-4">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-warning text-dark">Price Action</span>
                                <span class="text-muted small">Pola Candle</span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">🕯️ Candlestick Reversal</h6>
                            <?php if ($candlestick !== null): ?>
                                <div class="mb-2">
                                    Pola: <strong><?= Html::encode($candlestick['pattern_name']) ?></strong>
                                    <span class="badge bg-<?= $candlestick['is_bullish'] ? 'success' : 'secondary' ?> ms-1">
                                        <?= Html::encode($candlestick['pattern_type']) ?>
                                    </span>
                                </div>
                                <div class="small text-muted mb-2">
                                    Konfirmasi Volume: <strong><?= Html::encode($candlestick['volume_confirmation']) ?></strong>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small mb-2">Belum terbentuk pola pembalikan arah candlestick signifikan hari ini.</p>
                            <?php endif; ?>
                            <a href="<?= Url::to(['/candlestick/detail', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-warning btn-sm w-100 text-dark">
                                Buka Candlestick &raquo;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 2. Double & Triple Bottom Patterns -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm border-top border-primary border-4">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary">Pola Reversal</span>
                                <span class="text-muted small">Formasi W</span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">📉 Double / Triple Bottom</h6>
                            <div class="mb-2 small">
                                Double Bottom: 
                                <?php if (!empty($bottomPatterns['has_double_bottom'])): ?>
                                    <span class="badge bg-success">Terdeteksi (<?= Html::encode($bottomPatterns['double_bottom_status']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-muted">Tidak Aktif</span>
                                <?php endif; ?>
                            </div>
                            <div class="mb-2 small">
                                Triple Bottom: 
                                <?php if (!empty($bottomPatterns['has_triple_bottom'])): ?>
                                    <span class="badge bg-success">Terdeteksi</span>
                                <?php else: ?>
                                    <span class="text-muted">Tidak Aktif</span>
                                <?php endif; ?>
                            </div>
                            <a href="<?= Url::to(['/double-bottom/detail', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-primary btn-sm w-100">
                                Buka Double Bottom &raquo;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 3. RSI Momentum & Oversold -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm border-top border-info border-4">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-info text-dark">Momentum</span>
                                <span class="text-muted small">RSI (14)</span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">📊 RSI Oversold &amp; Divergence</h6>
                            <?php if ($rsi !== null): ?>
                                <div class="mb-2">
                                    Nilai RSI: <strong class="<?= $rsi['is_oversold'] ? 'text-success' : 'text-dark' ?> fs-6"><?= $rsi['rsi_current'] ?></strong>
                                    <span class="badge bg-<?= $rsi['is_oversold'] ? 'success' : 'light text-dark border' ?> ms-1"><?= Html::encode($rsi['status']) ?></span>
                                </div>
                                <div class="small text-muted mb-2">
                                    Divergensi: <?= !empty($rsi['divergence_type']) ? Html::encode($rsi['divergence_type']) : 'Tidak terdeteksi' ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small mb-2">Data RSI dalam zona konsolidasi normal.</p>
                            <?php endif; ?>
                            <a href="<?= Url::to(['/rsi-double-bottom/index', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-info btn-sm w-100 text-dark">
                                Buka RSI Scanner &raquo;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 4. Moving Average Dynamic Support -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm border-top border-success border-4">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-success">Tren &amp; Support</span>
                                <span class="text-muted small">MA20 / MA50</span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">📈 Moving Average Support</h6>
                            <?php if ($ma !== null): ?>
                                <div class="small mb-1">MA20: <strong><?= $fmt->asCurrency($ma['fast_ma']) ?></strong> &bull; MA50: <strong><?= $fmt->asCurrency($ma['slow_ma']) ?></strong></div>
                                <div class="small mb-2">Tren: <strong><?= Html::encode($ma['trend_status']) ?></strong> (<?= Html::encode($ma['strategy_name']) ?>)</div>
                            <?php else: ?>
                                <p class="text-muted small mb-2">Data MA sedang dikalkulasi.</p>
                            <?php endif; ?>
                            <a href="<?= Url::to(['/moving-average/detail', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-success btn-sm w-100">
                                Buka Moving Average &raquo;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 5. Fibonacci Retracement Levels -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm border-top border-danger border-4">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-danger">Golden Pocket</span>
                                <span class="text-muted small">Fibonacci</span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">🌀 Fibonacci Retracement</h6>
                            <?php if ($fib !== null): ?>
                                <div class="small mb-1">Golden Ratio (0.618): <strong class="text-danger"><?= $fmt->asCurrency($fib['fibo_618']) ?></strong></div>
                                <div class="small mb-2">Support 0.50: <strong><?= $fmt->asCurrency($fib['fibo_500']) ?></strong></div>
                            <?php else: ?>
                                <p class="text-muted small mb-2">Data Fibonacci level tidak tersedia.</p>
                            <?php endif; ?>
                            <a href="<?= Url::to(['/fibonacci/detail', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-danger btn-sm w-100">
                                Buka Fibonacci &raquo;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 6. Confluence Strategy PRO -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm border-top border-dark border-4">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-dark">Multi-Sinyal</span>
                                <span class="text-muted small">PRO</span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">🎯 Confluence Strategy</h6>
                            <p class="small text-muted mb-2">
                                Validasi sinyal gabungan untuk akurasi posisi averaging dan probabilitas pantulan harga tertinggi.
                            </p>
                            <a href="<?= Url::to(['/strategy-confluence/index', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-dark btn-sm w-100">
                                Buka Confluence PRO &raquo;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 4: RIWAYAT SKENARIO DITERAPKAN         -->
        <!-- ========================================== -->
        <div class="tab-pane fade" id="history-pane" role="tabpanel">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-clock-history text-primary me-2"></i> Riwayat Eksekusi Averaging Down &amp; Perubahan Posisi
                    </h5>
                    <span class="badge bg-light text-dark border"><?= count($histories ?? []) ?> Catatan</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($histories)): ?>
                        <div class="text-center py-5">
                            <div class="display-6 text-muted mb-2"><i class="bi bi-journal-x"></i></div>
                            <h6 class="fw-bold text-secondary">Belum ada riwayat skenario averaging down yang diterapkan</h6>
                            <p class="text-muted small mb-0">
                                Saat Anda menekan tombol <strong>"Terapkan"</strong> pada salah satu skenario averaging down atau kalkulator kustom, rekam jejak eksekusi akan otomatis tersimpan di sini.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-uppercase text-muted">
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th>Waktu Eksekusi</th>
                                        <th>Nama Skenario</th>
                                        <th class="text-end">Harga Beli Lama</th>
                                        <th class="text-end text-primary">Harga Rata-rata Baru</th>
                                        <th class="text-center">Penurunan Avg</th>
                                        <th class="text-center">Lembar Ditambah</th>
                                        <th class="text-end">Harga Beli Tambahan</th>
                                        <th class="text-end">Modal Ditambahkan</th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($histories as $hIdx => $h): ?>
                                        <?php
                                        $oldP = (float) $h->old_price;
                                        $newP = (float) $h->new_price;
                                        $dropAmount = $oldP - $newP;
                                        $dropPct = $oldP > 0 ? round(($dropAmount / $oldP) * 100, 2) : 0.0;
                                        ?>
                                        <tr>
                                            <td class="text-muted small"><?= $hIdx + 1 ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= date('d M Y', (int) $h->created_at) ?></div>
                                                <div class="small text-muted" style="font-size: 0.72rem;"><?= date('H:i:s', (int) $h->created_at) ?> WIB</div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                                    <?= Html::encode($h->scenario_name ?? 'Averaging Down') ?>
                                                </span>
                                            </td>
                                            <td class="text-end text-muted">
                                                $<?= number_format($oldP, 2) ?>
                                            </td>
                                            <td class="text-end fw-bold text-primary fs-6">
                                                $<?= number_format($newP, 2) ?>
                                            </td>
                                            <td class="text-center text-success fw-bold">
                                                <?php if ($dropPct > 0): ?>
                                                    -<?= $dropPct ?>%
                                                    <div class="small text-muted" style="font-size:0.70rem;">(-$<?= number_format($dropAmount, 2) ?>)</div>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center fw-semibold">
                                                +<?= \app\models\PortfolioPosition::formatShares((float) $h->added_shares) ?> lbr
                                                <div class="small text-muted" style="font-size:0.70rem;">(Total: <?= \app\models\PortfolioPosition::formatShares((float) $h->new_shares) ?>)</div>
                                            </td>
                                            <td class="text-end fw-semibold">
                                                <?= $h->added_price ? '$' . number_format((float) $h->added_price, 2) : '—' ?>
                                            </td>
                                            <td class="text-end text-muted">
                                                <?= $h->added_cost ? '$' . number_format((float) $h->added_cost, 2) : '—' ?>
                                            </td>
                                            <td class="small text-muted" style="max-width: 220px;">
                                                <?= Html::encode($h->notes ?? '—') ?>
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
    </div>
</div>

<!-- Modal Edit Posisi Saham -->
<div class="modal fade" id="modalEditPosition" tabindex="-1" aria-labelledby="modalEditPositionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <?php $formEdit = ActiveForm::begin(['action' => ['/portfolio/update', 'id' => $position->id], 'method' => 'post']); ?>
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold" id="modalEditPositionLabel">
                    <i class="bi bi-pencil-square me-2"></i> Edit Posisi <?= Html::encode($stock->symbol) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">Harga Beli Awal ($)</label>
                        <input type="number" step="0.0001" name="PortfolioPosition[buy_price]" class="form-control" value="<?= $position->buy_price ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">Jumlah Lembar (Shares)</label>
                        <input type="number" step="any" min="0.0001" name="PortfolioPosition[shares]" class="form-control" value="<?= $position->shares ?>" required>
                        <div class="form-text small" style="font-size:0.70rem;">Bisa bilangan bulat atau pecahan/desimal.</div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">Target Untung (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.1" name="PortfolioPosition[target_profit_pct]" class="form-control" value="<?= $position->target_profit_pct ?>" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">Tanggal Pembelian</label>
                        <input type="date" name="PortfolioPosition[buy_date]" class="form-control" value="<?= $position->buy_date ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Catatan / Alasan Entry</label>
                    <textarea name="PortfolioPosition[notes]" class="form-control" rows="3"><?= Html::encode($position->notes) ?></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Simpan Perubahan</button>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<!-- Script Live Multi-Mode Calculator untuk Averaging Down -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const oldPrice = <?= json_encode($buyPrice) ?>;
    const oldShares = <?= json_encode($shares) ?>;
    const currentPriceVal = <?= json_encode($currentPrice) ?>;

    const elAddPrice = document.getElementById('custom-add-price');
    const elAddShares = document.getElementById('custom-add-shares');
    const elBudget = document.getElementById('custom-budget');
    const elReboundTarget = document.getElementById('custom-rebound-target');
    const elTargetPct = document.getElementById('custom-target-pct');

    const containerShares = document.getElementById('input-shares-container');
    const containerBudget = document.getElementById('input-budget-container');
    const containerRebound = document.getElementById('input-rebound-container');

    const formNewAvg = document.getElementById('custom-form-new-avg');
    const formNewShares = document.getElementById('custom-form-new-shares');
    const formNotes = document.getElementById('custom-form-notes');
    const btnApply = document.getElementById('btn-apply-custom');

    function getActiveMode() {
        const checked = document.querySelector('input[name="calc_mode"]:checked');
        return checked ? checked.value : 'shares';
    }

    function switchMode(mode) {
        containerShares.classList.toggle('d-none', mode !== 'shares');
        containerBudget.classList.toggle('d-none', mode !== 'budget');
        containerRebound.classList.toggle('d-none', mode !== 'rebound');
        calculateLive();
    }

    document.querySelectorAll('input[name="calc_mode"]').forEach(radio => {
        radio.addEventListener('change', function () {
            switchMode(this.value);
        });
    });

    const btnUseCurrent = document.getElementById('btn-use-current-price');
    if (btnUseCurrent) {
        btnUseCurrent.addEventListener('click', function () {
            elAddPrice.value = currentPriceVal;
            calculateLive();
        });
    }

    const btnUseSupport = document.getElementById('btn-use-support-price');
    if (btnUseSupport) {
        btnUseSupport.addEventListener('click', function () {
            const supp = <?= json_encode($multiAnalysis['best_support_price'] ?? null) ?>;
            if (supp) {
                elAddPrice.value = parseFloat(supp);
                calculateLive();
            }
        });
    }

    function fmtUSD(val) {
        return '$' + Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtShares(val) {
        const n = Number(val);
        if (Number.isInteger(n)) return n.toString();
        return parseFloat(n.toFixed(4)).toString();
    }

    function calculateLive() {
        const mode = getActiveMode();
        const addPrice = parseFloat(elAddPrice.value) || 0;
        const targetPct = parseFloat(elTargetPct.value) || 3.0;

        document.getElementById('lbl-target-pct').innerText = targetPct;

        if (addPrice <= 0) {
            btnApply.disabled = true;
            return;
        }

        let addShares = 0;
        let addCost = 0;

        if (mode === 'shares') {
            addShares = parseFloat(elAddShares.value) || 0;
            addCost = addPrice * addShares;
        } else if (mode === 'budget') {
            const budget = parseFloat(elBudget.value) || 0;
            addCost = budget;
            addShares = budget / addPrice;
        } else if (mode === 'rebound') {
            const desiredRebound = parseFloat(elReboundTarget.value) || 0;
            if (desiredRebound <= 0) return;
            const targetNewAvg = addPrice * (1 + (desiredRebound / 100));

            if (targetNewAvg >= oldPrice) {
                // Posisi sudah di atas harga beli lama, tidak butuh avg down
                addShares = 0;
                addCost = 0;
            } else if (targetNewAvg <= addPrice) {
                addShares = 0;
                addCost = 0;
            } else {
                addShares = (oldShares * (oldPrice - targetNewAvg)) / (targetNewAvg - addPrice);
                addCost = addShares * addPrice;
            }
        }

        if (addShares <= 0 || addCost <= 0) {
            document.getElementById('res-add-info').innerText = '—';
            document.getElementById('res-total-shares-info').innerText = 'Masukkan angka yang valid';
            document.getElementById('res-new-avg').innerText = '—';
            document.getElementById('res-reduction-info').innerText = '';
            document.getElementById('res-bep-rebound').innerText = '—';
            document.getElementById('res-difficulty-badge').innerText = '';
            document.getElementById('res-new-target').innerText = '—';
            document.getElementById('res-profit-info').innerText = '';
            btnApply.disabled = true;
            return;
        }

        const oldCost = oldPrice * oldShares;
        const newTotalShares = oldShares + addShares;
        const newTotalCost = oldCost + addCost;
        const newAvgPrice = newTotalCost / newTotalShares;

        const reductionAmount = oldPrice - newAvgPrice;
        const reductionPct = (reductionAmount / oldPrice) * 100;

        const bepReboundPct = ((newAvgPrice - addPrice) / addPrice) * 100;
        const newTargetPrice = newAvgPrice * (1 + (targetPct / 100));
        const targetReboundPct = ((newTargetPrice - addPrice) / addPrice) * 100;
        const netProfit = (newTargetPrice - newAvgPrice) * newTotalShares;

        // Render hasil ke UI
        document.getElementById('res-add-info').innerHTML = fmtUSD(addCost) + ' <span class="badge bg-light text-dark border">+' + fmtShares(addShares) + ' lbr</span>';
        document.getElementById('res-total-shares-info').innerText = 'Total lembar baru: ' + fmtShares(newTotalShares) + ' lbr';

        document.getElementById('res-new-avg').innerText = fmtUSD(newAvgPrice);
        document.getElementById('res-reduction-info').innerText = 'Turun -$' + reductionAmount.toFixed(2) + ' (-' + reductionPct.toFixed(2) + '%)';

        document.getElementById('res-bep-rebound').innerText = '+' + bepReboundPct.toFixed(2) + '%';
        
        let diffBadge = '';
        if (targetReboundPct <= 5.0) {
            diffBadge = '<span class="badge bg-success">Pantulan Sangat Ringan (Mudah)</span>';
        } else if (targetReboundPct <= 15.0) {
            diffBadge = '<span class="badge bg-info text-white">Pantulan Wajar (Normal)</span>';
        } else if (targetReboundPct <= 30.0) {
            diffBadge = '<span class="badge bg-warning text-dark">Pantulan Menengah</span>';
        } else {
            diffBadge = '<span class="badge bg-secondary">Butuh Reli Kuat</span>';
        }
        document.getElementById('res-difficulty-badge').innerHTML = diffBadge;

        document.getElementById('res-new-target').innerText = fmtUSD(newTargetPrice);
        document.getElementById('res-profit-info').innerHTML = 'Cuan: <strong class="text-success">+' + fmtUSD(netProfit) + '</strong> (naik +' + targetReboundPct.toFixed(2) + '%)';

        // Set nilai ke form apply
        formNewAvg.value = newAvgPrice.toFixed(2);
        formNewShares.value = newTotalShares.toFixed(4);
        if (document.getElementById('custom-form-add-price')) {
            document.getElementById('custom-form-add-price').value = addPrice.toFixed(2);
        }
        if (document.getElementById('custom-form-add-shares')) {
            document.getElementById('custom-form-add-shares').value = addShares.toFixed(4);
        }
        if (document.getElementById('custom-form-add-cost')) {
            document.getElementById('custom-form-add-cost').value = addCost.toFixed(2);
        }
        formNotes.value = 'Kalkulasi Kustom: Beli di $' + addPrice.toFixed(2) + ' sebanyak +' + fmtShares(addShares) + ' lbr (' + fmtUSD(addCost) + ')';
        btnApply.disabled = false;
    }

    // Event listeners
    [elAddPrice, elAddShares, elBudget, elReboundTarget, elTargetPct].forEach(input => {
        if (input) {
            input.addEventListener('input', calculateLive);
            input.addEventListener('change', calculateLive);
        }
    });

    const btnCalc = document.getElementById('btn-calc-custom');
    if (btnCalc) {
        btnCalc.addEventListener('click', calculateLive);
    }

    // Inisialisasi awal
    calculateLive();
});
</script>
