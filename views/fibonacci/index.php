<?php

/** @var yii\web\View $this */
/** @var array $results */
/** @var array $params */
/** @var array $stats */
/** @var array $symbols */
/** @var array $tvSymbols */
/** @var array $timeframes */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Fibonacci Retracement & Extension Scanner';
$this->params['breadcrumbs'][] = 'Fibonacci Strategy & Levels';

$symbolsStr = implode(', ', $symbols);
$tvSymbolsStr = implode(', ', $tvSymbols);

$currentGet = Yii::$app->request->get();
$filterWith = fn(array $overrides) => Url::to(array_merge(['/fibonacci/index'], $currentGet, $overrides));

$badgeAction = [
    'BUY' => 'success',
    'SELL' => 'danger',
    'TAKE_PROFIT' => 'warning text-dark',
    'WAIT' => 'light text-muted border',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">
            <i class="bi bi-bullseye text-primary"></i> <?= Html::encode($this->title) ?>
        </h1>
        <p class="text-muted small mb-0">
            Penentuan <strong>waktu beli terbaik</strong> (Golden Pocket 50% &ndash; 61.8%) &amp; <strong>waktu jual terbaik</strong> (Target Ekstensi 1.272 &amp; 1.618 atau Stop Loss) dengan konfirmasi volume.
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
            TF: <?= Html::encode($timeframes[$params['timeframe']] ?? '1D') ?>
        </span>
    </div>
</div>

<!-- Interactive KPI Metric Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= Url::to(['/fibonacci/index', 'timeframe' => $params['timeframe']]) ?>" class="text-decoration-none">
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
        <a href="<?= $filterWith(['strategy' => 'golden_pocket']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-success bg-opacity-10 text-center h-100 py-2 border-start border-success border-4 hover-card <?= ($params['strategy'] === 'golden_pocket') ? 'ring-active' : '' ?>">
                <div class="card-body p-2">
                    <div class="text-success small fw-semibold text-uppercase">⭐ Golden Pocket Buys</div>
                    <div class="h2 mb-0 fw-bold text-success"><?= number_format((int) ($stats['goldenPocketCount'] ?? 0)) ?></div>
                    <div class="small text-success"><i class="bi bi-star-fill"></i> Waktu Beli Terbaik (50%-61.8%)</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'target_reached']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-warning bg-opacity-10 text-center h-100 py-2 border-start border-warning border-4 hover-card <?= ($params['strategy'] === 'target_reached') ? 'ring-active' : '' ?>">
                <div class="card-body p-2">
                    <div class="text-warning-emphasis small fw-semibold text-uppercase">🎯 Target Ekstensi Tercapai</div>
                    <div class="h2 mb-0 fw-bold text-dark"><?= number_format((int) ($stats['targetReachedCount'] ?? 0)) ?></div>
                    <div class="small text-warning-emphasis"><i class="bi bi-trophy-fill"></i> Waktu Jual TP (1.272 / 1.618)</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'breakdown']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-danger bg-opacity-10 text-center h-100 py-2 border-start border-danger border-4 hover-card <?= ($params['strategy'] === 'breakdown') ? 'ring-active' : '' ?>">
                <div class="card-body p-2">
                    <div class="text-danger small fw-semibold text-uppercase">⚠️ Alert Jual / Cut Loss</div>
                    <div class="h2 mb-0 fw-bold text-danger"><?= number_format((int) ($stats['breakdownCount'] ?? 0)) ?></div>
                    <div class="small text-danger"><i class="bi bi-shield-x"></i> Breakdown Support (&lt; 78.6%)</div>
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
                    '<i class="bi bi-star-fill text-warning"></i> Golden Pocket (50%-61.8%)',
                    $filterWith(['strategy' => 'golden_pocket']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'golden_pocket' ? 'btn-success text-white' : 'btn-outline-success')]
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-arrow-down-right"></i> Pullback 38.2% Dip',
                    $filterWith(['strategy' => 'pullback_382']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'pullback_382' ? 'btn-primary' : 'btn-outline-primary')]
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-rocket-takeoff"></i> Breakout Swing High',
                    $filterWith(['strategy' => 'breakout_high']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'breakout_high' ? 'btn-info text-white' : 'btn-outline-info')]
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-cash-stack"></i> Waktu Jual / TP Tercapai',
                    $filterWith(['strategy' => 'target_reached']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'target_reached' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark')]
                ) ?>

                <?= Html::a(
                    '<i class="bi bi-shield-slash"></i> Alert Jual / Breakdown',
                    $filterWith(['strategy' => 'breakdown']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'breakdown' ? 'btn-danger' : 'btn-outline-danger')]
                ) ?>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-clipboard"></i> Salin Simbol (<?= count($symbols) ?>)
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li>
                            <button class="dropdown-item small" type="button" onclick="copyToClipboard('<?= Html::encode($tvSymbolsStr) ?>', 'TradingView')">
                                <i class="bi bi-tv me-1 text-primary"></i> Format TradingView (<?= count($tvSymbols) ?>)
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item small" type="button" onclick="copyToClipboard('<?= Html::encode($symbolsStr) ?>', 'Biasa')">
                                <i class="bi bi-card-text me-1 text-success"></i> Format Simbol Biasa (<?= count($symbols) ?>)
                            </button>
                        </li>
                    </ul>
                </div>

                <?= Html::a(
                    '<i class="bi bi-download"></i> Ekspor CSV',
                    Url::to(array_merge(['/fibonacci/export'], $currentGet)),
                    ['class' => 'btn btn-sm btn-outline-primary shadow-sm']
                ) ?>
            </div>
        </div>
    </div>
</div>

<!-- Form Filter & Pencarian Lengkap -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <?= Html::beginForm(['/fibonacci/index'], 'get', ['class' => 'row g-2 align-items-end', 'id' => 'filter-form']) ?>
            
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Cari Simbol Saham</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <?= Html::textInput('symbol', $params['symbol'] ?? '', [
                        'class' => 'form-control',
                        'placeholder' => 'Contoh: NVDA, AAPL, BBCA',
                    ]) ?>
                </div>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Timeframe</label>
                <?= Html::dropDownList('timeframe', $params['timeframe'], $timeframes, [
                    'class' => 'form-select form-select-sm',
                    'onchange' => 'this.form.submit()',
                ]) ?>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Strategi Setup</label>
                <?= Html::dropDownList('strategy', $params['strategy'], [
                    'all' => 'Semua Sinyal',
                    'buy_all' => 'Semua Posisi Beli',
                    'golden_pocket' => '⭐ Golden Pocket (50%-61.8%)',
                    'pullback_382' => 'Pullback 38.2% Dip',
                    'breakout_high' => 'Breakout Swing High',
                    'sell_all' => 'Semua Alert Jual / TP',
                    'target_reached' => '🎯 Ekstensi 1.272 / 1.618 Tercapai',
                    'breakdown' => '⚠️ Breakdown Cut Loss',
                ], ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Min. Risk:Reward</label>
                <?= Html::dropDownList('minRr', (string) $params['minRr'], [
                    '0' => 'Semua R:R',
                    '1.5' => '≥ 1.5x (Moderat)',
                    '2.0' => '≥ 2.0x (Direkomendasikan)',
                    '3.0' => '≥ 3.0x (Sangat Tinggi)',
                ], ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Urutkan Berdasarkan</label>
                <?= Html::dropDownList('sort', $params['sort'], [
                    'rr' => 'R:R Tertinggi',
                    'gain' => 'Potensi Gain TP1 Terbesar',
                    'retrace' => 'Retracement Terdalam',
                    'rvol' => 'Volume RVOL Terbesar',
                    'symbol' => 'Simbol (A-Z)',
                    'price' => 'Harga Tertinggi',
                ], ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
            </div>

            <div class="col-12 col-md-1 d-flex gap-1">
                <?= Html::submitButton('<i class="bi bi-funnel-fill"></i> Filter', ['class' => 'btn btn-sm btn-primary w-100']) ?>
            </div>

        <?= Html::endForm() ?>
    </div>
</div>

<!-- Live Instant Filter Input -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="text-muted small">
        Menampilkan <strong><?= count($results) ?></strong> saham hasil scanner Fibonacci
    </div>
    <div style="max-width: 280px;" class="w-100">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-filter"></i></span>
            <input type="text" id="tableSearchInput" class="form-control" placeholder="Ketik untuk filter cepat di tabel...">
        </div>
    </div>
</div>

<!-- Table Results -->
<div class="card border-0 shadow-sm overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="fib-grid-table">
            <thead class="table-dark text-nowrap">
                <tr>
                    <th>Simbol</th>
                    <th>Harga Saat Ini</th>
                    <th>Retracement</th>
                    <th>Sinyal &amp; Rekomendasi Timing</th>
                    <th>Posisi Beli Presisi (Entry)</th>
                    <th>Stop Loss</th>
                    <th>TP1 (Swing High)</th>
                    <th>TP2 (1.272 Ext)</th>
                    <th>TP3 (1.618 Ext)</th>
                    <th>R:R</th>
                    <th>RVOL</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="12" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                            <strong>Tidak ditemukan saham yang cocok dengan kriteria filter Fibonacci saat ini.</strong>
                            <p class="small text-muted mb-0">Coba ubah opsi strategi ke <em>"Semua Sinyal"</em> atau pilih timeframe lainnya.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $r): ?>
                        <?php
                        $act = $r['action'];
                        $actBadge = $badgeAction[$act] ?? 'secondary';
                        $isGolden = ($r['signal'] === \app\services\FibonacciService::SIGNAL_GOLDEN_POCKET);
                        ?>
                        <tr class="<?= $isGolden ? 'table-success bg-opacity-25' : '' ?>" data-symbol="<?= Html::encode($r['symbol']) ?>">
                            <td class="fw-bold">
                                <?= Html::a(
                                    Html::encode($r['symbol']),
                                    ['/fibonacci/detail', 'symbol' => $r['symbol'], 'timeframe' => $params['timeframe']],
                                    ['class' => 'text-decoration-none text-primary fs-6']
                                ) ?>
                                <span class="d-block small text-muted text-truncate" style="max-width: 140px;" title="<?= Html::encode($r['name']) ?>">
                                    <?= Html::encode($r['name']) ?>
                                </span>
                            </td>
                            <td>
                                <strong class="fs-6 text-dark">$<?= number_format($r['current_price'], 2) ?></strong>
                                <span class="d-block text-muted" style="font-size: 0.72rem;">
                                    Range: $<?= number_format($r['swing_low'], 2) ?> - $<?= number_format($r['swing_high'], 2) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="badge bg-light text-dark border fw-semibold">
                                        <?= number_format($r['retrace_pct'], 1) ?>%
                                    </span>
                                </div>
                                <span class="d-block text-muted" style="font-size: 0.7rem;">
                                    <?= ($r['retrace_pct'] <= 50) ? 'Koreksi Sehat' : (($r['retrace_pct'] <= 65) ? 'Golden Area' : 'Deep Discount') ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-<?= $actBadge ?> px-2 py-1">
                                        <?= Html::encode($act) ?>
                                    </span>
                                    <span class="badge bg-<?= $r['strategy_badge'] ?> text-truncate" style="max-width: 190px;" title="<?= Html::encode($r['strategy_name']) ?>">
                                        <?= Html::encode($r['strategy_name']) ?>
                                    </span>
                                </div>
                                <span class="small fw-semibold <?= ($act === 'BUY') ? 'text-success' : (($act === 'TAKE_PROFIT') ? 'text-warning-emphasis' : 'text-danger') ?>">
                                    <?= Html::encode($r['timing_recommendation']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-primary">
                                    <?= Html::encode($r['entry_range']) ?>
                                </div>
                                <span class="text-muted" style="font-size: 0.72rem;">
                                    Optimal: $<?= number_format($r['entry_price'], 2) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-danger">
                                    $<?= number_format($r['stop_loss'], 2) ?>
                                </div>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size: 0.7rem;">
                                    -<?= number_format($r['stop_loss_pct'], 1) ?>%
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-success">
                                    $<?= number_format($r['target_1'], 2) ?>
                                </div>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.7rem;">
                                    +<?= number_format($r['target_1_pct'], 1) ?>%
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    $<?= number_format($r['target_2'], 2) ?>
                                </div>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size: 0.7rem;">
                                    +<?= number_format($r['target_2_pct'], 1) ?>%
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    $<?= number_format($r['target_3'], 2) ?>
                                </div>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size: 0.7rem;">
                                    +<?= number_format($r['target_3_pct'], 1) ?>%
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= ($r['rr_ratio'] >= 2.0) ? 'bg-success' : (($r['rr_ratio'] >= 1.5) ? 'bg-primary' : 'bg-secondary') ?> px-2 py-1 fs-7">
                                    <?= number_format($r['rr_ratio'], 1) ?>x
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= ($r['rvol'] >= 1.5) ? 'bg-primary' : 'bg-light text-dark border' ?>">
                                    <?= number_format($r['rvol'], 2) ?>x
                                </span>
                            </td>
                            <td class="text-center text-nowrap">
                                <?= Html::a(
                                    '<i class="bi bi-compass"></i> Trading Plan',
                                    ['/fibonacci/detail', 'symbol' => $r['symbol'], 'timeframe' => $params['timeframe']],
                                    ['class' => 'btn btn-sm btn-outline-primary shadow-sm']
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Floating Copy Notification Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
    <div id="copyToast" class="toast align-items-center text-white bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body small" id="copyToastMsg">
                <i class="bi bi-check2-circle text-success me-1"></i> Simbol disalin ke clipboard!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<style>
.hover-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.hover-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1) !important;
}
.ring-active {
    box-shadow: 0 0 0 2px #0d6efd !important;
}
.fs-7 {
    font-size: 0.8rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live table search
    const searchInput = document.getElementById('tableSearchInput');
    const table = document.getElementById('fib-grid-table');
    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                if (row.querySelector('td[colspan]')) return;
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
});

function copyToClipboard(text, label) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(function() {
        const msgEl = document.getElementById('copyToastMsg');
        if (msgEl) {
            msgEl.innerHTML = '<i class="bi bi-check2-circle text-success me-1"></i> Berhasil menyalin ' + label + ' ke clipboard!';
        }
        const toastEl = document.getElementById('copyToast');
        if (toastEl && window.bootstrap) {
            const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
            toast.show();
        }
    }).catch(function(err) {
        alert('Gagal menyalin: ' + err);
    });
}
</script>
