<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );
}

require_once( MEPR_GATEWAYS_PATH . '/authorizenet/client.php' );

class MeprAuthorizeProfileGateway extends MeprBaseRealGateway {
  public static $order_invoice_str = '_mepr_authnet_order_invoice';

  public $key;

  /** @var MeprArtificialAuthorizeNetProfileHttpClient */
  protected $client = null;

  /**
   * Mainly used in order bumps , so we only do 1$ validating transaction on the first bump
   * In the following up bumps we know this card is good or not already so we don't need validation
   *
   * @var boolean
   */
  protected $payment_validated;

  /** Used in the view to identify the gateway */
  public function __construct() {
    $this->name         = __( "Authorize.net Profile", 'memberpress' );
    $this->key          = __( 'authorizeprofile', 'memberpress' );
    $this->has_spc_form = true;
    $this->set_defaults();

    $this->capabilities = array(
      'process-credit-cards',
      'process-payments',
      'process-refunds',
      'create-subscriptions',
      'cancel-subscriptions',
      'update-subscriptions',
      'send-cc-expirations',
      'order-bumps',
      'multiple-subscriptions',
      'subscription-trial-payment'
    );

    // Setup the notification actions for this gateway
    $this->notifiers     = array(
      'whk' => 'webhook_listener',
    );
    $this->message_pages = array();
  }

  public function load( $settings ) {
    $this->settings = (object) $settings;
    $this->set_defaults();
  }

  public function set_defaults() {
    if ( ! isset( $this->settings ) ) {
      $this->settings = array();
    }

    $this->settings = (object) array_merge(
      array(
        'gateway'         => get_class( $this ),
        'id'              => $this->generate_id(),
        'label'           => '',
        'use_label'       => true,
        'icon'            => MEPR_IMAGES_URL . '/checkout/cards.png',
        'use_icon'        => true,
        'desc'            => __( 'Pay with your credit card via Authorize.net', 'memberpress' ),
        'use_desc'        => true,
        'login_name'      => '',
        'transaction_key' => '',
        'signature_key'   => '',
        'public_key'      => '',
        'force_ssl'       => false,
        'debug'           => false,
        'test_mode'       => false,
        'aimUrl'          => '',
        'arbUrl'          => '',
      ),
      (array) $this->settings
    );

    $this->id        = $this->settings->id;
    $this->label     = $this->settings->label;
    $this->use_label = $this->settings->use_label;
    $this->icon      = $this->settings->icon;
    $this->use_icon  = $this->settings->use_icon;
    $this->desc      = $this->settings->desc;
    $this->use_desc  = $this->settings->use_desc;
    //$this->recurrence_type = $this->settings->recurrence_type;

    if ( $this->is_test_mode() ) {
      $this->settings->aimUrl = 'https://test.authorize.net/gateway/transact.dll';
      $this->settings->arbUrl = 'https://apitest.authorize.net/xml/v1/request.api';
    } else {
      $this->settings->aimUrl = 'https://secure2.authorize.net/gateway/transact.dll';
      $this->settings->arbUrl = 'https://api2.authorize.net/xml/v1/request.api';
    }

    // An attempt to correct people who paste in spaces along with their credentials
    $this->settings->login_name      = trim( $this->settings->login_name );
    $this->settings->transaction_key = trim( $this->settings->transaction_key );
    $this->settings->signature_key   = trim( $this->settings->signature_key );
    $this->settings->public_key      = trim( $this->settings->public_key );
  }

  public function log( $data ) {
    if ( ! defined( 'WP_MEPR_DEBUG' ) ) {
      return;
    }

    file_put_contents( WP_CONTENT_DIR . '/authorize-net.log', print_r( $data, true ) . PHP_EOL, FILE_APPEND );
  }

  /**
   * Webhook listener. Responds to select Auth.net webhook notifications.
   *
   */
  public function webhook_listener() {
    $this->log( $_REQUEST );
    $this->log( $_POST );
    $this->log( $_GET );
    $this->log( file_get_contents( "php://input" ) );
    $this->email_status(
      "Webhook Just Came In (" . $_SERVER['REQUEST_METHOD'] . "):\n" . MeprUtils::object_to_string( $_REQUEST, true ) . "\n",
      $this->settings->debug
    );
    require_once( __DIR__ . '/MeprAuthorizeWebhooks.php' );
    $webhook_handler = new MeprAuthorizeWebhooks( $this->settings, $this->getHttpClient() );
    try {
      $webhook_handler->process_webhook();
    } catch ( Exception $e ) {
      MeprUtils::error_log( 'MeprAuthorizeGateway Webhook Error: ' . $e->getMessage() );
    }
  }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  public function display_options_form() {
    $mepr_options = MeprOptions::fetch();

    $login_name    = trim( $this->settings->login_name );
    $txn_key       = trim( $this->settings->transaction_key );
    $signature_key = trim( $this->settings->signature_key );
    $public_key    = trim( $this->settings->public_key );
    $test_mode     = ( $this->settings->test_mode == 'on' or $this->settings->test_mode == true );
    $debug         = ( $this->settings->debug == 'on' or $this->settings->debug == true );
    ?>
    <table>
      <tr>
        <td><?php _e( 'API Login ID*:', 'memberpress' ); ?></td>
        <td><input type="text" class="mepr-auto-trim"
                   name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id; ?>][login_name]"
                   value="<?php echo $login_name; ?>"/></td>
      </tr>
      <tr>
        <td><?php _e( 'Transaction Key*:', 'memberpress' ); ?></td>
        <td><input type="text" class="mepr-auto-trim"
                   name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id; ?>][transaction_key]"
                   value="<?php echo $txn_key; ?>"/></td>
      </tr>
      <tr>
        <td><?php _e( 'Signature Key*:', 'memberpress' ); ?></td>
        <td><input type="text" class="mepr-auto-trim"
                   name="<?php echo esc_attr($mepr_options->integrations_str); ?>[<?php echo esc_attr($this->id); ?>][signature_key]"
                   value="<?php echo esc_attr($signature_key); ?>"/></td>
      </tr>
      <tr>
        <td><?php _e( 'Public Key*:', 'memberpress' ); ?></td>
        <td><input type="text" class="mepr-auto-trim"
                   name="<?php echo esc_attr($mepr_options->integrations_str); ?>[<?php echo esc_attr($this->id); ?>][public_key]"
                   value="<?php echo esc_attr($public_key); ?>"/></td>
      </tr>
      <tr>
        <td colspan="2"><input type="checkbox"
                               name="<?php echo esc_attr($mepr_options->integrations_str); ?>[<?php echo esc_attr($this->id); ?>][test_mode]"<?php checked(
            $test_mode
          ); ?> />&nbsp;<?php _e( 'Use Authorize.net Sandbox', 'memberpress' ); ?>
        </td>
      </tr>
      <tr>
        <td><?php _e( 'Webhook URL:', 'memberpress' ); ?></td>
        <td>
          <?php MeprAppHelper::clipboard_input( $this->notify_url( 'whk' ) ); ?>
        </td>
      </tr>
    </table>
    <?php
  }

  private function get_public_key() {
    if ( empty( $this->settings->public_key ) ) {
      return '5FcB6WrfHGS76gHW3v7btBCE3HuuBuke9Pj96Ztfn5R32G5ep42vne7MCWZtAucY';
    }

    return $this->settings->public_key;
  }

  // SPC enabled
  public function process_payment_form( $transaction ) {
    $this->process_signup_form( $transaction );
  }

  public function record_payment() {
    // TODO: Implement record_payment() method.
  }

  public function process_refund( MeprTransaction $txn ) {
    $this->log( $txn );
    $this->getHttpClient()->refundTransaction( $txn );
  }

  public function record_refund() {
    // TODO: Implement record_refund() method.
  }

  public function record_subscription_payment() {
    // TODO: Implement record_subscription_payment() method.
  }

  public function record_payment_failure() {
    // TODO: Implement record_payment_failure() method.
  }

  public function process_trial_payment( $transaction ) {
    // TODO: Implement process_trial_payment() method.
  }

  public function record_trial_payment( $transaction ) {
    // TODO: Implement record_trial_payment() method.
  }

  public function process_create_subscription( $transaction ) {
    $this->process_signup_form( $transaction );
  }

  public function record_create_subscription() {
    // TODO: Implement record_create_subscription() method.
  }

  public function record_update_subscription() {
    // TODO: Implement record_update_subscription() method.
  }

  public function process_cancel_subscription( $subscription_id ) {
    $sub = new MeprSubscription( $subscription_id );
    $this->log( $subscription_id );
    $this->log( $_REQUEST );
    $subscription = $this->getHttpClient()->cancelSubscription( $sub->subscr_id );

    if ( $subscription ) {
      $_REQUEST['subscr_id'] = $subscription;
      $this->record_cancel_subscription();
    }
  }

  public function record_cancel_subscription() {
    $subscr_id = ( isset( $_REQUEST['subscr_id'] ) ) ? $_REQUEST['subscr_id'] : $_REQUEST['recurring_payment_id'];
    $sub       = MeprSubscription::get_one_by_subscr_id( $subscr_id );

    if ( ! $sub ) {
      return false;
    }

    // Seriously ... if sub was already cancelled what are we doing here?
    if ( $sub->status == MeprSubscription::$cancelled_str ) {
      return $sub;
    }

    $sub->status = MeprSubscription::$cancelled_str;
    $sub->store();

    if ( ! isset( $_REQUEST['silent'] ) || ( $_REQUEST['silent'] == false ) ) {
      MeprUtils::send_cancelled_sub_notices( $sub );
    }

    return $sub;
  }

  protected function getHttpClient() {
    if ($this->client !== null) {
        return $this->client;
    }

    $this->client = new MeprArtificialAuthorizeNetProfileHttpClient(
      $this->is_test_mode(),
      $this->settings->arbUrl,
      $this->id,
      $this->settings->login_name,
      $this->settings->transaction_key
    );

    return $this->client;
  }

  public function process_single_order($txn, $dataValue, $dataDescriptor) {
    $user                  = $txn->user();
    $client                = $this->getHttpClient();
    $authorizenet_customer = $client->getCustomerProfile( $user->ID );

    if ( $authorizenet_customer ) {
      $is_new_user = false;
      $newPaymentProfile = $client->createCustomerPaymentProfile( $user, $authorizenet_customer, $dataValue,
        $dataDescriptor );

      if ( $newPaymentProfile ) {
        $authorizenet_customer['newCustomerPaymentProfileId'] = $newPaymentProfile;
      }
    } else {
      $is_new_user = true;
      $client->createCustomerProfile( $user, $dataValue, $dataDescriptor );
      $authorizenet_customer = $client->getCustomerProfile( $user->ID );
    }

    $this->log( $authorizenet_customer );

    if ( $is_new_user || empty($dataDescriptor) || empty($dataValue) ) {
      // wait some time for the customer to occupy on authorizenet server
      sleep( 14 );
    }

    if ( $txn->is_one_time_payment() ) {
      $txn_num = $client->chargeCustomer( $authorizenet_customer, $txn );

      if ( ! empty( $txn ) ) {
        $txn->status    = MeprTransaction::$complete_str;
        $txn->trans_num = $txn_num;
        $txn->save();

        MeprUtils::send_signup_notices($txn);
        MeprUtils::send_transaction_receipt_notices($txn);

        add_filter( 'mepr-signup-checkout-url', function ( $uri, $txn ) {
          $mepr_options    = MeprOptions::fetch();
          $product         = new MeprProduct( $txn->product_id );
          $sanitized_title = sanitize_title( $product->post_title );

          return $mepr_options->thankyou_page_url( "membership_id={$txn->product_id}&membership={$sanitized_title}&trans_num={$txn->trans_num}" );
        }, 10, 2 );
      }
    } else {
      $sub = $txn->subscription();

      // This will only work before maybe_cancel_old_sub is run
      $upgrade           = $sub->is_upgrade();
      $downgrade         = $sub->is_downgrade();

      $event_txn = $sub->maybe_cancel_old_sub();

      if ( $upgrade ) {
        $this->upgraded_sub( $sub, $event_txn );
      } else if ( $downgrade ) {
        $this->downgraded_sub( $sub, $event_txn );
      } else {
        $this->new_sub( $sub, true );
      }

      // Charge a 1 dollar to validate payment source
      if ( apply_filters( 'mepr_authorize_profile_validation_txn_enabled', false ) && $this->payment_validated !== true ) {
          $validationTxn = clone $txn;
          $validationTxn->set_subtotal( 1 );
          $validationTxn->trans_num = $client->chargeCustomer( $authorizenet_customer, $validationTxn, false );
      }

      $subscr_id = $client->createSubscriptionFromCustomer( $authorizenet_customer, $txn, $sub, $dataDescriptor,
        $dataValue );

      if ( isset( $validationTxn ) ) {
        $client->voidTransaction( $validationTxn );
        $this->payment_validated = true;
      }

      if ( $subscr_id ) {
        $sub->subscr_id = $subscr_id;
        $sub->status    = \MeprSubscription::$active_str;
        $sub->save();
        $txn->status   = \MeprTransaction::$confirmed_str;
        $txn->txn_type = \MeprTransaction::$subscription_confirmation_str;

        if ( empty( $sub->trial ) ) {
          $txn->expires_at = MeprUtils::ts_to_mysql_date( time() + MeprUtils::days( 1 ), 'Y-m-d 23:59:59' );
        } else {
          $txn->status   = \MeprTransaction::$complete_str;
          $txn->txn_type = MeprTransaction::$payment_str;
        }

        $txn->save();
        MeprHooks::do_action('mepr-auth-net-subscription-initialized', $txn);
      }

      MeprUtils::send_signup_notices($txn);

      add_filter( 'mepr-signup-checkout-url', function ( $uri, $txn ) {
        $mepr_options    = MeprOptions::fetch();
        $product         = new MeprProduct( $txn->product_id );
        $sanitized_title = sanitize_title( $product->post_title );

        return $mepr_options->thankyou_page_url( "membership_id={$txn->product_id}&membership={$sanitized_title}&trans_num={$txn->trans_num}" );
      }, 10, 2 );
    }
  }

  /**
   * @param MeprTransaction $txn
   */
  public function process_signup_form( $txn ) {
    if ( ! isset( $_POST['dataValue'] ) && ! isset( $_POST['dataValue'] ) ) {
      return;
    }

    $order_bump_product_ids = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_map('intval', $_POST['mepr_order_bumps']) : [];
    $order_bump_products = MeprCheckoutCtrl::get_order_bump_products($txn->product_id, $order_bump_product_ids);
    $order_bumps = $this->process_order($txn, $order_bump_products);
    array_unshift($order_bumps, $txn);
    unset($_POST['mepr_order_bumps']);
    $dataValue = sanitize_text_field(wp_unslash($_POST['dataValue']));
    $dataDescriptor = sanitize_text_field(wp_unslash($_POST['dataDescriptor']));
    $this->log('data value' . $dataValue);
    $this->log('data descriptor' . $dataDescriptor);

    foreach ($order_bumps as $bump) {
      $this->process_single_order($bump, $dataValue, $dataDescriptor);
      $dataValue = null;
      $dataDescriptor = null;
    }

    if (isset($_POST['mepr_payment_method'])) {
      unset($_POST['mepr_payment_method']);
    }
  }

  public function display_payment_page( $txn ) {
    $payment_method      = $this;
    $public_key          = $this->get_public_key();
    $is_test             = $this->is_test_mode();
    $login_id            = $this->settings->login_name;
    $payment_form_action = 'mepr-authorize-net-payment-form';
    $txn                 = new MeprTransaction; //FIXME: This is simply for the action mepr-authorize-net-payment-form

    return MeprView::get_string( "/checkout/MeprAuthorizeProfileGateway/payment_gateway_fields", get_defined_vars() );
  }

  public function enqueue_payment_form_scripts() {
    wp_enqueue_script(
      'mepr-authorizenet-form',
      MEPR_GATEWAYS_URL . '/authorizenet/form.js',
      array( 'mepr-checkout-js', 'jquery.payment' ),
      MEPR_VERSION
    );
    wp_enqueue_style( 'mepr-authorizenet-form', MEPR_GATEWAYS_URL . '/authorizenet/form.css' );
    wp_enqueue_style( 'cardjs-css', MEPR_GATEWAYS_URL . '/authorizenet/card-js.min.css' );
    wp_enqueue_script( 'cardjs-js', MEPR_GATEWAYS_URL . '/authorizenet/card-js.min.js', ['jquery'], null, true );
  }

  public function display_payment_form( $amount, $user, $product_id, $transaction_id ) {
    $payment_method      = $this;
    $is_test             = $this->is_test_mode();
    $public_key          = $this->get_public_key();
    $login_id            = $this->settings->login_name;
    $payment_form_action = 'mepr-authorize-net-payment-form';
    $txn                 = new MeprTransaction; //FIXME: This is simply for the action mepr-authorize-net-payment-form
    $mepr_options        = MeprOptions::fetch();
    $prd                 = new MeprProduct( $product_id );
    $coupon              = false;
    $order_bump_product_ids = isset($_REQUEST['obs']) && is_array($_REQUEST['obs']) ? array_map('intval', $_REQUEST['obs']) : [];
    $txn = new MeprTransaction( $transaction_id );

    //Artifically set the price of the $prd in case a coupon was used
    if ( $prd->price != $amount ) {
      $coupon     = true;
      $prd->price = $amount;
    }

    $invoice = MeprTransactionsHelper::get_invoice( $txn );
    echo $invoice;
    ?>
    <form action="" method="post" id="mepr-authorizenet-payment-form">
      <input type="hidden" name="mepr_process_payment_form" value="Y"/>
      <input type="hidden" name="mepr_transaction_id" value="<?php echo esc_attr( $txn->id ); ?>"/>
      <?php
      foreach ($order_bump_product_ids as $orderId) {
        ?>
          <input type="hidden" name="mepr_order_bumps[]" value="<?php echo intval($orderId); ?>"/>
        <?php
      }
      ?>
      <div class="mepr-payment-method-desc-text mp_wrapper" style="display: block;">
        <?php
        echo MeprView::get_string( "/checkout/MeprAuthorizeProfileGateway/payment_gateway_fields", get_defined_vars() );
        ?>
        <br>
        <input type="submit" class="mepr-submit"
               value="<?php echo esc_attr( _x( 'Submit', 'ui', 'memberpress' ) ); ?>"/>
        <img src="<?php echo admin_url('images/loading.gif'); ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />
      </div>
    </form>
    <?php
  }

  public function validate_payment_form( $errors ) {
    // TODO: Implement validate_payment_form() method.
  }

  public function validate_options_form( $errors ) {
    // TODO: Implement validate_options_form() method.
  }


  /** Used to cancel a subscription by the given gateway. This method should be used
   * by the class to record a successful cancellation from the gateway. This method
   * should also be used by any IPN requests or Silent Posts.
   */
  public function process_update_subscription($sub_id) {
    $mepr_options = MeprOptions::fetch();

    $sub = new MeprSubscription($sub_id);
    if(!isset($sub->id) || (int)$sub->id <= 0)
      throw new MeprGatewayException( __('Your payment details are invalid, please check them and try again.', 'memberpress') );

    $usr = $sub->user();
    if(!isset($usr->ID) || (int)$usr->ID <= 0)
      throw new MeprGatewayException( __('Your payment details are invalid, please check them and try again.', 'memberpress') );

    $args = array( "refId" => $sub->id,
      "subscriptionId" => $sub->subscr_id,
      "subscription" => array(
        "payment" => array(
          "creditCard" => array(
            "cardNumber" => sanitize_text_field($_POST['update_cc_num']),
            "expirationDate" => sanitize_text_field($_POST['update_cc_exp_month']) . '-' . sanitize_text_field($_POST['update_cc_exp_year']),
            "cardCode" => sanitize_text_field($_POST['update_cvv_code'])
          )
        ),
      )
    );

    if (!empty($usr->first_name) && !empty($usr->last_name)) {
      $args['subscription']['billTo'] = array(
        "firstName" => $usr->first_name,
        "lastName" => $usr->last_name
      );
    }

    if($mepr_options->show_address_fields && $mepr_options->require_address_fields && isset($args['subscription']['billTo'])) {
      $args['subscription']['billTo'] =
        array_merge($args['subscription']['billTo'],
          array("address" => str_replace('&', '&amp;', get_user_meta($usr->ID, 'mepr-address-one', true)),
            "city" => get_user_meta($usr->ID, 'mepr-address-city', true),
            "state" => get_user_meta($usr->ID, 'mepr-address-state', true),
            "zip" => get_user_meta($usr->ID, 'mepr-address-zip', true),
            "country" => get_user_meta($usr->ID, 'mepr-address-country', true)));
    }

    if(isset($_POST['update_zip_post_code'])) {
      $args['subscription']['billTo']['zip'] = sanitize_text_field(wp_unslash($_POST['update_zip_post_code']));
    }

    $args = MeprHooks::apply_filters('mepr_authorize_update_subscription_args', $args, $sub);

    $res = $this->getHttpClient()->updateSubscription($args);

    return $res;
  }

  /** Displays the update account form on the subscription account page **/
  public function display_update_account_form($sub_id, $errors=array(), $message='') {
    $sub = new MeprSubscription($sub_id);

    $last4 = isset($_POST['update_cc_num']) ? substr(sanitize_text_field($_POST['update_cc_num']), -4) : $sub->cc_last4;
    $exp_month = isset($_POST['update_cc_exp_month']) ? sanitize_text_field($_POST['update_cc_exp_month']) : $sub->cc_exp_month;
    $exp_year = isset($_POST['update_cc_exp_year']) ? sanitize_text_field($_POST['update_cc_exp_year']) : $sub->cc_exp_year;

    // Only include the full cc number if there are errors
    if(strtolower($_SERVER['REQUEST_METHOD'])=='post' and empty($errors)) {
      $sub->cc_last4 = $last4;
      $sub->cc_exp_month = $exp_month;
      $sub->cc_exp_year = $exp_year;
      $sub->store();

      unset($_POST['update_cvv_code']); // Unset this for security
    }
    else { // If there are errors then show the full cc num ... if it's there
      $last4 = isset($_POST['update_cc_num']) ? sanitize_text_field($_POST['update_cc_num']) : $sub->cc_last4;
    }

    $ccv_code = (isset($_POST['update_cvv_code'])) ? sanitize_text_field($_POST['update_cvv_code']) : '';
    $exp = sprintf('%02d', $exp_month) . " / {$exp_year}";

    ?>
    <div class="mp_wrapper">
      <form action="" method="post" id="mepr_authorize_net_update_cc_form" class="mepr-checkout-form mepr-form" novalidate>
        <input type="hidden" name="_mepr_nonce" value="<?php echo wp_create_nonce('mepr_process_update_account_form'); ?>" />
        <div class="mepr_update_account_table">
          <div><strong><?php _e('Update your Credit Card information below', 'memberpress'); ?></strong></div>
          <?php MeprView::render('/shared/errors', get_defined_vars()); ?>
          <div class="mp-form-row">
            <label><?php _e('Credit Card Number', 'memberpress'); ?></label>
            <input type="text" class="mepr-form-input cc-number validation" pattern="\d*" autocomplete="cc-number" placeholder="<?php echo MeprUtils::cc_num($last4); ?>" required />
            <input type="hidden" class="mepr-cc-num" name="update_cc_num"/>
            <script>
                jQuery(document).ready(function($) {
                    $('input.cc-number').on('change blur', function (e) {
                        var num = $(this).val().replace(/ /g, '');
                        $('input.mepr-cc-num').val( num );
                    });
                });
            </script>
          </div>

          <input type="hidden" name="mepr-cc-type" class="cc-type" value="" />

          <div class="mp-form-row">
            <div class="mp-form-label">
              <label><?php _e('Expiration', 'memberpress'); ?></label>
              <span class="cc-error"><?php _e('Invalid Expiration', 'memberpress'); ?></span>
            </div>
            <input type="text" class="mepr-form-input cc-exp validation" value="<?php echo $exp; ?>" pattern="\d*" autocomplete="cc-exp" placeholder="mm/yy" required>
            <input type="hidden" class="cc-exp-month" name="update_cc_exp_month"/>
            <input type="hidden" class="cc-exp-year" name="update_cc_exp_year"/>
            <script>
                jQuery(document).ready(function($) {
                    $('input.cc-exp').on('change blur', function (e) {
                        var exp = $(this).payment('cardExpiryVal');
                        $( 'input.cc-exp-month' ).val( exp.month );
                        $( 'input.cc-exp-year' ).val( exp.year );
                    });
                });
            </script>
          </div>

          <div class="mp-form-row">
            <div class="mp-form-label">
              <label><?php _e('CVC', 'memberpress'); ?></label>
              <span class="cc-error"><?php _e('Invalid CVC Code', 'memberpress'); ?></span>
            </div>
            <input type="text" name="update_cvv_code" class="mepr-form-input card-cvc cc-cvc validation" pattern="\d*" autocomplete="off" required />
          </div>

          <div class="mp-form-row">
            <div class="mp-form-label">
              <label><?php _e('Zip code for Card', 'memberpress'); ?></label>
            </div>
            <input type="text" name="update_zip_post_code" class="mepr-form-input" autocomplete="off" value="" required />
          </div>
        </div>

        <div class="mepr_spacer">&nbsp;</div>

        <input type="submit" class="mepr-submit" value="<?php _e('Update Credit Card', 'memberpress'); ?>" />
        <img src="<?php echo admin_url('images/loading.gif'); ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />
        <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>
      </form>
    </div>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_update_account_form($errors=array()) {
    if( !isset($_POST['_mepr_nonce']) or empty($_POST['_mepr_nonce']) or
      !wp_verify_nonce($_POST['_mepr_nonce'], 'mepr_process_update_account_form') )
      $errors[] = __('An unknown error has occurred. Please try again.', 'memberpress');

    if(!isset($_POST['update_cc_num']) || empty($_POST['update_cc_num']))
      $errors[] = __('You must enter your Credit Card number.', 'memberpress');
    elseif(!$this->is_credit_card_valid($_POST['update_cc_num']))
      $errors[] = __('Your credit card number is invalid.', 'memberpress');

    if(!isset($_POST['update_cvv_code']) || empty($_POST['update_cvv_code']))
      $errors[] = __('You must enter your CVV code.', 'memberpress');

    return $errors;
  }

  /** Actually pushes the account update to the payment processor */
  public function process_update_account_form($sub_id) {
    return $this->process_update_subscription($sub_id);
  }

  public function is_test_mode() {
    return $this->settings->test_mode;
  }

  public function force_ssl() {
    return false;
  }

  /**
   * Returs the payment for and required fields for the gateway
   */
  public function spc_payment_fields() {
    $payment_method      = $this;
    $public_key          = $this->get_public_key();
    $is_test             = $this->is_test_mode();
    $login_id            = $this->settings->login_name;
    $payment_form_action = 'mepr-authorize-net-payment-form';
    $txn                 = new MeprTransaction; //FIXME: This is simply for the action mepr-authorize-net-payment-form

    return MeprView::get_string( "/checkout/MeprAuthorizeProfileGateway/payment_gateway_fields", get_defined_vars() );
  }

  public function process_suspend_subscription( $subscription_id ) {
    // TODO: Implement process_suspend_subscription() method.
  }

  public function record_suspend_subscription() {
    // TODO: Implement record_suspend_subscription() method.
  }

  public function process_resume_subscription( $subscription_id ) {
    // TODO: Implement process_resume_subscription() method.
  }

  public function record_resume_subscription() {
    // TODO: Implement record_resume_subscription() method.
  }

  public function process_payment( $transaction ) {
    $this->process_signup_form( $transaction );
  }
}
