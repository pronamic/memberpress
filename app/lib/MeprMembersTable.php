<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class MeprMembersTable extends WP_List_Table
{
    /**
     * Screen object.
     *
     * @var \WP_Screen
     */
    public $_screen;

    /**
     * Columns for the table.
     *
     * @var array
     */
    public $_columns;

    /**
     * Sortable columns.
     *
     * @var array
     */
    public $_sortable;

    /**
     * Searchable fields.
     *
     * @var array
     */
    public $_searchable;

    /**
     * Database search columns mapping.
     *
     * @var array
     */
    public $db_search_cols;

    /**
     * Total number of items.
     *
     * @var integer
     */
    public $totalitems;

    /**
     * Constructor.
     *
     * @param  string $screen  The screen.
     * @param  array  $columns The columns.
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
            'username'   => __('Username', 'memberpress'),
            'email'      => __('Email', 'memberpress'),
            'first_name' => __('First Name', 'memberpress'),
            'last_name'  => __('Last Name', 'memberpress'),
            'id'         => __('Id', 'memberpress'),
        ];

        $this->db_search_cols = [
            'username'   => 'u.user_login',
            'email'      => 'u.user_email',
            'last_name'  => 'pm_last_name.meta_value',
            'first_name' => 'pm_first_name.meta_value',
            'id'         => 'u.ID',
        ];

        parent::__construct(
            [
                'singular' => 'wp_list_mepr_members', // Singular label.
                'plural'   => 'wp_list_mepr_members', // Plural label, also this will be one of the table css class.
                'ajax'     => true, // It's false as we won't support Ajax for this table.
            ]
        );
    }

    /**
     * Get the column info.
     *
     * @return array
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
     * @param  string $which The which.
     * @return void
     */
    public function extra_tablenav($which)
    {
        if ($which == 'top') {
            $search_cols = $this->_searchable;
            MeprView::render('/admin/table_controls', compact('search_cols'));
        }

        if ($which == 'bottom') {
            $action     = 'mepr_members';
            $totalitems = $this->totalitems;
            $itemcount  = count($this->items);
            MeprView::render('/admin/table_footer', compact('action', 'totalitems', 'itemcount'));
        }
    }

    /**
     * Get the columns.
     *
     * @return array
     */
    public function get_columns()
    {
        return $this->_columns;
    }

    /**
     * Get the sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns()
    {
        return $sortable = [
            'col_id'              => ['ID',true],
            'col_username'        => ['username',true],
            'col_email'           => ['email',true],
            'col_status'          => ['status',true],
            'col_name'            => ['name',true],
            // 'col_txn_count' => array('m.txn_count',true),
            // 'col_expired_txn_count' => array('m.expired_txn_count',true),
            // 'col_active_txn_count'  => array('m.active_txn_count',true),
            // 'col_sub_count' => array('m.sub_count',true),
            'col_last_login_date' => ['last_login_date',true],
            'col_login_count'     => ['login_count',true],
            'col_total_spent'     => ['total_spent',true],
            'col_registered'      => ['registered',true],
        ];
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

        $orderby      = !empty($_GET['orderby']) ? esc_sql($_GET['orderby']) : 'registered';
        $order        = !empty($_GET['order'])   ? esc_sql($_GET['order'])   : 'DESC';
        $paged        = !empty($_GET['paged'])   ? esc_sql($_GET['paged'])   : 1;
        $search       = !empty($_GET['search'])  ? esc_sql($_GET['search'])  : '';
        $search_field = !empty($_GET['search-field'])  ? esc_sql($_GET['search-field'])  : 'any';
        $search_field = isset($this->db_search_cols[$search_field]) ? $this->db_search_cols[$search_field] : 'any';

        $list_table = MeprUser::list_table($orderby, $order, $paged, $search, $search_field, $perpage);
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

        MeprView::render('/admin/members/row', get_defined_vars());
    }

    /**
     * Get the items.
     *
     * @return array
     */
    public function get_items()
    {
        return $this->items;
    }
}
