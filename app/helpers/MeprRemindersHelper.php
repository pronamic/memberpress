<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprRemindersHelper
{
    /**
     * Get email vars.
     *
     * @param  MeprReminder $reminder The reminder.
     * @return array
     */
    public static function get_email_vars($reminder = null)
    {
        $vars = [
            'reminder_id',
            'reminder_trigger_length',
            'reminder_trigger_interval',
            'reminder_trigger_timing',
            'reminder_trigger_event',
            'reminder_name',
            'reminder_description',
        ];

        // DEPRECATED.
        $params = MeprHooks::apply_filters('mepr_reminder_notification_vars', $vars, $reminder);

        return MeprHooks::apply_filters('mepr_reminder_email_vars', $vars, $reminder);
    }

    /**
     * Get email params.
     *
     * @param  MeprReminder $reminder The reminder.
     * @return array
     */
    public static function get_email_params($reminder)
    {
        $params = [
            'reminder_id'               => $reminder->ID,
            'reminder_trigger_length'   => $reminder->trigger_length,
            'reminder_trigger_interval' => $reminder->trigger_interval,
            'reminder_trigger_timing'   => $reminder->trigger_timing,
            'reminder_trigger_event'    => $reminder->trigger_event,
            'reminder_name'             => self::get_reminder_info($reminder, 'name'),
            'reminder_description'      => self::get_reminder_info($reminder, 'description'),
        ];

        // DEPRECATED.
        $params = MeprHooks::apply_filters('mepr_reminder_notification_params', $params, $reminder);

        return MeprHooks::apply_filters('mepr_reminder_email_params', $params, $reminder);
    }

    /**
     * Get reminder info.
     *
     * @param  MeprReminder $reminder The reminder.
     * @param  string       $field    The field.
     * @return array
     */
    public static function get_reminder_info($reminder, $field)
    {
        if (!in_array($field, ['name', 'description'])) {
            return false;
        }

        $lookup = [
            'sub-expires'      => [
                'before' => [
                    'name'        => __('Subscription Expiring', 'memberpress'),
                    'description' => sprintf(
                        // Translators: %1$d: trigger length, %2$s: trigger interval.
                        __('Subscription is expiring in %1$d %2$s', 'memberpress'),
                        $reminder->trigger_length,
                        $reminder->get_trigger_interval_str()
                    ),
                ],
                'after'  => [
                    'name'        => __('Subscription Expired', 'memberpress'),
                    'description' => sprintf(
                        // Translators: %1$d: trigger length, %2$s: trigger interval.
                        __('Subscription expired %1$d %2$s ago', 'memberpress'),
                        $reminder->trigger_length,
                        $reminder->get_trigger_interval_str()
                    ),
                ],
            ],
            'sub-renews'       => [
                'before' => [
                    'name'        => __('Subscription Renewing', 'memberpress'),
                    'description' => sprintf(
                        // Translators: %1$d: trigger length, %2$s: trigger interval.
                        __('Subscription is renewing in %1$d %2$s', 'memberpress'),
                        $reminder->trigger_length,
                        $reminder->get_trigger_interval_str()
                    ),
                ],
                'after'  => [
                    'name'        => __('Subscription Renewed', 'memberpress'),
                    'description' => sprintf(
                        // Translators: %1$d: trigger length, %2$s: trigger interval.
                        __('Subscription renewed %1$d %2$s ago', 'memberpress'),
                        $reminder->trigger_length,
                        $reminder->get_trigger_interval_str()
                    ),
                ],
            ],
            'cc-expires'       => [
                'before' => [
                    'name'        => __('Credit Card Expiring', 'memberpress'),
                    'description' => sprintf(
                        // Translators: %1$d: trigger length, %2$s: trigger interval.
                        __('Credit Card is Expiring in %1$d %2$s', 'memberpress'),
                        $reminder->trigger_length,
                        $reminder->get_trigger_interval_str()
                    ),
                ],
                'after'  => [
                    'name'        => __('Credit Card Expired', 'memberpress'),
                    'description' => sprintf(
                        // Translators: %1$d: trigger length, %2$s: trigger interval.
                        __('Credit Card Expired %1$d %2$s ago', 'memberpress'),
                        $reminder->trigger_length,
                        $reminder->get_trigger_interval_str()
                    ),
                ],
            ],
            'member-signup'    => [
                'before' => [
                    'name'        => '',
                    'description' => '',
                ],
                'after'  => [
                    'name'        => __('Member Signed Up', 'memberpress'),
                    'description' => sprintf(
                        // Translators: %1$d: trigger length, %2$s: trigger interval.
                        __('Member Signed Up %1$d %2$s ago', 'memberpress'),
                        $reminder->trigger_length,
                        $reminder->get_trigger_interval_str()
                    ),
                ],
            ],
            'signup-abandoned' => [
                'before' => [
                    'name'        => '',
                    'description' => '',
                ],
                'after'  => [
                    'name'        => __('Sign Up Abandoned', 'memberpress'),
                    'description' => sprintf(
                        // Translators: %1$d: trigger length, %2$s: trigger interval.
                        __('Sign Up Abandoned %1$d %2$s ago', 'memberpress'),
                        $reminder->trigger_length,
                        $reminder->get_trigger_interval_str()
                    ),
                ],
            ],
            'sub-trial-ends'   => [
                'before' => [
                    'name'        => __('Subscription Trial Period is Ending Soon', 'memberpress'),
                    'description' => sprintf(
                        // Translators: %1$d: trigger length, %2$s: trigger interval.
                        __('Subscription trial period is ending in %1$d %2$s', 'memberpress'),
                        $reminder->trigger_length,
                        $reminder->get_trigger_interval_str()
                    ),
                ],
            ],
        ];

        $lookup = MeprHooks::apply_filters('mepr_reminder_lookup', $lookup, $reminder);

        return $lookup[ $reminder->trigger_event][$reminder->trigger_timing][$field];
    }

    /**
     * Products multiselect.
     *
     * @param  string $field_name The field name.
     * @param  array  $selected   The selected.
     * @return void
     */
    public static function products_multiselect($field_name, $selected)
    {
        $formatted = [];

        $all_products = MeprCptModel::all('MeprProduct');

        foreach ($all_products as $prd) {
            $formatted[$prd->ID] = $prd->post_title;
        }

        // Empty array means ALL products should be selected for backwards compat.
        if (!is_array($selected) || empty($selected)) {
            $selected = [];
        }

        ?>
      <select name="<?php echo $field_name; ?>[]" id="<?php echo $field_name; ?>" class="mepr-multi-select" multiple="true">
        <?php foreach ($formatted as $id => $name) : ?>
        <option value="<?php echo $id; ?>" <?php selected((empty($selected) || in_array($id, $selected))); ?>><?php echo $name; ?>&nbsp;</option>
        <?php endforeach; ?>
      </select>
      <span class="description">
        <small><?php _e('Hold the Control Key (Command Key on the Mac) in order to select or deselect multiple memberships', 'memberpress'); ?></small>
      </span>
        <?php
    }
}
