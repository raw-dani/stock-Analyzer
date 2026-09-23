<?php

declare(strict_types=1);

/** @var array $pattern */

use yii\bootstrap5\Html;
?>
<div class="row">
<div class="col-md-6">
<div class="card border-<?= $pattern['breakout'] ? 'success' : 'warning' ?>">
<div class="card-header bg-<?= $pattern['breakout'] ? 'success' : 'warning' ?> text-white">
<h5 class="mb-0">Pattern Status: <?= $pattern['breakout'] ? 'BREAKOUT CONFIRMED' : 'WAITING FOR BREAKOUT' ?></h5>
</div><div class="card-body">
<table class="table table-sm">
<tr><td>Confidence Score</td><td class="text-end fw-bold"><?= $pattern['confidence'] ?>%</td></tr>
<tr><td>First RSI Low (RSI1)</td><td class="text-end"><?= number_format($pattern['rsi1_value'], 1) ?> <br><small class="text-muted"><?= $pattern['rsi1_date'] ?></small></td></tr>
<tr><td>Second RSI Low (RSI2)</td><td class="text-end"><?= number_format($pattern['rsi2_value'], 1) ?> <br><small class="text-muted"><?= $pattern['rsi2_date'] ?></small></td></tr>
<tr><td>Neckline RSI (Resistance)</td><td class="text-end fw-bold"><?= number_format($pattern['neckline_rsi'], 1) ?> <br><small class="text-muted"><?= $pattern['neckline_date'] ?></small></td></tr>
<tr class="table-primary"><td>Current RSI</td><td class="text-end fw-bold"><?= number_format($pattern['current_rsi'], 1) ?></td></tr>
<tr class="table-success"><td>Target Price</td><td class="text-end fw-bold"><?= number_format($pattern['target_price'], 2) ?></td></tr>
<?php if ($pattern['risk_reward'] !== null): ?>
<tr><td>Risk/Reward Ratio</td><td class="text-end"><?= number_format($pattern['risk_reward'], 2) ?></td></tr>
<?php endif; ?>
<tr><td>RSI Divergence</td><td class="text-end"><?= $pattern['divergence'] ? '<span class="badge bg-info text-dark">YES (Bullish)</span>' : 'No' ?></td></tr>
</table></div></div></div>
<div class="col-md-6">
<div class="card"><div class="card-header"><h5 class="mb-0">RSI Chart &amp; Trading Context</h5></div><div class="card-body">
<?php if ($pattern['breakout']): ?>
<div class="alert alert-success"><strong>Breakout Confirmed!</strong> RSI telah break ke atas neckline.</div>
<?php else: ?>
<div class="alert alert-warning"><strong>Waiting for Breakout.</strong> RSI masih di bawah neckline (<?= number_format($pattern['neckline_rsi'], 1) ?>).</div>
<?php endif; ?>
<h6>Key Levels (Harga)</h6>
<ul class="list-group list-group-flush mb-3">
<li class="list-group-item d-flex justify-content-between"><span>Neckline Price (Resistance)</span><strong class="text-danger"><?= number_format($pattern['neckline_price'], 2) ?></strong></li>
<li class="list-group-item d-flex justify-content-between"><span>RSI1 Price Low</span><span><?= number_format($pattern['low1_price'], 2) ?></span></li>
<li class="list-group-item d-flex justify-content-between"><span>RSI2 Price Low</span><span><?= number_format($pattern['low2_price'], 2) ?></span></li>
<li class="list-group-item d-flex justify-content-between"><span>Current Price</span><strong><?= number_format($pattern['current_price'], 2) ?></strong></li>
<li class="list-group-item d-flex justify-content-between"><span>Target</span><strong class="text-success"><?= number_format($pattern['target_price'], 2) ?></strong></li>
</ul>
<h6>Trading Plan</h6>
<ul class="list-group list-group-flush mb-3">
<li class="list-group-item d-flex justify-content-between"><span>Entry (Current)</span><strong><?= number_format($pattern['current_price'], 2) ?></strong></li>
<li class="list-group-item d-flex justify-content-between"><span>Stop Loss (below avg low)</span><strong class="text-danger"><?= number_format(($pattern['low1_price'] + $pattern['low2_price']) / 2 * 0.98, 2) ?></strong></li>
<li class="list-group-item d-flex justify-content-between"><span>Potential Upside</span><strong class="text-success"><?= number_format(($pattern['target_price'] - $pattern['current_price']) / $pattern['current_price'] * 100, 1) ?>%</strong></li>
<li class="list-group-item d-flex justify-content-between"><span>Max Risk</span><strong class="text-danger"><?= number_format(($pattern['current_price'] - ($pattern['low1_price'] + $pattern['low2_price']) / 2 * 0.98) / $pattern['current_price'] * 100, 1) ?>%</strong></li>
</ul>
<div class="progress" style="height: 25px;">
<div class="progress-bar bg-success" style="width: <?= max(0, min(100, ($pattern['target_price'] - $pattern['current_price']) / $pattern['current_price'] * 100)) ?>%"></div>
</div>
</div></div>
</div>
