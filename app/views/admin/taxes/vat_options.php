<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<?php // Business VAT Country. ?>
<tr valign="top">
  <th scope="row">
    <label for="mepr_vat_enabled"><?php _e('Enable VAT', 'memberpress'); ?></label>
    <?php MeprAppHelper::info_tooltip(
        'mepr-enable-vat-tax',
        __('Enable VAT Tax Calculations', 'memberpress'),
        __('When this is checked then MemberPress will calculate VAT taxes for any customers who are located in the European Union.', 'memberpress')
    ); ?>
  </th>
  <td>
    <input type="checkbox" id="mepr_vat_enabled" name="mepr_vat_enabled" class="mepr-toggle-checkbox" data-box="mepr_vat_tax_box" value="<?php echo $vat_enabled; ?>" <?php checked($vat_enabled); ?> />
  </td>
</tr>
<tr valign="top">
  <td colspan="2" class="mepr-sub-box-wrapper">
    <div id="mepr_vat_tax_box" class="mepr-sub-box mepr_vat_tax_box">
      <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
      <table class="form-table">
        <tbody>
          <tr valign="top" id="mepr_vat_country_section">
            <th scope="row">
              <label for="mepr_vat_country"><?php _e('Merchant VAT Country', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip(
                  'mepr-merchant-vat-country',
                  __('Merchant VAT Country', 'memberpress'),
                  __('This is the country of the VAT MOSS your business is registered with.', 'memberpress')
              ); ?>
            </th>
            <td>
              <select name="mepr_vat_country">
                <?php foreach ($countries as $c => $d) : ?>
                  <option value="<?php echo $c; ?>" <?php selected($vat_country, $c); ?>><?php echo $d['name']; ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr valign="top" id="mepr_vat_tax_businesses_section">
            <th scope="row">
              <label for="mepr_vat_tax_businesses"><?php _e('Tax all EU Businesses', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip(
                  'mepr-merchant-vat-tax-businesses',
                  __('Tax all EU Businesses', 'memberpress'),
                  __('When this option is checked all EU Businesses will be taxed--even if they have a valid VAT number.', 'memberpress')
              ); ?>
            </th>
            <td>
              <input type="checkbox" name="mepr_vat_tax_businesses" <?php checked($vat_tax_businesses); ?> />
            </td>
          </tr>
          <tr valign="top" id="mepr_charge_business_customer_net_price_section">
            <th scope="row">
              <label for="mepr_charge_business_customer_net_price"><?php _e('Remove tax from valid Businesses', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip(
                  'mepr-charge-business-customer-net-price',
                  __('Remove tax from valid Businesses', 'memberpress'),
                  __('When enabled, and prices are "inclusive" of tax, then valid EU Businesses will have their tax removed and pay only the NET price of the membership. Invoice will show 0 tax by default.', 'memberpress')
              ); ?>
            </th>
            <td>
              <input type="checkbox" name="mepr_charge_business_customer_net_price" <?php checked($charge_business_customer_net_price); ?> />
            </td>
          </tr>
          <tr valign="top" id="mepr_show_negative_tax_on_invoice_section"<?php echo ($charge_business_customer_net_price != 1) ? " style='display:none;'" : ''; ?>>
            <th scope="row">
              <label for="mepr_show_negative_tax_on_invoice"><?php _e('Show negative tax on Invoice', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip(
                  'mepr-show-negative-tax-on-invoice',
                  __('Show negative tax on Invoice', 'memberpress'),
                  __('When enabled the tax which is removed for valid EU Businesses will show as a negative amount on the invoice.', 'memberpress')
              ); ?>
            </th>
            <td>
              <input type="checkbox" name="mepr_show_negative_tax_on_invoice" <?php checked($show_negative_tax_on_invoice); ?> />
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="mepr_vat_disable_vies_service"><?php _e('Disable VAT VIES database lookup', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip(
                  'mepr-merchant-vat-disable-vie-service',
                  __('Disable VAT VIES database lookup', 'memberpress'),
                  __('When this option is checked, the VAT number will not be validated by VIES online service.', 'memberpress')
              ); ?>
            </th>
            <td>
              <input type="checkbox" name="mepr_vat_disable_vies_service" <?php checked($vat_disable_vies_service); ?> />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </td>
</tr>
<?php
