<?php

/** @var yii\web\View $this */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Stock Volume & Pattern Analyzer';
?>
<div class="site-index">
    <!-- Hero Header -->
    <div class="p-5 mb-4 bg-light rounded-3 border shadow-sm">
        <div class="container-fluid py-2">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-primary px-3 py-2 rounded-pill mb-2">Institutional Volume &amp; Pattern Intelligence</span>
                    <h1 class="display-5 fw-bold text-dark mb-3">Stock Volume &amp; Reversal Analyzer</h1>
                    <p class="col-md-10 fs-5 text-muted">
                        Platform analisis kuantitatif dan teknikal berbasis <strong>Volume Breakdown</strong> dan pola pembalikan arah <strong>Double Bottom</strong> untuk menemukan peluang transaksi saham dengan Risk-to-Reward terukur.
                    </p>
                    <div class="d-flex flex-wrap gap-2 pt-2">
                        <?= Html::a('📉 Buka Double Bottom Scanner', ['/double-bottom/index'], ['class' => 'btn btn-primary btn-lg px-4 shadow-sm']) ?>
                        <?= Html::a('📊 Lihat Dashboard Pasar', ['/dashboard/index'], ['class' => 'btn btn-outline-dark btn-lg px-4']) ?>
                        <?= Html::a('🔍 Scanner Volume', ['/scanner/index'], ['class' => 'btn btn-outline-secondary btn-lg px-3']) ?>
                    </div>
                </div>
                <div class="col-lg-4 text-center d-none d-lg-block">
                    <div class="card border-primary shadow-sm">
                        <div class="card-body p-4">
                            <div class="text-primary mb-2" style="font-size: 3rem;">📉</div>
                            <h5 class="fw-bold">Pola Double Bottom</h5>
                            <p class="small text-muted mb-3">Deteksi otomatis formasi W, konfirmasi volume akumulasi, zona breakout, serta rasio R:R multi-target.</p>
                            <?= Html::a('Analisis Sekarang &raquo;', ['/double-bottom/index'], ['class' => 'btn btn-sm btn-primary w-100']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feature Grid / Menu Hub -->
    <div class="row g-4 mb-4">
        <!-- Feature 1: Double Bottom -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-primary border-top border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary">Fitur Unggulan</span>
                        <span class="text-muted small">Multi-Timeframe</span>
                    </div>
                    <h4 class="card-title fw-bold">📉 Double Bottom Scanner</h4>
                    <p class="card-text text-muted">
                        Pindai pola W klasik pada 1H, 2H, 4H, dan Daily. Dilengkapi kalkulasi Entry Zone, Multi-Target (TP1, TP2, TP3), Tight Stop Loss, konfirmasi volume, dan kalkulator posisi.
                    </p>
                    <?= Html::a('Buka Double Bottom &raquo;', ['/double-bottom/index'], ['class' => 'btn btn-outline-primary w-100']) ?>
                </div>
            </div>
        </div>

        <!-- Feature 2: RSI Double Bottom -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-info border-top border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-info text-dark">Momentum</span>
                        <span class="text-muted small">Oversold Reversal</span>
                    </div>
                    <h4 class="card-title fw-bold">📉 RSI Double Bottom</h4>
                    <p class="card-text text-muted">
                        Kombinasi pola dasar harga dengan indikator RSI momentum divergensi di area oversold untuk konfirmasi pembalikan arah tren yang kuat.
                    </p>
                    <?= Html::a('Buka RSI Double Bottom &raquo;', ['/rsi-double-bottom/index'], ['class' => 'btn btn-outline-info w-100 text-dark']) ?>
                </div>
            </div>
        </div>

        <!-- Feature 3: Volume Scanner -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-success border-top border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-success">Volume Flow</span>
                        <span class="text-muted small">Weekly &amp; Daily</span>
                    </div>
                    <h4 class="card-title fw-bold">🔍 Volume Scanner</h4>
                    <p class="card-text text-muted">
                        Saring saham berdasarkan tekanan beli institusi (Buy Ratio), lonjakan volume relatif (RVOL), kapitalisasi pasar, dan sektor.
                    </p>
                    <?= Html::a('Buka Volume Scanner &raquo;', ['/scanner/index'], ['class' => 'btn btn-outline-success w-100']) ?>
                </div>
            </div>
        </div>

        <!-- Feature 4: Market Dashboard -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-secondary border-top border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-secondary">Ringkasan</span>
                        <span class="text-muted small">Pasar Berjalan</span>
                    </div>
                    <h4 class="card-title fw-bold">📊 Market Dashboard</h4>
                    <p class="card-text text-muted">
                        Pantau volume total pasar, rasio tekanan beli keseluruhan, top buying/selling pressure, serta distribusi sinyal aktif minggu ini.
                    </p>
                    <?= Html::a('Lihat Dashboard &raquo;', ['/dashboard/index'], ['class' => 'btn btn-outline-secondary w-100']) ?>
                </div>
            </div>
        </div>

        <!-- Feature 5: Sector Performance -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-warning border-top border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-warning text-dark">Rotasi Sektor</span>
                        <span class="text-muted small">Heatmap</span>
                    </div>
                    <h4 class="card-title fw-bold">🗺️ Sector Analysis</h4>
                    <p class="card-text text-muted">
                        Identifikasi sektor mana yang sedang dimasuki dana institusional dan sektor mana yang mengalami distribusi tekanan jual.
                    </p>
                    <?= Html::a('Buka Sektor &raquo;', ['/sector/index'], ['class' => 'btn btn-outline-warning w-100 text-dark']) ?>
                </div>
            </div>
        </div>

        <!-- Feature 6: Backtesting Engine -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-dark border-top border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-dark">Validasi Data</span>
                        <span class="text-muted small">Historis</span>
                    </div>
                    <h4 class="card-title fw-bold">🧪 Backtesting Engine</h4>
                    <p class="card-text text-muted">
                        Uji strategi parameter volume dan pola sinyal terhadap data riwayat harga historis untuk mengukur win rate, profit factor, dan max drawdown.
                    </p>
                    <?= Html::a('Buka Backtest &raquo;', ['/backtest/index'], ['class' => 'btn btn-outline-dark w-100']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

