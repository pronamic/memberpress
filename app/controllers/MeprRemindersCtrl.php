<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprRemindersCtrl extends MeprCptCtrl
{
    /**
     * Load hooks for managing reminders and cron jobs.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('save_post', [$this, 'save_postdata']);

        $disable_reminder_crons = get_option('mepr_disable_reminder_crons');
        if (!$disable_reminder_crons) {
            $r = new MeprReminder();
            foreach ($r->event_actions as $e) {
                add_action($e, [$this, 'send_reminders']);
            }

            // Set up cron schedules.
            add_filter('cron_schedules', [$this, 'intervals']);
            add_action('mepr_reminders_worker', [$this, 'worker']);
            add_action('plugins_loaded', [$this, 'schedule_reminders']);
        } else {
            $this->unschedule_reminders();
        }

        // Clean up crons and possibly other stuff when a reminder is deleted or trashed.
        add_action('before_delete_post', [$this, 'delete']);
        add_action('wp_trash_post', [$this, 'delete']);

        // Add some cols.
        add_action('manage_posts_custom_column', [$this, 'custom_columns'], 10, 2);
        add_filter('manage_edit-mp-reminder_columns', [$this, 'columns']);
    }

    /**
     * Schedule a reminder for a specific ID.
     *
     * @param integer $id The ID of the reminder to schedule.
     *
     * @return void
     */
    public function schedule_reminder($id)
    {
        // Stop zombie cron jobs in their tracks here.
        $reminder = $this->get_valid_reminder($id);

        if ($reminder === false) {
            $this->unschedule_reminder($id);
            return;
        }

        $args = [$id];

        if (!wp_next_scheduled('mepr_reminders_worker', $args)) {
            wp_schedule_event(
                time(),
                'mepr_reminders_worker_interval',
                'mepr_reminders_worker',
                $args
            );
        }
    }

    /**
     * Schedule all reminders.
     *
     * @return void
     */
    public function schedule_reminders()
    {
        $reminders = MeprCptModel::all('MeprReminder');

        if (!empty($reminders)) {
            foreach ($reminders as $r) {
                $vr = $this->get_valid_reminder($r->ID);

                if ($vr !== false) {
                    $this->schedule_reminder($r->ID);
                } else {
                    $this->unschedule_reminder($r->ID);
                }
            }
        }
    }

    /**
     * Unschedule a reminder for a specific ID.
     *
     * @param integer $id The ID of the reminder to unschedule.
     *
     * @return void
     */
    public function unschedule_reminder($id)
    {
        $args      = [$id];
        $timestamp = wp_next_scheduled('mepr_reminders_worker', $args);
        wp_unschedule_event($timestamp, 'mepr_reminders_worker', $args);
    }

    /**
     * Unschedule all reminders.
     *
     * @return void
     */
    public function unschedule_reminders()
    {
        $reminders = MeprCptModel::all('MeprReminder');

        if (!empty($reminders)) {
            foreach ($reminders as $r) {
                $this->unschedule_reminder($r->ID);
            }
        }
    }

    /**
     * Define custom columns for the reminders list table.
     *
     * @param array $columns The existing columns.
     *
     * @return array The modified columns.
     */
    public function columns($columns)
    {
        $columns = [
            'cb'                => '<input type="checkbox" />',
            'title'             => __('Reminder Title', 'memberpress'),
            'send_to_admin'     => __('Send Notice to Admin', 'memberpress'),
            'send_to_member'    => __('Send Reminder to Member', 'memberpress'),
            'reminder_products' => __('Memberships', 'memberpress'),
        ];

        return $columns;
    }

    /**
     * Display custom column content for the reminders list table.
     *
     * @param string  $column  The name of the column.
     * @param integer $post_id The ID of the post.
     *
     * @return void
     */
    public function custom_columns($column, $post_id)
    {
        $reminder = $this->get_valid_reminder($post_id);

        if ($reminder !== false) {
            switch ($reminder->trigger_event) {
                case 'sub-expires':
                    $uclass = 'MeprUserSubExpiresReminderEmail';
                    $aclass = 'MeprAdminSubExpiresReminderEmail';
                    break;
                case 'sub-renews':
                    $uclass = 'MeprUserSubRenewsReminderEmail';
                    $aclass = 'MeprAdminSubRenewsReminderEmail';
                    break;
                case 'signup-abandoned':
                    $uclass = 'MeprUserSignupAbandonedReminderEmail';
                    $aclass = 'MeprAdminSignupAbandonedReminderEmail';
                    break;
                case 'member-signup':
                    $uclass = 'MeprUserMemberSignupReminderEmail';
                    $aclass = 'MeprAdminMemberSignupReminderEmail';
                    break;
                case 'cc-expires':
                    $uclass = 'MeprUserCcExpiresReminderEmail';
                    $aclass = 'MeprAdminCcExpiresReminderEmail';
                    break;
                case 'sub-trial-ends':
                    $uclass = 'MeprUserSubTrialEndsReminderEmail';
                    $aclass = 'MeprAdminSubTrialEndsReminderEmail';
                    break;
                default:
                    echo '';
                    return;
            }

            // TODO: Yah, not pretty but works ... change at some point.
            $cval = '<span style="color: %s; font-size: 120%%;"><strong>%s</strong></span>';

            if ('send_to_admin' == $column) {
                (int)$reminder->emails[$aclass]['enabled'] > 0 ? printf($cval, 'limegreen', '✔︎') : printf($cval, 'red', '✖︎');
            } elseif ('send_to_member' == $column) {
                (int)$reminder->emails[$uclass]['enabled'] > 0 ? printf($cval, 'limegreen', '✔︎') : printf($cval, 'red', '✖︎');
            } elseif ('reminder_products' == $column) {
                echo implode(', ', $reminder->get_formatted_products());
            }
        }
    }

    /**
     * Register the custom post type for reminders.
     *
     * @return void
     */
    public function register_post_type()
    {
        $this->cpt = (object)[
            'slug'   => MeprReminder::$cpt,
            'config' => [
                'labels'               => [
                    'name'               => __('Reminders', 'memberpress'),
                    'singular_name'      => __('Reminder', 'memberpress'),
                    'add_new'            => __('Add New', 'memberpress'),
                    'add_new_item'       => __('Add New Reminder', 'memberpress'),
                    'edit_item'          => __('Edit Reminder', 'memberpress'),
                    'new_item'           => __('New Reminder', 'memberpress'),
                    'view_item'          => __('View Reminder', 'memberpress'),
                    'search_items'       => __('Search Reminders', 'memberpress'),
                    'not_found'          => __('No Reminders found', 'memberpress'),
                    'not_found_in_trash' => __('No Reminders found in Trash', 'memberpress'),
                    'parent_item_colon'  => __('Parent Reminder:', 'memberpress'),
                ],
                'public'               => false,
                'show_ui'              => true, // MeprUpdateCtrl::is_activated(),.
                'show_in_menu'         => 'memberpress',
                'capability_type'      => 'post',
                'hierarchical'         => false,
                'register_meta_box_cb' => [$this, 'add_meta_boxes'],
                'rewrite'              => false,
                'supports'             => ['none', 'title'],
            ],
        ];
        register_post_type($this->cpt->slug, $this->cpt->config);
    }

    /**
     * Add meta boxes for the reminders post type.
     *
     * @return void
     */
    public function add_meta_boxes()
    {
        add_meta_box(
            'mp-reminder-trigger',
            __('Trigger', 'memberpress'),
            [$this, 'trigger_meta_box'],
            MeprReminder::$cpt,
            'normal'
        );
        add_meta_box(
            'mp-reminder-emails',
            __('Emails', 'memberpress'),
            [$this, 'emails_meta_box'],
            MeprReminder::$cpt,
            'normal'
        );
    }

    /**
     * Enqueue scripts and styles for the reminders admin page.
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        global $current_screen;

        if ($current_screen->post_type == MeprReminder::$cpt) {
            wp_enqueue_style('mepr-jquery-ui-smoothness', MEPR_CSS_URL . '/vendor/jquery-ui/smoothness.min.css', [], '1.13.3');
            wp_dequeue_script('autosave'); // Disable auto-saving.
            wp_enqueue_style('mepr-emails-css', MEPR_CSS_URL . '/admin-emails.css', [], MEPR_VERSION);
            $email_locals = [
                'set_email_defaults_nonce' => wp_create_nonce('set_email_defaults'),
                'send_test_email_nonce'    => wp_create_nonce('send_test_email'),
            ];
            wp_enqueue_script('mepr-emails-js', MEPR_JS_URL . '/admin_emails.js', ['jquery'], MEPR_VERSION);
            wp_localize_script('mepr-emails-js', 'MeprEmail', $email_locals);
            wp_enqueue_style('mepr-reminders-css', MEPR_CSS_URL . '/admin-reminders.css', ['mepr-emails-css'], MEPR_VERSION);
            wp_enqueue_script('mepr-reminders-js', MEPR_JS_URL . '/admin_reminders.js', ['jquery','jquery-ui-spinner','mepr-emails-js'], MEPR_VERSION);
        }
    }

    /**
     * Render the trigger meta box.
     *
     * @return void
     */
    public function trigger_meta_box()
    {
        global $post_id;

        $reminder = new MeprReminder($post_id);
        $nonce    = wp_create_nonce(md5(MeprReminder::$nonce_str . wp_salt()));

        MeprView::render('/admin/reminders/trigger', get_defined_vars());
    }

    /**
     * Render the emails meta box.
     *
     * @return void
     */
    public function emails_meta_box()
    {
        global $post_id;

        $reminder = new MeprReminder($post_id);

        MeprView::render('/admin/reminders/emails', get_defined_vars());
    }

    /**
     * Save post data for reminders.
     *
     * @param integer $post_id The ID of the post being saved.
     *
     * @return integer|void The post ID or void if not applicable.
     */
    public function save_postdata($post_id)
    {
        $post = get_post($post_id);

        if (
            !wp_verify_nonce(
                (isset($_POST[MeprReminder::$nonce_str])) ? $_POST[MeprReminder::$nonce_str] : '',
                md5(MeprReminder::$nonce_str . wp_salt())
            )
        ) {
            return $post_id; // Nonce prevents meta data from being wiped on move to trash.
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }
        if (defined('DOING_AJAX')) {
            return;
        }

        if (!empty($post) && $post->post_type == MeprReminder::$cpt) {
            $reminder = new MeprReminder($post_id);

            $reminder->trigger_length   = sanitize_text_field($_POST[MeprReminder::$trigger_length_str]);
            $reminder->trigger_interval = sanitize_text_field($_POST[MeprReminder::$trigger_interval_str]);
            $reminder->trigger_timing   = sanitize_text_field($_POST[MeprReminder::$trigger_timing_str]);
            $reminder->trigger_event    = sanitize_text_field($_POST[MeprReminder::$trigger_event_str]);
            $reminder->filter_products  = false;
            $reminder->products         = [];

            // Override filter by products vars.
            if (isset($_POST[MeprReminder::$filter_products_str]) && !empty($_POST[MeprReminder::$products_str])) {
                $reminder->filter_products = true;
                $reminder->products        = array_map('sanitize_text_field', $_POST[MeprReminder::$products_str]);
            }

            // Notification Settings.
            $emails = [];
            foreach ($_POST[MeprReminder::$emails_str] as $email => $vals) {
                $emails[$email] = [
                    'enabled'      => isset($vals['enabled']),
                    'use_template' => isset($vals['use_template']),
                    'subject'      => sanitize_text_field(wp_unslash($vals['subject'])),
                    'body'         => MeprUtils::maybe_wpautop(stripslashes($vals['body'])),
                ];
            }

            $reminder->emails = $emails;

            // Don't quite need this yet
            // $reminder = $this->validate($reminder);.
            $reminder->store_meta(); // Only storing metadata here.

            MeprHooks::do_action('mepr-reminder-save-meta', $reminder);
        }
    }

    /**
     * CRON SPECIFIC METHODS
     **/

    /**
     * Define custom cron intervals.
     *
     * @param array $schedules The existing cron schedules.
     *
     * @return array The modified cron schedules.
     */
    public function intervals($schedules)
    {
        $schedules[ 'mepr_reminders_worker_interval' ] = [
            'interval' => MeprUtils::minutes(15),
            'display'  => __('MemberPress Reminders Worker', 'memberpress'),
        ];

        return $schedules;
    }

    /**
     * Get a valid reminder object by ID.
     *
     * @param integer $id The ID of the reminder.
     *
     * @return MeprReminder|false The reminder object or false if invalid.
     */
    public function get_valid_reminder($id)
    {
        // If the remider_id is empty then forget it.
        if (empty($id)) {
            return false;
        }

        $post = get_post($id);

        // Post not found? fail.
        if (empty($post)) {
            return false;
        }

        // Not the right post type? fail.
        if ($post->post_type !== MeprReminder::$cpt) {
            return false;
        }

        // Not a published post? fail.
        if ($post->post_status !== 'publish') {
            return false;
        }

        $reminder = new MeprReminder($id);

        // ID is empty? fail.
        if (empty($reminder->ID)) {
            return false;
        }

        return $reminder;
    }

    /**
     * Worker function to process reminders.
     *
     * @param integer $reminder_id The ID of the reminder to process.
     *
     * @return void
     */
    public function worker($reminder_id)
    {
        $reminder = $this->get_valid_reminder($reminder_id);

        if ($reminder !== false) {
            @set_time_limit(0); // Unlimited run time.
            $run_limit = MeprUtils::minutes(10); // Limit to 10 minutes.

            // The event name will be the same no matter what we're doing here.
            $event = "{$reminder->trigger_timing}-{$reminder->trigger_event}-reminder";

            while ($this->run_time() < $run_limit) {
                $args = $reminder_id;
                $obj  = null;

                switch ($reminder->trigger_event) {
                    case 'sub-expires':
                        $txn = $reminder->get_next_expiring_txn();
                        if ($txn) {
                            $obj = new MeprTransaction($txn->id); // We need the actual model.
                        }
                        break;
                    case 'sub-renews':
                        if ($reminder->trigger_timing == 'before') {
                            $txn = $reminder->get_next_renewing_txn();
                            if ($txn) {
                                $obj = new MeprTransaction($txn->id); // We need the actual model.
                            }
                        } elseif ($reminder->trigger_timing == 'after') {
                            $txn = $reminder->get_renewed_txn();
                            if ($txn) {
                                $obj = new MeprTransaction($txn->id); // We need the actual model.
                            }
                        }
                        break;
                    case 'member-signup':
                        $txn_id = $reminder->get_next_member_signup();
                        if ($txn_id) {
                            $obj = new MeprTransaction($txn_id);
                        }
                        break;
                    case 'signup-abandoned':
                        $txn_id = $reminder->get_next_abandoned_signup();
                        if ($txn_id) {
                            $obj = new MeprTransaction($txn_id);
                        }
                        break;
                    case 'cc-expires':
                        $sub_id = $reminder->get_next_expired_cc();
                        if ($sub_id) {
                            $obj  = new MeprSubscription($sub_id);
                            $args = "{$reminder_id}|{$obj->cc_exp_month}|{$obj->cc_exp_year}";
                        }
                        break;
                    case 'sub-trial-ends':
                        if ($reminder->trigger_timing == 'before') {
                            $sub_id = $reminder->get_next_trial_ends_subs();
                            if ($sub_id) {
                                $obj  = new MeprSubscription($sub_id);
                                $args = "{$reminder_id}|{$obj->trial_days}";
                            }
                        }
                        break;
                    default:
                        $this->unschedule_reminder($reminder_id);
                        break;
                }

                if (isset($obj)) {
                    // We just catch the hooks from these events.
                    MeprEvent::record($event, $obj, $args);
                } else {
                    break; // Break out of the while loop.
                }
            }//end while
        }
    }

    /**
     * Get the runtime of the current process.
     *
     * @return integer The runtime in seconds.
     */
    private function run_time()
    {
        static $start_time;

        if (!isset($start_time)) {
            $start_time = time();
        }

        return ( time() - $start_time );
    }

    /**
     * Send reminder emails to users and admins.
     *
     * @param MeprUser $usr    The user object.
     * @param string   $uclass The user email class.
     * @param string   $aclass The admin email class.
     * @param array    $params The email parameters.
     * @param array    $args   The additional arguments.
     *
     * @return void
     */
    private function send_emails($usr, $uclass, $aclass, $params, $args)
    {
        try {
            $uemail     = MeprEmailFactory::fetch($uclass, 'MeprBaseReminderEmail', $args);
            $uemail->to = $usr->formatted_email();
            $uemail->send_if_enabled($params);

            $aemail = MeprEmailFactory::fetch($aclass, 'MeprBaseReminderEmail', $args);
            $aemail->send_if_enabled($params);
        } catch (Exception $e) {
            // Fail silently for now.
        }
    }

    /**
     * Send reminders based on the event.
     *
     * @param MeprEvent $event The event object.
     *
     * @return void
     */
    public function send_reminders($event)
    {
        // Now that we support renewals on one-time purchases -- we need to make sure they don't get reminded of expirations
        // if they have already renewed their one-time subscription again before the expiring sub reminder is sent out.
        $disable_email = false; // Do not send the emails if this gets set to true.

        if ($event->evt_id_type == 'transactions') {
            $txn = new MeprTransaction($event->evt_id);

            // Do not send reminders to sub-accounts.
            if (isset($txn->parent_transaction_id) && $txn->parent_transaction_id > 0) {
                $disable_email = true;
            }

            $usr      = $txn->user();
            $prd      = new MeprProduct($txn->product_id);
            $reminder = $this->get_valid_reminder($event->args);

            // Fail silently if reminder is invalid.
            if ($reminder === false) {
                return;
            }

            $params = array_merge(MeprRemindersHelper::get_email_params($reminder), MeprTransactionsHelper::get_email_params($txn));

            switch ($reminder->trigger_event) {
                case 'sub-expires':
                    // Don't send a reminder if the user has already renewed either a one-time or an offline subscription.
                    if ($reminder->trigger_timing == 'before') { // Handle when the reminder should go out before.
                        $txn_count = count($usr->transactions_for_product($txn->product_id, false, true));

                        // The txn_count > 1 works well for both renewals and offline subs actually because transactions_for_product
                        // should only ever return a count of currently active (payment type) transactions and no expired transactions.
                        if ($txn_count > 1) {
                            $disable_email = true;
                        }
                    } else { // Handle when the reminder should go out after
                        // Don't send to folks if they have an active txn on this subscription (OR one in the
                        // products group) already yo.
                        $active_subs = $usr->active_product_subscriptions('ids');
                        $product     = new MeprProduct($txn->product_id);
                        $grp         = new MeprGroup($product->group_id);

                        // If product is in a group with an upgrade path check for all memberships in that group.
                        if ($product->group_id && $product->group_id > 0 && $grp->is_upgrade_path) {
                            foreach ($grp->products('ids') as $prd_id) {
                                if (in_array($prd_id, $active_subs, false)) {
                                    $disable_email = true;
                                    break; // Breack out of the loop once we find one.
                                }
                            }
                        } else {
                            // Just check this product.
                            if (in_array($txn->product_id, $active_subs, false)) {
                                $disable_email = true;
                            }
                        }
                    }

                    $uclass = 'MeprUserSubExpiresReminderEmail';
                    $aclass = 'MeprAdminSubExpiresReminderEmail';
                    break;
                case 'sub-renews':
                    if ($reminder->trigger_timing == 'after') {
                        $sub       = $txn->subscription();
                        $txn_count = (isset($sub->txn_count) && $sub->txn_count) ? $sub->txn_count : 0;
                        if ($txn_count < 2 && ($sub->trial == false || ($sub->trial && $sub->trial_amount > 0.00))) {
                                $disable_email = true;
                        }
                    }
                    $uclass = 'MeprUserSubRenewsReminderEmail';
                    $aclass = 'MeprAdminSubRenewsReminderEmail';
                    break;
                case 'signup-abandoned':
                    // Make sure the user is not active on another membership.
                    $active_subs = $usr->active_product_subscriptions('ids');

                    if (!empty($active_subs)) {
                        $disable_email = true;
                    }

                    $uclass = 'MeprUserSignupAbandonedReminderEmail';
                    $aclass = 'MeprAdminSignupAbandonedReminderEmail';
                    break;
                case 'member-signup':
                    $uclass = 'MeprUserMemberSignupReminderEmail';
                    $aclass = 'MeprAdminMemberSignupReminderEmail';
                    break;
                default:
                    $uclass = $aclass = '';
            }

            $args = [['reminder_id' => $event->args]];

            $disable_email = MeprHooks::apply_filters("mepr-{$reminder->trigger_event}-reminder-disable", $disable_email, $reminder, $usr, $prd, $event);
            if (!$disable_email) {
                $this->send_emails($usr, $uclass, $aclass, $params, $args);
            }
        } elseif ($event->evt_id_type == 'subscriptions') {
            $sub = new MeprSubscription($event->evt_id);

            $usr      = $sub->user();
            $prd      = new MeprProduct($sub->product_id);
            $reminder = $this->get_valid_reminder($event->args);

            // Fail silently if reminder is invalid.
            if ($reminder === false) {
                return;
            }

            $parents = get_user_meta($usr->ID, 'mpca_corporate_account_id');

            // Do not email sub account users.
            if (count($parents) > 0) {
                $disable_email = true;
            }

            $params = array_merge(
                MeprRemindersHelper::get_email_params($reminder),
                MeprSubscriptionsHelper::get_email_params($sub)
            );

            switch ($reminder->trigger_event) {
                case 'sub-trial-ends':
                    if ('before' == $reminder->trigger_timing) {
                        $uclass = 'MeprUserSubTrialEndsReminderEmail';
                        $aclass = 'MeprAdminSubTrialEndsReminderEmail';
                    }
                    break;
                case 'cc-expires':
                    $uclass = 'MeprUserCcExpiresReminderEmail';
                    $aclass = 'MeprAdminCcExpiresReminderEmail';
                    break;
                default:
                    $uclass = $aclass = '';
            }

            $args = [['reminder_id' => $reminder->ID]];

            $disable_email = MeprHooks::apply_filters("mepr-{$reminder->trigger_event}-reminder-disable", $disable_email, $reminder, $usr, $prd, $event);
            if (!$disable_email) {
                $this->send_emails($usr, $uclass, $aclass, $params, $args);
            }
        }
    }

    /**
     * Delete a reminder and unschedule its cron job.
     *
     * @param integer $id The ID of the reminder to delete.
     *
     * @return void
     */
    public function delete($id)
    {
        global $post_type;
        if ($post_type != MeprReminder::$cpt) {
            return;
        }
        $this->unschedule_reminder($id);
    }
}
