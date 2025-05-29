<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

#[AllowDynamicProperties]
class MeprPayPalStandardGateway extends MeprBasePayPalGateway
{
    /**
     * Used in the view to identify the gateway
     */
    public function __construct()
    {
        $this->name         = 'PayPal Standard';
        $this->key          = 'paypal';
        $this->has_spc_form = true;
        $this->set_defaults();

        // Setup the notification actions for this gateway.
        $this->notifiers     = [
            'ipn'    => 'listener',
            'cancel' => 'cancel_handler',
            'return' => 'return_handler',
        ];
        $this->message_pages = ['cancel' => 'cancel_message'];
    }

    /**
     * Load the gateway settings.
     *
     * @param array $settings The settings to load.
     *
     * @return void
     */
    public function load($settings)
    {
        $this->settings = (object)$settings;
        $this->set_defaults();
    }

    /**
     * Set default settings for the gateway.
     *
     * @return void
     */
    protected function set_defaults()
    {
        if (!isset($this->settings)) {
            $this->settings = [];
        }

        $this->settings =
        (object)array_merge(
            [
                'gateway'       => 'MeprPayPalStandardGateway',
                'id'            => $this->generate_id(),
                'label'         => '',
                'use_label'     => true,
                'icon'          => MEPR_IMAGES_URL . '/checkout/paypal.png',
                'use_icon'      => true,
                'desc'          => __('Pay via your PayPal account', 'memberpress'),
                'use_desc'      => true,
                'paypal_email'  => '',
                'advanced_mode' => false,
                'api_username'  => '',
                'api_password'  => '',
                'signature'     => '',
                'sandbox'       => false,
                'debug'         => false,
            ],
            (array)$this->settings
        );

        $this->id        = $this->settings->id;
        $this->label     = $this->settings->label;
        $this->use_label = $this->settings->use_label;
        $this->icon      = $this->settings->icon;
        $this->use_icon  = $this->settings->use_icon;
        $this->desc      = $this->settings->desc;
        $this->use_desc  = $this->settings->use_desc;

        if ($this->is_test_mode()) {
            $this->settings->url      = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
            $this->settings->api_url  = 'https://api-3t.sandbox.paypal.com/nvp';
            $this->settings->form_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        } else {
            $this->settings->url      = 'https://ipnpb.paypal.com/cgi-bin/webscr';
            $this->settings->api_url  = 'https://api-3t.paypal.com/nvp';
            $this->settings->form_url = 'https://www.paypal.com/cgi-bin/webscr';
        }

        $this->settings->api_version = 69;

        if ($this->settings->advanced_mode == 'on' or $this->settings->advanced_mode == true) {
            $this->capabilities = [
                'process-payments',
                'process-refunds',
                'create-subscriptions',
                'cancel-subscriptions',
                'update-subscriptions',
                'suspend-subscriptions',
                'resume-subscriptions',
                'subscription-trial-payment', // The trial payment doesn't have to be processed as a separate one-off like Authorize.net & Stripe.
                'order-bumps',
            ];
        } else {
            $this->capabilities = [
                'process-payments',
                'create-subscriptions',
                'update-subscriptions',
                'subscription-trial-payment', // The trial payment doesn't have to be processed as a separate one-off like Authorize.net & Stripe.
                'order-bumps',
            ];
        }

        // An attempt to correct people who paste in spaces along with their credentials.
        $this->settings->paypal_email = trim($this->settings->paypal_email);
    }

    /**
     * Process the Instant Payment Notification (IPN) from PayPal.
     *
     * @return mixed
     */
    public function listener()
    {
        $_POST = wp_unslash($_POST);
        $this->email_status("PayPal IPN Recieved\n" . MeprUtils::object_to_string($_POST, true) . "\n", $this->settings->debug);

        if ($this->validate_ipn()) {
            return $this->process_ipn();
        }

        return false;
    }

    /**
     * Process the IPN from PayPal.
     *
     * @return mixed
     */
    public function process_ipn()
    {
        $recurring_payment_txn_types = ['recurring_payment', 'subscr_payment', 'recurring_payment_outstanding_payment'];
        $failed_txn_types            = ['recurring_payment_skipped', 'subscr_failed'];
        $payment_status_types        = ['denied','expired','failed'];
        $refunded_types              = ['refunded','reversed','voided'];
        $cancel_sub_types            = ['recurring_payment_profile_cancel', 'subscr_cancel', 'recurring_payment_suspended_due_to_max_failed_payment'];

        if (isset($_POST['txn_type']) && strtolower($_POST['txn_type']) == 'web_accept') {
            if ($this->is_ipn_for_me()) {
                $txn_id = isset($_POST['item_number']) && is_numeric($_POST['item_number']) ? (int) $_POST['item_number'] : 0;
                $txn    = new MeprTransaction($txn_id);

                // Check if this is a multi-item purchase.
                $order                   = $txn->order();
                $order_bump_transactions = $order instanceof MeprOrder ? MeprTransaction::get_all_by_order_id_and_gateway($order->id, $this->id, $txn->id) : [];

                if ($order instanceof MeprOrder && count($order_bump_transactions)) {
                    if (!$order->is_complete() && !$order->is_processing()) {
                        $order->update_meta('processing', true);
                        $transactions = array_merge([$txn], $order_bump_transactions);
                        $trans_num    = $_POST['txn_id'];

                        foreach ($transactions as $transaction) {
                            $_POST['txn_id'] = sprintf('mi_%d_%s', $order->id, uniqid());

                            if (!$transaction->is_payment_required()) {
                                  MeprTransaction::create_free_transaction($transaction, false, $_POST['txn_id']);
                            } else {
                                $_POST['item_number'] = $transaction->id;
                                $_POST['mc_gross']    = $transaction->total;

                                $this->record_payment();
                            }
                        }

                        $order->trans_num = $trans_num;
                        $order->status    = MeprOrder::$complete_str;
                        $order->store();
                        $order->delete_meta('processing');
                    }

                    return;
                }

                $this->record_payment();
            }
        } elseif (isset($_POST['txn_type']) && strtolower($_POST['txn_type']) == 'subscr_signup') {
            // We're only going to use subscr_signup here for free trial periods
            // Otherwise the record_create_subscription will be called during subscr_payment
            // because PayPal decided it would be great to send the subscr_payment webhook before the subscr_signup DOH!
            if (!$this->is_ipn_for_me() || !isset($_POST['item_number'])) {
                return; // Need a txn ID.
            }

            $txn = new MeprTransaction($_POST['item_number']);

            if (!isset($txn->id) || empty($txn->id) || (int)$txn->id <= 0) {
                return; // No txn.
            }

            $sub = new MeprSubscription($txn->subscription_id);

            if ($sub && $sub->id > 0 && $sub->trial && $sub->trial_amount <= 0.00) {
                // Check if this is a multi-item purchase.
                $order                   = $txn->order();
                $order_bump_transactions = $order instanceof MeprOrder ? MeprTransaction::get_all_by_order_id_and_gateway($order->id, $this->id, $txn->id) : [];

                if ($order instanceof MeprOrder && count($order_bump_transactions)) {
                    foreach ($order_bump_transactions as $transaction) {
                        if ($transaction->is_payment_required()) {
                            // If any order bump required payment, we don't want to record subscription creation here.
                            // It will be handled by the $recurring_payment_txn_types IPN below.
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

                $this->record_create_subscription();
            }
        } elseif (
            isset($_POST['txn_type'], $_POST['payment_status']) &&
            in_array(strtolower($_POST['txn_type']), $recurring_payment_txn_types) &&
            in_array(strtolower($_POST['payment_status']), $payment_status_types)
        ) {
            $this->record_payment_failure();
        } elseif (isset($_POST['txn_type']) && in_array(strtolower($_POST['txn_type']), $recurring_payment_txn_types)) {
            if (!isset($_POST['recurring_payment_id']) && !isset($_POST['subscr_id'])) {
                return;
            }

            // First see if the subscription has already been setup with the correct I- or S- number.
            if (isset($_POST['subscr_id']) && !empty($_POST['subscr_id'])) {
                $sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_id']);
            } else {
                $sub = MeprSubscription::get_one_by_subscr_id($_POST['recurring_payment_id']);
            }

            // If no $sub at this point it's safe to assume this is a new signup so let's get the $sub from the $txn instead
            // This order of operations to get the $sub will prevent issues between multiple sites sharing the same IPN URL (via IPN FWD ADDON).
            if ($sub === false && isset($_POST['item_number'])) {
                // CANNOT DO IPN CHECK UNTIL HERE OR IT WILL MESS STUFF UP.
                if (!$this->is_ipn_for_me()) { // This isn't for us, so let's bail.
                    return;
                }

                $txn = new MeprTransaction($_POST['item_number']);

                // Check if this is a multi-item purchase.
                $order                   = $txn->order();
                $order_bump_transactions = $order instanceof MeprOrder ? MeprTransaction::get_all_by_order_id_and_gateway($order->id, $this->id, $txn->id) : [];

                if ($order instanceof MeprOrder && count($order_bump_transactions)) {
                    if (!$order->is_complete() && !$order->is_processing()) {
                        $order->update_meta('processing', true);
                        $transactions = array_merge([$txn], $order_bump_transactions);
                        $trans_num    = $_POST['txn_id'];

                        foreach ($transactions as $transaction) {
                            $_POST['item_number'] = $transaction->id;
                            $_POST['txn_id']      = sprintf('mi_%d_%s', $order->id, uniqid());

                            if (!$transaction->is_payment_required()) {
                                MeprTransaction::create_free_transaction($transaction, false, $_POST['txn_id']);
                            } elseif ($transaction->is_one_time_payment()) {
                                $_POST['mc_gross'] = (float) $transaction->total;

                                $this->record_payment();
                            } else {
                                $subscription = $transaction->subscription();

                                if (!($subscription instanceof MeprSubscription)) {
                                    continue;
                                }

                                if ($subscription->gateway == $this->id) {
                                    // The subscription hasn't been set up yet so let's set it up first.
                                    if (strpos($subscription->subscr_id, 'S-') === false && strpos($subscription->subscr_id, 'I-') === false) {
                                        $this->record_create_subscription();
                                    }

                                    if ($subscription->trial && $subscription->trial_days > 0) {
                                        if ($subscription->trial_total > 0) {
                                            $_POST['mc_gross'] = $subscription->trial_total;
                                        } else {
                                            continue; // Initial payment for a free trial with order bump, we don't want to record a subscription transaction here.
                                        }
                                    } else {
                                        $_POST['mc_gross'] = $subscription->total;
                                    }

                                    $_POST['mepr_order_id'] = $order->id;

                                    // Record recurring payment.
                                    $this->record_subscription_payment();
                                }
                            }
                        }

                        $order->trans_num = $trans_num;
                        $order->status    = MeprOrder::$complete_str;
                        $order->store();
                        $order->delete_meta('processing');
                    }

                    return;
                } else {
                    $sub = $txn->subscription();
                }
            }

            if ($sub !== false && $sub->gateway == $this->id) {
                // The subscription hasn't been setup yet so let's set it up first.
                if (strpos($sub->subscr_id, 'S-') === false && strpos($sub->subscr_id, 'I-') === false) {
                    $this->record_create_subscription(); // Is it even possible to get here?
                }

                // Record recurring payment on existing sub (this bypasses is_ipn_for_me which is needed in case
                // subscriptions were imported from 3rd party services).
                $this->record_subscription_payment();
            }
        } elseif (isset($_POST['parent_txn_id']) && !isset($_POST['txn_type'])) {
            if (in_array(strtolower($_POST['payment_status']), $refunded_types)) {
                return $this->record_refund();
            }
        } elseif (isset($_POST['txn_type']) && strtolower($_POST['txn_type']) == 'recurring_payment_suspended') {
            $this->record_suspend_subscription();
        } elseif (isset($_POST['txn_type']) && in_array(strtolower($_POST['txn_type']), $cancel_sub_types)) {
            $this->record_cancel_subscription();
        } elseif (
            ( isset($_POST['txn_type']) &&
              in_array(strtolower($_POST['txn_type']), $failed_txn_types) ) ||
            ( isset($_POST['payment_status']) &&
              in_array(strtolower($_POST['payment_status']), $payment_status_types) )
        ) {
            $this->record_payment_failure();
        }
    }

    /**
     * Determine if the IPN is for the current gateway.
     *
     * @return boolean True if the IPN is for the current gateway, false otherwise.
     */
    public function is_ipn_for_me()
    {
        // Note: Sometimes PayPal doesn't send the custom field, or it is cutoff and doesn't include the gateway_id
        // This prevents transactions from being recorded. Since the fix is dependent on PayPal, this filter
        // is to override the IPN is for me check so customers sites can still operate.
        // CAUTION: Should ony be used for customers with this specific issue and that only have 1 PayPal payment gateway setup.
        // The same filter is in MeprPayPalCommerceGateway as well.
        if (apply_filters('mepr_override_ipn_is_for_me', false)) {
            return true;
        }

        if (isset($_POST['custom']) && !empty($_POST['custom'])) {
            $custom_vars = (array)json_decode(stripslashes($_POST['custom']));

            if (isset($custom_vars['gateway_id']) && $custom_vars['gateway_id'] == $this->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record the payment of a subscription.
     *
     * @return void
     */
    public function record_subscription_payment()
    {
        if (!isset($_POST['recurring_payment_id']) && !isset($_POST['subscr_id'])) {
            return;
        }

        if (isset($_POST['subscr_id']) && !empty($_POST['subscr_id'])) {
            $sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_id']);
        } else {
            $sub = MeprSubscription::get_one_by_subscr_id($_POST['recurring_payment_id']);
        }

        if ($sub) {
            $timestamp = isset($_POST['payment_date']) ? strtotime($_POST['payment_date']) : time();
            $first_txn = new MeprTransaction($sub->first_txn_id);

            if (!isset($first_txn->id) || empty($first_txn->id)) {
                $first_txn             = new MeprTransaction();
                $first_txn->user_id    = $sub->user_id;
                $first_txn->product_id = $sub->product_id;
                $first_txn->coupon_id  = $sub->coupon_id;
            }

            $existing = MeprTransaction::get_one_by_trans_num($_POST['txn_id']);

            // There's a chance this may have already happened during the return handler, if so let's just get everything up to date on the existing one.
            if ($existing != null && isset($existing->id) && (int)$existing->id > 0) {
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

            $this->email_status("Subscription Transaction\n" . MeprUtils::object_to_string($txn->rec, true), $this->settings->debug);

            MeprUtils::send_transaction_receipt_notices($txn);

            return $txn;
        }

        return false;
    }

    /**
     * Record the failure of a payment.
     *
     * @return MeprTransaction|boolean
     */
    public function record_payment_failure()
    {
        if (isset($_POST['ipn_track_id']) && $txn_res = MeprTransaction::get_one_by_trans_num($_POST['ipn_track_id']) && isset($txn_res->id)) { // phpcs:ignore
            return false; // We've already recorded this failure duh - don't send more emails.
        } elseif (isset($_POST['txn_id']) && $txn_res = MeprTransaction::get_one_by_trans_num($_POST['txn_id']) && isset($txn_res->id)) { // phpcs:ignore
            $txn         = new MeprTransaction($txn_res->id);
            $txn->status = MeprTransaction::$failed_str;
            $txn->store();
        } elseif (
            ( isset($_POST['recurring_payment_id']) &&
              ($sub = MeprSubscription::get_one_by_subscr_id($_POST['recurring_payment_id'])) ) || // phpcs:ignore
            ( isset($_POST['subscr_id']) &&
              ($sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_id'])) ) // phpcs:ignore
        ) {
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
            // If ipn_track_id isn't set then just use uniqid.
            $txn->trans_num = ( isset($_POST['ipn_track_id']) ? $_POST['ipn_track_id'] : uniqid() );
            $txn->gateway   = $this->id;
            $txn->set_gross((isset($_POST['mc_gross'])) ? $_POST['mc_gross'] : ((isset($_POST['amount'])) ? $_POST['amount'] : 0.00));
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
     * Process the payment of a transaction.
     *
     * @param MeprTransaction $txn The transaction object.
     *
     * @return void
     */
    public function process_payment($txn)
    {
        // Handled in the IPN, only record_payment is needed here.
    }

    /**
     * Record the payment of a transaction.
     *
     * @return MeprTransaction|boolean
     */
    public function record_payment()
    {
        if (!isset($_POST['item_number']) || empty($_POST['item_number'])) {
            return false;
        }

        $txn = new MeprTransaction($_POST['item_number']);

        // The amount can be fudged in the URL with PayPal Standard - so let's make sure no fudgyness is goin' on.
        if (isset($_POST['mc_gross']) && (float)$_POST['mc_gross'] < (float)$txn->total) {
            $txn->amount     = (float)$_POST['mc_gross'];
            $txn->total      = (float)$_POST['mc_gross'];
            $txn->tax_amount = 0.00;
            $txn->tax_rate   = 0.00;
            $txn->status     = MeprTransaction::$pending_str;
            $txn->txn_type   = MeprTransaction::$payment_str;
            $txn->trans_num  = $_POST['txn_id'];
            $txn->store();

            return false;
        }

        // Already been here somehow?
        if ($txn->status == MeprTransaction::$complete_str && $txn->trans_num == $_POST['txn_id']) {
            return false;
        }

        if (isset($_POST['payment_status']) && strtolower($_POST['payment_status']) == 'completed') {
            $timestamp = isset($_POST['payment_date']) ? strtotime($_POST['payment_date']) : time();

            $txn->trans_num  = $_POST['txn_id'];
            $txn->txn_type   = MeprTransaction::$payment_str;
            $txn->status     = MeprTransaction::$complete_str;
            $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);

            // This will only work before maybe_cancel_old_sub is run.
            $upgrade   = $txn->is_upgrade();
            $downgrade = $txn->is_downgrade();

            $event_txn = $txn->maybe_cancel_old_sub();
            $txn->store();

            $this->email_status("Transaction\n" . MeprUtils::object_to_string($txn->rec, true) . "\n", $this->settings->debug);

            $prd = $txn->product();

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

            return $txn;
        }

        return false;
    }

    /**
     * This method should be used by the class to record a successful refund from
     * the gateway. This method should also be used by any IPN requests or Silent Posts.
     *
     * @param MeprTransaction $txn The transaction object.
     *
     * @return MeprTransaction|boolean
     * @throws MeprGatewayException The gateway exception.
     */
    public function process_refund(MeprTransaction $txn)
    {
        $mepr_options = MeprOptions::fetch();

        $args = MeprHooks::apply_filters('mepr_paypal_std_refund_args', [
            'TRANSACTIONID' => $txn->trans_num,
            'REFUNDTYPE'    => 'Full',
            'CURRENCYCODE'  => $mepr_options->currency_code,
        ], $txn);

        $this->email_status("RefundTransaction Request:\n" . MeprUtils::object_to_string($args, true) . "\n", $this->settings->debug);
        $res = $this->send_nvp_request('RefundTransaction', $args);
        $this->email_status("RefundTransaction Response:\n" . MeprUtils::object_to_string($res, true) . "\n", $this->settings->debug);

        if (!isset($res['ACK']) or strtoupper($res['ACK']) != 'SUCCESS') {
            throw new MeprGatewayException(__('The refund was unsuccessful. Please login at PayPal and refund the transaction there.', 'memberpress'));
        }

        $_POST['parent_txn_id'] = $txn->id;
        return $this->record_refund();
    }

    /**
     * Record the refund of a transaction.
     *
     * @return MeprTransaction|boolean
     */
    public function record_refund()
    {
        $obj = MeprTransaction::get_one_by_trans_num($_POST['parent_txn_id']);

        if (!is_null($obj) && (int)$obj->id > 0) {
            $txn = new MeprTransaction($obj->id);

            // Seriously ... if txn was already refunded what are we doing here?
            if ($txn->status == MeprTransaction::$refunded_str) {
                return $txn;
            }

            $txn->status = MeprTransaction::$refunded_str;

            $this->email_status("Processing Refund: \n" . MeprUtils::object_to_string($_POST) . "\n Affected Transaction: \n" . MeprUtils::object_to_string($txn), $this->settings->debug);

            $txn->store();

            MeprUtils::send_refunded_txn_notices(
                $txn,
                MeprHooks::apply_filters('mepr_paypal_std_transaction_refunded_event_args', '', $txn)
            );

            return $txn;
        }

        return false;
    }

    // Not needed in PayPal since PayPal supports the trial payment inclusive of the Subscription.

    /**
     * Process the trial payment.
     *
     * @param MeprTransaction $transaction The transaction object.
     *
     * @return void
     */
    public function process_trial_payment($transaction)
    {
    }

    /**
     * Record the trial payment.
     *
     * @param MeprTransaction $transaction The transaction object.
     *
     * @return void
     */
    public function record_trial_payment($transaction)
    {
    }

    /**
     * Process the creation of a subscription.
     *
     * @param MeprTransaction $txn The transaction object.
     *
     * @return void
     */
    public function process_create_subscription($txn)
    {
        // This all happens in the IPN so record_created_subscription is all that's needed.
    }

    /**
     * Record the creation of a subscription.
     *
     * @return void
     */
    public function record_create_subscription()
    {
        $mepr_options = MeprOptions::fetch();

        $this->email_status("Paypal Create Subscription Response \$_POST:\n" . MeprUtils::object_to_string($_POST, true) . "\n", $this->settings->debug);

        $temp_txn = new MeprTransaction($_POST['item_number']);

        if ((int)$temp_txn->id <= 0) {
            return;
        }

        $sub = $temp_txn->subscription();

        if ((int)$sub->id > 0) {
            $timestamp = isset($_POST['payment_date']) ? strtotime($_POST['payment_date']) : time();

            $sub->subscr_id  = $_POST['subscr_id'];
            $sub->status     = MeprSubscription::$active_str;
            $sub->created_at = gmdate('c', $timestamp);
            $sub->store();

            $txn = $sub->first_txn();

            if ($txn == false || !($txn instanceof MeprTransaction)) {
                $txn                  = new MeprTransaction();
                $txn->user_id         = $sub->user_id;
                $txn->product_id      = $sub->product_id;
                $txn->gateway         = $this->id;
                $txn->subscription_id = $sub->id;
            }

            $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);

            // Only set the trans_num on free trial periods (silly, but necessary if the IPN comes in before the return URL is hit).
            if ($sub->trial && $sub->trial_amount <= 0.00) {
                $txn->trans_num = uniqid();
            }

            if ($sub->trial) {
                $expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
            } elseif (!$mepr_options->disable_grace_init_days && $mepr_options->grace_init_days > 0) {
                $expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($mepr_options->grace_init_days), 'Y-m-d 23:59:59');
            } else {
                $expires_at = $txn->created_at; // Expire immediately.
            }

            $txn->status   = MeprTransaction::$confirmed_str;
            $txn->txn_type = MeprTransaction::$subscription_confirmation_str;
            $txn->set_subtotal(0.00); // Just a confirmation txn.
            $txn->expires_at = $expires_at;
            $txn->store(true);

            // This will only work before maybe_cancel_old_sub is run.
            $upgrade   = $sub->is_upgrade();
            $downgrade = $sub->is_downgrade();

            $event_txn = $sub->maybe_cancel_old_sub();

            $this->email_status("Subscription Transaction\n" . MeprUtils::object_to_string($txn->rec, true), $this->settings->debug);

            if ($upgrade) {
                $this->upgraded_sub($sub, $event_txn);
            } elseif ($downgrade) {
                $this->downgraded_sub($sub, $event_txn);
            } else {
                $this->new_sub($sub, true);
            }

            MeprUtils::send_signup_notices($txn);

            return [
                'subscription' => $sub,
                'transaction'  => $txn,
            ];
        }
    }

    /**
     * Process the update of a subscription.
     *
     * With PayPal, we bill the outstanding amount of the previous subscription,
     * cancel the previous subscription and create a new subscription
     *
     * @param  integer $sub_id The subscription ID.
     * @return void
     */
    public function process_update_subscription($sub_id)
    {
        // Account info updated on PayPal.com.
    }

    /**
     * Record the update of a subscription.
     *
     * @return void
     */
    public function record_update_subscription()
    {
        // Account info updated on PayPal.com.
    }

    /**
     * Process the suspension of a subscription.
     *
     * @param  integer $sub_id The subscription ID.
     * @return void
     * @throws MeprGatewayException The gateway exception when the subscription is already suspended or in a free trial.
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

        $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
        $this->record_suspend_subscription();
    }

    /**
     * Record the suspension of a subscription.
     *
     * @return MeprSubscription|boolean
     */
    public function record_suspend_subscription()
    {
        $subscr_id = $_REQUEST['recurring_payment_id'];
        $sub       = MeprSubscription::get_one_by_subscr_id($subscr_id);

        if (!$sub) {
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
     * @return MeprSubscription|boolean
     */
    public function record_resume_subscription()
    {
        // APPARENTLY PAYPAL DOES NOT SEND OUT AN IPN FOR THIS -- SO WE CAN'T ACTUALLY RECORD THIS HERE UGH
        // BUT WE DO SET THE SUBSCR STATUS BACK TO ACTIVE WHEN THE NEXT PAYMENT CLEARS.
        $subscr_id = $_REQUEST['recurring_payment_id'];
        $sub       = MeprSubscription::get_one_by_subscr_id($subscr_id);

        if (!$sub) {
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
        if ($prior_txn == false || !($prior_txn instanceof MeprTransaction) || strtotime($prior_txn->expires_at) < time()) {
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
     * Process the cancellation of a subscription.
     *
     * @param  integer $sub_id The subscription ID.
     * @return void
     */
    public function process_cancel_subscription($sub_id)
    {
        $sub = new MeprSubscription($sub_id);

        // Should already expire naturally at paypal so we have no need
        // to do this when we're "cancelling" because of a natural expiration.
        if (!isset($_REQUEST['expire'])) {
            $this->update_paypal_payment_profile($sub_id, 'Cancel');
        }

        $_REQUEST['subscr_id'] = $sub->subscr_id;
        $this->record_cancel_subscription();
    }

    /**
     * Record the cancellation of a subscription.
     *
     * @return MeprSubscription|boolean
     */
    public function record_cancel_subscription()
    {
        // Not sure how/why this would happen but fail silently if it does.
        if (!isset($_REQUEST['subscr_id']) && !isset($_REQUEST['recurring_payment_id'])) {
            return false;
        }

        $subscr_id = (isset($_REQUEST['subscr_id'])) ? $_REQUEST['subscr_id'] : $_REQUEST['recurring_payment_id'];
        $sub       = MeprSubscription::get_one_by_subscr_id($subscr_id);

        if (!$sub) {
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

        if (!isset($_REQUEST['silent']) || ($_REQUEST['silent'] == false)) {
            MeprUtils::send_cancelled_sub_notices($sub);
        }

        return $sub;
    }

    /**
     * Process the signup form.
     *
     * @param MeprTransaction $txn The transaction object.
     *
     * @return void
     */
    public function process_signup_form($txn)
    {
        // Not used.
    }

    /**
     * This gets called on the 'init' hook when the signup form is processed ...
     * this is in place so that payment solutions like paypal can redirect
     * before any content is rendered.
     *
     * @param  MeprTransaction $txn The transaction object.
     * @throws Exception The exception.
     */
    public function display_payment_page($txn)
    {
        $order_bump_product_ids = isset($_GET['obs']) && is_array($_GET['obs']) ? array_map('intval', $_GET['obs']) : [];
        $order_bump_products    = MeprCheckoutCtrl::get_order_bump_products($txn->product_id, $order_bump_product_ids);

        $order_bumps = $this->process_order($txn, $order_bump_products);

        $gateway_payment_args = http_build_query($this->get_gateway_payment_args($txn, $order_bumps));
        $url                  = $this->settings->form_url . '?' . $gateway_payment_args;
        MeprUtils::wp_redirect(str_replace('&amp;', '&', $url));
    }

    /**
     * This gets called on wp_enqueue_script and enqueues a set of
     * scripts for use on the page containing the payment form
     */
    public function enqueue_payment_form_scripts()
    {
        // No need, handled on the PayPal side.
    }

    /**
     * Returs the payment form and required fields for the gateway
     */
    public function spc_payment_fields()
    {
        if ($this->settings->use_desc) {
            return wpautop(esc_html(trim($this->settings->desc)));
        }

        return '';
    }

    /**
     * This gets called on the_content and just renders the payment form
     * For PayPal Standard we're loading up a hidden form and submitting it with JS
     *
     * @param float    $amount         The amount of the transaction.
     * @param MeprUser $user           The user object.
     * @param integer  $product_id     The product ID.
     * @param integer  $transaction_id The transaction ID.
     */
    public function display_payment_form($amount, $user, $product_id, $transaction_id)
    {
        // Handled on the PayPal site so we don't have a need for it here.
    }

    /**
     * Validates the payment form before a payment is processed
     *
     * @param array $errors The errors to validate.
     */
    public function validate_payment_form($errors)
    {
        // PayPal does this on their own form.
    }

    /**
     * Redirects the user to PayPal checkout
     *
     * @param  MeprTransaction $txn The transaction object.
     * @throws MeprGatewayException The gateway exception.
     * @throws Exception The exception.
     */
    public function process_payment_form($txn)
    {
        $order_bump_product_ids = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_map('intval', $_POST['mepr_order_bumps']) : [];
        $order_bump_products    = MeprCheckoutCtrl::get_order_bump_products($txn->product_id, $order_bump_product_ids);

        $order_bumps = $this->process_order($txn, $order_bump_products);

        $gateway_payment_args = http_build_query($this->get_gateway_payment_args($txn, $order_bumps));
        $url                  = $this->settings->form_url . '?' . $gateway_payment_args;
        MeprUtils::wp_redirect(str_replace('&amp;', '&', $url));
    }

    /**
     * Get the args to send to PayPal
     *
     * @param  MeprTransaction   $txn         The transaction object.
     * @param  MeprTransaction[] $order_bumps The order bumps.
     * @throws MeprGatewayException The gateway exception.
     */
    private function get_gateway_payment_args($txn, array $order_bumps = [])
    {
        $mepr_options = MeprOptions::fetch();
        $prd          = $txn->product();
        $sub          = null;

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
        }

        // Txn vars.
        $custom = MeprHooks::apply_filters('mepr_paypal_std_custom_payment_vars', [
            'gateway_id' => $this->id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
        ], $txn);

        $cancel_url   = $this->notify_url('cancel');
        $cancel_delim = MeprUtils::get_delim($cancel_url);
        $return_url   = $this->notify_url('return');
        $return_delim = MeprUtils::get_delim($return_url);

        $payment_vars = [
            'cmd'           => '_xclick',
            'business'      => $this->settings->paypal_email,
            'lc'            => $mepr_options->language_code,
            'currency_code' => $mepr_options->currency_code,
            'item_name'     => $prd->post_title,
            'item_number'   => $txn->id,
            'tax_rate'      => MeprUtils::format_float(0.000, 3),
            'return'        => $return_url . $return_delim . 'mepr_txn_id=' . $txn->id,
            'cancel_return' => $cancel_url . $cancel_delim . 'txn_id=' . $txn->id,
            'no_shipping'   => 1,
            'custom'        => json_encode($custom),
            'bn'            => 'Caseproof_SP',
        ];

        if ($sub instanceof MeprSubscription) {
            $trial_total      = 0.00;
            $has_trial        = $sub->trial && $sub->trial_days > 0;
            $convert_to_trial = false;

            if ($has_trial) {
                $trial_total = (float) $sub->trial_total;
            }

            foreach ($transactions as $transaction) {
                if (!$transaction->is_payment_required()) {
                    continue;
                } elseif ($transaction->is_one_time_payment()) {
                    $trial_total += (float) $transaction->total;

                    if (!$has_trial) {
                        $convert_to_trial = true;
                    }
                }
            }

            // If there is no trial period, and there is an order bump, add the first subscription payment to the trial amount.
            if ($convert_to_trial) {
                $trial_total += (float) $sub->total;
            }

            $period_type_map = [
                'days'   => 'D',
                'weeks'  => 'W',
                'months' => 'M',
                'years'  => 'Y',
            ];

            // Build the subscription vars.
            $sub_vars = [
                'cmd' => '_xclick-subscriptions',
                'src' => 1,
                'sra' => 1, // Attempt to rebill failed txns.
                'a3'  => $this->format_currency($sub->total),
                'p3'  => $sub->period,
                't3'  => $period_type_map[$sub->period_type],
            ];

            // Handle the limiting of cycles - this is messy with PayPal Standard.
            if ($sub->limit_cycles) {
                if ($sub->limit_cycles_num > 1) {
                    $sub_vars['srt'] = $sub->limit_cycles_num; // The srt MUST be > 1.
                } else {
                    $sub_vars['src'] = 0; // Tell PayPal not to bill after the first cycle.
                }
            }

            // Handle Trial period stuff.
            if ($has_trial || $convert_to_trial) {
                if ($convert_to_trial) {
                    $now        = new DateTimeImmutable('now');
                    $end        = $now->modify(sprintf('+%d %s', $sub->period, $sub->period_type));
                    $trial_days = $end->diff($now)->format('%a');
                } else {
                    $trial_days = $sub->trial_days;
                }

                $sub_trial_vars = [
                    'a1' => $this->format_currency($trial_total),
                ];

                // Trial Days, Weeks, Months, or Years.
                if ($trial_days <= 90) {
                    $sub_trial_vars['p1'] = $trial_days;
                    $sub_trial_vars['t1'] = 'D';
                } else {
                    if ($trial_days % 30 == 0) { // 30 days in a month
                        $sub_trial_vars['p1'] = (int)($trial_days / 30);
                        $sub_trial_vars['t1'] = 'M';
                    } elseif ($trial_days % 365 == 0) { // 365 days in a year
                        $sub_trial_vars['p1'] = (int)($trial_days / 365);
                        $sub_trial_vars['t1'] = 'Y';
                    } else { // Force a round to the nearest week - that's the best we can do here.
                        $sub_trial_vars['p1'] = round((int)$trial_days / 7);
                        $sub_trial_vars['t1'] = 'W';

                        if (!$convert_to_trial) {
                                $sub->trial_days = (int)($sub_trial_vars['p1'] * 7);
                                $sub->store();
                        }
                    }
                }

                $sub_vars = array_merge($sub_vars, $sub_trial_vars);

                // Set the RETURN differently since we DON'T get an ITEM NUMBER from PayPal on free trial periods doh!
                if ($trial_total <= 0.00) {
                    $sub_vars['return'] = $return_url . $return_delim . 'free_trial_txn_id=' . $txn->id;
                }
            }

            $sub_vars = MeprHooks::apply_filters('mepr_paypal_std_subscription_vars', $sub_vars, $txn, $sub);

            // Merge payment vars with subscr vars overriding payment vars.
            $payment_vars = array_merge($payment_vars, $sub_vars);
        } else {
            $total = 0.00;

            foreach ($transactions as $transaction) {
                $product = $transaction->product();

                if (empty($product->ID)) {
                    throw new MeprGatewayException(__('Product not found', 'memberpress'));
                }

                $total += (float) $transaction->total;
            }

            $payment_vars = array_merge($payment_vars, [
                'amount' => $total,
            ]);
        }

        $payment_vars = MeprHooks::apply_filters('mepr_paypal_std_payment_vars', $payment_vars, $txn);

        return $payment_vars;
    }

    /**
     * Displays the form for the given payment gateway on the MemberPress Options page
     */
    public function display_options_form()
    {
        $mepr_options = MeprOptions::fetch();

        $paypal_email = trim($this->settings->paypal_email);
        $api_username = trim($this->settings->api_username);
        $api_password = trim($this->settings->api_password);
        $signature    = trim($this->settings->signature);
        $advanced     = ($this->settings->advanced_mode == 'on' or $this->settings->advanced_mode == true);
        $sandbox      = ($this->settings->sandbox == 'on' or $this->settings->sandbox == true);
        $debug        = ($this->settings->debug == 'on' or $this->settings->debug == true);

        ?>

    <table >
      <tr>
        <td><?php _e('Primary PayPal Email*:', 'memberpress'); ?></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][paypal_email]" value="<?php echo $paypal_email; ?>" /></td>
      </tr>
      <tr>
        <td colspan="2">
          <input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][advanced_mode]" id="advanced-mode-<?php echo $this->id; ?>" class="advanced_mode_checkbox" data-value="<?php echo $this->id;?>" <?php checked($advanced); ?> />
          <label for="advanced-mode-<?php echo $this->id; ?>"><?php _e('Advanced Mode', 'memberpress'); ?></label>
        </td>
      </tr>
      <tr class="advanced_mode_row-<?php echo $this->id;?> mepr_hidden">
        <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<em><?php _e('API Username:', 'memberpress'); ?></em></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][api_username]" value="<?php echo $api_username; ?>" /></td>
      </tr>
      <tr class="advanced_mode_row-<?php echo $this->id;?> mepr_hidden">
        <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<em><?php _e('API Password:', 'memberpress'); ?></em></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][api_password]" value="<?php echo $api_password; ?>" /></td>
      </tr>
      <tr class="advanced_mode_row-<?php echo $this->id;?> mepr_hidden">
        <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<em><?php _e('Signature:', 'memberpress'); ?></em></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][signature]" value="<?php echo $signature; ?>" /></td>
      </tr>
      <tr>
        <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][sandbox]"<?php echo checked($sandbox); ?> />&nbsp;<?php _e('Use PayPal Sandbox', 'memberpress'); ?></td>
      </tr>
      <tr>
        <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][debug]"<?php echo checked($debug); ?> />&nbsp;<?php _e('Send PayPal Debug Emails', 'memberpress'); ?></td>
      </tr>
      <tr>
        <td><?php _e('PayPal IPN URL:', 'memberpress'); ?></td>
        <td><?php MeprAppHelper::clipboard_input($this->notify_url('ipn')); ?></td>
      </tr>
      <!-- THIS IS NOT ACTUALLY USED ANY LONGER - BUT IT IS REQUIRED FOR THE RETURN DATA TO BE SENT SO LEAVING IT IN PLACE FOR NOW -->
      <tr>
        <td><?php _e('Return URL:', 'memberpress'); ?></td>
        <td><?php MeprAppHelper::clipboard_input($this->notify_url('return')); ?></td>
      </tr>
        <?php MeprHooks::do_action('mepr-paypal-standard-options-form', $this); ?>
    </table>

        <?php
        // }
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

        if (
            !isset($_POST[$mepr_options->integrations_str][$this->id]['paypal_email']) or
            empty($_POST[$mepr_options->integrations_str][$this->id]['paypal_email']) or
            !is_email(stripslashes($_POST[$mepr_options->integrations_str][$this->id]['paypal_email']))
        ) {
            $errors[] = __("Primary PayPal Email field can't be blank.", 'memberpress');
        }

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
     *
     * @return boolean
     */
    public function is_test_mode()
    {
        return (isset($this->settings->sandbox) and $this->settings->sandbox);
    }

    /**
     * Forces the SSL.
     *
     * @return boolean
     */
    public function force_ssl()
    {
        return false; // Redirects off site where ssl is installed.
    }

    /**
     * Sends an NVP request to the PayPal API.
     *
     * @param  string  $method_name The method name.
     * @param  array   $args        The arguments.
     * @param  string  $method      The method.
     * @param  boolean $blocking    The blocking.
     * @return boolean
     * @throws MeprHttpException If there's an HTTP error connecting to PayPal.
     */
    private function send_nvp_request($method_name, $args, $method = 'post', $blocking = true)
    {
        $mepr_options = MeprOptions::fetch();
        $args         = array_merge(
            [
                'METHOD'    => $method_name,
                'VERSION'   => $this->settings->api_version,
                'PWD'       => $this->settings->api_password,
                'USER'      => $this->settings->api_username,
                'SIGNATURE' => $this->settings->signature,
            ],
            $args
        );

        $args = MeprHooks::apply_filters('mepr_paypal_std_send_request_args', $args);

        $arg_array = MeprHooks::apply_filters('mepr_paypal_std_send_request', [
            'method'      => strtoupper($method),
            'body'        => $args,
            'timeout'     => 15,
            'httpversion' => '1.1', // PayPal is now requiring this.
            'blocking'    => $blocking,
            'sslverify'   => $mepr_options->sslverify,
            'headers'     => [],
        ]);

        $resp = wp_remote_request($this->settings->api_url, $arg_array);

        // If we're not blocking then the response is irrelevant
        // So we'll just return true.
        if ($blocking == false) {
            return true;
        }

        if (is_wp_error($resp)) {
            throw new MeprHttpException(sprintf(
                // Translators: %s: gateway name.
                __('You had an HTTP error connecting to %s', 'memberpress'),
                $this->name
            ));
        } else {
            return wp_parse_args($resp['body']);
        }

        return false;
    }

    /**
     * Updates the PayPal payment profile.
     *
     * @param  integer $sub_id The subscription ID.
     * @param  string  $action The action.
     * @return void
     * @throws MeprGatewayException If there's a problem with the PayPal API request.
     */
    private function update_paypal_payment_profile($sub_id, $action = 'cancel')
    {
        $sub = new MeprSubscription($sub_id);

        $args = MeprHooks::apply_filters('mepr_paypal_std_update_payment_profile_args', [
            'PROFILEID' => $sub->subscr_id,
            'ACTION'    => $action,
        ], $sub);

        $this->email_status("PayPal Update subscription request: \n" . MeprUtils::object_to_string($args, true) . "\n", $this->settings->debug);

        $res = $this->send_nvp_request('ManageRecurringPaymentsProfileStatus', $args);

        $this->email_status("PayPal Update subscription response: \n" . MeprUtils::object_to_string($res, true) . "\n", $this->settings->debug);

        if (strtolower($res['ACK']) != 'success') {
            throw new MeprGatewayException(__('There was a problem cancelling, try logging in directly at PayPal to update the status of your recurring profile.', 'memberpress'));
        }

        $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
    }

    /**
     * Determine if the current request is a redirect from PayPal
     *
     * @return boolean
     */
    private function is_paypal_referrer()
    {
        $referrer           = isset($_SERVER['HTTP_REFERER']) ? wp_unslash($_SERVER['HTTP_REFERER']) : '';
        $is_paypal_referrer = (strpos($referrer, 'paypal.com') !== false);

        return MeprHooks::apply_filters('mepr_paypal_standard_is_paypal_referrer', $is_paypal_referrer);
    }

    /**
     * Find the transaction from a PayPal return
     *
     * @return MeprTransaction|null
     */
    protected function get_paypal_return_txn()
    {
        if (isset($_GET['mepr_txn_id']) && is_numeric($_GET['mepr_txn_id'])) {
            $txn = new MeprTransaction((int) $_GET['mepr_txn_id']);

            if (!empty($txn->id)) {
                return $txn;
            }
        }

        foreach (['txn_id', 'tx'] as $key) {
            if (isset($_GET[$key])) {
                $existing_txn = MeprTransaction::get_one_by_trans_num(sanitize_text_field(wp_unslash($_GET[$key])));

                if (!empty($existing_txn->id)) {
                    $txn = new MeprTransaction($existing_txn->id);

                    if (!empty($txn->id)) {
                        return $txn;
                    }
                }
            }
        }

        if (isset($_GET['item_number']) && is_numeric($_GET['item_number'])) {
            $txn = new MeprTransaction((int) $_GET['item_number']);

            if (!empty($txn->id)) {
                return $txn;
            }
        }

        return null;
    }

    /**
     * Handles the return from PayPal.
     *
     * @return void
     */
    public function return_handler()
    {
        $mepr_options = MeprOptions::fetch();

        if (! $this->is_paypal_referrer()) {
            wp_die(_x('Something unexpected has occurred. Please contact us for assistance.', 'ui', 'memberpress') . ' <br/><a href="' . $mepr_options->account_page_url('action=subscriptions') . '">View my Subscriptions</a>');
        }

        $this->email_status("Paypal Return \$_REQUEST:\n" . MeprUtils::object_to_string($_REQUEST, true) . "\n", $this->settings->debug);

        // Let's find the transaction from the PayPal return URL vars.
        $txn = $this->get_paypal_return_txn();

        if (isset($txn->id) && $txn->id) {
            $product = new MeprProduct($txn->product_id);

            // Did the IPN already beat us here?
            if (strpos($txn->trans_num, 'mp-txn') === false) {
                $sanitized_title = sanitize_title($product->post_title);
                $query_params    = [
                    'membership'    => $sanitized_title,
                    'trans_num'     => $txn->trans_num,
                    'membership_id' => $product->ID,
                ];
                if ($txn->subscription_id > 0) {
                    $sub          = $txn->subscription();
                    $query_params = array_merge($query_params, ['subscr_id' => $sub->subscr_id]);
                }

                $thankyou_url = $this->do_thankyou_url($query_params, $txn);

                MeprUtils::wp_redirect($thankyou_url);
            }
            // Check if this is a multi-item purchase.
            $order                   = $txn->order();
            $order_bump_transactions = $order instanceof MeprOrder ? MeprTransaction::get_all_by_order_id_and_gateway($order->id, $this->id, $txn->id) : [];

            if ($order instanceof MeprOrder && count($order_bump_transactions)) {
                if (!$order->is_complete()) {
                    $transactions = array_merge([$txn], $order_bump_transactions);

                    foreach ($transactions as $transaction) {
                        if (!$transaction->is_payment_required()) {
                            continue;
                        } elseif ($transaction->is_one_time_payment()) {
                            $transaction->txn_type = MeprTransaction::$payment_str;
                            $transaction->status   = MeprTransaction::$complete_str;
                            $transaction->store();
                        } else {
                            $subscription = $transaction->subscription();

                            if (!($subscription instanceof MeprSubscription)) {
                                continue;
                            }

                            $this->activate_subscription($transaction, $subscription);
                        }
                    }
                }

                $sanitized_title = sanitize_title($product->post_title);
                $query_params    = [
                    'membership'    => $sanitized_title,
                    'trans_num'     => $txn->trans_num,
                    'membership_id' => $product->ID,
                ];

                if ($txn->subscription_id > 0) {
                    $sub          = $txn->subscription();
                    $query_params = array_merge($query_params, ['subscr_id' => $sub->subscr_id]);
                }

                MeprUtils::wp_redirect($mepr_options->thankyou_page_url(build_query($query_params)));
            }

            $sub = $txn->subscription();

            // If $sub let's set this up as a confirmation txn until the IPN comes in later so the user can have access now.
            if ($sub) {
                $sub->status     = MeprSubscription::$active_str;
                $sub->created_at = $txn->created_at; // Set the created at too.
                $sub->store();

                if (!$mepr_options->disable_grace_init_days && $mepr_options->grace_init_days > 0) {
                    $expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($mepr_options->grace_init_days), 'Y-m-d 23:59:59');
                } else {
                    $expires_at = $txn->created_at; // Expire immediately.
                }

                $txn->trans_num  = uniqid();
                $txn->txn_type   = MeprTransaction::$subscription_confirmation_str;
                $txn->status     = MeprTransaction::$confirmed_str;
                $txn->expires_at = $expires_at;
                $txn->store(true);
            } else {
                // The amount can be fudged in the URL with PayPal Standard - so let's make sure no fudgyness is goin' on.
                if (isset($_GET['amt']) && (float)$_GET['amt'] < (float)$txn->total) {
                    $txn->status   = MeprTransaction::$pending_str;
                    $txn->txn_type = MeprTransaction::$payment_str;
                    $txn->store();
                    wp_die(_x('Your payment amount was lower than expected. Please contact us for assistance if necessary.', 'ui', 'memberpress') . ' <br/><a href="' . $mepr_options->account_page_url('action=subscriptions') . '">View my Subscriptions</a>');
                }

                // Don't set a trans_num here - it will get updated when the IPN comes in.
                $txn->txn_type = MeprTransaction::$payment_str;
                $txn->status   = MeprTransaction::$complete_str;
                $txn->store();
            }

            $this->email_status("Paypal Transaction \$txn:\n" . MeprUtils::object_to_string($txn, true) . "\n", $this->settings->debug);

            $sanitized_title = sanitize_title($product->post_title);
            $query_params    = [
                'membership'    => $sanitized_title,
                'trans_num'     => $txn->trans_num,
                'membership_id' => $product->ID,
            ];
            if ($txn->subscription_id > 0) {
                $sub          = $txn->subscription();
                $query_params = array_merge($query_params, ['subscr_id' => $sub->subscr_id]);
            }

            $thankyou_url = $this->do_thankyou_url($query_params, $txn);

            MeprUtils::wp_redirect($thankyou_url);
        }

        // Handle free trial periods here YO.
        if (isset($_GET['free_trial_txn_id']) and is_numeric($_GET['free_trial_txn_id'])) {
            $free_trial_txn = new MeprTransaction((int)$_GET['free_trial_txn_id']);
            $fsub           = $free_trial_txn->subscription();
            $product        = new MeprProduct($free_trial_txn->product_id);

            // Did the IPN already beat us here?
            if (strpos($free_trial_txn->trans_num, 'mp-txn') === false) {
                $sanitized_title = sanitize_title($product->post_title);
                $query_params    = [
                    'membership'    => $sanitized_title,
                    'trans_num'     => $free_trial_txn->trans_num,
                    'membership_id' => $product->ID,
                ];
                if ($free_trial_txn->subscription_id > 0) {
                    $sub          = $free_trial_txn->subscription();
                    $query_params = array_merge($query_params, ['subscr_id' => $sub->subscr_id]);
                }
                $thankyou_url = $this->do_thankyou_url($query_params, $free_trial_txn);
                MeprUtils::wp_redirect($thankyou_url);
            }

            // Confirmation txn so the user can have access right away, instead of waiting for the IPN.
            $free_trial_txn->set_subtotal(0.00);
            $free_trial_txn->txn_type   = MeprTransaction::$subscription_confirmation_str;
            $free_trial_txn->trans_num  = uniqid();
            $free_trial_txn->status     = MeprTransaction::$confirmed_str;
            $free_trial_txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days(1), 'Y-m-d 23:59:59');
            $free_trial_txn->store();

            $fsub->status     = MeprSubscription::$active_str;
            $fsub->created_at = $free_trial_txn->created_at; // Set the created at too.
            $fsub->store();

            $this->email_status("Paypal Transaction \$free_trial_txn:\n" . MeprUtils::object_to_string($free_trial_txn, true) . "\n", $this->settings->debug);

            $sanitized_title = sanitize_title($product->post_title);
            $query_params    = [
                'membership'    => $sanitized_title,
                'trans_num'     => $free_trial_txn->trans_num,
                'membership_id' => $product->ID,
            ];
            if ($free_trial_txn->subscription_id > 0) {
                $sub          = $free_trial_txn->subscription();
                $query_params = array_merge($query_params, ['subscr_id' => $sub->subscr_id]);
            }

            $thankyou_url = $this->do_thankyou_url($query_params, $free_trial_txn);
            MeprUtils::wp_redirect($thankyou_url);
        }

        // If all else fails, just send them to their account page.
        MeprUtils::wp_redirect($mepr_options->account_page_url('action=subscriptions'));
    }

    /**
     * Handles the cancel from PayPal.
     *
     * @return void
     */
    public function cancel_handler()
    {
        // Handled with a GET REQUEST by PayPal.
        $this->email_status("Paypal Cancel \$_REQUEST:\n" . MeprUtils::object_to_string($_REQUEST, true) . "\n", $this->settings->debug);

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

        // If all else fails, just send them to their account page.
        $mepr_options = MeprOptions::fetch();
        MeprUtils::wp_redirect($mepr_options->account_page_url('action=subscriptions'));
    }

    /**
     * Displays the cancel message.
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
    )); ?><br/></p>
        <?php
    }
}
