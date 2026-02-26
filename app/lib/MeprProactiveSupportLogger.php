<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProactiveSupportLogger
{
    private const OPTION_KEY = 'mepr_proactive_support_logs';
    private const MAX_LOGS   = 200;

    /**
     * Store a log entry.
     *
     * @param string $action      Action label.
     * @param string $trigger     Trigger key.
     * @param string $admin_email Admin email.
     * @param string $message     Additional message.
     *
     * @return void
     */
    public static function log($action, $trigger, $admin_email, $message = ''): void
    {
        $logs = self::get_logs_store();

        array_unshift(
            $logs,
            [
                'timestamp'   => current_time('mysql', true),
                'action'      => sanitize_text_field($action),
                'trigger'     => sanitize_key($trigger),
                'admin_email' => sanitize_email($admin_email),
                'message'     => sanitize_textarea_field($message),
            ]
        );

        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, 0, self::MAX_LOGS);
        }

        self::save_logs_store($logs);
    }

    /**
     * Get log entries.
     *
     * @param integer $limit Limit.
     *
     * @return array
     */
    public static function get_logs($limit = 50): array
    {
        $logs = self::get_logs_store();

        return array_slice($logs, 0, $limit);
    }

    /**
     * Retrieve logs from MeprOptions, migrating legacy data if needed.
     *
     * @return array
     */
    private static function get_logs_store(): array
    {
        self::maybe_migrate_logs_option();

        $options = MeprOptions::fetch();
        $logs    = $options->proactive_support_logs ?? [];
        if (!is_array($logs)) {
            $logs = [];
        }

        foreach ($logs as &$log) {
            if (!isset($log['admin_email'])) {
                $email = '';
                if (isset($log['user_id'])) {
                    $user = get_user_by('id', (int) $log['user_id']);
                    if ($user instanceof WP_User) {
                        $email = $user->user_email;
                    }
                }
                $log['admin_email'] = $email;
            }
        }
        unset($log);

        return $logs;
    }

    /**
     * Persist logs back to MeprOptions.
     *
     * @param array $logs Logs to store.
     *
     * @return void
     */
    private static function save_logs_store(array $logs): void
    {
        $options                         = MeprOptions::fetch(true);
        $options->proactive_support_logs = array_values($logs);
        $options->store(false);
    }

    /**
     * Migrate legacy logs option into MeprOptions.
     *
     * @return void
     */
    private static function maybe_migrate_logs_option(): void
    {
        $legacy = get_option(self::OPTION_KEY, null);
        if ($legacy === null) {
            return;
        }

        $options                         = MeprOptions::fetch(true);
        $options->proactive_support_logs = is_array($legacy) ? $legacy : [];
        $options->store(false);

        delete_option(self::OPTION_KEY);
    }
}
