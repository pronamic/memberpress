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
    <?php
    $records   = MeprReports::get_monthly_dataset('transactions', $curr_month, $curr_year, $curr_product);
    $p_total   = $f_total = $c_total = $r_total = $rev_total = $ref_total = $tax_total = 0;
    $row_index = 0;

    $revenue_dataset = MeprReports::get_revenue_dataset($curr_month, $curr_year, $curr_product);
    $taxes_dataset   = MeprReports::get_taxes_dataset($curr_month, $curr_year, $curr_product);
    $refunds_dataset = MeprReports::get_refunds_dataset($curr_month, $curr_year, $curr_product);

    foreach ($records as $r) {
        $revenue = isset($revenue_dataset[$r->day]) ? (float) $revenue_dataset[$r->day] : 0.00;
        $taxes   = isset($taxes_dataset[$r->day]) ? (float) $taxes_dataset[$r->day] : 0.00;
        $refunds = isset($refunds_dataset[$r->day]) ? (float) $refunds_dataset[$r->day] : 0.00;

        $all       = (float)($revenue + $refunds + $taxes);
        $alternate = ( $row_index++ % 2 ? '' : 'alternate' );
        ?>
      <tr class="<?php echo $alternate; ?>">
        <td>
          <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=memberpress-trans&membership=' . $curr_product . '&month=' . $curr_month . '&day=' . $r->day . '&year=' . $curr_year), 'customize_transactions', 'mepr_transactions_nonce'); ?>">
            <?php echo MeprReports::make_table_date($curr_month, $r->day, $curr_year, get_option('date_format')); ?>
          </a>
        </td>
        <td>
          <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=memberpress-trans&membership=' . $curr_product . '&month=' . $curr_month . '&day=' . $r->day . '&year=' . $curr_year . '&status=pending'), 'customize_transactions', 'mepr_transactions_nonce'); ?>">
            <?php echo $r->p;
            $p_total += $r->p; ?>
          </a>
        </td>
        <td>
          <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=memberpress-trans&membership=' . $curr_product . '&month=' . $curr_month . '&day=' . $r->day . '&year=' . $curr_year . '&status=failed'), 'customize_transactions', 'mepr_transactions_nonce'); ?>">
            <?php echo $r->f;
            $f_total += $r->f; ?>
          </a>
        </td>
        <td>
          <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=memberpress-trans&membership=' . $curr_product . '&month=' . $curr_month . '&day=' . $r->day . '&year=' . $curr_year . '&status=complete'), 'customize_transactions', 'mepr_transactions_nonce'); ?>">
            <?php echo $r->c;
            $c_total += $r->c; ?>
          </a>
        </td>
        <td>
          <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=memberpress-trans&membership=' . $curr_product . '&month=' . $curr_month . '&day=' . $r->day . '&year=' . $curr_year . '&status=refunded'), 'customize_transactions', 'mepr_transactions_nonce'); ?>">
            <?php echo $r->r;
            $r_total += $r->r; ?>
          </a>
        </td>
        <td <?php if (!empty($all)) {
            echo 'style="color:green;font-weight:bold;"';
            } ?>><?php echo MeprAppHelper::format_currency(($all), true, false);
$rev_total += $revenue; ?></td>
        <td <?php if (!empty($refunds)) {
            echo 'style="color:red;font-weight:bold;"';
            } ?>><?php echo MeprAppHelper::format_currency($refunds, true, false);
$ref_total += $refunds; ?></td>
        <td <?php if (!empty($taxes)) {
            echo 'style="color:orange;font-weight:bold;"';
            } ?>><?php echo MeprAppHelper::format_currency($taxes, true, false);
$tax_total += $taxes; ?></td>
        <td <?php if (!empty($revenue)) {
            echo 'style="color:navy;font-weight:bold;"';
            } ?>><?php echo MeprAppHelper::format_currency($revenue, true, false); ?></td>
      </tr>
        <?php
    }
    $all_total = (float)($rev_total + $ref_total + $tax_total);
    ?>
    </tbody>
    <tfoot>
      <tr>
        <th><?php _e('Totals', 'memberpress'); ?></th>
        <th><?php echo $p_total; ?></th>
        <th><?php echo $f_total; ?></th>
        <th><?php echo $c_total; ?></th>
        <th><?php echo $r_total; ?></th>
        <th <?php if (!empty($all_total)) {
            echo 'style="color:green;font-weight:bold;"';
            } ?>><?php echo MeprAppHelper::format_currency($all_total, true, false); ?></th>
        <th <?php if (!empty($ref_total)) {
            echo 'style="color:red;font-weight:bold;"';
            } ?>><?php echo MeprAppHelper::format_currency($ref_total, true, false); ?></th>
        <th <?php if (!empty($tax_total)) {
            echo 'style="color:orange;font-weight:bold;"';
            } ?>><?php echo MeprAppHelper::format_currency($tax_total, true, false); ?></th>
        <th <?php if (!empty($rev_total)) {
            echo 'style="color:navy;font-weight:bold;"';
            } ?>><?php echo MeprAppHelper::format_currency($rev_total, true, false); ?></th>
      </tr>
  </tfoot>
</table>
<div>&nbsp;</div>
<div>
  <a class="button" href="<?php
    echo MeprUtils::admin_url(
        'admin-ajax.php', // $path
        ['export_report','mepr_reports_nonce'], // $nonce
        [ // $add_params
            'action' => 'mepr_export_report',
            'export' => 'monthly',
        ],
        true, // $include_query_string
        ['page','main-view'] // $exclude_params
    ); ?>"><?php _e('Export as CSV', 'memberpress'); ?></a>
  <?php MeprHooks::do_action('mepr-report-footer', 'monthly'); ?>
</div>

