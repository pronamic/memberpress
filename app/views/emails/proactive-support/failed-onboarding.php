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
        esc_html__('Looks like %1$s (%2$s) hasn’t completed the MemberPress onboarding wizard yet. Finishing the guided steps takes about five minutes and unlocks the rest of the setup tools.', 'memberpress'),
        esc_html($site_name),
        esc_url($site_url)
    );
    ?>
</p>

<p><?php esc_html_e('Want me to spot-check the wizard with you or send over a quick loom? Just hit reply and I’ll help you wrap things up.', 'memberpress'); ?></p>

<p>
    <a href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener">
        <?php esc_html_e('Continue the onboarding wizard', 'memberpress'); ?>
    </a>
</p>

<p><?php esc_html_e('Cheering you on,', 'memberpress'); ?><br><?php esc_html_e('MemberPress Support', 'memberpress'); ?></p>
