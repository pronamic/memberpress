<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div class="mp_wrapper mp_invoice">
  <?php if (isset($sub_price_str)) : ?>
  <div class="mp_price_str">
    <strong><?php _ex('Terms:', 'ui', 'memberpress'); ?></strong> <?php echo $sub_price_str; ?>
  </div>
  <div class="mp-spacer">&nbsp;</div>
  <?php endif; ?>
  <table class="mp-table">
    <thead>
      <tr>
        <th><?php _ex('Description', 'ui', 'memberpress'); ?></th>
        <?php if ($show_quantity) : ?>
          <th><?php _ex('Quantity', 'ui', 'memberpress'); ?></th>
        <?php endif; ?>
        <th><?php _ex('Amount', 'ui', 'memberpress'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($invoice['items'] as $item) : ?>
        <tr>
          <td><?php echo $item['description']; ?></td>
            <?php if ($show_quantity) : ?>
            <td><?php echo $item['quantity']; ?></td>
            <?php endif; ?>
          <td class="mp-currency-cell"><?php echo MeprAppHelper::format_currency($item['amount'], true, false); ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (isset($invoice['coupon']) && !empty($invoice['coupon']) && $invoice['coupon']['id'] != 0) : ?>
        <tr>
          <td><?php echo $invoice['coupon']['desc']; ?></td>
            <?php if ($show_quantity) : ?>
            <td>&nbsp;</td>
            <?php endif; ?>
          <td class="mp-currency-cell"><?php echo MeprAppHelper::format_currency(MeprCouponsHelper::format_coupon_amount($invoice['coupon']['amount']), true, false); ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <?php if (is_array($invoice['tax_items']) && count($invoice['tax_items'])) : ?>
        <tr>
            <?php if ($show_quantity) : ?>
            <th>&nbsp;</th>
            <?php endif; ?>
          <th><?php _ex('Sub-Total', 'ui', 'memberpress'); ?></th>
          <th class="mp-currency-cell"><?php echo MeprAppHelper::format_currency($subtotal, true, false); ?></th>
        </tr>
            <?php foreach ($invoice['tax_items'] as $tax_item) : ?>
                <?php if ($tax_item['amount'] > 0 || $tax_item['percent'] > 0) : ?>
            <tr>
                    <?php if ($show_quantity) : ?>
                <th>&nbsp;</th>
                    <?php endif; ?>
              <th class="mepr-tax-invoice"><?php echo MeprUtils::format_tax_percent_for_display($tax_item['percent']) . '% ' . $tax_item['type']; ?> <br /><small><?php echo esc_html($tax_item['post_title']); ?></small></th>
              <th class="mp-currency-cell"><?php echo MeprAppHelper::format_currency($tax_item['amount'], true, false); ?></th>
            </tr>
                <?php endif; ?>
            <?php endforeach; ?>
      <?php endif; ?>
      <tr>
        <?php if ($show_quantity) : ?>
          <th>&nbsp;</th>
        <?php endif; ?>
        <th><?php _ex('Total', 'ui', 'memberpress'); ?></th>
        <th class="mp-currency-cell"><?php echo MeprAppHelper::format_currency($total, true, false); ?></th>
      </tr>
    </tfoot>
  </table>
</div>
