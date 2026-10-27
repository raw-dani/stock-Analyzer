<?php

declare(strict_types=1);

use app\services\StochasticService;
use yii\bootstrap5\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var array $results */
/** @var array $params */
/** @var array $stats */
/** @var array $sectors */
/** @var array $timeframes */
/** @var array $presets */

$this->title = 'Stochastic Oscillator Strategy Scanner';
$this->params['breadcrumbs'][] = $this->title;

$currentTimeframe = (int) ($params['timeframe'] ?? StochasticService::TIMEFRAME_1D);
?>

<div class="stochastic-index">
    <!-- Hero Banner Header -->
    <div class="card border-0 shadow-sm mb-4 text-white overflow-hidden" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #0e7490 100%); border-radius: 16px;">
        <div class="card-body p-4 p-lg-5 position-relative">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-white bg-opacity-15 text-white mb-3 shadow-sm" style="font-size: 0.82rem; backdrop-filter: blur(8px);">
                        <i class="bi bi-activity text-info"></i>
                        <span>Momentum &amp; Reversal Precision Intelligence</span>
                        <span class="badge bg-info text-dark fw-bold" style="font-size: 0.65rem;">PRO</span>
                    </div>
                    <h1 class="display-6 fw-bold mb-2 text-white">
                        📈 Stochastic Strategy Scanner
                    </h1>
                    <p class="lead mb-0 text-white-50" style="font-size: 1.05rem; max-width: 680px;">
                        Deteksi momentum pembalikan arah harga akurat melalui <strong>Bullish Golden Cross di zona Oversold (&lt; 20)</strong>, <strong>Regular &amp; Hidden Divergence</strong>, dan titik jenuh beli <em>Overbought (&gt; 80)</em> lengkap dengan Trade Plan (Entry, SL, TP1, TP2, dan R/R).
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <div class="d-inline-flex flex-column align-items-lg-end gap-2">
                        <div class="btn-group p-1 bg-white bg-opacity-15 rounded-pill shadow-sm" role="group" aria-label="Timeframe switcher" style="backdrop-filter: blur(8px);">
                            <?php foreach ([24 => '1D', 4 => '4H', 2 => '2H', 1 => '1H'] as $tfVal => $tfLabel): ?>
                                <a href="<?= Url::to(array_merge(['/stochastic/index'], $params, ['timeframe' => $tfVal])) ?>"
                                   class="btn btn-sm rounded-pill px-3 fw-bold <?= ($currentTimeframe === $tfVal) ? 'btn-info text-dark shadow-sm' : 'text-white' ?>"
                                   style="font-size: 0.82rem;">
                                    <?= Html::encode($tfLabel) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <span class="small text-white-50 font-monospace" style="font-size:0.75rem;">
                            Timeframe: <?= Html::encode($timeframes[$currentTimeframe] ?? 'Daily') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5 Key Metric Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- Metric 1: Golden Cross Oversold -->
        <div class="col-xxl col-lg-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-4" style="background: #ffffff; border-left: 5px solid #10b981 !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.70rem; letter-spacing:0.5px;">Golden Cross Oversold</div>
                        <div class="h3 fw-bold text-dark mb-0 mt-1"><?= (int) $stats['golden_cross_oversold'] ?> <span class="text-muted fw-normal" style="font-size:0.85rem;">emiten</span></div>
                        <div class="text-success small mt-1" style="font-size:0.75rem;">
                            <i class="bi bi-lightning-charge-fill me-1"></i>Cross %K &gt; %D di &lt; 20
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success" style="width: 48px; height: 48px; font-size: 1.35rem;">
                        <i class="bi bi-rocket-takeoff-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 2: Shakeout Low (Spring) -->
        <div class="col-xxl col-lg-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-4" style="background: #ffffff; border-left: 5px solid #f59e0b !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase d-flex align-items-center gap-1" style="font-size:0.70rem; letter-spacing:0.5px;">
                            Shakeout Low <span class="badge bg-danger text-white py-0 px-1" style="font-size:0.6rem;">HOT</span>
                        </div>
                        <div class="h3 fw-bold text-dark mb-0 mt-1"><?= (int) ($stats['shakeout_low_count'] ?? 0) ?> <span class="text-muted fw-normal" style="font-size:0.85rem;">emiten</span></div>
                        <div class="text-warning-emphasis small mt-1" style="font-size:0.75rem;">
                            <i class="bi bi-shield-fill-check me-1"></i>False Breakdown &amp; Reclaim
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-15 text-warning" style="width: 48px; height: 48px; font-size: 1.35rem;">
                        <i class="bi bi-shield-shaded"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 3: Bullish Divergence -->
        <div class="col-xxl col-lg-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-4" style="background: #ffffff; border-left: 5px solid #06b6d4 !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.70rem; letter-spacing:0.5px;">Bullish Divergence</div>
                        <div class="h3 fw-bold text-dark mb-0 mt-1"><?= (int) $stats['bullish_divergence'] ?> <span class="text-muted fw-normal" style="font-size:0.85rem;">saham</span></div>
                        <div class="text-info small mt-1" style="font-size:0.75rem;">
                            <i class="bi bi-bullseye me-1"></i>Pembalikan harga terkuat
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info" style="width: 48px; height: 48px; font-size: 1.35rem;">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 4: Zona Oversold (< 20) -->
        <div class="col-xxl col-lg-6 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-4" style="background: #ffffff; border-left: 5px solid #6366f1 !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.70rem; letter-spacing:0.5px;">Jenuh Jual (Oversold)</div>
                        <div class="h3 fw-bold text-dark mb-0 mt-1"><?= (int) $stats['oversold_count'] ?> <span class="text-muted fw-normal" style="font-size:0.85rem;">saham</span></div>
                        <div class="text-primary small mt-1" style="font-size:0.75rem;">
                            <i class="bi bi-arrow-down-circle me-1"></i>%K di bawah level 20
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" style="width: 48px; height: 48px; font-size: 1.35rem;">
                        <i class="bi bi-water"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 5: Zona Overbought (> 80) -->
        <div class="col-xxl col-lg-6 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-4" style="background: #ffffff; border-left: 5px solid #f43f5e !important;">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.70rem; letter-spacing:0.5px;">Jenuh Beli (Overbought)</div>
                        <div class="h3 fw-bold text-dark mb-0 mt-1"><?= (int) $stats['overbought_count'] ?> <span class="text-muted fw-normal" style="font-size:0.85rem;">saham</span></div>
                        <div class="text-danger small mt-1" style="font-size:0.75rem;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>%K di atas 80 (Area TP)
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger" style="width: 48px; height: 48px; font-size: 1.35rem;">
                        <i class="bi bi-fire"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Control Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-lg-4">
            <!-- Quick Preset Filter Pills -->
            <div class="d-flex align-items-center gap-2 overflow-auto pb-2 mb-3" style="white-space: nowrap;">
                <span class="small text-muted fw-bold me-1"><i class="bi bi-lightning-charge"></i> Quick Preset:</span>
                <a href="<?= Url::to(['/stochastic/index', 'timeframe' => $currentTimeframe, 'strategy' => 'all']) ?>"
                   class="btn btn-sm rounded-pill px-3 <?= (($params['strategy'] ?? 'all') === 'all') ? 'btn-primary text-white' : 'btn-light text-dark border' ?>">
                    Semua
                </a>
                <a href="<?= Url::to(['/stochastic/index', 'timeframe' => $currentTimeframe, 'strategy' => 'buy_all']) ?>"
                   class="btn btn-sm rounded-pill px-3 <?= (($params['strategy'] ?? 'all') === 'buy_all') ? 'btn-success text-white' : 'btn-light text-dark border' ?>">
                    Semua Sinyal Buy
                </a>
                <a href="<?= Url::to(['/stochastic/index', 'timeframe' => $currentTimeframe, 'strategy' => 'shakeout_low']) ?>"
                   class="btn btn-sm rounded-pill px-3 <?= (($params['strategy'] ?? 'all') === 'shakeout_low') ? 'btn-warning text-dark fw-bold' : 'btn-light text-dark border' ?>">
                    🛡️ Shakeout Low <span class="badge bg-danger text-white rounded-pill ms-1" style="font-size:0.6rem;">HOT</span>
                </a>
                <a href="<?= Url::to(['/stochastic/index', 'timeframe' => $currentTimeframe, 'strategy' => 'golden_cross_oversold']) ?>"
                   class="btn btn-sm rounded-pill px-3 <?= (($params['strategy'] ?? 'all') === 'golden_cross_oversold') ? 'btn-success text-white' : 'btn-light text-dark border' ?>">
                    🚀 Golden Cross &lt; 20
                </a>
                <a href="<?= Url::to(['/stochastic/index', 'timeframe' => $currentTimeframe, 'strategy' => 'bullish_divergence']) ?>"
                   class="btn btn-sm rounded-pill px-3 <?= (($params['strategy'] ?? 'all') === 'bullish_divergence') ? 'btn-info text-white' : 'btn-light text-dark border' ?>">
                    🎯 Bullish Divergence
                </a>
                <a href="<?= Url::to(['/stochastic/index', 'timeframe' => $currentTimeframe, 'strategy' => 'oversold_zone']) ?>"
                   class="btn btn-sm rounded-pill px-3 <?= (($params['strategy'] ?? 'all') === 'oversold_zone') ? 'btn-primary text-white' : 'btn-light text-dark border' ?>">
                    🌊 Oversold (%K &lt; 20)
                </a>
                <a href="<?= Url::to(['/stochastic/index', 'timeframe' => $currentTimeframe, 'strategy' => 'overbought_zone']) ?>"
                   class="btn btn-sm rounded-pill px-3 <?= (($params['strategy'] ?? 'all') === 'overbought_zone') ? 'btn-danger text-white' : 'btn-light text-dark border' ?>">
                    🔥 Overbought (%K &gt; 80)
                </a>
            </div>

            <form method="get" action="<?= Url::to(['/stochastic/index']) ?>" class="row g-3 align-items-end" id="stochastic-filter-form">
                <input type="hidden" name="timeframe" value="<?= $currentTimeframe ?>">

                <!-- Strategy Preset Filter -->
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Strategi &amp; Kondisi:</label>
                    <select name="strategy" class="form-select form-select-sm">
                        <?php foreach ($presets as $pKey => $pLabel): ?>
                            <option value="<?= Html::encode($pKey) ?>" <?= (($params['strategy'] ?? 'all') === $pKey) ? 'selected' : '' ?>>
                                <?= Html::encode($pLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Min Risk / Reward Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Min. Risk/Reward (R:R):</label>
                    <select name="minRr" class="form-select form-select-sm">
                        <option value="0.0" <?= (($params['minRr'] ?? 0.0) == 0.0) ? 'selected' : '' ?>>Semua R:R</option>
                        <option value="1.5" <?= (($params['minRr'] ?? 0.0) == 1.5) ? 'selected' : '' ?>>&ge; 1 : 1.5</option>
                        <option value="2.0" <?= (($params['minRr'] ?? 0.0) == 2.0) ? 'selected' : '' ?>>&ge; 1 : 2.0 (Bagus)</option>
                        <option value="2.5" <?= (($params['minRr'] ?? 0.0) == 2.5) ? 'selected' : '' ?>>&ge; 1 : 2.5 (Sangat Bagus)</option>
                    </select>
                </div>

                <!-- Sektor Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Sektor:</label>
                    <select name="sector" class="form-select form-select-sm">
                        <option value="all" <?= (($params['sector'] ?? 'all') === 'all') ? 'selected' : '' ?>>Semua Sektor</option>
                        <?php foreach ($sectors as $sec): ?>
                            <option value="<?= Html::encode($sec) ?>" <?= (($params['sector'] ?? '') === $sec) ? 'selected' : '' ?>>
                                <?= Html::encode($sec) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Simbol / Search Box -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Cari Simbol:</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="symbol" class="form-control form-control-sm border-start-0" placeholder="e.g. AAPL, NVDA..." value="<?= Html::encode($params['symbol'] ?? '') ?>">
                    </div>
                </div>

                <!-- Sorting Option -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Urutan Hasil:</label>
                    <select name="sort" class="form-select form-select-sm">
                        <option value="confidence_desc" <?= (($params['sort'] ?? '') === 'confidence_desc') ? 'selected' : '' ?>>Win-Rate Score</option>
                        <option value="rr_desc" <?= (($params['sort'] ?? '') === 'rr_desc') ? 'selected' : '' ?>>R/R Ratio Tertinggi</option>
                        <option value="k_asc" <?= (($params['sort'] ?? '') === 'k_asc') ? 'selected' : '' ?>>%K Paling Rendah (Oversold)</option>
                        <option value="k_desc" <?= (($params['sort'] ?? '') === 'k_desc') ? 'selected' : '' ?>>%K Paling Tinggi (Overbought)</option>
                        <option value="change_desc" <?= (($params['sort'] ?? '') === 'change_desc') ? 'selected' : '' ?>>Perubahan % Tertinggi</option>
                        <option value="symbol_asc" <?= (($params['sort'] ?? '') === 'symbol_asc') ? 'selected' : '' ?>>Simbol (A-Z)</option>
                    </select>
                </div>

                <!-- Submit / Reset Buttons -->
                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold" title="Terapkan Filter">
                        <i class="bi bi-filter"></i> Scan
                    </button>
                    <a href="<?= Url::to(['/stochastic/index', 'timeframe' => $currentTimeframe]) ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Scanner Table Results Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary fw-bold px-2 py-1">
                    <?= count($results) ?> Saham Ditemukan
                </span>
                <span class="text-muted small">Parameter Osilator: Slow Stochastic (14, 3, 3)</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success bg-opacity-10 text-success border border-success fw-normal small">
                    <i class="bi bi-check2-circle me-1"></i>Oversold (&lt;20) = Peluang Beli
                </span>
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger fw-normal small">
                    <i class="bi bi-exclamation-circle me-1"></i>Overbought (&gt;80) = Area TP/Exit
                </span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="table-stochastic">
                <thead class="table-light text-muted small fw-semibold" style="font-size: 0.78rem;">
                    <tr>
                        <th class="ps-3 py-3" style="width: 140px;">SIMBOL &amp; NAMA</th>
                        <th class="text-end py-3">HARGA TERAKHIR</th>
                        <th class="text-center py-3">STOCHASTIC (%K / %D)</th>
                        <th class="py-3">SINYAL &amp; STRATEGI</th>
                        <th class="text-center py-3">SCORE</th>
                        <th class="text-end py-3">ENTRY</th>
                        <th class="text-end py-3">STOP LOSS</th>
                        <th class="text-end py-3">TARGET 1 (TP1)</th>
                        <th class="text-center py-3">R : R</th>
                        <th class="text-center py-3">AKSI</th>
                        <th class="text-center pe-3 py-3" style="width: 110px;">DETAIL</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="font-size: 0.88rem;">
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <i class="bi bi-search fs-2 text-muted d-block mb-2"></i>
                                Tidak ada saham yang cocok dengan kriteria filter saat ini.<br>
                                <small class="text-secondary">Coba ubah opsi strategi atau gunakan pilihan "Semua Saham &amp; Kondisi".</small>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $item): ?>
                            <?php
                            $action = $item['action'];
                            $actionBadge = match ($action) {
                                StochasticService::ACTION_STRONG_BUY => '<span class="badge bg-success px-2 py-1 fw-bold"><i class="bi bi-rocket-takeoff-fill me-1"></i>STRONG BUY</span>',
                                StochasticService::ACTION_BUY => '<span class="badge bg-success bg-opacity-15 text-success border border-success px-2 py-1 fw-bold"><i class="bi bi-check-lg me-1"></i>BUY</span>',
                                StochasticService::ACTION_TAKE_PROFIT => '<span class="badge bg-warning text-dark px-2 py-1 fw-bold"><i class="bi bi-cash-stack me-1"></i>TAKE PROFIT</span>',
                                StochasticService::ACTION_SELL => '<span class="badge bg-danger px-2 py-1 fw-bold"><i class="bi bi-box-arrow-right me-1"></i>SELL</span>',
                                default => '<span class="badge bg-secondary bg-opacity-15 text-secondary border px-2 py-1">WATCH</span>',
                            };

                            $currK = (float) $item['curr_k'];
                            $currD = (float) $item['curr_d'];
                            $zoneColor = match (true) {
                                $currK < 20.0 => '#10b981', // oversold
                                $currK > 80.0 => '#ef4444', // overbought
                                default => '#0284c7', // neutral
                            };

                            $changePct = (float) ($item['change_pct'] ?? 0);
                            $changeColor = $changePct >= 0 ? 'text-success' : 'text-danger';
                            $changeSign = $changePct >= 0 ? '+' : '';
                            ?>
                            <tr>
                                <!-- Simbol & Nama -->
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="symbol-badge bg-light border fw-bold px-2 py-1 rounded font-monospace text-dark" style="font-size:0.85rem;">
                                            <?= Html::encode($item['symbol']) ?>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 140px;" title="<?= Html::encode($item['name']) ?>">
                                                <?= Html::encode($item['name'] ?: $item['symbol']) ?>
                                            </div>
                                            <div class="text-muted small" style="font-size: 0.70rem;">
                                                <?= Html::encode($item['sector'] ?: 'US Stock') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Harga Terakhir -->
                                <td class="text-end font-monospace">
                                    <div class="fw-bold text-dark">$<?= number_format((float) $item['current_price'], 2) ?></div>
                                    <div class="<?= $changeColor ?> small" style="font-size: 0.72rem;">
                                        <?= $changeSign ?><?= number_format($changePct, 2) ?>%
                                    </div>
                                </td>

                                <!-- Stochastic %K / %D -->
                                <td class="text-center font-monospace">
                                    <div class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded border" style="background-color: #f8fafc;">
                                        <span class="fw-bold" style="color: <?= $zoneColor ?>;">%K <?= number_format($currK, 1) ?></span>
                                        <span class="text-muted small">/</span>
                                        <span class="text-secondary small">%D <?= number_format($currD, 1) ?></span>
                                    </div>
                                    <div class="mt-1" style="font-size:0.68rem;">
                                        <?php if ($currK < 20.0): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success py-0 px-1">Oversold</span>
                                        <?php elseif ($currK > 80.0): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger py-0 px-1">Overbought</span>
                                        <?php else: ?>
                                            <span class="text-muted">Netral (<?= round($currK) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Sinyal & Strategi -->
                                <td>
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <span class="badge bg-<?= Html::encode($item['strategy_badge']) ?> bg-opacity-15 text-<?= Html::encode($item['strategy_badge']) ?> border border-<?= Html::encode($item['strategy_badge']) ?> fw-semibold" style="font-size:0.75rem;">
                                            <?= Html::encode($item['strategy_name']) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($item['shakeout_low']['detected'])): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-warning bg-opacity-25 text-dark border border-warning" style="font-size:0.67rem;">
                                                <i class="bi bi-shield-check me-1"></i>Support $<?= number_format($item['shakeout_low']['support_level'], 2) ?> (Sweep -<?= $item['shakeout_low']['sweep_pct'] ?>%)
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-muted text-truncate mt-1 small" style="font-size: 0.73rem; max-width: 250px;" title="<?= Html::encode($item['description']) ?>">
                                        <?= Html::encode($item['description']) ?>
                                    </div>
                                </td>

                                <!-- Win-Rate Score -->
                                <td class="text-center font-monospace">
                                    <span class="badge bg-light text-dark border fw-bold px-2 py-1">
                                        <?= (int) ($item['confidence_score'] ?? 50) ?>%
                                    </span>
                                </td>

                                <!-- Entry Price -->
                                <td class="text-end font-monospace fw-semibold text-dark">
                                    $<?= number_format((float) $item['entry_price'], 2) ?>
                                </td>

                                <!-- Stop Loss -->
                                <td class="text-end font-monospace">
                                    <div class="text-danger fw-semibold">$<?= number_format((float) $item['stop_loss'], 2) ?></div>
                                    <div class="text-muted small" style="font-size:0.70rem;">-<?= number_format((float) $item['stop_loss_pct'], 1) ?>%</div>
                                </td>

                                <!-- Target Profit 1 -->
                                <td class="text-end font-monospace">
                                    <div class="text-success fw-bold">$<?= number_format((float) $item['target_profit_1'], 2) ?></div>
                                    <div class="text-success small" style="font-size:0.70rem;">+<?= number_format((float) $item['target_profit_1_pct'], 1) ?>%</div>
                                </td>

                                <!-- Risk / Reward -->
                                <td class="text-center font-monospace">
                                    <span class="badge bg-light text-dark border fw-semibold">
                                        1 : <?= number_format((float) $item['risk_reward_ratio'], 1) ?>
                                    </span>
                                </td>

                                <!-- Aksi -->
                                <td class="text-center">
                                    <?= $actionBadge ?>
                                </td>

                                <!-- Detail Action -->
                                <td class="text-center pe-3">
                                    <a href="<?= Url::to(['/stochastic/detail', 'symbol' => $item['symbol'], 'timeframe' => $currentTimeframe]) ?>"
                                       class="btn btn-outline-primary btn-sm fw-bold px-2 py-1"
                                       title="Lihat Chart Interaktif &amp; Setup <?= Html::encode($item['symbol']) ?>">
                                        <i class="bi bi-graph-up me-1"></i>Setup
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
