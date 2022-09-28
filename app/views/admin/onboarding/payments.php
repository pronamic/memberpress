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
