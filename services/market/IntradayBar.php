<?php
// IntradayBar: satu bar OHLCV intraday (reuse untuk 1h/interval lain).
// Properti datetime 'Y-m-d H:i:s' + method isValid() mirror DailyBar.

declare(strict_types=1);

namespace app\services\market;

final class IntradayBar
{
    public function __construct(
        public readonly string $symbol,
        public readonly string $datetime, // Y-m-d H:i:s
        public readonly string $interval, // '1h'
        public readonly float $open,
        public readonly float $high,
        public readonly float $low,
        public readonly float $close,
        public readonly int $volume,
    ) {
    }

    public function isValid(): bool
    {
        return $this->open > 0
            && $this->high > 0
            && $this->low > 0
            && $this->close > 0
            && $this->volume >= 0
            && $this->high >= max($this->open, $this->close)
            && $this->low <= min($this->open, $this->close)
            && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $this->datetime) === 1;
    }

    /** Konversi ke DailyBar-compatible array untuk upsert lama bila perlu. */
    public function toArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'datetime' => $this->datetime,
            'interval' => $this->interval,
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'close' => $this->close,
            'volume' => $this->volume,
        ];
    }
}
