<?php

/** @var yii\web\View $this */
/** @var array $results */
/** @var int $timeframe */
/** @var int $lookback */
/** @var float $tolerance */
/** @var float $tolerancePercent */
/** @var int $minSeparation */
/** @var int $maxSeparation */
/** @var float $necklineMinDepth */
/** @var bool $breakoutOnly */
/** @var int $minConfidence */
/** @var string $statusFilter */
/** @var float $minRr */
/** @var bool $volOnly */
/** @var string $patternType */
/** @var string $falseBreakoutFilter */
/** @var string $sort */
/** @var array $timeframes */
/** @var array $patternTypes */
/** @var array $falseBreakoutFilters */
/** @var array $filterParams */
/** @var array $stats */
/** @var bool $fromCache */
/** @var int $lookbackDays */
/** @var int $tbCount */
/** @var int $ihsCount */
/** @var int $shakeoutCount */
/** @var int $bullTrapCount */
/** @var int $safeBreakoutCount */

use yii\bootstrap5\Html;
use app\services\TripleBottomService;

$this->title = 'Triple Bottom & Inverted Head-and-Shoulders Scanner';
$this->params['breadcrumbs'][] = $this->title;
$lookbackDays = $lookbackDays ?? $lookback;
$statusFilter = $statusFilter ?? 'all';
$patternType = $patternType ?? TripleBottomService::PATTERN_ALL;
$falseBreakoutFilter = $falseBreakoutFilter ?? TripleBottomService::RISK_ALL;
$minRr = $minRr ?? 0.0;
$volOnly = !empty($volOnly);

$breakoutCount = count(array_filter($results, fn($r) => !empty($r['breakout'])));
$buyZoneCount = count(array_filter($results, fn($r) => in_array($r['trade_status'] ?? '', ['buy_zone', 'retest', 'bottom3_bounce'], true)));
$highRrCount = count(array_filter($results, fn($r) => ((float)($r['best_rr'] ?? 0)) >= 2.0));
$tbCount = $tbCount ?? count(array_filter($results, fn($r) => ($r['pattern_type'] ?? '') === TripleBottomService::PATTERN_TRIPLE_BOTTOM));
$ihsCount = $ihsCount ?? count(array_filter($results, fn($r) => ($r['pattern_type'] ?? '') === TripleBottomService::PATTERN_INVERSE_HS));
$shakeoutCount = $shakeoutCount ?? count(array_filter($results, fn($r) => ($r['pattern_type'] ?? '') === TripleBottomService::PATTERN_SHAKEOUT_LOW || !empty($r['is_shakeout_low'])));
$bullTrapCount = $bullTrapCount ?? count(array_filter($results, fn($r) => !empty($r['bull_trap_detected'])));

$stockCount = (int) ($stats['stockCount'] ?? 0);
$sortLink = fn(string $key, string $label) => Html::a($label . ($sort === $key ? ' ▼' : ''), array_merge(['triple-bottom/index'], $filterParams, ['sort' => $key]), ['class' => 'text-decoration-none text-dark fw-bold']);
$filterWith = fn(array $overrides) => \yii\helpers\Url::to(array_merge(['/triple-bottom/index'], $filterParams, $overrides));
?>

<!-- 1. Unified App Hero Header -->
<div class="app-hero-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="app-hero-icon text-warning" style="background: rgba(245, 158, 11, 0.1);">
                <?= \app\helpers\SvgHelper::icon('graph-up') ?>
            </div>
            <div>
                <h1 class="app-hero-title">
                    <?= Html::encode($this->title) ?>
                </h1>
                <p class="app-hero-desc">
                    Deteksi pola 3 lembah (Triple Bottom &amp; Inverted H&amp;S) dengan timing presisi Beli/Jual dan proteksi risiko False Breakout (Bull Trap).
                </p>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Timeframe Group -->
            <div class="btn-group-tf">
                <?php foreach ($timeframes as $tfKey => $tfLabel): ?>
                    <?= Html::a(
                        Html::encode($tfLabel),
                        $filterWith(['timeframe' => $tfKey]),
                        ['class' => 'btn-tf ' . ((int) $timeframe === (int) $tfKey ? 'active' : '')]
                    ) ?>
                <?php endforeach; ?>
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
        <a href="<?= $filterWith(['patternType' => \app\services\TripleBottomService::PATTERN_ALL, 'statusFilter' => 'all']) ?>" class="kpi-card-unified kpi-accent-indigo <?= ($statusFilter === 'all' && $patternType === \app\services\TripleBottomService::PATTERN_ALL) ? 'kpi-selected' : '' ?>">
            <div class="kpi-header">
                <span class="kpi-label">Pola Ditemukan</span>
                <?= \app\helpers\SvgHelper::icon('grid', ['class' => 'text-muted']) ?>
            </div>
            <div class="kpi-value"><?= count($results) ?></div>
            <div class="kpi-subtext">TB: <?= $tbCount ?> | IHS: <?= $ihsCount ?></div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['statusFilter' => 'buy_zone']) ?>" class="kpi-card-unified kpi-accent-emerald <?= $statusFilter === 'buy_zone' ? 'kpi-selected' : '' ?>">
            <div class="kpi-header">
                <span class="kpi-label text-success">Zona Beli (Buy Zones)</span>
                <?= \app\helpers\SvgHelper::icon('check-circle', ['class' => 'text-success']) ?>
            </div>
            <div class="kpi-value text-success"><?= $buyZoneCount ?></div>
            <div class="kpi-subtext text-success">Optimal Entry Area</div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['statusFilter' => 'breakout']) ?>" class="kpi-card-unified kpi-accent-cyan <?= $statusFilter === 'breakout' ? 'kpi-selected' : '' ?>">
            <div class="kpi-header">
                <span class="kpi-label text-cyan">Breakout Tembus</span>
                <?= \app\helpers\SvgHelper::icon('rocket-takeoff', ['class' => 'text-info']) ?>
            </div>
            <div class="kpi-value text-info"><?= $breakoutCount ?></div>
            <div class="kpi-subtext">
                <?php if ($bullTrapCount > 0): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0">⚠️ <?= $bullTrapCount ?> Bull Trap</span>
                <?php else: ?>
                    <span class="text-success small">✓ Bebas Bull Trap</span>
                <?php endif; ?>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['minRr' => '2.0']) ?>" class="kpi-card-unified kpi-accent-amber <?= (float)$minRr >= 2.0 ? 'kpi-selected' : '' ?>">
            <div class="kpi-header">
                <span class="kpi-label text-warning-emphasis">Risk/Reward Ideal (&ge; 2.0)</span>
                <?= \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-warning']) ?>
            </div>
            <div class="kpi-value text-dark"><?= $highRrCount ?></div>
            <div class="kpi-subtext">Potensi Profit &ge; 2x Risiko</div>
        </a>
    </div>
</div>

<!-- 3. Unified Filter Card & Presets -->
<div class="filter-card-unified">
    <!-- Preset Pills Bar -->
    <div class="preset-pills-bar">
        <span class="small fw-bold text-muted me-1">
            <?= \app\helpers\SvgHelper::icon('sliders2', ['class' => 'text-primary']) ?> Preset Cepat:
        </span>
        
        <?= Html::a(
            \app\helpers\SvgHelper::icon('grid-fill') . ' Semua Pola',
            $filterWith(['statusFilter' => 'all']),
            ['class' => 'preset-pill ' . ($statusFilter === 'all' ? 'active' : '')]
        ) ?>

        <?= Html::a(
            \app\helpers\SvgHelper::icon('check-circle-fill') . ' In Buy Zone (0-3%)',
            $filterWith(['statusFilter' => 'buy_zone']),
            ['class' => 'preset-pill ' . ($statusFilter === 'buy_zone' ? 'active' : '')]
        ) ?>

        <?= Html::a(
            \app\helpers\SvgHelper::icon('arrow-repeat') . ' Retest Neckline',
            $filterWith(['statusFilter' => 'retest']),
            ['class' => 'preset-pill ' . ($statusFilter === 'retest' ? 'active' : '')]
        ) ?>

        <?= Html::a(
            \app\helpers\SvgHelper::icon('lightning-fill') . ' Bottom 3 Bounce',
            $filterWith(['statusFilter' => 'bottom3_bounce']),
            ['class' => 'preset-pill ' . ($statusFilter === 'bottom3_bounce' ? 'active' : '')]
        ) ?>

        <?= Html::a(
            \app\helpers\SvgHelper::icon('rocket-takeoff') . ' Terkonfirmasi Breakout',
            $filterWith(['statusFilter' => 'breakout']),
            ['class' => 'preset-pill ' . ($statusFilter === 'breakout' ? 'active' : '')]
        ) ?>
    </div>

    <!-- Filter Form -->
    <?= Html::beginForm(['triple-bottom/index'], 'get', ['class' => 'row g-2 align-items-end']) ?>
        <input type="hidden" name="timeframe" value="<?= Html::encode($timeframe) ?>">

        <div class="col-md-3 col-6">
            <label class="form-label small text-muted mb-1">Tipe Pola Reversal</label>
            <?= Html::dropDownList('patternType', $patternType, $patternTypes, ['class' => 'form-select form-select-sm']) ?>
        </div>

        <div class="col-md-3 col-6">
            <label class="form-label small text-muted mb-1">Status Eksekusi</label>
            <?= Html::dropDownList('statusFilter', $statusFilter, [
                'all' => 'Semua Status Sinyal',
                'buy_zone' => '🟢 In Breakout Buy Zone (0-3%)',
                'retest' => '🔄 Retest Neckline (Peluang Beli)',
                'bottom3_bounce' => '⚡ Bottom 3 Bounce (Beli Awal)',
                'breakout' => '🚀 Terkonfirmasi Breakout',
                'approaching' => '👀 Mendekati Neckline Resistance',
            ], ['class' => 'form-select form-select-sm']) ?>
        </div>

        <div class="col-md-2 col-6">
            <label class="form-label small text-muted mb-1">Risiko False Breakout</label>
            <?= Html::dropDownList('falseBreakoutFilter', $falseBreakoutFilter, $falseBreakoutFilters, ['class' => 'form-select form-select-sm']) ?>
        </div>

        <div class="col-md-2 col-6">
            <label class="form-label small text-muted mb-1">Min. R:R</label>
            <?= Html::dropDownList('minRr', $minRr, [
                '0' => 'Semua R:R',
                '1.5' => '≥ 1.5 : 1',
                '2.0' => '≥ 2.0 : 1 (Ideal)',
                '3.0' => '≥ 3.0 : 1 (Super)',
            ], ['class' => 'form-select form-select-sm']) ?>
        </div>

        <div class="col-md-2 col-6">
            <label class="form-label small text-muted mb-1">Urutan (Sort)</label>
            <?= Html::dropDownList('sort', $sort, [
                'confidence' => 'Score Confidence',
                'rr' => 'Risk/Reward Tertinggi',
                'distance' => 'Terdekat ke Neckline',
                'rvol' => 'Lonjakan Volume (RVOL)',
                'target' => 'Potensi Profit Terbesar',
                'symbol' => 'Simbol (A-Z)',
            ], ['class' => 'form-select form-select-sm']) ?>
        </div>

        <div class="col-md-2 col-6">
            <label class="form-label small text-muted mb-1">Lookback (Bars)</label>
            <?= Html::input('number', 'lookback', $lookbackDays, ['class' => 'form-control form-control-sm', 'min' => 15, 'max' => 500]) ?>
        </div>

        <div class="col-md-2 col-6">
            <label class="form-label small text-muted mb-1">Toleransi Level (%)</label>
            <?= Html::input('number', 'tolerance', round($tolerancePercent, 1), ['class' => 'form-control form-control-sm', 'min' => 0.1, 'max' => 20, 'step' => 0.1]) ?>
        </div>

        <div class="col-md-2 col-6">
            <label class="form-label small text-muted mb-1">Min. Confidence (%)</label>
            <?= Html::input('number', 'minConfidence', $minConfidence, ['class' => 'form-control form-control-sm', 'min' => 0, 'max' => 100, 'step' => 5]) ?>
        </div>

        <div class="col-md-3 col-6 d-flex align-items-center gap-2 pt-3">
            <div class="form-check mb-0">
                <?= Html::checkbox('breakoutOnly', $breakoutOnly, ['class' => 'form-check-input', 'id' => 'tb-cb-bo']) ?>
                <label class="form-check-label small" for="tb-cb-bo">Hanya Breakout</label>
            </div>
            <div class="form-check mb-0">
                <?= Html::checkbox('volOnly', $volOnly, ['class' => 'form-check-input', 'id' => 'tb-cb-vol']) ?>
                <label class="form-check-label small" for="tb-cb-vol">Volume</label>
            </div>
        </div>

        <div class="col-md-3 col-12 d-flex justify-content-end gap-1">
            <?= Html::submitButton(\app\helpers\SvgHelper::icon('search') . ' Pindai', ['class' => 'btn btn-primary btn-sm flex-grow-1']) ?>
            <?= Html::a(\app\helpers\SvgHelper::icon('arrow-counterclockwise'), ['triple-bottom/index'], ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Reset Filter']) ?>
        </div>
    <?= Html::endForm() ?>
</div>

<!-- 4. Tabel Hasil Scanner Unified -->
<div class="table-card-unified">
    <div class="table-card-header">
        <div>
            <h5 class="mb-0">
                <?= \app\helpers\SvgHelper::icon('table', ['class' => 'text-primary me-1']) ?> Daftar Saham Pola Reversal (Triple Bottom &amp; Inverted H&amp;S)
            </h5>
            <small class="text-muted">Menampilkan <?= count($results) ?> saham teranalisis</small>
        </div>
        <div class="small text-muted">
            TF: <strong><?= TripleBottomService::getTimeframeLabel($timeframe) ?></strong>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($results)): ?>
            <div class="p-4 text-center text-muted">
                <?= \app\helpers\SvgHelper::icon('inbox', ['class' => 'fs-1 d-block mb-2']) ?>
                <p class="mb-0">Tidak ada saham yang memenuhi kriteria pola saat ini. Coba ubah filter jenis pola, perbesar toleransi (misal 4-5%), atau ubah timeframe.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-unified table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th><?= $sortLink('symbol', 'Saham & Pola') ?></th>
                            <th>Harga Saat Ini</th>
                            <th>Support Lembah</th>
                            <th>Neckline</th>
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
                            $status = $p['trade_status'] ?? '';
                            $badgeClass = 'bg-secondary';
                            if ($status === 'buy_zone') $badgeClass = 'bg-success';
                            elseif ($status === 'retest') $badgeClass = 'bg-info text-dark';
                            elseif ($status === 'bottom3_bounce') $badgeClass = 'bg-primary';
                            elseif ($status === 'extended') $badgeClass = 'bg-warning text-dark';
                            elseif ($status === 'overextended') $badgeClass = 'bg-danger';
                            elseif ($status === 'approaching') $badgeClass = 'bg-info text-white';

                            $profitTp2 = $p['current_price'] > 0 ? round((($p['tp2_price'] - $p['current_price']) / $p['current_price']) * 100, 1) : 0;
                            $isIhs = ($p['pattern_type'] ?? '') === TripleBottomService::PATTERN_INVERSE_HS;
                            $risk = $p['false_breakout_risk'] ?? 'LOW';
                            $isBullTrap = !empty($p['bull_trap_detected']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= Html::encode($p['symbol']) ?></strong>
                                    <small class="text-muted d-block"><?= Html::encode($p['sector'] ?? '-') ?></small>
                                    <?php if ($isIhs): ?>
                                        <span class="badge" style="background-color: #6f42c1; font-size: 0.7rem;" title="Inverted Head and Shoulders"><?= \app\helpers\SvgHelper::icon('person-arms-up') ?> Inverted H&amp;S</span>
                                    <?php elseif (($p['pattern_type'] ?? '') === TripleBottomService::PATTERN_SHAKEOUT_LOW || !empty($p['is_shakeout_low'])): ?>
                                        <span class="badge bg-secondary text-white" style="font-size: 0.7rem;" title="Triple Bottom dengan Shakeout Low (Liquidity Sweep)"><?= \app\helpers\SvgHelper::icon('lightning-charge') ?> Shakeout Low</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary" style="font-size: 0.7rem;" title="Triple Bottom (3 Troughs)"><?= \app\helpers\SvgHelper::icon('triangle-half') ?> Triple Bottom</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold">$<?= number_format($p['current_price'], 2) ?></span>
                                    <small class="d-block <?= $p['distance_neckline_pct'] >= 0 ? 'text-success' : 'text-muted' ?>">
                                        <?= ($p['distance_neckline_pct'] >= 0 ? '+' : '') . number_format($p['distance_neckline_pct'], 1) ?>% vs Neckline
                                    </small>
                                </td>
                                <td>
                                    <?php if ($isIhs): ?>
                                        <div class="small">Bahu Kiri: <strong>$<?= number_format($p['low1_price'], 2) ?></strong></div>
                                        <div class="small">Kepala: <strong class="text-primary">$<?= number_format($p['low2_price'], 2) ?></strong> (-<?= $p['head_drop_left_pct'] ?? 0 ?>%)</div>
                                        <div class="small">Bahu Kanan: <strong>$<?= number_format($p['low3_price'], 2) ?></strong></div>
                                    <?php else: ?>
                                        <span class="small">$<?= number_format($p['low1_price'], 2) ?> | $<?= number_format($p['low2_price'], 2) ?> | $<?= number_format($p['low3_price'], 2) ?></span>
                                        <small class="text-muted d-block">Avg: $<?= number_format($p['avg_bottom'], 2) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>$<?= number_format($p['neckline'], 2) ?></strong>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>"><?= Html::encode($p['trade_action']) ?></span>
                                </td>
                                <td>
                                    <?php if ($isBullTrap): ?>
                                        <span class="badge bg-danger text-white"><?= \app\helpers\SvgHelper::icon('exclamation-octagon-fill') ?> Bull Trap</span>
                                        <small class="text-danger d-block" style="font-size: 0.7rem;">Anjlok kembali di bawah neckline</small>
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
                                    <small class="text-muted">Swing: $<?= number_format($p['stop_loss'], 2) ?></small>
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
                                    <?= Html::a(\app\helpers\SvgHelper::icon('graph-up') . ' Detail &amp; Chart', array_merge(['triple-bottom/detail', 'symbol' => $p['symbol']], $filterParams), ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

