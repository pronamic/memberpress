<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProactiveSupportCronCtrl extends MeprBaseCtrl
{
    public const CRON_HOOK = 'mepr_proactive_support_process';

    /**
     * Register hooks.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_filter('cron_schedules', [$this, 'register_interval']);
        add_action('init', [$this, 'schedule']);
        add_action(self::CRON_HOOK, [$this, 'run_job']);
    }

    /**
     * Register six-hour interval.
     *
     * @param array $schedules Schedules.
     *
     * @return array
     */
    public function register_interval($schedules)
    {
        if (!isset($schedules['mepr_proactive_support_six_hours'])) {
            $schedules['mepr_proactive_support_six_hours'] = [
                'interval' => 6 * HOUR_IN_SECONDS,
                'display'  => __('Every 6 Hours', 'memberpress'),
            ];
        }

        return $schedules;
    }

    /**
     * Schedule the cron event if needed.
     *
     * @return void
     */
    public function schedule()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'mepr_proactive_support_six_hours', self::CRON_HOOK);
        }
    }

    /**
     * Trigger the job.
     *
     * @return void
     */
    public function run_job()
    {
        $job = new MeprProactiveSupportCronJob();
        $job->run();
    }
}
