<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProduct extends MeprCptModel implements MeprProductInterface
{
    /**
     * Meta key for product price
     *
     * @var string
     */
    public static $price_str                      = '_mepr_product_price';

    /**
     * Meta key for product period
     *
     * @var string
     */
    public static $period_str                     = '_mepr_product_period';

    /**
     * Meta key for product period type
     *
     * @var string
     */
    public static $period_type_str                = '_mepr_product_period_type';

    /**
     * Meta key for signup button text
     *
     * @var string
     */
    public static $signup_button_text_str         = '_mepr_product_signup_button_text';

    /**
     * Meta key for limit cycles
     *
     * @var string
     */
    public static $limit_cycles_str               = '_mepr_product_limit_cycles';

    /**
     * Meta key for limit cycles number
     *
     * @var string
     */
    public static $limit_cycles_num_str           = '_mepr_product_limit_cycles_num';

    /**
     * Meta key for limit cycles action
     *
     * @var string
     */
    public static $limit_cycles_action_str        = '_mepr_product_limit_cycles_action';

    /**
     * Meta key for limit cycles expires after
     *
     * @var string
     */
    public static $limit_cycles_expires_after_str = '_mepr_product_limit_cycles_expires_after';

    /**
     * Meta key for limit cycles expires type
     *
     * @var string
     */
    public static $limit_cycles_expires_type_str  = '_mepr_product_limit_cycles_expires_type';

    /**
     * Meta key for trial
     *
     * @var string
     */
    public static $trial_str                      = '_mepr_product_trial';

    /**
     * Meta key for trial days
     *
     * @var string
     */
    public static $trial_days_str                 = '_mepr_product_trial_days';

    /**
     * Meta key for trial amount
     *
     * @var string
     */
    public static $trial_amount_str               = '_mepr_product_trial_amount';

    /**
     * Meta key for trial once
     *
     * @var string
     */
    public static $trial_once_str                 = '_mepr_product_trial_once';

    /**
     * Meta key for group ID
     *
     * @var string
     */
    public static $group_id_str                   = '_mepr_group_id'; // Only one group at a time dude.

    /**
     * Meta key for group order
     *
     * @var string
     */
    public static $group_order_str                = '_mepr_group_order'; // Position in group.

    /**
     * Meta key for is highlighted
     *
     * @var string
     */
    public static $is_highlighted_str             = '_mepr_product_is_highlighted';

    /**
     * Meta key for who can purchase
     *
     * @var string
     */
    public static $who_can_purchase_str           = '_mepr_product_who_can_purchase';

    /**
     * Meta key for have or had
     *
     * @var string
     */
    public static $have_or_had_str                = '_mepr_product_purchase';

    /**
     * Meta key for pricing title
     *
     * @var string
     */
    public static $pricing_title_str              = '_mepr_product_pricing_title';

    /**
     * Meta key for pricing display
     *
     * @var string
     */
    public static $pricing_display_str            = '_mepr_product_pricing_display';

    /**
     * Meta key for pricing show price
     *
     * @var string
     */
    public static $pricing_show_price_str         = '_mepr_product_pricing_show_price';

    /**
     * Meta key for custom price
     *
     * @var string
     */
    public static $custom_price_str               = '_mepr_product_custom_price';

    /**
     * Meta key for pricing heading text
     *
     * @var string
     */
    public static $pricing_heading_txt_str        = '_mepr_product_pricing_heading_text';

    /**
     * Meta key for pricing footer text
     *
     * @var string
     */
    public static $pricing_footer_txt_str         = '_mepr_product_pricing_footer_text';

    /**
     * Meta key for pricing button text
     *
     * @var string
     */
    public static $pricing_button_txt_str         = '_mepr_product_pricing_button_text';

    /**
     * Meta key for pricing button position
     *
     * @var string
     */
    public static $pricing_button_position_str    = '_mepr_product_pricing_button_position';

    /**
     * Meta key for pricing benefits
     *
     * @var string
     */
    public static $pricing_benefits_str           = '_mepr_product_pricing_benefits';

    /**
     * Meta key for register price action
     *
     * @var string
     */
    public static $register_price_action_str      = '_mepr_register_price_action';

    /**
     * Meta key for register price
     *
     * @var string
     */
    public static $register_price_str             = '_mepr_register_price';

    /**
     * Meta key for thank you page enabled
     *
     * @var string
     */
    public static $thank_you_page_enabled_str     = '_mepr_thank_you_page_enabled';

    /**
     * Meta key for thank you page type
     *
     * @var string
     */
    public static $thank_you_page_type_str        = '_mepr_thank_you_page_type';

    /**
     * Meta key for thank you message
     *
     * @var string
     */
    public static $thank_you_message_str          = '_mepr_product_thank_you_message';

    /**
     * Meta key for thank you page ID
     *
     * @var string
     */
    public static $thank_you_page_id_str          = '_mepr_product_thank_you_page_id';

    /**
     * Meta key for simultaneous subscriptions
     *
     * @var string
     */
    public static $simultaneous_subscriptions_str = '_mepr_allow_simultaneous_subscriptions';

    /**
     * Meta key for use custom template
     *
     * @var string
     */
    public static $use_custom_template_str        = '_mepr_use_custom_template';

    /**
     * Meta key for custom template
     *
     * @var string
     */
    public static $custom_template_str            = '_mepr_custom_template';

    /**
     * Meta key for customize payment methods
     *
     * @var string
     */
    public static $customize_payment_methods_str  = '_mepr_customize_payment_methods';

    /**
     * Meta key for custom payment methods
     *
     * @var string
     */
    public static $custom_payment_methods_str     = '_mepr_custom_payment_methods';

    /**
     * Meta key for customize profile fields
     *
     * @var string
     */
    public static $customize_profile_fields_str   = '_mepr_customize_profile_fields';

    /**
     * Meta key for custom profile fields
     *
     * @var string
     */
    public static $custom_profile_fields_str      = '_mepr_custom_profile_fields';

    /**
     * Meta key for custom login urls enabled
     *
     * @var string
     */
    public static $custom_login_urls_enabled_str  = '_mepr_custom_login_urls_enabled';

    /**
     * Meta key for custom login urls default
     *
     * @var string
     */
    public static $custom_login_urls_default_str  = '_mepr_custom_login_urls_default';

    /**
     * Meta key for custom login urls
     *
     * @var string
     */
    public static $custom_login_urls_str          = '_mepr_custom_login_urls';

    /**
     * Meta key for expire type
     *
     * @var string
     */
    public static $expire_type_str                = '_mepr_expire_type';

    /**
     * Meta key for expire after
     *
     * @var string
     */
    public static $expire_after_str               = '_mepr_expire_after';

    /**
     * Meta key for expire unit
     *
     * @var string
     */
    public static $expire_unit_str                = '_mepr_expire_unit';

    /**
     * Meta key for expire fixed
     *
     * @var string
     */
    public static $expire_fixed_str               = '_mepr_expire_fixed';

    /**
     * Meta key for tax exempt
     *
     * @var string
     */
    public static $tax_exempt_str                 = '_mepr_tax_exempt';

    /**
     * Meta key for tax class
     *
     * @var string
     */
    public static $tax_class_str                  = '_mepr_tax_class';

    /**
     * Meta key for allow renewal
     *
     * @var string
     */
    public static $allow_renewal_str              = '_mepr_allow_renewal';

    /**
     * Meta key for access URL
     *
     * @var string
     */
    public static $access_url_str                 = '_mepr_access_url';

    /**
     * Meta key for emails
     *
     * @var string
     */
    public static $emails_str                     = '_mepr_emails';

    /**
     * Meta key for disable address fields
     *
     * @var string
     */
    public static $disable_address_fields_str     = '_mepr_disable_address_fields'; // For free products mostly.

    /**
     * Meta key for cannot purchase message
     *
     * @var string
     */
    public static $cannot_purchase_message_str    = '_mepr_cannot_purchase_message';

    /**
     * Meta key for plan code
     *
     * @var string
     */
    public static $plan_code_str                  = '_mepr_plan_code';

    /**
     * Meta key for nonce
     *
     * @var string
     */
    public static $nonce_str                      = 'mepr_products_nonce';

    /**
     * Meta key for DB cleanup last run
     *
     * @var string
     */
    public static $last_run_str                   = 'mepr_products_db_cleanup_last_run';

    /**
     * Custom post type name
     *
     * @var string
     */
    public static $cpt                       = 'memberpressproduct';

    /**
     * Taxonomy name for product category
     *
     * @var string
     */
    public static $taxonomy_product_category = 'mepr-product-category';

    /**
     * The period types.
     *
     * @var array
     */
    public $period_types;

    /**
     * The limit cycles actions.
     *
     * @var array
     */
    public $limit_cycles_actions;

    /**
     * The expire units.
     *
     * @var array
     */
    public $expire_units;

    /**
     * The register price actions.
     *
     * @var array
     */
    public $register_price_actions;

    /**
     * The pricing displays.
     *
     * @var array
     */
    public $pricing_displays;

    /**
     * The expire types.
     *
     * @var array
     */
    public $expire_types;

    /**
     * Constructor for the MeprProduct class.
     *
     * @param mixed $obj The object to load.
     */
    public function __construct($obj = null)
    {
        $this->load_cpt(
            $obj,
            self::$cpt,
            [
                'price'                      => 0.00,
                'period'                     => 1,
                'period_type'                => 'lifetime', // Default to lifetime to simplify new membership form.
                'signup_button_text'         => __('Sign Up', 'memberpress'),
                'limit_cycles'               => false,
                'limit_cycles_num'           => 2,
                'limit_cycles_action'        => 'expire',
                'limit_cycles_expires_after' => 1,
                'limit_cycles_expires_type'  => 'days',
                'trial'                      => false,
                'trial_days'                 => 1,
                'trial_amount'               => 0.00,
                'trial_once'                 => true,
                'group_id'                   => 0,
                'group_order'                => 0,
                'is_highlighted'             => false,
                'plan_code'                  => '',
                // The who_can_purchase should be an array of OBJECTS.
                'who_can_purchase'           => [],
                'pricing_title'              => '',
                'pricing_show_price'         => true,
                'pricing_display'            => '',
                'custom_price'               => '',
                'pricing_heading_txt'        => '',
                'pricing_footer_txt'         => '',
                'pricing_button_txt'         => '',
                'pricing_button_position'    => 'footer',
                // Pricing benefits should be an array of strings.
                'pricing_benefits'           => [],
                'register_price_action'      => 'default',
                'register_price'             => '',
                'thank_you_page_enabled'     => false,
                'thank_you_page_type'        => '',
                'thank_you_message'          => '',
                'thank_you_page_id'          => 0,
                'custom_login_urls_enabled'  => false,
                'custom_login_urls_default'  => '',
                // An array of objects ->url and ->count.
                'custom_login_urls'          => [],
                'expire_type'                => 'none',
                'expire_after'               => 1,
                'expire_unit'                => 'days',
                'expire_fixed'               => '',
                'tax_exempt'                 => false,
                'tax_class'                  => 'standard',
                'allow_renewal'              => false,
                'access_url'                 => '',
                'emails'                     => [],
                'disable_address_fields'     => false, // Mostly for free products.
                'simultaneous_subscriptions' => false,
                'use_custom_template'        => false,
                'custom_template'            => '',
                'customize_payment_methods'  => false,
                'custom_payment_methods'     => [],
                'customize_profile_fields'   => false,
                'custom_profile_fields'      => [],
                'cannot_purchase_message'    => _x('You don\'t have access to purchase this item.', 'ui', 'memberpress'),
            ]
        );

        $this->period_types           = ['weeks','months','years','lifetime'];
        $this->limit_cycles_actions   = ['expire','lifetime', 'expires_after'];
        $this->register_price_actions = ['default', 'hidden', 'custom'];
        $this->expire_types           = ['none','delay','fixed'];
        $this->expire_units           = ['days','weeks','months','years'];
        $this->pricing_displays       = ['auto','custom','none'];

        if (empty($this->pricing_display) && isset($this->pricing_show_price)) {
            $this->pricing_display = $this->pricing_show_price ? 'auto' : 'none';
        }
    }

    /**
     * Validate the product's properties.
     *
     * @return void
     */
    public function validate()
    {
        $this->validate_not_empty($this->post_title, 'title');
        $this->validate_is_currency($this->price, 0.00, null, 'price');
        $this->validate_is_numeric($this->period, 1, 12, 'period');
        $this->validate_is_in_array($this->period_type, $this->period_types, 'period_type');
        $this->validate_not_empty($this->signup_button_text, 'signup_button_text');

        $this->validate_is_bool($this->limit_cycles, 'limit_cycles');
        if ($this->limit_cycles) {
            $this->validate_is_numeric($this->limit_cycles_num, 1, null, 'limit_cycles_num');
            $this->validate_is_in_array($this->limit_cycles_action, $this->limit_cycles_actions, 'limit_cycles_action');
        }

        $this->validate_is_bool($this->trial, 'trial');
        if ($this->trial) {
            $this->validate_is_numeric($this->trial_days, 0.00, null, 'trial_days');
            $this->validate_is_currency($this->trial_amount, 0.00, null, 'trial_amount');
            $this->validate_is_bool($this->trial_once, 'trial_once');
        }

        $this->validate_is_numeric($this->group_id, 0, null, 'group_id');
        $this->validate_is_numeric($this->group_order, 0, null, 'group_order');

        $this->validate_is_bool($this->is_highlighted, 'is_highlighted');
        $this->validate_is_array($this->who_can_purchase, 'who_can_purchase');
        // $this->validate_is_bool($this->pricing_show_price, 'pricing_show_price');
        $this->validate_is_in_array($this->pricing_display, $this->pricing_displays, 'pricing_display');

        // No need to validate these at this time
        // 'pricing_title' => '',
        // 'pricing_heading_txt' => '',
        // 'pricing_footer_txt' => '',
        // 'pricing_button_txt' => '',
        // $this->validate_is_array($this->pricing_benefits, 'pricing_benefits');.
        $this->validate_is_in_array($this->register_price_action, $this->register_price_actions, 'register_price_action');
        $this->validate_is_bool($this->thank_you_page_enabled, 'thank_you_page_enabled');

        // No need to validate
        // 'thank_you_message' => ''.
        $this->validate_is_bool($this->custom_login_urls_enabled, 'custom_login_urls_enabled');

        // No need to validate
        // 'custom_login_urls_default' => ''.
        if ($this->custom_login_urls_enabled) {
            $this->validate_is_array($this->custom_login_urls, 'custom_login_urls');
        }

        $this->validate_is_in_array($this->expire_type, $this->expire_types, 'expire_type');
        if ($this->expire_type == 'delay') {
            $this->validate_is_numeric($this->expire_after, 1, null, 'expire_after');
            $this->validate_is_in_array($this->expire_unit, $this->expire_units, 'expire_unit');
        } elseif ($this->expire_type == 'fixed') {
            $this->validate_is_date($this->expire_fixed, 'expire_fixed');
        }

        $this->validate_is_bool($this->tax_exempt, 'tax_exempt');
        $this->validate_is_bool($this->allow_renewal, 'allow_renewal');

        if (!empty($this->access_url)) {
            $this->validate_is_url($this->access_url, 'access_url');
        }

        $this->validate_is_array($this->emails, 'emails');

        $this->validate_is_bool($this->simultaneous_subscriptions, 'simultaneous_subscriptions');

        $this->validate_is_bool($this->use_custom_template, 'use_custom_template');
        if ($this->use_custom_template) {
            $this->validate_not_empty($this->custom_template, 'custom_template');
        }

        $this->validate_is_bool($this->customize_payment_methods, 'customize_payment_methods');
        if ($this->customize_payment_methods) {
            $this->validate_is_array($this->custom_payment_methods, 'custom_payment_methods');
        }

        $this->validate_is_bool($this->customize_profile_fields, 'customize_profile_fields');
        if ($this->customize_profile_fields) {
            $this->validate_is_array($this->custom_profile_fields, 'custom_profile_fields');
        }
    }

    /**
     * Store the product's metadata.
     *
     * @return void
     */
    public function store_meta()
    {
        $id = $this->ID;

        update_post_meta($id, self::$price_str, MeprUtils::format_float($this->price));
        update_post_meta($id, self::$period_str, $this->period);
        update_post_meta($id, self::$period_type_str, $this->period_type);
        update_post_meta($id, self::$signup_button_text_str, $this->signup_button_text);
        update_post_meta($id, self::$limit_cycles_str, $this->limit_cycles);
        update_post_meta($id, self::$limit_cycles_num_str, $this->limit_cycles_num);
        update_post_meta($id, self::$limit_cycles_action_str, $this->limit_cycles_action);
        update_post_meta($id, self::$limit_cycles_expires_after_str, $this->limit_cycles_expires_after);
        update_post_meta($id, self::$limit_cycles_expires_type_str, $this->limit_cycles_expires_type);
        update_post_meta($id, self::$trial_str, $this->trial);
        update_post_meta($id, self::$trial_days_str, $this->trial_days);
        update_post_meta($id, self::$trial_amount_str, $this->trial_amount);
        update_post_meta($id, self::$trial_once_str, $this->trial_once);
        update_post_meta($id, self::$group_id_str, $this->group_id);
        update_post_meta($id, self::$group_order_str, $this->group_order);
        update_post_meta($id, self::$who_can_purchase_str, $this->who_can_purchase);
        update_post_meta($id, self::$is_highlighted_str, $this->is_highlighted);
        update_post_meta($id, self::$pricing_title_str, $this->pricing_title);
        update_post_meta($id, self::$pricing_display_str, $this->pricing_display);
        update_post_meta($id, self::$custom_price_str, $this->custom_price);
        update_post_meta($id, self::$pricing_heading_txt_str, $this->pricing_heading_txt);
        update_post_meta($id, self::$pricing_footer_txt_str, $this->pricing_footer_txt);
        update_post_meta($id, self::$pricing_button_txt_str, $this->pricing_button_txt);
        update_post_meta($id, self::$pricing_button_position_str, $this->pricing_button_position);
        update_post_meta($id, self::$pricing_benefits_str, $this->pricing_benefits);
        update_post_meta($id, self::$register_price_action_str, $this->register_price_action);
        update_post_meta($id, self::$register_price_str, $this->register_price);
        update_post_meta($id, self::$thank_you_page_enabled_str, $this->thank_you_page_enabled);
        update_post_meta($id, self::$thank_you_page_type_str, $this->thank_you_page_type);
        update_post_meta($id, self::$thank_you_message_str, $this->thank_you_message);
        update_post_meta($id, self::$thank_you_page_id_str, $this->thank_you_page_id);
        update_post_meta($id, self::$custom_login_urls_enabled_str, $this->custom_login_urls_enabled);
        update_post_meta($id, self::$custom_login_urls_default_str, $this->custom_login_urls_default);
        update_post_meta($id, self::$custom_login_urls_str, $this->custom_login_urls);
        update_post_meta($id, self::$expire_type_str, $this->expire_type);
        update_post_meta($id, self::$expire_after_str, $this->expire_after);
        update_post_meta($id, self::$expire_unit_str, $this->expire_unit);
        update_post_meta($id, self::$expire_fixed_str, $this->expire_fixed);
        update_post_meta($id, self::$tax_exempt_str, $this->tax_exempt);
        update_post_meta($id, self::$tax_class_str, $this->tax_class);
        update_post_meta($id, self::$allow_renewal_str, $this->allow_renewal);
        update_post_meta($id, self::$access_url_str, $this->access_url);
        update_post_meta($id, self::$emails_str, $this->emails);
        update_post_meta($id, self::$disable_address_fields_str, $this->disable_address_fields); // Mostly for free products.
        update_post_meta($id, self::$simultaneous_subscriptions_str, $this->simultaneous_subscriptions);
        update_post_meta($id, self::$use_custom_template_str, $this->use_custom_template);
        update_post_meta($id, self::$custom_template_str, $this->custom_template);
        update_post_meta($id, self::$customize_payment_methods_str, $this->customize_payment_methods);
        update_post_meta($id, self::$customize_profile_fields_str, $this->customize_profile_fields);
        update_post_meta($id, self::$cannot_purchase_message_str, $this->cannot_purchase_message);
        update_post_meta($id, self::$plan_code_str, $this->plan_code);

        if ($this->customize_payment_methods) {
            update_post_meta($id, self::$custom_payment_methods_str, $this->custom_payment_methods);
        } else {
            delete_post_meta($id, self::$custom_payment_methods_str);
        }

        if ($this->customize_profile_fields) {
            update_post_meta($id, self::$custom_profile_fields_str, $this->custom_profile_fields);
        } else {
            delete_post_meta($id, self::$custom_profile_fields_str);
        }
    }

    /**
     * Check if the product is prorated.
     *
     * @return boolean
     */
    public function is_prorated()
    {
        $mepr_options = MeprOptions::fetch();
        return($mepr_options->pro_rated_upgrades and $this->is_upgrade_or_downgrade());
    }

    /**
     * Whether this product is tax exempt
     *
     * @return boolean
     */
    public function is_tax_exempt()
    {
        return (bool) $this->tax_exempt;
    }

    /**
     * Get a single product by ID.
     *
     * @param  integer $id The product ID.
     * @return MeprProduct|false
     */
    public static function get_one($id)
    {
        $post = get_post($id);

        if (is_null($post)) {
            return false;
        } else {
            return new MeprProduct($post->ID);
        }
    }

    /**
     * Get all products.
     *
     * @return MeprProduct[]
     */
    public static function get_all()
    {
        global $wpdb;

        $q = $wpdb->prepare(
            "
        SELECT ID
          FROM {$wpdb->posts}
         WHERE post_type=%s
           AND post_status=%s
      ",
            self::$cpt,
            'publish'
        );

        $ids = $wpdb->get_col($q);

        $memberships = [];
        foreach ($ids as $id) {
            $memberships[] = new MeprProduct($id);
        }

        return $memberships;
    }

    /**
     * Get the total number of published products
     *
     * @return integer
     */
    public static function count()
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            self::$cpt
        );

        return (int) $wpdb->get_var($query);
    }

    /**
     * This presents the price as a float, based on the information contained in
     * $this, the user_id and $coupon_code passed to it.
     *
     * If a user_id and a coupon code is present just adjust the price based on
     * the user first (if any) and then apply the coupon to the remaining price.
     *
     * Coupon code needs to be validated using MeprCoupon::is_valid_coupon_code()
     * before passing a code to this method
     *
     * @param  string|null $coupon_code The coupon code.
     * @param  boolean     $prorate     Whether to prorate the price.
     * @return float
     */
    public function adjusted_price($coupon_code = null, $prorate = true)
    {
        $current_user  = MeprUtils::get_currentuserinfo();
        $product_price = $this->price;
        $mepr_options  = MeprOptions::fetch();

        // Note to future self, we do not want to validate the coupon
        // here as it causes major issues if the coupon has expired
        // or has reached its usage count max. See notes above this method.
        if (!empty($coupon_code)) {
            $coupon = MeprCoupon::get_one_from_code($coupon_code);

            if ($coupon !== false) {
                $product_price = $coupon->apply_discount($product_price, $this->is_one_time_payment(), $this);
            }
        }

        if (!empty($_REQUEST['ca']) || !empty($_REQUEST['mpca_corporate_account_id'])) {
            // Signup is a Corporate Account validated in validate_ca_signup and associate_sub_account.
            $product_price = 0.00;
        } elseif ($prorate && $this->is_one_time_payment() && $this->is_prorated()) {
            $grp = $this->group();

            // Calculate days in this new period.
            $days_in_new_period = $this->days_in_my_period();

            $old_sub = $current_user->subscription_in_group($grp->ID);
            if ($old_sub) {
                $lt = $old_sub->latest_txn();

                if ($lt != false && $lt instanceof MeprTransaction && $lt->id > 0) {
                    $r = MeprUtils::calculate_proration(
                        $lt->amount,
                        $product_price,
                        $old_sub->days_in_this_period(),
                        $days_in_new_period,
                        $old_sub->days_till_expiration(),
                        $grp->upgrade_path_reset_period,
                        $old_sub
                    );

                      // Don't update this below if the latest payment was for 0.00.
                    if (MeprUtils::format_float($lt->amount) > 0.00) {
                        $product_price = $r->proration;
                    }
                }
            } else {
                $txn = $current_user->lifetime_subscription_in_group($grp->ID);
                if ($txn && MeprUtils::format_float($txn->amount) > 0.00) {
                    // Don't update this below if the latest payment was for 0.00.
                    $r = MeprUtils::calculate_proration(
                        $txn->amount,
                        $product_price,
                        $txn->days_in_this_period(),
                        $days_in_new_period,
                        $txn->days_till_expiration(),
                        $grp->upgrade_path_reset_period
                    );

                    $product_price = $r->proration;
                }
            }
        }

        $product_price = MeprHooks::apply_filters('mepr_adjusted_price', $product_price, $coupon_code, $this);

        return MeprUtils::format_float($product_price);
    }

    /**
     * Check if payment is required for the product.
     *
     * @param  string|null $coupon_code The coupon code.
     * @return boolean
     */
    public function is_payment_required($coupon_code = null)
    {
        if ($coupon_code && !MeprCoupon::is_valid_coupon_code($coupon_code, $this->ID)) {
            $coupon_code = null;
        }

        return $this->adjusted_price($coupon_code) > 0.00;
    }

    /**
     * Get the number of days in the product's period.
     *
     * @param  string $default The default value if no period is set.
     * @return integer|string
     */
    public function days_in_my_period($default = 'lifetime')
    {
        $now            = time();
        $new_expires_at = $this->get_expires_at($now);

        if (is_null($new_expires_at)) {
            return $default;
        }

        $future_date  = new DateTime(gmdate('Y-m-d', $new_expires_at));
        $current_date = new DateTime(gmdate('Y-m-d', $now));
        $timediff     = $current_date->diff($future_date);

        return $timediff->days; // # of days difference
    }

    /**
     * Gets the value for 'expires_at' for the given created_at time for this membership.
     *
     * @param  integer|null $created_at The creation timestamp.
     * @param  boolean      $check_user Whether to check the user for renewals.
     * @return integer|null
     */
    public function get_expires_at($created_at = null, $check_user = true)
    {
        $mepr_options = MeprOptions::fetch();

        if (is_null($created_at)) {
            $created_at = time();
        }

        $user       = MeprUtils::get_currentuserinfo();
        $expires_at = $created_at;
        $period     = $this->period;

        switch ($this->period_type) {
            case 'days':
                $expires_at += MeprUtils::days($period) + MeprUtils::days($mepr_options->grace_expire_days);
                break;
            case 'weeks':
                $expires_at += MeprUtils::weeks($period) + MeprUtils::days($mepr_options->grace_expire_days);
                break;
            case 'months':
                $expires_at += MeprUtils::months($period, $created_at) + MeprUtils::days($mepr_options->grace_expire_days);
                break;
            case 'years':
                $expires_at += MeprUtils::years($period, $created_at) + MeprUtils::days($mepr_options->grace_expire_days);
                break;
            default: // One-time payment.
                if ($this->expire_type == 'delay') {
                    if ($check_user && MeprUtils::is_user_logged_in()) {
                        // Handle renewals.
                        if ($this->is_renewal()) {
                            $last_txn = $this->get_last_active_txn($user->ID);
                            if ($last_txn) {
                                $expires_at = $created_at = strtotime($last_txn->expires_at);
                            }
                        }
                    }

                    switch ($this->expire_unit) {
                        case 'days':
                            $expires_at += MeprUtils::days($this->expire_after);
                            break;
                        case 'weeks':
                            $expires_at += MeprUtils::weeks($this->expire_after);
                            break;
                        case 'months':
                            $expires_at += MeprUtils::months($this->expire_after, $created_at);
                            break;
                        case 'years':
                            $expires_at += MeprUtils::years($this->expire_after, $created_at);
                    }
                } elseif ($this->expire_type == 'fixed') {
                    $expires_at = strtotime($this->expire_fixed);
                    $now        = time();
                    // Make sure we adjust the year if the membership is a renewable type and the user forgot to bump up the year.
                    if ($this->allow_renewal) {
                        while ($now > $expires_at) {
                            $expires_at += MeprUtils::years(1);
                        }
                    }
                    // Actually handle renewals.
                    if ($check_user && $this->is_renewal()) {
                        $last_txn = $this->get_last_active_txn($user->ID);
                        if ($last_txn) {
                            $expires_at_date = date_create($last_txn->expires_at, new DateTimeZone('UTC'));

                            if ($expires_at_date instanceof DateTime) {
                                $expires_at_date->modify('+1 year');
                                $expires_at = $expires_at_date->format('U');
                            } else {
                                $expires_at = strtotime($last_txn->expires_at) + MeprUtils::years(1);
                            }
                        }
                    }
                } else { // Lifetime.
                    $expires_at = null;
                }
        }

        return $expires_at;
    }

    /**
     * Get the pricing page product IDs.
     *
     * @return array
     */
    public static function get_pricing_page_product_ids()
    {
        global $wpdb;

        $q = "SELECT p.ID, p.menu_order
            FROM {$wpdb->postmeta} AS m INNER JOIN {$wpdb->posts} AS p
              ON p.ID = m.post_id
            WHERE m.meta_key = %s
              AND m.meta_value = 1
          ORDER BY p.menu_order, p.ID";

        return $wpdb->get_col($wpdb->prepare($q, self::$show_on_pricing_str));
    }

    /**
     * Check if the product is a one-time payment.
     *
     * @return boolean
     */
    public function is_one_time_payment()
    {
        return MeprHooks::apply_filters('mepr_product_is_one_time_payment', $this->period_type == 'lifetime' || $this->price == 0.00);
    }

    /**
     * Check if the membership type is renewable.
     *
     * @return boolean
     */
    public function is_renewable()
    {
        return (($this->expire_type == 'delay' || $this->expire_type == 'fixed') && $this->allow_renewal);
    }

    /**
     * Check if the product is a renewal.
     *
     * @return boolean
     */
    public function is_renewal()
    {
        global $user_ID;

        if (MeprUtils::is_user_logged_in()) {
            $user = new MeprUser($user_ID);
        }

        return (MeprUtils::is_user_logged_in() &&
            $user->is_already_subscribed_to($this->ID) &&
            ($this->expire_type == 'delay' || $this->expire_type == 'fixed') &&
            $this->allow_renewal);
    }

    /**
     * Can this product be purchased?
     *
     * @return boolean
     */
    public function can_you_buy_me()
    {
        $override = MeprHooks::apply_filters('mepr-can-you-buy-me-override', null, $this);
        if (!is_null($override)) {
            return $override;
        }

        // Admins can see & purchase anything.
        if (MeprUtils::is_logged_in_and_an_admin()) {
            return true;
        }

        $user = MeprUtils::get_currentuserinfo();

        // Make sure user hasn't already subscribed to this membership first.
        if (
            $user instanceof MeprUser &&
            $user->is_already_subscribed_to($this->ID) &&
            !$this->simultaneous_subscriptions &&
            !$this->allow_renewal
        ) {
            return false;
        }

        if (empty($this->who_can_purchase)) {
            return true; // No rules exist so everyone can purchase.
        }

        // Do not allow upgrades/downgrades during a pro-rated trial period from a previous upgrade/downgrade.
        if ($user instanceof MeprUser) {
            $group = $this->group();
            if ($group !== false) {
                $sub_in_group = $user->subscription_in_group($group->ID);
                if ($sub_in_group !== false && $sub_in_group->prorated_trial && $sub_in_group->in_trial()) {
                    return MeprHooks::apply_filters('mepr-allow-multiple-upgrades-downgrades', false, $user, $sub_in_group, $this);
                }
            }
        }

        foreach ($this->who_can_purchase as $who) {
            // Give Developers a chance to hook in here
            // Return true or false if you run your own custom handling here
            // Otherwise return string 'no_custom' if MemberPress should handle the processing.
            $custom = MeprHooks::apply_filters('mepr-who-can-purchase-custom-check', 'no_custom', $who, $this);

            if ($custom !== 'no_custom' && is_bool($custom)) {
                return $custom;
            }

            if ($who->user_type == 'disabled') {
                return false;
            }

            if ($who->user_type == 'everyone') {
                return true;
            }

            if ($who->user_type == 'guests' && !MeprUtils::is_user_logged_in()) {
                return true; // If not a logged in member they can purchase.
            }

            if ($who->user_type == 'members' && MeprUtils::is_user_logged_in()) {
                if ($user instanceof MeprUser && $user->can_user_purchase($who, $this->ID)) {
                    return true;
                }
            }
        }

        return false; // If we make it here, nothing applied so let's return false.
    }

    // This is really only used for annual renewals currently
    // It has some special code for fallback transactions (Downgrade Path feature of Groups)
    // So be careful when using this function if you need to includ fallback transactions.
    /**
     * Get the last active transaction for a user.
     *
     * @param  integer $user_id The user ID.
     * @return MeprTransaction|false
     */
    public function get_last_active_txn($user_id)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $q = "SELECT tr.id AS id
            FROM {$mepr_db->transactions} AS tr
           WHERE tr.user_id=%d
             AND tr.product_id=%d
             AND tr.status=%s
             AND tr.expires_at > %s
           ORDER BY tr.created_at DESC
           LIMIT 1";

        $lq = "SELECT tr.id AS id
            FROM {$mepr_db->transactions} AS tr
           WHERE tr.user_id=%d
             AND tr.product_id=%d
             AND tr.status=%s
             AND tr.expires_at=%s
             AND tr.txn_type <> %s
           ORDER BY tr.created_at DESC
           LIMIT 1";

        $q  = $wpdb->prepare($q, $user_id, $this->ID, MeprTransaction::$complete_str, MeprUtils::db_now());
        $lq = $wpdb->prepare($lq, $user_id, $this->ID, MeprTransaction::$complete_str, MeprUtils::db_lifetime(), MeprTransaction::$fallback_str);

        $txn_id = $wpdb->get_var($lq);
        if ($txn_id) {
            // Try for lifetimes.
            return new MeprTransaction($txn_id);
        }

        $txn_id = $wpdb->get_var($q);
        if ($txn_id) {
            // Try for expiring.
            return new MeprTransaction($txn_id);
        }

        // TODO: Maybe throw an exception here at some point.
        return false;
    }

    /**
     * Get the group associated with the product.
     *
     * @return MeprGroup|false
     */
    public function group()
    {
        // Don't do static caching stuff here.
        if (!isset($this->group_id) or empty($this->group_id)) {
            return false;
        }

        return new MeprGroup($this->group_id);
    }

    /**
     * Get the URL for the product's group.
     *
     * @return string
     */
    public function group_url()
    {
        $grp = $this->group();
        if ($grp && !$grp->pricing_page_disabled) {
            return $grp->url();
        }

        return $this->url();
    }

    /**
     * Determines if this is a membership upgrade.
     *
     * @return boolean
     */
    public function is_upgrade()
    {
        return $this->is_upgrade_or_downgrade('upgrade');
    }

    /**
     * Determines if this is a membership downgrade.
     *
     * @return boolean
     */
    public function is_downgrade()
    {
        return $this->is_upgrade_or_downgrade('downgrade');
    }

    /**
     * Determines if this is a membership upgrade for a certain user.
     *
     * @param  integer $user_id The user ID.
     * @return boolean
     */
    public function is_upgrade_for($user_id)
    {
        return $this->is_upgrade_or_downgrade_for($user_id, 'upgrade');
    }

    /**
     * Determines if this is a membership downgrade for a certain user.
     *
     * @param  integer $user_id The user ID.
     * @return boolean
     */
    public function is_downgrade_for($user_id)
    {
        return $this->is_upgrade_or_downgrade_for($user_id, 'downgrade');
    }

    /**
     * Check if the product is an upgrade or downgrade.
     *
     * @param  string|false   $type The type of change ('upgrade' or 'downgrade').
     * @param  MeprUser|false $usr  The user object.
     * @return boolean
     */
    public function is_upgrade_or_downgrade($type = false, $usr = false)
    {
        if ($usr === false) {
            $usr = MeprUtils::get_currentuserinfo();
        }
        return ($usr && $this->is_upgrade_or_downgrade_for($usr->ID, $type)); // Must be an upgrade/downgrade for the user.
    }

    /**
     * Determines if this is a membership upgrade or downgrade for a certain user.
     *
     * @param  integer      $user_id The user ID.
     * @param  string|false $type    The type of change ('upgrade' or 'downgrade').
     * @return boolean
     */
    public function is_upgrade_or_downgrade_for($user_id, $type = false)
    {
        $usr = new MeprUser($user_id);
        $grp = $this->group();

        // Not part of a group ... not an upgrade.
        if (!$grp) {
            return false;
        }

        // No upgrade path here ... not an upgrade.
        if (!$grp->is_upgrade_path) {
            return false;
        }

        $prds = $usr->active_product_subscriptions('products', true);

        if (!empty($prds)) {
            foreach ($prds as $p) {
                $g = $p->group();
                if (
                    $g && $g instanceof MeprGroup &&
                    $g->ID == $grp->ID && $this->ID != $p->ID
                ) {
                    if ($type === false) {
                        return true;
                    } elseif ($type == 'upgrade') {
                        return $this->group_order > $p->group_order;
                    } elseif ($type == 'downgrade') {
                        return $this->group_order < $p->group_order;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Clean up the database for products.
     *
     * @return void
     */
    public static function cleanup_db()
    {
        global $wpdb;
        $date     = time();
        $last_run = get_option(self::$last_run_str, 0); // Prevents all this code from executing on every page load.

        if (($date - $last_run) > 86400) { // Runs once at most once a day.
            update_option(self::$last_run_str, $date);
            $sq1     = "SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = '" . self::$cpt . "' AND
                      post_status = 'auto-draft'";
            $sq1_res = $wpdb->get_col($sq1);
            if (!empty($sq1_res)) {
                $post_ids = implode(',', $sq1_res);
                $q1       = "DELETE
                  FROM {$wpdb->postmeta}
                  WHERE post_id IN ({$post_ids})";
                $q2       = "DELETE
                  FROM {$wpdb->posts}
                  WHERE post_type = '" . self::$cpt . "' AND
                        post_status = 'auto-draft'";

                $wpdb->query($q1);
                $wpdb->query($q2);
            }
        }
    }

    /**
     * Get the page template for the product.
     *
     * @return string|null
     */
    public function get_page_template()
    {
        if ($this->use_custom_template) {
            return locate_template($this->custom_template);
        }

        return null;
    }

    /*
     * Defines the template hierarchy search path for member core products.
     * Currently unused method that would specify template file lookup order.
     *
        public static function template_search_path() {
            return array( 'page_memberpressproduct.php',
                      'single-memberpressproduct.php',
                      'page.php',
                      'custom_template.php',
                      'single.php',
                      'index.php' );
        }
     */

    /**
     * Get the payment methods for the product.
     *
     * @return array
     */
    public function payment_methods()
    {
        $mepr_options = MeprOptions::fetch();

        $pms = $mepr_options->payment_methods();

        unset($pms['free']);
        unset($pms['manual']);

        $pmkeys = array_keys($pms);

        if (
            isset($this->custom_payment_methods) and
            !is_null($this->custom_payment_methods) and
            is_array($this->custom_payment_methods)
        ) {
            return array_intersect($this->custom_payment_methods, $pmkeys);
        }

        return $pmkeys;
    }

    /**
     * Get the edit URL for the product.
     *
     * @param  string $args Additional URL arguments.
     * @return string
     */
    public function edit_url($args = '')
    {
        if (isset($this->ID) && $this->post_type == self::$cpt) {
            return get_edit_post_link($this->ID);
        } else {
            return '';
        }
    }

    /**
     * Get the URL for the product.
     *
     * @param  string  $args            Additional URL arguments.
     * @param  boolean $modify_if_https Whether to modify the URL if HTTPS is used.
     * @return string
     */
    public function url($args = '', $modify_if_https = false)
    {
        if (isset($this->ID)) {
            $url = MeprUtils::get_permalink($this->ID) . $args;

            if (MeprUtils::is_ssl() && $modify_if_https) {
                $url = preg_replace('!^http:!', 'https:', $url);
            }
            $url = MeprHooks::apply_filters('mepr-product-url', $url, $this, $args, $modify_if_https);

            return $url;
        } else {
            return '';
        }
    }

    /**
     * Check if the product has a manual signup form.
     *
     * @return boolean
     */
    public function manual_append_signup()
    {
        return preg_match('~\[\s*mepr-(product|membership)-registration-form\s*\]~', $this->post_content);
    }

    /**
     * Get the custom profile fields for the product.
     *
     * @return array
     */
    public function custom_profile_fields()
    {
        $mepr_options = MeprOptions::fetch();
        $fields       = [];

        if (!$this->customize_profile_fields) {
            return $mepr_options->custom_fields;
        }

        foreach ($mepr_options->custom_fields as $row) {
            if (in_array($row->field_key, $this->custom_profile_fields)) {
                $fields[] = $row;
            }
        }

        return $fields;
    }

    /**
     * This function is to be used to determine if a trial should be allowed.
     * The current idea here is that if the user has
     *
     * @return boolean
     */
    public function trial_is_expired()
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        if ($this->trial && MeprUtils::is_user_logged_in()) {
            $current_user = MeprUtils::get_currentuserinfo();
            if ($current_user) {
                $q = $wpdb->prepare(
                    "
                  SELECT COUNT(*)
                    FROM {$mepr_db->transactions} AS t
                   WHERE t.user_id=%d
                     AND t.product_id=%d
                     AND t.txn_type IN (%s,%s)
                     AND t.status IN (%s,%s,%s)
                ",
                    $current_user->ID,
                    $this->ID,
                    MeprTransaction::$subscription_confirmation_str,
                    MeprTransaction::$payment_str,
                    MeprTransaction::$complete_str,
                    MeprTransaction::$refunded_str,
                    MeprTransaction::$confirmed_str
                );

                $already_trialled = $wpdb->get_var($q);

                return ($already_trialled > 0);
            }
        }

        return false;
    }

    /**
     * Check if a post is a product page.
     *
     * @param  WP_Post $post The post object.
     * @return MeprProduct|false
     */
    public static function is_product_page($post)
    {
        $return = false;

        if (is_object($post) && property_exists($post, 'post_type') && $post->post_type == MeprProduct::$cpt) {
            $prd    = new MeprProduct($post->ID);
            $return = $prd;
        } elseif (
            is_object($post) && preg_match(
                '~\[mepr-(product|membership)-registration-form\s+(product_)?id=[\"\\\'](\d+)[\"\\\']~',
                $post->post_content,
                $m
            )
        ) {
            if (isset($m[1])) {
                $prd    = new MeprProduct($m[1]);
                $return = $prd;
            }
        }

        return MeprHooks::apply_filters('mepr-is-product-page', $return, $post);
    }

    /**
     * Get the highest menu order active membership by user.
     *
     * @param  integer $user_id The user ID.
     * @return integer|false
     */
    public static function get_highest_menu_order_active_membership_by_user($user_id)
    {
        global $wpdb;

        $user = new MeprUser($user_id);

        if ((int)$user->ID === 0) {
            return false;
        }

        $active_memberships = array_unique($user->active_product_subscriptions('ids'), true);

        if (empty($active_memberships)) {
            return false;
        }

        $in = '%d';
        if (count($active_memberships) > 1) {
            $placeholders = array_fill(0, count($active_memberships), '%d');
            $in           = implode(',', $placeholders); // Convert to comma separated string if > 1.
        }

        $q = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID IN({$in}) ORDER BY menu_order DESC, ID DESC LIMIT 1", $active_memberships);

        $result = $wpdb->get_var($q);

        return ( ! is_null($result) ) ? $result : false;
    }

    /**
     * Get the Stripe Product ID
     *
     * @param  string $gateway_id The gateway ID.
     * @return string|false
     */
    public function get_stripe_product_id($gateway_id)
    {
        return get_post_meta($this->ID, '_mepr_stripe_product_id_' . $gateway_id, true);
    }

    /**
     * Get the Stripe Initial payment product ID
     *
     * @param  string $gateway_id The gateway ID.
     * @return string|false
     */
    public function get_stripe_initial_payment_product_id($gateway_id)
    {
        return get_post_meta($this->ID, '_mepr_stripe_initial_payment_product_id_' . $gateway_id, true);
    }

    /**
     * Set the Stripe Product ID
     *
     * @param string $gateway_id The gateway ID.
     * @param string $product_id The Stripe Product ID.
     */
    public function set_stripe_product_id($gateway_id, $product_id)
    {
        update_post_meta($this->ID, '_mepr_stripe_product_id_' . $gateway_id, $product_id);
    }

    /**
     * Set the Stripe Initial Payment Product ID
     *
     * @param string $gateway_id The gateway ID.
     * @param string $product_id The Stripe Product ID.
     */
    public function set_stripe_initial_payment_product_id($gateway_id, $product_id)
    {
        update_post_meta($this->ID, '_mepr_stripe_initial_payment_product_id_' . $gateway_id, $product_id);
    }

    /**
     * Get the Stripe Plan ID for this product's current payment terms
     *
     * @param  string $gateway_id The gateway ID.
     * @param  string $amount     The payment amount (excluding tax).
     * @return string|false
     */
    public function get_stripe_plan_id($gateway_id, $amount)
    {
        $meta_key = sprintf('_mepr_stripe_plan_id_%s_%s', $gateway_id, $this->terms_hash($amount));

        $plan_id = get_post_meta($this->ID, $meta_key, true);

        return MeprHooks::apply_filters('mepr-product-get-stripe-plan-id', $plan_id, $this, $gateway_id, $amount);
    }

    /**
     * Set the Stripe Plan ID for this product's current payment terms
     *
     * @param string $gateway_id The gateway ID.
     * @param string $amount     The payment amount (excluding tax).
     * @param string $plan_id    The Stripe Plan ID.
     */
    public function set_stripe_plan_id($gateway_id, $amount, $plan_id)
    {
        $meta_key = sprintf('_mepr_stripe_plan_id_%s_%s', $gateway_id, $this->terms_hash($amount));

        update_post_meta($this->ID, $meta_key, $plan_id);
    }

    /**
     * Get the hash of the payment terms
     *
     * If this hash changes then a different Stripe Plan will be created.
     *
     * @param  string $amount The payment amount.
     * @return string
     */
    private function terms_hash($amount)
    {
        $mepr_options = MeprOptions::fetch();

        $terms = [
            'currency'    => $mepr_options->currency_code,
            'amount'      => $amount,
            'period'      => $this->period,
            'period_type' => $this->period_type,
        ];

        return md5(serialize($terms));
    }

    /**
     * Delete the Stripe Product ID
     *
     * @param string $gateway_id The gateway ID.
     * @param string $product_id The Stripe Product ID.
     */
    public static function delete_stripe_product_id($gateway_id, $product_id)
    {
        if (is_string($product_id) && $product_id !== '') {
            delete_metadata('post', null, '_mepr_stripe_product_id_' . $gateway_id, $product_id, true);
        }
    }

    /**
     * Delete the Stripe Plan ID
     *
     * @param string $gateway_id The gateway ID.
     * @param string $plan_id    The Stripe Plan ID.
     */
    public static function delete_stripe_plan_id($gateway_id, $plan_id)
    {
        if (!is_string($plan_id) || $plan_id === '') {
            return;
        }

        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value = %s",
            $wpdb->esc_like('_mepr_stripe_plan_id_' . $gateway_id) . '%',
            $plan_id
        );

        $meta_ids = $wpdb->get_col($query);

        if (is_array($meta_ids) && count($meta_ids)) {
            foreach ($meta_ids as $meta_id) {
                delete_metadata_by_mid('post', $meta_id);
            }
        }
    }

    /**
     * Get the array of order bumps chosen for this product
     *
     * @return MeprProduct[]
     */
    public function get_order_bumps()
    {
        $product_ids = get_post_meta($this->ID, '_mepr_order_bumps', true);
        $order_bumps = [];

        if (is_array($product_ids)) {
            foreach ($product_ids as $product_id) {
                $product = new MeprProduct((int) $product_id);

                if ($product->ID > 0) {
                    $order_bumps[] = $product;
                }
            }
        }

        return $order_bumps;
    }

    /**
     * Get the array of order bumps chosen for this product marked as required.
     *
     * @return int[]
     */
    public function get_required_order_bumps()
    {
        $product_ids = get_post_meta($this->ID, '_mepr_order_bumps_required', true);
        $order_bumps = [];
        if (!is_array($product_ids)) {
            return $order_bumps;
        }

        foreach ($product_ids as $product_id) {
            $product = new MeprProduct((int) $product_id);
            if ($product->ID > 0) {
                $order_bumps[] = $product->ID;
            }
        }

        return $order_bumps;
    }
}
