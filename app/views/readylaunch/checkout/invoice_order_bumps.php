<?php if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

  $mepr_coupon_code = $coupon && isset($coupon->ID) ? $coupon->post_title : '';
  // $coupon_code = '';
  // if($coupon && isset($coupon->ID)) {
  // $coupon_code = $coupon->post_title;
  // }
?>

<div class="mp_wrapper mp_invoice">
  <?php if (isset($sub_price_str)) : ?>
  <div class="mp_price_str">
    <strong><?php echo esc_html_x('Terms:', 'ui', 'memberpress'); ?></strong> <?php echo esc_html($sub_price_str); ?>
  </div>
  <div class="mp-spacer">&nbsp;</div>
  <?php endif; ?>
  <table class="mp-table">

    <tbody>
      <?php foreach ($invoice['items'] as $item_index => $item) : ?>
      <tr>
        <td>
          <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/checkout/product.png'); ?>" alt="" role="presentation" />
        </td>
        <td>
          <p>
            <?php
                echo wp_kses(
                    str_replace(
                        MeprProductsHelper::renewal_str($prd),
                        '',
                        $item['description']
                    ),
                    [
                        'br' => [],
                        'small' => [],
                    ]
                );
            ?>
          </p>
            <?php if ($item_index === 0) : ?>
                <?php if (isset($txn, $sub) && !$txn->is_one_time_payment() && $sub instanceof MeprSubscription && $sub->id > 0) : ?>
              <p class="desc"><?php echo esc_html(MeprAppHelper::format_price_string($sub, $sub->price, true, $mepr_coupon_code)); ?></p>
                <?php elseif (!(isset($txn) && $txn->txn_type === 'sub_account')) : ?>
              <p class="desc"><?php MeprProductsHelper::display_invoice($prd, $mepr_coupon_code); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </td>
            <?php if ($show_quantity) : ?>
        <td><?php echo esc_html($item['quantity']); ?></td>
            <?php endif; ?>
        <td class="mp-currency-cell"><?php echo esc_html(MeprAppHelper::format_currency($item['amount'], true, false)); ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (isset($invoice['coupon']) && ! empty($invoice['coupon']) && (int) $invoice['coupon']['id'] !== 0) : ?>
      <tr>
        <td></td>
        <td>
            <?php echo esc_html($invoice['coupon']['desc']); ?>
        </td>
            <?php if ($show_quantity) : ?>
        <td>&nbsp;</td>
            <?php endif; ?>
        <td class="mp-currency-cell">
          -<?php echo esc_html(MeprAppHelper::format_currency($invoice['coupon']['amount'], true, false)); ?></td>
      </tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <?php if (is_array($invoice['tax_items']) && count($invoice['tax_items'])) : ?>
      <tr>
        <th></th>
            <?php if ($show_quantity) : ?>
        <th>&nbsp;</th>
            <?php endif; ?>
        <th class="bb"><?php echo esc_html_x('Sub-Total', 'ui', 'memberpress'); ?></th>
        <th class="mp-currency-cell bb"><?php echo esc_html(MeprAppHelper::format_currency($subtotal, true, false)); ?></th>
      </tr>
            <?php foreach ($invoice['tax_items'] as $tax_item) : ?>
                <?php if ($tax_item['amount'] > 0 || $tax_item['percent'] > 0) : ?>
          <tr>
            <th></th>
                    <?php if ($show_quantity) : ?>
            <th>&nbsp;</th>
                    <?php endif; ?>
            <th class="mepr-tax-invoice">
                    <?php echo esc_html(MeprUtils::format_tax_percent_for_display($tax_item['percent']) . '% ' . $tax_item['type']); ?>
              <br /><small><?php echo esc_html($tax_item['post_title']); ?></small>
            </th>
            <th class="mp-currency-cell">
                    <?php echo esc_html(MeprAppHelper::format_currency($tax_item['amount'], true, false)); ?></th>
          </tr>
                <?php endif; ?>
            <?php endforeach; ?>
      <?php endif; ?>
      <tr>
        <td></td>
        <?php if ($show_quantity) : ?>
        <th>&nbsp;</th>
        <?php endif; ?>
        <th class="bt"><?php echo esc_html_x('Total', 'ui', 'memberpress'); ?></th>
        <th class="mp-currency-cell bt total_cell"><?php echo esc_html(MeprAppHelper::format_currency($total, true, false)); ?>
        </th>
        <input type="hidden" name="mepr_stripe_txn_amount"
          value="<?php echo esc_attr(MeprUtils::format_stripe_currency($total)); ?>" />
      </tr>
    </tfoot>
  </table>
</div>
