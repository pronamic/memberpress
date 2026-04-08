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
        add_filter('mepr_display_order_bumps_upsell', [$this, 'display_order_bumps_upsell']);
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
     * Determine whether to display the Order Bumps upsell on the Edit Membership page.
     *
     * @param  boolean $display Whether to display.
     * @return boolean
     */
    public function display_order_bumps_upsell(bool $display): bool
    {
        if (
            !defined('MCOB_VERSION')
            && class_exists('MeprOnboardingHelper')
            && !MeprOnboardingHelper::is_pro_edition(MEPR_EDITION)
            && !MeprOnboardingHelper::is_elite_edition(MEPR_EDITION)
            && !MeprOnboardingHelper::is_scale_edition(MEPR_EDITION)
        ) {
            $display = true;
        }

        return $display;
    }
}
