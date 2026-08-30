<?php

/**
 * Functional test: Alert System (task 10.1, 10.5, 10.8).
 *
 * Dev DB = @app/data/stocks.db (fixture NVDA/AAPL/XOM).
 * Membuat user dummy + login via form (pola WatchlistCest), lalu uji CRUD alert
 * dan halaman notifikasi.
 */
class AlertCest
{
    private string $username = '';
    private string $password = 'Password123';

    public function _before(\FunctionalTester $I)
    {
        $user = new \app\models\User();
        $this->username = 'alerttest_' . uniqid();
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
        $I->amOnRoute('alert/index');
        $I->see('Alerts', 'h1');
        $I->see('Buat Alert');
    }

    public function createPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('alert/create');
        $I->see('Buat Alert', 'h1');
        $I->seeElement('form');
    }

    public function createAlertWithValidSymbol(\FunctionalTester $I)
    {
        $I->amOnRoute('alert/create');
        $I->submitForm('#alert-form', [
            'AlertForm[symbol]' => 'NVDA',
            'AlertForm[condition_type]' => 'score',
            'AlertForm[operator]' => '>=',
            'AlertForm[threshold]' => '80',
            'AlertForm[active]' => '1',
        ]);
        $I->amOnRoute('alert/index');
        $I->see('NVDA');
        $I->see('Aktif');
    }

    public function createAlertWithUnknownSymbolFails(\FunctionalTester $I)
    {
        $I->amOnRoute('alert/create');
        $I->submitForm('#alert-form', [
            'AlertForm[symbol]' => 'ZZZZZ',
            'AlertForm[condition_type]' => 'score',
            'AlertForm[operator]' => '>=',
            'AlertForm[threshold]' => '80',
        ]);
        $I->see('tidak ditemukan');
    }

    public function notificationsPageRenders(\FunctionalTester $I)
    {
        $I->amOnRoute('alert/notifications');
        $I->see('Notifikasi Alert', 'h1');
        $I->seeElement('table.table');
    }
}
