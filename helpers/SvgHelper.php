<?php

namespace app\helpers;

use yii\bootstrap5\Html;

/**
 * SvgHelper provides modern inline SVG rendering for Bootstrap Icons
 * Supports both standalone inline SVG and SVG sprite <use> references.
 */
class SvgHelper
{
    /** @var array<string, string> Cache of loaded SVG inner paths */
    protected static array $cache = [];

    /**
     * Render an icon as an inline <svg> element.
     *
     * @param string $name Name of the icon (e.g. 'speedometer2', 'wallet2', 'graph-up')
     * @param array $options HTML attributes for the <svg> element (e.g. ['class' => 'me-1 text-primary', 'width' => 16, 'height' => 16])
     * @return string Raw SVG HTML string
     */
    public static function icon(string $name, array $options = []): string
    {
        // Strip 'bi-' or 'bi ' prefix if accidentally passed
        $cleanName = preg_replace('/^(?:bi\s+bi-|bi-)/', '', trim($name));

        $width = $options['width'] ?? '1em';
        $height = $options['height'] ?? '1em';
        $fill = $options['fill'] ?? 'currentColor';

        $class = 'bi bi-' . $cleanName;
        if (!empty($options['class'])) {
            $class .= ' ' . $options['class'];
        }

        $attrs = [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'width' => $width,
            'height' => $height,
            'fill' => $fill,
            'class' => $class,
            'viewBox' => '0 0 16 16',
            'aria-hidden' => 'true',
        ];

        foreach ($options as $k => $v) {
            if (!in_array($k, ['width', 'height', 'fill', 'class'], true)) {
                $attrs[$k] = $v;
            }
        }

        $innerContent = static::getIconContent($cleanName);

        $svgTag = Html::tag('svg', $innerContent, $attrs);
        return preg_replace('/\s+/', ' ', trim($svgTag));
    }

    /**
     * Get the inner XML paths of an icon.
     */
    protected static function getIconContent(string $name): string
    {
        if (isset(static::$cache[$name])) {
            return static::$cache[$name];
        }

        $baseDir = dirname(__DIR__);
        $candidates = [
            "{$baseDir}/web/icons/{$name}.svg",
            "{$baseDir}/node_modules/bootstrap-icons/icons/{$name}.svg",
        ];

        foreach ($candidates as $filePath) {
            if (is_file($filePath)) {
                $svg = file_get_contents($filePath);
                if (preg_match('/<svg[^>]*>(.*?)<\/svg>/is', $svg, $matches)) {
                    $cleaned = preg_replace('/\s+/', ' ', trim($matches[1]));
                    return static::$cache[$name] = $cleaned;
                }
            }
        }

        // Fallback to SVG sprite <use> reference
        return static::$cache[$name] = '<use xlink:href="/bootstrap-icons.svg#' . Html::encode($name) . '"/>';
    }
}
