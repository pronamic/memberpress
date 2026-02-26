<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProactiveSupportAnalyticsHelper
{
    /**
     * Retrieve dashboard data for proactive support analytics.
     *
     * @return array
     */
    public static function get_dashboard_data(): array
    {
        $table = MeprProactiveSupportHelper::table_name();

        $status_counts  = self::get_status_counts($table);
        $send_totals    = self::get_send_totals($table);
        $trigger_totals = self::get_trigger_totals($table);
        $admin_counts   = self::get_admin_counts($table);

        return [
            'status_counts'  => $status_counts,
            'send_totals'    => $send_totals,
            'trigger_totals' => $trigger_totals,
            'admin_counts'   => $admin_counts,
        ];
    }

    /**
     * Get counts grouped by status.
     *
     * @param string $table Table name.
     *
     * @return array
     */
    private static function get_status_counts($table): array
    {
        global $wpdb;
        $sql    = "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status";
        $rows   = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $output = [];
        foreach ((array) $rows as $row) {
            $output[$row['status']] = (int) $row['total'];
        }
        return $output;
    }

    /**
     * Get per-trigger totals, including number of sent emails.
     *
     * @param string $table Table name.
     *
     * @return array
     */
    private static function get_trigger_totals($table): array
    {
        global $wpdb;
        $sql    = "SELECT trigger_type, COUNT(*) AS total, SUM(CASE WHEN email_sent_at IS NOT NULL THEN 1 ELSE 0 END) AS sent_count, SUM(CASE WHEN send_count > 1 THEN send_count - 1 ELSE 0 END) AS resend_count FROM {$table} GROUP BY trigger_type ORDER BY total DESC";
        $rows   = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $output = [];
        foreach ((array) $rows as $row) {
            $output[] = [
                'trigger'      => $row['trigger_type'],
                'total'        => (int) $row['total'],
                'sent_count'   => (int) $row['sent_count'],
                'resend_count' => (int) $row['resend_count'],
            ];
        }
        return $output;
    }

    /**
     * Get total sends and resends.
     *
     * @param string $table Table name.
     *
     * @return array
     */
    private static function get_send_totals($table): array
    {
        global $wpdb;
        $sql = "SELECT COUNT(*) AS sent_total, SUM(CASE WHEN send_count > 1 THEN send_count - 1 ELSE 0 END) AS resend_total FROM {$table} WHERE email_sent_at IS NOT NULL";
        $row = $wpdb->get_row($sql, ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

        return [
            'sent_total'   => isset($row['sent_total']) ? (int) $row['sent_total'] : 0,
            'resend_total' => isset($row['resend_total']) ? (int) $row['resend_total'] : 0,
        ];
    }

    /**
     * Get counts of distinct admins.
     *
     * @param string $table Table name.
     *
     * @return array
     */
    private static function get_admin_counts($table): array
    {
        global $wpdb;
        $total_admins = count(MeprProactiveSupportHelper::get_notification_emails());

        $sql            = "SELECT COUNT(DISTINCT admin_email) FROM {$table} WHERE status = 'sent'";
        $admins_reached = (int) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

        return [
            'total_admins'   => $total_admins,
            'admins_reached' => $admins_reached,
        ];
    }
}
