<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var array $results */
/** @var int $timeframe */
/** @var int $lookback */
/** @var float $tolerance */
/** @var int $rsiPeriod */
/** @var float $maxRsi */
/** @var array $timeframes */

use yii\bootstrap5\Html;

$this->title = 'RSI Double Bottom Scanner';
$this->params['breadcrumbs'][] = $this->title;

$intradaySymbols = \app\models\IntradayPrice::find()
    ->select('stock_id')->distinct()->column();
$dailyMax = \app\models\DailyPrice::find()->max('date');
$intradayMax = \app\models\IntradayPrice::find()->max('datetime');
$allSymbols = \app\models\Stock::find()->where(['active' => true])->count();
$tfNeedsIntraday = $timeframe !== 24;
$covered = count($intradaySymbols);
$resultsIntraday = count(array_filter($results, fn($r) => ($r['data_source'] ?? '') === 'intraday'));
$resultsFallback = count(array_filter($results, fn($r) => ($r['approximated'] ?? false)));
$staleDays = $dailyMax ? (int) floor((time() - strtotime($dailyMax . ' 23:59:00')) / 86400) : null;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">
        <i class="bi bi-graph-up-arrow"></i> <?= Html::encode($this->title) ?>
    </h1>
    <span class="badge bg-info text-dark"><?= \app\controllers\RsiDoubleBottomController::getTimeframes()[$timeframe] ?></span>
</div>

<?php if ($tfNeedsIntraday && $covered < $allSymbols): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center">
    <div>
        <strong>⚠️ Mode approximated:</strong>
        intraday 1H baru tersedia untuk <?= $covered ?>/<?= $allSymbols ?> simbol.
        <?= $resultsFallback ?> hasil di bawah dihitung dari <em>daily fallback</em> (kurang akurat untuk TF 1H/2H/4H).
        Jalankan <code>php yii data/sync-intraday-all</code> untuk melengkapi.
    </div>
</div>
<?php endif ?>
<?php if ($staleDays !== null && $staleDays >= 3): ?>
<div class="alert alert-danger">
    <strong>⛔ Data basi <?= $staleDays ?> hari</strong> (daily terakhir <?= Html::encode((string) $dailyMax) ?>).
    Jalankan <code>php yii data/refresh-all</code> sebelum mengambil keputusan trading.
</div>
<?php endif ?>

<div class="card mb-3">
    <div class="card-body py-2 small text-muted d-flex flex-wrap gap-3">
        <span>📅 Daily terakhir: <strong><?= Html::encode((string) ($dailyMax ?? '—')) ?></strong></span>
        <span>🕐 Intraday terakhir: <strong><?= Html::encode((string) ($intradayMax ?? '—')) ?></strong></span>
        <span>📦 Cakupan intraday: <strong><?= $covered ?>/<?= $allSymbols ?> simbol</strong></span>
        <?php if ($tfNeedsIntraday): ?>
        <span>🎯 Hasil dari intraday asli: <strong><?= $resultsIntraday ?></strong> / fallback: <strong><?= $resultsFallback ?></strong></span>
        <?php endif ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <?= Html::beginForm(['/rsi-double-bottom/index'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <div class="col-md-2">
                <label class="form-label fw-bold">Timeframe</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select', 'onchange' => 'this.form.submit()']) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Lookback (candles)</label>
                <?= Html::input('number', 'lookback', $lookback, ['class' => 'form-control', 'min' => 20, 'max' => 300]) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Tolerance (RSI pts)</label>
                <?= Html::input('number', 'tolerance', $tolerance, ['class' => 'form-control', 'min' => 0.5, 'max' => 10, 'step' => 0.5]) ?>
                <small class="text-muted">Selisih RSI1 - RSI2 maksimal</small>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">RSI Period</label>
                <?= Html::input('number', 'rsiPeriod', $rsiPeriod, ['class' => 'form-control', 'min' => 2, 'max' => 50]) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Max RSI (lembah)</label>
                <?= Html::input('number', 'maxRsi', $maxRsi, ['class' => 'form-control', 'min' => 20, 'max' => 70, 'step' => 5]) ?>
                <small class="text-muted">Lembah harus &lt; maxRsi</small>
            </div>
            <div class="col-md-2">
                <?= Html::submitButton('Scan', ['class' => 'btn btn-primary w-100']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<?php
$stockCount = \app\models\Stock::find()->where(['active' => true])->count();
$intradayCount = \app\models\IntradayPrice::find()->count();
$dailyCount = \app\models\DailyPrice::find()->count();
?>
<div class="alert alert-info">
    <strong>Debug Info:</strong>
    Active Stocks: <?= $stockCount ?> | 
    Intraday bars (1h): <?= $intradayCount ?> | 
    Daily bars: <?= $dailyCount ?> | 
    Timeframe: <?= \app\controllers\RsiDoubleBottomController::getTimeframes()[$timeframe] ?> | 
    Lookback: <?= $lookback ?> candles | 
    Tolerance: <?= $tolerance ?> pts | 
    RSI Period: <?= $rsiPeriod ?> | 
    Max RSI: <?= $maxRsi ?>
</div>

<div class="row mb-4">
    <div class="col-md-3"><div class="card text-center"><div class="card-body">
        <div class="h2 mb-0 text-primary"><?= count($results) ?></div>
        <small class="text-muted">Patterns Found</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body">
        <div class="h2 mb-0 text-success"><?= count(array_filter($results, fn($r) => (bool) ($r['breakout'] ?? false))) ?></div>
        <small class="text-muted">Breakout Confirmed</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body">
        <div class="h2 mb-0 text-warning"><?= count(array_filter($results, fn($r) => (bool) ($r['divergence'] ?? false))) ?></div>
        <small class="text-muted">Bullish Divergence</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body">
        <div class="h2 mb-0 text-danger"><?= count(array_filter($results, fn($r) => ($r['confidence'] ?? 0) >= 70)) ?></div>
        <small class="text-muted">Confidence ≥ 70%</small></div></div></div>
</div>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-striped table-hover mb-0">
<thead><tr>
<th>Symbol</th><th>Name</th><th>Sector</th><th>Data</th>
<th>RSI1</th><th>RSI2</th><th>Neckline<br>RSI</th><th>Current<br>RSI</th>
<th>RSI1<br>Date</th><th>RSI2<br>Date</th>
<th>Breakout</th><th>Diverg.</th><th>Conf.</th><th>Target<br>Price</th><th>Action</th>
</tr></thead><tbody>
<?php if (empty($results)): ?>
<tr><td colspan="15" class="text-center py-4 text-muted">
<i class="bi bi-search"></i> No RSI Double Bottom patterns found with current settings.
</td></tr>
<?php else: ?>
<?php foreach ($results as $r): ?>
<?php $source = $r['data_source'] ?? (($r['approximated'] ?? false) ? 'daily_fallback' : ($timeframe === 24 ? 'daily' : 'intraday')); ?>
<tr>
<td><strong><?= Html::encode($r['symbol']) ?></strong></td>
<td><?= Html::encode($r['stock_name']) ?></td>
<td><?= Html::encode($r['sector'] ?? '-') ?></td>
<td>
<?php if ($source === 'daily_fallback'): ?><span class="badge bg-warning text-dark">Daily fallback</span>
<?php elseif ($source === 'daily'): ?><span class="badge bg-secondary">Daily</span>
<?php else: ?><span class="badge bg-info text-dark">Intraday</span><?php endif; ?>
</td>
<td class="text-end"><?= number_format($r['rsi1_value'] ?? 0, 1) ?></td>
<td class="text-end"><?= number_format($r['rsi2_value'] ?? 0, 1) ?></td>
<td class="text-end text-danger"><?= number_format($r['neckline_rsi'] ?? 0, 1) ?></td>
<td class="text-end fw-bold"><?= number_format($r['current_rsi'] ?? 0, 1) ?></td>
<td><?= $r['rsi1_date'] ? substr($r['rsi1_date'], 0, 10) : '-' ?></td>
<td><?= $r['rsi2_date'] ? substr($r['rsi2_date'], 0, 10) : '-' ?></td>
<td class="text-center">
<?php if ($r['breakout']): ?><span class="badge bg-success">Yes</span>
<?php else: ?><span class="badge bg-warning">No</span><?php endif; ?>
</td><td class="text-center">
<?php if ($r['divergence']): ?><span class="badge bg-info text-dark">Yes</span>
<?php else: ?><span class="badge bg-secondary">No</span><?php endif; ?>
</td>
<td class="text-end">
<?php $conf = $r['confidence'] ?? 0;
$confClass = $conf >= 70 ? 'text-success' : ($conf >= 50 ? 'text-warning' : 'text-danger'); ?>
<span class="<?= $confClass ?> fw-bold"><?= $conf ?>%</span>
</td>
<td class="text-end text-success fw-bold"><?= number_format($r['target_price'] ?? 0, 2) ?></td>
<td>
<?= Html::a('Detail', ['/rsi-double-bottom/detail', 'symbol' => $r['symbol'], 'timeframe' => $timeframe, 'lookback' => $lookback, 'tolerance' => $tolerance, 'rsiPeriod' => $rsiPeriod, 'maxRsi' => $maxRsi], ['class' => 'btn btn-sm btn-outline-primary']) ?>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody></table></div></div></div>

<div class="card mt-4"><div class="card-header"><h5 class="mb-0">About RSI Double Bottom Pattern</h5></div><div class="card-body">
<div class="row"><div class="col-md-6">
<h6>Pattern Description</h6>
<p>Sama seperti double bottom harga, tetapi polanya terbentuk di <strong>seri RSI(14)</strong> — dua lembah RSI di zona lemah (&lt; maxRsi) yang hampir sama tingginya, dipisahkan satu puncak (neckline RSI). Konfirmasi terjadi saat RSI break ke atas neckline.</p>
<ul>
<li><strong>RSI1 / RSI2:</strong> nilai RSI di dua lembah (harus &lt; maxRsi)</li>
<li><strong>Neckline RSI:</strong> puncak RSI tertinggi di antara kedua lembah</li>
<li><strong>Current RSI:</strong> RSI candle paling akhir</li>
<li><strong>Breakout:</strong> Current RSI &gt; Neckline RSI</li>
<li><strong>Divergence:</strong> harga low2 lebih rendah tapi RSI2 lebih tinggi (bullish)</li>
</ul></div><div class="col-md-6">
<h6>Cara Menggunakan</h6>
<ul>
<li>Pilih <strong>Timeframe</strong>: 1H/2H/4H butuh data intraday (jalankan <code>php yii data/sync-intraday NVDA</code> dulu). 1D pakai data harian.</li>
<li>Lembah default di bawah <strong>40</strong> RSI — lebih ketat = lebih sedikit pola.</li>
<li>Badge <strong>Approximated</strong>: menunjukkan pola dihasilkan dari daily_price (bukan candle asli).</li>
<li>Konsentrasi pada pola dengan <strong>confidence ≥ 70%</strong> dan breakout confirmed.</li>
</ul>
<h6>Next Step (Fase 2)</h6>
<ul>
<li>REST API GET /api/v1/rsi-double-bottom</li>
<li>Integrasi Alert (cooldown 24 jam)</li>
<li>Backtest strategi breakout RSI</li>
</ul></div></div></div></div>
