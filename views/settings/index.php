<?php

use app\models\form\SettingsForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var SettingsForm $model */
/** @var array $status */
/** @var array $syncCommands */
/** @var array|null $syncStatus */
/** @var string $phpExecutable */

$this->title = 'Settings & Admin';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="settings-index">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 fw-bold"><?= Html::encode($this->title) ?></h1>
            <p class="text-muted small mb-0">Konfigurasi sistem &amp; pusat eksekusi sinkronisasi data pasar.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-secondary border font-monospace small" title="PHP Binary yang digunakan untuk menjalankan console CLI">
                <?= \app\helpers\SvgHelper::icon('terminal', ['class' => 'me-1']) ?> <?= Html::encode(basename($phpExecutable ?? 'php')) ?>
            </span>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- PANEL KHUSUS: SINKRONISASI DATA PASAR (CLI RUNNER)              -->
    <!-- ============================================================== -->
    <div class="card border-0 shadow-sm mb-4 border-top border-primary border-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <?= \app\helpers\SvgHelper::icon('cpu-fill', ['class' => 'text-primary me-2']) ?> Eksekusi Sinkronisasi Data Pasar (CLI Pipeline)
                </h5>
                <div class="small text-muted">Jalankan perintah konsol database pasar langsung dari tombol web ini tanpa perlu membuka terminal manual</div>
            </div>
            <div id="sync-status-indicator">
                <?php if (!empty($syncStatus['last_time'])): ?>
                    <span class="badge bg-<?= !empty($syncStatus['last_success']) ? 'success' : 'danger' ?> bg-opacity-10 text-<?= !empty($syncStatus['last_success']) ? 'success' : 'danger' ?> border border-<?= !empty($syncStatus['last_success']) ? 'success' : 'danger' ?> px-2 py-1 small">
                        <?= \app\helpers\SvgHelper::icon('clock-history', ['class' => 'me-1']) ?> Terakhir: <?= Html::encode($syncStatus['last_title'] ?? '') ?> (<?= Yii::$app->formatter->asRelativeTime($syncStatus['last_time']) ?>)
                    </span>
                <?php else: ?>
                    <span class="badge bg-light text-secondary border px-2 py-1 small">
                        <?= \app\helpers\SvgHelper::icon('info-circle', ['class' => 'me-1']) ?> Siap Dijalankan
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-4">
            <!-- Grid 4 Tombol Utama Perintah CLI -->
            <div class="row g-3 mb-4">
                <!-- 1. php yii market/refresh-stocklist -->
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border bg-light shadow-sm">
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary font-monospace" style="font-size:0.75rem;">
                                    market/refresh-stocklist
                                </span>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info" style="font-size:0.68rem;">
                                    <?= \app\helpers\SvgHelper::icon('lightning-charge-fill') ?> Cepat (~1-2 dtk)
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">📋 Refresh Stock List</h6>
                            <p class="text-muted small flex-grow-1 mb-3" style="font-size:0.80rem;">
                                Ambil dan perbarui daftar seluruh simbol saham yang didukung provider (Yahoo Finance) ke database lokal (DKNG, WDC, BKNG, dll).
                            </p>
                            <div class="pt-2 border-top">
                                <button type="button" class="btn btn-primary w-100 fw-bold btn-run-cmd shadow-sm"
                                    data-cmd="market/refresh-stocklist"
                                    data-title="Refresh Stock List"
                                    data-cli="php yii market/refresh-stocklist">
                                    <?= \app\helpers\SvgHelper::icon('play-circle-fill', ['class' => 'me-1']) ?> Jalankan Stock List
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. php yii data/refresh-all -->
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border bg-light shadow-sm">
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-success bg-opacity-10 text-success border border-success font-monospace" style="font-size:0.75rem;">
                                    data/refresh-all
                                </span>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success" style="font-size:0.68rem;">
                                    Candle 1D (Harian)
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">📅 Refresh Daily Data (All)</h6>
                            <p class="text-muted small flex-grow-1 mb-2" style="font-size:0.80rem;">
                                Ambil update candle harian (*Daily OHLCV*) secara incremental untuk seluruh saham aktif (lanjut dari tanggal terakhir di DB).
                            </p>
                            <div class="mb-2">
                                <label class="form-label small text-muted fw-semibold mb-1" style="font-size:0.75rem;">Limit Saham:</label>
                                <select class="form-select form-select-sm" id="limit-refresh-daily">
                                    <option value="0" selected>Semua Saham Aktif</option>
                                    <option value="10">10 Saham Pertama (Tes Cepat)</option>
                                    <option value="25">25 Saham Pertama</option>
                                    <option value="50">50 Saham Pertama</option>
                                    <option value="100">100 Saham Pertama</option>
                                </select>
                            </div>
                            <div class="pt-2 border-top">
                                <button type="button" class="btn btn-success w-100 fw-bold btn-run-cmd shadow-sm"
                                    data-cmd="data/refresh-all"
                                    data-limit-id="limit-refresh-daily"
                                    data-title="Refresh Daily Data (All Stocks)"
                                    data-cli="php yii data/refresh-all">
                                    <?= \app\helpers\SvgHelper::icon('play-circle-fill', ['class' => 'me-1']) ?> Jalankan Daily Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. php yii data/analyze -->
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border bg-light shadow-sm">
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-info bg-opacity-10 text-info border border-info font-monospace" style="font-size:0.75rem;">
                                    data/analyze
                                </span>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info" style="font-size:0.68rem;">
                                    Metode A &amp; Scoring
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">📊 Analisis Volume &amp; Sinyal</h6>
                            <p class="text-muted small flex-grow-1 mb-3" style="font-size:0.80rem;">
                                Hitung transaksi Metode A (buyer vs seller), akumulasi mingguan, lonjakan RVOL, dan update scoring sinyal seluruh saham aktif.
                            </p>
                            <div class="pt-2 border-top">
                                <button type="button" class="btn btn-info text-dark w-100 fw-bold btn-run-cmd shadow-sm"
                                    data-cmd="data/analyze"
                                    data-title="Analyze Weekly Volume &amp; Signals"
                                    data-cli="php yii data/analyze">
                                    <?= \app\helpers\SvgHelper::icon('play-circle-fill', ['class' => 'me-1']) ?> Jalankan Analisis &amp; Scoring
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. php yii data/sync-intraday-all -->
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border bg-light shadow-sm">
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning font-monospace" style="font-size:0.75rem;">
                                    data/sync-intraday-all
                                </span>
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning" style="font-size:0.68rem;">
                                    Candle 1H (Intraday)
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">⏱️ Sync Intraday 1H Data (All)</h6>
                            <p class="text-muted small flex-grow-1 mb-2" style="font-size:0.80rem;">
                                Ambil update candle 1 jam (*1-Hour Bars*) secara incremental untuk analisis multi-timeframe (1H, 2H, 4H, 1D) dan volume scanner.
                            </p>
                            <div class="mb-2">
                                <label class="form-label small text-muted fw-semibold mb-1" style="font-size:0.75rem;">Limit Saham:</label>
                                <select class="form-select form-select-sm" id="limit-sync-intraday">
                                    <option value="0" selected>Semua Saham Aktif</option>
                                    <option value="10">10 Saham Pertama (Tes Cepat)</option>
                                    <option value="25">25 Saham Pertama</option>
                                    <option value="50">50 Saham Pertama</option>
                                    <option value="100">100 Saham Pertama</option>
                                </select>
                            </div>
                            <div class="pt-2 border-top">
                                <button type="button" class="btn btn-warning text-dark w-100 fw-bold btn-run-cmd shadow-sm"
                                    data-cmd="data/sync-intraday-all"
                                    data-limit-id="limit-sync-intraday"
                                    data-title="Sync Intraday 1H Data (All Stocks)"
                                    data-cli="php yii data/sync-intraday-all">
                                    <?= \app\helpers\SvgHelper::icon('play-circle-fill', ['class' => 'me-1']) ?> Jalankan Intraday Sync
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Master Action Banner: Jalankan Semua Pipeline Sekaligus -->
            <div class="p-3 bg-white rounded border shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="display-6 text-primary"><?= \app\helpers\SvgHelper::icon('rocket-takeoff-fill') ?></div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Jalankan Rangkaian Sinkronisasi Lengkap (All-in-One)</h6>
                        <div class="small text-muted">
                            Mengeksekusi secara otomatis dan berurutan: 
                            <strong>1. Refresh Stock List</strong> &rarr; 
                            <strong>2. Refresh Daily Data</strong> &rarr; 
                            <strong>3. Analisis Volume &amp; Scoring</strong> &rarr; 
                            <strong>4. Sync Intraday Data</strong>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select class="form-select form-select-sm" id="limit-sync-all" style="width: auto;">
                        <option value="0" selected>Semua Saham</option>
                        <option value="10">Limit 10 Saham</option>
                        <option value="25">Limit 25 Saham</option>
                        <option value="50">Limit 50 Saham</option>
                    </select>
                    <button type="button" class="btn btn-dark fw-bold btn-run-cmd px-3 shadow-sm"
                        data-cmd="all"
                        data-limit-id="limit-sync-all"
                        data-title="Rangkaian Sinkronisasi Lengkap (All-in-One)"
                        data-cli="php yii market/refresh-stocklist && data/refresh-all && data/analyze && data/sync-intraday-all">
                        <?= \app\helpers\SvgHelper::icon('play-fill', ['class' => 'me-1']) ?> Eksekusi Semua Pipeline
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- PENGATURAN SISTEM (PROVIDER, CHANNEL, THRESHOLD, SISTEM)       -->
    <!-- ============================================================== -->
    <div class="row">
        <div class="col-md-8">
            <?php $form = ActiveForm::begin(['id' => 'settings-form']); ?>

            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom fw-bold">📡 Market Data Provider</div>
                <div class="card-body p-4">
                    <?= $form->field($model, 'provider')->dropDownList(SettingsForm::PROVIDERS) ?>
                    <p class="small text-muted mb-0">
                        HTTP provider (Alpha Vantage / Polygon) butuh API key di <code>config/params.php</code>.<br>
                        <strong>Yahoo Finance</strong> &amp; CSV (dev) dapat digunakan langsung tanpa API key.
                    </p>
                </div>
            </div>

            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom fw-bold">🔔 Channel Notifikasi</div>
                <div class="card-body p-4">
                    <?= $form->field($model, 'channelApp')->checkbox() ?>
                    <?= $form->field($model, 'channelEmail')->checkbox() ?>
                    <?= $form->field($model, 'channelTelegram')->checkbox() ?>
                </div>
            </div>

            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom fw-bold">🎯 Threshold Scoring Signal</div>
                <div class="card-body p-4">
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
            </div>

            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom fw-bold">⚙️ Sistem</div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-4"><?= $form->field($model, 'alertCooldownHours')->input('number', ['min' => 1]) ?></div>
                        <div class="col-md-4"><?= $form->field($model, 'apiRateLimitPerMinute')->input('number', ['min' => 1]) ?></div>
                        <div class="col-md-4"><?= $form->field($model, 'historyDays')->input('number', ['min' => 1]) ?></div>
                    </div>
                </div>
            </div>

            <div class="form-group mb-4">
                <?= Html::submitButton('💾 Simpan Pengaturan', ['class' => 'btn btn-primary px-4 fw-bold shadow-sm']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>

        <!-- Kolom Kanan: Status Database & Provider -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-bottom fw-bold">
                    🩺 Status Database Pasar
                </div>
                <div class="card-body small">
                    <?php if (is_array($status)): ?>
                        <dl class="row mb-0">
                            <dt class="col-6 text-muted">Provider Aktif</dt>
                            <dd class="col-6 fw-bold">
                                <?= Html::encode($status['providerLabel']) ?>
                                <?php if ($status['providerConfigured']): ?>
                                    <span class="text-success ms-1" title="Provider terkonfigurasi">✓</span>
                                <?php else: ?>
                                    <span class="text-danger ms-1" title="API key belum di-set">✗</span>
                                <?php endif ?>
                            </dd>

                            <dt class="col-6 text-muted">Total Saham</dt>
                            <dd class="col-6 fw-bold text-primary"><?= (int) $status['stockCount'] ?> simbol</dd>

                            <dt class="col-6 text-muted">Daily Terakhir</dt>
                            <dd class="col-6" id="stat-last-daily"><?= $status['lastDataAt'] ? Yii::$app->formatter->asRelativeTime($status['lastDataAt']) : '—' ?></dd>

                            <dt class="col-6 text-muted">Intraday 1H Terakhir</dt>
                            <dd class="col-6" id="stat-last-intraday"><?= !empty($status['lastIntradayAt']) ? Yii::$app->formatter->asRelativeTime($status['lastIntradayAt']) : (!empty($status['lastIntradayDatetime']) ? Html::encode($status['lastIntradayDatetime']) : '—') ?></dd>

                            <dt class="col-6 text-muted">Analisis Terakhir</dt>
                            <dd class="col-6"><?= $status['lastAnalyzedAt'] ? Yii::$app->formatter->asRelativeTime($status['lastAnalyzedAt']) : '—' ?></dd>

                            <dt class="col-6 text-muted">Sinyal Terakhir</dt>
                            <dd class="col-6"><?= Html::encode((string) ($status['lastSignalDate'] ?? '—')) ?></dd>
                        </dl>
                    <?php endif ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body small">
                    <h6 class="fw-bold mb-2"><?= \app\helpers\SvgHelper::icon('lightbulb-fill', ['class' => 'text-warning me-1']) ?> Tips Penggunaan CLI:</h6>
                    <p class="text-secondary mb-1">
                        &bull; Jalankan <strong>Refresh Stock List</strong> sesekali atau jika ada simbol baru yang ditambahkan ke daftar provider.
                    </p>
                    <p class="text-secondary mb-1">
                        &bull; Jalankan <strong>Refresh Daily Data</strong> setiap hari setelah penutupan bursa NYSE (~04:00 WIB).
                    </p>
                    <p class="text-secondary mb-0">
                        &bull; Jalankan <strong>Sync Intraday</strong> setiap 1-4 jam pada jam market buka untuk memantau pergerakan volume real-time.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL TERMINAL RUNNER (LIVE STREAMING OUTPUT KONSOL)            -->
<!-- ============================================================== -->
<div class="modal fade" id="modalCommandTerminal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalCommandTerminalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="background-color: #18181b; color: #e4e4e7;">
            <div class="modal-header py-2 px-3 border-bottom border-dark" style="background-color: #27272a;">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary font-monospace" id="terminal-badge-cmd">CLI</span>
                    <h6 class="modal-title font-monospace mb-0 text-white" id="modalCommandTerminalTitle">php yii ...</h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark px-2 py-1" id="terminal-status-badge">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        <span id="terminal-status-text">Menjalankan...</span>
                    </span>
                    <span class="small font-monospace text-muted" id="terminal-timer">00:00</span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="btn-close-terminal" disabled aria-label="Tutup"></button>
                </div>
            </div>
            <div class="modal-body p-0" style="background-color: #09090b;">
                <div class="p-3 font-monospace" id="terminal-output" style="min-height: 340px; max-height: 480px; overflow-y: auto; color: #4ade80; white-space: pre-wrap; word-break: break-all; line-height: 1.5; font-size: 0.82rem;">
                    <!-- Live console output streamed here -->
                </div>
            </div>
            <div class="modal-footer py-2 px-3 border-top border-dark d-flex justify-content-between align-items-center" style="background-color: #27272a;">
                <div class="small text-muted font-monospace" id="terminal-footer-info" style="font-size:0.75rem;">
                    <?= \app\helpers\SvgHelper::icon('broadcast', ['class' => 'text-success me-1']) ?> Streaming live console output
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-light btn-sm font-monospace" id="btn-copy-terminal" title="Salin seluruh isi log terminal">
                        <?= \app\helpers\SvgHelper::icon('clipboard', ['class' => 'me-1']) ?> Salin Log
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm font-monospace" id="btn-clear-terminal" title="Bersihkan tampilan terminal">
                        <?= \app\helpers\SvgHelper::icon('eraser', ['class' => 'me-1']) ?> Bersihkan
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm fw-bold px-3" data-bs-dismiss="modal" id="btn-modal-dismiss" disabled>
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- JAVASCRIPT LIVE TERMINAL RUNNER & POLLING                       -->
<!-- ============================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('modalCommandTerminal');
    const terminalOutput = document.getElementById('terminal-output');
    const terminalTitle = document.getElementById('modalCommandTerminalTitle');
    const terminalBadge = document.getElementById('terminal-badge-cmd');
    const statusBadge = document.getElementById('terminal-status-badge');
    const statusText = document.getElementById('terminal-status-text');
    const timerText = document.getElementById('terminal-timer');
    const btnClose = document.getElementById('btn-close-terminal');
    const btnDismiss = document.getElementById('btn-modal-dismiss');
    const footerInfo = document.getElementById('terminal-footer-info');

    function getModal() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            return bootstrap.Modal.getOrCreateInstance(modalEl);
        }
        return null;
    }

    let currentEventSource = null;
    let timerInterval = null;
    let secondsElapsed = 0;

    function formatTime(sec) {
        const m = Math.floor(sec / 60).toString().padStart(2, '0');
        const s = (sec % 60).toString().padStart(2, '0');
        return m + ':' + s;
    }

    function appendOutput(text, isError = false) {
        const span = document.createElement('span');
        if (isError) {
            span.style.color = '#ef4444';
        }
        span.textContent = text;
        terminalOutput.appendChild(span);
        terminalOutput.scrollTop = terminalOutput.scrollHeight;
    }

    function setRunningState(running, success = null, message = '') {
        btnClose.disabled = running;
        btnDismiss.disabled = running;

        if (running) {
            statusBadge.className = 'badge bg-warning text-dark px-2 py-1';
            statusText.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menjalankan...';
            footerInfo.innerHTML = <?= json_encode(\app\helpers\SvgHelper::icon('broadcast', ['class' => 'text-success me-1'])) ?> + ' Proses sedang berjalan di server...';
        } else {
            clearInterval(timerInterval);
            if (success === true) {
                statusBadge.className = 'badge bg-success px-2 py-1';
                statusText.innerText = '✓ Selesai';
                footerInfo.innerHTML = <?= json_encode(\app\helpers\SvgHelper::icon('check-circle-fill', ['class' => 'text-success me-1'])) ?> + ' ' + (message || 'Eksekusi selesai dengan sukses.');
            } else {
                statusBadge.className = 'badge bg-danger px-2 py-1';
                statusText.innerText = '✗ Gagal';
                footerInfo.innerHTML = <?= json_encode(\app\helpers\SvgHelper::icon('exclamation-triangle-fill', ['class' => 'text-danger me-1'])) ?> + ' ' + (message || 'Terdapat error atau proses terhenti.');
            }
        }
    }

    // Event listener untuk semua tombol .btn-run-cmd
    document.querySelectorAll('.btn-run-cmd').forEach(button => {
        button.addEventListener('click', function () {
            const cmd = this.getAttribute('data-cmd');
            const title = this.getAttribute('data-title') || cmd;
            const cli = this.getAttribute('data-cli') || ('php yii ' + cmd);
            const limitId = this.getAttribute('data-limit-id');
            let limit = 0;

            if (limitId) {
                const limitSelect = document.getElementById(limitId);
                if (limitSelect) {
                    limit = parseInt(limitSelect.value, 10) || 0;
                }
            }

            // Siapkan UI modal terminal
            terminalTitle.innerText = cli + (limit > 0 ? ' --limit=' + limit : '');
            terminalBadge.innerText = cmd.toUpperCase();
            terminalOutput.innerHTML = '';
            appendOutput('> Memulai eksekusi perintah...\n');
            appendOutput('> Command: ' + cli + (limit > 0 ? ' --limit=' + limit : '') + '\n');
            appendOutput('> Waktu: ' + new Date().toLocaleTimeString() + '\n');
            appendOutput('------------------------------------------------------------\n');

            secondsElapsed = 0;
            timerText.innerText = '00:00';
            clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                secondsElapsed++;
                timerText.innerText = formatTime(secondsElapsed);
            }, 1000);

            setRunningState(true);
            const modalInstance = getModal();
            if (modalInstance) {
                modalInstance.show();
            }

            // Bangun URL streaming SSE
            const runUrl = <?= json_encode(Url::to(['settings/run-command'])) ?>;
            const streamUrl = runUrl + '?cmd=' + encodeURIComponent(cmd) + '&limit=' + limit + '&stream=1';

            if (currentEventSource) {
                currentEventSource.close();
            }

            try {
                currentEventSource = new EventSource(streamUrl);

                currentEventSource.onmessage = function (event) {
                    try {
                        const data = JSON.parse(event.data);
                        if (data.type === 'start') {
                            appendOutput('[SYS] Memulai pipeline: ' + data.title + ' (' + data.total + ' perintah)\n');
                        } else if (data.type === 'cmd_start') {
                            appendOutput('\n[TASK ' + data.index + '] ' + data.title + ' -> ' + data.cli + '\n');
                        } else if (data.type === 'output') {
                            appendOutput(data.line);
                        } else if (data.type === 'cmd_fail') {
                            appendOutput('\n[ERROR] Task ' + data.index + ' gagal dengan exit code: ' + data.exitCode + '\n', true);
                        } else if (data.type === 'done') {
                            appendOutput('\n------------------------------------------------------------\n');
                            appendOutput('[SYS] ' + data.message + '\n');
                            setRunningState(false, data.success, data.message);
                            currentEventSource.close();
                            currentEventSource = null;
                            refreshSyncStatus();
                        } else if (data.type === 'error') {
                            appendOutput('\n[ERROR] ' + data.message + '\n', true);
                            setRunningState(false, false, data.message);
                            currentEventSource.close();
                            currentEventSource = null;
                        }
                    } catch (e) {
                        appendOutput(event.data + '\n');
                    }
                };

                currentEventSource.onerror = function () {
                    // Jika koneksi putus sebelum sinyal done
                    if (currentEventSource && currentEventSource.readyState === EventSource.CLOSED) {
                        setRunningState(false, true, 'Koneksi stream ditutup.');
                    } else {
                        appendOutput('\n[INFO] Koneksi stream selesai atau dialihkan.\n');
                        setRunningState(false, true, 'Proses selesai.');
                    }
                    if (currentEventSource) {
                        currentEventSource.close();
                        currentEventSource = null;
                    }
                    refreshSyncStatus();
                };
            } catch (err) {
                appendOutput('\n[ERROR] Gagal menginisialisasi EventSource: ' + err.message + '\n', true);
                setRunningState(false, false, err.message);
            }
        });
    });

    // Salin log ke clipboard
    const btnCopy = document.getElementById('btn-copy-terminal');
    if (btnCopy) {
        btnCopy.addEventListener('click', function () {
            const text = terminalOutput.innerText;
            navigator.clipboard.writeText(text).then(() => {
                const orig = this.innerHTML;
                this.innerHTML = <?= json_encode(\app\helpers\SvgHelper::icon('check-lg', ['class' => 'text-success me-1'])) ?> + ' Tersalin!';
                setTimeout(() => { this.innerHTML = orig; }, 2000);
            }).catch(err => {
                alert('Gagal menyalin: ' + err);
            });
        });
    }

    // Bersihkan terminal
    const btnClear = document.getElementById('btn-clear-terminal');
    if (btnClear) {
        btnClear.addEventListener('click', function () {
            terminalOutput.innerHTML = '';
        });
    }

    // Refresh status indicator via AJAX
    function refreshSyncStatus() {
        fetch(<?= json_encode(Url::to(['settings/sync-status'])) ?>)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.sync) {
                    const el = document.getElementById('sync-status-indicator');
                    if (el) {
                        const isSuccess = data.sync.last_success;
                        const badgeClass = isSuccess ? 'success' : 'danger';
                        el.innerHTML = '<span class="badge bg-' + badgeClass + ' bg-opacity-10 text-' + badgeClass + ' border border-' + badgeClass + ' px-2 py-1 small">' +
                            <?= json_encode(\app\helpers\SvgHelper::icon('clock-history', ['class' => 'me-1'])) ?> + ' Terakhir: ' + (data.sync.last_title || '') + ' (' + data.sync.last_time + ')' +
                            '</span>';
                    }
                }
                if (data.market) {
                    const elDaily = document.getElementById('stat-last-daily');
                    if (elDaily && data.market.lastDataAt) {
                        elDaily.innerText = 'Baru saja diperbarui';
                    }
                    const elIntra = document.getElementById('stat-last-intraday');
                    if (elIntra && data.market.lastIntradayDatetime) {
                        elIntra.innerText = data.market.lastIntradayDatetime;
                    }
                }
            })
            .catch(() => {});
    }
});
</script>