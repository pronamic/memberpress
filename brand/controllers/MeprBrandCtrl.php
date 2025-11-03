<?php

class MeprBrandCtrl extends MeprBaseCtrl
{
    /**
     * Loads the hooks.
     */
    public function load_hooks(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Application fee.
        add_filter('pre_set_site_transient_mepr_license_info', [$this, 'maybe_update_application_fee']);
        add_action('mepr_process_application_fee', [$this, 'process_application_fee']);
    }

    /**
     * Enqueues admin scripts.
     */
    public function admin_enqueue_scripts(): void
    {
        wp_enqueue_style('mepr-brand-admin-shared', MEPR_BRAND_URL . '/css/admin-shared.css', [], MEPR_VERSION);
    }

    /**
     * Enqueues front-end scripts.
     */
    public function enqueue_scripts(): void
    {
        if (is_admin_bar_showing() && MeprUtils::is_mepr_admin()) {
            wp_enqueue_style(
                'mepr-fontello-memberpress',
                MEPR_FONTS_URL . '/fontello/css/memberpress.css',
                [],
                MEPR_VERSION
            );

            wp_enqueue_style('mepr-brand-admin-bar', MEPR_BRAND_URL . '/css/admin-bar.css', [], MEPR_VERSION);
        }
    }

    /**
     * Handles application fee updates when license edition changes.
     *
     * @param  array $license_info The license information.
     * @return array The license information.
     */
    public function maybe_update_application_fee(array $license_info): array
    {
        $edition = $license_info['product_slug'] ?? '';
        $action  = '';

        if (empty($edition)) {
            return $license_info;
        }

        if (MeprDrmHelper::is_app_fee_enabled()) {
            return $license_info;
        }

        $drm_app_fee = new MeprDrmAppFee();

        if ('memberpress-launch' === $edition) {
            $subs = $drm_app_fee->get_all_active_subs(['mepr_app_fee_not_applied' => true], 1);
            if (!empty($subs)) {
                $action = 'add';
            }
        } else {
            $subs = $drm_app_fee->get_all_active_subs(['mepr_app_fee_applied' => true], 1);
            if (!empty($subs)) {
                $action = 'remove';
            }
        }

        if ($action && !wp_next_scheduled('mepr_process_application_fee', [$action])) {
            wp_schedule_event(time(), 'mepr_drm_ten_minutes', 'mepr_process_application_fee', [$action]);
        }

        return $license_info;
    }

    /**
     * Processes the application fee for the active subscriptions.
     *
     * @param string $action The action to perform ('add' or 'remove').
     */
    public function process_application_fee(string $action): void
    {
        $drm_app_fee = new MeprDrmAppFee();
        $subs        = [];

        if ('remove' === $action) {
            $subs = $drm_app_fee->get_all_active_subs(['mepr_app_fee_applied' => true]);
            $drm_app_fee->process_subscriptions_fee($subs, '', 0.0, true);
        } elseif ('add' === $action) {
            $fee_percentage = MeprHooks::apply_filters('mepr_stripe_default_application_fee_percentage', 0.0);

            if ($fee_percentage > 0.0) {
                $subs = $drm_app_fee->get_all_active_subs(['mepr_app_fee_not_applied' => true]);
                $drm_app_fee->process_subscriptions_fee($subs, '', $fee_percentage);
            }
        }

        if (!is_array($subs) || empty($subs)) {
            wp_clear_scheduled_hook('mepr_process_application_fee', [$action]);
        }
    }
}
