<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Stock Volume Analyzer',

    // ==== US Stock Volume Analyzer ====
    // Provider data pasar: alpha_vantage | polygon | yahoo_finance | csv (dev/backfill)
    'marketDataProvider' => 'yahoo_finance',
    'marketDataProviderConfig' => [
        'alpha_vantage' => [
            'apiKey' => getenv('ALPHAVANTAGE_API_KEY') ?: '',
            'baseUrl' => 'https://www.alphavantage.co/query',
            'rateLimitPerMinute' => 5,
        ],
        'polygon' => [
            'apiKey' => getenv('POLYGON_API_KEY') ?: '',
            'baseUrl' => 'https://api.polygon.io',
            'rateLimitPerMinute' => 5,
        ],
        'yahoo_finance' => [
            'baseUrl' => 'https://query1.finance.yahoo.com',
            'rateLimitPerMinute' => 30,
        ],
        'csv' => [
            'dataPath' => '@app/data/csv',
        ],
    ],

    // Kolom backfill default saat fetch (hari)
    'historyDays' => 400,

    // Threshold scoring signal engine (desain §11) — urut dari syarat tertinggi
    'signalThresholds' => [
        'buyRatio' => [
            ['min' => 0.70, 'points' => 30],
            ['min' => 0.60, 'points' => 20],
            ['min' => 0.55, 'points' => 10],
        ],
        'volumeGrowth' => [
            ['min' => 0.50, 'points' => 25],
            ['min' => 0.25, 'points' => 15],
            ['min' => 0.10, 'points' => 10],
        ],
        'rvol' => [
            ['min' => 2.0, 'points' => 20],
            ['min' => 1.5, 'points' => 15],
            ['min' => 1.2, 'points' => 10],
        ],
        'aboveMa20' => 15,
        'aboveMa50' => 10,
    ],

    // Alert
    'alertCooldownHours' => 24,

    // REST API v1 (Modul 13): rate limit per klien (token/IP) per menit
    'apiRateLimitPerMinute' => 120,

    // Price Double Bottom scanner (Modul tambahan).
    // Nilai default teknis ada di DoubleBottomService::DEFAULT_*; di sini
    // hanya override opsional + kategori log.
    'doubleBottomDefaults' => [
        'logCategory' => 'app\\services\\doublebottom',
    ],

    // RSI Double Bottom scanner (Modul tambahan)
    'rsiDefaults' => [
        'rsiPeriod'      => 14,
        'maxRsiForBottom'=> 40.0,   // kedua lembah harus < nilai ini
        'tolerance'      => 3.0,     // poin RSI: |RSI1 - RSI2| <= ini
        'minSeparation'  => 5,       // candle minimum antar lembah
        'maxSeparation'  => 30,      // candle maksimum antar lembah
        'lookback'       => 150,     // jumlah candle yang dipindai
        'necklineMin'    => 2.0,     // prominensi neckline minimum (poin RSI)
        'logCategory'    => 'app\\services\\rsidoublebottom',
    ],
];
