<?php

abstract class MeprBaseRealAjaxGateway extends MeprBaseRealGateway
{
    /**
     * Process a payment via Ajax.
     *
     * @param MeprProduct           $prd The product.
     * @param MeprUser              $usr The user.
     * @param MeprTransaction       $txn The transaction.
     * @param MeprSubscription|null $sub The subscription (null for one-time payments).
     * @param MeprCoupon|null       $cpn The coupon (null if no coupon).
     */
    abstract public function process_payment_ajax(
        MeprProduct $prd,
        MeprUser $usr,
        MeprTransaction $txn,
        MeprSubscription $sub = null,
        MeprCoupon $cpn = null
    );

    /**
     * Is this gateway configured to support Ajax processing?
     *
     * @return boolean
     */
    public function validate_ajax_gateway(): bool
    {
        return true;
    }

    /**
     * Process the order bumps in the request, and return the created transactions, total amount and whether at least
     * one order bump is a subscription.
     *
     * @param  MeprProduct           $prd The product.
     * @param  MeprUser              $usr The user.
     * @param  MeprTransaction       $txn The transaction.
     * @param  MeprSubscription|null $sub The subscription (null for one-time payments).
     * @return array{0: MeprTransaction[], 1: float, 2: boolean} The array of order bump transactions, the amount and
     *         whether any of the order bumps is a recurring subscription.
     */
    protected function process_order_bumps(MeprProduct $prd, MeprUser $usr, MeprTransaction $txn, MeprSubscription $sub = null): array
    {
        try {
            $product_ids      = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_map('intval', $_POST['mepr_order_bumps']) : [];
            $products         = MeprCheckoutCtrl::get_order_bump_products($prd->ID, $product_ids);
            $total            = 0.00;
            $transactions     = [];
            $has_subscription = false;

            if (count($products)) {
                $order                         = new MeprOrder();
                $order->user_id                = $usr->ID;
                $order->primary_transaction_id = $txn->id;
                $order->gateway                = $this->id;
                $order->store();

                $txn->order_id = $order->id;
                $txn->store();

                if ($sub instanceof MeprSubscription) {
                    $sub->order_id = $order->id;
                    $sub->store();
                }

                foreach ($products as $product) {
                    list($transaction, $subscription) = MeprCheckoutCtrl::prepare_transaction(
                        $product,
                        $order->id,
                        $usr->ID,
                        $this->id
                    );

                    if ($product->is_one_time_payment()) {
                        if ((float) $transaction->total > 0) {
                            $total += (float) $transaction->total;
                        }
                    } else {
                        if (!($subscription instanceof MeprSubscription)) {
                            wp_send_json_error(__('Subscription not found', 'memberpress'));
                        }

                        $has_subscription = true;

                        if ($subscription->trial && $subscription->trial_days > 0) {
                            if ((float) $subscription->trial_total > 0) {
                                $total += (float) $subscription->trial_total;
                            }
                        } else {
                            if ((float) $subscription->total > 0) {
                                $total += (float) $subscription->total;
                            }
                        }
                    }

                    $transactions[] = $transaction;
                }
            }

            return [$transactions, $total, $has_subscription];
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Process an account update form via Ajax.
     *
     * @param MeprSubscription $sub The subscription to be updated.
     *
     * @throws MeprGatewayException Always thrown, unless this method is implemented in a subclass.
     */
    public function process_update_account_form_ajax(MeprSubscription $sub)
    {
        throw new MeprGatewayException('Not implemented');
    }
}
