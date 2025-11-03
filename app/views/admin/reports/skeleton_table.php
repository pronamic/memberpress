<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<table class="widefat" style="margin-top:25px;">
  <thead>
    <tr>
      <th width="15.11%"><?php esc_html_e('Date', 'memberpress'); ?></th>
      <th width="11.11%"><?php esc_html_e('Pending', 'memberpress'); ?></th>
      <th width="11.11%"><?php esc_html_e('Failed', 'memberpress'); ?></th>
      <th width="11.11%"><?php esc_html_e('Complete', 'memberpress'); ?></th>
      <th width="11.11%"><?php esc_html_e('Refunded', 'memberpress'); ?></th>
      <th width="11.11%"><?php esc_html_e('Collected', 'memberpress'); ?></th>
      <th width="11.11%"><?php esc_html_e('Refunded', 'memberpress'); ?></th>
      <th width="6.11%"><?php esc_html_e('Tax', 'memberpress'); ?></th>
      <th width="11.11%"><?php esc_html_e('Net Total', 'memberpress'); ?></th>
    </tr>
  </thead>
  <tbody>
      <tr class="mepr-table-loading-row">
        <td colspan="9">
          <?php MeprView::render('/admin/reports/svg_loader'); ?>
        </td>
      </tr>
    </tbody>
</table>
