<?php

abstract class MeprMigrator implements MeprMigratorInterface
{
    /**
     * Array of logs to display for non-critical issues.
     *
     * @var string[]
     */
    protected $logs = [];

    /**
     * Migrate a model to MemberPress.
     *
     * Creates a new instance of the given $class and stores it.
     *
     * @param  string  $class       The class name of the model to create.
     * @param  integer $existing_id The existing ID, if numeric and > 0, the model will be updated rather than created.
     * @param  array   $data        The array of properties to set on the model instance.
     * @return mixed                 Returns an instance of $class
     * @throws Exception             If there was an issue creating the model. The caller should handle the exception
     *                               (log failure, continue processing for example).
     */
    protected function migrate_model(string $class, int $existing_id, array $data)
    {
        $model       = new $class($existing_id);
        $valid_attrs = $model->get_attrs();

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $valid_attrs)) {
                $model->$key = $value;
            }
        }

        $result = $model->store();

        if ($result instanceof WP_Error || empty($result)) {
            throw new Exception($result instanceof WP_Error ? $result->get_error_message() : 'model ID was empty');
        }

        return $model;
    }

    /**
     * Runs before the migrator starts.
     */
    public static function before_start()
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);
        wp_suspend_cache_invalidation();
    }

    /**
     * Runs when the migrator finishes.
     */
    public static function finish()
    {
        wp_suspend_cache_invalidation(false);
        wp_cache_flush();
        wp_defer_term_counting(false);
        wp_defer_comment_counting(false);
    }

    /**
     * Free memory, to prevent memory exhausted errors.
     */
    public static function free_memory()
    {
        global $wpdb, $wp_object_cache;

        // Empty the query log, this can grow constantly if SAVEQUERIES is enabled.
        $wpdb->queries = [];

        if ($wp_object_cache instanceof \WP_Object_Cache) {
            if (function_exists('wp_cache_flush_runtime')) {
                wp_cache_flush_runtime();
            } elseif (!wp_using_ext_object_cache() || apply_filters('mepr_migrate_flush_cache', false)) {
                wp_cache_flush();
            }
        }
    }

    /**
     * Send a JSON success response.
     *
     * @param array $data     The data for the current request.
     * @param array $response Additional data to add to the response.
     */
    protected function send_success_response(array $data, array $response)
    {
        wp_send_json_success(
            array_merge(
                [
                    'migrator' => $data['migrator'],
                    'options'  => $data['options'],
                ],
                $response
            )
        );
    }

    /**
     * Build the log message for a model migration failure.
     *
     * @param  string         $class   The PHP class of the model.
     * @param  string         $title   The title of the model to identify it.
     * @param  integer|string $id      The ID of the model.
     * @param  string         $message The error message to display.
     * @return string
     */
    protected function model_migration_failed_log(string $class, string $title, $id, string $message): string
    {
        return sprintf(
            // Translators: %1$s: the model type, %2$s: the model title, %3$s: the model ID, %4$s: the error message.
            __('Failed to migrate %1$s "%2$s" [ID: %3$s]: %4$s', 'memberpress'),
            basename($class),
            $title,
            $id,
            $message
        );
    }

    /**
     * Get the limit and offset from the given request data (if any).
     *
     * @param  array   $data          The data for the current request.
     * @param  integer $default_limit The default limit to use if none exists in the request data.
     * @return array
     */
    protected function get_request_limit_offset(array $data, int $default_limit): array
    {
        $limit  = isset($data['limit']) && is_numeric($data['limit']) && $data['limit'] > 0 ? MeprUtils::clamp((int) $data['limit'], 1, 500) : $default_limit;
        $offset = isset($data['offset']) && is_numeric($data['offset']) && $data['offset'] > 0 ? (int) $data['offset'] : 0;

        return [$limit, $offset];
    }
}
