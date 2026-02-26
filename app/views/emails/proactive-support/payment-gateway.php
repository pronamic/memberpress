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
        esc_html__('Memberships are ready on %1$s (%2$s) but no payment gateways are connected yet. Without Stripe, PayPal, or another gateway, members won’t be able to check out.', 'memberpress'),
        esc_html($site_name),
        esc_url($site_url)
    );
    ?>
</p>

<p><?php esc_html_e('Need help gathering API keys or verifying webhook URLs? I can send you step-by-step instructions for your preferred gateway.', 'memberpress'); ?></p>

<p>
    <a href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener">
        <?php esc_html_e('Connect a payment gateway', 'memberpress'); ?>
    </a>
</p>

<p><?php esc_html_e('Talk soon,', 'memberpress'); ?><br><?php esc_html_e('MemberPress Support', 'memberpress'); ?></p>
