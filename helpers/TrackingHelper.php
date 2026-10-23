<?php

declare(strict_types=1);

namespace app\helpers;

use app\models\SiteSetting;
use Yii;
use yii\helpers\Html;

/**
 * Helper untuk menyisipkan skrip Google Analytics 4, Google Tag Manager,
 * Webmaster Verification, dan Custom Scripts secara non-blocking berstandar Core Web Vitals.
 */
class TrackingHelper
{
    /**
     * Apakah tracking boleh aktif pada request saat ini?
     * Otomatis dinonaktifkan jika user adalah Admin (mencegah internal traffic pollution).
     */
    public static function shouldTrack(): bool
    {
        if (Yii::$app->user && !Yii::$app->user->isGuest) {
            $user = Yii::$app->user->identity;
            if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Render tag meta verifikasi Google & Bing di dalam <head>
     */
    public static function renderVerificationMeta(): string
    {
        $html = '';

        $googleToken = SiteSetting::get('google_site_verification');
        if (!empty($googleToken)) {
            $cleanToken = trim($googleToken);
            // Ekstrak nilai jika admin memasukkan seluruh tag <meta ...>
            if (preg_match('/content=["\']([^"\']+)["\']/', $cleanToken, $matches)) {
                $cleanToken = $matches[1];
            }
            $html .= Html::tag('meta', '', ['name' => 'google-site-verification', 'content' => $cleanToken]) . "\n";
        }

        $bingToken = SiteSetting::get('bing_site_verification');
        if (!empty($bingToken)) {
            $cleanBing = trim($bingToken);
            if (preg_match('/content=["\']([^"\']+)["\']/', $cleanBing, $matches)) {
                $cleanBing = $matches[1];
            }
            $html .= Html::tag('meta', '', ['name' => 'msvalidate.01', 'content' => $cleanBing]) . "\n";
        }

        return $html;
    }

    /**
     * Render skrip pelacak di dalam tag <head> (GA4, GTM head snippet, Custom Head Scripts)
     */
    public static function renderHeadScripts(): string
    {
        $html = self::renderVerificationMeta();

        if (!self::shouldTrack()) {
            return $html;
        }

        $gtmId = SiteSetting::get('gtm_container_id');
        if (!empty($gtmId)) {
            $cleanGtm = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($gtmId));
            $html .= <<<HTML
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$cleanGtm}');</script>
<!-- End Google Tag Manager -->

HTML;
        }

        $ga4Id = SiteSetting::get('ga4_measurement_id');
        if (!empty($ga4Id) && empty($gtmId)) {
            // Jika GTM belum dipasang, gunakan gtag.js langsung
            $cleanGa4 = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($ga4Id));
            $html .= <<<HTML
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$cleanGa4}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$cleanGa4}');
</script>

HTML;
        }

        $customHead = SiteSetting::get('custom_head_scripts');
        if (!empty($customHead)) {
            $html .= trim($customHead) . "\n";
        }

        return $html;
    }

    /**
     * Render snippet tepat setelah pembuka <body> (GTM noscript)
     */
    public static function renderBodyStart(): string
    {
        if (!self::shouldTrack()) {
            return '';
        }

        $gtmId = SiteSetting::get('gtm_container_id');
        if (!empty($gtmId)) {
            $cleanGtm = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($gtmId));
            return <<<HTML
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$cleanGtm}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

HTML;
        }

        return '';
    }

    /**
     * Render skrip sebelum penutup </body> (Custom Body/Footer Scripts)
     */
    public static function renderBodyEnd(): string
    {
        if (!self::shouldTrack()) {
            return '';
        }

        $customBody = SiteSetting::get('custom_body_scripts');
        return !empty($customBody) ? (trim($customBody) . "\n") : '';
    }
}
