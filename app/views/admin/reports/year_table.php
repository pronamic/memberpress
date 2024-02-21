<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

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
    $records = MeprReports::get_yearly_dataset('transactions', $curr_year, $curr_product);
    $pTotal = $fTotal = $cTotal = $rTotal = $revTotal = $refTotal = $taxTotal = 0;
    $row_index = 0;

    $revenue_dataset = MeprReports::get_revenue_dataset(false, $curr_year, $curr_product);
    $taxes_dataset = MeprReports::get_taxes_dataset(false, $curr_year, $curr_product);
    $refunds_dataset = MeprReports::get_refunds_dataset(false, $curr_year, $curr_product);
    $collected_dataset = MeprReports::get_collected_dataset(false, $curr_year, $curr_product);

    foreach($records as $r) {
      $revenue = isset($revenue_dataset[$r->month]) ? (float) $revenue_dataset[$r->month] : 0.00;
      $taxes = isset($taxes_dataset[$r->month]) ? (float) $taxes_dataset[$r->month] : 0.00;
      $refunds = isset($refunds_dataset[$r->month]) ? (float) $refunds_dataset[$r->month] : 0.00;
      $collected = isset($collected_dataset[$r->month]) ? (float) $collected_dataset[$r->month] : 0.00;
      $all = (float)($revenue + $refunds + $taxes);
      $alternate = ( $row_index++ % 2 ? '' : 'alternate' );
      $r->day = '';
    ?>
      <tr class="<?php echo $alternate; ?>">
        <td>
          <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=memberpress-trans&membership='.$curr_product.'&month='.$r->month.'&day='.$r->day.'&year='.$curr_year), 'customize_transactions', 'mepr_transactions_nonce'); ?>">
            <?php echo MeprReports::make_table_date($r->month, 1, $curr_year, 'm/Y'); ?>
          </a>
        </td>
        <td>
          <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=memberpress-trans&membership='.$curr_product.'&month='.$r->month.'&day='.$r->day.'&year='.$curr_year.'&status=pending'), 'customize_transactions', 'mepr_transactions_nonce'); ?>">
            <?php echo $r->p; $pTotal += $r->p; ?>
          </a>
        </td>
        <td>
          <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=memberpress-trans&membership='.$curr_product.'&month='.$r->month.'&day='.$r->day.'&year='.$curr_year.'&status=failed'), 'customize_transactions', 'mepr_transactions_nonce'); ?>">
            <?php echo $r->f; $fTotal += $r->f; ?>
          </a>
        </td>
        <td>
          <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=memberpress-trans&membership='.$curr_product.'&month='.$r->month.'&day='.$r->day.'&year='.$curr_year.'&status=complete'), 'customize_transactions', 'mepr_transactions_nonce'); ?>">
            <?php echo $r->c; $cTotal += $r->c; ?>
          </a>
        </td>
        <td>
          <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=memberpress-trans&membership='.$curr_product.'&month='.$r->month.'&day='.$r->day.'&year='.$curr_year.'&status=refunded'), 'customize_transactions', 'mepr_transactions_nonce'); ?>">
            <?php echo $r->r; $rTotal += $r->r; ?>
          </a>
        </td>
        <td <?php if(!empty($all)) { echo 'style="color:green;font-weight:bold;"'; } ?>><?php echo MeprAppHelper::format_currency($all,true,false); $revTotal += $revenue; ?></td>
        <td <?php if(!empty($refunds)) { echo 'style="color:red;font-weight:bold;"'; } ?>><?php echo MeprAppHelper::format_currency($refunds,true,false); $refTotal += $refunds; ?></td>
        <td <?php if(!empty($taxes)) { echo 'style="color:orange;font-weight:bold;"'; } ?>><?php echo MeprAppHelper::format_currency($taxes,true,false); $taxTotal += $taxes; ?></td>
        <td <?php if(!empty($revenue)) { echo 'style="color:navy;font-weight:bold;"'; } ?>><?php echo MeprAppHelper::format_currency($revenue,true,false); ?></td>
      </tr>
    <?php
    }
    $allTotal = (float)($revTotal + $refTotal + $taxTotal);
    ?>
    </tbody>
    <tfoot>
      <tr>
        <th><?php _e('Totals', 'memberpress'); ?></th>
        <th><?php echo $pTotal; ?></th>
        <th><?php echo $fTotal; ?></th>
        <th><?php echo $cTotal; ?></th>
        <th><?php echo $rTotal; ?></th>
        <th <?php if(!empty($allTotal)) { echo 'style="color:green;font-weight:bold;"'; } ?>><?php echo MeprAppHelper::format_currency($allTotal,true,false); ?></th>
        <th <?php if(!empty($refTotal)) { echo 'style="color:red;font-weight:bold;"'; } ?>><?php echo MeprAppHelper::format_currency($refTotal,true,false); ?></th>
        <th <?php if(!empty($taxTotal)) { echo 'style="color:orange;font-weight:bold;"'; } ?>><?php echo MeprAppHelper::format_currency($taxTotal,true,false); ?></th>
        <th <?php if(!empty($revTotal)) { echo 'style="color:navy;font-weight:bold;"'; } ?>><?php echo MeprAppHelper::format_currency($revTotal,true,false); ?></th>
      </tr>
  </tfoot>
</table>
<div>&nbsp;</div>
<div>
  <a class="button" href="<?php
    echo MeprUtils::admin_url(
      "admin-ajax.php", // $path
      array('export_report','mepr_reports_nonce'), // $nonce
      array( // $add_params
        'action'=>'mepr_export_report',
        'export'=>'yearly'
      ),
      true, // $include_query_string
      array('page','month','main-view') // $exclude_params
    ); ?>"><?php _e('Export as CSV', 'memberpress'); ?></a>
  <?php MeprHooks::do_action('mepr-report-footer','yearly'); ?>
</div>

