<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprJobs
{
    /**
     * The job configuration.
     *
     * @var object
     */
    public $config;

    /**
     * Constructor for the MeprJobs class.
     * Sets up job configuration and schedules cron jobs for worker and cleanup tasks.
     */
    public function __construct()
    {
        // Setup job configuration.
        $this->config = MeprHooks::apply_filters('mepr-jobs-config', (object)[
            'status'  => (object)[
                'pending'  => 'pending',
                'complete' => 'complete',
                'failed'   => 'failed',
                'working'  => 'working',
            ],
            'worker'  => (object)[
                'interval'    => MeprUtils::minutes(1),
                'retry_after' => MeprUtils::minutes(30), // Standard retries after a failure.
            ],
            'cleanup' => (object)[
                'num_retries'            => 5, // "num_retries" before transactions fail.
                'interval'               => MeprUtils::hours(1),
                'retry_after'            => MeprUtils::hours(1), // Purely for zombie jobs left in a bad state.
                'delete_completed_after' => MeprUtils::days(2),
                'delete_failed_after'    => MeprUtils::days(30),
            ],
        ]);

        // Setup the options page.
        add_action('mepr_display_general_options', [$this,'display_option_fields']);
        add_action('mepr-process-options', [$this,'store_option_fields']);

        // Set a wp-cron.
        add_filter('cron_schedules', [$this,'intervals']);
        add_action('mepr_jobs_worker', [$this,'worker']);
        add_action('mepr_jobs_cleanup', [$this,'cleanup']);

        if (!wp_next_scheduled('mepr_jobs_worker')) {
            wp_schedule_event(time() + $this->config->worker->interval, 'mepr_jobs_interval', 'mepr_jobs_worker');
        }

        if (!wp_next_scheduled('mepr_jobs_cleanup')) {
            wp_schedule_event(time() + $this->config->cleanup->interval, 'mepr_jobs_cleanup_interval', 'mepr_jobs_cleanup');
        }
    }

    /**
     * Adds custom intervals for cron schedules.
     *
     * @param array $schedules The existing schedules.
     *
     * @return array The modified schedules.
     */
    public function intervals($schedules)
    {
        $schedules['mepr_jobs_interval'] = [
            'interval' => $this->config->worker->interval,
            'display'  => __('MemberPress Jobs Worker', 'memberpress'),
        ];

        $schedules['mepr_jobs_cleanup_interval'] = [
            'interval' => $this->config->cleanup->interval,
            'display'  => __('MemberPress Jobs Cleanup', 'memberpress'),
        ];

        return $schedules;
    }

    /**
     * Processes jobs in the queue, executing them if possible.
     *
     * @return void
     */
    public function worker()
    {
        $max_run_time = 45;
        $start_time   = time();

        // We want to allow for at least 15 seconds of buffer.
        while (( time() - $start_time ) <= $max_run_time) {
            $job = $this->next_job();
            if (!$job) {
                break;
            }

            try {
                $this->work($job);
                if (isset($job->class)) {
                    $obj = MeprJobFactory::fetch($job->class, $job);
                    MeprUtils::debug_log(sprintf(
                        // Translators: %1$s: job ID, %2$s: job class.
                        __('Starting Job - %1$s (%2$s): %3$s', 'memberpress'),
                        $job->id,
                        $job->class,
                        MeprUtils::object_to_string($obj)
                    ));
                    $obj->perform(); // Run the job's perform method.
                    MeprUtils::debug_log(sprintf(
                        // Translators: %1$s: job ID, %2$s: job class.
                        __('Job Completed - %1$s (%2$s)', 'memberpress'),
                        $job->id,
                        $job->class
                    ));
                    $this->complete($job); // When we're successful we complete the job.
                } else {
                    $this->fail($job, __('No class was specified in the job config', 'memberpress'));
                    MeprUtils::debug_log(__('Job Failed: No class', 'memberpress'));
                }
            } catch (Exception $e) {
                $this->fail($job, $e->getMessage());
                MeprUtils::debug_log(sprintf(
                    // Translators: %s: error message.
                    __('Job Failed: %s', 'memberpress'),
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Cleans up completed and failed jobs from the queue.
     *
     * @return void
     */
    public function cleanup()
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        // Retry lingering jobs.
        $query = "UPDATE {$mepr_db->jobs}
                 SET status = %s
               WHERE status IN (%s,%s)
                 AND tries <= %d
                 AND TIMESTAMPDIFF(SECOND,lastrun,%s) >= %d";
        $query = $wpdb->prepare(
            $query,
            $this->config->status->pending, // Set status to pending.
            $this->config->status->working, // If status = working or.
            $this->config->status->failed, // The status = failed and.
            $this->config->cleanup->num_retries, // Number of tries <= num_retries.
            MeprUtils::db_now(),
            $this->config->cleanup->retry_after // And the correct number of seconds since lastrun has elapsed.
        );
        $wpdb->query($query);

        // Delete completed jobs that have been in the system for over a day?
        $query = "DELETE FROM {$mepr_db->jobs}
               WHERE status = %s
                 AND TIMESTAMPDIFF(SECOND,lastrun,%s) >= %d";
        $query = $wpdb->prepare(
            $query, // Delete jobs.
            $this->config->status->complete, // Which have a status = complete.
            MeprUtils::db_now(),
            $this->config->cleanup->delete_completed_after // And the correct number of seconds since lastrun has elapsed.
        );
        $wpdb->query($query);

        // Delete jobs that have been retried and are still in a working state.
        $query = "DELETE FROM {$mepr_db->jobs}
               WHERE tries > %d
                 AND TIMESTAMPDIFF(SECOND,lastrun,%s) >= %d";
        $query = $wpdb->prepare(
            $query, // Delete jobs.
            $this->config->cleanup->num_retries, // Which have only been 'n' retries.
            MeprUtils::db_now(),
            $this->config->cleanup->delete_failed_after // And the correct number of seconds since lastrun has elapsed.
        );
        $wpdb->query($query);
    }

    /**
     * Retrieves the next job from the queue.
     *
     * @return object|null The next job object or null if no job is available.
     */
    public function queue()
    {
        global $wpdb;

        $mepr_db = new MeprDb();

        $query = "
      SELECT * FROM {$mepr_db->jobs}
       WHERE status = %s
         AND runtime <= %s
       ORDER BY priority ASC, runtime ASC
    ";
        $query = $wpdb->prepare($query, $this->config->status->pending, MeprUtils::db_now());

        return $wpdb->get_results($query, OBJECT);
    }

    /**
     * Retrieves the next job from the queue.
     *
     * @return object|null The next job object or null if no job is available.
     */
    public function next_job()
    {
        global $wpdb;

        $mepr_db = new MeprDb();

        $query = "SELECT * FROM {$mepr_db->jobs}
               WHERE status = %s
                 AND runtime <= %s
               ORDER BY priority ASC, runtime ASC
               LIMIT 1";
        $query = $wpdb->prepare($query, $this->config->status->pending, MeprUtils::db_now());

        return $wpdb->get_row($query, OBJECT);
    }

    /**
     * Enqueues a job to be executed after a specified interval.
     *
     * @param string  $in        The interval after which the job should be executed.
     * @param string  $classname The class name of the job.
     * @param array   $args      Optional arguments for the job.
     * @param integer $priority  The priority of the job.
     *
     * @return void
     */
    public function enqueue_in($in, $classname, $args = [], $priority = 10)
    {
        $when = time() + $this->interval2seconds($in);
        $this->enqueue($classname, $args, $when, $priority);
    }

    /**
     * Enqueues a job to be executed at a specific time.
     *
     * @param integer $at        The timestamp at which the job should be executed.
     * @param string  $classname The class name of the job.
     * @param array   $args      Optional arguments for the job.
     * @param integer $priority  The priority of the job.
     *
     * @return void
     */
    public function enqueue_at($at, $classname, $args = [], $priority = 10)
    {
        $when = $at;
        $this->enqueue($classname, $args, $when, $priority);
    }

    /**
     * Enqueues a job to be executed.
     *
     * @param string  $classname The class name of the job.
     * @param array   $args      Optional arguments for the job.
     * @param mixed   $when      The time when the job should be executed.
     * @param integer $priority  The priority of the job.
     *
     * @return integer|false The job ID on success, false on failure.
     */
    public function enqueue($classname, $args = [], $when = 'now', $priority = 10)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        if ($when === 'now') {
            $when = time();
        }

        $config = [
            'runtime'  => MeprUtils::ts_to_mysql_date($when),
            'firstrun' => MeprUtils::ts_to_mysql_date($when),
            'priority' => $priority,
            'tries'    => 0,
            'class'    => $classname,
            'args'     => json_encode($args),
            'reason'   => '',
            'status'   => $this->config->status->pending,
            'lastrun'  => MeprUtils::db_now(),
        ];

        // Returns the job id to dequeue later if necessary.
        return $mepr_db->create_record($mepr_db->jobs, $config, true);
    }

    /**
     * Removes a job from the queue.
     *
     * @param integer $job_id The ID of the job to remove.
     *
     * @return integer|void The number of rows affected, or false on error.
     */
    public function dequeue($job_id)
    {
        if ($job_id == 0) {
            return;
        }

        global $wpdb;
        $mepr_db = new MeprDb();
        return $mepr_db->delete_records($mepr_db->jobs, ['id' => $job_id]);
    }

    /**
     * Marks a job as in progress.
     *
     * @param object $job The job object.
     *
     * @return void
     */
    public function work($job)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $args = [
            'status'  => $this->config->status->working,
            'tries'   => $job->tries + 1,
            'lastrun' => MeprUtils::db_now(),
        ];

        $mepr_db->update_record($mepr_db->jobs, $job->id, $args);
    }

    /**
     * Retries a failed job after a specified interval.
     *
     * @param object $job    The job object.
     * @param string $reason Optional reason for the retry.
     *
     * @return void
     */
    public function retry($job, $reason = '')
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $when = time() + $this->config->worker->retry_after;

        $args = [
            'status'  => $this->config->status->pending,
            'runtime' => MeprUtils::ts_to_mysql_date($when),
            'reason'  => $reason,
        ];

        $mepr_db->update_record($mepr_db->jobs, $job->id, $args);
    }

    /**
     * Marks a job as complete.
     *
     * @param object $job The job object.
     *
     * @return void
     */
    public function complete($job)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $args = [
            'status' => $this->config->status->complete,
            'reason' => '',
        ];

        $mepr_db->update_record($mepr_db->jobs, $job->id, $args);
    }

    /**
     * Marks a job as failed and retries if possible.
     *
     * @param object $job    The job object.
     * @param string $reason Optional reason for the failure.
     *
     * @return void
     */
    public function fail($job, $reason = '')
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        // We fail and then re-enqueue for an hour later 5 times before giving up.
        if ($job->tries >= $this->config->cleanup->num_retries) {
            $args = [
                'status' => $this->config->status->failed,
                'reason' => $reason,
            ];

            $mepr_db->update_record($mepr_db->jobs, $job->id, $args);
        } else {
            $this->retry($job, $reason);
        }
    }

    /**
     * Converts a time interval string to seconds.
     *
     * @param string $interval The interval string.
     *
     * @return integer The interval in seconds.
     */
    private function interval2seconds($interval)
    {
        $units   = ['m','h','d','w','M','y'];
        $seconds = 0;

        foreach ($units as $u) {
            preg_match_all("/(\d+){$u}/", $interval, $matches);
            if (isset($matches[1])) {
                foreach ($matches[1] as $m) {
                    if ($u == 'm') {
                        $seconds += MeprUtils::minutes($m);
                    } elseif ($u == 'h') {
                        $seconds += MeprUtils::hours($m);
                    } elseif ($u == 'd') {
                        $seconds += MeprUtils::days($m);
                    } elseif ($u == 'w') {
                        $seconds += MeprUtils::weeks($m);
                    } elseif ($u == 'M') {
                        $seconds += MeprUtils::months($m);
                    } elseif ($u == 'y') {
                        $seconds += MeprUtils::years($m);
                    }
                }
            }
        }

        return $seconds;
    }

    /**
     * Unschedules the worker and cleanup events.
     *
     * @return void
     */
    public function unschedule_events()
    {
        $timestamp = wp_next_scheduled('mepr_jobs_worker');
        wp_unschedule_event($timestamp, 'mepr_jobs_worker');

        $timestamp = wp_next_scheduled('mepr_jobs_cleanup');
        wp_unschedule_event($timestamp, 'mepr_jobs_cleanup');
    }

    /**
     * Displays the option fields for background jobs.
     *
     * @return void
     */
    public function display_option_fields()
    {
        $enabled = get_option('mp-bkg-email-jobs-enabled', isset($_POST['bkg_email_jobs_enabled']));

        ?>
    <div id="mp-bkg-email-jobs">
      <br/>
      <h3><?php _e('Background Jobs', 'memberpress'); ?></h3>
      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="bkg_email_jobs_enabled"><?php _e('Asynchronous Emails', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip(
                  'mepr-asynchronous-emails',
                  __('Send Emails Asynchronously in the Background', 'memberpress'),
                  __('This option will allow you to send all MemberPress emails asynchronously. This option can increase the speed & performance of the checkout process but may also result in a delay in when emails are recieved. <strong>Note:</strong> This option requires wp-cron to be enabled and working.', 'memberpress')
              ); ?>
            </th>
            <td>
              <input type="checkbox" name="bkg_email_jobs_enabled" id="bkg_email_jobs_enabled" <?php checked($enabled); ?> />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
        <?php
    }

    /**
     * Validates the option fields for background jobs.
     *
     * @param array $errors The existing errors.
     *
     * @return void
     */
    public function validate_option_fields($errors)
    {
        // Nothing to validate yet -- if ever.
    }

    /**
     * Updates the option fields for background jobs.
     *
     * @return void
     */
    public function update_option_fields()
    {
        // Nothing to do yet -- if ever.
    }

    /**
     * Stores the option fields for background jobs.
     *
     * @return void
     */
    public function store_option_fields()
    {
        update_option('mp-bkg-email-jobs-enabled', isset($_POST['bkg_email_jobs_enabled']));
    }
}
