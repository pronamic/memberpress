<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

abstract class MeprBaseJob
{
    /**
     * The record object
     *
     * @var object
     */
    public $rec;

    /**
     * The database object
     *
     * @var object
     */
    public $db;

    /**
     * Constructor.
     *
     * @param  object $db The database object.
     * @return void
     */
    public function __construct($db = false)
    {
        if (empty($db)) {
            $db     = (object)[];
            $db->id = 0;
        }

        $this->rec = isset($db->args) ? json_decode($db->args, true) : [];
        $this->db  = (object)$db;
    }

    /**
     * Perform the job.
     *
     * @return void
     */
    abstract public function perform();

    /**
     * Enqueue in.
     *
     * @param  integer $in       The in.
     * @param  integer $priority The priority.
     * @return void
     */
    public function enqueue_in($in, $priority = 10)
    {
        $classname = get_class($this);
        $jobs      = new MeprJobs();
        $jobs->enqueue_in($in, $classname, (array)$this->rec, $priority);
    }

    /**
     * Enqueue at.
     *
     * @param  integer $at       The at.
     * @param  integer $priority The priority.
     * @return void
     */
    public function enqueue_at($at, $priority = 10)
    {
        $classname = get_class($this);
        $jobs      = new MeprJobs();
        $jobs->enqueue_at($at, $classname, (array)$this->rec, $priority);
    }

    /**
     * Enqueue job.
     *
     * @param  string  $when     The when.
     * @param  integer $priority The priority.
     * @return void
     */
    public function enqueue($when = 'now', $priority = 10)
    {
        $classname = get_class($this);
        $jobs      = new MeprJobs();
        $jobs->enqueue($classname, (array)$this->rec, $when, $priority);
    }

    /**
     * Dequeue job.
     *
     * @return void
     */
    public function dequeue()
    {
        $jobs = new MeprJobs();
        $jobs->dequeue($this->db->id);
    }

    /**
     * Get job attribute.
     *
     * @param  string $name The name.
     * @return mixed
     */
    public function __get($name)
    {
        $value = null;

        $object_vars = array_keys(get_object_vars($this));
        $rec_array   = (array)$this->rec;

        if (in_array($name, $object_vars)) {
            $value = $this->$name;
        } elseif (array_key_exists($name, $rec_array)) {
            if (is_array($this->rec)) {
                $value = $this->rec[$name];
            } else {
                $value = $this->rec->$name;
            }
        }

        return MeprHooks::apply_filters('mepr-get-job-attribute-' . $name, $value, $this);
    }

    /**
     * Set job attribute.
     *
     * @param  string $name  The name.
     * @param  mixed  $value The value.
     * @return void
     */
    public function __set($name, $value)
    {
        $value = MeprHooks::apply_filters('mepr-set-job-attribute-' . $name, $value, $this);

        $object_vars = array_keys(get_object_vars($this));
        $rec_array   = (array)$this->rec;

        if (in_array($name, $object_vars)) {
            $this->$name = $value;
        } else {
            if (is_array($this->rec)) {
                $this->rec[$name] = $value;
            } else {
                $this->rec->$name = $value;
            }
        }
    }

    /**
     * Is job attribute set.
     *
     * @param  string $name The name.
     * @return boolean
     */
    public function __isset($name)
    {
        if (is_array($this->rec)) {
            return isset($this->rec[$name]);
        } elseif (is_object($this->rec)) {
            return isset($this->rec->$name);
        } else {
            return false;
        }
    }

    /**
     * Unset job attribute.
     *
     * @param  string $name The name.
     * @return void
     */
    public function __unset($name)
    {
        if (is_array($this->rec)) {
            unset($this->rec[$name]);
        } elseif (is_object($this->rec)) {
            unset($this->rec->$name);
        }
    }

    /**
     * We just return a JSON encoding of the attributes in the model when we
     * try to get a string for the model.
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode((array)$this->rec);
    }
}
