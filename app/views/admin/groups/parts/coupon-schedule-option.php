<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Template part for a checkbox with date/time fields.
 *
 * @var string  $checkbox_field_name The full name attribute for the checkbox.
 * @var string  $label               The label text for the checkbox.
 * @var string  $box_id              The unique ID for the schedule box.
 * @var string  $box_class           Optional CSS class for the box (e.g., 'apply-box' or 'unapply-box').
 * @var boolean $checked             Whether the checkbox is checked.
 * @var string  $month_field         The name attribute for the month field.
 * @var string  $day_field           The name attribute for the day field.
 * @var string  $year_field          The name attribute for the year field.
 * @var string  $hour_field          The name attribute for the hour field.
 * @var string  $minute_field        The name attribute for the minute field.
 * @var string  $timezone_field      The name attribute for the timezone field.
 * @var integer $date_ts             The date timestamp.
 * @var string  $timezone            The timezone.
 * @var boolean $begin               Whether this is a start date (true) or end date (false).
 * @var string  $help                Optional help text to display below the date fields.
 */
?>
<label>
  <input type="checkbox" name="<?php echo esc_attr($checkbox_field_name); ?>" value="1" class="mepr-toggle-checkbox" data-box="<?php echo esc_attr($box_id); ?>" <?php checked($checked); ?> />
  <?php echo esc_html($label); ?>
</label>
<div id="<?php echo esc_attr($box_id); ?>" class="mepr-sub-box <?php echo esc_attr($box_class); ?><?php echo $checked ? '' : ' mepr-sub-box-hidden'; ?>">
  <div class="mepr-arrow mepr-white mepr-up mepr-sub-box-arrow"> </div>
    <?php
    MeprView::render(
        '/admin/schedule-date-fields',
        [
            'month_field'    => $month_field,
            'day_field'      => $day_field,
            'year_field'     => $year_field,
            'hour_field'     => $hour_field,
            'minute_field'   => $minute_field,
            'timezone_field' => $timezone_field,
            'date_ts'        => $date_ts,
            'timezone'       => $timezone,
            'begin'          => $begin,
        ]
    );
    ?>
    <?php if (!empty($help)) : ?>
        <div class="mepr-help-text">
          <?php echo wp_kses_post($help); ?>
        </div>
    <?php endif; ?>
</div>
