<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Stock Volume Analyzer',

    // ==== US Stock Volume Analyzer ====
    // Provider data pasar: alpha_vantage | polygon | csv (dev/backfill)
    'marketDataProvider' => 'csv',
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
        'csv' => [
            // Folder berisi file CSV per simbol: SYMBOL.csv (date,open,high,low,close,volume)
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
];
