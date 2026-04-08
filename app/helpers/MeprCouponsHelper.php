<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

use MemberPress\GroundLevel\Support\Time;

class MeprCouponsHelper
{
    /**
     * Cache for coupon dropdown query.
     *
     * @var array|null
     */
    private static $coupon_dropdown_cache = null;

    /**
     * Clear the coupon dropdown cache.
     *
     * @return void
     */
    public static function clear_coupon_dropdown_cache(): void
    {
        self::$coupon_dropdown_cache = null;
    }

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
      <select name="<?php echo esc_attr($field_name); ?>[]" id="<?php echo esc_attr($field_name); ?>[]" class="mepr-multi-select mepr-coupon-products-select" multiple="true">
        <?php
        foreach ($contents as $curr_type => $curr_label) {
            ?>
          <option value="<?php echo esc_attr($curr_type); ?>" <?php echo (in_array($curr_type, array_map('intval', $access), true)) ? 'selected="selected"' : ''; ?>><?php echo esc_html($curr_label); ?>&nbsp;</option>
            <?php
        }
        ?>
      </select>
        <?php
    }

    /**
     * Displays the coupons dropdown.
     *
     * @param  string             $field_name   Field name (or full name attribute for single-select with brackets).
     * @param  integer|array|null $selected     Selected coupon ID(s). Single ID for single-select, array for multi-select.
     * @param  array              $exclude      Array of coupon IDs to exclude from the dropdown.
     * @param  boolean            $multiple     Whether to render as multi-select dropdown.
     * @param  string             $css_class    CSS class(es) for the select element. Defaults to multi-select classes if multiple is true.
     * @param  string|null        $default_text Default option text for single-select (null to omit default option).
     * @return void
     */
    public static function coupons_dropdown(
        $field_name,
        $selected = null,
        $exclude = [],
        $multiple = false,
        $css_class = '',
        $default_text = null
    ): void {
        $exclude = is_array($exclude) ? $exclude : [];

        if ($multiple) {
            $selected = is_array($selected) ? $selected : [];
        }

        if (is_null(self::$coupon_dropdown_cache)) {
            self::$coupon_dropdown_cache = MeprCptModel::all('MeprCoupon', false, [
                'orderby' => 'title',
                'order'   => 'ASC',
            ]);
        }

        $posts = self::$coupon_dropdown_cache;

        $contents = [];
        foreach ($posts as $post) {
            if (in_array($post->ID, $exclude, true)) {
                continue;
            }
            $contents[$post->ID] = $post->post_title;
        }

        if ($multiple) {
            $name_attr     = esc_attr($field_name) . '[]';
            $id_attr       = esc_attr($field_name) . '[]';
            $multiple_attr = 'multiple="true"';
            $css_class     = empty($css_class) ? 'mepr-multi-select mepr-coupon-coupons-select' :
                $css_class . ' mepr-multi-select mepr-coupon-coupons-select';
        } else {
            $name_attr     = $field_name;
            $id_attr       = esc_attr($field_name);
            $multiple_attr = '';
        }

        ?>
      <select name="<?php echo $name_attr; ?>" id="<?php echo $id_attr; ?>" class="<?php echo esc_attr($css_class); ?>" <?php echo $multiple_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped ?>>
        <?php if ($default_text !== null && !$multiple) : ?>
          <option value=""><?php echo esc_html($default_text); ?></option>
        <?php endif; ?>
        <?php
        foreach ($contents as $coupon_id => $coupon_title) {
            $is_selected = false;
            if ($multiple) {
                $is_selected = !empty($selected) && in_array($coupon_id, array_map('intval', $selected), true);
            } else {
                $is_selected = $selected !== null && (int) $selected === (int) $coupon_id;
            }
            ?>
          <option value="<?php echo esc_attr($coupon_id); ?>" <?php echo $is_selected ? 'selected="selected"' : ''; ?>><?php echo esc_html($coupon_title); ?><?php echo $multiple ? '&nbsp;' : ''; ?></option>
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
            $ts = Time::now();
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
      <option value="<?php echo esc_attr($val); ?>" <?php echo ((int) MeprUtils::get_date_from_ts($ts, 'n') === $val) ? 'selected="selected"' : ''; ?>><?php echo esc_html($month); ?></option>
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

    /**
     * Returns types for usage on upgrades and downgrades.
     *
     * @return array
     */
    public static function get_usage_on_upgrades_downgrades_types()
    {
        $types = [
            'none'       => __('No', 'memberpress'),
            'upgrades'   => __('Upgrades only', 'memberpress'),
            'downgrades' => __('Downgrades only', 'memberpress'),
            'both'       => __('Both Upgrades and Downgrades', 'memberpress'),
        ];
        return MeprHooks::apply_filters('mepr_coupon_usage_on_upgrades_downgrades_types', $types);
    }

    /**
     * Returns types for usage on upgrades and downgrades if other coupons were used.
     *
     * @return array
     */
    public static function get_usage_if_other_coupon_used_types()
    {
        $types = [
            'unset'       => __('Unset', 'memberpress'),
            'none'        => __('No other coupon', 'memberpress'),
            'any'         => __('Any other coupon', 'memberpress'),
            'anyof'       => __('Any of the following coupons', 'memberpress'),
            'noneof'      => __('None of the following coupons', 'memberpress'),
            'noneoranyof' => __('No other coupon, or any of the following coupons', 'memberpress'),
        ];
        return MeprHooks::apply_filters('mepr_coupon_usage_if_other_coupon_used_types', $types);
    }

    /**
     * Process date/time field from data array.
     *
     * @param  array   $date_data The date data array (expects slashed data, will be unslashed).
     * @param  array   $field_map Field map mapping internal keys to data keys.
     * @param  boolean $begin     Whether this is a start date (true) or end date (false).
     * @return array The output array with date/time information.
     */
    public static function process_date_field($date_data, $field_map, $begin = true): array
    {
        $date_data      = wp_unslash($date_data);
        $default_hour   = $begin ? 0 : 23;
        $default_minute = $begin ? 0 : 59;

        $month    = isset($date_data[$field_map['month']]) ? max(1, intval($date_data[$field_map['month']])) : 1;
        $day      = isset($date_data[$field_map['day']]) ? max(1, intval($date_data[$field_map['day']])) : 1;
        $year     = isset($date_data[$field_map['year']]) ? intval($date_data[$field_map['year']]) : 1970;
        $hour     = isset($date_data[$field_map['hour']]) ? max(0, min(23, intval($date_data[$field_map['hour']]))) :
            $default_hour;
        $minute   = isset($date_data[$field_map['minute']]) ?
            max(0, min(59, intval($date_data[$field_map['minute']]))) :
            $default_minute;
        $timezone = isset($date_data[$field_map['timezone']]) ?
            sanitize_text_field($date_data[$field_map['timezone']]) :
            0;

        $date_ts = MeprUtils::make_ts_date($month, $day, $year, $begin, $hour, $minute);

        return [
            'date_ts'  => $date_ts,
            'timezone' => $timezone,
        ];
    }

    /**
     * Extract field map entries by prefix and remove prefix from keys.
     *
     * @param  array  $field_map The field map array.
     * @param  string $prefix    The prefix to extract (e.g., 'start' or 'end').
     * @return array The extracted field map with prefix removed from keys.
     */
    private static function extract_field_map_by_prefix(array $field_map, string $prefix): array
    {
        $extracted = [];
        $prefix_with_underscore = $prefix . '_';
        foreach ($field_map as $key => $value) {
            if (str_starts_with($key, $prefix_with_underscore)) {
                $new_key            = str_replace($prefix_with_underscore, '', $key);
                $extracted[$new_key] = $value;
            }
        }
        return $extracted;
    }

    /**
     * Process coupon schedule data and return coupon data array.
     *
     * @param  array $coupon_data The coupon data array. Expects slashed data, will be unslashed.
     * @param  array $field_map   Field map mapping internal keys to data keys.
     * @return array The output array with start and end date/time information.
     */
    public static function process_date_fields($coupon_data, $field_map = []): array
    {
        $field_map = wp_parse_args($field_map, self::get_default_date_fields_map());

        // Checkboxes: if checked, field exists in POST. If unchecked, field doesn't exist.
        $should_start_checked = isset($coupon_data[$field_map['should_start']]);
        $should_end_checked   = isset($coupon_data[$field_map['should_end']]);

        // Process start date/time if should_start is checked.
        $start_date_ts  = 0;
        $start_timezone = 0;
        if ($should_start_checked) {
            $start_field_map = self::extract_field_map_by_prefix($field_map, 'start');
            $start_data      = self::process_date_field(
                $coupon_data,
                $start_field_map,
                true
            );
            $start_date_ts   = $start_data['date_ts'];
            $start_timezone  = $start_data['timezone'];

            if (!empty($start_date_ts)) {
                // Get datetime object of start_date_ts in UTC.
                try {
                    $utc_timezone       = new DateTimeZone('UTC');
                    $minimum_start_date = new DateTime('@' . Time::now(), $utc_timezone);

                    $start_start_ts = self::convert_timestamp_to_tz($start_date_ts, $start_timezone);
                    $start_date     = new DateTime('@' . $start_start_ts, $utc_timezone);

                    if ($minimum_start_date > $start_date) {
                        $should_start_checked = false;
                        $start_date_ts        = 0;
                        $start_timezone       = 0;
                    }
                } catch (Exception $e) {
                    // If any exception occurs during date processing, disable start date.
                    $should_start_checked = false;
                    $start_date_ts        = 0;
                    $start_timezone       = 0;
                }
            }
        }

        // Process end date/time if should_end is checked.
        $end_date_ts  = 0;
        $end_timezone = 0;
        if ($should_end_checked) {
            $end_field_map = self::extract_field_map_by_prefix($field_map, 'end');
            $end_data      = self::process_date_field(
                $coupon_data,
                $end_field_map,
                false
            );
            $end_date_ts   = $end_data['date_ts'];
            $end_timezone  = $end_data['timezone'];
        }

        return [
            'should_start'   => $should_start_checked,
            'start_date_ts'  => $start_date_ts,
            'start_timezone' => $start_timezone,
            'should_end'     => $should_end_checked,
            'end_date_ts'    => $end_date_ts,
            'end_timezone'   => $end_timezone,
        ];
    }

    /**
     * Get default date fields map.
     *
     * @return array<string, string>
     */
    private static function get_default_date_fields_map(): array
    {
        return [
            'should_start'   => MeprCoupon::$should_start_str,
            'should_end'     => MeprCoupon::$should_expire_str,
            'start_month'    => MeprCoupon::$starts_on_month_str,
            'start_day'      => MeprCoupon::$starts_on_day_str,
            'start_year'     => MeprCoupon::$starts_on_year_str,
            'start_hour'     => MeprCoupon::$starts_on_hour_str,
            'start_minute'   => MeprCoupon::$starts_on_minute_str,
            'start_timezone' => MeprCoupon::$start_on_timezone_str,
            'end_month'      => MeprCoupon::$expires_on_month_str,
            'end_day'        => MeprCoupon::$expires_on_day_str,
            'end_year'       => MeprCoupon::$expires_on_year_str,
            'end_hour'       => MeprCoupon::$expires_on_hour_str,
            'end_minute'     => MeprCoupon::$expires_on_minute_str,
            'end_timezone'   => MeprCoupon::$expires_on_timezone_str,
        ];
    }

    /**
     * Validate coupon parameter from request source.
     *
     * Retrieves coupon code from specified request source (GET, POST, or REQUEST),
     * sanitizes it, and validates it against the given product ID.
     *
     * @param  integer $product_id          Product ID to validate coupon against.
     * @param  string  $coupon_param_source Source for coupon parameter: 'GET', 'POST', or 'REQUEST' (default 'GET').
     * @param  integer $user_id             User ID to validate coupon against (default 0, uses current user if 0 or negative).
     * @return string Coupon code if valid, empty string otherwise.
     */
    public static function validate_coupon_param(
        int $product_id,
        string $coupon_param_source = 'GET',
        int $user_id = 0
    ): string {
        $source_var = [];
        switch ($coupon_param_source) {
            case 'POST':
                $source_var = $_POST;
                break;
            case 'REQUEST':
                $source_var = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verification not required here.
                break;
            case 'GET':
            default:
                $source_var = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verification not required here.
                break;
        }
        $coupon_param = sanitize_text_field(wp_unslash($source_var['coupon'] ?? ''));

        // Validate coupon code against product.
        if (!empty($coupon_param) && MeprCoupon::is_valid_coupon_code($coupon_param, $product_id, $user_id)) {
            return $coupon_param;
        }

        return '';
    }
}
