<?php

declare(strict_types=1);

namespace app\services\market;

/**
 * Kontrak opsional untuk provider yang mendukung data intraday.
 * Dipisah dari DataProviderInterface agar provider lama (CSV/AV)
 * tidak pecah kontrak.
 */
interface IntradayDataProviderInterface
{
    /**
     * Ambil bar OHLCV intraday untuk satu simbol.
     *
     * @param string $symbol normalized symbol, mis. "NVDA"
     * @param string $interval '1h' (base). '2h'/'4h' boleh didukung atau diagregasi caller.
     * @param string|null $fromDatetime 'Y-m-d H:i:s'
     * @param string|null $toDatetime 'Y-m-d H:i:s'
     * @return IntradayBar[] diurutkan ascending by datetime
     */
    public function getIntradayBars(string $symbol, string $interval = '1h', ?string $fromDatetime = null, ?string $toDatetime = null): array;
}
