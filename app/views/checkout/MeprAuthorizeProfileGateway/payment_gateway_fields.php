<?php if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );
} ?>
<div data-authorizenet-fields="1" class="mepr-authorizenet-cc-form">
  <data data-authorizenet="1" data-merp-gateway-async="1" data-public-key="<?php echo esc_attr($public_key); ?>" data-login-id="<?php echo esc_attr($login_id); ?>"/>
  <div class="mepr-authorizenet-errors mepr-form-has-errors">
  </div>
  <div class="mp-form-row">
    <label><?php esc_html_e('Credit Card Number', 'memberpress'); ?></label>
    <input class="cc-number" maxlength="16" name="credit-number" pattern="\d*" placeholder=""
           type="tel"/>
  </div>
  <div class="mepr-cc-second-row">
    <div>
      <label><?php esc_html_e('Expiration Date', 'memberpress'); ?></label>
      <input class="cc-expires" maxlength="5" name="credit-expires" pattern="\d*" placeholder="MM/YY"
             type="tel"/>
    </div>
    <div>
      <label><?php esc_html_e('Security Code', 'memberpress'); ?></label>
      <input class="cc-cvc" maxlength="4" name="credit-cvc" pattern="\d*"
             placeholder="CVC" type="tel"/>
    </div>
  </div>
  <input type="hidden" name="dataValue" class="dataValue"/>
  <input type="hidden" name="dataDescriptor" class="dataDescriptor"/>
</div>
