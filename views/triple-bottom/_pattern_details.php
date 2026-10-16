<?php

/** @var array $pattern */

use yii\bootstrap5\Html;
use app\services\TripleBottomService;

$src = $pattern['data_source'] ?? (($pattern['approximated'] ?? false) ? 'daily_fallback' : 'daily');
$tradeStatus = $pattern['trade_status'] ?? ($pattern['breakout'] ? 'buy_zone' : 'forming');
$currentPrice = (float) $pattern['current_price'];
$neckline = (float) $pattern['neckline'];
$targetPrice = (float) $pattern['target_price'];
$tp1 = (float) ($pattern['tp1_price'] ?? ($neckline + (($targetPrice - $neckline) * 0.5)));
$tp2 = (float) ($pattern['tp2_price'] ?? $targetPrice);
$tp3 = (float) ($pattern['tp3_price'] ?? ($neckline + (($targetPrice - $neckline) * 1.618)));
$stopLossTight = (float) ($pattern['stop_loss_tight'] ?? round($neckline * 0.98, 2));
$stopLossSwing = (float) ($pattern['stop_loss'] ?? round($pattern['min_support'] * 0.98, 2));
$distPct = (float) ($pattern['distance_neckline_pct'] ?? 0);
$bestRr = (float) ($pattern['best_rr'] ?? ($pattern['risk_reward'] ?? 0));
$rvol = (float) ($pattern['rvol'] ?? 1.0);
$volDryUp = !empty($pattern['volume_dry_up']);
$volConfirmed = !empty($pattern['volume_confirmed']) || $rvol >= 1.3;

$isIhs = ($pattern['pattern_type'] ?? '') === TripleBottomService::PATTERN_INVERSE_HS;
$patternName = $pattern['pattern_name'] ?? ($isIhs ? 'Inverted Head & Shoulders' : 'Triple Bottom');

$profitTp1Pct = $currentPrice > 0 ? (($tp1 - $currentPrice) / $currentPrice) * 100 : 0;
$profitTp2Pct = $currentPrice > 0 ? (($tp2 - $currentPrice) / $currentPrice) * 100 : 0;
$profitTp3Pct = $currentPrice > 0 ? (($tp3 - $currentPrice) / $currentPrice) * 100 : 0;
$lossTightPct = $currentPrice > 0 ? abs(($currentPrice - $stopLossTight) / $currentPrice) * 100 : 0;
$lossSwingPct = $currentPrice > 0 ? abs(($currentPrice - $stopLossSwing) / $currentPrice) * 100 : 0;

$riskLevel = $pattern['false_breakout_risk'] ?? 'LOW';
$riskScore = (int) ($pattern['false_breakout_score'] ?? 10);
$isBullTrap = !empty($pattern['bull_trap_detected']);
$hasUpperWick = !empty($pattern['rejection_wick']);
$upperWickPct = (float) ($pattern['upper_wick_pct'] ?? 0);
$lowVolBreakout = !empty($pattern['low_volume_breakout']);
$riskReasons = (array) ($pattern['false_breakout_reasons'] ?? []);
$mitigationStrategies = (array) ($pattern['mitigation_strategies'] ?? []);
?>

<?php if ($src === 'daily_fallback'): ?>
<div class="alert alert-warning py-2 mb-3 small">
    <strong>⚠️ Mode Fallback:</strong> Data intraday belum tersedia untuk saham ini — menggunakan data harian.
</div>
<?php endif; ?>

<!-- Bar Status Eksekusi Beli / Jual -->
<div class="card mb-4 border-0 shadow-sm <?php
    if ($tradeStatus === 'buy_zone') echo 'bg-success text-white';
    elseif ($tradeStatus === 'retest') echo 'bg-info text-dark';
    elseif ($tradeStatus === 'bottom3_bounce') echo 'bg-primary text-white';
    elseif ($tradeStatus === 'extended') echo 'bg-warning text-dark';
    elseif ($tradeStatus === 'overextended') echo 'bg-danger text-white';
    elseif ($tradeStatus === 'approaching') echo 'bg-info text-white';
    else echo 'bg-secondary text-white';
?>">
    <div class="card-body py-3 px-4 d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="text-uppercase small fw-bold" style="letter-spacing: 0.05rem;">Sinyal Eksekusi <?= Html::encode($patternName) ?></span>
                <?php if ($isIhs): ?>
                    <span class="badge" style="background-color: rgba(255,255,255,0.3); font-size: 0.75rem;"><?= \app\helpers\SvgHelper::icon('person-arms-up') ?> Inverse H&amp;S Reversal</span>
                <?php else: ?>
                    <span class="badge" style="background-color: rgba(255,255,255,0.3); font-size: 0.75rem;"><?= \app\helpers\SvgHelper::icon('triangle-half') ?> Triple Bottom Reversal</span>
                <?php endif; ?>
            </div>
            <h4 class="mb-0 fw-bold">
                <?php if ($tradeStatus === 'buy_zone'): ?>
                    🎯 Golden Breakout Buy Zone (0% - +3% dari Neckline)
                <?php elseif ($tradeStatus === 'retest'): ?>
                    🔄 Retest Neckline (Peluang Beli Reversal Sangat Kuat)
                <?php elseif ($tradeStatus === 'bottom3_bounce'): ?>
                    ⚡ Agresif Buy Zone (<?= $isIhs ? 'Memantul dari Bahu Kanan' : 'Memantul dari Lembah ke-3' ?>)
                <?php elseif ($tradeStatus === 'extended'): ?>
                    ⚠️ Harga Extended (+3% - +6%) — Amankan Sebagian atau Beli Terukur
                <?php elseif ($tradeStatus === 'overextended'): ?>
                    ⛔ Waktu Jual / Amankan Profit (> +6%) — JANGAN KEJAR BELI!
                <?php elseif ($tradeStatus === 'approaching'): ?>
                    👀 Menjelang Breakout (Pantau Penembusan Resistance)
                <?php else: ?>
                    ⏳ Pola Sedang Terbentuk (Menunggu Konfirmasi)
                <?php endif; ?>
            </h4>
        </div>
        <div class="text-end">
            <span class="fs-4 fw-bold"><?= $pattern['confidence'] ?>%</span>
            <div class="small">Confidence Score</div>
        </div>
    </div>
</div>

<!-- DEDICATED CARD: DETEKSI & PENANGANAN RISIKO FALSE BREAKOUT -->
<div class="card shadow-sm border-0 mb-4 <?php
    if ($isBullTrap || $riskLevel === 'HIGH') echo 'border-start border-danger border-4';
    elseif ($riskLevel === 'MEDIUM') echo 'border-start border-warning border-4';
    else echo 'border-start border-success border-4';
?>">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">
            <?= \app\helpers\SvgHelper::icon('shield-exclamation', ['class' => 'text-danger']) ?> Deteksi &amp; Penanganan Risiko False Breakout (Bull Trap Protection)
        </h5>
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted">Skor Risiko:</span>
            <span class="badge <?= $riskLevel === 'HIGH' ? 'bg-danger' : ($riskLevel === 'MEDIUM' ? 'bg-warning text-dark' : 'bg-success') ?> fs-6">
                <?= $riskScore ?> / 100 (<?= $riskLevel === 'HIGH' ? 'TINGGI' : ($riskLevel === 'MEDIUM' ? 'SEDANG' : 'RENDAH') ?>)
            </span>
        </div>
    </div>
    <div class="card-body">
        <?php if ($isBullTrap): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3">
                <?= \app\helpers\SvgHelper::icon('exclamation-octagon-fill', ['class' => 'fs-3 me-3 text-danger']) ?>
                <div>
                    <strong class="d-block">⚠️ PERINGATAN: TERDETEKSI BULL TRAP (FALSE BREAKOUT)!</strong>
                    Harga sempat menembus garis Neckline ($<?= number_format($neckline, 2) ?>), namun kehilangan tenaga beli dan ditutup kembali di bawah neckline. Segera terapkan protokol mitigasi di bawah!
                </div>
            </div>
        <?php elseif ($riskLevel === 'HIGH'): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3">
                <?= \app\helpers\SvgHelper::icon('exclamation-triangle-fill', ['class' => 'fs-3 me-3 text-danger']) ?>
                <div>
                    <strong>🔴 RISIKO FALSE BREAKOUT TINGGI:</strong> Terdapat indikasi penolakan harga atau kelemahan volume di area resistance neckline. Jangan terburu-buru masuk.
                </div>
            </div>
        <?php elseif ($riskLevel === 'MEDIUM'): ?>
            <div class="alert alert-warning d-flex align-items-center mb-3">
                <?= \app\helpers\SvgHelper::icon('info-circle-fill', ['class' => 'fs-3 me-3 text-warning']) ?>
                <div>
                    <strong>🟡 RISIKO SEDANG:</strong> Penembusan memerlukan konfirmasi lilin berikutnya atau retest ke garis Neckline sebelum menambah posisi penuh.
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success d-flex align-items-center mb-3">
                <?= \app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'fs-3 me-3 text-success']) ?>
                <div>
                    <strong>🟢 RISIKO FALSE BREAKOUT RENDAH (BREAKOUT SOLID):</strong> Struktur harga stabil, penolakan atas rendah, dan volume mendukung momentum kelanjutan tren naik.
                </div>
            </div>
        <?php endif; ?>

        <!-- Parameter Diagnosa Risiko -->
        <div class="row g-3 mb-3">
            <div class="col-md-3 col-6">
                <div class="p-2 border rounded bg-light">
                    <small class="text-muted d-block">Status Bull Trap</small>
                    <strong class="<?= $isBullTrap ? 'text-danger' : 'text-success' ?>">
                        <?= $isBullTrap ? '⚠️ Terjadi Bull Trap' : '✓ Tidak Ada Bull Trap' ?>
                    </strong>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-2 border rounded bg-light">
                    <small class="text-muted d-block">Upper Wick Rejection</small>
                    <strong class="<?= $hasUpperWick ? 'text-danger' : 'text-success' ?>">
                        <?= $upperWickPct ?>% <?= $hasUpperWick ? '(Tekanan Jual Masif)' : '(Wajar)' ?>
                    </strong>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-2 border rounded bg-light">
                    <small class="text-muted d-block">Volume Breakout (RVOL)</small>
                    <strong class="<?= $lowVolBreakout ? 'text-danger' : ($rvol >= 1.3 ? 'text-success' : 'text-dark') ?>">
                        <?= number_format($rvol, 1) ?>x <?= $lowVolBreakout ? '(Rendah / Kurang Vol)' : ($rvol >= 1.3 ? '(Institusi Aktif)' : '(Normal)') ?>
                    </strong>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-2 border rounded bg-light">
                    <small class="text-muted d-block">Ekstensi dari Neckline</small>
                    <strong class="<?= $distPct > 6.0 ? 'text-danger' : ($distPct >= 0 ? 'text-success' : 'text-muted') ?>">
                        <?= ($distPct >= 0 ? '+' : '') . number_format($distPct, 1) ?>% <?= $distPct > 6.0 ? '(Overextended)' : '' ?>
                    </strong>
                </div>
            </div>
        </div>

        <!-- Alasan & Panduan Mitigasi -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="p-3 border rounded h-100 bg-white">
                    <h6 class="fw-bold text-dark mb-2"><?= \app\helpers\SvgHelper::icon('search', ['class' => 'text-danger']) ?> Temuan Analisis Risiko:</h6>
                    <ul class="mb-0 ps-3 small text-muted">
                        <?php foreach ($riskReasons as $reason): ?>
                            <li class="mb-1"><?= Html::encode($reason) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 border rounded h-100 bg-light">
                    <h6 class="fw-bold text-success mb-2"><?= \app\helpers\SvgHelper::icon('shield-check', ['class' => 'text-success']) ?> Protokol &amp; Strategi Mitigasi Trader:</h6>
                    <ul class="mb-0 ps-3 small text-dark">
                        <?php foreach ($mitigationStrategies as $strategy): ?>
                            <li class="mb-1"><strong><?= Html::encode($strategy) ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Panel Kiri: Level Harga & Multi-Target Jual/Beli -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold"><?= \app\helpers\SvgHelper::icon('layers-fill', ['class' => 'text-primary']) ?> Level Kunci Trading &amp; Anatomi Pola</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        <tr>
                            <td class="ps-3 text-muted">Tipe &amp; Rentang Pola</td>
                            <td class="text-end pe-3 fw-bold">
                                <?php if ($isIhs): ?>
                                    <span class="badge" style="background-color: #6f42c1;">Inverted Head &amp; Shoulders</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Triple Bottom</span>
                                <?php endif; ?>
                                <small class="text-muted d-block"><?= $pattern['total_span'] ?? 0 ?> bars total (L1→L2: <?= $pattern['span_12'] ?? 0 ?>, L2→L3: <?= $pattern['span_23'] ?? 0 ?>)</small>
                            </td>
                        </tr>
                        <?php if ($isIhs): ?>
                            <tr>
                                <td class="ps-3 text-muted">Bahu Kiri (Left Shoulder)</td>
                                <td class="text-end pe-3">
                                    <strong>$<?= number_format($pattern['low1_price'], 2) ?></strong>
                                    <small class="text-muted d-block"><?= substr((string)$pattern['low1_date'], 0, 10) ?></small>
                                </td>
                            </tr>
                            <tr class="table-light">
                                <td class="ps-3 fw-bold text-dark">Kepala Terendah (Head)</td>
                                <td class="text-end pe-3">
                                    <strong class="text-primary fs-6">$<?= number_format($pattern['low2_price'], 2) ?></strong>
                                    <span class="badge bg-primary ms-1">-<?= $pattern['head_drop_left_pct'] ?? 0 ?>% vs Bahu</span>
                                    <small class="text-muted d-block"><?= substr((string)$pattern['low2_date'], 0, 10) ?></small>
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-muted">Bahu Kanan (Right Shoulder)</td>
                                <td class="text-end pe-3">
                                    <strong>$<?= number_format($pattern['low3_price'], 2) ?></strong>
                                    <small class="text-muted d-block">Selisih Bahu: <?= $pattern['shoulder_diff_pct'] ?? 0 ?>%</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td class="ps-3 text-muted">Level Lembah 1, 2 &amp; 3</td>
                                <td class="text-end pe-3">
                                    <div><strong>L1: $<?= number_format($pattern['low1_price'], 2) ?></strong> <small class="text-muted">(<?= substr((string)$pattern['low1_date'], 0, 10) ?>)</small></div>
                                    <div><strong>L2: $<?= number_format($pattern['low2_price'], 2) ?></strong> <small class="text-muted">(<?= substr((string)$pattern['low2_date'], 0, 10) ?>)</small></div>
                                    <div><strong>L3: $<?= number_format($pattern['low3_price'], 2) ?></strong> <small class="text-muted">(<?= substr((string)$pattern['low3_date'], 0, 10) ?>)</small></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr class="table-light">
                            <td class="ps-3 fw-bold text-dark">Neckline Resistance (Puncak Konfirmasi)</td>
                            <td class="text-end pe-3 fw-bold fs-6">
                                $<?= number_format($neckline, 2) ?>
                                <div class="small text-muted fw-normal">Zona Beli Breakout: $<?= number_format($pattern['entry_zone_min'] ?? $neckline, 2) ?> - $<?= number_format($pattern['entry_zone_max'] ?? ($neckline * 1.03), 2) ?></div>
                            </td>
                        </tr>
                        <tr class="table-primary">
                            <td class="ps-3 fw-bold">Harga Saat Ini</td>
                            <td class="text-end pe-3 fw-bold fs-5 text-primary">
                                $<?= number_format($currentPrice, 2) ?>
                                <span class="badge <?= $distPct >= 0 ? 'bg-success' : 'bg-secondary' ?> ms-1" style="font-size: 0.75rem;">
                                    <?= ($distPct >= 0 ? '+' : '') . number_format($distPct, 1) ?>% vs Neckline
                                </span>
                            </td>
                        </tr>
                        <!-- Target Jual (Take Profit) -->
                        <tr class="table-success">
                            <td class="ps-3">
                                <strong class="text-success"><?= \app\helpers\SvgHelper::icon('bullseye') ?> Target Jual 1 (TP1 - Amankan 50%)</strong><br>
                                <small class="text-muted">Tutup 30-50% posisi &amp; pasang Trailing Stop</small>
                            </td>
                            <td class="text-end pe-3">
                                <strong class="text-success fs-6">$<?= number_format($tp1, 2) ?></strong>
                                <span class="text-success small fw-bold d-block">+<?= number_format($profitTp1Pct, 1) ?>%</span>
                            </td>
                        </tr>
                        <tr class="table-success">
                            <td class="ps-3">
                                <strong class="text-success"><?= \app\helpers\SvgHelper::icon('flag-fill') ?> Target Jual 2 (TP2 - Target Pola 100%)</strong><br>
                                <small class="text-muted">Target Measured Move ($Neckline + <?= $isIhs ? 'Tinggi Kepala' : 'Tinggi Pola' ?>)</small>
                            </td>
                            <td class="text-end pe-3">
                                <strong class="text-success fs-6">$<?= number_format($tp2, 2) ?></strong>
                                <span class="text-success small fw-bold d-block">+<?= number_format($profitTp2Pct, 1) ?>%</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-3">
                                <strong><?= \app\helpers\SvgHelper::icon('rocket-takeoff') ?> Target Jual 3 (TP3 - Extension 161.8%)</strong><br>
                                <small class="text-muted">Fibonacci Extended Move bila momentum sangat kuat</small>
                            </td>
                            <td class="text-end pe-3">
                                <strong class="fs-6">$<?= number_format($tp3, 2) ?></strong>
                                <small class="text-muted d-block">+<?= number_format($profitTp3Pct, 1) ?>%</small>
                            </td>
                        </tr>
                        <!-- Batas Rugi (Stop Loss / Cut Loss) -->
                        <tr class="table-danger">
                            <td class="ps-3">
                                <strong class="text-danger"><?= \app\helpers\SvgHelper::icon('shield-x') ?> Cut Loss Ketat (Tight Stop Loss)</strong><br>
                                <small class="text-muted">2% di bawah Neckline (Batal bila false breakout)</small>
                            </td>
                            <td class="text-end pe-3">
                                <strong class="text-danger fs-6">$<?= number_format($stopLossTight, 2) ?></strong>
                                <span class="text-danger small fw-bold d-block">-<?= number_format($lossTightPct, 1) ?>%</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-3">
                                <strong class="text-danger"><?= \app\helpers\SvgHelper::icon('shield-slash') ?> <?= $isIhs ? 'Cut Loss Bahu Kanan (Swing SL)' : 'Cut Loss Swing (Wide Stop Loss)' ?></strong><br>
                                <small class="text-muted"><?= $isIhs ? '2% di bawah level Bahu Kanan' : '2% di bawah level terendah Bottom' ?></small>
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
                                <small class="text-muted d-block fw-normal" style="font-size: 0.75rem;">(Berdasarkan Tight SL ke Target TP2)</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Panel Kanan: Rencana Aksi, Panduan Waktu Beli/Jual & Checklist -->
    <div class="col-lg-6">
        <!-- Rencana Aksi Langsung -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold"><?= \app\helpers\SvgHelper::icon('compass-fill', ['class' => 'text-success']) ?> Rencana Eksekusi: Kapan Beli &amp; Jual?</h5>
            </div>
            <div class="card-body">
                <div class="alert <?= in_array($tradeStatus, ['buy_zone', 'retest', 'bottom3_bounce'], true) ? 'alert-success' : ($tradeStatus === 'overextended' ? 'alert-danger' : 'alert-warning') ?> mb-3">
                    <h6 class="fw-bold mb-1"><?= \app\helpers\SvgHelper::icon('clock-history') ?> Rekomendasi Waktu Eksekusi:</h6>
                    <p class="mb-0"><?= Html::encode($pattern['action_description']) ?></p>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <div class="border rounded p-2 bg-light">
                            <span class="small fw-bold text-success d-block"><?= \app\helpers\SvgHelper::icon('cart-check-fill') ?> WAKTU BELI TERBAIK:</span>
                            <ul class="mb-0 ps-3 small text-muted">
                                <li><strong>Breakout:</strong> Saat candle tembus $<?= number_format($neckline, 2) ?> dengan RVOL &gt; 1.3x.</li>
                                <li><strong>Pullback:</strong> Saat harga uji ulang (retest) $<?= number_format($neckline, 2) ?> dan memantul.</li>
                                <li><strong>Area Beli:</strong> $<?= number_format($pattern['entry_zone_min'] ?? $neckline, 2) ?> – $<?= number_format($pattern['entry_zone_max'] ?? ($neckline * 1.03), 2) ?>.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="border rounded p-2 bg-light">
                            <span class="small fw-bold text-danger d-block"><?= \app\helpers\SvgHelper::icon('cash-stack') ?> WAKTU JUAL TERBAIK:</span>
                            <ul class="mb-0 ps-3 small text-muted">
                                <li><strong>TP Parsial:</strong> Jual 50% di $<?= number_format($tp1, 2) ?> (+<?= number_format($profitTp1Pct, 1) ?>%).</li>
                                <li><strong>TP Penuh:</strong> Jual sisa di $<?= number_format($tp2, 2) ?> (+<?= number_format($profitTp2Pct, 1) ?>%).</li>
                                <li><strong>Cut Loss:</strong> Segera jual jika closing &lt; $<?= number_format($stopLossTight, 2) ?>.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Quality Checklist -->
                <h6 class="fw-bold text-dark mb-2">5-Point Reversal Quality Checklist:</h6>
                <ul class="list-group list-group-flush border rounded mb-0 small">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>1. <?= $isIhs ? 'Struktur Inverted H&S (Bahu Kiri, Kepala Terendah, Bahu Kanan)' : 'Tiga Lembah Teruji (3 Support Touches)' ?></span>
                        <span class="badge bg-success">Lulus ✓</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>2. Volume Mengering di Lembah Akhir (Selling Exhaustion)</span>
                        <?php if ($volDryUp): ?>
                            <span class="badge bg-success">Lulus ✓ (Tekanan Jual Habis)</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Netral (Volume Normal)</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>3. Konfirmasi Penembusan Neckline</span>
                        <?php if ($pattern['breakout']): ?>
                            <span class="badge bg-success">Lulus ✓ (Breakout Sukses)</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Belum (Menunggu Penembusan)</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>4. Proteksi False Breakout / RVOL Volume</span>
                        <?php if ($riskLevel === 'LOW'): ?>
                            <span class="badge bg-success">Aman ✓ (Rendah Resiko False Breakout)</span>
                        <?php elseif ($isBullTrap): ?>
                            <span class="badge bg-danger">Waspada ⚠️ (Bull Trap Terdeteksi)</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Perlu Hati-hati (<?= $riskLevel ?>)</span>
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

        <!-- Kalkulator Manajemen Risiko (Position Sizing) -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold"><?= \app\helpers\SvgHelper::icon('calculator', ['class' => 'text-primary']) ?> Kalkulator Alokasi Modal &amp; Ukuran Lembar</h5>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small text-muted">Modal Trading ($)</label>
                        <input type="number" id="tb-calc-capital" class="form-control form-control-sm" value="10000" step="500">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted">Toleransi Risiko (%)</label>
                        <input type="number" id="tb-calc-risk" class="form-control form-control-sm" value="1.5" step="0.5">
                    </div>
                </div>
                <div class="p-3 bg-light rounded small">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Maksimal Risiko Dollar:</span>
                        <strong id="tb-calc-max-risk">$150.00</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Rekomendasi Jumlah Lembar Saham:</span>
                        <strong class="text-primary" id="tb-calc-shares">- lembar</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Estimasi Profit di Target TP2:</span>
                        <strong class="text-success" id="tb-calc-profit">+$0.00</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function recalcTb() {
        const cap = parseFloat(document.getElementById('tb-calc-capital').value) || 0;
        const riskPct = parseFloat(document.getElementById('tb-calc-risk').value) || 0;
        const curPrice = <?= $currentPrice ?>;
        const slPrice = <?= $stopLossTight ?>;
        const tpPrice = <?= $tp2 ?>;
        
        const maxRiskDollar = cap * (riskPct / 100);
        document.getElementById('tb-calc-max-risk').textContent = '$' + maxRiskDollar.toFixed(2);
        
        const riskPerShare = Math.max(curPrice - slPrice, 0.01);
        const shares = Math.floor(maxRiskDollar / riskPerShare);
        document.getElementById('tb-calc-shares').textContent = shares.toLocaleString() + ' shares (~$' + (shares * curPrice).toFixed(0) + ')';
        
        const profit = shares * Math.max(tpPrice - curPrice, 0);
        document.getElementById('tb-calc-profit').textContent = '+$' + profit.toFixed(2);
    }
    document.getElementById('tb-calc-capital')?.addEventListener('input', recalcTb);
    document.getElementById('tb-calc-risk')?.addEventListener('input', recalcTb);
    recalcTb();
});
</script>
