<?php

declare(strict_types=1);

namespace app\services\market;

/**
 * Satu bar OHLCV harian dari provider.
 */
final class DailyBar
{
    public function __construct(
        public readonly string $symbol,
        public readonly string $date,      // Y-m-d
        public readonly float $open,
        public readonly float $high,
        public readonly float $low,
        public readonly float $close,
        public readonly int $volume,
    ) {
    }

    /**
     * Validasi sanity OHLC (task 2.6):
     * - high >= max(open, close), low <= min(open, close)
     * - semua harga > 0, volume >= 0
     */
    public function isValid(): bool
    {
        return $this->open > 0
            && $this->high > 0
            && $this->low > 0
            && $this->close > 0
            && $this->volume >= 0
            && $this->high >= max($this->open, $this->close)
            && $this->low <= min($this->open, $this->close)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1;
    }
}
