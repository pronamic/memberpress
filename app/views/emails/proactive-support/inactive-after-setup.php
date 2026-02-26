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
        esc_html__('%1$s (%2$s) looks fully configured, but no new members have registered in the last few weeks. Want a second set of eyes on your offer or funnel?', 'memberpress'),
        esc_html($site_name),
        esc_url($site_url)
    );
    ?>
</p>

<p><?php esc_html_e('I can review your pricing page, make sure checkout is working, or share a few quick wins (like limited-time bonuses) that typically jump-start enrollments.', 'memberpress'); ?></p>

<p>
    <a href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener">
        <?php esc_html_e('Review member activity', 'memberpress'); ?>
    </a>
</p>

<p><?php esc_html_e('Here to help reignite things,', 'memberpress'); ?><br><?php esc_html_e('MemberPress Support', 'memberpress'); ?></p>
