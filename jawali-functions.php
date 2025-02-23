<?php
function get_inline_script() {
    ob_start();
    ?>
    jQuery(document).ready(function($) {
        function generateRandomRefId() {
            return Math.floor(Math.random() * 9000000000) + 1000000000;
        }

        function updateRequestDetails() {
            var payment_code = $('#jawali_payment_code').val();
            var receiver_mobile = $('#billing_phone').val();

            // تأكد من أن رقم الهاتف يتكون من آخر 9 أرقام فقط
            receiver_mobile = receiver_mobile.slice(-9);

            var requestDetails = {
                "header": {
                    "serviceDetail": {
                        "corrID": jawali_params.corr_id,
                        "domainName": "MerchantDomain",
                        "serviceName": "PAYAG.ECOMMERCEINQUIRY"
                    },
                    "signonDetail": {
                        "clientID": "WeCash",
                        "orgID": jawali_params.org_id,
                        "userID": jawali_params.user_id,
                        "externalUser": "External User"
                    },
                    "messageContext": {
                        "clientDate": jawali_params.client_date,
                        "bodyType": "Clear"
                    }
                },
                "body": {
                    "agentWallet": jawali_params.agent_wallet,
                    "password": jawali_params.agent_wallet_pwd,
                    "accessToken": jawali_params.access_token,
                    "voucher": payment_code,
                    "receiverMobile": receiver_mobile,
                    "purpose": "test bill payment",
                    "refId": generateRandomRefId()
                }
            };
        }

        // Update the request details on page load and when the payment code changes
        $('#jawali_payment_code').on('input', updateRequestDetails);

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
                        if (data.responseBody.txnamount == jawali_params.order_total &&
                            data.responseBody.txncurrency == jawali_params.currency &&
                            data.responseBody.state == 'PENDING' &&
                            data.responseBody.receiverMobile == receiver_mobile) {

                            // Call payment processing function
                            $.ajax({
                                url: jawali_params.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'process_jawali_payment',
                                    payment_code: payment_code,
                                    amount: jawali_params.order_total,
                                    receiver_mobile: receiver_mobile
                                },
                                success: function(payment_response) {
                                    if (payment_response.success) {
                                        window.location.href = jawali_params.return_url;
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
    <?php
    return ob_get_clean();
}

function enqueue_jawali_scripts() {
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
add_action('wp_enqueue_scripts', 'enqueue_jawali_scripts');
?>