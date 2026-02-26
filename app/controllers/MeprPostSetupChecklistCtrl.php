<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Controller for the Post-Setup Checklist feature.
 *
 * Handles rendering the sidebar checklist on MemberPress admin pages
 * and processing AJAX actions for minimize/dismiss functionality.
 */
class MeprPostSetupChecklistCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for the post-setup checklist.
     *
     * @return void
     */
    public function load_hooks()
    {
        // Check early if we need to re-show the checklist (from dashboard link).
        add_action('admin_init', [$this, 'maybe_reshow_checklist']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_footer', [$this, 'render_sidebar'], 100);
        add_action('admin_body_class', [$this, 'add_body_class']);

        // AJAX actions.
        add_action('wp_ajax_mepr_post_setup_checklist_dismiss', [$this, 'ajax_dismiss']);
        add_action('wp_ajax_mepr_post_setup_checklist_minimize', [$this, 'ajax_minimize']);
        add_action('wp_ajax_mepr_post_setup_checklist_expand', [$this, 'ajax_expand']);
        add_action('wp_ajax_mepr_post_setup_checklist_skip_step', [$this, 'ajax_skip_step']);
    }

    /**
     * Re-show the checklist if user clicked "View Checklist" from dashboard.
     *
     * This allows users to access the checklist even if they previously dismissed it.
     *
     * @return void
     */
    public function maybe_reshow_checklist()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check.
        if (empty($_GET['expand_checklist']) || $_GET['expand_checklist'] !== '1') {
            return;
        }

        // Only on the options page.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check.
        if (empty($_GET['page']) || $_GET['page'] !== 'memberpress-options') {
            return;
        }

        // If checklist is complete, don't re-show.
        if (MeprPostSetupChecklistHelper::is_complete()) {
            return;
        }

        // Un-dismiss and un-minimize the checklist.
        delete_option(MeprPostSetupChecklistHelper::$dismissed_option);
        update_option(MeprPostSetupChecklistHelper::$minimized_option, false);
    }

    /**
     * Enqueue scripts and styles for the checklist.
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        if (!MeprPostSetupChecklistHelper::should_show()) {
            return;
        }

        wp_enqueue_style(
            'mepr-post-setup-checklist',
            MEPR_CSS_URL . '/admin-post-setup-checklist.css',
            [],
            MEPR_VERSION
        );

        wp_enqueue_script(
            'mepr-post-setup-checklist',
            MEPR_JS_URL . '/admin_post_setup_checklist.js',
            [],
            MEPR_VERSION,
            true
        );

        wp_localize_script('mepr-post-setup-checklist', 'MeprPostSetupChecklist', [
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('mepr_post_setup_checklist'),
            'addons_nonce' => wp_create_nonce('mepr_addons'),
            'is_minimized' => MeprPostSetupChecklistHelper::is_minimized(),
            'i18n'         => [
                'confirm'     => __('Are you sure you want to dismiss the setup checklist? You can always access setup options from the Settings menu.', 'memberpress'),
                'skip'        => __('Skip', 'memberpress'),
                'skipping'    => __('Skipping...', 'memberpress'),
                'installing'  => __('Installing...', 'memberpress'),
                'activating'  => __('Activating...', 'memberpress'),
                'install_err' => __('Installation failed. Please try again.', 'memberpress'),
                'activate_err' => __('Activation failed. Please try again.', 'memberpress'),
            ],
        ]);
    }

    /**
     * Add body class when checklist is shown.
     *
     * @param string $classes Space-separated list of body classes.
     *
     * @return string Modified body classes.
     */
    public function add_body_class($classes)
    {
        if (!MeprPostSetupChecklistHelper::should_show()) {
            return $classes;
        }

        $classes .= ' mepr-has-post-setup-checklist';

        if (MeprPostSetupChecklistHelper::is_minimized()) {
            $classes .= ' mepr-post-setup-checklist-minimized';
        }

        return $classes;
    }

    /**
     * Render the sidebar checklist.
     *
     * @return void
     */
    public function render_sidebar()
    {
        if (!MeprPostSetupChecklistHelper::should_show()) {
            return;
        }

        $steps          = MeprPostSetupChecklistHelper::get_grouped_steps();
        $optional_steps = MeprPostSetupChecklistHelper::get_optional_steps();
        $progress       = MeprPostSetupChecklistHelper::get_progress_percentage();
        $completed      = MeprPostSetupChecklistHelper::get_completed_count();
        $total          = MeprPostSetupChecklistHelper::get_total_count();
        $is_minimized   = MeprPostSetupChecklistHelper::is_minimized();
        $next_step      = MeprPostSetupChecklistHelper::get_next_step();

        MeprView::render('/admin/post-setup-checklist/sidebar', compact(
            'steps',
            'optional_steps',
            'progress',
            'completed',
            'total',
            'is_minimized',
            'next_step'
        ));
    }

    /**
     * AJAX handler to dismiss the checklist.
     *
     * @return void
     */
    public function ajax_dismiss()
    {
        check_ajax_referer('mepr_post_setup_checklist', 'nonce');

        if (!MeprUtils::is_mepr_admin()) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'memberpress'));
        }

        MeprPostSetupChecklistHelper::dismiss();

        wp_send_json_success([
            'message' => __('Checklist dismissed.', 'memberpress'),
        ]);
    }

    /**
     * AJAX handler to minimize the checklist.
     *
     * @return void
     */
    public function ajax_minimize()
    {
        check_ajax_referer('mepr_post_setup_checklist', 'nonce');

        if (!MeprUtils::is_mepr_admin()) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'memberpress'));
        }

        MeprPostSetupChecklistHelper::set_minimized(true);

        wp_send_json_success([
            'message' => __('Checklist minimized.', 'memberpress'),
        ]);
    }

    /**
     * AJAX handler to expand the checklist.
     *
     * @return void
     */
    public function ajax_expand()
    {
        check_ajax_referer('mepr_post_setup_checklist', 'nonce');

        if (!MeprUtils::is_mepr_admin()) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'memberpress'));
        }

        MeprPostSetupChecklistHelper::set_minimized(false);

        wp_send_json_success([
            'message' => __('Checklist expanded.', 'memberpress'),
        ]);
    }

    /**
     * AJAX handler to skip a step.
     *
     * @return void
     */
    public function ajax_skip_step()
    {
        check_ajax_referer('mepr_post_setup_checklist', 'nonce');

        if (!MeprUtils::is_mepr_admin()) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'memberpress'));
        }

        $step_id = isset($_POST['step_id']) ? sanitize_text_field(wp_unslash($_POST['step_id'])) : '';

        if (empty($step_id)) {
            wp_send_json_error(__('Invalid step ID.', 'memberpress'));
        }

        MeprPostSetupChecklistHelper::skip_step($step_id);

        wp_send_json_success([
            'message' => __('Step skipped.', 'memberpress'),
            'step_id' => $step_id,
        ]);
    }
}
