<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;

/**
 * Service untuk deteksi pola Double Bottom (price-based).
 *
 * - TIMEFRAME_1D memakai candle harian (daily_price).
 * - TIMEFRAME_1H/2H/4H memakai intraday_price (base 1h); 2H/4H
 *   diagregasi dari bar 1H. Bila intraday < 20 bar -> fallback
 *   harian (approximated=true, data_source='daily_fallback').
 */
final class DoubleBottomService
{
    // Timeframe constants (dalam jam; 24 = harian)
    public const TIMEFRAME_1H = 1;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_1D = 24;

    private const MIN_LOOKBACK = 5;
    private const MAX_LOOKBACK = 500;
    private const MIN_TOLERANCE = 0.001;
    private const MAX_TOLERANCE = 0.20;
    private const MIN_SEPARATION = 2;
    private const MIN_INTRADAY_BARS = 20;

    /**
     * Scan semua saham aktif untuk double bottom pattern.
     * @param int $lookbackDays jumlah candle (bukan hari kalender)
     */
    public function scanAll(int $timeframe = self::TIMEFRAME_4H, int $lookbackDays = 60, float $tolerance = 0.02, int $minSeparation = 3, ?int $maxSeparation = null): array
    {
        $this->validateOptions($timeframe, $lookbackDays, $tolerance, $minSeparation, $maxSeparation);
        $maxSeparation ??= (int) floor($lookbackDays / 2);
        $results = [];
        $stocks = Stock::find()->where(['active' => true])->all();
        
        \Yii::info("Scan started: " . count($stocks) . " stocks, timeframe={$timeframe}, lookback={$lookbackDays}, tolerance={$tolerance}", 'app\services\doublebottom');

        foreach ($stocks as $stock) {
            $pattern = $this->detectPattern($stock->id, $timeframe, $lookbackDays, $tolerance, $minSeparation, $maxSeparation);
            if ($pattern !== null) {
                $pattern['symbol'] = $stock->symbol;
                $pattern['stock_name'] = $stock->name;
                $pattern['sector'] = $stock->sector;
                $results[] = $pattern;
                \Yii::info("Pattern found: {$stock->symbol}, confidence={$pattern['confidence']}", 'app\services\doublebottom');
            }
        }

        usort($results, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);
        return $results;
    }

    /**
     * Deteksi double bottom pattern untuk satu saham.
     */
    public function detectPattern(int $stockId, int $timeframe = self::TIMEFRAME_4H, int $lookbackDays = 60, float $tolerance = 0.02, int $minSeparation = 3, ?int $maxSeparation = null): ?array
    {
        $this->validateOptions($timeframe, $lookbackDays, $tolerance, $minSeparation, $maxSeparation);
        $maxSeparation ??= (int) floor($lookbackDays / 2);

        [$candles, $approx] = $this->loadCandles($stockId, $timeframe, $lookbackDays);

        if (count($candles) < 5) {
            \Yii::debug("Stock ID {$stockId}: Not enough candles (" . count($candles) . ' < 5)', 'app\services\doublebottom');
            return null;
        }

        $result = $this->findDoubleBottom($candles, $tolerance, $minSeparation, $maxSeparation);

        if ($result === null) {
            \Yii::debug("Stock ID {$stockId}: No double bottom pattern found", 'app\services\doublebottom');
            return null;
        }

        $result['timeframe'] = $timeframe;
        $result['approximated'] = $approx;
        $result['data_source'] = $approx ? 'daily_fallback' : ($timeframe === self::TIMEFRAME_1D ? 'daily' : 'intraday');
        return $result;
    }

    private function validateOptions(int $timeframe, int $lookback, float $tolerance, int $minSeparation, ?int $maxSeparation): void
    {
        if (!in_array($timeframe, [self::TIMEFRAME_1H, self::TIMEFRAME_2H, self::TIMEFRAME_4H, self::TIMEFRAME_1D], true)) {
            throw new \InvalidArgumentException('Timeframe harus salah satu dari 1H, 2H, 4H, atau 1D.');
        }
        if ($lookback < self::MIN_LOOKBACK || $lookback > self::MAX_LOOKBACK) {
            throw new \InvalidArgumentException('Lookback harus antara ' . self::MIN_LOOKBACK . ' dan ' . self::MAX_LOOKBACK . ' candle.');
        }
        if (!is_finite($tolerance) || $tolerance < self::MIN_TOLERANCE || $tolerance > self::MAX_TOLERANCE) {
            throw new \InvalidArgumentException('Tolerance harus antara 0.1% dan 20% (0.001 - 0.20).');
        }
        if ($minSeparation < self::MIN_SEPARATION) {
            throw new \InvalidArgumentException('Separasi minimum antar low adalah ' . self::MIN_SEPARATION . ' candle.');
        }
        if ($maxSeparation !== null && $maxSeparation < $minSeparation) {
            throw new \InvalidArgumentException('Separasi maksimum harus >= separasi minimum.');
        }
    }

    /**
     * Muat candle analisa: 1D dari daily_price; 1H/2H/4H dari
     * intraday_price (base 1h, agregasi 2h/4h). Fallback harian
     * bila intraday < 20 bar. Return [candles, approximated].
     *
     * @return array{0: array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}>, 1: bool}
     */
    private function loadCandles(int $stockId, int $timeframe, int $lookback): array
    {
        if ($timeframe === self::TIMEFRAME_1D) {
            return [$this->dailyCandles($stockId, $lookback), false];
        }
        try {
            $rows = IntradayPrice::find()
                ->where(['stock_id' => $stockId, 'timeframe' => IntradayPrice::TF_1H])
                ->orderBy(['datetime' => SORT_DESC])
                ->limit(max($lookback * 4, 60))
                ->all();
        } catch (\Throwable $e) {
            \Yii::warning('Intraday unavailable (DB scan ' . $stockId . '): ' . $e->getMessage(), 'app\services\doublebottom');
            return [$this->dailyCandles($stockId, $lookback), true];
        }
        $base = [];
        foreach (array_reverse($rows) as $p) {
            $base[] = [
                'open' => (float) $p->open,
                'high' => (float) $p->high,
                'low' => (float) $p->low,
                'close' => (float) $p->close,
                'volume' => (int) $p->volume,
                'date' => $p->datetime,
            ];
        }
        if (count($base) >= self::MIN_INTRADAY_BARS) {
            $per = $timeframe === self::TIMEFRAME_2H ? 2 : ($timeframe === self::TIMEFRAME_4H ? 4 : 1);
            return [array_slice($this->aggregate($base, $per), -$lookback), false];
        }
        return [$this->dailyCandles($stockId, $lookback), true];
    }

    /** @return array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> */
    private function dailyCandles(int $stockId, int $lookback): array
    {
        $prices = DailyPrice::find()
            ->where(['stock_id' => $stockId])
            ->orderBy(['date' => SORT_DESC])
            ->limit($lookback)
            ->all();
        $out = [];
        foreach (array_reverse($prices) as $p) {
            $out[] = [
                'open' => (float) $p->open,
                'high' => (float) $p->high,
                'low' => (float) $p->low,
                'close' => (float) $p->close,
                'volume' => (int) $p->volume,
                'date' => $p->date,
            ];
        }
        return $out;
    }

    /**
     * Agregasi bar 1H -> 2H/4H (O=first, H=max, L=min, C=last, V=sum).
     * @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $base
     */
    private function aggregate(array $base, int $per): array
    {
        if ($per <= 1) {
            return $base;
        }
        $out = [];
        $chunk = [];
        foreach ($base as $c) {
            $chunk[] = $c;
            if (count($chunk) >= $per) {
                $out[] = $this->makeCandle($chunk);
                $chunk = [];
            }
        }
        if (count($chunk) > 0) {
            $out[] = $this->makeCandle($chunk);
        }
        return $out;
    }

    /** @param array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}> $chunk */
    private function makeCandle(array $chunk): array
    {
        return [
            'open' => (float) $chunk[0]['open'],
            'high' => (float) max(array_column($chunk, 'high')),
            'low' => (float) min(array_column($chunk, 'low')),
            'close' => (float) end($chunk)['close'],
            'volume' => (int) array_sum(array_column($chunk, 'volume')),
            'date' => end($chunk)['date'],
        ];
    }

    /**
     * Cari double bottom pattern: evaluasi SEMUA pair low dengan
     * separasi valid, kembalikan kandidat confidence tertinggi.
     * Syarat: neckline harus cukup dalam (default >= 1.5% di atas
     * rata-rata low) agar pola W valid, bukan sideways datar.
     */
    private function findDoubleBottom(
        array $candles,
        float $tolerance,
        int $minSeparation,
        int $maxSeparation,
        float $necklineMinDepth = 0.015
    ): ?array {
        $count = count($candles);
        if ($count < 5) {
            return null;
        }

        // Cari semua lows (local minima)
        $lows = [];
        for ($i = 1; $i < $count - 1; $i++) {
            if ($candles[$i]['low'] < $candles[$i - 1]['low'] && $candles[$i]['low'] < $candles[$i + 1]['low']) {
                $lows[] = [
                    'index' => $i,
                    'price' => $candles[$i]['low'],
                    'date' => $candles[$i]['date'],
                ];
            }
        }

        // Evaluasi semua pair, simpan yang terbaik (bukan first-match)
        $best = null;
        $lc = count($lows);
        for ($i = 0; $i < $lc; $i++) {
            for ($j = $i + 1; $j < $lc; $j++) {
                $low1 = $lows[$i];
                $low2 = $lows[$j];
                $sep = $low2['index'] - $low1['index'];
                if ($sep < $minSeparation || $sep > $maxSeparation) {
                    continue;
                }

                $avgLow = ($low1['price'] + $low2['price']) / 2;
                if ($avgLow <= 0) {
                    continue;
                }
                $diff = abs($low1['price'] - $low2['price']) / $avgLow;

                if ($diff <= $tolerance) {
                    // Cari neckline (high tertinggi di antara kedua low)
                    $neckline = 0;
                    $necklineDate = '';
                    for ($k = $low1['index']; $k <= $low2['index']; $k++) {
                        if ($candles[$k]['high'] > $neckline) {
                            $neckline = $candles[$k]['high'];
                            $necklineDate = $candles[$k]['date'];
                        }
                    }

                    // Neckline harus cukup dalam di atas avg low (pola W valid)
                    $depth = ($neckline - $avgLow) / $avgLow;
                    if ($neckline > $avgLow && $depth >= $necklineMinDepth) {
                        $currentClose = end($candles)['close'];
                        $breakout = $currentClose > $neckline;

                        $confidence = $this->calculateConfidence(
                            $low1['price'],
                            $low2['price'],
                            $neckline,
                            $currentClose,
                            $breakout,
                            $diff
                        );

                        $targetPrice = $neckline + ($neckline - $avgLow);
                        $riskBase = $currentClose - $avgLow;
                        $cand = [
                            'low1_price' => $low1['price'],
                            'low1_date' => $low1['date'],
                            'low2_price' => $low2['price'],
                            'low2_date' => $low2['date'],
                            'neckline' => $neckline,
                            'neckline_date' => $necklineDate,
                            'current_price' => $currentClose,
                            'breakout' => $breakout,
                            'confidence' => $confidence,
                            'target_price' => $targetPrice,
                            // risk_reward hanya bermakna bila harga di atas support
                            'risk_reward' => ($breakout && $riskBase > 0) ? ($targetPrice - $currentClose) / $riskBase : null,
                        ];
                        if ($best === null || $cand['confidence'] > $best['confidence']) {
                            $best = $cand;
                        }
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Hitung confidence score untuk pattern (0-100).
     * Catatan: $currentClose > neckline*1.02 adalah konfirmasi
     * kekuatan breakout harga (bukan data volume intraday).
     */
    private function calculateConfidence(float $low1, float $low2, float $neckline, float $currentClose, bool $breakout, float $lowDiff): int
    {
        $score = 50;

        // Bonus untuk lows yang sangat mirip
        if ($lowDiff < 0.01) {
            $score += 15;
        } elseif ($lowDiff < 0.015) {
            $score += 10;
        } elseif ($lowDiff < 0.02) {
            $score += 5;
        }

        // Bonus untuk breakout
        if ($breakout) {
            $score += 20;
        }

        // Bonus untuk depth yang cukup
        $depth = ($neckline - ($low1 + $low2) / 2) / (($low1 + $low2) / 2);
        if ($depth > 0.05) {
            $score += 10;
        } elseif ($depth > 0.03) {
            $score += 5;
        }

        // Bonus untuk konfirmasi kekuatan breakout harga (>2% di atas neckline)
        if ($currentClose > $neckline * 1.02) {
            $score += 5;
        }

        return min(100, max(0, $score));
    }

    /**
     * Get timeframe label.
     */
    public static function getTimeframeLabel(int $timeframe): string
    {
        return match ($timeframe) {
            self::TIMEFRAME_1H => '1 Hour',
            self::TIMEFRAME_2H => '2 Hours',
            self::TIMEFRAME_4H => '4 Hours',
            self::TIMEFRAME_1D => 'Daily',
            default => "{$timeframe}H",
        };
    }

    /**
     * Get available timeframes (termasuk Daily agar konsisten dengan service).
     */
    public static function getTimeframes(): array
    {
        return [
            self::TIMEFRAME_1H => '1 Hour',
            self::TIMEFRAME_2H => '2 Hours',
            self::TIMEFRAME_4H => '4 Hours',
            self::TIMEFRAME_1D => 'Daily',
        ];
    }
}