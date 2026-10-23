<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\Inflector;

/**
 * Model ActiveRecord untuk tabel {{%blog_post}}.
 * Mewakili artikel analisis saham AI dwibahasa (Indonesia & Inggris).
 *
 * @property int $id
 * @property int|null $stock_id
 * @property string $symbol
 * @property string $time_frame
 * @property string $strategy_name
 * @property string $category
 * @property string $analysis_date
 * @property string $slug_id
 * @property string $slug_en
 * @property string $title_id
 * @property string $title_en
 * @property string|null $excerpt_id
 * @property string|null $excerpt_en
 * @property string $content_id
 * @property string $content_en
 * @property string|null $featured_image
 * @property string|array|null $trading_plan
 * @property string|null $tags
 * @property string|null $meta_title_id
 * @property string|null $meta_title_en
 * @property string|null $meta_desc_id
 * @property string|null $meta_desc_en
 * @property int $author_id
 * @property string $status
 * @property int $views_count
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Stock|null $stock
 * @property User $author
 */
class BlogPost extends ActiveRecord
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_PUBLISHED => 'Published',
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    public static function tableName(): string
    {
        return '{{%blog_post}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            [['symbol', 'analysis_date', 'title_id', 'title_en', 'content_id', 'content_en', 'author_id'], 'required'],
            [['stock_id', 'author_id', 'views_count'], 'integer'],
            [['symbol'], 'string', 'max' => 16],
            [['symbol'], 'filter', 'filter' => 'strtoupper'],
            [['time_frame'], 'string', 'max' => 16],
            [['time_frame'], 'default', 'value' => '1D'],
            [['strategy_name'], 'string', 'max' => 64],
            [['strategy_name'], 'default', 'value' => 'general'],
            [['category'], 'string', 'max' => 64],
            [['analysis_date'], 'date', 'format' => 'php:Y-m-d'],
            [['slug_id', 'slug_en', 'title_id', 'title_en', 'featured_image', 'meta_title_id', 'meta_title_en'], 'string', 'max' => 255],
            [['slug_id'], 'unique'],
            [['slug_en'], 'unique'],
            [['meta_desc_id', 'meta_desc_en'], 'string', 'max' => 320],
            [['tags'], 'string', 'max' => 255],
            [['status'], 'in', 'range' => array_keys(self::STATUSES)],
            [['status'], 'default', 'value' => self::STATUS_PUBLISHED],
            [['excerpt_id', 'excerpt_en', 'content_id', 'content_en'], 'string'],
            [['trading_plan'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'stock_id' => 'Emiten Saham',
            'symbol' => 'Simbol Saham',
            'time_frame' => 'Timeframe',
            'strategy_name' => 'Kode Strategi',
            'category' => 'Kategori Artikel',
            'analysis_date' => 'Tanggal Analisis',
            'title_id' => 'Judul (Bahasa Indonesia)',
            'title_en' => 'Title (English)',
            'excerpt_id' => 'Ringkasan (ID)',
            'excerpt_en' => 'Excerpt (EN)',
            'content_id' => 'Konten Artikel (ID)',
            'content_en' => 'Article Content (EN)',
            'featured_image' => 'Gambar Unggulan (WebP)',
            'trading_plan' => 'Rencana Trading',
            'tags' => 'Tag (Koma terpisah)',
            'status' => 'Status Publikasi',
            'views_count' => 'Jumlah Dilihat',
            'created_at' => 'Waktu Terbit',
            'updated_at' => 'Pembaruan Terakhir',
        ];
    }

    /**
     * Konversi nama strategi teknikal menjadi Nama Kategori ramah pembaca
     */
    public static function strategyToCategory(string $strategy): string
    {
        $map = [
            'rsi-double-bottom' => 'RSI Double Bottom',
            'double-bottom' => 'Double Bottom',
            'triple-bottom' => 'Triple Bottom',
            'moving-average' => 'Moving Average',
            'bollinger-band' => 'Bollinger Bands',
            'bollinger-bands' => 'Bollinger Bands',
            'bullish-pattern' => 'Bullish Pattern',
            'bullish-patterns' => 'Bullish Pattern',
            'volume-breakout' => 'Volume Breakout',
            'fibonacci' => 'Fibonacci Retracement',
            'general' => 'Analisis Saham',
        ];

        $key = strtolower(trim($strategy));
        if (isset($map[$key])) {
            return $map[$key];
        }

        return ucwords(str_replace(['-', '_'], ' ', $key));
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // Auto-assign category dari strategy_name jika category masih kosong
        if (empty($this->category)) {
            $this->category = static::strategyToCategory((string) $this->strategy_name);
        }

        // Auto-generate slugs jika belum ada
        if (empty($this->slug_id)) {
            $this->slug_id = static::generateUniqueSlug($this->title_id, 'id', $this->id);
        }
        if (empty($this->slug_en)) {
            $this->slug_en = static::generateUniqueSlug($this->title_en, 'en', $this->id);
        }

        // Pastikan trading_plan disimpan sebagai string JSON jika berupa array
        if (is_array($this->trading_plan)) {
            $this->trading_plan = json_encode($this->trading_plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return true;
    }

    /**
     * Relasi ke emiten saham
     */
    public function getStock(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }

    /**
     * Relasi ke penulis (User)
     */
    public function getAuthor(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'author_id']);
    }

    /**
     * Pengecekan Granular / Mendetail: symbol + time_frame + strategy_name + analysis_date
     * Sesuai Dokumen Perencanaan Bagian 1.3 & 2.
     */
    public static function findExisting(string $symbol, string $timeFrame, string $strategyName, string $analysisDate): ?self
    {
        return static::find()
            ->where([
                'symbol' => strtoupper(trim($symbol)),
                'time_frame' => strtoupper(trim($timeFrame)),
                'strategy_name' => strtolower(trim($strategyName)),
                'analysis_date' => $analysisDate,
            ])
            ->one();
    }

    /**
     * Generate slug unik ramah SEO
     */
    public static function generateUniqueSlug(string $title, string $lang = 'id', ?int $excludeId = null): string
    {
        $base = Inflector::slug($title);
        if (empty($base)) {
            $base = 'analisis-saham-' . strtolower($lang);
        }

        $slugColumn = ($lang === 'en') ? 'slug_en' : 'slug_id';
        $slug = $base;
        $counter = 2;

        while (true) {
            $query = static::find()->where([$slugColumn => $slug]);
            if ($excludeId !== null) {
                $query->andWhere(['!=', 'id', $excludeId]);
            }
            if (!$query->exists()) {
                break;
            }
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Dapatkan array Trading Plan yang telah didecode
     */
    public function getTradingPlanArray(): array
    {
        if (empty($this->trading_plan)) {
            return [];
        }
        if (is_array($this->trading_plan)) {
            return $this->trading_plan;
        }
        $decoded = json_decode((string) $this->trading_plan, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Set Trading Plan
     */
    public function setTradingPlanArray(array $plan): void
    {
        $this->trading_plan = json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Dapatkan daftar tag dalam bentuk array
     */
    public function getTagsArray(): array
    {
        if (empty($this->tags)) {
            return [];
        }
        $parts = explode(',', $this->tags);
        return array_values(array_filter(array_map('trim', $parts)));
    }

    /**
     * Hitung perkiraan waktu baca artikel (dalam menit)
     */
    public function getReadingTime(string $lang = 'id'): int
    {
        $content = ($lang === 'en') ? $this->content_en : $this->content_id;
        $wordCount = str_word_count(strip_tags((string) $content));
        return max(1, (int) ceil($wordCount / 200));
    }

    /**
     * Tambah counter views secara atomik
     */
    public function incrementViews(): void
    {
        try {
            $this->updateCounters(['views_count' => 1]);
        } catch (\Throwable $e) {
            // Abaikan kesalahan non-kritis
        }
    }

    /**
     * Label representatif strategi teknikal
     */
    public function getStrategyLabel(): string
    {
        return match (strtolower($this->strategy_name)) {
            'double-bottom' => 'Double Bottom',
            'triple-bottom' => 'Triple Bottom',
            'rsi-double-bottom' => 'RSI Double Bottom',
            'moving-average' => 'Moving Average Confluence',
            'bollinger-bands' => 'Bollinger Bands Breakout',
            'bullish-pattern' => 'Bullish Pattern Continuation',
            'fibonacci' => 'Fibonacci Retracement',
            'volume-breakout' => 'Volume Breakout Analyzer',
            default => 'General Technical Analysis',
        };
    }

    /**
     * Dapatkan bias trading (BULLISH, BEARISH, NEUTRAL)
     */
    public function getBias(): string
    {
        $plan = $this->getTradingPlanArray();
        $bias = strtoupper((string) ($plan['bias'] ?? 'BULLISH'));
        return in_array($bias, ['BULLISH', 'BEARISH', 'NEUTRAL'], true) ? $bias : 'BULLISH';
    }
}
