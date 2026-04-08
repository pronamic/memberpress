<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Template part for schedule date/time fields.
 *
 * @var string  $month_field    The name attribute for the month field.
 * @var string  $day_field      The name attribute for the day field.
 * @var string  $year_field     The name attribute for the year field.
 * @var string  $hour_field     The name attribute for the hour field.
 * @var string  $minute_field   The name attribute for the minute field.
 * @var string  $timezone_field The name attribute for the timezone field.
 * @var integer $date_ts        The date timestamp.
 * @var string  $timezone       The timezone.
 * @var boolean $begin          Whether this is a start date (true) or end date (false).
 */
$current_ts     = $date_ts > 0 ? $date_ts : 0;
$default_hour   = $begin ? 0 : 23;
$default_minute = $begin ? 0 : 59;
?>
<div class="mepr-schedule-date-fields">
  <div class="mepr-schedule-date-fields-row">
    <div class="mepr-schedule-date-fields__field">
        <span class="description"><small><?php echo esc_html(MeprUtils::period_type_name('months')); ?></small></span>
        <select name="<?php echo esc_attr($month_field); ?>">
            <?php MeprCouponsHelper::months_options($date_ts); ?>
        </select>
    </div>
    <div class="mepr-schedule-date-fields__field">
        <span class="description"><small><?php echo esc_html(MeprUtils::period_type_name('days')); ?></small></span>
        <input type="text" size="2" maxlength="2" name="<?php echo esc_attr($day_field); ?>" value="<?php echo esc_attr(MeprUtils::get_date_from_ts($current_ts, 'j')); ?>" />
        </div>
    <div class="mepr-schedule-date-fields__field">
        <span class="description"><small><?php echo esc_html(MeprUtils::period_type_name('years')); ?></small></span>
        <input type="text" size="4" maxlength="4" name="<?php echo esc_attr($year_field); ?>" value="<?php echo esc_attr(MeprUtils::get_date_from_ts($current_ts, 'Y')); ?>" />
    </div>
    <div class="mepr-schedule-date-fields__field">
        <span class="description"><small><?php echo esc_html(MeprUtils::time_type_name('hours')); ?></small></span>
        <input type="text" size="2" maxlength="2" name="<?php echo esc_attr($hour_field); ?>" value="<?php echo esc_attr($date_ts ? MeprUtils::get_date_from_ts($date_ts, 'G') : $default_hour); ?>" />
    </div>
    <div class="mepr-schedule-date-fields__field">
        <span class="description"><small><?php echo esc_html(MeprUtils::time_type_name('minutes')); ?></small></span>
        <input type="text" size="2" maxlength="2" name="<?php echo esc_attr($minute_field); ?>" value="<?php echo esc_attr($date_ts ? MeprUtils::get_date_from_ts($date_ts, 'i') : $default_minute); ?>" />
    </div>
  </div>
  <div class="mepr-schedule-date-fields-row">
    <div class="mepr-schedule-date-fields__field">
        <span class="description"><small><?php esc_html_e('Timezone', 'memberpress'); ?></small></span>
        <select name="<?php echo esc_attr($timezone_field); ?>" class="mepr-schedule-date-fields__timezone">
            <?php echo wp_timezone_choice($timezone); ?>
        </select>
    </div>
  </div>
</div>

