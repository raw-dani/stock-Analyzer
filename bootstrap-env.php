<?php

/**
 * Loader minimum untuk .env (tanpa ketergantungan eksternal).
 * Membaca file .env dan menge-set variabel environment via putenv() + $_ENV.
 */
function loadEnv(string $file): void
{
    if (!file_exists($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) < 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($value !== '' && !getenv($key) && !isset($_ENV[$key])) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');
