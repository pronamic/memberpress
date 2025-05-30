<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprEvent extends MeprBaseModel
{
    /**
     * Event type for user related events.
     *
     * @var string
     */
    public static $users_str         = 'users';

    /**
     * Event type for transaction related events.
     *
     * @var string
     */
    public static $transactions_str  = 'transactions';

    /**
     * Event type for subscription related events.
     *
     * @var string
     */
    public static $subscriptions_str = 'subscriptions';

    /**
     * Event type for DRM related events.
     *
     * @var string
     */
    public static $drm_str           = 'drm';

    /**
     * Event name for login events.
     *
     * @var string
     */
    public static $login_event_str = 'login';

    /**
     * Constructor for the MeprEvent class.
     *
     * @param mixed $obj The object to initialize the event with.
     */
    public function __construct($obj = null)
    {
        $this->initialize(
            [
                'id'          => 0,
                'args'        => null,
                'event'       => 'login',
                'evt_id'      => 0,
                'evt_id_type' => 'users',
                'created_at'  => null,
            ],
            $obj
        );
    }

    /**
     * Validate the event's properties.
     *
     * @return void
     */
    public function validate()
    {
        $this->validate_is_numeric($this->evt_id, 0, null, 'evt_id');
    }

    /**
     * Get a single event by ID.
     *
     * @param  integer $id          The ID of the event.
     * @param  string  $return_type The return type of the result.
     * @return object|null
     */
    public static function get_one($id, $return_type = OBJECT)
    {
        $mepr_db = new MeprDb();
        $args    = compact('id');
        return $mepr_db->get_one_record($mepr_db->events, $args, $return_type);
    }

    /**
     * Get a single event by event, event ID, and event ID type.
     *
     * @param  string  $event       The event name.
     * @param  integer $evt_id      The event ID.
     * @param  string  $evt_id_type The event ID type.
     * @param  string  $return_type The return type of the result.
     * @return object|null
     */
    public static function get_one_by_event_and_evt_id_and_evt_id_type($event, $evt_id, $evt_id_type, $return_type = OBJECT)
    {
        $mepr_db = new MeprDb();
        return $mepr_db->get_one_record($mepr_db->events, compact('event', 'evt_id', 'evt_id_type'), $return_type);
    }

    /**
     * Get the count of all events.
     *
     * @return integer
     */
    public static function get_count()
    {
        $mepr_db = new MeprDb();
        return $mepr_db->get_count($mepr_db->events);
    }

    /**
     * Get the count of events by event name.
     *
     * @param  string $event The event name.
     * @return integer
     */
    public static function get_count_by_event($event)
    {
        $mepr_db = new MeprDb();
        return $mepr_db->get_count($mepr_db->events, compact('event'));
    }

    /**
     * Get the count of events by event ID type.
     *
     * @param  string $evt_id_type The event ID type.
     * @return integer
     */
    public static function get_count_by_evt_id_type($evt_id_type)
    {
        $mepr_db = new MeprDb();
        return $mepr_db->get_count($mepr_db->events, compact('evt_id_type'));
    }

    /**
     * Get the count of events by event, event ID, and event ID type.
     *
     * @param  string  $event       The event name.
     * @param  integer $evt_id      The event ID.
     * @param  string  $evt_id_type The event ID type.
     * @return integer
     */
    public static function get_count_by_event_and_evt_id_and_evt_id_type($event, $evt_id, $evt_id_type)
    {
        $mepr_db = new MeprDb();
        return $mepr_db->get_count($mepr_db->events, compact('event', 'evt_id', 'evt_id_type'));
    }

    /**
     * Get all events.
     *
     * @param  string $order_by The order by clause.
     * @param  string $limit    The limit clause.
     * @return array
     */
    public static function get_all($order_by = '', $limit = '')
    {
        $mepr_db = new MeprDb();
        return $mepr_db->get_records($mepr_db->events, [], $order_by, $limit);
    }

    /**
     * Get all events by event name.
     *
     * @param  string $event    The event name.
     * @param  string $order_by The order by clause.
     * @param  string $limit    The limit clause.
     * @return array
     */
    public static function get_all_by_event($event, $order_by = '', $limit = '')
    {
        $mepr_db = new MeprDb();
        $args    = ['event' => $event];
        return $mepr_db->get_records($mepr_db->events, $args, $order_by, $limit);
    }

    /**
     * Get all events by event ID type.
     *
     * @param  string $evt_id_type The event ID type.
     * @param  string $order_by    The order by clause.
     * @param  string $limit       The limit clause.
     * @return array
     */
    public static function get_all_by_evt_id_type($evt_id_type, $order_by = '', $limit = '')
    {
        $mepr_db = new MeprDb();
        $args    = ['evt_id_type' => $evt_id_type];
        return $mepr_db->get_records($mepr_db->events, $args, $order_by, $limit);
    }

    /**
     * Store the event in the database.
     *
     * @return integer The ID of the stored event.
     */
    public function store()
    {
        $mepr_db = new MeprDb();

        MeprHooks::do_action('mepr-event-pre-store', $this);

        $this->use_existing_if_unique();

        $vals = (array)$this->rec;
        unset($vals['created_at']); // Let mepr_db handle this.

        if (isset($this->id) and (int)$this->id > 0) {
            $mepr_db->update_record($mepr_db->events, $this->id, $vals);
            MeprHooks::do_action('mepr-event-update', $this);
        } else {
            $this->id = $mepr_db->create_record($mepr_db->events, $vals);
            MeprHooks::do_action('mepr-event-create', $this);
            MeprHooks::do_action('mepr-event', $this);

            MeprHooks::do_action("mepr-evt-{$this->event}", $this); // DEPRECATED.
            MeprHooks::do_action("mepr-event-{$this->event}", $this);
        }

        MeprHooks::do_action('mepr-event-store', $this);

        return $this->id;
    }

    /**
     * Destroy the event from the database.
     *
     * @return boolean True on success, false on failure.
     */
    public function destroy()
    {
        $mepr_db = new MeprDb();

        $id   = $this->id;
        $args = compact('id');

        MeprHooks::do_action('mepr_event_destroy', $this);

        return MeprHooks::apply_filters('mepr_delete_event', $mepr_db->delete_records($mepr_db->events, $args), $args);
    }

    // TODO: This is a biggie ... we don't want to send the event object like this
    // we need to send the object associated with the event instead.
    /**
     * Get the data for the event.
     *
     * @return object|false
     */
    public function get_data()
    {
        $obj = false;
        switch ($this->evt_id_type) {
            case self::$users_str:
                $obj = new MeprUser($this->evt_id);

                // If member-deleted event is being passed, make sure we generate some data.
                if (!isset($obj->ID) || $obj->ID <= 0) {
                    if ($this->event == 'member-deleted') {
                          $obj->ID         = 0;
                          $obj->user_email = 'johndoe@email.com';
                          $obj->user_login = 'johndoe';
                          $obj->first_name = 'John';
                          $obj->last_name  = 'Doe';
                    }
                }

                break;
            case self::$transactions_str:
                $obj = new MeprTransaction($this->evt_id);
                break;
            case self::$subscriptions_str:
                $obj = new MeprSubscription($this->evt_id);
                break;
            default:
                return new WP_Error(__('An unsupported Event type was used', 'memberpress'));
        }

        return $obj;
    }

    /**
     * Get the arguments for the event.
     *
     * @return mixed
     */
    public function get_args()
    {
        if (!empty($this->args) && is_string($this->args)) {
            return json_decode($this->args);
        }
        return $this->args;
    }

    /**
     * Record an event.
     *
     * @param  string        $event The event name.
     * @param  MeprBaseModel $obj   The object associated with the event.
     * @param  mixed         $args  The arguments for the event.
     * @return void
     */
    public static function record($event, MeprBaseModel $obj, $args = '')
    {
        // Nothing to record? Hopefully this stops some ghost duplicate reminders we are seeing
        // Gotta use ->rec here to avoid weird shiz from happening hopefully.
        if ((!isset($obj->rec->id) || !$obj->rec->id) && (!isset($obj->rec->ID) || !$obj->rec->ID)) {
            return;
        }

        $e        = new MeprEvent();
        $e->event = $event;
        $e->args  = $args;

        // Just turn objects into json for fun.
        if (is_array($args) || is_object($args)) {
            $e->args = json_encode($args);
        }

        if ($obj instanceof MeprUser) {
            $e->evt_id      = $obj->rec->ID;
            $e->evt_id_type = self::$users_str;
        } elseif ($obj instanceof MeprTransaction) {
            $e->evt_id      = $obj->rec->id;
            $e->evt_id_type = self::$transactions_str;
        } elseif ($obj instanceof MeprSubscription) {
            $e->evt_id      = $obj->rec->id;
            $e->evt_id_type = self::$subscriptions_str;
        } elseif ($obj instanceof MeprDrm) {
            $e->evt_id      = $obj->rec->id;
            $e->evt_id_type = self::$drm_str;
        } else {
            return;
        }

        $e->store();
    }

    /**
     * Get the latest object for a given event.
     *
     * @param  string $event The event name.
     * @return MeprEvent|false
     */
    public static function latest($event)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $q = $wpdb->prepare("
      SELECT id
        FROM {$mepr_db->events}
       WHERE event=%s
       ORDER BY id DESC
       LIMIT 1
    ", $event);

        $id = $wpdb->get_var($q);
        if ($id) {
            return new MeprEvent($id);
        }

        return false;
    }

    /**
     * Get the tablename for the specific type of event.
     *
     * @param  string $event_type The event type.
     * @return string|null
     */
    public static function get_tablename($event_type)
    {
        global $wpdb;

        $mepr_db = MeprDb::fetch();

        if ($event_type == MeprEvent::$users_str) {
            return $wpdb->users;
        } elseif ($event_type == MeprEvent::$transactions_str) {
            return $mepr_db->transactions;
        } elseif ($event_type == MeprEvent::$subscriptions_str) {
            return $mepr_db->subscriptions;
        }
    }

    /**
     * Gets info from app/data/events.php if it exists.
     *
     * @return associative array if found or false if not found
     */
    private function event_info()
    {
        $event_data = require(MEPR_DATA_PATH . '/events.php');

        if (isset($event_data[$this->event])) {
            return $event_data[$this->event];
        }

        return false;
    }

    /**
     * Uses app/data/events.php to determine if the current event is
     * unique -- if true, only one row can be stored for a given event,
     * evt_id & evt_id_type.
     *
     * @return true/false
     */
    private function is_unique()
    {
        $event_info = $this->event_info();
        return (false !== $event_info && isset($event_info->unique) && $event_info->unique);
    }

    /**
     * Copy an existing event id & args if the event is unique and another
     * event record with the same event, evt_id & evt_id_type already exists.
     *
     * @return void
     */
    private function use_existing_if_unique()
    {
        if ($this->is_unique()) {
            $existing_event = self::get_one_by_event_and_evt_id_and_evt_id_type($this->event, $this->evt_id, $this->evt_id_type);
            if (!empty($existing_event)) {
                $this->id   = $existing_event->id;
                $this->args = $existing_event->args;
            }
        }
    }

    /**
     * Get the latest object for a given event and elapsed days.
     *
     * @param  string  $event        The event name.
     * @param  integer $elapsed_days The number of elapsed days.
     * @return MeprEvent|false
     */
    public static function latest_by_elapsed_days($event, $elapsed_days)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $q = $wpdb->prepare("
      SELECT id
        FROM {$mepr_db->events}
       WHERE event=%s
       AND created_at >= '%s' - interval %d day
       ORDER BY id DESC
       LIMIT 1
    ", $event, MeprUtils::db_now(), $elapsed_days);

        $id = $wpdb->get_var($q);
        if ($id) {
            return new MeprEvent($id);
        }

        return false;
    }
}
