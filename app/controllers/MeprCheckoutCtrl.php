<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprCheckoutCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for various actions and filters related to checkout.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('wp_enqueue_scripts', [$this,'enqueue_scripts']);
        add_action('mepr-signup', [$this, 'process_spc_payment_form'], 100); // 100 priority to give other things a chance to hook in before SPC takes over the world
        add_filter('mepr_signup_form_payment_description', [$this, 'maybe_render_payment_form'], 10, 4);
        MeprHooks::add_shortcode('mepr-ecommerce-tracking', [$this, 'replace_tracking_codes']);
        add_filter('mepr-signup-checkout-url', [$this, 'handle_spc_checkout_url'], 10, 2);
        add_action('mepr_readylaunch_thank_you_page_after_content', [$this, 'maybe_show_order_bumps_error_message_in_readylaunch']);
        add_filter('mepr_options_helper_payment_methods', [$this, 'exclude_disconnected_gateways'], 10, 3);
        add_filter('the_content', [$this, 'maybe_show_order_bumps_error_message'], 1);
        add_action('wp_ajax_mepr_get_checkout_state', [$this, 'get_checkout_state']);
        add_action('wp_ajax_nopriv_mepr_get_checkout_state', [$this, 'get_checkout_state']);
        add_action('wp_ajax_mepr_process_signup_form', [$this, 'process_signup_form_ajax']);
        add_action('wp_ajax_nopriv_mepr_process_signup_form', [$this, 'process_signup_form_ajax']);
        add_action('wp_ajax_mepr_process_payment_form', [$this, 'process_payment_form_ajax']);
        add_action('wp_ajax_nopriv_mepr_process_payment_form', [$this, 'process_payment_form_ajax']);
        add_action('wp_ajax_mepr_debug_checkout_error', [$this, 'debug_checkout_error']);
        add_action('wp_ajax_nopriv_mepr_debug_checkout_error', [$this, 'debug_checkout_error']);
    }

    /**
     * Show order bumps error message in ReadyLaunch if applicable.
     *
     * @return void
     */
    public function maybe_show_order_bumps_error_message_in_readylaunch()
    {
        if (!isset($_GET['trans_num'])) {
            return;
        }

        $trans_num    = sanitize_text_field(wp_unslash($_GET['trans_num']));
        $original_txn = MeprTransaction::get_one_by_trans_num($trans_num);

        if (isset($original_txn->id) && $original_txn->id > 0) {
            $original_txn = new MeprTransaction($original_txn->id);
        } else {
            return;
        }

        $order = $original_txn->order();

        if (!$order) {
            return;
        }

        $bumps  = MeprTransaction::get_all_by_order_id($order->id);
        $errors = [];

        foreach ($bumps as $txn) {
            $product = $txn->product();
            $meta    = $txn->get_meta('_authorizenet_txn_error_', true);

            if (empty($meta)) {
                continue;
            }

            $error         = (explode('|', $meta));
            $error_message = sprintf(
                // Translators: %1$s: product name, %2$s: opening anchor tag, %3$s: closing anchor tag.
                __('Notice: %1$s purchase failed. Click %2$shere%3$s to purchase it separately.', 'memberpress'),
                $product->post_title,
                '<a href="' . get_permalink($product->ID) . '" target="_blank">',
                '</a>'
            );

            if (! empty($error)) {
                $errors[] = $error_message;
            }
        }

        if (!empty($errors)) {
            MeprView::render('/shared/errors', get_defined_vars());
            echo '<br>';
        }
    }

    /**
     * Show order bumps error message on the thank you page if applicable.
     *
     * @param string $content The content of the page.
     *
     * @return string The modified content.
     */
    public function maybe_show_order_bumps_error_message($content)
    {
        if (is_singular() && in_the_loop() && is_main_query()) {
            $mepr_options = MeprOptions::fetch();

            if ($mepr_options->thankyou_page_id != get_the_ID()) {
                return $content;
            }

            if (!isset($_GET['trans_num'])) {
                return $content;
            }

            $trans_num    = sanitize_text_field(wp_unslash($_GET['trans_num']));
            $original_txn = MeprTransaction::get_one_by_trans_num($trans_num);

            if (isset($original_txn->id) && $original_txn->id > 0) {
                $original_txn = new MeprTransaction($original_txn->id);
            } else {
                return $content;
            }

            $order = $original_txn->order();

            if (!$order) {
                return $content;
            }

            $bumps  = MeprTransaction::get_all_by_order_id($order->id);
            $errors = [];

            foreach ($bumps as $txn) {
                $product = $txn->product();
                $meta    = $txn->get_meta('_authorizenet_txn_error_', true);

                if (empty($meta)) {
                    continue;
                }

                $error         = (explode('|', $meta));
                $error_message = sprintf(
                    // Translators: %1$s: product name, %2$s: opening anchor tag, %3$s: closing anchor tag.
                    __('Notice: %1$s purchase failed. Click %2$shere%3$s to purchase it separately.', 'memberpress'),
                    $product->post_title,
                    '<a href="' . get_permalink($product->ID) . '" target="_blank">',
                    '</a>'
                );

                if (! empty($error)) {
                    $errors[] = $error_message;
                }
            }

            if (!empty($errors)) {
                ob_start();
                MeprView::render('/shared/errors', get_defined_vars());
                $error_section = ob_get_contents();
                ob_end_clean();
                $content .= $error_section;
            }
        }

        return $content;
    }

    /**
     * Replace tracking codes in the content with actual values.
     *
     * @param array  $atts    The shortcode attributes.
     * @param string $content The content to replace codes in.
     *
     * @return string The content with replaced tracking codes.
     */
    public function replace_tracking_codes($atts, $content = '')
    {
        $atts = shortcode_atts(
            [
                'membership' => null,
            ],
            $atts,
            'mepr-ecommerce-tracking'
        );

        if (
            !($this->request_has_valid_thank_you_params($_GET) &&
            $this->request_has_valid_thank_you_membership_id($_GET) &&
            $this->request_has_valid_thank_you_trans_num($_GET) &&
            $this->request_has_valid_thank_you_membership($atts, $_GET))
        ) {
            return '';
        }

        $tracking_codes = [
            '%%subtotal%%'          => ['MeprTransaction' => 'tracking_subtotal'],
            '%%tax_amount%%'        => ['MeprTransaction' => 'tracking_tax_amount'],
            '%%tax_rate%%'          => ['MeprTransaction' => 'tracking_tax_rate'],
            '%%total%%'             => ['MeprTransaction' => 'tracking_total'],
            '%%txn_num%%'           => ['MeprTransaction' => 'trans_num'],
            '%%sub_id%%'            => ['MeprTransaction' => 'subscription_id'],
            '%%txn_id%%'            => ['MeprTransaction' => 'id'],
            '%%sub_num%%'           => ['MeprSubscription' => 'subscr_id'],
            '%%membership_amount%%' => ['MeprSubscription' => 'price'],
            '%%trial_days%%'        => ['MeprSubscription' => 'trial_days'],
            '%%trial_amount%%'      => ['MeprSubscription' => 'trial_amount'],
            '%%username%%'          => ['MeprUser' => 'user_login'],
            '%%user_email%%'        => ['MeprUser' => 'user_email'],
            '%%user_id%%'           => ['MeprUser' => 'ID'],
            '%%membership_name%%'   => ['MeprProduct' => 'post_title'],
            '%%membership_id%%'     => ['MeprProduct' => 'ID'],
        ];

        foreach ($tracking_codes as $code => $mapping) {
            // Make sure the content has a code to replace.
            if (strpos($content, $code) !== false) {
                foreach ($mapping as $model => $attr) {
                    switch ($model) {
                        case 'MeprTransaction':
                              // Only fetch the object once!
                            if (!isset($txn)) {
                                if (isset($_GET['trans_num']) && !empty($_GET['trans_num'])) {
                                          $rec = $model::get_one_by_trans_num($_GET['trans_num']);
                                          $txn = $obj = new MeprTransaction($rec->id);
                                } elseif (isset($_GET['transaction_id']) && !empty($_GET['transaction_id'])) {
                                    $txn = $obj = new MeprTransaction((int) $_GET['transaction_id']);
                                }
                            }
                            break;
                        case 'MeprSubscription':
                            if (!isset($sub)) {
                                if (isset($_GET['subscr_id']) && !empty($_GET['subscr_id'])) {
                                    $sub = $obj = $model::get_one_by_subscr_id($_GET['subscr_id']);
                                } elseif (isset($_GET['subscription_id']) && !empty($_GET['subscription_id'])) {
                                    $sub = $obj = $model::get_one((int) $_GET['subscription_id']);
                                }
                            }
                            break;
                        case 'MeprUser':
                            if (!isset($user)) {
                                $user = $obj = MeprUtils::get_currentuserinfo();
                            }
                            break;
                        case 'MeprProduct':
                            if (!isset($prod) && isset($_GET['membership_id']) && !empty($_GET['membership_id'])) {
                                $prod = $obj = new $model($_GET['membership_id']);
                            }
                            break;
                        default:
                              unset($obj);
                    }
                    if (isset($obj) && (isset($obj->id) && (int) $obj->id > 0) || (isset($obj->ID) && (int) $obj->ID > 0)) {
                        $content = str_replace($code, $obj->$attr, $content);
                        break; // Once we've replaced the code, it's time to move on.
                    }
                }
                // Blank out the code if it isn't found.
                $content = str_replace($code, '', $content);
            }
        }
        return $content;
    }

    /**
     * Enqueue gateway specific js/css if required.
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        global $post;
        $mepr_options = MeprOptions::fetch();

        if (MeprProduct::is_product_page($post)) {
            $has_phone = false;

            if (! empty($mepr_options->custom_fields)) {
                foreach ($mepr_options->custom_fields as $field) {
                    if ('tel' === $field->field_type && $field->show_on_signup) {
                        $has_phone = true;
                        break;
                    }
                }
            }

            // Check if there's a phone field.
            if ($has_phone) {
                wp_enqueue_style('mepr-phone-css', MEPR_CSS_URL . '/vendor/intlTelInput.min.css', '', '16.0.0');
                wp_enqueue_style('mepr-tel-config-css', MEPR_CSS_URL . '/tel_input.css', '', MEPR_VERSION);
                wp_enqueue_script('mepr-phone-js', MEPR_JS_URL . '/vendor/intlTelInput.js', '', '16.0.0', true);
                wp_enqueue_script('mepr-tel-config-js', MEPR_JS_URL . '/tel_input.js', ['mepr-phone-js', 'mp-signup'], MEPR_VERSION, true);
                wp_localize_script('mepr-tel-config-js', 'meprTel', MeprHooks::apply_filters('mepr-phone-input-config', [
                    'defaultCountry' => strtolower(get_option('mepr_biz_country')),
                    'utilsUrl'       => MEPR_JS_URL . '/vendor/intlTelInputUtils.js',
                    'onlyCountries'  => '',
                ]));
            }

            $txn = null;
            $pm  = null;

            if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'checkout') {
                if (isset($_REQUEST['mepr_transaction_id'])) {
                    $txn = new MeprTransaction($_REQUEST['mepr_transaction_id']);
                } elseif (isset($_REQUEST['txn'])) {
                    $txn = new MeprTransaction($_REQUEST['txn']);
                }

                if ($txn && $txn->id > 0) {
                    $pm = $txn->payment_method();
                }
            } elseif (
                MeprUtils::is_user_logged_in() &&
                isset($_REQUEST['action']) && $_REQUEST['action'] === 'update' &&
                isset($_REQUEST['sub'])
            ) {
                $sub = new MeprSubscription($_REQUEST['sub']);
                if ($sub->id > 0) {
                    $pm = $sub->payment_method();
                }
            }

            if ($pm instanceof MeprBaseRealGateway) {
                wp_register_script('mepr-checkout-js', MEPR_JS_URL . '/checkout.js', ['jquery', 'jquery.payment'], MEPR_VERSION);
                $pm->enqueue_payment_form_scripts();
            }
        }
    }

    /**
     * Renders the payment form if SPC is enabled and supported by the payment method.
     * Called from: mepr_signup_form_payment_description filter
     * Returns: description includding form for SPC if enabled
     *
     * @param string      $description    The description of the payment form.
     * @param object      $payment_method The payment method object.
     * @param boolean     $first          Whether this is the first payment method.
     * @param object|null $product        The product object.
     *
     * @return string The modified description.
     */
    public function maybe_render_payment_form($description, $payment_method, $first, $product = null)
    {
        $mepr_options = MeprOptions::fetch();
        if (($mepr_options->enable_spc || $mepr_options->design_enable_checkout_template) && $payment_method->has_spc_form) {
            // TODO: Maybe we queue these up from wp_enqueue_scripts?
            wp_register_script('mepr-checkout-js', MEPR_JS_URL . '/checkout.js', ['jquery', 'jquery.payment'], MEPR_VERSION);
            wp_enqueue_script('mepr-checkout-js');
            $payment_method->enqueue_payment_form_scripts();
            $description = $payment_method->spc_payment_fields($product);
        }
        return $description;
    }

    /**
     * Display the signup form for a product.
     *
     * @param object $product The product object.
     *
     * @return void
     */
    public function display_signup_form($product)
    {
        $mepr_options     = MeprOptions::fetch();
        $mepr_blogurl     = home_url();
        $mepr_coupon_code = '';

        extract($_REQUEST, EXTR_SKIP);
        if (isset($_REQUEST['errors'])) {
            if (is_array($_REQUEST['errors'])) {
                $errors = array_map('wp_kses_post', $_REQUEST['errors']); // Use kses here so our error HTML isn't stripped.
            } else {
                $errors = [wp_kses_post($_REQUEST['errors'])];
            }
        }
        // See if Coupon was passed via GET.
        if (isset($_GET['coupon']) && !empty($_GET['coupon'])) {
            if (MeprCoupon::is_valid_coupon_code($_GET['coupon'], $product->ID)) {
                $mepr_coupon_code = htmlentities(sanitize_text_field($_GET['coupon']));
            }
        }

        if (MeprUtils::is_user_logged_in()) {
            $mepr_current_user = MeprUtils::get_currentuserinfo();
        }

        $first_name_value = '';
        if (isset($user_first_name)) {
            $first_name_value = esc_attr(stripslashes($user_first_name));
        } elseif (MeprUtils::is_user_logged_in()) {
            $first_name_value = (string)$mepr_current_user->first_name;
        }

        $last_name_value = '';
        if (isset($user_last_name)) {
            $last_name_value = esc_attr(stripslashes($user_last_name));
        } elseif (MeprUtils::is_user_logged_in()) {
            $last_name_value = (string)$mepr_current_user->last_name;
        }

        if (isset($errors) and !empty($errors)) {
            MeprView::render('/shared/errors', get_defined_vars());
        }

        // Gather payment methods for checkout.
        $payment_methods = $product->payment_methods();
        if (empty($payment_methods)) {
            $payment_methods = array_keys($mepr_options->integrations);
        }
        $payment_methods = MeprHooks::apply_filters('mepr_options_helper_payment_methods', $payment_methods, 'mepr_payment_method', $product);
        $payment_methods = array_map(function ($pm_id) use ($mepr_options) {
            return $mepr_options->payment_method($pm_id);
        }, $payment_methods);

        static $unique_suffix = 0;
        $unique_suffix++;

        $payment_required = MeprHooks::apply_filters('mepr_signup_payment_required', $product->is_payment_required($mepr_coupon_code), $product);

        if ($mepr_options->enable_spc) {
            if (MeprReadyLaunchCtrl::template_enabled('checkout') || MeprAppHelper::has_block('memberpress/checkout')) {
                $is_rl_widget = ( is_active_sidebar('mepr_rl_registration_footer') || is_active_sidebar('mepr_rl_global_footer') );
                MeprView::render('/readylaunch/checkout/form', get_defined_vars());
            } else {
                MeprView::render('/checkout/spc_form', get_defined_vars());
            }
        } else {
            if (MeprReadyLaunchCtrl::template_enabled('checkout') || MeprAppHelper::has_block('memberpress/checkout')) {
                $is_rl_widget = ( is_active_sidebar('mepr_rl_registration_footer') || is_active_sidebar('mepr_rl_global_footer') );
                MeprView::render('/readylaunch/checkout/form', get_defined_vars());
            } else {
                MeprView::render('/checkout/form', get_defined_vars());
            }
        }
    }

    /**
     * Gets called on the 'init' hook ... used for processing aspects of the signup
     * form before the logic progresses on to 'the_content' ...
     *
     * @return void
     */
    public function process_signup_form()
    {
        $mepr_options = MeprOptions::fetch();

        // Validate the form post.
        $errors = MeprHooks::apply_filters('mepr-validate-signup', MeprUser::validate_signup($_POST, []));
        if (!empty($errors)) {
            $_POST['errors']    = $errors; // Deprecated?
            $_REQUEST['errors'] = $errors;

            return;
        }

        // Check if the user is logged in already.
        $is_existing_user = MeprUtils::is_user_logged_in();

        if ($is_existing_user) {
            $usr = MeprUtils::get_currentuserinfo();
        } else { // If new user we've got to create them and sign them in.
            $usr             = new MeprUser();
            $usr->user_login = ($mepr_options->username_is_email) ? sanitize_email($_POST['user_email']) : sanitize_user($_POST['user_login']);
            $usr->user_email = sanitize_email($_POST['user_email']);
            $usr->first_name = !empty($_POST['user_first_name']) ? MeprUtils::sanitize_name_field(wp_unslash($_POST['user_first_name'])) : '';
            $usr->last_name  = !empty($_POST['user_last_name']) ? MeprUtils::sanitize_name_field(wp_unslash($_POST['user_last_name'])) : '';

            $password = ($mepr_options->disable_checkout_password_fields === true) ? wp_generate_password() : $_POST['mepr_user_password'];
            // Have to use rec here because we unset user_pass on __construct.
            $usr->set_password($password);

            try {
                $usr->store();

                // We need to refresh the user object. In the case where emails are used as
                // usernames, the email & username could differ after the user is saved.
                $usr = new MeprUser($usr->ID);

                // Log the new user in.
                if (MeprHooks::apply_filters('mepr-auto-login', true, $_POST['mepr_product_id'], $usr)) {
                    wp_signon(
                        [
                            'user_login'    => $usr->user_login,
                            'user_password' => $password,
                        ],
                        MeprUtils::is_ssl() // May help with the users getting logged out when going between http and https.
                    );
                }

                MeprEvent::record('login', $usr); // Record the first login here.
            } catch (MeprCreateException $e) {
                $_POST['errors']    = [__('The user was unable to be saved.', 'memberpress')];  // Deprecated?
                $_REQUEST['errors'] = [__('The user was unable to be saved.', 'memberpress')];
                return;
            }
        }

        // Create a new transaction and set our new membership details.
        $txn          = new MeprTransaction();
        $txn->user_id = $usr->ID;

        // Get the membership in place.
        $txn->product_id = sanitize_text_field($_POST['mepr_product_id']);
        $product         = $txn->product();

        if (empty($product->ID)) {
            $_POST['errors']    = [__('Sorry, we were unable to find the membership.', 'memberpress')];
            $_REQUEST['errors'] = [__('Sorry, we were unable to find the membership.', 'memberpress')];
            return;
        }

        // If we're showing the fields on logged in purchases, let's save them here.
        if (!$is_existing_user || ($is_existing_user && $mepr_options->show_fields_logged_in_purchases)) {
            MeprUsersCtrl::save_extra_profile_fields($usr->ID, true, $product, true);
            $usr = new MeprUser($usr->ID); // Re-load the user object with the metadata now (helps with first name last name missing from hooks below).
        }

        // Needed for autoresponders (SPC + Stripe + Free Trial issue).
        MeprHooks::do_action('mepr-signup-user-loaded', $usr);

        // Set default price, adjust it later if coupon applies.
        $price = $product->adjusted_price();

        // Default coupon object.
        $cpn = (object)[
            'ID'         => 0,
            'post_title' => null,
        ];

        // Adjust membership price from the coupon code.
        if (isset($_POST['mepr_coupon_code']) && !empty($_POST['mepr_coupon_code'])) {
            // Coupon object has to be loaded here or else txn create will record a 0 for coupon_id.
            $mepr_coupon_code = htmlentities(sanitize_text_field($_POST['mepr_coupon_code']));
            $cpn              = MeprCoupon::get_one_from_code(sanitize_text_field($_POST['mepr_coupon_code']));

            if (($cpn !== false) || ($cpn instanceof MeprCoupon)) {
                $price = $product->adjusted_price($cpn->post_title);
            }
        }

        $txn->set_subtotal(MeprUtils::maybe_round_to_minimum_amount($price));

        // Set the coupon id of the transaction.
        $txn->coupon_id = $cpn->ID;

        // Figure out the Payment Method.
        if (isset($_POST['mepr_payment_method']) && !empty($_POST['mepr_payment_method'])) {
            $txn->gateway = sanitize_text_field($_POST['mepr_payment_method']);
        } else {
            $txn->gateway = MeprTransaction::$free_gateway_str;
        }

        // Let's checkout now.
        if ($txn->gateway === MeprTransaction::$free_gateway_str) {
            $signup_type = 'free';
        } else {
            $pm = $txn->payment_method();
            if ($pm instanceof MeprBaseExclusiveRecurringGateway) {
                $sub_attrs = $pm->subscription_attributes($product->plan_code);
                if ($pm->is_one_time_payment($product->plan_code)) {
                    $signup_type = 'non-recurring';
                    $price       = $sub_attrs['one_time_amount'];
                } else {
                    $signup_type = 'recurring';

                    // Create the subscription from the gateway plan.
                    $sub             = new MeprSubscription($sub_attrs);
                    $sub->user_id    = $usr->ID;
                    $sub->gateway    = $pm->id;
                    $sub->product_id = $product->ID;
                    $sub->maybe_prorate(); // Sub to sub.
                    $sub->store();

                    // Update the transaction with subscription id.
                    $txn->subscription_id = $sub->id;
                    $price                = $sub->price;
                }
                // Update subtotal.
                $txn->amount = $price;
            } elseif ($pm instanceof MeprBaseRealGateway) {
                // Set default price, adjust it later if coupon applies.
                $price = $product->adjusted_price();
                // Default coupon object.
                $cpn = (object)[
                    'ID'         => 0,
                    'post_title' => null,
                ];
                // Adjust membership price from the coupon code.
                if (isset($_POST['mepr_coupon_code']) && !empty($_POST['mepr_coupon_code'])) {
                    // Coupon object has to be loaded here or else txn create will record a 0 for coupon_id.
                    $cpn = MeprCoupon::get_one_from_code(sanitize_text_field($_POST['mepr_coupon_code']));
                    if (($cpn !== false) || ($cpn instanceof MeprCoupon)) {
                        $price = $product->adjusted_price($cpn->post_title);
                    }
                }
                $txn->set_subtotal(MeprUtils::maybe_round_to_minimum_amount($price));

                // Set the coupon id of the transaction.
                $txn->coupon_id = $cpn->ID;
                // Create a new subscription.
                if ($product->is_one_time_payment() || !$product->is_payment_required($cpn->post_title)) {
                    $signup_type = 'non-recurring';
                } else {
                    $signup_type = 'recurring';

                    $sub          = new MeprSubscription();
                    $sub->user_id = $usr->ID;
                    $sub->gateway = $pm->id;
                    $sub->load_product_vars($product, $cpn->post_title, true);
                    $sub->maybe_prorate(); // Sub to sub.
                    $sub->store();

                    $txn->subscription_id = $sub->id;
                }
            } else {
                $_POST['errors'] = [__('Invalid payment method', 'memberpress')];
                return;
            }
        }

        $txn->store();

        if (empty($txn->id)) {
            // Don't want any loose ends here if the $txn didn't save for some reason.
            if ($signup_type === 'recurring' && ($sub instanceof MeprSubscription)) {
                $sub->destroy();
            }
            $_POST['errors'] = [__('Sorry, we were unable to create a transaction.', 'memberpress')];
            return;
        }

        try {
            if (! $is_existing_user) {
                if ($mepr_options->disable_checkout_password_fields === true) {
                    $usr->send_password_notification('new');
                }
            }

            // DEPRECATED: These 2 actions here for backwards compatibility ... use mepr-signup instead.
            MeprHooks::do_action('mepr-track-signup', $txn->amount, $usr, $product->ID, $txn->id);
            MeprHooks::do_action('mepr-process-signup', $txn->amount, $usr, $product->ID, $txn->id);

            if (('free' !== $signup_type) && isset($pm) && ($pm instanceof MeprBaseRealGateway)) {
                $pm->process_signup_form($txn);
            }

            // Signup type can be 'free', 'non-recurring' or 'recurring'.
            MeprHooks::do_action("mepr-{$signup_type}-signup", $txn);
            MeprHooks::do_action('mepr-signup', $txn);

            // Pass order bump product IDs to the checkout second page.
            $obs  = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_filter(array_map('intval', $_POST['mepr_order_bumps'])) : [];
            $args = count($obs) ? ['obs' => $obs] : [];

            MeprUtils::wp_redirect(MeprHooks::apply_filters('mepr-signup-checkout-url', $txn->checkout_url($args), $txn));
        } catch (Exception $e) {
            $_POST['errors'] = $_REQUEST['errors'] = [$e->getMessage()];
        }
    }

    /**
     * Called from filter mepr-signup-checkout-url
     * Used to handle redirection when there are errors on SPC
     * Returns: redirection URL
     *
     * @param string $checkout_url The checkout URL.
     * @param object $txn          The transaction object.
     *
     * @return string The modified checkout URL.
     */
    public function handle_spc_checkout_url($checkout_url, $txn)
    {
        $mepr_options = MeprOptions::fetch();
        if (isset($_POST['mepr_payment_method'])) {
            $payment_method = $mepr_options->payment_method($_POST['mepr_payment_method']);
            if ($mepr_options->enable_spc && $payment_method->has_spc_form && !empty($_POST['errors'])) {
                $errors       = $_POST['errors'];
                $errors       = array_map('urlencode', $errors);
                $query_params = [
                    'errors'                    => $errors,
                    'mepr_transaction_id'       => $txn->id,
                    'mepr_process_signup_form'  => 0,
                    'mepr_process_payment_form' => 1,
                    'mepr_payment_method'       => sanitize_text_field($_POST['mepr_payment_method']),
                ];
                if (!empty($_POST['mepr_coupon_code'])) {
                    $query_params = array_merge(['mepr_coupon_code' => htmlentities(sanitize_text_field($_POST['mepr_coupon_code']))], $query_params);
                }
                $product      = $txn->product();
                $checkout_url = add_query_arg($query_params, $product->url());
            }
        }
        return $checkout_url;
    }

    /**
     * Called from mepr-signup action
     * Processes the payment for SPC
     *
     * @param object $txn The transaction object.
     *
     * @return void
     */
    public function process_spc_payment_form($txn)
    {
        if (wp_doing_ajax()) {
            return;
        }

        if (isset($_POST['smart-payment-button']) && $_POST['smart-payment-button']) {
            return;
        }

        $mepr_options = MeprOptions::fetch();
        if (isset($_POST['mepr_payment_method'])) {
            $payment_method = $mepr_options->payment_method($_POST['mepr_payment_method']);
            if ($mepr_options->enable_spc && $payment_method->has_spc_form || ($mepr_options->design_enable_checkout_template)) {
                $_POST = array_merge(
                    $_POST,
                    [
                        'mepr_process_payment_form' => 1,
                        'mepr_transaction_id'       => $txn->id,
                    ]
                );
                $this->process_payment_form();
            }
        }
    }

    /**
     * Display the payment page for a transaction.
     *
     * @return void
     */
    public function display_payment_page()
    {
        $mepr_options = MeprOptions::fetch();

        $txn_id = $_REQUEST['txn'];
        $txn    = new MeprTransaction($txn_id);

        if (!isset($txn->id) || $txn->id <= 0) {
            wp_die(__('ERROR: Invalid Transaction ID. Use your browser back button and try registering again.', 'memberpress'));
        }

        if ($txn->gateway === MeprTransaction::$free_gateway_str || $txn->amount <= 0.00) {
            MeprTransaction::create_free_transaction($txn);
        } else {
            $pm = $mepr_options->payment_method($txn->gateway);
            if ($pm instanceof MeprBaseRealGateway) {
                $pm->display_payment_page($txn);
            }
        }

        // Artificially set the payment method params so we can use them downstream
        // when display_payment_form is called in the 'the_content' action.
        $_REQUEST['payment_method_params'] = [
            'method'         => $txn->gateway,
            'amount'         => $txn->amount,
            'user'           => $txn->user(),
            'product_id'     => $txn->product_id,
            'transaction_id' => $txn->id,
        ];
    }

    /**
     * Display the payment form.
     * Called in the 'the_content' hook ... used to display a signup form
     *
     * @return void
     */
    public function display_payment_form()
    {
        $mepr_options = MeprOptions::fetch();

        if (isset($_REQUEST['payment_method_params'])) {
            extract($_REQUEST['payment_method_params'], EXTR_SKIP);

            if (isset($_REQUEST['errors']) && !empty($_REQUEST['errors'])) {
                $errors = $_REQUEST['errors'];
                MeprView::render('/shared/errors', get_defined_vars());
            }

            $pm = $mepr_options->payment_method($method);
            if ($pm && $pm instanceof MeprBaseRealGateway) {
                $pm->display_payment_form($amount, $user, $product_id, $transaction_id);
            }
        }
    }

    /**
     * Process the payment form.
     *
     * @return void
     */
    public function process_payment_form()
    {
        if (isset($_POST['mepr_process_payment_form']) && isset($_POST['mepr_transaction_id']) && is_numeric($_POST['mepr_transaction_id'])) {
            $txn = new MeprTransaction($_POST['mepr_transaction_id']);

            if ($txn->rec != false) {
                $mepr_options = MeprOptions::fetch();
                $pm           = $mepr_options->payment_method($txn->gateway);
                if ($pm instanceof MeprBaseRealGateway) {
                    $errors = MeprHooks::apply_filters('mepr_validate_payment_form', $pm->validate_payment_form([]), $txn, $pm);

                    if (empty($errors)) {
                        // The process_payment_form either returns true
                        // for success or an array of $errors on failure.
                        try {
                            $pm->process_payment_form($txn);
                        } catch (Exception $e) {
                            MeprHooks::do_action('mepr_payment_failure', $txn);
                            $errors = [$e->getMessage()];
                        }
                    }

                    if (empty($errors)) {
                        // Reload the txn now that it should have a proper trans_num set.
                        $txn             = new MeprTransaction($txn->id);
                        $product         = new MeprProduct($txn->product_id);
                        $sanitized_title = sanitize_title($product->post_title);
                        $query_params    = [
                            'membership'    => $sanitized_title,
                            'trans_num'     => $txn->trans_num,
                            'membership_id' => $product->ID,
                        ];
                        if ($txn->subscription_id > 0) {
                              $sub          = $txn->subscription();
                              $query_params = array_merge($query_params, ['subscr_id' => $sub->subscr_id]);
                        }
                        MeprUtils::wp_redirect($mepr_options->thankyou_page_url(build_query($query_params)));
                    } else {
                        // Artificially set the payment method params so we can use them downstream
                        // when display_payment_form is called in the 'the_content' action.
                        $_REQUEST['payment_method_params'] = [
                            'method'         => $pm->id,
                            'amount'         => $txn->amount,
                            'user'           => new MeprUser($txn->user_id),
                            'product_id'     => $txn->product_id,
                            'transaction_id' => $txn->id,
                        ];
                        $_REQUEST['mepr_payment_method']   = $pm->id;
                        $_POST['errors']                   = $errors;
                        return;
                    }
                }
            }
        }

        $_POST['errors'] = [__('Sorry, an unknown error occurred.', 'memberpress')];
    }

    /**
     * Exclude disconnected gateways from the list of payment methods.
     *
     * @param array            $pm_ids     The payment method IDs.
     * @param string           $field_name The field name.
     * @param MeprProduct|null $product    The product being purchased.
     *
     * @return array The filtered payment method IDs.
     */
    public function exclude_disconnected_gateways($pm_ids, $field_name, $product = null)
    {
        $mepr_options     = MeprOptions::fetch();
        $connected_pm_ids = [];
        $product = $product instanceof MeprProduct ? $product : null;

        foreach ($pm_ids as $pm_id) {
            $obj = $mepr_options->payment_method($pm_id);

            if (MeprUtils::is_gateway_connected($obj, $product)) {
                $connected_pm_ids[] = $pm_id;
            }
        }

        return $connected_pm_ids;
    }

    /**
     * Check if the request has valid thank you parameters.
     *
     * @param array $req The request parameters.
     *
     * @return boolean True if valid, false otherwise.
     */
    private function request_has_valid_thank_you_params($req)
    {
        // If these aren't set as parameters then this isn't actually a real checkout.
        if (
            !isset($req['membership']) ||
            !isset($req['membership_id']) ||
            (!isset($req['trans_num']) && !isset($req['transaction_id']))
        ) {
            return false;
        }

        return true;
    }

    /**
     * Check if the request has a valid membership ID.
     *
     * @param array $req The request parameters.
     *
     * @return boolean True if valid, false otherwise.
     */
    private function request_has_valid_thank_you_membership_id($req)
    {
        // If this is an invalid membership then let's bail, yo.
        $membership = new MeprProduct($req['membership_id']);
        if (!$membership || empty($membership->ID)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the request has a valid transaction number.
     *
     * @param array $req The request parameters.
     *
     * @return boolean True if valid, false otherwise.
     */
    private function request_has_valid_thank_you_trans_num($req)
    {
        // If this is an invalid transaction then let's bail, yo.
        $transaction = $this->get_transaction_from_request($req);

        return !empty($transaction);
    }

    /**
     * Check if the request has a valid membership.
     *
     * @param array $atts The shortcode attributes.
     * @param array $req  The request parameters.
     *
     * @return boolean True if valid, false otherwise.
     */
    private function request_has_valid_thank_you_membership($atts, $req)
    {
        $membership  = new MeprProduct($req['membership_id']);
        $transaction = $this->get_transaction_from_request($req);

        // If this transaction doesn't match the membership then something fishy is going on here bro.
        if (empty($transaction) || $transaction->product_id != $membership->ID) {
            return false;
        }

        // If the shortcode is tied to a specific membership then only show
        // it when this is the thank you page for the specified membership.
        if (
            !is_null($atts['membership']) && isset($req['membership_id']) &&
            $req['membership_id'] != $atts['membership']
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get the transaction from the request.
     *
     * @param array $req The request parameters.
     *
     * @return object|false The transaction object or false if not found.
     */
    private function get_transaction_from_request($req)
    {
        if (isset($req['trans_num'])) {
            return MeprTransaction::get_one_by_trans_num($req['trans_num']);
        } elseif (isset($req['transaction_id'])) {
            return MeprTransaction::get_one((int) $req['transaction_id']);
        }

        return false;
    }

    /**
     * Prepare transaction variables
     *
     * Instantiates and returns a new transaction and subscription (if recurring), based on the given parameters.
     *
     * @param  MeprProduct      $product    The product being purchased.
     * @param  integer          $order_id   The order ID.
     * @param  integer          $user_id    The user ID.
     * @param  string           $gateway_id The payment gateway ID.
     * @param  MeprCoupon|false $cpn        Optional coupon code.
     * @param  boolean          $store      Whether to store the transaction & subscription, default true.
     * @return array{0: MeprTransaction, 1: MeprSubscription|null}
     * @throws Exception If unable to store a transaction or subscription.
     */
    public static function prepare_transaction(MeprProduct $product, $order_id, $user_id, $gateway_id, $cpn = false, $store = true)
    {
        $txn             = new MeprTransaction();
        $txn->order_id   = $order_id;
        $txn->user_id    = $user_id;
        $txn->gateway    = $gateway_id;
        $txn->product_id = $product->ID;
        $txn->coupon_id  = 0;

        // Set default price, adjust it later if coupon applies.
        $price = $product->adjusted_price();

        // Adjust membership price from the coupon code.
        if ($cpn instanceof MeprCoupon && MeprCoupon::is_valid_coupon_code($cpn->post_title, $product->ID)) {
            $price          = $product->adjusted_price($cpn->post_title);
            $txn->coupon_id = $cpn->ID;
        }

        $txn->set_subtotal(MeprUtils::maybe_round_to_minimum_amount($price));

        if ($product->is_one_time_payment()) {
            $sub = null;
        } else {
            $sub           = new MeprSubscription();
            $sub->order_id = $order_id;
            $sub->user_id  = $user_id;
            $sub->gateway  = $gateway_id;
            $sub->load_product_vars($product, $cpn instanceof MeprCoupon ? $cpn->post_title : null, true);
            $sub->maybe_prorate(); // Sub to sub.
        }

        if ($store) {
            if ($sub instanceof MeprSubscription) {
                $sub->store();

                if (empty($sub->id)) {
                    throw new Exception(__('Sorry, we were unable to create a subscription.', 'memberpress'));
                }

                $txn->subscription_id = $sub->id;
            }

            $txn->store();

            if (empty($txn->id)) {
                // Don't want any loose ends here if the transaction didn't save for some reason.
                if ($sub instanceof MeprSubscription) {
                    $sub->destroy();
                }

                throw new Exception(__('Sorry, we were unable to create a transaction.', 'memberpress'));
            }
        }

        return [$txn, $sub];
    }

    /**
     * Get order bump products
     *
     * Gets an array that is filled with a MeprProduct for each order bump in the $_POST data.
     *
     * @param  integer $product_id             The main product for the purchase, order bumps with this ID will be ignored.
     * @param  array   $order_bump_product_ids The order bump product IDs.
     * @return MeprProduct[]
     * @throws Exception If a product was not found.
     */
    public static function get_order_bump_products($product_id, array $order_bump_product_ids)
    {
        $order_bump_products  = [];
        $base_product         = new MeprProduct($product_id);
        $required_order_bumps = $base_product->get_required_order_bumps();

        // Track if all required order bumps are found.
        if (!empty($required_order_bumps) && !empty($order_bump_product_ids)) {
            $missing_required_order_bumps = array_diff($required_order_bumps, $order_bump_product_ids);
            if (!empty($missing_required_order_bumps)) {
                throw new Exception(__('One of the required products is missing.', 'memberpress'));
            }
        }

        foreach ($order_bump_product_ids as $order_bump_product_id) {
            $product = new MeprProduct($order_bump_product_id);

            if (empty($product->ID)) {
                throw new Exception(__('Product not found', 'memberpress'));
            }

            if ($product_id == $product->ID) {
                continue;
            }

            if (!$product->can_you_buy_me()) {
                throw new Exception(sprintf(
                    // Translators: %s: product name.
                    __("You don't have access to purchase %s.", 'memberpress'),
                    $product->post_title
                ));
            }

            $group = $product->group();

            if ($group instanceof MeprGroup && $group->is_upgrade_path) {
                throw new Exception(sprintf(
                    // Translators: %s: product name.
                    __('The product %s cannot be purchased at this time.', 'memberpress'),
                    $product->post_title
                ));
            }

            $order_bump_products[] = $product;
        }

        return $order_bump_products;
    }

    /**
     * Get dynamic updates when the checkout state changes
     *
     * @return void
     */
    public function get_checkout_state()
    {
        $mepr_options = MeprOptions::fetch();
        $product_id   = isset($_POST['mepr_product_id']) ? (int) sanitize_text_field(wp_unslash($_POST['mepr_product_id'])) : 0;
        $coupon_code  = isset($_POST['mepr_coupon_code']) ? sanitize_text_field(wp_unslash($_POST['mepr_coupon_code'])) : '';

        if (empty($product_id)) {
            wp_send_json_error();
        }

        $prd = new MeprProduct($product_id);

        if (empty($prd->ID)) {
            wp_send_json_error();
        }

        if (!empty($coupon_code) && !check_ajax_referer('mepr_coupons', 'mepr_coupon_nonce', false)) {
            wp_send_json_error();
        }

        $is_gift             = isset($_POST['mpgft_gift_checkbox']) ? sanitize_text_field(wp_unslash($_POST['mpgft_gift_checkbox'])) : 'false';
        $payment_required    = $prd->is_payment_required($coupon_code);
        $order_bump_products = [];

        if ($is_gift == 'true') {
            $prd->allow_renewal = false;
        }

        try {
            $order_bump_product_ids = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_map('intval', $_POST['mepr_order_bumps']) : [];
            $order_bump_products    = self::get_order_bump_products($prd->ID, $order_bump_product_ids);

            foreach ($order_bump_products as $product) {
                if ($product->is_payment_required()) {
                    $payment_required = true;
                }
            }
        } catch (Exception $e) {
            // Ignore exception.
        }

        $payment_required = MeprHooks::apply_filters('mepr_signup_payment_required', $payment_required, $prd);

        ob_start();
        MeprProductsHelper::display_invoice($prd, $coupon_code);
        $price_string = ob_get_clean();

        // By default hide required order bumps pricing terms on SPC and ReadyLaunch™ Templates.
        $disable_ob_required_terms = $mepr_options->enable_spc || $mepr_options->design_enable_checkout_template;
        if (! $mepr_options->enable_spc_invoice) {
            $disable_ob_required_terms = false;
        }

        if (! MeprHooks::apply_filters('mepr_signup_disable_order_bumps_required_terms', $disable_ob_required_terms, $prd)) {
            $required_order_bumps = $prd->get_required_order_bumps();
            if (! empty($required_order_bumps)) {
                ob_start();
                foreach ($required_order_bumps as $required_order_bump_id) {
                    if (! MeprHooks::apply_filters('mepr_signup_skip_order_bump_required_terms', false, $required_order_bump_id, $prd)) {
                        echo'<br />';
                        MeprProductsHelper::display_invoice(new MeprProduct($required_order_bump_id), false, true);
                    }
                }

                $required_order_bumps_terms = ob_get_clean();
                if (! empty($required_order_bumps_terms)) {
                    $price_string .= wp_kses_post($required_order_bumps_terms);
                }
            }
        }

        ob_start();
        MeprProductsHelper::display_spc_invoice($prd, $coupon_code, $order_bump_products);
        $invoice_html = ob_get_clean();

        $data = [
            'payment_required' => $payment_required,
            'price_string'     => $price_string,
            'invoice_html'     => $invoice_html,
            'is_gift'          => MeprHooks::apply_filters('mepr_signup_product_is_gift', false, $prd),
        ];

        if ($payment_required) {
            $payment_method_ids = isset($_POST['mepr_payment_methods']) && is_array($_POST['mepr_payment_methods']) ? array_map('sanitize_text_field', array_map('wp_unslash', $_POST['mepr_payment_methods'])) : [];

            if (count($payment_method_ids)) {
                $payment_methods     = $mepr_options->payment_methods(false);
                $coupon              = MeprCoupon::get_one_from_code($coupon_code);
                $coupon_code         = $coupon instanceof MeprCoupon ? $coupon->post_title : '';
                $elements_options    = [];
                $square_verification = [];

                foreach ($payment_methods as $pm) {
                    if (!in_array($pm->id, $payment_method_ids, true)) {
                        continue;
                    }

                    if ($pm instanceof MeprStripeGateway && $pm->settings->stripe_checkout_enabled != 'on') {
                        try {
                             list($txn, $sub) = MeprCheckoutCtrl::prepare_transaction(
                                 $prd,
                                 0,
                                 get_current_user_id(),
                                 $pm->id,
                                 $coupon,
                                 false
                             );

                             $elements_options[$pm->id] = $pm->get_elements_options(
                                 $prd,
                                 $txn,
                                 $sub,
                                 $coupon_code,
                                 $order_bump_products
                             );
                        } catch (Exception $e) {
                            // Ignore exception.
                        }
                    } elseif ($pm instanceof MeprSquareGateway) {
                        try {
                            list($txn, $sub) = MeprCheckoutCtrl::prepare_transaction(
                                $prd,
                                0,
                                get_current_user_id(),
                                $pm->id,
                                $coupon,
                                false
                            );

                            $square_verification[$pm->id] = $pm->get_verification_details(
                                $prd,
                                $txn,
                                $sub,
                                $coupon_code,
                                $order_bump_products
                            );
                        } catch (Exception $e) {
                            // Ignore exception.
                        }
                    }
                }

                if (count($elements_options)) {
                    $data['elements_options'] = $elements_options;
                }

                if (count($square_verification)) {
                    $data['square_verification'] = $square_verification;
                }
            }
        }

        wp_send_json_success($data);
    }

    /**
     * Signup form processing for asynchronous gateways (Single Page Checkout).
     */
    public function process_signup_form_ajax()
    {
        MeprHooks::do_action('mepr_process_signup_form_ajax');

        try {
            $mepr_options      = MeprOptions::fetch();
            $payment_method_id = isset($_POST['mepr_payment_method']) ? sanitize_text_field(wp_unslash($_POST['mepr_payment_method'])) : '';
            $pm                = $mepr_options->payment_method($payment_method_id);

            if (!$pm instanceof MeprBaseRealAjaxGateway || !$pm->validate_ajax_gateway()) {
                wp_send_json_error(__('Invalid payment gateway', 'memberpress'));
            }

            // Validate the form post.
            $mepr_current_url = isset($_POST['mepr_current_url']) && is_string($_POST['mepr_current_url']) ? sanitize_text_field(wp_unslash($_POST['mepr_current_url'])) : '';
            $errors           = MeprHooks::apply_filters('mepr-validate-signup', MeprUser::validate_signup($_POST, [], $mepr_current_url));

            if (!empty($errors)) {
                wp_send_json_error(['errors' => $errors]);
            }

            $product_id = isset($_POST['mepr_product_id']) ? (int) $_POST['mepr_product_id'] : 0;
            $prd        = new MeprProduct($product_id);

            if (empty($prd->ID)) {
                wp_send_json_error(__('Sorry, we were unable to find the product.', 'memberpress'));
            }

            // Check if the user is logged in already.
            $is_existing_user = MeprUtils::is_user_logged_in();

            if ($is_existing_user) {
                $usr = MeprUtils::get_currentuserinfo();
            } else {
                // If new user we've got to create them and sign them in.
                $usr             = new MeprUser();
                $usr->user_login = ($mepr_options->username_is_email) ? sanitize_email($_POST['user_email']) : sanitize_user($_POST['user_login']);
                $usr->user_email = sanitize_email($_POST['user_email']);
                $usr->first_name = !empty($_POST['user_first_name']) ? MeprUtils::sanitize_name_field(wp_unslash($_POST['user_first_name'])) : '';
                $usr->last_name  = !empty($_POST['user_last_name']) ? MeprUtils::sanitize_name_field(wp_unslash($_POST['user_last_name'])) : '';

                $password = ($mepr_options->disable_checkout_password_fields === true) ? wp_generate_password() : $_POST['mepr_user_password'];
                // Have to use rec here because we unset user_pass on __construct.
                $usr->set_password($password);
                try {
                    $usr->store();

                    // We need to refresh the user object. In the case where emails are used as
                    // usernames, the email & username could differ after the user is saved.
                    $usr = new MeprUser($usr->ID);

                    // Log the new user in.
                    if (MeprHooks::apply_filters('mepr-auto-login', true, $_POST['mepr_product_id'], $usr)) {
                        wp_signon(
                            [
                                'user_login'    => $usr->user_login,
                                'user_password' => $password,
                            ],
                            MeprUtils::is_ssl() // May help with the users getting logged out when going between http and https.
                        );
                    }

                    MeprEvent::record('login', $usr); // Record the first login here.

                    if ($mepr_options->disable_checkout_password_fields === true) {
                        $usr->send_password_notification('new');
                    }
                } catch (MeprCreateException $e) {
                    wp_send_json_error(__('The user was unable to be saved.', 'memberpress'));
                }
            }

            // If we're showing the fields on logged in purchases, let's save them here.
            if (!$is_existing_user || ($is_existing_user && $mepr_options->show_fields_logged_in_purchases)) {
                MeprUsersCtrl::save_extra_profile_fields($usr->ID, true, $prd, true);
                $usr = new MeprUser($usr->ID); // Re-load the user object with the metadata now (helps with first name last name missing from hooks below).
            }

            // Needed for autoresponders (SPC + Stripe + Free Trial issue).
            MeprHooks::do_action('mepr-signup-user-loaded', $usr);

            $coupon_code = isset($_POST['mepr_coupon_code']) ? sanitize_text_field(wp_unslash($_POST['mepr_coupon_code'])) : '';
            $cpn         = MeprCoupon::get_one_from_code($coupon_code);

            try {
                list($txn, $sub) = MeprCheckoutCtrl::prepare_transaction(
                    $prd,
                    0,
                    $usr->ID,
                    $pm->id,
                    $cpn
                );

                MeprHooks::do_action('mepr-process-signup', $txn->amount, $usr, $prd->ID, $txn->id);
                MeprHooks::do_action('mepr-signup', $txn);

                $pm->process_payment_ajax(
                    $prd,
                    $usr,
                    $txn,
                    $sub instanceof MeprSubscription ? $sub : null,
                    $cpn instanceof MeprCoupon ? $cpn : null
                );
            } catch (Exception $e) {
                wp_send_json_error($e->getMessage());
            }
        } catch (Throwable $t) {
            $this->handle_critical_error($t);
        }
    }

    /**
     * Payment form processing for asynchronous gateways (Two-Page Checkout).
     */
    public function process_payment_form_ajax()
    {
        MeprHooks::do_action('mepr_process_payment_form_ajax');

        try {
            $txn = new MeprTransaction((int) $_POST['mepr_transaction_id'] ?? 0);

            if (!$txn->id) {
                wp_send_json_error(__('Transaction not found', 'memberpress'));
            }

            $pm = $txn->payment_method();

            if (!$pm instanceof MeprBaseRealAjaxGateway || !$pm->validate_ajax_gateway()) {
                wp_send_json_error(__('Invalid payment gateway', 'memberpress'));
            }

            $prd = $txn->product();

            if (!$prd->ID) {
                wp_send_json_error(__('Product not found', 'memberpress'));
            }

            $usr = $txn->user();

            if (!$usr->ID) {
                wp_send_json_error(__('User not found', 'memberpress'));
            }

            $sub = !$prd->is_one_time_payment() ? $txn->subscription() : null;
            $cpn = $txn->coupon();

            $pm->process_payment_ajax(
                $prd,
                $usr,
                $txn,
                $sub instanceof MeprSubscription ? $sub : null,
                $cpn instanceof MeprCoupon ? $cpn : null
            );
        } catch (Throwable $t) {
            $this->handle_critical_error($t);
        }
    }

    /**
     * Handle a critical error during checkout processing.
     *
     * @param Throwable $t The critical error that was triggered.
     */
    protected function handle_critical_error(Throwable $t)
    {
        $this->send_checkout_error_debug_email(
            $t->__toString(),
            isset($_POST['mepr_transaction_id']) && is_numeric($_POST['mepr_transaction_id']) ? (int) $_POST['mepr_transaction_id'] : null,
            isset($_POST['user_email']) && is_string($_POST['user_email']) ? sanitize_text_field(wp_unslash($_POST['user_email'])) : null
        );

        error_log(sprintf('PHP Fatal error: Uncaught %s', $t->__toString()));

        wp_send_json_error(__('An error occurred, please DO NOT submit the form again as you may be double charged. Please contact us for further assistance instead.', 'memberpress'));
    }

    /**
     * Handle the Ajax request to debug a checkout error
     */
    public function debug_checkout_error()
    {
        if (!MeprUtils::is_post_request() || !isset($_POST['data']) || !is_string($_POST['data'])) {
            wp_send_json_error();
        }

        $data = json_decode(wp_unslash($_POST['data']), true);

        if (!is_array($data)) {
            wp_send_json_error();
        }

        $allowed_keys = [
            'text_status'   => 'textStatus',
            'error_thrown'  => 'errorThrown',
            'status'        => 'jqXHR.status (200 expected)',
            'status_text'   => 'jqXHR.statusText (OK expected)',
            'response_text' => 'jqXHR.responseText (JSON object expected)',
        ];

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
     * Sends an email to the admin email addresses alerting them of the given checkout error.
     *
     * @param string       $content        The email content.
     * @param integer|null $transaction_id The transaction ID.
     * @param string|null  $customer_email The customer's email address.
     */
    protected function send_checkout_error_debug_email($content, $transaction_id = null, $customer_email = null)
    {
        if (MeprHooks::apply_filters('mepr_disable_checkout_error_debug_email', false)) {
            return;
        }

        $message = 'An error occurred during the MemberPress checkout which resulted in an error message being displayed to the customer. The transaction may not have fully completed.' . "\n\n";
        $message .= 'The error may have happened due to a plugin conflict or custom code, please carefully check the details below to identify the cause, or you can send this email to support@memberpress.com for help.' . "\n\n";

        if ($transaction_id) {
            $message .= sprintf("MemberPress transaction ID: %s\n", $transaction_id);

            if (!$customer_email) {
                $transaction = new MeprTransaction($transaction_id);
                $user        = $transaction->user();

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
