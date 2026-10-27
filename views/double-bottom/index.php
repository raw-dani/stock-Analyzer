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
/** @var string $sort */
/** @var array $timeframes */
/** @var array $filterParams */
/** @var array $stats */
/** @var bool $fromCache */
/** @var int $lookbackDays */

use yii\bootstrap5\Html;
use app\services\DoubleBottomService;

$this->title = 'Double Bottom Pattern Scanner';
$this->params['breadcrumbs'][] = $this->title;
$lookbackDays = $lookbackDays ?? $lookback;
$statusFilter = $statusFilter ?? 'all';
$minRr = $minRr ?? 0.0;
$volOnly = !empty($volOnly);

$breakoutCount = count(array_filter($results, fn($r) => !empty($r['breakout'])));
$buyZoneCount = count(array_filter($results, fn($r) => ($r['trade_status'] ?? '') === 'buy_zone'));
$highRrCount = count(array_filter($results, fn($r) => ((float)($r['best_rr'] ?? 0)) >= 2.0));

$stockCount = (int) ($stats['stockCount'] ?? 0);
$priceCount = (int) ($stats['priceCount'] ?? 0);
$intradayCount = (int) ($stats['intradayCount'] ?? 0);
$dailyMax = $stats['dailyMax'] ?? null;
$intradayMaxRaw = $stats['intradayMax'] ?? null;
$intradayMax = is_string($intradayMaxRaw) && $intradayMaxRaw !== '' ? substr($intradayMaxRaw, 0, 16) : '—';
$intradaySyms = (int) ($stats['intradaySyms'] ?? 0);
$realCount = count(array_filter($results, fn($r) => ($r['data_source'] ?? '') === 'intraday'));
$fbCount = count(array_filter($results, fn($r) => ($r['data_source'] ?? '') === 'daily_fallback'));
$isIntradayTf = in_array($timeframe, [1, 2, 4], true);
$staleDays = null;
if (is_string($dailyMax) && $dailyMax !== '') {
    $staleDays = (int) floor((time() - strtotime($dailyMax . ' 23:59:00')) / 86400);
}
$sortLink = fn(string $key, string $label) => Html::a($label . ($sort === $key ? ' ▼' : ''), array_merge(['/double-bottom/index'], $filterParams, ['sort' => $key]), ['class' => 'text-decoration-none text-dark fw-bold']);
$filterWith = fn(array $overrides) => \yii\helpers\Url::to(array_merge(['/double-bottom/index'], $filterParams, $overrides));
?>

<!-- 1. Unified App Hero Header -->
<div class="card border-0 shadow-sm mb-4 app-hero-header">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="app-hero-icon text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: #eef2ff;">
                    <?= \app\helpers\SvgHelper::icon('graph-up-arrow') ?>
                </div>
                <div>
                    <h1 class="app-hero-title h4 fw-bold mb-1 text-dark">
                        <?= Html::encode($this->title) ?>
                    </h1>
                    <p class="app-hero-desc small text-muted mb-0">
                        Pindai pola pembalikan arah W dengan validasi volume agresif, zona entri optimal, dan kalkulasi Risk/Reward presisi.
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

<?php if ($isIntradayTf && $fbCount > 0): ?>
<div class="alert alert-warning py-2 small mb-3">
    <strong>Mode approximasi:</strong>
    <?= $fbCount ?> dari <?= count($results) ?> hasil memakai <em>daily fallback</em>
    (intraday tersedia untuk <?= $intradaySyms ?>/<?= $stockCount ?> saham).
</div>
<?php endif; ?>
<?php if ($staleDays !== null && $staleDays >= 3): ?>
<div class="alert alert-danger py-2 small mb-3">
    <strong>Perhatian:</strong> Data harian terakhir <?= Html::encode((string) $dailyMax) ?> (<?= $staleDays ?> hari lalu).
</div>
<?php endif; ?>

<!-- 2. Unified KPI Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['statusFilter' => 'all']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-indigo <?= $statusFilter === 'all' ? 'kpi-selected border border-primary' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-muted">Pola Terdeteksi</span>
                    <?= \app\helpers\SvgHelper::icon('grid', ['class' => 'text-muted']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-dark"><?= count($results) ?></div>
                <div class="kpi-subtext small text-muted">Semua Pola W Terkonfirmasi</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['statusFilter' => 'buy_zone']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-emerald <?= $statusFilter === 'buy_zone' ? 'kpi-selected border border-success' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-success">Golden Buy Zone (0-3%)</span>
                    <?= \app\helpers\SvgHelper::icon('check-circle', ['class' => 'text-success']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-success"><?= $buyZoneCount ?></div>
                <div class="kpi-subtext small text-success">Optimal Entry Area</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['statusFilter' => 'breakout']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-cyan <?= $statusFilter === 'breakout' ? 'kpi-selected border border-info' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-cyan">Breakout Terkonfirmasi</span>
                    <?= \app\helpers\SvgHelper::icon('rocket-takeoff', ['class' => 'text-info']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-info"><?= $breakoutCount ?></div>
                <div class="kpi-subtext small text-muted">Menembus Neckline Resistance</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['minRr' => '2.0']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-amber <?= (float)$minRr >= 2.0 ? 'kpi-selected border border-warning' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-warning-emphasis">High R:R (&ge; 2.0x)</span>
                    <?= \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-warning']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-dark"><?= $highRrCount ?></div>
                <div class="kpi-subtext small text-muted">Rasio Risiko:Keuntungan Ideal</div>
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
                $filterWith(['statusFilter' => 'all']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($statusFilter === 'all' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('check-circle-fill') . ' In Buy Zone (0-3%)',
                $filterWith(['statusFilter' => 'buy_zone']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($statusFilter === 'buy_zone' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('rocket-takeoff') . ' Terkonfirmasi Breakout',
                $filterWith(['statusFilter' => 'breakout']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($statusFilter === 'breakout' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('arrow-repeat') . ' Retest Neckline',
                $filterWith(['statusFilter' => 'retest']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($statusFilter === 'retest' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('eye') . ' Mendekati Neckline (-3%..0)',
                $filterWith(['statusFilter' => 'approaching']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($statusFilter === 'approaching' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>
        </div>
    </div>

    <!-- Filter Form Body -->
    <div class="card-body p-3 filter-card-body">
        <?= Html::beginForm(['/double-bottom/index'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <input type="hidden" name="timeframe" value="<?= Html::encode($timeframe) ?>">

            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label small fw-bold text-dark mb-1">Status Transaksi</label>
                <?= Html::dropDownList('statusFilter', $statusFilter, [
                    'all' => 'Semua Status',
                    'buy_zone' => '🟢 In Golden Buy Zone (0-3%)',
                    'breakout' => '🚀 Terkonfirmasi Breakout',
                    'retest' => '🔄 Retest Neckline',
                    'approaching' => '👀 Mendekati Breakout (-3%..0)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Min. Risk/Reward</label>
                <?= Html::dropDownList('minRr', $minRr, [
                    '0' => 'Semua R:R',
                    '1.5' => '≥ 1.5 : 1',
                    '2.0' => '≥ 2.0 : 1 (Ideal)',
                    '3.0' => '≥ 3.0 : 1 (Super)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-3 col-md-3 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Urutan (Sort)</label>
                <?= Html::dropDownList('sort', $sort, [
                    'confidence' => 'Score Confidence',
                    'rr' => 'Risk/Reward Tertinggi',
                    'distance' => 'Terdekat ke Neckline',
                    'rvol' => 'Lonjakan Volume (RVOL)',
                    'target' => 'Potensi Gain % Target',
                    'symbol' => 'Simbol Saham',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Lookback (Candle)</label>
                <?= Html::input('number', 'lookback', $lookback, ['class' => 'form-control form-control-sm', 'min' => 5, 'max' => 500]) ?>
            </div>

            <div class="col-lg-2 col-md-6 col-6 d-flex gap-2">
                <?= Html::submitButton(\app\helpers\SvgHelper::icon('search') . ' Pindai', ['class' => 'btn btn-primary btn-sm flex-grow-1 shadow-sm']) ?>
                <?= Html::a(\app\helpers\SvgHelper::icon('arrow-counterclockwise'), ['/double-bottom/index'], ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Reset Filter']) ?>
            </div>

            <!-- Advanced Toggle Details -->
            <div class="col-12 mt-2">
                <details class="bg-light p-2 rounded border">
                    <summary class="small fw-semibold text-secondary" style="cursor: pointer;">Pengaturan Lanjutan (Separasi candle, Neckline depth, Min. Conf, Vol Only)</summary>
                    <div class="row g-2 mt-2 pt-2 border-top">
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold text-dark mb-1">Toleransi Low (%)</label>
                            <?= Html::input('number', 'tolerance', round($tolerancePercent, 1), ['class' => 'form-control form-control-sm', 'min' => 0.1, 'max' => 20, 'step' => 0.1]) ?>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold text-dark mb-1">Separasi Min - Maks</label>
                            <div class="input-group input-group-sm">
                                <?= Html::input('number', 'minSeparation', $minSeparation, ['class' => 'form-control', 'min' => 2, 'title' => 'Separasi minimum']) ?>
                                <?= Html::input('number', 'maxSeparation', $maxSeparation, ['class' => 'form-control', 'min' => 2, 'title' => 'Separasi maksimum']) ?>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold text-dark mb-1">Neckline Min Depth (%)</label>
                            <?= Html::input('number', 'necklineMinDepth', round($necklineMinDepth * 100, 1), ['class' => 'form-control form-control-sm', 'min' => 0, 'max' => 50, 'step' => 0.1]) ?>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold text-dark mb-1">Confidence Minimum</label>
                            <?= Html::input('number', 'minConfidence', $minConfidence, ['class' => 'form-control form-control-sm', 'min' => 0, 'max' => 100]) ?>
                        </div>
                        <div class="col-12 mt-2">
                            <div class="form-check mb-0">
                                <?= Html::checkbox('volOnly', $volOnly, ['class' => 'form-check-input', 'id' => 'volOnlyCheck', 'value' => 1]) ?>
                                <label class="form-check-label small fw-semibold" for="volOnlyCheck">Hanya dengan Konfirmasi Volume (RVOL ≥ 1.3x atau Dry-Up)</label>
                            </div>
                        </div>
                    </div>
                </details>
            </div>
        <?= Html::endForm() ?>
    </div>

<!-- 4. Tabel Hasil Scanner Unified -->
<div class="table-card-unified">
    <div class="table-card-header">
        <div>
            <h5 class="mb-0">
                <?= \app\helpers\SvgHelper::icon('table', ['class' => 'text-primary me-1']) ?> Daftar Peluang Saham Terdeteksi
            </h5>
            <small class="text-muted">Menampilkan <?= count($results) ?> saham teranalisis</small>
        </div>
        <div class="small text-muted">
            TF: <strong><?= DoubleBottomService::getTimeframeLabel($timeframe) ?></strong>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-unified table-hover align-middle mb-0 text-nowrap">
            <thead class="table-light text-muted small text-uppercase">
                <tr>
                        <th class="ps-3">#</th>
                        <th><?= $sortLink('symbol', 'Saham') ?></th>
                        <th class="text-center">Rekomendasi</th>
                        <th>Pola W</th>
                        <th class="text-end">Neckline (Entry)</th>
                        <th class="text-end">Saat Ini</th>
                        <th class="text-center"><?= $sortLink('distance', 'Jarak Neckline') ?></th>
                        <th class="text-end">Target TP1 / TP2</th>
                        <th class="text-end">Stop Loss</th>
                        <th class="text-center"><?= $sortLink('rr', 'R:R Ratio') ?></th>
                        <th class="text-center"><?= $sortLink('rvol', 'Volume') ?></th>
                        <th class="text-end pe-3"><?= $sortLink('confidence', 'Conf.') ?></th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="13" class="text-center text-muted py-5">
                            <?= \app\helpers\SvgHelper::icon('inbox', ['class' => 'fs-2 d-block mb-2']) ?>
                            Tidak ada pola double bottom yang cocok dengan kriteria filter saat ini.<br>
                            <small>Coba longgarkan toleransi atau pilih timeframe lain.</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $i => $r): ?>
                    <?php
                        $toTarget = $r['current_price'] > 0 ? ($r['target_price'] - $r['current_price']) / $r['current_price'] * 100 : 0;
                        $dist = (float) ($r['distance_neckline_pct'] ?? 0);
                        $bestRr = (float) ($r['best_rr'] ?? 0);
                        $tradeStatus = $r['trade_status'] ?? ($r['breakout'] ? 'breakout' : 'forming');
                        $rvol = (float) ($r['rvol'] ?? 1.0);
                        $tightSl = (float) ($r['stop_loss_tight'] ?? ($r['neckline'] * 0.98));
                        $slRiskPct = $r['current_price'] > 0 ? abs(($r['current_price'] - $tightSl) / $r['current_price'] * 100) : 0;
                    ?>
                    <tr>
                        <td class="ps-3 text-muted"><?= $i + 1 ?></td>
                        <td>
                            <strong class="fs-6"><?= Html::encode($r['symbol']) ?></strong>
                            <div class="small text-muted" style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= Html::encode($r['stock_name']) ?>
                            </div>
                            <span class="badge bg-secondary text-white" style="font-size: 0.65rem;"><?= Html::encode($r['sector'] ?? 'Stock') ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($tradeStatus === 'buy_zone'): ?>
                                <span class="badge bg-success px-2 py-1">BUY (Golden Zone)</span>
                            <?php elseif ($tradeStatus === 'retest'): ?>
                                <span class="badge bg-info text-dark px-2 py-1">BUY (Retest)</span>
                            <?php elseif ($tradeStatus === 'extended'): ?>
                                <span class="badge bg-warning text-dark px-2 py-1">HATI-HATI (Extended)</span>
                            <?php elseif ($tradeStatus === 'overextended'): ?>
                                <span class="badge bg-danger px-2 py-1">TUNGGU PULLBACK</span>
                            <?php elseif ($tradeStatus === 'approaching'): ?>
                                <span class="badge bg-primary px-2 py-1">PANTAU BREAKOUT</span>
                            <?php else: ?>
                                <span class="badge bg-secondary px-2 py-1">WAITING</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="small fw-bold">
                                <?php if (($r['pattern_type'] ?? '') === 'Higher Low'): ?>
                                    <span class="text-success">Higher Low 🚀</span>
                                <?php elseif (($r['pattern_type'] ?? '') === 'Lower Low'): ?>
                                    <span class="text-secondary">Shakeout Low</span>
                                <?php else: ?>
                                    <span class="text-primary">Twin Low</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                L1: <?= number_format($r['low1_price'], 2) ?> | L2: <?= number_format($r['low2_price'], 2) ?>
                            </small>
                        </td>
                        <td class="text-end">
                            <strong class="text-dark">$<?= number_format($r['neckline'], 2) ?></strong>
                            <div class="small text-muted">Zone: $<?= number_format($r['entry_zone_min'] ?? $r['neckline'], 2) ?> - $<?= number_format($r['entry_zone_max'] ?? ($r['neckline'] * 1.025), 2) ?></div>
                        </td>
                        <td class="text-end">
                            <strong class="text-primary">$<?= number_format($r['current_price'], 2) ?></strong>
                        </td>
                        <td class="text-center">
                            <?php if ($dist >= 0 && $dist <= 3.0): ?>
                                <span class="badge bg-success text-white">+<?= number_format($dist, 1) ?>%</span>
                            <?php elseif ($dist > 3.0): ?>
                                <span class="badge bg-warning text-dark">+<?= number_format($dist, 1) ?>%</span>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border"><?= number_format($dist, 1) ?>%</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="text-success fw-bold">TP1: $<?= number_format($r['tp1_price'] ?? $r['target_price'], 2) ?></div>
                            <div class="small text-muted">TP2: $<?= number_format($r['tp2_price'] ?? $r['target_price'], 2) ?> (+<?= number_format($toTarget, 1) ?>%)</div>
                        </td>
                        <td class="text-end">
                            <strong class="text-danger">$<?= number_format($tightSl, 2) ?></strong>
                            <div class="small text-muted">-<?= number_format($slRiskPct, 1) ?>%</div>
                        </td>
                        <td class="text-center">
                            <?php if ($bestRr >= 2.0): ?>
                                <span class="badge bg-success px-2 py-1 fs-7"><?= number_format($bestRr, 1) ?>x</span>
                            <?php elseif ($bestRr >= 1.5): ?>
                                <span class="badge bg-warning text-dark px-2 py-1 fs-7"><?= number_format($bestRr, 1) ?>x</span>
                            <?php elseif ($bestRr > 0): ?>
                                <span class="badge bg-light text-dark border px-2 py-1"><?= number_format($bestRr, 1) ?>x</span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($r['volume_confirmed']) || $rvol >= 1.3): ?>
                                <span class="badge bg-success" title="Relative Volume: <?= $rvol ?>x">🔥 RVOL <?= number_format($rvol, 1) ?>x</span>
                            <?php elseif (!empty($r['volume_dry_up'])): ?>
                                <span class="badge bg-info text-dark" title="Volume Low 2 Mengering">💧 Dry-Up</span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">RVOL <?= number_format($rvol, 1) ?>x</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <?php $cc = $r['confidence'] >= 75 ? 'text-success' : ($r['confidence'] >= 55 ? 'text-warning' : 'text-danger'); ?>
                            <span class="<?= $cc ?> fw-bold fs-6"><?= $r['confidence'] ?>%</span>
                        </td>
                        <td class="text-end pe-3">
                            <?= Html::a(\app\helpers\SvgHelper::icon('bar-chart-fill') . ' Detail', array_merge(['double-bottom/detail', 'symbol' => $r['symbol']], $filterParams), ['class' => 'btn btn-sm btn-primary shadow-sm']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Panduan Cara Menggunakan Informasi untuk Transaksi Saham yang Baik -->
<div class="card shadow-sm mt-4 border-0">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-bold"><?= \app\helpers\SvgHelper::icon('lightbulb-fill', ['class' => 'text-warning']) ?> Panduan Eksekusi Trading Double Bottom untuk Hasil Maksimal</h5>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-4">
                <h6 class="fw-bold text-success"><?= \app\helpers\SvgHelper::icon('check-circle-fill') ?> 1. Kapan Harus Masuk Posisi (Entry)?</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Golden Buy Zone:</strong> Masuk saat harga berada di antara <code>Neckline</code> s/d <code>Neckline + 2.5%</code> sesaat setelah breakout terkonfirmasi.</li>
                    <li><strong>Retest Entry:</strong> Jika harga sudah terlanjur naik tinggi, JANGAN KEJAR! Tunggu harga kembali menguji neckline dengan volume mengecil.</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-danger"><?= \app\helpers\SvgHelper::icon('shield-fill-x') ?> 2. Disiplin Stop Loss</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Tight Stop Loss (2% di bawah Neckline):</strong> Jika harga berbalik turun menembus ke bawah neckline, itu adalah <em>False Breakout</em>. Batasi risiko dan keluar dengan kerugian minimal.</li>
                    <li>Hindari membiarkan floating loss melewati level support Low 1 &amp; Low 2.</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-primary"><?= \app\helpers\SvgHelper::icon('cash-stack') ?> 3. Ambil Keuntungan Bertahap (Scaling Out)</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>TP1 (Conservative):</strong> Jual 50% lot saat mencapai TP1, lalu segera pindahkan Stop Loss ke titik impas (Breakeven) untuk <em>Free Trade</em>.</li>
                    <li><strong>TP2 (Full Measured Move):</strong> Likuidasi sisa posisi di target teknikal penuh.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

