<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var array $settings */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Pengaturan Webmaster, SEO, Analytics & AI | Admin';
$this->params['breadcrumbs'][] = ['label' => 'Blog Info Saham', 'url' => ['/blog/index']];
$this->params['breadcrumbs'][] = ['label' => 'Manajemen Admin', 'url' => ['/blog/admin']];
$this->params['breadcrumbs'][] = 'Pengaturan SEO & Tracking';
?>

<div class="blog-settings-container pb-5" style="max-width: 980px; margin: 0 auto;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-gear-wide-connected text-primary me-2"></i> Pengaturan SEO, Webmaster, &amp; AI
            </h1>
            <p class="text-muted small mb-0">
                Konfigurasi verifikasi mesin pencari (Google &amp; Bing), Google Analytics, GTM, dan OpenAI ChatGPT tanpa sentuh kode (Zero-Code).
            </p>
        </div>
        <a href="<?= Url::to(['/blog/admin']) ?>" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Admin Blog
        </a>
    </div>

    <form method="post" action="<?= Url::to(['/blog/settings']) ?>">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>

        <!-- 1. Google & Bing Webmaster Verification -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-google text-danger me-2"></i> Verifikasi Webmaster &amp; Search Console
                </h5>
                <span class="badge bg-light text-secondary border">Google &amp; Bing</span>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Google Site Verification Token / Meta Tag</label>
                    <input type="text" 
                           name="Setting[google_site_verification]" 
                           value="<?= Html::encode($settings['google_site_verification']) ?>" 
                           class="form-control font-monospace" 
                           placeholder='cth: google-site-verification=XXXXXXXXXXXXXXXX atau <meta name="google-site-verification" content="..." />'>
                    <div class="form-text small text-muted">
                        Dapatkan kode verifikasi dari <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a>. Sistem otomatis menyematkannya ke tag <code>&lt;head&gt;</code>.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Bing Webmaster Tools Verification Token</label>
                    <input type="text" 
                           name="Setting[bing_site_verification]" 
                           value="<?= Html::encode($settings['bing_site_verification']) ?>" 
                           class="form-control font-monospace" 
                           placeholder="cth: msvalidate.01=XXXXXXXXXXXXXXXX">
                    <div class="form-text small text-muted">
                        Kode verifikasi meta dari <a href="https://www.bing.com/webmasters" target="_blank" rel="noopener">Bing Webmaster Tools</a>.
                    </div>
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold">Default Meta Keywords (SEO)</label>
                    <input type="text" 
                           name="Setting[default_meta_keywords]" 
                           value="<?= Html::encode($settings['default_meta_keywords']) ?>" 
                           class="form-control" 
                           placeholder="analisis saham, info saham, technical analysis, double bottom, volume breakout">
                </div>
            </div>
        </div>

        <!-- 2. Google Analytics 4 (GA4) & Google Tag Manager (GTM) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-graph-up-arrow text-warning me-2"></i> Tracking &amp; Analitik Pengunjung
                </h5>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Async Non-blocking</span>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info border-0 rounded-3 small py-2 px-3 mb-4 d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill fs-5 text-info"></i>
                    <span>
                        Skrip pelacak diinjeksi dengan standar <strong>Core Web Vitals</strong> dan <strong>otomatis dinonaktifkan untuk sesi Admin</strong> agar data analitik Anda tetap murni (*filter internal traffic*).
                    </span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Google Analytics 4 (GA4) Measurement ID</label>
                        <input type="text" 
                               name="Setting[ga4_measurement_id]" 
                               value="<?= Html::encode($settings['ga4_measurement_id']) ?>" 
                               class="form-control font-monospace" 
                               placeholder="G-XXXXXXXXXX">
                        <div class="form-text small text-muted">Format: <code>G-XXXXXXXXXX</code> dari Google Analytics 4 Property.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Google Tag Manager (GTM) Container ID</label>
                        <input type="text" 
                               name="Setting[gtm_container_id]" 
                               value="<?= Html::encode($settings['gtm_container_id']) ?>" 
                               class="form-control font-monospace" 
                               placeholder="GTM-XXXXXXX">
                        <div class="form-text small text-muted">Format: <code>GTM-XXXXXXX</code>. Jika GTM diisi, script head &amp; noscript body otomatis diinjeksi.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Integrasi AI & Optimasi Biaya Token ($0 Token / Zero-Cost Mode / Groq / OpenAI) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-robot text-primary me-2"></i> Konfigurasi AI Penulis &amp; Provider (Groq / OpenAI)
                    </h5>
                    <small class="text-muted">Gunakan Groq API untuk artikel 100% unik &amp; berkualitas tinggi secara GRATIS ($0), atau OpenAI / Zero-Cost.</small>
                </div>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill">
                    <i class="bi bi-shield-check me-1"></i> Multi-Provider AI Ready
                </span>
            </div>
            <div class="card-body p-4">
                <!-- Banner Penjelasan Solusi Token $0 -->
                <div class="alert alert-light border rounded-3 p-3 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-2 text-success">
                            <i class="bi bi-lightbulb-fill fs-4"></i>
                        </div>
                        <div class="small text-secondary">
                            <strong class="text-dark">Bagaimana menghasilkan artikel yang 100% unik tanpa biaya token?</strong><br>
                            Sistem ini mendukung 2 opsi terbaik untuk menghasilkan artikel blog berkualitas Google E-E-A-T:<br>
                            <strong>1. Groq Cloud API (100% GRATIS &amp; Artikel Unik):</strong> Menggunakan LLM mutakhir Llama 3.3 70B secara gratis tanpa biaya token dan artikel 100% unik, kaya narasi, dan cepat (&lt; 2 detik).<br>
                            <strong>2. Zero-Cost Local Engine:</strong> Generator kuantitatif lokal berbasis cetakan template ($0 tanpa API).
                        </div>
                    </div>
                </div>

                <!-- 1. Pilihan Mode Operasional Generator -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark mb-2">Pilih Mode Pembuatan Artikel Blog:</label>
                    <div class="row g-3">
                        <!-- Mode Cloud AI (Rekomendasi) -->
                        <div class="col-md-6">
                            <div class="card h-100 border <?= $settings['ai_generation_mode'] === \app\services\OpenAiBlogService::MODE_AI_OPTIMIZED ? 'border-primary bg-primary bg-opacity-10' : 'border-light-subtle' ?> rounded-4 p-3 shadow-none cursor-pointer" onclick="selectGenerationMode('ai_optimized')">
                                <div class="form-check d-flex align-items-start gap-2">
                                    <input class="form-check-input mt-1" type="radio" name="Setting[ai_generation_mode]" id="mode_ai_optimized" value="ai_optimized" <?= $settings['ai_generation_mode'] === \app\services\OpenAiBlogService::MODE_AI_OPTIMIZED ? 'checked' : '' ?> onchange="onModeChange()">
                                    <label class="form-check-label w-100" for="mode_ai_optimized">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-primary"><i class="bi bi-stars me-1"></i> Cloud AI (Groq Free / OpenAI / OpenRouter)</strong>
                                            <span class="badge bg-primary text-white rounded-pill">100% Artikel Unik</span>
                                        </div>
                                        <p class="text-muted small mb-0">
                                            Artikel 100% unik, mendalam, dan kaya konteks pasar. Menggunakan model AI canggih seperti Groq Llama 3.3 70B (Gratis) atau OpenAI ChatGPT.
                                        </p>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Mode Zero Cost (Template Lokal) -->
                        <div class="col-md-6">
                            <div class="card h-100 border <?= $settings['ai_generation_mode'] === \app\services\OpenAiBlogService::MODE_ZERO_COST ? 'border-success bg-success bg-opacity-10' : 'border-light-subtle' ?> rounded-4 p-3 shadow-none cursor-pointer" onclick="selectGenerationMode('zero_cost')">
                                <div class="form-check d-flex align-items-start gap-2">
                                    <input class="form-check-input mt-1" type="radio" name="Setting[ai_generation_mode]" id="mode_zero_cost" value="zero_cost" <?= $settings['ai_generation_mode'] === \app\services\OpenAiBlogService::MODE_ZERO_COST ? 'checked' : '' ?> onchange="onModeChange()">
                                    <label class="form-check-label w-100" for="mode_zero_cost">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-success"><i class="bi bi-lightning-charge-fill me-1"></i> Zero-Cost Local Engine (Template)</strong>
                                            <span class="badge bg-success text-white rounded-pill">$0 Tanpa API</span>
                                        </div>
                                        <p class="text-muted small mb-0">
                                            Generator kuantitatif lokal berbasis cetakan template terstruktur. Tidak memerlukan API key, namun artikel menggunakan pola kalimat serupa.
                                        </p>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian Detail Cloud AI Config -->
                <div id="cloud_ai_settings_section" class="border rounded-4 p-3 bg-light bg-opacity-50">
                    <!-- Header Provider -->
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <label class="form-label fw-bold text-dark mb-0 fs-6">
                            <i class="bi bi-cpu text-primary me-2"></i> Pilih Provider Layanan AI:
                        </label>
                        <div class="d-flex align-items-center gap-2">
                            <a href="<?= Url::to(['/blog/clear-ai-cache']) ?>" class="btn btn-outline-danger btn-sm rounded-pill" onclick="return confirm('Bersihkan cache respon AI yang tersimpan?')">
                                <i class="bi bi-trash3 me-1"></i> Bersihkan Cache AI
                            </a>
                        </div>
                    </div>

                    <!-- Pilihan Kartu Provider AI -->
                    <div class="row g-2 mb-4">
                        <!-- 1. GROQ (100% GRATIS) -->
                        <div class="col-md-4">
                            <div class="card h-100 border <?= $settings['ai_provider'] === 'groq' ? 'border-success bg-white shadow-sm ring-success' : 'border-light-subtle bg-white' ?> rounded-3 p-3 cursor-pointer provider-card" id="provider_card_groq" onclick="selectAiProvider('groq')">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="Setting[ai_provider]" id="provider_groq" value="groq" <?= $settings['ai_provider'] === 'groq' ? 'checked' : '' ?> onchange="onProviderChange()">
                                    <label class="form-check-label w-100" for="provider_groq">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-success"><i class="bi bi-lightning-charge-fill me-1"></i> Groq Cloud</strong>
                                            <span class="badge bg-success text-white" style="font-size: 0.68rem;">100% GRATIS</span>
                                        </div>
                                        <div class="small text-muted" style="font-size: 0.78rem;">
                                            Llama 3.3 70B • Sangat Cepat • Kualitas GPT-4 • Tanpa Kartu Kredit
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 2. OPENAI (CHATGPT) -->
                        <div class="col-md-4">
                            <div class="card h-100 border <?= $settings['ai_provider'] === 'openai' ? 'border-primary bg-white shadow-sm' : 'border-light-subtle bg-white' ?> rounded-3 p-3 cursor-pointer provider-card" id="provider_card_openai" onclick="selectAiProvider('openai')">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="Setting[ai_provider]" id="provider_openai" value="openai" <?= $settings['ai_provider'] === 'openai' ? 'checked' : '' ?> onchange="onProviderChange()">
                                    <label class="form-check-label w-100" for="provider_openai">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-primary"><i class="bi bi-chat-quote-fill me-1"></i> OpenAI (ChatGPT)</strong>
                                            <span class="badge bg-secondary text-white" style="font-size: 0.68rem;">Token Berbayar</span>
                                        </div>
                                        <div class="small text-muted" style="font-size: 0.78rem;">
                                            GPT-6 Luna, GPT-5.6, GPT-4o, GPT-4o-mini
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 3. CUSTOM / OPENROUTER / OLLAMA -->
                        <div class="col-md-4">
                            <div class="card h-100 border <?= in_array($settings['ai_provider'], ['openrouter', 'ollama', 'custom']) ? 'border-dark bg-white shadow-sm' : 'border-light-subtle bg-white' ?> rounded-3 p-3 cursor-pointer provider-card" id="provider_card_custom" onclick="selectAiProvider('custom')">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="Setting[ai_provider]" id="provider_custom" value="custom" <?= in_array($settings['ai_provider'], ['openrouter', 'ollama', 'custom']) ? 'checked' : '' ?> onchange="onProviderChange()">
                                    <label class="form-check-label w-100" for="provider_custom">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-dark"><i class="bi bi-hdd-network-fill me-1"></i> Custom / OpenRouter</strong>
                                            <span class="badge bg-dark text-white" style="font-size: 0.68rem;">Fleksibel</span>
                                        </div>
                                        <div class="small text-muted" style="font-size: 0.78rem;">
                                            OpenRouter, Ollama Lokal, atau API Kompatibel Lainnya
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION FORM KHUSUS GROQ CLOUD -->
                    <div id="section_provider_groq" class="provider-detail-section bg-white p-3 rounded-3 border mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold text-success mb-0">
                                <i class="bi bi-lightning-charge-fill me-1"></i> Konfigurasi Groq Cloud API (100% Gratis)
                            </h6>
                            <a href="https://console.groq.com/keys" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success rounded-pill py-0 px-2 fw-semibold" style="font-size: 0.76rem;">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Dapatkan API Key Gratis di console.groq.com
                            </a>
                        </div>
                        <p class="small text-muted mb-3" style="font-size: 0.82rem;">
                            Groq Cloud menyediakan infrastruktur LPU™ generasi terbaru dengan kecepatan hingga ~500–1000 kata/detik dan kuota gratis harian yang besar. Model unggulan resmi: <strong>OpenAI GPT-OSS 120B / 20B</strong>, <strong>DeepSeek R1 Distill</strong>, atau <strong>Meta Llama 3.3 70B</strong>.
                        </p>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label fw-semibold text-dark small">Groq API Key (Secret Key)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-success"><i class="bi bi-key-fill"></i></span>
                                    <input type="password" 
                                           name="Setting[groq_api_key]" 
                                           value="<?= Html::encode(!empty($settings['groq_api_key']) ? $settings['groq_api_key'] : (str_starts_with($settings['openai_api_key'], 'gsk_') ? $settings['openai_api_key'] : '')) ?>" 
                                           class="form-control font-monospace bg-white border-start-0" 
                                           placeholder="gsk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                           id="groq_api_key_field">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('groq_api_key_field')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text small text-muted">Awalan kunci Groq adalah <code>gsk_...</code>. Dibuat gratis di <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com/keys</a>.</div>
                            </div>

                            <div class="col-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold text-dark small mb-0">Model Groq</label>
                                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2 rounded-pill fw-semibold" id="btnSyncGroqModels" onclick="fetchGroqModels()" style="font-size: 0.72rem;">
                                        <i class="bi bi-arrow-repeat me-1"></i> Muat Model dari Akun Saya
                                    </button>
                                </div>
                                <?php 
                                    $currentGroqModel = $settings['groq_model'] ?: \app\services\OpenAiBlogService::DEFAULT_GROQ_MODEL;
                                ?>
                                <select name="Setting[groq_model]" id="groq_model_field" class="form-select bg-white">
                                    <?php if (!array_key_exists($currentGroqModel, \app\services\OpenAiBlogService::GROQ_MODELS)): ?>
                                        <option value="<?= Html::encode($currentGroqModel) ?>" selected>
                                            <?= Html::encode($currentGroqModel) ?> (Tersimpan di Sistem)
                                        </option>
                                    <?php endif; ?>
                                    <?php foreach (\app\services\OpenAiBlogService::GROQ_MODELS as $gmKey => $gmLabel): ?>
                                        <option value="<?= Html::encode($gmKey) ?>" <?= $currentGroqModel === $gmKey ? 'selected' : '' ?>>
                                            <?= Html::encode($gmLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="groq_model_sync_feedback" class="small mt-1 text-muted" style="font-size: 0.76rem;">
                                    Klik <em>"Muat Model dari Akun Saya"</em> untuk sinkronisasi otomatis model yang diizinkan untuk API Key Anda.
                                </div>
                            </div>
                        </div>

                        <!-- Info Update Model Resmi Groq -->
                        <div class="alert alert-light border rounded-3 p-2 mt-3 mb-0 small text-secondary" style="font-size: 0.78rem;">
                            <div class="d-flex gap-2">
                                <i class="bi bi-info-circle-fill text-success flex-shrink-0 mt-1"></i>
                                <div>
                                    <strong>Status Model Groq Resmi:</strong> Model <code>mixtral-8x7b-32768</code> dan <code>gemma2-9b-it</code> telah resmi dipensiunkan (decommissioned) oleh Groq. Rekomendasi utama: <strong>openai/gpt-oss-120b</strong> (Flagship MoE Reasoning) dan <strong>openai/gpt-oss-20b</strong> (Super Cepat) yang aktif 100% dan bebas pembatasan.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION FORM KHUSUS OPENAI -->
                    <div id="section_provider_openai" class="provider-detail-section bg-white p-3 rounded-3 border mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold text-primary mb-0">
                                <i class="bi bi-chat-quote-fill me-1"></i> Konfigurasi OpenAI Official (ChatGPT)
                            </h6>
                            <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2 fw-semibold" style="font-size: 0.76rem;">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Buka platform.openai.com
                            </a>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label fw-semibold text-dark small">OpenAI API Key / Secret Token</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-primary"><i class="bi bi-key-fill"></i></span>
                                    <input type="password" 
                                           name="Setting[openai_api_key]" 
                                           value="<?= Html::encode($settings['openai_api_key']) ?>" 
                                           class="form-control font-monospace bg-white border-start-0" 
                                           placeholder="sk-proj-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                           id="openai_api_key_field">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('openai_api_key_field')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text small text-muted">Format: <code>sk-proj-...</code> atau <code>sk-...</code> dari platform OpenAI.</div>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label fw-semibold text-dark small">Model OpenAI (Default: gpt-6-luna)</label>
                                <select name="Setting[openai_model]" id="openai_model_field" class="form-select bg-white">
                                    <?php foreach (\app\services\OpenAiBlogService::SUPPORTED_MODELS as $mKey => $mLabel): ?>
                                        <option value="<?= Html::encode($mKey) ?>" <?= ($settings['openai_model'] ?: \app\services\OpenAiBlogService::DEFAULT_MODEL) === $mKey ? 'selected' : '' ?>>
                                            <?= Html::encode($mLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small text-muted">Otomatis fallback berjenjang jika tier akun belum mengaktifkan model terbaru.</div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION CUSTOM ENDPOINT / BASE URL (Shared / Advanced) -->
                    <div id="section_custom_endpoint" class="bg-white p-3 rounded-3 border mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-semibold text-dark mb-0 small">
                                <i class="bi bi-link-45deg me-1"></i> API Base URL Endpoint
                            </label>
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <span class="small text-muted me-1" style="font-size:0.75rem;">Preset Cepat:</span>
                                <button type="button" class="btn btn-outline-success btn-sm rounded-pill py-0 px-2" style="font-size:0.75rem;" onclick="setBaseUrlPreset('https://api.groq.com/openai/v1', 'groq')">
                                    Groq Cloud
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0 px-2" style="font-size:0.75rem;" onclick="setBaseUrlPreset('https://api.openai.com/v1', 'openai')">
                                    OpenAI Resmi
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm rounded-pill py-0 px-2" style="font-size:0.75rem;" onclick="setBaseUrlPreset('https://openrouter.ai/api/v1', 'custom')">
                                    OpenRouter
                                </button>
                                <button type="button" class="btn btn-outline-dark btn-sm rounded-pill py-0 px-2" style="font-size:0.75rem;" onclick="setBaseUrlPreset('http://localhost:11434/v1', 'custom')">
                                    Local Ollama
                                </button>
                            </div>
                        </div>

                        <input type="text" 
                               name="Setting[openai_base_url]" 
                               id="openai_base_url_field"
                               value="<?= Html::encode($settings['openai_base_url']) ?>" 
                               class="form-control font-monospace bg-white" 
                               placeholder="https://api.groq.com/openai/v1">
                        <div class="form-text small text-muted mt-1">
                            Endpoint standar: Groq (<code>https://api.groq.com/openai/v1</code>), OpenAI (<code>https://api.openai.com/v1</code>).
                        </div>
                    </div>

                    <!-- TOMBOL LIVE TEST KONEKSI API DENGAN INDIKATOR STATUS -->
                    <div class="p-3 rounded-3 border bg-white mb-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <strong class="d-block text-dark small"><i class="bi bi-activity text-primary me-1"></i> Uji Koneksi API AI</strong>
                                <span class="text-muted small" style="font-size:0.78rem;">Kirim sinyal tes instan untuk memastikan API Key dan Model Anda terhubung sempurna.</span>
                            </div>
                            <button type="button" id="btnTestAiConnection" class="btn btn-sm btn-outline-primary px-3 rounded-pill fw-bold" onclick="testAiConnection()">
                                <i class="bi bi-play-circle me-1"></i> Uji Koneksi Sekarang
                            </button>
                        </div>
                        <div id="aiTestResultBox" class="mt-2 d-none"></div>
                    </div>

                    <!-- Max Tokens & Response Caching -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark small">Batas Output Max Completion Tokens</label>
                            <div class="input-group">
                                <input type="number" 
                                       name="Setting[openai_max_tokens]" 
                                       value="<?= Html::encode($settings['openai_max_tokens']) ?>" 
                                       class="form-control bg-white font-monospace" 
                                       min="500" 
                                       max="4000" 
                                       step="100">
                                <span class="input-group-text bg-white text-muted small">tokens</span>
                            </div>
                            <div class="form-text small text-muted">Default: 1600 tokens. Membatasi panjang artikel agar tidak menguras kuota berlebihan.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark small">Caching Respon AI Cerdas</label>
                            <div class="form-check form-switch mt-2">
                                <input type="hidden" name="Setting[openai_cache_enabled]" value="0">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       role="switch" 
                                       id="openai_cache_enabled" 
                                       name="Setting[openai_cache_enabled]" 
                                       value="1" 
                                       <?= $settings['openai_cache_enabled'] !== '0' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold text-dark small" for="openai_cache_enabled">
                                    Simpan respon AI di cache lokal (24 jam)
                                </label>
                            </div>
                            <div class="form-text small text-muted">
                                Mencegah kuota terpotong dua kali untuk saham dan tanggal analisis yang sama.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Indikator 4 Perlindungan Hemat Token -->
                <div class="row g-2 mt-3 pt-2 border-top">
                    <div class="col-sm-6 col-md-3">
                        <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-light small">
                            <i class="bi bi-file-earmark-zip-fill text-primary fs-5"></i>
                            <div>
                                <span class="fw-bold d-block text-dark">Prompt Minified</span>
                                <span class="text-muted" style="font-size: 0.75rem;">Metrik dipangkas -80%</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-light small">
                            <i class="bi bi-clock-history text-success fs-5"></i>
                            <div>
                                <span class="fw-bold d-block text-dark">Smart Caching</span>
                                <span class="text-muted" style="font-size: 0.75rem;">0 token re-request</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-light small">
                            <i class="bi bi-shield-lock-fill text-warning fs-5"></i>
                            <div>
                                <span class="fw-bold d-block text-dark">Budget Guard</span>
                                <span class="text-muted" style="font-size: 0.75rem;">Max tokens dibatasi</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-light small">
                            <i class="bi bi-infinity text-info fs-5"></i>
                            <div>
                                <span class="fw-bold d-block text-dark">Zero-Cost Fallback</span>
                                <span class="text-muted" style="font-size: 0.75rem;">100% selalu berhasil</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Custom Scripts (Head & Body) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
            <div class="card-header bg-white border-bottom p-3">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-code-slash text-secondary me-2"></i> Skrip Pelacak Kustom Tambahan (Fleksibel)
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Custom Scripts di dalam tag &lt;head&gt;</label>
                    <textarea name="Setting[custom_head_scripts]" 
                              rows="4" 
                              class="form-control font-monospace small" 
                              placeholder="<!-- Cth: Meta Pixel / Facebook Pixel, Microsoft Clarity, dll -->"><?= Html::encode($settings['custom_head_scripts']) ?></textarea>
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold">Custom Scripts sebelum penutup &lt;/body&gt;</label>
                    <textarea name="Setting[custom_body_scripts]" 
                              rows="4" 
                              class="form-control font-monospace small" 
                              placeholder="<!-- Cth: Widget Live Chat, Script pelacak body lainnya -->"><?= Html::encode($settings['custom_body_scripts']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="text-end">
            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm">
                <i class="bi bi-check-lg me-1"></i> Simpan Semua Pengaturan
            </button>
        </div>
    </form>
</div>

<script>
function togglePasswordVisibility(fieldId) {
    var field = document.getElementById(fieldId);
    if (field) {
        field.type = field.type === 'password' ? 'text' : 'password';
    }
}

function selectGenerationMode(mode) {
    var radio = document.getElementById('mode_' + mode);
    if (radio) {
        radio.checked = true;
        onModeChange();
    }
}

function onModeChange() {
    var isZeroCost = document.getElementById('mode_zero_cost').checked;
    var zeroCard = document.querySelector('[onclick="selectGenerationMode(\'zero_cost\')"]');
    var aiCard = document.querySelector('[onclick="selectGenerationMode(\'ai_optimized\')"]');
    var cloudSection = document.getElementById('cloud_ai_settings_section');

    if (isZeroCost) {
        if (zeroCard) {
            zeroCard.classList.add('border-success', 'bg-success', 'bg-opacity-10');
            zeroCard.classList.remove('border-light-subtle');
        }
        if (aiCard) {
            aiCard.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
            aiCard.classList.add('border-light-subtle');
        }
        if (cloudSection) {
            cloudSection.style.opacity = '0.6';
        }
    } else {
        if (zeroCard) {
            zeroCard.classList.remove('border-success', 'bg-success', 'bg-opacity-10');
            zeroCard.classList.add('border-light-subtle');
        }
        if (aiCard) {
            aiCard.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
            aiCard.classList.remove('border-light-subtle');
        }
        if (cloudSection) {
            cloudSection.style.opacity = '1';
        }
    }
}

function selectAiProvider(provider) {
    var radio = document.getElementById('provider_' + provider);
    if (radio) {
        radio.checked = true;
        onProviderChange();
    }
}

function onProviderChange() {
    var groqRadio = document.getElementById('provider_groq');
    var openaiRadio = document.getElementById('provider_openai');
    var customRadio = document.getElementById('provider_custom');

    var cardGroq = document.getElementById('provider_card_groq');
    var cardOpenai = document.getElementById('provider_card_openai');
    var cardCustom = document.getElementById('provider_card_custom');

    var secGroq = document.getElementById('section_provider_groq');
    var secOpenai = document.getElementById('section_provider_openai');
    var baseUrlField = document.getElementById('openai_base_url_field');

    // Reset card classes
    [cardGroq, cardOpenai, cardCustom].forEach(function(card) {
        if (card) {
            card.classList.remove('border-success', 'border-primary', 'border-dark', 'shadow-sm');
            card.classList.add('border-light-subtle');
        }
    });

    if (groqRadio && groqRadio.checked) {
        if (cardGroq) {
            cardGroq.classList.add('border-success', 'shadow-sm');
            cardGroq.classList.remove('border-light-subtle');
        }
        if (secGroq) secGroq.style.display = 'block';
        if (secOpenai) secOpenai.style.display = 'none';
        if (baseUrlField && (!baseUrlField.value || baseUrlField.value.includes('api.openai.com') || baseUrlField.value.includes('localhost'))) {
            baseUrlField.value = 'https://api.groq.com/openai/v1';
        }
    } else if (openaiRadio && openaiRadio.checked) {
        if (cardOpenai) {
            cardOpenai.classList.add('border-primary', 'shadow-sm');
            cardOpenai.classList.remove('border-light-subtle');
        }
        if (secGroq) secGroq.style.display = 'none';
        if (secOpenai) secOpenai.style.display = 'block';
        if (baseUrlField && (!baseUrlField.value || baseUrlField.value.includes('groq.com') || baseUrlField.value.includes('localhost'))) {
            baseUrlField.value = 'https://api.openai.com/v1';
        }
    } else {
        if (cardCustom) {
            cardCustom.classList.add('border-dark', 'shadow-sm');
            cardCustom.classList.remove('border-light-subtle');
        }
        if (secGroq) secGroq.style.display = 'block';
        if (secOpenai) secOpenai.style.display = 'block';
    }
}

function setBaseUrlPreset(url, provider) {
    var field = document.getElementById('openai_base_url_field');
    if (field) {
        field.value = url;
    }
    if (provider) {
        selectAiProvider(provider);
    }
}

function testAiConnection() {
    var btn = document.getElementById('btnTestAiConnection');
    var resultBox = document.getElementById('aiTestResultBox');
    
    var provider = 'groq';
    var groqRadio = document.getElementById('provider_groq');
    var openaiRadio = document.getElementById('provider_openai');
    if (openaiRadio && openaiRadio.checked) {
        provider = 'openai';
    } else if (groqRadio && groqRadio.checked) {
        provider = 'groq';
    } else {
        provider = 'custom';
    }

    var apiKey = '';
    var model = '';
    if (provider === 'groq') {
        apiKey = document.getElementById('groq_api_key_field') ? document.getElementById('groq_api_key_field').value : '';
        model = document.getElementById('groq_model_field') ? document.getElementById('groq_model_field').value : '';
    } else {
        apiKey = document.getElementById('openai_api_key_field') ? document.getElementById('openai_api_key_field').value : '';
        model = document.getElementById('openai_model_field') ? document.getElementById('openai_model_field').value : '';
    }

    var baseUrl = document.getElementById('openai_base_url_field') ? document.getElementById('openai_base_url_field').value : '';

    if (!apiKey && !baseUrl.includes('localhost') && !baseUrl.includes('127.0.0.1')) {
        resultBox.className = 'alert alert-warning py-2 px-3 small mt-2 mb-0';
        resultBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Harap masukkan API Key terlebih dahulu sebelum menguji.';
        resultBox.classList.remove('d-none');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Menguji Koneksi...';
    resultBox.className = 'alert alert-info py-2 px-3 small mt-2 mb-0';
    resultBox.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i> Menghubungi ' + provider.toUpperCase() + ' (' + model + ')...';
    resultBox.classList.remove('d-none');

    var csrfParam = '<?= Yii::$app->request->csrfParam ?>';
    var csrfToken = '<?= Yii::$app->request->csrfToken ?>';

    var formData = new FormData();
    formData.append(csrfParam, csrfToken);
    formData.append('provider', provider);
    formData.append('api_key', apiKey);
    formData.append('base_url', baseUrl);
    formData.append('model', model);

    fetch('<?= Url::to(['/blog/test-ai-connection']) ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(res) {
        return res.text().then(function(text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Respon server (' + res.status + ' ' + res.statusText + '): ' + text.substring(0, 150));
            }
        });
    })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-circle me-1"></i> Uji Koneksi Sekarang';

        if (data.success) {
            resultBox.className = 'alert alert-success py-2 px-3 small mt-2 mb-0';
            resultBox.innerHTML = '<i class="bi bi-check-circle-fill me-1 text-success"></i> <strong>' + (data.message || 'Koneksi Berhasil!') + '</strong>';
        } else {
            resultBox.className = 'alert alert-danger py-2 px-3 small mt-2 mb-0';
            var errorHtml = '<i class="bi bi-x-circle-fill me-1 text-danger"></i> <strong>Gagal Terhubung:</strong> ' + (data.message || 'Terjadi kesalahan');
            if (provider === 'groq' && (data.message.includes('404') || data.message.includes('400') || data.message.includes('does not exist'))) {
                errorHtml += '<div class="mt-2 pt-2 border-top border-danger border-opacity-25 small text-dark"><i class="bi bi-lightbulb-fill text-warning me-1"></i> <strong>Solusi:</strong> Model mungkin belum diizinkan untuk API Key Anda atau sudah dipensiunkan Groq. Silakan klik tombol <strong>"Muat Model dari Akun Saya"</strong> di atas atau pilih model <code>openai/gpt-oss-120b</code> / <code>openai/gpt-oss-20b</code> lalu coba lagi.</div>';
            }
            resultBox.innerHTML = errorHtml;
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-circle me-1"></i> Uji Koneksi Sekarang';
        resultBox.className = 'alert alert-danger py-2 px-3 small mt-2 mb-0';
        resultBox.innerHTML = '<i class="bi bi-x-circle-fill me-1 text-danger"></i> <strong>Gagal Terhubung:</strong> ' + err.message;
    });
}

function fetchGroqModels() {
    var apiKeyField = document.getElementById('groq_api_key_field');
    var apiKey = apiKeyField ? apiKeyField.value.trim() : '';
    var btn = document.getElementById('btnSyncGroqModels');
    var feedback = document.getElementById('groq_model_sync_feedback');
    var select = document.getElementById('groq_model_field');

    if (!apiKey) {
        feedback.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-exclamation-circle me-1"></i> Masukkan Groq API Key terlebih dahulu.</span>';
        apiKeyField.focus();
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Memuat...';
    feedback.innerHTML = '<span class="text-info"><i class="bi bi-arrow-repeat spin me-1"></i> Mengambil daftar model aktif dari api.groq.com...</span>';

    var csrfParam = '<?= Yii::$app->request->csrfParam ?>';
    var csrfToken = '<?= Yii::$app->request->csrfToken ?>';

    var formData = new FormData();
    formData.append(csrfParam, csrfToken);
    formData.append('provider', 'groq');
    formData.append('api_key', apiKey);
    formData.append('base_url', 'https://api.groq.com/openai/v1');

    fetch('<?= Url::to(['/blog/fetch-ai-models']) ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(res) {
        return res.json();
    })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Muat Model dari Akun Saya';

        if (data.success && data.models && data.models.length > 0) {
            var currentVal = select.value;
            select.innerHTML = '';
            var foundCurrent = false;

            data.models.forEach(function(m) {
                var opt = document.createElement('option');
                opt.value = m.id;
                var label = m.id;
                if (m.id === 'openai/gpt-oss-120b') {
                    label = m.id + ' (Flagship MoE 120B - Ultra Cepat ~500 tok/s)';
                } else if (m.id === 'openai/gpt-oss-20b') {
                    label = m.id + ' (Super Fast Reasoning ~1000 tok/s)';
                } else if (m.id === 'deepseek-r1-distill-llama-70b') {
                    label = m.id + ' (DeepSeek R1 Reasoning Llama 70B)';
                } else if (m.id === 'deepseek-r1-distill-qwen-32b') {
                    label = m.id + ' (DeepSeek R1 Reasoning Qwen 32B)';
                } else if (m.id === 'qwen/qwen3.8-27b') {
                    label = m.id + ' (Qwen 3.8 27B Reasoning)';
                } else if (m.id === 'llama-3.3-70b-versatile') {
                    label = m.id + ' (Meta Llama 3.3 70B)';
                } else if (m.id === 'llama-3.1-8b-instant') {
                    label = m.id + ' (Meta Llama 3.1 8B)';
                }
                opt.textContent = label;
                if (m.id === currentVal) {
                    opt.selected = true;
                    foundCurrent = true;
                }
                select.appendChild(opt);
            });

            if (!foundCurrent) {
                var preferred = ['openai/gpt-oss-120b', 'openai/gpt-oss-20b', 'deepseek-r1-distill-llama-70b', 'qwen/qwen3.8-27b', 'llama-3.3-70b-versatile'];
                var setPreferred = false;
                for (var i = 0; i < preferred.length; i++) {
                    for (var j = 0; j < select.options.length; j++) {
                        if (select.options[j].value === preferred[i]) {
                            select.options[j].selected = true;
                            setPreferred = true;
                            break;
                        }
                    }
                    if (setPreferred) break;
                }
            }

            feedback.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Berhasil! ' + data.models.length + ' model aktif disinkronkan. Terpilih: <code>' + select.value + '</code></span>';
        } else {
            feedback.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> ' + (data.message || 'Gagal memuat model dari Groq.') + '</span>';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Muat Model dari Akun Saya';
        feedback.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> Error: ' + err.message + '</span>';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    onModeChange();
    onProviderChange();
});
</script>
