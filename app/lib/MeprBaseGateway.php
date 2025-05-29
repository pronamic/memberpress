<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Lays down the interface for Gateways in MemberPress
 **/
abstract class MeprBaseGateway
{
    /**
     * A unique key to identify this gateway.
     *
     * @var string
     */
    public $key;

    /**
     * Used in the view to identify the payment method
     *
     * @var string
     */
    public $name;

    /**
     * Used in the view to label the payment method
     *
     * @var string
     */
    public $label;

    /**
     * Used in the view to check if the label should be shown
     *
     * @var boolean
     */
    public $use_label;

    /**
     * Used in the view to render an icon for each payment method
     *
     * @var string
     */
    public $icon;

    /**
     * Used in the view to check if the icon should be shown
     *
     * @var boolean
     */
    public $use_icon;

    /**
     * Used in the view to render a description for each payment method
     *
     * @var string
     */
    public $desc;

    /**
     * Used in the view to check if the description should be shown
     *
     * @var boolean
     */
    public $use_desc;

    /**
     * The public id of the payment method
     *
     * @var string
     */
    public $id;

    /**
     * Used to render the SPC form
     *
     * @var boolean
     */
    public $has_spc_form;

    /**
     * The recurrence type of the payment method 'manual' or 'automatic'
     *
     * @var string
     */
    public $recurrence_type;

    /**
     * This will be where the gateway interface will store its settings
     *
     * @var object
     */
    public $settings;

    /**
     * Important to determine what this gateway is capable of
     *
     * @var array
     */
    public $capabilities;

    /**
     * An array of callbacks to be called on 'init' ... these will be used to
     * impelement listeners for notifiers like PayPal IPN and/or Authorize.net Silent Posts.
     *
     * This should be an array in this format:
     *
     *  array( 'action' => 'callback', 'action2' => 'callback2' ... )
     *
     * An example of this is:
     *
     *  array( 'ipn' => 'listener' )
     *
     * @var array
     */
    protected $notifiers;

    /**
     * This works just like the notifiers but is rendered as a page for the end user.
     * This can be used to render cancellation, error and any other kind of page the
     * specific gateway we're working with at the time requires.
     *
     * @var array
     */
    protected $message_pages;

    /**
     * This generates a unique id for the gateway integration to use
     */
    public function generate_id()
    {
        $mepr_options = MeprOptions::fetch();
        $ids          = array_keys($mepr_options->integrations);

        $num = mt_rand(1, 9999);
        $id  = MeprUtils::base36_encode(time()) . '-' . MeprUtils::base36_encode($num);

        return $id;
    }

    /**
     * The system uses this to load this if there's a payment option configured for this
     *
     * @param array $settings The settings to load.
     *
     * @return void
     */
    abstract public function load($settings);

    /**
     * Sets the defaults for the settings, etc.
     *
     * @return void
     */
    abstract protected function set_defaults();

    /**
     * Returns true if a capability exists, false if not.
     *
     * @param string $cap The capability to check.
     *
     * @return boolean True if the capability exists, false otherwise.
     */
    public function can($cap)
    {
        return in_array(trim($cap), $this->capabilities);
    }

    /**
     * Returns a lower-case slug for the current gateway
     */
    public function slug()
    {
        $slug = preg_replace('/^Mepr(.*)Gateway/', '$1', get_class($this));
        return MeprUtils::snakecase($slug);
    }

    /**
     * Returns the array of notifiers for the specific gateway
     */
    public function notifiers()
    {
        return $this->notifiers;
    }

    /**
     * Returns a notifier callback for a specific action.
     *
     * @param string $action The action to get the notifier for.
     *
     * @return mixed The notifier callback or false if not found.
     */
    public function notifier($action)
    {
        if (isset($this->notifiers[$action])) {
            return $this->notifiers[$action];
        }

        return false;
    }

    /**
     * Returns the array of notifiers for the specific gateway
     */
    public function message_pages()
    {
        return $this->message_pages;
    }

    /**
     * Returns a message page callback for a specific action.
     *
     * @param string $action The action to get the message page for.
     *
     * @return mixed The message page callback or false if not found.
     */
    public function message_page($action)
    {
        if (isset($this->message_pages[$action])) {
            return $this->message_pages[$action];
        }

        return false;
    }

    /**
     * Returns the URL of a given notifier for the current gateway.
     *
     * @param string  $action    The action to get the URL for.
     * @param boolean $force_ssl Whether to force SSL.
     *
     * @return string|false The URL of the notifier or false if not found.
     */
    public function notify_url($action, $force_ssl = false)
    {
        if (isset($this->notifiers[$action])) {
            $permalink_structure = get_option('permalink_structure');
            $force_ugly_urls     = get_option('mepr_force_ugly_gateway_notify_urls');

            if ($force_ugly_urls || empty($permalink_structure)) {
                $url = MEPR_SCRIPT_URL . "&pmt={$this->id}&action={$action}";
            } else {
                $notify_url = preg_replace('!%gatewayid%!', $this->id, MeprUtils::gateway_notify_url_structure());
                $notify_url = preg_replace('!%action%!', $action, $notify_url);

                $url = site_url($notify_url);
            }

            if ($force_ssl) {
                $url = preg_replace('/^http:/', 'https:', $url);
            }

            $slug = $this->slug();
            $url  = MeprHooks::apply_filters('mepr_gateway_notify_url', $url, $slug, $action, $this);
            return MeprHooks::apply_filters("mepr_gateway_{$slug}_{$action}_notify_url", $url, $this);
        }

        return false;
    }

    /**
     * Returns the URL of a given message page for the current membership & gateway.
     *
     * @param MeprProduct $product The product object.
     * @param string      $action  The action to get the URL for.
     *
     * @return string|false The URL of the message page or false if not found.
     */
    public function message_page_url($product, $action)
    {
        if (isset($this->message_pages[$action])) {
            return $product->url("?pmt={$this->id}&action={$action}");
        }
        return false;
    }

    /**
     * Used to send data to a given payment gateway. In gateways which redirect
     * before this step is necessary this method should just be left blank.
     *
     * @param MeprTransaction $transaction The transaction to process.
     *
     * @return void
     */
    abstract public function process_payment($transaction);

    /**
     * Used to record a successful payment by the given gateway. It should have
     * the ability to record a successful payment or a failure. It is this method
     * that should be used when receiving an IPN from PayPal or a Silent Post
     * from Authorize.net.
     *
     * @return void
     */
    abstract public function record_payment();

    /**
     * This method should be used by the class to push a request to to the gateway.
     *
     * @param MeprTransaction $txn The transaction to refund.
     *
     * @return void
     */
    abstract public function process_refund(MeprTransaction $txn);

    /**
     * This method should be used by the class to record a successful refund from
     * the gateway. This method should also be used by any IPN requests or Silent Posts.
     *
     * @return void
     */
    abstract public function record_refund();

    /**
     * Used to record a successful recurring payment by the given gateway. It
     * should have the ability to record a successful payment or a failure. It is
     * this method that should be used when receiving an IPN from PayPal or a
     * Silent Post from Authorize.net.
     *
     * @return void
     */
    abstract public function record_subscription_payment();

    /**
     * Records a payment failure for a transaction.
     *
     * @return void
     */
    abstract public function record_payment_failure();

    /**
     * Used to process a one-off payment for a trial period.
     * Should be used for gateways that don't support trial periods
     * on recurring subscriptions, or for gateways that don't
     * support flexible trial periods/amounts but do support
     * setting the subscription start date to some time in the future.
     * Authorize.net and Stripe.com currently use this method.
     *
     * @param MeprTransaction $transaction The transaction to process.
     *
     * @return void
     */
    abstract public function process_trial_payment($transaction);

    /**
     * Records a trial payment for a transaction.
     * See above -- process_trial_payment() method
     *
     * @param MeprTransaction $transaction The transaction to record.
     *
     * @return void
     */
    abstract public function record_trial_payment($transaction);

    /**
     * Used to send subscription data to a given payment gateway. In gateways
     * which redirect before this step is necessary this method should just be
     * left blank.
     *
     * @param MeprTransaction $transaction The transaction to process.
     *
     * @return void
     */
    abstract public function process_create_subscription($transaction);

    /**
     * Used to record a successful subscription by the given gateway. It should have
     * the ability to record a successful subscription or a failure. It is this method
     * that should be used when receiving an IPN from PayPal or a Silent Post
     * from Authorize.net.
     *
     * @return void
     */
    abstract public function record_create_subscription();

    /**
     * Processes the update of a subscription.
     *
     * @param integer $subscription_id The ID of the subscription to update.
     *
     * @return void
     */
    abstract public function process_update_subscription($subscription_id);

    /**
     * This method should be used by the class to record a successful cancellation
     * from the gateway. This method should also be used by any IPN requests or
     * Silent Posts.
     *
     * @return void
     */
    abstract public function record_update_subscription();

    /**
     * Used to suspend a subscription by the given gateway.
     *
     * @param integer $subscription_id The ID of the subscription to suspend.
     *
     * @return void
     */
    abstract public function process_suspend_subscription($subscription_id);

    /**
     * This method should be used by the class to record a successful suspension
     * from the gateway.
     *
     * @return void
     */
    abstract public function record_suspend_subscription();

    /**
     * Processes the resumption of a subscription.
     *
     * @param integer $subscription_id The ID of the subscription to resume.
     *
     * @return void
     */
    abstract public function process_resume_subscription($subscription_id);

    /**
     * Records the resumption of a subscription.
     *
     * @return void
     */
    abstract public function record_resume_subscription();

    /**
     * Used to cancel a subscription by the given gateway. This method should be used
     * by the class to record a successful cancellation from the gateway. This method
     * should also be used by any IPN requests or Silent Posts.
     *
     * @param integer $subscription_id The ID of the subscription to cancel.
     *
     * @return void
     */
    abstract public function process_cancel_subscription($subscription_id);

    /**
     * This method should be used by the class to record a successful cancellation
     * from the gateway. This method should also be used by any IPN requests or
     * Silent Posts.
     *
     * @return void
     */
    abstract public function record_cancel_subscription();

    /**
     * Gets called when the signup form is posted used for running any payment
     * method specific actions when processing the customer signup form.
     *
     * @param MeprTransaction $txn The transaction to process.
     *
     * @return void
     */
    abstract public function process_signup_form($txn);

    /**
     * Gets called on the 'init' action after before the payment page is
     * displayed. If we're using an offsite payment solution like PayPal
     * then this method will just redirect to it.
     *
     * @param MeprTransaction $txn The transaction to display the payment page for.
     *
     * @return void
     */
    abstract public function display_payment_page($txn);

    /**
     * This gets called on wp_enqueue_script and enqueues a set of
     * scripts for use on the page containing the payment form
     *
     * @return void
     */
    abstract public function enqueue_payment_form_scripts();

    /**
     * This spits out html for the payment form on the registration / payment
     * page for the user to fill out for payment.
     *
     * @param float   $amount         The amount to display.
     * @param object  $user           The user object.
     * @param integer $product_id     The product ID.
     * @param integer $transaction_id The transaction ID.
     *
     * @return void
     */
    abstract public function display_payment_form($amount, $user, $product_id, $transaction_id);

    /**
     * Validates the payment form.
     *
     * @param array $errors The errors to validate.
     *
     * @return void
     */
    abstract public function validate_payment_form($errors);

    /**
     * Processes the payment form for a transaction.
     * This method can be overridden if necessary
     *
     * @param MeprTransaction $txn The transaction to process.
     *
     * @return void
     * @throws Exception If the transaction is not a valid MeprTransaction object.
     */
    public function process_payment_form($txn)
    {
        $mepr_options = MeprOptions::fetch();

        // Back button fix for IE and Edge
        // Make sure they haven't just completed the subscription signup and clicked the back button.
        if ($txn->status != MeprTransaction::$pending_str) {
            throw new Exception(sprintf(
                // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                _x('You already completed your payment to this subscription. %1$sClick here to view your subscriptions%2$s.', 'ui', 'memberpress'),
                '<a href="' . $mepr_options->account_page_url('action=subscriptions') . '">',
                '</a>'
            ));
        }

        $error_str = __('Sorry but we can\'t process your payment at this time. Try back later.', 'memberpress');

        if (isset($txn) && $txn instanceof MeprTransaction) {
            $usr = $txn->user();
            $prd = $txn->product();
        } else {
            throw new Exception($error_str . ' [PPF01]');
        }

        // How did we get here?
        if (!$prd->can_you_buy_me()) {
            $sub = $txn->subscription();
            if ($sub) {
                $sub->destroy();
            }

            $txn->destroy();

            throw new Exception($error_str . ' [PPF02]');
        }

        if ($txn->amount <= 0.00) {
            MeprTransaction::create_free_transaction($txn);
            return;
        }

        if ($txn->gateway == $this->id) {
            if (!$prd->is_one_time_payment()) {
                // Trial pmt is included in the Subscription profile at gateway (PayPal mostly).
                $sub = $txn->subscription();
                if (!$this->can('subscription-trial-payment') && $sub !== false && $sub->trial && $sub->trial_amount > 0.00) {
                    $calculate_taxes = (bool) get_option('mepr_calculate_taxes');
                    $tax_inclusive   = $mepr_options->attr('tax_calc_type') == 'inclusive';
                    $txn->set_subtotal($calculate_taxes && $tax_inclusive ? $sub->trial_total : $sub->trial_amount);
                    $this->email_status("Calling process_trial_payment ...\n\n" . MeprUtils::object_to_string($txn) . "\n\n" . MeprUtils::object_to_string($sub), $this->settings->debug);
                    $this->process_trial_payment($txn);
                }
                $this->process_create_subscription($txn);
            } else {
                $this->process_payment($txn);
            }
        } else {
            throw new Exception($error_str . ' [PPF03]');
        }
    }

    /**
     * Displays the options form for the payment gateway.
     * Displays the form for the given payment gateway on the MemberPress Options page
     *
     * @return void
     */
    abstract public function display_options_form();

    /**
     * Validates the options form for the payment gateway.
     *
     * @param array $errors The errors to validate.
     *
     * @return void
     */
    abstract public function validate_options_form($errors);

    /**
     * This gets called on wp_enqueue_script and enqueues a set of
     * scripts for use on the front end user account page.
     * Can be overridden if custom scripts are necessary.
     *
     * @return void
     */
    public function enqueue_user_account_scripts()
    {
    }

    /**
     * Hides the update link for a subscription.
     *
     * @param MeprSubscription $subscription The subscription to hide the update link for.
     *
     * @return boolean False always.
     */
    protected function hide_update_link($subscription)
    {
        return false;
    }

    /**
     * This displays the subscription row buttons on the user account page. Can be overridden if necessary.
     *
     * @param MeprSubscription $subscription The subscription to print the row actions for.
     *
     * @return void
     */
    public function print_user_account_subscription_row_actions($subscription)
    {
        global $post;

        $mepr_options = MeprOptions::fetch();
        // $subscription = new MeprSubscription($sub_id);
        $product = new MeprProduct($subscription->product_id);

        // Assume we're either on the account page or some
        // page that is using the [mepr-account-form] shortcode.
        $account_url = MeprUtils::get_account_url();

        if (wp_doing_ajax()) {
            $account_url = isset($_POST['account_url']) ? esc_url($_POST['current_url']) : $account_url;
        }
        $account_delim    = ( preg_match('~\?~', $account_url) ? '&' : '?' );
        $user             = $subscription->user();
        $hide_update_link = $this->hide_update_link($subscription);

        ?>
        <?php // <div class="mepr-account-row-actions"> ?>
        <?php if ($subscription->status != MeprSubscription::$pending_str) : ?>
            <?php if ($subscription->status != MeprSubscription::$cancelled_str && !$hide_update_link) : ?>
          <a href="<?php echo $this->https_url("{$account_url}{$account_delim}action=update&sub={$subscription->id}"); ?>" class="mepr-account-row-action mepr-account-update"><?php _e('Update', 'memberpress'); ?></a>
            <?php endif; ?>

            <?php
            $grp = $product->group();
            if ($grp && count($grp->products('ids')) > 1 && count($grp->buyable_products()) >= 1) : // Can't upgrade to no other options. ?>
          <div id="mepr-upgrade-sub-<?php echo $subscription->id; ?>" class="mepr-white-popup mfp-hide">
            <center>
              <div class="mepr-upgrade-sub-text">
                <?php _e('Please select a new plan', 'memberpress'); ?>
              </div>
              <br/>
              <div>
                <select id="mepr-upgrade-dropdown-<?php echo $subscription->id; ?>" class="mepr-upgrade-dropdown">
                  <?php foreach ($grp->products() as $p) : ?>
                        <?php if ($p->can_you_buy_me()) : ?>
                      <option value="<?php echo $p->url(); ?>"><?php printf('%1$s (%2$s)', $p->post_title, MeprProductsHelper::product_terms($p, $user)); ?></option>
                        <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>
              <br/>
              <div class="mepr-cancel-sub-buttons">
                <button class="mepr-btn mepr-upgrade-buy-now" data-id="<?php echo $subscription->id; ?>"><?php _e('Select Plan', 'memberpress'); ?></button>
                <button class="mepr-btn mepr-upgrade-cancel"><?php _e('Cancel', 'memberpress'); ?></button>
              </div>
            </center>
          </div>
                <?php ob_start(); ?>
                <?php
                if ($product->simultaneous_subscriptions && $subscription->is_active() && $subscription->is_cancelled()) {
                    MeprAccountHelper::purchase_link($product, _x('Re-Subscribe', 'ui', 'memberpress'));
                }
                ?>
                <?php $upgrade_label = ($grp->is_upgrade_path ? __('Change Plan', 'memberpress') : __('Other Memberships', 'memberpress')); ?>
                <?php if (!$grp->disable_change_plan_popup) : ?>
            <a href="#mepr-upgrade-sub-<?php echo $subscription->id; ?>" class="mepr-open-upgrade-popup mepr-account-row-action mepr-account-upgrade"><?php echo $upgrade_label; ?></a>
                <?php else : ?>
            <a href="<?php echo $grp->url(); ?>" class="mepr-account-row-action mepr-account-upgrade"><?php echo $upgrade_label; ?></a>
                <?php endif; ?>
                <?php echo MeprHooks::apply_filters('mepr_custom_upgrade_link', ob_get_clean(), $subscription); ?>
            <?php endif; ?>

            <?php if (
            $mepr_options->allow_suspend_subs and
                  $this->can('suspend-subscriptions') and
                  $subscription->status == MeprSubscription::$active_str and
                  !$subscription->in_free_trial()
) : ?>
          <?php ob_start(); ?>
            <a href="<?php echo "{$account_url}{$account_delim}action=suspend&sub={$subscription->id}"; ?>" class="mepr-account-row-action mepr-account-suspend" onclick="return confirm('<?php _e('Are you sure you want to pause this subscription?', 'memberpress'); ?>');"><?php _e('Pause', 'memberpress'); ?></a>
          <?php echo MeprHooks::apply_filters('mepr_custom_pause_link', ob_get_clean(), $subscription); ?>
            <?php elseif (
            $mepr_options->allow_suspend_subs and
                      $this->can('suspend-subscriptions') and
                      $subscription->status == MeprSubscription::$suspended_str
) : ?>
          <div id="mepr-resume-sub-<?php echo $subscription->id; ?>" class="mepr-white-popup mfp-hide">
            <div class="mepr-resume-sub-text">
              <?php _e('Are you sure you want to resume this subscription?', 'memberpress'); ?>
            </div>
            <button class="mepr-btn mepr-left-margin mepr-confirm-yes" data-url="<?php echo "{$account_url}{$account_delim}action=resume&sub={$subscription->id}"; ?>"><?php _e('Yes', 'memberpress'); ?></button>
            <button class="mepr-btn mepr-confirm-no"><?php _e('No', 'memberpress'); ?></button>
          </div>
          <?php ob_start(); ?>
            <a href="#mepr-resume-sub-<?php echo $subscription->id; ?>" class="mepr-open-resume-confirm mepr-account-row-action mepr-account-resume"><?php _e('Resume', 'memberpress'); ?></a>
          <?php echo MeprHooks::apply_filters('mepr_custom_resume_link', ob_get_clean(), $subscription); ?>
            <?php endif; ?>

            <?php if ($mepr_options->allow_cancel_subs and $this->can('cancel-subscriptions') && $subscription->status == MeprSubscription::$active_str) : ?>
          <div id="mepr-cancel-sub-<?php echo $subscription->id; ?>" class="mepr-white-popup mfp-hide">
            <div class="mepr-cancel-sub-text">
                <?php _e('Are you sure you want to cancel this subscription?', 'memberpress'); ?>
            </div>
            <div class="mepr-cancel-sub-buttons">
              <button class="mepr-btn mepr-left-margin mepr-confirm-yes" data-url="<?php echo "{$account_url}{$account_delim}action=cancel&sub={$subscription->id}"; ?>"><?php _e('Yes', 'memberpress'); ?></button>
              <button class="mepr-btn mepr-confirm-no"><?php _e('No', 'memberpress'); ?></button>
            </div>
          </div>
                <?php ob_start(); ?>
            <a href="#mepr-cancel-sub-<?php echo $subscription->id; ?>" class="mepr-open-cancel-confirm mepr-account-row-action mepr-account-cancel"><?php _e('Cancel', 'memberpress'); ?></a>
                <?php echo MeprHooks::apply_filters('mepr_custom_cancel_link', ob_get_clean(), $subscription); ?>
            <?php endif; ?>
        <?php endif; ?>
        <?php // </div> ?>
        <?php
    }

    /**
     * Returns the HTTPS URL for a given URL.
     *
     * @param string $url The URL to convert to HTTPS.
     *
     * @return string The HTTPS URL.
     */
    protected function https_url($url)
    {
        return $this->force_ssl() ? preg_replace('!^https?:!', 'https:', $url) : $url;
    }

    /**
     * Displays the update account form on the subscription account page.
     *
     * @param integer $subscription_id The ID of the subscription.
     * @param array   $errors          The errors to display.
     * @param string  $message         The message to display.
     *
     * @return void
     */
    abstract public function display_update_account_form($subscription_id, $errors = [], $message = '');

    /**
     * Validates the update account form.
     *
     * @param array $errors The errors to validate.
     *
     * @return void
     */
    abstract public function validate_update_account_form($errors = []);

    /**
     * Processes the update account form.
     * Actually pushes the account update to the payment processor
     *
     * @param integer $subscription_id The ID of the subscription to update.
     *
     * @return void
     */
    abstract public function process_update_account_form($subscription_id);

    /**
     * Checks if the gateway is in test mode.
     *
     * @return boolean True if in test mode, false otherwise.
     */
    abstract public function is_test_mode();

    /**
     * Checks if SSL is forced for the gateway.
     *
     * @return boolean True if SSL is forced, false otherwise.
     */
    abstract public function force_ssl();

    /**
     * Sends an email status message.
     *
     * @param string  $message The message to send.
     * @param boolean $debug   Whether to send in debug mode.
     *
     * @return void
     */
    protected function email_status($message, $debug)
    {
        if ($debug) {
            // Send notification email to admin user (to and from the admin user)
            // Translators: In this string, %1$s is the Blog Name/Title and %2$s is the Name of the Payment Method.
            $subject = sprintf(__('[%1$s] %2$s Debug Email', 'memberpress'), MeprUtils::blogname(), $this->name);
            MeprUtils::wp_mail_to_admin($subject, $message);
        }
    }

    /**
     * Validates a credit card number using the Luhn algorithm.
     *
     * @param string $number The credit card number to validate.
     *
     * @return boolean True if the credit card number is valid, false otherwise.
     */
    protected function is_credit_card_valid($number)
    {
        // Short circuit if the cc# doesn't match any of the credit card types
        // if( $this->credit_card_type($number) ) //Need to add discover first
        // return false;
        // Strip any non-digits (useful for credit card numbers with spaces and hyphens).
        $number = preg_replace('/\D/', '', $number);

        // Set the string length and parity.
        $number_length = strlen($number);
        $parity        = $number_length % 2;

        // Loop through each digit and do the maths.
        $total = 0;
        for ($i = 0; $i < $number_length; $i++) {
            $digit = $number[$i];
            // Multiply alternate digits by two.
            if ($i % 2 == $parity) {
                $digit *= 2;
                // If the sum is two digits, add them together (in effect).
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            // Total up the digits.
            $total += $digit;
        }

        // If the total mod 10 equals 0, the number is valid.
        return ( ( $total % 10 ) == 0 );
    }

    /**
     * Determines the credit card type based on the number.
     *
     * @param string $number The credit card number.
     *
     * @return string|false The credit card type or false if not recognized.
     */
    protected function credit_card_type($number)
    {
        $cards   = [
            'visa'       => '(4\d{12}(?:\d{3})?)',
            'amex'       => '(3[47]\d{13})',
            'jcb'        => '(35[2-8][89]\d\d\d{10})',
            'maestro'    => '((?:5020|5038|6304|6579|6761)\d{12}(?:\d\d)?)',
            'solo'       => '((?:6334|6767)\d{12}(?:\d\d)?\d?)',
            'mastercard' => '(5[1-5]\d{14})',
            'switch'     => '(?:(?:(?:4903|4905|4911|4936|6333|6759)\d{12})|(?:(?:564182|633110)\d{10})(\d\d)?\d?)',
        ];
        $names   = ['Visa', 'American Express', 'JCB', 'Maestro', 'Solo', 'Mastercard', 'Switch'];
        $matches = [];
        $pattern = '#^(?:' . implode('|', $cards) . ')$#';
        $result  = preg_match($pattern, str_replace(' ', '', $number), $matches);
        return ($result > 0) ? $names[sizeof($matches) - 2] : false;
    }

    /**
     * Displays a dropdown for selecting months.
     *
     * @param string  $name      The name attribute for the dropdown.
     * @param string  $class     The class attribute for the dropdown.
     * @param string  $selected  The selected month.
     * @param boolean $pad_zeros Whether to pad single-digit months with zeros.
     *
     * @return void
     */
    public function months_dropdown($name, $class, $selected = '', $pad_zeros = false)
    {
        ?>
    <select <?php echo empty($name) ? '' : "name=\"{$name}\" "; ?>class="mepr-payment-form-select <?php echo empty($class) ? '' : $class; ?>">
        <?php
        for ($i = 1; $i <= 12; $i++) {
            $i_str        = $pad_zeros ? sprintf('%02d', $i) : $i;
            $selected_str = $selected == $i_str ? ' selected="selected"' : ''
            ?>
      <option value="<?php echo $i_str; ?>"<?php echo $selected_str; ?>><?php echo $i_str; ?></option>
            <?php
        }

        ?>
    </select>
        <?php
    }

    /**
     * Displays a dropdown for selecting years.
     *
     * @param string $name     The name attribute for the dropdown.
     * @param string $class    The class attribute for the dropdown.
     * @param string $selected The selected year.
     *
     * @return void
     */
    public function years_dropdown($name, $class, $selected = '')
    {
        $year = gmdate('Y', time());
        ?>
    <select <?php echo empty($name) ? '' : "name=\"{$name}\" "; ?>class="mepr-payment-form-select <?php echo empty($class) ? '' : $class; ?>">
        <?php
        for ($i = $year; $i <= ($year + 9); $i++) {
            $selected_str = $selected == $i ? ' selected="selected"' : ''
            ?>
      <option value="<?php echo $i; ?>"<?php echo $selected_str; ?>><?php echo $i; ?></option>
            <?php
        }

        ?>
    </select>
        <?php
    }

    /**
     * Retrieves the transaction count for a subscription.
     *
     * @param MeprSubscription|null $sub The subscription to get the transaction count for.
     *
     * @return integer|false The transaction count or false if not found.
     */
    public function txn_count($sub = null)
    {
        if (!is_null($sub)) {
            return $sub->txn_count;
        } else {
            return false;
        }
    }

    // Currently used for both PayPal gateways
    // Determines if the payment being recorded should be a paid trial period payment
    // If so, it should be a confirmation txn that we can convert to a payment txn.
    /**
     * Determines if a subscription trial payment is being recorded.
     *
     * @param MeprSubscription $sub The subscription to check.
     *
     * @return boolean True if a trial payment is being recorded, false otherwise.
     */
    protected function is_subscr_trial_payment($sub)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        // If no trial period, or trial period is free, then we don't want to record the first txn as a regular payment.
        if (!$sub->trial || ($sub->trial && $sub->trial_amount <= 0.00)) {
            return false;
        }

        // Let's also make sure the first txn is still a confirmation type.
        $first_txn = $sub->first_txn();
        if ($first_txn == false || !($first_txn instanceof MeprTransaction) || $first_txn->txn_type != MeprTransaction::$subscription_confirmation_str) {
            return false;
        }

        // Making sure this is in fact the first real payment.
        $q = $wpdb->prepare(
            "
        SELECT COUNT(*)
          FROM {$mepr_db->transactions}
         WHERE subscription_id = %d
           AND txn_type = %s
           AND status <> %s
      ",
            $sub->id,
            MeprTransaction::$payment_str,
            MeprTransaction::$pending_str
        );

        $count = $wpdb->get_var($q);

        return ((int)$count == 0);
    }

    /**
     * Get the renewal base date for a given subscription. This is the date MemberPress will use to calculate  expiration dates.
     * Of course this method is meant to be overridden when a gateway requires it.
     *
     * @param MeprSubscription $sub The subscription to get the renewal base date for.
     *
     * @return string The renewal base date.
     */
    public function get_renewal_base_date(MeprSubscription $sub)
    {
        return $sub->created_at;
    }

    /**
     * Handles an upgraded subscription.
     *
     * @param mixed $obj       The subscription object.
     * @param mixed $event_txn The event transaction.
     *
     * @return void
     */
    public function upgraded_sub($obj, $event_txn)
    {
        $type = MeprUtils::get_sub_type($obj);
        if ($type !== false) {
            MeprHooks::do_action("mepr-upgraded-{$type}-sub", $obj);
            MeprHooks::do_action('mepr-upgraded-sub', $type, $obj);
            MeprHooks::do_action('mepr-sub-created', $type, $obj, 'upgraded');
            if (MeprHooks::apply_filters('mepr_send_upgraded_sub_notices', true, $obj, $event_txn)) {
                MeprUtils::send_upgraded_sub_notices($obj);
            }
            MeprUtils::record_upgraded_sub_events($obj, $event_txn);
        }
    }

    /**
     * Handles a downgraded subscription.
     *
     * @param mixed $obj       The subscription object.
     * @param mixed $event_txn The event transaction.
     *
     * @return void
     */
    public function downgraded_sub($obj, $event_txn)
    {
        $type = MeprUtils::get_sub_type($obj);
        if ($type !== false) {
            MeprHooks::do_action("mepr-downgraded-{$type}-sub", $obj);
            MeprHooks::do_action('mepr-downgraded-sub', $type, $obj);
            MeprHooks::do_action('mepr-sub-created', $type, $obj, 'downgraded');
            if (MeprHooks::apply_filters('mepr_send_downgraded_sub_notices', true, $obj, $event_txn)) {
                MeprUtils::send_downgraded_sub_notices($obj);
            }
            MeprUtils::record_downgraded_sub_events($obj, $event_txn);
        }
    }

    /**
     * Handles a new subscription.
     *
     * @param mixed   $obj          The subscription object.
     * @param boolean $send_notices Whether to send notices.
     *
     * @return void
     */
    public function new_sub($obj, $send_notices = false)
    {
        $type = MeprUtils::get_sub_type($obj);
        if ($type !== false) {
            MeprHooks::do_action("mepr-new-{$type}-sub", $obj);
            MeprHooks::do_action('mepr-new-sub', $type, $obj);
            MeprHooks::do_action('mepr-sub-created', $type, $obj, 'new');
            if ($send_notices === true) {
                MeprUtils::send_new_sub_notices($obj);
            }
        }
    }
}
