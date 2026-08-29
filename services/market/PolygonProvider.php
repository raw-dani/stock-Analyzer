<?php

declare(strict_types=1);

namespace app\services\market;

use yii\base\Exception;

/**
 * Polygon.io Aggregates Daily (task 2.3).
 */
final class PolygonProvider extends BaseHttpProvider
{
    public function getDailyBars(string $symbol, ?string $fromDate = null, ?string $toDate = null): array
    {
        $from = $fromDate ?? date('Y-m-d', strtotime('-1 year'));
        $to = $toDate ?? date('Y-m-d');

        $path = "/v2/aggs/ticker/" . strtoupper($symbol) . "/range/1/day/{$from}/{$to}";
        $data = $this->apiGet([
            'adjusted' => 'true',
            'sort' => 'asc',
            'limit' => 50000,
            'apiKey' => $this->apiKey,
        ], apiPath: $path);

        if (!isset($data['results']) || !is_array($data['results'])) {
            throw new Exception("Polygon: unexpected response for {$symbol}");
        }

        $bars = [];
        foreach ($data['results'] as $r) {
            $date = date('Y-m-d', (int) ($r['t'] / 1000));
            $bars[] = new DailyBar(
                symbol: strtoupper($symbol),
                date: $date,
                open: (float) $r['o'],
                high: (float) $r['h'],
                low: (float) $r['l'],
                close: (float) $r['c'],
                volume: (int) $r['v'],
            );
        }

        usort($bars, fn ($a, $b) => strcmp($a->date, $b->date));
        return $bars;
    }
}
