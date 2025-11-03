<?php if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

  $mepr_coupon_code = $coupon && isset($coupon->ID) ? $coupon->post_title : '';

if ($mepr_coupon_code || ( is_object($tmpsub) && $tmpsub->prorated_trial )) {
    unset($sub_price_str);
}
?>

<div class="mp_wrapper mp_invoice">
  <?php if (isset($sub_price_str)) : ?>
  <div class="mp_price_str">
    <strong><?php echo esc_html_x('Terms:', 'ui', 'memberpress'); ?></strong> <?php echo esc_html($sub_price_str); ?>
  </div>
  <div class="mp-spacer">&nbsp;</div>
  <?php endif; ?>
  <div class="mp-table">

    <ul class="mp-cart-body">
      <?php foreach ($invoice['items'] as $item) : ?>
      <li class="mp-cart-item">
        <div class="mp-cart-item-image">
          <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/checkout/product.png'); ?>" alt="" role="presentation" />
        </div>
        <div class="mp-cart-item-details">
          <p><?php echo esc_html(str_replace(MeprProductsHelper::renewal_str($prd), '', $item['description'])); ?></p>
            <?php if (isset($txn, $sub) && !$txn->is_one_time_payment() && $sub instanceof MeprSubscription && $sub->id > 0) : ?>
            <p class="desc"><?php echo esc_html(MeprAppHelper::format_price_string($sub, $sub->price, true, $mepr_coupon_code)); ?></p>
            <?php elseif (!(isset($txn) && $txn->txn_type === 'sub_account')) : ?>
            <p class="desc"><?php MeprProductsHelper::display_invoice($prd, $mepr_coupon_code); ?></p>
            <?php endif; ?>
        </div>
            <?php if ($show_quantity) : ?>
        <div class="mp-cart-item-quantity"><?php echo esc_html($item['quantity']); ?></div>
            <?php endif; ?>
        <div class="mp-currency-cell"><?php echo esc_html(MeprAppHelper::format_currency($item['amount'], true, false)); ?></div>
      </li>
      <?php endforeach; ?>
      <?php if (isset($invoice['coupon']) && ! empty($invoice['coupon']) && (int) $invoice['coupon']['id'] !== 0) : ?>
      <div class="mp-cart-coupon">
        <div class="mp-cart-coupon-desc">
            <?php echo esc_html($invoice['coupon']['desc']); ?>
        </div>
        <div class="mp-currency-cell">
            <?php if ('0' !== $invoice['coupon']['amount']) : ?>
                <?php echo esc_html(MeprAppHelper::format_currency(MeprCouponsHelper::format_coupon_amount($invoice['coupon']['amount']), true, false)); ?>
            <?php else : ?>
            &nbsp;
            <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </ul>
    <div class="mp-cart-footer">
      <?php if ($invoice['tax']['amount'] > 0.00 || $invoice['tax']['percent'] > 0) : ?>
      <div class="mp-cart-subtotal">
        <div class="bb"><?php echo esc_html_x('Sub-Total', 'ui', 'memberpress'); ?></div>
        <div class="mp-currency-cell bb"><?php echo esc_html(MeprAppHelper::format_currency($subtotal, true, false)); ?></div>
      </div>
      <div>
        <div class="mepr-tax-invoice">
            <?php echo esc_html(MeprUtils::format_tax_percent_for_display($invoice['tax']['percent']) . '% ' . $invoice['tax']['type']); ?>
        </div>
        <div class="mp-currency-cell">
            <?php echo esc_html(MeprAppHelper::format_currency($invoice['tax']['amount'], true, false)); ?></div>
        </div>
      <?php endif; ?>
      <div>
        <div class="bt"><?php echo esc_html_x('Total', 'ui', 'memberpress'); ?></div>
        <div class="mp-currency-cell bt total_cell"><?php echo esc_html(MeprAppHelper::format_currency($total, true, false)); ?></div>
        <input type="hidden" name="mepr_stripe_txn_amount"
          value="<?php echo esc_attr(MeprUtils::format_stripe_currency($total)); ?>" />
      </div>
    </div>
  </div>
</div>
