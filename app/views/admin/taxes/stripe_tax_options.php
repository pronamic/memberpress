<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<tr valign="top">
  <th scope="row">
    <label for="mepr_tax_stripe_enabled"><a href="https://stripe.com/tax" target="_blank"><?php esc_html_e('Enable Stripe Tax', 'memberpress'); ?></a></label>
    <?php
      MeprAppHelper::info_tooltip(
          'mepr-enable-tax-stripe',
          __('Enable Stripe Tax', 'memberpress'),
          __('Use Stripe Tax for automatic tax calculations.', 'memberpress')
      );
        ?>
    <?php echo MeprUtils::new_badge(); ?>
  </th>
  <td>
    <?php if (empty($stripe_payment_methods)) : ?>
      <p><?php esc_html_e('You must add a Stripe payment method on the Payments tab and connect to Stripe to use Stripe Tax.', 'memberpress'); ?></p>
    <?php else : ?>
      <input type="checkbox" id="mepr_tax_stripe_enabled" name="mepr_tax_stripe_enabled" class="mepr-toggle-checkbox" data-box="mepr_tax_stripe_box" value="1" <?php checked($tax_stripe_enabled); ?> />
    <?php endif; ?>
  </td>
</tr>
<?php if (is_array($stripe_payment_methods) && count($stripe_payment_methods)) : ?>
  <tr valign="top">
    <td colspan="2" class="mepr-sub-box-wrapper">
      <div id="mepr_tax_stripe_box" class="mepr-sub-box mepr_tax_stripe_box">
        <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
        <table class="form-table">
          <tbody>
            <tr valign="top">
              <th scope="row">
                <label for="mepr_tax_stripe_payment_method"><?php esc_html_e('Stripe Tax Payment Method', 'memberpress'); ?>*</label>
                <?php
                  MeprAppHelper::info_tooltip(
                      'mepr-tax-stripe-payment-method',
                      __('Stripe Tax Payment Method', 'memberpress'),
                      __('Choose which Stripe payment method to use for Stripe Tax.', 'memberpress')
                  );
                ?>
              </th>
              <td>
                <select id="mepr_tax_stripe_payment_method" name="mepr_tax_stripe_payment_method" class="regular-text">
                  <?php if (!array_key_exists($selected_payment_method, $stripe_payment_methods)) : ?>
                    <option value=""><?php esc_html_e('Please select', 'memberpress'); ?></option>
                  <?php endif; ?>
                  <?php foreach ($stripe_payment_methods as $id => $label) : ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php echo selected($selected_payment_method, $id); ?>><?php echo esc_html($label); ?></option>
                  <?php endforeach; ?>
                </select>
                <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/square-loader.gif'); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" id="mepr-loader-validate-stripe-tax" class="mepr_loader">
                <div id="mepr-stripe-tax-inactive-popup" class="mepr-shared-popup mfp-hide">
                  <h2 class="mepr-text-align-center"><?php esc_html_e('Stripe Tax is not active on that Stripe account', 'memberpress'); ?></h2>
                  <p class="mepr-text-align-center">
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
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </td>
  </tr>
<?php endif; ?>
