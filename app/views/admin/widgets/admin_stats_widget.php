<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div id="widget-info-blocks">
  <img src="<?php echo esc_url(MEPR_BRAND_URL . '/images/logo.svg'); ?>" id="mepr-stats-logo" />
  <p><?php esc_html_e('Your 7-Day membership activity:', 'memberpress'); ?></span></p>

  <div class="widget_info_block">
    <span class="info_block_title"><?php esc_html_e('Pending Transactions', 'memberpress'); ?></span>
    <h4><?php echo esc_html($pending_transactions); ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php esc_html_e('Failed Transactions', 'memberpress'); ?></span>
    <h4><?php echo esc_html($failed_transactions); ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php esc_html_e('Refunded Transactions', 'memberpress'); ?></span>
    <h4><?php echo esc_html($refunded_transactions); ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php esc_html_e('Completed Transactions', 'memberpress'); ?></span>
    <h4><?php echo esc_html($completed_transactions); ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php esc_html_e('Amount Collected', 'memberpress'); ?></span>
    <h4><?php echo esc_html(MeprAppHelper::format_currency(($revenue + $refunds), true, false)); ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php esc_html_e('Amount Refunded', 'memberpress'); ?></span>
    <h4><?php echo esc_html(MeprAppHelper::format_currency($refunds, true, false)); ?></h4>
  </div>

  <div class="widget_info_block">
    <span class="info_block_title"><?php esc_html_e('Total Income', 'memberpress'); ?></span>
    <h4><?php echo esc_html(MeprAppHelper::format_currency($revenue, true, false)); ?></h4>
  </div>
</div>

<div style="clear:both;height:10px;"></div>

<div id="mepr-widget-report">
  <?php MeprView::render('/admin/reports/svg_loader'); ?>
</div>

<div class="alignright">
  <a href="<?php
          echo esc_url(MeprUtils::admin_url(
              'admin-ajax.php',
              ['export_report','mepr_reports_nonce'],
              [
                  'action' => 'mepr_export_report',
                  'export' => 'widget',
              ]
          ));
            ?>"><?php esc_html_e('Export as CSV', 'memberpress'); ?></a>
</div>

<div>
  <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-reports')); ?>" class="button"><?php esc_html_e('View More MemberPress Reports', 'memberpress'); ?></a>
</div>

<!-- Widget JS Helpers -->
<div id="mepr-widget-currency-symbol" data-value="<?php echo esc_attr($mepr_options->currency_symbol); ?>"></div>
