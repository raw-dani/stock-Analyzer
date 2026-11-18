<?php

declare(strict_types=1);

namespace app\services\learning;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;

/**
 * WalkForwardValidator
 *
 * Mengelola pemisahan data historis lilin (candlestick) menjadi set In-Sample (Pelatihan)
 * dan Out-of-Sample (Pengujian Murni) untuk mencegah lookahead bias dan curve-fitting (overfitting).
 */
final class WalkForwardValidator
{
    public const TIMEFRAME_1H = 1;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_1D = 24;

    /**
     * Memuat candle urut kronologis (ASC) dan memecah menjadi In-Sample & Out-of-Sample.
     *
     * @param int $stockId
     * @param int $timeframe
     * @param int $maxCandles
     * @param float $splitRatio (default 0.70 = 70% In-Sample, 30% Out-of-Sample)
     * @return array{
     *     inSample: array<int, array<string, mixed>>,
     *     outOfSample: array<int, array<string, mixed>>,
     *     allCandles: array<int, array<string, mixed>>,
     *     total: int
     * }
     */
    public function loadSplitCandles(int $stockId, int $timeframe = self::TIMEFRAME_1D, int $maxCandles = 500, float $splitRatio = 0.70): array
    {
        $allCandles = $this->loadAscCandles($stockId, $timeframe, $maxCandles);
        $total = count($allCandles);

        if ($total < 40) {
            return [
                'inSample' => $allCandles,
                'outOfSample' => [],
                'allCandles' => $allCandles,
                'total' => $total,
            ];
        }

        $splitIndex = (int) floor($total * $splitRatio);
        $inSample = array_slice($allCandles, 0, $splitIndex);
        $outOfSample = array_slice($allCandles, $splitIndex);

        return [
            'inSample' => $inSample,
            'outOfSample' => $outOfSample,
            'allCandles' => $allCandles,
            'total' => $total,
        ];
    }

    /**
     * Ambil data lilin berurutan dari masa lampau ke masa kini (ASC) secara hemat memori.
     *
     * @param int $stockId
     * @param int $timeframe
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function loadAscCandles(int $stockId, int $timeframe, int $limit): array
    {
        if ($timeframe === self::TIMEFRAME_1D) {
            $rows = DailyPrice::find()
                ->where(['stock_id' => $stockId])
                ->orderBy(['date' => SORT_DESC])
                ->limit($limit)
                ->asArray()
                ->all();

            $candles = [];
            foreach (array_reverse($rows) as $r) {
                $candles[] = [
                    'date'   => $r['date'],
                    'open'   => (float) $r['open'],
                    'high'   => (float) $r['high'],
                    'low'    => (float) $r['low'],
                    'close'  => (float) $r['close'],
                    'volume' => (int) $r['volume'],
                ];
            }
            return $candles;
        }

        // Intraday
        $rows = IntradayPrice::find()
            ->where(['stock_id' => $stockId, 'timeframe' => IntradayPrice::TF_1H])
            ->orderBy(['datetime' => SORT_DESC])
            ->limit(max($limit * 4, 200))
            ->asArray()
            ->all();

        if (count($rows) >= 20) {
            $base = [];
            foreach (array_reverse($rows) as $r) {
                $base[] = [
                    'date'   => $r['datetime'],
                    'open'   => (float) $r['open'],
                    'high'   => (float) $r['high'],
                    'low'    => (float) $r['low'],
                    'close'  => (float) $r['close'],
                    'volume' => (int) $r['volume'],
                ];
            }

            $per = match ($timeframe) {
                self::TIMEFRAME_4H => 4,
                self::TIMEFRAME_2H => 2,
                default => 1,
            };

            $aggregated = $this->aggregate($base, $per);
            return array_slice($aggregated, -$limit);
        }

        // Fallback ke daily jika intraday kosong
        return $this->loadAscCandles($stockId, self::TIMEFRAME_1D, $limit);
    }

    /**
     * Agregasi candle intraday (e.g. 1H -> 2H atau 4H).
     */
    private function aggregate(array $base, int $per): array
    {
        if ($per <= 1) {
            return $base;
        }

        $out = [];
        $chunks = array_chunk($base, $per);
        foreach ($chunks as $chunk) {
            $open = $chunk[0]['open'];
            $close = end($chunk)['close'];
            $date = end($chunk)['date'];
            $high = -PHP_FLOAT_MAX;
            $low = PHP_FLOAT_MAX;
            $vol = 0;
            foreach ($chunk as $b) {
                $high = max($high, $b['high']);
                $low = min($low, $b['low']);
                $vol += $b['volume'];
            }
            $out[] = [
                'date' => $date,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => $vol,
            ];
        }
        return $out;
    }
}
