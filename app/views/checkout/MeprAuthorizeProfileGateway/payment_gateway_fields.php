<?php if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div data-authorizenet-fields="1" class="mepr-authorizenet-cc-form">
  <data data-authorizenet="1" data-merp-gateway-async="1" data-public-key="<?php echo esc_attr($public_key); ?>" data-is-test="<?php echo esc_attr($is_test); ?>" data-login-id="<?php echo esc_attr($login_id); ?>"/>
  <div class="mepr-authorizenet-errors">
  </div>
  <div class="mp-form-row">
    <div class="card-js"></div>
  </div>
  <input type="hidden" name="dataValue" class="dataValue"/>
  <input type="hidden" name="dataDescriptor" class="dataDescriptor"/>
</div>
