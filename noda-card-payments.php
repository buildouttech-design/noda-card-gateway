<?php

declare(strict_types=1);

/*
Plugin Name:       NodaCard Payments Gateway for WooCommerce
Plugin URI:        https://github.com/buildouttech-design/noda-card-gateway
Description:       Accept credit and debit card payments securely via Noda with PCI-compliant redirection.
Version:           1.0.0
Requires at least: 5.3.0
Requires PHP:      8.2.0
Author:            Paul Anthony McGowan
Author URI:        https://buildouttechno.com
Text Domain:       noda-card-gateway
License:           GPLv3
License URI:       https://www.gnu.org/licenses/gpl-3.0.html
Domain Path:       /languages
WC requires at least: 4.5
WC tested up to:     8.0
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! defined( 'NODA_PLUGIN_URL' ) ) {
    define( 'NODA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
// WooCommerce compatibility
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});
// Register your card payment gateway
add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
    $gateways[] = 'WC_Gateway_Noda_Card';
    return $gateways;
});
// Include the gateway class
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    require_once __DIR__ . '/includes/class-wc-gateway-noda-card.php';
});

add_filter( 'woocommerce_gateway_title', function( $title, $gateway_id ) {
    if ( 'noda_card' === $gateway_id ) {
        $plugin_root_url = plugin_dir_url( __FILE__ );
        $logo_url = $plugin_root_url . 'assets/images/noda-plum-80px.png';
        $logo_img = '<img src="' . esc_url( $logo_url ) . '" alt="Noda Logo" style="height:24px; vertical-align:middle; margin-left:8px;" />';
        $title .= ' ' . $logo_img;
    }
    return $title;
}, 10, 2 );
// Hide Noda Icon in Checkout Payment Method Description
function noda_hide_icon_checkout_css() {
    if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_admin() ) {
        wp_add_inline_style( 'woocommerce-inline', '
            #payment > ul > li.wc_payment_method.payment_method_noda_card > img {
                display: none;
            }
        ' );
    }
}
add_action( 'wp_enqueue_scripts', 'noda_hide_icon_checkout_css' );



