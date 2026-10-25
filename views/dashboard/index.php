<?php

/** @var yii\web\View $this */
/** @var string|null $lastWeek */
/** @var int $marketVolume */
/** @var float $buyPressure */
/** @var app\models\WeeklyAnalysis[] $topBuy */
/** @var app\models\WeeklyAnalysis[] $topSell */
/** @var app\models\WeeklyAnalysis[] $topRanked */
/** @var app\models\Signal[] $recentBuySignals */
/** @var array $signalCount */
/** @var array $dailyStats */
/** @var array $portfolioData */
/** @var array $confluencePicks */
/** @var int $totalTrackedStocks */

use app\widgets\SignalBadge;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Market & Strategy Intelligence Dashboard';
$fmt = Yii::$app->formatter;

$lastDailyDate = $dailyStats['date'] ?? null;
$gainersCount = $dailyStats['gainersCount'] ?? 0;
$losersCount = $dailyStats['losersCount'] ?? 0;
$totalDaily = $dailyStats['totalStocks'] ?? 0;
$dailyAdvRatio = $totalDaily > 0 ? round(($gainersCount / $totalDaily) * 100, 1) : 50;
?>

<div class="dashboard-index">
    <!-- Header Page & Quick Actions -->
    <div class="card border-0 shadow-sm mb-4 app-hero-header">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="app-hero-icon text-primary bg-primary bg-opacity-10">
                        <?= \app\helpers\SvgHelper::icon('speedometer2') ?>
                    </div>
                    <div>
                        <h1 class="app-hero-title mb-1">
                            <?= Html::encode($this->title) ?>
                        </h1>
                        <p class="app-hero-desc mb-0">
                            Pusat navigasi dan pemantauan menyeluruh: Volume Breakout Institusi, Pola Reversal, Confluence Multi-Indikator, serta Portofolio Aktif Anda.
                        </p>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?= Url::to(['/strategy-confluence/index']) ?>" class="btn btn-outline-primary btn-sm px-3 shadow-sm fw-semibold rounded-pill">
                        <?= \app\helpers\SvgHelper::icon('bullseye', ['class' => 'me-1']) ?> Confluence PRO
                    </a>
                    <a href="<?= Url::to(['/portfolio/index']) ?>" class="btn btn-success btn-sm px-3 shadow-sm fw-bold rounded-pill">
                        <?= \app\helpers\SvgHelper::icon('briefcase-fill', ['class' => 'me-1']) ?> Portofolio
                    </a>
                    <a href="<?= Url::to(['/scanner/index']) ?>" class="btn btn-primary btn-sm px-3 shadow-sm fw-semibold rounded-pill">
                        <?= \app\helpers\SvgHelper::icon('search', ['class' => 'me-1']) ?> Volume Scanner
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Hub to All Available Features in the App -->
    <div class="row g-2 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= Url::to(['/portfolio/index']) ?>" class="card text-decoration-none border-0 shadow-sm bg-white h-100 hover-card p-3 text-center rounded-3">
                <div class="text-success mb-1" style="font-size: 1.4rem;"><?= \app\helpers\SvgHelper::icon('wallet2') ?></div>
                <div class="fw-bold text-dark small">Portofolio & DCA</div>
                <div class="text-muted" style="font-size: 0.72rem;">Target Profit & Avg Down</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= Url::to(['/strategy-confluence/index']) ?>" class="card text-decoration-none border-0 shadow-sm bg-white h-100 hover-card p-3 text-center rounded-3">
                <div class="text-primary mb-1" style="font-size: 1.4rem;"><?= \app\helpers\SvgHelper::icon('diagram-3-fill') ?></div>
                <div class="fw-bold text-dark small">Confluence PRO</div>
                <div class="text-muted" style="font-size: 0.72rem;">MA + RSI + MACD + Fibo</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= Url::to(['/double-bottom/index']) ?>" class="card text-decoration-none border-0 shadow-sm bg-white h-100 hover-card p-3 text-center rounded-3">
                <div class="text-cyan mb-1" style="font-size: 1.4rem;"><?= \app\helpers\SvgHelper::icon('graph-down') ?></div>
                <div class="fw-bold text-dark small">Double Bottom</div>
                <div class="text-muted" style="font-size: 0.72rem;">W-Formation Reversal</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= Url::to(['/triple-bottom/index']) ?>" class="card text-decoration-none border-0 shadow-sm bg-white h-100 hover-card p-3 text-center rounded-3">
                <div class="text-warning mb-1" style="font-size: 1.4rem;"><?= \app\helpers\SvgHelper::icon('water') ?></div>
                <div class="fw-bold text-dark small">Triple Bottom</div>
                <div class="text-muted" style="font-size: 0.72rem;">Kuat Menahan Support</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= Url::to(['/rsi-double-bottom/index']) ?>" class="card text-decoration-none border-0 shadow-sm bg-white h-100 hover-card p-3 text-center rounded-3">
                <div class="text-danger mb-1" style="font-size: 1.4rem;"><?= \app\helpers\SvgHelper::icon('activity') ?></div>
                <div class="fw-bold text-dark small">RSI & Momentum</div>
                <div class="text-muted" style="font-size: 0.72rem;">Oversold & Divergensi</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= Url::to(['/candlestick/index']) ?>" class="card text-decoration-none border-0 shadow-sm bg-white h-100 hover-card p-3 text-center rounded-3">
                <div class="text-secondary mb-1" style="font-size: 1.4rem;"><?= \app\helpers\SvgHelper::icon('bar-chart-steps') ?></div>
                <div class="fw-bold text-dark small">Candlestick Scanner</div>
                <div class="text-muted" style="font-size: 0.72rem;">Hammer, Engulfing, Vol</div>
            </a>
        </div>
    </div>

    <!-- Personal Portfolio Banner (if Logged in) -->
    <?php if (!$portfolioData['isGuest']): ?>
    <div class="card border-0 shadow-sm bg-primary bg-gradient text-white mb-4 rounded-3">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <span class="badge bg-white text-primary mb-2 fw-semibold">Portofolio Pengguna Aktif</span>
                    <h4 class="fw-bold mb-1">
                        <?= \app\helpers\SvgHelper::icon('person-circle', ['class' => 'me-1']) ?> Halo, <?= Html::encode(Yii::$app->user->identity->username) ?>
                    </h4>
                    <p class="mb-0 text-white-50 small">
                        <?php if ($portfolioData['count'] > 0): ?>
                            Anda memiliki <strong><?= $portfolioData['count'] ?> posisi saham</strong> aktif. Pantau target keuntungan atau manfaatkan strategi Averaging Down.
                        <?php else: ?>
                            Portofolio Anda masih kosong. Daftarkan saham yang sudah Anda beli untuk memantau target profit dan kalkulasi modal otomatis!
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <?php if ($portfolioData['count'] > 0): ?>
                        <div class="text-end border-end border-white border-opacity-25 pe-3">
                            <div class="text-white-50 small">Total Nilai Saham</div>
                            <div class="fs-4 fw-bold"><?= $fmt->asCurrency($portfolioData['currentValue']) ?></div>
                        </div>
                        <div class="text-start pe-2">
                            <div class="text-white-50 small">Status Posisi</div>
                            <div class="fw-semibold small">
                                <span class="badge bg-success"><?= $portfolioData['profitCount'] ?> Profit</span>
                                <span class="badge bg-danger"><?= $portfolioData['lossCount'] ?> Minus</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <a href="<?= Url::to(['/portfolio/index']) ?>" class="btn btn-light fw-bold px-3 shadow-sm rounded-pill">
                        Kelola Portofolio &raquo;
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info border-0 shadow-sm d-flex justify-content-between align-items-center mb-4 rounded-3">
        <div>
            <?= \app\helpers\SvgHelper::icon('info-circle-fill', ['class' => 'me-2 fs-5 align-middle']) ?>
            <strong>Kelola Portofolio Pribadi Anda:</strong> Masuk atau daftar akun untuk melacak saham yang Anda miliki, kalkulasi target jual +3%, dan simulasi Averaging Down.
        </div>
        <div class="d-flex gap-2">
            <a href="<?= Url::to(['/site/login']) ?>" class="btn btn-primary btn-sm px-3 fw-bold rounded-pill">Login</a>
            <a href="<?= Url::to(['/site/register']) ?>" class="btn btn-outline-primary btn-sm px-3 fw-bold rounded-pill">Register</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPI Metric Cards: Market Sentiment & Weekly Volume -->
    <div class="row g-3 mb-4">
        <!-- Market Breadth / Advance-Decline -->
        <div class="col-md-6 col-lg-3">
            <div class="kpi-card-unified card border-0 shadow-sm h-100 kpi-accent-emerald">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="kpi-card-label mb-0">Market Breadth (Harian)</span>
                            <span class="badge bg-light text-muted border"><?= Html::encode($lastDailyDate ?? 'N/A') ?></span>
                        </div>
                        <div class="d-flex align-items-baseline gap-2 mb-2">
                            <div class="kpi-card-value text-emerald-700 mb-0"><?= $gainersCount ?> Naik</div>
                            <span class="text-muted">vs</span>
                            <div class="h5 text-danger mb-0"><?= $losersCount ?> Turun</div>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: <?= $dailyAdvRatio ?>%"></div>
                            <div class="progress-bar bg-danger" style="width: <?= 100 - $dailyAdvRatio ?>%"></div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between text-muted small mt-2" style="font-size: 0.75rem;">
                        <span><?= $dailyAdvRatio ?>% Advancing</span>
                        <span>Total <?= $totalDaily ?> Saham</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Market Volume (Mingguan) -->
        <div class="col-md-6 col-lg-3">
            <div class="kpi-card-unified card border-0 shadow-sm h-100 kpi-accent-indigo">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="kpi-card-label">Total Volume Pasar</div>
                        <div class="kpi-card-value text-indigo-700"><?= $fmt->asVolume($marketVolume) ?></div>
                    </div>
                    <div class="kpi-card-sub text-muted mt-2">
                        Minggu: <strong><?= Html::encode($lastWeek ?? 'N/A') ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Buy Pressure (Akumulasi Institusi) -->
        <div class="col-md-6 col-lg-3">
            <div class="kpi-card-unified card border-0 shadow-sm h-100 kpi-accent-cyan">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="kpi-card-label">Rasio Tekanan Beli</div>
                        <div class="kpi-card-value text-cyan-700"><?= $fmt->asRatioPercent($buyPressure) ?></div>
                        <div class="progress my-2" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: <?= round($buyPressure * 100) ?>%"></div>
                            <div class="progress-bar bg-danger" style="width: <?= round((1 - $buyPressure) * 100) ?>%"></div>
                        </div>
                    </div>
                    <div class="kpi-card-sub text-muted mt-2">
                        Institusional Akumulasi (Buy / Total)
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Sinyal Terverifikasi -->
        <div class="col-md-6 col-lg-3">
            <div class="kpi-card-unified card border-0 shadow-sm h-100 kpi-accent-amber">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <?php
                            $signalMap = [];
                            foreach ($signalCount as $row) {
                                $signalMap[$row['signal']] = (int) $row['cnt'];
                            }
                            $strongBuy = ($signalMap['STRONG_BUY'] ?? 0) + ($signalMap['BUY'] ?? 0);
                            $totalSignals = array_sum($signalMap);
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="kpi-card-label mb-0">Sinyal Buy / Strong Buy</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle"><?= $strongBuy ?> Aktif</span>
                        </div>
                        <div class="kpi-card-value text-amber-700"><?= $strongBuy ?> <span class="fs-6 fw-normal text-muted">/ <?= $totalSignals ?> sinyal</span></div>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <?php foreach ($signalCount as $row): ?>
                            <span class="badge bg-light text-dark border px-1" style="font-size: 0.7rem;">
                                <?= Html::encode($row['signal']) ?>: <strong><?= $row['cnt'] ?></strong>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Multi-Indicator Confluence Picks Section (PRO Feature Highlights) -->
    <?php if (!empty($confluencePicks)): ?>
    <div class="table-card-unified mb-4">
        <div class="table-card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <?= \app\helpers\SvgHelper::icon('stars', ['class' => 'text-warning me-2']) ?>Pilihan Saham Potensial: Confluence Strategy (PRO)
                </h5>
                <span class="text-muted small">Peringkat keselarasan teknikal (Fibonacci Golden Pocket 61.8%, RSI, Moving Average & MACD)</span>
            </div>
            <a href="<?= Url::to(['/strategy-confluence/index']) ?>" class="btn btn-outline-primary btn-sm px-3 fw-bold rounded-pill">
                Lihat Semua (Full Scan) &raquo;
            </a>
        </div>
        <div class="table-responsive">
            <table class="table-unified">
                    <thead class="table-light">
                        <tr class="small text-muted text-uppercase">
                            <th class="ps-3">Simbol</th>
                            <th>Harga Terakhir</th>
                            <th>Confluence Score</th>
                            <th>Sinyal Rekomendasi</th>
                            <th>RSI (14)</th>
                            <th>Golden Pocket (61.8%)</th>
                            <th>Rasio R:R Target</th>
                            <th class="text-end pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($confluencePicks as $pick): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark fs-6"><?= Html::encode($pick['symbol']) ?></div>
                                <div class="small text-muted" style="font-size: 0.75rem;"><?= Html::encode($pick['name'] ?? '') ?></div>
                            </td>
                            <td class="fw-bold text-dark">
                                $<?= number_format((float)$pick['current_price'], 2) ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                        <div class="progress-bar bg-success" style="width: <?= min(100, $pick['confluence_score']) ?>%"></div>
                                    </div>
                                    <span class="fw-bold text-success small"><?= $pick['confluence_score'] ?></span>
                                </div>
                            </td>
                            <td>
                                <?php
                                    $actionBadgeClass = match($pick['action']) {
                                        'STRONG_BUY' => 'bg-success',
                                        'BUY' => 'bg-primary',
                                        'WATCH' => 'bg-warning text-dark',
                                        default => 'bg-secondary',
                                    };
                                ?>
                                <span class="badge <?= $actionBadgeClass ?> px-2 py-1"><?= Html::encode($pick['action']) ?></span>
                            </td>
                            <td>
                                <span class="small fw-semibold <?= $pick['rsi'] <= 35 ? 'text-success' : ($pick['rsi'] >= 70 ? 'text-danger' : 'text-dark') ?>">
                                    <?= round($pick['rsi'], 1) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($pick['in_golden_pocket'])): ?>
                                    <span class="badge bg-warning bg-opacity-25 text-warning-emphasis border border-warning-subtle">
                                        <?= \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'me-1']) ?> Area Golden Pocket
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    1 : <?= number_format((float)($pick['rr_ratio'] ?? 2.0), 1) ?>
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="<?= Url::to(['/strategy-confluence/detail', 'symbol' => $pick['symbol']]) ?>" class="btn btn-outline-primary btn-sm px-2 py-1">
                                    <?= \app\helpers\SvgHelper::icon('graph-up', ['class' => 'me-1']) ?> Analisis
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Daily Movers: Top Gainers, Losers, and Volume Leaders -->
    <div class="row g-3 mb-4">
        <!-- Top Gainers -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-success">
                        <?= \app\helpers\SvgHelper::icon('arrow-up-right-circle-fill', ['class' => 'me-1']) ?> Top Gainers Harian
                    </h6>
                    <span class="text-muted small"><?= Html::encode($lastDailyDate ?? '') ?></span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($dailyStats['topGainers'] as $g): 
                                $gainPct = $g->open > 0 ? round(($g->close - $g->open) / $g->open * 100, 2) : 0;
                            ?>
                            <tr>
                                <td class="ps-3 py-2">
                                    <?= Html::a(Html::encode($g->stock->symbol), ['/stock/view', 'symbol' => $g->stock->symbol], ['class' => 'fw-bold text-decoration-none']) ?>
                                    <div class="text-muted small" style="font-size: 0.72rem;"><?= Html::encode($g->stock->name ?? '') ?></div>
                                </td>
                                <td class="text-end py-2">
                                    <div class="fw-semibold text-dark">$<?= number_format((float)$g->close, 2) ?></div>
                                </td>
                                <td class="text-end pe-3 py-2">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle fw-bold">
                                        +<?= number_format($gainPct, 2) ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Losers (Potensial Averaging Down & Buy on Dip) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-danger">
                        <?= \app\helpers\SvgHelper::icon('arrow-down-right-circle-fill', ['class' => 'me-1']) ?> Top Decliners (Buy on Dip)
                    </h6>
                    <span class="badge bg-light text-muted border">Peluang Rebound</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($dailyStats['topLosers'] as $l): 
                                $dropPct = $l->open > 0 ? round(($l->close - $l->open) / $l->open * 100, 2) : 0;
                            ?>
                            <tr>
                                <td class="ps-3 py-2">
                                    <?= Html::a(Html::encode($l->stock->symbol), ['/stock/view', 'symbol' => $l->stock->symbol], ['class' => 'fw-bold text-decoration-none']) ?>
                                    <div class="text-muted small" style="font-size: 0.72rem;"><?= Html::encode($l->stock->name ?? '') ?></div>
                                </td>
                                <td class="text-end py-2">
                                    <div class="fw-semibold text-dark">$<?= number_format((float)$l->close, 2) ?></div>
                                </td>
                                <td class="text-end pe-3 py-2">
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle fw-bold">
                                        <?= number_format($dropPct, 2) ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Volume Leaders -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-primary">
                        <?= \app\helpers\SvgHelper::icon('bar-chart-fill', ['class' => 'me-1']) ?> Volume Leaders
                    </h6>
                    <span class="text-muted small">Aktivitas Terbesar</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($dailyStats['topVolumes'] as $v): ?>
                            <tr>
                                <td class="ps-3 py-2">
                                    <?= Html::a(Html::encode($v->stock->symbol), ['/stock/view', 'symbol' => $v->stock->symbol], ['class' => 'fw-bold text-decoration-none']) ?>
                                    <div class="text-muted small" style="font-size: 0.72rem;"><?= Html::encode($v->stock->name ?? '') ?></div>
                                </td>
                                <td class="text-end py-2">
                                    <div class="fw-semibold text-dark">$<?= number_format((float)$v->close, 2) ?></div>
                                </td>
                                <td class="text-end pe-3 py-2">
                                    <span class="badge bg-light text-dark border">
                                        <?= $fmt->asVolume((int)$v->volume) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Institutional Volume Breakdown & Technical Sinyal -->
    <div class="row g-3">
        <!-- Top Institutional Accumulation (Buy Pressure) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="card-title fw-bold text-success mb-0">
                        <?= \app\helpers\SvgHelper::icon('shield-fill-check', ['class' => 'me-1']) ?> Akumulasi Volume Institusi
                    </h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th class="ps-3">Saham</th>
                                <th>Buy Ratio</th>
                                <th class="text-end pe-3">Sinyal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topBuy as $w): if (!empty($w->stock)): ?>
                            <tr>
                                <td class="ps-3">
                                    <?= Html::a(Html::encode($w->stock->symbol), ['/stock/view', 'symbol' => $w->stock->symbol], ['class' => 'fw-bold text-decoration-none']) ?>
                                </td>
                                <td>
                                    <span class="fw-semibold text-success"><?= $fmt->asRatioPercent($w->buy_ratio) ?></span>
                                </td>
                                <td class="text-end pe-3"><?= SignalBadge::widget(['signal' => $w->signal]) ?></td>
                            </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Ranked by Institutional Score -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="card-title fw-bold text-dark mb-0">
                        <?= \app\helpers\SvgHelper::icon('trophy-fill', ['class' => 'text-warning me-1']) ?> Top Ranked (Volume Score)
                    </h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th class="ps-3">Saham</th>
                                <th>Score (0-100)</th>
                                <th class="text-end pe-3">Sinyal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topRanked as $w): if (!empty($w->stock)): ?>
                            <tr>
                                <td class="ps-3">
                                    <?= Html::a(Html::encode($w->stock->symbol), ['/stock/view', 'symbol' => $w->stock->symbol], ['class' => 'fw-bold text-decoration-none']) ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary px-2 py-1"><?= (int) $w->score ?> pts</span>
                                </td>
                                <td class="text-end pe-3"><?= SignalBadge::widget(['signal' => $w->signal]) ?></td>
                            </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Buy Signals with Reasoning -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="card-title fw-bold text-primary mb-0">
                        <?= \app\helpers\SvgHelper::icon('bell-fill', ['class' => 'text-info me-1']) ?> Sinyal Buy Terkini
                    </h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th class="ps-3">Saham</th>
                                <th>Tanggal</th>
                                <th>Sinyal</th>
                                <th class="text-end pe-3">Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBuySignals as $s): if (!empty($s->stock)): ?>
                            <tr>
                                <td class="ps-3">
                                    <?= Html::a(Html::encode($s->stock->symbol), ['/stock/view', 'symbol' => $s->stock->symbol], ['class' => 'fw-bold text-decoration-none']) ?>
                                </td>
                                <td class="text-muted small"><?= Html::encode($s->date) ?></td>
                                <td><?= SignalBadge::widget(['signal' => $s->signal]) ?></td>
                                <td class="text-end pe-3 fw-bold text-dark"><?= (int) $s->score ?></td>
                            </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hover-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.hover-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
}
</style>
