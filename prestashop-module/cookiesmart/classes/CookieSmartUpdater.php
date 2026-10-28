<?php
/**
 * Cookie Smart for PrestaShop - updates for the version distributed on GitHub.
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
 * Il server Cookie Smart indica l'ultima versione (GET /api/v1/updates/prestashop), lo zip si scarica
 * dalle release GitHub, si verifica (sha256 e contenuto), si estrae e la migrazione la esegue PrestaShop.
 * Il pacchetto per PrestaShop Addons non contiene questa classe: lì gli aggiornamenti li distribuisce Addons.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CookieSmartUpdater
{
    /** @var CookieSmart */
    private $module;

    public function __construct(CookieSmart $module)
    {
        $this->module = $module;
    }

    private function l($string)
    {
        return $this->module->l($string, 'cookiesmartupdater');
    }

    /** Richieste della pagina di configurazione: aggiornamento, migrazione, conferma. */
    public function handleRequest()
    {
        if (Tools::isSubmit('submitCookieSmartUpdate')) {
            return $this->performUpdate(); // se riesce ricarica la pagina per la migrazione
        }
        if (Tools::getValue('cookiesmart_upgrade')) {
            return $this->runUpgrade(); // se riesce ricarica la pagina con la conferma
        }
        $updated = (string) Tools::getValue('cookiesmart_updated');
        if ($updated !== '' && preg_match('/^\d+\.\d+(\.\d+)?$/', $updated)) {
            return $this->module->displayConfirmation(sprintf($this->l('Cookie Smart has been updated to version %s.'), $updated));
        }

        return '';
    }

    /** Avviso nella pagina di configurazione: nuova versione o aggiornamento da completare. */
    public function render()
    {
        // File nuovi già estratti ma migrazione non eseguita (es. pagina chiusa durante l'aggiornamento)
        if ($this->upgradePending()) {
            return $this->display([
                'cookiesmart_pending' => true,
                'cookiesmart_version' => $this->module->version,
                'cookiesmart_upgrade_url' => $this->module->configureUrl() . '&cookiesmart_upgrade=1',
            ]);
        }

        $r = $this->availableUpdate(true);
        if (!$r) {
            return '';
        }

        return $this->display([
            'cookiesmart_pending' => false,
            'cookiesmart_new_version' => $r['version'],
            'cookiesmart_version' => $this->module->version,
            'cookiesmart_changelog' => isset($r['changelog']) ? (string) $r['changelog'] : '',
            'cookiesmart_form_url' => $this->module->configureUrl(),
        ]);
    }

    /** Avviso nella dashboard quando c'è una nuova versione o l'aggiornamento non è finito. */
    public function dashboardNotice()
    {
        $pending = $this->upgradePending();
        $r = $pending ? null : $this->availableUpdate();
        if (!$pending && !$r) {
            return '';
        }

        $context = Context::getContext();
        $context->smarty->assign([
            'cookiesmart_pending' => $pending,
            'cookiesmart_new_version' => $r ? $r['version'] : '',
            'cookiesmart_configure_url' => $context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->module->name]),
        ]);

        return $this->module->display(_PS_MODULE_DIR_ . $this->module->name . '/' . $this->module->name . '.php', 'views/templates/admin/dashboard_update.tpl');
    }

    private function display(array $vars)
    {
        Context::getContext()->smarty->assign($vars);

        return $this->module->display(_PS_MODULE_DIR_ . $this->module->name . '/' . $this->module->name . '.php', 'views/templates/admin/update.tpl');
    }

    /** Ultima versione se più recente di quella installata. */
    private function availableUpdate($fresh = false)
    {
        $r = $this->latestRelease($fresh);

        return $r && version_compare($r['version'], $this->module->version, '>') ? $r : null;
    }

    /**
     * Ultima versione pubblicata, dal server Cookie Smart: in cache per 6 ore (1 ora se il server non
     * risponde o non ci sono release). Si accettano solo zip delle release GitHub.
     */
    private function latestRelease($fresh = false)
    {
        if (!$fresh && (int) Configuration::get(CookieSmart::CONF_UPDATE_UNTIL) > time()) {
            $cached = json_decode((string) Configuration::get(CookieSmart::CONF_UPDATE), true);

            return is_array($cached) && $cached ? $cached : null;
        }

        $context = stream_context_create(['http' => ['timeout' => 5, 'header' => "Accept: application/json\r\n"]]);
        $body = Tools::file_get_contents($this->module->serverUrl() . '/api/v1/updates/prestashop', false, $context, 5);
        $data = $body ? json_decode($body, true) : null;

        $valid = is_array($data) && !empty($data['version']) && is_string($data['version'])
            && preg_match('/^\d+\.\d+(\.\d+)?$/', $data['version'])
            && isset($data['download_url']) && is_string($data['download_url'])
            && strpos($data['download_url'], 'https://github.com/') === 0;

        Configuration::updateValue(CookieSmart::CONF_UPDATE, $valid ? json_encode($data) : '');
        Configuration::updateValue(CookieSmart::CONF_UPDATE_UNTIL, time() + ($valid ? 6 : 1) * 3600);

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
            return $this->module->displayError($this->l('No update available.'));
        }

        $file = tempnam(_PS_CACHE_DIR_, 'cookiesmart');
        try {
            $context = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1]]);
            $body = Tools::file_get_contents($r['download_url'], false, $context, 30);
            if (!$body || file_put_contents($file, $body) === false) {
                throw new Exception($this->l('Unable to download the update from GitHub.'));
            }
            $error = $this->checkPackage($file, isset($r['sha256']) ? $r['sha256'] : null);
            if ($error) {
                throw new Exception($error);
            }

            $zip = new ZipArchive();
            if ($zip->open($file) !== true || !$zip->extractTo(_PS_MODULE_DIR_)) {
                throw new Exception(sprintf($this->l('Unable to write the files in %s: check the folder permissions.'), _PS_MODULE_DIR_ . $this->module->name));
            }
            // La richiesta successiva deve leggere i file nuovi anche con l'OPcache attivo
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = $zip->getNameIndex($i);
                if (function_exists('opcache_invalidate') && substr($name, -4) === '.php') {
                    opcache_invalidate(_PS_MODULE_DIR_ . $name, true);
                }
            }
            $zip->close();
        } catch (Exception $e) {
            $this->removeFile($file);

            return $this->module->displayError($this->l('Update failed:') . ' ' . $e->getMessage());
        }

        $this->removeFile($file);
        Tools::redirectAdmin($this->module->configureUrl() . '&cookiesmart_upgrade=1');

        return '';
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
            if (!$manager->upgrade($this->module->name)) {
                throw new Exception($this->l('PrestaShop did not complete the update.'));
            }
        } catch (Exception $e) {
            return $this->module->displayError($this->l('Update failed:') . ' ' . $e->getMessage());
        }

        Configuration::updateValue(CookieSmart::CONF_UPDATE_UNTIL, 0);
        Tools::redirectAdmin($this->module->configureUrl() . '&cookiesmart_updated=' . urlencode($this->module->version));

        return '';
    }

    /** I file sono più recenti della versione registrata nel database. */
    private function upgradePending()
    {
        $installed = Db::getInstance()->getValue('SELECT version FROM `' . _DB_PREFIX_ . 'module` WHERE name = \'' . pSQL($this->module->name) . '\'');

        return $installed && version_compare($installed, $this->module->version, '<');
    }

    /**
     * Controlli sullo zip prima di installarlo: impronta sha256 (se indicata dal server) e contenuto
     * (solo la cartella cookiesmart/ con il file principale). Restituisce il messaggio d'errore o null.
     */
    private function checkPackage($file, $sha256)
    {
        if ($sha256 && !hash_equals(strtolower($sha256), hash_file('sha256', $file))) {
            return $this->l('The downloaded zip does not match the published version.');
        }

        $name = $this->module->name;
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            return $this->l('The downloaded zip is not valid.');
        }
        $valid = $zip->numFiles > 0 && $zip->locateName($name . '/' . $name . '.php') !== false;
        for ($i = 0; $valid && $i < $zip->numFiles; ++$i) {
            $entry = $zip->getNameIndex($i);
            $valid = strpos($entry, $name . '/') === 0 && strpos($entry, '..') === false;
        }
        $zip->close();

        return $valid ? null : $this->l('The downloaded zip does not contain the Cookie Smart module.');
    }

    private function removeFile($file)
    {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
