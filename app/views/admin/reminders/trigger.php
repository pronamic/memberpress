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
<?php
$trigger_options = MeprHooks::apply_filters('mepr_reminder_trigger_options', [
    'after_member-signup'    => __('after Member Signs Up', 'memberpress'),
    'after_signup-abandoned' => __('after Signup Abandoned', 'memberpress'),
    'before_sub-expires'     => __('before Subscription Expires', 'memberpress'),
    'after_sub-expires'      => __('after Subscription Expires', 'memberpress'),
    'before_sub-renews'      => __('before Subscription Renews', 'memberpress'),
    'after_sub-renews'       => __('after Subscription Renews', 'memberpress'),
    'before_sub-trial-ends'  => __('before Subscription Trial Ends', 'memberpress'),
    'before_cc-expires'      => __('before Credit Card Expires', 'memberpress'),
    'after_cc-expires'       => __('after Credit Card Expires', 'memberpress'),
]);
?>
<select id="trigger">
  <?php foreach ($trigger_options as $value => $label) : ?>
    <option value="<?php echo esc_attr($value); ?>" <?php selected($trigger, $value); ?>><?php echo esc_html($label); ?></option>
  <?php endforeach; ?>
  <?php MeprHooks::do_action('mepr_reminder_trigger_option', $trigger); ?>
</select>
<input type="hidden" name="<?php echo esc_attr(MeprReminder::$trigger_timing_str); ?>" id="<?php echo esc_attr(MeprReminder::$trigger_timing_str); ?>" value="<?php echo esc_attr($reminder->trigger_timing); ?>" />
<input type="hidden" name="<?php echo esc_attr(MeprReminder::$trigger_event_str); ?>" id="<?php echo esc_attr(MeprReminder::$trigger_event_str); ?>" value="<?php echo esc_attr($reminder->trigger_event); ?>" />
<input type="hidden" name="<?php echo esc_attr(MeprReminder::$nonce_str); ?>" value="<?php echo esc_attr($nonce); ?>" />

