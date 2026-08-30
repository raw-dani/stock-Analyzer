<?php

/**
 * Functional test: Backtesting web (task 11.5).
 *
 * Dev DB = @app/data/stocks.db (fixture NVDA/AAPL/XOM, 3×120 hari + weekly_analysis).
 * Form submit menjalankan backtest nyata (menulis backtest_run + backtest_trade).
 */
class BacktestCest
{
    private string $username = '';
    private string $password = 'Password123';

    public function _before(\FunctionalTester $I)
    {
        $user = new \app\models\User();
        $this->username = 'bttest_' . uniqid();
        $user->username = $this->username;
        $user->email = $this->username . '@example.com';
        $user->status = \app\models\User::STATUS_ACTIVE;
        $user->setPassword($this->password);
        $user->generateAuthKey();
        $user->save(false);

        $I->amOnRoute('site/login');
        $I->submitForm('#login-form', [
            'LoginForm[username]' => $this->username,
            'LoginForm[password]' => $this->password,
        ]);
        $I->see('Logout', 'form');
    }

    public function indexPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('backtest/index');
        $I->see('Backtesting', 'h1');
        $I->see('Jalankan Backtest');
    }

    public function createPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('backtest/create');
        $I->see('Jalankan Backtest', 'h1');
        $I->seeElement('#backtest-form');
    }

    public function runBacktestProducesTrades(\FunctionalTester $I)
    {
        $I->amOnRoute('backtest/create');
        $I->submitForm('#backtest-form', [
            'BacktestForm[name]' => 'test-run',
            'BacktestForm[minBuyRatio]' => '0.5',
            'BacktestForm[holdingDays]' => '5',
        ]);
        $I->see('Daftar Trades');
        $I->seeElement('table');
    }

    public function runListShowsCreatedRun(\FunctionalTester $I)
    {
        $I->amOnRoute('backtest/create');
        $I->submitForm('#backtest-form', [
            'BacktestForm[name]' => 'listed-run',
            'BacktestForm[holdingDays]' => '10',
        ]);
        $I->amOnRoute('backtest/index');
        $I->see('Backtesting', 'h1');
        $I->see('listed-run');
    }
}
