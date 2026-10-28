<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var array $results */
/** @var array $params */
/** @var array $stats */
/** @var array $symbols */
/** @var array $tvSymbols */
/** @var array $timeframes */
/** @var array $presets */
/** @var array $sectors */
/** @var bool|null $fromCache */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Stochastic Oscillator Strategy & Position Scanner';
$this->params['breadcrumbs'][] = 'Stochastic Strategy';

$symbolsStr = implode(', ', $symbols ?? []);
$tvSymbolsStr = implode(', ', $tvSymbols ?? []);

$currentGet = Yii::$app->request->get();
$filterWith = fn(array $overrides) => Url::to(array_merge(['/stochastic/index'], $currentGet, $overrides));

$badgeAction = [
    'STRONG_BUY' => 'success shadow-sm',
    'BUY' => 'primary shadow-sm',
    'HOLD' => 'info text-white shadow-sm',
    'SELL' => 'danger shadow-sm',
    'TAKE_PROFIT' => 'warning text-dark shadow-sm',
    'WATCH' => 'secondary',
];
?>

<!-- 1. Unified App Hero Header -->
<div class="card border-0 shadow-sm mb-4 app-hero-header">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="app-hero-icon text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(14, 165, 233, 0.1);">
                    <?= \app\helpers\SvgHelper::icon('activity') ?>
                </div>
                <div>
                    <h1 class="app-hero-title h4 fw-bold mb-1 text-dark">
                        <?= Html::encode($this->title) ?>
                    </h1>
                    <p class="app-hero-desc small text-muted mb-0">
                        Penentuan waktu beli (<em>entry</em>), jenuh jual/beli, <strong>🛡️ Shakeout Low (Wyckoff Spring)</strong>, dan pembalikan arah berbasis <strong>Slow Stochastic (14, 3, 3)</strong>.
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
                            ['class' => 'btn-tf btn btn-sm rounded-pill px-3 ' . ((int) ($params['timeframe'] ?? 24) === (int) $tfKey ? 'active btn-primary text-white shadow-sm' : 'btn-light text-secondary border-0')]
                        ) ?>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill shadow-xs" data-bs-toggle="modal" data-bs-target="#tvModal" <?= empty($tvSymbols) ? 'disabled' : '' ?>>
                    <?= \app\helpers\SvgHelper::icon('tv') ?> TradingView (<?= count($tvSymbols ?? []) ?>)
                </button>

                <?= Html::a(
                    \app\helpers\SvgHelper::icon('download') . ' Export CSV',
                    Url::to(array_merge(['/stochastic/export'], $currentGet)),
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
    <div class="col-6 col-md-4 col-lg">
        <a href="<?= $filterWith(['strategy' => 'all']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-indigo <?= ($params['strategy'] ?? 'all') === 'all' ? 'kpi-selected border border-primary' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-muted">Total Saham</span>
                    <?= \app\helpers\SvgHelper::icon('grid', ['class' => 'text-muted']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-dark"><?= number_format((int) ($stats['total_scanned'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-muted">Semua Hasil Scan</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-lg">
        <a href="<?= $filterWith(['strategy' => 'buy_all']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-emerald <?= ($params['strategy'] ?? '') === 'buy_all' ? 'kpi-selected border border-success' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-success">Sinyal Beli (Buy)</span>
                    <?= \app\helpers\SvgHelper::icon('check-circle', ['class' => 'text-success']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-success"><?= number_format((int) ($stats['total_buy'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-success">Setup Beli Aktif</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-lg">
        <a href="<?= $filterWith(['strategy' => 'shakeout_low']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-amber <?= ($params['strategy'] ?? '') === 'shakeout_low' ? 'kpi-selected border border-warning' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-warning-emphasis">
                        <?= \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-warning me-1']) ?>Shakeout Low
                    </span>
                    <span class="badge bg-warning-subtle text-warning-emphasis px-1 py-0" style="font-size:0.65rem;">HIGH WINRATE</span>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-dark"><?= number_format((int) ($stats['shakeout_low_count'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-muted">False Breakdown &amp; Reclaim</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-lg">
        <a href="<?= $filterWith(['strategy' => 'golden_cross_oversold']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-cyan <?= ($params['strategy'] ?? '') === 'golden_cross_oversold' ? 'kpi-selected border border-info' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-info">Golden Cross &lt; 20</span>
                    <?= \app\helpers\SvgHelper::icon('rocket-takeoff', ['class' => 'text-info']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-info"><?= number_format((int) ($stats['golden_cross_oversold'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-muted">Rebound Jenuh Jual</div>
            </div>
        </a>
    </div>

    <div class="col-12 col-md-4 col-lg">
        <a href="<?= $filterWith(['strategy' => 'sell_all']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-rose <?= ($params['strategy'] ?? '') === 'sell_all' ? 'kpi-selected border border-danger' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-danger">Take Profit / Exit</span>
                    <?= \app\helpers\SvgHelper::icon('exclamation-triangle', ['class' => 'text-danger']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-danger"><?= number_format((int) ($stats['overbought_count'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-danger">Death Cross &amp; Overbought</div>
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
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . (($params['strategy'] ?? 'all') === 'all' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('check-circle-fill') . ' Semua Sinyal Beli',
                $filterWith(['strategy' => 'buy_all']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . (($params['strategy'] ?? '') === 'buy_all' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('lightning-charge-fill') . ' Shakeout Low (' . ($stats['shakeout_low_count'] ?? 0) . ')',
                $filterWith(['strategy' => 'shakeout_low']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . (($params['strategy'] ?? '') === 'shakeout_low' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('rocket-takeoff-fill') . ' Golden Cross Oversold (' . ($stats['golden_cross_oversold'] ?? 0) . ')',
                $filterWith(['strategy' => 'golden_cross_oversold']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . (($params['strategy'] ?? '') === 'golden_cross_oversold' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('bullseye') . ' Bullish Divergence (' . ($stats['bullish_divergence'] ?? 0) . ')',
                $filterWith(['strategy' => 'bullish_divergence']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . (($params['strategy'] ?? '') === 'bullish_divergence' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('water') . ' Oversold (%K < 20)',
                $filterWith(['strategy' => 'oversold_zone']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . (($params['strategy'] ?? '') === 'oversold_zone' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('shield-exclamation') . ' Exit / TP (' . ($stats['total_sell'] ?? 0) . ')',
                $filterWith(['strategy' => 'sell_all']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . (($params['strategy'] ?? '') === 'sell_all' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>
        </div>
    </div>

    <!-- Filter Form Grid -->
    <div class="card-body p-3 filter-card-body">
        <?= Html::beginForm(['/stochastic/index'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <?= Html::hiddenInput('timeframe', $params['timeframe'] ?? 24) ?>
            <?= Html::hiddenInput('sort', $params['sort'] ?? 'confidence_desc') ?>

            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label small fw-bold text-dark mb-1">Strategi Stochastic</label>
                <?= Html::dropDownList('strategy', $params['strategy'] ?? 'all', $presets, ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Min. Risk:Reward</label>
                <?= Html::dropDownList('minRr', $params['minRr'] ?? 0.0, [
                    '0' => 'Semua R:R',
                    '1.5' => 'Min. 1.5 : 1',
                    '2.0' => 'Min. 2.0 : 1',
                    '3.0' => 'Min. 3.0 : 1 (Asimetris)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Cari Simbol</label>
                <?= Html::textInput('symbol', $params['symbol'] ?? '', ['class' => 'form-control form-control-sm text-uppercase', 'placeholder' => 'Contoh: AAPL, NVDA']) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Sektor</label>
                <?php
                $sectorOptions = ['all' => 'Semua Sektor'];
                foreach ($sectors as $sec) {
                    $sectorOptions[$sec] = $sec;
                }
                ?>
                <?= Html::dropDownList('sector', $params['sector'] ?? 'all', $sectorOptions, ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-6 col-6 d-flex gap-2">
                <?= Html::submitButton(\app\helpers\SvgHelper::icon('search') . ' Pindai', ['class' => 'btn btn-primary btn-sm flex-grow-1 shadow-sm']) ?>
                <?= Html::a(\app\helpers\SvgHelper::icon('arrow-counterclockwise'), ['/stochastic/index', 'timeframe' => $params['timeframe'] ?? 24], ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Reset Filter']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- 4. Tabel Hasil Scanner Unified -->
<div class="table-card-unified">
    <div class="table-card-header">
        <div>
            <h5 class="mb-0">
                <?= \app\helpers\SvgHelper::icon('table', ['class' => 'text-primary me-1']) ?> Daftar Sinyal &amp; Setup Stochastic Oscillator
            </h5>
            <small class="text-muted">Menampilkan <?= count($results) ?> saham teranalisis</small>
        </div>
        <div class="small text-muted">
            Parameter: <strong>Slow Stochastic (14, 3, 3)</strong>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-unified table-hover align-middle mb-0" id="table-stochastic">
            <thead class="table-light text-muted small text-uppercase">
                <tr>
                    <th class="ps-3">Saham</th>
                    <th class="text-end">Harga Saat Ini</th>
                    <th class="text-center">Stochastic (%K / %D)</th>
                    <th>Setup &amp; Sinyal</th>
                    <th class="text-center">Skor</th>
                    <th class="text-end">Area Beli (Entry)</th>
                    <th class="text-end">Stop Loss</th>
                    <th class="text-end">TP1 (Gain)</th>
                    <th class="text-end">TP2 (Gain)</th>
                    <th class="text-center">R:R</th>
                    <th class="text-center">Aksi</th>
                    <th class="pe-3 text-end">Aksi Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="12" class="text-center py-5">
                            <div class="text-muted">
                                <?= \app\helpers\SvgHelper::icon('search', ['class' => 'fs-1 d-block mb-3 opacity-50']) ?>
                                <h6 class="fw-bold">Tidak ada saham yang sesuai kriteria filter saat ini</h6>
                                <p class="small mb-3">Coba ubah kriteria filter, pilih preset lain, atau gunakan timeframe berbeda.</p>
                                <?= Html::a('Reset Filter', ['/stochastic/index', 'timeframe' => $params['timeframe'] ?? 24], ['class' => 'btn btn-sm btn-outline-primary rounded-pill px-3']) ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $r): ?>
                        <?php
                        $currK = (float) $r['curr_k'];
                        $currD = (float) $r['curr_d'];
                        $changePct = (float) ($r['change_pct'] ?? 0);
                        $changeColor = $changePct >= 0 ? 'text-success' : 'text-danger';
                        $changeSign = $changePct > 0 ? '+' : '';
                        $zoneColor = $currK < 20.0 ? '#10b981' : ($currK > 80.0 ? '#ef4444' : '#0284c7');
                        $action = $r['action'] ?? 'WATCH';
                        $actionClass = $badgeAction[$action] ?? 'secondary';
                        ?>
                        <tr>
                            <!-- Simbol & Nama -->
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="symbol-badge bg-light border fw-bold px-2 py-1 rounded font-monospace text-dark" style="font-size:0.85rem;">
                                        <?= Html::encode($r['symbol']) ?>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 140px;" title="<?= Html::encode($r['name']) ?>">
                                            <?= Html::encode($r['name'] ?: $r['symbol']) ?>
                                        </div>
                                        <div class="text-muted small" style="font-size: 0.70rem;">
                                            <?= Html::encode($r['sector'] ?: 'US Stock') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Harga Saat Ini -->
                            <td class="text-end font-monospace">
                                <div class="fw-bold text-dark">$<?= number_format((float) $r['current_price'], 2) ?></div>
                                <div class="<?= $changeColor ?> small" style="font-size: 0.72rem;">
                                    <?= $changeSign ?><?= number_format($changePct, 2) ?>%
                                </div>
                            </td>

                            <!-- Stochastic %K / %D -->
                            <td class="text-center font-monospace">
                                <div class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded border" style="background-color: #f8fafc;">
                                    <span class="fw-bold" style="color: <?= $zoneColor ?>;">%K <?= number_format($currK, 1) ?></span>
                                    <span class="text-muted small">/</span>
                                    <span class="text-secondary small">%D <?= number_format($currD, 1) ?></span>
                                </div>
                                <div class="mt-1" style="font-size:0.68rem;">
                                    <?php if ($currK < 20.0): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-1">Oversold</span>
                                    <?php elseif ($currK > 80.0): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0 px-1">Overbought</span>
                                    <?php else: ?>
                                        <span class="text-muted">Netral (<?= round($currK) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Setup & Sinyal -->
                            <td>
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <span class="badge bg-<?= Html::encode($r['strategy_badge']) ?>-subtle text-<?= Html::encode($r['strategy_badge']) ?>-emphasis border border-<?= Html::encode($r['strategy_badge']) ?>-subtle fw-semibold" style="font-size:0.75rem;">
                                        <?= Html::encode($r['strategy_name']) ?>
                                    </span>
                                </div>
                                <?php if (!empty($r['shakeout_low']['detected'])): ?>
                                    <div class="mt-1">
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size:0.67rem;">
                                            <i class="bi bi-shield-check me-1"></i>Support $<?= number_format($r['shakeout_low']['support_level'], 2) ?> (Sweep -<?= $r['shakeout_low']['sweep_pct'] ?>%)
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="text-muted text-truncate mt-1 small" style="font-size: 0.73rem; max-width: 240px;" title="<?= Html::encode($r['description']) ?>">
                                    <?= Html::encode($r['description']) ?>
                                </div>
                            </td>

                            <!-- Skor Winrate -->
                            <td class="text-center font-monospace">
                                <span class="badge bg-light text-dark border fw-bold px-2 py-1">
                                    <?= (int) ($r['confidence_score'] ?? 50) ?>%
                                </span>
                            </td>

                            <!-- Area Beli (Entry) -->
                            <td class="text-end font-monospace fw-semibold text-dark">
                                $<?= number_format((float) $r['entry_price'], 2) ?>
                            </td>

                            <!-- Stop Loss -->
                            <td class="text-end font-monospace text-danger">
                                <div>$<?= number_format((float) $r['stop_loss'], 2) ?></div>
                                <small class="text-danger opacity-75">-<?= $r['stop_loss_pct'] ?>%</small>
                            </td>

                            <!-- Target Profit 1 (TP1) -->
                            <td class="text-end font-monospace text-success">
                                <div class="fw-semibold">$<?= number_format((float) $r['target_profit_1'], 2) ?></div>
                                <small class="text-success">+<?= $r['target_profit_1_pct'] ?>%</small>
                            </td>

                            <!-- Target Profit 2 (TP2) -->
                            <td class="text-end font-monospace" style="color: #0f766e;">
                                <div class="fw-semibold">$<?= number_format((float) $r['target_profit_2'], 2) ?></div>
                                <small>+<?= $r['target_profit_2_pct'] ?>%</small>
                            </td>

                            <!-- R:R -->
                            <td class="text-center">
                                <span class="badge bg-<?= ($r['risk_reward_ratio'] >= 2.0) ? 'success' : (($r['risk_reward_ratio'] >= 1.5) ? 'primary' : 'secondary') ?> fs-7">
                                    1 : <?= $r['risk_reward_ratio'] ?>
                                </span>
                            </td>

                            <!-- Aksi -->
                            <td class="text-center">
                                <span class="badge bg-<?= $actionClass ?> text-uppercase px-2 py-1" style="font-size:0.72rem;">
                                    <?= Html::encode($action) ?>
                                </span>
                            </td>

                            <!-- Aksi Detail -->
                            <td class="pe-3 text-end">
                                <?= Html::a(
                                    \app\helpers\SvgHelper::icon('box-arrow-up-right') . ' Plan &amp; Chart',
                                    ['/stochastic/detail', 'symbol' => $r['symbol'], 'timeframe' => $params['timeframe'] ?? 24],
                                    ['class' => 'btn btn-sm btn-outline-primary shadow-none py-1 px-2 text-nowrap']
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal TradingView Watchlist Multi-Symbol -->
<div class="modal fade" id="tvModal" tabindex="-1" aria-labelledby="tvModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="tvModalLabel">
                    <?= \app\helpers\SvgHelper::icon('tv') ?> Copy Simbol untuk TradingView
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Salin daftar simbol di bawah ini untuk dimasukkan langsung ke Watchlist TradingView Anda:
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Format Simbol dengan Bursa (NASDAQ:AAPL, ...)</label>
                    <textarea id="tvSymbolsArea" class="form-control form-control-sm font-monospace" rows="4" readonly><?= Html::encode($tvSymbolsStr) ?></textarea>
                </div>
                <div>
                    <label class="form-label small fw-bold text-dark">Format Simbol Standar (AAPL, NVDA, ...)</label>
                    <textarea id="plainSymbolsArea" class="form-control form-control-sm font-monospace" rows="3" readonly><?= Html::encode($symbolsStr) ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('tvSymbolsArea').value); alert('Simbol berhasil disalin ke clipboard!');">
                    <?= \app\helpers\SvgHelper::icon('clipboard') ?> Salin Simbol TradingView
                </button>
            </div>
        </div>
    </div>
</div>
