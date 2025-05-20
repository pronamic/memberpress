<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class MeprTransactionsTable extends WP_List_Table
{
    /**
     * The screen object for this table.
     *
     * @var WP_Screen
     */
    public $_screen;

    /**
     * The columns to display in the table.
     *
     * @var array
     */
    public $_columns;

    /**
     * The sortable columns for this table.
     *
     * @var array
     */
    public $_sortable;

    /**
     * The searchable fields for this table.
     *
     * @var array
     */
    public $_searchable;

    /**
     * The database column names to search.
     *
     * @var array
     */
    public $db_search_cols;

    /**
     * The total number of items.
     *
     * @var integer
     */
    public $totalitems;

    /**
     * Constructor for the MeprTransactionsTable class.
     *
     * @param string $screen  The screen to display the table on.
     * @param array  $columns The columns to display in the table.
     *
     * @return void
     */
    public function __construct($screen, $columns = [])
    {
        if (is_string($screen)) {
            $screen = convert_to_screen($screen);
        }

        $this->_screen = $screen;

        if (!empty($columns)) {
            $this->_columns = $columns;
        }

        $this->_searchable = [
            'txn'   => __('Transaction', 'memberpress'),
            'sub'   => __('Subscription', 'memberpress'),
            'user'  => __('Username', 'memberpress'),
            'email' => __('User Email', 'memberpress'),
            'id'    => __('Id', 'memberpress'),
        ];

        $this->db_search_cols = [
            'txn'   => 'tr.trans_num, ord.trans_num',
            'sub'   => 'sub.subscr_id',
            'user'  => 'm.user_login',
            'email' => 'm.user_email',
            'id'    => 'tr.id',
        ];

        parent::__construct(
            [
                'singular' => 'wp_list_mepr_transaction', // Singular label.
                'plural'   => 'wp_list_mepr_transactions', // Plural label, also this will be one of the table CSS class.
                'ajax'     => true, // It's false as we won't support Ajax for this table.
            ]
        );
    }

    /**
     * Get the column info.
     *
     * @return array The column info.
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
     * @param string $which The which.
     *
     * @return void
     */
    public function extra_tablenav($which)
    {
        if ($which == 'top') {
            $search_cols = $this->_searchable;

            MeprView::render('/admin/table_controls', get_defined_vars());
        }

        if ($which == 'bottom') {
            $action     = 'mepr_transactions';
            $totalitems = $this->totalitems;
            $itemcount  = count($this->items);
            MeprView::render('/admin/table_footer', compact('action', 'totalitems', 'itemcount'));
        }
    }

    /**
     * Get the columns.
     *
     * @return array The columns.
     */
    public function get_columns()
    {
        return $this->_columns;
    }

    /**
     * Get the sortable columns.
     *
     * @return array The sortable columns.
     */
    public function get_sortable_columns()
    {
        $cols = [
            'col_id'             => ['ID', true],
            'col_trans_num'      => ['trans_num', true],
            'col_subscr_id'      => ['subscr_id', true],
            'col_product'        => ['product_name', true],
            'col_net'            => ['amount', true],
            'col_tax'            => ['tax_amount', true],
            'col_total'          => ['total', true],
            'col_propername'     => ['last_name', true],
            'col_user_login'     => ['user_login', true],
            'col_status'         => ['status', true],
            'col_payment_system' => ['gateway', true],
            'col_created_at'     => ['created_at', true],
            'col_expires_at'     => ['expires_at', true],
        ];

        return MeprHooks::apply_filters('mepr-admin-transactions-sortable-cols', $cols);
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

            $perpage = !empty($option) ? get_user_meta($user_id, $option, true) : 10;
            $perpage = !empty($perpage) && !is_array($perpage) ? $perpage : 10;

            // Specifically for the CSV export to work properly.
            $_SERVER['QUERY_STRING'] = ( empty($_SERVER['QUERY_STRING']) ? '?' : "{$_SERVER['QUERY_STRING']}&" ) . "perpage={$perpage}";
        } else {
            $perpage = !empty($_GET['perpage']) ? esc_sql($_GET['perpage']) : 10;
        }

        $orderby      = !empty($_GET['orderby']) ? esc_sql($_GET['orderby']) : 'created_at';
        $order        = !empty($_GET['order'])   ? esc_sql($_GET['order'])   : 'DESC';
        $paged        = !empty($_GET['paged'])   ? esc_sql($_GET['paged'])   : 1;
        $search       = !empty($_GET['search'])  ? esc_sql($_GET['search'])  : '';
        $search_field = !empty($_GET['search-field'])  ? esc_sql($_GET['search-field'])  : 'any';
        $search_field = isset($this->db_search_cols[$search_field]) ? $this->db_search_cols[$search_field] : 'any';

        $list_table = MeprTransaction::list_table($orderby, $order, $paged, $search, $search_field, $perpage);
        $totalitems = $list_table['count'];

        // How many pages do we have in total?
        $totalpages = ceil($totalitems / $perpage);

        // -- Register the pagination --
        $this->set_pagination_args(
            [
                'total_items' => $totalitems,
                'total_pages' => $totalpages,
                'per_page'    => $perpage,
            ]
        );

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
        // Get the records registered in the prepare_items method.
        $records = $this->items;

        // Get the columns registered in the get_columns and get_sortable_columns methods.
        list( $columns, $hidden ) = $this->get_column_info();

        MeprView::render('/admin/transactions/row', get_defined_vars());
    }

    /**
     * Get the items.
     *
     * @return array The items.
     */
    public function get_items()
    {
        return $this->items;
    }
}
