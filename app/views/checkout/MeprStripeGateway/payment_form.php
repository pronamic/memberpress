<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

if (isset($user) && $user instanceof MeprUser && isset($mepr_options)) {
    MeprView::render('/checkout/MeprStripeGateway/payment_gateway_fields', get_defined_vars());
}
?>
<?php if ($payment_method->settings->stripe_checkout_enabled == 'on') : ?>
    <?php MeprHooks::do_action('mepr-stripe-payment-form-before-name-field', $txn); ?>
    <?php if ($payment_method->settings->use_desc) : ?>
    <div class="mepr-stripe-gateway-description"><?php esc_html_e('Pay with your Credit Card via Stripe Checkout', 'memberpress'); ?></div>
    <?php endif; ?>
<?php else : ?>
    <?php MeprHooks::do_action('mepr-stripe-payment-form-before-name-field', $txn); ?>
  <div class="mepr-stripe-elements">
    <?php MeprHooks::do_action('mepr-stripe-payment-form-card-field', $txn); ?>
    <div class="mepr-stripe-card-element" data-stripe-public-key="<?php echo esc_attr($payment_method->get_public_key()); ?>" data-payment-method-id="<?php echo esc_attr($payment_method->settings->id); ?>" data-locale-code="<?php echo esc_attr(MeprStripeGateway::get_locale_code()); ?>" data-elements-options="<?php echo isset($elements_options) ? esc_attr(wp_json_encode($elements_options)) : ''; ?>" data-user-email="<?php echo isset($user) && $user instanceof MeprUser ? esc_attr($user->user_email) : ''; ?>"></div>
    <div role="alert" class="mepr-stripe-card-errors"></div>
  </div>
<?php endif; ?>
<?php MeprHooks::do_action('mepr-stripe-payment-form', $txn); ?>
<noscript><p class="mepr_nojs"><?php esc_html_e('JavaScript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
