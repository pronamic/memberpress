<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

#[AllowDynamicProperties]
class MeprArtificialGateway extends MeprBaseRealGateway
{
    /**
     * Used in the view to identify the gateway
     */
    public function __construct()
    {
        $this->name         = 'Offline Payment';
        $this->key          = 'offline';
        $this->has_spc_form = true;
        $this->set_defaults();

        $this->capabilities = [
            // 'process-payments',
            // 'create-subscriptions',
            // 'process-refunds',
            'cancel-subscriptions', // Yup we can cancel them here - needed for upgrade/downgrades
        // 'update-subscriptions',
        // 'suspend-subscriptions',
        // 'send-cc-expirations'.
        ];

        // Setup the notification actions for this gateway.
        $this->notifiers = [];
    }

    /**
     * Loads the gateway settings.
     *
     * @param array $settings The settings to load.
     *
     * @return void
     */
    public function load($settings)
    {
        $this->settings = (object)$settings;
        $this->set_defaults();
    }

    /**
     * Sets the default settings for the gateway.
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
                'gateway'                 => 'MeprArtificialGateway',
                'id'                      => $this->generate_id(),
                'label'                   => '',
                'use_label'               => true,
                'icon'                    => '',
                'use_icon'                => true,
                'desc'                    => '',
                'use_desc'                => true,
                'manually_complete'       => false,
                'always_send_welcome'     => false,
                'no_cancel_up_down_grade' => false,
                'email'                   => '',
                'sandbox'                 => false,
                'debug'                   => false,
            ],
            (array)$this->settings
        );

        $this->id        = $this->settings->id;
        $this->label     = $this->settings->label;
        $this->use_label = $this->settings->use_label;
        $this->icon      = $this->settings->icon;
        $this->use_icon  = $this->settings->use_icon;
        $this->desc      = $this->settings->desc;
        $this->use_desc  = $this->settings->use_desc;
        // $this->recurrence_type = $this->settings->recurrence_type;
    }

    /**
     * Hides the update link when using offline gateway.
     *
     * @param MeprSubscription $subscription The subscription object.
     *
     * @return boolean Always returns true.
     */
    protected function hide_update_link($subscription)
    {
        return true;
    }

    /**
     * Returns the payment fields description for the SPC form.
     *
     * @return string The payment fields description.
     */
    public function spc_payment_fields()
    {
        return $this->settings->desc;
    }

    /**
     * Used for capturing offline gateway events.
     *
     * @param MeprTransaction $txn The transaction object.
     *
     * @return void
     */
    public static function capture_txn_status_for_events($txn)
    {
        $mepr_options = MeprOptions::fetch();
        $gateway      = $mepr_options->payment_method($txn->gateway);

        if (self::event_exists_already($txn)) {
            return;
        }

        if ($gateway !== false && isset($gateway->settings->gateway) && $gateway->settings->gateway == 'MeprArtificialGateway') {
            MeprEvent::record('offline-payment-' . $txn->status, $txn);

            if ($txn->status == MeprTransaction::$complete_str) {
                $sub = $txn->subscription();
                if ($sub && $sub instanceof MeprSubscription) {
                    $sub->limit_payment_cycles();
                }

                self::maybe_cancel_old_sub($txn, $gateway);
                MeprUtils::send_transaction_receipt_notices($txn);
            }
        }
    }

    /**
     * Checks if an event already exists for a transaction.
     *
     * @param MeprTransaction $txn The transaction to check.
     *
     * @return boolean True if the event exists, false otherwise.
     */
    public static function event_exists_already($txn)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        return $wpdb->get_results("SELECT * FROM {$mepr_db->events} WHERE event = 'offline-payment-{$txn->status}' AND evt_id = {$txn->id} AND evt_id_type = 'transactions'");
    }

    /**
     * Used to send data to a given payment gateway. In gateways which redirect
     * before this step is necessary this method should just be left blank.
     *
     * @param MeprTransaction $txn The transaction to process.
     *
     * @return MeprTransaction|void The processed transaction.
     */
    public function process_payment($txn)
    {
        if (isset($txn) && $txn instanceof MeprTransaction) {
            $usr = new MeprUser($txn->user_id);
            $prd = new MeprProduct($txn->product_id);
        } else {
            return;
        }

        $upgrade   = $txn->is_upgrade();
        $downgrade = $txn->is_downgrade();

        $event_txn = $txn->maybe_cancel_old_sub();

        if ($upgrade) {
            $this->upgraded_sub($txn, $event_txn);
        } elseif ($downgrade) {
            $this->downgraded_sub($txn, $event_txn);
        } else {
            $this->new_sub($txn);
        }

        $txn->gateway   = $this->id;
        $txn->trans_num = 't_' . uniqid();
        $txn->store();

        if (!$this->settings->manually_complete) {
            $txn->status = MeprTransaction::$complete_str;
            $txn->store(); // Need to store here so the event will show as "complete" when firing the hooks
            // The receipt is set when the transaction is automatically set to complete (see: capture_txn_status_for_events)
            // MeprUtils::send_transaction_receipt_notices($txn);.
            MeprUtils::send_signup_notices($txn);
        } else {
            if ($this->settings->always_send_welcome) {
                MeprUtils::send_signup_notices($txn, false, true);
            } elseif (!$usr->signup_notice_sent) {
                MeprUtils::send_notices($txn, null, 'MeprAdminSignupEmail');
                MeprUtils::send_notices($txn, null, 'MeprAdminNewOneOffEmail');
                $usr->signup_notice_sent = true;
                $usr->store();
            }

            MeprEvent::record('member-signup-completed', $usr, (object)$txn->rec);
        }

        return $txn;
    }

    /**
     * Used to record a successful recurring payment by the given gateway. It
     * should have the ability to record a successful payment or a failure. It is
     * this method that should be used when receiving an IPN from PayPal or a
     * Silent Post from Authorize.net.
     *
     * @return void
     */
    public function record_subscription_payment()
    {
        // Doesn't happen in test mode ... no need.
    }

    /**
     * Used to record a declined payment.
     */
    public function record_payment_failure()
    {
        // No need for this here.
    }

    /**
     * Used to record a successful payment by the given gateway. It should have
     * the ability to record a successful payment or a failure. It is this method
     * that should be used when receiving an IPN from PayPal or a Silent Post
     * from Authorize.net.
     *
     * @return void
     */
    public function record_payment()
    {
        // This happens manually in test mode.
    }

    /**
     * This method should be used by the class to record a successful refund from
     * the gateway. This method should also be used by any IPN requests or Silent Posts.
     *
     * @param MeprTransaction $txn The transaction to process.
     *
     * @return void
     */
    public function process_refund(MeprTransaction $txn)
    {
        // This happens manually in test mode.
    }

    /**
     * This method should be used by the class to record a successful refund from
     * the gateway. This method should also be used by any IPN requests or Silent Posts.
     *
     * @return void
     */
    public function record_refund()
    {
        // This happens manually in test mode.
    }

    // Not needed in the Artificial gateway.
    /**
     * Processes a trial payment.
     *
     * @param MeprTransaction $transaction The transaction to process.
     *
     * @return void
     */
    public function process_trial_payment($transaction)
    {
    }

    /**
     * Records a trial payment.
     *
     * @param MeprTransaction $transaction The transaction to process.
     *
     * @return void
     */
    public function record_trial_payment($transaction)
    {
    }

    /**
     * Used to send subscription data to a given payment gateway. In gateways
     * which redirect before this step is necessary this method should just be
     * left blank.
     *
     * @param MeprTransaction $txn The transaction to process.
     *
     * @return void
     */
    public function process_create_subscription($txn)
    {
        if (isset($txn) && $txn instanceof MeprTransaction) {
            $usr = new MeprUser($txn->user_id);
            $prd = new MeprProduct($txn->product_id);
        } else {
            return;
        }

        $sub = $txn->subscription();

        // Not super thrilled about this but there are literally
        // no automated recurring profiles when paying offline.
        $sub->subscr_id  = 'ts_' . uniqid();
        $sub->status     = MeprSubscription::$active_str;
        $sub->created_at = gmdate('c');
        $sub->gateway    = $this->id;

        // If this subscription has a paid trail, we need to change the price of this transaction to the trial price duh.
        if ($sub->trial) {
            $mepr_options    = MeprOptions::fetch();
            $calculate_taxes = (bool) get_option('mepr_calculate_taxes');
            $tax_inclusive   = $mepr_options->attr('tax_calc_type') == 'inclusive';
            $txn->set_subtotal($calculate_taxes && $tax_inclusive ? $sub->trial_total : $sub->trial_amount);
            $expires_ts      = time() + MeprUtils::days($sub->trial_days);
            $txn->expires_at = gmdate('c', $expires_ts);
        }

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

        $sub->store();

        $txn->gateway   = $this->id;
        $txn->trans_num = 't_' . uniqid();
        $txn->store();

        if (!$this->settings->manually_complete) {
            $txn->status = MeprTransaction::$complete_str;
            $txn->store(); // Need to store here so the event will show as "complete" when firing the hooks.
            MeprUtils::send_signup_notices($txn);
        } else {
            if ($this->settings->always_send_welcome) {
                MeprUtils::send_signup_notices($txn, false, true);
            } elseif (!$usr->signup_notice_sent) {
                MeprUtils::send_notices($txn, null, 'MeprAdminSignupEmail');
                $usr->signup_notice_sent = true;
                $usr->store();
            }

            // Apparently this gets sent already somewhere else
            // MeprUtils::send_notices($sub, null, 'MeprAdminNewSubEmail');.
            MeprEvent::record('member-signup-completed', $usr, (object)$txn->rec);
        }

        return [
            'subscription' => $sub,
            'transaction'  => $txn,
        ];
    }

    /**
     * Used to record a successful subscription by the given gateway. It should have
     * the ability to record a successful subscription or a failure. It is this method
     * that should be used when receiving an IPN from PayPal or a Silent Post
     * from Authorize.net.
     *
     * @return void
     */
    public function record_create_subscription()
    {
        // No reason to separate this out without webhooks/postbacks/ipns.
    }

    /**
     * Processes the update of a subscription.
     *
     * @param integer $sub_id The ID of the subscription to update.
     *
     * @return void
     */
    public function process_update_subscription($sub_id)
    {
        // This happens manually in test mode.
    }

    /**
     * This method should be used by the class to record a successful cancellation
     * from the gateway. This method should also be used by any IPN requests or
     * Silent Posts.
     */
    public function record_update_subscription()
    {
        // No need for this one with the artificial gateway.
    }

    /**
     * Processes the suspension of a subscription.
     *
     * @param integer $sub_id The ID of the subscription to suspend.
     *
     * @return void
     */
    public function process_suspend_subscription($sub_id)
    {
    }

    /**
     * This method should be used by the class to record a successful suspension
     * from the gateway.
     */
    public function record_suspend_subscription()
    {
    }

    /**
     * Processes the resumption of a subscription.
     *
     * @param integer $sub_id The ID of the subscription to resume.
     *
     * @return void
     */
    public function process_resume_subscription($sub_id)
    {
    }

    /**
     * This method should be used by the class to record a successful resuming of
     * as subscription from the gateway.
     */
    public function record_resume_subscription()
    {
    }

    /**
     * Used to cancel a subscription by the given gateway. This method should be used
     * by the class to record a successful cancellation from the gateway. This method
     * should also be used by any IPN requests or Silent Posts.
     *
     * @param integer $sub_id The ID of the subscription to cancel.
     *
     * @return void
     */
    public function process_cancel_subscription($sub_id)
    {
        $sub                = new MeprSubscription($sub_id);
        $_REQUEST['sub_id'] = $sub_id;
        $this->record_cancel_subscription();
    }

    /**
     * This method should be used by the class to record a successful cancellation
     * from the gateway. This method should also be used by any IPN requests or
     * Silent Posts.
     */
    public function record_cancel_subscription()
    {
        $sub = new MeprSubscription($_REQUEST['sub_id']);

        if (!$sub) {
            return false;
        }

        // Seriously ... if sub was already cancelled what are we doing here?
        if ($sub->status == MeprSubscription::$cancelled_str) {
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

        return $sub;
    }

    /**
     * This gets called on the 'init' hook when the signup form is processed ...
     * this is in place so that payment solutions like paypal can redirect
     * before any content is rendered.
     *
     * @param MeprTransaction $txn The transaction to process.
     *
     * @return void
     */
    public function process_signup_form($txn)
    {
        // Not needed
        // if($txn->amount <= 0.00) {
        // MeprTransaction::create_free_transaction($txn);
        // return;
        // }
        // Redirect to thank you page
        // $mepr_options = MeprOptions::fetch();
        // $product = new MeprProduct($txn->product_id);
        // $sanitized_title = sanitize_title($product->post_title);
        // MeprUtils::wp_redirect($mepr_options->thankyou_page_url("membership={$sanitized_title}&trans_num={$txn->trans_num}"));.
    }

    /**
     * Displays the payment page for a transaction.
     *
     * @param MeprTransaction $txn The transaction to display the payment page for.
     *
     * @return void
     */
    public function display_payment_page($txn)
    {
        // Nothing here yet.
    }

    /**
     * This gets called on wp_enqueue_script and enqueues a set of
     * scripts for use on the page containing the payment form
     */
    public function enqueue_payment_form_scripts()
    {
        // This happens manually in test mode.
    }

    /**
     * Displays the payment form for a transaction.
     *
     * @param float   $amount     The amount to display.
     * @param object  $user       The user object.
     * @param integer $product_id The product ID.
     * @param integer $txn_id     The transaction ID.
     *
     * @return void
     */
    public function display_payment_form($amount, $user, $product_id, $txn_id)
    {
        $mepr_options = MeprOptions::fetch();
        $prd          = new MeprProduct($product_id);
        $coupon       = false;

        $txn = new MeprTransaction($txn_id);

        // Artifically set the price of the $prd in case a coupon was used.
        if ($prd->price != $amount) {
            $coupon     = true;
            $prd->price = $amount;
        }

        ob_start();

        $invoice = MeprTransactionsHelper::get_invoice($txn);
        echo $invoice;
        echo MeprOptionsHelper::payment_method_description($this);

        ?>
      <div class="mp_wrapper mp_payment_form_wrapper">
        <form action="" method="post" id="payment-form" class="mepr-form" novalidate>
          <input type="hidden" name="mepr_process_payment_form" value="Y" />
          <input type="hidden" name="mepr_transaction_id" value="<?php echo esc_attr($txn_id); ?>" />

          <div class="mepr_spacer">&nbsp;</div>

          <input type="submit" class="mepr-submit" value="<?php _e('Submit', 'memberpress'); ?>" />
          <img src="<?php echo admin_url('images/loading.gif'); ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />
          <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>

          <noscript><p class="mepr_nojs"><?php _e('JavaScript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
        </form>
      </div>
        <?php

        $output = MeprHooks::apply_filters('mepr_artificial_gateway_payment_form', ob_get_clean(), $txn);
        echo $output;
    }

    /**
     * Validates the payment form.
     *
     * @param array $errors The errors to validate.
     *
     * @return void
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
        $mepr_options            = MeprOptions::fetch();
        $manually_complete       = ($this->settings->manually_complete == 'on' || $this->settings->manually_complete == true);
        $no_cancel_up_down_grade = ($this->settings->no_cancel_up_down_grade == 'on' || $this->settings->no_cancel_up_down_grade == true);
        $always_send_welcome     = ($this->settings->always_send_welcome == 'on' || $this->settings->always_send_welcome == true);
        ?>
    <table>
      <tr>
        <td colspan="2">
          <input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][manually_complete]"<?php echo checked($manually_complete); ?> />&nbsp;<?php _e('Admin Must Manually Complete Transactions', 'memberpress'); ?>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][always_send_welcome]"<?php echo checked($always_send_welcome); ?> />&nbsp;<?php _e('Send Welcome email when "Admin Must Manually Complete Transactions" is enabled', 'memberpress'); ?>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][no_cancel_up_down_grade]"<?php echo checked($no_cancel_up_down_grade); ?> />&nbsp;<?php _e('Do not cancel old plan on upgrades when "Admin Must Manually Complete Transactions" is enabled', 'memberpress'); ?>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <label><?php _e('Description', 'memberpress'); ?></label><br/>
          <textarea name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][desc]" rows="3" cols="45"><?php echo stripslashes($this->settings->desc); ?></textarea>
        </td>
      </tr>
        <?php echo MeprHooks::do_action('mepr-artificial-gateway-settings-after', $this); ?>
    </table>
        <?php
    }

    /**
     * Validates the options form for the payment gateway.
     *
     * @param array $errors The errors to validate.
     *
     * @return array The validated errors.
     */
    public function validate_options_form($errors)
    {
        return $errors;
    }

    /**
     * This gets called on wp_enqueue_script and enqueues a set of
     * scripts for use on the front end user account page.
     */
    public function enqueue_user_account_scripts()
    {
    }

    /**
     * Displays the update account form on the subscription account page.
     *
     * @param integer $sub_id  The ID of the subscription.
     * @param array   $errors  The errors to display.
     * @param string  $message The message to display.
     *
     * @return void
     */
    public function display_update_account_form($sub_id, $errors = [], $message = '')
    {
        // Handled Manually in test gateway.
        ?>
    <p><b><?php _e('This action is not possible with the payment method used with this Subscription', 'memberpress'); ?></b></p>
        <?php
    }

    /**
     * Validates the update account form.
     *
     * @param array $errors The errors to validate.
     *
     * @return array The validated errors.
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
     * @param integer $sub_id The ID of the subscription to update.
     *
     * @return void
     */
    public function process_update_account_form($sub_id)
    {
        // Handled Manually in test gateway.
    }

    /**
     * Returns boolean ... whether or not we should be sending in test mode or not
     */
    public function is_test_mode()
    {
        return false; // Why bother.
    }

    /**
     * Checks if SSL is forced for the gateway.
     *
     * @return boolean True if SSL is forced, false otherwise.
     */
    public function force_ssl()
    {
        return false; // Why bother.
    }

    /**
     * Cancels an old subscription if necessary.
     *
     * @param MeprTransaction $txn     The transaction to check.
     * @param object          $gateway The gateway object.
     *
     * @return void
     */
    public static function maybe_cancel_old_sub($txn, $gateway)
    {
        // If we are marking the transacton as complete, and the admin must manually complete, and old subscriptions
        // are not canceled when the admin must manually complete, then we need to check to see if the old sub needs to
        // be cancelled here.
        if ($txn->status == MeprTransaction::$complete_str && $gateway->settings->manually_complete && $gateway->settings->no_cancel_up_down_grade) {
            $sub = $txn->subscription();
            if ($sub) {
                $sub->maybe_cancel_old_sub(true); // Pass true here to by pass the artificial gateway check.
            } else {
                $txn->maybe_cancel_old_sub(true); // Pass true here to by pass the artificial gateway check.
            }
        }
    }
}
