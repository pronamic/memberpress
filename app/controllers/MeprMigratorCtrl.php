<?php

class MeprMigratorCtrl extends MeprBaseCtrl
{
    /**
     * Loads the hooks for this controller.
     */
    public function load_hooks()
    {
        add_action('admin_enqueue_scripts', 'MeprMigratorCtrl::admin_enqueue_scripts');
        add_action('mpcs_admin_options_tab', 'MeprMigratorCtrl::add_options_tab');
        add_action('mpcs_admin_options_tab_content', 'MeprMigratorCtrl::add_options_tab_content');
        add_action('wp_ajax_mepr_migrator_migrate', 'MeprMigratorCtrl::migrate');
        add_action('admin_notices', 'MeprMigratorCtrl::admin_notices');
    }

    /**
     * Enqueue admin scripts.
     */
    public static function admin_enqueue_scripts()
    {
        if (MeprMigratorHelper::is_migrator_page()) {
            $migrators = MeprMigratorHelper::get_usable_course_migrators();
            if ($migrators) {
                wp_enqueue_style('memberpress-migrator', MEPR_CSS_URL . '/migrator.css', [], MEPR_VERSION);
                wp_enqueue_script('memberpress-migrator', MEPR_JS_URL . '/migrator.js', ['jquery'], MEPR_VERSION, true);

                if (in_array(MeprMigratorLearnDash::KEY, $migrators, true)) {
                    wp_enqueue_script('memberpress-migrator-learndash', MEPR_JS_URL . '/migrator.learndash.js', ['jquery', 'memberpress-migrator'], MEPR_VERSION, true);
                }

                wp_localize_script('memberpress-migrator', 'MeprMigratorL10n', [
                    'ajax_url'           => admin_url('admin-ajax.php'),
                    'migrate_nonce'      => wp_create_nonce('mepr_migrator_migrate'),
                    'leave_are_you_sure' => __('The migration has not yet completed, are you sure you want to leave this page?', 'memberpress'),
                    'migration_complete' => __('Migration complete', 'memberpress'),
                ]);
            }
        }
    }

    /**
     * Adds the Migrator tab to the Courses Settings within MP Settings.
     */
    public static function add_options_tab()
    {
        ?>
        <li><a data-id="migrator"><?php esc_html_e('Migrator', 'memberpress'); ?></a></li>
        <?php
    }

    /**
     * Adds the content of the Migrator tab.
     */
    public static function add_options_tab_content()
    {
        MeprView::render('/admin/options/courses_migrator');
    }

    /**
     * Handles the Ajax request to run a migration.
     */
    public static function migrate()
    {
        $data = MeprUtils::get_json_request_data('mepr_migrator_migrate');

        if (!isset($data['migrator'])) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        $migrator = null;

        switch ($data['migrator']) {
            case MeprMigratorLearnDash::KEY:
                $migrator = new MeprMigratorLearnDash();
                break;
        }

        if (!$migrator instanceof MeprMigrator) {
            wp_send_json_error(__('Migrator not found', 'memberpress'));
        }

        try {
            $migrator->migrate($data);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Display admin notices.
     */
    public static function admin_notices()
    {
        global $pagenow, $typenow;

        if (
            !MeprUtils::is_logged_in_and_an_admin() ||
            get_option('mepr_dismiss_notice_learndash_migrator') ||
            $pagenow != 'edit.php' ||
            $typenow != 'mpcs-course'
        ) {
            return;
        }

        if (
            MeprMigratorHelper::has_completed_migration(MeprMigratorLearnDash::KEY) ||
            !MeprMigratorLearnDash::is_migration_possible()
        ) {
            update_option('mepr_dismiss_notice_learndash_migrator', true);
            return;
        }
        ?>
    <div class="notice notice-info is-dismissible mepr-learndash-migrator-notice mepr-notice-dismiss-permanently" data-notice="learndash_migrator">
      <div>
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/info-icon.jpg'); ?>" alt="" style="width: 30px; height: 30px;">
        <div>
          <p class="mepr-learndash-migrator-notice-heading"><?php esc_html_e('Migrate from LearnDash to MemberPress Courses?', 'memberpress'); ?></p>
          <p><?php esc_html_e('We noticed that you have some LearnDash courses that could be migrated to MemberPress Courses automatically. Click the button below to get started.', 'memberpress'); ?></p>
          <p class="mepr-learndash-migrator-notice-button-row">
            <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-courses-options&courses-tab=migrator')); ?>" class="button button-primary"><?php esc_html_e('Let\'s Do It', 'memberpress'); ?></a>
            <a href="#" class="mepr-dismiss-this-notice"><?php esc_html_e('Dismiss', 'memberpress'); ?></a>
          </p>
        </div>
      </div>
    </div>
        <?php
    }
}
