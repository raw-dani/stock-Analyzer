<?php

declare(strict_types=1);

/**
 * Functional test: Fibonacci Retracement & Extension Scanner & Precision Trade Levels.
 */
class FibonacciCest
{
    public function fibonacciPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('fibonacci/index');
        $I->see('Fibonacci', 'h1');
        $I->seeElement('table#fib-grid-table');
        $I->see('Golden Pocket');
    }

    public function filterByGoldenPocket(\FunctionalTester $I)
    {
        $I->amOnRoute('fibonacci/index', ['strategy' => 'golden_pocket']);
        $I->see('Fibonacci', 'h1');
        $I->seeElement('table#fib-grid-table');
    }

    public function filterByTargetReached(\FunctionalTester $I)
    {
        $I->amOnRoute('fibonacci/index', ['strategy' => 'target_reached']);
        $I->see('Fibonacci', 'h1');
        $I->seeElement('table#fib-grid-table');
    }

    public function filterByTimeframe4H(\FunctionalTester $I)
    {
        $I->amOnRoute('fibonacci/index', ['timeframe' => 4]);
        $I->see('Fibonacci', 'h1');
        $I->seeElement('table#fib-grid-table');
    }

    public function filterByTimeframe2H(\FunctionalTester $I)
    {
        $I->amOnRoute('fibonacci/index', ['timeframe' => 2]);
        $I->see('Fibonacci', 'h1');
        $I->seeElement('table#fib-grid-table');
    }

    public function filterByTimeframe1H(\FunctionalTester $I)
    {
        $I->amOnRoute('fibonacci/index', ['timeframe' => 1]);
        $I->see('Fibonacci', 'h1');
        $I->seeElement('table#fib-grid-table');
    }

    public function detailPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('fibonacci/detail', ['symbol' => 'NVDA']);
        $I->see('NVDA', 'h1');
        $I->see('Posisi Beli (Entry)');
        $I->see('Stop Loss');
        $I->see('TP1');
        $I->see('161.8%');
    }

    public function detailPageTimeframe2H(\FunctionalTester $I)
    {
        $I->amOnRoute('fibonacci/detail', ['symbol' => 'NVDA', 'timeframe' => 2]);
        $I->see('NVDA', 'h1');
        $I->see('Posisi Beli (Entry)');
    }

    public function exportCsvWorks(\FunctionalTester $I)
    {
        $I->amOnRoute('fibonacci/export');
        $I->seeResponseCodeIs(200);
        $I->see('Simbol');
    }
}
