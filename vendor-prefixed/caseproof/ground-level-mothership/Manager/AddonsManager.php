<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Mothership\Manager;

use MemberPress\GroundLevel\Mothership\Api\Request\Products;
use MemberPress\GroundLevel\Mothership\Api\Response;
use MemberPress\GroundLevel\Mothership\Manager\AddonInstallSkin;
use MemberPress\GroundLevel\Mothership\Service as MothershipService;
use MemberPress\GroundLevel\Container\Contracts\StaticContainerAwareness;
use MemberPress\GroundLevel\Container\Concerns\HasStaticContainer;

/**
 * The Addons class fetches the available add-ons and integrates with the WP plugin installation API.
 */
class AddonsManager implements StaticContainerAwareness
{
    use HasStaticContainer;

    /**
     * Load the hooks for the add-ons.
     *
     * @return void
     */
    public static function loadHooks(): void
    {
        add_action('wp_ajax_mosh_addon_activate', [self::class, 'ajaxAddonActivate']);
        add_action('wp_ajax_mosh_addon_deactivate', [self::class, 'ajaxAddonDeactivate']);
        add_action('wp_ajax_mosh_addon_install', [self::class, 'ajaxAddonInstall']);
        add_filter('site_transient_update_plugins', [self::class, 'addonsUpdatePlugins']);
    }

    /**
     * Update the plugins transient with the available add-ons.
     *
     * @param  object $transient The plugins transient.
     * @return object The plugins transient.
     */
    public static function addonsUpdatePlugins($transient)
    {
        // If the license key is not set, return the transient.
        if (! self::getContainer()->get(MothershipService::CONNECTION_PLUGIN_SERVICE_ID)->getLicenseKey()) {
            return $transient;
        }

        // If the license is not active, return the transient.
        if (
            ! self::getContainer()->get(
                MothershipService::CONNECTION_PLUGIN_SERVICE_ID
            )->getLicenseActivationStatus()
        ) {
            return $transient;
        }

        if (! is_object($transient)) {
            return $transient;
        }

        $transientCheck = self::checkAddonsUpdateTransient();
        if ($transientCheck !== false) {
            $productsTransient = get_transient(
                self::getContainer()->get(
                    MothershipService::CONNECTION_PLUGIN_SERVICE_ID
                )->pluginId . '-mosh-products'
            );
            return ($productsTransient->products ?? false) ?
                self::getTransientWithAddonsUpdates($productsTransient->products, $transient) : $transient;
        }

        if (! isset($transient->response) || ! is_array($transient->response)) {
            $transient->response = [];
        }

        $products = self::getAddons(false);

        if ($products instanceof Response && $products->isError()) {
            // Set transient to expire in 30 minutes so we don't keep checking..
            set_transient(
                self::getContainer()->get(
                    MothershipService::CONNECTION_PLUGIN_SERVICE_ID
                )->pluginId . '-mosh-addons-update-check',
                null,
                30 * MINUTE_IN_SECONDS
            );
            return $transient;
        }

        if (! is_array($products->products ?? null)) {
            return $transient;
        }

        $transient = self::getTransientWithAddonsUpdates($products->products, $transient);

        // Create a transient that expires every 30 minutes. We only want this to run once every 30 minutes.
        set_transient(
            self::getContainer()->get(
                MothershipService::CONNECTION_PLUGIN_SERVICE_ID
            )->pluginId . '-mosh-addons-update-check',
            null,
            30 * MINUTE_IN_SECONDS
        );

        return $transient;
    }

    /**
     * Check if the add-ons update transient is available which expires every 30 minutes.
     *
     * @return boolean True if the transient is available, false otherwise.
     */
    public static function checkAddonsUpdateTransient(): bool
    {
        $updateCheck = get_transient(
            self::getContainer()->get(
                MothershipService::CONNECTION_PLUGIN_SERVICE_ID
            )->pluginId . '-mosh-addons-update-check'
        );
        if ($updateCheck !== false) {
            return true;
        }
        return false;
    }

    /**
     * Returns the modified transient with add-ons updates.
     *
     * @param  array  $products  The products to check.
     * @param  object $transient The transient to update.
     * @return object The modified transient.
     */
    public static function getTransientWithAddonsUpdates(array $products, object $transient)
    {
        foreach ($products ?? [] as $product) {
            if (! isset($transient->checked[$product->main_file])) { // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps -- API response
                continue;
            }

            $versionLatest = $product->_embedded->{'version-latest'}->number ?? '';
            $urlLatest     = $product->_embedded->{'version-latest'}->url ?? '';

            $item = (object) [
                'id'          => $product->main_file,  // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps -- API response
                'slug'        => $product->slug,
                'plugin'      => $product->main_file, // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps -- API response
                'new_version' => $versionLatest,
                'package'     => $urlLatest,
                'icons'       => [
                    'png' => $product->image,
                ],
            ];
            if (
                version_compare(
                    $transient->checked[$product->main_file], // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps -- API response
                    $versionLatest,
                    '>='
                )
            ) {
                $transient->no_update[$product->main_file] = $item; // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps -- API response
            } else {
                $transient->response[$product->main_file] = $item; // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps -- API response
            }
        }
        return $transient;
    }

    /**
     * Activate an add-on.
     *
     * @return void
     */
    public static function ajaxAddonActivate(): void
    {
        if (! isset($_POST['plugin'])) {
            wp_send_json_error(esc_html__('Bad request.', 'memberpress'));
        }

        if (! current_user_can('activate_plugins')) {
            wp_send_json_error(esc_html__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (! check_ajax_referer('mosh_addons', false, false)) {
            wp_send_json_error(esc_html__('Security check failed.', 'memberpress'));
        }

        $result = activate_plugins(wp_unslash($_POST['plugin']));
        $type   = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'add-on';

        if (is_wp_error($result)) {
            if ($type === 'plugin') {
                wp_send_json_error(
                    esc_html__(
                        'Could not activate plugin. Please activate from the Plugins page manually.',
                        'memberpress'
                    )
                );
            } else {
                wp_send_json_error(
                    esc_html__(
                        'Could not activate add-on. Please activate from the Plugins page manually.',
                        'memberpress'
                    )
                );
            }
        }

        if ($type === 'plugin') {
            wp_send_json_success(esc_html__('Plugin activated.', 'memberpress'));
        } else {
            wp_send_json_success(esc_html__('Add-on activated.', 'memberpress'));
        }
    }

    /**
     * Deactivate an add-on.
     *
     * @return void
     */
    public static function ajaxAddonDeactivate(): void
    {
        if (! isset($_POST['plugin'])) {
            wp_send_json_error(esc_html__('Bad request.', 'memberpress'));
        }
        if (! current_user_can('deactivate_plugins')) {
            wp_send_json_error(esc_html__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }
        if (! check_ajax_referer('mosh_addons', false, false)) {
            wp_send_json_error(esc_html__('Security check failed.', 'memberpress'));
        }

        deactivate_plugins(wp_unslash($_POST['plugin']));
        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'add-on';

        if ($type === 'plugin') {
            wp_send_json_success(esc_html__('Plugin deactivated.', 'memberpress'));
        } else {
            wp_send_json_success(esc_html__('Add-on deactivated.', 'memberpress'));
        }
    }

    /**
     * Install an add-on.
     *
     * @return void
     */
    public static function ajaxAddonInstall(): void
    {
        if (! isset($_POST['plugin'])) {
            wp_send_json_error(esc_html__('Bad request.', 'memberpress'));
        }

        if (! current_user_can('install_plugins') || ! current_user_can('activate_plugins')) {
            wp_send_json_error(esc_html__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (! check_ajax_referer('mosh_addons', false, false)) {
            wp_send_json_error(esc_html__('Security check failed.', 'memberpress'));
        }

        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'add-on';

        if ($type === 'plugin') {
            $error = esc_html__(
                'Could not install plugin. Please download and install manually.',
                'memberpress'
            );
        } else {
            $error = esc_html__('Could not install add-on.', 'memberpress');
        }

        // Set the current screen to avoid undefined notices.
        set_current_screen();

        $url = esc_url_raw(
            add_query_arg(
                [],
                admin_url('admin.php')
            )
        );

        $creds = request_filesystem_credentials($url, '', false, false, null);

        // Check for file system permissions.
        if (false === $creds) {
            wp_send_json_error($error);
        }

        if (! WP_Filesystem($creds)) {
            wp_send_json_error($error);
        }

        // We do not need any extra credentials if we have gotten this far, so let's install the plugin.
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        // Do not allow WordPress to search/download translations, as this will break JS output.
        remove_action('upgrader_process_complete', ['Language_Pack_Upgrader', 'async_upgrade'], 20);

        $installer = new \Plugin_Upgrader(new AddonInstallSkin());
        $plugin    = wp_unslash($_POST['plugin']);
        $installer->install($plugin);

        // Flush the cache and return the newly installed plugin basename.
        wp_cache_flush();

        if ($installer->plugin_info()) {
            $pluginBaseName = $installer->plugin_info();

            // Activate the plugin silently.
            $activated = activate_plugin($pluginBaseName);

            if (! is_wp_error($activated)) {
                wp_send_json_success(
                    [
                        'message'   => $type === 'plugin'
                                        ? esc_html__('Plugin installed and activated.', 'memberpress')
                                        : esc_html__('Add-on installed and activated.', 'memberpress'),
                        'activated' => true,
                        'basename'  => $pluginBaseName,
                    ]
                );
            } else {
                wp_send_json_success(
                    [
                        'message'   => $type === 'plugin'
                                        ? esc_html__('Plugin installed.', 'memberpress')
                                        : esc_html__('Add-on installed.', 'memberpress'),
                        'activated' => false,
                        'basename'  => $pluginBaseName,
                    ]
                );
            }
        }

        wp_send_json_error($error);
    }

    /**
     * Get the add-ons from the API.
     *
     * @param  boolean $cached Whether to use the cached products or not.
     * @return object The add-ons.
     */
    public static function getAddons(bool $cached = false)
    {
        if ($cached) {
            $addons = get_transient(
                self::getContainer()->get(
                    MothershipService::CONNECTION_PLUGIN_SERVICE_ID
                )->pluginId . '-mosh-products'
            );
            if (false !== $addons) {
                return $addons;
            }
        }

        $args['_embed'] = 'version-latest';
        $resp           = Products::list($args);

        // If the response is not an error, set the transient.
        if ($resp instanceof Response && ! $resp->isError()) {
            set_transient(
                self::getContainer()->get(
                    MothershipService::CONNECTION_PLUGIN_SERVICE_ID
                )->pluginId . '-mosh-products',
                $resp,
                HOUR_IN_SECONDS
            );
        }
        return $resp;
    }

    /**
     * Generates and returns the HTML for the add-ons.
     *
     * @return string The HTML for the add-ons.
     */
    public static function generateAddonsHtml(): string
    {
        if (! self::getContainer()->get(MothershipService::CONNECTION_PLUGIN_SERVICE_ID)->getLicenseKey()) {
            return '<div class="notice notice-error is-dismissible"><p>' . esc_html__(
                'Please enter your license key to access add-ons.',
                'memberpress'
            ) . '</p></div>';
        }

        // Refresh the add-ons if the button is clicked.
        if (isset($_POST['submit-button-mosh-refresh-addon'])) {
            delete_transient(
                self::getContainer()->get(MothershipService::CONNECTION_PLUGIN_SERVICE_ID)->pluginId . '-mosh-products'
            );
        }

        $addons = self::getAddons(true);
        if ($addons instanceof Response && $addons->isError()) {
            return sprintf(
                '<div class=""><p>%s <b>%s</b></p></div>',
                esc_html__('There was an issue connecting with the API.', 'memberpress'),
                $addons->error
            );
        }

        self::enqueueAssets();
        ob_start();
        $products = $addons->products ?? [];
        include_once __DIR__ . '/../Views/products.php';
        return ob_get_clean();
    }

    /**
     * Enqueues the assets for the add-ons display.
     *
     * @return void
     */
    public static function enqueueAssets(): void
    {
        wp_enqueue_style('dashicons');
        wp_enqueue_script('mosh-addons-js', plugin_dir_url(__FILE__) . '../assets/addons.js', [], null, true);
        wp_enqueue_style('mosh-addons-css', plugin_dir_url(__FILE__) . '../assets/addons.css');
        wp_localize_script('mosh-addons-js', 'MoshAddons', [
            'ajax_url'              => admin_url('admin-ajax.php'),
            'nonce'                 => wp_create_nonce('mosh_addons'),
            'active'                => esc_html__('Active', 'memberpress'),
            'inactive'              => esc_html__('Inactive', 'memberpress'),
            'activate'              => esc_html__('Activate', 'memberpress'),
            'deactivate'            => esc_html__('Deactivate', 'memberpress'),
            'install_failed'        => esc_html__(
                'Could not install add-on. Please download and install manually.',
                'memberpress'
            ),
            'plugin_install_failed' => esc_html__(
                'Could not install plugin. Please download and install manually.',
                'memberpress'
            ),
        ]);
    }
}
