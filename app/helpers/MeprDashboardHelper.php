<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Helper class for the MemberPress Dashboard.
 *
 * Provides methods for calculating business metrics, detecting user state,
 * and managing dashboard preferences.
 */
class MeprDashboardHelper
{
    /**
     * Cache key prefix for dashboard metrics.
     *
     * @var string
     */
    const CACHE_PREFIX = 'mepr_dashboard_';

    /**
     * Cache duration for metrics (1 day).
     *
     * @var int
     */
    const CACHE_DURATION = DAY_IN_SECONDS;

    /**
     * Get Monthly Recurring Revenue (MRR).
     *
     * Calculates MRR based on active recurring subscriptions that have
     * at least one non-expired transaction, normalizing to monthly equivalent.
     *
     * @return float The MRR amount.
     */
    public static function get_mrr()
    {
        $cached = get_transient(self::CACHE_PREFIX . 'mrr');

        if ($cached !== false) {
            return (float) $cached;
        }

        global $wpdb;
        $mepr_db = MeprDb::fetch();
        $now     = MeprUtils::db_now();

        // Get active subscriptions that have at least one non-expired transaction.
        // This ensures we only count subscriptions with actual active access.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe.
        $subscriptions = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT s.id, s.price, s.period, s.period_type
                FROM {$mepr_db->subscriptions} AS s
                WHERE s.status = %s
                AND EXISTS (
                    SELECT 1
                    FROM {$mepr_db->transactions} AS t
                    WHERE t.subscription_id = s.id
                    AND t.status IN (%s, %s)
                    AND (t.expires_at >= %s OR t.expires_at IS NULL OR t.expires_at = %s)
                )",
                MeprSubscription::$active_str,
                MeprTransaction::$complete_str,
                MeprTransaction::$confirmed_str,
                $now,
                MeprUtils::db_lifetime()
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $mrr = 0.0;

        foreach ($subscriptions as $sub) {
            $monthly_amount = self::normalize_to_monthly($sub->price, $sub->period, $sub->period_type);
            $mrr += $monthly_amount;
        }

        set_transient(self::CACHE_PREFIX . 'mrr', $mrr, self::CACHE_DURATION);

        return $mrr;
    }

    /**
     * Normalize a subscription amount to monthly equivalent.
     *
     * Handles all MemberPress billing intervals:
     * - Weekly (1 week)
     * - Monthly (1 month)
     * - Quarterly (3 months)
     * - Semi-annually (6 months)
     * - Yearly (1 year / 12 months)
     * - Custom intervals (weeks or months with any period number)
     *
     * @param float   $amount      The subscription amount.
     * @param integer $period      The billing period (e.g., 1, 3, 6, 12).
     * @param string  $period_type The period type (weeks, months, years, lifetime).
     *
     * @return float The monthly equivalent amount.
     */
    public static function normalize_to_monthly($amount, $period, $period_type)
    {
        $amount = (float) $amount;
        $period = (int) $period;

        if ($period <= 0) {
            return 0.0;
        }

        switch ($period_type) {
            case 'weeks':
                // Convert weeks to monthly (approximately 4.33 weeks per month).
                return ($amount / $period) * 4.33;

            case 'months':
                // Monthly, quarterly (3), semi-annually (6), or custom month intervals.
                return $amount / $period;

            case 'years':
                // Convert years to months (1 year = 12 months).
                return $amount / ($period * 12);

            case 'lifetime':
                // Lifetime/one-time payments are not recurring, don't contribute to MRR.
                return 0.0;

            default:
                // Assume monthly if unknown period type.
                return $amount / $period;
        }
    }

    /**
     * Get the count of active members.
     *
     * Uses MeprReports::get_active_members_count() which properly counts
     * members, handling both recurring memberships and one-time/lifetime memberships.
     *
     * @return integer The number of active members.
     */
    public static function get_active_member_count()
    {
        $cached = get_transient(self::CACHE_PREFIX . 'active_members');

        if ($cached !== false) {
            return (int) $cached;
        }

        // Use the existing MeprReports method which properly counts active members,
        // supporting both recurring and one-time memberships.
        $count = (int) MeprReports::get_active_members_count();

        set_transient(self::CACHE_PREFIX . 'active_members', $count, self::CACHE_DURATION);

        return $count;
    }

    /**
     * Get the churn rate for the last 30 days.
     *
     * Churn is calculated as:
     * (Members who lost access during period / Active members at start of period) * 100
     *
     * A user is counted as churned when their transaction(s) expired during the period
     * AND they have no other active transactions remaining.
     *
     * @return float|null The churn rate percentage, or null if insufficient data.
     */
    public static function get_churn_rate()
    {
        $cached = get_transient(self::CACHE_PREFIX . 'churn');

        if ($cached !== false) {
            return $cached === 'null' ? null : (float) $cached;
        }

        global $wpdb;
        $mepr_db = MeprDb::fetch();

        // Calculate churn for the last 30 days.
        $start_date = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
        $now        = MeprUtils::db_now();

        // Count unique users whose transactions expired during the period
        // AND who have no other active transactions remaining.
        // This covers both one-time and recurring membership transactions.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe.
        $total_churned = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT t.user_id)
                FROM {$mepr_db->transactions} AS t
                WHERE t.status IN (%s, %s)
                AND t.expires_at >= %s
                AND t.expires_at < %s
                AND t.expires_at <> %s
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$mepr_db->transactions} AS t2
                    WHERE t2.user_id = t.user_id
                    AND t2.status IN (%s, %s)
                    AND (t2.expires_at >= %s OR t2.expires_at IS NULL OR t2.expires_at = %s)
                )",
                MeprTransaction::$complete_str,
                MeprTransaction::$confirmed_str,
                $start_date,
                $now,
                MeprUtils::db_lifetime(),
                MeprTransaction::$complete_str,
                MeprTransaction::$confirmed_str,
                $now,
                MeprUtils::db_lifetime()
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Get current active members and add churned to approximate starting count.
        $current_active = (int) MeprReports::get_active_members_count();
        $starting_active = $current_active + $total_churned;

        if ($starting_active === 0) {
            set_transient(self::CACHE_PREFIX . 'churn', 'null', self::CACHE_DURATION);
            return null;
        }

        $churn_rate = ($total_churned / $starting_active) * 100;
        $churn_rate = round($churn_rate, 1);

        set_transient(self::CACHE_PREFIX . 'churn', $churn_rate, self::CACHE_DURATION);

        return $churn_rate;
    }

    /**
     * Get the average Customer Lifetime Value (LTV).
     *
     * @return float The average LTV.
     */
    public static function get_ltv()
    {
        // Use the existing implementation from MeprReports.
        $ltv = MeprReports::get_average_lifetime_value();

        return $ltv ? (float) $ltv : 0.0;
    }

    /**
     * Get trend indicator for a metric.
     *
     * Compares current month to previous month and returns direction and change.
     * - For 'revenue': returns 'direction', 'percentage', 'label'.
     * - For 'members': returns 'direction', 'value' (net change), 'label'.
     * - For 'churn': returns 'direction', 'sentiment', 'percentage', 'label'.
     *
     * @param string $metric The metric to calculate trend for.
     *
     * @return array|null Trend data array, or null if insufficient data.
     */
    public static function get_trend($metric)
    {
        $current  = self::get_metric_for_period($metric, 'current');
        $previous = self::get_metric_for_period($metric, 'previous');

        // For members, we show net member change (new members - churned).
        if ($metric === 'members') {
            if ($current === null || (int) $current === 0) {
                return null;
            }
            $net_change = (int) $current;
            return [
                'direction' => $net_change >= 0 ? 'up' : 'down',
                'value'     => abs($net_change),
            ];
        }

        // For churn, lower is better, so invert sentiment logic.
        if ($metric === 'churn') {
            if ($previous === null || $current === null) {
                return null;
            }
            $previous = (float) $previous;
            $current  = (float) $current;
            $diff     = $previous - $current;

            if (abs($diff) < 0.1) {
                return null; // No significant change.
            }

            // Direction shows the actual movement (down if decreased).
            // Sentiment shows if this is good or bad (positive = good = green).
            return [
                'direction'  => $diff > 0 ? 'down' : 'up',
                'sentiment'  => $diff > 0 ? 'positive' : 'negative', // Down is good for churn.
                'percentage' => abs(round($diff, 1)),
            ];
        }

        // Default: percentage change.
        $previous = (float) $previous;
        $current  = (float) $current;

        // If previous is 0 but current has value, show as 100% growth.
        if ($previous === 0.0) {
            if ($current > 0) {
                return [
                    'direction'  => 'up',
                    'percentage' => 100,
                ];
            }
            return null;
        }

        $change = (($current - $previous) / $previous) * 100;

        return [
            'direction'  => $change >= 0 ? 'up' : 'down',
            'percentage' => abs(round($change, 1)),
        ];
    }

    /**
     * Get a metric value for the current or previous month.
     *
     * @param string $metric The metric name.
     * @param string $which  Which period ('current', 'previous').
     *
     * @return float|null The metric value.
     */
    public static function get_metric_for_period($metric, $which = 'current')
    {
        switch ($metric) {
            case 'revenue':
                return self::get_revenue_for_period_comparison($which);

            case 'members':
                return self::get_members_for_period_comparison($which);

            case 'churn':
                return self::get_churn_for_period_comparison($which);

            default:
                return null;
        }
    }

    /**
     * Get revenue for the current or previous month for comparison.
     *
     * @param string $which Which period ('current', 'previous').
     *
     * @return float|null The revenue amount.
     */
    private static function get_revenue_for_period_comparison($which)
    {
        if ($which === 'current') {
            $month = (int) gmdate('n');
            $year  = (int) gmdate('Y');
        } else {
            // Previous month.
            $prev_month_date = new \DateTimeImmutable('first day of last month', new \DateTimeZone('UTC'));
            $month = (int) $prev_month_date->format('n');
            $year  = (int) $prev_month_date->format('Y');
        }

        return (float) MeprReports::get_revenue($month, false, $year);
    }

    /**
     * Get net member change for the current or previous month.
     *
     * Calculates net change = new members - churned members.
     *
     * @param string $which Which period ('current', 'previous').
     *
     * @return float|null The net member change.
     */
    private static function get_members_for_period_comparison($which)
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        // Calculate date ranges for 30-day periods.
        if ($which === 'current') {
            $start_date = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
            $end_date   = MeprUtils::db_now();
        } else {
            $start_date = gmdate('Y-m-d H:i:s', strtotime('-60 days'));
            $end_date   = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
        }

        // Count new members (first completed transaction in period).
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe.
        $new_members = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT t.user_id)
                FROM {$mepr_db->transactions} AS t
                WHERE t.status IN (%s, %s)
                AND t.created_at >= %s
                AND t.created_at < %s
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$mepr_db->transactions} AS t2
                    WHERE t2.user_id = t.user_id
                    AND t2.status IN (%s, %s)
                    AND t2.created_at < %s
                )",
                MeprTransaction::$complete_str,
                MeprTransaction::$confirmed_str,
                $start_date,
                $end_date,
                MeprTransaction::$complete_str,
                MeprTransaction::$confirmed_str,
                $start_date
            )
        );

        // Count churned members during the period (users whose transactions expired
        // and who have no other active transactions remaining).
        $total_churned = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT t.user_id)
                FROM {$mepr_db->transactions} AS t
                WHERE t.status IN (%s, %s)
                AND t.expires_at >= %s
                AND t.expires_at < %s
                AND t.expires_at <> %s
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$mepr_db->transactions} AS t2
                    WHERE t2.user_id = t.user_id
                    AND t2.status IN (%s, %s)
                    AND (t2.expires_at >= %s OR t2.expires_at IS NULL OR t2.expires_at = %s)
                )",
                MeprTransaction::$complete_str,
                MeprTransaction::$confirmed_str,
                $start_date,
                $end_date,
                MeprUtils::db_lifetime(),
                MeprTransaction::$complete_str,
                MeprTransaction::$confirmed_str,
                $end_date,
                MeprUtils::db_lifetime()
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $net_change    = $new_members - $total_churned;

        return (float) $net_change;
    }

    /**
     * Get churn rate for the current or previous month for comparison.
     *
     * @param string $which Which period ('current', 'previous').
     *
     * @return float|null The churn rate.
     */
    private static function get_churn_for_period_comparison($which)
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        // Calculate date ranges for 30-day periods.
        if ($which === 'current') {
            $start_date = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
            $end_date   = MeprUtils::db_now();
        } else {
            $start_date = gmdate('Y-m-d H:i:s', strtotime('-60 days'));
            $end_date   = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
        }

        // Count unique users whose transactions expired during the period
        // AND who have no other active transactions remaining.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe.
        $total_churned = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT t.user_id)
                FROM {$mepr_db->transactions} AS t
                WHERE t.status IN (%s, %s)
                AND t.expires_at >= %s
                AND t.expires_at < %s
                AND t.expires_at <> %s
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$mepr_db->transactions} AS t2
                    WHERE t2.user_id = t.user_id
                    AND t2.status IN (%s, %s)
                    AND (t2.expires_at >= %s OR t2.expires_at IS NULL OR t2.expires_at = %s)
                )",
                MeprTransaction::$complete_str,
                MeprTransaction::$confirmed_str,
                $start_date,
                $end_date,
                MeprUtils::db_lifetime(),
                MeprTransaction::$complete_str,
                MeprTransaction::$confirmed_str,
                $end_date,
                MeprUtils::db_lifetime()
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Get current active members as approximation.
        $current_active = (int) MeprReports::get_active_members_count();
        $starting_active = $current_active + $total_churned;

        if ($starting_active <= 0) {
            return null;
        }

        return ($total_churned / $starting_active) * 100;
    }

    /**
     * Check if the site is in "launch phase" (no revenue yet).
     *
     * @return boolean True if in launch phase.
     */
    public static function is_launch_phase()
    {
        return !self::has_any_revenue();
    }

    /**
     * Check if the setup notice should be shown on the dashboard.
     *
     * @return boolean True if notice should be shown.
     */
    public static function should_show_setup_notice()
    {
        // Don't show if the dashboard setup notice was dismissed.
        if (get_option('mepr_dashboard_setup_notice_dismissed', false)) {
            return false;
        }

        // Don't show if Post-Setup Checklist is complete.
        if (MeprPostSetupChecklistHelper::is_complete()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the site has generated any revenue.
     *
     * @return boolean True if at least one completed transaction exists.
     */
    public static function has_any_revenue()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
                "SELECT COUNT(*) FROM {$mepr_db->transactions}
                WHERE status = %s AND txn_type = %s AND amount > 0",
                MeprTransaction::$complete_str,
                MeprTransaction::$payment_str
            )
        );

        return $count > 0;
    }

    /**
     * Get total revenue earned (all time).
     *
     * Uses MeprReports::get_revenue() with no date filters.
     *
     * @return float The total revenue.
     */
    public static function get_total_revenue()
    {
        $total = MeprReports::get_revenue();

        return $total ? (float) $total : 0.0;
    }

    /**
     * Get total member count (all time, including inactive).
     *
     * Uses MeprReports::get_total_members_count() which counts users
     * who have at least one transaction (supporting both recurring
     * and one-time memberships).
     *
     * @return integer The total member count.
     */
    public static function get_total_member_count()
    {
        return (int) MeprReports::get_total_members_count();
    }

    /**
     * Check if the first sale celebration should be shown.
     *
     * @return boolean True if celebration should be shown.
     */
    public static function should_show_first_sale_celebration()
    {
        // Only show once.
        if (get_option('mepr_dashboard_first_sale_celebrated', false)) {
            return false;
        }

        // Only show if there's revenue.
        if (!self::has_any_revenue()) {
            return false;
        }

        return true;
    }

    /**
     * Mark the first sale celebration as shown.
     *
     * @return void
     */
    public static function mark_first_sale_celebrated()
    {
        update_option('mepr_dashboard_first_sale_celebrated', true);
    }

    /**
     * Clear all dashboard caches.
     *
     * Should be called when subscription/transaction events occur.
     *
     * @return void
     */
    public static function clear_cache()
    {
        delete_transient(self::CACHE_PREFIX . 'mrr');
        delete_transient(self::CACHE_PREFIX . 'active_members');
        delete_transient(self::CACHE_PREFIX . 'churn');
    }

    /**
     * Default milestone targets used for MRR, members, and subscriptions.
     *
     * @var array<int>
     */
    private static $milestones = [100, 250, 500, 1000, 5000, 10000, 50000, 100000, 1000000];

    /**
     * Get the next milestone target for a given current value.
     *
     * @param integer|float $current_value The current value (MRR, member count, or subscription count).
     *
     * @return integer The next milestone target.
     */
    private static function get_next_milestone_target($current_value)
    {
        /**
         * Filter the milestone sequence used for all dashboard milestones (MRR, members, subscriptions).
         *
         * @param array<int> $milestones Ordered list of milestone targets.
         */
        $milestones = MeprHooks::apply_filters('mepr_dashboard_milestones', self::$milestones);
        $milestones = array_values(array_map('intval', array_filter($milestones, 'is_numeric')));

        if (empty($milestones)) {
            return 100;
        }

        foreach ($milestones as $target) {
            if ($current_value < $target) {
                return $target;
            }
        }

        return (int) end($milestones);
    }

    /**
     * Get the dynamic MRR milestone target.
     *
     * @param float $current_mrr The current MRR value.
     *
     * @return integer The calculated target.
     */
    public static function get_mrr_milestone_target($current_mrr)
    {
        return self::get_next_milestone_target($current_mrr);
    }

    /**
     * Get the dynamic members milestone target.
     *
     * @param integer $current_members The current member count.
     *
     * @return integer The calculated target.
     */
    public static function get_members_milestone_target($current_members)
    {
        return self::get_next_milestone_target($current_members);
    }

    /**
     * Get the count of active subscriptions.
     *
     * @return integer The number of active subscriptions.
     */
    public static function get_active_subscription_count()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();
        $now     = MeprUtils::db_now();

        // Count subscriptions that are active AND have at least one non-expired transaction.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe.
        $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$mepr_db->subscriptions} AS s
                WHERE s.status = %s
                AND EXISTS (
                    SELECT 1
                    FROM {$mepr_db->transactions} AS t
                    WHERE t.subscription_id = s.id
                    AND t.status IN (%s, %s)
                    AND (t.expires_at >= %s OR t.expires_at IS NULL OR t.expires_at = %s)
                )",
                MeprSubscription::$active_str,
                MeprTransaction::$complete_str,
                MeprTransaction::$confirmed_str,
                $now,
                MeprUtils::db_lifetime()
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return $count;
    }

    /**
     * Get the dynamic subscriptions milestone target.
     *
     * @param integer $current_subscriptions The current subscription count.
     *
     * @return integer The calculated target.
     */
    public static function get_subscriptions_milestone_target($current_subscriptions)
    {
        return self::get_next_milestone_target($current_subscriptions);
    }

    /**
     * Get contextual resources based on user's setup state.
     *
     * Resources are provided by the brand (e.g. MemberPress) via the
     * mepr_dashboard_contextual_resources filter. MemberPress does not supply
     * default resources; it only builds context and applies the filter.
     *
     * @param integer $limit Maximum number of resources to return.
     *
     * @return array Array of resource items with 'title', 'type', and 'url' keys.
     */
    public static function get_contextual_resources($limit = 4)
    {
        $has_membership  = MeprPostSetupChecklistHelper::has_membership();
        $has_rule        = MeprPostSetupChecklistHelper::has_rule();
        $has_gateway     = MeprPostSetupChecklistHelper::is_payment_gateway_connected();
        $has_reminder    = MeprPostSetupChecklistHelper::has_reminder();
        $is_launch_phase = self::is_launch_phase();

        $context = [
            'has_membership'  => $has_membership,
            'has_rule'        => $has_rule,
            'has_gateway'     => $has_gateway,
            'has_reminder'    => $has_reminder,
            'is_launch_phase' => $is_launch_phase,
            'limit'           => (int) $limit,
        ];

        /**
         * Filter contextual resources for the dashboard.
         *
         * The brand (e.g. MemberPress) should return an array of resources
         * based on context. Each resource must have 'title', 'type', and 'url' keys.
         *
         * @param array $resources Initial resources (empty when no fallback).
         * @param array $context   Setup state: has_gateway, has_membership, has_rule,
         *                         has_reminder, is_launch_phase, limit.
         */
        $resources = MeprHooks::apply_filters('mepr_dashboard_contextual_resources', [], $context);

        return array_slice(is_array($resources) ? $resources : [], 0, $limit);
    }
}
