<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var array $results */
/** @var array $allResults */
/** @var int $timeframe */
/** @var int $lookback */
/** @var float $tolerance */
/** @var int $rsiPeriod */
/** @var float $maxRsi */
/** @var string $statusFilter */
/** @var int $minConf */
/** @var string $sortBy */
/** @var array $timeframes */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'RSI Double Bottom Scanner';
$this->params['breadcrumbs'][] = $this->title;

$allPatterns = $allResults ?? $results;
$totalCount = count($allPatterns);
$breakoutCount = count(array_filter($allPatterns, fn($r) => !empty($r['breakout'])));
$divergenceCount = count(array_filter($allPatterns, fn($r) => !empty($r['divergence'])));
$highConfCount = count(array_filter($allPatterns, fn($r) => ($r['confidence'] ?? 0) >= 70));
$readyCount = count(array_filter($allPatterns, fn($r) => in_array($r['action'] ?? '', ['BUY_NOW', 'READY_BREAKOUT', 'NEAR_BREAKOUT'], true)));

$dailyMax = \app\models\DailyPrice::find()->max('date');
$intradayMax = \app\models\IntradayPrice::find()->max('datetime');
$staleDays = $dailyMax ? (int) floor((time() - strtotime($dailyMax . ' 23:59:00')) / 86400) : null;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">
            <i class="bi bi-graph-up-arrow text-primary"></i> <?= Html::encode($this->title) ?>
        </h1>
        <p class="text-muted small mb-0">Deteksi pola pembalikan arah (reversal) pada osilator RSI(14) dengan konfirmasi Neckline Breakout dan Bullish Divergence.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary px-3 py-2 fs-6">
            <i class="bi bi-clock me-1"></i> <?= Html::encode($timeframes[$timeframe] ?? "{$timeframe}H") ?>
        </span>
    </div>
</div>

<?php if ($staleDays !== null && $staleDays >= 3): ?>
    <div class="alert alert-warning py-2 mb-3 d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-warning"></i>
        <div>
            <strong>Peringatan Data Pasar:</strong> Data harian terakhir tanggal <strong><?= Html::encode((string) $dailyMax) ?></strong> (<?= $staleDays ?> hari lalu).
            Jalankan <code>php yii data/refresh-all</code> untuk mendapatkan harga terbaru.
        </div>
    </div>
<?php endif; ?>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-light text-center h-100 py-2">
            <div class="card-body p-2">
                <div class="text-muted small fw-semibold text-uppercase">Total Pola RSI</div>
                <div class="h2 mb-0 fw-bold text-dark"><?= $totalCount ?></div>
                <div class="small text-muted">Timeframe <?= Html::encode($timeframes[$timeframe] ?? '') ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 text-center h-100 py-2 border-start border-success border-4">
            <div class="card-body p-2">
                <div class="text-success small fw-semibold text-uppercase">Breakout Confirmed</div>
                <div class="h2 mb-0 fw-bold text-success"><?= $breakoutCount ?></div>
                <div class="small text-success">RSI &gt; Neckline</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10 text-center h-100 py-2 border-start border-primary border-4">
            <div class="card-body p-2">
                <div class="text-primary small fw-semibold text-uppercase">Bullish Divergence</div>
                <div class="h2 mb-0 fw-bold text-primary"><?= $divergenceCount ?></div>
                <div class="small text-primary">Price Low2 &lt; Low1, RSI2 &gt; RSI1</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10 text-center h-100 py-2 border-start border-warning border-4">
            <div class="card-body p-2">
                <div class="text-warning-emphasis small fw-semibold text-uppercase">Confidence &ge; 70%</div>
                <div class="h2 mb-0 fw-bold text-warning-emphasis"><?= $highConfCount ?></div>
                <div class="small text-muted">Setup Probabilitas Tinggi</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Toolbar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <?= Html::beginForm(['/rsi-double-bottom/index'], 'get', ['id' => 'rsi-filter-form']) ?>
            <!-- Quick Filter Tabs & Controls -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="fw-bold small text-muted me-1">Filter Status:</span>
                    <?php
                    $filters = [
                        'all' => ['label' => 'Semua (' . $totalCount . ')', 'icon' => 'list'],
                        'ready' => ['label' => 'Siap / Breakout (' . $readyCount . ')', 'icon' => 'lightning-charge-fill'],
                        'breakout' => ['label' => 'Breakout (' . $breakoutCount . ')', 'icon' => 'check-circle-fill'],
                        'divergence' => ['label' => 'Divergence (' . $divergenceCount . ')', 'icon' => 'gem'],
                        'high_conf' => ['label' => 'Conf &ge; 70% (' . $highConfCount . ')', 'icon' => 'star-fill'],
                    ];
                    foreach ($filters as $key => $meta):
                        $active = ($statusFilter === $key);
                    ?>
                        <button type="submit" name="statusFilter" value="<?= $key ?>" class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            <i class="bi bi-<?= $meta['icon'] ?>"></i> <?= $meta['label'] ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex align-items-center gap-2 ms-auto">
                    <label class="form-label small fw-bold text-muted mb-0">Urutkan:</label>
                    <?= Html::dropDownList('sortBy', $sortBy, [
                        'confidence' => 'Confidence Tertinggi',
                        'rr' => 'Risk/Reward Tertinggi',
                        'upside' => 'Potential Upside Tertinggi',
                        'current_rsi' => 'RSI Terendah (Oversold)',
                    ], [
                        'class' => 'form-select form-select-sm',
                        'onchange' => 'this.form.submit()',
                        'style' => 'width: 200px;'
                    ]) ?>

                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedParams" aria-expanded="false">
                        <i class="bi bi-sliders"></i> Parameter
                    </button>
                </div>
            </div>

            <!-- Collapsible Advanced Parameters -->
            <div class="collapse mt-3 pt-3 border-top" id="advancedParams">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Timeframe</label>
                        <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()']) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Lookback (candles)</label>
                        <?= Html::input('number', 'lookback', $lookback, ['class' => 'form-control form-control-sm', 'min' => 20, 'max' => 300]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Tolerance (pts RSI)</label>
                        <?= Html::input('number', 'tolerance', $tolerance, ['class' => 'form-control form-control-sm', 'min' => 0.5, 'max' => 10, 'step' => 0.5]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">RSI Period</label>
                        <?= Html::input('number', 'rsiPeriod', $rsiPeriod, ['class' => 'form-control form-control-sm', 'min' => 2, 'max' => 50]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Max RSI Lembah</label>
                        <?= Html::input('number', 'maxRsi', $maxRsi, ['class' => 'form-control form-control-sm', 'min' => 20, 'max' => 70, 'step' => 5]) ?>
                    </div>
                    <div class="col-md-2">
                        <?= Html::submitButton('<i class="bi bi-arrow-repeat"></i> Terapkan', ['class' => 'btn btn-primary btn-sm w-100']) ?>
                    </div>
                </div>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- Scanner Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="bi bi-table text-primary me-1"></i> Hasil Scan RSI Double Bottom
            <span class="badge bg-secondary ms-1"><?= count($results) ?> Saham</span>
        </h5>
        <div class="small text-muted">
            TF: <strong><?= Html::encode($timeframes[$timeframe] ?? '') ?></strong> • Period RSI: <strong><?= $rsiPeriod ?></strong>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th style="width: 100px;">Simbol</th>
                    <th>Nama Saham &amp; Sektor</th>
                    <th style="width: 150px;">Aksi Trading</th>
                    <th class="text-end">Harga Saat Ini</th>
                    <th class="text-end">RSI Lembah</th>
                    <th class="text-end">Neckline RSI</th>
                    <th class="text-end">Current RSI</th>
                    <th class="text-center">Divergence</th>
                    <th class="text-end">Target (TP1)</th>
                    <th class="text-end">Stop Loss</th>
                    <th class="text-end">R:R</th>
                    <th class="text-end">Confidence</th>
                    <th class="text-center" style="width: 120px;">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="13" class="text-center py-5 text-muted">
                            <i class="bi bi-search display-6 text-secondary d-block mb-2"></i>
                            <strong>Tidak ditemukan pola RSI Double Bottom dengan filter saat ini.</strong>
                            <p class="small text-muted mb-0">Coba ubah status filter ke "Semua" atau sesuaikan parameter lookback dan tolerance.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $r): ?>
                        <?php
                        $conf = (int) ($r['confidence'] ?? 0);
                        $confColor = $conf >= 70 ? 'success' : ($conf >= 50 ? 'warning' : 'danger');
                        $actionBadge = $r['action_badge'] ?? 'bg-secondary';
                        $actionLabel = $r['action_label'] ?? 'WAITING';
                        ?>
                        <tr>
                            <td>
                                <strong class="fs-6 text-primary"><?= Html::encode($r['symbol']) ?></strong>
                                <?php if (!empty($r['approximated'])): ?>
                                    <span class="badge bg-light text-muted border d-block" style="font-size: 0.65rem;">approx</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= Html::encode($r['stock_name'] ?? $r['symbol']) ?></div>
                                <div class="small text-muted"><?= Html::encode($r['sector'] ?? 'Unknown Sector') ?></div>
                            </td>
                            <td>
                                <span class="badge <?= $actionBadge ?> px-2 py-1">
                                    <?= Html::encode($actionLabel) ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold">
                                $<?= number_format((float) ($r['current_price'] ?? 0), 2) ?>
                            </td>
                            <td class="text-end small">
                                <span class="badge bg-light text-dark border">
                                    <?= number_format((float) ($r['rsi1_value'] ?? 0), 1) ?> / <?= number_format((float) ($r['rsi2_value'] ?? 0), 1) ?>
                                </span>
                            </td>
                            <td class="text-end small fw-semibold text-danger">
                                <?= number_format((float) ($r['neckline_rsi'] ?? 0), 1) ?>
                            </td>
                            <td class="text-end fw-bold <?= ($r['breakout'] ?? false) ? 'text-success' : 'text-primary' ?>">
                                <?= number_format((float) ($r['current_rsi'] ?? 0), 1) ?>
                                <?php if (!empty($r['breakout'])): ?>
                                    <i class="bi bi-arrow-up-circle-fill text-success small"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($r['divergence'])): ?>
                                    <span class="badge bg-primary text-white" title="Bullish Divergence: Harga lower-low, RSI higher-low">
                                        <i class="bi bi-gem"></i> YES
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-success fw-bold">
                                $<?= number_format((float) ($r['tp1_price'] ?? $r['target_price'] ?? 0), 2) ?>
                                <div class="small text-success fw-normal">+<?= number_format((float) ($r['potential_upside'] ?? 0), 1) ?>%</div>
                            </td>
                            <td class="text-end text-danger small">
                                $<?= number_format((float) ($r['sl_price'] ?? 0), 2) ?>
                                <div class="text-muted" style="font-size: 0.7rem;">-<?= number_format((float) ($r['potential_risk'] ?? 0), 1) ?>%</div>
                            </td>
                            <td class="text-end fw-bold">
                                <?php if (($r['risk_reward'] ?? null) !== null && $r['risk_reward'] > 0): ?>
                                    <span class="<?= $r['risk_reward'] >= 2.0 ? 'text-success' : 'text-dark' ?>">
                                        1:<?= number_format((float) $r['risk_reward'], 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <span class="badge bg-<?= $confColor ?>"><?= $conf ?>%</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <?= Html::a(
                                    '<i class="bi bi-graph-up"></i> Chart',
                                    [
                                        '/rsi-double-bottom/detail',
                                        'symbol' => $r['symbol'],
                                        'timeframe' => $timeframe,
                                        'lookback' => $lookback,
                                        'tolerance' => $tolerance,
                                        'rsiPeriod' => $rsiPeriod,
                                        'maxRsi' => $maxRsi,
                                    ],
                                    ['class' => 'btn btn-sm btn-primary py-0 px-2']
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Strategy Guide Accordion -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="bi bi-info-circle text-primary me-1"></i> Panduan Eksekusi Trading RSI Double Bottom
        </h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <h6 class="fw-bold text-primary"><i class="bi bi-1-circle"></i> Karakteristik Pola</h6>
                <p class="small text-muted mb-0">
                    Pola terbentuk di indikator <strong>RSI(14)</strong> saat RSI mencetak dua lembah di area jenuh jual (RSI &lt; 40) dengan puncak resistance (neckline) di antaranya. Breakout terkonfirmasi ketika Current RSI melintasi ke atas Neckline RSI.
                </p>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-success"><i class="bi bi-2-circle"></i> Keunggulan Bullish Divergence</h6>
                <p class="small text-muted mb-0">
                    Jika harga saham mencetak <em>Lower Low</em> (Lembah 2 lebih rendah dari Lembah 1) namun RSI mencetak <em>Higher Low</em> (RSI2 lebih tinggi dari RSI1), ini menandakan pelemahan momentum jual (bullish divergence). Ini adalah setup pembalikan tren berprobabilitas tertinggi.
                </p>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-danger"><i class="bi bi-3-circle"></i> Manajemen Risiko</h6>
                <p class="small text-muted mb-0">
                    Selalu pasang Stop Loss di bawah harga terendah pola (Swing Low - 2%). Target keuntungan (TP1) dihitung dari proyeksi tinggi pola (Measured Move) 100%, TP2 di Fibonacci 161.8%, dan TP3 di 200%. Minimalisir transaksi jika Risk/Reward di bawah 1:1.5.
                </p>
            </div>
        </div>
    </div>
</div>
