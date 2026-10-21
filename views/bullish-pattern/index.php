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
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">
            <?= \app\helpers\SvgHelper::icon('rocket-takeoff', ['class' => 'text-primary']) ?> <?= Html::encode($this->title) ?>
        </h1>
        <small class="text-muted">
            Deteksi posisi Beli &amp; Jual ideal dengan <strong>Cup &amp; Handle, Bullish Rectangle, Bullish Pennant, Bullish Flag, dan Ascending Triangle</strong>
        </small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-primary px-3 py-2 fs-7"><?= BullishPatternService::getTimeframeLabel($timeframe) ?></span>
    </div>
</div>

<?= $this->render('//layouts/_sync_status_badge', [
    'fromCache' => $fromCache ?? false,
    'timeframe' => (int) ($timeframe ?? 24),
]) ?>

<!-- Form Filter & Parameter -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light py-2">
        <strong class="text-secondary"><?= \app\helpers\SvgHelper::icon('funnel') ?> Parameter Filter &amp; Jenis Pola Bullish</strong>
    </div>
    <div class="card-body">
        <?= Html::beginForm(['/bullish-pattern/index'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Timeframe</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small fw-bold">Jenis Pola Bullish</label>
                <?= Html::dropDownList('patternType', $patternType, $patternTypes, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small fw-bold">Status Eksekusi (Timing)</label>
                <?= Html::dropDownList('statusFilter', $statusFilter, $statusOptions, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Risiko False Breakout</label>
                <?= Html::dropDownList('falseBreakoutFilter', $falseBreakoutFilter, $falseBreakoutFilters, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Min. R:R</label>
                <?= Html::dropDownList('minRr', $minRr, [
                    '0' => 'Semua R:R',
                    '1.5' => '≥ 1.5 : 1',
                    '2.0' => '≥ 2.0 : 1 (Ideal)',
                    '3.0' => '≥ 3.0 : 1 (Super)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Urutkan (Sorting)</label>
                <?= Html::dropDownList('sort', $sort, [
                    'confidence' => 'Score Confidence',
                    'rr' => 'Risk/Reward Tertinggi',
                    'distance' => 'Terdekat ke Breakout',
                    'rvol' => 'Lonjakan Volume (RVOL)',
                    'target' => 'Potensi Profit Terbesar',
                    'symbol' => 'Simbol (A-Z)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Lookback (Bars)</label>
                <?= Html::input('number', 'lookback', $lookbackDays, ['class' => 'form-control form-control-sm', 'min' => 15, 'max' => 500]) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Min. Confidence (%)</label>
                <?= Html::input('number', 'minConfidence', $minConfidence, ['class' => 'form-control form-control-sm', 'min' => 0, 'max' => 100, 'step' => 5]) ?>
            </div>
            <div class="col-md-4 col-12 d-flex align-items-center gap-3">
                <div class="form-check">
                    <?= Html::checkbox('breakoutOnly', $breakoutOnly, ['class' => 'form-check-input', 'id' => 'bp-cb-bo']) ?>
                    <label class="form-check-label small" for="bp-cb-bo">Hanya yang sudah Breakout</label>
                </div>
                <div class="form-check">
                    <?= Html::checkbox('volOnly', $volOnly, ['class' => 'form-check-input', 'id' => 'bp-cb-vol']) ?>
                    <label class="form-check-label small" for="bp-cb-vol">Konfirmasi Volume</label>
                </div>
            </div>
            <div class="col-md-2 col-12 d-flex justify-content-end gap-2">
                <?= Html::submitButton(\app\helpers\SvgHelper::icon('search') . ' Pindai Saham', ['class' => 'btn btn-sm btn-primary px-3']) ?>
                <?= Html::a(\app\helpers\SvgHelper::icon('x-circle') . ' Reset', ['/bullish-pattern/index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10 text-primary">
            <div class="card-body py-3">
                <div class="text-uppercase small fw-bold">Total Pola Ditemukan</div>
                <div class="fs-3 fw-bold"><?= count($results) ?> <small class="fs-6 text-muted fw-normal">/ <?= $stockCount ?> saham</small></div>
                <div class="d-flex flex-wrap gap-1 mt-1">
                    <span class="badge" style="background-color: #fd7e14;" title="Cup & Handle">Cup: <?= $cupCount ?></span>
                    <span class="badge" style="background-color: #198754;" title="Bullish Flag">Flag: <?= $flagCount ?></span>
                    <span class="badge" style="background-color: #0dcaf0; color: #000;" title="Bullish Pennant">Pennant: <?= $pennantCount ?></span>
                    <span class="badge" style="background-color: #6f42c1;" title="Ascending Triangle">Triangle: <?= $triangleCount ?></span>
                    <span class="badge" style="background-color: #20c997;" title="Bullish Rectangle">Box: <?= $rectCount ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 text-success">
            <div class="card-body py-3">
                <div class="text-uppercase small fw-bold">Zona Beli (Buy Zones)</div>
                <div class="fs-3 fw-bold"><?= $buyZoneCount ?> <small class="fs-6 fw-normal">siap entri</small></div>
                <div class="small mt-1 text-muted">
                    Breakout, Retest &bull; <span class="badge bg-warning text-dark">⚡ <?= $shakeoutCount ?? 0 ?> Shakeout</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm bg-info bg-opacity-10 text-info">
            <div class="card-body py-3">
                <div class="text-uppercase small fw-bold">Breakout Tembus</div>
                <div class="fs-3 fw-bold"><?= $breakoutCount ?> <small class="fs-6 fw-normal">konfirmasi</small></div>
                <div class="small mt-1 text-danger">
                    <?php if ($bullTrapCount > 0): ?>
                        <span class="badge bg-danger">⚠️ <?= $bullTrapCount ?> Bull Trap</span>
                    <?php else: ?>
                        <span class="badge bg-success">0 Bull Trap (Aman)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10 text-dark">
            <div class="card-body py-3">
                <div class="text-uppercase small fw-bold">Risk/Reward Ideal (≥2.0)</div>
                <div class="fs-3 fw-bold"><?= $highRrCount ?> <small class="fs-6 fw-normal">saham</small></div>
                <div class="small mt-1 text-muted">Potensi profit &ge; 2x resiko kerugian</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Hasil Scanner -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><?= \app\helpers\SvgHelper::icon('table', ['class' => 'text-primary']) ?> Daftar Saham dengan Pola Bullish Reversal &amp; Continuation</h5>
        <span class="badge bg-secondary"><?= count($results) ?> hasil</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($results)): ?>
            <div class="p-4 text-center text-muted">
                <?= \app\helpers\SvgHelper::icon('inbox', ['class' => 'fs-1 d-block mb-2']) ?>
                <p class="mb-0">Tidak ada saham yang memenuhi kriteria pola saat ini. Coba pilih filter "Semua 5 Pola Bullish", perkecil Min R:R, atau ubah timeframe.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
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
