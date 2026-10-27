<?php

/** @var yii\web\View $this */
/** @var array $results */
/** @var array $params */
/** @var array $stats */
/** @var array $symbols */
/** @var array $tvSymbols */
/** @var array $timeframes */
/** @var array $presets */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Bollinger Bands Strategy & Position Scanner';
$this->params['breadcrumbs'][] = 'Bollinger Bands Strategy';

$symbolsStr = implode(', ', $symbols);
$tvSymbolsStr = implode(', ', $tvSymbols);

$currentGet = Yii::$app->request->get();
$filterWith = fn(array $overrides) => Url::to(array_merge(['/bollinger-band/index'], $currentGet, $overrides));

$badgeAction = [
    'STRONG_BUY' => 'success',
    'BUY' => 'success',
    'HOLD' => 'primary',
    'SELL' => 'danger',
    'TAKE_PROFIT' => 'warning text-dark',
    'WATCH' => 'secondary',
];
?>

<!-- 1. Unified App Hero Header -->
<div class="app-hero-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="app-hero-icon">
                <?= \app\helpers\SvgHelper::icon('activity') ?>
            </div>
            <div>
                <h1 class="app-hero-title">
                    <?= Html::encode($this->title) ?>
                </h1>
                <p class="app-hero-desc">
                    Penentuan waktu beli (<em>entry</em>), waktu jual (<em>take profit / exit</em>), dan deteksi <strong>⚡ Shakeout Low (Liquidity Sweep)</strong> presisi berbasis Bollinger Bands (20, 2.0).
                </p>
            </div>
        </div>
<!-- 1. Unified App Hero Header -->
<div class="card border-0 shadow-sm mb-4 app-hero-header">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="app-hero-icon text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: #eef2ff;">
                    <?= \app\helpers\SvgHelper::icon('bounding-box') ?>
                </div>
                <div>
                    <h1 class="app-hero-title h4 fw-bold mb-1 text-dark">
                        <?= Html::encode($this->title) ?>
                    </h1>
                    <p class="app-hero-desc small text-muted mb-0">
                        Strategi pembalikan &amp; breakout volatilitas: <strong>Shakeout Low</strong>, <strong>Squeeze Breakout</strong>, dan <strong>Upper Reversal</strong>.
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
                            ['class' => 'btn-tf btn btn-sm rounded-pill px-3 ' . ((int) $params['timeframe'] === $tfKey ? 'active btn-primary text-white shadow-sm' : 'btn-light text-secondary border-0')]
                        ) ?>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill shadow-xs" data-bs-toggle="modal" data-bs-target="#tvModal" <?= empty($tvSymbols) ? 'disabled' : '' ?>>
                    <?= \app\helpers\SvgHelper::icon('tv') ?> TradingView (<?= count($tvSymbols) ?>)
                </button>

                <?= Html::a(
                    \app\helpers\SvgHelper::icon('download') . ' Export CSV',
                    Url::to(array_merge(['/bollinger-band/export'], $currentGet)),
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
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= $filterWith(['strategy' => 'all']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-indigo <?= $params['strategy'] === 'all' ? 'kpi-selected border border-primary' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-muted">Total Saham</span>
                    <?= \app\helpers\SvgHelper::icon('grid', ['class' => 'text-muted']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-dark"><?= number_format((int) ($stats['total'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-muted">Semua Hasil Scan</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= $filterWith(['strategy' => 'buy_all']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-emerald <?= $params['strategy'] === 'buy_all' ? 'kpi-selected border border-success' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-success">Sinyal Beli (Buy)</span>
                    <?= \app\helpers\SvgHelper::icon('check-circle', ['class' => 'text-success']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-success"><?= number_format((int) ($stats['buyCount'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-success">Setup Beli Aktif</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-lg-3">
        <a href="<?= $filterWith(['strategy' => 'shakeout_low']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-amber <?= $params['strategy'] === 'shakeout_low' ? 'kpi-selected border border-warning' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-warning-emphasis">
                        <?= \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-warning me-1']) ?>Shakeout Low
                    </span>
                    <span class="badge bg-warning-subtle text-warning-emphasis px-1 py-0" style="font-size:0.65rem;">HIGH WINRATE</span>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-dark"><?= number_format((int) ($stats['shakeoutCount'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-muted">Liquidity Sweep Bawah Band</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= $filterWith(['strategy' => 'squeeze_breakout']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-cyan <?= $params['strategy'] === 'squeeze_breakout' ? 'kpi-selected border border-info' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-cyan">Squeeze Breakout</span>
                    <?= \app\helpers\SvgHelper::icon('box-arrow-up-right', ['class' => 'text-info']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-info"><?= number_format((int) ($stats['squeezeCount'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-muted">Ledakan Volatilitas</div>
            </div>
        </a>
    </div>

    <div class="col-12 col-md-4 col-lg-3">
        <a href="<?= $filterWith(['strategy' => 'sell_all']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-rose <?= $params['strategy'] === 'sell_all' ? 'kpi-selected border border-danger' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-danger">Take Profit / Exit</span>
                    <?= \app\helpers\SvgHelper::icon('exclamation-triangle', ['class' => 'text-danger']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-danger"><?= number_format((int) ($stats['sellCount'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-danger">Upper Reversal &amp; Cut Loss</div>
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
                \app\helpers\SvgHelper::icon('check-circle-fill') . ' Semua Posisi Beli',
                $filterWith(['strategy' => 'buy_all']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'buy_all' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('lightning-charge-fill') . ' Shakeout Low (' . ($stats['shakeoutCount'] ?? 0) . ')',
                $filterWith(['strategy' => 'shakeout_low']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'shakeout_low' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('box-arrow-up-right') . ' Squeeze Breakout (' . ($stats['squeezeCount'] ?? 0) . ')',
                $filterWith(['strategy' => 'squeeze_breakout']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'squeeze_breakout' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('arrow-down-up') . ' Lower Band Bounce',
                $filterWith(['strategy' => 'lower_bounce']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'lower_bounce' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('shield-exclamation') . ' Exit / TP (' . ($stats['sellCount'] ?? 0) . ')',
                $filterWith(['strategy' => 'sell_all']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'sell_all' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>
        </div>
    </div>

    <!-- Filter Form Grid -->
    <div class="card-body p-3 filter-card-body">
        <?= Html::beginForm(['/bollinger-band/index'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <?= Html::hiddenInput('timeframe', $params['timeframe']) ?>
            <?= Html::hiddenInput('sort', $params['sort']) ?>

            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label small fw-bold text-dark mb-1">Strategi Bollinger Bands</label>
                <?= Html::dropDownList('strategy', $params['strategy'], $presets, ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Min. Risk:Reward</label>
                <?= Html::dropDownList('minRr', $params['minRr'], [
                    '0' => 'Semua R:R',
                    '1.5' => 'Min. 1.5 : 1',
                    '2.0' => 'Min. 2.0 : 1',
                    '3.0' => 'Min. 3.0 : 1 (Asimetris)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Cari Simbol</label>
                <?= Html::textInput('symbol', $params['symbol'], ['class' => 'form-control form-control-sm text-uppercase', 'placeholder' => 'Contoh: BBCA, ASII']) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Sektor</label>
                <?= Html::textInput('sector', $params['sector'], ['class' => 'form-control form-control-sm', 'placeholder' => 'Semua Sektor']) ?>
            </div>

            <div class="col-lg-2 col-md-6 col-6 d-flex gap-2">
                <?= Html::submitButton(\app\helpers\SvgHelper::icon('search') . ' Pindai', ['class' => 'btn btn-primary btn-sm flex-grow-1 shadow-sm']) ?>
                <?= Html::a(\app\helpers\SvgHelper::icon('arrow-counterclockwise'), ['/bollinger-band/index'], ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Reset Filter']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- 4. Tabel Hasil Scanner Unified -->
<div class="table-card-unified">
    <div class="table-card-header">
        <div>
            <h5 class="mb-0">
                <?= \app\helpers\SvgHelper::icon('table', ['class' => 'text-primary me-1']) ?> Daftar Sinyal Bollinger Bands
            </h5>
            <small class="text-muted">Menampilkan <?= count($results) ?> saham teranalisis</small>
        </div>
        <div class="small text-muted">
            Parameter: <strong>Period 20, Multiplier 2.0</strong>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-unified table-hover align-middle mb-0">
            <thead class="table-light text-muted small text-uppercase">
                <tr>
                    <th class="ps-3">Saham</th>
                    <th>Harga Saat Ini</th>
                    <th>Bollinger Levels</th>
                    <th>%B &amp; BandWidth</th>
                    <th>Setup &amp; Sinyal</th>
                    <th>Area Beli (Entry)</th>
                    <th>Stop Loss</th>
                    <th>TP1 (Gain)</th>
                    <th>TP2 (Gain)</th>
                    <th>R:R</th>
                    <th>RVOL &amp; RSI</th>
                    <th class="pe-3 text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="12" class="text-center py-5 text-muted">
                            <?= \app\helpers\SvgHelper::icon('inbox', ['class' => 'fs-1 d-block mb-2 text-secondary opacity-50']) ?>
                            Tidak ada saham yang memenuhi kriteria filter saat ini. Cobalah ganti preset atau reset filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $r): ?>
                        <?php
                        $act = $r['action'];
                        $actBadge = $badgeAction[$act] ?? 'secondary';
                        $isShakeout = !empty($r['is_shakeout_low']);
                        ?>
                        <tr class="<?= $isShakeout ? 'table-warning bg-opacity-25' : '' ?>">
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-1">
                                    <strong><?= Html::encode($r['symbol']) ?></strong>
                                    <?php if (!empty($r['exchange'])): ?>
                                        <span class="badge bg-light text-secondary border" style="font-size: 0.65rem;"><?= Html::encode($r['exchange']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small text-truncate" style="max-width: 130px;" title="<?= Html::encode($r['name']) ?>">
                                    <?= Html::encode($r['name']) ?>
                                </div>
                            </td>

                            <td>
                                <div class="fw-bold text-dark">
                                    <?= Yii::$app->formatter->asCurrency($r['price']) ?>
                                </div>
                                <small class="<?= ($r['price_change_pct'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= ($r['price_change_pct'] ?? 0) >= 0 ? '+' : '' ?><?= $r['price_change_pct'] ?>%
                                </small>
                            </td>

                            <td>
                                <div class="small">
                                    <span class="text-danger" title="Upper Band">U: <?= Yii::$app->formatter->asCurrency($r['upper_band']) ?></span><br>
                                    <span class="text-muted" title="Middle Band (SMA 20)">M: <?= Yii::$app->formatter->asCurrency($r['middle_band']) ?></span><br>
                                    <span class="text-success" title="Lower Band">L: <?= Yii::$app->formatter->asCurrency($r['lower_band']) ?></span>
                                </div>
                            </td>

                            <td>
                                <div>
                                    <span class="badge bg-light text-dark border" style="font-size: 0.7rem;" title="%B: Posisi harga relatif terhadap Upper & Lower Band">
                                        %B: <?= $r['percent_b'] ?>
                                    </span>
                                </div>
                                <div class="mt-1">
                                    <?php if (!empty($r['is_squeeze'])): ?>
                                        <span class="badge bg-primary text-white" style="font-size: 0.65rem;" title="BandWidth Sempit (Fase Squeeze)">
                                            Squeeze <?= $r['bandwidth'] ?>%
                                        </span>
                                    <?php else: ?>
                                        <small class="text-muted">BW: <?= $r['bandwidth'] ?>%</small>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    <span class="badge bg-<?= $actBadge ?> fs-7">
                                        <?= Html::encode($r['action']) ?>
                                    </span>
                                    <?php if ($isShakeout): ?>
                                        <span class="badge bg-warning text-dark border border-warning" style="font-size: 0.65rem;" title="Liquidity Sweep undercuts Lower Band by <?= $r['shakeout_undercut_pct'] ?? 0 ?>%">
                                            <?= \app\helpers\SvgHelper::icon('lightning-charge-fill') ?> Sweep <?= $r['shakeout_undercut_pct'] ?? 0 ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted mt-1" style="max-width: 170px;">
                                    <?= Html::encode($r['strategy_name']) ?>
                                </div>
                            </td>

                            <td>
                                <span class="fw-semibold text-primary">
                                    <?= Yii::$app->formatter->asCurrency($r['entry_price']) ?>
                                </span>
                            </td>

                            <td>
                                <div class="text-danger fw-semibold">
                                    <?= Yii::$app->formatter->asCurrency($r['stop_loss']) ?>
                                </div>
                                <small class="text-danger">-<?= $r['stop_loss_pct'] ?>%</small>
                            </td>

                            <td>
                                <div class="text-success fw-semibold">
                                    <?= Yii::$app->formatter->asCurrency($r['target_1']) ?>
                                </div>
                                <small class="text-success">+<?= $r['target_1_pct'] ?>%</small>
                            </td>

                            <td>
                                <div class="text-success fw-semibold">
                                    <?= Yii::$app->formatter->asCurrency($r['target_2']) ?>
                                </div>
                                <small class="text-success">+<?= $r['target_2_pct'] ?>%</small>
                            </td>

                            <td>
                                <span class="badge bg-<?= ($r['rr_ratio'] >= 2.0) ? 'success' : (($r['rr_ratio'] >= 1.5) ? 'primary' : 'secondary') ?> fs-7">
                                    <?= $r['rr_ratio'] ?>x
                                </span>
                            </td>

                            <td>
                                <div class="small">
                                    <span class="<?= ($r['rvol'] >= 1.5) ? 'text-success fw-bold' : 'text-muted' ?>" title="RVOL terhadap SMA 20">
                                        <?= $r['rvol'] ?>x Vol
                                    </span>
                                </div>
                                <div class="small">
                                    <span class="badge bg-purple text-white" style="font-size: 0.65rem; background-color: #6f42c1;" title="RSI 14">
                                        RSI <?= $r['rsi'] ?>
                                    </span>
                                </div>
                            </td>

                            <td class="pe-3 text-end">
                                <?= Html::a(
                                    \app\helpers\SvgHelper::icon('box-arrow-up-right') . ' Plan &amp; Chart',
                                    ['/bollinger-band/detail', 'symbol' => $r['symbol'], 'timeframe' => $params['timeframe']],
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
                    <label class="form-label small fw-bold text-dark">Format Simbol dengan Bursa (IDX:BBCA, ...)</label>
                    <textarea id="tvSymbolsArea" class="form-control form-control-sm" rows="4" readonly><?= Html::encode($tvSymbolsStr) ?></textarea>
                </div>
                <div>
                    <label class="form-label small fw-bold text-dark">Format Simbol Standar (BBCA, ASII, ...)</label>
                    <textarea id="plainSymbolsArea" class="form-control form-control-sm" rows="3" readonly><?= Html::encode($symbolsStr) ?></textarea>
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
