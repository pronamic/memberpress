<?php

/**
 * The minimum amount that can be charged per-currency in the checkout.
 *
 * Based on https://stripe.com/docs/currencies#minimum-and-maximum-charge-amounts.
 */

return MeprHooks::apply_filters('mepr_minimum_charge_amounts', [
    'USD' => 0.50,
    'AED' => 2.00,
    'AUD' => 0.50,
    'BGN' => 1.00,
    'BRL' => 0.50,
    'CAD' => 0.50,
    'CHF' => 0.50,
    'CZK' => 15.00,
    'DKK' => 2.50,
    'EUR' => 0.50,
    'GBP' => 0.30,
    'HKD' => 4.00,
    'HRK' => 0.50,
    'HUF' => 175.00,
    'INR' => 0.50,
    'JPY' => 50,
    'MXN' => 10,
    'MYR' => 2,
    'NOK' => 3.00,
    'NZD' => 0.50,
    'PLN' => 2.00,
    'RON' => 2.00,
    'SEK' => 3.00,
    'SGD' => 0.50,
    'THB' => 10,
]);
