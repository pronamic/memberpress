<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprUtils
{
    /**
     * Get the user ID by email.
     * Maybe this should be in MeprUser?
     *
     * @param string $email The email.
     *
     * @return string The user ID.
     */
    public static function get_user_id_by_email($email)
    {
        $user = self::get_user_by('email', $email);
        if (is_object($user)) {
            return $user->ID;
        }

        return '';
    }

    /**
     * Format a Stripe currency amount.
     *
     * @param float $amount The amount to format.
     *
     * @return float The formatted amount.
     */
    public static function format_stripe_currency($amount)
    {
        // Handle zero decimal currencies in Stripe.
        $amount = (MeprStripeGateway::is_zero_decimal_currency())
            ? MeprUtils::format_float($amount, 0)
            : MeprUtils::format_float(($amount * 100), 0);

        return $amount;
    }

    /**
     * Determines whether the user is on a MemberPress admin page.
     *
     * @return boolean
     */
    public static function is_memberpress_admin_page()
    {
        if (! is_admin()) {
            return false;
        }
        global $current_screen;
        return preg_match('/^(memberpress|mp-)/', $current_screen->post_type)
            || preg_match('/^memberpress_page_memberpress-/', $current_screen->id);
    }

    /**
     * Determines if a file is an image.
     *
     * @param string $filename The filename.
     *
     * @return boolean
     */
    public static function is_image($filename)
    {
        if (!file_exists($filename)) {
            return false;
        }

        $file_meta = @getimagesize($filename); // @ suppress errors if $filename is not an image

        if (!is_array($file_meta)) {
            return false;
        }

        $image_mimes = ['image/gif', 'image/jpeg', 'image/png'];

        return in_array($file_meta['mime'], $image_mimes);
    }

    /**
     * Looks up month names.
     *
     * @param  boolean       $abbreviations   If true then will return month name abbreviations.
     * @param  false|integer $index           If false then will return the full array of month names, otherwise returns
     *                                        the name of the month at the supplied numeric index.
     * @param  boolean       $one_based_index If true and a numeric $index is supplied the months array will be treated
     *                                        as if the index were one based (meaning January = 1) instead of zero based.
     * @return string[]|string Returns the month name or abbreviation or the full array of month names or abbreviations
     */
    public static function month_names($abbreviations = true, $index = false, $one_based_index = false)
    {
        if ($abbreviations) {
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
        } else {
            $months = [
                __('January', 'memberpress'),
                __('February', 'memberpress'),
                __('March', 'memberpress'),
                __('April', 'memberpress'),
                __('May', 'memberpress'),
                __('June', 'memberpress'),
                __('July', 'memberpress'),
                __('August', 'memberpress'),
                __('September', 'memberpress'),
                __('October', 'memberpress'),
                __('November', 'memberpress'),
                __('December', 'memberpress'),
            ];
        }

        if ($index === false) {
            return $months; // No index then return the full array.
        }

        $index = $one_based_index ? $index - 1 : $index;

        return $months[$index];
    }

    /**
     * Convert days to weeks, months or years ... or leave as days.
     * Eventually we may want to make this more accurate but for now
     * it's just a quick way to more nicely format the trial days.
     *
     * @param integer $days The number of days.
     *
     * @return array The period type and count.
     */
    public static function period_type_from_days($days)
    {
        // Maybe convert to years
        // For now we don't care about leap year.
        if ($days % 365 === 0) {
            return ['years', (int) round($days / 365)];
        } elseif ($days % 30 === 0) {
            // Not as exact as we'd like but as close as it's gonna get for now.
            return ['months', (int) round($days / 30)];
        } elseif ($days % 7 === 0) {
            // Of course this is exact ... easy peasy.
            return ['weeks', (int) round($days / 7)];
        } else {
            return ['days', $days];
        }
    }

    /**
     * Get the period type name.
     *
     * @param string  $period_type The period type.
     * @param integer $count       The count.
     *
     * @return string The period type name.
     */
    public static function period_type_name($period_type, $count = 1)
    {
        $count = (int)$count;

        switch ($period_type) {
            case 'hours':
                return _n('Hour', 'Hours', $count, 'memberpress');
            case 'days':
                return _n('Day', 'Days', $count, 'memberpress');
            case 'weeks':
                return _n('Week', 'Weeks', $count, 'memberpress');
            case 'months':
                return _n('Month', 'Months', $count, 'memberpress');
            case 'years':
                return _n('Year', 'Years', $count, 'memberpress');
            default:
                return $period_type;
        }
    }

    /**
     * Determines if the permalink structure is on.
     *
     * @return boolean
     */
    public static function rewriting_on()
    {
        $permalink_structure = get_option('permalink_structure');
        return ($permalink_structure and !empty($permalink_structure));
    }

    /**
     * Determines if the user is logged in and the current user is the given user ID.
     *
     * @param integer $user_id The user ID.
     *
     * @return boolean
     */
    public static function is_logged_in_and_current_user($user_id)
    {
        $current_user = self::get_currentuserinfo();

        return (self::is_user_logged_in() and (is_object($current_user) && $current_user->ID == $user_id));
    }

    /**
     * Determines if the user is logged in and an admin.
     *
     * @return boolean
     */
    public static function is_logged_in_and_an_admin()
    {
        return (self::is_user_logged_in() and self::is_mepr_admin());
    }

    /**
     * Determines if the user is logged in and a subscriber.
     *
     * @return boolean
     */
    public static function is_logged_in_and_a_subscriber()
    {
        return (self::is_user_logged_in() and self::is_subscriber());
    }

    /**
     * Get the MemberPress admin capability.
     *
     * @return string The MemberPress admin capability.
     */
    public static function get_mepr_admin_capability()
    {
        return MeprHooks::apply_filters('mepr-admin-capability', 'remove_users');
    }

    /**
     * Determines if the user is a MemberPress admin.
     *
     * @param integer $user_id The user ID.
     *
     * @return boolean
     */
    public static function is_mepr_admin($user_id = null)
    {
        $mepr_cap = self::get_mepr_admin_capability();

        if (empty($user_id)) {
            return self::current_user_can($mepr_cap);
        } else {
            return user_can($user_id, $mepr_cap);
        }
    }

    /**
     * Determines if the user is a subscriber.
     *
     * @return boolean
     */
    public static function is_subscriber()
    {
        return (current_user_can('subscriber'));
    }

    /**
     * Determines if the current user can a given role.
     *
     * @param string $role The role.
     *
     * @return boolean
     */
    public static function current_user_can($role)
    {
        self::include_pluggables('wp_get_current_user');
        return current_user_can($role);
    }

    /**
     * Convert minutes to seconds.
     *
     * @param integer $n The number of minutes.
     *
     * @return integer The number of seconds.
     */
    public static function minutes($n = 1)
    {
        return $n * 60;
    }

    /**
     * Convert hours to seconds.
     *
     * @param integer $n The number of hours.
     *
     * @return integer The number of seconds.
     */
    public static function hours($n = 1)
    {
        return $n * self::minutes(60);
    }

    /**
     * Convert days to seconds.
     *
     * @param integer $n The number of days.
     *
     * @return integer The number of seconds.
     */
    public static function days($n = 1)
    {
        return $n * self::hours(24);
    }

    /**
     * Convert weeks to seconds.
     *
     * @param integer $n The number of weeks.
     *
     * @return integer The number of seconds.
     */
    public static function weeks($n = 1)
    {
        return $n * self::days(7);
    }

    /**
     * Convert months to seconds.
     *
     * @param integer $n         The number of months.
     * @param integer $base_ts   The base timestamp.
     * @param boolean $backwards Whether to go backwards.
     * @param integer $day_num   The day number.
     *
     * @return integer The number of seconds.
     */
    public static function months($n, $base_ts = false, $backwards = false, $day_num = false)
    {
        $base_ts = empty($base_ts) ? time() : $base_ts;

        $month_num  = gmdate('n', $base_ts);
        $day_num    = ( (int) $day_num < 1 || (int) $day_num > 31 ) ? gmdate('j', $base_ts) : $day_num;
        $year_num   = gmdate('Y', $base_ts);
        $hour_num   = gmdate('H', $base_ts);
        $minute_num = gmdate('i', $base_ts);
        $second_num = gmdate('s', $base_ts);

        // We're going to use the FIRST DAY of month for our calc date, then adjust the day of month when we're done
        // This allows us to get the correct target month first, then set the right day of month afterwards.
        try {
            $calc_date = new DateTime("{$year_num}-{$month_num}-1 {$hour_num}:{$minute_num}:{$second_num}", new DateTimeZone('UTC'));
        } catch (Exception $e) {
            return 0;
        }

        if ($backwards) {
            $calc_date->modify("-{$n} month");
        } else {
            $calc_date->modify("+{$n} month");
        }

        $days_in_new_month = $calc_date->format('t');

        // Now that we have the right month, let's get the right day of month.
        if ($days_in_new_month < $day_num) {
            $calc_date->modify('last day of this month');
        } elseif ($day_num > 1) {
            // $calc_date is already at the first day of the month, so we'll minus one day here
            $add_days = ( $day_num - 1 );
            $calc_date->modify("+{$add_days} day");
        }

        // If $backwards is true, this will most likely be a negative number so we'll use abs().
        return abs($calc_date->getTimestamp() - $base_ts);
    }

    /**
     * Convert years to seconds.
     *
     * @param integer $n         The number of years.
     * @param integer $base_ts   The base timestamp.
     * @param boolean $backwards Whether to go backwards.
     * @param integer $day_num   The day number.
     * @param integer $month_num The month number.
     *
     * @return integer The number of seconds.
     */
    public static function years($n, $base_ts = false, $backwards = false, $day_num = false, $month_num = false)
    {
        $base_ts = empty($base_ts) ? time() : $base_ts;

        $day_num    = ( (int) $day_num < 1 || (int) $day_num > 31 ) ? gmdate('j', $base_ts) : $day_num;
        $month_num  = ( (int) $month_num < 1 || (int) $month_num > 12 ) ? gmdate('n', $base_ts) : $month_num;
        $year_num   = gmdate('Y', $base_ts);
        $hour_num   = gmdate('H', $base_ts);
        $minute_num = gmdate('i', $base_ts);
        $second_num = gmdate('s', $base_ts);

        try {
            $calc_date = new DateTime("{$year_num}-{$month_num}-{$day_num} {$hour_num}:{$minute_num}:{$second_num}", new DateTimeZone('UTC'));
        } catch (Exception $e) {
            return 0;
        }

        if ($backwards) {
            $calc_date->modify("-{$n} year");
        } else {
            $calc_date->modify("+{$n} year");
        }

        // If we're counting from Feb 29th on a Leap Year to a non-leap year we need to minus 1 day
        // or we'll end up with a March 1st date.
        if ($day_num == 29 && $month_num == 2 && $calc_date->format('L') == 0) {
            $calc_date->modify('-1 day');
        }

        // If $backwards is true, this will most likely be a negative number so we'll use abs().
        return abs($calc_date->getTimestamp() - $base_ts);
    }

    /**
     * Convert a timestamp into approximate minutes.
     *
     * @param integer $ts The timestamp.
     *
     * @return integer The approximate minutes.
     */
    public static function tsminutes($ts)
    {
        return (int)($ts / 60);
    }

    /**
     * Convert a timestamp into approximate hours.
     *
     * @param integer $ts The timestamp.
     *
     * @return integer The approximate hours.
     */
    public static function tshours($ts)
    {
        return (int)(self::tsminutes($ts) / 60);
    }

    /**
     * Convert a timestamp into approximate days.
     *
     * @param integer $ts The timestamp.
     *
     * @return integer The approximate days.
     */
    public static function tsdays($ts)
    {
        return (int)(self::tshours($ts) / 24);
    }

    /**
     * Convert a timestamp into approximate weeks.
     *
     * @param integer $ts The timestamp.
     *
     * @return integer The approximate weeks.
     */
    public static function tsweeks($ts)
    {
        return (int)(self::tsdays($ts) / 7);
    }

    /**
     * Make a timestamp date.
     * Coupons rely on this be careful changing it
     *
     * @param integer $month The month.
     * @param integer $day   The day.
     * @param integer $year  The year.
     * @param boolean $begin Whether to start at the beginning of the day.
     *
     * @return integer The timestamp.
     */
    public static function make_ts_date($month, $day, $year, $begin = false)
    {
        if (true === $begin) {
            return mktime(00, 00, 01, $month, $day, $year);
        }
        return mktime(23, 59, 59, $month, $day, $year);
    }

    /**
     * Get a date from a timestamp.
     * Coupons rely on this be careful changing it
     *
     * @param integer $ts     The timestamp.
     * @param string  $format The format.
     *
     * @return string The date.
     */
    public static function get_date_from_ts($ts, $format = 'M d, Y')
    {
        if ($ts > 0) {
            return gmdate($format, $ts);
        } else {
            return gmdate($format, time());
        }
    }

    /**
     * Convert a MySQL date to a timestamp.
     *
     * @param string $mysql_date The MySQL date to convert.
     *
     * @return integer The timestamp.
     */
    public static function db_date_to_ts($mysql_date)
    {
        return strtotime($mysql_date);
    }

    /**
     * Convert a timestamp to a MySQL date.
     *
     * @param integer $ts     The timestamp to convert.
     * @param string  $format The date format.
     *
     * @return string The MySQL date.
     */
    public static function ts_to_mysql_date($ts, $format = 'Y-m-d H:i:s')
    {
        return gmdate($format, $ts);
    }

    /**
     * Get the current date and time in MySQL format.
     *
     * @param string $format The date format.
     *
     * @return string The current date and time.
     */
    public static function db_now($format = 'Y-m-d H:i:s')
    {
        return self::ts_to_mysql_date(time(), $format);
    }

    /**
     * Get the MySQL representation of a lifetime.
     *
     * @return string The lifetime representation.
     */
    public static function db_lifetime()
    {
        return '0000-00-00 00:00:00';
    }

    /**
     * Deprecated mysql* functions
     *
     * @param string $mysql_date The MySQL date to convert.
     *
     * @return integer
     */
    public static function mysql_date_to_ts($mysql_date)
    {
        return self::db_date_to_ts($mysql_date);
    }

    /**
     * Get the current date and time in MySQL format.
     *
     * @param string $format The date format.
     *
     * @return string The current date and time.
     */
    public static function mysql_now($format = 'Y-m-d H:i:s')
    {
        return self::db_now($format);
    }

    /**
     * Get the MySQL representation of a lifetime.
     *
     * @return string The lifetime representation.
     */
    public static function mysql_lifetime()
    {
        return self::db_lifetime();
    }

    /**
     * Convert an array to a string.
     *
     * @param array   $my_array The array to convert.
     * @param boolean $debug    Whether to include debug information.
     * @param integer $level    The level of detail.
     *
     * @return string The string representation.
     */
    public static function array_to_string($my_array, $debug = false, $level = 0)
    {
        return self::object_to_string($my_array);
    }

    /**
     * Convert an object to a string.
     *
     * @param object $object The object to convert.
     *
     * @return string The string representation.
     */
    public static function object_to_string($object)
    {
        ob_start();
        print_r($object);

        return ob_get_clean();
    }

    /**
     * Inserts into an associative array
     *
     * @param array   $array  The array to insert into.
     * @param array   $values The values to insert.
     * @param integer $offset The offset to insert the values at.
     *
     * @return array The array with the values inserted.
     */
    public static function a_array_insert($array, $values, $offset)
    {
        return array_slice($array, 0, $offset, true) + $values + array_slice($array, $offset, null, true);
    }

    /**
     * Drop in replacement for evil eval
     *
     * @param string $content     The content to replace values in.
     * @param array  $params      The parameters to replace in the content.
     * @param string $start_token The start token.
     * @param string $end_token   The end token.
     *
     * @return string The content with the values replaced.
     */
    public static function replace_vals($content, $params, $start_token = '\\{\$', $end_token = '\\}')
    {
        if (!is_array($params)) {
            return $content;
        }

        $callback     = function ($k) use ($start_token, $end_token) {
            $k = preg_quote($k, '/');
            return "/{$start_token}" . "[^\W_]*{$k}[^\W_]*" . "{$end_token}/";
        };
        $patterns     = array_map($callback, array_keys($params));
        $replacements = array_values($params);

        // Make sure all replacements can be converted to a string yo.
        foreach ($replacements as $i => $val) {
            // The method_exists below causes a fatal error for incomplete classes.
            if ($val instanceof __PHP_Incomplete_Class) {
                $replacements[$i] = '';
                continue;
            }

            // Numbers and strings and objects with __toString are fine as is.
            if (is_string($val) || is_numeric($val) || (is_object($val) && method_exists($val, '__toString'))) {
                continue;
            }

            // Datetime's.
            if ($val instanceof DateTime && isset($val->date)) {
                $replacements[$i] = $val->date;
                continue;
            }

            // If we made it here ???
            $replacements[$i] = '';
        }

        $result = preg_replace($patterns, $replacements, $content);

        // Remove unreplaced tags.
        return preg_replace('({\$.*?})', '', $result);
    }

    /**
     * Format a tax percentage for display.
     *
     * @param float $number The tax percentage.
     *
     * @return string The formatted tax percentage.
     */
    public static function format_tax_percent_for_display($number)
    {
        $number = self::format_float($number, 3) + 0; // Number with period as decimal point - adding 0 will truncate insignificant 0's at the end.

        // How many decimal places are left?
        $num_remain_dec = strlen(substr(strrchr($number, '.'), 1));

        return number_format_i18n($number, $num_remain_dec);
    }

    /**
     * Format a float to a specified number of decimal places.
     *
     * @param float   $number       The number to format.
     * @param integer $num_decimals The number of decimal places.
     *
     * @return string The formatted number.
     */
    public static function format_float($number, $num_decimals = 2)
    {
        return number_format($number, $num_decimals, '.', '');
    }

    /**
     * Format a float and drop zero decimals.
     *
     * @param float   $n            The number to format.
     * @param integer $num_decimals The number of decimal places.
     *
     * @return string The formatted number.
     */
    public static function format_float_drop_zero_decimals($n, $num_decimals = 2)
    {
        return (
            (floor($n) == round($n, $num_decimals))
            ? number_format($n, 0, '.', '')
            : number_format($n, $num_decimals, '.', '')
        );
    }

    /**
     * Convert a value to a float.
     *
     * @param mixed $val The value to convert.
     *
     * @return float The float value.
     */
    public static function float_value($val)
    {
        $val = str_replace(',', '.', $val);
        $val = preg_replace('/\.(?=.*\.)/', '', $val);

        return floatval($val);
    }

    /**
     * Format a currency float.
     *
     * @param float|string $number       The number to format.
     * @param integer      $num_decimals The number of decimal places.
     *
     * @return string The formatted currency.
     */
    public static function format_currency_float($number, $num_decimals = 2)
    {
        if (is_string($number)) {
            $number = self::float_value($number);
        }

        if (function_exists('number_format_i18n')) {
            return number_format_i18n($number, $num_decimals); // The wp way.
        }

        return self::format_float($number, $num_decimals);
    }

    /**
     * Converts number to US format
     *
     * @param mixed $number       The number to format.
     * @param mixed $num_decimals The number of decimal places.
     *
     * @return mixed
     */
    public static function format_currency_us_float($number, $num_decimals = 2)
    {
        global $wp_locale;

        if (! isset($wp_locale) || false === function_exists('number_format_i18n')) {
            return self::format_float($number, $num_decimals);
        }

        $decimal_point = $wp_locale->number_format['decimal_point'];
        $thousands_sep = $wp_locale->number_format['thousands_sep'];

        // Remove thousand separator.
        $number = str_replace($thousands_sep, '', $number);

        // Fix for locales where the thousand seperator is a space -
        // need to check for the html code, (above) as well as the actual space (handled with preg_replace below) and ascii 160 (str_replace below)
        // and for some reason str_replace doesn't always work on spaces but the preg_replace does.
        if ($thousands_sep == '&nbsp;' || $thousands_sep == ' ' || $thousands_sep == "\xc2\xa0") {
            $number = preg_replace('/\s+/', '', $number);
            $number = str_replace("\xc2\xa0", '', $number);
        }

        // Replaces decimal separator.
        $index = strrpos($number, $decimal_point);
        if ($index !== false) {
            $number[ $index ] = '.';
        }

        return (float) $number;
    }

    /**
     * Check if the current currency is a zero decimal currency.
     *
     * @return boolean True if zero decimal, false otherwise.
     */
    public static function is_zero_decimal_currency()
    {
        $mepr_options  = MeprOptions::fetch();
        $zero_decimals = ['BIF', 'DJF', 'JPY', 'KRW', 'PYG', 'VND', 'XAF', 'XPF', 'CLP', 'GNF', 'KMF', 'MGA', 'RWF', 'VUV', 'XOF', 'HUF'];

        return in_array($mepr_options->currency_code, $zero_decimals, false);
    }

    /**
     * Get all published pages.
     *
     * @return array The pages.
     */
    public static function get_pages()
    {
        global $wpdb;

        $orderby_allowed = ['ID', 'post_title', 'post_date'];
        $orderby         = MeprHooks::apply_filters('mepr_page_orderby', 'ID');
        $orderby         = in_array($orderby, $orderby_allowed) ? $orderby : 'ID';
        $query           = "SELECT * FROM {$wpdb->posts} WHERE post_status = %s AND post_type = %s ORDER BY $orderby";
        $query           = $wpdb->prepare($query, 'publish', 'page');
        $results         = $wpdb->get_results($query);

        if ($results) {
            return $results;
        } else {
            return [];
        }
    }

    /**
     * Check if the current page is a product page.
     *
     * @return boolean True if a product page, false otherwise.
     */
    public static function is_product_page()
    {
        $current_post = self::get_current_post();

        return is_object($current_post) and $current_post->post_type == 'memberpressproduct';
    }

    /**
     * Get the current protocol (http or https).
     *
     * @return string The protocol.
     */
    public static function protocol()
    {
        if (
            is_ssl() ||
            ( defined('MEPR_SECURE_PROXY') && // USER must define this in wp-config.php if they're doing HTTPS between the proxy.
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https' )
        ) {
            return 'https';
        } else {
            return 'http';
        }
    }

    /**
     * Determines if the current protocol is SSL.
     * Less problemmatic replacement for WordPress' is_ssl() function
     *
     * @return boolean True if SSL, false otherwise.
     */
    public static function is_ssl()
    {
        return (self::protocol() === 'https');
    }

    /**
     * Get a property value from a class.
     *
     * @param string $class_name The class name.
     * @param string $property   The property name.
     *
     * @return mixed|null The property value or null if not found.
     */
    public static function get_property($class_name, $property)
    {
        if (!class_exists($class_name)) {
            return null;
        }
        if (!property_exists($class_name, $property)) {
            return null;
        }

        $vars = get_class_vars($class_name);

        return $vars[$property];
    }

    /**
     * Generate a random string.
     *
     * @param integer $length    The length of the string.
     * @param boolean $lowercase Whether to include lowercase letters.
     * @param boolean $uppercase Whether to include uppercase letters.
     * @param boolean $symbols   Whether to include symbols.
     *
     * @return string The random string.
     */
    public static function random_string($length = 10, $lowercase = true, $uppercase = false, $symbols = false)
    {
        $characters  = '0123456789';
        $characters .= $uppercase ? 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' : '';
        $characters .= $lowercase ? 'abcdefghijklmnopqrstuvwxyz' : '';
        $characters .= $symbols ? '@#*^%$&!' : '';
        $string      = '';
        $max_index   = strlen($characters) - 1;

        for ($p = 0; $p < $length; $p++) {
            $string .= $characters[mt_rand(0, $max_index)];
        }

        return $string;
    }

    /**
     * Sanitize a string.
     *
     * @param string $string The string to sanitize.
     *
     * @return string The sanitized string.
     */
    public static function sanitize_string($string)
    {
        // Converts "Hey there buddy-boy!" to "hey_there_buddy_boy".
        return str_replace('-', '_', sanitize_title($string));
    }

    /**
     * Flush rewrite rules.
     *
     * @return void
     */
    public static function flush_rewrite_rules()
    {
        // Load our controllers.
        $controllers = @glob(MEPR_CTRLS_PATH . '/Mepr*Ctrl.php', GLOB_NOSORT);

        foreach ($controllers as $controller) {
            $class = preg_replace('#\.php#', '', basename($controller));

            if (preg_match('#Mepr.*Ctrl#', $class)) {
                $obj = new $class();

                // Only act on MeprCptCtrls.
                if ($obj instanceof MeprCptCtrl) {
                    $obj->register_post_type();
                }
            }
        }

        flush_rewrite_rules();
    }

    /**
     * Format a protected version of a cc num from the last 4 digits
     *
     * @param string $last4 The last 4 digits of the cc num.
     *
     * @return string The protected cc num.
     */
    public static function cc_num($last4 = '****')
    {
        // If a full cc num happens to get here then it gets reduced to the last4 here.
        $last4 = substr($last4, -4);

        return "**** **** **** {$last4}";
    }

    /**
     * Calculate proration by subscription.
     *
     * @param MeprSubscription $old_sub      The old subscription.
     * @param MeprSubscription $new_sub      The new subscription.
     * @param boolean          $reset_period Whether to reset the period.
     */
    public static function calculate_proration_by_subs($old_sub, $new_sub, $reset_period = false)
    {
        // If no money has changed hands yet then no proration.
        if ($old_sub->trial && $old_sub->trial_amount <= 0.00 && $old_sub->txn_count < 1) {
            return (object)[
                'proration' => 0.00,
                'days'      => 0,
            ];
        }

        // If the subscription has a trial and we're in that first trial payment use trial amount
        // Otherwise use regular price.
        if ($old_sub->trial && $old_sub->trial_amount > 0.00 && $old_sub->txn_count == 1) {
            $old_price = $old_sub->trial_amount; // May need to be updated to trial_total when taxes in paid trials feature is in place.
        } else {
            $old_price = $old_sub->price;
        }

        $new_price          = $new_sub->price;
        $days_in_new_period = $new_sub->days_in_this_period(true);

        $coupon = $new_sub->coupon();
        if (
            $new_sub->trial && $new_sub->trial_amount > 0.00 ||
            ($coupon && ($coupon->discount_mode == 'first-payment' || $coupon->discount_mode == 'trial-override'))
        ) {
            $new_price          = $new_sub->trial_amount;
            $days_in_new_period = $new_sub->trial_days;
        }

        $res = self::calculate_proration(
            $old_price,
            $new_price,
            $old_sub->days_in_this_period(),
            $days_in_new_period,
            $old_sub->days_till_expiration(),
            $reset_period,
            $old_sub,
            $new_sub
        );

        return $res;
    }

    /**
     * Calculate proration.
     *
     * @param float            $old_amount    The old amount.
     * @param float            $new_amount    The new amount.
     * @param string           $old_period    The old period.
     * @param string           $new_period    The new period.
     * @param string           $old_days_left The old days left.
     * @param boolean          $reset_period  Whether to reset the period.
     * @param MeprSubscription $old_sub       The old subscription.
     * @param MeprSubscription $new_sub       The new subscription.
     *
     * @return object The proration.
     */
    public static function calculate_proration(
        $old_amount,
        $new_amount,
        $old_period = 'lifetime',
        $new_period = 'lifetime',
        $old_days_left = 'lifetime',
        $reset_period = false,
        $old_sub = false, // These will be false on non auto-recurring.
        $new_sub = false  // These will be false on non auto-recurring.
    ) {
        // By default days left in the new sub are equal to the days left in the old.
        $new_days_left = $old_days_left;

        if (is_numeric($old_period) && is_numeric($new_period) && $new_sub !== false && $old_amount > 0) {
            // Recurring to recurring.
            if ($old_days_left > $new_period || $reset_period) {
                // What if the days left exceed the $new_period?
                // And the new outstanding amount is greater?
                // Days left should be reset to the new period.
                $new_days_left = $new_period;
            }

            $old_per_day_amount = $old_amount / $old_period;
            $new_per_day_amount = $new_amount / $new_period;

            $old_outstanding_amount = $old_per_day_amount * (int) $old_days_left;
            $new_outstanding_amount = $new_per_day_amount * (int) $new_days_left;

            $proration = $new_outstanding_amount - $old_outstanding_amount;

            $days = $new_days_left;
            if ($proration < 0) {
                $proration = 0;

                if ($new_per_day_amount > 0 && $old_outstanding_amount > 0) {
                    $days = $old_outstanding_amount / $new_per_day_amount;
                } else {
                    $days = ($new_amount > 0 ? ((abs($proration) + $new_amount) / $new_amount) * $new_days_left : 0);
                }
            }
        } elseif (is_numeric($old_period) && is_numeric($old_days_left) && ($new_period == 'lifetime' || $new_sub === false) && $old_amount > 0) {
            // Recurring to lifetime
            // Apply outstanding amount to lifetime purchase
            // Calculate amount of money left on old sub.
            $old_outstanding_amount = (($old_amount / $old_period) * $old_days_left);

            $proration = max($new_amount - $old_outstanding_amount, 0.00);
            $days      = 0; // We just do this thing.
        } elseif ($old_period == 'lifetime' && is_numeric($new_period) && $old_amount > 0) {
            // Lifetime to recurring.
            $proration = max($new_amount - $old_amount, 0.00);
            $days      = $new_period; // (is_numeric($old_days_left) && !$reset_period)?$old_days_left:$new_period;
        } elseif ($old_period == 'lifetime' && $new_period == 'lifetime' && $old_amount > 0) {
            // Lifetime to lifetime.
            $proration = max(($new_amount - $old_amount), 0.00);
            $days      = 0; // We be lifetime brah.
        } else {
            // Default.
            $proration = 0;
            $days      = 0;
        }

        // Don't allow amounts that are less than a dollar but greater than zero.
        $proration = (($proration > 0.00 && $proration < 1.00) ? 1.00 : $proration);
        $days      = ceil($days);
        $proration = self::format_float($proration);

        // Make sure we don't do more than 1 year on days.
        if ($days > 365) {
            $days = 365;
        }

        $prorations = (object)compact('proration', 'days');

        return MeprHooks::apply_filters('mepr-proration', $prorations, $old_amount, $new_amount, $old_period, $new_period, $old_days_left, $old_sub, $new_sub, $reset_period);
    }

    /**
     * Check if an array is associative.
     *
     * @param array $arr The array to check.
     *
     * @return boolean True if the array is associative, false otherwise.
     */
    public static function is_associative_array($arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Get post meta with default.
     *
     * @param integer $post_id  The post ID.
     * @param string  $meta_key The meta key.
     * @param boolean $single   Whether to return a single value.
     * @param mixed   $default  The default value.
     */
    public static function get_post_meta_with_default($post_id, $meta_key, $single = false, $default = null)
    {
        $pms = get_post_custom($post_id);
        $var = get_post_meta($post_id, $meta_key, $single);

        if (($single and $var == '') or (!$single and $var == [])) {
            // Since false bools are stored as empty string ('') we need
            // to see if the meta_key is actually stored in the db and
            // it's a bool value before we blindly return default.
            if (isset($pms[$meta_key]) and is_bool($default)) {
                return false;
            } else {
                return $default;
            }
        } else {
            return $var;
        }
    }

    /**
     * Get post meta values.
     *
     * @param string $meta_key The meta key.
     *
     * @return array The post meta values.
     */
    public static function get_post_meta_values($meta_key)
    {
        global $wpdb;

        $query = $wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE meta_key=%s", $meta_key);
        $metas = $wpdb->get_results($query);

        for ($i = 0; $i < count($metas); $i++) {
            $metas[$i]->meta_value = maybe_unserialize($metas[$i]->meta_value);
        }

        return $metas;
    }

    /**
     * Convert to plain text.
     *
     * @param string $text The text to convert.
     *
     * @return string The plain text.
     */
    public static function convert_to_plain_text($text)
    {
        $text = preg_replace('~<style[^>]*>[^<]*</style>~', '', $text);
        $text = strip_tags($text);
        $text = trim($text);
        $text = preg_replace("~\r~", '', $text); // Make sure we're only dealing with \n's here.
        $text = preg_replace("~\n\n+~", "\n\n", $text); // Reduce 1 or more blank lines to 1.

        return $text;
    }

    /**
     * Splice an associative array.
     *
     * @param array $input       The input array.
     * @param mixed $offset      The offset to splice at.
     * @param mixed $length      The length of the splice.
     * @param array $replacement The replacement array.
     *
     * @return void
     */
    public static function array_splice_assoc(&$input, $offset, $length, $replacement)
    {
        $replacement = (array) $replacement;
        $key_indices = array_flip(array_keys($input));

        if (isset($input[$offset]) && is_string($offset)) {
            $offset = $key_indices[$offset];
        }
        if (isset($input[$length]) && is_string($length)) {
            $length = $key_indices[$length] - $offset;
        }

        $input = array_slice($input, 0, $offset, true)
            + $replacement
            + array_slice($input, $offset + $length, null, true);
    }

    /**
     * Get the post URI.
     *
     * @param integer $post_id The post ID.
     *
     * @return string The post URI.
     */
    public static function post_uri($post_id)
    {
        return preg_replace('!' . preg_quote(home_url(), '!') . '!', '', get_permalink($post_id));
    }

    /**
     * Get the sub type.
     *
     * @param mixed $sub The sub.
     *
     * @return string The sub type.
     */
    public static function get_sub_type($sub)
    {
        if ($sub instanceof MeprSubscription) {
            return 'recurring';
        } elseif ($sub instanceof MeprTransaction) {
            return 'single';
        }

        return false;
    }

    /**
     * Get the current post , and account for non-singular views
     *
     * @return WP_Post The current post.
     */
    public static function get_current_post()
    {
        global $post;

        if (in_the_loop()) {
            $post_id = get_the_ID(); // Returns false or ID.

            if ($post_id !== false && $post_id > 0) {
                $new_post = get_post($post_id); // Returns WP_Post or null.
            }
        }

        if (!isset($new_post) && isset($post) && $post instanceof WP_Post && $post->ID > 0) {
            $new_post = get_post($post->ID); // Returns WP_Post or null.
        }

        return (isset($new_post)) ? $new_post : false;
    }

    /**
     * Render a JSON response.
     *
     * @param mixed   $struct   The data structure to render.
     * @param string  $filename The filename for the response.
     * @param boolean $is_debug Whether to include debug information.
     *
     * @return void
     */
    public static function render_json($struct, $filename = '', $is_debug = false)
    {
        header('Content-Type: text/json');

        if (!$is_debug and !empty($filename)) {
            header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
        }

        die(json_encode($struct));
    }

    /**
     * Render an XML response.
     *
     * @param mixed   $struct   The data structure to render.
     * @param string  $filename The filename for the response.
     * @param boolean $is_debug Whether to include debug information.
     *
     * @return void
     */
    protected function render_xml($struct, $filename = '', $is_debug = false)
    {
        header('Content-Type: text/xml');

        if (!$is_debug and !empty($filename)) {
            header("Content-Disposition: attachment; filename=\"{$filename}.xml\"");
        }

        die(self::to_xml($struct));
    }

    /**
     * Render a CSV response.
     *
     * @param mixed   $struct   The data structure to render.
     * @param string  $filename The filename for the response.
     * @param boolean $is_debug Whether to include debug information.
     *
     * @return void
     */
    public static function render_csv($struct, $filename = '', $is_debug = false)
    {
        if (!$is_debug) {
            header('Content-Type: text/csv');

            if (!empty($filename)) {
                header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
            }
        }

        header('Content-Type: text/plain');

        die(self::to_csv($struct));
    }

    /**
     * Render an unauthorized response.
     *
     * @param string $message The unauthorized message.
     *
     * @return void
     */
    public static function render_unauthorized($message)
    {
        header('WWW-Authenticate: Basic realm="' . self::blogname() . '"');
        header('HTTP/1.0 401 Unauthorized');
        die(sprintf(
            // Translators: %s: unauthorized message.
            __('UNAUTHORIZED: %s', 'memberpress'),
            $message
        ));
    }

    /**
     * The main function for converting to an XML document.
     * Pass in a multi dimensional array and this recrusively loops through and builds up an XML document.
     *
     * @param  array            $data             The data to convert.
     * @param  string           $root_node_name   What you want the root node to be - defaults to data.
     * @param  SimpleXMLElement $xml              Should only be used recursively.
     * @param  string           $parent_node_name The parent node name.
     * @return string XML
     */
    public static function to_xml($data, $root_node_name = 'memberpressData', $xml = null, $parent_node_name = '')
    {
        // Turn off compatibility mode as simple xml throws a wobbly if you don't.
        // Deprecated as of PHP 5.3
        // if(ini_get('zend.ze1_compatibility_mode') == 1) {
        // ini_set('zend.ze1_compatibility_mode', 0);
        // }.
        if (is_null($xml)) {
            $xml = simplexml_load_string('<?xml version=\'1.0\' encoding=\'utf-8\'?' . '><' . $root_node_name . ' />');
        }

        // Loop through the data passed in.
        foreach ($data as $key => $value) {
            // No numeric keys in our XML please!
            if (is_numeric($key)) {
                if (empty($parent_node_name)) {
                    $key = 'unknownNode_' . (string)$key; // Make string key...
                } else {
                    $key = preg_replace('/s$/', '', $parent_node_name); // We assume that there's an 's' at the end of the string?
                }
            }

            $key = self::camelcase($key);

            // If there is another array found recursively call this function.
            if (is_array($value)) {
                $node = $xml->addChild($key);
                // Recursive call.
                self::to_xml($value, $root_node_name, $node, $key);
            } else {
                // Add single node.
                $value = htmlentities($value);
                $xml->addChild($key, $value);
            }
        }

        // Pass back as string. Or simple xml object if you want!
        return $xml->asXML();
    }

    /**
     * Formats an associative array as CSV and returns the CSV as a string.
     * Can handle nested arrays, headers are named by associative array keys.
     * Adapted from http://us3.php.net/manual/en/function.fputcsv.php#87120
     *
     * @param array   $struct             The structure to convert.
     * @param string  $delimiter          The delimiter.
     * @param string  $enclosure          The enclosure.
     * @param boolean $enclose_all        Whether to enclose all fields.
     * @param string  $telescope          The telescope.
     * @param boolean $null_to_mysql_null Whether to convert null values to MySQL null.
     *
     * @return string The CSV.
     */
    public static function to_csv(
        $struct,
        $delimiter = ',',
        $enclosure = '"',
        $enclose_all = false,
        $telescope = '.',
        $null_to_mysql_null = false
    ) {
        $struct = self::deep_convert_to_associative_array($struct);

        if (self::is_associative_array($struct)) {
            $struct = [$struct];
        }

        $csv     = '';
        $headers = [];
        $lines   = [];

        foreach ($struct as $row) {
            $last_path = ''; // Tracking for the header.
            $lines[]   = self::process_csv_row(
                $row,
                $headers,
                $last_path,
                '',
                $delimiter,
                $enclosure,
                $enclose_all,
                $telescope,
                $null_to_mysql_null
            );
        }

        // Always enclose headers.
        $csv .= $enclosure . implode($enclosure . $delimiter . $enclosure, array_keys($headers)) . $enclosure . "\n";

        foreach ($lines as $line) {
            $csv_line = array_merge($headers, $line);
            $csv     .= implode($delimiter, array_values($csv_line)) . "\n";
        }

        return $csv;
    }

    /**
     * Expects an associative array for a row of this data structure. Should
     * handle nested arrays by telescoping header values with the $telescope arg.
     *
     * @param array   $row                The row data.
     * @param array   $headers            The headers.
     * @param string  $last_path          The last path.
     * @param string  $path               The path.
     * @param string  $delimiter          The delimiter.
     * @param string  $enclosure          The enclosure.
     * @param boolean $enclose_all        Whether to enclose all fields.
     * @param string  $telescope          The telescope.
     * @param boolean $null_to_mysql_null Whether to convert null values to MySQL null.
     *
     * @return array The processed row.
     */
    private static function process_csv_row(
        $row,
        &$headers,
        &$last_path,
        $path = '',
        $delimiter = ',',
        $enclosure = '"',
        $enclose_all = false,
        $telescope = '.',
        $null_to_mysql_null = false
    ) {

        $output = [];

        foreach ($row as $label => $field) {
            $new_path = (empty($path) ? $label : $path . $telescope . $label);

            if (is_null($field) and $null_to_mysql_null) {
                $headers           = self::header_insert($headers, $new_path, $last_path);
                $last_path         = $new_path;
                $output[$new_path] = 'NULL';

                continue;
            }

            $field = MeprHooks::apply_filters('mepr_process_csv_cell', $field, $label);

            if (is_array($field)) {
                $output += self::process_csv_row($field, $headers, $last_path, $new_path, $delimiter, $enclosure, $enclose_all, $telescope, $null_to_mysql_null);
            } else {
                $delimiter_esc = preg_quote($delimiter, '/');
                $enclosure_esc = preg_quote($enclosure, '/');
                $headers       = self::header_insert($headers, $new_path, $last_path);
                $last_path     = $new_path;

                // Enclose fields containing $delimiter, $enclosure or whitespace.
                if ($enclose_all or preg_match("/(?:{$delimiter_esc}|{$enclosure_esc}|\s)/", $field)) {
                          $output[$new_path] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
                } else {
                    $output[$new_path] = $field;
                }
            }
        }

        return $output;
    }

    /**
     * Insert a header.
     *
     * @param array  $headers   The headers.
     * @param string $new_path  The new path.
     * @param string $last_path The last path.
     *
     * @return array The headers.
     */
    private static function header_insert($headers, $new_path, $last_path)
    {
        if (!isset($headers[$new_path])) {
            $headers = self::array_insert($headers, $last_path, [$new_path => '']);
        }

        return $headers;
    }

    /**
     * Insert an array.
     *
     * @param array  $array  The array.
     * @param string $index  The index.
     * @param array  $insert The insert.
     *
     * @return array The array.
     */
    public static function array_insert($array, $index, $insert)
    {
        $pos    = array_search($index, array_keys($array));
        $pos    = empty($pos) ? 0 : (int)$pos;
        $before = array_slice($array, 0, $pos + 1);
        $after  = array_slice($array, $pos);
        $array  = $before + $insert + $after;

        return $array;
    }

    /**
     * Convert a snake-case string to camel case. The 'lower' parameter
     * will allow you to choose 'lower' camelCase or 'upper' CamelCase.
     *
     * @param string $str  The string to convert.
     * @param string $type The type of camel case.
     *
     * @return string The camel case string.
     */
    public static function camelcase($str, $type = 'lower')
    {
        // Level the playing field.
        $str = strtolower($str);
        // Replace dashes and/or underscores with spaces to prepare for ucwords.
        $str = preg_replace('/[-_]/', ' ', $str);
        // Ucwords bro ... uppercase the first letter of every word.
        $str = ucwords($str);
        // Now get rid of the spaces.
        $str = preg_replace('/ /', '', $str);

        if ($type == 'lower') {
            // Lowercase the first character of the string.
            $str[0] = strtolower($str[0]);
        }

        return $str;
    }

    /**
     * Convert a snake-case string to lower camel case.
     *
     * @param string $str The string to convert.
     *
     * @return string The lower camel case string.
     */
    public static function lower_camelcase($str)
    {
        return self::camelcase($str, 'lower');
    }

    /**
     * Convert a snake-case string to upper camel case.
     *
     * @param string $str The string to convert.
     *
     * @return string The upper camel case string.
     */
    public static function upper_camelcase($str)
    {
        return self::camelcase($str, 'upper');
    }

    /**
     * Convert a string to snake case.
     *
     * @param string $str   The string to convert.
     * @param string $delim The delimiter.
     *
     * @return string The snake case string.
     */
    public static function snakecase($str, $delim = '_')
    {
        // Search for '_-' then just lowercase and ensure correct delim.
        if (preg_match('/[-_]/', $str)) {
            $str = preg_replace('/[-_]/', $delim, $str);
        } else { // Assume camel case.
            $str = preg_replace('/([A-Z])/', $delim . '$1', $str);
            $str = preg_replace('/^' . preg_quote($delim) . '/', '', $str);
        }

        return strtolower($str);
    }

    /**
     * Convert a string to kebab case.
     *
     * @param string $str The string to convert.
     *
     * @return string The kebab case string.
     */
    public static function kebabcase($str)
    {
        return self::snakecase($str, '-');
    }

    /**
     * Convert a string to human case.
     *
     * @param string $str   The string to convert.
     * @param string $delim The delimiter.
     *
     * @return string The human case string.
     */
    public static function humancase($str, $delim = ' ')
    {
        $str = self::snakecase($str, $delim);
        return ucwords($str);
    }

    /**
     * Unsanitize a title.
     *
     * @param string $str The string to unsanitize.
     *
     * @return string The unsanitized string.
     */
    public static function unsanitize_title($str)
    {
        if (!is_string($str)) {
            return __('Unknown', 'memberpress');
        }

        $str = str_replace(['-', '_'], [' ', ' '], $str);
        return ucwords($str);
    }

    /**
     * Deep convert to associative array using JSON.
     * TODO: Find some cleaner way to do a deep convert to an assoc array
     *
     * @param mixed $struct The structure to convert.
     *
     * @return array The associative array.
     */
    public static function deep_convert_to_associative_array($struct)
    {
        return json_decode(json_encode($struct), true);
    }

    /**
     * Hex encode a string.
     *
     * @param string $str   The string to encode.
     * @param string $delim The delimiter.
     *
     * @return string The encoded string.
     */
    public static function hex_encode($str, $delim = '%')
    {
        $encoded = bin2hex($str);
        $encoded = chunk_split($encoded, 2, $delim);
        $encoded = $delim . substr($encoded, 0, strlen($encoded) - strlen($delim));

        return $encoded;
    }

    /**
     * Check if user meta exists.
     *
     * @param integer $user_id  The user ID.
     * @param string  $meta_key The meta key.
     *
     * @return boolean True if the user meta exists, false otherwise.
     */
    public static function user_meta_exists($user_id, $meta_key)
    {
        global $wpdb;

        $q     = "SELECT COUNT(*)
            FROM {$wpdb->usermeta} AS um
           WHERE um.user_id=%d
             AND um.meta_key=%s";
        $q     = $wpdb->prepare($q, $user_id, $meta_key);
        $count = $wpdb->get_var($q);

        return ($count > 0);
    }

    /**
     * Parses a CSV file and returns an associative array
     *
     * @param string $filepath    The path to the CSV file.
     * @param array  $validations The validations.
     * @param array  $mappings    The mappings.
     *
     * @return array The associative array.
     * @throws Exception If the CSV file is missing required columns.
     */
    public static function parse_csv_file($filepath, $validations = [], $mappings = [])
    {
        $assoc     = $headers = [];
        $col_count = 0;
        $row       = 1;

        $handle = fopen($filepath, 'r');
        if ($handle !== false) {
            $delimiter = self::get_file_delimiter($filepath);
            // Check for BOM - Byte Order Mark.
            $bom = "\xef\xbb\xbf";
            // Move pointer to the 4th byte to check if we have a BOM.
            if (fgets($handle, 4) !== $bom) {
                // BOM not found - rewind pointer to start of file.
                rewind($handle);
            }

            while (true) {
                $data = fgetcsv($handle, 1000, $delimiter);
                if ($data === false) {
                    break;
                }

                if ($row === 1) {
                    foreach ($data as $i => $header) {
                        if (!empty($header)) {
                            if (isset($mappings[$header])) {
                                $headers[$i] = $mappings[$header];
                            } else {
                                $headers[$i] = $header;
                            }
                        }
                        foreach ($validations as $col => $v) {
                            if (in_array('required', $v) && !in_array($col, $headers)) {
                                throw new Exception(sprintf(
                                    // Translators: %s: column name.
                                    __('Your CSV file must contain the column: %s', 'memberpress'),
                                    $col
                                ));
                            }
                        }
                    }
                    $col_count = count($headers);
                } else {
                    if (!self::csv_row_is_blank($data)) {
                        $new_row = [];
                        for ($i = 0; $i < $col_count; $i++) {
                            $new_row[$headers[$i]] = $data[$i];
                        }
                        foreach ($validations as $col => $v) {
                            if (in_array('required', $v) && !in_array($col, $headers)) {
                                throw new Exception(sprintf(
                                    // Translators: %s: column name.
                                    __('Your CSV file must contain the column: %s', 'memberpress'),
                                    $col
                                ));
                            }
                        }
                        $assoc[] = $new_row;
                    }
                }
                $row++;
            }
            fclose($handle);
        }

        return $assoc;
    }

    /**
     * Retrieves the file delimiter based on the first line of the provided CSV file.
     *
     * @param  string $filepath The path to the CSV file.
     * @return string The detected delimiter character.
     */
    private static function get_file_delimiter($filepath)
    {
        $delimiters = apply_filters(
            'mepr-csv-tax-rate-delimiters',
            [
                ';'  => 0,
                ','  => 0,
                "\t" => 0,
                '|'  => 0,
            ],
            $filepath
        );

        $handle = fopen($filepath, 'r');

        if ($handle) {
            $first_line = fgets($handle);
            fclose($handle);

            foreach ($delimiters as $delimiter => &$count) {
                $count = count(str_getcsv($first_line, $delimiter));
            }

            if (max($delimiters) > 0) {
                return array_search(max($delimiters), $delimiters);
            }
        }

        return ','; // Default to comma.
    }

    /**
     * Check if a CSV row is blank.
     *
     * @param array $row The row data.
     *
     * @return boolean True if the row is blank, false otherwise.
     */
    private static function csv_row_is_blank($row)
    {
        foreach ($row as $i => $cell) {
            if (!empty($cell)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get countries.
     *
     * @param boolean $prioritize_my_country Whether to prioritize the user's country.
     *
     * @return array The countries.
     */
    public static function countries($prioritize_my_country = true)
    {
        $countries = require(MEPR_I18N_PATH . '/countries.php');

        if ($prioritize_my_country) {
            $country_code = get_option('mepr_biz_country');

            if (!empty($country_code) && isset($countries[$country_code])) {
                $my_country = [$country_code => $countries[$country_code]];
                unset($countries[$country_code]);
                $countries = array_merge($my_country, $countries);
            }
        }

        return MeprHooks::apply_filters(
            'mepr_countries',
            $countries,
            $prioritize_my_country
        );
    }

    /**
     * Get country name.
     *
     * @param string $code The country code.
     *
     * @return string The country name.
     */
    public static function country_name($code)
    {
        $countries = self::countries(false);
        return (isset($countries[$code]) ? $countries[$code] : $code);
    }

    /**
     * Get states.
     *
     * @return array The states.
     */
    public static function states()
    {
        $states = [];
        $sfiles = @glob(MEPR_I18N_PATH . '/states/[A-Z][A-Z].php', GLOB_NOSORT);
        foreach ($sfiles as $sfile) {
            require($sfile);
        }

        return MeprHooks::apply_filters(
            'mepr_states',
            $states
        );
    }

    /**
     * Clean a string.
     *
     * @param string $str The string to clean.
     *
     * @return string The cleaned string.
     */
    public static function clean($str)
    {
        return sanitize_text_field($str);
    }

    /**
     * This is for converting an array that would look something like this into an SQL where clause:
     *        array(
     *          array(
     *            'var' => 'tr.id',
     *            'val' => '28'
     *          ),
     *          array(
     *            'cond' => 'OR',
     *            'var'  => 'tr.txn_type',
     *            'op'   => '<>',
     *            'val'  => 'payment'
     *          )
     *        )
     *
     *      This is mainly used with params coming in from the URL so we don't get any sql injection happening.
     *
     * @param array  $q     The query.
     * @param string $where The where clause.
     *
     * @return string The where clause.
     */
    public static function build_where_clause($q, $where = '')
    {
        global $wpdb;

        if (!empty($q)) {
            foreach ($q as $qk => $qv) {
                if (isset($qv['var']) && isset($qv['val'])) {
                    $cond   = ' ';
                    $cond  .= ((isset($qv['cond']) && preg_match('/^(AND|OR)$/i', $qv['cond'])) ? $qv['cond'] : 'AND');
                    $cond  .= ' ';
                    $cond  .= preg_match('/^`[\w\.]+`$/', $qv['var']) ? $qv['var'] : '`' . $qv['var'] . '`';
                    $cond  .= ((isset($qv['op']) && preg_match('/^(<>|<|>|<=|>=)$/i', $qv['op'])) ? $qv['op'] : '=');
                    $cond  .= is_numeric($qv['val']) ? '%d' : '%s';
                    $where .= $wpdb->prepare($cond, $qv['val']);
                }
            }
        }

        return $where;
    }

    /**
     * Compress CSS.
     *
     * @param string $buffer The buffer.
     *
     * @return string The compressed CSS.
     */
    public static function compress_css($buffer)
    {
        // Remove comments.
        $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);

        // Remove tabs, spaces, newlines, etc.
        $buffer = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $buffer);

        return $buffer;
    }

    /**
     * Load price table CSS URL.
     *
     * @return string The price table CSS URL.
     */
    public static function load_price_table_css_url()
    {
        return MEPR_SCRIPT_URL . '&action=mepr_load_css&t=price_table';
    }

    /**
     * Locate by IP.
     *
     * @param string $ip     The IP address.
     * @param string $source The source.
     *
     * @return array The location.
     */
    public static function locate_by_ip($ip = null, $source = 'geoplugin')
    {
        $ip = (is_null($ip) ? $_SERVER['REMOTE_ADDR'] : $ip);
        if (!self::is_ip($ip)) {
            return false;
        }

        $lockey = 'mp_locate_by_ip_' . md5($ip . $source);
        $loc    = get_transient($lockey);

        if (false === $loc) {
            if ($source == 'freegeoip') {
                $url    = "https://freegeoip.net/json/{$ip}";
                $cindex = 'country_code';
                $sindex = 'region_code';
            } else { // Geoplugin.
                $url    = "http://www.geoplugin.net/json.gp?ip={$ip}";
                $cindex = 'geoplugin_countryCode';
                $sindex = 'geoplugin_regionCode';
            }

            $res = wp_remote_get($url);
            $obj = json_decode($res['body']);

            $state   = (isset($obj->{$sindex}) ? $obj->{$sindex} : '');
            $country = (isset($obj->{$cindex}) ? $obj->{$cindex} : '');

            // If the state is goofy then just blank it out.
            if (file_exists(MEPR_I18N_PATH . '/states/' . $country . '.php')) {
                $states = [];
                require(MEPR_I18N_PATH . '/states/' . $country . '.php');
                if (!isset($states[$country][$state])) {
                    $state = '';
                }
            }

            $loc = (object)compact('state', 'country');
            set_transient($lockey, $loc, DAY_IN_SECONDS);
        }

        return $loc;
    }

    /**
     * Check if an IP address is valid.
     *
     * @param string $ip The IP address.
     *
     * @return boolean True if the IP address is valid, false otherwise.
     */
    public static function is_ip($ip)
    {
        return ((bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6));
    }

    /**
     * Get country by IP.
     *
     * @param string $ip     The IP address.
     * @param string $source The source.
     *
     * @return string The country.
     */
    public static function country_by_ip($ip = null, $source = 'geoplugin')
    {
        return (($loc = self::locate_by_ip()) ? $loc->country : '' );
    }

    /**
     * Get state by IP.
     *
     * @param string $ip     The IP address.
     * @param string $source The source.
     *
     * @return string The state.
     */
    public static function state_by_ip($ip = null, $source = 'geoplugin')
    {
        return (($loc = self::locate_by_ip()) ? $loc->state : '' );
    }

    /**
     * Base36 encode.
     *
     * @param integer $base10 The base10 number.
     *
     * @return string The base36 encoded string.
     */
    public static function base36_encode($base10)
    {
        return base_convert($base10, 10, 36);
    }

    /**
     * Base36 decode.
     *
     * @param string $base36 The base36 encoded string.
     *
     * @return integer The base10 number.
     */
    public static function base36_decode($base36)
    {
        return base_convert($base36, 36, 10);
    }

    /**
     * Check if a string is a date.
     *
     * @param string $str The string to check.
     *
     * @return boolean True if the string is a date, false otherwise.
     */
    public static function is_date($str)
    {
        if (!is_string($str)) {
            return false;
        }

        // Validate date formats: YYYY-MM-DD HH:MM:SS or YYYY-MM-DD.
        if (preg_match('/\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?/', $str)) {
            return strtotime($str) !== false;
        }

        $date_format = get_option('date_format');

        // Replace d/m/Y with m/d/Y and validate.
        if ('d/m/Y' === $date_format) {
            $str = preg_replace('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', '$2/$1/$3', $str);

            return strtotime($str) !== false;
        }

        $locale          = get_locale();
        $locale_date_map = require MEPR_I18N_PATH . '/locale_date_map.php';

        // Validate date formats for non-US locales.
        if (array_key_exists($locale, $locale_date_map)) {
            return MeprUtils::validate_international_date($str, $date_format, $locale_date_map[$locale]);
        }

        $d = MeprHooks::apply_filters('mepr_is_date', strtotime($str), $str);

        return ($d !== false);
    }

    /**
     * Validate an international date.
     *
     * @param string $date_str   The date string.
     * @param string $format     The format.
     * @param array  $locale_map The locale map.
     *
     * @return boolean True if the date is valid, false otherwise.
     */
    private static function validate_international_date($date_str, $format, $locale_map)
    {
        $date_str = strtolower($date_str);

        // Try to detect the locale based on month/day names.
        foreach ($locale_map['months'] as $non_english => $english) {
            if (stripos($date_str, $non_english) !== false) {
                $date_str = str_ireplace($non_english, $english, $date_str);
            }
        }
        foreach ($locale_map['days'] as $non_english => $english) {
            if (stripos($date_str, $non_english) !== false) {
                $date_str = str_ireplace($non_english, $english, $date_str);
            }
        }

        $date = DateTime::createFromFormat($format, $date_str);

        return $date && $date->format($format) === $date_str;
    }

    /**
     * Check if a string is a URL.
     *
     * @param string $str The string to check.
     *
     * @return boolean True if the string is a URL, false otherwise.
     */
    public static function is_url($str)
    {
        return preg_match('/https?:\/\/[\w-]+(\.[\w-]{2,})*(:\d{1,5})?/', $str);
    }

    /**
     * Check if a string is an email.
     *
     * @param string $str The string to check.
     *
     * @return boolean True if the string is an email, false otherwise.
     */
    public static function is_email($str)
    {
        return is_email($str);
    }

    /**
     * Check if a string is a phone number.
     *
     * @param string $str The string to check.
     *
     * @return boolean True if the string is a phone number, false otherwise.
     */
    public static function is_phone($str)
    {
        return preg_match('/\(?\d{3}\)?[- ]\d{3}-\d{4}/', $str);
    }

    /**
     * Get the delimiter for a link.
     *
     * @param string $link The link.
     *
     * @return string The delimiter.
     */
    public static function get_delim($link)
    {
        return ((preg_match('#\?#', $link)) ? '&' : '?');
    }

    /**
     * Get HTTP status codes.
     *
     * @return array The HTTP status codes.
     */
    public static function http_status_codes()
    {
        return [
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'Switch Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Unordered Collection',
            426 => 'Upgrade Required',
            449 => 'Retry With',
            450 => 'Blocked by Windows Parental Controls',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            509 => 'Bandwidth Limit Exceeded',
            510 => 'Not Extended',
        ];
    }

    /**
     * Exit with status.
     *
     * @param integer $status  The status code.
     * @param string  $message The message.
     */
    public static function exit_with_status($status, $message = '')
    {
        $codes = self::http_status_codes();
        header("HTTP/1.1 {$status} {$codes[$status]}", true, $status);
        exit($message);
    }

    /**
     * Log an error.
     *
     * @param string $error The error.
     */
    public static function error_log($error)
    {
        error_log(sprintf(
            // Translators: %s: error message.
            __('*** MemberPress Error: %s', 'memberpress'),
            $error
        ));
    }

    /**
     * Debug log.
     *
     * @param string $message The message.
     */
    public static function debug_log($message)
    {
        // Getting some complaints about using WP_DEBUG here.
        if (defined('WP_MEPR_DEBUG') && WP_MEPR_DEBUG) {
            error_log(sprintf(
                // Translators: %s: debug message.
                __('*** MemberPress Debug: %s', 'memberpress'),
                $message
            ));
        }
    }

    /**
     * Check if an object is a WP_Error.
     *
     * @param mixed $obj The object to check.
     *
     * @return boolean True if the object is a WP_Error, false otherwise.
     */
    public static function is_wp_error($obj)
    {
        if (is_wp_error($obj)) {
            self::error_log($obj->get_error_message());
            return true;
        }

        return false;
    }

    /**
     * EMAIL NOTICE METHODS
     **/
    /**
     * Send notices.
     *
     * @param mixed   $obj         The object.
     * @param string  $user_class  The user class.
     * @param string  $admin_class The admin class.
     * @param boolean $force       Whether to force the email to be sent.
     *
     * @return boolean True if the email was sent, false otherwise.
     */
    public static function send_notices($obj, $user_class = null, $admin_class = null, $force = false)
    {
        if ($obj instanceof MeprSubscription) {
            $params = MeprSubscriptionsHelper::get_email_params($obj);
        } elseif ($obj instanceof MeprTransaction) {
            $params = MeprTransactionsHelper::get_email_params($obj);
        } else {
            return false;
        }

        $usr           = $obj->user();
        $disable_email = MeprHooks::apply_filters('mepr_send_email_disable', false, $obj, $user_class, $admin_class);

        try {
            if (!is_null($user_class) && false == $disable_email) {
                $uemail     = MeprEmailFactory::fetch($user_class);
                $uemail->to = $usr->formatted_email();

                if ($force) {
                    $uemail->send($params);
                } else {
                    $uemail->send_if_enabled($params);
                }
            }

            if (!is_null($admin_class) && false == $disable_email) {
                $aemail = MeprEmailFactory::fetch($admin_class);

                if ($force) {
                    $aemail->send($params);
                } else {
                    $aemail->send_if_enabled($params);
                }
            }
        } catch (Exception $e) {
            // Fail silently for now.
        }
    }

    /**
     * Send signup notices.
     *
     * @param MeprTransaction $txn                The transaction.
     * @param boolean         $force              Whether to force the email to be sent.
     * @param boolean         $send_admin_notices Whether to send admin notices.
     *
     * @return void
     */
    public static function send_signup_notices($txn, $force = false, $send_admin_notices = true)
    {
        $admin_one_off_class = ($send_admin_notices ? 'MeprAdminNewOneOffEmail' : null);
        $admin_class         = ($send_admin_notices ? 'MeprAdminSignupEmail' : null);
        $user                = $txn->user();

        $prd_sent = self::maybe_send_product_welcome_notices($txn, $user, false);

        // If this is a one-off send that email too.
        if (empty($txn->subscription_id)) {
            self::send_notices($txn, null, $admin_one_off_class, $force);
        }

        // Send New Signup Emails?
        if (!$user->signup_notice_sent) {
            // Don't send the MemberPress Welcome Email if the Product Welcome Email was sent instead.
            if ($prd_sent) {
                self::send_notices($txn, null, $admin_class, $force);
            } else {
                self::send_notices($txn, 'MeprUserWelcomeEmail', $admin_class, $force);
            }

            $user->signup_notice_sent = true;
            $user->store();

            // Maybe move this to the bottom of this method outside of an if statement?
            // Not sure if this should happen on each new signup, or only on a member's first signup.
            MeprEvent::record('member-signup-completed', $user, (object)$txn->rec); // Have to use ->rec here for some reason.
        }
    }

    /**
     * Send new subscription notices.
     *
     * @param MeprSubscription $sub The subscription.
     *
     * @return void
     */
    public static function send_new_sub_notices($sub)
    {
        self::send_notices($sub, null, 'MeprAdminNewSubEmail');
    }

    /**
     * Send transaction receipt notices.
     *
     * @param MeprTransaction $txn The transaction.
     *
     * @return void
     */
    public static function send_transaction_receipt_notices($txn)
    {
        /**
   * TODO: These events should probably be moved ... but
          * 'tis very convenient to put them here for now.
*/
        MeprEvent::record('transaction-completed', $txn);

        // This is a recurring payment.
        $sub = $txn->subscription();
        if ($sub) {
            MeprEvent::record('recurring-transaction-completed', $txn);

            if ($sub->txn_count > 1) {
                MeprEvent::record('renewal-transaction-completed', $txn);
            }
        } elseif (!$sub) {
            MeprEvent::record('non-recurring-transaction-completed', $txn);
        }

        self::send_notices(
            $txn,
            'MeprUserReceiptEmail',
            'MeprAdminReceiptEmail'
        );
    }

    /**
     * Send suspended subscription notices.
     *
     * @param MeprSubscription $sub The subscription.
     *
     * @return void
     */
    public static function send_suspended_sub_notices($sub)
    {
        self::send_notices(
            $sub,
            'MeprUserSuspendedSubEmail',
            'MeprAdminSuspendedSubEmail'
        );
        MeprEvent::record('subscription-paused', $sub);
    }

    /**
     * Send resumed subscription notices.
     *
     * @param MeprSubscription $sub        The subscription.
     * @param string           $event_args The event arguments.
     *
     * @return void
     */
    public static function send_resumed_sub_notices($sub, $event_args = '')
    {
        self::send_notices(
            $sub,
            'MeprUserResumedSubEmail',
            'MeprAdminResumedSubEmail'
        );
        MeprEvent::record('subscription-resumed', $sub, $event_args);
    }

    /**
     * Send cancelled subscription notices.
     *
     * @param MeprSubscription $sub The subscription.
     *
     * @return void
     */
    public static function send_cancelled_sub_notices($sub)
    {
        self::send_notices(
            $sub,
            'MeprUserCancelledSubEmail',
            'MeprAdminCancelledSubEmail'
        );
        MeprEvent::record('subscription-stopped', $sub);
    }

    /**
     * Send upgraded transaction notices.
     *
     * @param MeprTransaction $txn The transaction.
     *
     * @return void
     */
    public static function send_upgraded_txn_notices($txn)
    {
        self::send_upgraded_sub_notices($txn);
    }

    /**
     * Send upgraded subscription notices.
     *
     * @param MeprSubscription $sub The subscription.
     *
     * @return void
     */
    public static function send_upgraded_sub_notices($sub)
    {
        self::send_notices(
            $sub,
            'MeprUserUpgradedSubEmail',
            'MeprAdminUpgradedSubEmail'
        );
    }

    /**
     * Record upgraded subscription events.
     *
     * @param mixed           $obj       The object.
     * @param MeprTransaction $event_txn The event transaction.
     *
     * @return void
     */
    public static function record_upgraded_sub_events($obj, $event_txn)
    {
        MeprEvent::record('subscription-upgraded', $obj);

        if ($event_txn instanceof MeprTransaction) {
            MeprEvent::record('subscription-changed', $event_txn, $obj->first_txn_id); // The first_txn_id works best here for Corporate Accounts.
        }

        if ($obj instanceof MeprTransaction) {
            MeprEvent::record('subscription-upgraded-to-one-time', $obj);
        } else {
            MeprEvent::record('subscription-upgraded-to-recurring', $obj);
        }
    }

    /**
     * Send downgraded transaction notices.
     *
     * @param MeprTransaction $txn The transaction.
     *
     * @return void
     */
    public static function send_downgraded_txn_notices($txn)
    {
        self::send_downgraded_sub_notices($txn);
    }

    /**
     * Send downgraded subscription notices.
     *
     * @param MeprSubscription $sub The subscription.
     *
     * @return void
     */
    public static function send_downgraded_sub_notices($sub)
    {
        self::send_notices(
            $sub,
            'MeprUserDowngradedSubEmail',
            'MeprAdminDowngradedSubEmail'
        );
    }

    /**
     * Record downgraded subscription events.
     *
     * @param mixed           $obj       The object.
     * @param MeprTransaction $event_txn The event transaction.
     *
     * @return void
     */
    public static function record_downgraded_sub_events($obj, $event_txn)
    {
        MeprEvent::record('subscription-downgraded', $obj);

        if ($event_txn instanceof MeprTransaction) {
            MeprEvent::record('subscription-changed', $event_txn, $obj->first_txn_id); // The first_txn_id works best here for Corporate Accounts.
        }

        if ($obj instanceof MeprTransaction) {
            MeprEvent::record('subscription-downgraded-to-one-time', $obj);
        } else {
            MeprEvent::record('subscription-downgraded-to-recurring', $obj);
        }
    }

    /**
     * Send refunded transaction notices.
     *
     * @param MeprTransaction $txn  The transaction.
     * @param string          $args The arguments.
     *
     * @return void
     */
    public static function send_refunded_txn_notices($txn, $args = '')
    {
        self::send_notices(
            $txn,
            'MeprUserRefundedTxnEmail',
            'MeprAdminRefundedTxnEmail'
        );

        MeprEvent::record('transaction-refunded', $txn, $args);

        // This is a recurring payment.
        $sub = $txn->subscription();
        if ($sub && $sub->txn_count > 0) {
            MeprEvent::record('recurring-transaction-refunded', $txn, $args);
        }
    }

    /**
     * Send failed transaction notices.
     *
     * @param MeprTransaction $txn The transaction.
     *
     * @return void
     */
    public static function send_failed_txn_notices($txn)
    {
        self::send_notices(
            $txn,
            'MeprUserFailedTxnEmail',
            'MeprAdminFailedTxnEmail'
        );

        MeprEvent::record('transaction-failed', $txn);

        // This is a recurring payment.
        $sub = $txn->subscription();
        if ($sub && $sub->txn_count > 0) {
            MeprEvent::record('recurring-transaction-failed', $txn);
        }
    }

    /**
     * Send CC expiration notices.
     *
     * @param MeprTransaction $txn The transaction.
     *
     * @return void
     */
    public static function send_cc_expiration_notices($txn)
    {
        $sub = $txn->subscription();

        if (
            $sub instanceof MeprSubscription &&
            $sub->cc_expiring_before_next_payment()
        ) {
            self::send_notices(
                $sub,
                'MeprUserCcExpiringEmail',
                'MeprAdminCcExpiringEmail'
            );
        }
    }

    /**
     * Maybe send product welcome notices.
     *
     * @param MeprTransaction $txn          The transaction.
     * @param MeprUser        $user         The user.
     * @param boolean         $force_global Whether to force the global welcome email.
     *
     * @return boolean True if the product welcome email was sent, false otherwise.
     */
    public static function maybe_send_product_welcome_notices($txn, $user, $force_global = true)
    {
        $sent = false;

        try {
            $params     = MeprTransactionsHelper::get_email_params($txn);
            $uemail     = MeprEmailFactory::fetch(
                'MeprUserProductWelcomeEmail',
                'MeprBaseProductEmail',
                [
                    [
                        'product_id' => $txn->product_id,
                    ],
                ]
            );
            $uemail->to = $user->formatted_email();

            if (isset($uemail->product->emails['MeprUserProductWelcomeEmail'])) {
                  $email = $uemail->product->emails['MeprUserProductWelcomeEmail'];
                if (MeprHooks::apply_filters('mepr_user_product_welcome_email_enabled', $email['enabled'], $txn, $user, $uemail)) {
                    // Don't resend the product welcome email if the subscription is resumed.
                    if ($txn->subscription_id > 0 && MeprEvent::get_count_by_event_and_evt_id_and_evt_id_type('subscription-resumed', $txn->subscription_id, 'subscriptions') > 0) {
                        return false;
                    }

                    $uemail->send(
                        $params,
                        stripslashes($email['subject']),
                        stripslashes($email['body']),
                        $email['use_template']
                    );

                    $sent = true;
                } elseif ($force_global) { // Send global Welcome.
                    $uemail     = MeprEmailFactory::fetch('MeprUserWelcomeEmail');
                    $uemail->to = $user->formatted_email();
                    $uemail->send($params);
                    $sent = true;
                }
            }
        } catch (Exception $e) {
            // Fail silently for now.
        }

        return $sent;
    }

    /**
     * Filter array keys.
     *
     * @param array $sarray The source array.
     * @param array $keys   The keys to filter.
     *
     * @return array The filtered array.
     */
    public static function filter_array_keys($sarray, $keys)
    {
        $rarray = [];
        foreach ($sarray as $key => $value) {
            if (in_array($key, $keys)) {
                $rarray[$key] = $value;
            }
        }
        return $rarray;
    }

    /**
     * Maybe wpautop.
     *
     * @param string $text The text.
     *
     * @return string The text.
     */
    public static function maybe_wpautop($text)
    {
        $wpautop_disabled = get_option('mepr_wpautop_disable_for_emails');

        if ($wpautop_disabled) {
            return $text;
        }

        return wpautop($text);
    }

    /**
     * Match URI.
     *
     * @param string  $pattern              The pattern.
     * @param string  $uri                  The URI.
     * @param array   $matches              The matches.
     * @param boolean $include_query_string Whether to include the query string.
     *
     * @return boolean True if the URI matches the pattern, false otherwise.
     */
    public static function match_uri($pattern, $uri, &$matches, $include_query_string = false)
    {
        if ($include_query_string) {
            $uri = urldecode($uri);
        } else {
            // Remove query string and decode.
            $uri = preg_replace('#(\?.*)?$#', '', urldecode($uri));
        }

        // Resolve WP installs in sub-directories.
        preg_match('!^https?://[^/]*?(/.*)$!', site_url(), $m);

        $subdir = ( isset($m[1]) ? $m[1] : '' );
        $regex  = '!^' . $subdir . $pattern . '$!';
        return preg_match($regex, $uri, $matches);
    }

    /**
     * Verifies that a url parameter exists and optionally that it contains a certain value.
     *
     * @param string $name   The name of the parameter.
     * @param string $value  The value of the parameter.
     * @param string $method The method to check.
     *
     * @return boolean True if the parameter exists and optionally contains a certain value, false otherwise.
     */
    public static function valid_url_param($name, $value = null, $method = null)
    {
        $params = $_REQUEST;
        if (!empty($method)) {
            $method = strtoupper($method);

            if ($method == 'GET') {
                $params = $_GET;
            } elseif ($method == 'POST') {
                $params = $_POST;
            }
        }

        $verified = isset($params[$name]);

        if ($verified && !empty($value)) {
            $verified = ($params[$name] == $value);
        }

        return $verified;
    }

    /**
     * Build query string.
     *
     * @param array   $add_params           The additional parameters.
     * @param boolean $include_query_string Whether to include the query string.
     * @param array   $exclude_params       The parameters to exclude.
     * @param boolean $exclude_referer      Whether to exclude the referer.
     *
     * @return string The query string.
     */
    public static function build_query_string(
        $add_params = [],
        $include_query_string = false,
        $exclude_params = [],
        $exclude_referer = true
    ) {
        $query_string = '';
        if ($include_query_string) {
            $query_string = $_SERVER['QUERY_STRING'];
        }

        if (empty($query_string)) {
            $query_string = http_build_query($add_params);
        } else {
            $query_string = $query_string . '&' . http_build_query($add_params);
        }

        if ($exclude_referer) {
            $exclude_params[] = '_wp_http_referer';
        }

        foreach ($exclude_params as $param) {
            $query_string = preg_replace('!&?' . preg_quote($param, '!') . '=[^&]*!', '', $query_string);
        }

        return $query_string;
    }

    /**
     * Admin URL.
     *
     * @param string  $path                 The path.
     * @param array   $add_nonce            The nonce, $add_nonce = [$action,$name].
     * @param array   $add_params           The additional parameters.
     * @param boolean $include_query_string Whether to include the query string.
     * @param array   $exclude_params       The parameters to exclude.
     * @param boolean $exclude_referer      Whether to exclude the referer.
     *
     * @return string The admin URL.
     */
    public static function admin_url(
        $path,
        $add_nonce = [],
        $add_params = [],
        $include_query_string = false,
        $exclude_params = [],
        $exclude_referer = true
    ) {
        $delim = MeprUtils::get_delim($path);

        // Automatically exclude the nonce if it's present.
        if (!empty($add_nonce)) {
            $nonce_action     = $add_nonce[0];
            $nonce_name       = (isset($add_nonce[1]) ? $add_nonce[1] : '_wpnonce');
            $exclude_params[] = $nonce_name;
        }

        $url = admin_url($path . $delim . self::build_query_string($add_params, $include_query_string, $exclude_params, $exclude_referer));

        if (empty($add_nonce)) {
            return $url;
        } else {
            return html_entity_decode(wp_nonce_url($url, $nonce_action, $nonce_name));
        }
    }

    /**
     * Pretty permalinks using index.
     *
     * @return boolean True if pretty permalinks are using index, false otherwise.
     */
    public static function pretty_permalinks_using_index()
    {
        $permalink_structure = get_option('permalink_structure');
        return preg_match('!^/index.php!', $permalink_structure);
    }

    /**
     * This returns the structure for all of the gateway notify urls.
     * It can even account for folks unlucky enough to have to prepend
     * their URLs with '/index.php'.
     * NOTE: This function is only applicable if pretty permalinks are enabled.
     */
    public static function gateway_notify_url_structure()
    {
        $pre_slug_index = '';
        if (self::pretty_permalinks_using_index()) {
            $pre_slug_index = '/index.php';
        }

        return MeprHooks::apply_filters(
            'mepr_gateway_notify_url_structure',
            "{$pre_slug_index}/mepr/notify/%gatewayid%/%action%"
        );
    }

    /**
     * This modifies the gateway notify url structure to be matched against a uri.
     * By default it will generate this: /mepr/notify/([^/\?]+)/([^/\?]+)/?
     * However, this could change depending on what gateway_notify_url_structure returns
     */
    public static function gateway_notify_url_regex_pattern()
    {
        return preg_replace('!(%gatewayid%|%action%)!', '([^/\?]+)', self::gateway_notify_url_structure()) . '/?';
    }

    /**
     * Returns an array to be used with wp_remote_request
     *
     * @param string $jwt    The JWT.
     * @param string $domain The domain.
     *
     * @return array The array to be used with wp_remote_request.
     */
    public static function jwt_header($jwt, $domain)
    {
        return [
            'Authorization' => 'Bearer ' . $jwt,
            'Accept'        => 'application/json;ver=1.0',
            'Content-Type'  => 'application/json; charset=UTF-8',
            'Host'          => $domain,
        ];
    }

    /**
     * A more robust way to get a header.
     *
     * @param string $header_name The header name.
     *
     * @return string The header value.
     */
    public static function get_http_header($header_name)
    {
        $header_name        = strtoupper($header_name);
        $server_header_name = 'HTTP_' . str_replace('-', '_', $header_name);

        if (isset($_SERVER[$server_header_name])) {
            return $_SERVER[$server_header_name];
        } elseif (function_exists('getallheaders')) {
            $myheaders = getallheaders();

            $headers_upper = array_change_key_case($myheaders, CASE_UPPER);
            if (isset($headers_upper[$header_name])) {
                return $headers_upper[$header_name];
            }
        }

        return false;
    }

    // PLUGGABLE FUNCTIONS AS TO NOT STEP ON OTHER PLUGINS' CODE.
    /**
     * Get the current user info.
     *
     * @return MeprUser|false The current user object or false if not logged in.
     */
    public static function get_currentuserinfo()
    {
        self::include_pluggables('wp_get_current_user');
        $current_user = wp_get_current_user();

        if (isset($current_user->ID) && $current_user->ID > 0) {
            return new MeprUser($current_user->ID);
        } else {
            return false;
        }
    }

    /**
     * Get current user ID.
     *
     * @return integer The current user ID.
     */
    public static function get_current_user_id()
    {
        self::include_pluggables('wp_get_current_user');
        return get_current_user_id();
    }

    /**
     * Get user by field.
     *
     * @param string $field The field.
     * @param string $value The value.
     *
     * @return WP_User The user.
     */
    public static function get_user_by($field, $value)
    {
        self::include_pluggables('get_user_by');

        return get_user_by($field, $value);
    }

    // Just sends to the emails configured in the plugin settings.
    /**
     * Send email to the emails configured in the plugin settings.
     *
     * @param string $subject The subject.
     * @param string $message The message.
     * @param string $headers The headers.
     */
    public static function wp_mail_to_admin($subject, $message, $headers = '')
    {
        $mepr_options = MeprOptions::fetch();
        $recipient    = $mepr_options->admin_email_addresses;
        self::wp_mail($recipient, $subject, $message, $headers);
    }

    /**
     * Send email.
     *
     * @param string $recipient   The recipient.
     * @param string $subject     The subject.
     * @param string $message     The message.
     * @param string $headers     The headers.
     * @param array  $attachments The attachments.
     *
     * @return void
     */
    public static function wp_mail($recipient, $subject, $message, $headers = '', $attachments = [])
    {
        self::include_pluggables('wp_mail');

        // Parse shortcodes in the message body.
        $message = do_shortcode($message);

        add_filter('wp_mail_from_name', 'MeprUtils::set_mail_from_name');
        add_filter('wp_mail_from', 'MeprUtils::set_mail_from_email');
        add_action('phpmailer_init', 'MeprUtils::reset_alt_body', 5);

        // We just send individual emails.
        $recipients = explode(',', $recipient);
        $recipients = MeprHooks::apply_filters('mepr-wp-mail-recipients', $recipients, $subject, $message, $headers);
        $subject    = MeprHooks::apply_filters('mepr-wp-mail-subject', $subject, $recipients, $message, $headers);
        $message    = MeprHooks::apply_filters('mepr-wp-mail-message', $message, $recipients, $subject, $headers);
        $headers    = MeprHooks::apply_filters('mepr-wp-mail-headers', $headers, $recipients, $subject, $message, $attachments);

        MeprHooks::do_action('mepr_before_send_email', $recipients, $subject, $message, $headers, $attachments);

        foreach ($recipients as $to) {
            $to = trim($to);

            // TEMP FIX TO AVOID ALL THE SENDGRID ISSUES WE'VE BEEN SEEING IN SUPPORT LATELY
            // Let's get rid of the pretty TO's -- causing too many problems
            // mbstring?
            if (extension_loaded('mbstring')) {
                if (mb_strpos($to, '<') !== false) {
                    $to = mb_substr($to, (mb_strpos($to, '<') + 1), -1);
                }
            } else {
                if (strpos($to, '<') !== false) {
                    $to = substr($to, (strpos($to, '<') + 1), -1);
                }
            }

            wp_mail($to, $subject, $message, $headers, $attachments);

            /*
             * Just leaving these here as I need to debug this shiz enough, it would save me some time.
             *
                global $phpmailer;
                var_dump($phpmailer);
             */
        }

        MeprHooks::do_action('mepr_after_send_email', $recipients, $subject, $message, $headers, $attachments);

        remove_action('phpmailer_init', 'MeprUtils::reset_alt_body', 5);
        remove_filter('wp_mail_from', 'MeprUtils::set_mail_from_name');
        remove_filter('wp_mail_from_name', 'MeprUtils::set_mail_from_email');
    }

    /**
     * Set the mail from name.
     *
     * @param string $name The name.
     *
     * @return string The name.
     */
    public static function set_mail_from_name($name)
    {
        $mepr_options = MeprOptions::fetch();

        return $mepr_options->mail_send_from_name;
    }

    /**
     * Set the mail from email.
     *
     * @param string $email The email.
     *
     * @return string The email.
     */
    public static function set_mail_from_email($email)
    {
        $mepr_options = MeprOptions::fetch();

        return $mepr_options->mail_send_from_email;
    }

    /**
     * Make sure to reset the AltBody or it can contain remnants of other emails already sent in the same request
     *
     * @param PHPMailer $phpmailer The PHPMailer object.
     */
    public static function reset_alt_body($phpmailer)
    {
        $phpmailer->AltBody = '';
    }

    /**
     * Determines if the user is logged in.
     *
     * @return boolean True if the user is logged in, false otherwise.
     */
    public static function is_user_logged_in()
    {
        self::include_pluggables('is_user_logged_in');

        return is_user_logged_in();
    }

    /**
     * Get the avatar.
     *
     * @param integer $id   The ID.
     * @param integer $size The size.
     *
     * @return string The avatar.
     */
    public static function get_avatar($id, $size)
    {
        self::include_pluggables('get_avatar');

        return get_avatar($id, $size);
    }

    /**
     * Hash a password.
     *
     * @param string $password_str The password.
     *
     * @return string The hashed password.
     */
    public static function wp_hash_password($password_str)
    {
        self::include_pluggables('wp_hash_password');

        return wp_hash_password($password_str);
    }

    /**
     * Generate a password.
     *
     * @param integer $length        The length.
     * @param boolean $special_chars Whether to include special characters.
     *
     * @return string The password.
     */
    public static function wp_generate_password($length, $special_chars) // Don't test.
    {
        self::include_pluggables('wp_generate_password');

        return wp_generate_password($length, $special_chars);
    }

    /**
     * Get the permalink.
     * Special handling for protocol
     *
     * @param integer $id        The ID.
     * @param boolean $leavename Whether to leave the name.
     *
     * @return string The permalink.
     */
    public static function get_permalink($id = 0, $leavename = false)
    {
        $permalink = get_permalink($id, $leavename);

        if (self::is_ssl()) {
            $permalink = preg_replace('!^https?://!', 'https://', $permalink);
        }

        return $permalink;
    }

    /**
     * Get the current request URL.
     *
     * @return string
     */
    public static function get_current_url()
    {
        return (is_ssl() ? 'https' : 'http') . '://' . wp_unslash($_SERVER['HTTP_HOST']) . wp_unslash($_SERVER['REQUEST_URI']);
    }

    /**
     * Get the current URL without parameters.
     *
     * @return string The current URL without parameters.
     */
    public static function get_current_url_without_params()
    {
        return explode('?', $_SERVER['REQUEST_URI'], 2)[0];
    }

    /**
     * Get account page URL
     *
     * @param  WP_Post $post The post object.
     * @return string
     */
    public static function get_account_url($post = null)
    {
        if (null === $post) {
            global $post;
        }

        // Permalink is empty when set to Plain (default).
        $pretty_permalink = get_option('permalink_structure');

        if (empty($pretty_permalink) && isset($post->ID) && $post->ID > 0) {
            $account_url = MeprUtils::get_permalink($post->ID);
        } else {
            $account_url = MeprUtils::get_current_url_without_params();
        }

        return $account_url;
    }

    /**
     * Redirect to a location.
     *
     * @param string  $location The location.
     * @param integer $status   The status.
     */
    public static function wp_redirect($location, $status = 302)
    {
        self::include_pluggables('wp_redirect');

        // Don't cache redirects YO!
        header('Cache-Control: private, no-cache, no-store, max-age=0, must-revalidate, proxy-revalidate');
        header('Pragma: no-cache');
        header('Expires: Fri, 01 Jan 2016 00:00:01 GMT', true); // Some date in the past.
        wp_redirect($location, $status);

        exit;
    }

    /**
     * Authenticate a user.
     * Probably shouldn't use this any more to authenticate passwords - see MeprUtils::wp_check_password instead
     *
     * @param string $username The username.
     * @param string $password The password.
     *
     * @return WP_User The user.
     */
    public static function wp_authenticate($username, $password)
    {
        self::include_pluggables('wp_authenticate');
        return wp_authenticate($username, $password);
    }

    /**
     * Check a password.
     *
     * @param WP_User $user     The user.
     * @param string  $password The password.
     *
     * @return boolean True if the password is correct, false otherwise.
     */
    public static function wp_check_password($user, $password)
    {
        self::include_pluggables('wp_check_password');
        return wp_check_password($password, $user->data->user_pass, $user->ID);
    }

    /**
     * Check an AJAX referer.
     *
     * @param string $slug  The slug.
     * @param string $param The parameter.
     *
     * @return boolean True if the referer is correct, false otherwise.
     */
    public static function check_ajax_referer($slug, $param)
    {
        self::include_pluggables('check_ajax_referer');
        return check_ajax_referer($slug, $param);
    }

    /**
     * Include pluggables.
     *
     * @param string $function_name The function name.
     */
    public static function include_pluggables($function_name)
    {
        if (!function_exists($function_name)) {
            require_once(ABSPATH . WPINC . '/pluggable.php');
        }
    }

    /**
     * Get the login URL.
     *
     * @return string The login URL.
     */
    public static function login_url()
    {
        // These funcs are thin wrappers for WP funcs, no need to test.
        $mepr_options = MeprOptions::fetch();

        if ($mepr_options->login_page_id > 0) {
            return $mepr_options->login_page_url();
        } else {
            return wp_login_url($mepr_options->account_page_url());
        }
    }

    /**
     * Get the logout URL.
     *
     * @return string The logout URL.
     */
    public static function logout_url()
    {
        return MeprHooks::apply_filters('mepr-logout-url', wp_logout_url(self::login_url()));
    }

    /**
     * Get the site domain.
     *
     * @return string The site domain.
     */
    public static function site_domain()
    {
        return preg_replace('#^https?://(www\.)?([^\?\/]*)#', '$2', get_option('home'));
    }

    /**
     * Check if cURL is enabled.
     *
     * @return boolean True if cURL is enabled, false otherwise.
     */
    public static function is_curl_enabled()
    {
        return function_exists('curl_version');
    }

    /**
     * Check if the request is a POST request.
     *
     * @return boolean True if the request is a POST request, false otherwise.
     */
    public static function is_post_request()
    {
        if (isset($_SERVER['REQUEST_METHOD'])) {
            return (strtolower($_SERVER['REQUEST_METHOD']) == 'post');
        } else {
            return (isset($_POST) && !empty($_POST));
        }
    }

    /**
     * Check if the request is a GET request.
     *
     * @return boolean True if the request is a GET request, false otherwise.
     */
    public static function is_get_request()
    {
        if (isset($_SERVER['REQUEST_METHOD'])) {
            return (strtolower($_SERVER['REQUEST_METHOD']) == 'get');
        } else {
            return (!isset($_POST) || empty($_POST));
        }
    }

    /**
     * Pieces together the current url like a champ
     *
     * @return string The current URL.
     */
    public static function request_url()
    {
        $url = (self::is_ssl()) ? 'https://' : 'http://';

        if ($_SERVER['SERVER_PORT'] != '80') {
            $url .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
        } else {
            $url .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        return $url;
    }

    /**
     * Get the formatted user meta.
     *
     * @param integer $user_id The user ID.
     *
     * @return array The formatted user meta.
     */
    public static function get_formatted_usermeta($user_id)
    {
        $mepr_options    = MeprOptions::fetch();
        $ums             = get_user_meta($user_id);
        $new_ums         = [];
        $return_ugly_val = MeprHooks::apply_filters('mepr-return-ugly-usermeta-vals', false);

        if (!empty($ums)) {
            foreach ($ums as $umkey => $um) {
                // Only support first val for now and yes some of these will be serialized values.
                $val    = maybe_unserialize($um[0]);
                $strval = $val;

                if (is_array($val)) { // Handle array type custom fields like multi-select, checkboxes etc we'll unsanitize the vals.
                    if (!empty($val)) {
                        foreach ($val as $i => $k) {
                            if (is_int($i)) { // Multiselects (indexed array).
                                if (!$return_ugly_val) {
                                    $k = self::unsanitize_title($k);
                                }
                                  $strval = (is_array($strval)) ? "{$k}" : $strval . ", {$k}";
                            } else { // Checkboxes (associative array).
                                if (!$return_ugly_val) {
                                    $i = self::unsanitize_title($i);
                                }
                                $strval = (is_array($strval)) ? "{$i}" : $strval . ", {$i}";
                            }
                        }
                    } else { // Convert empty array to empty string.
                        $strval = '';
                    }
                } elseif ($val == 'on') { // Single checkbox.
                    $strval = _x('Checked', 'ui', 'memberpress');
                } elseif ($return_ugly_val) { // Return the ugly value.
                    $strval = $val;
                } else { // We need to check for checkboxes and radios and match them up with MP custom fields.
                    $mepr_field = $mepr_options->get_custom_field($umkey);

                    if (!is_null($mepr_field) && !empty($mepr_field->options)) {
                        foreach ($mepr_field->options as $option) {
                            if ($option->option_value == $val) {
                                $strval = stripslashes($option->option_name);
                                break; // Found a match, so stop here.
                            }
                        }
                    }
                }

                $new_ums["{$umkey}"] = $strval;
            }
        }

        return $new_ums;
    }

    /**
     * Send an admin signup notification.
     * purely for backwards compatibility (deprecated)
     *
     * @param array $params The parameters.
     */
    public static function send_admin_signup_notification($params)
    {
        $txn    = MeprTransaction::get_one_by_trans_num($params['trans_num']);
        $txn    = new MeprTransaction($txn->id);
        $params = MeprTransactionsHelper::get_email_params($txn); // Yeah, re-set these.
        $usr    = $txn->user();

        try {
            $aemail = MeprEmailFactory::fetch('MeprAdminSignupEmail');
            $aemail->send($params);
        } catch (Exception $e) {
            // Fail silently for now.
        }
    }

    /**
     * Send a user signup notification.
     *
     * @param array $params The parameters.
     */
    public static function send_user_signup_notification($params)
    {
        $txn    = MeprTransaction::get_one_by_trans_num($params['trans_num']);
        $txn    = new MeprTransaction($txn->id);
        $params = MeprTransactionsHelper::get_email_params($txn); // Yeah, re-set these.
        $usr    = $txn->user();

        try {
            $uemail     = MeprEmailFactory::fetch('MeprUserWelcomeEmail');
            $uemail->to = $usr->formatted_email();
            $uemail->send($params);
        } catch (Exception $e) {
            // Fail silently for now.
        }
    }

    /**
     * Send a user receipt notification.
     *
     * @param array $params The parameters.
     */
    public static function send_user_receipt_notification($params)
    {
        $txn    = MeprTransaction::get_one_by_trans_num($params['trans_num']);
        $txn    = new MeprTransaction($txn->id);
        $params = MeprTransactionsHelper::get_email_params($txn); // Yeah, re-set these.
        $usr    = $txn->user();

        try {
            $uemail     = MeprEmailFactory::fetch('MeprUserReceiptEmail');
            $uemail->to = $usr->formatted_email();
            $uemail->send($params);

            $aemail = MeprEmailFactory::fetch('MeprAdminReceiptEmail');
            $aemail->send($params);
        } catch (Exception $e) {
            // Fail silently for now.
        }
    }

    /**
     * Get the ID of the current screen
     *
     * @return string|null
     */
    public static function get_current_screen_id()
    {
        global $current_screen;

        if ($current_screen instanceof WP_Screen) {
            return $current_screen->id;
        }

        return null;
    }

    /**
     * Formats and translates a date or time
     *
     * @param  string            $format   The format of the returned date.
     * @param  DateTimeInterface $date     The DateTime or DateTimeImmutable instance representing the moment of time in UTC, or null to use the current time.
     * @param  DateTimeZone      $timezone The timezone of the returned date, will default to the WP timezone if omitted.
     * @return string|false                The formatted date or false if there was an error
     */
    public static function date($format, DateTimeInterface $date = null, DateTimeZone $timezone = null)
    {
        if (!$date) {
            $date = date_create('@' . time());

            if (!$date) {
                return false;
            }
        }

        $timestamp = $date->getTimestamp();

        if ($timestamp === false || !function_exists('wp_date')) {
            $timezone = $timezone ? $timezone : self::get_timezone();
            $date->setTimezone($timezone);

            return $date->format($format);
        }

        return wp_date($format, $timestamp, $timezone);
    }

    /**
     * Get the WP timezone as a DateTimeZone instance
     *
     * Duplicate of wp_timezone() for WP <5.3.
     *
     * @return DateTimeZone
     */
    public static function get_timezone()
    {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        $timezone_string = get_option('timezone_string');

        if ($timezone_string) {
            return new DateTimeZone($timezone_string);
        }

        $offset  = (float) get_option('gmt_offset');
        $hours   = (int) $offset;
        $minutes = ($offset - $hours);

        $sign      = ($offset < 0) ? '-' : '+';
        $abs_hour  = abs($hours);
        $abs_mins  = abs($minutes * 60);
        $tz_offset = sprintf('%s%02d:%02d', $sign, $abs_hour, $abs_mins);

        return new DateTimeZone($tz_offset);
    }

    /**
     * Matches each symbol of PHP date format standard
     * with datepicker format
     *
     * @param  string $format PHP date format.
     * @return string reformatted string
     */
    public static function datepicker_format($format)
    {
        $supported_options = [
            'd'   => 'dd',  // Day, leading 0.
            'j'   => 'd',   // Day, no 0.
            'z'   => 'o',   // Day of the year, no leading zeroes,
            // 'D' => 'D',   // Day name short, not sure how it'll work with translations.
            'l '  => 'DD ',  // Day name full, idem before.
            'l, ' => 'DD, ',  // Day name full, idem before.
            'm'   => 'mm',  // Month of the year, leading 0.
            'n'   => 'm',   // Month of the year, no leading 0.
            // 'M' => 'M',   // Month, Short name.
            'F '  => 'MM ',  // Month, full name.
            'F, ' => 'MM, ',  // Month, full name.
            'y'   => 'y',   // Year, two digit.
            'Y'   => 'yy',  // Year, full.
            'H'   => 'HH',  // Hour with leading 0 (24 hour).
            'G'   => 'H',   // Hour with no leading 0 (24 hour).
            'h'   => 'hh',  // Hour with leading 0 (12 hour).
            'g'   => 'h',   // Hour with no leading 0 (12 hour).
            'i'   => 'mm',  // Minute with leading 0.
            's'   => 'ss',  // Second with leading 0.
            'a'   => 'tt',  // An am/pm.
            'A'   => 'TT', // AM/PM.
        ];

        foreach ($supported_options as $php => $js) {
            $format = preg_replace("~(?<!\\\\)$php~", $js, $format);
        }

        $supported_options = [
            'l' => 'DD',  // Day name full, idem before.
            'F' => 'MM',  // Month, full name.
        ];

        if (isset($supported_options[ $format ])) {
            $format = $supported_options[ $format ];
        }

        $format = preg_replace_callback('~(?:\\\.)+~', [__CLASS__, 'wrap_escaped_chars'], $format);

        return $format;
    }

    /**
     * Helper function
     *
     * @param  string $value The value to wrap/escape.
     * @return string
     */
    public static function wrap_escaped_chars($value)
    {
        return '&#39;' . str_replace('\\', '', $value[0]) . '&#39;';
    }

    /**
     * Get the site title (blogname)
     *
     * @return string
     */
    public static function blogname()
    {
        return wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    }

    /**
     * Determine whether our Black Friday promotion is active.
     *
     * @return boolean
     */
    public static function is_black_friday_time()
    {
        // Currently runs between November 22 and December 3, 2021.
        return time() > strtotime('2021-11-22 00:00:00 America/Denver') && time() < strtotime('2021-12-04 00:00:00 America/Denver');
    }

    /**
     * Determine whether our promotion is active.
     *
     * @return boolean
     */
    public static function is_promo_time()
    {
        // Start date - end date.
        return time() < strtotime('2022-08-30 00:00:00 America/Denver');
    }

    /**
     * Get the edition data from a product slug
     *
     * @param  string $product_slug The product slug.
     * @return array|null
     */
    public static function get_edition($product_slug)
    {
        $editions = [
            [
                'index' => 0,
                'slug'  => 'business',
                'name'  => 'MemberPress Business',
            ],
            [
                'index' => 1,
                'slug'  => 'memberpress-basic',
                'name'  => 'MemberPress Basic',
            ],
            [
                'index' => 2,
                'slug'  => 'memberpress-plus',
                'name'  => 'MemberPress Plus',
            ],
            [
                'index' => 3,
                'slug'  => 'memberpress-plus-2',
                'name'  => 'MemberPress Plus',
            ],
            [
                'index' => 4,
                'slug'  => 'developer',
                'name'  => 'MemberPress Developer',
            ],
            [
                'index' => 5,
                'slug'  => 'memberpress-pro',
                'name'  => 'MemberPress Pro',
            ],
            [
                'index' => 6,
                'slug'  => 'memberpress-pro-5',
                'name'  => 'MemberPress Pro',
            ],
            [
                'index' => 7,
                'slug'  => 'memberpress-reseller',
                'name'  => 'MemberPress Reseller',
            ],
            [
                'index' => 8,
                'slug'  => 'memberpress-oem',
                'name'  => 'MemberPress OEM',
            ],
            [
                'index' => 9,
                'slug'  => 'memberpress-elite',
                'name'  => 'MemberPress Elite',
            ],
        ];

        if (preg_match('/^memberpress-reseller-.+$/', $product_slug)) {
            $editions[7]['slug'] = $product_slug;
        }

        foreach ($editions as $edition) {
            if ($product_slug == $edition['slug']) {
                return $edition;
            }
        }

        return null;
    }

    /**
     * Is the installed edition of MemberPress different from the edition in the license?
     *
     * @return array|false An array containing the installed edition and license edition data, false if the correct edition is installed
     */
    public static function is_incorrect_edition_installed()
    {
        $license              = get_site_transient('mepr_license_info');
        $license_product_slug = !empty($license) && !empty($license['product_slug']) ? $license['product_slug'] : '';

        if (
            empty($license_product_slug) ||
            empty(MEPR_EDITION) ||
            $license_product_slug == MEPR_EDITION ||
            !current_user_can('update_plugins') ||
            @is_dir(MEPR_PATH . '/.git')
        ) {
            return false;
        }

        $installed_edition = self::get_edition(MEPR_EDITION);
        $license_edition   = self::get_edition($license_product_slug);

        if (!is_array($installed_edition) || !is_array($license_edition)) {
            return false;
        }

        return [
            'installed' => $installed_edition,
            'license'   => $license_edition,
        ];
    }

    /**
     * Is the given product slug a Pro edition of MemberPress?
     *
     * @param  string $product_slug The product slug.
     * @return boolean
     */
    public static function is_pro_edition($product_slug)
    {
        if (empty($product_slug)) {
            return false;
        }

        return in_array($product_slug, ['memberpress-pro', 'memberpress-pro-5'], true) || MeprUtils::is_oem_edition($product_slug);
    }

    /**
     * Is the given product slug an OEM/reseller edition of MemberPress?
     *
     * @param  string $product_slug The product slug.
     * @return boolean
     */
    public static function is_oem_edition($product_slug)
    {
        if (empty($product_slug)) {
            return false;
        }

        return $product_slug == 'memberpress-oem' || preg_match('/^memberpress-reseller-.+$/', $product_slug);
    }

    /**
     * Determines whether or not the provided gateway is connected.
     *
     * @param  object           $gateway The gateway object.
     * @param  MeprProduct|null $product The product being purchased.
     * @return boolean
     */
    public static function is_gateway_connected($gateway, MeprProduct $product = null)
    {
        if (!is_object($gateway) || !isset($gateway->key)) {
            return false;
        }

        // Payment gateways such as Authorize.net and PayPal Standard require fields to be filled out,
        // so we won't worry about validating them here.
        switch ($gateway->key) {
            case 'stripe':
                return (MeprStripeGateway::is_stripe_connect($gateway->id) || MeprStripeGateway::keys_are_set($gateway->id));
            case 'paypalcommerce':
                return ($gateway->is_paypal_connected() || $gateway->is_paypal_connected_live());
            case 'square':
                return $gateway instanceof MeprSquareGateway && $gateway->is_usable($product);
            default:
                return true;
        }
    }

    /**
     * Returns the minimum charge amount for the currently configured currency, or false if there is no minimum.
     *
     * We are aligning with the Stripe minimum charge amounts from:
     * https://stripe.com/docs/currencies#minimum-and-maximum-charge-amounts
     *
     * @return float|integer|false
     */
    public static function get_minimum_amount()
    {
        static $minimum_amount;

        if ($minimum_amount === null) {
            $mepr_options  = MeprOptions::fetch();
            $currency_code = strtoupper(MeprHooks::apply_filters('mepr_minimum_charge_currency', $mepr_options->currency_code));

            $minimums       = require MEPR_DATA_PATH . '/minimum_charge_amounts.php';
            $minimum_amount = isset($minimums[$currency_code]) ? $minimums[$currency_code] : false;
        }

        return $minimum_amount;
    }

    /**
     * Returns the minimum amount if the given amount is above zero and below the minimum charge amount.
     *
     * @param  string|float|integer $amount The amount to round.
     * @return string|float|integer
     */
    public static function maybe_round_to_minimum_amount($amount)
    {
        if ($amount > 0) {
            $minimum_amount = self::get_minimum_amount();

            if ($minimum_amount && $amount < $minimum_amount) {
                $amount = $minimum_amount;
            }
        }

        return $amount;
    }

    /**
     * Get the HTML for the 'NEW' badge
     *
     * @return string
     */
    public static function new_badge()
    {
        return sprintf('<span class="mepr-new-badge">%s</span>', esc_html__('NEW', 'memberpress'));
    }

    /**
     * Performs a case-sensitive check indicating if needle is
     * contained in haystack.
     *
     * @param  string $haystack The string to search in.
     * @param  string $needle   The substring to search for in the `$haystack`.
     * @return boolean True if `$needle` is in `$haystack`, otherwise false.
     */
    public static function str_contains($haystack, $needle)
    {
        if ('' === $needle) {
            return true;
        }

        return false !== strpos($haystack, $needle);
    }

    /**
     * Is the given product slug an Elite edition of MemberPress?
     *
     * @param  string $product_slug The product slug.
     * @return boolean
     */
    public static function is_elite_edition($product_slug)
    {
        if (empty($product_slug)) {
            return false;
        }

        return in_array($product_slug, ['memberpress-elite'], true);
    }

    /**
     * Validate a JSON request
     *
     * @param string $nonce_action The nonce action to verify.
     */
    public static function validate_json_request($nonce_action)
    {
        if (!self::is_post_request()) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        if (!self::is_logged_in_and_an_admin()) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!check_ajax_referer($nonce_action, false, false)) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }
    }

    /**
     * Get the request data from a JSON request
     *
     * @param  string $nonce_action The nonce action to verify.
     * @return array
     */
    public static function get_json_request_data($nonce_action)
    {
        self::validate_json_request($nonce_action);

        if (!isset($_POST['data']) || !is_string($_POST['data'])) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        $data = json_decode(wp_unslash($_POST['data']), true);

        if (!is_array($data)) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        return $data;
    }

    /**
     * Formats the given content for display.
     *
     * Parses blocks, shortcodes and formats paragraphs.
     *
     * @param  string $content The content to format.
     * @return string The formatted content.
     */
    public static function format_content($content): string
    {
        return do_shortcode(shortcode_unautop(wpautop(do_blocks($content))));
    }

    /**
     * Sanitize the given name field value.
     *
     * @param  string $value The value to sanitize.
     * @return string
     */
    public static function sanitize_name_field($value): string
    {
        $value = sanitize_text_field($value);
        $value = preg_replace('/https?:\/\/\S+/', '', $value);

        return trim($value);
    }

    /**
     * Ensure the given number $x is between $min and $max inclusive.
     *
     * @param  mixed $x   The value to clamp.
     * @param  mixed $min The minimum value.
     * @param  mixed $max The maximum value.
     * @return mixed
     */
    public static function clamp($x, $min, $max)
    {
        return min(max($x, $min), $max);
    }

    /**
     * Encrypts a given value using the provided key.
     *
     * @param  string $value The plaintext value to be encrypted.
     * @param  string $key   The binary string key to be used for encryption.
     * @return string The encrypted value as a hexadecimal string.
     * @throws Random\RandomException|Exception If an appropriate source of randomness cannot be found.
     * @throws SodiumException If there is an error during sodium function calls.
     */
    public static function encrypt(string $value, string $key): string
    {
        $nonce      = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($value, $nonce, $key);

        return sodium_bin2hex($nonce . $ciphertext);
    }

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
    /**
     * Decrypts an encrypted value using the provided key.
     *
     * @param  string $value The encrypted value in hexadecimal format.
     * @param  string $key   The binary string key to use for decryption.
     * @return string The decrypted plaintext value.
     * @throws InvalidArgumentException If the given value is empty.
     * @throws UnexpectedValueException If the value failed to be decrypted.
     * @throws SodiumException If there is an error during decryption.
     */
    public static function decrypt(string $value, string $key): string
    {
        if ($value === '') {
            throw new InvalidArgumentException('Invalid value to decrypt.');
        }

        $value      = sodium_hex2bin($value);
        $nonce      = mb_substr($value, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($value, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        $plaintext  = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($plaintext === false) {
            throw new UnexpectedValueException('Failed to decrypt value.');
        }

        return $plaintext;
    }
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
}
