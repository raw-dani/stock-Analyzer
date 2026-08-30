<?php

$db = require __DIR__ . '/db.php';
// Test DB: SQLite (same dev DB, read-only scanner tests)
$db['dsn'] = 'sqlite:@app/data/stocks.db';

return $db;
