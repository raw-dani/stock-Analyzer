# US Stock Volume Analyzer

Aplikasi web berbasis Yii 2 untuk menganalisis volume transaksi saham Amerika dan mengidentifikasi **buying pressure** mingguan. Sistem ini mengambil data OHLCV, mengklasifikasikan buy/sell volume, menghitung indikator mingguan (RVOL, volume growth, MA20/MA50), menilai saham dengan skor 0-100, dan menghasilkan sinyal BUY/SELL.

> Dokumentasi lengkap (instalasi, konfigurasi, REST API, struktur proyek): lihat [`../README.md`](../README.md).

## Data Provider

- **Yahoo Finance** (`yahoo_finance`, default produksi) — `GET https://query1.finance.yahoo.com/v8/finance/chart/{symbol}`, tanpa API key. Mendukung data **harian + intraday 1H**.
- **Alpha Vantage / Polygon** — butuh API key (`.env`: `ALPHAVANTAGE_API_KEY` / `POLYGON_API_KEY`), hanya data harian.
- **CSV** (`csv`, default dev) — backfill dari file `data/csv/`.

Daftar statis Yahoo: **~136 simbol** (NASDAQ + NYSE + ETF spt. SPCX/ARKK), termasuk DKNG, SPCX, BABA, KLAC, SEDG, FSLR, WDC, ADI, PM, NOW, ETSY, GE, RTX.

## Console Commands

```bash
# Refresh daftar saham dari provider aktif (~136 simbol Yahoo)
php yii market/refresh-stocklist

# Fetch data harian semua simbol aktif (via queue) / 1 simbol
php yii data/fetch
php yii data/fetch NVDA

# Sync langsung 1 simbol tanpa queue (debug); opsi --from=YYYY-MM-DD --to=YYYY-MM-DD
php yii data/sync NVDA

# Refresh incremental SEMUA simbol aktif (cron harian); opsi --limit=50 --sleepMs=2000
php yii data/refresh-all

# Sync intraday 1H (hanya yahoo_finance): 1 simbol / semua simbol (cron 1-4 jam)
php yii data/sync-intraday NVDA [--interval=1h]
php yii data/sync-intraday-all [--interval=1h --limit=20]

# Klasifikasi & agregasi mingguan: semua simbol / 1 simbol
php yii data/analyze
php yii data/analyze NVDA

# Scan & generate sinyal
php yii signal/scan

# Dispatch alert (cron)
php yii alert/dispatch

# Backtest strategi signal / rsi_double_bottom + grid search
php yii backtest/run --minScore=80 --holdingDays=5
php yii backtest/run --strategy=rsi_double_bottom --timeframe=4 --lookback=150 --tolerance=3 --rsiPeriod=14 --maxRsi=40 --holdingDays=5
php yii backtest/grid
```

### Menambah Saham Baru (alur wajib)

Semua perintah hanya memproses saham `active = 1` di tabel `stock`. Setelah menambah simbol ke `services/market/YahooFinanceProvider.php::staticStockList()`, jalankan berurutan:

```bash
php yii market/refresh-stocklist   # daftarkan / reaktivasi ke tabel stock
php yii data/sync DKNG             # ambil OHLCV (beri jeda antar simbol, rate limit Yahoo ~30 req/menit)
php yii data/analyze DKNG          # isi weekly_analysis (syarat muncul di Scanner/Detail/chart)
php yii signal/scan                # hasilkan sinyal
```

Troubleshooting: cek `stock` (ada? `active=1`?) → `daily_price` (ada bar?) → `weekly_analysis` (ada baris?). Jangan jalankan `refresh-stocklist` saat provider = `csv` (akan menonaktifkan simbol di luar CSV). `SPCX` wajar datanya sedikit (ETF baru, ±69 bar / 16 minggu).

## Web Interface

Jalankan dari folder ini (`stock-volume-analyzer/`):

```bash
php -S 127.0.0.1:8089 -t web
```

Lalu buka scanner & halaman analisis:

```
http://127.0.0.1:8089/scanner
http://127.0.0.1:8089/double-bottom
http://127.0.0.1:8089/rsi-double-bottom
http://127.0.0.1:8089/backtest/index
```

## Logging

- `runtime/logs/services.log` — progres scan per saham (mis. `[3/15] AAPL: 4 minggu discoring`).
- `runtime/logs/app.log` — error/warning.

DIRECTORY STRUCTURE
-------------------

      assets/             contains assets definition
      commands/           contains console commands (controllers)
      config/             contains application configurations
      controllers/        contains Web controller classes
      mail/               contains view files for e-mails
      models/             contains model classes
      runtime/            contains files generated during runtime
      tests/              contains various tests for the basic application
      vendor/             contains dependent 3rd-party packages
      views/              contains view files for the Web application
      web/                contains the entry script and Web resources



REQUIREMENTS
------------

The minimum requirement by this project template that your Web server supports PHP 7.4.


INSTALLATION
------------

### Install via Composer

If you do not have [Composer](https://getcomposer.org/), you may install it by following the instructions
at [getcomposer.org](https://getcomposer.org/doc/00-intro.md#installation-nix).

You can then install this project template using the following command:

~~~
composer create-project --prefer-dist yiisoft/yii2-app-basic basic
~~~

Now you should be able to access the application through the following URL, assuming `basic` is the directory
directly under the Web root.

~~~
http://localhost/basic/web/
~~~

### Install from an Archive File

Extract the archive file downloaded from [yiiframework.com](https://www.yiiframework.com/download/) to
a directory named `basic` that is directly under the Web root.

Set cookie validation key in `config/web.php` file to some random secret string:

```php
'request' => [
    // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
    'cookieValidationKey' => '<secret random string goes here>',
],
```

You can then access the application through the following URL:

~~~
http://localhost/basic/web/
~~~


### Install with Docker

Update your vendor packages

    docker-compose run --rm php composer update --prefer-dist
    
Run the installation triggers (creating cookie validation code)

    docker-compose run --rm php composer install    
    
Start the container

    docker-compose up -d
    
You can then access the application through the following URL:

    http://127.0.0.1:8000

**NOTES:** 
- Minimum required Docker engine version `17.04` for development (see [Performance tuning for volume mounts](https://docs.docker.com/docker-for-mac/osxfs-caching/))
- The default configuration uses a host-volume in your home directory `.docker-composer` for composer caches


CONFIGURATION
-------------

### Database

Edit the file `config/db.php` with real data, for example:

```php
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=yii2basic',
    'username' => 'root',
    'password' => '1234',
    'charset' => 'utf8',
];
```

**NOTES:**
- Yii won't create the database for you, this has to be done manually before you can access it.
- Check and edit the other files in the `config/` directory to customize your application as required.
- Refer to the README in the `tests` directory for information specific to basic application tests.


TESTING
-------

Tests are located in `tests` directory. They are developed with [Codeception PHP Testing Framework](https://codeception.com/).
By default, there are 3 test suites:

- `unit`
- `functional`
- `acceptance`

Tests can be executed by running

```
vendor/bin/codecept run
```

The command above will execute unit and functional tests. Unit tests are testing the system components, while functional
tests are for testing user interaction. Acceptance tests are disabled by default as they require additional setup since
they perform testing in real browser. 


### Running  acceptance tests

To execute acceptance tests do the following:  

1. Rename `tests/acceptance.suite.yml.example` to `tests/acceptance.suite.yml` to enable suite configuration

2. Replace `codeception/base` package in `composer.json` with `codeception/codeception` to install full-featured
   version of Codeception

3. Update dependencies with Composer 

    ```
    composer update  
    ```

4. Download [Selenium Server](https://www.seleniumhq.org/download/) and launch it:

    ```
    java -jar ~/selenium-server-standalone-x.xx.x.jar
    ```

    In case of using Selenium Server 3.0 with Firefox browser since v48 or Google Chrome since v53 you must download [GeckoDriver](https://github.com/mozilla/geckodriver/releases) or [ChromeDriver](https://sites.google.com/a/chromium.org/chromedriver/downloads) and launch Selenium with it:

    ```
    # for Firefox
    java -jar -Dwebdriver.gecko.driver=~/geckodriver ~/selenium-server-standalone-3.xx.x.jar
    
    # for Google Chrome
    java -jar -Dwebdriver.chrome.driver=~/chromedriver ~/selenium-server-standalone-3.xx.x.jar
    ``` 
    
    As an alternative way you can use already configured Docker container with older versions of Selenium and Firefox:
    
    ```
    docker run --net=host selenium/standalone-firefox:2.53.0
    ```

5. (Optional) Create `yii2basic_test` database and update it by applying migrations if you have them.

   ```
   tests/bin/yii migrate
   ```

   The database configuration can be found at `config/test_db.php`.


6. Start web server:

    ```
    tests/bin/yii serve
    ```

7. Now you can run all available tests

   ```
   # run all available tests
   vendor/bin/codecept run

   # run acceptance tests
   vendor/bin/codecept run acceptance

   # run only unit and functional tests
   vendor/bin/codecept run unit,functional
   ```

### Code coverage support

By default, code coverage is disabled in `codeception.yml` configuration file, you should uncomment needed rows to be able
to collect code coverage. You can run your tests and collect coverage with the following command:

```
#collect coverage for all tests
vendor/bin/codecept run --coverage --coverage-html --coverage-xml

#collect coverage only for unit tests
vendor/bin/codecept run unit --coverage --coverage-html --coverage-xml

#collect coverage for unit and functional tests
vendor/bin/codecept run functional,unit --coverage --coverage-html --coverage-xml
```

You can see code coverage output under the `tests/_output` directory.
