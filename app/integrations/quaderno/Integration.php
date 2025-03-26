<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprQuadernoIntegration
{
    /**
     * Constructor for the MeprQuadernoIntegration class.
     */
    public function __construct()
    {
        add_filter('mepr_stripe_payment_args', [$this, 'stripe_payment_args'], 10, 2);
        add_filter('mepr_stripe_payment_intent_args', [$this, 'stripe_payment_args'], 10, 2);
        add_filter('mepr_stripe_subscription_args', [$this, 'stripe_subscription_args'], 10, 3);
        add_filter('mepr_stripe_resume_subscription_args', [$this, 'stripe_resume_subscription_args'], 10, 2);
        add_filter('mepr_stripe_customer_args', [$this, 'stripe_customer_args'], 10, 2);
        add_filter('mepr_stripe_create_customer_args', [$this, 'stripe_create_customer_args'], 10, 2);
        add_filter('mepr_paypal_std_custom_payment_vars', [$this, 'paypal_std_custom_payment_vars'], 10, 2);
        add_filter('mepr_paypal_std_payment_vars', [$this, 'paypal_std_payment_vars'], 10, 2);
    }

    /**
     * Modify Stripe payment arguments.
     *
     * @param  array  $args The original payment arguments.
     * @param  object $txn  The transaction object.
     * @return array Modified payment arguments.
     */
    public function stripe_payment_args($args, $txn)
    {
        $usr = $txn->user();

        if (!isset($args['metadata']) || !is_array($args['metadata'])) {
            $args['metadata'] = [];
        }

        if (isset($txn->tax_rate) && $txn->tax_rate > 0) {
            $args['metadata']['tax_rate'] = $txn->tax_rate;
        }

        $args['metadata']['vat_number']    = get_user_meta($usr->ID, 'mepr_vat_number', true);
        $args['metadata']['invoice_email'] = $usr->user_email;

        return $args;
    }

    /**
     * Modify Stripe subscription arguments.
     *
     * @param  array  $args The original subscription arguments.
     * @param  object $txn  The transaction object.
     * @param  object $sub  The subscription object.
     * @return array
     */
    public function stripe_subscription_args($args, $txn, $sub)
    {
        if (!isset($args['metadata']) || !is_array($args['metadata'])) {
            $args['metadata'] = [];
        }

        if (isset($txn->tax_rate) && $txn->tax_rate > 0) {
            $args['metadata']['tax_rate'] = $txn->tax_rate;
        }

        return $args;
    }

    /**
     * Modify Stripe resume subscription arguments.
     *
     * @param  array  $args The original resume subscription arguments.
     * @param  object $sub  The subscription object.
     * @return array
     */
    public function stripe_resume_subscription_args($args, $sub)
    {
        if (!isset($args['metadata']) || !is_array($args['metadata'])) {
            $args['metadata'] = [];
        }

        if (isset($sub->tax_rate) && $sub->tax_rate > 0) {
            $args['metadata']['tax_rate'] = $sub->tax_rate;
        }

        return $args;
    }

    /**
     * Modify Stripe customer arguments.
     *
     * @param  array  $args The original customer arguments.
     * @param  object $sub  The subscription object.
     * @return array
     */
    public function stripe_customer_args($args, $sub)
    {
        return $this->stripe_create_customer_args($args, $sub->user());
    }

    /**
     * Create Stripe customer arguments.
     *
     * @param  array  $args The original customer arguments.
     * @param  object $usr  The user object.
     * @return array
     */
    public function stripe_create_customer_args($args, $usr)
    {
        if (!isset($args['metadata']) || !is_array($args['metadata'])) {
            $args['metadata'] = [];
        }

        $args['metadata']['vat_number'] = get_user_meta($usr->ID, 'mepr_vat_number', true);

        return $args;
    }

    /**
     * Modify PayPal standard custom payment variables.
     *
     * @param  array  $custom The original custom payment variables.
     * @param  object $txn    The transaction object.
     * @return array
     */
    public function paypal_std_custom_payment_vars($custom, $txn)
    {
        $usr = $txn->user();

        if (!is_array($custom)) {
            $custom = [];
        }

        $custom['vat_number'] = get_user_meta($usr->ID, 'mepr_vat_number', true);
        $custom['tax_id']     = get_user_meta($usr->ID, 'mepr_vat_number', true);

        if ($txn->tax_rate > 0) {
            $custom['tax']['rate'] = $txn->tax_rate;
        }

        return $custom;
    }

    /**
     * Modify PayPal standard payment variables.
     *
     * @param  array  $vars The original payment variables.
     * @param  object $txn  The transaction object.
     * @return array
     */
    public function paypal_std_payment_vars($vars, $txn)
    {
        $user = $txn->user();

        if (!isset($vars['first_name']) && !empty($user->first_name)) {
            $vars['first_name'] = $user->first_name;
        }

        if (!isset($vars['last_name']) && !empty($user->last_name)) {
            $vars['last_name'] = $user->last_name;
        }

        $address1 = get_user_meta($user->ID, 'mepr-address-one', true);
        if (!isset($vars['address1']) && !empty($address1)) {
            $vars['address1'] = $address1;
        }

        $city = get_user_meta($user->ID, 'mepr-address-city', true);
        if (!isset($vars['city']) && !empty($city)) {
            $vars['city'] = $city;
        }

        $zip = get_user_meta($user->ID, 'mepr-address-zip', true);
        if (!isset($vars['zip']) && !empty($zip)) {
            $vars['zip'] = $zip;
        }

        $country = get_user_meta($user->ID, 'mepr-address-country', true);
        if (!isset($vars['country']) && !empty($country)) {
            $vars['country'] = $country;
        }

        return $vars;
    }
}

new MeprQuadernoIntegration();
