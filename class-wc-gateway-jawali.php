<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Gateway_Jawali extends WC_Payment_Gateway
{
    private $client_id;
    private $client_secret;
    private $login_url;
    private $other_url;
    private $login_token;
    private $access_token;

    public function __construct()
    {
        $this->id = 'jawali';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = __('Jawali Payment', 'jawali-payment-gateway');
        $this->method_description = __('Allows payments with Jawali Payment Gateway.', 'jawali-payment-gateway');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->client_id = $this->get_option('client_id');
        $this->client_secret = $this->get_option('client_secret');
        $this->login_url = $this->get_option('login_url', 'https://app.wecash.com.ye:8493/paygate/oauth/token');
        $this->other_url = $this->get_option('other_url', 'https://app.wecash.com.ye:8493/paygate/v1/ws/callWS');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

        $this->login_token = get_transient('jawali_login_token');
        $this->access_token = get_transient('jawali_access_token');

        // Enqueue custom script for handling validation and payment
        if (is_checkout() && $this->is_available()) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_inline_script'));
        }
    }

    public function enqueue_inline_script()
    {
        require_once 'jawali-functions.php';
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', get_inline_script());
        wp_localize_script('jquery', 'jawali_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'corr_id' => uniqid(),
            'org_id' => get_option('jawali_org_id', '22000030756'),
            'user_id' => get_option('jawali_user_id', 'gold.time.b'),
            'client_date' => time(),
            'agent_wallet' => get_option('jawali_agent_wallet', '5186'),
            'agent_wallet_pwd' => get_option('jawali_agent_wallet_pwd', '81771188'),
            'access_token' => get_transient('jawali_access_token') ? get_transient('jawali_access_token') : 'None',
            'order_total' => WC()->cart->total,
            'currency' => get_woocommerce_currency(),
            'return_url' => wc_get_checkout_url()
        ));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'jawali-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Jawali Payment Gateway', 'jawali-payment-gateway'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'jawali-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'jawali-payment-gateway'),
                'default' => __('Jawali Payment', 'jawali-payment-gateway'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'jawali-payment-gateway'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'jawali-payment-gateway'),
                'default' => __('Pay with your Jawali account.', 'jawali-payment-gateway'),
            ),
            'client_id' => array(
                'title' => __('Client ID', 'jawali-payment-gateway'),
                'type' => 'text',
                'description' => __('Client ID provided by Jawali.', 'jawali-payment-gateway'),
                'default' => '',
            ),
            'client_secret' => array(
                'title' => __('Client Secret', 'jawali-payment-gateway'),
                'type' => 'password',
                'description' => __('Client Secret provided by Jawali.', 'jawali-payment-gateway'),
                'default' => '',
            ),
            'login_url' => array(
                'title' => __('Login URL', 'jawali-payment-gateway'),
                'type' => 'text',
                'description' => __('URL for Jawali login API.', 'jawali-payment-gateway'),
                'default' => 'https://app.wecash.com.ye:8493/paygate/oauth/token',
            ),
            'other_url' => array(
                'title' => __('Other URL', 'jawali-payment-gateway'),
                'type' => 'text',
                'description' => __('URL for other Jawali API calls.', 'jawali-payment-gateway'),
                'default' => 'https://app.wecash.com.ye:8493/paygate/v1/ws/callWS',
            ),
        );
    }

    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        global $woocommerce;
        $order_id = WC()->session ? WC()->session->get('order_awaiting_payment') : '';
        $order = wc_get_order($order_id);

        ?>
        <p class="form-row form-row-wide">
            <label for="jawali_payment_code"><?php _e('Payment Code', 'jawali-payment-gateway'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="jawali_payment_code" id="jawali_payment_code" placeholder="<?php _e('Enter your payment code', 'jawali-payment-gateway'); ?>" />
        </p>
        <p class="form-row form-row-wide">
            <button type="button" class="button" id="jawali-validate-button"><?php _e('Validate Payment Code', 'jawali-payment-gateway'); ?></button>
        </p>
        <div id="jawali-validation-result" style="margin-top: 20px;"></div>
        <script>
            jQuery(document).ready(function($) {
                $('#jawali-validate-button').on('click', function() {
                    var payment_code = $('#jawali_payment_code').val();
                    var receiver_mobile = $('#billing_phone').val();

                    // تأكد من أن رقم الهاتف يتكون من آخر 9 أرقام فقط
                    receiver_mobile = receiver_mobile.slice(-9);

                    $.ajax({
                        url: jawali_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'jawali_validate_payment_code',
                            payment_code: payment_code,
                            receiver_mobile: receiver_mobile
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#jawali-validation-result').html('<pre style="color: green;">' + JSON.stringify(response.data, null, 2) + '</pre>');
                                $('#jawali_hidden_payment_code').val(payment_code);
                                $('#jawali-pay-button').show();
                                var data = response.data;
                                if (data.responseBody.txnamount == <?php echo $order->get_total(); ?> &&
                                    data.responseBody.txncurrency == '<?php echo get_woocommerce_currency(); ?>' &&
                                    data.responseBody.state == 'PENDING' &&
                                    data.responseBody.receiverMobile == receiver_mobile) {

                                    // Call payment processing function
                                    $.ajax({
                                        url: jawali_params.ajax_url,
                                        type: 'POST',
                                        data: {
                                            action: 'process_jawali_payment',
                                            payment_code: payment_code,
                                            amount: <?php echo $order->get_total(); ?>,
                                            receiver_mobile: receiver_mobile
                                        },
                                        success: function(payment_response) {
                                            if (payment_response.success) {
                                                window.location.href = '<?php echo $this->get_return_url($order); ?>';
                                            } else {
                                                $('#jawali-validation-result').html('<pre style="color: red;">Payment error: ' + payment_response.data.message + '</pre>');
                                            }
                                        },
                                        error: function(payment_response) {
                                            var errorMessage = payment_response.responseJSON && payment_response.responseJSON.message ? payment_response.responseJSON.message : 'Unknown error';
                                            $('#jawali-validation-result').html('<pre style="color: red;">An error occurred during payment processing: ' + errorMessage + '</pre>');
                                        }
                                    });
                                } else {
                                    $('#jawali-validation-result').html('<pre style="color: red;">Payment code is not eligible for this transaction.</pre>');
                                }
                            } else {
                                $('#jawali-validation-result').html('<pre style="color: red;">Payment error: ' + response.data.message + '</pre>');
                            }
                        },
                        error: function(response) {
                            var errorMessage = response.responseJSON && response.responseJSON.message ? response.responseJSON.message : 'Unknown error';
                            $('#jawali-validation-result').html('<pre style="color: red;">An error occurred while validating the payment code: ' + errorMessage + '</pre>');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    public function receipt_page($order_id)
    {
        echo '<p>' . __('Thank you for your order, please click the button below to pay with Jawali.', 'jawali-payment-gateway') . '</p>';
        echo $this->generate_payment_form($order_id);
    }

    public function generate_payment_form($order_id)
    {
        $order = wc_get_order($order_id);
        $amount = $order->get_total();
        $currency = get_woocommerce_currency();

        $form = '<form id="jawali-payment-form" action="' . esc_url($this->get_return_url($order)) . '" method="POST">';
        $form .= '<input type="hidden" name="order_id" value="' . esc_attr($order_id) . '">';
        $form .= '<input type="hidden" name="amount" value="' . esc_attr($amount) . '">';
        $form .= '<input type="hidden" name="currency" value="' . esc_attr($currency) . '">';
        $form .= '<input type="hidden" name="payment_code" id="jawali_hidden_payment_code" value="">';
        $form .= '<button type="submit" class="button alt" id="jawali-pay-button" style="display:none;">' . __('Pay with Jawali', 'jawali-payment-gateway') . '</button>';
        $form .= '</form>';

        return $form;
    }
}
?>