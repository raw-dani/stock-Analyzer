<?php

/** @var yii\web\View $this */
/** @var array $results */
/** @var int $timeframe */
/** @var int $lookback */
/** @var string $patternType */
/** @var string $statusFilter */
/** @var float $minRr */
/** @var bool $breakoutOnly */
/** @var bool $volOnly */
/** @var string $falseBreakoutFilter */
/** @var int $minConfidence */
/** @var string $sort */
/** @var array $timeframes */
/** @var array $patternTypes */
/** @var array $statusOptions */
/** @var array $falseBreakoutFilters */
/** @var array $filterParams */
/** @var array $stats */
/** @var bool $fromCache */
/** @var int $lookbackDays */
/** @var int $cupCount */
/** @var int $rectCount */
/** @var int $pennantCount */
/** @var int $flagCount */
/** @var int $triangleCount */
/** @var int $breakoutCount */
/** @var int $buyZoneCount */
/** @var int $bullTrapCount */
/** @var int $highRrCount */

use yii\bootstrap5\Html;
use app\services\BullishPatternService;

$this->title = 'Bullish Continuation & Breakout Patterns';
$this->params['breadcrumbs'][] = $this->title;
$lookbackDays = $lookbackDays ?? $lookback;
$stockCount = (int) ($stats['stockCount'] ?? 0);

$sortLink = fn(string $key, string $label) => Html::a(
    $label . ($sort === $key ? ' ▼' : ''),
    array_merge(['/bullish-pattern/index'], $filterParams, ['sort' => $key]),
    ['class' => 'text-decoration-none text-dark fw-bold']
);
$filterWith = fn(array $overrides) => \yii\helpers\Url::to(array_merge(['/bullish-pattern/index'], $filterParams, $overrides));
?>

<!-- 1. Unified App Hero Header -->
<!-- 1. Unified App Hero Header -->
<div class="card border-0 shadow-sm mb-4 app-hero-header">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="app-hero-icon text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: #eef2ff;">
                    <?= \app\helpers\SvgHelper::icon('rocket-takeoff') ?>
                </div>
                <div>
                    <h1 class="app-hero-title h4 fw-bold mb-1 text-dark">
                        <?= Html::encode($this->title) ?>
                    </h1>
                    <p class="app-hero-desc small text-muted mb-0">
                        Deteksi posisi Beli &amp; Jual ideal dengan <strong>Cup &amp; Handle, Bullish Rectangle, Bullish Pennant, Bullish Flag, dan Ascending Triangle</strong>.
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
                            ['class' => 'btn-tf btn btn-sm rounded-pill px-3 ' . ((int) $timeframe === (int) $tfKey ? 'active btn-primary text-white shadow-sm' : 'btn-light text-secondary border-0')]
                        ) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->render('//layouts/_sync_status_badge', [
    'fromCache' => $fromCache ?? false,
    'timeframe' => (int) ($timeframe ?? 24),
]) ?>

<!-- 2. Unified KPI Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['patternType' => 'ALL', 'statusFilter' => 'all']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-indigo <?= ($statusFilter === 'all' && ($patternType === 'ALL' || empty($patternType))) ? 'kpi-selected border border-primary' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-muted">Pola Ditemukan</span>
                    <?= \app\helpers\SvgHelper::icon('grid', ['class' => 'text-muted']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-dark"><?= count($results) ?></div>
                <div class="kpi-subtext small text-muted">Cup: <?= $cupCount ?> | Flag: <?= $flagCount ?> | Tri: <?= $triangleCount ?></div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['statusFilter' => 'buy_zone']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-emerald <?= $statusFilter === 'buy_zone' ? 'kpi-selected border border-success' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-success">Zona Beli (Buy Zones)</span>
                    <?= \app\helpers\SvgHelper::icon('check-circle', ['class' => 'text-success']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-success"><?= $buyZoneCount ?></div>
                <div class="kpi-subtext small text-success">
                    Optimal Entry &bull; <?= ($shakeoutCount ?? 0) > 0 ? '⚡ ' . $shakeoutCount . ' Shakeout' : 'Siap Beli' ?>
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['statusFilter' => 'breakout']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-cyan <?= $statusFilter === 'breakout' ? 'kpi-selected border border-info' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-cyan">Breakout Tembus</span>
                    <?= \app\helpers\SvgHelper::icon('rocket-takeoff', ['class' => 'text-info']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-info"><?= $breakoutCount ?></div>
                <div class="kpi-subtext small">
                    <?php if ($bullTrapCount > 0): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0">⚠️ <?= $bullTrapCount ?> Bull Trap</span>
                    <?php else: ?>
                        <span class="text-success small">✓ Bebas Bull Trap</span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['minRr' => '2.0']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-amber <?= (float)$minRr >= 2.0 ? 'kpi-selected border border-warning' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-warning-emphasis">Risk/Reward Ideal (&ge; 2.0)</span>
                    <?= \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-warning']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-dark"><?= $highRrCount ?></div>
                <div class="kpi-subtext small text-muted">Potensi Profit &ge; 2x Risiko</div>
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
                \app\helpers\SvgHelper::icon('grid-fill') . ' Semua Pola',
                $filterWith(['patternType' => 'ALL', 'statusFilter' => 'all']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . (($patternType === 'ALL' || empty($patternType)) && $statusFilter === 'all' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('check-circle-fill') . ' In Buy Zone',
                $filterWith(['statusFilter' => 'buy_zone']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($statusFilter === 'buy_zone' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('rocket-takeoff') . ' Terkonfirmasi Breakout',
                $filterWith(['statusFilter' => 'breakout']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($statusFilter === 'breakout' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('cup-hot-fill') . ' Cup & Handle (' . $cupCount . ')',
                $filterWith(['patternType' => 'CUP_AND_HANDLE']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($patternType === 'CUP_AND_HANDLE' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('flag-fill') . ' Bullish Flag (' . $flagCount . ')',
                $filterWith(['patternType' => 'BULLISH_FLAG']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($patternType === 'BULLISH_FLAG' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('triangle-fill') . ' Ascending Triangle (' . $triangleCount . ')',
                $filterWith(['patternType' => 'ASCENDING_TRIANGLE']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($patternType === 'ASCENDING_TRIANGLE' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>
        </div>
    </div>

    <!-- Filter Form Body -->
    <div class="card-body p-3 filter-card-body">
        <?= Html::beginForm(['/bullish-pattern/index'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <input type="hidden" name="timeframe" value="<?= Html::encode($timeframe) ?>">

            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label small fw-semibold text-secondary mb-1">Jenis Pola Bullish</label>
                <?= Html::dropDownList('patternType', $patternType, $patternTypes, ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label small fw-semibold text-secondary mb-1">Status Eksekusi (Timing)</label>
                <?= Html::dropDownList('statusFilter', $statusFilter, $statusOptions, ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Risiko False Breakout</label>
                <?= Html::dropDownList('falseBreakoutFilter', $falseBreakoutFilter, $falseBreakoutFilters, ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Min. R:R</label>
                <?= Html::dropDownList('minRr', $minRr, [
                    '0' => 'Semua R:R',
                    '1.5' => '≥ 1.5 : 1',
                    '2.0' => '≥ 2.0 : 1 (Ideal)',
                    '3.0' => '≥ 3.0 : 1 (Super)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label small fw-semibold text-secondary mb-1">Urutan (Sort)</label>
                <?= Html::dropDownList('sort', $sort, [
                    'confidence' => 'Score Confidence',
                    'rr' => 'Risk/Reward Tertinggi',
                    'distance' => 'Terdekat ke Breakout',
                    'rvol' => 'Lonjakan Volume (RVOL)',
                    'target' => 'Potensi Profit Terbesar',
                    'symbol' => 'Simbol (A-Z)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Lookback (Bars)</label>
                <?= Html::input('number', 'lookback', $lookbackDays, ['class' => 'form-control form-control-sm', 'min' => 15, 'max' => 500]) ?>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Min. Confidence (%)</label>
                <?= Html::input('number', 'minConfidence', $minConfidence, ['class' => 'form-control form-control-sm', 'min' => 0, 'max' => 100, 'step' => 5]) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label small fw-semibold text-secondary mb-1 d-none d-md-block">&nbsp;</label>
                <div class="d-flex align-items-center gap-3 py-1">
                    <div class="form-check mb-0">
                        <?= Html::checkbox('breakoutOnly', $breakoutOnly, ['class' => 'form-check-input', 'id' => 'bp-cb-bo']) ?>
                        <label class="form-check-label small fw-semibold text-secondary" for="bp-cb-bo">Hanya Breakout</label>
                    </div>
                    <div class="form-check mb-0">
                        <?= Html::checkbox('volOnly', $volOnly, ['class' => 'form-check-input', 'id' => 'bp-cb-vol']) ?>
                        <label class="form-check-label small fw-semibold text-secondary" for="bp-cb-vol">Volume</label>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-6 col-12 d-flex gap-2">
                <?= Html::submitButton(\app\helpers\SvgHelper::icon('search') . ' Pindai', ['class' => 'btn btn-primary btn-sm flex-grow-1 shadow-sm']) ?>
                <?= Html::a(\app\helpers\SvgHelper::icon('arrow-counterclockwise'), ['/bullish-pattern/index'], ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Reset Filter']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- 4. Tabel Hasil Scanner Unified -->
<div class="table-card-unified">
    <div class="table-card-header">
        <div>
            <h5 class="mb-0">
                <?= \app\helpers\SvgHelper::icon('table', ['class' => 'text-primary me-1']) ?> Daftar Saham Pola Bullish Reversal &amp; Continuation
            </h5>
            <small class="text-muted">Menampilkan <?= count($results) ?> saham teranalisis</small>
        </div>
        <div class="small text-muted">
            TF: <strong><?= BullishPatternService::getTimeframeLabel($timeframe) ?></strong>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($results)): ?>
            <div class="p-4 text-center text-muted">
                <?= \app\helpers\SvgHelper::icon('inbox', ['class' => 'fs-1 d-block mb-2']) ?>
                <p class="mb-0">Tidak ada saham yang memenuhi kriteria pola saat ini. Coba pilih filter "Semua 5 Pola Bullish", perkecil Min R:R, atau ubah timeframe.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-unified table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th><?= $sortLink('symbol', 'Saham & Pola') ?></th>
                            <th>Harga Saat Ini</th>
                            <th>Struktur Anatomi Pola</th>
                            <th>Resistance Breakout</th>
                            <th>Status Eksekusi (Timing)</th>
                            <th>Risiko False Breakout</th>
                            <th>Target Jual (TP1 / TP2)</th>
                            <th>Cut Loss (SL)</th>
                            <th><?= $sortLink('rr', 'R:R') ?></th>
                            <th><?= $sortLink('rvol', 'RVOL') ?></th>
                            <th><?= $sortLink('confidence', 'Skor') ?></th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $p): ?>
                            <?php
                            $badge = BullishPatternService::getPatternBadge($p['pattern_type'] ?? '');
                            $status = $p['trade_status'] ?? '';
                            $badgeClass = 'bg-secondary';
                            if ($status === BullishPatternService::STATUS_BUY_ZONE) $badgeClass = 'bg-success';
                            elseif ($status === BullishPatternService::STATUS_SHAKEOUT_LOW) $badgeClass = 'bg-warning text-dark border border-warning';
                            elseif ($status === BullishPatternService::STATUS_RETEST) $badgeClass = 'bg-info text-dark';
                            elseif ($status === BullishPatternService::STATUS_CONSOLIDATION_BOUNCE) $badgeClass = 'bg-primary';
                            elseif ($status === BullishPatternService::STATUS_EXTENDED) $badgeClass = 'bg-warning text-dark';
                            elseif ($status === BullishPatternService::STATUS_OVEREXTENDED) $badgeClass = 'bg-danger';
                            elseif ($status === BullishPatternService::STATUS_APPROACHING) $badgeClass = 'bg-info text-white';

                            $profitTp2 = $p['current_price'] > 0 ? round((($p['tp2_price'] - $p['current_price']) / $p['current_price']) * 100, 1) : 0;
                            $risk = $p['false_breakout_risk'] ?? 'LOW';
                            $isBullTrap = !empty($p['bull_trap_detected']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= Html::encode($p['symbol']) ?></strong>
                                    <small class="text-muted d-block"><?= Html::encode($p['sector'] ?? '-') ?></small>
                                    <span class="badge text-white" style="background-color: <?= $badge['color'] ?>; font-size: 0.72rem;">
                                        <?= \app\helpers\SvgHelper::icon(str_replace('bi-', '', $badge['icon'])) ?> <?= Html::encode($badge['label']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold">$<?= number_format($p['current_price'], 2) ?></span>
                                    <small class="d-block <?= $p['distance_resistance_pct'] >= 0 ? 'text-success' : 'text-muted' ?>">
                                        <?= ($p['distance_resistance_pct'] >= 0 ? '+' : '') . number_format($p['distance_resistance_pct'], 1) ?>% vs Breakout
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted d-block"><?= Html::encode($p['structure_desc'] ?? '-') ?></small>
                                    <div class="small">Lantai Support: <strong>$<?= number_format($p['support_base'], 2) ?></strong></div>
                                </td>
                                <td>
                                    <strong class="text-primary">$<?= number_format($p['resistance_line'], 2) ?></strong>
                                    <small class="text-muted d-block">Tinggi: $<?= number_format($p['pattern_height'], 2) ?></small>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>"><?= Html::encode($p['trade_action']) ?></span>
                                </td>
                                <td>
                                    <?php if ($isBullTrap): ?>
                                        <span class="badge bg-danger text-white"><?= \app\helpers\SvgHelper::icon('exclamation-octagon-fill') ?> Bull Trap</span>
                                        <small class="text-danger d-block" style="font-size: 0.7rem;">Anjlok kembali di bawah resist</small>
                                    <?php elseif ($risk === 'HIGH'): ?>
                                        <span class="badge bg-danger">🔴 Risiko Tinggi</span>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">Skor <?= $p['false_breakout_score'] ?? 0 ?>/100</small>
                                    <?php elseif ($risk === 'MEDIUM'): ?>
                                        <span class="badge bg-warning text-dark">🟡 Sedang</span>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">
                                            <?= !empty($p['rejection_wick']) ? 'Upper Wick ' . ($p['upper_wick_pct'] ?? 0) . '%' : (!empty($p['low_volume_breakout']) ? 'RVOL ' . ($p['rvol'] ?? 1) . 'x' : 'Konsolidasi') ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="badge bg-success">🟢 Rendah (Aman)</span>
                                        <small class="text-success d-block" style="font-size: 0.7rem;">Breakout Solid</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">TP1: <strong class="text-success">$<?= number_format($p['tp1_price'], 2) ?></strong></div>
                                    <div class="small">TP2: <strong class="text-success">$<?= number_format($p['tp2_price'], 2) ?></strong> (+<?= $profitTp2 ?>%)</div>
                                </td>
                                <td>
                                    <div class="small text-danger fw-bold">$<?= number_format($p['stop_loss_tight'], 2) ?></div>
                                    <small class="text-muted">Swing: $<?= number_format($p['stop_loss_swing'], 2) ?></small>
                                </td>
                                <td>
                                    <span class="fw-bold <?= ((float)($p['best_rr'] ?? 0)) >= 2.0 ? 'text-success' : 'text-dark' ?>">
                                        <?= number_format((float)($p['best_rr'] ?? 0), 1) ?> : 1
                                    </span>
                                </td>
                                <td>
                                    <span class="<?= ((float)($p['rvol'] ?? 1.0)) >= 1.3 ? 'text-success fw-bold' : 'text-muted' ?>">
                                        <?= number_format((float)($p['rvol'] ?? 1.0), 1) ?>x
                                    </span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 16px; width: 65px;">
                                        <div class="progress-bar <?= $p['confidence'] >= 75 ? 'bg-success' : ($p['confidence'] >= 60 ? 'bg-primary' : 'bg-warning') ?>" role="progressbar" style="width: <?= $p['confidence'] ?>%;">
                                            <?= $p['confidence'] ?>%
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?= Html::a(\app\helpers\SvgHelper::icon('graph-up') . ' Detail &amp; Chart', array_merge(['/bullish-pattern/detail', 'symbol' => $p['symbol']], $filterParams), ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
