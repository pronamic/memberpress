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
        esc_html__('Memberships exist for %1$s (%2$s) but there isn’t a pricing page yet, so visitors don’t have a front door to sign up.', 'memberpress'),
        esc_html($site_name),
        esc_url($site_url)
    );
    ?>
</p>

<p><?php esc_html_e('Need design ideas or shortcode examples? I can show you a few high-converting layouts and help you customize the copy for your offer.', 'memberpress'); ?></p>

<p>
    <a href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener">
        <?php esc_html_e('Build a pricing/registration page', 'memberpress'); ?>
    </a>
</p>

<p><?php esc_html_e('Here if you need me,', 'memberpress'); ?><br><?php esc_html_e('MemberPress Support', 'memberpress'); ?></p>
