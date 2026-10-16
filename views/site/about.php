<?php

/** @var yii\web\View $this */

use yii\bootstrap5\Html;

$this->title = 'Tentang Aplikasi - Stock Analyzer Pro';
$this->params['breadcrumbs'][] = 'About';
?>

<div class="site-about py-4">
    <!-- Header Hero Section -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-dark text-white position-relative" 
         style="background: linear-gradient(135deg, #0b1727 0%, #172554 50%, #0d3b66 100%);">
        <div class="position-absolute rounded-circle" 
             style="width: 320px; height: 320px; background: radial-gradient(circle, rgba(79,70,229,0.3) 0%, rgba(0,0,0,0) 70%); top: -100px; right: -60px; pointer-events: none;"></div>
        <div class="card-body p-4 p-lg-5 position-relative z-1">
            <div class="d-inline-flex align-items-center gap-2 bg-primary bg-opacity-25 border border-primary border-opacity-50 text-info px-3 py-1 rounded-pill small fw-semibold mb-3">
                <?= \app\helpers\SvgHelper::icon('graph-up-arrow') ?> Stock Analyzer Pro
            </div>
            <h1 class="display-6 fw-bold mb-2">Platform Intelijen Pasar &amp; Analisis Saham Terpadu</h1>
            <p class="lead text-white-50 mb-0" style="max-width: 780px;">
                Dirancang untuk membantu investor dan trader menganalisis pasar saham dengan presisi tinggi melalui konfluensi teknikal multi-timeframe, deteksi lonjakan volume (RVOL), pola reversal, dan kalkulator manajemen risiko.
            </p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Kolom Kiri: Fitur & Arsitektur Aplikasi -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
                <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                    <?= \app\helpers\SvgHelper::icon('lightning-charge-fill', ['class' => 'text-primary']) ?>
                    Fokus &amp; Kapabilitas Utama
                </h5>
                <p class="text-muted small">
                    Aplikasi ini mengintegrasikan berbagai model analisis teknikal teruji untuk menyajikan sinyal yang objektif tanpa bias emosional:
                </p>

                <div class="row g-3 mt-1">
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3 h-100 border border-light-subtle">
                            <div class="fw-bold text-primary mb-1 d-flex align-items-center gap-2">
                                <?= \app\helpers\SvgHelper::icon('bullseye') ?>
                                Confluence Strategy
                            </div>
                            <div class="text-muted small">
                                Menggabungkan sinyal Moving Average, RSI Divergence, Fibonacci, dan Price Action untuk probabilitas tertinggi.
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3 h-100 border border-light-subtle">
                            <div class="fw-bold text-success mb-1 d-flex align-items-center gap-2">
                                <?= \app\helpers\SvgHelper::icon('search') ?>
                                Volume &amp; RVOL Scanner
                            </div>
                            <div class="text-muted small">
                                Deteksi anomali akumulasi volume besar (RVOL &ge; 1.5x - 2.0x) sebelum terjadinya pergerakan tren harga masif.
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3 h-100 border border-light-subtle">
                            <div class="fw-bold text-info mb-1 d-flex align-items-center gap-2">
                                <?= \app\helpers\SvgHelper::icon('layers') ?>
                                Multi-Pattern Recognition
                            </div>
                            <div class="text-muted small">
                                Deteksi otomatis pola Double Bottom, Triple Bottom, Cup &amp; Handle, Inverted Head &amp; Shoulders, dan Candlestick.
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3 h-100 border border-light-subtle">
                            <div class="fw-bold text-warning mb-1 d-flex align-items-center gap-2">
                                <?= \app\helpers\SvgHelper::icon('calculator') ?>
                                Averaging &amp; Portfolio
                            </div>
                            <div class="text-muted small">
                                Kalkulator alokasi modal dan rencana averaging down/up terukur dengan batas risiko Stop Loss (SL) yang ketat.
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4 border-light-subtle">

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted small">
                    <span><?= \app\helpers\SvgHelper::icon('code-slash', ['class' => 'me-1 text-primary']) ?> Framework: <strong>Yii2 PHP &bull; Bootstrap 5</strong></span>
                    <span><?= \app\helpers\SvgHelper::icon('database-check', ['class' => 'me-1 text-success']) ?> Data: <strong>Real-time Market Feed &bull; SQLite/MySQL</strong></span>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Profil Pengembang (Developer Profile) -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
                <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                    <?= \app\helpers\SvgHelper::icon('person-badge', ['class' => 'text-primary']) ?>
                    Informasi Pengembang
                </h5>

                <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-4 border border-light-subtle">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle fs-3 fw-bold" 
                         style="width: 58px; height: 58px; flex-shrink: 0;">
                        RAW
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Rohmat Ali Wardani</h5>
                        <span class="text-muted small">Software Engineer &amp; System Architect</span>
                    </div>
                </div>

                <div class="list-group list-group-flush small mb-4">
                    <div class="list-group-item d-flex align-items-center justify-content-between px-0 py-3 border-light-subtle">
                        <span class="text-muted d-flex align-items-center gap-2">
                            <?= \app\helpers\SvgHelper::icon('person-fill', ['class' => 'text-secondary']) ?>
                            Nama Lengkap
                        </span>
                        <strong class="text-dark">Rohmat Ali Wardani</strong>
                    </div>

                    <div class="list-group-item d-flex align-items-center justify-content-between px-0 py-3 border-light-subtle">
                        <span class="text-muted d-flex align-items-center gap-2">
                            <?= \app\helpers\SvgHelper::icon('envelope-fill', ['class' => 'text-danger']) ?>
                            Email
                        </span>
                        <a href="mailto:rohmataliwardani@gmail.com" class="fw-semibold text-decoration-none text-primary">
                            rohmataliwardani@gmail.com
                        </a>
                    </div>

                    <div class="list-group-item d-flex align-items-center justify-content-between px-0 py-3 border-light-subtle">
                        <span class="text-muted d-flex align-items-center gap-2">
                            <?= \app\helpers\SvgHelper::icon('linkedin', ['class' => 'text-primary']) ?>
                            LinkedIn
                        </span>
                        <a href="https://www.linkedin.com/in/rohmat-ali-wardani/" target="_blank" rel="noopener noreferrer" class="fw-semibold text-decoration-none text-primary d-inline-flex align-items-center gap-1">
                            Profil LinkedIn <?= \app\helpers\SvgHelper::icon('box-arrow-up-right', ['class' => 'fs-7']) ?>
                        </a>
                    </div>
                </div>

                <div class="mt-auto pt-3">
                    <a href="https://www.linkedin.com/in/rohmat-ali-wardani/" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary w-100 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm py-2">
                        <?= \app\helpers\SvgHelper::icon('linkedin') ?>
                        Terhubung di LinkedIn
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
