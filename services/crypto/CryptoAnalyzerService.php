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
    public function syncCoinBars(CryptoAsset $asset, string $timeframe = '1h', int $limit = 200): int
    {
        $bars = $this->provider->getKlines($asset->symbol, $timeframe, max($limit, 200));
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
     * Hitung analisis volume pressure, RVOL, RSI, MA20/50/200, dan skor sinyal presisi
     */
    public function analyze(CryptoAsset $asset, string $timeframe = '1h', ?array $ticker24h = null): ?CryptoAnalysis
    {
        /** @var CryptoPrice[] $bars */
        $bars = CryptoPrice::find()
            ->where(['crypto_id' => $asset->id, 'timeframe' => $timeframe])
            ->orderBy(['bar_time' => SORT_ASC])
            ->limit(200)
            ->all();

        $n = count($bars);
        if ($n < 3) {
            return null;
        }

        $latestBar = $bars[$n - 1];
        $prevBar = $bars[$n - 2];
        $currentPrice = (float)$latestBar->close;
        $isLatestGreen = $currentPrice >= (float)$latestBar->open;

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

        // 4. Moving Average MA20, MA50, MA200
        $ma20 = $this->calculateSMA($bars, 20);
        $ma50 = $this->calculateSMA($bars, 50);
        $ma200 = $n >= 80 ? $this->calculateSMA($bars, min(200, $n)) : null;

        // 5. RSI 14 (Current & Previous untuk deteksi Hook/Reversal)
        $rsiSeries = $this->calculateRsiSeries($bars, 14);
        $rsi = !empty($rsiSeries) ? end($rsiSeries) : null;
        $prevRsi = count($rsiSeries) >= 2 ? $rsiSeries[count($rsiSeries) - 2] : $rsi;

        // 6. Kalkulasi Skor Akumulasi Presisi (0 - 100)
        $score = 50;

        // Faktor 1: Taker Buying Pressure (Orderflow Real Binance)
        if ($finalBuyRatio >= 72.0) {
            $score += 22;
        } elseif ($finalBuyRatio >= 62.0) {
            $score += 14;
        } elseif ($finalBuyRatio >= 54.0) {
            $score += 7;
        } elseif ($finalBuyRatio <= 36.0) {
            $score -= 22; // Dominasi seller agresif
        } elseif ($finalBuyRatio <= 44.0) {
            $score -= 12;
        }

        // Faktor 2: RVOL Directional (Volume Spike Terarah)
        // PENTING REAL TRADING: Volume tinggi saat harga jatuh marun adalah PANIC SELL / DUMP, bukan akumulasi!
        if ($rvol >= 1.4) {
            if ($finalBuyRatio >= 58.0 && $isLatestGreen) {
                $score += ($rvol >= 2.0 ? 16 : 10); // Akumulasi buyer bervolume tinggi
            } elseif ($finalBuyRatio <= 44.0 && !$isLatestGreen) {
                $score -= ($rvol >= 2.0 ? 20 : 12); // Panic dumping / distribusi institusi
            } else {
                $score += 4; // Volume churn / perebutan harga
            }
        } elseif ($rvol < 0.6) {
            $score -= 5; // Volume sepi / minat rendah
        }

        // Faktor 3: RSI Reversal vs Falling Knife Protection
        if ($rsi !== null) {
            if ($rsi <= 32.0) {
                // Jangan beli pisau jatuh: RSI oversold hanya positif jika harga mulai berbalik naik (Hook)
                if ($rsi > $prevRsi || $isLatestGreen || $finalBuyRatio >= 55.0) {
                    $score += 14; // Reversal / Absorption bounce terkonfirmasi
                } else {
                    $score -= 8; // Masih dalam tren jatuh bebas (falling knife)
                }
            } elseif ($rsi <= 40.0 && ($rsi > $prevRsi || $isLatestGreen)) {
                $score += 8;
            } elseif ($rsi >= 75.0) {
                $score -= 16; // Overbought ekstrem rawan koreksi tajam
            } elseif ($rsi >= 68.0) {
                $score -= 7;
            }
        }

        // Faktor 4: Trend Regime (MA20, MA50, MA200)
        if ($ma20 !== null) {
            if ($currentPrice >= $ma20 * 0.998) {
                $score += 10;
            } else {
                $score -= 10;
            }
        }
        if ($ma20 !== null && $ma50 !== null) {
            if ($ma20 >= $ma50) {
                $score += 8; // Golden cross / Bullish alignment
            } else {
                $score -= 8; // Death cross / Bearish alignment
            }
        }
        // Filter Pasar Bearish Makro: Di bawah MA200 kurangi skor agar tidak menangkap koin yang sedang downtrend besar
        if ($ma200 !== null && $currentPrice < $ma200 * 0.98) {
            $score -= 10;
        }

        // Clamp 0 - 100
        $score = max(0, min(100, $score));

        // 7. Sinyal Presisi Real Spot Trading (Tanpa Margin / Leverage)
        $signal = CryptoAnalysis::SIGNAL_NEUTRAL;
        $isDumping = ($rvol >= 1.5 && !$isLatestGreen && $finalBuyRatio <= 44.0);

        if ($score >= 76 && $finalBuyRatio >= 58.0 && !$isDumping && ($ma20 === null || $currentPrice >= $ma20 * 0.985)) {
            $signal = CryptoAnalysis::SIGNAL_STRONG_BUY;
        } elseif ($score >= 64 && $finalBuyRatio >= 52.0 && !$isDumping) {
            $signal = CryptoAnalysis::SIGNAL_BUY;
        } elseif ($score <= 36 || $finalBuyRatio <= 40.0 || $isDumping) {
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
     * Hitung series RSI 14 dari kumpulan bar
     * @return float[]
     */
    private function calculateRsiSeries(array $bars, int $period = 14): array
    {
        $n = count($bars);
        if ($n < $period + 1) {
            return [];
        }

        $series = [];
        $gains = 0.0;
        $losses = 0.0;

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

        $series[] = $avgLoss == 0.0 ? 100.0 : round(100.0 - (100.0 / (1.0 + ($avgGain / $avgLoss))), 2);

        for ($i = $period + 1; $i < $n; $i++) {
            $change = (float)$bars[$i]->close - (float)$bars[$i - 1]->close;
            $gain = $change >= 0 ? $change : 0.0;
            $loss = $change < 0 ? abs($change) : 0.0;

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;

            $rsi = $avgLoss == 0.0 ? 100.0 : round(100.0 - (100.0 / (1.0 + ($avgGain / $avgLoss))), 2);
            $series[] = $rsi;
        }

        return $series;
    }

    /**
     * Hitung RSI 14
     */
    private function calculateRSI(array $bars, int $period = 14): ?float
    {
        $series = $this->calculateRsiSeries($bars, $period);
        return !empty($series) ? end($series) : null;
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
