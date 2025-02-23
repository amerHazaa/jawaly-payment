<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Ensure the function is declared only once
if ( ! function_exists('loginAndAuthenticate') ) {
    function loginAndAuthenticate() {
        // Your authentication logic here
    }
}

// Initiate connection to jawali server on page load
add_action('wp_loaded', 'loginAndAuthenticate', 10);

class WC_Gateway_Jawali extends WC_Payment_Gateway {

    private $client_id;
    private $client_secret;
    private $login_url;
    private $other_url;
    private $access_token;
    private $login_token;

    public function __construct() {
        $this->id                 = 'jawali';
        $this->icon               = '';
        $this->has_fields         = true;
        $this->method_title       = 'Jawali Payment';
        $this->method_description = 'Allows payments with Jawali Payment Gateway.';

        $this->init_form_fields();
        $this->init_settings();

        // Set fixed title and description
        $this->title        = 'Jawali Payment';
        $this->description  = 'Pay by Jawali';

        $this->client_id    = $this->get_option( 'client_id' );
        $this->client_secret = $this->get_option( 'client_secret' );
        $this->login_url    = $this->get_option( 'login_url', 'https://app.wecash.com.ye:8493/paygate/oauth/token' );
        $this->other_url    = $this->get_option( 'other_url', 'https://app.wecash.com.ye:8493/paygate/v1/ws/callWS' );

        $this->login_token  = get_option( 'jawali_login_token' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'check_response' ) );

        $this->access_token = get_transient( 'jawali_access_token' );

        // Enqueue custom script for handling validation and payment
        if ( is_checkout() && $this->is_available() ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_inline_script' ) );
        }
    }

    public function enqueue_inline_script() {
        require_once 'jawfunc.php';
        wp_enqueue_script( 'jquery' );

        $amount = WC()->cart->total;
        $currency_code = get_woocommerce_currency();

        $inline_script = get_inline_script($this->other_url, $this->login_token, $this->access_token, $this->client_id, $this->client_secret, $amount, $currency_code);
        wp_add_inline_script( 'jquery', $inline_script );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Jawali Payment Gateway',
                'default' => 'yes'
            )
        );
    }

    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wp_kses_post( $this->description ) );
        }
        ?>
        <p class="form-row form-row-wide">
            <label for="jawali_payment_code">Payment Code <span class="required">*</span></label>
            <input type="text" class="input-text" name="jawali_payment_code" id="jawali_payment_code" placeholder="Enter your payment code" />
            <button type="button" class="button" id="jawali-validate-button">Validate Payment Code</button>
        </p>
        <div id="jawali-validation-result" style="display:none;"></div>
        <?php
    }

    public function receipt_page( $order_id ) {
        echo '<p>Thank you for your order, please click the button below to pay with Jawali.</p>';
        echo $this->generate_payment_form( $order_id );
    }

    public function generate_payment_form( $order_id ) {
        $order = wc_get_order( $order_id );
        $amount = $order->get_total();
        $currency = get_woocommerce_currency();

        $form = '<form id="jawali-payment-form" action="' . esc_url( $this->get_return_url( $order ) ) . '" method="POST">';
        $form .= '<input type="hidden" name="order_id" value="' . esc_attr( $order_id ) . '">';
        $form .= '<input type="hidden" name="amount" value="' . esc_attr( $amount ) . '">';
        $form .= '<input type="hidden" name="currency" value="' . esc_attr( $currency ) . '">';
        $form .= '<input type="hidden" name="payment_code" id="jawali_hidden_payment_code" value="">';
        $form .= '<button type="submit" class="button alt" id="jawali-pay-button" style="display:none;">Pay with Jawali</button>';
        $form .= '</form>';

        return $form;
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $amount = $order->get_total();
        $payment_code = isset($_POST['payment_code']) ? sanitize_text_field($_POST['payment_code']) : '';

        // Process payment
        $response = process_payment($payment_code, $amount, $order->get_billing_phone());

        if ($response['result'] == 'success') {
            $order->payment_complete();
            wc_reduce_stock_levels( $order_id );
            WC()->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        } else {
            wc_add_notice('Payment error: ' . $response['message'], 'error');
            return array(
                'result' => 'failure',
                'redirect' => '',
            );
        }
    }

    private function call_jawali_api( $order ) {
        $url = $this->other_url;
        $args = array(
            'body'    => json_encode( array(
                'header' => array(
                    'serviceDetail' => array(
                        'corrID'      => uniqid(),
                        'domainName'  => 'MerchantDomain',
                        'serviceName' => 'PAYAG.ECOMMCASHOUT',
                    ),
                ),
                'body' => array(
                    'amount'   => $order->get_total(),
                    'currency' => get_woocommerce_currency(),
                    'order_id' => $order->get_id(),
                ),
            ) ),
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type'  => 'application/json',
            ),
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            $error_message = 'Jawali API Error: ' . $response->get_error_message();
            error_log($error_message); // تسجيل الخطأ في سجلات الخادم
            return array(
                'result' => 'failure',
                'message' => 'Payment processing failed. Please try again.',
                'debug' => $error_message // إرسال تفاصيل الخطأ للتصحيح (للاستخدام في البيئة التطويرية فقط)
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['responseBody']['status'] ) && $data['responseBody']['status'] == '00' ) {
            return array( 'result' => 'success' );
        } else {
            $error_message = 'Jawali API Error: ' . ($data['responseBody']['message'] ?? 'Unknown error');
            error_log($error_message); // تسجيل الخطأ في سجلات الخادم
            return array(
                'result' => 'failure',
                'message' => 'Payment processing failed. Please try again.',
                'debug' => $error_message // إرسال تفاصيل الخطأ للتصحيح (للاستخدام في البيئة التطويرية فقط)
            );
        }
    }
}
?>