<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

#[AllowDynamicProperties]
class MeprPayPalCommerceGateway extends MeprBasePayPalGateway
{
    /**
     * Used in the view to identify the gateway
     */
    public function __construct()
    {
        $this->name         = 'PayPal';
        $this->key          = 'paypalcommerce';
        $this->has_spc_form = true;
        $this->set_defaults();

        // Setup the notification actions for this gateway.
        $this->notifiers = [
            'ipn'     => 'ipn_listener',
            'cancel'  => 'cancel_handler',
            'webhook' => 'webhook_handler',
            'return'  => 'return_handler',
        ];

        $this->message_pages = ['cancel' => 'cancel_message'];
    }

    /**
     * Record the creation of a subscription.
     *
     * @return void
     */
    public function record_create_subscription()
    {
        // Not needed, subscription will be created with PENDING satatus, before payment done.
    }

    /**
     * Record a payment failure.
     *
     * @return MeprTransaction|false
     */
    public function record_payment_failure()
    {
        if (isset($_POST['txn_id']) && ( $txn_res = MeprTransaction::get_one_by_trans_num($_POST['txn_id']) ) && isset($txn_res->id)) { // phpcs:ignore
            $txn         = new MeprTransaction($txn_res->id);
            $txn->status = MeprTransaction::$failed_str;
            $txn->store();
        } elseif (
            ( isset($_POST['recurring_payment_id']) and
                 ( $sub = MeprSubscription::get_one_by_subscr_id($_POST['recurring_payment_id']) ) ) or // phpcs:ignore
               ( isset($_POST['subscr_id']) and
                 ( $sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_id']) ) ) // phpcs:ignore
        ) {
            $first_txn = $sub->first_txn();

            if ($first_txn == false || ! ( $first_txn instanceof MeprTransaction )) {
                $first_txn             = new MeprTransaction();
                $first_txn->user_id    = $sub->user_id;
                $first_txn->product_id = $sub->product_id;
                $first_txn->coupon_id  = $sub->coupon_id;
            }

            $txn                  = new MeprTransaction();
            $txn->user_id         = $sub->user_id;
            $txn->product_id      = $sub->product_id;
            $txn->coupon_id       = $first_txn->coupon_id;
            $txn->txn_type        = MeprTransaction::$payment_str;
            $txn->status          = MeprTransaction::$failed_str;
            $txn->subscription_id = $sub->id;
            $txn->trans_num       = ( isset($_POST['recurring_payment_id']) ? $_POST['recurring_payment_id'] : uniqid() );
            $txn->gateway         = $this->id;

            $txn->set_gross(isset($_POST['mc_gross']) ? $_POST['mc_gross'] : ( isset($_POST['amount']) ? $_POST['amount'] : 0.00 ));

            $txn->store();

            $sub->expire_txns(); // Expire associated transactions for the old subscription.
            $sub->store();
        } else {
            return false; // Nothing we can do here ... so we outta here.
        }

        MeprUtils::send_failed_txn_notices($txn);

        return $txn;
    }

    /**
     * Record a payment.
     *
     * @return void
     */
    public function record_payment()
    {
        // Not needed, payment will be recorded by webhook handler.
    }

    /**
     * Load the gateway settings.
     *
     * @param  array $settings The settings to load.
     * @return void
     */
    public function load($settings)
    {
        $this->settings = (object) $settings;
        $this->set_defaults();
    }

    /**
     * Log data for debugging purposes.
     *
     * @param  mixed $data The data to log.
     * @return void
     */
    public function log($data)
    {
        if (! defined('WP_MEPR_DEBUG')) {
            return;
        }

        file_put_contents(WP_CONTENT_DIR . '/paypal-connect.log', print_r($data, true) . PHP_EOL, FILE_APPEND);
    }

    /**
     * Set default settings for the gateway.
     *
     * @return void
     */
    protected function set_defaults()
    {
        if (! isset($this->settings)) {
            $this->settings = [];
        }

        $this->settings  =
        (object) array_merge(
            [
                'gateway'                            => 'MeprPayPalCommerceGateway',
                'id'                                 => $this->generate_id(),
                'label'                              => '',
                'use_label'                          => true,
                'icon'                               => MEPR_IMAGES_URL . '/checkout/paypal.png',
                'use_icon'                           => true,
                'desc'                               => __('Pay via your PayPal account', 'memberpress'),
                'use_desc'                           => true,
                'enable_smart_button'                => false,
                'enable_paypal_standard_debug_email' => false,
                'test_client_id'                     => '',
                'test_client_secret'                 => '',
                'live_client_id'                     => '',
                'live_client_secret'                 => '',
                'test_webhook_id'                    => '',
                'live_webhook_id'                    => '',
                'test_merchant_id'                   => '',
                'live_merchant_id'                   => '',
                'test_auth_code'                     => '',
                'live_auth_code'                     => '',
                'test_email_confirmed'               => '',
                'live_email_confirmed'               => '',
                'debug'                              => false,
            ],
            (array) $this->settings
        );
        $this->id        = $this->settings->id;
        $this->label     = $this->settings->label;
        $this->use_label = $this->settings->use_label;
        $this->icon      = $this->settings->icon;
        $this->use_icon  = $this->settings->use_icon;
        $this->desc      = $this->settings->desc;
        $this->use_desc  = $this->settings->use_desc;
        $this->debug     = defined('WP_MEPR_DEBUG') && WP_MEPR_DEBUG === true;

        if ($this->is_test_mode()) {
            $this->settings->url          = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
            $this->settings->api_url      = 'https://api-3t.sandbox.paypal.com/nvp';
            $this->settings->rest_api_url = 'https://api-m.sandbox.paypal.com';
        } else {
            $this->settings->url          = 'https://ipnpb.paypal.com/cgi-bin/webscr';
            $this->settings->api_url      = 'https://api-3t.paypal.com/nvp';
            $this->settings->rest_api_url = 'https://api.paypal.com';
        }

        $this->settings->api_version = 69;

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
        ];
    }

    /**
     * Used to record a successful recurring payment by the given gateway. It
     * should have the ability to record a successful payment or a failure. It is
     * this method that should be used when receiving an IPN from PayPal or a
     * Silent Post from Authorize.net.
     *
     * @return mixed
     */
    public function record_subscription_payment()
    {
        if (! isset($_POST['recurring_payment_id']) && ! isset($_POST['subscr_id'])) {
            return;
        }

        if (isset($_POST['subscr_id']) && ! empty($_POST['subscr_id'])) {
            $sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_id']);
        } else {
            $sub = MeprSubscription::get_one_by_subscr_id($_POST['recurring_payment_id']);
        }

        if ($sub) {
            $timestamp = isset($_POST['payment_date']) ? strtotime($_POST['payment_date']) : time();
            $first_txn = new MeprTransaction($sub->first_txn_id);

            if (! isset($first_txn->id) || empty($first_txn->id)) {
                $first_txn             = new MeprTransaction();
                $first_txn->user_id    = $sub->user_id;
                $first_txn->product_id = $sub->product_id;
                $first_txn->coupon_id  = $sub->coupon_id;
            }

            $existing = MeprTransaction::get_one_by_trans_num($_POST['txn_id']);

            // There's a chance this may have already happened during the return handler, if so let's just get everything up to date on the existing one.
            if ($existing != null && isset($existing->id) && (int) $existing->id > 0) {
                $txn     = new MeprTransaction($existing->id);
                $handled = $txn->get_meta('mepr_paypal_notification_handled');

                if (!empty($handled)) {
                    return;
                }
            } else {
                $txn = new MeprTransaction();
            }

            // If this is a trial payment, let's just convert the confirmation txn into a payment txn.
            if ($this->is_subscr_trial_payment($sub)) {
                $txn                  = $first_txn; // For use below in send notices.
                $txn->created_at      = MeprUtils::ts_to_mysql_date($timestamp);
                $txn->expires_at      = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
                $txn->gateway         = $this->id;
                $txn->trans_num       = $_POST['txn_id'];
                $txn->txn_type        = MeprTransaction::$payment_str;
                $txn->status          = MeprTransaction::$complete_str;
                $txn->subscription_id = $sub->id;

                if (isset($_POST['mepr_order_id'])) {
                    $txn->order_id = $_POST['mepr_order_id'];
                }

                $txn->set_gross($_POST['mc_gross']);
                $txn->store();
            } else {
                $txn->created_at      = MeprUtils::ts_to_mysql_date($timestamp);
                $txn->user_id         = $first_txn->user_id;
                $txn->product_id      = $first_txn->product_id;
                $txn->coupon_id       = $first_txn->coupon_id;
                $txn->gateway         = $this->id;
                $txn->trans_num       = $_POST['txn_id'];
                $txn->txn_type        = MeprTransaction::$payment_str;
                $txn->status          = MeprTransaction::$complete_str;
                $txn->subscription_id = $sub->id;

                if (isset($_POST['mepr_order_id'])) {
                    $txn->order_id = $_POST['mepr_order_id'];
                }

                $txn->set_gross($_POST['mc_gross']);
                $txn->store();

                // Check that the subscription status is still enabled.
                if ($sub->status != MeprSubscription::$active_str) {
                    $sub->status = MeprSubscription::$active_str;
                    $sub->store();
                }

                // Not waiting for an IPN here bro ... just making it happen even though
                // the total occurrences is already capped in record_create_subscription().
                $sub->limit_payment_cycles();
            }

            $txn->update_meta('mepr_paypal_notification_handled', true);

            $this->email_status("Subscription Transaction\n" . MeprUtils::object_to_string($txn->rec, true), $this->debug);

            MeprUtils::send_transaction_receipt_notices($txn);

            return $txn;
        }

        return false;
    }

    /**
     * Used to send data to a given payment gateway. In gateways which redirect
     * before this step is necessary this method should just be left blank.
     *
     * @param  MeprTransaction $txn The transaction object.
     * @return void
     */
    public function process_payment($txn)
    {
    }

    /**
     * Processes a refund for a given transaction.
     *
     * @param MeprTransaction $txn The transaction object.
     *
     * @return false|MeprTransaction
     * @throws MeprGatewayException When refund request fails.
     */
    public function process_refund(MeprTransaction $txn)
    {
        $product = $txn->product();

        if ($product->is_one_time_payment()) {
            $txn_number = $txn->trans_num;
            $options    = [
                'headers' => [
                    'Content-Type'                  => 'application/json',
                    'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                    'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
                ],
            ];
            $response   = wp_remote_post($this->settings->rest_api_url . '/v2/payments/captures/' . $txn_number . '/refund', $options);
            $this->log($response);
            $response = json_decode(wp_remote_retrieve_body($response), true);
            $this->log($options);
        } else {
            $endpoint = '/v1/payments/sale/' . $txn->trans_num . '/refund';
            $options  = [
                'headers' => [
                    'Content-Type'                  => 'application/json',
                    'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                    'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
                ],
            ];
            $response = wp_remote_post($this->settings->rest_api_url . $endpoint, $options);
            $this->log($response);
            $response = json_decode(wp_remote_retrieve_body($response), true);
            $this->log($options);

            if (isset($response['name']) && $response['name'] == 'TRANSACTION_ALREADY_REFUNDED') {
                $_POST['parent_txn_id'] = $txn->id;

                return $this->record_refund();
            }
        }

        if (isset($response['status']) && $response['status'] !== 'COMPLETED') {
            throw new MeprGatewayException(__('Refund request has been done unsuccessfully', 'memberpress'));
        }

        $_POST['parent_txn_id'] = $txn->id;

        return $this->record_refund();
    }

    /**
     * This method should be used by the class to record a successful refund from
     * the gateway. This method should also be used by any webhook requests or Silent Posts.
     */
    public function record_refund()
    {
        $obj = new MeprTransaction($_POST['parent_txn_id']);

        if (! is_null($obj) && (int) $obj->id > 0) {
            $txn = $obj;

            // Seriously ... if txn was already refunded what are we doing here?
            if ($txn->status == MeprTransaction::$refunded_str) {
                return $txn;
            }

            $txn->status = MeprTransaction::$refunded_str;

            $this->email_status("Processing Refund: \n" . MeprUtils::object_to_string($_POST) . "\n Affected Transaction: \n" . MeprUtils::object_to_string($txn), $this->debug);

            $txn->store();

            MeprUtils::send_refunded_txn_notices($txn);

            return $txn;
        }

        return false;
    }

    /**
     * Process a trial payment.
     * Not needed in PayPal since PayPal supports the trial payment inclusive of the Subscription
     *
     * @param  MeprTransaction $transaction The transaction object.
     * @return void
     */
    public function process_trial_payment($transaction)
    {
    }

    /**
     * Record a trial payment.
     *
     * @param  MeprTransaction $transaction The transaction object.
     * @return void
     */
    public function record_trial_payment($transaction)
    {
    }

    /**
     * Used to send subscription data to a given payment gateway. In gateways
     * which redirect before this step is necessary this method should just be
     * left blank.
     *
     * @param  MeprTransaction $txn The transaction object.
     * @return void
     */
    public function process_create_subscription($txn)
    {
    }

    /**
     * Process a successful one-time payment
     *
     * @param  MeprTransaction $txn       The MemberPress transaction.
     * @param  string          $trans_num The transaction number to set.
     * @return void
     */
    public function handle_one_time_payment(MeprTransaction $txn, $trans_num)
    {
        // Just short circuit if the txn has already completed.
        if ($txn->status == MeprTransaction::$complete_str) {
            return;
        }

        $txn->trans_num = $trans_num;
        $txn->status    = MeprTransaction::$complete_str;
        $txn->store();

        $prd = $txn->product();

        // This will only work before maybe_cancel_old_sub is run.
        $upgrade   = $txn->is_upgrade();
        $downgrade = $txn->is_downgrade();

        $event_txn = $txn->maybe_cancel_old_sub();

        if ($prd->period_type == 'lifetime') {
            if ($upgrade) {
                $this->upgraded_sub($txn, $event_txn);
            } elseif ($downgrade) {
                $this->downgraded_sub($txn, $event_txn);
            } else {
                $this->new_sub($txn);
            }

            MeprUtils::send_signup_notices($txn);
        }

        MeprUtils::send_transaction_receipt_notices($txn);
    }

    /**
     * Handle the creation of a new subscription
     *
     * @param  MeprSubscription $sub       The subscription object.
     * @param  string           $subscr_id The subscription ID.
     * @return void
     */
    public function handle_create_subscription(MeprSubscription $sub, $subscr_id)
    {
        $txn = $sub->first_txn();

        if (!($txn instanceof MeprTransaction)) {
            $txn                  = new MeprTransaction();
            $txn->user_id         = $sub->user_id;
            $txn->product_id      = $sub->product_id;
            $txn->gateway         = $this->id;
            $txn->subscription_id = $sub->id;
        }

        $sub->subscr_id = $subscr_id;

        $this->activate_subscription($txn, $sub);

        // This will only work before maybe_cancel_old_sub is run.
        $upgrade   = $sub->is_upgrade();
        $downgrade = $sub->is_downgrade();

        $event_txn = $sub->maybe_cancel_old_sub();

        if ($upgrade) {
            $this->upgraded_sub($sub, $event_txn);
        } elseif ($downgrade) {
            $this->downgraded_sub($sub, $event_txn);
        } else {
            $this->new_sub($sub, true);
        }

        MeprUtils::send_signup_notices($txn);
    }

    /**
     * Used to cancel a subscription by the given gateway. This method should be used
     * by the class to record a successful cancellation from the gateway. This method
     * should also be used by any IPN requests or Silent Posts.
     *
     * With PayPal, we bill the outstanding amount of the previous subscription,
     * cancel the previous subscription and create a new subscription.
     *
     * @param  integer $sub_id The subscription ID.
     * @return void
     */
    public function process_update_subscription($sub_id)
    {
        // Account info updated on PayPal.com.
    }

    /**
     * This method should be used by the class to record a successful cancellation
     * from the gateway. This method should also be used by any IPN requests or
     * Silent Posts.
     *
     * @return void
     */
    public function record_update_subscription()
    {
        // Account info updated on PayPal.com.
    }

    /**
     * Used to suspend a subscription by the given gateway.
     *
     * @param  integer $sub_id The subscription ID.
     * @return void
     * @throws MeprGatewayException When subscription is already paused, during free trial, or suspend operation fails.
     */
    public function process_suspend_subscription($sub_id)
    {
        $sub = new MeprSubscription($sub_id);

        if ($sub->status == MeprSubscription::$suspended_str) {
            throw new MeprGatewayException(__('This subscription has already been paused.', 'memberpress'));
        }

        if ($sub->in_free_trial()) {
            throw new MeprGatewayException(__('Sorry, subscriptions cannot be paused during a free trial.', 'memberpress'));
        }

        $this->update_paypal_payment_profile($sub_id, 'Suspend');

        $_REQUEST['subscr_id'] = $sub->subscr_id;
        $this->record_suspend_subscription();
    }

    /**
     * Record the suspension of a subscription.
     *
     * @return MeprSubscription|false
     */
    public function record_suspend_subscription()
    {
        $subscr_id = $_REQUEST['subscr_id'];
        $sub       = MeprSubscription::get_one_by_subscr_id($subscr_id);

        if (! $sub) {
            return false;
        }

        // Seriously ... if sub was already suspended what are we doing here?
        if ($sub->status == MeprSubscription::$suspended_str) {
            return $sub;
        }

        $sub->status = MeprSubscription::$suspended_str;
        $sub->store();

        MeprUtils::send_suspended_sub_notices($sub);

        return $sub;
    }

    /**
     * Process the resumption of a subscription.
     *
     * @param  integer $sub_id The subscription ID.
     * @return void
     */
    public function process_resume_subscription($sub_id)
    {
        $sub = new MeprSubscription($sub_id);
        $this->update_paypal_payment_profile($sub_id, 'Reactivate');

        $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
        $this->record_resume_subscription();
    }

    /**
     * Record the resumption of a subscription.
     *
     * @return MeprSubscription|false
     */
    public function record_resume_subscription()
    {
        // APPARENTLY PAYPAL DOES NOT SEND OUT AN IPN FOR THIS -- SO WE CAN'T ACTUALLY RECORD THIS HERE UGH
        // BUT WE DO SET THE SUBSCR STATUS BACK TO ACTIVE WHEN THE NEXT PAYMENT CLEARS.
        $subscr_id = $_REQUEST['recurring_payment_id'];
        $sub       = MeprSubscription::get_one_by_subscr_id($subscr_id);

        if (! $sub) {
            return false;
        }

        // Seriously ... if sub was already active what are we doing here?
        if ($sub->status == MeprSubscription::$active_str) {
            return $sub;
        }

        $sub->status = MeprSubscription::$active_str;
        $sub->store();

        // Check if prior txn is expired yet or not, if so create a temporary txn so the user can access the content immediately.
        $prior_txn = $sub->latest_txn();
        if ($prior_txn == false || ! ( $prior_txn instanceof MeprTransaction ) || strtotime($prior_txn->expires_at) < time()) {
            $txn                  = new MeprTransaction();
            $txn->subscription_id = $sub->id;
            $txn->trans_num       = $sub->subscr_id . '-' . uniqid();
            $txn->status          = MeprTransaction::$confirmed_str;
            $txn->txn_type        = MeprTransaction::$subscription_confirmation_str;
            $txn->expires_at      = MeprUtils::ts_to_mysql_date($sub->get_expires_at());
            $txn->set_subtotal(0.00); // Just a confirmation txn.
            $txn->store();
        }

        MeprUtils::send_resumed_sub_notices($sub);

        return $sub;
    }

    /**
     * Used to cancel a subscription by the given gateway. This method
     * should also be used by any IPN requests or Silent Posts.
     *
     * @param  integer $sub_id The subscription ID.
     * @return void
     */
    public function process_cancel_subscription($sub_id)
    {
        $sub = new MeprSubscription($sub_id);

        // Should already expire naturally at paypal so we have no need
        // to do this when we're "cancelling" because of a natural expiration.
        if (! isset($_REQUEST['expire']) || isset($_REQUEST['limit_payment_cycles'])) {
            $this->update_paypal_payment_profile($sub_id, 'Cancel');
        }

        $_REQUEST['subscr_id'] = $sub->subscr_id;
        $this->record_cancel_subscription();
    }

    /**
     * Record the cancellation of a subscription. This method should also be used by any IPN requests or Silent Posts.
     *
     * @return MeprSubscription|false
     */
    public function record_cancel_subscription()
    {
        // Not sure how/why this would happen but fail silently if it does.
        if (! isset($_REQUEST['subscr_id']) && ! isset($_REQUEST['recurring_payment_id'])) {
            return false;
        }

        $subscr_id = ( isset($_REQUEST['subscr_id']) ) ? $_REQUEST['subscr_id'] : $_REQUEST['recurring_payment_id'];
        $sub       = MeprSubscription::get_one_by_subscr_id($subscr_id);

        if (! $sub) {
            return false;
        }

        // Seriously ... if sub was already cancelled what are we doing here?
        if ($sub->status == MeprSubscription::$cancelled_str) {
            return $sub;
        }

        $sub->status = MeprSubscription::$cancelled_str;
        $sub->store();

        if (isset($_REQUEST['expire'])) {
            $sub->limit_reached_actions();
        }

        if (! isset($_REQUEST['silent']) || ( $_REQUEST['silent'] == false )) {
            MeprUtils::send_cancelled_sub_notices($sub);
        }

        return $sub;
    }

    /**
     * This method is only used when signing up using Smart Buttons
     *
     * @param  MeprTransaction $txn The transaction object.
     * @return void
     */
    public function process_signup_form($txn)
    {
        if (isset($_POST['smart-payment-button']) && $_POST['smart-payment-button']) {
            $order_bump_product_ids = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_map('intval', $_POST['mepr_order_bumps']) : [];
            $order_bump_products    = MeprCheckoutCtrl::get_order_bump_products($txn->product_id, $order_bump_product_ids);

            $order_bumps = $this->process_order($txn, $order_bump_products);

            $data = $this->setup_payment_with_paypal_commerce($txn, $order_bumps, true);

            MeprHooks::do_action('mepr-signup', $txn);

            wp_send_json_success($data);
        }
    }

    /**
     * This gets called on the 'init' hook when the signup form is processed ...
     * this is in place so that payment solutions like paypal can redirect
     * before any content is rendered.
     *
     * This method is called when the Single Page Checkout option is disabled. When the option is enabled,
     * process_payment_form handles the processing.
     *
     * @param  MeprTransaction $txn The transaction object.
     * @return void
     * @throws MeprGatewayException The gateway exception.
     * @throws Exception The exception.
     */
    public function display_payment_page($txn)
    {
        $order_bump_product_ids = isset($_GET['obs']) && is_array($_GET['obs']) ? array_map('intval', $_GET['obs']) : [];
        $order_bump_products    = MeprCheckoutCtrl::get_order_bump_products($txn->product_id, $order_bump_product_ids);

        $order_bumps = $this->process_order($txn, $order_bump_products);

        $this->setup_payment_with_paypal_commerce($txn, $order_bumps);
    }

    /**
     * Enqueue scripts for the payment form.
     *
     * @return void
     */
    public function enqueue_payment_form_scripts()
    {
        if (wp_script_is('mepr-paypalcommerce-form', 'enqueued')) {
            return;
        }

        if ($this->is_test_mode()) {
            $client_id = $this->settings->test_client_id;
        } else {
            $client_id = $this->settings->live_client_id;
        }
        $mepr_options  = MeprOptions::fetch();
        $currency_code = strtoupper($mepr_options->currency_code);
        if ($this->settings->enable_smart_button == 'on') {
            wp_enqueue_script('paypal-sdk-js', 'https://www.paypal.com/sdk/js?vault=true&enable-funding=venmo&currency=' . $currency_code . '&client-id=' . $client_id, [], null, true);
            wp_enqueue_script('mepr-paypalcommerce-form', MEPR_GATEWAYS_URL . '/paypal/form.js', [
                'paypal-sdk-js',
                'mepr-checkout-js',
                'jquery.payment',
            ], MEPR_VERSION, true);

            wp_localize_script('mepr-paypalcommerce-form', 'MeprPayPalCommerceL10n', [
                'ajax_url' => admin_url('admin-ajax.php'),
            ]);
        }
    }

    /**
     * Get the PayPal order object.
     *
     * @param  string $pp_order_id The PayPal order ID.
     * @return array
     */
    protected function get_paypal_order_object($pp_order_id)
    {
        $response = wp_remote_get($this->settings->rest_api_url . '/v2/checkout/orders/' . $pp_order_id, [
            'headers' => [
                'Content-Type'                  => 'application/json',
                'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
            ],
        ]);

        $response = wp_remote_retrieve_body($response);
        $response = json_decode($response, true);

        if (!isset($response['purchase_units'])) {
            $this->log($response);
        }

        return $response;
    }

    /**
     * Get the PayPal basic auth token.
     *
     * @return string
     */
    public function get_pp_basic_auth_token()
    {
        if ($this->is_test_mode()) {
            return base64_encode($this->settings->test_client_id . ':' . $this->settings->test_client_secret);
        } else {
            return base64_encode($this->settings->live_client_id . ':' . $this->settings->live_client_secret);
        }
    }

    /**
     * Get PayPal subscription transactions.
     *
     * @param  string      $pp_subscription_id The PayPal subscription ID.
     * @param  string|null $start_date         The start date.
     * @param  string|null $end_date           The end date.
     * @return array
     */
    public function get_paypal_subscription_transactions($pp_subscription_id, $start_date = null, $end_date = null)
    {
        if (empty($start_date) && empty($end_date)) {
            $start_date = new DateTime();
            $end_date   = new DateTime();
            $end_date->add(new DateInterval('P1D'));
            $start_date->sub(new DateInterval('P1D'));
        }
        $time = 'start_time=' . $start_date->format('Y-m-d') . 'T00:00:00.90Z&end_time=' . $end_date->format('Y-m-d') . 'T23:59:59.90Z';

        $this->log($this->settings->rest_api_url . '/v1/billing/subscriptions/' . $pp_subscription_id . '/transactions?' . $time);
        $response = wp_remote_get($this->settings->rest_api_url . '/v1/billing/subscriptions/' . $pp_subscription_id . '/transactions?' . $time, [
            'headers' => [
                'Content-Type'                  => 'application/json',
                'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
            ],
        ]);

        $response = wp_remote_retrieve_body($response);
        $response = json_decode($response, true);

        return $response;
    }

    /**
     * Get the PayPal subscription object.
     *
     * @param  string $pp_subscription_id The PayPal subscription ID.
     * @return array
     */
    public function get_paypal_subscription_object($pp_subscription_id)
    {
        $response = wp_remote_get($this->settings->rest_api_url . '/v1/billing/subscriptions/' . $pp_subscription_id, [
            'headers' => [
                'Content-Type'                  => 'application/json',
                'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
            ],
        ]);

        $response = wp_remote_retrieve_body($response);
        $response = json_decode($response, true);

        return $response;
    }

    /**
     * Retrieves the PayPal sale payment object for a given payment ID.
     *
     * @param string $pp_payment_id The PayPal payment ID.
     *
     * @return mixed The PayPal sale payment object or an error.
     */
    protected function get_paypal_sale_payment_object($pp_payment_id)
    {
        $response = wp_remote_get($this->settings->rest_api_url . '/v2/payments/captures/' . $pp_payment_id, [
            'headers' => [
                'Content-Type'                  => 'application/json',
                'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
            ],
        ]);

        $response = wp_remote_retrieve_body($response);
        $response = json_decode($response, true);

        return $response;
    }

    /**
     * Generates the PayPal Connect URL for onboarding or connecting an account.
     *
     * @param  string  $method_id  The payment method ID.
     * @param  boolean $sandbox    Whether to use the sandbox environment.
     * @param  boolean $onboarding Whether the URL is for onboarding.
     * @return boolean|string The PayPal Connect URL or false on failure.
     */
    public static function get_paypal_connect_url($method_id, $sandbox = false, $onboarding = false)
    {
        $base_return_url = admin_url('admin.php?page=memberpress-account-login&paypal-connect=1&method_id=' . $method_id, false);

        if ($onboarding) {
            $base_return_url = add_query_arg([
                'onboarding' => 'true',
            ], $base_return_url);
        }

        $error_url = add_query_arg([
            'mepr-action' => 'error',
        ], $base_return_url);

        if ($sandbox) {
            $base_return_url = add_query_arg([
                'sandbox' => '1',
            ], $base_return_url);
        }

        $site_uuid = get_option('mepr_authenticator_site_uuid');

        if (empty($site_uuid)) {
            return false;
        }

        $mepr_options = MeprOptions::fetch();
        $pm           = new self();
        $pm->load(['id' => $method_id]);

        $payload = [
            'method_id'           => $pm->id,
            'site_uuid'           => $site_uuid,
            'user_uuid'           => get_option('mepr_authenticator_user_uuid'),
            'return_url'          => $base_return_url,
            'error_url'           => $error_url,
            'webhook_url'         => $pm->notify_url('whk'),
            'service_webhook_url' => $pm->notify_url('paypal-service-whk'),
            'mp_version'          => MEPR_VERSION,
        ];

        $jwt = MeprAuthenticatorCtrl::generate_jwt($payload);

        if ($sandbox) {
            $service_url = MEPR_PAYPAL_SERVICE_URL . '/sandbox/onboarding/';
        } else {
            $service_url = MEPR_PAYPAL_SERVICE_URL . '/onboarding/';
        }

        return add_query_arg([
            'site_uuid' => $site_uuid,
            'method_id' => $method_id,
            'jwt'       => $jwt,
        ], $service_url);
    }

    /**
     * Set up the PayPal transaction
     *
     * Returns an array with the order/subscription ID and the transaction ID if $return_the_object is true,
     * otherwise redirects to PayPal.
     *
     * @param  MeprTransaction   $txn               The main transaction for the product being purchased.
     * @param  MeprTransaction[] $order_bumps       Additional transactions added as order bumps.
     * @param  boolean           $return_the_object If true, returns an array for the Smart Buttons request, otherwise
     *                                              redirects to PayPal.
     * @return array
     * @throws MeprGatewayException When payment setup fails.
     */
    public function setup_payment_with_paypal_commerce($txn, array $order_bumps = [], $return_the_object = false)
    {
        $mepr_options  = MeprOptions::fetch();
        $currency_code = strtoupper($mepr_options->currency_code);
        $prd           = $txn->product();
        $sub           = null;

        if (empty($prd->ID)) {
            throw new MeprGatewayException(__('Product not found', 'memberpress'));
        }

        $transactions = array_merge([$txn], $order_bumps);

        foreach ($transactions as $transaction) {
            if (!$transaction->is_one_time_payment()) {
                $subscription = $transaction->subscription();

                if (!($subscription instanceof MeprSubscription)) {
                    throw new MeprGatewayException(__('Subscription not found', 'memberpress'));
                }

                if ($sub instanceof MeprSubscription) {
                    throw new MeprGatewayException(__('Multiple subscriptions are not supported', 'memberpress'));
                }

                $sub = $subscription;
            }

            $transaction->update_meta('is_paypal_commerce', true);
        }

        if ($sub instanceof MeprSubscription) {
            // Presence of a subscription means we need to use the Subscriptions API.
            $has_trial        = $sub->trial && $sub->trial_days > 0;
            $trial_days       = $has_trial ? $sub->trial_days : 0;
            $trial_total      = $has_trial ? (float) $sub->trial_total : 0.00;
            $trial_tax_amount = $has_trial ? (float) $sub->trial_tax_amount : 0.00;
            $convert_to_trial = false;
            $skip_taxes       = false;

            foreach ($transactions as $transaction) {
                if (!$transaction->is_payment_required()) {
                    continue;
                } elseif ($transaction->is_one_time_payment()) {
                    $trial_total      += (float) $transaction->total;
                    $trial_tax_amount += (float) $transaction->tax_amount;

                    if (!$has_trial) {
                        $convert_to_trial = true;
                    }
                }

                if ($txn->tax_rate != $transaction->tax_rate) {
                    // PayPal doesn't let us have a different tax rate for an order bump, so if an order bump has a different
                    // tax rate, we will skip using taxes.
                    $skip_taxes = true;
                }
            }

            // If there is no trial period and there is an order bump, set the trial days to cover one payment cycle and
            // add the first subscription payment to the trial amount.
            if ($convert_to_trial) {
                $now               = new DateTimeImmutable('now');
                $end               = $now->modify(sprintf('+%d %s', $sub->period, $sub->period_type));
                $trial_days        = $end->diff($now)->format('%a');
                $trial_total      += (float) $sub->total;
                $trial_tax_amount += (float) $sub->tax_amount;
            }

            if (!$skip_taxes && get_option('mepr_calculate_taxes') && $mepr_options->attr('tax_calc_type') != 'inclusive') {
                $trial_amount = $trial_total - $trial_tax_amount;
            } else {
                $trial_amount = $trial_total;
            }

            $pp_plan_id      = $this->get_pp_plan_id($sub, $has_trial || $convert_to_trial, $trial_days, $trial_amount, $skip_taxes);
            $pp_subscription = $this->get_pp_subscription($pp_plan_id, $txn, $sub, $return_the_object, $skip_taxes);
            $sub->subscr_id  = $pp_subscription['id'];
            $sub->store();

            if (isset($pp_subscription['links']) && is_array($pp_subscription['links'])) {
                foreach ($pp_subscription['links'] as $link) {
                    if (isset($link['rel']) && $link['rel'] == 'approve' && isset($link['href'])) {
                        if ($return_the_object) {
                            return [
                                'id'     => $pp_subscription['id'],
                                'txn_id' => $txn->id,
                            ];
                        }

                          $this->log($link['href']);
                          MeprUtils::wp_redirect($link['href']);
                    }
                }
            }

            throw new MeprGatewayException(__('Could not create PayPal subscription', 'memberpress'));
        } else {
            $items  = [];
            $amount = 0.00;
            $tax    = 0.00;
            $total  = 0.00;

            foreach ($transactions as $transaction) {
                $product = $transaction->product();

                if (empty($product->ID)) {
                    throw new MeprGatewayException(__('Product not found', 'memberpress'));
                }

                $items[] = [
                    'name'        => $product->post_title,
                    'unit_amount' => [
                        'currency_code' => $currency_code,
                        'value'         => (string) $transaction->amount,
                    ],
                    'tax'         => [
                        'currency_code' => $currency_code,
                        'value'         => (string) $transaction->tax_amount,
                    ],
                    'quantity'    => 1,
                ];

                $amount += (float) $transaction->amount;
                $tax    += (float) $transaction->tax_amount;
                $total  += (float) $transaction->total;
            }

            $payload = [
                'intent'              => 'CAPTURE',
                'purchase_units'      => [
                    [
                        'custom_id'   => $txn->id,
                        'description' => $prd->post_title,
                        'items'       => $items,
                        'amount'      => [
                            'currency_code' => $currency_code,
                            'value'         => (string) $total,
                            'breakdown'     => [
                                'item_total' => [
                                    'currency_code' => $currency_code,
                                    'value'         => (string) $amount,
                                ],
                                'tax_total'  => [
                                    'currency_code' => $currency_code,
                                    'value'         => (string) $tax,
                                ],
                            ],
                        ],
                    ],
                ],
                'application_context' => [
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action'         => 'PAY_NOW',
                    'return_url'          => add_query_arg(['txn_id' => $txn->id], $this->notify_url('return')),
                    'cancel_url'          => $this->notify_url('cancel'),
                ],
            ];

            $payload = json_encode(MeprHooks::apply_filters('mepr_paypal_onetime_subscription_args', $payload, $txn), JSON_UNESCAPED_SLASHES);

            $response = wp_remote_post($this->settings->rest_api_url . '/v2/checkout/orders', [
                'body'    => $payload,
                'headers' => [
                    'Content-Type'                  => 'application/json',
                    'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                    'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
                ],
            ]);

            $code     = wp_remote_retrieve_response_code($response);
            $response = json_decode(wp_remote_retrieve_body($response), true);

            $this->log($payload);
            $this->log($response);

            if (($code < 200 || $code > 299) || !is_array($response) || !isset($response['links']) || !is_array($response['links'])) {
                throw new MeprGatewayException(__('Could not create PayPal Order', 'memberpress'));
            }

            foreach ($response['links'] as $link) {
                if (isset($link['rel']) && $link['rel'] == 'approve' && isset($link['href'])) {
                    if ($return_the_object) {
                        return [
                            'id'     => $response['id'],
                            'txn_id' => $txn->id,
                        ];
                    }

                    MeprUtils::wp_redirect(esc_url_raw(add_query_arg('Return', 'TRUE', $link['href'])));
                }
            }

            throw new MeprGatewayException(__('Could not create PayPal Order', 'memberpress'));
        }
    }

    /**
     * Returns the payment form and required fields for the gateway
     *
     * @return string The payment form.
     */
    public function spc_payment_fields()
    {
        $payment_method = $this;

        return MeprView::get_string('/checkout/MeprPayPalCommerceGateway/payment_form', get_defined_vars());
    }

    /**
     * This gets called on the_content and just renders the payment form
     *
     * @param  float    $amount         The amount to charge.
     * @param  MeprUser $user           The user object.
     * @param  integer  $product_id     The product ID.
     * @param  string   $transaction_id The transaction ID.
     * @return void
     */
    public function display_payment_form($amount, $user, $product_id, $transaction_id)
    {
        $payment_method = $this;
        ?>
    <div class="mp_wrapper mp_payment_form_wrapper">
      <form action="" method="post" id="mepr-paypal-payment-form">
        <input type="hidden" name="mepr_process_payment_form" value="Y">
        <input type="hidden" name="mepr_transaction_id" value="<?php echo esc_attr($transaction_id); ?>">
        <?php
          echo MeprView::get_string('/checkout/MeprPayPalCommerceGateway/payment_form', get_defined_vars());
        ?>
        <div class="mepr_spacer">&nbsp;</div>
        <input type="submit" class="mepr-submit" value="<?php _ex('Submit', 'ui', 'memberpress'); ?>"/>
      </form>
    </div>
        <?php
    }

    /**
     * Validates the payment form before a payment is processed
     *
     * @param  array $errors The errors array.
     * @return void
     */
    public function validate_payment_form($errors)
    {
        // PayPal does this on their own form.
    }

    /**
     * Process the payment form
     *
     * This method is called when the Single Page Checkout option is enabled. When the option is disabled,
     * display_payment_page handles the processing.
     *
     * @param  MeprTransaction $txn The transaction object.
     * @return void
     * @throws MeprGatewayException The gateway exception.
     * @throws Exception The exception.
     */
    public function process_payment_form($txn)
    {
        $order_bump_product_ids = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_map('intval', $_POST['mepr_order_bumps']) : [];
        $order_bump_products    = MeprCheckoutCtrl::get_order_bump_products($txn->product_id, $order_bump_product_ids);

        $order_bumps = $this->process_order($txn, $order_bump_products);

        $this->setup_payment_with_paypal_commerce($txn, $order_bumps);
    }

    /**
     * Retrieves the PayPal product ID for a given product.
     *
     * @param MeprProduct $product The product object.
     *
     * @return string The PayPal product ID.
     * @throws MeprGatewayException If the PayPal product cannot be created.
     */
    public function get_pp_product_id(MeprProduct $product)
    {
        $args = [
            'name' => $product->post_title,
            'type' => 'SERVICE',
        ];

        $meta_key  = '_mepr_paypal_product_';
        $meta_key .= $this->id . '_';

        if ($this->is_test_mode()) {
            $meta_key .= 'test_';
        }

        $meta_key     .= implode('_', $args);
        $meta_key      = sanitize_title($meta_key);
        $pp_product_id = get_post_meta($product->ID, $meta_key, true);

        if (empty($pp_product_id)) {
            // Create new pp product id.
            $response_raw = wp_remote_post($this->settings->rest_api_url . '/v1/catalogs/products', [
                'headers' => [
                    'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
                    'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                    'Content-Type'                  => 'application/json',
                ],
                'body'    => json_encode($args),
            ]);

            $response = json_decode(wp_remote_retrieve_body($response_raw), true);

            if (isset($response['id'])) {
                $pp_product_id = $response['id'];
                update_post_meta($product->ID, $meta_key, $response['id']);

                return $pp_product_id;
            } else {
                $this->log($response_raw);
                throw new MeprGatewayException(__('Could not create PayPal product', 'memberpress'));
            }
        }

        return $pp_product_id;
    }

    /**
     * Retrieves the PayPal subscription object.
     *
     * @param string           $pp_plan_id         The PayPal plan ID.
     * @param MeprTransaction  $txn                The transaction object.
     * @param MeprSubscription $sub                The subscription object.
     * @param boolean          $return_the_request Whether to return the request.
     * @param boolean          $skip_taxes         Whether to skip taxes.
     *
     * @return array|WP_Error The PayPal subscription object or an error.
     * @throws MeprGatewayException If the PayPal subscription cannot be created.
     */
    public function get_pp_subscription($pp_plan_id, $txn, MeprSubscription $sub, $return_the_request = false, $skip_taxes = false)
    {
        $mepr_options = MeprOptions::fetch();

        $args = [
            'plan_id'             => $pp_plan_id,
            'custom_id'           => $sub->id,
            'application_context' => [
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'SUBSCRIBE_NOW',
                'cancel_url'          => $this->notify_url('cancel'),
                'return_url'          => add_query_arg(['txn_id' => $txn->id], $this->notify_url('return')),
            ],
        ];

        if (get_option('mepr_calculate_taxes') && !$skip_taxes) {
            if ($mepr_options->attr('tax_calc_type') == 'inclusive') {
                $tax_inclusive = true;
            } else {
                $tax_inclusive = false;
            }

            $args['plan'] = [
                'taxes' => [
                    'percentage' => $sub->tax_rate,
                    'inclusive'  => $tax_inclusive,
                ],
            ];
        }

        $args     = MeprHooks::apply_filters('mepr_paypal_subcription_args', $args, $sub);
        $options  = [
            'headers' => [
                'Content-Type'                  => 'application/json',
                'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
            ],
            'body'    => json_encode($args, JSON_UNESCAPED_SLASHES),
        ];
        $response = wp_remote_post($this->settings->rest_api_url . '/v1/billing/subscriptions', $options);

        $raw      = wp_remote_retrieve_body($response);
        $response = json_decode($raw, true);

        if ($return_the_request) {
            return $response;
        }

        if (isset($response['id'])) {
            return $response;
        } else {
            $this->log($options);
            $this->log($raw);
            throw new MeprGatewayException(__('Could not create PayPal subscription', 'memberpress'));
        }
    }

    /**
     * Retrieves the PayPal plan ID for a given subscription.
     *
     * @param  MeprSubscription $sub          The subscription object.
     * @param  boolean          $trial        Whether the subscription has a trial period.
     * @param  boolean          $trial_days   The number of trial days.
     * @param  float            $trial_amount The trial amount.
     * @param  boolean          $skip_taxes   Whether to skip taxes.
     * @return string The PayPal plan ID.
     * @throws MeprGatewayException If the PayPal plan cannot be created.
     */
    public function get_pp_plan_id(MeprSubscription $sub, $trial = false, $trial_days = 0, $trial_amount = 0.00, $skip_taxes = false)
    {
        $product      = $sub->product();
        $mepr_options = MeprOptions::fetch();

        if ($mepr_options->attr('tax_calc_type') == 'inclusive' || $skip_taxes) {
            $tax_inclusive = true;
            $amount        = round($sub->total, 2);
        } else {
            $tax_inclusive = false;
            $amount        = round($sub->total - $sub->tax_amount, 2);
        }

        $interval = 'day';

        if ($sub->period_type == 'months') {
            $interval = 'month';
        } elseif ($sub->period_type == 'years') {
            $interval = 'year';
        } elseif ($sub->period_type == 'weeks') {
            $interval = 'week';
        }

        $sub_period = $sub->period;

        if ($interval == 'day' && $sub_period > 365) {
            $sub_period = 365; // Truncate at 365 even for a leap year.
        }

        if ($trial_days > 365) {
            $trial_days = 365; // Truncate at 365 even for a leap year.
        }

        $args = [
            'amount'                => $amount,
            'method_id'             => $this->id,
            'tax_rate'              => $skip_taxes ? 'na' : $sub->tax_rate,
            'tax_inclusive'         => $skip_taxes ? 'na' : ($tax_inclusive ? 'yes' : 'no'),
            'test'                  => $this->is_test_mode() ? 'test' : 'live',
            'interval'              => $interval,
            'period'                => $sub_period,
            'trial'                 => $trial ? 'yes' : 'no',
            'trial_total'           => $trial_amount,
            'total_cycles'          => $sub->limit_cycles_num,
            'trial_days'            => $trial_days,
            'interval_count'        => $sub_period,
            'currency'              => $mepr_options->currency_code,
            'memberpress_product_id' => $product->ID,
        ];

        $plan_meta_key = '_mepr_paypal_plan_' . implode('_', $args);
        $plan_id       = get_post_meta($product->ID, $plan_meta_key, true);

        if (empty($plan_id)) {
            $billing_cycles  = [];
            $sequence_number = 1;

            if ($trial) {
                $billing_cycles[] = [
                    'frequency'      => [
                        'interval_unit'  => 'DAY',
                        'interval_count' => $args['trial_days'],
                    ],
                    'pricing_scheme' => [
                        'fixed_price' => [
                            'currency_code' => $mepr_options->currency_code,
                            'value'         => (string) $trial_amount,
                        ],
                    ],
                    'tenure_type'    => 'TRIAL',
                    'sequence'       => $sequence_number,
                    'total_cycles'   => 1,
                ];

                $sequence_number++;
            }

            $billing_cycles[] = [
                'frequency'      => [
                    'interval_unit'  => strtoupper($interval),
                    'interval_count' => intval($sub_period),
                ],
                'pricing_scheme' => [
                    'fixed_price' => [
                        'currency_code' => $mepr_options->currency_code,
                        'value'         => (string) $amount,
                    ],
                ],
                'tenure_type'    => 'REGULAR',
                'sequence'       => $sequence_number,
                'total_cycles'   => ( $sub->limit_cycles && $sub->limit_cycles_num >= 1 ) ? $sub->limit_cycles_num : 0,
            ];

            $request_args = [
                'product_id'          => $this->get_pp_product_id($product),
                'name'                => $product->post_title,
                'status'              => 'ACTIVE',
                'billing_cycles'      => $billing_cycles,
                'payment_preferences' => [
                    'auto_bill_outstanding'     => true,
                    'payment_failure_threshold' => 3,
                ],
            ];

            if (!$skip_taxes) {
                $request_args['taxes'] = [
                    'percentage' => $sub->tax_rate,
                    'inclusive'  => $tax_inclusive,
                ];
            }

            $request_args = MeprHooks::apply_filters('mepr_paypal_plan_args', $request_args, $sub);
            $this->log('Sending to pp plan' . print_r($request_args, true));

            $options  = [
                'headers' => [
                    'Content-Type'                  => 'application/json',
                    'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                    'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
                ],
                'body'    => wp_json_encode($request_args),
            ];
            $response = wp_remote_post($this->settings->rest_api_url . '/v1/billing/plans', $options);
            $raw      = wp_remote_retrieve_body($response);
            $response = json_decode($raw, true);

            if (isset($response['id'])) {
                $this->log($options);
                $plan_id = $response['id'];
                update_post_meta($product->ID, $plan_meta_key, $plan_id);

                return $plan_id;
            } else {
                $this->log($request_args);
                $this->log($options);
                $this->log($raw);
                $this->log($response);
                throw new MeprGatewayException(__('Could not create Plan', 'memberpress'));
            }
        }

        return $plan_id;
    }

    /**
     * Displays the form for the given payment gateway on the MemberPress Options page
     */
    public function display_options_form()
    {
        $mepr_options           = MeprOptions::fetch();
        $pm                     = $this;
        $upgraded_from_standard = false;

        if (isset($mepr_options->legacy_integrations[ $this->id ])) {
            $upgraded_from_standard = true;
        }

        $debug           = defined('WP_MEPR_DEBUG') && WP_MEPR_DEBUG === true;
        $settings        = $this->settings;
        $buffer_settings = get_option('mepr_buff_integrations', []);

        if (isset($buffer_settings[ $this->id ])) {
            foreach (['test_merchant_id', 'live_merchant_id', 'test_email_confirmed', 'live_email_confirmed'] as $key) {
                if (isset($buffer_settings[ $this->id ][ $key ])) {
                    $settings->{$key}                                = $buffer_settings[ $this->id ][ $key ];
                    $mepr_options->integrations[ $this->id ][ $key ] = $buffer_settings[ $this->id ][ $key ];
                }
            }
        }

        $test_client_id_str                     = "{$mepr_options->integrations_str}[{$this->id}][test_client_id]";
        $test_client_secret_str                 = "{$mepr_options->integrations_str}[{$this->id}][test_client_secret]";
        $live_client_id_str                     = "{$mepr_options->integrations_str}[{$this->id}][live_client_id]";
        $live_client_secret_str                 = "{$mepr_options->integrations_str}[{$this->id}][live_client_secret]";
        $test_webhook_id_str                    = "{$mepr_options->integrations_str}[{$this->id}][test_webhook_id]";
        $live_webhook_id_str                    = "{$mepr_options->integrations_str}[{$this->id}][live_webhook_id]";
        $test_merchant_id_str                   = "{$mepr_options->integrations_str}[{$this->id}][test_merchant_id]";
        $live_merchant_id_str                   = "{$mepr_options->integrations_str}[{$this->id}][live_merchant_id]";
        $test_email_confirmed_str               = "{$mepr_options->integrations_str}[{$this->id}][test_email_confirmed]";
        $live_email_confirmed_str               = "{$mepr_options->integrations_str}[{$this->id}][live_email_confirmed]";
        $enable_smart_button_str                = "{$mepr_options->integrations_str}[{$this->id}][enable_smart_button]";
        $enable_paypal_standard_debug_email_str = "{$mepr_options->integrations_str}[{$this->id}][enable_paypal_standard_debug_email]";

        $account_email                      = get_option('mepr_authenticator_account_email');
        $secret                             = get_option('mepr_authenticator_secret_token');
        $site_uuid                          = get_option('mepr_authenticator_site_uuid');
        $payment_id                         = $this->id;
        $enable_smart_button                = $settings->enable_smart_button == 'on';
        $enable_paypal_standard_debug_email = $settings->enable_paypal_standard_debug_email == 'on';
        if ($account_email && $secret && $site_uuid) {
            $paypal_connect_url_sandbox = self::get_paypal_connect_url($this->id, true);
            $paypal_connect_url         = self::get_paypal_connect_url($this->id);
        } else {
            $memberpress_connect_url = MeprAuthenticatorCtrl::get_auth_connect_url(false, $this->id, [
                'paypal_connect' => true,
                'method_id'      => $this->id,
            ]);
        }

        $base_return_url = add_query_arg(
            [
                'action'   => 'mepr_paypal_connect_update_creds',
                '_wpnonce' => wp_create_nonce('paypal-update-creds'),
            ],
            admin_url('admin-ajax.php')
        );

        $base_return_url_sandbox = add_query_arg(
            [
                'action'   => 'mepr_paypal_connect_update_creds_sandbox',
                '_wpnonce' => wp_create_nonce('paypal-update-creds'),
            ],
            admin_url('admin-ajax.php')
        );
        $paypal_js_url           = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';
        MeprView::render('/admin/gateways/paypal/connect-migrate-prompt', get_defined_vars());
    }

    /**
     * Validates the form for the given payment gateway on the MemberPress Options page
     *
     * @param  array $errors The errors array.
     * @return array The errors array.
     */
    public function validate_options_form($errors)
    {
        $mepr_options = MeprOptions::fetch();

        return $errors;
    }

    /**
     * Displays the update account form on the subscription account page
     *
     * @param  integer $sub_id  The subscription ID.
     * @param  array   $errors  The errors array.
     * @param  string  $message The message.
     * @return void
     */
    public function display_update_account_form($sub_id, $errors = [], $message = '')
    {
        ?>
    <h3><?php _e('Updating your PayPal Account Information', 'memberpress'); ?></h3>
    <div>
        <?php printf(
        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
            __('To update your PayPal Account Information, please go to %1$sPayPal.com%2$s, login and edit your account information there.', 'memberpress'),
            '<a href="http://paypal.com" target="blank">',
            '</a>'
        ); ?>
    </div>
        <?php
    }

    /**
     * IPN listener.
     *
     * @return boolean
     */
    public function ipn_listener()
    {
        $_POST = wp_unslash($_POST);
        $this->log('IPN received');
        $this->log($_POST);
        do_action('mepr_paypal_commerce_ipn_listener_preprocess');
        $this->email_status("PayPal IPN Recieved\n" . MeprUtils::object_to_string($_POST, true) . "\n", $this->debug);

        if ($this->validate_ipn()) {
            $mepr_options = MeprOptions::fetch();

            if (! isset($mepr_options->legacy_integrations[ $this->id ])) {
                return false;
            }

            $standard_gateway                                        = new MeprPayPalStandardGateway();
            $mepr_options->legacy_integrations[ $this->id ]['debug'] = $this->debug;
            $standard_gateway->load($mepr_options->legacy_integrations[ $this->id ]);

            return $standard_gateway->process_ipn();
        }

        return false;
    }

    /**
     * Handle webhook events.
     *
     * @return void
     */
    public function webhook_handler()
    {
        $request = @file_get_contents('php://input');
        $request = json_decode($request, true);
        $this->log('Webhook received');
        $this->log($request);

        if (! isset($request['event_type'])) {
            return;
        }

        if ($request['event_type'] == 'BILLING.SUBSCRIPTION.ACTIVATED') {
            // Only free trials are handled here.
            if (isset($request['resource']['id'], $request['resource']['custom_id'], $request['resource']['billing_info']) && !isset($request['resource']['billing_info']['last_payment'])) {
                $sub = new MeprSubscription((int) $request['resource']['custom_id']);

                if ($sub->id > 0 && $sub->gateway == $this->id && $sub->trial && $sub->trial_days > 0 && $sub->trial_amount <= 0.00) {
                    // Check if this is a multi-item purchase.
                    $txn = $sub->first_txn();

                    if ($txn instanceof MeprTransaction) {
                        $order                   = $txn->order();
                        $order_bump_transactions = $order instanceof MeprOrder ? MeprTransaction::get_all_by_order_id_and_gateway($order->id, $this->id, $txn->id) : [];

                        if ($order instanceof MeprOrder && count($order_bump_transactions)) {
                            foreach ($order_bump_transactions as $transaction) {
                                if ($transaction->is_payment_required()) {
                                          // If any order bump required payment, we don't want to record subscription creation here.
                                          // It will be handled by the PAYMENT.SALE.COMPLETED webhook below.
                                          return;
                                }
                            }

                              // If we reach here, payment was not required for any order bump, create free transactions for any free product.
                            foreach ($order_bump_transactions as $transaction) {
                                if (!$transaction->is_payment_required()) {
                                    MeprTransaction::create_free_transaction($transaction, false, sprintf('mi_%d_%s', $order->id, uniqid()));
                                }
                            }
                        }
                    }

                    $this->handle_create_subscription($sub, $request['resource']['id']);
                }
            }
        } elseif ($request['event_type'] == 'BILLING.SUBSCRIPTION.CANCELLED' || $request['event_type'] == 'BILLING.SUBSCRIPTION.EXPIRED') {
            $_REQUEST['subscr_id'] = $request['resource']['id'];
            $this->record_cancel_subscription();
        } elseif ($request['event_type'] == 'BILLING.SUBSCRIPTION.SUSPENDED') {
            $_REQUEST['subscr_id'] = $request['resource']['id'];
            $this->record_suspend_subscription();
        } elseif ($request['event_type'] == 'PAYMENT.CAPTURE.DENIED') {
            $_POST['txn_id'] = $request['resource']['id'];
            $this->record_payment_failure();
        } elseif ($request['event_type'] == 'PAYMENT.SALE.REFUNDED') {
            $txn_num                = $request['resource']['sale_id'];
            $existing_txn           = MeprTransaction::get_one_by_trans_num($txn_num);
            $_POST['parent_txn_id'] = $existing_txn->id;
            $this->record_refund();
        } elseif (in_array($request['event_type'], ['PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REFUNDED'])) {
            $links   = $request['resource']['links'];
            $txn_num = '';

            foreach ($links as $link) {
                if ($link['rel'] == 'up') {
                    $href    = explode('/', $link['href']);
                    $txn_num = array_pop($href);
                }
            }

            $existing_txn           = MeprTransaction::get_one_by_trans_num($txn_num);
            $_POST['parent_txn_id'] = $existing_txn->id;
            $this->record_refund();
        } elseif ($request['event_type'] == 'PAYMENT.SALE.COMPLETED') {
            $pp_payment = $this->get_paypal_sale_payment_object($request['resource']['id']);
            $resource   = $request['resource'];
            $this->log('Processing recurring payment');
            $this->log($pp_payment);
            $this->log($resource);

            if ($pp_payment['status'] == 'COMPLETED' && isset($pp_payment['custom_id'])) {
                $this->log('Payment confirmed');

                $_POST['txn_id']       = $pp_payment['id'];
                $_POST['mc_gross']     = $resource['amount']['total'];
                $_POST['payment_date'] = $resource['create_time'];
                $_POST['subscr_id']    = $_POST['recurring_payment_id'] = $resource['billing_agreement_id'];

                // First see if the subscription has already been set up with the correct I- or S- number.
                $sub = MeprSubscription::get_one_by_subscr_id($resource['billing_agreement_id']);

                // If no $sub at this point it's safe to assume this is a new signup so let's get the $sub from the custom_id instead.
                if (!($sub instanceof MeprSubscription)) {
                    $sub = new MeprSubscription($pp_payment['custom_id']);
                    $txn = $sub->first_txn();
                    if ($sub->id > 0 && $sub->gateway == $this->id && $txn instanceof MeprTransaction && $txn->id > 0) {
                        // Check if this is a multi-item purchase.
                        $order                   = $txn->order();
                        $order_bump_transactions = $order instanceof MeprOrder ? MeprTransaction::get_all_by_order_id_and_gateway($order->id, $this->id, $txn->id) : [];

                        if ($order instanceof MeprOrder && count($order_bump_transactions)) {
                            if (!$order->is_complete() && !$order->is_processing()) {
                                $order->update_meta('processing', true);
                                $transactions = array_merge([$txn], $order_bump_transactions);

                                foreach ($transactions as $transaction) {
                                          $trans_num = sprintf('mi_%d_%s', $order->id, uniqid());

                                    if (!$transaction->is_payment_required()) {
                                        MeprTransaction::create_free_transaction($transaction, false, $trans_num);
                                    } elseif ($transaction->is_one_time_payment()) {
                                        $this->handle_one_time_payment($transaction, $trans_num);
                                    } else {
                                        $subscription = $txn->subscription();

                                        if (!($subscription instanceof MeprSubscription)) {
                                                  continue;
                                        }

                                        $this->handle_create_subscription($subscription, $resource['billing_agreement_id']);

                                        if ($subscription->trial && $subscription->trial_days > 0) {
                                            if ($subscription->trial_total > 0) {
                                                $_POST['mc_gross'] = $subscription->trial_total;
                                            } else {
                                                    continue; // Initial payment for a free trial with order bump, we don't want to record a subscription transaction here.
                                            }
                                        } else {
                                                        $_POST['mc_gross'] = $subscription->total;
                                        }

                                        $_POST['txn_id']        = $trans_num;
                                        $_POST['mepr_order_id'] = $order->id;

                                        $this->record_subscription_payment();
                                    }
                                }

                                $order->trans_num = $pp_payment['id'];
                                $order->status    = MeprOrder::$complete_str;
                                $order->store();
                                $order->delete_meta('processing');
                            }

                              return;
                        }
                    }
                }

                if ($sub instanceof MeprSubscription && $sub->id > 0) {
                    $this->record_subscription_payment();
                }
            }
        }
    }

    /**
     * Validates the payment form before a payment is processed
     *
     * @param  array $errors The errors array.
     * @return void
     */
    public function validate_update_account_form($errors = [])
    {
        // We'll have them update their cc info on paypal.com.
    }

    /**
     * Actually pushes the account update to the payment processor
     *
     * @param  integer $sub_id The subscription ID.
     * @return void
     */
    public function process_update_account_form($sub_id)
    {
        // We'll have them update their cc info on paypal.com.
    }

    /**
     * Returns boolean ... whether or not we should be sending in test mode or not
     */
    public function is_test_mode()
    {
        if ($this->is_paypal_connected() && ! $this->is_paypal_connected_live()) {
            return true;
        }

        if ($this->is_paypal_connected_live()) {
            return false;
        }

        return true;
    }

    /**
     * Checks if the PayPal connection is active.
     *
     * @return boolean
     */
    public function is_paypal_connected()
    {
        return ! empty($this->settings->test_client_id);
    }

    /**
     * Checks if the PayPal connection is live.
     *
     * @return boolean
     */
    public function is_paypal_connected_live()
    {
        return ! empty($this->settings->live_client_id);
    }

    /**
     * Checks if the PayPal email is confirmed for test mode.
     *
     * @return boolean
     */
    public function is_paypal_email_confirmed()
    {
        return ! empty($this->settings->test_email_confirmed);
    }

    /**
     * Checks if the PayPal email is confirmed for live mode.
     *
     * @return boolean
     */
    public function is_paypal_email_confirmed_live()
    {
        return ! empty($this->settings->live_email_confirmed);
    }

    /**
     * Force SSL.
     *
     * @return boolean
     */
    public function force_ssl()
    {
        return false; // Redirects off site where ssl is installed.
    }

    /**
     * Checks whether the user has a Paypal payment method that uses Paypal Connect
     *
     * @param  string $status The status of the payment method.
     * @return boolean
     */
    public static function has_method_with_connect_status($status = 'connected')
    {
        $mepr_options = MeprOptions::fetch();
        foreach ($mepr_options->integrations as $integration) {
            if (! isset($integration['gateway']) || 'MeprPayPalCommerceGateway' !== $integration['gateway']) {
                continue;
            }

            return ! empty($integration['test_client_id']) || ! empty($integration['live_client_id']);
        }

        return false;
    }

    /**
     * Update a PayPal payment profile.
     *
     * @param  integer $sub_id The subscription ID.
     * @param  string  $action The action to take.
     * @return void
     * @throws MeprGatewayException When the PayPal API request fails.
     */
    private function update_paypal_payment_profile($sub_id, $action = 'cancel')
    {
        $action = strtolower($action);
        $sub    = new MeprSubscription($sub_id);

        $options = [
            'headers' => [
                'Content-Type'                  => 'application/json',
                'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
            ],
            'body'    => json_encode([
                'reason' => esc_html(__('On request', 'memberpress')),
            ], JSON_UNESCAPED_SLASHES),
        ];

        if ($action == 'reactivate') {
            $action = 'activate';
        }

        $this->log($options);
        $endpoint = $this->settings->rest_api_url . '/v1/billing/subscriptions/' . $sub->subscr_id . '/' . $action;
        $this->log($endpoint);

        $response      = wp_remote_post($endpoint, $options);
        $response_code = wp_remote_retrieve_response_code($response);

        $this->log($response_code);

        if ($response_code < 200 || $response_code >= 300) {
            throw new MeprGatewayException(__('There was a problem, try logging in directly at PayPal to update the status of your recurring profile.', 'memberpress'));
        }

        $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
    }

    /**
     * Return handler.
     *
     * @return void
     * @throws MeprGatewayException When subscription or order cannot be processed properly.
     */
    public function return_handler()
    {
        $this->email_status("Paypal Return \$_REQUEST:\n" . MeprUtils::object_to_string($_REQUEST, true) . "\n", $this->debug);
        $mepr_options = MeprOptions::fetch();

        $transaction_id = isset($_GET['txn_id']) ? (int) sanitize_text_field(wp_unslash($_GET['txn_id'])) : 0;
        $txn            = new MeprTransaction($transaction_id);

        if (empty($txn->id)) {
            return;
        }

        if (empty($txn->get_meta('is_paypal_commerce'))) {
            if (!isset($mepr_options->legacy_integrations[$this->id])) {
                return;
            }

            $standard_gateway = new MeprPayPalStandardGateway();
            $standard_gateway->load($mepr_options->legacy_integrations[$this->id]);
            $standard_gateway->return_handler();

            return;
        }

        try {
            $prd = $txn->product();

            $thankyou_page_args = [
                'membership'     => sanitize_title($prd->post_title),
                'membership_id'  => $prd->ID,
                'transaction_id' => $txn->id,
            ];

            $subscription_id = isset($_GET['subscription_id']) ? sanitize_text_field(wp_unslash($_GET['subscription_id'])) : '';

            // Check if this is a multi-item purchase.
            $order                   = $txn->order();
            $order_bump_transactions = $order instanceof MeprOrder ? MeprTransaction::get_all_by_order_id_and_gateway($order->id, $this->id, $txn->id) : [];
            $transactions            = array_merge([$txn], $order_bump_transactions);

            if (!empty($subscription_id)) {
                // Return from a subscription creation.
                $subscription = $this->get_paypal_subscription_object($subscription_id);

                if (!isset($subscription['id'], $subscription['status'])) {
                    throw new MeprGatewayException(__('Subscription not found', 'memberpress'));
                }

                if (
                    ($subscription['status'] == 'ACTIVE' && $subscription['billing_info']['next_billing_time']) ||
                    ($subscription['status'] == 'EXPIRED' && $subscription['billing_info']['cycle_executions'])
                ) {
                    // The subscription may have been an order bump, so check all transactions for this order (there will only be one sub).
                    foreach ($transactions as $transaction) {
                        $sub = $transaction->subscription();

                        if ($sub instanceof MeprSubscription && $sub->id > 0) {
                              $this->handle_create_subscription($sub, $sub->subscr_id);
                        }
                    }
                } else {
                    throw new MeprGatewayException(__('Subscription not found', 'memberpress'));
                }

                if (!empty($txn->subscription_id)) {
                    $thankyou_page_args = array_merge($thankyou_page_args, ['subscription_id' => $txn->subscription_id]);
                }
            } else {
                // Return from a one-time payment.
                $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

                if (!empty($token)) {
                    $pp_order = $this->capture_paypal_commerce_order($token);

                    if (isset($pp_order['status']) && in_array($pp_order['status'], ['COMPLETED', 'PENDING'], true)) {
                        $trans_num = !empty($pp_order['purchase_units'][0]['payments']['captures'][0]['id']) ? $pp_order['purchase_units'][0]['payments']['captures'][0]['id'] : $token;

                        if ($order instanceof MeprOrder && count($order_bump_transactions)) {
                            if (!$order->is_complete() && !$order->is_processing()) {
                                $order->update_meta('processing', true);
                                $transactions = array_merge([$txn], $order_bump_transactions);

                                foreach ($transactions as $transaction) {
                                    if (!$transaction->is_payment_required()) {
                                          MeprTransaction::create_free_transaction($transaction, false, sprintf('mi_%d_%s', $order->id, uniqid()));
                                    } elseif ($transaction->is_one_time_payment()) {
                                        $this->handle_one_time_payment($transaction, sprintf('mi_%d_%s', $order->id, uniqid()));
                                    }
                                }

                                $order->trans_num = $trans_num;
                                $order->status    = MeprOrder::$complete_str;
                                $order->store();
                                $order->delete_meta('processing');
                            }
                        } else {
                            $this->handle_one_time_payment($txn, $trans_num);
                        }
                    } elseif (isset($pp_order['details']) && is_array($pp_order['details'])) {
                        if ($pp_order['details'][0]['issue'] != 'ORDER_ALREADY_CAPTURED') {
                            foreach ($transactions as $transaction) {
                                $transaction->status = MeprTransaction::$failed_str;
                                $transaction->store();
                            }

                            throw new MeprGatewayException(__('Payer has not yet approved the Order for payment', 'memberpress'));
                        }
                    } else {
                        throw new MeprGatewayException(__('Order could not be captured', 'memberpress'));
                    }
                } else {
                    return;
                }
            }

            MeprUtils::wp_redirect(esc_url_raw($mepr_options->thankyou_page_url($thankyou_page_args)));
        } catch (Exception $e) {
            $product     = $txn->product();
            $product_url = MeprUtils::get_permalink($product->ID);
            $product_url = !empty($product_url) ? $product_url : home_url();

            MeprUtils::wp_redirect(esc_url_raw(add_query_arg([
                'errors' => $e->getMessage(),
            ], $product_url)));
        }
    }

    /**
     * Cancel handler.
     *
     * @return void
     */
    public function cancel_handler()
    {
        $mepr_options = MeprOptions::fetch();
        // Handled with a GET REQUEST by PayPal.
        $this->email_status("Paypal Cancel \$_REQUEST:\n" . MeprUtils::object_to_string($_REQUEST, true) . "\n", $this->debug);

        MeprHooks::do_action('mepr_paypal_checkout_cancelled_before', $_REQUEST);

        if (isset($_REQUEST['txn_id']) && is_numeric($_REQUEST['txn_id'])) {
            $txn = new MeprTransaction($_REQUEST['txn_id']);

            // Make sure the txn status is pending.
            $txn->status = MeprTransaction::$pending_str;
            $txn->store();

            $sub = $txn->subscription();
            if ($sub) {
                $sub->status = MeprSubscription::$pending_str;
                $sub->store();
            }

            if (isset($txn->product_id) && $txn->product_id > 0) {
                $prd = new MeprProduct($txn->product_id);
                MeprUtils::wp_redirect($this->message_page_url($prd, 'cancel'));
            }
        }

        if (isset($_REQUEST['subscription_id'])) {
            $sub = MeprSubscription::get_one_by_subscr_id($_REQUEST['subscription_id']);
            $prd = $sub->product();
            MeprUtils::wp_redirect($this->message_page_url($prd, 'cancel'));
        }

        // If all else fails, just send them to their account page.
        MeprUtils::wp_redirect($mepr_options->account_page_url('action=subscriptions'));
    }

    /**
     * Cancel message.
     *
     * @return void
     */
    public function cancel_message()
    {
        $mepr_options = MeprOptions::fetch();
        ?>
    <h4><?php _e('Your payment at PayPal was cancelled.', 'memberpress'); ?></h4>
    <p><?php echo MeprHooks::apply_filters('mepr_paypal_cancel_message', sprintf(
        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
        __('You can retry your purchase by %1$sclicking here%2$s.', 'memberpress'),
        '<a href="' . MeprUtils::get_permalink() . '">',
        '</a>'
    )); ?>
      <br/></p>
        <?php
    }

    /**
     * Capture a PayPal Commerce order.
     *
     * @param  string $pp_order_id The PayPal order ID.
     * @return array The response from the PayPal API.
     */
    protected function capture_paypal_commerce_order($pp_order_id)
    {
        $response = wp_remote_post($this->settings->rest_api_url . '/v2/checkout/orders/' . $pp_order_id . '/capture', [
            'headers' => [
                'Content-Type'                  => 'application/json',
                'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
                'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
            ],
        ]);

        $response = json_decode(wp_remote_retrieve_body($response), true);

        return $response;
    }
}
