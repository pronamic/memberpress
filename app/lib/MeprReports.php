<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprReports
{
    /**
     * Get the count of transactions based on status and optional date and product filters.
     *
     * @param string  $status  The transaction status.
     * @param boolean $day     The day filter.
     * @param boolean $month   The month filter.
     * @param boolean $year    The year filter.
     * @param mixed   $product The product filter.
     *
     * @return integer The count of transactions.
     */
    public static function get_transactions_count($status, $day = false, $month = false, $year = false, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition.
        $where_conditions[] = 'status = %s';
        $prepare_values[] = $status;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions if provided.
        if ($month) {
            $where_conditions[] = 'MONTH(created_at) = %d';
            $prepare_values[] = (int)$month;
        }

        if ($day) {
            $where_conditions[] = 'DAY(created_at) = %d';
            $prepare_values[] = (int)$day;
        }

        if ($year) {
            $where_conditions[] = 'YEAR(created_at) = %d';
            $prepare_values[] = (int)$year;
        }

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT COUNT(*) FROM {$mepr_db->transactions} WHERE {$where_clause}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        return (int)$wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get the total revenue based on optional date and product filters.
     *
     * @param boolean $month   The month filter.
     * @param boolean $day     The day filter.
     * @param boolean $year    The year filter.
     * @param mixed   $product The product filter.
     *
     * @return float The total revenue.
     */
    public static function get_revenue($month = false, $day = false, $year = false, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition.
        $where_conditions[] = 'status = %s';
        $prepare_values[] = MeprTransaction::$complete_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions if provided.
        if ($month) {
            $where_conditions[] = 'MONTH(created_at) = %d';
            $prepare_values[] = (int)$month;
        }

        if ($day) {
            $where_conditions[] = 'DAY(created_at) = %d';
            $prepare_values[] = (int)$day;
        }

        if ($year) {
            $where_conditions[] = 'YEAR(created_at) = %d';
            $prepare_values[] = (int)$year;
        }

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT SUM(amount) FROM {$mepr_db->transactions} WHERE {$where_clause}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        return $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get the total collected amount including tax based on optional date and product filters.
     *
     * @param boolean $month   The month filter.
     * @param boolean $day     The day filter.
     * @param boolean $year    The year filter.
     * @param mixed   $product The product filter.
     *
     * @return float The total collected amount.
     */
    public static function get_collected($month = false, $day = false, $year = false, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition (IN clause).
        $where_conditions[] = 'status IN (%s,%s)';
        $prepare_values[] = MeprTransaction::$complete_str;
        $prepare_values[] = MeprTransaction::$refunded_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions if provided.
        if ($month) {
            $where_conditions[] = 'MONTH(created_at) = %d';
            $prepare_values[] = (int)$month;
        }

        if ($day) {
            $where_conditions[] = 'DAY(created_at) = %d';
            $prepare_values[] = (int)$day;
        }

        if ($year) {
            $where_conditions[] = 'YEAR(created_at) = %d';
            $prepare_values[] = (int)$year;
        }

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT (SUM(amount)+SUM(tax_amount)) FROM {$mepr_db->transactions} WHERE {$where_clause}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        return $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get the total refunds including tax based on optional date and product filters.
     *
     * @param boolean $month   The month filter.
     * @param boolean $day     The day filter.
     * @param boolean $year    The year filter.
     * @param mixed   $product The product filter.
     *
     * @return float The total refunds.
     */
    public static function get_refunds($month = false, $day = false, $year = false, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition.
        $where_conditions[] = 'status = %s';
        $prepare_values[] = MeprTransaction::$refunded_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions if provided.
        if ($month) {
            $where_conditions[] = 'MONTH(created_at) = %d';
            $prepare_values[] = (int)$month;
        }

        if ($day) {
            $where_conditions[] = 'DAY(created_at) = %d';
            $prepare_values[] = (int)$day;
        }

        if ($year) {
            $where_conditions[] = 'YEAR(created_at) = %d';
            $prepare_values[] = (int)$year;
        }

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT (SUM(amount)+SUM(tax_amount)) FROM {$mepr_db->transactions} WHERE {$where_clause}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        return (float)$wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get the total taxes based on optional date and product filters.
     *
     * @param boolean $month   The month filter.
     * @param boolean $day     The day filter.
     * @param boolean $year    The year filter.
     * @param mixed   $product The product filter.
     *
     * @return float The total taxes.
     */
    public static function get_taxes($month = false, $day = false, $year = false, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition.
        $where_conditions[] = 'status = %s';
        $prepare_values[] = MeprTransaction::$complete_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions if provided.
        if ($month) {
            $where_conditions[] = 'MONTH(created_at) = %d';
            $prepare_values[] = (int)$month;
        }

        if ($day) {
            $where_conditions[] = 'DAY(created_at) = %d';
            $prepare_values[] = (int)$day;
        }

        if ($year) {
            $where_conditions[] = 'YEAR(created_at) = %d';
            $prepare_values[] = (int)$year;
        }

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT SUM(tax_amount) FROM {$mepr_db->transactions} WHERE {$where_clause}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        return $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get widget data for transactions based on type.
     *
     * @param string $type The type of data to retrieve ('amounts' or 'counts').
     *
     * @return array The widget data.
     */
    public static function get_widget_data($type = 'amounts')
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $results = [];
        $time    = time();

        $selecttype = ($type === 'amounts') ? 'SUM(amount)' : 'COUNT(*)';

        $q = "SELECT %s AS date,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = %s
              AND status = %s) as p,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = %s
              AND status = %s) as f,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = %s
              AND status = %s) as c,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = %s
              AND status = %s) as r";

        for ($i = 6; $i >= 0; $i--) {
            $ts          = $time - MeprUtils::days($i);
            $date        = gmdate('M j', $ts);
            $year        = gmdate('Y', $ts);
            $month       = gmdate('n', $ts);
            $day         = gmdate('j', $ts);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $results[$i] = $wpdb->get_row($wpdb->prepare(
                $q, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $date,
                $year,
                $month,
                $day,
                MeprTransaction::$payment_str,
                MeprTransaction::$pending_str,
                $year,
                $month,
                $day,
                MeprTransaction::$payment_str,
                MeprTransaction::$failed_str,
                $year,
                $month,
                $day,
                MeprTransaction::$payment_str,
                MeprTransaction::$complete_str,
                $year,
                $month,
                $day,
                MeprTransaction::$payment_str,
                MeprTransaction::$refunded_str,
            )); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        return $results;
    }

    /**
     * Get pie chart data for transactions based on year and month.
     *
     * @param boolean $year  The year filter.
     * @param boolean $month The month filter.
     *
     * @return array The pie chart data.
     */
    public static function get_pie_data($year = false, $month = false)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition.
        $where_conditions[] = 't.status = %s';
        $prepare_values[] = MeprTransaction::$complete_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions if provided.
        if ($year) {
            $where_conditions[] = 'YEAR(t.created_at) = %d';
            $prepare_values[] = (int)$year;
        }

        if ($month) {
            $where_conditions[] = 'MONTH(t.created_at) = %d';
            $prepare_values[] = (int)$month;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $wpdb->prepare(
            "SELECT p.post_title AS product, COUNT(t.id) AS transactions
            FROM {$mepr_db->transactions} AS t
            LEFT JOIN {$wpdb->posts} AS p
            ON t.product_id = p.ID
            WHERE {$where_clause}
            GROUP BY t.product_id",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return $wpdb->get_results($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get monthly data for transactions based on type, month, year, and product.
     *
     * @param string  $type    The type of data to retrieve ('amounts' or 'counts').
     * @param integer $month   The month filter.
     * @param integer $year    The year filter.
     * @param mixed   $product The product filter.
     * @param array   $q       Additional query parameters.
     *
     * @return array The monthly data.
     */
    public static function get_monthly_data($type, $month, $year, $product, $q = [])
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $results       = [];
        $days_in_month = gmdate('t', mktime(0, 0, 0, $month, 1, $year));
        $where         = MeprUtils::build_where_clause($q);

        // Build product clause.
        $product_clause = '';
        $has_product = ($product !== 'all');
        if ($has_product) {
            $product_clause = ' AND product_id = %d';
        }

        $selecttype = ($type === 'amounts') ? 'SUM(amount)' : 'COUNT(*)';

        $base_query = "SELECT %d AS day,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = %s
              AND status = %s{$product_clause}{$where}) as p,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = %s
              AND status = %s{$product_clause}{$where}) as f,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = %s
              AND status = %s{$product_clause}{$where}) as c,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = %s
              AND status = %s{$product_clause}{$where}) as r";

        if ($type === 'amounts') {
            $base_query .= ",
        (SELECT SUM(tax_amount)
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = %d
            AND MONTH(created_at) = %d
            AND DAY(created_at) = %d
            AND txn_type = %s
            AND status = %s{$product_clause}{$where}) as x,
        (SELECT (SUM(tax_amount)+SUM(amount))
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = %d
            AND MONTH(created_at) = %d
            AND DAY(created_at) = %d
            AND txn_type = %s
            AND status IN (%s,%s){$product_clause}{$where}) as t";
        }

        for ($i = 1; $i <= $days_in_month; $i++) {
            $params = [$i]; // Day number.

            // Parameters for p (pending).
            $params = array_merge($params, [$year, $month, $i, MeprTransaction::$payment_str, MeprTransaction::$pending_str]);
            if ($has_product) {
                $params[] = $product;
            }

            // Parameters for f (failed).
            $params = array_merge($params, [$year, $month, $i, MeprTransaction::$payment_str, MeprTransaction::$failed_str]);
            if ($has_product) {
                $params[] = $product;
            }

            // Parameters for c (complete).
            $params = array_merge($params, [$year, $month, $i, MeprTransaction::$payment_str, MeprTransaction::$complete_str]);
            if ($has_product) {
                $params[] = $product;
            }

            // Parameters for r (refunded).
            $params = array_merge($params, [$year, $month, $i, MeprTransaction::$payment_str, MeprTransaction::$refunded_str]);
            if ($has_product) {
                $params[] = $product;
            }

            if ($type === 'amounts') {
                // Parameters for x (tax amounts).
                $params = array_merge($params, [$year, $month, $i, MeprTransaction::$payment_str, MeprTransaction::$complete_str]);
                if ($has_product) {
                    $params[] = $product;
                }

                // Parameters for t (total amounts).
                $params = array_merge($params, [$year, $month, $i, MeprTransaction::$payment_str, MeprTransaction::$complete_str, MeprTransaction::$refunded_str]);
                if ($has_product) {
                    $params[] = $product;
                }
            }

            $results[$i] = $wpdb->get_row($wpdb->prepare($base_query, ...$params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
        }

        return $results;
    }

    /**
     * Get yearly data for transactions based on type, year, and product.
     *
     * @param string  $type    The type of data to retrieve ('amounts' or 'counts').
     * @param integer $year    The year filter.
     * @param mixed   $product The product filter.
     * @param array   $q       Additional query parameters.
     *
     * @return array The yearly data.
     */
    public static function get_yearly_data($type, $year, $product, $q = [])
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $results = [];
        $where   = MeprUtils::build_where_clause($q);

        // Build product clause.
        $product_clause = '';
        $has_product = ($product !== 'all');
        if ($has_product) {
            $product_clause = ' AND product_id = %d';
        }

        $selecttype = ($type === 'amounts') ? 'SUM(amount)' : 'COUNT(*)';

        $base_query = "
      SELECT %d AS month,
        (SELECT {$selecttype}
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = %d
            AND MONTH(created_at) = %d
            AND txn_type = %s
            AND status = %s{$product_clause}{$where}) as p,
        (SELECT {$selecttype}
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = %d
            AND MONTH(created_at) = %d
            AND txn_type = %s
            AND status = %s{$product_clause}{$where}) as f,
        (SELECT {$selecttype}
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = %d
            AND MONTH(created_at) = %d
            AND txn_type = %s
            AND status = %s{$product_clause}{$where}) as c,
        (SELECT {$selecttype}
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = %d
            AND MONTH(created_at) = %d
            AND txn_type = %s
            AND status = %s{$product_clause}{$where}) as r";

        if ($type === 'amounts') {
            $base_query .= ",
        (SELECT SUM(tax_amount)
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = %d
            AND MONTH(created_at) = %d
            AND txn_type = %s
            AND status = %s{$product_clause}{$where}) as x,
        (SELECT (SUM(tax_amount)+SUM(amount))
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = %d
            AND MONTH(created_at) = %d
            AND txn_type = %s
            AND status IN (%s,%s){$product_clause}{$where}) as t";
        }

        for ($i = 1; $i <= 12; $i++) {
            $params = [$i]; // Month number.

            // Parameters for p (pending).
            $params = array_merge($params, [$year, $i, MeprTransaction::$payment_str, MeprTransaction::$pending_str]);
            if ($has_product) {
                $params[] = $product;
            }

            // Parameters for f (failed).
            $params = array_merge($params, [$year, $i, MeprTransaction::$payment_str, MeprTransaction::$failed_str]);
            if ($has_product) {
                $params[] = $product;
            }

            // Parameters for c (complete).
            $params = array_merge($params, [$year, $i, MeprTransaction::$payment_str, MeprTransaction::$complete_str]);
            if ($has_product) {
                $params[] = $product;
            }

            // Parameters for r (refunded).
            $params = array_merge($params, [$year, $i, MeprTransaction::$payment_str, MeprTransaction::$refunded_str]);
            if ($has_product) {
                $params[] = $product;
            }

            if ($type === 'amounts') {
                // Parameters for x (tax amounts).
                $params = array_merge($params, [$year, $i, MeprTransaction::$payment_str, MeprTransaction::$complete_str]);
                if ($has_product) {
                    $params[] = $product;
                }

                // Parameters for t (total amounts).
                $params = array_merge($params, [$year, $i, MeprTransaction::$payment_str, MeprTransaction::$complete_str, MeprTransaction::$refunded_str]);
                if ($has_product) {
                    $params[] = $product;
                }
            }

            $results[$i] = $wpdb->get_row($wpdb->prepare($base_query, ...$params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
        }

        return $results;
    }

    /**
     * Get the first year of transactions.
     *
     * @return integer The first year of transactions.
     */
    public static function get_first_year()
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $year = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            'SELECT YEAR(created_at) ' .
            "FROM {$mepr_db->transactions} " .
            'WHERE txn_type = %s ' .
            'ORDER BY created_at ' .
            'LIMIT 1',
            MeprTransaction::$payment_str
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ($year) {
            return (int) $year;
        }

        return (int) gmdate('Y');
    }

    /**
     * Get the last year of transactions.
     *
     * @return integer The last year of transactions.
     */
    public static function get_last_year()
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $year = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            'SELECT YEAR(created_at) ' .
            "FROM {$mepr_db->transactions} " .
            'WHERE txn_type = %s ' .
            'ORDER BY created_at DESC ' .
            'LIMIT 1',
            MeprTransaction::$payment_str
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ($year) {
            return (int) $year;
        }

        return (int) gmdate('Y');
    }

    /**
     * Get the total count of members with transactions.
     *
     * @return integer The total count of members.
     */
    public static function get_total_members_count()
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $query = "SELECT COUNT(u.ID)
                FROM {$wpdb->users} AS u
               WHERE 0 <
                     (SELECT COUNT(tr.user_id)
                        FROM {$mepr_db->transactions} AS tr
                       WHERE tr.user_id=u.ID
                     )";

        return (int)$wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get the total count of WordPress users.
     *
     * @return integer The total count of WordPress users.
     */
    public static function get_total_wp_users_count()
    {
        $result = count_users();
        return $result['total_users'];
    }

    /**
     * Get the count of active members based on transaction status.
     *
     * @return integer The count of active members.
     */
    public static function get_active_members_count()
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $query = "
      SELECT COUNT(DISTINCT u.ID)
            FROM {$mepr_db->transactions} AS tr
            INNER JOIN {$wpdb->users} AS u
            ON u.ID=tr.user_id
            WHERE (tr.expires_at >= %s OR tr.expires_at IS NULL OR tr.expires_at = %s)
         AND tr.status IN (%s, %s)
    ";

        $query = $wpdb->prepare(
            $query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            MeprUtils::db_now(),
            MeprUtils::db_lifetime(),
            MeprTransaction::$complete_str,
            MeprTransaction::$confirmed_str
        );

        return $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get the count of inactive members based on transaction status.
     *
     * @return integer The count of inactive members.
     */
    public static function get_inactive_members_count()
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $query = "
      SELECT COUNT(u.ID)
        FROM {$wpdb->users} AS u
        WHERE u.ID NOT IN
          (SELECT tr.user_id
            FROM {$mepr_db->transactions} AS tr
            WHERE (tr.expires_at >= %s OR tr.expires_at IS NULL OR tr.expires_at = %s)
              AND tr.status IN (%s, %s)
          )
          AND 0 <
            (SELECT COUNT(tr2.user_id)
              FROM {$mepr_db->transactions} AS tr2
              WHERE tr2.user_id=u.ID
            )
    ";

        $query = $wpdb->prepare(
            $query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            MeprUtils::db_now(),
            MeprUtils::db_lifetime(),
            MeprTransaction::$complete_str,
            MeprTransaction::$confirmed_str
        );

        return $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get the count of free active members.
     *
     * @return integer The count of free active members.
     */
    public static function get_free_active_members_count()
    {
        return self::get_free_or_paid_active_members_count();
    }

    /**
     * Get the count of paid active members.
     *
     * @return integer The count of paid active members.
     */
    public static function get_paid_active_members_count()
    {
        return self::get_free_or_paid_active_members_count(true);
    }

    /**
     * Get the count of free or paid active members.
     *
     * @param boolean $paid Whether to count paid members.
     *
     * @return integer The count of free or paid active members.
     */
    private static function get_free_or_paid_active_members_count($paid = false)
    {
        global $wpdb;

        $sum_operator = $paid ? '>' : '<=';

        $mepr_db = new MeprDb();

        $query = "
      SELECT COUNT(*) AS famc
            FROM ( SELECT t.user_id AS user_id,
                (SUM(t.amount)+SUM(t.tax_amount)) AS lv
                FROM {$mepr_db->transactions} AS t
                WHERE t.status IN (%s,%s)
                AND ( t.expires_at = %s OR t.expires_at >= %s )
                AND t.user_id > 0
                GROUP BY t.user_id ) as lvsums
       WHERE lvsums.lv {$sum_operator} 0
    ";

        $query = $wpdb->prepare(
            $query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            MeprTransaction::$complete_str,
            MeprTransaction::$confirmed_str,
            MeprUtils::db_lifetime(),
            MeprUtils::db_now()
        );

        return $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get the average lifetime value of members.
     *
     * @return float The average lifetime value.
     */
    public static function get_average_lifetime_value()
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT ROUND(AVG(lv), 2) AS alv
            FROM ( SELECT SUM(t.amount) AS lv
            FROM {$mepr_db->transactions} AS t
            WHERE t.txn_type = %s
            AND t.status = %s
            GROUP BY t.user_id ) as lvsums",
            MeprTransaction::$payment_str,
            MeprTransaction::$complete_str
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Get the average number of payments per member.
     *
     * @return float The average number of payments per member.
     */
    public static function get_average_payments_per_member()
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT AVG(p.num) AS appm
            FROM ( SELECT COUNT(*) AS num
            FROM {$mepr_db->transactions} AS t
            WHERE t.status=%s
            AND t.txn_type=%s
            GROUP BY t.user_id ) as p",
            MeprTransaction::$complete_str,
            MeprTransaction::$payment_str
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Get the percentage of members who rebill.
     *
     * @return float The percentage of members who rebill.
     */
    public static function get_percentage_members_who_rebill()
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        // $q = "
        // SELECT (
        // SELECT COUNT(p.num) AS up
        // FROM ( SELECT COUNT(*) AS num
        // FROM {$mepr_db->transactions} AS t
        // WHERE t.status=%s
        // AND ( SELECT tr.id
        // FROM {$mepr_db->transactions} AS tr
        // WHERE tr.status=%s
        // AND tr.user_id=t.user_id
        // AND tr.expires_at <> '0000-00-00 00:00:00'
        // AND tr.expires_at < %s
        // ORDER BY tr.id ASC
        // LIMIT 1 ) IS NOT NULL
        // GROUP BY t.user_id ) as p
        // WHERE p.num > 1
        // ) / (
        // SELECT COUNT(p.num) AS up
        // FROM ( SELECT COUNT(*) AS num
        // FROM {$mepr_db->transactions} AS t
        // WHERE t.status=%s
        // AND ( SELECT tr.id
        // FROM {$mepr_db->transactions} AS tr
        // WHERE tr.status=%s
        // AND tr.user_id=t.user_id
        // AND tr.expires_at <> '0000-00-00 00:00:00'
        // AND tr.expires_at < %s
        // ORDER BY tr.id ASC
        // LIMIT 1 ) IS NOT NULL
        // GROUP BY t.user_id ) as p
        // ) * 100
        // ";
        // $q = $wpdb->prepare($q,
        // MeprTransaction::$complete_str,
        // MeprTransaction::$complete_str,
        // $now,
        // MeprTransaction::$complete_str,
        // MeprTransaction::$complete_str,
        // $now);
        $q = "
      SELECT COUNT(*) AS num
            FROM {$mepr_db->transactions} AS t
            WHERE t.status = %s
            AND t.txn_type = %s
            AND ( SELECT tr.id
                FROM {$mepr_db->transactions} AS tr
                WHERE tr.status=%s
                AND tr.txn_type=%s
                AND tr.user_id=t.user_id
                AND tr.expires_at <> '0000-00-00 00:00:00'
                AND tr.expires_at < %s
                ORDER BY tr.id ASC
                LIMIT 1 ) IS NOT NULL
       GROUP BY t.user_id
    ";

        $q = $wpdb->prepare(
            $q, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            MeprTransaction::$complete_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$complete_str,
            MeprTransaction::$payment_str,
            gmdate('Y-m-d H:i:s')
        );

        $res = $wpdb->get_col($q); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

        if (empty($res)) {
            return 0;
        }

        $gt_two = 0;
        foreach ($res as $num) {
            if ($num > 1) {
                $gt_two++;
            }
        }

        return (($gt_two / count($res)) * 100);
    }

    /**
     * Format a date based on month, day, and year parameters.
     *
     * @param integer $month  The month value.
     * @param integer $day    The day value.
     * @param integer $year   The year value.
     * @param string  $format The date format string.
     *
     * @return string The formatted date.
     */
    public static function make_table_date($month, $day, $year, $format = 'm/d/Y')
    {
        $ts = mktime(0, 0, 1, $month, $day, $year);
        return MeprUtils::get_date_from_ts($ts, $format);
    }

    /**
     * Get subscription statistics based on creation date.
     *
     * @param boolean $created_since The creation date filter.
     *
     * @return object The subscription statistics.
     */
    public static function subscription_stats($created_since = false)
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $q = $wpdb->prepare(
            "
        SELECT IFNULL(SUM(IF(status=%s,1,0)), 0) AS pending,
               IFNULL(SUM(IF(status=%s,1,0)), 0) AS enabled,
               IFNULL(SUM(IF(status=%s,1,0)), 0) AS suspended,
               IFNULL(SUM(IF(status=%s,1,0)), 0) AS cancelled,
               IFNULL(ROUND(AVG(IF(status=%s,total,0)),2), 0.00) AS pending_average_total,
               IFNULL(ROUND(AVG(IF(status=%s,total,0)),2), 0.00) AS enabled_average_total,
               IFNULL(ROUND(AVG(IF(status=%s,total,0)),2), 0.00) AS suspended_average_total,
               IFNULL(ROUND(AVG(IF(status=%s,total,0)),2), 0.00) AS cancelled_average_total,
               IFNULL(ROUND(SUM(IF(status=%s,total,0)),2), 0.00) AS pending_sum_total,
               IFNULL(ROUND(SUM(IF(status=%s,total,0)),2), 0.00) AS enabled_sum_total,
               IFNULL(ROUND(SUM(IF(status=%s,total,0)),2), 0.00) AS suspended_sum_total,
               IFNULL(ROUND(SUM(IF(status=%s,total,0)),2), 0.00) AS cancelled_sum_total
          FROM {$mepr_db->subscriptions}
      ",
            MeprSubscription::$pending_str,
            MeprSubscription::$active_str,
            MeprSubscription::$suspended_str,
            MeprSubscription::$cancelled_str,
            MeprSubscription::$pending_str,
            MeprSubscription::$active_str,
            MeprSubscription::$suspended_str,
            MeprSubscription::$cancelled_str,
            MeprSubscription::$pending_str,
            MeprSubscription::$active_str,
            MeprSubscription::$suspended_str,
            MeprSubscription::$cancelled_str
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (!empty($created_since)) {
            $q .= $wpdb->prepare('WHERE created_at >= %s', $created_since);
        }

        $stats = $wpdb->get_row($q, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

        $wpdb->query('SET SQL_BIG_SELECTS=1'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $q = $wpdb->prepare(
            "
      SELECT COUNT(*) AS active,
             IFNULL(SUM(IF(s.status=%s,1,0)), 0) AS active_and_enabled
        FROM {$mepr_db->subscriptions} AS s
        JOIN {$mepr_db->transactions} AS t
          ON t.subscription_id=s.id
         AND t.status IN (%s,%s)
         AND ( t.expires_at = %s
             OR ( t.expires_at <> %s
                AND t.expires_at=(
                  SELECT MAX(t2.expires_at)
                    FROM {$mepr_db->transactions} as t2
                   WHERE t2.subscription_id=s.id
                     AND t2.status IN (%s,%s)
                )
             )
         )
      ",
            MeprSubscription::$active_str,
            MeprTransaction::$confirmed_str,
            MeprTransaction::$complete_str,
            MeprUtils::db_lifetime(),
            MeprUtils::db_lifetime(),
            MeprTransaction::$confirmed_str,
            MeprTransaction::$complete_str
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (!empty($created_since)) {
            $q .= $wpdb->prepare('WHERE s.created_at >= %s', $created_since);
        }

        // Because we're dealing with sums & counts $stats should always be an array.
        $stats = array_merge($stats, $wpdb->get_row($q, ARRAY_A)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

        return (object)$stats;
    }

    /**
     * Get transaction statistics based on creation date.
     *
     * @param boolean $created_since The creation date filter.
     *
     * @return object The transaction statistics.
     */
    public static function transaction_stats($created_since = false)
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $q = $wpdb->prepare(
            "
        SELECT IFNULL(SUM(IF(txn_type=%s AND status=%s,1,0)), 0) AS pending,
               IFNULL(SUM(IF(txn_type=%s AND status=%s,1,0)), 0) AS failed,
               IFNULL(SUM(IF(txn_type=%s AND status=%s,1,0)), 0) AS complete,
               IFNULL(SUM(IF(txn_type=%s AND status=%s,1,0)), 0) AS refunded,
               IFNULL(ROUND(AVG(IF(txn_type=%s AND status=%s,total,0)),2), 0.00) AS pending_average_total,
               IFNULL(ROUND(AVG(IF(txn_type=%s AND status=%s,total,0)),2), 0.00) AS failed_average_total,
               IFNULL(ROUND(AVG(IF(txn_type=%s AND status=%s,total,0)),2), 0.00) AS complete_average_total,
               IFNULL(ROUND(AVG(IF(txn_type=%s AND status=%s,total,0)),2), 0.00) AS refunded_average_total,
               IFNULL(ROUND(SUM(IF(txn_type=%s AND status=%s,total,0)),2), 0.00) AS pending_sum_total,
               IFNULL(ROUND(SUM(IF(txn_type=%s AND status=%s,total,0)),2), 0.00) AS failed_sum_total,
               IFNULL(ROUND(SUM(IF(txn_type=%s AND status=%s,total,0)),2), 0.00) AS complete_sum_total,
               IFNULL(ROUND(SUM(IF(txn_type=%s AND status=%s,total,0)),2), 0.00) AS refunded_sum_total
          FROM {$mepr_db->transactions}
      ",
            MeprTransaction::$payment_str,
            MeprTransaction::$pending_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$failed_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$complete_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$refunded_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$pending_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$failed_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$complete_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$refunded_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$pending_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$failed_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$complete_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$refunded_str
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (!empty($created_since)) {
            $q .= $wpdb->prepare('WHERE created_at >= %s', $created_since);
        }

        return $wpdb->get_row($q); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get refund event statistics based on creation date.
     *
     * @param boolean $created_since The creation date filter.
     *
     * @return object The refund event statistics.
     */
    public static function refund_event_stats($created_since = false)
    {
        return self::event_stats('transaction-refunded', 'transactions', $created_since);
    }

    /**
     * Get cancel event statistics based on creation date.
     *
     * @param boolean $created_since The creation date filter.
     *
     * @return object The cancel event statistics.
     */
    public static function cancel_event_stats($created_since = false)
    {
        return self::event_stats('subscription-stopped', 'subscriptions', $created_since);
    }

    /**
     * Get upgrade event statistics based on creation date.
     *
     * @param boolean $created_since The creation date filter.
     *
     * @return object The upgrade event statistics.
     */
    public static function upgrade_event_stats($created_since = false)
    {
        return self::event_stats('subscription-upgraded', 'subscriptions', $created_since);
    }

    // Cancellation events.
    /**
     * Get event statistics based on event type and creation date.
     *
     * @param string  $event         The event type.
     * @param string  $event_type    The event type identifier.
     * @param boolean $created_since The creation date filter.
     *
     * @return object The event statistics.
     */
    public static function event_stats($event, $event_type, $created_since = false)
    {
        global $wpdb;

        $mepr_db   = MeprDb::fetch();
        $tablename = MeprEvent::get_tablename($event_type);

        if ($event_type === MeprEvent::$users_str) {
            return false;
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $q = $wpdb->prepare(
            "
        SELECT COUNT(*) AS obj_count,
               IFNULL(SUM(o.total), 0.00) AS obj_total
          FROM {$tablename} AS o
          JOIN {$mepr_db->events} AS e
            ON e.evt_id=o.id
         WHERE e.evt_id_type=%s
           AND e.event=%s
      ",
            $event_type,
            $event
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (!empty($created_since)) {
            $q .= $wpdb->prepare('AND e.created_at >= %s', $created_since);
        }

        return $wpdb->get_row($q); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get revenue statistics for a specific payment gateway.
     *
     * @param integer $payment_method_id The payment method ID.
     *
     * @return object The revenue statistics.
     */
    public static function gateway_revenue_stats($payment_method_id)
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $q = $wpdb->prepare(
            "
        SELECT IFNULL(ROUND(SUM(IF(created_at > DATE_SUB(NOW(), INTERVAL 7 DAY),total,0)),2), 0.00) AS week_revenue,
               IFNULL(ROUND(SUM(IF(created_at > DATE_SUB(NOW(), INTERVAL 30 DAY),total,0)),2), 0.00) AS month_revenue,
               IFNULL(ROUND(SUM(IF(created_at > DATE_SUB(NOW(), INTERVAL 365 DAY),total,0)),2), 0.00) AS year_revenue,
               IFNULL(ROUND(SUM(total),2), 0.00) AS lifetime_revenue
          FROM {$mepr_db->transactions}
         WHERE txn_type=%s AND status IN (%s,%s) AND gateway=%s
      ",
            MeprTransaction::$payment_str,
            MeprTransaction::$complete_str,
            MeprTransaction::$refunded_str,
            $payment_method_id
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $rev_stats = $wpdb->get_row($q, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $q = $wpdb->prepare(
            "
        SELECT IFNULL(ROUND(SUM(IF(e.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY),t.total,0)),2), 0.00) AS week_refunds_total,
               IFNULL(ROUND(SUM(IF(e.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY),t.total,0)),2), 0.00) AS month_refunds_total,
               IFNULL(ROUND(SUM(IF(e.created_at > DATE_SUB(NOW(), INTERVAL 365 DAY),t.total,0)),2), 0.00) AS year_refunds_total,
               IFNULL(ROUND(SUM(t.total),2), 0.00) AS lifetime_refunds_total
          FROM {$mepr_db->transactions} AS t
          JOIN {$mepr_db->events} AS e
            ON e.evt_id=t.id
         WHERE e.evt_id_type=%s
           AND e.event=%s
           AND t.gateway=%s
      ",
            'transactions',
            'transaction-refunded',
            $payment_method_id
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $ref_stats = $wpdb->get_row($q, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

        return (object)array_merge($rev_stats, $ref_stats);
    }

    /**
     * Get recurring revenue based on optional date and product filters.
     *
     * @param boolean $month   The month filter.
     * @param boolean $day     The day filter.
     * @param boolean $year    The year filter.
     * @param mixed   $product The product filter.
     *
     * @return float The recurring revenue.
     */
    public static function get_recurring_revenue($month = false, $day = false, $year = false, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add subscription subquery conditions.
        $where_conditions[] = 'subscription_id IN (
              SELECT subscription_id
                FROM ' . $mepr_db->transactions . '
                WHERE subscription_id > 0
                AND (status = %s OR status = %s)
                AND txn_type = %s
                GROUP BY subscription_id
                HAVING COUNT(*) > 1
            )';
        $prepare_values[] = MeprTransaction::$complete_str;
        $prepare_values[] = MeprTransaction::$refunded_str;
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add status condition.
        $where_conditions[] = 'status = %s';
        $prepare_values[] = MeprTransaction::$complete_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions if provided.
        if ($month) {
            $where_conditions[] = 'MONTH(created_at) = %d';
            $prepare_values[] = (int)$month;
        }

        if ($day) {
            $where_conditions[] = 'DAY(created_at) = %d';
            $prepare_values[] = (int)$day;
        }

        if ($year) {
            $where_conditions[] = 'YEAR(created_at) = %d';
            $prepare_values[] = (int)$year;
        }

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT SUM(amount) FROM {$mepr_db->transactions} WHERE {$where_clause}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        return $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get transaction counts for a date range based on status collection and product.
     *
     * @param array              $status_collection The collection of statuses.
     * @param \DateTimeImmutable $start_date        The start date.
     * @param \DateTimeImmutable $end_date          The end date.
     * @param mixed              $product           The product filter.
     *
     * @return array The transaction counts.
     */
    public static function get_date_range_transactions_counts(array $status_collection, \DateTimeImmutable $start_date, \DateTimeImmutable $end_date, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition (IN clause).
        $status_placeholders = implode(',', array_fill(0, count($status_collection), '%s'));
        $where_conditions[] = "status IN ({$status_placeholders})";
        $prepare_values = array_merge($prepare_values, $status_collection);

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions.
        $where_conditions[] = 'DATE(created_at) >= %s';
        $prepare_values[] = $start_date->format('Y-m-d');

        $where_conditions[] = 'DATE(created_at) <= %s';
        $prepare_values[] = $end_date->format('Y-m-d');

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT COUNT(*) as total_count, status FROM {$mepr_db->transactions} WHERE {$where_clause} GROUP BY status",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        $results = $wpdb->get_results($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

        $data = [];
        foreach ($status_collection as $status) {
            $data[$status] = 0;
        }

        if (! empty($results)) {
            foreach ($results as $row) {
                $data[$row->status] = $row->total_count;
            }
        }

        return $data;
    }

    /**
     * Get revenue for a date range based on product.
     *
     * @param \DateTimeImmutable $start_date The start date.
     * @param \DateTimeImmutable $end_date   The end date.
     * @param mixed              $product    The product filter.
     *
     * @return float The revenue for the date range.
     */
    public static function get_date_range_revenue(\DateTimeImmutable $start_date, \DateTimeImmutable $end_date, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition.
        $where_conditions[] = 'status = %s';
        $prepare_values[] = MeprTransaction::$complete_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions.
        $where_conditions[] = 'DATE(created_at) >= %s';
        $prepare_values[] = $start_date->format('Y-m-d');

        $where_conditions[] = 'DATE(created_at) <= %s';
        $prepare_values[] = $end_date->format('Y-m-d');

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT SUM(amount) FROM {$mepr_db->transactions} WHERE {$where_clause}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        return $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get refunds for a date range based on product.
     *
     * @param \DateTimeImmutable $start_date The start date.
     * @param \DateTimeImmutable $end_date   The end date.
     * @param mixed              $product    The product filter.
     *
     * @return float The refunds for the date range.
     */
    public static function get_date_range_refunds(\DateTimeImmutable $start_date, \DateTimeImmutable $end_date, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition.
        $where_conditions[] = 'status = %s';
        $prepare_values[] = MeprTransaction::$refunded_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions.
        $where_conditions[] = 'DATE(created_at) >= %s';
        $prepare_values[] = $start_date->format('Y-m-d');

        $where_conditions[] = 'DATE(created_at) <= %s';
        $prepare_values[] = $end_date->format('Y-m-d');

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT (SUM(amount)+SUM(tax_amount)) FROM {$mepr_db->transactions} WHERE {$where_clause}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        return $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get monthly dataset for transactions based on type, month, year, and product.
     *
     * @param string  $type    The type of data to retrieve ('amounts' or 'counts').
     * @param integer $month   The month filter.
     * @param integer $year    The year filter.
     * @param mixed   $product The product filter.
     * @param array   $q       Additional query parameters.
     *
     * @return array The monthly dataset.
     */
    public static function get_monthly_dataset($type, $month, $year, $product, $q = [])
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $results       = [];
        $days_in_month = gmdate('t', mktime(0, 0, 0, $month, 1, $year));
        $where         = MeprUtils::build_where_clause($q);

        $selecttype = ($type === 'amounts') ? 'SUM(amount)' : 'COUNT(*)';

        // Build base conditions for all queries.
        $base_conditions = [];
        $base_values = [];

        // Add date conditions.
        $base_conditions[] = 'YEAR(created_at) = %d';
        $base_values[] = (int)$year;

        $base_conditions[] = 'MONTH(created_at) = %d';
        $base_values[] = (int)$month;

        // Add transaction type condition.
        $base_conditions[] = 'txn_type = %s';
        $base_values[] = MeprTransaction::$payment_str;

        // Add product condition if provided.
        if ($product !== 'all') {
            $base_conditions[] = 'product_id = %d';
            $base_values[] = (int)$product;
        }

        // Add additional where conditions if provided.
        if (!empty($where)) {
            $base_conditions[] = $where;
        }

        $base_where = implode(' AND ', $base_conditions);

        $queries = [];

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $queries['p'] = $wpdb->prepare(
            "SELECT {$selecttype} as mepr_value, DAY(created_at) as mepr_day
            FROM {$mepr_db->transactions}
            WHERE {$base_where} AND status = %s
            GROUP BY DAY(created_at)",
            ...array_merge($base_values, [MeprTransaction::$pending_str])
        );

        $queries['f'] = $wpdb->prepare(
            "SELECT {$selecttype} as mepr_value, DAY(created_at) as mepr_day
            FROM {$mepr_db->transactions}
            WHERE {$base_where} AND status = %s
            GROUP BY DAY(created_at)",
            ...array_merge($base_values, [MeprTransaction::$failed_str])
        );

        $queries['c'] = $wpdb->prepare(
            "SELECT {$selecttype} as mepr_value, DAY(created_at) as mepr_day
            FROM {$mepr_db->transactions}
            WHERE {$base_where} AND status = %s
            GROUP BY DAY(created_at)",
            ...array_merge($base_values, [MeprTransaction::$complete_str])
        );

        $queries['r'] = $wpdb->prepare(
            "SELECT {$selecttype} as mepr_value, DAY(created_at) as mepr_day
            FROM {$mepr_db->transactions}
            WHERE {$base_where} AND status = %s
            GROUP BY DAY(created_at)",
            ...array_merge($base_values, [MeprTransaction::$refunded_str])
        );

        if ($type === 'amounts') {
            $queries['x'] = $wpdb->prepare(
                "SELECT SUM(tax_amount) as mepr_value, DAY(created_at) as mepr_day
                FROM {$mepr_db->transactions}
                WHERE {$base_where} AND status = %s
                GROUP BY DAY(created_at)",
                ...array_merge($base_values, [MeprTransaction::$complete_str])
            );

            $queries['t'] = $wpdb->prepare(
                "SELECT (SUM(tax_amount)+SUM(amount)) as mepr_value, DAY(created_at) as mepr_day
                FROM {$mepr_db->transactions}
                WHERE {$base_where} AND status IN (%s,%s)
                GROUP BY DAY(created_at)",
                ...array_merge($base_values, [MeprTransaction::$complete_str, MeprTransaction::$refunded_str])
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        }

        foreach ($queries as $type => $sql) {
            for ($i = 1; $i <= $days_in_month; $i++) {
                if (! isset($results[$i])) {
                    $results[$i]      = new stdClass();
                    $results[$i]->day = $i;
                }

                $results[$i]->$type = 0;
            }

            $resultset = $wpdb->get_results($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

            if (! empty($resultset)) {
                foreach ($resultset as $row) {
                    $results[$row->mepr_day]->$type = $row->mepr_value;
                }
            }
        }

        return $results;
    }

    /**
     * Format the dataset for Mepr transactions.
     *
     * @param array $results The results to format.
     *
     * @return array The formatted dataset.
     */
    protected static function format_mepr_dataset($results)
    {
        $ds = [];
        if (! empty($results)) {
            foreach ($results as $row) {
                if (isset($row->mepr_day) && isset($row->mepr_value)) {
                    $ds[$row->mepr_day] = $row->mepr_value;
                }

                if (isset($row->mepr_month) && isset($row->mepr_value)) {
                    $ds[$row->mepr_month] = $row->mepr_value;
                }
            }
        }

        return $ds;
    }

    /**
     * Get revenue dataset for a specific month and year based on product.
     *
     * @param integer $month   The month filter.
     * @param integer $year    The year filter.
     * @param mixed   $product The product filter.
     *
     * @return array The revenue dataset.
     */
    public static function get_revenue_dataset($month, $year, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition.
        $where_conditions[] = 'status = %s';
        $prepare_values[] = MeprTransaction::$complete_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions if provided.
        if ($month) {
            $where_conditions[] = 'MONTH(created_at) = %d';
            $prepare_values[] = (int)$month;
        }

        if ($year) {
            $where_conditions[] = 'YEAR(created_at) = %d';
            $prepare_values[] = (int)$year;
        }

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);
        $groupby = !empty($month) ? 'GROUP BY DAY(created_at)' : 'GROUP BY MONTH(created_at)';
        $mepr_col = !empty($month) ? 'DAY(created_at) as mepr_day' : 'MONTH(created_at) as mepr_month';

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $wpdb->prepare(
            "SELECT SUM(amount) as mepr_value, {$mepr_col} FROM {$mepr_db->transactions} WHERE {$where_clause} {$groupby}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return self::format_mepr_dataset($wpdb->get_results($query)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get taxes dataset for a specific month and year based on product.
     *
     * @param boolean $month   The month filter.
     * @param boolean $year    The year filter.
     * @param mixed   $product The product filter.
     *
     * @return array The taxes dataset.
     */
    public static function get_taxes_dataset($month = false, $year = false, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition.
        $where_conditions[] = 'status = %s';
        $prepare_values[] = MeprTransaction::$complete_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions if provided.
        if ($month) {
            $where_conditions[] = 'MONTH(created_at) = %d';
            $prepare_values[] = (int)$month;
        }

        if ($year) {
            $where_conditions[] = 'YEAR(created_at) = %d';
            $prepare_values[] = (int)$year;
        }

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);
        $groupby = !empty($month) ? 'GROUP BY DAY(created_at)' : 'GROUP BY MONTH(created_at)';
        $mepr_col = !empty($month) ? 'DAY(created_at) as mepr_day' : 'MONTH(created_at) as mepr_month';

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT SUM(tax_amount) as mepr_value, {$mepr_col} FROM {$mepr_db->transactions} WHERE {$where_clause} {$groupby}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        return self::format_mepr_dataset($wpdb->get_results($query)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get refunds dataset for a specific month and year based on product.
     *
     * @param boolean $month   The month filter.
     * @param boolean $year    The year filter.
     * @param mixed   $product The product filter.
     *
     * @return array The refunds dataset.
     */
    public static function get_refunds_dataset($month = false, $year = false, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition.
        $where_conditions[] = 'status = %s';
        $prepare_values[] = MeprTransaction::$refunded_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions if provided.
        if ($month) {
            $where_conditions[] = 'MONTH(created_at) = %d';
            $prepare_values[] = (int)$month;
        }

        if ($year) {
            $where_conditions[] = 'YEAR(created_at) = %d';
            $prepare_values[] = (int)$year;
        }

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);
        $groupby = !empty($month) ? 'GROUP BY DAY(created_at)' : 'GROUP BY MONTH(created_at)';
        $mepr_col = !empty($month) ? 'DAY(created_at) as mepr_day' : 'MONTH(created_at) as mepr_month';

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT (SUM(amount)+SUM(tax_amount)) as mepr_value, {$mepr_col} FROM {$mepr_db->transactions} WHERE {$where_clause} {$groupby}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        return self::format_mepr_dataset($wpdb->get_results($query)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get collected dataset for a specific month and year based on product.
     *
     * @param boolean $month   The month filter.
     * @param boolean $year    The year filter.
     * @param mixed   $product The product filter.
     *
     * @return array The collected dataset.
     */
    public static function get_collected_dataset($month = false, $year = false, $product = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $where_conditions = [];
        $prepare_values = [];

        // Add status condition (IN clause).
        $where_conditions[] = 'status IN (%s,%s)';
        $prepare_values[] = MeprTransaction::$complete_str;
        $prepare_values[] = MeprTransaction::$refunded_str;

        // Add transaction type condition.
        $where_conditions[] = 'txn_type = %s';
        $prepare_values[] = MeprTransaction::$payment_str;

        // Add date conditions if provided.
        if ($month) {
            $where_conditions[] = 'MONTH(created_at) = %d';
            $prepare_values[] = (int)$month;
        }

        if ($year) {
            $where_conditions[] = 'YEAR(created_at) = %d';
            $prepare_values[] = (int)$year;
        }

        // Add product condition if provided.
        if (isset($product) && $product !== 'all') {
            $where_conditions[] = 'product_id = %d';
            $prepare_values[] = (int)$product;
        }

        $where_clause = implode(' AND ', $where_conditions);
        $groupby = !empty($month) ? 'GROUP BY DAY(created_at)' : 'GROUP BY MONTH(created_at)';
        $mepr_col = !empty($month) ? 'DAY(created_at) as mepr_day' : 'MONTH(created_at) as mepr_month';

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT (SUM(amount)+SUM(tax_amount)) as mepr_value, {$mepr_col} FROM {$mepr_db->transactions} WHERE {$where_clause} {$groupby}",
            ...$prepare_values
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        return self::format_mepr_dataset($wpdb->get_results($query)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Get yearly dataset for transactions based on type, year, and product.
     *
     * @param string  $type    The type of data to retrieve ('amounts' or 'counts').
     * @param integer $year    The year filter.
     * @param mixed   $product The product filter.
     * @param array   $q       Additional query parameters.
     *
     * @return array The yearly dataset.
     */
    public static function get_yearly_dataset($type, $year, $product, $q = [])
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $results    = [];
        $andproduct = ($product === 'all') ? '' : $wpdb->prepare(' AND product_id = %d', $product);
        $where      = MeprUtils::build_where_clause($q);

        $selecttype = ($type === 'amounts') ? 'SUM(amount)' : 'COUNT(*)';

        $queries      = [];
        $queries['p'] = "SELECT {$selecttype} as mepr_value, MONTH(created_at) as mepr_month
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND txn_type = '" . MeprTransaction::$payment_str . "'
            AND status = '" . MeprTransaction::$pending_str . "'
            {$andproduct}{$where}
            GROUP BY MONTH(created_at)";

        $queries['f'] = "SELECT {$selecttype} as mepr_value, MONTH(created_at) as mepr_month
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND txn_type = '" . MeprTransaction::$payment_str . "'
            AND status = '" . MeprTransaction::$failed_str . "'
            {$andproduct}{$where}
            GROUP BY MONTH(created_at)";

        $queries['c'] = "SELECT {$selecttype} as mepr_value, MONTH(created_at) as mepr_month
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND txn_type = '" . MeprTransaction::$payment_str . "'
            AND status = '" . MeprTransaction::$complete_str . "'
            {$andproduct}{$where}
            GROUP BY MONTH(created_at)";

        $queries['r'] = "SELECT {$selecttype} as mepr_value, MONTH(created_at) as mepr_month
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND txn_type = '" . MeprTransaction::$payment_str . "'
            AND status = '" . MeprTransaction::$refunded_str . "'
            {$andproduct}{$where}
            GROUP BY MONTH(created_at)";


        if ($type === 'amounts') {
            $queries['x'] = "SELECT SUM(tax_amount) as mepr_value, MONTH(created_at) as mepr_month
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND txn_type = '" . MeprTransaction::$payment_str . "'
            AND status = '" . MeprTransaction::$complete_str . "'
            {$andproduct}{$where}
            GROUP BY MONTH(created_at)";

            $queries['t'] = "SELECT (SUM(tax_amount)+SUM(amount)) as mepr_value, MONTH(created_at) as mepr_month
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND txn_type = '" . MeprTransaction::$payment_str . "'
            AND status IN ('" . MeprTransaction::$complete_str . "', '" . MeprTransaction::$refunded_str . "')
            {$andproduct}{$where}
            GROUP BY MONTH(created_at)";
        }

        foreach ($queries as $type => $sql) {
            for ($i = 1; $i <= 12; $i++) {
                if (! isset($results[$i])) {
                    $results[$i]        = new stdClass();
                    $results[$i]->month = $i;
                }

                $results[$i]->$type = 0;
            }

            $resultset = $wpdb->get_results($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

            if (! empty($resultset)) {
                foreach ($resultset as $row) {
                    $results[$row->mepr_month]->$type = $row->mepr_value;
                }
            }
        }

        return $results;
    }
}
