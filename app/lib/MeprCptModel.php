<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Specific base class for CPT Style models
 */
abstract class MeprCptModel extends MeprBaseModel
{
    // All inheriting classes should set -- public static $cpt (custom post type)
    public static $cpt = '';

    /**
     * This should only be used if the model is using a custom post type
     **/
    protected function initialize_new_cpt()
    {
        $whos_calling = get_class($this);

        if (!isset($this->attrs) or !is_array($this->attrs)) {
            $this->attrs = [];
        }

        $r = [
            'ID'           => null,
            'post_content' => '',
            'post_title'   => null,
            'post_excerpt' => '',
            'post_name'    => null,
            'post_date'    => null,
            'post_status'  => 'publish', // We'll assume this is published if not coming through the post editor
            'post_parent'  => 0,
            'menu_order'   => 0,
            'post_type'    => MeprUtils::get_property($whos_calling, 'cpt'),
        ];

        // Initialize postmeta variables
        // Backwards compatible in case attrs has no default values
        if (MeprUtils::is_associative_array($this->attrs)) {
            foreach ($this->attrs as $var => $default) {
                $r[$var] = $default;
            }
        } else {
            foreach ($this->attrs as $var) {
                $r[$var] = null;
            }
        }

        $this->rec = (object)$r;

        return $this->rec;
    }

    /**
     * Requires defaults to be set.
     *
     * @param  integer $id    The id.
     * @param  string  $cpt   The cpt.
     * @param  array   $attrs The attrs.
     * @return void
     */
    protected function load_cpt($id, $cpt, $attrs)
    {
        $this->attrs = $attrs;

        $this->rec = get_post($id);
        if (null === $this->rec) {
            $this->initialize_new_cpt();
        } elseif ($this->post_type != $cpt) {
            $this->initialize_new_cpt();
        } else {
            $this->load_meta($id);
        }
    }

    /**
     * Requires defaults to be set.
     *
     * @param  integer $id The id.
     * @return void
     */
    protected function load_meta($id)
    {
        $metas = get_post_custom($id);

        $rec = [];

        // Unserialize and set appropriately
        foreach ($this->attrs as $akey => $aval) {
            $rclass = new ReflectionClass($this);
            // This requires that the static variable have the same name
            // as the attribute key with "_str" appended
            $rkey = $rclass->getStaticPropertyValue("{$akey}_str");
            if (isset($metas[$rkey])) {
                if (count($metas[$rkey]) > 1) {
                    $rec[$akey] = [];
                    foreach ($metas[$rkey] as $skey => $sval) {
                        $rec[$akey][$skey] = maybe_unserialize($sval);
                    }
                } else {
                    $mval = $metas[$rkey][0];
                    if ($mval === '' and is_bool($this->attrs[$akey])) {
                        $rec[$akey] = false;
                    } else {
                        $rec[$akey] = maybe_unserialize($mval);
                    }
                }
            }
        }

        $this->rec = (object)array_merge((array)$this->rec, $this->attrs, $rec);
    }

    /**
     * Stores the model.
     *
     * @return integer
     */
    public function store()
    {
        if (isset($this->ID) and !is_null($this->ID)) {
            $id = wp_update_post((array)$this->rec);
        } else {
            $id = wp_insert_post((array)$this->rec);
        }

        if (empty($id) or is_wp_error($id)) {
            throw new MeprCreateException(sprintf(__('This was unable to be saved.', 'memberpress')));
        } else {
            $this->ID = $id;
        }

        $this->store_meta();

        return $id;
    }

    /**
     * Stores the meta.
     *
     * @return void
     */
    abstract public function store_meta();

    /**
     * Saves the meta.
     *
     * @return void
     */
    public function save_meta()
    {
        $this->store_meta();
    }

    /**
     * Destroys the model.
     *
     * @return boolean
     */
    public function destroy()
    {
        $res = wp_delete_post($this->ID, true);

        if (false === $res) {
            throw new MeprCreateException(sprintf(__('This was unable to be deleted.', 'memberpress')));
        }

        return $res;
    }

    /**
     * Gets the URL.
     * Should probabaly add a delim char check to add before the args
     * similar to how I did it in MeprOptions
     *
     * @param  string $args The args.
     * @return string
     */
    public function url($args = '')
    {
        $link = MeprUtils::get_permalink($this->ID);
        return MeprHooks::apply_filters('mepr_cpt_model_url', "{$link}{$args}", $this);
    }

    /**
     * Gets all the models.
     *
     * @param  string  $class            The class.
     * @param  boolean $reset_transients The reset transients.
     * @param  boolean $extra_args       The extra args.
     * @return array
     */
    public static function all($class, $reset_transients = false, $extra_args = false)
    {
        if (empty($class)) {
            return [];
        }

        // $r = new ReflectionClass(get_called_class()); //Not possible pre PHP 5.3 so we have to pass the class name as an argument gah
        $r         = new ReflectionClass($class);
        $cpt       = $r->getStaticPropertyValue('cpt');
        $models    = [];
        $transient = 'mepr_all_models_for_class_' . strtolower($class);
        $args      = [
            'numberposts' => -1,
            'post_type'   => $cpt,
            'post_status' => 'publish',
        ];

        if ($extra_args && !empty($extra_args)) {
            $args = array_merge($args, $extra_args);
        }

        if (empty($cpt)) {
            return [];
        }

        $use_transient_cache = MeprHooks::apply_filters('mepr-cpt-all-use-transient-cache', true, $cpt, $class);

        if ($use_transient_cache === true) {
            $cached = get_transient($transient);

            if (!empty($cached) && !$reset_transients && !function_exists('pll_current_language')) { // Need to check for PolyLang plugin before returning the cache
                return $cached; // Return the transient cached data
            }
        }

        // Not cached? Let's load up the posts with get_posts then
        $posts = get_posts(MeprHooks::apply_filters('mepr_cpt_all_args', $args, $cpt));

        foreach ($posts as $post) {
            if (isset($post->post_type) && $post->post_type == $cpt) {
                $models[] = $r->newInstance($post->ID);
            }
        }

        delete_transient($transient);
        if ($use_transient_cache === true) {
            set_transient($transient, $models, YEAR_IN_SECONDS); // Set a long expiration (so transients are not autoloaded) - we'll update this during MeprCptCtrl->save_post() calls
        }

        return $models;
    }

    /**
     * Gets all the data.
     *
     * @param  string  $class   The class.
     * @param  string  $type    The type.
     * @param  string  $orderby The orderby.
     * @param  string  $order   The order.
     * @param  integer $limit   The limit.
     * @param  integer $offset  The offset.
     * @param  array   $selects The selects.
     * @param  array   $joins   The joins.
     * @param  array   $wheres  The wheres.
     * @return array
     */
    public static function get_all_data(
        $class, // get_class relies on $this so we have to pass the name in
        $type = OBJECT,
        $orderby = 'ID',
        $order = 'ASC',
        $limit = 100,
        $offset = 0,
        $selects = [],
        $joins = [],
        $wheres = []
    ) {
        global $wpdb;

        $rc  = new ReflectionClass($class);
        $obj = $rc->newInstance();

        // Account for associative or numeric arrays
        if (MeprUtils::is_associative_array($obj->attrs)) {
            $meta_keys = array_keys($obj->attrs);
        } else {
            $meta_keys = $obj->attrs;
        }

        array_unshift(
            $wheres,
            $wpdb->prepare('p.post_type=%s', $rc->getStaticPropertyValue('cpt')),
            $wpdb->prepare('p.post_status=%s', 'publish')
        );

        if (empty($selects)) {
            $selects      = ['p.*'];
            $fill_selects = true;
        } else {
            $fill_selects = false;
        }

        foreach ($meta_keys as $meta_key) {
            // Static var for every attr convention
            $meta_key_str = $rc->getStaticPropertyValue("{$meta_key}_str");

            if ($fill_selects) {
                $selects[] = "pm_{$meta_key}.meta_value AS {$meta_key}";
            }
            $joins[] = $wpdb->prepare(
                "LEFT JOIN {$wpdb->postmeta} AS pm_{$meta_key} " .
                "ON pm_{$meta_key}.post_id=p.ID " .
                "AND pm_{$meta_key}.meta_key=%s",
                $meta_key_str
            );
        }

        $selects_str = join(', ', $selects);
        $joins_str   = join(' ', $joins);
        $wheres_str  = join(' AND ', $wheres);

        $q = "SELECT {$selects_str} " .
           "FROM {$wpdb->posts} AS p {$joins_str} " .
          "WHERE {$wheres_str} " .
          "ORDER BY {$orderby} {$order} " .
          "LIMIT {$limit} " .
         "OFFSET {$offset}";

        $res = $wpdb->get_results($q, $type);

        // two layer maybe_unserialize
        for ($i = 0; $i < count($res); $i++) {
            foreach ($res[$i] as $k => $val) {
                $res[$i][$k] = maybe_unserialize($val);
            }
        }

        return $res;
    }
}
