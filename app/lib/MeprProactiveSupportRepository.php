<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProactiveSupportRepository
{
    /**
     * Insert a proactive support record.
     *
     * @param integer $user_id      WP user ID (optional).
     * @param string  $admin_email  Administrator email.
     * @param string  $trigger_type Trigger key.
     * @param string  $status       Status.
     * @param array   $meta         Additional metadata.
     *
     * @return integer|false
     */
    public static function insert($user_id, $admin_email, $trigger_type, $status = 'pending', $meta = [])
    {
        global $wpdb;
        $table = MeprProactiveSupportHelper::table_name();

        $email = strtolower(sanitize_email($admin_email));
        if (empty($email)) {
            return false;
        }

        $data = [
            'user_id'      => (int) $user_id,
            'admin_email'  => $email,
            'trigger_type' => sanitize_key($trigger_type),
            'status'       => sanitize_text_field($status),
            'meta'         => wp_json_encode($meta),
            'created_at'   => current_time('mysql', true),
        ];

        $inserted = $wpdb->insert($table, $data);

        if ($inserted !== false) {
            return (int) $wpdb->insert_id;
        }

        MeprUtils::debug_log(
            'Failed to insert proactive support record.',
            [
                'admin_email' => $email,
                'trigger'     => $data['trigger_type'],
                'error'       => $wpdb->last_error,
            ]
        );

        return false;
    }

    /**
     * Update a record.
     *
     * @param integer $id   Record ID.
     * @param array   $data Data to update.
     *
     * @return boolean
     */
    public static function update($id, $data): bool
    {
        global $wpdb;
        $table = MeprProactiveSupportHelper::table_name();

        if (!isset($data['updated_at'])) {
            $data['updated_at'] = current_time('mysql', true);
        }

        $updated = $wpdb->update($table, $data, ['id' => (int) $id]);

        if ($updated === false) {
            MeprUtils::debug_log(
                'Failed to update proactive support record.',
                [
                    'record_id' => (int) $id,
                    'error'     => $wpdb->last_error,
                ]
            );
        }

        return (false !== $updated);
    }

    /**
     * Find latest record for a trigger/email.
     *
     * @param string $admin_email Email address.
     * @param string $trigger     Trigger key.
     *
     * @return object|null
     */
    public static function latest_for_trigger($admin_email, $trigger): ?object
    {
        global $wpdb;
        $table = MeprProactiveSupportHelper::table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$table} WHERE admin_email = %s AND trigger_type = %s ORDER BY id DESC LIMIT 1",
                sanitize_email($admin_email),
                sanitize_key($trigger)
            )
        );
    }

    /**
     * Find a record by ID.
     *
     * @param integer $id Record ID.
     *
     * @return object|null
     */
    public static function find($id): ?object
    {
        global $wpdb;
        $table = MeprProactiveSupportHelper::table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Count sent notifications for an email.
     *
     * @param string $admin_email Email address.
     *
     * @return integer
     */
    public static function total_sent_for_email($admin_email): int
    {
        global $wpdb;
        $table = MeprProactiveSupportHelper::table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(*) FROM {$table} WHERE admin_email = %s AND status = %s",
                sanitize_email($admin_email),
                'sent'
            )
        );
    }

    /**
     * Retrieve the last sent timestamp for an email.
     *
     * @param string $admin_email Email address.
     *
     * @return string|null
     */
    public static function last_sent_at_for_email($admin_email): ?string
    {
        global $wpdb;
        $table = MeprProactiveSupportHelper::table_name();

        $value = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT MAX(email_sent_at) FROM {$table} WHERE admin_email = %s AND email_sent_at IS NOT NULL",
                sanitize_email($admin_email)
            )
        );

        if (empty($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Retrieve log entries for admin UI.
     *
     * @param integer $limit Limit.
     *
     * @return array
     */
    public static function latest($limit = 50): array
    {
        global $wpdb;
        $table = MeprProactiveSupportHelper::table_name();
        $limit = absint($limit);
        if ($limit <= 0) {
            $limit = 50;
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Fetch queue entries that need manual review.
     *
     * @param integer $limit Max rows.
     *
     * @return array
     */
    public static function queue($limit = 25): array
    {
        global $wpdb;
        $table = MeprProactiveSupportHelper::table_name();
        $limit = absint($limit);
        if ($limit <= 0) {
            $limit = 25;
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$table} WHERE status IN (%s,%s) ORDER BY created_at DESC LIMIT %d",
                'pending',
                'sent',
                $limit
            )
        );
    }

    /**
     * Search proactive support records with filters.
     *
     * @param array $args Filter args.
     *
     * @return array
     */
    public static function search($args = []): array
    {
        global $wpdb;
        $table = MeprProactiveSupportHelper::table_name();

        $defaults = [
            'trigger'    => '',
            'status'     => '',
            'email_like' => '',
            'limit'      => 50,
        ];

        $args  = wp_parse_args($args, $defaults);
        $limit = absint($args['limit']);
        if ($limit <= 0) {
            $limit = 50;
        }

        $clauses = [];

        if (!empty($args['trigger'])) {
            $clauses[] = $wpdb->prepare('trigger_type = %s', sanitize_key($args['trigger']));
        }

        if (!empty($args['status'])) {
            $clauses[] = $wpdb->prepare('status = %s', sanitize_key($args['status']));
        }

        if (!empty($args['email_like'])) {
            $like      = '%' . $wpdb->esc_like($args['email_like']) . '%';
            $clauses[] = $wpdb->prepare('admin_email LIKE %s', $like);
        }

        $where_sql = '';
        if (!empty($clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $clauses);
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$table} {$where_sql} ORDER BY id DESC LIMIT %d",
                $limit
            )
        );
    }
}
