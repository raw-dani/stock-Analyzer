<?php

use app\models\form\SettingsForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var SettingsForm $model */
/** @var array $status */

$this->title = 'Settings & Admin';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="settings-index">
    <h1><?= Html::encode($this->title) ?></h1>
    <p class="text-muted">Konfigurasi sistem (khusus admin). Perubahan threshold berlaku pada proses <code>signal/scan</code> berikutnya.</p>

    <div class="row">
        <div class="col-md-8">
            <?php $form = ActiveForm::begin(['id' => 'settings-form']); ?>

            <div class="card mb-3">
                <div class="card-header">📡 Market Data Provider</div>
                <div class="card-body">
                    <?= $form->field($model, 'provider')->dropDownList(SettingsForm::PROVIDERS) ?>
                    <p class="small text-muted mb-0">
                        HTTP provider (Alpha Vantage / Polygon) butuh API key di <code>config/params.php</code>.
                    </p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">🔔 Channel Notifikasi</div>
                <div class="card-body">
                    <?= $form->field($model, 'channelApp')->checkbox() ?>
                    <?= $form->field($model, 'channelEmail')->checkbox() ?>
                    <?= $form->field($model, 'channelTelegram')->checkbox() ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">🎯 Threshold Scoring Signal</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="fw-semibold mb-2">Buy Ratio (min)</div>
                            <?= $form->field($model, 'buyRatio1')->input('number', ['step' => '0.01', 'min' => 0, 'max' => 1]) ?>
                            <?= $form->field($model, 'buyRatio2')->input('number', ['step' => '0.01', 'min' => 0, 'max' => 1]) ?>
                            <?= $form->field($model, 'buyRatio3')->input('number', ['step' => '0.01', 'min' => 0, 'max' => 1]) ?>
                        </div>
                        <div class="col-md-6">
                            <div class="fw-semibold mb-2">Volume Growth (min)</div>
                            <?= $form->field($model, 'volumeGrowth1')->input('number', ['step' => '0.01', 'min' => 0]) ?>
                            <?= $form->field($model, 'volumeGrowth2')->input('number', ['step' => '0.01', 'min' => 0]) ?>
                            <?= $form->field($model, 'volumeGrowth3')->input('number', ['step' => '0.01', 'min' => 0]) ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="fw-semibold mb-2">RVOL (min)</div>
                            <?= $form->field($model, 'rvol1')->input('number', ['step' => '0.1', 'min' => 0]) ?>
                            <?= $form->field($model, 'rvol2')->input('number', ['step' => '0.1', 'min' => 0]) ?>
                            <?= $form->field($model, 'rvol3')->input('number', ['step' => '0.1', 'min' => 0]) ?>
                        </div>
                        <div class="col-md-6">
                            <div class="fw-semibold mb-2">Price Trend (poin)</div>
                            <?= $form->field($model, 'aboveMa20')->input('number', ['min' => 0, 'max' => 100]) ?>
                            <?= $form->field($model, 'aboveMa50')->input('number', ['min' => 0, 'max' => 100]) ?>
                        </div>
                    </div>
                </div>
<div class="card mb-3">
                <div class="card-header">⚙️ Sistem</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4"><?= $form->field($model, 'alertCooldownHours')->input('number', ['min' => 1]) ?></div>
                        <div class="col-md-4"><?= $form->field($model, 'apiRateLimitPerMinute')->input('number', ['min' => 1]) ?></div>
                        <div class="col-md-4"><?= $form->field($model, 'historyDays')->input('number', ['min' => 1]) ?></div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <?= Html::submitButton('💾 Simpan Pengaturan', ['class' => 'btn btn-primary']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">🩺 Status Market</div>
                <div class="card-body small">
                    <?php if (is_array($status)): ?>
                        <dl class="row mb-0">
                            <dt class="col-6">Provider</dt>
                            <dd class="col-6">
                                <?= Html::encode($status['providerLabel']) ?>
                                <?php if ($status['providerConfigured']): ?>
                                    <span class="text-success">✓</span>
                                <?php else: ?>
                                    <span class="text-danger" title="API key belum di-set">✗</span>
                                <?php endif ?>
                            </dd>
                            <dt class="col-6">Saham</dt>
                            <dd class="col-6"><?= (int) $status['stockCount'] ?></dd>
                            <dt class="col-6">Data terakhir</dt>
                            <dd class="col-6"><?= $status['lastDataAt'] ? Yii::$app->formatter->asRelativeTime($status['lastDataAt']) : '—' ?></dd>
                            <dt class="col-6">Analisis terakhir</dt>
                            <dd class="col-6"><?= $status['lastAnalyzedAt'] ? Yii::$app->formatter->asRelativeTime($status['lastAnalyzedAt']) : '—' ?></dd>
                            <dt class="col-6">Sinyal terakhir</dt>
                            <dd class="col-6"><?= Html::encode((string) ($status['lastSignalDate'] ?? '—')) ?></dd>
                        </dl>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>
            </div>