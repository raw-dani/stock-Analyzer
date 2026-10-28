<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var array $results */
/** @var array $allResults */
/** @var int $timeframe */
/** @var int $lookback */
/** @var float $tolerance */
/** @var int $rsiPeriod */
/** @var float $maxRsi */
/** @var int|null $minSeparation */
/** @var int|null $maxSeparation */
/** @var float|null $necklineMin */
/** @var string $statusFilter */
/** @var int $minConf */
/** @var string $sortBy */
/** @var array $timeframes */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'RSI Strategy & Signal Scanner';
$this->params['breadcrumbs'][] = $this->title;

$allPatterns = $allResults ?? $results;
$totalCount = count($allPatterns);
$buyCount = count(array_filter($allPatterns, fn($r) => in_array($r['action'] ?? '', ['BUY_NOW', 'BUY_PULLBACK', 'MOMENTUM_BUY'], true)));
$sellCount = count(array_filter($allPatterns, fn($r) => in_array($r['action'] ?? '', ['SELL_NOW', 'TAKE_PROFIT'], true)));
$bullDivCount = count(array_filter($allPatterns, fn($r) => !empty($r['divergence'])));
$bearDivCount = count(array_filter($allPatterns, fn($r) => !empty($r['bearish_divergence'])));
$volumeSurgeCount = count(array_filter($allPatterns, fn($r) => !empty($r['volume_spike'])));
$candleReversalCount = count(array_filter($allPatterns, fn($r) => !empty($r['candle_pattern'])));
$fibPocketCount = count(array_filter($allPatterns, fn($r) => !empty($r['is_golden_pocket'])));
$shakeoutCount = count(array_filter($allPatterns, fn($r) => !empty($r['is_shakeout_low']) || ($r['signal_type'] ?? '') === 'BUY_SHAKEOUT_LOW'));
$oversoldWatchCount = count(array_filter($allPatterns, fn($r) => ($r['current_rsi'] ?? 50) <= 35));
$overboughtWatchCount = count(array_filter($allPatterns, fn($r) => ($r['current_rsi'] ?? 50) >= 65));

$dailyMax = \app\models\DailyPrice::find()->max('date');
$staleDays = $dailyMax ? (int) floor((time() - strtotime($dailyMax . ' 23:59:00')) / 86400) : null;
?>

<div class="card border-0 shadow-sm mb-4 app-hero-header">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="app-hero-icon text-primary bg-primary bg-opacity-10">
                    <?= \app\helpers\SvgHelper::icon('activity') ?>
                </div>
                <div>
                    <h1 class="app-hero-title mb-1">
                        <?= Html::encode($this->title) ?>
                    </h1>
                    <p class="app-hero-desc mb-0">
                        Sistem analisis RSI Multi-Strategi terintegrasi <strong>Konfirmasi Volume (RVOL)</strong>, <strong>Candlestick Reversal</strong>, dan <strong>Fibonacci Retracement</strong> untuk menentukan <strong>Waktu Beli (Entry)</strong> dan target keuntungan berjenjang <strong>TP1, TP2, TP3</strong> serta Stop Loss terukur.
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group-tf btn-group btn-group-sm p-1 bg-light rounded-pill border shadow-sm" role="group" aria-label="Timeframe Selector">
                    <?php foreach ([24 => '1D (Daily)', 4 => '4H', 2 => '2H', 1 => '1H'] as $tfVal => $tfLabel): ?>
                        <?= Html::a(
                            Html::encode($tfLabel),
                            [
                                '/rsi-double-bottom/index',
                                'timeframe' => $tfVal,
                                'statusFilter' => $statusFilter,
                                'sortBy' => $sortBy,
                                'rsiPeriod' => $rsiPeriod,
                                'lookback' => $lookback,
                                'maxRsi' => $maxRsi,
                            ],
                            [
                                'class' => 'btn-tf btn btn-sm rounded-pill px-3 ' . ($timeframe === $tfVal ? 'active' : ''),
                            ]
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

<?php if ($staleDays !== null && $staleDays >= 3): ?>
    <div class="alert alert-warning py-2 mb-3 d-flex align-items-center">
        <?= \app\helpers\SvgHelper::icon('exclamation-triangle-fill', ['class' => 'fs-5 me-2 text-warning']) ?>
        <div>
            <strong>Peringatan Data Pasar:</strong> Data harian terakhir tanggal <strong><?= Html::encode((string) $dailyMax) ?></strong> (<?= $staleDays ?> hari lalu).
            Jalankan <code>php yii data/refresh-all</code> untuk mendapatkan harga terbaru.
        </div>
    </div>
<?php endif; ?>

<!-- Interactive KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="javascript:void(0)" onclick="document.getElementById('filter-status-input').value = 'buy'; document.getElementById('rsi-filter-form').submit();" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-emerald <?= ($statusFilter === 'buy') ? 'kpi-selected' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="kpi-card-label">Sinyal Waktu Beli</div>
                    <div class="kpi-card-value text-emerald-700"><?= $buyCount ?></div>
                </div>
                <div class="kpi-card-sub text-emerald-600 mt-2">
                    <?= \app\helpers\SvgHelper::icon('cart-check-fill', ['class' => 'me-1']) ?> Reversal Bounce / Bullish Div
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="javascript:void(0)" onclick="document.getElementById('filter-status-input').value = 'sell'; document.getElementById('rsi-filter-form').submit();" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-rose <?= ($statusFilter === 'sell') ? 'kpi-selected' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="kpi-card-label">Sinyal Waktu Jual / TP</div>
                    <div class="kpi-card-value text-rose-700"><?= $sellCount ?></div>
                </div>
                <div class="kpi-card-sub text-rose-600 mt-2">
                    <?= \app\helpers\SvgHelper::icon('box-arrow-right', ['class' => 'me-1']) ?> Overbought Exit / Bearish Div
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="javascript:void(0)" onclick="document.getElementById('filter-status-input').value = 'volume_surge'; document.getElementById('rsi-filter-form').submit();" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-cyan <?= in_array($statusFilter, ['volume_surge', 'candle_reversal', 'fib_pocket']) ? 'kpi-selected' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="kpi-card-label">Konfirmasi Multi-Strategi</div>
                    <div class="kpi-card-value text-cyan-700"><?= $volumeSurgeCount + $candleReversalCount + $fibPocketCount ?></div>
                </div>
                <div class="kpi-card-sub text-cyan-600 mt-2">
                    Vol (<?= $volumeSurgeCount ?>) • Candle (<?= $candleReversalCount ?>) • Fib (<?= $fibPocketCount ?>)
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="javascript:void(0)" onclick="document.getElementById('filter-status-input').value = 'oversold'; document.getElementById('rsi-filter-form').submit();" class="kpi-card-unified card border-0 shadow-sm text-decoration-none h-100 kpi-accent-amber <?= in_array($statusFilter, ['oversold', 'overbought']) ? 'kpi-selected' : '' ?>">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="kpi-card-label">Area Jenuh (Watchlist)</div>
                    <div class="kpi-card-value text-amber-700"><?= $oversoldWatchCount + $overboughtWatchCount ?></div>
                </div>
                <div class="kpi-card-sub text-amber-600 mt-2">
                    Oversold (<?= $oversoldWatchCount ?>) / Overbought (<?= $overboughtWatchCount ?>)
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Filter Toolbar -->
<div class="card border-0 shadow-sm mb-4 filter-card-unified">
    <div class="card-header bg-white border-bottom py-2 px-3 filter-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="filter-card-title fw-bold text-dark">
            <?= \app\helpers\SvgHelper::icon('funnel', ['class' => 'text-primary me-1']) ?> Parameter Filter &amp; Sinyal RSI
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-xs btn-outline-secondary py-1 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#advancedParams" aria-expanded="false">
                <?= \app\helpers\SvgHelper::icon('sliders') ?> Parameter Indikator
            </button>
        </div>
    </div>

    <!-- Quick Presets Pills Bar -->
    <div class="px-3 py-2 bg-light border-bottom d-flex flex-wrap align-items-center gap-2 preset-pills-bar">
        <span class="preset-pills-title text-muted small fw-bold">
            <?= \app\helpers\SvgHelper::icon('lightning-fill', ['class' => 'text-warning me-1']) ?> Sinyal:
        </span>
        <?php
        $filters = [
            'all' => ['label' => 'Semua (' . $totalCount . ')', 'icon' => 'list'],
            'buy' => ['label' => 'Waktu Beli (' . $buyCount . ')', 'icon' => 'cart-plus-fill'],
            'sell' => ['label' => 'Waktu Jual / TP (' . $sellCount . ')', 'icon' => 'tag-fill'],
            'bull_div' => ['label' => 'Bullish Div (' . $bullDivCount . ')', 'icon' => 'gem'],
            'bear_div' => ['label' => 'Bearish Div (' . $bearDivCount . ')', 'icon' => 'lightning-fill'],
            'volume_surge' => ['label' => 'Vol Surge (' . $volumeSurgeCount . ')', 'icon' => 'bar-chart-fill'],
            'candle_reversal' => ['label' => 'Candle Pattern (' . $candleReversalCount . ')', 'icon' => 'reception-4'],
            'fib_pocket' => ['label' => 'Fib Golden Zone (' . $fibPocketCount . ')', 'icon' => 'bullseye'],
            'shakeout' => ['label' => 'Shakeout Low (' . $shakeoutCount . ')', 'icon' => 'lightning-charge'],
            'oversold' => ['label' => 'Oversold ≤ 35 (' . $oversoldWatchCount . ')', 'icon' => 'arrow-down-circle'],
            'overbought' => ['label' => 'Overbought ≥ 65 (' . $overboughtWatchCount . ')', 'icon' => 'arrow-up-circle'],
        ];
        foreach ($filters as $key => $meta):
            $active = ($statusFilter === $key);
        ?>
            <button type="button" 
                    class="preset-pill btn btn-sm rounded-pill px-3 py-1 <?= $active ? 'active' : '' ?>"
                    onclick="document.getElementById('filter-status-input').value = '<?= $key ?>'; document.getElementById('rsi-filter-form').submit();">
                <?= \app\helpers\SvgHelper::icon($meta['icon']) ?> <?= $meta['label'] ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="card-body p-3 filter-card-body">
        <?= Html::beginForm(['/rsi-double-bottom/index'], 'get', ['id' => 'rsi-filter-form']) ?>
            <?= Html::hiddenInput('timeframe', $timeframe, ['id' => 'filter-timeframe-input']) ?>
            <?= Html::hiddenInput('statusFilter', $statusFilter, ['id' => 'filter-status-input']) ?>

            <div class="row g-3 align-items-end">
                <div class="col-md-3 col-6">
                    <label class="form-label small fw-bold text-dark mb-1">Timeframe Analisis</label>
                    <select class="form-select form-select-sm" onchange="document.getElementById('filter-timeframe-input').value = this.value; document.getElementById('rsi-filter-form').submit();">
                        <?php foreach ($timeframes as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= $timeframe === $val ? 'selected' : '' ?>><?= Html::encode($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 col-6">
                    <label class="form-label small fw-bold text-dark mb-1">Urutan Hasil (Sorting)</label>
                    <?= Html::dropDownList('sortBy', $sortBy, [
                        'confidence' => 'Confidence Tertinggi',
                        'rr' => 'Risk/Reward Tertinggi',
                        'upside' => 'Potential Upside Tertinggi',
                        'current_rsi' => 'RSI Terendah (Oversold)',
                        'rsi_high' => 'RSI Tertinggi (Overbought)',
                    ], [
                        'class' => 'form-select form-select-sm',
                        'onchange' => 'this.form.submit()',
                    ]) ?>
                </div>

                <div class="col-md-6 col-12 text-md-end">
                    <?= Html::a(\app\helpers\SvgHelper::icon('arrow-counterclockwise') . ' Reset Filter', ['/rsi-double-bottom/index'], ['class' => 'btn btn-sm btn-outline-secondary px-3']) ?>
                </div>
            </div>

            <!-- Collapsible Advanced Parameters -->
            <div class="collapse mt-3 pt-3 border-top" id="advancedParams">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-dark mb-1">RSI Period</label>
                        <?= Html::input('number', 'rsiPeriod', $rsiPeriod, ['class' => 'form-control form-control-sm', 'min' => 2, 'max' => 50]) ?>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-dark mb-1">Lookback Bars</label>
                        <?= Html::input('number', 'lookback', $lookback, ['class' => 'form-control form-control-sm', 'min' => 20, 'max' => 400]) ?>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-dark mb-1">Ambang Max RSI</label>
                        <?= Html::input('number', 'maxRsi', $maxRsi, ['class' => 'form-control form-control-sm', 'min' => 20, 'max' => 70, 'step' => 5]) ?>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-dark mb-1">Tolerance (pts)</label>
                        <?= Html::input('number', 'tolerance', $tolerance, ['class' => 'form-control form-control-sm', 'min' => 0.5, 'max' => 10, 'step' => 0.5]) ?>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-dark mb-1">Jarak Lembah (Min/Max)</label>
                        <div class="input-group input-group-sm">
                            <?= Html::input('number', 'minSeparation', $minSeparation, ['class' => 'form-control', 'placeholder' => 'Min', 'min' => 2, 'max' => 30]) ?>
                            <span class="input-group-text">-</span>
                            <?= Html::input('number', 'maxSeparation', $maxSeparation, ['class' => 'form-control', 'placeholder' => 'Max', 'min' => 5, 'max' => 100]) ?>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <?= Html::submitButton(\app\helpers\SvgHelper::icon('arrow-repeat') . ' Terapkan', ['class' => 'btn btn-primary btn-sm w-100 shadow-sm']) ?>
                    </div>
                </div>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- Scanner Table -->
<div class="table-card-unified">
    <div class="table-card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark">
            <?= \app\helpers\SvgHelper::icon('table', ['class' => 'text-primary me-1']) ?> Rekomendasi Sinyal RSI &amp; Level Eksekusi
            <span class="badge bg-secondary ms-1"><?= count($results) ?> Saham</span>
        </h5>
        <div class="small text-muted">
            TF: <strong><?= Html::encode($timeframes[$timeframe] ?? '') ?></strong> • Period RSI: <strong><?= $rsiPeriod ?></strong>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table-unified">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th style="width: 90px;">Simbol</th>
                    <th>Nama Saham &amp; Sektor</th>
                    <th style="width: 220px;">Rekomendasi Aksi</th>
                    <th class="text-end">Harga Saat Ini</th>
                    <th class="text-center" style="width: 120px;">RSI (<?= $rsiPeriod ?>)</th>
                    <th class="text-center" style="min-width: 110px;">Trend (EMA20/MA50)</th>
                    <th class="text-center">Konfirmasi Multi-Strategi</th>
                    <th class="text-end" style="min-width: 130px;">Target TP1 / TP2 / TP3</th>
                    <th class="text-end">Stop Loss</th>
                    <th class="text-end">R:R</th>
                    <th class="text-end">Confidence</th>
                    <th class="text-center" style="width: 90px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="12" class="text-center py-5 text-muted">
                            <?= \app\helpers\SvgHelper::icon('search', ['class' => 'display-6 text-secondary d-block mb-2']) ?>
                            <strong>Tidak ditemukan saham dengan kriteria filter saat ini.</strong>
                            <p class="small text-muted mb-0">Coba pilih tab filter "Semua" atau gunakan timeframe Daily.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $r): ?>
                        <?php
                        $conf = (int) ($r['confidence'] ?? 0);
                        $confColor = $conf >= 80 ? 'success' : ($conf >= 65 ? 'warning' : 'secondary');
                        $actionBadge = $r['action_badge'] ?? 'bg-secondary';
                        $actionLabel = $r['action_label'] ?? 'WAIT';
                        $rsiVal = (float) ($r['current_rsi'] ?? 50);
                        $rsiColor = $rsiVal <= 30 ? 'text-success fw-bold' : ($rsiVal >= 70 ? 'text-danger fw-bold' : ($rsiVal < 45 ? 'text-info' : 'text-dark'));
                        $isBuyAction = in_array($r['action'] ?? '', ['BUY_NOW', 'BUY_PULLBACK', 'MOMENTUM_BUY'], true);
                        $isSellAction = in_array($r['action'] ?? '', ['SELL_NOW', 'TAKE_PROFIT'], true);
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <?php if ($isBuyAction): ?>
                                        <span class="p-1 bg-success rounded-circle d-inline-block" title="Potensi Beli (Bullish Setup)" style="width: 8px; height: 8px;"></span>
                                    <?php elseif ($isSellAction): ?>
                                        <span class="p-1 bg-danger rounded-circle d-inline-block" title="Waspada / Exit (Bearish Setup)" style="width: 8px; height: 8px;"></span>
                                    <?php else: ?>
                                        <span class="p-1 bg-secondary bg-opacity-25 rounded-circle d-inline-block" title="Wait & See" style="width: 8px; height: 8px;"></span>
                                    <?php endif; ?>
                                    <strong class="fs-6 text-primary"><?= Html::encode($r['symbol']) ?></strong>
                                </div>
                                <?php if (!empty($r['approximated'])): ?>
                                    <span class="badge bg-light text-muted border d-block mt-1" style="font-size: 0.65rem;">approx</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= Html::encode($r['stock_name'] ?? $r['symbol']) ?></div>
                                <div class="small text-muted"><?= Html::encode($r['sector'] ?? 'Unknown Sector') ?></div>
                            </td>
                            <td>
                                <span class="badge <?= $actionBadge ?> px-2 py-1 mb-1 d-inline-block">
                                    <?= Html::encode($actionLabel) ?>
                                </span>
                                <div class="small text-muted" style="font-size: 0.72rem; line-height: 1.2;">
                                    <?= Html::encode($r['signal_reason'] ?? '') ?>
                                </div>
                            </td>
                            <td class="text-end fw-bold">
                                $<?= number_format((float) ($r['current_price'] ?? 0), 2) ?>
                            </td>
                            <td class="text-center">
                                <span class="fs-6 <?= $rsiColor ?>">
                                    <?= number_format($rsiVal, 1) ?>
                                </span>
                                <?php if ($rsiVal <= 30): ?>
                                    <span class="badge bg-success-subtle text-success border border-success d-block mx-auto mt-1" style="font-size: 0.65rem; width: fit-content;">OVERSOLD</span>
                                <?php elseif ($rsiVal >= 70): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger d-block mx-auto mt-1" style="font-size: 0.65rem; width: fit-content;">OVERBOUGHT</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex flex-column gap-1 align-items-center">
                                    <?php if (!empty($r['is_above_ema20'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success py-0" style="font-size: 0.68rem;" title="Harga ($<?= number_format((float)($r['current_price'] ?? 0), 2) ?>) di atas EMA20 ($<?= number_format((float)($r['ema20'] ?? 0), 2) ?>)">
                                            EMA20 ▲
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border py-0" style="font-size: 0.68rem;" title="Harga ($<?= number_format((float)($r['current_price'] ?? 0), 2) ?>) di bawah EMA20 ($<?= number_format((float)($r['ema20'] ?? 0), 2) ?>)">
                                            EMA20 ▼
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($r['is_above_ma50'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success py-0" style="font-size: 0.68rem;" title="Harga di atas MA50 ($<?= number_format((float)($r['ma50'] ?? 0), 2) ?>)">
                                            MA50 ▲
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border py-0" style="font-size: 0.68rem;" title="Harga di bawah MA50 ($<?= number_format((float)($r['ma50'] ?? 0), 2) ?>)">
                                            MA50 ▼
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex flex-column gap-1 align-items-center">
                                    <?php if (!empty($r['divergence'])): ?>
                                        <?php 
                                        $divObj = is_array($r['divergence']) ? $r['divergence'] : (is_array($r['divergence_details'] ?? null) ? $r['divergence_details'] : []);
                                        $divBarsAgo = (int)($divObj['bars_ago'] ?? 0);
                                        $isFreshDiv = !empty($divObj['is_fresh']) || $divBarsAgo <= 5;
                                        ?>
                                        <span class="badge <?= $isFreshDiv ? 'bg-primary text-white' : 'bg-primary-subtle text-primary border border-primary' ?>" title="Bullish Divergence: <?= $divBarsAgo ?> bar lalu (<?= $isFreshDiv ? 'Sinyal Baru / Fresh' : 'In Progress' ?>)">
                                            <?= \app\helpers\SvgHelper::icon('gem') ?> BULLISH DIV <?= $isFreshDiv ? '<span class="badge bg-success text-white py-0 px-1 ms-1" style="font-size:0.55rem;">FRESH</span>' : '<span class="text-muted ms-1" style="font-size:0.60rem;">(' . $divBarsAgo . 'b)</span>' ?>
                                        </span>
                                    <?php elseif (!empty($r['bearish_divergence'])): ?>
                                        <?php 
                                        $bearObj = is_array($r['bearish_divergence']) ? $r['bearish_divergence'] : (is_array($r['divergence_details'] ?? null) ? $r['divergence_details'] : []);
                                        $bearBarsAgo = (int)($bearObj['bars_ago'] ?? 0);
                                        $isFreshBear = !empty($bearObj['is_fresh']) || $bearBarsAgo <= 5;
                                        ?>
                                        <span class="badge <?= $isFreshBear ? 'bg-danger text-white' : 'bg-danger-subtle text-danger border border-danger' ?>" title="Bearish Divergence: <?= $bearBarsAgo ?> bar lalu">
                                            <?= \app\helpers\SvgHelper::icon('lightning-charge') ?> BEARISH DIV <?= $isFreshBear ? '<span class="badge bg-danger-emphasis text-white py-0 px-1 ms-1" style="font-size:0.55rem;">ACTIVE</span>' : '<span class="text-muted ms-1" style="font-size:0.60rem;">(' . $bearBarsAgo . 'b)</span>' ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($r['recent_double_bottom'])): ?>
                                        <?php $dbFresh = !empty($r['recent_double_bottom']['is_fresh']); ?>
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info" title="RSI Double Bottom: T1 (<?= $r['recent_double_bottom']['trough1']['rsi'] ?>) & T2 (<?= $r['recent_double_bottom']['trough2']['rsi'] ?>)">
                                            W-BOTTOM <?= $dbFresh ? '★ FRESH' : '' ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($r['volume_spike'])): ?>
                                        <span class="badge bg-info text-dark" title="Lonjakan Volume RVOL <?= $r['rvol'] ?? 0 ?>x">
                                            <?= \app\helpers\SvgHelper::icon('bar-chart-fill') ?> RVOL <?= $r['rvol'] ?? 0 ?>x
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($r['candle_pattern'])): ?>
                                        <span class="badge <?= ($r['candle_signal'] ?? '') === 'BULLISH' ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?>">
                                            <?= \app\helpers\SvgHelper::icon('reception-4') ?> <?= Html::encode($r['candle_pattern']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($r['is_golden_pocket'])): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning" title="Fibonacci Golden Pocket 50%-61.8%">
                                            <?= \app\helpers\SvgHelper::icon('bullseye') ?> Fib Golden Zone
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($r['is_shakeout_low']) || ($r['signal_type'] ?? '') === 'BUY_SHAKEOUT_LOW'): ?>
                                        <span class="badge bg-secondary text-white" title="Shakeout Low: Undercut support lalu memantul kembali">
                                            <?= \app\helpers\SvgHelper::icon('lightning-charge') ?> Shakeout Low <?= !empty($r['shakeout_pct']) ? '(-' . $r['shakeout_pct'] . '%)' : '' ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (empty($r['divergence']) && empty($r['bearish_divergence']) && empty($r['recent_double_bottom']) && empty($r['volume_spike']) && empty($r['candle_pattern']) && empty($r['is_golden_pocket']) && empty($r['is_shakeout_low']) && ($r['signal_type'] ?? '') !== 'BUY_SHAKEOUT_LOW'): ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="text-success fw-bold" title="TP1: Target Swing / Minimum 1.5R">
                                    <span class="badge bg-success text-white py-0 me-1" style="font-size: 0.65rem;">TP1</span>
                                    $<?= number_format((float) ($r['tp1_price'] ?? 0), 2) ?>
                                    <small class="fw-normal">(+<?= number_format((float) ($r['tp1_pct'] ?? 0), 1) ?>%)</small>
                                </div>
                                <div class="text-primary small" style="font-size: 0.75rem;" title="TP2: Ekstensi Fibonacci 1.272 / 2.8R">
                                    <span class="badge bg-primary-subtle text-primary border py-0 me-1" style="font-size: 0.62rem;">TP2</span>
                                    $<?= number_format((float) ($r['tp2_price'] ?? 0), 2) ?>
                                    <small>(+<?= number_format((float) ($r['tp2_pct'] ?? 0), 1) ?>%)</small>
                                </div>
                                <div class="text-muted small" style="font-size: 0.72rem;" title="TP3: Ekstensi Fibonacci 1.618 / 4.0R">
                                    <span class="badge bg-warning-subtle text-dark border py-0 me-1" style="font-size: 0.62rem;">TP3</span>
                                    $<?= number_format((float) ($r['tp3_price'] ?? 0), 2) ?>
                                    <small>(+<?= number_format((float) ($r['tp3_pct'] ?? 0), 1) ?>%)</small>
                                </div>
                            </td>
                            <td class="text-end text-danger small">
                                $<?= number_format((float) ($r['sl_price'] ?? 0), 2) ?>
                                <div class="text-muted" style="font-size: 0.7rem;">-<?= number_format((float) ($r['potential_risk'] ?? 0), 1) ?>%</div>
                            </td>
                            <td class="text-end fw-bold">
                                <?php if (($r['risk_reward'] ?? null) !== null && $r['risk_reward'] > 0): ?>
                                    <span class="<?= $r['risk_reward'] >= 2.0 ? 'text-success' : 'text-dark' ?>">
                                        1:<?= number_format((float) $r['risk_reward'], 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <span class="badge bg-<?= $confColor ?>"><?= $conf ?>%</span>
                            </td>
                            <td class="text-center">
                                <?= Html::a(
                                    \app\helpers\SvgHelper::icon('graph-up') . ' Detail',
                                    [
                                        '/rsi-double-bottom/detail',
                                        'symbol' => $r['symbol'],
                                        'timeframe' => $timeframe,
                                        'statusFilter' => $statusFilter,
                                        'sortBy' => $sortBy,
                                        'lookback' => $lookback,
                                        'tolerance' => $tolerance,
                                        'rsiPeriod' => $rsiPeriod,
                                        'maxRsi' => $maxRsi,
                                    ],
                                    ['class' => 'btn btn-sm btn-primary py-0 px-2']
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Strategy Guide Accordion -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-dark">
            <?= \app\helpers\SvgHelper::icon('book-half', ['class' => 'text-primary me-1']) ?> Pedoman Rekomendasi Waktu Beli &amp; Waktu Jual Berbasis RSI
        </h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <h6 class="fw-bold text-success"><?= \app\helpers\SvgHelper::icon('cart-check') ?> 1. Kapan Waktu Beli Terbaik?</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Oversold Reversal (Cross 30 UP):</strong> RSI sempat berada di bawah 30 lalu memantul melintas ke atas level 30.</li>
                    <li><strong>Bullish Divergence:</strong> Harga saham membuat <em>Lower Low</em> tetapi RSI membentuk <em>Higher Low</em>.</li>
                    <li><strong>Uptrend Pullback:</strong> Saham berada di atas MA50 dan RSI menguji support 40-50 lalu berbalik naik.</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-danger"><?= \app\helpers\SvgHelper::icon('box-arrow-right') ?> 2. Kapan Waktu Jual / TP Terbaik?</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Overbought Exit (Cross 70 DOWN):</strong> RSI telah melampaui level 70 dan mulai melintas turun di bawah 70.</li>
                    <li><strong>Bearish Divergence:</strong> Harga menembus rekor harga baru (<em>Higher High</em>) tetapi RSI gagal naik (<em>Lower High</em>).</li>
                    <li><strong>Extreme Overbought (>75-80):</strong> Waktu yang tepat untuk mengamankan profit sebagian (Take Profit).</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-primary"><?= \app\helpers\SvgHelper::icon('shield-check') ?> 3. Filter Trend &amp; Money Management</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Hindari Pisau Jatuh:</strong> Prioritaskan posisi Beli jika tren harga terkonfirmasi di atas MA50 atau terdapat Divergensi kuat.</li>
                    <li><strong>Stop Loss Terukur:</strong> Batasi risiko maksimal 2-3% di bawah Swing Low terdekat.</li>
                    <li><strong>Rasio R:R:</strong> Hanya ambil posisi jika proyeksi Risk to Reward minimal 1:1.5 ke target keuntungan (TP1).</li>
                </ul>
            </div>
        </div>
    </div>
</div>
