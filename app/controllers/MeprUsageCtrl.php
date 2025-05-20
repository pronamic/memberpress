<?php

class MeprUsageCtrl extends MeprBaseCtrl
{
    /**
     * Loads the hooks.
     *
     * @return void
     */
    public function load_hooks()
    {
        if (!get_option('mepr_disable_senddata')) {
            add_filter('cron_schedules', [$this,'intervals']);
            add_action('mepr_snapshot_worker', [$this,'snapshot']);

            if (!wp_next_scheduled('mepr_snapshot_worker')) {
                 wp_schedule_event(time() + MeprUtils::weeks(1), 'mepr_snapshot_interval', 'mepr_snapshot_worker');
            }
        }

        add_action('mepr_display_general_options', [$this,'display_options'], 99);
        add_action('mepr-process-options', [$this,'save_options']);
    }

    /**
     * Intervals.
     *
     * @param  array $schedules The schedules.
     * @return array
     */
    public function intervals($schedules)
    {
        $schedules['mepr_snapshot_interval'] = [
            'interval' => MeprUtils::weeks(1),
            'display'  => __('MemberPress Snapshot Interval', 'memberpress'),
        ];

        return $schedules;
    }

    /**
     * Snapshot.
     *
     * @return void
     */
    public function snapshot()
    {
        if (get_option('mepr_disable_senddata')) {
            return;
        }

        // This is here because we've learned through sad experience that we can't fully
        // rely on WP-CRON to wait for an entire week so we check here to ensure we're ready.
        $already_sent = MeprExpiringOption::get('sent_snapshot');
        if (!empty($already_sent)) {
            MeprUtils::debug_log(
                __('Your site is attempting to send too many snapshots, we\'ll put an end to that.', 'memberpress')
            );
            return;
        }

        $ep =
        'aHR0cHM6Ly9tZW1iZXJwcmVz' .
        'cy1hbmFseXRpY3MuaGVyb2t1' .
        'YXBwLmNvbS9zbmFwc2hvdA==';

        $usage = new MeprUsage();
        $body  = wp_json_encode($usage->snapshot());

        $headers = [
            'Accept'         => 'application/json',
            'Content-Type'   => 'application/json',
            'Content-Length' => strlen($body),
        ];

        // Setup variable for wp_remote_request.
        $post = [
            'method'  => 'POST',
            'headers' => $headers,
            'body'    => $body,
        ];

        wp_remote_request(base64_decode($ep), $post); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, Generic.Files.LineLength.TooLong

        // 6 days so we don't accidentally miss the weekly cron
        MeprExpiringOption::set('sent_snapshot', 1, MeprUtils::days(6));
    }

    /**
     * Displays the options.
     *
     * @return void
     */
    public function display_options()
    {
        $disable_senddata   = get_option('mepr_disable_senddata');
        $hide_announcements = get_option('mepr_hide_announcements');

        MeprView::render('admin/usage/option', compact('disable_senddata', 'hide_announcements'));
    }

    /**
     * Saves the options.
     *
     * @param  array $params The params.
     * @return void
     */
    public function save_options($params)
    {
        update_option('mepr_disable_senddata', !isset($params['mepr_enable_senddata']));
        update_option('mepr_hide_announcements', isset($params['mepr_hide_announcements']));
    }
}
