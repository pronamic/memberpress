<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprAccountLoginCtrl extends MeprBaseCtrl
{
    /**
     * Loads the hooks.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_filter('submenu_file', [$this, 'highlight_menu_item']);
    }

    /**
     * Highlights the menu item.
     *
     * @param  string $submenu_file The submenu file.
     * @return string
     */
    public function highlight_menu_item($submenu_file)
    {
        global $current_screen;

        // Remove the "Account Login" menu item on all pages
        remove_submenu_page('memberpress', 'memberpress-account-login');

        // Set the highlighted menu item to "Settings"
        if ($current_screen instanceof WP_Screen && $current_screen->id == 'memberpress_page_memberpress-account-login') {
            $submenu_file = 'memberpress-options';
        }

        return $submenu_file;
    }

    /**
     * Routes the request.
     *
     * @return void
     */
    public static function route()
    {
        $account_email = get_option('mepr_authenticator_account_email');
        $secret        = get_option('mepr_authenticator_secret_token');
        $site_uuid     = get_option('mepr_authenticator_site_uuid');

        MeprView::render('/admin/account-login/ui', get_defined_vars());
    }
}
