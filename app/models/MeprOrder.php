<?php

/**
 * Represents an order in the system.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $primary_transaction_id
 * @property string $trans_num
 * @property string $status
 * @property string $gateway
 * @property string $created_at
 */
class MeprOrder extends MeprBaseMetaModel
{
    /**
     * Status string for pending orders.
     *
     * @var string
     */
    public static $pending_str  = 'pending';

    /**
     * Status string for failed orders.
     *
     * @var string
     */
    public static $failed_str   = 'failed';

    /**
     * Status string for complete orders.
     *
     * @var string
     */
    public static $complete_str = 'complete';

    /**
     * Status string for refunded orders.
     *
     * @var string
     */
    public static $refunded_str = 'refunded';

    /**
     * Constructor for the MeprOrder class.
     *
     * @param mixed $obj Optional. The object to initialize the order with.
     */
    public function __construct($obj = null)
    {
        parent::__construct($obj);

        $this->initialize(
            [
                'id'                     => 0,
                'user_id'                => null,
                'primary_transaction_id' => 0,
                'trans_num'              => MeprOrder::generate_trans_num(),
                'status'                 => self::$pending_str,
                'gateway'                => null,
                'created_at'             => null,
            ],
            $obj
        );
    }

    /**
     * Create a new order record in the database.
     *
     * @param MeprOrder $order The order object to create.
     *
     * @return integer The ID of the created order.
     */
    public static function create(MeprOrder $order)
    {
        $mepr_db = new MeprDb();

        if (is_null($order->created_at) || empty($order->created_at)) {
            $order->created_at = MeprUtils::ts_to_mysql_date(time());
        }

        $args = (array) $order->get_values();

        return MeprHooks::apply_filters('mepr_create_order', $mepr_db->create_record($mepr_db->orders, $args, false), $args, $order->user_id);
    }

    /**
     * Update an existing order record in the database.
     *
     * @param MeprOrder $order The order object to update.
     *
     * @return integer The ID of the updated order.
     */
    public static function update(MeprOrder $order)
    {
        $mepr_db = new MeprDb();
        $args    = (array) $order->get_values();

        return MeprHooks::apply_filters('mepr_update_order', $mepr_db->update_record($mepr_db->orders, $order->id, $args), $args, $order->user_id);
    }

    /**
     * Store the order in the database, creating or updating as necessary.
     *
     * @return integer The ID of the stored order.
     */
    public function store()
    {
        $old_order = new self($this->id);

        if (isset($this->id) && !is_null($this->id) && (int) $this->id > 0) {
            $this->id = self::update($this);
        } else {
            $this->id = self::create($this);
        }

        MeprHooks::do_action('mepr-order-transition-status', $old_order->status, $this->status, $this);
        MeprHooks::do_action('mepr-order-store', $this, $old_order);
        MeprHooks::do_action('mepr-order-status-' . $this->status, $this);

        return $this->id;
    }

    /**
     * Delete the order from the database.
     *
     * @return boolean True if the order was successfully deleted, false otherwise.
     */
    public function destroy()
    {
        $mepr_db = new MeprDb();
        $id      = $this->id;
        $args    = compact('id');

        MeprHooks::do_action('mepr_order_destroy', $this);
        MeprHooks::do_action('mepr_pre_delete_order', $this);
        $result = MeprHooks::apply_filters('mepr_delete_order', $mepr_db->delete_records($mepr_db->orders, $args), $args);
        MeprHooks::do_action('mepr_post_delete_order', $this);

        return $result;
    }

    /**
     * Generates a unique transaction number for the order.
     *
     * @return string The generated transaction number.
     */
    public static function generate_trans_num()
    {
        return uniqid('mp-ord-');
    }

    /**
     * Get an order by ID
     *
     * @param  integer $id          The order ID.
     * @param  string  $return_type Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
     *                             correspond to an stdClass object, an associative array, or a numeric array,
     *                             respectively. Default OBJECT.
     * @return array|object|null Database query result in format specified by $return_type or null on failure.
     */
    public static function get_one($id, $return_type = OBJECT)
    {
        $mepr_db = new MeprDb();
        $args    = compact('id');

        return $mepr_db->get_one_record($mepr_db->orders, $args, $return_type);
    }

    /**
     * Get all transactions associated with the order.
     *
     * @return array An array of transaction records.
     */
    public function get_transactions()
    {
        global $wpdb;
        $transaction_table = $wpdb->prefix . 'mepr_transactions';
        $mepr_db           = new MeprDb();

        return $mepr_db->get_records($transaction_table, ['order_id' => $this->id]);
    }

    /**
     * Checks if the order is complete.
     *
     * @return boolean True if the order is complete, false otherwise.
     */
    public function is_complete()
    {
        return $this->status == MeprOrder::$complete_str;
    }

    /**
     * Checks if the order is processing.
     *
     * @return boolean True if the order is processing, false otherwise.
     */
    public function is_processing()
    {
        return $this->get_meta('processing', true) == '1';
    }
}
