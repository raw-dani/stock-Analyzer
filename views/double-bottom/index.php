<?php

/** @var yii\web\View $this */
/** @var array $results */
/** @var int $timeframe */
/** @var int $lookbackDays */
/** @var float $tolerance */
/** @var array $timeframes */

use yii\bootstrap5\Html;

$this->title = 'Double Bottom Scanner';
$this->params['breadcrumbs'][] = $this->title;

$breakoutCount = count(array_filter($results, fn($r) => $r['breakout']));
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">
        <i class="bi bi-graph-up-arrow"></i> <?= Html::encode($this->title) ?>
    </h1>
    <span class="badge bg-primary"><?= \app\services\DoubleBottomService::getTimeframeLabel($timeframe) ?></span>
</div>

<!-- Filter Panel -->
<div class="card mb-4">
    <div class="card-body">
        <?= Html::beginForm(['double-bottom/index'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            <div class="col-md-3">
                <label class="form-label fw-bold">Timeframe</label>
                <?= Html::dropDownList('timeframe', $timeframe, $timeframes, [
                    'class' => 'form-select',
                    'onchange' => 'this.form.submit()',
                ]) ?>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Lookback (candles)</label>
                <?= Html::input('number', 'lookback', $lookbackDays, [
                    'class' => 'form-control',
                    'min' => 5,
                    'max' => 500,
                ]) ?>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Tolerance (%)</label>
                <?= Html::input('number', 'tolerance', round($tolerance * 100, 1), [
                    'class' => 'form-control',
                    'min' => 0.1,
                    'max' => 20,
                    'step' => 0.1,
                ]) ?>
                <small class="text-muted">Perbedaan low yang diizinkan (0.1-20%)</small>
            </div>
            <div class="col-md-3">
                <?= Html::submitButton('Scan', ['class' => 'btn btn-primary w-100']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- Debug Info -->
<?php
$stockCount = \app\models\Stock::find()->where(['active' => true])->count();
$priceCount = \app\models\DailyPrice::find()->count();
$intradayCount = \app\models\IntradayPrice::find()->count();
$dailyMax = \app\models\DailyPrice::find()->max('date');
$intradayMaxRaw = \app\models\IntradayPrice::find()->max('datetime');
$intradayMax = is_string($intradayMaxRaw) && $intradayMaxRaw !== '' ? substr($intradayMaxRaw, 0, 16) : '—';
$intradaySyms = (int) \app\models\IntradayPrice::find()->select('stock_id')->distinct()->count();
$realCount = count(array_filter($results, fn($r) => ($r['data_source'] ?? '') === 'intraday'));
$fbCount = count(array_filter($results, fn($r) => ($r['data_source'] ?? '') === 'daily_fallback'));
$isIntradayTf = in_array($timeframe, [1, 2, 4], true);
$staleDays = null;
if (is_string($dailyMax) && $dailyMax !== '') {
    $staleDays = (int) floor((time() - strtotime($dailyMax)) / 86400);
}
?>
<?php if ($isIntradayTf && ($fbCount > 0 || $intradaySyms < $stockCount)): ?>
<div class="alert alert-warning">
    <strong>⚠️ Approximated Mode:</strong>
    <?= $fbCount ?> dari <?= count($results) ?> hasil memakai <em>daily fallback</em>
    (intraday tersedia untuk <?= $intradaySyms ?>/<?= $stockCount ?> saham).
    Jalankan <code>php yii data/sync-intraday-all</code> untuk melengkapi data 1H.
</div>
<?php endif; ?>
<?php if ($staleDays !== null && $staleDays >= 3): ?>
<div class="alert alert-danger">
    <strong>⛔ Data basi:</strong> daily terakhir <?= Html::encode((string) $dailyMax) ?>
    (<?= $staleDays ?> hari lalu). Jalankan <code>php yii data/refresh-all</code> sebelum trading.
</div>
<?php endif; ?>
<div class="alert alert-info">
    <strong>Debug Info:</strong>
    Active Stocks: <?= $stockCount ?> |
    Daily bars: <?= $priceCount ?> (terakhir: <?= Html::encode((string) ($dailyMax ?? '—')) ?>) |
    Intraday 1H: <?= $intradayCount ?> bar / <?= $intradaySyms ?> saham (terakhir: <?= Html::encode($intradayMax) ?>) |
    Timeframe: <?= \app\services\DoubleBottomService::getTimeframeLabel($timeframe) ?> |
    Lookback: <?= $lookbackDays ?> candles |
    Tolerance: <?= $tolerance * 100 ?>%
    <?php if ($isIntradayTf): ?>
        | Intraday asli: <?= $realCount ?> | Fallback: <?= $fbCount ?>
    <?php endif; ?>
</div>

<!-- Summary Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h2 mb-0 text-primary"><?= count($results) ?></div>
                <small class="text-muted">Patterns Found</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h2 mb-0 text-success"><?= $breakoutCount ?></div>
                <small class="text-muted">Breakout Confirmed</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h2 mb-0 text-warning"><?= count($results) - $breakoutCount ?></div>
                <small class="text-muted">Waiting Breakout</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h2 mb-0 text-info">
                    <?= count($results) > 0 ? round(array_sum(array_column($results, 'confidence')) / count($results)) : 0 ?>%
                </div>
                <small class="text-muted">Avg Confidence</small>
            </div>
        </div>
    </div>
</div>

<!-- Results Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Scan Results</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Symbol</th>
                        <th>Sector</th>
                        <th class="text-end">Low 1</th>
                        <th>Date 1</th>
                        <th class="text-end">Low 2</th>
                        <th>Date 2</th>
                        <th class="text-end">Neckline</th>
                        <th class="text-end">Current</th>
                        <th class="text-end">Target</th>
                        <th class="text-center">Data</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Conf.</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="14" class="text-center text-muted py-4">
                                No double bottom patterns found for current settings.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $i => $r): ?>
                            <?php $src = $r['data_source'] ?? (($r['approximated'] ?? false) ? 'daily_fallback' : ($timeframe === 24 ? 'daily' : 'intraday')); ?>
                            <tr class="<?= $r['breakout'] ? 'table-success' : '' ?>">
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <strong><?= Html::encode($r['symbol']) ?></strong>
                                    <br><small class="text-muted"><?= Html::encode($r['stock_name']) ?></small>
                                </td>
                                <td><?= Html::encode($r['sector'] ?? '-') ?></td>
                                <td class="text-end"><?= number_format($r['low1_price'], 2) ?></td>
                                <td><?= $r['low1_date'] ?></td>
                                <td class="text-end"><?= number_format($r['low2_price'], 2) ?></td>
                                <td><?= $r['low2_date'] ?></td>
                                <td class="text-end fw-bold"><?= number_format($r['neckline'], 2) ?></td>
                                <td class="text-end"><?= number_format($r['current_price'], 2) ?></td>
                                <td class="text-end text-success"><?= number_format($r['target_price'], 2) ?></td>
                                <td class="text-center">
                                    <?php if ($src === 'intraday'): ?>
                                        <span class="badge bg-primary">Intraday</span>
                                    <?php elseif ($src === 'daily'): ?>
                                        <span class="badge bg-secondary">Daily</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark" title="Intraday belum ada — memakai agregasi harian">Daily fallback</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($r['breakout']): ?>
                                        <span class="badge bg-success">Breakout</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Waiting</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php
                                    $confClass = $r['confidence'] >= 70 ? 'text-success' : ($r['confidence'] >= 50 ? 'text-warning' : 'text-danger');
                                    ?>
                                    <span class="<?= $confClass ?> fw-bold"><?= $r['confidence'] ?>%</span>
                                </td>
                                <td>
                                    <?= Html::a('Detail', ['double-bottom/detail', 'symbol' => $r['symbol'], 'timeframe' => $timeframe, 'lookback' => $lookbackDays, 'tolerance' => $tolerance * 100], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pattern Info -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">About Double Bottom Pattern</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Pattern Description</h6>
                <p>A double bottom is a bullish reversal pattern where price forms two distinct lows at approximately the same level, separated by a moderate peak (neckline).</p>
                <ul>
                    <li><strong>Low 1:</strong> First price low (support test)</li>
                    <li><strong>Low 2:</strong> Second price low (support retest)</li>
                    <li><strong>Neckline:</strong> Resistance level between lows</li>
                    <li><strong>Breakout:</strong> Price closes above neckline</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Trading Signals</h6>
                <ul>
                    <li><span class="badge bg-success">Breakout</span> - Strong buy signal confirmed</li>
                    <li><span class="badge bg-warning">Waiting</span> - Pattern forming, wait for breakout</li>
                    <li><strong>Target:</strong> Neckline + (Neckline - Low average)</li>
                    <li><strong>Stop Loss:</strong> Below the pattern lows</li>
                </ul>
            </div>
        </div>
    </div>
</div>