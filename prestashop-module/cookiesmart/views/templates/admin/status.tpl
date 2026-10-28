{**
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
 *}
{if $cookiesmart_connected}
  <div class="alert alert-success">
    {l s='Connected to Cookie Smart.' mod='cookiesmart'}
    {l s='Services to block:' mod='cookiesmart'} <strong>{$cookiesmart_patterns|intval}</strong>.
    {l s='Configuration version:' mod='cookiesmart'} <strong>{$cookiesmart_config_version|intval}</strong>.
    <br>{l s='For the "Cookie preferences" link in the footer use the address #cookiesmart-preferences (e.g. in Modules > Link List).' mod='cookiesmart'}
    <br>{l s='To show the up-to-date list of cookies in your cookie policy, write [cookiesmart_declaration] in its CMS page (Design > Pages).' mod='cookiesmart'}
    {if $cookiesmart_dev_server}
      <br>{l s='Development server:' mod='cookiesmart'} {$cookiesmart_dev_server|escape:'html':'UTF-8'}
    {/if}
  </div>
{else}
  <div class="alert alert-warning">{l s='Unable to read the configuration from the server: check your site key. The banner is loaded anyway and blocks the services in the browser.' mod='cookiesmart'}</div>
{/if}
