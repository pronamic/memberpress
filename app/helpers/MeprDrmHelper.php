<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprDrmHelper
{
    const NO_LICENSE_EVENT      = 'no-license';
    const INVALID_LICENSE_EVENT = 'invalid-license';

    const DRM_LOW    = 'low';
    const DRM_MEDIUM = 'medium';
    const DRM_LOCKED = 'locked';

    /**
     * Stores the current DRM status.
     *
     * @var string
     */
    private static $drm_status     = '';

    /**
     * Stores the DRM links for different statuses and purposes.
     *
     * @var array|null
     */
    private static $drm_links      = null;

    /**
     * Fallback links to use when DRM links aren't available.
     *
     * @var array
     */
    private static $fallback_links = [
        'account' => 'https://memberpress.com/account/',
        'support' => 'https://memberpress.com/support/',
        'pricing' => 'https://memberpress.com/pricing/',
    ];

    /**
     * Set the DRM status.
     *
     * @param string $status The DRM status to set.
     *
     * @return void
     */
    public static function set_status($status)
    {
        self::$drm_status = $status;
    }

    /**
     * Get the current DRM status.
     *
     * @return string The current DRM status.
     */
    public static function get_status()
    {
        return self::$drm_status;
    }

    /**
     * Check if a license key exists.
     *
     * @return boolean True if a license key exists, false otherwise.
     */
    public static function has_key()
    {
        $mepr_options = MeprOptions::fetch();
        $key          = '';
        if (isset($mepr_options->mothership_license)) {
            $key = $mepr_options->mothership_license;
        }
        return ! empty($key);
    }

    /**
     * Get the license key.
     *
     * @return string The license key.
     */
    public static function get_key()
    {
        $mepr_options = MeprOptions::fetch();
        $key          = '';
        if (isset($mepr_options->mothership_license)) {
            $key = $mepr_options->mothership_license;
        }
        return $key;
    }

    /**
     * Check if activation override is valid.
     *
     * @return boolean True if activation override is valid, false otherwise.
     */
    public static function is_aov()
    {
        $aov = get_option('mepr_activation_override');

        if (! empty($aov)) {
            return true; // Valid license.
        }

        return false;
    }

    /**
     * Check if the license is valid.
     *
     * @return boolean True if the license is valid, false otherwise.
     */
    public static function is_valid()
    {

        if (self::is_aov() || MeprUpdateCtrl::is_activated()) {
            return true; // Valid license.
        }

        if (! self::has_key()) {
            return false;
        }

        $license = get_site_transient('mepr_license_info');

        if (! isset($license['license_key'])) {
            return false;  // Invalid license.
        }

        if ('enabled' != $license['license_key']['status']) {
            return false; // Invalid license.
        }

        // Expiry is not set. It is unlimited.
        if (is_null($license['license_key']['expires_at'])) {
            return true; // Valid license.
        }

        $expiry_stamp = strtotime($license['license_key']['expires_at']);

        // License has a valid expiry date and it is in future?
        if ($expiry_stamp && $expiry_stamp >= strtotime('Y-m-d')) {
            return true; // Valid license.
        }

        return false; // Invalid license.
    }

    /**
     * Calculate the number of days elapsed since a given date.
     *
     * @param string $created_at The date to calculate from.
     *
     * @return integer The number of days elapsed.
     */
    public static function days_elapsed($created_at)
    {

        $timestamp = strtotime($created_at);

        if (false === $timestamp) {
            return 0; // Invalid timestamp.
        }

        $start_date = new DateTime(date('Y-m-d'));
        $end_date   = new DateTime(date('Y-m-d', $timestamp));
        $difference = $end_date->diff($start_date);

        return absint($difference->format('%a'));
    }


    /**
     * Determine the DRM status, using a default if not provided.
     *
     * @param string $drm_status The DRM status to check.
     *
     * @return string The determined DRM status.
     */
    protected static function maybe_drm_status($drm_status = '')
    {
        if (empty($drm_status)) {
            $drm_status = self::$drm_status;
        }

        return $drm_status;
    }

    /**
     * Check if the DRM status is locked.
     *
     * @param string $drm_status The DRM status to check.
     *
     * @return boolean True if the DRM status is locked, false otherwise.
     */
    public static function is_locked($drm_status = '')
    {
        return ( self::DRM_LOCKED === self::maybe_drm_status($drm_status) );
    }

    /**
     * Check if the DRM status is medium.
     *
     * @param string $drm_status The DRM status to check.
     *
     * @return boolean True if the DRM status is medium, false otherwise.
     */
    public static function is_medium($drm_status = '')
    {
        return ( self::DRM_MEDIUM === self::maybe_drm_status($drm_status) );
    }

    /**
     * Check if the DRM status is low.
     *
     * @param string $drm_status The DRM status to check.
     *
     * @return boolean True if the DRM status is low, false otherwise.
     */
    public static function is_low($drm_status = '')
    {
        return ( self::DRM_LOW === self::maybe_drm_status($drm_status) );
    }

    /**
     * Get DRM information based on status, event, and purpose.
     *
     * @param string $drm_status The DRM status.
     * @param string $event_name The event name.
     * @param string $purpose    The purpose of the information.
     *
     * @return array The DRM information.
     */
    public static function get_info($drm_status, $event_name, $purpose)
    {

        $out = [];
        switch ($event_name) {
            case self::NO_LICENSE_EVENT:
                $out = self::drm_info_no_license($drm_status, $purpose);
                break;
            case self::INVALID_LICENSE_EVENT:
                $drm_info = self::drm_info_invalid_license($drm_status, $purpose);
                $out      = MeprHooks::apply_filters('mepr_drm_invalid_license_info', $drm_info, $drm_status);
                break;
            default:
        }

        return $out;
    }

    /**
     * Get the status key for a given DRM status.
     *
     * @param string $drm_status The DRM status.
     *
     * @return string The status key.
     */
    public static function get_status_key($drm_status)
    {

        $out = '';
        switch ($drm_status) {
            case self::DRM_LOW:
                $out = 'dl';
                break;
            case self::DRM_MEDIUM:
                $out = 'dm';
                break;
            case self::DRM_LOCKED:
                $out = 'dll';
                break;
            default:
        }

        return $out;
    }

    /**
     * Get the DRM links.
     *
     * @return array The DRM links.
     */
    protected static function get_drm_links()
    {

        if (self::$drm_links === null) {
            self::$drm_links = [
                self::DRM_LOW    => [
                    'email'   => [
                        'home'    => 'https://memberpress.com/drmlow/email',
                        'account' => 'https://memberpress.com/drmlow/email/acct',
                        'support' => 'https://memberpress.com/drmlow/email/support',
                        'pricing' => 'https://memberpress.com/drmlow/email/pricing',
                    ],
                    'general' => [
                        'home'    => 'https://memberpress.com/drmlow/ipm',
                        'account' => 'https://memberpress.com/drmlow/ipm/account',
                        'support' => 'https://memberpress.com/drmlow/ipm/support',
                        'pricing' => 'https://memberpress.com/drmlow/ipm/pricing',
                    ],
                ],
                self::DRM_MEDIUM => [
                    'email'   => [
                        'home'    => 'https://memberpress.com/drmmed/email',
                        'account' => 'https://memberpress.com/drmmed/email/acct',
                        'support' => 'https://memberpress.com/drmmed/email/support',
                        'pricing' => 'https://memberpress.com/drmmed/email/pricing',
                    ],
                    'general' => [
                        'home'    => 'https://memberpress.com/drmmed/ipm',
                        'account' => 'https://memberpress.com/drmmed/ipm/account',
                        'support' => 'https://memberpress.com/drmmed/ipm/support',
                        'pricing' => 'https://memberpress.com/drmmed/ipm/pricing',
                    ],
                ],
                self::DRM_LOCKED => [
                    'email'   => [
                        'home'    => 'https://memberpress.com/drmlock/email',
                        'account' => 'https://memberpress.com/drmlock/email/acct',
                        'support' => 'https://memberpress.com/drmlock/email/support',
                        'pricing' => 'https://memberpress.com/drmlock/email/pricing',
                    ],
                    'general' => [
                        'home'    => 'https://memberpress.com/drmlock/ipm',
                        'account' => 'https://memberpress.com/drmlock/ipm/account',
                        'support' => 'https://memberpress.com/drmlock/ipm/support',
                        'pricing' => 'https://memberpress.com/drmlock/ipm/pricing',
                    ],
                ],
            ];
        }

        return MeprHooks::apply_filters('mepr_drm_links', self::$drm_links);
    }

    /**
     * Get a specific DRM link based on status, purpose, and type.
     *
     * @param string $drm_status The DRM status.
     * @param string $purpose    The purpose of the link.
     * @param string $type       The type of link.
     *
     * @return string The DRM link.
     */
    public static function get_drm_link($drm_status, $purpose, $type)
    {
        $drm_links = self::get_drm_links();
        if (isset($drm_links[ $drm_status ])) {
            if (! isset($drm_links[ $drm_status ][ $purpose ])) {
                $purpose = 'general';
            }

            if (isset($drm_links[ $drm_status ][ $purpose ])) {
                $data = $drm_links[ $drm_status ][ $purpose ];
                if (isset($data[ $type ])) {
                    return $data[ $type ];
                }
            }
        }

        // Fallback links.
        if (isset(self::$fallback_links[$type])) {
            return self::$fallback_links[$type];
        }

        return '';
    }

    /**
     * Get DRM information for no license event.
     *
     * @param string $drm_status The DRM status.
     * @param string $purpose    The purpose of the information.
     *
     * @return array The DRM information for no license event.
     */
    protected static function drm_info_no_license($drm_status, $purpose)
    {

        $account_link            = self::get_drm_link($drm_status, $purpose, 'account');
        $support_link            = self::get_drm_link($drm_status, $purpose, 'support');
        $pricing_link            = self::get_drm_link($drm_status, $purpose, 'pricing');
        $additional_instructions = sprintf(
            // Translators: %s: site URL.
            __('This is an automated message from %s.', 'memberpress'),
            esc_url(home_url())
        );
        switch ($drm_status) {
            case self::DRM_LOW:
                $admin_notice_view = 'low_warning';
                $heading           = __('MemberPress: Did You Forget Something?', 'memberpress');
                $color             = 'orange';
                $simple_message    = __('Oops! It looks like your MemberPress license key is missing. Here\'s how to fix the problem fast and easy:', 'memberpress');
                $help_message      = __('We’re here if you need any help.', 'memberpress');
                $label             = __('Alert', 'memberpress');
                $activation_link   = admin_url('admin.php?page=memberpress-options#mepr-license');
                $message           = sprintf(
                    '<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li></ul>',
                    $simple_message,
                    sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        __('Grab your key from your %1$sAccount Page%2$s.', 'memberpress'),
                        '<a href="' . esc_url($account_link) . '">',
                        '</a>'
                    ),
                    sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        __('%1$sClick here%2$s to enter and activate it.', 'memberpress'),
                        '<a href="' . esc_url($activation_link) . '">',
                        '</a>'
                    ),
                    __('That’s it!', 'memberpress')
                );
                break;
            case self::DRM_MEDIUM:
                $admin_notice_view = 'medium_warning';
                $heading           = __('MemberPress: WARNING! Your Business is at Risk', 'memberpress');
                $color             = 'orange';
                $simple_message    = __('To continue using MemberPress without interruption, you need to enter your license key right away. Here’s how:', 'memberpress');
                $help_message      = __('Let us know if you need assistance.', 'memberpress');
                $label             = __('Critical', 'memberpress');
                $activation_link   = admin_url('admin.php?page=memberpress-options#mepr-license');
                $message           = sprintf(
                    '<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li></ul>',
                    $simple_message,
                    sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        __('Grab your key from your %1$sAccount Page%2$s.', 'memberpress'),
                        '<a href="' . esc_url($account_link) . '">',
                        '</a>'
                    ),
                    sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        __('%1$sClick here%2$s to enter and activate it.', 'memberpress'),
                        '<a href="' . esc_url($activation_link) . '">',
                        '</a>'
                    ),
                    __('That’s it!', 'memberpress')
                );
                break;
            case self::DRM_LOCKED:
                $admin_notice_view = 'locked_warning';
                $heading           = __('ALERT! MemberPress Backend is Deactivated', 'memberpress');
                $color             = 'red';
                $simple_message    = __('Because your license key is inactive, you can no longer manage MemberPress on the backend (e.g., you can\'t do things like issue customer refunds or add new members). Fortunately, this problem is easy to fix!', 'memberpress');
                $help_message      = __('We\'re here to help you get things up and running. Let us know if you need assistance.', 'memberpress');
                $label             = __('Critical', 'memberpress');
                $activation_link   = admin_url('admin.php?page=memberpress-members');
                $message           = sprintf(
                    '<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li></ul>',
                    $simple_message,
                    sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        __('Grab your key from your %1$sAccount Page%2$s.', 'memberpress'),
                        '<a href="' . esc_url($account_link) . '">',
                        '</a>'
                    ),
                    sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        __('%1$sClick here%2$s to enter and activate it.', 'memberpress'),
                        '<a href="' . esc_url($activation_link) . '">',
                        '</a>'
                    ),
                    __('That’s it!', 'memberpress')
                );
                break;
            default:
                $heading                 = '';
                $color                   = '';
                $message                 = '';
                $help_message            = '';
                $label                   = '';
                $activation_link         = '';
                $admin_notice_view       = '';
                $simple_message          = '';
                $additional_instructions = '';
        }

        return compact('heading', 'color', 'message', 'simple_message', 'help_message', 'label', 'activation_link', 'account_link', 'support_link', 'pricing_link', 'admin_notice_view', 'additional_instructions');
    }

    /**
     * Get DRM information for invalid license event.
     *
     * @param string $drm_status The DRM status.
     * @param string $purpose    The purpose of the information.
     *
     * @return array The DRM information for invalid license event.
     */
    protected static function drm_info_invalid_license($drm_status, $purpose)
    {

        $account_link            = self::get_drm_link($drm_status, $purpose, 'account');
        $support_link            = self::get_drm_link($drm_status, $purpose, 'support');
        $pricing_link            = self::get_drm_link($drm_status, $purpose, 'pricing');
        $additional_instructions = sprintf(
            // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
            __('This is an automated message from %1$s. If you continue getting these messages, please try deactivating and then re-activating your license key on %2$s.', 'memberpress'),
            esc_url(home_url()),
            esc_url(home_url())
        );

        switch ($drm_status) {
            case self::DRM_MEDIUM:
                $admin_notice_view = 'medium_warning';
                $heading           = __('MemberPress: WARNING! Your Business is at Risk', 'memberpress');
                $color             = 'orange';
                $simple_message    = __('Your MemberPress license key is expired, but is required to continue using MemberPress. Fortunately, it’s easy to renew your license key. Just do the following:', 'memberpress');
                $help_message      = __('Let us know if you need assistance.', 'memberpress');
                $label             = __('Critical', 'memberpress');
                $activation_link   = admin_url('admin.php?page=memberpress-options#mepr-license');
                $message           = sprintf(
                    '<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li></ul>',
                    $simple_message,
                    sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        __('Go to MemberPress.com and make your selection. %1$sPricing%2$s.', 'memberpress'),
                        '<a href="' . esc_url($pricing_link) . '">',
                        '</a>'
                    ),
                    sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        __('%1$sClick here%2$s to enter and activate your new license key.', 'memberpress'),
                        '<a href="' . esc_url($activation_link) . '">',
                        '</a>'
                    ),
                    __('That’s it!', 'memberpress')
                );
                break;
            case self::DRM_LOCKED:
                $admin_notice_view = 'locked_warning';
                $label             = __('Critical', 'memberpress');
                $heading           = __('ALERT! MemberPress Backend is Deactivated', 'memberpress');
                $color             = 'red';
                $simple_message    = __('Without an active license key, MemberPress cannot be managed on the backend. Your frontend will remain intact, but you can’t: Issue customer refunds, Add new members, Manage memberships. Fortunately, this problem is easy to fix by doing the following: ', 'memberpress');
                $activation_link   = admin_url('admin.php?page=memberpress-members');
                $message           = sprintf(
                    '<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li></ul>',
                    $simple_message,
                    sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        __('Go to MemberPress.com and make your selection. %1$sPricing%2$s.', 'memberpress'),
                        '<a href="' . esc_url($pricing_link) . '">',
                        '</a>'
                    ),
                    sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        __('%1$sClick here%2$s to enter and activate your new license key.', 'memberpress'),
                        '<a href="' . esc_url($activation_link) . '">',
                        '</a>'
                    ),
                    __('That’s it!', 'memberpress')
                );
                $help_message      = __('We’re here to help you get things back up and running. Let us know if you need assistance.', 'memberpress');
                break;
            default:
                $heading                 = '';
                $color                   = '';
                $message                 = '';
                $help_message            = '';
                $label                   = '';
                $activation_link         = '';
                $admin_notice_view       = '';
                $simple_message          = '';
                $additional_instructions = '';
        }

        return compact('heading', 'color', 'message', 'simple_message', 'help_message', 'label', 'activation_link', 'account_link', 'support_link', 'admin_notice_view', 'pricing_link', 'additional_instructions');
    }

    /**
     * Parse event arguments from a JSON string.
     *
     * @param string $args The JSON string of arguments.
     *
     * @return array The parsed event arguments.
     */
    public static function parse_event_args($args)
    {
        return json_decode($args, true);
    }

    /**
     * Prepare a dismissable notice key for a given notice.
     *
     * @param string $notice The notice identifier.
     *
     * @return string The dismissable notice key.
     */
    public static function prepare_dismissable_notice_key($notice)
    {
        $notice = sanitize_key($notice);
        return "{$notice}_u" . get_current_user_id();
    }

    /**
     * Check if a notice is dismissed based on event data and notice key.
     *
     * @param array  $event_data The event data.
     * @param string $notice_key The notice key.
     *
     * @return boolean True if the notice is dismissed, false otherwise.
     */
    public static function is_dismissed($event_data, $notice_key)
    {
        if (isset($event_data[ $notice_key ])) {
            $diff = (int) abs(time() - $event_data[ $notice_key ]);
            if ($diff <= ( HOUR_IN_SECONDS * 24 )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get DRM transient fee data.
     *
     * @return array|false The DRM transient fee data or false if not available.
     */
    public static function get_drm_transient_fee_data()
    {
        $transient      =  get_transient('mepr_drm_app_fee');
        $transient_data = false;
        if (!empty($transient) && strstr($transient, '|')) {
            $data           = explode('|', $transient);
            $transient_data = [
                'v'       => $data[0],
                'a99_f33' => $data[1],
            ];
        }

        return $transient_data;
    }

    /**
     * Get the DRM application fee version.
     *
     * @return integer The DRM application fee version.
     */
    public static function get_drm_app_fee_version()
    {
        $transient = self::get_drm_transient_fee_data();
        if (!empty($transient) && is_array($transient)) {
            return $transient['v'];
        }

        return get_option('mepr_drm_application_fee_version', 0);
    }

    /**
     * Get the DRM transient application fee.
     *
     * @return string|false The DRM transient application fee or false if not available.
     */
    private static function get_drm_transient_app_fee()
    {
        $transient = self::get_drm_transient_fee_data();
        if (!empty($transient) && is_array($transient)) {
            return $transient['a99_f33'];
        }
        return false;
    }

    /**
     * Get the application fee percentage.
     *
     * @param boolean $bypass Whether to bypass the transient check.
     *
     * @return string The application fee percentage.
     */
    public static function get_application_fee_percentage($bypass = false)
    {
        $app_fee = self::get_drm_transient_app_fee();
        if (false !== $app_fee && false === $bypass) {
            return $app_fee;
        }

        $url = 'https://memberpress.com/wp-json/caseproof/d7m/v1/f33';
        if (defined('MEPR_STAGING_MP_URL') && ( defined('MPSTAGE') && MPSTAGE )) {
            $url = MEPR_STAGING_MP_URL . '/wp-json/caseproof/d7m/v1/f33';
        }

        $args = [
            'sslverify' => false,
        ];

        $url = add_query_arg([
            'MEMBERPRESS-DR7-KEY' => 'BAY074X4F4C8UUARHZMV',
        ], $url);

        $api_response    = wp_remote_get($url, $args);
        $fee_percentage  = apply_filters('mepr_drm_application_fee_percentage', 3);
        $current_version = get_option('mepr_drm_application_fee_version', 0);
        $transient_data  = $current_version . '|' . $fee_percentage;

        if (!is_wp_error($api_response)) {
            $data = json_decode($api_response['body'], true);
            if (null !== $data) {
                if (isset($data['v'])) {
                    $fee_percentage = base64_decode($data['a99_f33']);
                    $transient_data = $data['v'] . '|' . $fee_percentage;
                    update_option('mepr_drm_application_fee_version', $data['v']);
                }
            }
        }

        set_transient('mepr_drm_app_fee', $transient_data, WEEK_IN_SECONDS);

        return $fee_percentage;
    }

    /**
     * Check if the application fee is enabled.
     *
     * @return boolean True if the application fee is enabled, false otherwise.
     */
    public static function is_app_fee_enabled()
    {
        return get_option('mepr_drm_app_fee_enabled', false);
    }

    /**
     * Enable the application fee.
     *
     * @return boolean True on success, false on failure.
     */
    public static function enable_app_fee()
    {
        return update_option('mepr_drm_app_fee_enabled', time(), false);
    }

    /**
     * Disable the application fee.
     *
     * @return boolean True on success, false on failure.
     */
    public static function disable_app_fee()
    {
        return delete_option('mepr_drm_app_fee_enabled');
    }

    /**
     * Check if the application fee notice is dismissed.
     *
     * @return boolean True if the notice is dismissed, false otherwise.
     */
    public static function is_app_fee_notice_dismissed()
    {
        $dimissed_time = get_option('mepr_drm_app_fee_notice_dimissed', false);

        if ($dimissed_time) {
            $diff = (int) abs(time() - $dimissed_time);
            if ($diff <= ( DAY_IN_SECONDS * 30 )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dismiss the application fee notice.
     *
     * @return boolean True on success, false on failure.
     */
    public static function dismiss_app_fee_notice()
    {
        return update_option('mepr_drm_app_fee_notice_dimissed', time(), false);
    }

    /**
     * Check if the application fee is allowed for a country.
     *
     * @param string $country The country code.
     *
     * @return boolean
     */
    public static function is_country_unlockable_by_fee($country)
    {
        if (empty($country)) {
            return false; // No country provided, disallow by default.
        }

        $country = strtoupper($country);
        $disallowed_countries = [
            'BR', // Brazil.
            'IN', // India.
            'MX', // Mexico.
            'MY', // Malaysia.
            'SG', // Singapore.
            'TH', // Thailand.
        ];

        return !in_array($country, $disallowed_countries, true);
    }
}
