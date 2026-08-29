<?php

declare(strict_types=1);

namespace app\services\market;

/**
 * Kontrak provider data pasar (desain §8.1 — task 2.1).
 */
interface DataProviderInterface
{
    /**
     * Ambil bar OHLCV harian untuk satu simbol.
     *
     * @param string $symbol normalized symbol, mis. "NVDA"
     * @return DailyBar[] diurutkan ascending by date
     * @throws \yii\base\Exception jika provider gagal / simbol tidak dikenal
     */
    public function getDailyBars(string $symbol, ?string $fromDate = null, ?string $toDate = null): array;

    /**
     * Daftar simbol aktif yang tersedia di provider.
     *
     * @return array<int, array{symbol: string, name: string, exchange: string, sector?: ?string, industry?: ?string, market_cap?: ?int}>
     */
    public function getStockList(): array;
}
