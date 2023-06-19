<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprStripeCtrl extends MeprBaseCtrl
{
  public function load_hooks() {
    add_action('wp_ajax_mepr_stripe_confirm_payment', array($this, 'confirm_payment'));
    add_action('wp_ajax_nopriv_mepr_stripe_confirm_payment', array($this, 'confirm_payment'));
    add_action('wp_ajax_mepr_stripe_get_elements_options', array($this, 'get_elements_options'));
    add_action('wp_ajax_nopriv_mepr_stripe_get_elements_options', array($this, 'get_elements_options'));
    add_action('wp_ajax_mepr_stripe_create_checkout_session', array($this, 'create_checkout_session'));
    add_action('wp_ajax_nopriv_mepr_stripe_create_checkout_session', array($this, 'create_checkout_session'));
    add_action('wp_ajax_mepr_stripe_create_account_setup_intent', array($this, 'create_account_setup_intent'));
    add_action('wp_ajax_nopriv_mepr_stripe_create_account_setup_intent', array($this, 'create_account_setup_intent'));
    add_action('wp_ajax_mepr_stripe_update_payment_method', array($this, 'update_payment_method'));
    add_action('wp_ajax_nopriv_mepr_stripe_update_payment_method', array($this, 'update_payment_method'));
    add_action('wp_ajax_mepr_stripe_debug_checkout_error', array($this, 'debug_checkout_error'));
    add_action('wp_ajax_nopriv_mepr_stripe_debug_checkout_error', array($this, 'debug_checkout_error'));
    add_action('mepr-update-new-user-email', array($this, 'update_user_email'));
  }

  /**
   * Update email of stripe user object when customer change email
   * on Memberpress account page
   *
   * @param MeprUser $mepr_current_user
   */
  public function update_user_email($mepr_current_user) {
    $mepr_options = MeprOptions::fetch();

    foreach ( $mepr_options->integrations as $integration ) {
      if ( $integration['gateway'] == 'MeprStripeGateway' ) {
        $payment_method = new MeprStripeGateway();
        $payment_method->load( $integration );
        $stripe_customer_id = $mepr_current_user->get_stripe_customer_id($payment_method->get_meta_gateway_id());

        if ( empty( $stripe_customer_id ) ) {
          // Continue on other instances of MeprStripeGateway
          continue;
        }

        $args = [ 'email' => $mepr_current_user->user_email ];

        try {
          $payment_method->send_stripe_request( 'customers/' . $stripe_customer_id, $args, 'post' );
        } catch (\Exception $exception) {}
      }
    }
  }

  public function get_elements_options() {
    $mepr_options = MeprOptions::fetch();
    $transaction_id = isset($_POST['mepr_transaction_id']) && is_numeric($_POST['mepr_transaction_id']) ? (int) $_POST['mepr_transaction_id'] : 0;

    if($transaction_id > 0) {
      $txn = new MeprTransaction($transaction_id);

      if(!$txn->id) {
        wp_send_json_error(__('Transaction not found', 'memberpress'));
      }

      $pm = $mepr_options->payment_method($txn->gateway, true, true);

      if(!($pm instanceof MeprStripeGateway)) {
        wp_send_json_error(__('Invalid payment gateway', 'memberpress'));
      }

      $prd = $txn->product();

      if($pm->settings->stripe_checkout_enabled == 'on') {
        wp_send_json_error(__('Bad request', 'memberpress'));
      }

      if(!$prd->ID) {
        wp_send_json_error(__('Product not found', 'memberpress'));
      }

      if(!$prd->is_one_time_payment()) {
        $sub = $txn->subscription();
      }

      $cpn = $txn->coupon();
      $coupon_code = $cpn instanceof MeprCoupon ? $cpn->post_title : '';
    }
    else {
      $payment_method_id = isset($_POST['mepr_payment_method']) ? sanitize_text_field(wp_unslash($_POST['mepr_payment_method'])) : '';
      $pm = $mepr_options->payment_method($payment_method_id, true, true);

      if(!($pm instanceof MeprStripeGateway)) {
        wp_send_json_error(__('Invalid payment gateway', 'memberpress'));
      }

      if($pm->settings->stripe_checkout_enabled == 'on') {
        wp_send_json_error(__('Bad request', 'memberpress'));
      }

      $product_id = isset($_POST['mepr_product_id']) ? (int) $_POST['mepr_product_id'] : 0;
      $prd = new MeprProduct($product_id);

      if(empty($prd->ID)) {
        wp_send_json_error(__('Sorry, we were unable to find the product.', 'memberpress'));
      }

      $coupon_code = isset($_POST['mepr_coupon_code']) ? sanitize_text_field(wp_unslash($_POST['mepr_coupon_code'])) : '';
      $cpn = MeprCoupon::get_one_from_code($coupon_code);
      $coupon_code = $cpn instanceof MeprCoupon ? $cpn->post_title : '';

      try {
        list($txn, $sub) = MeprCheckoutCtrl::prepare_transaction(
          $prd,
          0,
          get_current_user_id(),
          $pm->id,
          $cpn,
          false
        );
      }
      catch(Exception $e) {
        wp_send_json_error($e->getMessage());
      }
    }

    try {
      $payment_required = $prd->is_payment_required($coupon_code);
      $order_bump_product_ids = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_map('intval', $_POST['mepr_order_bumps']) : [];
      $order_bump_products = MeprCheckoutCtrl::get_order_bump_products($prd->ID, $order_bump_product_ids);

      foreach($order_bump_products as $product) {
        if($product->is_payment_required()) {
          $payment_required = true;
        }
      }

      if(!$payment_required) {
        wp_send_json_success(['payment_required' => false]);
      }

      wp_send_json_success(
        $pm->get_elements_options(
          $prd,
          $txn,
          isset($sub) && $sub instanceof MeprSubscription ? $sub : null,
          $coupon_code,
          $order_bump_products
        )
      );
    }
    catch(Exception $e) {
      wp_send_json_error($e->getMessage());
    }
  }

  public function create_checkout_session() {
    MeprHooks::do_action('mepr_stripe_before_create_checkout_session');

    $this->do_confirm_payment('stripe_checkout');
  }

  public function confirm_payment() {
    MeprHooks::do_action('mepr_stripe_before_confirm_payment');

    try {
      $this->do_confirm_payment();
    } catch (Throwable $t) { // Errors and exceptions in PHP 7
      $content = $t->__toString();
    } catch (Exception $e) { // Exceptions in PHP 5
      $content = $e->__toString();
    }

    $this->send_checkout_error_debug_email(
      $content,
      isset($_POST['mepr_transaction_id']) && is_numeric($_POST['mepr_transaction_id']) ? (int) $_POST['mepr_transaction_id'] : null,
      isset($_POST['user_email']) && is_string($_POST['user_email']) ? sanitize_text_field(wp_unslash($_POST['user_email'])) : null
    );

    wp_send_json(array('error' => __('An error occurred, please DO NOT submit the form again as you may be double charged. Please contact us for further assistance instead.', 'memberpress')));
  }

  public function do_confirm_payment($mode = '') {
    $mepr_options = MeprOptions::fetch();
    $transaction_id = isset($_POST['mepr_transaction_id']) && is_numeric($_POST['mepr_transaction_id']) ? (int) $_POST['mepr_transaction_id'] : 0;

    if($transaction_id > 0) {
      $txn = new MeprTransaction($transaction_id);

      if(!$txn->id) {
        wp_send_json(['error' => __('Transaction not found', 'memberpress')]);
      }

      $pm = $txn->payment_method();

      if(!($pm instanceof MeprStripeGateway)) {
        wp_send_json(['error' => __('Invalid payment gateway', 'memberpress')]);
      }

      $prd = $txn->product();

      if(!$prd->ID) {
        wp_send_json(['error' => __('Product not found', 'memberpress')]);
      }

      $usr = $txn->user();

      if(!$usr->ID) {
        wp_send_json(['error' => __('User not found', 'memberpress')]);
      }

      if(!$prd->is_one_time_payment()) {
        $sub = $txn->subscription();
      }

      $cpn = $txn->coupon();
      $coupon_code = $cpn instanceof MeprCoupon ? $cpn->post_title : '';
    }
    else {
      // We don't have a transaction ID (i.e. this is the Single Page Checkout), so let's create the user and transaction
      // This code is essentially the same as MeprCheckoutCtrl::process_signup_form
      $disable_checkout_password_fields = $mepr_options->disable_checkout_password_fields;

      // Validate the form post
      $mepr_current_url = isset($_POST['mepr_current_url']) && is_string($_POST['mepr_current_url']) ? sanitize_text_field(wp_unslash($_POST['mepr_current_url'])) : '';
      $errors = MeprHooks::apply_filters('mepr-validate-signup', MeprUser::validate_signup($_POST, array(), $mepr_current_url));

      if(!empty($errors)) {
        wp_send_json(['errors' => $errors]);
      }

      // Check if the user is logged in already
      $is_existing_user = MeprUtils::is_user_logged_in();

      if($is_existing_user) {
        $usr = MeprUtils::get_currentuserinfo();
      }
      else { // If new user we've got to create them and sign them in
        $usr = new MeprUser();
        $usr->user_login = ($mepr_options->username_is_email)?sanitize_email($_POST['user_email']):sanitize_user($_POST['user_login']);
        $usr->user_email = sanitize_email($_POST['user_email']);
        $usr->first_name = isset($_POST['user_first_name']) && !empty($_POST['user_first_name']) ? sanitize_text_field(wp_unslash($_POST['user_first_name'])) : '';
        $usr->last_name = isset($_POST['user_last_name']) && !empty($_POST['user_last_name']) ? sanitize_text_field(wp_unslash($_POST['user_last_name'])) : '';

        $password = ($disable_checkout_password_fields === true) ? wp_generate_password() : $_POST['mepr_user_password'];
        //Have to use rec here because we unset user_pass on __construct
        $usr->set_password($password);
        try {
          $usr->store();

          // We need to refresh the user object. In the case where emails are used as
          // usernames, the email & username could differ after the user is saved.
          $usr = new MeprUser( $usr->ID );

          if ( $disable_checkout_password_fields === true ) {
            $usr->send_password_notification( 'new' );
          }
          // Log the new user in
          if ( MeprHooks::apply_filters( 'mepr-auto-login', true, $_POST['mepr_product_id'], $usr ) ) {
            wp_signon(
              array(
                'user_login'    => $usr->user_login,
                'user_password' => $password
              ),
              MeprUtils::is_ssl() //May help with the users getting logged out when going between http and https
            );
          }

          MeprEvent::record( 'login', $usr ); //Record the first login here
        }
        catch (MeprCreateException $e) {
          wp_send_json(['error' => __('The user was unable to be saved.', 'memberpress')]);
        }
      }

      $product_id = isset($_POST['mepr_product_id']) ? (int) $_POST['mepr_product_id'] : 0;
      $prd = new MeprProduct($product_id);

      if(empty($prd->ID)) {
        wp_send_json(['error' => __('Sorry, we were unable to find the product.', 'memberpress')]);
      }

      // If we're showing the fields on logged in purchases, let's save them here
      if(!$is_existing_user || ($is_existing_user && $mepr_options->show_fields_logged_in_purchases)) {
        MeprUsersCtrl::save_extra_profile_fields($usr->ID, true, $prd, true);
        $usr = new MeprUser($usr->ID); //Re-load the user object with the metadata now (helps with first name last name missing from hooks below)
      }

      // Needed for autoresponders (SPC + Stripe + Free Trial issue)
      MeprHooks::do_action('mepr-signup-user-loaded', $usr);

      $payment_method_id = isset($_POST['mepr_payment_method']) ? sanitize_text_field(wp_unslash($_POST['mepr_payment_method'])) : '';
      $pm = $mepr_options->payment_method($payment_method_id);

      if(!($pm instanceof MeprStripeGateway)) {
        wp_send_json(['error' => __('Invalid payment gateway', 'memberpress')]);
      }

      $coupon_code = isset($_POST['mepr_coupon_code']) ? sanitize_text_field(wp_unslash($_POST['mepr_coupon_code'])) : '';
      $cpn = MeprCoupon::get_one_from_code($coupon_code);
      $coupon_code = $cpn instanceof MeprCoupon ? $cpn->post_title : '';

      try {
        list($txn, $sub) = MeprCheckoutCtrl::prepare_transaction(
          $prd,
          0,
          $usr->ID,
          $pm->id,
          $cpn
        );
      }
      catch(Exception $e) {
        wp_send_json(['error' => $e->getMessage()]);
      }
    }

    try {
      // Prevent duplicate charges if the user is already subscribed
      $this->check_if_already_subscribed($usr, $prd);

      $order_bump_product_ids = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_map('intval', $_POST['mepr_order_bumps']) : [];
      $order_bump_products = MeprCheckoutCtrl::get_order_bump_products($prd->ID, $order_bump_product_ids);
      $order_bump_total = 0.00;
      $order_bump_transactions = [];
      $has_subscription_order_bump = false;

      if(count($order_bump_products)) {
        $order = new MeprOrder();
        $order->user_id = $usr->ID;
        $order->primary_transaction_id = $txn->id;
        $order->gateway = $pm->id;
        $order->store();

        $txn->order_id = $order->id;
        $txn->store();

        if(isset($sub) && $sub instanceof MeprSubscription) {
          $sub->order_id = $order->id;
          $sub->store();
        }

        foreach($order_bump_products as $product) {
          // Prevent duplicate charges if the user is already subscribed
          $this->check_if_already_subscribed($usr, $product);

          list($transaction, $subscription) = MeprCheckoutCtrl::prepare_transaction(
            $product,
            $order->id,
            $usr->ID,
            $pm->id
          );

          if($product->is_one_time_payment()) {
            if((float) $transaction->total > 0) {
              $order_bump_total += (float) $transaction->total;
            }
          }
          else {
            if(!($subscription instanceof MeprSubscription)) {
              wp_send_json_error(__('Subscription not found', 'memberpress'));
            }

            $has_subscription_order_bump = true;

            if($subscription->trial && $subscription->trial_days > 0) {
              if((float) $subscription->trial_total > 0) {
                $order_bump_total += (float) $subscription->trial_total;
              }
            }
            else {
              if((float) $subscription->total > 0) {
                $order_bump_total += (float) $subscription->total;
              }
            }
          }

          $order_bump_transactions[] = $transaction;
        }
      }

      if($mode == 'stripe_checkout') {
        if(count($order_bump_products)) {
          $checkout_session = $pm->create_multi_item_checkout_session($txn, $prd, $usr, $coupon_code, $order_bump_transactions);
        }
        else {
          if(!isset($sub)) {
            $sub = $txn->subscription();
          }

          $checkout_session = $pm->create_checkout_session($txn, $prd, $usr, $sub);
        }

        MeprHooks::do_action('mepr_stripe_checkout_pending', $txn, $usr);
        MeprHooks::do_action('mepr-process-signup', $txn->amount, $usr, $prd->ID, $txn->id);
        MeprHooks::do_action('mepr-signup', $txn);

        wp_send_json([
          'id' => $checkout_session->id,
          'public_key' => $pm->settings->public_key,
        ]);
      }

      $customer_id = $usr->get_stripe_customer_id($pm->get_meta_gateway_id());

      if(!is_string($customer_id) || strpos($customer_id, 'cus_') !== 0) {
        $customer_id = $pm->get_customer_id($usr);
      }
      else {
        $pm->update_customer($customer_id, $usr);
      }

      $action = 'confirmPayment';

      $thank_you_page_args = [
        'membership' => sanitize_title($prd->post_title),
        'membership_id' => $prd->ID,
        'transaction_id' => $txn->id,
      ];

      if($prd->is_one_time_payment() || !$prd->is_payment_required($coupon_code)) {
        $total = $prd->is_payment_required($coupon_code) ? (float) $txn->total : 0.00;
        $total += $order_bump_total;

        if($total > 0.00) {
          $setup_future_usage = $has_subscription_order_bump ? 'off_session' : null;
          $payment_intent = $pm->create_payment_intent($total, $customer_id, $txn, $prd, $setup_future_usage);

          $client_secret = $payment_intent->client_secret;

          $txn->trans_num = $payment_intent->id;
          $txn->store();
        }
        else {
          $setup_intent = $pm->create_setup_intent($customer_id, $txn, $prd);

          $client_secret = $setup_intent->client_secret;

          $txn->trans_num = $setup_intent->id;
          $txn->store();

          $action = 'confirmSetup';
        }
      }
      else {
        if(!isset($sub) || !$sub instanceof MeprSubscription) {
          wp_send_json_error(__('Subscription not found', 'memberpress'));
        }

        $thank_you_page_args['subscription_id'] = $sub->id;

        $calculate_taxes = (bool) get_option('mepr_calculate_taxes');
        $tax_inclusive = $mepr_options->attr('tax_calc_type') == 'inclusive';
        $invoice_items = [];

        foreach($order_bump_transactions as $transaction) {
          $product = $transaction->product();

          if(empty($product->ID)) {
            wp_send_json(['error' => __('Product not found', 'memberpress')]);
          }

          if(!$transaction->is_payment_required()) {
            continue;
          }
          elseif($transaction->is_one_time_payment()) {
            if((float) $transaction->total > 0) {
              $amount = $calculate_taxes && !$tax_inclusive && $transaction->tax_rate > 0 ? (float) $transaction->amount : (float) $transaction->total;
              $invoice_items[] = $pm->build_invoice_item($amount, $transaction, $product, $customer_id);
            }
          }
          else {
            $subscription = $transaction->subscription();

            if(!($subscription instanceof MeprSubscription)) {
              wp_send_json_error(__('Subscription not found', 'memberpress'));
            }

            if($subscription->trial && $subscription->trial_days > 0) {
              if((float) $subscription->trial_total > 0) {
                $amount = $calculate_taxes && !$tax_inclusive && $transaction->tax_rate > 0 ? (float) $subscription->trial_amount : (float) $subscription->trial_total;
                $invoice_items[] = $pm->build_invoice_item($amount, $transaction, $product, $customer_id);
              }
            }
            else {
              $amount = $calculate_taxes && !$tax_inclusive && $transaction->tax_rate > 0 ? (float) $subscription->price : (float) $subscription->total;
              $invoice_items[] = $pm->build_invoice_item($amount, $transaction, $product, $customer_id);
            }
          }
        }

        // Use a SetupIntent for free trials, and create the subscription via webhook later
        if($sub->trial && $sub->trial_days > 0 && (float) $sub->trial_total <= 0.00 && empty($invoice_items)) {
          $setup_intent = $pm->create_setup_intent($customer_id, $txn, $prd);

          $client_secret = $setup_intent->client_secret;

          $txn->trans_num = $setup_intent->id;
          $txn->store();

          $action = 'confirmSetup';
        }
        else {
          $trial_days = 0;

          if($sub->trial && $sub->trial_days > 0) {
            $trial_days = $sub->trial_days;

            if((float) $sub->trial_total > 0.00) {
              $amount = $calculate_taxes && !$tax_inclusive && $txn->tax_rate > 0 ? (float) $sub->trial_amount : (float) $sub->trial_total;
              array_unshift($invoice_items, $pm->build_invoice_item($amount, $txn, $prd, $customer_id));
            }
          }
          elseif(count($invoice_items)) {
            // If there is no trial period and there is an order bump, set the trial days to cover one payment cycle and
            // add the first subscription payment to the trial amount
            $now = new DateTimeImmutable('now');
            $end = $now->modify(sprintf('+%d %s', $sub->period, $sub->period_type));
            $trial_days = $end->diff($now)->format('%a');

            $amount = $calculate_taxes && !$tax_inclusive && $txn->tax_rate > 0 ? (float) $sub->price : (float) $sub->total;

            array_unshift($invoice_items, $pm->build_invoice_item($amount, $txn, $prd, $customer_id));
          }

          $invoice_item_ids = [];

          foreach($invoice_items as $invoice_item) {
            $invoice_item = (object) $pm->send_stripe_request('invoiceitems', $invoice_item, 'post');
            $invoice_item_ids[] = $invoice_item->id;
          }

          try {
            $subscription = $pm->create_subscription($txn, $sub, $prd, $customer_id, null, true, $trial_days);

            if(empty($subscription->latest_invoice['payment_intent']['client_secret'])) {
              throw new MeprGatewayException(__('PaymentIntent not found', 'memberpress'));
            }

            $client_secret = $subscription->latest_invoice['payment_intent']['client_secret'];

            $sub->subscr_id = $subscription->id;
            $sub->store();

            $txn->trans_num = $subscription->latest_invoice['id'];
            $txn->store();
          }
          catch(Exception $e) {
            // Delete any created invoice items if the subscription failed to be created
            foreach($invoice_item_ids as $invoice_item_id) {
              try {
                $pm->send_stripe_request("invoiceitems/$invoice_item_id", [], 'delete');
              }
              catch(Exception $e) {
                // ignore any exception here, throw the original
              }
            }

            throw $e;
          }
        }
      }

      MeprHooks::do_action('mepr_stripe_payment_pending', $txn, $usr);
      MeprHooks::do_action('mepr-process-signup', $txn->amount, $usr, $prd->ID, $txn->id);
      MeprHooks::do_action('mepr-signup', $txn);

      $return_url = add_query_arg(
        [
          'txn_id' => $txn->id,
          'redirect_to' => urlencode($mepr_options->thankyou_page_url($thank_you_page_args)),
        ],
        $pm->notify_url('return')
      );

      wp_send_json([
        'action' => $action,
        'client_secret' => $client_secret,
        'return_url' => $return_url,
        'transaction_id' => $txn->id,
      ]);
    }
    catch(Exception $e) {
      wp_send_json([
        'error' => $e->getMessage(),
        'transaction_id' => $txn->id
      ]);
    }
  }

  /**
   * Ends execution with a JSON error response if the user is already subscribed to this product
   *
   * @param MeprUser $usr
   * @param MeprProduct $product
   */
  private function check_if_already_subscribed($usr, $product) {
    $mepr_options = MeprOptions::fetch();

    if($usr->is_already_subscribed_to($product->ID) && !$product->simultaneous_subscriptions && !$product->allow_renewal && !$product->allow_gifting) {
      wp_send_json(array(
        'error' => sprintf(
          /* translators: %1$s: product name, %2$s: open link tag, %3$s: close link tag */
          esc_html__('You are already subscribed to %1$s, %2$sclick here%3$s to view your subscriptions.', 'memberpress'),
          esc_html($product->post_title),
          '<a href="' . esc_url(add_query_arg(array('action' => 'subscriptions'), $mepr_options->account_page_url())) . '">',
          '</a>'
        )
      ));
    }
  }

  /**
   * Handle the Ajax request to create a SetupIntent for updating the payment method for a subscription
   */
  public function create_account_setup_intent() {
    $subscription_id = isset($_POST['subscription_id']) && is_numeric($_POST['subscription_id']) ? (int) $_POST['subscription_id'] : 0;

    if(empty($subscription_id)) {
      wp_send_json_error(__('Bad request', 'memberpress'));
    }

    if (!is_user_logged_in()) {
      wp_send_json_error(__('Sorry, you must be logged in to do this.', 'memberpress'));
    }

    if (!check_ajax_referer('mepr_process_update_account_form', '_mepr_nonce', false)) {
      wp_send_json_error(__('Security check failed.', 'memberpress'));
    }

    $sub = new MeprSubscription($subscription_id);

    if (!($sub instanceof MeprSubscription)) {
      wp_send_json_error(__('Subscription not found', 'memberpress'));
    }

    $usr = $sub->user();

    if ($usr->ID != get_current_user_id()) {
      wp_send_json_error(__('This subscription is for another user.', 'memberpress'));
    }

    $pm = $sub->payment_method();

    if (!($pm instanceof MeprStripeGateway)) {
      wp_send_json_error(__('Invalid payment gateway', 'memberpress'));
    }

    try {
      if(strpos($sub->subscr_id, 'sub_') === 0) {
        $subscription = $pm->retrieve_subscription($sub->subscr_id);
      }
      else {
        $subscription = $pm->get_customer_subscription($sub->subscr_id);
      }

      $setup_intent = $pm->create_update_setup_intent($subscription->customer);

      wp_send_json_success($setup_intent->client_secret);
    }
    catch(Exception $e) {
      wp_send_json_error($e->getMessage());
    }
  }

  /**
   * Handle the request to update the payment method for a subscription
   */
  public function update_payment_method() {
    $mepr_options = MeprOptions::fetch();

    $subscription_id = isset($_GET['subscription_id']) && is_numeric($_GET['subscription_id']) ? (int) $_GET['subscription_id'] : 0;
    $setup_intent_id = isset($_GET['setup_intent']) ? sanitize_text_field(wp_unslash($_GET['setup_intent'])) : '';
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';

    $return_url = add_query_arg([
      'action' => 'update',
      'sub' => $subscription_id,
    ], $mepr_options->account_page_url());

    if(empty($subscription_id) || empty($setup_intent_id)) {
      MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('Bad request', 'memberpress'))], $return_url));
    }

    if(!is_user_logged_in()) {
      MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('Sorry, you must be logged in to do this.', 'memberpress'))], $return_url));
    }

    if(!wp_verify_nonce($nonce, 'mepr_process_update_account_form')) {
      MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('Security check failed.', 'memberpress'))], $return_url));
    }

    $sub = new MeprSubscription($subscription_id);

    if(!($sub->id > 0)) {
      MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('Subscription not found', 'memberpress'))], $return_url));
    }

    $usr = $sub->user();

    if($usr->ID != get_current_user_id()) {
      MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('This subscription is for another user.', 'memberpress'))], $return_url));
    }

    $pm = $sub->payment_method();

    if(!($pm instanceof MeprStripeGateway)) {
      MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('Invalid payment gateway', 'memberpress'))], $return_url));
    }

    try {
      $setup_intent = $pm->retrieve_setup_intent($setup_intent_id);

      if($setup_intent->status != 'succeeded') {
        MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('The payment setup was unsuccessful, please try another payment method.', 'memberpress'))], $return_url));
      }

      $subscription = $pm->update_subscription_payment_method($sub, $usr, (object) $setup_intent->payment_method);

      if($subscription->latest_invoice && $subscription->latest_invoice['status'] == 'open') {
        try {
          $pm->retry_invoice_payment($subscription->latest_invoice['id']);
        } catch (Exception $e) {
          // Ignore
        }
      }

      MeprUtils::wp_redirect(add_query_arg(['message' => urlencode(__('Your account information was successfully updated.', 'memberpress'))], $return_url));
    } catch (Exception $e) {
      MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode($e->getMessage())], $return_url));
    }
  }

  /**
   * Handle the Ajax request to debug a checkout error
   */
  public function debug_checkout_error() {
    if (!MeprUtils::is_post_request() || !isset($_POST['data']) || !is_string($_POST['data'])) {
      wp_send_json_error();
    }

    $data = json_decode(wp_unslash($_POST['data']), true);

    if (!is_array($data)) {
      wp_send_json_error();
    }

    $allowed_keys = array(
      'text_status' => 'textStatus',
      'error_thrown' => 'errorThrown',
      'status' => 'jqXHR.status (200 expected)',
      'status_text' => 'jqXHR.statusText (OK expected)',
      'response_text' => 'jqXHR.responseText (JSON object expected)'
    );

    $content = 'INVALID SERVER RESPONSE' . "\n\n";

    foreach ($allowed_keys as $key => $label) {
      if (!array_key_exists($key, $data)) {
        continue;
      }

      ob_start();
      var_dump($data[$key]);
      $value = ob_get_clean();

      $content .= sprintf(
        "%s:\n%s\n",
        $label,
        $value
      );
    }

    $this->send_checkout_error_debug_email(
      $content,
      isset($data['transaction_id']) && is_numeric($data['transaction_id']) ? (int) $data['transaction_id'] : null,
      isset($data['customer_email']) && is_string($data['customer_email']) ? sanitize_text_field($data['customer_email']) : null
    );

    wp_send_json_success();
  }

  /**
   * Sends an email to the admin email addresses alerting them of the given checkout error
   *
   * @param string      $content
   * @param int|null    $transaction_id
   * @param string|null $customer_email
   */
  private function send_checkout_error_debug_email($content, $transaction_id = null, $customer_email = null) {
    if (MeprHooks::apply_filters('mepr_disable_checkout_error_debug_email', false)) {
      return;
    }

    $message = 'An error occurred during the MemberPress checkout which resulted in an error message being displayed to the customer. The transaction may not have fully completed.' . "\n\n";
    $message .= 'The error may have happened due to a plugin conflict or custom code, please carefully check the details below to identify the cause, or you can send this email to support@memberpress.com for help.' . "\n\n";

    if ($transaction_id) {
      $message .= sprintf("MemberPress transaction ID: %s\n", $transaction_id);

      if (!$customer_email) {
        $transaction = new MeprTransaction($transaction_id);
        $user = $transaction->user();

        if ($user->ID > 0 && $user->user_email) {
          $customer_email = $user->user_email;
        }
      }
    }

    if ($customer_email) {
      $message .= sprintf("Customer email: %s\n", $customer_email);
    }

    $message .= sprintf("Customer IP: %s\n", $_SERVER['REMOTE_ADDR']);
    $message .= sprintf("Customer User Agent: %s\n", !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '(empty)');
    $message .= sprintf("Date (UTC): %s\n\n", gmdate('Y-m-d H:i:s'));

    MeprUtils::wp_mail_to_admin('[MemberPress] IMPORTANT: Checkout error', $message . $content);
  }
}
