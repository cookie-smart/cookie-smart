<?php
/**
 * Cookie Smart - riscrittura dell'HTML prima dell'invio al browser (blocco lato server).
 *
 * Classe indipendente da PrestaShop, testabile da sola (test/rewriter_test.php).
 * Stessa logica del plugin WordPress: gli script e gli iframe dei servizi in elenco diventano
 * type="text/plain" / data-src, così il browser non li scarica finché il banner non li sblocca.
 */
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

    /** Inserisce lo script del banner come primo elemento del <head>, se non c'è già. */
    public static function injectLoader(string $html, string $loaderUrl): string
    {
        if ($loaderUrl === '' || strpos($html, $loaderUrl) !== false) {
            return $html; // già presente (es. inserito a mano nel tema)
        }
        $tag = '<script src="' . htmlspecialchars($loaderUrl, ENT_QUOTES) . '" data-cfasync="false"></script>';

        return preg_replace('/<head\b[^>]*>/i', '$0' . "\n" . $tag, $html, 1) ?? $html;
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

        // <script src="..."> di un servizio in elenco
        $html = preg_replace_callback('/<script\b[^>]*\ssrc=(["\'])(.*?)\1[^>]*>/is', function ($m) {
            if (strpos($m[0], 'data-cookiesmart-category') !== false) {
                return $m[0];
            }
            $cat = $this->categoryForUrl(html_entity_decode($m[2]));

            return $cat ? self::blockScriptTag($m[0], $cat) : $m[0];
        }, $html) ?? $html;

        // <iframe src="..."> -> data-src: il banner mostra un segnaposto
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
            // conserva il type originale (es. module) per lo sblocco
            if (strtolower($m[2]) !== 'text/javascript') {
                $attr .= ' data-cookiesmart-type="' . htmlspecialchars($m[2], ENT_QUOTES) . '"';
            }

            return preg_replace('/\stype=(["\']).*?\1/i', ' type="text/plain"' . $attr, $tag, 1);
        }

        return preg_replace('/<script\b/i', '<script type="text/plain"' . $attr, $tag, 1);
    }
}
