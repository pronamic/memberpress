<?php

/**
 * WP Code Integration for MemberPress
 *
 * Handles integration with WP Code plugin including:
 * - Plugin lifecycle management (install, update, activate)
 * - Snippet library loading and caching
 * - Plugin status detection
 */

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprWpCodeIntegration
{
    /**
     * WPCode lite download URL
     *
     * @var string
     */
    public $lite_download_url = 'https://downloads.wordpress.org/plugin/insert-headers-and-footers.zip';

    /**
     * Lite plugin slug
     *
     * @var string
     */
    public $lite_plugin_slug = 'insert-headers-and-footers/ihaf.php';

    /**
     * WPCode pro plugin slug
     *
     * @var string
     */
    public $pro_plugin_slug = 'wpcode-premium/wpcode.php';

    /**
     * Cache expiration time for snippets
     *
     * @var integer
     */
    private $cache_time = 6 * HOUR_IN_SECONDS;

    /**
     * Load MemberPress snippets from WP Code library
     *
     * @return array The snippets array
     */
    public function load_snippets()
    {
        // Check cache first.
        $cache_key = 'mepr_wpcode_snippets';
        $cached = get_transient($cache_key);

        if (false !== $cached && !isset($_GET['refresh'])) {
            return $cached;
        }

        // Get snippets from WPCode if available.
        $snippets = $this->get_placeholder_snippets();

        // Try direct API call first with full_note parameter.
        $api_snippets = $this->fetch_snippets_from_api();

        if (!empty($api_snippets)) {
            // Map tags to categories and difficulties.
            $snippets = array_map([$this, 'map_snippet_taxonomy'], $api_snippets);
        } elseif (function_exists('wpcode_get_library_snippets_by_username')) {
            // Fallback to WPCode function if direct API call fails.
            $api_snippets = wpcode_get_library_snippets_by_username('memberpress');

            if (!empty($api_snippets)) {
                // Map tags to categories and difficulties.
                $snippets = array_map([$this, 'map_snippet_taxonomy'], $api_snippets);
            }
        }

        // Sort by installed status (installed first).
        uasort($snippets, function ($a, $b) {
            $a_installed = isset($a['installed']) ? $a['installed'] : false;
            $b_installed = isset($b['installed']) ? $b['installed'] : false;
            return ($b_installed <=> $a_installed);
        });

        // Cache the results.
        set_transient($cache_key, $snippets, $this->cache_time);

        return $snippets;
    }

    /**
     * Fetch snippets directly from WP Code API with full_note parameter
     *
     * @return array The snippets from API or empty array on failure
     */
    private function fetch_snippets_from_api()
    {
        $url = 'https://library.wpcode.com/api/profile/memberpress/?full_note=1';

        $response = wp_remote_get($url, [
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            MeprUtils::debug_log('WP Code API Error: ' . $response->get_error_message());
            return [];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            MeprUtils::debug_log('WP Code API returned status code: ' . $response_code);
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            MeprUtils::debug_log('WP Code API JSON decode error: ' . json_last_error_msg());
            return [];
        }

        if (!is_array($data)) {
            MeprUtils::debug_log('WP Code API data is not an array');
            return [];
        }

        // The API returns snippets nested in data->snippets.
        if (!isset($data['data']['snippets']) || !is_array($data['data']['snippets'])) {
            MeprUtils::debug_log('WP Code API missing data->snippets structure');
            return [];
        }

        return $data['data']['snippets'];
    }

    /**
     * Map WP Code snippet tags to categories and difficulties
     *
     * @param  array $snippet The snippet data from WP Code API.
     * @return array The snippet with mapped category and difficulty
     */
    private function map_snippet_taxonomy($snippet)
    {
        $tags = isset($snippet['tags']) && is_array($snippet['tags']) ? $snippet['tags'] : [];
        $tags_lower = array_map('strtolower', $tags);

        // Map tags to categories.
        $category_map = [
            'checkout-payment' => ['checkout', 'payment'],
            'registration-login' => ['registration', 'login'],
            'content-protection' => ['protection'],
            'analytics-tracking' => ['analytics', 'tracking'],
            'design-styling' => ['design', 'style'],
            'automation-webhooks' => ['automation', 'webhooks'],
            'reports-data' => ['reports', 'data'],
        ];

        $snippet['category'] = 'general';
        foreach ($category_map as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (in_array($keyword, $tags_lower, true)) {
                    $snippet['category'] = $category;
                    break 2;
                }
            }
        }

        // Map tags to difficulty.
        $snippet['difficulty'] = 'intermediate';
        if (in_array('beginner', $tags_lower, true)) {
            $snippet['difficulty'] = 'beginner';
        } elseif (in_array('intermediate', $tags_lower, true)) {
            $snippet['difficulty'] = 'intermediate';
        } elseif (in_array('advanced', $tags_lower, true)) {
            $snippet['difficulty'] = 'advanced';
        }

        // Create truncated and full note versions.
        if (isset($snippet['note'])) {
            $full_note = $snippet['note'];
            // Store full note separately.
            $snippet['note_full'] = $full_note;
            // Truncate note to ~100 characters for card display.
            $snippet['note'] = $this->truncate_note($full_note, 100);
        }

        // Ensure 'installed' key exists (default to false).
        if (!isset($snippet['installed'])) {
            $snippet['installed'] = false;
        }

        // Generate install URL from library_id for WP Code API snippets.
        // Uses WP Code's library page which handles snippet import with nonce verification.
        if (isset($snippet['library_id']) && !empty($snippet['library_id'])) {
            $snippet['install'] = wp_nonce_url(
                add_query_arg([
                    'snippet_library_id' => absint($snippet['library_id']),
                    'page' => 'wpcode-library',
                ], admin_url('admin.php')),
                'wpcode_add_from_library'
            );
        } elseif (!isset($snippet['install'])) {
            // Fallback for placeholder snippets without library_id.
            $snippet['install'] = '#';
        }

        return $snippet;
    }

    /**
     * Truncate note to specified length
     *
     * @param  string  $text   The text to truncate.
     * @param  integer $length Maximum length.
     * @return string  Truncated text
     */
    private function truncate_note($text, $length = 100)
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        // Truncate at word boundary.
        $truncated = substr($text, 0, $length);
        $last_space = strrpos($truncated, ' ');

        if ($last_space !== false) {
            $truncated = substr($truncated, 0, $last_space);
        }

        return $truncated . '...';
    }

    /**
     * Get placeholder snippets when WPCode is not available
     *
     * @return array The placeholder snippets
     */
    private function get_placeholder_snippets()
    {
        $snippet_data = [
            [
                'title' => 'Customize Registration Form Fields',
                'note' => 'Add custom fields to your MemberPress registration form with validation and conditional logic.',
                'category' => 'registration-login',
                'difficulty' => 'intermediate',
                'code' => "<?php\n/**\n * Add custom field to registration form\n */\nadd_action('mepr-signup-form-after-billing', 'add_custom_registration_field');\n\nfunction add_custom_registration_field(\$user_id) {\n  ?>\n  <div class=\"memberpress-form-row\">\n    <label for=\"custom_field\">\n      Custom Field Name:\n      <span class=\"cc-required\">*</span>\n    </label>\n    <input type=\"text\" name=\"custom_field\" id=\"custom_field\" class=\"mepr-form-input\" required />\n  </div>\n  <?php\n}",
            ],
            [
                'title' => 'Custom Pricing Table Layout',
                'note' => 'Create a beautiful custom pricing table design that matches your brand.',
                'category' => 'design-styling',
                'difficulty' => 'beginner',
                'code' => "<?php\n/**\n * Custom pricing table CSS\n */\nadd_action('wp_head', 'custom_pricing_table_styles');\n\nfunction custom_pricing_table_styles() {\n  ?>\n  <style>\n    .mepr-pricing-table {\n      display: grid;\n      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));\n      gap: 20px;\n      max-width: 1200px;\n      margin: 0 auto;\n    }\n  </style>\n  <?php\n}",
            ],
            [
                'title' => 'Google Analytics E-commerce Tracking',
                'note' => 'Track membership purchases and renewals in Google Analytics for better insights.',
                'category' => 'analytics-tracking',
                'difficulty' => 'advanced',
                'code' => "<?php\n/**\n * Google Analytics tracking code\n */\nadd_action('mepr-signup', 'track_membership_purchase', 10, 1);\n\nfunction track_membership_purchase(\$txn) {\n  \$membership = \$txn->product();\n  \n  // Add GA tracking code here\n  ?>\n  <script>\n    gtag('event', 'purchase', {\n      transaction_id: '<?php echo esc_js(\$txn->trans_num); ?>',\n      value: <?php echo \$txn->amount; ?>,\n      currency: 'USD',\n      items: [{\n        item_name: '<?php echo esc_js(\$membership->post_title); ?>'\n      }]\n    });\n  </script>\n  <?php\n}",
            ],
            [
                'title' => 'Redirect After Login',
                'note' => 'Automatically redirect members to a custom page after successful login based on membership level.',
                'category' => 'registration-login',
                'difficulty' => 'beginner',
                'code' => "<?php\n/**\n * Login redirect code\n */\nadd_filter('mepr-account-nav-login-link', 'custom_login_redirect');\n\nfunction custom_login_redirect(\$url) {\n  // Check user membership and redirect accordingly\n  \$user = MeprUtils::get_currentuserinfo();\n  \n  if(\$user && \$user->ID > 0) {\n    \$memberships = \$user->active_product_subscriptions();\n    \n    if(!empty(\$memberships)) {\n      return home_url('/members-dashboard/');\n    }\n  }\n  \n  return \$url;\n}",
            ],
            [
                'title' => 'Custom Thank You Page',
                'note' => 'Redirect to different thank you pages based on the membership purchased.',
                'category' => 'checkout-payment',
                'difficulty' => 'intermediate',
                'code' => "<?php\n/**\n * Thank you page redirect\n */\nadd_action('mepr-signup', 'custom_thank_you_redirect', 10, 1);\n\nfunction custom_thank_you_redirect(\$txn) {\n  \$product_id = \$txn->product_id;\n  \n  // Redirect based on product ID\n  if(\$product_id == 123) {\n    wp_redirect(home_url('/thank-you-premium/'));\n    exit;\n  } elseif(\$product_id == 456) {\n    wp_redirect(home_url('/thank-you-basic/'));\n    exit;\n  }\n}",
            ],
            [
                'title' => 'Hide Protected Content Excerpts',
                'note' => 'Completely hide excerpts of protected content from non-members instead of showing a preview.',
                'category' => 'content-protection',
                'difficulty' => 'beginner',
                'code' => "<?php\n/**\n * Hide protected content excerpts\n */\nadd_filter('mepr-unauthorized-excerpt', 'hide_protected_excerpts', 10, 2);\n\nfunction hide_protected_excerpts(\$excerpt, \$post) {\n  // Return empty string to hide excerpt completely\n  return '';\n  \n  // Or show custom message\n  // return '<p>This content is available to members only.</p>';\n}",
            ],
        ];

        $placeholder_snippets = [];

        foreach ($snippet_data as $snippet) {
            $placeholder_snippets[] = [
                'title' => $snippet['title'],
                'note' => $snippet['note'],
                'install' => 'https://library.wpcode.com/',
                'installed' => false,
                'category' => $snippet['category'],
                'difficulty' => $snippet['difficulty'],
                'code' => $snippet['code'],
            ];
        }

        return $placeholder_snippets;
    }

    /**
     * Check if WP Code plugin is installed (lite or pro)
     *
     * @return boolean True if WPCode is installed
     */
    public function is_plugin_installed()
    {
        return $this->is_pro_installed() || $this->is_lite_installed();
    }

    /**
     * Check if WP Code Pro is installed
     *
     * @return boolean True if WPCode Pro is installed
     */
    public function is_pro_installed()
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return array_key_exists($this->pro_plugin_slug, get_plugins());
    }

    /**
     * Check if WP Code Lite is installed
     *
     * @return boolean True if WPCode Lite is installed
     */
    public function is_lite_installed()
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return array_key_exists($this->lite_plugin_slug, get_plugins());
    }

    /**
     * Check if WP Code plugin is active
     *
     * @return boolean True if WPCode is active
     */
    public function is_plugin_active()
    {
        return function_exists('wpcode');
    }

    /**
     * Get WP Code plugin version
     *
     * @return string Plugin version or empty string
     */
    public function get_plugin_version()
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();

        if ($this->is_pro_installed()) {
            return $plugins[$this->pro_plugin_slug]['Version'] ?? '';
        }

        if ($this->is_lite_installed()) {
            return $plugins[$this->lite_plugin_slug]['Version'] ?? '';
        }

        return '';
    }

    /**
     * Get plugin slug (pro if installed, otherwise lite)
     *
     * @return string Plugin slug
     */
    public function get_plugin_slug()
    {
        return $this->is_pro_installed() ? $this->pro_plugin_slug : $this->lite_plugin_slug;
    }

    /**
     * Determine what action is required for WPCode
     *
     * @return string Action: 'install', 'update', 'activate', or 'none'
     */
    public function get_required_action()
    {
        if (!$this->is_plugin_installed()) {
            return 'install';
        }

        $version = $this->get_plugin_version();
        $update_required = version_compare($version, '2.0.10', '<');

        if ($update_required) {
            return 'update';
        }

        if (!$this->is_plugin_active()) {
            return 'activate';
        }

        return 'none';
    }

    /**
     * Check if WPCode action is required
     *
     * @return boolean True if install, update, or activate is needed
     */
    public function is_action_required()
    {
        return $this->get_required_action() !== 'none';
    }

    /**
     * Clear snippets cache
     *
     * @return void
     */
    public function clear_cache()
    {
        delete_transient('mepr_wpcode_snippets');
    }

    /**
     * Install and activate WP Code plugin
     *
     * @return boolean|WP_Error True on success, WP_Error on failure
     */
    public function install_plugin()
    {
        if (!current_user_can('install_plugins')) {
            return new WP_Error('permission_denied', __('You do not have permission to install plugins.', 'memberpress'));
        }

        // Track installation source.
        update_option('memberpress_installed_wpcode', [
            'installed_at' => current_time('mysql'),
            'source' => 'snippets-library',
            'version' => 'lite',
        ]);

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        // Use WordPress plugin installation API.
        $installer = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $installer->install($this->lite_download_url);

        if (is_wp_error($result)) {
            return $result;
        }

        // Activate plugin.
        $plugin_file = $installer->plugin_info();
        if ($plugin_file) {
            $activated = activate_plugin($plugin_file);

            if (is_wp_error($activated)) {
                return $activated;
            }
        }

        // Clear cache so next load gets fresh data.
        $this->clear_cache();

        return true;
    }

    /**
     * Update WP Code plugin
     *
     * @return boolean|WP_Error True on success, WP_Error on failure
     */
    public function update_plugin()
    {
        if (!current_user_can('update_plugins')) {
            return new WP_Error('permission_denied', __('You do not have permission to update plugins.', 'memberpress'));
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $plugin_slug = $this->get_plugin_slug();

        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->upgrade($plugin_slug);

        if (is_wp_error($result)) {
            return $result;
        }

        // Clear cache.
        $this->clear_cache();

        return true;
    }

    /**
     * Activate WP Code plugin
     *
     * @return boolean|WP_Error True on success, WP_Error on failure
     */
    public function activate_plugin()
    {
        if (!current_user_can('activate_plugins')) {
            return new WP_Error('permission_denied', __('You do not have permission to activate plugins.', 'memberpress'));
        }

        $plugin_slug = $this->get_plugin_slug();
        $result = activate_plugin($plugin_slug);

        if (is_wp_error($result)) {
            return $result;
        }

        // Clear cache.
        $this->clear_cache();

        return true;
    }
}
