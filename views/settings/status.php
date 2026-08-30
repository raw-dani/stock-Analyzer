<?php

use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var array $status */

$this->title = 'Status Market';
$this->params['breadcrumbs'][] = 'Settings';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="settings-status">
    <h1><?= Html::encode($this->title) ?></h1>
    <p class="text-muted">Status koneksi market data provider & waktu update data terakhir (Modul 14.3).</p>

    <div class="card" style="max-width: 560px;">
        <div class="card-body">
            <table class="table table-sm mb-0">
                <tbody>
                    <tr>
                        <th scope="row">Provider aktif</th>
                        <td>
                            <?= Html::encode($status['providerLabel']) ?>
                            <?php if ($status['providerConfigured']): ?>
                                <span class="badge bg-success">terkoneksi</span>
                            <?php else: ?>
                                <span class="badge bg-danger">belum dikonfigurasi (API key)</span>
                            <?php endif ?>
                        </td>
                    </tr>
                    <tr><th scope="row">Jumlah saham</th><td><?= (int) $status['stockCount'] ?></td></tr>
                    <tr>
                        <th scope="row">Data harga terakhir</th>
                        <td>
                            <?= $status['lastDataAt']
                                ? Yii::$app->formatter->asDatetime($status['lastDataAt'], 'php:d M Y H:i')
                                . ' (' . Yii::$app->formatter->asRelativeTime($status['lastDataAt']) . ')'
                                : '—' ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Analisis mingguan terakhir</th>
                        <td>
                            <?= $status['lastAnalyzedAt']
                                ? Yii::$app->formatter->asRelativeTime($status['lastAnalyzedAt'])
                                : '—' ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sinyal terakhir</th>
                        <td><?= Html::encode((string) ($status['lastSignalDate'] ?? '—')) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>