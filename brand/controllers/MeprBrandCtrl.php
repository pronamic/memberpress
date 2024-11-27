<?php

class MeprBrandCtrl extends MeprBaseCtrl
{
    public function load_hooks()
    {
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function admin_enqueue_scripts()
    {
        wp_enqueue_style('mepr-brand-admin-shared', MEPR_BRAND_URL . '/css/admin-shared.css', [], MEPR_VERSION);
    }

    public function enqueue_scripts()
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
}
