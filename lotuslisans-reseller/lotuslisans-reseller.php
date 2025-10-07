<?php
/**
 * Plugin Name: LotusLisans Reseller for WooCommerce
 * Plugin URI: https://partner.lotuslisans.com.tr/
 * Description: WooCommerce entegrasyonu ile LotusLisans ürünlerini yönetin.
 * Version: 1.0.0
 * Author: LotusLisans Entegrasyon
 * License: GPLv2 or later
 * Text Domain: lotuslisans-reseller
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'LOTUSLISANS_RESELLER_FILE' ) ) {
    define( 'LOTUSLISANS_RESELLER_FILE', __FILE__ );
}

define( 'LOTUSLISANS_RESELLER_PATH', plugin_dir_path( LOTUSLISANS_RESELLER_FILE ) );
define( 'LOTUSLISANS_RESELLER_URL', plugin_dir_url( LOTUSLISANS_RESELLER_FILE ) );
define( 'LOTUSLISANS_RESELLER_VERSION', '1.0.0' );

require_once LOTUSLISANS_RESELLER_PATH . 'includes/class-lotuslisans-reseller-plugin.php';

function lotuslisans_reseller() {
    return LotusLisans_Reseller_Plugin::instance();
}

lotuslisans_reseller();
