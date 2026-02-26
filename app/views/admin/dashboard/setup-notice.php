<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

$checklist_url = admin_url('admin.php?page=memberpress-options&expand_checklist=1');
$images_url    = MEPR_IMAGES_URL . '/dashboard';
?>

<div class="mepr-dashboard-setup-notice" id="mepr-dashboard-setup-notice">
    <div class="mepr-setup-notice-icon">
        <img src="<?php echo esc_url($images_url . '/icon-alert-circle.svg'); ?>" alt="" width="24" height="24">
    </div>
    <div class="mepr-setup-notice-content">
        <p>
            <strong><?php esc_html_e('Your site isn\'t fully launched yet.', 'memberpress'); ?></strong>
            <?php esc_html_e('Complete your Post-Setup Checklist to start selling faster.', 'memberpress'); ?>
        </p>
    </div>
    <div class="mepr-setup-notice-actions">
        <button type="button" class="button mepr-dismiss-setup-notice" data-nonce="<?php echo esc_attr(wp_create_nonce('mepr_dashboard')); ?>">
            <?php esc_html_e('Dismiss', 'memberpress'); ?>
        </button>
        <a href="<?php echo esc_url($checklist_url); ?>" class="button button-primary">
            <?php esc_html_e('View Checklist', 'memberpress'); ?>
        </a>
        <button type="button" class="mepr-setup-notice-close mepr-dismiss-setup-notice" data-nonce="<?php echo esc_attr(wp_create_nonce('mepr_dashboard')); ?>" aria-label="<?php esc_attr_e('Dismiss', 'memberpress'); ?>">
            <img src="<?php echo esc_url($images_url . '/icon-x-close.svg'); ?>" alt="" width="20" height="20">
        </button>
    </div>
</div>
