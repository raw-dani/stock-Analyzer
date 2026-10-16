<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\form\ScanFilterForm;
use app\models\Stock;
use app\services\MarketDataService;
use app\services\ScannerService;
use app\services\SignalEngineService;
use app\services\VolumeAnalyzerService;
use Yii;
use yii\web\Controller;

/**
 * Stock scanner — filter + GridView (task 6.3).
 */
final class ScannerController extends Controller
{
    public function actionIndex(): string
    {
        $form = new ScanFilterForm();

        $query = Yii::$app->request->get();
        $query = array_filter($query, fn ($v) => $v !== '' && $v !== null);
        $form->load($query, '');

        $dataProvider = Yii::$container->get(ScannerService::class)->search($form);

        $stats = [
            'total' => $dataProvider->getTotalCount(),
            'strongBuy' => 0,
            'heavyAccumulation' => 0,
            'avgRvol' => 0.0,
        ];

        $symbols = [];
        $tvSymbols = [];

        try {
            $baseQuery = clone $dataProvider->query;
            if ($form->mode === 'daily') {
                $statDaily = (clone $baseQuery)->asArray()->all();
                $strongBuy = 0;
                $sumBuyRatio = 0.0;
                $validCount = 0;
                foreach ($statDaily as $row) {
                    $b = (float) ($row['buy_volume'] ?? 0);
                    $s = (float) ($row['sell_volume'] ?? 0);
                    $tot = $b + $s;
                    if ($tot > 0) {
                        $ratio = $b / $tot;
                        if ($ratio >= 0.70) {
                            $strongBuy++;
                        }
                        $sumBuyRatio += $ratio;
                        $validCount++;
                    }
                }
                $stats['strongBuy'] = $strongBuy;
                $stats['heavyAccumulation'] = $strongBuy;
                $stats['avgRvol'] = $validCount > 0 ? round(($sumBuyRatio / $validCount) * 100, 1) : 0.0;
            } else {
                $statRow = (clone $baseQuery)
                    ->select([
                        'strongBuy' => new \yii\db\Expression("SUM(CASE WHEN {{%weekly_analysis}}.signal = 'STRONG_BUY' THEN 1 ELSE 0 END)"),
                        'heavyAccumulation' => new \yii\db\Expression('SUM(CASE WHEN {{%weekly_analysis}}.buy_ratio >= 0.70 THEN 1 ELSE 0 END)'),
                        'avgRvol' => new \yii\db\Expression('AVG({{%weekly_analysis}}.rvol)'),
                    ])
                    ->asArray()
                    ->one();

                $stats['strongBuy'] = (int) ($statRow['strongBuy'] ?? 0);
                $stats['heavyAccumulation'] = (int) ($statRow['heavyAccumulation'] ?? 0);
                $stats['avgRvol'] = round((float) ($statRow['avgRvol'] ?? 0), 2);
            }

            // Extract unique symbols for 1-click clipboard copy / TradingView watchlist (dibatasi 500 teratas agar ringan)
            $rows = (clone $baseQuery)->select(['{{%stock}}.symbol', '{{%stock}}.exchange'])->limit(500)->asArray()->all();
            foreach ($rows as $r) {
                if (!empty($r['symbol'])) {
                    $sym = (string) $r['symbol'];
                    $symbols[] = $sym;
                    $exch = !empty($r['exchange']) ? $r['exchange'] . ':' : '';
                    $tvSymbols[] = $exch . $sym;
                }
            }
            $symbols = array_values(array_unique($symbols));
            $tvSymbols = array_values(array_unique($tvSymbols));
        } catch (\Throwable $e) {
            Yii::warning('Scanner stats error: ' . $e->getMessage(), 'app\services\scanner');
        }

        return $this->render('index', [
            'searchModel' => $form,
            'dataProvider' => $dataProvider,
            'stats' => $stats,
            'symbols' => $symbols,
            'tvSymbols' => $tvSymbols,
        ]);
    }

    /**
     * Export hasil filter scanner ke CSV.
     */
    public function actionExport(): \yii\web\Response
    {
        $form = new ScanFilterForm();
        $query = Yii::$app->request->get();
        $query = array_filter($query, fn ($v) => $v !== '' && $v !== null);
        $form->load($query, '');
        $form->limit = 5000;

        $dataProvider = Yii::$container->get(ScannerService::class)->search($form);
        $dataProvider->pagination = false;

        $models = $dataProvider->getModels();
        $isDaily = $form->mode === 'daily';
        $filename = 'stock_scanner_' . ($isDaily ? 'daily' : 'weekly') . '_' . date('Ymd_His') . '.csv';

        $handle = fopen('php://temp', 'r+');
        if ($isDaily) {
            fputcsv($handle, ['Tanggal', 'Simbol', 'Nama Perusahaan', 'Bursa', 'Sektor', 'Harga Close', 'Volume', 'Buy Volume', 'Sell Volume', 'Buy Ratio %']);
            foreach ($models as $m) {
                $total = (int) $m->buy_volume + (int) $m->sell_volume;
                $ratioPct = $total > 0 ? round(($m->buy_volume / $total) * 100, 2) : 0;
                fputcsv($handle, [
                    $m->date,
                    $m->stock->symbol ?? '',
                    $m->stock->name ?? '',
                    $m->stock->exchange ?? '',
                    $m->stock->sector ?? '',
                    $m->close,
                    $m->volume,
                    $m->buy_volume,
                    $m->sell_volume,
                    $ratioPct,
                ]);
            }
        } else {
            fputcsv($handle, ['Tanggal Minggu', 'Simbol', 'Nama Perusahaan', 'Bursa', 'Sektor', 'Harga', 'Market Cap', 'Buy Ratio %', 'RVOL', 'Vol Growth %', 'Skor', 'Sinyal']);
            foreach ($models as $m) {
                fputcsv($handle, [
                    $m->week_start,
                    $m->stock->symbol ?? '',
                    $m->stock->name ?? '',
                    $m->stock->exchange ?? '',
                    $m->stock->sector ?? '',
                    $m->close_price,
                    $m->stock->market_cap ?? '',
                    $m->buy_ratio !== null ? round($m->buy_ratio * 100, 2) : '',
                    $m->rvol,
                    $m->volume_growth !== null ? round($m->volume_growth * 100, 2) : '',
                    $m->score,
                    $m->signal,
                ]);
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return Yii::$app->response->sendContentAsFile($content, $filename, [
            'mimeType' => 'text/csv',
            'inline' => false,
        ]);
    }

    public function actionRunScan(): \yii\web\Response
    {
        $form = new ScanFilterForm();

        $query = Yii::$app->request->get();
        $query = array_filter($query, fn ($v) => $v !== '' && $v !== null);
        $form->load($query, '');

        $dbOk = false;
        try {
            Yii::$app->getDb()->createCommand('SELECT 1')->queryScalar();
            $dbOk = true;
        } catch (\Throwable $e) {
            Yii::error('DB connection failed: ' . $e->getMessage(), 'app\services\scanner');
        }

        if (!$dbOk) {
            Yii::$app->session->setFlash('danger', 'Gagal terhubung ke database. Scan dibatalkan.');
            return $this->redirect(['index', 'exchange' => $form->exchange, 'sector' => $form->sector, 'symbol' => $form->symbol, 'minScore' => $form->minScore, 'minBuyRatio' => $form->minBuyRatio, 'minMarketCap' => $form->minMarketCap, 'maxMarketCap' => $form->maxMarketCap, 'minPrice' => $form->minPrice, 'maxPrice' => $form->maxPrice, 'minVolume' => $form->minVolume, 'signal' => $form->signal]);
        }

        $stockQuery = Stock::find()->where(['active' => true]);

        if ($form->exchange !== null && $form->exchange !== '') {
            $stockQuery->andWhere(['exchange' => $form->exchange]);
        }
        if ($form->sector !== null && $form->sector !== '') {
            $stockQuery->andWhere(['sector' => $form->sector]);
        }
        if ($form->symbol !== null && $form->symbol !== '') {
            $stockQuery->andWhere(['like', 'symbol', $form->symbol]);
        }

        $stocks = $stockQuery->all();
        $stockCount = count($stocks);
        $filterLabel = $form->exchange ?: 'all exchange';

        Yii::info('Berhasil terhubung ke database.', 'app\services\scanner');
        Yii::info("Mencoba koneksi data provider: " . (string) (Yii::$app->params['marketDataProvider'] ?? 'csv'), 'app\services\scanner');
        Yii::info("Scan dimulai: {$stockCount} saham (filter: {$filterLabel})", 'app\services\scanner');

        set_time_limit(0);

        $marketService = new MarketDataService(Yii::$app->get('marketData')->get());
        $analyzer = new VolumeAnalyzerService();
        $engine = new SignalEngineService();

        // Jika user mencari simbol tertentu, lakukan sync simbol tersebut
        $hasSpecificSymbol = !empty($form->symbol);
        if ($hasSpecificSymbol) {
            foreach ($stocks as $stock) {
                try {
                    $marketService->syncSymbol($stock->symbol, $fromDate);
                    $analyzer->analyzeStock($stock->id);
                    $engine->scoreStock($stock);
                } catch (\Throwable $e) {
                    Yii::warning("Sync gagal {$stock->symbol}: " . $e->getMessage(), 'app\services\scanner');
                }
            }
            $flashMsg = "Sinkronisasi & scoring simbol {$form->symbol} berhasil diperbarui.";
        } else {
            // Cek apakah ada bar harian yang belum diklasifikasi buy/sell volume-nya (biasanya bar baru)
            $unclassifiedCount = (int) \app\models\DailyPrice::find()->where(['buy_volume' => null])->count();
            if ($unclassifiedCount > 0) {
                // Klasifikasi hanya bar baru yang belum diproses secara batch langsung via SQL update cepat
                \Yii::$app->db->createCommand("
                    UPDATE {{%daily_price}} 
                    SET buy_volume = CASE 
                        WHEN close > open THEN volume 
                        WHEN close < open THEN 0 
                        ELSE CAST(volume / 2 AS INTEGER) 
                    END,
                    sell_volume = CASE 
                        WHEN close > open THEN 0 
                        WHEN close < open THEN volume 
                        ELSE CAST(volume - CAST(volume / 2 AS INTEGER) AS INTEGER) 
                    END
                    WHERE buy_volume IS NULL
                ")->execute();
            }

            // Agregasi mingguan & scoring untuk semua saham terfilter
            $analyzedCount = 0;
            foreach ($stocks as $stock) {
                try {
                    $analyzer->aggregateWeekly($stock->id);
                    $engine->scoreStock($stock);
                    $analyzedCount++;
                } catch (\Throwable $e) {
                    // ignore individual failure
                }
            }

            $flashMsg = "Scan selesai! Analisis & scoring mingguan berhasil diperbarui untuk {$analyzedCount} saham ({$filterLabel}).";
        }

        Yii::$app->session->setFlash('success', $flashMsg);

        $redirectParams = array_filter([
            'exchange' => $form->exchange,
            'sector' => $form->sector,
            'symbol' => $form->symbol,
            'minScore' => $form->minScore,
            'minBuyRatio' => $form->minBuyRatio,
            'minMarketCap' => $form->minMarketCap,
            'maxMarketCap' => $form->maxMarketCap,
            'minPrice' => $form->minPrice,
            'maxPrice' => $form->maxPrice,
            'minVolume' => $form->minVolume,
            'signal' => $form->signal,
            'mode' => $form->mode,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->redirect(array_merge(['index'], $redirectParams));
    }
}
