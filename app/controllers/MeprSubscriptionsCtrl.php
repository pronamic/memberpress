<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprSubscriptionsCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for subscription management.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_mepr_subscr_num_search', [$this, 'subscr_num_search']);
        add_action('wp_ajax_mepr_subscr_edit_status', [$this, 'edit_subscr_status']);
        add_action('wp_ajax_mepr_delete_subscription', [$this, 'delete_subscription']);
        add_action('wp_ajax_mepr_suspend_subscription', [$this, 'suspend_subscription']);
        add_action('wp_ajax_mepr_resume_subscription', [$this, 'resume_subscription']);
        add_action('wp_ajax_mepr_resume_subscription_email_customer', [$this, 'resume_subscription_email_customer']);
        add_action('wp_ajax_mepr_cancel_subscription', [$this, 'cancel_subscription']);
        add_action('wp_ajax_mepr_subscriptions', [$this, 'csv']);
        add_action('wp_ajax_mepr_lifetime_subscriptions', [$this, 'lifetime_csv']);
        add_action('mepr_control_table_footer', [$this, 'export_footer_link'], 10, 3);

        // Screen Options
        $hook = $this->get_hook();
        add_action("load-{$hook}", [$this,'add_recurring_screen_options']);
        add_filter("manage_{$hook}_columns", [$this, 'get_columns']);

        $hook = $this->get_hook(true);
        add_action("load-{$hook}", [$this,'add_lifetime_screen_options']);
        add_filter("manage_{$hook}_columns", [$this, 'get_lifetime_columns']);

        add_filter('set_screen_option_mp_lifetime_subs_perpage', [$this,'setup_screen_options_lifetime'], 10, 3);
        add_filter('set_screen_option_mp_subs_perpage', [$this,'setup_screen_options_subs'], 10, 3);

        add_action('mepr_table_controls_search', [$this, 'table_search_box']);
    }

    /**
     * Get the admin page hook for subscriptions.
     *
     * @param  boolean $lifetime Whether to get the hook for lifetime subscriptions.
     * @return string
     */
    private function get_hook($lifetime = false)
    {
        if ($lifetime) {
            return 'memberpress_page_memberpress-lifetimes';
        }
        return 'memberpress_page_memberpress-subscriptions';
    }

    /**
     * Check if the current screen is for lifetime subscriptions.
     *
     * @return boolean
     */
    private function is_lifetime()
    {
        $screen = get_current_screen();

        if (isset($screen) && is_object($screen)) {
            return ( $this->get_hook(true) === $screen->id );
        } elseif (isset($_GET['page'])) {
            return ( 'memberpress-lifetimes' === $_GET['page'] );
        } else {
            return false;
        }
    }

    /**
     * Handle the listing of subscriptions.
     *
     * @return void
     */
    public function listing()
    {
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'list';

        switch ($action) {
            case 'new':
                $this->new_sub();
                break;
            case 'edit':
                $this->edit();
                break;
            case 'list':
                $this->display_list();
                break;
            default:
                break;
        }
    }

    /**
     * Display the list of subscriptions.
     *
     * @return void
     */
    private function display_list()
    {
        $screen    = get_current_screen();
        $lifetime  = ( $screen->id === $this->get_hook(true) );
        $sub_table = new MeprSubscriptionsTable($screen, $this->get_columns(), $lifetime);
        $sub_table->prepare_items();
        MeprView::render('/admin/subscriptions/list', get_defined_vars());
    }

    /**
     * Create a new subscription.
     *
     * @return void
     */
    private function new_sub()
    {
        $mepr_options = MeprOptions::fetch();
        $sub          = new MeprSubscription();

        if (MeprUtils::is_post_request()) {
            $errors = $this->validate();
            if (empty($errors)) {
                if ($this->create_or_update($sub)) {
                    $sub             = new MeprSubscription($sub->id);
                    $user            = $sub->user();
                    $sub->user_login = $user->user_login;
                    $message         = __('A subscription was created successfully.', 'memberpress');
                } else {
                    $errors[] = __('There was a problem creating the subscription', 'memberpress');
                }
                $_REQUEST['id']     = $sub->id;
                $_REQUEST['action'] = 'edit';
                MeprView::render('/admin/subscriptions/edit', get_defined_vars());
            } else {
                MeprView::render('/admin/subscriptions/new', get_defined_vars());
            }
        } else {
            MeprView::render('/admin/subscriptions/new', get_defined_vars());
        }
    }

    /**
     * Edit an existing subscription.
     *
     * @return void
     */
    private function edit()
    {
        $mepr_options = MeprOptions::fetch();
        if (isset($_REQUEST['id'])) {
            $sub = new MeprSubscription($_REQUEST['id']);
            if ($sub->id > 0) {
                $user            = $sub->user();
                $sub->user_login = $user->user_login;
                if (MeprUtils::is_post_request()) {
                    $errors = $this->validate();
                    if (empty($errors) && $this->create_or_update($sub)) {
                        $message = __('The subscription was updated successfully.', 'memberpress');
                    } else {
                        $errors[] = __('There was a problem updating the subscription', 'memberpress');
                    }
                    MeprView::render('/admin/subscriptions/edit', get_defined_vars());
                } else {
                    MeprView::render('/admin/subscriptions/edit', get_defined_vars());
                }
            } else {
                $this->new_sub();
            }
        } else {
            $this->new_sub();
        }
    }

    /**
     * Create or update a subscription.
     *
     * @param  MeprSubscription $sub The subscription object.
     * @return boolean
     */
    private function create_or_update($sub)
    {
        check_admin_referer('mepr_create_or_update_subscription', 'mepr_subscriptions_nonce');

        extract($_POST, EXTR_SKIP);
        $user = new MeprUser();
        $user->load_user_data_by_login($user_login);
        $sub->user_id                    = $user->ID;
        $sub->subscr_id                  = wp_unslash($subscr_id);
        $sub->product_id                 = $product_id;
        $product                         = new MeprProduct($product_id);
        $sub->price                      = isset($price) ? MeprUtils::format_currency_us_float($price) : MeprUtils::format_currency_us_float($product->price);
        $sub->period                     = isset($period) ? (int) $period : (int) $product->period;
        $sub->period_type                = isset($period_type) ? (string) $period_type : (string) $product->period_type;
        $sub->limit_cycles               = isset($limit_cycles) ? (bool) $limit_cycles : $product->limit_cycles;
        $sub->limit_cycles_num           = isset($limit_cycles_num) ? (int) $limit_cycles_num : (int) $product->limit_cycles_num;
        $sub->limit_cycles_action        = isset($limit_cycles_action) ? $limit_cycles_action : $product->limit_cycles_action;
        $sub->limit_cycles_expires_after = isset($limit_cycles_expires_after) ? (int) $limit_cycles_expires_after : (int) $product->limit_cycles_expires_after;
        $sub->limit_cycles_expires_type  = isset($limit_cycles_expires_type) ? (string) $limit_cycles_expires_type : (string) $product->limit_cycles_expires_type;
        $sub->tax_amount                 = MeprUtils::format_currency_us_float($tax_amount);
        $sub->tax_rate                   = MeprUtils::format_currency_us_float($tax_rate);
        $sub->total                      = $sub->price + $sub->tax_amount;
        $sub->status                     = $status;
        $sub->gateway                    = $gateway;
        $sub->trial                      = isset($trial) ? (bool) $trial : false;
        $sub->trial_days                 = (int) $trial_days;
        $sub->trial_amount               = MeprUtils::format_currency_us_float($trial_amount);
        $sub->trial_tax_amount           = (isset($trial_tax_amount) ? (float) $trial_tax_amount : 0.0);
        $sub->trial_total                = (isset($trial_total) ? (float) $trial_total : 0.0);
        if (isset($created_at) && (empty($created_at) || is_null($created_at))) {
            $sub->created_at = MeprUtils::ts_to_mysql_date(time());
        } else {
            $sub->created_at = MeprUtils::ts_to_mysql_date(strtotime($created_at));
        }
        return $sub->store();
    }

    /**
     * Validate subscription data.
     *
     * @return array
     */
    private function validate()
    {
        $errors = [];
        extract($_POST, EXTR_SKIP);
        $user = new MeprUser();

        if (isset($subscr_id) && !empty($subscr_id)) {
            if (preg_match('#[^a-zA-z0-9_\-]#', $subscr_id)) {
                $errors[] = __('The Subscription ID must contain only letters, numbers, underscores and hyphens', 'memberpress');
            }
        } else {
            $errors[] = __('The Subscription ID is required', 'memberpress');
        }
        if (!isset($user_login) || empty($user_login)) {
            $errors[] = __('The username is required', 'memberpress');
        } elseif (is_email($user_login) && !username_exists($user_login)) {
            $user->load_user_data_by_email($user_login);
            if (!$user->ID) {
                $errors[] = __('You must enter a valid username or email address', 'memberpress');
            } else { // For use downstream in create and update transaction methods
                $_POST['user_login'] = $user->user_login;
            }
        } else {
            $user->load_user_data_by_login($user_login);
            if (!$user->ID) {
                $errors[] = __('You must enter a valid username or email address', 'memberpress');
            }
        }
        if (!isset($product_id) || empty($product_id)) {
            $errors[] = __('Membership is required', 'memberpress');
        } else {
            $product = new MeprProduct($product_id);
            if (!isset($product->ID)) {
                $errors[] = __('A valid membership is required', 'memberpress');
            }
        }
        if (!isset($price) || empty($price)) {
            $errors[] = __('The sub-total is required', 'memberpress');
        }
        if (preg_match('/[^0-9., ]/', $price)) {
            $errors[] = __('The sub-total must be a number', 'memberpress');
        }
        if (!is_numeric($trial_days)) {
            $errors[] = __('The trial days must be a number', 'memberpress');
        }

        return $errors;
    }

    /**
     * Enqueue scripts for the subscription admin page.
     *
     * @param  string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_scripts($hook)
    {
        if ($hook === $this->get_hook() || $hook === $this->get_hook(true)) {
            $l10n = [
                'del_sub'                                  => __('A Subscription should be cancelled (at the Gateway or here) by you, or by the Member on their Account page before being removed. Deleting an Active Subscription can cause future recurring payments not to be tracked properly. Are you sure you want to delete this Subscription?', 'memberpress'),
                'del_sub_error'                            => __('The Subscription could not be deleted. Please try again later.', 'memberpress'),
                'cancel_sub'                               => __('This will cancel all future payments for this subscription. Are you sure you want to cancel this Subscription?', 'memberpress'),
                'cancel_sub_error'                         => __('The Subscription could not be cancelled here. Please login to your gateway\'s virtual terminal to cancel it.', 'memberpress'),
                'cancel_sub_success'                       => __('The Subscription was successfully cancelled.', 'memberpress'),
                'cancelled_text'                           => __('Stopped', 'memberpress'),
                'suspend_sub'                              => __("This will stop all payments for this subscription until the user logs into their account and resumes.\n\nAre you sure you want to pause this Subscription?", 'memberpress'),
                'suspend_sub_error'                        => __('The Subscription could not be paused here. Please login to your gateway\'s virtual terminal to pause it.', 'memberpress'),
                'suspend_sub_success'                      => __('The Subscription was successfully paused.', 'memberpress'),
                'suspend_text'                             => __('Paused', 'memberpress'),
                'resume_sub'                               => __("This will resume payments for this subscription.\n\nAre you sure you want to resume this Subscription?", 'memberpress'),
                'resume_sub_error'                         => __('The Subscription could not be resumed here. Please login to your gateway\'s virtual terminal to resume it.', 'memberpress'),
                'resume_sub_success'                       => __('The Subscription was successfully resumed.', 'memberpress'),
                'resume_sub_requires_action'               => __('The Subscription could not be resumed automatically because the customer needs to authorize the transaction. Do you want to send the customer an email with a link to pay the invoice?', 'memberpress'),
                'resume_sub_customer_email_sent'           => __('The email has been sent to the customer. The Subscription will resume when the invoice has been paid.', 'memberpress'),
                'resume_sub_customer_email_error'          => __("An error occurred sending the email to the customer:\n\n%s", 'memberpress'),
                'server_response_invalid'                  => __('The response from the server was invalid or malformed', 'memberpress'),
                'ajax_error'                               => __('Ajax error', 'memberpress'),
                'resume_text'                              => __('Enabled', 'memberpress'),
                'delete_subscription_nonce'                => wp_create_nonce('delete_subscription'),
                'suspend_subscription_nonce'               => wp_create_nonce('suspend_subscription'),
                'update_status_subscription_nonce'         => wp_create_nonce('update_status_subscription'),
                'resume_subscription_nonce'                => wp_create_nonce('resume_subscription'),
                'resume_subscription_email_customer_nonce' => wp_create_nonce('resume_subscription_email_customer'),
                'cancel_subscription_nonce'                => wp_create_nonce('cancel_subscription'),
            ];

            wp_enqueue_style('mepr-subscriptions-css', MEPR_CSS_URL . '/admin-subscriptions.css', [], MEPR_VERSION);
            wp_register_script('mphelpers', MEPR_JS_URL . '/mphelpers.js', ['suggest'], MEPR_VERSION);
            wp_enqueue_script('mepr-subscriptions-js', MEPR_JS_URL . '/admin_subscriptions.js', ['jquery', 'mphelpers'], MEPR_VERSION);
            wp_enqueue_script('mepr-table-controls-js', MEPR_JS_URL . '/table_controls.js', ['jquery'], MEPR_VERSION);
            wp_localize_script('mepr-subscriptions-js', 'MeprSub', $l10n);
        }
    }

    /**
     * Edit the status of a subscription via AJAX.
     *
     * @return void
     */
    public function edit_subscr_status()
    {
        check_ajax_referer('update_status_subscription', 'mepr_subscriptions_nonce');

        if (
            !isset($_POST['id']) || empty($_POST['id']) ||
            !isset($_POST['value']) || empty($_POST['value'])
        ) {
            die(__('Save Failed', 'memberpress'));
        }

        $id    = sanitize_key($_POST['id']);
        $value = sanitize_text_field($_POST['value']);

        $sub = new MeprSubscription($id);
        if (empty($sub->id)) {
            die(__('Save Failed', 'memberpress'));
        }

        $sub->status = $value;
        $sub->store();

        echo MeprAppHelper::human_readable_status($value, 'subscription');
        die();
    }

    /**
     * Delete a subscription via AJAX.
     *
     * @return void
     */
    public function delete_subscription()
    {
        check_ajax_referer('delete_subscription', 'mepr_subscriptions_nonce');

        if (!MeprUtils::is_mepr_admin()) {
            die(__('You do not have access.', 'memberpress'));
        }

        if (!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
            die(__('Could not delete subscription', 'memberpress'));
        }

        $sub = new MeprSubscription($_POST['id']);
        $sub->destroy();

        die('true'); // don't localize this string
    }

    /**
     * Suspend a subscription via AJAX.
     *
     * @return void
     */
    public function suspend_subscription()
    {
        check_ajax_referer('suspend_subscription', 'mepr_subscriptions_nonce');

        if (!MeprUtils::is_mepr_admin()) {
            die(__('You do not have access.', 'memberpress'));
        }

        if (!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
            die(__('Could not pause subscription', 'memberpress'));
        }

        $sub = new MeprSubscription($_POST['id']);
        $sub->suspend();

        die('true'); // don't localize this string
    }

    /**
     * Resume a subscription via AJAX.
     *
     * @return void
     */
    public function resume_subscription()
    {
        if (!check_ajax_referer('resume_subscription', 'mepr_subscriptions_nonce', false)) {
            wp_send_json([
                'status'  => 'error',
                'message' => __('Security check failed.', 'memberpress'),
            ]);
        }

        if (!MeprUtils::is_mepr_admin()) {
            wp_send_json([
                'status'  => 'error',
                'message' => __('You do not have access.', 'memberpress'),
            ]);
        }

        if (!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
            wp_send_json([
                'status'  => 'error',
                'message' => __('Could not resume subscription', 'memberpress'),
            ]);
        }

        $sub = new MeprSubscription($_POST['id']);

        try {
            $sub->resume();
        } catch (MeprGatewayRequiresActionException $e) {
            wp_send_json([
                'status' => 'requires_action',
            ]);
        } catch (Exception $e) {
            wp_send_json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        wp_send_json(['status' => 'success']);
    }

    /**
     * Send an email to the customer to resume a subscription via AJAX.
     *
     * @return void
     */
    public function resume_subscription_email_customer()
    {
        if (!check_ajax_referer('resume_subscription_email_customer', 'mepr_subscriptions_nonce', false)) {
            wp_send_json([
                'status'  => 'error',
                'message' => __('Security check failed.', 'memberpress'),
            ]);
        }

        if (!MeprUtils::is_mepr_admin()) {
            wp_send_json([
                'status'  => 'error',
                'message' => __('You do not have access.', 'memberpress'),
            ]);
        }

        if (!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
            wp_send_json([
                'status'  => 'error',
                'message' => __('Could not resume subscription', 'memberpress'),
            ]);
        }

        $sub = new MeprSubscription($_POST['id']);

        if (!$sub->id) {
            wp_send_json([
                'status'  => 'error',
                'message' => __('Subscription not found.', 'memberpress'),
            ]);
        }

        $pm = $sub->payment_method();

        if ($pm instanceof MeprStripeGateway) {
            try {
                $pm->send_latest_invoice_payment_email($sub);
            } catch (Exception $e) {
                wp_send_json([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ]);
            }
        } else {
            wp_send_json([
                'status'  => 'error',
                'message' => __('The subscription\'s payment method does not support this.', 'memberpress'),
            ]);
        }

        wp_send_json(['status' => 'success']);
    }

    /**
     * Cancel a subscription via AJAX.
     *
     * @return void
     */
    public function cancel_subscription()
    {
        check_ajax_referer('cancel_subscription', 'mepr_subscriptions_nonce');

        if (!MeprUtils::is_mepr_admin()) {
            die(__('You do not have access.', 'memberpress'));
        }

        if (!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
            die(__('Could not cancel subscription', 'memberpress'));
        }

        $sub = new MeprSubscription($_POST['id']);

        try {
            $sub->cancel();
        } catch (Exception $e) {
            die($e->getMessage());
        }

        die('true'); // don't localize this string
    }

    /**
     * Search for subscriptions by subscription number via AJAX.
     *
     * @return void
     */
    public function subscr_num_search()
    {
        if (!MeprUtils::is_mepr_admin()) {
            die('-1');
        }

        // jQuery suggest plugin has already trimmed and escaped user input (\ becomes \\)
        // so we just need to sanitize the username
        $s = sanitize_user($_GET['q']);

        if (strlen($s) < 5) {
            die();
        } // require 5 chars for matching

        $subs = MeprSubscription::search_by_subscr_id($s);

        MeprView::render('/admin/subscriptions/search', get_defined_vars());
        die();
    }

    /**
     * Export subscriptions to CSV.
     *
     * @param  boolean $lifetime Whether to export lifetime subscriptions.
     * @return void
     */
    public function csv($lifetime = false)
    {
        check_ajax_referer('export_subscriptions', 'mepr_subscriptions_nonce');

        $filename = ( $lifetime ? 'non-recurring-' : '' ) . 'subscriptions-' . time();

        // Since we're running WP_List_Table headless we need to do this
        $GLOBALS['hook_suffix'] = false;

        $screen = get_current_screen();
        $tab    = new MeprSubscriptionsTable($screen, $this->get_columns(), $lifetime);

        if (isset($_REQUEST['all']) && !empty($_REQUEST['all'])) {
            $search       = isset($_REQUEST['search']) && !empty($_REQUEST['search']) ? esc_sql($_REQUEST['search'])  : '';
            $search_field = isset($_REQUEST['search']) && !empty($_REQUEST['search-field'])  ? esc_sql($_REQUEST['search-field'])  : 'any';
            $search_field = isset($tab->db_search_cols[$search_field]) ? $tab->db_search_cols[$search_field] : 'any';

            if ($lifetime) {
                $all = MeprSubscription::lifetime_subscr_table(
                    'created_at',
                    'ASC',
                    '',
                    $search,
                    $search_field,
                    '',
                    false,
                    $_REQUEST
                );
            } else {
                $all = MeprSubscription::subscr_table(
                    'created_at',
                    'ASC',
                    '',
                    $search,
                    $search_field,
                    '',
                    false,
                    $_REQUEST
                );
            }

            MeprUtils::render_csv($all['results'], $filename);
        } else {
            $tab->prepare_items();

            MeprUtils::render_csv($tab->get_items(), $filename);
        }
    }

    /**
     * Export lifetime subscriptions to CSV.
     *
     * @return void
     */
    public function lifetime_csv()
    {
        $this->csv(true);
    }

    /**
     * Add export links to the footer of the subscription table.
     *
     * @param  string  $action     The action being performed.
     * @param  integer $totalitems The total number of items.
     * @param  integer $itemcount  The number of items on the current page.
     * @return void
     */
    public function export_footer_link($action, $totalitems, $itemcount)
    {
        if ($action == 'mepr_subscriptions' || $action == 'mepr_lifetime_subscriptions') {
            MeprAppHelper::export_table_link($action, 'export_subscriptions', 'mepr_subscriptions_nonce', $itemcount);
            ?> | <?php
      MeprAppHelper::export_table_link($action, 'export_subscriptions', 'mepr_subscriptions_nonce', $totalitems, true);
        }
    }

    /**
     * Get the columns for the lifetime subscriptions table.
     *
     * @param  boolean $lifetime Whether to get lifetime columns.
     * @return array
     */
    public function get_columns($lifetime = false)
    {
        $prefix = $lifetime ? 'col_txn_' : 'col_';
        $cols   = [
            $prefix . 'id'           => __('Id', 'memberpress'),
            $prefix . 'subscr_id'    => __('Subscription', 'memberpress'),
            $prefix . 'active'       => __('Active', 'memberpress'),
            $prefix . 'status'       => __('Auto Rebill', 'memberpress'),
            $prefix . 'product'      => __('Membership', 'memberpress'),
            $prefix . 'product_meta' => __('Terms', 'memberpress'),
            $prefix . 'propername'   => __('Name', 'memberpress'),
            $prefix . 'member'       => __('User', 'memberpress'),
            $prefix . 'gateway'      => __('Gateway', 'memberpress'),
            $prefix . 'txn_count'    => __('Transaction', 'memberpress'),
            $prefix . 'created_at'   => __('Created On', 'memberpress'),
            $prefix . 'expires_at'   => __('Expires On', 'memberpress'),
        ];

        if ($lifetime) {
            unset($cols[$prefix . 'status']);
            unset($cols[$prefix . 'delete_sub']);
            unset($cols[$prefix . 'txn_count']);
            $cols[$prefix . 'subscr_id']    = __('Transaction', 'memberpress');
            $cols[$prefix . 'product_meta'] = __('Price', 'memberpress');
        }

        return MeprHooks::apply_filters('mepr-admin-subscriptions-cols', $cols, $prefix, $lifetime);
    }

    /**
     * Get the columns for the lifetime subscriptions table.
     *
     * @return array
     */
    public function get_lifetime_columns()
    {
        return $this->get_columns(true);
    }

    /**
     * Add screen options for the subscriptions page.
     *
     * @param  string $optname The option name for the screen options.
     * @return void
     */
    public function add_screen_options($optname = 'mp_subs_perpage')
    {
        add_screen_option('layout_columns');
        add_screen_option('per_page', [
            'label'   => __('Subscriptions', 'memberpress'),
            'default' => 10,
            'option'  => $optname,
        ]);
    }

    /**
     * Add screen options for recurring subscriptions.
     *
     * @return void
     */
    public function add_recurring_screen_options()
    {
        $this->add_screen_options('mp_subs_perpage');
    }

    /**
     * Add screen options for lifetime subscriptions.
     *
     * @return void
     */
    public function add_lifetime_screen_options()
    {
        $this->add_screen_options('mp_lifetime_subs_perpage');
    }

    /**
     * Setup screen options for lifetime subscriptions.
     *
     * @param  mixed  $status The current status.
     * @param  string $option The option name.
     * @param  mixed  $value  The option value.
     * @return mixed
     */
    public function setup_screen_options_lifetime($status, $option, $value)
    {
        if ('mp_lifetime_subs_perpage' === $option) {
            return $value;
        }
        return $status;
    }

    /**
     * Setup screen options for subscriptions.
     *
     * @param  mixed  $status The current status.
     * @param  string $option The option name.
     * @param  mixed  $value  The option value.
     * @return mixed
     */
    public function setup_screen_options_subs($status, $option, $value)
    {
        if ('mp_subs_perpage' === $option) {
            return $value;
        }
        return $status;
    }

    /**
     * Display the search box for the subscription table.
     *
     * @return void
     */
    public function table_search_box()
    {
        if (isset($_REQUEST['page']) && ($_REQUEST['page'] == 'memberpress-subscriptions' || $_REQUEST['page'] == 'memberpress-lifetimes')) {
            $mepr_options = MeprOptions::fetch();

            $membership = (isset($_REQUEST['membership']) ? $_REQUEST['membership'] : false);
            $status     = (isset($_REQUEST['status']) ? $_REQUEST['status'] : 'all');
            $gateway    = (isset($_REQUEST['gateway']) ? $_REQUEST['gateway'] : 'all');

            $args     = [
                'orderby' => 'title',
                'order'   => 'ASC',
            ];
            $prds     = MeprCptModel::all('MeprProduct', false, $args);
            $gateways = $mepr_options->payment_methods();

            MeprView::render('/admin/subscriptions/search_box', compact('membership', 'status', 'prds', 'gateways', 'gateway'));
        }
    }
}
