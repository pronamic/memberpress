<?php
if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

$success_url      = $payment_method->notify_url('return');
$smart_payment_on = $payment_method->settings->enable_smart_button == 'on';

static $unique_suffix = 0;
$unique_suffix++;

if ($payment_method->settings->use_desc) {
    echo wpautop(esc_html(trim($payment_method->settings->desc)));
}
?>

<div role="alert" class="mepr-paypal-card-errors"></div>

<?php if ($smart_payment_on) { ?>
<div class="mepr-paypal-button-container"
     style="display: inline-block"
     data-payment-method-id="<?php echo esc_attr($payment_method->id); ?>"
     data-success-url="<?php echo esc_attr($success_url); ?>"
     id="mepr-paypal-button-container-<?php echo esc_attr($payment_method->id); ?>-<?php echo esc_attr($unique_suffix); ?>"></div>

<noscript><p
      class="mepr_nojs"><?php esc_html_e('JavaScript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p>
</noscript>
<?php } ?>
