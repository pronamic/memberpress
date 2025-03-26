<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

interface MeprProductInterface
{
    /**
     * Get the group this product belongs to
     *
     * @return MeprGroup|false
     */
    public function group();

    /**
     * Check if this product is an upgrade
     *
     * @return boolean
     */
    public function is_upgrade();

    /**
     * Check if this product is a downgrade
     *
     * @return boolean
     */
    public function is_downgrade();

    /**
     * Check if this product is either an upgrade or downgrade
     *
     * @return boolean
     */
    public function is_upgrade_or_downgrade();
}
