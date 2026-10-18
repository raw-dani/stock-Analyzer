<?php

/** @var yii\web\View $this */
/** @var string $timeframe */
/** @var string $strategy */
/** @var int $minScore */
/** @var float $minRr */
/** @var string $search */
/** @var string $sort */
/** @var array $results */
/** @var array $stats */
/** @var array $presets */
/** @var array $timeframes */
/** @var string $currency */
/** @var float $usdtIdrRate */

use app\services\crypto\CryptoConfluenceService;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$currency = $currency ?? 'IDR';
$usdtIdrRate = $usdtIdrRate ?? 17900.0;

$this->title = 'Crypto Confluence Strategy Scanner (MA, RSI, MACD & Fibonacci)';
$this->params['breadcrumbs'][] = ['label' => 'Crypto Market', 'url' => ['/crypto/index']];
$this->params['breadcrumbs'][] = 'Confluence Strategy';
?>

<div class="crypto-confluence pb-5">
    <!-- Header Section -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <span class="crypto-icon-badge bg-primary text-white px-2 py-1 rounded-3 shadow-sm">
                        <i class="bi bi-bullseye fs-5"></i>
                    </span>
                    Confluence Strategy Scanner
                </h1>
                <span class="badge bg-dark text-warning border border-warning px-2 py-1 d-inline-flex align-items-center gap-1">
                    <span class="live-pulse"></span>
                    CRYPTO 24/7
                </span>
                <span class="badge bg-primary-subtle text-primary px-2 py-1 fw-bold">PRO Confluence</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 d-inline-flex align-items-center gap-1">
                    <i class="bi bi-shield-check"></i> Spot Trading (Tanpa Leverage)
                </span>
            </div>
            <p class="text-muted small mb-0">
                Penyelarasan multi-indikator: <strong>Moving Averages (EMA/SMA)</strong>, <strong>RSI 14</strong>, <strong>MACD Momentum</strong>, <strong>Fibonacci Golden Pocket (61.8%)</strong>, & <strong>Taker Buy Pressure</strong>.
            </p>
        </div>

        <!-- Currency & Timeframe Toolbar -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Currency Switcher (IDR vs USDT) -->
            <div class="btn-group shadow-sm" role="group" title="Pilih Mata Uang Denominasi">
                <a href="<?= Url::to(['/crypto/confluence', 'timeframe' => $timeframe, 'strategy' => $strategy, 'minScore' => $minScore, 'minRr' => $minRr, 'search' => $search, 'sort' => $sort, 'currency' => 'IDR']) ?>"
                   class="btn btn-sm <?= $currency === 'IDR' ? 'btn-success active fw-bold' : 'btn-outline-secondary' ?>">
                    🇮🇩 IDR (Rp)
                </a>
                <a href="<?= Url::to(['/crypto/confluence', 'timeframe' => $timeframe, 'strategy' => $strategy, 'minScore' => $minScore, 'minRr' => $minRr, 'search' => $search, 'sort' => $sort, 'currency' => 'USDT']) ?>"
                   class="btn btn-sm <?= $currency === 'USDT' ? 'btn-primary active fw-bold' : 'btn-outline-secondary' ?>">
                    💵 USDT ($)
                </a>
            </div>

            <!-- Live USDT/IDR Rate Badge -->
            <span class="badge bg-light text-dark border shadow-xs d-none d-sm-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.72rem;" title="Kurs Live USDT/IDR Binance Spot">
                <span class="live-pulse" style="width: 6px; height: 6px;"></span>
                1 USDT = Rp <?= number_format($usdtIdrRate, 0, ',', '.') ?>
            </span>

            <span class="small fw-bold text-muted text-uppercase tracking-wider">TF:</span>
            <div class="btn-group shadow-sm" role="group">
                <?php foreach ($timeframes as $tfKey => $tfName): ?>
                    <a href="<?= Url::to(['/crypto/confluence', 'timeframe' => $tfKey, 'strategy' => $strategy, 'minScore' => $minScore, 'minRr' => $minRr, 'search' => $search, 'sort' => $sort, 'currency' => $currency]) ?>"
                       class="btn btn-sm <?= $timeframe === $tfKey ? 'btn-primary active fw-bold' : 'btn-outline-secondary' ?>">
                        <?= strtoupper($tfKey) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <a href="<?= Url::to(['/crypto/index', 'timeframe' => $timeframe, 'currency' => $currency]) ?>" class="btn btn-sm btn-outline-secondary shadow-sm">
                <i class="bi bi-arrow-left"></i> Volume Hub
            </a>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm h-100 kpi-card bg-body-tertiary">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase mb-1">Total Sinyal</div>
                    <div class="h3 mb-0 fw-bold text-dark"><?= $stats['total'] ?></div>
                    <div class="small text-muted mt-1">Koin tervalidasi</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm h-100 kpi-card bg-body-tertiary">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase mb-1">Strong Buy</div>
                    <div class="h3 mb-0 fw-bold text-success"><?= $stats['strongBuyCount'] ?></div>
                    <div class="small text-muted mt-1">Skor Confluence &ge; 78</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm h-100 kpi-card bg-body-tertiary">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase mb-1">Buy Setup</div>
                    <div class="h3 mb-0 fw-bold text-primary"><?= $stats['buyCount'] ?></div>
                    <div class="small text-muted mt-1">Skor Confluence &ge; 65</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm h-100 kpi-card bg-body-tertiary">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase mb-1">Golden Pocket</div>
                    <div class="h3 mb-0 fw-bold text-warning"><?= $stats['goldenPocketCount'] ?></div>
                    <div class="small text-muted mt-1">Fib 50% - 61.8%</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm h-100 kpi-card bg-body-tertiary">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase mb-1">MACD Cross</div>
                    <div class="h3 mb-0 fw-bold text-info"><?= $stats['macdCrossCount'] ?></div>
                    <div class="small text-muted mt-1">Golden Cross baru</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm h-100 kpi-card bg-body-tertiary">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase mb-1">RSI Oversold</div>
                    <div class="h3 mb-0 fw-bold text-danger"><?= $stats['oversoldCount'] ?></div>
                    <div class="small text-muted mt-1">RSI &le; 35 (Reversal)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Strategy Toolbar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="get" action="<?= Url::to(['/crypto/confluence']) ?>" class="row g-2 align-items-center">
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
                    <select name="strategy" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($presets as $pKey => $pLabel): ?>
                            <option value="<?= $pKey ?>" <?= $strategy === $pKey ? 'selected' : '' ?>><?= Html::encode($pLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-3 col-md-2">
                    <select name="minScore" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0" <?= $minScore === 0 ? 'selected' : '' ?>>Semua Skor</option>
                        <option value="60" <?= $minScore === 60 ? 'selected' : '' ?>>Skor &ge; 60</option>
                        <option value="70" <?= $minScore === 70 ? 'selected' : '' ?>>Skor &ge; 70</option>
                        <option value="80" <?= $minScore === 80 ? 'selected' : '' ?>>Skor &ge; 80 (Tinggi)</option>
                    </select>
                </div>

                <div class="col-3 col-md-2">
                    <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="score_desc" <?= $sort === 'score_desc' ? 'selected' : '' ?>>Urutkan: Skor Tertinggi</option>
                        <option value="rr_desc" <?= $sort === 'rr_desc' ? 'selected' : '' ?>>Urutkan: R:R Tertinggi</option>
                        <option value="upside_desc" <?= $sort === 'upside_desc' ? 'selected' : '' ?>>Urutkan: Upside % Tertinggi</option>
                        <option value="buy_ratio_desc" <?= $sort === 'buy_ratio_desc' ? 'selected' : '' ?>>Urutkan: Buy Pressure</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Urutkan: Nama Koin</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100 shadow-sm">
                        <i class="bi bi-funnel"></i> Terapkan
                    </button>
                    <?php if ($search !== '' || $strategy !== 'all' || $minScore > 0 || $sort !== 'score_desc'): ?>
                        <a href="<?= Url::to(['/crypto/confluence', 'timeframe' => $timeframe]) ?>" class="btn btn-sm btn-light border" title="Reset">
                            <i class="bi bi-x"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Confluence Results Table -->
    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 190px;">Asset / Koin</th>
                        <th>Harga Terkini</th>
                        <th>Confluence Skor & Aksi</th>
                        <th style="min-width: 250px;">Faktor Confluence Aktif</th>
                        <th>Indikator Utama</th>
                        <th style="min-width: 200px;">Trading Plan (Entry, SL, TP)</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-bullseye fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ada sinyal confluence yang memenuhi filter preset ini.
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
                            $score = $r['confluence_score'];
                            $action = $r['action'];
                            $rr = $r['rr_ratio'];
                            $upside = $r['potential_upside'];
                            $riskPct = $r['risk_pct'];
                            $chg24h = (float)($r['price_change_24h'] ?? 0.0);
                            $rowId = 'detail-conf-' . strtolower($coin->symbol);

                            $formattedPrice = $fmtPrice($price);

                            $actionBadge = match ($action) {
                                CryptoConfluenceService::ACTION_STRONG_BUY => 'bg-success text-white',
                                CryptoConfluenceService::ACTION_BUY => 'bg-primary text-white',
                                CryptoConfluenceService::ACTION_HOLD => 'bg-info text-dark',
                                CryptoConfluenceService::ACTION_WAIT => 'bg-secondary text-white',
                                CryptoConfluenceService::ACTION_SELL => 'bg-danger text-white',
                                default => 'bg-light text-dark',
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
                                    <div class="fw-bold font-monospace text-dark"><?= $formattedPrice ?></div>
                                    <div class="text-muted font-monospace" style="font-size: 0.7rem;">
                                        <?= $fmtDual($price) ?>
                                    </div>
                                    <div class="small <?= $chg24h >= 0 ? 'text-success' : 'text-danger' ?> font-monospace" style="font-size: 0.72rem;">
                                        <i class="bi <?= $chg24h >= 0 ? 'bi-caret-up-fill' : 'bi-caret-down-fill' ?>"></i>
                                        <?= ($chg24h >= 0 ? '+' : '') . number_format($chg24h, 2) ?>% (24h)
                                    </div>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <div class="score-circle-sm <?= $score >= 75 ? 'border-success text-success bg-success-subtle' : ($score <= 35 ? 'border-danger text-danger bg-danger-subtle' : 'border-primary text-primary bg-primary-subtle') ?>">
                                            <?= $score ?>
                                        </div>
                                        <span class="badge <?= $actionBadge ?> px-2 py-1 shadow-xs" style="font-size: 0.72rem;">
                                            <?= Html::encode($action) ?>
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 4px; width: 110px;">
                                        <div class="progress-bar <?= $score >= 75 ? 'bg-success' : ($score <= 35 ? 'bg-danger' : 'bg-primary') ?>" 
                                             role="progressbar" 
                                             style="width: <?= $score ?>%;"></div>
                                    </div>
                                </td>

                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if (!empty($r['confluences'])): ?>
                                            <?php foreach (array_slice($r['confluences'], 0, 3) as $conf): ?>
                                                <span class="badge bg-light text-dark border small fw-normal py-1">
                                                    <i class="bi bi-check-circle-fill text-success me-1"></i><?= Html::encode($conf) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($r['confluences']) > 3): ?>
                                                <span class="badge bg-secondary-subtle text-secondary small py-1">
                                                    +<?= count($r['confluences']) - 3 ?> faktor lagi
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">Konsolidasi netral</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="small">
                                        <div>
                                            RSI: <strong class="<?= $r['rsi'] <= 35 ? 'text-success' : ($r['rsi'] >= 70 ? 'text-danger' : 'text-dark') ?>"><?= $r['rsi'] ?></strong>
                                            <?php if ($r['rsi'] <= 35): ?><span class="badge bg-success-subtle text-success p-1">Oversold</span><?php endif; ?>
                                        </div>
                                        <div>
                                            MACD: 
                                            <?php if ($r['macd_cross'] === 'BULLISH_CROSS'): ?>
                                                <span class="badge bg-info text-white p-1">Golden Cross</span>
                                            <?php else: ?>
                                                <span class="text-muted"><?= $r['macd_hist'] >= 0 ? '+' : '' ?><?= $r['macd_hist'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            Taker Buy: <strong class="text-success"><?= $r['buy_ratio'] ?>%</strong> &bull; RVOL: <?= $r['rvol'] ?>x
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="small font-monospace">
                                        <div><span class="text-muted">SL:</span> <strong class="text-danger"><?= $fmtPrice($r['stop_loss']) ?></strong> (-<?= $riskPct ?>%)</div>
                                        <div><span class="text-muted">TP1:</span> <strong class="text-success"><?= $fmtPrice($r['target_tp1']) ?></strong> (+<?= $upside ?>%)</div>
                                        <div class="mt-1">
                                            <span class="badge <?= $rr >= 2.0 ? 'bg-success' : ($rr >= 1.5 ? 'bg-primary' : 'bg-secondary') ?>">
                                                R:R 1 : <?= $rr ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1">
                                        <button class="btn btn-sm btn-primary shadow-sm d-inline-flex align-items-center gap-1 btn-detail-toggle"
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

                            <!-- Expandable Analysis Detail Panel -->
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
                                                    <span class="badge <?= $actionBadge ?> px-2 py-1 fw-bold">
                                                        <?= Html::encode($action) ?> (Skor Confluence <?= $score ?>/100)
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
                                                <!-- Quadrant 1: Moving Average Matrix -->
                                                <div class="col-12 col-lg-6 col-xl-3">
                                                    <div class="card border-0 shadow-sm h-100 bg-white">
                                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                            <div class="fw-bold small text-dark d-flex align-items-center gap-1">
                                                                <i class="bi bi-diagram-3 text-primary"></i> Moving Average Matrix
                                                            </div>
                                                            <span class="badge <?= $r['ma_bullish'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>" style="font-size: 0.7rem;">
                                                                <?= $r['ma_bullish'] ? 'Bullish Trend' : 'Mixed / Consolidate' ?>
                                                            </span>
                                                        </div>
                                                        <div class="card-body p-3 small">
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-borderless mb-2 font-monospace">
                                                                    <tbody>
                                                                        <tr>
                                                                            <td class="text-muted ps-0">EMA 20</td>
                                                                            <td class="text-end fw-semibold"><?= $fmtPrice($r['ema20'] ?? 0) ?></td>
                                                                            <td class="text-end pe-0">
                                                                                <span class="badge <?= ($price >= ($r['ema20'] ?? 0)) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> p-1">
                                                                                    <?= ($price >= ($r['ema20'] ?? 0)) ? 'Above' : 'Below' ?>
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="text-muted ps-0">SMA 50</td>
                                                                            <td class="text-end fw-semibold"><?= $fmtPrice($r['sma50'] ?? 0) ?></td>
                                                                            <td class="text-end pe-0">
                                                                                <span class="badge <?= ($price >= ($r['sma50'] ?? 0)) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> p-1">
                                                                                    <?= ($price >= ($r['sma50'] ?? 0)) ? 'Above' : 'Below' ?>
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="text-muted ps-0">SMA 200</td>
                                                                            <td class="text-end fw-semibold"><?= $fmtPrice($r['sma200'] ?? 0) ?></td>
                                                                            <td class="text-end pe-0">
                                                                                <span class="badge <?= ($price >= ($r['sma200'] ?? 0)) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> p-1">
                                                                                    <?= ($price >= ($r['sma200'] ?? 0)) ? 'Bull Trend' : 'Bear Filter' ?>
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="text-muted ps-0">EMA 50</td>
                                                                            <td class="text-end fw-semibold"><?= $fmtPrice($r['ema50'] ?? 0) ?></td>
                                                                            <td class="text-end pe-0 text-muted">Mid Trend</td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>

                                                            <div class="border-top pt-2">
                                                                <div class="text-muted mb-1" style="font-size: 0.72rem;">Rentang Swing (50 Bar):</div>
                                                                <div class="d-flex justify-content-between font-monospace" style="font-size: 0.75rem;">
                                                                    <span>Low: <strong class="text-danger"><?= $fmtPrice($r['swing_low'] ?? 0) ?></strong></span>
                                                                    <span>High: <strong class="text-success"><?= $fmtPrice($r['swing_high'] ?? 0) ?></strong></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Quadrant 2: Momentum & Oscillator (RSI, MACD & Orderflow) -->
                                                <div class="col-12 col-lg-6 col-xl-3">
                                                    <div class="card border-0 shadow-sm h-100 bg-white">
                                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                            <div class="fw-bold small text-dark d-flex align-items-center gap-1">
                                                                <i class="bi bi-activity text-info"></i> Momentum & Orderflow
                                                            </div>
                                                            <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">
                                                                RSI & MACD
                                                            </span>
                                                        </div>
                                                        <div class="card-body p-3 small">
                                                            <!-- RSI 14 Gauge -->
                                                            <div class="mb-3">
                                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                                    <span class="text-muted">RSI (14):</span>
                                                                    <strong class="font-monospace <?= $r['rsi'] <= 35 ? 'text-success' : ($r['rsi'] >= 70 ? 'text-danger' : 'text-primary') ?>">
                                                                        <?= $r['rsi'] ?> 
                                                                        <span class="badge <?= $r['rsi'] <= 35 ? 'bg-success' : ($r['rsi'] >= 70 ? 'bg-danger' : 'bg-primary') ?> ms-1 p-1" style="font-size: 0.65rem;">
                                                                            <?= $r['rsi_status'] ?>
                                                                        </span>
                                                                    </strong>
                                                                </div>
                                                                <div class="progress position-relative" style="height: 7px;">
                                                                    <div class="progress-bar <?= $r['rsi'] <= 35 ? 'bg-success' : ($r['rsi'] >= 70 ? 'bg-danger' : 'bg-primary') ?>" 
                                                                         style="width: <?= min(100, max(0, $r['rsi'])) ?>%;"></div>
                                                                </div>
                                                                <div class="d-flex justify-content-between text-muted mt-1" style="font-size: 0.65rem;">
                                                                    <span>0 (Oversold &le;30)</span>
                                                                    <span>50 (Netral)</span>
                                                                    <span>100 (Overbought &ge;70)</span>
                                                                </div>
                                                            </div>

                                                            <!-- MACD Metrics -->
                                                            <div class="mb-3 border-top pt-2">
                                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                                    <span class="text-muted">MACD (12,26,9):</span>
                                                                    <?php if ($r['macd_cross'] === 'BULLISH_CROSS'): ?>
                                                                        <span class="badge bg-info text-white p-1">🚀 Bullish Golden Cross</span>
                                                                    <?php elseif ($r['macd_cross'] === 'BEARISH_CROSS'): ?>
                                                                        <span class="badge bg-danger text-white p-1">⚠️ Bearish Cross</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-light text-dark border p-1"><?= $r['macd_hist'] >= 0 ? 'Hist +' : 'Hist -' ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="d-flex justify-content-between font-monospace text-muted" style="font-size: 0.72rem;">
                                                                    <span>Line: <strong class="text-dark"><?= $r['macd'] ?></strong></span>
                                                                    <span>Sig: <strong class="text-dark"><?= $r['macd_signal'] ?></strong></span>
                                                                    <span>Hist: <strong class="<?= $r['macd_hist'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($r['macd_hist'] >= 0 ? '+' : '') . $r['macd_hist'] ?></strong></span>
                                                                </div>
                                                            </div>

                                                            <!-- Taker Volume Orderflow -->
                                                            <div class="border-top pt-2">
                                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                                    <span class="text-muted">Taker Buy Orderflow:</span>
                                                                    <strong class="text-success font-monospace"><?= $r['buy_ratio'] ?>% Buy</strong>
                                                                </div>
                                                                <div class="progress" style="height: 6px;">
                                                                    <div class="progress-bar bg-success" style="width: <?= $r['buy_ratio'] ?>%;"></div>
                                                                    <div class="progress-bar bg-danger" style="width: <?= max(0, 100 - $r['buy_ratio']) ?>%;"></div>
                                                                </div>
                                                                <div class="d-flex justify-content-between text-muted mt-1" style="font-size: 0.68rem;">
                                                                    <span class="text-success">Buyer <?= $r['buy_ratio'] ?>%</span>
                                                                    <span class="text-danger">Seller <?= round(100 - $r['buy_ratio'], 1) ?>%</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Quadrant 3: Fibonacci Retracement & Golden Pocket Grid -->
                                                <div class="col-12 col-lg-6 col-xl-3">
                                                    <div class="card border-0 shadow-sm h-100 bg-white">
                                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                            <div class="fw-bold small text-dark d-flex align-items-center gap-1">
                                                                <i class="bi bi-segmented-nav text-warning"></i> Fibonacci Retracement
                                                            </div>
                                                            <?php if (!empty($r['in_golden_pocket'])): ?>
                                                                <span class="badge bg-warning text-dark fw-bold px-2 py-1 pulse-subtle" style="font-size: 0.68rem;">
                                                                    🎯 Golden Pocket
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="card-body p-3 small">
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-borderless mb-2 font-monospace" style="font-size: 0.75rem;">
                                                                    <tbody>
                                                                        <tr class="text-muted">
                                                                            <td>0.0% (High)</td>
                                                                            <td class="text-end"><?= $fmtPrice($r['swing_high'] ?? 0) ?></td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="text-muted">23.6% Fib</td>
                                                                            <td class="text-end"><?= $fmtPrice($r['fib_levels']['level_236'] ?? 0) ?></td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="text-muted">38.2% Fib</td>
                                                                            <td class="text-end"><?= $fmtPrice($r['fib_levels']['level_382'] ?? 0) ?></td>
                                                                        </tr>
                                                                        <tr class="<?= !empty($r['in_golden_pocket']) ? 'table-warning fw-bold text-dark rounded' : '' ?>">
                                                                            <td><span class="badge bg-warning-subtle text-dark p-1">50.0% Pocket</span></td>
                                                                            <td class="text-end"><?= $fmtPrice($r['fib_levels']['level_500'] ?? 0) ?></td>
                                                                        </tr>
                                                                        <tr class="<?= !empty($r['in_golden_pocket']) ? 'table-warning fw-bold text-dark rounded' : '' ?>">
                                                                            <td><span class="badge bg-warning text-dark p-1">61.8% Golden</span></td>
                                                                            <td class="text-end"><?= $fmtPrice($r['fib_levels']['level_618'] ?? 0) ?></td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="text-muted">78.6% Fib (Cut)</td>
                                                                            <td class="text-end"><?= $fmtPrice($r['fib_levels']['level_786'] ?? 0) ?></td>
                                                                        </tr>
                                                                        <tr class="text-muted">
                                                                            <td>100.0% (Low)</td>
                                                                            <td class="text-end"><?= $fmtPrice($r['swing_low'] ?? 0) ?></td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            <?php if (!empty($r['in_golden_pocket'])): ?>
                                                                <div class="alert alert-warning py-1 px-2 mb-0 small text-center" style="font-size: 0.72rem;">
                                                                    <i class="bi bi-star-fill text-warning me-1"></i>Harga sedang berada di zona rebound terkuat (50% - 61.8%)!
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Quadrant 4: Actionable Trading Plan & Position Sizing -->
                                                <div class="col-12 col-lg-6 col-xl-3">
                                                    <div class="card border-0 shadow-sm h-100 bg-white">
                                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                            <div class="fw-bold small text-dark d-flex align-items-center gap-1">
                                                                <i class="bi bi-shield-check text-success"></i> Trading Plan & Risk Plan
                                                            </div>
                                                            <span class="badge bg-primary px-2 py-1 font-monospace" style="font-size: 0.7rem;">
                                                                R:R 1:<?= $rr ?>
                                                            </span>
                                                        </div>
                                                        <div class="card-body p-3 small font-monospace">
                                                            <div class="mb-2">
                                                                <div class="text-muted" style="font-size: 0.7rem;">Zona Entry Disarankan:</div>
                                                                <div class="fw-bold text-primary">
                                                                    <?= $fmtPrice($r['entry_range']['low']) ?> - <?= $fmtPrice($r['entry_range']['high']) ?>
                                                                </div>
                                                            </div>

                                                            <div class="d-flex justify-content-between mb-2">
                                                                <div>
                                                                    <div class="text-muted" style="font-size: 0.7rem;">Stop Loss (-<?= $riskPct ?>%):</div>
                                                                    <strong class="text-danger"><?= $fmtPrice($r['stop_loss']) ?></strong>
                                                                </div>
                                                                <div class="text-end">
                                                                    <div class="text-muted" style="font-size: 0.7rem;">Target TP1 (+<?= $upside ?>%):</div>
                                                                    <strong class="text-success"><?= $fmtPrice($r['target_tp1']) ?></strong>
                                                                </div>
                                                            </div>

                                                            <div class="mb-3">
                                                                <div class="text-muted" style="font-size: 0.7rem;">Target Runner TP2:</div>
                                                                <strong class="text-info"><?= $fmtPrice($r['target_tp2']) ?></strong>
                                                            </div>

                                                            <!-- Position Sizing Simulation Table (2% Risk Rule) -->
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
                                                            <div class="border-top pt-2">
                                                                <div class="text-muted mb-1 fw-bold d-flex justify-content-between align-items-center" style="font-size: 0.68rem; font-family: sans-serif;">
                                                                    <span><i class="bi bi-calculator me-1"></i>Simulasi Spot (Risk 2%):</span>
                                                                    <span class="badge bg-success-subtle text-success border border-success-subtle p-1" style="font-size: 0.62rem;">Spot 1x Non-Leverage</span>
                                                                </div>
                                                                <div class="bg-light p-2 rounded small" style="font-size: 0.72rem;">
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
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Full Factors Checklist -->
                                            <?php if (!empty($r['confluences'])): ?>
                                                <div class="mt-3 bg-white p-3 rounded shadow-sm border">
                                                    <div class="fw-bold small text-dark mb-2 d-flex align-items-center gap-1">
                                                        <i class="bi bi-check2-all text-success fs-6"></i> Seluruh Faktor Confluence Terverifikasi (Skor <?= $score ?>/100):
                                                    </div>
                                                    <div class="row g-2">
                                                        <?php foreach ($r['confluences'] as $cItem): ?>
                                                            <div class="col-12 col-md-6 col-lg-4">
                                                                <div class="d-flex align-items-center gap-2 p-2 rounded bg-light border-start border-success border-3">
                                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                                    <span class="small text-dark fw-medium"><?= Html::encode($cItem) ?></span>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
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
.pulse-subtle {
    animation: pulse-border 2s infinite;
}
@keyframes pulse-border {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}
.score-circle-sm {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
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
