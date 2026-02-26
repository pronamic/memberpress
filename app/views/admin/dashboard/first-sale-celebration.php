<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
?>

<div class="mepr-first-sale-modal" id="mepr-first-sale-modal" role="dialog" aria-modal="true" aria-labelledby="mepr-first-sale-title">
    <div class="mepr-first-sale-overlay"></div>
    <div class="mepr-first-sale-content">
        <div class="mepr-first-sale-icon">
            <span class="dashicons dashicons-yes-alt"></span>
        </div>
        <h2 id="mepr-first-sale-title"><?php esc_html_e('Congratulations!', 'memberpress'); ?></h2>
        <p class="mepr-first-sale-message">
            <?php esc_html_e('You made your first sale! Your membership business is officially live.', 'memberpress'); ?>
        </p>
        <p class="mepr-first-sale-submessage">
            <?php esc_html_e('This is just the beginning. Keep building and growing your community!', 'memberpress'); ?>
        </p>
        <button type="button" class="button button-primary mepr-first-sale-close" data-nonce="<?php echo esc_attr(wp_create_nonce('mepr_dashboard')); ?>">
            <?php esc_html_e('Continue to Dashboard', 'memberpress'); ?>
        </button>
    </div>
</div>
