<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProductsCtrl extends MeprCptCtrl
{
    /**
     * Load hooks for product management.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('admin_enqueue_scripts', 'MeprProductsCtrl::enqueue_scripts');
        add_action('manage_pages_custom_column', 'MeprProductsCtrl::custom_columns', 10, 2);
        add_filter('manage_edit-' . MeprProduct::$cpt . '_columns', 'MeprProductsCtrl::columns');
        add_filter('manage_edit-' . MeprProduct::$cpt . '_sortable_columns', 'MeprProductsCtrl::sortable_columns');
        add_filter('template_include', 'MeprProductsCtrl::template_include');
        add_action('save_post', 'MeprProductsCtrl::save_postdata');
        add_filter('the_content', 'MeprProductsCtrl::display_registration_form', 10);
        add_action('admin_init', 'MeprProduct::cleanup_db');
        add_action('before_delete_post', 'MeprProductsCtrl::nullify_records_on_delete');
        add_filter('login_redirect', 'MeprProductsCtrl::track_and_override_login_redirect_wp', 999999, 3);
        add_filter('mepr-process-login-redirect-url', 'MeprProductsCtrl::track_and_override_login_redirect_mepr', 10, 2);

        MeprHooks::add_shortcode('mepr-product-link', 'MeprProductsCtrl::shortcode_product_link'); // DEPRECATED.
        MeprHooks::add_shortcode('mepr-product-registration-form', 'MeprProductsCtrl::shortcode_registration_form'); // DEPRECATED.
        MeprHooks::add_shortcode('mepr-product-purchased', 'MeprProductsCtrl::shortcode_if_product_was_purchased'); // DEPRECATED.
        MeprHooks::add_shortcode('mepr-product-access-url', 'MeprProductsCtrl::shortcode_access_url_link'); // DEPRECATED.

        MeprHooks::add_shortcode('mepr-membership-link', 'MeprProductsCtrl::shortcode_product_link');
        MeprHooks::add_shortcode('mepr-membership-registration-form', 'MeprProductsCtrl::shortcode_registration_form');
        MeprHooks::add_shortcode('mepr-membership-purchased', 'MeprProductsCtrl::shortcode_if_product_was_purchased');
        MeprHooks::add_shortcode('mepr-membership-access-url', 'MeprProductsCtrl::shortcode_access_url_link');

        MeprHooks::add_shortcode('mepr-membership-price', 'MeprProductsCtrl::shortcode_price');

        add_action('wp_ajax_mepr_get_product_price_str', 'MeprProductsCtrl::get_price_str_ajax');

        // Cleanup list view.
        add_filter('views_edit-' . MeprProduct::$cpt, 'MeprAppCtrl::cleanup_list_view');

        // Category filter.
        add_action('init', 'MeprProductsCtrl::register_taxonomy');
        add_action('admin_init', 'MeprProductsCtrl::register_filter_queries');
        add_action('restrict_manage_posts', 'MeprProductsCtrl::render_memberships_filters', 10, 1);
        add_action('admin_footer-edit.php', 'MeprProductsCtrl::render_categories_button');
    }

    /**
     * Register the custom post type for products.
     *
     * @return void
     */
    public function register_post_type()
    {
        $mepr_options = MeprOptions::fetch();
        $this->cpt    = (object)[
            'slug'   => MeprProduct::$cpt,
            'config' => [
                'labels'               => [
                    'name'               => __('Memberships', 'memberpress'),
                    'singular_name'      => __('Membership', 'memberpress'),
                    'add_new'            => __('Add New', 'memberpress'),
                    'add_new_item'       => __('Add New Membership', 'memberpress'),
                    'edit_item'          => __('Edit Membership', 'memberpress'),
                    'new_item'           => __('New Membership', 'memberpress'),
                    'view_item'          => __('View Membership', 'memberpress'),
                    'search_items'       => __('Search Membership', 'memberpress'),
                    'not_found'          => __('No Membership found', 'memberpress'),
                    'not_found_in_trash' => __('No Membership found in Trash', 'memberpress'),
                    'parent_item_colon'  => __('Parent Membership:', 'memberpress'),
                ],
                'public'               => true,
                'show_ui'              => true, // MeprUpdateCtrl::is_activated().
                'show_in_menu'         => 'memberpress',
                'capability_type'      => 'page',
                'hierarchical'         => true,
                'register_meta_box_cb' => 'MeprProductsCtrl::add_meta_boxes',
                'rewrite'              => [
                    'slug'       => $mepr_options->product_pages_slug,
                    'with_front' => false,
                ],
                'supports'             => ['title', 'editor', 'page-attributes', 'comments', 'thumbnail'],
            ],
        ];
        register_post_type($this->cpt->slug, $this->cpt->config);
    }

    /**
     * Register the taxonomy for product categories.
     *
     * @return void
     */
    public static function register_taxonomy()
    {
        register_taxonomy(
            MeprProduct::$taxonomy_product_category,
            MeprProduct::$cpt,
            [
                'labels'            => [
                    'name'                       => esc_html_x('Categories', 'taxonomy general name', 'memberpress'),
                    'singular_name'              => esc_html_x('Category', 'taxonomy singular name', 'memberpress'),
                    'search_items'               => esc_html__('Search Categories', 'memberpress'),
                    'all_items'                  => esc_html__('All Categories', 'memberpress'),
                    'parent_item'                => esc_html__('Parent Category', 'memberpress'),
                    'parent_item_colon'          => esc_html__('Parent Category:', 'memberpress'),
                    'edit_item'                  => esc_html__('Edit Category', 'memberpress'),
                    'update_item'                => esc_html__('Update Category', 'memberpress'),
                    'add_new_item'               => esc_html__('Add New Category', 'memberpress'),
                    'new_item_name'              => esc_html__('New Category Name', 'memberpress'),
                    'menu_name'                  => esc_html__('Categories', 'memberpress'),
                    'separate_items_with_commas' => esc_html__('Separate Categories with commas', 'memberpress'),
                    'add_or_remove_items'        => esc_html__('Add or remove Categories', 'memberpress'),
                    'choose_from_most_used'      => esc_html__('Choose from the most used', 'memberpress'),
                    'popular_items'              => esc_html__('Popular Categories', 'memberpress'),
                    'not_found'                  => esc_html__('Not Found', 'memberpress'),
                    'no_terms'                   => esc_html__('No Categories', 'memberpress'),
                    'items_list'                 => esc_html__('Categories list', 'memberpress'),
                    'items_list_navigation'      => esc_html__('Categories list navigation', 'memberpress'),
                ],
                'hierarchical'      => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'rewrite'           => false,
                'capabilities'      => [
                    'manage_terms' => 'manage_options',
                    'edit_terms'   => 'manage_options',
                    'delete_terms' => 'manage_options',
                    'assign_terms' => 'manage_options',
                ],
            ]
        );
    }

    /**
     * Define the columns for the product list table.
     *
     * @param  array $columns The existing columns.
     * @return array
     */
    public static function columns($columns)
    {
        $columns = [
            'cb'    => '<input type="checkbox" />',
            'ID'    => esc_html__('ID', 'memberpress'),
            'title' => esc_html__('Membership Title', 'memberpress'),
            'terms' => esc_html__('Terms', 'memberpress'),
            'url'   => esc_html__('URL', 'memberpress'),
        ];
        return MeprHooks::apply_filters('mepr-admin-memberships-columns', $columns);
    }

    /**
     * Define the sortable columns for the product list table.
     *
     * @param  array $columns The existing columns.
     * @return array
     */
    public static function sortable_columns($columns)
    {
        $columns['ID'] = 'ID';
        return $columns;
    }

    /**
     * Render custom columns in the product list table.
     *
     * @param  string  $column  The name of the column.
     * @param  integer $post_id The ID of the post.
     * @return void
     */
    public static function custom_columns($column, $post_id)
    {
        $mepr_options = MeprOptions::fetch();
        $product      = new MeprProduct($post_id);

        if ($product->ID !== null) {
            if ('ID' == $column) {
                echo $product->ID;
            } elseif ('terms' == $column) {
                echo MeprProductsHelper::format_currency($product, true, null, false); // $product, $show_symbol, $coupon_code, $show_prorated
            } elseif ('url' == $column) {
                echo $product->url();
            }
        }
    }

    /**
     * Handle the template include for product pages.
     *
     * @param  string $template The current template path.
     * @return string The new template path if applicable.
     */
    public static function template_include($template)
    {
        global $post, $wp_query;

        if (!is_singular()) {
            return $template;
        }

        if (isset($post) && is_a($post, 'WP_Post') && $post->post_type == MeprProduct::$cpt) {
            $product      = new MeprProduct($post->ID);
            $new_template = $product->get_page_template();
        }

        if (isset($new_template) && !empty($new_template)) {
            return $new_template;
        }

        return $template;
    }

    /**
     * Add meta boxes for the product edit screen.
     *
     * @return void
     */
    public static function add_meta_boxes()
    {
        global $post_id;

        $product = new MeprProduct($post_id);

        add_meta_box('memberpress-product-meta', __('Membership Terms', 'memberpress'), 'MeprProductsCtrl::product_meta_box', MeprProduct::$cpt, 'side', 'high', ['product' => $product]);

        add_meta_box('memberpress-custom-template', __('Custom Page Template', 'memberpress'), 'MeprProductsCtrl::custom_page_template', MeprProduct::$cpt, 'side', 'default', ['product' => $product]);

        add_meta_box('memberpress-product-options', __('Membership Options', 'memberpress'), 'MeprProductsCtrl::product_options_meta_box', MeprProduct::$cpt, 'normal', 'high', ['product' => $product]);

        MeprHooks::do_action('mepr-product-meta-boxes', $product); // DEPRECATED.
        MeprHooks::do_action('mepr-membership-meta-boxes', $product);
    }

    /**
     * Save the product's metadata when the post is saved.
     *
     * @param  integer $post_id The ID of the post being saved.
     * @return integer
     */
    public static function save_postdata($post_id)
    {
        $post = get_post($post_id);

        if (!wp_verify_nonce((isset($_POST[MeprProduct::$nonce_str])) ? $_POST[MeprProduct::$nonce_str] : '', MeprProduct::$nonce_str . wp_salt())) {
            return $post_id; // Nonce prevents meta data from being wiped on move to trash.
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        if (defined('DOING_AJAX')) {
            return;
        }

        if (!empty($post) && $post->post_type == MeprProduct::$cpt) {
            $product = new MeprProduct($post_id);

            extract($_POST, EXTR_SKIP);

            $product->price                      = (isset($_mepr_product_price)) ? MeprUtils::format_currency_us_float(sanitize_text_field($_mepr_product_price)) : $product->attrs['price'];
            $product->period                     = (isset($_mepr_product_period)) ? sanitize_text_field($_mepr_product_period) : $product->attrs['period'];
            $product->period_type                = (isset($_mepr_product_period_type)) ? sanitize_text_field($_mepr_product_period_type) : $product->attrs['period_type'];
            $product->signup_button_text         = (isset($_mepr_product_signup_button_text)) ? wp_kses_post(trim($_mepr_product_signup_button_text)) : $product->attrs['signup_button_text'];
            $product->limit_cycles               = isset($_mepr_product_limit_cycles);
            $product->limit_cycles_num           = (isset($_mepr_product_limit_cycles_num)) ? sanitize_text_field($_mepr_product_limit_cycles_num) : $product->attrs['limit_cycles_num'];
            $product->limit_cycles_action        = (isset($_mepr_product_limit_cycles_action) ? sanitize_text_field($_mepr_product_limit_cycles_action) : $product->attrs['limit_cycles_action']);
            $product->limit_cycles_expires_after = (isset($_mepr_product_limit_cycles_expires_after) ? sanitize_text_field($_mepr_product_limit_cycles_expires_after) : $product->attrs['limit_cycles_expires_after']);
            $product->limit_cycles_expires_type  = (isset($_mepr_product_limit_cycles_expires_type) ? sanitize_text_field($_mepr_product_limit_cycles_expires_type) : $product->attrs['limit_cycles_expires_type']);
            $product->trial                      = isset($_mepr_product_trial);
            $product->trial_days                 = (isset($_mepr_product_trial_days)) ? sanitize_text_field($_mepr_product_trial_days) : $product->attrs['trial_days'];

            // Make sure the number of trial days is always set to at least 1 day.
            if ($product->trial_days <= 0) {
                $product->trial_days = 1;
            }

            $product->trial_amount       = (isset($_mepr_product_trial_amount)) ? MeprUtils::format_currency_us_float(sanitize_text_field($_mepr_product_trial_amount)) : $product->attrs['trial_amount'];
            $product->trial_once         = isset($_mepr_product_trial_once);
            $product->who_can_purchase   = self::get_who_can_purchase_array();
            $product->is_highlighted     = isset($_mepr_product_is_highlighted);
            $product->pricing_title      = (isset($_mepr_product_pricing_title)) ? wp_kses_post(trim($_mepr_product_pricing_title)) : $product->attrs['pricing_title'];
            $product->pricing_show_price = isset($_mepr_product_pricing_show_price);
            $product->plan_code          = isset($_mepr_plan_code) ? sanitize_user($_mepr_plan_code, true) : $product->attrs['plan_code'];

            $product->pricing_display = isset($_mepr_product_pricing_display) ? sanitize_text_field($_mepr_product_pricing_display) : $product->attrs['pricing_display'];

            $product->custom_price = isset($_mepr_product_custom_price) ? sanitize_text_field($_mepr_product_custom_price) : $product->attrs['custom_price'];

            $product->pricing_heading_txt     = (isset($_mepr_product_pricing_heading_text)) ? wp_kses_post($_mepr_product_pricing_heading_text) : $product->attrs['pricing_heading_text'];
            $product->pricing_footer_txt      = (isset($_mepr_product_pricing_footer_text)) ? wp_kses_post($_mepr_product_pricing_footer_text) : $product->attrs['pricing_footer_txt'];
            $product->pricing_button_txt      = (isset($_mepr_product_pricing_button_text)) ? wp_kses_post(trim($_mepr_product_pricing_button_text)) : $product->attrs['pricing_button_txt'];
            $product->pricing_button_position = (isset($_mepr_product_pricing_button_position)) ? sanitize_text_field($_mepr_product_pricing_button_position) : $product->attrs['pricing_button_position'];
            $product->pricing_benefits        = (isset($_mepr_product_pricing_benefits)) ? array_map(function ($benefit) {
                return trim(sanitize_text_field($benefit));
            }, $_mepr_product_pricing_benefits) : $product->attrs['pricing_benefits'];
            $product->register_price_action   = (isset($_mepr_register_price_action)) ? sanitize_text_field($_mepr_register_price_action) : $product->attrs['register_price_action'];
            $product->register_price          = (isset($_mepr_register_price)) ? sanitize_text_field($_mepr_register_price) : $product->attrs['register_price'];
            $product->thank_you_page_enabled  = isset($_mepr_thank_you_page_enabled);
            $product->thank_you_message       = (isset($meprproductthankyoumessage) && !empty($meprproductthankyoumessage)) ? wp_kses_post(wp_unslash($meprproductthankyoumessage)) : $product->attrs['thank_you_message'];
            $product->thank_you_page_type     = (isset($_mepr_thank_you_page_type) ? sanitize_text_field($_mepr_thank_you_page_type) : $product->attrs['thank_you_page_type']);
            $product->thank_you_page_id       = (isset($_mepr_product_thank_you_page_id) && is_numeric($_mepr_product_thank_you_page_id) && (int)$_mepr_product_thank_you_page_id > 0) ? (int)$_mepr_product_thank_you_page_id : $product->attrs['thank_you_page_id'];

            /**
            * Sets thank_you_page_id to the id from the POST or Adds the new page.
            */
            if ($product->thank_you_page_type == 'page' && isset($_mepr_product_thank_you_page_id)) {
                if (is_numeric($_mepr_product_thank_you_page_id) && (int)$_mepr_product_thank_you_page_id > 0) {
                    $product->thank_you_page_id = (int)$_mepr_product_thank_you_page_id;
                } elseif ($product->thank_you_page_enabled && preg_match('#^__auto_page:(.*?)$#', $_mepr_product_thank_you_page_id, $matches)) {
                    $product->thank_you_page_id = MeprAppHelper::auto_add_page($matches[1], esc_html__('Your subscription has been set up successfully.', 'memberpress'));
                } else {
                    $product->thank_you_page_id = $product->attrs['thank_you_page_id'];
                }
            }

            $product->simultaneous_subscriptions = isset($_mepr_allow_simultaneous_subscriptions);
            $product->use_custom_template        = isset($_mepr_use_custom_template);
            $product->custom_template            = isset($_mepr_custom_template) ? sanitize_text_field($_mepr_custom_template) : $product->attrs['custom_template'];
            $product->customize_payment_methods  = isset($_mepr_customize_payment_methods);
            $product->customize_profile_fields   = isset($_mepr_customize_profile_fields);
            $product->custom_profile_fields      = []; // We'll populate it below if we need to.
            $custom_payment_methods              = json_decode(sanitize_text_field(wp_unslash($_POST['mepr-product-payment-methods-json'])));
            $product->custom_payment_methods     = is_array($custom_payment_methods) ? $custom_payment_methods : [];
            $product->custom_login_urls_enabled  = isset($_mepr_custom_login_urls_enabled);
            $product->expire_type                = isset(${MeprProduct::$expire_type_str}) ? sanitize_text_field($_POST[MeprProduct::$expire_type_str]) : $product->attrs['expire_type'];
            $product->expire_after               = isset(${MeprProduct::$expire_after_str}) ? sanitize_text_field($_POST[MeprProduct::$expire_after_str]) : $product->attrs['expire_after'];
            $product->expire_unit                = isset(${MeprProduct::$expire_unit_str}) ? sanitize_text_field($_POST[MeprProduct::$expire_unit_str]) : $product->attrs['expire_unit'];
            $product->expire_fixed               = isset(${MeprProduct::$expire_fixed_str}) ? sanitize_text_field($_POST[MeprProduct::$expire_fixed_str]) : $product->attrs['expire_fixed'];
            $product->tax_exempt                 = isset($_POST[MeprProduct::$tax_exempt_str]);
            $product->tax_class                  = isset(${MeprProduct::$tax_class_str}) ? sanitize_text_field($_POST[MeprProduct::$tax_class_str]) : $product->attrs['tax_class'];
            $product->allow_renewal              = (($product->period_type == 'lifetime' || $product->price == 0.00) && (($product->expire_type == 'delay' && isset($_POST[MeprProduct::$allow_renewal_str])) || ($product->expire_type == 'fixed' && isset($_POST[MeprProduct::$allow_renewal_str . '-fixed']))));
            $product->access_url                 = isset($_mepr_access_url) ? sanitize_text_field(wp_unslash(trim($_mepr_access_url))) : $product->attrs['access_url'];
            $product->disable_address_fields     = (isset($_mepr_disable_address_fields) && $product->price <= 0.00);
            $product->cannot_purchase_message    = (!empty($meprcannotpurchasemessage)) ? wp_kses_post(wp_unslash($meprcannotpurchasemessage)) : $product->cannot_purchase_message;

            // Notification Settings.
            $emails = [];
            foreach ($_POST[MeprProduct::$emails_str] as $email => $vals) {
                $emails[$email] = [
                    'enabled'      => isset($vals['enabled']),
                    'use_template' => isset($vals['use_template']),
                    'subject'      => sanitize_text_field(wp_unslash($vals['subject'])),
                    'body'         => MeprUtils::maybe_wpautop(wp_kses_post(wp_unslash($vals['body']))),
                ];
            }
            $product->emails = $emails;

            if ($product->custom_login_urls_enabled) {
                $product = self::set_custom_login_urls($product);
            }

            // Setup the custom profile fields.
            if ($product->customize_profile_fields && isset($_POST['product-profile-fields'])) {
                $slugs = [];

                foreach ($_POST['product-profile-fields'] as $key => $value) {
                    $slugs[] = sanitize_title_with_dashes($key);
                }

                $product->custom_profile_fields = $slugs;
            }

            $product = self::validate_product($product);
            $product->store_meta(); // Only storing metadata here.

            // Some themes rely on this meta key to be set to use the custom template, and they don't use locate_template.
            if ($product->use_custom_template && !empty($product->custom_template)) {
                update_post_meta($product->ID, '_wp_page_template', $product->custom_template);
            } else {
                update_post_meta($product->ID, '_wp_page_template', '');
            }

            MeprHooks::do_action('mepr-product-save-meta', $product); // DEPRECATED.
            MeprHooks::do_action('mepr-membership-save-meta', $product);
        }
    }

    /**
     * Set custom login URLs for a product.
     *
     * @param  MeprProduct $product The product object.
     * @return MeprProduct
     */
    public static function set_custom_login_urls($product)
    {
        extract($_POST, EXTR_SKIP);

        $custom_login_urls = [];

        $product->custom_login_urls_default = (isset($_mepr_custom_login_urls_default) && !empty($_mepr_custom_login_urls_default)) ? stripslashes(trim($_mepr_custom_login_urls_default)) : '';

        if (isset($_mepr_custom_login_urls) && !empty($_mepr_custom_login_urls)) {
            foreach ($_mepr_custom_login_urls as $i => $url) {
                if (!empty($url)) {
                    $custom_login_urls[] = (object)[
                        'url'   => stripslashes(trim($url)),
                        'count' => (int)$_mepr_custom_login_urls_count[$i],
                    ];
                }
            }
        }

        $product->custom_login_urls = $custom_login_urls;

        return $product;
    }

    /**
     * Get the array of users who can purchase the product.
     *
     * @return array
     */
    public static function get_who_can_purchase_array()
    {
        $rows = [];

        if (empty($_POST[MeprProduct::$who_can_purchase_str . '-user_type'])) {
            return $rows;
        }

        $count = count($_POST[MeprProduct::$who_can_purchase_str . '-user_type']) - 1;

        for ($i = 0; $i < $count; $i++) {
            $user_type     = sanitize_text_field($_POST[MeprProduct::$who_can_purchase_str . '-user_type'][$i]);
            $product_id    = sanitize_text_field($_POST[MeprProduct::$who_can_purchase_str . '-product_id'][$i]);
            $purchase_type = sanitize_text_field($_POST[MeprProduct::$have_or_had_str . '-type'][$i]);
            $rows[]        = (object)[
                'user_type'     => $user_type,
                'product_id'    => $product_id,
                'purchase_type' => $purchase_type,
            ];
        }

        return $rows;
    }

    /**
     * Validate the product's properties.
     *
     * @param  MeprProduct $product The product object.
     * @return MeprProduct
     */
    public static function validate_product($product)
    {
        // Validate Periods.
        if ($product->period_type == 'weeks' && $product->period > 52) {
            $product->period = 52;
        }

        if ($product->period_type == 'months' && $product->period > 12) {
            $product->period = 12;
        }

        if (!is_numeric($product->period) || $product->period <= 0 || empty($product->period)) {
            $product->period = 1;
        }

        if (!is_numeric($product->trial_days) || $product->trial_days <= 0 || empty($product->trial_days)) {
            $product->trial_days = 0;
        }

        if ($product->trial_days > 365) {
            $product->trial_days = 365;
        }

        // Validate Prices
        // preg_match replaces !is_numeric() to allow comma, period & space before applying (float).
        if (preg_match('/[^0-9., ]/', $product->price) || $product->price < 0.00) {
            $product->price = 0.00;
        }

        if (!is_numeric($product->trial_amount) || $product->trial_amount < 0.00) {
            $product->trial_amount = 0.00;
        }

        // Disable trial && cycles limit if lifetime is set and set period to 1.
        if ($product->period_type == 'lifetime') {
            $product->limit_cycles = false;
            $product->trial        = false;
            $product->period       = 1;
        }

        // Cycles limit must be positive.
        if (empty($product->limit_cycles_num) || !is_numeric($product->limit_cycles_num) || $product->limit_cycles_num <= 0) {
            $product->limit_cycles_num = 2;
        }

        // If price = 0.00 and period type is not lifetime, we need to disable cycles and trials.
        if ($product->price == 0.00 && $product->period_type != 'lifetime') {
            $product->limit_cycles = false;
            $product->trial        = false;
        }

        // Handle delayed expirations on one-time payments.
        if ($product->period_type == 'lifetime' && $product->expire_type == 'delay') {
            if (!is_numeric($product->expire_after) || $product->expire_after < 0) {
                $product->expire_after = 1;
            }

            if (!in_array($product->expire_unit, ['days', 'weeks', 'months', 'years'])) {
                $product->expire_unit = 'days';
            }
        }

        // Handle fixed expirations on one-time payments.
        if ($product->period_type == 'lifetime' && $product->expire_type == 'fixed') {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $product->expire_fixed, $datebit)) {
                if (!checkdate($datebit[2], $datebit[3], $datebit[1])) {
                    $product->expire_type = 'none'; // An invalid date was set, so let's just make this a lifetime.
                }
            } else {
                $product->expire_type = 'none'; // An invalid date was set, so let's just make this a lifetime.
            }
        }

        // Make sure there's at least one payment method selected when customizing payment methods.
        if ($product->customize_payment_methods && (empty($product->custom_payment_methods) || ! is_array($product->custom_payment_methods))) {
            $product->customize_payment_methods = false;
        }

        return $product;
    }

    /**
     * Displays product terms for the meta box.
     * Returns terms from gateway or admin product terms form
     * Don't use $post here, it is null on new membership - use args instead
     *
     * @param  WP_Post $post The post object.
     * @param  array   $args The arguments for the meta box.
     * @return string
     */
    public static function product_meta_box($post, $args)
    {
        $product      = $args['args']['product'];
        $mepr_options = MeprOptions::fetch();
        $gateway_ids  = array_keys($mepr_options->payment_methods());
        foreach ($gateway_ids as $gateway_id) {
            $gateway = $mepr_options->payment_method($gateway_id);
            if ($gateway instanceof MeprBaseExclusiveRecurringGateway) {
                // Return terms from exclusive gateway.
                return $gateway->display_plans_terms($product);
            }
        }

        // Render product terms form.
        MeprView::render('/admin/products/form', get_defined_vars());
    }

    /**
     * Display the product options meta box.
     * Don't use $post here, it is null on new membership - use args instead.
     *
     * @param  WP_Post $post The post object.
     * @param  array   $args The arguments for the meta box.
     * @return void
     */
    public static function product_options_meta_box($post, $args)
    {
        $mepr_options = MeprOptions::fetch();
        $product      = $args['args']['product'];

        MeprView::render('/admin/products/product_options_meta_box', get_defined_vars());
    }

    /**
     * Display the custom page template meta box.
     * Don't use $post here, it is null on new membership - use args instead.
     *
     * @param  WP_Post $post The post object.
     * @param  array   $args The arguments for the meta box.
     * @return void
     */
    public static function custom_page_template($post, $args)
    {
        $product = $args['args']['product'];

        MeprView::render('/admin/products/custom_page_template_form', get_defined_vars());
    }

    /**
     * Display the registration form for a product.
     *
     * @param  string  $content The existing content.
     * @param  boolean $manual  Whether the form is displayed manually.
     * @return string
     */
    public static function display_registration_form($content, $manual = false)
    {
        global $user_ID;
        $mepr_options = MeprOptions::fetch();
        $current_post = MeprUtils::get_current_post();

        // This isn't a post? Just return the content then.
        if ($current_post === false) {
            return $content;
        }

        // Stop rendering registration form on Admin side.
        if (is_admin()) {
            return $content;
        }

        // WARNING the_content CAN be run more than once per page load
        // so this static var prevents stuff from happening twice
        // like cancelling a subscr or resuming etc...
        static $already_run    = [];
        static $new_content    = [];
        static $content_length = [];

        // Init this posts static values.
        if (!isset($new_content[$current_post->ID]) || empty($new_content[$current_post->ID])) {
            $already_run[$current_post->ID]    = false;
            $new_content[$current_post->ID]    = '';
            $content_length[$current_post->ID] = -1;
        }

        if ($already_run[$current_post->ID] && strlen($content) == $content_length[$current_post->ID] && !$manual) { // Shortcode may pass.
            return $new_content[$current_post->ID];
        }

        $content_length[$current_post->ID] = strlen($content);
        $already_run[$current_post->ID]    = true;

        if (isset($current_post) && is_a($current_post, 'WP_Post') && $current_post->post_type == MeprProduct::$cpt) {
            if (post_password_required($current_post)) {
                // See notes above.
                $new_content[$current_post->ID] = $content;
                return $new_content[$current_post->ID];
            }

            $prd = new MeprProduct($current_post->ID);

            // Short circuiting for any of the following reasons.
            if (
                $prd->ID === null || // Bad membership for some reason.
                (!$manual && $prd->manual_append_signup()) || // Show manually and the_content filter are enabled.
                ($manual && !$prd->manual_append_signup())
            ) { // Show manually and do_shortcode are disabled
                // See notes above.
                $new_content[$current_post->ID] = $content;
                return $new_content[$current_post->ID];
            }

            // We want to render this form after processing the signup form unless
            // there were errors and when trying to process the paymet form.
            if (
                isset($_REQUEST) and
                ((isset($_POST['mepr_process_signup_form']) and !isset($_POST['errors'])) or
                isset($_POST['mepr_process_payment_form']) or
                (isset($_GET['action']) and $_GET['action'] === 'checkout' and isset($_GET['txn'])))
            ) {
                ob_start();
                try {
                    $checkout_ctrl = MeprCtrlFactory::fetch('checkout');
                    $checkout_ctrl->display_payment_form();
                } catch (Exception $e) {
                    ?>
          <div class="mepr_error"><?php _e('There was a problem with our payment system. Please come back soon and try again.', 'memberpress'); ?></div>
                    <?php
                }

                // See notes above.
                $new_content[$current_post->ID] = ob_get_clean();
                return $new_content[$current_post->ID];
            }

            $res = self::get_registration_form($prd);
            if ($res->enabled) {
                $content .= $res->content;
            } else {
                $content = $res->content;
            }
        }

        // See notes above.
        $new_content[$current_post->ID] = $content;
        return $new_content[$current_post->ID];
    }

    /**
     * Get the registration form for a product.
     *
     * @param  MeprProduct $prd The product object.
     * @return object
     */
    public static function get_registration_form($prd)
    {
        global $user_ID;
        $mepr_options = MeprOptions::fetch();

        $product_access_str = '';
        if (
            $user_ID && !$prd->simultaneous_subscriptions && !empty($prd->access_url)
        ) {
            $user = new MeprUser($user_ID);
            if ($user->is_already_subscribed_to($prd->ID)) {
                $product_access_str = MeprHooks::apply_filters('mepr_product_access_string', sprintf(
                    // Translators: %1$s: opening div tag, %2$s: opening anchor tag, %3$s: closing anchor and div tags.
                    __('%1$sYou have already subscribed to this item. %2$sClick here to access it%3$s', 'memberpress'),
                    '<div class="mepr-product-access-url">',
                    '<a href="' . stripslashes($prd->access_url) . '">',
                    '</a></div>'
                ), $prd);
            }
        }

        ob_start();
        // If the user can't purchase this let's show a message.
        if (!$prd->can_you_buy_me()) {
            $enabled = false;
            if (!empty($product_access_str)) {
                $cant_purchase_str = $product_access_str;
            } else {
                $cant_purchase_str = wpautop(do_shortcode($prd->cannot_purchase_message));
            }

            $cant_purchase_str = MeprHooks::apply_filters('mepr-product-cant-purchase-string', $cant_purchase_str, $prd); // DEPRECATED.
            echo MeprHooks::apply_filters('mepr-membership-cant-purchase-string', $cant_purchase_str, $prd);
        } else {
            $pm   = isset($_GET['pmt']) ? $mepr_options->payment_method($_GET['pmt']) : null;
            $msgp = ($pm && isset($_GET['action'])) ? $pm->message_page($_GET['action']) : null;
            if ($pm && $msgp) {
                $enabled = false;
                call_user_func([$pm, $msgp]);
            } else {
                $enabled = true;
                try {
                    $checkout_ctrl = MeprCtrlFactory::fetch('checkout');
                    echo $product_access_str;
                    $checkout_ctrl->display_signup_form($prd);
                } catch (Exception $e) {
                    ?>
                    <div class="mepr_error"><?php _e('There was a problem with our payment system. Please come back soon and try again.', 'memberpress'); ?></div>
                    <?php
                }
            }
        }

        $content = ob_get_clean();
        return (object)compact('enabled', 'content');
    }

    /**
     * Enqueue scripts for the product admin page.
     *
     * @param  string $hook The current admin page hook.
     * @return void
     */
    public static function enqueue_scripts($hook)
    {
        global $current_screen;

        if ($current_screen->post_type == MeprProduct::$cpt) {
            wp_register_style('mepr-jquery-ui-smoothness', MEPR_CSS_URL . '/vendor/jquery-ui/smoothness.min.css', [], '1.13.3');
            wp_register_style('jquery-ui-timepicker-addon', MEPR_CSS_URL . '/vendor/jquery-ui-timepicker-addon.css', ['mepr-jquery-ui-smoothness'], MEPR_VERSION);
            wp_enqueue_style('mepr-transactions-css', MEPR_CSS_URL . '/admin-transactions.css', ['jquery-ui-timepicker-addon'], MEPR_VERSION);
            wp_enqueue_style('mepr-emails-css', MEPR_CSS_URL . '/admin-emails.css', [], MEPR_VERSION);
            wp_enqueue_style('mepr-products-css', MEPR_CSS_URL . '/admin-products.css', ['mepr-emails-css','mepr-settings-table-css','jquery-ui-timepicker-addon'], MEPR_VERSION);

            wp_dequeue_script('autosave'); // Disable auto-saving.

            wp_register_script('mepr-timepicker-js', MEPR_JS_URL . '/vendor/jquery-ui-timepicker-addon.js', ['jquery-ui-datepicker'], MEPR_VERSION);
            wp_register_script('mepr-date-picker-js', MEPR_JS_URL . '/date_picker.js', ['mepr-timepicker-js'], MEPR_VERSION);
            wp_enqueue_script('mepr-products-js', MEPR_JS_URL . '/admin_products.js', ['jquery-ui-spinner','mepr-date-picker-js','jquery-ui-sortable','mepr-settings-table-js','mepr-admin-shared-js'], MEPR_VERSION);
            $email_locals = [
                'set_email_defaults_nonce' => wp_create_nonce('set_email_defaults'),
                'send_test_email_nonce'    => wp_create_nonce('send_test_email'),
            ];
            wp_enqueue_script('mepr-emails-js', MEPR_JS_URL . '/admin_emails.js', ['mepr-products-js'], MEPR_VERSION);
            wp_localize_script('mepr-emails-js', 'MeprEmail', $email_locals);

            // We need to hide the timepicker stuff here.
            $date_picker_frontend = [
                'timeFormat' => '',
                'showTime'   => false,
            ];
            wp_localize_script('mepr-date-picker-js', 'MeprDatePicker', $date_picker_frontend);

            $options = [
                'removeBenefitStr'         => __('Remove Benefit', 'memberpress'),
                'register_price_action_id' => '#' . MeprProduct::$register_price_action_str,
                'register_price_id'        => '#' . MeprProduct::$register_price_str,
                'wpnonce'                  => wp_create_nonce(MEPR_PLUGIN_SLUG),
            ];
            wp_localize_script('mepr-products-js', 'MeprProducts', $options);

            MeprHooks::do_action('mepr-product-admin-enqueue-script', $hook); // DEPRECATED.
            MeprHooks::do_action('mepr-membership-admin-enqueue-script', $hook);
        }
    }

    /**
     * Nullify records associated with a product upon deletion.
     *
     * @param  integer $id The ID of the product.
     * @return integer
     */
    public static function nullify_records_on_delete($id)
    {
        MeprTransaction::nullify_product_id_on_delete($id);
        MeprSubscription::nullify_product_id_on_delete($id);

        return $id;
    }

    /**
     * Generate a product link shortcode.
     *
     * @param  array  $atts    The shortcode attributes.
     * @param  string $content The content of the shortcode.
     * @return string
     */
    public static function shortcode_product_link($atts, $content = '')
    {
        if (!isset($atts['id']) || !is_numeric($atts['id'])) {
            return $content;
        }

        $product = new MeprProduct($atts['id']);

        if ($product->ID === null) {
            return $content;
        }

        return MeprProductsHelper::generate_product_link_html($product, $content);
    }

    /**
     * Generate a registration form shortcode.
     *
     * @param  array  $atts    The shortcode attributes.
     * @param  string $content The content of the shortcode.
     * @return string
     */
    public static function shortcode_registration_form($atts, $content = '')
    {
        $membership_id = (isset($atts['id'])) ? $atts['id'] : 0;
        $membership_id = ($membership_id === 0 && isset($atts['product_id'])) ? $atts['product_id'] : $membership_id; // Back compat.

        $prd = ($membership_id > 0) ? new MeprProduct($membership_id) : false;

        if ($prd !== false && isset($prd->ID) && $prd->ID > 0) {
            $res = self::get_registration_form($prd);
            return $res->content;
        }

        return self::display_registration_form('', true);
    }

    /**
     * Generate a shortcode to check if a product was purchased.
     *
     * @param  array  $atts    The shortcode attributes.
     * @param  string $content The content of the shortcode.
     * @return string
     */
    public static function shortcode_if_product_was_purchased($atts, $content = '')
    {
        // Let's keep the protected string hidden if we have garbage input.
        if (
            !isset($atts['id']) or
            !is_numeric($atts['id']) or
            !isset($_REQUEST['trans_num'])
        ) {
            return '';
        }

        $txn  = new MeprTransaction();
        $data = MeprTransaction::get_one_by_trans_num($_REQUEST['trans_num']);
        $txn->load_data($data);

        if (!$txn->id or $txn->product_id != $atts['id']) {
            return '';
        }

        return $content;
    }

    /**
     * Get the thank you page message for a product.
     *
     * @return string
     */
    public static function maybe_get_thank_you_page_message()
    {
        if (isset($_REQUEST['membership_id'])) {
            $product = new MeprProduct(intval($_REQUEST['membership_id']));
        } else {
            if (! isset($_REQUEST['trans_num'])) {
                return '';
            }

            $txn  = new MeprTransaction();
            $data = MeprTransaction::get_one_by_trans_num($_REQUEST['trans_num']);
            $txn->load_data($data);

            if (! $txn->id || ! $txn->product_id) {
                return '';
            }

            $product = $txn->product();
        }

        if ($product->ID === null || !$product->thank_you_page_enabled || empty($product->thank_you_message)) {
            return '';
        }

        // Backwards compatibility check.
        if (!empty($product->thank_you_page_type) && $product->thank_you_page_type != 'message') {
            return '';
        }

        $message = wpautop(stripslashes($product->thank_you_message));
        $message = do_shortcode($message);
        $message = MeprHooks::apply_filters('mepr_custom_thankyou_message', $message);

        if (isset($txn)) {
            MeprHooks::do_action('mepr-thank-you-page', $txn);
        }

        return '<div id="mepr-thank-you-page-message">' . $message . '</div>';
    }

    /**
     * Track and override the login redirect for a product.
     * wrapper for track_and_override_login_redirect_mepr() to catch regular WP logins
     *
     * @param  string          $url     The original URL.
     * @param  string          $request The request.
     * @param  WP_User|boolean $user    The WordPress user object.
     * @return string
     */
    public static function track_and_override_login_redirect_wp($url, $request, $user)
    {
        return self::track_and_override_login_redirect_mepr($url, $user, true);
    }

    /**
     * Track and override the login redirect for a product.
     *
     * @param  string          $url              The original URL.
     * @param  WP_User|boolean $wp_user          The WordPress user object.
     * @param  boolean         $is_wp_login_page Whether the login page is a WordPress login page.
     * @param  boolean         $track            Whether to track the login.
     * @return string
     */
    public static function track_and_override_login_redirect_mepr($url = '', $wp_user = false, $is_wp_login_page = false, $track = true)
    {
        static $exsubs     = null;
        static $num_logins = null;

        $mepr_options = MeprOptions::fetch();

        if (empty($wp_user) || is_wp_error($wp_user)) {
            return $url;
        }

        $is_login_page = ((isset($_POST['mepr_is_login_page']) && $_POST['mepr_is_login_page'] == 'true') || $is_wp_login_page);

        // Track this login, then get the num total logins for this user.
        $user = new MeprUser($wp_user->ID);

        if ($track) {
            MeprEvent::record('login', $user);
        }

        // Short circuit if user has expired subscriptions and is not an admin.
        if (is_null($exsubs)) {
            $exsubs = $user->subscription_expirations('expired', true);
        }
        if (!empty($exsubs) && !$wp_user->has_cap('delete_users')) {
            return $mepr_options->account_page_url();
        }

        if (is_null($num_logins)) {
            $num_logins = $user->get_num_logins();
        }

        // Get user's active memberships.
        $membership_id = MeprProduct::get_highest_menu_order_active_membership_by_user($user->ID);

        if ($membership_id === false) {
            return $url;
        } else {
            $membership = new MeprProduct($membership_id);
        }

        if ($membership->custom_login_urls_enabled && (!empty($membership->custom_login_urls_default) || !empty($membership->custom_login_urls))) {
            if (!empty($membership->custom_login_urls)) {
                foreach ($membership->custom_login_urls as $custom_url) {
                    if (!empty($custom_url) && $custom_url->count == $num_logins) {
                        return stripslashes($custom_url->url);
                    }
                }
            }

            return (!empty($membership->custom_login_urls_default) && $is_login_page) ? $membership->custom_login_urls_default : $url;
        }

        return $url;
    }

    /**
     * Get the price string via AJAX for the price box in the dashboard.
     *
     * @return void
     */
    public static function get_price_str_ajax()
    {
        if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
            die(__('An unknown error has occurred', 'memberpress'));
        }

        $product = new MeprProduct($_POST['product_id']);

        if (!isset($product->ID) || (int)$product->ID <= 0) {
            die(__('Please save membership first to see the Price here.', 'memberpress'));
        }

        die(MeprAppHelper::format_price_string($product, $product->price));
    }

    /**
     * Generate an access URL link shortcode.
     *
     * @param  array  $atts    The shortcode attributes.
     * @param  string $content The content of the shortcode.
     * @return string
     */
    public static function shortcode_access_url_link($atts = [], $content = '')
    {
        if (!isset($atts['id']) || !is_numeric($atts['id'])) {
            return $content;
        }

        $product = new MeprProduct($atts['id']);

        if ($product->ID === null || empty($product->access_url)) {
            return $content;
        }

        if (empty($content)) {
            $link_text = $product->post_title;
        } else {
            $link_text = $content;
        }

        return '<a href="' . $product->access_url . '">' . $link_text . '</a>';
    }

    /**
     * Generate a price shortcode for a product.
     *
     * @param  array  $atts    The shortcode attributes.
     * @param  string $content The content of the shortcode.
     * @return string
     */
    public static function shortcode_price($atts = [], $content = '')
    {
        global $post;

        if (!isset($atts['id']) && !empty($post) && $post->post_type == MeprProduct::$cpt) {
            $membership = new MeprProduct($post->ID);
        } elseif (isset($atts['id'])) {
            $membership = new MeprProduct($atts['id']);
        } else {
            return '';
        }

        $coupon_code = null;
        $diff        = false;
        if (isset($atts['coupon'])) {
            if ($atts['coupon'] == 'param' && isset($_REQUEST['coupon'])) {
                $coupon_code = $_REQUEST['coupon'];
            } else {
                $coupon_code = $atts['coupon'];
            }

            if (isset($atts['diff']) && $atts['diff']) {
                $diff = true;
            }

            $coupon = MeprCoupon::get_one_from_code($coupon_code);

            if ($coupon) {
                $coupon->maybe_apply_trial_override($membership);
            }
        }

        if ($membership->trial) {
            $adj_price = $membership->trial_amount;
        } else {
            $adj_price = $membership->adjusted_price($coupon_code);
        }

        if ($diff) {
            $display_price = $membership->adjusted_price() - $adj_price;
        } else {
            $display_price = $adj_price;
        }

        if (isset($atts['format'])) {
            preg_match('!^(\d*)(\.\d*)?$!', $display_price, $price_matches);
            if ($atts['format'] == 'cents') {
                return (isset($price_matches[2]) ? $price_matches[2] : '&nbsp;&nbsp;&nbsp;');
            } elseif ($atts['format'] == 'dollars') {
                return $price_matches[1];
            }
        }

        return MeprUtils::format_float_drop_zero_decimals($display_price);
    }

    /**
     * Render filters for membership products.
     *
     * @param  string $post_type The post type being filtered.
     * @return void
     */
    public static function render_memberships_filters($post_type)
    {
        if ($post_type === MeprProduct::$cpt) {
            $taxonomy      = MeprProduct::$taxonomy_product_category;
            $selected      = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
            $info_taxonomy = get_taxonomy($taxonomy);

            if (false === $info_taxonomy) {
                return;
            }

            $taxonomy_args = [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'fields'     => 'ids',
                'number'     => 1,
            ];

            $taxonomy_terms = get_terms($taxonomy_args);

            if (empty($taxonomy_terms)) {
                return;
            }

            wp_dropdown_categories([
                'show_option_all' => sprintf(
                    // Translators: %s: taxonomy label.
                    esc_html__('Show all %s', 'memberpress'),
                    $info_taxonomy->label
                ),
                'taxonomy'        => $taxonomy,
                'name'            => $taxonomy,
                'orderby'         => 'name',
                'selected'        => $selected,
                'show_count'      => true,
                'hide_empty'      => false,
            ]);

            echo wp_kses(
                sprintf('<input type="submit" id="mepr_filter_submit" class="button" value="%s">', esc_html__('Filter', 'memberpress')),
                [
                    'input' => [
                        'type'  => [],
                        'name'  => [],
                        'id'    => [],
                        'class' => [],
                        'value' => [],
                    ],
                ]
            );
        }
    }

    /**
     * Register filter queries.
     *
     * @return void
     */
    public static function register_filter_queries()
    {
        add_action('parse_query', 'MeprProductsCtrl::filter_memberships');
    }

    /**
     * Filter the memberships as per selected taxonomy.
     *
     * @param  WP_Query $query The query object.
     * @return void
     */
    public static function filter_memberships($query)
    {
        global $pagenow;
        $taxonomy = MeprProduct::$taxonomy_product_category;
        if (
            $pagenow == 'edit.php' && is_admin()
            && isset($query->query_vars['post_type'])
            && $query->query_vars['post_type'] === MeprProduct::$cpt
            && isset($query->query_vars[$taxonomy])
            && is_numeric($query->query_vars[$taxonomy])
            && 0 < absint($query->query_vars[$taxonomy])
        ) {
            $term = get_term_by('id', (int) $query->query_vars[$taxonomy], $taxonomy);
            if ($term && ! is_wp_error($term)) {
                $query->query_vars[$taxonomy] = $term->slug;
            }
        }
    }

    /**
     * Render category button beside 'Add New' button.
     *
     * @return void
     */
    public static function render_categories_button()
    {
        if (empty($_GET['post_type']) || MeprProduct::$cpt !== $_GET['post_type']) {
            return;
        }
        $category_link = add_query_arg(
            [
                'taxonomy'  => MeprProduct::$taxonomy_product_category,
                'post_type' => MeprProduct::$cpt,
            ],
            esc_url(admin_url('edit-tags.php'))
        );
        $category_btn  = wp_kses(
            sprintf(
                '<a href="%1$s" class="page-title-action" target = _blank>%2$s</a>',
                $category_link,
                esc_html__('Categories', 'memberpress')
            ),
            [
                'a' => [
                    'href'   => [],
                    'class'  => [],
                    'target' => [],
                ],
            ]
        );
        ?>
    <script>
      jQuery(document).ready(function($) {
        $('.wrap .wp-header-end').before("<?php echo addslashes($category_btn); ?>");
      });
    </script>
        <?php
    }
}
