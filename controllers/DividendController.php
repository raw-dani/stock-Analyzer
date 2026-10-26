<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\StockDividendSummary;
use app\services\DividendService;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;

/**
 * Controller untuk modul Dividend Hunter (Screener, Kalender Ex-Date, dan Kalkulator Passive Income).
 */
final class DividendController extends Controller
{
    private DividendService $dividendService;

    public function __construct($id, $module, ?DividendService $dividendService = null, $config = [])
    {
        $this->dividendService = $dividendService ?? new DividendService();
        parent::__construct($id, $module, $config);
    }

    /**
     * Halaman Utama Dividend Hunter.
     */
    public function actionIndex(): string
    {
        $request = Yii::$app->request;
        $tab = $request->get('tab', 'screener');

        $filters = [
            'search' => $request->get('search', ''),
            'min_yield' => $request->get('min_yield', ''),
            'max_yield' => $request->get('max_yield', ''),
            'frequency' => $request->get('frequency', 'all'),
            'safety' => $request->get('safety', 'all'),
            'sector' => $request->get('sector', 'all'),
            'sort' => $request->get('sort', 'yield_desc'),
            'include_zero' => (bool) $request->get('include_zero', false),
        ];

        // Ambil data screener
        $screenerResults = $this->dividendService->getScreenerData($filters);

        // Ambil data kalender ex-dividend
        $calendarResults = $this->dividendService->getCalendarData(60);

        // Ambil daftar sektor unik yang membagikan dividen
        $sectors = StockDividendSummary::find()
            ->select('sector')
            ->where(['>', 'dividend_yield', 0])
            ->andWhere(['not', ['sector' => null]])
            ->andWhere(['!=', 'sector', ''])
            ->distinct()
            ->column();
        sort($sectors);

        // Hitung ringkasan statistik
        $totalDividendStocks = StockDividendSummary::find()->where(['>', 'dividend_yield', 0])->count();
        $avgYield = (float) (StockDividendSummary::find()->where(['>', 'dividend_yield', 0])->average('dividend_yield') ?? 0);
        $monthlyPayersCount = StockDividendSummary::find()->where(['payout_frequency' => StockDividendSummary::FREQ_MONTHLY])->count();
        $aristocratsCount = StockDividendSummary::find()->where(['safety_score' => StockDividendSummary::SAFETY_ARISTOCRAT])->count();
        $topYieldStock = StockDividendSummary::find()->where(['>', 'dividend_yield', 0])->orderBy(['dividend_yield' => SORT_DESC])->one();

        // Data awal untuk kalkulator passive income
        $calcCapital = (float) $request->get('capital', 10000.0);
        $calcSymbol = $request->get('symbol', 'O');
        $calcResult = $this->dividendService->calculatePassiveIncome($calcCapital, $calcSymbol);

        // Daftar saham untuk dropdown kalkulator
        $dividendStocksList = StockDividendSummary::find()
            ->select(['symbol', 'company_name', 'dividend_yield', 'current_price', 'payout_frequency'])
            ->where(['>', 'dividend_yield', 0])
            ->orderBy(['dividend_yield' => SORT_DESC])
            ->asArray()
            ->all();

        return $this->render('index', [
            'tab' => $tab,
            'filters' => $filters,
            'screenerResults' => $screenerResults,
            'calendarResults' => $calendarResults,
            'sectors' => $sectors,
            'stats' => [
                'total_dividend_stocks' => (int) $totalDividendStocks,
                'avg_yield' => round($avgYield, 2),
                'monthly_payers_count' => (int) $monthlyPayersCount,
                'aristocrats_count' => (int) $aristocratsCount,
                'top_yield_stock' => $topYieldStock,
            ],
            'calcCapital' => $calcCapital,
            'calcSymbol' => $calcSymbol,
            'calcResult' => $calcResult,
            'dividendStocksList' => $dividendStocksList,
        ]);
    }

    /**
     * AJAX endpoint untuk refresh dividen 1 saham tertentu langsung dari tabel screener.
     */
    public function actionSyncOne(string $symbol): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $result = $this->dividendService->syncStockDividends($symbol);
        return $this->asJson($result);
    }

    /**
     * AJAX endpoint untuk kalkulator passive income dinamis.
     */
    public function actionCalculate(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;
        $capital = (float) $request->post('capital', $request->get('capital', 10000.0));
        $symbol = $request->post('symbol', $request->get('symbol', null));
        $customYield = $request->post('yield', $request->get('yield', null));
        $customYield = $customYield !== null && is_numeric($customYield) ? (float) $customYield : null;

        $result = $this->dividendService->calculatePassiveIncome($capital, $symbol, $customYield);
        return $this->asJson(['success' => true, 'data' => $result]);
    }
}
