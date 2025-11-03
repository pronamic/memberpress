<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Helper class for DRM debug functionality
 *
 * Provides methods to display and analyze DRM-related information in the WordPress site health section
 */
class MeprDrmDebugHelper
{
    /**
     * Adds DRM debug information to the WordPress site health page
     *
     * @param array $tests Array of existing site health tests.
     *
     * @return array Modified array of site health tests including DRM debug information
     */
    public static function site_health_debug_status($tests)
    {
        if (!isset($_GET['mepr_drm_debug']) || !MeprUtils::is_logged_in_and_an_admin()) {
            return $tests;
        }

        $tests['direct']['mepr_drm_debug'] = [
            'label' => __('MemberPress - DRM Info', 'memberpress'),
            'test' => ['MeprDrmDebugHelper', 'run_drm_debug'],
        ];

        return $tests;
    }

    /**
     * Main method to generate the DRM debug report
     *
     * Combines all debug sections and adds download functionality
     *
     * @return array Site health test result array containing the debug information
     */
    public static function run_drm_debug()
    {
        $content = '<div id="mepr-drm-debug-content" class="mepr-drm-debug">';

        // Build content from separate sections.
        $content .= self::get_drm_status_section();
        $content .= self::get_app_fee_section();
        $content .= self::get_drm_options_section();
        $content .= self::get_drm_events_section();
        $content .= self::get_debug_styles();
        $content .= '</div>';

        // Get HTML and JS for the download button.
        $generate_report_parts = self::generate_html_js_button();
        if (!empty($generate_report_parts['html']) && !empty($generate_report_parts['js'])) {
            $content = $generate_report_parts['html'] . $content . $generate_report_parts['js'];
        }

        return [
            'label' => __('DRM Debug Information', 'memberpress'),
            'status' => 'critical',
            'badge' => [
                'label' => 'DRM',
                'color' => 'orange',
            ],
            'description' => $content,
            'actions' => '',
            'test' => 'run_drm_debug',
        ];
    }

    /**
     * Generates the HTML button and JavaScript for downloading the debug report
     *
     * @return array {
     * Array containing button HTML and JavaScript
     *
     * @type string $html HTML for the download button
     * @type string $js   JavaScript for download functionality
     * }
     */
    private static function generate_html_js_button()
    {
        $button_html = '<div style="text-align: right; margin-bottom: 5px;">';
        $button_html .= sprintf(
            '<button class="button button-small copy-debug" onclick="meprDownloadDebug(this)" data-target="mepr-drm-debug-content">%s</button>',
            esc_html__('Download', 'memberpress')
        );
        $button_html .= '</div>';

        $button_js = '
        <script>
        function meprDownloadDebug(button) {
            const debugId = button.getAttribute("data-target");
            const debugContent = document.getElementById(debugId);

            if (! debugContent) {
                console.error("Debug content not found");
                return;
            }

            try {
                // Clone the content so we can modify it without affecting the page.
                const contentClone = debugContent.cloneNode(true);

                // Remove all copy buttons.
                const copyButtons = contentClone.querySelectorAll(".copy-sql, .copy-debug");
                copyButtons.forEach(btn => btn.remove());

                // Sanitize content - remove any script tags.
                const scripts = contentClone.getElementsByTagName("script");
                while (scripts.length > 0) {
                    scripts[0].parentNode.removeChild(scripts[0]);
                }

                // Create the HTML document.
                const html = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="utf-8">
                        <meta name="robots" content="noindex, nofollow">
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                        <title>DRM Debug Information</title>
                        <style>
                            :root {
                                --border-color: #c3c4c7;
                                --background-light: #f6f7f7;
                                --text-primary: #1d2327;
                                --text-secondary: #50575e;
                                --accent-color: #2271b1;
                                --spacing-sm: 8px;
                                --spacing-md: 16px;
                                --spacing-lg: 24px;
                                --spacing-xl: 32px;
                            }

                            body {
                                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                                line-height: 1.5;
                                padding: var(--spacing-xl);
                                max-width: 1200px;
                                margin: 0 auto;
                                background: #f0f0f1;
                                color: var(--text-primary);
                            }
                            .mepr-drm-debug {
                                background: #fff;
                                border: 1px solid var(--border-color);
                                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                                padding: var(--spacing-xl);
                                border-radius: 4px;
                            }
                            table {
                                width: 100%;
                                border-collapse: separate;
                                border-spacing: 0;
                                margin: var(--spacing-lg) 0 var(--spacing-xl);
                                background: #fff;
                                border: 1px solid var(--border-color);
                                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                                border-radius: 4px;
                                overflow: hidden;
                            }
                            th, td {
                                padding: var(--spacing-md);
                                text-align: left;
                                border-bottom: 1px solid var(--border-color);
                                border-right: 1px solid var(--border-color);
                                vertical-align: top;
                            }
                            th:last-child, td:last-child { border-right: none; }
                            tr:last-child td, tr:last-child th { border-bottom: none; }
                            th {
                                background: var(--background-light);
                                font-weight: 600;
                                color: var(--text-primary);
                            }
                            table.two-col th { width: 30%; }
                            table.three-col th { width: auto; }
                            .striped tbody tr:nth-child(odd) { background-color: #fff; }
                            .striped tbody tr:nth-child(even) { background-color: var(--background-light); }
                            h1 {
                                color: var(--text-primary);
                                font-size: 24px;
                                margin: 0 0 var(--spacing-xl);
                                padding-bottom: var(--spacing-md);
                                border-bottom: 2px solid var(--accent-color);
                                font-weight: 600;
                            }
                            h2 {
                                color: var(--text-primary);
                                font-size: 20px;
                                margin: var(--spacing-xl) 0 var(--spacing-md);
                                padding-bottom: var(--spacing-sm);
                                border-bottom: 1px solid var(--border-color);
                                font-weight: 600;
                            }
                            h3 {
                                color: var(--text-primary);
                                font-size: 16px;
                                margin: var(--spacing-lg) 0 var(--spacing-md);
                                font-weight: 600;
                            }
                            .mepr-serialized-data {
                                margin: var(--spacing-sm) 0;
                                padding: var(--spacing-md);
                                background: var(--background-light);
                                border: 1px solid var(--border-color);
                                border-radius: 3px;
                            }
                            .mepr-data-row {
                                margin: var(--spacing-sm) 0;
                                line-height: 1.5;
                                display: flex;
                                align-items: flex-start;
                            }
                            .mepr-key {
                                font-weight: 600;
                                margin-right: var(--spacing-md);
                                color: var(--accent-color);
                                min-width: 120px;
                            }
                            .mepr-meta {
                                color: var(--text-secondary);
                                font-size: 12px;
                                margin-top: var(--spacing-xl);
                                padding-top: var(--spacing-md);
                                border-top: 1px solid var(--border-color);
                            }
                            @media print {
                                body {
                                    background: #fff;
                                    padding: var(--spacing-md);
                                }
                                .mepr-drm-debug {
                                    border: none;
                                    box-shadow: none;
                                    padding: 0;
                                }
                                table {
                                    box-shadow: none;
                                    border: 1px solid #000;
                                }
                                th, td {
                                    border-color: #000;
                                }
                                .mepr-serialized-data {
                                    border: 1px solid #000;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <h1>DRM Debug Information</h1>
                        ${contentClone.innerHTML}
                        <div class="mepr-meta">
                            Generated: ${new Date().toLocaleString()}
                            <br>
                            User ID: ' . (int) get_current_user_id() . '
                        </div>
                    </body>
                    </html>`;

                // Create blob and download with error handling
                try {
                    const blob = new Blob([html], { type: "text/html" });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement("a");
                    a.href = url;
                    a.download = "memberpress-drm-debug-" + new Date().toISOString().split("T")[0] + ".html";
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);

                    // Show feedback.
                    const originalText = button.textContent;
                    button.textContent = "' . esc_js(__('Downloaded!', 'memberpress')) . '";
                    setTimeout(function() {
                        button.textContent = originalText;
                    }, 2000);
                } catch (error) {
                    console.error("Download failed:", error);
                    button.textContent = "' . esc_js(__('Download Failed', 'memberpress')) . '";
                    setTimeout(function() {
                        button.textContent = originalText;
                    }, 2000);
                }
            } catch (error) {
                console.error("Content processing failed:", error);
            }
        }
        </script>';

        return [
            'html' => $button_html,
            'js' => $button_js,
        ];
    }

    /**
     * Generates the DRM status section of the debug report
     *
     * Shows current DRM status, license information, and active event details
     *
     * @return string HTML content for the DRM status section
     */
    private static function get_drm_status_section()
    {
        $drm_status = MeprDrmHelper::get_status();
        $license_key = MeprDrmHelper::get_key();

        if (empty($drm_status)) {
            $drm_status = '-';
        }

        if (empty($license_key)) {
            $license_key = '-';
        }

        $drm_event_data = self::get_current_drm_event_data();
        $content = '<h2>' . esc_html__('Current DRM Status', 'memberpress') . '</h2>';
        $content .= '<table id="drm-status-table" class="widefat striped">';
        $content .= sprintf(
            '<tr><th>%s</th><td>%s</td></tr>',
            esc_html__('DRM Event Type', 'memberpress'),
            esc_html($drm_event_data['type'] ?? '-')
        );
        $content .= sprintf(
            '<tr><th>%s</th><td>%s</td></tr>',
            esc_html__('DRM Days Elapsed', 'memberpress'),
            esc_html($drm_event_data['days_elapsed'] ?? '-')
        );
        $content .= sprintf(
            '<tr><th>%s</th><td>%s</td></tr>',
            esc_html__('DRM Status', 'memberpress'),
            esc_html($drm_status)
        );
        $content .= sprintf(
            '<tr><th>%s</th><td>%s</td></tr>',
            esc_html__('License Key', 'memberpress'),
            esc_html($license_key)
        );
        $content .= sprintf(
            '<tr><th>%s</th><td>%s</td></tr>',
            esc_html__('License Valid', 'memberpress'),
            MeprDrmHelper::is_valid() ? esc_html__('Yes', 'memberpress') : esc_html__('No', 'memberpress')
        );
        $content .= '</table>';

        if (!empty($drm_event_data['type']) && !empty($drm_event_data['event']) && $drm_event_data['event'] instanceof MeprEvent) {
            $content .= '<h3>' . esc_html__('Current DRM Event Information', 'memberpress') . '</h3>';
            $content .= '<table class="widefat striped">';
            $content .= sprintf(
                '<tr><th>%s</th><th>%s</th><th>%s</th></tr>',
                esc_html__('Event', 'memberpress'),
                esc_html__('Created At', 'memberpress'),
                esc_html__('Args', 'memberpress')
            );
            $content .= self::format_event_row($drm_event_data['event']);
            $content .= '</table>';
        }
        return $content;
    }

    /**
     * Gets the DRM options from the WordPress options table
     *
     * Displays all options with 'mepr_drm_' prefix
     *
     * @return string HTML content showing all DRM-related options
     */
    private static function get_drm_options_section()
    {
        global $wpdb;

        $content = sprintf('<h2>%s</h2>', esc_html__('DRM Options Log', 'memberpress'));
        $content .= '<table class="widefat striped">';
        $content .= sprintf(
            '<tr><th>%s</th><th>%s</th></tr>',
            esc_html__('Name', 'memberpress'),
            esc_html__('Value', 'memberpress')
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $drm_options = $wpdb->get_results(
            'SELECT option_name, option_value ' .
            "FROM {$wpdb->options} " .
            'WHERE option_name LIKE "mepr_drm_%" ' .
            'ORDER BY option_id ASC'
        );

        foreach ($drm_options as $option) {
            $value = self::format_option_value($option->option_name, $option->option_value);
            $content .= sprintf(
                '<tr><th>%s</th><td>%s</td></tr>',
                esc_html($option->option_name),
                wp_kses_post($value)
            );
        }
        $content .= '</table>';

        return $content;
    }

    /**
     * Gets the chronological history of DRM events
     *
     * @return string HTML content showing all DRM events in chronological order
     */
    private static function get_drm_events_section()
    {
        $content = sprintf('<h2>%s</h2>', esc_html__('DRM Events Chronological', 'memberpress'));
        $content .= '<table class="widefat striped">';
        $content .= sprintf(
            '<tr><th>%s</th><th>%s</th><th>%s</th></tr>',
            esc_html__('Event', 'memberpress'),
            esc_html__('Created At', 'memberpress'),
            esc_html__('Args', 'memberpress')
        );

        $drm_events = MeprEvent::get_all_by_evt_id_type(MeprEvent::$drm_str, 'id ASC');

        foreach ($drm_events as $event) {
            if ($event) {
                $content .= self::format_event_row($event);
            }
        }
        $content .= '</table>';

        return $content;
    }

    /**
     * Generates the application fee section of the debug report
     *
     * Shows fee settings, subscription counts, and fee history
     *
     * @return string HTML content for the application fee section
     */
    private static function get_app_fee_section()
    {
        $content = '<h2>' . esc_html__('Application Fee Information', 'memberpress') . '</h2>';

        // Basic information table.
        $content .= self::get_app_fee_basic_info();

        // Fee history table.
        $content .= self::get_app_fee_history();

        return $content;
    }

    /**
     * Generates the basic app fee information table
     *
     * Shows current settings and subscription statistics
     *
     * @return string HTML content for the basic fee information table
     */
    private static function get_app_fee_basic_info()
    {
        global $wpdb;

        $content = '<table class="widefat striped">';

        // Add basic settings.
        $content .= self::get_app_fee_settings();

        // Check for fee history before showing subscription stats.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $has_fee_history = $wpdb->get_var(
            "SELECT EXISTS (
                SELECT 1
                FROM {$wpdb->options}
                WHERE option_name LIKE 'mepr_drm_app_fee_%_data_%'
                LIMIT 1
            )"
        );

        // Only show subscription stats if fee history exists.
        if ($has_fee_history) {
            $content .= self::get_app_fee_subscription_stats();
        }

        $content .= '</table>';

        return $content;
    }

    /**
     * Gets the current application fee settings
     *
     * @return string HTML content showing current fee settings
     */
    private static function get_app_fee_settings()
    {
        $content = '';
        $content .= sprintf(
            '<tr><th>%s</th><td>%s</td></tr>',
            esc_html__('App Fee Enabled', 'memberpress'),
            MeprDrmHelper::is_app_fee_enabled() ? esc_html__('Yes', 'memberpress') : esc_html__('No', 'memberpress')
        );
        $content .= sprintf(
            '<tr><th>%s</th><td>%s%%</td></tr>',
            esc_html__('Current Fee Percentage', 'memberpress'),
            esc_html(MeprDrmHelper::get_application_fee_percentage())
        );
        $content .= sprintf(
            '<tr><th>%s</th><td>%s</td></tr>',
            esc_html__('Fee API Version', 'memberpress'),
            esc_html(MeprDrmHelper::get_drm_app_fee_version())
        );

        return $content;
    }

    /**
     * Gets the subscription statistics for application fees
     *
     * Includes counts of active subscriptions with fees applied/revoked and historical totals
     *
     * @return string HTML content showing subscription statistics with SQL copy buttons
     */
    private static function get_app_fee_subscription_stats()
    {
        $counts = self::get_app_fee_subscription_counts();
        $content = '';

        $content .= sprintf(
            '<tr><td colspan="2"><h4>%s</h4></td></tr>',
            esc_html__('Active Subscriptions', 'memberpress')
        );

        $content .= sprintf(
            '<tr><th>%s</th><td>%s <button class="button button-small copy-sql" data-sql="%s" onclick="meprcopySql(this)">%s</button></td></tr>',
            esc_html__('With Fee Applied', 'memberpress'),
            esc_html($counts['applied']['count']),
            esc_attr($counts['applied']['copy_sql']),
            esc_html__('Copy SQL', 'memberpress')
        );

        $content .= sprintf(
            '<tr><th>%s</th><td>%s <button class="button button-small copy-sql" data-sql="%s" onclick="meprcopySql(this)">%s</button></td></tr>',
            esc_html__('With Fee Revoked', 'memberpress'),
            esc_html($counts['revoked']['count']),
            esc_attr($counts['revoked']['copy_sql']),
            esc_html__('Copy SQL', 'memberpress')
        );

        $content .= sprintf(
            '<tr><td colspan="2"><h4>%s</h4></td></tr>',
            esc_html__('Historical Totals', 'memberpress')
        );

        $content .= sprintf(
            '<tr><th>%s</th><td>%s</td></tr>',
            esc_html__('Total Ever Fee Applied', 'memberpress'),
            esc_html($counts['all_applied']['count'])
        );

        $content .= sprintf(
            '<tr><th>%s</th><td>%s</td></tr>',
            esc_html__('Total Ever Fee Revoked', 'memberpress'),
            esc_html($counts['all_revoked']['count'])
        );

        $content .= '
        <script>
        function meprcopySql(button) {
            const sql = button.getAttribute("data-sql");
            navigator.clipboard.writeText(sql).then(function() {
                const originalText = button.textContent;
                button.textContent = "' . esc_js(__('Copied!', 'memberpress')) . '";
                setTimeout(function() {
                    button.textContent = originalText;
                }, 2000);
            });
        }
        </script>
        <style>
        .copy-sql {
            margin-left: 10px !important;
        }
        </style>';

        return $content;
    }

    /**
     * Gets the history of application fee enable/disable events
     *
     * @return string HTML content showing the fee enable/disable history
     */
    private static function get_app_fee_history()
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $fee_history_data = $wpdb->get_results(
            'SELECT option_name, option_value ' .
            "FROM {$wpdb->options} " .
            'WHERE option_name LIKE "mepr_drm_app_fee_%_data_%" ' .
            'ORDER BY option_id ASC'
        );

        if (empty($fee_history_data)) {
            return '';
        }

        $content = sprintf('<h3>%s</h3>', esc_html__('Fee Enable/Disable History', 'memberpress'));
        $content .= '<table class="widefat striped">';
        $content .= sprintf(
            '<tr><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr>',
            esc_html__('Action', 'memberpress'),
            esc_html__('Date', 'memberpress'),
            esc_html__('User', 'memberpress'),
            esc_html__('Domain', 'memberpress')
        );

        foreach ($fee_history_data as $data) {
            $content .= self::format_fee_history_row($data);
        }

        $content .= '</table>';

        return $content;
    }

    /**
     * Gets counts of subscriptions with application fee applied and revoked
     *
     * @return array {
     * Array containing subscription counts and their SQL queries
     *
     * @type array $applied {
     * Active subscriptions with fee applied
     * @type string $count     Number of subscriptions with fee applied
     * @type string $copy_sql  SQL query for copying subscription IDs
     * }
     * @type array $revoked {
     * Active subscriptions with fee revoked
     * @type string $count     Number of subscriptions with fee revoked
     * @type string $copy_sql  SQL query for copying subscription IDs
     * }
     * @type array $all_applied {
     * All subscriptions that ever had fee applied
     * @type string $count     Total number of subscriptions
     * @type string $copy_sql  Empty string, no SQL available
     * }
     * @type array $all_revoked {
     * All subscriptions that ever had fee revoked
     * @type string $count     Total number of subscriptions
     * @type string $copy_sql  Empty string, no SQL available
     * }
     * }
     */
    private static function get_app_fee_subscription_counts()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $all_applied_sql =
            'SELECT COUNT(DISTINCT s.id) ' .
            "FROM {$mepr_db->subscriptions} s " .
            "JOIN {$mepr_db->subscription_meta} sm ON s.id = sm.subscription_id " .
            'WHERE sm.meta_key = \'application_fee_percent\' ' .
            'AND sm.meta_value > 0';

        $all_revoked_sql =
            'SELECT COUNT(DISTINCT s.id) ' .
            "FROM {$mepr_db->subscriptions} s " .
            "JOIN {$mepr_db->subscription_meta} sm ON s.id = sm.subscription_id " .
            'WHERE sm.meta_key = \'application_fee_revoked\'';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $active_applied_sql = $wpdb->prepare(
            'SELECT COUNT(DISTINCT s.id) as count ' .
            "FROM {$mepr_db->subscriptions} s " .
            "JOIN {$mepr_db->transactions} t ON s.id = t.subscription_id " .
            "JOIN {$mepr_db->subscription_meta} sm ON s.id = sm.subscription_id " .
            'WHERE t.status IN (%s, %s) ' .
            'AND s.status = %s ' .
            'AND t.expires_at > %s ' .
            'AND t.expires_at != \'0000-00-00 00:00:00\' ' .
            'AND sm.meta_key = \'application_fee_percent\' ' .
            'AND sm.meta_value > 0',
            MeprTransaction::$complete_str,
            MeprTransaction::$confirmed_str,
            MeprSubscription::$active_str,
            MeprUtils::db_now()
        );

        $active_revoked_sql = $wpdb->prepare(
            'SELECT COUNT(DISTINCT s.id) as count ' .
            "FROM {$mepr_db->subscriptions} s " .
            "JOIN {$mepr_db->transactions} t ON s.id = t.subscription_id " .
            "JOIN {$mepr_db->subscription_meta} sm ON s.id = sm.subscription_id " .
            'WHERE t.status IN (%s, %s) ' .
            'AND s.status = %s ' .
            'AND t.expires_at > %s ' .
            'AND t.expires_at != \'0000-00-00 00:00:00\' ' .
            'AND sm.meta_key = \'application_fee_revoked\'',
            MeprTransaction::$complete_str,
            MeprTransaction::$confirmed_str,
            MeprSubscription::$active_str,
            MeprUtils::db_now()
        ); // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $copy_applied_sql = str_replace('COUNT(DISTINCT s.id) as count', 'DISTINCT s.id', $active_applied_sql);
        $copy_revoked_sql = str_replace('COUNT(DISTINCT s.id) as count', 'DISTINCT s.id', $active_revoked_sql);

        return [
            'applied' => [
                'count' => number_format((int) $wpdb->get_var($active_applied_sql)), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
                'copy_sql' => $copy_applied_sql,
            ],
            'revoked' => [
                'count' => number_format((int) $wpdb->get_var($active_revoked_sql)), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
                'copy_sql' => $copy_revoked_sql,
            ],
            'all_applied' => [
                'count' => number_format((int) $wpdb->get_var($all_applied_sql)), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
                'copy_sql' => '',
            ],
            'all_revoked' => [
                'count' => number_format((int) $wpdb->get_var($all_revoked_sql)), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
                'copy_sql' => '',
            ],
        ];
    }

    /**
     * Formats a single row of fee history data for display
     *
     * @param object $data The history data object containing option name and value.
     *
     * @return string HTML for a single history row
     */
    private static function format_fee_history_row($data)
    {
        $value = maybe_unserialize($data->option_value);

        if (!is_array($value) || !isset($value['time']) || !isset($value['user_id']) || !isset($value['domain'])) {
            return '';
        }

        $action = (strpos($data->option_name, 'enabled_data_') !== false) ? esc_html__('Enabled', 'memberpress') : esc_html__('Disabled', 'memberpress');

        $user = null;
        if ($value['user_id']) {
            $user = get_user_by('id', (int) $value['user_id']);
        }

        $user_info = esc_html__('Unknown User', 'memberpress');
        if ($user) {
            $user_info = sprintf(
                '<a href="%s" target="_blank">%s</a><br/>(ID: %d, Username: %s)',
                esc_url(admin_url('user-edit.php?user_id=' . $user->ID)),
                esc_html($user->display_name),
                $user->ID,
                esc_html($user->user_login)
            );
        }

        return sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            esc_html($action),
            esc_html($value['time']),
            wp_kses_post($user_info),
            esc_html($value['domain'])
        );
    }

    /**
     * Formats an option value for display based on its type and name
     *
     * @param string $option_name  The name of the option.
     * @param string $option_value The raw value of the option.
     *
     * @return string Formatted HTML for displaying the option value
     */
    private static function format_option_value($option_name, $option_value)
    {
        if (is_serialized($option_value)) {
            $unserialized = maybe_unserialize($option_value);
            if (is_array($unserialized)) {
                $formatted = '<div class="mepr-serialized-data">';
                foreach ($unserialized as $key => $value) {
                    if (is_numeric($value) && strlen($value) === 10) {
                        $value = sprintf(
                            '%s (%s)',
                            $value,
                            gmdate('Y-m-d H:i:s', (int) $value)
                        );
                    }
                    $formatted .= sprintf(
                        '<div class="mepr-data-row"><span class="mepr-key">%s:</span> <span class="mepr-value">%s</span></div>',
                        esc_html($key),
                        esc_html($value)
                    );
                }
                $formatted .= '</div>';

                return $formatted;
            }
        }

        if (is_numeric($option_value) && strlen($option_value) === 10) {
            return sprintf(
                '%s (%s)',
                $option_value,
                gmdate('Y-m-d H:i:s', (int) $option_value)
            );
        }

        return $option_value;
    }

    /**
     * Formats a DRM event row for display in tables
     *
     * @param object $event The event object to format.
     *
     * @return string HTML for the event table row
     */
    private static function format_event_row($event)
    {
        $args_data = json_decode($event->args, true);
        $formatted_args = '';

        if (is_array($args_data)) {
            foreach ($args_data as $key => $value) {
                if (is_numeric($value) && strlen($value) === 10) {
                    $value = sprintf(
                        '%s (%s)',
                        $value,
                        gmdate('Y-m-d H:i:s', (int) $value)
                    );
                }
                $formatted_args .= sprintf(
                    '%s: %s<br>',
                    esc_html($key),
                    esc_html($value)
                );
            }
        }

        return sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
            esc_html($event->event),
            esc_html($event->created_at),
            $formatted_args
        );
    }

    /**
     * Gets the CSS styles for the debug display
     *
     * @return string CSS styles for formatting the debug information
     */
    private static function get_debug_styles()
    {
        return '<style>
            .mepr-drm-debug table { margin-bottom: 20px; }
            .mepr-drm-debug h3 { margin: 20px 0 10px; }
            .mepr-drm-debug th { width: 250px; }
        </style>';
    }

    /**
     * Gets the current DRM event data including type, event object, and days elapsed
     *
     * @return array {
     * Array containing current DRM event information
     *
     * @type string      $type         The type of DRM event
     * @type MeprEvent   $event        The event object
     * @type int         $days_elapsed Number of days since the event
     * }
     */
    private static function get_current_drm_event_data()
    {
        // Check DRM license status flags.
        $drm_no_license = get_option('mepr_drm_no_license', false);
        $drm_invalid_license = get_option('mepr_drm_invalid_license', false);

        $drm_event_type = '';
        if ($drm_no_license) {
            $drm_event_type = MeprDrmHelper::NO_LICENSE_EVENT;
        } elseif ($drm_invalid_license) {
            $drm_event_type = MeprDrmHelper::INVALID_LICENSE_EVENT;
        }

        $data = [];
        if (!empty($drm_event_type)) {
            $event = MeprEvent::latest($drm_event_type);
            if ($event) {
                $data['type'] = $drm_event_type;
                $data['event'] = $event;
                $data['days_elapsed'] = MeprDrmHelper::days_elapsed($event->created_at);
            }
        }

        return $data;
    }
}
