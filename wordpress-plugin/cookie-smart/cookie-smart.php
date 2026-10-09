<?php
/**
 * Plugin Name:       Cookie Smart
 * Description:       Banner cookie e blocco preventivo degli script collegati al server Cookie Smart.
 * Version:           1.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            PC Web Agency
 * Update URI:        https://cookiesmart.it
 * License:           GPL-2.0-or-later
 * Text Domain:       cookie-smart
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Cookie_Smart_Plugin
{
    const OPTION = 'cookie_smart_settings';
    const TRANSIENT = 'cookie_smart_remote_config';
    /** Ultima configurazione ricevuta: resta valida se il server non risponde. */
    const LAST_CONFIG = 'cookie_smart_last_config';
    /** Ultima versione pubblicata (dal server Cookie Smart; lo zip sta su GitHub). */
    const UPDATE_TRANSIENT = 'cookie_smart_update';
    const SLUG = 'cookie-smart';

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
            // Opzionale: riscrive anche script e iframe scritti direttamente nel tema
            if (!empty($this->settings()['buffer'])) {
                add_action('template_redirect', [$this, 'startBuffer'], 0);
            }
        }

        // Aggiornamenti: WordPress chiede la nuova versione all'host di «Update URI»
        add_filter('update_plugins_cookiesmart.it', [$this, 'checkUpdate'], 10, 3);
        add_filter('plugins_api', [$this, 'pluginInfo'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'verifyPackage'], 10, 3);

        // WP Consent API (https://wordpress.org/plugins/wp-consent-api/)
        add_filter('wp_consent_api_registered_' . plugin_basename(__FILE__), '__return_true');
        add_filter('wp_get_consent_type', function () { return 'optin'; });
    }

    // -------------------------------------------------------------------------
    // Impostazioni
    // -------------------------------------------------------------------------

    public function settings(): array
    {
        return wp_parse_args(get_option(self::OPTION, []), [
            'server_url' => 'https://cookiesmart.it',
            'site_key' => '',
            'buffer' => 0,
        ]);
    }

    private function isConfigured(): bool
    {
        $s = $this->settings();
        return $s['site_key'] !== '';
    }

    /** Indirizzo del loader indicato dal server (es. la CDN); altrimenti quello del server configurato. */
    private function loaderUrl(): string
    {
        $cfg = $this->remoteConfig();
        if (!empty($cfg['loaderUrl']) && is_string($cfg['loaderUrl']) && strpos($cfg['loaderUrl'], 'https://') === 0) {
            return $cfg['loaderUrl'];
        }

        $s = $this->settings();
        return $this->serverUrl() . '/api/c/' . rawurlencode($s['site_key']) . '.js';
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
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=cookie-smart')) . '">' . esc_html__('Impostazioni', 'cookie-smart') . '</a>');
        return $links;
    }

    /**
     * Server Cookie Smart: https://cookiesmart.it. Per lo sviluppo si può cambiare con la costante
     * COOKIESMART_SERVER_URL in wp-config.php; vale anche un indirizzo salvato dalle versioni precedenti.
     */
    private function serverUrl(): string
    {
        $url = defined('COOKIESMART_SERVER_URL') ? (string) constant('COOKIESMART_SERVER_URL') : $this->settings()['server_url'];

        return untrailingslashit($url ?: 'https://cookiesmart.it');
    }

    /** Indirizzo di una pagina del sito Cookie Smart (registrazione, accesso). */
    private function siteUrl(string $path = ''): string
    {
        return $this->serverUrl() . $path;
    }

    /** Link sotto la descrizione del plugin, nella pagina Plugin. */
    public function rowMeta(array $links, string $file): array
    {
        if ($file === plugin_basename(__FILE__)) {
            $links[] = '<a href="' . esc_url($this->siteUrl()) . '" target="_blank" rel="noopener">' . esc_html__('Sito di Cookie Smart', 'cookie-smart') . '</a>';
            if (!$this->isConfigured()) {
                $links[] = '<a href="' . esc_url($this->siteUrl('/app/register')) . '" target="_blank" rel="noopener">' . esc_html__('Crea un account', 'cookie-smart') . '</a>';
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
            esc_html__('Cookie Smart è installato ma non ancora collegato:', 'cookie-smart'),
            esc_html__('il banner non viene mostrato finché non inserisci la chiave del sito.', 'cookie-smart'),
            esc_url(admin_url('admin.php?page=cookie-smart')),
            esc_html__('Collega Cookie Smart', 'cookie-smart')
        );
    }

    /** Guida all'attivazione nella pagina del plugin, per chi lo ha installato senza passare dal sito. */
    private function renderSetupGuide(): void
    {
        ?>
        <div class="card" style="max-width:720px;padding:16px 20px;margin:16px 0">
            <h2 style="margin-top:0"><?php esc_html_e('Per attivare il banner ti serve un account Cookie Smart', 'cookie-smart'); ?></h2>
            <ol style="margin-left:1.2em">
                <li><?php esc_html_e('Crea l\'account sul sito di Cookie Smart: 14 giorni di prova gratuita, senza carta di credito.', 'cookie-smart'); ?></li>
                <li><?php esc_html_e('Nel pannello aggiungi il tuo sito con il suo dominio.', 'cookie-smart'); ?></li>
                <li><?php esc_html_e('Copia la «Chiave del sito» dalla pagina del sito e incollala qui sotto.', 'cookie-smart'); ?></li>
            </ol>
            <p>
                <a class="button button-primary" href="<?php echo esc_url($this->siteUrl('/app/register')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Crea l\'account gratis', 'cookie-smart'); ?></a>
                <a class="button" href="<?php echo esc_url($this->siteUrl('/app/login')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Ho già un account: accedi', 'cookie-smart'); ?></a>
            </p>
        </div>
        <?php
    }

    public function registerSettings(): void
    {
        register_setting('cookie_smart', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => function ($in) {
                delete_transient(self::TRANSIENT); // ricarica la configurazione remota
                return [
                    // Non più nel modulo: si conserva l'eventuale indirizzo salvato in precedenza
                    'server_url' => esc_url_raw((string) ($this->settings()['server_url'] ?? '')),
                    'site_key' => preg_replace('/[^A-Za-z0-9]/', '', $in['site_key'] ?? ''),
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
                        <th scope="row"><label for="cookiesmart-key">Chiave del sito</label></th>
                        <td><input id="cookiesmart-key" type="text" class="regular-text code" name="<?php echo esc_attr(self::OPTION); ?>[site_key]" value="<?php echo esc_attr($s['site_key']); ?>">
                            <p class="description"><?php printf(
                                /* translators: %s: link al pannello Cookie Smart */
                                esc_html__('La trovi nel %s, nella pagina del sito.', 'cookie-smart'),
                                '<a href="' . esc_url($this->siteUrl('/app/login')) . '" target="_blank" rel="noopener">' . esc_html__('pannello Cookie Smart', 'cookie-smart') . '</a>'
                            ); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row">Blocco nel tema</th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[buffer]" value="1" <?php checked($s['buffer'], 1); ?>>
                                Riscrivi anche script e iframe inseriti direttamente nel tema o nei contenuti</label>
                            <p class="description">Usa l'output buffering. Verifica il funzionamento con il plugin di cache attivo.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <?php if ($this->isConfigured()) : ?>
                <h2>Stato</h2>
                <?php if ($cfg) : ?>
                    <p>✅ Connesso al server. Versione configurazione: <strong><?php echo (int) $cfg['version']; ?></strong>,
                        pattern di blocco: <strong><?php echo count($cfg['blockPatterns'] ?? []); ?></strong>.</p>
                <?php else : ?>
                    <p>⚠️ Impossibile leggere la configurazione dal server: controlla URL e chiave. Il banner viene comunque caricato.</p>
                <?php endif; ?>
                <p>Per un link "Preferenze cookie" nel footer usa <code>&lt;a href="#cookiesmart-preferences"&gt;Preferenze cookie&lt;/a&gt;</code> oppure lo shortcode <code>[cookie_smart_preferences]</code>.</p>
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

        $s = $this->settings();
        $url = $this->serverUrl() . '/api/v1/sites/' . rawurlencode($s['site_key']) . '/config';
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

    // -------------------------------------------------------------------------
    // Aggiornamenti (versione dal server Cookie Smart, zip dalle release GitHub)
    // -------------------------------------------------------------------------

    /**
     * Ultima versione pubblicata, in cache per 6 ore (1 ora se il server non risponde).
     * @return array|null
     */
    private function latestRelease()
    {
        $cached = get_transient(self::UPDATE_TRANSIENT);
        if (is_array($cached)) {
            return $cached ?: null;
        }

        $server = $this->serverUrl();
        $res = wp_remote_get($server . '/api/v1/updates/wordpress', ['timeout' => 5, 'headers' => ['Accept' => 'application/json']]);
        $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
        $data = $code === 200 ? json_decode(wp_remote_retrieve_body($res), true) : null;

        // Si accettano solo zip delle release GitHub
        $valid = is_array($data) && !empty($data['version']) && is_string($data['download_url'] ?? null)
            && strpos($data['download_url'], 'https://github.com/') === 0;

        set_transient(self::UPDATE_TRANSIENT, $valid ? $data : [], $valid || $code === 404 ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);
        return $valid ? $data : null;
    }

    /** Filtro update_plugins_{host}: dati della nuova versione; WordPress la confronta con quella installata. */
    public function checkUpdate($update, $pluginData, string $pluginFile)
    {
        if ($pluginFile !== plugin_basename(__FILE__) || !($r = $this->latestRelease())) {
            return $update;
        }

        return [
            'slug' => self::SLUG,
            'version' => $r['version'],
            'url' => $r['release_url'] ?? '',
            'package' => $r['download_url'],
            'requires' => $r['requires'] ?? '',
            'tested' => $r['tested'] ?? '',
            'requires_php' => $r['requires_php'] ?? '',
        ];
    }

    /** Finestra «Visualizza dettagli» (il plugin non è su wordpress.org). */
    public function pluginInfo($result, $action, $args)
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== self::SLUG || !($r = $this->latestRelease())) {
            return $result;
        }

        return (object) [
            'name' => 'Cookie Smart',
            'slug' => self::SLUG,
            'version' => $r['version'],
            'author' => 'PC Web Agency',
            'homepage' => 'https://cookiesmart.it',
            'requires' => $r['requires'] ?? '',
            'tested' => $r['tested'] ?? '',
            'requires_php' => $r['requires_php'] ?? '',
            'last_updated' => $r['published_at'] ?? '',
            'download_link' => $r['download_url'],
            'sections' => [
                'description' => 'Banner cookie e blocco preventivo degli script, collegato al server Cookie Smart.',
                'changelog' => nl2br(esc_html($r['changelog'] ?? '')),
            ],
        ];
    }

    /** Prima di installare l'aggiornamento verifica l'impronta sha256 dello zip indicata dal server. */
    public function verifyPackage($reply, $package, $upgrader = null)
    {
        $r = $this->latestRelease();
        if ($reply !== false || !$r || $package !== $r['download_url'] || empty($r['sha256'])) {
            return $reply;
        }

        $file = download_url($package);
        if (is_wp_error($file)) {
            return $file;
        }
        if (!hash_equals(strtolower($r['sha256']), hash_file('sha256', $file))) {
            @unlink($file);
            return new WP_Error('cookie_smart_checksum', 'Lo zip scaricato non corrisponde alla versione pubblicata: aggiornamento annullato.');
        }

        return $file;
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
        printf("<script src=\"%s\" data-no-optimize=\"1\" data-no-defer=\"1\" data-cfasync=\"false\" data-pagespeed-no-defer></script>\n", esc_url($this->loaderUrl()));

        // Ponte verso la WP Consent API, se presente
        ?>
<script data-no-optimize="1" data-cfasync="false">
window.addEventListener('cookiesmart:consent',function(e){
  if(typeof window.wp_set_consent!=='function')return;
  var c=e.detail.categories,m={preferences:'functional',statistics:'analytics','statistics-anonymous':'analytics',marketing:'marketing'};
  for(var k in m){window.wp_set_consent(k,c[m[k]]?'allow':'deny');}
});
</script>
        <?php
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

    public function startBuffer(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_is_json_request() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        ob_start([$this, 'rewriteHtml']);
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
    $atts = shortcode_atts(['label' => 'Preferenze cookie'], $atts);
    return '<a href="#cookiesmart-preferences">' . esc_html($atts['label']) . '</a>';
});
