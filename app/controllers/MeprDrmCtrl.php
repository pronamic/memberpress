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
        add_action('mepr_drm_app_fee_reversal', [$this, 'drm_app_fee_reversal']);
        add_action('mepr_drm_app_fee_revision', [$this, 'drm_app_fee_percentage_revision']);
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
        wp_clear_scheduled_hook('mepr_drm_app_fee_mapper');
        wp_clear_scheduled_hook('mepr_drm_app_fee_reversal');
        wp_clear_scheduled_hook('mepr_drm_app_fee_revision');

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
        wp_clear_scheduled_hook('mepr_drm_app_fee_mapper');
        wp_clear_scheduled_hook('mepr_drm_app_fee_reversal');
        wp_clear_scheduled_hook('mepr_drm_app_fee_revision');

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
            $notice       = sanitize_key($_POST['notice']);
            $secret       = sanitize_key($_POST['secret']);
            $secret_parts = explode('-', $secret);
            $notice_hash  = $secret_parts[0];
            $event_hash   = $secret_parts[1];
            $notice_key   = MeprDrmHelper::prepare_dismissable_notice_key($notice);

            if ($notice_hash == sha1($notice)) {
                $event = null;
                if (sha1(MeprDrmHelper::NO_LICENSE_EVENT) == $event_hash) {
                    $event = MeprEvent::latest(MeprDrmHelper::NO_LICENSE_EVENT);
                } elseif (sha1(MeprDrmHelper::INVALID_LICENSE_EVENT) == $event_hash) {
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

        if (MeprDrmHelper::is_valid()) {
            return; // Bail.
        }

        if (MeprDrmHelper::is_app_fee_enabled()) {
            add_action('admin_notices', [$this, 'app_fee_admin_notices'], 20);
            add_action('admin_footer', [$this, 'app_fee_modal_footer'], 99);

            $drm_app_fee = new MeprDrmAppFee();
            $drm_app_fee->init_crons();

            return; // Bail.
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

        if (wp_doing_ajax()) {
            return;
        }

        if (MeprDrmHelper::is_locked()) {
            if (MeprDrmHelper::is_app_fee_enabled()) {
                return; // Bail.
            }

            $page = isset($_GET['page']) ? $_GET['page'] : ''; // phpcs:ignore WordPress.Security.NonceVerification

            if ('memberpress-members' === $page) {
                $action = isset($_GET['action']) ? $_GET['action'] : ''; // phpcs:ignore WordPress.Security.NonceVerification

                if ('new' == $action) {
                    wp_die(__('Sorry, you are not allowed to access this page.', 'memberpress')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                if (MeprUtils::is_post_request() && 'create' == $action) {
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

        ob_start();
        ?>

  <span class="awaiting-mod">
    <span class="pending-count" id="meprDrmAdminMenuUnreadCount" aria-hidden="true"><?php echo __('!', 'memberpress'); ?></span></span>
  </span>

        <?php $output = ob_get_clean(); ?>

  <script>
  jQuery(document).ready(function($) {
    $('li.toplevel_page_memberpress-drm .wp-menu-name').append(`<?php echo $output; ?>`);
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
            'interval' => 300,
            'display'  => __('Every 10 minutes', 'memberpress'),
        ];

        return $array;
    }

    /**
     * DRM app fee mapper.
     *
     * @return void
     */
    public function drm_app_fee_mapper()
    {
        if (! MeprDrmHelper::is_app_fee_enabled()) {
            return; // Bail.
        }

        $api_version        = MeprDrmHelper::get_drm_app_fee_version();
        $current_percentage = MeprDrmHelper::get_application_fee_percentage();
        $meprdrm            = new MeprDrmAppFee();

        // --Add fee--
        $subscriptions = $meprdrm->get_all_active_subs(['mepr_app_fee_not_applied' => true]);
        $meprdrm->process_subscriptions_fee($subscriptions, $api_version, $current_percentage);

        // --Update fee--
        $args = [
            'mepr_app_not_fee_version' => true,
            'drm_fee_api_version'  => $api_version,
        ];
        $subscriptions = $meprdrm->get_all_active_subs($args);
        $meprdrm->process_subscriptions_fee($subscriptions, $api_version, $current_percentage);
    }

    /**
     * DRM app fee reversal.
     *
     * @return void
     */
    public function drm_app_fee_reversal()
    {

        if (! MeprDrmHelper::is_valid()) {
            return; // Bail.
        }

        $meprdrm            = new MeprDrmAppFee();
        $api_version        = MeprDrmHelper::get_drm_app_fee_version();
        $current_percentage = MeprDrmHelper::get_application_fee_percentage();
        $subscriptions      = $meprdrm->get_all_active_subs(['mepr_app_fee_applied' => true]);
        $meprdrm->process_subscriptions_fee($subscriptions, $api_version, 0, true);
    }

    /**
     * DRM app fee percentage revision.
     *
     * @return void
     */
    public function drm_app_fee_percentage_revision()
    {
        $current_version    = MeprDrmHelper::get_drm_app_fee_version();
        $current_percentage = MeprDrmHelper::get_application_fee_percentage();
        MeprDrmHelper::get_application_fee_percentage(true);
    }
}
