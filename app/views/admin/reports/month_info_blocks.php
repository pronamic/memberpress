<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div class="info_block">
  <span class="info_block_title"><?php esc_html_e('Pending Transactions', 'memberpress'); ?></span>
  <h3><?php echo esc_html(MeprReports::get_transactions_count(MeprTransaction::$pending_str, false, $curr_month, $curr_year, $curr_product)); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php esc_html_e('Failed Transactions', 'memberpress'); ?></span>
  <h3><?php echo esc_html(MeprReports::get_transactions_count(MeprTransaction::$failed_str, false, $curr_month, $curr_year, $curr_product)); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php esc_html_e('Refunded Transactions', 'memberpress'); ?></span>
  <h3><?php echo esc_html(MeprReports::get_transactions_count(MeprTransaction::$refunded_str, false, $curr_month, $curr_year, $curr_product)); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php esc_html_e('Completed Transactions', 'memberpress'); ?></span>
  <h3><?php echo esc_html(MeprReports::get_transactions_count(MeprTransaction::$complete_str, false, $curr_month, $curr_year, $curr_product)); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php esc_html_e('Amount Collected', 'memberpress'); ?></span>
  <h3><?php echo esc_html(MeprAppHelper::format_currency(MeprReports::get_collected($curr_month, false, $curr_year, $curr_product), true, false)); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php esc_html_e('Amount Refunded', 'memberpress'); ?></span>
  <h3><?php echo esc_html(MeprAppHelper::format_currency(MeprReports::get_refunds($curr_month, false, $curr_year, $curr_product), true, false)); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php esc_html_e('Taxes Collected', 'memberpress'); ?></span>
  <h3><?php echo esc_html(MeprAppHelper::format_currency(MeprReports::get_taxes($curr_month, false, $curr_year, $curr_product), true, false)); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php esc_html_e('Total Net Income', 'memberpress'); ?></span>
  <h3><?php echo esc_html(MeprAppHelper::format_currency(MeprReports::get_revenue($curr_month, false, $curr_year, $curr_product), true, false)); ?></h3>
</div>

