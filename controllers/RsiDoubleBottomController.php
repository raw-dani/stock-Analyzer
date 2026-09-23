<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\RsiDoubleBottomService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Scanner Double Bottom berbasis seri RSI — TF 1H, 2H, 4H, 1D.
 * Mirror DoubleBottomController tapi pakai RsiDoubleBottomService.
 */
final class RsiDoubleBottomController extends Controller
{
    /**
     * Halaman index — tampilkan hasil scan RSI Double Bottom.
     * Parameter GET (diambil dari params.rsiDefaults sebagai default):
     *   timeframe  : 1|2|4|24  (1H/2H/4H/1D)
     *   lookback   : jumlah candle (default 150)
     *   tolerance  : poin RSI selisih maks (default 3.0)
     *   rsiPeriod  : periode Wilder (default 14)
     *   maxRsi     : ambang bawah lembah (default 40)
     */
    public function actionIndex(
        ?int $timeframe = null,
        ?int $lookback = null,
        ?float $tolerance = null,
        ?int $rsiPeriod = null,
        ?float $maxRsi = null
    ): string {
        $defaults = Yii::$app->params['rsiDefaults'] ?? [];
        $timeframe       = $timeframe       ?? self::TIMEFRAME_4H;
        $lookback        = $lookback        ?? ($defaults['lookback']        ?? 150);
        $tolerance       = $tolerance       ?? ($defaults['tolerance']       ?? 3.0);
        $rsiPeriod       = $rsiPeriod       ?? ($defaults['rsiPeriod']       ?? 14);
        $maxRsi          = $maxRsi          ?? ($defaults['maxRsiForBottom'] ?? 40.0);
        $minSeparation   = $defaults['minSeparation'] ?? 5;
        $maxSeparation   = $defaults['maxSeparation'] ?? 30;
        $necklineMin     = $defaults['necklineMin'] ?? 2.0;

        $timeframes = self::getTimeframes();
        if (!isset($timeframes[$timeframe])) {
            $timeframe = self::TIMEFRAME_4H;
        }

        $logCat = $defaults['logCategory'] ?? 'app\services\rsidoublebottom';
        Yii::info("RSI Double Bottom Scan: timeframe={$timeframe} lookback={$lookback} tolerance={$tolerance} period={$rsiPeriod} maxRsi={$maxRsi}", $logCat);

        $service = new RsiDoubleBottomService();
        try {
            $results = $service->scanAll($timeframe, $lookback, $tolerance, $rsiPeriod, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        } catch (\InvalidArgumentException $e) {
            Yii::warning("RSI Double Bottom Scan: {$e->getMessage()}", $logCat);
            $lookback = $defaults['lookback'] ?? 150;
            $tolerance = $defaults['tolerance'] ?? 3.0;
            $rsiPeriod = $defaults['rsiPeriod'] ?? 14;
            $maxRsi = $defaults['maxRsiForBottom'] ?? 40.0;
            $minSeparation = $defaults['minSeparation'] ?? 5;
            $maxSeparation = $defaults['maxSeparation'] ?? 30;
            $necklineMin = $defaults['necklineMin'] ?? 2.0;
            $results = $service->scanAll($timeframe, $lookback, $tolerance, $rsiPeriod, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        }

        Yii::info("RSI Double Bottom Scan: found " . count($results) . " patterns", $logCat);

        return $this->render('index', [
            'results'     => $results,
            'timeframe'   => $timeframe,
            'lookback'    => $lookback,
            'tolerance'   => $tolerance,
            'rsiPeriod'   => $rsiPeriod,
            'maxRsi'      => $maxRsi,
            'minSeparation' => $minSeparation,
            'maxSeparation' => $maxSeparation,
            'necklineMin'   => $necklineMin,
            'timeframes'  => $timeframes,
        ]);
    }

    /**
     * Halaman detail satu simbol — tampilkan pola RSI Double Bottom terbesar.
     */
    public function actionDetail(string $symbol): string
    {
        $defaults = Yii::$app->params['rsiDefaults'] ?? [];
        $timeframe = (int) Yii::$app->request->get('timeframe', self::TIMEFRAME_4H);
        $lookback  = (int) Yii::$app->request->get('lookback',  $defaults['lookback']   ?? 150);
        $tolerance = (float) Yii::$app->request->get('tolerance', $defaults['tolerance'] ?? 3.0);
        $rsiPeriod = (int) Yii::$app->request->get('rsiPeriod', $defaults['rsiPeriod']  ?? 14);
        $maxRsi    = (float) Yii::$app->request->get('maxRsi',    $defaults['maxRsiForBottom'] ?? 40.0);
        $minSeparation = $defaults['minSeparation'] ?? 5;
        $maxSeparation = $defaults['maxSeparation'] ?? 30;
        $necklineMin = $defaults['necklineMin'] ?? 2.0;

        $timeframes = self::getTimeframes();
        if (!isset($timeframes[$timeframe])) {
            $timeframe = self::TIMEFRAME_4H;
        }

        $stock = \app\models\Stock::find()->where(['symbol' => $symbol])->one();
        if ($stock === null) {
            throw new NotFoundHttpException("Stock {$symbol} not found.");
        }

        $service = new RsiDoubleBottomService();
        try {
            $pattern = $service->detectPattern($stock->id, $timeframe, $lookback, $tolerance, $rsiPeriod, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        } catch (\InvalidArgumentException $e) {
            Yii::warning("RSI Double Bottom detail {$symbol}: {$e->getMessage()}", $defaults['logCategory'] ?? 'app\services\rsidoublebottom');
            $timeframe = self::TIMEFRAME_4H;
            $lookback = $defaults['lookback'] ?? 150;
            $tolerance = $defaults['tolerance'] ?? 3.0;
            $rsiPeriod = $defaults['rsiPeriod'] ?? 14;
            $maxRsi = $defaults['maxRsiForBottom'] ?? 40.0;
            $minSeparation = $defaults['minSeparation'] ?? 5;
            $maxSeparation = $defaults['maxSeparation'] ?? 30;
            $necklineMin = $defaults['necklineMin'] ?? 2.0;
            $pattern = $service->detectPattern($stock->id, $timeframe, $lookback, $tolerance, $rsiPeriod, $maxRsi, $minSeparation, $maxSeparation, $necklineMin);
        }

        return $this->render('detail', [
            'stock'     => $stock,
            'pattern'   => $pattern,
            'timeframe' => $timeframe,
            'lookback'  => $lookback,
            'tolerance' => $tolerance,
            'rsiPeriod' => $rsiPeriod,
            'maxRsi'    => $maxRsi,
            'minSeparation' => $minSeparation,
            'maxSeparation' => $maxSeparation,
            'necklineMin'   => $necklineMin,
            'timeframes'=> $timeframes,
        ]);
    }

    public const TIMEFRAME_1H = RsiDoubleBottomService::TIMEFRAME_1H;
    public const TIMEFRAME_2H = RsiDoubleBottomService::TIMEFRAME_2H;
    public const TIMEFRAME_4H = RsiDoubleBottomService::TIMEFRAME_4H;
    public const TIMEFRAME_1D = RsiDoubleBottomService::TIMEFRAME_1D;

    public static function getTimeframes(): array
    {
        return RsiDoubleBottomService::getTimeframes();
    }
}
