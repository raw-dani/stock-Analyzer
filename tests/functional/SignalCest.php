<?php

/**
 * Functional test: signal history (task 9.1–9.3).
/**
 * Functional test: signal history (task 9.1–9.3).
 *
 * Memakai SQLite dev DB (@app/data/stocks.db) yang sudah berisi
 * fixture NVDA/AAPL/XOM + histori signal. Test bersifat read-only (hanya GET).
 */
class SignalCest
{
    public function historyPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('signal/index');
        $I->see('Signal History', 'h1');
        $I->seeElement('table.table');
        $I->seeElement('tbody tr');
    }

    public function filterBySignal(\FunctionalTester $I)
    {
        $I->amOnRoute('signal/index', ['signal' => 'STRONG_BUY']);
        $I->see('Signal History', 'h1');
        $I->seeElement('table.table');
        $I->see('STRONG BUY');
    }

    public function filterByDateRange(\FunctionalTester $I)
    {
        $I->amOnRoute('signal/index', ['dateFrom' => '2026-01-01', 'dateTo' => '2026-12-31']);
        $I->see('Signal History', 'h1');
        $I->seeElement('table.table');
    }

    public function filterBySymbol(\FunctionalTester $I)
    {
        $I->amOnRoute('signal/index', ['symbol' => 'NVDA']);
        $I->see('Signal History', 'h1');
        $I->seeElement('table.table');
        $I->see('NVDA');
    }

    public function sortToggleWorks(\FunctionalTester $I)
    {
        $I->amOnRoute('signal/index', ['sort' => 'score']);
        $I->see('Signal History', 'h1');
        $I->seeElement('table.table');
        $I->seeElement('tbody tr');
    }

    public function symbolHistoryPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('signal/symbol', ['symbol' => 'NVDA']);
        $I->see('NVDA', 'h1');
        $I->seeElement('table.table');
        $I->seeElement('#chart-signal-history');
    }
}
