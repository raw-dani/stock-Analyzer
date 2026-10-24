<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\BlogPost;
use app\models\SiteSetting;
use app\models\Stock;
use app\models\User;
use app\services\BlogImageService;
use app\services\OpenAiBlogService;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller untuk Fitur Blog Info Saham AI Bilingual,
 * Publikasi Analisis Cepat, Manajemen Admin, dan Integrasi SEO/Webmaster.
 */
class BlogController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['admin', 'create', 'update', 'delete', 'settings', 'generate-from-stock', 'check-existing', 'test-ai-connection', 'fetch-ai-models'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            /** @var User|null $user */
                            $user = Yii::$app->user->identity;
                            return $user !== null && $user->isAdmin();
                        },
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'generate-from-stock' => ['POST'],
                    'test-ai-connection' => ['POST'],
                    'fetch-ai-models' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Halaman Utama Blog Publik (Grid Kartu Modern, Filter, & Pencarian)
     */
    public function actionIndex(): string
    {
        $req = Yii::$app->request;
        $search = trim((string) $req->get('q', ''));
        $strategy = trim((string) $req->get('strategy', ''));
        $category = trim((string) $req->get('category', ''));
        $tag = trim((string) $req->get('tag', ''));

        $query = BlogPost::find()->where(['status' => BlogPost::STATUS_PUBLISHED]);

        if (!empty($search)) {
            $query->andWhere([
                'or',
                ['like', 'symbol', strtoupper($search)],
                ['like', 'title_id', $search],
                ['like', 'title_en', $search],
                ['like', 'category', $search],
                ['like', 'tags', $search],
            ]);
        }

        if (!empty($category) && $category !== 'all') {
            $query->andWhere(['category' => $category]);
        }

        if (!empty($strategy) && $strategy !== 'all') {
            $query->andWhere(['strategy_name' => strtolower($strategy)]);
        }

        if (!empty($tag)) {
            $query->andWhere(['like', 'tags', $tag]);
        }

        // Ambil 1 artikel unggulan (terbaru) jika halaman 1 dan tanpa filter
        $featuredPost = null;
        if (empty($search) && empty($strategy) && empty($category) && empty($tag) && (int) $req->get('page', 1) <= 1) {
            $featuredPost = BlogPost::find()
                ->where(['status' => BlogPost::STATUS_PUBLISHED])
                ->orderBy(['created_at' => SORT_DESC])
                ->one();

            if ($featuredPost !== null) {
                $query->andWhere(['!=', 'id', $featuredPost->id]);
            }
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query->orderBy(['created_at' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 9,
                'defaultPageSize' => 9,
            ],
        ]);

        // Ambil daftar kategori aktif untuk filter bar
        $categories = BlogPost::find()
            ->select(['category'])
            ->distinct()
            ->where(['status' => BlogPost::STATUS_PUBLISHED])
            ->andWhere(['not', ['category' => null]])
            ->andWhere(['!=', 'category', ''])
            ->column();

        // Ambil daftar strategi aktif untuk filter bar
        $strategies = BlogPost::find()
            ->select(['strategy_name'])
            ->distinct()
            ->where(['status' => BlogPost::STATUS_PUBLISHED])
            ->column();

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'featuredPost' => $featuredPost,
            'search' => $search,
            'selectedCategory' => $category,
            'selectedStrategy' => $strategy,
            'selectedTag' => $tag,
            'categories' => $categories,
            'strategies' => $strategies,
        ]);
    }

    /**
     * Detail Artikel Blog Publik (Bilingual Switcher, Rich Schema JSON-LD, SEO Meta)
     */
    public function actionView(?string $slug = null, ?int $id = null, string $lang = 'id'): string
    {
        $post = null;
        if (!empty($slug)) {
            $post = BlogPost::find()
                ->where(['slug_id' => $slug])
                ->orWhere(['slug_en' => $slug])
                ->one();
        } elseif (!empty($id)) {
            $post = BlogPost::findOne($id);
        }

        if ($post === null) {
            throw new NotFoundHttpException('Artikel blog tidak ditemukan.');
        }

        // Jika artikel masih draft, hanya admin yang boleh melihat preview
        $isAdmin = Yii::$app->user && !Yii::$app->user->isGuest && Yii::$app->user->identity->isAdmin();
        if ($post->status !== BlogPost::STATUS_PUBLISHED && !$isAdmin) {
            throw new NotFoundHttpException('Artikel blog sedang dalam peninjauan draft.');
        }

        // Tambah counter pembaca
        $post->incrementViews();

        // Tentukan bahasa aktif awal berdasarkan slug yang dibuka
        if (!empty($slug) && $slug === $post->slug_en) {
            $lang = 'en';
        }

        // Ambil rekomendasi artikel terkait
        $relatedPosts = BlogPost::find()
            ->where(['status' => BlogPost::STATUS_PUBLISHED])
            ->andWhere(['!=', 'id', $post->id])
            ->andWhere([
                'or',
                ['symbol' => $post->symbol],
                ['strategy_name' => $post->strategy_name],
            ])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(3)
            ->all();

        return $this->render('view', [
            'post' => $post,
            'activeLang' => in_array($lang, ['id', 'en'], true) ? $lang : 'id',
            'relatedPosts' => $relatedPosts,
        ]);
    }

    /**
     * XML Sitemap Resmi (/blog/sitemap.xml) untuk Googlebot & Bingbot
     */
    public function actionSitemap(): Response
    {
        $posts = BlogPost::find()
            ->where(['status' => BlogPost::STATUS_PUBLISHED])
            ->orderBy(['updated_at' => SORT_DESC])
            ->all();

        $baseUrl = '';
        try {
            $baseUrl = Yii::$app->request->hostInfo;
        } catch (\Throwable $e) {
            $baseUrl = 'http://127.0.0.1:8089';
        }
        if (empty($baseUrl)) {
            $baseUrl = 'http://127.0.0.1:8089';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // URL Indeks Blog
        $blogIndexUrl = $baseUrl . Url::to(['/blog/index']);
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($blogIndexUrl, ENT_XML1) . "</loc>\n";
        $xml .= "    <changefreq>daily</changefreq>\n";
        $xml .= "    <priority>0.9</priority>\n";
        $xml .= "  </url>\n";

        /** @var BlogPost $post */
        foreach ($posts as $post) {
            $urlId = $baseUrl . Url::to(['/blog/view', 'slug' => $post->slug_id]);
            $urlEn = $baseUrl . Url::to(['/blog/view', 'slug' => $post->slug_en]);
            $lastmod = date('c', $post->updated_at);

            // Entry Bahasa Indonesia
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($urlId, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>weekly</changefreq>\n";
            $xml .= "    <priority>0.8</priority>\n";
            $xml .= '    <xhtml:link rel="alternate" hreflang="id" href="' . htmlspecialchars($urlId, ENT_XML1) . '" />' . "\n";
            $xml .= '    <xhtml:link rel="alternate" hreflang="en" href="' . htmlspecialchars($urlEn, ENT_XML1) . '" />' . "\n";
            $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($urlId, ENT_XML1) . '" />' . "\n";
            $xml .= "  </url>\n";

            // Entry Bahasa Inggris
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($urlEn, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>weekly</changefreq>\n";
            $xml .= "    <priority>0.8</priority>\n";
            $xml .= '    <xhtml:link rel="alternate" hreflang="id" href="' . htmlspecialchars($urlId, ENT_XML1) . '" />' . "\n";
            $xml .= '    <xhtml:link rel="alternate" hreflang="en" href="' . htmlspecialchars($urlEn, ENT_XML1) . '" />' . "\n";
            $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($urlId, ENT_XML1) . '" />' . "\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        // Tulis sitemap.xml di webroot
        try {
            $webroot = (is_string(Yii::getAlias('@webroot', false)) && file_exists(Yii::getAlias('@webroot', false) . '/index.php'))
                ? Yii::getAlias('@webroot')
                : (Yii::getAlias('@app') . '/web');

            $sitemapFile = $webroot . '/sitemap.xml';
            @file_put_contents($sitemapFile, $xml);
        } catch (\Throwable $e) {
            Yii::warning("Sitemap write error: " . $e->getMessage());
        }

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->data = $xml;
        return $response;
    }

    /**
     * Dashboard Manajemen Admin Blog (/blog/admin)
     */
    public function actionAdmin(): string
    {
        $search = trim((string) Yii::$app->request->get('q', ''));
        $status = trim((string) Yii::$app->request->get('status', ''));
        $category = trim((string) Yii::$app->request->get('category', ''));

        $query = BlogPost::find()->with(['stock', 'author']);

        if (!empty($search)) {
            $query->andWhere([
                'or',
                ['like', 'symbol', strtoupper($search)],
                ['like', 'title_id', $search],
                ['like', 'title_en', $search],
                ['like', 'category', $search],
            ]);
        }

        if (!empty($status) && in_array($status, array_keys(BlogPost::STATUSES), true)) {
            $query->andWhere(['status' => $status]);
        }

        if (!empty($category)) {
            $query->andWhere(['category' => $category]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query->orderBy(['created_at' => SORT_DESC]),
            'pagination' => ['pageSize' => 15],
        ]);

        $categories = BlogPost::find()
            ->select(['category'])
            ->distinct()
            ->where(['not', ['category' => null]])
            ->andWhere(['!=', 'category', ''])
            ->column();

        $stats = [
            'total' => BlogPost::find()->count(),
            'published' => BlogPost::find()->where(['status' => BlogPost::STATUS_PUBLISHED])->count(),
            'draft' => BlogPost::find()->where(['status' => BlogPost::STATUS_DRAFT])->count(),
            'total_views' => BlogPost::find()->sum('views_count') ?? 0,
        ];

        return $this->render('admin', [
            'dataProvider' => $dataProvider,
            'stats' => $stats,
            'search' => $search,
            'selectedStatus' => $status,
            'selectedCategory' => $category,
            'categories' => $categories,
        ]);
    }

    /**
     * Form Buat Artikel Manual Baru
     */
    public function actionCreate(): Response|string
    {
        $model = new BlogPost();
        $model->author_id = (int) Yii::$app->user->id;
        $model->status = BlogPost::STATUS_PUBLISHED;
        $model->analysis_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->save()) {
                Yii::$app->session->setFlash('success', "Artikel {$model->symbol} berhasil diterbitkan.");
                return $this->redirect(['view', 'slug' => $model->slug_id]);
            }
        }

        return $this->render('form', [
            'model' => $model,
            'isNew' => true,
        ]);
    }

    /**
     * Form Edit Artikel Blog
     */
    public function actionUpdate(int $id): Response|string
    {
        $model = BlogPost::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Artikel tidak ditemukan.');
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->save()) {
                Yii::$app->session->setFlash('success', "Artikel {$model->symbol} berhasil diperbarui.");
                return $this->redirect(['view', 'slug' => $model->slug_id]);
            }
        }

        return $this->render('form', [
            'model' => $model,
            'isNew' => false,
        ]);
    }

    /**
     * Hapus Artikel Blog
     */
    public function actionDelete(int $id): Response
    {
        $model = BlogPost::findOne($id);
        if ($model !== null) {
            // Hapus file gambar screenshot jika ada
            if (!empty($model->featured_image)) {
                $filePath = Yii::getAlias('@webroot') . $model->featured_image;
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            $symbol = $model->symbol;
            $model->delete();
            Yii::$app->session->setFlash('success', "Artikel {$symbol} berhasil dihapus.");
        }

        return $this->redirect(['admin']);
    }

    /**
     * Halaman Pengaturan Webmaster, SEO & Tracking Analytics (/blog/admin/settings)
     */
    public function actionSettings(): Response|string
    {
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post('Setting', []);

            // Simpan pengaturan SEO & Webmaster
            $fields = [
                'google_site_verification' => ['seo', 'Google Search Console Verification Token'],
                'bing_site_verification' => ['seo', 'Bing Webmaster Tools Verification Token'],
                'default_meta_keywords' => ['seo', 'Default SEO Meta Keywords'],
                'ga4_measurement_id' => ['analytics', 'Google Analytics 4 Measurement ID'],
                'gtm_container_id' => ['analytics', 'Google Tag Manager Container ID'],
                'custom_head_scripts' => ['analytics', 'Custom Head Scripts'],
                'custom_body_scripts' => ['analytics', 'Custom Body Scripts'],
                'ai_generation_mode' => ['ai', 'Mode Pembuatan Artikel (zero_cost / ai_optimized)'],
                'ai_provider' => ['ai', 'Penyedia Layanan AI (groq / openai / openrouter / ollama / custom)'],
                'groq_api_key' => ['ai', 'Groq Cloud API Key (gsk_...)'],
                'groq_model' => ['ai', 'Model Groq (llama-3.3-70b-versatile / etc)'],
                'openai_base_url' => ['ai', 'Custom API Base URL (OpenAI / Groq Free / OpenRouter)'],
                'openai_max_tokens' => ['ai', 'Max Output Completion Tokens (800-4000)'],
                'openai_cache_enabled' => ['ai', 'Aktifkan Caching Respon AI (Hemat Token)'],
                'openai_api_key' => ['ai', 'OpenAI API Secret Key'],
                'openai_model' => ['ai', 'OpenAI Model (gpt-6-luna / gpt-5.6-luna / gpt-5.4-nano / etc)'],
            ];

            foreach ($fields as $key => [$cat, $desc]) {
                $val = isset($post[$key]) ? trim((string) $post[$key]) : '';
                SiteSetting::set($key, $val, $cat, $desc);
            }

            SiteSetting::flushCache();
            Yii::$app->session->setFlash('success', 'Pengaturan SEO, Webmaster, & AI Provider Optimizer berhasil disimpan.');
            return $this->redirect(['settings']);
        }

        $currentSettings = [
            'google_site_verification' => SiteSetting::get('google_site_verification', ''),
            'bing_site_verification' => SiteSetting::get('bing_site_verification', ''),
            'default_meta_keywords' => SiteSetting::get('default_meta_keywords', ''),
            'ga4_measurement_id' => SiteSetting::get('ga4_measurement_id', ''),
            'gtm_container_id' => SiteSetting::get('gtm_container_id', ''),
            'custom_head_scripts' => SiteSetting::get('custom_head_scripts', ''),
            'custom_body_scripts' => SiteSetting::get('custom_body_scripts', ''),
            'ai_generation_mode' => SiteSetting::get('ai_generation_mode', OpenAiBlogService::getGenerationMode()),
            'ai_provider' => SiteSetting::get('ai_provider', OpenAiBlogService::getProvider()),
            'groq_api_key' => SiteSetting::get('groq_api_key', ''),
            'groq_model' => SiteSetting::get('groq_model', OpenAiBlogService::DEFAULT_GROQ_MODEL),
            'openai_base_url' => SiteSetting::get('openai_base_url', OpenAiBlogService::getBaseUrl()),
            'openai_max_tokens' => SiteSetting::get('openai_max_tokens', (string) OpenAiBlogService::DEFAULT_MAX_TOKENS),
            'openai_cache_enabled' => SiteSetting::get('openai_cache_enabled', '1'),
            'openai_api_key' => SiteSetting::get('openai_api_key', ''),
            'openai_model' => SiteSetting::get('openai_model', OpenAiBlogService::DEFAULT_MODEL),
        ];

        return $this->render('settings', [
            'settings' => $currentSettings,
        ]);
    }

    /**
     * Endpoint AJAX Uji Koneksi API AI (Groq / OpenAI / Custom)
     */
    public function actionTestAiConnection(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $req = Yii::$app->request;

        $provider = (string) $req->post('provider', 'groq');
        $apiKey = trim((string) $req->post('api_key', ''));
        $baseUrl = trim((string) $req->post('base_url', ''));
        $model = trim((string) $req->post('model', ''));

        if (empty($baseUrl)) {
            $baseUrl = ($provider === 'groq') ? OpenAiBlogService::GROQ_BASE_URL : OpenAiBlogService::DEFAULT_BASE_URL;
        }

        if (empty($apiKey) && !str_contains($baseUrl, 'localhost') && !str_contains($baseUrl, '127.0.0.1')) {
            return $this->asJson([
                'success' => false,
                'message' => 'API Key wajib diisi untuk melakukan pengujian koneksi.',
            ]);
        }

        if (empty($model)) {
            $model = ($provider === 'groq') ? OpenAiBlogService::DEFAULT_GROQ_MODEL : OpenAiBlogService::DEFAULT_MODEL;
        }

        $service = new OpenAiBlogService();
        $result = $service->testConnection($provider, $apiKey, $baseUrl, $model);
        return $this->asJson($result);
    }

    /**
     * Ambil daftar model yang aktif secara dinamis dari API Provider (AJAX)
     */
    public function actionFetchAiModels(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $req = Yii::$app->request;
        $provider = trim((string) $req->post('provider', 'groq'));
        $apiKey = trim((string) $req->post('api_key', ''));
        $baseUrl = trim((string) $req->post('base_url', ''));

        if (empty($apiKey)) {
            $apiKey = OpenAiBlogService::getApiKey() ?? '';
        }

        if (empty($apiKey) && !str_contains($baseUrl, 'localhost') && !str_contains($baseUrl, '127.0.0.1')) {
            return $this->asJson([
                'success' => false,
                'message' => 'Silakan masukkan API Key terlebih dahulu untuk memuat daftar model yang aktif.',
                'models' => [],
            ]);
        }

        if (empty($baseUrl)) {
            $baseUrl = ($provider === OpenAiBlogService::PROVIDER_GROQ) ? OpenAiBlogService::GROQ_BASE_URL : OpenAiBlogService::DEFAULT_BASE_URL;
        }

        $service = new OpenAiBlogService();
        $result = $service->fetchAvailableModels($provider, $apiKey, $baseUrl);
        return $this->asJson($result);
    }

    /**
     * Bersihkan Cache Respon AI Generator
     */
    public function actionClearAiCache(): Response
    {
        if (Yii::$app->has('cache')) {
            Yii::$app->cache->flush();
        }
        Yii::$app->session->setFlash('success', 'Cache respon AI dan token berhasil dibersihkan.');
        return $this->redirect(['settings']);
    }

    /**
     * Endpoint Cek Granular Duplikasi (AJAX):
     * Cek apakah symbol + time_frame + strategy_name + analysis_date sudah pernah diposting.
     */
    public function actionCheckExisting(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $req = Yii::$app->request;
        $symbol = strtoupper(trim((string) $req->get('symbol', $req->post('symbol', ''))));
        $timeFrame = strtoupper(trim((string) $req->get('time_frame', $req->post('time_frame', '1D'))));
        $strategyName = strtolower(trim((string) $req->get('strategy_name', $req->post('strategy_name', 'general'))));
        $analysisDate = trim((string) $req->get('analysis_date', $req->post('analysis_date', date('Y-m-d'))));

        if (empty($symbol)) {
            return $this->asJson(['exists' => false]);
        }

        $existing = BlogPost::findExisting($symbol, $timeFrame, $strategyName, $analysisDate);

        if ($existing !== null) {
            return $this->asJson([
                'exists' => true,
                'id' => $existing->id,
                'title' => $existing->title_id,
                'url' => Url::to(['/blog/view', 'slug' => $existing->slug_id]),
                'editUrl' => Url::to(['/blog/update', 'id' => $existing->id]),
                'time_frame' => $existing->time_frame,
                'strategy_name' => $existing->strategy_name,
                'analysis_date' => $existing->analysis_date,
            ]);
        }

        return $this->asJson(['exists' => false]);
    }

    /**
     * Endpoint Generator Cepat dari Detail Analisa Saham (AJAX):
     * Menerima screenshot base64 + metrik teknikal, memanggil OpenAI, dan menyimpan postingan.
     */
    public function actionGenerateFromStock(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $req = Yii::$app->request;
        $symbol = strtoupper(trim((string) $req->post('symbol', '')));
        $timeFrame = strtoupper(trim((string) $req->post('time_frame', '1D')));
        $strategyName = strtolower(trim((string) $req->post('strategy_name', 'general')));
        $analysisDate = trim((string) $req->post('analysis_date', date('Y-m-d')));
        $stockName = trim((string) $req->post('stock_name', ''));
        $rawMetrics = $req->post('metrics');
        $screenshotBase64 = (string) $req->post('screenshot', '');
        $overwrite = (bool) $req->post('overwrite', false);

        if (empty($symbol)) {
            return $this->asJson(['success' => false, 'message' => 'Simbol saham tidak boleh kosong.']);
        }

        // Pengecekan Granular duplikasi
        $existing = BlogPost::findExisting($symbol, $timeFrame, $strategyName, $analysisDate);
        if ($existing !== null && !$overwrite) {
            return $this->asJson([
                'success' => true,
                'already_exists' => true,
                'id' => $existing->id,
                'url' => Url::to(['/blog/view', 'slug' => $existing->slug_id]),
                'editUrl' => Url::to(['/blog/update', 'id' => $existing->id]),
                'message' => "Analisis {$symbol} [{$timeFrame}] [{$strategyName}] sudah pernah diposting.",
            ]);
        }

        // Parse metrik teknikal
        $metrics = [];
        if (is_array($rawMetrics)) {
            $metrics = $rawMetrics;
        } elseif (is_string($rawMetrics)) {
            $decoded = json_decode($rawMetrics, true);
            $metrics = is_array($decoded) ? $decoded : [];
        }

        // 1. Simpan screenshot chart WebP
        $imageService = new BlogImageService();
        $featuredImage = null;
        if (!empty($screenshotBase64)) {
            $featuredImage = $imageService->saveBase64Screenshot($screenshotBase64, $symbol, $timeFrame, $strategyName);
        }

        // Hubungkan stock_id jika simbol terdaftar
        $stock = Stock::findOne(['symbol' => $symbol]);
        $stockId = $stock?->id;
        if (empty($stockName) && $stock !== null) {
            $stockName = $stock->name;
        }

        // 2. Generate konten dwibahasa via OpenAI Service (Mendukung Zero-Cost, Kompresi Token & Cache)
        $aiService = new OpenAiBlogService();
        $generated = $aiService->generateAnalysisPost(
            $symbol,
            $timeFrame,
            $strategyName,
            $analysisDate,
            $metrics,
            $stockName,
            $overwrite
        );

        // 3. Simpan entitas BlogPost
        $post = ($existing !== null && $overwrite) ? $existing : new BlogPost();
        $post->stock_id = $stockId;
        $post->symbol = $symbol;
        $post->time_frame = $timeFrame;
        $post->strategy_name = $strategyName;

        // Kategori otomatis dari nama strategi halaman detail saham yang digenerate
        $postCategory = trim((string) $req->post('category', ''));
        if (empty($postCategory)) {
            $postCategory = BlogPost::strategyToCategory($strategyName);
        }
        $post->category = $postCategory;

        $post->analysis_date = $analysisDate;
        $post->author_id = (int) Yii::$app->user->id;
        $post->status = BlogPost::STATUS_PUBLISHED;

        $post->title_id = $generated['title_id'] ?? "Analisis Saham {$symbol} ({$timeFrame})";
        $post->title_en = $generated['title_en'] ?? "{$symbol} Stock Analysis ({$timeFrame})";
        $post->excerpt_id = $generated['excerpt_id'] ?? null;
        $post->excerpt_en = $generated['excerpt_en'] ?? null;
        $post->content_id = $generated['content_id'] ?? '<p>Analisis saham terbit.</p>';
        $post->content_en = $generated['content_en'] ?? '<p>Stock analysis published.</p>';

        if (!empty($featuredImage)) {
            $post->featured_image = $featuredImage;
        }

        if (!empty($generated['trading_plan'])) {
            $post->setTradingPlanArray($generated['trading_plan']);
        }

        if (!empty($generated['tags'])) {
            $post->tags = is_array($generated['tags']) ? implode(', ', $generated['tags']) : (string) $generated['tags'];
        }

        $post->meta_title_id = $generated['meta_title_id'] ?? null;
        $post->meta_title_en = $generated['meta_title_en'] ?? null;
        $post->meta_desc_id = $generated['meta_desc_id'] ?? null;
        $post->meta_desc_en = $generated['meta_desc_en'] ?? null;

        if (!$post->save()) {
            Yii::error("Gagal menyimpan BlogPost: " . json_encode($post->errors), __METHOD__);
            return $this->asJson([
                'success' => false,
                'message' => 'Gagal menyimpan artikel blog: ' . implode(', ', $post->getFirstErrors()),
            ]);
        }

        $engine = $generated['_engine'] ?? 'zero_cost';
        $engineModel = $generated['_model'] ?? 'local_template';
        $fallbackReason = $generated['_fallback_reason'] ?? '';

        $message = ($engine === 'ai')
            ? "Artikel {$symbol} [{$timeFrame}] berhasil digenerate menggunakan AI ({$engineModel}) - 100% Unik!"
            : "Artikel {$symbol} [{$timeFrame}] dibuat dengan Template Kuantitatif Lokal (Zero-Cost)" . (!empty($fallbackReason) ? " (Penyebab AI fallback: {$fallbackReason})" : "") . ".";

        return $this->asJson([
            'success' => true,
            'id' => $post->id,
            'slug_id' => $post->slug_id,
            'slug_en' => $post->slug_en,
            'title' => $post->title_id,
            'url' => Url::to(['/blog/view', 'slug' => $post->slug_id]),
            'editUrl' => Url::to(['/blog/update', 'id' => $post->id]),
            'engine' => $engine,
            'model' => $engineModel,
            'fallback_reason' => $fallbackReason,
            'message' => $message,
        ]);
    }
}
