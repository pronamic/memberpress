<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * This works the way I always anticipate transients to work but forget that
 * they don't work the way I think. They'll always reside in the options table
 * and will be self cleaning (as they're accessed).
 */
class MeprExpiringOption
{
    // TODO: Perhaps add some kind of WP-CRON job to cleanup expired options?
    // Not sure how many we'll have due to the self-cleaning features in self::get.
    /**
     * Sets an option with an expiration time.
     *
     * @param string  $name              The name of the option.
     * @param mixed   $value             The value to set.
     * @param integer $expire_in_seconds The number of seconds until the option expires.
     *
     * @return void
     */
    public static function set($name, $value, $expire_in_seconds)
    {
        list($value_key,$timeout_key) = self::get_keys($name);
        $timeout                      = self::calc_timeout($expire_in_seconds);

        update_option($value_key, $value);
        update_option($timeout_key, $timeout);
    }

    /**
     * Gets an option value with expiration checking.
     *
     * @param string $name The name of the option to retrieve.
     *
     * @return mixed The option value or empty string if expired/not found
     */
    public static function get($name)
    {
        list($value_key,$timeout_key) = self::get_keys($name);
        $timeout                      = get_option($timeout_key);

        // Auto-cleanup if expired.
        if (time() > (int)$timeout) {
            delete_option($timeout_key);
            delete_option($value_key);
            return '';
        }

        return get_option($value_key);
    }

    /**
     * Gets the value and timeout keys for an option.
     *
     * @param string $name The name of the option.
     *
     * @return array Array containing value key and timeout key
     */
    private static function get_keys($name)
    {
        $value_key   = "_mepr_expiring_{$name}";
        $timeout_key = "_mepr_expiring_timeout_{$name}";

        return [$value_key,$timeout_key];
    }

    /**
     * Calculates the timeout timestamp for an option.
     *
     * @param integer $expire_in_seconds Number of seconds until expiration.
     *
     * @return integer Unix timestamp when the option will expire
     */
    private static function calc_timeout($expire_in_seconds)
    {
        return time() + $expire_in_seconds;
    }
}
