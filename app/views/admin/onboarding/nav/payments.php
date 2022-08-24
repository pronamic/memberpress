<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<?php
  $mepr_options = MeprOptions::fetch();
  $saved_gateway_id = get_option('mepr_onboarding_payment_gateway');

  if(!empty($mepr_options->integrations) && empty($saved_gateway_id)) {
    $state = 1; // skip without confirmation
  }
  elseif(!empty($saved_gateway_id)) {
    $state = 2; // continue
  }
  else {
    $state = 3; // skip with confirmation
  }
?>
<div id="mepr-wizard-payments-skip"<?php echo $state == 2 || $state == 3  ? ' class="mepr-hidden"' : ''; ?>>
  <button type="button" class="mepr-wizard-button-link mepr-wizard-go-to-step" data-step="7" data-context="skip"><span><?php esc_html_e('Skip', 'memberpress'); ?></span></button>
</div>
<div id="mepr-wizard-payments-continue"<?php echo $state == 1 || $state == 3 ? ' class="mepr-hidden"' : ''; ?>>
  <button type="button" class="mepr-wizard-button-blue mepr-wizard-go-to-step" data-step="7"><?php esc_html_e('Continue', 'memberpress'); ?></button>
</div>
<div id="mepr-wizard-payments-skip-empty"<?php echo $state == 1 || $state == 2 ? ' class="mepr-hidden"' : ''; ?>>
  <button type="button" id="mepr-wizard-skip-payment-methods" class="mepr-wizard-button-link"><span><?php esc_html_e('Skip', 'memberpress'); ?></span></button>
</div>
