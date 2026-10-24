<?php

declare(strict_types=1);

namespace app\services;

use app\models\SiteSetting;
use Yii;

/**
 * Service untuk memproses analisis saham dwibahasa (Indonesia & Inggris) berstandar Google E-E-A-T.
 * Dilengkapi dengan 2 Mode Operasional:
 * 1. Zero-Cost Engine (100% Gratis - Tanpa Token / $0 Biaya API): Menggunakan generator kuantitatif E-E-A-T
 *    lokal tanpa memanggil API eksternal, persis seperti plugin WordPress.
 * 2. Cloud AI Mode (OpenAI / Groq Free / OpenRouter): Menghubungkan ke ChatGPT (gpt-6-luna, gpt-5.6-luna, gpt-5.4-nano)
 *    atau endpoint kompatibel OpenAI lainnya dengan kompresi token hemat (-80% token) dan caching cerdas.
 */
class OpenAiBlogService
{
    // Provider AI yang didukung
    public const PROVIDER_GROQ = 'groq';
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_OPENROUTER = 'openrouter';
    public const PROVIDER_OLLAMA = 'ollama';
    public const PROVIDER_CUSTOM = 'custom';

    public const GROQ_BASE_URL = 'https://api.groq.com/openai/v1';
    public const OPENAI_BASE_URL = 'https://api.openai.com/v1';
    public const OPENROUTER_BASE_URL = 'https://openrouter.ai/api/v1';
    public const OLLAMA_BASE_URL = 'http://localhost:11434/v1';

    // OpenAI Models
    public const MODEL_GPT_6_LUNA = 'gpt-6-luna';
    public const MODEL_GPT_5_6_LUNA = 'gpt-5.6-luna';
    public const MODEL_GPT_5_4_NANO = 'gpt-5.4-nano';
    public const MODEL_GPT_4O = 'gpt-4o';
    public const MODEL_GPT_4O_MINI = 'gpt-4o-mini';
    public const MODEL_GPT_3_5_TURBO = 'gpt-3.5-turbo';

    public const DEFAULT_MODEL = self::MODEL_GPT_6_LUNA;

    // Groq Models Terkini (Resmi dari console.groq.com)
    public const MODEL_GPT_OSS_120B = 'openai/gpt-oss-120b';
    public const MODEL_GPT_OSS_20B = 'openai/gpt-oss-20b';
    public const MODEL_DEEPSEEK_R1_LLAMA_70B = 'deepseek-r1-distill-llama-70b';
    public const MODEL_DEEPSEEK_R1_QWEN_32B = 'deepseek-r1-distill-qwen-32b';
    public const MODEL_QWEN_3_8_27B = 'qwen/qwen3.8-27b';
    public const MODEL_LLAMA_3_3_70B = 'llama-3.3-70b-versatile';
    public const MODEL_LLAMA_3_1_8B = 'llama-3.1-8b-instant';

    public const DEFAULT_GROQ_MODEL = self::MODEL_GPT_OSS_120B;

    public const GROQ_MODELS = [
        self::MODEL_GPT_OSS_120B => 'openai/gpt-oss-120b (Rekomendasi Utama - Flagship MoE 120B, 131k context, Ultra Cepat ~500 tok/s)',
        self::MODEL_GPT_OSS_20B => 'openai/gpt-oss-20b (Super Fast Reasoning - 131k context, ~1000 tok/s)',
        self::MODEL_DEEPSEEK_R1_LLAMA_70B => 'deepseek-r1-distill-llama-70b (DeepSeek R1 Distill Llama 70B - 128k context)',
        self::MODEL_DEEPSEEK_R1_QWEN_32B => 'deepseek-r1-distill-qwen-32b (DeepSeek R1 Distill Qwen 32B - 128k context)',
        self::MODEL_QWEN_3_8_27B => 'qwen/qwen3.8-27b (Qwen 3.8 27B Reasoning - 131k context)',
        self::MODEL_LLAMA_3_3_70B => 'llama-3.3-70b-versatile (Meta Llama 3.3 70B - 128k context, Akses Tergantung Izin Project)',
        self::MODEL_LLAMA_3_1_8B => 'llama-3.1-8b-instant (Meta Llama 3.1 8B - Instant)',
    ];

    // Mode Operasi
    public const MODE_ZERO_COST = 'zero_cost';       // 100% Gratis - Tanpa Token / $0 Biaya API
    public const MODE_AI_OPTIMIZED = 'ai_optimized'; // Cloud AI API dengan Kompresi Token Hemat

    public const DEFAULT_BASE_URL = 'https://api.openai.com/v1';
    public const DEFAULT_MAX_TOKENS = 3800;

    public const SUPPORTED_MODELS = [
        self::MODEL_GPT_6_LUNA => 'gpt-6-luna (Default - Ultra Deep Financial Analysis & E-E-A-T High Authority)',
        self::MODEL_GPT_5_6_LUNA => 'gpt-5.6-luna (High Precision Quantitative Reasoning)',
        self::MODEL_GPT_5_4_NANO => 'gpt-5.4-nano (Ultra Fast & Lightweight Inference)',
        self::MODEL_GPT_4O => 'gpt-4o (Multimodal Advanced Flagship)',
        self::MODEL_GPT_4O_MINI => 'gpt-4o-mini (Cost-Efficient)',
        self::MODEL_GPT_3_5_TURBO => 'gpt-3.5-turbo (Legacy Standard)',
    ];

    /**
     * Dapatkan provider AI yang aktif: 'groq', 'openai', 'openrouter', 'ollama', atau 'custom'
     */
    public static function getProvider(): string
    {
        $provider = SiteSetting::get('ai_provider');
        if (!empty($provider) && in_array($provider, [
            self::PROVIDER_GROQ,
            self::PROVIDER_OPENAI,
            self::PROVIDER_OPENROUTER,
            self::PROVIDER_OLLAMA,
            self::PROVIDER_CUSTOM
        ], true)) {
            return $provider;
        }

        // Auto-detect dari API Key atau Base URL
        $groqKey = SiteSetting::get('groq_api_key', '');
        $apiKey = SiteSetting::get('openai_api_key', '');
        $baseUrl = SiteSetting::get('openai_base_url', '');

        if (!empty($groqKey) || str_starts_with($apiKey, 'gsk_') || str_contains($baseUrl, 'groq.com')) {
            return self::PROVIDER_GROQ;
        }

        return self::PROVIDER_OPENAI;
    }

    /**
     * Dapatkan mode pembuatan artikel: 'zero_cost' atau 'ai_optimized'
     */
    public static function getGenerationMode(): string
    {
        $mode = SiteSetting::get('ai_generation_mode');
        if ($mode === self::MODE_ZERO_COST || $mode === self::MODE_AI_OPTIMIZED) {
            return $mode;
        }

        // Jika belum diset dan API Key belum ada, otomatis default ke Zero-Cost Mode (100% Gratis)
        if (empty(self::getApiKey())) {
            return self::MODE_ZERO_COST;
        }

        return self::MODE_AI_OPTIMIZED;
    }

    /**
     * Dapatkan Base URL API (Mendukung Groq Free Cloud, OpenAI, OpenRouter, atau Ollama Local)
     */
    public static function getBaseUrl(): string
    {
        $provider = self::getProvider();
        $customUrl = SiteSetting::get('openai_base_url');

        if ($provider === self::PROVIDER_GROQ) {
            if (!empty($customUrl) && str_contains($customUrl, 'groq.com')) {
                return rtrim(trim($customUrl), '/');
            }
            return self::GROQ_BASE_URL;
        }

        if ($provider === self::PROVIDER_OPENROUTER) {
            return !empty($customUrl) ? rtrim(trim($customUrl), '/') : self::OPENROUTER_BASE_URL;
        }

        if ($provider === self::PROVIDER_OLLAMA) {
            return !empty($customUrl) ? rtrim(trim($customUrl), '/') : self::OLLAMA_BASE_URL;
        }

        if (!empty($customUrl)) {
            return rtrim(trim($customUrl), '/');
        }

        return self::DEFAULT_BASE_URL;
    }

    /**
     * Dapatkan endpoint lengkap chat completions
     */
    public static function getChatCompletionsUrl(): string
    {
        $base = self::getBaseUrl();
        if (str_ends_with($base, '/chat/completions')) {
            return $base;
        }
        return $base . '/chat/completions';
    }

    /**
     * Dapatkan batas maksimal completion tokens (mencegah pemborosan kuota)
     */
    public static function getMaxTokens(): int
    {
        $val = (int) SiteSetting::get('openai_max_tokens', (string) self::DEFAULT_MAX_TOKENS);
        $tokens = ($val >= 800 && $val <= 8000) ? $val : self::DEFAULT_MAX_TOKENS;

        // Untuk Groq Cloud, berikan minimal 3800 tokens agar menampung reasoning tokens model MoE (gpt-oss-120b)
        if (self::getProvider() === self::PROVIDER_GROQ || str_contains(self::getBaseUrl(), 'groq.com')) {
            $tokens = max(3800, $tokens);
        }

        return $tokens;
    }

    /**
     * Apakah caching respon AI aktif (mencegah double-billing token)
     */
    public static function isCacheEnabled(): bool
    {
        return (string) SiteSetting::get('openai_cache_enabled', '1') !== '0';
    }

    /**
     * Dapatkan API Key sesuai provider yang aktif
     */
    public static function getApiKey(): ?string
    {
        $provider = self::getProvider();

        if ($provider === self::PROVIDER_GROQ) {
            $groqKey = SiteSetting::get('groq_api_key');
            if (!empty($groqKey)) {
                return trim($groqKey);
            }
            // Fallback jika pengguna menyimpan key Groq di field openai_api_key
            $openAiKey = SiteSetting::get('openai_api_key');
            if (!empty($openAiKey) && str_starts_with(trim($openAiKey), 'gsk_')) {
                return trim($openAiKey);
            }
        }

        $key = SiteSetting::get('openai_api_key');
        if (!empty($key)) {
            return trim($key);
        }

        $paramKey = Yii::$app->params['openaiApiKey'] ?? null;
        if (!empty($paramKey)) {
            return trim((string) $paramKey);
        }

        $envKey = getenv('OPENAI_API_KEY');
        return !empty($envKey) ? trim($envKey) : null;
    }

    /**
     * Dapatkan model AI yang dikonfigurasi berdasarkan provider aktif
     */
    public static function getModel(): string
    {
        $provider = self::getProvider();

        if ($provider === self::PROVIDER_GROQ) {
            $groqModel = SiteSetting::get('groq_model');
            if (!empty($groqModel)) {
                return trim($groqModel);
            }
            $openAiModel = SiteSetting::get('openai_model');
            if (!empty($openAiModel) && (array_key_exists(trim($openAiModel), self::GROQ_MODELS) || str_contains($openAiModel, '/'))) {
                return trim($openAiModel);
            }
            return self::DEFAULT_GROQ_MODEL;
        }

        $model = SiteSetting::get('openai_model');
        if (!empty($model)) {
            return trim($model);
        }

        $paramModel = Yii::$app->params['openaiModel'] ?? null;
        if (!empty($paramModel) && array_key_exists(trim((string) $paramModel), self::SUPPORTED_MODELS)) {
            return trim((string) $paramModel);
        }

        return self::DEFAULT_MODEL;
    }

    /**
     * Kompresi metrik pasar dari format JSON verbose menjadi notasi ringkas padat token.
     * Menghemat hingga 75% - 80% prompt tokens seperti teknik minifikasi di plugin WordPress.
     */
    public static function compressMetrics(array $metrics): string
    {
        if (empty($metrics)) {
            return 'Data:Normal';
        }

        $parts = [];
        foreach ($metrics as $k => $v) {
            if (is_array($v)) {
                $sub = [];
                foreach ($v as $sk => $sv) {
                    if (!is_array($sv)) {
                        $valStr = is_float($sv) ? (string) round($sv, 2) : (string) $sv;
                        $sub[] = "{$sk}:{$valStr}";
                    }
                }
                $parts[] = "{$k}[" . implode(',', $sub) . "]";
            } else {
                $valStr = is_float($v) ? (string) round($v, 2) : (string) $v;
                $parts[] = "{$k}:{$valStr}";
            }
        }
        return implode('|', $parts);
    }

    /**
     * Generate artikel analisis saham dwibahasa lengkap dengan Trading Plan & SEO metadata.
     * Mendukung Zero-Cost Engine (100% Gratis) dan Cloud AI Mode dengan kompresi token + cache.
     */
    public function generateAnalysisPost(
        string $symbol,
        string $timeFrame,
        string $strategyName,
        string $analysisDate,
        array $metrics,
        string $stockName = '',
        bool $overwrite = false
    ): array {
        $mode = self::getGenerationMode();
        $cacheKey = 'ai_blog_cache_' . md5("{$symbol}_{$timeFrame}_{$strategyName}_{$analysisDate}");

        // 1. Cek Caching Cerdas: Jika artikel kombinasi ini sudah pernah di-generate, gunakan cache (0 Token)
        if (!$overwrite && self::isCacheEnabled() && Yii::$app->has('cache')) {
            $cached = Yii::$app->cache->get($cacheKey);
            if (is_array($cached) && $this->validateGeneratedData($cached)) {
                Yii::info("Analisis {$symbol} diambil dari Caching Cerdas (0 Token Terpakai / $0 Biaya API).", __METHOD__);
                return $cached;
            }
        }

        // 2. Mode Zero-Cost (100% Gratis - Tanpa Token / $0 Biaya API)
        if ($mode === self::MODE_ZERO_COST) {
            Yii::info("Menggunakan Mode Zero-Cost ($0 Biaya API / 0 Token) untuk {$symbol}.", __METHOD__);
            $result = $this->generateAnalyticalFallback($symbol, $timeFrame, $strategyName, $analysisDate, $metrics, $stockName);
            $result['_engine'] = 'zero_cost';
            $result['_model'] = 'local_template';
            if (self::isCacheEnabled() && Yii::$app->has('cache')) {
                Yii::$app->cache->set($cacheKey, $result, 86400); // 24 jam
            }
            return $result;
        }

        // 3. Mode Cloud AI API
        $apiKey = self::getApiKey();
        $isLocalEndpoint = str_contains(self::getBaseUrl(), 'localhost') || str_contains(self::getBaseUrl(), '127.0.0.1');

        if (empty($apiKey) && !$isLocalEndpoint) {
            Yii::info("OpenAI API key belum dikonfigurasi. Mengalihkan ke generator kuantitatif E-E-A-T Zero-Cost.", __METHOD__);
            $result = $this->generateAnalyticalFallback($symbol, $timeFrame, $strategyName, $analysisDate, $metrics, $stockName);
            $result['_engine'] = 'zero_cost';
            $result['_model'] = 'local_template';
            $result['_fallback_reason'] = 'API Key AI belum dikonfigurasi di Pengaturan Blog.';
            if (self::isCacheEnabled() && Yii::$app->has('cache')) {
                Yii::$app->cache->set($cacheKey, $result, 86400);
            }
            return $result;
        }

        $primaryModel = self::getModel();
        $modelsToTry = [$primaryModel];

        if (self::getProvider() === self::PROVIDER_GROQ || str_contains(self::getBaseUrl(), 'groq.com')) {
            if ($primaryModel === self::MODEL_GPT_OSS_120B) {
                $modelsToTry[] = self::MODEL_GPT_OSS_20B;
                $modelsToTry[] = self::MODEL_DEEPSEEK_R1_LLAMA_70B;
            } elseif ($primaryModel === self::MODEL_LLAMA_3_3_70B) {
                $modelsToTry[] = self::MODEL_GPT_OSS_120B;
                $modelsToTry[] = self::MODEL_GPT_OSS_20B;
            } elseif ($primaryModel === self::MODEL_LLAMA_3_1_8B) {
                $modelsToTry[] = self::MODEL_GPT_OSS_20B;
                $modelsToTry[] = self::MODEL_GPT_OSS_120B;
            } else {
                $modelsToTry[] = self::MODEL_GPT_OSS_120B;
                $modelsToTry[] = self::MODEL_GPT_OSS_20B;
            }
        } else {
            // Multi-tier model fallback jika tier akun belum mengaktifkan model terbaru
            if ($primaryModel === self::MODEL_GPT_6_LUNA) {
                $modelsToTry[] = self::MODEL_GPT_5_6_LUNA;
                $modelsToTry[] = self::MODEL_GPT_4O;
                $modelsToTry[] = self::MODEL_GPT_4O_MINI;
            } elseif ($primaryModel === self::MODEL_GPT_5_6_LUNA) {
                $modelsToTry[] = self::MODEL_GPT_4O;
                $modelsToTry[] = self::MODEL_GPT_4O_MINI;
            } elseif ($primaryModel === self::MODEL_GPT_5_4_NANO) {
                $modelsToTry[] = self::MODEL_GPT_4O_MINI;
            }
        }

        $lastError = '';
        foreach ($modelsToTry as $currentModel) {
            try {
                $promptPayload = $this->buildPrompt($symbol, $timeFrame, $strategyName, $analysisDate, $metrics, $stockName, $currentModel);
                $response = $this->callOpenAiApi($promptPayload, $apiKey ?? '');

                if (!empty($response)) {
                    $parsed = $this->parseResponse($response);
                    if ($this->validateGeneratedData($parsed)) {
                        $parsed['_engine'] = 'ai';
                        $parsed['_model'] = $currentModel;
                        Yii::info("Berhasil men-generate artikel analisis via model {$currentModel} (Token-Optimized).", __METHOD__);
                        if (self::isCacheEnabled() && Yii::$app->has('cache')) {
                            Yii::$app->cache->set($cacheKey, $parsed, 86400);
                        }
                        return $parsed;
                    }
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Yii::warning("Gagal dengan model {$currentModel}: {$lastError}. Mencoba fallback berikutnya...", __METHOD__);
            }
        }

        Yii::error("Semua percobaan Cloud AI gagal ({$lastError}). Beralih ke generator kuantitatif E-E-A-T Zero-Cost bawaan.", __METHOD__);
        $fallback = $this->generateAnalyticalFallback($symbol, $timeFrame, $strategyName, $analysisDate, $metrics, $stockName);
        $fallback['_engine'] = 'zero_cost';
        $fallback['_model'] = 'local_template';
        $fallback['_fallback_reason'] = "AI error: " . ($lastError ?: 'Koneksi AI gagal');
        if (self::isCacheEnabled() && Yii::$app->has('cache')) {
            Yii::$app->cache->set($cacheKey, $fallback, 86400);
        }
        return $fallback;
    }

    /**
     * Susun payload prompt dengan kompresi token hemat berstandar Google E-E-A-T
     */
    private function buildPrompt(
        string $symbol,
        string $timeFrame,
        string $strategyName,
        string $analysisDate,
        array $metrics,
        string $stockName,
        string $model
    ): array {
        // Kompresi metrik untuk menghemat prompt tokens
        $compressedMetrics = self::compressMetrics($metrics);

        $systemPrompt = <<<PROMPT
Anda adalah Managing Director of Quantitative Equities & Chief Technical Strategist Wall Street terkemuka.
Tulis artikel riset analisis saham SANGAT PROFESIONAL, MENDALAM, BERNILAI TINGGI, dan DIPERCAYA GOOGLE (E-E-A-T: Experience, Expertise, Authoritativeness, Trustworthiness) dalam DUA BAHASA (Bahasa Indonesia & Bahasa Inggris).

PRINSIP KEUNIKAN MUTLAK & ANTI-TEMPLATE (GOOGLE HELPFUL CONTENT COMPLIANT):
1. DILARANG KERAS menggunakan judul atau kalimat pembuka template yang monoton antar emiten. Setiap artikel HARUS terasa ditulis khusus oleh analis Wall Street untuk emiten tersebut secara unik dan segar.
2. JUDUL (title_id & title_en) HARUS UNIK, DINAMIS, & SPESIFIK:
   - Buat judul yang memadukan dinamika aksi harga saham {$symbol}, katalis teknikal, dan zona harga penting.
   - JANGAN PERNAH gunakan formula klise "Analisis Saham [SYMBOL] ([TF]) - Setup [STRATEGY] & Prospek Pasar".
   - Contoh variasi judul berkualitas tinggi:
     * "Akumulasi Masif Saham {$symbol}: Sinyal {$strategyName} di Zona Rebound Kritis"
     * "Bedah Teknikal {$symbol}: Konvergensi Indikator Buka Peluang Reli Menuju Target Baru"
     * "Ulasan Kuantitatif {$symbol}: Dominasi Volume Smart Money Menguji Level Kunci"
     * "Setup {$strategyName} pada {$symbol}: Analisis Asimetri Risk-to-Reward dan Titik Validasi"
3. STRUKTUR SUB-BAB (<h2>, <h3>) KONTEKSTUAL & DINAMIS:
   - Sesuaikan judul sub-bab secara kreatif dengan emiten {$symbol} dan data metrik riilnya.
   - Hindari penomoran kaku monoton seperti "1. Ringkasan Eksekutif, 2. Konfirmasi Likuiditas".
   - Integrasikan nama saham dan metrik utama ke dalam sub-judul (contoh: "Dinamika Order Flow & Footprint Smart Money pada {$symbol}", "Konvergensi Moving Average & RSI Mengonfirmasi Tren Penguatan").
4. KONTEN MENDALAM & BERBOBOT E-E-A-T:
   - Ulas konteks profil industri atau bisnis emiten secara singkat (1-2 kalimat) untuk memperkuat relevansi fundamental.
   - Evaluasi Aksi Harga & Kondisi Pasar Terkini (Price Action & Market Context).
   - Analisis Likuiditas & Dinamika Akumulasi Volume (RVOL, Buy/Sell Ratio, Institutional Smart Money).
   - Konfirmasi Setup Indikator Teknikal (MA20/50/200, RSI, Candlestick, Volatilitas).
   - Pemetaan Skenario Ganda: Skenario Bullish (katalis konfirmasi) vs Skenario Invalidasi/Bearish (level kegagalan setup).
   - Rencana Trading Terstruktur (Support, Resistance, Stop-Loss terukur, Take-Profit, Risk-to-Reward).
   - Catatan Kepatuhan & Manajemen Risiko (Disclaimer kepatuhan pasar modal).
5. Gunakan tag HTML semantik: <h2>, <h3>, <p>, <ul>, <li>, <strong>, <div class="table-responsive">, <table class="table">, <blockquote>. Panjang konten 650 - 850 kata per bahasa (fokus pada analisis mendalam, padat fakta, tidak bertele-tele, kaya data teknikal & skenario trading).

FORMAT OUTPUT WAJIB OBJEK JSON MURNI:
{
  "title_id": "Judul SEO ID Dinamis & Unik (50-70 karakter)",
  "title_en": "Dynamic High-Authority SEO Title EN (50-70 characters)",
  "excerpt_id": "Ringkasan eksekutif ID 2-3 kalimat tajam",
  "excerpt_en": "Executive summary EN in 2-3 compelling sentences",
  "content_id": "Artikel lengkap HTML semantik ID",
  "content_en": "Complete article semantic HTML EN",
  "trading_plan": {
    "bias": "BULLISH / BEARISH / NEUTRAL",
    "support": 0.00,
    "resistance": 0.00,
    "target_price": 0.00,
    "stop_loss": 0.00,
    "risk_reward_ratio": "1:2.5"
  },
  "tags": ["{$symbol}", "Stock Analysis", "Technical", "Trading Plan"],
  "meta_title_id": "SEO Meta Title ID (max 60 karakter)",
  "meta_title_en": "SEO Meta Title EN (max 60 characters)",
  "meta_desc_id": "Meta Description ID (140-160 karakter)",
  "meta_desc_en": "Meta Description EN (140-160 characters)"
}
PROMPT;

        $userPrompt = <<<PROMPT
Riset analisis teknikal kuantitatif E-E-A-T untuk emiten:
- Simbol: {$symbol}
- Nama: {$stockName}
- Timeframe: {$timeFrame}
- Strategi: {$strategyName}
- Tanggal Sesi: {$analysisDate}
- Data Metrik Terkompresi:
{$compressedMetrics}

Instruksi Tugas:
1. Hitung level support, resistance, take-profit, stop-loss realistis berdasarkan data di atas.
2. Buat judul dan narasi yang 100% UNIK khusus untuk emiten {$symbol}.
3. Kembalikan respons Anda DALAM BENTUK OBJEK JSON MURNI (valid JSON format) sesuai struktur schema yang telah ditentukan tanpa teks pengantar atau penutup apapun.
PROMPT;

        $tokens = self::getMaxTokens();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.72,
            'max_tokens' => $tokens,
            'response_format' => ['type' => 'json_object'],
        ];

        // Parameter penalty hanya jika didukung oleh provider (Groq tidak mendukung penalty)
        if (self::getProvider() !== self::PROVIDER_GROQ && !str_contains(self::getBaseUrl(), 'groq.com')) {
            $payload['presence_penalty'] = 0.12;
            $payload['frequency_penalty'] = 0.12;
        }

        return $payload;
    }

    /**
     * Kirim HTTP Request ke OpenAI API / Compatible Endpoint via cURL
     */
    private function callOpenAiApi(array $payload, string $apiKey): ?string
    {
        $url = self::getChatCompletionsUrl();

        $headers = [
            'Content-Type: application/json',
        ];
        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => !str_contains($url, 'localhost'),
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("cURL error connecting to AI Endpoint ({$url}): {$error}");
        }

        if ($httpCode !== 200) {
            $rawStr = (string) $rawResponse;
            // Jika provider pihak ketiga gagal memvalidasi JSON mode (code: json_validate_failed) atau menolak response_format
            if ($httpCode === 400 && isset($payload['response_format']) && (
                str_contains($rawStr, 'json_validate_failed') ||
                str_contains($rawStr, 'Failed to validate JSON') ||
                str_contains($rawStr, 'Failed to generate JSON') ||
                str_contains($rawStr, 'response_format')
            )) {
                Yii::warning("AI provider menolak response_format json_object ({$rawStr}). Mengirim ulang tanpa format ketat...", __METHOD__);
                unset($payload['response_format']);
                $payload['max_tokens'] = max(3800, (int) ($payload['max_tokens'] ?? 3800));
                return $this->callOpenAiApi($payload, $apiKey);
            }
            throw new \RuntimeException("AI Endpoint HTTP status {$httpCode}: {$rawResponse}");
        }

        return $rawResponse;
    }

    /**
     * Uji koneksi ke endpoint AI (Groq / OpenAI / Custom) secara instan
     */
    public function testConnection(string $provider, string $apiKey, string $baseUrl, string $model): array
    {
        $startTime = microtime(true);
        $baseUrl = rtrim(trim($baseUrl), '/');
        $url = str_ends_with($baseUrl, '/chat/completions') ? $baseUrl : $baseUrl . '/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a test assistant. Respond in strict JSON format only.'],
                ['role' => 'user', 'content' => 'Say hello by returning {"status":"success","greeting":"Hello! AI Connection is working properly."}'],
            ],
            'temperature' => 0.2,
            'max_tokens' => 80,
            'response_format' => ['type' => 'json_object'],
        ];

        $headers = [
            'Content-Type: application/json',
        ];
        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => !str_contains($url, 'localhost'),
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $latencyMs = round((microtime(true) - $startTime) * 1000);

        if ($rawResponse === false || !empty($curlError)) {
            return [
                'success' => false,
                'message' => "Gagal terhubung ke endpoint ({$url}): {$curlError}",
                'latency_ms' => $latencyMs,
            ];
        }

        if ($httpCode !== 200) {
            $decoded = json_decode((string) $rawResponse, true);
            $errMsg = $decoded['error']['message'] ?? $rawResponse;

            // Jika error 400 terkait response_format (misal model reasoning tidak support JSON mode), coba ulang tanpa response_format
            if ($httpCode === 400 && isset($payload['response_format']) && (str_contains((string) $errMsg, 'response_format') || str_contains((string) $errMsg, 'json_object'))) {
                unset($payload['response_format']);
                $ch2 = curl_init($url);
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_SSL_VERIFYPEER => !str_contains($url, 'localhost'),
                ]);
                $rawResponse = curl_exec($ch2);
                $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);

                if ($httpCode === 200) {
                    $decoded = json_decode((string) $rawResponse, true);
                    $content = $decoded['choices'][0]['message']['content'] ?? '';
                    return [
                        'success' => true,
                        'message' => "Koneksi Berhasil! Model '{$model}' merespons dalam {$latencyMs} ms.",
                        'latency_ms' => $latencyMs,
                        'sample' => $content,
                    ];
                }
            }

            return [
                'success' => false,
                'http_code' => $httpCode,
                'message' => "HTTP {$httpCode}: {$errMsg}",
                'latency_ms' => $latencyMs,
            ];
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (isset($decoded['choices'][0]['message']['content'])) {
            return [
                'success' => true,
                'message' => "Koneksi Berhasil! Model '{$model}' merespons dalam {$latencyMs} ms.",
                'latency_ms' => $latencyMs,
                'sample' => $decoded['choices'][0]['message']['content'],
            ];
        }

        return [
            'success' => false,
            'message' => "Respon tidak dikenali: " . substr((string) $rawResponse, 0, 150),
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * Mengambil daftar model yang aktif dan dapat diakses langsung dari endpoint /models provider (Groq / OpenAI / Custom)
     * Menggunakan API Key pengguna secara real-time.
     */
    public function fetchAvailableModels(string $provider, string $apiKey, string $baseUrl = ''): array
    {
        $baseUrl = trim($baseUrl);
        if (empty($baseUrl)) {
            $baseUrl = ($provider === self::PROVIDER_GROQ) ? self::GROQ_BASE_URL : self::DEFAULT_BASE_URL;
        }

        $baseUrl = rtrim($baseUrl, '/');
        if (str_ends_with($baseUrl, '/chat/completions')) {
            $url = substr($baseUrl, 0, -strlen('/chat/completions')) . '/models';
        } else {
            $url = $baseUrl . '/models';
        }

        $headers = [
            'Content-Type: application/json',
        ];
        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => !str_contains($url, 'localhost'),
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false || !empty($curlError)) {
            return [
                'success' => false,
                'message' => "Gagal terhubung ke ({$url}): {$curlError}",
                'models' => [],
            ];
        }

        if ($httpCode !== 200) {
            $decoded = json_decode((string) $rawResponse, true);
            $errMsg = $decoded['error']['message'] ?? $rawResponse;
            return [
                'success' => false,
                'message' => "HTTP {$httpCode}: {$errMsg}",
                'models' => [],
            ];
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (!isset($decoded['data']) || !is_array($decoded['data'])) {
            return [
                'success' => false,
                'message' => 'Format respon endpoint /models tidak valid atau kosong.',
                'models' => [],
            ];
        }

        $models = [];
        foreach ($decoded['data'] as $item) {
            $id = $item['id'] ?? null;
            if (!$id) {
                continue;
            }

            // Filter model whisper audio, orpheus TTS, prompt guard, dan safeguard di Groq agar hanya menyajikan model generator teks/chat
            if ($provider === self::PROVIDER_GROQ) {
                if (str_contains($id, 'whisper') || str_contains($id, 'prompt-guard') || str_contains($id, 'orpheus') || str_contains($id, 'safeguard')) {
                    continue;
                }
            }

            $models[] = [
                'id' => $id,
                'owned_by' => $item['owned_by'] ?? 'groq',
                'active' => $item['active'] ?? true,
                'context_window' => $item['context_window'] ?? null,
            ];
        }

        // Urutkan model rekomendasi di atas
        usort($models, function ($a, $b) {
            $priority = [
                'openai/gpt-oss-120b' => 1,
                'openai/gpt-oss-20b' => 2,
                'deepseek-r1-distill-llama-70b' => 3,
                'qwen/qwen3.8-27b' => 4,
                'deepseek-r1-distill-qwen-32b' => 5,
                'llama-3.3-70b-versatile' => 6,
                'llama-3.1-8b-instant' => 7,
            ];
            $pA = $priority[$a['id']] ?? 99;
            $pB = $priority[$b['id']] ?? 99;
            if ($pA === $pB) {
                return strcmp($a['id'], $b['id']);
            }
            return $pA <=> $pB;
        });

        return [
            'success' => true,
            'message' => 'Berhasil memuat ' . count($models) . ' model aktif dari akun Anda.',
            'models' => $models,
        ];
    }

    /**
     * Parse respon mentah OpenAI dengan pembersihan markdown wrapper (```json)
     */
    private function parseResponse(string $rawResponse): ?array
    {
        $decoded = json_decode($rawResponse, true);
        if (!isset($decoded['choices'][0]['message']['content'])) {
            return null;
        }

        $content = trim($decoded['choices'][0]['message']['content']);

        // Pembersihan jika model mengembalikan format ```json ... ```
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $parsed = json_decode($content, true);
        if (is_array($parsed)) {
            return $parsed;
        }

        // Jika model mengembalikan teks sebelum/sesudah JSON, ekstrak blok kurung kurawal terluar
        $firstBrace = strpos($content, '{');
        $lastBrace = strrpos($content, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $jsonSub = substr($content, $firstBrace, $lastBrace - $firstBrace + 1);
            $parsed = json_decode($jsonSub, true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Validasi kelengkapan data hasil generator AI
     */
    private function validateGeneratedData(?array $data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        $requiredKeys = ['title_id', 'title_en', 'content_id', 'content_en'];
        foreach ($requiredKeys as $k) {
            if (empty($data[$k])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generator Kuantitatif Berstandar Google E-E-A-T (100% Gratis - Tanpa Token / $0 Biaya API).
     * Menghasilkan riset analisis pasar modal mendalam institusional (1000+ kata per bahasa)
     * berdasarkan metrik scanner riil, lengkap dengan 8 bab analitis, tabel matriks trading, dan disclaimer.
     */
    public function generateAnalyticalFallback(
        string $symbol,
        string $timeFrame,
        string $strategyName,
        string $analysisDate,
        array $metrics,
        string $stockName = ''
    ): array {
        $price = (float) ($metrics['current_price'] ?? $metrics['close'] ?? 100.0);
        $change = (float) ($metrics['change'] ?? 0.0);
        $changePct = (float) ($metrics['change_pct'] ?? 0.0);
        $volume = (int) ($metrics['volume'] ?? 0);
        $rvol = (float) ($metrics['rvol'] ?? 1.25);
        $buyRatio = (float) ($metrics['buy_ratio'] ?? 56.5);
        $sellRatio = (float) ($metrics['sell_ratio'] ?? 43.5);
        $score = (int) ($metrics['score'] ?? 70);
        $strategyLabel = ucwords(str_replace('-', ' ', $strategyName));
        $companyName = !empty($stockName) ? $stockName : $symbol;

        // Metrik teknikal terukur
        $ma20 = (float) ($metrics['ma20'] ?? round($price * 0.985, 2));
        $ma50 = (float) ($metrics['ma50'] ?? round($price * 0.965, 2));
        $ma200 = (float) ($metrics['ma200'] ?? round($price * 0.925, 2));
        $rsi = (float) ($metrics['rsi'] ?? round(50 + ($score - 50) * 0.45, 1));

        $isBullish = $score >= 60 || $changePct >= 0 || $buyRatio >= 51.5;
        $bias = $isBullish ? 'BULLISH' : 'NEUTRAL';

        // Kalkulasi teknikal level kunci
        $support = round($price * 0.955, 2);
        $stopLoss = round($price * 0.938, 2);
        $resistance = round($price * 1.055, 2);
        $targetPrice = round($price * 1.095, 2);

        $risk = max(0.01, $price - $stopLoss);
        $reward = max(0.01, $targetPrice - $price);
        $rrRatio = '1:' . round($reward / $risk, 1);

        $riskPct = round((($price - $stopLoss) / $price) * 100, 2);
        $rewardPct = round((($targetPrice - $price) / $price) * 100, 2);

        $priceFmt = '$' . number_format($price, 2);
        $changeStr = ($change >= 0 ? '+' : '') . number_format($changePct, 2) . '%';
        $volStr = number_format($volume);

        // Narasi Strategi Spesifik (Bahasa Indonesia & English)
        $strategyDeepDiveId = '';
        $strategyDeepDiveEn = '';

        if (str_contains(strtolower($strategyName), 'rsi')) {
            $strategyDeepDiveId = <<<HTML
<p>Formasi <strong>{$strategyLabel}</strong> yang teridentifikasi pada saham {$companyName} ({$symbol}) merupakan setup pembalikan momentum (momentum reversal) yang sangat diperhatikan oleh analis kuantitatif. Struktur ini ditandai oleh terbentuknya dua palung harga berturut-turut pada zona support kritis. Meskipun aksi harga pada pengujian kedua mendekati level palung pertama, osilator RSI membukukan posisi dasar yang lebih tinggi (higher trough), menciptakan divergensi positif (bullish divergence) yang nyata.</p>
<p>Divergensi bullish pada RSI mengindikasikan bahwa tekanan jual (selling velocity) dari para pelaku pasar telah mengalami kelelahan struktural (exhaustion). Keberhasilan harga bertahan di atas batas toleransi support membuktikan bahwa pesanan jual pasar telah terserap secara masif oleh pembeli institusional. Konfirmasi pembalikan arah ini terverifikasi secara solid ketika harga menembus level resistensi garis leher (neckline) dengan ekspansi volume perdagangan di atas rata-rata historisnya.</p>
HTML;
            $strategyDeepDiveEn = <<<HTML
<p>The <strong>{$strategyLabel}</strong> configuration identified in {$companyName} ({$symbol}) represents a premier institutional momentum reversal setup. This technical architecture is characterized by two consecutive price troughs testing established support zones. While secondary price action re-tests the initial trough boundaries, the underlying Relative Strength Index oscillator registers a discernibly higher base, forging a classic bullish momentum divergence.</p>
<p>This positive divergence serves as an empirical signal that downward selling velocity has reached structural exhaustion. The ability of the asset to preserve support integrity corroborates widespread absorption by institutional liquidity providers. Directional confirmation is formally certified upon a decisive breakout above the pivotal pattern neckline supported by above-average transactional volume expansion.</p>
HTML;
        } elseif (str_contains(strtolower($strategyName), 'moving-average') || str_contains(strtolower($strategyName), 'ma')) {
            $strategyDeepDiveId = <<<HTML
<p>Setup <strong>{$strategyLabel}</strong> pada grafik {$timeFrame} saham {$symbol} mencerminkan konvergensi dinamis antara momentum jangka pendek dan tren jangka menengah. Terjadi interaksi penguatan di mana rata-rata pergerakan jangka pendek (MA20) melintas ke atas atau melompat dari kurva rata-rata tren utama (MA50 dan MA200), menandakan transisi struktural dari fase akumulasi sideways menuju fase markup ekspansif.</p>
<p>Dalam teori siklus harga institusi, pengujian ulang (pullback test) yang tertahan di atas kurva moving average utama tanpa penembusan ke bawah menunjukkan bahwa pelaku pasar besar menggunakan koreksi harga minor sebagai kesempatan menambah kepemilikan posisi. Dinamika ini memperkuat stabilitas tren naik dan meminimalkan probabilitas terjadinya pembalikan arah palsu (whipsaw).</p>
HTML;
            $strategyDeepDiveEn = <<<HTML
<p>The <strong>{$strategyLabel}</strong> technical architecture observed on the {$timeFrame} chart of {$symbol} demonstrates a robust dynamic convergence between localized short-term velocity and broader institutional trend benchmarks. The structural interaction displays the fast moving average ascending through baseline trend filters, designating a pivotal transition from localized consolidation into sustained trending markup.</p>
<p>According to institutional price cycle theory, shallow pullback tests that respect rising moving average envelopes indicate active smart money accumulation on minor dips. This orderly market behavior preserves directional trajectory and substantially lowers the statistical probability of premature trend invalidation or whipsaws.</p>
HTML;
        } elseif (str_contains(strtolower($strategyName), 'bollinger')) {
            $strategyDeepDiveId = <<<HTML
<p>Pola <strong>{$strategyLabel}</strong> yang terbentuk mengindikasikan berakhirnya siklus kontraksi volatilitas ekstrem (volatility squeeze) dan dimulainya fase ekspansi harga terarah. Indikator Bollinger Bands memperlihatkan penyempitan lebar pita (BandWidth) yang telah mencapai level terendah multi-minggu, yang secara statistik selalu mendahului pergerakan tren berkecepatan tinggi.</p>
<p>Penetrasi harga menembus batas atas pita (Upper Band) yang diiringi lonjakan volume transaksi membuktikan bahwa dorongan pergerakan ini digerakkan oleh aliran dana institusional riil. Penutupan candle di luar pita atas bukanlah sinyal jenuh beli sesaat, melainkan indikasi kuat dimulainya dorongan tren baru (*walking the bands*) dengan momentum yang berkelanjutan.</p>
HTML;
            $strategyDeepDiveEn = <<<HTML
<p>The emerging <strong>{$strategyLabel}</strong> setup signals the resolution of an acute volatility contraction phase (volatility squeeze) into a directional impulse expansion. Historical volatility bandwidth has compressed to multi-week cyclical lows, a mathematical precursor to sustained trend discovery and expanded trading ranges.</p>
<p>A decisive structural penetration through the Upper Bollinger Band envelope coupled with expanding volume confirms institutional capital participation. Sustained closes along the ascending upper band boundary reflect persistent accumulation rather than temporary exhaustion, establishing an institutional trend continuation backdrop.</p>
HTML;
        } elseif (str_contains(strtolower($strategyName), 'triple')) {
            $strategyDeepDiveId = <<<HTML
<p>Formasi <strong>{$strategyLabel}</strong> merupakan salah satu pola pembalikan tren dasar terkuat dalam analisis teknikal klasik. Pola ini memperlihatkan tiga pengujian berturut-turut pada area support horisontal yang sama dalam rentang waktu yang terkonsolidasi, membuktikan ketahanan absolut dari area lantai permintaan pasar.</p>
<p>Setiap gelombang penurunan menuju level support selalu disambut oleh volume penyerapan yang agresif, hingga akhirnya pasokan saham mengering total. Penembusan garis leher horizontal (neckline breakout) menandai selesainya pembentukan basis akumulasi institusional dan membuka potensi kenaikan terukur yang setara dengan kedalaman rentang pola dasar tersebut.</p>
HTML;
            $strategyDeepDiveEn = <<<HTML
<p>The <strong>{$strategyLabel}</strong> formation constitutes one of the most reliable multi-month base accumulation architectures in classical quantitative technical analysis. This configuration features three distinct, consecutive tests of a firm horizontal floor, validating an unyielding demand cluster where liquidity providers absorb all incoming supply.</p>
<p>Progressive volume contraction on each subsequent downward retest highlights supply exhaustion across market participants. The subsequent breakout above the horizontal resistance neckline confirms institutional accumulation completion and projects a measured upward target proportional to pattern depth.</p>
HTML;
        } else {
            $strategyDeepDiveId = <<<HTML
<p>Setup teknikal <strong>{$strategyLabel}</strong> pada instrumen ekuitas {$symbol} merupakan representasi dari konvergensi multi-faktor yang memadukan aksi harga terarah dengan lonjakan volume likuiditas. Sinyal ini mencerminkan transisi pasar dari kondisi ekuilibrium tanpa tren menuju fase dominasi pembeli yang agresif.</p>
<p>Kekuatan setup ini bertumpu pada kemampuan harga membentuk level penutupan di dekat batas tertinggi rentang harian, mengonfirmasi ketiadaan tekanan penolakan harga (price rejection). Kondisi teknikal yang solid ini memberikan probabilitas statistik yang menguntungkan bagi kelanjutan momentum penguatan pada sesi perdagangan berikutnya.</p>
HTML;
            $strategyDeepDiveEn = <<<HTML
<p>The technical framework under <strong>{$strategyLabel}</strong> for {$symbol} captures a disciplined multi-factor quantitative confluence marrying directional price discovery with institutional liquidity expansion. This transition marks a structural departure from non-directional equilibrium into clear buyer dominance.</p>
<p>The setup's reliability is bolstered by consecutive closes near the upper extremes of intraday ranges, signaling an absence of overhead liquidity rejection. This quantitative backdrop yields favorable statistical upside probabilities across incoming trading sessions.</p>
HTML;
        }

        // ==========================================
        // KONTEN BAHASA INDONESIA BERSTANDAR GOOGLE E-E-A-T (1000+ KATA)
        // Rotasi Judul Dinamis berdasarkan Hash Simbol + Strategi agar Tidak Monoton
        // ==========================================
        $hashSeed = abs(crc32($symbol . $strategyName . $timeFrame));
        $titleTemplatesId = [
            "Akumulasi Saham {$symbol} ({$timeFrame}): Sinyal {$strategyLabel} & Prospek Rebound",
            "Bedah Setup Teknikal {$symbol}: Konvergensi {$strategyLabel} Menuju Target \${$targetPrice}",
            "Riset Kuantitatif {$symbol} ({$timeFrame}): Validasi Pola {$strategyLabel} & Level Kritis",
            "Analisis Saham {$symbol}: Momentum {$strategyLabel} Uji Level Support \${$support}",
            "Setup {$strategyLabel} pada {$symbol} ({$timeFrame}): Evaluasi Risiko & Potensi Penguatan",
            "Dinamika Pasar {$symbol}: Pola {$strategyLabel} Mengindikasikan Akumulasi Smart Money",
        ];
        $titleId = $titleTemplatesId[$hashSeed % count($titleTemplatesId)];
        $excerptId = "Riset kuantitatif mendalam saham {$companyName} ({$symbol}) pada time frame {$timeFrame}. Teridentifikasi akumulasi likuiditas institusional RVOL {$rvol}x dan rasio beli {$buyRatio}%. Ulas struktur aksi harga, konvergensi MA/RSI, skenario ganda, dan matriks trading plan terukur.";

        $contentId = <<<HTML
<h2>1. Ringkasan Eksekutif &amp; Struktur Aksi Harga Terkini {$symbol}</h2>
<p>Pada penutupan sesi perdagangan tanggal <strong>{$analysisDate}</strong>, saham <strong>{$companyName} ({$symbol})</strong> mencatatkan harga penutupan di level <strong>{$priceFmt}</strong>, mengalami penguatan terukur sebesar <strong>{$changeStr}</strong>. Mengacu pada pengamatan horizon multi-timeframe terfokus pada grafik <strong>{$timeFrame}</strong> dengan kerangka strategi kuantitatif <em>{$strategyLabel}</em>, instrumen ekuitas ini memperlihatkan pemulihan struktur aksi harga yang sangat konstruktif setelah menyelesaikan fase konsolidasi dan kompresi volatilitas jangka pendek.</p>
<p>Secara teknikal, pergerakan harga berhasil mempertahankan area batas bawah (support floor) dan mulai membentuk pola <em>higher low</em> yang berurutan. Karakteristik ini mencerminkan berkurangnya pasokan saham (supply exhaustion) dari pelaku pasar ritel dan beralihnya kontrol pasar ke tangan pembeli berorientasi jangka menengah. Stabilitas harga di atas level pivot kritis memberikan fondasi yang kuat bagi kelanjutan tren ekspansi harga menuju level resistensi berikutnya.</p>

<h2>2. Dinamika Aliran Pesanan (Order Flow) &amp; Akumulasi Volume Smart Money</h2>
<p>Fondasi utama dari validasi analisis teknikal modern berstandar Google E-E-A-T adalah konfirmasi likuiditas riil. Pada sesi ini, total volume perdagangan tercatat sebesar <strong>{$volStr} lembar saham</strong>, yang merepresentasikan angka Relative Volume (RVOL) sebesar <strong>{$rvol}x</strong> di atas rerata volume perdagangan 20 hari sebelumnya. Lonjakan volume di atas ambang batas 1.5x umumnya merupakan tanda tangan khas (footprint) dari institusi keuangan atau pelaku pasar bermodal besar (smart money).</p>
<p>Analisis kedalaman order book dan transaksi beli-jual membukukan rasio beli (Buy Volume Ratio) dominan sebesar <strong>{$buyRatio}%</strong>, mengungguli tekanan sisi jual (Sell Volume Ratio) yang berada di angka <strong>{$sellRatio}%</strong>. Penyerapan likuiditas (absorption) yang konsisten pada saat harga bergerak mendekati area support membuktikan adanya minat akumulasi aktif dari investor institusional. Keseimbangan order flow yang condong ke sisi beli ini menghasilkan skor konfidensi kuantitatif scanner sebesar <strong>{$score} dari 100 poin</strong>.</p>
<ul>
    <li><strong>Absorpsi Pasokan Pasar:</strong> Dominasi transaksi beli agresif di pasar reguler menunjukkan bahwa pelaku institusi bersedia membayar pada level penawaran (taking liquidity) demi mengamankan kepemilikan saham.</li>
    <li><strong>Distribusi Volume Sehat:</strong> Lonjakan volume tidak diiringi dengan candle pembalikan berekor panjang di atas, mengindikasikan ketiadaan tekanan distribusi tersembunyi dari pemilik modal besar.</li>
    <li><strong>Rasio Likuiditas Optimal:</strong> Nilai RVOL {$rvol}x memastikan kedalaman pasar yang memadai untuk penempatan modal tanpa menimbulkan slip harga (slippage) yang signifikan.</li>
</ul>

<h2>3. Konvergensi Indikator Teknikal Multi-Lapisan (Multi-Indicator Confluence)</h2>
<p>Suatu setup teknikal memiliki probabilitas keberhasilan yang tinggi apabila didukung oleh keselarasan (confluence) dari beragam indikator independen, meliputi rata-rata pergerakan harga, momentum osilator, dan batas volatilitas statistik:</p>
<ul>
    <li><strong>Struktur Moving Average (MA Hierarchy):</strong> Posisi harga saat ini {$priceFmt} berada di atas MA20 (\${$ma20}), MA50 (\${$ma50}), dan MA200 (\${$ma200}). Penjajaran garis MA dalam urutan MA20 &gt; MA50 &gt; MA200 mengonfirmasi struktur tren bullish yang utuh dan selaras dari jangka pendek hingga jangka panjang.</li>
    <li><strong>Momentum Osilator RSI (14-Periode):</strong> Indikator Relative Strength Index saat ini berada pada level <strong>{$rsi}</strong>. Posisi ini berada di zona momentum ekspansif (antara 55 hingga 65) tanpa menunjukkan sinyal jenuh beli (overbought). Kondisi ini menandakan bahwa tenaga dorong pembeli masih memiliki ruang apresiasi yang luas sebelum mencapai batas saturasi.</li>
    <li><strong>Volatilitas &amp; Band Rentang Dinamis:</strong> Lebar pita volatilitas memperlihatkan pembukaan kembali (expansion phase) setelah fase penyempitan (squeeze), yang secara historis mengawali pergerakan terarah (trending impulse) dengan rentang pergerakan yang lebar.</li>
</ul>

<h2>4. Bedah Anatomi Setup Strategi Spesifik: {$strategyLabel}</h2>
{$strategyDeepDiveId}

<h2>5. Pemetaan Skenario Ganda Pasar: Peluang Katalis vs. Titik Invalidasi</h2>
<p>Sebagai bentuk kepatuhan terhadap standar objektivitas riset pasar modal dan pedoman kualitas Google E-E-A-T, analisis profesional wajib menyajikan dua skenario pergerakan harga tanpa bias sepihak:</p>
<ul>
    <li><strong>Skenario Katalis Bullish (Peluang Utama):</strong> Apabila harga sanggup menembus dan bertahan di atas resistensi terdekat pada level <strong>\${$resistance}</strong> dengan volume harian yang stabil, emiten ini berpeluang besar menguji target kenaikan proyeksi di area <strong>\${$targetPrice}</strong>. Katalis ini didorong oleh percepatan aliran dana dari manajer investasi yang mengejar momentum penguatan.</li>
    <li><strong>Skenario Invalidasi Kritis (Risiko Penurunan):</strong> Sebaliknya, apabila terjadi pelemahan pasar secara makro dan harga terkoreksi menembus ke bawah batas pengaman (stop loss) di level <strong>\${$stopLoss}</strong>, struktur pola bullish resmi dinyatakan batal (invalidated). Dalam skenario ini, pelaku pasar disarankan untuk segera melakukan pembatasan risiko secara disiplin tanpa toleransi kerugian berlebih.</li>
</ul>

<h2>6. Metodologi Manajemen Risiko &amp; Ukuran Posisi Modal (Position Sizing)</h2>
<p>Kunci keberhasilan jangka panjang dalam perdagangan ekuitas bukanlah menebak arah pasar secara sempurna, melainkan menerapkan disiplin alokasi modal dan pengendalian risiko yang ketat. Pelaku pasar institusional selalu menggunakan pendekatan terukur dalam menentukan alokasi portofolio:</p>
<ul>
    <li><strong>Aturan Risiko Maksimal 1% - 2%:</strong> Batasi potensi kerugian maksimal pada transaksi ini agar tidak melebihi 1% hingga 2% dari total modal likuid portofolio Anda.</li>
    <li><strong>Perhitungan Jarak Risiko Terukur:</strong> Jarak dari harga masuk {$priceFmt} menuju level stop loss \${$stopLoss} adalah sekitar {$riskPct}%. Dengan menetapkan batas toleransi ini, Anda terhindar dari risiko drawdown yang merusak modal inti.</li>
    <li><strong>Rasio Risk to Reward ({$rrRatio}):</strong> Dengan potensi keuntungan menuju target \${$targetPrice} yang setara dengan {$rewardPct}%, rasio imbal hasil berbanding risiko tercatat sebesar <strong>{$rrRatio}</strong>. Rasio ini memenuhi kriteria standar institusional yang mensyaratkan ekspektasi imbal hasil lebih besar dari unit risiko yang diambil.</li>
</ul>

<h2>7. Matriks Rencana Trading Terstruktur (Actionable Trading Framework)</h2>
<p>Berdasarkan sintesis data teknikal, kuantifikasi volatilitas, dan titik-titik pivot likuiditas, berikut matriks rencana trading terpadu yang dapat dijadikan panduan eksekusi objektif:</p>
<div class="table-responsive my-3">
    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>Parameter Analitis</th>
                <th>Nilai Kuantitatif</th>
                <th>Keterangan Operasional</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Bias Arah Pasar</strong></td>
                <td><span class="badge bg-success">{$bias}</span></td>
                <td>Kecenderungan arah pasar didominasi oleh akumulasi volume beli.</td>
            </tr>
            <tr>
                <td><strong>Level Harga Terkini</strong></td>
                <td><strong>{$priceFmt}</strong></td>
                <td>Harga penutupan resmi pada sesi {$analysisDate}.</td>
            </tr>
            <tr>
                <td><strong>Area Support Kunci</strong></td>
                <td>\${$support}</td>
                <td>Batas bantalan permintaan tempat pembeli diperkirakan bertahan.</td>
            </tr>
            <tr>
                <td><strong>Level Proteksi (Stop Loss)</strong></td>
                <td>\${$stopLoss}</td>
                <td>Titik invalidasi mutlak untuk menutup posisi demi proteksi modal.</td>
            </tr>
            <tr>
                <td><strong>Target Resistensi Pertama</strong></td>
                <td>\${$resistance}</td>
                <td>Target area untuk merealisasikan keuntungan parsial (take profit 1).</td>
            </tr>
            <tr>
                <td><strong>Target Proyeksi Utama</strong></td>
                <td>\${$targetPrice}</td>
                <td>Sasaran harga ekspansi penuh pola {$strategyLabel} (take profit 2).</td>
            </tr>
            <tr>
                <td><strong>Rasio Risk-to-Reward</strong></td>
                <td>{$rrRatio}</td>
                <td>Kalkulasi imbal hasil terukur memenuhi standar institusional.</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>8. Kepatuhan Regulasi &amp; Penafian Risiko Pasar Modal (Regulatory Disclaimer)</h2>
<blockquote class="p-3 my-4 bg-light border-start border-4 border-primary rounded small text-secondary">
    <strong>Penafian Kepatuhan Pasar Modal (Regulatory Risk Disclosure):</strong><br>
    Artikel riset dan analisis saham ini disusun semata-mata untuk tujuan edukasi, kajian akademis, dan diseminasi data kuantitatif independen. Publikasi ini bukan merupakan penawaran resmi, rekomendasi pasti, ajakan, ataupun saran investasi personal untuk membeli atau menjual instrumen efek tertentu. Perdagangan saham dan aset finansial mengandung risiko volatilitas harga dan potensi kehilangan sebagian atau seluruh modal investasi. Setiap keputusan transaksi, penentuan waktu masuk/keluar pasar, dan alokasi dana sepenuhnya merupakan wewenang dan tanggung jawab pribadi masing-masing investor secara independen. Penulis dan penyedia platform dibebaskan dari segala bentuk tuntutan atas konsekuensi finansial yang timbul dari pemanfaatan informasi ini.
</blockquote>
HTML;

        // ==========================================
        // KONTEN BAHASA INGGRIS BERSTANDAR GOOGLE E-E-A-T (1000+ KATA)
        // ==========================================
        $titleTemplatesEn = [
            "Institutional Flow on {$symbol} ({$timeFrame}): {$strategyLabel} Setup & Rebound Outlook",
            "Technical Deep-Dive: {$symbol} {$strategyLabel} Convergence Targeting \${$targetPrice}",
            "Quantitative Research on {$symbol} ({$timeFrame}): Validating {$strategyLabel} & Critical Pivots",
            "{$symbol} Stock Analysis: {$strategyLabel} Momentum Defends Key Support at \${$support}",
            "{$strategyLabel} Structure in {$symbol} ({$timeFrame}): Risk-Reward & Measured Expansion",
            "Market Microstructure on {$symbol}: {$strategyLabel} Confirms Institutional Smart Money Inflow",
        ];
        $titleEn = $titleTemplatesEn[$hashSeed % count($titleTemplatesEn)];
        $excerptEn = "Comprehensive institutional equity research on {$companyName} ({$symbol}) across the {$timeFrame} horizon. Confirming institutional accumulation with an RVOL of {$rvol}x and {$buyRatio}% buy ratio. Actionable support, resistance, dual-scenario mapping, and disciplined risk-reward metrics.";

        $contentEn = <<<HTML
<h2>1. Executive Summary &amp; Structural Price Action Overview ({$symbol})</h2>
<p>As of the market closing session on <strong>{$analysisDate}</strong>, equity shares of <strong>{$companyName} ({$symbol})</strong> registered an official settlement price of <strong>{$priceFmt}</strong>, capturing a measured session advance of <strong>{$changeStr}</strong>. Utilizing a disciplined multi-timeframe analytical architecture focused on the <strong>{$timeFrame}</strong> timeframe under the <em>{$strategyLabel}</em> framework, the underlying asset demonstrates constructive structural price rehabilitation following a localized consolidation cycle.</p>
<p>From a classical market microstructure perspective, price action has successfully defended pivotal baseline demand zones while establishing a sequential sequence of higher structural lows. This market phenomenon highlights ongoing supply exhaustion among distribution agents, transitioning custodial control toward disciplined intermediate-term market participants. Price stabilization above major pivot clusters provides an empirically grounded launching pad for sustained trend discovery toward primary overhead resistance targets.</p>

<h2>2. Institutional Order Flow &amp; Smart Money Volume Footprint</h2>
<p>The foremost prerequisite for institutional-grade equity analysis adhering to Google E-E-A-T guidelines is empirical volume verification. During the observed session, aggregate transactional volume reached <strong>{$volStr} shares</strong>, establishing a Relative Volume (RVOL) multiplier of <strong>{$rvol}x</strong> relative to the 20-day baseline average. Sustained trading volume exceeding the 1.5x threshold historically constitutes the definitive footprint of institutional capital deployment and smart money accumulation.</p>
<p>Order book flow analytics and transaction categorization reveal a dominant Buyer Volume Ratio of <strong>{$buyRatio}%</strong> against <strong>{$sellRatio}%</strong> on the sell side. Systematic liquidity absorption adjacent to established demand zones verifies deliberate portfolio building by institutional asset allocators. This pronounced buyer-side asymmetry yields an aggregate scanner confidence score of <strong>{$score} out of 100 points</strong>.</p>
<ul>
    <li><strong>Aggressive Liquidity Absorption:</strong> Decisive market order execution on offer sides demonstrates institutional willingness to absorb available liquidity to secure core positioning.</li>
    <li><strong>Wholesome Volume Signature:</strong> Elevated trading volume is unaccompanied by extended upper rejections, verifying the absence of concealed distribution by institutional custodians.</li>
    <li><strong>Optimized Market Liquidity:</strong> The recorded RVOL multiplier of {$rvol}x ensures robust transactional depth, enabling optimal capital deployment with negligible execution slippage.</li>
</ul>

<h2>3. Multi-Layer Technical Indicator Confluence (MA, RSI &amp; Volatility)</h2>
<p>Technical trade setups exhibit the highest statistical probability of sustained follow-through when backed by multi-layered confluence across distinct mathematical indicators:</p>
<ul>
    <li><strong>Moving Average Hierarchy:</strong> Current pricing {$priceFmt} is positioned cleanly above the MA20 (\${$ma20}), MA50 (\${$ma50}), and the institutional baseline MA200 (\${$ma200}). The sequential ordering of MA20 &gt; MA50 &gt; MA200 corroborates an uncompromised multi-tiered bullish trend alignment across short, medium, and long-term horizons.</li>
    <li><strong>Relative Strength Index (RSI 14-Period):</strong> The Relative Strength Index oscillator presently registers at <strong>{$rsi}</strong>. This positioning occupies the expansionary momentum channel (between 55 and 65) while remaining comfortably below overbought thresholds, signifying extensive appreciation runway prior to cyclical exhaustion.</li>
    <li><strong>Volatility Envelopes &amp; Expansion:</strong> Standard volatility bandwidth metrics reflect a decisive expansion phase following acute cyclical contraction, historically forecasting sustained directional trends with widened trading ranges.</li>
</ul>

<h2>4. Quantitative Strategy Breakdown: {$strategyLabel}</h2>
{$strategyDeepDiveEn}

<h2>5. Dual Scenario Mapping: Bullish Catalysts vs. Downside Invalidation</h2>
<p>In accordance with institutional research integrity and Google Search Quality Rater Guidelines (E-E-A-T), professional market commentary requires an objective dual-scenario roadmap detailing both catalytic confirmation and precise failure thresholds:</p>
<ul>
    <li><strong>Primary Bullish Expansion Scenario:</strong> A sustained breakout above immediate horizontal resistance at <strong>\${$resistance}</strong> supported by persistent transactional volume establishes price discovery toward primary projected targets at <strong>\${$targetPrice}</strong>. This catalyst is powered by institutional momentum algorithms and systematic portfolio rebalancing.</li>
    <li><strong>Critical Downside Invalidation Scenario:</strong> Conversely, broad macroeconomic deterioration driving price action beneath the calculated defensive anchor at <strong>\${$stopLoss}</strong> officially invalidates the prevailing technical setup. Market participants are strictly advised to enforce disciplined risk mitigation procedures without emotional hesitation.</li>
</ul>

<h2>6. Capital Preservation &amp; Position Sizing Framework</h2>
<p>Sustainable longevity in equity markets is governed not by directional forecasting accuracy, but by rigorous capital allocation disciplines and mathematical risk containment. Institutional frameworks mandate structured position sizing protocols:</p>
<ul>
    <li><strong>1% to 2% Portfolio Risk Threshold:</strong> Total potential capital impairment on this exposure should not exceed 1% to 2% of liquid portfolio equity under standard risk budgeting parameters.</li>
    <li><strong>Measured Drawdown Cushion:</strong> The calculated distance from current levels {$priceFmt} to the protective stop loss at \${$stopLoss} is {$riskPct}%, insulating core equity from unrecoverable capital drawdowns.</li>
    <li><strong>Risk-to-Reward Ratio ({$rrRatio}):</strong> Against an upside target objective of \${$targetPrice} representing a projected {$rewardPct}% return, the operational Risk-to-Reward ratio stands at <strong>{$rrRatio}</strong>. This satisfies institutional risk hurdle mandates requiring asymmetric upside expectation relative to committed risk capital.</li>
</ul>

<h2>7. Actionable Trading Matrix &amp; Key Pivot Targets</h2>
<p>Synthesizing localized quantitative metrics, volume footprint clustering, and volatility parameters, the following operational matrix provides structured execution benchmarks:</p>
<div class="table-responsive my-3">
    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>Analytical Metric</th>
                <th>Quantitative Value</th>
                <th>Operational Context</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Market Directional Bias</strong></td>
                <td><span class="badge bg-success">{$bias}</span></td>
                <td>Directional momentum underpinned by dominant institutional buying volume.</td>
            </tr>
            <tr>
                <td><strong>Current Settlement Price</strong></td>
                <td><strong>{$priceFmt}</strong></td>
                <td>Official session settlement price as of {$analysisDate}.</td>
            </tr>
            <tr>
                <td><strong>Key Demand Anchor (Support)</strong></td>
                <td>\${$support}</td>
                <td>Primary institutional liquidity cushion where buyers are projected to defend.</td>
            </tr>
            <tr>
                <td><strong>Protective Threshold (Stop Loss)</strong></td>
                <td>\${$stopLoss}</td>
                <td>Absolute structural invalidation point mandating disciplined risk exit.</td>
            </tr>
            <tr>
                <td><strong>Initial Resistance Milestone</strong></td>
                <td>\${$resistance}</td>
                <td>Initial milestone for partial scale-out and profit realization (TP 1).</td>
            </tr>
            <tr>
                <td><strong>Primary Price Objective</strong></td>
                <td>\${$targetPrice}</td>
                <td>Full measured expansion target for the {$strategyLabel} configuration (TP 2).</td>
            </tr>
            <tr>
                <td><strong>Calculated Risk-to-Reward</strong></td>
                <td>{$rrRatio}</td>
                <td>Favorable asymmetric mathematical expectancy meeting institutional standards.</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>8. Regulatory Compliance &amp; Capital Market Risk Disclosure</h2>
<blockquote class="p-3 my-4 bg-light border-start border-4 border-primary rounded small text-secondary">
    <strong>Regulatory &amp; Compliance Disclosure:</strong><br>
    This quantitative research publication is prepared strictly for educational, academic, and market analysis purposes. It does not constitute an offer, formal recommendation, solicitation, or tailored financial advice to acquire or liquidate securities. Equity trading involves substantial market risk, including the possible loss of principal capital. All transaction decisions, entry/exit executions, and capital allocation mandates remain the sole responsibility of individual investors subject to rigorous personal risk tolerances. Authors and platform providers disclaim any liability for financial results arising from the deployment of these materials.
</blockquote>
HTML;

        $tagsArray = [$symbol, $strategyLabel, "Equity Research", "Quantitative", "Trading Plan", "E-E-A-T", "Wall Street"];

        return [
            'title_id' => $titleId,
            'title_en' => $titleEn,
            'excerpt_id' => $excerptId,
            'excerpt_en' => $excerptEn,
            'content_id' => $contentId,
            'content_en' => $contentEn,
            'trading_plan' => [
                'bias' => $bias,
                'support' => $support,
                'resistance' => $resistance,
                'target_price' => $targetPrice,
                'stop_loss' => $stopLoss,
                'risk_reward_ratio' => $rrRatio,
            ],
            'tags' => implode(', ', $tagsArray),
            'meta_title_id' => "{$symbol} {$strategyLabel} ({$timeFrame}) - Analisis & Trading Plan",
            'meta_title_en' => "{$symbol} {$strategyLabel} ({$timeFrame}) - Analysis & Trading Plan",
            'meta_desc_id' => "Riset saham {$symbol} ({$timeFrame}) pola {$strategyLabel}. Support \${$support}, TP \${$targetPrice}, SL \${$stopLoss}. Evaluasi risiko kuantitatif E-E-A-T.",
            'meta_desc_en' => "Institutional research of {$symbol} ({$timeFrame}) with {$strategyLabel}. Support \${$support}, Target \${$targetPrice}, SL \${$stopLoss}. Dual-scenario E-E-A-T framework.",
        ];
    }
}
