<?php
/**
 * Cookie Smart for PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    PC Web Agency <info@cookiesmart.it>
 * @copyright PC Web Agency
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

/*
 * Rewrites the HTML before it is sent to the browser (server-side blocking).
 * The scripts and iframes of the listed services become type="text/plain" / data-src, so the browser
 * does not download them until the banner unblocks them after consent.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CookieSmartHtmlRewriter
{
    /** @var array<int, array{pattern: string, category: string}> */
    private $patterns;

    public function __construct(array $patterns)
    {
        $this->patterns = array_values(array_filter($patterns, function ($p) {
            return !empty($p['pattern']) && !empty($p['category']);
        }));
    }

    /** Adds the banner script as the first element of the <head>, unless it is already there. */
    public static function injectLoader(string $html, string $loaderUrl): string
    {
        if ($loaderUrl === '' || strpos($html, $loaderUrl) !== false) {
            return $html; // already present (e.g. added manually to the theme)
        }
        $tag = '<script src="' . htmlspecialchars($loaderUrl, ENT_QUOTES) . '" data-cfasync="false"></script>';

        return preg_replace('/<head\b[^>]*>/i', '$0' . "\n" . $tag, $html, 1) ?? $html;
    }

    /**
     * Replaces the [cookiesmart_declaration] text written in a CMS page (also when the editor wraps it
     * in a paragraph) with the placeholder filled in by the banner: the cookie declaration.
     */
    public static function injectDeclaration(string $html): string
    {
        // Body only: the text may also end up in the <head> (title, meta description), where it stays as it is
        $start = stripos($html, '<body');
        if ($start === false || strpos($html, '[cookiesmart_declaration]', $start) === false) {
            return $html;
        }
        $div = '<div class="cookiesmart-declaration" data-cookiesmart-declaration></div>';
        $body = preg_replace('#<p\b[^>]*>(?:\s|&nbsp;)*\[cookiesmart_declaration\](?:\s|&nbsp;)*</p>#i', $div, substr($html, $start));

        return substr($html, 0, $start) . str_replace('[cookiesmart_declaration]', $div, $body ?? substr($html, $start));
    }

    public function categoryForUrl(string $url): ?string
    {
        foreach ($this->patterns as $p) {
            if (strpos($url, $p['pattern']) !== false) {
                return $p['category'];
            }
        }

        return null;
    }

    public function rewrite(string $html): string
    {
        if ($this->patterns === [] || stripos($html, '<html') === false) {
            return $html;
        }

        // <script src="..."> of a listed service
        $html = preg_replace_callback('/<script\b[^>]*\ssrc=(["\'])(.*?)\1[^>]*>/is', function ($m) {
            if (strpos($m[0], 'data-cookiesmart-category') !== false) {
                return $m[0];
            }
            $cat = $this->categoryForUrl(html_entity_decode($m[2]));

            return $cat ? self::blockScriptTag($m[0], $cat) : $m[0];
        }, $html) ?? $html;

        // <iframe src="..."> -> data-src: the banner shows a placeholder
        $html = preg_replace_callback('/<iframe\b[^>]*\ssrc=(["\'])(.*?)\1[^>]*>/is', function ($m) {
            if (strpos($m[0], 'data-cookiesmart-category') !== false) {
                return $m[0];
            }
            $cat = $this->categoryForUrl(html_entity_decode($m[2]));
            if (!$cat) {
                return $m[0];
            }
            $tag = preg_replace('/\ssrc=(["\'])/i', ' data-src=$1', $m[0], 1);

            return preg_replace('/<iframe\b/i', '<iframe data-cookiesmart-category="' . htmlspecialchars($cat, ENT_QUOTES) . '" data-cookiesmart-blocked', $tag, 1);
        }, $html) ?? $html;

        return $html;
    }

    private static function blockScriptTag(string $tag, string $category): string
    {
        $attr = ' data-cookiesmart-category="' . htmlspecialchars($category, ENT_QUOTES) . '"';

        if (preg_match('/\stype=(["\'])(.*?)\1/i', $tag, $m)) {
            // keep the original type (e.g. module) for unblocking
            if (strtolower($m[2]) !== 'text/javascript') {
                $attr .= ' data-cookiesmart-type="' . htmlspecialchars($m[2], ENT_QUOTES) . '"';
            }

            return preg_replace('/\stype=(["\']).*?\1/i', ' type="text/plain"' . $attr, $tag, 1);
        }

        return preg_replace('/<script\b/i', '<script type="text/plain"' . $attr, $tag, 1);
    }
}
