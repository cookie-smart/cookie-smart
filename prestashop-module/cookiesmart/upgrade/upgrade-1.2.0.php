<?php
/**
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
 */

// 1.2.0: back office dashboard notices (displayDashboardTop hook).
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_0($module)
{
    return $module->registerHook('displayDashboardTop');
}
