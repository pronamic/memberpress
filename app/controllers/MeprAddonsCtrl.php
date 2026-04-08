<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprAddonsCtrl extends MeprBaseCtrl
{
    /**
     * Loads the hooks.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_mepr_addon_activate', [$this, 'ajax_addon_activate']);
        add_action('wp_ajax_mepr_addon_deactivate', [$this, 'ajax_addon_deactivate']);
        add_action('wp_ajax_mepr_addon_install', [$this, 'ajax_addon_install']);
        add_filter('wp_mail_smtp_core_get_upgrade_link', [$this, 'smtp_affiliate_link']);
        add_filter('monsterinsights_shareasale_id', [$this, 'monsterinsights_shareasale_id']);
    }

    /**
     * Routes the request.
     *
     * @return void
     */
    public static function route()
    {
        $force      = isset($_GET['refresh']) && $_GET['refresh'] === 'true';
        $addons     = class_exists('MeprUpdateCtrl') ? MeprUpdateCtrl::addons(true, $force, true) : null;
        $plugins    = get_plugins();
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'addons';
        wp_cache_delete('plugins', 'plugins');

        // Get registered tabs.
        $registered_tabs = self::get_registered_tabs();

        MeprView::render('/admin/addons/ui', get_defined_vars());
    }

    /**
     * Get registered tabs for the add-ons page.
     *
     * @return array Array of registered tabs.
     */
    public static function get_registered_tabs()
    {
        // Default add-ons tab.
        $tabs = [
            'addons' => [
                'id'         => 'addons',
                'label'      => __('Add-ons', 'memberpress'),
                'capability' => 'install_plugins',
                'priority'   => 10,
                'callback'   => null, // Rendered in the main view.
            ],
        ];

        /**
         * Filter to register custom tabs for the add-ons page.
         *
         * Allows brands/plugins to register tabs in a structured way.
         *
         * @param array $tabs Array of tab configurations.
         *
         * Each tab should have:
         * - id (string, required): Unique tab identifier
         * - label (string, required): Tab label (translated)
         * - capability (string, optional): Required capability, default 'manage_options'
         * - priority (int, optional): Display order, default 10
         * - callback (callable, optional): Function to render tab content
         */
        $registered = apply_filters('mepr_addons_registered_tabs', []);

        // Validate and merge registered tabs.
        foreach ($registered as $tab) {
            if (!is_array($tab) || !isset($tab['id'], $tab['label'])) {
                continue;
            }

            $tab_id = sanitize_key($tab['id']);

            $tabs[$tab_id] = [
                'id'         => $tab_id,
                'label'      => $tab['label'],
                'capability' => isset($tab['capability']) ? $tab['capability'] : 'manage_options',
                'priority'   => isset($tab['priority']) ? absint($tab['priority']) : 10,
                'callback'   => isset($tab['callback']) && is_callable($tab['callback']) ? $tab['callback'] : null,
            ];
        }

        // Sort by priority.
        uasort($tabs, function ($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $tabs;
    }

    /**
     * Enqueues the scripts.
     *
     * @param  string $hook The hook.
     * @return void
     */
    public function enqueue_scripts($hook)
    {
        if (preg_match('/_page_memberpress-addons$/', $hook)) {
            wp_enqueue_style('mepr-addons-css', MEPR_CSS_URL . '/admin-addons.css', [], MEPR_VERSION);
            wp_enqueue_script('list-js', MEPR_JS_URL . '/vendor/list.min.js', [], '1.5.0');
            wp_enqueue_script('jquery-match-height', MEPR_JS_URL . '/vendor/jquery.matchHeight-min.js', [], '0.7.2');
            wp_enqueue_script('mepr-addons-js', MEPR_JS_URL . '/admin_addons.js', ['list-js', 'jquery-match-height'], MEPR_VERSION);

            wp_localize_script('mepr-addons-js', 'MeprAddons', [
                'ajax_url'              => admin_url('admin-ajax.php'),
                'nonce'                 => wp_create_nonce('mepr_addons'),
                'active'                => __('Active', 'memberpress'),
                'inactive'              => __('Inactive', 'memberpress'),
                'activate'              => __('Activate', 'memberpress'),
                'deactivate'            => __('Deactivate', 'memberpress'),
                'install_failed'        => __('Could not install add-on. Please download from memberpress.com and install manually.', 'memberpress'),
                'plugin_install_failed' => __('Could not install plugin. Please download and install manually.', 'memberpress'),
            ]);

            // Get active tab for conditional enqueuing.
            $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'addons';

            /**
             * Allow brands to enqueue their own scripts and styles for custom tabs.
             *
             * @param string $hook       The current admin page hook.
             * @param string $active_tab The currently active tab.
             */
            do_action('mepr_addons_enqueue_scripts', $hook, $active_tab);
        }

        if (preg_match('/_page_memberpress-affiliates$/', $hook)) {
            wp_enqueue_style('mepr-sister-plugin-css', MEPR_CSS_URL . '/admin-sister-plugin.css', [], MEPR_VERSION);
        }
    }

    /**
     * Ajax addon activate.
     *
     * @return void
     */
    public function ajax_addon_activate()
    {
        if (!isset($_POST['plugin'])) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_addons', false, false)) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }

        $result = activate_plugins(sanitize_text_field(wp_unslash($_POST['plugin'])));
        $type   = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'add-on';

        if (is_wp_error($result)) {
            if ($type === 'plugin') {
                wp_send_json_error(__('Could not activate plugin. Please activate from the Plugins page manually.', 'memberpress'));
            } else {
                wp_send_json_error(__('Could not activate add-on. Please activate from the Plugins page manually.', 'memberpress'));
            }
        }

        if ($type === 'plugin') {
            wp_send_json_success(__('Plugin activated.', 'memberpress'));
        } else {
            wp_send_json_success(__('Add-on activated.', 'memberpress'));
        }
    }

    /**
     * Ajax addon deactivate.
     *
     * @return void
     */
    public function ajax_addon_deactivate()
    {
        if (!isset($_POST['plugin'])) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        if (!current_user_can('deactivate_plugins')) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_addons', false, false)) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }

        deactivate_plugins(sanitize_text_field(wp_unslash($_POST['plugin'])));
        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'add-on';

        if ($type === 'plugin') {
            wp_send_json_success(__('Plugin deactivated.', 'memberpress'));
        } else {
            wp_send_json_success(__('Add-on deactivated.', 'memberpress'));
        }
    }

    /**
     * Ajax addon install.
     *
     * @return void
     */
    public function ajax_addon_install()
    {
        if (!isset($_POST['plugin'])) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        if (!current_user_can('install_plugins') || !current_user_can('activate_plugins')) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_addons', false, false)) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }

        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'add-on';

        if ($type === 'plugin') {
            $error = esc_html__('Could not install plugin. Please download and install manually.', 'memberpress');
        } else {
            $error = esc_html__('Could not install add-on. Please download from memberpress.com and install manually.', 'memberpress');
        }

        // Set the current screen to avoid undefined notices.
        set_current_screen('memberpress_page_memberpress-addons');

        // Prepare variables.
        $url = esc_url_raw(
            add_query_arg(
                [
                    'page' => 'memberpress-addons',
                ],
                admin_url('admin.php')
            )
        );

        $creds = request_filesystem_credentials($url, '', false, false, null);

        // Check for file system permissions.
        if (false === $creds) {
            wp_send_json_error($error);
        }

        if (!WP_Filesystem($creds)) {
            wp_send_json_error($error);
        }

        // We do not need any extra credentials if we have gotten this far, so let's install the plugin.
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        // Do not allow WordPress to search/download translations, as this will break JS output.
        remove_action('upgrader_process_complete', ['Language_Pack_Upgrader', 'async_upgrade'], 20);

        // Create the plugin upgrader with our custom skin.
        $installer = new Plugin_Upgrader(new MeprAddonInstallSkin());

        $plugin = esc_url_raw(wp_unslash($_POST['plugin']));
        $installer->install($plugin);

        if ($plugin === 'https://downloads.wordpress.org/plugin/google-analytics-for-wordpress.latest-stable.zip') {
            update_option('memberpress_installed_monsterinsights', true);
        }

        if ($plugin === 'https://downloads.wordpress.org/plugin/wp-mail-smtp.latest-stable.zip') {
            update_option('memberpress_installed_wp_mail_smtp', true);
        }

        // Flush the cache and return the newly installed plugin basename.
        wp_cache_flush();

        if ($installer->plugin_info()) {
            $plugin_basename = $installer->plugin_info();

            // Activate the plugin silently.
            $activated = activate_plugin($plugin_basename);

            if (!is_wp_error($activated)) {
                wp_send_json_success(
                    [
                        'message'   => $type === 'plugin' ? __('Plugin installed & activated.', 'memberpress') : __('Add-on installed & activated.', 'memberpress'),
                        'activated' => true,
                        'basename'  => $plugin_basename,
                    ]
                );
            } else {
                wp_send_json_success(
                    [
                        'message'   => $type === 'plugin' ? __('Plugin installed.', 'memberpress') : __('Add-on installed.', 'memberpress'),
                        'activated' => false,
                        'basename'  => $plugin_basename,
                    ]
                );
            }
        }

        wp_send_json_error($error);
    }

    /**
     * Returns current plugin info.
     *
     * @param  string $main_file The main file.
     * @return string
     */
    public function curr_plugin_info($main_file)
    {
        static $curr_plugins;

        if (!isset($curr_plugins)) {
            if (!function_exists('get_plugins')) {
                require_once(ABSPATH . '/wp-admin/includes/plugin.php');
            }

            $curr_plugins = get_plugins();
            wp_cache_delete('plugins', 'plugins');
        }

        if (isset($curr_plugins[$main_file])) {
            return $curr_plugins[$main_file];
        }

        return '';
    }

    /**
     * Monsterinsights shareasale id.
     *
     * @param  string $id The id.
     * @return string
     */
    public function monsterinsights_shareasale_id($id)
    {
        if (get_option('memberpress_installed_monsterinsights')) {
            $id = '409876';
        }

        return $id;
    }

    /**
     * SMTP affiliate link.
     *
     * @param  string $link The link.
     * @return string
     */
    public function smtp_affiliate_link($link)
    {
        if (get_option('memberpress_installed_wp_mail_smtp')) {
            $link = 'https://shareasale.com/r.cfm?b=834775&u=409876&m=64312&urllink=wpmailsmtp%2Ecom%2Flite%2Dupgrade%2F&afftrack=MP%2DAnalytics%2DMenu%2DItem';
        }

        return $link;
    }

    /**
     * Affiliates.
     *
     * @return void
     */
    public static function affiliates()
    {
        $pricing_url = MeprUtils::get_link_url('affiliates_ea_pricing');

        MeprView::render('/admin/addons/affiliates', get_defined_vars());
    }
}
