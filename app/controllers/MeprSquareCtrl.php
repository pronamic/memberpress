<?php

declare(strict_types=1);

class MeprSquareCtrl extends MeprBaseCtrl
{
    /**
     * Load the hooks for this controller.
     */
    public function load_hooks()
    {
        add_action('wp_ajax_mepr_square_connect_new_gateway', [$this, 'connect_new_gateway']);
        add_action('admin_init', [$this, 'process_admin_actions']);
        add_action('admin_notices', [$this, 'connect_admin_notices']);
        add_action('admin_notices', [$this, 'expired_access_token_notice']);
        add_action('admin_notices', [$this, 'currency_mismatch_notice']);
        add_action('admin_notices', [$this, 'subscription_cadence_notice']);
    }

    /**
     * Handle the Ajax request to connect a new (unsaved) gateway.
     */
    public function connect_new_gateway(): void
    {
        if (!MeprUtils::is_mepr_admin()) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_square_connect', false, false)) {
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
                'gateway'   => 'MeprSquareGateway',
                'sandbox'   => $environment == 'sandbox',
                'label'     => sanitize_text_field(wp_unslash($_POST[$options->integrations_str][$gateway_id]['label'])),
                'use_label' => isset($_POST[$options->integrations_str][$gateway_id]['use_label']),
                'use_icon'  => isset($_POST[$options->integrations_str][$gateway_id]['use_icon']),
                'use_desc'  => isset($_POST[$options->integrations_str][$gateway_id]['use_desc']),
                'saved'     => true,
            ],
        ]);

        $options->store(false);

        $pm = $options->payment_method($gateway_id);

        if (!$pm instanceof MeprSquareGateway) {
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
        $action = sanitize_text_field(wp_unslash($_GET['mepr_square_action'] ?? ''));
        if (empty($action)) {
            return;
        }

        if (!MeprUtils::is_logged_in_and_an_admin()) {
            $this->die(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), "mepr_square_$action")) {
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

                if ($pm instanceof MeprSquareGateway) {
                    try {
                        $pm->fetch_credentials(
                            sanitize_text_field(wp_unslash($_GET['environment'] ?? '')) == 'sandbox' ? 'sandbox' : 'production'
                        );

                        $args['mepr-square-connect-status'] = 'connected';
                    } catch (Exception $e) {
                        $args['error'] = sprintf(
                            // Translators: %s: the error message.
                            __('Error updating credentials: %s', 'memberpress'),
                            $e->getMessage()
                        );
                    }
                } else {
                    $args['error'] = __('Sorry, this only works with Square.', 'memberpress');
                }
            } else {
                $args['error'] = __('Sorry, updating your credentials failed. (pmt)', 'memberpress');
            }
        }

        if (isset($args['error'])) {
            $args['mepr-square-connect-status'] = 'error';
        }

        $redirect_url = add_query_arg(array_map('rawurlencode', $args), admin_url('admin.php')) . '#mepr-integration';

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Processing refreshing the credentials for a Square gateway.
     */
    protected function process_refresh_credentials(): void
    {
        $environment = sanitize_text_field(wp_unslash($_GET['environment'] ?? ''));
        if (!in_array($environment, ['sandbox', 'production'], true)) {
            $this->die(__('Sorry, the refresh failed.', 'memberpress'));
        }

        // Make sure we have a payment method ID.
        $payment_method_id = sanitize_text_field(wp_unslash($_GET['payment_method_id'] ?? ''));
        if (empty($payment_method_id)) {
            $this->die(__('Sorry, the refresh failed.', 'memberpress'));
        }

        $options = MeprOptions::fetch();
        $pm      = $options->payment_method($payment_method_id);

        if (!$pm instanceof MeprSquareGateway) {
            $this->die(__('Sorry, this only works with Square.', 'memberpress'));
        }

        try {
            $pm->refresh_credentials($environment);

            $redirect_url = add_query_arg(
                [
                    'page'                       => 'memberpress-options',
                    'mepr-square-connect-status' => 'refreshed',
                ],
                admin_url('admin.php')
            ) . '#mepr-integration';

            wp_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            $this->die(
                sprintf(
                    // Translators: %s: the error message.
                    __('Error from the Square Connect service: %s', 'memberpress'),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Process disconnecting a Square gateway.
     */
    protected function process_disconnect(): void
    {
        $environment = sanitize_text_field(wp_unslash($_GET['environment'] ?? ''));
        if (!in_array($environment, ['sandbox', 'production'], true)) {
            $this->die(__('Sorry, the disconnect failed.', 'memberpress'));
        }

        // Make sure we have a payment method ID.
        $payment_method_id = sanitize_text_field(wp_unslash($_GET['payment_method_id'] ?? ''));
        if (empty($payment_method_id)) {
            $this->die(__('Sorry, the disconnect failed.', 'memberpress'));
        }

        $options = MeprOptions::fetch();
        $pm      = $options->payment_method($payment_method_id);

        if (!$pm instanceof MeprSquareGateway) {
            $this->die(__('Sorry, this only works with Square.', 'memberpress'));
        }

        try {
            $pm->disconnect($environment, 'full');

            $redirect_url = add_query_arg(
                [
                    'page'                       => 'memberpress-options',
                    'mepr-square-connect-status' => 'disconnected',
                ],
                admin_url('admin.php')
            ) . '#mepr-integration';

            wp_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            $this->die(
                sprintf(
                    // Translators: %s: the error message.
                    __('Error from the Square Connect service: %s', 'memberpress'),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Render a wp_die() page with the given message, and end execution.
     *
     * @param string $message The message to display.
     */
    protected function die(string $message): void
    {
        wp_die(esc_html($message), '', ['back_link' => true]);
    }

    /**
     * Displays admin notices based on Square connection status.
     *
     * This method checks the `mepr-square-connect-status` query parameter in the URL
     * and outputs appropriate admin notices based on the status value. If the status
     * indicates an error, a corresponding error message is displayed. For success statuses,
     * the method renders a success notice with an appropriate message.
     */
    public function connect_admin_notices(): void
    {
        if (!MeprUtils::is_mepr_admin()) {
            return;
        }

        if (isset($_GET['mepr-square-connect-status'])) {
            $status = sanitize_text_field(wp_unslash($_GET['mepr-square-connect-status']));

            if ($status == 'error') {
                $error = sanitize_text_field(wp_unslash($_GET['error']));
                $error = empty($error) ? __('The payment method could not be connected to Square.', 'memberpress') : $error;

                printf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    esc_html($error)
                );
            } elseif ($status == 'connected') {
                $message = __('The Square payment method was successfully connected.', 'memberpress');
            } elseif ($status == 'disconnected') {
                $message = __('The Square payment method was successfully disconnected.', 'memberpress');
            } elseif ($status == 'refreshed') {
                $message = __('The Square payment method credentials have been updated.', 'memberpress');
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
     * Displays an admin notice if the access token for a Square gateway has expired or is about to expire.
     *
     * The method checks the credentials for connected Square gateways in different environments (production and optionally sandbox).
     * If the credentials have expired or are nearing expiration, it displays a warning message in the admin area, prompting the user to refresh the credentials
     * or contact support if necessary.
     *
     * @return void
     */
    public function expired_access_token_notice(): void
    {
        if (
            !MeprUtils::is_mepr_admin() ||
            !MeprUtils::is_memberpress_admin_page() ||
            !MeprHooks::apply_filters('mepr_square_expired_access_token_notice', true)
        ) {
            return;
        }

        $options = MeprOptions::fetch();
        $payment_methods = $options->payment_methods(false);
        $environments = ['production'];

        if (MeprHooks::apply_filters('mepr_square_expired_access_token_notice_sandbox', false)) {
            $environments[] = 'sandbox';
        }

        foreach ($payment_methods as $pm) {
            if (!$pm instanceof MeprSquareGateway) {
                continue;
            }

            foreach ($environments as $environment) {
                if (
                    !empty($pm->settings->{"{$environment}_connected"}) &&
                    !empty($pm->settings->{"{$environment}_expires_at"})
                ) {
                    try {
                        $timezone   = new DateTimeZone('UTC');
                        $expires_at = new DateTime($pm->settings->{"{$environment}_expires_at"}, $timezone);
                        $now        = new DateTime('now', $timezone);

                        if ($expires_at < $now) {
                            printf(
                                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                                sprintf(
                                    // Translators: %1$s: open tag for link to refresh credentials, %2$s: close link tag, %3$s: open tag for link to support.
                                    esc_html__('The credentials for the Square gateway have expired. To continue accepting payments, please %1$sRefresh Square Credentials%2$s to update them. %3$sContact support%2$s if this issue persists.', 'memberpress'),
                                    '<a href="' . esc_url($pm->refresh_credentials_url($environment)) . '">',
                                    '</a>',
                                    '<a href="https://memberpress.com/support/">'
                                )
                            );
                        } else {
                            $expire_days = $expires_at->diff($now)->days;

                            // Square access tokens expire after 30 days. The Square Connect app will refresh them every
                            // 7 days, if we get passed 8 days then there is a problem.
                            if (is_int($expire_days) && $expire_days < 22) {
                                printf(
                                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                                    sprintf(
                                        // Translators: %1$d: the number of days, %2$s: open tag for link to refresh credentials, %3$s: close link tag, %4$s: open tag for link to support.
                                        esc_html__('The credentials for the Square gateway will expire in %1$d days. To continue accepting payments, please %2$sRefresh Square Credentials%3$s to update them. %4$sContact support%3$s if this issue persists.', 'memberpress'),
                                        esc_html($expire_days),
                                        '<a href="' . esc_url($pm->refresh_credentials_url($environment)) . '">',
                                        '</a>',
                                        '<a href="https://memberpress.com/support/">'
                                    )
                                );
                            }
                        }
                    } catch (Exception $e) {
                        // Ignore DateTime errors.
                    }
                }
            }
        }
    }

    /**
     * Displays an admin notice when there is a currency mismatch between the configured MemberPress currency
     * and the currency used by the connected Square gateway.
     *
     * @return void
     */
    public function currency_mismatch_notice(): void
    {
        if (!MeprUtils::is_mepr_admin() || !MeprHooks::apply_filters('mepr_square_currency_mismatch_notice', true)) {
            return;
        }

        $screen_id = MeprUtils::get_current_screen_id();

        if (!is_string($screen_id) || !preg_match('/_page_memberpress-options$/', $screen_id)) {
            return;
        }

        $options = MeprOptions::fetch();
        $payment_methods = $options->payment_methods(false);
        $environments = ['production'];

        if (MeprHooks::apply_filters('mepr_square_currency_mismatch_notice_sandbox', true)) {
            $environments[] = 'sandbox';
        }

        $configured_currency = $options->currency_code;

        if (MeprUtils::is_post_request()) {
            $action = sanitize_text_field(wp_unslash($_POST['action'] ?? ''));

            if ($action == 'process-form') {
                $posted_currency = sanitize_text_field(wp_unslash($_POST['mepr-currency-code'] ?? ''));

                if (!empty($posted_currency)) {
                    $configured_currency = $posted_currency;
                }
            }
        }

        foreach ($payment_methods as $pm) {
            if (!$pm instanceof MeprSquareGateway) {
                continue;
            }

            foreach ($environments as $environment) {
                if (
                    !empty($pm->settings->{"{$environment}_currency"}) &&
                    $pm->settings->{"{$environment}_currency"} != $configured_currency
                ) {
                    printf(
                        '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                        esc_html(
                            sprintf(
                                // Translators: %1$s: the gateway currency, %2$s: the MemberPress currency.
                                __('The connected Square gateway processes payments in %1$s but the configured MemberPress currency code is %2$s. The gateway will not be usable if these currencies do not match.', 'memberpress'),
                                $pm->settings->{"{$environment}_currency"},
                                $configured_currency
                            )
                        )
                    );
                }
            }
        }
    }

    /**
     * Displays a notice regarding subscription cadence compatibility with Square payment gateway.
     *
     * @return void
     */
    public function subscription_cadence_notice(): void
    {
        if (
            !MeprUtils::is_mepr_admin() ||
            !MeprHooks::apply_filters('mepr_square_subscription_cadence_notice', true)
        ) {
            return;
        }

        $screen_id = MeprUtils::get_current_screen_id();

        if (!is_string($screen_id) || $screen_id != 'memberpressproduct') {
            return;
        }

        $options = MeprOptions::fetch();
        $has_square = false;

        foreach ($options->integrations as $integration) {
            if (!empty($integration['gateway']) && $integration['gateway'] == 'MeprSquareGateway') {
                $has_square = true;
                break;
            }
        }

        if (!$has_square) {
            return;
        }

        global $post;

        if (empty($post->ID)) {
            return;
        }

        $product = new MeprProduct($post->ID);

        if ($product->period_type == 'lifetime') {
            return;
        }

        try {
            MeprSquareGateway::get_cadence((string) $product->period_type, (int) $product->period);
        } catch (MeprGatewayException $e) {
            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                esc_html__('Due to incompatible pricing terms, Square is unavailable as a payment method for this membership.', 'memberpress')
            );
        }
    }
}
