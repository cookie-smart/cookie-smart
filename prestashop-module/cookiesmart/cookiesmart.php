<?php
/**
 * Cookie Smart per PrestaShop 1.7.1+, 8 e 9.
 *
 * - inserisce lo script del banner come primo elemento del <head> (senza modificare il tema);
 * - blocco lato server: marca gli script e gli iframe dei servizi in elenco prima che la pagina
 *   arrivi al browser, che così non li scarica nemmeno;
 * - i servizi da bloccare arrivano dal server Cookie Smart (GET /api/v1/sites/{chiave}/config):
 *   un'unica fonte, la stessa del banner;
 * - aggiornamenti: il server Cookie Smart indica l'ultima versione (GET /api/v1/updates/prestashop),
 *   lo zip si scarica dalle release GitHub, si estrae e la migrazione la esegue PrestaShop.
 *
 * @author PC Web Agency
 * @license AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/CookieSmartHtmlRewriter.php';

class CookieSmart extends Module
{
    const CONF_SERVER = 'COOKIESMART_SERVER_URL';
    const CONF_KEY = 'COOKIESMART_SITE_KEY';
    const CONF_BLOCK = 'COOKIESMART_SERVER_BLOCK';
    const CONF_CACHE = 'COOKIESMART_REMOTE_CONFIG';
    const CONF_CACHE_UNTIL = 'COOKIESMART_REMOTE_CONFIG_UNTIL';
    const CONF_UPDATE = 'COOKIESMART_UPDATE';
    const CONF_UPDATE_UNTIL = 'COOKIESMART_UPDATE_UNTIL';

    const DEFAULT_SERVER = 'https://cookiesmart.it';

    /** @var array|null configurazione remota letta in questa richiesta */
    private $remoteConfig;

    public function __construct()
    {
        $this->name = 'cookiesmart';
        $this->tab = 'front_office_features';
        $this->version = '1.2.0';
        $this->author = 'PC Web Agency';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.1.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Cookie Smart');
        $this->description = $this->l('Banner cookie, blocco preventivo degli script, Google Consent Mode v2 e registro dei consensi, secondo le linee guida del Garante privacy.');
        $this->confirmUninstall = $this->l('Disinstallando il modulo il banner cookie non verrà più mostrato. Continuare?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionOutputHTMLBefore')
            && $this->registerHook('displayDashboardTop')
            && Configuration::updateValue(self::CONF_SERVER, self::DEFAULT_SERVER)
            && Configuration::updateValue(self::CONF_BLOCK, 1);
    }

    public function uninstall()
    {
        foreach ([self::CONF_SERVER, self::CONF_KEY, self::CONF_BLOCK, self::CONF_CACHE, self::CONF_CACHE_UNTIL, self::CONF_UPDATE, self::CONF_UPDATE_UNTIL] as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    // -------------------------------------------------------------------------
    // Configurazione nel back office
    // -------------------------------------------------------------------------

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitCookieSmartUpdate')) {
            $output .= $this->performUpdate(); // se riesce ricarica la pagina per la migrazione
        } elseif (Tools::getValue('cookiesmart_upgrade')) {
            $output .= $this->runUpgrade(); // se riesce ricarica la pagina con la conferma
        } elseif ($updated = Tools::getValue('cookiesmart_updated')) {
            $output .= $this->displayConfirmation(sprintf($this->l('Cookie Smart aggiornato alla versione %s.'), Tools::safeOutput($updated)));
        }

        if (Tools::isSubmit('submitCookieSmart')) {
            $server = rtrim(trim((string) Tools::getValue(self::CONF_SERVER)), '/');
            $key = preg_replace('/[^A-Za-z0-9]/', '', (string) Tools::getValue(self::CONF_KEY));

            if (!Validate::isAbsoluteUrl($server) || stripos($server, 'https://') !== 0) {
                $output .= $this->displayError($this->l('Indica l\'indirizzo del server Cookie Smart, con https://'));
            } elseif (strlen($key) < 16) {
                $output .= $this->displayError($this->l('La chiave del sito non è valida: copiala dal pannello Cookie Smart, nella pagina del sito.'));
            } else {
                Configuration::updateValue(self::CONF_SERVER, $server);
                Configuration::updateValue(self::CONF_KEY, $key);
                Configuration::updateValue(self::CONF_BLOCK, (int) Tools::getValue(self::CONF_BLOCK));
                $this->clearRemoteConfig();
                $output .= $this->displayConfirmation($this->l('Impostazioni salvate.'));
            }
        }

        return $output . $this->renderUpdate() . $this->renderStatus() . $this->renderForm();
    }

    private function configureUrl()
    {
        return AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules');
    }

    private function renderForm()
    {
        $form = [
            'form' => [
                'legend' => ['title' => $this->l('Collegamento a Cookie Smart'), 'icon' => 'icon-shield'],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Indirizzo del server'),
                        'name' => self::CONF_SERVER,
                        'required' => true,
                        'desc' => $this->l('Di norma https://cookiesmart.it'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Chiave del sito'),
                        'name' => self::CONF_KEY,
                        'required' => true,
                        'desc' => $this->l('La trovi nel pannello Cookie Smart, nella pagina del sito, alla voce «Chiave del sito».'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Blocco lato server'),
                        'name' => self::CONF_BLOCK,
                        'is_bool' => true,
                        'desc' => $this->l('Consigliato. Gli script e gli iframe dei servizi da bloccare vengono disattivati già nella pagina inviata al browser, che così non li scarica prima del consenso.'),
                        'values' => [
                            ['id' => 'block_on', 'value' => 1, 'label' => $this->l('Sì')],
                            ['id' => 'block_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                ],
                'submit' => ['title' => $this->l('Salva')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitCookieSmart';
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->fields_value = [
            self::CONF_SERVER => Configuration::get(self::CONF_SERVER) ?: self::DEFAULT_SERVER,
            self::CONF_KEY => Configuration::get(self::CONF_KEY),
            self::CONF_BLOCK => (int) Configuration::get(self::CONF_BLOCK),
        ];

        return $helper->generateForm([$form]);
    }

    private function renderStatus()
    {
        if (!$this->isConfigured()) {
            return '<div class="alert alert-info">' . $this->l('Inserisci la chiave del sito per attivare il banner.') . '</div>';
        }

        $cfg = $this->remoteConfig(true);
        if (!$cfg) {
            return '<div class="alert alert-warning">' . $this->l('Impossibile leggere la configurazione dal server: controlla indirizzo e chiave. Il banner viene comunque caricato e blocca i servizi dal browser.') . '</div>';
        }

        return '<div class="alert alert-success">'
            . sprintf($this->l('Collegato a Cookie Smart. Servizi da bloccare: %d. Versione della configurazione: %d.'), count($cfg['blockPatterns'] ?? []), (int) ($cfg['version'] ?? 0))
            . '<br>' . $this->l('Per il link «Preferenze cookie» nel footer usa l\'indirizzo #cookiesmart-preferences (es. in Moduli › Link List).')
            . '</div>';
    }

    // -------------------------------------------------------------------------
    // Aggiornamenti
    // -------------------------------------------------------------------------

    private function renderUpdate()
    {
        // File nuovi già estratti ma migrazione non eseguita (es. pagina chiusa durante l'aggiornamento)
        if ($this->upgradePending()) {
            return '<div class="alert alert-warning">'
                . sprintf($this->l('I file di Cookie Smart %s sono già installati: manca l\'ultimo passaggio dell\'aggiornamento.'), $this->version)
                . ' <a class="btn btn-primary" href="' . htmlspecialchars($this->configureUrl() . '&cookiesmart_upgrade=1', ENT_QUOTES, 'UTF-8') . '">'
                . $this->l('Completa l\'aggiornamento') . '</a></div>';
        }

        $r = $this->availableUpdate(true);
        if (!$r) {
            return '';
        }

        $changelog = !empty($r['changelog']) ? '<div style="margin:10px 0">' . nl2br(htmlspecialchars($r['changelog'], ENT_QUOTES, 'UTF-8')) . '</div>' : '';

        return '<div class="alert alert-warning">'
            . '<strong>' . sprintf($this->l('È disponibile la versione %s di Cookie Smart (installata: %s).'), htmlspecialchars($r['version'], ENT_QUOTES, 'UTF-8'), $this->version) . '</strong>'
            . $changelog
            . '<form method="post" action="' . htmlspecialchars($this->configureUrl(), ENT_QUOTES, 'UTF-8') . '">'
            . '<button type="submit" name="submitCookieSmartUpdate" value="1" class="btn btn-primary">' . sprintf($this->l('Aggiorna alla versione %s'), htmlspecialchars($r['version'], ENT_QUOTES, 'UTF-8')) . '</button>'
            . '</form></div>';
    }

    /** Avviso nella dashboard del back office quando c'è una nuova versione. */
    public function hookDisplayDashboardTop($params)
    {
        if ($this->upgradePending()) {
            $text = $this->l('L\'aggiornamento di Cookie Smart non è stato completato.');
        } elseif ($r = $this->availableUpdate()) {
            $text = sprintf($this->l('È disponibile la versione %s di Cookie Smart.'), htmlspecialchars($r['version'], ENT_QUOTES, 'UTF-8'));
        } else {
            return '';
        }

        $url = $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]);

        return '<div class="alert alert-warning">' . $text
            . ' <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $this->l('Aggiorna') . '</a></div>';
    }

    /** Ultima versione se più recente di quella installata. */
    private function availableUpdate($fresh = false)
    {
        $r = $this->latestRelease($fresh);

        return $r && version_compare($r['version'], $this->version, '>') ? $r : null;
    }

    /**
     * Ultima versione pubblicata, dal server Cookie Smart: in cache per 6 ore (1 ora se il server non
     * risponde o non ci sono release). Si accettano solo zip delle release GitHub.
     */
    private function latestRelease($fresh = false)
    {
        if (!$fresh && (int) Configuration::get(self::CONF_UPDATE_UNTIL) > time()) {
            $cached = json_decode((string) Configuration::get(self::CONF_UPDATE), true);

            return is_array($cached) && $cached ? $cached : null;
        }

        $context = stream_context_create(['http' => ['timeout' => 5, 'header' => "Accept: application/json\r\n"]]);
        $body = Tools::file_get_contents($this->serverUrl() . '/api/v1/updates/prestashop', false, $context, 5);
        $data = $body ? json_decode($body, true) : null;

        $valid = is_array($data) && !empty($data['version']) && is_string($data['version'])
            && preg_match('/^\d+\.\d+(\.\d+)?$/', $data['version'])
            && is_string($data['download_url'] ?? null) && strpos($data['download_url'], 'https://github.com/') === 0;

        Configuration::updateValue(self::CONF_UPDATE, $valid ? json_encode($data) : '');
        Configuration::updateValue(self::CONF_UPDATE_UNTIL, time() + ($valid ? 6 : 1) * 3600);

        return $valid ? $data : null;
    }

    /**
     * Primo passo dell'aggiornamento: scarica lo zip, lo verifica e ne estrae i file nella cartella dei moduli.
     * La migrazione (script upgrade/, versione nel database) la esegue la richiesta successiva: questa ha
     * già caricato la classe del modulo nella versione vecchia.
     */
    private function performUpdate()
    {
        $r = $this->availableUpdate(true);
        if (!$r) {
            return $this->displayError($this->l('Nessun aggiornamento disponibile.'));
        }

        $file = tempnam(_PS_CACHE_DIR_, 'cookiesmart');
        try {
            $context = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1]]);
            $body = Tools::file_get_contents($r['download_url'], false, $context, 30);
            if (!$body || file_put_contents($file, $body) === false) {
                throw new Exception($this->l('Impossibile scaricare l\'aggiornamento da GitHub.'));
            }
            $error = $this->checkPackage($file, isset($r['sha256']) ? $r['sha256'] : null);
            if ($error) {
                throw new Exception($error);
            }

            $zip = new ZipArchive();
            if ($zip->open($file) !== true || !$zip->extractTo(_PS_MODULE_DIR_)) {
                throw new Exception(sprintf($this->l('Impossibile scrivere i file in %s: controlla i permessi della cartella.'), _PS_MODULE_DIR_ . $this->name));
            }
            // La richiesta successiva deve leggere i file nuovi anche con l'OPcache attivo
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = $zip->getNameIndex($i);
                if (function_exists('opcache_invalidate') && substr($name, -4) === '.php') {
                    @opcache_invalidate(_PS_MODULE_DIR_ . $name, true);
                }
            }
            $zip->close();
        } catch (Exception $e) {
            @unlink($file);

            return $this->displayError($this->l('Aggiornamento non riuscito:') . ' ' . $e->getMessage());
        }

        @unlink($file);
        Tools::redirectAdmin($this->configureUrl() . '&cookiesmart_upgrade=1');
    }

    /** Secondo passo: PrestaShop esegue gli script upgrade/ e registra la nuova versione. */
    private function runUpgrade()
    {
        if (!$this->upgradePending()) {
            return '';
        }

        try {
            // Senza zip, upgrade() esegue solo la migrazione dai file già presenti (1.7, 8 e 9)
            $manager = \PrestaShop\PrestaShop\Core\Addon\Module\ModuleManagerBuilder::getInstance()->build();
            if (!$manager->upgrade($this->name)) {
                throw new Exception($this->l('PrestaShop non ha completato l\'aggiornamento.'));
            }
        } catch (Exception $e) {
            return $this->displayError($this->l('Aggiornamento non riuscito:') . ' ' . $e->getMessage());
        }

        Configuration::updateValue(self::CONF_UPDATE_UNTIL, 0);
        Tools::redirectAdmin($this->configureUrl() . '&cookiesmart_updated=' . urlencode($this->version));
    }

    /** I file sono più recenti della versione registrata nel database. */
    private function upgradePending()
    {
        $installed = Db::getInstance()->getValue('SELECT version FROM `' . _DB_PREFIX_ . 'module` WHERE name = \'' . pSQL($this->name) . '\'');

        return $installed && version_compare($installed, $this->version, '<');
    }

    /**
     * Controlli sullo zip prima di installarlo: impronta sha256 (se indicata dal server) e contenuto
     * (solo la cartella cookiesmart/ con il file principale). Restituisce il messaggio d'errore o null.
     */
    private function checkPackage($file, $sha256)
    {
        if ($sha256 && !hash_equals(strtolower($sha256), hash_file('sha256', $file))) {
            return $this->l('Lo zip scaricato non corrisponde alla versione pubblicata.');
        }

        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            return $this->l('Lo zip scaricato non è valido.');
        }
        $valid = $zip->numFiles > 0 && $zip->locateName($this->name . '/' . $this->name . '.php') !== false;
        for ($i = 0; $valid && $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            $valid = strpos($name, $this->name . '/') === 0 && strpos($name, '..') === false;
        }
        $zip->close();

        return $valid ? null : $this->l('Lo zip scaricato non contiene il modulo Cookie Smart.');
    }

    // -------------------------------------------------------------------------
    // Front office
    // -------------------------------------------------------------------------

    /**
     * Pagina HTML pronta per l'invio: script del banner per primo nel <head> e,
     * se attivo, blocco degli script e iframe dei servizi in elenco.
     */
    public function hookActionOutputHTMLBefore($params)
    {
        if (!$this->isConfigured() || !isset($params['html']) || !is_string($params['html'])) {
            return;
        }
        $controller = $this->context->controller ?? null;
        if (!$controller instanceof FrontController) {
            return; // solo il negozio, non il back office
        }

        $html = CookieSmartHtmlRewriter::injectLoader($params['html'], $this->loaderUrl());

        if (Configuration::get(self::CONF_BLOCK)) {
            $cfg = $this->remoteConfig() ?: [];
            $html = (new CookieSmartHtmlRewriter($cfg['blockPatterns'] ?? []))->rewrite($html);
        }

        $params['html'] = $html;
    }

    // -------------------------------------------------------------------------

    private function isConfigured()
    {
        return (bool) Configuration::get(self::CONF_KEY);
    }

    private function serverUrl()
    {
        return rtrim(Configuration::get(self::CONF_SERVER) ?: self::DEFAULT_SERVER, '/');
    }

    /** Indirizzo del loader indicato dal server (es. la CDN); altrimenti quello del server configurato. */
    private function loaderUrl()
    {
        $cfg = $this->remoteConfig() ?: [];
        if (!empty($cfg['loaderUrl']) && is_string($cfg['loaderUrl']) && strpos($cfg['loaderUrl'], 'https://') === 0) {
            return $cfg['loaderUrl'];
        }

        return $this->serverUrl() . '/api/c/' . rawurlencode(Configuration::get(self::CONF_KEY)) . '.js';
    }

    /**
     * Configurazione dal server Cookie Smart, in cache per 6 ore. Se il server non risponde si continua
     * con l'ultima ricevuta (pattern e indirizzo del loader) e si riprova tra 10 minuti.
     */
    private function remoteConfig($fresh = false)
    {
        if ($this->remoteConfig !== null && !$fresh) {
            return $this->remoteConfig ?: null;
        }

        if (!$fresh && (int) Configuration::get(self::CONF_CACHE_UNTIL) > time()) {
            $cached = json_decode((string) Configuration::get(self::CONF_CACHE), true);
            $this->remoteConfig = is_array($cached) ? $cached : [];

            return $this->remoteConfig ?: null;
        }

        $url = $this->serverUrl() . '/api/v1/sites/' . rawurlencode(Configuration::get(self::CONF_KEY)) . '/config';
        $context = stream_context_create(['http' => ['timeout' => 5, 'header' => "Accept: application/json\r\n"]]);
        $body = Tools::file_get_contents($url, false, $context, 5);
        $data = $body ? json_decode($body, true) : null;

        if (is_array($data) && isset($data['blockPatterns'])) {
            Configuration::updateValue(self::CONF_CACHE, json_encode($data));
            Configuration::updateValue(self::CONF_CACHE_UNTIL, time() + 6 * 3600);

            return $this->remoteConfig = $data;
        }

        $last = json_decode((string) Configuration::get(self::CONF_CACHE), true);
        $this->remoteConfig = is_array($last) ? $last : [];
        Configuration::updateValue(self::CONF_CACHE_UNTIL, time() + 600);

        return $this->remoteConfig ?: null;
    }

    private function clearRemoteConfig()
    {
        Configuration::updateValue(self::CONF_CACHE, '');
        Configuration::updateValue(self::CONF_CACHE_UNTIL, 0);
        $this->remoteConfig = null;
    }
}
