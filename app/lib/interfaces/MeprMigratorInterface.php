<?php

interface MeprMigratorInterface
{
    /**
     * Do the migration based on the given data.
     *
     * @param array $data The data array for the current step.
     */
    public function migrate(array $data);
}
