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
/** @var string $sort */
/** @var array $timeframes */
/** @var array $filterParams */
/** @var array $stats */
/** @var bool $fromCache */
/** @var int $lookbackDays */
use yii\bootstrap5\Html;
use app\services\DoubleBottomService;
$this->title = 'Double Bottom Scanner';
$this->params['breadcrumbs'][] = $this->title;
$lookbackDays = $lookbackDays ?? $lookback;
$breakoutCount = count(array_filter($results, fn($r) => $r['breakout']));
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
$sortLink = fn(string $key, string $label) => Html::a($label . ($sort === $key ? ' ▲' : ''), array_merge(['double-bottom/index'], $filterParams, ['sort' => $key]));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="bi bi-graph-up-arrow"></i> <?= Html::encode($this->title) ?></h1>
    <span class="d-flex gap-2">
        <?php if ($fromCache): ?><span class="badge bg-secondary" title="Hasil dari cache 15 menit">Cache</span><?php endif; ?>
        <span class="badge bg-primary"><?= DoubleBottomService::getTimeframeLabel($timeframe) ?></span>
    </span>
</div>
<div class="card mb-4">
    <div class="card-body">
        <?= Html::beginForm(['double-bottom/index'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <div class="col-md-2">
                <label class="form-label fw-bold">Timeframe</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, ['class' => 'form-select', 'onchange' => 'this.form.submit()']) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Lookback (candle)</label>
                <?= Html::input('number', 'lookback', $lookback, ['class' => 'form-control', 'min' => 5, 'max' => 500]) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Toleransi (%)</label>
                <?= Html::input('number', 'tolerance', round($tolerancePercent, 1), ['class' => 'form-control', 'min' => 0.1, 'max' => 20, 'step' => 0.1]) ?>
                <small class="text-muted">Selisih low maks (0,1–20%)</small>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Separasi (candle)</label>
                <div class="input-group">
                    <?= Html::input('number', 'minSeparation', $minSeparation, ['class' => 'form-control', 'min' => 2, 'title' => 'Separasi minimum']) ?>
                    <?= Html::input('number', 'maxSeparation', $maxSeparation, ['class' => 'form-control', 'min' => 2, 'title' => 'Separasi maksimum (kosongkan = otomatis)']) ?>
                </div>
                <small class="text-muted">Min–maks jarak Low 1–Low 2</small>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Neckline min (%)</label>
                <?= Html::input('number', 'necklineMinDepth', round($necklineMinDepth * 100, 1), ['class' => 'form-control', 'min' => 0, 'max' => 50, 'step' => 0.1]) ?>
                <small class="text-muted">Kedalaman W minimum</small>
            </div>
            <div class="col-md-2">
                <div class="form-check mb-2">
                    <?= Html::checkbox('breakoutOnly', $breakoutOnly, ['class' => 'form-check-input', 'id' => 'boOnly', 'value' => 1]) ?>
                    <label class="form-check-label" for="boOnly">Breakout saja</label>
                </div>
                <div class="input-group mb-2">
                    <span class="input-group-text">Conf ≥</span>
                    <?= Html::input('number', 'minConfidence', $minConfidence, ['class' => 'form-control', 'min' => 0, 'max' => 100]) ?>
                </div>
                <?= Html::submitButton('Pindai', ['class' => 'btn btn-primary w-100']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>
<?php if ($isIntradayTf && ($fbCount > 0 || $intradaySyms < $stockCount)): ?>
<div class="alert alert-warning">
    <strong>Mode approximasi:</strong>
    <?= $fbCount ?> dari <?= count($results) ?> hasil memakai <em>daily fallback</em>
    (intraday tersedia untuk <?= $intradaySyms ?>/<?= $stockCount ?> saham).
    Jalankan <code>php yii data/sync-intraday-all</code> untuk melengkapi data 1H.
</div>
<?php endif; ?>
<?php if ($staleDays !== null && $staleDays >= 3): ?>
<div class="alert alert-danger">
    <strong>Data basi:</strong> daily terakhir <?= Html::encode((string) $dailyMax) ?>
    (<?= $staleDays ?> hari lalu). Jalankan <code>php yii data/refresh-all</code> sebelum trading.
</div>
<?php endif; ?>
<?php if (YII_DEBUG): ?>
<details class="mb-3"><summary class="text-muted small">Info debug (hanya mode dev)</summary>
<div class="small text-muted">Saham aktif: <?= $stockCount ?> | Daily: <?= $priceCount ?> (terakhir: <?= Html::encode((string) ($dailyMax ?? '—')) ?>) | Intraday 1H: <?= $intradayCount ?> bar / <?= $intradaySyms ?> saham (terakhir: <?= Html::encode($intradayMax) ?>) | Intraday asli: <?= $realCount ?> | Fallback: <?= $fbCount ?></div></details>
<?php endif; ?>
<div class="row mb-4">
    <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="h2 mb-0 text-primary"><?= count($results) ?></div><small class="text-muted">Pola Ditemukan</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="h2 mb-0 text-success"><?= $breakoutCount ?></div><small class="text-muted">Breakout Terkonfirmasi</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="h2 mb-0 text-warning"><?= count($results) - $breakoutCount ?></div><small class="text-muted">Menunggu Breakout</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="h2 mb-0 text-info"><?= count($results) > 0 ? round(array_sum(array_column($results, 'confidence')) / count($results)) : 0 ?>%</div><small class="text-muted">Rata-rata Confidence</small></div></div></div>
</div>
<div class="card">
    <div class="card-header"><h5 class="mb-0">Hasil Pindai</h5></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light"><tr>
                <th>#</th><th><?= $sortLink('symbol', 'Simbol') ?></th><th>Sektor</th>
                <th class="text-end">Low 1</th><th><?= $sortLink('low1_date', 'Tgl 1') ?></th>
                <th class="text-end">Low 2</th><th>Tgl 2</th>
                <th class="text-end">Neckline</th><th class="text-end">Saat ini</th>
                <th class="text-end"><?= $sortLink('target', 'Target') ?></th><th class="text-end">Ke Target</th>
                <th class="text-center">Data</th><th class="text-center">Status</th>
                <th class="text-end"><?= $sortLink('confidence', 'Conf.') ?></th><th></th>
            </tr></thead>
            <tbody>
            <?php if (empty($results)): ?>
                <tr><td colspan="15" class="text-center text-muted py-4">Tidak ada pola double bottom untuk pengaturan saat ini.</td></tr>
            <?php else: ?>
                <?php foreach ($results as $i => $r): ?>
                <?php $src = $r['data_source'] ?? (($r['approximated'] ?? false) ? 'daily_fallback' : ($timeframe === 24 ? 'daily' : 'intraday')); ?>
                <?php $toTarget = $r['current_price'] > 0 ? ($r['target_price'] - $r['current_price']) / $r['current_price'] * 100 : 0; ?>
                <tr class="<?= $r['breakout'] ? 'table-success' : '' ?>">
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= Html::encode($r['symbol']) ?></strong><br><small class="text-muted"><?= Html::encode($r['stock_name']) ?></small></td>
                    <td><?= Html::encode($r['sector'] ?? '-') ?></td>
                    <td class="text-end"><?= number_format($r['low1_price'], 2) ?></td>
                    <td><small><?= Html::encode(substr((string) $r['low1_date'], 0, 16)) ?></small></td>
                    <td class="text-end"><?= number_format($r['low2_price'], 2) ?></td>
                    <td><small><?= Html::encode(substr((string) $r['low2_date'], 0, 16)) ?></small></td>
                    <td class="text-end fw-bold"><?= number_format($r['neckline'], 2) ?></td>
                    <td class="text-end"><?= number_format($r['current_price'], 2) ?></td>
                    <td class="text-end text-success"><?= number_format($r['target_price'], 2) ?></td>
                    <td class="text-end <?= $toTarget >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($toTarget >= 0 ? '+' : '') . number_format($toTarget, 1) ?>%</td>
                    <td class="text-center">
                        <?php if ($src === 'intraday'): ?><span class="badge bg-primary">Intraday</span>
                        <?php elseif ($src === 'daily'): ?><span class="badge bg-secondary">Daily</span>
                        <?php else: ?><span class="badge bg-warning text-dark" title="Intraday belum ada - memakai agregasi harian">Daily fallback</span><?php endif; ?>
                    </td>
                    <td class="text-center"><?php if ($r['breakout']): ?><span class="badge bg-success">Breakout</span><?php else: ?><span class="badge bg-warning">Waiting</span><?php endif; ?></td>
                    <td class="text-end"><?php $cc = $r['confidence'] >= 70 ? 'text-success' : ($r['confidence'] >= 50 ? 'text-warning' : 'text-danger'); ?>
                        <span class="<?= $cc ?> fw-bold"><?= $r['confidence'] ?>%</span></td>
                    <td><?= Html::a('Detail', array_merge(['double-bottom/detail', 'symbol' => $r['symbol']], $filterParams), ['class' => 'btn btn-sm btn-outline-primary']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
</div>
<div class="card mt-4"><div class="card-header"><h5 class="mb-0">Tentang Pola Double Bottom</h5></div>
<div class="card-body"><div class="row">
<div class="col-md-6"><h6>Deskripsi Pola</h6>
<p>Double bottom adalah pola reversal bullish: harga membentuk dua lembah di level yang hampir sama, dipisahkan puncak moderat (neckline).</p>
<ul><li><strong>Low 1:</strong> uji support pertama</li><li><strong>Low 2:</strong> retest support</li><li><strong>Neckline:</strong> resistance di antara kedua low</li><li><strong>Breakout:</strong> close di atas neckline</li></ul></div>
<div class="col-md-6"><h6>Sinyal Trading</h6>
<ul><li><span class="badge bg-success">Breakout</span> - sinyal beli kuat terkonfirmasi</li>
<li><span class="badge bg-warning">Waiting</span> - pola terbentuk, tunggu breakout</li>
<li><strong>Target:</strong> Neckline + (Neckline - rata-rata Low)</li>
<li><strong>Stop Loss:</strong> 2% di bawah rata-rata Low</li></ul></div>
</div></div></div>
