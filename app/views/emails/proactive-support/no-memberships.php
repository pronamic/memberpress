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
        esc_html__('Onboarding is complete on %1$s (%2$s), but there still aren’t any memberships defined. Creating even a placeholder membership product unlocks the registration experiences and paywall rules.', 'memberpress'),
        esc_html($site_name),
        esc_url($site_url)
    );
    ?>
</p>

<p><?php esc_html_e('Stuck on pricing, tiers, or naming? Reply with your offer idea and I’ll send back a few examples based on what’s working for other site owners.', 'memberpress'); ?></p>

<p>
    <a href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener">
        <?php esc_html_e('Create your first membership', 'memberpress'); ?>
    </a>
</p>

<p><?php esc_html_e('Happy to help brainstorm,', 'memberpress'); ?><br><?php esc_html_e('MemberPress Support', 'memberpress'); ?></p>
