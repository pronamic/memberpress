<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div class="info_block">
  <span class="info_block_title"><?php _e('Active Members', 'memberpress'); ?></span>
  <h3><?php echo MeprReports::get_active_members_count(); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php _e('Inactive Members', 'memberpress'); ?></span>
  <h3><?php echo MeprReports::get_inactive_members_count(); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php _e('Total Members', 'memberpress'); ?></span>
  <h3><?php echo MeprReports::get_total_members_count(); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php _e('Total WP Users', 'memberpress'); ?></span>
  <h3><?php echo MeprReports::get_total_wp_users_count(); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php _e('Active Free Members', 'memberpress'); ?></span>
  <h3><?php echo MeprReports::get_free_active_members_count(); ?></h3>
</div>

<div class="info_block">
  <span class="info_block_title"><?php _e('Active Paid Members', 'memberpress'); ?></span>
  <h3><?php echo MeprReports::get_paid_active_members_count(); ?></h3>
</div>
<div class="info_block">
  <span class="info_block_title"><?php _e('Avg Mbr Lifetime Val', 'memberpress'); ?></span>
  <h3><?php echo MeprAppHelper::format_currency(MeprReports::get_average_lifetime_value(), true, false); ?></h3>
</div>
<?php
/*
 *  These are slowing things down too much for now
    <div class="info_block">
    <span class="info_block_title"><?php _e('Avg Num Mbr Pmts', 'memberpress'); ?></span>
    <h3><?php echo MeprUtils::format_float(MeprReports::get_average_payments_per_member()); ?></h3>
    </div>

    <div class="info_block">
    <span class="info_block_title"><?php _e('% Members Rebill', 'memberpress'); ?></span>
    <h3><?php echo MeprUtils::format_float(MeprReports::get_percentage_members_who_rebill()) . '%'; ?></h3>
    </div>
 */

?>