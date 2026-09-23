<?php

declare(strict_types=1);

namespace app\services;

use app\models\DailyPrice;
use app\models\IntradayPrice;
use app\models\Stock;

/**
 * Deteksi Double Bottom pada seri RSI (bukan harga).
 * Mirror DoubleBottomService: scanAll + detectPattern + confidence.
 * TF: 1H/2H/4H dari intraday_price 1h, 1D dari daily_price.
 */
final class RsiDoubleBottomService
{
    public const TIMEFRAME_1H = 1;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_1D = 24;

    private const MIN_LOOKBACK = 20;
    private const MAX_LOOKBACK = 300;
    private const MIN_RSI_PERIOD = 2;
    private const MAX_RSI_PERIOD = 50;
    private const MIN_TOLERANCE = 0.5;
    private const MAX_TOLERANCE = 10.0;
    private const MIN_MAX_RSI = 0.0;
    private const MAX_MAX_RSI = 100.0;

    public function scanAll(
        int $timeframe = 4,
        int $lookback = 150,
        float $tol = 3.0,
        int $period = 14,
        float $maxRsi = 40.0,
        int $minSeparation = 5,
        int $maxSeparation = 30,
        float $necklineMin = 2.0
    ): array {
        $this->validateOptions($timeframe, $lookback, $tol, $period, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $results = [];
        $stocks = Stock::find()->where(['active' => true])->all();
        \Yii::info('RSI-Bottom scan: ' . count($stocks) . " stocks tf={$timeframe}", 'app\services\rsidoublebottom');
        foreach ($stocks as $stock) {
            try {
                $p = $this->detectPattern($stock->id, $timeframe, $lookback, $tol, $period, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
            } catch (\Throwable $e) {
                \Yii::warning("RSI-Bottom scan failed {$stock->symbol}: " . $e->getMessage(), 'app\services\rsidoublebottom');
                continue;
            }
            if ($p !== null) {
                $p['symbol'] = $stock->symbol;
                $p['stock_name'] = $stock->name;
                $p['sector'] = $stock->sector;
                $results[] = $p;
            }
        }
        usort($results, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);
        return $results;
    }

    public function detectPattern(
        int $stockId,
        int $tf = 4,
        int $lookback = 150,
        float $tol = 3.0,
        int $period = 14,
        float $maxRsi = 40.0,
        int $minSeparation = 5,
        int $maxSeparation = 30,
        float $necklineMin = 2.0
    ): ?array {
        $this->validateOptions($tf, $lookback, $tol, $period, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        [$candles, $approx] = $this->loadCandles($stockId, $tf, $lookback);
        if (count($candles) < ($period + 10)) {
            return null;
        }
        $closes = array_map(fn ($c) => (float) $c['close'], $candles);
        $rsi = RsiService::calculate($closes, $period);
        $found = $this->findBottom($candles, $rsi, $tol, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        if ($found === null) {
            return null;
        }
        $found['timeframe'] = $tf;
        $found['approximated'] = $approx;
        $found['data_source'] = $approx ? 'daily_fallback' : ($tf === self::TIMEFRAME_1D ? 'daily' : 'intraday');
        $found['rsi_period'] = $period;
        return $found;
    }

    private function validateOptions(
        int $timeframe,
        int $lookback,
        float $tol,
        int $period,
        float $maxRsi,
        int $minSeparation,
        int $maxSeparation,
        float $necklineMin
    ): void {
        if (!in_array($timeframe, [self::TIMEFRAME_1H, self::TIMEFRAME_2H, self::TIMEFRAME_4H, self::TIMEFRAME_1D], true)) {
            throw new \InvalidArgumentException('Timeframe harus salah satu dari 1H, 2H, 4H, atau 1D.');
        }
        if ($lookback < self::MIN_LOOKBACK || $lookback > self::MAX_LOOKBACK) {
            throw new \InvalidArgumentException("Lookback harus antara " . self::MIN_LOOKBACK . " dan " . self::MAX_LOOKBACK . " candle.");
        }
        if ($period < self::MIN_RSI_PERIOD || $period > self::MAX_RSI_PERIOD) {
            throw new \InvalidArgumentException("RSI period harus antara " . self::MIN_RSI_PERIOD . " dan " . self::MAX_RSI_PERIOD . '.');
        }
        if (!is_finite($tol) || $tol < self::MIN_TOLERANCE || $tol > self::MAX_TOLERANCE) {
            throw new \InvalidArgumentException("Tolerance harus antara " . self::MIN_TOLERANCE . " dan " . self::MAX_TOLERANCE . " poin RSI.");
        }
        if (!is_finite($maxRsi) || $maxRsi < self::MIN_MAX_RSI || $maxRsi > self::MAX_MAX_RSI) {
            throw new \InvalidArgumentException('Max RSI harus antara 0 dan 100.');
        }
        if ($minSeparation < 1 || $maxSeparation < $minSeparation) {
            throw new \InvalidArgumentException('Separation tidak valid.');
        }
        if (!is_finite($necklineMin) || $necklineMin < 0.0) {
            throw new \InvalidArgumentException('Neckline minimum tidak valid.');
        }
    }

    private function loadCandles(int $stockId, int $tf, int $lookback): array
    {
        if ($tf === self::TIMEFRAME_1D) {
            return [$this->dailyCandles($stockId, $lookback), false];
        }
        try {
            $rows = IntradayPrice::find()
                ->where(['stock_id' => $stockId, 'timeframe' => '1h'])
                ->orderBy(['datetime' => SORT_DESC])
                ->limit(max($lookback * 4, 60))
                ->all();
        } catch (\Throwable $e) {
            \Yii::warning("Intraday data unavailable for RSI-Bottom scan {$stockId}: " . $e->getMessage(), 'app\services\rsidoublebottom');
            return [$this->dailyCandles($stockId, $lookback), true];
        }
        $rows = array_reverse($rows);
        $base = [];
        foreach ($rows as $p) {
            $base[] = ['open' => (float) $p->open, 'high' => (float) $p->high, 'low' => (float) $p->low, 'close' => (float) $p->close, 'volume' => (int) $p->volume, 'date' => $p->datetime];
        }
        if (count($base) >= 20) {
            $per = $tf === 2 ? 2 : ($tf === 4 ? 4 : 1);
            return [array_slice($this->aggregate($base, $per), -$lookback), false];
        }
        return [$this->dailyCandles($stockId, $lookback), true];
    }

    private function dailyCandles(int $stockId, int $lookback): array
    {
        $prices = DailyPrice::find()
            ->where(['stock_id' => $stockId])
            ->orderBy(['date' => SORT_DESC])
            ->limit($lookback)
            ->all();
        $prices = array_reverse($prices);
        $out = [];
        foreach ($prices as $p) {
            $out[] = ['open' => (float) $p->open, 'high' => (float) $p->high, 'low' => (float) $p->low, 'close' => (float) $p->close, 'volume' => (int) $p->volume, 'date' => $p->date];
        }
        return $out;
    }

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

    private function makeCandle(array $chunk): array
    {
        return [
            'open' => (float) $chunk[0]['open'],
            'high' => max(array_column($chunk, 'high')),
            'low' => min(array_column($chunk, 'low')),
            'close' => (float) end($chunk)['close'],
            'volume' => array_sum(array_column($chunk, 'volume')),
            'date' => end($chunk)['date'],
        ];
    }

    private function findBottom(
        array $candles,
        array $rsi,
        float $tol,
        float $maxRsi,
        int $minSeparation,
        int $maxSeparation,
        float $necklineMin
    ): ?array
    {
        $n = count($candles);
        $lows = [];
        for ($i = 1; $i < $n - 1; $i++) {
            if ($rsi[$i] === null || $rsi[$i - 1] === null || $rsi[$i + 1] === null) {
                continue;
            }
            if ($rsi[$i] < $rsi[$i - 1] && $rsi[$i] < $rsi[$i + 1] && $rsi[$i] < $maxRsi) {
                $lows[] = ['index' => $i, 'rsi' => $rsi[$i]];
            }
        }
        $best = null;
        $lc = count($lows);
        for ($a = 0; $a < $lc; $a++) {
            for ($b = $a + 1; $b < $lc; $b++) {
                $i1 = $lows[$a]['index'];
                $i2 = $lows[$b]['index'];
                $sep = $i2 - $i1;
                if ($sep < $minSeparation || $sep > $maxSeparation) {
                    continue;
                }
                $r1 = $lows[$a]['rsi'];
                $r2 = $lows[$b]['rsi'];
                if (abs($r1 - $r2) > $tol) {
                    continue;
                }
                $neck = -1.0;
                $neckIdx = $i1;
                for ($k = $i1 + 1; $k < $i2; $k++) {
                    if ($rsi[$k] !== null && $rsi[$k] > $neck) {
                        $neck = $rsi[$k];
                        $neckIdx = $k;
                    }
                }
                if ($neck < max($r1, $r2) + $necklineMin) {
                    continue;
                }
                $cur = $rsi[$n - 1];
                if ($cur === null) {
                    continue;
                }
                $breakout = $cur > $neck;
                $p1 = (float) $candles[$i1]['low'];
                $p2 = (float) $candles[$i2]['low'];
                $div = ($p2 < $p1) && ($r2 > $r1);
                $conf = $this->confidence(abs($r1 - $r2), $neck - ($r1 + $r2) / 2, $breakout, $div, $r1, $r2);
                $neckPrice = 0.0;
                for ($k = $i1; $k <= $i2; $k++) {
                    $neckPrice = max($neckPrice, (float) $candles[$k]['high']);
                }
                $curPrice = (float) $candles[$n - 1]['close'];
                $avgLow = ($p1 + $p2) / 2;
                $target = $neckPrice + ($neckPrice - $avgLow);
                $cand = [
                    'rsi1_value' => round($r1, 2), 'rsi1_date' => $candles[$i1]['date'],
                    'rsi2_value' => round($r2, 2), 'rsi2_date' => $candles[$i2]['date'],
                    'neckline_rsi' => round($neck, 2), 'neckline_date' => $candles[$neckIdx]['date'],
                    'current_rsi' => round($cur, 2),
                    'low1_price' => $p1, 'low2_price' => $p2,
                    'neckline_price' => $neckPrice, 'current_price' => $curPrice,
                    'breakout' => $breakout, 'divergence' => $div,
                    'confidence' => $conf, 'target_price' => round($target, 2),
                    'risk_reward' => ($curPrice > $avgLow) ? round(($target - $curPrice) / ($curPrice - $avgLow), 2) : null,
                ];
                if ($best === null || $cand['confidence'] > $best['confidence']) {
                    $best = $cand;
                }
            }
        }
        return $best;
    }

    private function confidence(float $diff, float $depth, bool $bo, bool $div, float $r1, float $r2): int
    {
        $s = 50;
        if ($diff <= 1.0) {
            $s += 15;
        } elseif ($diff <= 2.0) {
            $s += 10;
        } elseif ($diff <= 3.0) {
            $s += 5;
        }
        if ($bo) {
            $s += 20;
        }
        if ($depth > 8) {
            $s += 10;
        } elseif ($depth > 5) {
            $s += 5;
        }
        if ($div) {
            $s += 10;
        }
        if ($r1 < 30 && $r2 < 30) {
            $s += 5;
        }
        return min(100, max(0, $s));
    }

    public static function getTimeframeLabel(int $tf): string
    {
        return match ($tf) {
            self::TIMEFRAME_1H => '1 Hour',
            self::TIMEFRAME_2H => '2 Hours',
            self::TIMEFRAME_4H => '4 Hours',
            self::TIMEFRAME_1D => '1 Day',
            default => "{$tf}H",
        };
    }

    public static function getTimeframes(): array
    {
        return [
            self::TIMEFRAME_1H => '1 Hour',
            self::TIMEFRAME_2H => '2 Hours',
            self::TIMEFRAME_4H => '4 Hours',
            self::TIMEFRAME_1D => '1 Day',
        ];
    }
}
