<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
?>
<p>
    <?php
    esc_html_e('Hi,', 'memberpress');
    ?>
</p>

<p>
    <?php
    printf(
        // Translators: 1: site name, 2: site url.
        esc_html__('You’re just about ready to launch %1$s (%2$s), but there aren’t any protection rules yet so premium content is still wide open.', 'memberpress'),
        esc_html($site_name),
        esc_url($site_url)
    );
    ?>
</p>

<p><?php esc_html_e('If you let me know what content should be members-only I can send back ready-to-import rules or a quick screen recording showing the exact steps.', 'memberpress'); ?></p>

<p>
    <a href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener">
        <?php esc_html_e('Create content protection rules', 'memberpress'); ?>
    </a>
</p>

<p><?php esc_html_e('Almost there,', 'memberpress'); ?><br><?php esc_html_e('MemberPress Support', 'memberpress'); ?></p>
