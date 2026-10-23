<?php

/** @var yii\web\View $this */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Tentang Aplikasi - Stock Volume Analyzer';
$this->params['breadcrumbs'][] = 'Tentang Aplikasi';
?>

<div class="site-about py-3" style="max-width: 1040px; margin: 0 auto;">
    <!-- Minimalist Header Banner -->
    <div class="card border border-light-subtle rounded-4 shadow-sm bg-white mb-4 overflow-hidden">
        <div class="card-body p-4 p-md-5">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small fw-semibold mb-3">
                <i class="bi bi-graph-up-arrow"></i> Platform Intelijen Pasar Saham &amp; Kripto
            </div>
            <h1 class="display-6 fw-bold text-dark tracking-tight mb-2">
                Stock Volume Analyzer
            </h1>
            <p class="lead text-muted mb-0" style="max-width: 760px; font-size: 1.05rem; line-height: 1.6;">
                Platform analisis teknikal dan intelijen kuantitatif berbasis volume. Dirancang untuk membantu investor dan trader mengidentifikasi momentum akumulasi institusi, pola konfluensi multi-timeframe, dan manajemen risiko terukur tanpa bias emosional.
            </p>
        </div>
    </div>

    <!-- 4 Pilar Kapabilitas Utama -->
    <div class="mb-4">
        <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-cpu text-primary"></i> Kapabilitas &amp; Algoritma Utama
        </h5>
        <div class="row g-3">
            <div class="col-md-6 col-lg-3">
                <div class="card border border-light-subtle rounded-3 h-100 p-3 bg-white shadow-none hover-card">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-primary bg-opacity-10 text-primary mb-2" style="width: 36px; height: 36px;">
                        <i class="bi bi-bullseye fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Confluence Strategy</h6>
                    <p class="text-muted small mb-0" style="line-height: 1.5;">
                        Harmonisasi sinyal Moving Average, RSI Divergence, Fibonacci, dan Price Action untuk probabilitas reversal tertinggi.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card border border-light-subtle rounded-3 h-100 p-3 bg-white shadow-none hover-card">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-success bg-opacity-10 text-success mb-2" style="width: 36px; height: 36px;">
                        <i class="bi bi-search fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Volume &amp; RVOL Flow</h6>
                    <p class="text-muted small mb-0" style="line-height: 1.5;">
                        Mendeteksi anomali lonjakan volume akumulasi (RVOL &ge; 1.5x) sebelum terjadinya ekspansi tren harga besar.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card border border-light-subtle rounded-3 h-100 p-3 bg-white shadow-none hover-card">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-info bg-opacity-10 text-info mb-2" style="width: 36px; height: 36px;">
                        <i class="bi bi-layers fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Pattern Recognition</h6>
                    <p class="text-muted small mb-0" style="line-height: 1.5;">
                        Deteksi otomatis pola Double Bottom, Triple Bottom, Bullish Continuation, dan Candlestick multi-timeframe.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card border border-light-subtle rounded-3 h-100 p-3 bg-white shadow-none hover-card">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-warning bg-opacity-10 text-warning mb-2" style="width: 36px; height: 36px;">
                        <i class="bi bi-calculator fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Portofolio &amp; DCA Plan</h6>
                    <p class="text-muted small mb-0" style="line-height: 1.5;">
                        Kalkulator pemulihan posisi saham, alokasi bertingkat (Dollar Cost Averaging), serta batasan Stop Loss rasional.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Profil Pengembang & Dukungan gmteknologi.com -->
    <div class="row g-4 mb-4">
        <!-- Kolom Pengembang -->
        <div class="col-lg-6">
            <div class="card border border-light-subtle rounded-4 h-100 p-4 bg-white shadow-sm">
                <h6 class="fw-bold text-uppercase small text-muted mb-3 tracking-wider">
                    <i class="bi bi-person-badge text-primary me-1"></i> Arsitek &amp; Pengembang Aplikasi
                </h6>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 52px; height: 52px; flex-shrink: 0;">
                        RAW
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Rohmat Ali Wardani</h5>
                        <span class="text-muted small">Software Engineer &amp; System Architect</span>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2 pt-2 border-top border-light-subtle small mb-4">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted"><i class="bi bi-envelope text-danger me-1"></i> Email:</span>
                        <a href="mailto:rohmataliwardani@gmail.com" class="fw-semibold text-decoration-none text-primary">
                            rohmataliwardani@gmail.com
                        </a>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted"><i class="bi bi-linkedin text-primary me-1"></i> LinkedIn:</span>
                        <a href="https://www.linkedin.com/in/rohmat-ali-wardani/" target="_blank" rel="noopener noreferrer" class="fw-semibold text-decoration-none text-primary">
                            rohmat-ali-wardani <i class="bi bi-box-arrow-up-right" style="font-size: 0.7rem;"></i>
                        </a>
                    </div>
                </div>

                <div class="mt-auto">
                    <a href="https://www.linkedin.com/in/rohmat-ali-wardani/" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm w-100 rounded-pill py-2 fw-semibold">
                        <i class="bi bi-linkedin me-1"></i> Kunjungi Profil LinkedIn
                    </a>
                </div>
            </div>
        </div>

        <!-- Kolom Teknologi & Partner gmteknologi.com -->
        <div class="col-lg-6">
            <div class="card border border-light-subtle rounded-4 h-100 p-4 bg-white shadow-sm">
                <h6 class="fw-bold text-uppercase small text-muted mb-3 tracking-wider">
                    <i class="bi bi-buildings text-primary me-1"></i> Didukung &amp; Dikembangkan Oleh
                </h6>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 52px; height: 52px; flex-shrink: 0; background: linear-gradient(135deg, #0d6efd, #0f172a) !important;">
                        GM
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">gmteknologi.com</h5>
                        <span class="text-muted small">Technology Solutions &amp; Intelligent Software</span>
                    </div>
                </div>

                <p class="small text-muted mb-3" style="line-height: 1.6;">
                    Menyediakan arsitektur perangkat lunak andal, otomatisasi data analitis, solusi berbasis kecerdasan buatan (AI), serta infrastruktur cloud berkinerja tinggi untuk mendukung pertumbuhan bisnis digital.
                </p>

                <div class="mt-auto pt-2 border-top border-light-subtle">
                    <a href="https://gmteknologi.com" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm w-100 rounded-pill py-2 fw-semibold shadow-sm">
                        <i class="bi bi-globe me-1"></i> Jelajahi gmteknologi.com <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.7rem;"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Spesifikasi Arsitektur Sistem Minimalis -->
    <div class="card border border-light-subtle rounded-4 bg-light p-3 mb-4 shadow-none">
        <div class="row g-2 text-muted small align-items-center text-center text-md-start">
            <div class="col-md-4">
                <i class="bi bi-code-slash text-primary me-1"></i> Core: <strong>Yii2 PHP &bull; Bootstrap 5</strong>
            </div>
            <div class="col-md-4 text-md-center">
                <i class="bi bi-hdd-network text-success me-1"></i> Feed: <strong>Yahoo Finance Provider</strong>
            </div>
            <div class="col-md-4 text-md-end">
                <i class="bi bi-database-check text-info me-1"></i> Storage: <strong>MySQL &bull; SQLite Engine</strong>
            </div>
        </div>
    </div>

    <!-- Disclaimer & Peringatan Risiko -->
    <div class="p-3 rounded-3 bg-white border border-light-subtle text-muted small" style="font-size: 0.78rem; line-height: 1.6;">
        <span class="fw-bold text-secondary">Pernyataan Risiko (Risk Disclaimer):</span>
        Pasar saham dan instrumen kripto memiliki volatilitas dan risiko kerugian modal yang nyata. Seluruh indikator, chart pola teknikal, dan ringkasan yang dihasilkan pada aplikasi ini ditujukan secara eksklusif sebagai sarana riset mandiri dan edukasi kuantitatif. Keputusan eksekusi transaksi beli atau jual sepenuhnya berada di bawah tanggung jawab masing-masing investor.
    </div>
</div>

<style>
.hover-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06) !important;
}
</style>
