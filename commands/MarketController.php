<?php

declare(strict_types=1);

namespace app\commands;

use app\models\Stock;
use app\services\MarketDataService;
use app\services\market\DataProviderInterface;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * php yii market/refresh-stocklist — refresh daftar simbol dari provider.
 * Untuk CSV provider: daftar simbol = file CSV di data/csv/.
 */
final class MarketController extends Controller
{
    public function actionRefreshStocklist(): int
    {
        /** @var DataProviderInterface $provider */
        $provider = Yii::$app->get('marketData')->get();
        $list = $provider->getStockList();

        if ($list === []) {
            $this->stdout("Provider tidak menyediakan stock list. Seed manual / CSV.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $new = 0;
        foreach ($list as $item) {
            $symbol = MarketDataService::normalizeSymbol($item['symbol']);
            $stock = Stock::findOne(['symbol' => $symbol]);
            if ($stock === null) {
                $stock = new Stock();
                $stock->symbol = $symbol;
                $stock->name = $item['name'] ?? $symbol;
                $stock->exchange = $item['exchange'] ?? 'NASDAQ';
                $stock->sector = $item['sector'] ?? null;
                $stock->industry = $item['industry'] ?? null;
                $stock->market_cap = $item['market_cap'] ?? null;
                $stock->save(false);
                $new++;
            } else {
                $stock->name = $item['name'] ?? $stock->name;
                $stock->exchange = $item['exchange'] ?? $stock->exchange;
                $stock->sector = $item['sector'] ?? $stock->sector;
                $stock->industry = $item['industry'] ?? $stock->industry;
                $stock->market_cap = $item['market_cap'] ?? $stock->market_cap;
                // Reaktivasi: simbol yang sempat dinonaktifkan tapi muncul lagi
                // di list provider harus aktif kembali agar ikut fetch/analyze/scan.
                $stock->active = true;
                $stock->save(false);
            }
        }

        $providerSymbols = array_map(fn ($item) => MarketDataService::normalizeSymbol($item['symbol']), $list);
        $deactivated = Stock::updateAll(
            ['active' => false],
            ['and', ['active' => true], ['not', ['symbol' => $providerSymbols]]]
        );

        $this->stdout("Stock list refreshed: {$new} new, " . (count($list) - $new) . " updated, {$deactivated} deactivated\n", Console::FG_GREEN);
        return ExitCode::OK;
    }
}
