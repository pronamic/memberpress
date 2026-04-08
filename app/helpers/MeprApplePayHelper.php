<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Helper for generating Apple Pay recurring payment request configurations.
 */
class MeprApplePayHelper
{
    /**
     * Get the Apple Pay recurring payment request configuration.
     *
     * Generates a gateway-agnostic recurringPaymentRequest array for Apple Pay MPAN support.
     * The caller is responsible for formatting $amount (e.g. Stripe passes a zero-decimal int,
     * PayPal passes a decimal string). The helper places it in the structure as-is.
     *
     * @param  MeprProduct      $prd    The product object.
     * @param  MeprSubscription $sub    The subscription object.
     * @param  mixed            $amount The pre-formatted billing amount (gateway-dependent format).
     * @return array The Apple Pay recurring payment request configuration.
     */
    public static function get_recurring_payment_request(MeprProduct $prd, MeprSubscription $sub, $amount): array
    {
        // Translators: %d: product ID.
        $product_label  = !empty($prd->post_title) ? $prd->post_title : sprintf(__('Product %d', 'memberpress'), $prd->ID);
        $interval_count = (int) $prd->period;

        // Apple Pay doesn't support a "week" interval unit, so convert weeks to days.
        if ($prd->period_type === 'weeks') {
            $interval_unit  = 'day';
            $interval_count = $interval_count * 7;
        } else {
            $interval_unit = $prd->period_type === 'years' ? 'year' : 'month';
        }

        $recurring_payment_request = [
            'paymentDescription' => $product_label,
            'managementURL'      => MeprOptions::fetch()->account_page_url('action=subscriptions'),
            'regularBilling'     => [
                'amount'                        => $amount,
                'label'                         => $product_label,
                'paymentTiming'                 => 'recurring',
                'recurringPaymentIntervalUnit'  => $interval_unit,
                'recurringPaymentIntervalCount' => $interval_count,
            ],
        ];

        if ($sub->trial && $sub->trial_days > 0) {
            $recurring_payment_request['trialDays'] = (int) $sub->trial_days;
        }

        return MeprHooks::apply_filters('mepr_apple_pay_recurring_payment_request', $recurring_payment_request, $prd, $sub, $amount);
    }
}
