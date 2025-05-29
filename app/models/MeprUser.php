<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprUser extends MeprBaseModel
{
    /**
     * Database field name for user ID
     *
     * @var string
     */
    public static $id_str           = 'ID';

    /**
     * Database field name for user first name
     *
     * @var string
     */
    public static $first_name_str   = 'first_name';

    /**
     * Database field name for user last name
     *
     * @var string
     */
    public static $last_name_str    = 'last_name';

    /**
     * Database field name for username
     *
     * @var string
     */
    public static $username_str     = 'user_login';

    /**
     * Database field name for user email
     *
     * @var string
     */
    public static $email_str        = 'user_email';

    /**
     * Database field name for user password
     *
     * @var string
     */
    public static $password_str     = 'user_pass';

    /**
     * Database field name for user message
     *
     * @var string
     */
    public static $user_message_str = 'mepr_user_message';

    /**
     * Database field name for user UUID
     *
     * @var string
     */
    public static $uuid_str         = 'uuid';

    /**
     * Nonce field name for user operations
     *
     * @var string
     */
    public static $nonce_str        = 'mepr_users_nonce';

    /**
     * Field used to prevent welcome notification from sending multiple times
     *
     * @var string
     */
    public static $signup_notice_sent_str = 'signup_notice_sent';

    /**
     * Constructor for the MeprUser class.
     *
     * @param integer|null $id The ID of the user to load.
     */
    public function __construct($id = null)
    {
        $this->attrs = [];
        $this->initialize_new_user(); // A bit redundant I know - But this prevents a nasty error when Standards = STRICT in PHP.
        $this->load_user_data_by_id($id);
    }

    /**
     * Get all users.
     *
     * @param  string $type     The type of data to return ('objects' or 'ids').
     * @param  array  $args     The arguments for filtering users.
     * @param  string $order_by The order by clause.
     * @param  string $limit    The limit clause.
     * @return array
     */
    public static function all($type = 'objects', $args = [], $order_by = '', $limit = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        if ($type == 'objects') {
            $records = $mepr_db->get_records($wpdb->users, $args, $order_by, $limit);

            $users = [];
            foreach ($records as $record) {
                $users[] = new MeprUser($record->ID);
            }
        } elseif ($type == 'ids') {
            $users = $mepr_db->get_col($wpdb->users, 'id', $args, $order_by, $limit);
        }

        return $users;
    }

    /**
     * Validate the user's properties.
     *
     * @return void
     */
    public function validate()
    {
        $this->validate_is_email($this->user_email, 'user_email');
        $this->validate_not_empty($this->user_login, 'user_login');
    }

    /**
     * Load user data by ID.
     *
     * @param  integer|null $id The ID of the user.
     * @return void
     */
    public function load_user_data_by_id($id = null)
    {
        if (empty($id) or !is_numeric($id)) {
            $this->initialize_new_user();
        } else {
            $wp_user_obj = MeprUtils::get_user_by('id', $id);
            if ($wp_user_obj instanceof WP_User) {
                $this->load_wp_user($wp_user_obj);
                $this->load_meta();
            } else {
                $this->initialize_new_user();
            }
        }

        // This must be here to ensure that we don't pull an encrypted
        // password, encrypt it a second time and store it.
        unset($this->user_pass);
    }

    /**
     * Load user data by login.
     *
     * @param  string|null $login The login of the user.
     * @return void
     */
    public function load_user_data_by_login($login = null)
    {
        if (empty($login)) {
            $this->initialize_new_user();
        } else {
            $wp_user_obj = MeprUtils::get_user_by('login', $login);
            if ($wp_user_obj instanceof WP_User) {
                $this->load_wp_user($wp_user_obj);
                $this->load_meta($wp_user_obj);
            } else {
                $this->initialize_new_user();
            }
        }

        // This must be here to ensure that we don't pull an encrypted
        // password, encrypt it a second time and store it.
        unset($this->user_pass);
    }

    /**
     * Load user data by email.
     *
     * @param  string|null $email The email of the user.
     * @return void
     */
    public function load_user_data_by_email($email = null)
    {
        if (empty($email)) {
            $this->initialize_new_user();
        } else {
            $wp_user_obj = MeprUtils::get_user_by('email', $email);
            if ($wp_user_obj instanceof WP_User) {
                $this->load_wp_user($wp_user_obj);
                $this->load_meta($wp_user_obj);
            } else {
                $this->initialize_new_user();
            }
        }

        // This must be here to ensure that we don't pull an encrypted
        // password, encrypt it a second time and store it.
        unset($this->user_pass);
    }

    /**
     * Load user data by UUID.
     *
     * @param  string|null $uuid The UUID of the user.
     * @return boolean|void
     */
    public function load_user_data_by_uuid($uuid = null)
    {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1";
        $query = $wpdb->prepare($query, self::$uuid_str, $uuid);
        $row   = $wpdb->get_row($query);

        if ($row and isset($row->user_id) and is_numeric($row->user_id)) {
            return $this->load_user_data_by_id($row->user_id);
        } else {
            return false;
        }
    }

    /**
     * Initialize a new user.
     *
     * @return object
     */
    protected function initialize_new_user()
    {
        if (!isset($this->attrs) or !is_array($this->attrs)) {
            $this->attrs = [];
        }

        $u = [
            'ID'                  => null,
            'first_name'          => null,
            'last_name'           => null,
            'user_login'          => null,
            'user_nicename'       => null,
            'user_email'          => null,
            'user_url'            => null,
            'user_pass'           => null,
            'user_message'        => null,
            'user_registered'     => null,
            'user_activation_key' => null,
            'user_status'         => null,
            'signup_notice_sent'  => null,
            'display_name'        => null,
        ];

        // Initialize user_meta variables.
        foreach ($this->attrs as $var) {
            $u[$var] = null;
        }

        $this->rec = (object)$u;

        return $this->rec;
    }

    /**
     * Load WordPress user data.
     *
     * @param  WP_User $wp_user_obj The WordPress user object.
     * @return void
     */
    public function load_wp_user($wp_user_obj)
    {
        $this->rec->ID                  = $wp_user_obj->ID;
        $this->rec->user_login          = $wp_user_obj->user_login;
        $this->rec->user_nicename       = (isset($wp_user_obj->user_nicename)) ? $wp_user_obj->user_nicename : '';
        $this->rec->user_email          = $wp_user_obj->user_email;
        $this->rec->user_url            = (isset($wp_user_obj->user_url)) ? $wp_user_obj->user_url : '';
        $this->rec->user_pass           = $wp_user_obj->user_pass;
        $this->rec->user_message        = stripslashes($wp_user_obj->user_message);
        $this->rec->user_registered     = $wp_user_obj->user_registered;
        $this->rec->user_activation_key = (isset($wp_user_obj->user_activation_key)) ? $wp_user_obj->user_activation_key : '';
        $this->rec->user_status         = (isset($wp_user_obj->user_status)) ? $wp_user_obj->user_status : '';
        // We don't need this, and as of WP 3.9 -- this causes wp_update_user() to wipe users role/caps!!!
        // $this->rec->role = (isset($wp_user_obj->role))?$wp_user_obj->role:'';.
        $this->rec->display_name = (isset($wp_user_obj->display_name)) ? $wp_user_obj->display_name : '';
    }

    /**
     * Load user meta data.
     *
     * @return void
     */
    public function load_meta()
    {
        $this->rec->first_name         = get_user_meta($this->ID, self::$first_name_str, true);
        $this->rec->last_name          = get_user_meta($this->ID, self::$last_name_str, true);
        $this->rec->signup_notice_sent = get_user_meta($this->ID, self::$signup_notice_sent_str, true);
        $this->rec->user_pass          = get_user_meta($this->ID, self::$password_str, true);
        $this->rec->user_message       = get_user_meta($this->ID, self::$user_message_str, true);
        $this->rec->uuid               = $this->load_uuid();
    }

    /**
     * Retrieve or generate the UUID.
     *
     * @param  boolean $force Whether to force generation of a new UUID.
     * @return string
     */
    public function load_uuid($force = false)
    {
        $uuid = get_user_meta($this->ID, self::$uuid_str, true);

        if ($force or empty($uuid)) {
            $uuid = md5(base64_encode(uniqid()));
            update_user_meta($this->ID, self::$uuid_str, $uuid);
        }

        return $uuid;
    }

    /**
     * Check if the user is active.
     *
     * @return boolean
     */
    public function is_active()
    {
        $subscriptions = $this->active_product_subscriptions('ids', true);
        return !empty($subscriptions);
    }

    /**
     * Check if the user has expired.
     *
     * @return boolean
     */
    public function has_expired()
    {
        $subscriptions = $this->active_product_subscriptions('ids', true, false);
        return !empty($subscriptions);
    }

    /**
     * Check if the user is already subscribed to a membership.
     *
     * @param  integer $product_id The product ID.
     * @return boolean
     */
    public function is_already_subscribed_to($product_id)
    {
        $active_subs = $this->active_product_subscriptions('ids', true);
        return in_array($product_id, $active_subs);
    }

    /**
     * Check if the user is active on a membership.
     *
     * @param  mixed $obj The object to check.
     * @return boolean
     */
    public function is_active_on_membership($obj)
    {
        $id = 0;

        if ($obj instanceof MeprProduct) {
            $id = $obj->ID;
        } elseif ($obj instanceof MeprTransaction || $obj instanceof MeprSubscription) {
            $id = $obj->product_id;
        } elseif (is_numeric($obj) && $obj > 0) {
            $id = $obj;
        } else {
            return false;
        }

        return $this->is_already_subscribed_to($id);
    }

    /**
     * Check if a condition exists for the user or the user's memberships.
     *
     * @param  integer $rule_id The rule ID.
     * @return boolean
     */
    public function has_access_from_rule($rule_id)
    {
        global $wpdb;
        $mepr_db = new MeprDb();
        $rule    = new MeprRule($rule_id);
        $user    = new \WP_User($this->ID);

        $where_clause         = $wpdb->prepare(
            "
      WHERE rule_id=%d
      AND ((access_type='member' AND access_operator='is' AND access_condition=%s)
      ",
            $rule_id,
            $this->user_login
        );
        $active_subscriptions = $this->active_product_subscriptions();
        if (!empty($active_subscriptions)) {
            $where_clause .= $wpdb->prepare(" OR (access_type='membership' AND access_operator='is' AND access_condition IN (" . implode(',', array_fill(0, count($active_subscriptions), '%d')) . '))', $active_subscriptions);
        }
        $where_clause .= ')';

        $query = "
      SELECT 1
      FROM {$mepr_db->rule_access_conditions}
      {$where_clause}
      LIMIT 1
    ";

        // Check for role or capability.
        $has_role_or_cap = false;
        foreach ($rule->access_conditions() as $condition) {
            if ('role' === $condition->access_type) {
                if (in_array($condition->access_condition, (array) $user->roles)) {
                    $has_role_or_cap = true;
                    break;
                }
            } elseif ('capability' === $condition->access_type && $user->has_cap($condition->access_condition)) {
                $has_role_or_cap = true;
                break;
            }
        }

        $user_has_access = (1 === $wpdb->query($query)) || $has_role_or_cap;

        return MeprHooks::apply_filters('mepr_user_has_access_from_rule', $user_has_access, $this, $rule_id);
    }

    /**
     * Retrieve the current subscription within a group (with upgrade paths enabled).
     *
     * @param  integer|MeprGroup $group_id        The group ID or object.
     * @param  boolean           $look_for_lapsed Whether to look for lapsed subscriptions.
     * @param  boolean           $omit_id         Whether to omit a specific ID.
     * @return MeprSubscription|false
     */
    public function subscription_in_group($group_id, $look_for_lapsed = false, $omit_id = false)
    {
        if ($group_id instanceof MeprGroup && isset($group_id->ID) && $group_id->ID) {
            $group_id = $group_id->ID;
        }

        $subs = MeprSubscription::get_all_active_by_user_id($this->ID, 'sub.id DESC', '', false, $look_for_lapsed);

        if (empty($subs)) {
            return false;
        }

        foreach ($subs as $sub_data) {
            // Do not return an ID that should be omitted from the results.
            if ($omit_id && $omit_id == $sub_data->id) {
                continue;
            }

            $sub = new MeprSubscription($sub_data->id);
            $prd = $sub->product();
            if ($prd->group_id == $group_id) {
                return $sub;
            }
        }

        return false;
    }

    /**
     * Retrieve the lifetime subscription within a group.
     *
     * @param  integer|MeprGroup $group_id          The group ID or object.
     * @param  array             $exclude_txn_types The transaction types to exclude.
     * @return MeprTransaction|false
     */
    public function lifetime_subscription_in_group($group_id, $exclude_txn_types = [])
    {
        if ($group_id instanceof MeprGroup && isset($group_id->ID) && $group_id->ID) {
            $group_id = $group_id->ID;
        }

        $txns = $this->active_product_subscriptions('transactions');

        if (empty($txns)) {
            return false;
        }

        // Let's get the one with the lowest ID to prevent getting our most recent signup.
        $lowest_id_txn = false;

        foreach ($txns as $txn) {
            // Skip if the transaction type is in the exclude list.
            if (!empty($exclude_txn_types) && in_array($txn->txn_type, $exclude_txn_types)) {
                continue;
            }

            $p = $txn->product();

            if ((int)$txn->subscription_id == 0 && $p->group_id == $group_id) {
                if ($lowest_id_txn === false || $txn->id < $lowest_id_txn->id) {
                    $lowest_id_txn = $txn;
                }
            }
        }

        return $lowest_id_txn;
    }

    /**
     * Check if the user is logged in and is the current user.
     *
     * @return boolean
     */
    public function is_logged_in_and_current_user()
    {
        return MeprUtils::is_logged_in_and_current_user($this->ID);
    }

    /**
     * Check if the user is logged in.
     *
     * @return boolean
     */
    public function is_logged_in()
    {
        return MeprUtils::is_logged_in($this->ID);
    }

    /**
     * Get active product subscriptions.
     *
     * @param  string  $return_type     The return type ('ids', 'products', 'transactions').
     * @param  boolean $force           Whether to force the query.
     * @param  boolean $exclude_expired Whether to exclude expired subscriptions.
     * @return array
     */
    public function active_product_subscriptions($return_type = 'ids', $force = false, $exclude_expired = true)
    {
        static $items; // Prevents a butt load of queries on the front end.

        if (empty($this->ID)) {
            return [];
        }

        $user_id = $this->ID;

        // Setup caching array.
        if (!isset($items) || !is_array($items)) {
            $items = [];
        }

        // Setup caching array for this user.
        if (!isset($items[$user_id]) || !is_array($items[$user_id])) {
            $items[$user_id] = [];
        }

        // I'm assuming we may run into instances where we need to force the query to run
        // so $force should allow that.
        if ($force || !isset($items[$user_id][$return_type]) || !is_array($items[$user_id][$return_type])) {
            $txns = MeprTransaction::get_all_complete_by_user_id(
                $user_id, // The user_id.
                'product_id, created_at DESC', // The order_by.
                '', // The limit.
                false, // The count.
                $exclude_expired, // The exclude_expired.
                true, // The include_confirmations.
                true // Allow custom where clause override.
            );

            $result = [];
            $ids    = [];

            foreach ($txns as $txn) {
                if ($return_type == 'ids' && ! in_array($txn->product_id, $ids)) {
                    $result[] = $txn->product_id;
                } elseif (($return_type == 'products' || $return_type === true) && ! in_array($txn->product_id, $ids)) {
                    $result[] = new MeprProduct($txn->product_id);
                } elseif ($return_type == 'transactions') {
                    $result[] = new MeprTransaction($txn->id);
                }
                $ids[] = $txn->product_id;
            }

            // Do not static cache result if $exclude_expired is false.
            if (!$exclude_expired) {
                return $result;
            } else {
                $items[$user_id][$return_type] = $result;
            }
        }

        return apply_filters('mepr-user-active-product-subscriptions', $items[$user_id][$return_type], $user_id, $return_type);
    }

    /**
     * Get active subscription titles.
     *
     * @param  string $sep The separator for titles.
     * @return string
     */
    public function get_active_subscription_titles($sep = ', ')
    {
        $formatted_titles = '';
        $res              = $this->active_product_subscriptions();

        if (!empty($res)) {
            // Do not list the same name multiple times.
            $products = array_values(array_unique($res));
            $titles   = [];

            for ($i = 0; $i < count($products); $i++) {
                $titles[] = get_the_title($products[$i]);
            }

            sort($titles);

            $formatted_titles = implode($sep, $titles);
        }

        return $formatted_titles;
    }

    /**
     * Gets the product_id's of the subscriptions this user has which are marked as "Enabled".
     * This does NOT mean they are active, just that they are recurring and marked as "Enabled".
     *
     * @param  integer|null $prd_id The product ID.
     * @return array
     */
    public function get_enabled_product_ids($prd_id = null)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $q = $wpdb->prepare("SELECT product_id FROM {$mepr_db->subscriptions} WHERE status = %s AND user_id = %d", MeprSubscription::$active_str, $this->ID);

        if (isset($prd_id) && $prd_id) {
            $q .= ' ' . $wpdb->prepare('AND product_id = %d', $prd_id);
        }

        $res = $wpdb->get_col($q);

        return array_unique($res);
    }

    /**
     * Check if a user can purchase a product.
     *
     * @param  object  $who         Should be 1 (row) object in the $product->who_can_purchase array.
     * @param  integer $curr_prd_id The current product ID.
     * @return boolean
     */
    public function can_user_purchase($who, $curr_prd_id = 0)
    {
        $current_subscriptions = $this->active_product_subscriptions('ids');
        $all_subscriptions     = $this->active_product_subscriptions('ids', true, false); // We need to force here, and we do not want to exclude expired.
        $expired_subscriptions = array_diff($all_subscriptions, $current_subscriptions); // Return values from $all_subscriptions which are NOT also present in $current_subscriptions.

        if (isset($who->purchase_type) && $who->purchase_type === 'had') {
            // User is previously subscribed to anything.
            if ($who->product_id == 'anything') {
                return ! empty($expired_subscriptions);
            }

            // Now let's check if the actual membership ID is in the user's past subscriptions or not.
            return in_array($who->product_id, $expired_subscriptions);
        }

        // User is not currently subscribed to something.
        if ($who->product_id == 'nothing') {
            return empty($current_subscriptions);
        }

        // User is currently subscribed to anything.
        if ($who->product_id == 'anything') {
            return !empty($current_subscriptions);
        }

        // User has previously purchased this membership level.
        if ($who->product_id == 'subscribed-before') {
            return in_array($curr_prd_id, $all_subscriptions);
        }

        // User has NOT previously purchased this membership level before.
        if ($who->product_id == 'not-subscribed-before') {
            return !in_array($curr_prd_id, $all_subscriptions);
        }

        // User has NOT subscribed to any membership before.
        if ($who->product_id == 'not-subscribed-any-before') {
            return empty($all_subscriptions);
        }

        // Now let's check if the actual membership ID is in the user's active subscriptions or not.
        return in_array($who->product_id, $current_subscriptions);
    }

    /**
     * Get the fallback transaction for a product.
     *
     * @param  integer $product_id The product ID.
     * @return MeprTransaction|false
     */
    public function fallback_txn($product_id)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $query = $wpdb->prepare(
            "
      SELECT id FROM {$mepr_db->transactions}
      WHERE user_id = %d
        AND product_id = %d
        AND gateway = %s
        AND expires_at = '0000-00-00 00:00:00'
      ORDER BY id DESC
      LIMIT 1
      ",
            $this->ID,
            $product_id,
            MeprTransaction::$fallback_gateway_str
        );

        $result =  $wpdb->get_var($query);
        if ($result) {
            return new MeprTransaction($result);
        } else {
            return false;
        }
    }

    /**
     * Get the full name of the user.
     *
     * @return string
     */
    public function get_full_name()
    {
        return $this->full_name();
    }

    /**
     * Get the full name of the user.
     *
     * @return string
     */
    public function full_name()
    {
        $name = '';

        if (!empty($this->first_name)) {
            $name = $this->first_name;
        }

        if (!empty($this->last_name)) {
            if (empty($name)) {
                $name = $this->last_name;
            } else {
                $name .= " {$this->last_name}";
            }
        }

        if (empty(trim($name))) {
            $name = $this->display_name;
        }

        return $name;
    }

    /**
     * Get the registration date of the current user.
     * Make sure user is logged in before calling this function
     *
     * @return string
     */
    public static function get_current_user_registration_date()
    {
        global $user_ID;

        return self::get_user_registration_date($user_ID);
    }

    /**
     * Get the registration date of a user.
     *
     * @param  integer $user_id The user ID.
     * @return string
     */
    public static function get_user_registration_date($user_id)
    {
        global $wpdb;

        $q = "SELECT `user_registered` FROM {$wpdb->users} WHERE ID=%d";

        $result = $wpdb->get_var($wpdb->prepare($q, $user_id));

        return (empty($result) ? gmdate('Y-m-d H:i:s') : $result);
    }

    /**
     * Get the signup date for a user's product.
     * This used to be called "get_ts_of_product_signup"
     *
     * @param  integer $user_id    The user ID.
     * @param  integer $product_id The product ID.
     * @return string|false
     */
    public static function get_user_product_signup_date($user_id, $product_id)
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();
        $prd     = new MeprProduct($product_id);

        // If this is a renewal type product, we should grab the first txn instead of the last.
        $order = ($prd->is_one_time_payment() && $prd->allow_renewal) ? 'ASC' : 'DESC';
        $order = MeprHooks::apply_filters('mepr-user-membership-signup-date-txn-order', $order, $user_id, $product_id);

        // Grab  complete payment OR confirmed confirmation type for this user.
        $q      = "SELECT id
            FROM {$mepr_db->transactions}
            WHERE product_id = %d
              AND user_id = %d
              AND ( (txn_type IN (%s,%s,%s,%s) AND status = %s) OR (txn_type = %s AND status = %s) )
          ORDER BY id {$order}
          LIMIT 1";
        $q      =  $wpdb->prepare(
            $q,
            $product_id,
            $user_id,
            MeprTransaction::$payment_str,
            MeprTransaction::$sub_account_str,
            MeprTransaction::$woo_txn_str,
            MeprTransaction::$fallback_str,
            MeprTransaction::$complete_str,
            MeprTransaction::$subscription_confirmation_str,
            MeprTransaction::$confirmed_str
        );
        $txn_id = $wpdb->get_var($q);

        // No txn for this user for this product.
        if (empty($txn_id) || $txn_id <= 0) {
            return false;
        }

        // Load up the txn object duh!
        $txn = new MeprTransaction($txn_id);

        // This isn't a subscription, so this should be the only txn for this product.
        $sub = $txn->subscription();
        if (empty($sub)) {
            return $txn->created_at;
        }

        // Get the first real payment txn in this $sub unless it had a free trial - if free trial then we want the drips to start when the free trial started, not when the first payment happened.
        if (!$sub->trial || ($sub->trial && $sub->trial_amount > 0.00)) {
            $_REQUEST['mepr_get_real_payment'] = true; // Try to get a real payment instead of a confirmation txn.
        }

        $first_txn = $sub->first_txn();

        if ($first_txn == false || !($first_txn instanceof MeprTransaction)) {
            return false;
        } else {
            return $first_txn->created_at;
        }
    }

    /**
     * Get the expiration date for a user's product.
     *
     * @param  integer $user_id    The user ID.
     * @param  integer $product_id The product ID.
     * @param  boolean $return_txn Whether to return the transaction object.
     * @return string|MeprTransaction|false
     */
    public static function get_user_product_expires_at_date($user_id, $product_id, $return_txn = false)
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $select = ($return_txn) ? 'id' : 'expires_at';

        $q = "SELECT {$select} FROM {$mepr_db->transactions} WHERE status IN(%s, %s) AND product_id = %d AND user_id = %d ORDER BY expires_at DESC LIMIT 1";
        $q = $wpdb->prepare($q, MeprTransaction::$complete_str, MeprTransaction::$confirmed_str, $product_id, $user_id);

        $result = $wpdb->get_var($q);

        if ($result && $return_txn) {
            return new MeprTransaction((int)$result);
        }

        return ($result) ? $result : false;
    }

    /**
     * Store the user data.
     *
     * @return integer The user ID.
     * @throws MeprCreateException If the user cannot be saved.
     */
    public function store()
    {
        if (isset($this->ID) and !is_null($this->ID)) {
            $id = wp_update_user((array)$this->rec);
        } else {
            // Check if the email is already in use.
            $maybe_user = get_user_by('email', $this->user_email);

            if (! empty($maybe_user->ID)) { // User with this email, so update.
                $this->ID      = $maybe_user->ID;
                $this->rec->ID = $maybe_user->ID;
                $id            = wp_update_user((array)$this->rec);
            } else { // Insert the user.
                $id = wp_insert_user((array)$this->rec);
            }
        }

        if (is_wp_error($id)) {
            throw new MeprCreateException(sprintf(
                // Translators: %s: error message.
                __('The user was unable to be saved: %s', 'memberpress'),
                $id->get_error_message()
            ));
        } else {
            $this->rec->ID = $id;
        }

        $this->store_meta();

        return $id;
    }

    /**
     * Alias of store
     *
     * @return integer The user ID.
     */
    public function save()
    {
        return $this->store();
    }

    /**
     * Store the user meta data.
     *
     * @return void
     */
    public function store_meta()
    {
        update_user_meta($this->ID, self::$first_name_str, $this->first_name);
        update_user_meta($this->ID, self::$last_name_str, $this->last_name);
        update_user_meta($this->ID, self::$signup_notice_sent_str, $this->signup_notice_sent);
    }

    /**
     * Alias of store_meta
     *
     * @return void
     */
    public function save_meta()
    {
        $this->store_meta();
    }

    /**
     * Destroy the user.
     *
     * @return MeprUser
     * @throws MeprCreateException If the user cannot be deleted.
     */
    public function destroy()
    {
        if (!function_exists('wp_delete_user')) {
            if (!is_multisite()) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
            } else {
                require_once(ABSPATH . 'wp-admin/includes/ms.php');
            }
        }

        $res = wp_delete_user($this->ID);

        if (false === $res) {
            throw new MeprCreateException(sprintf(__('This user was unable to be deleted.', 'memberpress')));
        }

        return $this;
    }

    /**
     * Check if the reset form key is valid.
     *
     * @param  string $key The reset form key.
     * @return boolean
     */
    public function reset_form_key_is_valid($key)
    {
        $wp_user = check_password_reset_key($key, $this->user_login);
        return !is_wp_error($wp_user);
    }

    /**
     * Check if the reset form key has expired.
     *
     * @param  string $key The reset form key.
     * @return boolean
     */
    public function reset_form_key_has_expired($key)
    {
        $wp_user = check_password_reset_key($key, $this->user_login);
        return is_wp_error($wp_user) && 'expired_key' === $wp_user->get_error_code() ? true : false;
    }

    /**
     * Backwards compatibility
     *
     * @deprecated Use send_password_notification('reset')
     * @param      boolean $force_send Whether to force the send.
     * @return     void
     */
    public function send_reset_password_requested_notification($force_send = false)
    {
        $this->send_password_notification('reset', $force_send);
    }

    /**
     * Send reset or set password email
     *
     * @param  string  $type       Reset|Set.
     * @param  boolean $force_send Ignore already_sent.
     * @return void
     */
    public function send_password_notification($type, $force_send = false)
    {
        static $already_sent;

        // Prevent duplicate emails.
        if ($force_send === false && $already_sent === true) {
            return;
        }

        $already_sent = true;
        // Locals for email view.
        $link = $this->reset_password_link();
        if ($link) {
            $locals = [
                'user_login'          => $this->user_login,
                'user_data'           => get_user_by('login', $this->user_login),
                'first_name'          => $this->first_name,
                'mepr_blogname'       => MeprUtils::blogname(),
                'mepr_blogurl'        => home_url(),
                'reset_password_link' => $link,
            ];

            if ($type === 'reset') {
                $this->send_reset_password_notification($locals);
            } else {
                $this->send_set_password_notification($locals);
            }
        }
    }

    /**
     * Send reset password email
     *
     * @param  array $locals Local variables used in view.
     * @return void
     */
    private function send_reset_password_notification($locals = [])
    {
        // Translators: In this string, %s is the Blog Name/Title.
        $subject = apply_filters('retrieve_password_title', sprintf(__('[%s] Password Reset', 'memberpress'), $locals['mepr_blogname']), $locals['user_login'], $locals['user_data']);

        ob_start();
        MeprView::render('/emails/user_reset_password', get_defined_vars());
        $message = ob_get_clean();

        MeprUtils::wp_mail($this->formatted_email(), $subject, $message, ['Content-Type: text/html']);
    }

    /**
     * Send set password email
     *
     * @param  array $locals Local variables used in view.
     * @return void
     */
    private function send_set_password_notification($locals = [])
    {
        // Translators: In this string, %s is the Blog Name/Title.
        $subject = MeprHooks::apply_filters('mepr_set_new_password_title', sprintf(__('[%s] Set Your New Password', 'memberpress'), $locals['mepr_blogname']));

        ob_start();
        MeprView::render('/emails/user_set_password', get_defined_vars());
        $message = ob_get_clean();

        MeprUtils::wp_mail($this->formatted_email(), $subject, $message, ['Content-Type: text/html']);
    }

    /**
     * Set the password and send notifications.
     *
     * @param  string $key      The reset key.
     * @param  string $password The new password.
     * @return boolean|void
     */
    public function set_password_and_send_notifications($key, $password)
    {
        static $already_sent;

        // Prevent duplicate emails.
        if ($already_sent === true) {
            return;
        }

        $already_sent = true;


        $mepr_options  = MeprOptions::fetch();
        $mepr_blogname = MeprUtils::blogname();
        $mepr_blogurl  = home_url();

        if ($this->reset_form_key_is_valid($key)) {
            add_filter('send_password_change_email', '__return_false'); // DISABLE WP'S PW CHANGE NOTIFICATION.

            $this->rec->user_pass = $password;
            $this->store();

            $username   = $this->user_login;
            $first_name = $this->first_name;

            // Translators: In this string, %s is the Blog Name/Title.
            $subject = MeprHooks::apply_filters('mepr_admin_pw_reset_title', sprintf(__('[%s] Password Lost/Changed', 'memberpress'), $mepr_blogname));

            ob_start();
            MeprView::render('/emails/admin_password_reset', get_defined_vars());
            $message = ob_get_clean();

            MeprUtils::wp_mail_to_admin($subject, $message, ['Content-Type: text/html']);

            $login_link = $mepr_options->login_page_url();

            // Send password email to new user.
            $recipient = $this->formatted_email();

            // Translators: In this string, %s is the Blog Name/Title.
            $subject = MeprHooks::apply_filters('mepr_user_pw_reset_title', sprintf(_x('[%s] Your new Password', 'ui', 'memberpress'), $mepr_blogname));

            ob_start();
            MeprView::render('/emails/user_password_was_reset', get_defined_vars());
            $message = ob_get_clean();

            MeprUtils::wp_mail($recipient, $subject, $message, ['Content-Type: text/html']);

            return true;
        }

        return false;
    }

    /**
     * Validate the account.
     *
     * @param  array $params The parameters to validate.
     * @param  array $errors The existing errors.
     * @return array
     */
    public static function validate_account($params, $errors = [])
    {
        $mepr_options = MeprOptions::fetch();

        extract($params, EXTR_SKIP);

        if ($mepr_options->require_fname_lname && (empty($user_first_name) || empty($user_last_name))) {
            $errors[] = __('You must enter both your First and Last name', 'memberpress');
        }

        if (empty($user_email) || !is_email(stripslashes($user_email))) {
            $errors[] = __('You must enter a valid email address', 'memberpress');
        }

        // Old email is not the same as the new, so let's make sure no else has it.
        $user = MeprUtils::get_currentuserinfo(); // Old user info is here since we haven't stored the new stuff yet.
        if ($user !== false && $user->user_email != stripslashes($user_email) && email_exists(stripslashes($user_email))) {
            $errors[] = __('This email is already in use by another member', 'memberpress');
        }

        return $errors;
    }

    /**
     * Validate the signup.
     *
     * @param  array  $params      The parameters to validate.
     * @param  array  $errors      The existing errors.
     * @param  string $current_url The current URL.
     * @return array
     */
    public static function validate_signup($params, $errors, $current_url = '')
    {
        $mepr_options         = MeprOptions::fetch();
        $custom_fields_errors = [];

        extract($params);

        if (!MeprUtils::is_user_logged_in()) {
            // Don't validate username shiz if there's no username yo!
            if (!$mepr_options->username_is_email) {
                $user_login = sanitize_user($user_login);

                if (empty($user_login)) {
                    $errors['user_login'] = __('Username must not be blank', 'memberpress');
                }

                if (!preg_match('#^[a-zA-Z0-9_@\.\-\+ ]+$#', $user_login)) { // Emails can have a few more characters - so let's not block an email here.
                    $errors['user_login'] = __('Username must only contain letters, numbers, spaces and/or underscores', 'memberpress');
                }

                if (username_exists($user_login)) {
                    $current_url = urlencode(esc_url($current_url ? $current_url : $_SERVER['REQUEST_URI']));
                    $login_url   = $mepr_options->login_page_url("redirect_to={$current_url}");

                    $errors['user_login'] = sprintf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        __('This username has already been taken. If you are an existing user, please %1$sLogin%2$s first. You will be redirected back here to complete your sign-up afterwards.', 'memberpress'),
                        "<a href=\"{$login_url}\"><strong>",
                        '</strong></a>'
                    );
                }
            }

            if (!is_email(stripslashes($user_email))) {
                $errors['user_email'] = __('Email must be a real and properly formatted email address', 'memberpress');
            }

            if (email_exists($user_email)) {
                $current_url = $current_url ? $current_url : urlencode(esc_url($_SERVER['REQUEST_URI']));
                $login_url   = $mepr_options->login_page_url("redirect_to={$current_url}");

                $errors['user_email'] = sprintf(
                    // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                    __('This email address has already been used. If you are an existing user, please %1$sLogin%2$s to complete your purchase. You will be redirected back here to complete your sign-up afterwards.', 'memberpress'),
                    "<a href=\"{$login_url}\"><strong>",
                    '</strong></a>'
                );
            }

            if ($mepr_options->disable_checkout_password_fields === false) {
                if (empty($mepr_user_password)) {
                    $errors['mepr_user_password'] = __('You must enter a Password.', 'memberpress');
                }

                if (empty($mepr_user_password_confirm)) {
                    $errors['mepr_user_password_confirm'] = __('You must enter a Password Confirmation.', 'memberpress');
                }

                if ($mepr_user_password != $mepr_user_password_confirm) {
                    $errors['mepr_user_password_confirm'] = __('Your Password and Password Confirmation don\'t match.', 'memberpress');
                }
            }
        }

        // Honeypot (for logged in and logged out users now).
        if ((isset($mepr_no_val) && !empty($mepr_no_val))) {
            $errors[] = __('Only humans are allowed to register.', 'memberpress');
        }

        if (
            ($mepr_options->show_fname_lname and $mepr_options->require_fname_lname) &&
            (empty($user_first_name) || empty($user_last_name))
        ) {
            $errors[] = __('You must enter both your First and Last name', 'memberpress');
        }

        if (isset($mepr_coupon_code) && !empty($mepr_coupon_code) && !MeprCoupon::is_valid_coupon_code($mepr_coupon_code, $mepr_product_id)) {
            $errors['mepr_coupon_code'] = __('Your coupon code is invalid.', 'memberpress');
        }

        if ($mepr_options->require_tos && !isset($mepr_agree_to_tos)) {
            $errors[] = __('You must agree to the Terms of Service', 'memberpress');
        }

        if ($mepr_options->require_privacy_policy && !isset($mepr_agree_to_privacy_policy)) {
            $errors[] = __('You must agree to the Privacy Policy', 'memberpress');
        }

        $product             = new MeprProduct($mepr_product_id);
        $product_coupon_code = isset($mepr_coupon_code) ? $mepr_coupon_code : null;
        $product_price       = $product->adjusted_price($product_coupon_code);
        $pms                 = $mepr_options->payment_methods();

        if (!$product->can_you_buy_me()) {
            $user = MeprUtils::get_currentuserinfo();

            if (
                $user instanceof MeprUser &&
                $user->is_already_subscribed_to($product->ID) &&
                !$product->simultaneous_subscriptions &&
                !$product->allow_renewal
            ) {
                $errors[] = sprintf(
                    // Translators: %1$s: product name, %2$s: open link tag, %3$s: close link tag.
                    esc_html__('You are already subscribed to %1$s, %2$sclick here%3$s to view your subscriptions.', 'memberpress'),
                    esc_html($product->post_title),
                    '<a href="' . esc_url(add_query_arg(['action' => 'subscriptions'], $mepr_options->account_page_url())) . '">',
                    '</a>'
                );
            } else {
                if (!empty($product->cannot_purchase_message)) {
                    $errors[] = wpautop(do_shortcode($product->cannot_purchase_message));
                } else {
                    $errors[] = _x('You don\'t have access to purchase this item.', 'ui', 'memberpress');
                }
            }
        }

        // Don't allow free payment method on non-free transactions
        // Don't allow manual payment method on the signup form.
        unset($pms['free']);
        unset($pms['manual']);

        $pms = array_keys($pms);

        if ((!isset($mepr_payment_method) or empty($mepr_payment_method)) and $product_price > 0.00) {
            $errors[] = __('There are no active Payment Methods right now ... please contact the system administrator for help.', 'memberpress');
        }

        // We only care what the payment_method is if the membership isn't free
        // Don't allow payment methods not included in mepr option's pm's
        // Don't allow payment methods not included in custom pm's if we're customizing pm's.
        if (
            isset($mepr_payment_method) and
            !empty($mepr_payment_method) and
            $product_price > 0.00 and
            ( !in_array($mepr_payment_method, $pms) or
            ( $product->customize_payment_methods and
            isset($product->custom_payment_methods) and
            is_array($product->custom_payment_methods) and
            !in_array(
                $mepr_payment_method,
                $product->custom_payment_methods
            ) ) )
        ) {
            $errors[] = __('Invalid Payment Method', 'memberpress');
        }

        // Make sure this isn't the logged in purchases form.
        if (!isset($logged_in_purchase) || (isset($logged_in_purchase) && $mepr_options->show_fields_logged_in_purchases)) {
            $custom_fields_errors = MeprUsersCtrl::validate_extra_profile_fields(null, null, null, true, $product);
        }

        $order_bump_product_ids = isset($_POST['mepr_order_bumps']) && is_array($_POST['mepr_order_bumps']) ? array_filter(array_map('intval', $_POST['mepr_order_bumps'])) : [];

        // Make sure this isn't the logged in purchases form.
        if (!isset($logged_in_purchase)) {
            $product_required_order_bumps = $product->get_required_order_bumps();

            // If the membership requires order bumps and they are not found in the POST request, enqueue a validation error.
            if (empty($order_bump_product_ids) && ! empty($product_required_order_bumps)) {
                $errors[] = __('Required add-on(s) cannot be removed from this sale.', 'memberpress');
            } elseif (! empty($product_required_order_bumps) && ! empty($order_bump_product_ids)) {
                foreach ($product_required_order_bumps as $required_order_bump_id) {
                    if (! in_array($required_order_bump_id, $order_bump_product_ids, true)) {
                         $errors[] = __('One of the required add-ons cannot be removed from this sale.', 'memberpress');
                         break;
                    }
                }
            }
        }

        if (!empty($order_bump_product_ids)) {
            try {
                MeprCheckoutCtrl::get_order_bump_products($mepr_product_id, $order_bump_product_ids);
            } catch (\Exception $ex) {
                $errors[] = $ex->getMessage();
            }
        }

        return array_merge($errors, $custom_fields_errors);
    }

    /**
     * Validate the login.
     *
     * @param  array $params The parameters to validate.
     * @param  array $errors The existing errors.
     * @return array
     */
    public static function validate_login($params, $errors)
    {
        extract($params);
        $log = stripcslashes($log); // Necessary to allow apostrophes in email addresses. Yeah, I didn't know that was a thing either.

        if (empty($log)) {
            $errors[] = __('Username must not be blank', 'memberpress');
        }

        if (is_email($log)) {
            $user = get_user_by('email', $log);

            // Try one more thing before giving up in case their username is an email address but doesn't match their current email address (user_email != user_login).
            if ($user === false) {
                $user = get_user_by('login', $log);
            }
        } else {
            $user = get_user_by('login', $log);
        }

        if ($user === false) {
            $errors[] = __('Your username or password was incorrect', 'memberpress');
        }

        // If no errors at this point, let's check their password.
        if (empty($errors)) {
            if (!MeprUtils::wp_check_password($user, $pwd)) {
                $errors[] = __('Your username or password was incorrect', 'memberpress');
            }
        }

        return $errors;
    }

    /**
     * Validate the forgot password form.
     *
     * @param  array $params The parameters to validate.
     * @param  array $errors The existing errors.
     * @return array
     */
    public static function validate_forgot_password($params, $errors)
    {
        extract($params);

        if (empty($mepr_user_or_email)) {
            $errors[] = __('You must enter a Username or Email', 'memberpress');
        }

        return $errors;
    }

    /**
     * Validate the reset password form.
     *
     * @param  array $params The parameters to validate.
     * @param  array $errors The existing errors.
     * @return array
     */
    public static function validate_reset_password($params, $errors)
    {
        $mepr_options = MeprOptions::fetch();

        if ($mepr_options->enforce_strong_password && isset($params['mp-pass-strength']) && (int) $params['mp-pass-strength'] < MeprZxcvbnCtrl::get_required_int()) {
            $errors[] = __('Your password must meet the minimum strength requirement.', 'memberpress');
        }

        $password         = isset($params['mepr_user_password']) ? $params['mepr_user_password'] : '';
        $password_confirm = isset($params['mepr_user_password_confirm']) ? $params['mepr_user_password_confirm'] : '';

        if (empty($password)) {
            $errors[] = __('You must enter a Password.', 'memberpress');
        }

        if (empty($password_confirm)) {
            $errors[] = __('You must enter a Password Confirmation.', 'memberpress');
        }

        if ($password != $password_confirm) {
            $errors[] = __("Your Password and Password Confirmation don't match.", 'memberpress');
        }

        return $errors;
    }

    /**
     * Mark a renewal as sent.
     *
     * @param  integer $txn_id The transaction ID.
     * @return boolean
     */
    public function sent_renewal($txn_id)
    {
        return add_user_meta($this->ID, 'mepr_renewal', $txn_id);
    }

    /**
     * Get the renewals for the user.
     *
     * @return array
     */
    public function get_renewals()
    {
        return get_user_meta($this->ID, 'mepr_renewal', false);
    }

    /**
     * Check if a renewal has already been sent.
     *
     * @param  integer $txn_id The transaction ID.
     * @return boolean
     */
    public function renewal_already_sent($txn_id)
    {
        $renewals = $this->get_renewals();
        return (!empty($renewals) and in_array($txn_id, $renewals));
    }

    /**
     * Get the subscriptions for the user.
     *
     * @return array
     */
    public function subscriptions()
    {
        $table = MeprSubscription::account_subscr_table(
            'created_at',
            'DESC',
            '',
            '',
            'any',
            '',
            false,
            [
                'member'   => $this->rec->user_login,
                'statuses' => [
                    MeprSubscription::$active_str,
                    MeprSubscription::$suspended_str,
                    MeprSubscription::$cancelled_str,
                ],
            ],
            MeprHooks::apply_filters('mepr_user_subscriptions_query_cols', ['id','created_at'])
        );

        $subscriptions = [];
        foreach ($table['results'] as $row) {
            if ($row->sub_type == 'subscription') {
                $sub = new MeprSubscription($row->id);
            } elseif ($row->sub_type == 'transaction') {
                $sub = new MeprTransaction($row->id);
            }

            $subscriptions[] = MeprHooks::apply_filters('mepr_user_subscriptions_customize_subscription', $sub, $row, $this);
        }

        return $subscriptions;
    }

    /**
     * Get the transactions for the user.
     *
     * @param  string|null $where The where clause.
     * @param  string      $order The order by clause.
     * @param  string      $sort  The sort direction.
     * @return array
     */
    public function transactions($where = null, $order = 'created_at', $sort = 'DESC')
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $conditions = $wpdb->prepare('WHERE user_id=%d', $this->ID);

        if (!is_null($where)) {
            $conditions = "{$conditions} AND {$where}";
        }

        $q = "
      SELECT *
        FROM {$mepr_db->transactions}
       {$conditions}
       ORDER BY {$order} {$sort}
    ";

        return $wpdb->get_results($q);
    }

    /**
     * Get transactions for a product.
     * Does NOT get sub confirmation txns. For right now this is only used for Reminders, but could be used in other places
     *
     * @param  integer $product_id  The product ID.
     * @param  boolean $expired     Whether to include expired transactions.
     * @param  boolean $non_expired Whether to include non-expired transactions.
     * @return array
     */
    public function transactions_for_product($product_id, $expired = false, $non_expired = false)
    {
        global $wpdb;

        $operator    = ($expired) ? '<=' : '>=';
        $db_lifetime = ($expired) ? '' : $wpdb->prepare('OR expires_at IS NULL OR expires_at = %s', MeprUtils::db_lifetime());
        $where       = $wpdb->prepare(
            "product_id = %d AND (expires_at {$operator} %s {$db_lifetime}) AND txn_type = %s AND status = %s",
            $product_id,
            gmdate('c'),
            MeprTransaction::$payment_str,
            MeprTransaction::$complete_str
        );

        return $this->transactions($where);
    }

    /**
     * Get recent transactions for the user.
     *
     * @param  integer $limit The number of transactions to retrieve.
     * @return array
     */
    public function recent_transactions($limit = 5)
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        $q = $wpdb->prepare(
            "
        SELECT id
          FROM {$mepr_db->transactions}
         WHERE user_id=%d
         ORDER BY id DESC
         LIMIT %d
      ",
            $this->ID,
            $limit
        );

        $txn_ids = $wpdb->get_col($q);

        if (empty($txn_ids)) {
            return [];
        }

        $txns = [];
        foreach ($txn_ids as $txn_id) {
            $txns[] = new MeprTransaction($txn_id);
        }

        return $txns;
    }

    /**
     * Get recent subscriptions for the user.
     *
     * @param  integer $limit The number of subscriptions to retrieve.
     * @return array
     */
    public function recent_subscriptions($limit = 5)
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        $q = $wpdb->prepare(
            "
        SELECT sub.id
          FROM {$mepr_db->subscriptions} AS sub
         WHERE sub.user_id = %d
         ORDER BY id DESC
         LIMIT %d
      ",
            $this->ID,
            $limit
        );

        $sub_ids = $wpdb->get_col($q);

        if (empty($sub_ids)) {
            return [];
        }

        $subs = [];
        foreach ($sub_ids as $sub_id) {
            $subs[] = new MeprSubscription($sub_id);
        }

        return $subs;
    }

    /**
     * Email users with expiring transactions.
     *
     * @return void
     */
    public static function email_users_with_expiring_transactions()
    {
        $mepr_options = MeprOptions::fetch();

        if ($mepr_options->user_renew_email == true) {
            $transactions = MeprTransaction::get_expiring_transactions();
            if (!empty($transactions) and is_array($transactions)) {
                foreach ($transactions as $transaction) {
                    $user    = new MeprUser($transaction->user_id);
                    $product = new MeprProduct($transaction->product_id);

                    $params                  = new stdClass();
                    $params->user_first_name = $user->first_name;
                    $params->user_last_name  = $user->last_name;
                    $params->user_email      = $user->user_email;
                    $params->to_email        = $user->user_email;
                    $params->to_name         = "{$user->first_name} {$user->last_name}";
                    $params->membership_type = $product->post_title;
                    $params->business_name   = $mepr_options->attr('biz_name');
                    $params->blog_name       = MeprUtils::blogname();
                    $params->renewal_link    = $user->renewal_link($transaction->id);

                    if (MeprUtils::send_user_renew_notification((array)$params)) {
                        $user->sent_renewal($transaction->id);
                    }
                }
            }
        }
    }

    /**
     * Get the renewal link for a transaction.
     *
     * @param  integer $txn_id The transaction ID.
     * @return string
     */
    public function renewal_link($txn_id)
    {
        $txn     = new MeprTransaction($txn_id);
        $product = new MeprProduct($txn->product_id);

        if ($product->allow_renewal) {
            return $product->url("?renew=true&uid={$this->uuid}&tid={$txn_id}");
        }

        return '';
    }

    /**
     * Get the reset password link.
     *
     * @return string|false
     */
    public function reset_password_link()
    {
        $mepr_options = MeprOptions::fetch();

        $wp_user = MeprUtils::get_user_by('id', $this->ID);
        // Creates and stores key (user_activation_key) with default expiration of 1 day.
        add_filter('allow_password_reset', 'MeprUser::allow_password_reset', 99, 2);
        $key = get_password_reset_key($wp_user);
        remove_filter('allow_password_reset', 'MeprUser::allow_password_reset', 99);

        $permalink = $mepr_options->login_page_url();
        $delim     = MeprAppCtrl::get_param_delimiter_char($permalink);

        if (is_wp_error($key)) {
            $_REQUEST['error'] = $key->get_error_message();
            return false;
        }

        return "{$permalink}{$delim}action=reset_password&mkey={$key}&u=" . urlencode($this->user_login);
    }

    /**
     * Allow password reset.
     *
     * @param  boolean $allow   Whether to allow password reset.
     * @param  integer $user_id The user ID.
     * @return boolean
     */
    public static function allow_password_reset($allow, $user_id)
    {
        return true;
    }

    /**
     * Returns a list of product ids that the user has or is currently subscribed to
     *
     * @return array
     */
    public function current_and_prior_subscriptions()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $q = "SELECT DISTINCT(product_id)
            FROM {$mepr_db->transactions}
          WHERE user_id = %d
            AND ( (txn_type IN (%s,%s,%s,%s) AND status = %s) OR ((txn_type = %s AND status = %s)) )";

        $q = $wpdb->prepare(
            $q,
            $this->ID,
            MeprTransaction::$payment_str,
            MeprTransaction::$sub_account_str,
            MeprTransaction::$woo_txn_str,
            MeprTransaction::$fallback_str,
            MeprTransaction::$complete_str,
            MeprTransaction::$subscription_confirmation_str,
            MeprTransaction::$confirmed_str
        );

        return $wpdb->get_col($q);
    }

    /**
     * Get subscription expirations.
     *
     * @param  string  $type            The type of expiration ('all', 'expired').
     * @param  boolean $exclude_stopped Whether to exclude stopped subscriptions.
     * @return array
     */
    public function subscription_expirations($type = 'all', $exclude_stopped = false)
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $exp_op = (($type == 'expired') ? '<=' : '>');

        // Get all recurring subscriptions that
        // are expired but still have an active status.
        $query = 'SELECT sub.id as id, tr.expires_at AS expires_at ' .
               "FROM {$mepr_db->subscriptions} AS sub " .
               "JOIN {$mepr_db->transactions} AS tr " .
                 'ON tr.id = ( CASE ' .
                              // When 1 or more lifetime txns exist for this sub.
                              'WHEN ( SELECT COUNT(*) ' .
                                       "FROM {$mepr_db->transactions} AS etc " .
                                      'WHERE etc.subscription_id=sub.id ' .
                                        'AND etc.status IN (%s,%s) ' .
                                        'AND etc.expires_at=%s ) > 0 ' .
                              // Use the latest lifetime txn for expiring_txn.
                              'THEN ( SELECT max(etl.id) ' .
                                       "FROM {$mepr_db->transactions} AS etl " .
                                      'WHERE etl.subscription_id=sub.id ' .
                                        'AND etl.status IN (%s,%s) ' .
                                        'AND etl.expires_at=%s ) ' .
                              // Otherwise use the latest complete txn for expiring_txn.
                              'ELSE ( SELECT etr.id ' .
                                       "FROM {$mepr_db->transactions} AS etr " .
                                      'WHERE etr.subscription_id=sub.id ' .
                                        'AND etr.status IN (%s,%s) ' .
                                      'ORDER BY etr.expires_at DESC ' .
                                      'LIMIT 1 ) ' .
                              'END ) ' .
              'WHERE sub.user_id=%d ' .
                'AND tr.expires_at IS NOT NULL ' .
                'AND tr.expires_at > %s ' .
                'AND DATE_ADD(tr.created_at, INTERVAL 1 DAY) <= %s ' . // At least a day old here.
                "AND tr.expires_at {$exp_op} %s";

        $query = $wpdb->prepare(
            $query,
            MeprTransaction::$complete_str,
            MeprTransaction::$confirmed_str,
            MeprUtils::db_lifetime(),
            MeprTransaction::$complete_str,
            MeprTransaction::$confirmed_str,
            MeprUtils::db_lifetime(),
            MeprTransaction::$complete_str,
            MeprTransaction::$confirmed_str,
            $this->ID, // User ID.
            MeprUtils::db_lifetime(),
            MeprUtils::db_now(),
            MeprUtils::db_now()
        );

        if ($exclude_stopped) {
            $query .= $wpdb->prepare(' AND sub.status = %s', MeprSubscription::$active_str);
        }

        $res = $wpdb->get_results($query);

        return $res;
    }

    /**
     * Get the number of logins for the user.
     *
     * @return integer
     */
    public function get_num_logins()
    {
        $mepr_db = MeprDb::fetch();
        $args    = [
            'evt_id_type' => MeprEvent::$users_str,
            'evt_id'      => $this->ID,
            'event'       => MeprEvent::$login_event_str,
        ];
        return $mepr_db->get_count($mepr_db->events, $args);
    }

    /**
     * Get the last login data for the user.
     *
     * @return object|false
     */
    public function get_last_login_data()
    {
        $mepr_db = MeprDb::fetch();
        $args    = [
            'evt_id_type' => MeprEvent::$users_str,
            'evt_id'      => $this->ID,
            'event'       => MeprEvent::$login_event_str,
        ];
        $rec     = $mepr_db->get_records($mepr_db->events, $args, '`created_at` DESC', 1);
        return ( empty($rec) ? false : $rec[0] );
    }

    /**
     * Set the address for the user.
     *
     * @param  array $params The address parameters.
     * @return void
     */
    public function set_address($params)
    {
        update_user_meta($this->ID, 'mepr-address-one', sanitize_text_field(wp_unslash($params['mepr-address-one'])));
        update_user_meta($this->ID, 'mepr-address-two', sanitize_text_field(wp_unslash($params['mepr-address-two'])));
        update_user_meta($this->ID, 'mepr-address-city', sanitize_text_field(wp_unslash($params['mepr-address-city'])));
        update_user_meta($this->ID, 'mepr-address-state', sanitize_text_field(wp_unslash($params['mepr-address-state'])));
        update_user_meta($this->ID, 'mepr-address-zip', sanitize_text_field(wp_unslash($params['mepr-address-zip'])));
        update_user_meta($this->ID, 'mepr-address-country', sanitize_text_field(wp_unslash($params['mepr-address-country'])));
    }

    /**
     * Get the full address for the user.
     *
     * @param  boolean $fallback_to_biz_addr Whether to fallback to business address.
     * @return array
     */
    public function full_address($fallback_to_biz_addr = true)
    {
        return [
            'mepr-address-one'     => $this->address('one', $fallback_to_biz_addr),
            'mepr-address-two'     => $this->address('two', $fallback_to_biz_addr),
            'mepr-address-city'    => $this->address('city', $fallback_to_biz_addr),
            'mepr-address-state'   => $this->address('state', $fallback_to_biz_addr),
            'mepr-address-zip'     => $this->address('zip', $fallback_to_biz_addr),
            'mepr-address-country' => $this->address('country', $fallback_to_biz_addr),
        ];
    }

    /**
     * Get the address for a specific field.
     *
     * @param  string  $field                The address field.
     * @param  boolean $fallback_to_biz_addr Whether to fallback to business address.
     * @return string
     */
    public function address($field, $fallback_to_biz_addr = true)
    {
        if ($this->address_is_set()) {
            return get_user_meta($this->ID, "mepr-address-{$field}", true);
        } elseif ($fallback_to_biz_addr) {
            $mepr_options = MeprOptions::fetch();

            if ($mepr_options->attr('tax_default_address') == 'none') {
                return get_user_meta($this->ID, "mepr-address-{$field}", true);
            } else {
                switch ($field) {
                    case 'one':
                        return $mepr_options->attr('biz_address1');
                    case 'two':
                        return $mepr_options->attr('biz_address2');
                    case 'city':
                        return $mepr_options->attr('biz_city');
                    case 'state':
                        return $mepr_options->attr('biz_state');
                    case 'zip':
                        return $mepr_options->attr('biz_postcode');
                    case 'country':
                        return $mepr_options->attr('biz_country');
                    default:
                        return get_user_meta($this->ID, "mepr-address-{$field}", true);
                }
            }
        } else {
            return '';
        }
    }

    /**
     * Check if the address is set.
     *
     * @return boolean
     */
    public function address_is_set()
    {
        $one = get_user_meta($this->ID, 'mepr-address-one', true);
        // $two      = get_user_meta( $this->ID, 'mepr-address-two', true);
        $city     = get_user_meta($this->ID, 'mepr-address-city', true);
        $state    = get_user_meta($this->ID, 'mepr-address-state', true);
        $country  = get_user_meta($this->ID, 'mepr-address-country', true);
        $postcode = get_user_meta($this->ID, 'mepr-address-zip', true);

        // To update pricing terms string with AJAX, we need to send the POST address
        // This only runs when running AJAX, that's the only place the two actions are set.
        if ($this->use_address_from_request()) {
            $one      = isset($_POST['mepr-address-one']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-one'])) : '';
            $city     = isset($_POST['mepr-address-city']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-city'])) : '';
            $state    = isset($_POST['mepr-address-state']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-state'])) : '';
            $country  = isset($_POST['mepr-address-country']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-country'])) : '';
            $postcode = isset($_POST['mepr-address-zip']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-zip'])) : '';
        }

        return (!empty($country) && !empty($postcode) && !empty($state) && !empty($city) && !empty($one));
    }

    /**
     * Whether the address in the request data should override the saved user address
     *
     * @return boolean
     */
    public function use_address_from_request()
    {
        $use_address_from_request = false;
        $action                   = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';

        if (!empty($action) && $action == 'mepr_get_checkout_state') {
            $mepr_options = MeprOptions::fetch();

            if (!MeprUtils::is_user_logged_in() || ($mepr_options->show_address_fields && $mepr_options->show_fields_logged_in_purchases)) {
                $use_address_from_request = true;
            }
        }

        return $use_address_from_request;
    }

    /**
     * Check if address fields are required for active products
     *
     * @return boolean
     */
    public function show_address_fields()
    {
        $active_products = $this->active_product_subscriptions('products');
        foreach ($active_products as $product) {
            if (!$product->disable_address_fields) {
                return true;
            }
        }
        return empty($active_products); // If the user has no memberships, let's just show the address fields.
    }

    /**
     * Get the tax rate for the user.
     *
     * @param  integer|null $prd_id The product ID.
     * @return MeprTaxRate
     */
    public function tax_rate($prd_id = null)
    {
        $mepr_options = MeprOptions::fetch();

        // No taxes enabled?
        if (!get_option('mepr_calculate_taxes', false)) {
            return new MeprTaxRate();
        }

        $country  = $mepr_options->attr('biz_country');
        $state    = $mepr_options->attr('biz_state');
        $postcode = $mepr_options->attr('biz_postcode');
        $city     = $mepr_options->attr('biz_city');
        $street   = sprintf('%s %s', $mepr_options->attr('biz_address1'), $mepr_options->attr('biz_address2'));

        if ($this->address_is_set()) {
            if (
                $mepr_options->attr('tax_calc_location') == 'customer' ||
                MeprHooks::apply_filters('mepr-tax-rate-use-customer-address', false, $this)
            ) {
                if ($this->use_address_from_request()) {
                    $one      = isset($_POST['mepr-address-one']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-one'])) : '';
                    $two      = isset($_POST['mepr-address-two']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-two'])) : '';
                    $city     = isset($_POST['mepr-address-city']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-city'])) : '';
                    $state    = isset($_POST['mepr-address-state']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-state'])) : '';
                    $country  = isset($_POST['mepr-address-country']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-country'])) : '';
                    $postcode = isset($_POST['mepr-address-zip']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-zip'])) : '';
                    $street   = sprintf('%s %s', $one, $two);
                } else {
                    $country  = $this->address('country');
                    $state    = $this->address('state');
                    $postcode = $this->address('zip');
                    $city     = $this->address('city');
                    $street   = sprintf('%s %s', $this->address('one'), $this->address('two'));
                }
            }
        } elseif ($mepr_options->attr('tax_default_address') == 'none') {
            return new MeprTaxRate();
        }

        $user = $this;

        return MeprTaxRate::find_rate(compact('street', 'country', 'state', 'postcode', 'city', 'user', 'prd_id'));
    }

    /**
     * Calculate the tax for a subtotal.
     *
     * @param  float   $subtotal     The subtotal amount.
     * @param  integer $num_decimals The number of decimals.
     * @param  integer $prd_id       The product ID.
     * @return array
     */
    public function calculate_tax($subtotal, $num_decimals = 2, $prd_id = null)
    {
        $rate = $this->tax_rate($prd_id);

        // We assume that we're dealing with the subtotal.
        $tax_amount          = MeprUtils::format_float(($subtotal * ($rate->tax_rate / 100.00)), $num_decimals);
        $total               = MeprUtils::format_float(($subtotal + $tax_amount), $num_decimals);
        $tax_reversal_amount = 0.00;

        if ($rate->customer_type === 'business' && $rate->reversal) {
            if ($subtotal > 0) {
                $minimum_amount = MeprUtils::get_minimum_amount();

                if ($minimum_amount && $subtotal < $minimum_amount) {
                    $subtotal   = $minimum_amount;
                    $tax_amount = 0.00;
                }
            }

            $total               = MeprUtils::format_float($subtotal, $num_decimals);
            $tax_reversal_amount = $tax_amount;
            $tax_amount          = 0.00;
        }

        return [MeprUtils::format_float($total - $tax_amount), $total, $rate->tax_rate, $tax_amount, $rate->tax_desc, $rate->tax_class, $tax_reversal_amount];
    }

    /**
     * Calculate the subtotal from a total.
     *
     * @param  float            $total        The total amount.
     * @param  float|null       $percent      The tax percentage.
     * @param  integer          $num_decimals The number of decimals.
     * @param  MeprProduct|null $prd          The product object.
     * @return float
     */
    public function calculate_subtotal($total, $percent = null, $num_decimals = 2, $prd = null)
    {
        $prd_id = $prd instanceof MeprProduct ? $prd->ID : null;
        if (is_null($percent)) {
            $rate    = $this->tax_rate($prd_id);
            $percent = $rate->tax_rate;
        }

        if (! is_null($prd) && $prd->is_tax_exempt()) {
            $percent = 0;
        }

        return ($total / (1 + ($percent / 100)));
    }

    /**
     * Get the formatted address for the user.
     *
     * @return string
     */
    public function formatted_address()
    {
        $addr1   = get_user_meta($this->ID, 'mepr-address-one', true);
        $addr2   = get_user_meta($this->ID, 'mepr-address-two', true);
        $city    = get_user_meta($this->ID, 'mepr-address-city', true);
        $state   = get_user_meta($this->ID, 'mepr-address-state', true);
        $zip     = get_user_meta($this->ID, 'mepr-address-zip', true);
        $country = get_user_meta($this->ID, 'mepr-address-country', true);

        if (
            empty($addr1) or empty($city) or
            empty($state) or empty($zip)
        ) {
            return '';
        }

        $addr = $addr1;

        if ($addr2 and !empty($addr2)) {
            $addr .= "<br/>{$addr2}";
        }
        if ($country and !empty($country)) {
            $country = "<br/>{$country}";
        } else {
            $country = '';
        }

        $addr = sprintf(
            // Translators: %1$s: address line 1, %2$s: city, %3$s: state, %4$s: zip, %5$s: country.
            __('<br/>%1$s<br/>%2$s, %3$s %4$s%5$s<br/>', 'memberpress'),
            $addr,
            $city,
            $state,
            $zip,
            $country
        );

        return MeprHooks::apply_filters('mepr-user-formatted-address', $addr, $this);
    }

    /**
     * Get the formatted email for the user.
     *
     * @return string
     */
    public function formatted_email()
    {
        return str_replace(',', '', $this->full_name()) . " <{$this->user_email}>";
    }

    /**
     * Check if the account form is manually placed.
     *
     * @param  WP_Post $post The post object.
     * @return boolean
     */
    public static function manually_place_account_form($post)
    {
        return ($post instanceof WP_Post && ( preg_match('~\[mepr-account-form~', $post->post_content) || has_block('memberpress/pro-account-tabs') ) );
    }

    /**
     * Check if the page is an account page.
     *
     * @param  WP_Post $post The post object.
     * @return boolean
     */
    public static function is_account_page($post)
    {
        $mepr_options = MeprOptions::fetch();

        $is_account_page = (
        ($post instanceof WP_Post && $post->ID == $mepr_options->account_page_id) ||
        self::manually_place_account_form($post)
        );

        return MeprHooks::apply_filters('mepr_is_account_page', $is_account_page, $post);
    }

    /**
     * Check if the page is a login page.
     *
     * @param  WP_Post $post The post object.
     * @return boolean
     */
    public static function is_login_page($post)
    {
        $mepr_options = MeprOptions::fetch();
        return ($post instanceof WP_Post &&
            ($post->ID == $mepr_options->login_page_id ||
             preg_match('~\[mepr-login-form~', $post->post_content))
             || has_block('memberpress/pro-login-form'));
    }

    /**
     * Get custom profile values.
     *
     * @param  boolean $force_all Whether to force all fields.
     * @return array
     */
    public function custom_profile_values($force_all = false)
    {
        $fields = $this->custom_profile_fields($force_all);
        $values = [];

        foreach ($fields as $field) {
            $values[$field->field_key] = get_user_meta($this->ID, $field->field_key, true);
        }

        return $values;
    }

    /**
     * Get custom profile fields.
     *
     * @param  boolean $force_all Whether to force all fields.
     * @return array
     */
    public function custom_profile_fields($force_all = false)
    {
        global $wpdb;
        $mepr_options = MeprOptions::fetch();
        $slugs        = $rows = [];

        // If there's no custom fields why are we here?
        if (empty($mepr_options->custom_fields)) {
            return [];
        }

        // How many memberships have field customizations.
        $q = "
      SELECT COUNT(*)
        FROM {$wpdb->postmeta} AS pm
       WHERE pm.meta_key = %s
         AND pm.meta_value = %s
         AND pm.post_id IN (
               SELECT p.ID
                 FROM {$wpdb->posts} AS p
                WHERE p.post_status = %s
                  AND p.post_type = %s
             )
    ";

        $q = $wpdb->prepare($q, MeprProduct::$customize_profile_fields_str, '1', 'publish', MeprProduct::$cpt);

        $count = $wpdb->get_var($q);

        // If force all fields enabled, or no memberships have customized fields, just return the MeprOptions->custom_fields.
        if (MeprHooks::apply_filters('mepr_show_all_custom_fields_on_account', $force_all) || empty($count)) {
            return $mepr_options->custom_fields;
        }

        // If the user hasn't purchased anything, and at least one membership has customized fields just show all fields.
        $prods = $this->active_product_subscriptions('products');
        $prods = array_unique($prods);

        if (empty($prods)) {
            return [];
        }

        // Loop through the memberships and get a unique array of slugs.
        foreach ($prods as $p) {
            if ($p->customize_profile_fields) {
                $slugs = array_merge($slugs, $p->custom_profile_fields);
            } else {
                // If at least one membership has customized fields disabled, just return all the fields.
                return $mepr_options->custom_fields;
            }
        }

        // Not sure why this would happen, but if it does let's return an empty array.
        if (empty($slugs)) {
            return [];
        }

        // Array unique the slugs for fun (SORT_STRING requires php 5.2.9+).
        $slugs = array_unique($slugs, SORT_STRING);

        // Pull in the fields that are actually called out in the slugs array.
        foreach ($mepr_options->custom_fields as $row) {
            if (in_array($row->field_key, $slugs)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Set the password for the user.
     * We have to bypass the magic attribute for this since it's a special property.
     *
     * @param  string $password The password.
     * @return void
     */
    public function set_password($password)
    {
        $this->rec->user_pass = $password;
    }

    /**
     * Update the transaction meta for the user.
     * No longer used - we should verify that this isn't used anywhere in MP or our add-ons
     * before deleting this function
     *
     * @return void
     */
    public function update_txn_meta()
    {
        $latest_txn = $this->latest_txn;
        if ($latest_txn != false && $latest_txn instanceof MeprTransaction && $latest_txn->id > 0) {
            update_user_meta($this->ID, 'mepr_latest_txn_date', $latest_txn->created_at);
        } else {
            update_user_meta($this->ID, 'mepr_latest_txn_date', false);
        }

        update_user_meta($this->ID, 'mepr_txn_count', $this->txn_count);
        update_user_meta($this->ID, 'mepr_active_txn_count', $this->active_txn_count);
        update_user_meta($this->ID, 'mepr_expired_txn_count', $this->expired_txn_count);
        update_user_meta($this->ID, 'mepr_total_spent', $this->total_spent);
        update_user_meta($this->ID, 'mepr_memberships', $this->memberships);
    }

    /**
     * List users in a table.
     *
     * @param  string     $order_by       The order by clause.
     * @param  string     $order          The order direction.
     * @param  string     $paged          The paged parameter.
     * @param  string     $search         The search term.
     * @param  string     $search_field   The search field.
     * @param  integer    $perpage        The number of items per page.
     * @param  array|null $params         The additional parameters.
     * @param  boolean    $include_fields Whether to include custom fields.
     * @return array
     */
    public static function list_table(
        $order_by = '',
        $order = '',
        $paged = '',
        $search = '',
        $search_field = 'any',
        $perpage = 10,
        $params = null,
        $include_fields = false
    ) {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        if (is_null($params)) {
            $params = $_GET;
        }

        $mepr_options = MeprOptions::fetch();

        if (empty($order_by)) {
            $order_by = 'registered';
            $order    = 'DESC';
        }

        $cols = [
            'ID'                   => 'u.ID',
            'username'             => 'u.user_login',
            'email'                => 'u.user_email',
            'name'                 => "CONCAT(pm_last_name.meta_value, ', ', pm_first_name.meta_value)",
            'first_name'           => 'pm_first_name.meta_value',
            'last_name'            => 'pm_last_name.meta_value',
            'txn_count'            => 'IFNULL(m.txn_count,0)',
            'active_txn_count'     => 'IFNULL(m.active_txn_count,0)',
            'expired_txn_count'    => 'IFNULL(m.expired_txn_count,0)',
            'trial_txn_count'      => 'IFNULL(m.trial_txn_count,0)',
            'sub_count'            => 'IFNULL(m.sub_count,0)',
            'active_sub_count'     => 'IFNULL(m.active_sub_count,0)',
            'pending_sub_count'    => 'IFNULL(m.pending_sub_count,0)',
            'suspended_sub_count'  => 'IFNULL(m.suspended_sub_count,0)',
            'cancelled_sub_count'  => 'IFNULL(m.cancelled_sub_count,0)',
            'latest_txn_date'      => 'IFNULL(latest_txn.created_at,NULL)',
            'first_txn_date'       => 'IFNULL(first_txn.created_at,NULL)',
            'status'               => "CASE WHEN active_txn_count>0 THEN 'active' WHEN trial_txn_count>0 THEN 'active' WHEN expired_txn_count>0 THEN 'expired' ELSE 'none' END",
            'memberships'          => "IFNULL(m.memberships,'')",
            'inactive_memberships' => "IFNULL(m.inactive_memberships,'')",
            'last_login_date'      => 'IFNULL(last_login.created_at, NULL)',
            'login_count'          => 'IFNULL(m.login_count,0)',
            'total_spent'          => 'IFNULL(m.total_spent,0.00)',
            'registered'           => 'u.user_registered',
        ];

        $args = [];

        if (is_multisite()) {
            // $blog_id = get_current_blog_id();
            // $blog_user_ids = get_users(array('blog_id'=>$blog_id,'fields'=>'ID'));
            // $args[] = 'u.ID IN (' . implode(',',$blog_user_ids) . ')';
            $args[] = $wpdb->prepare(
                "
          (SELECT COUNT(*)
             FROM {$wpdb->usermeta} AS um_cap
            WHERE um_cap.user_id=u.ID
              AND um_cap.meta_key=%s) > 0
        ",
                $wpdb->get_blog_prefix() . 'user_level'
            );
        }

        if (isset($params['month']) && is_numeric($params['month'])) {
            $args[] = $wpdb->prepare('MONTH(u.user_registered) = %s', $params['month']);
        }

        if (isset($params['day']) && is_numeric($params['day'])) {
            $args[] = $wpdb->prepare('DAY(u.user_registered) = %s', $params['day']);
        }

        if (isset($params['year']) && is_numeric($params['year'])) {
            $args[] = $wpdb->prepare('YEAR(u.user_registered) = %s', $params['year']);
        }

        if (isset($params['status']) && $params['status'] != 'all') {
            if ($params['status'] == 'active') {
                $args[] = '(m.active_txn_count > 0 OR m.trial_txn_count > 0)';
            } elseif ($params['status'] == 'inactive') {
                $args[] = 'm.active_txn_count <= 0';
                $args[] = 'm.expired_txn_count > 0';
                $args[] = 'm.trial_txn_count <= 0';
            } elseif ($params['status'] == 'expired') {
                $args[] = "m.inactive_memberships <> ''";  // $args[] = 'm.expired_txn_count > 0'; Does not work here, will pull all members because active subscriptions will have expired transactions.
            } elseif ($params['status'] == 'none') {
                $args[] = 'm.active_txn_count <= 0';
                $args[] = 'm.expired_txn_count <= 0';
                $args[] = 'm.trial_txn_count <= 0';
            }
        }

        if (isset($params['membership']) && !empty($params['membership']) && is_numeric($params['membership'])) {
            // $args[] = $wpdb->prepare("%s IN (m.memberships)",$params['membership']);
            if (isset($params['status']) && $params['status'] != 'all') {
                if ($params['status'] == 'active') {
                    // Search in active memberships only.
                    $args[] = $wpdb->prepare("m.memberships RLIKE '(^|,)%d(,|$)'", $params['membership']);
                } elseif ($params['status'] == 'expired' || $params['status'] == 'inactive') {
                    // Search in inactive memberships only.
                    $args[] = $wpdb->prepare("m.inactive_memberships RLIKE '(^|,)%d(,|$)'", $params['membership']);
                }
            } else {
                // Search in both.
                $args[] = $wpdb->prepare("(m.memberships RLIKE '(^|,)%d(,|$)' OR m.inactive_memberships RLIKE '(^|,)%d(,|$)')", $params['membership'], $params['membership']);
            }
        }

        if (isset($params['prd_id']) && !empty($params['prd_id']) && is_numeric($params['prd_id'])) {
            // $args[] = $wpdb->prepare("%s IN (m.memberships)",$params['prd_id']);
            if (isset($params['status']) && $params['status'] != 'all') {
                if ($params['status'] == 'active') {
                    // Search in active memberships only.
                    $args[] = $wpdb->prepare("m.memberships RLIKE '(^|,)%d(,|$)'", $params['prd_id']);
                } elseif ($params['status'] == 'expired' || $params['status'] == 'inactive') {
                    // Search in inactive memberships only.
                    $args[] = $wpdb->prepare("m.inactive_memberships RLIKE '(^|,)%d(,|$)'", $params['prd_id']);
                }
            } else {
                // Search in both.
                $args[] = $wpdb->prepare("(m.memberships RLIKE '(^|,)%d(,|$)' OR m.inactive_memberships RLIKE '(^|,)%d(,|$)')", $params['prd_id'], $params['prd_id']);
            }
        }

        $joins = [
            "LEFT JOIN {$wpdb->usermeta} AS pm_first_name ON pm_first_name.user_id = u.ID AND pm_first_name.meta_key='first_name'",
            "LEFT JOIN {$wpdb->usermeta} AS pm_last_name ON pm_last_name.user_id = u.ID AND pm_last_name.meta_key='last_name'",
            "/* IMPORTANT */ JOIN {$mepr_db->members} AS m ON m.user_id=u.ID",
            "LEFT JOIN {$mepr_db->transactions} AS first_txn ON m.first_txn_id=first_txn.id",
            "LEFT JOIN {$mepr_db->transactions} AS latest_txn ON m.latest_txn_id=latest_txn.id",
            "LEFT JOIN {$mepr_db->events} AS last_login ON m.last_login_id=last_login.id",
        ];

        // Include custom fields in results?
        if ($include_fields) {
            $custom_fields = array_merge($mepr_options->address_fields, $mepr_options->custom_fields);

            foreach ($custom_fields as $i => $field) {
                $col                     = "pm_col_{$i}";
                $cols[$field->field_key] = $wpdb->prepare(
                    "
            IFNULL(
              (
                SELECT GROUP_CONCAT({$col}.meta_value)
                  FROM {$wpdb->usermeta} AS {$col}
                 WHERE {$col}.meta_key=%s
                   AND {$col}.user_id=u.ID
                 GROUP BY {$col}.user_id
              ),
              ''
            )
          ",
                    $field->field_key
                );
            }

            if (get_option('mepr_calculate_taxes') && get_option('mepr_vat_enabled')) {
                $vat_id_keys = $vat_id_keys = ['mepr_vat_customer_type', 'mepr_vat_number'];
                foreach ($vat_id_keys as $vat_id_key) {
                    $cols[$vat_id_key] = $wpdb->prepare(
                        "
            IFNULL(
              (
                SELECT meta_value
                FROM {$wpdb->usermeta}
                WHERE meta_key = %s
                AND user_id = u.ID
                LIMIT 1
              ),
              ''
            )
            ",
                        $vat_id_key
                    );
                }
            }

            $stripe_customer_id_keys = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
                $wpdb->esc_like('_mepr_stripe_customer_id_') . '%'
            ));

            if (is_array($stripe_customer_id_keys)) {
                foreach ($stripe_customer_id_keys as $stripe_customer_id_key) {
                    $cols[$stripe_customer_id_key] = $wpdb->prepare(
                        "
            IFNULL(
              (
                SELECT meta_value
                FROM {$wpdb->usermeta}
                WHERE meta_key = %s
                AND user_id = u.ID
                LIMIT 1
              ),
              ''
            )
            ",
                        $stripe_customer_id_key
                    );
                }
            }
        }

        return MeprDb::list_table($cols, "{$wpdb->users} AS u", $joins, $args, $order_by, $order, $paged, $search, $search_field, $perpage); // , false, true);
    }

    /*****
     * MAGIC METHOD HANDLERS
     *****/

    /**
     * Magic Method: Get the first transaction for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return MeprTransaction|false
     */
    protected function mgm_first_txn($mgm, $val = '')
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();
        $where   = '';

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status IN (%s, %s)
             ORDER BY t.created_at ASC
             LIMIT 1
          ",
                    $this->rec->ID,
                    MeprTransaction::$complete_str,
                    MeprTransaction::$confirmed_str
                );

                $id = $wpdb->get_var($q);
                return empty($id) ? false : new MeprTransaction($id);
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the latest transaction for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return MeprTransaction|false
     */
    protected function mgm_latest_txn($mgm, $val = '')
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status IN (%s, %s)
             ORDER BY t.created_at DESC
             LIMIT 1
          ",
                    $this->rec->ID,
                    MeprTransaction::$complete_str,
                    MeprTransaction::$confirmed_str
                );

                $id = $wpdb->get_var($q);
                return empty($id) ? false : new MeprTransaction($id);
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the number of transactions for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return integer
     */
    protected function mgm_txn_count($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT COUNT(*)
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id=%d
               AND t.status IN (%s,%s)
          ",
                    $this->rec->ID,
                    MeprTransaction::$complete_str,
                    MeprTransaction::$confirmed_str
                );

                return $wpdb->get_var($q);
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the number of trial transactions for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return integer
     */
    protected function mgm_trial_txn_count($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                return $wpdb->get_var($wpdb->prepare(
                    "(
            SELECT COUNT(*)
              FROM {$mepr_db->transactions} AS t
              JOIN {$mepr_db->subscriptions} AS sub
                ON t.subscription_id = sub.id
             WHERE t.user_id = %d
               AND t.txn_type = %s
               AND (
                 t.expires_at IS NULL
                 OR t.expires_at = %s
                 OR t.expires_at > %s
               )
               AND sub.trial IS TRUE
               AND sub.trial_amount = 0.00
          )",
                    $this->rec->ID,
                    MeprTransaction::$subscription_confirmation_str,
                    MeprUtils::db_lifetime(),
                    MeprUtils::db_now()
                ));
            default:
                return 0;
        }
    }

    /**
     * Magic Method: Get the number of active transactions for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return integer
     */
    protected function mgm_active_txn_count($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT COUNT(*)
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id=%d
               AND t.status IN (%s,%s)
               AND (
                 t.expires_at IS NULL
                 OR t.expires_at = %s
                 OR t.expires_at > %s
               )
          ",
                    $this->rec->ID,
                    MeprTransaction::$complete_str,
                    MeprTransaction::$confirmed_str,
                    MeprUtils::db_lifetime(),
                    MeprUtils::db_now()
                );

                return $wpdb->get_var($q);
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the number of expired transactions for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return integer
     */
    protected function mgm_expired_txn_count($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT COUNT(*)
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status IN (%s,%s)
               AND ( (
                   t.expires_at IS NOT NULL
                   AND t.expires_at <> %s
                   AND t.expires_at < %s
                 )
               )
          ",
                    $this->rec->ID,
                    MeprTransaction::$complete_str,
                    MeprTransaction::$confirmed_str,
                    MeprUtils::db_lifetime(),
                    MeprUtils::db_now()
                );

                return $wpdb->get_var($q);
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the total amount spent by the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return float
     */
    protected function mgm_total_spent($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT sum(t.total)
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status IN (%s,%s)
          ",
                    $this->rec->ID,
                    MeprTransaction::$complete_str,
                    MeprTransaction::$confirmed_str
                );

                return MeprUtils::format_float($wpdb->get_var($q));
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the number of confirmations for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return integer
     */
    protected function mgm_confirmations($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status = %s
          ",
                    $this->rec->ID,
                    MeprTransaction::$confirmed_str
                );

                $ids = $wpdb->get_col($q);

                if (empty($ids)) {
                      return false;
                }

                $txns = [];
                foreach ($ids as $id) {
                    $txns[] = new MeprTransaction($id);
                }

                return $txns;
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the payments for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return array
     */
    protected function mgm_payments($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status = %s
               AND t.amount > 0
          ",
                    $this->rec->ID,
                    MeprTransaction::$complete_str
                );

                $ids = $wpdb->get_col($q);

                if (empty($ids)) {
                      return false;
                }

                $txns = [];
                foreach ($ids as $id) {
                    $txns[] = new MeprTransaction($id);
                }

                return $txns;
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the transactions for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return array
     */
    protected function mgm_transactions($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
          ",
                    $this->rec->ID
                );

                $ids = $wpdb->get_col($q);

                if (empty($ids)) {
                      return false;
                }

                $txns = [];
                foreach ($ids as $id) {
                    $txns[] = new MeprTransaction($id);
                }

                return $txns;
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the refunds for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return array
     */
    protected function mgm_refunds($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status = %s
          ",
                    $this->rec->ID,
                    MeprTransaction::$refunded_str
                );

                $ids = $wpdb->get_col($q);

                if (empty($ids)) {
                      return false;
                }

                $txns = [];
                foreach ($ids as $id) {
                    $txns[] = new MeprTransaction($id);
                }

                return $txns;
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the pending payments for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return array
     */
    protected function mgm_pending_payments($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status = %s
          ",
                    $this->rec->ID,
                    MeprTransaction::$pending_str
                );

                $ids = $wpdb->get_col($q);

                if (empty($ids)) {
                      return false;
                }

                $txns = [];
                foreach ($ids as $id) {
                    $txns[] = new MeprTransaction($id);
                }

                return $txns;
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the failed payments for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return array
     */
    protected function mgm_failed_payments($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status = %s
          ",
                    $this->rec->ID,
                    MeprTransaction::$failed_str
                );

                $ids = $wpdb->get_col($q);

                if (empty($ids)) {
                      return false;
                }

                $txns = [];
                foreach ($ids as $id) {
                    $txns[] = new MeprTransaction($id);
                }

                return $txns;
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the memberships for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return array
     */
    protected function mgm_memberships($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT DISTINCT t.product_id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND (
                 t.expires_at IS NULL
                 OR t.expires_at = %s
                 OR t.expires_at > %s
               )
               AND ( (
                   t.txn_type IN (%s,%s,%s,%s)
                   AND t.status=%s
                 ) OR (
                   t.txn_type=%s
                   AND t.status=%s
                 )
               )
          ",
                    $this->rec->ID,
                    MeprUtils::db_lifetime(),
                    MeprUtils::db_now(),
                    MeprTransaction::$payment_str,
                    MeprTransaction::$sub_account_str,
                    MeprTransaction::$woo_txn_str,
                    MeprTransaction::$fallback_str,
                    MeprTransaction::$complete_str,
                    MeprTransaction::$subscription_confirmation_str,
                    MeprTransaction::$confirmed_str
                );

                $ids = $wpdb->get_col($q);

                if (empty($ids)) {
                      return false;
                }

                $memberships = [];
                foreach ($ids as $id) {
                    $memberships[] = new MeprProduct($id);
                }

                return $memberships;
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the logins for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return array
     */
    protected function mgm_logins($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT e.id
              FROM {$mepr_db->events} AS e
             WHERE e.evt_id=%d
               AND e.evt_id_type=%s
               AND e.event=%s
          ",
                    $this->rec->ID,
                    MeprEvent::$users_str,
                    MeprEvent::$login_event_str
                );

                $ids = $wpdb->get_col($q);

                if (empty($ids)) {
                      return false;
                }

                $logins = [];
                foreach ($ids as $id) {
                    $logins[] = new MeprEvent($id);
                }

                return $logins;
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the last login for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return MeprEvent|false
     */
    protected function mgm_last_login($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT e.id
              FROM {$mepr_db->events} AS e
             WHERE e.evt_id=%d
               AND e.evt_id_type=%s
               AND e.event=%s
             ORDER BY e.created_at DESC
             LIMIT 1
          ",
                    $this->rec->ID,
                    MeprEvent::$users_str,
                    MeprEvent::$login_event_str
                );

                $eid = $wpdb->get_var($q);
                return !empty($eid) ? new MeprEvent($eid) : false;
            default:
                return false;
        }
    }

    /**
     * Magic Method: Get the login count for the user.
     *
     * @param  string $mgm The magic method.
     * @param  string $val The value.
     * @return integer
     */
    protected function mgm_login_count($mgm, $val = '')
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        switch ($mgm) {
            case 'get':
                $q = $wpdb->prepare(
                    "
            SELECT COUNT(*)
              FROM {$mepr_db->events} AS e
             WHERE e.evt_id=%d
               AND e.evt_id_type=%s
               AND e.event=%s
          ",
                    $this->rec->ID,
                    MeprEvent::$users_str,
                    MeprEvent::$login_event_str
                );

                return $wpdb->get_var($q);
            default:
                return false;
        }
    }

    /*
     *  MEMBER DATA METHODS
        Member Data is statically stored, dynamic data which is acquired by utilizing the member_data static
        method. This will run some moderately expensive queries which will be cached in the members table
        so that the expensive queries can be run once, at the point when individual members are updated.
        Utilizing this approach reduces the strain on the server and increases performance because these
        queries are only run once when a user is updated and are usually only run for one member at a time.
     */

    /**
     * Get member data.
     *
     * Member Data is statically stored, dynamic data which is acquired by utilizing the member_data static
     * method. This will run some moderately expensive queries which will be cached in the members table
     * so that the expensive queries can be run once, at the point when individual members are updated.
     * Utilizing this approach reduces the strain on the server and increases performance because these
     * queries are only run once when a user is updated and are usually only run for one member at a time.
     *
     * @param  integer|null $u    User ID to get data for. If null, returns data for all users.
     * @param  array        $cols Specific columns to fetch. Empty array means all columns.
     * @return object|array       Member data object for a single user or array of objects for multiple users.
     */
    public static function member_data($u = null, array $cols = [])
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $select_cols = [];

        // Empty cols indicates we're getting all columns.
        if (empty($cols) || in_array('first_txn_id', $cols)) {
            $select_cols['first_txn_id'] = self::member_col_first_txn_id();
        }
        if (empty($cols) || in_array('latest_txn_id', $cols)) {
            $select_cols['latest_txn_id'] = self::member_col_latest_txn_id();
        }
        if (empty($cols) || in_array('txn_count', $cols)) {
            $select_cols['txn_count'] = self::member_col_txn_count();
        }
        if (empty($cols) || in_array('expired_txn_count', $cols)) {
            $select_cols['expired_txn_count'] = self::member_col_expired_txn_count();
        }
        if (empty($cols) || in_array('active_txn_count', $cols)) {
            $select_cols['active_txn_count'] = self::member_col_active_txn_count();
        }
        if (empty($cols) || in_array('trial_txn_count', $cols)) {
            $select_cols['trial_txn_count'] = self::member_col_trial_txn_count();
        }
        if (empty($cols) || in_array('sub_count', $cols)) {
            $select_cols['sub_count'] = self::member_col_sub_count();
        }
        if (empty($cols) || in_array('pending_sub_count', $cols)) {
            $select_cols['pending_sub_count'] = self::member_col_sub_count(MeprSubscription::$pending_str);
        }
        if (empty($cols) || in_array('active_sub_count', $cols)) {
            $select_cols['active_sub_count'] = self::member_col_sub_count(MeprSubscription::$active_str);
        }
        if (empty($cols) || in_array('suspended_sub_count', $cols)) {
            $select_cols['suspended_sub_count'] = self::member_col_sub_count(MeprSubscription::$suspended_str);
        }
        if (empty($cols) || in_array('cancelled_sub_count', $cols)) {
            $select_cols['cancelled_sub_count'] = self::member_col_sub_count(MeprSubscription::$cancelled_str);
        }
        if (empty($cols) || in_array('memberships', $cols)) {
            $select_cols['memberships'] = self::member_col_memberships();
        }
        if (empty($cols) || in_array('inactive_memberships', $cols)) {
            $select_cols['inactive_memberships'] = self::member_col_inactive_memberships();
        }
        if (empty($cols) || in_array('last_login_id', $cols)) {
            $select_cols['last_login_id'] = self::member_col_last_login_id();
        }
        if (empty($cols) || in_array('login_count', $cols)) {
            $select_cols['login_count'] = self::member_col_login_count();
        }
        if (empty($cols) || in_array('total_spent', $cols)) {
            $select_cols['total_spent'] = self::member_col_total_spent();
        }

        $selects = '';
        foreach ($select_cols as $col_name => $col_query) {
            $selects .= "\n{$col_query} AS {$col_name},";
        }
        // $selects = rtrim($selects, ',');
        $where = self::get_member_where($u);

        $q = $wpdb->prepare(
            "
        SELECT
          u.ID AS user_id,
          {$selects}
          %s AS updated_at
        FROM {$wpdb->users} AS u
        {$where}
      ",
            MeprUtils::db_now()
        );

        if (!is_null($u)) {
            $q .= '
        LIMIT 1
      ';

            $data = $wpdb->get_row($q);
        } else {
            $data = $wpdb->get_results($q);
        }


        $active_memberships = [];
        if (isset($data->memberships)) {
            $active_memberships = explode(',', str_replace('|', ',', $data->memberships));
        }
        $inactive_memberships = [];
        if (isset($data->inactive_memberships)) {
            $inactive_memberships = explode(',', str_replace('|', ',', $data->inactive_memberships));
        }

        if (!empty($active_memberships) && !empty($inactive_memberships)) {
            foreach ($inactive_memberships as $key => $id) {
                if (in_array($id, $active_memberships)) {
                    unset($inactive_memberships[$key]);
                }
            }

            if (empty($inactive_memberships)) {
                $data->inactive_memberships = '';
            } else {
                $data->inactive_memberships = implode(',', $inactive_memberships);
            }
        }

        return $data;
    }

    /***
     * SQL FOR MEMBER COLUMNS
     ***/
    private static function member_col_first_txn_id()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        return $wpdb->prepare(
            "(
        SELECT t.id
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id = u.ID
           AND t.status = %s
         ORDER BY t.created_at ASC
         LIMIT 1
      )",
            MeprTransaction::$complete_str
        );
    }

    /**
     * SQL for the latest transaction ID for the user.
     *
     * @return string
     */
    private static function member_col_latest_txn_id()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        return $wpdb->prepare(
            "(
        SELECT t.id
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id = u.ID
           AND t.status = %s
         ORDER BY t.created_at DESC
         LIMIT 1
      )",
            MeprTransaction::$complete_str
        );
    }

    /**
     * SQL for the number of transactions for the user.
     *
     * @return string
     */
    private static function member_col_txn_count()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        return $wpdb->prepare(
            "(
        SELECT COUNT(*)
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id=u.ID
           AND t.txn_type IN (%s,%s,%s)
      )",
            MeprTransaction::$payment_str,
            MeprTransaction::$sub_account_str,
            MeprTransaction::$woo_txn_str
        );
    }

    /**
     * SQL for the number of expired transactions for the user.
     *
     * @return string
     */
    private static function member_col_expired_txn_count()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        return $wpdb->prepare(
            "(
        SELECT COUNT(*)
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id = u.ID
           AND t.status = %s
           AND t.txn_type IN (%s,%s,%s)
           AND ( (
               t.expires_at IS NOT NULL
               AND t.expires_at <> %s
               AND t.expires_at < %s
             )
           )
      )",
            MeprTransaction::$complete_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$sub_account_str,
            MeprTransaction::$woo_txn_str,
            MeprUtils::db_lifetime(),
            MeprUtils::db_now()
        );
    }

    /**
     * SQL for the number of active transactions for the user.
     *
     * @return string
     */
    private static function member_col_active_txn_count()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        return $wpdb->prepare(
            "(
        SELECT COUNT(*)
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id=u.ID
           AND t.status = %s
           AND t.txn_type IN (%s,%s,%s)
           AND (
             t.expires_at IS NULL
             OR t.expires_at = %s
             OR t.expires_at > %s
           )
      )",
            MeprTransaction::$complete_str,
            MeprTransaction::$payment_str,
            MeprTransaction::$sub_account_str,
            MeprTransaction::$woo_txn_str,
            MeprUtils::db_lifetime(),
            MeprUtils::db_now()
        );
    }

    /**
     * SQL for the number of trial transactions for the user.
     *
     * @return string
     */
    private static function member_col_trial_txn_count()
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        return $wpdb->prepare(
            "(
        SELECT COUNT(*)
          FROM {$mepr_db->transactions} AS t
          JOIN {$mepr_db->subscriptions} AS sub
            ON t.subscription_id = sub.id
         WHERE t.user_id=u.ID
           AND t.txn_type = %s
           AND (
             t.expires_at IS NULL
             OR t.expires_at = %s
             OR t.expires_at > %s
           )
           AND sub.trial IS TRUE
           AND sub.trial_amount = 0.00
      )",
            MeprTransaction::$subscription_confirmation_str,
            MeprUtils::db_lifetime(),
            MeprUtils::db_now()
        );
    }

    /**
     * SQL for the number of subscriptions for the user.
     *
     * @param  string|null $status The status of the subscriptions to count.
     * @return string
     */
    private static function member_col_sub_count($status = null)
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $where_status = (empty($status) ? '' : $wpdb->prepare('AND s.status=%s', $status));

        return "(
      SELECT COUNT(*)
        FROM {$mepr_db->subscriptions} AS s
       WHERE s.user_id=u.ID
       {$where_status}
    )";
    }

    /**
     * SQL for the number of inactive memberships for the user.
     *
     * @return string
     */
    private static function member_col_inactive_memberships()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        return $wpdb->prepare(
            "(
        SELECT GROUP_CONCAT(
                 DISTINCT t.product_id
                 ORDER BY t.product_id
                 SEPARATOR ','
               )
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id = u.ID
           AND (
             t.expires_at < %s
             AND t.expires_at <> %s
           )
           AND (
                t.txn_type IN (%s,%s)
                AND t.status=%s
           )
      )",
            MeprUtils::db_now(),
            MeprUtils::db_lifetime(),
            MeprTransaction::$payment_str,
            MeprTransaction::$sub_account_str,
            MeprTransaction::$complete_str
        );
    }

    /**
     * SQL for the memberships for the user.
     *
     * @return string
     */
    private static function member_col_memberships()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        return $wpdb->prepare(
            "(
        SELECT GROUP_CONCAT(
                 DISTINCT t.product_id
                 ORDER BY t.product_id
                 SEPARATOR ','
               )
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id = u.ID
           AND (
             t.expires_at > %s
             OR t.expires_at = %s
             OR t.expires_at IS NULL
           )
           AND ( (
                t.txn_type IN (%s,%s,%s,%s)
                AND t.status=%s
             ) OR (
                t.txn_type=%s
                AND t.status=%s
             )
           )
      )",
            MeprUtils::db_now(),
            MeprUtils::db_lifetime(),
            MeprTransaction::$payment_str,
            MeprTransaction::$sub_account_str,
            MeprTransaction::$woo_txn_str,
            MeprTransaction::$fallback_str,
            MeprTransaction::$complete_str,
            MeprTransaction::$subscription_confirmation_str,
            MeprTransaction::$confirmed_str
        );
    }

    /**
     * SQL for the last login ID for the user.
     *
     * @return string
     */
    private static function member_col_last_login_id()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        return $wpdb->prepare(
            "(
        SELECT e.id
          FROM {$mepr_db->events} AS e
         WHERE e.evt_id=u.ID
           AND e.evt_id_type=%s
           AND e.event=%s
         ORDER BY e.created_at DESC
         LIMIT 1
      )",
            MeprEvent::$users_str,
            MeprEvent::$login_event_str
        );
    }

    /**
     * SQL for the login count for the user.
     *
     * @return string
     */
    private static function member_col_login_count()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        return $wpdb->prepare(
            "(
        SELECT COUNT(*)
          FROM {$mepr_db->events} AS e
         WHERE e.evt_id=u.ID
           AND e.evt_id_type=%s
           AND e.event=%s
      )",
            MeprEvent::$users_str,
            MeprEvent::$login_event_str
        );
    }

    /**
     * SQL for the total amount spent for the user.
     *
     * @return string
     */
    private static function member_col_total_spent()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        return $wpdb->prepare(
            "(
        SELECT sum(t.total)
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id=u.ID
           AND t.status IN (%s)
      )",
            MeprTransaction::$complete_str
        );
    }

    /**
     * Update the member data for this user.
     *
     * @param string[] $cols Restrict the update to these columns.
     */
    public function update_member_data($cols = [])
    {
        self::update_member_data_static($this->ID, $cols);
    }

    /**
     * Update the member data for the given user.
     *
     * @param integer  $user_id The ID of the user.
     * @param string[] $cols    Restrict the update to these columns.
     */
    public static function update_member_data_static($user_id, $cols = [])
    {
        if (empty($user_id)) {
            return;
        }

        $mepr_db = MeprDb::fetch();

        // Return if the `members` table does not exist (fix for multisite).
        if (!$mepr_db->table_exists($mepr_db->members)) {
            return;
        }

        $data = self::member_data($user_id, $cols);

        if (!empty($data) && is_object($data) && isset($data->user_id)) {
            $member = $mepr_db->get_one_record($mepr_db->members, ['user_id' => $data->user_id]);

            if (empty($member)) {
                $mepr_db->create_record($mepr_db->members, $data);
            } else {
                $mepr_db->update_record($mepr_db->members, $member->id, $data);
            }
        } else {
            $mepr_db->delete_records($mepr_db->members, ['user_id' => $user_id]);
        }
    }

    /**
     * Get the user IDs to update member data on.
     *
     * @param  boolean $exclude_already_updated Exclude members who have already been updated.
     * @param  integer $limit                   Limit the update to this number of members.
     * @return integer[]                        The array of user IDs.
     */
    public static function get_update_all_member_data_user_ids($exclude_already_updated = false, $limit = 0)
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();
        $where   = '';

        if (is_multisite()) {
            $where = $wpdb->prepare(
                "WHERE (SELECT COUNT(*)
                 FROM $wpdb->usermeta AS um_cap
                WHERE um_cap.user_id=ID
                  AND um_cap.meta_key=%s) > 0",
                $wpdb->get_blog_prefix() . 'user_level'
            );
        }

        if ($exclude_already_updated) {
            if (empty($where)) {
                $where = 'WHERE';
            } else {
                $where = "$where AND ";
            }

            $where = "$where ID NOT IN (SELECT user_id FROM $mepr_db->members)";
        }

        if (!empty($limit)) {
            $limit = "LIMIT $limit";
        }

        $user_ids = $wpdb->get_col("SELECT ID FROM $wpdb->users $where $limit");

        return array_map('intval', $user_ids);
    }

    /**
     * Update member data for all users.
     *
     * @param boolean  $exclude_already_updated Exclude members who have already been updated.
     * @param integer  $limit                   Limit the update to this number of members.
     * @param string[] $cols                    Restrict the update to these columns.
     */
    public static function update_all_member_data($exclude_already_updated = false, $limit = 0, $cols = [])
    {
        $user_ids = self::get_update_all_member_data_user_ids($exclude_already_updated, $limit);

        foreach ($user_ids as $user_id) {
            MeprUser::update_member_data_static($user_id, $cols);
        }
    }

    /**
     * Update all member data that was updated at least 1 day ago
     *
     * @param integer $limit The limit of users to update.
     */
    public static function update_existing_member_data($limit)
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $interval = '2 DAY';

        $ms_where = '';
        if (is_multisite()) {
            // $blog_id = get_current_blog_id();
            // $blog_user_ids = get_users(array('blog_id'=>$blog_id,'fields'=>'ID'));
            // $ms_where = 'm.user_id IN (' . implode(',',$blog_user_ids) . ') AND';
            $ms_where = $wpdb->prepare(
                "
          (SELECT COUNT(*)
             FROM {$wpdb->usermeta} AS um_cap
            WHERE um_cap.user_id=m.user_id
              AND um_cap.meta_key=%s) > 0
        ",
                $wpdb->get_blog_prefix() . 'user_level'
            );
        }

        $q = $wpdb->prepare(
            "
        SELECT m.user_id
          FROM {$mepr_db->members} AS m
         WHERE {$ms_where} (
                 m.updated_at IS NULL
                 OR m.updated_at = %s
                 OR m.updated_at <= DATE_SUB(%s,INTERVAL {$interval})
               )
         ORDER BY m.updated_at
         LIMIT %d
      ",
            MeprUtils::db_lifetime(),
            MeprUtils::db_now(),
            $limit
        );

        $uids = $wpdb->get_col($q);

        foreach ($uids as $uid) {
            $u     = new MeprUser();
            $u->ID = $uid;
            $u->update_member_data();
        }
    }

    /**
     * Delete the member data for the user.
     *
     * @return integer The number of rows deleted.
     */
    public function delete_member_data()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();
        $q       = $wpdb->prepare("DELETE FROM {$mepr_db->members} WHERE user_id=%s", $this->ID);
        return $wpdb->query($q);
    }

    /**
     * Delete all member data.
     *
     * @return integer The number of rows deleted.
     */
    public static function delete_all_member_data()
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();
        $q       = "DELETE FROM {$mepr_db->members}";
        return $wpdb->query($q);
    }

    /**
     * Get the where clause for the member data.
     *
     * @param  mixed  $u      The user or users to get the member data for.
     * @param  string $id_col The column to use for the user ID.
     * @return string The where clause.
     */
    private static function get_member_where($u = null, $id_col = 'u.ID')
    {
        global $wpdb;

        $where = '';

        if (!is_null($u)) {
            if (is_array($u)) {
                $uids  = implode(',', $u);
                $where = "
          WHERE {$id_col} IN ({$uids})
        ";
            } else {
                $where = $wpdb->prepare(
                    "
            WHERE {$id_col} = %d
          ",
                    $u
                );
            }
        }

        return $where;
    }

    /**
     * Get the Stripe Customer ID
     *
     * @param  string $gateway_id The gateway ID.
     * @return string|false
     */
    public function get_stripe_customer_id($gateway_id)
    {
        $mepr_options = MeprOptions::fetch();
        $meta_key     = sprintf('_mepr_stripe_customer_id_%s_%s', $gateway_id, $mepr_options->currency_code);

        return get_user_meta($this->ID, $meta_key, true);
    }

    /**
     * Set the Stripe Customer ID
     *
     * @param string $gateway_id  The gateway ID.
     * @param string $customer_id The Stripe Customer ID.
     */
    public function set_stripe_customer_id($gateway_id, $customer_id)
    {
        $mepr_options = MeprOptions::fetch();
        $meta_key     = sprintf('_mepr_stripe_customer_id_%s_%s', $gateway_id, $mepr_options->currency_code);

        update_user_meta($this->ID, $meta_key, $customer_id);
    }

    /**
     * Delete the Stripe Customer ID
     *
     * @param string $gateway_id  The gateway ID.
     * @param string $customer_id The Stripe Customer ID.
     */
    public static function delete_stripe_customer_id($gateway_id, $customer_id)
    {
        if (!is_string($customer_id) || $customer_id === '') {
            return;
        }

        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT umeta_id FROM {$wpdb->usermeta} WHERE meta_key LIKE %s AND meta_value = %s",
            $wpdb->esc_like('_mepr_stripe_customer_id_' . $gateway_id) . '%',
            $customer_id
        );

        $meta_ids = $wpdb->get_col($query);

        if (is_array($meta_ids) && count($meta_ids)) {
            foreach ($meta_ids as $meta_id) {
                delete_metadata_by_mid('user', $meta_id);
            }
        }
    }
}
