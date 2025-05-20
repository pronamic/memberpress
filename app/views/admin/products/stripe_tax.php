<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div id="mepr-stripe-tax-options" class="mepr-product-adv-item<?php echo $product->tax_exempt ? ' mepr-hidden' : ''; ?>">
  <label for="_mepr_tax_stripe_tax_code">
    <span><?php esc_html_e('Stripe Tax Category', 'memberpress'); ?>:</span>
    <select name="_mepr_tax_stripe_tax_code" id="_mepr_tax_stripe_tax_code">
      <option value="" <?php selected($tax_code, ''); ?>><?php esc_html_e('Default tax category', 'memberpress'); ?></option>
      <option value="txcd_10000000" <?php selected($tax_code, 'txcd_10000000'); ?>><?php esc_html_e('General - Electronically Supplied Services', 'memberpress'); ?></option>
      <option value="txcd_99999999" <?php selected($tax_code, 'txcd_99999999'); ?>><?php esc_html_e('General - Tangible Goods', 'memberpress'); ?></option>
      <option value="txcd_20030000" <?php selected($tax_code, 'txcd_20030000'); ?>><?php esc_html_e('General - Services', 'memberpress'); ?></option>
      <option value="custom" <?php selected($tax_code, 'custom'); ?>><?php esc_html_e('Custom', 'memberpress'); ?></option>
    </select>
  </label>
  <input type="text" id="_mepr_tax_stripe_tax_code_custom" name="_mepr_tax_stripe_tax_code_custom" placeholder="<?php esc_attr_e('Enter custom tax code', 'memberpress'); ?>" value="<?php echo esc_attr($tax_code_custom); ?>">
  <?php
    MeprAppHelper::info_tooltip(
        'membership-stripe-tax-code',
        __('Stripe Tax Category', 'memberpress'),
        sprintf(
        // Translators: %1$s: br tag, %2$s: open strong tag, %3$s: close strong tag, %4$s: open link tag, %5$s: close link tag.
            __('Set the Stripe tax category for this membership.%1$s%1$sThe %2$sDefault tax category%3$s will use the default tax category from the %4$sStripe tax settings%5$s.%1$s%1$sSelect %2$sCustom%3$s to be able to enter a %6$scustom tax category%5$s.', 'memberpress'),
            '<br>',
            '<strong>',
            '</strong>',
            '<a href="https://dashboard.stripe.com/settings/tax" target="_blank">',
            '</a>',
            '<a href="https://stripe.com/docs/tax/tax-categories" target="_blank">'
        )
    );
    ?>
</div>
