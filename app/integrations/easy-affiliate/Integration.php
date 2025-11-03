<?php

/**
 * Easy Affiliate Integration
 */

defined('ABSPATH') || exit;

// Initialize the integration as late as possible via plugins_loaded to give time for EA to load.
add_action(
    'plugins_loaded',
    function () {
        if (!defined('ESAF_VERSION') || version_compare(ESAF_VERSION, '1.4.0', '<')) {
            // Bail if EA is not installed or is older than version 1.4.0.
            return;
        }

        // No conflicts and all pre-requisites are met. Proceed.
        // Load the integration class.
        require_once __DIR__ . '/MeprEasyAffiliateIntegration.php';

        // Initialize the integration.
        MeprEasyAffiliateIntegration::instance();
    },
    9 // Run this later just to make sure EA's done with its thing.
);
