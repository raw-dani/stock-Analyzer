<?php

/** @var yii\web\View $this */
/** @var array $results */
/** @var array $params */
/** @var array $stats */
/** @var array $symbols */
/** @var array $tvSymbols */
/** @var array $maPairs */
/** @var array $timeframes */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Moving Average Strategy & Position Scanner';
$this->params['breadcrumbs'][] = 'Moving Average Signals';

$symbolsStr = implode(', ', $symbols);
$tvSymbolsStr = implode(', ', $tvSymbols);

$currentGet = Yii::$app->request->get();
$filterWith = fn(array $overrides) => Url::to(array_merge(['/moving-average/index'], $currentGet, $overrides));

$badgeAction = [
    'BUY' => 'success',
    'HOLD' => 'secondary',
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
                    <?= \app\helpers\SvgHelper::icon('graph-up') ?>
                </div>
                <div>
                    <h1 class="app-hero-title h4 fw-bold mb-1 text-dark">
                        <?= Html::encode($this->title) ?>
                    </h1>
                    <p class="app-hero-desc small text-muted mb-0">
                        Penentuan posisi beli (*entry*), batas risiko (*stop loss*), dan target keuntungan (*take profit*) presisi menggunakan Moving Average &amp; konfirmasi volume.
                    </p>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- MA Type Toggle: SMA vs EMA -->
                <div class="btn-group btn-group-sm p-1 bg-light rounded-pill border" role="group">
                    <?= Html::a(
                        'SMA',
                        $filterWith(['maType' => 'sma']),
                        ['class' => 'btn btn-sm rounded-pill px-3 ' . (($params['maType'] ?? 'sma') === 'sma' ? 'btn-primary text-white shadow-sm' : 'btn-light text-secondary border-0')]
                    ) ?>
                    <?= Html::a(
                        'EMA',
                        $filterWith(['maType' => 'ema']),
                        ['class' => 'btn btn-sm rounded-pill px-3 ' . (($params['maType'] ?? 'sma') === 'ema' ? 'btn-primary text-white shadow-sm' : 'btn-light text-secondary border-0')]
                    ) ?>
                </div>
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
                <span class="badge bg-dark-subtle text-dark border px-3 py-2 fs-7 rounded-pill">
                    <?= strtoupper($params['maType'] ?? 'SMA') ?>: <?= Html::encode($maPairs[$params['maPair']] ?? 'MA20 / MA50') ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?= $this->render('//layouts/_sync_status_badge', [
    'fromCache' => $fromCache ?? false,
    'timeframe' => (int) ($params['timeframe'] ?? 24),
]) ?>

<!-- Interactive KPI Metric Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= Url::to(['/moving-average/index', 'timeframe' => $params['timeframe'], 'maPair' => $params['maPair']]) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-indigo <?= (empty($params['strategy']) || $params['strategy'] === 'all') ? 'kpi-selected border border-primary' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-muted">Total Saham Terfilter</span>
                    <?= \app\helpers\SvgHelper::icon('grid', ['class' => 'text-muted']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-dark"><?= number_format((int) ($stats['total'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-muted">
                    <?= \app\helpers\SvgHelper::icon('arrow-counterclockwise', ['class' => 'me-1']) ?> Reset ke semua
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'buy_all']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-emerald <?= ($params['strategy'] === 'buy_all') ? 'kpi-selected border border-success' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-success">Posisi Beli (Buy Setups)</span>
                    <?= \app\helpers\SvgHelper::icon('check-circle', ['class' => 'text-success']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-success"><?= number_format((int) ($stats['buyCount'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-success">
                    <?= \app\helpers\SvgHelper::icon('check2-circle', ['class' => 'me-1']) ?> Golden Cross &amp; Rebound
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'candle_bounce']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-cyan <?= ($params['strategy'] === 'candle_bounce') ? 'kpi-selected border border-info' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-cyan">Konfirmasi Multi-Strategi</span>
                    <?= \app\helpers\SvgHelper::icon('reception-4', ['class' => 'text-info']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-info"><?= number_format((int) (($stats['candleBounceCount'] ?? 0) + ($stats['volumeSurgeCount'] ?? 0))) ?></div>
                <div class="kpi-subtext small text-muted">
                    Rebound (<?= $stats['candleBounceCount'] ?? 0 ?>) • Vol (<?= $stats['volumeSurgeCount'] ?? 0 ?>)
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'sell_all']) ?>" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-rose <?= ($params['strategy'] === 'sell_all') ? 'kpi-selected border border-danger' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="kpi-header d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-label small fw-bold text-uppercase text-danger">Alert Jual / Take Profit</span>
                    <?= \app\helpers\SvgHelper::icon('exclamation-triangle', ['class' => 'text-danger']) ?>
                </div>
                <div class="kpi-value h3 fw-bold mb-1 text-danger"><?= number_format((int) ($stats['sellCount'] ?? 0)) ?></div>
                <div class="kpi-subtext small text-danger">
                    Exit &amp; Realisasi Profit
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Strategy Quick Presets & Filter Form -->
<div class="card border-0 shadow-sm mb-4 filter-card-unified">
    <!-- Strategy Quick Presets Pills -->
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
                \app\helpers\SvgHelper::icon('arrow-repeat') . ' Golden Cross',
                $filterWith(['strategy' => 'golden_cross']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'golden_cross' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('reception-4') . ' MA + Rebound',
                $filterWith(['strategy' => 'candle_bounce']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'candle_bounce' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('bullseye') . ' Pullback Buy',
                $filterWith(['strategy' => 'pullback']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'pullback' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-warning']) . ' Shakeout Low (' . ($stats['shakeoutCount'] ?? 0) . ')',
                $filterWith(['strategy' => 'shakeout_low']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'shakeout_low' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('graph-up-arrow') . ' MA Breakout + Vol',
                $filterWith(['strategy' => 'breakout']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'breakout' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('currency-dollar') . ' Take Profit Climax',
                $filterWith(['strategy' => 'take_profit']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'take_profit' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('x-octagon-fill') . ' Death Cross (Sell)',
                $filterWith(['strategy' => 'death_cross']),
                ['class' => 'preset-pill btn btn-sm rounded-pill px-3 py-1 ' . ($params['strategy'] === 'death_cross' ? 'active btn-primary text-white border-primary shadow-sm' : 'btn-light text-secondary border')]
            ) ?>
        </div>
    </div>

    <div class="card-body p-3 filter-card-body">
        <?= Html::beginForm(['/moving-average/index'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Tipe MA</label>
                <?= Html::dropDownList('maType', $params['maType'] ?? 'sma', [
                    'sma' => 'SMA (Simple)',
                    'ema' => 'EMA (Exponential)',
                ], ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Kombinasi MA (Periode)</label>
                <?= Html::dropDownList('maPair', $params['maPair'], $maPairs, ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Timeframe</label>
                <?= Html::dropDownList('timeframe', $params['timeframe'], $timeframes, ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Strategi Spesifik</label>
                <?= Html::dropDownList('strategy', $params['strategy'], [
                    'all' => 'Semua Setup Strategi',
                    'buy_all' => '🟢 Semua Setup BELI (BUY)',
                    'golden_cross' => '⭐ Golden Cross (Persilangan MA)',
                    'shakeout_low' => '⚡ Shakeout Low (MA Bear Trap)',
                    'candle_bounce' => '🕯️ MA Bounce + Candlestick Reversal',
                    'pullback' => '🎯 Pullback Buy on Dip (MA Support)',
                    'breakout' => '🚀 Breakout di Atas MA',
                    'sell_all' => '🔴 Semua Sinyal JUAL (SELL)',
                    'death_cross' => '⚠️ Death Cross (Bearish Exit)',
                    'take_profit' => '💰 Overextended Climax (Take Profit)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Min. R:R</label>
                <?= Html::dropDownList('minRr', $params['minRr'], [
                    '0' => 'Semua R:R',
                    '1.5' => '≥ 1.5 : 1 (Standar)',
                    '2.0' => '≥ 2.0 : 1 (Ideal)',
                    '3.0' => '≥ 3.0 : 1 (Super Ratio)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <label class="form-label small fw-bold text-dark mb-1">Urutan Hasil (Sorting)</label>
                <?= Html::dropDownList('sort', $params['sort'], [
                    'rr' => 'Risk/Reward Tertinggi',
                    'gain' => 'Potensi Gain TP1 Tertinggi (%)',
                    'rvol' => 'Lonjakan Volume (RVOL)',
                    'symbol' => 'Simbol Saham (A-Z)',
                    'price' => 'Harga Tertinggi',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Simbol</label>
                <?= Html::textInput('symbol', $params['symbol'], ['class' => 'form-control form-control-sm text-uppercase', 'placeholder' => 'Cth: NVDA']) ?>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-bold text-dark mb-1">Bursa</label>
                <?= Html::dropDownList('exchange', $params['exchange'], [
                    '' => 'Semua Bursa',
                    'NASDAQ' => 'NASDAQ',
                    'NYSE' => 'NYSE',
                    'AMEX' => 'AMEX',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="small text-muted py-1" style="font-size: 0.78rem;">
                    <?= \app\helpers\SvgHelper::icon('shield-check', ['class' => 'text-success me-1']) ?>
                    <strong>Stop Loss ATR</strong> &amp; <strong>Slope MA</strong> aktif untuk presisi sinyal maksimal.
                </div>
            </div>

            <div class="col-lg-2 col-md-6 col-12 d-flex gap-2">
                <?= Html::a(\app\helpers\SvgHelper::icon('arrow-counterclockwise'), ['/moving-average/index'], ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Reset Filter']) ?>
                <?= Html::submitButton(\app\helpers\SvgHelper::icon('search') . ' Pindai', ['class' => 'btn btn-primary btn-sm flex-grow-1 fw-bold shadow-sm']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- Table Results with Actions -->
<div class="table-card-unified">
    <div class="table-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0 fw-bold text-dark">
                <?= \app\helpers\SvgHelper::icon('table', ['class' => 'text-primary me-1']) ?> Sinyal &amp; Rencana Posisi Trading
                <span class="badge bg-secondary ms-1"><?= count($results) ?> Saham Lolos</span>
                <span class="badge bg-primary ms-1"><?= \app\helpers\SvgHelper::icon('stopwatch') ?> <?= Html::encode($timeframes[$params['timeframe']] ?? 'Daily') ?></span>
            </h5>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Client-side quick filter input -->
            <div class="input-group input-group-sm" style="max-width: 220px;">
                <span class="input-group-text bg-light border-end-0"><?= \app\helpers\SvgHelper::icon('search', ['class' => 'text-muted']) ?></span>
                <input type="text" id="ma-table-search" class="form-control form-control-sm border-start-0" placeholder="Filter di tabel...">
            </div>

            <!-- Copy Symbols Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" <?= empty($symbols) ? 'disabled' : '' ?>>
                    <?= \app\helpers\SvgHelper::icon('clipboard') ?> Salin Simbol (<?= count($symbols) ?>)
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                    <li><h6 class="dropdown-header">Pilih Format Simbol</h6></li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0)" onclick="copySymbolsText('<?= Html::encode($symbolsStr) ?>', 'Simbol standar')">
                            <?= \app\helpers\SvgHelper::icon('file-text', ['class' => 'me-1']) ?> Standar (Cth: NVDA, AAPL)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0)" onclick="copySymbolsText('<?= Html::encode($tvSymbolsStr) ?>', 'Format TradingView')">
                            <?= \app\helpers\SvgHelper::icon('graph-up', ['class' => 'me-1']) ?> TradingView (Cth: NASDAQ:NVDA)
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Export CSV -->
            <a href="<?= Url::to(array_merge(['/moving-average/export'], $currentGet)) ?>" class="btn btn-outline-success btn-sm shadow-sm" title="Download data hasil filter dalam format CSV">
                <?= \app\helpers\SvgHelper::icon('download') ?> Export CSV
            </a>
        </div>
    </div>

    <!-- Toast Notification for Copy -->
    <div id="copy-toast" class="alert alert-dark position-fixed bottom-0 end-0 m-3 shadow py-2 px-3 fade" style="display:none; z-index:9999;" role="alert">
        <?= \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success me-1']) ?> <span id="copy-toast-text">Simbol disalin ke clipboard!</span>
    </div>

    <div class="table-responsive">
        <table class="table-unified" id="ma-grid-table">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th style="width: 35px;">#</th>
                    <th style="width: 105px;">Simbol</th>
                    <th>Harga Terkini</th>
                    <th class="text-center" style="width: 110px;">Rekomendasi</th>
                    <th>Setup Strategi</th>
                    <th class="text-center">Konfirmasi Multi-Strategi</th>
                    <th class="text-end text-primary" style="width: 110px;">Posisi Beli</th>
                    <th class="text-end text-danger" style="width: 115px;">Stop Loss (SL)</th>
                    <th class="text-end text-success" style="min-width: 135px;">Target TP1 / TP2 / TP3</th>
                    <th class="text-center" style="width: 85px;">Risk/Reward</th>
                    <th class="text-center" style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr class="empty">
                        <td colspan="11" class="text-center py-5">
                            <div class="display-6 text-muted mb-2"><?= \app\helpers\SvgHelper::icon('funnel') ?></div>
                            <h5 class="fw-bold text-secondary">Tidak ada saham yang cocok dengan kriteria filter</h5>
                            <p class="text-muted small mb-3">Coba longgarkan filter strategi atau pilih timeframe yang berbeda.</p>
                            <a href="<?= Url::to(['/moving-average/index']) ?>" class="btn btn-primary btn-sm px-3 shadow-sm">
                                <?= \app\helpers\SvgHelper::icon('arrow-counterclockwise') ?> Reset Semua Filter
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $idx => $r): ?>
                        <?php
                        $act = $r['action'];
                        $isBuy = ($act === 'BUY');
                        $isSell = ($act === 'SELL');
                        $isTp = ($act === 'TAKE_PROFIT');
                        $rsiVal = (float)($r['rsi'] ?? 50.0);
                        $rsiColor = $rsiVal <= 40 ? 'text-success fw-bold' : ($rsiVal >= 70 ? 'text-danger fw-bold' : 'text-primary');
                        ?>
                        <tr>
                            <td class="text-muted small"><?= $idx + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <?php if ($isBuy): ?>
                                        <span class="p-1 bg-success rounded-circle d-inline-block" title="Potensi Beli (MA Golden Cross / Bullish)" style="width: 8px; height: 8px;"></span>
                                    <?php elseif ($isSell): ?>
                                        <span class="p-1 bg-danger rounded-circle d-inline-block" title="Exit / Sell (MA Death Cross / Bearish)" style="width: 8px; height: 8px;"></span>
                                    <?php elseif ($isTp): ?>
                                        <span class="p-1 bg-warning rounded-circle d-inline-block" title="Take Profit" style="width: 8px; height: 8px;"></span>
                                    <?php else: ?>
                                        <span class="p-1 bg-secondary bg-opacity-25 rounded-circle d-inline-block" title="Hold / Wait" style="width: 8px; height: 8px;"></span>
                                    <?php endif; ?>
                                    <?= Html::a(
                                        Html::encode($r['symbol']),
                                        ['/moving-average/detail', 'symbol' => $r['symbol'], 'timeframe' => $params['timeframe'], 'maPair' => $params['maPair'], 'maType' => $params['maType'] ?? 'sma'],
                                        ['class' => 'fw-bold text-primary text-decoration-none fs-6']
                                    ) ?>
                                    <?php if (!empty($r['exchange'])): ?>
                                        <span class="badge bg-light text-secondary border py-0 px-1" style="font-size:0.65rem;"><?= Html::encode($r['exchange']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small text-truncate" style="max-width: 140px; font-size: 0.72rem;">
                                    <?= Html::encode($r['name'] ?? $r['sector'] ?? '') ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= Yii::$app->formatter->asCurrency($r['current_price']) ?></div>
                                <div class="small text-muted" style="font-size: 0.72rem;">
                                    Fast MA: <?= Yii::$app->formatter->asCurrency($r['fast_ma']) ?>
                                </div>
                                <div class="small text-muted" style="font-size: 0.70rem;">
                                    <?= \app\helpers\SvgHelper::icon('clock', ['class' => 'me-1']) ?><?= Html::encode($r['date']) ?>
                                    <?php if (!empty($r['is_fallback'])): ?>
                                        <span class="badge bg-warning text-dark py-0 px-1" title="Data intraday belum cukup, memakai data harian">Harian</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $badgeAction[$act] ?? 'secondary' ?> px-2 py-1 shadow-sm">
                                    <?= Html::encode($act) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark small">
                                    <?= Html::encode($r['strategy_name']) ?>
                                    <?php if (!empty($r['is_shakeout_low'])): ?>
                                        <span class="badge bg-warning text-dark border border-warning ms-1" style="font-size:0.65rem;" title="Liquidity sweep undercuts MA by <?= $r['shakeout_undercut_pct'] ?? 0 ?>%">
                                            <?= \app\helpers\SvgHelper::icon('lightning-charge-fill') ?> Sweep <?= $r['shakeout_undercut_pct'] ?? 0 ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small" style="font-size: 0.73rem;">
                                    Jarak MA: <span class="<?= $r['dist_fast_pct'] >= 0 ? 'text-success' : 'text-danger' ?> fw-bold"><?= ($r['dist_fast_pct'] > 0 ? '+' : '') . $r['dist_fast_pct'] ?>%</span>
                                    &bull; Tren: <strong><?= Html::encode($r['trend_status']) ?></strong>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex flex-column gap-1 align-items-center">
                                    <?php if (!empty($r['is_shakeout_low'])): ?>
                                        <span class="badge bg-warning text-dark border border-warning" style="font-size:0.68rem;" title="Bear Trap: Undercut lalu rebound di atas MA">
                                            <?= \app\helpers\SvgHelper::icon('shield-check') ?> Shakeout Low
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge bg-light text-dark border" style="font-size:0.7rem;" title="RSI Momentum (14)">
                                        RSI(14): <strong class="<?= $rsiColor ?>"><?= number_format($rsiVal, 1) ?></strong>
                                    </span>
                                    <?php if (!empty($r['candle_pattern'])): ?>
                                        <span class="badge <?= ($r['candle_signal'] ?? '') === 'BULLISH' ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?>" style="font-size:0.68rem;">
                                            <?= \app\helpers\SvgHelper::icon('reception-4') ?> <?= Html::encode($r['candle_pattern']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (($r['rvol'] ?? 0) >= 1.5): ?>
                                        <span class="badge bg-info text-dark" style="font-size:0.68rem;" title="Lonjakan Volume RVOL &ge; 1.5x">
                                            <?= \app\helpers\SvgHelper::icon('lightning-fill') ?> RVOL <?= $r['rvol'] ?>x
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <?php if ($act === 'BUY' || $act === 'HOLD'): ?>
                                    <div class="fw-bold text-primary"><?= Yii::$app->formatter->asCurrency($r['entry_price']) ?></div>
                                    <div class="text-muted small" style="font-size: 0.72rem;"><?= Html::encode($r['entry_range']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($act === 'BUY' || $act === 'HOLD'): ?>
                                    <div class="fw-bold text-danger"><?= Yii::$app->formatter->asCurrency($r['stop_loss']) ?></div>
                                    <div class="text-danger small" style="font-size: 0.72rem;">-<?= $r['stop_loss_pct'] ?>% Risk</div>
                                <?php else: ?>
                                    <span class="badge bg-danger text-white">Exit / Sell</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($act === 'BUY' || $act === 'HOLD'): ?>
                                    <div class="text-success fw-bold" style="font-size: 0.85rem;" title="TP1: Target Swing / Minimum 1.8R">
                                        <span class="badge bg-success text-white py-0 me-1" style="font-size: 0.62rem;">TP1</span>
                                        <?= Yii::$app->formatter->asCurrency($r['target_1']) ?>
                                        <small class="fw-normal">(+<?= $r['target_1_pct'] ?>%)</small>
                                    </div>
                                    <div class="text-primary small" style="font-size: 0.75rem;" title="TP2: Target Runner 3.0R">
                                        <span class="badge bg-primary-subtle text-primary border py-0 me-1" style="font-size: 0.62rem;">TP2</span>
                                        <?= Yii::$app->formatter->asCurrency($r['target_2']) ?>
                                        <small>(+<?= $r['target_2_pct'] ?>%)</small>
                                    </div>
                                    <?php if (!empty($r['target_3'])): ?>
                                        <div class="text-muted small" style="font-size: 0.72rem;" title="TP3: Target Moonbag 4.2R">
                                            <span class="badge bg-warning-subtle text-dark border py-0 me-1" style="font-size: 0.62rem;">TP3</span>
                                            <?= Yii::$app->formatter->asCurrency($r['target_3']) ?>
                                            <small>(+<?= $r['target_3_pct'] ?>%)</small>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (($r['rr_ratio'] ?? 0) > 0): ?>
                                    <span class="badge bg-<?= $r['rr_ratio'] >= 2.0 ? 'success' : 'primary' ?> px-2 py-1">
                                        <?= $r['rr_ratio'] ?>x
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($r['rvol'] >= 1.5): ?>
                                    <span class="badge bg-warning text-dark px-2 py-1"><?= \app\helpers\SvgHelper::icon('fire') ?> <?= $r['rvol'] ?>x</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border"><?= $r['rvol'] ?>x</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <?= Html::a(
                                        \app\helpers\SvgHelper::icon('eye') . ' Plan',
                                        ['/moving-average/detail', 'symbol' => $r['symbol'], 'timeframe' => $params['timeframe'], 'maPair' => $params['maPair']],
                                        ['class' => 'btn btn-primary py-0 px-2 fw-semibold', 'title' => 'Lihat Rencana Trading Presisi & Chart MA']
                                    ) ?>
                                    <?= Html::a(
                                        \app\helpers\SvgHelper::icon('graph-up'),
                                        ['/stock/view', 'symbol' => $r['symbol']],
                                        ['class' => 'btn btn-outline-secondary py-0 px-2', 'title' => 'Detail Saham Fundamental']
                                    ) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.hover-card {
    transition: transform 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.hover-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.1) !important;
}
.ring-active {
    box-shadow: 0 0 0 2px #0d6efd !important;
}
</style>

<script>
// Live client-side instant filtering on the table
document.getElementById('ma-table-search')?.addEventListener('input', function () {
    const filter = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#ma-grid-table tbody tr');
    rows.forEach(row => {
        if (row.classList.contains('empty')) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

// Copy symbols to clipboard with feedback
function copySymbolsText(text, label) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(function() {
        const toast = document.getElementById('copy-toast');
        const toastText = document.getElementById('copy-toast-text');
        if (toast && toastText) {
            toastText.textContent = label + ' berhasil disalin ke clipboard!';
            toast.style.display = 'block';
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => { toast.style.display = 'none'; }, 200);
            }, 2500);
        }
    }).catch(function(err) {
        alert('Gagal menyalin: ' + err);
    });
}
</script>
