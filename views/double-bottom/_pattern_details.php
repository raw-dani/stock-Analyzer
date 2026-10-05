<?php

/** @var array $pattern */

use yii\bootstrap5\Html;

$src = $pattern['data_source'] ?? (($pattern['approximated'] ?? false) ? 'daily_fallback' : 'daily');
$tradeStatus = $pattern['trade_status'] ?? ($pattern['breakout'] ? 'buy_zone' : 'forming');
$currentPrice = (float) $pattern['current_price'];
$neckline = (float) $pattern['neckline'];
$targetPrice = (float) $pattern['target_price'];
$tp1 = (float) ($pattern['tp1_price'] ?? ($neckline + (($targetPrice - $neckline) * 0.5)));
$tp2 = (float) ($pattern['tp2_price'] ?? $targetPrice);
$tp3 = (float) ($pattern['tp3_price'] ?? ($neckline + (($targetPrice - $neckline) * 1.618)));
$stopLossTight = (float) ($pattern['stop_loss_tight'] ?? round($neckline * 0.98, 2));
$stopLossSwing = (float) ($pattern['stop_loss'] ?? round((($pattern['low1_price'] + $pattern['low2_price']) / 2) * 0.98, 2));
$distPct = (float) ($pattern['distance_neckline_pct'] ?? 0);
$bestRr = (float) ($pattern['best_rr'] ?? ($pattern['risk_reward'] ?? 0));
$rvol = (float) ($pattern['rvol'] ?? 1.0);
$volDryUp = !empty($pattern['volume_dry_up']);
$volConfirmed = !empty($pattern['volume_confirmed']) || $rvol >= 1.3;

$profitTp1Pct = $currentPrice > 0 ? (($tp1 - $currentPrice) / $currentPrice) * 100 : 0;
$profitTp2Pct = $currentPrice > 0 ? (($tp2 - $currentPrice) / $currentPrice) * 100 : 0;
$lossTightPct = $currentPrice > 0 ? abs(($currentPrice - $stopLossTight) / $currentPrice) * 100 : 0;
$lossSwingPct = $currentPrice > 0 ? abs(($currentPrice - $stopLossSwing) / $currentPrice) * 100 : 0;
?>

<?php if ($src === 'daily_fallback'): ?>
<div class="alert alert-warning py-2 mb-3 small">
    <strong>⚠️ Mode Fallback:</strong> Data intraday belum tersedia untuk saham ini — memakai agregasi harian.
</div>
<?php endif; ?>

<!-- Bar Status Eksekusi -->
<div class="card mb-4 border-0 shadow-sm <?php
    if ($tradeStatus === 'buy_zone') echo 'bg-success text-white';
    elseif ($tradeStatus === 'retest') echo 'bg-info text-dark';
    elseif ($tradeStatus === 'extended') echo 'bg-warning text-dark';
    elseif ($tradeStatus === 'overextended') echo 'bg-danger text-white';
    elseif ($tradeStatus === 'approaching') echo 'bg-primary text-white';
    else echo 'bg-secondary text-white';
?>">
    <div class="card-body py-3 px-4 d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <span class="text-uppercase small fw-bold" style="letter-spacing: 0.05rem;">Status Trading Pola</span>
            <h4 class="mb-0 fw-bold">
                <?php if ($tradeStatus === 'buy_zone'): ?>
                    🎯 Golden Entry Zone (0% - +3% dari Neckline)
                <?php elseif ($tradeStatus === 'retest'): ?>
                    🔄 Retest Neckline (Peluang Beli Reversal Tinggi)
                <?php elseif ($tradeStatus === 'extended'): ?>
                    ⚠️ Harga Mulai Extended (+3% - +6%)
                <?php elseif ($tradeStatus === 'overextended'): ?>
                    ⛔ Overextended (> +6%) — Jangan Kejar! Tunggu Pullback
                <?php elseif ($tradeStatus === 'approaching'): ?>
                    👀 Menjelang Breakout (Pantau Penembusan Neckline)
                <?php else: ?>
                    ⏳ Pola Sedang Terbentuk (Menunggu Breakout)
                <?php endif; ?>
            </h4>
        </div>
        <div class="text-end">
            <span class="fs-4 fw-bold"><?= $pattern['confidence'] ?>%</span>
            <div class="small">Confidence Score</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Panel Kiri: Level Harga & Multi-Target -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold"><i class="bi bi-layers-fill text-primary"></i> Struktur Harga &amp; Level Kunci</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        <tr>
                            <td class="ps-3 text-muted">Bentuk Pola (Geometry)</td>
                            <td class="text-end pe-3 fw-bold">
                                <?php if (($pattern['pattern_type'] ?? '') === 'Higher Low'): ?>
                                    <span class="badge bg-success">Higher Low 🚀 (Bullish)</span>
                                <?php elseif (($pattern['pattern_type'] ?? '') === 'Lower Low'): ?>
                                    <span class="badge bg-secondary">Lower Low (Shakeout)</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Twin Low (Support Datar)</span>
                                <?php endif; ?>
                                <small class="text-muted d-block"><?= $pattern['pattern_span'] ?? 0 ?> candle jarak L1-L2</small>
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Support Low 1 &amp; Low 2</td>
                            <td class="text-end pe-3">
                                <strong>$<?= number_format($pattern['low1_price'], 2) ?></strong> <small class="text-muted">(<?= substr((string)$pattern['low1_date'], 0, 10) ?>)</small><br>
                                <strong>$<?= number_format($pattern['low2_price'], 2) ?></strong> <small class="text-muted">(<?= substr((string)$pattern['low2_date'], 0, 10) ?>)</small>
                            </td>
                        </tr>
                        <tr class="table-light">
                            <td class="ps-3 fw-bold text-dark">Neckline (Titik Konfirmasi)</td>
                            <td class="text-end pe-3 fw-bold fs-6">
                                $<?= number_format($neckline, 2) ?>
                                <div class="small text-muted fw-normal">Zona Beli: $<?= number_format($pattern['entry_zone_min'] ?? $neckline, 2) ?> - $<?= number_format($pattern['entry_zone_max'] ?? ($neckline * 1.025), 2) ?></div>
                            </td>
                        </tr>
                        <tr class="table-primary">
                            <td class="ps-3 fw-bold">Harga Saat Ini</td>
                            <td class="text-end pe-3 fw-bold fs-5 text-primary">
                                $<?= number_format($currentPrice, 2) ?>
                                <span class="badge <?= $distPct >= 0 ? 'bg-success' : 'bg-secondary' ?> ms-1" style="font-size: 0.75rem;">
                                    <?= ($distPct >= 0 ? '+' : '') . number_format($distPct, 1) ?>% dari Neckline
                                </span>
                            </td>
                        </tr>
                        <tr class="table-success">
                            <td class="ps-3">
                                <strong class="text-success">Target 1 (TP1 - Konservatif 50%)</strong><br>
                                <small class="text-muted">Titik kunci amankan 50% profit &amp; BEP</small>
                            </td>
                            <td class="text-end pe-3">
                                <strong class="text-success fs-6">$<?= number_format($tp1, 2) ?></strong>
                                <span class="text-success small fw-bold d-block">+<?= number_format($profitTp1Pct, 1) ?>%</span>
                            </td>
                        </tr>
                        <tr class="table-success">
                            <td class="ps-3">
                                <strong class="text-success">Target 2 (TP2 - Standar 100%)</strong><br>
                                <small class="text-muted">Full Measured Move (Neckline + Tinggi W)</small>
                            </td>
                            <td class="text-end pe-3">
                                <strong class="text-success fs-6">$<?= number_format($tp2, 2) ?></strong>
                                <span class="text-success small fw-bold d-block">+<?= number_format($profitTp2Pct, 1) ?>%</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-3">
                                <strong>Target 3 (TP3 - Runner 161.8%)</strong><br>
                                <small class="text-muted">Fibonacci Extension untuk tren kuat</small>
                            </td>
                            <td class="text-end pe-3">
                                <strong class="fs-6">$<?= number_format($tp3, 2) ?></strong>
                                <small class="text-muted d-block">+<?= number_format($currentPrice > 0 ? (($tp3 - $currentPrice) / $currentPrice * 100) : 0, 1) ?>%</small>
                            </td>
                        </tr>
                        <tr class="table-danger">
                            <td class="ps-3">
                                <strong class="text-danger">Stop Loss Ketat (Tight SL)</strong><br>
                                <small class="text-muted">2% di bawah Neckline (Batal bila Fakeout)</small>
                            </td>
                            <td class="text-end pe-3">
                                <strong class="text-danger fs-6">$<?= number_format($stopLossTight, 2) ?></strong>
                                <span class="text-danger small fw-bold d-block">-<?= number_format($lossTightPct, 1) ?>%</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-3">
                                <strong class="text-danger">Stop Loss Swing (Wide SL)</strong><br>
                                <small class="text-muted">2% di bawah Support Lows</small>
                            </td>
                            <td class="text-end pe-3">
                                <strong class="text-danger">$<?= number_format($stopLossSwing, 2) ?></strong>
                                <small class="text-muted d-block">-<?= number_format($lossSwingPct, 1) ?>%</small>
                            </td>
                        </tr>
                        <tr class="table-light">
                            <td class="ps-3 fw-bold">Rasio Risk / Reward (R:R)</td>
                            <td class="text-end pe-3 fw-bold fs-5 <?= $bestRr >= 2.0 ? 'text-success' : ($bestRr >= 1.5 ? 'text-warning' : 'text-danger') ?>">
                                <?= number_format($bestRr, 1) ?> : 1
                                <small class="text-muted d-block fw-normal" style="font-size: 0.75rem;">(Berdasarkan Tight SL ke TP2)</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Panel Kanan: Rencana Aksi, 5-Point Checklist & Kalkulator Lot -->
    <div class="col-lg-6">
        <!-- Rencana Aksi Langsung -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold"><i class="bi bi-compass-fill text-success"></i> Rencana Eksekusi Trading</h5>
            </div>
            <div class="card-body">
                <div class="alert <?= $tradeStatus === 'buy_zone' || $tradeStatus === 'retest' ? 'alert-success' : ($tradeStatus === 'overextended' ? 'alert-danger' : 'alert-warning') ?> mb-3">
                    <h6 class="fw-bold mb-1"><i class="bi bi-arrow-right-circle-fill"></i> Instruksi Eksekusi:</h6>
                    <?php if ($tradeStatus === 'buy_zone'): ?>
                        Saham berada di <strong>Golden Buy Zone</strong>. Anda dapat mengambil posisi beli saat ini di harga $<?= number_format($currentPrice, 2) ?> dengan target TP1 $<?= number_format($tp1, 2) ?> dan Stop Loss ketat di $<?= number_format($stopLossTight, 2) ?>.
                    <?php elseif ($tradeStatus === 'retest'): ?>
                        Harga sedang melakukan <strong>Pullback / Retest</strong> di area Neckline. Ini merupakan titik entri berprobabilitas tinggi dengan rasio R:R optimal.
                    <?php elseif ($tradeStatus === 'overextended'): ?>
                        <strong>JANGAN BELI SEKARANG!</strong> Harga sudah naik lebih dari 6% dari titik breakout. Pasang alert atau limit order di area retest neckline ($<?= number_format($neckline, 2) ?>).
                    <?php elseif ($tradeStatus === 'approaching'): ?>
                        Harga sedang mendekati Neckline ($<?= number_format($neckline, 2) ?>). Anda dapat memasang <strong>Buy Stop Order</strong> di $<?= number_format($neckline * 1.005, 2) ?> untuk otomatis membeli begitu breakout terkonfirmasi.
                    <?php else: ?>
                        Pola masih dalam tahap pembentukan. Tunggu hingga harga mampu menembus dan bertahan di atas Neckline $<?= number_format($neckline, 2) ?>.
                    <?php endif; ?>
                </div>

                <!-- 5-Point Quality Checklist -->
                <h6 class="fw-bold text-dark mb-2">5-Point Trading Checklist:</h6>
                <ul class="list-group list-group-flush border rounded mb-3 small">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>1. Bentuk Pola W Simetris &amp; Valid</span>
                        <span class="badge bg-success">Lulus ✓</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>2. Volume Low 2 Mengering (Supply Dry-up)</span>
                        <?php if ($volDryUp): ?>
                            <span class="badge bg-success">Lulus ✓ (Tekanan Jual Habis)</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Netral (Vol Normal)</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>3. Konfirmasi Penembusan Neckline</span>
                        <?php if ($pattern['breakout']): ?>
                            <span class="badge bg-success">Lulus ✓ (Breakout Sukses)</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Belum (Menunggu Breakout)</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>4. Lonjakan Volume Breakout (RVOL &gt; 1.3x)</span>
                        <?php if ($volConfirmed): ?>
                            <span class="badge bg-success">Lulus ✓ (RVOL <?= number_format($rvol, 1) ?>x)</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Rendah (RVOL <?= number_format($rvol, 1) ?>x)</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>5. Rasio Risk-to-Reward Layak (R:R &ge; 2.0x)</span>
                        <?php if ($bestRr >= 2.0): ?>
                            <span class="badge bg-success">Lulus ✓ (<?= number_format($bestRr, 1) ?> : 1)</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Kurang (<?= number_format($bestRr, 1) ?> : 1)</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Kalkulator Manajemen Risiko & Ukuran Posisi (Position Sizing) -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold"><i class="bi bi-calculator text-primary"></i> Kalkulator Ukuran Posisi &amp; Risiko</h5>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Total Modal Portofolio ($)</label>
                        <input type="number" id="calc-capital" class="form-control form-control-sm" value="10000" min="100" step="500">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Maks. Risiko Per Trade (%)</label>
                        <input type="number" id="calc-risk-pct" class="form-control form-control-sm" value="1.0" min="0.1" max="5.0" step="0.1">
                    </div>
                </div>

                <div class="p-3 bg-light rounded border">
                    <div class="row g-2 text-center">
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">Nominal Risiko</small>
                            <strong class="text-danger fs-6" id="res-risk-amt">$100</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">Jml Lembar Saham</small>
                            <strong class="text-primary fs-6" id="res-shares">0 lembar</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">Total Alokasi Modal</small>
                            <strong class="text-dark fs-6" id="res-alloc">$0</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">Potensi Profit (TP2)</small>
                            <strong class="text-success fs-6" id="res-profit">$0</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function recalc() {
        var cap = parseFloat(document.getElementById('calc-capital').value) || 0;
        var rPct = parseFloat(document.getElementById('calc-risk-pct').value) || 0;
        var currPrice = <?= json_encode($currentPrice) ?>;
        var sl = <?= json_encode($stopLossTight) ?>;
        var tp = <?= json_encode($tp2) ?>;

        var maxRiskDollar = cap * (rPct / 100);
        var riskPerShare = Math.max(currPrice - sl, 0.01);
        var shares = Math.floor(maxRiskDollar / riskPerShare);
        if (shares < 0) shares = 0;

        var totalAlloc = shares * currPrice;
        var potentialProfit = shares * Math.max(tp - currPrice, 0);

        document.getElementById('res-risk-amt').innerText = '$' + maxRiskDollar.toFixed(0);
        document.getElementById('res-shares').innerText = shares.toLocaleString() + ' shs';
        document.getElementById('res-alloc').innerText = '$' + totalAlloc.toLocaleString(undefined, {maximumFractionDigits: 0});
        document.getElementById('res-profit').innerText = '+$' + potentialProfit.toLocaleString(undefined, {maximumFractionDigits: 0});
    }

    var capEl = document.getElementById('calc-capital');
    var riskEl = document.getElementById('calc-risk-pct');
    if (capEl && riskEl) {
        capEl.addEventListener('input', recalc);
        riskEl.addEventListener('input', recalc);
        recalc();
    }
})();
</script>