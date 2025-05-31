<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprCoachkitCtrl extends MeprBaseCtrl
{
    /**
     * The plugin slug for CoachKit.
     *
     * @var string
     */
    private $coachkit_slug = 'memberpress-coachkit/main.php';

    /**
     * Load hooks for the CoachKit management.
     *
     * @return void
     */
    public function load_hooks()
    {
        if (! is_plugin_active($this->coachkit_slug)) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('wp_ajax_mepr_coachkit_action', [$this, 'ajax_coachkit_action']);
        } else {
            add_action('admin_notices', [$this, 'activated_admin_notice']);
        }
    }

    /**
     * Display an admin notice when CoachKit is activated.
     *
     * @return void
     */
    public function activated_admin_notice()
    {
        if (isset($_GET['coachkit_activated']) && ! empty($_GET['coachkit_activated']) && 'true' === $_GET['coachkit_activated']) : ?>
          <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('MemberPress CoachKit™ has been activated successfully!', 'memberpress') ?></p>
          </div>
        <?php endif;
    }

    /**
     * Route the CoachKit management page.
     *
     * @return void
     */
    public static function route()
    {
        $plugins = get_plugins();

        $coachkit_addon = false;
        if (empty($plugins['memberpress-coachkit/main.php'])) {
            // Only query addons if CoachKit™ is not installed.
            $addons         = (array) MeprUpdateCtrl::addons(true, true);
            $coachkit_addon = ! empty($addons['memberpress-coachkit']) ? $addons['memberpress-coachkit'] : false;
        }

        MeprView::render('/admin/coachkit/ui', get_defined_vars());
    }

    /**
     * Enqueue scripts and styles for the CoachKit admin page.
     *
     * @param  string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_scripts($hook)
    {
        if (preg_match('/_page_memberpress-(coachkit|options)$/', $hook)) {
            if (preg_match('/_page_memberpress-coachkit$/', $hook)) {
                remove_all_actions('admin_notices');
            }

            wp_enqueue_style('mepr-sister-plugin-css', MEPR_CSS_URL . '/admin-sister-plugin.css', [], MEPR_VERSION);
        }
    }

    /**
     * Handle actions for MemberPress CoachKit™
     *
     * @return void
     */
    public function ajax_coachkit_action()
    {

        if (empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'mepr_coachkit_action')) {
            die();
        }

        if (! current_user_can('activate_plugins')) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        $type      = sanitize_text_field($_POST['type']);
        $installed = false;
        $activated = false;
        $message   = '';
        $result    = 'error';
        switch ($type) {
            case 'install-activate': // Install and activate courses.
                $installed = $this->install_coachkit(true);
                $activated = $installed ? $installed : $activated;
                $result    = $installed ? 'success' : 'error';
                $message   = $installed ? esc_html__('CoachKit™ has been installed and activated successfully. Enjoy!', 'memberpress') : esc_html__('CoachKit™ could not be installed. Please check your license settings, or contact MemberPress support for help.', 'memberpress');
                break;
            case 'activate': // Just activate (already installed).
                $activated = is_null(activate_plugin($this->coachkit_slug));
                $result    = 'success';
                $message   = esc_html__('CoachKit™ has been activated successfully. Enjoy!', 'memberpress');
                break;
            default:
                break;
        }

        delete_option('mepr_courses_flushed_rewrite_rules');

        $redirect = '';

        if ($activated) {
            // Redirect to the Programs page.
            $redirect = add_query_arg([
                'post_type'          => 'mpch-program',
                'coachkit_activated' => 'true',
            ], admin_url('edit.php'));
        }

        wp_send_json_success([
            'installed' => $installed,
            'activated' => $activated,
            'result'    => $result,
            'message'   => $message,
            'redirect'  => $redirect,
        ]);
    }

    /**
     * Install the MemberPress CoachKit™ addon
     *
     * @param boolean $activate Whether to activate after installing.
     *
     * @return boolean Whether the plugin was installed
     */
    public function install_coachkit($activate = false)
    {
        $addons         = (array) MeprUpdateCtrl::addons(true, true, true);
        $coachkit_addon = ! empty($addons['memberpress-coachkit']) ? $addons['memberpress-coachkit'] : [];

        $plugins = get_plugins();
        wp_cache_delete('plugins', 'plugins');

        if (empty($coachkit_addon)) {
            return false;
        }

        // Set the current screen to avoid undefined notices.
        set_current_screen("memberpress_page_{$this->coachkit_slug}");

        // Prepare variables.
        $url = esc_url_raw(
            add_query_arg(
                [
                    'page' => $this->coachkit_slug,
                ],
                admin_url('admin.php')
            )
        );

        $creds = request_filesystem_credentials($url, '', false, false, null);

        // Check for file system permissions.
        if (false === $creds) {
            wp_send_json_error(esc_html('File system credentials failed.', 'memberpress'));
        }
        if (! WP_Filesystem($creds)) {
            wp_send_json_error(esc_html('File system credentials failed.', 'memberpress'));
        }

        // We do not need any extra credentials if we have gotten this far, so let's install the plugin.
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        // Do not allow WordPress to search/download translations, as this will break JS output.
        remove_action('upgrader_process_complete', ['Language_Pack_Upgrader', 'async_upgrade'], 20);

        // Create the plugin upgrader with our custom skin.
        $installer = new Plugin_Upgrader(new MeprAddonInstallSkin());

        $plugin = wp_unslash($coachkit_addon->url);
        $installer->install($plugin);

        // Flush the cache and return the newly installed plugin basename.
        wp_cache_flush();

        if ($installer->plugin_info() && true === $activate) {
            activate_plugin($installer->plugin_info());
        }

        return $installer->plugin_info();
    }
}
