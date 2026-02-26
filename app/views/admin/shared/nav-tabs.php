<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Navigation tabs for main MemberPress admin pages.
 *
 * @var string $active_tab The currently active tab slug.
 */
$active_tab = $active_tab ?? 'dashboard';

$mepr_nav_tabs = [
    'dashboard' => [
        'label' => __('Dashboard', 'memberpress'),
        'url'   => admin_url('admin.php?page=memberpress-dashboard'),
    ],
    'memberships' => [
        'label' => __('Memberships', 'memberpress'),
        'url'   => admin_url('edit.php?post_type=memberpressproduct'),
    ],
    'subscriptions' => [
        'label' => __('Subscriptions', 'memberpress'),
        'url'   => admin_url('admin.php?page=memberpress-subscriptions'),
    ],
    'transactions' => [
        'label' => __('Transactions', 'memberpress'),
        'url'   => admin_url('admin.php?page=memberpress-trans'),
    ],
    'reports' => [
        'label' => __('Reports', 'memberpress'),
        'url'   => admin_url('admin.php?page=memberpress-reports'),
    ],
];

$mepr_nav_tabs = MeprHooks::apply_filters('mepr_admin_nav_tabs', $mepr_nav_tabs, $active_tab);
?>

<nav class="mepr-nav-tabs" aria-label="<?php esc_attr_e('Main navigation', 'memberpress'); ?>">
    <ul class="mepr-nav-tabs-list">
        <?php foreach ($mepr_nav_tabs as $mepr_tab_slug => $mepr_tab_data) : ?>
            <li class="mepr-nav-tab-item">
                <a href="<?php echo esc_url($mepr_tab_data['url']); ?>"
                   class="mepr-nav-tab <?php echo $active_tab === $mepr_tab_slug ? 'mepr-nav-tab-active' : ''; ?>"
                   <?php echo $active_tab === $mepr_tab_slug ? 'aria-current="page"' : ''; ?>>
                    <?php echo esc_html($mepr_tab_data['label']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
