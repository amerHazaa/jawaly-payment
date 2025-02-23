<?php
add_action('wp_ajax_jawali_validate_payment_code', 'jawali_validate_payment_code');
add_action('wp_ajax_nopriv_jawali_validate_payment_code', 'jawali_validate_payment_code');

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
?>