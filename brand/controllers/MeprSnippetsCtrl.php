<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * MemberPress Snippets Controller
 *
 * Handles the snippets tab functionality in the MemberPress add-ons page.
 * This is brand-specific functionality for MemberPress only.
 */
class MeprSnippetsCtrl extends MeprBaseCtrl
{
    /**
     * WP Code Integration instance
     *
     * @var MeprWpCodeIntegration
     */
    private $wpcode_integration;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Load WP Code Integration.
        require_once MEPR_BRAND_PATH . '/integrations/wpcode/Integration.php';
        $this->wpcode_integration = new MeprWpCodeIntegration();

        // Call parent constructor which will call load_hooks().
        parent::__construct();
    }

    /**
     * Load hooks for the snippets controller.
     *
     * @return void
     */
    public function load_hooks()
    {
        // Register snippets tab using the filter-based API (preferred method).
        add_filter('mepr_addons_registered_tabs', [$this, 'register_snippets_tab']);

        // Hook into enqueue scripts.
        add_action('mepr_addons_enqueue_scripts', [$this, 'enqueue_scripts'], 10, 2);

        // Register AJAX handlers and filters.
        add_action('wp_ajax_mepr_wpcode_action', [$this, 'ajax_wpcode_action']);
        add_filter('wpcode_upgrade_link', [$this, 'wpcode_affiliate_link']);
    }

    /**
     * Register the snippets tab using the filter-based API.
     *
     * @param  array $tabs Existing registered tabs.
     * @return array Modified tabs array with snippets tab.
     */
    public function register_snippets_tab($tabs)
    {
        $tabs[] = [
            'id'         => 'snippets',
            'label'      => __('Snippets', 'memberpress'),
            'capability' => 'manage_options',
            'priority'   => 20,
            'callback'   => [$this, 'render_snippets_content_callback'],
        ];

        return $tabs;
    }

    /**
     * Callback to render the Snippets tab content.
     *
     * @return void
     */
    public function render_snippets_content_callback()
    {
        $snippets = $this->wpcode_integration->load_snippets();
        $wpcode_action = $this->wpcode_integration->get_required_action();
        $wpcode_required = $this->wpcode_integration->is_action_required();
        $wpcode_plugin = $wpcode_action === 'activate'
            ? $this->wpcode_integration->get_plugin_slug()
            : $this->wpcode_integration->lite_download_url;

        require MEPR_BRAND_VIEWS_PATH . '/admin/addons/snippets.php';
    }

    /**
     * Enqueue scripts and styles for snippets tab
     *
     * @param  string $hook       The current admin page hook.
     * @param  string $active_tab The currently active tab.
     * @return void
     */
    public function enqueue_scripts($hook, $active_tab = 'addons')
    {
        // Only enqueue on snippets tab.
        if ($active_tab !== 'snippets') {
            return;
        }

        // Enqueue snippets CSS.
        wp_enqueue_style('mepr-snippets-css', MEPR_BRAND_URL . '/css/admin-snippets.css', [], MEPR_VERSION);

        // Enqueue Prism.js for syntax highlighting.
        wp_enqueue_style('mepr-prism-css', MEPR_BRAND_URL . '/css/vendor/prism-tomorrow.min.css', [], '1.29.0');
        wp_enqueue_script('mepr-prism-bundle', MEPR_BRAND_URL . '/js/vendor/prism-bundle.min.js', [], '1.29.0', true);

        // Enqueue snippets JavaScript.
        wp_enqueue_script('mepr-snippets-js', MEPR_BRAND_URL . '/js/snippets.js', ['jquery', 'mepr-prism-bundle'], MEPR_VERSION, true);

        // Add WPCode snippets localization.
        wp_localize_script('mepr-snippets-js', 'MeprWpCode', [
            'ajax_url'             => admin_url('admin-ajax.php'),
            'nonce'                => wp_create_nonce('mepr_addons'),
            'installing_text'      => __('Installing', 'memberpress'),
            'updating_text'        => __('Updating', 'memberpress'),
            'activating_text'      => __('Activating', 'memberpress'),
            'error_occurred'       => __('An error occurred. Please try again.', 'memberpress'),
            // Translators: %s: action name (install, update, or activate).
            'action_failed'        => __('Failed to %s WP Code. Please try again or install manually.', 'memberpress'),
            'copied'               => __('Copied!', 'memberpress'),
        ]);
    }

    /**
     * WP Code affiliate link.
     *
     * @param  string $link The link.
     * @return string
     */
    public function wpcode_affiliate_link($link)
    {
        if (get_option('memberpress_installed_wpcode')) {
            $affiliate_params = [
                'ref' => 'memberpress',
                'campaign' => 'upgrade',
                'source' => 'memberpress-snippets',
            ];

            $link = add_query_arg($affiliate_params, $link);
        }

        return $link;
    }

    /**
     * AJAX handler for WP Code install/update/activate actions.
     *
     * @return void
     */
    public function ajax_wpcode_action()
    {
        if (!isset($_POST['plugin']) || !isset($_POST['wpcode_action'])) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_addons', false, false)) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }

        $action = sanitize_text_field(wp_unslash($_POST['wpcode_action']));
        $plugin = sanitize_text_field(wp_unslash($_POST['plugin']));

        switch ($action) {
            case 'install':
                if (!current_user_can('install_plugins')) {
                    wp_send_json_error(__('You do not have permission to install plugins.', 'memberpress'));
                }

                // Set the current screen to avoid undefined notices.
                set_current_screen('memberpress_page_memberpress-addons');

                // Prepare variables.
                $url = esc_url_raw(
                    add_query_arg(
                        [
                            'page' => 'memberpress-addons',
                            'tab' => 'snippets',
                        ],
                        admin_url('admin.php')
                    )
                );

                $creds = request_filesystem_credentials($url, '', false, false, null);

                // Check for file system permissions.
                if (false === $creds) {
                    wp_send_json_error(__('Could not install WP Code. Please install manually.', 'memberpress'));
                }

                if (!WP_Filesystem($creds)) {
                    wp_send_json_error(__('Could not install WP Code. Please install manually.', 'memberpress'));
                }

                $result = $this->wpcode_integration->install_plugin();

                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message());
                }

                wp_send_json_success([
                    'message' => __('WP Code installed & activated successfully!', 'memberpress'),
                ]);
                break;

            case 'update':
                if (!current_user_can('update_plugins')) {
                    wp_send_json_error(__('You do not have permission to update plugins.', 'memberpress'));
                }

                $result = $this->wpcode_integration->update_plugin();

                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message());
                }

                wp_send_json_success([
                    'message' => __('WP Code updated successfully!', 'memberpress'),
                ]);
                break;

            case 'activate':
                if (!current_user_can('activate_plugins')) {
                    wp_send_json_error(__('You do not have permission to activate plugins.', 'memberpress'));
                }

                $result = $this->wpcode_integration->activate_plugin();

                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message());
                }

                wp_send_json_success([
                    'message' => __('WP Code activated successfully!', 'memberpress'),
                ]);
                break;

            default:
                wp_send_json_error(__('Invalid action.', 'memberpress'));
        }
    }
}
