<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

#[AllowDynamicProperties]
class MeprStripeGateway extends MeprBaseRealAjaxGateway
{
    const STRIPE_API_VERSION = '2022-11-15';

    /**
     * Used in the view to identify the gateway
     */
    public function __construct()
    {
        $this->name = 'Stripe';
        $this->icon = MEPR_IMAGES_URL . '/checkout/cards.png';
        $this->desc = __('Pay with your credit card via Stripe', 'memberpress');
        $this->key  = 'stripe';
        $this->set_defaults();
        $this->has_spc_form = true;

        $this->capabilities = [
            'process-credit-cards',
            'process-payments',
            'process-refunds',
            'create-subscriptions',
            'cancel-subscriptions',
            'update-subscriptions',
            'suspend-subscriptions',
            'resume-subscriptions',
            'send-cc-expirations',
            'subscription-trial-payment',
            'order-bumps',
            'multiple-subscriptions',
        ];

        // Setup the notification actions for this gateway.
        $this->notifiers = [
            'whk'                 => 'listener',
            'stripe-service-whk'  => 'service_listener',
            'update-billing.html' => 'churn_buster',
            'return'              => 'return_handler',
        ];
    }

    /**
     * Load gateway settings
     *
     * @param  array $settings Gateway settings array.
     * @return void
     */
    public function load($settings)
    {
        $this->settings = (object)$settings;
        $this->set_defaults();
    }

    /**
     * Set default gateway settings
     *
     * Initializes default settings for the Stripe gateway including API keys,
     * payment methods, and connection status
     *
     * @return void
     */
    protected function set_defaults()
    {
        if (!isset($this->settings)) {
            $this->settings = [];
        }

        $this->settings = (object)array_merge(
            [
                'gateway'                 => 'MeprStripeGateway',
                'id'                      => $this->generate_id(),
                'label'                   => '',
                'use_label'               => true,
                'use_icon'                => true,
                'use_desc'                => true,
                'email'                   => '',
                'sandbox'                 => false,
                'force_ssl'               => false,
                'debug'                   => false,
                'test_mode'               => false,
                'stripe_checkout_enabled' => false,
                'churn_buster_enabled'    => false,
                'churn_buster_uuid'       => '',
                'api_keys'                => [
                    'test' => [
                        'public' => '',
                        'secret' => '',
                    ],
                    'live' => [
                        'public' => '',
                        'secret' => '',
                    ],
                ],
                'connect_status'          => false,
                'service_account_id'      => '',
                'service_account_name'    => '',
                'payment_methods'         => $this->get_default_payment_methods(),
            ],
            (array)$this->settings
        );

        $this->id                   = $this->settings->id;
        $this->label                = $this->settings->label;
        $this->use_label            = $this->settings->use_label;
        $this->use_icon             = $this->settings->use_icon;
        $this->use_desc             = $this->settings->use_desc;
        $this->connect_status       = $this->settings->connect_status;
        $this->service_account_id   = $this->settings->service_account_id;
        $this->service_account_name = $this->settings->service_account_name;
        // $this->recurrence_type = $this->settings->recurrence_type;
        if ($this->is_test_mode()) {
            $this->settings->public_key = trim($this->settings->api_keys['test']['public']);
            $this->settings->secret_key = trim($this->settings->api_keys['test']['secret']);
        } else {
            $this->settings->public_key = trim($this->settings->api_keys['live']['public']);
            $this->settings->secret_key = trim($this->settings->api_keys['live']['secret']);
        }
    }

    /**
     * Determines whether the update link for a subscription should be hidden.
     *
     * @param  MeprSubscription $subscription The subscription object.
     * @return boolean Returns true if the subscription status is suspended, otherwise false.
     */
    protected function hide_update_link($subscription)
    {
        if ($subscription->status === MeprSubscription::$suspended_str) {
            return true;
        }

        return false;
    }

    /**
     * Retrieves the Stripe tax rate ID for a given product.
     *
     * @param string      $name      The name of the tax.
     * @param integer     $rate      The tax rate.
     * @param MeprProduct $product   The product object.
     * @param boolean     $inclusive Whether the tax is inclusive.
     *
     * @return object The Stripe tax rate ID.
     * @throws MeprHttpException When HTTP request to Stripe API fails.
     * @throws MeprRemoteException When Stripe API returns an error response.
     */
    public function get_stripe_tax_rate_id($name, $rate, $product, $inclusive = false)
    {
        $tax_request = [
            'inclusive'  => 'false',
            'percentage' => MeprUtils::format_float((float) $rate, 3),
        ];

        if (empty($name)) {
            $tax_request['display_name'] = 'Tax';
        } else {
            $tax_request['display_name'] = $name;
        }

        if ($inclusive) {
            $tax_request['inclusive'] = 'true';
        }

        $meta_key = '_mepr_stripe_tax_id_' . $this->get_meta_gateway_id() . '_' . md5(serialize($tax_request));

        $tax_id = get_post_meta($product->ID, $meta_key, true);

        if (!empty($tax_id)) {
            return $tax_id;
        }

        $stripe_tax = (object) $this->send_stripe_request('tax_rates', $tax_request);

        update_post_meta($product->ID, $meta_key, $stripe_tax->id);

        return $stripe_tax->id;
    }

    /**
     * Create a Checkout session and redirect the user to Stripe.
     *
     * @param  MeprTransaction       $txn     The transaction.
     * @param  MeprProduct           $product The product.
     * @param  MeprUser              $usr     The user.
     * @param  MeprSubscription|null $sub     The subscription (null for one-time payments).
     * @throws MeprHttpException              If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException            If there was an invalid or error response from Stripe.
     */
    public function create_checkout_session(
        $txn,
        $product,
        $usr,
        $sub = null
    ) {
        $mepr_options    = MeprOptions::fetch();
        $current_user    = MeprUtils::get_currentuserinfo();
        $calculate_taxes = get_option('mepr_calculate_taxes') == true ? true : false;

        if ($mepr_options->attr('tax_calc_type') == 'inclusive' && $calculate_taxes && $txn->tax_rate > 0) {
            $tax_inclusive = true;
        } else {
            $tax_inclusive = false;
        }

        $thankyou_page_args = [
            'membership'     => sanitize_title($product->post_title),
            'transaction_id' => $txn->id,
            'membership_id'  => $product->ID,
        ];

        if ($sub instanceof MeprSubscription) {
            $thankyou_page_args = array_merge($thankyou_page_args, ['subscription_id' => $sub->id]);
        }

        $success_url = add_query_arg(
            [
                'txn_id'      => $txn->id,
                'redirect_to' => urlencode($mepr_options->thankyou_page_url($thankyou_page_args)),
            ],
            $this->notify_url('return')
        );

        $customer_id       = $this->get_customer_id($usr);
        $base_price        = MeprHooks::apply_filters('mepr_stripe_product_base_price', $product->adjusted_price(), $product, $usr, $sub);
        $base_price        = MeprUtils::maybe_round_to_minimum_amount($base_price);
        $price_id          = $this->get_stripe_price_id($sub, $product, $base_price);
        $stripe_product_id = $this->get_product_id($product);
        $coupon            = $txn->coupon();

        if ($coupon instanceof MeprCoupon) {
            $discount_amount = $this->get_coupon_discount_amount($coupon, $product);

            if ($discount_amount > 0 && $coupon->discount_mode != 'first-payment') {
                if ($tax_inclusive) {
                    if ($coupon->discount_type != 'percent') {
                        $stripe_coupon_id = $this->get_coupon_id($coupon, $this->to_zero_decimal_amount($coupon->get_discount_amount($product)));
                    } else {
                        $stripe_coupon_id = $this->get_coupon_id($coupon, $coupon->get_discount_amount($product));
                    }
                } else {
                    $stripe_coupon_id = $this->get_coupon_id($coupon, $discount_amount);
                }
            }

            if ($product->is_one_time_payment() && $coupon->discount_mode == 'first-payment' && $coupon->get_first_payment_discount_amount($product) > 0) {
                if ($coupon->first_payment_discount_type  != 'percent') {
                    $stripe_coupon_id = $this->get_coupon_id($coupon, $this->to_zero_decimal_amount($coupon->get_first_payment_discount_amount($product)), true);
                } else {
                    $stripe_coupon_id = $this->get_coupon_id($coupon, $coupon->get_first_payment_discount_amount($product), true);
                }
            }

            if (
                !$product->is_one_time_payment() &&
                (
                ($coupon->discount_mode == 'first-payment' && $coupon->get_first_payment_discount_amount($product) > 0) ||
                ($coupon->discount_mode == 'trial-override' && $coupon->get_discount_amount($product) > 0)
                )
            ) {
                $tmp_sub          = new MeprSubscription();
                $tmp_sub->id      = 0;
                $tmp_sub->user_id = (isset($current_user->ID)) ? $current_user->ID : 0;
                $tmp_sub->load_product_vars($product, null, true);
                $tmp_sub->maybe_prorate();
                $sub->trial_amount = $tmp_sub->price;

                if ($coupon->get_discount_amount($product) > 0) {
                    $tmp_coupon                  = new MeprCoupon();
                    $tmp_coupon->discount_amount = $coupon->get_discount_amount($product);
                    $tmp_coupon->discount_type   = $coupon->discount_type;
                    $price                       = $tmp_coupon->apply_discount($product->price, false, $product);
                    $price                       = MeprHooks::apply_filters('mepr_stripe_product_base_price', $price, $product, $usr, $sub);
                    $price                       = MeprUtils::maybe_round_to_minimum_amount($price);
                    $price_id                    = $this->get_stripe_price_id($sub, $product, $price);
                }
            } else {
                $discounted_amount = $coupon->apply_discount($base_price, $product->is_one_time_payment(), $product);
                $minimum_amount    = MeprUtils::get_minimum_amount();

                // If the coupon brings the amount below the minimum charge amount, set the amount to the minimum charge amount
                // and don't use a Stripe coupon.
                if ($minimum_amount && $discounted_amount < $minimum_amount) {
                    $price_id         = $this->get_stripe_price_id($sub, $product, $minimum_amount);
                    $stripe_coupon_id = null;
                }
            }
        }

        if ($calculate_taxes && $txn->tax_rate > 0 && $txn->tax_amount > 0) {
            $tax_rate_id = $this->get_stripe_tax_rate_id($txn->tax_desc, $txn->tax_rate, $product, $tax_inclusive);
        }

        $metadata = array_filter([
            'platform'                  => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
            'site_url'                  => get_site_url(),
            'ip_address'                => MeprAntiCardTestingCtrl::get_ip(),
            'transaction_id'            => $txn->id,
            'memberpress_transaction_id' => $txn->id,
            'memberpress_product'        => $product->post_title,
            'memberpress_product_id'     => $product->ID,
        ]);

        if (!$product->is_one_time_payment()) {
            $metadata = array_filter(array_merge($metadata, [
                'memberpress_subscription_id' => $sub->id,
            ]));

            $checkout_session = [
                'customer'             => $customer_id,
                'payment_method_types' => $this->get_subscription_payment_method_types(),
                'line_items'           => [
                    [
                        'price'    => $price_id,
                        'quantity' => 1,
                    ],
                ],
                'mode'                 => 'subscription',
                'success_url'          => $success_url,
                'cancel_url'           => MeprUtils::get_current_url(),
                'metadata'             => $metadata,
                'subscription_data'    => [
                    'metadata' => $metadata,
                ],
            ];

            if (!empty($product->post_title)) {
                $checkout_session['subscription_data']['description'] = $product->post_title;
            }
        } else {
            $payment_method_types   = $this->get_payment_intent_payment_method_types(null, (float) $txn->total);
            $payment_method_options = [];

            if (in_array('wechat_pay', $payment_method_types, true)) {
                $payment_method_options = array_merge($payment_method_options, [
                    'wechat_pay' => [
                        'client' => 'web',
                    ],
                ]);
            }

            $checkout_session = [
                'payment_method_options' => $payment_method_options,
                'customer'               => $customer_id,
                'line_items'             => [
                    [
                        'price'    => $price_id,
                        'quantity' => 1,
                    ],
                ],
                'mode'                   => 'payment',
                'metadata'               => $metadata,
                'success_url'            => $success_url,
                'cancel_url'             => MeprUtils::get_current_url(),
                'payment_intent_data'    => [
                    'metadata' => $metadata,
                ],
            ];

            if (!empty($product->post_title)) {
                $checkout_session['payment_intent_data']['description'] = $product->post_title;
            }

            if (MeprHooks::apply_filters('mepr_stripe_payment_intent_store_payment_method', false, $txn, $product)) {
                $checkout_session['payment_intent_data']['setup_future_usage'] = 'off_session';
            }

            $index = array_search('afterpay_clearpay', $payment_method_types);
            if ($index !== false) {
                $full_name = $usr->get_full_name();

                $address = [
                    'line1'       => get_user_meta($usr->ID, 'mepr-address-one', true),
                    'line2'       => get_user_meta($usr->ID, 'mepr-address-two', true),
                    'city'        => get_user_meta($usr->ID, 'mepr-address-city', true),
                    'state'       => get_user_meta($usr->ID, 'mepr-address-state', true),
                    'country'     => get_user_meta($usr->ID, 'mepr-address-country', true),
                    'postal_code' => get_user_meta($usr->ID, 'mepr-address-zip', true),
                ];

                foreach ($address as $key => $value) {
                    if (empty($value) || !is_string($value)) {
                        unset($address[$key]);
                    }
                }

                if (!empty($full_name) && !empty($address) && !empty($address['line1'])) {
                    $checkout_session['payment_intent_data']['shipping'] = [
                        'name'    => $full_name,
                        'address' => $address,
                    ];
                } else {
                    // Name and Address fields are required to use Afterpay/Clearpay.
                    array_splice($payment_method_types, $index, 1);
                }
            }

            $checkout_session['payment_method_types'] = $payment_method_types;
        }

        if ($sub instanceof MeprSubscription && $sub->trial > 0) {
            $checkout_session['subscription_data']['trial_period_days'] = $sub->trial_days;

            // The section below sets the recurring price amount to the coupon discounted amount.
            // Since a Stripe coupon will also discount the trial amount, we can't use a Stripe coupon if there is a paid trial.
            if ($coupon instanceof MeprCoupon && $coupon->get_discount_amount($product) > 0 && $sub->trial_total > 0) {
                $tmp_coupon                                 = new MeprCoupon();
                $tmp_coupon->discount_amount                = $coupon->get_discount_amount($product);
                $tmp_coupon->discount_type                  = $coupon->discount_type;
                $price                                      = $tmp_coupon->apply_discount($product->price, false, $product);
                $price                                      = MeprHooks::apply_filters('mepr_stripe_product_base_price', $price, $product, $usr, $sub);
                $price                                      = MeprUtils::maybe_round_to_minimum_amount($price);
                $checkout_session['line_items'][0]['price'] = $this->get_stripe_price_id($sub, $product, $price);
            }

            if ($tax_inclusive) {
                $trial_amount = $sub->trial_total;
            } else {
                $trial_amount = $sub->trial_total - $sub->trial_tax_amount;
            }

            $trial_amount = (self::is_zero_decimal_currency()) ? MeprUtils::format_float(($trial_amount), 0) : MeprUtils::format_float(($trial_amount * 100), 0);
            $trial_plan   = [
                'quantity'   => 1,
                'price_data' => [
                    'currency'    => $mepr_options->currency_code,
                    'product'     => $this->get_product_id($product, true),
                    'unit_amount' => $trial_amount,
                ],
            ];

            if (isset($tax_rate_id)) {
                $trial_plan['tax_rates'] = [$tax_rate_id];
            }

            $checkout_session['line_items'][] = $trial_plan;
        }

        if (isset($tax_rate_id)) {
            $checkout_session['line_items'][0]['tax_rates'] = [$tax_rate_id];
        }

        if (isset($stripe_coupon_id)) {
            if (!$sub instanceof MeprSubscription || !$sub->trial || $sub->trial_total == 0) {
                $checkout_session['discounts'] = [
                    ['coupon' => $stripe_coupon_id],
                ];
            }

            if (
                !$product->is_one_time_payment() &&
                (
                ($coupon->discount_mode == 'first-payment' && $coupon->get_first_payment_discount_amount($product) > 0) ||
                ($coupon->discount_mode == 'trial-override' && $coupon->get_discount_amount($product) > 0)
                )
            ) {
                unset($checkout_session['discounts']);
            }
        }

        // Active product to use in Stripe checkout.
        $this->send_stripe_request('products/' . $stripe_product_id, ['active' => 'true']);

        $checkout_session = MeprHooks::apply_filters('mepr_stripe_checkout_session_args', $checkout_session, $txn, $sub);

        if (!$product->is_one_time_payment()) {
            $application_fee_percentage = $this->get_application_fee_percentage();
            if (!empty($application_fee_percentage)) {
                if (!isset($checkout_session['subscription_data'])) {
                    $checkout_session['subscription_data'] = [];
                }
                $checkout_session['subscription_data']['application_fee_percent'] = $application_fee_percentage;
            }
        } else {
            $application_fee_percentage = $this->get_application_fee_percentage();
            $application_fee            = floor($txn->amount * $application_fee_percentage / 100);
            if (!empty($application_fee)) {
                $checkout_session['payment_intent_data']['application_fee_amount'] = $application_fee;
            }
        }

        $checkout_session = (object) $this->send_stripe_request('checkout/sessions', $checkout_session, 'post');

        $txn->trans_num = $checkout_session->id;
        $txn->store();

        MeprUtils::wp_redirect($checkout_session->url, 303);
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * Create a Checkout session for purchases with order bumps, and redirect the user to Stripe.
     *
     * @param  MeprTransaction   $txn         The transaction.
     * @param  MeprProduct       $prd         The product.
     * @param  MeprUser          $usr         The user.
     * @param  string            $coupon_code The coupon code.
     * @param  MeprTransaction[] $order_bumps The array of order bump transactions in the checkout.
     * @throws MeprGatewayException           If there was an error processing the transaction.
     * @throws MeprHttpException              If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException            If there was an invalid or error response from Stripe.
     */
    public function create_multi_item_checkout_session(MeprTransaction $txn, MeprProduct $prd, MeprUser $usr, $coupon_code = '', array $order_bumps = [])
    {
        $mepr_options    = MeprOptions::fetch();
        $calculate_taxes = (bool) get_option('mepr_calculate_taxes');
        $tax_inclusive   = $mepr_options->attr('tax_calc_type') == 'inclusive';
        $line_items      = [];

        $thankyou_page_args = [
            'membership'     => sanitize_title($prd->post_title),
            'transaction_id' => $txn->id,
            'membership_id'  => $prd->ID,
        ];

        if ($prd->is_one_time_payment() || !$prd->is_payment_required($coupon_code)) {
            if ($prd->is_payment_required($coupon_code)) {
                $amount = $calculate_taxes && !$tax_inclusive && $txn->tax_rate > 0 ? (float) $txn->amount : (float) $txn->total;
            } else {
                $amount = 0.00;
            }

            $line_items[] = $this->build_line_item($this->get_one_time_price_id($prd, $amount), $txn, $prd);
        } else {
            $sub = $txn->subscription();

            if (!($sub instanceof MeprSubscription)) {
                throw new MeprGatewayException(__('Subscription not found', 'memberpress'));
            }

            $amount             = $calculate_taxes && !$tax_inclusive && $txn->tax_rate > 0 ? (float) $sub->price : (float) $sub->total;
            $line_items[]       = $this->build_line_item($this->get_stripe_price_id($sub, $prd, $amount), $txn, $prd);
            $thankyou_page_args = array_merge($thankyou_page_args, ['subscription_id' => $sub->id]);
        }

        $total            = $amount;
        $has_subscription = false;

        foreach ($order_bumps as $transaction) {
            $product = $transaction->product();

            if (empty($product->ID)) {
                throw new MeprGatewayException(__('Product not found', 'memberpress'));
            }

            if (!$transaction->is_payment_required()) {
                $amount = 0.00;
            } elseif ($transaction->is_one_time_payment()) {
                $amount = $calculate_taxes && !$tax_inclusive && $transaction->tax_rate > 0 ? (float) $transaction->amount : (float) $transaction->total;
            } else {
                $subscription = $transaction->subscription();

                if (!($subscription instanceof MeprSubscription)) {
                    throw new MeprGatewayException(__('Subscription not found', 'memberpress'));
                }

                if ($subscription->trial && $subscription->trial_days > 0) {
                    $amount = $calculate_taxes && !$tax_inclusive && $transaction->tax_rate > 0 ? (float) $subscription->trial_amount : (float) $subscription->trial_total;
                } else {
                    $amount = $calculate_taxes && !$tax_inclusive && $transaction->tax_rate > 0 ? (float) $subscription->price : (float) $subscription->total;
                }

                $has_subscription = true;
            }

            $line_items[] = $this->build_line_item($this->get_one_time_price_id($product, $amount), $transaction, $product);
            $total       += $amount;
        }

        $metadata = array_filter([
            'platform'                  => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
            'site_url'                  => get_site_url(),
            'ip_address'                => MeprAntiCardTestingCtrl::get_ip(),
            'transaction_id'            => $txn->id,
            'memberpress_transaction_id' => $txn->id,
            'memberpress_product'        => $prd->post_title,
            'memberpress_product_id'     => $prd->ID,
        ]);

        if ($prd->is_one_time_payment() || !$prd->is_payment_required($coupon_code)) {
            if ($total > 0.00) {
                $store_payment_method   = MeprHooks::apply_filters('mepr_stripe_payment_intent_store_payment_method', $has_subscription, $txn, $prd);
                $setup_future_usage     = $store_payment_method ? 'off_session' : null;
                $payment_method_types   = $this->get_payment_intent_payment_method_types($setup_future_usage, $total);
                $payment_method_options = [];

                if (in_array('wechat_pay', $payment_method_types, true)) {
                    $payment_method_options = array_merge($payment_method_options, [
                        'wechat_pay' => [
                            'client' => 'web',
                        ],
                    ]);
                }

                $args = [
                    'mode'                   => 'payment',
                    'customer'               => $this->get_customer_id($usr),
                    'payment_method_options' => $payment_method_options,
                    'line_items'             => $line_items,
                    'metadata'               => $metadata,
                    'payment_intent_data'    => [
                        'metadata' => $metadata,
                    ],
                ];

                if ($store_payment_method) {
                    $args['payment_intent_data']['setup_future_usage'] = 'off_session';
                }

                if (!empty($prd->post_title)) {
                    $args['payment_intent_data']['description'] = $prd->post_title;
                }

                $index = array_search('afterpay_clearpay', $payment_method_types);
                if ($index !== false) {
                    $full_name = $usr->get_full_name();

                    $address = [
                        'line1'       => get_user_meta($usr->ID, 'mepr-address-one', true),
                        'line2'       => get_user_meta($usr->ID, 'mepr-address-two', true),
                        'city'        => get_user_meta($usr->ID, 'mepr-address-city', true),
                        'state'       => get_user_meta($usr->ID, 'mepr-address-state', true),
                        'country'     => get_user_meta($usr->ID, 'mepr-address-country', true),
                        'postal_code' => get_user_meta($usr->ID, 'mepr-address-zip', true),
                    ];

                    foreach ($address as $key => $value) {
                        if (empty($value) || !is_string($value)) {
                            unset($address[$key]);
                        }
                    }

                    if (!empty($full_name) && !empty($address) && !empty($address['line1'])) {
                        $args['payment_intent_data']['shipping'] = [
                            'name'    => $full_name,
                            'address' => $address,
                        ];
                    } else {
                        // Name and Address fields are required to use Afterpay/Clearpay.
                        array_splice($payment_method_types, $index, 1);
                    }
                }

                $args['payment_method_types'] = $payment_method_types;
            } else {
                $args = [
                    'mode'                 => 'setup',
                    'customer'             => $this->get_customer_id($usr),
                    'payment_method_types' => $this->get_setup_intent_payment_method_types(),
                    'metadata'             => $metadata,
                    'setup_intent_data'    => [
                        'metadata' => $metadata,
                    ],
                ];

                if (!empty($prd->post_title)) {
                    $args['setup_intent_data']['description'] = $prd->post_title;
                }
            }
        } else {
            $sub = $txn->subscription();

            if (!($sub instanceof MeprSubscription)) {
                throw new MeprGatewayException(__('Subscription not found', 'memberpress'));
            }

            $metadata = array_filter(array_merge($metadata, [
                'memberpress_subscription_id' => $sub->id,
            ]));

            $args = [
                'mode'                 => 'subscription',
                'customer'             => $this->get_customer_id($usr),
                'payment_method_types' => $this->get_subscription_payment_method_types(),
                'metadata'             => $metadata,
                'subscription_data'    => [
                    'metadata' => $metadata,
                ],
            ];

            if (!empty($prd->post_title)) {
                $args['subscription_data']['description'] = $prd->post_title;
            }

            if ($sub->trial && $sub->trial_days > 0) {
                $amount    = $calculate_taxes && !$tax_inclusive && $txn->tax_rate > 0 ? (float) $sub->trial_amount : (float) $sub->trial_total;
                $line_item = $this->build_line_item($this->get_one_time_price_id($prd, $amount), $txn, $prd);
                array_unshift($line_items, $line_item);

                $args['subscription_data']['trial_period_days'] = $sub->trial_days;
            }

            $args = array_merge($args, [
                'line_items' => $line_items,
            ]);
        }

        $success_url = add_query_arg(
            [
                'txn_id'      => $txn->id,
                'redirect_to' => urlencode($mepr_options->thankyou_page_url($thankyou_page_args)),
            ],
            $this->notify_url('return')
        );

        $args = array_merge($args, [
            'success_url' => $success_url,
            'cancel_url'  => MeprUtils::get_current_url(),
        ]);

        $args = MeprHooks::apply_filters('mepr_stripe_checkout_session_args', $args, $txn);

        $checkout_session = (object) $this->send_stripe_request('checkout/sessions', $args, 'post');

        $txn->trans_num = $checkout_session->id;
        $txn->store();

        MeprUtils::wp_redirect($checkout_session->url, 303);
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    /**
     * Convert the given amount to a zero-decimal Stripe amount
     *
     * @param  float $amount The amount to convert.
     * @return string The zero-decimal amount
     */
    public function to_zero_decimal_amount($amount)
    {
        return self::is_zero_decimal_currency() ? MeprUtils::format_float($amount, 0) : MeprUtils::format_float(($amount * 100), 0);
    }

    /**
     * Get the payment method types for SetupIntents (free trials)
     *
     * @return array
     */
    public function get_setup_intent_payment_method_types()
    {
        $types           = ['card'];
        $payment_methods = $this->settings->payment_methods;

        if (is_array($payment_methods) && count($payment_methods)) {
            foreach ($this->get_available_payment_methods() as $payment_method) {
                if (in_array($payment_method['key'], $payment_methods, true) && in_array('setup_intents', $payment_method['capabilities'], true)) {
                    $types[] = $payment_method['key'];
                }
            }
        }

        $types = MeprHooks::apply_filters('mepr_stripe_setup_intent_payment_method_types', $types);

        return $this->filter_incompatible_payment_method_types(array_unique($types));
    }

    /**
     * Get the payment method types for PaymentIntents (one-time payments)
     *
     * @param  string|null $setup_future_usage The setup future usage value.
     * @param  float|null  $amount             The amount to filter payment method types for.
     * @return array The filtered payment method types
     */
    public function get_payment_intent_payment_method_types($setup_future_usage = null, $amount = null)
    {
        $types           = ['card'];
        $payment_methods = $this->settings->payment_methods;

        if (is_array($payment_methods) && count($payment_methods)) {
            foreach ($this->get_available_payment_methods() as $payment_method) {
                if (in_array($payment_method['key'], $payment_methods, true) && in_array('payment_intents', $payment_method['capabilities'], true)) {
                    if (is_null($setup_future_usage)) {
                        $types[] = $payment_method['key'];
                    } elseif (in_array('setup_future_usage', $payment_method['capabilities'], true)) {
                        $types[] = $payment_method['key'];
                    }
                }
            }
        }

        $types = MeprHooks::apply_filters('mepr_stripe_payment_intent_payment_method_types', $types);

        return $this->filter_incompatible_payment_method_types(array_unique($types), $amount);
    }

    /**
     * Get the payment method types for Subscriptions (paid trials, recurring subscriptions)
     *
     * @return array
     */
    public function get_subscription_payment_method_types()
    {
        $types           = ['card'];
        $payment_methods = $this->settings->payment_methods;

        if (is_array($payment_methods) && count($payment_methods)) {
            foreach ($this->get_available_payment_methods() as $payment_method) {
                if (in_array($payment_method['key'], $payment_methods, true) && in_array('subscriptions', $payment_method['capabilities'], true)) {
                    $types[] = $payment_method['key'];
                }
            }
        }

        $types = MeprHooks::apply_filters('mepr_stripe_subscription_payment_method_types', $types);

        return $this->filter_incompatible_payment_method_types(array_unique($types));
    }

    /**
     * Get the payment method types for the SetupIntent when updating a subscription's payment method
     *
     * @return array
     */
    public function get_update_setup_intent_payment_method_types()
    {
        $types           = ['card'];
        $payment_methods = $this->settings->payment_methods;

        if (is_array($payment_methods) && count($payment_methods)) {
            foreach ($this->get_available_payment_methods() as $payment_method) {
                if (in_array($payment_method['key'], $payment_methods, true) && in_array('setup_intents', $payment_method['capabilities'], true)) {
                    $types[] = $payment_method['key'];
                }
            }
        }

        $types = MeprHooks::apply_filters('mepr_stripe_update_setup_intent_payment_method_types', $types);

        return $this->filter_incompatible_payment_method_types(array_unique($types));
    }

    /**
     * Filter out payment methods that are incompatible with the current setup or payment amount
     *
     * @param  array              $types  The payment method types to filter.
     * @param  float|integer|null $amount The payment amount to filter payment method types for.
     * @return array
     */
    private function filter_incompatible_payment_method_types(array $types, $amount = null)
    {
        $mepr_options = MeprOptions::fetch();

        $key = array_search('affirm', $types);
        if ($key !== false) {
            if (!is_null($amount) && $amount < 50) {
                // Affirm does not support a payment amount less than 50 USD.
                array_splice($types, $key, 1);
            } elseif ($mepr_options->show_address_fields) {
                $country = $this->get_address_country();

                if (!empty($country) && $country != 'US') {
                    // The address country must be either not set, or set to 'US' when using Affirm.
                    array_splice($types, $key, 1);
                }
            }
        }

        $key = array_search('afterpay_clearpay', $types);
        if ($key !== false) {
            if (!$mepr_options->show_fname_lname || !$mepr_options->require_fname_lname || !$mepr_options->show_address_fields || !$mepr_options->require_address_fields) {
                // Name and Address fields are required to use Afterpay/Clearpay.
                array_splice($types, $key, 1);
            } elseif (!is_null($amount)) {
                // Check if outside transaction limits: https://stripe.com/docs/payments/afterpay-clearpay#collection-schedule.
                if (
                    $amount < 1 ||
                    (in_array($mepr_options->currency_code, ['AUD', 'CAD', 'NZD'], true) && $amount > 2000) ||
                    (in_array($mepr_options->currency_code, ['GBP', 'EUR'], true) && $amount > 1000) ||
                    ($mepr_options->currency_code == 'USD' && $amount > 4000)
                ) {
                    array_splice($types, $key, 1);
                }
            }
        }

        $key = array_search('cashapp', $types);
        if ($key !== false) {
            if ($mepr_options->show_address_fields) {
                $country = $this->get_address_country();

                if (!empty($country) && $country != 'US') {
                    // The address country must be either not set, or set to 'US' when using CashApp.
                    array_splice($types, $key, 1);
                }
            }
        }

        $key = array_search('konbini', $types);
        if ($key !== false) {
            if (!is_null($amount) && ($amount < 120 || $amount > 300000)) {
                // Konbini does not support a payment amount less than 120 or greater than 300000 JPY.
                array_splice($types, $key, 1);
            }
        }

        $key = array_search('boleto', $types);
        if ($key !== false) {
            if (!is_null($amount) && ($amount < 5 || $amount > 49999.99)) {
                // Boleto does not support a payment amount less than 5 or greater than 49999.99 BRL.
                array_splice($types, $key, 1);
            }
        }

        $key = array_search('oxxo', $types);
        if ($key !== false) {
            if (!is_null($amount) && ($amount < 10 || $amount > 10000)) {
                // OXXO does not support a payment amount less than 10 or greater than 10000 MXN.
                array_splice($types, $key, 1);
            }
        }

        $key = array_search('twint', $types);
        if ($key !== false) {
            if (!is_null($amount) && $amount > 5000) {
                // TWINT does not support a payment amount greater than 5000 CHF.
                array_splice($types, $key, 1);
            }
        }

        return $types;
    }

    /**
     * Get the customer's address country from the post data or saved user data
     *
     * @return string
     */
    private function get_address_country()
    {
        $country = isset($_POST['mepr-address-country']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-country'])) : '';

        if (empty($country) && is_user_logged_in()) {
            $country = get_user_meta(get_current_user_id(), 'mepr-address-country', true);
        }

        return $country;
    }

    /**
     * Create a PaymentIntent via the Stripe API
     *
     * @param  float           $amount             The payment amount.
     * @param  string          $customer_id        The Stripe Customer ID.
     * @param  MeprTransaction $txn                The MemberPress transaction.
     * @param  MeprProduct     $prd                The MemberPress product.
     * @param  string|null     $setup_future_usage Set the setup_future_usage parameter.
     * @return stdClass                                The Stripe PaymentIntent data
     * @throws MeprHttpException                       If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException                     If there was an invalid or error response from Stripe.
     */
    public function create_payment_intent($amount, $customer_id, MeprTransaction $txn, MeprProduct $prd, $setup_future_usage = null)
    {
        $mepr_options = MeprOptions::fetch();

        $args = [
            'amount'               => $this->to_zero_decimal_amount($amount),
            'currency'             => $mepr_options->currency_code,
            'customer'             => $customer_id,
            'payment_method_types' => $this->get_payment_intent_payment_method_types($setup_future_usage, $amount),
            'description'          => $prd->post_title,
            'metadata'             => [
                'platform'              => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
                'transaction_id'        => $txn->id,
                'site_url'              => get_site_url(),
                'ip_address'            => MeprAntiCardTestingCtrl::get_ip(),
                'memberpress_product'    => $prd->post_title,
                'memberpress_product_id' => $prd->ID,
            ],
        ];

        if (!is_null($setup_future_usage)) {
            $args['setup_future_usage'] = $setup_future_usage;
        }

        $args = MeprHooks::apply_filters('mepr_stripe_payment_intent_args', $args, $txn);

        $args = $this->add_application_fee_amount($args);

        $payment_intent = (object) $this->send_stripe_request('payment_intents', $args, 'post');

        if (is_array($payment_intent)) {
            $this->maybe_record_txn_application_fee_meta($args, $txn);
        }

        return $payment_intent;
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * Create a Checkout session and redirect to Stripe.
     *
     * @param  MeprTransaction $txn                    The transaction for the main product.
     * @param  array           $order_bump_product_ids The array of order bump product IDs.
     * @throws MeprGatewayException                    If there was an error processing the transaction.
     * @throws MeprHttpException                       If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException                     If there was an invalid or error response from Stripe.
     * @throws Exception                               If there was an error processing order bumps.
     */
    protected function checkout_redirect(MeprTransaction $txn, array $order_bump_product_ids)
    {
        $order_bump_products = MeprCheckoutCtrl::get_order_bump_products($txn->product_id, $order_bump_product_ids);
        $order_bumps         = $this->process_order($txn, $order_bump_products);
        $prd                 = $txn->product();
        $usr                 = $txn->user();
        $cpn                 = $txn->coupon();
        $coupon_code         = $cpn instanceof MeprCoupon ? $cpn->post_title : '';
        $sub                 = $txn->subscription();
        $sub                 = $sub instanceof MeprSubscription ? $sub : null;

        if (!$prd->ID) {
            throw new MeprGatewayException(__('Product not found', 'memberpress'));
        }

        if (!$usr->ID) {
            throw new MeprGatewayException(__('User not found', 'memberpress'));
        }

        MeprHooks::do_action('mepr_stripe_checkout_pending', $txn, $usr);

        if (count($order_bumps)) {
            $this->create_multi_item_checkout_session($txn, $prd, $usr, $coupon_code, $order_bumps);
        } else {
            $this->create_checkout_session($txn, $prd, $usr, $sub);
        }
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber

    /**
     * Process payment form submission
     *
     * @param  MeprTransaction $txn The transaction to process.
     * @throws MeprGatewayException When payment processing fails.
     * @return void
     */
    public function process_payment_form($txn)
    {
        if (isset($_REQUEST['mepr_payment_methods_hidden'])) {
            $order_bump_product_ids = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_map('intval', $_POST['mepr_order_bumps']) : [];
            $order_bump_products    = MeprCheckoutCtrl::get_order_bump_products($txn->product_id, $order_bump_product_ids);
            $this->process_order($txn, $order_bump_products);
        } elseif ($this->settings->stripe_checkout_enabled == 'on') {
            $order_bump_product_ids = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_map('intval', $_POST['mepr_order_bumps']) : [];
            $this->checkout_redirect($txn, $order_bump_product_ids);
        }

        throw new MeprGatewayException(__('Payment was unsuccessful, please check your payment details and try again.', 'memberpress'));
    }

    /**
     * Used to send data to a given payment gateway. In gateways which redirect
     * before this step is necessary this method should just be left blank.
     *
     * @param MeprTransaction $transaction The transaction object.
     *
     * @return void
     */
    public function process_payment($transaction)
    {
        // Not used.
    }

    /**
     * Used to record a successful recurring payment by the given gateway. It
     * should have the ability to record a successful payment or a failure. It is
     * this method that should be used when receiving an IPN from PayPal or a
     * Silent Post from Authorize.net.
     */
    public function record_subscription_payment()
    {
        // Not used.
    }

    /**
     * Handle a free invoice payment for a subscription
     *
     * This can happen if the customer has a credit balance in Stripe that covers the full recurring payment amount.
     *
     * @param MeprSubscription $sub       The subscription object.
     * @param float            $amount    The payment amount.
     * @param string           $trans_num The transaction number.
     *
     * @return void
     */
    public function record_free_sub_payment(MeprSubscription $sub, $amount, $trans_num)
    {
        if (MeprTransaction::txn_exists($trans_num)) {
            return;
        }

        $txn                  = new MeprTransaction();
        $txn->user_id         = $sub->user_id;
        $txn->product_id      = $sub->product_id;
        $txn->status          = MeprTransaction::$complete_str;
        $txn->txn_type        = MeprTransaction::$payment_str;
        $txn->trans_num       = $trans_num;
        $txn->gateway         = $this->id;
        $txn->subscription_id = $sub->id;
        $txn->set_gross($amount);
        $txn->store();
    }

    /**
     * Handle the Stripe `invoice.payment_succeeded` webhook
     *
     * @param  stdClass $invoice The Stripe Invoice data.
     * @throws MeprHttpException            If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException          If there was an invalid or error response from Stripe.
     */
    public function handle_invoice_payment_succeeded_webhook($invoice)
    {
        if (empty($invoice->subscription) || empty($invoice->customer)) {
            return;
        }

        // We don't do anything here for $0 invoices that are also the first subscription payment, or the first subscription
        // payment for Stripe Checkout. Return early to avoid unnecessary Stripe requests (to prevent rate limit errors).
        if ($invoice->billing_reason == 'subscription_create' && (!isset($invoice->charge) || $this->settings->stripe_checkout_enabled == 'on')) {
            return;
        }

        // Fetch expanded invoice data from Stripe.
        $invoice = (object) $this->send_stripe_request("invoices/$invoice->id", [
            'expand' => [
                'charge',
                'customer',
                'payment_intent',
                'payment_intent.payment_method',
            ],
        ], 'get');

        $customer       = isset($invoice->customer) ? (object) $invoice->customer : null;
        $payment_method = isset($invoice->payment_intent['payment_method']) ? (object) $invoice->payment_intent['payment_method'] : null;

        $sub = MeprSubscription::get_one_by_subscr_id($invoice->subscription);

        if (!($sub instanceof MeprSubscription)) {
            if ($customer) {
                // Look for an old cus_xxx subscription.
                $sub = MeprSubscription::get_one_by_subscr_id($customer->id);
            }

            if (!($sub instanceof MeprSubscription)) {
                return;
            }
        }

        // If this isn't for us, bail.
        if ($sub->gateway != $this->id) {
            return;
        }

        if ($invoice->billing_reason == 'subscription_create') {
            $txn_res = MeprTransaction::get_one_by_trans_num($invoice->id);

            if (!empty($txn_res) && isset($txn_res->id)) {
                $txn = new MeprTransaction($txn_res->id);

                if (!empty($txn->id) && $txn->gateway == $this->id && $txn->subscription_id == $sub->id) {
                    $order                   = $txn->order();
                    $order_bump_transactions = $order instanceof MeprOrder ? MeprTransaction::get_all_by_order_id_and_gateway($order->id, $this->id, $txn->id) : [];

                    if ($order instanceof MeprOrder && count($order_bump_transactions)) {
                        if (!$order->is_complete() && !$order->is_processing()) {
                              $order->update_meta('processing', true);

                              $this->record_cc_vars($sub, $payment_method);
                              $this->record_create_sub($sub);

                            if ($sub->trial && $sub->trial_days > 0) {
                                $amount                  = (float) $sub->trial_total;
                                $txn_expires_at_override = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
                            } else {
                                $amount                  = (float) $sub->total;
                                $txn_expires_at_override = null;
                            }

                            if ($amount > 0) {
                                $this->record_sub_payment($sub, $amount, sprintf('mi_%d_%s', $order->id, uniqid()), $this->get_card($payment_method), $txn_expires_at_override, $order->id);
                            }

                            foreach ($order_bump_transactions as $transaction) {
                                $trans_num = sprintf('mi_%d_%s', $order->id, uniqid());

                                if (!$transaction->is_payment_required()) {
                                    MeprTransaction::create_free_transaction($transaction, false, $trans_num);
                                    continue;
                                }

                                if ($transaction->is_one_time_payment()) {
                                    $this->record_one_time_payment($transaction, $trans_num);
                                } else {
                                    if ($customer && $payment_method) {
                                        try {
                                            $this->create_sub($transaction, $customer->id, $payment_method, $trans_num, $order->id);
                                        } catch (Exception $e) {
                                            continue;
                                        }
                                    }
                                }
                            }

                            if (isset($invoice->charge['id'])) {
                                $order->trans_num = $invoice->charge['id'];
                            }

                            $order->status = MeprOrder::$complete_str;
                            $order->store();
                            $order->delete_meta('processing');
                        }

                        if ($payment_method && isset($customer->invoice_settings) && is_array($customer->invoice_settings)) {
                            $default_payment_method = isset($customer->invoice_settings['default_payment_method']) ? $customer->invoice_settings['default_payment_method'] : null;

                            if (empty($default_payment_method)) {
                                $this->set_customer_default_payment_method($customer->id, $payment_method->id);
                            }
                        }

                        return;
                    }
                }
            } else {
                // Check that this transaction hasn't been processed before.
                $txn_res = MeprTransaction::get_one_by_trans_num($invoice->subscription);

                if (isset($txn_res->id) && $txn_res->id > 0) {
                    return;
                }
            }

            $this->record_cc_vars($sub, $payment_method);
            $this->record_create_sub($sub);

            if ($payment_method && isset($customer->invoice_settings) && is_array($customer->invoice_settings)) {
                $default_payment_method = isset($customer->invoice_settings['default_payment_method']) ? $customer->invoice_settings['default_payment_method'] : null;

                if (empty($default_payment_method)) {
                    $this->set_customer_default_payment_method($customer->id, $payment_method->id);
                }
            }
        }

        if (isset($invoice->charge)) {
            $amount                  = (float) $invoice->charge['amount'];
            $txn_expires_at_override = null;

            if (!self::is_zero_decimal_currency()) {
                $amount = $amount / 100;
            }

            if ($invoice->billing_reason == 'subscription_create' && $sub->trial && $sub->trial_days > 0) {
                $txn_expires_at_override = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
            }

            $this->record_sub_payment($sub, $amount, $invoice->charge['id'], $this->get_card($payment_method), $txn_expires_at_override);
        } elseif ($invoice->billing_reason != 'subscription_create') {
            $amount = self::is_zero_decimal_currency() ? $invoice->total : $invoice->total / 100;

            $this->record_free_sub_payment($sub, $amount, $invoice->id);
        }
    }

    /**
     * Handle the invoice.payment_failed webhook
     *
     * Sends an email to the customer to pay the outstanding invoice.
     *
     * @param stdClass $invoice The Stripe Invoice object.
     */
    public function handle_invoice_payment_failed_webhook($invoice)
    {
        try {
            $email = MeprEmailFactory::fetch('MeprUserStripeInvoiceEmail');

            if ($email->enabled() && $invoice->hosted_invoice_url) {
                $sub = MeprSubscription::get_one_by_subscr_id($invoice->subscription);

                if (!($sub instanceof MeprSubscription)) {
                    // Look for an old cus_xxx subscription.
                    $sub = MeprSubscription::get_one_by_subscr_id($invoice->customer);
                }

                if (
                    $sub instanceof MeprSubscription &&
                    $sub->id > 0 &&
                    MeprHooks::apply_filters('mepr_send_invoice_payment_failed_email', $sub->txn_count > 1, $invoice, $sub)
                ) {
                    if ($sub->gateway != $this->id) {
                        return;
                    }

                    $usr = $sub->user();

                    if ($usr->ID > 0) {
                        $email->to = $usr->formatted_email();

                        $params = array_merge(
                            MeprSubscriptionsHelper::get_email_params($sub),
                            ['stripe_invoice_url' => $invoice->hosted_invoice_url]
                        );

                        $email->send($params);
                    }
                }
            }
        } catch (Exception $e) {
            // Fail silently.
        }
    }

    /**
     * Handle the payment_intent.succeeded webhook
     *
     * Processes a successful PaymentIntent, creating subscriptions if necessary.
     *
     * @param stdClass $payment_intent The PaymentIntent object.
     *
     * @return void
     */
    public function handle_payment_intent_succeeded_webhook($payment_intent)
    {
        if (
            empty($payment_intent->id) ||
            empty($payment_intent->payment_method) ||
            empty($payment_intent->customer)
        ) {
            return;
        }

        $charge_id = $this->get_payment_intent_charge_id($payment_intent);
        if (empty($charge_id)) {
            return;
        }

        $txn_res = MeprTransaction::get_one_by_trans_num($payment_intent->id);

        if (empty($txn_res) || !isset($txn_res->id)) {
            return;
        }

        $txn = new MeprTransaction($txn_res->id);

        if (empty($txn->id) || $txn->gateway != $this->id) {
            return;
        }

        try {
            $payment_intent = (object) $this->send_stripe_request("payment_intents/$payment_intent->id", [
                'expand' => [
                    'payment_method',
                    'customer',
                ],
            ], 'get');

            $customer                = (object) $payment_intent->customer;
            $payment_method          = (object) $payment_intent->payment_method;
            $order                   = $txn->order();
            $order_bump_transactions = $order instanceof MeprOrder ? MeprTransaction::get_all_by_order_id_and_gateway($order->id, $this->id, $txn->id) : [];

            if ($order instanceof MeprOrder && count($order_bump_transactions)) {
                if (!$order->is_complete() && !$order->is_processing()) {
                    $order->update_meta('processing', true);

                    foreach (array_merge([$txn], $order_bump_transactions) as $transaction) {
                        $trans_num = sprintf('mi_%d_%s', $order->id, uniqid());

                        if (!$transaction->is_payment_required()) {
                            MeprTransaction::create_free_transaction($transaction, false, $trans_num);
                            continue;
                        }

                        if ($transaction->is_one_time_payment()) {
                            $this->record_one_time_payment($transaction, $trans_num);
                        } else {
                            try {
                                $this->create_sub($transaction, $customer->id, $payment_method, $trans_num, $order->id);
                            } catch (Exception $e) {
                                continue;
                            }
                        }
                    }

                    $order->trans_num = $charge_id;
                    $order->status    = MeprOrder::$complete_str;
                    $order->store();
                    $order->delete_meta('processing');
                }
            } else {
                $this->record_one_time_payment($txn, $charge_id);
            }

            if ($payment_method && isset($customer->invoice_settings) && is_array($customer->invoice_settings)) {
                $default_payment_method = isset($customer->invoice_settings['default_payment_method']) ? $customer->invoice_settings['default_payment_method'] : null;

                if (empty($default_payment_method) && isset($payment_intent->setup_future_usage)) {
                    $this->set_customer_default_payment_method($customer->id, $payment_method->id);
                }
            }
        } catch (Exception $e) {
            http_response_code(500);
            die($e->getMessage());
        }
    }

    /**
     * Handle the setup_intent.succeeded webhook
     *
     * Processes a successful SetupIntent, creating subscriptions if necessary.
     *
     * @param stdClass $setup_intent The SetupIntent object.
     *
     * @return void
     */
    public function handle_setup_intent_succeeded_webhook($setup_intent)
    {
        if (empty($setup_intent->id) || empty($setup_intent->payment_method) || empty($setup_intent->customer)) {
            return;
        }

        $txn_res = MeprTransaction::get_one_by_trans_num($setup_intent->id);

        if (empty($txn_res) || !isset($txn_res->id)) {
            return;
        }

        $txn = new MeprTransaction($txn_res->id);

        if (empty($txn->id) || $txn->gateway != $this->id) {
            return;
        }

        // This only handles free trials or 100% off coupons.
        if ($txn->is_payment_required()) {
            $sub = $txn->subscription();

            if (!($sub instanceof MeprSubscription) || $sub->status == MeprSubscription::$active_str || $sub->gateway != $this->id) {
                return;
            }

            if (!($sub->trial && $sub->trial_days > 0 && (float) $sub->trial_amount <= 0.00)) {
                return;
            }
        }

        try {
            $setup_intent = (object) $this->send_stripe_request("setup_intents/$setup_intent->id", [
                'expand' => [
                    'payment_method',
                    'customer',
                ],
            ], 'get');

            $customer                = (object) $setup_intent->customer;
            $payment_method          = (object) $setup_intent->payment_method;
            $order                   = $txn->order();
            $order_bump_transactions = $order instanceof MeprOrder ? MeprTransaction::get_all_by_order_id_and_gateway($order->id, $this->id, $txn->id) : [];

            if ($order instanceof MeprOrder && count($order_bump_transactions)) {
                if (!$order->is_complete() && !$order->is_processing()) {
                    $order->update_meta('processing', true);

                    foreach (array_merge([$txn], $order_bump_transactions) as $transaction) {
                        $trans_num = sprintf('mi_%d_%s', $order->id, uniqid());

                        if (!$transaction->is_payment_required()) {
                            MeprTransaction::create_free_transaction($transaction, false, $trans_num);
                            continue;
                        }

                        // We only need to handle free trials here, not one-time payments.
                        if (!$transaction->is_one_time_payment()) {
                            try {
                                $this->create_sub($transaction, $customer->id, $payment_method, $trans_num, $order->id);
                            } catch (Exception $e) {
                                continue;
                            }
                        }
                    }

                    $order->trans_num = $setup_intent->id;
                    $order->status    = MeprOrder::$complete_str;
                    $order->store();
                    $order->delete_meta('processing');
                }
            } else {
                $this->create_sub($txn, $customer->id, $payment_method, $setup_intent->id);
            }

            if ($payment_method && isset($customer->invoice_settings) && is_array($customer->invoice_settings)) {
                $default_payment_method = isset($customer->invoice_settings['default_payment_method']) ? $customer->invoice_settings['default_payment_method'] : null;

                if (empty($default_payment_method)) {
                    $this->set_customer_default_payment_method($customer->id, $payment_method->id);
                }
            }
        } catch (Exception $e) {
            http_response_code(500);
            die($e->getMessage());
        }
    }

    /**
     * Used to record a declined payment.
     */
    public function record_payment_failure()
    {
        if (isset($_REQUEST['data'])) {
            $charge  = (object) $_REQUEST['data'];
            $txn_res = MeprTransaction::get_one_by_trans_num($charge->id);

            if (is_object($txn_res) and isset($txn_res->id)) {
                $txn         = new MeprTransaction($txn_res->id);
                $txn->status = MeprTransaction::$failed_str;
                $txn->store();
            } else {
                // Fetch expanded charge data from Stripe.
                $args = [
                    'expand' => [
                        'invoice',
                    ],
                ];

                $charge = (object) $this->send_stripe_request("charges/{$charge->id}", $args, 'get');

                $sub = isset($charge->invoice['subscription']) ? MeprSubscription::get_one_by_subscr_id($charge->invoice['subscription']) : null;

                if (!($sub instanceof MeprSubscription)) {
                    // Look for an old cus_xxx subscription.
                    $sub = isset($charge->customer) ? MeprSubscription::get_one_by_subscr_id($charge->customer) : null;
                }

                if ($sub instanceof MeprSubscription) {
                    if ($sub->gateway != $this->id) {
                        return false;
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
                    $txn->coupon_id       = $coupon_id;
                    $txn->txn_type        = MeprTransaction::$payment_str;
                    $txn->status          = MeprTransaction::$failed_str;
                    $txn->subscription_id = $sub->id;
                    $txn->trans_num       = $charge->id;
                    $txn->gateway         = $this->id;

                    if (self::is_zero_decimal_currency()) {
                        $txn->set_gross((float)$charge->amount);
                    } else {
                        $txn->set_gross((float)$charge->amount / 100);
                    }

                    $txn->store();

                    // Reload the subscription in case it was modified while storing the transaction.
                    $sub = new MeprSubscription($sub->id);

                    // If first payment fails, Stripe will not set up the subscription, so we need to mark it as cancelled.
                    if ($sub->txn_count == 0 && !($sub->trial && $sub->trial_amount == 0.00)) {
                        $sub->status = MeprSubscription::$cancelled_str;
                    }

                    $sub->expire_txns(); // Expire associated transactions for the old subscription.
                    $sub->store();
                } else {
                    return false; // Nothing we can do here ... so we outta here.
                }
            }

            MeprUtils::send_failed_txn_notices($txn);

            return $txn;
        }

        return false;
    }

    /**
     * Used to record a successful payment by the given gateway. It should have
     * the ability to record a successful payment or a failure. It is this method
     * that should be used when receiving an IPN from PayPal or a Silent Post
     * from Authorize.net.
     */
    public function record_payment()
    {
        // Not used.
    }

    /**
     * This method should be used by the class to record a successful refund from
     * the gateway. This method should also be used by any IPN requests or Silent Posts.
     *
     * @param MeprTransaction $txn The transaction object.
     *
     * @return integer|void
     */
    public function process_refund(MeprTransaction $txn)
    {
        $args   = MeprHooks::apply_filters('mepr_stripe_refund_args', [], $txn);
        $refund = (object)$this->send_stripe_request("charges/{$txn->trans_num}/refund", $args);
        $this->email_status('Stripe Refund: ' . MeprUtils::object_to_string($refund), $this->settings->debug);
        $_REQUEST['data'] = $refund;
        return $this->record_refund();
    }

    /**
     * This method should be used by the class to record a successful refund from
     * the gateway. This method should also be used by any IPN requests or Silent Posts.
     *
     * @return integer|void The transaction id
     */
    public function record_refund()
    {
        if (isset($_REQUEST['data'])) {
            $charge = (object)$_REQUEST['data'];
            $obj    = MeprTransaction::get_one_by_trans_num($charge->id);

            if (!is_null($obj) && (int)$obj->id > 0) {
                $txn = new MeprTransaction($obj->id);

                // Seriously ... if txn was already refunded what are we doing here?
                if ($txn->status == MeprTransaction::$refunded_str) {
                    return $txn->id;
                }

                $txn->status = MeprTransaction::$refunded_str;
                $txn->store();

                MeprUtils::send_refunded_txn_notices(
                    $txn,
                    MeprHooks::apply_filters('mepr_stripe_transaction_refunded_event_args', '', $txn, $charge)
                );

                return $txn->id;
            }
        }

        return false;
    }

    /**
     * Process a trial payment
     *
     * @param  MeprTransaction $txn The transaction to process.
     * @throws MeprGatewayException When trial payment processing fails.
     * @return void
     */
    public function process_trial_payment($txn)
    {
        // Not used.
    }

    /**
     * Record a trial payment
     *
     * @param  MeprTransaction $txn The transaction to record.
     * @throws MeprGatewayException When recording the trial payment fails.
     * @return void
     */
    public function record_trial_payment($txn)
    {
        // Not used.
    }

    /**
     * Process a completed Stripe Checkout session
     *
     * @param  object $checkout_session The Stripe Checkout session object.
     * @throws MeprGatewayException When session processing fails.
     * @return void
     */
    public function process_stripe_checkout_session_completed($checkout_session)
    {
        if (empty($checkout_session->id) || empty($checkout_session->customer)) {
            return;
        }

        $txn_res = MeprTransaction::get_one_by_trans_num($checkout_session->id);

        if (empty($txn_res) || !isset($txn_res->id)) {
            return;
        }

        $txn = new MeprTransaction($txn_res->id);

        if (empty($txn->id) || $txn->gateway != $this->id) {
            return;
        }

        try {
            $checkout_session = (object) $this->send_stripe_request("checkout/sessions/$checkout_session->id", [
                'expand' => [
                    'customer',
                    'payment_intent.payment_method',
                    'setup_intent.payment_method',
                    'subscription.default_payment_method',
                    'subscription.latest_invoice.charge',
                    'subscription.latest_invoice.payment_intent.payment_method',
                ],
            ], 'get');

            $customer       = isset($checkout_session->customer) ? (object) $checkout_session->customer : null;
            $payment_method = null;
            $charge_id      = null;

            if ($checkout_session->mode == 'subscription') {
                if (isset($checkout_session->subscription['default_payment_method'])) {
                    $payment_method = (object) $checkout_session->subscription['default_payment_method'];
                }

                if (isset($checkout_session->subscription['latest_invoice']['payment_intent'])) {
                    $payment_intent = (object) $checkout_session->subscription['latest_invoice']['payment_intent'];

                    if ($payment_intent) {
                        if ($checkout_session->payment_status == 'unpaid' && $this->is_async_payment_method($payment_intent->payment_method['type'])) {
                            return; // Async payment method, wait for the checkout.session.async_payment_succeeded webhook.
                        }

                        $charge_id = $this->get_payment_intent_charge_id($payment_intent);
                    }
                }
            } elseif ($checkout_session->mode == 'payment') {
                if (isset($checkout_session->payment_intent['payment_method'])) {
                    $payment_method = (object) $checkout_session->payment_intent['payment_method'];
                }

                if (isset($checkout_session->payment_intent)) {
                    $payment_intent = (object) $checkout_session->payment_intent;

                    if ($payment_intent) {
                        if ($checkout_session->payment_status == 'unpaid' && $this->is_async_payment_method($payment_intent->payment_method['type'])) {
                            return; // Async payment method, wait for the checkout.session.async_payment_succeeded webhook.
                        }

                        $charge_id = $this->get_payment_intent_charge_id($payment_intent);
                    }
                }
            } elseif ($checkout_session->mode == 'setup') {
                if (isset($checkout_session->setup_intent['payment_method'])) {
                    $payment_method = (object) $checkout_session->setup_intent['payment_method'];
                }

                if (isset($checkout_session->setup_intent['id'])) {
                    $charge_id = $checkout_session->setup_intent['id'];
                }
            }

            $order                   = $txn->order();
            $order_bump_transactions = $order instanceof MeprOrder ? MeprTransaction::get_all_by_order_id_and_gateway($order->id, $this->id, $txn->id) : [];

            if ($order instanceof MeprOrder && count($order_bump_transactions)) {
                if (!$order->is_complete() && !$order->is_processing()) {
                    $order->update_meta('processing', true);
                    $trans_num = sprintf('mi_%d_%s', $order->id, uniqid());

                    if (!$txn->is_payment_required()) {
                        MeprTransaction::create_free_transaction($txn, false, $trans_num);
                    } elseif ($txn->is_one_time_payment()) {
                        $this->record_one_time_payment($txn, $trans_num);
                    } else {
                        $sub = $txn->subscription();

                        if ($sub instanceof MeprSubscription) {
                            if (isset($checkout_session->subscription['id'])) {
                                $sub->subscr_id = $checkout_session->subscription['id'];
                            }

                            $this->record_cc_vars($sub, $payment_method);
                            $this->record_create_sub($sub);

                            if ($sub->trial && $sub->trial_days > 0) {
                                $amount                  = (float) $sub->trial_total;
                                $txn_expires_at_override = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
                            } else {
                                $amount                  = (float) $sub->total;
                                $txn_expires_at_override = null;
                            }

                            if ($amount > 0) {
                                $this->record_sub_payment($sub, $amount, sprintf('mi_%d_%s', $order->id, uniqid()), $this->get_card($payment_method), $txn_expires_at_override, $order->id);
                            }
                        }
                    }

                    foreach ($order_bump_transactions as $transaction) {
                        $trans_num = sprintf('mi_%d_%s', $order->id, uniqid());

                        if (!$transaction->is_payment_required()) {
                            MeprTransaction::create_free_transaction($transaction, false, $trans_num);
                            continue;
                        }

                        if ($transaction->is_one_time_payment()) {
                            $this->record_one_time_payment($transaction, $trans_num);
                        } else {
                            if ($customer && $payment_method) {
                                try {
                                    $this->create_sub($transaction, $customer->id, $payment_method, $trans_num, $order->id);
                                } catch (Exception $e) {
                                    continue;
                                }
                            }
                        }
                    }

                    if ($charge_id) {
                        $order->trans_num = $charge_id;
                    }

                    $order->status = MeprOrder::$complete_str;
                    $order->store();
                    $order->delete_meta('processing');
                }
            } else {
                if ($txn->is_one_time_payment()) {
                    $trans_num = $charge_id ? $charge_id : $txn->trans_num;

                    $this->record_one_time_payment($txn, $trans_num);
                } else {
                    $sub = $txn->subscription();

                    if ($sub instanceof MeprSubscription) {
                        if (isset($checkout_session->subscription['id'])) {
                            $sub->subscr_id = $checkout_session->subscription['id'];
                        }

                        $this->record_cc_vars($sub, $payment_method);
                        $this->record_create_sub($sub);

                        if (isset($checkout_session->subscription['latest_invoice']['charge'])) {
                            $charge                  = (object) $checkout_session->subscription['latest_invoice']['charge'];
                            $amount                  = (float) $charge->amount;
                            $txn_expires_at_override = null;

                            if (!self::is_zero_decimal_currency()) {
                                $amount = $amount / 100;
                            }

                            if ($sub->trial && $sub->trial_days > 0) {
                                $txn_expires_at_override = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
                            }

                            $this->record_sub_payment($sub, $amount, $charge->id, $this->get_card($payment_method), $txn_expires_at_override);
                        }
                    }
                }
            }

            if ($payment_method && isset($customer->invoice_settings) && is_array($customer->invoice_settings)) {
                $default_payment_method = isset($customer->invoice_settings['default_payment_method']) ? $customer->invoice_settings['default_payment_method'] : null;

                if (empty($default_payment_method) && (!isset($payment_intent) || isset($payment_intent->setup_future_usage))) {
                    $this->set_customer_default_payment_method($customer->id, $payment_method->id);
                }
            }
        } catch (Exception $e) {
            http_response_code(500);
            die($e->getMessage());
        }
    }

    /**
     * Process a failed async payment from Stripe Checkout
     *
     * @param  object $checkout_session The Stripe Checkout session object.
     * @throws MeprGatewayException When payment fails.
     * @return void
     */
    public function process_checkout_session_async_payment_failed($checkout_session)
    {
        if (empty($checkout_session->id) || empty($checkout_session->customer)) {
            return;
        }

        $txn_res = MeprTransaction::get_one_by_trans_num($checkout_session->id);

        if (empty($txn_res) || !isset($txn_res->id)) {
            return;
        }

        $txn = new MeprTransaction($txn_res->id);

        if (empty($txn->id) || $txn->gateway != $this->id) {
            return;
        }

        try {
            // Only one-time payments need to be handled here, failed payments for subscriptions will be recorded on the
            // charge.failed event.
            if ($checkout_session->mode == 'payment') {
                $checkout_session = (object) $this->send_stripe_request("checkout/sessions/$checkout_session->id", [
                    'expand' => [
                        'payment_intent',
                    ],
                ], 'get');

                $charge_id = $this->get_payment_intent_charge_id((object) $checkout_session->payment_intent);

                if ($charge_id) {
                      $txn->trans_num = $charge_id;
                      $txn->store();

                      $_REQUEST['data'] = (object) ['id' => $charge_id];

                      $this->record_payment_failure();
                }
            }
        } catch (Exception $e) {
            http_response_code(500);
            die($e->getMessage());
        }
    }

    /**
     * Used to send subscription data to a given payment gateway. In gateways
     * which redirect before this step is necessary this method should just be
     * left blank.
     *
     * @param MeprTransaction $txn The transaction object.
     *
     * @return void
     */
    public function process_create_subscription($txn)
    {
        // Not used.
    }

    /**
     * Used to record a successful subscription by the given gateway. It should have
     * the ability to record a successful subscription or a failure. It is this method
     * that should be used when receiving an IPN from PayPal or a Silent Post
     * from Authorize.net.
     */
    public function record_create_subscription()
    {
        // Not used.
    }

    /**
     * Create a new Stripe subscription and records the first payment as complete.
     *
     * @param  MeprTransaction $txn            The MemberPress transaction.
     * @param  string          $customer_id    The Stripe Customer ID.
     * @param  stdClass        $payment_method The Stripe PaymentMethod data.
     * @param  string          $trans_num      The transaction number for the payment.
     * @param  integer         $order_id       The order ID for the payment transaction.
     * @throws DateMalformedStringException    If there was an error during date calculations.
     * @throws MeprHttpException               If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException             If there was an invalid or error response from Stripe.
     */
    public function create_sub(MeprTransaction $txn, $customer_id, $payment_method, $trans_num, $order_id = 0)
    {
        $sub = $txn->subscription();

        if (!($sub instanceof MeprSubscription)) {
            return;
        }

        if ($sub->status == MeprSubscription::$active_str) {
            return;
        }

        if ($sub->trial && $sub->trial_days > 0) {
            $amount                  = (float) $sub->trial_total;
            $trial_days              = $sub->trial_days;
            $txn_expires_at_override = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
        } else {
            $amount                  = (float) $sub->total;
            $txn_expires_at_override = null;

            // If the sub doesn't have a trial, we want to create a subscription with the trial days set to cover one period
            // since the initial period was already paid for.
            $now        = new DateTimeImmutable('now');
            $end        = $now->modify(sprintf('+%d %s', $sub->period, $sub->period_type));
            $trial_days = $end->diff($now)->format('%a');
        }

        $subscription = $this->create_subscription($txn, $sub, $txn->product(), $customer_id, $payment_method->id, false, $trial_days);

        $sub->subscr_id = $subscription->id;

        if (isset($payment_method->card) && is_array($payment_method->card)) {
            $sub->cc_last4     = $payment_method->card['last4'];
            $sub->cc_exp_month = $payment_method->card['exp_month'];
            $sub->cc_exp_year  = $payment_method->card['exp_year'];
        }

        $sub->store();

        $this->record_create_sub($sub);

        if ($amount > 0) {
            $this->record_sub_payment($sub, $amount, $trans_num, $this->get_card($payment_method), $txn_expires_at_override, $order_id);
        }
    }

    /**
     * Record the creation of a new subscription
     *
     * @param MeprSubscription $sub The subscription object.
     *
     * @return void
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

        MeprHooks::do_action('mepr_stripe_subscription_created', $txn, $sub);
    }

    /**
     * Process subscription updates
     *
     * @param  string $sub_id The subscription ID to update.
     * @throws MeprGatewayException When subscription update fails.
     * @return void
     */
    public function process_update_subscription($sub_id)
    {
        // This is handled via Ajax.
    }

    /**
     * Create a SetupIntent
     *
     * @param  string          $customer_id The Stripe Customer ID.
     * @param  MeprTransaction $txn         The MemberPress transaction.
     * @param  MeprProduct     $prd         The MemberPress product.
     * @return stdClass                         The Stripe SetupIntent object
     * @throws MeprHttpException                If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException              If there was an invalid or error response from Stripe.
     */
    public function create_setup_intent($customer_id, MeprTransaction $txn, MeprProduct $prd)
    {
        $args = MeprHooks::apply_filters('mepr_stripe_setup_intent_args', [
            'customer'             => $customer_id,
            'payment_method_types' => $this->get_setup_intent_payment_method_types(),
            'description'          => $prd->post_title,
            'metadata'             => [
                'platform'              => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
                'transaction_id'        => $txn->id,
                'site_url'              => get_site_url(),
                'ip_address'            => MeprAntiCardTestingCtrl::get_ip(),
                'memberpress_product'    => $prd->post_title,
                'memberpress_product_id' => $prd->ID,
            ],
        ], $txn);

        $setup_intent = (object) $this->send_stripe_request('setup_intents', $args);

        return $setup_intent;
    }

    /**
     * Create a SetupIntent for updating a subscription's payment method
     *
     * @param  string $customer_id The Stripe Customer ID.
     * @return stdClass                         The Stripe SetupIntent object
     * @throws MeprHttpException                If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException              If there was an invalid or error response from Stripe.
     */
    public function create_update_setup_intent($customer_id)
    {
        $args = MeprHooks::apply_filters('mepr_stripe_update_setup_intent_args', [
            'customer'             => $customer_id,
            'payment_method_types' => $this->get_update_setup_intent_payment_method_types(),
            'metadata'             => [
                'platform'   => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
                'site_url'   => get_site_url(),
                'ip_address' => MeprAntiCardTestingCtrl::get_ip(),
            ],
        ]);

        $setup_intent = (object) $this->send_stripe_request('setup_intents', $args);

        return $setup_intent;
    }

    /**
     * Update a payment method for a subscription
     *
     * @param  MeprSubscription $sub            The MemberPress subscription.
     * @param  MeprUser         $usr            The MemberPress user.
     * @param  stdClass         $payment_method The Stripe PaymentMethod data, must be attached to the customer.
     * @return stdClass                               The Stripe Subscription data
     * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException                    If there was an invalid or error response from Stripe.
     */
    public function update_subscription_payment_method(MeprSubscription $sub, MeprUser $usr, $payment_method)
    {
        // Attach the payment method to the customer and set this as the default payment method for the subscription.
        if (strpos($sub->subscr_id, 'sub_') === 0) {
            $subscription = $this->retrieve_subscription($sub->subscr_id);
            $customer_id  = $usr->get_stripe_customer_id($this->get_meta_gateway_id());

            if (!is_string($customer_id) || strpos($customer_id, 'cus_') !== 0) {
                // If the Stripe customer ID isn't saved locally for this user, let's save it. This can happen if sub_
                // subscriptions are imported and the cus_ IDs aren't imported for users.
                $usr->set_stripe_customer_id($this->get_meta_gateway_id(), $subscription->customer);
            }
        } else {
            $subscription = $this->get_customer_subscription($sub->subscr_id);
        }

        $args = ['default_payment_method' => $payment_method->id];

        if (!empty($payment_method->type)) {
            // Ensure that this payment method type is allowed on the subscription.
            $payment_method_types = $subscription->payment_settings['payment_method_types'];

            if (is_array($payment_method_types) && !in_array($payment_method->type, $payment_method_types, true)) {
                $args = array_merge($args, [
                    'payment_settings' => [
                        'payment_method_types' => array_merge($payment_method_types, [$payment_method->type]),
                    ],
                ]);
            }
        }

        $this->send_stripe_request("subscriptions/{$subscription->id}", $args);

        if (MeprHooks::apply_filters('mepr_stripe_update_set_as_default', true)) {
            $this->send_stripe_request("customers/{$subscription->customer}", [
                'invoice_settings' => [
                    'default_payment_method' => $payment_method->id,
                ],
            ]);
        }

        // Save the card details.
        if (isset($payment_method->card) && is_array($payment_method->card)) {
            $sub->cc_last4     = $payment_method->card['last4'];
            $sub->cc_exp_month = $payment_method->card['exp_month'];
            $sub->cc_exp_year  = $payment_method->card['exp_year'];
        } else {
            $sub->cc_last4     = '';
            $sub->cc_exp_month = '';
            $sub->cc_exp_year  = '';
        }

        $sub->store();

        return $subscription;
    }

    /**
     * Get the Stripe subscription for the given customer ID
     *
     * @param  string $customer_id The Stripe customer ID.
     * @return object                           The Stripe subscription object
     * @throws MeprHttpException                If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException              If there was an invalid or error response from Stripe.
     */
    public function get_customer_subscription($customer_id)
    {
        $args = [
            'expand' => [
                'latest_invoice',
                'default_payment_method',
            ],
        ];

        $subscription = (object) $this->send_stripe_request("customers/{$customer_id}/subscription", $args, 'get');

        return $subscription;
    }

    /**
     * Retry the payment for the given invoice ID
     *
     * @param  string $invoice_id The Stripe invoice ID.
     * @throws MeprHttpException               If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException             If there was an invalid or error response from Stripe.
     */
    public function retry_invoice_payment($invoice_id)
    {
        $this->send_stripe_request("invoices/{$invoice_id}/pay", [], 'post');
    }

    /**
     * This method should be used by the class to record a successful cancellation
     * from the gateway. This method should also be used by any IPN requests or
     * Silent Posts.
     */
    public function record_update_subscription()
    {
        // No need for this one with stripe.
    }

    /**
     * Used to suspend a subscription by the given gateway.
     *
     * @param string $sub_id The subscription ID.
     *
     * @return MeprSubscription|false
     * @throws MeprGatewayException When the subscription has already been paused or is in a free trial.
     */
    public function process_suspend_subscription($sub_id)
    {
        $mepr_options = MeprOptions::fetch();
        $sub          = new MeprSubscription($sub_id);

        if ($sub->status == MeprSubscription::$suspended_str) {
            throw new MeprGatewayException(__('This subscription has already been paused.', 'memberpress'));
        }

        if (!MeprUtils::is_mepr_admin() && $sub->in_free_trial()) {
            throw new MeprGatewayException(__('Sorry, subscriptions cannot be paused during a free trial.', 'memberpress'));
        }

        $args = MeprHooks::apply_filters('mepr_stripe_suspend_subscription_args', [], $sub);

        if (strpos($sub->subscr_id, 'sub_') === 0) {
            $res = $this->send_stripe_request("subscriptions/{$sub->subscr_id}", $args, 'delete');
        } else {
            try {
                $customer = $this->retrieve_customer($sub->subscr_id);
            } catch (Exception $e) {
                // If there's not already a customer then we're done here.
                return false;
            }

            // Yeah ... we're cancelling here bro ... with stripe we should be able to restart again.
            $res = $this->send_stripe_request("customers/{$customer->id}/subscription", $args, 'delete');
        }

        $_REQUEST['data'] = $res;

        return $this->record_suspend_subscription();
    }

    /**
     * This method should be used by the class to record a successful suspension
     * from the gateway.
     *
     * @return MeprSubscription|false The subscription object or false if the subscription was not found
     */
    public function record_suspend_subscription()
    {
        if (isset($_REQUEST['data'])) {
            $subscription = (object) $_REQUEST['data'];

            $sub = MeprSubscription::get_one_by_subscr_id($subscription->id);

            if (!($sub instanceof MeprSubscription)) {
                // Look for an old cus_xxx subscription.
                $sub = MeprSubscription::get_one_by_subscr_id($subscription->customer);
            }

            if ($sub instanceof MeprSubscription) {
                // Seriously ... if sub was already cancelled what are we doing here?
                if ($sub->status == MeprSubscription::$suspended_str) {
                    return $sub;
                }

                $sub->status = MeprSubscription::$suspended_str;
                $sub->store();

                MeprUtils::send_suspended_sub_notices($sub);
            }
        }

        return false;
    }

    /**
     * Used to suspend a subscription by the given gateway.
     *
     * @param string $sub_id The subscription ID.
     *
     * @return void
     * @throws MeprGatewayException When the subscription has already been resumed or currency is different.
     * @throws MeprGatewayRequiresActionException When manual payment action is required.
     */
    public function process_resume_subscription($sub_id)
    {
        $mepr_options = MeprOptions::fetch();
        MeprHooks::do_action('mepr-pre-stripe-resume-subscription', $sub_id); // Allow users to change the subscription programatically before resuming it.
        $sub = new MeprSubscription($sub_id);

        if ($sub->status == MeprSubscription::$active_str) {
            throw new MeprGatewayException(__('This subscription has already been resumed.', 'memberpress'));
        }

        $orig_trial        = $sub->trial;
        $orig_trial_days   = $sub->trial_days;
        $orig_trial_amount = $sub->trial_amount;
        $tax_inclusive     = $mepr_options->attr('tax_calc_type') == 'inclusive';

        if ($sub->is_expired() and !$sub->is_lifetime()) {
            $expiring_txn = $sub->expiring_txn();

            // If it's already expired with a real transaction
            // then we want to resume immediately.
            if (
                $expiring_txn != false && $expiring_txn instanceof MeprTransaction &&
                $expiring_txn->status != MeprTransaction::$confirmed_str
            ) {
                $sub->trial                     = false;
                $sub->trial_days                = 0;
                $sub->trial_amount              = 0.00;
                $sub->trial_tax_amount          = 0.00;
                $sub->trial_total               = 0.00;
                $sub->trial_tax_reversal_amount = 0.00;
                $sub->store();
            }
        } else {
            $sub->trial                     = true;
            $sub->trial_days                = MeprUtils::tsdays(strtotime($sub->expires_at) - time());
            $sub->trial_amount              = 0.00;
            $sub->trial_tax_amount          = 0.00;
            $sub->trial_total               = 0.00;
            $sub->trial_tax_reversal_amount = 0.00;
            $sub->store();
        }

        $usr = $sub->user();
        $prd = $sub->product();

        $customer_id       = $usr->get_stripe_customer_id($this->get_meta_gateway_id());
        $customer_id_valid = is_string($customer_id) && strpos($customer_id, 'cus_') === 0;
        $save_customer_id  = false;

        if (!$customer_id_valid) {
            // The user does not have a saved customer ID.
            if (strpos($sub->subscr_id, 'cus_') === 0) {
                // For cus_ subscriptions, save the subscription ID as their customer ID.
                $customer_id       = $sub->subscr_id;
                $customer_id_valid = true;
                $save_customer_id  = true;
            } else {
                // For sub_ subscriptions, look up the old subscription to find the customer ID.
                $old_subscription = $this->retrieve_subscription($sub->subscr_id);

                if (!empty($old_subscription->customer) && is_string($old_subscription->customer)) {
                    $customer_id       = $old_subscription->customer;
                    $customer_id_valid = true;
                    $save_customer_id  = true;
                }
            }
        }

        if ($customer_id_valid) {
            $customer = $this->retrieve_customer($customer_id);

            if ($customer->currency && strtolower($customer->currency) != strtolower($mepr_options->currency_code)) {
                throw new MeprGatewayException(__('The subscription is locked to a different currency. Please contact us to resume your subscription.', 'memberpress'));
            }
        } else {
            throw new MeprGatewayException(__('Customer not found', 'memberpress'));
        }

        // This needs to be after the currency check above.
        if ($save_customer_id) {
            $usr->set_stripe_customer_id($this->get_meta_gateway_id(), $customer_id);
        }

        $payment_method_id = !empty($customer->invoice_settings['default_payment_method']['id']) ? $customer->invoice_settings['default_payment_method']['id'] : null;

        if (empty($payment_method_id)) {
            $payment_method_id = $this->set_customer_default_payment_method($customer->id);
        }

        if ($tax_inclusive) {
            $plan_id     = $this->get_stripe_plan_id($sub, $prd, $sub->total);
            $tax_rate_id = $sub->tax_rate > 0 && $sub->tax_amount > 0 ? $this->get_stripe_tax_rate_id($sub->tax_desc, $sub->tax_rate, $prd, true) : null;
        } else {
            $plan_id     = $this->get_stripe_plan_id($sub, $prd, $sub->price);
            $tax_rate_id = $sub->tax_rate > 0 && $sub->tax_amount > 0 ? $this->get_stripe_tax_rate_id($sub->tax_desc, $sub->tax_rate, $prd, false) : null;
        }

        $item = ['plan' => $plan_id];

        if ($tax_rate_id) {
            $item['tax_rates'] = [$tax_rate_id];
        }

        $args = MeprHooks::apply_filters('mepr_stripe_resume_subscription_args', [
            'customer'    => $customer->id,
            'items'       => [$item],
            'expand'      => [
                'latest_invoice',
                'latest_invoice.charge',
                'latest_invoice.payment_intent',
                'latest_invoice.payment_intent.payment_method',
            ],
            'metadata'    => [
                'platform'   => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
                'site_url'   => get_site_url(),
                'ip_address' => MeprAntiCardTestingCtrl::get_ip(),
            ],
            'off_session' => 'true',
        ], $sub);

        // Specifically set a default_payment_method on the subscription.
        if (!empty($payment_method_id)) {
            $args = array_merge(['default_payment_method' => $payment_method_id], $args);
        }

        $event_args = '';

        if ($sub->trial) {
            $args       = array_merge(['trial_period_days' => $sub->trial_days], $args);
            $event_args = ['days_until_renewal' => $sub->trial_days];
        }

        $args = $this->add_application_fee_percentage($args);

        $subscription = (object) $this->send_stripe_request('subscriptions', $args);

        $sub->subscr_id    = $subscription->id;
        $sub->trial        = $orig_trial;
        $sub->trial_days   = $orig_trial_days;
        $sub->trial_amount = $orig_trial_amount;
        $sub->store();

        $this->maybe_record_sub_application_fee_meta($args, $sub);

        $this->email_status("process_resume_subscription: \n" . MeprUtils::object_to_string($sub) . "\n", $this->settings->debug);

        $invoice = (object) $subscription->latest_invoice;

        if ($invoice->status == 'open') {
            throw new MeprGatewayRequiresActionException(
                sprintf(
                // Translators: %1$s: open link tag, %2$s: close link tag.
                    __('The subscription could not be resumed automatically, %1$sclick here%2$s to complete the payment.', 'memberpress'),
                    sprintf('<a href="%s" target="_blank">', esc_url($invoice->hosted_invoice_url)),
                    '</a>'
                )
            );
        }

        $sub->status = MeprSubscription::$active_str;
        $sub->store();

        if (isset($invoice->charge)) {
            $amount = (float) $invoice->charge['amount'];

            if (!self::is_zero_decimal_currency()) {
                $amount = $amount / 100;
            }

            $payment_method = isset($invoice->payment_intent['payment_method']) ? (object) $invoice->payment_intent['payment_method'] : null;

            $this->record_sub_payment($sub, $amount, $invoice->charge['id'], $this->get_card($payment_method));
        }

        MeprUtils::send_resumed_sub_notices($sub, $event_args);
    }

    /**
     * Set a default payment method on a customer
     *
     * If the $payment_method_id param is null, the first card attached to the customer will be set as the default.
     *
     * @param  string      $customer_id       The Stripe Customer ID.
     * @param  string|null $payment_method_id The Stripe PaymentMethod ID to set as the default.
     * @return string|null                     The Stripe PaymentMethod ID that was set as the default
     */
    public function set_customer_default_payment_method($customer_id, $payment_method_id = null)
    {
        try {
            if (empty($payment_method_id)) {
                $payment_methods = (object) $this->send_stripe_request("customers/$customer_id/payment_methods", [
                    'type'  => 'card',
                    'limit' => '1',
                ], 'get');

                if (isset($payment_methods->data[0]['id'])) {
                      $payment_method_id = $payment_methods->data[0]['id'];
                } else {
                    return null;
                }
            }

            $this->send_stripe_request("customers/$customer_id", [
                'invoice_settings' => [
                    'default_payment_method' => $payment_method_id,
                ],
            ]);

            return $payment_method_id;
        } catch (Exception $e) {
            // Ignore, not critical enough to cause an error.
        }

        return null;
    }

    /**
     * This method should be used by the class to record a successful resuming of
     * as subscription from the gateway.
     */
    public function record_resume_subscription()
    {
        // Not used.
    }

    /**
     * Used to cancel a subscription by the given gateway. This method should be used
     * by the class to record a successful cancellation from the gateway. This method
     * should also be used by any IPN requests or Silent Posts.
     *
     * @param string $sub_id The subscription ID.
     *
     * @return boolean|MeprSubscription The subscription object or false if the subscription was not found
     * @throws MeprGatewayException When the subscription has already been cancelled.
     */
    public function process_cancel_subscription($sub_id)
    {
        $sub = new MeprSubscription($sub_id);

        if ($sub->status == MeprSubscription::$cancelled_str || $sub->status == MeprSubscription::$suspended_str) {
            throw new MeprGatewayException(__('This subscription has already been cancelled.', 'memberpress'));
        }

        if (strpos($sub->subscr_id, 'sub_') === 0) {
            $endpoint = "subscriptions/{$sub->subscr_id}";
        } else {
            try {
                $customer = $this->retrieve_customer($sub->subscr_id);
            } catch (MeprRemoteException $e) {
                throw new MeprGatewayException(__('The Subscription could not be cancelled here. Please login to your gateway\'s virtual terminal to cancel it.', 'memberpress'));
            } catch (Exception $e) {
                // If there's not already a customer then we're done here.
                return false;
            }

            $endpoint = "customers/{$customer->id}/subscription";
        }

        $args = MeprHooks::apply_filters('mepr_stripe_cancel_subscription_args', [], $sub);

        $subscription = $this->send_stripe_request($endpoint, $args, 'delete');

        $_REQUEST['data'] = $subscription;

        return $this->record_cancel_subscription();
    }

    /**
     * This method should be used by the class to record a successful cancellation
     * from the gateway. This method should also be used by any IPN requests or
     * Silent Posts.
     */
    public function record_cancel_subscription()
    {
        if (isset($_REQUEST['data'])) {
            $subscription = (object) $_REQUEST['data'];
            $sub          = MeprSubscription::get_one_by_subscr_id($subscription->id);

            if (!($sub instanceof MeprSubscription)) {
                // Look for an old cus_xxx subscription.
                $sub = MeprSubscription::get_one_by_subscr_id($subscription->customer);
            }

            if ($sub instanceof MeprSubscription) {
                // Seriously ... if sub was already cancelled what are we doing here?
                // Also, for stripe, since a suspension is only slightly different
                // than a cancellation, we kick it into high gear and check for that too.
                if ($sub->status == MeprSubscription::$cancelled_str || $sub->status == MeprSubscription::$suspended_str) {
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
            }
        }

        return false;
    }

    /**
     * This gets called on the 'init' hook when the signup form is processed ...
     * this is in place so that payment solutions like paypal can redirect
     * before any content is rendered.
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
     * Display the payment page
     *
     * @param  MeprTransaction $txn The transaction object.
     * @return string|void The payment page HTML
     */
    public function display_payment_page($txn)
    {
        if ($this->settings->stripe_checkout_enabled == 'on') {
            $order_bump_product_ids = isset($_GET['obs']) && is_array($_GET['obs']) ? array_map('intval', $_GET['obs']) : [];
            $this->checkout_redirect($txn, $order_bump_product_ids);
        }
    }

    /**
     * This gets called on wp_enqueue_script and enqueues a set of
     * scripts for use on the page containing the payment form
     */
    public function enqueue_payment_form_scripts()
    {
        if (wp_script_is('mepr-stripe-form', 'enqueued')) {
            return;
        }

        $mepr_options = MeprOptions::fetch();

        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], MEPR_VERSION);
        wp_enqueue_script('mepr-stripe-form', MEPR_GATEWAYS_URL . '/stripe/form.js', ['stripe-js', 'mepr-checkout-js', 'jquery.payment'], MEPR_VERSION);

        $l10n = [
            'api_version'                    => self::STRIPE_API_VERSION,
            'currency'                       => strtolower($mepr_options->currency_code),
            'payment_information_incomplete' => __('Please complete payment information', 'memberpress'),
            'elements_appearance'            => $this->get_elements_appearance(),
            'payment_element_terms'          => $this->get_payment_element_terms(),
            'ajax_url'                       => admin_url('admin-ajax.php'),
            'ajax_error'                     => __('An error occurred, please DO NOT submit the form again as you may be double charged. Please contact us for further assistance instead.', 'memberpress'),
            'invalid_response_error'         => __('The response from the server was invalid', 'memberpress'),
            'error_please_try_again'         => __('An error occurred, please try again', 'memberpress'),
            'top_error'                      => sprintf(
            // Translators: %1$s: open strong tag, %2$s: close strong tag, %3$s: error message.
                esc_html__('%1$sERROR%2$s: %3$s', 'memberpress'),
                '<strong>',
                '</strong>',
                '%s'
            ),
        ];

        wp_localize_script(
            'mepr-stripe-form',
            'MeprStripeGateway',
            ['l10n_print_after' => 'MeprStripeGateway = ' . wp_json_encode($l10n)]
        );
    }

    /**
     * Get the appearance data for Stripe Elements
     *
     * @return array|stdClass
     */
    private function get_elements_appearance()
    {
        $appearance = [];

        $appearance = MeprHooks::apply_filters('mepr-stripe-elements-appearance', $appearance);

        if (empty($appearance)) {
            return new stdClass(); // {} in JSON
        }

        return $appearance;
    }

    /**
     * Get the terms options for the payment element
     *
     * @return array|stdClass
     */
    private function get_payment_element_terms()
    {
        $terms = [
            'applePay'      => 'never',
            'auBecsDebit'   => 'never',
            'bancontact'    => 'never',
            'card'          => 'never',
            'cashapp'       => 'never',
            'googlePay'     => 'never',
            'ideal'         => 'never',
            'paypal'        => 'never',
            'sepaDebit'     => 'never',
            'sofort'        => 'never',
            'usBankAccount' => 'never',
        ];

        $terms = MeprHooks::apply_filters('mepr-stripe-payment-element-terms', $terms);

        if (empty($terms)) {
            return new stdClass(); // {} in JSON
        }

        return $terms;
    }

    /**
     * Returns the payment form and required fields for the gateway
     *
     * @param MeprProduct|null $product The product to get the payment fields for.
     *
     * @return string The payment form HTML
     */
    public function spc_payment_fields($product = null)
    {
        $mepr_options        = MeprOptions::fetch();
        $payment_method      = $this;
        $payment_form_action = 'mepr-stripe-payment-form';
        $user                = MeprUtils::is_user_logged_in() ? MeprUtils::get_currentuserinfo() : null;

        if ($product instanceof MeprProduct && $this->settings->stripe_checkout_enabled != 'on') {
            try {
                $coupon_code = isset($_GET['coupon']) ? sanitize_text_field(wp_unslash($_GET['coupon'])) : '';
                $cpn         = MeprCoupon::get_one_from_code($coupon_code);
                $coupon_code = $cpn instanceof MeprCoupon ? $cpn->post_title : '';

                list($txn, $sub) = MeprCheckoutCtrl::prepare_transaction(
                    $product,
                    0,
                    $user instanceof MeprUser ? $user->ID : 0,
                    $this->id,
                    $cpn,
                    false
                );

                $elements_options = $this->get_elements_options($product, $txn, $sub, $coupon_code);
            } catch (Exception $e) {
                $txn = new MeprTransaction();
            }
        } else {
            $txn = new MeprTransaction();
        }

        return MeprView::get_string('/checkout/MeprStripeGateway/payment_form', get_defined_vars());
    }

    /**
     * Display the payment form
     * This gets called on the_content and just renders the payment form
     *
     * @param float    $amount         The payment amount.
     * @param MeprUser $user           The user making the payment.
     * @param integer  $product_id     The product being purchased.
     * @param string   $transaction_id The transaction ID.
     *
     * @return void
     */
    public function display_payment_form($amount, $user, $product_id, $transaction_id)
    {
        $txn         = new MeprTransaction($transaction_id);
        $order_bumps = $this->get_order_bumps((int) $txn->product_id);

        if (count($order_bumps)) {
            echo MeprTransactionsHelper::get_invoice_order_bumps($txn, '', $order_bumps);
        } else {
            echo MeprTransactionsHelper::get_invoice($txn);
        }
        ?>
        <div class="mp_wrapper mp_payment_form_wrapper">
            <?php
                $this->display_on_site_form($txn);
            ?>
        </div>
        <?php
    }

    /**
     * Displays the on-site payment form for the given transaction.
     *
     * @param MeprTransaction $txn The transaction object.
     */
    public function display_on_site_form($txn)
    {
        $mepr_options = MeprOptions::fetch();
        $prd          = $txn->product();
        $user         = $txn->user();

        if (!$prd->is_one_time_payment()) {
            $sub = $txn->subscription();
        }

        try {
            $cpn                    = $txn->coupon();
            $coupon_code            = $cpn instanceof MeprCoupon ? $cpn->post_title : '';
            $order_bump_product_ids = isset($_GET['obs']) && is_array($_GET['obs']) ? array_map('intval', $_GET['obs']) : [];
            $order_bump_products    = MeprCheckoutCtrl::get_order_bump_products($prd->ID, $order_bump_product_ids);

            $elements_options = $this->get_elements_options(
                $prd,
                $txn,
                isset($sub) && $sub instanceof MeprSubscription ? $sub : null,
                $coupon_code,
                $order_bump_products
            );
        } catch (Exception $e) {
            // Ignore.
        }
        ?>
      <form action="" method="post" id="mepr-stripe-payment-form">
        <input type="hidden" name="mepr_process_payment_form" value="Y" />
        <input type="hidden" name="mepr_transaction_id" value="<?php echo esc_attr($txn->id); ?>" />
        <input type="hidden" name="address_required" value="<?php echo $mepr_options->show_address_fields && $mepr_options->require_address_fields ? 1 : 0 ?>" />

        <?php
        if ($user instanceof MeprUser) {
            MeprView::render('/checkout/MeprStripeGateway/payment_gateway_fields', get_defined_vars());
        }
        ?>
        <?php MeprHooks::do_action('mepr-stripe-payment-form-before-payment-element', $txn); ?>
        <?php if ($this->settings->stripe_checkout_enabled == 'on') : ?>
            <?php MeprHooks::do_action('mepr-stripe-payment-form-before-name-field', $txn); ?>
            <?php if ($this->settings->use_desc) : ?>
        <div class="mepr-stripe-gateway-description"><?php esc_html_e('Pay with your Credit Card via Stripe Checkout', 'memberpress'); ?></div>
            <?php endif; ?>
        <?php else : ?>
      <div class="mepr-stripe-elements">
        <div class="mepr-stripe-card-element" data-stripe-public-key="<?php echo esc_attr($this->get_public_key()); ?>" data-payment-method-id="<?php echo esc_attr($this->settings->id); ?>" data-locale-code="<?php echo esc_attr(self::get_locale_code()); ?>" data-elements-options="<?php echo isset($elements_options) ? esc_attr(wp_json_encode($elements_options)) : ''; ?>" data-user-email="<?php echo esc_attr($user->user_email); ?>"></div>
        <div role="alert" class="mepr-stripe-card-errors"></div>
      </div>
            <?php MeprHooks::do_action('mepr-stripe-payment-form', $txn); ?>
        <?php endif; ?>
        <?php MeprHooks::do_action('mepr_render_order_bump_hidden_fields'); ?>
      <div class="mepr_spacer">&nbsp;</div>
      <input type="submit" class="mepr-submit" value="<?php echo esc_attr(_x('Submit', 'ui', 'memberpress')); ?>" />
      <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />

      <noscript><p class="mepr_nojs"><?php esc_html_e('JavaScript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
    </form>
        <?php
    }

    /**
     * Validates the payment form before a payment is processed
     *
     * @param array $errors The errors array.
     *
     * @return array|void The errors array
     */
    public function validate_payment_form($errors)
    {
        // This is done in the javascript with Stripe.
    }

    /**
     * Displays the form for the given payment gateway on the MemberPress Options page
     */
    public function display_options_form()
    {
        $mepr_options = MeprOptions::fetch();

        $id                 = $this->id;
        $integrations       = $mepr_options->integrations;
        $whk_url            = $this->notify_url('whk');
        $update_billing_url = $this->notify_url('update-billing.html', true);

        $test_secret_key         = trim($this->settings->api_keys['test']['secret']);
        $test_public_key         = trim($this->settings->api_keys['test']['public']);
        $live_secret_key         = trim($this->settings->api_keys['live']['secret']);
        $live_public_key         = trim($this->settings->api_keys['live']['public']);
        $force_ssl               = ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true);
        $debug                   = ($this->settings->debug == 'on' or $this->settings->debug == true);
        $test_mode               = $this->is_test_mode();
        $connect_status          = trim($this->settings->connect_status);
        $service_account_id      = trim($this->settings->service_account_id);
        $service_account_name    = stripslashes(trim($this->settings->service_account_name));
        $churn_buster_enabled    = ($this->settings->churn_buster_enabled == 'on' or $this->settings->churn_buster_enabled == true);
        $stripe_checkout_enabled = $this->settings->stripe_checkout_enabled == 'on';
        $payment_methods         = $this->get_available_payment_methods();
        $enabled_payment_methods = is_array($this->settings->payment_methods) ? $this->settings->payment_methods : [];

        $churn_buster_uuid = trim($this->settings->churn_buster_uuid);

        $test_secret_key_str         = "{$mepr_options->integrations_str}[{$this->id}][api_keys][test][secret]";
        $test_public_key_str         = "{$mepr_options->integrations_str}[{$this->id}][api_keys][test][public]";
        $live_secret_key_str         = "{$mepr_options->integrations_str}[{$this->id}][api_keys][live][secret]";
        $live_public_key_str         = "{$mepr_options->integrations_str}[{$this->id}][api_keys][live][public]";
        $force_ssl_str               = "{$mepr_options->integrations_str}[{$this->id}][force_ssl]";
        $debug_str                   = "{$mepr_options->integrations_str}[{$this->id}][debug]";
        $test_mode_str               = "{$mepr_options->integrations_str}[{$this->id}][test_mode]";
        $connect_status_string       = "{$mepr_options->integrations_str}[{$this->id}][connect_status]";
        $service_account_id_string   = "{$mepr_options->integrations_str}[{$this->id}][service_account_id]";
        $service_account_name_string = "{$mepr_options->integrations_str}[{$this->id}][service_account_name]";
        $churn_buster_enabled_str    = "{$mepr_options->integrations_str}[{$this->id}][churn_buster_enabled]";
        $stripe_checkout_enabled_str = "{$mepr_options->integrations_str}[{$this->id}][stripe_checkout_enabled]";
        $churn_buster_uuid_str       = "{$mepr_options->integrations_str}[{$this->id}][churn_buster_uuid]";
        $payment_methods_str         = "{$mepr_options->integrations_str}[{$this->id}][payment_methods]";

        $account_email = get_option('mepr_authenticator_account_email');
        $secret        = get_option('mepr_authenticator_secret_token');
        $site_uuid     = get_option('mepr_authenticator_site_uuid');

        // $stripe_connect_url = 'https://connect.stripe.com/express/oauth/authorize?response_type=code&amp;client_id=ca_32D88BD1qLklliziD7gYQvctJIhWBSQ7&amp;scope=read_write';
        // If we're logged in then let's present a stripe url otherwise an authenticator url
        if ($account_email && $secret && $site_uuid) {
            $stripe_connect_url = self::get_stripe_connect_url($this->id);
        } else {
            $stripe_connect_url = MeprAuthenticatorCtrl::get_auth_connect_url(true, $this->id);
        }

        if (! defined('MEPR_DISABLE_STRIPE_CONNECT')) {
            MeprView::render('/admin/gateways/stripe/connect-migrate-prompt', get_defined_vars());
        }
        MeprView::render('/admin/gateways/stripe/keys', get_defined_vars());
        MeprView::render('/admin/gateways/stripe/checkboxes', get_defined_vars());
    }

    /**
     * Validates the form for the given payment gateway on the MemberPress Options page
     *
     * @param array $errors The errors array.
     *
     * @return array The errors array
     */
    public function validate_options_form($errors)
    {
        $mepr_options = MeprOptions::fetch();

        $testmode    = isset($_REQUEST[$mepr_options->integrations_str][$this->id]['test_mode']);
        $testmodestr = $testmode ? 'test' : 'live';

        // Bail if connecting to a Stripe Connect account, since the keys won't be set at this time
        // Also, bail if the payment method is not currently connected.
        if (isset($_REQUEST['stripe_connect_account_number']) || self::stripe_connect_status($this->id) == 'not-connected') {
            return $errors;
        }

        if (
            !isset($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['secret']) or
            empty($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['secret']) or
            !isset($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['public']) or
            empty($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['public'])
        ) {
            $errors[] = __('All Stripe keys must be filled in.', 'memberpress');
        }

        return $errors;
    }

    /**
     * This gets called on wp_enqueue_script and enqueues a set of
     * scripts for use on the front end user account page.
     */
    public function enqueue_user_account_scripts()
    {
        $sub = (isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['sub'])) ? new MeprSubscription((int)$_GET['sub']) : false;
        if ($sub !== false && $sub->gateway == $this->id) {
            $mepr_options = MeprOptions::fetch();

            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], MEPR_VERSION . time());
            wp_enqueue_script('stripe-account-create-token', MEPR_GATEWAYS_URL . '/stripe/account_create_token.js', ['jquery', 'stripe-js'], MEPR_VERSION);

            $return_url = add_query_arg([
                'action'          => 'mepr_stripe_update_payment_method',
                'subscription_id' => $sub->id,
                'nonce'           => wp_create_nonce('mepr_process_update_account_form'),
            ], admin_url('admin-ajax.php'));

            wp_localize_script('stripe-account-create-token', 'MeprStripeAccountForm', [
                'api_version'           => self::STRIPE_API_VERSION,
                'public_key'            => $this->get_public_key(),
                'currency'              => strtolower($mepr_options->currency_code),
                'payment_method_types'  => $this->get_update_setup_intent_payment_method_types(),
                'elements_appearance'   => $this->get_elements_appearance(),
                'payment_element_terms' => $this->get_payment_element_terms(),
                'ajax_url'              => admin_url('admin-ajax.php'),
                'return_url'            => $return_url,
            ]);
        }
    }

    /**
     * Displays the update account form on the subscription account page
     *
     * @param integer $sub_id  The subscription ID.
     * @param array   $errors  The errors array.
     * @param string  $message The message to display.
     *
     * @return void
     */
    public function display_update_account_form($sub_id, $errors = [], $message = '')
    {
        $mepr_options         = MeprOptions::fetch();
        $sub                  = new MeprSubscription($sub_id);
        $user                 = $sub->user();
        $subscribed_with_link = false;

        if (empty($message) && isset($_GET['message'])) {
            $message = sanitize_text_field(wp_unslash($_GET['message']));
        }

        try {
            if (strpos($sub->subscr_id, 'sub_') === 0) {
                $subscription         = $this->retrieve_subscription($sub->subscr_id);
                $subscribed_with_link = isset($subscription->default_payment_method['type']) && $subscription->default_payment_method['type'] == 'link';
            }
        } catch (Exception $e) {
            // Ignore.
        }
        ?>
      <div class="mp_wrapper">
        <form action="" method="post" id="mepr-stripe-payment-form" data-sub-id="<?php echo esc_attr($sub->id); ?>">
          <input type="hidden" name="_mepr_nonce" value="<?php echo wp_create_nonce('mepr_process_update_account_form'); ?>" />
          <input type="hidden" name="address_required" value="<?php echo $mepr_options->show_address_fields && $mepr_options->require_address_fields ? 1 : 0 ?>" />
          <?php MeprView::render('/shared/errors', get_defined_vars()); ?>
          <?php
            if ($user instanceof MeprUser) {
                MeprView::render('/checkout/MeprStripeGateway/payment_gateway_fields', get_defined_vars());
            }
            ?>
          <div class="mepr_update_account_table">
            <?php if ($subscribed_with_link) : ?>
              <div><strong><?php _e('Link is set up as the payment method for this subscription. You can change the default payment method by logging in to <a href="https://link.co">Link.co</a>.', 'memberpress'); ?></strong></div>
              <div><strong><?php esc_html_e('Or', 'memberpress'); ?></strong></div>
              <div><strong><?php esc_html_e('If you do not wish to use Link for this subscription, you can enter the details for a different payment method below.', 'memberpress'); ?></strong></div><br/>
            <?php else : ?>
              <div><strong><?php esc_html_e('Update your payment information below', 'memberpress'); ?></strong></div><br/>
            <?php endif; ?>
            <div class="mepr-stripe-elements">
              <div id="card-element" class="mepr-stripe-card-element" data-locale-code="<?php echo esc_attr(self::get_locale_code()); ?>" data-user-email="<?php echo $user instanceof MeprUser ? esc_attr($user->user_email) : ''; ?>"></div>
              <div id="card-errors" role="alert" class="mepr-stripe-card-errors"></div>
            </div>
            <div class="mepr_spacer">&nbsp;</div>
            <input type="submit" class="mepr-submit" value="<?php echo esc_attr(_x('Submit', 'ui', 'memberpress')); ?>" />
            <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />

            <noscript><p class="mepr_nojs"><?php esc_html_e('JavaScript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
          </div>
        </form>
      </div>
        <?php
    }

    /**
     * Validates the payment form before a payment is processed
     *
     * @param array $errors The errors array.
     *
     * @return array The errors array
     */
    public function validate_update_account_form($errors = [])
    {
        return $errors;
    }

    /**
     * Used to update the credit card information on a subscription by the given gateway.
     * This method should be used by the class to record a successful cancellation from
     * the gateway. This method should also be used by any IPN requests or Silent Posts.
     *
     * @param integer $sub_id The subscription ID.
     *
     * @return void
     */
    public function process_update_account_form($sub_id)
    {
        // Not used.
    }

    /**
     * No longer in use, but overridden templates might still call this
     *
     * @return false
     */
    public function is_stripe_link_enabled()
    {
        return false;
    }

    /**
     * Get available payment methods for the current currency
     *
     * @return array List of available payment methods
     */
    public function get_available_payment_methods()
    {
        $mepr_options        = MeprOptions::fetch();
        $all_payment_methods = require MEPR_DATA_PATH . '/stripe_payment_methods.php';
        $payment_methods     = [];

        foreach ($all_payment_methods as $payment_method) {
            if (is_string($payment_method['currencies']) && $payment_method['currencies'] == 'all') {
                $payment_methods[] = $payment_method;
            } elseif (is_array($payment_method['currencies']) && in_array(strtoupper($mepr_options->currency_code), $payment_method['currencies'], true)) {
                $payment_methods[] = $payment_method;
            }
        }

        return $payment_methods;
    }

    /**
     * Whether we should be sending in test mode or not
     *
     * @return boolean
     */
    public function is_test_mode()
    {
        if (defined('MEMBERPRESS_STRIPE_TESTING') && MEMBERPRESS_STRIPE_TESTING) {
            $test_mode = true;
        } else {
            $test_mode = isset($this->settings->test_mode) && $this->settings->test_mode;
        }

        return apply_filters('mepr_stripe_is_test_mode', $test_mode, $this);
    }

    /**
     * Get the Stripe publishable key
     *
     * @return string
     */
    public function get_public_key()
    {
        if ($this->is_test_mode()) {
            return trim($this->settings->api_keys['test']['public']);
        }

        return trim($this->settings->api_keys['live']['public']);
    }

    /**
     * Get the Stripe secret key
     *
     * @return string
     */
    public function get_secret_key()
    {
        if ($this->is_test_mode()) {
            return trim($this->settings->api_keys['test']['secret']);
        }

        return trim($this->settings->api_keys['live']['secret']);
    }

    /**
     * Force SSL for payment processing
     *
     * @return boolean True if SSL should be forced
     */
    public function force_ssl()
    {
        return (isset($this->settings->force_ssl) and ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true));
    }

    /**
     * Get the renewal base date for a given subscription. This is the date MemberPress will use to calculate expiration dates.
     * Of course this method is meant to be overridden when a gateway requires it.
     *
     * @param MeprSubscription $sub The MemberPress subscription.
     *
     * @return string The renewal base date
     */
    public function get_renewal_base_date(MeprSubscription $sub)
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $q = $wpdb->prepare(
            "
        SELECT e.created_at, e.args
          FROM {$mepr_db->events} AS e
         WHERE e.event='subscription-resumed'
           AND e.evt_id_type='subscriptions'
           AND e.evt_id=%d
         ORDER BY e.created_at DESC
         LIMIT 1
      ",
            $sub->id
        );

        $event = $wpdb->get_row($q);

        if (!empty($event)) {
            $renewal_base_date = $event->created_at;

            // If the subscription was still active when it was resumed, a trial period is added. We need to account
            // for this trial period to get the correct renewal date.
            if (!empty($event->args)) {
                $args = json_decode($event->args, true);

                if (is_array($args) && isset($args['days_until_renewal']) && is_numeric($args['days_until_renewal'])) {
                    $date = date_create($event->created_at, new DateTimeZone('UTC'));

                    if ($date instanceof DateTime) {
                        $date->modify("+{$args['days_until_renewal']} days");
                        $renewal_base_date = $date->format('Y-m-d H:i:s');
                    }
                }
            }

            return $renewal_base_date;
        }

        return $sub->created_at;
    }

    /**
     * Process an incoming webhook from the Stripe Connect service
     *
     * @return void
     */
    public function service_listener()
    {

        $mepr_options = MeprOptions::fetch();

        // Retrieve the request's body and parse it as JSON.
        $body = @file_get_contents('php://input');

        MeprUtils::debug_log('********* WEBHOOK CONTENTS: ' . $body);
        $header_signature = MeprUtils::get_http_header('Signature');

        if (empty($header_signature)) {
            MeprUtils::debug_log('*** Exiting with no signature');
            MeprUtils::exit_with_status(403, __('No Webhook Signature', 'memberpress'));
        }

        $secret    = get_option('mepr_authenticator_secret_token');
        $signature = hash_hmac('sha256', $body, $secret);

        MeprUtils::debug_log('********* WEBHOOK SECRETS -- SERVICE: [' . $header_signature . '] LOCAL: [' . $signature . ']');

        if ($header_signature != $signature) {
            MeprUtils::debug_log('*** Exiting with incorrect signature');
            MeprUtils::exit_with_status(403, __('Incorrect Webhook Signature', 'memberpress'));
        }

        $body = json_decode($body, true);

        if (!isset($body['event']) || empty($body['event'])) {
            MeprUtils::exit_with_status(403, __('No `event` set', 'memberpress'));
        }

        $event = sanitize_text_field($body['event']);

        $auth_site_uuid = get_option('mepr_authenticator_site_uuid');

        if ($event == 'update-credentials') {
            $site_uuid = sanitize_text_field($body['data']['site_uuid']);
            if ($auth_site_uuid != $site_uuid) {
                MeprUtils::exit_with_status(404, __('Request was sent to the wrong site?', 'memberpress'));
            }

            $method_id = sanitize_text_field($body['data']['payment_method']);
            $pm        = $mepr_options->payment_method($method_id);
            if (empty($pm)) {
                MeprUtils::exit_with_status(404, __('No payment method like that exists on this site', 'memberpress'));
            }

            $pm->update_connect_credentials();

            MeprUtils::debug_log("*** MeprStripeGateway::service_listener stored payment methods [{$method_id}]: " . print_r($mepr_options->integrations[$method_id]['api_keys']['test']['secret'], true));

            wp_send_json(['credentials' => 'saved']);
        }

        MeprUtils::exit_with_status(404, __('Webhook not supported', 'memberpress'));
    }

    /**
     * STRIPE SPECIFIC METHODS
     **/
    public function listener()
    {
        // Retrieve the request's body and parse it as JSON.
        $body       = @file_get_contents('php://input');
        $event_json = (object)json_decode($body, true);

        if (!isset($event_json->id)) {
            return;
        }

        if (apply_filters('mepr_stripe_webhook_livemode_detection', false) && isset($event_json->livemode) && is_bool($event_json->livemode)) {
            if ($event_json->livemode) {
                add_filter('mepr_stripe_is_test_mode', '__return_false');
            } else {
                add_filter('mepr_stripe_is_test_mode', '__return_true');
            }
        }

        // Use the id to pull the event directly from the API (purely a security measure).
        try {
            $event = (object)$this->send_stripe_request("events/{$event_json->id}", [], 'get');
        } catch (Exception $e) {
            http_response_code(202); // Throw a 202 here so stripe doesn't send out a billion webhook broken emails.
            die($e->getMessage()); // Do nothing.
        }

        try {
            $_REQUEST['data'] = $obj = (object) $event->data['object'];

            if ($event->type == 'charge.succeeded') {
                // For one time payment with stripe checkout session.
            } elseif ($event->type == 'charge.failed') {
                $this->record_payment_failure();
            } elseif ($event->type == 'charge.refunded') {
                $this->record_refund();
            } elseif ($event->type == 'charge.disputed') {
                // Not worried about this right now.
            } elseif ($event->type == 'customer.subscription.created') {
                // $this->record_create_subscription(); // done on page
            } elseif ($event->type == 'checkout.session.completed' || $event->type == 'checkout.session.async_payment_succeeded') {
                $this->process_stripe_checkout_session_completed($obj);
            } elseif ($event->type == 'checkout.session.async_payment_failed') {
                $this->process_checkout_session_async_payment_failed($obj);
            } elseif ($event->type == 'customer.subscription.updated') {
                // $this->record_update_subscription(); // done on page
            } elseif ($event->type == 'customer.subscription.deleted') {
                $this->record_cancel_subscription();
            } elseif ($event->type == 'customer.subscription.trial_will_end') {
                // We may want to implement this feature at some point.
            } elseif ($event->type == 'invoice.payment_succeeded') {
                $this->handle_invoice_payment_succeeded_webhook($obj);
            } elseif ($event->type == 'invoice.payment_failed') {
                $this->handle_invoice_payment_failed_webhook($obj);
            } elseif ($event->type == 'customer.deleted') {
                MeprUser::delete_stripe_customer_id($this->get_meta_gateway_id(), $obj->id);
            } elseif ($event->type == 'product.deleted') {
                MeprProduct::delete_stripe_product_id($this->get_meta_gateway_id(), $obj->id);
            } elseif ($event->type == 'payment_intent.succeeded') {
                $this->handle_payment_intent_succeeded_webhook($obj);
            } elseif ($event->type == 'setup_intent.succeeded') {
                $this->handle_setup_intent_succeeded_webhook($obj);
            } elseif ($event->type == 'plan.deleted') {
                MeprProduct::delete_stripe_plan_id($this->get_meta_gateway_id(), $obj->id);
            } elseif ($event->type == 'coupon.deleted') {
                MeprCoupon::delete_stripe_coupon_id($this->get_meta_gateway_id(), $obj->id);
            } elseif ($event->type == 'payment_intent.payment_failed') {
                $this->handle_payment_intent_payment_failed_webhook($obj);
            } elseif ($event->type == 'setup_intent.setup_failed') {
                $this->handle_setup_intent_setup_failed_webhook($obj);
            } elseif ($event->type == 'payment_method.automatically_updated') {
                if (isset($event->data['previous_attributes'])) {
                    $this->handle_payment_method_automatically_updated_event(
                        $obj,
                        (object) $event->data['previous_attributes']
                    );
                }
            }
        } catch (Exception $e) {
            http_response_code(500);
            die($e->getMessage());
        }
    }

    /**
     * Renders a custom page where a credit card can be updated
     */
    public function churn_buster()
    {
        $churn_buster_enabled = ($this->settings->churn_buster_enabled == 'on' || $this->settings->churn_buster_enabled == true);
        $uuid                 = trim($this->settings->churn_buster_uuid);

        if ($churn_buster_enabled && !empty($uuid)) {
            $mepr_options = MeprOptions::fetch();
            $company      = $mepr_options->attr('biz_name');

            if (empty($company)) {
                $company = MeprUtils::blogname();
            }

            MeprView::render('account/churn_buster', compact('uuid', 'company'));
        } else {
            MeprUtils::exit_with_status(404);
        }
    }

    /**
     * The return handler activates the grace period for subscriptions whose PaymentIntent has succeeded or is still processing
     */
    public function return_handler()
    {
        $txn_id = isset($_GET['txn_id']) ? (int) sanitize_text_field(wp_unslash($_GET['txn_id'])) : 0;
        $txn    = new MeprTransaction($txn_id);

        if ($txn->id > 0) {
            try {
                if ($txn->is_one_time_payment()) {
                    $payment_intent_id = isset($_GET['payment_intent']) ? sanitize_text_field(wp_unslash($_GET['payment_intent'])) : '';

                    if (!empty($payment_intent_id)) {
                        $payment_intent = (object) $this->send_stripe_request('payment_intents/' . $payment_intent_id, [], 'get');

                        if (isset($payment_intent->status) && $payment_intent->status == 'requires_payment_method') {
                            $product     = $txn->product();
                            $product_url = MeprUtils::get_permalink($product->ID);
                            $product_url = !empty($product_url) ? $product_url : home_url();

                            MeprUtils::wp_redirect(esc_url_raw(add_query_arg([
                                'errors' => __('Payment was unsuccessful, please check your payment details and try again.', 'memberpress'),
                            ], $product_url)));
                        }
                    }
                } else {
                    $sub = $txn->subscription();

                    if ($sub instanceof MeprSubscription && $sub->status != MeprSubscription::$active_str && $sub->gateway == $this->id) {
                        $payment_intent_id = isset($_GET['payment_intent']) ? sanitize_text_field(wp_unslash($_GET['payment_intent'])) : '';

                        if (!empty($payment_intent_id)) {
                                $payment_intent = (object) $this->send_stripe_request('payment_intents/' . $payment_intent_id, ['expand' => ['invoice']], 'get');

                            if (isset($payment_intent->status) && in_array($payment_intent->status, ['succeeded', 'processing'], true)) {
                                if (isset($payment_intent->created) && $payment_intent->created > (time() - MeprUtils::hours(24))) {
                                    if (isset($payment_intent->invoice['subscription']) && $sub->subscr_id == $payment_intent->invoice['subscription']) {
                                        $this->activate_subscription($txn, $sub, false);
                                    }
                                }
                            }
                        }

                        if (strpos($txn->trans_num, 'cs_') === 0) {
                            $checkout_session = (object) $this->send_stripe_request('checkout/sessions/' . $txn->trans_num, [], 'get');

                            if (isset($checkout_session->created) && $checkout_session->created > (time() - MeprUtils::hours(24))) {
                                $this->activate_subscription($txn, $sub, false);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Ignore.
            }
        }

        $redirect_to = isset($_GET['redirect_to']) ? sanitize_url(wp_unslash($_GET['redirect_to'])) : home_url();

        MeprUtils::wp_redirect($redirect_to);
    }

    /**
     * Fetches the latest invoice object.
     *
     * @param  MeprSubscription $sub The MemberPress subscription.
     * @throws MeprGatewayException If the customer could not be found.
     * @return object The latest invoice object
     */
    public function get_latest_invoice(MeprSubscription $sub)
    {
        if (strpos($sub->subscr_id, 'sub_') === 0) {
            $endpoint = "subscriptions/{$sub->subscr_id}";
        } else {
            try {
                $customer = $this->retrieve_customer($sub->subscr_id);
            } catch (Exception $e) {
                throw new MeprGatewayException(__('Customer not found', 'memberpress'));
            }

            $endpoint = "customers/{$customer->id}/subscription";
        }

        $args = MeprHooks::apply_filters('mepr_stripe_get_latest_invoice_args', [
            'expand' => [
                'latest_invoice',
            ],
        ], $sub);

        $subscr = (object) $this->send_stripe_request($endpoint, $args, 'get');

        return (object) $subscr->latest_invoice;
    }

    /**
     * Sends an email to the customer with a link to confirm the payment for a subscription invoice
     *
     * @param  MeprSubscription $sub The MemberPress subscription.
     * @throws MeprGatewayException If the customer could not be found or the invoice does not require action.
     * @uses   MeprStripeGateway::get_latest_invoice() to retrieve the latest invoice data
     */
    public function send_latest_invoice_payment_email(MeprSubscription $sub)
    {
        $user = $sub->user();

        if (!$user->ID) {
            throw new MeprGatewayException(__('User not found', 'memberpress'));
        }

        $invoice = $this->get_latest_invoice($sub);

        if ($invoice->status == 'open') {
            // Translators: In this string, %s is the Blog Name/Title.
            $subject = sprintf(__('[%s] Confirm Subscription Payment', 'memberpress'), MeprUtils::blogname());

            $body = MeprView::get_string('/emails/user_confirm_payment', [
                'blogname'           => MeprUtils::blogname(),
                'subscr_num'         => $sub->subscr_id,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
            ]);

            $message = MeprView::get_string('/emails/template', compact('body'));

            MeprUtils::wp_mail($user->formatted_email(), $subject, $message, ['Content-Type: text/html']);
        } else {
            throw new MeprGatewayException(__('The invoice does not require action from the customer.', 'memberpress'));
        }
    }

    /**
     * Sanitize the statement descriptor
     *
     * Removes invalid chars and limits the length.
     *
     * @param  string $statement_descriptor The statement descriptor to sanitize.
     * @return string
     */
    private function sanitize_statement_descriptor($statement_descriptor)
    {
        if (!is_string($statement_descriptor)) {
            return '';
        }

        $statement_descriptor = preg_replace('/[^a-zA-Z0-9.\-_ ]/', '', $statement_descriptor);
        $statement_descriptor = trim(substr($statement_descriptor, 0, 22));

        return $statement_descriptor;
    }

    /**
     * Send request to Stripe API
     *
     * @param  string       $endpoint        The API endpoint.
     * @param  array        $args            Request arguments.
     * @param  string       $method          HTTP method (post, get, etc).
     * @param  string       $domain          API domain.
     * @param  boolean      $blocking        Whether to wait for response.
     * @param  string|false $idempotency_key Idempotency key for the request.
     * @throws MeprHttpException When HTTP request fails.
     * @throws MeprRemoteException When API returns error.
     * @return array|object Response from Stripe API
     */
    public function send_stripe_request(
        $endpoint,
        $args = [],
        $method = 'post',
        $domain = 'https://api.stripe.com/v1/',
        $blocking = true,
        $idempotency_key = false
    ) {
        $mepr_options = MeprOptions::fetch();
        $uri          = "{$domain}{$endpoint}";

        $args = MeprHooks::apply_filters('mepr_stripe_request_args', $args);

        $arg_array = [
            'method'    => strtoupper($method),
            'body'      => $args,
            'timeout'   => 15,
            'blocking'  => $blocking,
            'sslverify' => $mepr_options->sslverify,
            'headers'   => $this->get_headers(),
        ];

        if (false !== $idempotency_key) {
            $arg_array['headers']['Idempotency-Key'] = $idempotency_key;
        }

        $arg_array = MeprHooks::apply_filters('mepr_stripe_request', $arg_array);

        $uid = uniqid();
        // $this->email_status("###{$uid} Stripe Call to {$uri} API Key: {$this->settings->secret_key}\n" . MeprUtils::object_to_string($arg_array, true) . "\n", $this->settings->debug);
        $resp = wp_remote_request($uri, $arg_array);

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
            $json_res = json_decode($resp['body'], true);
            if (null !== $json_res) {
                // $this->email_status("###{$uid} Stripe Response from {$uri}\n" . MeprUtils::object_to_string($json_res, true) . "\n", $this->settings->debug);
                if (isset($json_res['error'])) {
                    throw new MeprRemoteException("{$json_res['error']['message']} ({$json_res['error']['type']})");
                } else {
                    return $json_res;
                }
            } else { // Un-decipherable message.
                throw new MeprRemoteException(sprintf(__('There was an issue with the credit card processor. Try again later.', 'memberpress'), $this->name));
            }
        }

        return false;
    }

    /**
     * Get card information from a payment method.
     *
     * @param stdClass $payment_method The Stripe payment method object.
     *
     * @return array|null The card data array, or null a non-card payment method was used.
     */
    public function get_card($payment_method)
    {
        if ($payment_method && isset($payment_method->card) && is_array($payment_method->card)) {
            return [
                'last4'     => $payment_method->card['last4'],
                'exp_month' => $payment_method->card['exp_month'],
                'exp_year'  => $payment_method->card['exp_year'],
            ];
        }

        return null;
    }

    /**
     * Generates the user agent we use to pass to API request so
     * Stripe can identify our application.
     */
    public function get_user_agent()
    {
        $app_info = [
            'name'       => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
            'version'    => MEPR_VERSION,
            'url'        => 'https://memberpress.com',
            'partner_id' => 'pp_partner_EbxCWHE5ve1yUk',
        ];

        return [
            'lang'         => 'php',
            'lang_version' => phpversion(),
            'publisher'    => 'memberpress',
            'uname'        => (function_exists('php_uname')) ? php_uname() : '',
            'application'  => $app_info,
        ];
    }

    /**
     * Generates the headers to pass to API request.
     *
     * @return array
     */
    public function get_headers()
    {
        $user_agent = $this->get_user_agent();
        $app_info   = $user_agent['application'];

        return apply_filters(
            'mepr_stripe_request_headers',
            [
                'Authorization'              => 'Basic ' . base64_encode("{$this->get_secret_key()}:"),
                'Stripe-Version'             => self::STRIPE_API_VERSION,
                'User-Agent'                 => $app_info['name'] . '/' . $app_info['version'] . ' (' . $app_info['url'] . ')',
                'X-Stripe-Client-User-Agent' => json_encode($user_agent),
            ]
        );
    }

    /**
     * Assembles the URL for redirecting to Stripe Connect
     *
     * @param  string  $method_id  The payment method ID.
     * @param  boolean $onboarding True if we are onboarding.
     * @return string
     */
    public static function get_stripe_connect_url($method_id, $onboarding = false)
    {

        $args = [
            'action'   => 'mepr_stripe_connect_update_creds',
            '_wpnonce' => wp_create_nonce('stripe-update-creds'),
        ];

        if ($onboarding) {
            $args['onboarding'] = 'true';
        }

        $base_return_url = add_query_arg($args, admin_url('admin-ajax.php'));

        $error_url = add_query_arg([
            'mepr-action' => 'error',
        ], $base_return_url);

        $site_uuid = get_option('mepr_authenticator_site_uuid');

        if (empty($site_uuid)) {
            return false;
        }

        $mepr_options = MeprOptions::fetch();
        $pm           = new MeprStripeGateway();
        $pm->load(['id' => $method_id]);

        $payload = [
            'method_id'           => $pm->id,
            'site_uuid'           => $site_uuid,
            'user_uuid'           => get_option('mepr_authenticator_user_uuid'),
            'return_url'          => $base_return_url,
            'error_url'           => $error_url,
            'webhook_url'         => $pm->notify_url('whk'),
            'service_webhook_url' => $pm->notify_url('stripe-service-whk'),
            'mp_version'          => MEPR_VERSION,
        ];

        $jwt = MeprAuthenticatorCtrl::generate_jwt($payload);
        return MEPR_STRIPE_SERVICE_URL  . "/connect/{$site_uuid}/{$method_id}/{$jwt}";
    }

    /**
     * Get the Stripe Connect button URL
     *
     * @param  string $method_id The payment method ID.
     * @return void
     */
    public static function get_stripe_connect_button_url($method_id)
    {
        MeprAuthenticatorCtrl::get_auth_connect_url(true, $id);
    }

    /**
     * Check if API keys are set for a payment method
     *
     * @param  string $method_id Payment method ID.
     * @return boolean True if keys are set
     */
    public static function keys_are_set($method_id)
    {
        $mepr_options = MeprOptions::fetch();

        if (!isset($mepr_options->integrations[$method_id])) {
            return false;
        }

        $integ = $mepr_options->integrations[$method_id];

        return (
        ( isset($integ['api_keys']['test']['public']) && !empty($integ['api_keys']['test']['public']) ) ||
        ( isset($integ['api_keys']['test']['secret']) && !empty($integ['api_keys']['test']['secret']) ) ||
        ( isset($integ['api_keys']['live']['public']) && !empty($integ['api_keys']['live']['public']) ) ||
        ( isset($integ['api_keys']['live']['secret']) && !empty($integ['api_keys']['live']['secret']) )
        );
    }

    /**
     * Check if payment method uses Stripe Connect
     *
     * @param  string $method_id Payment method ID.
     * @return boolean True if using Stripe Connect
     */
    public static function is_stripe_connect($method_id)
    {
        $connect_status = self::stripe_connect_status($method_id);
        return ($connect_status !== 'not-connected');
    }

    /**
     * Get Stripe Connect status for payment method
     *
     * @param  string $method_id Payment method ID.
     * @return string Connect status ('connected', 'not-connected', etc)
     */
    public static function stripe_connect_status($method_id)
    {
        $mepr_options = MeprOptions::fetch();

        if (!isset($mepr_options->integrations[$method_id])) {
            return 'not-connected';
        }

        $integ = $mepr_options->integrations[$method_id];

        return ( !isset($integ['connect_status']) || empty($integ['connect_status']) ) ? 'not-connected' : $integ['connect_status'];
    }

    /**
     * Checks whether the user has a Stripe payment method that uses Stripe Connect
     *
     * @param string  $status    The connect status to check for.
     * @param boolean $return_id Whether to return the payment method ID or just true.
     *
     * @return string|boolean The payment method ID if $return_id is true, otherwise true or false.
     */
    public static function has_method_with_connect_status($status = 'connected', $return_id = false)
    {
        $mepr_options = MeprOptions::fetch();
        foreach ($mepr_options->integrations as $integration) {
            // Only check Stripe payment methods.
            if ('MeprStripeGateway' !== $integration['gateway']) {
                continue;
            }

            if ($status == self::stripe_connect_status($integration['id'])) {
                return $return_id ? $integration['id'] : true;
            }
        }

        return false;
    }

    /**
     * Fetches the credentials from the Stripe Connect service and updates them in the payment method.
     */
    public function update_connect_credentials()
    {
        $mepr_options = MeprOptions::fetch();

        $site_uuid = get_option('mepr_authenticator_site_uuid');

        $payload = [
            'site_uuid' => $site_uuid,
        ];

        $jwt = MeprAuthenticatorCtrl::generate_jwt($payload);

        // Make sure the request came from the Connect service.
        $response = wp_remote_get(MEPR_STRIPE_SERVICE_URL . "/api/credentials/{$this->id}", [
            'headers' => MeprUtils::jwt_header($jwt, MEPR_STRIPE_SERVICE_DOMAIN),
        ]);

        $creds = json_decode(wp_remote_retrieve_body($response), true);

        MeprUtils::debug_log("*** MeprStripeGateway::update_connect_credentials updating creds for method_id = [{$this->id}]");

        // Store the credentials.
        foreach ($mepr_options->integrations as $method_id => $integ) {
            // Update ALL of the payment methods connected to this account.
            if (
                $method_id == $this->id || (
                isset($mepr_options->integrations[$method_id]['service_account_id']) &&
                $mepr_options->integrations[$method_id]['service_account_id'] == $creds['service_account_id'] )
            ) {
                MeprUtils::debug_log('*** MeprStripeGateway::update_connect_credentials updating payment method with this data: ' . print_r($creds, true));

                $integ['api_keys']['test']['public'] = sanitize_text_field($creds['test_publishable_key']);
                $integ['api_keys']['test']['secret'] = sanitize_text_field($creds['test_secret_key']);
                $integ['api_keys']['live']['public'] = sanitize_text_field($creds['live_publishable_key']);
                $integ['api_keys']['live']['secret'] = sanitize_text_field($creds['live_secret_key']);
                $integ['service_account_id']         = sanitize_text_field($creds['service_account_id']);
                $integ['service_account_name']       = sanitize_text_field($creds['service_account_name']);
                $integ['connect_status']             = 'connected';

                $mepr_options->integrations[$method_id] = $integ;
            }
        }

        return $mepr_options->store(false);
    }

    /**
     * Is this payment method connected to Stripe?
     *
     * @return boolean
     */
    public function is_connected()
    {
        if (!empty($this->settings->connect_status) && $this->settings->connect_status != 'not-connected') {
            return true;
        }

        return (
        !empty($this->settings->api_keys['test']['public']) ||
        !empty($this->settings->api_keys['test']['secret']) ||
        !empty($this->settings->api_keys['live']['public']) ||
        !empty($this->settings->api_keys['live']['secret'])
        );
    }

    /**
     * Get the Stripe Customer ID
     *
     * If the Stripe Customer does not exist for the given user, one will be created.
     *
     * @param  MeprUser $usr The MemberPress user.
     * @return string                   The Stripe Customer ID
     * @throws MeprHttpException        If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException      If there was an invalid or error response from Stripe.
     */
    public function get_customer_id(MeprUser $usr)
    {
        $customer_id = $usr->get_stripe_customer_id($this->get_meta_gateway_id());

        if (!is_string($customer_id) || strpos($customer_id, 'cus_') !== 0) {
            $customer = $this->create_customer($usr);
            $usr->set_stripe_customer_id($this->get_meta_gateway_id(), $customer->id);
            $customer_id = $customer->id;
        }

        return $customer_id;
    }

    /**
     * Create a Stripe Customer
     *
     * @param  MeprUser $usr The MemberPress user.
     * @return stdClass                 The Stripe Customer data
     * @throws MeprHttpException        If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException      If there was an invalid or error response from Stripe.
     */
    public function create_customer(MeprUser $usr)
    {
        $args = MeprHooks::apply_filters('mepr_stripe_create_customer_args', $this->get_customer_args($usr), $usr);

        $customer = (object) $this->send_stripe_request('customers', $args, 'post');

        return $customer;
    }

    /**
     * Update the Stripe Customer with the latest user data
     *
     * @param  string   $customer_id The Stripe Customer ID.
     * @param  MeprUser $usr         The MemberPress User.
     * @throws MeprHttpException                If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException              If there was an invalid or error response from Stripe.
     */
    public function update_customer($customer_id, MeprUser $usr)
    {
        $args = MeprHooks::apply_filters('mepr_stripe_update_customer_args', $this->get_customer_args($usr), $usr);

        $this->send_stripe_request("customers/$customer_id", $args, 'post');
    }

    /**
     * Get the args for creating or updating a Stripe customer
     *
     * @param  MeprUser $usr The MemberPress user.
     * @return array
     */
    private function get_customer_args(MeprUser $usr)
    {
        $mepr_options = MeprOptions::fetch();

        $args = [
            'email' => $usr->user_email,
        ];

        $full_name = $usr->get_full_name();
        if ($full_name) {
            $args['name'] = $full_name;
        }

        if (MeprHooks::apply_filters('mepr_stripe_populate_customer_address', $mepr_options->show_address_fields)) {
            $address = [
                'line1'       => get_user_meta($usr->ID, 'mepr-address-one', true),
                'line2'       => get_user_meta($usr->ID, 'mepr-address-two', true),
                'city'        => get_user_meta($usr->ID, 'mepr-address-city', true),
                'state'       => get_user_meta($usr->ID, 'mepr-address-state', true),
                'country'     => get_user_meta($usr->ID, 'mepr-address-country', true),
                'postal_code' => get_user_meta($usr->ID, 'mepr-address-zip', true),
            ];

            foreach ($address as $key => $value) {
                if (empty($value) || !is_string($value)) {
                    unset($address[$key]);
                }
            }

            if (!empty($address) && !empty($address['line1'])) {
                $args['address'] = $address;

                if (MeprHooks::apply_filters('mepr_stripe_populate_customer_shipping_address', false) && !empty($full_name)) {
                    $args['shipping'] = [
                        'name'    => $full_name,
                        'address' => $address,
                    ];
                }
            }
        }

        return $args;
    }

    /**
     * Get the Stripe Product ID
     *
     * If the Stripe Product does not exist for the given product, one will be created.
     *
     * @param MeprProduct $prd             The MemberPress product.
     * @param boolean     $initial_payment Whether the product is for an initial payment.
     *
     * @return string                   The Stripe Product ID
     * @throws MeprHttpException        If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException      If there was an invalid or error response from Stripe.
     */
    public function get_product_id(MeprProduct $prd, $initial_payment = false)
    {
        $product_id = $prd->get_stripe_product_id($this->get_meta_gateway_id());

        if ($initial_payment) {
            $product_id = $prd->get_stripe_initial_payment_product_id($this->get_meta_gateway_id());
        }

        if (!is_string($product_id) || strpos($product_id, 'prod_') !== 0) {
            $product = $this->create_product($prd, $initial_payment);

            if ($initial_payment) {
                $prd->set_stripe_initial_payment_product_id($this->get_meta_gateway_id(), $product->id);
            } else {
                $prd->set_stripe_product_id($this->get_meta_gateway_id(), $product->id);
            }

            $product_id = $product->id;
        }

        return $product_id;
    }

    /**
     * Create a Stripe Plan
     *
     * @param  string           $product_id The Stripe Product ID.
     * @param  MeprSubscription $sub        The MemberPress subscription.
     * @param  string           $amount     The payment amount (excluding tax).
     * @return stdClass                        The Stripe Plan data
     * @throws MeprHttpException               If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException             If there was an invalid or error response from Stripe.
     */
    public function create_plan($product_id, MeprSubscription $sub, $amount)
    {
        $mepr_options = MeprOptions::fetch();

        if ($sub->period_type == 'months') {
            $interval = 'month';
        } elseif ($sub->period_type == 'years') {
            $interval = 'year';
        } elseif ($sub->period_type == 'weeks') {
            $interval = 'week';
        }

        $args = MeprHooks::apply_filters('mepr_stripe_create_plan_args', [
            'amount'         => $amount,
            'interval'       => $interval,
            'interval_count' => $sub->period,
            'currency'       => $mepr_options->currency_code,
            'product'        => $product_id,
        ], $sub);

        // Prevent a Stripe error if the user is using the pre-1.6.0 method of setting the statement_descriptor.
        if (array_key_exists('statement_descriptor', $args)) {
            unset($args['statement_descriptor']);
        }

        // Prevent a Stripe error if the 'product' value is modified by hook.
        if (!isset($args['product']) || is_array($args['product'])) {
            $args['product'] = $product_id;
        }

        // Don't enclose this in try/catch ... we want any errors to bubble up.
        $plan = (object) $this->send_stripe_request('plans', $args, 'post');

        return $plan;
    }

    /**
     * Create a Stripe Product
     *
     * @param MeprProduct $prd             The MemberPress product.
     * @param boolean     $initial_payment Whether the product is for an initial payment.
     *
     * @return stdClass                 The Stripe Product data
     * @throws MeprHttpException        If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException      If there was an invalid or error response from Stripe.
     */
    public function create_product(MeprProduct $prd, $initial_payment = false)
    {
        if ($initial_payment) {
            $product_name = $prd->post_title . ' - Initial Payment';
        } else {
            $product_name = $prd->post_title;
        }

        $args = MeprHooks::apply_filters('mepr_stripe_create_product_args', [
            'name' => $product_name,
            'type' => 'service',
        ], $prd);

        // Prevent a Stripe error in the rare case that a membership has an empty title.
        if (empty($args['name'])) {
            $args['name'] = sprintf(
            // Translators: %d: the product ID.
                __('Product %d', 'memberpress'),
                $prd->ID
            );
        }

        if (!array_key_exists('statement_descriptor', $args)) {
            $statement_descriptor = $this->get_statement_descriptor($prd);

            if (strlen($statement_descriptor) > 1) {
                $args['statement_descriptor'] = $statement_descriptor;
            }
        }

        $product = (object) $this->send_stripe_request('products', $args, 'post');

        return $product;
    }

    /**
     * Get stripe price id
     * If there is a plan id attached to product then use it, if not use price id instead
     * If a price id doesn't exist, create one
     *
     * @param MeprSubscription $sub   The MemberPress subscription.
     * @param MeprProduct      $prd   The MemberPress product.
     * @param float            $price The price to get the Stripe price ID for.
     *
     * @return string The Stripe price ID
     * @throws MeprHttpException If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException If there was an invalid or error response from Stripe.
     */
    public function get_stripe_price_id($sub, MeprProduct $prd, $price)
    {
        $mepr_options = MeprOptions::fetch();

        if ($prd->is_one_time_payment()) {
            $meta_key = '_mepr_stripe_onetime_price_id_' .
                  md5(serialize([
                      'currency'       => $mepr_options->currency_code,
                      'amount'         => $price,
                      'payment_method' => $this->get_meta_gateway_id(),
                  ]));
            $price_id = get_post_meta(
                $prd->ID,
                $meta_key,
                true
            );

            if (!empty($price_id)) {
                  return $price_id;
            }
        } else {
            $plan_id = $this->get_stripe_plan_id($sub, $prd, $price);

            if (! empty($plan_id)) {
                return $plan_id;
            }

            $meta_key = '_mepr_stripe_subscription_price_id_' .
                  md5(serialize([
                      'currency'       => $mepr_options->currency_code,
                      'amount'         => $price,
                      'payment_method' => $this->get_meta_gateway_id(),
                  ]));
            $price_id = get_post_meta(
                $prd->ID,
                $meta_key,
                true
            );

            if (! empty($price_id)) {
                return $price_id;
            }
        }

        // Handle zero decimal currencies in Stripe.
        $amount     = $this->to_zero_decimal_amount($price);
        $product_id = $prd->ID;

        if ($prd->is_one_time_payment()) {
            $args = MeprHooks::apply_filters('mepr_stripe_create_price_args', [
                'product'     => $this->get_product_id($prd),
                'unit_amount' => $amount,
                'currency'    => $mepr_options->currency_code,
            ], $sub);

            $price = (object) $this->send_stripe_request('prices', $args, 'post');
            update_post_meta($product_id, $meta_key, $price->id);

            return $price->id;
        }

        $interval = '';

        if ($sub->period_type == 'months') {
            $interval = 'month';
        } elseif ($sub->period_type == 'years') {
            $interval = 'year';
        } elseif ($sub->period_type == 'weeks') {
            $interval = 'week';
        }

        $args = MeprHooks::apply_filters('mepr_stripe_create_price_args', [
            'product'     => $this->get_product_id($prd),
            'unit_amount' => $amount,
            'currency'    => $mepr_options->currency_code,
            'recurring'   => [
                'interval' => $interval,
            ],
        ], $sub);

        // Prevent a Stripe error if the 'product' value is modified by hook.
        if (!isset($args['product']) || is_array($args['product'])) {
            $args['product'] = $product_id;
        }

        $price = (object) $this->send_stripe_request('prices', $args, 'post');
        update_post_meta($product_id, $meta_key, $price->id);

        return $price->id;
    }

    /**
     * Get the Stripe Price ID for a one-time payment for the given product and amount
     *
     * If the Stripe Price does not exist for the given product and amount, one will be created.
     *
     * @param  MeprProduct $prd    The MemberPress product.
     * @param  float       $amount The amount to charge.
     * @return string                      The Stripe Price ID
     * @throws MeprHttpException           If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException         If there was an invalid or error response from Stripe.
     */
    public function get_one_time_price_id(MeprProduct $prd, $amount)
    {
        $mepr_options = MeprOptions::fetch();

        $terms = [
            'currency'       => $mepr_options->currency_code,
            'amount'         => $amount,
            'payment_method' => $this->get_meta_gateway_id(),
        ];

        $meta_key = sprintf('_mepr_stripe_onetime_price_id_%s', md5(serialize($terms)));
        $price_id = get_post_meta($prd->ID, $meta_key, true);

        if (!is_string($price_id) || strpos($price_id, 'price_') !== 0) {
            $args = MeprHooks::apply_filters('mepr_stripe_create_price_args', [
                'product'     => $this->get_product_id($prd),
                'unit_amount' => $this->to_zero_decimal_amount($amount),
                'currency'    => $mepr_options->currency_code,
            ], $prd, $amount);

            $price = (object) $this->send_stripe_request('prices', $args, 'post');

            update_post_meta($prd->ID, $meta_key, $price->id);
            $price_id = $price->id;
        }

        return $price_id;
    }

    /**
     * Get the Stripe Plan ID
     *
     * If the Stripe Plan does not exist for the given subscription, one will be created.
     *
     * @param  MeprSubscription $sub    The MemberPress subscription.
     * @param  MeprProduct      $prd    The MemberPress product.
     * @param  float            $amount The plan amount.
     * @return string                      The Stripe Plan ID
     * @throws MeprHttpException           If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException         If there was an invalid or error response from Stripe.
     */
    public function get_stripe_plan_id(MeprSubscription $sub, MeprProduct $prd, $amount)
    {
        $amount  = $this->to_zero_decimal_amount($amount);
        $plan_id = $prd->get_stripe_plan_id($this->get_meta_gateway_id(), $amount);

        if (!is_string($plan_id) || strpos($plan_id, 'plan_') !== 0) {
            $plan = $this->create_plan($this->get_product_id($prd), $sub, $amount);
            $prd->set_stripe_plan_id($this->get_meta_gateway_id(), $amount, $plan->id);
            $plan_id = $plan->id;
        }

        return $plan_id;
    }

    /**
     * Create a Stripe Coupon
     *
     * @param  MeprCoupon $cpn             The MemberPress coupon.
     * @param  string     $discount_amount The coupon discount amount.
     * @param  boolean    $onetime         Whether the coupon is for a one-time payment.
     * @return stdClass                             The Stripe Coupon data
     * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException                  If there was an invalid or error response from Stripe.
     */
    public function create_coupon(MeprCoupon $cpn, $discount_amount, $onetime)
    {
        $args = MeprHooks::apply_filters('mepr_stripe_create_coupon_args', [
            'name'     => substr($cpn->post_title, 0, 40),
            'duration' => 'forever',
        ], $cpn);

        $mepr_options = MeprOptions::fetch();


        if ($onetime) {
            $args['duration'] = 'once';

            if ($cpn->first_payment_discount_type == 'percent') {
                $args = array_merge([
                    'percent_off' => $discount_amount,
                ], $args);
            } else {
                $args = array_merge([
                    'amount_off' => $discount_amount,
                    'currency'   => $mepr_options->currency_code,
                ], $args);
            }
        } else {
            if ($cpn->discount_type == 'percent') {
                $args = array_merge([
                    'percent_off' => $discount_amount,
                ], $args);
            } else {
                $args = array_merge([
                    'amount_off' => $discount_amount,
                    'currency'   => $mepr_options->currency_code,
                ], $args);
            }
        }

        $coupon = (object) $this->send_stripe_request('coupons', $args, 'post');

        return $coupon;
    }

    /**
     * Get the Stripe Coupon ID
     *
     * @param  MeprCoupon $cpn             The MemberPress coupon.
     * @param  string     $discount_amount The coupon discount amount.
     * @param  boolean    $onetime         Whether the coupon is for a one-time payment.
     * @return string                               The Stripe Coupon ID
     * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException                  If there was an invalid or error response from Stripe.
     */
    public function get_coupon_id(MeprCoupon $cpn, $discount_amount, $onetime = false)
    {
        $coupon_id = $cpn->get_stripe_coupon_id($this->get_meta_gateway_id(), $discount_amount, $onetime);

        if (!is_string($coupon_id) || $coupon_id === '') {
            $coupon = $this->create_coupon($cpn, $discount_amount, $onetime);
            $cpn->set_stripe_coupon_id($this->get_meta_gateway_id(), $discount_amount, $coupon->id, $onetime);
            $coupon_id = $coupon->id;
        }

        return $coupon_id;
    }

    /**
     * Get the statement descriptor
     *
     * @param  MeprProduct $product The MemberPress product.
     * @return string               The statement descriptor
     */
    private function get_statement_descriptor(MeprProduct $product)
    {
        $descriptor = MeprUtils::blogname();

        if (empty($descriptor)) {
            $descriptor = parse_url(get_option('siteurl'), PHP_URL_HOST);
        }

        $descriptor = MeprHooks::apply_filters('mepr_stripe_statement_descriptor', $descriptor, $product);

        return $this->sanitize_statement_descriptor($descriptor);
    }

    /**
     * Create a Stripe Subscription
     *
     * @param MeprTransaction  $txn                The MemberPress transaction.
     * @param MeprSubscription $sub                The MemberPress subscription.
     * @param MeprProduct      $prd                The MemberPress product.
     * @param string           $customer_id        The Stripe Customer ID.
     * @param string           $payment_method_id  The Stripe PaymentMethod ID to use for payment or setup.
     * @param boolean          $default_incomplete Whether the subscription should default to incomplete status.
     * @param integer          $trial_days         The number of trial days to set on the subscription.
     *
     * @return stdClass                                The Stripe Subscription data
     * @throws MeprHttpException                       If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException                     If there was an invalid or error response from Stripe.
     */
    public function create_subscription(MeprTransaction $txn, MeprSubscription $sub, MeprProduct $prd, $customer_id, $payment_method_id = null, $default_incomplete = true, $trial_days = 0)
    {
        $mepr_options = MeprOptions::fetch();

        $price = MeprHooks::apply_filters('mepr_stripe_product_base_price', $prd->price, $prd, $txn->user(), $sub);
        $price = MeprUtils::maybe_round_to_minimum_amount($price);

        $coupon = $sub->coupon();

        if ($coupon instanceof MeprCoupon) {
            $discounted_amount = $coupon->apply_discount($price, false, $prd);
            $minimum_amount    = MeprUtils::get_minimum_amount();

            // If the coupon brings the subscription amount below the minimum charge amount, set the subscription amount to
            // the minimum charge amount and don't use a Stripe coupon.
            if ($minimum_amount && $discounted_amount < $minimum_amount) {
                $price  = $minimum_amount;
                $coupon = null;
            }
        }

        $item = ['plan' => $this->get_stripe_plan_id($sub, $prd, $price)];

        if (get_option('mepr_calculate_taxes') && $txn->tax_rate > 0 && $txn->tax_amount > 0) {
            $item = array_merge($item, [
                'tax_rates' => [$this->get_stripe_tax_rate_id($txn->tax_desc, $txn->tax_rate, $prd, $mepr_options->attr('tax_calc_type') == 'inclusive')],
            ]);
        }

        $args = [
            'customer'         => $customer_id,
            'items'            => [$item],
            'payment_settings' => [
                'payment_method_types'        => $this->get_subscription_payment_method_types(),
                'save_default_payment_method' => 'on_subscription',
            ],
            'expand'           => [
                'latest_invoice.payment_intent',
            ],
            'metadata'         => [
                'platform'              => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
                'transaction_id'        => $txn->id,
                'site_url'              => get_site_url(),
                'ip_address'            => MeprAntiCardTestingCtrl::get_ip(),
                'memberpress_product'    => $prd->post_title,
                'memberpress_product_id' => $prd->ID,
            ],
        ];

        if (!empty($prd->post_title)) {
            $args['description'] = $prd->post_title;
        }

        if ($payment_method_id) {
            $args = array_merge($args, ['default_payment_method' => $payment_method_id]);
        }

        if ($default_incomplete) {
            $args = array_merge($args, [
                'payment_behavior' => 'default_incomplete',
            ]);
        }

        if ($trial_days > 0) {
            $args = array_merge($args, ['trial_period_days' => $trial_days]);
        }

        if ($coupon instanceof MeprCoupon) {
            $discount_amount = $this->get_coupon_discount_amount($coupon, $prd);

            if ($discount_amount > 0) {
                $args = array_merge(['coupon' => $this->get_coupon_id($coupon, $discount_amount)], $args);
            }
        }

        $args = MeprHooks::apply_filters('mepr_stripe_subscription_args', $args, $txn, $sub);

        $args = $this->add_application_fee_percentage($args);

        $this->email_status("create_subscription: \n" . MeprUtils::object_to_string($txn) . "\n", $this->settings->debug);

        $subscription = (object) $this->send_stripe_request('subscriptions', $args, 'post');

        $this->maybe_record_sub_application_fee_meta($args, $sub);

        return $subscription;
    }

    /**
     * Get the discount amount for the given coupon
     *
     * @param  MeprCoupon  $coupon  The coupon being used.
     * @param  MeprProduct $product The membership being purchased.
     * @return string|integer           The formatted discount amount or 0 if no discount
     */
    public function get_coupon_discount_amount(MeprCoupon $coupon, MeprProduct $product)
    {
        $discount_amount = $coupon->get_discount_amount($product);

        if ($discount_amount > 0) {
            if ($coupon->discount_type == 'percent') {
                $discount_amount = MeprUtils::format_float($discount_amount);
            } else {
                $discount_amount = $this->to_zero_decimal_amount($discount_amount);
            }

            if ($discount_amount > 0) {
                return $discount_amount;
            }
        }

        return 0;
    }

    /**
     * Retrieve a Stripe Subscription
     *
     * @param  string $subscription_id The Stripe Subscription ID.
     * @return stdClass                             The Stripe Subscription data
     * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException                  If there was an invalid or error response from Stripe.
     */
    public function retrieve_subscription($subscription_id)
    {
        $args = MeprHooks::apply_filters('mepr_stripe_retrieve_subscription_args', [
            'expand' => [
                'latest_invoice',
                'default_payment_method',
            ],
        ]);

        return (object) $this->send_stripe_request('subscriptions/' . $subscription_id, $args, 'get');
    }

    /**
     * Retrieve a Stripe SetupIntent
     *
     * @param  string $setup_intent_id The Stripe SetupIntent ID.
     * @return stdClass                             The Stripe SetupIntent data
     * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException                  If there was an invalid or error response from Stripe.
     */
    public function retrieve_setup_intent($setup_intent_id)
    {
        return (object) $this->send_stripe_request("setup_intents/{$setup_intent_id}", ['expand' => ['payment_method']], 'get');
    }

    /**
     * Retrieve a Stripe PaymentIntent
     *
     * @param  string $payment_intent_id The Stripe PaymentIntent ID.
     * @return stdClass                               The Stripe PaymentIntent data
     * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException                    If there was an invalid or error response from Stripe.
     */
    public function retrieve_payment_intent($payment_intent_id)
    {
        return (object) $this->send_stripe_request('payment_intents/' . $payment_intent_id, [], 'get');
    }

    /**
     * Retrieve a Stripe Invoice
     *
     * @param  string $invoice_id The Stripe Invoice ID.
     * @return stdClass                        The Stripe Invoice data
     * @throws MeprHttpException               If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException             If there was an invalid or error response from Stripe.
     */
    public function retrieve_invoice($invoice_id)
    {
        return (object) $this->send_stripe_request('invoices/' . $invoice_id, ['expand' => ['payment_intent']], 'get');
    }

    /**
     * Retrieve a Stripe Customer
     *
     * @param  string $customer_id The Stripe Customer ID.
     * @return stdClass                         The Stripe Customer data
     * @throws MeprHttpException                If there was an HTTP error connecting to Stripe.
     * @throws MeprRemoteException              If there was an invalid or error response from Stripe.
     */
    public function retrieve_customer($customer_id)
    {
        return (object) $this->send_stripe_request('customers/' . $customer_id, ['expand' => ['invoice_settings.default_payment_method']], 'get');
    }

    /**
     * Get the gateway ID for storing Stripe object IDs
     *
     * Object IDs in test mode do not exist in live mode, so we need to differentiate.
     *
     * @return string
     */
    public function get_meta_gateway_id()
    {
        $key = $this->id;

        if ($this->is_test_mode()) {
            $key .= '_test';
        }

        return $key;
    }

    /**
     * Is the currency a zero-decimal currency?
     *
     * 'HUF' is a special case for Stripe - it's a zero-decimal currency that must be multiplied by 100.
     *
     * @return boolean
     */
    public static function is_zero_decimal_currency()
    {
        $mepr_options = MeprOptions::fetch();

        if ($mepr_options->currency_code == 'HUF') {
            return false;
        }

        return MeprUtils::is_zero_decimal_currency();
    }

    /**
     * Get the locale code
     *
     * Converts the configured MP language code into a Stripe-compatible locale code
     *
     * @see    https://stripe.com/docs/js/appendix/supported_locales
     * @return string
     */
    public static function get_locale_code()
    {
        $mepr_options = MeprOptions::fetch();
        $locale_code  = 'auto';

        $locales = [
            'US' => 'en',
            'AE' => 'ar',
            'AR' => 'es-419',
            'AU' => 'en',
            'BG' => 'bg',
            'BR' => 'pt-BR',
            'CH' => 'auto',
            'CN' => 'zh',
            'CO' => 'es-419',
            'CZ' => 'cs',
            'DE' => 'de',
            'DK' => 'da',
            'EN' => 'en',
            'ES' => 'es',
            'FI' => 'fi',
            'FR' => 'fr',
            'GB' => 'en-GB',
            'HE' => 'he',
            'HR' => 'hr',
            'HU' => 'hu',
            'ID' => 'id',
            'IS' => 'auto',
            'IT' => 'it',
            'JP' => 'ja',
            'KR' => 'ko',
            'MS' => 'en',
            'MX' => 'es-419',
            'NL' => 'nl',
            'NO' => 'nb',
            'PE' => 'es-419',
            'PH' => 'fil',
            'PL' => 'pl',
            'PT' => 'pt',
            'RO' => 'ro',
            'RU' => 'ru',
            'SE' => 'sv',
            'SK' => 'sk',
            'SR' => 'nl',
            'SW' => 'auto',
            'TH' => 'th',
            'TN' => 'ar',
            'TR' => 'tr',
            'TW' => 'zh-TW',
            'VI' => 'vi',
            'ZA' => 'auto',
        ];

        if (isset($locales[$mepr_options->language_code])) {
            $locale_code = $locales[$mepr_options->language_code];
        }

        return MeprHooks::apply_filters('mepr_stripe_locale_code', $locale_code);
    }

    /**
     * Add application fee percentage to the arguments
     *
     * @param  array $args The arguments to add the application fee percentage to.
     * @return array The arguments with the application fee percentage added
     */
    private function add_application_fee_percentage($args)
    {
        $percentage = $this->get_application_fee_percentage();

        if ($percentage > 0) {
            $args['application_fee_percent'] = $percentage;
        }

        return $args;
    }

    /**
     * Add application fee amount to the arguments
     *
     * @param  array $args The arguments to add the application fee amount to.
     * @return array The arguments with the application fee amount added
     */
    private function add_application_fee_amount($args)
    {
        $percentage = $this->get_application_fee_percentage();

        if ($percentage > 0) {
            $amount          = $args['amount'];
            $application_fee = $amount * ( $percentage / 100 );
            $application_fee = floor($application_fee);
            if (! empty($application_fee)) {
                $args['application_fee_amount'] = intval($application_fee);
            }
        }

        return $args;
    }

    /**
     * Get the application fee percentage
     *
     * @return float The application fee percentage
     */
    private function get_application_fee_percentage()
    {
        $application_fee_percentage = 0;
        if (MeprDrmHelper::is_app_fee_enabled()) {
            $application_fee_percentage = MeprDrmHelper::get_application_fee_percentage();
        }

        return round(floatval($application_fee_percentage), 2);
    }

    /**
     * Maybe record subscription application fee meta
     *
     * @param  array            $args The arguments to record the application fee meta for.
     * @param  MeprSubscription $sub  The subscription to record the application fee meta for.
     * @return void
     */
    public function maybe_record_sub_application_fee_meta($args, $sub)
    {

        if (!$sub instanceof MeprSubscription) {
            return;
        }

        if (isset($args['application_fee_percent'])) {
            $sub->update_meta('application_fee_percent', $args['application_fee_percent']);
            $sub->update_meta('application_fee_version', isset($args['application_fee_version']) ? $args['application_fee_version'] : MeprDrmHelper::get_drm_app_fee_version());
        }
    }

    /**
     * Maybe record transaction application fee meta
     *
     * @param  array           $args The arguments to record the application fee meta for.
     * @param  MeprTransaction $txn  The transaction to record the application fee meta for.
     * @return void
     */
    private function maybe_record_txn_application_fee_meta($args, $txn)
    {

        if (!$txn instanceof MeprTransaction) {
            return;
        }

        if (isset($args['application_fee_amount'])) {
            $txn->update_meta('application_fee_amount', $args['application_fee_amount']);
            $txn->update_meta('application_fee_percent', $this->get_application_fee_percentage());
            $txn->update_meta('application_fee_version', MeprDrmHelper::get_drm_app_fee_version());
        }
    }

    /**
     * Build an invoice item
     *
     * @param  float           $amount      The amount to build the invoice item for.
     * @param  MeprTransaction $txn         The transaction object.
     * @param  MeprProduct     $prd         The product object.
     * @param  string          $customer_id The customer ID.
     * @return array
     * @throws MeprHttpException When HTTP request to Stripe API fails.
     * @throws MeprRemoteException When Stripe API returns an error response.
     */
    public function build_invoice_item($amount, MeprTransaction $txn, MeprProduct $prd, $customer_id)
    {
        $mepr_options = MeprOptions::fetch();

        $args = [
            'customer'     => $customer_id,
            'amount'       => $this->to_zero_decimal_amount($amount),
            'currency'     => $mepr_options->currency_code,
            'description'  => $prd->post_title,
            'metadata'     => [
                'platform'   => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
                'site_url'   => get_site_url(),
                'ip_address' => MeprAntiCardTestingCtrl::get_ip(),
            ],
            'discountable' => 'false',
        ];

        if (get_option('mepr_calculate_taxes') && $txn->tax_rate > 0 && $txn->tax_amount > 0) {
            $args['tax_rates'] = [$this->get_stripe_tax_rate_id($txn->tax_desc, $txn->tax_rate, $prd, $mepr_options->attr('tax_calc_type') == 'inclusive')];
        }

        if (empty($args['description'])) {
            // Translators: %d: product ID.
            $args['description'] = sprintf(__('Product %d', 'memberpress'), $prd->ID);
        }

        return $args;
    }

    /**
     * Build a line item for a checkout session
     *
     * @param string          $price The Stripe price ID.
     * @param MeprTransaction $txn   The transaction object.
     * @param MeprProduct     $prd   The product object.
     *
     * @return array
     * @throws MeprHttpException When HTTP request to Stripe API fails.
     * @throws MeprRemoteException When Stripe API returns an error response.
     */
    private function build_line_item($price, MeprTransaction $txn, MeprProduct $prd)
    {
        $mepr_options    = MeprOptions::fetch();
        $calculate_taxes = (bool) get_option('mepr_calculate_taxes');
        $tax_inclusive   = $mepr_options->attr('tax_calc_type') == 'inclusive';

        $line_item = [
            'price'    => $price,
            'quantity' => 1,
        ];

        if ($calculate_taxes && $txn->tax_rate > 0 && $txn->tax_amount > 0) {
            $line_item = array_merge($line_item, [
                'tax_rates' => [$this->get_stripe_tax_rate_id($txn->tax_desc, $txn->tax_rate, $prd, $tax_inclusive)],
            ]);
        }

        return $line_item;
    }

    /**
     * Handle the `payment_intent.payment_failed` webhook event
     *
     * For one-time payments, get the IP address from the PaymentIntent metadata, for subscriptions get it from the
     * Subscription metadata, then fire the hook for the card testing protection.
     *
     * @param stdClass $payment_intent The Stripe PaymentIntent data.
     *
     * @return void
     */
    public function handle_payment_intent_payment_failed_webhook($payment_intent)
    {
        $ip = isset($payment_intent->metadata['ip_address']) ? $payment_intent->metadata['ip_address'] : '';

        if (!empty($ip)) {
            MeprHooks::do_action('mepr_stripe_payment_failed', $ip);
        } else {
            try {
                $payment_intent = (object) $this->send_stripe_request("payment_intents/$payment_intent->id", [
                    'expand' => [
                        'invoice.subscription',
                    ],
                ], 'get');

                $subscription = isset($payment_intent->invoice['subscription']) ? (object) $payment_intent->invoice['subscription'] : null;

                if ($subscription) {
                      $ip = isset($subscription->metadata['ip_address']) ? $subscription->metadata['ip_address'] : '';

                    if (!empty($ip)) {
                        MeprHooks::do_action('mepr_stripe_payment_failed', $ip);
                    }
                }
            } catch (Exception $e) {
                // Ignore.
            }
        }
    }

    /**
     * Handle the `setup_intent.setup_failed` webhook event
     *
     * Get the IP address from the SetupIntent metadata, then fire the hook for the card testing protection.
     *
     * @param stdClass $setup_intent The Stripe SetupIntent data.
     *
     * @return void
     */
    public function handle_setup_intent_setup_failed_webhook($setup_intent)
    {
        $ip = isset($setup_intent->metadata['ip_address']) ? $setup_intent->metadata['ip_address'] : '';

        if (!empty($ip)) {
            MeprHooks::do_action('mepr_stripe_payment_failed', $ip);
        }
    }

    /**
     * Get the underlying charge ID from a PaymentIntent
     *
     * Since Stripe API version 2022-11-15 the property moved from 'charges' to 'latest_charge'.
     *
     * @param  stdClass $payment_intent The Stripe PaymentIntent data.
     * @return string|null
     */
    private function get_payment_intent_charge_id($payment_intent)
    {
        if (isset($payment_intent->latest_charge)) {
            return $payment_intent->latest_charge;
        }

        if (isset($payment_intent->charges['data'][0]['id'])) {
            return $payment_intent->charges['data'][0]['id'];
        }

        return null;
    }

    /**
     * Get the options to set on the Stripe Elements instance, depending on the product type
     *
     * @param  MeprProduct           $prd                 The product object.
     * @param  MeprTransaction       $txn                 The transaction object.
     * @param  MeprSubscription|null $sub                 The subscription object.
     * @param  string                $coupon_code         The coupon code.
     * @param  MeprProduct[]         $order_bump_products The order bump products.
     * @return array
     * @throws Exception When payment intent creation fails.
     */
    public function get_elements_options(MeprProduct $prd, MeprTransaction $txn, MeprSubscription $sub = null, $coupon_code = '', array $order_bump_products = [])
    {
        if ($prd->is_one_time_payment() || !$prd->is_payment_required($coupon_code)) {
            $amount           = $prd->is_payment_required($coupon_code) ? (float) $txn->total : 0.00;
            $has_subscription = false;

            foreach ($order_bump_products as $product) {
                list($transaction, $subscription) = MeprCheckoutCtrl::prepare_transaction(
                    $product,
                    0,
                    get_current_user_id(),
                    $this->id,
                    false,
                    false
                );

                if ($product->is_one_time_payment()) {
                      $amount += (float) $transaction->total;
                } else {
                    if (!($subscription instanceof MeprSubscription)) {
                        throw new Exception(__('Subscription not found', 'memberpress'));
                    }

                    $amount          += (float) ($subscription->trial && $subscription->trial_days > 0 ? $subscription->trial_total : $subscription->total);
                    $has_subscription = true;
                }
            }

            if ($amount > 0.00) {
                $store_payment_method = MeprHooks::apply_filters('mepr_stripe_payment_intent_store_payment_method', $has_subscription, $txn, $prd);
                $setup_future_usage   = $store_payment_method ? 'off_session' : null;

                $options = [
                    'mode'               => 'payment',
                    'amount'             => (int) $this->to_zero_decimal_amount($amount),
                    'paymentMethodTypes' => $this->get_payment_intent_payment_method_types($setup_future_usage, $amount),
                    'setupFutureUsage'   => $setup_future_usage,
                ];
            } else {
                $options = [
                    'mode'               => 'setup',
                    'paymentMethodTypes' => $this->get_setup_intent_payment_method_types(),
                ];
            }
        } else {
            if (!isset($sub) || !($sub instanceof MeprSubscription)) {
                throw new Exception(__('Subscription not found', 'memberpress'));
            }

            $amount = (float) ($sub->trial && $sub->trial_days > 0 ? $sub->trial_total : $sub->total);

            foreach ($order_bump_products as $product) {
                list($transaction, $subscription) = MeprCheckoutCtrl::prepare_transaction(
                    $product,
                    0,
                    get_current_user_id(),
                    $this->id,
                    false,
                    false
                );

                if ($product->is_one_time_payment()) {
                    if ((float) $transaction->total > 0) {
                        $amount += (float) $transaction->total;
                    }
                } else {
                    if (!($subscription instanceof MeprSubscription)) {
                        throw new Exception(__('Subscription not found', 'memberpress'));
                    }

                    $amount += (float) ($subscription->trial && $subscription->trial_days > 0 ? $subscription->trial_total : $subscription->total);
                }
            }

            if ($sub->trial && $sub->trial_days > 0 && (float) $sub->trial_amount <= 0.00 && $amount <= 0.00) {
                $options = [
                    'mode'               => 'setup',
                    'paymentMethodTypes' => $this->get_setup_intent_payment_method_types(),
                ];
            } else {
                $options = [
                    'mode'               => 'subscription',
                    'amount'             => (int) $this->to_zero_decimal_amount($amount),
                    'paymentMethodTypes' => $this->get_subscription_payment_method_types(),
                ];
            }
        }

        return $options;
    }

    /**
     * Is the given payment method type async?
     *
     * @param  string $type The payment method type.
     * @return boolean
     */
    private function is_async_payment_method($type)
    {
        $payment_methods = require MEPR_DATA_PATH . '/stripe_payment_methods.php';

        foreach ($payment_methods as $payment_method) {
            if ($payment_method['key'] == $type) {
                return isset($payment_method['async']) && $payment_method['async'];
            }
        }

        return false;
    }

    /**
     * For backwards compatibility, enable any payment methods by default that were previously enabled using old options or hooks
     *
     * @return string[]
     */
    private function get_default_payment_methods()
    {
        $mepr_options            = MeprOptions::fetch();
        $default_payment_methods = [];

        // If Link was enabled using the old option, add it by default.
        if (
            isset($this->settings->stripe_link_enabled) &&
            ($this->settings->stripe_link_enabled == 'on' || $this->settings->stripe_link_enabled == true) &&
            in_array($mepr_options->currency_code, ['EUR', 'BGN', 'HRK', 'CZK', 'DKK', 'GIP', 'HUF', 'NOK', 'PLN', 'RON', 'SEK', 'CHF', 'GBP', 'USD'], true)
        ) {
            $default_payment_methods = ['link'];
        }

        // Add payment methods from the old Stripe Checkout defaults and hooks.
        if (isset($this->settings->stripe_checkout_enabled) && $this->settings->stripe_checkout_enabled == 'on') {
            $default_checkout_recurring = $mepr_options->currency_code == 'EUR' ? ['sepa_debit'] : [];
            $default_checkout_recurring = MeprHooks::apply_filters('mepr-stripe-checkout-methods-for-recurring-payment', $default_checkout_recurring);
            $default_payment_methods    = array_merge($default_payment_methods, $default_checkout_recurring);

            $default_checkout_one_time = $mepr_options->currency_code == 'EUR' ? ['sepa_debit', 'ideal', 'bancontact', 'giropay', 'sofort'] : [];

            if ($mepr_options->currency_code == 'EUR' || $mepr_options->currency_code == 'PLN') {
                $default_checkout_one_time[] = 'p24';
            }

            $default_checkout_one_time = MeprHooks::apply_filters('mepr-stripe-checkout-methods-for-onetime-payment', $default_checkout_one_time);
            $default_payment_methods   = array_merge($default_payment_methods, $default_checkout_one_time);
        }

        return array_unique($default_payment_methods);
    }

    /**
     * Create an incomplete test payment to ensure that the given payment method types work
     *
     * @param  array $payment_method_types The payment method types to create a test payment for.
     * @return void
     * @throws MeprHttpException The HTTP exception.
     * @throws MeprRemoteException The remote exception.
     */
    public function create_test_payment_intent(array $payment_method_types)
    {
        $mepr_options   = MeprOptions::fetch();
        $minimum_amount = MeprUtils::get_minimum_amount();
        $amount         = $minimum_amount ? 2 * $minimum_amount : 10;

        array_unshift($payment_method_types, 'card');

        if ($mepr_options->currency_code == 'USD' && in_array('affirm', $payment_method_types, true)) {
            $amount = 50; // 50 USD is the minimum charge amount for Affirm
        }

        if ($mepr_options->currency_code == 'JPY' && in_array('konbini', $payment_method_types, true)) {
            $amount = 120; // 120 JPY is the minimum charge amount for Konbini
        }

        if ($mepr_options->currency_code == 'BRL' && in_array('boleto', $payment_method_types, true)) {
            $amount = 5; // 5 BRL is the minimum charge amount for Boleto
        }

        $amount = MeprHooks::apply_filters('mepr_stripe_test_payment_amount', $amount, $payment_method_types, $this);

        $args = [
            'amount'               => $this->to_zero_decimal_amount($amount),
            'currency'             => $mepr_options->currency_code,
            'payment_method_types' => $payment_method_types,
            'description'          => __('MemberPress Test Payment', 'memberpress'),
        ];

        $this->send_stripe_request('payment_intents', $args);
    }

    /**
     * Records CC Variables.
     *
     * @param object $sub            The subscription object.
     * @param mixed  $payment_method The payment method object.
     *
     * @return void
     */
    private function record_cc_vars($sub, $payment_method)
    {
        if ($sub instanceof MeprSubscription && isset($payment_method) && is_object($payment_method) && isset($payment_method->card) && is_array($payment_method->card)) {
            $sub->cc_last4     = $payment_method->card['last4'];
            $sub->cc_exp_month = $payment_method->card['exp_month'];
            $sub->cc_exp_year  = $payment_method->card['exp_year'];
        }
    }

    /**
     * Is the given ID a Charge object ID?
     *
     * A Charge object ID starts with either ch_ or py_.
     *
     * @param  string $id The ID to check.
     * @return boolean
     */
    private static function is_charge_object_id($id)
    {
        return strpos($id, 'ch_') === 0 || strpos($id, 'py_') === 0;
    }

    /**
     * Handle the `payment_method.automatically_updated` event.
     *
     * @param stdClass $payment_method The payment method object.
     * @param stdClass $previous       The previous payment method object.
     *
     * @return void
     */
    private function handle_payment_method_automatically_updated_event(stdClass $payment_method, stdClass $previous)
    {
        try {
            if (
                !empty($payment_method->customer) &&
                !empty($payment_method->card['last4']) &&
                !empty($previous->card['last4'])
            ) {
                global $wpdb;
                $mepr_db = MeprDb::fetch();

                $list = (object) $this->send_stripe_request(
                    'subscriptions',
                    ['customer' => $payment_method->customer],
                    'get'
                );

                if (isset($list->data) && is_array($list->data)) {
                    $subscr_ids = array_column($list->data, 'id');
                } else {
                    return;
                }

                // For backwards compatibility, look for a cus_ subscription also.
                $subscr_ids[] = $payment_method->customer;
                $placeholders = array_fill(0, count($subscr_ids), '%s');
                $values       = array_merge(
                    $subscr_ids,
                    [
                        $this->id,
                        $previous->card['last4'],
                    ]
                );

                $sub_ids = $wpdb->get_col(
                    // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
                    $wpdb->prepare(
                        "SELECT id
                        FROM $mepr_db->subscriptions
                        WHERE subscr_id IN (" . join(', ', $placeholders) . ')
                        AND gateway = %s
                        AND cc_last4 = %s',
                        $values
                    )
                );

                foreach ($sub_ids as $sub_id) {
                    $sub = new MeprSubscription($sub_id);

                    if ($sub->id > 0) {
                        $this->record_cc_vars($sub, $payment_method);
                        $sub->store();
                    }
                }
            }
        } catch (Exception $e) {
            // Do nothing.
        }
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing
    /**
     * Process a payment via Ajax.
     *
     * @param MeprProduct           $prd The product.
     * @param MeprUser              $usr The user.
     * @param MeprTransaction       $txn The transaction.
     * @param MeprSubscription|null $sub The subscription (null for one-time payments).
     * @param MeprCoupon|null       $cpn The coupon (null if no coupon).
     */
    public function process_payment_ajax(
        MeprProduct $prd,
        MeprUser $usr,
        MeprTransaction $txn,
        MeprSubscription $sub = null,
        MeprCoupon $cpn = null
    ) {
        try {
            if ($this->settings->stripe_checkout_enabled == 'on') {
                wp_send_json_error(__('Bad request', 'memberpress'));
            }

            $mepr_options = MeprOptions::fetch();
            $coupon_code  = $cpn instanceof MeprCoupon ? $cpn->post_title : '';
            list($ob_transactions, $ob_total, $has_subscription_order_bump) = $this->process_order_bumps($prd, $usr, $txn, $sub);

            $customer_id = $usr->get_stripe_customer_id($this->get_meta_gateway_id());

            if (!is_string($customer_id) || strpos($customer_id, 'cus_') !== 0) {
                $customer_id = $this->get_customer_id($usr);
            } else {
                $this->update_customer($customer_id, $usr);
            }

            $action = 'confirmPayment';

            $thank_you_page_args = [
                'membership'     => sanitize_title($prd->post_title),
                'membership_id'  => $prd->ID,
                'transaction_id' => $txn->id,
            ];

            if ($prd->is_one_time_payment() || !$prd->is_payment_required($coupon_code)) {
                $total  = $prd->is_payment_required($coupon_code) ? (float) $txn->total : 0.00;
                $total += $ob_total;

                if ($total > 0.00) {
                    $store_payment_method = MeprHooks::apply_filters('mepr_stripe_payment_intent_store_payment_method', $has_subscription_order_bump, $txn, $prd);
                    $setup_future_usage   = $store_payment_method ? 'off_session' : null;
                    $payment_intent       = $this->create_payment_intent($total, $customer_id, $txn, $prd, $setup_future_usage);

                    $client_secret = $payment_intent->client_secret;

                    $txn->trans_num = $payment_intent->id;
                    $txn->store();
                } else {
                    $setup_intent = $this->create_setup_intent($customer_id, $txn, $prd);

                    $client_secret = $setup_intent->client_secret;

                    $txn->trans_num = $setup_intent->id;
                    $txn->store();

                    $action = 'confirmSetup';
                }
            } else {
                if (!$sub instanceof MeprSubscription) {
                    wp_send_json_error(__('Subscription not found', 'memberpress'));
                }

                $thank_you_page_args['subscription_id'] = $sub->id;

                $calculate_taxes = (bool) get_option('mepr_calculate_taxes');
                $tax_inclusive   = $mepr_options->attr('tax_calc_type') == 'inclusive';
                $invoice_items   = [];

                foreach ($ob_transactions as $transaction) {
                    $product = $transaction->product();

                    if (empty($product->ID)) {
                        wp_send_json_error(__('Product not found', 'memberpress'));
                    }

                    if (!$transaction->is_payment_required()) {
                        continue;
                    } elseif ($transaction->is_one_time_payment()) {
                        if ((float) $transaction->total > 0) {
                            $amount          = $calculate_taxes && !$tax_inclusive && $transaction->tax_rate > 0 ? (float) $transaction->amount : (float) $transaction->total;
                            $invoice_items[] = $this->build_invoice_item($amount, $transaction, $product, $customer_id);
                        }
                    } else {
                        $subscription = $transaction->subscription();

                        if (!($subscription instanceof MeprSubscription)) {
                            wp_send_json_error(__('Subscription not found', 'memberpress'));
                        }

                        if ($subscription->trial && $subscription->trial_days > 0) {
                            if ((float) $subscription->trial_total > 0) {
                                $amount          = $calculate_taxes && !$tax_inclusive && $transaction->tax_rate > 0 ? (float) $subscription->trial_amount : (float) $subscription->trial_total;
                                $invoice_items[] = $this->build_invoice_item($amount, $transaction, $product, $customer_id);
                            }
                        } else {
                            $amount          = $calculate_taxes && !$tax_inclusive && $transaction->tax_rate > 0 ? (float) $subscription->price : (float) $subscription->total;
                            $invoice_items[] = $this->build_invoice_item($amount, $transaction, $product, $customer_id);
                        }
                    }
                }

                // Use a SetupIntent for free trials, and create the subscription via webhook later.
                if ($sub->trial && $sub->trial_days > 0 && (float) $sub->trial_total <= 0.00 && empty($invoice_items)) {
                    $setup_intent = $this->create_setup_intent($customer_id, $txn, $prd);

                    $client_secret = $setup_intent->client_secret;

                    $txn->trans_num = $setup_intent->id;
                    $txn->store();

                    $action = 'confirmSetup';
                } else {
                    $trial_days = 0;

                    if ($sub->trial && $sub->trial_days > 0) {
                        $trial_days = $sub->trial_days;

                        if ((float) $sub->trial_total > 0.00) {
                            $amount = $calculate_taxes && !$tax_inclusive && $txn->tax_rate > 0 ? (float) $sub->trial_amount : (float) $sub->trial_total;
                            array_unshift($invoice_items, $this->build_invoice_item($amount, $txn, $prd, $customer_id));
                        }
                    } elseif (count($invoice_items)) {
                        // If there is no trial period and there is an order bump, set the trial days to cover one payment cycle and
                        // add the first subscription payment to the trial amount.
                        $now        = new DateTimeImmutable('now');
                        $end        = $now->modify(sprintf('+%d %s', $sub->period, $sub->period_type));
                        $trial_days = $end->diff($now)->format('%a');

                        $amount = $calculate_taxes && !$tax_inclusive && $txn->tax_rate > 0 ? (float) $sub->price : (float) $sub->total;

                        array_unshift($invoice_items, $this->build_invoice_item($amount, $txn, $prd, $customer_id));
                    }

                    $invoice_item_ids = [];

                    foreach ($invoice_items as $invoice_item) {
                        $invoice_item       = (object) $this->send_stripe_request('invoiceitems', $invoice_item, 'post');
                        $invoice_item_ids[] = $invoice_item->id;
                    }

                    try {
                        $subscription = $this->create_subscription($txn, $sub, $prd, $customer_id, null, true, $trial_days);

                        if (empty($subscription->latest_invoice['payment_intent']['client_secret'])) {
                            throw new MeprGatewayException(__('PaymentIntent not found', 'memberpress'));
                        }

                        $client_secret = $subscription->latest_invoice['payment_intent']['client_secret'];

                        $sub->subscr_id = $subscription->id;
                        $sub->store();

                        $txn->trans_num = $subscription->latest_invoice['id'];
                        $txn->store();
                    } catch (Exception $e) {
                        // Delete any created invoice items if the subscription failed to be created.
                        foreach ($invoice_item_ids as $invoice_item_id) {
                            try {
                                $this->send_stripe_request("invoiceitems/$invoice_item_id", [], 'delete');
                            } catch (Exception $f) {
                                // Ignore any exception here, throw the original.
                            }
                        }

                        throw $e;
                    }
                }
            }

            MeprHooks::do_action('mepr_stripe_payment_pending', $txn, $usr);

            $return_url = add_query_arg(
                [
                    'txn_id'      => $txn->id,
                    'redirect_to' => urlencode($mepr_options->thankyou_page_url($thank_you_page_args)),
                ],
                $this->notify_url('return')
            );

            wp_send_json_success([
                'action'        => $action,
                'client_secret' => $client_secret,
                'return_url'    => $return_url,
            ]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing

    /**
     * Is this gateway configured to support Ajax processing?
     *
     * @return boolean
     */
    public function validate_ajax_gateway(): bool
    {
        return $this->settings->stripe_checkout_enabled != 'on';
    }

    /**
     * Updates the metadata for a charge using the provided transaction object.
     *
     * This method performs the update asynchronously by enqueuing a job to update
     * the charge metadata in the Stripe gateway.
     *
     * @param MeprTransaction $txn The transaction object containing the details
     * of the transaction, including the charge ID.
     *
     * @return void
     */
    public function update_charge_metadata(MeprTransaction $txn)
    {
        if (self::is_charge_object_id($txn->trans_num)) {
            // Update Stripe Metadata Asynchronously.
            $job                   = new MeprUpdateStripeMetadataJob();
            $job->gateway_settings = $this->settings;
            $job->transaction_id   = $txn->id;
            $job->enqueue();
        }
    }

    /**
     * Get the account country for the given payment method ID
     *
     * @param  string $pm_id The payment method ID.
     * @return string|false  The account country or false if not found
     */
    public static function get_account_country($pm_id)
    {
        if (false === $pm_id) {
            return false;
        }
        $mepr_options = MeprOptions::fetch();
        $pm = $mepr_options->payment_method($pm_id);

        if (false === $pm) {
            return false;
        }

        $account_country = false;
        try {
            $account_details = $pm->send_stripe_request('account', [], 'get');
            $account_country = $account_details['country'] ?? false;
        } catch (Exception $e) {
            // Do nothing.
        }

        return $account_country;
    }
}
