<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\PortfolioPosition;
use app\models\Stock;
use app\services\PortfolioAveragingService;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller Manajemen Portofolio, Target Keuntungan, dan Strategi Averaging Down.
 */
final class PortfolioController extends Controller
{
    private PortfolioAveragingService $service;

    public function __construct($id, $module, ?PortfolioAveragingService $service = null, $config = [])
    {
        $this->service = $service ?? new PortfolioAveragingService();
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'apply-average' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Dashboard Portofolio & Daftar Posisi Saham.
     */
    public function actionIndex(): string
    {
        $userId = (int) Yii::$app->user->id;
        $tab = Yii::$app->request->get('tab', 'all');

        $query = PortfolioPosition::find()
            ->where(['user_id' => $userId])
            ->with(['stock'])
            ->orderBy(['updated_at' => SORT_DESC]);

        $allPositions = $query->all();

        // Hitung Metrik Portofolio Total
        $totalInvested = 0.0;
        $currentValue = 0.0;
        $profitCount = 0;
        $lossCount = 0;

        foreach ($allPositions as $pos) {
            $totalInvested += $pos->getTotalCost();
            $currentValue += $pos->getCurrentValue();
            if ($pos->isProfit()) {
                $profitCount++;
            } elseif ($pos->isLoss()) {
                $lossCount++;
            }
        }

        $totalPlAmount = round($currentValue - $totalInvested, 2);
        $totalPlPct = $totalInvested > 0 ? round(($totalPlAmount / $totalInvested) * 100, 2) : 0.0;

        // Filter tampilan berdasarkan tab
        $positions = match ($tab) {
            'profit' => array_filter($allPositions, fn($p) => $p->isProfit()),
            'loss' => array_filter($allPositions, fn($p) => $p->isLoss()),
            default => $allPositions,
        };

        // Ambil daftar simbol saham aktif untuk modal tambah
        $availableStocks = Stock::find()
            ->where(['active' => true])
            ->orderBy(['symbol' => SORT_ASC])
            ->all();

        $newPosition = new PortfolioPosition([
            'target_profit_pct' => 3.00,
            'buy_date' => date('Y-m-d'),
        ]);

        return $this->render('index', [
            'positions' => $positions,
            'allCount' => count($allPositions),
            'profitCount' => $profitCount,
            'lossCount' => $lossCount,
            'totalInvested' => $totalInvested,
            'currentValue' => $currentValue,
            'totalPlAmount' => $totalPlAmount,
            'totalPlPct' => $totalPlPct,
            'tab' => $tab,
            'availableStocks' => $availableStocks,
            'newPosition' => $newPosition,
        ]);
    }

    /**
     * Tambah Posisi Saham Baru ke Portofolio.
     */
    public function actionCreate(): Response
    {
        $userId = (int) Yii::$app->user->id;
        $post = Yii::$app->request->post('PortfolioPosition', []);
        $symbol = strtoupper(trim((string) Yii::$app->request->post('symbol', '')));

        if (!empty($symbol)) {
            $stock = Stock::find()->where(['symbol' => $symbol, 'active' => true])->one();
            if ($stock !== null) {
                $post['stock_id'] = $stock->id;
            }
        }

        $model = new PortfolioPosition();
        $model->load($post, '');
        $model->user_id = $userId;

        if ($model->save()) {
            Yii::$app->session->setFlash('success', "Posisi saham {$model->stock->symbol} berhasil ditambahkan ke portofolio.");
        } else {
            $errors = implode(', ', $model->getFirstErrors());
            Yii::$app->session->setFlash('danger', "Gagal menambahkan posisi: {$errors}");
        }

        return $this->redirect(['index']);
    }

    /**
     * Update Data Posisi Saham.
     */
    public function actionUpdate(int $id): Response
    {
        $model = $this->findModel($id);
        $request = Yii::$app->request;
        $loaded = $model->load($request->post());

        if (!$loaded) {
            $post = $request->post('PortfolioPosition', []);
            $loaded = $model->load($post, '');
        }

        if ($loaded && $model->save()) {
            Yii::$app->session->setFlash('success', "Perubahan posisi {$model->stock->symbol} berhasil disimpan.");
        } else {
            $errors = !empty($model->getFirstErrors()) ? implode(', ', $model->getFirstErrors()) : 'Data tidak valid atau tidak ada perubahan yang dikirim.';
            Yii::$app->session->setFlash('danger', "Gagal memperbarui posisi: {$errors}");
        }

        return $this->redirect(['detail', 'id' => $id]);
    }

    /**
     * Hapus Posisi Saham dari Portofolio.
     */
    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        $symbol = $model->stock->symbol ?? 'Saham';
        $model->delete();

        Yii::$app->session->setFlash('success', "Posisi {$symbol} berhasil dihapus dari portofolio.");
        return $this->redirect(['index']);
    }

    /**
     * Halaman Detail: Rencana Target Untung & Simulasi Strategi Averaging Down Multi-Fitur.
     */
    public function actionDetail(int $id): string
    {
        $position = $this->findModel($id);
        $stock = $position->stock;

        // Analisis Multi-Fitur (Candlestick, Double/Triple Bottom, RSI, Moving Average, Fibonacci, Confluence)
        $multiAnalysis = $this->service->getMultiFeatureAnalysis($stock);

        // Simulasi Skenario Averaging Down
        $dcaData = $this->service->calculateDcaScenarios($position, $multiAnalysis['best_support_price'] ?? null);

        // Riwayat Penerapan Skenario
        $histories = $position->getHistoriesList();

        return $this->render('detail', [
            'position' => $position,
            'stock' => $stock,
            'multiAnalysis' => $multiAnalysis,
            'dcaData' => $dcaData,
            'histories' => $histories,
        ]);
    }

    /**
     * Terapkan Hasil Averaging Down ke Posisi Portofolio (Update Average Buy Price & Shares).
     */
    public function actionApplyAverage(int $id): Response
    {
        $position = $this->findModel($id);
        $req = Yii::$app->request;

        $newAvgPrice = (float) $req->post('new_avg_price', 0);
        $newTotalShares = (float) $req->post('new_total_shares', 0);
        $scenarioName = (string) $req->post('scenario_name', 'Averaging Down');
        $addPrice = (float) $req->post('add_price', 0);
        $addShares = (float) $req->post('add_shares', 0);
        $addCost = (float) $req->post('add_cost', 0);
        $notes = (string) $req->post('notes', '');

        if ($newAvgPrice > 0 && $newTotalShares > 0) {
            $oldPrice = (float) $position->buy_price;
            $oldShares = (float) $position->shares;

            if ($addShares <= 0) {
                $addShares = max(0, $newTotalShares - $oldShares);
            }
            if ($addCost <= 0 && $addPrice > 0) {
                $addCost = $addPrice * $addShares;
            }

            $position->buy_price = $newAvgPrice;
            $position->shares = $newTotalShares;
            if (!empty($notes)) {
                $position->notes = trim($position->notes . "\n[Avg Down " . date('Y-m-d') . "] " . $notes);
            }

            if ($position->save()) {
                // Catat log riwayat eksekusi skenario
                try {
                    $history = new \app\models\PortfolioHistory([
                        'position_id' => $position->id,
                        'user_id' => (int) $position->user_id,
                        'action_type' => \app\models\PortfolioHistory::ACTION_AVERAGE_DOWN,
                        'scenario_name' => $scenarioName,
                        'old_price' => $oldPrice,
                        'new_price' => $newAvgPrice,
                        'old_shares' => $oldShares,
                        'added_shares' => $addShares,
                        'new_shares' => $newTotalShares,
                        'added_price' => $addPrice > 0 ? $addPrice : null,
                        'added_cost' => $addCost > 0 ? $addCost : null,
                        'notes' => $notes,
                    ]);
                    $history->save();
                } catch (\Throwable $e) {
                    Yii::warning('Failed saving portfolio history: ' . $e->getMessage());
                }

                Yii::$app->session->setFlash('success', sprintf(
                    "Berhasil menerapkan skenario '%s' untuk %s! Harga rata-rata modal turun dari $%s menjadi $%s (Total: %s lembar).",
                    $scenarioName,
                    $position->stock->symbol,
                    number_format($oldPrice, 2),
                    number_format($newAvgPrice, 2),
                    PortfolioPosition::formatShares($newTotalShares)
                ));
            } else {
                Yii::$app->session->setFlash('danger', 'Gagal menerapkan averaging down: ' . implode(', ', $position->getFirstErrors()));
            }
        }

        return $this->redirect(['detail', 'id' => $id]);
    }

    /**
     * Refresh Harga Pasar Terkini Real-Time dari Yahoo Finance.
     */
    public function actionRefreshPrices(?int $id = null): Response
    {
        if ($id !== null) {
            $pos = $this->findModel($id);
            $newPrice = $pos->getCurrentPrice(true);
            $quote = $pos->getLiveQuoteDetails(true);
            $changeStr = $quote ? sprintf(" (%s%.2f%%)", $quote['change_pct'] >= 0 ? '+' : '', $quote['change_pct']) : '';
            Yii::$app->session->setFlash('success', "Harga real-time {$pos->stock->symbol} berhasil diperbarui: $" . number_format($newPrice, 2) . $changeStr);
            return $this->redirect(['detail', 'id' => $id]);
        }

        $userId = (int) Yii::$app->user->id;
        $positions = PortfolioPosition::find()->where(['user_id' => $userId])->all();
        $updated = 0;
        foreach ($positions as $pos) {
            $pos->getCurrentPrice(true);
            $updated++;
        }

        Yii::$app->session->setFlash('success', "Berhasil menyinkronkan harga real-time untuk {$updated} posisi saham di portofolio.");
        return $this->redirect(['index']);
    }

    /**
     * Endpoint JSON Simulasi Averaging Kustom secara Dinamis.
     */
    public function actionSimulateDca(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $position = $this->findModel($id);
        $addPrice = (float) Yii::$app->request->get('addPrice', $position->getCurrentPrice());
        $addShares = (float) Yii::$app->request->get('addShares', $position->shares);

        return $this->service->calculateCustomAverage($position, $addPrice, $addShares);
    }

    private function findModel(int $id): PortfolioPosition
    {
        $userId = (int) Yii::$app->user->id;
        $model = PortfolioPosition::find()
            ->where(['id' => $id, 'user_id' => $userId])
            ->with(['stock'])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException("Posisi portofolio tidak ditemukan.");
        }

        return $model;
    }
}
