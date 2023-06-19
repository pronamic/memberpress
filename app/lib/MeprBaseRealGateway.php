<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

abstract class MeprBaseRealGateway extends MeprBaseGateway {
  /**
   * Activate the subscription
   *
   * Also sets up the grace period confirmation transaction (if enabled).
   *
   * @param MeprTransaction  $txn The MemberPress transaction
   * @param MeprSubscription $sub The MemberPress subscription
   * @param bool $set_trans_num Whether to set the txn trans_num to the sub subscr_id
   */
  public function activate_subscription(MeprTransaction $txn, MeprSubscription $sub, $set_trans_num = true) {
    $mepr_options = MeprOptions::fetch();

    $sub->status = MeprSubscription::$active_str;
    $sub->created_at = gmdate('c');
    $sub->store();

    // If trial amount is zero then we've got to make sure the confirmation txn lasts through the trial
    if($sub->trial && $sub->trial_amount <= 0.00) {
      $trial_days = $sub->trial_days;

      if ( !$mepr_options->disable_grace_init_days && $mepr_options->grace_init_days > 1 ) {
        $trial_days += $mepr_options->grace_init_days;
      }

      $expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($trial_days), 'Y-m-d 23:59:59');
    } elseif(!$mepr_options->disable_grace_init_days && $mepr_options->grace_init_days > 0) {
      $expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($mepr_options->grace_init_days), 'Y-m-d 23:59:59');
    } else {
      $expires_at = $txn->created_at; // Expire immediately
    }

    if($set_trans_num) {
      $txn->trans_num = $sub->subscr_id;
    }

    $txn->status = MeprTransaction::$confirmed_str;
    $txn->txn_type = MeprTransaction::$subscription_confirmation_str;
    $txn->expires_at = $expires_at;
    $txn->set_subtotal(0.00); // Just a confirmation txn

    // Ensure that the `mepr-txn-store` hook is called with an active subscription
    $txn->store(true);
  }

  /**
   * Processes an order
   *
   * If there is an order bump, an order will be created and a transaction created for each order bump.
   * If no payment is due, it will redirect to the thank-you page.
   *
   * @param  MeprTransaction   $txn                 The transaction for the main product being purchased
   * @param  MeprProduct[]     $order_bump_products The array of order bump products
   * @return MeprTransaction[]                      The array of order bump transactions
   * @throws Exception
   */
  public function process_order(MeprTransaction $txn, array $order_bump_products = array()) {
    $prd = $txn->product();
    $cpn = $txn->coupon();
    $payment_required = $prd->is_payment_required($cpn instanceof MeprCoupon ? $cpn->post_title : null);
    $order_bumps = [];
    $order = $txn->order();

    if(count($order_bump_products)) {
      $order = new MeprOrder();
      $order->user_id = $txn->user_id;
      $order->primary_transaction_id = $txn->id;
      $order->gateway = $this->id;
      $order->store();

      $txn->order_id = $order->id;
      $txn->store();

      $sub = $txn->subscription();

      if($sub instanceof MeprSubscription) {
        $sub->order_id = $order->id;
        $sub->store();
      }

      foreach($order_bump_products as $product) {
        list($transaction) = MeprCheckoutCtrl::prepare_transaction(
          $product,
          $order->id,
          $txn->user_id,
          $this->id
        );

        if($product->is_payment_required()) {
          $payment_required = true;
        }

        $order_bumps[] = $transaction;
      }
    }

    if(!$payment_required) {
      MeprTransaction::create_free_transaction($txn, false);

      foreach($order_bumps as $order_bump_txn) {
        MeprTransaction::create_free_transaction($order_bump_txn, false);
      }

      if($order instanceof MeprOrder) {
        $order->status = 'complete';
        $order->gateway = MeprTransaction::$free_gateway_str;
        $order->store();
      }

      $mepr_options = MeprOptions::fetch();
      $sanitized_title = sanitize_title($prd->post_title);
      $query_params = array('membership' => $sanitized_title, 'trans_num' => $txn->trans_num, 'membership_id' => $prd->ID);

      MeprUtils::wp_redirect($mepr_options->thankyou_page_url(build_query($query_params)));
    }

    return $order_bumps;
  }
}
