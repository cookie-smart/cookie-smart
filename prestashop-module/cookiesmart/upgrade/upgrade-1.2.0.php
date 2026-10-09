<?php
/**
 * 1.2.0: avviso degli aggiornamenti nella dashboard del back office.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_0($module)
{
    return $module->registerHook('displayDashboardTop');
}
