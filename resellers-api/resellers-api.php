<?php
/**
 * Plugin Name: Resellers API for WooCommerce
 * Plugin URI: https://partner.lotuslisans.com.tr/
 * Description: WooCommerce üzerinden Resellers API entegrasyonu ile ürün ve sipariş yönetimi yapın.
 * Version: 1.0.0
 * Author: Resellers API Entegrasyon
 * License: GPLv2 or later
 * Text Domain: resellers-api
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'RESELLERS_API_FILE' ) ) {
    define( 'RESELLERS_API_FILE', __FILE__ );
}

define( 'RESELLERS_API_PATH', plugin_dir_path( RESELLERS_API_FILE ) );
define( 'RESELLERS_API_URL', plugin_dir_url( RESELLERS_API_FILE ) );
define( 'RESELLERS_API_VERSION', '1.0.0' );

require_once RESELLERS_API_PATH . 'includes/class-resellers-api-plugin.php';

function resellers_api() {
    return Resellers_API_Plugin::instance();
}

resellers_api();
