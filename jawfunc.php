<?php
add_action( 'wp_ajax_jawali_process_jawali_payment', 'jawali_process_jawali_payment' );
add_action( 'wp_ajax_nopriv_jawali_process_jawali_payment', 'jawali_process_jawali_payment' );
add_action( 'wp_ajax_jawali_validate_payment_code', 'jawali_validate_payment_code' );
add_action( 'wp_ajax_nopriv_jawali_validate_payment_code', 'jawali_validate_payment_code' );

// Function to process the payment
function process_payment($voucher, $amount, $receiver_mobile) {
    $url = 'https://app.wecash.com.ye:8493/paygate/v1/ws/callWS';
    $access_token = get_transient('jawali_access_token');

    $body = array(
        'header' => array(
            'serviceDetail' => array(
                'corrID' => uniqid(),
                'domainName' => 'MerchantDomain',
                'serviceName' => 'PAYAG.ECOMMCASHOUT'
            ),
            'signonDetail' => array(
                'clientID' => 'WeCash',
                'orgID' => '22000030756',
                'userID' => 'gold.time.b',
                'externalUser' => 'External User'
            ),
            'messageContext' => array(
                'clientDate' => date('YmdHis'),
                'bodyType' => 'Clear'
            )
        ),
        'body' => array(
            'agentWallet' => '5186',
            'password' => '81771188',
            'accessToken' => $access_token,
            'voucher' => $voucher,
            'receiverMobile' => $receiver_mobile,
            'purpose' => 'test bill payment',
            'refId' => uniqid(),
            'amount' => $amount,
            'currency' => 'YER' // تم تحديث العملة إلى YER
        )
    );

    $args = array(
        'body' => json_encode($body),
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 25 // زيادة المهلة إلى 25 ثانية
    );

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        $error_message = 'Jawali Payment Error: ' . $response->get_error_message();
        error_log($error_message); // تسجيل الخطأ في سجلات الخادم
        return array(
            'result' => 'failure',
            'message' => 'Payment processing failed. Please try again.',
            'debug' => $error_message // إرسال تفاصيل الخطأ للتصحيح (للاستخدام في البيئة التطويرية فقط)
        );
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['responseStatus']['systemStatus']) && $data['responseStatus']['systemStatus'] == '0') {
        return array('result' => 'success', 'data' => $data['responseBody']);
    } else {
        $error_message = 'Jawali API Error: ' . ($data['responseStatus']['systemStatusDesc'] ?? 'Unknown error');
        error_log($error_message); // تسجيل الخطأ في سجلات الخادم
        return array(
            'result' => 'failure',
            'message' => 'Payment processing failed. Please try again.',
            'debug' => $error_message // إرسال تفاصيل الخطأ للتصحيح (للاستخدام في البيئة التطويرية فقط)
        );
    }
}

function jawali_validate_payment_code() {
    $payment_code = sanitize_text_field($_POST['payment_code']);
    $receiver_mobile = sanitize_text_field($_POST['receiver_mobile']);

    // تأكد من أن رقم الهاتف يتكون من آخر 9 أرقام فقط
    $receiver_mobile = substr($receiver_mobile, -9);

    // Fetch required options
    $agent_wallet = get_option('jawali_agent_wallet', '5186');
    $agent_wallet_pwd = get_option('jawali_agent_wallet_pwd', '81771188');
    $org_id = get_option('jawali_org_id', '22000030756');
    $user_id = get_option('jawali_user_id', 'gold.time.b');
    $login_token = get_option('jawali_login_token');
    $access_token = get_transient('jawali_access_token');

    // Build the request body
    $body = json_encode(array(
        "header" => array(
            "serviceDetail" => array(
                "corrID" => uniqid(),
                "domainName" => "MerchantDomain",
                "serviceName" => "PAYAG.ECOMMERCEINQUIRY"
            ),
            "signonDetail" => array(
                "clientID" => "WeCash",
                "orgID" => $org_id,
                "userID" => $user_id,
                "externalUser" => "External User"
            ),
            "messageContext" => array(
                "clientDate" => time(),
                "bodyType" => "Clear"
            )
        ),
        "body" => array(
            "agentWallet" => $agent_wallet,
            "password" => $agent_wallet_pwd,
            "accessToken" => $access_token,
            "voucher" => $payment_code,
            "receiverMobile" => $receiver_mobile,
            "txncurrency" => "YER", // تم إضافة هذا الحقل
            "purpose" => "test bill payment",
            "refId" => uniqid()
        )
    ));

    // Initialize cURL
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://app.wecash.com.ye:8493/paygate/v1/ws/callWS',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $login_token,
            'Content-Type: application/json'
        ),
    ));

    // Execute the request
    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        $error_message = 'Jawali Validation Error: ' . curl_error($curl);
        error_log($error_message); // تسجيل الخطأ في سجلات الخادم
        wp_send_json_error(array(
            'message' => 'Failed to validate payment code. Please try again.',
            'debug' => $error_message // إرسال تفاصيل الخطأ للتصحيح (للاستخدام في البيئة التطويرية فقط)
        ));
    } else {
        $response_data = json_decode($response, true);
        if (isset($response_data['responseStatus']['systemStatus']) && $response_data['responseStatus']['systemStatus'] == "0") {
            wp_send_json_success($response_data);
        } else {
            $error_message = 'Jawali API Error: ' . ($response_data['responseStatus']['systemStatusDesc'] ?? 'Unknown error');
            error_log($error_message); // تسجيل الخطأ في سجلات الخادم
            wp_send_json_error(array(
                'message' => 'Payment code validation failed. Please check the code and try again.',
                'debug' => $error_message // إرسال تفاصيل الخطأ للتصحيح (للاستخدام في البيئة التطويرية فقط)
            ));
        }
    }

    curl_close($curl);

    wp_die();
}

function jawali_process_jawali_payment() {
    $payment_code = sanitize_text_field( $_POST['payment_code'] );
    $receiver_mobile = sanitize_text_field( $_POST['receiver_mobile'] );

    $agent_wallet = get_option('jawali_agent_wallet', '5186');
    $agent_wallet_pwd = get_option('jawali_agent_wallet_pwd', '81771188');
    $org_id = get_option('jawali_org_id', '22000030756');
    $user_id = get_option('jawali_user_id', 'gold.time.b');
    $login_token = get_option( 'jawali_login_token' );
    $access_token = get_transient( 'jawali_access_token' );

    $amount = sanitize_text_field($_POST['amount']);
    $currency_code = sanitize_text_field($_POST['currency']);

    if (empty($payment_code) || empty($receiver_mobile) || empty($amount) || empty($currency_code)) {
        wp_send_json_error('Missing required fields.');
    }

    $body = json_encode(array(
        "header" => array(
            "serviceDetail" => array(
                "corrID" => uniqid(),
                "domainName" => "MerchantDomain",
                "serviceName" => "PAYAG.ECOMMCASHOUT"
            ),
            "signonDetail" => array(
                "clientID" => "WeCash",
                "orgID" => $org_id,
                "userID" => $user_id,
                "externalUser" => "External User"
            ),
            "messageContext" => array(
                "clientDate" => time(),
                "bodyType" => "Clear"
            )
        ),
        "body" => array(
            "agentWallet" => $agent_wallet,
            "password" => $agent_wallet_pwd,
            "accessToken" => $access_token,
            "voucher" => $payment_code,
            "receiverMobile" => $receiver_mobile,
            "purpose" => "test bill payment",
            "refId" => uniqid(),
            "amount" => $amount,
            "currency" => "YER" // تم تحديث العملة إلى YER
        )
    ));

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://app.wecash.com.ye:8493/paygate/v1/ws/callWS',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $login_token,
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        $error_message = 'Jawali Payment Error: ' . curl_error($curl);
        error_log($error_message); // تسجيل الخطأ في سجلات الخادم
        wp_send_json_error(array(
            'message' => 'Payment processing failed. Please try again.',
            'debug' => $error_message // إرسال تفاصيل الخطأ للتصحيح (للاستخدام في البيئة التطويرية فقط)
        ));
    } else {
        $response_data = json_decode($response, true);
        if (isset($response_data['responseStatus']['systemStatus']) && $response_data['responseStatus']['systemStatus'] == "0") {
            wp_send_json_success( $response_data );
        } else {
            $error_message = 'Jawali API Error: ' . ($response_data['responseStatus']['systemStatusDesc'] ?? 'Unknown error');
            error_log($error_message); // تسجيل الخطأ في سجلات الخادم
            wp_send_json_error(array(
                'message' => 'Payment processing failed. Please try again.',
                'debug' => $error_message // إرسال تفاصيل الخطأ للتصحيح (للاستخدام في البيئة التطويرية فقط)
            ));
        }
    }

    curl_close($curl);

    wp_die();
}

function get_inline_script($other_url, $login_token, $access_token, $client_id, $client_secret, $amount, $currency_code) {
    ob_start();
    ?>
    jQuery(document).ready(function($) {
        function generateRandomRefId() {
            return Math.floor(Math.random() * 9000000000) + 1000000000;
        }

        function updateRequestDetails() {
            var payment_code = $('#jawali_payment_code').val();
            var receiver_mobile = $('#billing_phone').val();
            var access_token = '<?php echo $access_token ? $access_token : "None"; ?>';
            var agent_wallet = '<?php echo get_option('jawali_agent_wallet', '5186'); ?>';
            var agent_wallet_pwd = '<?php echo get_option('jawali_agent_wallet_pwd', '81771188'); ?>';
            var org_id = '<?php echo get_option('jawali_org_id', '22000030756'); ?>';
            var user_id = '<?php echo get_option('jawali_user_id', 'gold.time.b'); ?>';
            var amount = '<?php echo $amount; ?>';
            var currency_code = '<?php echo $currency_code; ?>';

            var requestDetails = {
                "header": {
                    "serviceDetail": {
                        "corrID": "<?php echo uniqid(); ?>",
                        "domainName": "MerchantDomain",
                        "serviceName": "PAYAG.ECOMMERCEINQUIRY"
                    },
                    "signonDetail": {
                        "clientID": "WeCash",
                        "orgID": org_id,
                        "userID": user_id,
                        "externalUser": "External User"
                    },
                    "messageContext": {
                        "clientDate": "<?php echo time(); ?>",
                        "bodyType": "Clear"
                    }
                },
                "body": {
                    "agentWallet": agent_wallet,
                    "password": agent_wallet_pwd,
                    "accessToken": access_token,
                    "voucher": payment_code,
                    "receiverMobile": receiver_mobile,
                    "txncurrency": "YER", // تم إضافة هذا الحقل
                    "purpose": "test bill payment",
                    "refId": generateRandomRefId()
                }
            };

            $('#jawali_request_details').val(JSON.stringify(requestDetails, null, 2));
        }

        // Update the request details on page load and when the payment code changes
        updateRequestDetails();
        $('#jawali_payment_code').on('input', updateRequestDetails);

        $('#jawali-validate-button').on('click', function() {
            var payment_code = $('#jawali_payment_code').val();
            var receiver_mobile = $('#billing_phone').val();
            var amount = '<?php echo $amount; ?>';
            var currency_code = '<?php echo $currency_code; ?>';

            if (!payment_code || !receiver_mobile || !amount || !currency_code) {
                alert("Please fill in all required fields.");
                return;
            }

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'jawali_validate_payment_code', // تم تغيير الـ action هنا
                    payment_code: payment_code,
                    receiver_mobile: receiver_mobile
                },
                success: function(response) {
                    if (response.success) {
                        $('#jawali-validation-result').html('<pre style="color: green;">' + JSON.stringify(response.data, null, 2) + '</pre>');
                        $('#jawali_hidden_payment_code').val(payment_code);
                        $('#jawali-pay-button').show(); // عرض زر الدفع بعد التحقق من الكود
                        alert("Payment code validated successfully.");
                    } else {
                        $('#jawali-validation-result').html('<pre style="color: red;">' + response.data.message + '</pre>');
                        console.error('Validation Error:', response.data.debug); // تسجيل الخطأ في console
                    }
                },
                error: function(response) {
                    $('#jawali-validation-result').html('<pre style="color: red;">An error occurred while validating the payment code: ' + JSON.stringify(response, null, 2) + '</pre>');
                    console.error('AJAX Error:', response); // تسجيل الخطأ في console
                }
            });
        });

        $('#jawali-pay-button').on('click', function() {
            var payment_code = $('#jawali_hidden_payment_code').val();
            var receiver_mobile = $('#billing_phone').val();
            var amount = '<?php echo $amount; ?>';
            var currency_code = '<?php echo $currency_code; ?>';

            if (!payment_code || !receiver_mobile || !amount || !currency_code) {
                alert("Please fill in all required fields.");
                return;
            }

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'jawali_process_jawali_payment',
                    payment_code: payment_code,
                    receiver_mobile: receiver_mobile,
                    amount: amount,
                    currency: currency_code
                },
                success: function(response) {
                    if (response.success) {
                        $('#jawali-validation-result').html('<pre style="color: green;">' + JSON.stringify(response.data, null, 2) + '</pre>');
                        alert("Payment processed successfully.");
                    } else {
                        $('#jawali-validation-result').html('<pre style="color: red;">' + response.data.message + '</pre>');
                        console.error('Payment Error:', response.data.debug); // تسجيل الخطأ في console
                    }
                },
                error: function(response) {
                    $('#jawali-validation-result').html('<pre style="color: red;">An error occurred while processing the payment: ' + JSON.stringify(response, null, 2) + '</pre>');
                    console.error('AJAX Error:', response); // تسجيل الخطأ في console
                }
            });
        });
    });
    <?php
    return ob_get_clean();
}
?>