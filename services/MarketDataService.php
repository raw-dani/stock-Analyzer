<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
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
