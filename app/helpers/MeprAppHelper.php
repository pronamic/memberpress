<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprAppHelper
{
    /**
     * Default allowed HTML tags for sanitization.
     *
     * @var array
     */
    private static $default_allowed_tags = null;

    /**
     * Displays an information tooltip.
     *
     * @param string $id    The ID of the tooltip.
     * @param string $title The title of the tooltip.
     * @param string $info  The information to display in the tooltip.
     */
    public static function info_tooltip($id, $title, $info)
    {
        ?>
        <span id="mepr-tooltip-<?php echo $id; ?>" class="mepr-tooltip">
      <span><i class="mp-icon mp-icon-info-circled mp-16"></i></span>
      <span class="mepr-data-title mepr-hidden"><?php echo $title; ?></span>
      <span class="mepr-data-info mepr-hidden"><?php echo $info; ?></span>
    </span>
        <?php
    }

    /**
     * Formats a number as currency.
     *
     * @param float   $number          The number to format.
     * @param boolean $show_symbol     Whether to show the currency symbol.
     * @param boolean $free_str        Whether to display 'Free' for zero values.
     * @param boolean $truncate_zeroes Whether to truncate trailing zeroes.
     *
     * @return string The formatted currency string.
     */
    public static function format_currency($number, $show_symbol = true, $free_str = true, $truncate_zeroes = false)
    {
        global $wp_locale;
        $mepr_options = MeprOptions::fetch();
        $dp           = $wp_locale->number_format['decimal_point'];

        if ((float) $number > 0.00 || !$free_str) {
            // Do decimal and 0's handling before adding symbol.
            if (MeprUtils::is_zero_decimal_currency()) {
                $rstr = (string) MeprUtils::format_currency_float((float) $number, 0);
            } else {
                $rstr = (string) MeprUtils::format_currency_float((float) $number, 2);

                if ($truncate_zeroes) {
                    $rstr = preg_replace('/' . preg_quote($dp) . '00$/', '', $rstr);
                }
            }

            if ($show_symbol) {
                if ($rstr < 0) {
                    if (!$mepr_options->currency_symbol_after) {
                        $rstr = '(' . $mepr_options->currency_symbol . str_replace('-', '', $rstr) . ')';
                    } else {
                        $rstr = '(' . str_replace('-', '', $rstr) . $mepr_options->currency_symbol . ')';
                    }
                } else {
                    if (!$mepr_options->currency_symbol_after) {
                        $rstr = $mepr_options->currency_symbol . $rstr;
                    } else {
                        $rstr = $rstr . $mepr_options->currency_symbol;
                    }
                }
            }
        } else {
            $rstr = __('Free', 'memberpress');
        }

        return MeprHooks::apply_filters('mepr_format_currency', $rstr, $number, $show_symbol);
    }

    /**
     * Automatically adds a page with the given name and content.
     *
     * @param string $page_name The name of the page.
     * @param string $content   The content of the page.
     *
     * @return integer|WP
     */
    public static function auto_add_page($page_name, $content = '')
    {
        return wp_insert_post([
            'post_title'     => $page_name,
            'post_content'   => $content,
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'comment_status' => 'closed',
        ]);
    }

    /**
     * Formats a number with optional decimals and truncation.
     *
     * @param float   $number          The number to format.
     * @param boolean $show_decimals   Whether to show decimals.
     * @param boolean $truncate_zeroes Whether to truncate trailing zeroes.
     *
     * @return string The formatted number.
     */
    public static function format_number($number, $show_decimals = false, $truncate_zeroes = false)
    {
        global $wp_locale;

        $decimal_point = $wp_locale->number_format['decimal_point'];
        $thousands_sep = $wp_locale->number_format['thousands_sep'];

        $rstr = 0;

        if ((float) $number > 0.00) {
            if ($show_decimals) {
                $rstr = (string) number_format((float) $number, 2, $decimal_point, $thousands_sep);
            } else {
                $rstr = (string) number_format((float) $number, 0, $decimal_point, $thousands_sep);
            }

            if ($show_decimals && $truncate_zeroes) {
                $rstr = preg_replace('/' . preg_quote($decimal_point) . '00$/', '', $rstr);
            }
        }

        return $rstr;
    }

    /**
     * Formats a date based on the user's WordPress locale settings.
     * NOTE - This should only be used in views/emails as it modifies UTC
     * timestamps to show in the users WP locale settings instead of in UTC
     *
     * @param string $datetime The date/time string to format.
     * @param string $default  The default value to return if the date is invalid.
     * @param string $format   The date format to use.
     *
     * @return string The formatted date.
     */
    public static function format_date($datetime, $default = null, $format = null)
    {
        if (is_null($default)) {
            $default = __('Unknown', 'memberpress');
        }
        if (is_null($format)) {
            $format = get_option('date_format'); // Gets WP date format option.
        }
        if (empty($datetime) or preg_match('#^0000-00-00#', $datetime)) {
            return $default;
        }

        $ts     = strtotime($datetime);
        $offset = get_option('gmt_offset'); // Gets WP timezone offset option.

        // Return a translatable date in the WP locale options.
        return date_i18n($format, ($ts + MeprUtils::hours($offset)), false);
    }

    /**
     * Formats a date in UTC timezone.
     * Right now - just used on the new/edit txn pages
     *
     * @param string $utc_datetime The UTC date/time string to format.
     * @param string $default      The default value to return if the date is invalid.
     * @param string $format       The date format to use.
     *
     * @return string The formatted date.
     */
    public static function format_date_utc($utc_datetime, $default = null, $format = null)
    {
        if (is_null($default)) {
            $default = __('Unknown', 'memberpress');
        }
        if (is_null($format)) { // Gets WP date format option.
            $format = get_option('date_format');
        }
        if (empty($utc_datetime) or preg_match('#^0000-00-00#', $utc_datetime)) {
            return $default;
        }

        $ts = strtotime($utc_datetime);

        return date_i18n($format, $ts, true); // Return a translatable date in the WP locale options.
    }

    /**
     * Renders a dropdown for page templates.
     *
     * @param string $field_name  The name of the field.
     * @param string $field_value The selected value.
     */
    public static function page_template_dropdown($field_name, $field_value = null)
    {
        $templates = get_page_templates();
        ?>
        <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>"
                class="mepr-dropdown mepr-page-templates-dropdown">
            <?php
            foreach ($templates as $template_name => $template_filename) {
                ?>
                <option
                    value="<?php echo $template_filename; ?>" <?php selected($template_filename, $field_value); ?>><?php echo $template_name; ?>
                    &nbsp;
                </option>
                <?php
            }
            ?>
        </select>
        <?php
    }

    /**
     * Converts a status to a human-readable string.
     *
     * @param string $status The status to convert.
     * @param string $type   The type of status ('transaction' or 'subscription').
     *
     * @return string The human-readable status.
     */
    public static function human_readable_status($status, $type = 'transaction')
    {
        if ($type == 'transaction') {
            switch ($status) {
                case MeprTransaction::$pending_str:
                    return __('Pending', 'memberpress');
                case MeprTransaction::$failed_str:
                    return __('Failed', 'memberpress');
                case MeprTransaction::$complete_str:
                    return __('Complete', 'memberpress');
                case MeprTransaction::$refunded_str:
                    return __('Refunded', 'memberpress');
                default:
                    return __('Unknown', 'memberpress');
            }
        } elseif ($type == 'subscription') {
            switch ($status) {
                case MeprSubscription::$pending_str:
                    return __('Pending', 'memberpress');
                case MeprSubscription::$active_str:
                    return __('Enabled', 'memberpress');
                case MeprSubscription::$cancelled_str:
                    return __('Stopped', 'memberpress');
                case MeprSubscription::$suspended_str:
                    return __('Paused', 'memberpress');
                default:
                    return __('Unknown', 'memberpress');
            }
        }
    }

    /**
     * Determines the subscription status for a template.
     *
     * @param object $sub The subscription object.
     *
     * @return string The subscription status.
     */
    public static function pro_template_sub_status($sub)
    {
        $type = $sub->sub_type;
        if ($type == 'transaction') {
            if (strpos($sub->active, 'mepr-active') !== false) {
                return 'active';
            } else {
                return 'canceled';
            }
        } elseif ($type == 'subscription') {
            $status = $sub->status;

            if ('active' === $status) {
                $subscription = new MeprSubscription($sub->id);
                $txns         = $subscription->transactions();
                if ($subscription->latest_txn_failed() || strpos($sub->active, 'No') !== false) {
                    $status = 'lapsed';
                }
            }

            switch ($status) {
                case MeprSubscription::$pending_str:
                    return 'pending';
                case MeprSubscription::$active_str:
                    return 'active';
                case MeprSubscription::$cancelled_str:
                    return 'canceled';
                case MeprSubscription::$suspended_str:
                    return 'paused';
                case 'lapsed':
                    return 'lapsed';
                default:
                    return 'unknown';
            }
        }
    }

    /**
     * Determines the transaction status for a template.
     *
     * @param object $txn The transaction object.
     *
     * @return string The transaction status.
     */
    public static function pro_template_txn_status($txn)
    {
        switch ($txn->status) {
            case MeprTransaction::$complete_str:
                return 'complete';
            case MeprTransaction::$refunded_str:
                return 'refunded';
            default:
                return 'unknown';
        }
    }

    /**
     * Formats a price string with optional coupon and proration.
     *
     * @param object  $obj           The object containing price details.
     * @param float   $price         The price amount.
     * @param boolean $show_symbol   Whether to show the currency symbol.
     * @param string  $coupon_code   The coupon code, if any.
     * @param boolean $show_prorated Whether to show proration details.
     *
     * @return string The formatted price string.
     */
    public static function format_price_string($obj, $price = 0.00, $show_symbol = true, $coupon_code = null, $show_prorated = true)
    {
        global $wp_locale;

        $user         = MeprUtils::get_currentuserinfo();
        $regex_dp     = preg_quote($wp_locale->number_format['decimal_point'], '#');
        $mepr_options = MeprOptions::fetch();
        $coupon       = false;

        if (empty($coupon_code)) {
            $coupon_code = null;
        } else {
            $coupon = MeprCoupon::get_one_from_code($coupon_code, true);
        }

        if ($obj instanceof MeprTransaction || $obj instanceof MeprSubscription) {
            $product = $obj->product();
        } elseif ($obj instanceof MeprProduct) {
            $product = $obj;
        } else {
            $product = false;
        }

        $proration_single_cycle = false;
        if ($obj instanceof MeprSubscription && $obj->prorated_trial && $obj->trial && $obj->limit_cycles && 1 == $obj->limit_cycles_num) {
            $proration_single_cycle = true;
        }

        $tax_str = '';

        if (!empty($obj->tax_rate) && $obj->tax_rate > 0.00) {
            // $tax_rate = $obj->tax_rate;
            $tax_amount = preg_replace("#([{$regex_dp}]000?)([^0-9]*)$#", '$2', $obj->tax_amount);
            $tax_desc   = $obj->tax_desc;
            // $tax_str = ' +'.MeprUtils::format_float($tax_rate).'% '.$tax_desc;
            $tax_str = _x(' (price includes taxes)', 'ui', 'memberpress');
            if ($tax_amount <= 0) {
                $tax_str = '';
            }
            $price = $price + $tax_amount;
        }

        // Just truncate the zeros if it's an even dollar amount.
        $fprice = MeprAppHelper::format_currency($price, $show_symbol);
        $fprice = preg_replace("#([{$regex_dp}]000?)([^0-9]*)$#", '$2', (string) $fprice);

        $period          = isset($obj->period) ? (int) $obj->period : 1;
        $period_type     = isset($obj->period_type) ? $obj->period_type : 'lifetime';
        $period_type_str = MeprUtils::period_type_name($period_type, $period);

        if ((float) $price <= 0.00) {
            if (
                $period_type != 'lifetime' && !empty($coupon) &&
                (($coupon->discount_type == 'percent' && $coupon->discount_amount == 100) or ($coupon->discount_mode == 'standard' && $obj->trial == false))
            ) {
                $price_str = __('Free forever', 'memberpress');
            } elseif ($period_type == 'lifetime') {
                $price_str = __('Free', 'memberpress');
            } elseif ($period == 1) {
                $price_str = sprintf(
                    // Translators: %s: period type.
                    __('Free for a %s', 'memberpress'),
                    $period_type_str
                );
            } else {
                $price_str = sprintf(
                    // Translators: %1$d: period, %2$s: period type.
                    __('Free for %1$d %2$s', 'memberpress'),
                    $period,
                    $period_type_str
                );
            }
        } elseif ($period_type == 'lifetime') {
            $price_str = $fprice;
            if (
                $show_prorated && $obj instanceof MeprProduct &&
                $mepr_options->pro_rated_upgrades && $obj->is_upgrade_or_downgrade()
            ) {
                $group        = $obj->group();
                $lt           = false;
                $old_subscr   = $user->subscription_in_group($group->ID);
                $old_lifetime = $user->lifetime_subscription_in_group($group->ID);

                if ($old_subscr !== false) {
                    $lt = $old_subscr->latest_txn();
                }

                if ($old_lifetime !== false) {
                    $lt = $old_lifetime;
                }

                // Don't show prorated if the old amount is 0.00.
                if ($lt === false || MeprUtils::format_float($lt->amount) > 0.00) {
                    $price_str .= __(' (prorated)', 'memberpress');
                }
            }
        } else {
            if ($obj->trial) {
                if ($obj->trial_amount > 0.00) {
                    $trial_str = MeprAppHelper::format_currency($obj->trial_total > 0.00 ? $obj->trial_total : $obj->trial_amount, $show_symbol);
                    $trial_str = preg_replace("#([{$regex_dp}]000?)([^0-9]*)$#", '$2', (string) $trial_str);
                } else {
                    $trial_str = __('free', 'memberpress');
                }

                if (
                    ($obj instanceof MeprSubscription and $obj->prorated_trial) or
                    ($obj instanceof MeprProduct and $mepr_options->pro_rated_upgrades and $obj->is_upgrade_or_downgrade())
                ) {
                    if ($obj instanceof MeprProduct) {
                        $usr = MeprUtils::get_currentuserinfo();
                        $grp = $obj->group();

                        $old_sub     = $usr->subscription_in_group($grp->ID);
                        $upgrade_str = ($show_prorated && $old_sub) ? __(' (proration)', 'memberpress') : '';
                    } elseif ($show_prorated) {
                        $upgrade_str = __(' (proration)', 'memberpress');
                    } else {
                        $upgrade_str = '';
                    }
                } else {
                    $upgrade_str = '';
                }

                if ($obj->trial_days > 0) {
                    list($conv_trial_type, $conv_trial_count) = MeprUtils::period_type_from_days($obj->trial_days);

                    $conv_trial_type_str = MeprUtils::period_type_name($conv_trial_type, $conv_trial_count);

                    // If proration and max number of payments is 1.
                    if ($proration_single_cycle) {
                        $sub_str = (
                            // Translators: %1$s: trial price, %2$s: trial period, %3$s: upgrade price, %4$s: upgrade period.
                            __('%1$s %2$s for %3$s%4$s ', 'memberpress')
                        );
                    } else {
                        $sub_str = (
                            // Translators: %1$s: trial price, %2$s: trial period, %3$s: upgrade price, %4$s: upgrade period.
                            __('%1$s %2$s for %3$s%4$s then ', 'memberpress')
                        );
                    }

                    $price_str = sprintf($sub_str, $conv_trial_count, $conv_trial_type_str, $trial_str, $upgrade_str);
                } else {
                    $sub_str   = (
                        // Translators: %1$s: trial price, %2$s: upgrade price.
                        __('%1$s%2$s once and ', 'memberpress')
                    );
                    $price_str = sprintf($sub_str, $trial_str, $upgrade_str);
                }
            } else {
                $price_str = '';
            }

            if ($obj->limit_cycles and $obj->limit_cycles_num == 1) {
                if (!$proration_single_cycle) {
                    $price_str .= $fprice;
                    if ($obj->limit_cycles_action == 'expire') {
                        $price_str .= sprintf(
                            // Translators: %1$d: period, %2$s: period type.
                            __(' for %1$d %2$s', 'memberpress'),
                            $period,
                            $period_type_str
                        );
                    }
                }
            } elseif ($obj->limit_cycles) { // Prefix with payments count.
                $price_str .= sprintf(
                    // Translators: %1$d: number of payments.
                    _n(
                        '%1$d payment of ',
                        '%1$d payments of ',
                        $obj->limit_cycles_num,
                        'memberpress'
                    ),
                    $obj->limit_cycles_num
                );
            }

            if (!$obj->limit_cycles or ($obj->limit_cycles and $obj->limit_cycles_num > 1)) {
                if ($period == 1) {
                    $price_str .= sprintf(
                        // Translators: %1$s: price, %2$s: period type.
                        __('%1$s / %2$s', 'memberpress'),
                        $fprice,
                        $period_type_str
                    );
                } else {
                    $price_str .= sprintf(
                        // Translators: %1$s: price, %2$d: period, %3$s: period type.
                        __('%1$s / %2$d %3$s', 'memberpress'),
                        $fprice,
                        $period,
                        $period_type_str
                    );
                }
            }
        }

        if ($period_type == 'lifetime') {
            if ($obj->expire_type == 'delay') {
                $expire_str = MeprUtils::period_type_name($obj->expire_unit, $obj->expire_after);
                $price_str .= sprintf(
                    // Translators: %1$d: expiration period, %2$s: expiration unit.
                    __(' for %1$d %2$s', 'memberpress'),
                    $obj->expire_after,
                    $expire_str
                );
            } elseif ($obj->expire_type == 'fixed') {
                $now = time();

                if ($obj instanceof MeprTransaction || $obj instanceof MeprSubscription) {
                    $expire_ts = strtotime($obj->expire_fixed);
                } else {
                    $expire_ts = strtotime($product->expire_fixed);

                    // Make sure we adjust the year if the membership is a renewable type and the user forgot to bump up the year.
                    if ($product->allow_renewal) {
                        while ($now > $expire_ts) { // Add a year until $now < expiration date.
                            $expire_ts += MeprUtils::years(1);
                        }
                    }
                }

                $expire_str = date_i18n(get_option('date_format'), $expire_ts, true);

                if (!$product->is_renewal()) { // Just hide this if it's a renewal.
                    $price_str .= sprintf(
                        // Translators: %s: expiration date.
                        __(' for access until %s', 'memberpress'),
                        $expire_str
                    );
                }
            }
        }

        if (isset($tax_str) && !empty($tax_str) && $price > 0) {
            $price_str = $price_str . $tax_str;
        }

        if (!empty($coupon)) {
            $price_str .= sprintf(
                // Translators: %s: coupon code.
                __(' with coupon %s', 'memberpress'),
                $coupon_code
            );
        }

        return MeprHooks::apply_filters('mepr-price-string', $price_str, $obj, $show_symbol);
    }

    /**
     * Displays available email templates.
     *
     * @param string $etype The email type.
     * @param array  $args  Additional arguments.
     */
    public static function display_emails($etype = 'MeprBaseEmail', $args = [])
    {
        ?>
        <div class="mepr-emails-wrap"><?php

        $emails = apply_filters('mepr_display_emails', MeprEmailFactory::all($etype, $args), $etype, $args);

        foreach ($emails as $email) {
            if ($email->show_form) {
                $email->display_form();
            }
        }

        ?></div><?php
    }

    /**
     * Renders a CSV file for download.
     *
     * @param array  $rows     The data rows.
     * @param array  $header   The header row.
     * @param string $filename The filename for the CSV.
     *
     * @return void
     */
    public static function render_csv($rows, $header = [], $filename = null)
    {
        $filename = (is_null($filename) ? uniqid() . '.csv' : $filename);

        // Output headers so that the file is downloaded rather than displayed.
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename={$filename}");

        // Create a file pointer connected to the output stream.
        $output = fopen('php://output', 'w');

        // Output the column headings.
        fputcsv($output, $header);

        // Loop over the rows, outputting them.
        foreach ($rows as $row) {
            fputcsv($output, (array) $row);
        }

        // Close the file and exit.
        fclose($output);
        exit;
    }

    /**
     * Renders a dropdown for countries.
     *
     * @param string  $field_key     The field key.
     * @param string  $value         The selected value.
     * @param string  $classes       Additional CSS classes.
     * @param string  $required_attr Required attribute.
     * @param boolean $geolocate     Whether to geolocate.
     * @param string  $unique_suffix A unique suffix for the field.
     *
     * @return string The HTML for the dropdown.
     */
    public static function countries_dropdown($field_key, $value = null, $classes = '', $required_attr = '', $geolocate = true, $unique_suffix = '')
    {
        $value = (($geolocate && empty($value)) ? '' : $value);

        ob_start();
        ?>
        <select name="<?php echo $field_key; ?>" id="<?php echo $field_key . $unique_suffix; ?>"
                class="<?php echo $classes; ?> mepr-countries-dropdown mepr-form-input mepr-select-field" <?php echo $required_attr; ?>>
            <option value=""><?php _e('-- Select Country --', 'memberpress'); ?></option>
            <?php foreach (MeprUtils::countries() as $opt_key => $opt_val) : ?>
                <option
                    value="<?php echo $opt_key; ?>" <?php selected(esc_attr($opt_key), esc_attr($value)); ?>><?php echo stripslashes($opt_val); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders a dropdown for memberships.
     * NOTE: In order to use this method you must also enqueue the i18n.js along with the localize script array.
     *
     * @param string  $field_key     The field key.
     * @param string  $value         The selected value.
     * @param string  $classes       Additional CSS classes.
     * @param string  $required_attr Required attribute.
     * @param boolean $geolocate     Whether to geolocate.
     * @param string  $unique_suffix A unique suffix for the field.
     *
     * @return string The HTML for the dropdown.
     */
    public static function states_dropdown($field_key, $value = null, $classes = '', $required_attr = '', $geolocate = true, $unique_suffix = '')
    {
        $value = (($geolocate && empty($value)) ? '' : $value);

        ob_start();
        ?>
        <select name="" data-fieldname="<?php echo $field_key; ?>" data-value="<?php echo esc_attr($value); ?>"
                id="<?php echo $field_key . $unique_suffix; ?>"
                class="<?php echo $classes; ?> mepr-hidden mepr-states-dropdown mepr-form-input mepr-select-field"
                style="display: none;" <?php echo $required_attr; ?>>
        </select>
        <?php // Make sure the text box isn't hidden ... at the very least we need to see something! ?>
        <input type="text" name="<?php echo $field_key; ?>" data-fieldname="<?php echo $field_key; ?>"
               data-value="<?php echo esc_attr($value); ?>" id="<?php echo $field_key . $unique_suffix; ?>"
               class="<?php echo $classes; ?> mepr-states-text mepr-form-input"
               value="<?php echo esc_attr($value); ?>" <?php echo $required_attr; ?>/>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders a dropdown for memberships.
     *
     * @param string $field_name  The field name.
     * @param array  $memberships The memberships to include.
     * @param string $classes     Additional CSS classes.
     *
     * @return void
     */
    public static function memberships_dropdown($field_name, $memberships = [], $classes = '')
    {
        $memberships = is_array($memberships) ? $memberships : [];
        $contents    = [];

        $posts = MeprCptModel::all('MeprProduct', false, [
            'orderby' => 'title',
            'order'   => 'ASC',
        ]);

        foreach ($posts as $post) {
            $contents[$post->ID] = $post->post_title;
        }
        ?>
        <select name="<?php echo esc_attr($field_name); ?>" class="<?php echo esc_attr($classes); ?>">
            <?php foreach ($contents as $curr_type => $curr_label) : ?>
                <option
                    value="<?php echo esc_attr($curr_type); ?>" <?php selected(in_array($curr_type, $memberships)); ?>><?php echo esc_html($curr_label); ?>
                    &nbsp;
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Renders a dropdown for roles.
     *
     * @param string $field_name The field name.
     * @param array  $roles      The roles to include.
     * @param string $classes    Additional CSS classes.
     *
     * @return void
     */
    public static function roles_dropdown($field_name, $roles = [], $classes = '')
    {
        $roles    = is_array($roles) ? $roles : [];
        $contents = [];

        $wp_roles = wp_roles();

        foreach ($wp_roles->roles as $key => $r) {
            $contents[$key] = $r['name'];
        }
        ?>
        <select name="<?php echo esc_attr($field_name); ?>" class="<?php echo esc_attr($classes); ?>">
            <?php foreach ($contents as $curr_type => $curr_label) : ?>
                <option
                    value="<?php echo esc_attr($curr_type); ?>" <?php selected(in_array($curr_type, $roles)); ?>><?php echo esc_html($curr_label); ?>
                    &nbsp;
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Generates a link for exporting a table as CSV.
     *
     * @param string  $action       The action to perform.
     * @param string  $nonce_action The nonce action.
     * @param string  $nonce_name   The nonce name.
     * @param integer $itemcount    The number of items.
     * @param boolean $all          Whether to export all items.
     *
     * @return void
     */
    public static function export_table_link($action, $nonce_action, $nonce_name, $itemcount, $all = false)
    {
        $params = ['action' => $action];

        if ($all) {
            $params['all'] = 1;
            $label         = (
                // Translators: %s: number of records.
                __('Export table as CSV (%s records)', 'memberpress')
            );
        } else {
            $label = (
                // Translators: %s: number of records.
                __('Export all as CSV (%s records)', 'memberpress')
            );
        }

        $export_link = MeprUtils::admin_url(
            'admin-ajax.php',
            [$nonce_action, $nonce_name],
            $params,
            true
        );

        ?>
        <a href="<?php echo esc_url($export_link); ?>"><?php printf($label, MeprAppHelper::format_number($itemcount)); ?></a>
        <?php
    }

    /**
     * Retrieves the privacy policy page link.
     *
     * @return string|false The permalink of the privacy policy page, or false if not set.
     */
    public static function privacy_policy_page_link()
    {
        $privacy_policy_page_id = get_option('wp_page_for_privacy_policy', false);
        if ($privacy_policy_page_id !== false) {
            return get_permalink($privacy_policy_page_id);
        }

        return false;
    }

    /**
     * Renders an input field with clipboard functionality.
     *
     * @param string $value         The value to display.
     * @param string $input_classes Additional CSS classes for the input.
     * @param string $icon_classes  Additional CSS classes for the icon.
     *
     * @return void
     */
    public static function clipboard_input($value, $input_classes = '', $icon_classes = '')
    {
        ?>
        <input type="text" class="mepr-clipboard-input <?php echo $input_classes; ?>" onfocus="this.select();"
               onclick="this.select();" readonly="true" value="<?php echo $value; ?>" />
        <span class="mepr-clipboard <?php echo $icon_classes; ?>">
        <i class="mp-clipboardjs mp-icon mp-icon-clipboard mp-16" data-clipboard-text="<?php echo $value; ?>"></i>
      </span>
        <?php
    }

    /**
     * Converts a status to a human-readable string.
     *
     * @param string $status The status to convert.
     *
     * @return string The human-readable status.
     */
    public static function status_human_readable($status)
    {
        $status = strtolower($status);
        $map    = [
            'active'   => __('Active', 'memberpress'),
            'canceled' => __('Canceled', 'memberpress'),
            'lapsed'   => __('Lapsed', 'memberpress'),
            'pending'  => __('Pending', 'memberpress'),
            'paused'   => __('Paused', 'memberpress'),
            'unknown'  => __('Unknown', 'memberpress'),
        ];

        if (isset($map[$status])) {
            return $map[$status];
        } else {
            return __('Unknown', 'memberpress');
        }
    }

    /**
     * Checks if the coaching feature is enabled.
     *
     * @return boolean True if coaching is enabled, false otherwise.
     */
    public static function is_coaching_enabled()
    {
        $slug = 'memberpress-coachkit/main.php';

        return is_plugin_active($slug);
    }

    /**
     * Checks if a block template of a post contains a specific block.
     *
     * This function searches the content of a block template associated with a post
     * to determine if the specified block is present within it.
     *
     * @param  string $block_name The name of the block to check for.
     *                           If the block name does not include a namespace, 'memberpress/' is prepended.
     * @return boolean True if the block is found in the block template, false otherwise.
     */
    public static function block_template_has_block($block_name)
    {
        global $_wp_current_template_content;

        if (null === $_wp_current_template_content || !$_wp_current_template_content) {
            return false;
        }

        if (!MeprUtils::str_contains($block_name, '/')) {
            $block_name = 'memberpress/' . $block_name;
        }

        $has_block = MeprUtils::str_contains($_wp_current_template_content, '<!-- wp:' . $block_name . ' ');

        return $has_block;
    }

    /**
     * Checks if a block is present in the current content or block template.
     *
     * This function checks if the specified block is either present in the content of
     * the post or is defined in the block template associated with the post.
     *
     * @param  string $block_name The name of the block to check for.
     * @return boolean True if the block is found in the content or block template, false otherwise.
     */
    public static function has_block($block_name)
    {
        return has_block($block_name) || self::block_template_has_block($block_name);
    }

    /**
     * Check if the current page is a memberpress page.
     *
     * @param object $post_object The post object.
     *
     * @return boolean
     */
    public static function is_memberpress_page($post_object = '')
    {
        if (!$post_object instanceof WP_Post) {
            global $post;
            $post_object = $post;
        }
        $is_memberpress_page = MeprUser::is_account_page($post_object) ||
            MeprUser::is_login_page($post_object) ||
            MeprProduct::is_product_page($post_object) ||
            MeprGroup::is_group_page($post_object) ||
            self::is_thankyou_page($post_object);

        $check_mp_course_pages = MeprHooks::apply_filters(
            'mepr_check_mp_course_pages',
            true,
            $is_memberpress_page,
            $post_object
        );

        if (class_exists('memberpress\courses\helpers\Courses') && $check_mp_course_pages && !$is_memberpress_page) {
            $is_memberpress_page = memberpress\courses\helpers\Courses::is_a_course($post_object) ||
                memberpress\courses\helpers\Lessons::is_a_lesson($post_object);
        }

        return MeprHooks::apply_filters('mepr_is_memberpress_page', $is_memberpress_page, $post_object);
    }

    /**
     * Checks if a page is the thank you page.
     *
     * @param WP_Post $post The post object to check.
     *
     * @return boolean True if the page is the thank you page, false otherwise.
     */
    public static function is_thankyou_page($post)
    {
        $mepr_options     = MeprOptions::fetch();
        $is_thankyou_page = $post instanceof WP_Post && $post->ID == $mepr_options->thankyou_page_id;

        return MeprHooks::apply_filters('mepr_is_thankyou_page', $is_thankyou_page, $post);
    }

    /**
     * Sanitizes content using allowed HTML tags.
     *
     * @param string $content      The content to sanitize.
     * @param array  $allowed_tags The allowed HTML tags.
     *
     * @return string The sanitized content.
     */
    public static function wp_kses($content, $allowed_tags = [])
    {
        if (!is_array($allowed_tags) || empty($allowed_tags)) {
            if (self::$default_allowed_tags === null) {
                self::$default_allowed_tags = [
                    'a'          => [
                        'href'        => [],
                        'rel'         => [],
                        'title'       => [],
                        'target'      => [],
                        'aria-label'  => [],
                        'aria-hidden' => [],
                        'role'        => [],
                    ],
                    'abbr'       => [
                        'title' => [],
                    ],
                    'b'          => [],
                    'blockquote' => [
                        'cite' => [],
                    ],
                    'cite'       => [
                        'title' => [],
                    ],
                    'code'       => [],
                    'del'        => [
                        'datetime' => [],
                        'title'    => [],
                    ],
                    'dd'         => [],
                    'div'        => [
                        'title'       => [],
                        'style'       => [],
                        'aria-label'  => [],
                        'aria-hidden' => [],
                        'role'        => [],
                    ],
                    'dl'         => [],
                    'dt'         => [],
                    'em'         => [],
                    'h1'         => [],
                    'h2'         => [],
                    'h3'         => [],
                    'h4'         => [],
                    'h5'         => [],
                    'h6'         => [],
                    'i'          => [
                        'aria-hidden' => [],
                    ],
                    'img'        => [
                        'alt'     => [],
                        'height'  => [],
                        'src'     => [],
                        'width'   => [],
                        'loading' => [],
                        'sizes'   => [],
                        'srcset'  => [],
                    ],
                    'form'       => [
                        'name'   => [],
                        'action' => [],
                        'method' => [],
                        'aria-label' => [],
                    ],
                    'li'         => [],
                    'ol'         => [],
                    'p'          => [],
                    'q'          => [
                        'cite'  => [],
                        'title' => [],
                    ],
                    'span'       => [
                        'title'       => [],
                        'style'       => [],
                        'aria-label'  => [],
                        'aria-hidden' => [],
                    ],
                    'strike'     => [],
                    'strong'     => [],
                    'ul'         => [],
                    'input'      => [
                        'type'          => [],
                        'name'          => [],
                        'value'         => [],
                        'placeholder'   => [],
                        'readonly'      => [],
                        'disabled'      => [],
                        'checked'       => [],
                        'aria-label'    => [],
                        'aria-required' => [],
                        'aria-invalid'  => [],
                        'autocomplete'  => [],
                    ],
                    'button'     => [
                        'type'          => [],
                        'data-toggle'   => [],
                        'value'         => [],
                        'aria-label'    => [],
                        'aria-pressed'  => [],
                        'aria-expanded' => [],
                        'readonly'      => [],
                        'disabled'      => [],
                    ],
                    'label'      => [
                        'for'    => [],
                    ],
                    'nav'        => [
                        'aria-label' => [],
                        'role'       => [],
                    ],
                    'section'    => [
                        'aria-labelledby' => [],
                    ],
                    'table'      => [
                        'role'   => [],
                    ],
                    'thead'      => [],
                    'tbody'      => [],
                    'tr'         => [],
                    'th'         => [
                        'scope' => [],
                    ],
                    'td'         => [],
                ];

                // Allow all global attributes.
                $global_attributes = [
                    'id'               => [],
                    'class'            => [],
                    'aria-describedby' => [],
                    'aria-details'     => [],
                    'aria-hidden'      => [],
                    'aria-labelledby'  => [],
                    'aria-label'       => [],
                    'role'             => [],
                    'tabindex'         => [],
                    'data-*'           => [], // Support for all data-* attributes.
                ];

                // Apply global attributes to all elements.
                foreach (self::$default_allowed_tags as $tag => $attributes) {
                    self::$default_allowed_tags[$tag] = array_merge($attributes, $global_attributes);
                }
            }

            $allowed_tags = self::$default_allowed_tags;
        }

        return wp_kses($content, $allowed_tags);
    }
}
