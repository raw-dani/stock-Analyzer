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

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">
            <?= \app\helpers\SvgHelper::icon('activity', ['class' => 'text-primary']) ?> <?= Html::encode($this->title) ?>
        </h1>
        <p class="text-muted small mb-0">
            Penentuan waktu beli (*entry*), waktu jual (*take profit / exit*), dan deteksi <strong>⚡ Shakeout Low (Liquidity Sweep)</strong> presisi berbasis Bollinger Bands (20, 2.0).
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm shadow-sm" role="group">
            <?php foreach ($timeframes as $tfKey => $tfLabel): ?>
                <?= Html::a(
                    Html::encode($tfLabel),
                    $filterWith(['timeframe' => $tfKey]),
                    ['class' => 'btn btn-sm ' . ((int) $params['timeframe'] === $tfKey ? 'btn-primary' : 'btn-outline-secondary')]
                ) ?>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-sm btn-outline-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#tvModal" <?= empty($tvSymbols) ? 'disabled' : '' ?>>
            <?= \app\helpers\SvgHelper::icon('tv') ?> TradingView (<?= count($tvSymbols) ?>)
        </button>

        <?= Html::a(
            \app\helpers\SvgHelper::icon('download') . ' Export CSV',
            Url::to(array_merge(['/bollinger-band/export'], $currentGet)),
            ['class' => 'btn btn-sm btn-outline-success shadow-sm']
        ) ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= $filterWith(['strategy' => 'all']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-white h-100 <?= $params['strategy'] === 'all' ? 'border-primary border-2' : '' ?>">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Total Saham</div>
                    <div class="h2 mb-0 fw-bold text-dark"><?= number_format((int) ($stats['total'] ?? 0)) ?></div>
                    <div class="small text-muted"><?= \app\helpers\SvgHelper::icon('grid') ?> Seluruh Hasil Scan</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= $filterWith(['strategy' => 'buy_all']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-success bg-opacity-10 h-100 <?= $params['strategy'] === 'buy_all' ? 'border-success border-2' : '' ?>">
                <div class="card-body p-3">
                    <div class="text-success small fw-semibold text-uppercase">Sinyal Beli (Buy)</div>
                    <div class="h2 mb-0 fw-bold text-success"><?= number_format((int) ($stats['buyCount'] ?? 0)) ?></div>
                    <div class="small text-success"><?= \app\helpers\SvgHelper::icon('check-circle') ?> Setup Beli Aktif</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-lg-3">
        <a href="<?= $filterWith(['strategy' => 'shakeout_low']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-warning bg-opacity-10 h-100 <?= $params['strategy'] === 'shakeout_low' ? 'border-warning border-2' : '' ?>">
                <div class="card-body p-3">
                    <div class="text-warning-emphasis small fw-semibold text-uppercase">
                        <?= \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-warning']) ?> Shakeout Low
                    </div>
                    <div class="h2 mb-0 fw-bold text-dark"><?= number_format((int) ($stats['shakeoutCount'] ?? 0)) ?></div>
                    <div class="small text-dark">Liquidity Sweep di Bawah Band</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= $filterWith(['strategy' => 'squeeze_breakout']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-primary bg-opacity-10 h-100 <?= $params['strategy'] === 'squeeze_breakout' ? 'border-primary border-2' : '' ?>">
                <div class="card-body p-3">
                    <div class="text-primary small fw-semibold text-uppercase">Squeeze Breakout</div>
                    <div class="h2 mb-0 fw-bold text-primary"><?= number_format((int) ($stats['squeezeCount'] ?? 0)) ?></div>
                    <div class="small text-primary"><?= \app\helpers\SvgHelper::icon('box-arrow-up-right') ?> Ledakan Volatilitas</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-12 col-md-4 col-lg-3">
        <a href="<?= $filterWith(['strategy' => 'sell_all']) ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm bg-danger bg-opacity-10 h-100 <?= $params['strategy'] === 'sell_all' ? 'border-danger border-2' : '' ?>">
                <div class="card-body p-3">
                    <div class="text-danger small fw-semibold text-uppercase">Take Profit / Exit</div>
                    <div class="h2 mb-0 fw-bold text-danger"><?= number_format((int) ($stats['sellCount'] ?? 0)) ?></div>
                    <div class="small text-danger"><?= \app\helpers\SvgHelper::icon('exclamation-triangle') ?> Upper Reversal &amp; Cut Loss</div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Strategy Quick Presets (1-Klik) -->
<div class="card border-0 shadow-sm mb-3 bg-light">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="small fw-bold text-muted me-1"><?= \app\helpers\SvgHelper::icon('lightning-fill', ['class' => 'text-warning']) ?> Preset Strategi Cepat:</span>
                
                <?= Html::a(
                    \app\helpers\SvgHelper::icon('grid-fill') . ' Semua Sinyal',
                    $filterWith(['strategy' => 'all']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'all' ? 'btn-dark' : 'btn-outline-dark')]
                ) ?>

                <?= Html::a(
                    \app\helpers\SvgHelper::icon('check-circle-fill') . ' Semua Posisi Beli',
                    $filterWith(['strategy' => 'buy_all']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'buy_all' ? 'btn-success' : 'btn-outline-success')]
                ) ?>

                <?= Html::a(
                    \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-warning']) . ' Shakeout Low (' . ($stats['shakeoutCount'] ?? 0) . ')',
                    $filterWith(['strategy' => 'shakeout_low']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'shakeout_low' ? 'btn-warning text-dark active fw-bold' : 'btn-outline-warning text-dark')]
                ) ?>

                <?= Html::a(
                    \app\helpers\SvgHelper::icon('box-arrow-up-right') . ' Squeeze Breakout (' . ($stats['squeezeCount'] ?? 0) . ')',
                    $filterWith(['strategy' => 'squeeze_breakout']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'squeeze_breakout' ? 'btn-primary' : 'btn-outline-primary')]
                ) ?>

                <?= Html::a(
                    \app\helpers\SvgHelper::icon('arrow-down-up') . ' Lower Band Bounce',
                    $filterWith(['strategy' => 'lower_bounce']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'lower_bounce' ? 'btn-info text-white' : 'btn-outline-info')]
                ) ?>

                <?= Html::a(
                    \app\helpers\SvgHelper::icon('currency-dollar') . ' Take Profit / Exit (' . ($stats['sellCount'] ?? 0) . ')',
                    $filterWith(['strategy' => 'sell_all']),
                    ['class' => 'btn btn-sm ' . ($params['strategy'] === 'sell_all' ? 'btn-danger' : 'btn-outline-danger')]
                ) ?>
            </div>

            <!-- Sort Bar -->
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Urutkan:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <?= Html::a('R:R', $filterWith(['sort' => 'rr']), ['class' => 'btn btn-sm ' . ($params['sort'] === 'rr' ? 'btn-secondary' : 'btn-outline-secondary')]) ?>
                    <?= Html::a('Gain TP1', $filterWith(['sort' => 'gain']), ['class' => 'btn btn-sm ' . ($params['sort'] === 'gain' ? 'btn-secondary' : 'btn-outline-secondary')]) ?>
                    <?= Html::a('RVOL', $filterWith(['sort' => 'rvol']), ['class' => 'btn btn-sm ' . ($params['sort'] === 'rvol' ? 'btn-secondary' : 'btn-outline-secondary')]) ?>
                    <?= Html::a('BandWidth', $filterWith(['sort' => 'bandwidth']), ['class' => 'btn btn-sm ' . ($params['sort'] === 'bandwidth' ? 'btn-secondary' : 'btn-outline-secondary')]) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Toolbar Lengkap -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <?= Html::beginForm(['/bollinger-band/index'], 'get', ['class' => 'row g-2 align-items-end']) ?>
            <?= Html::hiddenInput('timeframe', $params['timeframe']) ?>
            <?= Html::hiddenInput('sort', $params['sort']) ?>

            <div class="col-md-3 col-12">
                <label class="form-label small text-muted mb-1">Strategi Bollinger Bands</label>
                <?= Html::dropDownList('strategy', $params['strategy'], $presets, ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-md-2 col-6">
                <label class="form-label small text-muted mb-1">Min. Risk:Reward</label>
                <?= Html::dropDownList('minRr', $params['minRr'], [
                    '0' => 'Semua R:R',
                    '1.5' => 'Min. 1.5 : 1',
                    '2.0' => 'Min. 2.0 : 1',
                    '3.0' => 'Min. 3.0 : 1 (Asimetris)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>

            <div class="col-md-2 col-6">
                <label class="form-label small text-muted mb-1">Cari Simbol</label>
                <?= Html::textInput('symbol', $params['symbol'], ['class' => 'form-select form-select-sm', 'placeholder' => 'Contoh: BBCA, ASII']) ?>
            </div>

            <div class="col-md-3 col-8">
                <label class="form-label small text-muted mb-1">Sektor</label>
                <?= Html::textInput('sector', $params['sector'], ['class' => 'form-control form-control-sm', 'placeholder' => 'Semua Sektor']) ?>
            </div>

            <div class="col-md-2 col-4 d-flex gap-1">
                <?= Html::submitButton(\app\helpers\SvgHelper::icon('search') . ' Filter', ['class' => 'btn btn-primary btn-sm w-100']) ?>
                <?= Html::a(\app\helpers\SvgHelper::icon('arrow-counterclockwise'), ['/bollinger-band/index'], ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Reset Filter']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- Tabel Hasil Scanner -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold text-dark">
                <?= \app\helpers\SvgHelper::icon('table') ?> Daftar Sinyal Bollinger Bands
            </h5>
            <small class="text-muted">Menampilkan <?= count($results) ?> saham teranalisis</small>
        </div>
        <div class="small text-muted">
            Parameter: <strong>Period 20, Multiplier 2.0</strong>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
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
                    <label class="form-label small fw-bold text-muted">Format Simbol dengan Bursa (IDX:BBCA, ...)</label>
                    <textarea id="tvSymbolsArea" class="form-control form-control-sm" rows="4" readonly><?= Html::encode($tvSymbolsStr) ?></textarea>
                </div>
                <div>
                    <label class="form-label small fw-bold text-muted">Format Simbol Standar (BBCA, ASII, ...)</label>
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
