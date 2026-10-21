<?php

declare(strict_types=1);

/** @var array $pattern */

use yii\bootstrap5\Html;

$actionBadge = $pattern['action_badge'] ?? 'bg-secondary';
$actionLabel = $pattern['action_label'] ?? 'WAITING';
$curPrice = (float) ($pattern['current_price'] ?? 0);
$tp1 = (float) ($pattern['tp1_price'] ?? $pattern['target_price'] ?? 0);
$tp2 = (float) ($pattern['tp2_price'] ?? 0);
$tp3 = (float) ($pattern['tp3_price'] ?? 0);
$slTight = (float) ($pattern['sl_tight'] ?? 0);
$slSwing = (float) ($pattern['sl_price'] ?? 0);
$riskReward = $pattern['risk_reward'] ?? null;
$curRsi = (float) ($pattern['current_rsi'] ?? 50);
$prevRsi = (float) ($pattern['prev_rsi'] ?? 50);
$ma50 = $pattern['ma50'] ?? null;
$isAboveMa50 = !empty($pattern['is_above_ma50']);
$isBuy = in_array($pattern['action'] ?? '', ['BUY_NOW', 'BUY_PULLBACK', 'MOMENTUM_BUY'], true);
$isSell = in_array($pattern['action'] ?? '', ['SELL_NOW', 'TAKE_PROFIT'], true);
$tp1Pct = (float) ($pattern['tp1_pct'] ?? ($curPrice > 0 ? round((($tp1 - $curPrice) / $curPrice) * 100, 2) : 0));
$tp2Pct = (float) ($pattern['tp2_pct'] ?? ($curPrice > 0 ? round((($tp2 - $curPrice) / $curPrice) * 100, 2) : 0));
$tp3Pct = (float) ($pattern['tp3_pct'] ?? ($curPrice > 0 ? round((($tp3 - $curPrice) / $curPrice) * 100, 2) : 0));
$slPct = (float) ($pattern['potential_risk'] ?? ($curPrice > 0 ? round((($curPrice - $slSwing) / $curPrice) * 100, 2) : 0));
$slTightPct = $curPrice > 0 ? round((($curPrice - $slTight) / $curPrice) * 100, 2) : 0;
?>

<div class="row g-3 mb-4">
    <!-- Kolom Kiri: Status & Rekomendasi Sinyal RSI & Multi-Strategi -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <?= \app\helpers\SvgHelper::icon('speedometer2', ['class' => 'text-primary me-1']) ?> Rekomendasi Eksekusi &amp; Level Kunci
                </h5>
                <span class="badge <?= $actionBadge ?> px-3 py-2 fs-6">
                    <?= Html::encode($actionLabel) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="alert <?= $isBuy ? 'alert-success' : ($isSell ? 'alert-danger' : 'alert-light border') ?> py-2 mb-3">
                    <strong class="d-block mb-1">
                        <?= $isBuy 
                            ? \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success me-1']) 
                            : ($isSell 
                                ? \app\helpers\SvgHelper::icon('exclamation-triangle-fill', ['class' => 'text-danger me-1']) 
                                : \app\helpers\SvgHelper::icon('info-circle', ['class' => 'text-primary me-1'])) ?>
                        Analisa Logika:
                    </strong>
                    <span class="small"><?= Html::encode($pattern['signal_reason'] ?? 'Menunggu formasi konfirmasi RSI.') ?></span>
                </div>

                <table class="table table-sm table-borderless align-middle mb-0">
                    <tbody>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><?= \app\helpers\SvgHelper::icon('star-fill', ['class' => 'text-warning me-1']) ?> Confidence Score</td>
                            <td class="text-end fw-bold fs-5 text-dark py-2"><?= $pattern['confidence'] ?? 50 ?>%</td>
                        </tr>
                        <tr class="border-bottom table-light">
                            <td class="fw-bold text-dark py-2"><?= \app\helpers\SvgHelper::icon('activity', ['class' => 'text-primary me-1']) ?> Current RSI (14)</td>
                            <td class="text-end fw-bold py-2 <?= $curRsi <= 35 ? 'text-success' : ($curRsi >= 65 ? 'text-danger' : 'text-primary') ?>">
                                <?= number_format($curRsi, 1) ?>
                                <small class="text-muted fw-normal d-inline-block ms-1">(Kemarin: <?= number_format($prevRsi, 1) ?>)</small>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><?= \app\helpers\SvgHelper::icon('compass', ['class' => 'text-info me-1']) ?> Filter Tren (MA50)</td>
                            <td class="text-end py-2">
                                <?php if ($isAboveMa50): ?>
                                    <span class="badge bg-success-subtle text-success border border-success">
                                        <?= \app\helpers\SvgHelper::icon('arrow-up-circle') ?> Bullish (Di atas MA50: $<?= number_format((float)$ma50, 2) ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <?= \app\helpers\SvgHelper::icon('arrow-down-circle') ?> Bearish (Di bawah MA50: $<?= number_format((float)$ma50, 2) ?>)
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><?= \app\helpers\SvgHelper::icon('bar-chart-fill', ['class' => 'text-primary me-1']) ?> Konfirmasi Volume (RVOL)</td>
                            <td class="text-end py-2">
                                <?php $rvolVal = (float)($pattern['rvol'] ?? 1.0); ?>
                                <?php if (!empty($pattern['volume_spike'])): ?>
                                    <span class="badge bg-info text-dark" title="Lonjakan Volume &gt;= 1.5x SMA20">
                                        <?= \app\helpers\SvgHelper::icon('lightning-fill') ?> RVOL <?= number_format($rvolVal, 2) ?>x (Surge)
                                    </span>
                                <?php elseif (!empty($pattern['volume_supportive'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success">
                                        <?= \app\helpers\SvgHelper::icon('check-lg') ?> RVOL <?= number_format($rvolVal, 2) ?>x (Mendukung)
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">RVOL <?= number_format($rvolVal, 2) ?>x (Normal)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><?= \app\helpers\SvgHelper::icon('reception-4', ['class' => 'text-purple me-1']) ?> Pola Candlestick</td>
                            <td class="text-end py-2">
                                <?php if (!empty($pattern['candle_pattern'])): ?>
                                    <span class="badge <?= ($pattern['candle_signal'] ?? '') === 'BULLISH' ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                                        <?= Html::encode($pattern['candle_pattern']) ?> (<?= Html::encode($pattern['candle_signal'] ?? '') ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">Konsolidasi Standar</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><?= \app\helpers\SvgHelper::icon('bullseye', ['class' => 'text-warning me-1']) ?> Fibonacci Retracement</td>
                            <td class="text-end py-2">
                                <?php if (!empty($pattern['is_golden_pocket'])): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning">
                                        <?= \app\helpers\SvgHelper::icon('star-fill') ?> Golden Pocket 50%-61.8% ($<?= number_format((float)($pattern['fib618'] ?? 0), 2) ?> - $<?= number_format((float)($pattern['fib50'] ?? 0), 2) ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">Swing: $<?= number_format((float)($pattern['swing_low'] ?? 0), 2) ?> - $<?= number_format((float)($pattern['swing_high'] ?? 0), 2) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><?= \app\helpers\SvgHelper::icon('gem', ['class' => 'text-primary me-1']) ?> Status Divergence</td>
                            <td class="text-end py-2">
                                <?php if (!empty($pattern['divergence'])): ?>
                                    <span class="badge bg-success text-white"><?= \app\helpers\SvgHelper::icon('check-lg') ?> BULLISH DIVERGENCE (Waktu Beli)</span>
                                <?php elseif (!empty($pattern['bearish_divergence'])): ?>
                                    <span class="badge bg-danger text-white"><?= \app\helpers\SvgHelper::icon('exclamation-triangle') ?> BEARISH DIVERGENCE (Waktu Jual)</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Tidak Ada Divergensi</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><?= \app\helpers\SvgHelper::icon('lightning-charge', ['class' => 'text-secondary me-1']) ?> Status Shakeout Low</td>
                            <td class="text-end py-2">
                                <?php if (!empty($pattern['is_shakeout_low']) || ($pattern['signal_type'] ?? '') === 'BUY_SHAKEOUT_LOW'): ?>
                                    <span class="badge bg-secondary text-white"><?= \app\helpers\SvgHelper::icon('lightning-charge') ?> Shakeout Low Terdeteksi (-<?= number_format((float)($pattern['shakeout_pct'] ?? 0), 1) ?>%)</span>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Undercut support Low 1 sebelum memantul kembali (Liquidity Sweep)</small>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Normal / Tidak Ada Shakeout</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><?= \app\helpers\SvgHelper::icon('shield-x', ['class' => 'text-danger me-1']) ?> Batas Cut Loss (SL)</td>
                            <td class="text-end py-2">
                                <span class="text-danger fw-bold fs-6">$<?= number_format($slSwing, 2) ?></span>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle ms-1">-<?= number_format($slPct, 2) ?>%</span>
                                <small class="text-muted fw-normal d-block" style="font-size: 0.72rem;">Tight SL: $<?= number_format($slTight, 2) ?> (-<?= number_format($slTightPct, 2) ?>%)</small>
                            </td>
                        </tr>
                        <tr class="table-success bg-opacity-25 border-bottom">
                            <td class="fw-bold text-success py-2">
                                <?= \app\helpers\SvgHelper::icon('bullseye', ['class' => 'me-1']) ?> Target Profit 1 (TP1 Konservatif)
                            </td>
                            <td class="text-end py-2">
                                <span class="fw-bold text-success fs-5">$<?= number_format($tp1, 2) ?></span>
                                <span class="badge bg-success text-white fw-bold ms-1">+<?= number_format($tp1Pct, 2) ?>%</span>
                                <?php if ($riskReward !== null): ?>
                                    <small class="d-block text-muted fw-normal" style="font-size: 0.75rem;">Risk/Reward 1:<?= number_format((float) $riskReward, 1) ?> (Swing High / Min 1.5R - 1.8R)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2">
                                <?= \app\helpers\SvgHelper::icon('graph-up-arrow', ['class' => 'text-primary me-1']) ?> Target Profit 2 (TP2 Fibonacci Ext 1.272)
                            </td>
                            <td class="text-end py-2">
                                <span class="fw-semibold text-primary fs-6">$<?= number_format($tp2, 2) ?></span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold ms-1">+<?= number_format($tp2Pct, 2) ?>%</span>
                                <small class="d-block text-muted fw-normal" style="font-size: 0.72rem;">Extended 2.8R Target</small>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted py-2">
                                <?= \app\helpers\SvgHelper::icon('trophy', ['class' => 'text-warning me-1']) ?> Target Profit 3 (TP3 Golden Ext 1.618)
                            </td>
                            <td class="text-end py-2">
                                <span class="fw-semibold text-dark fs-6">$<?= number_format($tp3, 2) ?></span>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-bold ms-1">+<?= number_format($tp3Pct, 2) ?>%</span>
                                <small class="d-block text-muted fw-normal" style="font-size: 0.72rem;">Extended 4.0R Trend Rider</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: 6-Point Multi-Strategy Trading Checklist -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <?= \app\helpers\SvgHelper::icon('check2-all', ['class' => 'text-success me-1']) ?> 6-Point Multi-Strategy Trading Checklist
                </h5>
            </div>
            <div class="card-body">
                <?php
                $cond1 = ($curRsi <= 40 || !empty($pattern['divergence']));
                $cond2 = ($curRsi > $prevRsi); // RSI mulai memantul ke atas
                $cond3 = $isAboveMa50; // Tren sehat
                $cond4 = (!empty($pattern['divergence']) || !empty($pattern['breakout']) || !empty($pattern['is_golden_pocket']) || ($pattern['candle_signal'] ?? '') === 'BULLISH');
                $cond5 = (!empty($pattern['volume_spike']) || !empty($pattern['volume_supportive']));
                $cond6 = ($riskReward !== null && $riskReward >= 1.5);
                $scoreCount = (int)$cond1 + (int)$cond2 + (int)$cond3 + (int)$cond4 + (int)$cond5 + (int)$cond6;
                ?>
                <div class="d-flex align-items-center justify-content-between p-2 mb-3 bg-light rounded">
                    <span class="small fw-bold">Kualitas Setup Perdagangan Terkonfirmasi:</span>
                    <span class="badge <?= $scoreCount >= 5 ? 'bg-success' : ($scoreCount >= 3 ? 'bg-warning text-dark' : 'bg-secondary') ?> fs-6">
                        <?= $scoreCount ?> / 6 Parameter Terpenuhi
                    </span>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>1. Area Masuk Diskon (Oversold / Reversal Zone):</strong>
                            <div class="text-muted">RSI berada di zona pantulan &lt; 40 (Current: <?= number_format($curRsi, 1) ?>)</div>
                        </div>
                        <?= $cond1 
                            ? \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success fs-5']) 
                            : \app\helpers\SvgHelper::icon('x-circle-fill', ['class' => 'text-secondary fs-5']) ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>2. Konfirmasi Momentum Rebound RSI:</strong>
                            <div class="text-muted">RSI hari ini melengkung naik (<?= number_format($curRsi, 1) ?> &gt; <?= number_format($prevRsi, 1) ?>)</div>
                        </div>
                        <?= $cond2 
                            ? \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success fs-5']) 
                            : \app\helpers\SvgHelper::icon('x-circle-fill', ['class' => 'text-danger fs-5']) ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>3. Trend Bias Filter (MA50):</strong>
                            <div class="text-muted">Harga berada di atas MA50 (Bukan pisau jatuh liar)</div>
                        </div>
                        <?= $cond3 
                            ? \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success fs-5']) 
                            : \app\helpers\SvgHelper::icon('dash-circle', ['class' => 'text-warning fs-5']) ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>4. Katalis Reversal (Candle / Fib / Divergence):</strong>
                            <div class="text-muted">Terdapat Bullish Divergence, Reversal Candle (<?= Html::encode($pattern['candle_pattern'] ?? 'Tidak Ada') ?>), atau Fib Pocket</div>
                        </div>
                        <?= $cond4 
                            ? \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success fs-5']) 
                            : \app\helpers\SvgHelper::icon('dash-circle', ['class' => 'text-muted fs-5']) ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>5. Konfirmasi Volume Transaksi (RVOL &ge; 1.0x):</strong>
                            <div class="text-muted">Volume saat harga rebound mencukupi (RVOL: <?= number_format((float)($pattern['rvol'] ?? 1.0), 2) ?>x)</div>
                        </div>
                        <?= $cond5 
                            ? \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success fs-5']) 
                            : \app\helpers\SvgHelper::icon('x-circle-fill', ['class' => 'text-secondary fs-5']) ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>6. Rasio Risk to Reward Memadai (&ge; 1:1.5):</strong>
                            <div class="text-muted">Potensi keuntungan TP1 jauh lebih besar dibanding batas risiko (R:R 1:<?= number_format((float)($riskReward ?? 0), 1) ?>)</div>
                        </div>
                        <?= $cond6 
                            ? \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success fs-5']) 
                            : \app\helpers\SvgHelper::icon('x-circle-fill', ['class' => 'text-danger fs-5']) ?>
                    </li>
                </ul>

                <div class="p-2 mt-3 bg-light rounded small">
                    <strong class="d-block text-dark mb-1"><?= \app\helpers\SvgHelper::icon('lightbulb-fill', ['class' => 'text-warning me-1']) ?> Strategi Eksekusi Take Profit Bertahap:</strong>
                    <div class="text-muted">
                        • <strong>TP1 ($<?= number_format($tp1, 2) ?>):</strong> Jual 40%-50% porsi saham untuk amankan modal &amp; kunci cuan.<br>
                        • <strong>TP2 ($<?= number_format($tp2, 2) ?>):</strong> Jual 30% porsi tambahan dan naikkan Stop Loss ke Trailing Stop (Break Even).<br>
                        • <strong>TP3 ($<?= number_format($tp3, 2) ?>):</strong> Biarkan sisa 20%-30% saham <em>let your profits run</em> hingga RSI jenuh jual &gt; 75.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Position Sizing & Risk Calculator -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark">
            <?= \app\helpers\SvgHelper::icon('calculator', ['class' => 'text-primary me-1']) ?> Kalkulator Ukuran Posisi &amp; Multi-Target TP (TP1 / TP2 / TP3)
        </h5>
        <span class="badge bg-light text-muted border">Kalkulasi Otomatis Sesuai Modal</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Total Modal Portofolio ($)</label>
                <input type="number" id="rsi-calc-capital" class="form-control" value="10000" step="500">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Toleransi Risiko (%)</label>
                <input type="number" id="rsi-calc-risk-pct" class="form-control" value="1.0" step="0.5" min="0.1" max="10">
                <div class="form-text small text-muted">Standar: 1% - 2% per trade</div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Harga Entry ($)</label>
                <input type="number" id="rsi-calc-entry" class="form-control" value="<?= $curPrice ?>" step="0.01">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Stop Loss ($)</label>
                <input type="number" id="rsi-calc-sl" class="form-control" value="<?= $slSwing ?>" step="0.01">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Pilihan Target Profit</label>
                <select id="rsi-calc-tp-target" class="form-select">
                    <option value="<?= $tp1 ?>" selected>TP1 Utama: $<?= number_format($tp1, 2) ?> (+<?= number_format($tp1Pct, 1) ?>%)</option>
                    <option value="<?= $tp2 ?>">TP2 Runner: $<?= number_format($tp2, 2) ?> (+<?= number_format($tp2Pct, 1) ?>%)</option>
                    <option value="<?= $tp3 ?>">TP3 Moonbag: $<?= number_format($tp3, 2) ?> (+<?= number_format($tp3Pct, 1) ?>%)</option>
                </select>
                <input type="hidden" id="rsi-calc-tp" value="<?= $tp1 ?>">
            </div>
        </div>

        <div class="row g-3 mt-2 pt-3 border-top bg-light rounded p-2">
            <div class="col-md-3 text-center">
                <div class="small text-muted fw-bold">RISIKO MAKSIMAL ($)</div>
                <div class="h4 mb-0 text-danger fw-bold" id="rsi-res-max-loss">$100.00</div>
            </div>
            <div class="col-md-3 text-center border-start">
                <div class="small text-muted fw-bold">UKURAN POSISI (LEMBAR)</div>
                <div class="h4 mb-0 text-primary fw-bold" id="rsi-res-shares">0 lembar</div>
                <div class="small text-muted" id="rsi-res-total-alloc">Alokasi: $0.00</div>
            </div>
            <div class="col-md-3 text-center border-start">
                <div class="small text-muted fw-bold">PROYEKSI PROFIT</div>
                <div class="h4 mb-0 text-success fw-bold" id="rsi-res-profit">$0.00</div>
            </div>
            <div class="col-md-3 text-center border-start">
                <div class="small text-muted fw-bold">RASIO RISK / REWARD</div>
                <div class="h4 mb-0 fw-bold" id="rsi-res-rr">1 : 0.0</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function recalcRsiPosition() {
        const capital = parseFloat(document.getElementById('rsi-calc-capital').value) || 0;
        const riskPct = parseFloat(document.getElementById('rsi-calc-risk-pct').value) || 0;
        const entry = parseFloat(document.getElementById('rsi-calc-entry').value) || 0;
        const sl = parseFloat(document.getElementById('rsi-calc-sl').value) || 0;
        const tpSelector = document.getElementById('rsi-calc-tp-target');
        const tp = parseFloat(tpSelector ? tpSelector.value : document.getElementById('rsi-calc-tp').value) || 0;

        const maxRiskUsd = (capital * riskPct) / 100;
        document.getElementById('rsi-res-max-loss').innerText = '$' + maxRiskUsd.toFixed(2);

        const riskPerShare = entry - sl;
        if (riskPerShare > 0 && entry > 0) {
            const shares = Math.floor(maxRiskUsd / riskPerShare);
            const totalAlloc = shares * entry;
            const profit = shares * (tp - entry);
            const rr = (tp - entry) / riskPerShare;

            document.getElementById('rsi-res-shares').innerText = shares.toLocaleString() + ' lembar';
            document.getElementById('rsi-res-total-alloc').innerText = 'Alokasi: $' + totalAlloc.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('rsi-res-profit').innerText = '+$' + profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('rsi-res-rr').innerText = '1 : ' + (rr > 0 ? rr.toFixed(2) : '0.0');
            document.getElementById('rsi-res-rr').className = 'h4 mb-0 fw-bold ' + (rr >= 2.0 ? 'text-success' : (rr >= 1.5 ? 'text-primary' : 'text-danger'));
        } else {
            document.getElementById('rsi-res-shares').innerText = '0 lembar';
            document.getElementById('rsi-res-total-alloc').innerText = 'SL harus di bawah Entry';
            document.getElementById('rsi-res-profit').innerText = '$0.00';
            document.getElementById('rsi-res-rr').innerText = 'Invalid';
        }
    }

    ['rsi-calc-capital', 'rsi-calc-risk-pct', 'rsi-calc-entry', 'rsi-calc-sl', 'rsi-calc-tp-target'].forEach(function(id) {
        document.getElementById(id)?.addEventListener('input', recalcRsiPosition);
        document.getElementById(id)?.addEventListener('change', recalcRsiPosition);
    });
    recalcRsiPosition();
});
</script>
