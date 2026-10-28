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
<div class="alert alert-warning">
  {if $cookiesmart_pending}
    {l s='The Cookie Smart update has not been completed.' mod='cookiesmart'}
  {else}
    {l s='Version %s of Cookie Smart is available.' sprintf=[$cookiesmart_new_version] mod='cookiesmart'}
  {/if}
  <a href="{$cookiesmart_configure_url|escape:'html':'UTF-8'}">{l s='Update' mod='cookiesmart'}</a>
</div>
