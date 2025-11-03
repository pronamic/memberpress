<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

class MeprFilesystem
{
    /**
     * Returns the WP_Filesystem_Base instance, initializing it if necessary.
     *
     * @throws Exception If the WP_Filesystem could not be initialized.
     */
    public static function get(): WP_Filesystem_Base
    {
        global $wp_filesystem;

        if (!$wp_filesystem instanceof WP_Filesystem_Base) {
            $initialized = self::initialize();

            if (!$initialized) {
                throw new Exception('Could not initialize WP_Filesystem.');
            }
        }

        return $wp_filesystem;
    }

    /**
     * Initializes the WP_Filesystem global.
     */
    private static function initialize(): bool
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        if ('direct' === get_filesystem_method()) {
            $initialized = WP_Filesystem();
        } else {
            ob_start();
            $credentials = request_filesystem_credentials('');
            ob_end_clean();

            $initialized = $credentials && WP_Filesystem($credentials);
        }

        return is_null($initialized) ? false : $initialized;
    }
}
