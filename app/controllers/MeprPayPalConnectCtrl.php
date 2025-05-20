<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprPayPalConnectCtrl extends MeprBaseCtrl
{
    const PAYPAL_BN_CODE     = 'Memberpress_SP_PPCP';
    const PAYPAL_URL_LIVE    = 'https://api-m.paypal.com';
    const PAYPAL_URL_SANDBOX = 'https://api-m.sandbox.paypal.com';

    /**
     * Load the hooks
     *
     * @return void
     */
    public function load_hooks()
    {
        if (! defined('MEPR_PAYPAL_SERVICE_DOMAIN')) {
            define('MEPR_PAYPAL_SERVICE_DOMAIN', 'paypal.memberpress.com');
        }

        if (! defined('MEPR_PAYPAL_SERVICE_URL')) {
            define('MEPR_PAYPAL_SERVICE_URL', 'https://' . MEPR_PAYPAL_SERVICE_DOMAIN);
        }

        add_filter('http_request_timeout', function ($seconds) {
            return $seconds + 15;
        });

        add_action('admin_init', [$this, 'admin_init']);
        add_action('mepr-transaction-expired', [$this, 'check_for_renewal_transactions'], 10, 2);
        add_action('mepr-saved-options', [$this, 'mepr_saved_options']);
        $this->add_ajax_endpoints();
    }

    /**
     * Checks for renewal transactions for a given subscription.
     *
     * @param MeprTransaction $txn        The transaction object.
     * @param boolean         $sub_status The subscription status.
     *
     * @return void
     */
    public function check_for_renewal_transactions($txn, $sub_status = false)
    {
        $sub = $txn->subscription();

        if (empty($sub->id)) {
            return;
        }

        $mepr_options = MeprOptions::fetch();
        $subscr_id    = $sub->subscr_id;
        /**
         * Gateway object.
         *
         * @var MeprPayPalCommerceGateway $gateway
         */
        $gateway = $mepr_options->payment_method($subscr_id);

        if (!$gateway instanceof MeprPayPalCommerceGateway) {
            return;
        }

        $date     = new DateTime();
        $next_date = new DateTime();

        if ($txn->txn_type == MeprTransaction::$subscription_confirmation_str) {
            $next_date->add(new DateInterval('P1D'));
            $date->sub(new DateInterval('P1D'));
        } elseif ($txn->txn_type == MeprTransaction::$payment_str) {
            $next_date->add(new DateInterval('P1D'));
            $date->sub(new DateInterval('P5D'));
        } else {
            $date     = null;
            $next_date = null;
        }

        // Get transactions from yesterday.
        $pp_transactions = $gateway->get_paypal_subscription_transactions($subscr_id, $date, $next_date);

        foreach ($pp_transactions as $pp_transaction) {
            $_POST['txn_id']       = $pp_transaction['id'];
            $_POST['mc_gross']     = $pp_transaction['amount_with_breakdown']['gross_amount']['value'];
            $_POST['payment_date'] = $pp_transaction['time'];
            $_POST['subscr_id']    = $subscr_id;
            $gateway->record_subscription_payment();
        }
    }

    /**
     * Save the options
     *
     * @param array $settings The settings.
     *
     * @return array The settings
     */
    public function mepr_saved_options($settings)
    {
        $mepr_options = MeprOptions::fetch();

        if (! isset($settings['mepr-integrations']) || empty($settings['mepr-integrations']) || ! is_array($settings['mepr-integrations'])) {
            return $settings;
        }

        foreach ($settings['mepr-integrations'] as $key => $integration) {
            if ($integration['gateway'] == MeprPayPalCommerceGateway::class) {
                if (isset($mepr_options->legacy_integrations[ $key ])) {
                    $mepr_options->legacy_integrations[ $key ]['debug'] = isset($integration['enable_paypal_standard_debug_email']);
                    $mepr_options->store(false);
                }
            }
        }

        return $settings;
    }

    /**
     * Admin init
     *
     * @return void
     */
    public function admin_init()
    {
        if (! isset($_GET['page']) || $_GET['page'] !== 'memberpress-options') {
            return;
        }

        if (! isset($_GET['paypal']) || ! isset($_GET['method-id'])) {
            return;
        }

        if (isset($_GET['sandbox']) & ! empty($_GET['sandbox'])) {
            $sandbox = true;
        } else {
            $sandbox = false;
        }

        $method_id     = filter_input(INPUT_GET, 'method-id');
        $mepr_options = MeprOptions::fetch();
        $integrations = $mepr_options->integrations;

        if (! isset($integrations[ $method_id ])) {
            $integrations[ $method_id ]  = [
                'label'   => esc_html(__('PayPal', 'memberpress')),
                'id'      => $method_id,
                'gateway' => 'MeprPayPalCommerceGateway',
                'saved'   => true,
            ];
            $mepr_options->integrations = $integrations;
            $mepr_options->store(false);
        }
    }

    /**
     * Add a site health test callback
     *
     * @param array $tests Array of tests to be run.
     *
     * @return array
     */
    public function add_site_health_test($tests)
    {
        $tests['direct']['mepr_paypal_connect_test'] = [
            'label' => __('MemberPress - PayPal Connect Security', 'memberpress'),
            'test'  => [$this, 'run_site_health_test'],
        ];

        return $tests;
    }

    /**
     * Check and show upgrade notices for PayPal integration.
     *
     * @return void
     */
    public function check_and_show_upgrade_notices()
    {
        $mepr_options = MeprOptions::fetch();
        $integrations = $mepr_options->integrations;

        if (! is_array($integrations)) {
            return;
        }

        $has_old_paypal_integration = false;

        foreach ($integrations as $integration) {
            if (isset($integration['gateway']) && $integration['gateway'] === 'MeprPayPalStandardGateway') {
                $has_old_paypal_integration = true;
                break;
            }
        }

        if ($has_old_paypal_integration === false) {
            return;
        }

        $has_commerce_gateway = false;

        foreach ($mepr_options->integrations as $integration) {
            if (isset($integration['gateway']) && 'MeprPayPalCommerceGateway' === $integration['gateway']) {
                $has_commerce_gateway = true;
                break;
            }
        }

        if (! $has_commerce_gateway && ( ! isset($_COOKIE['mepr_paypal_connect_upgrade_dismissed']) || false == $_COOKIE['mepr_paypal_connect_upgrade_dismissed'] )) {
            ?>
      <div class="notice notice-error mepr-notice is-dismissible" id="mepr_paypal_connect_upgrade_notice">
        <p>
        <p><span class="dashicons dashicons-warning mepr-warning-notice-icon"></span><strong class="mepr-warning-notice-title"><?php _e('MemberPress Security Notice', 'memberpress'); ?></strong></p>
        <p><strong><?php _e('Your current PayPal payment connection is out of date and may become insecure. Please click the button below to upgrade your PayPal payment method now.', 'memberpress'); ?></strong></p>
        <p><a href="<?php echo admin_url('admin.php?page=memberpress-options#mepr-integration'); ?>" class="button button-primary"><?php _e('Upgrade PayPal Standard to Fix this Error Now', 'memberpress'); ?></a></p>
        </p>
            <?php wp_nonce_field('mepr_paypal_connect_upgrade_notice_dismiss', 'mepr_paypal_connect_upgrade_notice_dismiss'); ?>
      </div>
            <?php
        }
    }

    /**
     * Show notices if commerce is not connected
     *
     * @return void
     */
    public function show_notices_if_commerce_not_connected()
    {
        $mepr_options         = MeprOptions::fetch();
        $has_commerce_gateway = false;

        foreach ($mepr_options->integrations as $integration) {
            if (isset($integration['gateway']) && 'MeprPayPalCommerceGateway' === $integration['gateway']) {
                $has_commerce_gateway = true;
                break;
            }
        }

        if ($has_commerce_gateway && ! MeprPayPalCommerceGateway::has_method_with_connect_status('not-connected')) {
            ?>
      <div class="notice notice-error mepr-notice" id="mepr_stripe_connect_upgrade_notice">
        <p>
        <p><span class="dashicons dashicons-warning mepr-warning-notice-icon"></span><strong class="mepr-warning-notice-title"><?php _e('Your MemberPress PayPal Connection is incomplete', 'memberpress'); ?></strong></p>
        <p><strong><?php _e('Your PayPal connection in MemberPress must be connected in order to accept PayPal payments. Please click the button below to finish connecting your PayPal payment method now.', 'memberpress'); ?></strong></p>
        <p><a href="<?php echo admin_url('admin.php?page=memberpress-options#mepr-integration'); ?>" class="button button-primary"><?php _e('Connect PayPal Payment Method', 'memberpress'); ?></a></p>
        </p>
      </div>
            <?php
        }
    }

    /**
     * Show admin notices
     *
     * @return void
     */
    public function admin_notices()
    {
        if (! isset($_REQUEST['paypal-gateway-message']) && ! isset($_REQUEST['paypal-gateway-message-success'])) {
            return;
        }

        if (isset($_REQUEST['paypal-gateway-message-success'])) {
            $class   = 'notice notice-success';
            $message = sanitize_text_field($_REQUEST['paypal-gateway-message-success']);
        } else {
            $class   = 'notice notice-error';
            $message = sanitize_text_field($_REQUEST['paypal-gateway-message']);
        }

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    /**
     * Add ajax endpoints
     *
     * @return void
     */
    protected function add_ajax_endpoints()
    {
        add_action('wp_ajax_mepr_paypal_connect_rollback', [$this, 'rollback_paypal_to_standard']);
        add_action('wp_ajax_mepr_paypal_connect_upgrade_standard_gateway', [$this, 'upgrade_standard_gateway']);
        add_action('wp_ajax_mepr_paypal_connect_update_creds', [$this, 'process_update_creds']);
        add_action('wp_ajax_mepr_paypal_connect_update_creds_sandbox', [$this, 'process_update_creds_sandbox']);
        add_action('wp_ajax_mepr_paypal_connect_disconnect', [$this, 'process_remove_creds']);
        add_action('wp_ajax_mepr_paypal_commerce_get_smart_button_mode', [$this, 'get_smart_button_mode']);
        add_action('wp_ajax_nopriv_mepr_paypal_commerce_get_smart_button_mode', [$this, 'get_smart_button_mode']);
        add_action('wp_ajax_mepr_paypal_commerce_create_smart_button', [$this, 'generate_smart_button_object']);
        add_action('wp_ajax_nopriv_mepr_paypal_commerce_create_smart_button', [$this, 'generate_smart_button_object']);
        add_action('admin_init', [$this, 'onboarding_success']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_filter('mepr_signup_form_payment_description', [$this, 'maybe_render_payment_form'], 10, 3);
    }

    /**
     * Renders the payment form if SPC is enabled and supported by the payment method.
     * Called from: mepr_signup_form_payment_description filter
     * Returns: description includding form for SPC if enabled
     *
     * @param string  $description    The payment description.
     * @param object  $payment_method The payment method object.
     * @param boolean $first          Whether this is the first payment method.
     *
     * @return string The modified payment description.
     */
    public function maybe_render_payment_form($description, $payment_method, $first)
    {
        $mepr_options = MeprOptions::fetch();

        if (! $payment_method instanceof MeprPayPalCommerceGateway) {
            return $description;
        }

        if (! ( $mepr_options->enable_spc && $payment_method->has_spc_form )) {
            // Include smart buttons in spc.
            wp_register_script('mepr-checkout-js', MEPR_JS_URL . '/checkout.js', ['jquery', 'jquery.payment'], MEPR_VERSION);
            wp_enqueue_script('mepr-checkout-js');
            $payment_method->enqueue_payment_form_scripts();
            $description = $payment_method->spc_payment_fields();
        }

        return $description;
    }

    /**
     * Onboarding success
     *
     * @return void
     */
    public function onboarding_success()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['mepr-paypal-commerce-confirm-email']) && $_GET['mepr-paypal-commerce-confirm-email'] == '1') {
            $sandbox         = isset($_GET['sandbox']) && $_GET['sandbox'] == '1';
            $mepr_options    = MeprOptions::fetch();
            $integrations    = $mepr_options->integrations;
            $method_id       = filter_var($_GET['method-id']);
            $site_uuid       = get_option('mepr_authenticator_site_uuid');
            $buffer_settings = get_option('mepr_buff_integrations', []);

            if (isset($buffer_settings[ $method_id ])) {
                foreach (['test_merchant_id', 'live_merchant_id', 'test_email_confirmed', 'live_email_confirmed'] as $key) {
                    if (isset($buffer_settings[ $method_id ][ $key ])) {
                        $mepr_options->integrations[ $method_id ][ $key ] = $buffer_settings[ $method_id ][ $key ];
                    }
                }
            }

            if ($sandbox) {
                $endpoint = MEPR_PAYPAL_SERVICE_URL . "/sandbox/credentials/{$method_id}";
                $payload  = [
                    'site_uuid'   => $site_uuid,
                    'merchant_id' => $integrations[ $method_id ]['test_merchant_id'],
                ];
            } else {
                $endpoint = MEPR_PAYPAL_SERVICE_URL . "/credentials/{$method_id}";
                $payload  = [
                    'site_uuid'   => $site_uuid,
                    'merchant_id' => $integrations[ $method_id ]['live_merchant_id'],
                ];
            }

            $jwt = MeprAuthenticatorCtrl::generate_jwt($payload);

            $options = [
                'headers' => MeprUtils::jwt_header($jwt, MEPR_PAYPAL_SERVICE_DOMAIN),
            ];

            $response = wp_remote_get($endpoint, $options);
            $creds    = wp_remote_retrieve_body($response);
            self::debug_log($endpoint);
            self::debug_log($options);
            $creds = json_decode($creds, true);
            self::debug_log($creds);

            if (isset($creds['primary_email_confirmed']) && ! empty($creds['primary_email_confirmed'])) {
                if ($sandbox) {
                    $integrations[ $method_id ]['test_email_confirmed'] = true;
                } else {
                    $integrations[ $method_id ]['live_email_confirmed'] = true;
                }

                $mepr_options->integrations = $integrations;
                $mepr_options->store(false);
            }
        }
        if (isset($_GET['paypal-connect']) && $_GET['paypal-connect'] == '1') {
            $mepr_options = MeprOptions::fetch();
            $method_id    = filter_var($_GET['method_id']);
            $integrations = $mepr_options->integrations;
            self::debug_log($_GET);
            if (isset($_GET['merchantIdInPayPal'])) {
                if (isset($_GET['sandbox']) && $_GET['sandbox'] == '1') {
                    $integrations[ $method_id ]['test_merchant_id'] = esc_sql($_GET['merchantIdInPayPal']);
                } else {
                    $integrations[ $method_id ]['live_merchant_id'] = esc_sql($_GET['merchantIdInPayPal']);
                }
            }
            if (isset($_GET['isEmailConfirmed'])) {
                $is_confirmed = ! ( $_GET['isEmailConfirmed'] == 'false' );

                if (isset($_GET['sandbox']) && $_GET['sandbox'] == '1') {
                    $integrations[ $method_id ]['test_email_confirmed'] = $is_confirmed;
                } else {
                    $integrations[ $method_id ]['live_email_confirmed'] = $is_confirmed;
                }
            }
            self::debug_log($integrations);
            $mepr_options->integrations = $integrations;
            $buffer                     = get_option('mepr_buff_integrations');

            if (empty($buffer)) {
                $buffer = [];
            }

            $buffer[ $method_id ] = $integrations[ $method_id ];
            update_option('mepr_buff_integrations', $buffer);

            $mepr_options->store(false);

            $onboarding = isset($_GET['onboarding']) ? sanitize_text_field(wp_unslash($_GET['onboarding'])) : '';

            if ($onboarding == 'true') {
                update_option('mepr_onboarding_payment_gateway', $method_id);

                $redirect_url = add_query_arg([
                    'page' => 'memberpress-onboarding',
                    'step' => '6',
                ], admin_url('admin.php'));
            } else {
                $redirect_url = admin_url('admin.php?page=memberpress-options#mepr-integration');
            }

            MeprUtils::wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Create a webhook
     *
     * @param string  $webhook_url   The webhook URL.
     * @param string  $client_id     The client ID.
     * @param string  $client_secret The client secret.
     * @param boolean $sandbox       Whether to use the sandbox environment.
     *
     * @return string The webhook ID
     */
    public function create_webhook($webhook_url, $client_id, $client_secret, $sandbox = false)
    {
        self::debug_log('Attempt to create webhook');

        $webhook_url = str_ireplace('http://', 'https://', $webhook_url);
        $url         = self::get_base_paypal_endpoint($sandbox);
        $payload     = [
            'url'         => $webhook_url,
            'event_types' => [
                [
                    'name' => 'INVOICING.INVOICE.PAID',
                ],
                [
                    'name' => 'CHECKOUT.ORDER.COMPLETED',
                ],
                [
                    'name' => 'CHECKOUT.ORDER.PROCESSED',
                ],
                [
                    'name' => 'PAYMENT.SALE.COMPLETED',
                ],
                [
                    'name' => 'PAYMENT.CAPTURE.REFUNDED',
                ],
                [
                    'name' => 'PAYMENT.CAPTURE.DENIED',
                ],
                [
                    'name' => 'PAYMENT.SALE.REFUNDED',
                ],
                [
                    'name' => 'BILLING.SUBSCRIPTION.ACTIVATED',
                ],
                [
                    'name' => 'BILLING.SUBSCRIPTION.SUSPENDED',
                ],
                [
                    'name' => 'BILLING.SUBSCRIPTION.EXPIRED',
                ],
                [
                    'name' => 'BILLING.SUBSCRIPTION.CANCELLED',
                ],
            ],
        ];
        $json_string = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $response = wp_remote_post($url . '/v1/notifications/webhooks', [
            'headers' => [
                'Authorization'                 => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'PayPal-Partner-Attribution-Id' => self::PAYPAL_BN_CODE,
                'Content-Type'                  => 'application/json',
            ],
            'body'    => $json_string,
            'method'  => 'POST',
        ]);

        $raw = wp_remote_retrieve_body($response);
        self::debug_log($json_string);
        self::debug_log($raw);
        $paypal_webhook = json_decode($raw, true);

        if (isset($paypal_webhook['id'])) {
            return $paypal_webhook['id'];
        }
    }

    /**
     * Deletes a PayPal webhook.
     *
     * @todo  This is unused for now, we can't delete webhook because new webhook won't receive
     * notifications for payments created from prior webhook.
     * @param string  $webhook_id The webhook ID.
     * @param string  $token      The authorization token.
     * @param boolean $sandbox    Whether to use the sandbox environment.
     *
     * @return boolean True if the webhook was deleted successfully, false otherwise.
     */
    public static function delete_webhook($webhook_id, $token, $sandbox = false)
    {
        $url     = self::get_base_paypal_endpoint($sandbox) . '/v1/notifications/webhooks/' . $webhook_id;
        $options = [
            'headers' => [
                'Authorization'                 => 'Basic ' . $token,
                'PayPal-Partner-Attribution-Id' => self::PAYPAL_BN_CODE,
                'Content-Type'                  => 'application/json',
            ],
            'method'  => 'DELETE',
        ];

        $response      = wp_remote_request($url, $options);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code >= 200 && $response_code < 300) {
            return true;
        }

        self::debug_log($response);

        return false;
    }

    /**
     * Logs debug information to a file if debugging is enabled.
     *
     * @param mixed $data The data to log.
     *
     * @return void
     */
    public static function debug_log($data)
    {
        if (! defined('WP_MEPR_DEBUG')) {
            return;
        }

        file_put_contents(WP_CONTENT_DIR . '/paypal-connect.log', print_r($data, true) . PHP_EOL, FILE_APPEND);
    }

    /**
     * Upgrade the standard gateway
     *
     * @return void
     */
    public function upgrade_standard_gateway()
    {
        $mepr_options              = MeprOptions::fetch();
        $id                        = filter_input(INPUT_GET, 'method-id', FILTER_SANITIZE_STRING);
        $standard_gateway_settings = $mepr_options->integrations[ $id ];

        if (! isset($mepr_options->legacy_integrations)) {
            $mepr_options->legacy_integrations = [];
        }

        $mepr_options->legacy_integrations[ $id ]     = $standard_gateway_settings;
        $mepr_options->integrations[ $id ]['gateway'] = MeprPayPalCommerceGateway::class;
        $mepr_options->store(false);
        $url = admin_url('admin.php?page=memberpress-options#mepr-integration');
        MeprUtils::wp_redirect($url);
    }

    /**
     * Process the remove credentials
     *
     * @return void
     */
    public function process_remove_creds()
    {
        $mepr_options = MeprOptions::fetch();
        $site_uuid    = get_option('mepr_authenticator_site_uuid');
        $method_id    = sanitize_text_field($_REQUEST['method-id']);
        $payload      = [
            'site_uuid' => $site_uuid,
        ];

        $sandbox = filter_var(isset($_GET['sandbox']) ? $_GET['sandbox'] : 0);
        $retry   = filter_var(isset($_GET['retry']) ? $_GET['retry'] : 0);

        if ($retry) {
            $integrations                                = $mepr_options->integrations;
            $integrations[ $method_id ]['live_auth_code'] = '';
            $integrations[ $method_id ]['test_auth_code'] = '';
            $mepr_options->integrations                  = $integrations;
            $mepr_options->store(false);
            $message = esc_html(__('You have disconnected your PayPal. You should login to your PayPal account and go to Developer settings to delete the app created by this gateway unless you have active recurring subscriptions that were created with this gateway', 'memberpress'));
            $url     = admin_url('admin.php?page=memberpress-options&paypal-gateway-message-success=' . $message . '#mepr-integration');
            MeprUtils::wp_redirect($url);
        }

        self::debug_log($sandbox);

        if (! empty($sandbox)) {
            $endpoint = MEPR_PAYPAL_SERVICE_URL . "/sandbox/credentials/{$method_id}";
        } else {
            $endpoint = MEPR_PAYPAL_SERVICE_URL . "/credentials/{$method_id}";
        }

        $jwt = MeprAuthenticatorCtrl::generate_jwt($payload);

        // Make sure the request came from the Connect service.
        $options = [
            'body'    => [
                'method-id' => $method_id,
            ],
            'method'  => 'DELETE',
            'headers' => MeprUtils::jwt_header($jwt, MEPR_PAYPAL_SERVICE_DOMAIN),
        ];

        $response      = wp_remote_request($endpoint, $options);
        $response_code = wp_remote_retrieve_response_code($response);
        $body          = wp_remote_retrieve_body($response);
        $integrations  = $mepr_options->integrations;

        if (empty($sandbox)) {
            $integrations[ $method_id ]['live_webhook_id'] = '';
        } else {
            $integrations[ $method_id ]['test_webhook_id'] = '';
        }

        self::debug_log($body);

        if (empty($sandbox)) {
            $integrations[$method_id]['live_client_id']     = '';
            $integrations[$method_id]['live_client_secret'] = '';
            $integrations[$method_id]['live_merchant_id']   = '';
        } else {
            $integrations[$method_id]['test_client_id']     = '';
            $integrations[$method_id]['test_client_secret'] = '';
            $integrations[$method_id]['test_merchant_id']   = '';
        }

        $mepr_options->integrations = $integrations;
        $mepr_options->store(false);
        $message = esc_html(__('You have disconnected your PayPal. You should login to your PayPal account and go to Developer settings to delete the app created by this gateway unless you have active recurring subscriptions that were created with this gateway', 'memberpress'));
        $url     = admin_url('admin.php?page=memberpress-options&paypal-gateway-message-success=' . $message . '#mepr-integration');

        MeprUtils::wp_redirect($url);
    }

    /**
     * Process the update credentials
     *
     * @return void
     */
    public function process_update_creds_sandbox()
    {
        $this->process_update_creds(true);
    }

    /**
     * Process the update credentials
     *
     * @param boolean $sandbox Whether to use the sandbox environment.
     *
     * @return void
     */
    public function process_update_creds($sandbox = false)
    {
        // Security check.
        if (! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'paypal-update-creds')) {
            wp_die(__('Sorry, updating your credentials failed. (security)', 'memberpress'));
        }

        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permission to perform this operation', 'memberpress'));
        }

        $posted = json_decode(@file_get_contents('php://input'), true);
        self::debug_log($posted);
        $auth_code = $posted['authCode'];
        $shared_id = $posted['sharedId'];
        $method_id = $posted['payment_method_id'];

        $this->handle_update_creds($sandbox, $auth_code, $shared_id, $method_id);
    }

    /**
     * Handle the update credentials
     *
     * @param boolean $sandbox   Whether to use the sandbox environment.
     * @param string  $auth_code The auth code.
     * @param string  $shared_id The shared ID.
     * @param string  $method_id The method ID.
     *
     * @return void
     */
    public function handle_update_creds($sandbox, $auth_code, $shared_id, $method_id)
    {
        $pm           = new MeprPayPalCommerceGateway();
        $mepr_options = MeprOptions::fetch();
        $integrations = $mepr_options->integrations;

        if (! isset($integrations[ $method_id ])) {
            $integrations[ $method_id ] = [
                'label'   => esc_html(__('PayPal', 'memberpress')),
                'id'      => $method_id,
                'gateway' => 'MeprPayPalCommerceGateway',
                'saved'   => true,
            ];

            $mepr_options->integrations = $integrations;
            $mepr_options->store(false);
            $pm->load(['id' => $method_id]);
            $pm->id = $method_id;
        }

        $pm->load($integrations[ $method_id ]);

        if ($sandbox) {
            if (isset($integrations[ $method_id ]['test_auth_code']) && ! empty($integrations[ $method_id ]['test_auth_code'])) {
                die('An auth code is being processed');
            }
            $integrations[ $method_id ]['test_auth_code'] = $auth_code;
            $mepr_options->integrations                  = $integrations;
            $mepr_options->store(false);
        } else {
            if (isset($integrations[ $method_id ]['live_auth_code']) && ! empty($integrations[ $method_id ]['live_auth_code'])) {
                die('An auth code is being processed');
            }
            $integrations[ $method_id ]['live_auth_code'] = $auth_code;
            $mepr_options->integrations                  = $integrations;
            $mepr_options->store(false);
        }

        $site_uuid = get_option('mepr_authenticator_site_uuid');

        $payload = [
            'site_uuid' => $site_uuid,
        ];

        $jwt     = MeprAuthenticatorCtrl::generate_jwt($payload);
        $options = [
            'body'    => [
                'auth_code' => $auth_code,
                'share_id'  => $shared_id,
            ],
            'headers' => MeprUtils::jwt_header($jwt, MEPR_PAYPAL_SERVICE_DOMAIN),
        ];

        if ($sandbox) {
            $endpoint = MEPR_PAYPAL_SERVICE_URL . "/sandbox/credentials/{$method_id}";
        } else {
            $endpoint = MEPR_PAYPAL_SERVICE_URL . "/credentials/{$method_id}";
        }

        $response = wp_remote_post($endpoint, $options);
        $creds    = wp_remote_retrieve_body($response);
        $creds    = json_decode($creds, true);

        if (isset($creds['client_id']) && isset($creds['client_secret'])) {
            $webhook_id = self::create_webhook($pm->notify_url('webhook'), $creds['client_id'], $creds['client_secret'], $sandbox);

            if ($sandbox) {
                $integrations[ $method_id ]['test_client_id']     = $creds['client_id'];
                $integrations[ $method_id ]['test_client_secret'] = $creds['client_secret'];
                $integrations[ $method_id ]['test_auth_code']     = '';
                $integrations[ $method_id ]['test_webhook_id']    = $webhook_id;
            } else {
                $integrations[ $method_id ]['live_client_id']     = $creds['client_id'];
                $integrations[ $method_id ]['live_client_secret'] = $creds['client_secret'];
                $integrations[ $method_id ]['live_auth_code']     = '';
                $integrations[ $method_id ]['live_webhook_id']    = $webhook_id;
            }

            $mepr_options->integrations = $integrations;
            $mepr_options->store(false);
        }
    }

    /**
     * Get the smart button mode
     *
     * @return void
     */
    public function get_smart_button_mode()
    {
        $mepr_options   = MeprOptions::fetch();
        $transaction_id = isset($_POST['mepr_transaction_id']) && is_numeric($_POST['mepr_transaction_id']) ? (int) $_POST['mepr_transaction_id'] : 0;

        if ($transaction_id > 0) {
            $txn = new MeprTransaction($transaction_id);

            if (!$txn->id) {
                wp_send_json_error(__('Transaction not found', 'memberpress'));
            }

            $pm = $mepr_options->payment_method($txn->gateway);

            if (!($pm instanceof MeprPayPalCommerceGateway)) {
                wp_send_json_error(__('Invalid payment gateway', 'memberpress'));
            }

            $prd = $txn->product();
        } else {
            $product_id = isset($_POST['mepr_product_id']) ? (int) $_POST['mepr_product_id'] : 0;
            $prd        = new MeprProduct($product_id);
        }

        if (empty($prd->ID)) {
            wp_send_json_error(__('Product not found', 'memberpress'));
        }

        $has_subscription = !$prd->is_one_time_payment();

        $order_bump_product_ids = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_map('intval', $_POST['mepr_order_bumps']) : [];

        foreach ($order_bump_product_ids as $order_bump_product_id) {
            $product = new MeprProduct($order_bump_product_id);

            if (empty($product->ID)) {
                wp_send_json_error(__('Product not found', 'memberpress'));
            }

            if (!$product->is_one_time_payment()) {
                $has_subscription = true;
            }
        }

        wp_send_json_success($has_subscription ? 'subscription' : 'order');
    }

    /**
     * Generate the smart button object
     *
     * @return void
     */
    public function generate_smart_button_object()
    {
        $mepr_options      = MeprOptions::fetch();
        $key               = 'mepr_payment_method';
        $payment_method_id = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
        $pm                = $mepr_options->payment_method($payment_method_id);

        if (!($pm instanceof MeprPayPalCommerceGateway)) {
            wp_send_json_error(['errors' => [__('Invalid payment gateway', 'memberpress')]]);
        }

        if ($pm->settings->enable_smart_button != 'on') {
            wp_send_json_error(['errors' => [__('Bad request', 'memberpress')]]);
        }

        $_POST['smart-payment-button'] = true;
        $checkout_ctrl                 = MeprCtrlFactory::fetch('checkout');
        $checkout_ctrl->process_signup_form();

        if (isset($_REQUEST['errors'])) {
            wp_send_json_error(['errors' => $_REQUEST['errors']]);
        }

        wp_send_json($_REQUEST);
    }

    /**
     * Get the base paypal endpoint
     *
     * @param boolean $sandbox Whether to use the sandbox environment.
     *
     * @return string The endpoint
     */
    public static function get_base_paypal_endpoint($sandbox = false)
    {
        if ($sandbox) {
            return self::PAYPAL_URL_SANDBOX;
        }

        return self::PAYPAL_URL_LIVE;
    }

    /**
     * Rollback PayPal integration to the standard gateway.
     *
     * @return void
     */
    public function rollback_paypal_to_standard()
    {
        $mepr_options = MeprOptions::fetch();
        $id           = filter_input(INPUT_GET, 'method-id', FILTER_SANITIZE_STRING);

        if (! isset($mepr_options->legacy_integrations[ $id ])) {
            return;
        }

        $mepr_options->integrations[ $id ]            = $mepr_options->legacy_integrations[ $id ];
        $mepr_options->integrations[ $id ]['gateway'] = MeprPayPalStandardGateway::class;
        $mepr_options->store(false);
        $message = esc_html(__('You have reverted PayPal to legacy gateway', 'memberpress'));
        $url     = admin_url('admin.php?page=memberpress-options&paypal-gateway-message=' . $message . '#mepr-integration');
        MeprUtils::wp_redirect($url);
    }

    /**
     * Run a site health check and return the result
     *
     * @return array
     */
    public function run_site_health_test()
    {
        $result = [
            'label'       => __('MemberPress is securely connected to PayPal', 'memberpress'),
            'status'      => 'good',
            'badge'       => [
                'label' => __('Security', 'memberpress'),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                __('Your MemberPress PayPal connection is complete and secure.', 'memberpress')
            ),
            'actions'     => '',
            'test'        => 'run_site_health_test',
        ];

        if (class_exists('MeprPaypalCommerceGateway') && ! MeprPayPalCommerceGateway::has_method_with_connect_status('not-connected')) {
            $result = [
                'label'       => __('MemberPress is not securely connected to PayPal', 'memberpress'),
                'status'      => 'critical',
                'badge'       => [
                    'label' => __('Security', 'memberpress'),
                    'color' => 'red',
                ],
                'description' => sprintf(
                    '<p>%s</p>',
                    __('Your current PayPal payment connection is out of date and may become insecure or stop working. Please click the button below to re-connect your PayPal payment method now.', 'memberpress')
                ),
                'actions'     => '<a href="' . admin_url('admin.php?page=memberpress-options#mepr-integration') . '" class="button button-primary">' . __('Re-connect PayPal Payments to Fix this Error Now', 'memberpress') . '</a>',
                'test'        => 'run_site_health_test',
            ];
        }

        return $result;
    }
}

