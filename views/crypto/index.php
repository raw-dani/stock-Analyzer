<?php

/** @var yii\web\View $this */
/** @var string $timeframe */
/** @var string $signalFilter */
/** @var string $search */
/** @var string $sort */
/** @var array $rows */
/** @var array $stats */
/** @var string $currency */
/** @var float $usdtIdrRate */

use app\models\CryptoAnalysis;
use app\models\CryptoPrice;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$currency = $currency ?? 'IDR';
$usdtIdrRate = $usdtIdrRate ?? 17900.0;
$tfLabels = CryptoPrice::SUPPORTED_TIMEFRAMES;
?>

<div class="crypto-index pb-5">
    <!-- Header Section with Live Status -->
    <div class="card border-0 shadow-sm mb-4 app-hero-header">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                        <div class="app-hero-icon text-warning bg-warning bg-opacity-10">
                            <i class="bi bi-currency-bitcoin"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h1 class="app-hero-title mb-0">Crypto Volume Analyzer</h1>
                                <span class="badge bg-dark text-warning border border-warning px-2 py-1 d-inline-flex align-items-center gap-1 rounded-pill">
                                    <span class="live-pulse"></span> MARKET 24/7 LIVE
                                </span>
                                <span class="badge bg-light text-secondary border px-2 py-1 rounded-pill">Binance Spot</span>
                            </div>
                            <p class="app-hero-desc mb-0 mt-1">
                                Deteksi <strong>Real Buying Pressure</strong> koin crypto berdasarkan data asli <em>Taker Buy Volume</em> Binance (aggressor orderflow).
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Currency & Timeframe Toolbar -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Currency Switcher -->
                    <div class="btn-group btn-group-sm shadow-sm" role="group" title="Pilih Mata Uang Denominasi">
                        <a href="<?= Url::to(['/crypto/index', 'timeframe' => $timeframe, 'signal' => $signalFilter, 'search' => $search, 'sort' => $sort, 'currency' => 'IDR']) ?>"
                           class="btn <?= $currency === 'IDR' ? 'btn-success active fw-bold' : 'btn-outline-secondary' ?>">
                            🇮🇩 IDR (Rp)
                        </a>
                        <a href="<?= Url::to(['/crypto/index', 'timeframe' => $timeframe, 'signal' => $signalFilter, 'search' => $search, 'sort' => $sort, 'currency' => 'USDT']) ?>"
                           class="btn <?= $currency === 'USDT' ? 'btn-primary active fw-bold' : 'btn-outline-secondary' ?>">
                            💵 USDT ($)
                        </a>
                    </div>

                    <!-- Live Kurs Badge -->
                    <span class="badge bg-light text-dark border shadow-xs d-none d-sm-inline-flex align-items-center gap-1 font-monospace rounded-pill" style="font-size: 0.72rem;" title="Kurs Live USDT/IDR Binance Spot">
                        <span class="live-pulse" style="width: 6px; height: 6px;"></span>
                        1 USDT = Rp <?= number_format($usdtIdrRate, 0, ',', '.') ?>
                    </span>

                    <div class="btn-group-tf btn-group btn-group-sm p-1 bg-light rounded-pill border shadow-sm">
                        <?php foreach ($tfLabels as $tfKey => $tfName): ?>
                            <a href="<?= Url::to(['/crypto/index', 'timeframe' => $tfKey, 'signal' => $signalFilter, 'search' => $search, 'sort' => $sort, 'currency' => $currency]) ?>"
                               class="btn-tf btn btn-sm rounded-pill px-3 <?= $timeframe === $tfKey ? 'active' : '' ?>">
                                <?= strtoupper($tfKey) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-success shadow-sm d-inline-flex align-items-center gap-1 rounded-pill" id="btn-sync-all" onclick="syncAllCrypto()">
                        <i class="bi bi-arrow-repeat" id="icon-sync-spin"></i>
                        <span id="text-sync-btn">Refresh Data</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?= $this->render('//layouts/_crypto_sync_status_badge', [
        'fromCache' => $fromCache ?? false,
        'timeframe' => $timeframe,
    ]) ?>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="kpi-card-unified card border-0 shadow-sm h-100 kpi-accent-indigo">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="kpi-card-label">Total Dipantau</div>
                        <div class="kpi-card-value text-indigo-700"><?= $stats['filtered'] ?> <span class="fs-6 fw-normal text-muted">/ <?= $stats['total'] ?> Koin</span></div>
                    </div>
                    <div class="kpi-card-sub text-muted mt-2">Top likuiditas USDT</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="kpi-card-unified card border-0 shadow-sm h-100 kpi-accent-emerald">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="kpi-card-label">Akumulasi Kuat</div>
                        <div class="kpi-card-value text-emerald-700"><?= $stats['strongBuy'] ?> <span class="fs-6 fw-normal text-muted">Koin</span></div>
                    </div>
                    <div class="kpi-card-sub mt-2 d-flex flex-wrap align-items-center gap-1">
                        <span class="text-emerald-600"><span class="fw-bold"><?= $stats['heavyAccum'] ?></span> Buy &ge;65%</span>
                        <?php if (($stats['shakeoutCount'] ?? 0) > 0): ?>
                            <a href="<?= Url::to(['/crypto/index', 'timeframe' => $timeframe, 'signal' => 'shakeout_low', 'currency' => $currency]) ?>" class="badge bg-warning text-dark text-decoration-none border border-warning" title="Lihat koin yang mengalami Shakeout Low / Liquidity Sweep">
                                ⚡ <?= $stats['shakeoutCount'] ?> Shakeout
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="kpi-card-unified card border-0 shadow-sm h-100 kpi-accent-cyan">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="kpi-card-label">Rata-rata Buy Pressure</div>
                        <div class="kpi-card-value <?= $stats['avgBuyRatio'] >= 50 ? 'text-cyan-700' : 'text-rose-700' ?>">
                            <?= number_format($stats['avgBuyRatio'], 1) ?>%
                        </div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: <?= $stats['avgBuyRatio'] ?>%"></div>
                            <div class="progress-bar bg-danger" style="width: <?= 100 - $stats['avgBuyRatio'] ?>%"></div>
                        </div>
                    </div>
                    <div class="kpi-card-sub text-muted mt-2">
                        Tekanan beli agregat
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="kpi-card-unified card border-0 shadow-sm h-100 kpi-accent-amber">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="kpi-card-label">Top Mover 24H</div>
                        <?php if ($stats['topGainer']): ?>
                            <div class="fw-bold text-success mb-0 d-flex align-items-center justify-content-between">
                                <span><?= Html::encode($stats['topGainer']['symbol']) ?></span>
                                <span>+<?= number_format($stats['topGainer']['change'], 2) ?>%</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($stats['topLoser']): ?>
                            <div class="small text-danger d-flex align-items-center justify-content-between mt-1">
                                <span><?= Html::encode($stats['topLoser']['symbol']) ?></span>
                                <span><?= number_format($stats['topLoser']['change'], 2) ?>%</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="kpi-card-sub text-muted mt-2">
                        Pergerakan harga 24 jam
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="card border-0 shadow-sm mb-4 filter-card-unified">
        <div class="card-body p-3 filter-card-body">
            <form method="get" action="<?= Url::to(['/crypto/index']) ?>" class="row g-2 align-items-center">
                <input type="hidden" name="timeframe" value="<?= Html::encode($timeframe) ?>">

                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0" 
                               placeholder="Cari simbol atau nama koin (mis. SOL, BTC)..." 
                               value="<?= Html::encode($search) ?>">
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <select name="signal" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Sinyal</option>
                        <option value="shakeout_low" <?= $signalFilter === 'shakeout_low' ? 'selected' : '' ?>>⚡ Shakeout Low (Liquidity Sweep)</option>
                        <option value="<?= CryptoAnalysis::SIGNAL_STRONG_BUY ?>" <?= $signalFilter === CryptoAnalysis::SIGNAL_STRONG_BUY ? 'selected' : '' ?>>Strong Buy (Akumulasi Tinggi)</option>
                        <option value="<?= CryptoAnalysis::SIGNAL_BUY ?>" <?= $signalFilter === CryptoAnalysis::SIGNAL_BUY ? 'selected' : '' ?>>Buy (Bullish)</option>
                        <option value="<?= CryptoAnalysis::SIGNAL_NEUTRAL ?>" <?= $signalFilter === CryptoAnalysis::SIGNAL_NEUTRAL ? 'selected' : '' ?>>Neutral</option>
                        <option value="<?= CryptoAnalysis::SIGNAL_SELL ?>" <?= $signalFilter === CryptoAnalysis::SIGNAL_SELL ? 'selected' : '' ?>>Sell (Distribusi / Bearish)</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="score_desc" <?= $sort === 'score_desc' ? 'selected' : '' ?>>Urutkan: Skor Tertinggi</option>
                        <option value="buy_ratio_desc" <?= $sort === 'buy_ratio_desc' ? 'selected' : '' ?>>Urutkan: Buy Pressure Tertinggi</option>
                        <option value="rvol_desc" <?= $sort === 'rvol_desc' ? 'selected' : '' ?>>Urutkan: RVOL (Spike) Tertinggi</option>
                        <option value="change_desc" <?= $sort === 'change_desc' ? 'selected' : '' ?>>Urutkan: Kenaikan 24h Tertinggi</option>
                        <option value="change_asc" <?= $sort === 'change_asc' ? 'selected' : '' ?>>Urutkan: Penurunan 24h Terbanyak</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Urutkan: Nama Koin (A-Z)</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100 shadow-sm">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if ($search !== '' || $signalFilter !== '' || $sort !== 'score_desc'): ?>
                        <a href="<?= Url::to(['/crypto/index', 'timeframe' => $timeframe]) ?>" class="btn btn-sm btn-light border" title="Reset Filter">
                            <i class="bi bi-x"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Scanner Table Card -->
    <div class="table-card-unified">
        <div class="table-responsive">
            <table class="table-unified">
                <thead class="table-light">
                    <tr>
                        <th style="width: 220px;">Asset / Koin</th>
                        <th>Harga Terkini</th>
                        <th>24h Change</th>
                        <th style="min-width: 170px;">
                            Buy Pressure Ratio (Taker)
                            <i class="bi bi-info-circle ms-1 text-muted" title="Rasio volume pembeli agresif (hijau) vs penjual agresif (merah) dari data Taker Buy Binance"></i>
                        </th>
                        <th>RVOL</th>
                        <th>RSI (14)</th>
                        <th>Trend MA</th>
                        <th>Score & Sinyal</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-search fs-1 d-block mb-2"></i>
                                Tidak ada koin yang sesuai dengan filter pencarian.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): 
                            $coin = $row['asset'];
                            $change = $row['change24h'];
                            $isUp = $change >= 0;
                            $buyRatio = $row['buyRatio'];
                            $sellRatio = $row['sellRatio'];
                            $rvol = $row['rvol'];
                            $rsi = $row['rsi'];
                            $score = $row['score'];
                            $signal = $row['signal'];

                            // Formatting harga presisi
                            $price = $row['currentPrice'];
                            if ($currency === 'IDR') {
                                $valIdr = $price * $usdtIdrRate;
                                if ($valIdr >= 1000000) {
                                    $formattedPrice = 'Rp ' . number_format($valIdr, 0, ',', '.');
                                } elseif ($valIdr >= 1) {
                                    $formattedPrice = 'Rp ' . number_format($valIdr, 2, ',', '.');
                                } elseif ($valIdr > 0) {
                                    $formattedPrice = 'Rp ' . rtrim(rtrim(number_format($valIdr, 4, ',', '.'), '0'), ',');
                                } else {
                                    $formattedPrice = '-';
                                }
                                $dualPrice = $price > 0 ? ('$' . number_format($price, $price >= 1 ? 2 : 6)) : '';
                            } else {
                                if ($price >= 1000) {
                                    $formattedPrice = '$' . number_format($price, 2);
                                } elseif ($price >= 1) {
                                    $formattedPrice = '$' . number_format($price, 4);
                                } elseif ($price > 0) {
                                    $formattedPrice = '$' . number_format($price, 6);
                                } else {
                                    $formattedPrice = '-';
                                }
                                $dualPrice = $price > 0 ? ('Rp ' . number_format($price * $usdtIdrRate, 0, ',', '.')) : '';
                            }

                            // Sinyal badge
                            $badgeClass = match ($signal) {
                                CryptoAnalysis::SIGNAL_STRONG_BUY => 'bg-success text-white',
                                CryptoAnalysis::SIGNAL_BUY => 'bg-primary text-white',
                                CryptoAnalysis::SIGNAL_NEUTRAL => 'bg-secondary text-white',
                                CryptoAnalysis::SIGNAL_SELL => 'bg-danger text-white',
                                default => 'bg-light text-dark',
                            };
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= Html::encode($coin->getIconUrl()) ?>" 
                                             alt="<?= Html::encode($coin->symbol) ?>" 
                                             class="rounded-circle border"
                                             style="width: 32px; height: 32px; object-fit: cover;"
                                             onerror="this.src='https://assets.coincap.io/assets/icons/btc@2x.png';">
                                        <div>
                                            <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                                <a href="<?= Url::to(['/crypto/view', 'symbol' => $coin->symbol, 'timeframe' => $timeframe, 'currency' => $currency]) ?>" class="text-dark text-decoration-none hover-primary">
                                                    <?= Html::encode($coin->symbol) ?>
                                                </a>
                                            </div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">
                                                <?= Html::encode($coin->name) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="fw-bold font-monospace text-dark"><?= $formattedPrice ?></div>
                                    <div class="text-muted font-monospace" style="font-size: 0.68rem;"><?= $dualPrice ?></div>
                                </td>

                                <td>
                                    <span class="badge <?= $isUp ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> font-monospace px-2 py-1">
                                        <i class="bi bi-<?= $isUp ? 'arrow-up' : 'arrow-down' ?>"></i>
                                        <?= ($isUp ? '+' : '') . number_format($change, 2) ?>%
                                    </span>
                                </td>

                                <td>
                                    <div class="d-flex justify-content-between small mb-1 font-monospace">
                                        <span class="text-success fw-bold"><?= number_format($buyRatio, 1) ?>% Buy</span>
                                        <span class="text-danger fw-bold"><?= number_format($sellRatio, 1) ?>% Sell</span>
                                    </div>
                                    <div class="progress" style="height: 7px; border-radius: 4px; background: #e9ecef;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $buyRatio ?>%" aria-valuenow="<?= $buyRatio ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $sellRatio ?>%" aria-valuenow="<?= $sellRatio ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </td>

                                <td>
                                    <?php if ($rvol >= 2.0): ?>
                                        <span class="badge bg-danger text-white font-monospace px-2" title="Volume Spike Sangat Kuat!">
                                            <i class="bi bi-fire"></i> <?= number_format($rvol, 2) ?>x
                                        </span>
                                    <?php elseif ($rvol >= 1.3): ?>
                                        <span class="badge bg-warning text-dark font-monospace px-2" title="Volume di atas rata-rata">
                                            <i class="bi bi-lightning-charge"></i> <?= number_format($rvol, 2) ?>x
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted font-monospace small"><?= number_format($rvol, 2) ?>x</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($rsi !== null): ?>
                                        <?php if ($rsi <= 32): ?>
                                            <span class="badge bg-success-subtle text-success font-monospace px-2" title="Oversold (Potensi Rebound)">
                                                <?= number_format($rsi, 1) ?>
                                            </span>
                                        <?php elseif ($rsi >= 70): ?>
                                            <span class="badge bg-danger-subtle text-danger font-monospace px-2" title="Overbought (Rawan Koreksi)">
                                                <?= number_format($rsi, 1) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-secondary font-monospace"><?= number_format($rsi, 1) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($row['ma20'] !== null): ?>
                                        <?php $aboveMa = $row['currentPrice'] >= $row['ma20']; ?>
                                        <span class="badge <?= $aboveMa ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> small">
                                            <?= $aboveMa ? 'Above MA20' : 'Below MA20' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                    <?php if (!empty($row['isShakeoutLow'])): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-warning text-dark border border-warning" style="font-size: 0.65rem;" title="Liquidity Sweep / Bear Trap Rebound (Undercut: <?= $row['shakeoutUndercutPct'] ?>%)">
                                                ⚡ Sweep <?= $row['shakeoutUndercutPct'] ?>%
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="score-circle-sm <?= $score >= 75 ? 'border-success text-success' : ($score <= 35 ? 'border-danger text-danger' : 'border-secondary text-secondary') ?>">
                                            <?= $score ?>
                                        </div>
                                        <div>
                                            <span class="badge <?= $badgeClass ?> px-2 py-1" style="font-size: 0.7rem;">
                                                <?= Html::encode($signal) ?>
                                            </span>
                                            <?php if (!empty($row['isShakeoutLow'])): ?>
                                                <div class="text-warning fw-bold small" style="font-size: 0.65rem;">
                                                    <i class="bi bi-lightning-charge-fill"></i> Shakeout
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td class="text-end">
                                    <a href="<?= Url::to(['/crypto/view', 'symbol' => $coin->symbol, 'timeframe' => $timeframe]) ?>" 
                                       class="btn btn-sm btn-outline-primary shadow-sm d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-graph-up"></i> Chart
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
.kpi-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.08) !important;
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
</style>

<script>
function syncAllCrypto() {
    const btn = document.getElementById('btn-sync-all');
    const spin = document.getElementById('icon-sync-spin');
    const txt = document.getElementById('text-sync-btn');
    
    if (btn) btn.disabled = true;
    if (spin) spin.classList.add('spin-animation');
    if (txt) txt.innerText = 'Menyinkronkan...';
    
    fetch('<?= Url::to(['/crypto/sync-all', 'timeframe' => $timeframe]) ?>', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            if (txt) txt.innerText = 'Selesai!';
            setTimeout(() => {
                window.location.reload();
            }, 800);
        })
        .catch(() => {
            window.location.reload();
        });
}
</script>
<style>
.spin-animation {
    animation: spin 1s infinite linear;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
