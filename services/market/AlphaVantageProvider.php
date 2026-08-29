<?php

declare(strict_types=1);

namespace app\services\market;

use yii\base\Exception;

/**
 * Alpha Vantage TIME_SERIES_DAILY (task 2.3).
 */
final class AlphaVantageProvider extends BaseHttpProvider
{
    private const FUNCTION_DAILY = 'TIME_SERIES_DAILY';

    public function getDailyBars(string $symbol, ?string $fromDate = null, ?string $toDate = null): array
    {
        $data = $this->apiGet([
            'function' => self::FUNCTION_DAILY,
            'symbol' => strtoupper($symbol),
            'outputsize' => 'full',
            'datatype' => 'json',
            'apikey' => $this->apiKey,
        ]);

        $series = $data['Time Series (Daily)'] ?? null;
        if (!is_array($series)) {
            throw new Exception("Alpha Vantage: unexpected response for {$symbol}");
        }

        $bars = [];
        foreach ($series as $date => $row) {
            if ($fromDate !== null && $date < $fromDate) {
                continue;
            }
            if ($toDate !== null && $date > $toDate) {
                continue;
            }
            $bars[] = new DailyBar(
                symbol: strtoupper($symbol),
                date: $date,
                open: (float) $row['1. open'],
                high: (float) $row['2. high'],
                low: (float) $row['3. low'],
                close: (float) $row['4. close'],
                volume: (int) $row['5. volume'],
            );
        }

        if ($bars === []) {
            throw new Exception("Alpha Vantage: no bars for {$symbol}");
        }

        usort($bars, fn ($a, $b) => strcmp($a->date, $b->date));
        return $bars;
    }
}
