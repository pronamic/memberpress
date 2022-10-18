<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprStripeGateway extends MeprBaseRealGateway {
  const STRIPE_API_VERSION = '2022-08-01';

  /** Used in the view to identify the gateway */
  public function __construct() {
    $this->name = 'Stripe';
    $this->icon = MEPR_IMAGES_URL . '/checkout/cards.png';
    $this->desc = __('Pay with your credit card via Stripe', 'memberpress');
    $this->key = __('stripe', 'memberpress');
    $this->set_defaults();
    $this->has_spc_form = true;

    $this->capabilities = array(
      'process-credit-cards',
      'process-payments',
      'process-refunds',
      'create-subscriptions',
      'cancel-subscriptions',
      'update-subscriptions',
      'suspend-subscriptions',
      'resume-subscriptions',
      'send-cc-expirations',
      'subscription-trial-payment'
    );

    // Setup the notification actions for this gateway
    $this->notifiers = array(
      'whk' => 'listener',
      'stripe-service-whk' => 'service_listener',
      'update-billing.html' => 'churn_buster'
    );
  }

  public function load($settings) {
    $this->settings = (object)$settings;
    $this->set_defaults();
  }

  protected function set_defaults() {
    if(!isset($this->settings)) {
      $this->settings = array();
    }

    $this->settings = (object)array_merge(
      array(
        'gateway' => 'MeprStripeGateway',
        'id' => $this->generate_id(),
        'label' => '',
        'use_label' => true,
        'use_icon' => true,
        'use_desc' => true,
        'email' => '',
        'sandbox' => false,
        'force_ssl' => false,
        'debug' => false,
        'test_mode' => false,
        'stripe_link_enabled' => 'default',
        'stripe_checkout_enabled' => false,
        'churn_buster_enabled' => false,
        'churn_buster_uuid' => '',
        'api_keys' => array(
          'test' => array(
            'public' => '',
            'secret' => ''
          ),
          'live' => array(
            'public' => '',
            'secret' => ''
          )
        ),
        'connect_status' => false,
        'service_account_id' => '',
        'service_account_name' => '',
      ),
      (array)$this->settings
    );

    $this->id = $this->settings->id;
    $this->label = $this->settings->label;
    $this->use_label = $this->settings->use_label;
    $this->use_icon = $this->settings->use_icon;
    $this->use_desc = $this->settings->use_desc;
    $this->connect_status = $this->settings->connect_status;
    $this->service_account_id = $this->settings->service_account_id;
    $this->service_account_name = $this->settings->service_account_name;
    //$this->recurrence_type = $this->settings->recurrence_type;

    if($this->is_test_mode()) {
      $this->settings->public_key = trim($this->settings->api_keys['test']['public']);
      $this->settings->secret_key = trim($this->settings->api_keys['test']['secret']);
    }
    else {
      $this->settings->public_key = trim($this->settings->api_keys['live']['public']);
      $this->settings->secret_key = trim($this->settings->api_keys['live']['secret']);
    }
  }

  /**
   * @param $subscription MeprSubscription
   *
   * @return bool
   */
  protected function hide_update_link($subscription) {
    if ($subscription->status === MeprSubscription::$suspended_str) {
      return true;
    }

    return false;
  }

  /**
   * @param $name
   * @param integer $rate
   * @param MeprProduct $product
   * @param $inclusive
   *
   * @return object
   * @throws MeprHttpException
   * @throws MeprRemoteException
   */
  public function get_stripe_tax_rate_id($name, $rate, $product, $inclusive = false)
  {
    $tax_request = [
      'inclusive' => 'false',
      'percentage' => MeprUtils::format_float((float) $rate, 3),
    ];

    if (empty($name)) {
      $tax_request['display_name'] = "Tax";
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

  public function get_available_methods_for_recurring_payment() {
    $methods = [
      'card',
    ];

    $mepr_option = MeprOptions::fetch();
    $currency = strtolower($mepr_option->currency_code);

    if ($currency === 'eur') {
      $methods = array_merge($methods, [
        'sepa_debit',
      ]);
    }

    $methods = MeprHooks::apply_filters('mepr-stripe-checkout-methods-for-recurring-payment', $methods);

    return $methods;
  }

  public function get_available_methods_for_onetime_payment() {
    $methods = [
      'card',
    ];

    $mepr_option = MeprOptions::fetch();
    $currency = strtolower($mepr_option->currency_code);

    if ($currency === 'eur') {
      $methods = array_merge($methods, [
        'sepa_debit',
        'ideal',
        'bancontact',
        'giropay',
        'sofort',
      ]);
    }

    if ($currency === 'eur' || $currency === 'pln') {
      $methods[] = 'p24';
    }

    $methods = MeprHooks::apply_filters('mepr-stripe-checkout-methods-for-onetime-payment', $methods);

    return $methods;
  }

  /**
   * Create a checkout.session object that will be used to redirect user to
   * checkout.stripe.com
   *
   * @param MeprTransaction $txn
   * @param MeprSubscription $sub
   * @param MeprProduct $product
   * @param MeprUser $usr
   * @throws Exception
   */
  public function create_checkout_session(
      $txn,
      $product,
      $usr,
      $sub = null
  ) {
    $mepr_options = MeprOptions::fetch();
    $current_user = MeprUtils::get_currentuserinfo();
    $calculate_taxes = get_option('mepr_calculate_taxes') == true ? true : false;

    if($mepr_options->attr('tax_calc_type') == 'inclusive' && $calculate_taxes && $txn->tax_rate > 0) {
      $tax_inclusive = true;
    } else {
      $tax_inclusive = false;
    }

    $thankyou_page_args = [
      'membership' => sanitize_title($product->post_title),
      'transaction_id' => $txn->id,
      'membership_id' => $product->ID,
    ];

    if($sub instanceof MeprSubscription) {
      $thankyou_page_args = array_merge($thankyou_page_args, ['subscription_id' => $sub->id]);
    }

    $success_url = $mepr_options->thankyou_page_url($thankyou_page_args);
    $cancel_url = esc_url_raw(strtok($_POST['mepr_current_url'], "#"));

    if (empty($cancel_url)) {
      $cancel_url = home_url() . $_SERVER["REQUEST_URI"];
    }

    $customer_id = $this->get_customer_id($usr);
    $price_id = $this->get_stripe_price_id($sub, $product, $product->adjusted_price());
    $stripe_product_id = $this->get_product_id($product);
    $coupon = $txn->coupon();

    if($coupon instanceof MeprCoupon) {
      $discount_amount = $this->get_coupon_discount_amount($coupon, $product, $txn->tax_rate);

      if ($discount_amount > 0 && $coupon->discount_mode != 'first-payment') {
        if ($tax_inclusive) {
          if ($coupon->discount_type != 'percent') {
            $stripe_coupon_id = $this->get_coupon_id( $coupon, $this->to_zero_decimal_amount($coupon->get_discount_amount( $product ) ));
          } else {
            $stripe_coupon_id = $this->get_coupon_id( $coupon, $coupon->get_discount_amount( $product ) );
          }
        } else {
          $stripe_coupon_id = $this->get_coupon_id( $coupon, $discount_amount );
        }
      }

      if($product->is_one_time_payment() && $coupon->discount_mode == 'first-payment' && $coupon->get_first_payment_discount_amount($product) > 0) {
        if ($coupon->first_payment_discount_type  != 'percent') {
          $stripe_coupon_id = $this->get_coupon_id($coupon, $this->to_zero_decimal_amount($coupon->get_first_payment_discount_amount($product)), true);
        } else {
          $stripe_coupon_id = $this->get_coupon_id($coupon, $coupon->get_first_payment_discount_amount($product), true);
        }
      }

      if(
          !$product->is_one_time_payment() &&
          (
            ($coupon->discount_mode == 'first-payment' && $coupon->get_first_payment_discount_amount($product) > 0) ||
            ($coupon->discount_mode == 'trial-override' && $coupon->get_discount_amount($product) > 0)
          )
      ) {
          $tmp_sub = new MeprSubscription();
          $tmp_sub->id = 0;
          $tmp_sub->user_id = (isset($current_user->ID))?$current_user->ID:0;
          $tmp_sub->load_product_vars($product, null,true);
          $tmp_sub->maybe_prorate();
          $sub->trial_amount = $tmp_sub->price;

          if ($coupon->get_discount_amount($product) > 0) {
            $tmp_coupon = new MeprCoupon();
            $tmp_coupon->discount_amount = $coupon->get_discount_amount($product);
            $tmp_coupon->discount_type = $coupon->discount_type;
            $price = $tmp_coupon->apply_discount($product->price, false, $product);
            $price_id = $this->get_stripe_price_id($sub, $product, $price);
          }
      }
    }

    if ($txn->tax_rate > 0 && $calculate_taxes) {
      $tax_rate_id = $this->get_stripe_tax_rate_id($txn->tax_desc, $txn->tax_rate, $product, $tax_inclusive);
    }
    if (!$product->is_one_time_payment()) {
      $checkout_session = [
        'customer'=> $customer_id,
        'payment_method_types' => $this->get_available_methods_for_recurring_payment(),
        'line_items' => [[
          'price' => $price_id,
          'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
        'metadata' => [
          'memberpress_subscription_id' => $sub->id,
        ]
      ];
    } else {
      $checkout_session = [
        'payment_method_types' => $this->get_available_methods_for_onetime_payment(),
        'customer'=> $customer_id,
        'line_items' => [[
          'price' => $price_id,
          'quantity' => 1,
        ]],
        'mode' => 'payment',
        'metadata' => [
          'memberpress_transaction_id' => $txn->id,
        ],
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
      ];
    }

    if ($sub instanceof MeprSubscription && $sub->trial > 0) {
      $checkout_session['subscription_data'] = [
        'trial_period_days' => $sub->trial_days,
      ];

      // The section below sets the recurring price amount to the coupon discounted amount.
      // Since a Stripe coupon will also discount the trial amount, we can't use a Stripe coupon if there is a paid trial.
      if($coupon instanceof MeprCoupon && $coupon->get_discount_amount($product) > 0 && $sub->trial_total > 0) {
        $tmp_coupon = new MeprCoupon();
        $tmp_coupon->discount_amount = $coupon->get_discount_amount($product);
        $tmp_coupon->discount_type = $coupon->discount_type;
        $price = $tmp_coupon->apply_discount($product->price, false, $product);
        $checkout_session['line_items'][0]['price'] = $this->get_stripe_price_id($sub, $product, $price);
      }

      if ($tax_inclusive) {
        $trial_amount = $sub->trial_total;
      } else {
        $trial_amount = $sub->trial_total - $sub->trial_tax_amount;
      }

      $trial_amount = (self::is_zero_decimal_currency())?MeprUtils::format_float(($trial_amount), 0):MeprUtils::format_float(($trial_amount * 100), 0);
      $trial_plan = [
        'quantity' => 1,
        'price_data' => [
          'currency' => $mepr_options->currency_code,
          'product' => $this->get_product_id($product, true),
          'unit_amount' => $trial_amount,
        ]
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
          [ 'coupon' => $stripe_coupon_id ],
        ];
      }

      if(
        !$product->is_one_time_payment() &&
        (
          ($coupon->discount_mode == 'first-payment' && $coupon->get_first_payment_discount_amount($product) > 0) ||
          ($coupon->discount_mode == 'trial-override' && $coupon->get_discount_amount($product) > 0)
        )
      ) {
        unset($checkout_session['discounts']);
      }
    }

    // active product to use in stripe checkout
    $this->send_stripe_request('products/' . $stripe_product_id, ['active' => 'true']);

    $checkout_session = MeprHooks::apply_filters('mepr_stripe_checkout_session_args', $checkout_session, $txn, $sub);

    $result = $this->send_stripe_request('checkout/sessions', $checkout_session, 'post');
    $result['public_key'] = $this->settings->public_key;
    wp_send_json( $result );
  }

  /**
   * Convert the given amount to a zero-decimal Stripe amount
   *
   * @param  float  $amount
   * @return string
   */
  public function to_zero_decimal_amount($amount) {
    return self::is_zero_decimal_currency() ? MeprUtils::format_float($amount, 0) : MeprUtils::format_float(($amount * 100), 0);
  }

  /**
   * Create a PaymentIntent via the Stripe API
   *
   * @param  MeprTransaction     $txn         The MemberPress transaction
   * @param  MeprProduct         $prd         The MemberPress product
   * @param  string              $customer_id The Stripe Customer ID
   * @return stdClass                         The Stripe PaymentIntent data
   * @throws MeprHttpException                If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException              If there was an invalid or error response from Stripe
   */
  public function create_payment_intent(MeprTransaction $txn, MeprProduct $prd, $customer_id) {
    $mepr_options = MeprOptions::fetch();

    $args = MeprHooks::apply_filters('mepr_stripe_payment_intent_args', [
      'amount' => $this->to_zero_decimal_amount($txn->total),
      'currency' => $mepr_options->currency_code,
      'customer' => $customer_id,
      'payment_method_types' => MeprHooks::apply_filters('mepr_stripe_payment_intent_payment_method_types', $this->is_stripe_link_enabled() ? ['card', 'link'] : ['card'], $txn, $prd),
      'setup_future_usage' => 'off_session', // Required to allow rebills to use this card
      'description' => $prd->post_title,
      'metadata' => [
        'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
        'site_url' => get_site_url(),
        'ip_address' => $_SERVER['REMOTE_ADDR']
      ],
    ], $txn);

    $payment_intent = (object) $this->send_stripe_request('payment_intents', $args, 'post');

    return $payment_intent;
  }

  /**
   * Process a successful one-time payment
   *
   * @param  MeprTransaction $txn            The MemberPress transaction
   * @param  stdClass        $payment_intent The Stripe PaymentIntent data
   * @return bool|void
   */
  public function handle_one_time_payment(MeprTransaction $txn, $payment_intent) {
    // Get the Charge from the PaymentIntent
    $charge = (object) $payment_intent->charges['data'][0];

    $txn->trans_num = $charge->id;
    $txn->store();

    $_REQUEST['data'] = $charge;

    return $this->record_payment();
  }

  public function process_payment_form($txn) {
    if(isset($_REQUEST['mepr_payment_methods_hidden']) && $txn->amount == 0.00) {
      MeprTransaction::create_free_transaction($txn);
    }
    else {
      throw new MeprGatewayException(__('Payment was unsuccessful, please check your payment details and try again.', 'memberpress'));
    }
  }

  /** Used to send data to a given payment gateway. In gateways which redirect
    * before this step is necessary this method should just be left blank.
    */
  public function process_payment($txn) {
    // Not used
  }

  /** Used to record a successful recurring payment by the given gateway. It
    * should have the ability to record a successful payment or a failure. It is
    * this method that should be used when receiving an IPN from PayPal or a
    * Silent Post from Authorize.net.
    */
  public function record_subscription_payment() {
    if(isset($_REQUEST['data'])) {
      $charge = (object) $_REQUEST['data'];

      if(!isset($charge, $charge->id, $charge->customer)) {
        return false;
      }

      $subscription = isset($_REQUEST['subscription']) ? (object) $_REQUEST['subscription'] : null;
      $invoice = isset($_REQUEST['invoice']) ? (object) $_REQUEST['invoice'] : null;
      $sub = isset($subscription, $subscription->id) ? MeprSubscription::get_one_by_subscr_id($subscription->id) : null;

      if($sub instanceof MeprSubscription) {
        $subscr_id = $subscription->id;
      } else {
        // Look for an old cus_xxx subscription
        $sub = MeprSubscription::get_one_by_subscr_id($charge->customer);
        $subscr_id = $charge->customer;
      }

      // Make sure there's a valid subscription for this request and this payment hasn't already been recorded
      if(!($sub instanceof MeprSubscription) || MeprTransaction::txn_exists($charge->id)) {
        return false;
      }

      //If this isn't for us, bail
      if($sub->gateway != $this->id) { return false; }

      $first_txn = $sub->first_txn();

      if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
        $coupon_id = $sub->coupon_id;
      }
      else {
        $coupon_id = $first_txn->coupon_id;
      }

      $txn = new MeprTransaction();
      $txn->user_id    = $sub->user_id;
      $txn->product_id = $sub->product_id;
      $txn->status     = MeprTransaction::$complete_str;
      $txn->coupon_id  = $coupon_id;
      $txn->trans_num  = $charge->id;
      $txn->gateway    = $this->id;
      $txn->subscription_id = $sub->id;

      // If this is the first payment for a paid trial, expire the transaction after the trial ends.
      // The actual number of trial days can be different from $sub->trial_days, for example when resuming, so we'll
      // calculate the trial days from the Stripe subscription data.
      if($subscription &&
        $subscription->status == 'trialing' &&
        isset($invoice->billing_reason) &&
        $invoice->billing_reason == 'subscription_create' &&
        is_numeric($subscription->trial_start) &&
        is_numeric($subscription->trial_end)
      ) {
        $trial_days = ($subscription->trial_end - $subscription->trial_start) / MeprUtils::days(1);

        if($trial_days > 0) {
          $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($trial_days), 'Y-m-d 23:59:59');
        }
      }

      if(self::is_zero_decimal_currency()) {
        $txn->set_gross((float)$charge->amount);
      }
      else {
        $txn->set_gross((float)$charge->amount / 100);
      }

      $txn->store();

      // Reload the subscription in case it was modified while storing the transaction
      $sub = new MeprSubscription($sub->id);

      // Needs to be here to get around some funky GoDaddy caching issue
      $sub->subscr_id = $subscr_id;

      $this->email_status( "record_subscription_payment:" .
        "\nSubscription: " . MeprUtils::object_to_string($sub) .
        "\nTransaction: " . MeprUtils::object_to_string($first_txn),
        $this->settings->debug);

      // Update Stripe Metadata Asynchronously
      $job = new MeprUpdateStripeMetadataJob();
      $job->gateway_settings = $this->settings;
      $job->transaction_id = $txn->id;
      $job->enqueue();

      $sub->status = MeprSubscription::$active_str;

      $payment_method = isset($_REQUEST['payment_method']) ? (object) $_REQUEST['payment_method'] : null;

      if($payment_method && isset($payment_method->card) && is_array($payment_method->card)) {
        $sub->cc_last4 = $payment_method->card['last4'];
        $sub->cc_exp_month = $payment_method->card['exp_month'];
        $sub->cc_exp_year = $payment_method->card['exp_year'];
      }
      elseif($card = $this->get_card($charge)) {
        $sub->cc_exp_month = $card['exp_month'];
        $sub->cc_exp_year  = $card['exp_year'];
        $sub->cc_last4     = $card['last4'];
      }

      $sub->gateway = $this->id;
      $sub->store();
      // If a limit was set on the recurring cycles we need
      // to cancel the subscr if the txn_count >= limit_cycles_num
      // This is not possible natively with Stripe so we
      // just cancel the subscr when limit_cycles_num is hit
      $sub->limit_payment_cycles();

      $this->email_status( "Subscription Transaction\n" .
                           MeprUtils::object_to_string($txn->rec),
                           $this->settings->debug );

      MeprUtils::send_transaction_receipt_notices( $txn );
      MeprUtils::send_cc_expiration_notices( $txn );

      return $txn;
    }

    return false;
  }

  /**
   * Record a subscription payment that doesn't have an associated charge
   *
   * Called when the invoice payment was 0.00, which can happen if the subscription amount is less than
   * the Stripe minimum payment. We want to record these as subscription payments unless it's the first "payment"
   * of a free trial.
   *
   * @return MeprTransaction|false The created transaction or false if no transaction was created
   */
  public function record_subscription_free_invoice_payment() {
    if(isset($_REQUEST['invoice'], $_REQUEST['invoice']->id)) {
      $invoice = (object) $_REQUEST['invoice'];

      $subscription = isset($_REQUEST['subscription']) ? (object) $_REQUEST['subscription'] : null;
      $sub = isset($subscription, $subscription->id) ? MeprSubscription::get_one_by_subscr_id($subscription->id) : null;

      if(!($sub instanceof MeprSubscription)) {
        // Look for an old cus_xxx subscription
        $sub = isset($invoice->customer['id']) ? MeprSubscription::get_one_by_subscr_id($invoice->customer['id']) : null;
      }

      // Make sure there's a valid subscription for this request and this payment hasn't already been recorded
      if(!($sub instanceof MeprSubscription) || MeprTransaction::txn_exists($invoice->id)) {
        return false;
      }

      // If this is the first invoice "payment" for a free trial, there is no need to create an extra confirmation txn
      if($invoice->billing_reason == 'subscription_create' && $sub->trial && $sub->trial_amount <= 0.00) {
        return false;
      }

      $txn = new MeprTransaction();
      $txn->user_id = $sub->user_id;
      $txn->product_id = $sub->product_id;
      $txn->status = MeprTransaction::$confirmed_str;
      $txn->txn_type = MeprTransaction::$subscription_confirmation_str;
      $txn->trans_num = $invoice->id;
      $txn->gateway = $this->id;
      $txn->subscription_id = $sub->id;
      $txn->set_subtotal(0.00); // Just a confirmation txn

      // If this is the first invoice "payment" for a paid trial, expire the transaction after the trial ends.
      // The actual number of trial days can be different from $sub->trial_days, for example when resuming, so we'll
      // calculate the trial days from the Stripe subscription data.
      if($subscription &&
        $subscription->status == 'trialing' &&
        isset($invoice->billing_reason) &&
        $invoice->billing_reason == 'subscription_create' &&
        is_numeric($subscription->trial_start) &&
        is_numeric($subscription->trial_end)
      ) {
        $trial_days = ($subscription->trial_end - $subscription->trial_start) / MeprUtils::days(1);

        if($trial_days > 0) {
          $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($trial_days), 'Y-m-d 23:59:59');
        }
      }

      $txn->store();

      return $txn;
    }

    return false;
  }

  /**
   * Handle the Stripe `invoice.payment_succeeded` webhook
   *
   * @param  stdClass            $invoice The Stripe Invoice data
   * @throws MeprHttpException            If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException          If there was an invalid or error response from Stripe
   */
  public function handle_invoice_payment_succeeded_webhook($invoice) {
    $this->email_status('Stripe handle_invoice_payment_succeeded_webhook response: ' . MeprUtils::object_to_string($invoice), $this->settings->debug);

    // Fetch expanded invoice data from Stripe
    $args = MeprHooks::apply_filters('mepr_stripe_handle_invoice_payment_succeeded_webhook_args', array(
      'expand' => array(
        'customer',
        'charge',
        'payment_intent',
        'payment_intent.payment_method',
        'subscription',
        'subscription.default_payment_method'
      )
    ), $invoice);

    $invoice = (object) $this->send_stripe_request("invoices/{$invoice->id}", $args, 'get');

    $_REQUEST['invoice'] = $invoice;
    $_REQUEST['subscription'] = (object) $invoice->subscription;

    if(isset($invoice->payment_intent['payment_method'])) {
      $_REQUEST['payment_method'] = (object) $invoice->payment_intent['payment_method'];
    }
    elseif(isset($invoice->subscription['default_payment_method'])) {
      // For free trials, get the payment method from the subscription
      $_REQUEST['payment_method'] = (object) $invoice->subscription['default_payment_method'];
    }

    if($invoice->billing_reason == 'subscription_create') {
      $_REQUEST['data'] = (object) $invoice->customer;

      if(isset($invoice->customer['invoice_settings']) && is_array($invoice->customer['invoice_settings'])) {
        $default_payment_method = isset($invoice->customer['invoice_settings']['default_payment_method']) ? $invoice->customer['invoice_settings']['default_payment_method'] : null;
        $subscription_payment_method = isset($invoice->subscription['default_payment_method']['id']) ? $invoice->subscription['default_payment_method']['id'] : null;

        if(empty($default_payment_method) && !empty($subscription_payment_method)) {
          $this->set_customer_default_payment_method($invoice->customer['id'], $subscription_payment_method);
        }
      }

      $this->record_create_subscription();
    }

    if(isset($invoice->charge)) {
      $_REQUEST['data'] = (object) $invoice->charge;

      $this->record_subscription_payment();
    }
    else {
      $this->record_subscription_free_invoice_payment();
    }
  }

  /**
   * Handle the invoice.payment_failed webhook
   *
   * Sends an email to the customer to pay the outstanding invoice.
   *
   * @param stdClass $invoice The Stripe Invoice object
   */
  public function handle_invoice_payment_failed_webhook($invoice) {
    try {
      $email = MeprEmailFactory::fetch('MeprUserStripeInvoiceEmail');

      if($email->enabled() && $invoice->hosted_invoice_url) {
        $sub = MeprSubscription::get_one_by_subscr_id($invoice->subscription);

        if(!($sub instanceof MeprSubscription)) {
          // Look for an old cus_xxx subscription
          $sub = MeprSubscription::get_one_by_subscr_id($invoice->customer);
        }

        if($sub instanceof MeprSubscription && $sub->id > 0 && $sub->txn_count > 1) {
          $usr = $sub->user();

          if($usr->ID > 0) {
            $email->to = $usr->formatted_email();

            $params = array_merge(
              MeprSubscriptionsHelper::get_email_params($sub),
              array('stripe_invoice_url' => $invoice->hosted_invoice_url)
            );

            $email->send($params);
          }
        }
      }
    } catch (Exception $e) {
      // Fail silently
    }
  }

  /**
   * Handle the setup_intent.succeeded webhook
   *
   * Creates a free trial Stripe Subscription from a successful SetupIntent.
   *
   * @param stdClass $setup_intent
   */
  public function handle_setup_intent_succeeded_webhook($setup_intent) {
    $txn_res = MeprTransaction::get_one_by_trans_num($setup_intent->id);

    if(is_object($txn_res) && isset($txn_res->id)) {
      $txn = new MeprTransaction($txn_res->id);
      $sub = $txn->subscription();

      if($sub instanceof MeprSubscription) {
        if($sub->status == MeprSubscription::$active_str) {
          return;
        }

        if(!($sub->trial && $sub->trial_days > 0 && (float) $sub->trial_amount <= 0.00)) {
          return; // This only handles free trials
        }

        if(empty($setup_intent->payment_method) || empty($setup_intent->customer)) {
          return;
        }

        try {
          $subscription = $this->create_subscription($txn, $sub, $txn->product(), $setup_intent->customer, $setup_intent->payment_method, false);

          $sub->subscr_id = $subscription->id;
          $sub->store();

          $txn->trans_num = $subscription->id;
          $txn->store();
        }
        catch(Exception $e) {
          http_response_code(202);
          die($e->getMessage());
        }
      }
    }
  }

  /** Used to record a declined payment. */
  public function record_payment_failure() {
    if(isset($_REQUEST['data'])) {
      $charge = (object) $_REQUEST['data'];
      $txn_res = MeprTransaction::get_one_by_trans_num($charge->id);

      if(is_object($txn_res) and isset($txn_res->id)) {
        $txn = new MeprTransaction($txn_res->id);
        $txn->status = MeprTransaction::$failed_str;
        $txn->store();
      }
      else {
        // Fetch expanded charge data from Stripe
        $args = [
          'expand' => [
            'invoice'
          ]
        ];

        $charge = (object) $this->send_stripe_request("charges/{$charge->id}", $args, 'get');

        $sub = isset($charge->invoice['subscription']) ? MeprSubscription::get_one_by_subscr_id($charge->invoice['subscription']) : null;

        if(!($sub instanceof MeprSubscription)) {
          // Look for an old cus_xxx subscription
          $sub = isset($charge->customer) ? MeprSubscription::get_one_by_subscr_id($charge->customer) : null;
        }

        if($sub instanceof MeprSubscription) {
          $first_txn = $sub->first_txn();

          if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
            $coupon_id = $sub->coupon_id;
          }
          else {
            $coupon_id = $first_txn->coupon_id;
          }

          $txn = new MeprTransaction();
          $txn->user_id = $sub->user_id;
          $txn->product_id = $sub->product_id;
          $txn->coupon_id = $coupon_id;
          $txn->txn_type = MeprTransaction::$payment_str;
          $txn->status = MeprTransaction::$failed_str;
          $txn->subscription_id = $sub->id;
          $txn->trans_num = $charge->id;
          $txn->gateway = $this->id;

          if(self::is_zero_decimal_currency()) {
            $txn->set_gross((float)$charge->amount);
          }
          else {
            $txn->set_gross((float)$charge->amount / 100);
          }

          $txn->store();

          // Reload the subscription in case it was modified while storing the transaction
          $sub = new MeprSubscription($sub->id);

          //If first payment fails, Stripe will not set up the subscription, so we need to mark it as cancelled in MP
          if($sub->txn_count == 0 && !($sub->trial && $sub->trial_amount == 0.00)) {
            $sub->status = MeprSubscription::$cancelled_str;
          }

          $sub->gateway = $this->id;
          $sub->expire_txns(); //Expire associated transactions for the old subscription
          $sub->store();
        }
        else {
          return false; // Nothing we can do here ... so we outta here
        }
      }

      MeprUtils::send_failed_txn_notices($txn);

      return $txn;
    }

    return false;
  }

  /** Used to record a successful payment by the given gateway. It should have
    * the ability to record a successful payment or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_payment($charge = null) {
    $this->email_status( "Starting record_payment: " . MeprUtils::object_to_string($_REQUEST), $this->settings->debug );

    if (empty($charge)) {
      $charge = isset($_REQUEST['data']) ? (object)$_REQUEST['data'] : [];
    } else {
      $charge = (object)$charge;
    }

    if(!empty($charge)) {
      $this->email_status("record_payment: \n" . MeprUtils::object_to_string($charge, true) . "\n", $this->settings->debug);
      $obj = MeprTransaction::get_one_by_trans_num($charge->id);

      if(is_object($obj) and isset($obj->id)) {
        $txn = new MeprTransaction();
        $txn->load_data($obj);
        $usr = $txn->user();

        // Just short circuit if the txn has already completed
        if($txn->status == MeprTransaction::$complete_str)
          return;

        $txn->status    = MeprTransaction::$complete_str;
        // This will only work before maybe_cancel_old_sub is run
        $upgrade = $txn->is_upgrade();
        $downgrade = $txn->is_downgrade();

        $event_txn = $txn->maybe_cancel_old_sub();
        $txn->store();

        $this->email_status("Standard Transaction\n" . MeprUtils::object_to_string($txn->rec, true) . "\n", $this->settings->debug);

        $prd = $txn->product();

        if( $prd->period_type=='lifetime' ) {
          if( $upgrade ) {
            $this->upgraded_sub($txn, $event_txn);
          }
          else if( $downgrade ) {
            $this->downgraded_sub($txn, $event_txn);
          }
          else {
            $this->new_sub($txn);
          }

          MeprUtils::send_signup_notices( $txn );
        }

        MeprUtils::send_transaction_receipt_notices( $txn );
        MeprUtils::send_cc_expiration_notices( $txn );
      }
    }

    return false;
  }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function process_refund(MeprTransaction $txn) {
    $args = MeprHooks::apply_filters('mepr_stripe_refund_args', array(), $txn);
    $refund = (object)$this->send_stripe_request( "charges/{$txn->trans_num}/refund", $args );
    $this->email_status( "Stripe Refund: " . MeprUtils::object_to_string($refund), $this->settings->debug );
    $_REQUEST['data'] = $refund;
    return $this->record_refund();
  }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function record_refund() {
    if(isset($_REQUEST['data']))
    {
      $charge = (object)$_REQUEST['data'];
      $obj = MeprTransaction::get_one_by_trans_num($charge->id);

      if(!is_null($obj) && (int)$obj->id > 0) {
        $txn = new MeprTransaction($obj->id);

        // Seriously ... if txn was already refunded what are we doing here?
        if($txn->status == MeprTransaction::$refunded_str) { return $txn->id; }

        $txn->status = MeprTransaction::$refunded_str;
        $txn->store();

        MeprUtils::send_refunded_txn_notices($txn);

        return $txn->id;
      }
    }

    return false;
  }

  public function process_trial_payment($txn) {
    // Not used
  }

  public function record_trial_payment($txn) {
    // Not used
  }

  public function process_stripe_checkout_session_completed($event) {
    $data = $event->data['object'];
    $stripe_subscription_id = isset($data['subscription']) && !empty($data['subscription']) ? $data['subscription'] : null;
    $metadata = $data['metadata'];

    if ($data['mode'] == 'payment') {
      // Processing one time payment notification

      if (isset($metadata['memberpress_transaction_id'])) {
        $_REQUEST['data'] = $data;
        $payment_intent_id = $data['payment_intent'];
        $payment_intent = $this->retrieve_payment_intent($payment_intent_id);
        $charges = $payment_intent->charges['data'];
        $charge = end($charges);

        /** @var MeprTransaction $txn */
        $txn = new MeprTransaction($metadata['memberpress_transaction_id']);
        $txn->trans_num = $charge['id'];
        $txn->store();
        $this->record_payment($charge);
        MeprHooks::do_action('mepr-signup', $txn);
        return;
      }
    }

    if (empty($stripe_subscription_id)) {
      return;
    }

    if (!isset($metadata['memberpress_subscription_id'])) {
      return;
    }

    $memberpress_subscription_id = $metadata['memberpress_subscription_id'];

    $stripe_subscription = (object) $this->send_stripe_request('subscriptions/' . $stripe_subscription_id, [], 'get');

    if ($stripe_subscription->status == 'active' or $stripe_subscription->status == 'trialing') {
      $memberpress_subscription = new MeprSubscription($memberpress_subscription_id);

      if ($memberpress_subscription->gateway && $memberpress_subscription->gateway == $this->id) {
        $memberpress_subscription->subscr_id = $stripe_subscription->id;
        $first_txn = $memberpress_subscription->first_txn();

        if ($first_txn instanceof MeprTransaction) {
          $this->activate_subscription($first_txn, $memberpress_subscription);
          MeprHooks::do_action('mepr-signup', $first_txn);
        }

        $memberpress_subscription->store();
      }
    }
  }

  /** Used to send subscription data to a given payment gateway. In gateways
    * which redirect before this step is necessary this method should just be
    * left blank.
    */
  public function process_create_subscription($txn) {
    // Not used
  }

  /** Used to record a successful subscription by the given gateway. It should have
    * the ability to record a successful subscription or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_create_subscription() {
    if(isset($_REQUEST['data'])) {
      $customer = (object) $_REQUEST['data'];
      $subscription = isset($_REQUEST['subscription']) ? (object) $_REQUEST['subscription'] : null;
      $payment_method = isset($_REQUEST['payment_method']) ? (object) $_REQUEST['payment_method'] : null;
      $sub = isset($subscription, $subscription->id) ? MeprSubscription::get_one_by_subscr_id($subscription->id) : null;

      if(!($sub instanceof MeprSubscription)) {
        // Look for an old cus_xxx subscription
        $sub = MeprSubscription::get_one_by_subscr_id($customer->id);
      }

      // Skip if the subscription was not found
      if($sub instanceof MeprSubscription) {
        $txn = $sub->first_txn();
        if($txn == false || !($txn instanceof MeprTransaction)) {
          $txn = new MeprTransaction();
          $txn->user_id = $sub->user_id;
          $txn->product_id = $sub->product_id;
        }

        if($payment_method && isset($payment_method->card) && is_array($payment_method->card)) {
          $sub->cc_last4 = $payment_method->card['last4'];
          $sub->cc_exp_month = $payment_method->card['exp_month'];
          $sub->cc_exp_year = $payment_method->card['exp_year'];
        }

        $this->activate_subscription($txn, $sub);

        // This will only work before maybe_cancel_old_sub is run
        $upgrade = $sub->is_upgrade();
        $downgrade = $sub->is_downgrade();

        $event_txn = $sub->maybe_cancel_old_sub();

        if ($upgrade) {
          $this->upgraded_sub($sub, $event_txn);
        } else if ($downgrade) {
          $this->downgraded_sub($sub, $event_txn);
        } else {
          $this->new_sub($sub, true);
        }

        MeprUtils::send_signup_notices($txn);

        MeprHooks::do_action('mepr_stripe_subscription_created', $txn, $sub);

        return array('subscription' => $sub, 'transaction' => $txn);
      }
    }

    return false;
  }

  public function process_update_subscription($sub_id) {
    // This is handled via Ajax
  }

  /**
   * Create a SetupIntent
   *
   * @param  string              $customer_id The Stripe Customer ID
   * @return stdClass                         The Stripe SetupIntent object
   * @throws MeprHttpException                If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException              If there was an invalid or error response from Stripe
   */
  public function create_setup_intent($customer_id) {
    $setup_intent = (object) $this->send_stripe_request('setup_intents', [
      'customer' => $customer_id,
      'payment_method_types' => MeprHooks::apply_filters('mepr_stripe_setup_intent_payment_method_types', $this->is_stripe_link_enabled() ? ['card', 'link'] : ['card']),
      'metadata' => [
        'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
        'site_url' => get_site_url(),
        'ip_address' => $_SERVER['REMOTE_ADDR'],
      ],
    ]);

    return $setup_intent;
  }

  /**
   * Update a payment method for a subscription
   *
   * @param  MeprSubscription     $sub              The MemberPress subscription
   * @param  MeprUser             $usr              The MemberPress user
   * @param  stdClass             $payment_method   The Stripe PaymentMethod data, must be attached to the customer
   * @return stdClass                               The Stripe Subscription data
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function update_subscription_payment_method(MeprSubscription $sub, MeprUser $usr, $payment_method) {
    // Attach the payment method to the customer and set this as the default payment method for the subscription
    if(strpos($sub->subscr_id, 'sub_') === 0) {
      $subscription = $this->retrieve_subscription($sub->subscr_id);
      $customer_id = $usr->get_stripe_customer_id($this->get_meta_gateway_id());

      if(!is_string($customer_id) || strpos($customer_id, 'cus_') !== 0) {
        // If the Stripe customer ID isn't saved locally for this user, let's save it. This can happen if sub_
        // subscriptions are imported and the cus_ IDs aren't imported for users.
        $usr->set_stripe_customer_id($this->get_meta_gateway_id(), $subscription->customer);
      }
    }
    else {
      $subscription = $this->get_customer_subscription($sub->subscr_id);
    }

    $this->send_stripe_request("subscriptions/{$subscription->id}", ['default_payment_method' => $payment_method->id]);

    if(MeprHooks::apply_filters('mepr_stripe_update_set_as_default', true)) {
      $this->send_stripe_request("customers/{$subscription->customer}", [
        'invoice_settings' => [
          'default_payment_method' => $payment_method->id
        ]
      ]);
    }

    // Save the card details
    if(isset($payment_method->card) && is_array($payment_method->card)) {
      $sub->cc_last4 = $payment_method->card['last4'];
      $sub->cc_exp_month = $payment_method->card['exp_month'];
      $sub->cc_exp_year = $payment_method->card['exp_year'];
    }
    else {
      $sub->cc_last4 = '';
      $sub->cc_exp_month = '';
      $sub->cc_exp_year = '';
    }

    $sub->store();

    return $subscription;
  }

  /**
   * Get the Stripe subscription for the given customer ID
   *
   * @param  string              $customer_id The Stripe customer ID
   * @return object                           The Stripe subscription object
   * @throws MeprHttpException                If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException              If there was an invalid or error response from Stripe
   */
  public function get_customer_subscription($customer_id) {
    $args = array(
      'expand' => array(
        'latest_invoice',
        'default_payment_method',
      )
    );

    $subscription = (object) $this->send_stripe_request("customers/{$customer_id}/subscription", $args, 'get');

    return $subscription;
  }

  /**
   * Retry the payment for the given invoice ID
   *
   * @param  string              $invoice_id The Stripe invoice ID
   * @throws MeprHttpException               If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException             If there was an invalid or error response from Stripe
   */
  public function retry_invoice_payment($invoice_id) {
    $this->send_stripe_request("invoices/{$invoice_id}/pay", array(), 'post');
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_update_subscription() {
    // No need for this one with stripe
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_suspend_subscription($sub_id) {
    $mepr_options = MeprOptions::fetch();
    $sub = new MeprSubscription($sub_id);

    if($sub->status == MeprSubscription::$suspended_str) {
      throw new MeprGatewayException(__('This subscription has already been paused.', 'memberpress'));
    }

    if(!MeprUtils::is_mepr_admin() && $sub->in_free_trial()) {
      throw new MeprGatewayException(__('Sorry, subscriptions cannot be paused during a free trial.', 'memberpress'));
    }

    $args = MeprHooks::apply_filters('mepr_stripe_suspend_subscription_args', array(), $sub);

    if(strpos($sub->subscr_id, 'sub_') === 0) {
      $res = $this->send_stripe_request("subscriptions/{$sub->subscr_id}", $args, 'delete');
    }
    else {
      try {
        $customer = $this->retrieve_customer($sub->subscr_id);
      }
      catch(Exception $e) {
        // If there's not already a customer then we're done here
        return false;
      }

      // Yeah ... we're cancelling here bro ... with stripe we should be able to restart again
      $res = $this->send_stripe_request( "customers/{$customer->id}/subscription", $args, 'delete' );
    }

    $_REQUEST['data'] = $res;

    return $this->record_suspend_subscription();
  }

  /** This method should be used by the class to record a successful suspension
    * from the gateway.
    */
  public function record_suspend_subscription() {
    if(isset($_REQUEST['data'])) {
      $subscription = (object) $_REQUEST['data'];

      $sub = MeprSubscription::get_one_by_subscr_id($subscription->id);

      if(!($sub instanceof MeprSubscription)) {
        // Look for an old cus_xxx subscription
        $sub = MeprSubscription::get_one_by_subscr_id($subscription->customer);
      }

      if($sub instanceof MeprSubscription) {
        // Seriously ... if sub was already cancelled what are we doing here?
        if($sub->status == MeprSubscription::$suspended_str) { return $sub; }

        $sub->status = MeprSubscription::$suspended_str;
        $sub->store();

        MeprUtils::send_suspended_sub_notices($sub);
      }
    }

    return false;
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_resume_subscription($sub_id) {
    $mepr_options = MeprOptions::fetch();
    MeprHooks::do_action('mepr-pre-stripe-resume-subscription', $sub_id); //Allow users to change the subscription programatically before resuming it
    $sub = new MeprSubscription($sub_id);

    if($sub->status == MeprSubscription::$active_str) {
      throw new MeprGatewayException(__('This subscription has already been resumed.', 'memberpress'));
    }

    $orig_trial        = $sub->trial;
    $orig_trial_days   = $sub->trial_days;
    $orig_trial_amount = $sub->trial_amount;
    $tax_inclusive = $mepr_options->attr('tax_calc_type') == 'inclusive';

    if( $sub->is_expired() and !$sub->is_lifetime() ) {
      $expiring_txn = $sub->expiring_txn();

      // if it's already expired with a real transaction
      // then we want to resume immediately
      if( $expiring_txn != false && $expiring_txn instanceof MeprTransaction &&
          $expiring_txn->status!=MeprTransaction::$confirmed_str ) {
        $sub->trial = false;
        $sub->trial_days = 0;
        $sub->trial_amount = 0.00;
        $sub->store();
      }
    }
    else {
      $sub->trial = true;
      $sub->trial_days = MeprUtils::tsdays(strtotime($sub->expires_at) - time());
      $sub->trial_amount = 0.00;
      $sub->store();
    }

    $usr = $sub->user();
    $prd = $sub->product();

    $customer_id = $usr->get_stripe_customer_id($this->get_meta_gateway_id());
    $customer_id_valid = is_string($customer_id) && strpos($customer_id, 'cus_') === 0;
    $save_customer_id = false;

    if(!$customer_id_valid) {
      // The user does not have a saved customer ID
      if(strpos($sub->subscr_id, 'cus_') === 0) {
        // For cus_ subscriptions, save the subscription ID as their customer ID
        $customer_id = $sub->subscr_id;
        $customer_id_valid = true;
        $save_customer_id = true;
      }
      else {
        // For sub_ subscriptions, look up the old subscription to find the customer ID
        $old_subscription = $this->retrieve_subscription($sub->subscr_id);

        if(!empty($old_subscription->customer) && is_string($old_subscription->customer)) {
          $customer_id = $old_subscription->customer;
          $customer_id_valid = true;
          $save_customer_id = true;
        }
      }
    }

    if($customer_id_valid) {
      $customer = $this->retrieve_customer($customer_id);

      if($customer->currency && strtolower($customer->currency) != strtolower($mepr_options->currency_code)) {
        throw new MeprGatewayException(__('The subscription is locked to a different currency. Please contact us to resume your subscription.', 'memberpress'));
      }
    }
    else {
      throw new MeprGatewayException(__('Customer not found', 'memberpress'));
    }

    // This needs to be after the currency check above
    if($save_customer_id) {
      $usr->set_stripe_customer_id($this->get_meta_gateway_id(), $customer_id);
    }

    $payment_method_id = !empty($customer->invoice_settings['default_payment_method']['id']) ? $customer->invoice_settings['default_payment_method']['id'] : null;

    if(empty($payment_method_id)) {
      $payment_method_id = $this->set_customer_default_payment_method($customer->id);
    }

    if($tax_inclusive) {
      $plan_id = $this->get_stripe_plan_id($sub, $prd, $sub->total);
      $tax_rate_id = $sub->tax_rate > 0 ? $this->get_stripe_tax_rate_id($sub->tax_desc, $sub->tax_rate, $prd, true) : null;
    }
    else {
      $plan_id = $this->get_stripe_plan_id($sub, $prd, $sub->price);
      $tax_rate_id = $sub->tax_rate > 0 ? $this->get_stripe_tax_rate_id($sub->tax_desc, $sub->tax_rate, $prd, false) : null;
    }

    $item = ['plan' => $plan_id];

    if($tax_rate_id) {
      $item['tax_rates'] = [$tax_rate_id];
    }

    $args = MeprHooks::apply_filters('mepr_stripe_resume_subscription_args', [
      'customer' => $customer->id,
      'items' => [$item],
      'expand' => [
        'latest_invoice',
      ],
      'metadata' => [
        'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
        'site_url' => get_site_url(),
        'ip_address' => $_SERVER['REMOTE_ADDR']
      ],
      'off_session' => 'true'
    ], $sub);

    // Specifically set a default_payment_method on the subscription
    if(!empty($payment_method_id)) {
      $args = array_merge(['default_payment_method' => $payment_method_id], $args);
    }

    if($sub->trial) {
      $args = array_merge(['trial_period_days' => $sub->trial_days], $args);
    }

    $subscription = (object) $this->send_stripe_request('subscriptions', $args);

    $sub->subscr_id    = $subscription->id;
    $sub->trial        = $orig_trial;
    $sub->trial_days   = $orig_trial_days;
    $sub->trial_amount = $orig_trial_amount;
    $sub->store();

    $this->email_status( "process_resume_subscription: \n" . MeprUtils::object_to_string($sub) . "\n", $this->settings->debug );

    $invoice = (object) $subscription->latest_invoice;

    if($invoice->status == 'open') {
      throw new MeprGatewayRequiresActionException(
        sprintf(
          // translators: %1$s: open link tag, %2$s: close link tag
          __('The subscription could not be resumed automatically, %1$sclick here%2$s to complete the payment.', 'memberpress'),
          sprintf('<a href="%s" target="_blank">', esc_url($invoice->hosted_invoice_url)),
          '</a>'
        )
      );
    }

    $_REQUEST['data'] = $customer;
    $_REQUEST['sub'] = $sub;

    return $this->record_resume_subscription();
  }

  /**
   * Set a default payment method on a customer
   *
   * If the $payment_method_id param is null, the first card attached to the customer will be set as the default.
   *
   * @param  string      $customer_id        The Stripe Customer ID
   * @param  string|null $payment_method_id  The Stripe PaymentMethod ID to set as the default
   * @return string|null                     The Stripe PaymentMethod ID that was set as the default
   */
  public function set_customer_default_payment_method($customer_id, $payment_method_id = null) {
    try {
      if(empty($payment_method_id)) {
        $payment_methods = (object) $this->send_stripe_request("customers/$customer_id/payment_methods", [
          'type' => 'card',
          'limit' => '1',
        ], 'get');

        if(isset($payment_methods->data[0]['id'])) {
          $payment_method_id = $payment_methods->data[0]['id'];
        }
        else {
          return null;
        }
      }

      $this->send_stripe_request("customers/$customer_id", [
        'invoice_settings' => [
          'default_payment_method' => $payment_method_id
        ]
      ]);

      return $payment_method_id;
    }
    catch(Exception $e) {
      // Ignore, not critical enough to cause an error
    }

    return null;
  }

  /** This method should be used by the class to record a successful resuming of
    * as subscription from the gateway.
    */
  public function record_resume_subscription() {
    if(isset($_REQUEST['data'], $_REQUEST['sub'])) {
      $customer = (object) $_REQUEST['data'];
      $sub = $_REQUEST['sub'];

      if($sub instanceof MeprSubscription) {
        $sub->status = MeprSubscription::$active_str;
        $sub->store();

        //Check if prior txn is expired yet or not, if so create a temporary txn so the user can access the content immediately
        $prior_txn = $sub->latest_txn();
        if($prior_txn == false || !($prior_txn instanceof MeprTransaction) || strtotime($prior_txn->expires_at) < time()) {
          $txn = new MeprTransaction();
          $txn->subscription_id = $sub->id;
          $txn->trans_num = $sub->subscr_id . '-' . uniqid();
          $txn->status = MeprTransaction::$confirmed_str;
          $txn->txn_type = MeprTransaction::$subscription_confirmation_str;
          $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days(0), 'Y-m-d 23:59:59');
          $txn->set_subtotal(0.00); // Just a confirmation txn
          $txn->store();
        }

        MeprUtils::send_resumed_sub_notices($sub);

        return array('subscription' => $sub, 'transaction' => (isset($txn)) ? $txn : $prior_txn);
      }
    }

    return false;
  }

  /** Used to cancel a subscription by the given gateway. This method should be used
    * by the class to record a successful cancellation from the gateway. This method
    * should also be used by any IPN requests or Silent Posts.
    */
  public function process_cancel_subscription($sub_id) {
    $sub = new MeprSubscription($sub_id);

    if($sub->status == MeprSubscription::$cancelled_str || $sub->status == MeprSubscription::$suspended_str) {
      throw new MeprGatewayException(__('This subscription has already been cancelled.', 'memberpress'));
    }

    if(strpos($sub->subscr_id, 'sub_') === 0) {
      $endpoint = "subscriptions/{$sub->subscr_id}";
    }
    else {
      try {
        $customer = $this->retrieve_customer($sub->subscr_id);
      }
      catch(Exception $e) {
        // If there's not already a customer then we're done here
        return false;
      }

      $endpoint = "customers/{$customer->id}/subscription";
    }

    $args = MeprHooks::apply_filters('mepr_stripe_cancel_subscription_args', [], $sub);

    $subscription = $this->send_stripe_request($endpoint, $args, 'delete');

    $_REQUEST['data'] = $subscription;

    return $this->record_cancel_subscription();
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_cancel_subscription() {
    if(isset($_REQUEST['data'])) {
      $subscription = (object) $_REQUEST['data'];
      $sub = MeprSubscription::get_one_by_subscr_id($subscription->id);

      if(!($sub instanceof MeprSubscription)) {
        // Look for an old cus_xxx subscription
        $sub = MeprSubscription::get_one_by_subscr_id($subscription->customer);
      }

      if($sub instanceof MeprSubscription) {
        // Seriously ... if sub was already cancelled what are we doing here?
        // Also, for stripe, since a suspension is only slightly different
        // than a cancellation, we kick it into high gear and check for that too
        if($sub->status == MeprSubscription::$cancelled_str || $sub->status == MeprSubscription::$suspended_str) {
          return $sub;
        }

        $sub->status = MeprSubscription::$cancelled_str;
        $sub->store();

        if(isset($_REQUEST['expire'])) {
          $sub->limit_reached_actions();
        }

        if(!isset($_REQUEST['silent']) || ($_REQUEST['silent']==false)) {
          MeprUtils::send_cancelled_sub_notices($sub);
        }
      }
    }

    return false;
  }

  /** This gets called on the 'init' hook when the signup form is processed ...
    * this is in place so that payment solutions like paypal can redirect
    * before any content is rendered.
  */
  public function process_signup_form($txn) {
    // Not used
  }

  public function display_payment_page($txn) {
    // Nothing to do here ...
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the page containing the payment form
    */
  public function enqueue_payment_form_scripts() {
    if (wp_script_is('mepr-stripe-form', 'enqueued')) {
      return;
    }

    $mepr_options = MeprOptions::fetch();

    wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), MEPR_VERSION);
    wp_enqueue_script('mepr-stripe-form', MEPR_GATEWAYS_URL . '/stripe/form.js', array('stripe-js', 'mepr-checkout-js', 'jquery.payment'), MEPR_VERSION);

    $l10n = [
      'api_version' => self::STRIPE_API_VERSION,
      'taxes_enabled' => (bool) get_option('mepr_calculate_taxes'),
      'payment_information_incomplete' => __('Please complete payment information', 'memberpress'),
      'placeholder_text_email_address' => __('Please enter your email and address to view the payment form.', 'memberpress'),
      'placeholder_text_email' => __('Please enter your email to view the payment form.', 'memberpress'),
      'address_fields_required' => ($mepr_options->show_address_fields && $mepr_options->require_address_fields),
      'elements_appearance' => $this->get_elements_appearance(),
      'ajax_url' => admin_url('admin-ajax.php'),
      'ajax_error' => __('An error occurred, please DO NOT submit the form again as you may be double charged. Please contact us for further assistance instead.', 'memberpress'),
      'invalid_response_error' => __('The response from the server was invalid', 'memberpress'),
      'error_please_try_again' => __('An error occurred, please try again', 'memberpress'),
      'top_error' => sprintf(
        // translators: %1$s: open strong tag, %2$s: close strong tag, %3$s: error message
        esc_html__('%1$sERROR%2$s: %3$s', 'memberpress'),
        '<strong>',
        '</strong>',
        '%s'
      )
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
   * @return array
   */
  private function get_elements_appearance() {
    $appearance = [];

    $appearance = MeprHooks::apply_filters('mepr-stripe-elements-appearance', $appearance);

    return $appearance;
  }

  /**
  * Returns the payment form and required fields for the gateway
  */
  public function spc_payment_fields() {
    $mepr_options = MeprOptions::fetch();
    $payment_method = $this;
    $payment_form_action = 'mepr-stripe-payment-form';
    $txn = new MeprTransaction; //FIXME: This is simply for the action mepr-authorize-net-payment-form
    $user = MeprUtils::is_user_logged_in() ? MeprUtils::get_currentuserinfo() : null;

    return MeprView::get_string("/checkout/MeprStripeGateway/payment_form", get_defined_vars());
  }

  /** This gets called on the_content and just renders the payment form
    */
  public function display_payment_form($amount, $user, $product_id, $txn_id) {
    $mepr_options = MeprOptions::fetch();
    $prd = new MeprProduct($product_id);
    $coupon = false;

    $txn = new MeprTransaction($txn_id);

    //Artifically set the price of the $prd in case a coupon was used
    if($prd->price != $amount) {
      $coupon = true;
      $prd->price = $amount;
    }

    $invoice = MeprTransactionsHelper::get_invoice($txn);
    echo $invoice;

    ?>
      <div class="mp_wrapper mp_payment_form_wrapper">
        <?php
            $this->display_on_site_form($txn);
        ?>
      </div>
    <?php
  }

  public function display_on_site_form($txn) {
    $mepr_options = MeprOptions::fetch();
    $user         = $txn->user();
    ?>
      <form action="" method="post" id="mepr-stripe-payment-form">
        <input type="hidden" name="mepr_process_payment_form" value="Y" />
        <input type="hidden" name="mepr_transaction_id" value="<?php echo esc_attr($txn->id); ?>" />
        <input type="hidden" name="address_required" value="<?php echo $mepr_options->show_address_fields && $mepr_options->require_address_fields ? 1 : 0 ?>" />

        <?php
          if($user instanceof MeprUser) {
            MeprView::render("/checkout/MeprStripeGateway/payment_gateway_fields", get_defined_vars());
          }
        ?>
    <?php if($this->settings->stripe_checkout_enabled == 'on'): ?>
      <?php MeprHooks::do_action('mepr-stripe-payment-form-before-name-field', $txn); ?>
      <input type="hidden" name="mepr_stripe_is_checkout" value="1"/>
      <input type="hidden" name="mepr_stripe_checkout_page_mode" value="1"/>
      <div class="mepr-stripe-gateway-description"><?php esc_html_e('Pay with your Credit Card via Stripe Checkout', 'memberpress'); ?></div>
      <span role="alert" class="mepr-stripe-checkout-errors"></span>
    <?php else: ?>
      <div class="mepr-stripe-elements">
        <?php if($this->is_stripe_link_enabled()) : ?>
          <div class="mepr-stripe-link-element" data-stripe-email="<?php echo $user instanceof MeprUser ? esc_attr($user->user_email) : ''; ?>"></div>
        <?php endif; ?>
        <div class="mepr-stripe-card-element" data-stripe-public-key="<?php echo esc_attr($this->settings->public_key); ?>" data-payment-method-id="<?php echo esc_attr($this->settings->id); ?>" data-locale-code="<?php echo esc_attr(self::get_locale_code()); ?>"></div>
        <div class="mepr-stripe-payment-element-loading mepr-hidden">
          <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" />
        </div>
        <div role="alert" class="mepr-stripe-card-errors"></div>
      </div>
      <?php MeprHooks::do_action('mepr-stripe-payment-form', $txn); ?>
    <?php endif; ?>
      <div class="mepr_spacer">&nbsp;</div>
      <input type="submit" class="mepr-submit" value="<?php echo esc_attr(_x('Submit', 'ui', 'memberpress')); ?>" />
      <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />

      <noscript><p class="mepr_nojs"><?php esc_html_e('Javascript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
    </form>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_payment_form($errors) {
    // This is done in the javascript with Stripe
  }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  public function display_options_form() {
    $mepr_options = MeprOptions::fetch();

    $id = $this->id;
    $integrations = $mepr_options->integrations;
    $whk_url = $this->notify_url( 'whk' );
    $update_billing_url = $this->notify_url( 'update-billing.html', true );

    $test_secret_key      = trim($this->settings->api_keys['test']['secret']);
    $test_public_key      = trim($this->settings->api_keys['test']['public']);
    $live_secret_key      = trim($this->settings->api_keys['live']['secret']);
    $live_public_key      = trim($this->settings->api_keys['live']['public']);
    $force_ssl            = ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true);
    $debug                = ($this->settings->debug == 'on' or $this->settings->debug == true);
    $test_mode            = ($this->settings->test_mode == 'on' or $this->settings->test_mode == true);
    $connect_status       = trim($this->settings->connect_status);
    $service_account_id   = trim($this->settings->service_account_id);
    $service_account_name = stripslashes(trim($this->settings->service_account_name));
    $churn_buster_enabled = ($this->settings->churn_buster_enabled == 'on' or $this->settings->churn_buster_enabled == true);
    $stripe_checkout_enabled = $this->settings->stripe_checkout_enabled == 'on';
    $stripe_link_enabled = $this->settings->stripe_link_enabled == 'on' or $this->settings->stripe_link_enabled == true;
    $currency_supports_link = $this->currency_supports_link();

    if ($this->settings->stripe_link_enabled === 'default' && $currency_supports_link) {
      $stripe_link_enabled = true;
    }

    $churn_buster_uuid    = trim($this->settings->churn_buster_uuid);

    $test_secret_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][test][secret]";
    $test_public_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][test][public]";
    $live_secret_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][live][secret]";
    $live_public_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][live][public]";
    $force_ssl_str            = "{$mepr_options->integrations_str}[{$this->id}][force_ssl]";
    $debug_str                = "{$mepr_options->integrations_str}[{$this->id}][debug]";
    $test_mode_str            = "{$mepr_options->integrations_str}[{$this->id}][test_mode]";
    $connect_status_string    = "{$mepr_options->integrations_str}[{$this->id}][connect_status]";
    $service_account_id_string= "{$mepr_options->integrations_str}[{$this->id}][service_account_id]";
    $service_account_name_string= "{$mepr_options->integrations_str}[{$this->id}][service_account_name]";
    $churn_buster_enabled_str = "{$mepr_options->integrations_str}[{$this->id}][churn_buster_enabled]";
    $stripe_checkout_enabled_str = "{$mepr_options->integrations_str}[{$this->id}][stripe_checkout_enabled]";
    $stripe_link_enabled_str = "{$mepr_options->integrations_str}[{$this->id}][stripe_link_enabled]";
    $churn_buster_uuid_str    = "{$mepr_options->integrations_str}[{$this->id}][churn_buster_uuid]";

    $account_email = get_option( 'mepr_authenticator_account_email' );
    $secret = get_option( 'mepr_authenticator_secret_token' );
    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    // $stripe_connect_url = 'https://connect.stripe.com/express/oauth/authorize?response_type=code&amp;client_id=ca_32D88BD1qLklliziD7gYQvctJIhWBSQ7&amp;scope=read_write';

    // If we're logged in then let's present a stripe url otherwise an authenticator url
    if( $account_email && $secret && $site_uuid ) {
      $stripe_connect_url = self::get_stripe_connect_url($this->id);
    }
    else {
      $stripe_connect_url = MeprAuthenticatorCtrl::get_auth_connect_url( true, $this->id );
    }

    if ( ! defined ( 'MEPR_DISABLE_STRIPE_CONNECT' ) ) {
      MeprView::render('/admin/gateways/stripe/connect-migrate-prompt', get_defined_vars());
    }
    MeprView::render('/admin/gateways/stripe/keys', get_defined_vars());
    MeprView::render('/admin/gateways/stripe/checkboxes', get_defined_vars());
  }

  /** Validates the form for the given payment gateway on the MemberPress Options page */
  public function validate_options_form($errors) {
    $mepr_options = MeprOptions::fetch();

    if ( ! isset( $_REQUEST[ $mepr_options->integrations_str ][ $this->id ]['stripe_link_enabled'] ) ) {
      $_POST[ $mepr_options->integrations_str ][ $this->id ]['stripe_link_enabled'] = false;
    }

    $testmode = isset($_REQUEST[$mepr_options->integrations_str][$this->id]['test_mode']);
    $testmodestr  = $testmode ? 'test' : 'live';

    // Bail if connecting to a Stripe Connect account, since the keys won't be set at this time
    // Also, bail if the payment method is not currently connected.
    if ( isset( $_REQUEST['stripe_connect_account_number'] ) || self::stripe_connect_status( $this->id ) == 'not-connected' ) {
      return $errors;
    }

    if( !isset($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['secret']) or
         empty($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['secret']) or
        !isset($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['public']) or
         empty($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['public']) ) {
      $errors[] = __("All Stripe keys must be filled in.", 'memberpress');
    }

    return $errors;
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the front end user account page.
    */
  public function enqueue_user_account_scripts() {
    $sub = (isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['sub'])) ? new MeprSubscription((int)$_GET['sub']) : false;
    if($sub !== false && $sub->gateway == $this->id) {
      wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), MEPR_VERSION . time());
      wp_enqueue_script('stripe-account-create-token', MEPR_GATEWAYS_URL . '/stripe/account_create_token.js', array('stripe-js'), MEPR_VERSION . time());

      $return_url = add_query_arg([
        'action' => 'mepr_stripe_update_payment_method',
        'subscription_id' => $sub->id,
        'nonce' => wp_create_nonce('mepr_process_update_account_form'),
      ], admin_url('admin-ajax.php'));

      wp_localize_script('stripe-account-create-token', 'MeprStripeAccountForm', array(
        'api_version' => self::STRIPE_API_VERSION,
        'public_key' => $this->settings->public_key,
        'elements_appearance' => $this->get_elements_appearance(),
        'ajax_url' => admin_url('admin-ajax.php'),
        'return_url' => $return_url,
      ));
    }
  }

  /** Displays the update account form on the subscription account page **/
  public function display_update_account_form($sub_id, $errors=array(), $message='') {
    $mepr_options = MeprOptions::fetch();
    $sub = new MeprSubscription($sub_id);
    $user = $sub->user();
    $subscribed_with_link = false;

    if(empty($message) && isset($_GET['message'])) {
      $message = sanitize_text_field(wp_unslash($_GET['message']));
    }

    try {
      if(strpos($sub->subscr_id, 'sub_') === 0) {
        $subscription = $this->retrieve_subscription($sub->subscr_id);
        $subscribed_with_link = isset($subscription->default_payment_method['type']) && $subscription->default_payment_method['type'] == 'link';
      }
    }
    catch(Exception $e) {
      // Ignore
    }
    ?>
      <div class="mp_wrapper">
        <form action="" method="post" id="mepr-stripe-payment-form" data-sub-id="<?php echo esc_attr($sub->id); ?>">
          <input type="hidden" name="_mepr_nonce" value="<?php echo wp_create_nonce('mepr_process_update_account_form'); ?>" />
          <input type="hidden" name="address_required" value="<?php echo $mepr_options->show_address_fields && $mepr_options->require_address_fields ? 1 : 0 ?>" />
          <?php MeprView::render('/shared/errors', get_defined_vars()); ?>
          <?php
            if($user instanceof MeprUser) {
              MeprView::render("/checkout/MeprStripeGateway/payment_gateway_fields", get_defined_vars());
            }
          ?>
          <div class="mepr_update_account_table">
            <?php if($subscribed_with_link) : ?>
              <div><strong><?php _e( 'Link is set up as the payment method for this subscription. You can change the default payment method by logging in to <a href="https://link.co">Link.co</a>.', 'memberpress' ); ?></strong></div>
              <div><strong><?php esc_html_e( 'Or', 'memberpress' ); ?></strong></div>
              <div><strong><?php esc_html_e('If you do not wish to use Link for this subscription, you can enter the details for a different payment method below.', 'memberpress'); ?></strong></div><br/>
            <?php else : ?>
              <div><strong><?php esc_html_e('Update your payment information below', 'memberpress'); ?></strong></div><br/>
            <?php endif; ?>
            <div class="mepr-stripe-elements">
              <?php if($this->is_stripe_link_enabled()) : ?>
                <div class="mepr-stripe-link-element" data-stripe-email="<?php echo isset($user) && $user instanceof MeprUser ? esc_attr($user->user_email) : ''; ?>"></div>
              <?php endif; ?>
              <div id="card-element" class="mepr-stripe-card-element" data-locale-code="<?php echo esc_attr(self::get_locale_code()); ?>"></div>
              <div class="mepr-stripe-payment-element-loading mepr-hidden">
                <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" />
              </div>
              <div id="card-errors" role="alert" class="mepr-stripe-card-errors"></div>
            </div>
            <div class="mepr_spacer">&nbsp;</div>
            <input type="submit" class="mepr-submit" value="<?php echo esc_attr(_x('Submit', 'ui', 'memberpress')); ?>" />
            <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />

            <noscript><p class="mepr_nojs"><?php esc_html_e('Javascript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
          </div>
        </form>
      </div>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_update_account_form($errors=array()) {
    return $errors;
  }

  /** Used to update the credit card information on a subscription by the given gateway.
    * This method should be used by the class to record a successful cancellation from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function process_update_account_form($sub_id) {
    // Not used
  }

  public function is_stripe_link_enabled() {
    if(!$this->currency_supports_link()) {
      return false;
    }

    return (isset($this->settings->stripe_link_enabled) && $this->settings->stripe_link_enabled);
  }

  public function currency_supports_link() {
    $mepr_options = MeprOptions::fetch();

    return in_array($mepr_options->currency_code, [
      'EUR', 'BGN', 'HRK', 'CZK', 'DKK', 'GIP', 'HUF', 'NOK', 'PLN', 'RON', 'SEK', 'CHF', 'GBP', 'USD'
    ], true);
  }

  /** Returns boolean ... whether or not we should be sending in test mode or not */
  public function is_test_mode() {
    if (defined('MEMBERPRESS_STRIPE_TESTING') && MEMBERPRESS_STRIPE_TESTING == true) {
      $this->settings->test_mode = true;
      return true;
    }

    return (isset($this->settings->test_mode) && $this->settings->test_mode);
  }

  public function force_ssl() {
    return (isset($this->settings->force_ssl) and ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true));
  }

  /** Get the renewal base date for a given subscription. This is the date MemberPress will use to calculate expiration dates.
    * Of course this method is meant to be overridden when a gateway requires it.
    */
  public function get_renewal_base_date(MeprSubscription $sub) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $q = $wpdb->prepare("
        SELECT e.created_at
          FROM {$mepr_db->events} AS e
         WHERE e.event='subscription-resumed'
           AND e.evt_id_type='subscriptions'
           AND e.evt_id=%d
         ORDER BY e.created_at DESC
         LIMIT 1
      ",
      $sub->id
    );

    $renewal_base_date = $wpdb->get_var($q);
    if(!empty($renewal_base_date)) {
      return $renewal_base_date;
    }

    return $sub->created_at;
  }

  /**
   * Process an incoming webhook from the Stripe Connect service
   *
   * @return void
   */
  public function service_listener() {

    $mepr_options = MeprOptions::fetch();

    // retrieve the request's body and parse it as JSON
    $body = @file_get_contents('php://input');

    MeprUtils::debug_log('********* WEBHOOK CONTENTS: ' . $body);
    $header_signature = MeprUtils::get_http_header('Signature');

    if(empty($header_signature)) {
      MeprUtils::debug_log('*** Exiting with no signature');
      MeprUtils::exit_with_status(403, __('No Webhook Signature', 'memberpress'));
    }

    $secret = get_option( 'mepr_authenticator_secret_token' );
    $signature = hash_hmac( 'sha256', $body, $secret );

    MeprUtils::debug_log('********* WEBHOOK SECRETS -- SERVICE: [' . $header_signature . '] LOCAL: [' . $signature . ']');

    if($header_signature != $signature) {
      MeprUtils::debug_log('*** Exiting with incorrect signature');
      MeprUtils::exit_with_status(403, __('Incorrect Webhook Signature', 'memberpress'));
    }

    $body = json_decode($body, true);

    if(!isset($body['event']) || empty($body['event'])) {
      MeprUtils::exit_with_status(403, __('No `event` set', 'memberpress'));
    }

    $event = sanitize_text_field( $body['event'] );

    $auth_site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    if($event == 'update-credentials') {

      $site_uuid = sanitize_text_field( $body['data']['site_uuid'] );
      if($auth_site_uuid != $site_uuid) {
        MeprUtils::exit_with_status(404, __('Request was sent to the wrong site?', 'memberpress'));
      }

      $method_id = sanitize_text_field( $body['data']['payment_method'] );
      $pm = $mepr_options->payment_method($method_id);
      if(empty($pm)) {
        MeprUtils::exit_with_status(404, __('No payment method like that exists on this site', 'memberpress'));
      }

      $pm->update_connect_credentials();

      MeprUtils::debug_log("*** MeprStripeGateway::service_listener stored payment methods [{$method_id}]: " . print_r($mepr_options->integrations[$method_id]['api_keys']['test']['secret'],true));

      wp_send_json( array( 'credentials' => 'saved' ) );

    }

    MeprUtils::exit_with_status(404, __('Webhook not supported', 'memberpress'));
  }

  /** STRIPE SPECIFIC METHODS **/

  public function listener() {
    // retrieve the request's body and parse it as JSON
    $body = @file_get_contents('php://input');
    $event_json = (object)json_decode($body,true);


    if(!isset($event_json->id)) return;

    // Use the id to pull the event directly from the API (purely a security measure)
    try {
      $event = (object)$this->send_stripe_request( "events/{$event_json->id}", array(), 'get' );
    }
    catch( Exception $e ) {
      http_response_code(202); //Throw a 202 here so stripe doesn't send out a billion webhook broken emails
      die($e->getMessage()); // Do nothing
    }
    //$event = $event_json;

    $_REQUEST['data'] = $obj = (object)$event->data['object'];

    if($event->type=='charge.succeeded') {
      // For one time payment with stripe checkout session
    }
    else if($event->type=='charge.failed') {
      $this->record_payment_failure();
    }
    else if($event->type=='charge.refunded') {
      $this->record_refund();
    }
    else if($event->type=='charge.disputed') {
      // Not worried about this right now
    }
    else if($event->type=='customer.subscription.created') {
      //$this->record_create_subscription(); // done on page
    }
    else if($event->type=='checkout.session.completed') {
      $this->process_stripe_checkout_session_completed($event);
    }
    else if($event->type=='customer.subscription.updated') {
      //$this->record_update_subscription(); // done on page
    }
    else if($event->type=='customer.subscription.deleted') {
      $this->record_cancel_subscription();
    }
    else if($event->type=='customer.subscription.trial_will_end') {
      // We may want to implement this feature at some point
    }
    else if($event->type=='invoice.payment_succeeded') {
      $this->handle_invoice_payment_succeeded_webhook($obj);
    }
    else if ($event->type=='invoice.payment_failed') {
      $this->handle_invoice_payment_failed_webhook($obj);
    }
    else if($event->type=='customer.deleted') {
      MeprUser::delete_stripe_customer_id($this->get_meta_gateway_id(), $obj->id);
    }
    else if($event->type=='product.deleted') {
      MeprProduct::delete_stripe_product_id($this->get_meta_gateway_id(), $obj->id);
    }
    else if($event->type=='payment_intent.succeeded') {
      $txn = MeprTransaction::get_one_by_trans_num($obj->id);

      if(is_object($txn) and isset($txn->id)) {
        $txn = new MeprTransaction($txn->id);
        $this->handle_one_time_payment($txn, $obj);
      }
    }
    else if($event->type=='setup_intent.succeeded') {
      $this->handle_setup_intent_succeeded_webhook($obj);
    }
    else if($event->type=='plan.deleted') {
      MeprProduct::delete_stripe_plan_id($this->get_meta_gateway_id(), $obj->id);
    }
    else if($event->type=='coupon.deleted') {
      MeprCoupon::delete_stripe_coupon_id($this->get_meta_gateway_id(), $obj->id);
    }
  }

  /** Renders a custom page where a credit card can be updated */
  public function churn_buster() {
    $churn_buster_enabled = ($this->settings->churn_buster_enabled == 'on' || $this->settings->churn_buster_enabled == true);
    $uuid = trim($this->settings->churn_buster_uuid);

    if($churn_buster_enabled && !empty($uuid)) {
      $mepr_options = MeprOptions::fetch();
      $company = $mepr_options->attr('biz_name');

      if(empty($company)) {
        $company = MeprUtils::blogname();
      }

      MeprView::render('account/churn_buster', compact('uuid','company'));
    }
    else {
      MeprUtils::exit_with_status(404);
    }
  }

  /**
   * Sends an email to the customer with a link to confirm the payment for a subscription invoice
   *
   * @param  MeprSubscription     $sub The MemberPress subscription
   * @throws MeprGatewayException      If the customer could not be found or the invoice does not require action
   * @throws MeprHttpException         If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException       If there was an invalid or error response from Stripe
   */
  public function send_latest_invoice_payment_email(MeprSubscription $sub) {
    $user = $sub->user();

    if(!$user->ID) {
      throw new MeprGatewayException(__('User not found', 'memberpress'));
    }

    if(strpos($sub->subscr_id, 'sub_') === 0) {
      $endpoint = "subscriptions/{$sub->subscr_id}";
      $subscr_num = $sub->subscr_id;
    }
    else {
      try {
        $customer = $this->retrieve_customer($sub->subscr_id);
      }
      catch(Exception $e) {
        throw new MeprGatewayException(__('Customer not found', 'memberpress'));
      }

      $endpoint = "customers/{$customer->id}/subscription";
      $subscr_num = $customer->id;
    }

    $args = MeprHooks::apply_filters('mepr_stripe_send_latest_invoice_payment_email_args', array(
      'expand' => array(
        'latest_invoice'
      )
    ), $sub);

    $subscr = (object) $this->send_stripe_request($endpoint, $args, 'get');

    $invoice = (object) $subscr->latest_invoice;

    if ($invoice->status == 'open') {
      /* translators: In this string, %s is the Blog Name/Title */
      $subject = sprintf( __('[%s] Confirm Subscription Payment', 'memberpress'), MeprUtils::blogname());

      $body = MeprView::get_string('/emails/user_confirm_payment', array(
        'blogname' => MeprUtils::blogname(),
        'subscr_num' => $subscr_num,
        'hosted_invoice_url' => $invoice->hosted_invoice_url
      ));

      $message = MeprView::get_string('/emails/template', compact('body'));

      MeprUtils::wp_mail($user->formatted_email(), $subject, $message, array("Content-Type: text/html"));
    } else {
      throw new MeprGatewayException(__('The invoice does not require action from the customer.', 'memberpress'));
    }
  }

  /**
   * Sanitize the statement descriptor
   *
   * Removes invalid chars and limits the length.
   *
   * @param  string $statement_descriptor
   * @return string
   */
  private function sanitize_statement_descriptor($statement_descriptor) {
    if(!is_string($statement_descriptor)) {
      return '';
    }

    $statement_descriptor = preg_replace('/[^a-zA-Z0-9.\-_ ]/', '', $statement_descriptor);
    $statement_descriptor = trim(substr($statement_descriptor, 0, 22));

    return $statement_descriptor;
  }

  public function send_stripe_request( $endpoint,
                                       $args=array(),
                                       $method='post',
                                       $domain='https://api.stripe.com/v1/',
                                       $blocking=true,
                                       $idempotency_key=false ) {
    $mepr_options = MeprOptions::fetch();
    $uri = "{$domain}{$endpoint}";

    $args = MeprHooks::apply_filters('mepr_stripe_request_args', $args);

    $arg_array = array(
      'method'    => strtoupper($method),
      'body'      => $args,
      'timeout'   => 15,
      'blocking'  => $blocking,
      'sslverify' => $mepr_options->sslverify,
      'headers'   => $this->get_headers()
    );

    if(false !== $idempotency_key) {
      $arg_array['headers']['Idempotency-Key'] = $idempotency_key;
    }

    $arg_array = MeprHooks::apply_filters('mepr_stripe_request', $arg_array);

    $uid = uniqid();
    // $this->email_status("###{$uid} Stripe Call to {$uri} API Key: {$this->settings->secret_key}\n" . MeprUtils::object_to_string($arg_array, true) . "\n", $this->settings->debug);

    $resp = wp_remote_request( $uri, $arg_array );

    // If we're not blocking then the response is irrelevant
    // So we'll just return true.
    if( $blocking==false )
      return true;

    if( is_wp_error( $resp ) ) {
      throw new MeprHttpException( sprintf( __( 'You had an HTTP error connecting to %s' , 'memberpress'), $this->name ) );
    }
    else {
      if( null !== ( $json_res = json_decode( $resp['body'], true ) ) ) {
        //$this->email_status("###{$uid} Stripe Response from {$uri}\n" . MeprUtils::object_to_string($json_res, true) . "\n", $this->settings->debug);
        if( isset($json_res['error']) )
          throw new MeprRemoteException( "{$json_res['error']['message']} ({$json_res['error']['type']})" );
        else
          return $json_res;
      }
      else // Un-decipherable message
        throw new MeprRemoteException( sprintf( __( 'There was an issue with the credit card processor. Try again later.', 'memberpress'), $this->name ) );
    }

    return false;
  }

  /** Get card object from a charge response */
  public function get_card($data) {
    // the card object is no longer returned as of 2015-02-18 ... instead it returns 'source'
    if(isset($data->source) && $data->source['object']=='card') {
      return $data->source;
    }
    elseif(isset($data->card)) {
      return $data->card;
    }
  }

  /**
    * Generates the user agent we use to pass to API request so
    * Stripe can identify our application.
    */
  public function get_user_agent() {
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
   */
  public function get_headers() {
    $user_agent = $this->get_user_agent();
    $app_info   = $user_agent['application'];

    return apply_filters(
      'mepr_stripe_request_headers', [
        'Authorization'              => 'Basic ' . base64_encode("{$this->settings->secret_key}:"),
        'Stripe-Version'             => self::STRIPE_API_VERSION,
        'User-Agent'                 => $app_info['name'] . '/' . $app_info['version'] . ' (' . $app_info['url'] . ')',
        'X-Stripe-Client-User-Agent' => json_encode( $user_agent ),
      ]
    );
  }

  /**
   * Assembles the URL for redirecting to Stripe Connect
   *
   * @param  string $id         Payment ID
   * @param  bool   $onboarding True if we are onboarding
   * @return string
   */
  public static function get_stripe_connect_url($method_id, $onboarding = false) {

    $args = array(
      'action' => 'mepr_stripe_connect_update_creds',
      '_wpnonce' => wp_create_nonce( 'stripe-update-creds' )
    );

    if($onboarding) {
      $args['onboarding'] = 'true';
    }

    $base_return_url = add_query_arg( $args, admin_url( 'admin-ajax.php' ) );

    $error_url = add_query_arg( array(
      'mepr-action' => 'error'
    ), $base_return_url );

    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    if ( empty( $site_uuid ) ) {
      return false;
    }

    $mepr_options = MeprOptions::fetch();
    $pm = new MeprStripeGateway();
    $pm->load(array('id'=>$method_id));

    $payload = array(
      'method_id' => $pm->id,
      'site_uuid' => $site_uuid,
      'user_uuid' => get_option( 'mepr_authenticator_user_uuid' ),
      'return_url'=> $base_return_url,
      'error_url' => $error_url,
      'webhook_url'  => $pm->notify_url( 'whk' ),
      'service_webhook_url' => $pm->notify_url( 'stripe-service-whk' ),
      'mp_version' => MEPR_VERSION
    );

    $jwt = MeprAuthenticatorCtrl::generate_jwt( $payload );
    return MEPR_STRIPE_SERVICE_URL  . "/connect/{$site_uuid}/{$method_id}/{$jwt}";
  }

  public static function get_stripe_connect_button_url($method_id) {
    MeprAuthenticatorCtrl::get_auth_connect_url( true, $id );
  }

  public static function keys_are_set( $method_id ) {
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

  public static function is_stripe_connect( $method_id ) {
    $connect_status = self::stripe_connect_status( $method_id );
    return ($connect_status !== 'not-connected');
  }

  public static function stripe_connect_status( $method_id ) {
    $mepr_options = MeprOptions::fetch();

    if (!isset($mepr_options->integrations[$method_id])) {
      return 'not-connected';
    }

    $integ = $mepr_options->integrations[$method_id];

    return ( !isset( $integ['connect_status'] ) || empty( $integ['connect_status'] ) ) ? 'not-connected' : $integ['connect_status'];
  }

  /**
   * Checks whether the user has a Stripe payment method that uses Stripe Connect
   *
   * @return boolean
   */
  public static function has_method_with_connect_status($status = 'connected') {
    $mepr_options = MeprOptions::fetch();
    foreach ( $mepr_options->integrations as $integration ) {

      // Only check Stripe payment methods
      if ( 'MeprStripeGateway' !== $integration['gateway'] ) {
        continue;
      }

      if ( $status == self::stripe_connect_status( $integration['id'] ) ) {
        return true;
      }
    }

    return false;
  }

  /** Fetches the credentials from MP-Stripe-Connect and updates them in the payment method. */
  public function update_connect_credentials() {
    $mepr_options = MeprOptions::fetch();

    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    $payload = array(
      'site_uuid' => $site_uuid
    );

    $jwt = MeprAuthenticatorCtrl::generate_jwt( $payload );

    // Make sure the request came from the Connect service
    $response = wp_remote_get( MEPR_STRIPE_SERVICE_URL . "/api/credentials/{$this->id}", array(
      'headers' => MeprUtils::jwt_header($jwt, MEPR_STRIPE_SERVICE_DOMAIN)
    ) );

    $creds = json_decode( wp_remote_retrieve_body( $response ), true );

    MeprUtils::debug_log("*** MeprStripeGateway::update_connect_credentials updating creds for method_id = [{$this->id}]");

    // Store the credentials
    foreach($mepr_options->integrations as $method_id => $integ) {
      // Update ALL of the payment methods connected to this account
      if( $method_id == $this->id || (
          isset($mepr_options->integrations[$method_id]['service_account_id']) &&
          $mepr_options->integrations[$method_id]['service_account_id'] == $creds['service_account_id'] ) )
      {
        MeprUtils::debug_log("*** MeprStripeGateway::update_connect_credentials updating payment method with this data: " . print_r($creds,true));

        $integ['api_keys']['test']['public'] = sanitize_text_field( $creds['test_publishable_key'] );
        $integ['api_keys']['test']['secret'] = sanitize_text_field( $creds['test_secret_key'] );
        $integ['api_keys']['live']['public'] = sanitize_text_field( $creds['live_publishable_key'] );
        $integ['api_keys']['live']['secret'] = sanitize_text_field( $creds['live_secret_key'] );
        $integ['service_account_id'] = sanitize_text_field( $creds['service_account_id'] );
        $integ['service_account_name'] = sanitize_text_field( $creds['service_account_name'] );
        $integ['connect_status'] = 'connected';

        $mepr_options->integrations[$method_id] = $integ;
      }
    }

    return $mepr_options->store(false);
  }

  /**
   * Get the Stripe Customer ID
   *
   * If the Stripe Customer does not exist for the given user, one will be created.
   *
   * @param  MeprUser            $usr The MemberPress user
   * @return string                   The Stripe Customer ID
   * @throws MeprHttpException        If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException      If there was an invalid or error response from Stripe
   */
  public function get_customer_id(MeprUser $usr) {
    $customer_id = $usr->get_stripe_customer_id($this->get_meta_gateway_id());

    if(!is_string($customer_id) || strpos($customer_id, 'cus_') !== 0) {
      $customer = $this->create_customer($usr);
      $usr->set_stripe_customer_id($this->get_meta_gateway_id(), $customer->id);
      $customer_id = $customer->id;
    }

    return $customer_id;
  }

  /**
   * Create a Stripe Customer
   *
   * @param  MeprUser            $usr The MemberPress user
   * @return stdClass                 The Stripe Customer data
   * @throws MeprHttpException        If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException      If there was an invalid or error response from Stripe
   */
  public function create_customer(MeprUser $usr) {
    $args = [
      'email' => $usr->user_email
    ];

    if($full_name = $usr->get_full_name()) {
      $args['name'] = $full_name;
    }

    $args = MeprHooks::apply_filters('mepr_stripe_create_customer_args', $args, $usr);

    $customer = (object) $this->send_stripe_request('customers', $args, 'post');

    return $customer;
  }

  /**
   * Update the Stripe Customer with the latest user data
   *
   * @param  string              $customer_id The Stripe Customer ID
   * @param  MeprUser            $usr         The MemberPress User
   * @throws MeprHttpException                If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException              If there was an invalid or error response from Stripe
   */
  public function update_customer($customer_id, MeprUser $usr) {
    $args = [
      'email' => $usr->user_email,
    ];

    if($full_name = $usr->get_full_name()) {
      $args['name'] = $full_name;
    }

    $address = [
      'line1' => get_user_meta($usr->ID, 'mepr-address-one', true),
      'line2' => get_user_meta($usr->ID, 'mepr-address-two', true),
      'city' => get_user_meta($usr->ID, 'mepr-address-city', true),
      'state' => get_user_meta($usr->ID, 'mepr-address-state', true),
      'country' => get_user_meta($usr->ID, 'mepr-address-country', true),
      'postal_code' => get_user_meta($usr->ID, 'mepr-address-zip', true)
    ];

    foreach($address as $key => $value) {
      if(empty($value) || !is_string($value)) {
        unset($address[$key]);
      }
    }

    if(!empty($address) && !empty($address['line1'])) {
      $args['address'] = $address;
    }

    $args = MeprHooks::apply_filters('mepr_stripe_update_customer_args', $args, $usr);

    $this->send_stripe_request("customers/$customer_id", $args, 'post');
  }

  /**
   * Get the Stripe Product ID
   *
   * If the Stripe Product does not exist for the given product, one will be created.
   *
   * @param  MeprProduct  $prd  The MemberPress product
   * @param  bool  $initial_payment
   *
   * @return string                   The Stripe Product ID
   * @throws MeprHttpException        If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException      If there was an invalid or error response from Stripe
   */
  public function get_product_id(MeprProduct $prd, $initial_payment = false) {
    $product_id = $prd->get_stripe_product_id($this->get_meta_gateway_id());

    if ( $initial_payment ) {
      $product_id = $prd->get_stripe_initial_payment_product_id( $this->get_meta_gateway_id() );
    }

    if(!is_string($product_id) || strpos($product_id, 'prod_') !== 0) {
      $product = $this->create_product($prd, $initial_payment);

      if ($initial_payment) {
        $prd->set_stripe_initial_payment_product_id( $this->get_meta_gateway_id(), $product->id );
      } else {
        $prd->set_stripe_product_id( $this->get_meta_gateway_id(), $product->id );
      }

      $product_id = $product->id;
    }

    return $product_id;
  }

  /**
   * Create a Stripe Plan
   *
   * @param  string              $product_id The Stripe Product ID
   * @param  MeprSubscription    $sub        The MemberPress subscription
   * @param  string              $amount     The payment amount (excluding tax)
   * @return stdClass                        The Stripe Plan data
   * @throws MeprHttpException               If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException             If there was an invalid or error response from Stripe
   */
  public function create_plan($product_id, MeprSubscription $sub, $amount) {
    $mepr_options = MeprOptions::fetch();

    if($sub->period_type == 'months') {
      $interval = 'month';
    } elseif($sub->period_type == 'years') {
      $interval = 'year';
    } elseif($sub->period_type == 'weeks') {
      $interval = 'week';
    }

    $args = MeprHooks::apply_filters('mepr_stripe_create_plan_args', [
      'amount' => $amount,
      'interval' => $interval,
      'interval_count' => $sub->period,
      'currency' => $mepr_options->currency_code,
      'product' => $product_id
    ], $sub);

    // Prevent a Stripe error if the user is using the pre-1.6.0 method of setting the statement_descriptor
    if(array_key_exists('statement_descriptor', $args)) {
      unset($args['statement_descriptor']);
    }

    // Prevent a Stripe error if the 'product' value is modified by hook
    if(!isset($args['product']) || is_array($args['product'])) {
      $args['product'] = $product_id;
    }

    // Don't enclose this in try/catch ... we want any errors to bubble up
    $plan = (object) $this->send_stripe_request('plans', $args, 'post');

    return $plan;
  }

  /**
   * Create a Stripe Product
   *
   * @param  MeprProduct  $prd  The MemberPress product
   * @param  bool  $initial_payment
   *
   * @return stdClass                 The Stripe Product data
   * @throws MeprHttpException        If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException      If there was an invalid or error response from Stripe
   */
  public function create_product(MeprProduct $prd, $initial_payment = false) {
    if ( $initial_payment ) {
      $product_name = $prd->post_title . ' - Initial Payment';
    } else {
      $product_name = $prd->post_title;
    }

    $args = MeprHooks::apply_filters('mepr_stripe_create_product_args', [
      'name' => $product_name,
      'type' => 'service'
    ], $prd);

    // Prevent a Stripe error in the rare case that a membership has an empty title
    if(empty($args['name'])) {
      $args['name'] = sprintf(
        /* translators: %d: the product ID */
        __('Product %d', 'memberpress'),
        $prd->ID
      );
    }

    if(!array_key_exists('statement_descriptor', $args)) {
      $statement_descriptor = $this->get_statement_descriptor($prd);

      if(strlen($statement_descriptor) > 1) {
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
   * @param MeprSubscription $sub
   * @param MeprProduct $prd
   * @param float $price
   */
  public function get_stripe_price_id($sub, MeprProduct $prd, $price) {
    $mepr_options = MeprOptions::fetch();

    if ($prd->is_one_time_payment()) {
      $meta_key = '_mepr_stripe_onetime_price_id_' .
                  md5(serialize([
                    'currency' => $mepr_options->currency_code,
                    'amount' => $price,
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
      $plan_id = $this->get_stripe_plan_id( $sub, $prd, $price );

      if ( ! empty( $plan_id ) ) {
        return $plan_id;
      }

      $meta_key = '_mepr_stripe_subscription_price_id_' .
                  md5(serialize([
                    'currency' => $mepr_options->currency_code,
                    'amount' => $price,
                    'payment_method' => $this->get_meta_gateway_id(),
                  ]));
      $price_id = get_post_meta(
        $prd->ID,
        $meta_key,
        true
      );

      if ( ! empty( $price_id ) ) {
        return $price_id;
      }
    }

    // Handle zero decimal currencies in Stripe
    $amount = $this->to_zero_decimal_amount($price);
    $product_id = $prd->ID;

    if ($prd->is_one_time_payment()) {
      $args = MeprHooks::apply_filters('mepr_stripe_create_price_args', [
        'product' => $this->get_product_id($prd),
        'unit_amount' => $amount,
        'currency' => $mepr_options->currency_code,
      ], $sub);

      $price = (object) $this->send_stripe_request('prices', $args, 'post');
      update_post_meta($product_id, $meta_key, $price->id);

      return $price->id;
    }

    $interval = '';

    if($sub->period_type == 'months') {
      $interval = 'month';
    } elseif($sub->period_type == 'years') {
      $interval = 'year';
    } elseif($sub->period_type == 'weeks') {
      $interval = 'week';
    }

    $args = MeprHooks::apply_filters('mepr_stripe_create_price_args', [
      'product' => $this->get_product_id($prd),
      'unit_amount' => $amount,
      'currency' => $mepr_options->currency_code,
      'recurring' => [
        'interval' => $interval,
      ],
    ], $sub);

    // Prevent a Stripe error if the 'product' value is modified by hook
    if(!isset($args['product']) || is_array($args['product'])) {
      $args['product'] = $product_id;
    }

    $price = (object) $this->send_stripe_request('prices', $args, 'post');
    update_post_meta($product_id, $meta_key, $price->id);

    return $price->id;
  }

  /**
   * Get the Stripe Plan ID
   *
   * If the Stripe Plan does not exist for the given subscription, one will be created.
   *
   * @param  MeprSubscription    $sub    The MemberPress subscription
   * @param  MeprProduct         $prd    The MemberPress product
   * @param  float               $amount The plan amount
   * @return string                      The Stripe Plan ID
   * @throws MeprHttpException           If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException         If there was an invalid or error response from Stripe
   */
  public function get_stripe_plan_id(MeprSubscription $sub, MeprProduct $prd, $amount) {
    $amount = $this->to_zero_decimal_amount($amount);
    $plan_id = $prd->get_stripe_plan_id($this->get_meta_gateway_id(), $amount);

    if(!is_string($plan_id) || strpos($plan_id, 'plan_') !== 0) {
      $plan = $this->create_plan($this->get_product_id($prd), $sub, $amount);
      $prd->set_stripe_plan_id($this->get_meta_gateway_id(), $amount, $plan->id);
      $plan_id = $plan->id;
    }

    return $plan_id;
  }

  /**
   * Create a Stripe Coupon
   *
   * @param  MeprCoupon          $cpn             The MemberPress coupon
   * @param  string              $discount_amount The coupon discount amount
   * @return stdClass                             The Stripe Coupon data
   * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                  If there was an invalid or error response from Stripe
   */
  public function create_coupon(MeprCoupon $cpn, $discount_amount, $onetime) {
    $args = MeprHooks::apply_filters('mepr_stripe_create_coupon_args', [
      'name' => substr($cpn->post_title, 0 , 40),
      'duration' => 'forever'
    ], $cpn);

    $mepr_options = MeprOptions::fetch();


    if ($onetime) {
      $args['duration'] = 'once';

      if ($cpn->first_payment_discount_type == 'percent') {
        $args = array_merge( [
          'percent_off' => $discount_amount
        ], $args );
      } else {
        $args = array_merge([
          'amount_off' => $discount_amount,
          'currency' => $mepr_options->currency_code,
        ], $args);
      }
    } else {
      if ( $cpn->discount_type == 'percent' ) {
        $args = array_merge( [
          'percent_off' => $discount_amount
        ], $args );
      } else {
        $args = array_merge( [
          'amount_off' => $discount_amount,
          'currency'   => $mepr_options->currency_code,
        ], $args );
      }
    }

    $coupon = (object) $this->send_stripe_request('coupons', $args, 'post');

    return $coupon;
  }

  /**
   * Get the Stripe Coupon ID
   *
   * @param  MeprCoupon          $cpn             The MemberPress coupon
   * @param  string              $discount_amount The coupon discount amount
   * @param  bool              $onetime
   * @return string                               The Stripe Coupon ID
   * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                  If there was an invalid or error response from Stripe
   */
  public function get_coupon_id(MeprCoupon $cpn, $discount_amount, $onetime = false) {
    $coupon_id = $cpn->get_stripe_coupon_id($this->get_meta_gateway_id(), $discount_amount, $onetime);

    if(!is_string($coupon_id) || $coupon_id === '') {
      $coupon = $this->create_coupon($cpn, $discount_amount, $onetime);
      $cpn->set_stripe_coupon_id($this->get_meta_gateway_id(), $discount_amount, $coupon->id, $onetime);
      $coupon_id = $coupon->id;
    }

    return $coupon_id;
  }

  /**
   * Get the statement descriptor
   *
   * @param  MeprProduct $product The MemberPress product
   * @return string               The statement descriptor
   */
  private function get_statement_descriptor(MeprProduct $product) {
    $descriptor = MeprUtils::blogname();

    if(empty($descriptor)) {
      $descriptor = parse_url(get_option('siteurl'), PHP_URL_HOST);
    }

    $descriptor = MeprHooks::apply_filters('mepr_stripe_statement_descriptor', $descriptor, $product);

    return $this->sanitize_statement_descriptor($descriptor);
  }

  /**
   * Create a Stripe Subscription
   *
   * @param  MeprTransaction     $txn                The MemberPress transaction
   * @param  MeprSubscription    $sub                The MemberPress subscription
   * @param  MeprProduct         $prd                The MemberPress product
   * @param  string              $customer_id        The Stripe Customer ID
   * @param  string              $payment_method_id  The Stripe PaymentMethod ID to use for payment or setup
   * @param  boolean             $default_incomplete Whether the subscription should default to incomplete status
   * @return stdClass                                The Stripe Subscription data
   * @throws MeprHttpException                       If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                     If there was an invalid or error response from Stripe
   */
  public function create_subscription(MeprTransaction $txn, MeprSubscription $sub, MeprProduct $prd, $customer_id, $payment_method_id = null, $default_incomplete = true) {
    $mepr_options = MeprOptions::fetch();
    $tax_rate_id = null;

    if(get_option('mepr_calculate_taxes') && $txn->tax_rate > 0) {
      $tax_rate_id = $this->get_stripe_tax_rate_id($txn->tax_desc, $txn->tax_rate, $prd, $mepr_options->attr('tax_calc_type') == 'inclusive');
    }

    if($sub->trial && $sub->trial_days > 0 && (float) $sub->trial_amount > 0.00) {
      // For paid trials, add the trial payment amount as an invoice item before creating the subscription
      $args = [
        'customer' => $customer_id,
        'amount' => $this->to_zero_decimal_amount($sub->trial_amount),
        'currency' => $mepr_options->currency_code,
        'description' => __('Initial Payment', 'memberpress'),
        'metadata' => [
          'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
          'site_url' => get_site_url(),
          'ip_address' => $_SERVER['REMOTE_ADDR']
        ],
        'discountable' => 'false'
      ];

      if($tax_rate_id) {
        $args['tax_rates'] = [$tax_rate_id];
      }

      $args = MeprHooks::apply_filters('mepr_stripe_paid_trial_invoice_args', $args, $txn, $sub);

      $invoice_item = (object) $this->send_stripe_request('invoiceitems', $args, 'post');
    }

    $item = ['plan' => $this->get_stripe_plan_id($sub, $prd, $prd->price)];

    if($tax_rate_id) {
      $item['tax_rates'] = [$tax_rate_id];
    }

    $args = [
      'customer' => $customer_id,
      'items' => [$item],
      'expand' => [
        'latest_invoice.payment_intent',
      ],
      'metadata' => [
        'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
        'site_url' => get_site_url(),
        'ip_address' => $_SERVER['REMOTE_ADDR']
      ],
    ];

    if($payment_method_id) {
      $args = array_merge($args, ['default_payment_method' => $payment_method_id]);
    }

    if($default_incomplete) {
      $args = array_merge($args, [
        'payment_behavior' => 'default_incomplete',
        'payment_settings' => [
          'payment_method_types' => MeprHooks::apply_filters('mepr_stripe_subscription_payment_method_types', $this->is_stripe_link_enabled() ? ['card', 'link'] : ['card'], $txn, $sub, $prd),
          'save_default_payment_method' => 'on_subscription',
        ],
      ]);
    }

    if($txn->id > 0) {
      $args = array_merge_recursive($args, [
        'metadata' => [
          'transaction_id' => $txn->id,
        ]
      ]);
    }

    if($sub->trial && $sub->trial_days > 0) {
      $args = array_merge($args, ['trial_period_days' => $sub->trial_days]);
    }

    $coupon = $sub->coupon();

    if($coupon instanceof MeprCoupon) {
      $discount_amount = $this->get_coupon_discount_amount($coupon, $prd, $txn->tax_rate);

      if($discount_amount > 0) {
        $args = array_merge(['coupon' => $this->get_coupon_id($coupon, $discount_amount)], $args);
      }
    }

    MeprHooks::apply_filters('mepr_stripe_subscription_args', $args, $txn, $sub);

    $this->email_status("create_subscription: \n" . MeprUtils::object_to_string($txn) . "\n", $this->settings->debug);

    try {
      $subscription = (object) $this->send_stripe_request('subscriptions', $args, 'post');
    }
    catch(Exception $e) {
      if(isset($invoice_item)) {
        // Delete the created trial invoice item if the subscription failed to be created
        $this->send_stripe_request("invoiceitems/{$invoice_item->id}", [], 'delete');
      }

      throw $e;
    }

    return $subscription;
  }

  /**
   * Get the discount amount for the given coupon
   *
   * @param  MeprCoupon  $coupon   The coupon being used
   * @param  MeprProduct $product  The membership being purchased
   * @param  string      $tax_rate The tax rate for the transaction
   * @return string|int            The formatted discount amount or 0 if no discount
   */
  public function get_coupon_discount_amount(MeprCoupon $coupon, MeprProduct $product, $tax_rate) {
    $mepr_options = MeprOptions::fetch();
    $discount_amount = $coupon->get_discount_amount($product);

    if($discount_amount > 0) {
      if($coupon->discount_type == 'percent') {
        $discount_amount = MeprUtils::format_float($discount_amount);
      }
      else {
        $discount_amount = self::is_zero_decimal_currency() ? MeprUtils::format_float($discount_amount, 0) : MeprUtils::format_float($discount_amount * 100, 0);
      }

      if($discount_amount > 0) {
        return $discount_amount;
      }
    }

    return 0;
  }

  /**
   * Retrieve a Stripe Subscription
   *
   * @param  string              $subscription_id The Stripe Subscription ID
   * @return stdClass                             The Stripe Subscription data
   * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                  If there was an invalid or error response from Stripe
   */
  public function retrieve_subscription($subscription_id) {
    $args = MeprHooks::apply_filters('mepr_stripe_retrieve_subscription_args', [
      'expand' => [
        'latest_invoice',
        'default_payment_method',
      ]
    ]);

    return (object) $this->send_stripe_request('subscriptions/' . $subscription_id, $args, 'get');
  }

  /**
   * Retrieve a Stripe SetupIntent
   *
   * @param  string              $setup_intent_id The Stripe SetupIntent ID
   * @return stdClass                             The Stripe SetupIntent data
   * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                  If there was an invalid or error response from Stripe
   */
  public function retrieve_setup_intent($setup_intent_id) {
    return (object) $this->send_stripe_request( "setup_intents/{$setup_intent_id}", ['expand' => ['payment_method']], 'get');
  }

  /**
   * Retrieve a Stripe PaymentIntent
   *
   * @param  string              $payment_intent_id The Stripe PaymentIntent ID
   * @return stdClass                               The Stripe PaymentIntent data
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function retrieve_payment_intent($payment_intent_id) {
    return (object) $this->send_stripe_request('payment_intents/' . $payment_intent_id, [], 'get');
  }

  /**
   * Retrieve a Stripe Invoice
   *
   * @param  string              $invoice_id The Stripe Invoice ID
   * @return stdClass                        The Stripe Invoice data
   * @throws MeprHttpException               If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException             If there was an invalid or error response from Stripe
   */
  public function retrieve_invoice($invoice_id) {
    return (object) $this->send_stripe_request('invoices/' . $invoice_id, ['expand' => ['payment_intent']], 'get');
  }

  /**
   * Retrieve a Stripe Customer
   *
   * @param  string              $customer_id The Stripe Customer ID
   * @return stdClass                         The Stripe Customer data
   * @throws MeprHttpException                If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException              If there was an invalid or error response from Stripe
   */
  public function retrieve_customer($customer_id) {
    return (object) $this->send_stripe_request('customers/' . $customer_id, ['expand' => ['invoice_settings.default_payment_method']], 'get');
  }

  /**
   * Get the gateway ID for storing Stripe object IDs
   *
   * Object IDs in test mode do not exist in live mode, so we need to differentiate.
   *
   * @return string
   */
  public function get_meta_gateway_id() {
    $key = $this->id;

    if($this->is_test_mode()) {
      $key .= '_test';
    }

    return $key;
  }

  /**
   * Is the currency a zero-decimal currency?
   *
   * 'HUF' is a special case for Stripe - it's a zero-decimal currency that must be multiplied by 100.
   *
   * @return bool
   */
  public static function is_zero_decimal_currency() {
    $mepr_options = MeprOptions::fetch();

    if($mepr_options->currency_code == 'HUF') {
      return false;
    }

    return MeprUtils::is_zero_decimal_currency();
  }

  /**
   * Get the locale code
   *
   * Converts the configured MP language code into a Stripe-compatible locale code
   *
   * @see https://stripe.com/docs/js/appendix/supported_locales
   * @return string
   */
  public static function get_locale_code() {
    $mepr_options = MeprOptions::fetch();
    $locale_code = 'auto';

    $locales = array(
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
    );

    if(isset($locales[$mepr_options->language_code])) {
      $locale_code = $locales[$mepr_options->language_code];
    }

    return MeprHooks::apply_filters('mepr_stripe_locale_code', $locale_code);
  }
}
