<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Woo_Gomer_Gateway extends WC_Payment_Gateway {

    public $api_domain;
    public $api_key;
    public $custom_logo;

    public function __construct() {
        $this->id                 = 'woo_gomer';
        $this->icon               = plugins_url( '../assets/qris-logo.png', __FILE__ );
        $this->has_fields         = false;
        $this->method_title       = 'QRIS Gopay Merchant';
        $this->method_description = 'Terintegrasi otomatis dengan sistem Gopay Merchant.';

        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->api_domain   = $this->get_option( 'api_domain' );
        $this->api_key      = $this->get_option( 'api_key' );
        $this->custom_logo  = $this->get_option( 'custom_logo' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Aktifkan Woo Gomer QRIS',
                'default' => 'yes'
            ),
            'title' => array(
                'title'   => 'Judul Checkout',
                'type'    => 'text',
                'default' => 'QRIS (Gopay / All Bank)',
            ),
            'api_domain' => array(
                'title'       => 'API Domain URL',
                'type'        => 'text',
                'description' => 'Contoh: https://customapi.pages.dev',
            ),
            'api_key' => array(
                'title'       => 'API Key / Webhook Secret',
                'type'        => 'password',
                'description' => 'Gunakan 1 key yang sama untuk API dan Webhook.',
            ),
            'custom_logo' => array(
                'title'       => 'Logo Website (150x50px)',
                'type'        => 'image_upload',
                'description' => 'Tempel URL logo atau gunakan tombol upload di samping.',
                'default'     => ''
            )
        );
    }

    public function generate_image_upload_html( $key, $data ) {
        $field_key = $this->get_field_key( $key );
        $defaults  = array('title'=>'', 'disabled'=>false, 'class'=>'', 'css'=>'', 'placeholder'=>'', 'desc_tip'=>false, 'description'=>'');
        $data = wp_parse_args( $data, $defaults );
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="text" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" <?php disabled( $data['disabled'], true ); ?> />
                    <button type="button" class="button" id="woo_gomer_upload_btn" style="margin-left: 5px;">Upload Logo</button>
                    <?php echo $this->get_description_html( $data ); ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function($){
                            $("#woo_gomer_upload_btn").click(function(e) {
                                e.preventDefault();
                                var image = wp.media({ title: "Upload Logo", multiple: false }).open()
                                .on("select", function(){
                                    var uploaded_image = image.state().get("selection").first();
                                    $("#<?php echo esc_js( $field_key ); ?>").val(uploaded_image.toJSON().url);
                                });
                            });
                        });
                    </script>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $endpoint = rtrim( $this->api_domain, '/' ) . '/api/trx';
        
        $body = array(
            'order_id'       => (string) $order->get_id(),
            'amount'         => (int) round( $order->get_total() ),
            'link_name'      => 'Tagihan ' . $order->get_id(),
            'webhook_url'    => rest_url( 'woo_gomer/v1/webhook' ),
            'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_wa'    => $order->get_billing_phone(),
            'customer_email' => $order->get_billing_email(),
        );

        $response = wp_remote_post( $endpoint, array(
            'headers'     => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->api_key ),
            'body'        => wp_json_encode( $body ),
            'timeout'     => 15
        ));

        if ( is_wp_error( $response ) ) {
            wc_add_notice( 'Koneksi API Error: ' . $response->get_error_message(), 'error' );
            return;
        }

        $raw_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw_body, true );

        if ( isset($data['status']) && $data['status'] === 'success' ) {
            $clean_raw_qris = wp_specialchars_decode( $data['raw_qris'], ENT_QUOTES );
            $order->update_meta_data( '_woo_gomer_raw_qris', $clean_raw_qris );
            
            // CATAT WAKTU SERVER SAAT INI UNTUK PATOKAN 15 MENIT
            $order->update_meta_data( '_woo_gomer_generated_time', time() ); 
            $order->save();
            WC()->cart->empty_cart();
            return array( 'result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ) );
        } else {
            $http_code = wp_remote_retrieve_response_code( $response );
            $raw_body_display = empty( $raw_body ) ? 'Respon Kosong' : $raw_body;
            wc_get_logger()->error( "HTTP Code: $http_code | Raw Response: $raw_body_display", array( 'source' => 'woo_gomer' ) );
            wc_add_notice( "Gagal QRIS. HTTP Code: $http_code | Raw: " . esc_html( $raw_body_display ), 'error' );
            return;
        }
    }

    public function receipt_page( $order_id ) {
        $order = wc_get_order( $order_id );
        
        // Cek validitas waktu secara pasif saat halaman dimuat
        $generated_time = (int) $order->get_meta( '_woo_gomer_generated_time' );
        $time_limit     = 15 * 60; // 15 Menit
        $time_passed    = time() - $generated_time;
        $time_left      = $time_limit - $time_passed;

        // Jika sudah lunas atau kedaluwarsa/gagal
        if ( $order->is_paid() ) {
            echo '<div class="woocommerce-message">Pembayaran telah berhasil diterima!</div>';
            return;
        }

        if ( $time_left <= 0 || $order->get_status() === 'cancelled' || $order->get_status() === 'failed' ) {
            if ( $order->get_status() === 'pending' ) {
                $order->update_status( 'cancelled', 'Waktu pembayaran QRIS habis (15 Menit).' );
            }
            echo '<div class="woocommerce-error">QRIS telah kedaluwarsa (Lewat 15 Menit). Silakan buat pesanan baru.</div>';
            echo '<a class="button" href="'.esc_url( wc_get_checkout_url() ).'">Kembali ke Checkout</a>';
            return;
        }

        $raw_qris = $order->get_meta( '_woo_gomer_raw_qris' );
        if ( ! $raw_qris ) return;
        
        $raw_qris = wp_specialchars_decode( $raw_qris, ENT_QUOTES );
        
        echo '<div style="text-align:center; padding:15px; border: 1px solid #eee; border-radius:10px; background:#fafafa;">';
        
        // Logo dengan margin yang lebih rapat
        if ( $this->custom_logo ) {
            echo '<img src="'.esc_url($this->custom_logo).'" style="max-width:150px; max-height:50px; margin-bottom:5px; object-fit:contain;">';
        }

        // Ukuran font dikecilkan menjadi 16px (sebelumnya h2)
        echo '<div style="font-size:16px; font-weight:bold; margin-bottom:10px; color:#333;">Scan QRIS Untuk Bayar</div>';
        
        // Timer Countdown UI
        echo '<div style="font-size:14px; margin-bottom:10px; color:#d9534f; font-weight:bold;">Sisa Waktu: <span id="woo-gomer-timer">--:--</span></div>';

        echo '<div id="woo-gomer-qr" style="display:inline-block; padding:10px; background:#fff; border: 2px solid #333; border-radius:8px;"></div>';
        echo '<div style="margin-top:10px;"><img src="'.esc_url($this->icon).'" style="width:100px;" alt="QRIS Logo"></div>';
        echo '</div>';
        
        ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                // Render QR Code (Hapus esc_js di sini agar tidak di-encode lagi menjadi &amp;)
                new QRCode(document.getElementById("woo-gomer-qr"), {
                    text: "<?php echo $raw_qris; ?>",
                    width: 250, 
                    height: 250,
                    correctLevel : QRCode.CorrectLevel.H
                });

                // Countdown Timer Logic
                var timeLeft = <?php echo (int) $time_left; ?>;
                var timerEl = document.getElementById('woo-gomer-timer');
                var orderId = <?php echo (int) $order_id; ?>;
                var checkUrl = "<?php echo esc_url( rest_url('woo_gomer/v1/check-status/') ); ?>" + orderId;

                var countdown = setInterval(function() {
                    if (timeLeft <= 0) {
                        clearInterval(countdown);
                        timerEl.innerHTML = "KEDALUWARSA";
                        location.reload(); // Reload agar PHP merender halaman "Expired"
                        return;
                    }
                    var m = Math.floor(timeLeft / 60);
                    var s = timeLeft % 60;
                    timerEl.innerHTML = (m < 10 ? "0" : "") + m + ":" + (s < 10 ? "0" : "") + s;
                    timeLeft--;
                }, 1000);

                // Polling Status Logic (Cek ke database setiap 5 detik)
                var polling = setInterval(function() {
                    fetch(checkUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'paid' || data.status === 'expired') {
                            clearInterval(polling);
                            clearInterval(countdown);
                            // Reload halaman, biarkan PHP menangani redirect sukses / render kedaluwarsa
                            location.reload(); 
                        }
                    })
                    .catch(err => console.error("Polling error:", err));
                }, 5000);
            });
        </script>
        <?php
    }
}
