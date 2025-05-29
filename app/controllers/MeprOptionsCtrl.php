<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprOptionsCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for various AJAX actions and admin scripts.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('wp_ajax_mepr_activate_license', 'MeprOptionsCtrl::ajax_activate_license');
        add_action('wp_ajax_mepr_deactivate_license', 'MeprOptionsCtrl::ajax_deactivate_license');
        add_action('wp_ajax_mepr_install_license_edition', 'MeprOptionsCtrl::ajax_install_license_edition');
        add_action('wp_ajax_mepr_gateway_form', 'MeprOptionsCtrl::gateway_form');
        add_action('admin_enqueue_scripts', 'MeprOptionsCtrl::enqueue_scripts');
        add_action('admin_print_footer_scripts', 'MeprOptionsCtrl::enqueue_footer_scripts');
        add_action('admin_notices', 'MeprOptionsCtrl::maybe_show_stripe_checkout_warning');
        add_action('wp_ajax_mepr_validate_stripe_payment_method_types', 'MeprOptionsCtrl::validate_stripe_payment_method_types');
        add_action('wp_ajax_mepr_activate_stripe_payment_method', 'MeprOptionsCtrl::activate_stripe_payment_method');
    }

    /**
     * Show a warning if Stripe Checkout is deprecated.
     *
     * @return void
     */
    public static function maybe_show_stripe_checkout_warning()
    {
        if (MeprUtils::is_get_request() && isset($_GET['page']) && $_GET['page'] == 'memberpress-options') {
            $mepr_options = MeprOptions::fetch();

            foreach ($mepr_options->integrations as $integration) {
                if (isset($integration['gateway']) && $integration['gateway'] == 'MeprStripeGateway' && isset($integration['use_stripe_checkout'])) {
                    MeprView::render('/admin/stripe_checkout_deprecated');
                    break;
                }
            }
        }
    }

    /**
     * Route various actions based on the request.
     *
     * @return mixed
     */
    public static function route()
    {
        $action = (isset($_REQUEST['action']) ? $_REQUEST['action'] : '');

        if (MeprUtils::is_post_request() && $action == 'process-form') {
            check_admin_referer('mepr_update_options', 'mepr_options_nonce');
            return self::process_form();
        } elseif (
            $action == 'queue' and isset($_REQUEST['_wpnonce']) and
            wp_verify_nonce($_REQUEST['_wpnonce'], 'MeprUpdateCtrl::manually_queue_update')
        ) {
            MeprUpdateCtrl::manually_queue_update();
        } elseif ($action === 'upgrade') { // Manually upgrade the database.
            $mepr_app = new MeprAppCtrl();
            try {
                delete_transient('mepr_migration_error');
                $mepr_app->install();
                $message = __('Database Was Upgraded', 'memberpress');
                return self::display_form([], $message);
            } catch (MeprDbMigrationException $e) {
                return self::display_form([$e->getMessage()], '');
            }
        } elseif ($action === 'clear_tax_rates') {
            check_admin_referer('clear_tax_rates', 'mepr_taxes_nonce');
            MeprTaxRate::destroy_all();
            $message = __('Tax rates have been cleared', 'memberpress');
            return self::display_form([], $message);
        } else {
            return self::display_form();
        }
    }

    /**
     * Display the options form in the admin panel.
     *
     * @param array  $errors  List of errors to display.
     * @param string $message Message to display.
     *
     * @return void
     */
    public static function display_form($errors = [], $message = '')
    {
        $mepr_options = MeprOptions::fetch();

        if (MeprUtils::is_logged_in_and_an_admin()) {
            if (!empty($mepr_options->mothership_license)) {
                $li = get_site_transient('mepr_license_info');

                if ($li === false) {
                    MeprUpdateCtrl::manually_queue_update();
                    $li = get_site_transient('mepr_license_info');
                }
            }

            MeprView::render('/admin/options/form', get_defined_vars());
        }
    }

    /**
     * Process the options form submission.
     *
     * @return void
     */
    public static function process_form()
    {
        $mepr_options = MeprOptions::fetch();

        if (MeprUtils::is_logged_in_and_an_admin()) {
            $errors = MeprHooks::apply_filters('mepr-validate-options', $mepr_options->validate($_POST, []));

            if (empty($errors)) {
                MeprHooks::do_action('mepr-process-options', $_POST);
                $settings = MeprHooks::apply_filters('mepr-saved-options', $_POST);
                $mepr_options->update($settings);
                $mepr_options->store();

                // Ensure that the rewrite rules are flushed & in place.
                MeprUtils::flush_rewrite_rules(); // Don't call this before running ->update() - it borks stuff.

                $message = __('Options saved.', 'memberpress');
            }

            if (!empty($mepr_options->mothership_license)) {
                $li = get_site_transient('mepr_license_info');

                if ($li === false) {
                    MeprUpdateCtrl::manually_queue_update();
                    $li = get_site_transient('mepr_license_info');
                }
            }

            MeprView::render('/admin/options/form', get_defined_vars());
        }
    }

    /**
     * Enqueue footer scripts for the admin panel.
     *
     * @return void
     */
    public static function enqueue_footer_scripts()
    {
        global $hook_suffix;

        $mepr_options = MeprOptions::fetch();
        if ($hook_suffix == 'memberpress_page_memberpress-options' && isset($mepr_options->integrations)) {
            foreach ($mepr_options->integrations as $integration) {
                if (isset($integration['gateway']) && $integration['gateway'] === 'MeprPayPalCommerceGateway') {
                    ?>
      <script id="paypal-sdk-js" src="https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js"></script>
                    <?php
                    break;
                }
            }
        }
    }

    /**
     * Enqueue scripts and styles for the admin panel.
     *
     * @param string $hook The current admin page hook.
     *
     * @return void
     */
    public static function enqueue_scripts($hook)
    {
        if ($hook == 'memberpress_page_memberpress-options') {
            $mepr_options = MeprOptions::fetch();

            wp_register_style('mepr-clipboardtip', MEPR_CSS_URL . '/vendor/tooltipster.bundle.min.css', [], MEPR_VERSION);
            wp_register_style('mepr-clipboardtip-borderless', MEPR_CSS_URL . '/vendor/tooltipster-sideTip-borderless.min.css', ['mepr-clipboardtip'], MEPR_VERSION);
            wp_enqueue_style('mp-options', MEPR_CSS_URL . '/admin-options.css', ['mepr-settings-table-css','mepr-clipboardtip','mepr-clipboardtip-borderless'], MEPR_VERSION);
            wp_enqueue_style('mp-emails', MEPR_CSS_URL . '/admin-emails.css', ['mp-options'], MEPR_VERSION);

            $js_helpers = [
                'nameLabel'                             => __('Name:', 'memberpress'),
                'typeLabel'                             => __('Type:', 'memberpress'),
                'defaultLabel'                          => __('Default Value(s):', 'memberpress'),
                'signupLabel'                           => __('Show at Signup', 'memberpress'),
                'accountLabel'                          => __('Show in Account', 'memberpress'),
                'requiredLabel'                         => __('Required', 'memberpress'),
                'textOption'                            => __('Text', 'memberpress'),
                'textareaOption'                        => __('Textarea', 'memberpress'),
                'checkboxOption'                        => __('Checkbox', 'memberpress'),
                'dropdownOption'                        => __('Dropdown', 'memberpress'),
                'multiselectOption'                     => __('Multi-Select', 'memberpress'),
                'emailOption'                           => __('Email', 'memberpress'),
                'urlOption'                             => __('URL', 'memberpress'),
                'phoneOption'                           => __('Phone', 'memberpress'),
                'radiosOption'                          => __('Radio Buttons', 'memberpress'),
                'checkboxesOption'                      => __('Checkboxes', 'memberpress'),
                'fileuploadOption'                      => __('File Upload', 'memberpress'),
                'dateOption'                            => __('Date', 'memberpress'),
                'optionNameLabel'                       => __('Option Name:', 'memberpress'),
                'optionValueLabel'                      => __('Option Value:', 'memberpress'),
                'addOptionLabel'                        => __('Add Option', 'memberpress'),
                'show_fname_lname_id'                   => "#{$mepr_options->show_fname_lname_str}",
                'require_fname_lname_id'                => "#{$mepr_options->require_fname_lname_str}",
                'jsUrl'                                 => MEPR_JS_URL,
                'taxRateRemoveStr'                      => __('Are you sure you want to delete this Tax Rate?', 'memberpress'),
                'confirmPMDelete'                       => __('WARNING: Do not remove this Payment Method if you have active subscriptions using it. Doing so will prevent you from being notified of recurring payments for those subscriptions, which means your members will lose access to their paid content. Are you sure you want to delete this Payment Method?', 'memberpress'),
                'wpnonce'                               => wp_create_nonce(MEPR_PLUGIN_SLUG),
                'option_nonce'                          => wp_create_nonce('mepr_gateway_form_nonce'),
                'tax_nonce'                             => wp_create_nonce('mepr_taxes'),
                'activate_license_nonce'                => wp_create_nonce('mepr_activate_license'),
                'activation_error'                      => (
                    // Translators: %s: error message.
                    __('An error occurred during activation: %s', 'memberpress')
                ),
                'invalid_response'                      => __('Invalid response.', 'memberpress'),
                'ajax_error'                            => __('Ajax error.', 'memberpress'),
                'deactivate_license_nonce'              => wp_create_nonce('mepr_deactivate_license'),
                'deactivate_confirm'                    => sprintf(
                    // Translators: %s: site domain.
                    __('Are you sure? MemberPress will not be functional on %s if this License Key is deactivated.', 'memberpress'),
                    MeprUtils::site_domain()
                ),
                'deactivation_error'                    => (
                    // Translators: %s: error message.
                    __('An error occurred during deactivation: %s', 'memberpress')
                ),
                'install_license_edition_nonce'         => wp_create_nonce('mepr_install_license_edition'),
                'validate_stripe_payment_methods_nonce' => wp_create_nonce('mepr_validate_stripe_payment_method_types'),
                'activate_stripe_payment_method_nonce'  => wp_create_nonce('mepr_activate_stripe_payment_method'),
                'validate_stripe_tax_nonce'             => wp_create_nonce('mepr_validate_stripe_tax'),
                'unable_to_verify_stripe_tax'           => __('Unable to verify Stripe Tax status', 'memberpress'),
                'square_connect_nonce'                  => wp_create_nonce('mepr_square_connect'),
                'square_disconnect_confirm'             => __('Are you sure you want to disconnect this gateway? Disconnecting this gateway will prevent subscription payments from being recorded and new payments will stop working when the access token expires.', 'memberpress'),
            ];

            wp_register_script('memberpress-i18n', MEPR_JS_URL . '/i18n.js', ['jquery'], MEPR_VERSION);
            wp_register_script('mepr-uploader', MEPR_JS_URL . '/uploader.js', [], MEPR_VERSION);
            wp_enqueue_script('alpinejs', MEPR_JS_URL . '/vendor/alpine.min.js', [], MEPR_VERSION, true);
            wp_localize_script('memberpress-i18n', 'MeprI18n', ['states' => MeprUtils::states()]);

            wp_register_script('mepr-clipboard-js', MEPR_JS_URL . '/vendor/clipboard.min.js', [], MEPR_VERSION);
            wp_register_script('mepr-tooltipster', MEPR_JS_URL . '/vendor/tooltipster.bundle.min.js', ['jquery'], MEPR_VERSION);
            wp_register_script('mepr-copy-to-clipboard', MEPR_JS_URL . '/copy_to_clipboard.js', ['mepr-clipboard-js','mepr-tooltipster'], MEPR_VERSION);
            wp_localize_script('mepr-copy-to-clipboard', 'MeprClipboard', [
                'copy_text'       => __('Copy to Clipboard', 'memberpress'),
                'copied_text'     => __('Copied!', 'memberpress'),
                'copy_error_text' => __('Oops, Copy Failed!', 'memberpress'),
            ]);

            wp_enqueue_script(
                'mepr-options-js',
                MEPR_JS_URL . '/admin_options.js',
                [
                    'jquery',
                    'mepr-copy-to-clipboard',
                    'mepr-settings-table-js',
                    'mepr-admin-shared-js',
                    'jquery-ui-sortable',
                    'memberpress-i18n',
                ],
                MEPR_VERSION
            );
            wp_localize_script('mepr-options-js', 'MeprOptions', $js_helpers);

            $email_locals = [
                'set_email_defaults_nonce' => wp_create_nonce('set_email_defaults'),
                'send_test_email_nonce'    => wp_create_nonce('send_test_email'),
            ];
            wp_enqueue_script('mepr-emails-js', MEPR_JS_URL . '/admin_emails.js', ['mepr-options-js'], MEPR_VERSION);
            wp_localize_script('mepr-emails-js', 'MeprEmail', $email_locals);
            MeprHooks::do_action('mepr-options-admin-enqueue-script', $hook);
        }
    }

    /**
     * Render the gateway form via AJAX.
     *
     * @return void
     */
    public static function gateway_form()
    {
        check_ajax_referer('mepr_gateway_form_nonce', 'option_nonce');

        if (!is_admin()) {
            die(json_encode(['error' => __('Unauthorized', 'memberpress')]));
        }

        $mepr_options = MeprOptions::fetch();

        if (!isset($_POST['g']) or empty($_POST['g'])) {
            $gateways = array_keys(MeprGatewayFactory::all());

            if (empty($gateways)) {
                die(json_encode(['error' => __('No gateways were found', 'memberpress')]));
            }

            // Artificially set the gateway to the first available.
            $gateway = $gateways[0];
        } else {
            $gateway = $_POST['g'];
        }

        try {
            $obj = MeprGatewayFactory::fetch($gateway);
        } catch (Exception $e) {
            die($e->getMessage());
        }

        ob_start();
        MeprView::render('/admin/options/gateway', get_defined_vars());
        $form = ob_get_clean();

        die(json_encode([
            'form' => $form,
            'id'   => $obj->id,
        ]));
    }

    /**
     * Activate a license via AJAX.
     *
     * @return void
     */
    public static function ajax_activate_license()
    {
        if (!MeprUtils::is_post_request() || !isset($_POST['key']) || !is_string($_POST['key'])) {
            wp_send_json_error(sprintf(
                // Translators: %s: error message.
                __('An error occurred during activation: %s', 'memberpress'),
                __('Bad request.', 'memberpress')
            ));
        }

        if (!MeprUtils::is_logged_in_and_an_admin()) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_activate_license', false, false)) {
            wp_send_json_error(sprintf(
                // Translators: %s: error message.
                __('An error occurred during activation: %s', 'memberpress'),
                __('Security check failed.', 'memberpress')
            ));
        }

        $mepr_options = MeprOptions::fetch();
        $license_key  = sanitize_text_field(wp_unslash($_POST['key']));

        try {
            $act        = MeprUpdateCtrl::activate_license($license_key);
            $li         = get_site_transient('mepr_license_info');
            $onboarding = isset($_POST['onboarding']) && sanitize_text_field(wp_unslash($_POST['onboarding'])) == '1';

            if ($onboarding) {
                $output = '';
            } else {
                $output = sprintf('<div class="notice notice-success inline"><p>%s</p></div>', esc_html($act['message']));
            }

            if (is_array($li)) {
                $editions          = MeprUtils::is_incorrect_edition_installed();
                $automatic_updates = !empty($mepr_options->auto_updates) ? $mepr_options->auto_updates : 'all';

                if (is_array($editions) && $editions['license']['index'] > $editions['installed']['index'] && $automatic_updates != 'none') {
                    // The installed plugin is a lower edition, try to upgrade to the higher license edition.
                    if (!empty($li['url']) && MeprUtils::is_url($li['url'])) {
                        $result = self::install_plugin_silently($li['url'], ['overwrite_package' => true]);

                        if ($result === true) {
                            do_action('mepr_plugin_edition_changed');
                            wp_send_json_success(true);
                        }
                    }
                }

                if ($onboarding) {
                    $output .= MeprView::get_string('/admin/onboarding/active_license', get_defined_vars());
                } else {
                    $output .= MeprView::get_string('/admin/options/active_license', get_defined_vars());
                }
            } else {
                $output .= sprintf('<div class="notice notice-warning"><p>%s</p></div>', esc_html__('The license information is not available, try refreshing the page.', 'memberpress'));
            }

            wp_send_json_success($output);
        } catch (Exception $e) {
            try {
                $expires = MeprUpdateCtrl::send_mothership_request("/license_keys/expires_at/$license_key");

                if (isset($expires['expires_at'])) {
                    $expires_at = strtotime($expires['expires_at']);

                    if ($expires_at && $expires_at < time()) {
                        $licenses = MeprUpdateCtrl::send_mothership_request("/license_keys/list_keys/$license_key");

                        if (!empty($licenses) && is_array($licenses)) {
                              $highest_edition_index = -1;
                              $highest_license       = null;

                            foreach ($licenses as $license) {
                                $edition = MeprUtils::get_edition($license['product_slug']);

                                if (is_array($edition) && $edition['index'] > $highest_edition_index) {
                                          $highest_edition_index = $edition['index'];
                                          $highest_license       = $license;
                                }
                            }

                            if (is_array($highest_license)) {
                                wp_send_json_error(
                                    sprintf(
                                    // Translators: %1$s: the product name, %2$s: open link tag, %3$s: close link tag.
                                        esc_html__('This License Key has expired, but you have an active license for %1$s, %2$sclick here%3$s to activate using this license instead.', 'memberpress'),
                                        '<strong>' . esc_html($highest_license['product_name']) . '</strong>',
                                        sprintf('<a href="#" id="mepr-activate-new-license" data-license-key="%s">', esc_attr($highest_license['license_key'])),
                                        '</a>'
                                    )
                                );
                            }
                        }
                    }
                }
            } catch (Exception $ignore) {
                // Nothing we can do, let it fail.
            }

            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Deactivate a license via AJAX.
     *
     * @return void
     */
    public static function ajax_deactivate_license()
    {
        if (!MeprUtils::is_post_request()) {
            wp_send_json_error(sprintf(
                // Translators: %s: error message.
                __('An error occurred during deactivation: %s', 'memberpress'),
                __('Bad request.', 'memberpress')
            ));
        }

        if (!MeprUtils::is_logged_in_and_an_admin()) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_deactivate_license', false, false)) {
            wp_send_json_error(sprintf(
                // Translators: %s: error message.
                __('An error occurred during deactivation: %s', 'memberpress'),
                __('Security check failed.', 'memberpress')
            ));
        }

        $mepr_options = MeprOptions::fetch();
        $act          = MeprUpdateCtrl::deactivate_license();

        $output  = sprintf('<div class="notice notice-success"><p>%s</p></div>', esc_html($act['message']));
        $output .= MeprView::get_string('/admin/options/inactive_license', get_defined_vars());

        wp_send_json_success($output);
    }

    /**
     * Silently install a plugin from a given URL.
     *
     * @param string $url  The URL of the plugin to install.
     * @param array  $args Additional arguments for installation.
     *
     * @return boolean|WP
     */
    public static function install_plugin_silently($url, $args)
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $skin     = new Automatic_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);

        if (!$skin->request_filesystem_credentials(false, WP_PLUGIN_DIR)) {
            return new WP_Error('no_filesystem_access', __('Failed to get filesystem access', 'memberpress'));
        }

        return $upgrader->install($url, $args);
    }

    /**
     * Install a license edition via AJAX.
     *
     * @return void
     */
    public static function ajax_install_license_edition()
    {
        if (!MeprUtils::is_post_request()) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        if (!current_user_can('update_plugins')) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_install_license_edition', false, false)) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }

        $li = get_site_transient('mepr_license_info');

        if (!empty($li) && is_array($li) && !empty($li['url']) && MeprUtils::is_url($li['url'])) {
            $result = self::install_plugin_silently($li['url'], ['overwrite_package' => true]);

            if ($result instanceof WP_Error) {
                wp_send_json_error($result->get_error_message());
            } elseif ($result === true) {
                do_action('mepr_plugin_edition_changed');
                wp_send_json_success(__('The correct edition of MemberPress has been installed successfully.', 'memberpress'));
            } else {
                wp_send_json_error(__('Failed to install the correct edition of MemberPress, please download it from memberpress.com and install it manually.', 'memberpress'));
            }
        }

        wp_send_json_error(__('License data not found', 'memberpress'));
    }

    /**
     * Validate Stripe payment method types via AJAX.
     *
     * @return void
     */
    public static function validate_stripe_payment_method_types()
    {
        if (!MeprUtils::is_post_request()) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        if (!MeprUtils::is_logged_in_and_an_admin()) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_validate_stripe_payment_method_types', false, false)) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }

        $gateway_id           = isset($_POST['gateway_id']) ? sanitize_text_field(wp_unslash($_POST['gateway_id'])) : '';
        $payment_method_types = isset($_POST['payment_method_types']) && is_array($_POST['payment_method_types']) ? array_filter(array_map('sanitize_key', array_map('wp_unslash', $_POST['payment_method_types']))) : [];

        if (empty($gateway_id) || empty($payment_method_types)) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        $mepr_options = MeprOptions::fetch();
        $pm           = $mepr_options->payment_method($gateway_id);

        if (!$pm instanceof MeprStripeGateway) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        try {
            if (in_array('link', $payment_method_types, true) && !self::is_payment_method_active('link', $pm->id)) {
                self::activate_payment_method('link', $pm);
            }

            $pm->create_test_payment_intent($payment_method_types);

            wp_send_json_success();
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Activate a Stripe payment method via AJAX.
     *
     * @return void
     * @throws Exception If the payment method couldn't be activated.
     */
    public static function activate_stripe_payment_method()
    {
        if (!MeprUtils::is_post_request()) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        if (!MeprUtils::is_logged_in_and_an_admin()) {
            wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_activate_stripe_payment_method', false, false)) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }

        $gateway_id          = isset($_POST['gateway_id']) ? sanitize_text_field(wp_unslash($_POST['gateway_id'])) : '';
        $payment_method_type = isset($_POST['payment_method_type']) ? sanitize_key(wp_unslash($_POST['payment_method_type'])) : '';
        $supported_types     = ['apple_pay', 'google_pay'];

        if (empty($gateway_id) || empty($payment_method_type) || !in_array($payment_method_type, $supported_types, true)) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        $mepr_options = MeprOptions::fetch();
        $pm           = $mepr_options->payment_method($gateway_id);

        if (!$pm instanceof MeprStripeGateway) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        try {
            if ($payment_method_type == 'apple_pay') {
                $root_dir                     = isset($_SERVER['DOCUMENT_ROOT']) ? wp_unslash($_SERVER['DOCUMENT_ROOT']) : ABSPATH;
                $domain_association_file_name = 'apple-developer-merchantid-domain-association';
                $well_known_dir               = untrailingslashit($root_dir) . '/.well-known';
                $full_path                    = "$well_known_dir/$domain_association_file_name";

                if (!file_exists($full_path)) {
                    if (!is_dir($well_known_dir) && !@mkdir($well_known_dir, 0755) && !is_dir($well_known_dir)) {
                        throw new Exception(__('Unable to create domain association folder in domain root', 'memberpress'));
                    }

                    if (!@copy(MEPR_GATEWAYS_PATH . "/stripe/$domain_association_file_name", $full_path)) {
                        throw new Exception(__('Unable to copy the domain association file', 'memberpress'));
                    }
                }
            }

            self::activate_payment_method($payment_method_type, $pm);

            wp_send_json_success();
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Is the given payment method active on the given Stripe gateway?
     *
     * Checks that the current site is registered as a payment method domain, and that the given payment method is active.
     *
     * @param  string $payment_method_type The payment method type: 'apple_pay', 'google_pay' or 'link'.
     * @param  string $gateway_id          The ID of the Stripe payment method.
     * @return boolean
     */
    public static function is_payment_method_active($payment_method_type, $gateway_id)
    {
        $pmd = get_option("mepr_stripe_payment_method_domain_$gateway_id");

        if (
            is_array($pmd) &&
            isset($pmd['domain_name']) && $pmd['domain_name'] == self::get_site_domain() &&
            isset($pmd['enabled']) && $pmd['enabled'] &&
            isset($pmd[$payment_method_type]['status']) && $pmd[$payment_method_type]['status'] == 'active'
        ) {
            return true;
        }

        return false;
    }

    /**
     * Activate the given payment method on the given gateway
     *
     * Registers the current site as a Stripe payment method domain, in both live and test mode, and ensures that the
     * given payment method is active.
     *
     * @param  string            $payment_method_type The payment method type: 'apple_pay', 'google_pay' or 'link'.
     * @param  MeprStripeGateway $pm                  The Stripe gateway object.
     * @throws Exception If the payment method couldn't be activated.
     *
     * @return void
     */
    public static function activate_payment_method($payment_method_type, MeprStripeGateway $pm)
    {
        $domain = self::get_site_domain();

        add_filter('mepr_stripe_is_test_mode', '__return_false');

        $live = $pm->send_stripe_request('payment_method_domains', [
            'domain_name' => $domain,
            'enabled'     => 'true',
        ], 'post');

        if ($live[$payment_method_type]['status'] != 'active') {
            $live = $pm->send_stripe_request("payment_method_domains/{$live['id']}/validate", [], 'post');

            if ($live[$payment_method_type]['status'] != 'active') {
                if (isset($live[$payment_method_type]['status_details']['error_message'])) {
                    $message = $live[$payment_method_type]['status_details']['error_message'];
                } else {
                    $message = sprintf(
                        // Translators: %s: payment method type.
                        __('Unable to activate payment method `%s`', 'memberpress'),
                        $payment_method_type
                    );
                }

                throw new Exception($message);
            }
        }

        remove_filter('mepr_stripe_is_test_mode', '__return_false');

        update_option("mepr_stripe_payment_method_domain_$pm->id", $live);

        add_filter('mepr_stripe_is_test_mode', '__return_true');

        try {
            $test = $pm->send_stripe_request('payment_method_domains', [
                'domain_name' => $domain,
                'enabled'     => 'true',
            ], 'post');

            if ($test[$payment_method_type]['status'] != 'active') {
                $pm->send_stripe_request("payment_method_domains/{$test['id']}/validate", [], 'post');
            }
        } catch (Exception $e) {
            // Ignore exceptions in Test mode.
        }

        remove_filter('mepr_stripe_is_test_mode', '__return_true');
    }

    /**
     * Get the domain of the current site, to be registered as a payment method domain
     *
     * @return string
     */
    private static function get_site_domain()
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : str_replace(['https://', 'http://'], '', get_site_url());
    }
}
