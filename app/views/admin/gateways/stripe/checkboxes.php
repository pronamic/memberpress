<?php

$classes = '';

if (! isset($_GET['display-keys']) && ! isset($_COOKIE['mepr_stripe_display_keys']) && ! defined('MEPR_DISABLE_STRIPE_CONNECT')) {
    $classes = 'class="mepr-hidden"';
}

?>

<table class="form-table">
  <tbody>
    <?php if (MeprStripeGateway::is_stripe_connect($id) || MeprStripeGateway::keys_are_set($id)) : ?>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $test_mode_str; ?>"><?php _e('Test Mode', 'memberpress'); ?></label></th>
        <td><input class="mepr-stripe-testmode" data-integration="<?php echo $id; ?>" type="checkbox" name="<?php echo $test_mode_str; ?>"<?php echo checked($test_mode); ?> <?php disabled((defined('MEMBERPRESS_STRIPE_TESTING') && MEMBERPRESS_STRIPE_TESTING));?> /></td>
      </tr>
        <?php if (count($payment_methods)) : ?>
        <tr valign="top">
          <th colspan="2">
            <div x-data="{ open: false }" class="mepr-stripe-customize-payment-methods">
              <button x-on:click="open = true" type="button" class="button button-secondary"><?php esc_html_e('Customize Payment Methods', 'memberpress'); ?></button>
              <div x-show="open" class="mepr_modal" role="dialog" aria-modal="true" x-cloak>
                <div class="mepr_modal__overlay"></div>
                <div class="mepr_modal__content_wrapper">
                  <div class="mepr_modal__content">
                    <div class="mepr_modal__box" @click.away="open = false">
                      <button x-on:click="open = false" type="button" class="mepr_modal__close">&#x2715;</button>
                      <div>
                        <h3><?php esc_html_e('Customize Payment Methods', 'memberpress'); ?></h3>
                        <div class="notice notice-info inline mepr-hidden mepr-stripe-currency-changed-notice">
                          <p><?php esc_html_e('The configured currency has changed, please save the options to change the payment methods.', 'memberpress'); ?></p>
                        </div>
                        <div class="mepr-stripe-payment-methods">
                          <p>
                            <?php
                              printf(
                                // Translators: %1$s: open link tag, %2$s: close link tag.
                                  esc_html__('Some of these payment methods have limitations. %1$sClick here%2$s to learn more.', 'memberpress'),
                                  '<a href="https://docs.memberpress.com/article/35-stripe" target="_blank">',
                                  '</a>'
                              );
                            ?>
                          </p>
                          <?php foreach ($payment_methods as $key => $payment_method) : ?>
                            <div class="mepr-stripe-payment-method">
                              <label class="switch">
                                <input type="checkbox" id="<?php echo esc_attr(sanitize_key("$payment_methods_str-{$payment_method['key']}")); ?>" class="mepr-stripe-payment-method-checkbox" name="<?php echo esc_attr($payment_methods_str); ?>[]" value="<?php echo esc_attr($payment_method['key']); ?>" <?php checked(in_array($payment_method['key'], $enabled_payment_methods, true)); ?>>
                                <span class="slider round"></span>
                              </label>
                              <label for="<?php echo esc_attr(sanitize_key("$payment_methods_str-{$payment_method['key']}")); ?>"><?php echo esc_html($payment_method['name']); ?></label>
                            </div>
                                <?php if ($key == 0) : ?>
                                    <?php
                                    // These payment methods require domain verification, but aren't separate payment method types
                                    // that can be activated like the others, nor can they be deactivated.
                                    $domain_payment_methods = [
                                        'apple_pay'  => __('Apple Pay', 'memberpress'),
                                        'google_pay' => __('Google Pay', 'memberpress'),
                                    ];
                                    ?>
                                    <?php foreach ($domain_payment_methods as $domain_payment_method => $domain_payment_method_label) : ?>
                                        <?php $domain_payment_method_active = MeprOptionsCtrl::is_payment_method_active($domain_payment_method, $id); ?>
                                <div class="mepr-stripe-payment-method">
                                  <label class="switch">
                                    <input type="checkbox" id="<?php echo esc_attr(sanitize_key("$payment_methods_str-$domain_payment_method")); ?>" class="mepr-stripe-domain-payment-method-checkbox" value="<?php echo esc_attr($domain_payment_method); ?>" <?php checked($domain_payment_method_active); ?> <?php disabled($domain_payment_method_active); ?>>
                                    <span class="slider round"></span>
                                  </label>
                                  <label for="<?php echo esc_attr(sanitize_key("$payment_methods_str-$domain_payment_method")); ?>"><?php echo esc_html($domain_payment_method_label); ?></label>
                                </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                          <?php endforeach; ?>
                        </div>
                        <div class="mepr-update-stripe-payment-methods">
                          <button class="mepr_modal__button button button-primary"><?php echo esc_html_x('Update', 'ui', 'memberpress'); ?></button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </th>
        </tr>
        <?php endif; ?>
    <?php endif; ?>
    <tr valign="top" <?php echo MeprStripeGateway::is_stripe_connect($id) || empty($live_public_key) ? 'style="display:none;"' : ''; ?>>
      <th scope="row"><label for="<?php echo $force_ssl_str; ?>"><?php _e('Force SSL', 'memberpress'); ?></label></th>
      <td><input type="checkbox" name="<?php echo $force_ssl_str; ?>"<?php echo checked($force_ssl); ?> /></td>
    </tr>
    <tr valign="top" <?php echo MeprStripeGateway::is_stripe_connect($id) || empty($live_public_key) ? 'style="display:none;"' : ''; ?>>
      <th scope="row"><label for="<?php echo $debug_str; ?>"><?php _e('Send Debug Emails', 'memberpress'); ?></label></th>
      <td><input type="checkbox" name="<?php echo $debug_str; ?>"<?php echo checked($debug); ?> /></td>
    </tr>
    <tr valign="top" <?php echo $classes; ?>>
      <th scope="row"><label><?php _e('Stripe Webhook URL:', 'memberpress'); ?></label></th>
      <td>
        <?php MeprAppHelper::clipboard_input($whk_url); ?>
      </td>
    </tr>
  </tbody>
</table>
