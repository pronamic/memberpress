<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprDbMigrationRollbackException extends MeprDbMigrationException
{
    /**
     * Constructor for the MeprDbMigrationRollbackException class.
     *
     * @param string    $message  The message of the exception.
     * @param integer   $code     The code of the exception.
     * @param Exception $previous The previous exception.
     *
     * @return void
     */
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        global $wpdb;
        $wpdb->query('ROLLBACK'); // Attempt a rollback.
        parent::__construct($message, $code, $previous);
    }
}
