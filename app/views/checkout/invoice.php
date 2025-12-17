<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div class="mp_wrapper mp_invoice">
  <?php
    if (isset($_POST['errors']) && isset($_GET['action']) && $_GET['action'] === 'checkout') {
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $errors = array_map('sanitize_text_field', wp_unslash($_POST['errors']));
        MeprView::render('/shared/errors', get_defined_vars());
    }
    ?>
  <?php if (isset($sub_price_str)) : ?>
  <div class="mp_price_str">
    <strong><?php echo esc_html_x('Terms:', 'ui', 'memberpress'); ?></strong> <?php echo esc_html($sub_price_str); ?>
  </div>
  <div class="mp-spacer">&nbsp;</div>
  <?php endif; ?>
  <table class="mp-table">
    <thead>
      <tr>
        <th><?php echo esc_html_x('Description', 'ui', 'memberpress'); ?></th>
        <?php if ($show_quantity) : ?>
          <th><?php echo esc_html_x('Quantity', 'ui', 'memberpress'); ?></th>
        <?php endif; ?>
        <th class="mp-currency-cell"><?php echo esc_html_x('Amount', 'ui', 'memberpress'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($invoice['items'] as $item) : ?>
        <tr>
          <td style="width: auto !important;"><?php echo esc_html($item['description']); ?></td>
            <?php if ($show_quantity) : ?>
            <td><?php echo esc_html($item['quantity']); ?></td>
            <?php endif; ?>
          <td class="mp-currency-cell"><?php echo esc_html(MeprAppHelper::format_currency($item['amount'], true, false)); ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (isset($invoice['coupon']) && !empty($invoice['coupon']) && (int) $invoice['coupon']['id'] !== 0) : ?>
        <tr>
          <td><?php echo esc_html($invoice['coupon']['desc']); ?></td>
            <?php if ($show_quantity) : ?>
            <td>&nbsp;</td>
            <?php endif; ?>
          <td class="mp-currency-cell">
            <?php if ('0' !== $invoice['coupon']['amount']) : ?>
                <?php echo esc_html(MeprAppHelper::format_currency(MeprCouponsHelper::format_coupon_amount($invoice['coupon']['amount']), true, false)); ?>
            <?php else : ?>
              &nbsp;
            <?php endif; ?>
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <?php if ($invoice['tax']['amount'] > 0.00 || $invoice['tax']['percent'] > 0) : ?>
        <tr>
            <?php if ($show_quantity) : ?>
            <th>&nbsp;</th>
            <?php endif; ?>
          <th><?php echo esc_html_x('Sub-Total', 'ui', 'memberpress'); ?></th>
          <th class="mp-currency-cell"><?php echo esc_html(MeprAppHelper::format_currency($subtotal, true, false)); ?></th>
        </tr>
        <tr>
            <?php if ($show_quantity) : ?>
            <th>&nbsp;</th>
            <?php endif; ?>
          <th class="mepr-tax-invoice"><?php echo esc_html(MeprUtils::format_tax_percent_for_display($invoice['tax']['percent']) . '% ' . $invoice['tax']['type']); ?></th>
          <th class="mp-currency-cell"><?php echo esc_html(MeprAppHelper::format_currency($invoice['tax']['amount'], true, false)); ?></th>
        </tr>
      <?php endif; ?>
      <tr>
        <?php if ($show_quantity) : ?>
          <th>&nbsp;</th>
        <?php endif; ?>
        <th><?php echo esc_html_x('Total', 'ui', 'memberpress'); ?></th>
        <th class="mp-currency-cell"><?php echo esc_html(MeprAppHelper::format_currency($total, true, false)); ?></th>
      </tr>
    </tfoot>
  </table>
</div>
