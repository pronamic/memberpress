<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

interface MeprTransactionInterface
{
    /**
     * Get the user associated with this transaction
     *
     * @return MeprUser|false
     */
    public function user();

    /**
     * Get the product associated with this transaction
     *
     * @return MeprProduct|false
     */
    public function product();

    /**
     * Get the coupon associated with this transaction
     *
     * @return MeprCoupon|false
     */
    public function coupon();

    /**
     * Get the payment method associated with this transaction
     *
     * @return MeprBaseGateway|false
     */
    public function payment_method();

    /**
     * Check if the transaction is expired
     *
     * @param  integer $offset The offset.
     * @return boolean
     */
    public function is_expired($offset = 0);

    /**
     * Cancel old subscription if necessary
     *
     * @return void
     */
    public function maybe_cancel_old_sub();

    /**
     * Set the gross amount for this transaction
     *
     * @param  float $gross The gross amount.
     * @return void
     */
    public function set_gross($gross);

    /**
     * Set the subtotal amount for this transaction
     *
     * @param  float $subtotal The subtotal amount.
     * @return void
     */
    public function set_subtotal($subtotal);

    /**
     * Apply tax to this transaction
     *
     * @param  float   $subtotal     The subtotal.
     * @param  integer $num_decimals The number of decimals.
     * @return void
     */
    public function apply_tax($subtotal, $num_decimals = 2);
}
