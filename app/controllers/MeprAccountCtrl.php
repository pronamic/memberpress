<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprAccountCtrl extends MeprBaseCtrl
{
    /**
     * Loads hooks for account-related actions and filters.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('wp_enqueue_scripts', [$this,  'enqueue_scripts']);
        add_action('init', [$this,  'maybe_update_username']); // Need to use init for cookie stuff and to get old and new emails.
        add_action('mepr-above-checkout-form', [$this,  'maybe_show_broken_sub_message']); // Show message on checkout form with link to update broken sub.
        add_action('wp_ajax_save_profile_changes', [$this, 'save_profile_fields']);
        add_action('wp_ajax_mepr_process_update_account_form', [$this, 'process_update_account_form_ajax']);


        /*
         * These are dependent on the the theme/template supporting them.
         * To Turn On:
            add_filter('mepr-account-nav-page-titles', 'mepr_cust_on_switch');
            add_filter('mepr-account-nav-broswer-titles', 'mepr_cust_on_switch');
            function mepr_cust_on_switch($on) {
            return true;
            }
         */
        add_filter('the_title', [$this, 'account_page_the_title'], 10, 2);
        add_filter('wp_title', [$this, 'account_page_browser_title']);

        // Shortcodes.
        MeprHooks::add_shortcode('mepr-account-form', [$this, 'account_form_shortcode']);
        MeprHooks::add_shortcode('mepr-account-link', [$this, 'get_account_links']);
        MeprHooks::add_shortcode('mepr-account-info', [$this, 'output_account_meta']);
        MeprHooks::add_shortcode('mepr-offline-instructions', [$this, 'offline_gateway_instructions']); // Offline gateway instructions shortcode.
    }

    // What if a user has a failed payment on a subscription, and instead of updating their CC
    // They accidentally go to purchase a new subscription instead? This could lead to a double billing
    // So let's warn them here.
    /**
     * Shows a warning message when a user with a broken subscription attempts to purchase a new one.
     *
     * @param integer $prd_id The product ID.
     *
     * @return void
     */
    public function maybe_show_broken_sub_message($prd_id)
    {
        global $pagenow;

        if ($pagenow == 'post.php') {
            return;
        }

        $mepr_options = MeprOptions::fetch();
        $user         = MeprUtils::get_currentuserinfo();
        $errors       = [];

        if ($user !== false) {
            $enabled_prd_ids = $user->get_enabled_product_ids($prd_id);

            if (!empty($enabled_prd_ids)) { // If it's not empty, then the user already has an Enabled subscription for this membership.
                $prd = new MeprProduct($prd_id);
                if (!$prd->simultaneous_subscriptions && apply_filters('maybe_show_broken_sub_message_override', true, $prd)) {
                    $errors[] = sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        _x('You already have a subscription to this Membership. Please %1$supdate your payment details%2$s on the existing subscription instead of purchasing again.', 'ui', 'memberpress'),
                        '<a href="' . $mepr_options->account_page_url('action=subscriptions') . '">',
                        '</a>'
                    );
                    MeprView::render('/shared/errors', get_defined_vars());
                    ?>
          <!-- Hidden signup form -->
          <script>
            jQuery(document).ready(function($) {
              $('input[value="' + <?php echo (int) $prd_id; ?> +'"]').closest('form').hide();
            });
          </script>
                    <?php
                }
            }
        }
    }

    /**
     * Updates the username if it's an email address and the email is changing.
     *
     * @return void
     */
    public function maybe_update_username()
    {
        if (
            MeprUtils::is_post_request() &&
            isset($_POST['mepr-process-account']) && $_POST['mepr-process-account'] == 'Y' &&
            isset($_POST['mepr_account_nonce']) && wp_verify_nonce($_POST['mepr_account_nonce'], 'update_account')
        ) {
            global $wpdb;
            $mepr_options = MeprOptions::fetch();

            $mepr_user = MeprUtils::get_currentuserinfo();
            $old_email = $mepr_user->user_email;
            $new_email = sanitize_email($_POST['user_email']);

            // Make sure no one else has this email as their username.
            if (is_email($new_email) && username_exists($new_email)) {
                return; // BAIL.
            }

            if (
                $mepr_user !== false &&
                $mepr_options->username_is_email &&
                is_email($new_email) && // Make sure this isn't sql injected or something.
                is_email($mepr_user->user_login) && // Make sure we're not overriding a non-email username.
                $old_email == $mepr_user->user_login && // Make sure old email and old username match up.
                $old_email != $new_email
            ) {
                // Some trickery here to keep the user logged in.
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->users} SET user_login = %s WHERE ID = %d", $new_email, $mepr_user->ID));
                clean_user_cache($mepr_user->ID); // Get rid of the user cache.
                wp_clear_auth_cookie(); // Clear their old cookie.
                wp_set_current_user($mepr_user->ID); // Set the current user again.
                wp_set_auth_cookie($mepr_user->ID, true, false); // Log the user back in w/out knowing their password.
                update_user_caches(new WP_User($mepr_user->ID));
            }
        }
    }

    /**
     * Enqueues scripts and styles for the account page.
     *
     * @param boolean $force Whether to force enqueueing scripts.
     *
     * @return void
     */
    public function enqueue_scripts($force = false)
    {
        global $post;
        $mepr_options = MeprOptions::fetch();

        if ($force || MeprUser::is_account_page($post)) {
            $popup_ctrl = new MeprPopupCtrl();

            $has_phone = false;

            if (! empty($mepr_options->custom_fields)) {
                foreach ($mepr_options->custom_fields as $field) {
                    if ('tel' === $field->field_type && $field->show_in_account) {
                        $has_phone = true;
                        break;
                    }
                }
            }

            wp_register_style('jquery-magnific-popup', $popup_ctrl->popup_css);
            wp_enqueue_style('mp-account', MEPR_CSS_URL . '/account.css', ['jquery-magnific-popup'], MEPR_VERSION);

            wp_register_script('jquery-magnific-popup', $popup_ctrl->popup_js, ['jquery']);
            wp_enqueue_script('mp-account', MEPR_JS_URL . '/account.js', ['jquery','jquery-magnific-popup'], MEPR_VERSION);

            $pms = $mepr_options->payment_methods();

            if ($pms) {
                wp_register_script('mepr-checkout-js', MEPR_JS_URL . '/checkout.js', ['jquery', 'jquery.payment'], MEPR_VERSION);
                wp_register_script('mepr-default-gateway-checkout-js', MEPR_JS_URL . '/gateway/checkout.js', ['mepr-checkout-js'], MEPR_VERSION);
                foreach ($pms as $pm) {
                    if ($pm instanceof MeprBaseRealGateway) {
                        $pm->enqueue_user_account_scripts();
                    }
                }
            }

            // Check if there's a phone field.
            if ($has_phone) {
                wp_enqueue_style('mepr-phone-css', MEPR_CSS_URL . '/vendor/intlTelInput.min.css', '', '16.0.0');
                wp_enqueue_style('mepr-tel-config-css', MEPR_CSS_URL . '/tel_input.css', '', MEPR_VERSION);
                wp_enqueue_script('mepr-phone-js', MEPR_JS_URL . '/vendor/intlTelInput.js', '', '16.0.0', true);
                wp_enqueue_script('mepr-tel-config-js', MEPR_JS_URL . '/tel_input.js', ['mepr-phone-js', 'mp-account'], MEPR_VERSION, true);
                wp_localize_script('mepr-tel-config-js', 'meprTel', MeprHooks::apply_filters('mepr-phone-input-config', [
                    'defaultCountry' => strtolower(get_option('mepr_biz_country')),
                    'utilsUrl'       => MEPR_JS_URL . '/vendor/intlTelInputUtils.js',
                    'onlyCountries'  => '',
                ]));
            }
        }
    }

    /**
     * Renders the account page based on the provided attributes.
     *
     * @param array $atts Attributes for rendering the account page.
     *
     * @return void
     */
    public function render($atts = [])
    {
        global $post;

        $mepr_current_user = MeprUtils::get_currentuserinfo();
        $expired_subs      = $mepr_current_user->subscription_expirations('expired', true);
        $mepr_options      = MeprOptions::fetch();

        // When this option is empty, the "Plain" permalink structure is in use.
        $account_url = MeprUtils::get_account_url();

        $delim = MeprAppCtrl::get_param_delimiter_char($account_url);

        if (MeprReadyLaunchCtrl::template_enabled('account') || MeprAppHelper::has_block('memberpress/pro-account-tabs')) {
            MeprView::render('/readylaunch/account/nav', get_defined_vars());
        } else {
            MeprView::render('/account/nav', get_defined_vars());
        }

        $action = MeprHooks::apply_filters('mepr-account-action', (isset($_REQUEST['action'])) ? $_REQUEST['action'] : false);


        switch ($action) {
            case 'payments':
                $this->payments();
                break;
            case 'subscriptions':
                $this->subscriptions();
                break;
            case 'newpassword':
                $this->password();
                break;
            case 'cancel':
                $this->cancel();
                break;
            case 'suspend':
                $this->suspend();
                break;
            case 'resume':
                $this->resume();
                break;
            case 'update':
                $this->update();
                break;
            case 'upgrade':
                $this->upgrade();
                break;
            default:
                // Allows you to override the content for a nav tab.
                ob_start();
                MeprHooks::do_action('mepr_account_nav_content', $action, $atts);
                $custom_content = ob_get_clean();

                if (empty($custom_content)) {
                    $this->home($atts);
                } else {
                    echo '<div class="mepr-' . $action . '-wrapper">' . $custom_content . '</div>';
                }
        }

        MeprHooks::do_action('mepr_after_account_render');
    }

    /**
     * Renders the home section of the account page.
     *
     * @param array $atts Attributes for rendering the home section.
     *
     * @return void
     */
    public function home($atts = [])
    {
        $mepr_current_user = MeprUtils::get_currentuserinfo();
        $mepr_options      = MeprOptions::fetch();
        $account_url       = $mepr_options->account_page_url();
        $delim             = MeprAppCtrl::get_param_delimiter_char($account_url);
        $errors            = [];
        $saved             = false;
        $welcome_message   = do_shortcode(wp_kses_post(wpautop(stripslashes($mepr_options->custom_message))));

        if (
            MeprUtils::is_post_request() &&
            isset($_POST['mepr-process-account']) && $_POST['mepr-process-account'] == 'Y'
        ) {
            check_admin_referer('update_account', 'mepr_account_nonce');
            $errors = MeprUsersCtrl::validate_extra_profile_fields(null, null, $mepr_current_user);
            $errors = MeprUser::validate_account($_POST, $errors);
            $errors = MeprHooks::apply_filters('mepr-validate-account', $errors, $mepr_current_user);

            if (empty($errors)) {
                // Need to find a better way to do this eventually but for now update the user's email.
                $new_email = sanitize_email($_POST['user_email']);

                if ($mepr_current_user->user_email != $new_email) {
                    $mepr_current_user->user_email = $new_email;
                    $mepr_current_user->store();
                    MeprHooks::do_action('mepr-update-new-user-email', $mepr_current_user);
                }

                // Save the usermeta.
                $saved = MeprUsersCtrl::save_extra_profile_fields($mepr_current_user->ID, true);
                if ($saved) {
                    $message = __('Your account has been saved.', 'memberpress');
                }

                // Reload the user now that we've updated it's info.
                $mepr_current_user = new MeprUser($mepr_current_user->ID);

                MeprHooks::do_action('mepr-save-account', $mepr_current_user);
                // Do not call mepr-account-updated here - it's already called in save_extra_profile_fields() above
                // MeprEvent::record('member-account-updated', $mepr_current_user);.
            }
        } elseif (isset($_REQUEST['message']) && $_REQUEST['message'] == 'password_updated') {
            $message = __('Your password was successfully updated.', 'memberpress');
        }

        // Load user last in case we saved above, we want the saved info to show up!
        $mepr_current_user = new MeprUser($mepr_current_user->ID);

        if (( MeprReadyLaunchCtrl::template_enabled('account') || MeprAppHelper::has_block('memberpress/pro-account-tabs') )) {
            if (is_array($atts)) {
                extract($atts, EXTR_SKIP);
            }
            $mepr_options = MeprOptions::fetch();
            $account_url  = $mepr_options->account_page_url();
            $delim        = MeprAppCtrl::get_param_delimiter_char($account_url);

            $mepr_current_user = MeprUtils::get_currentuserinfo();

            $address_fields = MeprUsersHelper::get_address_fields($mepr_current_user);
            $address_values = [];
            foreach ($address_fields as $address_field) {
                $value            = $mepr_current_user ? get_user_meta($mepr_current_user->ID, $address_field->field_key, true) : '';
                $address_values[] = $value;
            }

            static $unique_suffix = 0;
            $unique_suffix++;

            $first_name_value = '';
            if (isset($user_first_name)) {
                $first_name_value = esc_attr(stripslashes($user_first_name));
            } elseif (MeprUtils::is_user_logged_in()) {
                $first_name_value = (string) $mepr_current_user->first_name;
            }

            $last_name_value = '';
            if (isset($user_last_name)) {
                $last_name_value = esc_attr(stripslashes($user_last_name));
            } elseif (MeprUtils::is_user_logged_in()) {
                $last_name_value = (string) $mepr_current_user->last_name;
            }

            $custom_fields = MeprUsersHelper::get_custom_fields();
            ob_start();
            foreach ($custom_fields as $custom_field) {
                if (isset($custom_field->show_in_account) && ! $custom_field->show_in_account) {
                    continue;
                }
                MeprUsersHelper::render_pro_templates_custom_field_values($custom_field, $mepr_current_user);
            }
            $custom_fields_values = ob_get_clean();

            $has_welcome_image = $welcome_image = null;

            // Has welcome image? Priority given to shortcode.
            if (isset($atts['show_welcome_image'])) {
                $show_welcome_image = filter_var($atts['show_welcome_image'], FILTER_VALIDATE_BOOLEAN);
            } elseif (isset($mepr_options->design_account_welcome_img)) {
                $show_welcome_image = $mepr_options->design_account_welcome_img;
            }

            // Get welcome image? Priority given to shortcode.
            if (isset($atts['welcome_image']) && $atts['welcome_image'] > 0) {
                $welcome_image = wp_get_attachment_url($atts['welcome_image']);
            } elseif (isset($mepr_options->design_account_welcome_img)) {
                $welcome_image = wp_get_attachment_url($mepr_options->design_account_welcome_img);
            }

            // Change the account url if it's a block template.
            if (MeprAppHelper::has_block('memberpress/pro-account-tabs')) {
                $account_url = get_permalink();
            }

            MeprView::render('/readylaunch/account/home', get_defined_vars());
        } else {
            MeprView::render('/account/home', get_defined_vars());
        }
    }

    /**
     * Renders the password update section of the account page.
     *
     * @return void
     */
    public function password()
    {
        $mepr_current_user = MeprUtils::get_currentuserinfo();
        $mepr_options      = MeprOptions::fetch();
        $account_url       = $mepr_options->account_page_url();
        $delim             = MeprAppCtrl::get_param_delimiter_char($account_url);

        if (isset($_REQUEST['error'])) {
            if ($_REQUEST['error'] == 'weak') {
                $errors = [__('Password update failed, please check that your password meets the minimum strength requirement.', 'memberpress')];
            } else {
                $errors = [__('Password update failed, please be sure your passwords match and try again.', 'memberpress')];
            }
        }

        if (MeprReadyLaunchCtrl::template_enabled('account')) {
            MeprView::render('/readylaunch/account/password', get_defined_vars());
        } else {
            MeprView::render('/account/password', get_defined_vars());
        }
    }

    /**
     * Renders the payments section of the account page.
     *
     * @param array $args Optional arguments for rendering payments.
     *
     * @return void
     */
    public function payments($args = [])
    {
        global $wpdb;
        $mepr_current_user = MeprUtils::get_currentuserinfo();
        $mepr_options      = MeprOptions::fetch();
        $account_url       = $_SERVER['REQUEST_URI']; // Use URI for BuddyPress compatibility.
        $delim             = MeprAppCtrl::get_param_delimiter_char($account_url);
        $perpage           = MeprHooks::apply_filters('mepr_payments_per_page', 10);
        $curr_page         = (isset($_GET['currpage']) && is_numeric($_GET['currpage'])) ? $_GET['currpage'] : 1;
        $start             = ($curr_page - 1) * $perpage;
        $end               = $start + $perpage;

        if (isset($args['mode']) && 'readylaunch' == $args['mode']) {
            $perpage = isset($args['count']) ? $args['count'] + $perpage : $perpage;
        }

        $list_table = MeprTransaction::list_table(
            'created_at',
            'DESC',
            $curr_page,
            '',
            'any',
            $perpage,
            [
                'member'   => $mepr_current_user->user_login,
                'statuses' => [MeprTransaction::$complete_str],
            ]
        );

        $payments  = $list_table['results'];
        $all       = $list_table['count'];
        $next_page = (($curr_page * $perpage) >= $all) ? false : $curr_page + 1;
        $prev_page = ($curr_page > 1) ? $curr_page - 1 : false;

        if ($mepr_options->design_enable_account_template) {
            MeprView::render('/readylaunch/account/payments', get_defined_vars());
        } else {
            MeprView::render('/account/payments', get_defined_vars());
        }
    }

    /**
     * Renders the subscriptions section of the account page.
     *
     * @param string $message Optional message to display.
     * @param array  $errors  Optional errors to display.
     * @param array  $args    Optional arguments for rendering subscriptions.
     *
     * @return void
     */
    public function subscriptions($message = '', $errors = [], $args = [])
    {
        global $wpdb;
        $mepr_current_user = MeprUtils::get_currentuserinfo();
        $mepr_options      = MeprOptions::fetch();
        $account_url       = $_SERVER['REQUEST_URI']; // Use URI for BuddyPress compatibility.
        $delim             = MeprAppCtrl::get_param_delimiter_char($account_url);
        $perpage           = MeprHooks::apply_filters('mepr_subscriptions_per_page', 10);
        $curr_page         = (isset($_GET['currpage']) && is_numeric($_GET['currpage'])) ? $_GET['currpage'] : 1;
        $start             = ($curr_page - 1) * $perpage;
        $end               = $start + $perpage;

        // This is necessary to optimize the queries ... only query what we need.
        $sub_cols = ['id','user_id','product_id','subscr_id','status','created_at','expires_at','active'];

        if (isset($args['mode']) && 'readylaunch' == $args['mode']) {
            $perpage = isset($args['count']) ? $args['count'] + $perpage : $perpage;
        }

        $table = MeprSubscription::account_subscr_table(
            'created_at',
            'DESC',
            $curr_page,
            '',
            'any',
            $perpage,
            false,
            [
                'member'   => $mepr_current_user->user_login,
                'statuses' => [
                    MeprSubscription::$active_str,
                    MeprSubscription::$suspended_str,
                    MeprSubscription::$cancelled_str,
                ],
            ],
            $sub_cols
        );

        $subscriptions = $table['results'];
        $all           = $table['count'];
        $next_page     = (($curr_page * $perpage) >= $all) ? false : $curr_page + 1;
        $prev_page     = ($curr_page > 1) ? $curr_page - 1 : false;

        if ($mepr_options->design_enable_account_template) {
            MeprView::render('/readylaunch/shared/errors', get_defined_vars());
            MeprView::render('/readylaunch/account/subscriptions', get_defined_vars());
        } else {
            MeprView::render('/shared/errors', get_defined_vars());
            MeprView::render('/account/subscriptions', get_defined_vars());
        }
    }

    /**
     * Suspends a user's subscription.
     *
     * @return void
     */
    public function suspend()
    {
        $mepr_current_user = MeprUtils::get_currentuserinfo();
        $sub               = new MeprSubscription($_GET['sub']);
        $errors            = [];
        $message           = '';

        if ($sub->user_id == $mepr_current_user->ID) {
            $pm = $sub->payment_method();

            if ($pm->can('suspend-subscriptions')) {
                try {
                    $pm->process_suspend_subscription($sub->id);
                    $message = __('Your subscription was successfully paused.', 'memberpress');
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $this->subscriptions($message, $errors);
    }

    /**
     * Resumes a user's subscription.
     *
     * @return void
     */
    public function resume()
    {
        $mepr_current_user = MeprUtils::get_currentuserinfo();
        $sub               = new MeprSubscription($_GET['sub']);
        $errors            = [];
        $message           = '';

        if ($sub->user_id == $mepr_current_user->ID) {
            $pm = $sub->payment_method();

            if ($pm->can('suspend-subscriptions')) {
                try {
                    $pm->process_resume_subscription($sub->id);
                    $message = __('You successfully resumed your subscription.', 'memberpress');
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $this->subscriptions($message, $errors);
    }

    /**
     * Cancels a user's subscription.
     *
     * @return void
     */
    public function cancel()
    {
        $mepr_current_user = MeprUtils::get_currentuserinfo();
        $sub               = new MeprSubscription($_GET['sub']);
        $errors            = [];
        $message           = '';
        $success_message   = __('Your subscription was successfully cancelled.', 'memberpress');

        static $already_cancelled;

        if ($already_cancelled === true) {
            $message = $success_message;
        } elseif ($sub->user_id == $mepr_current_user->ID) {
            $already_cancelled = true;
            $pm                = $sub->payment_method();

            if ($pm->can('cancel-subscriptions')) {
                try {
                    $pm->process_cancel_subscription($sub->id);
                    $message = $success_message;
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $this->subscriptions($message, $errors);
    }

    /**
     * Updates a user's subscription information.
     *
     * @return void
     */
    public function update()
    {
        $mepr_current_user = MeprUtils::get_currentuserinfo();
        $sub               = new MeprSubscription($_REQUEST['sub']);

        if ($sub->user_id == $mepr_current_user->ID) {
            $pm = $sub->payment_method();

            if (strtoupper($_SERVER['REQUEST_METHOD'] == 'GET')) { // DISPLAY FORM.
                $pm->display_update_account_form($sub->id, []);
            } elseif (strtoupper($_SERVER['REQUEST_METHOD'] == 'POST')) { // PROCESS FORM.
                $errors  = $pm->validate_update_account_form([]);
                $message = '';

                if (empty($errors)) {
                    try {
                        $pm->process_update_account_form($sub->id);
                        $message = __('Your account information was successfully updated.', 'memberpress');
                    } catch (Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                }

                $pm->display_update_account_form($sub->id, $errors, $message);
            }
        }
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing
    /**
     * Update account form processing for asynchronous gateways.
     *
     * @return void
     */
    public function process_update_account_form_ajax()
    {
        try {
            $options         = MeprOptions::fetch();
            $subscription_id = (int) $_POST['mepr_subscription_id'] ?? 0;

            if (empty($subscription_id)) {
                throw new MeprGatewayException(__('Bad request', 'memberpress'));
            }

            if (!is_user_logged_in()) {
                throw new MeprGatewayException(__('Sorry, you must be logged in to do this.', 'memberpress'));
            }

            if (!check_ajax_referer('mepr_process_update_account_form', false, false)) {
                throw new MeprGatewayException(__('Security check failed.', 'memberpress'));
            }

            $sub = new MeprSubscription((int) $_POST['mepr_subscription_id'] ?? 0);

            if (!($sub->id > 0)) {
                throw new MeprGatewayException(__('Subscription not found', 'memberpress'));
            }

            $usr = $sub->user();

            if ($usr->ID != get_current_user_id()) {
                throw new MeprGatewayException(__('This subscription is for another user.', 'memberpress'));
            }

            $pm = $sub->payment_method();

            if (!$pm instanceof MeprBaseRealAjaxGateway) {
                throw new MeprGatewayException(__('Invalid payment gateway', 'memberpress'));
            }

            $pm->process_update_account_form_ajax($sub);

            wp_send_json_success(
                add_query_arg(
                    array_map(
                        'rawurlencode',
                        [
                            'action' => 'update',
                            'sub' => $sub->id,
                            'message' => __('Your account information was successfully updated.', 'memberpress'),
                        ]
                    ),
                    $options->account_page_url()
                )
            );
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing

    /**
     * Upgrades a user's subscription.
     *
     * @return void
     */
    public function upgrade()
    {
        $sub = new MeprSubscription($_GET['sub']);
        $prd = $sub->product();
        $grp = $prd->group();

        // TODO: Uyeah, we may want to come up with a more elegant solution here
        // for now we have to do a js redirect because we're in mid-page render.
        ?>
    <script>
      top.window.location = '<?php echo $grp->url(); ?>';
    </script>
        <?php
    }

    /**
     * Shortcode for displaying the account form.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Content within the shortcode.
     *
     * @return string The account form HTML.
     */
    public function account_form_shortcode($atts, $content = '')
    {
        // No need to validate anything as the below function already
        // does all the validations. This is just a wrapper.
        return $this->display_account_form($content, $atts);
    }

    /**
     * Displays the account form.
     *
     * @param string $content Content within the form.
     * @param array  $atts    Attributes for the form.
     *
     * @return string The account form HTML.
     */
    public function display_account_form($content = '', $atts = [])
    {
        global $post;

        // Static var to prevent duplicate token issues with Stripe.
        static $new_content;
        static $content_length;

        // Init this posts static values.
        if (!isset($new_content) || empty($new_content) || !isset($content_length)) {
            $new_content    = '';
            $content_length = -1;
        }

        if ($new_content && strlen($content) == $content_length) {
            return $new_content;
        }

        $content_length = strlen($content);

        if (MeprUtils::is_user_logged_in()) {
            ob_start();
            MeprAccountCtrl::render($atts);
            $content .= ob_get_clean();
        } else {
            $content = do_shortcode(MeprRulesCtrl::unauthorized_message($post));
        }

        $new_content = $content;

        return $new_content;
    }

    /**
     * Retrieves account links for the user.
     *
     * @return string The account links HTML.
     */
    public function get_account_links()
    {
        $mepr_options = MeprOptions::fetch();
        ob_start();

        if (MeprUtils::is_user_logged_in()) {
            $account_url = $mepr_options->account_page_url();
            $logout_url  = MeprUtils::logout_url();
            if (MeprReadyLaunchCtrl::template_enabled('account')) {
                MeprView::render('/readylaunch/account/logged_in_template', get_defined_vars());
            } else {
                MeprView::render('/account/logged_in_template', get_defined_vars());
            }
        } else {
            $login_url = MeprUtils::login_url();
            if (MeprReadyLaunchCtrl::template_enabled('account')) {
                MeprView::render('/readylaunch/account/logged_out_template', get_defined_vars());
            } else {
                MeprView::render('/account/logged_out_template', get_defined_vars());
            }
        }

        return ob_get_clean();
    }

    /**
     * Outputs account metadata based on attributes.
     *
     * @param array  $atts    Attributes for outputting metadata.
     * @param string $content Content within the metadata.
     *
     * @return string The account metadata.
     */
    public function output_account_meta($atts = [], $content = '')
    {
        global $mepr_options, $user_ID;

        if ((int)$user_ID < 1 || !isset($atts['field'])) {
            return '';
        }

        $ums      = MeprUtils::get_formatted_usermeta($user_ID);
        $usermeta = [];

        if (!empty($ums)) {
            foreach ($ums as $umkey => $umval) {
                $usermeta["{$umkey}"] = $umval;
            }
        }

        // Get some additional params yo.
        $userdata = get_userdata($user_ID);

        foreach ($userdata->data as $key => $value) {
            $usermeta[$key] = $value;
        }

        // We can begin to define more custom return cases in here...
        switch ($atts['field']) {
            case 'full_name':
                return ucfirst($usermeta['first_name']) . ' ' . ucfirst($usermeta['last_name']);
            break;
            case 'full_name_last_first':
                return ucfirst($usermeta['last_name']) . ', ' . ucfirst($usermeta['first_name']);
            break;
            case 'first_name_last_initial':
                return ucfirst($usermeta['first_name']) . ' ' . ucfirst($usermeta['last_name'][0]) . '.';
            break;
            case 'last_name_first_initial':
                return ucfirst($usermeta['last_name']) . ', ' . ucfirst($usermeta['first_name'][0]) . '.';
            break;
            case 'user_registered':
                return MeprAppHelper::format_date($usermeta[$atts['field']]);
            break;
            case 'mepr_user_message':
                return wpautop(stripslashes(do_shortcode($usermeta[$atts['field']])));
            break;
            default:
                // Make sure field actually exists.
                if (isset($usermeta[$atts['field']]) && !empty($usermeta[$atts['field']])) {
                    return $usermeta[$atts['field']];
                }
                break;
        }
    }

    /**
     * Saves a new password for the user.
     *
     * @param integer $user_id          The user ID.
     * @param string  $new_pass         The new password.
     * @param string  $new_pass_confirm The new password confirmation.
     *
     * @return void
     */
    public function save_new_password($user_id, $new_pass, $new_pass_confirm)
    {
        $mepr_options = MeprOptions::fetch();
        $account_url  = $mepr_options->account_page_url();
        $delim        = MeprAppCtrl::get_param_delimiter_char($account_url);

        $user = MeprUtils::get_currentuserinfo();

        // Check password strength first.
        if ($mepr_options->enforce_strong_password && isset($_POST['mp-pass-strength']) && (int)$_POST['mp-pass-strength'] < MeprZxcvbnCtrl::get_required_int()) {
            MeprUtils::wp_redirect($account_url . $delim . 'action=newpassword&error=weak');
        }

        if ($user_id && $user && ($user->ID == $user_id)) {
            if (($new_pass == $new_pass_confirm) && !empty($new_pass)) {
                $user->set_password($new_pass);
                $user->store();
                MeprUtils::wp_redirect($account_url . $delim . 'action=home&message=password_updated');
            }
        }

        MeprUtils::wp_redirect($account_url . $delim . 'action=newpassword&error=failed');
    }

    /**
     * Save account profile fields for Ready Launch account template
     *
     * @return void
     */
    public function save_profile_fields()
    {
        // Check for nonce security.
        if (isset($_POST['nonce']) && ! wp_verify_nonce($_POST['nonce'], 'mepr_account_update')) {
            die('Busted!');
        }

        // Since we use user_* for these, we need to artifically set the $_POST keys correctly for this to work.
        if (isset($_POST['user_first_name']) && ( !isset($_POST['first_name']) || empty($_POST['first_name']))) {
            $_POST['first_name'] = (!empty($_POST['user_first_name'])) ? MeprUtils::sanitize_name_field(wp_unslash($_POST['user_first_name'])) : '';
            unset($_POST['user_first_name']);
        }

        if (isset($_POST['user_last_name']) && (!isset($_POST['last_name']) || empty($_POST['last_name']))) {
            $_POST['last_name'] = (!empty($_POST['user_last_name'])) ? MeprUtils::sanitize_name_field(wp_unslash($_POST['user_last_name'])) : '';
            unset($_POST['user_last_name']);
        }

        $field_key    = array_map('sanitize_key', array_merge(array_keys($_POST), array_keys($_FILES)));
        $current_user = MeprUtils::get_currentuserinfo();

        $errors = MeprHooks::apply_filters('mepr-validate-account-ajax', [], $current_user, $field_key);

        if (empty($errors)) {
            if (isset($_POST['user_email']) && !empty($_POST['user_email'])) {
                $new_email = sanitize_email($_POST['user_email']);

                if ($current_user->user_email != $new_email) {
                    $current_user->user_email = $new_email;
                    $current_user->store();
                    MeprHooks::do_action('mepr-update-new-user-email', $current_user);
                }
            }

            MeprUsersCtrl::save_extra_profile_fields($current_user->ID, true, false, false, $field_key);

            MeprHooks::do_action('mepr-save-account', $current_user);

            wp_send_json_success();
        } else {
            wp_send_json_error($errors);
        }
    }


    /**
     * Generates a shortcode output for offline gateway payment instructions.
     *
     * @param array  $atts    Shortcode attributes, including 'gateway_id'.
     * @param string $content Content within the shortcode.
     *
     * @return string The payment instructions or empty string if conditions aren't met.
     */
    public function offline_gateway_instructions($atts = [], $content = '')
    {
        if (!isset($atts['gateway_id']) || empty($atts['gateway_id'])) {
            return '';
        }

        if (isset($_GET['trans_num']) && !empty($_GET['trans_num'])) {
            $txn = MeprTransaction::get_one_by_trans_num($_GET['trans_num']);
        } elseif (isset($_GET['transaction_id']) && !empty($_GET['transaction_id'])) {
            $txn = MeprTransaction::get_one($_GET['transaction_id']);
        } else {
            return '';
        }

        if (!isset($txn->gateway) || $txn->gateway != $atts['gateway_id']) {
            return '';
        }

        return do_shortcode($content);
    }

    /**
     * Modifies the account page title in the loop.
     *
     * @param string $title The current title.
     *
     * @return string The modified title.
     */
    public function account_page_the_title($title)
    {
        if (!in_the_loop() || !MeprHooks::apply_filters('mepr-account-nav-page-titles', false)) {
            return $title;
        }

        return $this->account_page_title($title);
    }
    /**
     * Modifies the account page browser title.
     *
     * @param string $title The current browser title.
     *
     * @return string The modified browser title.
     */
    public function account_page_browser_title($title)
    {
        if (!MeprHooks::apply_filters('mepr-account-nav-broswer-titles', false)) {
            return $title;
        }

        return $this->account_page_title($title);
    }
    /**
     * Modifies the account page title based on the action.
     *
     * @param string $title The current title.
     *
     * @return string The modified title.
     */
    public function account_page_title($title)
    {
        global $post;

        // Only apply the title changes on the account nave pages if it is turned on
        // and buddy press intregration is not installed.
        if (class_exists('MpBuddyPress')) {
            return $title;
        }

        // If we don't have a post, just return the title.
        if (!isset($post) || !isset($post->ID) || $post->ID <= 0) {
            return $title;
        }

        $action      = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        $title_after = '';
        $sep         = ' ' . apply_filters('document_title_separator', '-') . ' ';

        if (MeprUser::is_account_page($post)) {
            switch ($action) {
                case 'subscriptions':
                    $title_after = MeprHooks::apply_filters('mepr-account-subscriptions-title', _x('Subscriptions', 'ui', 'memberpress'));
                    break;
                case 'payments':
                    $title_after = MeprHooks::apply_filters('mepr-account-payments-title', _x('Payments', 'ui', 'memberpress'));
                    break;
                case 'courses':
                    $title_after = MeprHooks::apply_filters('mepr-account-courses-title', _x('Courses', 'ui', 'memberpress'));
                    break;
                default:
                    // For custom tabs on account page.
                    $title_after = MeprHooks::apply_filters('mepr-custom-account-nav-title', '', $action);
                    break;
            }
        }

        return !empty($title_after) ? $title . $sep . $title_after : $title;
    }
}
