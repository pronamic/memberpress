<?php

/*
Plugin Name: MemberPress Pro 30 (Legacy)
Plugin URI: https://memberpress.com/
Description: The membership plugin that makes it easy to accept payments for access to your content and digital products.
Version: 1.12.11
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
License: GPL v2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: Caseproof, LLC
Author URI: https://caseproof.com/
Text Domain: memberpress
*/

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

require_once __DIR__ . '/vendor-prefixed/autoload.php';

define('MEPR_PLUGIN_SLUG', 'memberpress/memberpress.php');
define('MEPR_PLUGIN_NAME', 'memberpress');
define('MEPR_PATH', __DIR__);
define('MEPR_IMAGES_PATH', MEPR_PATH . '/images');
define('MEPR_BRAND_PATH', MEPR_PATH . '/brand');
define('MEPR_BRAND_CTRLS_PATH', MEPR_BRAND_PATH . '/controllers');
define('MEPR_BRAND_ADDONS_PATH', MEPR_BRAND_PATH . '/add-ons');
define('MEPR_CSS_PATH', MEPR_PATH . '/css');
define('MEPR_JS_PATH', MEPR_PATH . '/js');
define('MEPR_I18N_PATH', MEPR_PATH . '/i18n');
define('MEPR_LIB_PATH', MEPR_PATH . '/app/lib');
define('MEPR_INTEGRATIONS_PATH', MEPR_PATH . '/app/integrations');
define('MEPR_INTERFACES_PATH', MEPR_PATH . '/app/lib/interfaces');
define('MEPR_DATA_PATH', MEPR_PATH . '/app/data');
define('MEPR_BRAND_DATA_PATH', MEPR_BRAND_PATH . '/data');
define('MEPR_FONTS_PATH', MEPR_PATH . '/fonts');
define('MEPR_APIS_PATH', MEPR_PATH . '/app/apis');
define('MEPR_MODELS_PATH', MEPR_PATH . '/app/models');
define('MEPR_BRAND_MODELS_PATH', MEPR_BRAND_PATH . '/models');
define('MEPR_CTRLS_PATH', MEPR_PATH . '/app/controllers');
define('MEPR_GATEWAYS_PATH', MEPR_PATH . '/app/gateways');
define('MEPR_EMAILS_PATH', MEPR_PATH . '/app/emails');
define('MEPR_JOBS_PATH', MEPR_PATH . '/app/jobs');
define('MEPR_VIEWS_PATH', MEPR_PATH . '/app/views');
define('MEPR_BRAND_VIEWS_PATH', MEPR_BRAND_PATH . '/views');
define('MEPR_WIDGETS_PATH', MEPR_PATH . '/app/widgets');
define('MEPR_HELPERS_PATH', MEPR_PATH . '/app/helpers');
define('MEPR_BRAND_HELPERS_PATH', MEPR_BRAND_PATH . '/helpers');
define('MEPR_EXCEPTIONS_PATH', MEPR_PATH . '/app/lib/exceptions');
define('MEPR_URL', plugins_url('/' . MEPR_PLUGIN_NAME));
define('MEPR_BRAND_URL', MEPR_URL . '/brand');
define('MEPR_VIEWS_URL', MEPR_URL . '/app/views');
define('MEPR_IMAGES_URL', MEPR_URL . '/images');
define('MEPR_BRAND_IMAGES_URL', MEPR_BRAND_URL . '/images');
define('MEPR_CSS_URL', MEPR_URL . '/css');
define('MEPR_BRAND_CSS_URL', MEPR_BRAND_URL . '/css');
define('MEPR_JS_URL', MEPR_URL . '/js');
define('MEPR_BRAND_JS_URL', MEPR_BRAND_URL . '/js');
define('MEPR_GATEWAYS_URL', MEPR_URL . '/app/gateways');
define('MEPR_FONTS_URL', MEPR_URL . '/fonts');
define('MEPR_SCRIPT_URL', site_url('/index.php?plugin=mepr'));
define('MEPR_OPTIONS_SLUG', 'mepr_options');
define('MEPR_EDITION', 'memberpress-pro');

/**
 * Returns current plugin version.
 *
 * @param  string $field The field.
 * @return string Plugin version
 */
function mepr_plugin_info($field)
{
    static $curr_plugins;

    if (!isset($curr_plugins)) {
        if (!function_exists('get_plugins')) {
            require_once(ABSPATH . '/wp-admin/includes/plugin.php');
        }

        $curr_plugins = get_plugins();
        wp_cache_delete('plugins', 'plugins');
    }

    if (isset($curr_plugins[MEPR_PLUGIN_SLUG][$field])) {
        return $curr_plugins[MEPR_PLUGIN_SLUG][$field];
    }

    return '';
}

// Plugin Information from the plugin header declaration.
define('MEPR_VERSION', mepr_plugin_info('Version'));
define('MEPR_DISPLAY_NAME', mepr_plugin_info('Name'));
define('MEPR_AUTHOR', mepr_plugin_info('Author'));
define('MEPR_AUTHOR_URI', mepr_plugin_info('AuthorURI'));
define('MEPR_DESCRIPTION', mepr_plugin_info('Description'));

/**
 * Autoloads MemberPress plugin classes based on naming conventions.
 *
 * @param  string $class_name The name of the class to load.
 * @return void
 */
function mepr_autoloader($class_name)
{
    // Only load classes belonging to this plugin.
    if (preg_match('/^Mepr.+$/', $class_name)) {
        if (preg_match('/^.+Interface$/', $class_name)) { // Load interfaces first.
            $filepath = MEPR_INTERFACES_PATH . "/{$class_name}.php";
        } elseif (preg_match('/^Mepr(Base|Cpt).+$/', $class_name)) { // Base classes are in lib.
            $filepath = MEPR_LIB_PATH . "/{$class_name}.php";
        } elseif (preg_match('/^.+BrandCtrl$/', $class_name)) {
            $filepath = MEPR_BRAND_CTRLS_PATH . "/{$class_name}.php";
        } elseif (preg_match('/^.+Ctrl$/', $class_name)) {
            $filepath = MEPR_CTRLS_PATH . "/{$class_name}.php";
            // Try the brand controllers dir if file doesn't exist.
            if (!file_exists($filepath)) {
                $filepath = MEPR_BRAND_CTRLS_PATH . "/{$class_name}.php";
            }
        } elseif (preg_match('/^.+Helper$/', $class_name)) {
            $filepath = MEPR_HELPERS_PATH . "/{$class_name}.php";
            // Try the brand helpers dir if file doesn't exist.
            if (!file_exists($filepath)) {
                $filepath = MEPR_BRAND_HELPERS_PATH . "/{$class_name}.php";
            }
        } elseif (preg_match('/^.+Exception$/', $class_name)) {
            $filepath = MEPR_EXCEPTIONS_PATH . "/{$class_name}.php";
        } elseif (preg_match('/^.+Jobs$/', $class_name)) {
            $filepath = MEPR_LIB_PATH . '/MeprJobs.php';
        } elseif (preg_match('/^MeprMigrator.+$/', $class_name)) {
            $filepath = MEPR_LIB_PATH . "/migrators/{$class_name}.php";
        } elseif (preg_match('/^.+Gateway$/', $class_name)) {
            foreach (MeprGatewayFactory::paths() as $path) {
                $filepath = $path . "/{$class_name}.php";
                if (file_exists($filepath)) {
                    require_once($filepath);
                    return;
                }
            }
            return;
        } elseif (preg_match('/^.+Email$/', $class_name)) {
            foreach (MeprEmailFactory::paths() as $path) {
                $filepath = $path . "/{$class_name}.php";
                if (file_exists($filepath)) {
                    require_once($filepath);
                    return;
                }
            }
            return;
        } elseif (preg_match('/^.+Job$/', $class_name)) {
            foreach (MeprJobFactory::paths() as $path) {
                $filepath = $path . "/{$class_name}.php";
                if (file_exists($filepath)) {
                    require_once($filepath);
                    return;
                }
            }
            return;
        } else {
            $filepath = MEPR_MODELS_PATH . "/{$class_name}.php";

            // Try the brand models dir if file doesn't exist.
            if (!file_exists($filepath)) {
                $filepath = MEPR_BRAND_MODELS_PATH . "/{$class_name}.php";
            }

            // Now let's try the lib dir if its not a model.
            if (!file_exists($filepath)) {
                $filepath = MEPR_LIB_PATH . "/{$class_name}.php";
            }
        }

        if (file_exists($filepath)) {
            require_once($filepath);
        }
    }
}

// If __autoload is active, put it on the spl_autoload stack.
if (is_array(spl_autoload_functions()) and in_array('__autoload', spl_autoload_functions(), true)) {
    spl_autoload_register('__autoload');
}

// Add the autoloader.
spl_autoload_register('mepr_autoloader');

// Load integration files.
foreach ((array) glob(MEPR_INTEGRATIONS_PATH . '/*/Integration.php') as $mepr_file) {
    include_once $mepr_file;
}

// Load brand add-ons.
if (file_exists(MEPR_BRAND_PATH . '/add-ons.php')) {
    require_once MEPR_BRAND_PATH . '/add-ons.php';
}

// Include other files.
require_once MEPR_LIB_PATH . '/core-functions.php';

// Define database tables immediately (before controllers load).
MeprDb::define_tables();

// Re-define tables when switching blogs in multisite to ensure correct prefix.
if (is_multisite()) {
    add_action('switch_blog', 'MeprDb::define_tables', 1);
}

// Load our controllers.
MeprCtrlFactory::all();

// Setup screens.
MeprAppCtrl::setup_menus();

// Start Job Processor / Scheduler.
new MeprJobs();

/**
 * Activation hook.
 */
function mepr_on_activate(): void
{
    $mepr_options = MeprOptions::fetch();

    if (!$mepr_options->setup_complete) {
        if (!is_numeric($mepr_options->thankyou_page_id) || $mepr_options->thankyou_page_id === 0) {
            $mepr_options->thankyou_page_id = MeprAppHelper::auto_add_page(__('Thank You', 'memberpress'), esc_html__('Your subscription has been set up successfully.', 'memberpress'));
        }

        if (!is_numeric($mepr_options->account_page_id) || $mepr_options->account_page_id === 0) {
            $mepr_options->account_page_id = MeprAppHelper::auto_add_page(__('Account', 'memberpress'));
        }

        if (!is_numeric($mepr_options->login_page_id) || $mepr_options->login_page_id === 0) {
            $mepr_options->login_page_id = MeprAppHelper::auto_add_page(__('Login', 'memberpress'));
        }

        // Enable Pro Mode Templates.
        if (! filter_var($mepr_options->design_enable_checkout_template, FILTER_VALIDATE_BOOLEAN)) {
            $mepr_options->design_enable_checkout_template = true;
        }

        if (! filter_var($mepr_options->design_enable_login_template, FILTER_VALIDATE_BOOLEAN)) {
            $mepr_options->design_enable_login_template = true;
        }

        if (! filter_var($mepr_options->design_enable_courses_template, FILTER_VALIDATE_BOOLEAN)) {
            $mepr_options->design_enable_courses_template = true;
        }

        if (! filter_var($mepr_options->design_enable_thankyou_template, FILTER_VALIDATE_BOOLEAN)) {
            $mepr_options->design_enable_thankyou_template = true;
        }

        if (! filter_var($mepr_options->design_enable_pricing_template, FILTER_VALIDATE_BOOLEAN)) {
            $mepr_options->design_enable_pricing_template = true;
        }

        if (! filter_var($mepr_options->design_enable_account_template, FILTER_VALIDATE_BOOLEAN)) {
            $mepr_options->design_enable_account_template = true;
        }

        $mepr_options->setup_complete      = 1;
        $mepr_options->activated_timestamp = time();
        $mepr_options->store(false);
    }

    update_option('mepr_flush_rewrite_rules', true);
}

/**
 * Deactivation hook.
 */
function mepr_on_deactivate(): void
{
    remove_action('mod_rewrite_rules', 'MeprRulesCtrl::mod_rewrite_rules');

    MeprUtils::flush_rewrite_rules();

    // Remove wp-cron general purpose jobs.
    $jobs = new MeprJobs();
    $jobs->unschedule_events();

    // Remove wp-cron transaction jobs.
    MeprTransactionsCtrl::unschedule_events();

    $reminders_controller = new MeprRemindersCtrl();
    $reminders_controller->unschedule_reminders();
}

register_activation_hook(MEPR_PLUGIN_SLUG, 'mepr_on_activate');
register_deactivation_hook(MEPR_PLUGIN_SLUG, 'mepr_on_deactivate');
