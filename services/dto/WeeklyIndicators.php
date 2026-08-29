<?php

declare(strict_types=1);

namespace app\services\dto;

/**
 * DTO indikator mingguan hasil agregasi VolumeAnalyzerService (task 3.2).
 * Dipakai SignalEngineService untuk scoring.
 */
final class WeeklyIndicators
{
    public function __construct(
        public readonly int $stockId,
        public readonly string $weekStart,     // Y-m-d (senin)
        public readonly int $buyVolume,
        public readonly int $sellVolume,
        public readonly float $buyRatio,        // 0..1
        public readonly float $sellRatio,       // 0..1
        public readonly float $buySellRatio,    // x
        public readonly ?float $volumeGrowth,   // 0.42 = +42%, null jika minggu pertama
        public readonly ?float $rvol,           // volume minggu ini / avg 4 minggu sebelumnya
        public readonly ?float $ma20,
        public readonly ?float $ma50,
        public readonly float $closePrice,
    ) {
    }
}
