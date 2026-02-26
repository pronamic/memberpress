<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$images_url = MEPR_IMAGES_URL . '/dashboard';

// Generic add-ons (third-party). Brand-specific add-ons are added via the filter.
// Include 'plugin_file' so we can hide add-ons that are already installed.
$addons = [
    [
        'title'        => __('Easy Affiliate', 'memberpress'),
        'description'  => __('Launch your own affiliate program and grow faster.', 'memberpress'),
        'image'        => 'easyaffiliate.png',
        'url'          => 'https://easyaffiliate.com/',
        'plugin_file'  => 'easy-affiliate/easy-affiliate.php',
    ],
    [
        'title'        => __('Pretty Links', 'memberpress'),
        'description'  => __('Organize and track all your promotional links.', 'memberpress'),
        'image'        => 'prettylinks.png',
        'url'          => 'https://prettylinks.com/',
        'plugin_file'  => 'pretty-link/pretty-link.php',
    ],
];

/**
 * Filter dashboard add-ons. Brands can add their add-ons here.
 *
 * @param array $addons Array of addon items with 'title', 'description', 'image', 'url' keys.
 *                      Optional 'plugin_file' key: if present and plugin is active, addon is excluded from the list.
 */
$addons = MeprHooks::apply_filters('mepr_dashboard_addons', $addons);

// Only show add-ons that are not already installed/active.
$addons = array_values(array_filter($addons, function ($addon) {
    if (empty($addon['plugin_file'])) {
        return true;
    }
    return !is_plugin_active($addon['plugin_file']);
}));
?>

<div class="mepr-dash-addons" id="mepr-dash-addons">
    <h2><?php esc_html_e('Extend Membership Site', 'memberpress'); ?></h2>

    <?php if (!empty($addons)) : ?>
    <div class="mepr-dash-addons-list">
        <?php foreach ($addons as $addon) : ?>
            <a href="<?php echo esc_url($addon['url']); ?>" class="mepr-dash-addon-item" target="_blank" rel="noopener noreferrer">
                <div class="mepr-dash-addon-icon">
                    <img src="<?php echo esc_url($images_url . '/' . $addon['image']); ?>" alt="">
                </div>
                <div class="mepr-dash-addon-content">
                    <div class="mepr-dash-addon-title"><?php echo esc_html($addon['title']); ?></div>
                    <div class="mepr-dash-addon-description"><?php echo esc_html($addon['description']); ?></div>
                </div>
                <div class="mepr-dash-addon-arrow">
                    <img src="<?php echo esc_url($images_url . '/icon-arrow-up-right.svg'); ?>" alt="" width="16" height="16">
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php else : ?>
    <p class="mepr-dash-addons-all-installed"><?php esc_html_e("You've got the recommended add-ons. Explore more in Growth Tools.", 'memberpress'); ?></p>
    <?php endif; ?>

    <div class="mepr-dash-addons-footer">
        <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-growth-tools')); ?>" class="mepr-dash-addons-link">
            <?php esc_html_e('Browse All Growth Tools', 'memberpress'); ?>
            <img src="<?php echo esc_url($images_url . '/icon-arrow-right.svg'); ?>" alt="" width="16" height="16">
        </a>
    </div>
</div>
