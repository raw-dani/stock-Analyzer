<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Model Portofolio Posisi Saham.
 *
 * @property int $id
 * @property int $user_id
 * @property int $stock_id
 * @property float $buy_price
 * @property float $shares
 * @property float $target_profit_pct
 * @property string|null $buy_date
 * @property string|null $notes
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Stock $stock
 * @property-read User $user
 */
class PortfolioPosition extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%portfolio_position}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['stock_id', 'buy_price', 'shares'], 'required'],
            [['user_id', 'stock_id'], 'integer'],
            [['buy_price', 'shares'], 'number', 'min' => 0.0001],
            [['target_profit_pct'], 'number', 'min' => 0.1, 'max' => 1000],
            [['target_profit_pct'], 'default', 'value' => 3.00],
            [['user_id'], 'default', 'value' => function () {
                return \Yii::$app->user->id ?? 100;
            }],
            [['buy_date'], 'string', 'max' => 10],
            [['notes'], 'string', 'max' => 1000],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'stock_id' => 'Simbol Saham',
            'buy_price' => 'Harga Beli (Avg Buy Price)',
            'shares' => 'Jumlah Lembar (Shares)',
            'target_profit_pct' => 'Target Profit (%)',
            'buy_date' => 'Tanggal Pembelian',
            'notes' => 'Catatan / Alasan Entry',
        ];
    }

    public function getStock(): ActiveQuery
    {
        return $this->hasOne(Stock::class, ['id' => 'stock_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getHistories(): ActiveQuery
    {
        return $this->hasMany(PortfolioHistory::class, ['position_id' => 'id'])->orderBy(['created_at' => SORT_DESC]);
    }

    /**
     * Mengambil daftar riwayat dengan aman (fallback array kosong jika query gagal atau tabel belum ada)
     * @return PortfolioHistory[]
     */
    public function getHistoriesList(): array
    {
        try {
            return $this->histories;
        } catch (\Throwable $e) {
            \Yii::warning('Failed fetching portfolio histories: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ambil harga pasar real-time terkini:
     * 1. Cek live quote dari cache / Yahoo Finance real-time.
     * 2. Jika offline/gagal, fallback ke IntradayPrice atau DailyPrice terbaru di DB.
     *
     * @param bool $forceRefresh Jika true, selalu fetch quote live baru dari Yahoo Finance.
     */
    public function getCurrentPrice(bool $forceRefresh = false): float
    {
        $stock = $this->stock;
        if ($stock === null) {
            return (float) $this->buy_price;
        }

        // 1. Coba ambil real-time live price (dengan cache 60 detik)
        $quote = $this->getLiveQuoteDetails($forceRefresh);
        if ($quote !== null && !empty($quote['price']) && $quote['price'] > 0) {
            return (float) $quote['price'];
        }

        // 2. Fallback: Cek harga intraday terbaru
        $intraday = IntradayPrice::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy(['datetime' => SORT_DESC])
            ->limit(1)
            ->one();

        if ($intraday !== null && (float) $intraday->close > 0) {
            return (float) $intraday->close;
        }

        // 3. Fallback: Cek daily price terbaru
        $daily = DailyPrice::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy(['date' => SORT_DESC])
            ->limit(1)
            ->one();

        if ($daily !== null && (float) $daily->close > 0) {
            return (float) $daily->close;
        }

        return (float) ($stock->price ?? $this->buy_price);
    }

    /**
     * Detail live quote realtime dari Yahoo Finance.
     */
    public function getLiveQuoteDetails(bool $forceRefresh = false): ?array
    {
        $stock = $this->stock;
        if ($stock === null) {
            return null;
        }

        $cacheKey = 'live_quote_' . $stock->symbol;
        if (!$forceRefresh && \Yii::$app->has('cache') && \Yii::$app->cache !== null) {
            $cached = \Yii::$app->cache->get($cacheKey);
            if (is_array($cached) && !empty($cached['price'])) {
                return $cached;
            }
        }

        $quote = self::fetchLiveQuote($stock->symbol);
        if ($quote !== null && !empty($quote['price'])) {
            if (\Yii::$app->has('cache') && \Yii::$app->cache !== null) {
                \Yii::$app->cache->set($cacheKey, $quote, 60); // cache 60 detik
            }

            // Sync ke IntradayPrice agar database lokal juga up to date
            try {
                self::recordLivePriceToIntraday($stock->id, $quote);
            } catch (\Throwable $e) {
                // Abaikan jika sync db gagal, tetap pakai harga live
            }

            return $quote;
        }

        return null;
    }

    /**
     * Fetch live quote langsung dari Yahoo Finance v8 chart API (gratis, tanpa API key).
     */
    public static function fetchLiveQuote(string $symbol): ?array
    {
        $cleanSymbol = strtoupper(trim(str_replace('.', '-', $symbol)));
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$cleanSymbol}?interval=1d&range=1d";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$res) {
            return null;
        }

        $data = json_decode($res, true);
        $meta = $data['chart']['result'][0]['meta'] ?? null;
        if (!$meta || empty($meta['regularMarketPrice'])) {
            return null;
        }

        $price = (float) $meta['regularMarketPrice'];
        $prev = (float) ($meta['previousClose'] ?? $meta['chartPreviousClose'] ?? $price);
        $change = $price - $prev;
        $changePct = $prev > 0 ? round(($change / $prev) * 100, 2) : 0.0;

        return [
            'symbol' => $symbol,
            'price' => $price,
            'previous_close' => $prev,
            'change' => round($change, 4),
            'change_pct' => $changePct,
            'timestamp' => !empty($meta['regularMarketTime']) ? (int) $meta['regularMarketTime'] : time(),
            'datetime' => !empty($meta['regularMarketTime']) ? date('Y-m-d H:i:s', $meta['regularMarketTime']) : date('Y-m-d H:i:s'),
            'is_live' => true,
        ];
    }

    /**
     * Catat / update harga live terkini ke tabel intraday price.
     */
    public static function recordLivePriceToIntraday(int $stockId, array $liveQuote): void
    {
        $price = (float) $liveQuote['price'];
        $dt = $liveQuote['datetime'] ?? date('Y-m-d H:i:s');

        // Cari bar intraday terbaru
        $lastBar = IntradayPrice::find()
            ->where(['stock_id' => $stockId])
            ->orderBy(['datetime' => SORT_DESC])
            ->one();

        if ($lastBar && substr($lastBar->datetime, 0, 10) === substr($dt, 0, 10)) {
            // Hari yang sama, update close dan high/low jika menembus
            $lastBar->close = $price;
            if ($price > (float)$lastBar->high) $lastBar->high = $price;
            if ($price < (float)$lastBar->low) $lastBar->low = $price;
            $lastBar->save(false);
        } else {
            // Buat bar baru
            $bar = new IntradayPrice();
            $bar->stock_id = $stockId;
            $bar->datetime = $dt;
            $bar->timeframe = '1h';
            $bar->open = $price;
            $bar->high = $price;
            $bar->low = $price;
            $bar->close = $price;
            $bar->volume = 0;
            $bar->save(false);
        }
    }

    /** Total modal yang dikeluarkan saat beli */
    public function getTotalCost(): float
    {
        return round((float) $this->buy_price * (float) $this->shares, 2);
    }

    /** Nilai pasar portofolio saat ini */
    public function getCurrentValue(): float
    {
        return round($this->getCurrentPrice() * (float) $this->shares, 2);
    }

    /** Keuntungan / Kerugian nominal ($) */
    public function getProfitLossAmount(): float
    {
        return round($this->getCurrentValue() - $this->getTotalCost(), 2);
    }

    /** Keuntungan / Kerugian persentase (%) */
    public function getProfitLossPct(): float
    {
        if ((float) $this->buy_price <= 0) {
            return 0.0;
        }
        return round((($this->getCurrentPrice() - (float) $this->buy_price) / (float) $this->buy_price) * 100, 2);
    }

    public function isProfit(): bool
    {
        return $this->getProfitLossAmount() > 0;
    }

    public function isLoss(): bool
    {
        return $this->getProfitLossAmount() < 0;
    }

    /** Harga jual target berdasarkan target_profit_pct (misal 3%) */
    public function getTargetSellPrice(): float
    {
        $pct = (float) $this->target_profit_pct;
        return round((float) $this->buy_price * (1 + ($pct / 100)), 2);
    }

    /** Proyeksi nominal keuntungan saat target profit tercapai ($) */
    public function getTargetProfitAmount(): float
    {
        return round(($this->getTargetSellPrice() - (float) $this->buy_price) * (float) $this->shares, 2);
    }

    /** Kenaikan harga ($) yang masih dibutuhkan untuk mencapai target sell */
    public function getRemainingToTargetAmount(): float
    {
        return round($this->getTargetSellPrice() - $this->getCurrentPrice(), 2);
    }

    /** Kenaikan persentase (%) dari harga saat ini untuk mencapai target sell */
    public function getRemainingToTargetPct(): float
    {
        $curr = $this->getCurrentPrice();
        if ($curr <= 0) {
            return 0.0;
        }
        return round((($this->getTargetSellPrice() - $curr) / $curr) * 100, 2);
    }

    /** Persentase kemajuan menuju target (0% s/d 100%+) */
    public function getProgressToTargetPct(): float
    {
        $targetPct = (float) $this->target_profit_pct;
        if ($targetPct <= 0) {
            return 0.0;
        }
        $currentPct = $this->getProfitLossPct();
        if ($currentPct <= 0) {
            return 0.0;
        }
        return min(100.0, round(($currentPct / $targetPct) * 100, 1));
    }

    public function getProfitLoss(): float
    {
        return $this->getProfitLossAmount();
    }

    public function getTargetProfitValue(): float
    {
        return $this->getTargetProfitAmount();
    }

    public function getProgressToTarget(): float
    {
        return $this->getProgressToTargetPct();
    }

    /**
     * Format jumlah lembar (shares): bulat jika integer, desimal rapi tanpa trailing zero jika desimal.
     */
    public function getFormattedShares(): string
    {
        return self::formatShares((float) $this->shares);
    }

    public static function formatShares(float|int|string $shares): string
    {
        $val = (float) $shares;
        if (floor($val) == $val) {
            return number_format($val, 0);
        }
        return rtrim(rtrim(number_format($val, 4), '0'), '.');
    }
}
