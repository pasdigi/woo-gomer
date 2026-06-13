<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Woo_Gomer_Webhook_Handler {

    public static function register_routes() {
        // Route untuk menerima webhook dari server API
        register_rest_route( 'woo_gomer/v1', '/webhook', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_webhook' ),
            'permission_callback' => '__return_true'
        ));

        // Route BARU untuk ajax polling dari halaman receipt (browser pembeli)
        register_rest_route( 'woo_gomer/v1', '/check-status/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'check_order_status' ),
            'permission_callback' => '__return_true'
        ));
    }

    public static function handle_webhook( WP_REST_Request $request ) {
        $settings = get_option( 'woocommerce_woo_gomer_settings' );
        $secret = isset( $settings['api_key'] ) ? $settings['api_key'] : '';

        if ( empty( $secret ) ) return new WP_REST_Response( 'Konfigurasi API Key belum diatur', 500 );

        $payload = $request->get_body();
        $signature = $request->get_header( 'x_signature' );
        $calculated = hash_hmac( 'sha256', $payload, $secret );

        if ( ! hash_equals( $calculated, (string)$signature ) ) {
            return new WP_REST_Response( 'WEBHOOK INVALID - Akses ditolak!', 403 );
        }

        $data = json_decode( $payload, true );
        $order_id_raw = '';
        $status       = '';

        if ( isset( $data[0]['payload'] ) ) {
            $order_id_raw = $data[0]['payload']['order_id'] ?? '';
            $status       = $data[0]['payload']['transaction_status'] ?? '';
        } else if ( isset( $data['order_id'] ) ) {
            $order_id_raw = $data['order_id'] ?? '';
            $status       = $data['transaction_status'] ?? '';
        }

        if ( ! empty( $order_id_raw ) ) {
            $order_id = str_replace( array('QRIS-', 'INV-', 'TEST-'), '', $order_id_raw );
            $order = wc_get_order( $order_id );

            if ( $order ) {
                if ( $status === 'settlement' || $status === 'success' ) {
                    $order->payment_complete();
                    $order->add_order_note( 'Pembayaran Woo Gomer lunas via Webhook.' );
                } else if ( in_array( $status, array( 'expire', 'cancel', 'deny' ) ) ) {
                    $order->update_status( 'cancelled', 'Pembayaran dibatalkan/kedaluwarsa via Webhook.' );
                }
                return new WP_REST_Response( 'WEBHOOK VALID', 200 );
            }
        }
        return new WP_REST_Response( 'Payload tidak cocok / Order tidak ditemukan', 400 );
    }

    // Fungsi Endpoint untuk melayani Polling Status
    public static function check_order_status( WP_REST_Request $request ) {
        $order_id = (int) $request->get_param( 'id' );
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_REST_Response( array( 'status' => 'not_found' ), 404 );
        }

        // Jika lunas dari jalur manapun (termasuk webhook)
        if ( $order->is_paid() ) {
            return new WP_REST_Response( array( 'status' => 'paid' ), 200 );
        }

        // Cek kedaluwarsa 15 Menit di level Database
        $generated_time = (int) $order->get_meta( '_woo_gomer_generated_time' );
        $time_limit = 15 * 60; // 900 Detik

        // Jika order masih "pending" tapi waktunya sudah lewat 15 menit sejak di-generate
        if ( $order->get_status() === 'pending' && ( time() - $generated_time ) > $time_limit ) {
            // Set expired ke database
            $order->update_status( 'cancelled', 'Auto-Expired via Polling (Lebih dari 15 Menit).' );
            return new WP_REST_Response( array( 'status' => 'expired' ), 200 );
        }

        // Jika status sengaja dibatalkan / gagal
        if ( $order->get_status() === 'cancelled' || $order->get_status() === 'failed' ) {
            return new WP_REST_Response( array( 'status' => 'expired' ), 200 );
        }

        return new WP_REST_Response( array( 'status' => 'pending' ), 200 );
    }
}