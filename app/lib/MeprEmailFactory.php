<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Churns out our Emails on demand
 **/
class MeprEmailFactory
{
    /**
     * Fetch.
     *
     * @param  string $class The class.
     * @param  string $etype The etype.
     * @param  array  $args  The args.
     * @return mixed
     * @throws MeprInvalidEmailException When class doesn't exist or is not a valid email object.
     */
    public static function fetch($class, $etype = 'MeprBaseEmail', $args = [])
    {
        if (!class_exists($class)) {
            throw new MeprInvalidEmailException(__('Email wasn\'t found', 'memberpress'));
        }

        // We'll let the autoloader in memberpress.php
        // handle including files containing these classes.
        $r   = new ReflectionClass($class);
        $obj = $r->newInstanceArgs($args);

        if (!($obj instanceof $etype)) {
            throw new MeprInvalidEmailException(sprintf(
                // Translators: %1$s: class name, %2$s: expected class name.
                __('Not a valid email object: %1$s is not an instance of %2$s', 'memberpress'),
                $class,
                $etype
            ));
        }

        return $obj;
    }

    /**
     * All.
     *
     * @param  string $etype The etype.
     * @param  array  $args  The args.
     * @return array
     */
    public static function all($etype = 'MeprBaseEmail', $args = [])
    {
        static $objs;

        if (!isset($objs)) {
            $objs = [];
        }

        if (!isset($objs[$etype])) {
            $objs[$etype] = [];

            foreach (self::paths() as $path) {
                $files = @glob($path . '/Mepr*Email.php', GLOB_NOSORT);
                foreach ($files as $file) {
                    $class = preg_replace('#\.php#', '', basename($file));

                    try {
                        $obj                  = self::fetch($class, $etype, $args);
                        $objs[$etype][$class] = $obj;
                    } catch (Exception $e) {
                        continue; // For now we do nothing if an exception is thrown.
                    }
                }
            }

            // Order based on the ui_order.
            uasort($objs[$etype], function ($a, $b) {
                if ($a->ui_order == $b->ui_order) {
                    return 0;
                }

                return ($a->ui_order < $b->ui_order) ? -1 : 1;
            });
        }

        return $objs[$etype];
    }

    /**
     * Gets the paths.
     *
     * @return array
     */
    public static function paths()
    {
        return MeprHooks::apply_filters('mepr-email-paths', [MEPR_EMAILS_PATH]);
    }
}
