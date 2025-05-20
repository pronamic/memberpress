<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class MeprSubscriptionsTable extends WP_List_Table
{
    /**
     * Flag indicating if this is a lifetime subscription table
     *
     * @var boolean
     */
    public $lifetime;

    /**
     * Count of recurring subscriptions
     *
     * @var integer
     */
    public $periodic_count;

    /**
     * Count of lifetime subscriptions
     *
     * @var integer
     */
    public $lifetime_count;

    /**
     * The current screen object
     *
     * @var WP_Screen
     */
    public $_screen;

    /**
     * The columns to be displayed in the table
     *
     * @var array
     */
    public $_columns;

    /**
     * The sortable columns
     *
     * @var array
     */
    public $_sortable;

    /**
     * Searchable fields for non-recurring subscriptions
     *
     * @var array
     */
    public $_non_recurring_searchable;

    /**
     * Searchable fields for recurring subscriptions
     *
     * @var array
     */
    public $_recurring_searchable;

    /**
     * Database columns for non-recurring subscription searches
     *
     * @var array
     */
    public $non_recurring_db_search_cols;

    /**
     * Database columns for recurring subscription searches
     *
     * @var array
     */
    public $recurring_db_search_cols;

    /**
     * Total number of items
     *
     * @var integer
     */
    public $totalitems;

    /**
     * Constructor.
     *
     * @param  string|WP_Screen $screen   The screen object or screen name.
     * @param  array            $columns  The columns to display in the table.
     * @param  boolean          $lifetime Whether this is a lifetime subscription table.
     * @return void
     */
    public function __construct($screen, $columns, $lifetime = false)
    {
        if (is_string($screen)) {
            $screen = convert_to_screen($screen);
        }

        $this->_screen = $screen;

        if (!empty($columns)) {
            $this->_columns = $columns;
        }

        $this->lifetime = $lifetime;

        if ($lifetime) {
            $label = 'wp_list_mepr_lifetime_subscription';
        } else {
            $label = 'wp_list_mepr_subscription';
        }

        $this->_recurring_searchable = [
            'subscription' => __('Subscription', 'memberpress'),
            'username'     => __('Username', 'memberpress'),
            'email'        => __('User Email', 'memberpress'),
            'id'           => __('Id', 'memberpress'),
        ];

        $this->_non_recurring_searchable = [
            'subscription' => __('Transaction', 'memberpress'),
            'username'     => __('Username', 'memberpress'),
            'email'        => __('User Email', 'memberpress'),
            'id'           => __('Id', 'memberpress'),
        ];

        $this->recurring_db_search_cols = [
            'subscription' => 'sub.subscr_id',
            'username'     => 'u.user_login',
            'email'        => 'u.user_email',
            'id'           => 'sub.id',
        ];

        $this->non_recurring_db_search_cols = [
            'subscription' => 'txn.trans_num',
            'username'     => 'u.user_login',
            'email'        => 'u.user_email',
            'id'           => 'txn.id',
        ];

        parent::__construct(
            [
                'singular' => $label, // Singular label.
                'plural'   => "{$label}s", // Plural label, also this well be one of the table css class.
                'ajax'     => true, // It is not false as we won't support Ajax for this table.
            ]
        );
    }

    /**
     * Get the column info.
     *
     * @return array Array of column information including columns, hidden columns, sortable columns, and primary column.
     */
    public function get_column_info()
    {
        $columns = get_column_headers($this->_screen);
        $hidden  = get_hidden_columns($this->_screen);

        // Bypass MeprHooks to call built-in filter.
        $sortable = apply_filters("manage_{$this->_screen->id}_sortable_columns", $this->get_sortable_columns());

        $primary = 'col_id';
        return [$columns, $hidden, $sortable, $primary];
    }

    /**
     * Extra table nav.
     *
     * @param  string $which The location of the extra table nav markup: 'top' or 'bottom'.
     * @return void
     */
    public function extra_tablenav($which)
    {
        if ($which == 'top') {
            $member       = (isset($_GET['member']) && !empty($_GET['member'])) ? '&member=' . urlencode(stripslashes($_GET['member'])) : '';
            $search       = (isset($_GET['search']) && !empty($_GET['search'])) ? '&search=' . urlencode(stripslashes($_GET['search'])) : '';
            $search_field = (isset($_GET['search-field']) && !empty($_GET['search-field'])) ? '&search-field=' . stripslashes($_GET['search-field']) : '';
            $perpage      = (isset($_GET['perpage']) && !empty($_GET['perpage'])) ? '&perpage=' . stripslashes($_GET['perpage']) : '';

            if ($this->lifetime) {
                $search_cols = $this->_non_recurring_searchable;
            } else {
                $search_cols = $this->_recurring_searchable;
            }

            $table = $this;
            MeprView::render('/admin/subscriptions/tabs', compact('table', 'member', 'search', 'search_field', 'perpage', 'search_cols'));
            MeprView::render('/admin/table_controls', compact('search_cols'));
        }

        if ($which == 'bottom') {
            if ($this->lifetime) {
                $action = 'mepr_lifetime_subscriptions';
            } else {
                $action = 'mepr_subscriptions';
            }

            $totalitems = $this->totalitems;
            $itemcount  = count($this->items);
            MeprView::render('/admin/table_footer', compact('action', 'totalitems', 'itemcount'));
        }
    }

    /**
     * Get the columns.
     *
     * @return array Array of column headers.
     */
    public function get_columns()
    {
        return $this->_columns;
    }

    /**
     * Get the sortable columns.
     *
     * @return array Array of sortable columns.
     */
    public function get_sortable_columns()
    {
        $prefix = $this->lifetime ? 'col_txn_' : 'col_';
        $cols   = [
            $prefix . 'created_at' => ['created_at', true],
            $prefix . 'id'         => ['ID', true],
            $prefix . 'member'     => ['member', true],
            $prefix . 'propername' => ['last_name', true],
            $prefix . 'product'    => ['product_name', true],
            $prefix . 'gateway'    => ['gateway', true],
            $prefix . 'subscr_id'  => ['subscr_id', true],
            $prefix . 'txn_count'  => ['txn_count', true],
            $prefix . 'expires_at' => ['expires_at', true],
            $prefix . 'ip_addr'    => ['ip_addr', true],
            $prefix . 'status'     => ['status', true],
            $prefix . 'active'     => ['active', true],
        ];

        if ($this->lifetime) {
            unset($cols[$prefix . 'txn_count']);
            unset($cols[$prefix . 'status']);
        }

        return MeprHooks::apply_filters('mepr-admin-subscriptions-sortable-cols', $cols, $prefix, $this->lifetime);
    }

    /**
     * Prepare the items.
     *
     * @return void
     */
    public function prepare_items()
    {
        $user_id = get_current_user_id();
        $screen  = get_current_screen();

        if (isset($screen) && is_object($screen)) {
            $option = $screen->get_option('per_page', 'option');

            $perpage = get_user_meta($user_id, $option, true);
            $perpage = !empty($perpage) && !is_array($perpage) ? $perpage : 10;

            // Specifically for the CSV export to work properly.
            $_SERVER['QUERY_STRING'] = ( empty($_SERVER['QUERY_STRING']) ? '?' : "{$_SERVER['QUERY_STRING']}&" ) . "perpage={$perpage}";
        } else {
            $perpage = !empty($_GET['perpage']) ? esc_sql($_GET['perpage']) : 10;
        }

        $orderby                    = !empty($_GET['orderby']) ? esc_sql($_GET['orderby']) : 'ID';
        $order                      = !empty($_GET['order'])   ? esc_sql($_GET['order'])   : 'DESC';
        $paged                      = !empty($_GET['paged'])   ? esc_sql($_GET['paged'])   : 1;
        $search                     = !empty($_GET['search'])  ? esc_sql($_GET['search'])  : '';
        $search_field               = !empty($_GET['search-field']) ? esc_sql($_GET['search-field'])  : 'any';
        $non_recurring_search_field = isset($this->non_recurring_db_search_cols[$search_field]) ? $this->non_recurring_db_search_cols[$search_field] : 'any';
        $recurring_search_field     = isset($this->recurring_db_search_cols[$search_field]) ? $this->recurring_db_search_cols[$search_field] : 'any';

        $lifetime_table = MeprSubscription::lifetime_subscr_table($orderby, $order, $paged, $search, $non_recurring_search_field, $perpage, (!$this->lifetime));
        $periodic_table = MeprSubscription::subscr_table($orderby, $order, $paged, $search, $recurring_search_field, $perpage, ($this->lifetime));

        $list_table = $this->lifetime ? $lifetime_table : $periodic_table;

        $this->periodic_count = $periodic_table['count'];
        $this->lifetime_count = $lifetime_table['count'];

        $totalitems = $list_table['count'];

        // How many pages do we have in total?
        $totalpages = ceil($totalitems / $perpage);

        // -- Register the pagination --
        $this->set_pagination_args([
            'total_items' => $totalitems,
            'total_pages' => $totalpages,
            'per_page'    => $perpage,
        ]);

        // -- Register the Columns --
        if (isset($screen) && is_object($screen)) {
            $this->_column_headers = $this->get_column_info();
        } else {
            // For CSV to work properly.
            $this->_column_headers = [
                $this->get_columns(),
                [],
                $this->get_sortable_columns(),
            ];
        }

        $this->totalitems = $totalitems;

        // -- Fetch the items --
        $this->items = $list_table['results'];
    }

    /**
     * Display the rows.
     *
     * @return void
     */
    public function display_rows()
    {
        $mepr_options = MeprOptions::fetch();

        // Get the records registered in the prepare_items method.
        $records = $this->items;

        // Get the columns registered in the get_columns and get_sortable_columns methods.
        list($columns, $hidden) = $this->get_column_info();

        $table = $this;
        MeprView::render('/admin/subscriptions/row', get_defined_vars());
    }

    /**
     * Get the items.
     *
     * @return array Array of table items.
     */
    public function get_items()
    {
        return $this->items;
    }
}
