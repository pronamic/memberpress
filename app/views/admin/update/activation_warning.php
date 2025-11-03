<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
?>
<?php if (defined('MEMBERPRESS_LICENSE_KEY') && isset($error)) : ?>
    <div class="error" style="padding: 10px;">
        <?php
        echo esc_html(
            sprintf(
                // Translators: %s: error message.
                __('Error with MEMBERPRESS_LICENSE_KEY: %s', 'memberpress'),
                $error
            )
        );
        ?>
    </div>
<?php else : ?>
    <?php
    $settings_page_url = admin_url('admin.php?page=memberpress-options');
    if (class_exists('MeprDrmHelper') && MeprDrmHelper::is_locked()) {
        $settings_page_url = admin_url('admin.php?page=memberpress-drm');
    }
    ?>
    <div class="error drm-mepr-activation-warning" style="padding: 10px;">
        <?php
        printf(
            // Translators: %1$s: opening bold tag, %2$s: closing bold tag, %3$s: opening anchor tag, %4$s: closing anchor tag, %5$s: opening anchor tag, %6$s: closing anchor tag.
            esc_html__('%1$sMemberPress doesn\'t have a valid license key installed.%2$s Go to the MemberPress %3$ssettings page%4$s to activate your license or go to %5$smemberpress.com%6$s to get one.', 'memberpress'),
            '<b>',
            '</b>',
            '<a href="' . esc_url($settings_page_url) . '">',
            '</a>',
            '<a href="' . esc_url(MeprUtils::get_link_url('home')) . '">',
            '</a>'
        );
        ?>
    </div>
<?php endif; ?>
