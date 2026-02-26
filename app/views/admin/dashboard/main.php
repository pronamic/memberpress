<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

$mepr_options            = MeprOptions::fetch();
$mepr_current_user       = wp_get_current_user();
$show_setup_notice       = MeprDashboardHelper::should_show_setup_notice();
$is_launch_phase         = MeprDashboardHelper::is_launch_phase();
$show_first_sale_modal   = MeprDashboardHelper::should_show_first_sale_celebration();
$active_tab              = 'dashboard';
$nba_recommendation      = MeprNextBestActionHelper::get_recommendation();
?>

<div class="wrap mepr-dashboard">
    <?php MeprView::render('/admin/shared/nav-tabs', get_defined_vars()); ?>

    <div class="mepr-dashboard-header">
        <h1><?php
            // Translators: %s is the user's display name.
            echo esc_html(sprintf(__('Welcome back, %s!', 'memberpress'), $mepr_current_user->display_name));
        ?></h1>
    </div>

    <?php if ($show_setup_notice) : ?>
        <?php MeprView::render('/admin/dashboard/setup-notice'); ?>
    <?php endif; ?>

    <?php if ($nba_recommendation !== null) : ?>
    <!-- Next Best Action Card (Full Width) -->
    <div class="mepr-dashboard-section mepr-dashboard-nba">
        <?php MeprView::render('/admin/dashboard/next-best-action', get_defined_vars()); ?>
    </div>
    <?php endif; ?>

    <div class="mepr-dashboard-grid">
        <!-- Left Column -->
        <div class="mepr-dashboard-column">
            <!-- Quick Stats / Business Health -->
            <div class="mepr-dashboard-section mepr-dashboard-stats">
                <?php if ($is_launch_phase) : ?>
                    <?php MeprView::render('/admin/dashboard/quick-stats-cold-start'); ?>
                <?php else : ?>
                    <?php MeprView::render('/admin/dashboard/quick-stats'); ?>
                <?php endif; ?>
            </div>

            <!-- Milestones -->
            <div class="mepr-dashboard-section mepr-dashboard-milestones">
                <?php MeprView::render('/admin/dashboard/milestones'); ?>
            </div>
        </div>

        <!-- Right Column -->
        <div class="mepr-dashboard-column">
            <!-- Updates and Resources -->
            <div class="mepr-dashboard-section mepr-dashboard-updates">
                <?php MeprView::render('/admin/dashboard/announcements'); ?>
            </div>

            <!-- Extend Membership Site (Add-ons) -->
            <div class="mepr-dashboard-section mepr-dashboard-addons">
                <?php MeprView::render('/admin/dashboard/addons'); ?>
            </div>
        </div>
    </div>

    <?php if ($show_first_sale_modal) : ?>
        <?php MeprView::render('/admin/dashboard/first-sale-celebration'); ?>
    <?php endif; ?>
</div>
