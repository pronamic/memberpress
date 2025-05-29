<?php

declare(strict_types=1);

use MemberPress\Lcobucci\JWT\Encoding\ChainedFormatter;
use MemberPress\Lcobucci\JWT\Encoding\JoseEncoder;
use MemberPress\Lcobucci\JWT\Signer\Hmac\Sha256;
use MemberPress\Lcobucci\JWT\Signer\Key\InMemory;
use MemberPress\Lcobucci\JWT\Token\Builder;

/**
 * Handles the integration with the Square payment gateway.
 *
 * This class is responsible for managing payment processing, subscriptions, refund operations,
 * and additional capabilities supported by the Square payment gateway. It provides methods
 * to handle various events and settings related to Square payments within the application.
 */
class MeprSquareGateway extends MeprBaseRealAjaxGateway
{
    const API_VERSION = '2025-04-16';

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->key  = 'square';
        $this->name = 'Square';
        $this->icon = MEPR_IMAGES_URL . '/checkout/cards.png';
        $this->desc = __('Pay with Square', 'memberpress');
        $this->set_defaults();
        $this->has_spc_form = true;

        $this->capabilities = [
            'process-credit-cards',
            'process-payments',
            'process-refunds',
            'create-subscriptions',
            'cancel-subscriptions',
            'update-subscriptions',
            'suspend-subscriptions',
            'resume-subscriptions',
            'send-cc-expirations',
            'subscription-trial-payment',
            'order-bumps',
            'multiple-subscriptions',
        ];

        // Set up the notification actions for this gateway.
        $this->notifiers = [
            'whk'     => 'webhook',
            'service' => 'service_listener',
        ];
    }

    /**
     * Load the given settings.
     *
     * @param object|array $settings The settings to load.
     */
    public function load($settings)
    {
        $this->settings = (object) $settings;
        $this->set_defaults();
    }

    /**
     * Set the default settings.
     */
    protected function set_defaults()
    {
        if (!isset($this->settings)) {
            $this->settings = [];
        }

        $this->settings = (object) array_merge(
            [
                'gateway'                 => 'MeprSquareGateway',
                'id'                      => $this->generate_id(),
                'label'                   => '',
                'use_label'               => true,
                'use_icon'                => true,
                'use_desc'                => true,
                'sandbox'                 => false,
                'production_connected'    => false,
                'production_merchant_id'  => '',
                'production_location_id'  => '',
                'production_access_token' => '',
                'production_expires_at'   => '',
                'production_currency'     => '',
                'production_country'      => '',
                'sandbox_connected'       => false,
                'sandbox_merchant_id'     => '',
                'sandbox_location_id'     => '',
                'sandbox_access_token'    => '',
                'sandbox_expires_at'      => '',
                'sandbox_currency'        => '',
                'sandbox_country'         => '',
                'saved'                   => false,
            ],
            (array) $this->settings
        );

        $this->id        = $this->settings->id;
        $this->label     = $this->settings->label;
        $this->use_label = $this->settings->use_label;
        $this->use_icon  = $this->settings->use_icon;
        $this->use_desc  = $this->settings->use_desc;
    }

    /**
     * Enqueues the necessary scripts for the Square payment form.
     *
     * This method enqueues JavaScript files required for the Square payment gateway
     * functionality and localizes the script with required data.
     *
     * @return void
     */
    public function enqueue_payment_form_scripts()
    {
        // Ensure that the scripts are only enqueued once.
        if (wp_script_is('mepr-square')) {
            return;
        }

        if ($this->is_test_mode()) {
            $src = 'https://sandbox.web.squarecdn.com/v1/square.js';
        } else {
            $src = 'https://web.squarecdn.com/v1/square.js';
        }

        wp_enqueue_script('square-js', $src, [], MEPR_VERSION);
        wp_enqueue_script('mepr-square', MEPR_GATEWAYS_URL . '/square/square.js', ['square-js', 'mepr-checkout-js', 'jquery.payment'], MEPR_VERSION);

        $userinfo = [];
        $user     = MeprUtils::get_currentuserinfo();

        if ($user instanceof MeprUser) {
            $userinfo = [
                'user_email'      => $user->user_email,
                'user_first_name' => $user->first_name,
                'user_last_name'  => $user->last_name,
            ];

            $options = MeprOptions::fetch();

            if ($options->show_address_fields) {
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
        }

        $l10n = [
            'ajax_url'       => admin_url('admin-ajax.php'),
            'userinfo'       => $userinfo,
            'request_failed' => __('Request failed', 'memberpress'),
            'top_error'      => sprintf(
                // Translators: %1$s: open strong tag, %2$s: close strong tag, %3$s: error message.
                esc_html__('%1$sERROR%2$s: %3$s', 'memberpress'),
                '<strong>',
                '</strong>',
                '%s'
            ),
        ];

        wp_localize_script(
            'mepr-square',
            'MeprSquareGateway',
            ['l10n_print_after' => 'MeprSquareGateway = ' . wp_json_encode($l10n)]
        );
    }

    /**
     * Displays the payment form (only shown in the Two-Page Checkout).
     *
     * @param float    $amount         The amount to be processed.
     * @param MeprUser $user           The user associated with the transaction.
     * @param integer  $product_id     The ID of the product associated with the transaction.
     * @param integer  $transaction_id The ID of the transaction being processed.
     *
     * @return void
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
            echo MeprTransactionsHelper::get_invoice_order_bumps($txn, '', $order_bumps);
        } else {
            echo MeprTransactionsHelper::get_invoice($txn);
        }

        try {
            $cpn                    = $txn->coupon();
            $coupon_code            = $cpn instanceof MeprCoupon ? $cpn->post_title : '';
            $order_bump_product_ids = isset($_GET['obs']) && is_array($_GET['obs']) ? array_map('intval', $_GET['obs']) : [];
            $order_bump_products    = MeprCheckoutCtrl::get_order_bump_products($prd->ID, $order_bump_product_ids);

            $verification_details = $this->get_verification_details(
                $prd,
                $txn,
                isset($sub) && $sub instanceof MeprSubscription ? $sub : null,
                $coupon_code,
                $order_bump_products
            );
        } catch (Exception $e) {
            MeprUtils::debug_log('[Square] Exception during get_verification_details: ' . $e->getMessage());

            printf(
                '<p>%s</p>',
                esc_html__('Payment method unavailable.', 'memberpress')
            );
            return;
        }
        ?>
        <div class="mp_wrapper mp_payment_form_wrapper">
            <form method="post" class="mepr-square-payment-form" data-type="payment">
                <input type="hidden" name="mepr_process_payment_form" value="Y" />
                <input type="hidden" name="mepr_transaction_id" value="<?php echo esc_attr($txn->id); ?>" />
                <?php echo $this->get_elements_html($verification_details); ?>
                <?php MeprHooks::do_action('mepr_render_order_bump_hidden_fields'); ?>
                <div class="mepr_spacer">&nbsp;</div>
                <input type="submit" class="mepr-submit" value="<?php echo esc_attr(_x('Submit', 'ui', 'memberpress')); ?>" />
                <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />
                <noscript><p class="mepr_nojs"><?php esc_html_e('JavaScript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
            </form>
        </div>
        <?php
    }

    /**
     * Generates and returns the HTML structure for the Square Elements payment interface.
     *
     * @param array $verification_details An associative array containing the verification details required
     *                                     for the payment element settings. This data is encoded into JSON
     *                                     and passed as a data attribute.
     *
     * @return string The HTML string for the Square Elements payment interface.
     */
    protected function get_elements_html(array $verification_details): string
    {
        ob_start();
        ?>
        <div class="mepr-square-elements">
            <div
                class="mepr-square-card-element"
                data-payment-method-id="<?php echo esc_attr($this->id); ?>"
                data-application-id="<?php echo esc_attr($this->get_app_id()); ?>"
                data-location-id="<?php echo esc_attr($this->get_location_id()); ?>"
                data-verification-details="<?php echo esc_attr(wp_json_encode($verification_details)); ?>"
            ></div>
            <div role="alert" class="mepr-square-errors"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get the verification details for Square, depending on the product type.
     *
     * @param MeprProduct           $prd                 The product.
     * @param MeprTransaction       $txn                 The transaction.
     * @param MeprSubscription|null $sub                 The subscription (null for one-time payments).
     * @param string                $coupon_code         The coupon code (empty string for no coupon).
     * @param MeprProduct[]         $order_bump_products The array of order bump products.
     *
     * @return array The verification details array.
     *
     * @throws Exception If the subscription was not found.
     */
    public function get_verification_details(MeprProduct $prd, MeprTransaction $txn, MeprSubscription $sub = null, string $coupon_code = '', array $order_bump_products = []): array
    {
        $options = MeprOptions::fetch();

        if ($prd->is_one_time_payment() || !$prd->is_payment_required($coupon_code)) {
            $amount           = $prd->is_payment_required($coupon_code) ? (float) $txn->total : 0.00;
            $has_subscription = false;

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
                        throw new Exception(__('Subscription not found', 'memberpress'));
                    }

                    $amount          += (float) ($subscription->trial && $subscription->trial_days > 0 ? $subscription->trial_total : $subscription->total);
                    $has_subscription = true;
                }
            }

            if ($amount > 0.00) {
                $store_payment_method = MeprHooks::apply_filters('mepr_square_store_payment_method', $has_subscription, $txn, $prd);

                $options = [
                    'amount'       => (string) $amount,
                    'intent'       => $store_payment_method ? 'CHARGE_AND_STORE' : 'CHARGE',
                    'currencyCode' => $options->currency_code,
                ];
            } else {
                $options = [
                    'intent' => 'STORE',
                ];
            }
        } else {
            if (!isset($sub) || !($sub instanceof MeprSubscription)) {
                throw new Exception(__('Subscription not found', 'memberpress'));
            }

            $amount = (float) ($sub->trial && $sub->trial_days > 0 ? $sub->trial_total : $sub->total);

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
                    if ((float) $transaction->total > 0) {
                        $amount += (float) $transaction->total;
                    }
                } else {
                    if (!($subscription instanceof MeprSubscription)) {
                        throw new Exception(__('Subscription not found', 'memberpress'));
                    }

                    $amount += (float) ($subscription->trial && $subscription->trial_days > 0 ? $subscription->trial_total : $subscription->total);
                }
            }

            if ($sub->trial && $sub->trial_days > 0 && (float) $sub->trial_amount <= 0.00 && $amount <= 0.00) {
                $options = [
                    'intent' => 'STORE',
                ];
            } else {
                $options = [
                    'amount'       => (string) $amount,
                    'intent'       => 'CHARGE_AND_STORE',
                    'currencyCode' => $options->currency_code,
                ];
            }
        }

        return $options;
    }

    /**
     * It's an error condition if this method is called in this gateway.
     *
     * One condition that causes this method to be called is when JavaScript is disabled in the browser.
     *
     * @param MeprTransaction $txn The transaction to process.
     *
     * @return void
     *
     * @throws MeprGatewayException This exception is always thrown.
     */
    public function process_payment_form($txn)
    {
        throw new MeprGatewayException(__('Payment was unsuccessful, please check your payment details and try again.', 'memberpress'));
    }

    /**
     * Displays the options form for the Square gateway.
     *
     * @return void
     */
    public function display_options_form()
    {
        MeprView::render('/admin/gateways/square/options', [
            'gateway' => $this,
            'options' => MeprOptions::fetch(),
        ]);
    }

    /**
     * Checks if the gateway is operating in test mode.
     *
     * @return boolean True if the sandbox mode is enabled; otherwise, false.
     */
    public function is_test_mode()
    {
        return $this->settings->sandbox;
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing
    /**
     * Renders the payment fields for Square payment gateway (Single Page Checkout and ReadyLaunch™).
     *
     * @param  MeprProduct|null $product The product being purchased.
     * @return string The HTML content for the Square payment fields.
     */
    public function spc_payment_fields($product = null): string
    {
        try {
            if (!$product instanceof MeprProduct) {
                throw new Exception(__('Product not found', 'memberpress'));
            }

            $user        = MeprUtils::is_user_logged_in() ? MeprUtils::get_currentuserinfo() : null;
            $coupon_code = isset($_GET['coupon']) ? sanitize_text_field(wp_unslash($_GET['coupon'])) : '';
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

            $verification_details = $this->get_verification_details($product, $txn, $sub, $coupon_code);
        } catch (Exception $e) {
            MeprUtils::debug_log('[Square] Exception during get_verification_details: ' . $e->getMessage());

            return sprintf(
                '<p>%s</p>',
                esc_html__('Payment method unavailable.', 'memberpress')
            );
        }

        return $this->get_elements_html($verification_details);
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * Makes an API request to the Square API.
     *
     * This function sends HTTP requests to the Square API endpoint with the specified
     * method, endpoint URL, and data payload.
     *
     * @param string      $method                 The HTTP method to use (e.g., 'GET', 'POST', 'PUT', 'DELETE').
     * @param string      $endpoint               The API endpoint to call (relative to the base URL).
     * @param array       $data                   The data to send in the request body (applies to POST and PUT).
     * @param string|null $environment            Optionally override the environment.
     * @param string|null $merchant_id            Optionally override the merchant ID.
     * @param string|null $encrypted_access_token Optionally override the access token.
     *
     * @return array Returns the response data as an associative array.
     *
     * @throws InvalidArgumentException If the access token is empty or an invalid environment given.
     * @throws MeprHttpException If there is an HTTP error (e.g., timeout or unreachable host).
     * @throws MeprRemoteException If the API request failed.
     * @throws SodiumException If there was an error decrypting the access token.
     * @throws UnexpectedValueException If the access token could not be decrypted.
     */
    public function api_request(
        string $method,
        string $endpoint,
        array $data = [],
        ?string $environment = null,
        ?string $merchant_id = null,
        ?string $encrypted_access_token = null
    ): array {
        if (is_null($environment)) {
            $environment = $this->settings->sandbox ? 'sandbox' : 'production';
        } else {
            if (!in_array($environment, ['production', 'sandbox'], true)) {
                throw new InvalidArgumentException(
                    sprintf(
                        // Translators: %s: the given environment value.
                        __('The environment must be either "production" or "sandbox", but "%s" was given.', 'memberpress'),
                        $environment
                    )
                );
            }
        }

        $merchant_id  = $merchant_id ?? $this->settings->{$environment . '_merchant_id'};
        $key = get_option("mepr_square_key_{$merchant_id}_$environment");

        if (!is_string($key) || strlen($key) != 64) {
            throw new UnexpectedValueException('Invalid decryption key');
        }

        $access_token = MeprUtils::decrypt(
            $encrypted_access_token ?? $this->settings->{$environment . '_access_token'},
            sodium_hex2bin($key)
        );

        $args = [
            'method'  => $method,
            'headers' => [
                'Square-Version' => $this->get_api_version(),
                'Authorization'  => sprintf('Bearer %s', $access_token),
                'Content-Type'   => 'application/json',
            ],
        ];

        if (count($data) && in_array($method, ['POST', 'PUT'], true)) {
            $args['body'] = wp_json_encode($data);
        }

        $base_url = $environment == 'sandbox' ? 'https://connect.squareupsandbox.com' : 'https://connect.squareup.com';

        $response = wp_remote_request(
            $base_url . '/' . ltrim($endpoint, '/'),
            $args
        );

        if (is_wp_error($response)) {
            throw new MeprHttpException(
                sprintf(
                    // Translators: %s: gateway name.
                    __('You had an HTTP error connecting to %s', 'memberpress'),
                    $this->name
                )
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code == 200 && is_array($json)) {
            return $json;
        }

        if (isset($json['errors'][0]['code'], $json['errors'][0]['detail'])) {
            throw new MeprRemoteException("{$json['errors'][0]['detail']} ({$json['errors'][0]['code']})");
        } else {
            throw new MeprRemoteException(__('There was an issue with the payment processor. Try again later.', 'memberpress'));
        }
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    /**
     * Get the Square API version.
     *
     * @return string
     */
    protected function get_api_version(): string
    {
        return MeprHooks::apply_filters('mepr_square_api_version', self::API_VERSION);
    }

    /**
     * Retrieves the location ID based on the current environment (sandbox or production).
     *
     * @return string The location ID corresponding to the active environment.
     */
    public function get_location_id(): string
    {
        $setting = ($this->settings->sandbox ? 'sandbox' : 'production') . '_location_id';

        return $this->settings->{$setting};
    }

    /**
     * Get the Square Application ID.
     *
     * @return string
     */
    public function get_app_id(): string
    {
        if ($this->is_test_mode()) {
            $app_id = 'sandbox-sq0idb-HJi-lKyCTEm5POUqtLmhcQ';
        } else {
            $app_id = 'sq0idp-6JJz2bK8Yxi2XId-IZLy0g';
        }

        return MeprHooks::apply_filters('mepr_square_app_id', $app_id, $this);
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
    public function process_payment_ajax(MeprProduct $prd, MeprUser $usr, MeprTransaction $txn, MeprSubscription $sub = null, MeprCoupon $cpn = null)
    {
        try {
            $mepr_options                     = MeprOptions::fetch();
            $coupon_code                      = $cpn instanceof MeprCoupon ? $cpn->post_title : '';
            list($ob_transactions, $ob_total) = $this->process_order_bumps($prd, $usr, $txn, $sub);
            $order                            = $txn->order();
            $source_id                        = sanitize_text_field(wp_unslash($_POST['mepr_square_source_id'] ?? ''));
            $idempotency_key                  = sanitize_text_field(wp_unslash($_POST['mepr_square_idempotency_key'] ?? ''));

            $this->bad_request_if_empty($source_id, 'missing payment source');
            $this->bad_request_if_empty($idempotency_key, 'missing idempotency key');

            $thank_you_page_args = [
                'membership'     => sanitize_title($prd->post_title),
                'membership_id'  => $prd->ID,
                'transaction_id' => $txn->id,
            ];

            $is_payment_required = $prd->is_payment_required($coupon_code);
            $customer_id = $this->get_customer_id($usr);

            if ($prd->is_one_time_payment() || !$is_payment_required) {
                $total  = $is_payment_required ? (float) $txn->total : 0.00;
                $total += $ob_total;

                if ($total > 0.00) {
                    $payment = $this->create_payment(
                        $source_id,
                        $idempotency_key,
                        (float) $total,
                        $prd,
                        $usr,
                        $txn
                    );

                    if ($payment['status'] == 'COMPLETED') {
                        if ($order instanceof MeprOrder && count($ob_transactions)) {
                            $this->record_one_time_payment($txn, sprintf('mi_%d_%s', $order->id, uniqid()));

                            $this->process_order_bump_transactions(
                                $order,
                                $ob_transactions,
                                $this->create_card($idempotency_key, $customer_id, $payment['id'], $usr),
                                $customer_id,
                                $idempotency_key,
                                $payment['id']
                            );
                        } else {
                            $this->record_one_time_payment($txn, $payment['id']);
                        }
                    } else {
                        wp_send_json_error(__('Payment was unsuccessful', 'memberpress'));
                    }
                } else {
                    if ($order instanceof MeprOrder && count($ob_transactions)) {
                        MeprTransaction::create_free_transaction($txn, false, sprintf('mi_%d_%s', $order->id, uniqid()));

                        $this->process_order_bump_transactions(
                            $order,
                            $ob_transactions,
                            $this->create_card($idempotency_key, $customer_id, $source_id, $usr),
                            $customer_id,
                            $idempotency_key
                        );
                    } else {
                        wp_send_json_error(__('Payment was unsuccessful', 'memberpress'));
                    }
                }
            } else {
                if (!$sub instanceof MeprSubscription) {
                    wp_send_json_error(__('Subscription not found', 'memberpress'));
                }

                $thank_you_page_args['subscription_id'] = $sub->id;

                $has_trial        = $sub->trial && $sub->trial_days;
                $trial_total      = $has_trial ? (float) $sub->trial_total : 0.00;
                $convert_to_trial = false;

                if ($order instanceof MeprOrder && count($ob_transactions)) {
                    $convert_to_trial = true;
                    $trial_total += $ob_total;
                }

                if (($has_trial || $convert_to_trial) && $trial_total > 0.00) {
                    $payment = $this->create_payment(
                        $source_id,
                        $idempotency_key,
                        $trial_total,
                        $prd,
                        $usr,
                        $txn
                    );

                    if ($payment['status'] != 'COMPLETED') {
                        wp_send_json_error(__('Payment was unsuccessful', 'memberpress'));
                    }

                    $card = $this->create_card($idempotency_key, $customer_id, $payment['id'], $usr);
                } else {
                    $card = $this->create_card($idempotency_key, $customer_id, $source_id, $usr);
                }

                $trial_days = 0;
                $txn_expires_at = null;

                if ($has_trial) {
                    $trial_days = (int) $sub->trial_days;
                    $txn_expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
                } else if ($convert_to_trial) {
                    // When there is an order bump, we want to create a subscription with the trial days set to cover one period
                    // since the initial period was already paid for.
                    $now        = new DateTimeImmutable('now');
                    $end        = $now->modify(sprintf('+%d %s', $sub->period, $sub->period_type));
                    $trial_days = (int) $end->diff($now)->format('%a');
                }

                $subscription = $this->create_subscription(
                    $idempotency_key,
                    $customer_id,
                    $card['id'],
                    $prd,
                    $sub,
                    $trial_days
                );

                $sub->subscr_id    = $subscription['id'];
                $sub->cc_last4     = !empty($card['last_4']) ? $card['last_4'] : '';
                $sub->cc_exp_month = !empty($card['exp_month']) ? $card['exp_month'] : '';
                $sub->cc_exp_year  = !empty($card['exp_year']) ? $card['exp_year'] : '';

                $this->record_create_sub($sub);

                if ($order instanceof MeprOrder && count($ob_transactions)) {
                    $amount = $has_trial ? (float) $sub->trial_total : (float) $sub->total;

                    if ($amount > 0) {
                        $this->record_sub_payment(
                            $sub,
                            $amount,
                            sprintf('mi_%d_%s', $order->id, uniqid()),
                            $card,
                            $txn_expires_at
                        );
                    }

                    $this->process_order_bump_transactions(
                        $order,
                        $ob_transactions,
                        $card,
                        $customer_id,
                        $idempotency_key,
                        isset($payment) ? $payment['id'] : null,
                    );
                } elseif (isset($payment)) {
                    $this->record_sub_payment(
                        $sub,
                        (float) $sub->trial_total,
                        $payment['id'],
                        null,
                        $txn_expires_at
                    );
                }
            }

            wp_send_json_success($mepr_options->thankyou_page_url($thank_you_page_args));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Create a Square subscription.
     *
     * @param string           $idempotency_key The idempotency key.
     * @param string           $customer_id     The ID of the Square Customer.
     * @param string           $card_id         The ID of the Square Card.
     * @param MeprProduct      $prd             The product.
     * @param MeprSubscription $sub             The subscription.
     * @param integer          $trial_days      The number of trial days.
     *
     * @return array The Square Subscription data.
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprGatewayException If the subscription terms are incompatible.
     * @throws MeprHttpException If there was an HTTP error connecting to Square.
     * @throws MeprRemoteException If there was an invalid or error response from Square.
     */
    public function create_subscription(
        string $idempotency_key,
        string $customer_id,
        string $card_id,
        MeprProduct $prd,
        MeprSubscription $sub,
        int $trial_days = 0
    ): array {
        $data = [
            'idempotency_key'   =>  "$idempotency_key-crs" . substr((string) $prd->ID, -5),
            'location_id'       => $this->get_location_id(),
            'plan_variation_id' => $this->get_plan_variation_id($idempotency_key, $sub, $prd, $trial_days),
            'customer_id'       => $customer_id,
            'card_id'           => $card_id,
        ];

        if (get_option('mepr_calculate_taxes') && $sub->tax_rate > 0 && $sub->tax_amount > 0) {
            $data['tax_percentage'] = (string) $sub->tax_rate;
        }

        $response = $this->api_request(
            'POST',
            '/v2/subscriptions',
            MeprHooks::apply_filters('mepr_square_create_subscription_data', $data, $sub, $prd, $trial_days, $this)
        );

        return $response['subscription'];
    }

    /**
     * Fetch the plan variation ID for a subscription and product based on pricing details.
     *
     * This method generates a unique plan variation ID for a subscription, associated with a specific product,
     * by leveraging Square's catalog API. If the plan variation ID does not exist in the database, it will be
     * created using relevant subscription, product details, and pricing information.
     *
     * @param string           $idempotency_key Unique identifier for preventing duplicate requests.
     * @param MeprSubscription $sub             The subscription for which the plan variation is being created or fetched.
     * @param MeprProduct      $prd             The product associated with the subscription.
     * @param integer          $trial_days      The number of trial days.
     *
     * @return string The plan variation ID.
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprGatewayException If the subscription terms are incompatible.
     * @throws MeprHttpException If there was an HTTP error connecting to Square.
     * @throws MeprRemoteException If there was an invalid or error response from Square.
     */
    protected function get_plan_variation_id(
        string $idempotency_key,
        MeprSubscription $sub,
        MeprProduct $prd,
        int $trial_days = 0
    ): string {
        $options = MeprOptions::fetch();
        $phases  = [];

        if ($trial_days > 0) {
            $phases[] = [
                'cadence' => 'DAILY',
                'ordinal' => 0,
                'periods' => $trial_days,
                'pricing' => [
                    'type'  => 'STATIC',
                    'price' => [
                        'amount'   => 0,
                        'currency' => $options->currency_code,
                    ],
                ],
            ];
        }

        $phase = [
            'cadence' => self::get_cadence((string) $sub->period_type, (int) $sub->period),
            'ordinal' => count($phases),
            'pricing' => [
                'type'  => 'STATIC',
                'price' => [
                    'amount'   => $this->format_amount((float) $sub->price),
                    'currency' => $options->currency_code,
                ],
            ],
        ];

        if ($sub->limit_cycles && $sub->limit_cycles_num > 0) {
            $periods = (int) $sub->limit_cycles_num;

            // Reduce the number of periods by 1 if there is a paid (but not prorated) trial. This intends to mirror the
            // logic in MeprSubscription::limit_payment_cycles.
            if ($sub->trial && $sub->trial_days > 0 && $sub->trial_amount > 0.00 && !$sub->prorated_trial) {
                $periods -= 1;
            }

            if ($periods > 0) {
                $phase['periods'] = $periods;
            }
        }

        $phases[] = $phase;

        $meta_key          = $this->get_meta_key((int) $prd->ID, sprintf('plan_variation_id_%s', md5(serialize($phases))));
        $plan_variation_id = get_post_meta($prd->ID, $meta_key, true);

        if (empty($plan_variation_id)) {
            $data = [
                'idempotency_key' => $idempotency_key . '-crpv',
                'object'          => [
                    'type'                             => 'SUBSCRIPTION_PLAN_VARIATION',
                    'id'                               => '#1',
                    'subscription_plan_variation_data' => [
                        'name'                 => empty($prd->post_title) ? "Product $prd->ID" : $prd->post_title,
                        'phases'               => $phases,
                        'subscription_plan_id' => $this->get_plan_id($prd, $idempotency_key),
                    ],
                ],
            ];

            $response = $this->api_request(
                'POST',
                '/v2/catalog/object',
                MeprHooks::apply_filters('mepr_square_create_plan_variation_data', $data, $sub, $prd, $trial_days, $this)
            );

            $plan_variation_id = $response['catalog_object']['id'];
            update_post_meta($prd->ID, $meta_key, $plan_variation_id);
        }

        return $plan_variation_id;
    }

    /**
     * Get the plan ID for the given product.
     *
     * If no plan ID exists, one will be created.
     *
     * @param MeprProduct $prd             The product.
     * @param string      $idempotency_key The idempotency key.
     *
     * @return string
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    protected function get_plan_id(MeprProduct $prd, string $idempotency_key): string
    {
        $meta_key = $this->get_meta_key((int) $prd->ID, 'plan_id');
        $plan_id  = get_post_meta($prd->ID, $meta_key, true);

        if (empty($plan_id)) {
            $data = [
                'idempotency_key' => $idempotency_key . '-crp',
                'object'          => [
                    'type'                   => 'SUBSCRIPTION_PLAN',
                    'id'                     => '#1',
                    'subscription_plan_data' => [
                        'name' => empty($prd->post_title) ? "Product $prd->ID" : $prd->post_title,
                    ],
                ],
            ];

            $plan = $this->api_request(
                'POST',
                '/v2/catalog/object',
                MeprHooks::apply_filters('mepr_square_create_plan_data', $data, $prd, $this)
            );

            $plan_id = $plan['catalog_object']['id'];
            update_post_meta($prd->ID, $meta_key, $plan_id);
        }

        return $plan_id;
    }

    /**
     * Get the cadence value for the given subscription.
     *
     * @param string  $period_type The period type.
     * @param integer $period      The period.
     *
     * @return string The Square-compatible cadence value.
     *
     * @throws MeprGatewayException If the subscription cadence is incompatible with Square.
     */
    public static function get_cadence(string $period_type, int $period): string
    {
        $cadence = '';

        switch ($period_type) {
            case 'weeks':
                if ($period == 1) {
                    $cadence = 'WEEKLY';
                } elseif ($period == 2) {
                    $cadence = 'EVERY_TWO_WEEKS';
                }
                break;
            case 'months':
                if ($period == 1) {
                    $cadence = 'MONTHLY';
                } elseif ($period == 2) {
                    $cadence = 'EVERY_TWO_MONTHS';
                } elseif ($period == 3) {
                    $cadence = 'QUARTERLY';
                } elseif ($period == 4) {
                    $cadence = 'EVERY_FOUR_MONTHS';
                } elseif ($period == 6) {
                    $cadence = 'EVERY_SIX_MONTHS';
                } elseif ($period == 12) {
                    $cadence = 'ANNUAL';
                } elseif ($period == 24) {
                    $cadence = 'EVERY_TWO_YEARS';
                }
                break;
            case 'years':
                if ($period == 1) {
                    $cadence = 'ANNUAL';
                } elseif ($period == 2) {
                    $cadence = 'EVERY_TWO_YEARS';
                }
                break;
        }

        if ($cadence === '') {
            throw new MeprGatewayException(__('Unsupported subscription cadence', 'memberpress'));
        }

        return $cadence;
    }

    /**
     * Create a card.
     *
     * @param string   $idempotency_key The idempotency key.
     * @param string   $customer_id     The Square customer ID.
     * @param string   $source_id       The Square source ID.
     * @param MeprUser $usr             The user.
     *
     * @return array The card data returned from Square.
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    public function create_card(string $idempotency_key, string $customer_id, string $source_id, MeprUser $usr): array
    {
        $data = [
            'idempotency_key' => $idempotency_key . '-cc',
            'source_id'       => $source_id,
            'card'            => [
                'customer_id'  => $customer_id,
                'reference_id' => "user-id-$usr->ID",
            ],
        ];

        $response = $this->api_request(
            'POST',
            '/v2/cards',
            MeprHooks::apply_filters('mepr_square_create_card_data', $data, $usr, $this)
        );

        return $response['card'];
    }

    /**
     * Create a customer.
     *
     * @param MeprUser $usr The user.
     *
     * @return array The customer data returned from Square.
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    protected function create_customer(MeprUser $usr): array
    {
        $response = $this->api_request(
            'POST',
            '/v2/customers',
            MeprHooks::apply_filters('mepr_square_create_customer_data', $this->get_customer_data($usr), $usr, $this)
        );

        return $response['customer'];
    }

    /**
     * Update a customer.
     *
     * @param string   $customer_id The Square customer ID.
     * @param MeprUser $usr         The user.
     *
     * @return array The customer data returned from Square.
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    protected function update_customer(string $customer_id, MeprUser $usr): array
    {
        $response = $this->api_request(
            'PUT',
            "/v2/customers/$customer_id",
            MeprHooks::apply_filters('mepr_square_update_customer_data', $this->get_customer_data($usr), $usr, $this)
        );

        return $response['customer'];
    }

    /**
     * Get the customer data to set on the Square customer.
     *
     * @param MeprUser $usr The user.
     *
     * @return array
     */
    protected function get_customer_data(MeprUser $usr): array
    {
        $mepr_options = MeprOptions::fetch();
        $data         = [
            'email_address' => $usr->user_email,
            'reference_id'  => "user-id-$usr->ID",
        ];

        if ($usr->first_name) {
            $data['given_name'] = $usr->first_name;
        }

        if ($usr->last_name) {
            $data['family_name'] = $usr->last_name;
        }

        if (MeprHooks::apply_filters('mepr_square_populate_customer_address', $mepr_options->show_address_fields)) {
            $address = [
                'address_line_1'                  => get_user_meta($usr->ID, 'mepr-address-one', true),
                'address_line_2'                  => get_user_meta($usr->ID, 'mepr-address-two', true),
                'locality'                        => get_user_meta($usr->ID, 'mepr-address-city', true),
                'administrative_district_level_1' => get_user_meta($usr->ID, 'mepr-address-state', true),
                'country'                         => get_user_meta($usr->ID, 'mepr-address-country', true),
                'postal_code'                     => get_user_meta($usr->ID, 'mepr-address-zip', true),
            ];

            foreach ($address as $key => $value) {
                if (empty($value) || !is_string($value)) {
                    unset($address[$key]);
                }
            }

            if (!empty($address) && !empty($address['address_line_1'])) {
                $data['address'] = $address;
            }
        }

        return MeprHooks::apply_filters('mepr_square_customer_data', $data, $usr, $this);
    }

    /**
     * Get the Square customer ID for the given user.
     *
     * If the user does not have a customer ID, one will be created.
     *
     * @param MeprUser $usr The user.
     *
     * @return string
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    public function get_customer_id(MeprUser $usr): string
    {
        $meta_key    = $this->get_meta_key($usr->ID, 'customer_id');
        $customer_id = get_user_meta($usr->ID, $meta_key, true);

        if (empty($customer_id)) {
            $customer = $this->create_customer($usr);
            update_user_meta($usr->ID, $meta_key, $customer['id']);
        } else {
            $customer = $this->update_customer($customer_id, $usr);
        }

        return $customer['id'];
    }

    /**
     * Get the meta key for storing Square object IDs.
     *
     * @param integer $object_id The WP object ID (post ID or user ID).
     * @param string  $suffix    The key suffix.
     *
     * @return string
     */
    protected function get_meta_key(int $object_id, string $suffix): string
    {
        return sprintf(
            '_mepr_square%s_%d_%s_%s',
            $this->is_test_mode() ? '_test' : '',
            $object_id,
            $this->settings->{($this->is_test_mode() ? 'sandbox' : 'production') . '_merchant_id'},
            $suffix
        );
    }

    /**
     * Create a payment.
     *
     * @param string          $source_id       The payment source token.
     * @param string          $idempotency_key The idempotency key.
     * @param float           $amount          The amount.
     * @param MeprProduct     $prd             The product.
     * @param MeprUser        $usr             The user.
     * @param MeprTransaction $txn             The transaction.
     *
     * @return array The created payment data.
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    public function create_payment(
        string $source_id,
        string $idempotency_key,
        float $amount,
        MeprProduct $prd,
        MeprUser $usr,
        MeprTransaction $txn
    ): array {
        $options = MeprOptions::fetch();

        $data = [
            'idempotency_key' => $idempotency_key . '-pay',
            'source_id'       => $source_id,
            'amount_money'    => [
                'amount'   => $this->format_amount($amount),
                'currency' => $options->currency_code,
            ],
            'customer_id'     => $this->get_customer_id($usr),
            'location_id'     => $this->get_location_id(),
            'reference_id'    => (string) $txn->id,
            'note'            => $prd->post_title,
        ];

        $response = $this->api_request(
            'POST',
            '/v2/payments',
            MeprHooks::apply_filters('mepr_square_create_payment_data', $data, $txn, $amount, $prd, $usr, $this)
        );

        return $response['payment'];
    }

    /**
     * Format the amount into an integer in the currency's smallest unit.
     *
     * @param float $amount The amount.
     *
     * @return integer
     */
    protected function format_amount(float $amount): int
    {
        return (int) (MeprUtils::is_zero_decimal_currency() ? $amount : $amount * 100);
    }

    /**
     * Send a 'bad request' JSON error if the given value is empty.
     *
     * @param string $value The value to check.
     * @param string $error The error message.
     */
    protected function bad_request_if_empty(string $value, string $error)
    {
        if (empty($value)) {
            wp_send_json_error(
                sprintf(
                    // Translators: %s: the reason for the error.
                    __('Bad request: %s', 'memberpress'),
                    $error
                )
            );
        }
    }

    /**
     * Get the Square Connect service domain.
     *
     * @param string $environment The environment: 'production' or 'sandbox'.
     *
     * @return string
     */
    public static function connect_service_domain(string $environment): string
    {
        if ($environment == 'sandbox') {
            if (defined('MEPR_SQUARE_CONNECT_SANDBOX_DOMAIN')) {
                $domain = MEPR_SQUARE_CONNECT_SANDBOX_DOMAIN;
            } else {
                $domain = 'square-sandbox.caseproof.com';
            }

            return MeprHooks::apply_filters('mepr_square_connect_sandbox_service_domain', $domain);
        } else {
            if (defined('MEPR_SQUARE_CONNECT_DOMAIN')) {
                $domain = MEPR_SQUARE_CONNECT_DOMAIN;
            } else {
                $domain = 'square.caseproof.com';
            }

            return MeprHooks::apply_filters('mepr_square_connect_service_domain', $domain);
        }
    }

    /**
     * Get the Square Connect service URL.
     *
     * @param  string $environment The environment: 'production' or 'sandbox'.
     * @return string
     */
    public static function connect_service_url(string $environment): string
    {
        return set_url_scheme(
            'https://' . self::connect_service_domain($environment),
            MeprHooks::apply_filters('mepr_square_connect_service_https', true) ? 'https' : 'http'
        );
    }

    /**
     * Get the URL to connect to Square.
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
                'mepr_square_action' => 'process_connect_return',
                '_wpnonce'           => wp_create_nonce('mepr_square_process_connect_return'),
                'pmt'                => $this->id,
            ],
            admin_url('admin.php')
        );

        $claims = [
            'payment_method_id'   => $this->id,
            'return_url'          => $return_url,
            'service_webhook_url' => $this->notify_url('service'),
            'user_uuid'           => get_option('mepr_authenticator_user_uuid'),
            'webhook_url'         => $this->notify_url('whk'),
        ];

        $site_uuid = get_option('mepr_authenticator_site_uuid');
        $jwt       = self::generate_jwt($environment, $claims, '+12 hours');

        return self::connect_service_url($environment) . "/memberpress/connect/$site_uuid/$jwt";
    }

    /**
     * Get the URL to connect to Square, via the Authenticator if the site is not yet Authenticator connected.
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
                        'square_connect'           => 'true',
                        'square_payment_method_id' => $this->id,
                        'square_environment'       => $environment,
                    ]
                ),
                admin_url('admin.php?page=memberpress-account-login')
            );

            return MeprAuthenticatorCtrl::get_auth_connect_url(false, false, [], $return_url);
        }
    }

    /**
     * Generates a JSON Web Token (JWT) with the specified claims and expiration modifier.
     *
     * @param string $environment         The environment: 'production' or 'sandbox'.
     * @param array  $claims              An associative array of claims to include in the JWT.
     * @param string $expires_at_modifier A date/time string modifier determining the token's expiration time.
     *
     * @return string The generated JWT.
     *
     * @throws MeprException If the secret token is not configured.
     */
    protected static function generate_jwt(string $environment, array $claims = [], string $expires_at_modifier = '+5 minutes'): string
    {
        $key = get_option('mepr_authenticator_secret_token');

        if (empty($key)) {
            throw new MeprException(__('Invalid secret token', 'memberpress'));
        }

        $builder     = new Builder(new JoseEncoder(), ChainedFormatter::withUnixTimestampDates());
        $algorithm   = new Sha256();
        $signing_key = InMemory::plainText($key);

        $builder
            ->issuedBy(wp_parse_url(get_site_url(), PHP_URL_HOST))
            ->permittedFor(self::connect_service_domain($environment));

        foreach ($claims as $name => $value) {
            $builder->withClaim($name, $value);
        }

        $token = $builder->getToken($algorithm, $signing_key);

        return $token->toString();
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing
    /**
     * Process an incoming webhook.
     */
    public function webhook()
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
            MeprUtils::debug_log('[Square] Invalid JSON payload: ' . json_last_error_msg());
            return;
        }

        if (!is_array($payload) || empty($payload['type'])) {
            MeprUtils::debug_log('[Square] Invalid JSON payload');
            return;
        }

        try {
            $secret = get_option('mepr_authenticator_secret_token');

            if (empty($secret)) {
                throw new MeprGatewayException('Missing secret token');
            }

            $signature = $_SERVER['HTTP_SIGNATURE'] ?? '';

            if (empty($signature)) {
                throw new MeprGatewayException('Missing signature header');
            }

            $computed_signature = hash_hmac('sha256', $body, $secret);

            if (!hash_equals($computed_signature, $signature)) {
                throw new MeprGatewayException('Invalid signature');
            }

            switch ($payload['type']) {
                case 'invoice.payment_made':
                    if (!empty($payload['data']['object']['invoice']) && is_array($payload['data']['object']['invoice'])) {
                        $this->handle_invoice_payment_made_webhook($payload['data']['object']['invoice']);
                    } else {
                        throw new MeprGatewayException($payload['type'] . ' event has invalid payload');
                    }
                    break;
                case 'invoice.updated':
                    if (!empty($payload['data']['object']['invoice']) && is_array($payload['data']['object']['invoice'])) {
                        $this->handle_invoice_updated_webhook($payload['data']['object']['invoice']);
                    } else {
                        throw new MeprGatewayException($payload['type'] . ' event has invalid payload');
                    }
                    break;
                case 'refund.created':
                case 'refund.updated':
                    if (!empty($payload['data']['object']['refund']) && is_array($payload['data']['object']['refund'])) {
                        $this->handle_refund_webhook($payload['data']['object']['refund']);
                    } else {
                        throw new MeprGatewayException($payload['type'] . ' event has invalid payload');
                    }
                    break;
                case 'subscription.updated':
                    if (!empty($payload['data']['object']['subscription']) && is_array($payload['data']['object']['subscription'])) {
                        $this->handle_subscription_updated_webhook($payload['data']['object']['subscription']);
                    } else {
                        throw new MeprGatewayException($payload['type'] . ' event has invalid payload');
                    }
                    break;
            }
        } catch (Exception $e) {
            MeprUtils::debug_log('[Square] Error processing webhook: ' . $e->getMessage());
            http_response_code(500);
            die($e->getMessage());
        }
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing

    /**
     * Handle the `invoice.payment_made` webhook.
     *
     * @param array $invoice The invoice data.
     *
     * @return void
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    protected function handle_invoice_payment_made_webhook(array $invoice): void
    {
        if (empty($invoice['subscription_id']) || empty($invoice['order_id'])) {
            return;
        }

        $sub = MeprSubscription::get_one_by_subscr_id($invoice['subscription_id']);

        // If subscription not found or this isn't for us, bail.
        if (!$sub instanceof MeprSubscription || $sub->gateway != $this->id) {
            return;
        }

        $order = $this->get_order($invoice['order_id']);

        if (isset($order['total_money']['amount'])) {
            $amount = $order['total_money']['amount'];
            $amount = MeprUtils::is_zero_decimal_currency() ? $amount : $amount / 100;
        } else {
            $amount = (float) $sub->total;
        }

        $this->record_sub_payment(
            $sub,
            $amount,
            $order['tenders'][0]['id'] ?? "sq-order-{$invoice['order_id']}"
        );
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * Handle the `invoice.updated` webhook.
     *
     * Currently, this just records a failed subscription payment.
     *
     * @param array $invoice The invoice data.
     *
     * @return void
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException|MeprGatewayException If there was an error with the request.
     */
    protected function handle_invoice_updated_webhook(array $invoice)
    {
        if (empty($invoice['subscription_id']) || empty($invoice['status']) || $invoice['status'] != 'UNPAID') {
            return;
        }

        $sub = MeprSubscription::get_one_by_subscr_id($invoice['subscription_id']);

        if (!$sub instanceof MeprSubscription || $sub->gateway != $this->id) {
            return;
        }

        $order = $this->get_order($invoice['order_id']);

        if (!isset($order['tenders'][0]['id'])) {
            throw new MeprGatewayException('[Square] payment ID not found in invoice.updated');
        }

        $payment = $this->get_payment($order['tenders'][0]['id']);

        if ($payment['status'] == 'FAILED') {
            $txn = MeprTransaction::get_instance_by_trans_num((string) $payment['id']);

            if ($txn instanceof MeprTransaction) {
                if ($txn->gateway == $this->id && $txn->subscription_id == $sub->id) {
                    $txn->status = MeprTransaction::$failed_str;
                    $txn->store();
                }
            } else {
                $first_txn = $sub->first_txn();

                if ($first_txn == false || !($first_txn instanceof MeprTransaction)) {
                    $coupon_id = $sub->coupon_id;
                } else {
                    $coupon_id = $first_txn->coupon_id;
                }

                $txn                  = new MeprTransaction();
                $txn->user_id         = $sub->user_id;
                $txn->product_id      = $sub->product_id;
                $txn->coupon_id       = $coupon_id;
                $txn->txn_type        = MeprTransaction::$payment_str;
                $txn->status          = MeprTransaction::$failed_str;
                $txn->subscription_id = $sub->id;
                $txn->trans_num       = $payment['id'];
                $txn->gateway         = $this->id;

                $total = $payment['total_money']['amount'];
                $total = MeprUtils::is_zero_decimal_currency() ? $total : $total / 100;

                $txn->set_gross((float) $total);
                $txn->store();

                // Reload the subscription in case it was modified while storing the transaction.
                $sub = new MeprSubscription($sub->id);
                $sub->expire_txns(); // Expire associated transactions for the old subscription.
                $sub->store();

                MeprUtils::send_failed_txn_notices($txn);
            }
        }
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    /**
     * Handle the `refund.created` and `refund.updated` webhooks.
     *
     * @param array $refund The refund data.
     *
     * @return void
     */
    protected function handle_refund_webhook(array $refund): void
    {
        if (empty($refund['status']) || empty($refund['payment_id']) || $refund['status'] !== 'COMPLETED') {
            return;
        }

        $txn = MeprTransaction::get_instance_by_trans_num($refund['payment_id']);

        if (!$txn instanceof MeprTransaction || $txn->gateway != $this->id) {
            return;
        }

        $this->record_transaction_refund($txn);
    }

    /**
     * Handle the `subscription.updated` webhook.
     *
     * @param array $subscription The subscription data.
     *
     * @return void
     */
    protected function handle_subscription_updated_webhook(array $subscription): void
    {
        if (empty($subscription['id']) || empty($subscription['status'])) {
            return;
        }

        $sub = MeprSubscription::get_one_by_subscr_id($subscription['id']);

        if (!$sub instanceof MeprSubscription || $sub->gateway != $this->id) {
            return;
        }

        if (in_array($subscription['status'], ['CANCELED', 'DEACTIVATED'])) {
            $this->record_cancel_sub($sub);
        }
    }

    /**
     * Get an order by ID.
     *
     * @param string $order_id The order ID.
     *
     * @return array The order data returned from Square.
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    protected function get_order(string $order_id): array
    {
        $response = $this->api_request('GET', "/v2/orders/$order_id");

        return $response['order'];
    }

    /**
     * Get a payment by ID.
     *
     * @param string $payment_id The payment ID.
     *
     * @return array The payment data returned from Square.
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    protected function get_payment(string $payment_id): array
    {
        $response = $this->api_request('GET', "/v2/payments/$payment_id");

        return $response['payment'];
    }

    /**
     * Webhook listener for service webhooks sent from the Square Connect app.
     *
     * @return void
     */
    public function service_listener()
    {
        $mepr_options = MeprOptions::fetch();

        // Retrieve the request's body and parse it as JSON.
        $body = file_get_contents('php://input');

        if (!is_string($body)) {
            MeprUtils::exit_with_status(400, __('Bad request.', 'memberpress'));
        }

        $header_signature = MeprUtils::get_http_header('Signature');

        if (empty($header_signature)) {
            MeprUtils::exit_with_status(403, __('No Webhook Signature', 'memberpress'));
        }

        $secret    = get_option('mepr_authenticator_secret_token');
        $signature = hash_hmac('sha256', $body, $secret);

        if ($header_signature != $signature) {
            MeprUtils::exit_with_status(403, __('Incorrect Webhook Signature', 'memberpress'));
        }

        $body = json_decode($body, true);

        if (empty($body['event'])) {
            MeprUtils::exit_with_status(403, __('No `event` set', 'memberpress'));
        }

        $event = sanitize_text_field($body['event']);

        $auth_site_uuid = get_option('mepr_authenticator_site_uuid');

        if ($event == 'update-credentials') {
            $site_uuid = sanitize_text_field($body['data']['site_uuid'] ?? '');
            if (empty($site_uuid) || $auth_site_uuid != $site_uuid) {
                MeprUtils::exit_with_status(404, __('Request was sent to the wrong site?', 'memberpress'));
            }

            $method_id = sanitize_text_field($body['data']['payment_method_id'] ?? '');
            $pm        = $mepr_options->payment_method($method_id);
            if (!$pm instanceof MeprSquareGateway) {
                MeprUtils::exit_with_status(404, __('No payment method like that exists on this site', 'memberpress'));
            }

            $environment = sanitize_key($body['data']['environment'] ?? '');
            if (!in_array($environment, ['production', 'sandbox'], true)) {
                MeprUtils::exit_with_status(404, sprintf(
                    // Translators: %s: the given environment value.
                    __('The environment must be either "production" or "sandbox", but "%s" was given.', 'memberpress'),
                    $environment
                ));
            }

            try {
                $pm->fetch_credentials($environment);

                MeprUtils::exit_with_status(204);
            } catch (Exception $e) {
                MeprUtils::exit_with_status(500, $e->getMessage());
            }
        }

        MeprUtils::exit_with_status(404, __('Webhook not supported', 'memberpress'));
    }

    /**
     * Fetches the credentials from the Square Connect service and updates them in the payment method settings.
     *
     * @param string $environment The environment to refresh the credentials for (e.g., 'production' or 'sandbox').
     *
     * @throws MeprException If there was an error generating the JWT.
     * @throws MeprGatewayException If there was an error with the request or the response was invalid.
     */
    public function fetch_credentials(string $environment)
    {
        $site_uuid = get_option('mepr_authenticator_site_uuid');
        $url       = self::connect_service_url($environment);

        $response = wp_remote_get(
            "$url/api/memberpress/credentials/$site_uuid/$this->id",
            [
                'headers' => MeprUtils::jwt_header(
                    $this->generate_jwt($environment),
                    self::connect_service_domain($environment)
                ),
            ]
        );

        $this->store_credentials($environment, $this->parse_credentials_response($response));
    }

    /**
     * Generate the URL to refresh the credentials for this Square gateway.
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
                    'mepr_square_action' => 'process_refresh_credentials',
                    'payment_method_id'  => $this->id,
                    'environment'        => $environment,
                    '_wpnonce'           => wp_create_nonce('mepr_square_process_refresh_credentials'),
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
    public function refresh_credentials(string $environment)
    {
        $site_uuid = get_option('mepr_authenticator_site_uuid');
        $url       = self::connect_service_url($environment);

        $response = wp_remote_post(
            "$url/api/memberpress/refresh/$site_uuid/$this->id",
            [
                'headers' => MeprUtils::jwt_header(
                    $this->generate_jwt($environment),
                    self::connect_service_domain($environment)
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
        if (wp_remote_retrieve_response_code($response) == 200) {
            $credentials = json_decode(wp_remote_retrieve_body($response), true);

            if (
                is_array($credentials) &&
                !empty($credentials['merchant_id']) &&
                !empty($credentials['access_token']) &&
                !empty($credentials['expires_at'])
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
     * Store the given credentials for this gateway and all other connected gateways with the same merchant ID.
     *
     * Additionally, if this gateway doesn't have a location set, the main business location ID will be set.
     *
     * @param string         $environment The environment to update the stored credentials for (e.g., 'production' or 'sandbox').
     * @param array|WP_Error $credentials The array of credential data.
     *
     * @return void
     */
    protected function store_credentials(string $environment, array $credentials): void
    {
        $options = MeprOptions::fetch();

        foreach ($options->integrations as $id => $integration) {
            if ($integration['gateway'] != self::class) {
                continue;
            }

            if (
                $integration['id'] == $this->id ||
                (
                    isset(
                        $integration["{$environment}_connected"],
                        $integration["{$environment}_merchant_id"]
                    ) &&
                    $integration["{$environment}_connected"] &&
                    $integration["{$environment}_merchant_id"] == $credentials['merchant_id']
                )
            ) {
                $integration["{$environment}_connected"]    = true;
                $integration["{$environment}_merchant_id"]  = $credentials['merchant_id'];
                $integration["{$environment}_access_token"] = $credentials['access_token'];
                $integration["{$environment}_expires_at"]   = $credentials['expires_at'];

                if (!empty($credentials['currency'])) {
                    $integration["{$environment}_currency"] = $credentials['currency'];
                }

                if (!empty($credentials['country'])) {
                    $integration["{$environment}_country"] = $credentials['country'];
                }

                if (empty($integration["{$environment}_location_id"]) && !empty($credentials['main_location_id'])) {
                    $integration["{$environment}_location_id"] = $credentials['main_location_id'];
                }

                if ($integration['id'] == $this->id) {
                    if (!empty($credentials['key'])) {
                        update_option("mepr_square_key_{$credentials['merchant_id']}_$environment", $credentials['key']);
                    }

                    $this->refresh_locations($environment, $credentials['merchant_id'], $credentials['access_token']);
                }

                $options->integrations[$id] = $integration;
            }
        }

        $options->store(false);
    }

    /**
     * Generate the URL to disconnect this Square gateway.
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
                    'mepr_square_action' => 'process_disconnect',
                    'payment_method_id'  => $this->id,
                    'environment'        => $environment,
                    '_wpnonce'           => wp_create_nonce('mepr_square_process_disconnect'),
                ]
            ),
            admin_url('admin.php')
        );
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * Disconnects the current gateway from the specified environment.
     *
     * @param string $environment The environment to disconnect from (e.g., 'production' or 'sandbox').
     * @param string $type        The type of disconnection to perform (e.g., 'full').
     *
     * @return void
     *
     * @throws MeprException Possible exception when generating the JWT.
     * @throws Exception If the disconnect process fails.
     */
    public function disconnect(string $environment, string $type): void
    {
        if ($type == 'full') {
            $options     = MeprOptions::fetch();
            $integration = $options->integrations[$this->id];

            $integration["{$environment}_connected"] = false;

            $options->integrations[$this->id] = $integration;
            $options->store(false);
        }

        $site_uuid = get_option('mepr_authenticator_site_uuid');
        $url       = self::connect_service_url($environment);

        $response = wp_remote_request(
            "$url/api/memberpress/disconnect/$site_uuid/$this->id",
            [
                'method'  => 'DELETE',
                'headers' => MeprUtils::jwt_header(
                    $this->generate_jwt($environment),
                    self::connect_service_domain($environment)
                ),
            ]
        );

        if (wp_remote_retrieve_response_code($response) != 204) {
            throw new Exception(__('Invalid response.', 'memberpress'));
        }
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing
    /**
     * Refresh available business locations for the merchant account.
     *
     * @param string|null $environment            Can be set to override the API request environment (used during initial connection).
     * @param string|null $merchant_id            Can be set to override the merchant ID used to store the locations (used during initial connection).
     * @param string|null $encrypted_access_token Can be set to override the API request access token (used during initial connection).
     *
     * @return array
     */
    protected function refresh_locations(
        ?string $environment = null,
        ?string $merchant_id = null,
        ?string $encrypted_access_token = null
    ): array {
        $locations = [];

        try {
            if (is_null($environment)) {
                $environment = $this->settings->sandbox ? 'sandbox' : 'production';
            } else {
                if (!in_array($environment, ['production', 'sandbox'], true)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            // Translators: %s: the given environment value.
                            'The environment must be either "production" or "sandbox", but "%s" was given.',
                            $environment
                        )
                    );
                }
            }

            $response = $this->api_request(
                'GET',
                '/v2/locations',
                [],
                $environment,
                $merchant_id,
                $encrypted_access_token
            );

            foreach ($response['locations'] as $location) {
                if (
                    $location['status'] == 'ACTIVE' &&
                    in_array('CREDIT_CARD_PROCESSING', (array) $location['capabilities'], true)
                ) {
                    $locations[$location['id']] = $location['name'];
                }
            }

            $merchant_id = $merchant_id ?? $this->settings->{"{$environment}_merchant_id"};

            update_option("mepr_square_{$environment}_{$merchant_id}_locations", $locations);
        } catch (Exception $e) {
            MeprUtils::debug_log('[Square] Exception listing locations: ' . $e->getMessage());
        }

        return $locations;
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing

    /**
     * Generates a dropdown HTML element for selecting a business location.
     *
     * @param  string $environment The environment context (e.g., 'sandbox' or 'production').
     * @return string The generated HTML for the business location dropdown.
     */
    public function get_business_location_dropdown(string $environment): string
    {
        $options          = MeprOptions::fetch();
        $merchant_id      = $this->settings->{"{$environment}_merchant_id"};
        $location_id      = $this->settings->{"{$environment}_location_id"};
        $locations        = get_option("mepr_square_{$environment}_{$merchant_id}_locations");
        $location_present = false;

        if (!is_array($locations) || empty($locations)) {
            $locations = $this->refresh_locations();
        }
        ob_start();
        ?>
        <select
            id="<?php echo esc_attr(sanitize_key("$options->integrations_str[$this->id][{$environment}_location_id]")); ?>"
            name="<?php echo esc_attr("$options->integrations_str[$this->id][{$environment}_location_id]") ?>"
        >
            <?php foreach ($locations as $id => $name) : ?>
                <?php
                if ($id == $location_id) {
                    $location_present = true;
                }
                ?>
                <option
                    value="<?php echo esc_attr($id) ?>"
                    <?php selected($id, $location_id) ?>
                >
                    <?php echo esc_html($name) ?>
                </option>
            <?php endforeach; ?>
            <?php if ($location_id && !$location_present) : ?>
                <option value="<?php echo esc_attr($location_id); ?>" selected><?php echo esc_html($location_id); ?></option>
            <?php endif; ?>
        </select>
        <?php
        return ob_get_clean();
    }

    /**
     * {@inheritdoc}
     *
     * @param MeprTransaction $txn The transaction to refund.
     *
     * @return void
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    public function process_refund(MeprTransaction $txn)
    {
        $data = [
            'idempotency_key' => md5($txn->trans_num),
            'payment_id'      => $txn->trans_num,
            'amount_money'    => [
                'amount'   => $this->format_amount((float) $txn->total),
                'currency' => (MeprOptions::fetch())->currency_code,
            ],
        ];

        $this->api_request(
            'POST',
            '/v2/refunds',
            MeprHooks::apply_filters('mepr_square_refund_data', $data, $txn)
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
        if ($txn->status == MeprTransaction::$refunded_str) {
            return;
        }

        $txn->status = MeprTransaction::$refunded_str;
        $txn->store();

        MeprUtils::send_refunded_txn_notices($txn);
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * {@inheritdoc}
     *
     * @param integer $subscription_id The ID of the subscription to cancel.
     *
     * @return void
     *
     * @throws MeprGatewayException If the subscription was not found or already cancelled.
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    public function process_cancel_subscription($subscription_id)
    {
        $sub = new MeprSubscription($subscription_id);

        if (!($sub->id > 0)) {
            throw new MeprGatewayException(__('Subscription not found', 'memberpress'));
        }

        if ($sub->status == MeprSubscription::$cancelled_str || $sub->status == MeprSubscription::$suspended_str) {
            throw new MeprGatewayException(__('This subscription has already been cancelled.', 'memberpress'));
        }

        // If this method is called from limit_payment_cycles, we don't need to actively cancel the subscription.
        // Instead, we can let it expire naturally at Square since the cycles are limited within the plan.
        if (!isset($_REQUEST['expire'])) {
            $this->api_request('POST', "/v2/subscriptions/$sub->subscr_id/cancel");
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
        if ($sub->status == MeprSubscription::$cancelled_str || $sub->status == MeprSubscription::$suspended_str) {
            return;
        }

        $sub->status = MeprSubscription::$cancelled_str;
        $sub->store();

        if (isset($_REQUEST['expire'])) {
            $sub->limit_reached_actions();
        }

        if (!isset($_REQUEST['silent']) || !$_REQUEST['silent']) {
            MeprUtils::send_cancelled_sub_notices($sub);
        }
    }

    /**
     * Enqueue the scripts for the update subscription payment method page.
     *
     * @return void
     */
    public function enqueue_user_account_scripts()
    {
        if (!isset($_GET['action']) || $_GET['action'] != 'update') {
            return;
        }

        $sub = new MeprSubscription((int) $_GET['sub'] ?? 0);

        if ($sub->id > 0 && $sub->gateway == $this->id) {
            $this->enqueue_payment_form_scripts();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param integer $subscription_id The ID of the subscription.
     * @param array   $errors          The errors to display.
     * @param string  $message         The message to display.
     *
     * @return void
     */
    public function display_update_account_form($subscription_id, $errors = [], $message = '')
    {
        if (empty($message) && isset($_GET['message'])) {
            $message = sanitize_text_field(wp_unslash($_GET['message']));
        }

        $options = MeprOptions::fetch();
        ?>
        <div class="mp-wrapper">
            <form method="post" class="mepr-square-payment-form" data-type="update_account">
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
                <?php echo $this->get_elements_html(['intent' => 'STORE']); ?>
                <div class="mepr_spacer">&nbsp;</div>
                <input type="submit" class="mepr-submit" value="<?php echo esc_attr(_x('Submit', 'ui', 'memberpress')); ?>" />
                <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />
                <noscript><p class="mepr_nojs"><?php esc_html_e('JavaScript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
            </form>
        </div>
        <?php
    }

    /**
     * {@inheritdoc}
     *
     * @param array $errors The errors to validate.
     *
     * @return array
     */
    public function validate_update_account_form($errors = [])
    {
        if (!wp_doing_ajax() || empty($_REQUEST['action']) || $_REQUEST['action'] != 'mepr_process_update_account_form') {
            $errors[] = __('Bad request.', 'memberpress');
        }

        return $errors;
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * Process an account update form via Ajax.
     *
     * @param MeprSubscription $sub The subscription to be updated.
     *
     * @throws MeprGatewayException If request data is missing.
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    public function process_update_account_form_ajax(MeprSubscription $sub)
    {
        $source_id       = sanitize_text_field(wp_unslash($_POST['mepr_square_source_id'] ?? ''));
        $idempotency_key = sanitize_text_field(wp_unslash($_POST['mepr_square_idempotency_key'] ?? ''));

        if (empty($source_id) || empty($idempotency_key)) {
            throw new MeprGatewayException(__('Bad request', 'memberpress'));
        }

        $usr          = $sub->user();
        $subscription = $this->get_subscription($sub->subscr_id);

        $card = $this->create_card(
            $idempotency_key,
            $subscription['customer_id'],
            $source_id,
            $usr
        );

        $this->api_request(
            'PUT',
            "/v2/subscriptions/$sub->subscr_id",
            [
                'subscription' => [
                    'card_id' => $card['id'],
                ],
            ]
        );

        $sub->cc_last4     = !empty($card['last_4']) ? $card['last_4'] : '';
        $sub->cc_exp_month = !empty($card['exp_month']) ? $card['exp_month'] : '';
        $sub->cc_exp_year  = !empty($card['exp_year']) ? $card['exp_year'] : '';
        $sub->store();

        $meta_key    = $this->get_meta_key($usr->ID, 'customer_id');
        $customer_id = get_user_meta($usr->ID, $meta_key, true);

        if (empty($customer_id)) {
            update_user_meta($usr->ID, $meta_key, $subscription['customer_id']);
        }
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    /**
     * Get a Square subscription by ID.
     *
     * @param string $subscr_id The Square subscription ID.
     *
     * @return array
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    protected function get_subscription(string $subscr_id): array
    {
        $response = $this->api_request('GET', "/v2/subscriptions/$subscr_id");

        return $response['subscription'];
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * {@inheritdoc}
     *
     * @param integer $subscription_id The ID of the subscription to suspend.
     *
     * @return void
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprGatewayException If the subscription cannot be paused.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    public function process_suspend_subscription($subscription_id)
    {
        $sub = new MeprSubscription($subscription_id);

        if ($sub->status == MeprSubscription::$suspended_str) {
            throw new MeprGatewayException(__('This subscription has already been paused.', 'memberpress'));
        }

        if (!MeprUtils::is_mepr_admin() && $sub->in_free_trial()) {
            throw new MeprGatewayException(__('Sorry, subscriptions cannot be paused during a free trial.', 'memberpress'));
        }

        $this->api_request('POST', "/v2/subscriptions/$sub->subscr_id/pause");

        $sub->status = MeprSubscription::$suspended_str;
        $sub->store();

        MeprUtils::send_suspended_sub_notices($sub);
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * {@inheritdoc}
     *
     * @param integer $subscription_id The ID of the subscription to resume.
     *
     * @return void
     *
     * @throws InvalidArgumentException|SodiumException|UnexpectedValueException If there was an error with the access token.
     * @throws MeprGatewayException If the subscription cannot be resumed.
     * @throws MeprHttpException|MeprRemoteException If there was an error with the request.
     */
    public function process_resume_subscription($subscription_id)
    {
        $sub = new MeprSubscription($subscription_id);

        if ($sub->status == MeprSubscription::$active_str) {
            throw new MeprGatewayException(__('This subscription has already been resumed.', 'memberpress'));
        }

        $this->api_request('POST', "/v2/subscriptions/$sub->subscr_id/resume");

        $sub->status = MeprSubscription::$active_str;
        $sub->store();

        MeprUtils::send_resumed_sub_notices($sub);
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    /**
     * Is this gateway usable?
     *
     * To be usable, the gateway must:
     *
     * - Have an access token.
     * - Use the same currency as MemberPress.
     * - For recurring memberships, Square must support the subscription cadence.
     *
     * @param MeprProduct|null $product The product being purchased.
     *
     * @return boolean
     */
    public function is_usable(MeprProduct $product = null): bool
    {
        $options     = MeprOptions::fetch();
        $environment = $this->is_test_mode() ? 'sandbox' : 'production';
        $is_usable   = !empty($this->settings->{"{$environment}_access_token"});
        $currency    = $this->settings->{"{$environment}_currency"};

        if (!empty($currency) && $currency != $options->currency_code) {
            $is_usable = false;
        }

        if ($product instanceof MeprProduct) {
            try {
                if ($product->period_type != 'lifetime') {
                    self::get_cadence((string) $product->period_type, (int) $product->period);
                }
            } catch (MeprGatewayException $e) {
                $is_usable = false;
            }
        }

        return (bool) MeprHooks::apply_filters('mepr_square_is_usable', $is_usable, $this, $product);
    }

    /**
     * Process order bump transactions for a given order.
     *
     * This method iterates through order bump transactions and processes them
     * by either recording one-time payments or creating subscriptions as needed.
     *
     * @param MeprOrder   $order           The order containing the order bumps.
     * @param array       $transactions    The array of order bump transactions to process.
     * @param array       $card            The payment card information.
     * @param string      $customer_id     The ID of the Square customer.
     * @param string      $idempotency_key The idempotency key for Square API calls.
     * @param string|null $order_trans_num The transaction number for the order.
     *
     * @return void
     */
    protected function process_order_bump_transactions(
        MeprOrder $order,
        array $transactions,
        array $card,
        string $customer_id,
        string $idempotency_key,
        ?string $order_trans_num = null
    ): void {
        if ($order->is_complete() || $order->is_processing()) {
            return;
        }

        try {
            $order->update_meta('processing', true);

            foreach ($transactions as $transaction) {
                $trans_num = sprintf('mi_%d_%s', $order->id, uniqid());
                $product = $transaction->product();

                if (empty($product->ID)) {
                    wp_send_json_error(__('Product not found', 'memberpress'));
                }

                if (!$transaction->is_payment_required()) {
                    MeprTransaction::create_free_transaction($transaction, false, $trans_num);
                    continue;
                }

                if ($transaction->is_one_time_payment()) {
                    $this->record_one_time_payment($transaction, $trans_num);
                } else {
                    $sub = $transaction->subscription();

                    if (!($sub instanceof MeprSubscription)) {
                        wp_send_json_error(__('Subscription not found', 'memberpress'));
                    }

                    if ($sub->trial && $sub->trial_days > 0) {
                        $trial_days     = (int) $sub->trial_days;
                        $amount         = (float) $sub->trial_total;
                        $txn_expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
                    } else {
                        // If the sub doesn't have a trial, we want to create a subscription with the trial days set to cover one period
                        // since the initial period was already paid for.
                        $now            = new DateTimeImmutable('now');
                        $end            = $now->modify(sprintf('+%d %s', $sub->period, $sub->period_type));
                        $trial_days     = (int) $end->diff($now)->format('%a');
                        $amount         = (float) $sub->total;
                        $txn_expires_at = null;
                    }

                    $subscription = $this->create_subscription(
                        $idempotency_key,
                        $customer_id,
                        $card['id'],
                        $product,
                        $sub,
                        max($trial_days, 0)
                    );

                    $sub->subscr_id    = $subscription['id'];
                    $sub->cc_last4     = !empty($card['last_4']) ? $card['last_4'] : '';
                    $sub->cc_exp_month = !empty($card['exp_month']) ? $card['exp_month'] : '';
                    $sub->cc_exp_year  = !empty($card['exp_year']) ? $card['exp_year'] : '';

                    $this->record_create_sub($sub);

                    if ($amount > 0) {
                        $this->record_sub_payment(
                            $sub,
                            $amount,
                            $trans_num,
                            $card,
                            $txn_expires_at
                        );
                    }
                }
            }

            if (!is_null($order_trans_num)) {
                $order->trans_num = $order_trans_num;
            }

            $order->status = MeprOrder::$complete_str;
            $order->store();
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        } finally {
            $order->delete_meta('processing');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param MeprTransaction $transaction The transaction to process.
     *
     * @return void
     */
    public function process_payment($transaction)
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     */
    public function record_payment()
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     */
    public function record_refund()
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     */
    public function record_subscription_payment()
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     */
    public function record_payment_failure()
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     *
     * @param MeprTransaction $transaction The transaction to process.
     *
     * @return void
     */
    public function process_trial_payment($transaction)
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     *
     * @param MeprTransaction $transaction The transaction to record.
     *
     * @return void
     */
    public function record_trial_payment($transaction)
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     *
     * @param MeprTransaction $transaction The transaction to process.
     *
     * @return void
     */
    public function process_create_subscription($transaction)
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     */
    public function record_create_subscription()
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     *
     * @param integer $subscription_id The ID of the subscription to update.
     *
     * @return void
     */
    public function process_update_subscription($subscription_id)
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     */
    public function record_update_subscription()
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     */
    public function record_suspend_subscription()
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     */
    public function record_resume_subscription()
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     */
    public function record_cancel_subscription()
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     *
     * @param MeprTransaction $txn The transaction to process.
     *
     * @return void
     */
    public function process_signup_form($txn)
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     *
     * @param MeprTransaction $txn The transaction to display the payment page for.
     *
     * @return void
     */
    public function display_payment_page($txn)
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     *
     * @param array $errors The errors to validate.
     *
     * @return void
     */
    public function validate_payment_form($errors)
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     *
     * @param array $errors The errors to validate.
     *
     * @return void
     */
    public function validate_options_form($errors)
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     *
     * @param integer $subscription_id The ID of the subscription to update.
     *
     * @return void
     */
    public function process_update_account_form($subscription_id)
    {
        // Required by parent class, not used in this implementation.
    }

    /**
     * {@inheritdoc}
     */
    public function force_ssl()
    {
        // Required by parent class, not used in this implementation.
    }
}
