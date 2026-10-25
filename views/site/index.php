<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Stock Volume & Pattern Analyzer | Platform Intelijen Pasar Saham US & Crypto';

// SEO Meta Tags Halaman Utama
$this->registerMetaTag([
    'name' => 'description',
    'content' => 'Platform intelijen kuantitatif pasar modal US & Crypto 24/7. Deteksi lonjakan volume institusi (RVOL), pola pembalikan arah teknikal, kalkulator averaging portofolio, dan trading plan otomatis.',
]);
$this->registerMetaTag(['name' => 'keywords', 'content' => 'scanner saham, volume breakout, relative volume, rvol, double bottom, trading plan, averaging saham, pasar saham us, wall street, analisa saham']);
$this->registerMetaTag(['property' => 'og:title', 'content' => $this->title]);
$this->registerMetaTag(['property' => 'og:description', 'content' => 'Deteksi akumulasi volume institusi dan trading plan berdisiplin tinggi untuk saham Wall Street & Crypto.']);
$this->registerMetaTag(['property' => 'og:type', 'content' => 'website']);
$this->registerMetaTag(['property' => 'og:url', 'content' => Url::home(true)]);
?>

<div class="landing-page-wrapper pb-5">
    <!-- ====================================================================
         1. HERO SECTION: Luminous, Clean, High-Conversion Fintech Banner
         ==================================================================== -->
    <section class="hero-section p-4 p-md-5 mb-5 rounded-4 shadow-sm position-relative overflow-hidden">
        <div class="hero-mesh-glow"></div>
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-7">
                <!-- Live Radar Pill -->
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white border shadow-xs mb-3">
                    <span class="live-pulse-dot"></span>
                    <span class="fw-semibold text-slate-700 small" style="color: #334155;">
                        Platform Intelijen Kuantitatif Saham US &amp; Crypto 24/7
                    </span>
                </div>

                <h1 class="display-5 fw-bold text-slate-900 mb-3 hero-headline">
                    Temukan Peluang Saham Wall Street Sebelum Reli Dimulai dengan <span class="text-gradient-brand">Validasi Volume Institusi</span>
                </h1>

                <p class="lead text-slate-600 mb-4 hero-subhead" style="max-width: 650px; color: #475569; font-size: 1.1rem; line-height: 1.6;">
                    Saring 8.000+ saham Wall Street dalam hitungan detik. Deteksi dini jejak dana besar (<em>Smart Money</em>) lewat Relative Volume (RVOL), formasi pembalikan arah teknikal, dan eksekusi dengan <strong>Trading Plan berdisiplin tinggi</strong>.
                </p>

                <!-- Action CTA Buttons -->
                <div class="d-flex flex-wrap gap-2.5 mb-4 pt-1">
                    <a href="<?= Url::to(['/scanner/index']) ?>" class="btn btn-primary btn-lg rounded-pill px-4 py-2.5 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                        <i class="bi bi-search"></i>
                        <span>Buka Scanner Gratis</span>
                    </a>
                    <a href="<?= Url::to(['/portfolio/index']) ?>" class="btn btn-outline-success btn-lg rounded-pill px-4 py-2.5 fw-bold bg-white shadow-xs d-inline-flex align-items-center gap-2">
                        <i class="bi bi-briefcase-fill text-emerald"></i>
                        <span>Portofolio &amp; Averaging</span>
                    </a>
                    <a href="<?= Url::to(['/blog/index']) ?>" class="btn btn-light btn-lg rounded-pill px-3.5 py-2.5 fw-semibold border shadow-xs d-inline-flex align-items-center gap-2 text-slate-700">
                        <i class="bi bi-newspaper text-primary"></i>
                        <span>Riset Saham AI</span>
                    </a>
                </div>

                <!-- Trust Micro-Ribbon -->
                <div class="d-flex flex-wrap align-items-center gap-3 pt-2 text-slate-500 small">
                    <span class="d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-check-circle-fill text-emerald"></i> 8,000+ Emiten NASDAQ &amp; NYSE
                    </span>
                    <span class="d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-check-circle-fill text-emerald"></i> Rasio Risk/Reward Min. 1:2
                    </span>
                    <span class="d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-check-circle-fill text-emerald"></i> 100% Gratis Akses Mandiri
                    </span>
                </div>
            </div>

            <!-- Terminal Interactive Preview Card (Right Column) -->
            <div class="col-lg-5 mt-4 mt-lg-0">
                <div class="hero-terminal-card p-4 rounded-4 shadow-lg position-relative">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-white border-opacity-10">
                        <div class="d-flex align-items-center gap-2">
                            <span class="live-pulse-dot"></span>
                            <span class="text-white fw-bold small text-uppercase tracking-wider" style="font-size: 0.75rem;">RADAR VOLUME REAL-TIME</span>
                        </div>
                        <span class="badge bg-emerald-subtle text-emerald border border-emerald border-opacity-25 px-2.5 py-1 small fw-bold">
                            Akumulasi Kuat
                        </span>
                    </div>

                    <!-- Stock Featured Simulation Item -->
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <div class="h4 fw-bold text-white mb-0">NVDA <span class="badge bg-primary bg-opacity-30 text-cyan-accent ms-1 fs-6">1D</span></div>
                            <span class="text-slate-400 small">NVIDIA Corporation &bull; Tech / AI</span>
                        </div>
                        <div class="text-end">
                            <div class="h4 fw-bold text-emerald mb-0">$143.50</div>
                            <span class="small text-emerald fw-semibold"><i class="bi bi-arrow-up-right"></i> +3.82%</span>
                        </div>
                    </div>

                    <!-- Metrics Grid -->
                    <div class="row g-2 text-center mb-3">
                        <div class="col-4">
                            <div class="p-2 rounded-3 bg-white bg-opacity-5 border border-white border-opacity-10">
                                <div class="text-slate-400 small" style="font-size: 0.7rem;">RVOL (20D)</div>
                                <div class="fw-bold text-warning fs-6">2.8x</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3 bg-white bg-opacity-5 border border-white border-opacity-10">
                                <div class="text-slate-400 small" style="font-size: 0.7rem;">SETUP</div>
                                <div class="fw-bold text-info fs-6">Confluence</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3 bg-white bg-opacity-5 border border-white border-opacity-10">
                                <div class="text-slate-400 small" style="font-size: 0.7rem;">RISK / REWARD</div>
                                <div class="fw-bold text-emerald fs-6">1:2.8</div>
                            </div>
                        </div>
                    </div>

                    <!-- Trading Plan Snapshot Strip -->
                    <div class="p-2.5 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-15 small text-white mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-slate-300">🎯 Target Profit (TP):</span>
                            <strong class="text-emerald">$158.00 (+10.1%)</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-slate-300">🛡️ Proteksi Stop Loss (SL):</span>
                            <strong class="text-rose">$136.50 (-4.8%)</strong>
                        </div>
                    </div>

                    <!-- Institutional Pressure Bar -->
                    <div>
                        <div class="d-flex justify-content-between small text-slate-300 mb-1" style="font-size: 0.75rem;">
                            <span>Tekanan Beli Institusi (Buy Ratio)</span>
                            <span class="text-emerald fw-bold">78% Buying Flow</span>
                        </div>
                        <div class="progress" style="height: 6px; background-color: rgba(255,255,255,0.1);">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 78%;" aria-valuenow="78" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         2. VALUE PROPOSITION: APLIKASI APAKAH INI & APAKAH MEMBANTU MEREKA?
         ==================================================================== -->
    <section class="mb-5 py-3">
        <div class="text-center max-w-750 mx-auto mb-5">
            <span class="badge badge-soft-primary px-3 py-1.5 rounded-pill fw-bold text-uppercase mb-2">
                Mengapa Stock Analyzer Pro?
            </span>
            <h2 class="display-6 fw-bold text-slate-900 mb-2">
                Solusi Cerdas untuk Masalah Terbesar Trader Saham
            </h2>
            <p class="text-slate-600 fs-6">
                Kebanyakan trader merugi bukan karena kurang pintar, tetapi karena kelelahan menyaring grafik manual, membeli karena FOMO emosional, dan tidak memiliki rencana keluar (*Exit Plan*) yang terukur.
            </p>
        </div>

        <div class="row g-4 align-items-stretch">
            <!-- Kolom Masalah Tradisional -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-4 bg-white border-start border-danger border-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="avatar-icon-box bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-x-circle-fill"></i>
                        </span>
                        <h3 class="h5 fw-bold text-danger mb-0">Cara Lama yang Melelahkan &amp; Sering Rugi</h3>
                    </div>
                    <ul class="list-unstyled d-flex flex-column gap-3 mb-0 text-slate-600 small" style="font-size: 0.92rem;">
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-dash-circle text-danger mt-1"></i>
                            <div><strong>Analisis Grafik Manual Berjam-jam:</strong> Membuka 8.000 chart satu per satu hingga larut malam tanpa jaminan menemukan setup yang valid.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-dash-circle text-danger mt-1"></i>
                            <div><strong>Terjebak Pembelian di Pucuk (FOMO):</strong> Masuk saat harga sudah naik tinggi karena terpancing berita, lalu harga berbalik turun (*bull trap*).</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-dash-circle text-danger mt-1"></i>
                            <div><strong>Tidak Ada Batas Risiko Jelas:</strong> Menahan floating loss terlalu lama dengan harapan harga kembali, namun langsung panik taking profit terlalu cepat.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-dash-circle text-danger mt-1"></i>
                            <div><strong>Average Down Tanpa Perhitungan:</strong> Menambah modal secara acak saat saham turun drastis hingga dana terkunci dan margin habis.</div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Kolom Solusi Modern -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-4 bg-white border-start border-emerald border-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="avatar-icon-box bg-emerald-subtle text-emerald">
                            <i class="bi bi-check-circle-fill"></i>
                        </span>
                        <h3 class="h5 fw-bold text-emerald mb-0">Dengan Stock Analyzer Pro: Otomatis &amp; Disiplin</h3>
                    </div>
                    <ul class="list-unstyled d-flex flex-column gap-3 mb-0 text-slate-600 small" style="font-size: 0.92rem;">
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-check2-circle text-emerald mt-1 fs-6"></i>
                            <div><strong>Radar Volume Otomatis Seketika:</strong> Scanner menyortir ribuan saham dan memunculkan hanya emiten dengan lonjakan volume institusi nyata (RVOL &gt; 1.5x).</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-check2-circle text-emerald mt-1 fs-6"></i>
                            <div><strong>Menunggangi Arus Dana Besar:</strong> Masuk di fase akumulasi awal bersama institusi, bukan mengejar harga yang sudah overbought.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-check2-circle text-emerald mt-1 fs-6"></i>
                            <div><strong>Trading Plan Otomatis (Entry, TP, SL):</strong> Target profit bertahap (TP1, TP2, TP3) dan batas risiko terhitung matematis sebelum klik order.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-check2-circle text-emerald mt-1 fs-6"></i>
                            <div><strong>Averaging Planner &amp; DCA Terstruktur:</strong> Simulasi penambahan lot di level support krusial untuk menurunkan modal rata-rata secara aman.</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         3. HOW IT MAKES YOU PROFITABLE (APAKAH MENGUNTUNGKAN MEREKA?)
         ==================================================================== -->
    <section class="mb-5 py-4 px-3 px-md-4 rounded-4 bg-white border shadow-sm">
        <div class="text-center max-w-750 mx-auto mb-5">
            <span class="badge badge-soft-emerald px-3 py-1.5 rounded-pill fw-bold text-uppercase mb-2">
                Keunggulan Finansial
            </span>
            <h2 class="display-6 fw-bold text-slate-900 mb-2">
                3 Cara Aplikasi Ini Menghasilkan Keuntungan Nyata untuk Anda
            </h2>
            <p class="text-slate-600 fs-6">
                Sistem kami dirancang bukan untuk berjudi (*gambling*), melainkan menerapkan prinsip probabilitas kuantitatif yang digunakan oleh fund manager Wall Street.
            </p>
        </div>

        <div class="row g-4">
            <!-- Pilar 1 -->
            <div class="col-md-4">
                <div class="p-4 rounded-4 bg-slate-50 border h-100 d-flex flex-column feature-benefit-box">
                    <div class="feature-benefit-icon bg-primary text-white mb-3">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h3 class="h5 fw-bold text-slate-900 mb-2">1. Masuk di Awal Lonjakan Institusi</h3>
                    <p class="text-slate-600 small mb-0 flex-grow-1" style="line-height: 1.6;">
                        Institusi besar tidak bisa menyembunyikan volume pembelian mereka. Indikator <strong>Relative Volume (RVOL)</strong> mendeteksi jejak akumulasi sebelum harga melesat, memungkinkan Anda mengambil posisi sebelum ritel lain menyadarinya.
                    </p>
                </div>
            </div>

            <!-- Pilar 2 -->
            <div class="col-md-4">
                <div class="p-4 rounded-4 bg-slate-50 border h-100 d-flex flex-column feature-benefit-box">
                    <div class="feature-benefit-icon bg-emerald text-white mb-3" style="background-color: #059669;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h3 class="h5 fw-bold text-slate-900 mb-2">2. Menjaga Rasio Risk/Reward Positif</h3>
                    <p class="text-slate-600 small mb-0 flex-grow-1" style="line-height: 1.6;">
                        Dengan memastikan setiap transaksi memiliki rasio Risk/Reward minimal <strong>1:2 hingga 1:3</strong>, Anda tetap membukukan keuntungan bersih konsisten meskipun hanya memenangkan separuh (50%) dari total transaksi Anda.
                    </p>
                </div>
            </div>

            <!-- Pilar 3 -->
            <div class="col-md-4">
                <div class="p-4 rounded-4 bg-slate-50 border h-100 d-flex flex-column feature-benefit-box">
                    <div class="feature-benefit-icon bg-warning text-dark mb-3">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <h3 class="h5 fw-bold text-slate-900 mb-2">3. Recovery Posisi dengan DCA Presisi</h3>
                    <p class="text-slate-600 small mb-0 flex-grow-1" style="line-height: 1.6;">
                        Fitur <strong>Averaging Planner</strong> menghitung secara presisi berapa jumlah lembar saham yang harus dibeli di area support agar harga rata-rata turun drastis, sehingga portofolio dapat keluar dari posisi minus dan berbalik untung lebih cepat.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         4. CORE FEATURE SUITE (TERMINAL TOOLS & SCANNER HUB)
         ==================================================================== -->
    <section class="mb-5">
        <div class="d-flex flex-wrap align-items-end justify-content-between mb-4">
            <div>
                <span class="badge badge-soft-primary px-3 py-1 rounded-pill fw-bold text-uppercase mb-1">
                    Terminal Fitur Lengkap
                </span>
                <h2 class="h3 fw-bold text-slate-900 mb-0">Alat Analisis Kuantitatif Siap Pakai</h2>
            </div>
            <a href="<?= Url::to(['/scanner/index']) ?>" class="btn btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold small mt-2 mt-sm-0">
                Lihat Semua Scanner <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <!-- Fitur 1: Portofolio & Averaging -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 bg-white feature-card transition-all">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-emerald-subtle text-emerald border border-emerald border-opacity-25 px-2.5 py-1">Manajemen Modal</span>
                        <span class="badge bg-light text-slate-500 border">Profit &amp; Recovery</span>
                    </div>
                    <h4 class="h5 fw-bold text-slate-900 mb-2">💼 Portofolio &amp; Averaging Planner</h4>
                    <p class="text-slate-600 small mb-4 flex-grow-1" style="line-height: 1.6;">
                        Pantau posisi saham realtime, tetapkan target profit otomatis (cth: +3%), dan simulasikan strategi Average Down bertahap untuk recovery modal optimal.
                    </p>
                    <a href="<?= Url::to(['/portfolio/index']) ?>" class="btn btn-outline-success rounded-pill w-100 fw-bold">
                        Buka Portofolio Planner <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Fitur 2: Confluence Strategy -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 bg-white feature-card transition-all">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1">Probabilitas Tinggi</span>
                        <span class="badge bg-light text-slate-500 border">Multi-Indikator</span>
                    </div>
                    <h4 class="h5 fw-bold text-slate-900 mb-2">🎯 Confluence Strategy Scanner</h4>
                    <p class="text-slate-600 small mb-4 flex-grow-1" style="line-height: 1.6;">
                        Kombinasi 4 lapis konfirmasi: Pola Reversal, RSI Divergence di area oversold, Golden Ratio Fibonacci 61.8%, serta Moving Average Alignment.
                    </p>
                    <a href="<?= Url::to(['/strategy-confluence/index']) ?>" class="btn btn-outline-primary rounded-pill w-100 fw-bold">
                        Buka Confluence Scanner <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Fitur 3: Volume & RVOL Scanner -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 bg-white feature-card transition-all">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-warning bg-opacity-15 text-dark border border-warning border-opacity-25 px-2.5 py-1">Smart Money Flow</span>
                        <span class="badge bg-light text-slate-500 border">Daily &amp; Weekly</span>
                    </div>
                    <h4 class="h5 fw-bold text-slate-900 mb-2">🔍 Volume &amp; RVOL Scanner</h4>
                    <p class="text-slate-600 small mb-4 flex-grow-1" style="line-height: 1.6;">
                        Lacak tekanan beli institusi (Buy Ratio), lonjakan volume relatif di atas rata-rata 20 hari, kapitalisasi pasar, dan rotasi sektor terpanas.
                    </p>
                    <a href="<?= Url::to(['/scanner/index']) ?>" class="btn btn-outline-warning text-dark rounded-pill w-100 fw-bold">
                        Buka Volume Scanner <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Fitur 4: Double Bottom Scanner -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 bg-white feature-card transition-all">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-info bg-opacity-15 text-info border border-info border-opacity-25 px-2.5 py-1">Pola Reversal Klasik</span>
                        <span class="badge bg-light text-slate-500 border">1H, 2H, 4H, 1D</span>
                    </div>
                    <h4 class="h5 fw-bold text-slate-900 mb-2">📉 Double Bottom Core Scanner</h4>
                    <p class="text-slate-600 small mb-4 flex-grow-1" style="line-height: 1.6;">
                        Deteksi otomatis formasi W di area support tahunan, lengkap dengan level konfirmasi breakout neckline dan target profit bertahap (TP1, TP2, TP3).
                    </p>
                    <a href="<?= Url::to(['/double-bottom/index']) ?>" class="btn btn-outline-info text-dark rounded-pill w-100 fw-bold">
                        Buka Double Bottom <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Fitur 5: AI Blog Info & Analisis Saham -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 bg-white feature-card transition-all">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-indigo-subtle text-indigo px-2.5 py-1">Riset Dwibahasa</span>
                        <span class="badge bg-light text-slate-500 border">AI Quantitative</span>
                    </div>
                    <h4 class="h5 fw-bold text-slate-900 mb-2">📰 AI Blog Info &amp; Trading Plan</h4>
                    <p class="text-slate-600 small mb-4 flex-grow-1" style="line-height: 1.6;">
                        Baca ulasan teknikal harian emiten pilihan Wall Street lengkap dengan skenario konfirmasi, invalidasi stop loss, serta penjelasan bahasa Indonesia yang ramah pembaca.
                    </p>
                    <a href="<?= Url::to(['/blog/index']) ?>" class="btn btn-outline-primary rounded-pill w-100 fw-bold">
                        Buka Artikel Riset AI <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Fitur 6: Crypto Market 24/7 -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 bg-white feature-card transition-all">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2.5 py-1">Non-Stop Market</span>
                        <span class="badge bg-light text-slate-500 border">Crypto Hub</span>
                    </div>
                    <h4 class="h5 fw-bold text-slate-900 mb-2">🪙 Crypto Market Scanner 24/7</h4>
                    <p class="text-slate-600 small mb-4 flex-grow-1" style="line-height: 1.6;">
                        Pantau lonjakan volume token dan konfluensi teknikal pasar aset digital secara realtime tanpa henti sepanjang hari kerja maupun akhir pekan.
                    </p>
                    <a href="<?= Url::to(['/crypto/index']) ?>" class="btn btn-outline-success rounded-pill w-100 fw-bold">
                        Buka Crypto Scanner <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         5. HOW TO GET STARTED IN 3 SIMPLE STEPS
         ==================================================================== -->
    <section class="mb-5 py-4 px-3 px-md-4 rounded-4 bg-slate-50 border">
        <div class="text-center max-w-750 mx-auto mb-4">
            <h2 class="h3 fw-bold text-slate-900 mb-2">Cara Memulai dalam 3 Langkah Sederhana</h2>
            <p class="text-slate-600 small">Tidak butuh instalasi rumit. Platform berjalan langsung di browser perangkat Anda.</p>
        </div>

        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="step-badge-number mb-3">1</div>
                <h4 class="h6 fw-bold text-slate-900 mb-1">Pilih Scanner &amp; Filter Setup</h4>
                <p class="text-slate-500 small mb-0">Buka menu Scanner Volume, Confluence, atau Double Bottom untuk melihat saham yang sedang diakumulasi.</p>
            </div>
            <div class="col-md-4">
                <div class="step-badge-number mb-3">2</div>
                <h4 class="h6 fw-bold text-slate-900 mb-1">Cek Batas Risiko &amp; Target</h4>
                <p class="text-slate-500 small mb-0">Periksa level Entry, Stop Loss ketat, dan Target TP untuk memastikan rasio Risk/Reward minimal 1:2.</p>
            </div>
            <div class="col-md-4">
                <div class="step-badge-number mb-3">3</div>
                <h4 class="h6 fw-bold text-slate-900 mb-1">Eksekusi &amp; Kelola Portofolio</h4>
                <p class="text-slate-500 small mb-0">Simpan di Portofolio Tracker untuk mengunci target profit disiplin dan rencana DCA jika diperlukan.</p>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         6. FAQ: PERTANYAAN UMUM CUSTOMER
         ==================================================================== -->
    <section class="mb-5">
        <div class="text-center max-w-750 mx-auto mb-4">
            <span class="badge badge-soft-primary px-3 py-1 rounded-pill fw-bold text-uppercase mb-1">
                Tanya Jawab
            </span>
            <h2 class="h3 fw-bold text-slate-900 mb-2">Pertanyaan yang Sering Diajukan</h2>
            <p class="text-slate-600 small">Semua yang perlu Anda ketahui mengenai cara kerja dan akses platform.</p>
        </div>

        <div class="accordion accordion-flush bg-white rounded-4 border shadow-sm p-2" id="faqAccordion">
            <div class="accordion-item border-bottom">
                <h3 class="accordion-header" id="faq1">
                    <button class="accordion-button collapsed fw-bold text-slate-800" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFaq1" aria-expanded="false" aria-controls="collapseFaq1">
                        Apakah platform ini gratis untuk digunakan?
                    </button>
                </h3>
                <div id="collapseFaq1" class="accordion-collapse collapse" aria-labelledby="faq1" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-slate-600 small" style="line-height: 1.6;">
                        Ya! Anda dapat langsung menggunakan fitur Scanner Volume, Pola Double Bottom, Confluence Strategy, dan membaca seluruh riset di Blog Info Saham AI secara gratis tanpa dipungut biaya langganan bulanan.
                    </div>
                </div>
            </div>

            <div class="accordion-item border-bottom">
                <h3 class="accordion-header" id="faq2">
                    <button class="accordion-button collapsed fw-bold text-slate-800" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFaq2" aria-expanded="false" aria-controls="collapseFaq2">
                        Bursa saham mana saja yang dipindai oleh sistem?
                    </button>
                </h3>
                <div id="collapseFaq2" class="accordion-collapse collapse" aria-labelledby="faq2" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-slate-600 small" style="line-height: 1.6;">
                        Stock Analyzer Pro memindai seluruh emiten aktif di bursa saham Amerika Serikat (Wall Street) yang terdaftar di <strong>NASDAQ, NYSE, dan AMEX</strong> (lebih dari 8.000 emiten), ditambah instrumen aset digital Crypto 24/7.
                    </div>
                </div>
            </div>

            <div class="accordion-item border-bottom">
                <h3 class="accordion-header" id="faq3">
                    <button class="accordion-button collapsed fw-bold text-slate-800" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFaq3" aria-expanded="false" aria-controls="collapseFaq3">
                        Mengapa indikator Relative Volume (RVOL) sangat penting?
                    </button>
                </h3>
                <div id="collapseFaq3" class="accordion-collapse collapse" aria-labelledby="faq3" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-slate-600 small" style="line-height: 1.6;">
                        Harga saham digerakkan oleh institusi besar (hedge fund, investment bank, asset manager). Ketika Relative Volume melonjak drastis di atas rata-rata 20 hari, itu menandakan adanya aliran dana besar masuk. Mengikuti arah institusi jauh lebih berpeluang untung daripada menebak pergerakan harga tanpa dukungan volume.
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h3 class="accordion-header" id="faq4">
                    <button class="accordion-button collapsed fw-bold text-slate-800" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFaq4" aria-expanded="false" aria-controls="collapseFaq4">
                        Apakah pemula bisa memahami hasil analisa aplikasi ini?
                    </button>
                </h3>
                <div id="collapseFaq4" class="accordion-collapse collapse" aria-labelledby="faq4" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-slate-600 small" style="line-height: 1.6;">
                        Tentu saja! Setiap sinyal telah dilengkapi dengan batas Target Price (TP), Stop Loss (SL), dan rasio Risk/Reward yang jelas, serta artikel ulasan AI dwibahasa (Indonesia &amp; Inggris) yang menjelaskan alasan di balik setiap setup dengan bahasa yang mudah dipahami.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         7. FINAL CONVERSION CTA BANNER: EMERALD & INDIGO GRADIENT
         ==================================================================== -->
    <section class="final-cta-banner p-4 p-md-5 rounded-4 shadow-sm position-relative overflow-hidden text-center text-md-start">
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1 rounded-pill bg-white border shadow-xs mb-2.5 text-emerald fw-semibold small">
                    <i class="bi bi-stars"></i> Akses Terbuka untuk Trader Mandiri
                </div>
                <h2 class="display-6 fw-bold text-slate-900 mb-2" style="font-size: 1.85rem;">
                    Siap Menemukan Setup Saham Berprobabilitas Tinggi Hari Ini?
                </h2>
                <p class="text-slate-600 mb-0" style="max-width: 650px;">
                    Tinggalkan cara lama yang menguras waktu dan emosi. Gunakan radar kuantitatif teruji untuk mengidentifikasi saham Wall Street terbaik sebelum momentum dimulai.
                </p>
            </div>
            <div class="col-lg-4 text-md-end mt-4 mt-lg-0">
                <div class="d-flex flex-column flex-sm-row justify-content-md-end gap-2">
                    <a href="<?= Url::to(['/site/register']) ?>" class="btn btn-emerald-primary fw-bold px-4 py-2.5 rounded-pill shadow-sm d-inline-flex align-items-center justify-content-center gap-2">
                        <span>Daftar Akun Gratis</span>
                        <i class="bi bi-arrow-right"></i>
                    </a>
                    <a href="<?= Url::to(['/scanner/index']) ?>" class="btn btn-outline-dark fw-semibold px-3.5 py-2.5 rounded-pill bg-white shadow-xs">
                        Buka Scanner
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- ====================================================================
     DESIGN SYSTEM & CLEAN MODERN HOMEPAGE STYLES
     ==================================================================== -->
<style>
/* Base Typography & Palette Helpers */
.text-slate-900 { color: #0f172a !important; }
.text-slate-800 { color: #1e293b !important; }
.text-slate-700 { color: #334155 !important; }
.text-slate-600 { color: #475569 !important; }
.text-slate-500 { color: #64748b !important; }
.text-slate-400 { color: #94a3b8 !important; }
.text-slate-300 { color: #cbd5e1 !important; }
.bg-slate-50 { background-color: #f8fafc !important; }

.text-emerald { color: #059669 !important; }
.text-rose { color: #e11d48 !important; }
.text-indigo { color: #4f46e5 !important; }
.text-cyan-accent { color: #38bdf8 !important; }

.bg-emerald-subtle { background-color: rgba(16, 185, 129, 0.15) !important; }
.bg-indigo-subtle { background-color: #eef2ff !important; color: #4f46e5 !important; }
.text-gradient-brand {
    background: linear-gradient(135deg, #0f172a 0%, #4f46e5 60%, #06b6d4 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.max-w-750 { max-width: 750px; }
.shadow-xs { box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); }

/* 1. Hero Section */
.hero-section {
    background: linear-gradient(135deg, #ffffff 0%, #f0f7ff 50%, #f8fafc 100%);
    border: 1px solid rgba(226, 232, 240, 0.95);
    position: relative;
}
.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #4f46e5 0%, #06b6d4 50%, #10b981 100%);
}
.hero-mesh-glow {
    position: absolute;
    top: -50px;
    right: -50px;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(79, 70, 229, 0.1) 0%, rgba(6, 182, 212, 0.05) 50%, transparent 70%);
    pointer-events: none;
    z-index: 1;
}
.hero-headline {
    letter-spacing: -0.025em;
    line-height: 1.22;
}

/* Live Pulse Animation */
.live-pulse-dot {
    width: 8px;
    height: 8px;
    background-color: #10b981;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: pulseGlow 2s infinite;
}
@keyframes pulseGlow {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Terminal Card Right */
.hero-terminal-card {
    background: linear-gradient(155deg, #090e1a 0%, #0f172a 45%, #1e1b4b 100%);
    border: 1px solid rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

/* Soft Badges */
.badge-soft-primary {
    background-color: #eef2ff !important;
    color: #4f46e5 !important;
    border: 1px solid #e0e7ff;
}
.badge-soft-emerald {
    background-color: #ecfdf5 !important;
    color: #059669 !important;
    border: 1px solid #a7f3d0;
}

/* Avatar Icons */
.avatar-icon-box {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

/* Feature Benefits Box */
.feature-benefit-box {
    transition: transform 0.22s ease, box-shadow 0.22s ease;
}
.feature-benefit-box:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.08) !important;
}
.feature-benefit-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
}

/* Core Feature Card */
.feature-card {
    border: 1px solid rgba(226, 232, 240, 0.85) !important;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease;
}
.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 32px -6px rgba(15, 23, 42, 0.09) !important;
    border-color: #cbd5e1 !important;
}

/* Step Badge Number */
.step-badge-number {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #4f46e5;
    color: #ffffff;
    font-weight: 800;
    font-size: 1.2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);
}

/* Final CTA Banner */
.final-cta-banner {
    background: linear-gradient(135deg, #ecfdf5 0%, #eff6ff 100%);
    border: 1px solid rgba(167, 243, 208, 0.8);
}
.btn-emerald-primary {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%) !important;
    color: #ffffff !important;
    border: none !important;
    transition: all 0.2s ease;
}
.btn-emerald-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3) !important;
}

/* Accordion Customization */
.accordion-button:not(.collapsed) {
    background-color: #f8fafc;
    color: #4f46e5;
    box-shadow: none;
}
.accordion-button:focus {
    box-shadow: none;
    border-color: rgba(79, 70, 229, 0.2);
}
</style>
