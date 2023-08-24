<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<tr valign="top">
  <th scope="row">
    <label for="mepr_tax_stripe_enabled"><a href="https://stripe.com/tax" target="_blank"><?php esc_html_e('Enable Stripe Tax', 'memberpress'); ?></a></label>
    <?php
      MeprAppHelper::info_tooltip( 'mepr-enable-tax-stripe',
        __('Enable Stripe Tax', 'memberpress'),
        __('Use Stripe Tax for automatic tax calculations.', 'memberpress')
      );
    ?>
    <?php echo MeprUtils::new_badge(); ?>
  </th>
  <td>
    <?php if(empty($stripe_payment_methods)) : ?>
      <p><?php esc_html_e('You must add a Stripe payment method on the Payments tab and connect to Stripe to use Stripe Tax.', 'memberpress'); ?></p>
    <?php else : ?>
      <input type="checkbox" id="mepr_tax_stripe_enabled" name="mepr_tax_stripe_enabled" class="mepr-toggle-checkbox" data-box="mepr_tax_stripe_box" value="1" <?php checked($tax_stripe_enabled); ?> />
    <?php endif; ?>
  </td>
</tr>
<?php if(is_array($stripe_payment_methods) && count($stripe_payment_methods)) : ?>
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
                  <?php if(!array_key_exists($selected_payment_method, $stripe_payment_methods)) : ?>
                    <option value=""><?php esc_html_e('Please select', 'memberpress'); ?></option>
                  <?php endif; ?>
                  <?php foreach($stripe_payment_methods as $id => $label) : ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php echo selected($selected_payment_method, $id); ?>><?php echo esc_html($label); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </td>
  </tr>
<?php endif; ?>
