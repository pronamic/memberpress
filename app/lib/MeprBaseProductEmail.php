<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

abstract class MeprBaseProductEmail extends MeprBaseEmail
{
    /**
     * Constructor.
     * Override the constructor to setup memberships and then
     * call the parent constructor to get everything else setup
     *
     * @param  array $args The args.
     * @return void
     */
    public function __construct($args = [])
    {
        // $this->product isn't necessarily set so you can't rely on it
        if (isset($args['product_id'])) {
            $this->product = new MeprProduct($args['product_id']);
        }

        parent::__construct($args);
    }

    /**
     * Gets the stored field.
     *
     * @param  string $fieldname The fieldname.
     * @return mixed
     */
    public function get_stored_field($fieldname)
    {
        $classname = get_class($this);
        $default   = isset($this->defaults[$fieldname]) ? $this->defaults[$fieldname] : false;

        if (!isset($this->product->emails[$classname][$fieldname])) {
            return $default;
        }

        return $this->product->emails[$classname][$fieldname];
    }

    /**
     * Gets the field name.
     *
     * @param  string  $field The field.
     * @param  boolean $id    The id.
     * @return string
     */
    public function field_name($field = 'enabled', $id = false)
    {
        $classname = get_class($this);

        if ($id) {
            return MeprProduct::$emails_str . '-' . $this->dashed_name() . '-' . $field;
        } else {
            return MeprProduct::$emails_str . '[' . $classname . '][' . $field . ']';
        }
    }
}
