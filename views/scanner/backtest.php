<?php

/** @var yii\web\View $this */
/** @var array $singleResult */
/** @var array $comparisonResults */
/** @var array $params */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Validasi & Backtesting Pola Akumulasi';
$this->params['breadcrumbs'][] = ['label' => 'Stock Scanner', 'url' => ['/scanner/index']];
$this->params['breadcrumbs'][] = 'Validasi & Backtesting';

$p = $params;
$res = $singleResult;
$train = $res['train'];
$test = $res['test'];
$comb = $res['combined'];
?>

<div class="container-fluid px-0">
    <!-- Header Banner -->
    <div class="card border-0 shadow-sm mb-4 bg-gradient text-white" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h3 class="fw-bold mb-1">
                        <?= \app\helpers\SvgHelper::icon('graph-up-arrow', ['class' => 'me-2']) ?> Validasi &amp; Backtesting Pola Akumulasi
                    </h3>
                    <p class="mb-0 text-white-50">
                        Uji empiris historis (Walk-Forward Validation, Anti-Lookahead Bias) untuk menemukan parameter optimal RVOL, CMF, Persistence &amp; Net Buy.
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <?= Html::a(
                        \app\helpers\SvgHelper::icon('arrow-left') . ' Kembali ke Scanner',
                        ['/scanner/index'],
                        ['class' => 'btn btn-light btn-sm fw-bold shadow-sm']
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Parameter Config Form -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3 d-flex justify-content-between align-items-center">
            <div class="fw-bold text-dark">
                <?= \app\helpers\SvgHelper::icon('sliders', ['class' => 'text-primary me-1']) ?> Parameter Grid &amp; Asumsi Biaya Transaksi
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                Walk-Forward Split: <?= ($p['splitRatio'] * 100) ?>% Train / <?= ((1 - $p['splitRatio']) * 100) ?>% Test
            </span>
        </div>
        <div class="card-body p-3">
            <form method="get" action="<?= Url::to(['/scanner/backtest']) ?>" id="backtest-form">
                <div class="row g-3">
                    <!-- RVOL Threshold -->
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Threshold RVOL (x)</label>
                        <select name="rvol" class="form-select form-select-sm">
                            <?php foreach ([1.5, 2.0, 2.5, 3.0] as $opt): ?>
                                <option value="<?= $opt ?>" <?= (abs($p['rvol'] - $opt) < 0.01) ? 'selected' : '' ?>><?= $opt ?>x</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" style="font-size:0.7rem;">Ambang lonjakan volume relatif</div>
                    </div>

                    <!-- CMF Threshold -->
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Threshold CMF</label>
                        <select name="cmf" class="form-select form-select-sm">
                            <?php foreach ([0.05, 0.10, 0.15, 0.20] as $opt): ?>
                                <option value="<?= $opt ?>" <?= (abs($p['cmf'] - $opt) < 0.01) ? 'selected' : '' ?>>+<?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" style="font-size:0.7rem;">Chaikin Money Flow 20-bar</div>
                    </div>

                    <!-- Persistence Days -->
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Persistence (Hari)</label>
                        <select name="persistence" class="form-select form-select-sm">
                            <?php foreach ([2, 3, 5, 10] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($p['persistence'] == $opt) ? 'selected' : '' ?>><?= $opt ?> Hari Berturut</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" style="font-size:0.7rem;">Konsistensi akumulasi minimal</div>
                    </div>

                    <!-- Net Buy Lookback -->
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Net Buy Window</label>
                        <select name="netBuyDays" class="form-select form-select-sm">
                            <?php foreach ([3, 5, 10, 20] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($p['netBuyDays'] == $opt) ? 'selected' : '' ?>><?= $opt ?> Hari Terakhir</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" style="font-size:0.7rem;">Lookback dominasi buyer (&ge;60%)</div>
                    </div>

                    <!-- OBV Slope Period -->
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-muted mb-1">OBV Slope (Hari)</label>
                        <select name="obvSlope" class="form-select form-select-sm">
                            <?php foreach ([5, 10, 20] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($p['obvSlope'] == $opt) ? 'selected' : '' ?>><?= $opt ?> Hari</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" style="font-size:0.7rem;">Rentang kenaikan slope OBV</div>
                    </div>

                    <!-- Accumulation Window -->
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Accumulation Base</label>
                        <select name="accWindow" class="form-select form-select-sm">
                            <?php foreach ([5, 10, 20] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($p['accWindow'] == $opt) ? 'selected' : '' ?>><?= $opt ?> Hari</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" style="font-size:0.7rem;">Rentang konsolidasi sideways</div>
                    </div>
                </div>

                <div class="row g-3 mt-1 pt-2 border-top">
                    <!-- Simbol Spesifik -->
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Simbol Spesifik</label>
                        <input type="text" name="symbol" value="<?= Html::encode($p['symbol']) ?>" class="form-control form-control-sm" placeholder="Semua (atau mis. NVDA, BBCA)">
                    </div>

                    <!-- Bursa -->
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Bursa</label>
                        <select name="exchange" class="form-select form-select-sm">
                            <option value="">Semua Bursa</option>
                            <option value="NASDAQ" <?= $p['exchange'] === 'NASDAQ' ? 'selected' : '' ?>>NASDAQ</option>
                            <option value="NYSE" <?= $p['exchange'] === 'NYSE' ? 'selected' : '' ?>>NYSE</option>
                            <option value="IDX" <?= $p['exchange'] === 'IDX' ? 'selected' : '' ?>>IDX (BEI)</option>
                        </select>
                    </div>

                    <!-- Fee Transaksi -->
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Fee Transaksi (%)</label>
                        <input type="number" step="0.05" name="feePct" value="<?= $p['feePct'] ?>" class="form-control form-control-sm" title="Komisi broker beli + jual">
                        <div class="form-text" style="font-size:0.7rem;">Default: 0.30% (buy+sell)</div>
                    </div>

                    <!-- Slippage -->
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Slippage (%)</label>
                        <input type="number" step="0.05" name="slippagePct" value="<?= $p['slippagePct'] ?>" class="form-control form-control-sm">
                        <div class="form-text" style="font-size:0.7rem;">Default: 0.10%</div>
                    </div>

                    <!-- Max Stocks -->
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Jumlah Sampel Saham</label>
                        <select name="maxStocks" class="form-select form-select-sm">
                            <?php foreach ([10 => '10 Saham (Cepat)', 30 => '30 Saham (Rekomendasi)', 50 => '50 Saham', 100 => '100 Saham'] as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= $p['maxStocks'] == $val ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tombol Submit -->
                    <div class="col-md-2 col-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold py-1">
                            <?= \app\helpers\SvgHelper::icon('play-fill') ?> Jalankan Backtest
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Hasil Utama Walk-Forward Testing -->
    <div class="row g-3 mb-4">
        <!-- Out-of-Sample Test Scorecard -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-success">
                        <?= \app\helpers\SvgHelper::icon('check2-circle', ['class' => 'me-1']) ?> Out-of-Sample Test (Data Uji Masa Depan)
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <?= $test['totalSignals'] ?> Sinyal Terverifikasi
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 text-center mb-3">
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border">
                                <div class="text-muted small">Win Rate (+3d)</div>
                                <div class="fs-5 fw-bold <?= $test['winRate3d'] >= 50 ? 'text-success' : 'text-danger' ?>">
                                    <?= $test['winRate3d'] ?>%
                                </div>
                                <div class="small text-muted" style="font-size:0.75rem;">Net: <?= $test['avgNetReturn3d'] ?>%</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border border-primary-subtle">
                                <div class="text-muted small">Win Rate (+5d)</div>
                                <div class="fs-5 fw-bold <?= $test['winRate5d'] >= 50 ? 'text-success' : 'text-danger' ?>">
                                    <?= $test['winRate5d'] ?>%
                                </div>
                                <div class="small fw-semibold <?= $test['avgNetReturn5d'] >= 0 ? 'text-success' : 'text-danger' ?>" style="font-size:0.75rem;">
                                    Net: <?= ($test['avgNetReturn5d'] > 0 ? '+' : '') . $test['avgNetReturn5d'] ?>%
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border">
                                <div class="text-muted small">Win Rate (+10d)</div>
                                <div class="fs-5 fw-bold <?= $test['winRate10d'] >= 50 ? 'text-success' : 'text-danger' ?>">
                                    <?= $test['winRate10d'] ?>%
                                </div>
                                <div class="small fw-semibold <?= $test['avgNetReturn10d'] >= 0 ? 'text-success' : 'text-danger' ?>" style="font-size:0.75rem;">
                                    Net: <?= ($test['avgNetReturn10d'] > 0 ? '+' : '') . $test['avgNetReturn10d'] ?>%
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MAE & MFE Breakdown -->
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border border-danger-subtle">
                                <div class="text-muted small" title="Maximum Adverse Excursion (Rata-rata Penurunan Terburuk Sebelum Naik)">Avg MAE</div>
                                <div class="fw-bold text-danger"><?= $test['avgMae'] ?>%</div>
                                <div class="text-muted" style="font-size:0.7rem;">Risiko Drawdown</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border border-success-subtle">
                                <div class="text-muted small" title="Maximum Favorable Excursion (Rata-rata Kenaikan Tertinggi yang Dicapai)">Avg MFE</div>
                                <div class="fw-bold text-success">+<?= $test['avgMfe'] ?>%</div>
                                <div class="text-muted" style="font-size:0.7rem;">Potensi Peak Profit</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border">
                                <div class="text-muted small">Profit Factor</div>
                                <div class="fw-bold <?= $test['profitFactor10d'] >= 1.5 ? 'text-success' : ($test['profitFactor10d'] >= 1.0 ? 'text-warning-emphasis' : 'text-danger') ?>">
                                    <?= $test['profitFactor10d'] ?>x
                                </div>
                                <div class="text-muted" style="font-size:0.7rem;">R:R <?= $test['rewardRiskRatio'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- In-Sample Train Scorecard -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-primary">
                        <?= \app\helpers\SvgHelper::icon('database', ['class' => 'me-1']) ?> In-Sample Train (Data Kalibrasi Historis)
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <?= $train['totalSignals'] ?> Sinyal Kalibrasi
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 text-center mb-3">
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border">
                                <div class="text-muted small">Train WR (+3d)</div>
                                <div class="fs-5 fw-bold <?= $train['winRate3d'] >= 50 ? 'text-success' : 'text-danger' ?>">
                                    <?= $train['winRate3d'] ?>%
                                </div>
                                <div class="small text-muted" style="font-size:0.75rem;">Net: <?= $train['avgNetReturn3d'] ?>%</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border">
                                <div class="text-muted small">Train WR (+5d)</div>
                                <div class="fs-5 fw-bold <?= $train['winRate5d'] >= 50 ? 'text-success' : 'text-danger' ?>">
                                    <?= $train['winRate5d'] ?>%
                                </div>
                                <div class="small fw-semibold <?= $train['avgNetReturn5d'] >= 0 ? 'text-success' : 'text-danger' ?>" style="font-size:0.75rem;">
                                    Net: <?= ($train['avgNetReturn5d'] > 0 ? '+' : '') . $train['avgNetReturn5d'] ?>%
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border">
                                <div class="text-muted small">Train WR (+10d)</div>
                                <div class="fs-5 fw-bold <?= $train['winRate10d'] >= 50 ? 'text-success' : 'text-danger' ?>">
                                    <?= $train['winRate10d'] ?>%
                                </div>
                                <div class="small fw-semibold <?= $train['avgNetReturn10d'] >= 0 ? 'text-success' : 'text-danger' ?>" style="font-size:0.75rem;">
                                    Net: <?= ($train['avgNetReturn10d'] > 0 ? '+' : '') . $train['avgNetReturn10d'] ?>%
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border border-danger-subtle">
                                <div class="text-muted small">Train MAE</div>
                                <div class="fw-bold text-danger"><?= $train['avgMae'] ?>%</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border border-success-subtle">
                                <div class="text-muted small">Train MFE</div>
                                <div class="fw-bold text-success">+<?= $train['avgMfe'] ?>%</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-light border">
                                <div class="text-muted small">Train Profit Factor</div>
                                <div class="fw-bold text-dark"><?= $train['profitFactor10d'] ?>x</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Parameter Benchmark Comparison Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 px-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <?= \app\helpers\SvgHelper::icon('bar-chart-fill', ['class' => 'text-primary me-2']) ?> Eksplorasi Grid Parameter &amp; Benchmark Kombinasi
                </h5>
                <small class="text-muted">Perbandingan empiris konfigurasi threshold pada data uji Out-of-Sample (setelah potongan fee &amp; slippage)</small>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 text-center" style="font-size:0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th class="text-start">Kombinasi Parameter</th>
                        <th>RVOL</th>
                        <th>CMF</th>
                        <th>Persistence</th>
                        <th>Net Buy</th>
                        <th>OBV Slope</th>
                        <th>Window</th>
                        <th>Test Sinyal</th>
                        <th>Win Rate (+5d)</th>
                        <th>Net Ret (+5d)</th>
                        <th>Win Rate (+10d)</th>
                        <th>Net Ret (+10d)</th>
                        <th>Avg MAE</th>
                        <th>Avg MFE</th>
                        <th>Profit Factor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comparisonResults as $cmp): ?>
                        <?php 
                            $c = $cmp['config'];
                            $isTop = $cmp['profitFactor'] >= 1.5 && $cmp['winRate5d'] >= 50;
                        ?>
                        <tr class="<?= $isTop ? 'table-success' : '' ?>">
                            <td class="text-start fw-bold">
                                <?= Html::encode($cmp['name']) ?>
                                <?php if ($isTop): ?>
                                    <span class="badge bg-success ms-1">Unggul</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= $c['rvol'] ?>x</span></td>
                            <td><span class="badge bg-light text-dark border">+<?= $c['cmf'] ?></span></td>
                            <td><?= $c['pers'] ?>d</td>
                            <td><?= $c['netBuy'] ?>d</td>
                            <td><?= $c['obv'] ?>d</td>
                            <td><?= $c['win'] ?>d</td>
                            <td class="fw-bold"><?= $cmp['testCount'] ?></td>
                            <td class="fw-bold <?= $cmp['winRate5d'] >= 50 ? 'text-success' : 'text-danger' ?>">
                                <?= $cmp['winRate5d'] ?>%
                            </td>
                            <td class="fw-semibold <?= $cmp['avgNetReturn5d'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= ($cmp['avgNetReturn5d'] > 0 ? '+' : '') . $cmp['avgNetReturn5d'] ?>%
                            </td>
                            <td class="fw-bold <?= $cmp['winRate10d'] >= 50 ? 'text-success' : 'text-danger' ?>">
                                <?= $cmp['winRate10d'] ?>%
                            </td>
                            <td class="fw-semibold <?= $cmp['avgNetReturn10d'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= ($cmp['avgNetReturn10d'] > 0 ? '+' : '') . $cmp['avgNetReturn10d'] ?>%
                            </td>
                            <td class="text-danger small"><?= $cmp['avgMae'] ?>%</td>
                            <td class="text-success small">+<?= $cmp['avgMfe'] ?>%</td>
                            <td class="fw-bold <?= $cmp['profitFactor'] >= 1.0 ? 'text-success' : 'text-danger' ?>">
                                <?= $cmp['profitFactor'] ?>x
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sample Recent Test Signals Table -->
    <?php if (!empty($res['sampleTrades'])): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 px-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">
                    <?= \app\helpers\SvgHelper::icon('clock-history', ['class' => 'text-secondary me-1']) ?> Sampel Transaksi &amp; Performa Forward Out-of-Sample (Terbaru)
                </span>
                <span class="small text-muted">Dievaluasi t+1 s/d t+10 setelah sinyal muncul</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center" style="font-size:0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Tanggal Sinyal</th>
                            <th>Simbol</th>
                            <th>Harga Entry</th>
                            <th>Return (+3d)</th>
                            <th>Return (+5d)</th>
                            <th>Return (+10d)</th>
                            <th>MAE (Worst Dip)</th>
                            <th>MFE (Peak Rise)</th>
                            <th>Hasil Bersih (Net Fee)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($res['sampleTrades'] as $idx => $t): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><?= Html::encode($t['date']) ?></td>
                                <td class="fw-bold text-primary"><?= Html::encode($t['symbol']) ?></td>
                                <td><?= number_format($t['entryPrice'], 2) ?></td>
                                <td class="<?= $t['netRet3d'] > 0 ? 'text-success fw-bold' : 'text-danger' ?>">
                                    <?= ($t['netRet3d'] > 0 ? '+' : '') . $t['netRet3d'] ?>%
                                </td>
                                <td class="<?= $t['netRet5d'] > 0 ? 'text-success fw-bold' : 'text-danger' ?>">
                                    <?= ($t['netRet5d'] > 0 ? '+' : '') . $t['netRet5d'] ?>%
                                </td>
                                <td class="<?= $t['netRet10d'] > 0 ? 'text-success fw-bold' : 'text-danger' ?>">
                                    <?= ($t['netRet10d'] > 0 ? '+' : '') . $t['netRet10d'] ?>%
                                </td>
                                <td class="text-danger small"><?= $t['mae'] ?>%</td>
                                <td class="text-success small">+<?= $t['mfe'] ?>%</td>
                                <td>
                                    <?php if ($t['win10d']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">PROFIT (+10d)</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">LOSS / FLAT</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
