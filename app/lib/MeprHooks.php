<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Helper methods for working with hooks in MemberPress
 */
class MeprHooks
{
    /**
     * Do action.
     *
     * @param  string $tag The tag.
     * @param  mixed  $arg The arg.
     * @return mixed
     */
    public static function do_action($tag, $arg = '')
    {
        return self::call(__FUNCTION__, $tag, func_get_args());
    }

    /**
     * Do action ref array.
     *
     * @param  string $tag  The tag.
     * @param  mixed  $args The args.
     * @return mixed
     */
    public static function do_action_ref_array($tag, $args)
    {
        return self::call(__FUNCTION__, $tag, func_get_args());
    }

    /**
     * Apply filters.
     *
     * @param  string $tag   The tag.
     * @param  mixed  $value The value.
     * @return mixed
     */
    public static function apply_filters($tag, $value)
    {
        return self::call(__FUNCTION__, $tag, func_get_args(), 'filter');
    }

    /**
     * Apply filters ref array.
     *
     * @param  string $tag  The tag.
     * @param  mixed  $args The args.
     * @return mixed
     */
    public static function apply_filters_ref_array($tag, $args)
    {
        return self::call(__FUNCTION__, $tag, func_get_args(), 'filter');
    }

    /**
     * Add shortcode.
     *
     * @param  string $tag      The tag.
     * @param  mixed  $callback The callback.
     * @return mixed
     */
    public static function add_shortcode($tag, $callback)
    {
        return self::call(__FUNCTION__, $tag, func_get_args(), 'shortcode');
    }

    /**
     * Call.
     *
     * @param  string $fn   The fn.
     * @param  string $tag  The tag.
     * @param  mixed  $args The args.
     * @param  string $type The type.
     * @return mixed
     */
    private static function call($fn, $tag, $args, $type = 'action')
    {
        $tags = self::tags($tag);

        foreach ($tags as $t) {
            $args[0] = $t;

            if ($type === 'filter') {
                $args[1] = call_user_func_array($fn, $args);
            } else {
                call_user_func_array($fn, $args);
            }
        }

        if ($type === 'filter') {
            return $args[1];
        }
    }

    /**
     * Adds the mepr prefix to the tag if it doesn't exist already.
     * We love dashes and underscores ... we just can't choose which we like better :)
     *
     * @param  string $tag The tag.
     * @return array
     */
    private static function tags($tag)
    {
        // Prepend mepr if it doesn't exist already.
        if (!preg_match('/^mepr[-_]/i', $tag)) {
            $tag = 'mepr_' . $tag;
        }

        $tags = [
            '-' => preg_replace('/[-_]/', '-', $tag),
            '_' => preg_replace('/[-_]/', '_', $tag),
        ];

        // In case the original tag has mixed dashes and underscores.
        if (!in_array($tag, array_values($tags))) {
            $tags['*'] = $tag;
        }

        return $tags;
    }
}
