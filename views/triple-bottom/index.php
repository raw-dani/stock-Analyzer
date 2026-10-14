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
$bullTrapCount = $bullTrapCount ?? count(array_filter($results, fn($r) => !empty($r['bull_trap_detected'])));

$stockCount = (int) ($stats['stockCount'] ?? 0);
$sortLink = fn(string $key, string $label) => Html::a($label . ($sort === $key ? ' ▼' : ''), array_merge(['triple-bottom/index'], $filterParams, ['sort' => $key]), ['class' => 'text-decoration-none text-dark fw-bold']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-graph-up text-primary"></i> <?= Html::encode($this->title) ?></h1>
        <small class="text-muted">Deteksi pola 3 lembah (Triple Bottom &amp; Inverted H&amp;S) dengan timing presisi Beli/Jual dan proteksi risiko False Breakout (Bull Trap)</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($fromCache): ?><span class="badge bg-secondary" title="Hasil dari cache 15 menit">Cache 15m</span><?php endif; ?>
        <span class="badge bg-primary px-3 py-2 fs-7"><?= TripleBottomService::getTimeframeLabel($timeframe) ?></span>
    </div>
</div>

<!-- Form Filter & Parameter -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light py-2">
        <strong class="text-secondary"><i class="bi bi-funnel"></i> Parameter Scanner &amp; Filter Trading</strong>
    </div>
    <div class="card-body">
        <?= Html::beginForm(['triple-bottom/index'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Timeframe</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small fw-bold">Tipe Pola Reversal</label>
                <?= Html::dropDownList('patternType', $patternType, $patternTypes, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small fw-bold">Risiko False Breakout</label>
                <?= Html::dropDownList('falseBreakoutFilter', $falseBreakoutFilter, $falseBreakoutFilters, ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Status Eksekusi</label>
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
                    'distance' => 'Terdekat ke Neckline',
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
                <label class="form-label small fw-bold">Toleransi Level (%)</label>
                <?= Html::input('number', 'tolerance', round($tolerancePercent, 1), ['class' => 'form-control form-control-sm', 'min' => 0.1, 'max' => 20, 'step' => 0.1]) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Min. Confidence (%)</label>
                <?= Html::input('number', 'minConfidence', $minConfidence, ['class' => 'form-control form-control-sm', 'min' => 0, 'max' => 100, 'step' => 5]) ?>
            </div>
            <div class="col-md-4 col-12 d-flex align-items-center gap-3">
                <div class="form-check">
                    <?= Html::checkbox('breakoutOnly', $breakoutOnly, ['class' => 'form-check-input', 'id' => 'tb-cb-bo']) ?>
                    <label class="form-check-label small" for="tb-cb-bo">Hanya Breakout</label>
                </div>
                <div class="form-check">
                    <?= Html::checkbox('volOnly', $volOnly, ['class' => 'form-check-input', 'id' => 'tb-cb-vol']) ?>
                    <label class="form-check-label small" for="tb-cb-vol">Konfirmasi Volume</label>
                </div>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <?= Html::submitButton('<i class="bi bi-search"></i> Pindai Saham', ['class' => 'btn btn-sm btn-primary px-3']) ?>
                <?= Html::a('<i class="bi bi-x-circle"></i> Reset', ['triple-bottom/index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10 text-primary">
            <div class="card-body py-3">
                <div class="text-uppercase small fw-bold">Pola Ditemukan</div>
                <div class="fs-3 fw-bold"><?= count($results) ?> <small class="fs-6 text-muted fw-normal">/ <?= $stockCount ?> saham</small></div>
                <div class="small mt-1 text-dark">
                    <span class="badge bg-primary me-1">TB: <?= $tbCount ?></span>
                    <span class="badge bg-info text-dark">IHS: <?= $ihsCount ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 text-success">
            <div class="card-body py-3">
                <div class="text-uppercase small fw-bold">Zona Beli (Buy Zones)</div>
                <div class="fs-3 fw-bold"><?= $buyZoneCount ?> <small class="fs-6 fw-normal">siap entri</small></div>
                <div class="small mt-1 text-muted">Optimal R:R &amp; jarak aman</div>
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
                        <span class="badge bg-success">0 Bull Trap</span>
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
                <div class="small mt-1 text-muted">Potensi profit &ge; 2x resiko</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Hasil Scanner -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="bi bi-table text-primary"></i> Daftar Saham dengan Pola Reversal (Triple Bottom &amp; Inverted H&amp;S)</h5>
        <span class="badge bg-secondary"><?= count($results) ?> hasil</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($results)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                <p class="mb-0">Tidak ada saham yang memenuhi kriteria pola saat ini. Coba ubah filter jenis pola, perbesar toleransi (misal 4-5%), atau ubah timeframe.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
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
                                        <span class="badge" style="background-color: #6f42c1; font-size: 0.7rem;" title="Inverted Head and Shoulders"><i class="bi bi-person-arms-up"></i> Inverted H&amp;S</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary" style="font-size: 0.7rem;" title="Triple Bottom (3 Troughs)"><i class="bi bi-triangle-half"></i> Triple Bottom</span>
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
                                        <span class="badge bg-danger text-white"><i class="bi bi-exclamation-octagon-fill"></i> Bull Trap</span>
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
                                    <?= Html::a('<i class="bi bi-graph-up"></i> Detail &amp; Chart', array_merge(['triple-bottom/detail', 'symbol' => $p['symbol']], $filterParams), ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

