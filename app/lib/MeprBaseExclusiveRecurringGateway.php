<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

abstract class MeprBaseExclusiveRecurringGateway extends MeprBaseRealGateway
{
    /**
     * Display the plans terms.
     *
     * @param  MeprProduct $product The product.
     * @return void
     */
    abstract public function display_plans_terms($product);

    /**
     * Check if the plan is a one time payment.
     *
     * @param  string $plan_code The plan code.
     * @return boolean
     */
    abstract public function is_one_time_payment($plan_code);

    /**
     * Get the subscription attributes.
     *
     * @param  string $plan_code The plan code.
     * @return array
     */
    abstract public function subscription_attributes($plan_code);

    /**
     * Process the payment form.
     *
     * @param  MeprTransaction $txn The transaction.
     * @return void
     */
    public function process_payment_form($txn)
    {
        // We're ready to create the subscription
        // One time payments are handled as subscriptions.
        $this->process_create_subscription($txn);
    }

    /**
     * Process the payment.
     *
     * @param  MeprTransaction $transaction The transaction.
     * @return void
     */
    public function process_payment($transaction)
    {
    }
}
