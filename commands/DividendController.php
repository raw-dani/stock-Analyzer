<?php

declare(strict_types=1);

namespace app\commands;

use app\services\DividendService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * CLI command untuk sinkronisasi data dividen pasar saham US dari Yahoo Finance.
 *
 * Contoh penggunaan:
 *   php yii dividend/sync JNJ          # Sync 1 saham
 *   php yii dividend/sync-all          # Sync semua saham aktif
 *   php yii dividend/sync-all --limit=25  # Sync 25 saham pertama
 */
final class DividendController extends Controller
{
    public int $limit = 0;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['limit']);
    }

    private DividendService $dividendService;

    public function __construct($id, $module, ?DividendService $dividendService = null, $config = [])
    {
        $this->dividendService = $dividendService ?? new DividendService();
        parent::__construct($id, $module, $config);
    }

    /**
     * Sinkronisasi dividen untuk 1 saham tertentu.
     */
    public function actionSync(string $symbol): int
    {
        $symbol = strtoupper(trim($symbol));
        $this->stdout("Sinkronisasi data dividen untuk {$symbol}...\n", Console::FG_YELLOW);

        $res = $this->dividendService->syncStockDividends($symbol);
        if ($res['success'] ?? false) {
            $this->stdout(sprintf(
                "✓ Sukses! %s | Harga: $%.2f | TTM Div: $%.2f | Yield: %.2f%% | Next Ex-Date: %s (%s)\n",
                $res['symbol'],
                $res['current_price'],
                $res['ttm_dividend'],
                $res['dividend_yield'],
                $res['estimated_next_ex_date'] ?? 'N/A',
                $res['safety_score']
            ), Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stderr("✗ Gagal: " . ($res['message'] ?? 'Error tidak diketahui') . "\n", Console::FG_RED);
        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Sinkronisasi dividen untuk seluruh saham aktif (bisa dibatasi limit).
     */
    public function actionSyncAll(?int $limit = null): int
    {
        $effectiveLimit = $limit ?? ($this->limit > 0 ? $this->limit : null);
        $this->stdout("Memulai sinkronisasi dividen pasar saham aktif" . ($effectiveLimit ? " (Limit: {$effectiveLimit})" : '') . "...\n", Console::FG_CYAN);
        $start = microtime(true);

        $result = $this->dividendService->syncAllActiveStocks($effectiveLimit, function ($curr, $total, $symbol, $res) {
            if ($res['success'] ?? false) {
                $yield = $res['dividend_yield'] ?? 0;
                $color = $yield > 0 ? Console::FG_GREEN : Console::FG_GREY;
                $this->stdout(sprintf(
                    "[%d/%d] %-5s: Yield %5.2f%% | Payouts: %2d | Next: %s\n",
                    $curr,
                    $total,
                    $symbol,
                    $yield,
                    $res['inserted_payouts'] ?? 0,
                    $res['estimated_next_ex_date'] ?? '-'
                ), $color);
            } else {
                $this->stdout(sprintf("[%d/%d] %-5s: Gagal - %s\n", $curr, $total, $symbol, $res['message'] ?? ''), Console::FG_RED);
            }
        });

        $elapsed = round(microtime(true) - $start, 2);
        $this->stdout(sprintf(
            "\nSelesai dalam %.2f detik! Total: %d, Berhasil: %d, Pembayar Dividen: %d, Gagal: %d\n",
            $elapsed,
            $result['total'],
            $result['synced'],
            $result['dividend_payers'],
            $result['failed']
        ), Console::FG_YELLOW);

        return ExitCode::OK;
    }
}
