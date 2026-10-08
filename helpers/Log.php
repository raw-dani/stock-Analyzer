<?php

declare(strict_types=1);

namespace app\helpers;

/**
 * Helper log — utilitas konfigurasi logging (Modul 15.5).
 */
final class Log
{
    /**
     * Log target error-alerting via email (yii\log\EmailTarget).
     *
     * Mengembalikan null bila env ERROR_ALERT_EMAIL belum di-set sehingga
     * pemanggil (config/web.php & config/console.php) membuangnya via array_filter.
     * Dipicu pada level error/warning dari kategori HTTP exception & application.
     */
    public static function errorAlertTarget(): ?array
    {
        $email = getenv('ERROR_ALERT_EMAIL');
        if ($email === false || $email === '') {
            return null;
        }

        return [
            'class' => 'yii\log\EmailTarget',
            'levels' => ['error', 'warning'],
            'categories' => [
                'yii\web\HttpException:404',
                'yii\web\HttpException:500',
                'application',
            ],
            'message' => [
                'from' => [$email],
                'to' => [$email],
                'subject' => 'Stock Analyzer Error Alert',
            ],
        ];
    }
}