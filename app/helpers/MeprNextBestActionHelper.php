<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Helper class for Next Best Action recommendations.
 *
 * Implements RICE-style scoring to determine the most impactful
 * action a user should take based on their current setup state.
 */
class MeprNextBestActionHelper
{
    /**
     * Action type constants.
     */
    const TYPE_ADOPTION   = 'adoption';
    const TYPE_GROWTH     = 'growth';
    const TYPE_UPSELL     = 'upsell';
    const TYPE_CROSS_SELL = 'cross_sell';

    /**
     * Get the highest-scored recommendation.
     *
     * @return array|null The recommended action or null if none available.
     */
    public static function get_recommendation()
    {
        $actions    = self::get_available_actions();
        $dismissed  = self::get_dismissed_actions();
        $is_checklist_incomplete = self::is_checklist_incomplete();

        // Filter out dismissed actions.
        $actions = array_filter($actions, function ($action) use ($dismissed) {
            return !in_array($action['id'], $dismissed, true);
        });

        // If checklist is incomplete, filter out advanced growth/scaling recommendations.
        if ($is_checklist_incomplete) {
            $actions = array_filter($actions, function ($action) {
                return $action['type'] !== self::TYPE_GROWTH || $action['priority'] <= 5;
            });
        }

        if (empty($actions)) {
            return null;
        }

        // Score each action using RICE.
        $scored_actions = [];
        foreach ($actions as $action) {
            $action['rice_score']   = self::calculate_rice_score($action);
            $scored_actions[]       = $action;
        }

        // Sort by RICE score (highest first).
        usort($scored_actions, function ($a, $b) {
            return $b['rice_score'] <=> $a['rice_score'];
        });

        // Return the highest scored action.
        return $scored_actions[0] ?? null;
    }

    /**
     * Get all possible actions based on current user state.
     *
     * @return array Array of possible actions.
     */
    public static function get_available_actions()
    {
        $actions = [];

        // Core adoption actions.
        $actions = array_merge($actions, self::get_adoption_actions());

        // Growth actions (only if past launch phase).
        if (!MeprDashboardHelper::is_launch_phase()) {
            $actions = array_merge($actions, self::get_growth_actions());
        }

        // Cross-sell actions.
        $actions = array_merge($actions, self::get_cross_sell_actions());

        // Filter to only applicable actions.
        $actions = array_filter($actions, function ($action) {
            return $action['applicable'] ?? true;
        });

        return MeprHooks::apply_filters('mepr_dashboard_nba_actions', $actions);
    }

    /**
     * Get core adoption actions.
     *
     * @return array Array of adoption actions.
     */
    private static function get_adoption_actions()
    {
        $actions = [];

        // Create first membership.
        if (!MeprPostSetupChecklistHelper::has_membership()) {
            $actions[] = [
                'id'          => 'create_membership',
                'title'       => __('Create your first Membership', 'memberpress'),
                'description' => __('Set up a membership product so customers can start purchasing access to your content.', 'memberpress'),
                'action_url'  => admin_url('post-new.php?post_type=memberpressproduct'),
                'action_text' => __('Create Membership', 'memberpress'),
                'effort'      => __('Takes ~5 minutes', 'memberpress'),
                'type'        => self::TYPE_ADOPTION,
                'priority'    => 1,
                'applicable'  => true,
                // RICE factors.
                'reach'       => 100,
                'impact'      => 3,
                'confidence'  => 100,
                'effort_score' => 1,
            ];
        }

        // Create first rule.
        if (MeprPostSetupChecklistHelper::has_membership() && !MeprPostSetupChecklistHelper::has_rule()) {
            $actions[] = [
                'id'          => 'create_rule',
                'title'       => __('Your content isn\'t protected yet', 'memberpress'),
                'description' => __('Create your first Rule to lock your pages and posts so only paying members can access them.', 'memberpress'),
                'action_url'  => admin_url('post-new.php?post_type=memberpressrule'),
                'action_text' => __('Create Rule', 'memberpress'),
                'effort'      => __('Takes ~5 minutes', 'memberpress'),
                'type'        => self::TYPE_ADOPTION,
                'priority'    => 2,
                'applicable'  => true,
                'reach'       => 100,
                'impact'      => 3,
                'confidence'  => 100,
                'effort_score' => 1,
            ];
        }

        // Connect payment gateway.
        if (!MeprPostSetupChecklistHelper::is_payment_gateway_connected()) {
            $actions[] = [
                'id'          => 'connect_gateway',
                'title'       => __('Connect a payment gateway', 'memberpress'),
                'description' => __('Set up Stripe or PayPal to start accepting real payments from your customers.', 'memberpress'),
                'action_url'  => admin_url('admin.php?page=memberpress-options#integration'),
                'action_text' => __('Connect Gateway', 'memberpress'),
                'effort'      => __('Takes ~10 minutes', 'memberpress'),
                'type'        => self::TYPE_ADOPTION,
                'priority'    => 3,
                'applicable'  => true,
                'reach'       => 100,
                'impact'      => 3,
                'confidence'  => 100,
                'effort_score' => 2,
            ];
        }

        // Set up reminders (churn prevention).
        if (MeprPostSetupChecklistHelper::has_membership() && !MeprPostSetupChecklistHelper::has_reminder()) {
            $actions[] = [
                'id'          => 'setup_reminder',
                'title'       => __('Recover lost revenue', 'memberpress'),
                'description' => __('Set up an automated credit card expiration reminder to prevent failed payments and reduce churn.', 'memberpress'),
                'action_url'  => admin_url('post-new.php?post_type=mp-reminder'),
                'action_text' => __('Create Reminder', 'memberpress'),
                'effort'      => __('Takes ~2 minutes', 'memberpress'),
                'type'        => self::TYPE_ADOPTION,
                'priority'    => 4,
                'applicable'  => true,
                'reach'       => 80,
                'impact'      => 2,
                'confidence'  => 90,
                'effort_score' => 0.5,
            ];
        }

        return $actions;
    }

    /**
     * Get growth-focused actions.
     *
     * @return array Array of growth actions.
     */
    private static function get_growth_actions()
    {
        $actions       = [];
        $total_revenue = MeprDashboardHelper::get_total_revenue();
        $member_count  = MeprDashboardHelper::get_active_member_count();

        // Check coupons - suggest creating first one if none exist.
        if (!MeprPostSetupChecklistHelper::has_coupon()) {
            $actions[] = [
                'id'          => 'create_coupon',
                'title'       => __('Boost sales with a promotion', 'memberpress'),
                'description' => __('Create a discount coupon to attract new members or reward existing ones.', 'memberpress'),
                'action_url'  => admin_url('post-new.php?post_type=memberpresscoupon'),
                'action_text' => __('Create Coupon', 'memberpress'),
                'effort'      => __('Takes ~2 minutes', 'memberpress'),
                'type'        => self::TYPE_GROWTH,
                'priority'    => 6,
                'applicable'  => $total_revenue > 0,
                'reach'       => 60,
                'impact'      => 2,
                'confidence'  => 80,
                'effort_score' => 0.5,
            ];
        }

        // Upsell actions.
        $actions = array_merge($actions, self::get_upsell_actions($member_count));

        return $actions;
    }

    /**
     * Get upsell actions for users on basic/growth plans.
     *
     * Brand-specific upsell actions (like plan upgrades) should be added
     * via the 'mepr_nba_upsell_actions' filter in the brand folder.
     *
     * @param integer $member_count The active member count.
     *
     * @return array Array of upsell actions.
     */
    private static function get_upsell_actions($member_count)
    {
        $actions = [];

        /**
         * Filter upsell actions for the Next Best Action recommendations.
         *
         * Brand-specific upsell actions (like plan upgrades) should be added via this filter.
         *
         * @param array   $actions      The upsell actions array.
         * @param integer $member_count The active member count.
         */
        return MeprHooks::apply_filters('mepr_nba_upsell_actions', $actions, $member_count);
    }

    /**
     * Get cross-sell actions.
     *
     * Brand-specific cross-sell actions (like add-on suggestions) should be added
     * via the 'mepr_nba_cross_sell_actions' filter in the brand folder.
     *
     * @return array Array of cross-sell actions.
     */
    private static function get_cross_sell_actions()
    {
        $actions       = [];
        $total_revenue = MeprDashboardHelper::get_total_revenue();
        $coupon_count  = self::get_coupon_count();

        // Pretty Links suggestion after 3+ coupons.
        if ($coupon_count >= 3 && !is_plugin_active('pretty-link/pretty-link.php')) {
            $actions[] = [
                'id'          => 'install_pretty_links',
                'title'       => __('Managing lots of promo links?', 'memberpress'),
                'description' => __('Pretty Links can organize and track your promotional links for better marketing insights.', 'memberpress'),
                'action_url'  => self::get_utm_url('https://prettylinks.com/', 'pretty_links'),
                'action_text' => __('Learn More', 'memberpress'),
                'effort'      => __('Takes ~1 minute', 'memberpress'),
                'type'        => self::TYPE_CROSS_SELL,
                'priority'    => 10,
                'applicable'  => true,
                'reach'       => 40,
                'impact'      => 1,
                'confidence'  => 70,
                'effort_score' => 0.5,
            ];
        }

        // Easy Affiliate suggestion at $5,000+ revenue.
        if ($total_revenue >= 5000 && !defined('ESAF_VERSION')) {
            $actions[] = [
                'id'          => 'install_easy_affiliate',
                'title'       => __('Ready to let others sell for you?', 'memberpress'),
                'description' => __('Launch your own affiliate program with Easy Affiliate and let partners help grow your membership.', 'memberpress'),
                'action_url'  => self::get_utm_url('https://easyaffiliate.com/', 'easy_affiliate'),
                'action_text' => __('Learn More', 'memberpress'),
                'effort'      => __('Takes ~7 minutes', 'memberpress'),
                'type'        => self::TYPE_CROSS_SELL,
                'priority'    => 11,
                'applicable'  => true,
                'reach'       => 30,
                'impact'      => 2,
                'confidence'  => 80,
                'effort_score' => 2,
            ];
        }

        /**
         * Filter cross-sell actions for the Next Best Action recommendations.
         *
         * Brand-specific cross-sell actions (like add-on suggestions) should be added via this filter.
         *
         * @param array $actions       The cross-sell actions array.
         * @param float $total_revenue The total revenue.
         * @param int   $coupon_count  The number of coupons.
         */
        return MeprHooks::apply_filters('mepr_nba_cross_sell_actions', $actions, $total_revenue, $coupon_count);
    }

    /**
     * Calculate RICE score for an action.
     *
     * RICE = (Reach × Impact × Confidence) / Effort
     *
     * @param array $action The action to score.
     *
     * @return float The RICE score.
     */
    public static function calculate_rice_score($action)
    {
        $reach      = $action['reach'] ?? 50;
        $impact     = $action['impact'] ?? 1;
        $confidence = $action['confidence'] ?? 50;
        $effort     = $action['effort_score'] ?? 1;

        if ($effort <= 0) {
            $effort = 0.5;
        }

        return ($reach * $impact * ($confidence / 100)) / $effort;
    }

    /**
     * Get list of dismissed action IDs.
     *
     * @return array Array of dismissed action IDs.
     */
    public static function get_dismissed_actions()
    {
        $dismissed = get_option('mepr_dashboard_nba_dismissed', []);

        return is_array($dismissed) ? $dismissed : [];
    }

    /**
     * Dismiss an action.
     *
     * @param string $action_id The action ID to dismiss.
     *
     * @return void
     */
    public static function dismiss_action($action_id)
    {
        $dismissed = self::get_dismissed_actions();

        if (!in_array($action_id, $dismissed, true)) {
            $dismissed[] = $action_id;
            update_option('mepr_dashboard_nba_dismissed', $dismissed);
        }
    }

    /**
     * Check if the post-setup checklist is incomplete.
     *
     * @return boolean True if checklist is incomplete.
     */
    public static function is_checklist_incomplete()
    {
        if (!class_exists('MeprPostSetupChecklistHelper')) {
            return false;
        }

        return !MeprPostSetupChecklistHelper::is_complete() &&
               !MeprPostSetupChecklistHelper::is_dismissed();
    }

    /**
     * Get the count of coupons.
     *
     * @return integer The number of coupons.
     */
    private static function get_coupon_count()
    {
        $counts = wp_count_posts(MeprCoupon::$cpt);

        return (int) ($counts->publish ?? 0);
    }

    /**
     * Generate a UTM-tagged URL for tracking.
     *
     * @param string $url         The base URL.
     * @param string $campaign_id The campaign identifier.
     *
     * @return string The URL with UTM parameters.
     */
    public static function get_utm_url($url, $campaign_id)
    {
        /**
         * Filter the UTM source for Next Best Action links.
         *
         * @param string $utm_source The UTM source (default 'memberpress').
         */
        $utm_source = MeprHooks::apply_filters('mepr_nba_utm_source', 'memberpress');

        $utm_params = [
            'utm_source'   => $utm_source,
            'utm_medium'   => 'dashboard',
            'utm_campaign' => $campaign_id,
        ];

        return add_query_arg($utm_params, $url);
    }

    /**
     * Format an action for display.
     *
     * @param array $action The action data.
     *
     * @return array The formatted action.
     */
    public static function format_for_display($action)
    {
        if (!$action) {
            return null;
        }

        return [
            'id'          => $action['id'],
            'title'       => $action['title'],
            'description' => $action['description'],
            'action_url'  => $action['action_url'],
            'action_text' => $action['action_text'],
            'effort'      => $action['effort'] ?? '',
            'type'        => $action['type'],
        ];
    }
}
