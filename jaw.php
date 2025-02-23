<?php
/*
Plugin Name: Jawali Payment Gateway
Description: Allows payments with Jawali Payment Gateway.
Version: 1.2.2
Author: amerHazaa
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Ensure WooCommerce is loaded before initializing the gateway
add_action( 'plugins_loaded', 'init_jawali_gateway', 0 );

function init_jawali_gateway() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    // Include the functions file
    include_once( plugin_dir_path( __FILE__ ) . 'jawfunc.php' );

    // Include the main class file
    include_once( plugin_dir_path( __FILE__ ) . 'jawclss.php' );

    // Add the gateway to WooCommerce
    function add_jawali_gateway_class( $methods ) {
        $methods[] = 'WC_Gateway_Jawali';
        return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'add_jawali_gateway_class' );
}

// Include the admin page
if ( is_admin() ) {
    include_once( plugin_dir_path( __FILE__ ) . 'jadmin.php' );
}
?>