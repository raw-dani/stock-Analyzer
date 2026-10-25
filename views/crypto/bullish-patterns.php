<?php

/** @var yii\web\View $this */
/** @var string $timeframe */
/** @var string $patternType */
/** @var string $status */
/** @var int $minConfidence */
/** @var float $minRr */
/** @var string $search */
/** @var string $sort */
/** @var array $results */
/** @var array $stats */
/** @var array $patternTypes */
/** @var array $statusOptions */
/** @var array $timeframes */
/** @var string $currency */
/** @var float $usdtIdrRate */

use app\services\crypto\CryptoBullishPatternService;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$currency = $currency ?? 'IDR';
$usdtIdrRate = $usdtIdrRate ?? 17900.0;

$this->title = 'Crypto Bullish Continuation & Breakout Patterns';
$this->params['breadcrumbs'][] = ['label' => 'Crypto Market', 'url' => ['/crypto/index']];
$this->params['breadcrumbs'][] = 'Bullish Patterns';
?>

<div class="crypto-bullish-patterns pb-5">
    <!-- Header Section -->
    <div class="app-hero-header mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <div class="app-hero-icon text-success bg-success bg-opacity-10">
                        <i class="bi bi-rocket-takeoff"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h1 class="app-hero-title mb-0">Bullish Breakout &amp; Continuation</h1>
                            <span class="badge bg-dark text-warning border border-warning px-2 py-1 d-inline-flex align-items-center gap-1 rounded-pill">
                                <span class="live-pulse"></span> CRYPTO 24/7
                            </span>
                            <span class="badge bg-success-subtle text-success px-2 py-1 fw-bold rounded-pill">Pattern Engine</span>
                        </div>
                        <p class="app-hero-desc mb-0 mt-1">
                            Deteksi pola klasik: <strong>Cup &amp; Handle</strong>, <strong>Bullish Flag</strong>, <strong>Pennant</strong>, <strong>Ascending Triangle</strong>, &amp; <strong>Darvas Box</strong> dengan konfirmasi volume beli agresif Binance.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Currency & Timeframe Toolbar -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Currency Switcher (IDR vs USDT) -->
                <div class="btn-group btn-group-sm shadow-sm" role="group" title="Pilih Mata Uang Denominasi">
                    <a href="<?= Url::to(['/crypto/bullish-patterns', 'timeframe' => $timeframe, 'patternType' => $patternType, 'status' => $status, 'minConfidence' => $minConfidence, 'minRr' => $minRr, 'search' => $search, 'sort' => $sort, 'currency' => 'IDR']) ?>"
                       class="btn <?= $currency === 'IDR' ? 'btn-success active fw-bold' : 'btn-outline-secondary' ?>">
                        🇮🇩 IDR (Rp)
                    </a>
                    <a href="<?= Url::to(['/crypto/bullish-patterns', 'timeframe' => $timeframe, 'patternType' => $patternType, 'status' => $status, 'minConfidence' => $minConfidence, 'minRr' => $minRr, 'search' => $search, 'sort' => $sort, 'currency' => 'USDT']) ?>"
                       class="btn <?= $currency === 'USDT' ? 'btn-primary active fw-bold' : 'btn-outline-secondary' ?>">
                        💵 USDT ($)
                    </a>
                </div>

                <!-- Live USDT/IDR Rate Badge -->
                <span class="badge bg-light text-dark border shadow-xs d-none d-sm-inline-flex align-items-center gap-1 font-monospace rounded-pill" style="font-size: 0.72rem;" title="Kurs Live USDT/IDR Binance Spot">
                    <span class="live-pulse" style="width: 6px; height: 6px;"></span>
                    1 USDT = Rp <?= number_format($usdtIdrRate, 0, ',', '.') ?>
                </span>

                <div class="btn-group-tf shadow-sm">
                    <?php foreach ($timeframes as $tfKey => $tfName): ?>
                        <a href="<?= Url::to(['/crypto/bullish-patterns', 'timeframe' => $tfKey, 'patternType' => $patternType, 'status' => $status, 'minConfidence' => $minConfidence, 'minRr' => $minRr, 'search' => $search, 'sort' => $sort, 'currency' => $currency]) ?>"
                           class="btn-tf <?= $timeframe === $tfKey ? 'active' : '' ?>">
                            <?= strtoupper($tfKey) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <a href="<?= Url::to(['/crypto/index', 'timeframe' => $timeframe, 'currency' => $currency]) ?>" class="btn btn-sm btn-outline-secondary shadow-sm rounded-pill">
                    <i class="bi bi-arrow-left"></i> Volume Hub
                </a>
            </div>
        </div>
    </div>

    <?= $this->render('//layouts/_crypto_sync_status_badge', [
        'fromCache' => $fromCache ?? false,
        'timeframe' => $timeframe,
    ]) ?>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="kpi-card-unified kpi-accent-indigo h-100">
                <div class="kpi-card-label">Total Pola</div>
                <div class="kpi-card-value text-indigo-700"><?= $stats['total'] ?></div>
                <div class="kpi-card-sub text-muted mt-1">Pola terdeteksi</div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <div class="kpi-card-unified kpi-accent-emerald h-100">
                <div class="kpi-card-label">Buy Zone</div>
                <div class="kpi-card-value text-emerald-700"><?= $stats['buyZoneCount'] ?></div>
                <div class="kpi-card-sub text-emerald-600 mt-1">Breakout segar + vol</div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <div class="kpi-card-unified kpi-accent-cyan h-100">
                <div class="kpi-card-label">Retest Support</div>
                <div class="kpi-card-value text-cyan-700"><?= $stats['retestCount'] ?></div>
                <div class="kpi-card-sub text-muted mt-1">Pullback ke resisten</div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <div class="kpi-card-unified kpi-accent-amber h-100">
                <div class="kpi-card-label">Sedang Membentuk</div>
                <div class="kpi-card-value text-amber-700"><?= $stats['formingCount'] ?></div>
                <div class="kpi-card-sub text-muted mt-1">Dekat level breakout</div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <div class="kpi-card-unified kpi-accent-indigo h-100">
                <div class="kpi-card-label">High Confidence</div>
                <div class="kpi-card-value text-indigo-700"><?= $stats['highConfidenceCount'] ?></div>
                <div class="kpi-card-sub text-muted mt-1">Keyakinan &ge; 75%</div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <div class="kpi-card-unified kpi-accent-emerald h-100">
                <div class="kpi-card-label">High R:R Ratio</div>
                <div class="kpi-card-value text-emerald-700"><?= $stats['highRrCount'] ?></div>
                <div class="kpi-card-sub text-emerald-600 mt-1">R:R &ge; 2.0x</div>
            </div>
        </div>
    </div>

    <!-- Filter & Pattern Toolbar -->
    <div class="filter-card-unified mb-4">
        <div class="filter-card-body p-3">
            <form method="get" action="<?= Url::to(['/crypto/bullish-patterns']) ?>" class="row g-2 align-items-center">
                <input type="hidden" name="timeframe" value="<?= Html::encode($timeframe) ?>">
                <input type="hidden" name="currency" value="<?= Html::encode($currency) ?>">

                <div class="col-12 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0" 
                               placeholder="Cari simbol (BTC, SOL, dll)..." 
                               value="<?= Html::encode($search) ?>">
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <select name="patternType" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($patternTypes as $ptKey => $ptLabel): ?>
                            <option value="<?= $ptKey ?>" <?= $patternType === $ptKey ? 'selected' : '' ?>><?= Html::encode($ptLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($statusOptions as $sKey => $sLabel): ?>
                            <option value="<?= $sKey ?>" <?= $status === $sKey ? 'selected' : '' ?>><?= Html::encode($sLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="confidence_desc" <?= $sort === 'confidence_desc' ? 'selected' : '' ?>>Urutkan: Confidence Tertinggi</option>
                        <option value="rr_desc" <?= $sort === 'rr_desc' ? 'selected' : '' ?>>Urutkan: R:R Tertinggi</option>
                        <option value="upside_desc" <?= $sort === 'upside_desc' ? 'selected' : '' ?>>Urutkan: Target Upside %</option>
                        <option value="distance_asc" <?= $sort === 'distance_asc' ? 'selected' : '' ?>>Urutkan: Dekat Level Breakout</option>
                    </select>
                </div>

                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100 shadow-sm">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if ($search !== '' || $patternType !== 'ALL' || $status !== '' || $sort !== 'confidence_desc'): ?>
                        <a href="<?= Url::to(['/crypto/bullish-patterns', 'timeframe' => $timeframe]) ?>" class="btn btn-sm btn-light border" title="Reset">
                            <i class="bi bi-x"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Patterns Results Table -->
    <div class="table-card-unified">
        <div class="table-responsive">
            <table class="table-unified">
                <thead class="table-light">
                    <tr>
                        <th style="width: 190px;">Asset / Koin</th>
                        <th>Pola Terdeteksi</th>
                        <th>Harga & Level Breakout</th>
                        <th>Status Eksekusi</th>
                        <th>Volume & Confidence</th>
                        <th style="min-width: 220px;">Trading Plan (SL, TP1, TP2, R:R)</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-rocket-takeoff fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ada pola bullish yang memenuhi filter pencarian ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $fmtPrice = function(float $valUsd) use ($currency, $usdtIdrRate): string {
                            if ($currency === 'IDR') {
                                $valIdr = $valUsd * $usdtIdrRate;
                                if ($valIdr >= 1000000) {
                                    return 'Rp ' . number_format($valIdr, 0, ',', '.');
                                } elseif ($valIdr >= 1) {
                                    return 'Rp ' . number_format($valIdr, 2, ',', '.');
                                } else {
                                    return 'Rp ' . rtrim(rtrim(number_format($valIdr, 4, ',', '.'), '0'), ',');
                                }
                            }
                            if ($valUsd >= 1000) return '$' . number_format($valUsd, 2);
                            if ($valUsd >= 1) return '$' . number_format($valUsd, 4);
                            if ($valUsd >= 0.0001) return '$' . number_format($valUsd, 6);
                            return '$' . rtrim(rtrim(number_format($valUsd, 8), '0'), '.');
                        };

                        $fmtDual = function(float $valUsd) use ($currency, $usdtIdrRate): string {
                            if ($currency === 'IDR') {
                                if ($valUsd >= 1000) return '$' . number_format($valUsd, 2);
                                if ($valUsd >= 1) return '$' . number_format($valUsd, 4);
                                return '$' . number_format($valUsd, 6);
                            }
                            $valIdr = $valUsd * $usdtIdrRate;
                            return 'Rp ' . number_format($valIdr, 0, ',', '.');
                        };

                        foreach ($results as $index => $r): 
                            $coin = $r['asset'];
                            $price = $r['current_price'];
                            $breakoutPrice = $r['breakout_price'];
                            $diff = $r['distance_to_breakout_pct'];
                            $status = $r['trade_status'];
                            $conf = $r['confidence'];
                            $rr = $r['rr_ratio'];
                            $upside = $r['potential_upside'];
                            $bullTrap = $r['bull_trap_detected'];
                            $chg24h = (float)($r['price_change_24h'] ?? 0.0);
                            $rowId = 'detail-pat-' . strtolower($coin->symbol) . '-' . strtolower($r['pattern_type']);

                            $formattedPrice = $fmtPrice($price);
                            $formattedBreakout = $fmtPrice($breakoutPrice);

                            $statusBadge = match ($status) {
                                CryptoBullishPatternService::STATUS_BUY_ZONE => 'bg-success text-white',
                                CryptoBullishPatternService::STATUS_RETEST => 'bg-primary text-white',
                                CryptoBullishPatternService::STATUS_CONSOLIDATION_BOUNCE => 'bg-info text-dark',
                                CryptoBullishPatternService::STATUS_FORMING => 'bg-warning text-dark',
                                CryptoBullishPatternService::STATUS_EXTENDED => 'bg-secondary text-white',
                                CryptoBullishPatternService::STATUS_BULL_TRAP => 'bg-danger text-white',
                                default => 'bg-light text-dark',
                            };

                            $patternIcon = match ($r['pattern_type']) {
                                CryptoBullishPatternService::PATTERN_CUP_AND_HANDLE => '☕',
                                CryptoBullishPatternService::PATTERN_BULLISH_FLAG => '🚩',
                                CryptoBullishPatternService::PATTERN_BULLISH_PENNANT => '📐',
                                CryptoBullishPatternService::PATTERN_ASCENDING_TRIANGLE => '🔺',
                                CryptoBullishPatternService::PATTERN_BULLISH_RECTANGLE => '📦',
                                default => '📈',
                            };
                        ?>
                            <tr class="align-middle">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= Html::encode($coin->getIconUrl()) ?>" 
                                             alt="<?= Html::encode($coin->symbol) ?>" 
                                             class="rounded-circle border shadow-xs"
                                             style="width: 34px; height: 34px; object-fit: cover;"
                                             onerror="this.src='https://assets.coincap.io/assets/icons/btc@2x.png';">
                                        <div>
                                            <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                                <a href="<?= Url::to(['/crypto/view', 'symbol' => $coin->symbol, 'timeframe' => $timeframe, 'currency' => $currency]) ?>" class="text-dark text-decoration-none hover-primary">
                                                    <?= Html::encode($coin->symbol) ?>
                                                </a>
                                                <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.65rem;"><?= $currency === 'IDR' ? 'SPOT IDR' : 'USDT' ?></span>
                                            </div>
                                            <div class="text-muted small text-truncate" style="font-size: 0.75rem; max-width: 110px;">
                                                <?= Html::encode($coin->name) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="fw-bold text-dark">
                                        <span class="me-1"><?= $patternIcon ?></span> <?= Html::encode($r['pattern_name']) ?>
                                    </div>
                                    <div class="text-muted small text-truncate" style="font-size: 0.75rem; max-width: 220px;" title="<?= Html::encode($r['description']) ?>">
                                        <?= Html::encode($r['description']) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="font-monospace fw-bold text-dark"><?= $formattedPrice ?></div>
                                    <div class="small text-muted font-monospace">
                                        Breakout: <strong><?= $formattedBreakout ?></strong>
                                    </div>
                                    <div class="text-muted font-monospace" style="font-size: 0.68rem;">
                                        <?= $fmtDual($price) ?>
                                    </div>
                                    <div class="small <?= $diff >= 0 ? 'text-success' : 'text-primary' ?> font-monospace">
                                        <?= ($diff >= 0 ? '+' : '') . number_format($diff, 2) ?>% dari level
                                    </div>
                                </td>

                                <td>
                                    <span class="badge <?= $statusBadge ?> px-2 py-1 mb-1 shadow-xs">
                                        <?= Html::encode($r['trade_status_label']) ?>
                                    </span>
                                    <?php if ($bullTrap): ?>
                                        <div class="badge bg-danger text-white p-1 mt-1 d-block shadow-xs" style="font-size: 0.68rem;">
                                            <i class="bi bi-exclamation-triangle-fill"></i> Waspada Bull Trap!
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <div class="score-circle-sm <?= $conf >= 75 ? 'border-success text-success bg-success-subtle' : 'border-primary text-primary bg-primary-subtle' ?>">
                                            <?= $conf ?>%
                                        </div>
                                        <span class="small fw-bold <?= $conf >= 75 ? 'text-success' : 'text-dark' ?>">Confidence</span>
                                    </div>
                                    <div class="small text-muted font-monospace">
                                        Taker Buy: <strong class="text-success"><?= $r['buy_ratio'] ?>%</strong> &bull; RVOL: <?= $r['rvol'] ?>x
                                    </div>
                                </td>

                                <td>
                                    <div class="small font-monospace">
                                        <div><span class="text-muted">SL:</span> <strong class="text-danger"><?= $fmtPrice($r['stop_loss']) ?></strong> (-<?= $r['risk_pct'] ?>%)</div>
                                        <div><span class="text-muted">TP1:</span> <strong class="text-success"><?= $fmtPrice($r['target_tp1']) ?></strong> (+<?= $upside ?>%)</div>
                                        <div><span class="text-muted">TP2:</span> <strong class="text-primary"><?= $fmtPrice($r['target_tp2']) ?></strong></div>
                                        <div class="mt-1">
                                            <span class="badge <?= $rr >= 2.0 ? 'bg-success' : ($rr >= 1.5 ? 'bg-primary' : 'bg-secondary') ?>">
                                                R:R 1 : <?= $rr ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1">
                                        <button class="btn btn-sm btn-success shadow-sm d-inline-flex align-items-center gap-1 btn-detail-toggle"
                                                type="button"
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#<?= $rowId ?>" 
                                                aria-expanded="false" 
                                                aria-controls="<?= $rowId ?>">
                                            <i class="bi bi-chevron-down"></i>
                                            <span>Detail</span>
                                        </button>
                                        <a href="<?= Url::to(['/crypto/view', 'symbol' => $coin->symbol, 'timeframe' => $timeframe]) ?>" 
                                           class="btn btn-sm btn-outline-secondary shadow-sm d-inline-flex align-items-center gap-1"
                                           title="Buka Interaktif Candlestick Chart">
                                            <i class="bi bi-graph-up"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <!-- Expandable Pattern Detail Panel -->
                            <tr class="detail-row">
                                <td colspan="7" class="p-0 border-0">
                                    <div class="collapse" id="<?= $rowId ?>">
                                        <div class="p-3 p-md-4 bg-body-tertiary border-top border-bottom shadow-inner">
                                            <!-- Sub-Header Info Bar -->
                                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <span class="badge bg-dark text-warning border px-2 py-1 font-monospace">
                                                        BINANCE:<?= Html::encode($coin->symbol) ?>USDT
                                                    </span>
                                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1">
                                                        Timeframe: <?= strtoupper($timeframe) ?>
                                                    </span>
                                                    <span class="badge bg-success text-white px-2 py-1 fw-bold">
                                                        <?= $patternIcon ?> <?= Html::encode($r['pattern_name']) ?>
                                                    </span>
                                                    <span class="badge <?= $statusBadge ?> px-2 py-1">
                                                        <?= Html::encode($r['trade_status_label']) ?>
                                                    </span>
                                                    <span class="small text-muted font-monospace">
                                                        Harga Saat Ini: <strong class="text-dark"><?= $formattedPrice ?></strong>
                                                    </span>
                                                </div>

                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <a href="https://indodax.com/market/<?= Html::encode($coin->symbol) ?>IDR" 
                                                       target="_blank" 
                                                       rel="noopener" 
                                                       class="btn btn-xs btn-outline-success text-dark border-success shadow-xs"
                                                       title="Trading Spot IDR di Indodax">
                                                        <i class="bi bi-box-arrow-up-right me-1"></i>Indodax (IDR)
                                                    </a>
                                                    <a href="https://www.tokocrypto.com/trade/<?= Html::encode($coin->symbol) ?>_BIDR" 
                                                       target="_blank" 
                                                       rel="noopener" 
                                                       class="btn btn-xs btn-outline-primary text-dark border-primary shadow-xs"
                                                       title="Trading Spot BIDR di Tokocrypto">
                                                        <i class="bi bi-box-arrow-up-right me-1"></i>Tokocrypto
                                                    </a>
                                                    <a href="https://www.binance.com/en/trade/<?= Html::encode($coin->symbol) ?>_USDT?type=spot" 
                                                       target="_blank" 
                                                       rel="noopener" 
                                                       class="btn btn-xs btn-outline-warning text-dark border-warning shadow-xs"
                                                       title="Trading Spot 1x Tanpa Leverage di Binance">
                                                        <i class="bi bi-box-arrow-up-right me-1"></i>Binance Spot
                                                    </a>
                                                    <a href="<?= Url::to(['/crypto/view', 'symbol' => $coin->symbol, 'timeframe' => $timeframe, 'currency' => $currency]) ?>" 
                                                       class="btn btn-xs btn-primary shadow-xs">
                                                        <i class="bi bi-graph-up me-1"></i>Full Screen Chart
                                                    </a>
                                                </div>
                                            </div>

                                            <!-- 4 Quadrants Detailed Analytics Grid -->
                                            <div class="row g-3">
                                                <!-- Quadrant 1: Anatomi & Geometri Pola -->
                                                <div class="col-12 col-lg-6 col-xl-3">
                                                    <div class="card border-0 shadow-sm h-100 bg-white">
                                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                            <div class="fw-bold small text-dark d-flex align-items-center gap-1">
                                                                <i class="bi bi-bounding-box text-success"></i> Geometri & Anatomi Pola
                                                            </div>
                                                            <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">
                                                                Tinggi: <?= $r['pattern_height_pct'] ?>%
                                                            </span>
                                                        </div>
                                                        <div class="card-body p-3 small">
                                                            <p class="text-muted mb-3" style="font-size: 0.75rem; line-height: 1.4;">
                                                                <?= Html::encode($r['description']) ?>
                                                            </p>

                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-borderless mb-0 font-monospace" style="font-size: 0.75rem;">
                                                                    <tbody>
                                                                        <tr>
                                                                            <td class="text-muted ps-0">Level Breakout</td>
                                                                            <td class="text-end fw-bold text-primary pe-0"><?= $formattedBreakout ?></td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="text-muted ps-0">Batas Support Pola</td>
                                                                            <td class="text-end fw-semibold pe-0"><?= $fmtPrice($r['support_level']) ?></td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="text-muted ps-0">Tinggi Struktur Pola</td>
                                                                            <td class="text-end pe-0"><?= $fmtPrice($r['pattern_height']) ?> (+<?= $r['pattern_height_pct'] ?>%)</td>
                                                                        </tr>
                                                                        <tr class="border-top">
                                                                            <td class="text-muted ps-0 pt-2">Posisi thd Breakout</td>
                                                                            <td class="text-end pe-0 pt-2 fw-bold <?= $diff >= 0 ? 'text-success' : 'text-primary' ?>">
                                                                                <?= ($diff >= 0 ? '+' : '') . number_format($diff, 2) ?>%
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Quadrant 2: Validasi Volume & Anti Bull-Trap Radar -->
                                                <div class="col-12 col-lg-6 col-xl-3">
                                                    <div class="card border-0 shadow-sm h-100 bg-white">
                                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                            <div class="fw-bold small text-dark d-flex align-items-center gap-1">
                                                                <i class="bi bi-shield-shaded text-primary"></i> Validasi Volume & Bull Trap
                                                            </div>
                                                            <span class="badge <?= $conf >= 75 ? 'bg-success' : 'bg-primary' ?> text-white" style="font-size: 0.7rem;">
                                                                Confidence <?= $conf ?>%
                                                            </span>
                                                        </div>
                                                        <div class="card-body p-3 small">
                                                            <!-- Bull Trap Diagnosis Alert -->
                                                            <?php if ($bullTrap): ?>
                                                                <div class="alert alert-danger py-2 px-3 mb-3 small border-0 shadow-xs">
                                                                    <div class="fw-bold d-flex align-items-center gap-1 mb-1">
                                                                        <i class="bi bi-exclamation-octagon-fill text-danger fs-6"></i>
                                                                        PERINGATAN BULL TRAP (FAKEOUT)!
                                                                    </div>
                                                                    <div style="font-size: 0.72rem; line-height: 1.35;">
                                                                        Harga menembus resistensi namun volume taker buy sangat lemah (<?= $r['buy_ratio'] ?>%) dan RVOL rendah (<?= $r['rvol'] ?>x). Berisiko tinggi terjadi pembalikan turun mendadak. Jangan entry terburu-buru!
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="alert alert-success-subtle text-success py-2 px-3 mb-3 small border border-success-subtle">
                                                                    <div class="fw-bold d-flex align-items-center gap-1 mb-1">
                                                                        <i class="bi bi-check-circle-fill text-success"></i>
                                                                        Breakout Tervalidasi Sehat
                                                                    </div>
                                                                    <div style="font-size: 0.72rem; line-height: 1.35;">
                                                                        Akumulasi volume taker buy agresif (<?= $r['buy_ratio'] ?>%) dengan likuiditas Binance yang memadai. Risiko false breakout tergolong minim.
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Taker Orderflow Pressure -->
                                                            <div>
                                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                                    <span class="text-muted">Taker Buying Flow:</span>
                                                                    <strong class="text-success font-monospace"><?= $r['buy_ratio'] ?>% Taker Buy</strong>
                                                                </div>
                                                                <div class="progress" style="height: 7px;">
                                                                    <div class="progress-bar bg-success" style="width: <?= $r['buy_ratio'] ?>%;"></div>
                                                                    <div class="progress-bar bg-danger" style="width: <?= max(0, 100 - $r['buy_ratio']) ?>%;"></div>
                                                                </div>
                                                                <div class="d-flex justify-content-between text-muted mt-1 font-monospace" style="font-size: 0.68rem;">
                                                                    <span>Buyer <?= $r['buy_ratio'] ?>%</span>
                                                                    <span>RVOL: <?= $r['rvol'] ?>x</span>
                                                                    <span>Seller <?= round(100 - $r['buy_ratio'], 1) ?>%</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Quadrant 3: Proyeksi Target & Measured Move Multi-Stage -->
                                                <div class="col-12 col-lg-6 col-xl-3">
                                                    <div class="card border-0 shadow-sm h-100 bg-white">
                                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                            <div class="fw-bold small text-dark d-flex align-items-center gap-1">
                                                                <i class="bi bi-crosshair text-danger"></i> Target & Measured Move
                                                            </div>
                                                            <span class="badge bg-primary font-monospace" style="font-size: 0.7rem;">
                                                                R:R 1:<?= $rr ?>
                                                            </span>
                                                        </div>
                                                        <div class="card-body p-3 small font-monospace">
                                                            <div class="mb-2">
                                                                <div class="text-muted" style="font-size: 0.7rem;">Rekomendasi Area Entry:</div>
                                                                <div class="fw-bold text-primary">
                                                                    <?= $fmtPrice(max($breakoutPrice * 0.995, $price * 0.995)) ?> - <?= $fmtPrice(max($breakoutPrice * 1.01, $price * 1.01)) ?>
                                                                </div>
                                                            </div>

                                                            <div class="d-flex justify-content-between mb-2">
                                                                <div>
                                                                    <div class="text-muted" style="font-size: 0.7rem;">Stop Loss (-<?= $r['risk_pct'] ?>%):</div>
                                                                    <strong class="text-danger"><?= $fmtPrice($r['stop_loss']) ?></strong>
                                                                    <div class="text-muted" style="font-size: 0.65rem;">Di bawah support pola</div>
                                                                </div>
                                                                <div class="text-end">
                                                                    <div class="text-muted" style="font-size: 0.7rem;">TP1 (Measured Move):</div>
                                                                    <strong class="text-success"><?= $fmtPrice($r['target_tp1']) ?></strong>
                                                                    <div class="text-success" style="font-size: 0.65rem;">+<?= $upside ?>% Gain</div>
                                                                </div>
                                                            </div>

                                                            <div class="border-top pt-2">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <div class="text-muted" style="font-size: 0.7rem;">TP2 (1.618 Fib Ext):</div>
                                                                        <strong class="text-info"><?= $fmtPrice($r['target_tp2']) ?></strong>
                                                                    </div>
                                                                    <?php 
                                                                        $upside2 = round((($r['target_tp2'] - $price) / $price) * 100, 2);
                                                                    ?>
                                                                    <span class="badge bg-info-subtle text-info font-monospace" style="font-size: 0.7rem;">
                                                                        +<?= $upside2 ?>% Runner
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Quadrant 4: Simulasi Money Management & Strategi Realisasi Profit -->
                                                <div class="col-12 col-lg-6 col-xl-3">
                                                    <div class="card border-0 shadow-sm h-100 bg-white">
                                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                            <div class="fw-bold small text-dark d-flex align-items-center gap-1">
                                                                <i class="bi bi-wallet2 text-success"></i> Manajemen Risiko (Risk 2%)
                                                            </div>
                                                            <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">
                                                                Smart Exit
                                                            </span>
                                                        </div>
                                                        <div class="card-body p-3 small">
                                                            <?php
                                                                $riskPerUnitUsd = max(0.000001, $price - $r['stop_loss']);
                                                                if ($currency === 'IDR') {
                                                                    $riskPerUnit = $riskPerUnitUsd * $usdtIdrRate;
                                                                    // Porto Rp 10 Juta (Maks Risk 2% = Rp 200.000)
                                                                    $maxRisk1 = 200000.0;
                                                                    $units1 = $riskPerUnit > 0 ? ($maxRisk1 / $riskPerUnit) : 0;
                                                                    $posSize1 = $units1 * ($price * $usdtIdrRate);
                                                                    $rewardTp1_1 = $units1 * max(0.0, ($r['target_tp1'] - $price) * $usdtIdrRate);

                                                                    // Porto Rp 50 Juta (Maks Risk 2% = Rp 1.000.000)
                                                                    $maxRisk2 = 1000000.0;
                                                                    $units2 = $riskPerUnit > 0 ? ($maxRisk2 / $riskPerUnit) : 0;
                                                                    $posSize2 = $units2 * ($price * $usdtIdrRate);
                                                                    $rewardTp1_2 = $units2 * max(0.0, ($r['target_tp1'] - $price) * $usdtIdrRate);

                                                                    $lbl1 = 'Porto Rp 10 Jt (Risk Rp 200rb):';
                                                                    $lbl2 = 'Porto Rp 50 Jt (Risk Rp 1 Jt):';
                                                                    $pos1Str = 'Rp ' . number_format($posSize1, 0, ',', '.');
                                                                    $rew1Str = '+Rp ' . number_format($rewardTp1_1, 0, ',', '.');
                                                                    $pos2Str = 'Rp ' . number_format($posSize2, 0, ',', '.');
                                                                    $rew2Str = '+Rp ' . number_format($rewardTp1_2, 0, ',', '.');
                                                                } else {
                                                                    // Porto $1,000 (Max Risk 2% = $20)
                                                                    $maxRisk1 = 20.0;
                                                                    $units1 = $riskPerUnitUsd > 0 ? ($maxRisk1 / $riskPerUnitUsd) : 0;
                                                                    $posSize1 = $units1 * $price;
                                                                    $rewardTp1_1 = $units1 * max(0.0, $r['target_tp1'] - $price);

                                                                    // Porto $10,000 (Max Risk 2% = $200)
                                                                    $maxRisk2 = 200.0;
                                                                    $units2 = $riskPerUnitUsd > 0 ? ($maxRisk2 / $riskPerUnitUsd) : 0;
                                                                    $posSize2 = $units2 * $price;
                                                                    $rewardTp1_2 = $units2 * max(0.0, $r['target_tp1'] - $price);

                                                                    $lbl1 = 'Porto $1k (Risk $20):';
                                                                    $lbl2 = 'Porto $10k (Risk $200):';
                                                                    $pos1Str = '$' . number_format($posSize1, 1);
                                                                    $rew1Str = '+$' . number_format($rewardTp1_1, 1);
                                                                    $pos2Str = '$' . number_format($posSize2, 1);
                                                                    $rew2Str = '+$' . number_format($rewardTp1_2, 1);
                                                                }
                                                            ?>
                                                            <div class="bg-light p-2 rounded small font-monospace mb-2" style="font-size: 0.72rem;">
                                                                <div class="d-flex justify-content-between mb-1">
                                                                    <span><?= $lbl1 ?></span>
                                                                    <strong class="text-dark"><?= $pos1Str ?></strong>
                                                                </div>
                                                                <div class="d-flex justify-content-between text-success">
                                                                    <span>Profit TP1:</span>
                                                                    <strong><?= $rew1Str ?></strong>
                                                                </div>
                                                                <hr class="my-1">
                                                                <div class="d-flex justify-content-between mb-1">
                                                                    <span><?= $lbl2 ?></span>
                                                                    <strong class="text-dark"><?= $pos2Str ?></strong>
                                                                </div>
                                                                <div class="d-flex justify-content-between text-success">
                                                                    <span>Profit TP1:</span>
                                                                    <strong><?= $rew2Str ?></strong>
                                                                </div>
                                                            </div>

                                                            <div class="border-top pt-2" style="font-size: 0.7rem; line-height: 1.35;">
                                                                <div class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-success me-1"></i>Eksekusi Spot Murni (1x):</div>
                                                                <div class="text-muted">1. Ambil 50% profit di TP1.</div>
                                                                <div class="text-muted">2. Pindahkan SL ke BEP (Risk-Free Trade).</div>
                                                                <div class="text-muted">3. Biarkan 50% sisa posisi mengejar TP2.</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Quality Criteria Checklist -->
                                            <div class="mt-3 bg-white p-3 rounded shadow-sm border">
                                                <div class="fw-bold small text-dark mb-2 d-flex align-items-center gap-1">
                                                    <i class="bi bi-check2-square text-success fs-6"></i> Kriteria Kualitas Pola & Eksekusi:
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-12 col-md-6 col-lg-3">
                                                        <div class="d-flex align-items-center gap-2 p-2 rounded bg-light border-start border-success border-3">
                                                            <i class="bi bi-check-circle-fill text-success"></i>
                                                            <span class="small text-dark fw-medium">Struktur Pola: <?= Html::encode($r['pattern_name']) ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-3">
                                                        <div class="d-flex align-items-center gap-2 p-2 rounded bg-light border-start <?= $diff >= 0 ? 'border-success' : 'border-primary' ?> border-3">
                                                            <i class="bi <?= $diff >= 0 ? 'bi-check-circle-fill text-success' : 'bi-arrow-right-circle-fill text-primary' ?>"></i>
                                                            <span class="small text-dark fw-medium">Jarak Breakout: <?= ($diff >= 0 ? '+' : '') . number_format($diff, 2) ?>%</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-3">
                                                        <div class="d-flex align-items-center gap-2 p-2 rounded bg-light border-start <?= !$bullTrap ? 'border-success' : 'border-danger' ?> border-3">
                                                            <i class="bi <?= !$bullTrap ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger' ?>"></i>
                                                            <span class="small text-dark fw-medium">Anti Fakeout: <?= !$bullTrap ? 'Aman (Tervalidasi)' : 'Waspada Trap' ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-3">
                                                        <div class="d-flex align-items-center gap-2 p-2 rounded bg-light border-start <?= $rr >= 1.5 ? 'border-success' : 'border-secondary' ?> border-3">
                                                            <i class="bi bi-check-circle-fill <?= $rr >= 1.5 ? 'text-success' : 'text-secondary' ?>"></i>
                                                            <span class="small text-dark fw-medium">Risk-to-Reward: 1 : <?= $rr ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.crypto-icon-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}
.live-pulse {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #ffc107;
    box-shadow: 0 0 0 rgba(255, 193, 7, 0.7);
    animation: pulse 1.8s infinite;
}
@keyframes pulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 7px rgba(255, 193, 7, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
}
.score-circle-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.68rem;
    font-weight: 700;
}
.hover-primary:hover {
    color: #0d6efd !important;
}
.btn-xs {
    padding: 0.2rem 0.45rem;
    font-size: 0.72rem;
    border-radius: 0.25rem;
}
.btn-detail-toggle[aria-expanded="true"] .bi-chevron-down {
    transform: rotate(180deg);
}
.btn-detail-toggle .bi-chevron-down {
    transition: transform 0.2s ease-in-out;
}
.shadow-xs {
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
}
.shadow-inner {
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.04);
}
</style>
