<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Controller for the MemberPress Dashboard.
 *
 * The Dashboard serves as the central hub for MemberPress users,
 * providing quick stats, next best actions, and guidance.
 */
class MeprDashboardCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for the dashboard functionality.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // AJAX actions for dashboard components.
        add_action('wp_ajax_mepr_dashboard_dismiss_setup_notice', [$this, 'ajax_dismiss_setup_notice']);
        add_action('wp_ajax_mepr_dashboard_dismiss_nba', [$this, 'ajax_dismiss_nba']);
        add_action('wp_ajax_mepr_dashboard_celebrate_first_sale', [$this, 'ajax_celebrate_first_sale']);

        // Cache invalidation hooks.
        add_action('mepr_subscription_transition_status', [$this, 'clear_dashboard_cache']);
        add_action('mepr_txn_transition_status', [$this, 'clear_dashboard_cache']);
        add_action('mepr_transaction_expired', [$this, 'clear_dashboard_cache']);
        add_action('mepr_event_transaction_expired', [$this, 'clear_dashboard_cache']);
    }

    /**
     * Clear the dashboard cache.
     *
     * @return void
     */
    public function clear_dashboard_cache()
    {
        MeprDashboardHelper::clear_cache();
    }

    /**
     * Enqueue scripts and styles for the dashboard page.
     *
     * @param string $hook The current admin page hook.
     *
     * @return void
     */
    public function enqueue_scripts($hook)
    {
        if ($hook !== 'toplevel_page_memberpress-dashboard' && $hook !== 'memberpress_page_memberpress-dashboard') {
            return;
        }

        wp_enqueue_style(
            'mepr-dashboard',
            MEPR_CSS_URL . '/admin-dashboard.css',
            [],
            MEPR_VERSION
        );

        wp_enqueue_script(
            'mepr-dashboard',
            MEPR_JS_URL . '/admin_dashboard.js',
            [],
            MEPR_VERSION,
            true
        );

        wp_localize_script('mepr-dashboard', 'MeprDashboard', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('mepr_dashboard'),
            'i18n'     => [
                'dismiss'               => __('Dismiss', 'memberpress'),
                'dismiss_setup_confirm' => __('Are you sure you want to dismiss the setup checklist? You can always access setup options from the Settings menu.', 'memberpress'),
            ],
        ]);
    }

    /**
     * Render the main dashboard page.
     *
     * @return void
     */
    public static function render()
    {
        if (!MeprUtils::is_mepr_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'memberpress'));
        }

        // On first dashboard load, auto-skip celebration for existing users with revenue.
        self::maybe_skip_first_sale_celebration_for_existing_users();

        MeprView::render('/admin/dashboard/main');
    }

    /**
     * Skip the first sale celebration for existing users who already have revenue.
     *
     * This prevents showing the celebration modal to long-time users who already
     * have transactions when they first visit the new dashboard.
     *
     * @return void
     */
    private static function maybe_skip_first_sale_celebration_for_existing_users()
    {
        // Only run this check once.
        if (get_option('mepr_dashboard_first_load_checked', false)) {
            return;
        }

        update_option('mepr_dashboard_first_load_checked', true);

        // If there's already revenue on first dashboard load, skip the celebration.
        if (MeprDashboardHelper::has_any_revenue()) {
            MeprDashboardHelper::mark_first_sale_celebrated();
        }
    }

    /**
     * AJAX handler to dismiss the setup notice.
     *
     * @return void
     */
    public function ajax_dismiss_setup_notice()
    {
        check_ajax_referer('mepr_dashboard', 'nonce');

        if (!MeprUtils::is_mepr_admin()) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'memberpress'));
        }

        update_option('mepr_dashboard_setup_notice_dismissed', true);

        // Also dismiss the Post-Setup Checklist sidebar so it doesn't appear on other pages.
        MeprPostSetupChecklistHelper::dismiss();

        wp_send_json_success([
            'message' => __('Setup notice dismissed.', 'memberpress'),
        ]);
    }

    /**
     * AJAX handler to dismiss a Next Best Action recommendation.
     *
     * @return void
     */
    public function ajax_dismiss_nba()
    {
        check_ajax_referer('mepr_dashboard', 'nonce');

        if (!MeprUtils::is_mepr_admin()) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'memberpress'));
        }

        $action_id = isset($_POST['action_id']) ? sanitize_key($_POST['action_id']) : '';

        if (empty($action_id)) {
            wp_send_json_error(__('Invalid action ID.', 'memberpress'));
        }

        $dismissed = get_option('mepr_dashboard_nba_dismissed', []);

        if (!is_array($dismissed)) {
            $dismissed = [];
        }

        if (!in_array($action_id, $dismissed, true)) {
            $dismissed[] = $action_id;
            update_option('mepr_dashboard_nba_dismissed', $dismissed);
        }

        wp_send_json_success([
            'message'   => __('Recommendation dismissed.', 'memberpress'),
            'action_id' => $action_id,
        ]);
    }

    /**
     * AJAX handler to mark the first sale celebration as shown.
     *
     * @return void
     */
    public function ajax_celebrate_first_sale()
    {
        check_ajax_referer('mepr_dashboard', 'nonce');

        if (!MeprUtils::is_mepr_admin()) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'memberpress'));
        }

        MeprDashboardHelper::mark_first_sale_celebrated();

        wp_send_json_success([
            'message' => __('First sale celebration acknowledged.', 'memberpress'),
        ]);
    }
}
