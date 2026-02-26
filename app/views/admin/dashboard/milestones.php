<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

$mepr_options = MeprOptions::fetch();
$images_url   = MEPR_IMAGES_URL . '/dashboard';

// Get current values.
$mrr           = MeprDashboardHelper::get_mrr();
$total_members = MeprDashboardHelper::get_total_member_count();

// Get dynamic milestone targets (auto-increase when reached).
$mrr_target     = MeprDashboardHelper::get_mrr_milestone_target($mrr);
$members_target = MeprDashboardHelper::get_members_milestone_target($total_members);

// Calculate progress percentages.
$mrr_progress     = $mrr_target > 0 ? min(100, ($mrr / $mrr_target) * 100) : 0;
$members_progress = $members_target > 0 ? min(100, ($total_members / $members_target) * 100) : 0;

// Get active subscriptions count and dynamic target.
$subscriptions_count  = MeprDashboardHelper::get_active_subscription_count();
$subscriptions_target = MeprDashboardHelper::get_subscriptions_milestone_target($subscriptions_count);
$subscriptions_progress = $subscriptions_target > 0 ? min(100, ($subscriptions_count / $subscriptions_target) * 100) : 0;
?>

<div class="mepr-dash-milestones" id="mepr-dash-milestones">
    <h2><?php esc_html_e('Milestones', 'memberpress'); ?></h2>

    <div class="mepr-dash-milestones-list">
        <!-- MRR Progress -->
        <div class="mepr-dash-milestone-item">
            <div class="mepr-dash-milestone-header">
                <span class="mepr-dash-milestone-label">
                    <?php
                    echo esc_html(sprintf(
                        // Translators: %s is the formatted MRR target amount.
                        __('Progress to %s MRR', 'memberpress'),
                        MeprAppHelper::format_currency($mrr_target, true, false, true)
                    ));
                    ?>
                </span>
                <span class="mepr-dash-milestone-value">
                    <?php echo esc_html(MeprAppHelper::format_currency($mrr, true, false, true)); ?> / <?php echo esc_html(MeprAppHelper::format_currency($mrr_target, true, false, true)); ?>
                </span>
            </div>
            <div class="mepr-dash-milestone-progress">
                <div class="mepr-dash-milestone-progress-fill" style="width: <?php echo esc_attr($mrr_progress); ?>%;"></div>
            </div>
        </div>

        <!-- Members Progress -->
        <div class="mepr-dash-milestone-item">
            <div class="mepr-dash-milestone-header">
                <span class="mepr-dash-milestone-label">
                    <?php
                    echo esc_html(sprintf(
                        // Translators: %d is the member count target.
                        __('Road to %d members', 'memberpress'),
                        $members_target
                    ));
                    ?>
                </span>
                <span class="mepr-dash-milestone-value">
                    <?php echo esc_html(number_format_i18n($total_members)); ?> / <?php echo esc_html(number_format_i18n($members_target)); ?>
                </span>
            </div>
            <div class="mepr-dash-milestone-progress">
                <div class="mepr-dash-milestone-progress-fill" style="width: <?php echo esc_attr($members_progress); ?>%;"></div>
            </div>
        </div>

        <!-- Active Subscriptions -->
        <div class="mepr-dash-milestone-item">
            <div class="mepr-dash-milestone-header">
                <span class="mepr-dash-milestone-label">
                    <?php
                    echo esc_html(sprintf(
                        // Translators: %d is the subscription count target.
                        __('Road to %d subscriptions', 'memberpress'),
                        $subscriptions_target
                    ));
                    ?>
                </span>
                <span class="mepr-dash-milestone-value">
                    <?php echo esc_html(number_format_i18n($subscriptions_count)); ?> / <?php echo esc_html(number_format_i18n($subscriptions_target)); ?>
                </span>
            </div>
            <div class="mepr-dash-milestone-progress">
                <div class="mepr-dash-milestone-progress-fill" style="width: <?php echo esc_attr($subscriptions_progress); ?>%;"></div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mepr-dash-quick-actions">
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=memberpressproduct')); ?>" class="mepr-dash-quick-action">
            <img src="<?php echo esc_url($images_url . '/icon-plus.svg'); ?>" alt="" width="16" height="16">
            <?php esc_html_e('Create Membership', 'memberpress'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-trans')); ?>" class="mepr-dash-quick-action">
            <img src="<?php echo esc_url($images_url . '/icon-file.svg'); ?>" alt="" width="16" height="16">
            <?php esc_html_e('View Transactions', 'memberpress'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-subscriptions')); ?>" class="mepr-dash-quick-action">
            <img src="<?php echo esc_url($images_url . '/icon-bar-chart.svg'); ?>" alt="" width="16" height="16">
            <?php esc_html_e('View Subscriptions', 'memberpress'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-members')); ?>" class="mepr-dash-quick-action">
            <img src="<?php echo esc_url($images_url . '/icon-users2.svg'); ?>" alt="" width="16" height="16">
            <?php esc_html_e('Manage Members', 'memberpress'); ?>
        </a>
    </div>
</div>
