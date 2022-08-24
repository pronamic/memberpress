<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<?php
  $mepr_options = MeprOptions::fetch();
  $existing_gateway = $mepr_options->payment_method('default', false);
  $onboarding_gateway = get_option('mepr_onboarding_payment_gateway');

  if(empty($onboarding_gateway) && $existing_gateway instanceof MeprBaseGateway) {
    update_option('mepr_onboarding_payment_gateway', $existing_gateway->id);
  }
?>
<h2 class="mepr-wizard-step-title"><?php esc_html_e('Get set up to accept payments', 'memberpress'); ?></h2>
<p class="mepr-wizard-step-description"><?php esc_html_e("You won't believe how easy it is to accept online payments with MemberPress. Just choose your gateway below, and we'll get you going.", 'memberpress'); ?></p>
<div id="mepr-wizard-payments"<?php echo MeprOnboardingHelper::get_payment_gateway_data() ? ' class="mepr-hidden"' : ''; ?>>
  <div class="mepr-wizard-payments">
    <div class="mepr-wizard-payments-stripe">
      <div class="mepr-wizard-payments-image">
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/stripe-logo.png'); ?>" alt="">
      </div>
      <p><?php esc_html_e("The world's most powerful and easy to use payment gateway.", 'memberpress'); ?></p>
      <button type="button" id="mepr-wizard-add-stripe" class="mepr-wizard-button-blue"><?php esc_html_e('Add Stripe', 'memberpress'); ?></button>
      <div class="mepr-wizard-hr"></div>
      <div class="mepr-wizard-payments-features">
        <div><?php esc_html_e('Stripe Offers', 'memberpress'); ?></div>
        <div><?php esc_html_e('Accept all Major Credit Cards', 'memberpress'); ?></div>
        <div><?php esc_html_e('Flexible subscriptions and billing terms', 'memberpress'); ?></div>
        <div><?php esc_html_e('Accept SEPA, Apple Pay, Google Wallet, iDeal', 'memberpress'); ?></div>
        <div><?php esc_html_e('Fraud prevention tools', 'memberpress'); ?></div>
      </div>
    </div>
    <div class="mepr-wizard-payments-paypal">
      <div class="mepr-wizard-payments-image">
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/PayPal_with_Tagline.svg'); ?>" alt="">
      </div>
      <p><?php esc_html_e('The faster, safer way to receive money or set up a merchant account.', 'memberpress'); ?></p>
      <?php
        $gateway = new MeprPayPalCommerceGateway();
        $sandbox = apply_filters( 'mepr_onboarding_paypal_sandbox', false );
      ?>
      <a href="<?php echo esc_url(MeprPayPalCommerceGateway::get_paypal_connect_url($gateway->id, $sandbox, true)); ?>&displayMode=embedded" data-paypal-button="true" data-paypal-onboard-complete="MeprOnboardingPayPalComplete" data-gateway-id="<?php echo esc_attr($gateway->id); ?>" data-sandbox="<?php echo $sandbox ? 'true' : 'false'; ?>" id="mepr-wizard-add-paypal" class="mepr-wizard-button-blue"><?php esc_html_e('Add PayPal', 'memberpress'); ?></a>
      <div class="mepr-wizard-hr"></div>
      <div class="mepr-wizard-payments-features">
        <div><?php esc_html_e('PayPal Offers', 'memberpress'); ?></div>
        <div><?php esc_html_e('Secure Payments', 'memberpress'); ?></div>
        <div><?php esc_html_e('Global Support', 'memberpress'); ?></div>
        <div><?php esc_html_e('Pay Later Support', 'memberpress'); ?></div>
        <div><?php esc_html_e('Venmo', 'memberpress'); ?></div>
        <div><?php esc_html_e('Easy to Setup', 'memberpress'); ?></div>
        <div><?php esc_html_e('Recurring Subscriptions', 'memberpress'); ?></div>
      </div>
    </div>
    <div class="mepr-wizard-payments-authorize">
      <span class="mepr-wizard-pro-badge"><?php esc_html_e('Pro', 'memberpress'); ?></span>
      <div class="mepr-wizard-payments-image">
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/authorize.net.svg'); ?>" alt="">
      </div>
      <p><?php esc_html_e('Accept payments anytime, anywhere.', 'memberpress'); ?></p>
      <?php if(MeprOnboardingHelper::is_pro_license()): ?>
        <button type="button" id="mepr-wizard-add-authorize" class="mepr-wizard-button-blue"><?php esc_html_e('Add Authorize.net', 'memberpress'); ?></button>
      <?php else: ?>
        <button type="button" id="mepr-wizard-add-authorize" class="mepr-wizard-button-orange mepr-optin"><?php esc_html_e('Add Authorize.net', 'memberpress'); ?></button>
      <?php endif; ?>
      <div class="mepr-wizard-hr"></div>
      <div class="mepr-wizard-payments-features">
        <div><?php esc_html_e('Authorize.net Offers', 'memberpress'); ?></div>
        <div><?php esc_html_e('Accept all Major Credit Cards', 'memberpress'); ?></div>
        <div><?php esc_html_e('Advanced Fraud Protection', 'memberpress'); ?></div>
        <div><?php esc_html_e('Recurring Payments', 'memberpress'); ?></div>
      </div>
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
