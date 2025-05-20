<?php

class MeprMigratorHelper
{
    /**
     * Are we on a page with the migrator?
     *
     * @return boolean
     */
    public static function is_migrator_page()
    {
        $id = MeprUtils::get_current_screen_id();

        if (!empty($id) && is_string($id)) {
            return preg_match('/_page_memberpress-(onboarding|courses-options)/', $id);
        }

        return false;
    }

    /**
     * Get the array of usable course migrators.
     *
     * @return array An array of migrator keys for any possible migrators.
     */
    public static function get_usable_course_migrators()
    {
        static $result;

        if (is_array($result)) {
            return $result;
        }

        $migrators = [];

        if (MeprMigratorLearnDash::is_migration_possible()) {
            $migrators[] = MeprMigratorLearnDash::KEY;
        }

        $result = $migrators;

        return $result;
    }

    /**
     * Has the given migration completed on this site?
     *
     * @param  string $migrator The migrator key, e.g. 'learndash'.
     * @return boolean
     */
    public static function has_completed_migration($migrator)
    {
        return (bool) get_option("mepr_migrator_{$migrator}_completed");
    }
}
