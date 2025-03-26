<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprDrm extends MeprBaseModel
{
    /**
     * INSTANCE VARIABLES & METHODS
     *
     * @param integer $id The DRM record ID.
     */
    public function __construct($id)
    {
        $this->rec     = new stdClass();
        $this->rec->id = $id;
    }

    /**
     * Store the DRM record.
     *
     * @return void
     */
    public function store()
    {
    }

    /**
     * Destroy the DRM record.
     *
     * @return void
     */
    public function destroy()
    {
    }
}
