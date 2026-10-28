<?php
/**
 * Plugin Name:       Cookie Smart – GDPR Cookie Banner & Consent Mode v2
 * Plugin URI:        https://cookiesmart.it
 * Description:       Cookie banner with prior blocking of scripts and iframes, Google Consent Mode v2 and consent log, compliant with the GDPR. Requires a Cookie Smart account.
 * Version:           1.5.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            PC Web Agency
 * Author URI:        https://www.pcwebagency.it
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cookie-smart
 * Domain Path:       /languages
 * Update URI:        https://cookiesmart.it
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Cookie_Smart_Plugin
{
    const VERSION = '1.5.0';
    const OPTION = 'cookie_smart_settings';
    const TRANSIENT = 'cookie_smart_remote_config';
    /** Ultima configurazione ricevuta: resta valida se il server non risponde. */
    const LAST_CONFIG = 'cookie_smart_last_config';
    const SLUG = 'cookie-smart';
    const DEFAULT_SERVER = 'https://cookiesmart.it';

    /** Scudo con spunta (logo), monocromatico: WordPress lo colora come le altre icone del menu. */
    const ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M10 1l7 3v5c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V4l7-3zM6.3 10.4l2.9 2.9 4.8-4.8-1.2-1.2-3.6 3.6-1.7-1.7-1.2 1.2z"/></svg>';

    /** @var array|null */
    private $remoteConfig = null;

    public static function instance(): self
    {
        static $i = null;
        return $i ?: ($i = new self());
    }

    private function __construct()
    {
        // @github-only-start
        // Traduzioni incluse nel plugin (languages/). Su wordpress.org le carica WordPress dai pacchetti di lingua.
        add_action('init', [$this, 'loadTextdomain']);
        // @github-only-end
        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'actionLinks']);
        add_filter('plugin_row_meta', [$this, 'rowMeta'], 10, 2);
        // Finché manca la chiave: avviso con il link alla configurazione
        add_action('admin_notices', [$this, 'setupNotice']);

        if ($this->isConfigured()) {
            // Il prima possibile nel <head>, prima di GTM e degli altri tag
            add_action('wp_head', [$this, 'printLoader'], -1000);
            // Marca gli script accodati con wp_enqueue_script
            add_filter('script_loader_tag', [$this, 'filterScriptTag'], 999, 3);
            // Opzionale: riscrive anche script e iframe scritti direttamente nel tema. Usa il buffer della pagina
            // gestito da WordPress (dalla 6.9): WordPress lo apre e lo chiude, solo per le pagine HTML del sito.
            if (!empty($this->settings()['buffer']) && self::themeBlockingSupported()) {
                add_filter('wp_template_enhancement_output_buffer', [$this, 'rewriteHtml']);
            }
        }

        // @github-only-start
        // Aggiornamenti dalle release GitHub: solo nella versione distribuita su GitHub.
        // Il pacchetto per wordpress.org non contiene questo blocco né il file (aggiorna wordpress.org).
        require_once __DIR__ . '/includes/class-cookie-smart-updater.php';
        new Cookie_Smart_Updater($this, __FILE__);
        // @github-only-end

        // WP Consent API (https://wordpress.org/plugins/wp-consent-api/)
        add_filter('wp_consent_api_registered_' . plugin_basename(__FILE__), '__return_true');
        add_filter('wp_get_consent_type', function () { return 'optin'; });
    }

    // @github-only-start
    public function loadTextdomain(): void
    {
        load_plugin_textdomain('cookie-smart', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    // @github-only-end

    // -------------------------------------------------------------------------
    // Impostazioni
    // -------------------------------------------------------------------------

    public function settings(): array
    {
        return wp_parse_args(get_option(self::OPTION, []), [
            'server_url' => self::DEFAULT_SERVER,
            'site_key' => '',
            'buffer' => 0,
        ]);
    }

    private function isConfigured(): bool
    {
        return $this->settings()['site_key'] !== '';
    }

    /** Indirizzo del loader indicato dal server (es. la CDN); altrimenti quello del server configurato. */
    private function loaderUrl(): string
    {
        $cfg = $this->remoteConfig();
        if (!empty($cfg['loaderUrl']) && is_string($cfg['loaderUrl']) && strpos($cfg['loaderUrl'], 'https://') === 0) {
            return $cfg['loaderUrl'];
        }

        return $this->serverUrl() . '/api/c/' . rawurlencode($this->settings()['site_key']) . '.js';
    }

    public function adminMenu(): void
    {
        add_menu_page(
            'Cookie Smart',
            'Cookie Smart',
            'manage_options',
            'cookie-smart',
            [$this, 'renderSettingsPage'],
            'data:image/svg+xml;base64,' . base64_encode(self::ICON_SVG),
            81 // subito dopo Impostazioni
        );
    }

    public function actionLinks(array $links): array
    {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=cookie-smart')) . '">' . esc_html__('Settings', 'cookie-smart') . '</a>');
        return $links;
    }

    /**
     * Server Cookie Smart: https://cookiesmart.it. Per lo sviluppo si può cambiare con la costante
     * COOKIESMART_SERVER_URL in wp-config.php; vale anche un indirizzo salvato dalle versioni precedenti.
     */
    public function serverUrl(): string
    {
        $url = defined('COOKIESMART_SERVER_URL') ? (string) constant('COOKIESMART_SERVER_URL') : $this->settings()['server_url'];

        return untrailingslashit($url ?: self::DEFAULT_SERVER);
    }

    /** Indirizzo di una pagina del sito Cookie Smart (registrazione, accesso). */
    private function siteUrl(string $path = ''): string
    {
        return $this->serverUrl() . $path;
    }

    /** Link sotto la descrizione del plugin, nella pagina Plugin. */
    public function rowMeta(array $links, string $file): array
    {
        // Il link al sito lo aggiunge già WordPress («Visit plugin site», dal Plugin URI)
        if ($file === plugin_basename(__FILE__)) {
            if (!$this->isConfigured()) {
                $links[] = '<a href="' . esc_url($this->siteUrl('/app/register')) . '" target="_blank" rel="noopener">' . esc_html__('Create an account', 'cookie-smart') . '</a>';
            }
        }
        return $links;
    }

    /** Avviso nella pagina Plugin e nella Bacheca finché il plugin non ha la chiave del sito. */
    public function setupNotice(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($this->isConfigured() || !current_user_can('manage_options') || !$screen || !in_array($screen->id, ['plugins', 'dashboard'], true)) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p><a class="button button-primary" href="%s">%s</a></p></div>',
            esc_html__('Cookie Smart is installed but not connected yet:', 'cookie-smart'),
            esc_html__('the cookie banner is not shown until you enter your site key.', 'cookie-smart'),
            esc_url(admin_url('admin.php?page=cookie-smart')),
            esc_html__('Connect Cookie Smart', 'cookie-smart')
        );
    }

    /** Guida all'attivazione nella pagina del plugin, per chi lo ha installato senza passare dal sito. */
    private function renderSetupGuide(): void
    {
        ?>
        <div class="card" style="max-width:720px;padding:16px 20px;margin:16px 0">
            <h2 style="margin-top:0"><?php esc_html_e('To activate the banner you need a Cookie Smart account', 'cookie-smart'); ?></h2>
            <ol style="margin-left:1.2em">
                <li><?php esc_html_e('Create your account on the Cookie Smart website: 14-day free trial, no credit card required.', 'cookie-smart'); ?></li>
                <li><?php esc_html_e('In the dashboard, add your website with its domain.', 'cookie-smart'); ?></li>
                <li><?php esc_html_e('Copy the "Site key" from your website page and paste it below.', 'cookie-smart'); ?></li>
            </ol>
            <p>
                <a class="button button-primary" href="<?php echo esc_url($this->siteUrl('/app/register')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Create a free account', 'cookie-smart'); ?></a>
                <a class="button" href="<?php echo esc_url($this->siteUrl('/app/login')); ?>" target="_blank" rel="noopener"><?php esc_html_e('I already have an account: log in', 'cookie-smart'); ?></a>
            </p>
        </div>
        <?php
    }

    public function registerSettings(): void
    {
        register_setting('cookie_smart', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => function ($in) {
                $in = is_array($in) ? $in : [];
                delete_transient(self::TRANSIENT); // ricarica la configurazione remota
                return [
                    // Non più nel modulo: si conserva l'eventuale indirizzo salvato in precedenza
                    'server_url' => esc_url_raw((string) ($this->settings()['server_url'] ?? '')),
                    'site_key' => preg_replace('/[^A-Za-z0-9]/', '', (string) ($in['site_key'] ?? '')),
                    'buffer' => empty($in['buffer']) ? 0 : 1,
                ];
            },
        ]);
    }

    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = $this->settings();
        $cfg = $this->isConfigured() ? $this->remoteConfig(true) : null;
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px">
                <span style="display:inline-grid;place-items:center;width:36px;height:36px;border-radius:8px;background:#1f4fd1" aria-hidden="true">
                    <svg viewBox="0 0 20 20" width="22" height="22"><path fill="#fff" fill-rule="evenodd" d="M10 1l7 3v5c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V4l7-3zM6.3 10.4l2.9 2.9 4.8-4.8-1.2-1.2-3.6 3.6-1.7-1.7-1.2 1.2z"/></svg>
                </span>
                Cookie Smart
            </h1>
            <?php if (!$this->isConfigured()) { $this->renderSetupGuide(); } ?>
            <form method="post" action="options.php">
                <?php settings_fields('cookie_smart'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="cookiesmart-key"><?php esc_html_e('Site key', 'cookie-smart'); ?></label></th>
                        <td><input id="cookiesmart-key" type="text" class="regular-text code" name="<?php echo esc_attr(self::OPTION); ?>[site_key]" value="<?php echo esc_attr($s['site_key']); ?>">
                            <p class="description"><?php
                                printf(
                                    /* translators: %s: link to the Cookie Smart dashboard */
                                    esc_html__('You can find it in the %s, on your website page.', 'cookie-smart'),
                                    '<a href="' . esc_url($this->siteUrl('/app/login')) . '" target="_blank" rel="noopener">' . esc_html__('Cookie Smart dashboard', 'cookie-smart') . '</a>'
                                );
                            ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Blocking in the theme', 'cookie-smart'); ?></th>
                        <td>
                            <?php $supported = self::themeBlockingSupported(); ?>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[buffer]" value="1" <?php checked($s['buffer'] && $supported, true); ?> <?php disabled(!$supported); ?>>
                                <?php esc_html_e('Also rewrite scripts and iframes added directly in the theme or in the content', 'cookie-smart'); ?></label>
                            <p class="description"><?php
                                if ($supported) {
                                    esc_html_e('Check that it works with your caching plugin enabled.', 'cookie-smart');
                                } else {
                                    esc_html_e('Requires WordPress 6.9 or later.', 'cookie-smart');
                                }
                            ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <?php if ($this->isConfigured()) : ?>
                <h2><?php esc_html_e('Status', 'cookie-smart'); ?></h2>
                <?php if ($cfg) : ?>
                    <p>✅ <?php
                        printf(
                            /* translators: 1: configuration version, 2: number of blocking patterns */
                            esc_html__('Connected to the server. Configuration version: %1$s, blocking patterns: %2$s.', 'cookie-smart'),
                            '<strong>' . (int) ($cfg['version'] ?? 0) . '</strong>',
                            '<strong>' . count($cfg['blockPatterns'] ?? []) . '</strong>'
                        );
                    ?></p>
                <?php else : ?>
                    <p>⚠️ <?php esc_html_e('Unable to read the configuration from the server: check your site key. The banner is loaded anyway.', 'cookie-smart'); ?></p>
                <?php endif; ?>
                <p><?php
                    printf(
                        /* translators: 1: HTML link example, 2: shortcode */
                        esc_html__('For a "Cookie preferences" link in the footer use %1$s or the shortcode %2$s.', 'cookie-smart'),
                        '<code>' . esc_html('<a href="#cookiesmart-preferences">' . __('Cookie preferences', 'cookie-smart') . '</a>') . '</code>',
                        '<code>[cookie_smart_preferences]</code>'
                    );
                ?></p>
                <p><?php
                    printf(
                        /* translators: %s: shortcode */
                        esc_html__('To show the up-to-date list of cookies, grouped by category, in your cookie policy page, add the shortcode %s.', 'cookie-smart'),
                        '<code>[cookie_smart_declaration]</code>'
                    );
                ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Configurazione remota (pattern di blocco dal server)
    // -------------------------------------------------------------------------

    /** @return array|null */
    private function remoteConfig(bool $fresh = false)
    {
        if ($this->remoteConfig !== null && !$fresh) {
            return $this->remoteConfig ?: null;
        }

        $cached = $fresh ? false : get_transient(self::TRANSIENT);
        if (is_array($cached)) {
            return $this->remoteConfig = $cached;
        }

        $url = $this->serverUrl() . '/api/v1/sites/' . rawurlencode($this->settings()['site_key']) . '/config';
        $res = wp_remote_get($url, ['timeout' => 5, 'headers' => ['Accept' => 'application/json']]);

        $data = null;
        if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
            $data = json_decode(wp_remote_retrieve_body($res), true);
        }

        if (is_array($data) && isset($data['blockPatterns'])) {
            set_transient(self::TRANSIENT, $data, 6 * HOUR_IN_SECONDS);
            update_option(self::LAST_CONFIG, $data, false);
            return $this->remoteConfig = $data;
        }

        // Server non raggiungibile: si continua con l'ultima configurazione ricevuta (pattern di blocco e
        // indirizzo del loader) e si riprova tra 10 minuti
        $last = get_option(self::LAST_CONFIG, []);
        $last = is_array($last) ? $last : [];
        set_transient(self::TRANSIENT, $last, 10 * MINUTE_IN_SECONDS);
        $this->remoteConfig = $last;
        return $last ?: null;
    }

    private function categoryForUrl(string $url)
    {
        $cfg = $this->remoteConfig();
        foreach (($cfg['blockPatterns'] ?? []) as $p) {
            if (!empty($p['pattern']) && strpos($url, $p['pattern']) !== false) {
                return $p['category'];
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Front-end
    // -------------------------------------------------------------------------

    public function printLoader(): void
    {
        // Lo script deve essere sincrono (niente async/defer): deve agire prima degli altri.
        // Gli attributi data-* chiedono ai plugin di ottimizzazione di non spostarlo né ritardarlo.
        wp_print_script_tag([
            'src' => esc_url($this->loaderUrl()),
            'data-no-optimize' => '1',
            'data-no-defer' => '1',
            'data-cfasync' => 'false',
            'data-pagespeed-no-defer' => true,
        ]);

        // Ponte verso la WP Consent API, se presente
        wp_print_inline_script_tag(
            "window.addEventListener('cookiesmart:consent',function(e){"
            . "if(typeof window.wp_set_consent!=='function')return;"
            . "var c=e.detail.categories,m={preferences:'functional',statistics:'analytics','statistics-anonymous':'analytics',marketing:'marketing'};"
            . "for(var k in m){window.wp_set_consent(k,c[m[k]]?'allow':'deny');}"
            . '});',
            ['data-no-optimize' => '1', 'data-cfasync' => 'false']
        );
    }

    public function filterScriptTag(string $tag, string $handle, string $src): string
    {
        if ($src === '' || strpos($tag, 'data-cookiesmart-category') !== false) {
            return $tag;
        }
        $cat = $this->categoryForUrl($src);
        return $cat ? $this->blockScriptTag($tag, $cat) : $tag;
    }

    private function blockScriptTag(string $tag, string $category): string
    {
        $attr = ' data-cookiesmart-category="' . esc_attr($category) . '"';

        if (preg_match('/\stype=(["\'])(.*?)\1/i', $tag, $m)) {
            // conserva il type originale (es. module) per lo sblocco
            if (strtolower($m[2]) !== 'text/javascript') {
                $attr .= ' data-cookiesmart-type="' . esc_attr($m[2]) . '"';
            }
            return preg_replace('/\stype=(["\']).*?\1/i', ' type="text/plain"' . $attr, $tag, 1);
        }

        return preg_replace('/<script\b/i', '<script type="text/plain"' . $attr, $tag, 1);
    }

    /** Il blocco nel tema usa il buffer della pagina di WordPress, disponibile dalla versione 6.9. */
    public static function themeBlockingSupported(): bool
    {
        return function_exists('wp_start_template_enhancement_output_buffer');
    }

    public function rewriteHtml(string $html): string
    {
        if (stripos($html, '<html') === false) {
            return $html; // non è una pagina HTML
        }

        // <script src="...">
        $html = preg_replace_callback('/<script\b[^>]*\ssrc=(["\'])(.*?)\1[^>]*>/is', function ($m) {
            if (strpos($m[0], 'data-cookiesmart-category') !== false) {
                return $m[0];
            }
            $cat = $this->categoryForUrl(html_entity_decode($m[2]));
            return $cat ? $this->blockScriptTag($m[0], $cat) : $m[0];
        }, $html);

        // <iframe src="..."> -> data-src, il client mostra un segnaposto
        $html = preg_replace_callback('/<iframe\b[^>]*\ssrc=(["\'])(.*?)\1[^>]*>/is', function ($m) {
            if (strpos($m[0], 'data-cookiesmart-category') !== false) {
                return $m[0];
            }
            $cat = $this->categoryForUrl(html_entity_decode($m[2]));
            if (!$cat) {
                return $m[0];
            }
            $tag = preg_replace('/\ssrc=(["\'])/i', ' data-src=$1', $m[0], 1);
            return preg_replace('/<iframe\b/i', '<iframe data-cookiesmart-category="' . esc_attr($cat) . '" data-cookiesmart-blocked', $tag, 1);
        }, $html);

        return $html;
    }
}

add_action('plugins_loaded', ['Cookie_Smart_Plugin', 'instance']);

add_shortcode('cookie_smart_preferences', function ($atts) {
    $atts = shortcode_atts(['label' => __('Cookie preferences', 'cookie-smart')], $atts);
    return '<a href="#cookiesmart-preferences">' . esc_html($atts['label']) . '</a>';
});

// Dichiarazione dei cookie per la pagina della cookie policy: il segnaposto lo riempie il banner
add_shortcode('cookie_smart_declaration', function () {
    return '<div class="cookiesmart-declaration" data-cookiesmart-declaration></div>';
});
