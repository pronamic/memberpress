<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprLogException extends MeprException
{
    /**
     * Constructor for the MeprLogException class.
     *
     * @param string    $message  The message of the exception.
     * @param integer   $code     The code of the exception.
     * @param Exception $previous The previous exception.
     *
     * @return void
     */
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        $classname = get_class($this);
        MeprUtils::error_log("{$classname}: {$message}");
        parent::__construct($message, $code, $previous);
    }
}
