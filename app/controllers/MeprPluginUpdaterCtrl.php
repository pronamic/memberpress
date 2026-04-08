<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Handles plugin update mechanisms (version checks, auto-updates, rollback, add-on info).
 *
 * Separated from MeprUpdateCtrl so that WP.org-hosted editions can exclude this file
 * (WP.org does not permit custom plugin updaters) while retaining license management.
 */
class MeprPluginUpdaterCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for plugin update mechanisms.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_filter('auto_update_plugin', 'MeprPluginUpdaterCtrl::automatic_updates', 10, 2);
        add_filter('pre_set_site_transient_update_plugins', 'MeprPluginUpdaterCtrl::queue_update');
        add_filter('plugins_api', 'MeprPluginUpdaterCtrl::plugin_info', 11, 3);
        add_action('in_plugin_update_message-memberpress/memberpress.php', 'MeprPluginUpdaterCtrl::check_incorrect_edition');
        add_action('mepr_plugin_edition_changed', 'MeprPluginUpdaterCtrl::clear_update_transients');
        add_action('wp_ajax_mepr_edge_updates', 'MeprPluginUpdaterCtrl::mepr_edge_updates');
        add_action('admin_menu', 'MeprPluginUpdaterCtrl::admin_menu', 50);
    }

    /**
     * Add a custom admin menu item.
     *
     * @return void
     */
    public static function admin_menu()
    {
        // Create an official rollback page in the fashion of WordPress' built in upgrader.
        if (isset($_GET['page']) && $_GET['page'] === 'mepr-rollback') {
            add_dashboard_page(__('Rollback MemberPress', 'memberpress'), __('Rollback MemberPress', 'memberpress'), 'update_plugins', 'mepr-rollback', 'MeprPluginUpdaterCtrl::rollback');
        }
    }

    /**
     * Filters the auto update plugin routine to allow MemberPress to be
     * automatically updated.
     *
     * @param  boolean $update Flag to update the plugin or not.
     * @param  array   $item   Update data about a specific plugin.
     * @return boolean   $update   The new update state.
     */
    public static function automatic_updates($update, $item)
    {
        if (MeprHooks::apply_filters('mepr_disable_mothership_updates', false)) {
            return $update;
        }

        // If this is multisite and is not on the main site, return early.
        if (is_multisite() && ! is_main_site()) {
            return $update;
        }

        // If we don't have everything we need, return early.
        $item = (array) $item;
        if (! isset($item['new_version']) || ! isset($item['slug'])) {
            return $update;
        }

        // If the plugin isn't ours, return early.
        $is_memberpress = 'memberpress' === $item['slug'];
        $is_addon      = isset($item['slug']) && 0 === strpos($item['slug'], 'memberpress-'); // See updater class.
        if (! $is_memberpress && ! $is_addon) {
            return $update;
        }

        // If the plugin folder is a git repo, return early.
        if (@is_dir(WP_PLUGIN_DIR . '/' . $item['slug'] . '/.git')) {
            return $update;
        }

        $mepr_options = MeprOptions::fetch();

        $automatic_updates = ! empty($mepr_options->auto_updates) ? $mepr_options->auto_updates : 'all';
        $current_major     = self::get_major_version(mepr_plugin_info('Version'));
        $new_major         = self::get_major_version($item['new_version']);

        // Major update available
        // If major update are enabled, run the update, else bail.
        if ($current_major < $new_major) {
            return 'all' === $automatic_updates ? true : $update;
        }

        // Minor update available
        // If minor (or major) updates are enabled, run the update, else bail.
        if ($current_major === $new_major && version_compare(mepr_plugin_info('Version'), $item['new_version'], '<')) {
            return 'all' === $automatic_updates || 'minor' === $automatic_updates ? true : $update;
        }

        return $update;
    }

    /**
     * Get the major version from a version string.
     *
     * @param  string $version The version string.
     * @return string
     */
    public static function get_major_version($version)
    {
        $exploded_version = explode('.', $version);
        return $exploded_version[0];
    }

    /**
     * Queue an update for the MemberPress plugin.
     *
     * @param object  $transient The update transient.
     * @param boolean $force     Optional. Whether to force the update. Default false.
     * @param boolean $rollback  Optional. Whether to rollback the update. Default false.
     *
     * @return object The modified update transient.
     */
    public static function queue_update($transient, $force = false, $rollback = false)
    {
        if (!$force && MeprHooks::apply_filters('mepr_disable_mothership_updates', false)) {
            return $transient;
        }

        if (empty($transient) || !is_object($transient)) {
            return $transient;
        }

        $mepr_options = MeprOptions::fetch();

        $update_info = get_site_transient('mepr_update_info');

        if ($force || (false === $update_info)) {
            if (empty($mepr_options->mothership_license)) {
                // Just here to query for the current version.
                $args = [];
                if ($mepr_options->edge_updates || ( defined('MEMBERPRESS_EDGE') && MEMBERPRESS_EDGE )) {
                    $args['edge'] = 'true';
                }

                try {
                    $version_info = MeprUpdateCtrl::send_mothership_request('/versions/latest/developer', $args);
                    $curr_version = $version_info['version'];
                    $download_url = '';
                } catch (Exception $e) {
                    return $transient;
                }
            } else {
                try {
                    $domain = urlencode(MeprUtils::site_domain());
                    $args   = compact('domain');

                    if ($mepr_options->edge_updates || ( defined('MEMBERPRESS_EDGE') && MEMBERPRESS_EDGE )) {
                        $args['edge'] = 'true';
                    }

                    if ($rollback) {
                        $args['curr_version'] = MEPR_VERSION;
                        $args['rollback']     = 'true';
                    }

                    $license_info = MeprUpdateCtrl::send_mothership_request("/versions/info/{$mepr_options->mothership_license}", $args, 'post');
                    $curr_version = $license_info['version'];
                    $download_url = $license_info['url'];

                    set_site_transient('mepr_license_info', $license_info, MeprUtils::hours(24));

                    if (MeprUtils::is_incorrect_edition_installed()) {
                        $download_url = '';
                    }
                } catch (Exception $e) {
                    try {
                        // Just here to query for the current version.
                        $args = [];
                        if ($mepr_options->edge_updates || ( defined('MEMBERPRESS_EDGE') && MEMBERPRESS_EDGE )) {
                              $args['edge'] = 'true';
                        }

                        $version_info = MeprUpdateCtrl::send_mothership_request('/versions/latest/developer', $args);
                        $curr_version = $version_info['version'];
                        $download_url = '';
                    } catch (Exception $e) {
                        if (isset($transient->response[MEPR_PLUGIN_SLUG])) {
                            unset($transient->response[MEPR_PLUGIN_SLUG]);
                        }

                        MeprUpdateCtrl::check_license_activation();
                        return $transient;
                    }
                }
            }

            set_site_transient(
                'mepr_update_info',
                compact('curr_version', 'download_url'),
                MeprUtils::hours(12)
            );

            self::addons(false, true);
        } else {
            extract($update_info);
        }

        if (isset($curr_version) && ($rollback || version_compare($curr_version, MEPR_VERSION, '>'))) {
            $transient->response[MEPR_PLUGIN_SLUG] = (object)[
                'id'          => $curr_version,
                'plugin'      => MEPR_PLUGIN_SLUG,
                'slug'        => 'memberpress',
                'new_version' => $curr_version,
                'url'         => MeprUtils::get_link_url('home'),
                'package'     => $download_url,
            ];
        } else {
            unset($transient->response[MEPR_PLUGIN_SLUG]);
        }

        MeprUpdateCtrl::check_license_activation();
        return $transient;
    }

    /**
     * Manually queue an update for the MemberPress plugin.
     *
     * @return void
     */
    public static function manually_queue_update()
    {
        $transient = get_site_transient('update_plugins');
        set_site_transient('update_plugins', self::queue_update($transient, true));
    }

    /**
     * Display the queue update button.
     *
     * @return void
     */
    public static function queue_button()
    {
        ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-options&action=queue&_wpnonce=' . wp_create_nonce('MeprUpdateCtrl::manually_queue_update'))); ?>" class="button"><?php esc_html_e('Check for Update', 'memberpress')?></a>
        <?php
    }

    /**
     * Return up-to-date addon info for memberpress & its addons
     *
     * @param  object $api    The API object.
     * @param  string $action The action.
     * @param  array  $args   The arguments.
     * @return object The API object.
     */
    public static function plugin_info($api, $action, $args)
    {
        global $wp_version;

        if (!isset($action) || $action !== 'plugin_information') {
            return $api;
        } elseif (isset($args->slug) && preg_match('#^(affiliate-royale)#', $args->slug)) {
            // If AR is installed we allow it to take care of updates.
            if (is_plugin_active('affiliate-royale/affiliate-royale.php')) {
                return $api;
            }
        } elseif (isset($args->slug) && !preg_match('#^(memberpress|affiliate-royale)#', $args->slug)) {
            return $api;
        }

        if ($args->slug === 'memberpress') {
            $mothership_slug = MEPR_EDITION;
            $display_name    = MEPR_DISPLAY_NAME;
            $description     = '
        <h3>The "All-In-One" Membership Plugin for WordPress</h3>
        <p>
          MemberPress will help you build astounding WordPress membership sites, accept credit cards securely, control who sees your content, and sell digital downloads ... all without the difficult setup.
        </p>
        <p>
          MemberPress will help you confidently create, manage and track membership subscriptions and sell digital download products. In addition to these features, MemberPress will allow you manage your members by granting and revoking their access to posts, pages, videos, categories, tags, feeds, communities, digital files and more based on what memberships they belong to.
        </p>
        <p>
          With MemberPress you’ll be able to create powerful and compelling WordPress membership sites that leverage all of the great features of WordPress, WordPress plugins and other 3rd party services including content management, forums, and social communities.
        </p>
      ';
            $faq             = 'You can read more about how to use MemberPress by visiting <a href="' . esc_url(MeprUtils::get_link_url('docs')) . '">the user manual</a>.';
            $changelog       = 'You can read more about the latest changes to MemberPress by visiting <a href="' . esc_url(MeprUtils::get_link_url('change_log')) . '">the change log</a>';
        } else {
            $mothership_slug = $args->slug;
            $faq             = 'You can read more about MemberPress Add-Ons by visiting <a href="' . esc_url(MeprUtils::get_link_url('docs_addons')) . '">the user manual</a>.';
            $addon_info      = self::mepr_addon_info($args->slug);
            if (!empty($addon_info)) {
                $display_name = $addon_info['Name'];
                $description  = $addon_info['Description'];
            } else {
                $display_name = 'MemberPress Add-On';
                $description  = 'MemberPress Add-On';
            }
            if (
                in_array($display_name, [
                    'MemberPress Courses',
                    'MemberPress Downloads',
                    'MemberPress Developer Tools',
                    'MemberPress Corporate Accounts',
                    'MemberPress PDF Invoice',
                    'MemberPress + BuddyPress Integration',
                ], true)
            ) {
                $plugin_slug = ($args->slug === 'memberpress-courses') ? 'memberpress-courses' : str_replace('memberpress', '', $addon_info['TextDomain']);
                $plugin_url  = rtrim(MeprUtils::get_link_url('addons'), '/') . '/' . rawurlencode($plugin_slug) . '/';
                $changelog   = sprintf(
                    // Translators: %1$s: the add-on name, %2$s: change log link open tag, %3$s: close link tag.
                    esc_html__('You can read more about the latest changes to %1$s by visiting %2$sthe change log%3$s.', 'memberpress'),
                    esc_html($display_name),
                    '<a href="' . esc_url($plugin_url) . '">',
                    '</a>'
                );
            }
        }

        $mepr_options = MeprOptions::fetch();

        $mothership_args = [];
        if ($mepr_options->edge_updates || (defined('MEMBERPRESS_EDGE') && MEMBERPRESS_EDGE)) {
            $mothership_args['edge'] = 'true';
        }
        $download_url = '';
        try {
            if (empty($mepr_options->mothership_license)) {
                $version_info = MeprUpdateCtrl::send_mothership_request("/versions/latest/{$mothership_slug}", $mothership_args);
            } else {
                $mothership_args['domain'] = urlencode(MeprUtils::site_domain());
                $version_info              = MeprUpdateCtrl::send_mothership_request("/versions/info/{$mothership_slug}/{$mepr_options->mothership_license}", $mothership_args);
                $download_url              = $version_info['url'];
            }
        } catch (Exception $e) {
            MeprUtils::debug_log($e->getMessage());
            $version_info = [
                'version'      => '',
                'version_date' => '',
            ];
        }
        $plugin_info = [
            'slug'           => $args->slug,
            'name'           => $display_name,
            'author'         => '<a href="https://blairwilliams.com/">Caseproof, LLC</a>',
            'author_profile' => 'https://blairwilliams.com/',
            'contributors'   => [
                [
                    'display_name' => 'Caseproof',
                    'profile'      => '',
                    'avatar'       => '',
                ],
            ],
            'homepage'       => MeprUtils::get_link_url('home'),
            'version'        => $version_info['version'],
            'requires'       => '3.8',
            'requires_php'   => '5.3',
            'tested'         => $wp_version,
            'compatibility'  => [$wp_version => [$wp_version => [100, 0, 0]]], // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            'last_updated'   => $version_info['version_date'],
            'download_link'  => $download_url,
            'sections'       => [
                'description' => $description,
                'faq'         => $faq,
            ],
            'banners'        => [
                'low'  => MEPR_IMAGES_URL . '/banner-772x250.png',
                'high' => MEPR_IMAGES_URL . '/banner-1544x500.png',
            ],
        ];

        if (isset($changelog)) {
            $plugin_info['sections']['changelog'] = $changelog;
        }

        return (object)$plugin_info;
    }

    /**
     * Get the addon info.
     *
     * @param  string $slug The slug.
     * @return array The addon info.
     */
    private static function mepr_addon_info($slug)
    {
        static $curr_plugins;

        if (!isset($curr_plugins)) {
            if (!function_exists('get_plugins')) {
                require_once(ABSPATH . '/wp-admin/includes/plugin.php');
            }
            $curr_plugins = get_plugins();
            wp_cache_delete('plugins', 'plugins');
        }

        if (isset($curr_plugins[$slug . '/main.php'])) {
            return $curr_plugins[$slug . '/main.php'];
        } elseif (isset($curr_plugins[$slug . "/{$slug}.php"])) {
            return $curr_plugins[$slug . "/{$slug}.php"];
        }

        return '';
    }

    /**
     * Rollback the MemberPress plugin to a previous version.
     *
     * @return void
     */
    public static function rollback()
    {
        // Ensure the rollback is valid.
        check_admin_referer('mepr_rollback_nonce');

        // Permissions check.
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('You don\'t have sufficient permissions to rollback MemberPress.', 'memberpress'));
        }

        $transient = get_site_transient('update_plugins');
        $transient = self::queue_update($transient, true, true);

        $info = get_site_transient('mepr_update_info');

        // Get the necessary class.
        include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
        include_once(MEPR_LIB_PATH . '/class-rollback-memberpress-upgrader.php');

        $args = wp_parse_args($_GET, ['page' => 'mepr-rollback']);

        $title   = '';
        $nonce   = 'upgrade-plugin_' . MEPR_PLUGIN_NAME;
        $url     = 'index.php?page=mepr-rollback';
        $plugin  = MEPR_PLUGIN_NAME;
        $version = $info['curr_version'];

        $upgrader = new MeprRollbackUpgrader(
            new Plugin_Upgrader_Skin(compact('title', 'nonce', 'url', 'plugin', 'version'))
        );

        $upgrader->rollback($info);
    }

    /**
     * Get the URL for the rollback page.
     *
     * @return string The rollback URL.
     */
    public static function rollback_url()
    {
        $nonce = wp_create_nonce('mepr_rollback_nonce');
        return admin_url("index.php?page=mepr-rollback&_wpnonce={$nonce}");
    }

    /**
     * Handle edge updates for the MemberPress plugin.
     *
     * @return void
     */
    public static function mepr_edge_updates()
    {
        if (!MeprUtils::is_mepr_admin() || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpnonce'] ?? '')), 'wp-edge-updates')) {
            die(json_encode(['error' => __('You do not have access.', 'memberpress')]));
        }

        if (!isset($_POST['edge'])) {
            die(json_encode(['error' => __('Edge updates couldn\'t be updated.', 'memberpress')]));
        }

        $mepr_options               = MeprOptions::fetch();
        $mepr_options->edge_updates = ($_POST['edge'] === 'true');
        $mepr_options->store(false);

        // Re-queue updates when this is checked.
        self::manually_queue_update();

        die(json_encode(['state' => ($mepr_options->edge_updates ? 'true' : 'false')]));
    }

    /**
     * Get addon information for MemberPress.
     *
     * @param boolean $return_object Optional. Whether to return the addons as an object. Default false.
     * @param boolean $force         Optional. Whether to force a refresh of the addons. Default false.
     * @param boolean $all           Optional. Whether to get all addons. Default false.
     *
     * @return array The addon information.
     */
    public static function addons($return_object = false, $force = false, $all = false)
    {
        $mepr_options = MeprOptions::fetch();
        $license      = $mepr_options->mothership_license;
        $transient    = $all ? 'mepr_all_addons' : 'mepr_addons';

        if ($force) {
            delete_site_transient($transient);
        }

        $addons = get_site_transient($transient);
        if ($addons) {
            $addons = json_decode($addons);
        } else {
            $addons = [];

            if (!empty($license)) {
                try {
                    $domain = urlencode(MeprUtils::site_domain());
                    $args   = compact('domain');

                    if ($all) {
                        $args['all'] = 'true';
                    }

                    if (defined('MEMBERPRESS_EDGE') && MEMBERPRESS_EDGE) {
                        $args['edge'] = 'true';
                    }
                    $addons = MeprUpdateCtrl::send_mothership_request('/versions/addons/' . MEPR_EDITION . "/{$license}", $args);
                } catch (Exception $e) {
                    // Fail silently.
                    MeprUtils::debug_log($e->getMessage());
                }
            }

            $json = json_encode($addons);
            set_site_transient($transient, $json, MeprUtils::hours(12));

            if ($return_object) {
                $addons = json_decode($json);
            }
        }

        return $addons;
    }

    /**
     * Check if the incorrect edition of MemberPress is installed.
     *
     * @return void
     */
    public static function check_incorrect_edition()
    {
        if (MeprUtils::is_incorrect_edition_installed()) {
            printf(
            // Translators: %1$s: open link tag, %2$s: close link tag.
                ' <strong>' . esc_html__('To restore automatic updates, %1$sinstall the correct edition%2$s of MemberPress.', 'memberpress') . '</strong>',
                sprintf('<a href="%s">', esc_url(admin_url('admin.php?page=memberpress-options#mepr-license'))),
                '</a>'
            );
        }
    }

    /**
     * Clear update transients for the MemberPress plugin.
     *
     * @return void
     */
    public static function clear_update_transients()
    {
        delete_site_transient('update_plugins');
        delete_site_transient('mepr_update_info');
        delete_site_transient('mepr_addons');
        delete_site_transient('mepr_all_addons');
    }
}
