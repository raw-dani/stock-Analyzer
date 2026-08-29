<?php

return [
    'class' => 'yii\db\Connection',
    // MVP: SQLite. Untuk produksi ganti ke MySQL/PostgreSQL (lihat desain §6)
    'dsn' => 'sqlite:@app/data/stocks.db',

    // Contoh konfigurasi MySQL (Phase 2):
    // 'dsn' => 'mysql:host=localhost;dbname=stock_analyzer',
    // 'username' => 'root',
    // 'password' => '',
    // 'charset' => 'utf8mb4',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];

