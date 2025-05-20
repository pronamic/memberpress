<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprMembersCtrl extends MeprBaseCtrl
{
    /**
     * Loads hooks for various actions and filters related to members.
     *
     * @return void
     */
    public function load_hooks()
    {
        // Screen Options.
        $hook = 'memberpress_page_memberpress-members';
        add_action("load-{$hook}", [$this,'add_screen_options']);
        add_filter('set_screen_option_mp_members_perpage', [$this,'setup_screen_options'], 10, 3);
        add_filter("manage_{$hook}_columns", [$this, 'get_columns'], 0);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Update listing meta.
        add_action('mepr_txn_store', [$this, 'update_txn_meta']);
        add_action('mepr_txn_destroy', [$this, 'update_txn_meta']);
        add_action('mepr_event_store', [$this, 'update_event_meta']);
        add_action('mepr_event_destroy', [$this, 'update_event_meta']);
        add_action('user_register', [$this, 'update_member_meta']);
        add_action('profile_update', [$this, 'update_member_meta']);
        add_action('delete_user', [$this, 'delete_member_meta']);
        add_action('mepr_table_controls_search', [$this, 'table_search_box']);
        add_action('mepr_subscription_deleted', [$this, 'update_member_data_from_subscription']);
        add_action('mepr_subscription_status_cancelled', [$this, 'update_member_data_from_subscription']);
        add_action('mepr_subscription_status_suspended', [$this, 'update_member_data_from_subscription']);
        add_action('mepr_subscription_status_pending', [$this, 'update_member_data_from_subscription']);
        add_action('mepr_subscription_status_active', [$this, 'update_member_data_from_subscription']);
        add_action('mepr-transaction-expired', [$this, 'update_txn_meta'], 11, 2);

        if (is_multisite()) {
            add_action('add_user_to_blog', [$this, 'update_member_meta']);
            add_action('remove_user_from_blog', [$this, 'delete_member_meta']);
        }

        // Export members.
        add_action('wp_ajax_mepr_members', [$this, 'csv']);
        add_action('mepr_control_table_footer', [$this, 'export_footer_link'], 10, 3);

        // Keeping members up to date.
        add_filter('cron_schedules', [$this,'intervals']);
        add_action('mepr_member_data_updater_worker', [$this,'updater']);

        $member_data_timestamp = wp_next_scheduled('mepr_member_data_updater_worker');
        if (!$member_data_timestamp) {
            wp_schedule_event(time() + MeprUtils::hours(6), 'mepr_member_data_updater_interval', 'mepr_member_data_updater_worker');
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
        $schedules['mepr_member_data_updater_interval'] = [
            'interval' => MeprUtils::hours(6), // Run four times a day.
            'display'  => __('MemberPress Member Data Update Interval', 'memberpress'),
        ];

        return $schedules;
    }

    /**
     * Updates member data in the background.
     *
     * @return void
     */
    public function updater()
    {
        MeprUtils::debug_log('Start Updating Missing Members');
        MeprUser::update_all_member_data(true, 100);
        MeprUtils::debug_log('End Updating Missing Members');
    }

    /**
     * Enqueues scripts and styles for the members page.
     *
     * @param string $hook The current admin page hook.
     *
     * @return void
     */
    public function enqueue_scripts($hook)
    {
        if ($hook == 'memberpress_page_memberpress-members' || $hook == 'memberpress_page_memberpress-new-member') {
            wp_register_script('mepr-table-controls-js', MEPR_JS_URL . '/table_controls.js', ['jquery'], MEPR_VERSION);
            wp_register_script('mepr-timepicker-js', MEPR_JS_URL . '/vendor/jquery-ui-timepicker-addon.js', ['jquery-ui-datepicker']);
            wp_register_script('mepr-date-picker-js', MEPR_JS_URL . '/date_picker.js', ['mepr-timepicker-js'], MEPR_VERSION);
            wp_register_script('mphelpers', MEPR_JS_URL . '/mphelpers.js', ['suggest'], MEPR_VERSION);
            wp_enqueue_script(
                'mepr-members-js',
                MEPR_JS_URL . '/admin_members.js',
                ['mepr-table-controls-js','jquery','mphelpers','mepr-date-picker-js','mepr-settings-table-js'],
                MEPR_VERSION
            );

            wp_register_style('mepr-jquery-ui-smoothness', MEPR_CSS_URL . '/vendor/jquery-ui/smoothness.min.css', [], '1.13.3');
            wp_register_style('jquery-ui-timepicker-addon', MEPR_CSS_URL . '/vendor/jquery-ui-timepicker-addon.css', ['mepr-jquery-ui-smoothness'], MEPR_VERSION);
            wp_enqueue_style('mepr-members-css', MEPR_CSS_URL . '/admin-members.css', ['mepr-settings-table-css','jquery-ui-timepicker-addon'], MEPR_VERSION);
        }
    }

    /**
     * Handles the listing of members, including creating new members.
     *
     * @return void
     */
    public function listing()
    {
        $action = (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) ? $_REQUEST['action'] : false;
        if ($action == 'new') {
            $this->new_member();
        } elseif (MeprUtils::is_post_request() && $action == 'create') {
            $this->create_member();
        } else {
            $this->display_list();
        }
    }

    /**
     * Retrieves the columns for the members list table.
     *
     * @return array The columns for the members list table.
     */
    public function get_columns()
    {
        $cols = [
            'col_id'                   => __('Id', 'memberpress'),
            // 'col_photo' => __('Photo'),
            'col_username'             => __('Username', 'memberpress'),
            'col_email'                => __('Email', 'memberpress'),
            'col_status'               => __('Status', 'memberpress'),
            'col_name'                 => __('Name', 'memberpress'),
            'col_sub_info'             => __('Subscriptions', 'memberpress'),
            'col_txn_info'             => __('Transactions', 'memberpress'),
            // 'col_info' => __('Info', 'memberpress'),
            // 'col_txn_count' => __('Transactions', 'memberpress'),
            // 'col_expired_txn_count' => __('Expired Transactions'),
            // 'col_active_txn_count' => __('Active Transactions'),
            // 'col_sub_count' => __('Subscriptions', 'memberpress'),
            // 'col_pending_sub_count' => __('Pending Subscriptions'),
            // 'col_active_sub_count' => __('Enabled Subscriptions'),
            // 'col_suspended_sub_count' => __('Paused Subscriptions'),
            // 'col_cancelled_sub_count' => __('Stopped Subscriptions'),
            'col_memberships'          => __('Memberships', 'memberpress'),
            'col_inactive_memberships' => __('Inactive Memberships', 'memberpress'),
            'col_last_login_date'      => __('Last Login', 'memberpress'),
            'col_login_count'          => __('Logins', 'memberpress'),
            'col_total_spent'          => __('Value', 'memberpress'),
            'col_registered'           => __('Registered', 'memberpress'),
        ];

        return MeprHooks::apply_filters('mepr-admin-members-cols', $cols);
    }

    /**
     * Displays the list of members.
     *
     * @param string $message Optional message to display.
     * @param array  $errors  Optional array of errors to display.
     *
     * @return void
     */
    public function display_list($message = '', $errors = [])
    {
        $screen = get_current_screen();

        $list_table = new MeprMembersTable($screen, $this->get_columns());
        $list_table->prepare_items();

        MeprView::render('/admin/members/list', compact('message', 'list_table'));
    }

    /**
     * Displays the form for creating a new member.
     *
     * @param MeprUser        $member      Optional member object.
     * @param MeprTransaction $transaction Optional transaction object.
     * @param string          $errors      Optional errors to display.
     * @param string          $message     Optional message to display.
     *
     * @return void
     */
    public function new_member($member = null, $transaction = null, $errors = '', $message = '')
    {
        $mepr_options = MeprOptions::fetch();

        if (empty($member)) {
            $member                    = new MeprUser();
            $member->send_notification = true;
            $member->password          = wp_generate_password(24);
        }

        if (empty($transaction)) {
            $transaction               = new MeprTransaction();
            $transaction->status       = MeprTransaction::$complete_str; // Default this to complete in this case.
            $transaction->send_welcome = true;
        }

        MeprView::render('/admin/members/new_member', compact('mepr_options', 'member', 'transaction', 'errors', 'message'));
    }

    /**
     * Creates a new member based on form input.
     *
     * @return mixed
     */
    public function create_member()
    {
        check_admin_referer('mepr_create_member', 'mepr_members_nonce');

        $mepr_options = MeprOptions::fetch();
        $errors       = $this->validate_new_member();
        $message      = '';

        $member = new MeprUser();
        $member->load_from_array($_POST['member']);
        $member->send_notification = isset($_POST['member']['send_notification']);

        // Just here in case things fail so we can show the same password when the new_member page is re-displayed.
        $member->password   = $_POST['member']['user_pass'];
        $member->user_email = sanitize_email($_POST['member']['user_email']);

        $transaction = new MeprTransaction();
        $transaction->load_from_array($_POST['transaction']);
        $transaction->send_welcome      = isset($_POST['transaction']['send_welcome']);
        $_POST['transaction']['amount'] = MeprUtils::format_currency_us_float($_POST['transaction']['amount']); // Don't forget this, or the members page and emails will have $0.00 for amounts.
        if ($transaction->total <= 0) {
            $transaction->total = $_POST['transaction']['amount']; // Don't forget this, or the members page and emails will have $0.00 for amounts.
        }

        if (count($errors) <= 0) {
            try {
                $member->set_password($_POST['member']['user_pass']);
                $member->store();

                // Needed for autoresponders - call before storing txn.
                MeprHooks::do_action('mepr-signup-user-loaded', $member);

                if ($member->send_notification) {
                      $member->send_password_notification('new');
                }

                $transaction->user_id = $member->ID;
                $transaction->store();

                // Trigger the right events here yo.
                MeprEvent::record('transaction-completed', $transaction);
                MeprEvent::record('non-recurring-transaction-completed', $transaction);

                // Run the signup hooks.
                MeprHooks::do_action('mepr-non-recurring-signup', $transaction);
                MeprHooks::do_action('mepr-signup', $transaction);

                if ($transaction->send_welcome) {
                    MeprUtils::send_signup_notices($transaction);
                } else { // Trigger the event for this yo, as it's normally triggered in send_signup_notices.
                    MeprEvent::record('member-signup-completed', $member, (object)$transaction->rec); // Have to use ->rec here for some reason.
                }

                $message = __('Your new member was created successfully.', 'memberpress');

                return $this->display_list($message);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $this->new_member($member, $transaction, $errors, $message);
    }

    /**
     * Adds screen options for the members page.
     *
     * @return void
     */
    public function add_screen_options()
    {
        add_screen_option('layout_columns');

        $option = 'per_page';

        $args = [
            'label'   => __('Members', 'memberpress'),
            'default' => 10,
            'option'  => 'mp_members_perpage',
        ];

        add_screen_option($option, $args);
    }

    /**
     * Sets up screen options for the members page.
     *
     * @param mixed  $status The current status.
     * @param string $option The option name.
     * @param mixed  $value  The option value.
     *
     * @return mixed The modified status or value.
     */
    public function setup_screen_options($status, $option, $value)
    {
        if ('mp_members_perpage' === $option) {
            return $value;
        }

        return $status;
    }

    /**
     * Updates transaction metadata.
     * This is purely for performance ... we don't want to do these queries during a listing
     *
     * @param MeprTransaction $txn        The transaction object.
     * @param boolean         $sub_status Optional subscription status.
     *
     * @return void
     */
    public function update_txn_meta($txn, $sub_status = false)
    {
        $u = $txn->user();
        $u->update_member_data();
    }

    /**
     * Updates event metadata.
     *
     * @param MeprEvent $evt The event object.
     *
     * @return void
     */
    public function update_event_meta($evt)
    {
        if ($evt->evt_id_type === MeprEvent::$users_str && $evt->event === MeprEvent::$login_event_str) {
            $u = $evt->get_data();
            $u->update_member_data();
        }
    }

    /**
     * Updates member metadata.
     *
     * @param integer $user_id The user ID.
     *
     * @return void
     */
    public function update_member_meta($user_id)
    {
        $u = new MeprUser($user_id);
        $u->update_member_data();
    }

    /**
     * Updates member data from a subscription.
     *
     * @param MeprSubscription $subscription The subscription object.
     *
     * @return void
     */
    public function update_member_data_from_subscription($subscription)
    {
        $member = $subscription->user();
        $member->update_member_data();
    }

    /**
     * Deletes member metadata.
     *
     * @param integer $user_id The user ID.
     *
     * @return void
     */
    public function delete_member_meta($user_id)
    {
        $u = new MeprUser($user_id);
        $u->delete_member_data();
    }

    /**
     * Validates the input for creating a new member.
     *
     * @return array An array of validation errors.
     */
    public function validate_new_member()
    {
        $errors = [];
        $usr    = new MeprUser();

        if (!isset($_POST['member']['user_login']) || empty($_POST['member']['user_login'])) {
            $errors[] = __('The username field can\'t be blank.', 'memberpress');
        }

        if (username_exists($_POST['member']['user_login'])) {
            $errors[] = __('This username is already taken.', 'memberpress');
        }

        if (!validate_username($_POST['member']['user_login'])) {
            $errors[] = __('The username must be valid.', 'memberpress');
        }

        if (!isset($_POST['member']['user_email']) || empty($_POST['member']['user_email'])) {
            $errors[] = __('The email field can\'t be blank.', 'memberpress');
        }

        if (email_exists($_POST['member']['user_email'])) {
            $errors[] = __('This email is already being used by another user.', 'memberpress');
        }

        if (!is_email(stripslashes($_POST['member']['user_email']))) {
            $errors[] = __('A valid email must be entered.', 'memberpress');
        }

        // Simple validation here.
        if (!isset($_POST['transaction']['amount']) || empty($_POST['transaction']['amount'])) {
            $errors[] = __('The transaction amount must be set.', 'memberpress');
        }

        if (preg_match('/[^0-9., ]/', $_POST['transaction']['amount'])) {
            $errors[] = __('The transaction amount must be a number.', 'memberpress');
        }

        if (empty($_POST['transaction']['trans_num']) || preg_match('#[^a-zA-z0-9_\-]#', $_POST['transaction']['trans_num'])) {
            $errors[] = __('The Transaction Number is required, and must contain only letters, numbers, underscores and hyphens.', 'memberpress');
        }

        return $errors;
    }

    /**
     * Renders the search box for the members table.
     *
     * @return void
     */
    public function table_search_box()
    {
        if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'memberpress-members') {
            $membership = (isset($_REQUEST['membership']) ? $_REQUEST['membership'] : false);
            $status     = (isset($_REQUEST['status']) ? $_REQUEST['status'] : 'all');
            $prds       = MeprCptModel::all('MeprProduct', false, [
                'orderby' => 'title',
                'order'   => 'ASC',
            ]);
            MeprView::render('/admin/members/search_box', compact('membership', 'status', 'prds'));
        }
    }

    /**
     * Exports members data to a CSV file.
     *
     * @return void
     */
    public function csv()
    {
        check_ajax_referer('export_members', 'mepr_members_nonce');

        $filename = 'members-' . time();

        // Since we're running WP_List_Table headless we need to do this.
        $GLOBALS['hook_suffix'] = false;

        $screen = get_current_screen();
        $tab    = new MeprMembersTable($screen, $this->get_columns());

        if (isset($_REQUEST['all']) && !empty($_REQUEST['all'])) {
            $search       = isset($_REQUEST['search']) && !empty($_REQUEST['search']) ? esc_sql($_REQUEST['search'])  : '';
            $search_field = isset($_REQUEST['search']) && !empty($_REQUEST['search-field'])  ? esc_sql($_REQUEST['search-field'])  : 'any';
            $search_field = isset($tab->db_search_cols[$search_field]) ? $tab->db_search_cols[$search_field] : 'any';

            $all = MeprUser::list_table(
                'user_login',
                'ASC',
                '',
                $search,
                $search_field,
                '',
                $_REQUEST,
                true
            );

            add_filter('mepr_process_csv_cell', [$this,'process_custom_field'], 10, 2);
            MeprUtils::render_csv($all['results'], $filename);
        } else {
            $tab->prepare_items();
            MeprUtils::render_csv($tab->get_items(), $filename);
        }
    }

    /**
     * Processes custom fields for CSV export.
     *
     * @param mixed  $field The field value.
     * @param string $label The field label.
     *
     * @return mixed The processed field value.
     */
    public function process_custom_field($field, $label)
    {
        $mepr_options = MeprOptions::fetch();

        // Pull out our serialized custom field values.
        if (is_serialized($field)) {
            $field_settings = $mepr_options->get_custom_field($label);

            if (empty($field_settings)) {
                return $field;
            }

            if ($field_settings->field_type == 'multiselect') {
                $field = unserialize($field);
                return implode(',', $field);
            } elseif ($field_settings->field_type == 'checkboxes') {
                $field = unserialize($field);
                return implode(',', array_keys($field));
            }
        }

        return $field;
    }

    /**
     * Adds a footer link for exporting members.
     *
     * @param string  $action     The action name.
     * @param integer $totalitems The total number of items.
     * @param integer $itemcount  The number of items to export.
     *
     * @return void
     */
    public function export_footer_link($action, $totalitems, $itemcount)
    {
        if ($action == 'mepr_members') {
            MeprAppHelper::export_table_link($action, 'export_members', 'mepr_members_nonce', $itemcount);
            ?> | <?php
      MeprAppHelper::export_table_link($action, 'export_members', 'mepr_members_nonce', $totalitems, true);
        }
    }

    /**
     * Displays the DRM listing.
     *
     * @return void
     */
    public function listing_drm()
    {
        $this->display_list();
    }
}
