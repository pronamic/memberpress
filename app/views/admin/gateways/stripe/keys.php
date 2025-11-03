<?php
defined('ABSPATH') || exit;
$hide_keys = ! isset($_GET['display-keys']) && ! isset($_COOKIE['mepr_stripe_display_keys']) && ! defined('MEPR_DISABLE_STRIPE_CONNECT');
?>
<?php if (MeprStripeGateway::stripe_connect_status($id) === 'connected' || !$hide_keys) { ?>
<div class="stripe-checkout-method-select">
  <label class="mepr-stripe-method <?php echo $stripe_checkout_enabled ? '' : 'selected'; ?>">
    <div align="center" class="mepr-heading-section"><span class="stripe-title">stripe</span> Elements</div>
    <ul class="stripe-features">
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('Accept Credit Cards on site', 'memberpress'); ?></li>
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('Recurring billing', 'memberpress'); ?></li>
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('SCA ready', 'memberpress'); ?></li>
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('Accept Apple Pay', 'memberpress'); ?></li>
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('Accept Google Wallet', 'memberpress'); ?></li>
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('25+ ways to pay', 'memberpress'); ?></li>
    </ul>
    <input type="radio" class="mepr-toggle-checkbox" data-box="mepr_stripe_checkout_<?php echo esc_attr($id); ?>_box" name="<?php echo esc_attr($stripe_checkout_enabled_str); ?>" <?php checked($stripe_checkout_enabled, false); ?> value="off"/>
  </label>
  <label class="mepr-stripe-method <?php echo $stripe_checkout_enabled ? 'selected' : ''; ?>">
    <div align="center" class="mepr-heading-section"><span class="stripe-title">stripe</span> Checkout</div>
    <ul class="stripe-features">
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('Offsite secure hosted solution', 'memberpress'); ?></li>
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('Accept Credit Cards', 'memberpress'); ?></li>
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('Accept Apple Pay', 'memberpress'); ?></li>
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('Accept Google Wallet', 'memberpress'); ?></li>
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('25+ ways to pay', 'memberpress'); ?></li>
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('Recurring billing', 'memberpress'); ?></li>
      <li><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/Check_Mark.svg'); ?>" alt=""><?php esc_html_e('SCA ready', 'memberpress'); ?></li>
    </ul>
    <input type="radio" class="mepr-toggle-checkbox" data-box="mepr_stripe_checkout_<?php echo esc_attr($id); ?>_box" name="<?php echo esc_attr($stripe_checkout_enabled_str); ?>" <?php checked($stripe_checkout_enabled, true); ?> value="on"/>
  </label>
</div>
<?php } ?>
<div <?php echo $hide_keys ? 'class="mepr-hidden"' : ''; ?>>
  <table id="mepr-stripe-test-keys-<?php echo esc_attr($id); ?>" class="form-table mepr-stripe-test-keys mepr-hidden">
    <tbody>
      <tr valign="top">
        <th scope="row"><label for="<?php echo esc_attr($test_public_key_str); ?>"><?php esc_html_e('Test Publishable Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo esc_attr($test_public_key_str); ?>" value="<?php echo esc_attr($test_public_key); ?>" /></td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="<?php echo esc_attr($test_secret_key_str); ?>"><?php esc_html_e('Test Secret Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo esc_attr($test_secret_key_str); ?>" value="<?php echo esc_attr($test_secret_key); ?>" /></td>
      </tr>
    </tbody>
  </table>
  <table id="mepr-stripe-live-keys-<?php echo esc_attr($id); ?>" class="form-table mepr-stripe-live-keys mepr-hidden">
    <tbody>
      <tr valign="top">
        <th scope="row"><label for="<?php echo esc_attr($live_public_key_str); ?>"><?php esc_html_e('Live Publishable Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo esc_attr($live_public_key_str); ?>" value="<?php echo esc_attr($live_public_key); ?>" /></td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="<?php echo esc_attr($live_secret_key_str); ?>"><?php esc_html_e('Live Secret Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo esc_attr($live_secret_key_str); ?>" value="<?php echo esc_attr($live_secret_key); ?>" /></td>
      </tr>
    </tbody>
  </table>
  <input class="mepr-stripe-connect-status" type="hidden" name="<?php echo esc_attr($connect_status_string); ?>" value="<?php echo esc_attr($connect_status); ?>" />
  <input class="mepr-stripe-service-account-id" type="hidden" name="<?php echo esc_attr($service_account_id_string); ?>" value="<?php echo esc_attr($service_account_id); ?>" />
  <input class="mepr-stripe-service-account-name" type="hidden" name="<?php echo esc_attr($service_account_name_string); ?>" value="<?php echo esc_attr($service_account_name); ?>" />
  <input class="mepr-stripe-country" type="hidden" name="<?php echo esc_attr($country_string); ?>" value="<?php echo esc_attr($country); ?>" />
</div>
