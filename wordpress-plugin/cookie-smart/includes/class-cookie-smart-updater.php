<?php
/**
 * Aggiornamenti per la versione distribuita su GitHub: il server Cookie Smart indica l'ultima versione
 * (GET /api/v1/updates/wordpress), lo zip si scarica dalle release GitHub e se ne verifica l'impronta sha256.
 *
 * Il pacchetto per wordpress.org non contiene questo file: lì gli aggiornamenti li distribuisce wordpress.org.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Cookie_Smart_Updater
{
    /** Ultima versione pubblicata (dal server Cookie Smart; lo zip sta su GitHub). */
    const TRANSIENT = 'cookie_smart_update';

    /** @var Cookie_Smart_Plugin */
    private $plugin;
    /** @var string */
    private $basename;

    public function __construct(Cookie_Smart_Plugin $plugin, string $pluginFile)
    {
        $this->plugin = $plugin;
        $this->basename = plugin_basename($pluginFile);

        // WordPress chiede la nuova versione all'host di «Update URI»
        add_filter('update_plugins_cookiesmart.it', [$this, 'checkUpdate'], 10, 3);
        add_filter('plugins_api', [$this, 'pluginInfo'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'verifyPackage'], 10, 3);
    }

    /**
     * Ultima versione pubblicata, in cache per 6 ore (1 ora se il server non risponde).
     * @return array|null
     */
    private function latestRelease()
    {
        $cached = get_transient(self::TRANSIENT);
        if (is_array($cached)) {
            return $cached ?: null;
        }

        $res = wp_remote_get($this->plugin->serverUrl() . '/api/v1/updates/wordpress', ['timeout' => 5, 'headers' => ['Accept' => 'application/json']]);
        $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
        $data = $code === 200 ? json_decode(wp_remote_retrieve_body($res), true) : null;

        // Si accettano solo zip delle release GitHub
        $valid = is_array($data) && !empty($data['version']) && is_string($data['download_url'] ?? null)
            && strpos($data['download_url'], 'https://github.com/') === 0;

        set_transient(self::TRANSIENT, $valid ? $data : [], $valid || $code === 404 ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);
        return $valid ? $data : null;
    }

    /** Filtro update_plugins_{host}: dati della nuova versione; WordPress la confronta con quella installata. */
    public function checkUpdate($update, $pluginData, string $pluginFile)
    {
        if ($pluginFile !== $this->basename || !($r = $this->latestRelease())) {
            return $update;
        }

        return [
            'slug' => Cookie_Smart_Plugin::SLUG,
            'version' => $r['version'],
            'url' => $r['release_url'] ?? '',
            'package' => $r['download_url'],
            'requires' => $r['requires'] ?? '',
            'tested' => $r['tested'] ?? '',
            'requires_php' => $r['requires_php'] ?? '',
        ];
    }

    /** Finestra «Visualizza dettagli» (la versione GitHub non è su wordpress.org). */
    public function pluginInfo($result, $action, $args)
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== Cookie_Smart_Plugin::SLUG || !($r = $this->latestRelease())) {
            return $result;
        }

        return (object) [
            'name' => 'Cookie Smart',
            'slug' => Cookie_Smart_Plugin::SLUG,
            'version' => $r['version'],
            'author' => 'PC Web Agency',
            'homepage' => 'https://cookiesmart.it',
            'requires' => $r['requires'] ?? '',
            'tested' => $r['tested'] ?? '',
            'requires_php' => $r['requires_php'] ?? '',
            'last_updated' => $r['published_at'] ?? '',
            'download_link' => $r['download_url'],
            'sections' => [
                'description' => esc_html__('Cookie banner with prior blocking of scripts and iframes, connected to the Cookie Smart service.', 'cookie-smart'),
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
            wp_delete_file($file);
            return new WP_Error('cookie_smart_checksum', __('The downloaded zip does not match the published version: update cancelled.', 'cookie-smart'));
        }

        return $file;
    }
}
