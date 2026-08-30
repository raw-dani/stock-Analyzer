<?php

declare(strict_types=1);

namespace app\controllers;

use app\assets\ChartAsset;
use app\models\form\SignalFilterForm;
use app\models\Signal;
use app\models\Stock;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Sort;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Signal History web (task 9.1–9.3).
 * Catatan: commands/SignalController.php adalah console command `signal/scan`
 * (namespace app\commands) — tidak bentrok dengan controller web ini.
 */
final class SignalController extends Controller
{
    /**
     * Histori sinyal dengan filter tanggal/simbol/tipe + GridView, pagination, sort (task 9.1–9.2).
     */
    public function actionIndex(): string
    {
        $form = new SignalFilterForm();

        $query = Yii::$app->request->get();
        // Buang field kosong agar filter tidak ikut
        $query = array_filter($query, fn ($v) => $v !== '' && $v !== null);
        $form->load($query, '');

        $dataProvider = $this->buildProvider($form);

        return $this->render('index', [
            'searchModel' => $form,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Detail histori per simbol: perkembangan buying pressure + mini chart (task 9.3).
     */
    public function actionSymbol(string $symbol): string
    {
        $stock = Stock::find()
            ->where(['symbol' => strtoupper($symbol), 'active' => true])
            ->one();
        if ($stock === null) {
            throw new NotFoundHttpException("Symbol {$symbol} tidak ditemukan.");
        }

        $dataProvider = new ActiveDataProvider([
            'query' => Signal::find()
                ->where(['stock_id' => $stock->id])
                ->orderBy(['date' => SORT_DESC]),
            'pagination' => ['pageSize' => 15],
        ]);

        // Data seri untuk mini chart: buy_ratio & score per tanggal sinyal
        $rows = Signal::find()
            ->where(['stock_id' => $stock->id])
            ->orderBy(['date' => SORT_ASC])
            ->asArray()
            ->all();

        ChartAsset::register(Yii::$app->view);

        return $this->render('symbol', [
            'stock' => $stock,
            'dataProvider' => $dataProvider,
            'chartDates' => array_column($rows, 'date'),
            'chartBuyRatio' => array_map(fn ($r) => round((float) $r['buy_ratio'], 4), $rows),
            'chartScores' => array_map(fn ($r) => (int) $r['score'], $rows),
        ]);
    }

    private function buildProvider(SignalFilterForm $form): ActiveDataProvider
    {
        $query = Signal::find()
            ->joinWith(['stock'])
            ->andWhere(['{{%stock}}.active' => true]);

        if (!empty($form->symbol)) {
            $query->andFilterWhere(['like', '{{%stock}}.symbol', $form->symbol]);
        }
        $query->andFilterWhere(['{{%signal}}.signal' => $form->signal]);
        $query->andFilterWhere(['>=', '{{%signal}}.date', $form->dateFrom]);
        $query->andFilterWhere(['<=', '{{%signal}}.date', $form->dateTo]);

        $sort = new Sort([
            'attributes' => [
                'date' => ['asc' => ['{{%signal}}.date' => SORT_ASC], 'desc' => ['{{%signal}}.date' => SORT_DESC], 'default' => SORT_DESC],
                'symbol' => ['asc' => ['{{%stock}}.symbol' => SORT_ASC], 'desc' => ['{{%stock}}.symbol' => SORT_DESC], 'default' => SORT_ASC],
                'price' => ['asc' => ['{{%signal}}.price' => SORT_ASC], 'desc' => ['{{%signal}}.price' => SORT_DESC], 'default' => SORT_DESC],
                'score' => ['asc' => ['{{%signal}}.score' => SORT_ASC], 'desc' => ['{{%signal}}.score' => SORT_DESC], 'default' => SORT_DESC],
                'signal' => ['asc' => ['{{%signal}}.signal' => SORT_ASC], 'desc' => ['{{%signal}}.signal' => SORT_DESC], 'default' => SORT_ASC],
                'buy_ratio' => ['asc' => ['{{%signal}}.buy_ratio' => SORT_ASC], 'desc' => ['{{%signal}}.buy_ratio' => SORT_DESC], 'default' => SORT_DESC],
                'rvol' => ['asc' => ['{{%signal}}.rvol' => SORT_ASC], 'desc' => ['{{%signal}}.rvol' => SORT_DESC], 'default' => SORT_DESC],
            ],
            'defaultOrder' => ['date' => SORT_DESC],
        ]);

        return new ActiveDataProvider([
            'query' => $query,
            'sort' => $sort,
            'pagination' => ['pageSize' => 20],
        ]);
    }
}
