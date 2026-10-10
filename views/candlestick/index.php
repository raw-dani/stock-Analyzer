<?php

/** @var yii\web\View $this */
/** @var array $results */
/** @var array $params */
/** @var array $stats */
/** @var array $symbols */
/** @var array $tvSymbols */
/** @var array $timeframes */
/** @var array $patterns */
/** @var array $sectors */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Scanner Pola Candlestick & Konfirmasi Volume';
$this->params['breadcrumbs'][] = 'Candlestick Scanner';

$symbolsStr = implode(', ', $symbols);
$tvSymbolsStr = implode(', ', $tvSymbols);

$currentGet = Yii::$app->request->get();
$filterWith = fn(array $overrides) => Url::to(array_merge(['/candlestick/index'], $currentGet, $overrides));

$badgeAction = [
    'STRONG_BUY' => 'success',
    'BUY' => 'success',
    'WATCH' => 'warning text-dark',
    'SELL' => 'danger',
    'STRONG_SELL' => 'danger',
];
?>

<style>
.hover-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
}
.hover-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12) !important;
}
.ring-active {
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.35) !important;
}
.table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.04);
}
.candlestick-badge {
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}
.progress-confidence {
    height: 7px;
    border-radius: 4px;
    background-color: #e9ecef;
}
</style>

<!-- Header Title & Timeframe Selector -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">
            <i class="bi bi-bar-chart-steps text-primary"></i> <?= Html::encode($this->title) ?>
        </h1>
        <p class="text-muted small mb-0">
            Analisa pola potensial (<strong>Hammer, Shooting Star, Engulfing, Doji, Marubozu</strong>) untuk menentukan waktu beli &amp; jual presisi dengan <strong>konfirmasi volume multi-timeframe</strong>.
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm shadow-sm" role="group" id="tf-button-group">
            <?php foreach ($timeframes as $tfKey => $tfLabel): ?>
                <?= Html::a(
                    Html::encode($tfLabel),
                    $filterWith(['timeframe' => $tfKey]),
                    ['class' => 'btn btn-sm ' . ((int) $params['timeframe'] === (int) $tfKey ? 'btn-primary active' : 'btn-outline-secondary')]
                ) ?>
            <?php endforeach; ?>
        </div>
        <span class="badge bg-dark px-3 py-2 fs-7 shadow-sm">
            TF Aktif: <?= Html::encode($timeframes[$params['timeframe']] ?? '1D') ?>
        </span>
    </div>
</div>

<!-- Interactive KPI Metric Summary Cards -->
<div class="row g-3 mb-4">
    <!-- Card 1: Total Pola -->
    <div class="col-6 col-lg-2">
        <a href="<?= Url::to(['/candlestick/index', 'timeframe' => $params['timeframe']]) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-light text-center h-100 py-2 hover-card">
                <div class="card-body p-2">
                    <div class="text-muted small fw-semibold text-uppercase">Total Saham</div>
                    <div class="h2 mb-0 fw-bold text-dark"><?= number_format((int) ($stats['total'] ?? 0)) ?></div>
                    <div class="small text-muted" style="font-size: 0.72rem;"><i class="bi bi-arrow-counterclockwise"></i> Reset filter</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 2: Sinyal Beli (Buy Setups) -->
    <div class="col-6 col-lg-2">
        <a href="<?= $filterWith(['action' => 'buy_only', 'preset' => '']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-success bg-opacity-10 text-center h-100 py-2 border-start border-success border-4 hover-card <?= ($params['action'] === 'buy_only') ? 'ring-active' : '' ?>">
                <div class="card-body p-2">
                    <div class="text-success small fw-semibold text-uppercase">Waktu Beli (Buys)</div>
                    <div class="h2 mb-0 fw-bold text-success"><?= number_format((int) ($stats['buyCount'] ?? 0)) ?></div>
                    <div class="small text-success" style="font-size: 0.72rem;"><i class="bi bi-cart-check"></i> Reversal &amp; Breakout</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 3: Volume Surge (Konfirmasi Sangat Kuat) -->
    <div class="col-6 col-lg-2">
        <a href="<?= $filterWith(['volume_filter' => 'surge_only', 'preset' => '']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-primary bg-opacity-10 text-center h-100 py-2 border-start border-primary border-4 hover-card <?= ($params['volume_filter'] === 'surge_only') ? 'ring-active' : '' ?>">
                <div class="card-body p-2">
                    <div class="text-primary small fw-semibold text-uppercase">Volume Surge 🚀</div>
                    <div class="h2 mb-0 fw-bold text-primary"><?= number_format((int) ($stats['surgeCount'] ?? 0)) ?></div>
                    <div class="small text-primary" style="font-size: 0.72rem;"><i class="bi bi-lightning-charge-fill"></i> RVOL &ge; 2.0x SMA20</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 4: Volume Mendukung Kenaikan -->
    <div class="col-6 col-lg-2">
        <a href="<?= $filterWith(['volume_filter' => 'supportive_only', 'preset' => '']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-info bg-opacity-10 text-center h-100 py-2 border-start border-info border-4 hover-card <?= ($params['volume_filter'] === 'supportive_only') ? 'ring-active' : '' ?>">
                <div class="card-body p-2">
                    <div class="text-info-emphasis small fw-semibold text-uppercase">Volume Mendukung</div>
                    <div class="h2 mb-0 fw-bold text-dark"><?= number_format((int) ($stats['supportiveCount'] ?? 0)) ?></div>
                    <div class="small text-info-emphasis" style="font-size: 0.72rem;"><i class="bi bi-check2-circle"></i> Akumulasi Positif</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 5: Hammer / Pin Reversal -->
    <div class="col-6 col-lg-2">
        <a href="<?= $filterWith(['pattern' => 'HAMMER', 'preset' => '']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-warning bg-opacity-10 text-center h-100 py-2 border-start border-warning border-4 hover-card <?= ($params['pattern'] === 'HAMMER') ? 'ring-active' : '' ?>">
                <div class="card-body p-2">
                    <div class="text-warning-emphasis small fw-semibold text-uppercase">Hammer 🔨</div>
                    <div class="h2 mb-0 fw-bold text-dark"><?= number_format((int) ($stats['hammerCount'] ?? 0)) ?></div>
                    <div class="small text-warning-emphasis" style="font-size: 0.72rem;"><i class="bi bi-shield-check"></i> Reversal at Dip</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 6: Alert Jual (Sell Signals) -->
    <div class="col-6 col-lg-2">
        <a href="<?= $filterWith(['action' => 'sell_only', 'preset' => '']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-danger bg-opacity-10 text-center h-100 py-2 border-start border-danger border-4 hover-card <?= ($params['action'] === 'sell_only') ? 'ring-active' : '' ?>">
                <div class="card-body p-2">
                    <div class="text-danger small fw-semibold text-uppercase">Alert Jual ⭐/🔴</div>
                    <div class="h2 mb-0 fw-bold text-danger"><?= number_format((int) ($stats['sellCount'] ?? 0)) ?></div>
                    <div class="small text-danger" style="font-size: 0.72rem;"><i class="bi bi-exclamation-octagon"></i> Shooting Star / Exit</div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Strategy Quick Presets (1-Klik Filter Cepat) -->
<div class="card border-0 shadow-sm mb-3 bg-light">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="small fw-bold text-muted me-1"><i class="bi bi-lightning-fill text-warning"></i> Preset Cepat:</span>

                <?= Html::a(
                    '<i class="bi bi-grid-fill"></i> Semua Sinyal',
                    $filterWith(['preset' => '', 'pattern' => 'all', 'volume_filter' => 'all', 'action' => 'all']),
                    ['class' => 'btn btn-sm ' . (empty($params['preset']) && $params['pattern'] === 'all' && $params['volume_filter'] === 'all' && $params['action'] === 'all' ? 'btn-dark' : 'btn-outline-dark')]
                ) ?>

                <?= Html::a(
                    '🔨 Waktu Beli Hammer',
                    $filterWith(['preset' => 'buy_hammer']),
                    ['class' => 'btn btn-sm ' . ($params['preset'] === 'buy_hammer' ? 'btn-success text-white' : 'btn-outline-success')]
                ) ?>

                <?= Html::a(
                    '🟢 Bullish Engulfing',
                    $filterWith(['preset' => 'buy_engulfing']),
                    ['class' => 'btn btn-sm ' . ($params['preset'] === 'buy_engulfing' ? 'btn-success text-white' : 'btn-outline-success')]
                ) ?>

                <?= Html::a(
                    '⚡ Momentum Marubozu',
                    $filterWith(['preset' => 'momentum_marubozu']),
                    ['class' => 'btn btn-sm ' . ($params['preset'] === 'momentum_marubozu' ? 'btn-primary text-white' : 'btn-outline-primary')]
                ) ?>

                <?= Html::a(
                    '🚀 Lonjakan Volume (Surge)',
                    $filterWith(['preset' => 'volume_surge']),
                    ['class' => 'btn btn-sm ' . ($params['preset'] === 'volume_surge' ? 'btn-info text-white' : 'btn-outline-info')]
                ) ?>

                <?= Html::a(
                    '✅ Volume Mendukung Naik',
                    $filterWith(['preset' => 'supportive_vol']),
                    ['class' => 'btn btn-sm ' . ($params['preset'] === 'supportive_vol' ? 'btn-info text-white' : 'btn-outline-info')]
                ) ?>

                <?= Html::a(
                    '✝️ Doji (Pantau Breakout)',
                    $filterWith(['preset' => 'doji_watch']),
                    ['class' => 'btn btn-sm ' . ($params['preset'] === 'doji_watch' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark')]
                ) ?>

                <?= Html::a(
                    '🔻 Waktu Jual / Exit',
                    $filterWith(['preset' => 'sell_alerts']),
                    ['class' => 'btn btn-sm ' . ($params['preset'] === 'sell_alerts' ? 'btn-danger' : 'btn-outline-danger')]
                ) ?>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown" id="btn-copy-dropdown">
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
                    Url::to(array_merge(['/candlestick/export'], $currentGet)),
                    ['class' => 'btn btn-sm btn-outline-primary shadow-sm', 'id' => 'btn-export-csv']
                ) ?>
            </div>
        </div>
    </div>
</div>

<!-- Form Filter & Pencarian Lengkap -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <?= Html::beginForm(['/candlestick/index'], 'get', ['class' => 'row g-2 align-items-end', 'id' => 'candlestick-filter-form']) ?>
            <?= Html::hiddenInput('timeframe', $params['timeframe']) ?>

            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold mb-1">Cari Simbol Ticker</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <?= Html::textInput('symbol', $params['symbol'], [
                        'class' => 'form-control',
                        'placeholder' => 'Misal: AAPL, NVDA...',
                        'id' => 'input-search-symbol'
                    ]) ?>
                </div>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Pola Candlestick</label>
                <?= Html::dropDownList('pattern', $params['pattern'], array_merge(['all' => '— Semua Pola —'], $patterns), [
                    'class' => 'form-select form-select-sm',
                    'id' => 'select-pattern'
                ]) ?>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Konfirmasi Volume</label>
                <?= Html::dropDownList('volume_filter', $params['volume_filter'], [
                    'all' => '— Semua Kondisi Volume —',
                    'supportive_only' => '✅ Mendukung Kenaikan Saham',
                    'surge_only' => '🚀 Lonjakan Volume Surge (RVOL &ge; 2.0x)',
                ], [
                    'class' => 'form-select form-select-sm',
                    'id' => 'select-volume-filter'
                ]) ?>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Sinyal / Aksi</label>
                <?= Html::dropDownList('action', $params['action'], [
                    'all' => '— Semua Sinyal Aksi —',
                    'buy_only' => '🛒 Waktu Beli (BUY / Strong Buy)',
                    'sell_only' => '🔻 Waktu Jual (SELL / Exit)',
                    'watch_only' => '👁️ Pantau (WATCH / Doji)',
                ], [
                    'class' => 'form-select form-select-sm',
                    'id' => 'select-action-filter'
                ]) ?>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Sektor</label>
                <?= Html::dropDownList('sector', $params['sector'], array_merge(['' => '— Semua Sektor —'], array_combine($sectors, $sectors)), [
                    'class' => 'form-select form-select-sm',
                    'id' => 'select-sector-filter'
                ]) ?>
            </div>

            <div class="col-6 col-md-1">
                <label class="form-label small fw-semibold mb-1">Urutkan</label>
                <?= Html::dropDownList('sort', $params['sort'], [
                    'confidence_desc' => 'Keyakinan Tertinggi',
                    'rvol_desc' => 'Volume Tertinggi (RVOL)',
                    'change_desc' => 'Perubahan % Tertinggi',
                    'change_asc' => 'Perubahan % Terendah',
                    'rr_desc' => 'R:R Ratio Terbaik',
                    'symbol_asc' => 'Simbol A-Z',
                ], [
                    'class' => 'form-select form-select-sm',
                    'id' => 'select-sort-filter'
                ]) ?>
            </div>

            <div class="col-6 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100 shadow-sm" id="btn-submit-filter">
                    <i class="bi bi-funnel-fill"></i> Filter
                </button>
                <a href="<?= Url::to(['/candlestick/index', 'timeframe' => $params['timeframe']]) ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filter" id="btn-reset-filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- Scanner Results Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-table text-primary me-1"></i> Hasil Analisa Pola &amp; Sinyal Trading (<?= count($results) ?> Saham)
            </h5>
            <small class="text-muted">
                Peringkat diurutkan berdasarkan parameter pencarian aktif. Klik pada baris saham untuk membuka chart interaktif &amp; kalkulator trading plan.
            </small>
        </div>
        <div class="badge bg-light text-dark border px-2 py-1">
            Mode TF: <strong><?= Html::encode($timeframes[$params['timeframe']] ?? '1D') ?></strong>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="candlestick-results-table">
            <thead class="table-light text-muted small text-uppercase">
                <tr>
                    <th style="min-width: 140px;">Saham &amp; Sektor</th>
                    <th style="min-width: 170px;">Pola Candlestick</th>
                    <th style="min-width: 120px;">Sinyal / Aksi</th>
                    <th style="min-width: 110px;">Harga Terakhir</th>
                    <th style="min-width: 190px;">Konfirmasi Volume</th>
                    <th style="min-width: 180px;">Rencana Trading (Entry &bull; SL &bull; TP1)</th>
                    <th style="min-width: 110px;">Skor Keyakinan</th>
                    <th class="text-center" style="min-width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                                <strong>Tidak ditemukan pola candlestick yang cocok dengan filter saat ini.</strong>
                                <p class="small mb-3">Coba ganti pilihan pola, pilih time frame lain (1D, 4H, 2H, 1H), atau reset filter.</p>
                                <a href="<?= Url::to(['/candlestick/index', 'timeframe' => $params['timeframe']]) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset Semua Filter
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $r): ?>
                        <?php
                        $action = $r['action'];
                        $actionBadge = $badgeAction[$action] ?? 'secondary';
                        $volSupports = $r['volume_supports_bullish'];
                        ?>
                        <tr>
                            <!-- 1. Saham & Sektor -->
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <a href="<?= Url::to(['/candlestick/detail', 'symbol' => $r['symbol'], 'timeframe' => $params['timeframe']]) ?>" class="fw-bold text-dark text-decoration-none fs-6">
                                        <?= Html::encode($r['symbol']) ?>
                                    </a>
                                    <span class="badge bg-light text-secondary border px-1" style="font-size: 0.65rem;"><?= Html::encode($r['exchange']) ?></span>
                                    <?php if (!empty($r['is_fallback_daily'])): ?>
                                        <span class="badge bg-warning text-dark" style="font-size: 0.6rem;" title="Data intraday belum tersedia, analisis menggunakan data daily fallback">1D fallback</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted text-truncate" style="font-size: 0.75rem; max-width: 130px;" title="<?= Html::encode($r['name']) ?>">
                                    <?= Html::encode($r['name']) ?>
                                </div>
                                <span class="badge bg-light text-muted border-0 p-0" style="font-size: 0.68rem;">
                                    <?= Html::encode($r['sector']) ?>
                                </span>
                            </td>

                            <!-- 2. Pola Candlestick -->
                            <td>
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <span class="badge bg-<?= $r['pattern_badge'] ?> candlestick-badge shadow-sm">
                                        <?= $r['pattern_icon'] ?> <?= Html::encode($r['pattern_name']) ?>
                                    </span>
                                    <?php if ($r['bar_offset'] > 0): ?>
                                        <span class="badge bg-light text-muted border" style="font-size: 0.65rem;">
                                            <?= $r['bar_offset'] ?> bar lalu
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-secondary small fw-medium" style="font-size: 0.74rem;">
                                    <?= Html::encode($r['pattern_subtype']) ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.68rem;">
                                    <i class="bi bi-clock"></i> <?= Html::encode($r['bar_date']) ?>
                                </div>
                            </td>

                            <!-- 3. Sinyal / Waktu Aksi -->
                            <td>
                                <span class="badge bg-<?= $actionBadge ?> px-2 py-1 shadow-sm fs-7">
                                    <?php if (str_contains($action, 'BUY')): ?>
                                        <i class="bi bi-check2-circle me-1"></i>
                                    <?php elseif (str_contains($action, 'SELL')): ?>
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                    <?php else: ?>
                                        <i class="bi bi-eye me-1"></i>
                                    <?php endif; ?>
                                    <?= Html::encode($action) ?>
                                </span>
                                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                                    <?php if (str_contains($action, 'BUY')): ?>
                                        <span class="text-success fw-semibold">Waktu Beli Tepat</span>
                                    <?php elseif (str_contains($action, 'SELL')): ?>
                                        <span class="text-danger fw-semibold">Waktu Jual / Exit</span>
                                    <?php else: ?>
                                        <span class="text-warning-emphasis fw-semibold">Pantau Breakout</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- 4. Harga Terakhir & Chg % -->
                            <td>
                                <div class="fw-bold text-dark fs-6">
                                    $<?= number_format((float) $r['last_price'], 2) ?>
                                </div>
                                <?php if ($r['price_change_pct'] >= 0): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.72rem;">
                                        <i class="bi bi-arrow-up-short"></i> +<?= number_format((float) $r['price_change_pct'], 2) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size: 0.72rem;">
                                        <i class="bi bi-arrow-down-short"></i> <?= number_format((float) $r['price_change_pct'], 2) ?>%
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- 5. Konfirmasi Volume Kenaikan -->
                            <td>
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <span class="badge <?= $r['volume_badge'] ?> px-2 py-1" style="font-size: 0.72rem;">
                                        RVOL: <?= $r['rvol'] ?>x
                                    </span>
                                    <span class="small fw-bold text-dark" style="font-size: 0.72rem;">
                                        <?= Html::encode($r['volume_strength_label']) ?>
                                    </span>
                                </div>
                                <div class="small mt-1" style="font-size: 0.72rem;">
                                    <?php if ($volSupports): ?>
                                        <span class="text-success fw-semibold">
                                            <i class="bi bi-check-circle-fill"></i> Mendukung Kenaikan Saham
                                        </span>
                                    <?php else: ?>
                                        <?php if (str_contains($action, 'SELL')): ?>
                                            <span class="text-danger fw-semibold">
                                                <i class="bi bi-arrow-down-circle-fill"></i> Tekanan Distribusi Penjualan
                                            </span>
                                        <?php else: ?>
                                            <span class="text-warning-emphasis fw-semibold">
                                                <i class="bi bi-exclamation-triangle-fill"></i> Volume Tipis (Waspada Fakeout)
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.68rem;">
                                    Vol: <?= number_format((int) $r['volume']) ?> vs SMA20: <?= number_format((int) $r['volume_sma20']) ?>
                                </div>
                            </td>

                            <!-- 6. Rencana Trading Presisi -->
                            <td>
                                <div class="small" style="font-size: 0.75rem;">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Entry:</span>
                                        <span class="fw-bold text-dark">$<?= number_format((float) $r['entry_price'], 2) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-danger">Stop Loss:</span>
                                        <span class="text-danger fw-semibold">$<?= number_format((float) $r['stop_loss'], 2) ?> (-<?= $r['stop_loss_pct'] ?>%)</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-success">Target 1:</span>
                                        <span class="text-success fw-bold">$<?= number_format((float) $r['target_1'], 2) ?> (+<?= $r['target_1_pct'] ?>%)</span>
                                    </div>
                                </div>
                                <div class="mt-1 d-flex justify-content-between align-items-center" style="font-size: 0.68rem;">
                                    <span class="badge bg-light text-dark border">R:R: <strong><?= $r['rr_ratio'] ?>x</strong></span>
                                    <span class="text-muted">TP2: $<?= number_format((float) $r['target_2'], 2) ?></span>
                                </div>
                            </td>

                            <!-- 7. Skor Keyakinan (Confidence Score) -->
                            <td>
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="fw-bold fs-6 <?= $r['confidence_score'] >= 80 ? 'text-success' : ($r['confidence_score'] >= 60 ? 'text-primary' : 'text-secondary') ?>">
                                        <?= $r['confidence_score'] ?>%
                                    </span>
                                    <span class="text-muted small" style="font-size: 0.68rem;">Keyakinan</span>
                                </div>
                                <div class="progress progress-confidence">
                                    <div class="progress-bar <?= $r['confidence_score'] >= 80 ? 'bg-success' : ($r['confidence_score'] >= 60 ? 'bg-primary' : 'bg-warning') ?>"
                                         role="progressbar"
                                         style="width: <?= $r['confidence_score'] ?>%;">
                                    </div>
                                </div>
                            </td>

                            <!-- 8. Aksi -->
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <?= Html::a(
                                        '<i class="bi bi-graph-up me-1"></i> Detail',
                                        ['/candlestick/detail', 'symbol' => $r['symbol'], 'timeframe' => $params['timeframe']],
                                        ['class' => 'btn btn-sm btn-primary shadow-sm', 'title' => 'Buka Trading Plan & Chart ECharts']
                                    ) ?>
                                    <a href="https://www.tradingview.com/chart/?symbol=<?= !empty($r['exchange']) ? urlencode($r['exchange'] . ':' . $r['symbol']) : urlencode($r['symbol']) ?>"
                                       target="_blank"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="Lihat di TradingView">
                                        <i class="bi bi-tv"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Card Edukasi Panduan Pola Candlestick & Volume Konfirmasi -->
<div class="card border-0 shadow-sm bg-light mb-4">
    <div class="card-body p-4">
        <h5 class="fw-bold text-dark mb-3">
            <i class="bi bi-book-half text-primary me-2"></i> Panduan Edukasi: Pola Candlestick Potensial &amp; Konfirmasi Volume
        </h5>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm bg-white p-3">
                    <h6 class="fw-bold text-success mb-2">
                        🔨 Hammer &amp; Inverted Hammer
                    </h6>
                    <p class="small text-secondary mb-2">
                        <strong>Karakteristik:</strong> Body kecil di ujung atas dengan ekor bawah panjang (minimal 2x panjang body) yang terbentuk setelah penurunan harga.
                    </p>
                    <p class="small text-secondary mb-0">
                        <strong>Waktu Beli Terbaik:</strong> Saat harga menembus di atas High Hammer dengan stop loss di bawah Low ekor. Volume tinggi mengonfirmasi penyerapan supply institusional.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm bg-white p-3">
                    <h6 class="fw-bold text-success mb-2">
                        🟢 Bullish Engulfing
                    </h6>
                    <p class="small text-secondary mb-2">
                        <strong>Karakteristik:</strong> Candle hijau besar yang membungkus (engulf) seluruh badan candle merah sebelumnya di area support.
                    </p>
                    <p class="small text-secondary mb-0">
                        <strong>Waktu Beli Terbaik:</strong> Entry pada penutupan candle engulfing. Sinyal akumulasi mutlak dengan potensi kenaikan tinggi jika disertai volume di atas rata-rata.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm bg-white p-3">
                    <h6 class="fw-bold text-primary mb-2">
                        ⚡ Bullish Marubozu (Solid Momentum)
                    </h6>
                    <p class="small text-secondary mb-2">
                        <strong>Karakteristik:</strong> Candle hijau solid pejal tanpa jarum atas/bawah (body &ge; 85% range). Pembeli mendominasi sejak detik awal hingga tutup.
                    </p>
                    <p class="small text-secondary mb-0">
                        <strong>Waktu Beli Terbaik:</strong> Momentum breakout murni. Entry langsung atau saat pullback ringan ke separuh body Marubozu.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm bg-white p-3">
                    <h6 class="fw-bold text-warning-emphasis mb-2">
                        ✝️ Doji (Titik Balik Reversal)
                    </h6>
                    <p class="small text-secondary mb-2">
                        <strong>Karakteristik:</strong> Body sangat tipis/garis (open &asymp; close). Menggambarkan keseimbangan kekuatan atau keraguan pasar (indecision).
                    </p>
                    <p class="small text-secondary mb-0">
                        <strong>Strategi:</strong> Waktu pantau (wait &amp; see). Masuk posisi saat candle berikutnya berhasil breakout di atas High Doji dengan lonjakan volume.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm bg-white p-3">
                    <h6 class="fw-bold text-danger mb-2">
                        ⭐ Shooting Star &amp; Bearish Engulfing
                    </h6>
                    <p class="small text-secondary mb-2">
                        <strong>Karakteristik:</strong> Ekor atas panjang atau candle merah menelan candle hijau di puncak tren naik (resistance area).
                    </p>
                    <p class="small text-secondary mb-0">
                        <strong>Waktu Jual Terbaik:</strong> Segera lakukan Take Profit atau pasang proteksi stop loss ketat sebelum tekanan jual membanting harga lebih dalam.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm bg-white p-3">
                    <h6 class="fw-bold text-info-emphasis mb-2">
                        🚀 Mengapa Konfirmasi Volume Mutlak Diperlukan?
                    </h6>
                    <p class="small text-secondary mb-2">
                        <strong>Bahaya Fakeout / Bull Trap:</strong> Pola bullish yang terbentuk dengan volume rendah (kering) sering kali hanya pantulan semu ritel yang mudah gagal.
                    </p>
                    <p class="small text-secondary mb-0">
                        <strong>Jejak Big Money:</strong> Lonjakan volume (RVOL &ge; 1.5x - 2.0x) membuktikan institusi / paus sedang memborong saham, memberikan jaminan probabilitas kenaikan tertinggi!
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Notifikasi Copy Sukses -->
<div class="modal fade" id="copySuccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content text-center p-3">
            <div class="text-success mb-2"><i class="bi bi-check-circle-fill fs-1"></i></div>
            <h6 class="fw-bold mb-1">Berhasil Disalin!</h6>
            <p class="small text-muted mb-0" id="copyModalMessage">Daftar simbol telah disalin ke clipboard.</p>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text, format) {
    if (!text) {
        alert('Tidak ada simbol untuk disalin.');
        return;
    }
    navigator.clipboard.writeText(text).then(function () {
        document.getElementById('copyModalMessage').innerText = 'Simbol format ' + format + ' (' + text.split(',').length + ' item) disalin ke clipboard.';
        const modal = new bootstrap.Modal(document.getElementById('copySuccessModal'));
        modal.show();
        setTimeout(() => modal.hide(), 1600);
    }).catch(function (err) {
        alert('Gagal menyalin: ' + err);
    });
}
</script>
