<?php

declare(strict_types=1);

/** @var array $pattern */

use yii\bootstrap5\Html;

$actionBadge = $pattern['action_badge'] ?? ($pattern['breakout'] ? 'bg-success' : 'bg-warning text-dark');
$actionLabel = $pattern['action_label'] ?? ($pattern['breakout'] ? 'BREAKOUT CONFIRMED' : 'WAITING BREAKOUT');
$curPrice = (float) ($pattern['current_price'] ?? 0);
$tp1 = (float) ($pattern['tp1_price'] ?? $pattern['target_price'] ?? 0);
$tp2 = (float) ($pattern['tp2_price'] ?? 0);
$tp3 = (float) ($pattern['tp3_price'] ?? 0);
$slTight = (float) ($pattern['sl_tight'] ?? 0);
$slSwing = (float) ($pattern['sl_price'] ?? 0);
$riskReward = $pattern['risk_reward'] ?? null;
?>

<div class="row g-3 mb-4">
    <!-- Kolom Kiri: Status & Metrik Utama -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-speedometer2 text-primary me-1"></i> Status Pola RSI &amp; Key Levels
                </h5>
                <span class="badge <?= $actionBadge ?> px-3 py-2 fs-6">
                    <?= Html::encode($actionLabel) ?>
                </span>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless align-middle mb-0">
                    <tbody>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><i class="bi bi-star-fill text-warning me-1"></i> Confidence Score</td>
                            <td class="text-end fw-bold fs-5 text-dark py-2"><?= $pattern['confidence'] ?>%</td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><i class="bi bi-arrow-down-circle text-info me-1"></i> Lembah RSI 1 (L1)</td>
                            <td class="text-end py-2">
                                <span class="fw-bold"><?= number_format((float) $pattern['rsi1_value'], 1) ?></span>
                                <div class="small text-muted" style="font-size: 0.75rem;"><?= Html::encode((string) $pattern['rsi1_date']) ?></div>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><i class="bi bi-arrow-down-circle text-info me-1"></i> Lembah RSI 2 (L2)</td>
                            <td class="text-end py-2">
                                <span class="fw-bold"><?= number_format((float) $pattern['rsi2_value'], 1) ?></span>
                                <div class="small text-muted" style="font-size: 0.75rem;"><?= Html::encode((string) $pattern['rsi2_date']) ?></div>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><i class="bi bi-slash-circle text-danger me-1"></i> Neckline RSI (Resistance)</td>
                            <td class="text-end py-2">
                                <span class="fw-bold text-danger"><?= number_format((float) $pattern['neckline_rsi'], 1) ?></span>
                                <div class="small text-muted" style="font-size: 0.75rem;"><?= Html::encode((string) $pattern['neckline_date']) ?></div>
                            </td>
                        </tr>
                        <tr class="border-bottom table-light">
                            <td class="fw-bold text-dark py-2"><i class="bi bi-activity text-primary me-1"></i> Current RSI</td>
                            <td class="text-end fw-bold py-2 <?= ($pattern['breakout'] ?? false) ? 'text-success' : 'text-primary' ?>">
                                <?= number_format((float) $pattern['current_rsi'], 1) ?>
                                <?php if (!empty($pattern['breakout'])): ?>
                                    <span class="badge bg-success ms-1">Crossed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><i class="bi bi-gem text-primary me-1"></i> Bullish Divergence</td>
                            <td class="text-end py-2">
                                <?php if (!empty($pattern['divergence'])): ?>
                                    <span class="badge bg-primary text-white"><i class="bi bi-check-lg"></i> TERKONFIRMASI (Bullish)</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Tidak Ada Divergence</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2"><i class="bi bi-shield-x text-danger me-1"></i> Stop Loss</td>
                            <td class="text-end py-2 text-danger fw-bold">
                                $<?= number_format($slSwing, 2) ?>
                                <small class="text-muted fw-normal d-block" style="font-size: 0.72rem;">(Tight SL: $<?= number_format($slTight, 2) ?>)</small>
                            </td>
                        </tr>
                        <tr class="table-success bg-opacity-25">
                            <td class="fw-bold text-success py-2"><i class="bi bi-bullseye me-1"></i> Target Profit (TP1 100%)</td>
                            <td class="text-end fw-bold text-success fs-5 py-2">
                                $<?= number_format($tp1, 2) ?>
                                <?php if ($riskReward !== null): ?>
                                    <small class="d-block text-muted fw-normal" style="font-size: 0.75rem;">Risk/Reward 1:<?= number_format((float) $riskReward, 1) ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: 5-Point Trading Checklist -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-check2-all text-success me-1"></i> 5-Point RSI Trading Checklist
                </h5>
            </div>
            <div class="card-body">
                <?php
                $cond1 = ($pattern['rsi1_value'] < 40 && $pattern['rsi2_value'] < 40);
                $cond2 = abs($pattern['rsi1_value'] - $pattern['rsi2_value']) <= 3.0;
                $cond3 = !empty($pattern['divergence']);
                $cond4 = !empty($pattern['breakout']);
                $cond5 = ($riskReward !== null && $riskReward >= 1.5);
                $scoreCount = (int)$cond1 + (int)$cond2 + (int)$cond3 + (int)$cond4 + (int)$cond5;
                ?>
                <div class="d-flex align-items-center justify-content-between p-2 mb-3 bg-light rounded">
                    <span class="small fw-bold">Kesiapan Eksekusi:</span>
                    <span class="badge <?= $scoreCount >= 4 ? 'bg-success' : ($scoreCount >= 3 ? 'bg-warning text-dark' : 'bg-secondary') ?> fs-6">
                        <?= $scoreCount ?> / 5 Kriteria Terpenuhi
                    </span>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>1. Lembah di Area Oversold / Lemah:</strong>
                            <div class="text-muted">Kedua lembah RSI &lt; 40 (RSI1: <?= number_format($pattern['rsi1_value'], 1) ?>, RSI2: <?= number_format($pattern['rsi2_value'], 1) ?>)</div>
                        </div>
                        <i class="bi <?= $cond1 ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> fs-5"></i>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>2. Kedalaman Lembah Simetris:</strong>
                            <div class="text-muted">Selisih tinggi antar lembah &le; 3 poin (Diff: <?= number_format(abs($pattern['rsi1_value'] - $pattern['rsi2_value']), 1) ?> pts)</div>
                        </div>
                        <i class="bi <?= $cond2 ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> fs-5"></i>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>3. Bullish Divergence Terkonfirmasi:</strong>
                            <div class="text-muted">Harga cetak lower-low namun RSI cetak higher-low (indikasi akumulasi)</div>
                        </div>
                        <i class="bi <?= $cond3 ? 'bi-check-circle-fill text-success' : 'bi-dash-circle text-muted' ?> fs-5"></i>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>4. Konfirmasi Breakout Neckline:</strong>
                            <div class="text-muted">Current RSI (<?= number_format($pattern['current_rsi'], 1) ?>) &gt; Neckline RSI (<?= number_format($pattern['neckline_rsi'], 1) ?>)</div>
                        </div>
                        <i class="bi <?= $cond4 ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-warning' ?> fs-5"></i>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>5. Risk to Reward Menguntungkan (&ge; 1:1.5):</strong>
                            <div class="text-muted">Proyeksi profit jauh lebih besar dari risiko modal (R:R 1:<?= number_format((float)($riskReward ?? 0), 1) ?>)</div>
                        </div>
                        <i class="bi <?= $cond5 ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> fs-5"></i>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Position Sizing & Risk Calculator -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="bi bi-calculator text-primary me-1"></i> Kalkulator Ukuran Posisi &amp; Manajemen Risiko
        </h5>
        <span class="badge bg-light text-muted border">Otomatis Terisi dari Level Pola</span>
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
                <label class="form-label small fw-bold">Target Profit ($)</label>
                <input type="number" id="rsi-calc-tp" class="form-control" value="<?= $tp1 ?>" step="0.01">
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
                <div class="small text-muted fw-bold">PROYEKSI PROFIT (TP1)</div>
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
        const tp = parseFloat(document.getElementById('rsi-calc-tp').value) || 0;

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

    ['rsi-calc-capital', 'rsi-calc-risk-pct', 'rsi-calc-entry', 'rsi-calc-sl', 'rsi-calc-tp'].forEach(function(id) {
        document.getElementById(id)?.addEventListener('input', recalcRsiPosition);
    });
    recalcRsiPosition();
});
</script>
