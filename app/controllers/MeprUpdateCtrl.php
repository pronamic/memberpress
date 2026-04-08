<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * MeprUpdateCtrl - License activation and admin UI controller.
 *
 * This controller handles license management, activation/deactivation,
 * admin notices, and settings UI. Plugin update mechanisms (queue_update,
 * rollback, addons, plugin_info, automatic_updates, edge updates, etc.)
 * have been moved to MeprPluginUpdaterCtrl. Thin delegation wrappers are
 * provided here for backward compatibility; they delegate to
 * MeprPluginUpdaterCtrl when it is available, or return safe defaults.
 */
class MeprUpdateCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for the update controller.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('admin_enqueue_scripts', 'MeprUpdateCtrl::enqueue_scripts');
        add_action('admin_notices', 'MeprUpdateCtrl::activation_warning');
        add_action('admin_notices', 'MeprUpdateCtrl::promo_upgrade_notices');
        add_action('admin_init', 'MeprUpdateCtrl::activate_from_define');
        add_action('admin_init', 'MeprUpdateCtrl::maybe_activate');
        add_action('wp_ajax_mepr_dismiss_ip_admin_notice', 'MeprUpdateCtrl::dismiss_admin_notice');
        add_action('mepr_display_general_options', [$this,'display_options'], 99);
        add_action('mepr_process_options', [$this, 'store_options']);
    }

    /**
     * Dismiss an admin notice.
     *
     * @return void
     */
    public static function dismiss_admin_notice()
    {

        if (empty($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mepr_dismiss_ip_admin_notice')) {
            die();
        }

        $dismissed_admin_notices   = get_option('mp_dismissed_admin_notices', []);
        $dismissed_admin_notices[] = sanitize_text_field(wp_unslash($_POST['notice_id'] ?? ''));

        update_option('mp_dismissed_admin_notices', $dismissed_admin_notices);
        wp_send_json_success([], 201);
    }

    /**
     * Display promotional upgrade notices.
     *
     * @return void
     */
    public static function promo_upgrade_notices()
    {

        if (! MeprUtils::is_memberpress_admin_page() || ! MeprUtils::is_promo_time()) {
            return;
        }

        // Set an identifier for this notice.
        $notice_id               = 'mp_dc_22';
        $dismissed_admin_notices = get_option('mp_dismissed_admin_notices', []);

        // This notice has already been dismissed.
        if (in_array($notice_id, $dismissed_admin_notices, true)) {
            return;
        }

        $mepr_options = MeprOptions::fetch();

        if (!empty($mepr_options->mothership_license)) {
            $li = get_site_transient('mepr_license_info');

            if (false === $li) {
                MeprUpdateCtrl::manually_queue_update();
                $li = get_site_transient('mepr_license_info');
            }
        }

        // Default.
        $link        = '';
        $heading     = 'Monetize & Save!';
        $message     = 'Ready, Set, CONNECT💥Add a Forum to MemberPress 👉 Monetize Your Discord 👉 GROW Your Business w/ This New FREE Add-On';
        $button_text = '👉 LEARN MORE 👈';

        if (empty($link)) {
            return;
        }

        MeprView::render('/admin/admin-notification', get_defined_vars());
    }

    /**
     * Display the options for the update controller.
     *
     * @return void
     */
    public function display_options()
    {
        $mepr_options = MeprOptions::fetch();
        MeprView::render('admin/auto-updates/option', get_defined_vars());
    }

    /**
     * Store the options for the update controller.
     *
     * @return void
     */
    public function store_options()
    {
        $mepr_options               = MeprOptions::fetch();
        $name                       = $mepr_options->auto_updates_str;
        $mepr_options->auto_updates = isset($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : false;
        $mepr_options->store(false);
    }

    /**
     * Check if the MemberPress plugin is activated.
     *
     * @return boolean True if activated, false otherwise.
     */
    public static function is_activated()
    {
        $mepr_options = MeprOptions::fetch();
        $activated    = get_option('mepr_activated');
        return (!empty($mepr_options->mothership_license) && !empty($activated));
    }

    /**
     * Check the license activation status.
     *
     * @return void
     */
    public static function check_license_activation()
    {
        $aov = get_option('mepr_activation_override');

        if (!empty($aov)) {
            update_option('mepr_activated', true);
            MeprHooks::do_action('mepr_license_activated', ['aov' => 1]);
            return;
        }

        $mepr_options = MeprOptions::fetch();

        if (empty($mepr_options->mothership_license)) {
            return;
        }

        // Only check the key once per day.
        $option_key = "mepr_license_check_{$mepr_options->mothership_license}";

        if (get_site_transient($option_key)) {
            return;
        }

        $check_count = get_option($option_key, 0) + 1;
        update_option($option_key, $check_count);

        set_site_transient($option_key, true, MeprUtils::hours($check_count > 3 ? 72 : 24));

        $domain = urlencode(MeprUtils::site_domain());
        $args   = compact('domain');

        try {
            $act = self::send_mothership_request("/license_keys/check/{$mepr_options->mothership_license}", $args);

            if (!empty($act) && is_array($act)) {
                $license_expired = false;

                if (isset($act['expires_at'])) {
                    $expires_at = strtotime($act['expires_at']);

                    if ($expires_at && $expires_at < time()) {
                        $license_expired = true;
                        update_option('mepr_activated', false);
                        MeprHooks::do_action('mepr_license_expired', $act);
                    }
                }

                if (isset($act['status']) && !$license_expired) {
                    if ($act['status'] === 'enabled') {
                        update_option($option_key, 0);
                        update_option('mepr_activated', true);
                        MeprHooks::do_action('mepr_license_activated', $act);
                    } elseif ($act['status'] === 'disabled') {
                        update_option('mepr_activated', false);
                        MeprHooks::do_action('mepr_license_invalidated', $act);
                    }
                }
            }
        } catch (Exception $e) {
            if ($e->getMessage() === 'Not Found') {
                update_option('mepr_activated', false);
                MeprHooks::do_action('mepr_license_invalidated');
            }
        }
    }

    /**
     * Activate the license if not already activated.
     *
     * @return void
     */
    public static function maybe_activate()
    {
        $activated = get_option('mepr_activated');

        if (!$activated) {
            self::check_license_activation();
        }
    }

    /**
     * Activate the license from a defined constant.
     *
     * @return void
     */
    public static function activate_from_define()
    {
        $mepr_options = MeprOptions::fetch();

        if (defined('MEMBERPRESS_LICENSE_KEY') && $mepr_options->mothership_license !== MEMBERPRESS_LICENSE_KEY) {
            try {
                if (!empty($mepr_options->mothership_license)) {
                    // Deactivate the old license key.
                    self::deactivate_license();
                }

                // If we're using defines then we have to do this with defines too.
                $mepr_options               = MeprOptions::fetch();
                $mepr_options->edge_updates = false;
                $mepr_options->store(false);

                $act = self::activate_license(MEMBERPRESS_LICENSE_KEY);

                $message  = $act['message'];
                $view     = '/admin/errors';
                $callback = function () use ($view, $message) {
                    return MeprView::render($view, compact('message'));
                };
            } catch (Exception $e) {
                $view     = '/admin/update/activation_warning';
                $error    = $e->getMessage();
                $callback = function () use ($view, $error) {
                    return MeprView::render($view, compact('error'));
                };
            }

            add_action('admin_notices', $callback);
        }
    }

    /**
     * Activate the license with the given key
     *
     * @param  string $license_key The license key.
     * @return array The license data
     * @throws Exception If there was an error activating the license.
     */
    public static function activate_license($license_key)
    {
        $mepr_options = MeprOptions::fetch();

        $args = [
            'domain'  => urlencode(MeprUtils::site_domain()),
            'product' => MEPR_EDITION,
        ];

        $act = self::send_mothership_request("/license_keys/activate/{$license_key}", $args, 'post');

        $mepr_options->mothership_license = $license_key;
        $mepr_options->store(false);

        $option_key = "mepr_license_check_{$license_key}";
        delete_site_transient($option_key);
        delete_option($option_key);

        delete_site_transient('mepr_update_info');

        MeprHooks::do_action('mepr_license_activated_before_queue_update');

        self::manually_queue_update();

        // If the plugin updater is not available (e.g. WP.org Lite edition),
        // fetch license info directly so the activation UI can display it.
        if (!class_exists('MeprPluginUpdaterCtrl')) {
            self::get_license_info();
        }

        // Clear the cache of add-ons.
        delete_site_transient('mepr_addons');
        delete_site_transient('mepr_all_addons');

        MeprHooks::do_action('mepr_license_activated', $act);

        return $act;
    }

    /**
     * Deactivate the license
     *
     * @return array
     */
    public static function deactivate_license()
    {
        $mepr_options = MeprOptions::fetch();
        $license_key  = $mepr_options->mothership_license;
        $act          = ['message' => __('License key deactivated', 'memberpress')];

        if (!empty($mepr_options->mothership_license)) {
            try {
                $args = [
                    'domain' => urlencode(MeprUtils::site_domain()),
                ];

                $act = self::send_mothership_request("/license_keys/deactivate/{$mepr_options->mothership_license}", $args, 'post');
            } catch (Exception $e) {
                // Catching here to allow invalid license keys to be deactivated.
            }
        }

        $mepr_options->mothership_license = '';
        $mepr_options->store(false);

        $option_key = "mepr_license_check_{$license_key}";
        delete_site_transient($option_key);
        delete_option($option_key);

        delete_site_transient('mepr_update_info');

        MeprHooks::do_action('mepr_license_deactivated_before_queue_update');

        self::manually_queue_update();

        // Don't need to check the mothership for this one ... we just deactivated.
        update_option('mepr_activated', false);

        // Clear the cache of the license and add-ons.
        delete_site_transient('mepr_license_info');
        delete_site_transient('mepr_addons');
        delete_site_transient('mepr_all_addons');

        MeprHooks::do_action('mepr_license_deactivated', $act);

        return $act;
    }

    /**
     * Send a request to the Mothership API.
     *
     * @param string  $endpoint The API endpoint.
     * @param array   $args     Optional. The request arguments. Default empty array.
     * @param string  $method   Optional. The HTTP method to use. Default 'get'.
     * @param boolean $blocking Optional. Whether the request should block. Default true.
     *
     * @return array|boolean The response array or true if not blocking.
     *
     * @throws Exception If there is an error with the request.
     */
    public static function send_mothership_request($endpoint, $args = [], $method = 'get', $blocking = true)
    {
        $domain       = defined('MEPR_MOTHERSHIP_DOMAIN') ? MEPR_MOTHERSHIP_DOMAIN : 'https://mothership.caseproof.com';
        $mepr_options = MeprOptions::fetch();
        $uri          = "{$domain}{$endpoint}";

        $arg_array = [
            'method'    => strtoupper($method),
            'body'      => $args,
            'timeout'   => 15,
            'blocking'  => $blocking,
            'sslverify' => $mepr_options->sslverify,
        ];

        $resp = wp_remote_request($uri, $arg_array);

        // If we're not blocking then the response is irrelevant
        // So we'll just return true.
        if ($blocking === false) {
            return true;
        }

        if (is_wp_error($resp)) {
            throw new Exception(esc_html__('You had an HTTP error connecting to Caseproof\'s Mothership API', 'memberpress'));
        } else {
            $json_res = json_decode($resp['body'], true);
            if (null !== $json_res) {
                if (isset($json_res['error'])) {
                    throw new Exception(esc_html($json_res['error']));
                } else {
                    return $json_res;
                }
            } else {
                throw new Exception(esc_html__('Your License Key was invalid', 'memberpress'));
            }
        }

        return false;
    }

    /**
     * Enqueue scripts for the update controller.
     *
     * @param string $hook The current admin page hook.
     *
     * @return void
     */
    public static function enqueue_scripts($hook)
    {
        // The toplevel_page_memberpress will only be accessible if the plugin is not enabled.
        if (
            $hook === 'memberpress_page_memberpress-options' ||
            (!MeprUpdateCtrl::is_activated() && $hook === 'toplevel_page_memberpress')
        ) {
            wp_enqueue_style('mepr-activate-css', MEPR_CSS_URL . '/admin-activate.css', ['mepr-settings-table-css'], MEPR_VERSION);
        }
    }

    /**
     * Display an activation warning if the license is not activated.
     *
     * @return void
     */
    public static function activation_warning()
    {
        $mepr_options = MeprOptions::fetch();

        if (
            empty($mepr_options->mothership_license) &&
            (!isset($_REQUEST['page']) ||
            !($_REQUEST['page'] === 'memberpress-options' ||
            (!self::is_activated() && $_REQUEST['page'] === 'memberpress')))
        ) {
            MeprView::render('/admin/update/activation_warning', get_defined_vars());
        }
    }

    /**
     * Get the license information for the MemberPress plugin.
     *
     * @return array|false The license information or false if not available.
     */
    public static function get_license_info()
    {
        $mepr_options = MeprOptions::fetch();
        $license_info = get_site_transient('mepr_license_info');

        if (!$license_info && !empty($mepr_options->mothership_license)) {
            try {
                $domain       = urlencode(MeprUtils::site_domain());
                $args         = compact('domain');
                $license_info = self::send_mothership_request("/versions/info/{$mepr_options->mothership_license}", $args, 'post');

                set_site_transient('mepr_license_info', $license_info, MeprUtils::hours(24));
            } catch (Exception $e) {
                // Fail silently, license info will return false.
            }
        }

        return $license_info;
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::rollback() if available.
     *
     * @return void
     */
    public static function rollback()
    {
        if (class_exists('MeprPluginUpdaterCtrl')) {
            MeprPluginUpdaterCtrl::rollback();
        }
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::rollback_url() if available.
     *
     * @return string The rollback URL or empty string.
     */
    public static function rollback_url()
    {
        return class_exists('MeprPluginUpdaterCtrl')
            ? MeprPluginUpdaterCtrl::rollback_url()
            : '';
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::manually_queue_update() if available.
     *
     * @return void
     */
    public static function manually_queue_update()
    {
        if (class_exists('MeprPluginUpdaterCtrl')) {
            MeprPluginUpdaterCtrl::manually_queue_update();
        }
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::queue_update() if available.
     *
     * @param  object  $transient The update transient.
     * @param  boolean $force     Optional. Whether to force the update.
     * @param  boolean $rollback  Optional. Whether to rollback the update.
     * @return object The modified update transient.
     */
    public static function queue_update($transient, $force = false, $rollback = false)
    {
        return class_exists('MeprPluginUpdaterCtrl')
            ? MeprPluginUpdaterCtrl::queue_update($transient, $force, $rollback)
            : $transient;
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::addons() if available.
     *
     * @param  boolean $return_object Optional. Whether to return as an object.
     * @param  boolean $force         Optional. Whether to force a refresh.
     * @param  boolean $all           Optional. Whether to get all addons.
     * @return array|object The addon information.
     */
    public static function addons($return_object = false, $force = false, $all = false)
    {
        return class_exists('MeprPluginUpdaterCtrl')
            ? MeprPluginUpdaterCtrl::addons($return_object, $force, $all)
            : ($return_object ? (object) [] : []);
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::queue_button() if available.
     *
     * @return void
     */
    public static function queue_button()
    {
        if (class_exists('MeprPluginUpdaterCtrl')) {
            MeprPluginUpdaterCtrl::queue_button();
        }
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::automatic_updates() if available.
     *
     * @param  boolean $update Flag to update the plugin or not.
     * @param  array   $item   Update data about a specific plugin.
     * @return boolean The new update state.
     */
    public static function automatic_updates($update, $item)
    {
        return class_exists('MeprPluginUpdaterCtrl')
            ? MeprPluginUpdaterCtrl::automatic_updates($update, $item)
            : $update;
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::plugin_info() if available.
     *
     * @param  object $api    The API object.
     * @param  string $action The action.
     * @param  array  $args   The arguments.
     * @return object The API object.
     */
    public static function plugin_info($api, $action, $args)
    {
        return class_exists('MeprPluginUpdaterCtrl')
            ? MeprPluginUpdaterCtrl::plugin_info($api, $action, $args)
            : $api;
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::get_major_version() if available.
     *
     * @param  string $version The version string.
     * @return string The major version.
     */
    public static function get_major_version($version)
    {
        return class_exists('MeprPluginUpdaterCtrl')
            ? MeprPluginUpdaterCtrl::get_major_version($version)
            : '0';
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::mepr_edge_updates() if available.
     *
     * @return void
     */
    public static function mepr_edge_updates()
    {
        if (class_exists('MeprPluginUpdaterCtrl')) {
            MeprPluginUpdaterCtrl::mepr_edge_updates();
        }
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::check_incorrect_edition() if available.
     *
     * @return void
     */
    public static function check_incorrect_edition()
    {
        if (class_exists('MeprPluginUpdaterCtrl')) {
            MeprPluginUpdaterCtrl::check_incorrect_edition();
        }
    }

    /**
     * Delegates to MeprPluginUpdaterCtrl::clear_update_transients() if available.
     *
     * @return void
     */
    public static function clear_update_transients()
    {
        if (class_exists('MeprPluginUpdaterCtrl')) {
            MeprPluginUpdaterCtrl::clear_update_transients();
        }
    }
}
