<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprPayPalVaultingCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for PayPal Vaulting payment processing.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('wp_ajax_mepr_paypal_vaulting_connect_new_gateway', [$this, 'connect_new_gateway']);
        add_action('admin_init', [$this, 'process_admin_actions']);
        add_action('admin_notices', [$this, 'connection_admin_notices']);
        add_action('wp_ajax_mepr_paypal_vaulting_complete_transaction', [$this, 'complete_transaction']);
        add_action('wp_ajax_nopriv_mepr_paypal_vaulting_complete_transaction', [$this, 'complete_transaction']);
        add_action('wp_ajax_mepr_paypal_vaulting_complete_account_update', [$this, 'complete_account_update']);
    }

    /**
     * Handle the Ajax request to connect a new (unsaved) gateway.
     */
    public function connect_new_gateway(): void
    {
        if (!MeprUtils::is_mepr_admin()) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_paypal_vaulting_connect', false, false)) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }

        $options     = MeprOptions::fetch();
        $gateway_id  = sanitize_text_field(wp_unslash($_POST['gateway_id'] ?? ''));
        $environment = sanitize_text_field(wp_unslash($_POST['environment'] ?? ''));

        if (
            empty($gateway_id) ||
            empty($_POST[$options->integrations_str][$gateway_id]) ||
            !is_array($_POST[$options->integrations_str][$gateway_id]) ||
            !in_array($environment, ['sandbox', 'production'], true)
        ) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        if (array_key_exists($gateway_id, $options->integrations)) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        $options->integrations = array_merge($options->integrations, [
            $gateway_id => [
                'id'        => $gateway_id,
                'gateway'   => 'MeprPayPalVaultingGateway',
                'sandbox'   => $environment === 'sandbox',
                'label'     => sanitize_text_field(wp_unslash($_POST[$options->integrations_str][$gateway_id]['label'] ?? '')),
                'use_label' => isset($_POST[$options->integrations_str][$gateway_id]['use_label']),
                'use_icon'  => isset($_POST[$options->integrations_str][$gateway_id]['use_icon']),
                'use_desc'  => isset($_POST[$options->integrations_str][$gateway_id]['use_desc']),
                'saved'     => true,
            ],
        ]);

        $options->store(false);

        $pm = $options->payment_method($gateway_id);

        if (!$pm instanceof MeprPayPalVaultingGateway) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        try {
            wp_send_json_success($pm->connect_auth_url($environment));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Process admin actions.
     */
    public function process_admin_actions(): void
    {
        $action = sanitize_text_field(wp_unslash($_GET['mepr_paypal_vaulting_action'] ?? ''));
        if (empty($action)) {
            return;
        }

        if (!MeprUtils::is_logged_in_and_an_admin()) {
            $this->die(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), "mepr_paypal_vaulting_$action")) {
            $this->die(__('Security check failed.', 'memberpress'));
        }

        switch ($action) {
            case 'process_connect_return':
                $this->process_connect_return();
                break;
            case 'process_refresh_credentials':
                $this->process_refresh_credentials();
                break;
            case 'process_disconnect':
                $this->process_disconnect();
                break;
            case 'process_force_disconnect':
                $this->process_force_disconnect();
                break;
            case 'process_local_disconnect':
                $this->process_local_disconnect();
                break;
            default:
                $this->die(__('Bad request.', 'memberpress'));
        }
    }

    /**
     * Processes the return from a connection attempt.
     *
     * This method validates user permissions, handles potential errors, and redirects the user to the
     * plugin settings page.
     */
    protected function process_connect_return(): void
    {
        $args = [
            'page' => 'memberpress-options',
        ];

        if (isset($_GET['error'])) {
            $args['error'] = sanitize_text_field(wp_unslash($_GET['error']));
        } else {
            $pmt = sanitize_text_field(wp_unslash($_GET['pmt'] ?? ''));

            if (!empty($pmt)) {
                $options = MeprOptions::fetch();
                $pm      = $options->payment_method($pmt);

                if ($pm instanceof MeprPayPalVaultingGateway) {
                    try {
                        $pm->fetch_credentials(
                            sanitize_text_field(wp_unslash($_GET['environment'] ?? '')) === 'sandbox' ? 'sandbox' : 'production'
                        );

                        $args['mepr-paypal-vaulting-connection-status'] = 'connected';
                    } catch (Exception $e) {
                        $args['error'] = sprintf(
                            // Translators: %s: the error message.
                            __('Error updating credentials: %s', 'memberpress'),
                            $e->getMessage()
                        );
                    }
                } else {
                    $args['error'] = __('This action is not available for this payment method.', 'memberpress');
                }
            } else {
                $args['error'] = __('Sorry, updating your credentials failed. (pmt)', 'memberpress');
            }
        }

        if (isset($args['error'])) {
            $args['mepr-paypal-vaulting-connection-status'] = 'error';
        }

        $redirect_url = add_query_arg(array_map('rawurlencode', $args), admin_url('admin.php')) . '#mepr-integration';

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Processing refreshing the credentials for a PayPal Vaulting gateway.
     */
    protected function process_refresh_credentials(): void
    {
        [$pm, $environment] = $this->validate_payment_method_request();

        try {
            $pm->refresh_credentials($environment);

            $redirect_url = add_query_arg(
                [
                    'page' => 'memberpress-options',
                    'mepr-paypal-vaulting-connection-status' => 'refreshed',
                ],
                admin_url('admin.php')
            ) . '#mepr-integration';

            wp_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            $this->die(
                sprintf(
                    // Translators: %s: the error message.
                    __('Error from the remote service: %s', 'memberpress'),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Validate and retrieve payment method and environment from request.
     *
     * @return array{0: MeprPayPalVaultingGateway, 1: string} Array containing [payment_method, environment].
     */
    protected function validate_payment_method_request(): array
    {
        $environment = sanitize_text_field(wp_unslash($_GET['environment'] ?? ''));
        if (!in_array($environment, ['sandbox', 'production'], true)) {
            $this->die(__('Bad request.', 'memberpress'));
        }

        $payment_method_id = sanitize_text_field(wp_unslash($_GET['payment_method_id'] ?? ''));
        if (empty($payment_method_id)) {
            $this->die(__('Bad request.', 'memberpress'));
        }

        $options = MeprOptions::fetch();
        $pm      = $options->payment_method($payment_method_id);

        if (!$pm instanceof MeprPayPalVaultingGateway) {
            $this->die(__('This action is not available for this payment method.', 'memberpress'));
        }

        return [$pm, $environment];
    }

    /**
     * Process disconnecting a PayPal Vaulting gateway.
     */
    protected function process_disconnect(): void
    {
        [$pm, $environment] = $this->validate_payment_method_request();

        try {
            $pm->local_disconnect($environment);
            $pm->remote_disconnect($environment, false);

            $redirect_url = add_query_arg(
                [
                    'page' => 'memberpress-options',
                    'mepr-paypal-vaulting-connection-status' => 'disconnected',
                ],
                admin_url('admin.php')
            ) . '#mepr-integration';

            wp_redirect($redirect_url);
            exit;
        } catch (MeprGatewayDomainMismatchException $e) {
            // Domain mismatch detected - show options to the user.
            $this->die_with_disconnect_options($e, $pm->id, $environment);
        } catch (Exception $e) {
            $this->die(
                __('The payment method was disconnected on your site, but communication with the PayPal service failed. You\'ll need to reconnect to continue accepting PayPal payments.', 'memberpress')
            );
        }
    }

    /**
     * Process force disconnecting a PayPal Vaulting gateway.
     *
     * This bypasses domain validation and will disconnect the remote connection
     * even if called from a staging site.
     */
    protected function process_force_disconnect(): void
    {
        [$pm, $environment] = $this->validate_payment_method_request();

        try {
            $pm->local_disconnect($environment);
            $pm->remote_disconnect($environment, true);

            $redirect_url = add_query_arg(
                [
                    'page' => 'memberpress-options',
                    'mepr-paypal-vaulting-connection-status' => 'force_disconnected',
                ],
                admin_url('admin.php')
            ) . '#mepr-integration';

            wp_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            $this->die($e->getMessage());
        }
    }

    /**
     * Process local-only disconnect of a PayPal Vaulting gateway.
     *
     * This only disconnects locally without making any API calls to the
     * payments service, leaving the remote connection active.
     */
    protected function process_local_disconnect(): void
    {
        [$pm, $environment] = $this->validate_payment_method_request();

        try {
            $pm->local_disconnect($environment);

            $redirect_url = add_query_arg(
                [
                    'page' => 'memberpress-options',
                    'mepr-paypal-vaulting-connection-status' => 'local_disconnected',
                ],
                admin_url('admin.php')
            ) . '#mepr-integration';

            wp_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            $this->die($e->getMessage());
        }
    }

    /**
     * Display domain mismatch options using wp_die with action buttons.
     *
     * This method is called when a domain mismatch is detected during disconnect.
     * It presents the user with three options: force disconnect, local disconnect, or cancel.
     *
     * @param MeprGatewayDomainMismatchException $e                 The exception with domain info.
     * @param string                             $payment_method_id The payment method ID.
     * @param string                             $environment       The environment.
     */
    protected function die_with_disconnect_options(
        MeprGatewayDomainMismatchException $e,
        string $payment_method_id,
        string $environment
    ): void {
        $expected_domain = $e->get_expected_domain();
        $actual_domain   = $e->get_actual_domain();

        // Build action URLs.
        $force_url = add_query_arg(
            [
                'mepr_paypal_vaulting_action' => 'process_force_disconnect',
                'payment_method_id'           => $payment_method_id,
                'environment'                 => $environment,
                '_wpnonce'                    => wp_create_nonce('mepr_paypal_vaulting_process_force_disconnect'),
            ],
            admin_url('admin.php')
        );

        $local_url = add_query_arg(
            [
                'mepr_paypal_vaulting_action' => 'process_local_disconnect',
                'payment_method_id'           => $payment_method_id,
                'environment'                 => $environment,
                '_wpnonce'                    => wp_create_nonce('mepr_paypal_vaulting_process_local_disconnect'),
            ],
            admin_url('admin.php')
        );

        $cancel_url = add_query_arg(
            [
                'page' => 'memberpress-options',
            ],
            admin_url('admin.php')
        ) . '#mepr-integration';

        // Build custom HTML with styled buttons and a two-column layout.
        $message = sprintf(
            '<h1>%s</h1>',
            __('Domain Mismatch Detected', 'memberpress')
        );

        // Add all CSS styles.
        $message .= '<style>
            .mepr-warning-box {
                background: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 4px;
                padding: 15px;
                margin-top: 15px;
                margin-bottom: 20px;
            }
            #error-page .mepr-warning-text {
                margin: 10px 0;
                font-size: 14px;
                line-height: 1.6;
            }
            #error-page .mepr-warning-text:first-child {
                margin-top: 0;
            }
            #error-page .mepr-warning-text:last-child {
                margin-bottom: 0;
            }
            .mepr-domain-display {
                margin-top: 20px;
                margin-bottom: 20px;
                text-align: center;
            }
            .mepr-domain-item {
                margin-bottom: 5px;
            }
            .mepr-domain-item:last-child {
                margin-bottom: 0;
            }
            .mepr-domain-label {
                font-size: 12px;
                color: #856404;
                margin-bottom: 4px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .mepr-domain-value {
                font-size: 16px;
                font-weight: 600;
                color: #2c3338;
            }
            .mepr-domain-arrow {
                font-size: 24px;
                color: #856404;
            }
            .mepr-section-header {
                margin-bottom: 15px;
                font-size: 15px;
            }
            .mepr-disconnect-option {
                display: flex;
                align-items: center;
                gap: 30px;
                margin-bottom: 25px;
                padding: 20px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .mepr-disconnect-description {
                flex: 1;
            }
            .mepr-disconnect-description h3 {
                margin: 0 0 8px 0;
                font-size: 16px;
            }
            .mepr-disconnect-description p {
                margin: 0;
                color: #666;
                font-size: 13px;
                line-height: 1.6;
            }
            .mepr-disconnect-action {
                flex-shrink: 0;
            }
            .mepr-button-danger {
                background: #dc3232 !important;
                border-color: #dc3232 !important;
                color: #fff !important;
                text-decoration: none !important;
            }
            .mepr-button-danger:hover {
                background: #c62d2d !important;
                border-color: #c62d2d !important;
                color: #fff !important;
            }
        </style>';

        $gateway_name = 'PayPal';

        $message .= sprintf(
            '<div class="mepr-warning-box">
                <p class="mepr-warning-text">
                    <strong>%s</strong>
                </p>
                <p class="mepr-warning-text">
                    %s
                </p>
                <div class="mepr-domain-display">
                    <div class="mepr-domain-item">
                        <div class="mepr-domain-label">%s</div>
                        <div class="mepr-domain-value">%s</div>
                    </div>
                    <div class="mepr-domain-item">
                        <div class="mepr-domain-arrow">↓</div>
                    </div>
                    <div class="mepr-domain-item">
                        <div class="mepr-domain-label">%s</div>
                        <div class="mepr-domain-value">%s</div>
                    </div>
                </div>
                <p class="mepr-warning-text">
                    %s
                </p>
            </div>',
            sprintf(
                // Translators: %s: Payment gateway name (e.g., PayPal, Stripe).
                __('The domain of this site does not match the domain that originally connected to %s.', 'memberpress'),
                $gateway_name
            ),
            sprintf(
                // Translators: %s: Payment gateway name (e.g., PayPal, Stripe).
                __('This typically happens when a site is cloned for staging/development, moved to a new domain, or restored from a backup. The %s connection is still pointing to the original domain.', 'memberpress'),
                $gateway_name
            ),
            __('Original Connection Domain', 'memberpress'),
            esc_html($expected_domain),
            __('Current Site Domain', 'memberpress'),
            esc_html($actual_domain),
            __('If you disconnect from this site, it will affect the original site that established the connection. Please choose carefully how you want to proceed.', 'memberpress')
        );

        // Option 1: Force Disconnect.
        $message .= '<div class="mepr-disconnect-option">';
        $message .= '<div class="mepr-disconnect-description">';
        $message .= sprintf('<h3>%s</h3>', __('Force Disconnect', 'memberpress'));
        $message .= sprintf(
            '<p>%s</p>',
            __('This will disconnect the payment method on both this site AND the live site. Webhooks will be disabled for the live site, which will break live payments until reconnected. Only use this if you intend to disconnect the live site.', 'memberpress')
        );
        $message .= '</div>';
        $message .= '<div class="mepr-disconnect-action">';
        $message .= sprintf(
            '<a href="%s" class="button button-primary button-large mepr-button-danger" onclick="return confirm(\'%s\');">%s</a>',
            esc_url($force_url),
            esc_js(__('WARNING: This will disable webhooks for the live site and break live payments. Are you absolutely sure?', 'memberpress')),
            __('Force Disconnect', 'memberpress')
        );
        $message .= '</div>';
        $message .= '</div>';

        // Option 2: Local Disconnect Only.
        $message .= '<div class="mepr-disconnect-option">';
        $message .= '<div class="mepr-disconnect-description">';
        $message .= sprintf('<h3>%s</h3>', __('Local Disconnect Only', 'memberpress'));
        $message .= sprintf(
            '<p>%s</p>',
            __('This will only disconnect the payment method on this staging/development site. The live site will remain connected and continue processing payments normally. This is the recommended option for staging environments.', 'memberpress')
        );
        $message .= '</div>';
        $message .= '<div class="mepr-disconnect-action">';
        $message .= sprintf(
            '<a href="%s" class="button button-secondary button-large">%s</a>',
            esc_url($local_url),
            __('Local Disconnect', 'memberpress')
        );
        $message .= '</div>';
        $message .= '</div>';

        // Option 3: Cancel.
        $message .= '<div class="mepr-disconnect-option">';
        $message .= '<div class="mepr-disconnect-description">';
        $message .= sprintf('<h3>%s</h3>', __('Cancel', 'memberpress'));
        $message .= sprintf(
            '<p>%s</p>',
            __('Go back to the settings page without making any changes. The payment method will remain connected on both sites.', 'memberpress')
        );
        $message .= '</div>';
        $message .= '<div class="mepr-disconnect-action">';
        $message .= sprintf(
            '<a href="%s" class="button button-large">%s</a>',
            esc_url($cancel_url),
            __('Cancel', 'memberpress')
        );
        $message .= '</div>';
        $message .= '</div>';

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML output is intentional, dynamic content already escaped.
        wp_die(
            $message,
            __('Domain Mismatch', 'memberpress'),
            [
                'back_link' => true,
                'response'  => 409,
            ]
        );
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Render a wp_die() page with the given message and end execution.
     *
     * @param string $message The message to display.
     */
    protected function die(string $message): void
    {
        wp_die(esc_html($message), '', ['back_link' => true]);
    }

    /**
     * Displays admin notices based on PayPal Vaulting connection status.
     *
     * This method checks the `mepr-paypal-vaulting-connection-status` query parameter in the URL
     * and outputs appropriate admin notices based on the status value. If the status
     * indicates an error, a corresponding error message is displayed. For success statuses,
     * the method renders a success notice with an appropriate message.
     */
    public function connection_admin_notices(): void
    {
        if (!MeprUtils::is_mepr_admin()) {
            return;
        }

        if (isset($_GET['mepr-paypal-vaulting-connection-status'])) {
            $status = sanitize_text_field(wp_unslash($_GET['mepr-paypal-vaulting-connection-status']));

            if ($status === 'error') {
                $error = sanitize_text_field(wp_unslash($_GET['error'] ?? ''));
                $error = empty($error) ? __('The payment method could not be connected to PayPal.', 'memberpress') : $error;

                printf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    esc_html($error)
                );
            } elseif ($status === 'connected') {
                $message = __('The PayPal payment method was successfully connected.', 'memberpress');
            } elseif ($status === 'disconnected') {
                $message = __('The PayPal payment method was successfully disconnected.', 'memberpress');
            } elseif ($status === 'refreshed') {
                $message = __('The PayPal payment method credentials have been updated.', 'memberpress');
            } elseif ($status === 'force_disconnected') {
                $message = __('The PayPal payment method was forcefully disconnected. Webhooks have been disabled for the connected site.', 'memberpress');
            } elseif ($status === 'local_disconnected') {
                $message = __('The PayPal payment method was disconnected locally only. The remote connection remains active.', 'memberpress');
            }

            if (isset($message)) {
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    esc_html($message)
                );
            }
        }
    }

    /**
     * Handle the Ajax request to complete a PayPal transaction (payment or setup token).
     *
     * @return void
     */
    public function complete_transaction()
    {
        $payment_method_id = sanitize_text_field(wp_unslash($_POST['mepr_paypal_payment_method_id'] ?? ''));
        $payment_method    = sanitize_text_field(wp_unslash($_POST['mepr_paypal_payment_method'] ?? ''));
        $type              = sanitize_text_field(wp_unslash($_POST['mepr_paypal_transaction_type'] ?? ''));

        // Validate payment method from the frontend.
        $allowed_payment_methods = ['paypal', 'paylater', 'credit', 'venmo', 'card', 'apple_pay', 'google_pay'];

        if (!in_array($payment_method, $allowed_payment_methods, true)) {
            wp_send_json_error(__('Invalid payment method selected.', 'memberpress'));
        }

        if (in_array($payment_method, ['paylater', 'credit'], true)) {
            $payment_method = 'paypal';
        }

        // Get the transaction ID based on type.
        $id_param       = $type === 'payment' ? 'mepr_paypal_order_id' : 'mepr_paypal_setup_token_id';
        $transaction_id = sanitize_text_field(wp_unslash($_POST[$id_param] ?? ''));

        if (
            empty($payment_method_id) ||
            empty($transaction_id) ||
            empty($payment_method) ||
            !in_array($type, ['payment', 'setup'], true)
        ) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        try {
            $mepr_options = MeprOptions::fetch();
            $gateway      = $mepr_options->payment_method($payment_method_id);

            if (!($gateway instanceof MeprPayPalVaultingGateway)) {
                wp_send_json_error(__('Invalid payment method', 'memberpress'));
            }

            $result = $gateway->complete_transaction($transaction_id, $payment_method, $type);

            wp_send_json_success($result);
        } catch (MeprGatewayException $e) {
            // Gateway exceptions have user-facing messages.
            wp_send_json_error($e->getMessage());
        } catch (Exception $e) {
            // Error already logged in api_request() - show a generic message to the user.
            $error_message = $type === 'payment'
                ? __('There was an issue completing your payment. Please try again.', 'memberpress')
                : __('There was an issue completing your subscription. Please try again.', 'memberpress');

            wp_send_json_error($error_message);
        }
    }

    /**
     * Handle the Ajax request to complete an account update setup token.
     *
     * This is called after the user approves the PayPal setup token for updating
     * their payment method. It confirms the token and updates the subscription.
     *
     * @return void
     */
    public function complete_account_update()
    {
        try {
            // Validate user is logged in.
            if (!is_user_logged_in()) {
                wp_send_json_error(__('Sorry, you must be logged in to do this.', 'memberpress'));
            }

            $options         = MeprOptions::fetch();
            $subscription_id = (int) ($_POST['mepr_subscription_id'] ?? 0);
            $token_id        = sanitize_text_field(wp_unslash($_POST['mepr_paypal_setup_token_id'] ?? ''));
            $payment_method  = sanitize_text_field(wp_unslash($_POST['mepr_paypal_payment_method'] ?? ''));

            if (empty($subscription_id) || empty($token_id) || empty($payment_method)) {
                wp_send_json_error(__('Bad request', 'memberpress'));
            }

            // Validate payment method from the frontend.
            $allowed_payment_methods = ['paypal', 'paylater', 'credit', 'venmo', 'card', 'apple_pay', 'google_pay'];

            if (!in_array($payment_method, $allowed_payment_methods, true)) {
                wp_send_json_error(__('Invalid payment method selected.', 'memberpress'));
            }

            if (in_array($payment_method, ['paylater', 'credit'], true)) {
                $payment_method = 'paypal';
            }

            $sub = new MeprSubscription($subscription_id);

            if (!($sub->id > 0)) {
                wp_send_json_error(__('Subscription not found', 'memberpress'));
            }

            $usr = $sub->user();

            if ($usr->ID !== get_current_user_id()) {
                wp_send_json_error(__('This subscription is for another user.', 'memberpress'));
            }

            $pm = $sub->payment_method();

            if (!$pm instanceof MeprPayPalVaultingGateway) {
                wp_send_json_error(__('Invalid payment gateway', 'memberpress'));
            }

            // Call the gateway's complete handler.
            $pm->complete_account_update($sub, $token_id, $payment_method);

            // Return success redirect URL.
            wp_send_json_success(
                add_query_arg(
                    array_map(
                        'rawurlencode',
                        [
                            'action'  => 'update',
                            'sub'     => $sub->id,
                            'message' => __('Your account information was successfully updated.', 'memberpress'),
                        ]
                    ),
                    $options->account_page_url()
                )
            );
        } catch (MeprGatewayException $e) {
            // Gateway exceptions have user-facing messages.
            wp_send_json_error($e->getMessage());
        } catch (Exception $e) {
            // Error already logged in api_request() - show a generic message to the user.
            wp_send_json_error(__('There was an issue updating your payment method. Please try again.', 'memberpress'));
        }
    }
}
