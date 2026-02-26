<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProactiveSupportAdminCtrl extends MeprBaseCtrl
{
    /**
     * Whether access was granted during the current request.
     *
     * @var boolean
     */
    private bool $access_granted = false;

    /**
     * Register admin hooks.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('mepr_menu', [$this, 'register_menu']);
        add_action('admin_post_mepr_proactive_opt_out', [$this, 'handle_opt_out_request']);
        add_action('admin_post_nopriv_mepr_proactive_opt_out', [$this, 'handle_opt_out_request']);
        add_action('admin_post_mepr_proactive_review', [$this, 'handle_review_action']);
        add_action('admin_post_mepr_proactive_export', [$this, 'handle_export']);
    }

    /**
     * Add proactive support log page.
     *
     * @return void
     */
    public function register_menu()
    {
        $this->maybe_set_access_cookie();

        if (!$this->has_access_cookie()) {
            return;
        }

        add_submenu_page(
            'memberpress',
            esc_html__('Proactive Support', 'memberpress'),
            esc_html__('Proactive Support', 'memberpress'),
            'manage_options',
            'memberpress-proactive-support',
            [$this, 'render_log']
        );
    }

    /**
     * Render log page.
     *
     * @return void
     */
    public function render_log()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'memberpress'));
        }

        $this->maybe_set_access_cookie();

        $this->maybe_handle_settings_form();

        $search_value = isset($_GET['mepr_search']) ? sanitize_text_field(wp_unslash($_GET['mepr_search'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $filters      = [
            'trigger' => isset($_GET['mepr_trigger']) ? sanitize_key(wp_unslash($_GET['mepr_trigger'])) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'status'  => isset($_GET['mepr_status']) ? sanitize_key(wp_unslash($_GET['mepr_status'])) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'search'  => strtolower($search_value),
        ];

        $records          = MeprProactiveSupportRepository::search([
            'trigger'    => $filters['trigger'],
            'status'     => $filters['status'],
            'email_like' => $filters['search'],
            'limit'      => 100,
        ]);
        $queue            = MeprProactiveSupportRepository::queue();
        $logs             = MeprProactiveSupportLogger::get_logs();
        $analytics        = MeprProactiveSupportAnalyticsHelper::get_dashboard_data();
        $global_opt_out   = MeprProactiveSupportHelper::is_globally_opted_out();
        $opted_out_emails = MeprProactiveSupportHelper::get_admin_opt_out_emails();
        $statuses         = ['pending', 'sent', 'suppressed', 'cancelled', 'resolved'];

        $current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'analytics';
        $valid_tabs  = ['analytics', 'review', 'emails', 'events', 'preferences'];
        if (!in_array($current_tab, $valid_tabs, true)) {
            $current_tab = 'analytics';
        }

        $view = MeprView::get_string(
            '/admin/proactive-support/log-list',
            compact('records', 'logs', 'global_opt_out', 'queue', 'filters', 'statuses', 'analytics', 'current_tab', 'opted_out_emails')
        );
        echo $view; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Determine if the access cookie has been set.
     *
     * @return boolean
     */
    private function has_access_cookie()
    {
        return $this->access_granted || filter_input(INPUT_COOKIE, 'mepr_proactive_support_access') === '1';
    }

    /**
     * Check for query flag and set cookie for 24 hours.
     *
     * @return void
     */
    private function maybe_set_access_cookie()
    {
        $has_flag = filter_input(INPUT_GET, 'cspf-proactive-support', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($has_flag)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $this->set_access_cookie();
        $this->access_granted = true;
    }

    /**
     * Handle manual review queue actions.
     *
     * @return void
     */
    public function handle_review_action()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'memberpress'));
        }

        check_admin_referer('mepr_proactive_review_action');

        $record_id     = isset($_POST['record_id']) ? absint($_POST['record_id']) : 0;
        $review_action = isset($_POST['review_action']) ? sanitize_key($_POST['review_action']) : '';
        $record        = MeprProactiveSupportRepository::find($record_id);
        $redirect      = add_query_arg('tab', 'review', admin_url('admin.php?page=memberpress-proactive-support'));

        if (!$record) {
            wp_safe_redirect(add_query_arg('mepr_proactive_review', 'not_found', $redirect));
            exit;
        }

        $user_id = get_current_user_id();

        switch ($review_action) {
            case 'resolve':
                MeprProactiveSupportRepository::update($record_id, [
                    'status'      => 'resolved',
                    'resolved_by' => $user_id,
                    'resolved_at' => current_time('mysql', true),
                ]);
                MeprProactiveSupportLogger::log('resolved', $record->trigger_type, $record->admin_email, 'Manually resolved');
                $message = 'resolved';
                break;
            case 'cancel':
                MeprProactiveSupportRepository::update($record_id, [
                    'status' => 'cancelled',
                ]);
                MeprProactiveSupportLogger::log('cancelled', $record->trigger_type, $record->admin_email, 'Cancelled via review queue');
                $message = 'cancelled';
                break;
            case 'resend':
                MeprProactiveSupportRepository::update($record_id, [
                    'status'        => 'pending',
                    'email_sent_at' => null,
                ]);
                MeprProactiveSupportLogger::log('resend', $record->trigger_type, $record->admin_email, 'Queued for resend');
                $message = 'resent';
                break;
            default:
                $message = 'invalid';
                break;
        }

        wp_safe_redirect(add_query_arg('mepr_proactive_review', $message, $redirect));
        exit;
    }

    /**
     * Handle opt-out links from email footer.
     *
     * @return void
     */
    public function handle_opt_out_request()
    {
        $email = isset($_REQUEST['email']) ? sanitize_email(wp_unslash($_REQUEST['email'])) : '';
        $token = isset($_REQUEST['token']) ? sanitize_text_field(wp_unslash($_REQUEST['token'])) : '';
        if (empty($email)) {
            wp_die(esc_html__('No email address provided.', 'memberpress'));
        }

        $expected = MeprProactiveSupportHelper::get_opt_out_token($email);
        if (empty($token) || empty($expected) || !hash_equals($expected, $token)) {
            wp_die(esc_html__('Invalid opt-out request.', 'memberpress'));
        }

        $emails   = MeprProactiveSupportHelper::get_admin_opt_out_emails();
        $emails[] = $email;
        MeprProactiveSupportHelper::set_admin_opt_out_emails($emails);

        wp_die(
            sprintf(
                // Translators: %s: admin email.
                esc_html__('The email %s has been opted out of proactive support.', 'memberpress'),
                esc_html($email)
            ),
            esc_html__('Opted Out', 'memberpress'),
            ['response' => 200]
        );
    }

    /**
     * Process settings form submissions.
     *
     * @return void
     */
    private function maybe_handle_settings_form()
    {
        if (filter_input(INPUT_SERVER, 'REQUEST_METHOD') !== 'POST') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        check_admin_referer('mepr_proactive_settings');

        $action = isset($_POST['mepr_proactive_action']) ? sanitize_key(wp_unslash($_POST['mepr_proactive_action'])) : '';
        if ($action !== 'save_proactive_settings') {
            return;
        }

        $emails_field = isset($_POST['mepr_proactive_opt_out_emails']) ? (string) wp_unslash($_POST['mepr_proactive_opt_out_emails']) : '';
        $emails_lines = preg_split("/\r\n|\r|\n/", $emails_field);
        MeprProactiveSupportHelper::set_admin_opt_out_emails($emails_lines ?: []);

        add_settings_error(
            'mepr_proactive_support',
            'settings_saved',
            esc_html__('Proactive support preferences updated.', 'memberpress'),
            'updated'
        );

        // Clear transient notice query arg if present.
        if (isset($_GET['mepr_proactive_notice'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            unset($_GET['mepr_proactive_notice']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
    }

    /**
     * Export filtered records as CSV.
     *
     * @return string|void
     */
    public function handle_export()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export these logs.', 'memberpress'));
        }

        check_admin_referer('mepr_proactive_export');

        $filters = [
            'trigger' => isset($_GET['mepr_trigger']) ? sanitize_key(wp_unslash($_GET['mepr_trigger'])) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'status'  => isset($_GET['mepr_status']) ? sanitize_key(wp_unslash($_GET['mepr_status'])) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'search'  => isset($_GET['mepr_search']) ? sanitize_text_field(wp_unslash($_GET['mepr_search'])) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ];

        $records = MeprProactiveSupportRepository::search([
            'trigger'    => $filters['trigger'],
            'status'     => $filters['status'],
            'email_like' => $filters['search'],
            'limit'      => 1000,
        ]);

        if (apply_filters('mepr_proactive_support_export_headers', true)) {
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="mepr-proactive-support.csv"');
        }

        $capture = apply_filters('mepr_proactive_support_export_capture', false);
        $output  = fopen($capture ? 'php://temp' : 'php://output', 'w');
        fputcsv($output, ['ID', 'Admin', 'Trigger', 'Status', 'Email Sent', 'Created'], ',', '"', '\\');

        foreach ($records as $record) {
            $admin   = $this->escape_csv_value($record->admin_email ?: __('Unknown', 'memberpress'));
            $trigger = $this->escape_csv_value(MeprProactiveSupportHelper::trigger_label($record->trigger_type));
            $email   = empty($record->email_sent_at)
                ? ''
                : $this->escape_csv_value(get_date_from_gmt($record->email_sent_at, get_option('date_format') . ' ' . get_option('time_format')));
            $created = $this->escape_csv_value(get_date_from_gmt($record->created_at, get_option('date_format') . ' ' . get_option('time_format')));
            $status  = $this->escape_csv_value(ucfirst($record->status));

            fputcsv($output, [$record->id, $admin, $trigger, $status, $email, $created], ',', '"', '\\');
        }

        if ($capture) {
            rewind($output);
            $csv = (string) stream_get_contents($output);
            fclose($output);
            return $csv;
        }

        fclose($output);
        if (apply_filters('mepr_proactive_support_export_exit', true)) {
            exit;
        }
    }

    /**
     * Set the access cookie with hardened settings.
     *
     * @return void
     */
    private function set_access_cookie()
    {
        $options = [
            'expires'  => time() + DAY_IN_SECONDS,
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        if (PHP_VERSION_ID >= 70300) {
            setcookie('mepr_proactive_support_access', '1', $options);
            return;
        }

        setcookie(
            'mepr_proactive_support_access',
            '1',
            $options['expires'],
            $options['path'],
            $options['domain'],
            $options['secure'],
            $options['httponly']
        );
    }

    /**
     * Prevent CSV formula injection in exported fields.
     *
     * @param string $value Raw field value.
     *
     * @return string
     */
    private function escape_csv_value($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return $value;
        }

        if (preg_match('/^[=+\\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
