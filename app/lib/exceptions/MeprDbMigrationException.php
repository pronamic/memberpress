<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprDbMigrationException extends MeprLogException
{
    /**
     * Constructor for the MeprDbMigrationException class.
     *
     * @param string    $message  The message of the exception.
     * @param integer   $code     The code of the exception.
     * @param Exception $previous The previous exception.
     *
     * @return void
     */
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        delete_transient('mepr_migrating');
        delete_transient('mepr_current_migration');
        set_transient('mepr_migration_error', $message, MeprUtils::hours(4));
        parent::__construct($message, $code, $previous);
    }
}
