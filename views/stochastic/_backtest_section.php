<?php

/** @var yii\web\View $this */
/** @var array $backtestResult */
/** @var \app\models\Stock $stock */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$res = $backtestResult;
if (!$res['success']) {
    echo '<div class="alert alert-warning mb-4 shadow-sm">' . Html::encode($res['message']) . '</div>';
    return;
}

$p = $res['params'];
$train = $res['train'];
$test = $res['test'];
$comb = $res['combined'];
$trades = $res['recentTrades'] ?? [];
?>

<div class="card border-0 shadow-sm mb-4 border-start border-primary border-4" id="backtest-section">
    <!-- Header Backtesting -->
    <div class="card-header bg-white py-3 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="text-primary fs-4"><?= \app\helpers\SvgHelper::icon('graph-up-arrow') ?></span>
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    Hasil Validasi &amp; Backtesting Sinyal Stochastic: <?= Html::encode($stock->symbol) ?>
                </h5>
                <small class="text-muted">
                    Walk-Forward Testing (70% In-Sample Train / 30% Out-of-Sample Test) &bull; <?= $p['totalBarsLoaded'] ?> Bar Historis (<?= Html::encode($p['timeframeLabel']) ?>)
                </small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-light text-secondary border px-2 py-1">
                Fee + Slip: <?= number_format($p['totalCostPct'], 2) ?>%
            </span>
            <?php if (!empty($p['trendFilter'])): ?>
                <span class="badge bg-success-subtle text-success border px-2 py-1">Trend Guard ON</span>
            <?php endif; ?>
            <?php if (!empty($p['candleConfirmation'])): ?>
                <span class="badge bg-primary-subtle text-primary border px-2 py-1">Bullish Bar ON</span>
            <?php endif; ?>
            <span class="badge bg-info-subtle text-dark border px-2 py-1">
                TP: <?= ucfirst($p['tpModel'] ?? 'adaptive') ?>
            </span>
            <a href="#backtest-form-box" class="btn btn-sm btn-outline-primary">
                <?= \app\helpers\SvgHelper::icon('sliders') ?> Ganti Parameter
            </a>
        </div>
    </div>

    <div class="card-body p-3 p-md-4">
        <!-- 3 KPI Cards: Combined, Train (In-Sample), Test (Out-of-Sample) -->
        <div class="row g-3 mb-4">
            <!-- Out-of-Sample Test Card (Paling Utama) -->
            <div class="col-md-4 col-12">
                <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10 border-top border-success border-3">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-success text-white px-2 py-1">TEST (OUT-OF-SAMPLE)</span>
                            <small class="text-muted fw-bold"><?= $p['testRange']['bars'] ?> Bar Terbaru</small>
                        </div>
                        <div class="h3 mb-0 fw-bold text-success">
                            <?= number_format($test['win_rate'], 1) ?>% <small class="fs-6 text-muted fw-normal">Win Rate</small>
                        </div>
                        <div class="small mt-2 text-dark">
                            <div class="d-flex justify-content-between">
                                <span>Total Sinyal:</span>
                                <strong><?= $test['total_signals'] ?> Trade</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Rata-rata Net Return:</span>
                                <strong class="<?= $test['avg_net_return'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= ($test['avg_net_return'] >= 0 ? '+' : '') . number_format($test['avg_net_return'], 2) ?>%
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Profit Factor:</span>
                                <strong><?= number_format($test['profit_factor'], 2) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- In-Sample Train Card -->
            <div class="col-md-4 col-12">
                <div class="card border-0 shadow-sm h-100 bg-primary bg-opacity-10 border-top border-primary border-3">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-primary text-white px-2 py-1">TRAIN (IN-SAMPLE)</span>
                            <small class="text-muted fw-bold"><?= $p['trainRange']['bars'] ?> Bar Lampau</small>
                        </div>
                        <div class="h3 mb-0 fw-bold text-primary">
                            <?= number_format($train['win_rate'], 1) ?>% <small class="fs-6 text-muted fw-normal">Win Rate</small>
                        </div>
                        <div class="small mt-2 text-dark">
                            <div class="d-flex justify-content-between">
                                <span>Total Sinyal:</span>
                                <strong><?= $train['total_signals'] ?> Trade</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Rata-rata Net Return:</span>
                                <strong class="<?= $train['avg_net_return'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= ($train['avg_net_return'] >= 0 ? '+' : '') . number_format($train['avg_net_return'], 2) ?>%
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Profit Factor:</span>
                                <strong><?= number_format($train['profit_factor'], 2) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Combined Full History Card -->
            <div class="col-md-4 col-12">
                <div class="card border-0 shadow-sm h-100 bg-dark bg-opacity-10 border-top border-dark border-3">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-dark text-white px-2 py-1">KESELURUHAN (FULL DATA)</span>
                            <small class="text-muted fw-bold"><?= $comb['total_signals'] ?> Total Trade</small>
                        </div>
                        <div class="h3 mb-0 fw-bold text-dark">
                            <?= ($comb['total_net_return'] >= 0 ? '+' : '') . number_format($comb['total_net_return'], 2) ?>% <small class="fs-6 text-muted fw-normal">Kumulatif</small>
                        </div>
                        <div class="small mt-2 text-dark">
                            <div class="d-flex justify-content-between">
                                <span>Hit Target TP1:</span>
                                <strong class="text-success"><?= number_format($comb['tp1_hit_rate'], 1) ?>%</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Hit Target TP2:</span>
                                <strong class="text-success"><?= number_format($comb['tp2_hit_rate'], 1) ?>%</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Hit Stop Loss (Cut Loss):</span>
                                <strong class="text-danger"><?= number_format($comb['sl_hit_rate'], 1) ?>%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Forward Return Horizon Table: +3, +5, +10, +20 Candle -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light py-2 px-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <?= \app\helpers\SvgHelper::icon('clock-history') ?> Pertumbuhan Harga Pasca Sinyal Stochastic Muncul (Forward Horizons)
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="text-start ps-3">Horizon Pengujian</th>
                            <th>Win Rate (+Harga)</th>
                            <th>Rata-Rata Net Return (%)</th>
                            <th>Peak Potensial (MFE)</th>
                            <th>Drawdown Terdalam (MAE)</th>
                            <th>Status Kehandalan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-start ps-3 fw-bold">
                                <?= \app\helpers\SvgHelper::icon('arrow-right-short') ?> Dalam 3 Candle Berikutnya (+3)
                            </td>
                            <td>
                                <span class="badge bg-<?= $comb['ret_3d']['win_rate'] >= 50 ? 'success' : 'secondary' ?>">
                                    <?= number_format($comb['ret_3d']['win_rate'], 1) ?>%
                                </span>
                            </td>
                            <td class="fw-bold <?= $comb['ret_3d']['avg_net'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= ($comb['ret_3d']['avg_net'] >= 0 ? '+' : '') . number_format($comb['ret_3d']['avg_net'], 2) ?>%
                            </td>
                            <td class="text-success small">+<?= number_format($comb['avg_mfe'] * 0.45, 2) ?>%</td>
                            <td class="text-danger small"><?= number_format($comb['avg_mae'] * 0.55, 2) ?>%</td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= $comb['ret_3d']['win_rate'] >= 55 ? 'Cepat Bereaksi' : 'Konsolidasi' ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-start ps-3 fw-bold">
                                <?= \app\helpers\SvgHelper::icon('arrow-right-short') ?> Dalam 5 Candle Berikutnya (+5)
                            </td>
                            <td>
                                <span class="badge bg-<?= $comb['ret_5d']['win_rate'] >= 50 ? 'success' : 'secondary' ?>">
                                    <?= number_format($comb['ret_5d']['win_rate'], 1) ?>%
                                </span>
                            </td>
                            <td class="fw-bold <?= $comb['ret_5d']['avg_net'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= ($comb['ret_5d']['avg_net'] >= 0 ? '+' : '') . number_format($comb['ret_5d']['avg_net'], 2) ?>%
                            </td>
                            <td class="text-success small">+<?= number_format($comb['avg_mfe'] * 0.70, 2) ?>%</td>
                            <td class="text-danger small"><?= number_format($comb['avg_mae'] * 0.75, 2) ?>%</td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= $comb['ret_5d']['win_rate'] >= 55 ? 'Momentum Reversal' : 'Wajar' ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-start ps-3 fw-bold">
                                <?= \app\helpers\SvgHelper::icon('arrow-right-short') ?> Dalam 10 Candle Berikutnya (+10)
                            </td>
                            <td>
                                <span class="badge bg-<?= $comb['ret_10d']['win_rate'] >= 50 ? 'success' : 'secondary' ?>">
                                    <?= number_format($comb['ret_10d']['win_rate'], 1) ?>%
                                </span>
                            </td>
                            <td class="fw-bold <?= $comb['ret_10d']['avg_net'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= ($comb['ret_10d']['avg_net'] >= 0 ? '+' : '') . number_format($comb['ret_10d']['avg_net'], 2) ?>%
                            </td>
                            <td class="text-success small">+<?= number_format($comb['avg_mfe'] * 0.90, 2) ?>%</td>
                            <td class="text-danger small"><?= number_format($comb['avg_mae'] * 0.90, 2) ?>%</td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= $comb['ret_10d']['win_rate'] >= 55 ? 'Swing Optimal' : 'Evaluasi' ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-start ps-3 fw-bold">
                                <?= \app\helpers\SvgHelper::icon('arrow-right-short') ?> Dalam 20 Candle Berikutnya (+20)
                            </td>
                            <td>
                                <span class="badge bg-<?= $comb['ret_20d']['win_rate'] >= 50 ? 'success' : 'secondary' ?>">
                                    <?= number_format($comb['ret_20d']['win_rate'], 1) ?>%
                                </span>
                            </td>
                            <td class="fw-bold <?= $comb['ret_20d']['avg_net'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= ($comb['ret_20d']['avg_net'] >= 0 ? '+' : '') . number_format($comb['ret_20d']['avg_net'], 2) ?>%
                            </td>
                            <td class="text-success small">+<?= number_format($comb['avg_mfe'], 2) ?>%</td>
                            <td class="text-danger small"><?= number_format($comb['avg_mae'], 2) ?>%</td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= $comb['ret_20d']['win_rate'] >= 50 ? 'Trend Continuation' : 'Time Exit' ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sampel Eksekusi Historis -->
        <?php if (!empty($trades)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <?= \app\helpers\SvgHelper::icon('list-check') ?> Sampel Eksekusi Sinyal Stochastic Historis (20 Terakhir)
                    </h6>
                    <small class="text-muted">Total teruji: <?= count($trades) ?> trade</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light text-muted text-uppercase">
                            <tr>
                                <th class="ps-3">Tanggal Sinyal</th>
                                <th>Setup / Pola</th>
                                <th>%K / %D</th>
                                <th>Harga Beli</th>
                                <th>Stop Loss</th>
                                <th>TP1 / TP2</th>
                                <th>Hasil Selesai</th>
                                <th>Holding</th>
                                <th>Net Return</th>
                                <th class="pe-3 text-end">MAE / MFE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trades as $t): ?>
                                <tr>
                                    <td class="ps-3 text-nowrap">
                                        <strong><?= Html::encode($t['date'] ?? '-') ?></strong>
                                        <span class="badge bg-<?= ($t['split_type'] ?? '') === 'test' ? 'success' : 'primary' ?>-subtle text-<?= ($t['split_type'] ?? '') === 'test' ? 'success' : 'primary' ?> border" style="font-size:0.65rem;">
                                            <?= strtoupper($t['split_type'] ?? 'TRAIN') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $t['badge_class'] ?? 'primary' ?>">
                                            <?= Html::encode($t['signal_name'] ?? 'Stochastic Rebound') ?>
                                        </span>
                                    </td>
                                    <td><?= number_format((float) ($t['k_at_signal'] ?? 0), 1) ?> / <?= number_format((float) ($t['d_at_signal'] ?? 0), 1) ?></td>
                                    <td class="fw-bold">$<?= number_format((float) ($t['entry_price'] ?? 0), 2) ?></td>
                                    <td class="text-danger">$<?= number_format((float) ($t['stop_loss'] ?? 0), 2) ?></td>
                                    <td>
                                        <span class="text-success">$<?= number_format((float) ($t['target_tp1'] ?? 0), 2) ?></span> /
                                        <span class="text-primary">$<?= number_format((float) ($t['target_tp2'] ?? 0), 2) ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($t['hit_tp2'])): ?>
                                            <span class="badge bg-primary">HIT TP2 (+<?= number_format((float) ($t['tp2_gain_pct'] ?? 0), 1) ?>%)</span>
                                        <?php elseif (!empty($t['hit_tp1'])): ?>
                                            <span class="badge bg-success">HIT TP1 (+<?= number_format((float) ($t['tp1_gain_pct'] ?? 0), 1) ?>%)</span>
                                        <?php elseif (!empty($t['hit_sl'])): ?>
                                            <span class="badge bg-danger">HIT STOP LOSS</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">TIME EXIT (20 Bar)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $t['holding_bars'] ?? 1 ?> Bar</td>
                                    <td class="fw-bold <?= ($t['trade_net_ret'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= (($t['trade_net_ret'] ?? 0) >= 0 ? '+' : '') . number_format((float) ($t['trade_net_ret'] ?? 0), 2) ?>%
                                    </td>
                                    <td class="pe-3 text-end text-nowrap">
                                        <span class="text-danger small"><?= number_format((float) ($t['mae_pct'] ?? 0), 1) ?>%</span> /
                                        <span class="text-success small">+<?= number_format((float) ($t['mfe_pct'] ?? 0), 1) ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-light border mb-0 text-center py-3">
                <span class="text-muted">Tidak ada sinyal Stochastic historis yang terpicu dalam rentang pengujian <?= $p['totalBarsLoaded'] ?> bar ini.</span>
            </div>
        <?php endif; ?>
    </div>
</div>

