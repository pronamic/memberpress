<?php

/**
 * Easy Affiliate Integration Class
 */

use EasyAffiliate\Lib\Cookie;
use EasyAffiliate\Lib\Track;
use EasyAffiliate\Lib\Utils;
use EasyAffiliate\Models\Options;
use EasyAffiliate\Models\Transaction;
use EasyAffiliate\Models\User;

/**
 * This is a special controller that handles all of the MemberPress specific
 * public static functions for the Easy Affiliate.
 */
class MeprEasyAffiliateIntegration
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_hooks();
    }

    /**
     * Get the instance of the class
     *
     * @return object The instance of the class.
     */
    public static function instance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Load the hooks
     */
    public function load_hooks()
    {
        static $loaded = false;
        if ($loaded) {
            // Hooks already loaded. Do nothing.
            return;
        }
        $loaded = true;

        $options = Options::fetch();

        add_filter('esaf-config-integrations', [self::class, 'ea_integrations']);

        if (!in_array('memberpress', $options->integration, true)) {
            return;
        }

        add_action('mepr_event_subscription_created', [self::class, 'add_subscription_meta']);
        add_action('mepr_txn_status_pending', [self::class, 'add_transaction_meta']);
        add_action('mepr_txn_status_complete', [self::class, 'track_transaction']);
        add_action('mepr_txn_status_refunded', [self::class, 'refund_transaction']);

        // MemberPress Product Group Commission meta box integration.
        add_action('mepr_product_meta_boxes', [self::class, 'product_meta_boxes']);
        add_action('mepr_product_save_meta', [self::class, 'save_product']);

        // MemberPress Product Group Commission calculations.
        add_filter('esaf_commission_percentages', [self::class, 'commission_percentages'], 10, 2);
        add_filter('esaf_commission_type', [self::class, 'commission_type'], 10, 2);
        add_filter('esaf_commission_source', [self::class, 'commission_source'], 10, 2);
        add_filter('esaf_subscription_commissions', [self::class, 'subscription_commissions'], 10, 2);

        // Affiliate Based Coupons.
        add_action('mepr_coupon_meta_boxes', [self::class, 'coupon_meta_boxes']);
        add_action('mepr_coupon_save_meta', [self::class, 'save_coupon']);
        add_action('mepr_coupon_admin_enqueue_script', [self::class, 'enqueue_coupon_scripts']);
        add_filter('esaf_dashboard_coupon_count', [self::class, 'coupon_count']);
        add_action('esaf_creatives_coupons', [self::class, 'display_my_coupons']);
        add_action('user_register', [self::class, 'save_coupon_referrer'], 20);

        // Link the EA Transaction to the MemberPress Transaction.
        add_filter('esaf_transaction_source_label', [self::class, 'transaction_source_label'], 10, 2);
    }

    /**
     * Add MemberPress integration to Easy Affiliate
     * via the `esaf-config-integrations` filter.
     *
     * @wp-hook esaf-config-integrations
     *
     * @param  array $integrations The integrations array.
     * @return array
     */
    public static function ea_integrations($integrations)
    {
        $integrations['memberpress'] = [
            'label'      => __('MemberPress', 'memberpress'),
            'deprecated' => false,
            'detectable' => true,
            'controller' => '\\MeprEasyAffiliateIntegration',
        ];
        return $integrations;
    }

    /**
     * Check if the MemberPress plugin is active
     *
     * @return boolean Always returns true.
     */
    public static function is_plugin_active()
    {
        return true;
    }

    /**
     * Add subscription meta to the subscription
     *
     * @wp-hook mepr-event-subscription-created
     *
     * @param  MeprEvent $event The event object.
     * @return void
     */
    public static function add_subscription_meta($event)
    {
        $sub          = $event->get_data();
        $affiliate_id = Cookie::get_affiliate_id();
        $click_id     = Cookie::get_click_id();

        if ($sub instanceof MeprSubscription && !MeprUtils::is_logged_in_and_an_admin()) {
            // Override the affiliate if a coupon was used that is associated with an affiliate.
            $coupon = $sub->coupon();
            if ($coupon instanceof MeprCoupon) {
                $enabled = (isset($coupon->ID) && $coupon->ID) ? get_post_meta($coupon->ID, 'wafp_coupon_affiliate_enabled', true) : false;

                $coupon_affiliate_id = get_post_meta($coupon->ID, 'wafp_coupon_affiliate', true);
                if ($enabled && $coupon_affiliate_id) {
                    $affiliate_id = (int) $coupon_affiliate_id;
                    $click_id     = 0;
                }
            }

            if ($affiliate_id > 0) {
                $sub->update_meta('_esaf_affiliate_id', $affiliate_id);
                $sub->update_meta('_esaf_click_id', $click_id);
            }
        }
    }

    /**
     * Track a transaction
     *
     * @wp-hook mepr-txn-status-complete
     *
     * @param  MeprTransaction $txn The transaction object.
     * @return void
     */
    public static function track_transaction($txn)
    {
        // Kill it if it's not a payment type.
        if ($txn->txn_type !== MeprTransaction::$payment_str) {
            return;
        }

        // Check if we've already processed this transaction.
        $existing_transaction = Transaction::get_one([
            'source'   => 'memberpress',
            'order_id' => $txn->id,
        ]);

        if ($existing_transaction instanceof Transaction) {
            if ($existing_transaction->trans_num !== $txn->trans_num) {
                $existing_transaction->trans_num = $txn->trans_num;
                $existing_transaction->store();
            }

            return;
        }

        $subscr_id = '';
        $txn_count = 0;

        // If the admin is manually completing a txn or creating a new txn
        // we need to unset the cookie that may be in their browser so a false
        // commission doesn't get paid.
        if (MeprUtils::is_logged_in_and_an_admin() && Cookie::get_affiliate_id() > 0) {
            Cookie::clear();
        }

        // Track the coupon to an affiliate if a coupon exists and that coupon is tied to an affiliate.
        self::track_coupon($txn);

        $sub = $txn->subscription();

        if ($sub instanceof MeprSubscription) {
            $subscr_id = $sub->subscr_id;
            $txn_count = $sub->txn_count;

            // Override the affiliate to the one who referred the creation of the subscription
            // since the cookie is not present during a webhook/IPN request.
            $affiliate_id = (int) $sub->get_meta('_esaf_affiliate_id', true);

            if ($affiliate_id > 0) {
                $click_id = (int) $sub->get_meta('_esaf_click_id', true);
                Cookie::override($affiliate_id, $click_id);
            }
        } else {
            // Check if this is an offline transaction and override the affiliate to the one who referred the creation of it
            // since the cookie is not present when the transaction is completed.
            $affiliate_id = (int) $txn->get_meta('_esaf_affiliate_id', true);

            if ($affiliate_id > 0) {
                $click_id = (int) $txn->get_meta('_esaf_click_id', true);
                Cookie::override($affiliate_id, $click_id);
            }
        }

        if ($txn->amount > 0.00) {
            $prd                               = $txn->product();
            $_REQUEST['mepr_product_for_wafp'] = $prd; // Don't delete this $_REQUEST item - I use it down the line in wafp-calculate-commission filter for some folks.

            $coupon_code = '';
            $coupon      = $txn->coupon();
            if ($coupon) {
                $coupon_code = $coupon->post_title;
            }

            Track::sale(
                'memberpress',
                $txn->amount,
                $txn->trans_num,
                $prd->ID,
                $prd->post_title,
                $txn->id,
                $coupon_code,
                $txn->user_id,
                $subscr_id,
                $txn_count
            );
        }
    }

    /**
     * Add transaction meta to the transaction
     *
     * @wp-hook mepr-txn-status-pending
     *
     * @param  MeprTransaction $txn The transaction object.
     * @return void
     */
    public static function add_transaction_meta($txn)
    {
        // Kill it if it's not a payment type.
        if (!($txn instanceof MeprTransaction) || $txn->txn_type !== MeprTransaction::$payment_str) {
            return;
        }

        $sub = $txn->subscription();

        // Subscriptions are already tracked separately.
        if ($sub instanceof MeprSubscription) {
            return;
        }

        // Make sure not to track an admin manually adding a transaction.
        if (MeprUtils::is_logged_in_and_an_admin()) {
            return;
        }

        $affiliate_id = Cookie::get_affiliate_id();
        $click_id     = Cookie::get_click_id();

        // Override the affiliate if a coupon was used that is associated with an affiliate.
        $coupon = $txn->coupon();
        if ($coupon instanceof MeprCoupon) {
            $enabled = (isset($coupon->ID) && $coupon->ID) ? get_post_meta($coupon->ID, 'wafp_coupon_affiliate_enabled', true) : false;

            $coupon_affiliate_id = get_post_meta($coupon->ID, 'wafp_coupon_affiliate', true);
            if ($enabled && $coupon_affiliate_id) {
                $affiliate_id = (int) $coupon_affiliate_id;
                $click_id     = 0;
            }
        }

        if ($affiliate_id > 0) {
            $txn->update_meta('_esaf_affiliate_id', $affiliate_id);
            $txn->update_meta('_esaf_click_id', $click_id);
        }
    }

    /**
     * Refund a transaction
     *
     * @wp-hook mepr-txn-status-refunded
     *
     * @param  MeprTransaction $txn The transaction object.
     * @return void
     */
    public static function refund_transaction($txn)
    {
        $transaction = Transaction::get_one_by_trans_num($txn->trans_num);
        if ($transaction) {
            $transaction->apply_refund($txn->amount);
            $transaction->store();
        }
    }

    /**
     * Add product meta boxes to the product
     *
     * @wp-hook mepr-product-meta-boxes
     *
     * @param  MeprProduct $product The product object.
     * @return void
     */
    public static function product_meta_boxes($product)
    {
        add_meta_box(
            'memberpress-easy-affiliate-options',
            __('Easy Affiliate Commissions', 'memberpress'),
            [self::class, 'product_meta_box'],
            MeprProduct::$cpt,
            'side',
            'default',
            ['product' => $product]
        );
    }

    /**
     * Add product meta box to the product
     *
     * @param  WP_Post $post The post object.
     * @param  array   $args The arguments array.
     * @return void
     */
    public static function product_meta_box($post, $args)
    {
        $mepr_options              = MeprOptions::fetch();
        $product                   = $args['args']['product'];
        $commission_groups_enabled = false;
        $commission_type           = 'percentage';
        $commission_levels         = ['0.00'];
        $subscription_commissions  = 'all';

        $levels = get_post_meta($product->ID, 'wafp_commissions', true);

        if (is_array($levels) && count($levels) > 0) {
            $commission_groups_enabled = get_post_meta($product->ID, 'wafp_commission_groups_enabled', true);
            $commission_type           = get_post_meta($product->ID, 'wafp_commission_type', true);
            $commission_levels         = $levels;
            $subscription_commissions  = get_post_meta($product->ID, 'wafp_recurring', true) ? 'all' : 'first-only';
        }

        require __DIR__ . '/views/options/memberpress_product_meta_box.php';
    }

    /**
     * Save product meta
     *
     * @wp-hook mepr-product-save-meta
     *
     * @param  MeprProduct $product The product object.
     * @return void
     */
    public static function save_product($product)
    {
        $options = Options::fetch();

        $enabled           = isset($_POST['wafp_enable_commission_group']);
        $commission_type   = isset($_POST['wafp-commission-type']) && is_string($_POST['wafp-commission-type']) && $_POST['wafp-commission-type'] === 'fixed' ? 'fixed' : 'percentage';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $commission_levels = isset($_POST['wafp-commission']) && is_array($_POST['wafp-commission']) ? $options->sanitize_commissions($_POST['wafp-commission'], $commission_type) : [];
        $recurring         = isset($_POST['wafp-subscription-commissions']) && is_string($_POST['wafp-subscription-commissions']) && $_POST['wafp-subscription-commissions'] === 'all';

        update_post_meta($product->ID, 'wafp_commission_groups_enabled', $enabled);
        update_post_meta($product->ID, 'wafp_commission_type', $commission_type);
        update_post_meta($product->ID, 'wafp_commissions', $commission_levels);
        update_post_meta($product->ID, 'wafp_recurring', $recurring);
    }

    /**
     * Add commission percentages to the affiliate
     *
     * @wp-hook esaf_commission_percentages
     *
     * @param  array $commissions The commissions array.
     * @param  User  $affiliate   The affiliate object.
     * @return array
     */
    public static function commission_percentages($commissions, $affiliate)
    {
        $group = self::get_commission_group($affiliate->ID);
        if ($group) {
            $commissions = $group->commissions;
        }

        return $commissions;
    }

    /**
     * Get the commission group for the affiliate
     *
     * @param  integer $user_id The user ID.
     * @return object
     */
    public static function get_commission_group($user_id)
    {
        if (class_exists('MeprUser')) {
            $usr  = new MeprUser($user_id);
            $pids = $usr->active_product_subscriptions();

            foreach ($pids as $pid) {
                $commission_groups_enabled = get_post_meta($pid, 'wafp_commission_groups_enabled', true);

                // Just short circuit once we find our first product with groups enabled.
                if ($commission_groups_enabled) {
                    $product = new MeprProduct($pid);

                    return (object) [
                        'commission_type'   => get_post_meta($pid, 'wafp_commission_type', true),
                        'commission_source' => [
                            'slug'  => "product-{$pid}",
                            'label' => sprintf(
                                // Translators: %s: Product title.
                                __('%s Commission Group', 'memberpress'),
                                $product->post_title
                            ),
                        ],
                        'commissions'       => get_post_meta($pid, 'wafp_commissions', true),
                        'recurring'         => get_post_meta($pid, 'wafp_recurring', true),
                    ];
                }
            }
        }

        return false;
    }

    /**
     * Add commission type to the affiliate
     *
     * @wp-hook esaf_commission_type
     *
     * @param  string $commission_type The commission type.
     * @param  User   $affiliate       The affiliate object.
     * @return string
     */
    public static function commission_type($commission_type, $affiliate)
    {
        $group = self::get_commission_group($affiliate->ID);
        if ($group) {
            $commission_type = $group->commission_type;
        }

        return $commission_type;
    }

    /**
     * Add commission source to the affiliate
     *
     * @wp-hook esaf_commission_source
     *
     * @param  string                     $source    The source.
     * @param  \EasyAffiliate\Models\User $affiliate The affiliate object.
     * @return string
     */
    public static function commission_source($source, $affiliate)
    {
        $group = self::get_commission_group($affiliate->ID);
        if ($group) {
            $source = $group->commission_source;
        }

        return $source;
    }

    /**
     * Add subscription commissions to the affiliate
     *
     * @wp-hook esaf_subscription_commissions
     *
     * @param  string                     $subscription_commissions The subscription commissions.
     * @param  \EasyAffiliate\Models\User $affiliate                The affiliate object.
     * @return string
     */
    public static function subscription_commissions($subscription_commissions, $affiliate)
    {
        $group = self::get_commission_group($affiliate->ID);
        if ($group) {
            $subscription_commissions = $group->recurring ? 'all' : 'first-only';
        }

        return $subscription_commissions;
    }

    /**
     * Track a coupon
     *
     * @param  MeprTransaction $txn The transaction object.
     * @return void
     */
    public static function track_coupon($txn)
    {
        $coupon = $txn->coupon();
        if ($coupon instanceof MeprCoupon) {
            $enabled = (isset($coupon->ID) && $coupon->ID) ? get_post_meta($coupon->ID, 'wafp_coupon_affiliate_enabled', true) : false;

            $affiliate_id = get_post_meta($coupon->ID, 'wafp_coupon_affiliate', true);
            if ($enabled && $affiliate_id) {
                // Override the affiliate if there's a coupon associated with an affiliate.
                Cookie::override($affiliate_id);
            }
        }
    }

    /**
     * Add coupon meta boxes to the coupon
     *
     * @wp-hook mepr-coupon-meta-boxes
     *
     * @param  MeprCoupon $coupon The coupon object.
     * @return void
     */
    public static function coupon_meta_boxes($coupon)
    {
        add_meta_box(
            'memberpress-easy-affiliate-coupon-options',
            __('Associate Affiliate', 'memberpress'),
            [self::class, 'coupon_meta_box'],
            MeprCoupon::$cpt,
            'side',
            'default',
            ['coupon' => $coupon]
        );
    }

    /**
     * Add coupon meta box to the coupon
     *
     * @param  WP_Post $post The post object.
     * @param  array   $args The arguments array.
     * @return void
     */
    public static function coupon_meta_box($post, $args)
    {
        $mepr_options    = MeprOptions::fetch();
        $coupon          = $args['args']['coupon'];
        $enabled         = (isset($coupon->ID) && $coupon->ID) ? get_post_meta($coupon->ID, 'wafp_coupon_affiliate_enabled', true) : false;
        $affiliate_login = ''; // We'll populate later.
        $affiliate_id    = (isset($coupon->ID) && $coupon->ID) ? get_post_meta($coupon->ID, 'wafp_coupon_affiliate', true) : false;

        if ($affiliate_id) {
            $user            = get_user_by('id', $affiliate_id);
            $affiliate_login = $user->user_login;
        }

        require __DIR__ . '/views/options/memberpress_coupon_meta_box.php';
    }

    /**
     * Save coupon meta
     *
     * @wp-hook mepr-coupon-save-meta
     *
     * @param  MeprCoupon $coupon The coupon object.
     * @return void
     */
    public static function save_coupon($coupon)
    {
        if (isset($_POST['mepr-associate-affiliate-enable']) && !empty($_POST['mepr-associate-affiliate-username'])) {
            $username = sanitize_text_field(wp_unslash($_POST['mepr-associate-affiliate-username']));
            $user     = get_user_by('login', $username);

            if ($user instanceof WP_User && isset($user->ID) && $user->ID && isset($coupon->ID) && $coupon->ID) {
                update_post_meta($coupon->ID, 'wafp_coupon_affiliate_enabled', 1);
                update_post_meta($coupon->ID, 'wafp_coupon_affiliate', $user->ID);
            }
        } else {
            if (isset($coupon->ID) && $coupon->ID) {
                update_post_meta($coupon->ID, 'wafp_coupon_affiliate_enabled', 0);
                update_post_meta($coupon->ID, 'wafp_coupon_affiliate', 0);
            }
        }
    }

    /**
     * Enqueue coupon scripts
     *
     * @wp-hook mepr-coupon-admin-enqueue-script
     *
     * @return void
     */
    public static function enqueue_coupon_scripts()
    {
        wp_enqueue_style('wafp-mp-coupons-css', plugins_url('css/memberpress-coupons.css', __FILE__), [], MEPR_VERSION);
        wp_enqueue_script('wafp-mp-coupons-js', plugins_url('js/memberpress-coupons.js', __FILE__), ['jquery', 'suggest'], MEPR_VERSION);
    }

    /**
     * Add coupon count to the affiliate
     *
     * @wp-hook esaf_dashboard_coupon_count
     *
     * @param  integer $coupon_count The coupon count.
     * @return integer
     */
    public static function coupon_count($coupon_count)
    {
        $affiliate = Utils::get_currentuserinfo();

        if ($affiliate && $affiliate->is_affiliate) {
            $query = new WP_Query([
                'post_type'   => MeprCoupon::$cpt,
                'post_status' => 'publish',
                'meta_key'    => 'wafp_coupon_affiliate',
                'meta_value'  => $affiliate->ID,
            ]);

            $coupon_count += $query->found_posts;
        }

        return $coupon_count;
    }

    /**
     * Display the affiliate's coupons
     *
     * @wp-hook esaf_creatives_coupons
     *
     * @return void
     */
    public static function display_my_coupons()
    {
        $affiliate = Utils::get_currentuserinfo();

        if ($affiliate && $affiliate->is_affiliate) {
            $my_coupons = get_posts([
                'post_type'   => MeprCoupon::$cpt,
                'post_status' => 'publish',
                'meta_key'    => 'wafp_coupon_affiliate',
                'meta_value'  => $affiliate->ID,
                'fields'      => 'ids',
                'numberposts' => -1,
            ]);

            if (!empty($my_coupons)) {
                require __DIR__ . '/views/dashboard/memberpress-coupons.php';
            }
        }
    }

    /**
     * Set the customer's referrer to the coupon affiliate (if any)
     *
     * @wp-hook user_register
     *
     * @param  integer $user_id The user ID.
     * @return void
     */
    public static function save_coupon_referrer($user_id)
    {
        $coupon_code = isset($_POST['mepr_coupon_code']) ? sanitize_text_field(wp_unslash($_POST['mepr_coupon_code'])) : '';

        if (empty($coupon_code)) {
            return;
        }

        $coupon = MeprCoupon::get_one_from_code($coupon_code);

        if ($coupon instanceof MeprCoupon) {
            $enabled = (isset($coupon->ID) && $coupon->ID) ? get_post_meta($coupon->ID, 'wafp_coupon_affiliate_enabled', true) : false;

            $affiliate_id = get_post_meta($coupon->ID, 'wafp_coupon_affiliate', true);
            if ($enabled && $affiliate_id) {
                $user           = new User($user_id);
                $user->referrer = (int) $affiliate_id;
                $user->store();

                $cookie_affiliate_id = Cookie::get_affiliate_id();

                if ($cookie_affiliate_id > 0 && $user->referrer !== $cookie_affiliate_id) {
                    Cookie::delete();
                }
            }
        }
    }

    /**
     * Link the transaction source label to the MemberPress transaction if applicable
     *
     * @wp-hook esaf_transaction_source_label
     *
     * @param  string $label The original transaction source label (already escaped).
     * @param  object $rec   The transaction rec object.
     * @return string
     */
    public static function transaction_source_label($label, $rec)
    {
        $source   = isset($rec->source) && is_string($rec->source) ? $rec->source : '';
        $order_id = isset($rec->order_id) && is_numeric($rec->order_id) ? (int) $rec->order_id : 0;

        if ('memberpress' === $source && $order_id && class_exists('MeprUtils')) {
            $label = sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url("admin.php?page=memberpress-trans&action=edit&id={$order_id}")),
                $label
            );
        }

        return $label;
    }
}
