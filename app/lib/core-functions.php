<?php

/**
 * Unbranded core utility functions.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!function_exists('core_get_prefix_string')) {
    /**
     * Returns prefix name based on the given type.
     *
     * The available types are:
     * - upper: 'MEPR'
     * - camel: 'Mepr'
     * - short: 'mp'
     * - default: 'mepr'
     *
     * @param string $type Prefix type. Default is 'default'.
     *
     * @return string The actual prefix string.
     */
    function core_get_prefix_string(string $type = 'default'): string
    {
        switch ($type) {
            case 'upper':
                return 'MEPR';
            case 'camel':
                return 'Mepr';
            case 'short':
                return 'mp';
        }

        return 'mepr';
    }
}

if (!function_exists('core_get_class_name_prefixed')) {
    /**
     * Returns the prefixed class name.
     *
     * @example core_get_class_name_prefixed('ExampleClass')
     *
     * @param string $class The class name without prefix.
     */
    function core_get_class_name_prefixed(string $class): string
    {
        return '\\' . core_get_prefix_string('camel') . $class;
    }
}

if (!function_exists('core_create_class_instance')) {
    /**
     * Creates an instance of a class with the prefixed name.
     *
     * @example core_create_class_instance('ExampleClass', ['arg1', 'arg2'])
     *
     * @param  string $class The class name without prefix.
     * @param  array  $args  Optional arguments to pass to the class constructor. Default is [].
     * @throws InvalidArgumentException If the class does not exist.
     * @return object An instance of the class.
     */
    function core_create_class_instance(string $class, array $args = []): object
    {
        $class_name = core_get_class_name_prefixed($class);

        if (!class_exists($class_name)) {
            throw new InvalidArgumentException('Class' . esc_html($class_name) . 'does not exist.');
        }

        return new $class_name(...$args);
    }
}

if (!function_exists('core_get_hook_name_prefixed')) {
    /**
     * Returns the prefixed hook name.
     *
     * @example core_get_hook_name_prefixed('example_hook')
     *
     * @param string $hook The hook name without prefix.
     */
    function core_get_hook_name_prefixed(string $hook): string
    {
        return core_get_prefix_string() . '_' . $hook;
    }
}

if (!function_exists('core_get_shortcode_name_prefixed')) {
    /**
     * Returns the prefixed shortcode name.
     *
     * @example core_get_shortcode_name_prefixed('example-shortcode')
     *
     * @param string $shortcode The shortcode name without prefix.
     */
    function core_get_shortcode_name_prefixed(string $shortcode): string
    {
        return core_get_prefix_string() . '_' . $shortcode;
    }
}

if (!function_exists('core_get_option_name_prefixed')) {
    /**
     * Returns the prefixed option name.
     *
     * The given option name must start with a separator character (e.g., underscore or hyphen).
     *
     * @example core_get_option_name_prefixed('_example_option')
     * @example core_get_option_name_prefixed('-example-option')
     * @example core_get_option_name_prefixed('-example-option', 'short')
     *
     * @param string $option      The option name without prefix.
     * @param string $prefix_type Optionally override the prefix type. Default is 'default'.
     */
    function core_get_option_name_prefixed(string $option, string $prefix_type = 'default'): string
    {
        return core_get_prefix_string($prefix_type) . $option;
    }
}

if (!function_exists('core_get_option')) {
    /**
     * Retrieves an option value with the prefixed name.
     *
     * The given option name must start with a separator character (e.g., underscore or hyphen).
     *
     * @example core_get_option('_example_option')
     * @example core_get_option('_example_option', null)
     * @example core_get_option('-example-option', null, 'short')
     *
     * @param string $option      The option name without prefix.
     * @param mixed  $default     Optional. Default value if the option does not exist. Default is false.
     * @param string $prefix_type Optionally override the prefix type. Default is 'default'.
     *
     * @return mixed The option value or default value if not set.
     */
    function core_get_option(string $option, $default = false, string $prefix_type = 'default')
    {
        return get_option(core_get_option_name_prefixed($option, $prefix_type), $default);
    }
}

if (!function_exists('core_get_transient_name_prefixed')) {
    /**
     * Returns the prefixed transient name.
     *
     * The given transient name must start with a separator character (e.g., underscore or hyphen).
     *
     * @example core_get_transient_name_prefixed('_example_transient')
     * @example core_get_transient_name_prefixed('-example-transient')
     * @example core_get_transient_name_prefixed('-example-transient', 'short')
     *
     * @param string $transient   The transient name without prefix.
     * @param string $prefix_type Optionally override the prefix type. Default is 'default'.
     */
    function core_get_transient_name_prefixed(string $transient, string $prefix_type = 'default'): string
    {
        return core_get_prefix_string($prefix_type) . $transient;
    }
}

if (!function_exists('core_get_transient')) {
    /**
     * Retrieves a transient value with the prefixed name.
     *
     * The given transient name must start with a separator character (e.g., underscore or hyphen).
     *
     * @example core_get_transient('_example_transient')
     * @example core_get_transient('-example-transient')
     * @example core_get_transient('-example-transient', 'short')
     *
     * @param  string $transient   The transient name without prefix.
     * @param  string $prefix_type Optionally override the prefix type. Default is 'default'.
     * @return mixed The transient value.
     */
    function core_get_transient(string $transient, string $prefix_type = 'default')
    {
        return get_transient(core_get_transient_name_prefixed($transient, $prefix_type));
    }
}

if (!function_exists('core_get_site_transient')) {
    /**
     * Retrieves a site transient value with the prefixed name.
     *
     * The given transient name must start with a separator character (e.g., underscore or hyphen).
     *
     * @example core_get_site_transient('_example_site_transient')
     * @example core_get_site_transient('-example-site-transient')
     * @example core_get_site_transient('-example-site-transient', 'short')
     *
     * @param  string $transient   The transient name without prefix.
     * @param  string $prefix_type Optionally override the prefix type. Default is 'default'.
     * @return mixed The site transient value.
     */
    function core_get_site_transient(string $transient, string $prefix_type = 'default')
    {
        return get_site_transient(core_get_transient_name_prefixed($transient, $prefix_type));
    }
}

if (!function_exists('core_get_constant_name_prefixed')) {
    /**
     * Returns the prefixed constant name.
     *
     * @example core_get_constant_name_prefixed('EXAMPLE_CONSTANT')
     *
     * @param string $constant The constant name without prefix.
     */
    function core_get_constant_name_prefixed(string $constant): string
    {
        return core_get_prefix_string('upper') . '_' . strtoupper($constant);
    }
}

if (!function_exists('core_get_constant')) {
    /**
     * Retrieves a constant value with the prefixed name.
     *
     * @example core_get_constant('EXAMPLE_CONSTANT')
     *
     * @param  string $constant The constant name without prefix.
     * @return mixed The constant value or null if not defined.
     */
    function core_get_constant(string $constant)
    {
        $constant_name = core_get_constant_name_prefixed($constant);
        return defined($constant_name) ? constant($constant_name) : null;
    }
}
