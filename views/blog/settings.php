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

        <!-- 3. Integrasi AI & Optimasi Biaya Token ($0 Token / Zero-Cost Mode) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-robot text-primary me-2"></i> Konfigurasi AI Penulis &amp; Optimasi Token ($0 Biaya API)
                    </h5>
                    <small class="text-muted">Teknik optimasi hemat token &amp; mode gratis seperti pada plugin WordPress (AI Engine / GPT AI Power)</small>
                </div>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill">
                    <i class="bi bi-shield-check me-1"></i> Token-Optimized Engine
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
                            <strong class="text-dark">Bagaimana plugin WordPress bisa gratis tanpa terkena biaya OpenAI?</strong><br>
                            Plugin WordPress mencapai biaya $0 melalui 2 cara utama:
                            <strong>1. Zero-Cost Local Engine:</strong> Mengonversi data indikator pasar langsung menjadi artikel riset berstandar Google E-E-A-T tanpa memanggil API eksternal (100% Bebas Biaya &amp; $0 Token).
                            <strong>2. Endpoint Kompatibel Gratis (Groq / OpenRouter / Local):</strong> Mengizinkan penggantian Base URL ke penyedia gratis seperti Groq API (Llama 3.3 70B gratis &amp; super cepat).
                            Sistem ini sudah kami optimasi dengan <strong>kompresi metrik (-80% token)</strong> dan <strong>response caching 24 jam</strong>.
                        </div>
                    </div>
                </div>

                <!-- 1. Pilihan Mode Operasional Generator -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark mb-2">Pilih Mode Pembuatan Artikel Blog:</label>
                    <div class="row g-3">
                        <!-- Mode Zero Cost -->
                        <div class="col-md-6">
                            <div class="card h-100 border <?= $settings['ai_generation_mode'] === \app\services\OpenAiBlogService::MODE_ZERO_COST ? 'border-success bg-success bg-opacity-10' : 'border-light-subtle' ?> rounded-4 p-3 shadow-none cursor-pointer" onclick="selectGenerationMode('zero_cost')">
                                <div class="form-check d-flex align-items-start gap-2">
                                    <input class="form-check-input mt-1" type="radio" name="Setting[ai_generation_mode]" id="mode_zero_cost" value="zero_cost" <?= $settings['ai_generation_mode'] === \app\services\OpenAiBlogService::MODE_ZERO_COST ? 'checked' : '' ?> onchange="onModeChange()">
                                    <label class="form-check-label w-100" for="mode_zero_cost">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-success"><i class="bi bi-lightning-charge-fill me-1"></i> Zero-Cost Engine (100% Gratis)</strong>
                                            <span class="badge bg-success text-white rounded-pill">0 Token ($0.00)</span>
                                        </div>
                                        <p class="text-muted small mb-0">
                                            Menggunakan generator kuantitatif E-E-A-T lokal cerdas. Tidak memerlukan saldo/kuota API OpenAI sepeserpun, eksekusi super kilat (&lt; 50ms), dan artikel dijamin lolos pedoman kualitas Google.
                                        </p>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Mode Cloud AI -->
                        <div class="col-md-6">
                            <div class="card h-100 border <?= $settings['ai_generation_mode'] === \app\services\OpenAiBlogService::MODE_AI_OPTIMIZED ? 'border-primary bg-primary bg-opacity-10' : 'border-light-subtle' ?> rounded-4 p-3 shadow-none cursor-pointer" onclick="selectGenerationMode('ai_optimized')">
                                <div class="form-check d-flex align-items-start gap-2">
                                    <input class="form-check-input mt-1" type="radio" name="Setting[ai_generation_mode]" id="mode_ai_optimized" value="ai_optimized" <?= $settings['ai_generation_mode'] === \app\services\OpenAiBlogService::MODE_AI_OPTIMIZED ? 'checked' : '' ?> onchange="onModeChange()">
                                    <label class="form-check-label w-100" for="mode_ai_optimized">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-primary"><i class="bi bi-cloud-check-fill me-1"></i> Cloud AI (OpenAI / Groq / OpenRouter)</strong>
                                            <span class="badge bg-primary text-white rounded-pill">Token-Optimized</span>
                                        </div>
                                        <p class="text-muted small mb-0">
                                            Menghubungkan ke ChatGPT (gpt-6-luna, gpt-5.6-luna, gpt-5.4-nano) atau provider gratis kompatibel OpenAI. Dilengkapi kompresi token (-80%) &amp; caching cerdas.
                                        </p>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian Detail Cloud AI Config -->
                <div id="cloud_ai_settings_section" class="border rounded-4 p-3 bg-light bg-opacity-50">
                    <h6 class="fw-bold text-dark mb-3 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-sliders2 text-primary me-2"></i> Konfigurasi API Endpoint &amp; Model</span>
                        <a href="<?= Url::to(['/blog/clear-ai-cache']) ?>" class="btn btn-outline-danger btn-sm rounded-pill" onclick="return confirm('Bersihkan cache respon AI yang tersimpan?')">
                            <i class="bi bi-trash3 me-1"></i> Bersihkan Cache AI
                        </a>
                    </h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">OpenAI API Key / Secret Token</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-key-fill text-muted"></i></span>
                                <input type="password" 
                                       name="Setting[openai_api_key]" 
                                       value="<?= Html::encode($settings['openai_api_key']) ?>" 
                                       class="form-control font-monospace bg-white border-start-0" 
                                       placeholder="sk-proj-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx (atau gsk_... jika pakai Groq)"
                                       id="openai_api_key_field">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('openai_api_key_field')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text small text-muted">
                                Kunci API dari OpenAI (atau dari <a href="https://console.groq.com" target="_blank" rel="noopener">Groq Console</a> untuk kuota 100% gratis). Kosongkan jika pakai Ollama lokal.
                            </div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Model AI (Default: gpt-6-luna)</label>
                            <select name="Setting[openai_model]" class="form-select bg-white">
                                <?php foreach (\app\services\OpenAiBlogService::SUPPORTED_MODELS as $mKey => $mLabel): ?>
                                    <option value="<?= Html::encode($mKey) ?>" <?= ($settings['openai_model'] ?: \app\services\OpenAiBlogService::DEFAULT_MODEL) === $mKey ? 'selected' : '' ?>>
                                        <?= Html::encode($mLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small text-muted">Model otomatis fallback berjenjang jika tier akun belum mengaktifkan model terbaru.</div>
                        </div>
                    </div>

                    <!-- Custom API Base URL dengan Tombol Preset 1-Klik -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Custom API Base URL (OpenAI-Compatible Endpoint)</label>
                        <input type="text" 
                               name="Setting[openai_base_url]" 
                               id="openai_base_url_field"
                               value="<?= Html::encode($settings['openai_base_url']) ?>" 
                               class="form-control font-monospace bg-white" 
                               placeholder="https://api.openai.com/v1">
                        <div class="d-flex align-items-center gap-1 flex-wrap mt-2">
                            <span class="small text-muted me-1">Preset Cepat:</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0 px-2 small" onclick="setBaseUrlPreset('https://api.openai.com/v1')">
                                OpenAI Resmi
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm rounded-pill py-0 px-2 small" onclick="setBaseUrlPreset('https://api.groq.com/openai/v1')">
                                <i class="bi bi-lightning-charge text-success"></i> Groq Free API (100% Gratis)
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm rounded-pill py-0 px-2 small" onclick="setBaseUrlPreset('https://openrouter.ai/api/v1')">
                                OpenRouter Free
                            </button>
                            <button type="button" class="btn btn-outline-dark btn-sm rounded-pill py-0 px-2 small" onclick="setBaseUrlPreset('http://localhost:11434/v1')">
                                Local Ollama
                            </button>
                        </div>
                        <div class="form-text small text-muted mt-1">
                            Anda bisa mengganti endpoint ke Groq (<code>https://api.groq.com/openai/v1</code>) untuk menikmati generasi AI super cepat tanpa biaya token.
                        </div>
                    </div>

                    <!-- Max Tokens & Response Caching -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Batas Output Max Completion Tokens</label>
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
                            <label class="form-label fw-semibold">Caching Respon AI Cerdas</label>
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
                                Mencegah token OpenAI terpotong dua kali untuk saham dan tanggal analisis yang sama.
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

    if (isZeroCost) {
        if (zeroCard) {
            zeroCard.classList.add('border-success', 'bg-success', 'bg-opacity-10');
            zeroCard.classList.remove('border-light-subtle');
        }
        if (aiCard) {
            aiCard.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
            aiCard.classList.add('border-light-subtle');
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
    }
}

function setBaseUrlPreset(url) {
    var field = document.getElementById('openai_base_url_field');
    if (field) {
        field.value = url;
        field.focus();
    }
}
</script>
