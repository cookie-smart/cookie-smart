<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('cookie_smart_settings');
delete_transient('cookie_smart_remote_config');
delete_option('cookie_smart_last_config');
delete_transient('cookie_smart_update');
