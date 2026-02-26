<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

// Get progress if available.
$progress = 0;
if (class_exists('MeprPostSetupChecklistHelper')) {
    $progress = MeprPostSetupChecklistHelper::get_progress_percentage();
}

$checklist_url = admin_url('admin.php?page=memberpress-options&expand_checklist=1');
?>

<div class="mepr-quick-stats mepr-quick-stats-cold-start" id="mepr-quick-stats">
    <div class="mepr-cold-start-header">
        <h2><?php esc_html_e('Launch Your Membership Site', 'memberpress'); ?></h2>
    </div>

    <div class="mepr-launch-progress">
        <div class="mepr-launch-progress-bar">
            <div class="mepr-launch-progress-fill" style="width: <?php echo esc_attr($progress); ?>%;"></div>
        </div>
        <p class="mepr-launch-progress-text">
            <?php
            // Translators: %d is the percentage of setup completed.
            echo esc_html(sprintf(__('%d%% complete', 'memberpress'), $progress));
            ?>
        </p>
    </div>

    <div class="mepr-cold-start-message">
        <span class="dashicons dashicons-lightbulb"></span>
        <p><?php esc_html_e('Complete the setup checklist to start accepting payments and growing your membership.', 'memberpress'); ?></p>
    </div>

    <div class="mepr-quick-stats-footer">
        <a href="<?php echo esc_url($checklist_url); ?>" class="button button-primary">
            <?php esc_html_e('Complete Setup', 'memberpress'); ?>
        </a>
    </div>
</div>
