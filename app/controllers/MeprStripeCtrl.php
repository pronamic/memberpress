<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprStripeCtrl extends MeprBaseCtrl
{
  public function load_hooks() {
    add_action('wp_ajax_mepr_stripe_confirm_payment', array($this, 'confirm_payment'));
    add_action('wp_ajax_nopriv_mepr_stripe_confirm_payment', array($this, 'confirm_payment'));
    add_action('wp_ajax_mepr_stripe_create_payment_client_secret', array($this, 'create_payment_client_secret'));
    add_action('wp_ajax_nopriv_mepr_stripe_create_payment_client_secret', array($this, 'create_payment_client_secret'));
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

  public function create_payment_client_secret() {
    MeprHooks::do_action('mepr_stripe_before_create_payment_client_secret');

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

      if($pm->settings->stripe_checkout_enabled == 'on') {
        wp_send_json_error(__('Bad request', 'memberpress'));
      }

      $product = $txn->product();

      if(!$product->ID) {
        wp_send_json_error(__('Product not found', 'memberpress'));
      }

      if(!$product->is_one_time_payment()) {
        $sub = $txn->subscription();
      }

      $usr = $txn->user();

      if(!$usr->ID) {
        wp_send_json_error(__('User not found', 'memberpress'));
      }

      $is_user_logged_in = MeprUtils::is_user_logged_in();
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

      $usr = MeprUtils::get_currentuserinfo();

      if($usr instanceof MeprUser) {
        $is_user_logged_in = true;
      }
      else {
        $usr = new MeprUser();
        $is_user_logged_in = false;
      }

      $txn = new MeprTransaction();
      $txn->user_id = $is_user_logged_in ? $usr->ID : 0;
      $txn->gateway = $pm->id;
      $txn->product_id = isset($_POST['mepr_product_id']) ? (int) $_POST['mepr_product_id'] : 0;

      $product = $txn->product();

      if(empty($product->ID)) {
        wp_send_json_error(__('Sorry, we were unable to find the membership.', 'memberpress'));
      }

      // Set default price, adjust it later if coupon applies
      $price = $product->adjusted_price();

      // Default coupon object
      $cpn = (object) array('ID' => 0, 'post_title' => null);

      // Adjust membership price from the coupon code
      if(isset($_POST['mepr_coupon_code']) && !empty($_POST['mepr_coupon_code'])) {
        // Coupon object has to be loaded here or else txn create will record a 0 for coupon_id
        $cpn = MeprCoupon::get_one_from_code(sanitize_text_field($_POST['mepr_coupon_code']));

        if(($cpn !== false) || ($cpn instanceof MeprCoupon)) {
          $price = $product->adjusted_price($cpn->post_title);
        }
      }

      $txn->set_subtotal($price);

      // Set the coupon id of the transaction
      $txn->coupon_id = $cpn->ID;

      if(!$product->is_one_time_payment()) {
        $sub = new MeprSubscription();
        $sub->user_id = $is_user_logged_in ? $usr->ID : 0;
        $sub->gateway = $pm->id;
        $sub->load_product_vars($product, $cpn->post_title, true);
        $sub->maybe_prorate(); // sub to sub
      }
    }

    try {
      if(
        ($product->is_one_time_payment() && $txn->total <= 0) ||
        (!$product->is_one_time_payment() && isset($sub) && $sub instanceof MeprSubscription && $sub->total <= 0)
      ) {
        wp_send_json_success(['is_free_purchase' => true]);
      }

      $response = [];

      if($is_user_logged_in) {
        $customer_id = $pm->get_customer_id($usr);
      }
      else {
        $guest_customer_id = isset($_POST['customer_id']) ? sanitize_text_field(wp_unslash($_POST['customer_id'])) : '';
        $guest_customer_id_hash = isset($_POST['customer_id_hash']) ? sanitize_text_field(wp_unslash($_POST['customer_id_hash'])) : '';

        if(!empty($guest_customer_id) && !empty($guest_customer_id_hash) && hash_equals(wp_hash($guest_customer_id), $guest_customer_id_hash)) {
          $pm->send_stripe_request("customers/$guest_customer_id", $this->get_guest_customer_args(), 'post');
          $customer_id = $guest_customer_id;
        }
        else {
          $customer = (object) $pm->send_stripe_request('customers', $this->get_guest_customer_args(), 'post');
          $customer_id = $customer->id;

          $response = array_merge($response, [
            'customer_id' => $customer_id,
            'customer_id_hash' => wp_hash($customer_id),
          ]);
        }
      }

      if($product->is_one_time_payment()) {
        $payment_intent = $pm->create_payment_intent($txn, $product, $customer_id);

        $response = array_merge($response, [
          'client_secret' => $payment_intent->client_secret,
          'payment_intent_id' => $payment_intent->id,
          'payment_intent_id_hash' => wp_hash($payment_intent->id),
        ]);
      }
      else {
        if(!isset($sub) || !$sub instanceof MeprSubscription) {
          wp_send_json_error(__('Subscription not found', 'memberpress'));
        }

        if($sub->trial && $sub->trial_days > 0 && (float) $sub->trial_amount <= 0.00) {
          $setup_intent = $pm->create_setup_intent($customer_id);

          $response = array_merge($response, [
            'client_secret' => $setup_intent->client_secret,
            'setup_intent_id' => $setup_intent->id,
            'setup_intent_id_hash' => wp_hash($setup_intent->id),
          ]);
        }
        else {
          $subscription = $pm->create_subscription($txn, $sub, $product, $customer_id);

          if(empty($subscription->latest_invoice['payment_intent']['client_secret'])) {
            throw new MeprGatewayException(__('PaymentIntent not found', 'memberpress'));
          }

          $response = array_merge($response, [
            'client_secret' => $subscription->latest_invoice['payment_intent']['client_secret'],
            'subscription_id' => $subscription->id,
            'subscription_id_hash' => wp_hash($subscription->id),
          ]);
        }
      }

      wp_send_json_success($response);
    }
    catch(Exception $e) {
      if(stripos($e->getMessage(), 'The payment method type `link` is invalid.') === 0) {
        static $attempted;

        if(empty($attempted)) {
          $attempted = true;
          $mepr_options->integrations[$pm->id]['stripe_link_enabled'] = false;
          $mepr_options->store(false);
          self::create_payment_client_secret();
          return;
        }
      }

      wp_send_json_error($e->getMessage());
    }
  }

  /**
   * Get the data to send to Stripe for a guest customer (logged-out user)
   *
   * @return array
   */
  private function get_guest_customer_args() {
    $args = [];
    $email = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';

    if(!empty($email)) {
      $args['email'] = $email;
    }

    $user_first_name = isset($_POST['user_first_name']) ? sanitize_text_field(wp_unslash($_POST['user_first_name'])) : '';
    $user_last_name = isset($_POST['user_last_name']) ? sanitize_text_field(wp_unslash($_POST['user_last_name'])) : '';

    if(!empty($user_first_name)) {
      $name = $user_first_name;

      if(!empty($user_last_name)) {
        $name .= ' ' . $user_last_name;
      }

      $args['name'] = $name;
    }

    $address = [
      'line1' => isset($_POST['mepr-address-one']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-one'])) : '',
      'line2' => isset($_POST['mepr-address-two']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-two'])) : '',
      'city' => isset($_POST['mepr-address-city']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-city'])) : '',
      'state' => isset($_POST['mepr-address-state']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-state'])) : '',
      'country' => isset($_POST['mepr-address-country']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-country'])) : '',
      'postal_code' => isset($_POST['mepr-address-zip']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-zip'])) : '',
    ];

    foreach($address as $key => $value) {
      if(empty($value)) {
        unset($address[$key]);
      }
    }

    if(!empty($address) && !empty($address['line1'])) {
      $args['address'] = $address;
    }

    return $args;
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
      // Non-SPC
      $txn = new MeprTransaction($transaction_id);

      if(!$txn->id) {
        wp_send_json(array('error' => __('Transaction not found', 'memberpress')));
      }

      $pm = $txn->payment_method();

      if(!($pm instanceof MeprStripeGateway)) {
        wp_send_json(array('error' => __('Invalid payment gateway', 'memberpress')));
      }

      $product = $txn->product();

      if(!$product->ID) {
        wp_send_json(array('error' => __('Product not found', 'memberpress')));
      }

      $usr = $txn->user();

      if(!$usr->ID) {
        wp_send_json(array('error' => __('User not found', 'memberpress')));
      }

      // Prevent duplicate charges if the user is already subscribed
      $this->check_if_already_subscribed($usr, $product);
    }
    else {
      // We don't have a transaction ID (i.e. this is the Single Page Checkout), so let's create the user and transaction
      // This code is essentially the same as MeprCheckoutCtrl::process_signup_form
      $disable_checkout_password_fields = $mepr_options->disable_checkout_password_fields;

      // Validate the form post
      $mepr_current_url = isset($_POST['mepr_current_url']) && is_string($_POST['mepr_current_url']) ? sanitize_text_field(wp_unslash($_POST['mepr_current_url'])) : '';
      $errors = MeprHooks::apply_filters('mepr-validate-signup', MeprUser::validate_signup($_POST, array(), $mepr_current_url));

      if(!empty($errors)) {
        wp_send_json(array('errors' => $errors));
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
        } catch ( MeprCreateException $e ) {
          wp_send_json( array( 'error' => __( 'The user was unable to be saved.', 'memberpress' ) ) );
        }
      }

      // Create a new transaction and set our new membership details
      $txn = new MeprTransaction();
      $txn->user_id = $usr->ID;

      // Get the membership in place
      $txn->product_id = sanitize_text_field($_POST['mepr_product_id']);
      $product = $txn->product();

      if(empty($product->ID)) {
        wp_send_json(array('error' => __('Sorry, we were unable to find the membership.', 'memberpress')));
      }

      // Prevent duplicate charges if the user is already subscribed
      $this->check_if_already_subscribed($usr, $product);

      // If we're showing the fields on logged in purchases, let's save them here
      if(!$is_existing_user || ($is_existing_user && $mepr_options->show_fields_logged_in_purchases)) {
        MeprUsersCtrl::save_extra_profile_fields($usr->ID, true, $product, true);
        $usr = new MeprUser($usr->ID); //Re-load the user object with the metadata now (helps with first name last name missing from hooks below)
      }

      // Needed for autoresponders (SPC + Stripe + Free Trial issue)
      MeprHooks::do_action('mepr-signup-user-loaded', $usr);

      // Set default price, adjust it later if coupon applies
      $price = $product->adjusted_price();

      // Default coupon object
      $cpn = (object)array('ID' => 0, 'post_title' => null);

      // Adjust membership price from the coupon code
      if(isset($_POST['mepr_coupon_code']) && !empty($_POST['mepr_coupon_code'])) {
        // Coupon object has to be loaded here or else txn create will record a 0 for coupon_id
        $cpn = MeprCoupon::get_one_from_code(sanitize_text_field($_POST['mepr_coupon_code']));

        if(($cpn !== false) || ($cpn instanceof MeprCoupon)) {
          $price = $product->adjusted_price($cpn->post_title);
        }
      }

      $txn->set_subtotal($price);

      // Set the coupon id of the transaction
      $txn->coupon_id = $cpn->ID;

      // Figure out the Payment Method
      if(isset($_POST['mepr_payment_method']) && !empty($_POST['mepr_payment_method'])) {
        $txn->gateway = sanitize_text_field($_POST['mepr_payment_method']);
      }

      $pm = $txn->payment_method();

      if (!($pm instanceof MeprStripeGateway)) {
        wp_send_json(array('error' => __('Invalid payment gateway', 'memberpress')));
      }

      // Create a new subscription
      if($product->is_one_time_payment()) {
        $signup_type = 'non-recurring';
      }
      else {
        $signup_type = 'recurring';

        $sub = new MeprSubscription();
        $sub->user_id = $usr->ID;
        $sub->gateway = $pm->id;
        $sub->load_product_vars($product, $cpn->post_title, true);
        $sub->maybe_prorate(); // sub to sub
        $sub->store();

        $txn->subscription_id = $sub->id;
      }

      $txn->store();

      if(empty($txn->id)) {
        // Don't want any loose ends here if the $txn didn't save for some reason
        if($signup_type==='recurring' && ($sub instanceof MeprSubscription)) {
          $sub->destroy();
        }

        wp_send_json(array('error' => __('Sorry, we were unable to create a transaction.', 'memberpress')));
      }
    }

    try {
      if ($mode == 'stripe_checkout') {
        if (!isset($sub)) { $sub = $txn->subscription(); }

        MeprHooks::do_action('mepr-process-signup', $txn->amount, $usr, $product->ID, $txn->id);

        $pm->create_checkout_session(
          $txn,
          $product,
          $usr,
          $sub
        );

        return;
      }

      $action = 'confirmPayment';
      $thank_you_page_args = [
        'membership' => sanitize_title($product->post_title),
        'membership_id' => $product->ID,
        'transaction_id' => $txn->id,
      ];

      if($product->is_one_time_payment()) {
        $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field(wp_unslash($_POST['payment_intent_id'])) : '';
        $payment_intent_id_hash = isset($_POST['payment_intent_id_hash']) ? sanitize_text_field(wp_unslash($_POST['payment_intent_id_hash'])) : '';

        if(!empty($payment_intent_id) && !empty($payment_intent_id_hash) && hash_equals(wp_hash($payment_intent_id), $payment_intent_id_hash)) {
          $args = MeprHooks::apply_filters('mepr_stripe_update_payment_intent_args', [
            'metadata' => [
              'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
              'transaction_id' => $txn->id,
              'site_url' => get_site_url(),
              'ip_address' => MeprAntiCardTestingCtrl::get_ip(),
            ],
          ], $txn);

          $payment_intent = (object) $pm->send_stripe_request("payment_intents/$payment_intent_id", $args);

          $customer_id = $usr->get_stripe_customer_id($pm->get_meta_gateway_id());

          if(!is_string($customer_id) || strpos($customer_id, 'cus_') !== 0) {
            $usr->set_stripe_customer_id($pm->get_meta_gateway_id(), $payment_intent->customer);
          }

          $pm->update_customer($payment_intent->customer, $usr);

          $txn->trans_num = $payment_intent->id;
          $txn->store();
        }
        else {
          wp_send_json(array(
            'error' => __('Invalid PaymentIntent ID', 'memberpress'),
            'transaction_id' => $txn->id
          ));
        }
      }
      else {
        $sub = $txn->subscription();

        if(!($sub instanceof MeprSubscription)) {
          wp_send_json(array(
            'error' => __('Subscription not found', 'memberpress'),
            'transaction_id' => $txn->id
          ));
        }

        if($sub->trial && $sub->trial_days > 0 && (float) $sub->trial_amount <= 0.00) {
          $setup_intent_id = isset($_POST['setup_intent_id']) ? sanitize_text_field(wp_unslash($_POST['setup_intent_id'])) : '';
          $setup_intent_id_hash = isset($_POST['setup_intent_id_hash']) ? sanitize_text_field(wp_unslash($_POST['setup_intent_id_hash'])) : '';

          if(!empty($setup_intent_id) && !empty($setup_intent_id_hash) && hash_equals(wp_hash($setup_intent_id), $setup_intent_id_hash)) {
            $setup_intent = (object) $pm->send_stripe_request("setup_intents/$setup_intent_id", [
              'metadata' => [
                'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
                'transaction_id' => $txn->id,
                'site_url' => get_site_url(),
                'ip_address' => MeprAntiCardTestingCtrl::get_ip(),
              ],
            ]);

            $customer_id = $usr->get_stripe_customer_id($pm->get_meta_gateway_id());

            if(!is_string($customer_id) || strpos($customer_id, 'cus_') !== 0) {
              $usr->set_stripe_customer_id($pm->get_meta_gateway_id(), $setup_intent->customer);
            }

            $pm->update_customer($setup_intent->customer, $usr);

            $txn->trans_num = $setup_intent->id;
            $txn->store();

            $action = 'confirmSetup';
            $thank_you_page_args['subscription_id'] = $sub->id;
          }
          else {
            wp_send_json(array(
              'error' => __('Invalid SetupIntent ID', 'memberpress'),
              'transaction_id' => $txn->id
            ));
          }
        }
        else {
          $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field(wp_unslash($_POST['subscription_id'])) : '';
          $subscription_id_hash = isset($_POST['subscription_id_hash']) ? sanitize_text_field(wp_unslash($_POST['subscription_id_hash'])) : '';

          if(!empty($subscription_id) && !empty($subscription_id_hash) && hash_equals(wp_hash($subscription_id), $subscription_id_hash)) {
            $subscription = (object) $pm->send_stripe_request("subscriptions/$subscription_id", [
              'metadata' => [
                'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
                'transaction_id' => $txn->id,
                'site_url' => get_site_url(),
                'ip_address' => MeprAntiCardTestingCtrl::get_ip(),
              ],
            ], 'post');

            $customer_id = $usr->get_stripe_customer_id($pm->get_meta_gateway_id());

            if(!is_string($customer_id) || strpos($customer_id, 'cus_') !== 0) {
              $usr->set_stripe_customer_id($pm->get_meta_gateway_id(), $subscription->customer);
            }

            $pm->update_customer($subscription->customer, $usr);

            $sub->subscr_id = $subscription->id;
            $sub->store();

            $txn->trans_num = $subscription->id;
            $txn->store();

            $thank_you_page_args['subscription_id'] = $sub->id;
          }
          else {
            wp_send_json(array(
              'error' => __('Invalid Subscription ID', 'memberpress'),
              'transaction_id' => $txn->id
            ));
          }
        }
      }

      MeprHooks::do_action('mepr_stripe_payment_pending', $txn, $usr);
      MeprHooks::do_action('mepr-process-signup', $txn->amount, $usr, $product->ID, $txn->id);
      MeprHooks::do_action('mepr-signup', $txn);

      wp_send_json([
        'action' => $action,
        'return_url' => $mepr_options->thankyou_page_url($thank_you_page_args),
        'transaction_id' => $txn->id,
      ]);
    } catch (Exception $e) {
      wp_send_json(array(
        'error' => $e->getMessage(),
        'transaction_id' => $txn->id
      ));
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
          /* translators: %1$s: open link tag, %2$s: close link tag */
          esc_html__('You are already subscribed to this item, %1$sclick here%2$s to view your subscriptions.', 'memberpress'),
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

      $setup_intent = $pm->create_setup_intent($subscription->customer);

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

    if(!($sub instanceof MeprSubscription)) {
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
