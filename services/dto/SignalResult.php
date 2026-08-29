<?php

declare(strict_types=1);

namespace app\services\dto;

/**
 * Hasil evaluasi SignalEngineService.
 */
final class SignalResult
{
    public function __construct(
        public readonly int $score,
        public readonly string $signal,
        public readonly array $reasons,
    ) {
    }
}
