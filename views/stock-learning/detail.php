<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\Stock $stock */
/** @var app\models\StockLearningRun|null $latestRun */
/** @var array $generations */
/** @var array|null $currentSignal */
/** @var array $simulatedTrades */
/** @var array $strategies */
/** @var array $timeframes */
/** @var app\models\StockLearningRun[] $allRunsForStock */

use app\assets\ChartAsset;
use yii\bootstrap5\Html;
use yii\helpers\Url;

ChartAsset::register($this);

$this->title = 'Hasil Pembelajaran Strategi: ' . $stock->symbol;
$this->params['breadcrumbs'][] = ['label' => 'Stock Learning Engine', 'url' => ['index']];
$this->params['breadcrumbs'][] = $stock->symbol;

$chartId = 'convergence-chart-' . $stock->id;
$params = $latestRun ? $latestRun->getDecodedParams() : [];
$simulatedTrades = $simulatedTrades ?? [];

// Siapkan data untuk ECharts Konvergensi
$genNumbers = [];
$bestFitnessData = [];
$avgFitnessData = [];
$winRateData = [];

foreach ($generations as $g) {
    $genNumbers[] = 'Gen ' . $g['generation'];
    $bestFitnessData[] = round((float) $g['best_fitness'], 2);
    $avgFitnessData[] = round((float) $g['avg_fitness'], 2);
    $winRateData[] = round((float) $g['best_win_rate'], 1);
}
?>

<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fs-4 text-primary"><i class="bi bi-cpu"></i></span>
                <h1 class="h3 mb-0 fw-bold text-dark font-monospace"><?= Html::encode($stock->symbol) ?></h1>
                <span class="badge bg-light text-dark border px-2 py-1"><?= Html::encode($stock->name ?? '') ?></span>
                <?php if ($latestRun): ?>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace">
                        <i class="bi bi-hash"></i>Sesi #<?= $latestRun->id ?>
                    </span>
                    <?php if ($latestRun->status === 'completed'): ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                            <i class="bi bi-check-circle-fill me-1"></i>Model Terlatih (<?= $latestRun->generation_max ?> Gen)
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Optimasi parameter trading adaptif menggunakan Algoritma Genetika dan validasi Walk-Forward Out-of-Sample.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= Url::to(['/stock/view', 'symbol' => $stock->symbol]) ?>" class="btn btn-outline-info btn-sm shadow-xs">
                <i class="bi bi-graph-up me-1"></i> Chart Saham
            </a>
            <a href="<?= Url::to(['index']) ?>" class="btn btn-outline-secondary btn-sm shadow-xs">
                <i class="bi bi-arrow-left me-1"></i> Semua Model
            </a>
        </div>
    </div>

    <?php if (!$latestRun || $latestRun->status !== 'completed'): ?>
        <!-- Alert Belum Pernah Dilatih -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 text-center py-5">
            <div class="card-body">
                <i class="bi bi-cpu fs-1 text-primary opacity-50 mb-3 d-block"></i>
                <h5 class="fw-bold text-dark">Saham Ini Belum Memiliki Model Pembelajaran</h5>
                <p class="text-muted small mb-4 mx-auto" style="max-width: 550px;">
                    Jalankan simulasi untuk menemukan kombinasi indikator, rasio take-profit, dan stop-loss yang terbukti menghasilkan akurasi tertinggi untuk <strong><?= Html::encode($stock->symbol) ?></strong>.
                </p>
                <div class="d-inline-block text-start p-4 bg-light rounded-4 border shadow-xs" style="max-width: 600px; width: 100%;">
                    <?= Html::beginForm(['/stock-learning/run'], 'post', ['class' => 'row g-3 align-items-end']) ?>
                        <?= Html::hiddenInput('symbol', $stock->symbol) ?>
                        <div class="col-md-4 col-12">
                            <label class="form-label small fw-bold">Strategi Basis</label>
                            <?= Html::dropDownList('strategy', 'stochastic', $strategies, ['class' => 'form-select form-select-sm']) ?>
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label small fw-bold">Timeframe</label>
                            <?= Html::dropDownList('timeframe', 24, $timeframes, ['class' => 'form-select form-select-sm']) ?>
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label small fw-bold">Generasi Evolusi</label>
                            <?= Html::dropDownList('generations', 35, [20 => '20 Gen (Cepat)', 35 => '35 Gen (Optimal)', 60 => '60 Gen (Mendalam)'], ['class' => 'form-select form-select-sm']) ?>
                        </div>
                        <div class="col-12 text-center mt-3">
                            <?= Html::submitButton('<i class="bi bi-play-circle-fill me-1"></i> Mulai Pembelajaran Sekarang', ['class' => 'btn btn-primary fw-bold px-4 shadow-sm']) ?>
                        </div>
                    <?= Html::endForm() ?>
                </div>
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
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 border-0 rounded-3 shadow-xs p-3" style="background: #ffffff; border-top: 3px solid #0284c7 !important;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-2"><i class="bi bi-calendar-check fs-6"></i></span>
                                <h6 class="fw-bold text-dark mb-0 small">Prioritaskan Daily (1D)</h6>
                            </div>
                            <ul class="text-muted small ps-3 mb-0" style="font-size: 0.81rem; line-height: 1.55;">
                                <li class="mb-1">Data Daily memiliki <strong>500+ hari historis (2 tahun penuh)</strong> di database Anda.</li>
                                <li>Pembelajaran di Daily jauh lebih stabil, tahan banting, dan teruji di berbagai siklus pasar (bullish, bearish, dan sideways).</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 border-0 rounded-3 shadow-xs p-3" style="background: #ffffff; border-top: 3px solid #10b981 !important;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success bg-opacity-10 text-success p-2 rounded-2"><i class="bi bi-sliders fs-6"></i></span>
                                <h6 class="fw-bold text-dark mb-0 small">Ganti Strategi Sesuai Karakter Saham</h6>
                            </div>
                            <ul class="text-muted small ps-3 mb-0" style="font-size: 0.81rem; line-height: 1.55;">
                                <li class="mb-1">Jika saham sering <strong>swing / mantul di area jenuh</strong>: Pilih strategi <em>Stochastic Oscillator</em> atau <em>RSI Momentum</em>.</li>
                                <li>Jika saham <strong>tren kuat (trending)</strong>: Pilih strategi <em>Multi-Indicator Confluence</em>.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 border-0 rounded-3 shadow-xs p-3" style="background: #ffffff; border-top: 3px solid #f59e0b !important;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-warning bg-opacity-10 text-warning p-2 rounded-2"><i class="bi bi-graph-up-arrow fs-6"></i></span>
                                <h6 class="fw-bold text-dark mb-0 small">Fokus Profit Factor Tinggi</h6>
                            </div>
                            <ul class="text-muted small ps-3 mb-0" style="font-size: 0.81rem; line-height: 1.55;">
                                <li class="mb-1">Cari parameter dengan Profit Factor tinggi, bukan hanya Win Rate.</li>
                                <li>Model dengan Win Rate 55% tetapi <strong>Profit Factor 2.50</strong> jauh lebih menguntungkan daripada Win Rate 75% tetapi sekali kalah menghabiskan modal.</li>
                            </ul>
                        </div>
                    </div>
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
    <?php else: ?>
        <!-- Sinyal Real-Time Card -->
        <?php if ($currentSignal !== null): ?>
            <?php
            $isSignalActive = !empty($currentSignal['is_active']);
            $cardBg = $isSignalActive 
                ? 'background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%); border: 1px solid #bbf7d0 !important;' 
                : 'background: linear-gradient(135deg, #fffbeb 0%, #f8fafc 100%); border: 1px solid #fde68a !important;';
            ?>
            <div class="card border-0 shadow-sm rounded-4 mb-4" style="<?= $cardBg ?>">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <span class="badge bg-<?= $currentSignal['badge_class'] ?> px-3 py-1 fs-6 font-monospace">
                                    <i class="bi <?= $isSignalActive ? 'bi-lightning-charge-fill' : 'bi-hourglass-split' ?> me-1"></i>
                                    <?= Html::encode($currentSignal['title']) ?>
                                </span>
                                <span class="badge bg-white text-dark border">Data Per: <?= Html::encode($currentSignal['last_candle_date']) ?></span>
                            </div>
                            <h5 class="fw-bold text-dark mb-0 mt-2">
                                <?= $isSignalActive 
                                    ? 'Setup Beli Terkonfirmasi — Siap Eksekusi Sesuai Parameter Optimal' 
                                    : 'Menunggu Kondisi Sinyal Terbentuk — Simpan Trading Plan Berikut' ?>
                            </h5>
                        </div>
                        <div class="d-flex gap-2 flex-wrap text-end">
                            <div class="p-2 px-3 bg-white rounded-3 shadow-xs border">
                                <small class="text-muted d-block" style="font-size:0.7rem;">HARGA PASAR (ENTRY)</small>
                                <span class="fw-bold text-primary fs-5 font-monospace">$<?= number_format((float) $currentSignal['current_price'], 2) ?></span>
                            </div>
                            <div class="p-2 px-3 bg-white rounded-3 shadow-xs border border-success border-opacity-50">
                                <small class="text-success d-block fw-bold" style="font-size:0.7rem;">TARGET PROFIT (+<?= $currentSignal['target_pct'] ?>%)</small>
                                <span class="fw-bold text-success fs-5 font-monospace">$<?= number_format((float) $currentSignal['target_price'], 2) ?></span>
                            </div>
                            <div class="p-2 px-3 bg-white rounded-3 shadow-xs border border-danger border-opacity-50">
                                <small class="text-danger d-block fw-bold" style="font-size:0.7rem;">STOP LOSS (-<?= $currentSignal['stop_loss_pct'] ?>%)</small>
                                <span class="fw-bold text-danger fs-5 font-monospace">$<?= number_format((float) $currentSignal['stop_loss'], 2) ?></span>
                            </div>
                            <div class="p-2 px-3 bg-white rounded-3 shadow-xs border">
                                <small class="text-muted d-block" style="font-size:0.7rem;">RISK : REWARD</small>
                                <span class="fw-bold text-dark fs-5 font-monospace">1 : <?= $currentSignal['risk_reward'] ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($currentSignal['diagnostics']['conditions'])): ?>
                        <div class="mt-3 pt-3 border-top border-dark border-opacity-10">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-dark d-flex align-items-center gap-1">
                                    <i class="bi bi-ui-checks text-primary"></i> Checklist Syarat Sinyal Beli (Live Indikator Pasar):
                                </span>
                                <small class="text-secondary"><?= Html::encode($currentSignal['diagnostics']['summary'] ?? '') ?></small>
                            </div>
                            <div class="row g-2">
                                <?php foreach ($currentSignal['diagnostics']['conditions'] as $cond): ?>
                                    <div class="col-md-6 col-12">
                                        <div class="p-2 px-3 bg-white bg-opacity-75 rounded-3 border d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small fw-bold text-dark"><?= Html::encode($cond['label']) ?></div>
                                                <small class="text-muted d-block" style="font-size:0.75rem;">
                                                    Saat ini: <strong><?= Html::encode($cond['current']) ?></strong> &bull; Syarat: <?= Html::encode($cond['required']) ?>
                                                </small>
                                            </div>
                                            <div>
                                                <?php if ($cond['met']): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                                        <i class="bi bi-check-circle-fill me-1"></i> Terpenuhi
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning px-2 py-1">
                                                        <i class="bi bi-hourglass-split me-1"></i> Menunggu
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (!$isSignalActive && !empty($currentSignal['diagnostics']['trigger_forecast'])): ?>
                                <div class="alert alert-light border mt-2 mb-0 py-2 px-3 small text-muted d-flex align-items-center gap-2">
                                    <i class="bi bi-info-circle-fill text-primary fs-6"></i>
                                    <span><strong>Kapan sinyal beli muncul?</strong> <?= Html::encode($currentSignal['diagnostics']['trigger_forecast']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- KPI Performance Grid -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #10b981 !important;">
                    <div class="card-body p-3">
                        <span class="small text-muted fw-bold text-uppercase d-block mb-1">Out-of-Sample Win-Rate</span>
                        <h3 class="fw-bold mb-0 font-monospace">
                            <?php if ($latestRun->oos_win_rate !== null): ?>
                                <?php $oosVal = (float)$latestRun->oos_win_rate; ?>
                                <span class="<?= $oosVal >= 60.0 ? 'text-success' : ($oosVal >= 40.0 ? 'text-primary' : 'text-danger') ?>">
                                    <?= number_format($oosVal, 1) ?>%
                                </span>
                            <?php else: ?>
                                <span class="fs-6 text-muted">0 Trade di OOS</span>
                            <?php endif; ?>
                        </h3>
                        <small class="text-muted">Akurasi validasi independen</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #0284c7 !important;">
                    <div class="card-body p-3">
                        <span class="small text-muted fw-bold text-uppercase d-block mb-1">In-Sample Win-Rate</span>
                        <h3 class="fw-bold text-primary mb-0 font-monospace">
                            <?= number_format((float) $latestRun->is_win_rate, 1) ?>%
                        </h3>
                        <small class="text-muted">Akurasi dataset pelatihan</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #8b5cf6 !important;">
                    <div class="card-body p-3">
                        <span class="small text-muted fw-bold text-uppercase d-block mb-1">Profit Factor</span>
                        <h3 class="fw-bold mb-0 font-monospace" style="color: #8b5cf6;">
                            <?= ($latestRun->profit_factor !== null && (float)$latestRun->profit_factor > 0) ? number_format((float) $latestRun->profit_factor, 2) : '1.00' ?>
                        </h3>
                        <small class="text-muted">Rasio Gross Profit / Gross Loss</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #ef4444 !important;">
                    <div class="card-body p-3">
                        <span class="small text-muted fw-bold text-uppercase d-block mb-1">Max Drawdown</span>
                        <h3 class="fw-bold text-danger mb-0 font-monospace">
                            <?= ((float) $latestRun->max_drawdown > 0) ? '-' . number_format((float) $latestRun->max_drawdown, 1) . '%' : '0.0%' ?>
                        </h3>
                        <small class="text-muted">Risiko penurunan modal terdalam</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Convergence Chart & Optimal Parameters -->
        <div class="row g-4 mb-4">
            <!-- Convergence Chart -->
            <div class="col-lg-8 col-12">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-graph-up-arrow text-primary"></i>
                            <h6 class="fw-bold mb-0 text-dark">Grafik Konvergensi Generasi Evolusi (ECharts)</h6>
                        </div>
                        <small class="text-muted"><?= count($generations) ?> Generasi Tercatat</small>
                    </div>
                    <div class="card-body p-3">
                        <div id="<?= $chartId ?>" style="width: 100%; height: 380px;"></div>
                    </div>
                </div>
            </div>

            <!-- Optimal Parameters Breakdown -->
            <div class="col-lg-4 col-12">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-sliders text-warning"></i>
                            <h6 class="fw-bold mb-0 text-dark">Parameter Optimal Ditemukan</h6>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <ul class="list-group list-group-flush small">
                            <?php foreach ($params as $k => $val): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <span class="text-muted font-monospace"><?= Html::encode($k) ?></span>
                                    <span class="badge bg-light text-dark border font-monospace fs-6">
                                        <?= is_bool($val) ? ($val ? 'TRUE' : 'FALSE') : Html::encode((string) $val) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="mt-4 pt-3 border-top">
                            <small class="text-muted d-block mb-2">Simulasi &amp; Komputasi:</small>
                            <div class="d-flex justify-content-between small text-secondary mb-1">
                                <span>Total Backtest:</span>
                                <strong class="font-monospace"><?= number_format($latestRun->total_evals) ?> kali</strong>
                            </div>
                            <div class="d-flex justify-content-between small text-secondary">
                                <span>Waktu Eksekusi:</span>
                                <strong class="font-monospace"><?= $latestRun->runtime_seconds ?> detik</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Log Transaksi Simulasi -->
        <?php if (!empty($simulatedTrades)): ?>
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-primary"></i>
                        <h6 class="fw-bold mb-0 text-dark">Log Transaksi Simulasi Historis (In-Sample &amp; Out-of-Sample)</h6>
                    </div>
                    <span class="badge bg-secondary rounded-pill"><?= count($simulatedTrades) ?> Transaksi</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase text-secondary sticky-top">
                                <tr>
                                    <th>#</th>
                                    <th>Segmen</th>
                                    <th>Tanggal Masuk (Entry)</th>
                                    <th>Harga Masuk</th>
                                    <th>Tanggal Keluar (Exit)</th>
                                    <th>Harga Keluar</th>
                                    <th class="text-center">Durasi</th>
                                    <th>Alasan Keluar</th>
                                    <th class="text-end">PnL Bersih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($simulatedTrades, 0, 30) as $idx => $t): ?>
                                    <?php
                                    $isWin = !empty($t['is_win']);
                                    $pnlColor = $isWin ? 'text-success' : 'text-danger';
                                    $segmentBadge = ($t['segment'] ?? '') === 'OUT_OF_SAMPLE' 
                                        ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:0.68rem;">OUT-OF-SAMPLE</span>'
                                        : '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size:0.68rem;">IN-SAMPLE</span>';
                                    $reasonLabel = match ($t['exit_reason'] ?? '') {
                                        'TP_HIT' => '<span class="badge bg-success">TARGET PROFIT</span>',
                                        'SL_HIT' => '<span class="badge bg-danger">STOP LOSS</span>',
                                        default => '<span class="badge bg-secondary">TIME EXIT</span>',
                                    };
                                    ?>
                                    <tr>
                                        <td class="text-muted small"><?= $idx + 1 ?></td>
                                        <td><?= $segmentBadge ?></td>
                                        <td class="font-monospace small"><?= Html::encode($t['entry_date'] ?? '-') ?></td>
                                        <td class="font-monospace small fw-bold">$<?= number_format((float)($t['entry_price'] ?? 0), 2) ?></td>
                                        <td class="font-monospace small"><?= Html::encode($t['exit_date'] ?? '-') ?></td>
                                        <td class="font-monospace small fw-bold">$<?= number_format((float)($t['exit_price'] ?? 0), 2) ?></td>
                                        <td class="text-center small text-muted font-monospace"><?= $t['hold_bars'] ?? 0 ?> bar</td>
                                        <td><?= $reasonLabel ?></td>
                                        <td class="text-end font-monospace fw-bold <?= $pnlColor ?>">
                                            <?= ($t['pnl_pct'] > 0 ? '+' : '') . number_format((float)$t['pnl_pct'], 2) ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

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
                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-xs" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDetailGuide" aria-expanded="true" aria-controls="collapseDetailGuide">
                    <i class="bi bi-chevron-down me-1"></i> Buka / Sembunyikan Panduan
                </button>
            </div>
            <div class="collapse show" id="collapseDetailGuide">
                <div class="card-body p-3 p-md-4">
                    <div class="row g-3">
                        <!-- Poin 1 -->
                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="card h-100 border-0 rounded-3 shadow-xs p-3" style="background: #ffffff; border-top: 3px solid #0284c7 !important;">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-2"><i class="bi bi-calendar-check fs-6"></i></span>
                                    <h6 class="fw-bold text-dark mb-0 small">Prioritaskan Daily (1D)</h6>
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
                                    <h6 class="fw-bold text-dark mb-0 small">Ganti Strategi Sesuai Karakter Saham</h6>
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

        <!-- Form Latih Ulang (Re-Train) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-repeat text-primary"></i>
                    <h6 class="fw-bold mb-0 text-dark">Latih Ulang / Eksperimen Konfigurasi Baru</h6>
                </div>
            </div>
            <div class="card-body p-3">
                <?= Html::beginForm(['/stock-learning/run'], 'post', ['class' => 'row g-3 align-items-end']) ?>
                    <?= Html::hiddenInput('symbol', $stock->symbol) ?>
                    <div class="col-md-3 col-12">
                        <label class="form-label small fw-bold">Strategi Basis</label>
                        <?= Html::dropDownList('strategy', $latestRun->strategy_type, $strategies, ['class' => 'form-select form-select-sm']) ?>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">Timeframe</label>
                        <?= Html::dropDownList('timeframe', $latestRun->timeframe, $timeframes, ['class' => 'form-select form-select-sm']) ?>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">Generasi Evolusi</label>
                        <?= Html::dropDownList('generations', 35, [20 => '20 Gen (Cepat ~15s)', 35 => '35 Gen (Standar ~30s)', 60 => '60 Gen (Mendalam ~1m)', 100 => '100 Gen (Maksimal)'], ['class' => 'form-select form-select-sm']) ?>
                    </div>
                    <div class="col-md-3 col-12 text-end">
                        <?= Html::submitButton('<i class="bi bi-play-circle-fill me-1"></i> Jalankan Ulang Pembelajaran', ['class' => 'btn btn-primary btn-sm w-100 fw-bold shadow-sm']) ?>
                    </div>
                <?= Html::endForm() ?>
            </div>
        </div>

        <!-- Tabel Riwayat Semua Sesi Latihan Saham Ini -->
        <?php if (!empty($allRunsForStock) && count($allRunsForStock) > 1): ?>
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-collection-play text-primary"></i>
                        <h6 class="fw-bold mb-0 text-dark">Riwayat Seluruh Sesi Pembelajaran <?= Html::encode($stock->symbol) ?></h6>
                    </div>
                    <span class="badge bg-secondary rounded-pill"><?= count($allRunsForStock) ?> Sesi Tercatat</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase text-secondary">
                                <tr>
                                    <th># ID</th>
                                    <th>Waktu Latihan</th>
                                    <th>Strategi</th>
                                    <th>Timeframe</th>
                                    <th class="text-center">Generasi</th>
                                    <th class="text-center">OOS Win-Rate</th>
                                    <th class="text-center">In-Sample WR</th>
                                    <th class="text-center">Profit Factor</th>
                                    <th class="text-center">Max DD</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allRunsForStock as $r): ?>
                                    <?php
                                    $isCurrent = ($latestRun && $latestRun->id === $r->id);
                                    $rowClass = $isCurrent ? 'table-primary table-opacity-10' : '';
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="font-monospace small text-muted">
                                            #<?= $r->id ?>
                                            <?php if ($isCurrent): ?>
                                                <span class="badge bg-primary ms-1" style="font-size:0.6rem;">AKTIF</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small font-monospace"><?= date('d M Y H:i:s', $r->created_at) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= Html::encode($strategies[$r->strategy_type] ?? $r->strategy_type) ?></span></td>
                                        <td><span class="badge bg-light text-secondary border"><?= match((int)$r->timeframe) { 1 => '1 Jam (1H)', 4 => '4 Jam (4H)', default => 'Daily (1D)' } ?></span></td>
                                        <td class="text-center small font-monospace"><?= $r->generation_max ?> gen</td>
                                        <td class="text-center font-monospace">
                                            <?php if ($r->oos_win_rate !== null): ?>
                                                <?php $rOosVal = (float)$r->oos_win_rate; ?>
                                                <span class="fw-bold <?= $rOosVal >= 60.0 ? 'text-success' : ($rOosVal >= 40.0 ? 'text-primary' : 'text-danger') ?>">
                                                    <?= number_format($rOosVal, 1) ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border" style="font-size:0.68rem;" title="Tidak ada sinyal entry pada periode validasi Out-of-Sample">0 Trade (OOS)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center font-monospace text-muted small"><?= $r->is_win_rate !== null ? number_format((float)$r->is_win_rate, 1) . '%' : '-' ?></td>
                                        <td class="text-center font-monospace small"><?= ($r->profit_factor !== null && (float)$r->profit_factor > 0) ? number_format((float)$r->profit_factor, 2) : '1.00' ?></td>
                                        <td class="text-center font-monospace text-danger small"><?= ($r->max_drawdown !== null && (float)$r->max_drawdown > 0) ? '-' . number_format((float)$r->max_drawdown, 1) . '%' : '0.0%' ?></td>
                                        <td class="text-center"><span class="badge bg-success" style="font-size:0.65rem;"><?= strtoupper($r->status) ?></span></td>
                                        <td class="text-end">
                                            <?php if (!$isCurrent): ?>
                                                <a href="<?= Url::to(['/stock-learning/detail', 'symbol' => $stock->symbol, 'run_id' => $r->id]) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:0.75rem;">
                                                    Buka Sesi Ini
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">Sedang Dibuka</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ECharts Script -->
<?php if (!empty($generations)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartDom = document.getElementById('<?= $chartId ?>');
    if (!chartDom || typeof echarts === 'undefined') return;

    const myChart = echarts.init(chartDom);
    const option = {
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'cross' }
        },
        legend: {
            data: ['Best Fitness', 'Avg Fitness', 'Win Rate (%)'],
            top: 0
        },
        grid: {
            left: '50',
            right: '50',
            top: '40',
            bottom: '30'
        },
        xAxis: {
            type: 'category',
            data: <?= json_encode($genNumbers) ?>,
            axisLine: { lineStyle: { color: '#cbd5e1' } },
            axisLabel: { color: '#64748b', fontSize: 11 }
        },
        yAxis: [
            {
                type: 'value',
                name: 'Fitness',
                position: 'left',
                axisLine: { lineStyle: { color: '#0284c7' } },
                splitLine: { lineStyle: { color: '#f1f5f9' } }
            },
            {
                type: 'value',
                name: 'Win Rate (%)',
                min: 0,
                max: 100,
                position: 'right',
                axisLine: { lineStyle: { color: '#10b981' } },
                splitLine: { show: false }
            }
        ],
        series: [
            {
                name: 'Best Fitness',
                type: 'line',
                data: <?= json_encode($bestFitnessData) ?>,
                smooth: true,
                lineStyle: { color: '#0284c7', width: 2.5 },
                itemStyle: { color: '#0284c7' }
            },
            {
                name: 'Avg Fitness',
                type: 'line',
                data: <?= json_encode($avgFitnessData) ?>,
                smooth: true,
                lineStyle: { color: '#94a3b8', type: 'dashed', width: 1.5 },
                itemStyle: { color: '#94a3b8' }
            },
            {
                name: 'Win Rate (%)',
                type: 'line',
                yAxisIndex: 1,
                data: <?= json_encode($winRateData) ?>,
                smooth: true,
                lineStyle: { color: '#10b981', width: 2 },
                itemStyle: { color: '#10b981' }
            }
        ]
    };

    myChart.setOption(option);
    window.addEventListener('resize', () => myChart.resize());
});
</script>
<?php endif; ?>
