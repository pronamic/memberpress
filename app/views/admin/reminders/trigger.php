<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<input type="text" size="2" name="<?php echo esc_attr(MeprReminder::$trigger_length_str); ?>" id="<?php echo esc_attr(MeprReminder::$trigger_length_str); ?>" value="<?php echo esc_attr($reminder->trigger_length); ?>" />
<select name="<?php echo esc_attr(MeprReminder::$trigger_interval_str); ?>" id="<?php echo esc_attr(MeprReminder::$trigger_interval_str); ?>">
  <option value="hours"<?php selected($reminder->trigger_interval, 'hours'); ?>><?php esc_html_e('hours', 'memberpress'); ?></option>
  <option value="days"<?php selected($reminder->trigger_interval, 'days'); ?>><?php esc_html_e('days', 'memberpress'); ?></option>
  <option value="weeks"<?php selected($reminder->trigger_interval, 'weeks'); ?>><?php esc_html_e('weeks', 'memberpress'); ?></option>
  <option value="months"<?php selected($reminder->trigger_interval, 'months'); ?>><?php esc_html_e('months', 'memberpress'); ?></option>
  <option value="years"<?php selected($reminder->trigger_interval, 'years'); ?>><?php esc_html_e('years', 'memberpress'); ?></option>
</select>
<?php $trigger = "{$reminder->trigger_timing}_{$reminder->trigger_event}"; ?>
<select id="trigger">
  <option value="after_member-signup" <?php selected($trigger, 'after_member-signup'); ?>><?php esc_html_e('after Member Signs Up', 'memberpress'); ?></option>
  <option value="after_signup-abandoned" <?php selected($trigger, 'after_signup-abandoned'); ?>><?php esc_html_e('after Signup Abandoned', 'memberpress'); ?></option>
  <option value="before_sub-expires" <?php selected($trigger, 'before_sub-expires'); ?>><?php esc_html_e('before Subscription Expires', 'memberpress'); ?></option>
  <option value="after_sub-expires" <?php selected($trigger, 'after_sub-expires'); ?>><?php esc_html_e('after Subscription Expires', 'memberpress'); ?></option>
  <option value="before_sub-renews" <?php selected($trigger, 'before_sub-renews'); ?>><?php esc_html_e('before Subscription Renews', 'memberpress'); ?></option>
  <option value="after_sub-renews" <?php selected($trigger, 'after_sub-renews'); ?>><?php esc_html_e('after Subscription Renews', 'memberpress'); ?></option>
  <option value="before_sub-trial-ends" <?php selected($trigger, 'before_sub-trial-ends'); ?>><?php esc_html_e('before Subscription Trial Ends', 'memberpress'); ?></option>
  <option value="before_cc-expires" <?php selected($trigger, 'before_cc-expires'); ?>><?php esc_html_e('before Credit Card Expires', 'memberpress'); ?></option>
  <option value="after_cc-expires" <?php selected($trigger, 'after_cc-expires'); ?>><?php esc_html_e('after Credit Card Expires', 'memberpress'); ?></option>
  <?php MeprHooks::do_action('mepr_reminder_trigger_option', $trigger); ?>
</select>
<input type="hidden" name="<?php echo esc_attr(MeprReminder::$trigger_timing_str); ?>" id="<?php echo esc_attr(MeprReminder::$trigger_timing_str); ?>" value="<?php echo esc_attr($reminder->trigger_timing); ?>" />
<input type="hidden" name="<?php echo esc_attr(MeprReminder::$trigger_event_str); ?>" id="<?php echo esc_attr(MeprReminder::$trigger_event_str); ?>" value="<?php echo esc_attr($reminder->trigger_event); ?>" />
<input type="hidden" name="<?php echo esc_attr(MeprReminder::$nonce_str); ?>" value="<?php echo esc_attr($nonce); ?>" />

