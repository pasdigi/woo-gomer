<?php
/**
 * Plugin Name: Woo Gopay Merchant QRIS
 * Plugin URI: https://pasdigi.id
 * Description: Integrasi QRIS Gopay Merchant full fitur sistem invoice tanpa kode unik.
 * Version: 1.1.1
 * Author: Kangarifar
 * Author URI: https://fb.me/kangarifar
 * Text Domain: woo-gomer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', 'woo_gomer_init' );
function woo_gomer_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-woo-gomer-gateway.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-woo-gomer-webhook.php';

    add_filter( 'woocommerce_payment_gateways', function( $methods ) {
        $methods[] = 'WC_Woo_Gomer_Gateway';
        return $methods;
    });
}

// Load Media Uploader untuk admin
add_action( 'admin_enqueue_scripts', function() {
    if ( isset($_GET['section']) && $_GET['section'] === 'woo_gomer' ) {
        wp_enqueue_media();
    }
});

add_action( 'rest_api_init', array( 'Woo_Gomer_Webhook_Handler', 'register_routes' ) );
