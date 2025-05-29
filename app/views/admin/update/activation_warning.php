<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
?>
<?php if (defined('MEMBERPRESS_LICENSE_KEY') and isset($error)) : ?>
    <div class="error" style="padding: 10px;">
        <?php
        printf(
            // Translators: %s: error message.
            __('Error with MEMBERPRESS_LICENSE_KEY: %s', 'memberpress'),
            $error
        );
        ?>
    </div>
<?php else : ?>
    <?php
    $settings_page_url = admin_url('admin.php?page=memberpress-options');
    if (MeprDrmHelper::is_locked()) {
        $settings_page_url = admin_url('admin.php?page=memberpress-drm');
    }
    ?>
    <div class="error drm-mepr-activation-warning" style="padding: 10px;">
        <?php
        printf(
            // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag, %3$s: opening anchor tag, %4$s: closing anchor tag.
            __('<b>MemberPress doesn\'t have a valid license key installed.</b> Go to the MemberPress %1$ssettings page%2$s to activate your license or go to %3$smemberpress.com%4$s to get one.', 'memberpress'),
            '<a href="' . esc_url($settings_page_url) . '">',
            '</a>',
            '<a href="https://memberpress.com/">',
            '</a>'
        );
        ?>
    </div>
<?php endif; ?>
