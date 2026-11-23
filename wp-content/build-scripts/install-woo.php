<?php
// Installs and activates WooCommerce from wordpress.org using WordPress's own installer.
define('WP_USE_THEMES', false);
require dirname(__DIR__, 2) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
wp_set_current_user(1);
if (!file_exists(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php')) {
    $api = plugins_api('plugin_information', ['slug' => 'woocommerce', 'fields' => ['sections' => false]]);
    if (is_wp_error($api)) { echo $api->get_error_message(); exit(1); }
    $up = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $r = $up->install($api->download_link);
    var_dump($r);
}
$a = activate_plugin('woocommerce/woocommerce.php');
echo is_wp_error($a) ? $a->get_error_message() : "activated\n";
