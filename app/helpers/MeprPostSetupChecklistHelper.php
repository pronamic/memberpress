<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Helper class for the Post-Setup Checklist feature.
 *
 * Provides methods to detect progress, calculate completion percentages,
 * and manage checklist state.
 */
class MeprPostSetupChecklistHelper
{
    /**
     * Option key for storing checklist dismissed state.
     *
     * @var string
     */
    public static $dismissed_option = 'mepr_post_setup_checklist_dismissed';

    /**
     * Option key for storing checklist minimized state.
     *
     * @var string
     */
    public static $minimized_option = 'mepr_post_setup_checklist_minimized';

    /**
     * Option key for storing manually completed steps.
     *
     * @var string
     */
    public static $completed_steps_option = 'mepr_post_setup_checklist_completed';

    /**
     * Option key for storing skipped steps.
     *
     * @var string
     */
    public static $skipped_steps_option = 'mepr_post_setup_checklist_skipped';

    /**
     * Get all checklist steps with their current status.
     *
     * @return array Array of step data including id, title, description, completed status, and action URL.
     */
    public static function get_steps()
    {
        $skipped_steps = self::get_skipped_steps();

        $steps = [
            [
                'id'          => 'install_plugin',
                'title'       => __('Install Plugin', 'memberpress'),
                'description' => __('MemberPress plugin is installed and active.', 'memberpress'),
                'completed'   => true, // Always completed since checklist requires MemberPress to be installed.
                'skipped'     => false,
                'skippable'   => false,
                'action_url'  => '',
                'action_text' => '',
                'priority'    => 1,
            ],
            [
                'id'          => 'activate_license',
                'title'       => __('Activate license key', 'memberpress'),
                'description' => __('Enter your license key to enable updates and support.', 'memberpress'),
                'completed'   => MeprUpdateCtrl::is_activated(),
                'skipped'     => in_array('activate_license', $skipped_steps, true),
                'skippable'   => true,
                'action_url'  => admin_url('admin.php?page=memberpress-options#license'),
                'action_text' => __('Activate License', 'memberpress'),
                'priority'    => 2,
            ],
            [
                'id'          => 'connect_payment_gateway',
                'title'       => __('Connect live payment gateway', 'memberpress'),
                'description' => __('Set up Stripe or PayPal to start accepting payments.', 'memberpress'),
                'completed'   => self::is_payment_gateway_connected(),
                'skipped'     => in_array('connect_payment_gateway', $skipped_steps, true),
                'skippable'   => true,
                'action_url'  => admin_url('admin.php?page=memberpress-options#integration'),
                'action_text' => __('Connect Gateway', 'memberpress'),
                'priority'    => 3,
            ],
            [
                'id'          => 'create_membership',
                'title'       => __('Create your first Membership', 'memberpress'),
                'description' => __('Create a membership product for your customers to purchase.', 'memberpress'),
                'completed'   => self::has_membership(),
                'skipped'     => in_array('create_membership', $skipped_steps, true),
                'skippable'   => false,
                'action_url'  => admin_url('post-new.php?post_type=memberpressproduct'),
                'action_text' => __('Create Membership', 'memberpress'),
                'priority'    => 4,
            ],
            [
                'id'          => 'create_rule',
                'title'       => __('Create your first Rule', 'memberpress'),
                'description' => __('Protect your content by setting up access rules.', 'memberpress'),
                'completed'   => self::has_rule(),
                'skipped'     => in_array('create_rule', $skipped_steps, true),
                'skippable'   => true,
                'action_url'  => admin_url('post-new.php?post_type=memberpressrule'),
                'action_text' => __('Create Rule', 'memberpress'),
                'priority'    => 5,
            ],
            [
                'id'          => 'schedule_reminder',
                'title'       => __('Schedule a Reminder', 'memberpress'),
                'description' => __('Set up automated email reminders for your members.', 'memberpress'),
                'completed'   => self::has_reminder(),
                'skipped'     => in_array('schedule_reminder', $skipped_steps, true),
                'skippable'   => true,
                'action_url'  => admin_url('post-new.php?post_type=mp-reminder'),
                'action_text' => __('Create Reminder', 'memberpress'),
                'priority'    => 6,
            ],
            [
                'id'          => 'first_sale',
                'title'       => __('Make your first sale', 'memberpress'),
                'description' => __('Complete a transaction through your live payment gateway.', 'memberpress'),
                'completed'   => self::has_transaction(),
                'skipped'     => in_array('first_sale', $skipped_steps, true),
                'skippable'   => true,
                'action_url'  => '',
                'action_text' => '',
                'priority'    => 7,
            ],
        ];

        return MeprHooks::apply_filters('mepr_post_setup_checklist_steps', $steps);
    }

    /**
     * Get optional checklist steps.
     *
     * These steps don't count toward progress and can be completed in any order.
     *
     * @return array Array of optional step data.
     */
    public static function get_optional_steps()
    {
        $steps = [
            [
                'id'          => 'create_coupon',
                'title'       => __('Create a coupon', 'memberpress'),
                'description' => __('Create a discount coupon for promotions.', 'memberpress'),
                'completed'   => self::has_coupon(),
                'action_url'  => admin_url('post-new.php?post_type=memberpresscoupon'),
                'action_text' => __('Create Coupon', 'memberpress'),
            ],
            [
                'id'           => 'configure_email',
                'title'        => __('Configure Email Deliverability', 'memberpress'),
                'description'  => __('Set up SMTP to ensure emails reach your members.', 'memberpress'),
                'completed'    => self::is_smtp_configured(),
                'action_url'   => self::get_smtp_action_url(),
                'action_text'  => self::get_smtp_action_text(),
                'requires'     => 'wp-mail-smtp',
                'installed'    => self::is_wp_mail_smtp_installed(),
                'active'       => self::is_wp_mail_smtp_active(),
                'plugin_file'  => 'wp-mail-smtp/wp_mail_smtp.php',
                'download_url' => 'https://downloads.wordpress.org/plugin/wp-mail-smtp.latest-stable.zip',
                'addon_type'   => 'plugin',
            ],
            [
                'id'           => 'install_aioseo',
                'title'        => __('Set up SEO', 'memberpress'),
                'description'  => __('Optimize your site for search engines with All in One SEO.', 'memberpress'),
                'completed'    => self::is_aioseo_active(),
                'action_url'   => self::get_aioseo_action_url(),
                'action_text'  => self::get_aioseo_action_text(),
                'requires'     => 'all-in-one-seo-pack',
                'installed'    => self::is_aioseo_installed(),
                'active'       => self::is_aioseo_active(),
                'plugin_file'  => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
                'download_url' => 'https://downloads.wordpress.org/plugin/all-in-one-seo-pack.latest-stable.zip',
                'addon_type'   => 'plugin',
            ],
            [
                'id'           => 'install_optinmonster',
                'title'        => __('Grow your email list', 'memberpress'),
                'description'  => __('Convert visitors into subscribers with OptinMonster.', 'memberpress'),
                'completed'    => self::is_optinmonster_active(),
                'action_url'   => self::get_optinmonster_action_url(),
                'action_text'  => self::get_optinmonster_action_text(),
                'requires'     => 'optinmonster',
                'installed'    => self::is_optinmonster_installed(),
                'active'       => self::is_optinmonster_active(),
                'plugin_file'  => 'optinmonster/optin-monster-wp-api.php',
                'download_url' => 'https://downloads.wordpress.org/plugin/optinmonster.latest-stable.zip',
                'addon_type'   => 'plugin',
            ],
        ];

        return MeprHooks::apply_filters('mepr_post_setup_checklist_optional_steps', $steps);
    }

    /**
     * Check if at least one coupon exists.
     *
     * @return boolean True if a coupon exists.
     */
    public static function has_coupon()
    {
        $counts = wp_count_posts(MeprCoupon::$cpt);

        return (int) ($counts->publish ?? 0) > 0;
    }

    /**
     * Check if an addon is installable based on user's license plan.
     *
     * @param string $addon_slug The addon slug.
     *
     * @return boolean True if installable.
     */
    public static function is_addon_installable($addon_slug)
    {
        $addons = MeprUpdateCtrl::addons(true, false, true);

        if (!empty($addons) && isset($addons->{$addon_slug})) {
            return !empty($addons->{$addon_slug}->installable);
        }

        return false;
    }

    /**
     * Get the download URL for an addon.
     *
     * @param string $addon_slug The addon slug.
     *
     * @return string Download URL or empty string if not available.
     */
    public static function get_addon_download_url($addon_slug)
    {
        $addons = MeprUpdateCtrl::addons(true, false, true);

        if (!empty($addons) && isset($addons->{$addon_slug})) {
            return $addons->{$addon_slug}->url ?? '';
        }

        return '';
    }

    /**
     * Check if WP Mail SMTP plugin is installed.
     *
     * @return boolean True if installed.
     */
    public static function is_wp_mail_smtp_installed()
    {
        return is_dir(WP_PLUGIN_DIR . '/wp-mail-smtp');
    }

    /**
     * Check if WP Mail SMTP plugin is active.
     *
     * @return boolean True if active.
     */
    public static function is_wp_mail_smtp_active()
    {
        return is_plugin_active('wp-mail-smtp/wp_mail_smtp.php');
    }

    /**
     * Check if SMTP is configured (WP Mail SMTP is active and configured).
     *
     * @return boolean True if SMTP is configured.
     */
    public static function is_smtp_configured()
    {
        if (!self::is_wp_mail_smtp_active()) {
            return false;
        }

        // Check if WP Mail SMTP has a mailer configured (not default PHP mail).
        $wp_mail_smtp_options = get_option('wp_mail_smtp', []);

        if (empty($wp_mail_smtp_options)) {
            return false;
        }

        $mailer = $wp_mail_smtp_options['mail']['mailer'] ?? 'mail';

        // 'mail' is the default PHP mail, consider configured if using any other mailer.
        return $mailer !== 'mail';
    }

    /**
     * Get the action URL for email deliverability step.
     *
     * @return string Action URL.
     */
    public static function get_smtp_action_url()
    {
        if (self::is_wp_mail_smtp_active()) {
            return admin_url('admin.php?page=wp-mail-smtp');
        }

        // Open WP Mail SMTP plugin details modal within WP admin.
        return admin_url('plugin-install.php?tab=plugin-information&plugin=wp-mail-smtp&TB_iframe=true');
    }

    /**
     * Get the action text for email deliverability step.
     *
     * @return string Action text.
     */
    public static function get_smtp_action_text()
    {
        if (self::is_wp_mail_smtp_active()) {
            return __('Configure SMTP', 'memberpress');
        }

        if (self::is_wp_mail_smtp_installed()) {
            return __('Activate Plugin', 'memberpress');
        }

        return __('Install Plugin', 'memberpress');
    }

    /**
     * Check if All in One SEO plugin is installed.
     *
     * @return boolean True if installed.
     */
    public static function is_aioseo_installed()
    {
        return is_dir(WP_PLUGIN_DIR . '/all-in-one-seo-pack');
    }

    /**
     * Check if All in One SEO plugin is active.
     *
     * @return boolean True if active.
     */
    public static function is_aioseo_active()
    {
        return is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php');
    }

    /**
     * Get the action URL for All in One SEO step.
     *
     * @return string Action URL.
     */
    public static function get_aioseo_action_url()
    {
        if (self::is_aioseo_active()) {
            return admin_url('admin.php?page=aioseo');
        }

        // Open AIOSEO plugin details modal within WP admin.
        return admin_url('plugin-install.php?tab=plugin-information&plugin=all-in-one-seo-pack&TB_iframe=true');
    }

    /**
     * Get the action text for All in One SEO step.
     *
     * @return string Action text.
     */
    public static function get_aioseo_action_text()
    {
        if (self::is_aioseo_active()) {
            return __('Configure SEO', 'memberpress');
        }

        if (self::is_aioseo_installed()) {
            return __('Activate Plugin', 'memberpress');
        }

        return __('Install Plugin', 'memberpress');
    }

    /**
     * Check if OptinMonster plugin is installed.
     *
     * @return boolean True if installed.
     */
    public static function is_optinmonster_installed()
    {
        return is_dir(WP_PLUGIN_DIR . '/optinmonster');
    }

    /**
     * Check if OptinMonster plugin is active.
     *
     * @return boolean True if active.
     */
    public static function is_optinmonster_active()
    {
        return is_plugin_active('optinmonster/optin-monster-wp-api.php');
    }

    /**
     * Get the action URL for OptinMonster step.
     *
     * @return string Action URL.
     */
    public static function get_optinmonster_action_url()
    {
        if (self::is_optinmonster_active()) {
            return admin_url('admin.php?page=optin-monster-dashboard');
        }

        // Open OptinMonster plugin details modal within WP admin.
        return admin_url('plugin-install.php?tab=plugin-information&plugin=optinmonster&TB_iframe=true');
    }

    /**
     * Get the action text for OptinMonster step.
     *
     * @return string Action text.
     */
    public static function get_optinmonster_action_text()
    {
        if (self::is_optinmonster_active()) {
            return __('Configure OptinMonster', 'memberpress');
        }

        if (self::is_optinmonster_installed()) {
            return __('Activate Plugin', 'memberpress');
        }

        return __('Install Plugin', 'memberpress');
    }

    /**
     * Get the current progress percentage.
     *
     * Calculates progress based on the number of completed steps.
     *
     * @return integer Progress percentage (0-100).
     */
    public static function get_progress_percentage()
    {
        $steps           = self::get_steps();
        $total_steps     = count($steps);
        $done_steps      = 0;

        foreach ($steps as $step) {
            // Count both completed and skipped steps toward progress.
            if ($step['completed'] || $step['skipped']) {
                $done_steps++;
            }
        }

        if ($total_steps === 0) {
            return 0;
        }

        return (int) round(($done_steps / $total_steps) * 100);
    }

    /**
     * Get the count of completed or skipped steps.
     *
     * @return integer Number of completed or skipped steps.
     */
    public static function get_completed_count()
    {
        $steps = self::get_steps();
        $count = 0;

        foreach ($steps as $step) {
            // Count both completed and skipped steps.
            if ($step['completed'] || $step['skipped']) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get the total number of steps.
     *
     * @return integer Total number of steps.
     */
    public static function get_total_count()
    {
        return count(self::get_steps());
    }

    /**
     * Check if a payment gateway is connected.
     *
     * @return boolean True if a payment gateway is connected.
     */
    public static function is_payment_gateway_connected()
    {
        $mepr_options = MeprOptions::fetch();

        return count($mepr_options->integrations) > 0;
    }

    /**
     * Check if at least one membership product exists.
     *
     * @return boolean True if a membership exists.
     */
    public static function has_membership()
    {
        return MeprProduct::count() > 0;
    }

    /**
     * Check if at least one access rule exists.
     *
     * @return boolean True if a rule exists.
     */
    public static function has_rule()
    {
        return MeprRule::count() > 0;
    }

    /**
     * Check if at least one reminder is configured.
     *
     * @return boolean True if a reminder exists.
     */
    public static function has_reminder()
    {
        $reminders = MeprCptModel::all('MeprReminder');
        return !empty($reminders);
    }

    /**
     * Check if there's at least one completed or confirmed transaction.
     *
     * @return boolean True if a successful transaction exists.
     */
    public static function has_transaction()
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
                "SELECT COUNT(*) FROM {$mepr_db->transactions} WHERE status IN (%s, %s)",
                MeprTransaction::$complete_str,
                MeprTransaction::$confirmed_str
            )
        );

        return $count > 0;
    }

    /**
     * Check if the checklist has been dismissed.
     *
     * @return boolean True if dismissed.
     */
    public static function is_dismissed()
    {
        return (bool) get_option(self::$dismissed_option, false);
    }

    /**
     * Dismiss the checklist globally.
     *
     * @return void
     */
    public static function dismiss()
    {
        update_option(self::$dismissed_option, true);
    }

    /**
     * Check if the checklist is minimized (minimized by default).
     *
     * @return boolean True if minimized.
     */
    public static function is_minimized()
    {
        // Check if user just clicked a step link (cookie is set).
        // In this case, keep the checklist expanded.
        if (self::should_keep_expanded()) {
            return false;
        }

        // Default to minimized. Only expanded when explicitly set to false.
        $expanded = get_option(self::$minimized_option, null);

        // If option doesn't exist or is true, sidebar is minimized.
        // If option is explicitly false, sidebar is expanded.
        return $expanded !== false;
    }

    /**
     * Check if the checklist should be kept expanded (user clicked a step link).
     *
     * @return boolean True if the "keep expanded" cookie is set.
     */
    public static function should_keep_expanded()
    {
        return isset($_COOKIE['mepr_checklist_keep_expanded']);
    }

    /**
     * Set the minimized state globally.
     *
     * @param boolean $minimized Whether to minimize.
     *
     * @return void
     */
    public static function set_minimized($minimized)
    {
        if ($minimized) {
            // Minimized state - delete option to use default (minimized).
            delete_option(self::$minimized_option);
        } else {
            // Expanded state - store false to indicate expanded.
            update_option(self::$minimized_option, false);
        }
    }

    /**
     * Get the list of skipped step IDs.
     *
     * @return array Array of skipped step IDs.
     */
    public static function get_skipped_steps()
    {
        $skipped = get_option(self::$skipped_steps_option, []);

        return is_array($skipped) ? $skipped : [];
    }

    /**
     * Skip a step.
     *
     * @param string $step_id The step ID to skip.
     *
     * @return void
     */
    public static function skip_step($step_id)
    {
        $skipped = self::get_skipped_steps();

        if (!in_array($step_id, $skipped, true)) {
            $skipped[] = $step_id;
            update_option(self::$skipped_steps_option, $skipped);
        }
    }

    /**
     * Check if the checklist is complete (all steps done).
     *
     * @return boolean True if all steps are completed.
     */
    public static function is_complete()
    {
        return self::get_completed_count() === self::get_total_count();
    }

    /**
     * Check if we're on a MemberPress core admin page.
     *
     * @return boolean True if on a MemberPress core admin page.
     */
    public static function is_memberpress_core_page()
    {
        if (!is_admin()) {
            return false;
        }

        $screen = get_current_screen();

        if (!$screen) {
            return false;
        }

        // Check for MemberPress core post types (memberpressproduct, memberpressrule, etc.).
        if (!empty($screen->post_type) && preg_match('/^memberpress/', $screen->post_type)) {
            return true;
        }

        // Check for MemberPress core admin pages (memberpress-options, memberpress-reports, etc.).
        if (preg_match('/^memberpress_page_memberpress-/', $screen->id)) {
            return true;
        }

        // Check for MemberPress reminder post type.
        if ($screen->post_type === 'mp-reminder') {
            return true;
        }

        // Check for addon post types on their main listing pages (edit.php screen).
        // Brand-specific addon CPTs are added via filter.
        $addon_post_types = MeprHooks::apply_filters('mepr_post_setup_checklist_addon_post_types', []);

        if ($screen->base === 'edit' && in_array($screen->post_type, $addon_post_types, true)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the checklist should be shown.
     *
     * @return boolean True if the checklist should be displayed.
     */
    public static function should_show()
    {
        // Don't show if user doesn't have admin access.
        if (!MeprUtils::is_mepr_admin()) {
            return false;
        }

        // Don't show if dismissed.
        if (self::is_dismissed()) {
            return false;
        }

        // Don't show if already complete.
        if (self::is_complete()) {
            return false;
        }

        // Don't show on the onboarding page.
        if (MeprOnboardingCtrl::is_onboarding_page()) {
            return false;
        }

        // Only show on MemberPress core admin pages.
        if (!self::is_memberpress_core_page()) {
            return false;
        }

        return true;
    }

    /**
     * Get the next incomplete step.
     *
     * @return array|null The next incomplete step or null if all complete.
     */
    public static function get_next_step()
    {
        $steps = self::get_steps();

        foreach ($steps as $step) {
            if (!$step['completed']) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Get grouped steps (completed first, then pending).
     *
     * @return array Array with 'completed' and 'pending' keys.
     */
    public static function get_grouped_steps()
    {
        $steps     = self::get_steps();
        $completed = [];
        $pending   = [];
        $skipped   = [];

        foreach ($steps as $step) {
            if ($step['completed']) {
                $completed[] = $step;
            } elseif ($step['skipped']) {
                $skipped[] = $step;
            } else {
                $pending[] = $step;
            }
        }

        return [
            'completed' => $completed,
            'pending'   => $pending,
            'skipped'   => $skipped,
        ];
    }
}
