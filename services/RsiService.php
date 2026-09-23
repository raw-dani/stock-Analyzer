<?php

declare(strict_types=1);

namespace app\services;

/**
 * Kalkulasi RSI (Relative Strength Index) metode Wilder.
 * Pure function — mudah di-unit-test, tanpa dependency DB/Yii.
 */
final class RsiService
{
    /**
     * Hitung seri RSI sejajar index dengan input closes.
     * Elemen 0..period-1 = null (belum cukup data).
     *
     * @param float[] $closes harga close berurutan ASC
     * @return array<int, float|null> seri RSI 0-100
     */
    public static function calculate(array $closes, int $period = 14): array
    {
        $n = count($closes);
        $out = array_fill(0, $n, null);
        if ($period < 2 || $n <= $period) {
            return $out;
        }
        $gain = 0.0;
        $loss = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $diff = (float) $closes[$i] - (float) $closes[$i - 1];
            if ($diff > 0) {
                $gain += $diff;
            } else {
                $loss += -$diff;
            }
        }
        $avgGain = $gain / $period;
        $avgLoss = $loss / $period;
        $out[$period] = self::toRsi($avgGain, $avgLoss);
        for ($i = $period + 1; $i < $n; $i++) {
            $diff = (float) $closes[$i] - (float) $closes[$i - 1];
            $g = $diff > 0 ? $diff : 0.0;
            $l = $diff < 0 ? -$diff : 0.0;
            $avgGain = ($avgGain * ($period - 1) + $g) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $l) / $period;
            $out[$i] = self::toRsi($avgGain, $avgLoss);
        }
        return $out;
    }

    private static function toRsi(float $avgGain, float $avgLoss): float
    {
        if ($avgLoss <= 0.0) {
            return 100.0;
        }
        if ($avgGain <= 0.0) {
            return 0.0;
        }
        $rs = $avgGain / $avgLoss;
        return round(100.0 - (100.0 / (1.0 + $rs)), 2);
    }
}
