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
        esc_html__('Gateways are connected on %1$s (%2$s) but no test or live transactions have run yet. A quick $1 test confirms webhooks, receipts, and onboarding emails are working.', 'memberpress'),
        esc_html($site_name),
        esc_url($site_url)
    );
    ?>
</p>

<p><?php esc_html_e('Want me to run through a test payment with you or check your webhook log? Just reply and I’ll jump in.', 'memberpress'); ?></p>

<p>
    <a href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener">
        <?php echo esc_html($cta_text ?? __('Run a test transaction', 'memberpress')); ?>
    </a>
</p>

<p><?php esc_html_e('Let’s get payments flowing,', 'memberpress'); ?><br><?php esc_html_e('MemberPress Support', 'memberpress'); ?></p>
