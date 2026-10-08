<?php

/** @var yii\web\View $this */
/** @var array $results */
/** @var array $params */
/** @var array $stats */
/** @var array $symbols */
/** @var array $tvSymbols */
/** @var array $maPairs */
/** @var array $timeframes */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Moving Average Strategy & Position Scanner';
$this->params['breadcrumbs'][] = 'Moving Average Signals';

$symbolsStr = implode(', ', $symbols);
$tvSymbolsStr = implode(', ', $tvSymbols);

$currentGet = Yii::$app->request->get();
$filterWith = fn(array $overrides) => Url::to(array_merge(['/moving-average/index'], $currentGet, $overrides));

$badgeAction = [
    'BUY' => 'success',
    'HOLD' => 'secondary',
    'SELL' => 'danger',
    'TAKE_PROFIT' => 'warning text-dark',
    'WAIT' => 'light text-muted border',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">
            <i class="bi bi-graph-up text-primary"></i> <?= Html::encode($this->title) ?>
        </h1>
        <p class="text-muted small mb-0">
            Penentuan posisi beli (*entry*), batas risiko (*stop loss*), dan target keuntungan (*take profit*) presisi menggunakan Moving Average &amp; konfirmasi volume.
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm shadow-sm" role="group">
            <?php foreach ($timeframes as $tfKey => $tfLabel): ?>
                <?= Html::a(
                    Html::encode($tfLabel),
                    $filterWith(['timeframe' => $tfKey]),
                    ['class' => 'btn btn-sm ' . ((int) $params['timeframe'] === (int) $tfKey ? 'btn-primary' : 'btn-outline-secondary')]
                ) ?>
            <?php endforeach; ?>
        </div>
        <span class="badge bg-dark px-3 py-2 fs-7 shadow-sm">
            <?= Html::encode($maPairs[$params['maPair']] ?? 'MA20 / MA50') ?>
        </span>
    </div>
</div>

<!-- Interactive KPI Metric Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= Url::to(['/moving-average/index', 'timeframe' => $params['timeframe'], 'maPair' => $params['maPair']]) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-light text-center h-100 py-2 hover-card">
                <div class="card-body p-2">
                    <div class="text-muted small fw-semibold text-uppercase">Total Saham Terfilter</div>
                    <div class="h2 mb-0 fw-bold text-dark"><?= number_format((int) ($stats['total'] ?? 0)) ?></div>
                    <div class="small text-muted"><i class="bi bi-arrow-counterclockwise"></i> Klik untuk reset filter</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'buy_all']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-success bg-opacity-10 text-center h-100 py-2 border-start border-success border-4 hover-card <?= ($params['strategy'] === 'buy_all') ? 'ring-active' : '' ?>">
                <div class="card-body p-2">
                    <div class="text-success small fw-semibold text-uppercase">Posisi Beli (Buy Setups)</div>
                    <div class="h2 mb-0 fw-bold text-success"><?= number_format((int) ($stats['buyCount'] ?? 0)) ?></div>
                    <div class="small text-success"><i class="bi bi-check2-circle"></i> Golden Cross &amp; Rebound</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'pullback']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-primary bg-opacity-10 text-center h-100 py-2 border-start border-primary border-4 hover-card <?= ($params['strategy'] === 'pullback') ? 'ring-active' : '' ?>">
                <div class="card-body p-2">
                    <div class="text-primary small fw-semibold text-uppercase">Pullback Buy on Dip</div>
                    <div class="h2 mb-0 fw-bold text-primary"><?= number_format((int) ($stats['pullbackCount'] ?? 0)) ?></div>
                    <div class="small text-primary"><i class="bi bi-bullseye"></i> Uji Support MA (Low Risk)</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'sell_all']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-danger bg-opacity-10 text-center h-100 py-2 border-start border-danger border-4 hover-card <?= ($params['strategy'] === 'sell_all') ? 'ring-active' : '' ?>">
                <div class="card-body p-2">
                    <div class="text-danger small fw-semibold text-uppercase">Alert Jual / Take Profit</div>
                    <div class="h2 mb-0 fw-bold text-danger"><?= number_format((int) ($stats['sellCount'] ?? 0)) ?></div>
                    <div class="small text-danger"><i class="bi bi-exclamation-triangle"></i> Exit &amp; Realisasi Profit</div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Strategy Quick Presets (1-Klik) -->
<div class="card border-0 shadow-sm mb-3 bg-light">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="small fw-bold text-muted me-1"><i class="bi bi-lightning-fill text-warning"></i> Preset Strategi Cepat:</span>
                
                <?= Html::a(
                    '<i class="bi bi-grid-fill"></i> Semua Sinyal',
                    $filterWith(['strategy' => 'all']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'all' ? 'btn-dark' : 'btn-outline-dark')]
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-check-circle-fill"></i> Semua Posisi Beli',
                    $filterWith(['strategy' => 'buy_all']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'buy_all' ? 'btn-success' : 'btn-outline-success')]
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-arrow-repeat"></i> Golden Cross',
                    $filterWith(['strategy' => 'golden_cross']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'golden_cross' ? 'btn-primary' : 'btn-outline-primary')]
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-bullseye"></i> Pullback Buy (Dip)',
                    $filterWith(['strategy' => 'pullback']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'pullback' ? 'btn-info text-white' : 'btn-outline-info')]
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-graph-up-arrow"></i> MA Breakout + Vol',
                    $filterWith(['strategy' => 'breakout']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'breakout' ? 'btn-primary' : 'btn-outline-primary')]
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-currency-dollar"></i> Take Profit Climax',
                    $filterWith(['strategy' => 'take_profit']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'take_profit' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark')]
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-x-octagon-fill"></i> Death Cross (Sell)',
                    $filterWith(['strategy' => 'death_cross']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'death_cross' ? 'btn-danger' : 'btn-outline-danger')]
                ) ?>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?= Html::a('<i class="bi bi-arrow-counterclockwise"></i> Reset Filter', ['/moving-average/index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Filter Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
        <strong class="small text-secondary"><i class="bi bi-funnel"></i> Parameter Filter Moving Average</strong>
        <span class="badge bg-light text-dark border">Pengaturan Setup &amp; Rasio R:R</span>
    </div>
    <div class="card-body p-3">
        <?= Html::beginForm(['/moving-average/index'], 'get', ['class' => 'row g-2 align-items-end']) ?>
            <div class="col-md-3 col-6">
                <label class="form-label small fw-bold text-muted mb-1">Kombinasi MA (Periode)</label>
                <?= Html::dropDownList('maPair', $params['maPair'], $maPairs, ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
            </div>

            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold text-muted mb-1">Timeframe</label>
                <?= Html::dropDownList('timeframe', $params['timeframe'], $timeframes, ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
            </div>

            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold text-muted mb-1">Strategi Spesifik</label>
                <?= Html::dropDownList('strategy', $params['strategy'], [
                    'all' => 'Semua Setup Strategi',
                    'buy_all' => '🟢 Semua Setup BELI (BUY)',
                    'golden_cross' => '⭐ Golden Cross (Persilangan MA)',
                    'pullback' => '🎯 Pullback Buy on Dip (MA Support)',
                    'breakout' => '🚀 Breakout di Atas MA',
                    'sell_all' => '🔴 Semua Sinyal JUAL (SELL)',
                    'death_cross' => '⚠️ Death Cross (Bearish Exit)',
                    'take_profit' => '💰 Overextended Climax (Take Profit)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold text-muted mb-1">Min. Risk/Reward (R:R)</label>
                <?= Html::dropDownList('minRr', $params['minRr'], [
                    '0' => 'Semua R:R',
                    '1.5' => '≥ 1.5 : 1 (Standar)',
                    '2.0' => '≥ 2.0 : 1 (Ideal)',
                    '3.0' => '≥ 3.0 : 1 (Super Ratio)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-md-3 col-12">
                <label class="form-label small fw-bold text-muted mb-1">Urutan Hasil (Sorting)</label>
                <?= Html::dropDownList('sort', $params['sort'], [
                    'rr' => 'Risk/Reward Tertinggi',
                    'gain' => 'Potensi Gain TP1 Tertinggi (%)',
                    'rvol' => 'Lonjakan Volume (RVOL)',
                    'symbol' => 'Simbol Saham (A-Z)',
                    'price' => 'Harga Tertinggi',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-md-2 col-6 mt-2">
                <label class="form-label small fw-bold text-muted mb-1">Simbol</label>
                <?= Html::textInput('symbol', $params['symbol'], ['class' => 'form-control form-control-sm text-uppercase', 'placeholder' => 'Cth: NVDA']) ?>
            </div>

            <div class="col-md-2 col-6 mt-2">
                <label class="form-label small fw-bold text-muted mb-1">Bursa</label>
                <?= Html::dropDownList('exchange', $params['exchange'], [
                    '' => 'Semua Bursa',
                    'NASDAQ' => 'NASDAQ',
                    'NYSE' => 'NYSE',
                    'AMEX' => 'AMEX',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-md-5 col-12 mt-2">
                <div class="small text-muted pt-3">
                    <i class="bi bi-info-circle text-primary me-1"></i>
                    Level <strong>Stop Loss</strong> dihitung 1.5%-2.5% di bawah support MA, dan <strong>TP1/TP2</strong> dihitung otomatis berdasarkan kelipatan R:R.
                </div>
            </div>

            <div class="col-md-3 col-12 mt-2 text-end">
                <?= Html::submitButton('<i class="bi bi-search"></i> Pindai Saham', ['class' => 'btn btn-primary btn-sm px-4 fw-bold shadow-sm w-100']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- Table Results with Actions -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-table text-primary me-1"></i> Sinyal &amp; Rencana Posisi Trading
                <span class="badge bg-secondary ms-1"><?= count($results) ?> Saham Lolos</span>
            </h5>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Client-side quick filter input -->
            <div class="input-group input-group-sm" style="max-width: 220px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="ma-table-search" class="form-control form-control-sm border-start-0" placeholder="Filter di tabel...">
            </div>

            <!-- Copy Symbols Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" <?= empty($symbols) ? 'disabled' : '' ?>>
                    <i class="bi bi-clipboard"></i> Salin Simbol (<?= count($symbols) ?>)
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                    <li><h6 class="dropdown-header">Pilih Format Simbol</h6></li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0)" onclick="copySymbolsText('<?= Html::encode($symbolsStr) ?>', 'Simbol standar')">
                            <i class="bi bi-file-text me-1"></i> Standar (Cth: NVDA, AAPL)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0)" onclick="copySymbolsText('<?= Html::encode($tvSymbolsStr) ?>', 'Format TradingView')">
                            <i class="bi bi-graph-up me-1"></i> TradingView (Cth: NASDAQ:NVDA)
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Export CSV -->
            <a href="<?= Url::to(array_merge(['/moving-average/export'], $currentGet)) ?>" class="btn btn-outline-success btn-sm shadow-sm" title="Download data hasil filter dalam format CSV">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Toast Notification for Copy -->
    <div id="copy-toast" class="alert alert-dark position-fixed bottom-0 end-0 m-3 shadow py-2 px-3 fade" style="display:none; z-index:9999;" role="alert">
        <i class="bi bi-check-circle-fill text-success me-1"></i> <span id="copy-toast-text">Simbol disalin ke clipboard!</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="ma-grid-table">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 105px;">Simbol</th>
                    <th>Harga Terkini</th>
                    <th class="text-center" style="width: 120px;">Rekomendasi</th>
                    <th>Setup Strategi</th>
                    <th class="text-end text-primary" style="width: 120px;">Posisi Beli (Entry)</th>
                    <th class="text-end text-danger" style="width: 125px;">Stop Loss (SL)</th>
                    <th class="text-end text-success" style="width: 125px;">Target 1 (TP1)</th>
                    <th class="text-end text-success" style="width: 125px;">Target 2 (TP2)</th>
                    <th class="text-center" style="width: 90px;">Risk/Reward</th>
                    <th class="text-center" style="width: 85px;">RVOL</th>
                    <th class="text-center" style="width: 130px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr class="empty">
                        <td colspan="12" class="text-center py-5">
                            <div class="display-6 text-muted mb-2"><i class="bi bi-funnel"></i></div>
                            <h5 class="fw-bold text-secondary">Tidak ada saham yang cocok dengan kriteria filter</h5>
                            <p class="text-muted small mb-3">Coba longgarkan filter strategi atau pilih timeframe yang berbeda.</p>
                            <a href="<?= Url::to(['/moving-average/index']) ?>" class="btn btn-primary btn-sm px-3 shadow-sm">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset Semua Filter
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $idx => $r): ?>
                        <?php
                        $act = $r['action'];
                        $rowClass = match ($act) {
                            'BUY' => 'table-success bg-opacity-10',
                            'SELL' => 'table-danger bg-opacity-10',
                            'TAKE_PROFIT' => 'table-warning bg-opacity-10',
                            default => '',
                        };
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="text-muted small"><?= $idx + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <?= Html::a(
                                        Html::encode($r['symbol']),
                                        ['/moving-average/detail', 'symbol' => $r['symbol']],
                                        ['class' => 'fw-bold text-primary text-decoration-none fs-6']
                                    ) ?>
                                    <?php if (!empty($r['exchange'])): ?>
                                        <span class="badge bg-light text-secondary border py-0 px-1" style="font-size:0.65rem;"><?= Html::encode($r['exchange']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small text-truncate" style="max-width: 140px; font-size: 0.72rem;">
                                    <?= Html::encode($r['name'] ?? $r['sector'] ?? '') ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= Yii::$app->formatter->asCurrency($r['current_price']) ?></div>
                                <div class="small text-muted" style="font-size: 0.72rem;">
                                    Fast MA: <?= Yii::$app->formatter->asCurrency($r['fast_ma']) ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $badgeAction[$act] ?? 'secondary' ?> px-2 py-1 shadow-sm">
                                    <?= Html::encode($act) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark small"><?= Html::encode($r['strategy_name']) ?></div>
                                <div class="text-muted small" style="font-size: 0.73rem;">
                                    Jarak MA: <span class="<?= $r['dist_fast_pct'] >= 0 ? 'text-success' : 'text-danger' ?> fw-bold"><?= ($r['dist_fast_pct'] > 0 ? '+' : '') . $r['dist_fast_pct'] ?>%</span>
                                    &bull; Tren: <strong><?= Html::encode($r['trend_status']) ?></strong>
                                </div>
                            </td>
                            <td class="text-end">
                                <?php if ($act === 'BUY' || $act === 'HOLD'): ?>
                                    <div class="fw-bold text-primary"><?= Yii::$app->formatter->asCurrency($r['entry_price']) ?></div>
                                    <div class="text-muted small" style="font-size: 0.72rem;"><?= Html::encode($r['entry_range']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($act === 'BUY' || $act === 'HOLD'): ?>
                                    <div class="fw-bold text-danger"><?= Yii::$app->formatter->asCurrency($r['stop_loss']) ?></div>
                                    <div class="text-danger small" style="font-size: 0.72rem;">-<?= $r['stop_loss_pct'] ?>% Risk</div>
                                <?php else: ?>
                                    <span class="badge bg-danger text-white">Exit / Sell</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($act === 'BUY' || $act === 'HOLD'): ?>
                                    <div class="fw-bold text-success"><?= Yii::$app->formatter->asCurrency($r['target_1']) ?></div>
                                    <div class="text-success small" style="font-size: 0.72rem;">+<?= $r['target_1_pct'] ?>% Gain</div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($act === 'BUY' || $act === 'HOLD'): ?>
                                    <div class="fw-bold text-success"><?= Yii::$app->formatter->asCurrency($r['target_2']) ?></div>
                                    <div class="text-success small" style="font-size: 0.72rem;">+<?= $r['target_2_pct'] ?>% Gain</div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (($r['rr_ratio'] ?? 0) > 0): ?>
                                    <span class="badge bg-<?= $r['rr_ratio'] >= 2.0 ? 'success' : 'primary' ?> px-2 py-1">
                                        <?= $r['rr_ratio'] ?>x
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($r['rvol'] >= 1.5): ?>
                                    <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-fire"></i> <?= $r['rvol'] ?>x</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border"><?= $r['rvol'] ?>x</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <?= Html::a(
                                        '<i class="bi bi-eye"></i> Plan',
                                        ['/moving-average/detail', 'symbol' => $r['symbol']],
                                        ['class' => 'btn btn-primary py-0 px-2 fw-semibold', 'title' => 'Lihat Rencana Trading Presisi & Chart MA']
                                    ) ?>
                                    <?= Html::a(
                                        '<i class="bi bi-graph-up"></i>',
                                        ['/stock/view', 'symbol' => $r['symbol']],
                                        ['class' => 'btn btn-outline-secondary py-0 px-2', 'title' => 'Detail Saham Fundamental']
                                    ) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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
.ring-active {
    box-shadow: 0 0 0 2px #0d6efd !important;
}
</style>

<script>
// Live client-side instant filtering on the table
document.getElementById('ma-table-search')?.addEventListener('input', function () {
    const filter = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#ma-grid-table tbody tr');
    rows.forEach(row => {
        if (row.classList.contains('empty')) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

// Copy symbols to clipboard with feedback
function copySymbolsText(text, label) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(function() {
        const toast = document.getElementById('copy-toast');
        const toastText = document.getElementById('copy-toast-text');
        if (toast && toastText) {
            toastText.textContent = label + ' berhasil disalin ke clipboard!';
            toast.style.display = 'block';
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => { toast.style.display = 'none'; }, 200);
            }, 2500);
        }
    }).catch(function(err) {
        alert('Gagal menyalin: ' + err);
    });
}
</script>
