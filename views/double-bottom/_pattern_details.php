<?php

/** @var array $pattern */

use yii\bootstrap5\Html;
?>

<?php $src = $pattern['data_source'] ?? (($pattern['approximated'] ?? false) ? 'daily_fallback' : 'daily'); ?>
<?php if ($src === 'daily_fallback'): ?>
<div class="alert alert-warning py-2">
    <strong>⚠️ Approximated:</strong> intraday belum tersedia untuk saham ini — memakai <em>daily fallback</em>.
    Jalankan <code>php yii data/sync-intraday SYMBOL</code> untuk hasil 1H/2H/4H yang akurat.
</div>
<?php endif; ?>

<!-- Pattern Details -->
<div class="row">
    <div class="col-md-6">
        <div class="card border-<?= $pattern['breakout'] ? 'success' : 'warning' ?>">
            <div class="card-header bg-<?= $pattern['breakout'] ? 'success' : 'warning' ?> text-white">
                <h5 class="mb-0">
                    Pattern Status: <?= $pattern['breakout'] ? 'BREAKOUT CONFIRMED' : 'WAITING FOR BREAKOUT' ?>
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td>Confidence Score</td>
                        <td class="text-end fw-bold"><?= $pattern['confidence'] ?>%</td>
                    </tr>
                    <tr>
                        <td>First Low (Low 1)</td>
                        <td class="text-end"><?= number_format($pattern['low1_price'], 2) ?> <br><small class="text-muted"><?= $pattern['low1_date'] ?></small></td>
                    </tr>
                    <tr>
                        <td>Second Low (Low 2)</td>
                        <td class="text-end"><?= number_format($pattern['low2_price'], 2) ?> <br><small class="text-muted"><?= $pattern['low2_date'] ?></small></td>
                    </tr>
                    <tr>
                        <td>Neckline (Resistance)</td>
                        <td class="text-end fw-bold"><?= number_format($pattern['neckline'], 2) ?> <br><small class="text-muted"><?= $pattern['neckline_date'] ?></small></td>
                    </tr>
                    <tr class="table-primary">
                        <td>Current Price</td>
                        <td class="text-end fw-bold"><?= number_format($pattern['current_price'], 2) ?></td>
                    </tr>
                    <tr class="table-success">
                        <td>Target Price</td>
                        <td class="text-end fw-bold"><?= number_format($pattern['target_price'], 2) ?></td>
                    </tr>
                    <?php if ($pattern['risk_reward'] !== null): ?>
                    <tr>
                        <td>Risk/Reward Ratio</td>
                        <td class="text-end"><?= number_format($pattern['risk_reward'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Trading Plan</h5>
            </div>
            <div class="card-body">
                <?php if ($pattern['breakout']): ?>
                    <div class="alert alert-success">
                        <strong>Breakout Confirmed!</strong> The pattern is complete.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>Waiting for Breakout.</strong> Price needs to close above <?= number_format($pattern['neckline'], 2) ?> to confirm.
                    </div>
                <?php endif; ?>
                
                <h6>Key Levels</h6>
                <ul class="list-group list-group-flush mb-3">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Target</span>
                        <strong class="text-success"><?= number_format($pattern['target_price'], 2) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Entry (Current)</span>
                        <strong><?= number_format($pattern['current_price'], 2) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Stop Loss</span>
                        <strong class="text-danger"><?= number_format($pattern['stop_loss'] ?? (($pattern['low1_price'] + $pattern['low2_price']) / 2 * 0.98), 2) ?></strong>
                    </li>
                </ul>
                
                <h6>Potential Profit</h6>
                <?php
                $stopLoss = $pattern['stop_loss'] ?? (($pattern['low1_price'] + $pattern['low2_price']) / 2 * 0.98);
                $potentialProfit = (($pattern['target_price'] - $pattern['current_price']) / $pattern['current_price']) * 100;
                $potentialLoss = (($pattern['current_price'] - $stopLoss) / $pattern['current_price']) * 100;
                ?>
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar bg-success" style="width: <?= max(0, min(100, $potentialProfit)) ?>%">
                        +<?= number_format($potentialProfit, 1) ?>%
                    </div>
                </div>
                <small class="text-muted">Max Loss: -<?= number_format($potentialLoss, 1) ?>%</small>
            </div>
        </div>
    </div>
</div>