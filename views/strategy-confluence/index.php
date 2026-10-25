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

$this->title = '🎯 Confluence Strategy Scanner (MA, RSI, MACD & Fibonacci)';
$this->params['breadcrumbs'][] = 'Strategy Confluence';

$symbolsStr = implode(', ', $symbols);
$tvSymbolsStr = implode(', ', $tvSymbols);

$currentGet = Yii::$app->request->get();
$filterWith = fn(array $overrides) => Url::to(array_merge(['/strategy-confluence/index'], $currentGet, $overrides));

$badgeAction = [
    'STRONG_BUY' => 'success shadow-sm',
    'BUY' => 'primary shadow-sm',
    'HOLD' => 'info text-white shadow-sm',
    'SELL' => 'danger shadow-sm',
    'WAIT' => 'secondary',
];
?>

<!-- 1. Unified App Hero Header -->
<div class="app-hero-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="app-hero-icon text-danger" style="background: rgba(239, 68, 68, 0.1);">
                <?= \app\helpers\SvgHelper::icon('crosshair') ?>
            </div>
            <div>
                <h1 class="app-hero-title">
                    <?= Html::encode($this->title) ?>
                </h1>
                <p class="app-hero-desc">
                    Sistem analisis <strong>Multi-Indikator &amp; Multi-Timeframe</strong>: Support/Resistance MA (SMA/EMA), RSI Oversold, MACD Momentum, dan Fibonacci Golden Pocket (61.8%) untuk akurasi posisi beli &amp; jual presisi.
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
                        ['class' => 'btn-tf ' . ((int) $params['timeframe'] === (int) $tfKey ? 'active' : '')]
                    ) ?>
                <?php endforeach; ?>
            </div>

            <button type="button" class="btn btn-sm btn-outline-secondary shadow-xs" data-bs-toggle="modal" data-bs-target="#tvModal" <?= empty($tvSymbols) ? 'disabled' : '' ?>>
                <?= \app\helpers\SvgHelper::icon('tv') ?> TradingView (<?= count($tvSymbols) ?>)
            </button>

            <?= Html::a(
                \app\helpers\SvgHelper::icon('download') . ' Ekspor CSV',
                Url::to(array_merge(['/strategy-confluence/export'], $currentGet)),
                ['class' => 'btn btn-sm btn-outline-success shadow-xs']
            ) ?>
        </div>
    </div>
</div>

<?= $this->render('//layouts/_sync_status_badge', [
    'fromCache' => $fromCache ?? false,
    'timeframe' => (int) ($params['timeframe'] ?? 24),
]) ?>

<!-- 2. Unified KPI Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'all']) ?>" class="kpi-card-unified kpi-accent-indigo <?= $params['strategy'] === 'all' ? 'kpi-selected' : '' ?>">
            <div class="kpi-header">
                <span class="kpi-label">Total Saham Dianalisis</span>
                <?= \app\helpers\SvgHelper::icon('grid', ['class' => 'text-muted']) ?>
            </div>
            <div class="kpi-value"><?= number_format($stats['total']) ?></div>
            <div class="kpi-subtext"><?= \app\helpers\SvgHelper::icon('check2-circle', ['class' => 'text-primary']) ?> Multi-Timeframe Scan</div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'strong_buy']) ?>" class="kpi-card-unified kpi-accent-emerald <?= $params['strategy'] === 'strong_buy' ? 'kpi-selected' : '' ?>">
            <div class="kpi-header">
                <span class="kpi-label text-success">Strong Buy Confluence</span>
                <?= \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-success']) ?>
            </div>
            <div class="kpi-value text-success"><?= number_format($stats['strongBuyCount']) ?></div>
            <div class="kpi-subtext text-success">Skor Konfluensi &ge; 75/100</div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'golden_pocket']) ?>" class="kpi-card-unified kpi-accent-amber <?= in_array($params['strategy'], ['golden_pocket', 'shakeout_low'], true) ? 'kpi-selected' : '' ?>">
            <div class="kpi-header">
                <span class="kpi-label text-warning-emphasis">Fibonacci / Shakeout</span>
                <?= \app\helpers\SvgHelper::icon('bullseye', ['class' => 'text-warning']) ?>
            </div>
            <div class="kpi-value text-dark">
                <?= number_format($stats['goldenPocketCount']) ?>
                <?php if (($stats['shakeoutCount'] ?? 0) > 0): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis fs-7 ms-1">⚡ <?= $stats['shakeoutCount'] ?> Shakeout</span>
                <?php endif; ?>
            </div>
            <div class="kpi-subtext">Golden Pocket &amp; Liquidity Sweep</div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= $filterWith(['strategy' => 'macd_cross']) ?>" class="kpi-card-unified kpi-accent-cyan <?= in_array($params['strategy'], ['macd_cross', 'rsi_oversold'], true) ? 'kpi-selected' : '' ?>">
            <div class="kpi-header">
                <span class="kpi-label text-cyan">MACD Cross / RSI</span>
                <?= \app\helpers\SvgHelper::icon('graph-up-arrow', ['class' => 'text-info']) ?>
            </div>
            <div class="kpi-value text-info"><?= number_format($stats['macdCrossCount']) ?> / <?= number_format($stats['oversoldCount']) ?></div>
            <div class="kpi-subtext">Golden Cross &amp; Oversold</div>
        </a>
    </div>
</div>

<!-- 3. Unified Filter Card & Presets -->
<div class="filter-card-unified">
    <!-- Preset Strategi Cepat -->
    <div class="preset-pills-bar">
        <span class="small fw-bold text-muted me-1"><?= \app\helpers\SvgHelper::icon('sliders2', ['class' => 'text-primary']) ?> Preset Cepat:</span>
        <?php foreach ($presets as $pKey => $pLabel): ?>
            <?= Html::a(
                Html::encode($pLabel),
                $filterWith(['strategy' => $pKey]),
                ['class' => 'preset-pill ' . ($params['strategy'] === $pKey ? 'active' : '')]
            ) ?>
        <?php endforeach; ?>
    </div>

    <!-- Filter Form -->
    <form method="get" action="<?= Url::to(['/strategy-confluence/index']) ?>" class="row g-2 align-items-end">
        <input type="hidden" name="strategy" value="<?= Html::encode($params['strategy']) ?>">
        <input type="hidden" name="timeframe" value="<?= Html::encode($params['timeframe']) ?>">

        <div class="col-12 col-md-2">
            <label class="form-label small text-muted mb-1">Cari Simbol</label>
            <input type="text" name="symbol" class="form-control form-control-sm text-uppercase" placeholder="misal: AAPL, NVDA" value="<?= Html::encode($params['symbol']) ?>">
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1">Min Skor Konfluensi</label>
            <select name="minScore" class="form-select form-select-sm">
                <option value="0" <?= $params['minScore'] === 0 ? 'selected' : '' ?>>Semua Skor</option>
                <option value="60" <?= $params['minScore'] === 60 ? 'selected' : '' ?>>Skor &ge; 60 (Buy Signal)</option>
                <option value="75" <?= $params['minScore'] === 75 ? 'selected' : '' ?>>Skor &ge; 75 (High Quality)</option>
                <option value="85" <?= $params['minScore'] === 85 ? 'selected' : '' ?>>Skor &ge; 85 (Ultra Confluence)</option>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1">Minimal Risk/Reward</label>
            <select name="minRr" class="form-select form-select-sm">
                <option value="0" <?= $params['minRr'] == 0.0 ? 'selected' : '' ?>>Semua R:R</option>
                <option value="1.5" <?= $params['minRr'] == 1.5 ? 'selected' : '' ?>>R:R &ge; 1.5x</option>
                <option value="2.0" <?= $params['minRr'] == 2.0 ? 'selected' : '' ?>>R:R &ge; 2.0x (Sangat Ideal)</option>
                <option value="3.0" <?= $params['minRr'] == 3.0 ? 'selected' : '' ?>>R:R &ge; 3.0x</option>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1">Bursa / Exchange</label>
            <select name="exchange" class="form-select form-select-sm">
                <option value="" <?= empty($params['exchange']) ? 'selected' : '' ?>>Semua Bursa</option>
                <option value="NASDAQ" <?= $params['exchange'] === 'NASDAQ' ? 'selected' : '' ?>>NASDAQ</option>
                <option value="NYSE" <?= $params['exchange'] === 'NYSE' ? 'selected' : '' ?>>NYSE</option>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1">Urutan (Sort)</label>
            <select name="sort" class="form-select form-select-sm">
                <option value="score" <?= $params['sort'] === 'score' ? 'selected' : '' ?>>Skor Tertinggi</option>
                <option value="rr" <?= $params['sort'] === 'rr' ? 'selected' : '' ?>>Risk/Reward Ratio</option>
                <option value="gain" <?= $params['sort'] === 'gain' ? 'selected' : '' ?>>Potensi Gain TP1</option>
                <option value="rsi" <?= $params['sort'] === 'rsi' ? 'selected' : '' ?>>RSI Terendah (Oversold)</option>
                <option value="rvol" <?= $params['sort'] === 'rvol' ? 'selected' : '' ?>>Volume RVOL Tertinggi</option>
            </select>
        </div>

        <div class="col-12 col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                <?= \app\helpers\SvgHelper::icon('search') ?> Pindai
            </button>
            <a href="<?= Url::to(['/strategy-confluence/index']) ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                <?= \app\helpers\SvgHelper::icon('arrow-counterclockwise') ?>
            </a>
        </div>
    </form>
</div>

<!-- 4. Tabel Hasil Scanner Unified -->
<div class="table-card-unified">
    <div class="table-card-header">
        <div>
            <h5 class="mb-0">
                <?= \app\helpers\SvgHelper::icon('table', ['class' => 'text-primary me-1']) ?> Peluang Saham Terkonfirmasi
            </h5>
            <small class="text-muted">Menampilkan <?= count($results) ?> saham teranalisis multi-timeframe</small>
        </div>
        <div class="small text-muted">
            <span class="badge bg-success-subtle text-success border border-success-subtle me-1">Skor &ge; 75: Strong Buy</span>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">Skor &ge; 60: Buy</span>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Golden Pocket: 61.8%</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-unified table-hover align-middle mb-0">
            <thead class="table-light text-secondary small text-uppercase">
                <tr>
                    <th>Simbol / Emiten</th>
                    <th>Harga Saat Ini</th>
                    <th>Skor Konfluensi</th>
                    <th>Aksi &amp; Sinyal</th>
                    <th>Support / MA</th>
                    <th>RSI (14)</th>
                    <th>MACD</th>
                    <th>Fibonacci Retrace</th>
                    <th>Area Beli (Entry)</th>
                    <th>Stop Loss</th>
                    <th>Target TP1 (Gain)</th>
                    <th>R:R</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="13" class="text-center py-5 text-muted">
                            <?= \app\helpers\SvgHelper::icon('inbox', ['class' => 'fs-1 d-block mb-2 text-secondary opacity-50']) ?>
                            Tidak ada saham yang sesuai dengan kriteria filter confluence yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <!-- Simbol & Nama -->
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div>
                                        <div class="fw-bold text-dark">
                                            <?= Html::a(Html::encode($r['symbol']), ['detail', 'symbol' => $r['symbol']], ['class' => 'text-decoration-none text-primary fw-bold']) ?>
                                            <span class="badge bg-light text-secondary border small ms-1"><?= Html::encode($r['exchange']) ?></span>
                                        </div>
                                        <div class="text-muted text-truncate small" style="max-width: 140px;" title="<?= Html::encode($r['name']) ?>">
                                            <?= Html::encode($r['name']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Harga Saat Ini -->
                            <td>
                                <div class="fw-bold text-dark">$<?= number_format($r['current_price'], 2) ?></div>
                                <div class="small text-muted">RVOL: <?= $r['rvol'] ?>x</div>
                            </td>

                            <!-- Skor Konfluensi -->
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; width: 50px;">
                                        <div class="progress-bar <?= $r['confluence_score'] >= 75 ? 'bg-success' : ($r['confluence_score'] >= 60 ? 'bg-primary' : 'bg-warning') ?>" 
                                             role="progressbar" style="width: <?= $r['confluence_score'] ?>%;"></div>
                                    </div>
                                    <span class="badge <?= $r['confluence_score'] >= 75 ? 'bg-success' : ($r['confluence_score'] >= 60 ? 'bg-primary' : 'bg-secondary') ?> fs-7">
                                        <?= $r['confluence_score'] ?>/100
                                    </span>
                                </div>
                                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                                    Higher TF: <span class="fw-semibold <?= $r['higher_tf_trend'] === 'BULLISH' ? 'text-success' : ($r['higher_tf_trend'] === 'BEARISH' ? 'text-danger' : 'text-muted') ?>"><?= $r['higher_tf_trend'] ?></span>
                                </div>
                            </td>

                            <!-- Aksi & Sinyal -->
                            <td>
                                <span class="badge bg-<?= $badgeAction[$r['action']] ?? 'secondary' ?> mb-1">
                                    <?= Html::encode($r['action']) ?>
                                </span>
                                <div class="small text-dark fw-semibold"><?= Html::encode($r['signal_title']) ?></div>
                            </td>

                            <!-- Support & MA -->
                            <td>
                                <div class="small fw-semibold text-dark">
                                    $<?= number_format($r['support'], 2) ?>
                                    <?php if (!empty($r['is_shakeout_low'])): ?>
                                        <span class="badge bg-warning text-dark border border-warning ms-1" style="font-size: 0.65rem;" title="Liquidity sweep <?= $r['shakeout_undercut_pct'] ?? 0 ?>% di bawah support">
                                            ⚡ Sweep <?= $r['shakeout_undercut_pct'] ?? 0 ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted" style="font-size: 0.72rem;">
                                    EMA20: $<?= $r['ema_20'] ? number_format($r['ema_20'], 2) : '—' ?>
                                </div>
                            </td>

                            <!-- RSI (14) -->
                            <td>
                                <span class="badge <?= $r['rsi'] <= 30 ? 'bg-success text-white' : ($r['rsi'] <= 40 ? 'bg-success-subtle text-success border border-success' : ($r['rsi'] >= 70 ? 'bg-danger text-white' : 'bg-light text-dark border')) ?>">
                                    <?= $r['rsi'] ?>
                                </span>
                                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                                    <?= $r['rsi_status'] ?>
                                </div>
                            </td>

                            <!-- MACD -->
                            <td>
                                <div class="small fw-semibold <?= $r['macd'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $r['macd'] ?>
                                </div>
                                <?php if ($r['macd_cross'] === 'BULLISH_CROSS'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success" style="font-size: 0.65rem;">
                                        <?= \app\helpers\SvgHelper::icon('arrow-up-circle-fill') ?> Bullish Cross
                                    </span>
                                <?php elseif ($r['macd_cross'] === 'BEARISH_CROSS'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger" style="font-size: 0.65rem;">
                                        <?= \app\helpers\SvgHelper::icon('arrow-down-circle-fill') ?> Bearish Cross
                                    </span>
                                <?php else: ?>
                                    <span class="small text-muted" style="font-size: 0.7rem;">Hist: <?= $r['macd_hist'] ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Fibonacci Retracement -->
                            <td>
                                <div class="small fw-semibold text-dark"><?= $r['retrace_pct'] ?>%</div>
                                <?php if ($r['in_golden_pocket']): ?>
                                    <span class="badge bg-warning text-dark shadow-sm" style="font-size: 0.65rem;">
                                        <?= \app\helpers\SvgHelper::icon('star-fill') ?> Golden Pocket
                                    </span>
                                <?php else: ?>
                                    <span class="small text-muted" style="font-size: 0.7rem;">Swing: $<?= number_format($r['swing_low'], 2) ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Area Beli (Entry) -->
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary font-monospace">
                                    <?= Html::encode($r['entry_range']) ?>
                                </span>
                            </td>

                            <!-- Stop Loss -->
                            <td>
                                <div class="fw-bold text-danger">$<?= number_format($r['stop_loss'], 2) ?></div>
                                <div class="small text-danger" style="font-size: 0.72rem;">-<?= $r['stop_loss_pct'] ?>%</div>
                            </td>

                            <!-- Target TP1 (Gain) -->
                            <td>
                                <div class="fw-bold text-success">$<?= number_format($r['target_1'], 2) ?></div>
                                <div class="small text-success" style="font-size: 0.72rem;">+<?= $r['target_1_pct'] ?>%</div>
                            </td>

                            <!-- Risk / Reward -->
                            <td>
                                <span class="badge <?= $r['rr_ratio'] >= 2.0 ? 'bg-success' : 'bg-primary' ?> fs-7">
                                    <?= $r['rr_ratio'] ?>x
                                </span>
                            </td>

                            <!-- Aksi Buka Detail -->
                            <td class="text-center">
                                <?= Html::a(
                                    \app\helpers\SvgHelper::icon('graph-up-arrow') . ' Detail',
                                    ['detail', 'symbol' => $r['symbol'], 'timeframe' => $params['timeframe']],
                                    ['class' => 'btn btn-sm btn-outline-primary shadow-sm text-nowrap']
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
