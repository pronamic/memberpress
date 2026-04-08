<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

use MemberPress\Lcobucci\JWT\Encoding\ChainedFormatter;
use MemberPress\Lcobucci\JWT\Encoding\JoseEncoder;
use MemberPress\Lcobucci\JWT\Signer\Hmac\Sha256;
use MemberPress\Lcobucci\JWT\Signer\Key\InMemory;
use MemberPress\Lcobucci\JWT\Token\Builder;

#[AllowDynamicProperties]
class MeprPayPalVaultingGateway extends MeprBaseRealAjaxGateway
{
    /**
     * PayPal Vaulting Client IDs.
     */
    private const SANDBOX_CLIENT_ID    = 'AataiPtRz0GFmBuOgybdwoQC0oV4TcjULueGPiY-a7Xs55yq1lBIVEqzmQ1yvUff-gM0ueEk6JUJX01H';
    private const PRODUCTION_CLIENT_ID = 'AS3RYG_vomglgJGrzP85N79vkop1snHRQztmj_lzqMRATDOpsS2qvvPCZH0_1Rx6Sv-kEfja7FpPEtPe';

    /**
     * PayPal Partner Attribution IDs (BN Codes).
     */
    private const SANDBOX_BN_CODE    = 'FLAVORsb-fvw2w49547880_MP';
    private const PRODUCTION_BN_CODE = 'Memberpress_SP_PPCP';

    /**
     * Constructor - Initialize gateway properties.
     */
    public function __construct()
    {
        $this->name         = __('PayPal Complete Payments', 'memberpress');
        $this->key          = 'paypalvaulting';
        $this->has_spc_form = true;
        $this->set_defaults();

        // Set up notification handlers.
        $this->notifiers = [
            'webhook' => 'webhook_handler',
            'service' => 'service_webhook_handler',
        ];
    }

    /**
     * Load gateway settings.
     *
     * @param array|object $settings Gateway configuration.
     */
    public function load($settings)
    {
        $this->settings = (object) $settings;
        $this->set_defaults();
    }

    /**
     * Set default gateway settings.
     */
    protected function set_defaults()
    {
        if (!isset($this->settings)) {
            $this->settings = [];
        }

        $this->settings = (object) array_merge(
            [
                'gateway'                => 'MeprPayPalVaultingGateway',
                'id'                     => $this->generate_id(),
                'label'                  => '',
                'use_label'              => true,
                'icon'                   => MEPR_IMAGES_URL . '/checkout/paypal.png',
                'use_icon'               => true,
                'desc'                   => __('Pay securely via PayPal', 'memberpress'),
                'use_desc'               => true,
                'sandbox'                     => false,
                'production_connected'        => false,
                'production_merchant_id'      => '',
                'production_primary_email'    => '',
                'production_primary_currency' => '',
                'production_country'          => '',
                'production_public_id'        => '',
                'production_secret_key'       => '',
                'sandbox_connected'           => false,
                'sandbox_merchant_id'         => '',
                'sandbox_primary_email'       => '',
                'sandbox_primary_currency'    => '',
                'sandbox_country'             => '',
                'sandbox_public_id'           => '',
                'sandbox_secret_key'          => '',
                'saved'                       => false,
                'debug'                  => false,
                'enable_paypal'          => true,
                'enable_paylater'        => true,
                'enable_credit'          => true,
                'enable_venmo'           => false,
                'enable_card'            => false,
                'enable_advanced_cards'  => true,
                'enable_apple_pay'       => false,
                'enable_google_pay'      => false,
            ],
            (array) $this->settings
        );

        // Set instance properties from settings.
        $this->id        = $this->settings->id;
        $this->label     = $this->settings->label;
        $this->use_label = $this->settings->use_label;
        $this->icon      = $this->settings->icon;
        $this->use_icon  = $this->settings->use_icon;
        $this->desc      = $this->settings->desc;
        $this->use_desc  = $this->settings->use_desc;

        // Define gateway capabilities.
        $this->capabilities = [
            'process-payments',
            'process-refunds',
            'create-subscriptions',
            'cancel-subscriptions',
            'update-subscriptions',
            'suspend-subscriptions',
            'resume-subscriptions',
            'subscription-trial-payment',
            'order-bumps',
            'multiple-subscriptions',
        ];
    }

    /**
     * Check if gateway is in test mode
     *
     * @return boolean
     */
    public function is_test_mode(): bool
    {
        return $this->settings->sandbox;
    }

    /**
     * Check if the gateway is usable.
     *
     * @return boolean Whether the gateway is usable.
     */
    public function is_usable(): bool
    {
        $environment = $this->is_test_mode() ? 'sandbox' : 'production';
        $is_usable   = !empty($this->settings->{"{$environment}_connected"});

        return (bool) MeprHooks::apply_filters('mepr_paypal_vaulting_is_usable', $is_usable, $this);
    }

    /**
     * Get the PayPal Client ID
     *
     * @return string
     */
    public function get_client_id(): string
    {
        $client_id = $this->is_test_mode() ? self::SANDBOX_CLIENT_ID : self::PRODUCTION_CLIENT_ID;

        return MeprHooks::apply_filters('mepr_paypal_vaulting_client_id', $client_id, $this);
    }

    /**
     * Get the PayPal Partner Attribution ID (BN Code)
     *
     * @return string
     */
    public function get_bn_code(): string
    {
        return $this->is_test_mode() ? self::SANDBOX_BN_CODE : self::PRODUCTION_BN_CODE;
    }

    /**
     * Retrieves the merchant ID based on the current environment (sandbox or production).
     *
     * @return string The merchant ID corresponding to the active environment.
     */
    public function get_merchant_id(): string
    {
        $setting = ($this->settings->sandbox ? 'sandbox' : 'production') . '_merchant_id';

        return $this->settings->{$setting};
    }

    /**
     * Check if Pay Later messages should be shown.
     *
     * Pay Later messages require the PayPal account's primary currency to match
     * the site currency. If there's a mismatch, messages will fail to render.
     *
     * @return boolean True if Pay Later messages should be shown.
     */
    protected function should_show_paylater_messages(): bool
    {
        $mepr_options      = MeprOptions::fetch();
        $environment       = $this->is_test_mode() ? 'sandbox' : 'production';
        $primary_currency  = strtoupper($this->settings->{"{$environment}_primary_currency"});
        $site_currency     = strtoupper($mepr_options->currency_code);

        // Only show messages if the primary currency matches the site currency.
        return $primary_currency === $site_currency;
    }

    /**
     * Get the PayPal Vaulting service domain.
     *
     * @param  string $environment The environment: 'production' or 'sandbox'.
     * @return string
     */
    public static function vaulting_service_domain(string $environment): string
    {
        if ($environment === 'sandbox') {
            if (defined('MEPR_PAYPAL_VAULTING_SERVICE_SANDBOX_DOMAIN')) {
                $domain = MEPR_PAYPAL_VAULTING_SERVICE_SANDBOX_DOMAIN;
            } else {
                $domain = 'payments-sandbox.caseproof.com';
            }

            return MeprHooks::apply_filters('mepr_paypal_vaulting_service_sandbox_domain', $domain);
        } else {
            if (defined('MEPR_PAYPAL_VAULTING_SERVICE_DOMAIN')) {
                $domain = MEPR_PAYPAL_VAULTING_SERVICE_DOMAIN;
            } else {
                $domain = 'payments.caseproof.com';
            }

            return MeprHooks::apply_filters('mepr_paypal_vaulting_service_domain', $domain);
        }
    }

    /**
     * Get the PayPal Vaulting service URL.
     *
     * @param  string $environment The environment: 'production' or 'sandbox'.
     * @return string
     */
    public static function vaulting_service_url(string $environment): string
    {
        return set_url_scheme(
            'https://' . self::vaulting_service_domain($environment),
            MeprHooks::apply_filters('mepr_paypal_vaulting_service_https', true) ? 'https' : 'http'
        );
    }

    /**
     * Get the URL to connect to PayPal Vaulting service.
     *
     * @param string $environment The environment: 'production' or 'sandbox'.
     *
     * @return string The generated URL.
     *
     * @throws MeprException If there was an error generating the JWT.
     */
    public function connect_url(string $environment): string
    {
        $return_url = add_query_arg(
            [
                'mepr_paypal_vaulting_action' => 'process_connect_return',
                '_wpnonce'                    => wp_create_nonce('mepr_paypal_vaulting_process_connect_return'),
                'pmt'                         => $this->id,
                'environment'                 => $environment,
            ],
            admin_url('admin.php')
        );

        $claims = [
            'payment_method_id'   => $this->id,
            'return_url'          => $return_url,
            'webhook_url'         => $this->notify_url('webhook'),
            'service_webhook_url' => $this->notify_url('service'),
            'license_domain'      => MeprUtils::site_domain(),
            'license_key'         => MeprOptions::fetch()->mothership_license,
        ];

        $site_uuid = get_option('mepr_authenticator_site_uuid');
        $jwt       = self::generate_jwt($environment, $claims);

        return self::vaulting_service_url($environment) . "/memberpress/paypal/connect/$site_uuid/$jwt";
    }

    /**
     * Get the URL to connect to the PayPal Vaulting service, via the Authenticator if the site is not yet Authenticator connected.
     *
     * @param string $environment The environment: 'production' or 'sandbox'.
     *
     * @return string The generated URL.
     *
     * @throws MeprException If the secret token is not configured.
     */
    public function connect_auth_url(string $environment): string
    {
        $account_email = get_option('mepr_authenticator_account_email');
        $secret        = get_option('mepr_authenticator_secret_token');
        $site_uuid     = get_option('mepr_authenticator_site_uuid');

        if ($account_email && $secret && $site_uuid) {
            return $this->connect_url($environment);
        } else {
            $return_url = add_query_arg(
                array_map(
                    'rawurlencode',
                    [
                        'paypal_vaulting_connect'  => 'true',
                        'paypal_payment_method_id' => $this->id,
                        'paypal_environment'       => $environment,
                    ]
                ),
                admin_url('admin.php?page=memberpress-account-login')
            );

            return MeprAuthenticatorCtrl::get_auth_connect_url(false, false, [], $return_url);
        }
    }

    /**
     * Generate a JWT for authenticating with the PayPal Vaulting service.
     *
     * @param string $environment The environment: 'production' or 'sandbox'.
     * @param array  $claims      Optional additional claims to include in the JWT.
     *
     * @return string The generated JWT.
     *
     * @throws MeprException If the secret token is not configured.
     */
    protected static function generate_jwt(string $environment, array $claims = []): string
    {
        $key = get_option('mepr_authenticator_secret_token');

        if (empty($key)) {
            throw new MeprException(esc_html__('Invalid secret token', 'memberpress'));
        }

        $builder     = new Builder(new JoseEncoder(), ChainedFormatter::withUnixTimestampDates());
        $algorithm   = new Sha256();
        $signing_key = InMemory::plainText($key);

        $builder
            ->issuedBy(wp_parse_url(get_site_url(), PHP_URL_HOST))
            ->permittedFor(self::vaulting_service_domain($environment));

        foreach ($claims as $name => $value) {
            $builder->withClaim($name, $value);
        }

        $token = $builder->getToken($algorithm, $signing_key);

        return $token->toString();
    }

    /**
     * Fetch credentials from the PayPal Vaulting service.
     *
     * @param string $environment The environment: 'production' or 'sandbox'.
     *
     * @throws MeprException If the JWT could not be generated.
     * @throws MeprGatewayException If there was an error with the request or the response was invalid.
     */
    public function fetch_credentials(string $environment): void
    {
        $site_uuid = get_option('mepr_authenticator_site_uuid');
        $url       = self::vaulting_service_url($environment);
        $jwt       = self::generate_jwt($environment);

        $response = wp_remote_get(
            "$url/api/v1/memberpress/paypal/credentials/$site_uuid/$this->id",
            [
                'headers' => MeprUtils::jwt_header(
                    $jwt,
                    self::vaulting_service_domain($environment)
                ),
            ]
        );

        $this->store_credentials($environment, $this->parse_credentials_response($response));
    }

    /**
     * Parse the response from one of the credentials endpoints.
     *
     * @param array|WP_Error $response The request response.
     *
     * @return array The credentials data.
     *
     * @throws MeprGatewayException If there was an error with the request or the response was invalid.
     */
    protected function parse_credentials_response($response): array
    {
        if (wp_remote_retrieve_response_code($response) === 200) {
            $credentials = json_decode(wp_remote_retrieve_body($response), true);

            if (
                is_array($credentials) &&
                !empty($credentials['merchant_id']) &&
                !empty($credentials['public_id']) &&
                !empty($credentials['secret_key'])
            ) {
                return $credentials;
            } else {
                throw new MeprGatewayException('Invalid credentials');
            }
        } else {
            throw new MeprGatewayException('Error fetching credentials');
        }
    }

    /**
     * Store the given credentials for this gateway.
     *
     * @param string $environment The environment to update the stored credentials for (e.g., 'production' or 'sandbox').
     * @param array  $credentials The array of credential data.
     *
     * @return void
     */
    protected function store_credentials(string $environment, array $credentials): void
    {
        $options = MeprOptions::fetch();

        foreach ($options->integrations as $id => $integration) {
            if ($integration['gateway'] !== self::class) {
                continue;
            }

            if ($integration['id'] === $this->id) {
                $integration["{$environment}_connected"]        = true;
                $integration["{$environment}_merchant_id"]      = $credentials['merchant_id'];
                $integration["{$environment}_primary_email"]    = $credentials['primary_email'];
                $integration["{$environment}_primary_currency"] = $credentials['primary_currency'];
                $integration["{$environment}_country"]          = $credentials['country'];
                $integration["{$environment}_public_id"]        = $credentials['public_id'];
                $integration["{$environment}_secret_key"]       = $credentials['secret_key'];

                $options->integrations[$id] = $integration;
                break;
            }
        }

        $options->store(false);
    }

    /**
     * Generate the URL to refresh the credentials for this PayPal gateway.
     *
     * @param string $environment The environment to refresh the credentials for (e.g., 'production' or 'sandbox').
     *
     * @return string
     */
    public function refresh_credentials_url(string $environment): string
    {
        return add_query_arg(
            array_map(
                'rawurlencode',
                [
                    'mepr_paypal_vaulting_action' => 'process_refresh_credentials',
                    'payment_method_id'           => $this->id,
                    'environment'                 => $environment,
                    '_wpnonce'                    => wp_create_nonce(
                        'mepr_paypal_vaulting_process_refresh_credentials'
                    ),
                ]
            ),
            admin_url('admin.php')
        );
    }

    /**
     * Refresh the credentials for the given environment.
     *
     * @param string $environment The environment to refresh the credentials for (e.g., 'production' or 'sandbox').
     *
     * @throws MeprException If there was an error generating the JWT.
     * @throws MeprGatewayException If there was an error with the request or the response was invalid.
     */
    public function refresh_credentials(string $environment): void
    {
        $site_uuid = get_option('mepr_authenticator_site_uuid');
        $url       = self::vaulting_service_url($environment);
        $jwt       = self::generate_jwt($environment);

        $response = wp_remote_post(
            "$url/api/v1/memberpress/paypal/refresh/$site_uuid/$this->id",
            [
                'headers' => MeprUtils::jwt_header(
                    $jwt,
                    self::vaulting_service_domain($environment)
                ),
            ]
        );

        $this->store_credentials($environment, $this->parse_credentials_response($response));
    }

    /**
     * Generate the URL to disconnect this PayPal gateway.
     *
     * @param string $environment The environment to disconnect from (e.g., 'production' or 'sandbox').
     *
     * @return string
     */
    public function disconnect_url(string $environment): string
    {
        return add_query_arg(
            array_map(
                'rawurlencode',
                [
                    'mepr_paypal_vaulting_action' => 'process_disconnect',
                    'payment_method_id'           => $this->id,
                    'environment'                 => $environment,
                    '_wpnonce'                    => wp_create_nonce('mepr_paypal_vaulting_process_disconnect'),
                ]
            ),
            admin_url('admin.php')
        );
    }

    /**
     * Disconnect locally only, without affecting the remote connection.
     *
     * This method only updates the local WordPress options to mark the gateway
     * as disconnected. It does NOT make any API calls to the payments service,
     * so the remote connection and webhooks remain active.
     *
     * Use this when disconnecting from a staging/dev site to avoid affecting
     * the live site's configuration.
     *
     * @param  string $environment The environment (sandbox or production).
     * @return void
     */
    public function local_disconnect(string $environment): void
    {
        $options = MeprOptions::fetch();

        foreach ($options->integrations as $id => $integration) {
            if ($integration['gateway'] !== self::class) {
                continue;
            }

            if ($integration['id'] === $this->id) {
                $integration["{$environment}_connected"] = false;
                $options->integrations[$id]              = $integration;
                break;
            }
        }

        $options->store(false);
    }

    /**
     * Disconnect from the remote PayPal service.
     *
     * This method makes an API call to the payments service to disconnect
     * the PayPal connection. It can optionally bypass domain validation
     * using the force parameter.
     *
     * @param  string  $environment The environment (sandbox or production).
     * @param  boolean $force       Whether to bypass domain validation.
     * @return void
     * @throws MeprException                      If the JWT could not be generated.
     * @throws MeprGatewayException               If the remote disconnect fails.
     * @throws MeprGatewayDomainMismatchException If domain mismatch detected (when force=false).
     *
     * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
     */
    public function remote_disconnect(string $environment, bool $force): void
    {
        $site_uuid = get_option('mepr_authenticator_site_uuid');
        $url       = self::vaulting_service_url($environment);
        $jwt       = self::generate_jwt($environment);

        $disconnect_url = "$url/api/v1/memberpress/paypal/disconnect/$site_uuid/$this->id";

        if ($force) {
            $disconnect_url = add_query_arg(['force' => '1'], $disconnect_url);
        }

        $response = wp_remote_post(
            $disconnect_url,
            [
                'headers' => MeprUtils::jwt_header(
                    $jwt,
                    self::vaulting_service_domain($environment)
                ),
            ]
        );

        $response_code = wp_remote_retrieve_response_code($response);

        // Check for domain mismatch (409 Conflict) - only when not forcing.
        if (!$force && $response_code === 409) {
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['error']) && $body['error'] === 'domain_mismatch') {
                // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception constructor, not output.
                throw new MeprGatewayDomainMismatchException(
                    $body['expected_domain'] ?? 'unknown',
                    $body['actual_domain'] ?? 'unknown'
                );
                // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }

        // Normal success check.
        if ($response_code !== 204) {
            throw new MeprGatewayException('Error disconnecting from PayPal');
        }
    }

    /**
     * Make an API request to the PayPal Vaulting service.
     *
     * @param string $method   HTTP method (GET, POST, PUT, DELETE).
     * @param string $endpoint API endpoint path.
     * @param array  $data     Request data.
     *
     * @return array|null Response data, or null for 204 No Content responses.
     * @throws MeprHttpException   If HTTP request fails.
     * @throws MeprRemoteException If API returns an error.
     */
    public function api_request(
        string $method,
        string $endpoint,
        array $data = []
    ): ?array {
        $method      = strtoupper($method);
        $environment = $this->settings->sandbox ? 'sandbox' : 'production';
        $public_id   = $this->settings->{$environment . '_public_id'};
        $secret_key  = $this->settings->{$environment . '_secret_key'};

        $args = [
            'method'  => $method,
            'headers' => [
                'Accept'            => 'application/json',
                'Authorization'     => 'Basic ' . base64_encode("$public_id:$secret_key"), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                'Content-Type'      => 'application/json',
                'X-License-Domain' => MeprUtils::site_domain(),
                'X-License-Key'    => MeprOptions::fetch()->mothership_license,
            ],
        ];

        if (count($data) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request(
            $this->vaulting_service_url($environment) . '/' . ltrim($endpoint, '/'),
            $args
        );

        if (is_wp_error($response)) {
            throw new MeprHttpException(
                sprintf(
                    // Translators: %s: gateway name.
                    esc_html__('You had an HTTP error connecting to %s', 'memberpress'),
                    esc_html($this->name)
                )
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Handle successful responses (2xx).
        if ($code >= 200 && $code < 300) {
            // 204 No Content or empty body - return null.
            if ($code === 204 || empty($body)) {
                return null;
            }

            // Decode JSON response.
            $json = json_decode($body, true);

            // Valid JSON response - return it.
            if (is_array($json)) {
                return $json;
            }

            // Success status but invalid JSON - log and throw.
            MeprUtils::debug_log(
                sprintf(
                    'PayPal Vaulting API returned invalid JSON [%d %s]: Response: %s',
                    $code,
                    $method . ' ' . $endpoint,
                    $body
                )
            );

            throw new MeprRemoteException(__('Payment processor returned invalid response.', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        // Log error responses for debugging.
        MeprUtils::debug_log(
            sprintf(
                'PayPal Vaulting API Error [%d %s]: Request: %s, Response: %s',
                $code,
                $method . ' ' . $endpoint,
                wp_json_encode($data),
                $body
            )
        );

        // Throw an exception for flow control (message not shown to the user).
        throw new MeprRemoteException(__('Payment processor request failed.', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    /**
     * Process a payment via Ajax.
     *
     * @param MeprProduct           $prd The product.
     * @param MeprUser              $usr The user.
     * @param MeprTransaction       $txn The transaction.
     * @param MeprSubscription|null $sub The subscription (null for one-time payments).
     * @param MeprCoupon|null       $cpn The coupon (null if no coupon).
     */
    public function process_payment_ajax(
        MeprProduct $prd,
        MeprUser $usr,
        MeprTransaction $txn,
        MeprSubscription $sub = null,
        MeprCoupon $cpn = null
    ) {
        try {
            // Validate payment method from the frontend.
            $payment_method          = sanitize_text_field(wp_unslash($_POST['mepr_paypal_payment_method'] ?? ''));
            $allowed_payment_methods = ['paypal', 'paylater', 'credit', 'venmo', 'card', 'apple_pay', 'google_pay'];

            if (!in_array($payment_method, $allowed_payment_methods, true)) {
                wp_send_json_error(__('Invalid payment method selected.', 'memberpress'));
            }

            if (in_array($payment_method, ['paylater', 'credit'], true)) {
                $payment_method = 'paypal';
            }

            // Validate and format return URL for App Switch.
            $current_url = sanitize_url(wp_unslash($_POST['mepr_current_url'] ?? ''));
            $return_url  = $this->validate_and_format_return_url($current_url, $payment_method);

            // Process order bumps and build order data for the PayPal API.
            list($ob_transactions) = $this->process_order_bumps($prd, $usr, $txn, $sub);
            $request_data          = $this->build_order_data($prd, $usr, $txn, $sub, $cpn, $payment_method, $ob_transactions, $return_url);

            // Choose the endpoint based on whether payment is required.
            $endpoint = $request_data['total'] > 0.00
                ? 'api/v1/memberpress/paypal/payments'
                : 'api/v1/memberpress/paypal/setup-tokens';

            // Make the API request to create an order or a setup token.
            $data = $this->api_request('POST', $endpoint, $request_data);

            // Store the order ID/setup token ID as trans_num on the transaction.
            $txn->trans_num = $data['id'];
            $txn->store();

            // Return the order ID/setup token ID to the frontend.
            wp_send_json_success($data['id']);
        } catch (MeprGatewayException $e) {
            // Gateway exceptions have user-facing messages.
            wp_send_json_error($e->getMessage());
        } catch (Exception $e) {
            // Error already logged in api_request() - show generic message to the user.
            wp_send_json_error(__('There was an issue processing your payment. Please try again.', 'memberpress'));
        }
    }

    /**
     * Validate and format a return URL for PayPal requests.
     *
     * @param  string $return_url     The base return URL.
     * @param  string $payment_method The payment method type.
     * @return string The validated and formatted return URL.
     * @throws MeprGatewayException If the return URL is not provided, invalid, or external.
     */
    protected function validate_and_format_return_url(string $return_url, string $payment_method): string
    {
        // Check if a URL is provided.
        if (empty($return_url)) {
            throw new MeprGatewayException(__('A valid return URL is required.', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        // Validate that the URL belongs to this site (throws exception if invalid/external).
        $validated_url = wp_validate_redirect($return_url, false);
        if ($validated_url === false) {
            throw new MeprGatewayException(__('Invalid or external return URL provided.', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        // Remove fragment identifier and build return URL with payment method query param.
        $return_url = strtok($validated_url, '#') ?: $validated_url;

        return add_query_arg('paypal_payment_method', $payment_method, $return_url);
    }

    /**
     * Build order data for PayPal API requests.
     *
     * @param  MeprProduct           $prd             The product.
     * @param  MeprUser              $usr             The user.
     * @param  MeprTransaction       $txn             The transaction.
     * @param  MeprSubscription|null $sub             The subscription (null for one-time payments).
     * @param  MeprCoupon|null       $cpn             The coupon (null if no coupon).
     * @param  string                $payment_method  The payment method type ('paypal', 'card', or 'venmo').
     * @param  array                 $ob_transactions Array of order bump transactions.
     * @param  string                $return_url      The return URL.
     * @return array The order data array.
     */
    protected function build_order_data(
        MeprProduct $prd,
        MeprUser $usr,
        MeprTransaction $txn,
        ?MeprSubscription $sub,
        ?MeprCoupon $cpn,
        string $payment_method,
        array $ob_transactions,
        string $return_url
    ): array {
        $mepr_options = MeprOptions::fetch();
        $coupon_code  = $cpn instanceof MeprCoupon ? $cpn->post_title : '';

        // Build the item array for the PayPal API.
        $items          = [];
        $total_subtotal = 0.00;
        $total_tax      = 0.00;
        $total_amount   = 0.00;

        // Combine the main transaction with order bump transactions for unified processing.
        $transactions = array_merge([$txn], $ob_transactions);

        // Process all transactions.
        foreach ($transactions as $index => $transaction) {
            // Get product and subscription for current transaction.
            if ($index === 0) {
                // Main transaction - use passed parameters.
                $product      = $prd;
                $subscription = $sub;
                $coupon       = $coupon_code;
            } else {
                // Order bump transaction - fetch from transaction.
                $product      = new MeprProduct($transaction->product_id);
                $subscription = $transaction->subscription();
                $coupon       = '';
            }

            // Check payment requirement.
            $is_payment_required = $product->is_payment_required($coupon);

            // Truncate the product name to 127 characters, with fallback for empty titles.
            $product_name = trim($product->post_title);
            if (empty($product_name)) {
                $product_name = sprintf(
                    // Translators: %d: the product ID.
                    __('Product %d', 'memberpress'),
                    $product->ID
                );
            }
            $product_name = mb_substr($product_name, 0, 127);

            // Handle one-time payments and free products first.
            if ($product->is_one_time_payment() || !$is_payment_required) {
                $item = [
                    'type'         => 'one-time',
                    'product_id'   => $product->ID,
                    'product_name' => $product_name,
                    'reference_id' => (int) $transaction->id,
                    'subtotal'     => $this->to_base_currency_unit((float) $transaction->amount),
                    'tax'          => $this->to_base_currency_unit((float) $transaction->tax_amount),
                    'total'        => $this->to_base_currency_unit((float) $transaction->total),
                ];
            } else {
                // Handle subscriptions.
                if (!$subscription instanceof MeprSubscription) {
                    continue;
                }

                $has_trial = $subscription->trial && $subscription->trial_days;

                $item = [
                    'type'                 => 'subscription',
                    'product_id'           => $product->ID,
                    'product_name'         => $product_name,
                    'reference_id'         => (int) $subscription->id,
                    'billing_period'       => $this->normalize_billing_period($subscription->period_type),
                    'billing_period_count' => (int) $subscription->period,
                    'recurring_subtotal'   => $this->to_base_currency_unit((float) $subscription->price),
                    'recurring_tax'        => $this->to_base_currency_unit((float) $subscription->tax_amount),
                    'recurring_total'      => $this->to_base_currency_unit((float) $subscription->total),
                ];

                // Add trial information if applicable.
                if ($has_trial) {
                    $item['trial']          = true;
                    $item['trial_days']     = (int) $subscription->trial_days;
                    $item['trial_subtotal'] = $this->to_base_currency_unit((float) $subscription->trial_amount);
                    $item['trial_tax']      = $this->to_base_currency_unit((float) $subscription->trial_tax_amount);
                    $item['trial_total']    = $this->to_base_currency_unit((float) $subscription->trial_total);

                    // For the initial payment, use trial amounts.
                    $item['subtotal'] = $item['trial_subtotal'];
                    $item['tax']      = $item['trial_tax'];
                    $item['total']    = $item['trial_total'];
                } else {
                    $item['trial'] = false;

                    // For the initial payment, use recurring amounts.
                    $item['subtotal'] = $item['recurring_subtotal'];
                    $item['tax']      = $item['recurring_tax'];
                    $item['total']    = $item['recurring_total'];
                }

                // Add billing cycle limit if product has a limit.
                if ($product->limit_cycles && $product->limit_cycles_num > 0) {
                    $item['billing_cycle_limit'] = (int) $product->limit_cycles_num;
                }
            }

            $items[]         = $item;
            $total_subtotal += $item['subtotal'];
            $total_tax      += $item['tax'];
            $total_amount   += $item['total'];
        }

        // Build and return the request data.
        return [
            'items'          => $items,
            'subtotal'       => $total_subtotal,
            'tax'            => $total_tax,
            'total'          => $total_amount,
            'currency'       => strtoupper($mepr_options->currency_code),
            'payment_method' => $payment_method,
            'customer_email' => $usr->user_email,
            'return_url'     => $return_url,
            'cancel_url'     => $prd->url(),
        ];
    }

    /**
     * Build the 'Thank You' page URL.
     *
     * @param  MeprProduct           $prd The product.
     * @param  MeprTransaction       $txn The transaction.
     * @param  MeprSubscription|null $sub The subscription.
     * @return string Thank you page URL.
     */
    protected function build_thank_you_url(
        MeprProduct $prd,
        MeprTransaction $txn,
        ?MeprSubscription $sub = null
    ): string {
        $mepr_options = MeprOptions::fetch();

        $thank_you_page_args = [
            'membership'     => sanitize_title($prd->post_title),
            'membership_id'  => $prd->ID,
            'transaction_id' => $txn->id,
        ];

        if ($sub instanceof MeprSubscription) {
            $thank_you_page_args['subscription_id'] = $sub->id;
        }

        return $mepr_options->thankyou_page_url($thank_you_page_args);
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * Complete a PayPal transaction (capture payment or confirm setup token).
     *
     * @param string $id             The PayPal order ID or setup token ID.
     * @param string $payment_method The payment method type.
     * @param string $type           The transaction type: 'payment' or 'setup'.
     *
     * @return string The 'Thank You' page URL.
     * @throws MeprGatewayException If completion fails or required data is missing.
     * @throws MeprHttpException If there is an HTTP error (e.g., timeout or unreachable host).
     * @throws MeprRemoteException If the API request failed.
     */
    public function complete_transaction(string $id, string $payment_method, string $type): string
    {
        // Look up the transaction by ID (stored as trans_num).
        $txn = MeprTransaction::get_instance_by_trans_num($id);

        if (!$txn instanceof MeprTransaction) {
            throw new MeprGatewayException(__('Transaction not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $prd = $txn->product();

        if (!$prd instanceof MeprProduct) {
            throw new MeprGatewayException(__('Product not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $usr = $txn->user();

        if (!$usr instanceof MeprUser) {
            throw new MeprGatewayException(__('User not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $sub = $txn->subscription();
        $sub = $sub instanceof MeprSubscription ? $sub : null;
        $cpn = $txn->coupon();
        $cpn = $cpn instanceof MeprCoupon ? $cpn : null;

        // Look up order bump transactions if this is part of an order.
        $ob_transactions = [];
        $order           = $txn->order();
        if ($order instanceof MeprOrder) {
            $ob_transactions = MeprTransaction::get_all_by_order_id($order->id, $txn->id);
        }

        // Validate and format return URL.
        $current_url = sanitize_url(wp_unslash($_POST['mepr_current_url'] ?? ''));
        $return_url  = $this->validate_and_format_return_url($current_url, $payment_method);

        // Determine the API endpoint based on type.
        $is_payment = $type === 'payment';
        $endpoint   = $is_payment
            ? "api/v1/memberpress/paypal/payments/$id/capture"
            : "api/v1/memberpress/paypal/setup-tokens/$id/confirm";

        // Make the API request.
        $response = $this->api_request(
            'POST',
            $endpoint,
            $this->build_order_data(
                $prd,
                $usr,
                $txn,
                $sub,
                $cpn,
                $payment_method,
                $ob_transactions,
                $return_url
            )
        );

        // Extract the capture ID from the response if this is a payment.
        if ($is_payment) {
            $response_id = $response['capture_id'] ?? null;

            if (empty($response_id)) {
                throw new MeprGatewayException(__('Capture ID not found in response', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        } else {
            $response_id = '';
        }

        // Extract subscriptions and card details from the response.
        $subscriptions = $response['subscriptions'] ?? [];
        $card          = $response['card'] ?? [];

        // Handle transaction numbering and recording.
        if ($order instanceof MeprOrder && count($ob_transactions)) {
            // Multi-item purchase: use mi_ numbers for transactions and response ID for order.
            $trans_num = sprintf('mi_%d_%s', $order->id, uniqid());

            // Record the main transaction.
            if ($prd->is_one_time_payment()) {
                $this->record_one_time_payment($txn, $trans_num);
            } else {
                if (!$sub instanceof MeprSubscription) {
                    throw new MeprGatewayException(__('Subscription not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }

                $subscr_id = $subscriptions[$sub->id] ?? null;
                if (empty($subscr_id)) {
                    throw new MeprGatewayException(__('Subscription not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }

                $this->handle_subscription_capture($sub, $subscr_id, $card, $trans_num, (int) $order->id);
            }

            // Process order bump transactions.
            foreach ($ob_transactions as $ob_txn) {
                $ob_trans_num = sprintf('mi_%d_%s', $order->id, uniqid());
                $ob_product   = $ob_txn->product();

                if (!$ob_product instanceof MeprProduct) {
                    throw new MeprGatewayException(__('Product not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }

                if (!$ob_txn->is_payment_required()) {
                    MeprTransaction::create_free_transaction($ob_txn, false, $ob_trans_num);
                    continue;
                }

                if ($ob_product->is_one_time_payment()) {
                    $this->record_one_time_payment($ob_txn, $ob_trans_num);
                } else {
                    $ob_sub = $ob_txn->subscription();
                    if (!$ob_sub instanceof MeprSubscription) {
                        throw new MeprGatewayException(__('Subscription not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    }

                    $ob_subscr_id = $subscriptions[$ob_sub->id] ?? null;
                    if (empty($ob_subscr_id)) {
                        throw new MeprGatewayException(__('Subscription not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    }

                    $this->handle_subscription_capture($ob_sub, $ob_subscr_id, $card, $ob_trans_num, (int) $order->id);
                }
            }

            // Set the order trans_num to response ID if we have one.
            if (!empty($response_id)) {
                $order->trans_num = $response_id;
            }
            $order->status = MeprOrder::$complete_str;
            $order->store();
        } else {
            // Single item purchase: use response ID directly for transaction.
            if ($prd->is_one_time_payment()) {
                $this->record_one_time_payment($txn, $response_id);
            } else {
                if (!$sub instanceof MeprSubscription) {
                    throw new MeprGatewayException(__('Subscription not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }

                $subscr_id = $subscriptions[$sub->id] ?? null;
                if (empty($subscr_id)) {
                    throw new MeprGatewayException(__('Subscription not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }

                $this->handle_subscription_capture($sub, $subscr_id, $card, $response_id);
            }
        }

        return $this->build_thank_you_url($prd, $txn, $sub);
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    /**
     * Normalize the billing period from MemberPress format to PayPal API format.
     *
     * Converts plural forms (days, weeks, months, years) to singular forms (day, week, month, year).
     *
     * @param  string $period_type The MemberPress period type.
     * @return string The normalized period type.
     */
    protected function normalize_billing_period(string $period_type): string
    {
        $map = [
            'days'   => 'day',
            'weeks'  => 'week',
            'months' => 'month',
            'years'  => 'year',
        ];

        return $map[$period_type] ?? $period_type;
    }

    /**
     * Handle subscription capture by updating the subscription with platform data.
     *
     * @param MeprSubscription $sub       The subscription.
     * @param string           $subscr_id The platform subscription ID.
     * @param array            $card      Card details (last4, exp_month, exp_year).
     * @param string           $trans_num Transaction number for payment record.
     * @param integer          $order_id  The order ID for multi-item purchases.
     *
     * @return void
     */
    protected function handle_subscription_capture(
        MeprSubscription $sub,
        string $subscr_id,
        array $card,
        string $trans_num,
        int $order_id = 0
    ): void {
        // Update subscription with platform subscription ID and card details.
        $sub->subscr_id    = $subscr_id;
        $sub->cc_last4     = $card['last4'] ?? '';
        $sub->cc_exp_month = $card['exp_month'] ?? '';
        $sub->cc_exp_year  = $card['exp_year'] ?? '';

        // Activate the subscription.
        $this->record_create_sub($sub);

        // Record the initial payment if there was an amount charged.
        if ($sub->trial && $sub->trial_days > 0) {
            $total                   = (float) $sub->trial_total;
            $txn_expires_at_override = MeprUtils::ts_to_mysql_date(
                time() + MeprUtils::days($sub->trial_days),
                'Y-m-d 23:59:59'
            );
        } else {
            $total                   = (float) $sub->total;
            $txn_expires_at_override = null;
        }

        if ($total > 0) {
            $this->record_sub_payment($sub, $total, $trans_num, $card, $txn_expires_at_override, $order_id);
        }
    }

    /**
     * Process refund
     *
     * @param MeprTransaction $txn Transaction object.
     *
     * @return void
     *
     * @throws MeprHttpException If there was an error with the request.
     * @throws MeprRemoteException If the API request failed.
     */
    public function process_refund($txn)
    {
        $capture_id = $txn->trans_num;

        $this->api_request(
            'POST',
            "api/v1/memberpress/paypal/payments/{$capture_id}/refund"
        );

        $this->record_transaction_refund($txn);
    }

    /**
     * Record the transaction as refunded and send refunded transaction email notifications.
     *
     * @param MeprTransaction $txn The transaction being refunded.
     *
     * @return void
     */
    protected function record_transaction_refund(MeprTransaction $txn)
    {
        if ($txn->status === MeprTransaction::$refunded_str) {
            return;
        }

        $txn->status = MeprTransaction::$refunded_str;
        $txn->store();

        MeprUtils::send_refunded_txn_notices($txn);
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * Suspend subscription
     *
     * @param  integer $subscription_id Subscription ID.
     * @throws MeprGatewayException If the subscription cannot be paused.
     * @throws MeprHttpException If there was an HTTP error with the request.
     * @throws MeprRemoteException If the API request failed.
     */
    public function process_suspend_subscription($subscription_id)
    {
        $sub = new MeprSubscription($subscription_id);

        if (!($sub->id > 0)) {
            throw new MeprGatewayException(__('Subscription not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ($sub->status === MeprSubscription::$suspended_str) {
            throw new MeprGatewayException(__('This subscription has already been paused.', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if (!MeprUtils::is_mepr_admin() && $sub->in_free_trial()) {
            throw new MeprGatewayException(
                __('Sorry, subscriptions cannot be paused during a free trial.', 'memberpress') // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $this->api_request('POST', "api/v1/memberpress/paypal/subscriptions/$sub->subscr_id/pause");

        $sub->status = MeprSubscription::$suspended_str;
        $sub->store();

        MeprUtils::send_suspended_sub_notices($sub);
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * Resume subscription
     *
     * @param  integer $subscription_id Subscription ID.
     * @throws MeprGatewayException If the subscription cannot be resumed.
     * @throws MeprHttpException If there was an HTTP error with the request.
     * @throws MeprRemoteException If the API request failed.
     */
    public function process_resume_subscription($subscription_id)
    {
        $sub = new MeprSubscription($subscription_id);

        if (!($sub->id > 0)) {
            throw new MeprGatewayException(__('Subscription not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ($sub->status === MeprSubscription::$active_str) {
            throw new MeprGatewayException(__('This subscription has already been resumed.', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $this->api_request('POST', "api/v1/memberpress/paypal/subscriptions/$sub->subscr_id/resume");

        $sub->status = MeprSubscription::$active_str;
        $sub->store();

        MeprUtils::send_resumed_sub_notices($sub);
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * Cancel subscription
     *
     * @param  integer $subscription_id Subscription ID.
     * @throws MeprGatewayException If the subscription was not found or already cancelled.
     * @throws MeprHttpException If there was an HTTP error with the request.
     * @throws MeprRemoteException If the API request failed.
     */
    public function process_cancel_subscription($subscription_id)
    {
        $sub = new MeprSubscription($subscription_id);

        if (!($sub->id > 0)) {
            throw new MeprGatewayException(__('Subscription not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ($sub->status === MeprSubscription::$cancelled_str) {
            throw new MeprGatewayException(__('This subscription has already been cancelled.', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ($sub->status === MeprSubscription::$suspended_str) {
            throw new MeprGatewayException(
                __('This subscription is paused. Please resume it before cancelling.', 'memberpress') // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        // If called from limit_payment_cycles, let it expire naturally in the Payments Service.
        if (!isset($_REQUEST['expire'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $this->api_request('POST', "api/v1/memberpress/paypal/subscriptions/$sub->subscr_id/cancel");
        }

        $this->record_cancel_sub($sub);
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    /**
     * Record a subscription cancellation and send subscription cancelled email notifications.
     *
     * @param MeprSubscription $sub The subscription.
     *
     * @return void
     */
    protected function record_cancel_sub(MeprSubscription $sub): void
    {
        if ($sub->status === MeprSubscription::$cancelled_str || $sub->status === MeprSubscription::$suspended_str) {
            return;
        }

        $sub->status = MeprSubscription::$cancelled_str;
        $sub->store();

        if (isset($_REQUEST['expire'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $sub->limit_reached_actions();
        }

        if (!isset($_REQUEST['silent']) || !sanitize_text_field(wp_unslash($_REQUEST['silent']))) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            MeprUtils::send_cancelled_sub_notices($sub);
        }
    }

    /**
     * Enqueue payment form scripts
     */
    public function enqueue_payment_form_scripts()
    {
        // Ensure that the scripts are only enqueued once.
        if (wp_script_is('mepr-paypal-vaulting')) {
            return;
        }

        // Enqueue PayPal JS SDK loader.
        wp_enqueue_script(
            'paypal-js-loader',
            MEPR_GATEWAYS_URL . '/paypal-vaulting/paypal-js.min.js',
            [],
            '9.0.1',
            true
        );

        // Enqueue custom PayPal Vaulting integration script.
        wp_enqueue_script(
            'mepr-paypal-vaulting',
            MEPR_GATEWAYS_URL . '/paypal-vaulting/paypal-vaulting.js',
            ['jquery', 'paypal-js-loader'],
            MEPR_VERSION,
            true
        );

        $options = MeprOptions::fetch();

        // Build the userinfo array for logged-in users.
        $userinfo = [];
        $user     = MeprUtils::get_currentuserinfo();

        if ($user instanceof MeprUser && $options->show_address_fields) {
            $keys = [
                'mepr-address-one',
                'mepr-address-two',
                'mepr-address-city',
                'mepr-address-country',
                'mepr-address-state',
                'mepr-address-zip',
            ];

            foreach ($keys as $key) {
                $value = get_user_meta($user->ID, $key, true);

                if (is_string($value)) {
                    $userinfo[$key] = $value;
                }
            }
        }

        // Prepare localization data.
        $l10n = [
            'ajax_url'                 => admin_url('admin-ajax.php'),
            'userinfo'                 => $userinfo,
            'currency_code'            => strtoupper($options->currency_code),
            'request_failed'           => __('Request failed', 'memberpress'),
            'invalid_response'         => __('Invalid response', 'memberpress'),
            'card_field_style'         => $this->get_card_field_style(),
            'card_fields_invalid'      => __('Please fill in all card fields correctly.', 'memberpress'),
            'card_payment_failed'      => __('Card payment failed. Please try again.', 'memberpress'),
            'use_paypal_buttons'       => __('Please use one of the buttons above to pay via PayPal.', 'memberpress'),
            'apple_pay_total_label'    => __('Total', 'memberpress'),
            'google_pay_merchant_name' => MeprUtils::business_name('google_pay'),
            'button_height'            => (int) MeprHooks::apply_filters('mepr_paypal_vaulting_button_height', 45),
            'top_error'                => sprintf(
                // Translators: %1$s: open strong tag, %2$s: close strong tag, %3$s: error message.
                esc_html__('%1$sERROR%2$s: %3$s', 'memberpress'),
                '<strong>',
                '</strong>',
                '%s'
            ),
        ];

        // Localize script with configuration.
        wp_localize_script(
            'mepr-paypal-vaulting',
            'MeprPayPalVaultingGateway',
            ['l10n_print_after' => 'MeprPayPalVaultingGateway = ' . wp_json_encode($l10n)]
        );
    }

    /**
     * Enqueue the scripts for the update subscription payment method page.
     *
     * @return void
     */
    public function enqueue_user_account_scripts()
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'update') {
            return;
        }

        $sub = new MeprSubscription((int) ($_GET['sub'] ?? 0));

        if ($sub->id > 0 && $sub->gateway === $this->id) {
            $this->enqueue_payment_form_scripts();
        }
    }

    /**
     * Display payment form fields
     *
     * @param float   $amount         Payment amount.
     * @param array   $user           User data.
     * @param integer $product_id     Product ID.
     * @param integer $transaction_id Transaction ID.
     */
    public function display_payment_form($amount, $user, $product_id, $transaction_id)
    {
        $txn         = new MeprTransaction($transaction_id);
        $prd         = new MeprProduct((int) $txn->product_id);
        $order_bumps = $this->get_order_bumps($prd->ID);

        if (!$prd->is_one_time_payment()) {
            $sub = $txn->subscription();
        }

        if (count($order_bumps)) {
            echo MeprTransactionsHelper::get_invoice_order_bumps($txn, '', $order_bumps); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo MeprTransactionsHelper::get_invoice($txn); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        try {
            $cpn                    = $txn->coupon();
            $coupon_code            = $cpn instanceof MeprCoupon ? $cpn->post_title : '';
            $order_bump_product_ids = isset($_GET['obs']) && is_array($_GET['obs']) ? $_GET['obs'] : []; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $order_bump_product_ids = array_map('intval', $order_bump_product_ids);
            $order_bump_products    = MeprCheckoutCtrl::get_order_bump_products($prd->ID, $order_bump_product_ids);

            $sdk_options = $this->get_sdk_options(
                $prd,
                $txn,
                isset($sub) && $sub instanceof MeprSubscription ? $sub : null,
                $coupon_code,
                $order_bump_products
            );
        } catch (Exception $e) {
            MeprUtils::debug_log('[PayPal Vaulting] Exception during get_sdk_options: ' . $e->getMessage());

            printf(
                '<p>%s</p>',
                esc_html__('Payment method unavailable.', 'memberpress')
            );
            return;
        }
        ?>
        <div class="mp_wrapper mp_payment_form_wrapper">
            <form method="post" class="mepr-paypal-vaulting-payment-form" data-type="payment">
                <input type="hidden" name="mepr_process_payment_form" value="Y" />
                <input type="hidden" name="mepr_transaction_id" value="<?php echo esc_attr($txn->id); ?>" />
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $this->get_elements_html($sdk_options);
                ?>
                <?php MeprHooks::do_action('mepr_render_order_bump_hidden_fields'); ?>
                <div class="mepr_spacer">&nbsp;</div>
                <input
                    type="submit"
                    class="mepr-submit"
                    value="<?php echo esc_attr(_x('Submit', 'ui', 'memberpress')); ?>"
                />
                <img
                    src="<?php echo esc_url(admin_url('images/loading.gif')); ?>"
                    alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>"
                    style="display: none;"
                    class="mepr-loading-gif"
                />
                <div class="mepr-form-has-errors" style="display: none;"></div>
                <noscript>
                    <p class="mepr_nojs">
                        <?php
                        esc_html_e(
                            'JavaScript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', // phpcs:ignore Generic.Files.LineLength.TooLong
                            'memberpress'
                        );
                        ?>
                    </p>
                </noscript>
            </form>
        </div>
        <?php
    }

    /**
     * Validate payment form submission
     *
     * @param  array $errors Validation errors.
     * @return array Updated errors.
     */
    public function validate_payment_form($errors)
    {
        return $errors;
    }

    /**
     * Display payment fields for Simple Checkout
     *
     * @param  MeprProduct|null $product Product object.
     * @return string Payment field HTML.
     *
     * phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing
     */
    public function spc_payment_fields($product = null)
    {
        try {
            if (!$product instanceof MeprProduct) {
                throw new Exception(__('Product not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            $user        = MeprUtils::is_user_logged_in() ? MeprUtils::get_currentuserinfo() : null;
            $coupon_code = isset($_GET['coupon']) ? sanitize_text_field(wp_unslash($_GET['coupon'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $cpn         = MeprCoupon::get_one_from_code($coupon_code);
            $coupon_code = $cpn instanceof MeprCoupon ? $cpn->post_title : '';

            list($txn, $sub) = MeprCheckoutCtrl::prepare_transaction(
                $product,
                0,
                $user instanceof MeprUser ? $user->ID : 0,
                $this->id,
                $cpn,
                false
            );

            $sdk_options = $this->get_sdk_options($product, $txn, $sub, $coupon_code);
        } catch (Exception $e) {
            MeprUtils::debug_log('[PayPal Vaulting] Exception during get_sdk_options: ' . $e->getMessage());

            return sprintf(
                '<p>%s</p>',
                esc_html__('Payment method unavailable.', 'memberpress')
            );
        }

        return $this->get_elements_html($sdk_options);
    }

    /**
     * Get PayPal SDK options including vault and intent parameters.
     *
     * @param  MeprProduct           $prd                 The product.
     * @param  MeprTransaction       $txn                 The transaction.
     * @param  MeprSubscription|null $sub                 The subscription (null for one-time payments).
     * @param  string                $coupon_code         The coupon code.
     * @param  array                 $order_bump_products Array of order bump products.
     * @return array Dynamic SDK options.
     * @throws Exception If the subscription was not found.
     */
    public function get_sdk_options(
        MeprProduct $prd,
        MeprTransaction $txn,
        ?MeprSubscription $sub,
        string $coupon_code = '',
        array $order_bump_products = []
    ): array {
        // Initialize vault requirement.
        $vault = null;

        // Calculate the initial amount based on the product type.
        if ($prd->is_one_time_payment() || !$prd->is_payment_required($coupon_code)) {
            $amount = $prd->is_payment_required($coupon_code) ? (float) $txn->total : 0.00;
        } else {
            if (!isset($sub) || !($sub instanceof MeprSubscription)) {
                throw new Exception(__('Subscription not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            $amount = (float) ($sub->trial && $sub->trial_days > 0 ? $sub->trial_total : $sub->total);
            $vault  = true;
        }

        // Add order bump amounts and check for recurring bumps.
        $has_subscription_order_bump = false;

        foreach ($order_bump_products as $product) {
            list($transaction, $subscription) = MeprCheckoutCtrl::prepare_transaction(
                $product,
                0,
                get_current_user_id(),
                $this->id,
                false,
                false
            );

            if ($product->is_one_time_payment()) {
                $amount += (float) $transaction->total;
            } else {
                if (!($subscription instanceof MeprSubscription)) {
                    throw new Exception(__('Subscription not found', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }

                if ($subscription->trial && $subscription->trial_days > 0) {
                    $amount += (float) $subscription->trial_total;
                } else {
                    $amount += (float) $subscription->total;
                }

                $has_subscription_order_bump = true;
                $vault = true;
            }
        }

        // Build SDK options array.
        // Format amount as a string with correct decimal places (0 for zero-decimal currencies, 2 otherwise).
        $options = ['amount' => number_format($amount, MeprUtils::is_zero_decimal_currency() ? 0 : 2, '.', '')];

        if ($amount > 0.00) {
            $options['intent'] = 'capture';

            if ($vault === true) {
                $options['vault'] = true;
            }
        }

        // Include the Apple Pay recurring payment request for single-subscription orders.
        // PHP provides the data; JS decides whether to use it based on ApplePaySession version support.
        if ($vault === true && $sub instanceof MeprSubscription && !$has_subscription_order_bump) {
            $options['recurringPaymentRequest'] = MeprApplePayHelper::get_recurring_payment_request(
                $prd,
                $sub,
                number_format((float) $sub->total, MeprUtils::is_zero_decimal_currency() ? 0 : 2, '.', '')
            );
        }

        return $options;
    }

    /**
     * Get the style configuration for PayPal card fields.
     *
     * @return array
     */
    private function get_card_field_style(): array
    {
        $style = [
            'body'                                 => [
                'padding' => 0,
            ],
            'input'                                => [
                'border'   => '1px solid #ced4da',
                'color'    => '#303030',
                'fontSize' => '16px',
                'padding'  => '8px 12px',
            ],
            'input.card-field-number.display-icon' => [
                'paddingLeft' => '66px !important',
            ],
        ];

        return MeprHooks::apply_filters('mepr_paypal_vaulting_card_field_style', $style, $this);
    }

    /**
     * Generates and returns the HTML structure for the PayPal Vaulting payment interface.
     *
     * @param  array $sdk_options Dynamic SDK options.
     * @return string The HTML string for the PayPal Vaulting payment interface.
     */
    protected function get_elements_html(array $sdk_options): string
    {
        $mepr_options = MeprOptions::fetch();

        $enable_funding  = [];
        $disable_funding = [];
        $components      = [];

        // Ensure at least PayPal button is enabled if all methods are disabled.
        $enable_paypal = $this->settings->enable_paypal;
        if (!$enable_paypal && !$this->settings->enable_advanced_cards) {
            $enable_paypal = true;
        }

        // PayPal is the base payment method and cannot be explicitly disabled via SDK.
        // If PayPal is disabled in the admin UI, we disable all other methods as well.
        if ($enable_paypal) {
            $components[] = 'buttons';

            // Other methods can only be enabled if PayPal is enabled.
            if ($this->settings->enable_paylater) {
                $enable_funding[] = 'paylater';

                // Only include messages component if the primary account currency matches the site currency.
                if ($this->should_show_paylater_messages()) {
                    $components[] = 'messages';
                }
            } else {
                $disable_funding[] = 'paylater';
            }

            if ($this->settings->enable_credit) {
                $enable_funding[] = 'credit';
            } else {
                $disable_funding[] = 'credit';
            }

            if ($this->settings->enable_venmo) {
                $enable_funding[] = 'venmo';
            } else {
                $disable_funding[] = 'venmo';
            }

            if ($this->settings->enable_card && !$this->settings->enable_advanced_cards) {
                $enable_funding[] = 'card';
            } elseif (!$this->settings->enable_card && !$this->settings->enable_advanced_cards) {
                // Only disable card if both card button AND card fields are disabled.
                $disable_funding[] = 'card';
            }
        }

        if ($this->settings->enable_advanced_cards) {
            $components[] = 'card-fields';
        }

        if ($this->settings->enable_apple_pay) {
            $components[] = 'applepay';
        }

        if ($this->settings->enable_google_pay) {
            $components[] = 'googlepay';
        }

        // Build PayPal SDK configuration (static params only).
        $paypal_config = [
            'environment'              => $this->is_test_mode() ? 'sandbox' : 'production',
            'clientId'                 => $this->get_client_id(),
            'currency'                 => strtoupper($mepr_options->currency_code),
            'components'               => implode(',', $components),
            'enableFunding'            => join(',', $enable_funding),
            'disableFunding'           => join(',', $disable_funding),
            'dataPageType'             => 'checkout',
            'dataPartnerAttributionId' => $this->get_bn_code(),
        ];

        // Add merchant ID if available.
        $merchant_id = $this->get_merchant_id();
        if (!empty($merchant_id)) {
            $paypal_config['merchantId'] = $merchant_id;
        }

        // Apply filter to allow programmatic override.
        $paypal_config = MeprHooks::apply_filters(
            'mepr_paypal_vaulting_sdk_config',
            $paypal_config,
            $this
        );

        // Generate namespace from config hash so identical configs share the same namespace and SDK instance.
        $paypal_config['dataNamespace'] = 'pp_' . $this->id . '_' . md5(wp_json_encode($paypal_config));

        ob_start();
        ?>
        <div class="mepr-paypal-vaulting-elements"
            data-payment-method-id="<?php echo esc_attr($this->id); ?>"
            data-paypal-config="<?php echo esc_attr(wp_json_encode($paypal_config)); ?>"
            data-sdk-options="<?php echo esc_attr(wp_json_encode($sdk_options)); ?>"
        >
            <?php if ($enable_paypal) : ?>
                <div class="mepr-paypal-vaulting-buttons"></div>
            <?php endif; ?>
            <?php if ($this->settings->enable_paylater && $this->should_show_paylater_messages()) : ?>
                <div class="mepr-paypal-paylater-message"
                     data-pp-message
                     data-pp-amount="<?php echo esc_attr($sdk_options['amount']); ?>"
                     data-pp-pageType="checkout"
                     data-pp-currency="<?php echo esc_attr(strtoupper($mepr_options->currency_code)); ?>"
                     data-pp-style-layout="text"
                     data-pp-style-text-align="center"
                ></div>
            <?php endif; ?>
            <?php if ($this->settings->enable_advanced_cards) : ?>
                <?php if ($enable_paypal) : ?>
                    <div class="mepr-paypal-vaulting-or">
                        <?php echo esc_html_x('or', 'Separator between payment button options and card input fields', 'memberpress'); // phpcs:ignore Generic.Files.LineLength.TooLong ?>
                    </div>
                <?php endif; ?>
                <div class="mepr-paypal-vaulting-card-fields">
                    <div class="mepr-card-field-number"></div>
                    <div class="mepr-card-fields-row">
                        <div class="mepr-card-field-expiry"></div>
                        <div class="mepr-card-field-cvv"></div>
                    </div>
                </div>
            <?php endif; ?>
            <div role="alert" class="mepr-paypal-vaulting-errors"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Display gateway options form in admin.
     */
    public function display_options_form()
    {
        MeprView::render(
            '/admin/gateways/paypal-vaulting/options',
            [
                'gateway' => $this,
                'options' => MeprOptions::fetch(),
            ]
        );
    }

    /**
     * Validate the gateway options form
     *
     * @param  array $errors Validation errors.
     * @return array Updated errors.
     */
    public function validate_options_form($errors)
    {
        return $errors;
    }

    /**
     * Display the update account form for subscription changes.
     *
     * @param integer $subscription_id Subscription ID.
     * @param array   $errors          Validation errors.
     * @param string  $message         Message to display.
     */
    public function display_update_account_form($subscription_id, $errors = [], $message = '')
    {
        if (empty($message) && isset($_GET['message'])) {
            $message = sanitize_text_field(wp_unslash($_GET['message']));
        }

        $options = MeprOptions::fetch();
        ?>
        <div class="mp-wrapper">
            <form method="post" class="mepr-paypal-vaulting-payment-form" data-type="update_account">
                <input type="hidden" name="mepr_subscription_id" value="<?php echo esc_attr($subscription_id); ?>">
                <?php wp_nonce_field('mepr_process_update_account_form', '_ajax_nonce', false); ?>
                <?php
                if ($options->design_enable_account_template) {
                    printf('<h1>%s</h1>', esc_html__('Update subscription', 'memberpress'));
                }
                ?>
                <?php MeprView::render('/shared/errors', compact('errors', 'message')); ?>
                <div><strong><?php esc_html_e('Update your payment information below', 'memberpress'); ?></strong></div>
                <br/>
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $this->get_elements_html(['amount' => MeprUtils::is_zero_decimal_currency() ? '0' : '0.00']);
                ?>
                <div class="mepr_spacer">&nbsp;</div>
                <input type="submit" class="mepr-submit" value="<?php echo esc_attr(_x('Submit', 'ui', 'memberpress')); ?>" />
                <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />
                <div class="mepr-form-has-errors" style="display: none;"></div>
                <noscript>
                    <p class="mepr_nojs">
                        <?php
                        esc_html_e(
                            'JavaScript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.',
                            'memberpress'
                        );
                        ?>
                    </p>
                </noscript>
            </form>
        </div>
        <?php
    }

    /**
     * Validate the update account form.
     *
     * @param  array $errors Validation errors.
     * @return array Updated errors.
     */
    public function validate_update_account_form($errors = [])
    {
        return $errors;
    }

    /**
     * Process an account update form via Ajax.
     *
     * This creates a setup token when the PayPal button is clicked or Card Fields are submitted.
     * The completion is handled by complete_account_update().
     *
     * @param MeprSubscription $sub The subscription to be updated.
     */
    public function process_update_account_form_ajax(MeprSubscription $sub)
    {
        try {
            // Extract the payment method from the request.
            $payment_method = sanitize_text_field(wp_unslash($_POST['mepr_paypal_payment_method'] ?? ''));

            if (empty($payment_method)) {
                wp_send_json_error(__('Bad request', 'memberpress'));
            }

            // Validate and format return URL.
            $current_url = sanitize_url(wp_unslash($_POST['mepr_current_url'] ?? ''));
            $return_url  = $this->validate_and_format_return_url($current_url, $payment_method);

            // Build request data for account setup token.
            $request_data = [
                'payment_method' => $payment_method,
                'return_url'     => $return_url,
                'cancel_url'     => $return_url, // Use same URL for cancel.
            ];

            // Create a setup token via the API.
            $data = $this->api_request('POST', 'api/v1/memberpress/paypal/account-setup-tokens', $request_data);

            // Return the setup token ID to the JavaScript.
            wp_send_json_success($data['id']);
        } catch (MeprGatewayException $e) {
            // Gateway exceptions have user-facing messages.
            wp_send_json_error($e->getMessage());
        } catch (Exception $e) {
            // Error already logged in api_request() - show generic message to the user.
            wp_send_json_error(__('There was an issue processing your request. Please try again.', 'memberpress'));
        }
    }

    /**
     * Complete an account update after PayPal approval.
     *
     * This confirms the setup token and updates the subscription vault in one call.
     *
     * @param  MeprSubscription $sub            The subscription to be updated.
     * @param  string           $token_id       The setup token ID.
     * @param  string           $payment_method The payment method type (already normalized).
     * @throws MeprHttpException|MeprRemoteException If there was an error with the API request.
     */
    public function complete_account_update(MeprSubscription $sub, string $token_id, string $payment_method)
    {
        // Confirm the account setup token and update the subscription vault.
        // The remote service handles both confirming the token and updating the subscription.
        $response = $this->api_request(
            'POST',
            "api/v1/memberpress/paypal/account-setup-tokens/$token_id/confirm",
            [
                'subscription_id'     => $sub->subscr_id,
                'payment_method_type' => $payment_method,
            ]
        );

        // Extract card details from the response (subscription already updated remotely).
        $card_details = $response['card'] ?? [];

        // Update the local subscription with card details.
        $sub->cc_last4     = $card_details['last4'] ?? '';
        $sub->cc_exp_month = $card_details['exp_month'] ?? '';
        $sub->cc_exp_year  = $card_details['exp_year'] ?? '';
        $sub->store();
    }

    /**
     * Handle webhook notifications from the remote service.
     */
    public function webhook_handler()
    {
        if (!MeprUtils::is_post_request()) {
            return;
        }

        $body = file_get_contents('php://input');

        if (empty($body)) {
            return;
        }

        $payload = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            MeprUtils::debug_log('[PayPal] Invalid JSON payload: ' . json_last_error_msg());
            return;
        }

        if (!is_array($payload) || empty($payload['event'])) {
            MeprUtils::debug_log('[PayPal] Invalid JSON payload');
            return;
        }

        try {
            $secret = get_option('mepr_authenticator_secret_token');

            if (empty($secret)) {
                throw new MeprGatewayException('Missing secret token');
            }

            $signature = MeprUtils::get_http_header('Signature');

            if (empty($signature)) {
                throw new MeprGatewayException('Missing signature header');
            }

            $computed_signature = hash_hmac('sha256', $body, $secret);

            if (!hash_equals($computed_signature, $signature)) {
                throw new MeprGatewayException('Invalid signature');
            }

            // Ignore sandbox webhooks unless the gateway is in test mode.
            $webhook_environment = $payload['data']['environment'] ?? '';

            if ($webhook_environment === 'sandbox' && !$this->is_test_mode()) {
                MeprUtils::debug_log('[PayPal] Ignoring sandbox webhook - gateway is in production mode');
                return;
            }

            switch ($payload['event']) {
                case 'payment.refunded':
                    if (!empty($payload['data']) && is_array($payload['data'])) {
                        $this->handle_payment_refunded_webhook($payload['data']);
                    } else {
                        throw new MeprGatewayException($payload['event'] . ' event has invalid payload');
                    }
                    break;
                case 'subscription.payment_completed':
                    if (!empty($payload['data']) && is_array($payload['data'])) {
                        $this->handle_subscription_payment_completed_webhook($payload['data']);
                    } else {
                        throw new MeprGatewayException($payload['event'] . ' event has invalid payload');
                    }
                    break;
                case 'subscription.payment_failed':
                    if (!empty($payload['data']) && is_array($payload['data'])) {
                        $this->handle_subscription_payment_failed_webhook($payload['data']);
                    } else {
                        throw new MeprGatewayException($payload['event'] . ' event has invalid payload');
                    }
                    break;
                case 'subscription.cancelled':
                    if (!empty($payload['data']) && is_array($payload['data'])) {
                        $this->handle_subscription_cancelled_webhook($payload['data']);
                    } else {
                        throw new MeprGatewayException($payload['event'] . ' event has invalid payload');
                    }
                    break;
            }
        } catch (Exception $e) {
            MeprUtils::debug_log('[PayPal] Error processing webhook: ' . $e->getMessage());
            http_response_code(500);
            wp_die(esc_html($e->getMessage()));
        }
    }

    /**
     * Handle the `payment.refunded` webhook event.
     *
     * @param array<string, mixed> $data The webhook event data.
     *
     * @return void
     */
    protected function handle_payment_refunded_webhook(array $data): void
    {
        if (empty($data['capture_id'])) {
            return;
        }

        $txn = MeprTransaction::get_instance_by_trans_num($data['capture_id']);

        // If transaction not found or this isn't for us, bail.
        if (!$txn instanceof MeprTransaction || $txn->gateway !== $this->id) {
            return;
        }

        // Record the refund using the existing refund method.
        $this->record_transaction_refund($txn);
    }

    /**
     * Handle the `subscription.payment_completed` webhook event.
     *
     * @param array<string, mixed> $data The webhook event data.
     *
     * @return void
     */
    protected function handle_subscription_payment_completed_webhook(array $data): void
    {
        if (empty($data['subscription_id']) || empty($data['capture_id'])) {
            return;
        }

        $sub = MeprSubscription::get_one_by_subscr_id($data['subscription_id']);

        // If subscription not found or this isn't for us, bail.
        if (!$sub instanceof MeprSubscription || $sub->gateway !== $this->id) {
            return;
        }

        $total = isset($data['total'])
            ? $this->from_base_currency_unit($data['total'])
            : (float) $sub->total;

        $this->record_sub_payment(
            $sub,
            $total,
            $data['capture_id']
        );
    }

    /**
     * Handle the `subscription.cancelled` webhook event.
     *
     * @param array<string, mixed> $data The webhook event data.
     *
     * @return void
     */
    protected function handle_subscription_cancelled_webhook(array $data): void
    {
        if (empty($data['subscription_id'])) {
            return;
        }

        $sub = MeprSubscription::get_one_by_subscr_id($data['subscription_id']);

        // If subscription not found or this isn't for us, bail.
        if (!$sub instanceof MeprSubscription || $sub->gateway !== $this->id) {
            return;
        }

        $this->record_cancel_sub($sub);
    }

    /**
     * Handle the `subscription.payment_failed` webhook event.
     *
     * @param array<string, mixed> $data The webhook event data.
     *
     * @return void
     */
    protected function handle_subscription_payment_failed_webhook(array $data): void
    {
        if (empty($data['subscription_id']) || empty($data['reference_id'])) {
            return;
        }

        $sub = MeprSubscription::get_one_by_subscr_id($data['subscription_id']);

        // If subscription not found or this isn't for us, bail.
        if (!$sub instanceof MeprSubscription || $sub->gateway !== $this->id) {
            return;
        }

        // Try to look up an existing transaction first (prevents duplicates on webhook retries).
        $txn = MeprTransaction::get_instance_by_trans_num($data['reference_id']);

        if ($txn instanceof MeprTransaction) {
            // Transaction exists - just mark it as failed (webhook retry or duplicate).
            if ($txn->gateway === $this->id && (int) $txn->subscription_id === (int) $sub->id) {
                $txn->status = MeprTransaction::$failed_str;
                $txn->store();
            }
        } else {
            // Transaction doesn't exist - create a new failed transaction record.
            $txn                  = new MeprTransaction();
            $txn->user_id         = $sub->user_id;
            $txn->product_id      = $sub->product_id;
            $txn->subscription_id = $sub->id;
            $txn->gateway         = $this->id;
            $txn->trans_num       = $data['reference_id'];
            $txn->status          = MeprTransaction::$failed_str;
            $txn->created_at      = gmdate('Y-m-d H:i:s');

            // Use set_gross() to automatically calculate tax from the total.
            $total = isset($data['total'])
                ? $this->from_base_currency_unit($data['total'])
                : (float) $sub->total;
            $txn->set_gross($total);

            $txn->store();

            // Reload the subscription in case it was modified while storing the transaction.
            $sub = new MeprSubscription($sub->id);

            // Expire all transactions associated with this subscription.
            $sub->expire_txns();

            // Keep the subscription status unchanged (stays active for retries).
            // The subscription.cancelled webhook will handle final cancellation.
            $sub->store();

            // Send failure notifications to user and admin.
            MeprUtils::send_failed_txn_notices($txn);
        }
    }

    /**
     * Check if the current currency is zero-decimal for PayPal.
     *
     * PayPal treats TWD as zero-decimal, in addition to the base currencies.
     *
     * @return boolean True if zero-decimal currency.
     */
    public static function is_zero_decimal_currency(): bool
    {
        $mepr_options = MeprOptions::fetch();

        // PayPal additionally treats TWD as zero-decimal (per PayPal docs).
        if ($mepr_options->currency_code === 'TWD') {
            return true;
        }

        return MeprUtils::is_zero_decimal_currency();
    }

    /**
     * Convert an amount to base currency units (integer format).
     *
     * For normal currencies (USD, EUR): multiply by 100 (cents).
     * For zero-decimal currencies (JPY, TWD): keep as a whole number.
     *
     * @param  float $amount The amount in standard decimal format.
     * @return integer The amount in base currency units.
     */
    protected function to_base_currency_unit(float $amount): int
    {
        if (self::is_zero_decimal_currency()) {
            return (int) MeprUtils::format_float($amount, 0);
        }

        return (int) MeprUtils::format_float(($amount * 100), 0);
    }

    /**
     * Convert an amount from base currency units to decimal format.
     *
     * For normal currencies (USD, EUR): divide by 100.
     * For zero-decimal currencies (JPY, TWD): keep as-is.
     *
     * @param  integer $amount The amount in base currency units.
     * @return float The amount in decimal format.
     */
    protected function from_base_currency_unit(int $amount): float
    {
        if (self::is_zero_decimal_currency()) {
            return (float) $amount;
        }

        return (float) $amount / 100;
    }

    /**
     * Handle webhook notifications from the remote service.
     *
     * Reserved for potential future use if direct service-to-service webhook communication is needed.
     */
    public function service_webhook_handler()
    {
        // Currently not implemented - PayPal webhooks are handled via webhook_handler().
    }

    /**
     * Methods required by parent class but not used in this implementation.
     */

    /**
     * Process one-time payment
     *
     * @param MeprTransaction $transaction Transaction object.
     */
    public function process_payment($transaction)
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Record payment from webhook
     */
    public function record_payment()
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Process trial payment
     *
     * @param MeprTransaction $transaction Transaction object.
     */
    public function process_trial_payment($transaction)
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Record trial payment from webhook
     *
     * @param MeprTransaction $transaction Transaction object.
     */
    public function record_trial_payment($transaction)
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Create subscription
     *
     * @param MeprTransaction $transaction Transaction object.
     */
    public function process_create_subscription($transaction)
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Record subscription creation from webhook
     */
    public function record_create_subscription()
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Update subscription
     *
     * @param integer $subscription_id Subscription ID.
     */
    public function process_update_subscription($subscription_id)
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Record subscription update from webhook
     */
    public function record_update_subscription()
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Record subscription suspension from webhook
     */
    public function record_suspend_subscription()
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Record subscription resumption from webhook
     */
    public function record_resume_subscription()
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Record subscription cancellation from webhook
     */
    public function record_cancel_subscription()
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Record recurring subscription payment from webhook
     */
    public function record_subscription_payment()
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Record payment failure from webhook
     */
    public function record_payment_failure()
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Process signup form submission
     *
     * @param MeprTransaction $txn Transaction object.
     */
    public function process_signup_form($txn)
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Display the payment page
     *
     * @param MeprTransaction $txn Transaction object.
     */
    public function display_payment_page($txn)
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * Process update account form submission
     *
     * @param integer $subscription_id Subscription ID.
     */
    public function process_update_account_form($subscription_id)
    {
        // Required by the parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     */
    public function force_ssl(): bool
    {
        // Required by the parent class, not used in this implementation.
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function record_refund()
    {
        // Required by the parent class, not used in this implementation.
    }
}
