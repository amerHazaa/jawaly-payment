<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function jawali_payment_gateway_admin_menu() {
    add_menu_page(
        __( 'Jawali Payment Gateway', 'jawali-payment-gateway' ),
        __( 'Jawali Payment', 'jawali-payment-gateway' ),
        'manage_options',
        'jawali-payment-gateway',
        'jawali_payment_gateway_admin_page',
        'dashicons-admin-generic'
    );
}
add_action( 'admin_menu', 'jawali_payment_gateway_admin_menu' );

function jawali_payment_gateway_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php _e( 'Jawali Payment Gateway', 'jawali-payment-gateway' ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'jawali_payment_gateway_settings' ); ?>
            <?php do_settings_sections( 'jawali_payment_gateway_settings' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e( 'Client ID', 'jawali-payment-gateway' ); ?></th>
                    <td><input type="text" name="jawali_client_id" value="<?php echo esc_attr( get_option('jawali_client_id') ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e( 'Client Secret', 'jawali-payment-gateway' ); ?></th>
                    <td><input type="password" name="jawali_client_secret" value="<?php echo esc_attr( get_option('jawali_client_secret') ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e( 'Login URL', 'jawali-payment-gateway' ); ?></th>
                    <td><input type="text" name="jawali_login_url" value="<?php echo esc_attr( get_option('jawali_login_url', 'https://app.wecash.com.ye:8493/paygate/oauth/token') ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e( 'Other URL', 'jawali-payment-gateway' ); ?></th>
                    <td><input type="text" name="jawali_other_url" value="<?php echo esc_attr( get_option('jawali_other_url', 'https://app.wecash.com.ye:8493/paygate/v1/ws/callWS') ); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <h2><?php _e( 'Login', 'jawali-payment-gateway' ); ?></h2>
        <form id="jawali-login-form">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e( 'Username', 'jawali-payment-gateway' ); ?></th>
                    <td><input type="text" id="jawali_username" value="gold.time.b" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e( 'Password', 'jawali-payment-gateway' ); ?></th>
                    <td><input type="password" id="jawali_password" value="Gold@t1me" /></td>
                </tr>
            </table>
            <button type="button" id="jawali-login-button"><?php _e( 'Login', 'jawali-payment-gateway' ); ?></button>
        </form>
        <h2><?php _e( 'Wallet Authentication', 'jawali-payment-gateway' ); ?></h2>
        <form id="jawali-wallet-auth-form">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e( 'Wallet ID', 'jawali-payment-gateway' ); ?></th>
                    <td><input type="text" id="jawali_wallet_id" value="5186" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e( 'Wallet Password', 'jawali-payment-gateway' ); ?></th>
                    <td><input type="password" id="jawali_wallet_password" value="81771188" /></td>
                </tr>
            </table>
            <button type="button" id="jawali-wallet-auth-button"><?php _e( 'Authenticate', 'jawali-payment-gateway' ); ?></button>
        </form>
    </div>
    <script>
        jQuery(document).ready(function($) {
            function loginAndAuthenticate() {
                var username = $('#jawali_username').val();
                var password = $('#jawali_password').val();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'jawali_login',
                        username: username,
                        password: password
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e( 'Login successful', 'jawali-payment-gateway' ); ?>\nToken: ' + response.data.login_token);
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'jawali_wallet_auth',
                                    wallet_id: $('#jawali_wallet_id').val(),
                                    wallet_password: $('#jawali_wallet_password').val()
                                },
                                success: function(response) {
                                    if (response.success) {
                                        alert('<?php _e( 'Wallet authentication successful', 'jawali-payment-gateway' ); ?>\nToken: ' + response.data.access_token);
                                    } else {
                                        alert(response.data);
                                    }
                                },
                                error: function(response) {
                                    alert('<?php _e( 'Wallet authentication failed', 'jawali-payment-gateway' ); ?>');
                                }
                            });
                        } else {
                            alert(response.data);
                        }
                    },
                    error: function(response) {
                        alert('<?php _e( 'Login failed', 'jawali-payment-gateway' ); ?>');
                    }
                });
            }

            $('#jawali-login-button').on('click', loginAndAuthenticate);
            $('#jawali-wallet-auth-button').on('click', loginAndAuthenticate);
        });
    </script>
    <?php
}

function jawali_payment_gateway_register_settings() {
    register_setting( 'jawali_payment_gateway_settings', 'jawali_client_id' );
    register_setting( 'jawali_payment_gateway_settings', 'jawali_client_secret' );
    register_setting( 'jawali_payment_gateway_settings', 'jawali_login_url' );
    register_setting( 'jawali_payment_gateway_settings', 'jawali_other_url' );
}
add_action( 'admin_init', 'jawali_payment_gateway_register_settings' );

function jawali_login() {
    $username = sanitize_text_field( $_POST['username'] );
    $password = sanitize_text_field( $_POST['password'] );

    $url = get_option('jawali_login_url', 'https://app.wecash.com.ye:8493/paygate/oauth/token');
    $response = wp_remote_post($url, array(
        'method'    => 'POST',
        'headers'   => array(
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
        'body'      => http_build_query(array(
            'grant_type'    => 'password',
            'client_id'     => 'restapp',
            'client_secret' => 'restapp',
            'username'      => $username,
            'password'      => $password,
            'scope'         => 'read',
        )),
        'timeout'   => 15, // زيادة المهلة إلى 15 ثانية
    ));

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( $response->get_error_message() );
    } else {
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( isset( $data['access_token'] ) ) {
            update_option( 'jawali_login_token', $data['access_token'] );
            wp_send_json_success(array('login_token' => $data['access_token']));
        } else {
            wp_send_json_error( $data['error_description'] ?? 'Unknown error' );
        }
    }
}
add_action( 'wp_ajax_jawali_login', 'jawali_login' );

function jawali_wallet_auth() {
    $wallet_id = sanitize_text_field( $_POST['wallet_id'] );
    $wallet_password = sanitize_text_field( $_POST['wallet_password'] );

    $login_token = get_option( 'jawali_login_token' );
    if ( ! $login_token ) {
        wp_send_json_error( 'Login token not found. Please login first.' );
    }

    $url = get_option('jawali_other_url', 'https://app.wecash.com.ye:8493/paygate/v1/ws/callWS');
    $response = wp_remote_post($url, array(
        'method'    => 'POST',
        'headers'   => array(
            'Authorization' => 'Bearer ' . $login_token,
            'Content-Type' => 'application/json',
        ),
        'body'      => json_encode(array(
            'header' => array(
                'serviceDetail' => array(
                    'corrID'      => uniqid(),
                    'domainName'  => 'WalletDomain',
                    'serviceName' => 'PAYWA.WALLETAUTHENTICATION',
                ),
                'signonDetail' => array(
                    'clientID' => 'WeCash',
                    'orgID'    => '22000030756',
                    'userID'   => 'gold.time.b',
                    'externalUser' => 'External User',
                ),
                'messageContext' => array(
                    'clientDate' => time(),
                    'bodyType'   => 'Clear',
                ),
            ),
            'body' => array(
                'identifier' => $wallet_id,
                'password'   => $wallet_password,
            ),
        )),
        'timeout'   => 15, // زيادة المهلة إلى 15 ثانية
    ));

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( $response->get_error_message() );
    } else {
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( isset( $data['responseBody']['access_token'] ) ) {
            set_transient( 'jawali_access_token', $data['responseBody']['access_token'], HOUR_IN_SECONDS );
            wp_send_json_success(array('access_token' => $data['responseBody']['access_token']));
        } else {
            wp_send_json_error( $data['responseBody']['message'] ?? 'Unknown error' );
        }
    }
}
add_action( 'wp_ajax_jawali_wallet_auth', 'jawali_wallet_auth' );