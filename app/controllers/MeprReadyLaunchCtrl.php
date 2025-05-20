<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprReadyLaunchCtrl extends MeprBaseCtrl
{
    /**
     * Loads the hooks.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('mepr_display_options_tabs', 'MeprReadyLaunchCtrl::display_option_tab', 100);
        add_action('mepr_display_options', 'MeprReadyLaunchCtrl::display_option_fields');
        add_action('admin_enqueue_scripts', 'MeprReadyLaunchCtrl::enqueue_admin_scripts', 20);
        add_action('wp_enqueue_scripts', 'MeprReadyLaunchCtrl::enqueue_scripts', 999999);
        add_action('wp_head', 'MeprReadyLaunchCtrl::theme_style');
        add_action('admin_head', 'MeprReadyLaunchCtrl::theme_style');

        add_filter('mepr-validate-options', 'MeprReadyLaunchCtrl::validate_settings_fields');
        add_filter('template_include', [$this, 'override_page_templates'], 999999); // High priority so we have the last say here.
        add_filter('the_content', [$this, 'thankyou_page_content'], 99);
        add_filter('mepr_render_address_fields', [$this, 'placeholders_to_address_fields']); // High priority so we have the last say here.
        add_filter('show_admin_bar', [$this, 'remove_admin_bar']);
        add_filter('mepr-membership-cant-purchase-string', [$this, 'cant_purchase_message']);
        add_filter('mepr-validate-account-ajax', [$this, 'validate_account_fields'], 10, 3);

        add_action('wp_ajax_prepare_editable_field', 'MeprReadyLaunchCtrl::account_profile_editable_fields');
        add_action('wp_ajax_load_more_subscriptions', [$this, 'load_more_subscriptions']);
        add_action('wp_ajax_load_more_payments', [$this, 'load_more_payments']);


        // Shortcodes!
        MeprHooks::add_shortcode('mepr-pro-login-form', [$this, 'login_form_shortcode']);
        MeprHooks::add_shortcode('mepr-pro-pricing-table', [$this, 'pricing_table_shortcode']);
        MeprHooks::add_shortcode('mepr-pro-account-tabs', [$this, 'account_shortcode']);
        MeprHooks::add_shortcode('mepr-pro-checkout', [$this, 'checkout_shortcode']);
    }

    /**
     * Renders Pro login form.
     *
     * @param array $atts Shortcode attributes.
     */
    public function login_form_shortcode($atts = [])
    {
        $show_welcome_image = 0;
        $welcome_image      = 0;

        // Show welcome image.
        if (isset($atts['show_welcome_image'])) {
            $show_welcome_image = filter_var($atts['show_welcome_image'], FILTER_VALIDATE_BOOLEAN);
        }

        // Get welcome image.
        if (isset($atts['welcome_image']) && ! empty($atts['welcome_image'])) {
            $welcome_image = $atts['welcome_image'];
        }

        add_filter('mepr_pro_templates_has_login_block', '__return_true');
        $content = do_shortcode('[mepr-login-form welcome_image="' . $welcome_image . '" show_welcome_image="' . $show_welcome_image . '" admin_view="' . $atts['admin_view'] . '"]');

        return $content;
    }

    /**
     * Render pricing table shortcode
     *
     * @param  array $atts Shortcode args.
     * @return string
     */
    public function pricing_table_shortcode($atts = [])
    {
        wp_enqueue_script('mepr-pro-pricing', MEPR_JS_URL . '/readylaunch/pricing.js', ['jquery'], MEPR_VERSION, true);


        if (! isset($atts['group_id']) || $atts['group_id'] <= 0) {
            return esc_html__('Please select group', 'memberpress');
        }

        $group = new MeprGroup($atts['group_id']);

        if (! $group->ID) {
            return esc_html__('No group found', 'memberpress');
        }

        add_filter('mepr_pro_templates_has_pricing_block', '__return_true');
        $content = do_shortcode('[mepr-group-price-boxes group_id="' . $group->ID . '" show_title="' . $atts['show_title'] . '" button_highlight_color="' . $atts['button_highlight_color'] . '"] ');

        return $content;
    }

    /**
     * Render pricing table shortcode
     *
     * @param  array $atts Shortcode args.
     * @return string
     */
    public function account_shortcode($atts = [])
    {
        wp_enqueue_script('alpinejs', MEPR_JS_URL . '/vendor/alpine.min.js', [], MEPR_VERSION, true);
        wp_enqueue_script('mepr-accountjs', MEPR_JS_URL . '/readylaunch/account.js', ['jquery'], MEPR_VERSION, true);
        wp_localize_script(
            'mepr-accountjs',
            'MeprAccount',
            [
                'ajax_url'    => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('mepr_account_update'),
                'current_url' => MeprUtils::get_current_url_without_params(),
            ]
        );

        // Show welcome image.
        if (isset($atts['show_welcome_image'])) {
            $show_welcome_image = filter_var($atts['show_welcome_image'], FILTER_VALIDATE_BOOLEAN);
        }

        // Get welcome image.
        $welcome_image = '';
        if (isset($atts['welcome_image']) && ! empty($atts['welcome_image'])) {
            $welcome_image = $atts['welcome_image'];
        }

        add_filter('mepr_pro_templates_has_account_block', '__return_true');
        $content = do_shortcode('[mepr-account-form  welcome_image="' . $welcome_image . '" show_welcome_image="' . $show_welcome_image . '"]');

        if (MeprUtils::is_user_logged_in()) {
            $content = "<div class='mp_wrapper alignwide wp-block wp-shortcode'>" . $content . '</div>';
        }

        return $content;
    }

    /**
     * Checkout Shortcode
     *
     * @param  array $atts Array of attributes.
     * @return string
     */
    public function checkout_shortcode($atts = [])
    {
        wp_enqueue_script('mepr-signupjs', MEPR_JS_URL . '/readylaunch/signup.js', ['jquery'], MEPR_VERSION, true);

        wp_localize_script(
            'mepr-signupjs',
            'MeprProTemplateSignup',
            [
                'spc_enabled' => true,
            ]
        );

        if (! isset($atts['membership_id']) || $atts['membership_id'] <= 0) {
            return esc_html__('Please select membership', 'memberpress');
        }

        $prd = new MeprProduct($atts['membership_id']);

        if (! $prd->ID) {
            return esc_html__('No membership found', 'memberpress');
        }

        add_filter('mepr_pro_templates_has_checkout_block', '__return_true');
        $content = do_shortcode('[mepr-membership-registration-form id="' . $prd->ID . '"]');

        return $content;
    }

    /**
     * Override default template with the courses page template
     *
     * @param  string $template Current template.
     * @return string $template modified template
     */
    public function override_page_templates($template)
    {
        global $post;
        $mepr_options        = MeprOptions::fetch();
        $logout_url          = MeprUtils::logout_url();
        $account_url         = $mepr_options->account_page_url();
        $delim               = MeprAppCtrl::get_param_delimiter_char($account_url);
        $change_password_url = MeprHooks::apply_filters('mepr-rl-change-password-url', $account_url . $delim . 'action=newpassword');
        $logo                = esc_url(wp_get_attachment_url($mepr_options->design_logo_img));
        $user                = MeprUtils::get_currentuserinfo();
        $wrapper_classes     = '';

        if (self::template_enabled('pricing')) {
            $user              = MeprUtils::get_currentuserinfo();
            $has_welcome_image = $mepr_options->design_login_welcome_img;
            $group_ctrl        = MeprCtrlFactory::fetch('groups');

            $template = \MeprView::file('/readylaunch/layout/app');
            include $template;
            exit;
        }

        if (self::template_enabled('login')) {
            if ($post->ID == $mepr_options->login_page_id) {
                $template = \MeprView::file('/readylaunch/layout/guest');
                include $template;
                exit;
            }
        }

        if (self::template_enabled('account')) {
            $is_account_page = true;
            $template        = MeprView::file('/readylaunch/layout/app');
            include $template;
            exit;
        }

        // Checkout Page Template.
        if (self::template_enabled('checkout')) {
            $template = MeprView::file('/readylaunch/layout/app');
            include $template;
            exit;
        }

        if (self::template_enabled('thankyou')) {
            $template = MeprView::file('/readylaunch/layout/app');
            include $template;
            exit;
        }

        return $template;
    }

    /**
     * Gets the page content for thankyou page
     *
     * @param  string $content The content.
     * @return string
     */
    public function thankyou_page_content($content)
    {
        if (self::template_enabled('thankyou')) {
            $txn = isset($_GET['trans_num']) ? MeprTransaction::get_one_by_trans_num(sanitize_text_field(wp_unslash($_GET['trans_num']))) : null;
            $txn = null === $txn && isset($_GET['transaction_id']) ? MeprTransaction::get_one((int) $_GET['transaction_id']) : $txn;
            $txn = !empty($txn->id) ? new MeprTransaction($txn->id) : null;

            if ($txn instanceof MeprTransaction && !empty($txn->id)) {
                $mepr_options      = MeprOptions::fetch();
                $hide_invoice      = $mepr_options->design_thankyou_hide_invoice;
                $invoice_message   = do_shortcode($mepr_options->design_thankyou_invoice_message);
                $has_welcome_image = $mepr_options->design_show_thankyou_welcome_image;
                $welcome_image     = esc_url(wp_get_attachment_url($mepr_options->design_thankyou_welcome_img));
                $order             = $txn->order();

                if ($order instanceof MeprOrder) {
                    $order_bump_transactions = MeprTransaction::get_all_by_order_id_and_gateway($order->id, $txn->gateway, $txn->id);
                    $transactions            = array_merge([$txn], $order_bump_transactions);
                    $processed_sub_ids       = [];
                    $order_bumps             = [];
                    $amount                  = 0.00;

                    foreach ($transactions as $index => $transaction) {
                        if ($transaction->is_one_time_payment()) {
                            $amount += (float) $transaction->total;

                            if ($index > 0) {
                                  $order_bumps[] = [$transaction->product(), $transaction, null];
                            }
                        } else {
                            $subscription = $transaction->subscription();

                            if ($subscription instanceof MeprSubscription) {
                                // Subs can have both a payment txn and confirmation txn, make sure we don't process a sub twice.
                                if (in_array((int) $subscription->id, $processed_sub_ids, true)) {
                                        continue;
                                }

                                $processed_sub_ids[] = (int) $subscription->id;

                                if (($subscription->trial && $subscription->trial_days > 0 && $subscription->txn_count < 1) || $transaction->txn_type == MeprTransaction::$subscription_confirmation_str) {
                                    MeprTransactionsHelper::set_invoice_txn_vars_from_sub($transaction, $subscription);
                                }

                                $amount += (float) $transaction->total;

                                if ($index > 0) {
                                    $order_bumps[] = [$transaction->product(), $transaction, $subscription];
                                }
                            }
                        }
                    }

                    $amount       = MeprAppHelper::format_currency($amount);
                    $trans_num    = $order->trans_num;
                    $invoice_html = MeprTransactionsHelper::get_invoice_order_bumps($txn, '', $order_bumps);
                } else {
                    $sub = $txn->subscription();

                    if ($sub instanceof MeprSubscription && (($sub->trial && $sub->trial_days > 0 && $sub->txn_count < 1) || $txn->txn_type == MeprTransaction::$subscription_confirmation_str)) {
                        MeprTransactionsHelper::set_invoice_txn_vars_from_sub($txn, $sub);
                    }

                    $amount = strtok(MeprAppHelper::format_price_string($txn, $txn->amount), ' ');

                    $trans_num    = $txn->trans_num;
                    $invoice_html = MeprTransactionsHelper::get_invoice($txn);
                }

                $content = MeprView::get_string('/readylaunch/thankyou', get_defined_vars());
            } else {
                $content = '<p>' . esc_html__('Transaction not found', 'memberpress') . '</p>';
            }
        }

        return $content;
    }

    /**
     * Enqueues scripts for admin view
     *
     * @param  string $hook Current page hook.
     * @return void
     */
    public static function enqueue_admin_scripts($hook)
    {
        if (strstr($hook, 'memberpress-options') !== false) {
            wp_enqueue_style('mp-readylaunch', MEPR_CSS_URL . '/admin-readylaunch.css', [], MEPR_VERSION);

            // Let's localize data for our drag and drop settings.
            $plupload_args = [
                'file_data_name'   => 'async-upload',
                'url'              => admin_url('admin-ajax.php'),
                'filters'          => [
                    'max_file_size' => wp_max_upload_size() . 'b',
                    'mime_types'    => [['extensions' => 'jpg,gif,png,jpeg']],
                ],
                'multi_selection'  => false, // Limit selection to just one.

                // Additional post data to send to our ajax hook.
                'multipart_params' => [
                    '_wpnonce' => wp_create_nonce('media-form'),
                    'action'   => 'upload-attachment',            // The ajax action name.
                ],
            ];
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('mp-readylaunch', MEPR_JS_URL . '/admin-readylaunch.js', ['mepr-uploader', 'plupload-all', 'wp-color-picker'], MEPR_VERSION);
            wp_localize_script('mp-readylaunch', 'MeproTemplates', $plupload_args);
        }
    }

    /**
     * Enqueues scripts for frontend view
     *
     * @return void
     */
    public static function enqueue_scripts()
    {
        global $post;

        if (MeprUser::is_account_page($post)) {
            wp_enqueue_script('mepr-popper', MEPR_JS_URL . '/vendor/popper.min.js', [], MEPR_VERSION, true);
        }

        $handles = ['dashicons', 'jquery-ui-timepicker-addon', 'jquery-magnific-popup'];

        // Login Scripts.
        if (self::template_enabled('login')) {
            static::remove_styles($handles);
            static::add_template_scripts('login');
        }

        // Account Scripts.
        if (self::template_enabled('account')) {
            static::remove_styles($handles);
            static::add_template_scripts('account');
        }

        // Pricing Scripts.
        if (self::template_enabled('pricing')) {
            if (
                isset($post) &&
                is_a($post, 'WP_Post') &&
                $post->post_type == MeprGroup::$cpt
            ) {
                static::remove_styles($handles);
                static::add_template_scripts('pricing');
            }
        }

        // Checkout Scripts.
        if (self::template_enabled('checkout') || self::template_enabled('thankyou')) {
            static::remove_styles($handles);
            static::add_template_scripts('checkout');
        }

        if (
            function_exists('bp_is_current_action') &&
            bp_is_current_action('mp-subscriptions')
        ) {
            wp_enqueue_style('mp-rl-buddyboss', MEPR_CSS_URL . '/readylaunch/compatibility.css', [], MEPR_VERSION);
            wp_enqueue_script('alpinejs', MEPR_JS_URL . '/vendor/alpine.min.js', [], MEPR_VERSION, true);
            wp_enqueue_script('mepr-accountjs', MEPR_JS_URL . '/readylaunch/account.js', ['jquery'], MEPR_VERSION, true);
        }
    }

    /**
     * Add Design tab toadmin memberpress page
     *
     * @return void
     */
    public static function display_option_tab()
    {
        ?>
    <a class="nav-tab" id="design" href="#"><?php _e('ReadyLaunch™', 'memberpress'); ?></a>
        <?php
    }

    /**
     * Displays design option fields
     *
     * FILTER_VALIDATE_BOOLEAN is used to return boolean, it works as described below
     *
     * filter_var(true, FILTER_VALIDATE_BOOLEAN); // true
     * filter_var('true', FILTER_VALIDATE_BOOLEAN); // true
     * filter_var(1, FILTER_VALIDATE_BOOLEAN); // true
     * filter_var('1', FILTER_VALIDATE_BOOLEAN); // true
     * filter_var('on', FILTER_VALIDATE_BOOLEAN); // true
     * filter_var('yes', FILTER_VALIDATE_BOOLEAN); // true
     */
    public static function display_option_fields()
    {
        $mepr_options          = MeprOptions::fetch();
        $groups                = MeprCptModel::all('MeprGroup');
        $pricing_columns_limit = false;

        foreach ($groups as $group) {
            $products_count = count($group->products());
            if ($products_count > 5) {
                $pricing_columns_limit = true;
                break;
            }
        }

        $data = [
            'global'   => [
                'logoId' => isset($mepr_options->design_logo_img) ? absint($mepr_options->design_logo_img) : '',
            ],
            'pricing'  => [
                'enableTemplate' => isset($mepr_options->design_enable_pricing_template) ? filter_var($mepr_options->design_enable_pricing_template, FILTER_VALIDATE_BOOLEAN) : '',
            ],
            'checkout' => [
                'enableTemplate' => isset($mepr_options->design_enable_checkout_template) ? filter_var($mepr_options->design_enable_checkout_template, FILTER_VALIDATE_BOOLEAN) : '',
                'showPriceTerms' => isset($mepr_options->design_show_checkout_price_terms) ? filter_var($mepr_options->design_show_checkout_price_terms, FILTER_VALIDATE_BOOLEAN) : '',
            ],
            'login'    => [
                'enableTemplate'   => isset($mepr_options->design_enable_login_template) ? filter_var($mepr_options->design_enable_login_template, FILTER_VALIDATE_BOOLEAN) : '',
                'showWelcomeImage' => isset($mepr_options->design_show_login_welcome_image) ? filter_var($mepr_options->design_show_login_welcome_image, FILTER_VALIDATE_BOOLEAN) : '',
                'welcomeImageId'   => isset($mepr_options->design_login_welcome_img) ? absint($mepr_options->design_login_welcome_img) : '',
            ],
            'thankyou' => [
                'enableTemplate'   => isset($mepr_options->design_enable_thankyou_template) ? filter_var($mepr_options->design_enable_thankyou_template, FILTER_VALIDATE_BOOLEAN) : '',
                'showWelcomeImage' => isset($mepr_options->design_show_thankyou_welcome_image) ? filter_var($mepr_options->design_show_thankyou_welcome_image, FILTER_VALIDATE_BOOLEAN) : '',
                'hideInvoice'      => isset($mepr_options->design_thankyou_hide_invoice) ? filter_var($mepr_options->design_thankyou_hide_invoice, FILTER_VALIDATE_BOOLEAN) : '',
                'welcomeImageId'   => isset($mepr_options->design_thankyou_welcome_img) ? absint($mepr_options->design_thankyou_welcome_img) : '',
            ],
            'account'  => [
                'enableTemplate'   => isset($mepr_options->design_enable_account_template) ? filter_var($mepr_options->design_enable_account_template, FILTER_VALIDATE_BOOLEAN) : '',
                'showWelcomeImage' => isset($mepr_options->design_show_account_welcome_image) ? filter_var($mepr_options->design_show_account_welcome_image, FILTER_VALIDATE_BOOLEAN) : '',
                'welcomeImageId'   => isset($mepr_options->design_account_welcome_img) ? absint($mepr_options->design_account_welcome_img) : '',
            ],
            'courses'  => [
                'enableTemplate'       => '',
                'showProtectedCourses' => '',
                'removeInstructorLink' => '',
                'logoId'               => '',
            ],
            'coaching' => [
                'enableTemplate' =>  isset($mepr_options->rl_enable_coaching_template) ? filter_var($mepr_options->rl_enable_coaching_template, FILTER_VALIDATE_BOOLEAN) : '',
            ],
        ];

        $data = MeprHooks::apply_filters('mepr-readylaunch-options-data', $data);

        MeprView::render('/admin/readylaunch/options', get_defined_vars());
    }

    /**
     * Validates all Design tab admin settings
     *
     * @param  array $errors The errors array.
     * @return array
     */
    public static function validate_settings_fields($errors)
    {
        $params       = $_POST;
        $mepr_options = MeprOptions::fetch();

        // When LoginTemplate is enabled and ShowWelcomeImage is checked but dude forgot to upload the image.
        if (
            isset($params[ $mepr_options->design_enable_login_template_str ]) &&
            isset($params[ $mepr_options->design_show_login_welcome_image_str ]) &&
            absint($params[ $mepr_options->design_login_welcome_img_str ]) == 0
        ) {
            $errors[] = esc_html__('Welcome Image should be uploaded if Show Welcome Image button is checked', 'memberpress');
        }

        return $errors;
    }

    /**
     * Dequeues and deregisters styles unrelated to pro mode templates.
     *
     * @param  array $allowed_handles CSS Handles that won't be deregistered and dequeued when using Pro Mode.
     * @return void
     */
    public static function remove_styles($allowed_handles = [])
    {
        global $wp_styles;
        $allowed_handles         = apply_filters('mepr_design_style_handles', $allowed_handles);
        $allowed_handle_prefixes = apply_filters('mepr_design_style_handle_prefixes', ['mepr-', 'mp-', 'mpca-', 'mpcs-', 'mpgft-', 'ca-course']);

        // Remove styles.
        foreach ($wp_styles->queue as $style) {
            $handle = $wp_styles->registered[ $style ]->handle;
            if (! in_array($handle, $allowed_handles)) {
                foreach ($allowed_handle_prefixes as $prefix) {
                    if (strpos($handle, $prefix) === 0) {
                        continue 2;
                    }
                }

                wp_deregister_style($handle);
                wp_dequeue_style($handle);
            }
        }
    }

    /**
     * Add scripts to full page template
     *
     * @param  string $page The template page.
     * @return void
     */
    public static function add_template_scripts($page = '')
    {
        global $post;

        wp_enqueue_style('mp-pro-theme', MEPR_CSS_URL . '/readylaunch/theme.css', null, MEPR_VERSION);

        if ('login' === $page) {
            wp_enqueue_style('mp-pro-login', MEPR_CSS_URL . '/readylaunch/login.css', null, MEPR_VERSION);
        }

        if ('account' === $page) {
            wp_enqueue_style('mp-pro-login', MEPR_CSS_URL . '/readylaunch/login.css', null, MEPR_VERSION);
            wp_register_style('mp-pro-fonts', MEPR_CSS_URL . '/readylaunch/fonts.css', null, MEPR_VERSION);
            wp_enqueue_style('mp-pro-account', MEPR_CSS_URL . '/readylaunch/account.css', ['mp-pro-fonts'], MEPR_VERSION);
            wp_enqueue_script('alpinejs', MEPR_JS_URL . '/vendor/alpine.min.js', [], MEPR_VERSION, true);
            wp_enqueue_script('mepr-accountjs', MEPR_JS_URL . '/readylaunch/account.js', ['jquery'], MEPR_VERSION, true);

            wp_localize_script(
                'mepr-accountjs',
                'MeprAccount',
                [
                    'ajax_url'    => admin_url('admin-ajax.php'),
                    'nonce'       => wp_create_nonce('mepr_account_update'),
                    'account_url' => MeprUtils::get_current_url_without_params(),
                ]
            );
        }

        if ('pricing' == $page) {
            wp_enqueue_style('mp-pro-pricing', MEPR_CSS_URL . '/readylaunch/pricing.css', null, MEPR_VERSION);
            wp_enqueue_script('mepr-pro-pricing', MEPR_JS_URL . '/readylaunch/pricing.js', ['jquery'], MEPR_VERSION, true);
            wp_enqueue_script('alpinejs', MEPR_JS_URL . '/vendor/alpine.min.js', [], MEPR_VERSION, true);
        }

        if ('checkout' == $page) {
            wp_enqueue_style('mp-pro-checkout', MEPR_CSS_URL . '/readylaunch/checkout.css', null, MEPR_VERSION);
            wp_enqueue_script('mepr-signupjs', MEPR_JS_URL . '/readylaunch/signup.js', ['jquery'], MEPR_VERSION, true);
            wp_enqueue_script('alpinejs', MEPR_JS_URL . '/vendor/alpine.min.js', [], MEPR_VERSION, true);

            wp_localize_script(
                'mepr-signupjs',
                'MeprProTemplateSignup',
                [
                    'spc_enabled' => true,
                ]
            );
        }

        if ('thankyou' == $page) {
            wp_enqueue_style('mp-pro-checkout', MEPR_CSS_URL . '/readylaunch/checkout.css', null, MEPR_VERSION);
        }
    }

    /**
     * Adds the view path for the slug.
     *
     * @param  array  $paths         The paths.
     * @param  string $slug          The slug.
     * @param  array  $allowed_slugs The allowed slugs.
     * @return array
     */
    public function add_view_path_for_slug($paths, $slug, $allowed_slugs = [])
    {
        if (in_array($slug, $allowed_slugs) || empty($allowed_slugs)) {
            array_splice($paths, 1, 0, MEPR_PATH . '/app/views/readylaunch');
        }
        return $paths;
    }

    /**
     * Only remove admin bar when on readylaunch pro templates
     *
     * @param  boolean $show The show.
     * @return boolean
     */
    public function remove_admin_bar($show)
    {
        if (
            self::template_enabled('pricing') ||
            self::template_enabled('login') ||
            self::template_enabled('account') ||
            self::template_enabled('checkout') ||
            self::template_enabled('thankyou')
        ) { // Full page templates.
            $show = false;
        }

        return $show;
    }

    /**
     * Change the cant purchase message template
     *
     * @param  string $str Purchase message.
     * @return string
     */
    public function cant_purchase_message($str)
    {
        $errors[] = $str;

        // Is ReadyLaunch™️ checkout template is enabled?
        if (self::template_enabled('checkout')) {
            $str = MeprView::get_string('/readylaunch/shared/errors', get_defined_vars());
        }

        return '<div class="flex-centered">' . $str . '</div>';
    }

    /**
     * Checks if we should override the page template
     *
     * @param  string $template Template name.
     * @return boolean
     */
    public static function template_enabled($template)
    {
        global $post;
        global $wp_query;

        $page_name = $template . '_page_id';
        $options   = MeprOptions::fetch();

        if ('pricing' === $template) {
            return isset($post) &&
            is_a($post, 'WP_Post') &&
            $post->post_type == MeprGroup::$cpt &&
            self::template_active('pricing');
        }

        if ('checkout' === $template) {
            return self::template_active('checkout') &&
            ( isset($post) && is_a($post, 'WP_Post') && is_singular(MeprProduct::$cpt) );
        }

        return isset($wp_query) &&
        isset($options->$page_name) &&
        is_page($options->$page_name) &&
        self::template_active($template);
    }

    /**
     * Determines if the given template is active.
     *
     * @param string $template The template name (e.g. 'pricing', 'checkout', etc.).
     *
     * @return boolean
     */
    public static function template_active($template)
    {
        $options        = MeprOptions::fetch();
        $attribute_name = 'design_enable_' . $template . '_template';

        return isset($options->$attribute_name) && filter_var($options->$attribute_name, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * AJAX: Get the editable field HTML
     *
     * @return void.
     */
    public function account_profile_editable_fields()
    {
        $user      = MeprUtils::get_currentuserinfo();
        $field_key = isset($_POST['field']) ? wp_unslash(sanitize_text_field($_POST['field'])) : '';

        $custom_fields = MeprUsersHelper::get_custom_fields();

        $key   = array_search($field_key, array_column($custom_fields, 'field_key'));
        $field = $custom_fields[ $key ];
        $value = $user ? get_user_meta($user->ID, $field->field_key, true) : '';

        ob_start();
        echo '<div><label class="mepr_modal_form__label">' . esc_html($field->field_name) . '</label></div>';
        echo MeprUsersHelper::render_custom_field($field, $value);
        $content = ob_get_clean();

        wp_send_json_success($content);
    }

    /**
     * Validates the account fields.
     *
     * @param  array  $errors    The errors.
     * @param  object $user      The user.
     * @param  string $field_key The field key.
     * @return array
     */
    public function validate_account_fields($errors, $user, $field_key)
    {
        $mepr_options = MeprOptions::fetch();

        $errors = MeprUsersCtrl::validate_extra_profile_fields(null, true, $user, false, false, $field_key);

        // Validate first name and last name.
        if (isset($_POST['first_name']) || isset($_POST['last_name'])) {
            if ($mepr_options->require_fname_lname && (empty($_POST['first_name']) || empty($_POST['last_name']))) {
                $errors[] = __('You must enter both your First and Last name', 'memberpress');
            }
        }

        if (isset($_POST['user_email'])) {
            if (empty($_POST['user_email']) || !is_email(stripslashes($_POST['user_email']))) {
                $errors[] = __('You must enter a valid email address', 'memberpress');
            }

            // Old email is not the same as the new, so let's make sure no else has it
            // $user = MeprUtils::get_currentuserinfo(); //Old user info is here since we haven't stored the new stuff yet.
            if ($user !== false && $user->user_email != stripslashes($_POST['user_email']) && email_exists(stripslashes($_POST['user_email']))) {
                $errors[] = __('This email is already in use by another member', 'memberpress');
            }
        }

        return $errors;
    }

    /**
     * Load more subscriptions.
     *
     * @return void
     */
    public function load_more_subscriptions()
    {
        // Check for nonce security!
        if (isset($_POST['nonce']) && ! wp_verify_nonce($_POST['nonce'], 'mepr_account_update')) {
            die('Busted!');
        }

        $count     = isset($_POST['count']) ? absint(wp_unslash($_POST['count'])) : 1;
        $acct_ctrl = MeprCtrlFactory::fetch('account');

        ob_start();

        $acct_ctrl->subscriptions(
            '',
            [],
            [
                'mode'  => 'readylaunch',
                'count' => $count,
            ]
        );

        $content = ob_get_clean();
        wp_send_json_success($content);
    }

    /**
     * Load more payments.
     *
     * @return void
     */
    public function load_more_payments()
    {
        // Check for nonce security!
        if (isset($_POST['nonce']) && ! wp_verify_nonce($_POST['nonce'], 'mepr_account_update')) {
            die('Busted!');
        }

        $count     = isset($_POST['count']) ? absint(wp_unslash($_POST['count'])) : 1;
        $acct_ctrl = MeprCtrlFactory::fetch('account');

        ob_start();
        $acct_ctrl->payments(
            [
                'mode'  => 'readylaunch',
                'count' => $count,
            ]
        );

        $content = ob_get_clean();
        wp_send_json_success($content);
    }

    /**
     * Placeholders to address fields.
     *
     * @param  array $fields The fields.
     * @return array
     */
    public function placeholders_to_address_fields($fields)
    {
        foreach ($fields as $key => $field) {
            $fields[ $key ]->placeholder = $field->field_name;
        }

        return $fields;
    }

    /**
     * Theme style.
     *
     * @return void
     */
    public static function theme_style()
    {
        $mepr_options = MeprOptions::fetch();

        $primary_color  = ! empty($mepr_options->design_primary_color) ? $mepr_options->design_primary_color : '#06429e';
        $text_color     = self::getContrastColor($primary_color);
        $current_screen = is_admin() ? get_current_screen() : '';

        $is_block_editor = (
        isset($current_screen) && ! empty($current_screen) &&
        method_exists($current_screen, 'is_block_editor') &&
        $current_screen->is_block_editor()
        );

        if (
            self::template_enabled('pricing') ||
            self::template_enabled('login') ||
            self::template_enabled('account') ||
            self::template_enabled('checkout') ||
            self::template_enabled('thankyou') ||
            $is_block_editor
        ) { // Full page templates.
            $html  = '<style type="text/css">';
            $html .= sprintf('body.mepr-guest-layout{background:%s!important}', $primary_color);
            $html .= sprintf('.app-layout .site-header, .guest-layout .site-header{background:%s!important}', $primary_color);
            $html .= sprintf('#mepr-account-nav{background:%s!important}', $primary_color);
            $html .= sprintf('.mepr-price-menu .mepr-price-boxes .mepr-most-popular{background:%s!important}', $primary_color);
            $html .= sprintf('#mepr-account-nav .mepr-nav-item a{color:rgba(%s)}', self::hexToRgb($text_color, 0.7));
            $html .= sprintf('#mepr-account-nav .mepr-nav-item a:hover{color:%s}', $text_color);
            $html .= sprintf('.app-layout .profile-menu__text, .guest-layout .profile-menu__text, .app-layout .profile-menu__arrow_down, .guest-layout .profile-menu__arrow_down{color:%s}', $text_color);
            $html .= sprintf('.app-layout .profile-menu__text--small, .guest-layout .profile-menu__text--small{color:rgba(%s)}', self::hexToRgb($text_color, 0.7));
            $html .= '</style>';

            echo $html;
        }

        if (is_singular() && has_block('memberpress/pro-account-tabs')) {
            $html  = '<style type="text/css">';
            $html .= sprintf('#mepr-account-nav{background:%s!important}', $primary_color);
            $html .= sprintf('#mepr-account-nav .mepr-nav-item a{color:rgba(%s)}', self::hexToRgb($text_color, 0.7));
            $html .= '</style>';

            echo $html;
        }
    }

    /**
     * Get the contrast color.
     *
     * @param  string $hex_color The hex color.
     * @return string
     */
    public static function getContrastColor($hex_color)
    {
        $hex_color     = trim($hex_color);
        $tmp_hex_color = trim($hex_color, '#');
        if (! ctype_xdigit($tmp_hex_color)) { // Validate HEX code.
            $hex_color = '#FFFFFF'; // Fallback to white color.
        }

        // HexColor RGB.
        $r1 = hexdec(substr($hex_color, 1, 2));
        $g1 = hexdec(substr($hex_color, 3, 2));
        $b1 = hexdec(substr($hex_color, 5, 2));

        // Black RGB.
        $black_color    = '#000000';
        $r2_black_color = hexdec(substr($black_color, 1, 2));
        $g2_black_color = hexdec(substr($black_color, 3, 2));
        $b2_black_color = hexdec(substr($black_color, 5, 2));

        // Calc contrast ratio.
        $l1 = 0.2126 * pow($r1 / 255, 2.2) +
        0.7152 * pow($g1 / 255, 2.2) +
        0.0722 * pow($b1 / 255, 2.2);

        $l2 = 0.2126 * pow($r2_black_color / 255, 2.2) +
        0.7152 * pow($g2_black_color / 255, 2.2) +
        0.0722 * pow($b2_black_color / 255, 2.2);

        $contrast_ratio = 0;
        if ($l1 > $l2) {
            $contrast_ratio = (int) ( ( $l1 + 0.05 ) / ( $l2 + 0.05 ) );
        } else {
            $contrast_ratio = (int) ( ( $l2 + 0.05 ) / ( $l1 + 0.05 ) );
        }

        // If contrast is more than 5, return black color.
        if ($contrast_ratio > 5) {
            return '#000000';
        } else {
            // If not, return white color.
            return '#FFFFFF';
        }
    }

    /**
     * Hex to RGB.
     *
     * @param  string  $hex   The hex.
     * @param  boolean $alpha The alpha.
     * @return string
     */
    public static function hexToRgb($hex, $alpha = false)
    {
        $hex      = str_replace('#', '', $hex);
        $length   = strlen($hex);
        $rgb['r'] = hexdec($length == 6 ? substr($hex, 0, 2) : ( $length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0 ));
        $rgb['g'] = hexdec($length == 6 ? substr($hex, 2, 2) : ( $length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0 ));
        $rgb['b'] = hexdec($length == 6 ? substr($hex, 4, 2) : ( $length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0 ));
        if ($alpha) {
            $rgb['a'] = $alpha;
        }
        return implode(',', $rgb);
    }
}
