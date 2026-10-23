<?php

declare(strict_types=1);

namespace app\services;

use Yii;

/**
 * Service untuk memproses tangkapan layar chart visual, mengompresi ke WebP,
 * dan mengelola berkas aset media blog.
 */
class BlogImageService
{
    public const UPLOAD_SUBDIR = '/uploads/blog';

    /**
     * Simpan base64 screenshot menjadi berkas WebP yang ringan dan optimal untuk Google PageSpeed
     *
     * @param string $base64Data Data URL (cth: data:image/png;base64,....)
     * @param string $symbol Simbol saham (cth: CMCSA)
     * @param string $timeFrame Time frame (cth: 1H)
     * @param string $strategy Strategi (cth: double-bottom)
     * @return string|null Web URL relatif gambar yang tersimpan (cth: /uploads/blog/cmcsa_1h_double-bottom_17192831.webp)
     */
    public function saveBase64Screenshot(string $base64Data, string $symbol, string $timeFrame, string $strategy): ?string
    {
        if (empty($base64Data)) {
            return null;
        }

        // Pisahkan header base64 jika ada
        if (str_contains($base64Data, ';base64,')) {
            [, $base64Data] = explode(';base64,', $base64Data);
        }

        $binary = base64_decode($base64Data, true);
        if ($binary === false) {
            Yii::warning("Gagal melakukan base64 decode screenshot.", __METHOD__);
            return null;
        }

        $webroot = (is_string(Yii::getAlias('@webroot', false)) && file_exists(Yii::getAlias('@webroot', false) . '/index.php'))
            ? Yii::getAlias('@webroot')
            : (Yii::getAlias('@app') . '/web');
        $uploadDir = $webroot . self::UPLOAD_SUBDIR;

        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                Yii::error("Gagal membuat direktori upload: {$uploadDir}", __METHOD__);
                return null;
            }
        }

        $cleanSymbol = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $symbol));
        $cleanTf = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $timeFrame));
        $cleanStrat = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $strategy));
        $filename = sprintf('%s_%s_%s_%s', $cleanSymbol, $cleanTf, $cleanStrat, date('Ymd_His'));

        $webpFilename = $filename . '.webp';
        $fullWebpPath = $uploadDir . '/' . $webpFilename;
        $relativeUrl = self::UPLOAD_SUBDIR . '/' . $webpFilename;

        // Coba konversi ke WebP menggunakan GD jika didukung
        if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
            $img = @imagecreatefromstring($binary);
            if ($img !== false) {
                // Pertahankan transparansi bila ada
                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, true);

                $saved = @imagewebp($img, $fullWebpPath, 80);
                imagedestroy($img);

                if ($saved && file_exists($fullWebpPath)) {
                    return $relativeUrl;
                }
            }
        }

        // Fallback: simpan sebagai PNG jika WebP gagal
        $pngFilename = $filename . '.png';
        $fullPngPath = $uploadDir . '/' . $pngFilename;
        if (@file_put_contents($fullPngPath, $binary) !== false) {
            return self::UPLOAD_SUBDIR . '/' . $pngFilename;
        }

        return null;
    }
}
