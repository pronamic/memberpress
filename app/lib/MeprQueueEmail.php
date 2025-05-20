<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

abstract class MeprQueueEmail extends MeprBaseEmail
{
    /**
     * Used by the cron task to queue up emails in whatever fashion
     * is appropriate for the specific type of email we're dealing
     * with in the current object.
     *
     * @return void
     */
    abstract public function queue();
}
