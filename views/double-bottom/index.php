<?php
/** @var yii\web\View $this */
/** @var array $results */
/** @var int $timeframe */
/** @var int $lookback */
/** @var float $tolerance */
/** @var float $tolerancePercent */
/** @var int $minSeparation */
/** @var int $maxSeparation */
/** @var float $necklineMinDepth */
/** @var bool $breakoutOnly */
/** @var int $minConfidence */
/** @var string $statusFilter */
/** @var float $minRr */
/** @var bool $volOnly */
/** @var string $sort */
/** @var array $timeframes */
/** @var array $filterParams */
/** @var array $stats */
/** @var bool $fromCache */
/** @var int $lookbackDays */

use yii\bootstrap5\Html;
use app\services\DoubleBottomService;

$this->title = 'Double Bottom Pattern Scanner';
$this->params['breadcrumbs'][] = $this->title;
$lookbackDays = $lookbackDays ?? $lookback;
$statusFilter = $statusFilter ?? 'all';
$minRr = $minRr ?? 0.0;
$volOnly = !empty($volOnly);

$breakoutCount = count(array_filter($results, fn($r) => !empty($r['breakout'])));
$buyZoneCount = count(array_filter($results, fn($r) => ($r['trade_status'] ?? '') === 'buy_zone'));
$highRrCount = count(array_filter($results, fn($r) => ((float)($r['best_rr'] ?? 0)) >= 2.0));

$stockCount = (int) ($stats['stockCount'] ?? 0);
$priceCount = (int) ($stats['priceCount'] ?? 0);
$intradayCount = (int) ($stats['intradayCount'] ?? 0);
$dailyMax = $stats['dailyMax'] ?? null;
$intradayMaxRaw = $stats['intradayMax'] ?? null;
$intradayMax = is_string($intradayMaxRaw) && $intradayMaxRaw !== '' ? substr($intradayMaxRaw, 0, 16) : '—';
$intradaySyms = (int) ($stats['intradaySyms'] ?? 0);
$realCount = count(array_filter($results, fn($r) => ($r['data_source'] ?? '') === 'intraday'));
$fbCount = count(array_filter($results, fn($r) => ($r['data_source'] ?? '') === 'daily_fallback'));
$isIntradayTf = in_array($timeframe, [1, 2, 4], true);
$staleDays = null;
if (is_string($dailyMax) && $dailyMax !== '') {
    $staleDays = (int) floor((time() - strtotime($dailyMax . ' 23:59:00')) / 86400);
}
$sortLink = fn(string $key, string $label) => Html::a($label . ($sort === $key ? ' ▼' : ''), array_merge(['double-bottom/index'], $filterParams, ['sort' => $key]), ['class' => 'text-decoration-none text-dark fw-bold']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-graph-up-arrow text-primary"></i> <?= Html::encode($this->title) ?></h1>
        <small class="text-muted">Pindai pola pembalikan arah W dengan validasi volume, zona entri optimal, dan kalkulasi Risk/Reward</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($fromCache): ?><span class="badge bg-secondary" title="Hasil dari cache memori 15 menit">Cache 15m</span><?php endif; ?>
        <span class="badge bg-primary px-3 py-2 fs-7"><?= DoubleBottomService::getTimeframeLabel($timeframe) ?></span>
    </div>
</div>

<!-- Form Filter & Parameter -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light py-2">
        <strong class="text-secondary"><i class="bi bi-funnel"></i> Parameter &amp; Filter Trading</strong>
    </div>
    <div class="card-body">
        <?= Html::beginForm(['double-bottom/index'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Timeframe</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Status Transaksi</label>
                <?= Html::dropDownList('statusFilter', $statusFilter, [
                    'all' => 'Semua Status',
                    'buy_zone' => '🟢 In Golden Buy Zone (0-3%)',
                    'breakout' => '🚀 Terkonfirmasi Breakout',
                    'retest' => '🔄 Retest Neckline',
                    'approaching' => '👀 Mendekati Breakout (-3%..0)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Min. Risk/Reward (R:R)</label>
                <?= Html::dropDownList('minRr', $minRr, [
                    '0' => 'Semua R:R',
                    '1.5' => '≥ 1.5 : 1',
                    '2.0' => '≥ 2.0 : 1 (Ideal)',
                    '3.0' => '≥ 3.0 : 1 (Super)',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Urutkan (Sorting)</label>
                <?= Html::dropDownList('sort', $sort, [
                    'confidence' => 'Score Confidence',
                    'rr' => 'Risk/Reward Tertinggi',
                    'distance' => 'Terdekat ke Neckline',
                    'rvol' => 'Lonjakan Volume (RVOL)',
                    'target' => 'Potensi Gain % Target',
                    'symbol' => 'Simbol Saham',
                ], ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Lookback (Candle)</label>
                <?= Html::input('number', 'lookback', $lookback, ['class' => 'form-control form-control-sm', 'min' => 5, 'max' => 500]) ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Toleransi Low (%)</label>
                <?= Html::input('number', 'tolerance', round($tolerancePercent, 1), ['class' => 'form-control form-control-sm', 'min' => 0.1, 'max' => 20, 'step' => 0.1]) ?>
            </div>

            <!-- Advanced Row Toggle -->
            <div class="col-12 mt-2">
                <details>
                    <summary class="small text-muted" style="cursor: pointer;">Pengaturan Lanjutan (Separasi candle, Neckline depth, Min. Conf)</summary>
                    <div class="row g-2 mt-1 pt-2 border-top">
                        <div class="col-md-3">
                            <label class="form-label small">Separasi Min - Maks</label>
                            <div class="input-group input-group-sm">
                                <?= Html::input('number', 'minSeparation', $minSeparation, ['class' => 'form-control', 'min' => 2, 'title' => 'Separasi minimum']) ?>
                                <?= Html::input('number', 'maxSeparation', $maxSeparation, ['class' => 'form-control', 'min' => 2, 'title' => 'Separasi maksimum']) ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Neckline Min Depth (%)</label>
                            <?= Html::input('number', 'necklineMinDepth', round($necklineMinDepth * 100, 1), ['class' => 'form-control form-control-sm', 'min' => 0, 'max' => 50, 'step' => 0.1]) ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Confidence Minimum</label>
                            <?= Html::input('number', 'minConfidence', $minConfidence, ['class' => 'form-control form-control-sm', 'min' => 0, 'max' => 100]) ?>
                        </div>
                    </div>
                </details>
            </div>

            <div class="col-md-8 col-12 d-flex gap-3 align-items-center">
                <div class="form-check form-check-inline mb-0">
                    <?= Html::checkbox('volOnly', $volOnly, ['class' => 'form-check-input', 'id' => 'volOnlyCheck', 'value' => 1]) ?>
                    <label class="form-check-label small fw-bold" for="volOnlyCheck">Hanya dengan Konfirmasi Volume (RVOL ≥ 1.3x atau Dry-Up)</label>
                </div>
            </div>
            <div class="col-md-4 col-12 text-end">
                <?= Html::submitButton('<i class="bi bi-search"></i> Pindai Pasar', ['class' => 'btn btn-primary px-4 shadow-sm w-100']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<?php if ($isIntradayTf && ($fbCount > 0 || $intradaySyms < $stockCount)): ?>
<div class="alert alert-warning py-2 small">
    <strong>Mode approximasi:</strong>
    <?= $fbCount ?> dari <?= count($results) ?> hasil memakai <em>daily fallback</em>
    (intraday tersedia untuk <?= $intradaySyms ?>/<?= $stockCount ?> saham).
</div>
<?php endif; ?>
<?php if ($staleDays !== null && $staleDays >= 3): ?>
<div class="alert alert-danger py-2 small">
    <strong>Perhatian:</strong> Data harian terakhir <?= Html::encode((string) $dailyMax) ?> (<?= $staleDays ?> hari lalu).
</div>
<?php endif; ?>

<!-- KPI Quick Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card h-100 border-0 shadow-sm bg-light">
            <div class="card-body p-3 text-center">
                <div class="text-muted small fw-bold text-uppercase">Pola Ditemukan</div>
                <div class="h2 mb-0 text-primary fw-bold"><?= count($results) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 border-0 shadow-sm bg-light">
            <div class="card-body p-3 text-center">
                <div class="text-muted small fw-bold text-uppercase">Golden Buy Zone (0-3%)</div>
                <div class="h2 mb-0 text-success fw-bold"><?= $buyZoneCount ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 border-0 shadow-sm bg-light">
            <div class="card-body p-3 text-center">
                <div class="text-muted small fw-bold text-uppercase">Breakout Terkonfirmasi</div>
                <div class="h2 mb-0 text-info fw-bold"><?= $breakoutCount ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 border-0 shadow-sm bg-light">
            <div class="card-body p-3 text-center">
                <div class="text-muted small fw-bold text-uppercase">High R:R (≥ 2.0x)</div>
                <div class="h2 mb-0 text-warning fw-bold"><?= $highRrCount ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Hasil Scanner Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Daftar Peluang Saham Terdeteksi</h5>
        <span class="text-muted small">Menampilkan <?= count($results) ?> saham</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 text-nowrap">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">#</th>
                        <th><?= $sortLink('symbol', 'Saham') ?></th>
                        <th class="text-center">Rekomendasi</th>
                        <th>Pola W</th>
                        <th class="text-end">Neckline (Entry)</th>
                        <th class="text-end">Saat Ini</th>
                        <th class="text-center"><?= $sortLink('distance', 'Jarak Neckline') ?></th>
                        <th class="text-end">Target TP1 / TP2</th>
                        <th class="text-end">Stop Loss</th>
                        <th class="text-center"><?= $sortLink('rr', 'R:R Ratio') ?></th>
                        <th class="text-center"><?= $sortLink('rvol', 'Volume') ?></th>
                        <th class="text-end pe-3"><?= $sortLink('confidence', 'Conf.') ?></th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="13" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Tidak ada pola double bottom yang cocok dengan kriteria filter saat ini.<br>
                            <small>Coba longgarkan toleransi atau pilih timeframe lain.</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $i => $r): ?>
                    <?php
                        $toTarget = $r['current_price'] > 0 ? ($r['target_price'] - $r['current_price']) / $r['current_price'] * 100 : 0;
                        $dist = (float) ($r['distance_neckline_pct'] ?? 0);
                        $bestRr = (float) ($r['best_rr'] ?? 0);
                        $tradeStatus = $r['trade_status'] ?? ($r['breakout'] ? 'breakout' : 'forming');
                        $rvol = (float) ($r['rvol'] ?? 1.0);
                        $tightSl = (float) ($r['stop_loss_tight'] ?? ($r['neckline'] * 0.98));
                        $slRiskPct = $r['current_price'] > 0 ? abs(($r['current_price'] - $tightSl) / $r['current_price'] * 100) : 0;
                    ?>
                    <tr>
                        <td class="ps-3 text-muted"><?= $i + 1 ?></td>
                        <td>
                            <strong class="fs-6"><?= Html::encode($r['symbol']) ?></strong>
                            <div class="small text-muted" style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= Html::encode($r['stock_name']) ?>
                            </div>
                            <span class="badge bg-secondary text-white" style="font-size: 0.65rem;"><?= Html::encode($r['sector'] ?? 'Stock') ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($tradeStatus === 'buy_zone'): ?>
                                <span class="badge bg-success px-2 py-1">BUY (Golden Zone)</span>
                            <?php elseif ($tradeStatus === 'retest'): ?>
                                <span class="badge bg-info text-dark px-2 py-1">BUY (Retest)</span>
                            <?php elseif ($tradeStatus === 'extended'): ?>
                                <span class="badge bg-warning text-dark px-2 py-1">HATI-HATI (Extended)</span>
                            <?php elseif ($tradeStatus === 'overextended'): ?>
                                <span class="badge bg-danger px-2 py-1">TUNGGU PULLBACK</span>
                            <?php elseif ($tradeStatus === 'approaching'): ?>
                                <span class="badge bg-primary px-2 py-1">PANTAU BREAKOUT</span>
                            <?php else: ?>
                                <span class="badge bg-secondary px-2 py-1">WAITING</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="small fw-bold">
                                <?php if (($r['pattern_type'] ?? '') === 'Higher Low'): ?>
                                    <span class="text-success">Higher Low 🚀</span>
                                <?php elseif (($r['pattern_type'] ?? '') === 'Lower Low'): ?>
                                    <span class="text-secondary">Shakeout Low</span>
                                <?php else: ?>
                                    <span class="text-primary">Twin Low</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                L1: <?= number_format($r['low1_price'], 2) ?> | L2: <?= number_format($r['low2_price'], 2) ?>
                            </small>
                        </td>
                        <td class="text-end">
                            <strong class="text-dark">$<?= number_format($r['neckline'], 2) ?></strong>
                            <div class="small text-muted">Zone: $<?= number_format($r['entry_zone_min'] ?? $r['neckline'], 2) ?> - $<?= number_format($r['entry_zone_max'] ?? ($r['neckline'] * 1.025), 2) ?></div>
                        </td>
                        <td class="text-end">
                            <strong class="text-primary">$<?= number_format($r['current_price'], 2) ?></strong>
                        </td>
                        <td class="text-center">
                            <?php if ($dist >= 0 && $dist <= 3.0): ?>
                                <span class="badge bg-success text-white">+<?= number_format($dist, 1) ?>%</span>
                            <?php elseif ($dist > 3.0): ?>
                                <span class="badge bg-warning text-dark">+<?= number_format($dist, 1) ?>%</span>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border"><?= number_format($dist, 1) ?>%</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="text-success fw-bold">TP1: $<?= number_format($r['tp1_price'] ?? $r['target_price'], 2) ?></div>
                            <div class="small text-muted">TP2: $<?= number_format($r['tp2_price'] ?? $r['target_price'], 2) ?> (+<?= number_format($toTarget, 1) ?>%)</div>
                        </td>
                        <td class="text-end">
                            <strong class="text-danger">$<?= number_format($tightSl, 2) ?></strong>
                            <div class="small text-muted">-<?= number_format($slRiskPct, 1) ?>%</div>
                        </td>
                        <td class="text-center">
                            <?php if ($bestRr >= 2.0): ?>
                                <span class="badge bg-success px-2 py-1 fs-7"><?= number_format($bestRr, 1) ?>x</span>
                            <?php elseif ($bestRr >= 1.5): ?>
                                <span class="badge bg-warning text-dark px-2 py-1 fs-7"><?= number_format($bestRr, 1) ?>x</span>
                            <?php elseif ($bestRr > 0): ?>
                                <span class="badge bg-light text-dark border px-2 py-1"><?= number_format($bestRr, 1) ?>x</span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($r['volume_confirmed']) || $rvol >= 1.3): ?>
                                <span class="badge bg-success" title="Relative Volume: <?= $rvol ?>x">🔥 RVOL <?= number_format($rvol, 1) ?>x</span>
                            <?php elseif (!empty($r['volume_dry_up'])): ?>
                                <span class="badge bg-info text-dark" title="Volume Low 2 Mengering">💧 Dry-Up</span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">RVOL <?= number_format($rvol, 1) ?>x</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <?php $cc = $r['confidence'] >= 75 ? 'text-success' : ($r['confidence'] >= 55 ? 'text-warning' : 'text-danger'); ?>
                            <span class="<?= $cc ?> fw-bold fs-6"><?= $r['confidence'] ?>%</span>
                        </td>
                        <td class="text-end pe-3">
                            <?= Html::a('<i class="bi bi-bar-chart-fill"></i> Detail', array_merge(['double-bottom/detail', 'symbol' => $r['symbol']], $filterParams), ['class' => 'btn btn-sm btn-primary shadow-sm']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Panduan Cara Menggunakan Informasi untuk Transaksi Saham yang Baik -->
<div class="card shadow-sm mt-4 border-0">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-bold"><i class="bi bi-lightbulb-fill text-warning"></i> Panduan Eksekusi Trading Double Bottom untuk Hasil Maksimal</h5>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-4">
                <h6 class="fw-bold text-success"><i class="bi bi-check-circle-fill"></i> 1. Kapan Harus Masuk Posisi (Entry)?</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Golden Buy Zone:</strong> Masuk saat harga berada di antara <code>Neckline</code> s/d <code>Neckline + 2.5%</code> sesaat setelah breakout terkonfirmasi.</li>
                    <li><strong>Retest Entry:</strong> Jika harga sudah terlanjur naik tinggi, JANGAN KEJAR! Tunggu harga kembali menguji neckline dengan volume mengecil.</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-danger"><i class="bi bi-shield-fill-x"></i> 2. Disiplin Stop Loss</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Tight Stop Loss (2% di bawah Neckline):</strong> Jika harga berbalik turun menembus ke bawah neckline, itu adalah <em>False Breakout</em>. Batasi risiko dan keluar dengan kerugian minimal.</li>
                    <li>Hindari membiarkan floating loss melewati level support Low 1 &amp; Low 2.</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-primary"><i class="bi bi-cash-stack"></i> 3. Ambil Keuntungan Bertahap (Scaling Out)</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>TP1 (Conservative):</strong> Jual 50% lot saat mencapai TP1, lalu segera pindahkan Stop Loss ke titik impas (Breakeven) untuk <em>Free Trade</em>.</li>
                    <li><strong>TP2 (Full Measured Move):</strong> Likuidasi sisa posisi di target teknikal penuh.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

