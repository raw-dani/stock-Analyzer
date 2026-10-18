<?php

/** @var yii\web\View $this */
/** @var app\models\CryptoAsset $asset */
/** @var string $timeframe */
/** @var app\models\CryptoAnalysis|null $analysis */
/** @var array $chartData */
/** @var string $currency */
/** @var float $usdtIdrRate */

use app\models\CryptoAnalysis;
use app\models\CryptoPrice;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$currency = $currency ?? 'IDR';
$usdtIdrRate = $usdtIdrRate ?? 17900.0;

$this->title = $asset->symbol . ' (' . $asset->name . ') - Crypto Volume & Orderflow Chart';
$this->params['breadcrumbs'][] = ['label' => 'Crypto Market', 'url' => ['/crypto/index']];
$this->params['breadcrumbs'][] = $asset->symbol;

$tfLabels = CryptoPrice::SUPPORTED_TIMEFRAMES;

$price = $analysis ? (float)$analysis->current_price : 0.0;
$change = $analysis ? (float)($analysis->price_change_24h ?? 0.0) : 0.0;
$isUp = $change >= 0;
$buyRatio = $analysis ? (float)$analysis->buy_ratio : 50.0;
$sellRatio = max(0.0, round(100.0 - $buyRatio, 2));
$rvol = $analysis ? (float)($analysis->rvol ?? 1.0) : 1.0;
$rsi = $analysis && $analysis->rsi !== null ? (float)$analysis->rsi : null;
$score = $analysis ? (int)$analysis->score : 50;
$signal = $analysis ? $analysis->signal : CryptoAnalysis::SIGNAL_NEUTRAL;

// Format harga
if ($currency === 'IDR') {
    $valIdr = $price * $usdtIdrRate;
    $formattedPrice = 'Rp ' . number_format($valIdr, 0, ',', '.');
    $subPrice = '$' . number_format($price, $price >= 1 ? 2 : 6) . ' USDT';
} else {
    $formattedPrice = '$' . number_format($price, $price >= 1 ? 2 : 6);
    $subPrice = 'Rp ' . number_format($price * $usdtIdrRate, 0, ',', '.');
}

$signalClass = match ($signal) {
    CryptoAnalysis::SIGNAL_STRONG_BUY => 'bg-success text-white',
    CryptoAnalysis::SIGNAL_BUY => 'bg-primary text-white',
    CryptoAnalysis::SIGNAL_NEUTRAL => 'bg-secondary text-white',
    CryptoAnalysis::SIGNAL_SELL => 'bg-danger text-white',
    default => 'bg-light text-dark',
};
?>

<div class="crypto-view pb-5">
    <!-- Header Koin & Aksi Cepat -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= Html::encode($asset->getIconUrl()) ?>" 
                 alt="<?= Html::encode($asset->symbol) ?>" 
                 class="rounded-circle border p-1 bg-white shadow-sm"
                 style="width: 52px; height: 52px; object-fit: cover;"
                 onerror="this.src='https://assets.coincap.io/assets/icons/btc@2x.png';">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h1 class="h3 mb-0 fw-bold text-dark"><?= Html::encode($asset->symbol) ?></h1>
                    <span class="badge bg-warning text-dark border border-warning">Spot Trading (Tanpa Leverage)</span>
                    <span class="badge bg-dark text-warning d-inline-flex align-items-center gap-1">
                        <span class="live-pulse"></span> 24/7 LIVE
                    </span>
                    <span class="badge <?= $signalClass ?> px-2 py-1 fs-7">
                        <?= Html::encode($signal) ?> (Score: <?= $score ?>/100)
                    </span>
                </div>
                <p class="text-muted small mb-0 mt-1">
                    <?= Html::encode($asset->name) ?> &bull; Base Asset: <strong><?= Html::encode($asset->base_asset) ?></strong> &bull; Kurs: <strong>1 USDT = Rp <?= number_format($usdtIdrRate, 0, ',', '.') ?></strong>
                </p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="https://indodax.com/market/<?= Html::encode($asset->symbol) ?>IDR" 
               target="_blank" 
               rel="noopener" 
               class="btn btn-outline-success btn-sm shadow-sm"
               title="Trading Spot IDR di Indodax">
                <i class="bi bi-box-arrow-up-right me-1"></i>Indodax (IDR)
            </a>
            <a href="https://www.tokocrypto.com/trade/<?= Html::encode($asset->symbol) ?>_BIDR" 
               target="_blank" 
               rel="noopener" 
               class="btn btn-outline-primary btn-sm shadow-sm"
               title="Trading Spot BIDR di Tokocrypto">
                <i class="bi bi-box-arrow-up-right me-1"></i>Tokocrypto
            </a>
            <button type="button" class="btn btn-outline-warning text-dark border-warning btn-sm shadow-sm d-inline-flex align-items-center gap-1" id="btn-live-sync" onclick="triggerLiveSync()">
                <i class="bi bi-arrow-repeat" id="icon-sync-spin"></i>
                <span id="label-live-sync">Update Candle</span>
            </button>
            <a href="<?= Url::to(['/crypto/index', 'timeframe' => $timeframe, 'currency' => $currency]) ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="bi bi-arrow-left"></i> Kembali ke Scanner
            </a>
        </div>
    </div>

    <!-- Metrik Ringkas Koin -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm bg-body-tertiary h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Harga Terkini</div>
                    <div class="h4 mb-0 fw-bold text-dark font-monospace" id="metric-price"><?= $formattedPrice ?></div>
                    <div class="text-muted font-monospace" style="font-size: 0.72rem;"><?= $subPrice ?></div>
                    <div class="small <?= $isUp ? 'text-success' : 'text-danger' ?> mt-1 fw-bold" id="metric-change">
                        <i class="bi bi-<?= $isUp ? 'arrow-up' : 'arrow-down' ?>"></i>
                        <?= ($isUp ? '+' : '') . number_format($change, 2) ?>% (24H)
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm bg-body-tertiary h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Taker Buy Pressure (TF: <?= strtoupper($timeframe) ?>)</div>
                    <div class="h3 mb-0 fw-bold <?= $buyRatio >= 50 ? 'text-success' : 'text-danger' ?>" id="metric-buy-ratio">
                        <?= number_format($buyRatio, 1) ?>%
                    </div>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-success" id="bar-buy" style="width: <?= $buyRatio ?>%"></div>
                        <div class="progress-bar bg-danger" id="bar-sell" style="width: <?= $sellRatio ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm bg-body-tertiary h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Relative Volume (RVOL)</div>
                    <div class="h3 mb-0 fw-bold text-dark font-monospace" id="metric-rvol">
                        <?= number_format($rvol, 2) ?>x
                    </div>
                    <div class="small text-muted mt-1" id="metric-rvol-desc">
                        <?= $rvol >= 1.5 ? '<span class="text-danger fw-bold"><i class="bi bi-fire"></i> Volume Spike Terdeteksi</span>' : 'Volume Relatif Normal' ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm bg-body-tertiary h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">RSI (14) Momentum</div>
                    <div class="h3 mb-0 fw-bold font-monospace <?= $rsi !== null && $rsi <= 35 ? 'text-success' : ($rsi !== null && $rsi >= 70 ? 'text-danger' : 'text-dark') ?>" id="metric-rsi">
                        <?= $rsi !== null ? number_format($rsi, 1) : '-' ?>
                    </div>
                    <div class="small text-muted mt-1" id="metric-rsi-desc">
                        <?php if ($rsi !== null): ?>
                            <?= $rsi <= 32 ? '<span class="text-success fw-bold">Oversold (Potensi Reversal)</span>' : ($rsi >= 70 ? '<span class="text-danger fw-bold">Overbought (Area Jenuh)</span>' : 'Momentum Netral') ?>
                        <?php else: ?>
                            Menghitung...
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Container Card with Interactive Timeframe Switcher -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-bar-chart-line-fill text-primary"></i>
                <span class="fw-bold text-dark">Grafik Candlestick & Taker Volume Breakdown</span>
                <span class="badge bg-light text-secondary border" id="active-tf-badge">Timeframe: <?= strtoupper($timeframe) ?></span>
            </div>

            <!-- AJAX Timeframe Switcher (Tanpa reload halaman) -->
            <div class="btn-group btn-group-sm shadow-none" role="group">
                <?php foreach ($tfLabels as $tfKey => $tfName): ?>
                    <button type="button" 
                            class="btn tf-btn <?= $timeframe === $tfKey ? 'btn-primary active fw-bold' : 'btn-outline-secondary' ?>"
                            data-tf="<?= $tfKey ?>"
                            onclick="switchTimeframe('<?= $tfKey ?>')">
                        <?= strtoupper($tfKey) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card-body p-3">
            <div id="cryptoChart" style="width: 100%; height: 580px;"></div>
        </div>
        <div class="card-footer bg-light border-top py-2 px-3 d-flex justify-content-between align-items-center small text-muted">
            <div>
                <span class="me-3"><span class="badge bg-warning text-dark">&bull;</span> MA20 (Kuning)</span>
                <span class="me-3"><span class="badge bg-info text-white">&bull;</span> MA50 (Biru)</span>
                <span class="me-3"><span class="badge bg-success">&bull;</span> Taker Buy Volume (Hijau)</span>
                <span class="me-3"><span class="badge bg-danger">&bull;</span> Taker Sell Volume (Merah)</span>
                <span><span class="badge text-white" style="background:#7b1fa2;">&bull;</span> RSI 14 (Ungu)</span>
            </div>
            <div>
                <i class="bi bi-mouse me-1"></i> Gunakan scroll mouse untuk Zoom In/Out
            </div>
        </div>
    </div>
</div>

<style>
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
.spin-animation {
    animation: spin 1s infinite linear;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<script>
let cryptoChartInstance = null;
let currentSymbol = '<?= Html::encode($asset->symbol) ?>';
let currentTimeframe = '<?= Html::encode($timeframe) ?>';
let initialChartData = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

document.addEventListener('DOMContentLoaded', function () {
    initChart(initialChartData);
    window.addEventListener('resize', function () {
        if (cryptoChartInstance) {
            cryptoChartInstance.resize();
        }
    });
});

function initChart(data) {
    const chartDom = document.getElementById('cryptoChart');
    if (!chartDom) return;

    if (!cryptoChartInstance) {
        cryptoChartInstance = echarts.init(chartDom);
    }

    const dates = data.dates || [];
    const candles = data.candles || [];
    const buyVols = data.takerBuyVolumes || [];
    const sellVols = data.takerSellVolumes || [];
    const ma20 = data.ma20 || [];
    const ma50 = data.ma50 || [];
    const rsi = data.rsi || [];

    const option = {
        animation: false,
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'cross',
                lineStyle: { color: '#999', width: 1, type: 'dashed' }
            },
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            borderColor: '#ccc',
            borderWidth: 1,
            textStyle: { color: '#333', fontSize: 12 }
        },
        axisPointer: {
            link: [{ xAxisIndex: 'all' }]
        },
        grid: [
            { left: '4%', right: '3%', top: '5%', height: '52%' },      // Candlestick
            { left: '4%', right: '3%', top: '62%', height: '18%' },     // Volume Buy/Sell
            { left: '4%', right: '3%', top: '84%', height: '11%' }      // RSI
        ],
        xAxis: [
            {
                type: 'category',
                data: dates,
                gridIndex: 0,
                scale: true,
                boundaryGap: true,
                axisLine: { onZero: false, lineStyle: { color: '#dee2e6' } },
                axisLabel: { show: false },
                splitLine: { show: false }
            },
            {
                type: 'category',
                data: dates,
                gridIndex: 1,
                scale: true,
                boundaryGap: true,
                axisLine: { onZero: false, lineStyle: { color: '#dee2e6' } },
                axisLabel: { show: false },
                splitLine: { show: false }
            },
            {
                type: 'category',
                data: dates,
                gridIndex: 2,
                scale: true,
                boundaryGap: true,
                axisLine: { onZero: false, lineStyle: { color: '#ced4da' } },
                axisLabel: { color: '#6c757d', fontSize: 11 },
                splitLine: { show: false }
            }
        ],
        yAxis: [
            {
                scale: true,
                gridIndex: 0,
                splitNumber: 4,
                splitLine: { lineStyle: { color: '#f1f3f5' } },
                axisLabel: { color: '#6c757d', fontSize: 11 }
            },
            {
                scale: true,
                gridIndex: 1,
                splitNumber: 2,
                axisLabel: { show: false },
                axisLine: { show: false },
                axisTick: { show: false },
                splitLine: { lineStyle: { color: '#f8f9fa' } }
            },
            {
                scale: false,
                gridIndex: 2,
                min: 0,
                max: 100,
                splitNumber: 2,
                axisLabel: { color: '#adb5bd', fontSize: 10 },
                splitLine: { lineStyle: { color: '#f1f3f5' } }
            }
        ],
        dataZoom: [
            {
                type: 'inside',
                xAxisIndex: [0, 1, 2],
                start: Math.max(0, 100 - (60 / dates.length * 100)),
                end: 100
            },
            {
                show: false,
                xAxisIndex: [0, 1, 2],
                type: 'slider',
                bottom: 5,
                start: Math.max(0, 100 - (60 / dates.length * 100)),
                end: 100
            }
        ],
        series: [
            {
                name: 'OHLC',
                type: 'candlestick',
                data: candles,
                xAxisIndex: 0,
                yAxisIndex: 0,
                itemStyle: {
                    color: '#26a69a',
                    color0: '#ef5350',
                    borderColor: '#26a69a',
                    borderColor0: '#ef5350'
                }
            },
            {
                name: 'MA20',
                type: 'line',
                data: ma20,
                smooth: true,
                xAxisIndex: 0,
                yAxisIndex: 0,
                showSymbol: false,
                lineStyle: { width: 1.5, color: '#f59e0b' }
            },
            {
                name: 'MA50',
                type: 'line',
                data: ma50,
                smooth: true,
                xAxisIndex: 0,
                yAxisIndex: 0,
                showSymbol: false,
                lineStyle: { width: 1.5, color: '#0284c7' }
            },
            {
                name: 'Taker Buy Volume',
                type: 'bar',
                stack: 'volume',
                xAxisIndex: 1,
                yAxisIndex: 1,
                data: buyVols,
                itemStyle: { color: 'rgba(38, 166, 154, 0.85)' }
            },
            {
                name: 'Taker Sell Volume',
                type: 'bar',
                stack: 'volume',
                xAxisIndex: 1,
                yAxisIndex: 1,
                data: sellVols,
                itemStyle: { color: 'rgba(239, 83, 80, 0.85)' }
            },
            {
                name: 'RSI (14)',
                type: 'line',
                data: rsi,
                smooth: true,
                xAxisIndex: 2,
                yAxisIndex: 2,
                showSymbol: false,
                lineStyle: { width: 1.5, color: '#8b5cf6' },
                markLine: {
                    symbol: 'none',
                    data: [
                        { yAxis: 70, lineStyle: { color: '#ef4444', type: 'dashed' } },
                        { yAxis: 30, lineStyle: { color: '#10b981', type: 'dashed' } }
                    ]
                }
            }
        ]
    };

    cryptoChartInstance.setOption(option, true);
}

// Switch timeframe via AJAX tanpa reload
function switchTimeframe(tf) {
    currentTimeframe = tf;

    // Update active button state
    document.querySelectorAll('.tf-btn').forEach(btn => {
        if (btn.getAttribute('data-tf') === tf) {
            btn.className = 'btn tf-btn btn-primary active fw-bold';
        } else {
            btn.className = 'btn tf-btn btn-outline-secondary';
        }
    });

    document.getElementById('active-tf-badge').innerText = 'Timeframe: ' + tf.toUpperCase();

    if (cryptoChartInstance) {
        cryptoChartInstance.showLoading({ text: 'Memuat data ' + tf.toUpperCase() + '...' });
    }

    fetch('<?= Url::to(['/crypto/chart-data']) ?>?symbol=' + currentSymbol + '&timeframe=' + tf)
        .then(res => res.json())
        .then(data => {
            if (cryptoChartInstance) {
                cryptoChartInstance.hideLoading();
            }
            if (data.success && data.chart) {
                initChart(data.chart);
                updateMetricCards(data.analysis);
            }
        })
        .catch(err => {
            if (cryptoChartInstance) cryptoChartInstance.hideLoading();
            console.error(err);
        });
}

function updateMetricCards(analysis) {
    if (!analysis) return;
    const priceElem = document.getElementById('metric-price');
    const buyRatioElem = document.getElementById('metric-buy-ratio');
    const rvolElem = document.getElementById('metric-rvol');
    const rsiElem = document.getElementById('metric-rsi');
    const barBuy = document.getElementById('bar-buy');
    const barSell = document.getElementById('bar-sell');

    if (priceElem) {
        const p = analysis.current_price;
        priceElem.innerText = p >= 1000 ? '$' + p.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '$' + p.toFixed(4);
    }
    if (buyRatioElem) {
        buyRatioElem.innerText = analysis.buy_ratio.toFixed(1) + '%';
        buyRatioElem.className = 'h3 mb-0 fw-bold ' + (analysis.buy_ratio >= 50 ? 'text-success' : 'text-danger');
    }
    if (barBuy && barSell) {
        barBuy.style.width = analysis.buy_ratio + '%';
        barSell.style.width = (100 - analysis.buy_ratio) + '%';
    }
    if (rvolElem) {
        rvolElem.innerText = analysis.rvol.toFixed(2) + 'x';
    }
    if (rsiElem) {
        rsiElem.innerText = analysis.rsi.toFixed(1);
        rsiElem.className = 'h3 mb-0 fw-bold font-monospace ' + (analysis.rsi <= 35 ? 'text-success' : (analysis.rsi >= 70 ? 'text-danger' : 'text-dark'));
    }
}

// Live Sync button
function triggerLiveSync() {
    const btn = document.getElementById('btn-live-sync');
    const spin = document.getElementById('icon-sync-spin');
    const label = document.getElementById('label-live-sync');

    btn.disabled = true;
    spin.classList.add('spin-animation');
    label.innerText = 'Mengambil data Binance...';

    fetch('<?= Url::to(['/crypto/live-sync']) ?>?symbol=' + currentSymbol + '&timeframe=' + currentTimeframe)
        .then(res => res.json())
        .then(data => {
            spin.classList.remove('spin-animation');
            btn.disabled = false;
            label.innerText = 'Update Candle Terkini';
            if (data.success) {
                switchTimeframe(currentTimeframe);
            }
        })
        .catch(err => {
            spin.classList.remove('spin-animation');
            btn.disabled = false;
            label.innerText = 'Update Candle Terkini';
            console.error(err);
        });
}
</script>
