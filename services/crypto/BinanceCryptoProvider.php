<?php

declare(strict_types=1);

namespace app\services\crypto;

use yii\base\Component;
use yii\httpclient\Client;
use Yii;

/**
 * Data provider untuk Binance Public REST API (v3).
 * Tidak memerlukan API Key untuk market data publik.
 */
class BinanceCryptoProvider extends Component
{
    // data-api.binance.vision adalah official market data mirror Binance yang bebas dari Cloudflare 403 geo-block
    private const BASE_URL = 'https://data-api.binance.vision/api/v3';

    private ?Client $httpClient = null;

    public function init(): void
    {
        parent::init();
        $this->httpClient = new Client([
            'baseUrl' => self::BASE_URL,
            'transport' => 'yii\httpclient\CurlTransport',
            'responseConfig' => [
                'format' => Client::FORMAT_JSON,
            ],
            'requestConfig' => [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                ],
                'options' => [
                    'timeout' => 12,
                ],
            ],
        ]);
    }

    /**
     * Daftar default Top 28 Koin likuiditas tinggi berpasangan USDT
     *
     * @return array<int, array{symbol: string, base: string, quote: string, name: string, sort: int}>
     */
    public static function getStaticCoinList(): array
    {
        return [
            ['symbol' => 'BTCUSDT',  'base' => 'BTC',  'quote' => 'USDT', 'name' => 'Bitcoin',         'sort' => 1],
            ['symbol' => 'ETHUSDT',  'base' => 'ETH',  'quote' => 'USDT', 'name' => 'Ethereum',        'sort' => 2],
            ['symbol' => 'SOLUSDT',  'base' => 'SOL',  'quote' => 'USDT', 'name' => 'Solana',          'sort' => 3],
            ['symbol' => 'BNBUSDT',  'base' => 'BNB',  'quote' => 'USDT', 'name' => 'BNB Chain',       'sort' => 4],
            ['symbol' => 'XRPUSDT',  'base' => 'XRP',  'quote' => 'USDT', 'name' => 'Ripple',          'sort' => 5],
            ['symbol' => 'DOGEUSDT', 'base' => 'DOGE', 'quote' => 'USDT', 'name' => 'Dogecoin',        'sort' => 6],
            ['symbol' => 'ADAUSDT',  'base' => 'ADA',  'quote' => 'USDT', 'name' => 'Cardano',         'sort' => 7],
            ['symbol' => 'AVAXUSDT', 'base' => 'AVAX', 'quote' => 'USDT', 'name' => 'Avalanche',       'sort' => 8],
            ['symbol' => 'LINKUSDT', 'base' => 'LINK', 'quote' => 'USDT', 'name' => 'Chainlink',       'sort' => 9],
            ['symbol' => 'SUIUSDT',  'base' => 'SUI',  'quote' => 'USDT', 'name' => 'Sui Network',     'sort' => 10],
            ['symbol' => 'NEARUSDT', 'base' => 'NEAR', 'quote' => 'USDT', 'name' => 'NEAR Protocol',   'sort' => 11],
            ['symbol' => 'DOTUSDT',  'base' => 'DOT',  'quote' => 'USDT', 'name' => 'Polkadot',        'sort' => 12],
            ['symbol' => 'LTCUSDT',  'base' => 'LTC',  'quote' => 'USDT', 'name' => 'Litecoin',        'sort' => 13],
            ['symbol' => 'PEPEUSDT', 'base' => 'PEPE', 'quote' => 'USDT', 'name' => 'Pepe',            'sort' => 14],
            ['symbol' => 'SHIBUSDT', 'base' => 'SHIB', 'quote' => 'USDT', 'name' => 'Shiba Inu',      'sort' => 15],
            ['symbol' => 'APTUSDT',  'base' => 'APT',  'quote' => 'USDT', 'name' => 'Aptos',           'sort' => 16],
            ['symbol' => 'ARBUSDT',  'base' => 'ARB',  'quote' => 'USDT', 'name' => 'Arbitrum',        'sort' => 17],
            ['symbol' => 'OPUSDT',   'base' => 'OP',   'quote' => 'USDT', 'name' => 'Optimism',        'sort' => 18],
            ['symbol' => 'FETUSDT',  'base' => 'FET',  'quote' => 'USDT', 'name' => 'Artificial Super','sort' => 19],
            ['symbol' => 'TIAUSDT',  'base' => 'TIA',  'quote' => 'USDT', 'name' => 'Celestia',        'sort' => 20],
            ['symbol' => 'INJUSDT',  'base' => 'INJ',  'quote' => 'USDT', 'name' => 'Injective',       'sort' => 21],
            ['symbol' => 'RENDERUSDT','base'=> 'RENDER','quote' => 'USDT', 'name' => 'Render',        'sort' => 22],
            ['symbol' => 'POLUSDT',  'base' => 'POL',  'quote' => 'USDT', 'name' => 'Polygon Ecosystem','sort' => 23],
            ['symbol' => 'TONUSDT',  'base' => 'TON',  'quote' => 'USDT', 'name' => 'Toncoin',         'sort' => 24],
            ['symbol' => 'TAOUSDT',  'base' => 'TAO',  'quote' => 'USDT', 'name' => 'Bittensor',       'sort' => 25],
            ['symbol' => 'SEIUSDT',  'base' => 'SEI',  'quote' => 'USDT', 'name' => 'Sei Network',     'sort' => 26],
            ['symbol' => 'FILUSDT',  'base' => 'FIL',  'quote' => 'USDT', 'name' => 'Filecoin',        'sort' => 27],
            ['symbol' => 'KASUSDT',  'base' => 'KAS',  'quote' => 'USDT', 'name' => 'Kaspa',           'sort' => 28],
        ];
    }

    /**
     * Ambil ticker 24 jam untuk semua koin (sangat efisien: 1 call untuk seluruh koin)
     *
     * @return array<string, array{lastPrice: float, priceChangePercent: float, highPrice: float, lowPrice: float, volume: float, quoteVolume: float}>
     */
    public function get24hTickersMap(): array
    {
        try {
            $response = $this->httpClient->get('ticker/24hr')->send();
            if (!$response->isOk) {
                Yii::warning("Binance ticker/24hr returned status " . $response->statusCode, __METHOD__);
                return [];
            }
            $data = $response->data;
            if (!is_array($data)) {
                return [];
            }

            $result = [];
            foreach ($data as $item) {
                $sym = $item['symbol'] ?? '';
                if ($sym === '') {
                    continue;
                }
                $result[$sym] = [
                    'lastPrice' => (float)($item['lastPrice'] ?? 0),
                    'priceChangePercent' => (float)($item['priceChangePercent'] ?? 0),
                    'highPrice' => (float)($item['highPrice'] ?? 0),
                    'lowPrice' => (float)($item['lowPrice'] ?? 0),
                    'volume' => (float)($item['volume'] ?? 0),
                    'quoteVolume' => (float)($item['quoteVolume'] ?? 0),
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            Yii::error("Gagal mengambil ticker/24hr dari Binance: " . $e->getMessage(), __METHOD__);
            return [];
        }
    }

    /**
     * Ambil bar klines OHLCV + Taker Buy Volume
     *
     * @param string $symbol contoh 'BTCUSDT'
     * @param string $interval '15m', '1h', '2h', '4h', '1d'
     * @param int $limit default 100 bar
     * @return array<int, array{bar_time: string, open: float, high: float, low: float, close: float, volume: float, quote_volume: float, buy_volume: float, sell_volume: float}>
     */
    public function getKlines(string $symbol, string $interval = '1h', int $limit = 100): array
    {
        $symbol = strtoupper($symbol);
        $validIntervals = ['15m', '1h', '2h', '4h', '1d'];
        if (!in_array($interval, $validIntervals, true)) {
            $interval = '1h';
        }

        try {
            $response = $this->httpClient->get('klines', [
                'symbol' => $symbol,
                'interval' => $interval,
                'limit' => min($limit, 500),
            ])->send();

            if (!$response->isOk) {
                throw new \RuntimeException("Binance klines returned status " . $response->statusCode);
            }

            $rawBars = $response->data;
            if (!is_array($rawBars)) {
                return [];
            }

            $bars = [];
            foreach ($rawBars as $raw) {
                // Index Binance klines:
                // 0: Open time (ms)
                // 1: Open
                // 2: High
                // 3: Low
                // 4: Close
                // 5: Volume (base)
                // 6: Close time
                // 7: Quote asset volume (USDT)
                // 8: Number of trades
                // 9: Taker buy base asset volume (Volume Beli Agresif)
                // 10: Taker buy quote asset volume
                $openTimeMs = (int)($raw[0] ?? 0);
                $barTime = gmdate('Y-m-d H:i:s', (int)($openTimeMs / 1000));

                $open = (float)($raw[1] ?? 0);
                $high = (float)($raw[2] ?? 0);
                $low = (float)($raw[3] ?? 0);
                $close = (float)($raw[4] ?? 0);
                $volume = (float)($raw[5] ?? 0);
                $quoteVolume = (float)($raw[7] ?? 0);
                $buyVolume = (float)($raw[9] ?? 0);
                $sellVolume = max(0.0, $volume - $buyVolume);

                $bars[] = [
                    'bar_time' => $barTime,
                    'open' => $open,
                    'high' => $high,
                    'low' => $low,
                    'close' => $close,
                    'volume' => $volume,
                    'quote_volume' => $quoteVolume,
                    'buy_volume' => $buyVolume,
                    'sell_volume' => $sellVolume,
                ];
            }

            return $bars;
        } catch (\Throwable $e) {
            Yii::error("Gagal getKlines {$symbol} ({$interval}): " . $e->getMessage(), __METHOD__);
            return [];
        }
    }

    /**
     * Dapatkan kurs live USDT/IDR dari Binance Spot ticker
     *
     * @return float Kurs 1 USDT dalam IDR (misal 17922.0)
     */
    public function getUsdtIdrRate(): float
    {
        $cache = Yii::$app->cache;
        $cacheKey = 'crypto_live_usdt_idr_rate';
        if ($cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false && is_numeric($cached) && (float)$cached > 1000) {
                return (float)$cached;
            }
        }

        try {
            $response = $this->httpClient->get('ticker/price', ['symbol' => 'USDTIDR'])->send();
            if ($response->isOk && !empty($response->data['price'])) {
                $rate = (float)$response->data['price'];
                if ($rate > 1000) {
                    if ($cache) {
                        $cache->set($cacheKey, $rate, 300); // cache 5 menit
                    }
                    return $rate;
                }
            }
        } catch (\Throwable $e) {
            Yii::warning('Gagal fetch USDTIDR rate dari Binance: ' . $e->getMessage(), __METHOD__);
        }

        return 17900.0; // estimasi fallback jika offline
    }
}

