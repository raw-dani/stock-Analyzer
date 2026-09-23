<?php

declare(strict_types=1);

namespace app\services\market;

use Yii;
use yii\base\Exception;

/**
 * Provider dari file CSV lokal (dev/test/backfill — task 2.2).
 * Format file: data/csv/SYMBOL.csv dengan header date,open,high,low,close,volume
 */
final class CsvImportProvider implements DataProviderInterface, IntradayDataProviderInterface
{
    public function __construct(public string $dataPath = '@app/data/csv')
    {
    }

    public function getDailyBars(string $symbol, ?string $fromDate = null, ?string $toDate = null): array
    {
        $file = Yii::getAlias($this->dataPath) . '/' . strtoupper($symbol) . '.csv';
        if (!is_file($file)) {
            throw new Exception("CSV not found for symbol {$symbol}: {$file}");
        }

        $bars = [];
        $handle = fopen($file, 'rb');
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 6) {
                continue; // skip baris kosong/malformed
            }
            [$date, $open, $high, $low, $close, $volume] = $row;
            if ($fromDate !== null && $date < $fromDate) {
                continue;
            }
            if ($toDate !== null && $date > $toDate) {
                continue;
            }
            $bars[] = new DailyBar(
                symbol: strtoupper($symbol),
                date: $date,
                open: (float) $open,
                high: (float) $high,
                low: (float) $low,
                close: (float) $close,
                volume: (int) $volume,
            );
        }
        fclose($handle);

        usort($bars, fn ($a, $b) => strcmp($a->date, $b->date));
        return $bars;
    }

    public function getStockList(): array
    {
        $dir = Yii::getAlias($this->dataPath);
        $list = [];
                foreach (glob($dir . '/*.csv') ?: [] as $file) {
            $symbol = strtoupper(basename($file, '.csv'));
            $list[] = ['symbol' => $symbol, 'name' => $symbol, 'exchange' => 'NASDAQ', 'sector' => null];
        }
        return $list;
    }

    /**
     * CSV tidak menyimpan data intraday — kembalikan array kosong agar
     * syncIntraday() tidak crash pada dev backend.
     */
    public function getIntradayBars(string $symbol, string $interval = '1h', ?string $fromDatetime = null, ?string $toDatetime = null): array
    {
        return [];
    }
}