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
<div class="panel">
  <h3><i class="icon-shield"></i> {l s='To activate the banner you need a Cookie Smart account' mod='cookiesmart'}</h3>
  <ol>
    <li>{l s='Create your account on the Cookie Smart website: 14-day free trial, no credit card required.' mod='cookiesmart'}</li>
    <li>{l s='In the dashboard, add your shop with its domain.' mod='cookiesmart'}</li>
    <li>{l s='Copy the "Site key" from your website page, paste it below and save.' mod='cookiesmart'}</li>
  </ol>
  <p>
    <a class="btn btn-primary" href="{$cookiesmart_register_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Create a free account' mod='cookiesmart'}</a>
    <a class="btn btn-default" href="{$cookiesmart_login_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='I already have an account: log in' mod='cookiesmart'}</a>
  </p>
</div>
