<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprUsersCtrl extends MeprBaseCtrl
{
    /**
     * Load the necessary hooks for user management.
     *
     * @return void
     */
    public function load_hooks()
    {
        // Admin User Profile login meta box.
        add_action('add_meta_boxes', 'MeprUsersCtrl::login_page_meta_box');
        add_action('save_post', 'MeprUsersCtrl::save_postdata');

        // Admin User Profile customizations.
        add_action('admin_init', 'MeprUsersCtrl::maybe_redirect_member_from_admin');
        add_action('register_post', 'MeprUsersCtrl::maybe_disable_wp_registration_form', 10, 3);
        add_action('init', 'MeprUsersCtrl::maybe_disable_admin_bar', 3);
        add_action('wp_ajax_mepr_resend_welcome_email', 'MeprUsersCtrl::resend_welcome_email_callback');
        add_action('delete_user', 'MeprUsersCtrl::nullify_records_on_delete');
        add_action('admin_enqueue_scripts', 'MeprUsersCtrl::enqueue_scripts');

        // The bbPress profiles apparently pull this in on the front-end, so let's stop that.
        if (is_admin()) {
            // Profile fields show/save.
            add_action('show_user_profile', 'MeprUsersCtrl::extra_profile_fields');
            add_action('edit_user_profile', 'MeprUsersCtrl::extra_profile_fields');
            add_action('personal_options_update', 'MeprUsersCtrl::save_extra_profile_fields');
            add_action('edit_user_profile_update', 'MeprUsersCtrl::save_extra_profile_fields');

            // Purely for showing the errors in the users profile when saving -- it doesn't prevent the saving.
            add_action('user_profile_update_errors', 'MeprUsersCtrl::validate_extra_profile_fields', 10, 3);

            // Phone input script.
            add_action('admin_enqueue_scripts', 'MeprUsersCtrl::enqueue_admin_scripts');
        }

        // User page extra columns.
        add_filter('manage_users_columns', 'MeprUsersCtrl::add_extra_user_columns');
        add_filter('manage_users_sortable_columns', 'MeprUsersCtrl::sortable_extra_user_columns');
        add_filter('manage_users_custom_column', 'MeprUsersCtrl::manage_extra_user_columns', 10, 3);
        add_action('pre_user_query', 'MeprUsersCtrl::extra_user_columns_query_override');
        add_action('wp_ajax_mepr_user_search', 'MeprUsersCtrl::user_search');
        add_filter('wp_privacy_personal_data_erasers', [$this, 'register_mepr_data_eraser'], 10);

        // Shortcodes.
        MeprHooks::add_shortcode('mepr-list-subscriptions', 'MeprUsersCtrl::list_users_subscriptions');
        MeprHooks::add_shortcode('mepr-user-file', 'MeprUsersCtrl::show_user_file');
        MeprHooks::add_shortcode('mepr-user-active-membership-titles', 'MeprUsersCtrl::get_user_active_membership_titles');
    }

    /**
     * Admin scripts for the user profile page
     *
     * @param string $hook Page hook.
     *
     * @return void
     */
    public static function enqueue_admin_scripts($hook)
    {

        if (! in_array($hook, ['user-edit.php', 'profile.php'])) {
            return;
        }

        $mepr_options = MeprOptions::fetch();

        $has_phone = false;

        if (! empty($mepr_options->custom_fields)) {
            foreach ($mepr_options->custom_fields as $field) {
                if ('tel' === $field->field_type && $field->show_on_signup) {
                    $has_phone = true;
                    break;
                }
            }
        }

        // Check if there's a phone field.
        if ($has_phone) {
            wp_enqueue_style('mepr-phone-css', MEPR_CSS_URL . '/vendor/intlTelInput.min.css', [], '16.0.0');
            wp_enqueue_style('mepr-tel-config-css', MEPR_CSS_URL . '/tel_input.css', [], MEPR_VERSION);
            wp_enqueue_script('mepr-phone-js', MEPR_JS_URL . '/vendor/intlTelInput.js', [], '16.0.0', true);
            wp_enqueue_script('mepr-phone-utils-js', MEPR_JS_URL . '/vendor/intlTelInputUtils.js', ['mepr-phone-js'], '16.0.0', true);
            wp_enqueue_script('mepr-tel-config-js', MEPR_JS_URL . '/tel_input.js', ['mepr-phone-js'], MEPR_VERSION, true);
            wp_localize_script('mepr-tel-config-js', 'meprTel', MeprHooks::apply_filters('mepr-phone-input-config', [
                'defaultCountry' => strtolower(get_option('mepr_biz_country')),
                'onlyCountries'  => '',
            ]));
        }
    }

    /**
     * Register the data eraser for MemberPress.
     *
     * @param  array $erasers The existing data erasers.
     * @return array The modified data erasers.
     */
    public static function register_mepr_data_eraser($erasers)
    {
        $erasers[MEPR_PLUGIN_NAME] = [
            'eraser_friendly_name' => MEPR_PLUGIN_NAME,
            'callback'             => ['MeprUsersCtrl', 'erase_pii'],
        ];

        return $erasers;
    }

    /**
     * Erase personally identifiable information (PII) for a user.
     *
     * @param  string  $email The user's email address.
     * @param  integer $page  The page number for pagination.
     * @return array|void The result of the erasure process.
     */
    public static function erase_pii($email, $page = 1)
    {
        $user = get_user_by('email', $email);
        if (!$user) {
            return;
        }

        delete_user_meta($user->ID, 'mepr_vat_number');
        delete_user_meta($user->ID, 'mepr-geo-country');
        delete_user_meta($user->ID, 'mepr-address-one');
        delete_user_meta($user->ID, 'mepr-address-two');
        delete_user_meta($user->ID, 'mepr-address-city');
        delete_user_meta($user->ID, 'mepr-address-state');
        delete_user_meta($user->ID, 'mepr-address-zip');
        delete_user_meta($user->ID, 'mepr-address-country');

        return [
            'items_removed'  => true,
            'items_retained' => false,
            'messages'       => [],
            'done'           => true,
        ];
    }

    /**
     * Display the unauthorized page.
     *
     * @return void
     */
    public static function display_unauthorized_page()
    {
        if (MeprUtils::is_user_logged_in()) {
            MeprView::render('/shared/member_unauthorized', get_defined_vars());
        } else {
            MeprView::render('/shared/unauthorized', get_defined_vars());
        }
    }

    /**
     * Resend the welcome email to a user.
     *
     * @return void
     */
    public static function resend_welcome_email_callback()
    {
        $ajax_nonce   = $_REQUEST['nonce'];
        $mepr_options = MeprOptions::fetch();

        if (wp_verify_nonce($ajax_nonce, 'mepr_resend_welcome_email')) {
            if (MeprUtils::is_logged_in_and_an_admin()) {
                $usr = new MeprUser($_REQUEST['uid']);

                // Get the most recent transaction.
                $txns = MeprTransaction::get_all_complete_by_user_id(
                    $usr->ID,
                    'created_at DESC', // $order_by=''
                    '1', // $limit=''
                    false, // $count=false
                    false, // $exclude_expired=false
                    true // $include_confirmations=false
                );

                if (count($txns) <= 0) {
                    die(__('This user hasn\'t purchased any memberships - so no email will be sent.', 'memberpress'));
                }

                $txn    = new MeprTransaction($txns[0]->id);
                $params = MeprTransactionsHelper::get_email_params($txn);
                $usr    = $txn->user();

                try {
                    $uemail     = MeprEmailFactory::fetch('MeprUserWelcomeEmail');
                    $uemail->to = $usr->formatted_email();
                    $uemail->send($params);
                    die(__('Message Sent', 'memberpress'));
                } catch (Exception $e) {
                    die(__('There was an issue sending the email', 'memberpress'));
                }
            }
            die(__('Why you creepin\'?', 'memberpress'));
        }
        die(__('Cannot resend message', 'memberpress'));
    }

    /**
     * Nullify records associated with a user upon deletion.
     *
     * @param  integer $id The user ID.
     * @return integer The user ID.
     */
    public static function nullify_records_on_delete($id)
    {
        MeprTransaction::nullify_user_id_on_delete($id);
        MeprSubscription::nullify_user_id_on_delete($id);

        return $id;
    }

    /**
     * Email users with expiring transactions.
     *
     * @return boolean|void
     */
    public static function email_users_with_expiring_transactions()
    {
        return MeprUser::email_users_with_expiring_transactions();
    }

    // Not needed
    // public static function unschedule_email_users_with_expiring_transactions()
    // {
    // if($t = wp_next_scheduled('mepr_schedule_renew_emails'))
    // wp_unschedule_event($t, 'mepr_schedule_renew_emails');
    // }.

    /**
     * Enqueue scripts for the user profile page.
     *
     * @param  string $hook The current page hook.
     * @return void
     */
    public static function enqueue_scripts($hook)
    {
        if ($hook == 'user-edit.php' || $hook == 'profile.php') {
            wp_enqueue_style('mepr-jquery-ui-smoothness', MEPR_CSS_URL . '/vendor/jquery-ui/smoothness.min.css', [], '1.13.3');
            wp_enqueue_style('jquery-ui-timepicker-addon', MEPR_CSS_URL . '/vendor/jquery-ui-timepicker-addon.css', ['mepr-jquery-ui-smoothness'], MEPR_VERSION);

            wp_register_script('mepr-timepicker-js', MEPR_JS_URL . '/vendor/jquery-ui-timepicker-addon.js', ['jquery-ui-datepicker'], MEPR_VERSION);
            wp_enqueue_script('mepr-date-picker-js', MEPR_JS_URL . '/date_picker.js', ['mepr-timepicker-js'], MEPR_VERSION);
            wp_enqueue_script('mp-i18n', MEPR_JS_URL . '/i18n.js', ['jquery'], MEPR_VERSION);
            wp_localize_script('mp-i18n', 'MeprI18n', ['states' => MeprUtils::states()]);
            wp_enqueue_script('mp-edit-user', MEPR_JS_URL . '/admin_profile.js', ['jquery', 'suggest', 'mp-i18n'], MEPR_VERSION);
        }
    }

    /**
     * Display extra profile fields on the user profile page.
     *
     * @param  WP_User $wpuser The WordPress user object.
     * @return void
     */
    public static function extra_profile_fields($wpuser)
    {
        $mepr_options = MeprOptions::fetch();
        $user         = new MeprUser($wpuser->ID);

        MeprView::render('/admin/users/extra_profile_fields', get_defined_vars());
    }

    /**
     * Save extra profile fields for a user.
     *
     * @param  integer $user_id   The user ID.
     * @param  boolean $validated Whether the fields have been validated.
     * @param  boolean $product   The product object.
     * @param  boolean $is_signup Whether this is during signup.
     * @param  array   $selected  The selected fields to save.
     * @return boolean
     */
    public static function save_extra_profile_fields($user_id, $validated = false, $product = false, $is_signup = false, $selected = [])
    {
        $mepr_options = MeprOptions::fetch();
        $errors       = [];
        $user         = new MeprUser($user_id);

        if (isset($_POST[MeprUser::$user_message_str])) {
            update_user_meta($user_id, MeprUser::$user_message_str, (string)wp_kses_post($_POST[MeprUser::$user_message_str]));
        }

        // Get the right custom fields.
        if (is_admin() && MeprUtils::is_mepr_admin()) { // An admin is editing the user's profile, so let's save all fields.
            $custom_fields = $mepr_options->custom_fields;
        } elseif ($product !== false) {
            if ($product->customize_profile_fields) {
                $custom_fields = $product->custom_profile_fields();
            } else {
                $custom_fields = $mepr_options->custom_fields;
            }
        } else {
            $custom_fields = $user->custom_profile_fields();
        }

        // Since we use user_* for these, we need to artifically set the $_POST keys correctly for this to work.
        if (!isset($_POST['first_name']) || empty($_POST['first_name'])) {
            $_POST['first_name'] = (isset($_POST['user_first_name'])) ? MeprUtils::sanitize_name_field(wp_unslash($_POST['user_first_name'])) : '';
        }

        if (!isset($_POST['last_name']) || empty($_POST['last_name'])) {
            $_POST['last_name'] = (isset($_POST['user_last_name'])) ? MeprUtils::sanitize_name_field(wp_unslash($_POST['user_last_name'])) : '';
        }

        $custom_fields[] = (object)[
            'field_key'  => 'first_name',
            'field_type' => 'text',
        ];
        $custom_fields[] = (object)[
            'field_key'  => 'last_name',
            'field_type' => 'text',
        ];

        if ($mepr_options->show_address_fields) {
            if (!$product || !$product->disable_address_fields) {
                $custom_fields = array_merge($mepr_options->address_fields, $custom_fields);
            }
        }

        if ($mepr_options->require_privacy_policy && isset($_POST['mepr_agree_to_privacy_policy'])) {
            update_user_meta($user_id, 'mepr_agree_to_privacy_policy', true);
        }

        if ($mepr_options->require_tos && isset($_POST['mepr_agree_to_tos'])) {
            update_user_meta($user_id, 'mepr_agree_to_tos', true);
        }

        // Even though the validate_extra_profile_fields function will show an error on the
        // dashboard profile. It doesn't prevent the profile from saved because
        // user_profile_update_errors is called after the account has been saved which is really lame
        // So let's take care of that here. $validated should ALWAYS be true, except in this one case.
        if (!$validated) {
            $errors = self::validate_extra_profile_fields();
        }

        if (empty($errors)) {
            // TODO: move this somewhere it makes more sense.
            if (isset($_POST['mepr-geo-country'])) {
                update_user_meta($user_id, 'mepr-geo-country', sanitize_text_field($_POST['mepr-geo-country']));
            }

            foreach ($custom_fields as $line) {
                // Allows fields to be selectively saved.
                if (! empty($selected) && ! in_array($line->field_key, $selected)) {
                    continue;
                }

                // Don't do anything if this field isn't shown during signup, and this is a signup.
                if ($is_signup && isset($line->show_on_signup) && !$line->show_on_signup) {
                    continue;
                }
                // Only allow admin to update if it is not shown in account.
                if (!is_admin() && !$is_signup && isset($line->show_in_account) && !$line->show_in_account) {
                    continue;
                }

                if (isset($_POST[$line->field_key]) && !empty($_POST[$line->field_key])) {
                    if (in_array($line->field_type, ['checkboxes', 'multiselect'])) {
                        update_user_meta($user_id, $line->field_key, array_map('sanitize_text_field', array_filter($_POST[$line->field_key])));
                    } elseif ($line->field_type == 'textarea') {
                        update_user_meta($user_id, $line->field_key, sanitize_textarea_field($_POST[$line->field_key]));
                    } else {
                        update_user_meta($user_id, $line->field_key, sanitize_text_field($_POST[$line->field_key]));
                    }
                } else {
                    if ($line->field_type == 'file') {
                        if (isset($_FILES[$line->field_key]['error']) && $_FILES[$line->field_key]['error'] != UPLOAD_ERR_NO_FILE) {
                              $file = $_FILES[$line->field_key];

                            if (!empty($file['name']) && !empty($file['size']) && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
                                add_filter('upload_dir', 'MeprUsersHelper::get_upload_dir');
                                add_filter('upload_mimes', 'MeprUsersHelper::get_allowed_mime_types');

                                $pathinfo = pathinfo($file['name']);
                                $filename = sanitize_file_name($pathinfo['filename'] . '_' . uniqid() . '.' . $pathinfo['extension']);
                                $contents = @file_get_contents($file['tmp_name']);

                                if ($contents !== false) {
                                        $file = wp_upload_bits($filename, null, $contents);

                                    if (isset($file['url'])) {
                                        update_user_meta($user_id, $line->field_key, esc_url_raw($file['url']));
                                    }
                                }

                                remove_filter('upload_mimes', 'MeprUsersHelper::get_allowed_mime_types');
                                remove_filter('upload_dir', 'MeprUsersHelper::get_upload_dir');
                            }
                        }
                    } elseif ($line->field_type == 'checkbox') {
                        update_user_meta($user_id, $line->field_key, false);
                    } elseif (in_array($line->field_type, ['checkboxes', 'multiselect'])) {
                        update_user_meta($user_id, $line->field_key, []);
                    } else {
                        update_user_meta($user_id, $line->field_key, '');
                    }
                }
            }

            if (!$is_signup) {
                MeprEvent::record('member-account-updated', $user);
            }

            MeprHooks::do_action('mepr_user_account_saved', $user);

            return true;
        }

        return false;
    }

    /**
     * Validate extra profile fields for a user.
     * Should be moved to the Model eventually
     * This should be run before MeprUsersCtrl::save_extra_profile_fields is run
     *
     * @param  WP_Error|null  $errors    WP_Error object to add errors to.
     * @param  boolean|null   $update    Whether this is an update or a new user.
     * @param  WP_User|null   $user      The user object.
     * @param  boolean        $is_signup Whether this is during signup.
     * @param  object|boolean $product   The product object.
     * @param  array          $selected  The selected fields to validate.
     * @return array                     Array of validation errors.
     */
    public static function validate_extra_profile_fields(
        $errors = null,
        $update = null,
        $user = null,
        $is_signup = false,
        $product = false,
        $selected = []
    ) {
        $mepr_options = MeprOptions::fetch();
        $errs         = [];

        // Prevent checking when adding a new user via WP's New User system
        // or if an admin is editing the profile in the dashboard.
        if ($update === false || ($update !== false && MeprUtils::is_mepr_admin() && is_admin())) {
            return $errs;
        }

        // Get the right custom fields.
        if ($is_signup && $product !== false) {
            if ($product->customize_profile_fields) {
                $custom_fields = $product->custom_profile_fields();
            } else {
                $custom_fields = $mepr_options->custom_fields;
            }
        } elseif (!is_null($user)) {
            $mepr_user     = new MeprUser($user->ID);
            $custom_fields = $mepr_user->custom_profile_fields();
        } else {
            $custom_fields = $mepr_options->custom_fields;
        }

        // If the address line is set in POST then we should validate it.
        if (isset($_POST['mepr-address-one'])) {
            $custom_fields = array_merge($mepr_options->address_fields, $custom_fields);
        }

        foreach ($custom_fields as $line) {
            // Allows fields to be selectively validated.
            if (! empty($selected) && ! in_array($line->field_key, $selected)) {
                continue;
            }

            // If we're processing a signup and the custom field is not set
            // to show on signup we need to make sure it isn't required.
            if ($is_signup && $line->required && !$line->show_on_signup) {
                $line->required = false;
            } elseif (!$is_signup && !is_admin() && isset($line->show_in_account) && !$line->show_in_account) {
                // Account page shouldn't show errors if the fields have been hidden from the account page.
                $line->required = false;
            }

            if ((!isset($_POST[$line->field_key]) || (empty($_POST[$line->field_key]) && $_POST[$line->field_key] != '0')) && $line->required && 'file' != $line->field_type) {
                $errs[$line->field_key] = sprintf(
                    // Translators: %s: field name.
                    __('%s is required.', 'memberpress'),
                    stripslashes($line->field_name)
                );

                // This allows us to run this on dashboard profile fields as well as front end.
                if (is_object($errors)) {
                    $errors->add($line->field_key, sprintf(
                        // Translators: %s: field name.
                        __('%s is required.', 'memberpress'),
                        stripslashes($line->field_name)
                    ));
                }
            }

            if ('file' == $line->field_type) {
                $file_provided = isset($_FILES[$line->field_key]['error']) && $_FILES[$line->field_key]['error'] != UPLOAD_ERR_NO_FILE;

                if ($file_provided) {
                    // Validate new file upload.
                    $file = $_FILES[$line->field_key];

                    if (empty($file['tmp_name']) || empty($file['name']) || empty($file['size'])) {
                        if ($line->required) {
                            $errs[$line->field_key] = sprintf(
                                // Translators: %s: field name.
                                __('%s is required.', 'memberpress'),
                                stripslashes($line->field_name)
                            );
                        }
                    } elseif ($file['error'] == UPLOAD_ERR_OK) {
                        add_filter('upload_mimes', 'MeprUsersHelper::get_allowed_mime_types');
                        $wp_filetype = wp_check_filetype($file['name']);
                        remove_filter('upload_mimes', 'MeprUsersHelper::get_allowed_mime_types');

                        if (!$wp_filetype['ext'] && !current_user_can('unfiltered_upload')) {
                            $errs[$line->field_key] = sprintf(
                                // Translators: %s: field name.
                                __('%s file type not allowed.', 'memberpress'),
                                stripslashes($line->field_name)
                            );
                        }
                    } else {
                        $errs[$line->field_key] = sprintf(
                            // Translators: %s: field name.
                            __('%s could not be uploaded.', 'memberpress'),
                            stripslashes($line->field_name)
                        );
                    }
                } else {
                    // Validate existing file.
                    if ($line->required) {
                        $file = get_user_meta(get_current_user_id(), $line->field_key, true);

                        if (empty($file)) {
                              $errs[$line->field_key] = sprintf(
                                // Translators: %s: field name.
                                  __('%s is required.', 'memberpress'),
                                  stripslashes($line->field_name)
                              );
                        }
                    }
                }
            }

            if ($line->required && 'email' == $line->field_type && !empty($_POST[$line->field_key])) {
                if (!is_email(stripcslashes($_POST[$line->field_key]))) {
                    $errs[$line->field_key] = sprintf(
                        // Translators: %s: field name.
                        __('%s is not a valid email address.', 'memberpress'),
                        stripslashes($line->field_name)
                    );
                }
            }

            if ($line->required && 'url' == $line->field_type && !empty($_POST[$line->field_key])) {
                if (!preg_match('/(https?:\/\/)?(www\.)?[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#()?&\/\/=]*)/', $_POST[$line->field_key])) {
                    $errs[$line->field_key] = sprintf(
                        // Translators: %s: field name.
                        __('%s is not a valid URL.', 'memberpress'),
                        stripslashes($line->field_name)
                    );
                }
            }

            if ('date' == $line->field_type && !empty($_POST[$line->field_key])) {
                if (!MeprUtils::is_date($_POST[$line->field_key])) {
                    $errs[$line->field_key] = sprintf(
                        // Translators: %s: field name.
                        __('%s is not a valid date.', 'memberpress'),
                        stripslashes($line->field_name)
                    );
                }
            }
        }

        return $errs;
    }

    /**
     * Search for users via AJAX.
     *
     * @return void
     */
    public static function user_search()
    {
        if (!MeprUtils::is_mepr_admin()) {
            die('-1');
        }

        // The jQuery suggest plugin has already trimmed and escaped user input (\ becomes \\)
        // so we just need to sanitize the username.
        $s = sanitize_user($_GET['q']);

        if (strlen($s) < 2) {
            die; // Require 2 characters for matching.
        }

        $users = get_users(['search' => "*$s*"]);

        MeprView::render('/admin/users/search', get_defined_vars());
        die();
    }

    /**
     * Add extra columns to the WordPress Users list table.
     *
     * @param  array $columns The existing columns.
     * @return array          The modified columns.
     */
    public static function add_extra_user_columns($columns)
    {
        $columns['mepr_products']   = __('Active Memberships', 'memberpress');
        $columns['mepr_registered'] = __('Registered', 'memberpress');
        $columns['mepr_last_login'] = __('Last Login', 'memberpress');
        $columns['mepr_num_logins'] = __('# Logins', 'memberpress');

        return $columns;
    }

    /**
     * Define which columns should be sortable in the Users list table.
     *
     * @param  array $cols The sortable columns.
     * @return array       The modified sortable columns.
     */
    public static function sortable_extra_user_columns($cols)
    {
        $cols['mepr_registered'] = 'user_registered';
        $cols['mepr_last_login'] = 'last_login';
        $cols['mepr_num_logins'] = 'num_logins';

        return $cols;
    }

    /**
     * Modify the user query to enable sorting by custom columns.
     *
     * @param  WP_User_Query $query The user query object.
     * @return void
     */
    public static function extra_user_columns_query_override($query)
    {
        global $wpdb;
        $vars    = $query->query_vars;
        $mepr_db = new MeprDb();

        if (isset($vars['orderby']) && $vars['orderby'] == 'last_login') {
            $query->query_fields .= ", (SELECT e.created_at FROM {$mepr_db->events} AS e WHERE {$wpdb->users}.ID = e.evt_id AND e.evt_id_type='" . MeprEvent::$users_str . "' AND e.event = '" . MeprEvent::$login_event_str . "' ORDER BY e.created_at DESC LIMIT 1) AS last_login";
            $query->query_orderby = "ORDER BY last_login {$vars['order']}";
        }

        if (isset($vars['orderby']) && $vars['orderby'] == 'num_logins') {
            $query->query_fields .= ", (SELECT count(*) FROM {$mepr_db->events} AS e WHERE {$wpdb->users}.ID = e.evt_id AND e.evt_id_type='" . MeprEvent::$users_str . "' AND e.event = '" . MeprEvent::$login_event_str . "') AS num_logins";
            $query->query_orderby = "ORDER BY num_logins {$vars['order']}";
        }
    }

    /**
     * Display the content for the custom columns in the Users list table.
     *
     * @param  string  $value       The column value.
     * @param  string  $column_name The name of the column.
     * @param  integer $user_id     The user ID.
     * @return string               The column content.
     */
    public static function manage_extra_user_columns($value, $column_name, $user_id)
    {
        $user = new MeprUser($user_id);

        if ($column_name == 'mepr_registered') {
            $registered = $user->user_registered;
            return MeprAppHelper::format_date($registered, __('Unknown', 'memberpress'), 'M j, Y') . '<br/>' . MeprAppHelper::format_date($registered, __('Unknown', 'memberpress'), 'g:i A');
        }

        if ($column_name == 'mepr_products') {
            $titles = $user->get_active_subscription_titles('<br/>');

            if (!empty($titles)) {
                return $titles;
            } else {
                return __('None', 'memberpress');
            }
        }

        if ($column_name == 'mepr_last_login') {
            $login = $user->get_last_login_data();

            if (!empty($login)) {
                return MeprAppHelper::format_date($login->created_at, __('Never', 'memberpress'), 'M j, Y') . '<br/>' . MeprAppHelper::format_date($login->created_at, __('Never', 'memberpress'), 'g:i A');
            } else {
                return __('Never', 'memberpress');
            }
        }

        if ($column_name == 'mepr_num_logins') {
            return (int)$user->get_num_logins();
        }

        return $value;
    }

    /**
     * Redirect members from the admin area if necessary.
     *
     * @return void
     */
    public static function maybe_redirect_member_from_admin()
    {
        $mepr_options = MeprOptions::fetch();

        // Don't mess up AJAX requests.
        if (defined('DOING_AJAX')) {
            return;
        }

        // Don't mess up admin_post.php requests.
        if (strpos($_SERVER['REQUEST_URI'], 'admin-post.php') !== false && isset($_REQUEST['action'])) {
            return;
        }

        if ($mepr_options->lock_wp_admin && !current_user_can('delete_posts')) {
            if (isset($mepr_options->login_redirect_url) && !empty($mepr_options->login_redirect_url)) {
                MeprUtils::wp_redirect($mepr_options->login_redirect_url);
            } else {
                MeprUtils::wp_redirect(home_url());
            }
        }
    }

    /**
     * Disable the WordPress registration form if necessary.
     *
     * @param  string   $login  The login name.
     * @param  string   $email  The email address.
     * @param  WP_Error $errors The WP_Error object.
     * @return void
     */
    public static function maybe_disable_wp_registration_form($login, $email, $errors)
    {
        $mepr_options = MeprOptions::fetch();

        if ($mepr_options->disable_wp_registration_form) {
            $message = __('You cannot register with this form. Please use the registration page found on the website instead.', 'memberpress');
            $errors->add('mepr_disabled_error', $message);
        }
    }

    /**
     * Disable the WordPress admin bar if necessary.
     *
     * @return void
     */
    public static function maybe_disable_admin_bar()
    {
        $mepr_options = MeprOptions::fetch();

        if (!current_user_can('delete_posts') && $mepr_options->disable_wp_admin_bar) {
            show_admin_bar(false);
        }
    }

    /**
     * Add a meta box to the login page.
     *
     * @return void
     */
    public static function login_page_meta_box()
    {
        global $post;

        $mepr_options = MeprOptions::fetch();

        if (isset($post) && $post instanceof WP_Post && $post->ID == $mepr_options->login_page_id) {
            add_meta_box('mepr_login_page_meta_box', __('MemberPress Settings', 'memberpress'), 'MeprUsersCtrl::show_login_page_meta_box', 'page', 'normal', 'high');
        }
    }

    /**
     * Show the login page meta box.
     *
     * @return void
     */
    public static function show_login_page_meta_box()
    {
        global $post;

        $mepr_options = MeprOptions::fetch();

        if (isset($post) && $post->ID) {
            $manual_login_form = get_post_meta($post->ID, '_mepr_manual_login_form', true);

            MeprView::render('/admin/users/login_page_meta_box', get_defined_vars());
        }
    }

    /**
     * Save post data for the login page.
     *
     * @param  integer $post_id The post ID.
     * @return integer|void
     */
    public static function save_postdata($post_id)
    {
        $post         = get_post($post_id);
        $mepr_options = MeprOptions::fetch();

        if (!wp_verify_nonce((isset($_POST[MeprUser::$nonce_str])) ? $_POST[MeprUser::$nonce_str] : '', MeprUser::$nonce_str . wp_salt())) {
            return $post_id; // Nonce prevents meta data from being wiped on move to trash.
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        if (defined('DOING_AJAX')) {
            return;
        }

        if (!empty($post) && $post->ID == $mepr_options->login_page_id) {
            $manual_login_form = (isset($_POST['_mepr_manual_login_form']));
            update_post_meta($post->ID, '_mepr_manual_login_form', $manual_login_form);
        }
    }

    /**
     * List a user's subscriptions.
     *
     * @param  array  $atts    The shortcode attributes.
     * @param  string $content The content of the shortcode.
     * @return string
     */
    public static function list_users_subscriptions($atts, $content = '')
    {
        $user          = MeprUtils::get_currentuserinfo();
        $active_rows   = [];
        $inactive_rows = [];
        $alt_row       = 'mp_users_subscriptions_list_alt';

        if (!$user) {
            return '';
        }

        $status = (isset($atts['status'])) ? $atts['status'] : 'all';

        $all_ids    = $user->current_and_prior_subscriptions(); // Returns an array of Product ID's the user has ever been subscribed to.
        $active_ids = $user->active_product_subscriptions('ids');

        foreach ($all_ids as $id) {
            $prd        = new MeprProduct($id);
            $created_at = MeprUser::get_user_product_signup_date($user->ID, $id);

            if (in_array($id, $active_ids) && $status !== 'expired') {
                $expiring_txn = MeprUser::get_user_product_expires_at_date($user->ID, $id, true);
                $renewal_link = '';
                $expires_at   = _x('Unknown', 'ui', 'memberpress');

                if ($expiring_txn instanceof MeprTransaction) {
                    $renewal_link = MeprHooks::apply_filters('mepr_list_subscriptions_renewal_link', $user->renewal_link($expiring_txn->id), $expiring_txn);
                    $expires_at   = MeprAppHelper::format_date($expiring_txn->expires_at, _x('Never', 'ui', 'memberpress'));
                }

                $active_rows[] = (object)[
                    'membership'   => $prd->post_title,
                    'expires'      => $expires_at,
                    'renewal_link' => $renewal_link,
                    'access_url'   => $prd->access_url,
                    'created_at'   => $created_at,
                ];
            } elseif (!in_array($id, $active_ids) && in_array($status, ['expired', 'all'])) {
                $inactive_rows[] = (object)[
                    'membership'    => $prd->post_title,
                    'purchase_link' => $prd->url(),
                    'created_at'    => $created_at,
                ];
            }
        }

        // Sorting active subs.
        if (!empty($active_rows) && isset($atts['orderby']) && in_array($atts['orderby'], ['date', 'title'])) {
            if ($atts['orderby'] == 'date') {
                if ($atts['order'] == 'asc') {
                    usort($active_rows, function ($a, $b) {
                        return $a->created_at <=> $b->created_at;
                    });
                } else {
                    usort($active_rows, function ($a, $b) {
                        return $b->created_at <=> $a->created_at;
                    });
                }
            }
            if ($atts['orderby'] == 'title') {
                if ($atts['order'] == 'desc') {
                    usort($active_rows, function ($a, $b) {
                        return $b->membership <=> $a->membership;
                    });
                } else {
                    usort($active_rows, function ($a, $b) {
                        return $a->membership <=> $b->membership;
                    });
                }
            }
        }

        // Sorting inactive subs.
        if (!empty($inactive_rows) && isset($atts['orderby']) && in_array($atts['orderby'], ['date', 'title'])) {
            if ($atts['orderby'] == 'date') {
                if ($atts['order'] == 'asc') {
                    usort($inactive_rows, function ($a, $b) {
                        return $a->created_at <=> $b->created_at;
                    });
                } else {
                    usort($inactive_rows, function ($a, $b) {
                        return $b->created_at <=> $a->created_at;
                    });
                }
            }
            if ($atts['orderby'] == 'title') {
                if ($atts['order'] == 'desc') {
                    usort($inactive_rows, function ($a, $b) {
                        return $b->membership <=> $a->membership;
                    });
                } else {
                    usort($inactive_rows, function ($a, $b) {
                        return $a->membership <=> $b->membership;
                    });
                }
            }
        }

        ob_start();
        MeprView::render('/shortcodes/list_users_subscriptions', get_defined_vars());
        return ob_get_clean();
    }

    /**
     * Adds shortcode for displaying user files
     *
     * @param  mixed $atts    The shortcode attributes.
     * @param  mixed $content The content of the shortcode.
     * @return mixed
     */
    public static function show_user_file($atts, $content = '')
    {
        $key    = (isset($atts['slug'])) ? sanitize_text_field($atts['slug']) : '';
        $userid = (isset($atts['userid'])) ? intval($atts['userid']) : get_current_user_id();

        $mepr_options  = MeprOptions::fetch();
        $custom_fields = (array) $mepr_options->custom_fields;

        $field = array_filter($custom_fields, function ($field) use ($key) {
            return $field->field_key === $key && $field->field_type === 'file';
        });

        if (empty($field)) {
            return;
        }

        $download = get_user_meta($userid, $key, true);

        if (false === MeprUsersHelper::uploaded_file_exists($download)) {
            return;
        }

        ob_start();
        MeprView::render('/shortcodes/user_files', get_defined_vars());
        return ob_get_clean();
    }

    /**
     * Get the active membership titles for a user.
     *
     * @param  array  $atts    The shortcode attributes.
     * @param  string $content The content of the shortcode.
     * @return string|void
     */
    public static function get_user_active_membership_titles($atts, $content = '')
    {
        $userid = (isset($atts['userid']) && !empty($atts['userid'])) ? (int)trim($atts['userid']) : get_current_user_id();

        if (!$userid) {
            return;
        }

        $user    = new MeprUser($userid);
        $message = (isset($atts['message']) && !empty($atts['message'])) ? wp_kses_post($atts['message']) : '';
        $titles  = esc_attr(trim($user->get_active_subscription_titles()));

        return ('' != $titles) ? $titles : $message;
    }
}
