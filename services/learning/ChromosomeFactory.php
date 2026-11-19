<?php

declare(strict_types=1);

namespace app\services\learning;

/**
 * ChromosomeFactory
 *
 * Bertanggung jawab mendefinisikan ruang pencarian (search space), inisialisasi populasi acak,
 * operator rekombinasi (crossover), dan operator mutasi genetik untuk parameter strategi trading.
 */
final class ChromosomeFactory
{
    public const STRATEGY_STOCHASTIC = 'stochastic';
    public const STRATEGY_RSI        = 'rsi';
    public const STRATEGY_MULTI      = 'multi';

    /**
     * Dapatkan skema gen dan batasan rentang nilainya berdasarkan strategi.
     *
     * @param string $strategy
     * @return array<string, array{type: string, min?: float|int, max?: float|int, options?: array}>
     */
    public static function getGeneSchema(string $strategy): array
    {
        return match ($strategy) {
            self::STRATEGY_RSI => [
                'rsi_period'     => ['type' => 'int', 'min' => 6, 'max' => 25],
                'oversold'       => ['type' => 'float', 'min' => 18.0, 'max' => 38.0, 'step' => 1.0],
                'overbought'     => ['type' => 'float', 'min' => 65.0, 'max' => 85.0, 'step' => 1.0],
                'tp_target_pct'  => ['type' => 'float', 'min' => 1.5, 'max' => 8.0, 'step' => 0.25],
                'sl_pct'         => ['type' => 'float', 'min' => 0.8, 'max' => 4.0, 'step' => 0.2],
                'trend_filter'   => ['type' => 'bool'],
                'candle_confirm' => ['type' => 'bool'],
                'trail_bep'      => ['type' => 'bool'],
            ],
            self::STRATEGY_MULTI => [
                'stoch_k'        => ['type' => 'int', 'min' => 7, 'max' => 25],
                'stoch_os'       => ['type' => 'float', 'min' => 15.0, 'max' => 35.0, 'step' => 1.0],
                'rsi_period'     => ['type' => 'int', 'min' => 7, 'max' => 21],
                'rsi_os'         => ['type' => 'float', 'min' => 20.0, 'max' => 38.0, 'step' => 1.0],
                'ma_fast'        => ['type' => 'int', 'min' => 9, 'max' => 25],
                'ma_slow'        => ['type' => 'int', 'min' => 30, 'max' => 90],
                'min_signals'    => ['type' => 'int', 'min' => 2, 'max' => 3],
                'tp_target_pct'  => ['type' => 'float', 'min' => 2.0, 'max' => 7.5, 'step' => 0.25],
                'sl_pct'         => ['type' => 'float', 'min' => 1.0, 'max' => 3.5, 'step' => 0.2],
                'trend_filter'   => ['type' => 'bool'],
                'trail_bep'      => ['type' => 'bool'],
            ],
            default => [ // stochastic
                'periodK'        => ['type' => 'int', 'min' => 5, 'max' => 30],
                'smoothK'        => ['type' => 'int', 'min' => 1, 'max' => 6],
                'periodD'        => ['type' => 'int', 'min' => 1, 'max' => 6],
                'oversold'       => ['type' => 'float', 'min' => 15.0, 'max' => 35.0, 'step' => 1.0],
                'overbought'     => ['type' => 'float', 'min' => 65.0, 'max' => 88.0, 'step' => 1.0],
                'tp_target_pct'  => ['type' => 'float', 'min' => 1.5, 'max' => 8.0, 'step' => 0.25],
                'sl_pct'         => ['type' => 'float', 'min' => 0.8, 'max' => 4.0, 'step' => 0.2],
                'trend_filter'   => ['type' => 'bool'],
                'candle_confirm' => ['type' => 'bool'],
                'trail_bep'      => ['type' => 'bool'],
            ],
        };
    }

    /**
     * Buat 1 kromosom acak yang valid.
     *
     * @param string $strategy
     * @return array<string, mixed>
     */
    public static function createRandomChromosome(string $strategy): array
    {
        $schema = self::getGeneSchema($strategy);
        $chromosome = [];

        foreach ($schema as $gene => $rules) {
            $chromosome[$gene] = self::randomGeneValue($rules);
        }

        return self::enforceConstraints($chromosome, $strategy);
    }

    /**
     * Inisialisasi populasi awal (N individu acak).
     *
     * @param string $strategy
     * @param int $populationSize
     * @return array<int, array<string, mixed>>
     */
    public static function createInitialPopulation(string $strategy, int $populationSize = 30): array
    {
        $population = [];
        for ($i = 0; $i < $populationSize; $i++) {
            $population[] = self::createRandomChromosome($strategy);
        }
        return $population;
    }

    /**
     * Rekombinasi Crossover seragam (Uniform Crossover) antara 2 kromosom induk.
     * Menghasilkan 2 kromosom anak.
     *
     * @param array<string, mixed> $parentA
     * @param array<string, mixed> $parentB
     * @param string $strategy
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function crossover(array $parentA, array $parentB, string $strategy): array
    {
        $child1 = [];
        $child2 = [];

        foreach ($parentA as $gene => $valA) {
            $valB = $parentB[$gene] ?? $valA;
            if (random_int(0, 1) === 0) {
                $child1[$gene] = $valA;
                $child2[$gene] = $valB;
            } else {
                $child1[$gene] = $valB;
                $child2[$gene] = $valA;
            }
        }

        return [
            self::enforceConstraints($child1, $strategy),
            self::enforceConstraints($child2, $strategy),
        ];
    }

    /**
     * Mutasi genetik: Mengubah nilai gen tertentu dengan probabilitas mutationRate.
     *
     * @param array<string, mixed> $chromosome
     * @param string $strategy
     * @param float $mutationRate (default 0.15 = 15% per gen)
     * @return array<string, mixed>
     */
    public static function mutate(array $chromosome, string $strategy, float $mutationRate = 0.15): array
    {
        $schema = self::getGeneSchema($strategy);
        $mutated = $chromosome;

        foreach ($schema as $gene => $rules) {
            if ((random_int(1, 1000) / 1000.0) <= $mutationRate) {
                // Mutasi nilai: jika bool di-flip, jika numeric di-jitter atau di-regenerate
                if ($rules['type'] === 'bool') {
                    $mutated[$gene] = !($mutated[$gene] ?? false);
                } elseif ($rules['type'] === 'int') {
                    $delta = random_int(-2, 2);
                    $current = (int) ($mutated[$gene] ?? $rules['min']);
                    $mutated[$gene] = max($rules['min'], min($rules['max'], $current + $delta));
                } elseif ($rules['type'] === 'float') {
                    $step = $rules['step'] ?? 0.5;
                    $delta = (random_int(-3, 3)) * $step;
                    $current = (float) ($mutated[$gene] ?? $rules['min']);
                    $val = round(max($rules['min'], min($rules['max'], $current + $delta)), 2);
                    $mutated[$gene] = $val;
                }
            }
        }

        return self::enforceConstraints($mutated, $strategy);
    }

    /**
     * Memastikan batas logika antar gen terpenuhi (e.g. SL < TP, oversold < overbought, ma_fast < ma_slow).
     *
     * @param array<string, mixed> $chromosome
     * @param string $strategy
     * @return array<string, mixed>
     */
    public static function enforceConstraints(array $chromosome, string $strategy): array
    {
        // 1. Oversold vs Overbought
        if (isset($chromosome['oversold'], $chromosome['overbought'])) {
            if ($chromosome['oversold'] >= $chromosome['overbought'] - 15.0) {
                $chromosome['oversold'] = 20.0;
                $chromosome['overbought'] = 80.0;
            }
        }

        // 2. Risk/Reward: TP harus selalu minimal 1.3x - 1.5x lebih besar dari SL
        if (isset($chromosome['tp_target_pct'], $chromosome['sl_pct'])) {
            if ($chromosome['tp_target_pct'] < $chromosome['sl_pct'] * 1.3) {
                $chromosome['tp_target_pct'] = round((float) $chromosome['sl_pct'] * 1.5, 2);
            }
        }

        // 3. Multi Strategy MA: Fast < Slow
        if (isset($chromosome['ma_fast'], $chromosome['ma_slow'])) {
            if ($chromosome['ma_fast'] >= $chromosome['ma_slow']) {
                $chromosome['ma_fast'] = 14;
                $chromosome['ma_slow'] = 50;
            }
        }

        return $chromosome;
    }

    /**
     * Helper menghasilkan nilai acak sesuai aturan gen.
     *
     * @param array<string, mixed> $rules
     * @return mixed
     */
    private static function randomGeneValue(array $rules): mixed
    {
        return match ($rules['type']) {
            'bool' => (random_int(0, 1) === 1),
            'int' => random_int((int) $rules['min'], (int) $rules['max']),
            'float' => (function () use ($rules) {
                $step = $rules['step'] ?? 0.5;
                $minSteps = (int) round($rules['min'] / $step);
                $maxSteps = (int) round($rules['max'] / $step);
                return round(random_int($minSteps, $maxSteps) * $step, 2);
            })(),
            default => null,
        };
    }
}
