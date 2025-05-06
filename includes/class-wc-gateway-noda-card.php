<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_Noda_Card extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'noda_card';
        $this->icon = NODA_PLUGIN_URL . 'assets/images/noda.png';
        $this->method_title       = __( 'NodaCard Gateway', 'noda-card-gateway' );
        $this->method_description = __( 'Accept credit and debit card gateway securely via Noda.', 'noda-card-gateway' );
        $this->has_fields         = false;

        // Define form fields and load settings.
        $this->init_form_fields();
        $this->init_settings();

        // Load saved settings.
        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled     = $this->get_option( 'enabled' );
        $this->test_mode   = $this->get_option( 'sandbox_testing' ) === 'yes' ? true : false;
        $this->api_key     = $this->get_option( 'api_key' );
        $this->signature   = $this->get_option( 'signature' );
        $this->shop_id     = $this->get_option( 'shop_id' );
    
        // Save admin options.
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
    }

    // Define the admin settings fields.
    public function init_form_fields() {
        // Define the plugin root URL and logo image HTML
        $plugin_root_url = plugin_dir_url( dirname( __FILE__ ) );
        $logo_img = '<img src="' . esc_url( $plugin_root_url . 'assets/images/noda-plum-80px.png' ) . '" alt="' . esc_attr__( 'Noda Logo', 'noda-card-gateway' ) . '" style="height: 24px; vertical-align: middle; margin-left: 8px;" />';

        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Enable/Disable', 'noda-card-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable NodaCard Gateway', 'noda-card-gateway' ),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __( 'Title', 'noda-card-gateway' ),
                'type'        => 'text',
                'description' => __( 'Title shown to customers during checkout.', 'noda-card-gateway' ),
                'default'     => __( 'Debit or Credit Card Payment', 'noda-card-gateway' ),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __( 'Description', 'noda-card-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'Description shown to customers during checkout.', 'noda-card-gateway' ),
                'default'     => __( 'Pay securely using your credit or debit card with Noda.', 'noda-card-gateway' ),
                'desc_tip'    => true,
            ],
            'sandbox_testing' => [
                'title'       => __( 'Sandbox Testing Mode', 'noda-card-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable sandbox mode for testing', 'noda-card-gateway' ),
                'default'     => 'yes',
                'description' => __( 'Use sandbox environment for testing gateway.', 'noda-card-gateway' ),
                'desc_tip'    => true, // Show description as tooltip instead of below
            ],
            'api_key' => [
                'title'       => __( 'API Key', 'noda-card-gateway' ),
                'type'        => 'password',
                'description' => __( 'Required for processing gateway. Visit Noda website to get your API Key.', 'noda-card-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'signature' => [
                'title'       => __( 'Signature', 'noda-card-gateway' ),
                'type'        => 'password',
                'description' => __( 'Required for processing gateway. Visit Noda website to get your Signature Key.', 'noda-card-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'shop_id' => [
                'title'       => __( 'Shop ID', 'noda-card-gateway' ),
                'type'        => 'password',
                'description' => __( 'Required for processing gateway. Visit Noda website to get your Shop Key.', 'noda-card-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ]
            
        ];
    }
   // Output the settings form on the Manage page.
    public function admin_options() {
        ?>
        <h2><?php esc_html_e( 'NodaCard Gateway', 'noda-card-gateway' ); ?></h2>
        <p><?php esc_html_e( 'Accept credit and debit cards online securely via Noda.', 'noda-card-gateway' ); ?></p>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }
    // Process the order & payment
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
    
        // Prepare payment data to send to Noda API
        $payment_data = [
            'amount'      => $order->get_total(),
            'currency'    => $order->get_currency(),
            'order_id'    => $order_id,
            'return_url'  => $this->get_return_url( $order ),
            // Add other required fields like card details or token from checkout form
        ];
    
        // Process payment with Noda API
        $response = $this->create_noda_payment_session( $payment_data );
    
        if ( $response && $response['status'] === 'success' ) {
            // Payment successful
            $order->payment_complete( $response['transaction_id'] );
            $order->add_order_note( 'Noda payment completed. Transaction ID: ' . $response['transaction_id'] );
    
            // Reduce stock levels, empty cart, etc.
            wc_reduce_stock_levels( $order_id );
            WC()->cart->empty_cart();
    
            // Return success and redirect to thank you page
            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            ];
        } else {
            // Payment failed
            wc_add_notice( 'Payment error: ' . $response['message'], 'error' );
            return [
                'result'   => 'fail',
                'redirect' => '',
            ];
        }
    }

    // Create a payment session with Noda API
    private function create_noda_payment_session( $payment_data ) {
        // Set the API endpoint based on test mode
        $api_endpoint = $this->test_mode ? 'https://sandbox.noda.com/api/v1/payments' : 'https://api.noda.com/v1/payments';
    
        // Prepare headers
        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
            'Signature'     => $this->signature,
            'Shop-ID'       => $this->shop_id,
        ];
    
        // Send request to Noda API
        $response = wp_remote_post( $api_endpoint, [
            'method'      => 'POST',
            'headers'     => $headers,
            'body'        => json_encode( $payment_data ),
            'timeout'     => 45,
            'data_format' => 'body',
        ] );
    
        // Handle response
        if ( is_wp_error( $response ) ) {
            return [
                'status'  => 'error',
                'message' => __( 'Payment request failed. Please try again.', 'noda-card-gateway' ),
            ];
        }
    
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
    
}
