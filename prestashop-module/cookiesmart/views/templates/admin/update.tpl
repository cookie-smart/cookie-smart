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
{if $cookiesmart_pending}
  <div class="alert alert-warning">
    {l s='The files of Cookie Smart %s are already installed: the last step of the update is missing.' sprintf=[$cookiesmart_version] mod='cookiesmart'}
    <a class="btn btn-primary" href="{$cookiesmart_upgrade_url|escape:'html':'UTF-8'}">{l s='Complete the update' mod='cookiesmart'}</a>
  </div>
{else}
  <div class="alert alert-warning">
    <strong>{l s='Version %1$s of Cookie Smart is available (installed: %2$s).' sprintf=[$cookiesmart_new_version, $cookiesmart_version] mod='cookiesmart'}</strong>
    {if $cookiesmart_changelog}
      <div style="margin:10px 0">{$cookiesmart_changelog|escape:'html':'UTF-8'|nl2br}</div>
    {/if}
    <form method="post" action="{$cookiesmart_form_url|escape:'html':'UTF-8'}">
      <button type="submit" name="submitCookieSmartUpdate" value="1" class="btn btn-primary">{l s='Update to version %s' sprintf=[$cookiesmart_new_version] mod='cookiesmart'}</button>
    </form>
  </div>
{/if}
