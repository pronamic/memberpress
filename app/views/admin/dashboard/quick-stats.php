<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

$mepr_options   = MeprOptions::fetch();
$images_url     = MEPR_IMAGES_URL . '/dashboard';

// Get stats from the dashboard helper.
$mrr            = MeprDashboardHelper::get_mrr();
$active_members = MeprDashboardHelper::get_active_member_count();
$churn_rate     = MeprDashboardHelper::get_churn_rate();
$ltv            = MeprDashboardHelper::get_ltv();

// Get trend indicators.
$revenue_trend  = MeprDashboardHelper::get_trend('revenue');
$members_trend  = MeprDashboardHelper::get_trend('members');
$churn_trend    = MeprDashboardHelper::get_trend('churn');

// Inline SVG for trend icons (currentColor requires inline SVG).
// phpcs:disable Generic.Files.LineLength.TooLong
$trend_up_svg   = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.6667 4.66669L9.42092 9.91244C9.15691 10.1765 9.02491 10.3085 8.87269 10.3579C8.73879 10.4014 8.59456 10.4014 8.46066 10.3579C8.30845 10.3085 8.17644 10.1765 7.91243 9.91244L6.08759 8.0876C5.82358 7.82359 5.69157 7.69158 5.53935 7.64212C5.40546 7.59862 5.26123 7.59862 5.12733 7.64212C4.97511 7.69158 4.84311 7.82359 4.5791 8.0876L1.33334 11.3334M14.6667 4.66669H10M14.6667 4.66669V9.33335" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$trend_down_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.6667 11.3333L9.42092 6.08754C9.15691 5.82353 9.02491 5.69152 8.87269 5.64206C8.73879 5.59856 8.59456 5.59856 8.46066 5.64206C8.30845 5.69152 8.17644 5.82353 7.91243 6.08754L6.08759 7.91238C5.82358 8.17639 5.69157 8.3084 5.53935 8.35785C5.40546 8.40136 5.26123 8.40136 5.12733 8.35785C4.97511 8.3084 4.84311 8.17639 4.5791 7.91238L1.33334 4.66663M14.6667 11.3333H10M14.6667 11.3333V6.66663" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
// phpcs:enable Generic.Files.LineLength.TooLong
?>

<div class="mepr-quick-stats" id="mepr-quick-stats">
    <div class="mepr-quick-stats-header">
        <h2><?php esc_html_e('Quick Status', 'memberpress'); ?></h2>
    </div>

    <div class="mepr-stats-grid">
        <!-- Monthly Recurring Revenue -->
        <div class="mepr-stat-card">
            <div class="mepr-stat-icon">
                <img src="<?php echo esc_url($images_url . '/icon-coins-stacked.svg'); ?>" alt="" width="20" height="20">
            </div>
            <div class="mepr-stat-label"><?php esc_html_e('Monthly Recurring Revenue', 'memberpress'); ?></div>
            <div class="mepr-stat-value-row">
                <span class="mepr-stat-value"><?php echo esc_html(MeprAppHelper::format_currency($mrr, true, false)); ?></span>
                <?php if ($revenue_trend) : ?>
                    <span class="mepr-stat-trend trend-<?php echo esc_attr($revenue_trend['direction']); ?>">
                        <?php echo $revenue_trend['direction'] === 'up' ? $trend_up_svg : $trend_down_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo esc_html($revenue_trend['percentage'] . '%'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Members -->
        <div class="mepr-stat-card">
            <div class="mepr-stat-icon">
                <img src="<?php echo esc_url($images_url . '/icon-users.svg'); ?>" alt="" width="20" height="20">
            </div>
            <div class="mepr-stat-label"><?php esc_html_e('Active Members', 'memberpress'); ?></div>
            <div class="mepr-stat-value-row">
                <span class="mepr-stat-value"><?php echo esc_html(number_format_i18n($active_members)); ?></span>
                <?php if ($members_trend) : ?>
                    <span class="mepr-stat-trend trend-<?php echo esc_attr($members_trend['direction']); ?>">
                        <?php echo $members_trend['direction'] === 'up' ? $trend_up_svg : $trend_down_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo esc_html($members_trend['value']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Churn Rate -->
        <div class="mepr-stat-card">
            <div class="mepr-stat-icon">
                <img src="<?php echo esc_url($images_url . '/icon-line-chart-down.svg'); ?>" alt="" width="20" height="20">
            </div>
            <div class="mepr-stat-label"><?php esc_html_e('Churn Rate', 'memberpress'); ?></div>
            <div class="mepr-stat-value-row">
                <?php if ($churn_rate !== null) : ?>
                    <span class="mepr-stat-value"><?php echo esc_html(number_format_i18n($churn_rate, 1) . '%'); ?></span>
                    <?php if ($churn_trend) : ?>
                        <span class="mepr-stat-trend sentiment-<?php echo esc_attr($churn_trend['sentiment']); ?>">
                            <?php echo $churn_trend['direction'] === 'up' ? $trend_up_svg : $trend_down_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php echo esc_html($churn_trend['percentage'] . '%'); ?>
                        </span>
                    <?php endif; ?>
                <?php else : ?>
                    <span class="mepr-stat-value mepr-stat-na"><?php esc_html_e('N/A', 'memberpress'); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer Lifetime Value -->
        <div class="mepr-stat-card">
            <div class="mepr-stat-icon">
                <img src="<?php echo esc_url($images_url . '/icon-wallet.svg'); ?>" alt="" width="20" height="20">
            </div>
            <div class="mepr-stat-label"><?php esc_html_e('Avg Customer LTV', 'memberpress'); ?></div>
            <div class="mepr-stat-value-row">
                <span class="mepr-stat-value"><?php echo esc_html(MeprAppHelper::format_currency($ltv, true, false)); ?></span>
            </div>
        </div>
    </div>

    <div class="mepr-quick-stats-footer">
        <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-reports')); ?>" class="mepr-quick-stats-link">
            <?php esc_html_e('View full reports', 'memberpress'); ?>
            <img src="<?php echo esc_url($images_url . '/icon-arrow-right.svg'); ?>" alt="" width="16" height="16">
        </a>
    </div>
</div>
