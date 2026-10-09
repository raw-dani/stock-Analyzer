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

$this->title = 'RSI Strategy & Signal Scanner';
$this->params['breadcrumbs'][] = $this->title;

$allPatterns = $allResults ?? $results;
$totalCount = count($allPatterns);
$buyCount = count(array_filter($allPatterns, fn($r) => in_array($r['action'] ?? '', ['BUY_NOW', 'BUY_PULLBACK', 'MOMENTUM_BUY'], true)));
$sellCount = count(array_filter($allPatterns, fn($r) => in_array($r['action'] ?? '', ['SELL_NOW', 'TAKE_PROFIT'], true)));
$bullDivCount = count(array_filter($allPatterns, fn($r) => !empty($r['divergence'])));
$bearDivCount = count(array_filter($allPatterns, fn($r) => !empty($r['bearish_divergence'])));
$oversoldWatchCount = count(array_filter($allPatterns, fn($r) => ($r['current_rsi'] ?? 50) <= 35));
$overboughtWatchCount = count(array_filter($allPatterns, fn($r) => ($r['current_rsi'] ?? 50) >= 65));

$dailyMax = \app\models\DailyPrice::find()->max('date');
$staleDays = $dailyMax ? (int) floor((time() - strtotime($dailyMax . ' 23:59:00')) / 86400) : null;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">
            <i class="bi bi-activity text-primary"></i> <?= Html::encode($this->title) ?>
        </h1>
        <p class="text-muted small mb-0">
            Sistem analisis RSI modern untuk menentukan <strong>Waktu Beli (Entry)</strong> dan <strong>Waktu Jual (Exit/Take Profit)</strong> optimal berbasis Oversold Reversal, Bullish/Bearish Divergence, Trend Filter MA50, serta Level Risk-Reward presisi.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="btn-group shadow-sm" role="group" aria-label="Timeframe Selector">
            <?php foreach ([24 => '1D (Daily)', 4 => '4H', 2 => '2H', 1 => '1H'] as $tfVal => $tfLabel): ?>
                <?= Html::a(
                    Html::encode($tfLabel),
                    [
                        '/rsi-double-bottom/index',
                        'timeframe' => $tfVal,
                        'statusFilter' => $statusFilter,
                        'sortBy' => $sortBy,
                        'rsiPeriod' => $rsiPeriod,
                        'lookback' => $lookback,
                        'maxRsi' => $maxRsi,
                    ],
                    [
                        'class' => 'btn btn-sm ' . ($timeframe === $tfVal ? 'btn-primary active fw-bold' : 'btn-outline-secondary bg-white'),
                    ]
                ) ?>
            <?php endforeach; ?>
        </div>
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
        <div class="card border-0 shadow-sm bg-success bg-opacity-10 text-center h-100 py-2 border-start border-success border-4">
            <div class="card-body p-2">
                <div class="text-success small fw-semibold text-uppercase">
                    <i class="bi bi-cart-check-fill me-1"></i> Sinyal Waktu Beli
                </div>
                <div class="h2 mb-0 fw-bold text-success"><?= $buyCount ?></div>
                <div class="small text-success">Reversal Bounce / Bullish Div</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-danger bg-opacity-10 text-center h-100 py-2 border-start border-danger border-4">
            <div class="card-body p-2">
                <div class="text-danger small fw-semibold text-uppercase">
                    <i class="bi bi-box-arrow-right me-1"></i> Sinyal Waktu Jual / TP
                </div>
                <div class="h2 mb-0 fw-bold text-danger"><?= $sellCount ?></div>
                <div class="small text-danger">Overbought Exit / Bearish Div</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10 text-center h-100 py-2 border-start border-primary border-4">
            <div class="card-body p-2">
                <div class="text-primary small fw-semibold text-uppercase">
                    <i class="bi bi-gem me-1"></i> Bullish Divergence
                </div>
                <div class="h2 mb-0 fw-bold text-primary"><?= $bullDivCount ?></div>
                <div class="small text-primary">Pelemahan Tekanan Jual</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10 text-center h-100 py-2 border-start border-warning border-4">
            <div class="card-body p-2">
                <div class="text-warning-emphasis small fw-semibold text-uppercase">
                    <i class="bi bi-eye-fill me-1"></i> Area Jenuh (Watchlist)
                </div>
                <div class="h2 mb-0 fw-bold text-warning-emphasis"><?= $oversoldWatchCount + $overboughtWatchCount ?></div>
                <div class="small text-muted">Oversold (<?= $oversoldWatchCount ?>) / Overbought (<?= $overboughtWatchCount ?>)</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Toolbar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <?= Html::beginForm(['/rsi-double-bottom/index'], 'get', ['id' => 'rsi-filter-form']) ?>
            <?= Html::hiddenInput('timeframe', $timeframe, ['id' => 'filter-timeframe-input']) ?>
            <?= Html::hiddenInput('statusFilter', $statusFilter, ['id' => 'filter-status-input']) ?>

            <!-- Quick Filter Bar: Timeframe & Status & Sorter -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <!-- Timeframe Pills -->
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold small text-muted"><i class="bi bi-clock me-1"></i>Timeframe:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <?php foreach ([24 => '1D', 4 => '4H', 2 => '2H', 1 => '1H'] as $tfVal => $tfLabel): ?>
                            <button type="button" 
                                    class="btn <?= $timeframe === $tfVal ? 'btn-dark active fw-bold' : 'btn-outline-secondary' ?>"
                                    onclick="document.getElementById('filter-timeframe-input').value = '<?= $tfVal ?>'; document.getElementById('rsi-filter-form').submit();">
                                <?= $tfLabel ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sorting & Parameter Collapse Toggle -->
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <label class="form-label small fw-bold text-muted mb-0">Urutkan:</label>
                    <?= Html::dropDownList('sortBy', $sortBy, [
                        'confidence' => 'Confidence Tertinggi',
                        'rr' => 'Risk/Reward Tertinggi',
                        'upside' => 'Potential Upside Tertinggi',
                        'current_rsi' => 'RSI Terendah (Oversold)',
                        'rsi_high' => 'RSI Tertinggi (Overbought)',
                    ], [
                        'class' => 'form-select form-select-sm',
                        'onchange' => 'this.form.submit()',
                        'style' => 'width: 210px;'
                    ]) ?>

                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedParams" aria-expanded="false">
                        <i class="bi bi-sliders"></i> Parameter
                    </button>
                </div>
            </div>

            <!-- Signal Category Filter Tabs -->
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
                <span class="fw-bold small text-muted me-1">Sinyal / Kategori:</span>
                <?php
                $filters = [
                    'all' => ['label' => 'Semua (' . $totalCount . ')', 'icon' => 'list', 'class' => 'btn-outline-secondary'],
                    'buy' => ['label' => 'Waktu Beli (' . $buyCount . ')', 'icon' => 'cart-plus-fill', 'class' => 'btn-outline-success'],
                    'sell' => ['label' => 'Waktu Jual / TP (' . $sellCount . ')', 'icon' => 'tag-fill', 'class' => 'btn-outline-danger'],
                    'bull_div' => ['label' => 'Bullish Div (' . $bullDivCount . ')', 'icon' => 'gem', 'class' => 'btn-outline-primary'],
                    'bear_div' => ['label' => 'Bearish Div (' . $bearDivCount . ')', 'icon' => 'lightning-fill', 'class' => 'btn-outline-danger'],
                    'oversold' => ['label' => 'Oversold &le; 35 (' . $oversoldWatchCount . ')', 'icon' => 'arrow-down-circle', 'class' => 'btn-outline-info'],
                    'overbought' => ['label' => 'Overbought &ge; 65 (' . $overboughtWatchCount . ')', 'icon' => 'arrow-up-circle', 'class' => 'btn-outline-warning'],
                ];
                foreach ($filters as $key => $meta):
                    $active = ($statusFilter === $key);
                ?>
                    <button type="button" 
                            class="btn btn-sm <?= $active ? 'btn-primary active' : $meta['class'] ?>"
                            onclick="document.getElementById('filter-status-input').value = '<?= $key ?>'; document.getElementById('rsi-filter-form').submit();">
                        <i class="bi bi-<?= $meta['icon'] ?>"></i> <?= $meta['label'] ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Collapsible Advanced Parameters -->
            <div class="collapse mt-3 pt-3 border-top" id="advancedParams">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Timeframe</label>
                        <select class="form-select form-select-sm" onchange="document.getElementById('filter-timeframe-input').value = this.value; document.getElementById('rsi-filter-form').submit();">
                            <?php foreach ($timeframes as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= $timeframe === $val ? 'selected' : '' ?>><?= Html::encode($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">RSI Period</label>
                        <?= Html::input('number', 'rsiPeriod', $rsiPeriod, ['class' => 'form-control form-control-sm', 'min' => 2, 'max' => 50]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Lookback Candles</label>
                        <?= Html::input('number', 'lookback', $lookback, ['class' => 'form-control form-control-sm', 'min' => 20, 'max' => 300]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Ambang Lembah Max</label>
                        <?= Html::input('number', 'maxRsi', $maxRsi, ['class' => 'form-control form-control-sm', 'min' => 20, 'max' => 70, 'step' => 5]) ?>
                    </div>
                    <div class="col-md-3">
                        <?= Html::submitButton('<i class="bi bi-arrow-repeat"></i> Terapkan Pengaturan', ['class' => 'btn btn-primary btn-sm w-100']) ?>
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
            <i class="bi bi-table text-primary me-1"></i> Rekomendasi Sinyal RSI &amp; Level Eksekusi
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
                    <th style="width: 90px;">Simbol</th>
                    <th>Nama Saham &amp; Sektor</th>
                    <th style="width: 220px;">Rekomendasi Aksi</th>
                    <th class="text-end">Harga Saat Ini</th>
                    <th class="text-center" style="width: 130px;">RSI (<?= $rsiPeriod ?>)</th>
                    <th class="text-center">Trend (MA50)</th>
                    <th class="text-center">Divergence</th>
                    <th class="text-end">Target Profit</th>
                    <th class="text-end">Stop Loss</th>
                    <th class="text-end">R:R</th>
                    <th class="text-end">Confidence</th>
                    <th class="text-center" style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="12" class="text-center py-5 text-muted">
                            <i class="bi bi-search display-6 text-secondary d-block mb-2"></i>
                            <strong>Tidak ditemukan saham dengan kriteria filter saat ini.</strong>
                            <p class="small text-muted mb-0">Coba pilih tab filter "Semua" atau gunakan timeframe Daily.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $r): ?>
                        <?php
                        $conf = (int) ($r['confidence'] ?? 0);
                        $confColor = $conf >= 80 ? 'success' : ($conf >= 65 ? 'warning' : 'secondary');
                        $actionBadge = $r['action_badge'] ?? 'bg-secondary';
                        $actionLabel = $r['action_label'] ?? 'WAIT';
                        $rsiVal = (float) ($r['current_rsi'] ?? 50);
                        $rsiColor = $rsiVal <= 30 ? 'text-success fw-bold' : ($rsiVal >= 70 ? 'text-danger fw-bold' : ($rsiVal < 45 ? 'text-info' : 'text-dark'));
                        $isBuyAction = in_array($r['action'] ?? '', ['BUY_NOW', 'BUY_PULLBACK', 'MOMENTUM_BUY'], true);
                        $isSellAction = in_array($r['action'] ?? '', ['SELL_NOW', 'TAKE_PROFIT'], true);
                        ?>
                        <tr class="<?= $isBuyAction ? 'table-success bg-opacity-10' : ($isSellAction ? 'table-danger bg-opacity-10' : '') ?>">
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
                                <span class="badge <?= $actionBadge ?> px-2 py-1 mb-1 d-inline-block">
                                    <?= Html::encode($actionLabel) ?>
                                </span>
                                <div class="small text-muted" style="font-size: 0.72rem; line-height: 1.2;">
                                    <?= Html::encode($r['signal_reason'] ?? '') ?>
                                </div>
                            </td>
                            <td class="text-end fw-bold">
                                $<?= number_format((float) ($r['current_price'] ?? 0), 2) ?>
                            </td>
                            <td class="text-center">
                                <span class="fs-6 <?= $rsiColor ?>">
                                    <?= number_format($rsiVal, 1) ?>
                                </span>
                                <?php if ($rsiVal <= 30): ?>
                                    <span class="badge bg-success-subtle text-success border border-success d-block mx-auto mt-1" style="font-size: 0.65rem; width: fit-content;">OVERSOLD</span>
                                <?php elseif ($rsiVal >= 70): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger d-block mx-auto mt-1" style="font-size: 0.65rem; width: fit-content;">OVERBOUGHT</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($r['is_above_ma50'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success" title="Harga di atas MA50 ($<?= number_format((float)($r['ma50'] ?? 0), 2) ?>)">
                                        <i class="bi bi-arrow-up-circle"></i> Uptrend
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border" title="Harga di bawah MA50 ($<?= number_format((float)($r['ma50'] ?? 0), 2) ?>)">
                                        <i class="bi bi-arrow-down-circle"></i> Downtrend
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($r['divergence'])): ?>
                                    <span class="badge bg-primary text-white" title="Bullish Divergence: Peluang pembalikan naik">
                                        <i class="bi bi-gem"></i> BULLISH
                                    </span>
                                <?php elseif (!empty($r['bearish_divergence'])): ?>
                                    <span class="badge bg-danger text-white" title="Bearish Divergence: Waspada pembalikan turun">
                                        <i class="bi bi-lightning-charge"></i> BEARISH
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
                                <span class="badge bg-<?= $confColor ?>"><?= $conf ?>%</span>
                            </td>
                            <td class="text-center">
                                <?= Html::a(
                                    '<i class="bi bi-graph-up"></i> Detail',
                                    [
                                        '/rsi-double-bottom/detail',
                                        'symbol' => $r['symbol'],
                                        'timeframe' => $timeframe,
                                        'statusFilter' => $statusFilter,
                                        'sortBy' => $sortBy,
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
            <i class="bi bi-book-half text-primary me-1"></i> Pedoman Rekomendasi Waktu Beli &amp; Waktu Jual Berbasis RSI
        </h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <h6 class="fw-bold text-success"><i class="bi bi-cart-check"></i> 1. Kapan Waktu Beli Terbaik?</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Oversold Reversal (Cross 30 UP):</strong> RSI sempat berada di bawah 30 lalu memantul melintas ke atas level 30.</li>
                    <li><strong>Bullish Divergence:</strong> Harga saham membuat <em>Lower Low</em> tetapi RSI membentuk <em>Higher Low</em>.</li>
                    <li><strong>Uptrend Pullback:</strong> Saham berada di atas MA50 dan RSI menguji support 40-50 lalu berbalik naik.</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-danger"><i class="bi bi-box-arrow-right"></i> 2. Kapan Waktu Jual / TP Terbaik?</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Overbought Exit (Cross 70 DOWN):</strong> RSI telah melampaui level 70 dan mulai melintas turun di bawah 70.</li>
                    <li><strong>Bearish Divergence:</strong> Harga menembus rekor harga baru (<em>Higher High</em>) tetapi RSI gagal naik (<em>Lower High</em>).</li>
                    <li><strong>Extreme Overbought (>75-80):</strong> Waktu yang tepat untuk mengamankan profit sebagian (Take Profit).</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-primary"><i class="bi bi-shield-check"></i> 3. Filter Trend &amp; Money Management</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Hindari Pisau Jatuh:</strong> Prioritaskan posisi Beli jika tren harga terkonfirmasi di atas MA50 atau terdapat Divergensi kuat.</li>
                    <li><strong>Stop Loss Terukur:</strong> Batasi risiko maksimal 2-3% di bawah Swing Low terdekat.</li>
                    <li><strong>Rasio R:R:</strong> Hanya ambil posisi jika proyeksi Risk to Reward minimal 1:1.5 ke target keuntungan (TP1).</li>
                </ul>
            </div>
        </div>
    </div>
</div>
