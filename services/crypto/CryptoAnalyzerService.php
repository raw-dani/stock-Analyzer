<?php

declare(strict_types=1);

namespace app\services\crypto;

use app\models\CryptoAnalysis;
use app\models\CryptoAsset;
use app\models\CryptoPrice;
use yii\base\Component;
use Yii;

/**
 * Service untuk orkestrasi data, kalkulasi indikator volume & sinyal crypto.
 */
class CryptoAnalyzerService extends Component
{
    private BinanceCryptoProvider $provider;

    public function init(): void
    {
        parent::init();
        $this->provider = new BinanceCryptoProvider();
        $this->provider->init();
    }

    /**
     * Daftarkan atau update daftar default 28 koin crypto likuiditas tinggi
     */
    public function refreshCoinList(): int
    {
        $staticList = BinanceCryptoProvider::getStaticCoinList();
        $count = 0;
        $now = time();

        foreach ($staticList as $item) {
            $coin = CryptoAsset::findOne(['symbol' => $item['symbol']]);
            if ($coin === null) {
                $coin = new CryptoAsset();
                $coin->created_at = $now;
            }
            $coin->symbol = $item['symbol'];
            $coin->base_asset = $item['base'];
            $coin->quote_asset = $item['quote'];
            $coin->name = $item['name'];
            $coin->sort_order = $item['sort'];
            $coin->is_active = true;
            $coin->updated_at = $now;
            if ($coin->save()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sinkronisasi bar OHLCV + Taker volume untuk 1 koin pada timeframe tertentu
     *
     * @param CryptoAsset $asset
     * @param string $timeframe '15m', '1h', '2h', '4h', '1d'
     * @param int $limit
     * @return int jumlah bar yang tersimpan/terupdate
     */
    public function syncCoinBars(CryptoAsset $asset, string $timeframe = '1h', int $limit = 100): int
    {
        $bars = $this->provider->getKlines($asset->symbol, $timeframe, $limit);
        if (empty($bars)) {
            return 0;
        }

        $now = time();
        $saved = 0;

        foreach ($bars as $bar) {
            $price = CryptoPrice::findOne([
                'crypto_id' => $asset->id,
                'timeframe' => $timeframe,
                'bar_time' => $bar['bar_time'],
            ]);

            if ($price === null) {
                $price = new CryptoPrice();
                $price->crypto_id = $asset->id;
                $price->timeframe = $timeframe;
                $price->bar_time = $bar['bar_time'];
                $price->created_at = $now;
            }

            $price->open = $bar['open'];
            $price->high = $bar['high'];
            $price->low = $bar['low'];
            $price->close = $bar['close'];
            $price->volume = $bar['volume'];
            $price->quote_volume = $bar['quote_volume'];
            $price->buy_volume = $bar['buy_volume'];
            $price->sell_volume = $bar['sell_volume'];

            if ($price->save()) {
                $saved++;
            }
        }

        return $saved;
    }

    /**
     * Hitung analisis volume pressure, RVOL, RSI, MA20/50, dan skor sinyal
     */
    public function analyze(CryptoAsset $asset, string $timeframe = '1h', ?array $ticker24h = null): ?CryptoAnalysis
    {
        /** @var CryptoPrice[] $bars */
        $bars = CryptoPrice::find()
            ->where(['crypto_id' => $asset->id, 'timeframe' => $timeframe])
            ->orderBy(['bar_time' => SORT_ASC])
            ->limit(100)
            ->all();

        $n = count($bars);
        if ($n < 3) {
            return null;
        }

        $latestBar = $bars[$n - 1];
        $currentPrice = (float)$latestBar->close;

        // 1. Perubahan harga 24h
        $priceChange24h = null;
        if ($ticker24h !== null && isset($ticker24h['priceChangePercent'])) {
            $priceChange24h = (float)$ticker24h['priceChangePercent'];
        } else {
            // Estimasi 24 jam ke belakang berdasarkan timeframe
            $lookbackMap = ['15m' => 96, '1h' => 24, '2h' => 12, '4h' => 6, '1d' => 1];
            $barsAgo = $lookbackMap[$timeframe] ?? 24;
            $prevIdx = max(0, $n - 1 - $barsAgo);
            $prevClose = (float)$bars[$prevIdx]->close;
            if ($prevClose > 0) {
                $priceChange24h = round((($currentPrice - $prevClose) / $prevClose) * 100, 2);
            }
        }

        // 2. Buy Ratio: gabungan bar terakhir (bobot 60%) dan rata-rata 3 bar terakhir (bobot 40%)
        $latestBuyRatio = $latestBar->getBuyRatio();
        $sumBuy = 0.0;
        $sumVol = 0.0;
        $sampleCount = min(3, $n);
        for ($i = $n - $sampleCount; $i < $n; $i++) {
            $sumBuy += (float)$bars[$i]->buy_volume;
            $sumVol += (float)$bars[$i]->volume;
        }
        $smoothedBuyRatio = $sumVol > 0 ? (($sumBuy / $sumVol) * 100) : $latestBuyRatio;
        $finalBuyRatio = round(($latestBuyRatio * 0.6) + ($smoothedBuyRatio * 0.4), 2);

        // 3. RVOL (Relative Volume terhadap rata-rata 20 bar sebelumnya)
        $rvol = 1.0;
        $rvolWindow = min(20, $n - 1);
        if ($rvolWindow >= 5) {
            $volSum = 0.0;
            for ($i = $n - 1 - $rvolWindow; $i < $n - 1; $i++) {
                $volSum += (float)$bars[$i]->volume;
            }
            $avgVol = $volSum / $rvolWindow;
            if ($avgVol > 0) {
                $rvol = round(((float)$latestBar->volume) / $avgVol, 2);
            }
        }

        // 4. Moving Average MA20 dan MA50
        $ma20 = $this->calculateSMA($bars, 20);
        $ma50 = $this->calculateSMA($bars, 50);

        // 5. RSI 14
        $rsi = $this->calculateRSI($bars, 14);

        // 6. Kalkulasi Skor Akumulasi (0 - 100)
        $score = 50;

        // Faktor 1: Buying Pressure (Taker Buy Ratio)
        if ($finalBuyRatio >= 75.0) {
            $score += 25;
        } elseif ($finalBuyRatio >= 65.0) {
            $score += 15;
        } elseif ($finalBuyRatio >= 55.0) {
            $score += 8;
        } elseif ($finalBuyRatio <= 35.0) {
            $score -= 25;
        } elseif ($finalBuyRatio <= 45.0) {
            $score -= 15;
        }

        // Faktor 2: RVOL (Volume Spike)
        if ($rvol >= 2.0) {
            $score += 15;
        } elseif ($rvol >= 1.4) {
            $score += 10;
        } elseif ($rvol < 0.6) {
            $score -= 5;
        }

        // Faktor 3: RSI
        if ($rsi !== null) {
            if ($rsi <= 32.0) {
                $score += 15; // Oversold akumulasi
            } elseif ($rsi <= 40.0) {
                $score += 8;
            } elseif ($rsi >= 75.0) {
                $score -= 15; // Overbought rawan koreksi
            } elseif ($rsi >= 68.0) {
                $score -= 5;
            }
        }

        // Faktor 4: Trend MA
        if ($ma20 !== null) {
            if ($currentPrice >= $ma20) {
                $score += 10;
            } else {
                $score -= 10;
            }
        }
        if ($ma20 !== null && $ma50 !== null) {
            if ($ma20 >= $ma50) {
                $score += 5; // Golden cross / bullish alignment
            } else {
                $score -= 5;
            }
        }

        // Clamp 0 - 100
        $score = max(0, min(100, $score));

        // 7. Sinyal
        $signal = CryptoAnalysis::SIGNAL_NEUTRAL;
        if ($score >= 78 && $finalBuyRatio >= 60.0) {
            $signal = CryptoAnalysis::SIGNAL_STRONG_BUY;
        } elseif ($score >= 65 && $finalBuyRatio >= 53.0) {
            $signal = CryptoAnalysis::SIGNAL_BUY;
        } elseif ($score <= 35 || $finalBuyRatio <= 40.0) {
            $signal = CryptoAnalysis::SIGNAL_SELL;
        }

        // Simpan / update ke CryptoAnalysis
        $analysis = CryptoAnalysis::findOne([
            'crypto_id' => $asset->id,
            'timeframe' => $timeframe,
        ]);
        if ($analysis === null) {
            $analysis = new CryptoAnalysis();
            $analysis->crypto_id = $asset->id;
            $analysis->timeframe = $timeframe;
        }

        $analysis->current_price = $currentPrice;
        $analysis->price_change_24h = $priceChange24h;
        $analysis->buy_ratio = $finalBuyRatio;
        $analysis->rvol = $rvol;
        $analysis->rsi = $rsi;
        $analysis->ma20 = $ma20;
        $analysis->ma50 = $ma50;
        $analysis->score = $score;
        $analysis->signal = $signal;
        $analysis->updated_at = time();

        $analysis->save();

        return $analysis;
    }

    /**
     * Hitung SMA dari kumpulan bar
     */
    private function calculateSMA(array $bars, int $period): ?float
    {
        $n = count($bars);
        if ($n < 3) {
            return null;
        }
        $count = min($period, $n);
        $sum = 0.0;
        for ($i = $n - $count; $i < $n; $i++) {
            $sum += (float)$bars[$i]->close;
        }
        return round($sum / $count, 8);
    }

    /**
     * Hitung RSI 14
     */
    private function calculateRSI(array $bars, int $period = 14): ?float
    {
        $n = count($bars);
        if ($n < $period + 1) {
            return null;
        }

        $gains = 0.0;
        $losses = 0.0;

        // Initial average
        for ($i = 1; $i <= $period; $i++) {
            $change = (float)$bars[$i]->close - (float)$bars[$i - 1]->close;
            if ($change >= 0) {
                $gains += $change;
            } else {
                $losses += abs($change);
            }
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        // Smoothed average
        for ($i = $period + 1; $i < $n; $i++) {
            $change = (float)$bars[$i]->close - (float)$bars[$i - 1]->close;
            $gain = $change >= 0 ? $change : 0.0;
            $loss = $change < 0 ? abs($change) : 0.0;

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
        }

        if ($avgLoss == 0.0) {
            return 100.0;
        }

        $rs = $avgGain / $avgLoss;
        $rsi = 100.0 - (100.0 / (1.0 + $rs));
        return round($rsi, 2);
    }

    /**
     * Sinkronisasi dan analisis seluruh koin aktif untuk timeframe tertentu
     */
    public function syncAndAnalyzeAll(string $timeframe = '1h', int $limit = 100, ?callable $feedback = null): array
    {
        $coins = CryptoAsset::find()->where(['is_active' => true])->orderBy(['sort_order' => SORT_ASC])->all();
        if (empty($coins)) {
            $this->refreshCoinList();
            $coins = CryptoAsset::find()->where(['is_active' => true])->orderBy(['sort_order' => SORT_ASC])->all();
        }

        $tickersMap = $this->provider->get24hTickersMap();
        $synced = 0;
        $analyzed = 0;

        foreach ($coins as $coin) {
            $barsCount = $this->syncCoinBars($coin, $timeframe, $limit);
            if ($barsCount > 0) {
                $synced++;
                $ticker = $tickersMap[$coin->symbol] ?? null;
                $analysis = $this->analyze($coin, $timeframe, $ticker);
                if ($analysis !== null) {
                    $analyzed++;
                }
            }

            if ($feedback !== null) {
                $feedback($coin->symbol, $barsCount);
            }
        }

        return [
            'total_coins' => count($coins),
            'synced' => $synced,
            'analyzed' => $analyzed,
            'timeframe' => $timeframe,
        ];
    }
}
