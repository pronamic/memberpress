<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprDrmCtrl extends MeprBaseCtrl
{
    /**
     * Load the hooks for this controller
     */
    public function load_hooks()
    {
        add_action('mepr_license_activated', [$this, 'drm_license_activated']);
        add_action('mepr_license_deactivated', [$this, 'drm_license_deactivated']);
        add_action('mepr_license_expired', [$this, 'drm_license_invalid_expired']);
        add_action('mepr_license_invalidated', [$this, 'drm_license_invalid_expired']);
        add_action('mepr_drm_set_status_locked', [$this, 'drm_set_status_locked'], 10, 3);
        add_action('wp_ajax_mepr_dismiss_notice_drm', [$this, 'drm_dismiss_notice']);
        add_action('wp_ajax_mepr_dismiss_fee_notice_drm', [$this, 'drm_dismiss_fee_notice']);
        add_action('wp_ajax_mepr_drm_activate_license', [$this, 'ajax_drm_activate_license']);
        add_action('wp_ajax_mepr_drm_use_without_license', [$this, 'ajax_drm_use_without_license']);
        add_action('admin_menu', [$this, 'drm_init'], 1);
        add_action('admin_init', [$this, 'drm_throttle'], 20);
        add_action('admin_footer', [$this, 'drm_menu_append_alert']);
        add_filter('cron_schedules', [$this, 'drm_cron_schedules']);
        add_action('mepr_drm_app_fee_mapper', [$this, 'drm_app_fee_mapper']);
        add_action('mepr_drm_app_fee_revision', [$this, 'drm_app_fee_percentage_revision']);
        add_filter('site_status_tests', ['MeprDrmDebugHelper', 'site_health_debug_status'], 1);
        add_action('init', [$this, 'ensure_app_fee_mapper_scheduled']);
    }

    /**
     * DRM license activated.
     *
     * @return void
     */
    public function drm_license_activated()
    {
        delete_option('mepr_drm_no_license');
        delete_option('mepr_drm_invalid_license');
        delete_option('mepr_drm_app_fee_notice_dimissed');
        wp_clear_scheduled_hook('mepr_drm_app_fee_revision', [false]);

        // Delete DRM notices.
        $notiications = new MeprNotifications();
        $notiications->dismiss_events('mepr-drm');

        // Undo DRM Fee.
        $drm_app_fee = new MeprDrmAppFee();
        $drm_app_fee->undo_app_fee();
    }

    /**
     * DRM license deactivated.
     *
     * @return void
     */
    public function drm_license_deactivated()
    {
        wp_clear_scheduled_hook('mepr_drm_app_fee_revision', [false]);

        $drm_no_license = get_option('mepr_drm_no_license', false);

        if (! $drm_no_license) {
            delete_option('mepr_drm_invalid_license');

            // Set no license.
            update_option('mepr_drm_no_license', true);

            $drm = new MeprDrmNokey();
            $drm->create_event();
        }
    }

    /**
     * DRM license invalid expired.
     *
     * @return void
     */
    public function drm_license_invalid_expired()
    {
        $drm_invalid_license = get_option('mepr_drm_invalid_license', false);

        if (! $drm_invalid_license) {
            delete_option('mepr_drm_no_license');

            // Set invalid license.
            update_option('mepr_drm_invalid_license', true);

            $drm = new MeprDrmInvalid();
            $drm->create_event();
        }
    }

    /**
     * DRM dismiss notice.
     *
     * @return void
     */
    public static function drm_dismiss_notice()
    {
        if (check_ajax_referer('mepr_dismiss_notice', false, false) && isset($_POST['notice']) && is_string($_POST['notice'])) {
            $notice       = sanitize_key($_POST['notice'] ?? '');
            $secret       = sanitize_key($_POST['secret'] ?? '');
            $secret_parts = explode('-', $secret);
            $notice_hash  = $secret_parts[0];
            $event_hash   = $secret_parts[1];
            $notice_key   = MeprDrmHelper::prepare_dismissable_notice_key($notice);

            if ($notice_hash === sha1($notice)) {
                $event = null;
                if (sha1(MeprDrmHelper::NO_LICENSE_EVENT) === $event_hash) {
                    $event = MeprEvent::latest(MeprDrmHelper::NO_LICENSE_EVENT);
                } elseif (sha1(MeprDrmHelper::INVALID_LICENSE_EVENT) === $event_hash) {
                    $event = MeprEvent::latest(MeprDrmHelper::INVALID_LICENSE_EVENT);
                }

                if ($event && is_object($event)) {
                    if ($event->rec->id > 0) {
                        $event_data                = MeprDrmHelper::parse_event_args($event->args);
                        $event_data[ $notice_key ] = time();
                        $event->args               = json_encode($event_data);
                        $event->store();
                    }
                }
            }
        }

        wp_send_json_success();
    }

    /**
     * DRM init.
     *
     * @return void
     */
    public function drm_init()
    {
        if (! MeprDrmHelper::is_drm_enabled()) {
            return;
        }

        if (MeprDrmHelper::is_valid()) {
            return; // Bail.
        }

        if (MeprDrmHelper::is_app_fee_enabled()) {
            $drm_app_fee = new MeprDrmAppFee();
            $drm_app_fee->init_crons(); // Only schedules daily revision cron; mapper is scheduled by ensure_app_fee_mapper_scheduled().

            if (MeprDrmHelper::is_fee_unlock_available()) {
                add_action('admin_notices', [$this, 'app_fee_admin_notices'], 20);
                add_action('admin_footer', [$this, 'app_fee_modal_footer'], 99);

                return; // Bail.
            }
        }

        $drm_no_license      = get_option('mepr_drm_no_license', false);
        $drm_invalid_license = get_option('mepr_drm_invalid_license', false);

        if ($drm_no_license) {
            $drm = new MeprDrmNokey();
            $drm->run();
        } elseif ($drm_invalid_license) {
            $drm = new MeprDrmInvalid();
            $drm->run();
        }
    }

    /**
     * DRM throttle.
     *
     * @return void
     */
    public function drm_throttle()
    {
        if (! MeprDrmHelper::is_drm_enabled()) {
            return;
        }

        if (wp_doing_ajax()) {
            return;
        }

        if (MeprDrmHelper::is_locked()) {
            if (MeprDrmHelper::is_app_fee_enabled()) {
                return; // Bail.
            }

            $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

            if ('memberpress-members' === $page) {
                $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

                if ('new' === $action) {
                    wp_die(__('Sorry, you are not allowed to access this page.', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                if (MeprUtils::is_post_request() && 'create' === $action) {
                    wp_die(__('Sorry, you are not allowed to access this page.', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
        }
    }

    /**
     * AJAX DRM activate license.
     *
     * @return void
     */
    public function ajax_drm_activate_license()
    {
        if (! MeprUtils::is_post_request() || ! isset($_POST['key']) || ! is_string($_POST['key'])) {
            wp_send_json_error(sprintf(
                // Translators: %s: error message.
                __('An error occurred during activation: %s', 'memberpress'),
                __('Bad request.', 'memberpress')
            ));
        }

        if (! MeprUtils::is_logged_in_and_an_admin()) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (! check_ajax_referer('mepr_drm_activate_license', false, false)) {
            wp_send_json_error(sprintf(
                // Translators: %s: error message.
                __('An error occurred during activation: %s', 'memberpress'),
                __('Security check failed.', 'memberpress')
            ));
        }

        $mepr_options = MeprOptions::fetch();
        $license_key  = sanitize_text_field(wp_unslash($_POST['key']));

        if (!class_exists('MeprUpdateCtrl')) {
            wp_send_json_error(__('License activation is not available.', 'memberpress'));
        }

        try {
            $act = MeprUpdateCtrl::activate_license($license_key);

            $output = esc_html($act['message']);

            wp_send_json_success($output);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * DRM menu append alert.
     *
     * @return void
     */
    public function drm_menu_append_alert()
    {
        if (! MeprDrmHelper::is_locked()) {
            return;
        }
        ?>
        <script>
        jQuery(function ($) {
            $('li.toplevel_page_memberpress-drm .wp-menu-name').append('<span class="awaiting-mod"><span class="pending-count" id="meprDrmAdminMenuUnreadCount" aria-hidden="true">!</span></span>');
        });
        </script>
        <?php
    }

    /**
     * AJAX DRM use without license.
     *
     * @return void
     */
    public function ajax_drm_use_without_license()
    {
        if (! MeprUtils::is_post_request()) {
            wp_send_json_error(sprintf(
                // Translators: %s: error message.
                __('An error occurred during activation: %s', 'memberpress'),
                __('Bad request.', 'memberpress')
            ));
        }

        if (! MeprUtils::is_logged_in_and_an_admin()) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (! check_ajax_referer('mepr_drm_use_without_license', false, false)) {
            wp_send_json_error(sprintf(
                // Translators: %s: error message.
                __('An error occurred: %s', 'memberpress'),
                __('Security check failed.', 'memberpress')
            ));
        }

        // Check if fee unlock is available.
        if (! MeprDrmHelper::is_fee_unlock_available()) {
            wp_send_json_error(__('Invalid request. Please purchase or renew your license.', 'memberpress'));
            return;
        }

        try {
            $pm_id = MeprStripeGateway::has_method_with_connect_status('connected', true);
            if (! $pm_id) {
                wp_send_json_error(__('Invalid request.', 'memberpress'));
            }

            // Is it already enabled?
            if (MeprDrmHelper::is_app_fee_enabled()) {
                wp_send_json_success(['redirect_to' => admin_url('admin.php?page=memberpress-members')]);
                return;
            }

            // Check if the app fee is enabled for the country.
            $country = MeprStripeGateway::get_account_country($pm_id);
            $is_valid_country = MeprDrmHelper::is_country_unlockable_by_fee($country);

            if (! $is_valid_country) {
                wp_send_json_error(__('Invalid request.', 'memberpress'));
            }

            if (true !== MeprHooks::apply_filters('mepr_do_app_fee', true)) {
                wp_send_json_error(__('Not allowed.', 'memberpress'));
            }

            $drm_app_fee = new MeprDrmAppFee();
            $drm_app_fee->do_app_fee();

            wp_send_json_success(['redirect_to' => admin_url('admin.php?page=memberpress-members')]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * DRM set status locked.
     *
     * @param  string  $status     The status.
     * @param  integer $days       The days.
     * @param  string  $event_name The event name.
     * @return void
     */
    public function drm_set_status_locked($status, $days, $event_name)
    {
        if (! MeprDrmHelper::is_locked($status)) {
            return; // Bail.
        }

        if (! MeprStripeGateway::has_method_with_connect_status()) {
            return;
        }

        // Check if fee unlock is available.
        if (! MeprDrmHelper::is_fee_unlock_available()) {
            return; // Don't auto-apply if fee unlock is not available.
        }

        $drm_app_fee = new MeprDrmAppFee();
        $drm_app_fee->do_app_fee();

        if (! wp_doing_ajax()) {
            wp_safe_redirect(admin_url('admin.php?page=memberpress-members'));
            exit;
        }
    }

    /**
     * App fee admin notices.
     *
     * @return void
     */
    public function app_fee_admin_notices()
    {
        if (! MeprDrmHelper::is_app_fee_enabled()) {
            return;
        }

        $is_dismissed = (bool) MeprDrmHelper::is_app_fee_notice_dismissed();
        if (false === $is_dismissed) {
            echo'<style>.drm-mepr-activation-warning{display:none;}</style>';
            MeprView::render('/admin/drm/notices/fee_notice', get_defined_vars());
        }
    }

    /**
     * DRM dismiss fee notice.
     *
     * @return void
     */
    public static function drm_dismiss_fee_notice()
    {
        if (check_ajax_referer('mepr_dismiss_notice', false, false)) {
            MeprDrmHelper::dismiss_app_fee_notice();
        }

        wp_send_json_success();
    }

    /**
     * App fee modal footer.
     *
     * @return void
     */
    public function app_fee_modal_footer()
    {
        MeprView::render('/admin/drm/modal_fee');
    }

    /**
     * DRM cron schedules.
     *
     * @param  array $array The array.
     * @return array
     */
    public function drm_cron_schedules($array)
    {
        $array['mepr_drm_ten_minutes'] = [
            'interval' => 600,
            'display'  => 'Every 10 minutes',
        ];

        $array['mepr_drm_three_days'] = [
            'interval' => 259200,
            'display'  => 'Every 3 days',
        ];

        return $array;
    }

    /**
     * Ensures the unified app fee mapper cron is always scheduled.
     * Runs unconditionally — not gated by DRM or app-fee-enabled state.
     * Starts at 10-minute interval; the mapper switches to 3-day when idle.
     *
     * @return void
     */
    public function ensure_app_fee_mapper_scheduled()
    {
        if (! wp_next_scheduled('mepr_drm_app_fee_mapper', [false])) {
            wp_schedule_event(time(), 'mepr_drm_ten_minutes', 'mepr_drm_app_fee_mapper', [false]);
        }
    }

    /**
     * Unified app fee mapper. Always active, adaptive interval.
     * Adds, adjusts, or removes application fees on active Stripe subscriptions
     * based on the current total fee (DRM base + edition).
     * Runs at 10-minute interval when work is found, 3-day interval when idle.
     *
     * @return void
     */
    public function drm_app_fee_mapper()
    {
        // Skip fee processing on development/staging sites to avoid modifying production Stripe subscriptions.
        if (MeprUtils::is_dev_url() || (function_exists('wp_get_environment_type') && wp_get_environment_type() !== 'production')) {
            return;
        }

        $total_fee   = (float) MeprDrmHelper::get_total_application_fee_percentage();
        $api_version = MeprDrmHelper::is_app_fee_enabled() ? MeprDrmHelper::get_drm_app_fee_version() : '';
        $drm_app_fee = new MeprDrmAppFee();
        $has_work    = false;

        if ($total_fee > 0.0) {
            // Add fee to subs that don't have one.
            $subs = $drm_app_fee->get_all_active_subs(['mepr_app_fee_not_applied' => true]);
            if (is_array($subs) && ! empty($subs)) {
                $drm_app_fee->process_subscriptions_fee($subs, $api_version, $total_fee);
                $has_work = true;
            }

            // Adjust subs with wrong percentage.
            $subs = $drm_app_fee->get_all_active_subs(['mepr_app_fee_mismatch' => $total_fee]);
            if (is_array($subs) && ! empty($subs)) {
                $drm_app_fee->process_subscriptions_fee($subs, $api_version, $total_fee, false);
                $has_work = true;
            }

            // Update subs with version mismatch (DRM-specific, only when DRM base fee is active).
            if (MeprDrmHelper::is_app_fee_enabled()) {
                $subs = $drm_app_fee->get_all_active_subs([
                    'mepr_app_not_fee_version' => true,
                    'drm_fee_api_version'      => $api_version,
                ]);
                if (is_array($subs) && ! empty($subs)) {
                    $drm_app_fee->process_subscriptions_fee($subs, $api_version, $total_fee);
                    $has_work = true;
                }
            }
        } else {
            // Remove fee from subs that still have one.
            $subs = $drm_app_fee->get_all_active_subs(['mepr_app_fee_applied' => true]);
            if (is_array($subs) && ! empty($subs)) {
                $drm_app_fee->process_subscriptions_fee($subs, '', 0.0, true);
                $has_work = true;
            }
        }

        $this->reschedule_app_fee_mapper($has_work);
    }

    /**
     * Reschedules the app fee mapper: 10-minute interval when work remains, 3-day interval when idle.
     *
     * @param boolean $has_work Whether the current run processed any subscriptions.
     *
     * @return void
     */
    private function reschedule_app_fee_mapper(bool $has_work): void
    {
        $desired_interval = $has_work ? 'mepr_drm_ten_minutes' : 'mepr_drm_three_days';

        $timestamp = wp_next_scheduled('mepr_drm_app_fee_mapper', [false]);
        if ($timestamp) {
            $schedule = wp_get_schedule('mepr_drm_app_fee_mapper', [false]);
            if ($schedule === $desired_interval) {
                return; // Already on the correct interval.
            }
            wp_clear_scheduled_hook('mepr_drm_app_fee_mapper', [false]);
        }

        $next_run = $has_work ? time() + 600 : time() + 259200;
        wp_schedule_event($next_run, $desired_interval, 'mepr_drm_app_fee_mapper', [false]);
    }

    /**
     * DRM app fee percentage revision.
     *
     * @return void
     */
    public function drm_app_fee_percentage_revision()
    {
        MeprDrmHelper::get_application_fee_percentage(true);
    }
}
