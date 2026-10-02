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
 * - Default scanner terpusat di konstanta DEFAULT_* agar konsisten
 *   dengan controller / API / backtest / alert.
 */
final class DoubleBottomService
{
    // Timeframe constants (dalam jam; 24 = harian)
    public const TIMEFRAME_1H = 1;
    public const TIMEFRAME_2H = 2;
    public const TIMEFRAME_4H = 4;
    public const TIMEFRAME_1D = 24;

    // Default scanner — satu-satunya sumber default untuk web/API/backtest/alert.
    public const DEFAULT_TIMEFRAME = self::TIMEFRAME_4H;
    public const DEFAULT_LOOKBACK = 60;         // jumlah candle yang dipindai
    public const DEFAULT_TOLERANCE = 0.02;      // 2% (desimal)
    public const DEFAULT_MIN_SEPARATION = 3;
    public const NECKLINE_MIN_DEPTH = 0.015;    // 1.5% di atas rata-rata low

    private const MIN_LOOKBACK = 5;
    private const MAX_LOOKBACK = 500;
    private const MIN_TOLERANCE = 0.001;
    private const MAX_TOLERANCE = 0.20;
    private const MIN_SEPARATION = 2;
    private const MIN_INTRADAY_BARS = 20;
    private const STOP_LOSS_BUFFER = 0.02;      // stop loss 2% di bawah rata-rata low

    /**
     * Scan semua saham aktif untuk double bottom pattern.
     * @param int|null $lookback jumlah candle (bukan hari kalender). null = DEFAULT_LOOKBACK
     * @param float|null $tolerance desimal (0.02 = 2%). null = DEFAULT_TOLERANCE
     * @param int|null $maxSeparation null = otomatis (lookback/2)
     * @param bool $breakoutOnly true = hanya kembalikan pola yang sudah breakout
     * @param int $minConfidence filter confidence minimum (0-100)
     * @param float|null $necklineMinDepth kedalaman neckline minimum (desimal). null = NECKLINE_MIN_DEPTH
     */
    public function scanAll(
        int $timeframe = self::DEFAULT_TIMEFRAME,
        ?int $lookback = null,
        ?float $tolerance = null,
        int $minSeparation = self::DEFAULT_MIN_SEPARATION,
        ?int $maxSeparation = null,
        bool $breakoutOnly = false,
        int $minConfidence = 0,
        ?float $necklineMinDepth = null
    ): array {
        $lookback ??= self::DEFAULT_LOOKBACK;
        $tolerance ??= self::DEFAULT_TOLERANCE;
        $necklineMinDepth ??= self::NECKLINE_MIN_DEPTH;
        $this->validateOptions($timeframe, $lookback, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);
        $maxSeparation ??= (int) floor($lookback / 2);
        $results = [];
        $stocks = Stock::find()->where(['active' => true])->all();
        
        \Yii::info("Scan started: " . count($stocks) . " stocks, timeframe={$timeframe}, lookback={$lookback}, tolerance={$tolerance}", 'app\services\doublebottom');

        foreach ($stocks as $stock) {
            $pattern = $this->detectPattern($stock->id, $timeframe, $lookback, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);
            if ($pattern === null) {
                continue;
            }
            if ($breakoutOnly && !$pattern['breakout']) {
                continue;
            }
            if ($pattern['confidence'] < $minConfidence) {
                continue;
            }
            $pattern['symbol'] = $stock->symbol;
            $pattern['stock_name'] = $stock->name;
            $pattern['sector'] = $stock->sector;
            $results[] = $pattern;
            \Yii::info("Pattern found: {$stock->symbol}, confidence={$pattern['confidence']}", 'app\services\doublebottom');
        }

        usort($results, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);
        return $results;
    }

    /**
     * Deteksi pola double bottom untuk satu saham.
     * $necklineMinDepth = kedalaman W minimum (rasio desimal); null -> default.
     */
    public function detectPattern(
        int $stockId,
        int $timeframe = self::DEFAULT_TIMEFRAME,
        ?int $lookback = null,
        ?float $tolerance = null,
        int $minSeparation = self::DEFAULT_MIN_SEPARATION,
        ?int $maxSeparation = null,
        ?float $necklineMinDepth = null
    ): ?array {
        $lookback ??= self::DEFAULT_LOOKBACK;
        $tolerance ??= self::DEFAULT_TOLERANCE;
        $necklineMinDepth ??= self::NECKLINE_MIN_DEPTH;
        $this->validateOptions($timeframe, $lookback, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);
        $maxSeparation ??= (int) floor($lookback / 2);

        [$candles, $approx] = $this->loadCandles($stockId, $timeframe, $lookback);

        if (count($candles) < 5) {
            \Yii::debug("Stock ID {$stockId}: Not enough candles (" . count($candles) . ' < 5)', 'app\services\doublebottom');
            return null;
        }

        $result = $this->findDoubleBottom($candles, $tolerance, $minSeparation, $maxSeparation, $necklineMinDepth);

        if ($result === null) {
            \Yii::debug("Stock ID {$stockId}: No double bottom pattern found", 'app\services\doublebottom');
            return null;
        }

        $result['timeframe'] = $timeframe;
        $result['approximated'] = $approx;
        $result['data_source'] = $approx ? 'daily_fallback' : ($timeframe === self::TIMEFRAME_1D ? 'daily' : 'intraday');
        return $result;
    }

    private function validateOptions(int $timeframe, int $lookback, float $tolerance, int $minSeparation, ?int $maxSeparation, ?float $necklineMinDepth = null): void
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
        if ($necklineMinDepth !== null && (!is_finite($necklineMinDepth) || $necklineMinDepth < 0 || $necklineMinDepth > 0.5)) {
            throw new \InvalidArgumentException('Neckline minimum depth harus antara 0% dan 50% (0 - 0.5).');
        }
    }

    /**
     * Muat candle untuk deteksi pola double bottom.
     * - TIMEFRAME_1D memakai daily_price.
     * - TIMEFRAME_1H/2H/4H memakai intraday_price 1h (2H/4H diagregasi).
     * - Jika intraday < MIN_INTRADAY_BARS bar -> fallback harian (approx=true).
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

    /**
     * Ambil candle analisa untuk chart / ekspor (publik, dipakai halaman detail).
     * @return array{0: array<int, array{open:float,high:float,low:float,close:float,volume:int,date:string}>, 1: bool}
     */
    public function getAnalysisCandles(int $stockId, int $timeframe = self::DEFAULT_TIMEFRAME, ?int $lookback = null): array
    {
        $lookback ??= self::DEFAULT_LOOKBACK;
        return $this->loadCandles($stockId, $timeframe, $lookback);
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
        float $necklineMinDepth = self::NECKLINE_MIN_DEPTH
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
                            $diff,
                            $candles,
                            $low1['index'],
                            $low2['index']
                        );

                        $targetPrice = $neckline + ($neckline - $avgLow);
                        $riskBase = $currentClose - $avgLow;
                        $stopLoss = $avgLow * (1 - self::STOP_LOSS_BUFFER);
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
                            'stop_loss' => $stopLoss,
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
     *
     * Bonus volume (maks +10 agar harga tetap dominan):
     *  +5 low2 sepi (distribusi klasik: minat jual melemah di retest),
     *  +5 breakout bervolume (konfirmasi kekuatan penembusan neckline).
     * Basis penilaian memakai rata-rata volume window yang dipindai.
     */
    private function calculateConfidence(
        float $low1,
        float $low2,
        float $neckline,
        float $currentClose,
        bool $breakout,
        float $lowDiff,
        array $candles = [],
        int $low1Index = -1,
        int $low2Index = -1
    ): int {
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

        // --- Bonus volume (optional; dilewati bila data volume tidak tersedia) ---
        $volumes = array_column($candles, 'volume');
        $volumes = array_values(array_filter($volumes, fn ($v) => (int) $v > 0));
        if (count($volumes) >= 5) {
            $avgVol = array_sum($volumes) / count($volumes);
            if ($avgVol > 0 && $low2Index >= 0 && isset($candles[$low2Index]['volume'])) {
                // Low kedua sepi = tekanan jual melemah (sehat untuk reversal)
                if ((int) $candles[$low2Index]['volume'] < $avgVol * 0.9) {
                    $score += 5;
                }
            }
            if ($breakout) {
                // Breakout bervolume = konfirmasi kekuatan buyer
                $lastVol = (int) end($candles)['volume'];
                if ($lastVol > $avgVol * 1.5) {
                    $score += 5;
                }
            }
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