<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

#[AllowDynamicProperties]
class MeprPayPalGateway extends MeprBasePayPalGateway
{
    // This is stored with the user meta & the subscription meta
    public static $paypal_token_str = '_mepr_paypal_token';

    /**
     * Used in the view to identify the gateway
     */
    public function __construct()
    {
        $this->name = __('PayPal Express Checkout', 'memberpress');
        $this->key = __('paypalexpress', 'memberpress');
        $this->has_spc_form = false;

        $this->set_defaults();

        $this->capabilities = [
            'process-payments',
            'process-refunds',
            'create-subscriptions',
            'cancel-subscriptions',
            'update-subscriptions',
            'suspend-subscriptions',
            'resume-subscriptions',
            'subscription-trial-payment', // The trial payment doesn't have to be processed as a separate one-off like Authorize.net & Stripe
        // 'send-cc-expirations'
        ];

        // Setup the notification actions for this gateway
        $this->notifiers = [
            'ipn' => 'listener',
            'cancel' => 'cancel_handler',
            'return' => 'return_handler',
        ];
        $this->message_pages = [
            'cancel' => 'cancel_message',
            'payment_failed' => 'payment_failed_message',
        ];
    }

    public function load($settings)
    {
        $this->settings = (object)$settings;
        $this->set_defaults();
    }

    protected function set_defaults()
    {
        if (!isset($this->settings)) {
            $this->settings = [];
        }

        $this->settings = (object)array_merge(
            [
                'gateway' => 'MeprPayPalGateway',
                'id' => $this->generate_id(),
                'label' => '',
                'use_label' => true,
                'icon' => MEPR_IMAGES_URL . '/checkout/paypal.png',
                'use_icon' => true,
                'desc' => __('Pay via your PayPal account', 'memberpress'),
                'use_desc' => true,
                'api_username' => '',
                'api_password' => '',
                'signature' => '',
                'sandbox' => false,
                'debug' => false,
            ],
            (array)$this->settings
        );

        $this->id = $this->settings->id;
        $this->label = $this->settings->label;
        $this->use_label = $this->settings->use_label;
        $this->icon = $this->settings->icon;
        $this->use_icon = $this->settings->use_icon;
        $this->desc = $this->settings->desc;
        $this->use_desc = $this->settings->use_desc;

        if ($this->is_test_mode()) {
            $this->settings->url     = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
            $this->settings->api_url = 'https://api-3t.sandbox.paypal.com/nvp';
        } else {
            $this->settings->url = 'https://ipnpb.paypal.com/cgi-bin/webscr';
            $this->settings->api_url = 'https://api-3t.paypal.com/nvp';
        }

        $this->settings->api_version = 69;

        // An attempt to correct people who paste in spaces along with their credentials
        $this->settings->api_username = trim($this->settings->api_username);
        $this->settings->api_password = trim($this->settings->api_password);
        $this->settings->signature    = trim($this->settings->signature);
    }

    /**
     * Override to add the '_ec'
     */
    public function slug()
    {
        return parent::slug() . '_ec';
    }

    /**
     * Listens for an incoming connection from PayPal and then handles the request appropriately.
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

    private function process_ipn()
    {
        $recurring_payment_txn_types  = ['recurring_payment', 'subscr_payment', 'recurring_payment_outstanding_payment'];
        $failed_txn_types             = ['recurring_payment_skipped', 'subscr_failed'];
        $payment_status_types         = ['denied','expired','failed'];
        $refunded_types               = ['refunded','reversed','voided'];
        $cancel_sub_types             = ['recurring_payment_profile_cancel', 'subscr_cancel', 'recurring_payment_suspended_due_to_max_failed_payment'];

        if (isset($_POST['txn_type']) && in_array(strtolower($_POST['txn_type']), $recurring_payment_txn_types)) {
            $this->record_subscription_payment();
        } elseif (
            ( isset($_POST['txn_type']) && in_array(strtolower($_POST['txn_type']), $failed_txn_types) ) ||
             ( isset($_POST['payment_status']) && in_array(strtolower($_POST['payment_status']), $payment_status_types) )
        ) {
            $this->record_payment_failure();
        } elseif (isset($_POST['txn_type']) && in_array(strtolower($_POST['txn_type']), $cancel_sub_types)) {
            $this->record_cancel_subscription();
        } elseif (isset($_POST['txn_type']) && strtolower($_POST['txn_type']) == 'recurring_payment_suspended') {
            $this->record_suspend_subscription();
        } elseif (isset($_POST['parent_txn_id']) && !isset($_POST['txn_type'])) {
            if (in_array(strtolower($_POST['payment_status']), $refunded_types)) {
                return $this->record_refund();
            }
        } elseif (isset($_POST['txn_type']) && $_POST['txn_type'] == 'recurring_payment_profile_created') {
            // Need to catch INITAMT's WITH THIS HOOK
            $this->maybe_catch_initamt();
        }
    }

    public function maybe_catch_initamt()
    {
        if (isset($_POST['initial_payment_amount'], $_POST['initial_payment_status']) && $_POST['initial_payment_amount'] >= 0.00 && strtolower($_POST['initial_payment_status']) == 'completed') {
            if (isset($_POST['subscr_id']) && !empty($_POST['subscr_id'])) {
                $sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_id']);
            } else {
                $sub = MeprSubscription::get_one_by_subscr_id($_POST['recurring_payment_id']);
            }

            if ($sub === false || !isset($sub->id) || (int)$sub->id <= 0) {
                return;
            } //If this isn't a sub, then why are we here (IPN fwd probably)

            // Just convert the confirmation into the initial payment
            $first_txn = $sub->first_txn();
            if ($first_txn == false || !($first_txn instanceof MeprTransaction)) {
                $first_txn = new MeprTransaction();
                $first_txn->user_id = $sub->user_id;
                $first_txn->product_id = $sub->product_id;
                $first_txn->coupon_id = $sub->coupon_id;
            }

            $first_txn->trans_num   = $_POST['initial_payment_txn_id'];
            $first_txn->txn_type    = MeprTransaction::$payment_str;
            $first_txn->status      = MeprTransaction::$complete_str;
            $first_txn->expires_at  = MeprUtils::ts_to_mysql_date(strtotime($_POST['next_payment_date']), 'Y-m-d 23:59:59');
            $first_txn->set_gross($_POST['initial_payment_amount']);
            $first_txn->store();

            // Check that the subscription status is still enabled
            if ($sub->status != MeprSubscription::$active_str) {
                $sub->status = MeprSubscription::$active_str;
                $sub->store();
            }

            // Not waiting for an IPN here bro ... just making it happen even though
            // the total occurrences is already capped in record_create_subscription()
            $sub->limit_payment_cycles();

            $this->email_status(
                "Subscription Transaction - INITAMT\n" .
                          MeprUtils::object_to_string($first_txn->rec, true),
                $this->settings->debug
            );

            MeprUtils::send_transaction_receipt_notices($first_txn);
        }
    }

    /**
     * Used to record a successful recurring payment by the given gateway. It
     * should have the ability to record a successful payment or a failure. It is
     * this method that should be used when receiving an IPN from PayPal or a
     * Silent Post from Authorize.net.
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
            $first_txn = $sub->first_txn();

            if ($first_txn == false || !($first_txn instanceof MeprTransaction)) {
                $first_txn = new MeprTransaction();
                $first_txn->user_id = $sub->user_id;
                $first_txn->product_id = $sub->product_id;
                $first_txn->coupon_id = $sub->coupon_id;
            }

            // Prevent recording duplicates
            $existing_txn = MeprTransaction::get_one_by_trans_num($_POST['txn_id']);
            if (
                isset($existing_txn->id) &&
                $existing_txn->id > 0 &&
                in_array($existing_txn->status, [MeprTransaction::$complete_str, MeprTransaction::$confirmed_str])
            ) {
                return;
            }

            // If this is a trial payment, let's just convert the confirmation txn into a payment txn
            // then we won't have to mess with setting expires_at as it was already handled
            if ($this->is_subscr_trial_payment($sub)) {
                $txn = $first_txn; // For use below in send notices
                $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);
                $txn->gateway    = $this->id;
                $txn->trans_num  = $_POST['txn_id'];
                $txn->txn_type   = MeprTransaction::$payment_str;
                $txn->status     = MeprTransaction::$complete_str;
                $txn->subscription_id = $sub->id;

                $txn->set_gross($_POST['mc_gross']);

                $txn->store();
            } else {
                $txn = new MeprTransaction();
                $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);
                $txn->user_id    = $first_txn->user_id;
                $txn->product_id = $first_txn->product_id;
                $txn->coupon_id  = $first_txn->coupon_id;
                $txn->gateway    = $this->id;
                $txn->trans_num  = $_POST['txn_id'];
                $txn->txn_type   = MeprTransaction::$payment_str;
                $txn->status     = MeprTransaction::$complete_str;
                $txn->subscription_id = $sub->id;

                $txn->set_gross($_POST['mc_gross']);

                $txn->store();

                // Check that the subscription status is still enabled
                if ($sub->status != MeprSubscription::$active_str) {
                    $sub->status = MeprSubscription::$active_str;
                    $sub->store();
                }

                // Not waiting for an IPN here bro ... just making it happen even though
                // the total occurrences is already capped in record_create_subscription()
                $sub->limit_payment_cycles();
            }

            $this->email_status(
                "Subscription Transaction\n" .
                          MeprUtils::object_to_string($txn->rec, true),
                $this->settings->debug
            );

            MeprUtils::send_transaction_receipt_notices($txn);

            return $txn;
        }

        return false;
    }

    /**
     * Used to record a declined payment.
     */
    public function record_payment_failure()
    {
        if (isset($_POST['ipn_track_id']) && ($txn_res = MeprTransaction::get_one_by_trans_num($_POST['ipn_track_id'])) && isset($txn_res->id)) {
            return false; // We've already recorded this failure duh - don't send more emails
        } elseif (isset($_POST['txn_id']) && ($txn_res = MeprTransaction::get_one_by_trans_num($_POST['txn_id'])) && isset($txn_res->id)) {
            $txn = new MeprTransaction($txn_res->id);
            $txn->status = MeprTransaction::$failed_str;
            $txn->store();
        } elseif (
            ( isset($_POST['recurring_payment_id']) and
              ($sub = MeprSubscription::get_one_by_subscr_id($_POST['recurring_payment_id'])) ) or
            ( isset($_POST['subscr_id']) and
              ($sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_id'])) )
        ) {
            $first_txn = $sub->first_txn();

            if ($first_txn == false || !($first_txn instanceof MeprTransaction)) {
                $first_txn = new MeprTransaction();
                $first_txn->user_id = $sub->user_id;
                $first_txn->product_id = $sub->product_id;
                $first_txn->coupon_id = $sub->coupon_id;
            }

            $txn = new MeprTransaction();
            $txn->user_id = $sub->user_id;
            $txn->product_id = $sub->product_id;
            $txn->coupon_id = $first_txn->coupon_id;
            $txn->txn_type = MeprTransaction::$payment_str;
            $txn->status = MeprTransaction::$failed_str;
            $txn->subscription_id = $sub->id;
            // if ipn_track_id isn't set then just use uniqid
            $txn->trans_num = ( isset($_POST['ipn_track_id']) ? $_POST['ipn_track_id'] : uniqid() );
            $txn->gateway = $this->id;

            $txn->set_gross(isset($_POST['mc_gross']) ? $_POST['mc_gross'] : ( isset($_POST['amount']) ? $_POST['amount'] : 0.00 ));

            $txn->store();

            $sub->expire_txns(); // Expire associated transactions for the old subscription
            $sub->store();
        } else {
            return false; // Nothing we can do here ... so we outta here
        }

        MeprUtils::send_failed_txn_notices($txn);

        return $txn;
    }

    /**
     * Used to send data to a given payment gateway. In gateways which redirect
     * before this step is necessary this method should just be left blank.
     */
    public function process_payment($txn)
    {
        $mepr_options = MeprOptions::fetch();
        $prd = $txn->product();
        $sub = $txn->subscription();
        $usr = $txn->user();
        $tkn = $_REQUEST['token'];
        $pid = $_REQUEST['PayerID'];

        $args = MeprHooks::apply_filters('mepr_paypal_ec_payment_args', [
            'TOKEN' => $tkn,
            'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
            'PAYMENTREQUEST_0_AMT' => $txn->total,
            'PAYMENTREQUEST_0_CURRENCYCODE' => $mepr_options->currency_code,
            'BUTTONSOURCE' => 'Caseproof_SP',
            'PAYERID' => $pid,
        ], $txn, $sub);

        $this->email_status("DoExpressCheckoutPayment Request:\n" . MeprUtils::object_to_string($args, true) . "\n", $this->settings->debug);

        $res = $this->send_nvp_request('DoExpressCheckoutPayment', $args);

        if (isset($res['ACK']) && strtolower($res['ACK']) != 'success') {
            MeprUtils::wp_redirect($this->message_page_url($prd, 'payment_failed'));
        }

        $this->email_status("DoExpressCheckoutPayment Response:\n" . MeprUtils::object_to_string($res, true) . "\n", $this->settings->debug);

        $_REQUEST['paypal_response'] = $res;
        $_REQUEST['transaction'] = $txn;

        return $this->record_payment();
    }

    /**
     * Used to record a successful payment by the given gateway. It should have
     * the ability to record a successful payment or a failure. It is this method
     * that should be used when receiving an IPN from PayPal or a Silent Post
     * from Authorize.net.
     */
    public function record_payment()
    {
        if (!isset($_REQUEST['paypal_response']) or !isset($_REQUEST['transaction'])) {
            return false;
        }

        $res = $_REQUEST['paypal_response'];
        $txn = $_REQUEST['transaction'];

        if ($txn->status == MeprTransaction::$complete_str) {
            return false;
        }

        if (isset($res['PAYMENTINFO_0_PAYMENTSTATUS'])) {
            if (strtolower($res['ACK']) == 'success' and strtolower($res['PAYMENTINFO_0_PAYMENTSTATUS']) == 'completed') {
                $txn->trans_num  = $res['PAYMENTINFO_0_TRANSACTIONID'];
                $txn->txn_type   = MeprTransaction::$payment_str;
                $txn->status     = MeprTransaction::$complete_str;
                // This will only work before maybe_cancel_old_sub is run
                $upgrade = $txn->is_upgrade();
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
        }

        return false;
    }

    /**
     * This method should be used by the class to record a successful refund from
     * the gateway. This method should also be used by any IPN requests or Silent Posts.
     */
    public function process_refund(MeprTransaction $txn)
    {
        $mepr_options = MeprOptions::fetch();

        $args = MeprHooks::apply_filters('mepr_paypal_ec_refund_args', [
            'TRANSACTIONID' => $txn->trans_num,
            'REFUNDTYPE' => 'Full',
            'CURRENCYCODE' => $mepr_options->currency_code,
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
     * This method should be used by the class to record a successful refund from
     * the gateway. This method should also be used by any IPN requests or Silent Posts.
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

            MeprUtils::send_refunded_txn_notices($txn);

            return $txn;
        }

        return false;
    }

    // Not needed in PayPal since PayPal supports the trial payment inclusive of the Subscription
    public function process_trial_payment($transaction)
    {
    }
    public function record_trial_payment($transaction)
    {
    }

    /**
     * Used to send subscription data to a given payment gateway. In gateways
     * which redirect before this step is necessary this method should just be
     * left blank.
     */
    public function process_create_subscription($txn)
    {
        $mepr_options = MeprOptions::fetch();
        $prd = $txn->product();
        $sub = $txn->subscription();
        $usr = $txn->user();
        $tkn = $sub->token;

        // IMPORTANT - PayPal txn will fail if the descriptions do not match exactly
        // so if you change the description here you also need to mirror it
        // inside of process_signup_form().
        $desc = $this->paypal_desc($txn);

        // Default to 0 for infinite occurrences
        $total_occurrences = $sub->limit_cycles ? $sub->limit_cycles_num : 0;

        // Having issues with subscription start times for our friends in Australia and New Zeland
        // There doesn't appear to be any fixes available from PayPal -- so we'll have to allow them to modify
        // the start time via this filter if it comes to that.
        $gmt_utc_time = MeprHooks::apply_filters('mepr-paypal-express-subscr-start-ts', time(), $this);

        $args = [
            'TOKEN' => $tkn,
            'PROFILESTARTDATE' => gmdate('Y-m-d\TH:i:s\Z', $gmt_utc_time),
            'DESC' => $desc,
            'BILLINGPERIOD' => $this->paypal_period($prd->period_type),
            'BILLINGFREQUENCY' => $prd->period,
            'TOTALBILLINGCYCLES' => $total_occurrences,
            'AMT' => MeprUtils::format_float($txn->total),
            'CURRENCYCODE' => $mepr_options->currency_code,
            'EMAIL' => $usr->user_email,
            'L_PAYMENTREQUEST_0_ITEMCATEGORY0' => 'Digital', // TODO: Assume this for now?
            'L_PAYMENTREQUEST_0_NAME0' => $prd->post_title,
            'L_PAYMENTREQUEST_0_AMT0' => MeprUtils::format_float($txn->total),
            'L_PAYMENTREQUEST_0_QTY0' => 1,
        ];

        // Make sure we don't send a trial > 365 days
        if ($sub->trial && $sub->trial_days > 365) {
            $sub->trial_days = 365;
            $sub->store();
        }

        if ($sub->trial && (!$this->trial_matches_billing_period($txn, $sub))) { // Fix for Ross
            $args = array_merge(
                [
                    'TRIALBILLINGPERIOD' => 'Day',
                    'TRIALBILLINGFREQUENCY' => $sub->trial_days,
                    'TRIALAMT' => $sub->trial_total,
                    'TRIALTOTALBILLINGCYCLES' => 1,
                ],
                $args
            );
        } else { // Charge the initial payment IMMEDIATELY using INITAMT - Only doing this when there's no trial period
            $args['INITAMT']              = $args['AMT']; // Set initial amount to the regular amount
            $args['FAILEDINITAMTACTION']  = 'CancelOnFailure';

            // Update the billing cycles as initamt doesn't count
            if ($total_occurrences >= 1) {
                $args['TOTALBILLINGCYCLES']  = ($total_occurrences - 1);
            }

            // Now adjust the start date to be one cycle out
            switch ($args['BILLINGPERIOD']) {
                case 'Week':
                    $args['PROFILESTARTDATE'] = strtotime($args['PROFILESTARTDATE']) + MeprUtils::weeks($args['BILLINGFREQUENCY']);
                    break;
                case 'Month':
                    $args['PROFILESTARTDATE'] = strtotime($args['PROFILESTARTDATE']) + MeprUtils::months($args['BILLINGFREQUENCY']);
                    break;
                case 'Year':
                    $args['PROFILESTARTDATE'] = strtotime($args['PROFILESTARTDATE']) + MeprUtils::years($args['BILLINGFREQUENCY']);
                    break;
                default:
                    $args['PROFILESTARTDATE'] = strtotime($args['PROFILESTARTDATE']) + MeprUtils::days($args['BILLINGFREQUENCY']);
                    break;
            }

            // Convert back to a gmdate
            $args['PROFILESTARTDATE'] = gmdate('Y-m-d\TH:i:s\Z', $args['PROFILESTARTDATE']);
        }

        $args = MeprHooks::apply_filters('mepr_paypal_ec_create_subscription_args', $args, $txn, $sub);

        $this->email_status("Paypal Create Subscription \$args:\n" . MeprUtils::object_to_string($args, true) . "\n", $this->settings->debug);

        $res = $this->send_nvp_request('CreateRecurringPaymentsProfile', $args);

        $_REQUEST['paypal_response'] = $res;
        $_REQUEST['transaction'] = $txn;
        $_REQUEST['subscription'] = $sub;

        return $this->record_create_subscription();
    }

    // So we can take advantage of INITAMT for trial payments
    public function trial_matches_billing_period($txn, $sub)
    {
        if (!$sub->trial) {
            return false;
        }
        if ($sub->trial && $sub->trial_amount <= 0) {
            return false;
        } //Don't do this for free trials
        if ($txn->total <= 0) {
            return false;
        } //This shouldn't ever happen
        if ($sub->price != $sub->trial_amount) {
            return false;
        } //Trial amount is different

        // Monthly
        if ($sub->period_type == 'months' && !($sub->trial_days % 30) && $sub->period == ($sub->trial_days / 30)) {
            return true;
        }

        // Yearly
        if ($sub->period_type == 'years' && !($sub->trial_days % 365) && $sub->period == ($sub->trial_days / 365)) {
            return true;
        }

        return false;
    }

    /**
     * Used to record a successful subscription by the given gateway. It should have
     * the ability to record a successful subscription or a failure. It is this method
     * that should be used when receiving an IPN from PayPal or a Silent Post
     * from Authorize.net.
     */
    public function record_create_subscription()
    {
        $res = $_REQUEST['paypal_response'];
        $sub = $_REQUEST['subscription'];
        $this->email_status("Paypal Create Subscription Response \$res:\n" . MeprUtils::object_to_string($res, true) . "\n", $this->settings->debug);

        if (isset($res['L_ERRORCODE0']) and intval($res['L_ERRORCODE0']) == 10004) {
            $this->send_digital_goods_error_message();
            return false;
        }

        if (isset($res['PROFILESTATUS']) and ( strtolower($res['PROFILESTATUS']) == 'activeprofile' || strtolower($res['PROFILESTATUS']) == 'pendingprofile' )) {
            $timestamp = isset($res['TIMESTAMP']) ? strtotime($res['TIMESTAMP']) : time();

            $sub->subscr_id = $res['PROFILEID'];
            $sub->status = MeprSubscription::$active_str;
            $sub->created_at = gmdate('c', $timestamp);
            $sub->store();

            $txn = $sub->first_txn();
            if ($txn == false || !($txn instanceof MeprTransaction)) {
                $txn = new MeprTransaction();
                $txn->user_id = $sub->user_id;
                $txn->product_id = $sub->product_id;
                $txn->coupon_id = $sub->coupon_id;
            }

            $old_total = $txn->total;
            $txn->trans_num  = $res['PROFILEID'];
            $txn->status     = MeprTransaction::$confirmed_str;
            $txn->txn_type   = MeprTransaction::$subscription_confirmation_str;
            $txn->set_subtotal(0.00); // Just a confirmation txn

            // At the very least the subscription confirmation transaction gives
            // the user a 24 hour grace period so they can log in even before the
            // paypal transaction goes through (paypal batches txns at night)
            $mepr_options = MeprOptions::fetch();

            $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);

            if ($sub->trial) {
                $expires_at = MeprUtils::ts_to_mysql_date($timestamp + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
            } elseif (!$mepr_options->disable_grace_init_days && $mepr_options->grace_init_days > 0) {
                $expires_at = MeprUtils::ts_to_mysql_date($timestamp + MeprUtils::days($mepr_options->grace_init_days), 'Y-m-d 23:59:59');
            } else {
                $expires_at = $txn->created_at; // Expire immediately
            }

            $txn->expires_at = $expires_at;
            $txn->store(true);

            // This will only work before maybe_cancel_old_sub is run
            $upgrade = $sub->is_upgrade();
            $downgrade = $sub->is_downgrade();

            $event_txn = $sub->maybe_cancel_old_sub();

            $this->email_status(
                "Subscription Transaction\n" .
                           MeprUtils::object_to_string($txn->rec, true),
                $this->settings->debug
            );

            // $txn->set_gross($old_total); // Artificially set the old amount for notices
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
                'transaction' => $txn,
            ];
        }
    }

    /**
     * Used to cancel a subscription by the given gateway. This method should be used
     * by the class to record a successful cancellation from the gateway. This method
     * should also be used by any IPN requests or Silent Posts.
     *
     * With PayPal, we bill the outstanding amount of the previous subscription,
     * cancel the previous subscription and create a new subscription
     */
    public function process_update_subscription($sub_id)
    {
        // Account info updated on PayPal.com
    }

    /**
     * This method should be used by the class to record a successful cancellation
     * from the gateway. This method should also be used by any IPN requests or
     * Silent Posts.
     */
    public function record_update_subscription()
    {
        // Account info updated on PayPal.com
    }

    /**
     * Used to suspend a subscription by the given gateway.
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
     * This method should be used by the class to record a successful suspension
     * from the gateway.
     */
    public function record_suspend_subscription()
    {
        $subscr_id = $_REQUEST['recurring_payment_id'];
        $sub = MeprSubscription::get_one_by_subscr_id($subscr_id);

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
     * Used to suspend a subscription by the given gateway.
     */
    public function process_resume_subscription($sub_id)
    {
        $sub = new MeprSubscription($sub_id);
        // Maybe look into this to do a payment right away
        // https://developer.paypal.com/docs/classic/api/merchant/DoReferenceTransaction_API_Operation_NVP/
        $this->update_paypal_payment_profile($sub_id, 'Reactivate');

        $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
        $this->record_resume_subscription();
    }

    /**
     * This method should be used by the class to record a successful resuming of
     * as subscription from the gateway.
     */
    public function record_resume_subscription()
    {
        $subscr_id = $_REQUEST['recurring_payment_id'];
        $sub = MeprSubscription::get_one_by_subscr_id($subscr_id);

        if (!$sub) {
            return false;
        }

        // Seriously ... if sub was already active what are we doing here?
        if ($sub->status == MeprSubscription::$active_str) {
            return $sub;
        }

        $sub->status = MeprSubscription::$active_str;
        $sub->store();

        // Check if prior txn is expired yet or not, if so create a temporary txn so the user can access the content immediately
        $prior_txn = $sub->latest_txn();
        if ($prior_txn == false || !($prior_txn instanceof MeprTransaction) || strtotime($prior_txn->expires_at) < time()) {
            $txn = new MeprTransaction();
            $txn->subscription_id = $sub->id;
            $txn->trans_num  = $sub->subscr_id . '-' . uniqid();
            $txn->status     = MeprTransaction::$confirmed_str;
            $txn->txn_type   = MeprTransaction::$subscription_confirmation_str;
            $txn->expires_at = MeprUtils::ts_to_mysql_date($sub->get_expires_at());
            $txn->set_subtotal(0.00); // Just a confirmation txn
            $txn->store();
        }

        MeprUtils::send_resumed_sub_notices($sub);

        return $sub;
    }

    /**
     * Used to cancel a subscription by the given gateway. This method should be used
     * by the class to record a successful cancellation from the gateway. This method
     * should also be used by any IPN requests or Silent Posts.
     */
    public function process_cancel_subscription($sub_id)
    {
        $sub = new MeprSubscription($sub_id);

        // Should already expire naturally at paypal so we have no need
        // to do this when we're "cancelling" because of a natural expiration
        if (!isset($_REQUEST['expire'])) {
            $this->update_paypal_payment_profile($sub_id, 'Cancel');
        }

        $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
        $this->record_cancel_subscription();
    }

    /**
     * This method should be used by the class to record a successful cancellation
     * from the gateway. This method should also be used by any IPN requests or
     * Silent Posts.
     */
    public function record_cancel_subscription()
    {
        if (!isset($_REQUEST['recurring_payment_id'])) {
            return false;
        }

        $subscr_id = $_REQUEST['recurring_payment_id'];
        $sub = MeprSubscription::get_one_by_subscr_id($subscr_id);

        if (!$sub) {
            return false;
        }

        // Seriously ... if sub was already cancelled what are we doing here?
        if ($sub->status == MeprSubscription::$cancelled_str) {
            return $sub;
        }

        $sub->status = MeprSubscription::$cancelled_str;
        $sub->store();

        // Expire the grace period (confirmation) if no completed payments have come through
        // If sub had a free trial, we shouldn't expire that
        if ((int)$sub->txn_count <= 0 && (!$sub->trial || $sub->trial_amount > 0)) {
            $sub->expire_txns();
        }

        if (isset($_REQUEST['expire'])) {
            $sub->limit_reached_actions();
        }

        if (!isset($_REQUEST['silent']) || ($_REQUEST['silent'] == false)) {
            MeprUtils::send_cancelled_sub_notices($sub);
        }

        return $sub;
    }

    public function process_signup_form($txn)
    {
        // Nothing here yet
    }

    /**
     * This gets called on the 'init' hook when the signup form is processed ...
     * this is in place so that payment solutions like paypal can redirect
     * before any content is rendered.
     */
    public function display_payment_page($txn)
    {
        $mepr_options = MeprOptions::fetch();

        if (isset($txn) and $txn instanceof MeprTransaction) {
            $usr = $txn->user();
            $prd = $txn->product();
        } else {
            return false;
        }

        if ($txn->amount <= 0.00) {
            // Take care of this in display_payment_page
            // MeprTransaction::create_free_transaction($txn);
            return $txn->checkout_url();
        }

        if ($txn->gateway == $this->id) {
            $mepr_options = MeprOptions::fetch();
            $invoice      = $txn->id . '-' . time();
            $useraction = '';

            // IMPORTANT - PayPal txn will fail if the descriptions do not match exactly
            // so if you change the description here you also need to mirror it
            // inside of process_create_subscription().
            $desc = $this->paypal_desc($txn);

            $billing_type = (($prd->is_one_time_payment()) ? 'MerchantInitiatedBilling' : 'RecurringPayments');
            $args = [
                'PAYMENTREQUEST_0_AMT' => MeprUtils::format_float($txn->total),
                'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale', // Transaction or order? Transaction I assume?
                'PAYMENTREQUEST_0_DESC' => $desc, // Better way to get description working on lifetimes
                'L_BILLINGAGREEMENTDESCRIPTION0' => $desc,
                'L_BILLINGTYPE0' => $billing_type,
                'RETURNURL' => $this->notify_url('return'),
                'CANCELURL' => $this->notify_url('cancel'),
                'L_BILLINGAGREEMENTCUSTOM0' => $txn->id, // Ignored when RecurringPayments is the Type
                'L_PAYMENTTYPE0' => 'InstantOnly', // Ignored when RecurringPayments is the Type
                'PAYMENTREQUEST_0_CURRENCYCODE' => $mepr_options->currency_code,
                'NOSHIPPING' => 1, /*
                                       , // The following two lines are for payments w/out PayPal account (non recurring)
                                       'SOLUTIONTYPE' => 'Sole',
            'LANDINGPAGE' => 'Billing' */
            ];

            $args = MeprHooks::apply_filters('mepr_paypal_express_checkout_args', $args);
            $this->email_status(
                "MemberPress PayPal Request: \n" . MeprUtils::object_to_string($args, true) . "\n",
                $this->settings->debug
            );

            $res = $this->send_nvp_request('SetExpressCheckout', $args);

            $this->email_status(
                "PayPal Response Object: \n" . MeprUtils::object_to_string($res, true) . "\n",
                $this->settings->debug
            );

            $token = '';
            $ack = strtoupper($res['ACK']);
            if ($ack == 'SUCCESS' || $ack == 'SUCCESSWITHWARNING') {
                $txn->trans_num = $token = urldecode($res['TOKEN']);
                $txn->store();

                if (!$prd->is_one_time_payment() && ($sub = $txn->subscription())) {
                    $sub->token = $token;
                    $sub->store();
                }

                MeprUtils::wp_redirect("{$this->settings->url}?cmd=_express-checkout&token={$token}{$useraction}");
            } else {
                throw new Exception(__('The connection to PayPal failed', 'memberpress'));
            }
        }

        throw new Exception(__('There was a problem completing the transaction', 'memberpress'));
    }

    /**
     * This gets called on wp_enqueue_script and enqueues a set of
     * scripts for use on the page containing the payment form
     */
    public function enqueue_payment_form_scripts()
    {
        // No need, handled on the PayPal side
    }

    /**
     * This gets called on the_content and just renders the payment form
     */
    public function display_payment_form($amount, $user, $product_id, $transaction_id)
    {
        // Handled on the PayPal site so we don't have a need for it here
    }

    /**
     * Validates the payment form before a payment is processed
     */
    public function validate_payment_form($errors)
    {
        // PayPal does this on their own form
    }

    /**
     * Displays the form for the given payment gateway on the MemberPress Options page
     */
    public function display_options_form()
    {
        $mepr_options = MeprOptions::fetch();

        $api_username = trim($this->settings->api_username);
        $api_password = trim($this->settings->api_password);
        $signature    = trim($this->settings->signature);
        $sandbox      = ($this->settings->sandbox == 'on' or $this->settings->sandbox == true);
        $debug        = ($this->settings->debug == 'on' or $this->settings->debug == true);

        ?>
    <table>
      <tr>
        <td><?php _e('API Username*:', 'memberpress'); ?></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][api_username]" value="<?php echo $api_username; ?>" /></td>
      </tr>
      <tr>
        <td><?php _e('API Password*:', 'memberpress'); ?></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][api_password]" value="<?php echo $api_password; ?>" /></td>
      </tr>
      <tr>
        <td><?php _e('Signature*:', 'memberpress'); ?></td>
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
        <?php MeprHooks::do_action('mepr-paypal-express-options-form', $this); ?>
    </table>
        <?php
    }

    /**
     * Validates the form for the given payment gateway on the MemberPress Options page
     */
    public function validate_options_form($errors)
    {
        $mepr_options = MeprOptions::fetch();

        if (
            !isset($_POST[$mepr_options->integrations_str][$this->id]['api_username']) or
            empty($_POST[$mepr_options->integrations_str][$this->id]['api_username'])
        ) {
            $errors[] = __("PayPal API Username field can't be blank.", 'memberpress');
        } elseif (
            !isset($_POST[$mepr_options->integrations_str][$this->id]['api_password']) or
            empty($_POST[$mepr_options->integrations_str][$this->id]['api_password'])
        ) {
            $errors[] = __("PayPal API Password field can't be blank.", 'memberpress');
        } elseif (
            !isset($_POST[$mepr_options->integrations_str][$this->id]['signature']) or
            empty($_POST[$mepr_options->integrations_str][$this->id]['signature'])
        ) {
            $errors[] = __("PayPal Signature field can't be blank.", 'memberpress');
        }

        return $errors;
    }

    /**
     * Displays the update account form on the subscription account page
     **/
    public function display_update_account_form($sub_id, $errors = [], $message = '')
    {
        ?>
    <h3><?php _e('Updating your PayPal Account Information', 'memberpress'); ?></h3>
    <div><?php printf(__('To update your PayPal Account Information, please go to %1$sPayPal.com%2$s, login and edit your account information there.', 'memberpress'), '<a href="http://paypal.com" target="blank">', '</a>'); ?></div>
        <?php
    }

    /**
     * Validates the payment form before a payment is processed
     */
    public function validate_update_account_form($errors = [])
    {
        // We'll have them update their cc info on paypal.com
    }

    /**
     * Actually pushes the account update to the payment processor
     */
    public function process_update_account_form($sub_id)
    {
        // We'll have them update their cc info on paypal.com
    }

    /**
     * Returns boolean ... whether or not we should be sending in test mode or not
     */
    public function is_test_mode()
    {
        return (isset($this->settings->sandbox) and $this->settings->sandbox);
    }

    public function force_ssl()
    {
        return false; // redirects off site where ssl is installed
    }

    private function send_nvp_request($method_name, $args, $method = 'post', $blocking = true)
    {
        $mepr_options = MeprOptions::fetch();
        $args = array_merge(
            [
                'METHOD'    => $method_name,
                'VERSION'   => $this->settings->api_version,
                'PWD'       => $this->settings->api_password,
                'USER'      => $this->settings->api_username,
                'SIGNATURE' => $this->settings->signature,
            ],
            $args
        );

        $args = MeprHooks::apply_filters('mepr_paypal_ec_send_request_args', $args);

        $arg_array = MeprHooks::apply_filters('mepr_paypal_ec_send_request', [
            'method'    => strtoupper($method),
            'body'      => $args,
            'timeout'   => 15,
            'httpversion' => '1.1', // PayPal is now requiring this
            'blocking'  => $blocking,
            'sslverify' => $mepr_options->sslverify,
            'headers'   => [],
        ]);

        // $this->email_status("Sending Paypal Request\n" . MeprUtils::object_to_string($arg_array, true) . "\n", $this->settings->debug);
        $resp = wp_remote_request($this->settings->api_url, $arg_array);
        // $this->email_status("Got Paypal Response\n" . MeprUtils::object_to_string($resp, true) . "\n", $this->settings->debug);
        // If we're not blocking then the response is irrelevant
        // So we'll just return true.
        if ($blocking == false) {
            return true;
        }

        if (is_wp_error($resp)) {
            throw new MeprHttpException(sprintf(__('You had an HTTP error connecting to %s', 'memberpress'), $this->name));
        } else {
            return wp_parse_args($resp['body']);
        }

        return false;
    }

    public function return_handler()
    {
        // Handled with a GET REQUEST by PayPal
        $this->email_status("Paypal Return \$_REQUEST:\n" . MeprUtils::object_to_string($_REQUEST, true) . "\n", $this->settings->debug);

        $mepr_options = MeprOptions::fetch();

        if (
            (isset($_REQUEST['token']) && ($token = $_REQUEST['token'])) ||
            (isset($_REQUEST['TOKEN']) && ($token = $_REQUEST['TOKEN']))
        ) {
            $obj = MeprTransaction::get_one_by_trans_num($token);

            $txn = new MeprTransaction();
            $txn->load_data($obj);

            $this->email_status("Paypal Transaction \$txn:\n" . MeprUtils::object_to_string($txn, true) . "\n", $this->settings->debug);

            try {
                $this->process_payment_form($txn);
                $txn = new MeprTransaction($txn->id); // Grab the txn again, now that we've updated it
                $product = new MeprProduct($txn->product_id);
                $sanitized_title = sanitize_title($product->post_title);
                $query_params = [
                    'membership' => $sanitized_title,
                    'trans_num' => $txn->trans_num,
                    'membership_id' => $product->ID,
                ];
                if ($txn->subscription_id > 0) {
                    $sub = $txn->subscription();
                    $query_params = array_merge($query_params, ['subscr_id' => $sub->subscr_id]);
                }

                $thankyou_url = $this->do_thankyou_url($query_params, $txn);
                MeprUtils::wp_redirect($thankyou_url);
            } catch (Exception $e) {
                $prd = $txn->product();
                MeprUtils::wp_redirect($prd->url('?action=payment_form&txn=' . $txn->trans_num . '&message=' . $e->getMessage() . '&_wpnonce=' . wp_create_nonce('mepr_payment_form')));
            }
        }
    }

    public function cancel_handler()
    {
        // Handled with a GET REQUEST by PayPal
        $this->email_status("Paypal Cancel \$_REQUEST:\n" . MeprUtils::object_to_string($_REQUEST, true) . "\n", $this->settings->debug);

        if (isset($_REQUEST['token']) and ($token = $_REQUEST['token'])) {
            $txn = MeprTransaction::get_one_by_trans_num($token);
            $txn = new MeprTransaction($txn->id);

            // Make sure the txn status is pending
            $txn->status = MeprTransaction::$pending_str;
            $txn->store();

            if ($sub = $txn->subscription()) {
                $sub->status = MeprSubscription::$pending_str;
                $sub->store();
            }

            if ($txn) {
                $prd = new MeprProduct($txn->product_id);
                // TODO: Send an abandonment email
                MeprUtils::wp_redirect($this->message_page_url($prd, 'cancel'));
            } else {
                MeprUtils::wp_redirect(home_url());
            }
        }
    }

    public function cancel_message()
    {
        $mepr_options = MeprOptions::fetch();
        ?>
      <h4><?php _e('Your payment at PayPal was cancelled.', 'memberpress'); ?></h4>
      <p><?php echo MeprHooks::apply_filters('mepr_paypal_cancel_message', sprintf(__('You can retry your purchase by %1$sclicking here%2$s.', 'memberpress'), '<a href="' . MeprUtils::get_permalink() . '">', '</a>')); ?><br/></p>
        <?php
    }

    public function payment_failed_message()
    {
        $mepr_options = MeprOptions::fetch();
        ?>
      <h4><?php _e('Your payment at PayPal failed.', 'memberpress'); ?></h4>
      <p><?php echo MeprHooks::apply_filters('mepr_paypal_cancel_message', sprintf(__('You can retry your purchase by %1$sclicking here%2$s. If you continue having troubles paying, please contact PayPal support to find out why the payments are not being approved.', 'memberpress'), '<a href="' . MeprUtils::get_permalink() . '">', '</a>')); ?><br/></p>
        <?php
    }

    public function paypal_period($period_type)
    {
        if ($period_type == 'months') {
            return 'Month';
        } elseif ($period_type == 'years') {
            return 'Year';
        } elseif ($period_type == 'weeks') {
            return 'Week';
        } else {
            return $period_type;
        }
    }

    private function send_digital_goods_error_message()
    {
        $subject = sprintf(__('** PayPal Payment ERROR on %s', 'memberpress'), MeprUtils::blogname());
        $body = __('Your PayPal account isn\'t set up to sell Digital Goods.

PayPal is no longer accepting new signups for Digital Goods (via Express Checkout).

If your PayPal account is not newly opened, you may be able to contact PayPal\'s 2nd or 3rd tier support engineers and get Digital Goods enabled for Express Checkout. A little persistence here is sometimes what it takes to make it happen.

If their support cannot, or will not activate Digital Goods for you, then you will need to switch MemberPress to use the PayPal Standard gateway integration instead.

<a href="https://memberpress.com/docs/paypal-standard/">MemberPress - PayPal Standard Gateway Integration Instructions</a>

Thanks,

The MemberPress Team
', 'memberpress');

        MeprUtils::wp_mail_to_admin($subject, $body);
    }

    private function paypal_desc($txn)
    {
        $prd = new MeprProduct($txn->product_id);

        if ($prd->register_price_action == 'hidden' && !empty($prd->post_title)) {
            return $prd->post_title;
        } elseif ($prd->register_price_action == 'custom' && !empty($prd->register_price) && !$txn->coupon_id && !$txn->prorated) {
            return "{$prd->post_title} - " . stripslashes($prd->register_price);
        } else {
            return "{$prd->post_title} - " . MeprTransactionsHelper::format_currency($txn);
        }
    }

    private function update_paypal_payment_profile($sub_id, $action = 'cancel')
    {
        $sub = new MeprSubscription($sub_id);

        $args = MeprHooks::apply_filters('mepr_paypal_ec_update_payment_profile_args', [
            'PROFILEID' => $sub->subscr_id,
            'ACTION' => $action,
        ], $sub);

        $this->email_status(
            "PayPal Update subscription request: \n" . MeprUtils::object_to_string($args, true) . "\n",
            $this->settings->debug
        );

        $res = $this->send_nvp_request('ManageRecurringPaymentsProfileStatus', $args);

        $this->email_status(
            "PayPal Update subscription response: \n" . MeprUtils::object_to_string($res, true) . "\n",
            $this->settings->debug
        );

        if (strtolower($res['ACK']) != 'success') {
            throw new MeprGatewayException(__('There was a problem cancelling, try logging in directly at PayPal to update the status of your recurring profile.', 'memberpress'));
        }


        $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
    }
}
