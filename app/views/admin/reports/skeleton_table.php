<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<table class="widefat" style="margin-top:25px;">
  <thead>
    <tr>
      <th width="15.11%"><?php _e('Date', 'memberpress'); ?></th>
      <th width="11.11%"><?php _e('Pending', 'memberpress'); ?></th>
      <th width="11.11%"><?php _e('Failed', 'memberpress'); ?></th>
      <th width="11.11%"><?php _e('Complete', 'memberpress'); ?></th>
      <th width="11.11%"><?php _e('Refunded', 'memberpress'); ?></th>
      <th width="11.11%"><?php _e('Collected', 'memberpress'); ?></th>
      <th width="11.11%"><?php _e('Refunded', 'memberpress'); ?></th>
      <th width="6.11%"><?php _e('Tax', 'memberpress'); ?></th>
      <th width="11.11%"><?php _e('Net Total', 'memberpress'); ?></th>
    </tr>
  </thead>
  <tbody>
      <tr class="mepr-table-loading-row">
        <td colspan="9">
          <?php echo MeprView::get_string('/admin/reports/svg_loader'); ?>
        </td>
      </tr>
    </tbody>
</table>