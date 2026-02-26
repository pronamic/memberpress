<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

$images_url = MEPR_IMAGES_URL . '/dashboard';

// Get contextual resources based on user's setup state.
$updates = MeprDashboardHelper::get_contextual_resources(4);

/**
 * Filter the dashboard updates and resources.
 *
 * @param array $updates Array of update items with 'title', 'type', and 'url' keys.
 */
$updates = MeprHooks::apply_filters('mepr_dashboard_updates', $updates);
?>

<div class="mepr-dash-updates" id="mepr-dash-updates">
    <h2><?php esc_html_e('Updates and Resources', 'memberpress'); ?></h2>

    <div class="mepr-dash-updates-list">
        <?php foreach ($updates as $update) : ?>
            <a href="<?php echo esc_url($update['url']); ?>" class="mepr-dash-update-item" target="_blank" rel="noopener noreferrer">
                <div class="mepr-dash-update-content">
                    <div class="mepr-dash-update-title"><?php echo esc_html($update['title']); ?></div>
                    <div class="mepr-dash-update-type"><?php echo esc_html($update['type']); ?></div>
                </div>
                <div class="mepr-dash-update-arrow">
                    <img src="<?php echo esc_url($images_url . '/icon-arrow-up-right.svg'); ?>" alt="" width="16" height="16">
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="mepr-dash-updates-footer">
        <a href="<?php echo esc_url(MeprUtils::get_link_url('docs')); ?>" class="mepr-dash-updates-link" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('View All Updates', 'memberpress'); ?>
            <img src="<?php echo esc_url($images_url . '/icon-arrow-right.svg'); ?>" alt="" width="16" height="16">
        </a>
    </div>
</div>
