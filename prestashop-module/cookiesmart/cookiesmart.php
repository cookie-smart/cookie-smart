<?php
/**
 * Cookie Smart for PrestaShop 1.7.1+, 8 and 9.
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
 * - prints the banner script as the first element of the <head> (no theme changes);
 * - server-side blocking: the scripts and iframes of the listed services are disabled before the page
 *   reaches the browser, which therefore does not even download them;
 * - the services to block come from the Cookie Smart service (GET /api/v1/sites/{key}/config):
 *   one source, the same used by the banner.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/CookieSmartHtmlRewriter.php';
// @github-only-start
require_once __DIR__ . '/classes/CookieSmartUpdater.php';
// @github-only-end

class CookieSmart extends Module
{
    public const CONF_SERVER = 'COOKIESMART_SERVER_URL';
    public const CONF_KEY = 'COOKIESMART_SITE_KEY';
    public const CONF_BLOCK = 'COOKIESMART_SERVER_BLOCK';
    public const CONF_CACHE = 'COOKIESMART_REMOTE_CONFIG';
    public const CONF_CACHE_UNTIL = 'COOKIESMART_REMOTE_CONFIG_UNTIL';
    public const CONF_UPDATE = 'COOKIESMART_UPDATE';
    public const CONF_UPDATE_UNTIL = 'COOKIESMART_UPDATE_UNTIL';

    /** Cookie Smart service. For development it can be changed only from the command line (COOKIESMART_SERVER_URL). */
    public const DEFAULT_SERVER = 'https://cookiesmart.it';

    /** @var array|null remote configuration read during this request */
    private $remoteConfig;

    public function __construct()
    {
        $this->name = 'cookiesmart';
        $this->tab = 'front_office_features';
        $this->version = '1.4.0';
        $this->author = 'PC Web Agency';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.1.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Cookie Smart');
        $this->description = $this->l('Cookie banner, prior blocking of scripts, Google Consent Mode v2 and consent log, following the GDPR and the guidelines of the Italian Data Protection Authority.');
        $this->confirmUninstall = $this->l('If you uninstall the module, the cookie banner will no longer be shown. Continue?');
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
    // Back office configuration
    // -------------------------------------------------------------------------

    public function getContent()
    {
        $output = '';
        $update = '';
        // @github-only-start
        // Aggiornamento in corso, migrazione o conferma; poi l'avviso della nuova versione (solo GitHub)
        $updater = new CookieSmartUpdater($this);
        $output .= $updater->handleRequest();
        $update = $updater->render();
        // @github-only-end

        if (Tools::isSubmit('submitCookieSmart')) {
            $key = preg_replace('/[^A-Za-z0-9]/', '', (string) Tools::getValue(self::CONF_KEY));

            if (strlen($key) < 16) {
                $output .= $this->displayError($this->l('The site key is not valid: copy it from the Cookie Smart dashboard, on your website page.'));
            } else {
                Configuration::updateValue(self::CONF_KEY, $key);
                Configuration::updateValue(self::CONF_BLOCK, (int) Tools::getValue(self::CONF_BLOCK));
                $this->clearRemoteConfig();
                $output .= $this->displayConfirmation($this->l('Settings saved.'));
            }
        }

        return $output . $update . $this->renderStatus() . $this->renderForm();
    }

    /** Address of the module configuration page. */
    public function configureUrl()
    {
        return AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules');
    }

    private function renderForm()
    {
        $form = [
            'form' => [
                'legend' => ['title' => $this->l('Connection to Cookie Smart'), 'icon' => 'icon-shield'],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Site key'),
                        'name' => self::CONF_KEY,
                        'required' => true,
                        'desc' => $this->l('You can find it in the Cookie Smart dashboard, on your website page, under "Site key".') . ' ' . $this->l('Dashboard login:') . ' ' . $this->serverUrl() . '/app/login',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Server-side blocking'),
                        'name' => self::CONF_BLOCK,
                        'is_bool' => true,
                        'desc' => $this->l('Recommended. The scripts and iframes of the services to block are disabled in the page sent to the browser, which does not download them before consent.'),
                        'values' => [
                            ['id' => 'block_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'block_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                ],
                'submit' => ['title' => $this->l('Save')],
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
            self::CONF_KEY => Configuration::get(self::CONF_KEY),
            self::CONF_BLOCK => (int) Configuration::get(self::CONF_BLOCK),
        ];

        return $helper->generateForm([$form]);
    }

    /**
     * Without a site key: setup guide for merchants who installed the module without a Cookie Smart account.
     * With a site key: connection status.
     */
    private function renderStatus()
    {
        if (!$this->isConfigured()) {
            $this->context->smarty->assign([
                'cookiesmart_register_url' => $this->serverUrl() . '/app/register',
                'cookiesmart_login_url' => $this->serverUrl() . '/app/login',
            ]);

            return $this->display(__FILE__, 'views/templates/admin/setup.tpl');
        }

        $cfg = $this->remoteConfig(true);
        $this->context->smarty->assign([
            'cookiesmart_connected' => (bool) $cfg,
            'cookiesmart_patterns' => $cfg ? count(isset($cfg['blockPatterns']) ? $cfg['blockPatterns'] : []) : 0,
            'cookiesmart_config_version' => $cfg ? (int) (isset($cfg['version']) ? $cfg['version'] : 0) : 0,
            // Address different from the default one: development only (set from the command line)
            'cookiesmart_dev_server' => $this->serverUrl() !== self::DEFAULT_SERVER ? $this->serverUrl() : '',
        ]);

        return $this->display(__FILE__, 'views/templates/admin/status.tpl');
    }

    /** Back office dashboard notice until the module is connected. */
    public function hookDisplayDashboardTop($params)
    {
        // PrestaShop runs this hook in the title bar of every back office page:
        // notices are shown only on the dashboard (the module configuration page has its own)
        $controller = isset($this->context->controller) ? $this->context->controller : null;
        if (!$controller || (isset($controller->controller_name) ? $controller->controller_name : '') !== 'AdminDashboard') {
            return '';
        }

        if (!$this->isConfigured()) {
            $this->context->smarty->assign('cookiesmart_configure_url', $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]));

            return $this->display(__FILE__, 'views/templates/admin/dashboard_setup.tpl');
        }

        // @github-only-start
        return (new CookieSmartUpdater($this))->dashboardNotice();
        // @github-only-end

        return '';
    }

    // -------------------------------------------------------------------------
    // Front office
    // -------------------------------------------------------------------------

    /**
     * HTML page ready to be sent: banner script first in the <head> and, if enabled,
     * blocking of the scripts and iframes of the listed services.
     */
    public function hookActionOutputHTMLBefore($params)
    {
        if (!$this->isConfigured() || !isset($params['html']) || !is_string($params['html'])) {
            return;
        }
        $controller = isset($this->context->controller) ? $this->context->controller : null;
        if (!$controller instanceof FrontController) {
            return; // shop only, not the back office
        }

        $html = CookieSmartHtmlRewriter::injectLoader($params['html'], $this->loaderUrl());

        if (Configuration::get(self::CONF_BLOCK)) {
            $cfg = $this->remoteConfig() ?: [];
            $html = (new CookieSmartHtmlRewriter(isset($cfg['blockPatterns']) ? $cfg['blockPatterns'] : []))->rewrite($html);
        }

        $params['html'] = $html;
    }

    // -------------------------------------------------------------------------

    private function isConfigured()
    {
        return (bool) Configuration::get(self::CONF_KEY);
    }

    public function serverUrl()
    {
        return rtrim(Configuration::get(self::CONF_SERVER) ?: self::DEFAULT_SERVER, '/');
    }

    /** Banner script address provided by the service (e.g. the CDN); otherwise the default one. */
    private function loaderUrl()
    {
        $cfg = $this->remoteConfig() ?: [];
        if (!empty($cfg['loaderUrl']) && is_string($cfg['loaderUrl']) && strpos($cfg['loaderUrl'], 'https://') === 0) {
            return $cfg['loaderUrl'];
        }

        return $this->serverUrl() . '/api/c/' . rawurlencode(Configuration::get(self::CONF_KEY)) . '.js';
    }

    /**
     * Configuration from the Cookie Smart service, cached for 6 hours. If the service does not answer, the last
     * configuration received is kept (patterns and banner script address) and a new attempt is made after 10 minutes.
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
