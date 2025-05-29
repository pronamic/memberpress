<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

abstract class MeprBaseRealGateway extends MeprBaseGateway
{
    /**
     * Activate the subscription
     *
     * Also sets up the grace period confirmation transaction (if enabled).
     *
     * @param MeprTransaction  $txn            The MemberPress transaction.
     * @param MeprSubscription $sub            The MemberPress subscription.
     * @param boolean          $set_trans_num  Whether to set the txn trans_num to the sub subscr_id.
     * @param boolean          $set_created_at Whether to set the created_at timestamp.
     */
    public function activate_subscription(MeprTransaction $txn, MeprSubscription $sub, $set_trans_num = true, $set_created_at = true)
    {
        $mepr_options = MeprOptions::fetch();

        $sub->status = MeprSubscription::$active_str;

        if ($set_created_at) {
            $sub->created_at = gmdate('c');
        }

        $sub->store();

        // If trial amount is zero then we've got to make sure the confirmation txn lasts through the trial.
        if ($sub->trial && $sub->trial_amount <= 0.00) {
            $trial_days = $sub->trial_days;

            if (!$mepr_options->disable_grace_init_days && $mepr_options->grace_init_days > 1) {
                $trial_days += $mepr_options->grace_init_days;
            }

            $expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($trial_days), 'Y-m-d 23:59:59');
        } elseif (!$mepr_options->disable_grace_init_days && $mepr_options->grace_init_days > 0) {
            $expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($mepr_options->grace_init_days), 'Y-m-d 23:59:59');
        } else {
            $expires_at = $txn->created_at; // Expire immediately.
        }

        if ($set_trans_num) {
            $txn->trans_num = $sub->subscr_id;
        }

        $txn->status     = MeprTransaction::$confirmed_str;
        $txn->txn_type   = MeprTransaction::$subscription_confirmation_str;
        $txn->expires_at = $expires_at;
        $txn->set_subtotal(0.00); // Just a confirmation txn.

        // Ensure that the `mepr-txn-store` hook is called with an active subscription.
        $txn->store(true);
    }

    /**
     * Get the order bumps from the 'obs' $_GET param.
     *
     * It will return instances of the product, transaction and subscription (subscription will be null for one-time
     * payments). These are unsaved instances, not saved to the database.
     *
     * @param  integer $ignore_product_id The main product for the purchase, order bumps with this ID will be ignored.
     * @return array                      An array of arrays. Each inner array will contain instances of the product,
     *                                    transaction and subscription (subscription will be null for one-time payments).
     */
    protected function get_order_bumps(int $ignore_product_id): array
    {
        $order_bumps = [];

        try {
            $order_bump_product_ids = isset($_GET['obs']) && is_array($_GET['obs']) ? array_map('intval', $_GET['obs']) : [];
            $order_bump_products    = MeprCheckoutCtrl::get_order_bump_products($ignore_product_id, $order_bump_product_ids);

            foreach ($order_bump_products as $product) {
                list($transaction, $subscription) = MeprCheckoutCtrl::prepare_transaction(
                    $product,
                    0,
                    get_current_user_id(),
                    'manual',
                    false,
                    false
                );

                $order_bumps[] = [$product, $transaction, $subscription];
            }
        } catch (Exception $e) {
            // Ignore exception.
        }

        return $order_bumps;
    }

    /**
     * Process an order.
     *
     * If there is an order bump, an order will be created and a transaction created for each order bump.
     * If no payment is due, it will redirect to the thank-you page.
     *
     * @param  MeprTransaction $txn                 The transaction for the main product being purchased.
     * @param  MeprProduct[]   $order_bump_products The array of order bump products.
     * @return MeprTransaction[]                    The array of order bump transactions
     * @throws Exception When order processing fails or validation errors occur with any transaction.
     */
    public function process_order(MeprTransaction $txn, array $order_bump_products = [])
    {
        $prd              = $txn->product();
        $cpn              = $txn->coupon();
        $payment_required = $prd->is_payment_required($cpn instanceof MeprCoupon ? $cpn->post_title : null);
        $order_bumps      = [];
        $order            = $txn->order();

        if (count($order_bump_products)) {
            $order                         = new MeprOrder();
            $order->user_id                = $txn->user_id;
            $order->primary_transaction_id = $txn->id;
            $order->gateway                = $this->id;
            $order->store();

            $txn->order_id = $order->id;
            $txn->store();

            $sub = $txn->subscription();

            if ($sub instanceof MeprSubscription) {
                $sub->order_id = $order->id;
                $sub->store();
            }

            foreach ($order_bump_products as $product) {
                list($transaction) = MeprCheckoutCtrl::prepare_transaction(
                    $product,
                    $order->id,
                    $txn->user_id,
                    $this->id
                );

                if ($product->is_payment_required()) {
                    $payment_required = true;
                }

                $order_bumps[] = $transaction;
            }
        }

        if (!$payment_required) {
            MeprTransaction::create_free_transaction($txn, false);

            foreach ($order_bumps as $order_bump_txn) {
                MeprTransaction::create_free_transaction($order_bump_txn, false);
            }

            if ($order instanceof MeprOrder) {
                $order->status  = 'complete';
                $order->gateway = MeprTransaction::$free_gateway_str;
                $order->store();
            }

            $mepr_options    = MeprOptions::fetch();
            $sanitized_title = sanitize_title($prd->post_title);
            $query_params    = [
                'membership'    => $sanitized_title,
                'trans_num'     => $txn->trans_num,
                'membership_id' => $prd->ID,
            ];

            MeprUtils::wp_redirect($mepr_options->thankyou_page_url(build_query($query_params)));
        }

        return $order_bumps;
    }

    /**
     * Record a successful payment for a one-time payment product.
     *
     * @param MeprTransaction $txn       The transaction instance.
     * @param string          $trans_num The transaction number to set.
     */
    public function record_one_time_payment(MeprTransaction $txn, $trans_num)
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
        MeprUtils::send_cc_expiration_notices($txn);
    }

    /**
     * Record the creation of a new subscription.
     *
     * @param MeprSubscription $sub The subscription.
     */
    public function record_create_sub(MeprSubscription $sub)
    {
        $txn = $sub->first_txn();

        if (!($txn instanceof MeprTransaction)) {
            $txn                  = new MeprTransaction();
            $txn->user_id         = $sub->user_id;
            $txn->product_id      = $sub->product_id;
            $txn->gateway         = $this->id;
            $txn->subscription_id = $sub->id;
            $txn->order_id        = $sub->order_id;
        }

        $this->activate_subscription($txn, $sub, true, $sub->txn_count < 1);

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

        MeprHooks::do_action("mepr_{$this->key}_subscription_created", $txn, $sub);
    }

    /**
     * Records a subscription payment and sends a receipt notification email.
     *
     * @param  MeprSubscription $sub            The subscription object associated with the payment.
     * @param  float            $amount         The amount of the payment to be recorded.
     * @param  string           $trans_num      The transaction number associated with the payment.
     * @param  array|null       $card           Optional card information to store with the subscription.
     * @param  string|null      $txn_expires_at Optional expiration date override for the transaction.
     * @param  integer          $order_id       Optional order ID associated with the payment. Defaults to 0.
     * @return void
     */
    public function record_sub_payment(
        MeprSubscription $sub,
        $amount,
        $trans_num,
        $card = null,
        $txn_expires_at = null,
        $order_id = 0
    ) {
        if (MeprTransaction::txn_exists($trans_num)) {
            return;
        }

        $first_txn = $sub->first_txn();

        if ($first_txn == false || !($first_txn instanceof MeprTransaction)) {
            $coupon_id = $sub->coupon_id;
        } else {
            $coupon_id = $first_txn->coupon_id;
        }

        $txn                  = new MeprTransaction();
        $txn->user_id         = $sub->user_id;
        $txn->product_id      = $sub->product_id;
        $txn->status          = MeprTransaction::$complete_str;
        $txn->coupon_id       = $coupon_id;
        $txn->trans_num       = $trans_num;
        $txn->gateway         = $this->id;
        $txn->subscription_id = $sub->id;
        $txn->order_id        = $order_id;
        $txn->set_gross($amount);

        if (!is_null($txn_expires_at)) {
            $txn->expires_at = $txn_expires_at;
        }

        $txn->store();

        // Reload the subscription in case it was modified while storing the transaction.
        $sub          = new MeprSubscription($sub->id);
        $sub->gateway = $this->id;
        $sub->status  = MeprSubscription::$active_str;

        if (is_array($card)) {
            $sub->cc_last4     = $card['last4'] ?? '';
            $sub->cc_exp_month = $card['exp_month'] ?? '';
            $sub->cc_exp_year  = $card['exp_year'] ?? '';
        }

        $sub->store();

        // If a limit was set on the recurring cycles we need
        // to cancel the subscr if the txn_count >= limit_cycles_num.
        $sub->limit_payment_cycles();

        MeprUtils::send_transaction_receipt_notices($txn);
        MeprUtils::send_cc_expiration_notices($txn);

        do_action("mepr_{$this->key}_record_sub_payment", $txn, $sub);
    }
}
