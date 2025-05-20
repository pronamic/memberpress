<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<?php
  $mepr_options       = MeprOptions::fetch();
  $existing_gateway   = $mepr_options->payment_method('default', false);
  $onboarding_gateway = get_option('mepr_onboarding_payment_gateway');

if (empty($onboarding_gateway) && $existing_gateway instanceof MeprBaseGateway) {
    update_option('mepr_onboarding_payment_gateway', $existing_gateway->id);
}
?>
<h2 class="mepr-wizard-step-title"><?php esc_html_e('Get set up to accept payments', 'memberpress'); ?></h2>
<p class="mepr-wizard-step-description"><?php esc_html_e("You won't believe how easy it is to accept online payments with MemberPress.", 'memberpress'); ?></p>
<div id="mepr-wizard-payments"<?php echo MeprOnboardingHelper::get_payment_gateway_data() ? ' class="mepr-hidden"' : ''; ?>>
  <div class="mepr-wizard-payments">
    <div class="mepr-wizard-payments-stripe">
      <div class="mepr-wizard-payments-image">
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/stripe-logo.png'); ?>" alt="">
      </div>
      <p class="mepr-payment-description"><?php esc_html_e("The world's most powerful and easy to use payment gateway.", 'memberpress'); ?></p>
      <div class="mepr-wizard-hr"></div>
      <div class="mepr-wizard-feature-highlight"><?php esc_html_e('Stripe Offers', 'memberpress'); ?></div>
      <ul class="mepr-wizard-payments-features">
        <li><?php esc_html_e('Accept all Major Credit Cards', 'memberpress'); ?></li>
        <li><?php esc_html_e('Flexible subscriptions and billing terms', 'memberpress'); ?></li>
        <li><?php esc_html_e('Accept SEPA, Apple Pay, Google Wallet, iDeal', 'memberpress'); ?></li>
        <li><?php esc_html_e('Fraud prevention tools', 'memberpress'); ?></li>
      </ul>
      <div class="mepr-wizard-feature-plusmore"><?php esc_html_e('Plus more', 'memberpress'); ?></div>
      <button type="button" id="mepr-wizard-add-stripe" class="mepr-wizard-button-blue"><?php esc_html_e('Add Stripe', 'memberpress'); ?></button>
    </div>
  </div>
</div>
<div id="mepr-wizard-payment-selected">
  <?php echo MeprOnboardingHelper::get_payment_gateway_html(); ?>
</div>
<div id="mepr-wizard-configure-authorize-popup" class="mepr-wizard-popup mfp-hide">
  <h2><?php esc_html_e('Authorize.net', 'memberpress'); ?></h2>
  <div class="mepr-wizard-popup-field">
    <label for="mepr-wizard-authorize-login-name"><?php esc_html_e('API Login ID', 'memberpress'); ?></label>
    <input type="text" id="mepr-wizard-authorize-login-name">
  </div>
  <div class="mepr-wizard-popup-field">
    <label for="mepr-wizard-authorize-transaction-key"><?php esc_html_e('Transaction Key', 'memberpress'); ?></label>
    <input type="text" id="mepr-wizard-authorize-transaction-key">
  </div>
  <div class="mepr-wizard-popup-field">
    <label for="mepr-wizard-authorize-signature-key"><?php esc_html_e('Signature Key', 'memberpress'); ?></label>
    <input type="text" id="mepr-wizard-authorize-signature-key">
  </div>
  <div class="mepr-wizard-popup-field">
    <label for="mepr-wizard-authorize-webhook_url"><?php esc_html_e('Webhook URL', 'memberpress'); ?></label>
    <input type="text" id="mepr-wizard-authorize-webhook_url" disabled>
  </div>
  <div class="mepr-wizard-popup-button-row">
    <button type="button" id="mepr-wizard-configure-authorize-save" class="mepr-wizard-button-blue"><?php esc_html_e('Done', 'memberpress'); ?></button>
  </div>
</div>
<div id="mepr-wizard-skip-payment-methods-popup" class="mepr-wizard-popup mfp-hide">
  <h2><?php esc_html_e('Be sure to add your payment option', 'memberpress'); ?></h2>
  <p><?php esc_html_e('If you skip this step, MemberPress will set up the offline payment option automatically. You can later visit MemberPress > Settings > Payments to get your gateway rolling.', 'memberpress'); ?></p>
  <div class="mepr-wizard-popup-button-row">
    <button type="button" id="mepr-wizard-add-offline-payment-method" class="mepr-wizard-button-blue"><?php esc_html_e('Add Offline Payment', 'memberpress'); ?></button>
  </div>
</div>

<div id="mepr-wizard-authnet-pro-optin-popup" class="mepr-wizard-popup mfp-hide">
  <h2><?php esc_html_e('Trying to connect with Authorize.net?', 'memberpress'); ?></h2>
  <p><?php esc_html_e('You\'ll need a Pro license to enable this gateway. But it\'s easy! Just click the button below and follow the prompts.', 'memberpress'); ?></p>

  <div class="mepr-wizard-popup-button-row">
    <a href="<?php echo admin_url('admin.php?page=memberpress-onboarding&step=7'); ?>" id="mepr-wizard-configure-authorize-continue" class="mepr-wizard-button-orange"><?php esc_html_e('Continue to Upgrade', 'memberpress'); ?></a>
  </div>
</div>

<?php if ($existing_gateway instanceof MeprStripeGateway && !get_option('mepr_tax_stripe_enabled') && isset($_GET['step']) && $_GET['step'] == '6') : ?>
  <div id="mepr-wizard-enable-stripe-tax-popup" class="mepr-wizard-popup mfp-hide">
    <h2><?php esc_html_e('Do you need to collect taxes?', 'memberpress'); ?></h2>
    <p>
      <input type="checkbox" id="mepr_wizard_enable_stripe_tax" data-gateway-id="<?php echo esc_attr($existing_gateway->id); ?>">
      <label for="mepr_wizard_enable_stripe_tax">
        <img class="mepr-wizard-stripe-tax-checked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-checked.svg'); ?>" alt="">
        <img class="mepr-wizard-stripe-tax-unchecked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-unchecked.svg'); ?>" alt="">
        <span id="mepr-wizard-stripe-tax-label"><?php esc_html_e('Just check this box to enable automatic tax rate lookups and calculations with Stripe.*', 'memberpress'); ?></span>
      </label>
    </p>
    <p class="mepr-wizard-stripe-tax-fine-print">
      <?php
        printf(
          // Translators: %1$s: open link tag, %2$s: close link tag.
            esc_html__('* Pricing for Stripe Tax API starts at 0.50 USD per transaction, where you\'re registered to collect taxes. This includes 10 calculation API calls per transaction and is priced at 0.05 USD per additional calculation API call beyond 10. To learn more, visit the %1$sStripe Tax pricing page%2$s.', 'memberpress'),
            '<a href="https://stripe.com/tax#pricing" target="_blank">',
            '</a>'
        );
        ?>
    </p>
  </div>
  <div id="mepr-wizard-stripe-tax-enabled-popup" class="mepr-wizard-popup mfp-hide">
    <h2 class="mepr-text-align-center"><?php esc_html_e('Stripe Tax is now enabled', 'memberpress'); ?></h2>
    <p class="mepr-wizard-step-description mepr-text-align-center">
      <?php
        printf(
          // Translators: %1$s: open link tag, %2$s: close link tag, %3$s: open link tag, %4$s: close link tag.
            __('In the Stripe dashboard, please ensure that %1$sStripe Tax is enabled%2$s and that a %3$sRegistration is added%4$s for each location where tax should be collected.', 'memberpress'),
            '<a href="https://dashboard.stripe.com/tax" target="_blank">',
            '</a>',
            '<a href="https://dashboard.stripe.com/tax/registrations" target="_blank">',
            '</a>'
        );
        ?>
    </p>
  </div>
  <div id="mepr-wizard-stripe-tax-inactive-popup" class="mepr-wizard-popup mfp-hide">
    <h2 class="mepr-text-align-center"><?php esc_html_e('Stripe Tax could not be enabled', 'memberpress'); ?></h2>
    <p class="mepr-wizard-step-description mepr-text-align-center">
      <?php
        printf(
          // Translators: %1$s: open link tag, %2$s: close link tag, %3$s: open link tag, %4$s: close link tag.
            esc_html__('In the Stripe dashboard, please ensure that %1$sStripe Tax is enabled%2$s and that a %3$sRegistration is added%4$s for each location where tax should be collected.', 'memberpress'),
            '<a href="https://dashboard.stripe.com/tax" target="_blank">',
            '</a>',
            '<a href="https://dashboard.stripe.com/tax/registrations" target="_blank">',
            '</a>'
        );
        ?>
    </p>
    <p class="mepr-wizard-step-description mepr-text-align-center">
      <?php esc_html_e('Once Stripe Tax is enabled in the Stripe dashboard, you can enable Stripe Tax at MemberPress &rarr; Settings &rarr; Taxes.', 'memberpress'); ?>
    </p>
  </div>
<?php endif; ?>
