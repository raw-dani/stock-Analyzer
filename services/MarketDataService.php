<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;
use app\services\market\DataProviderInterface;
use app\services\market\DailyBar;
use Yii;
use yii\base\Component;
use yii\db\Expression;

/**
 * Menyimpan data provider ke DB: validasi sanity, normalisasi symbol,
 * upsert idempotent berdasarkan unique key (stock_id, date) (task 2.5, 2.6).
 */
final class MarketDataService extends Component
{
    public function __construct(private DataProviderInterface $provider, array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Normalisasi symbol: uppercase, hanya [A-Z.], trim.
     */
    public static function normalizeSymbol(string $symbol): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9.]/', '', trim($symbol)) ?? '');
    }

    /**
     * Ambil & simpan bar harian satu simbol (atau update data yang sudah ada).
     *
     * @return int jumlah bar baru/upserted
     */
    public function syncSymbol(string $symbol, ?string $fromDate = null, ?string $toDate = null): int
    {
        $symbol = self::normalizeSymbol($symbol);
        $stock = Stock::findOne(['symbol' => $symbol]);

        if ($stock === null) {
            // auto-register simbol yang belum ada
            $stock = new Stock();
            $stock->symbol = $symbol;
            $stock->name = $symbol;
            $stock->exchange = Stock::EXCHANGE_NASDAQ;
            $stock->save(false);
            Yii::info("Auto-registered stock {$symbol}", 'app\services\market');
        }
        $this->withStockContext($stock->id);

        // Incremental refresh: bila --from tak diisi, mulai dari tanggal
        // terakhir di DB minus overlap 5 hari (hemat kuota Yahoo).
        if ($fromDate === null) {
            $last = DailyPrice::find()->where(['stock_id' => $stock->id])->max('date');
            if (is_string($last) && $last !== '') {
                $fromDate = date('Y-m-d', strtotime($last) - 5 * 86400);
            }
        }

        $bars = $this->provider->getDailyBars($symbol, $fromDate, $toDate);
        $count = 0;

        foreach ($bars as $bar) {
            if (!$this->isValidBar($bar)) {
                Yii::warning("Skip invalid bar {$symbol} {$bar->date} (OHLC sanity failed)", 'app\services\market');
                continue;
            }
            $count += $this->upsertBar($stock->id, $bar);
        }

        Yii::info("Synced {$symbol}: {$count}/" . count($bars) . " bars upserted", 'app\services\market');
        return $count;
    }

    /**
     * Sync data intraday (1h base) untuk satu simbol — incremental.
     * Bila $fromDate null, lanjutkan dari datetime terakhir di DB
     * (dikurangi overlap 2 hari agar candle revisi tertimpa).
     * Menerima DailyBar lama maupun IntradayBar baru.
     *
     * @return int jumlah bar yang disimpan
     */
    public function syncIntraday(string $symbol, string $interval = '1h', ?string $fromDate = null, ?string $toDate = null): int
    {
        $interval = strtolower(trim($interval));
        $symbol = self::normalizeSymbol($symbol);
        $stock = Stock::findOne(['symbol' => $symbol]);

        if ($stock === null) {
            $stock = new Stock();
            $stock->symbol = $symbol;
            $stock->name = $symbol;
            $stock->exchange = Stock::EXCHANGE_NASDAQ;
            $stock->save(false);
        }

        if ($fromDate === null) {
            $last = IntradayPrice::find()
                ->where(['stock_id' => $stock->id, 'timeframe' => $interval])
                ->max('datetime');
            if (is_string($last) && $last !== '') {
                $fromDate = date('Y-m-d H:i:s', strtotime($last) - 2 * 86400);
            }
        }
        $bars = $this->provider->getIntradayBars($symbol, $interval, $fromDate, $toDate);
        $count = 0;

        foreach ($bars as $bar) {
            if (!$this->isValidIntradayBar($bar)) {
                Yii::warning("Skip invalid intraday bar {$symbol}", 'app\services\market');
                continue;
            }
            $count += $this->upsertIntradayBar($stock->id, $bar, $interval);
        }

        Yii::info("Synced intraday {$symbol}: {$count}/" . count($bars) . " bars upserted", 'app\services\market');
        return $count;
    }

    /**
     * Upsert idempotent untuk bar intraday.
     */
    private function upsertIntradayBar(int $stockId, object $bar, string $interval): int
    {
        $dt = $bar->datetime ?? $bar->date ?? null;
        if (!is_string($dt) || $dt === '') {
            return 0;
        }
        $existing = IntradayPrice::find()
            ->where(['stock_id' => $stockId, 'datetime' => $dt, 'timeframe' => $interval])
            ->one();

        if ($existing === null) {
            $row = new IntradayPrice();
            $row->created_at = time();
        } else {
            $row = $existing;
        }

        $row->stock_id = $stockId;
        $row->datetime = $dt;
        $row->timeframe = $interval;
        $row->open = $bar->open;
        $row->high = $bar->high;
        $row->low = $bar->low;
        $row->close = $bar->close;
        $row->volume = $bar->volume;
        $row->updated_at = time();

        return $row->save(false) ? 1 : 0;
    }

    /**
     * Validasi bar intraday dasar. Mendukung IntradayBar (datetime)
     * maupun DailyBar lama (date berisi 'Y-m-d H:i:s').
     */
    private function isValidIntradayBar(object $bar): bool
    {
        if (method_exists($bar, 'isValid')) {
            return $bar->isValid();
        }
        $dt = $bar->datetime ?? $bar->date ?? null;
        return $dt !== null
            && is_numeric($bar->open) && $bar->open > 0
            && is_numeric($bar->high) && $bar->high > 0
            && is_numeric($bar->low) && $bar->low > 0
            && is_numeric($bar->close) && $bar->close > 0
            && $bar->high >= $bar->low;
    }

    /**
     * Upsert idempotent — aman dijalankan ulang (cron berulang).
     * buy/sell volume dibiarkan null; diisi oleh VolumeAnalyzerService (Modul 3).
     */
    private function upsertBar(int $stockId, DailyBar $bar): int
    {
        $existing = DailyPrice::find()
            ->where(['stock_id' => $stockId, 'date' => $bar->date])
            ->one();

        if ($existing === null) {
            $row = new DailyPrice();
            $row->created_at = time();
        } else {
            $row = $existing;
        }

        $row->stock_id = $stockId;
        $row->date = $bar->date;
        $row->open = $bar->open;
        $row->high = $bar->high;
        $row->low = $bar->low;
        $row->close = $bar->close;
        $row->volume = $bar->volume;
        $row->updated_at = time();

        return $row->save(false) ? 1 : 0;
    }

    /**
     * OHLC sanity check + gap detection terhadap bar sebelumnya (task 2.6).
     */
    private function isValidBar(DailyBar $bar): bool
    {
        if (!$bar->isValid()) {
            return false;
        }

        // gap sanity: harga close yang loncat >25% dibanding bar sebelumnya dianggap mencurigakan
        $last = DailyPrice::find()
            ->where(['stock_id' => $this->currentStockId])
            ->orderBy(['date' => SORT_DESC])
            ->one();
        if ($last !== null && $last->close > 0) {
            $change = abs($bar->close - (float) $last->close) / (float) $last->close;
            if ($change > 0.25) {
                Yii::warning(
                    "Suspicious gap: {$bar->symbol} {$bar->date} change " . round($change * 100, 1) . "%",
                    'app\services\market'
                );
                // tetap disimpan, hanya warning (split bisa valid)
            }
        }

        return true;
    }

    private ?int $currentStockId = null;

    /** dipanggil sebelum loop bars agar gap-check punya konteks stock */
    public function withStockContext(int $stockId): self
    {
        $this->currentStockId = $stockId;
        return $this;
    }
}
