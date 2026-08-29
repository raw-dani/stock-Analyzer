<?php

declare(strict_types=1);

namespace app\services\market;

use Yii;
use yii\base\Exception;
use yii\httpclient\Client;

/**
 * Provider HTTP yang punya rate-limit + retry/backoff (task 2.3).
 * Subclass AlphaVantage/Polygon memakai helper ini.
 */
abstract class BaseHttpProvider implements DataProviderInterface
{
    public function __construct(
        public string $apiKey,
        public string $baseUrl,
        public int $rateLimitPerMinute = 5,
    ) {
        if ($apiKey === '') {
            throw new Exception(static::class . ': API key is empty. Set env var or params.php');
        }
    }

    /**
     * GET request dengan:
     * - sliding-window rate limit per menit
     * - retry 3x dengan exponential backoff untuk 429/5xx
     *
     * @param string|null $apiPath path tambahan setelah baseUrl (Polygon pakai ini)
     * @return array decoded JSON
     */
    protected function apiGet(array $params, int $maxRetries = 3, ?string $apiPath = null): array
    {
        static $requestTimestamps = [];

        // rate limit sliding window 60 detik
        $now = microtime(true);
        $requestTimestamps = array_values(array_filter($requestTimestamps, fn ($t) => ($now - $t) < 60));
        if (count($requestTimestamps) >= $this->rateLimitPerMinute) {
            $sleepUntil = $requestTimestamps[0] + 61;
            if ($sleepUntil > $now) {
                usleep((int) (($sleepUntil - $now) * 1_000_000));
            }
        }
        $requestTimestamps[] = microtime(true);

        $url = $this->baseUrl . ($apiPath ?? '');

        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                $response = Yii::createObject([
                    'class' => Client::class,
                    'requestConfig' => ['format' => Client::FORMAT_JSON],
                    'responseConfig' => ['format' => Client::FORMAT_JSON],
                ])->get($url, $params)->setOptions(['timeout' => 30])->send();

                if ($response->getStatusCode() === 429 || $response->getStatusCode() >= 500) {
                    throw new Exception('HTTP ' . $response->getStatusCode());
                }
                $data = $response->getData();
                if (isset($data['Note']) || isset($data['Error Message'])) {
                    // Alpha Vantage rate-limit message
                    throw new Exception('Provider said: ' . ($data['Note'] ?? $data['Error Message']));
                }
                return $data;
            } catch (\Throwable $e) {
                if ($attempt > $maxRetries) {
                    throw new Exception(static::class . ' failed after ' . $maxRetries . ' retries: ' . $e->getMessage(), 0, $e);
                }
                // exponential backoff: 2s, 4s, 8s
                usleep((int) (2 ** $attempt * 1_000_000));
            }
        }
    }

    public function getStockList(): array
    {
        // Provider HTTP umumnya tidak menyediakan full stock list gratis;
        // stock list dikelola lokal (seed / refresh terpisah).
        return [];
    }
}
