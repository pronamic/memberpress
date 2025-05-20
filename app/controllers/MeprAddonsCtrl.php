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
        $force   = isset($_GET['refresh']) && $_GET['refresh'] == 'true';
        $addons  = MeprUpdateCtrl::addons(true, $force, true);
        $plugins = get_plugins();
        wp_cache_delete('plugins', 'plugins');

        MeprView::render('/admin/addons/ui', get_defined_vars());
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
        }

        if (preg_match('/_page_memberpress-(analytics|smtp|affiliates)$/', $hook)) {
            wp_enqueue_style('mepr-sister-plugin-css', MEPR_CSS_URL . '/admin-sister-plugin.css', [], MEPR_VERSION);
            wp_enqueue_script('mepr-sister-plugin-js', MEPR_JS_URL . '/admin_sister_plugin.js', [], MEPR_VERSION);

            wp_localize_script('mepr-sister-plugin-js', 'MeprSisterPlugin', [
                'ajax_url'                => admin_url('admin-ajax.php'),
                'nonce'                   => wp_create_nonce('mepr_addons'),
                'install_failed'          => __('Could not install plugin. Please download and install manually.', 'memberpress'),
                'installed_and_activated' => __('Installed & Activated', 'memberpress'),
            ]);
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

        $result = activate_plugins(wp_unslash($_POST['plugin']));
        $type   = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'add-on';

        if (is_wp_error($result)) {
            if ($type == 'plugin') {
                wp_send_json_error(__('Could not activate plugin. Please activate from the Plugins page manually.', 'memberpress'));
            } else {
                wp_send_json_error(__('Could not activate add-on. Please activate from the Plugins page manually.', 'memberpress'));
            }
        }

        if ($type == 'plugin') {
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

        deactivate_plugins(wp_unslash($_POST['plugin']));
        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'add-on';

        if ($type == 'plugin') {
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

        if ($type == 'plugin') {
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

        $plugin = wp_unslash($_POST['plugin']);
        $installer->install($plugin);

        if ($plugin == 'https://downloads.wordpress.org/plugin/google-analytics-for-wordpress.latest-stable.zip') {
            update_option('memberpress_installed_monsterinsights', true);
        }

        if ($plugin == 'https://downloads.wordpress.org/plugin/wp-mail-smtp.latest-stable.zip') {
            update_option('memberpress_installed_wp_mail_smtp', true);
        }

        // Flush the cache and return the newly installed plugin basename.
        wp_cache_flush();

        if ($installer->plugin_info()) {
            $plugin_basename = $installer->plugin_info();

            // Activate the plugin silently.
            $activated = activate_plugin($plugin_basename);

            if (!is_wp_error($activated)) {
                if (isset($_POST['config']) && is_array($_POST['config'])) {
                    $slug        = isset($_POST['config']['slug']) && is_string($_POST['config']['slug']) ? wp_unslash($_POST['config']['slug']) : '';
                    $license_key = isset($_POST['config']['license_key']) && is_string($_POST['config']['license_key']) ? sanitize_text_field(wp_unslash($_POST['config']['license_key'])) : '';

                    if (
                        $slug == 'easy-affiliate/easy-affiliate.php' &&
                        !empty($license_key) &&
                        class_exists('EasyAffiliate\\Models\\Options') &&
                        class_exists('EasyAffiliate\\Controllers\\UpdateCtrl') &&
                        class_exists('EasyAffiliate\\Lib\\Utils')
                    ) {
                        try {
                            $options                     = \EasyAffiliate\Models\Options::fetch();
                            $options->mothership_license = $license_key;
                            $domain                      = urlencode(\EasyAffiliate\Lib\Utils::site_domain());
                            $args                        = compact('domain');
                            \EasyAffiliate\Controllers\UpdateCtrl::send_mothership_request("/license_keys/activate/{$options->mothership_license}", $args, 'post');
                            $options->store();
                            \EasyAffiliate\Controllers\UpdateCtrl::manually_queue_update();

                            // Clear the add-ons cache.
                            delete_site_transient('esaf_addons');
                            delete_site_transient('esaf_all_addons');
                        } catch (Exception $e) {
                            // Ignore license activation failure.
                        }
                    }
                }

                wp_send_json_success(
                    [
                        'message'   => $type == 'plugin' ? __('Plugin installed & activated.', 'memberpress') : __('Add-on installed & activated.', 'memberpress'),
                        'activated' => true,
                        'basename'  => $plugin_basename,
                    ]
                );
            } else {
                wp_send_json_success(
                    [
                        'message'   => $type == 'plugin' ? __('Plugin installed.', 'memberpress') : __('Add-on installed.', 'memberpress'),
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
        $installer_data = [
            'return_url' => admin_url('admin.php?page=memberpress-affiliates'),
            'nonce'      => wp_create_nonce('mepr_easy_affiliate_installer'),
        ];

        $installer_data = wp_json_encode($installer_data);
        $installer_data = rtrim(strtr(base64_encode($installer_data), '+/', '-_'), '=');
        $installer_url  = 'https://easyaffiliate.com/installer/' . $installer_data;

        $plugin = [
            'active'                => defined('ESAF_VERSION'),
            'installed'             => is_dir(WP_PLUGIN_DIR . '/easy-affiliate'),
            'installer_url'         => $installer_url,
            'auto_install'          => false,
            'url'                   => '',
            'license_key'           => '',
            'slug'                  => 'easy-affiliate/easy-affiliate.php',
            'activate_button_text'  => __('Activate Easy Affiliate', 'memberpress'),
            'next_step_button_html' => sprintf(
                '<a href="%s" class="button button-primary button-hero">%s</a>',
                esc_url(admin_url('admin.php?page=easy-affiliate-onboarding')),
                esc_html__('Run Setup Wizard', 'memberpress')
            ),
        ];

        if (isset($_GET['data']) && is_string($_GET['data'])) {
            $data = wp_unslash($_GET['data']);
            $data = base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
            $data = json_decode($data, true);

            if (is_array($data) && isset($data['license_key'], $data['download_url'], $data['nonce']) && wp_verify_nonce($data['nonce'], 'mepr_easy_affiliate_installer')) {
                $plugin['auto_install'] = true;
                $plugin['url']          = $data['download_url'];
                $plugin['license_key']  = $data['license_key'];
            }
        }

        MeprView::render('/admin/addons/affiliates', get_defined_vars());
    }
}
