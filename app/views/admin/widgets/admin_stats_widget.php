<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div id="widget-info-blocks">
  <img src="<?php echo esc_url(MEPR_BRAND_URL . '/images/logo.svg'); ?>" id="mepr-stats-logo" />
  <p><?php _e('Your 7-Day membership activity:', 'memberpress'); ?></span></p>

  <div class="widget_info_block">
    <span class="info_block_title"><?php _e('Pending Transactions', 'memberpress'); ?></span>
    <h4><?php echo $pending_transactions; ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php _e('Failed Transactions', 'memberpress'); ?></span>
    <h4><?php echo $failed_transactions; ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php _e('Refunded Transactions', 'memberpress'); ?></span>
    <h4><?php echo $refunded_transactions; ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php _e('Completed Transactions', 'memberpress'); ?></span>
    <h4><?php echo $completed_transactions; ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php _e('Amount Collected', 'memberpress'); ?></span>
    <h4><?php echo MeprAppHelper::format_currency(($revenue + $refunds), true, false); ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php _e('Amount Refunded', 'memberpress'); ?></span>
    <h4><?php echo MeprAppHelper::format_currency($refunds, true, false); ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php _e('Total Income', 'memberpress'); ?></span>
    <h4><?php echo MeprAppHelper::format_currency($revenue, true, false); ?></h4>
  </div>
</div>

<div style="clear:both;height:10px;"></div>

<div id="mepr-widget-report">
  <?php MeprView::render('/admin/reports/svg_loader'); ?>
</div>

<div class="alignright">
  <a href="<?php
          echo MeprUtils::admin_url(
              'admin-ajax.php',
              ['export_report','mepr_reports_nonce'],
              [
                  'action' => 'mepr_export_report',
                  'export' => 'widget',
              ]
          );
            ?>"><?php _e('Export as CSV', 'memberpress'); ?></a>
</div>

<div>
  <a href="<?php echo admin_url('admin.php?page=memberpress-reports'); ?>" class="button"><?php _e('View More MemberPress Reports', 'memberpress'); ?></a>
</div>

<!-- Widget JS Helpers -->
<div id="mepr-widget-currency-symbol" data-value="<?php echo $mepr_options->currency_symbol; ?>"></div>
