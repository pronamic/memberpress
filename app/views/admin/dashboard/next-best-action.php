<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

// Use pre-fetched recommendation when passed from main dashboard (avoids duplicate work).
$raw_nba_action = isset($nba_recommendation) ? $nba_recommendation : MeprNextBestActionHelper::get_recommendation();
$nba_action     = MeprNextBestActionHelper::format_for_display($raw_nba_action);
$images_url     = MEPR_IMAGES_URL . '/dashboard';
?>

<div class="mepr-nba-card" id="mepr-nba-card">
    <div class="mepr-nba-header">
        <div class="mepr-nba-header-content">
            <div class="mepr-nba-header-icon">
                <img src="<?php echo esc_url($images_url . '/icon-check.svg'); ?>" alt="" width="24" height="24">
            </div>
            <p><?php esc_html_e('We\'ve analyzed your site and believe this is the best next action you can take to improve it, grow faster, and monetize more effectively.', 'memberpress'); ?></p>
        </div>
        <button type="button" class="mepr-nba-dismiss" data-action-id="<?php echo esc_attr($nba_action['id']); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('mepr_dashboard')); ?>" title="<?php esc_attr_e('Dismiss', 'memberpress'); ?>">
            <img src="<?php echo esc_url($images_url . '/icon-x-close.svg'); ?>" alt="" width="20" height="20">
        </button>
    </div>

    <div class="mepr-nba-content">
        <div class="mepr-nba-recommendation">
            <h3><?php echo esc_html($nba_action['title']); ?></h3>
            <?php if (!empty($nba_action['effort'])) : ?>
                <div class="mepr-nba-effort-badge">
                    <span class="mepr-nba-effort-dot"></span>
                    <?php echo esc_html($nba_action['effort']); ?>
                </div>
            <?php endif; ?>
            <p><?php echo esc_html($nba_action['description']); ?></p>
            <a href="<?php echo esc_url($nba_action['action_url']); ?>" class="button button-primary">
                <?php echo esc_html($nba_action['action_text']); ?>
            </a>
        </div>
    </div>
</div>
