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

<!-- 1. Unified App Hero Header -->
<div class="card border-0 shadow-sm mb-4 app-hero-header">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="app-hero-icon text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: #eef2ff;">
                    <?= \app\helpers\SvgHelper::icon('bullseye') ?>
                </div>
                <div>
                    <h1 class="app-hero-title h4 fw-bold mb-1 text-dark">
                        <?= Html::encode($this->title) ?>
                    </h1>
                    <p class="app-hero-desc small text-muted mb-0">
                        Penentuan <strong>waktu beli terbaik</strong> (Golden Pocket 50% &ndash; 61.8%) &amp; <strong>waktu jual terbaik</strong> (Target Ekstensi 1.272 &amp; 1.618 atau Stop Loss) dengan konfirmasi volume.
                    </p>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Timeframe Group -->
                <div class="btn-group-tf btn-group btn-group-sm p-1 bg-light rounded-pill border">
                    <?php foreach ($timeframes as $tfKey => $tfLabel): ?>
                        <?= Html::a(
                            Html::encode($tfLabel),
                            $filterWith(['timeframe' => $tfKey]),
                            ['class' => 'btn-tf btn btn-sm rounded-pill px-3 ' . ((int) $params['timeframe'] === (int) $tfKey ? 'active btn-primary text-white shadow-sm' : 'btn-light text-secondary border-0')]
                        ) ?>
                    <?php endforeach; ?>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill dropdown-toggle shadow-xs" type="button" data-bs-toggle="dropdown">
                        <?= \app\helpers\SvgHelper::icon('clipboard') ?> Salin Simbol (<?= count($symbols) ?>)
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li>
                            <button class="dropdown-item small" type="button" onclick="copyToClipboard('<?= Html::encode($tvSymbolsStr) ?>', 'TradingView')">
                                <?= \app\helpers\SvgHelper::icon('tv', ['class' => 'me-1 text-primary']) ?> Format TradingView (<?= count($tvSymbols) ?>)
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item small" type="button" onclick="copyToClipboard('<?= Html::encode($symbolsStr) ?>', 'Biasa')">
                                <?= \app\helpers\SvgHelper::icon('card-text', ['class' => 'me-1 text-success']) ?> Format Simbol Biasa (<?= count($symbols) ?>)
                            </button>
                        </li>
                    </ul>
                </div>

                <?= Html::a(
                    \app\helpers\SvgHelper::icon('download') . ' Ekspor CSV',
                    Url::to(array_merge(['/fibonacci/export'], $currentGet)),
                    ['class' => 'btn btn-sm btn-outline-success rounded-pill shadow-xs']
                ) ?>
            </div>
        </div>
    </div>
</div>

<?= $this->render('//layouts/_sync_status_badge', [
    'fromCache' => $fromCache ?? false,
    'timeframe' => (int) ($params['timeframe'] ?? 24),
]) ?>

<!-- 2. Unified KPI Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= Url::to(['/fibonacci/index', 'timeframe' => $params['timeframe']]) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-indigo <?= ($params['strategy'] === 'all' || empty($params['strategy'])) ? 'kpi-selected border border-primary' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-muted">Total Saham Terfilter</span>
                    <?= \app\helpers\SvgHelper::icon('grid', ['class' => 'text-muted']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-dark"><?= number_format((int) ($stats['total'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-muted"><?= \app\helpers\SvgHelper::icon('arrow-counterclockwise') ?> Reset Filter</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'golden_pocket']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-emerald <?= ($params['strategy'] === 'golden_pocket') ? 'kpi-selected border border-success' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-success">⭐ Golden Pocket Buys</span>
                    <?= \app\helpers\SvgHelper::icon('check-circle', ['class' => 'text-success']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-success"><?= number_format((int) ($stats['goldenPocketCount'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-success">Waktu Beli Terbaik (50%-61.8%)</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'target_reached']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-amber <?= ($params['strategy'] === 'target_reached') ? 'kpi-selected border border-warning' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-warning-emphasis">🎯 Target Ekstensi Tercapai</span>
                    <?= \app\helpers\SvgHelper::icon('trophy-fill', ['class' => 'text-warning']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-dark"><?= number_format((int) ($stats['targetReachedCount'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-muted">Waktu Jual TP (1.272 / 1.618)</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'breakdown']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-rose <?= ($params['strategy'] === 'breakdown') ? 'kpi-selected border border-danger' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-danger">⚠️ Alert Jual / Cut Loss</span>
                    <?= \app\helpers\SvgHelper::icon('shield-x', ['class' => 'text-danger']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-danger"><?= number_format((int) ($stats['breakdownCount'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-danger">Breakdown Support (&lt; 78.6%)</div>
            </div>
        </a>
    </div>
</div>

<!-- 3. Unified Filter Card & Presets -->
<div class="card border-0 shadow-sm mb-4 filter-card-unified">
    <!-- Preset Pills Bar -->
    <div class="card-header bg-white border-bottom py-2 px-3 filter-card-header">
        <div class="preset-pills-bar d-flex flex-wrap align-items-center gap-1 mb-0">
            <span class="small fw-bold text-muted me-2">
                <?= \app\helpers\SvgHelper::icon('sliders2', ['class' => 'text-primary']) ?> Preset Cepat:
            </span>
            
            <?= Html::a(
                \app\helpers\SvgHelper::icon('grid-fill') . ' Semua Sinyal',
                $filterWith(['strategy' => 'all']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'all' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('star-fill', ['class' => 'text-warning']) . ' Golden Pocket',
                $filterWith(['strategy' => 'golden_pocket']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'golden_pocket' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-warning']) . ' Shakeout Low (' . ($stats['shakeoutCount'] ?? 0) . ')',
                $filterWith(['strategy' => 'shakeout_low']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'shakeout_low' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('arrow-down-right') . ' Pullback 38.2%',
                $filterWith(['strategy' => 'pullback_382']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'pullback_382' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('rocket-takeoff') . ' Breakout High',
                $filterWith(['strategy' => 'breakout_high']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'breakout_high' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('cash-stack') . ' TP Tercapai',
                $filterWith(['strategy' => 'target_reached']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'target_reached' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('shield-slash') . ' Breakdown Alert',
                $filterWith(['strategy' => 'breakdown']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'breakdown' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card-body p-3 filter-card-body">
        <?= Html::beginForm(['/fibonacci/index'], 'get', ['class' => 'row g-3 align-items-end', 'id' => 'filter-form']) ?>
            <input type="hidden" name="timeframe" value="<?= Html::encode($params['timeframe']) ?>">

            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label small fw-bold text-dark mb-1">Cari Simbol Saham</label>
                <?= Html::textInput('symbol', $params['symbol'] ?? '', [
                    'class' => 'form-control form-control-sm text-uppercase',
                    'placeholder' => 'Contoh: NVDA, AAPL, BBCA',
                ]) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label small fw-bold text-dark mb-1">Strategi Setup</label>
                <?= Html::dropDownList('strategy', $params['strategy'], [
                    'all' => 'Semua Sinyal',
                    'buy_all' => 'Semua Posisi Beli',
                    'golden_pocket' => '⭐ Golden Pocket (50%-61.8%)',
                    'shakeout_low' => '⚡ Shakeout Low (Golden Pocket Sweep)',
                    'pullback_382' => 'Pullback 38.2% Dip',
                    'breakout_high' => 'Breakout Swing High',
                    'sell_all' => 'Semua Alert Jual / TP',
                    'target_reached' => '🎯 Ekstensi 1.272 / 1.618 Tercapai',
                    'breakdown' => '⚠️ Breakdown Cut Loss',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-4 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Min. Risk/Reward</label>
                <?= Html::dropDownList('minRr', (string) $params['minRr'], [
                    '0' => 'Semua R:R',
                    '1.5' => '≥ 1.5 : 1',
                    '2.0' => '≥ 2.0 : 1 (Ideal)',
                    '3.0' => '≥ 3.0 : 1 (Super)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-4 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Urutkan (Sort)</label>
                <?= Html::dropDownList('sort', $params['sort'], [
                    'rr' => 'R:R Tertinggi',
                    'gain' => 'Potensi Gain TP1 Terbesar',
                    'retrace' => 'Retracement Terdalam',
                    'rvol' => 'Volume RVOL Terbesar',
                    'symbol' => 'Simbol (A-Z)',
                    'price' => 'Harga Tertinggi',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-4 col-12 d-flex gap-2">
                <?= Html::submitButton(\app\helpers\SvgHelper::icon('search') . ' Pindai', ['class' => 'btn btn-primary btn-sm flex-grow-1 shadow-sm']) ?>
                <?= Html::a(\app\helpers\SvgHelper::icon('arrow-counterclockwise'), ['/fibonacci/index'], ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Reset Filter']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- 4. Tabel Hasil Scanner Unified -->
<div class="table-card-unified">
    <div class="table-card-header">
        <div>
            <h5 class="mb-0">
                <?= \app\helpers\SvgHelper::icon('table', ['class' => 'text-primary me-1']) ?> Daftar Sinyal Fibonacci Retracement &amp; Extension
            </h5>
            <small class="text-muted">Menampilkan <strong><?= count($results) ?></strong> saham hasil scanner Fibonacci</small>
        </div>
        <div style="max-width: 250px;" class="w-100">
            <input type="text" id="tableSearchInput" class="form-control form-control-sm" placeholder="Filter cepat di tabel...">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-unified table-hover align-middle mb-0" id="fib-grid-table">
            <thead class="table-light text-muted small text-uppercase">
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
                            <?= \app\helpers\SvgHelper::icon('inbox', ['class' => 'fs-1 d-block mb-2 text-secondary']) ?>
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
                        $isBuy = ($act === 'BUY');
                        $isSell = ($act === 'SELL');
                        $isTp = ($act === 'TAKE_PROFIT');
                        ?>
                        <tr data-symbol="<?= Html::encode($r['symbol']) ?>">
                            <td class="fw-bold">
                                <div class="d-flex align-items-center gap-1">
                                    <?php if ($isBuy || $isGolden): ?>
                                        <span class="p-1 bg-success rounded-circle d-inline-block" title="<?= $isGolden ? 'Golden Pocket / Potensi Beli' : 'Potensi Beli' ?>" style="width: 8px; height: 8px;"></span>
                                    <?php elseif ($isSell): ?>
                                        <span class="p-1 bg-danger rounded-circle d-inline-block" title="Bearish Breakdown / Exit" style="width: 8px; height: 8px;"></span>
                                    <?php elseif ($isTp): ?>
                                        <span class="p-1 bg-warning rounded-circle d-inline-block" title="Take Profit" style="width: 8px; height: 8px;"></span>
                                    <?php else: ?>
                                        <span class="p-1 bg-secondary bg-opacity-25 rounded-circle d-inline-block" title="Wait & See" style="width: 8px; height: 8px;"></span>
                                    <?php endif; ?>
                                    <?= Html::a(
                                        Html::encode($r['symbol']),
                                        ['/fibonacci/detail', 'symbol' => $r['symbol'], 'timeframe' => $params['timeframe']],
                                        ['class' => 'text-decoration-none text-primary fs-6']
                                    ) ?>
                                </div>
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
                                    <?php if (!empty($r['is_shakeout_low'])): ?>
                                        <span class="badge bg-warning text-dark border border-warning" style="font-size:0.65rem;" title="Undercut 61.8% by <?= $r['shakeout_undercut_pct'] ?? 0 ?>%">
                                            <?= \app\helpers\SvgHelper::icon('lightning-charge-fill') ?> Sweep <?= $r['shakeout_undercut_pct'] ?? 0 ?>%
                                        </span>
                                    <?php endif; ?>
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
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: 0.7rem;">
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
                                    \app\helpers\SvgHelper::icon('compass') . ' Trading Plan',
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
                <?= \app\helpers\SvgHelper::icon('check2-circle', ['class' => 'text-success me-1']) ?> Simbol disalin ke clipboard!
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
            msgEl.innerHTML = '<?= \app\helpers\SvgHelper::icon('check2-circle', ['class' => 'text-success me-1']) ?> Berhasil menyalin ' + label + ' ke clipboard!';
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
