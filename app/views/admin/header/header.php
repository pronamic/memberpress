<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
?>

<div id="mp-admin-header">
    <img class="mp-logo" src="<?php echo esc_url(MEPR_BRAND_URL . '/images/logo.svg'); ?>" alt="MemberPress logo" />
    <div class="mp-admin-header-actions">
        <a class="mp-support-button button button-primary" href="<?php echo admin_url('admin.php?page=memberpress-support'); ?>"><?php _e('Support', 'memberpress')?></a>
        <?php MeprHooks::do_action('mepr_admin_header_actions'); ?>
    </div>
    <?php MeprHooks::do_action('mepr_admin_header'); ?>
</div>
