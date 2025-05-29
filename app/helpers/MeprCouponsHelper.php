<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprCouponsHelper
{
    /**
     * Displays the products dropdown.
     *
     * @param  string $field_name The field name.
     * @param  array  $access     The access.
     * @return void
     */
    public static function products_dropdown($field_name, $access = [])
    {
        $access   = is_array($access) ? $access : [];
        $contents = [];

        $posts = MeprCptModel::all('MeprProduct', false, [
            'orderby' => 'title',
            'order'   => 'ASC',
        ]);

        foreach ($posts as $post) {
            $contents[$post->ID] = $post->post_title;
        }

        ?>
      <select name="<?php echo $field_name; ?>[]" id="<?php echo $field_name; ?>[]" class="mepr-multi-select mepr-coupon-products-select" multiple="true">
        <?php
        foreach ($contents as $curr_type => $curr_label) {
            ?>
          <option value="<?php echo $curr_type; ?>" <?php echo (in_array($curr_type, $access)) ? 'selected="selected"' : ''; ?>><?php echo $curr_label; ?>&nbsp;</option>
            <?php
        }
        ?>
      </select>
        <?php
    }

    /**
     * Displays the months options.
     *
     * @param  integer $ts The timestamp.
     * @return void
     */
    public static function months_options($ts)
    {
        if ($ts <= 0) {
            $ts = time();
        }
        $months = [
            __('Jan', 'memberpress'),
            __('Feb', 'memberpress'),
            __('Mar', 'memberpress'),
            __('Apr', 'memberpress'),
            __('May', 'memberpress'),
            __('Jun', 'memberpress'),
            __('Jul', 'memberpress'),
            __('Aug', 'memberpress'),
            __('Sept', 'memberpress'),
            __('Oct', 'memberpress'),
            __('Nov', 'memberpress'),
            __('Dec', 'memberpress'),
        ];

        foreach ($months as $i => $month) :
            $val = $i + 1;
            ?>
      <option value="<?php echo $val; ?>" <?php echo (MeprUtils::get_date_from_ts($ts, 'n') == $val) ? 'selected="selected"' : ''; ?>><?php echo $month ?></option>
            <?php
        endforeach;
    }

    /**
     * Shows the coupon field link content.
     *
     * @param  string $coupon_code The coupon code.
     * @return string
     */
    public static function show_coupon_field_link_content($coupon_code)
    {
        $content = '';
        if (isset($coupon_code) && !empty($coupon_code)) {
            $content .= sprintf(
                // Translators: %s: coupon code.
                __('Using Coupon &ndash; %s', 'memberpress'),
                esc_html($coupon_code)
            );
        } else {
            $content .= __('Have a coupon?', 'memberpress');
        }
        return $content;
    }

    /**
     * Formats the coupon amount for invoice table
     *
     * @param  string $amount The amount.
     * @return float
     */
    public static function format_coupon_amount($amount)
    {
        return -( $amount );
    }

    /**
     * Convert UTC timestamp to coupon selected timestamp.
     *
     * @param string  $timestamp         UTC Timestamp.
     * @param integer $selected_timezone Selected timezone offset.
     *
     * @return string updated timestamp.
     */
    public static function convert_timestamp_to_tz($timestamp, $selected_timezone)
    {
        if (!empty($selected_timezone) && !empty($timestamp)) {
            $utc_datetime     = MeprUtils::ts_to_mysql_date($timestamp);
            $is_manual_offset = self::check_if_manual_offset($selected_timezone);
            if ($is_manual_offset) {
                $selected_timezone = self::convert_offset_to_timezone_string($selected_timezone);
            }
            try {
                $local_datetime = new DateTime($utc_datetime, new DateTimeZone($selected_timezone));
                $timestamp      = $local_datetime->getTimestamp();
            } catch (Exception $e) {
                // Ignore, send the GMT timestamp.
            }
        }
        return MeprHooks::apply_filters('mepr_coupon_converted_timestamp_to_tz', $timestamp, $selected_timezone);
    }

    /**
     * Check if the passed timezone is a manual offset.
     *
     * @param string $selected_timezone Timezone string.
     *
     * @return boolean
     */
    public static function check_if_manual_offset($selected_timezone)
    {
        if (in_array($selected_timezone, timezone_identifiers_list(), true)) {
            return false;
        }
        return true;
    }

    /**
     * Convert offset to PHP accepted timezone.
     *
     * @param string $offset UTC offset.
     *
     * @return string timezone string.
     */
    public static function convert_offset_to_timezone_string($offset)
    {
        if ('UTC+0' === $offset || 'UTC-0' === $offset) {
            return 'UTC';
        }
        $offset = MeprUtils::float_value(str_replace('UTC', '', strtoupper($offset)));
        if (empty($offset)) {
            return 'UTC';
        }
        $sign       = ($offset < 0) ? '-' : '+';
        $abs_offset = abs($offset);
        $hours      = floor($abs_offset);
        $minutes    = round(($abs_offset - $hours) * 60);
        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }

    /**
     * Get WP Selected timezone setting.
     *
     * @return string
     */
    public static function get_wp_selected_timezone_setting()
    {
        $current_offset       = get_option('gmt_offset');
        $wp_selected_timezone = get_option('timezone_string');

        // Remove old Etc mappings. Fallback to gmt_offset.
        if (str_contains($wp_selected_timezone, 'Etc/GMT')) {
            $wp_selected_timezone = '';
        }
        if (empty($wp_selected_timezone)) { // Create a UTC+- zone if no timezone string exists.
            if (0 === $current_offset) {
                $wp_selected_timezone = 'UTC+0';
            } elseif ($current_offset < 0) {
                $wp_selected_timezone = 'UTC' . $current_offset;
            } else {
                $wp_selected_timezone = 'UTC+' . $current_offset;
            }
        }
        return $wp_selected_timezone;
    }

    /**
     * Get time frame condition.
     *
     * @param string $time_frame Time Frame.
     *
     * @return string
     */
    public static function get_date_query_from_time_frame($time_frame)
    {
        $date_query    = '';
        $time_interval = '';
        if ('monthly' === $time_frame) {
            $time_interval = 30;
        } elseif ('yearly' === $time_frame) {
            $time_interval = 365;
        }
        $time_interval = (int) MeprHooks::apply_filters('mepr_coupon_time_interval_from_time_frame', $time_interval, $time_frame);
        if (!empty($time_interval)) {
            $date_query = sprintf('AND DATE(created_at) BETWEEN CURDATE() - INTERVAL %d DAY AND CURDATE()', $time_interval);
        }
        return $date_query;
    }

    /**
     * List of available time frames.
     */
    public static function get_available_time_frame()
    {
        $time_frames = [
            'lifetime' => __('Lifetime', 'memberpress'),
            'yearly'   => __('Yearly', 'memberpress'),
            'monthly'  => __('Monthly', 'memberpress'),
        ];
        return MeprHooks::apply_filters('mepr_coupon_time_frames_list', $time_frames);
    }
}
