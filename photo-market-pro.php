<?php
/**
 * Plugin Name: Photo Market Pro
 * Plugin URI:  https://yoursite.com
 * Description: Digitális fotó értékesítés WooCommerce-hez – kategóriák, szerkesztési opciók, biztonságos letöltési linkek, külső szerver támogatás.

 * Version:     1.6.4

 * Author:      Your Name
 * Text Domain: photo-market-pro
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;


define( 'PMP_VERSION',     '1.6.4' );

define( 'PMP_FILE',        __FILE__ );
define( 'PMP_DIR',         plugin_dir_path( __FILE__ ) );
define( 'PMP_URL',         plugin_dir_url( __FILE__ ) );

// Load install class early (needed for activation hook)
require_once PMP_DIR . 'includes/class-pmp-install.php';

register_activation_hook( __FILE__, [ 'PMP_Install', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PMP_Install', 'deactivate' ] );

add_action( 'plugins_loaded', 'pmp_boot', 5 );

function pmp_boot() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Photo Market Pro:</strong> A WooCommerce plugin szükséges!</p></div>';
        } );
        return;
    }

    require_once PMP_DIR . 'includes/class-pmp-photo.php';
    require_once PMP_DIR . 'includes/class-pmp-edit-options.php';
    require_once PMP_DIR . 'includes/class-pmp-download.php';
    require_once PMP_DIR . 'includes/class-pmp-order.php';
    require_once PMP_DIR . 'includes/class-pmp-r2.php';
    require_once PMP_DIR . 'admin/class-pmp-admin.php';
    require_once PMP_DIR . 'public/class-pmp-public.php';
    require_once PMP_DIR . 'includes/class-pmp-my-account.php';

    PMP_Install::init();
    PMP_Photo::init();
    PMP_Edit_Options::init();
    PMP_Download::init();
    PMP_Order::init();
    PMP_My_Account::init();
    PMP_Admin::init();
    PMP_Public::init();
}
