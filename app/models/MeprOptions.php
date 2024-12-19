<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * To add new dynamic options please edit the config file in lib/data/options/dynamic_attrs.json
*/
#[AllowDynamicProperties]
class MeprOptions
{
    public $dynamic_attrs;

    public function __construct($options = [])
    {
        $this->set_strings();
        $this->set_from_array($options);
        $this->set_defaults();
        $this->set_dynamic_attrs();
        $this->wpml_custom_fields(); // Need to store as an array for WPML - this converts back to objects :)
    }

    /**
     * Fetch the MeprOptions instance
     *
     * @param  boolean $force Force a fresh instance
     * @return self
     */
    public static function fetch($force = false)
    {
        static $mepr_options;

        if (!isset($mepr_options) or $force) {
            $mepr_options_array = get_option(MEPR_OPTIONS_SLUG);

            if (!$mepr_options_array) {
                $mepr_options = new MeprOptions(); // Just grab the defaults
            } elseif (is_object($mepr_options_array) and is_a($mepr_options_array, 'MeprOptions')) {
                $mepr_options = $mepr_options_array;
                $mepr_options->set_defaults();
                $mepr_options->store(false); // store will convert this back into an array
            } elseif (!is_array($mepr_options_array)) {
                $mepr_options = new MeprOptions(); // Just grab the defaults
            } else {
                $mepr_options = new MeprOptions($mepr_options_array); // Sets defaults for unset options
            }
        }

        $mepr_options->set_strings(); // keep strings fresh (not db cached)
        $mepr_options->wpml_custom_fields();  // Need to store as an array for WPML - this converts back to objects :)
        return MeprHooks::apply_filters('mepr_fetch_options', $mepr_options);
    }

    public function wpml_custom_fields()
    {
        if (!empty($this->custom_fields)) {
            $new_fields = [];
            foreach ($this->custom_fields as $row) {
                $row = (object)$row; // Convert rows back to objects

                $new_options = [];
                if (!empty($row->options)) {
                    foreach ($row->options as $option) {
                        $new_options[] = (object)$option; // Convert options back into objects
                    }

                    $row->options = $new_options;
                }

                $new_fields[] = $row;
            }

            $this->custom_fields = $new_fields;
        }

        return $this->custom_fields;
    }

    public static function reset()
    {
        delete_option(MEPR_OPTIONS_SLUG);
    }

    // This is used to allow permalinks to be retrieved
    // Early on in the game
    public function populate_rewrite()
    {
        if (empty($GLOBALS['wp_rewrite'])) {
            $GLOBALS['wp_rewrite'] = new WP_Rewrite();
        }
    }

    public function set_dynamic_attrs()
    {
        // TODO: We will want to migrate everything to using these dynamic type variables as we continue
        $json = file_get_contents(MEPR_DATA_PATH . '/options/dynamic_attrs.json');
        $attrs = json_decode($json, true);
        $this->dynamic_attrs = MeprHooks::apply_filters('mepr-options-dynamic-attrs', $attrs);
    }

    public function set_defaults()
    {
        $mepr_blogname = MeprUtils::blogname();

        if (!isset($this->legacy_integrations)) {
            $this->legacy_integrations = [];
        }

        if (!isset($this->account_page_id)) {
            $this->account_page_id = 0;
        }

        if (!isset($this->login_page_id)) {
            $this->login_page_id = 0;
        }

        if (!isset($this->thankyou_page_id)) {
            $this->thankyou_page_id = 0;
        }

        if (!isset($this->coaching_page_id)) {
            $this->coaching_page_id = 0;
        }

        if (!isset($this->force_login_page_url)) { // Forces wp's login_url filter to be overridden with MP login page permalink
            $this->force_login_page_url = false;
        }

        if (!isset($this->login_redirect_url)) {
            $this->populate_rewrite();
            $this->login_redirect_url = $this->account_page_url();
        }

        if (!isset($this->logout_redirect_url)) {
            $this->logout_redirect_url = '';
        }

        if (!isset($this->disable_mod_rewrite)) {
            $this->disable_mod_rewrite = true;
        }

        if (!isset($this->hide_admin_bar_menu)) {
            $this->hide_admin_bar_menu = false;
        }

        if (!isset($this->anti_card_testing_enabled)) {
            $this->anti_card_testing_enabled = true;
        }

        if (!isset($this->anti_card_testing_ip_method)) {
            $this->anti_card_testing_ip_method = '';
        }

        if (!isset($this->anti_card_testing_blocked)) {
            $this->anti_card_testing_blocked = [];
        }

        if (!isset($this->emails)) {
            $this->emails = [];

            // This is all for Backwards compatibility
            $this->emails['MeprAdminSignupEmail']  = [];
            $this->emails['MeprAdminReceiptEmail'] = [];
            $this->emails['MeprUserWelcomeEmail']  = [];
            $this->emails['MeprUserReceiptEmail']  = [];

            if (isset($this->admin_email)) {
                $this->emails['MeprAdminSignupEmail']['enabled'] = $this->admin_email;
            }
            if (isset($this->admin_email_subject)) {
                $this->emails['MeprAdminSignupEmail']['subject'] = $this->admin_email_subject;
            }
            if (isset($this->admin_email_body)) {
                $this->emails['MeprAdminSignupEmail']['body'] = MeprOptionsHelper::format_plaintext_email($this->admin_email_body);
            }

            if (isset($this->admin_user_receipt_email)) {
                $this->emails['MeprAdminReceiptEmail']['enabled'] = $this->admin_user_receipt_email;
            }

            if (isset($this->admin_user_receipt_email_subject)) {
                $this->emails['MeprAdminReceiptEmail']['subject'] = $this->admin_user_receipt_email_subject;
            }

            if (isset($this->admin_user_receipt_email_body)) {
                $this->emails['MeprAdminReceiptEmail']['body'] = MeprOptionsHelper::format_plaintext_email($this->admin_user_receipt_email_body);
            }

            if (isset($this->user_email)) {
                $this->emails['MeprUserWelcomeEmail']['enabled'] = $this->user_email;
            }
            if (isset($this->user_email_subject)) {
                $this->emails['MeprUserWelcomeEmail']['subject'] = $this->user_email_subject;
            }
            if (isset($this->user_email_body)) {
                $this->emails['MeprUserWelcomeEmail']['body'] = MeprOptionsHelper::format_plaintext_email($this->user_email_body);
            }

            if (isset($this->user_receipt_email)) {
                $this->emails['MeprUserReceiptEmail']['enabled'] = $this->user_receipt_email;
            }
            if (isset($this->user_receipt_email_subject)) {
                $this->emails['MeprUserReceiptEmail']['subject'] = $this->user_receipt_email_subject;
            }
            if (isset($this->user_receipt_email_body)) {
                $this->emails['MeprUserReceiptEmail']['body'] = MeprOptionsHelper::format_plaintext_email($this->user_receipt_email_body);
            }
        }

        foreach (MeprEmailFactory::all('MeprBaseOptionsEmail') as $email) {
            $classname = get_class($email);
            if (!isset($this->emails[$classname])) {
                $this->emails[$classname] = $email->defaults;
            }
        }

        // Account CSS Settings
        if (!isset($this->account_css_width)) {
            $this->account_css_width = 500;
        }

        if (!isset($this->custom_message)) {
            $this->custom_message = sprintf(__('Welcome to %s', 'memberpress'), $mepr_blogname);
        }

        if (
            $this->thankyou_page_id == 0 or
            $this->login_page_id == 0
        ) {
            $this->setup_complete = 0;
        } else {
            $this->setup_complete = 1;
        }

        if (!isset($this->activated_timestamp)) {
            $this->activated_timestamp = time();
        }

        if (!isset($this->currency_code)) {
            $this->currency_code = 'USD';
        }

        if (!isset($this->currency_symbol)) {
            $this->currency_symbol = '$';
        }

        if (!isset($this->currency_symbol_after)) {
            $this->currency_symbol_after = false;
        }

        if (!isset($this->language_code)) {
            $this->language_code = 'US';
        }

        if (!isset($this->integrations)) {
            $this->integrations = [];
        }

        if (!isset($this->lock_wp_admin)) {
            $this->lock_wp_admin = true;
        }

        if (!isset($this->enforce_strong_password)) {
            $this->enforce_strong_password = 0; // 0 (off), weak, medium, strong
        }

        if (!isset($this->disable_wp_registration_form)) {
            $this->disable_wp_registration_form = true;
        }

        if (!isset($this->disable_wp_admin_bar)) {
            $this->disable_wp_admin_bar = true;
        }

        if (!isset($this->pro_rated_upgrades)) {
            $this->pro_rated_upgrades = true;
        }

        if (!isset($this->disable_checkout_password_fields)) {
            $this->disable_checkout_password_fields = false;
        }

        if (!isset($this->enable_spc)) {
            $this->enable_spc = false;
        }

        if (!isset($this->enable_spc_invoice)) {
            $this->enable_spc_invoice = false;
        }

        if (!isset($this->coupon_field_enabled)) {
            $this->coupon_field_enabled = true;
        }

        if (!isset($this->require_tos)) {
            $this->require_tos = false;
        }

        if (!isset($this->require_privacy_policy)) {
            $this->require_privacy_policy = false;
        }

        if (!isset($this->tos_url)) {
            $this->tos_url = '';
        }

        if (!isset($this->tos_title)) {
            $this->tos_title = _x('I have read and agree to the Terms Of Service', 'ui', 'memberpress'); // This string is also below, so if we change this wording, we need to change it below also
        }

        if (!isset($this->privacy_policy_title)) {
            $this->privacy_policy_title = _x('This site collects names, emails and other user information. I consent to the terms set forth in the %Privacy Policy%.', 'ui', 'memberpress');
        }

        if (!isset($this->mail_send_from_name)) {
            $this->mail_send_from_name = MeprUtils::blogname();
        }

        if (!isset($this->mail_send_from_email)) {
            $this->mail_send_from_email = get_option('admin_email');
        }

        if (!isset($this->username_is_email)) {
            $this->username_is_email = false;
        }

        if (!isset($this->show_fname_lname)) {
            $this->show_fname_lname = true;
        }

        if (!isset($this->require_fname_lname)) {
            $this->require_fname_lname = false;
        }

        if (!isset($this->show_address_fields)) {
            $this->show_address_fields = false;
        }

        if (!isset($this->require_address_fields)) {
            $this->require_address_fields = true;
        }

        if (!isset($this->show_fields_logged_in_purchases)) {
            $this->show_fields_logged_in_purchases = true; // Changing this to true as we get way too many emails about this in support
        }

        $this->set_address_fields();

        if (!isset($this->custom_fields)) { // should be an array of objects but we store as just arrays in the DB for WPML compatibility - see $this->wpml_custom_fields()
            $this->custom_fields = [];
        }

        if (!isset($this->mothership_license)) {
            $this->mothership_license = '';
        }

        if (!isset($this->edge_updates)) {
            $this->edge_updates = false;
        }

        if (!isset($this->auto_updates)) {
            $this->auto_updates = false;
        }

        if (!isset($this->product_pages_slug)) {
            $this->product_pages_slug = 'register';
        }

        if (!isset($this->group_pages_slug)) {
            $this->group_pages_slug = 'plans';
        }

        if (!isset($this->admin_email_addresses)) {
            $this->admin_email_addresses = get_option('admin_email'); // default to admin_email
        }

        if (!isset($this->unauthorized_message)) {
            $this->unauthorized_message = MeprHooks::apply_filters('mepr-unauthorized-message', __('You are unauthorized to view this page.', 'memberpress'));
        }

        // For backwards compatibility
        if (!isset($this->redirect_on_unauthorized)) {
            // For backwards compatibility
            if (
                isset($this->unauthorized_page_id) and
                is_numeric($this->unauthorized_page_id) and
                (int)$this->unauthorized_page_id > 0
            ) {
                $this->redirect_on_unauthorized  = true;
                $this->populate_rewrite();
                $this->unauthorized_redirect_url = MeprUtils::get_permalink($this->unauthorized_page_id);
            } else {
                $this->redirect_on_unauthorized = false;
            }
        }

        if (!isset($this->unauthorized_redirect_url)) {
            $this->populate_rewrite();
            $this->unauthorized_redirect_url = $this->login_page_url();
        }

        if (!isset($this->redirect_non_singular)) {
            $this->redirect_non_singular = false;
        }

        if (!isset($this->redirect_method)) {
            $this->redirect_method = 'template_redirect';
        }

        if (!isset($this->unauth_show_excerpts)) {
            $this->unauth_show_excerpts = false;
        }

        if (!isset($this->unauth_excerpt_type)) {
            $this->unauth_excerpt_type = 'excerpt';
        }

        if (!isset($this->unauth_excerpt_size)) {
            $this->unauth_excerpt_size = 100;
        }

        if (!isset($this->unauth_show_login)) {
            $this->unauth_show_login = true;
        }

        if (!isset($this->authorize_seo_views)) {
            $this->authorize_seo_views = false;
        }

        if (!isset($this->seo_unauthorized_noindex)) {
            $this->seo_unauthorized_noindex = false;
        }

        if (!isset($this->paywall_enabled)) {
            $this->paywall_enabled = false;
        }

        if (!isset($this->paywall_num_free_views)) {
            $this->paywall_num_free_views = 1;
        }

        if (!isset($this->disable_summary_email)) {
            $this->disable_summary_email = false;
        }

        // Option to disable the grace days (Account tab of MP Options now)
        if (!isset($this->disable_grace_init_days)) {
            $this->disable_grace_init_days = false;
        }

        // How many days will the users get free access before their first
        // payment trial days in the membership will override this value
        if (isset($this->disable_grace_init_days) && $this->disable_grace_init_days) {
            $this->grace_init_days = MeprHooks::apply_filters('mepr-grace-init-days', 0);
        } else {
            $this->grace_init_days = MeprHooks::apply_filters('mepr-grace-init-days', 1);
        }

        // Do we want some overlap in expirations?
        $this->grace_expire_days = MeprHooks::apply_filters('mepr-grace-expire-days', 0);

        if (!isset($this->allow_cancel_subs)) {
            $this->allow_cancel_subs = true;
        }

        if (!isset($this->allow_suspend_subs)) {
            $this->allow_suspend_subs = false;
        }

        if (!isset($this->disable_global_autoresponder_list)) {
            $this->disable_global_autoresponder_list = false;
        }

        if (!isset($this->opt_in_checked_by_default)) {
            $this->opt_in_checked_by_default = false;
        }

        if (!isset($this->global_styles)) {
            $this->global_styles = false;
        }

        if (!isset($this->include_email_privacy_link)) {
            $this->include_email_privacy_link = false;
        }

        if (!isset($this->sslverify)) {
            $this->sslverify = true;
        }

        if (!isset($this->design_logo_img)) {
            $this->design_logo_img = '';
        }

        if (!isset($this->design_primary_color)) {
            $this->design_primary_color = '#06429E';
        }

        if (!isset($this->design_enable_checkout_template)) {
            $this->design_enable_checkout_template = false;
        }

        if (!isset($this->design_enable_login_template)) {
            $this->design_enable_login_template = false;
        }

        if (!isset($this->design_show_login_welcome_image)) {
            $this->design_show_login_welcome_image = false;
        }

        if (!isset($this->design_login_welcome_img)) {
            $this->design_login_welcome_img = '';
        }

        if (!isset($this->design_enable_account_template)) {
            $this->design_enable_account_template = false;
        }

        if (!isset($this->design_show_account_welcome_image)) {
            $this->design_show_account_welcome_image = false;
        }

        if (!isset($this->design_account_welcome_img)) {
            $this->design_account_welcome_img = '';
        }

        if (!isset($this->design_enable_courses_template)) {
            $this->design_enable_courses_template = '';
        }

        if (!isset($this->design_enable_thankyou_template)) {
            $this->design_enable_thankyou_template = false;
        }

        if (!isset($this->design_show_thankyou_welcome_image)) {
            $this->design_show_thankyou_welcome_image = false;
        }

        if (!isset($this->design_thankyou_hide_invoice)) {
            $this->design_thankyou_hide_invoice = '';
        }

        if (!isset($this->design_thankyou_invoice_message)) {
            $this->design_thankyou_invoice_message = '';
        }

        if (!isset($this->design_thankyou_welcome_img)) {
            $this->design_thankyou_welcome_img = '';
        }

        if (!isset($this->design_enable_pricing_template)) {
            $this->design_enable_pricing_template = false;
        }

        if (!isset($this->design_pricing_title)) {
            $this->design_pricing_title = '';
        }

        if (!isset($this->design_pricing_cta_color)) {
            $this->design_pricing_cta_color = '';
        }

        if (!isset($this->design_pricing_subheadline)) {
            $this->design_pricing_subheadline = '';
        }

        if (!isset($this->rl_enable_coaching_template)) {
            $this->rl_enable_coaching_template = true;
        }

        if (!isset($this->rl_enable_wp_footer)) {
            $this->rl_enable_wp_footer = 'enabled';
        }
    }

    public function set_strings()
    {
        $this->account_page_id_str                      = 'mepr-account-page-id';
        $this->login_page_id_str                        = 'mepr-login-page-id';
        $this->thankyou_page_id_str                     = 'mepr-thankyou-page-id';
        $this->coaching_page_id_str                     = 'mepr-coaching-page-id';
        $this->force_login_page_url_str                 = 'mepr-force-login-page-url';
        $this->login_redirect_url_str                   = 'mepr-login-redirect-url';
        $this->logout_redirect_url_str                  = 'mepr-logout-redirect-url';
        $this->account_css_width_str                    = 'mepr-account-css-width';
        $this->disable_mod_rewrite_str                  = 'mepr-disable-mod-rewrite';
        $this->hide_admin_bar_menu_str                  = 'mepr-hide-admin-bar-menu';
        $this->anti_card_testing_enabled_str            = 'mepr-anti-card-testing-enabled';
        $this->anti_card_testing_ip_method_str          = 'mepr-anti-card-testing-ip-method';
        $this->anti_card_testing_blocked_str            = 'mepr-anti-card-testing-blocked';
        $this->admin_email_str                          = 'mepr-admin-email';
        $this->admin_email_subject_str                  = 'mepr-admin-email-subject';
        $this->admin_email_body_str                     = 'mepr-admin-email-body';
        $this->admin_user_receipt_email_str             = 'mepr-admin-user-receipt-email';
        $this->admin_user_receipt_email_subject_str     = 'mepr-admin-user-receipt-email-subject';
        $this->admin_user_receipt_email_body_str        = 'mepr-admin-user-receipt-email-body';
        $this->admin_expirations_sent_email_str         = 'mepr-admin-expirations-sent-email';
        $this->admin_expirations_sent_email_subject_str = 'mepr-admin-expirations-sent-email-subject';
        $this->admin_expirations_sent_email_body_str    = 'mepr-admin-expirations-sent-email-body';
        $this->user_email_str                           = 'mepr-user-email';
        $this->user_email_subject_str                   = 'mepr-user-email-subject';
        $this->user_email_body_str                      = 'mepr-user-email-body';
        $this->user_receipt_email_str                   = 'mepr-receipt-user-email';
        $this->user_receipt_email_subject_str           = 'mepr-receipt-user-email-subject';
        $this->user_receipt_email_body_str              = 'mepr-receipt-user-email-body';
        $this->new_drip_available_str                   = 'mepr-new-drip-available';
        $this->new_drip_available_subject_str           = 'mepr-new-drip-available-subject';
        $this->new_drip_available_body_str              = 'mepr-new-drip-available-body';
        $this->custom_message_str                       = 'mepr-custom-message';

        $this->currency_code_str                        = 'mepr-currency-code';
        $this->currency_symbol_str                      = 'mepr-currency-symbol';
        $this->currency_symbol_after_str                = 'mepr-currency-symbol-after';
        $this->language_code_str                        = 'mepr-language-symbol';
        $this->integrations_str                         = 'mepr-integrations';

        $this->lock_wp_admin_str                        = 'mepr-lock-wp-admin';
        $this->enforce_strong_password_str              = 'mepr-enforce-strong-password';
        $this->disable_wp_registration_form_str         = 'mepr-disable-wp-registration-form';
        $this->disable_wp_admin_bar_str                 = 'mepr-disable-wp-admin-bar';
        $this->pro_rated_upgrades_str                   = 'mepr-pro-rated-upgrades';
        $this->disable_checkout_password_fields_str     = 'mepr-disable-checkout-password-fields';
        $this->enable_spc_str                           = 'mepr-enable-spc';
        $this->enable_spc_invoice_str                   = 'mepr-enable-spc-invoice';
        $this->coupon_field_enabled_str                 = 'mepr-coupon-field-enabled';
        $this->require_tos_str                          = 'mepr-require-tos';
        $this->tos_url_str                              = 'mepr-tos-url';
        $this->tos_title_str                            = 'mepr-tos-title';
        $this->mail_send_from_name_str                  = 'mepr-mail-send-from-name';
        $this->mail_send_from_email_str                 = 'mepr-mail-send-from-email';
        $this->username_is_email_str                    = 'mepr-username-is-email';
        $this->require_fname_lname_str                  = 'mepr-require-fname-lname';
        $this->show_fname_lname_str                     = 'mepr-show-fname-lname';
        $this->show_address_fields_str                  = 'mepr-show-address-fields';
        $this->require_address_fields_str               = 'mepr-require-address-fields';
        $this->show_fields_logged_in_purchases_str      = 'mepr-show-fields-logged-in-purchases';
        $this->custom_fields_str                        = 'mepr-custom-fields';
        $this->mothership_license_str                   = 'mepr-mothership-license';
        $this->edge_updates_str                         = 'mepr-edge-updates';
        $this->auto_updates_str                         = 'mepr-auto-updates';
        $this->product_pages_slug_str                   = 'mepr-product-pages-slug';
        $this->group_pages_slug_str                     = 'mepr-group-pages-slug';
        $this->admin_email_addresses_str                = 'mepr-admin-email-addresses';
        $this->unauthorized_message_str                 = 'mepr-unauthorized-message';
        $this->unauth_show_excerpts_str                 = 'mepr-unauth-show-excerpts';
        $this->unauth_excerpt_size_str                  = 'mepr-unauth-excerpt-size';
        $this->unauth_excerpt_type_str                  = 'mepr-unauth-excerpt-type';
        $this->unauth_show_login_str                    = 'mepr-unauth-show-login';
        $this->disable_grace_init_days_str              = 'mepr-disable-grace-init-days';
        $this->authorize_seo_views_str                  = 'mepr-authorize-seo-views';
        $this->paywall_enabled_str                      = 'mepr-paywall-enabled';
        $this->paywall_num_free_views_str               = 'mepr-paywall-num-free-views';
        $this->disable_summary_email_str                = 'mepr-disable-summary-email';
        $this->seo_unauthorized_noindex_str             = 'mepr-seo-unauthorized-noindex';
        $this->emails_str                               = 'mepr-emails';
        $this->redirect_on_unauthorized_str             = 'mepr-redirect-on-unauthorized';
        $this->unauthorized_redirect_url_str            = 'mepr-unauthorized-redirect-url';
        $this->redirect_non_singular_str                = 'mepr-redirect-non-singular';
        $this->redirect_method_str                      = 'mepr-redirect-method';
        $this->allow_cancel_subs_str                    = 'mepr-allow-cancel-subs';
        $this->allow_suspend_subs_str                   = 'mepr-allow-suspend-subs';
        $this->disable_global_autoresponder_list_str    = 'mepr-disable-global-autoresponder-list';
        $this->opt_in_checked_by_default_str            = 'mepr-opt-in-checked-by-default';
        $this->global_styles_str                        = 'mepr-global-styles';
        $this->include_email_privacy_link_str           = 'mepr-email-privacy-link';
        $this->require_privacy_policy_str               = 'mepr-require-privacy-policy';
        $this->privacy_policy_title_str                 = 'mepr-privacy-policy-title';

        $this->design_logo_img_str                      = 'mepr-design-logo-img';
        $this->design_primary_color_str                 = 'mepr-design-primary-color';
        $this->design_enable_login_template_str         = 'mepr-design-enable-login-template';
        $this->design_show_login_welcome_image_str      = 'mepr-design-show-login-welcome-image';
        $this->design_login_welcome_img_str             = 'mepr-design-login-welcome-img';
        $this->design_enable_checkout_template_str      = 'mepr-design-enable-checkout-template';
        $this->design_enable_account_template_str       = 'mepr-design-enable-account-template';
        $this->design_show_account_welcome_image_str    = 'mepr-design-show-account-welcome-image';
        $this->design_account_welcome_img_str           = 'mepr-design-account-welcome-img';
        $this->design_enable_courses_template_str       = 'mepr-design-enable-courses-template';
        $this->design_enable_thankyou_template_str      = 'mepr-design-enable-thankyou-template';
        $this->design_show_thankyou_welcome_image_str   = 'mepr-design-show-thankyou-welcome-image';
        $this->design_thankyou_welcome_img_str          = 'mepr-design-thankyou-welcome-img';
        $this->design_thankyou_hide_invoice_str         = 'mepr-design-thankyou-hide-invoice';
        $this->design_thankyou_invoice_message_str      = 'mepr-design-thankyou-message';
        $this->design_enable_pricing_template_str       = 'mepr-design-enable-pricing-template';
        $this->design_pricing_title_str                 = 'mepr-design-pricing-title';
        $this->design_pricing_cta_color_str             = 'mepr-design-pricing-cta-color';
        $this->design_pricing_subheadline_str           = 'mepr-design-pricing-subheadline';
        $this->rl_enable_coaching_template_str          = 'mepr-rl-enable-coaching-template';
        $this->design_show_checkout_price_terms_str     = 'mepr-design-show-checkout-price-terms';
        $this->rl_enable_wp_footer_str                  = 'mepr-rl-enable-wp-footer';
    }

    public function validate($params, $errors = [])
    {
        // Validate all of the integrations ...
        if (!empty($params[$this->integrations_str]) and is_array($params[$this->integrations_str])) {
            foreach ($params[$this->integrations_str] as $pmt) {
                $obj = MeprGatewayFactory::fetch($pmt['gateway'], $pmt);
                $errors = $obj->validate_options_form($errors);
            }
        }

        if (!isset($params[$this->product_pages_slug_str])) {
            $errors[] = __('The Membership Pages Slug must be set', 'memberpress');
        }

        if (!isset($params[$this->group_pages_slug_str])) {
            $errors[] = __('The Group Pages Slug must be set', 'memberpress');
        }

        if (!preg_match('#^[a-zA-Z0-9\-]+$#', $params[$this->product_pages_slug_str])) {
            $errors[] = __('The Membership Pages Slug must only contain letters, numbers and dashes.', 'memberpress');
        }

        if (!preg_match('#^[a-zA-Z0-9\-]+$#', $params[$this->group_pages_slug_str])) {
            $errors[] = __('The Group Pages Slug must only contain letters, numbers and dashes.', 'memberpress');
        }

        if (!isset($params[$this->admin_email_addresses_str]) || empty($params[$this->admin_email_addresses_str])) {
            $errors[] = __('At least one Admin Email Address must be set', 'memberpress');
        }

        // {2,20} should match most of the new TLDs like .academy for example {2,4} was causing failed validation on the new TLDs
        if (!preg_match('#^\s*[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,20}(,\s*[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,20})*$#', $params[$this->admin_email_addresses_str])) {
            $errors[] = __('The Admin Email Address field must contain 1 or more valid email addresses', 'memberpress');
        }

        $biz_address_is_set = (
        isset($params['mepr_biz_name'])     && !empty($params['mepr_biz_name']) &&
        isset($params['mepr_biz_address1']) && !empty($params['mepr_biz_address1']) &&
        isset($params['mepr_biz_city'])     && !empty($params['mepr_biz_city']) &&
        isset($params['mepr_biz_state'])    && !empty($params['mepr_biz_state']) &&
        isset($params['mepr_biz_country'])  && !empty($params['mepr_biz_country']) &&
        isset($params['mepr_biz_postcode']) && !empty($params['mepr_biz_postcode'])
        );

        // Temporarily killing  this validation - causing too many support reqs
        // if(!$biz_address_is_set) {
        // $errors[] = __('You must enter your full Merchant Business Address (found on the Info tab).', 'memberpress');
        // }
        if (isset($params['mepr_biz_state'])) {
            $params['mepr_biz_state'] = strtoupper($params['mepr_biz_state']);
        }

        // TODO: We need to figure this one out ... this is still problemmatic for many users
        // if(isset($params['mepr_biz_country']) && isset($params['mepr_biz_state']) &&
        // ($states = MeprUtils::states()) && isset($states[$params['mepr_biz_country']]) &&
        // !isset($states[$params['mepr_biz_country']][$params['mepr_biz_state']])) {
        // $errors[] = __('You must enter a valid state code with your Merchant Business Address (found on the Info tab).', 'memberpress');
        // }
        return $errors;
    }

    public function update($params)
    {
        if (isset($params['mepr_biz_state'])) {
            $params['mepr_biz_state'] = strtoupper($params['mepr_biz_state']);
        }

        // Set dynamic variables (we should migrate all attrs to use this soon)
        $this->set_attrs_from_slugs($params);

        // Page Settings
        if (
            !is_numeric($params[$this->account_page_id_str]) and
            preg_match('#^__auto_page:(.*?)$#', $params[$this->account_page_id_str], $matches)
        ) {
            $this->account_page_id = $_POST[$this->account_page_id_str] = MeprAppHelper::auto_add_page($matches[1]);
        } else {
            $this->account_page_id = (int)$params[$this->account_page_id_str];
        }

        if (
            !is_numeric($params[$this->login_page_id_str]) and
            preg_match('#^__auto_page:(.*?)$#', $params[$this->login_page_id_str], $matches)
        ) {
            $this->login_page_id = $_POST[$this->login_page_id_str] = MeprAppHelper::auto_add_page($matches[1]);
        } else {
            $this->login_page_id = (int)$params[$this->login_page_id_str];
        }

        if (
            !is_numeric($params[$this->thankyou_page_id_str]) and
            preg_match('#^__auto_page:(.*?)$#', $params[$this->thankyou_page_id_str], $matches)
        ) {
            $this->thankyou_page_id = $_POST[$this->thankyou_page_id_str] = MeprAppHelper::auto_add_page($matches[1], esc_html__('Your subscription has been set up successfully.', 'memberpress'));
        } else {
            $this->thankyou_page_id = (int)$params[$this->thankyou_page_id_str];
        }

        if (isset($params[$this->coaching_page_id_str])) {
            if (
                !is_numeric($params[$this->coaching_page_id_str]) &&
                preg_match('#^__auto_page:(.*?)$#', $params[$this->coaching_page_id_str], $matches)
            ) :
                $this->coaching_page_id = $_POST[$this->coaching_page_id_str] = MeprAppHelper::auto_add_page($matches[1]);
            else :
                $this->coaching_page_id = (int)$params[$this->coaching_page_id_str];
            endif;
        }

        $this->force_login_page_url = isset($params[$this->force_login_page_url_str]);

        $this->login_redirect_url = (isset($params[$this->login_redirect_url_str]) && !empty($params[$this->login_redirect_url_str])) ? trim(stripslashes($params[$this->login_redirect_url_str])) : $this->account_page_url();

        $this->logout_redirect_url = (isset($params[$this->logout_redirect_url_str]) && !empty($params[$this->logout_redirect_url_str])) ? trim(stripslashes($params[$this->logout_redirect_url_str])) : '';

        // Notification Settings
        $this->emails = [];

        foreach ($params[$this->emails_str] as $email => $vals) {
            $this->emails[$email] = [
                'enabled'       => isset($params[$this->emails_str][$email]['enabled']),
                'use_template'  => isset($params[$this->emails_str][$email]['use_template']),
                'subject'       => trim(stripslashes($params[$this->emails_str][$email]['subject'])),
                'body'          => MeprUtils::maybe_wpautop(stripslashes($params[$this->emails_str][$email]['body'])),
            ];
        }

        $this->disable_mod_rewrite           = isset($params[$this->disable_mod_rewrite_str]);
        $this->hide_admin_bar_menu           = isset($params[$this->hide_admin_bar_menu_str]);
        $this->anti_card_testing_enabled     = isset($params[$this->anti_card_testing_enabled_str]);
        $this->anti_card_testing_ip_method   = isset($params[$this->anti_card_testing_ip_method_str]) ? sanitize_text_field(wp_unslash($params[$this->anti_card_testing_ip_method_str])) : '';
        $this->anti_card_testing_blocked     = isset($params[$this->anti_card_testing_blocked_str]) && is_string($params[$this->anti_card_testing_blocked_str]) ? array_unique(array_filter(array_map('trim', explode("\n", $params[$this->anti_card_testing_blocked_str])))) : [];

        $this->custom_message                = wp_kses_post(stripslashes($params[$this->custom_message_str]));
        $currency_code                       = stripslashes($params[$this->currency_code_str]);
        $currency_code_changed               = $this->currency_code != $currency_code;
        $this->currency_code                 = $currency_code;
        $this->currency_symbol               = stripslashes($params[$this->currency_symbol_str]);
        $this->currency_symbol_after         = isset($params[$this->currency_symbol_after_str]);
        $this->language_code                 = stripslashes($params[$this->language_code_str]);

        if (isset($params[$this->integrations_str])) {
            foreach ($params[$this->integrations_str] as $intg_key => $intg) {
                $params[$this->integrations_str][$intg_key]['use_icon'] = isset($params[$this->integrations_str][$intg_key]['use_icon']);
                $params[$this->integrations_str][$intg_key]['use_label'] = isset($params[$this->integrations_str][$intg_key]['use_label']);
                $params[$this->integrations_str][$intg_key]['use_desc'] = isset($params[$this->integrations_str][$intg_key]['use_desc']);

                if (isset($intg['gateway']) && $intg['gateway'] == 'MeprStripeGateway') {
                    if ($currency_code_changed || empty($params[$this->integrations_str][$intg_key]['payment_methods'])) {
                        // Set the Stripe payment methods to be an empty array if the currency code has changed
                        // or no payment methods were selected.
                        $params[$this->integrations_str][$intg_key]['payment_methods'] = [];
                    }
                }
            }
        }

        $this->integrations                     = (isset($params[$this->integrations_str])) ? $params[$this->integrations_str] : [];
        $this->lock_wp_admin                    = isset($params[$this->lock_wp_admin_str]);
        $this->enforce_strong_password          = $params[$this->enforce_strong_password_str];
        $this->disable_wp_registration_form     = isset($params[$this->disable_wp_registration_form_str]);
        $this->disable_wp_admin_bar             = isset($params[$this->disable_wp_admin_bar_str]);
        $this->pro_rated_upgrades               = isset($params[$this->pro_rated_upgrades_str]);
        $this->disable_checkout_password_fields = isset($params[$this->disable_checkout_password_fields_str]);
        $this->enable_spc                       = isset($params[$this->enable_spc_str]);
        $this->enable_spc_invoice               = isset($params[$this->enable_spc_invoice_str]);
        $this->coupon_field_enabled             = isset($params[$this->coupon_field_enabled_str]);
        $this->require_tos                      = isset($params[$this->require_tos_str]);
        $this->tos_url                          = (isset($params[$this->tos_url_str])) ? trim(stripslashes($params[$this->tos_url_str])) : '';
        $this->tos_title                        = (isset($params[$this->tos_title_str]) && !empty($params[$this->tos_title_str])) ? trim(stripslashes($params[$this->tos_title_str])) : _x('I have read and agree to the Terms of Service', 'ui', 'memberpress');
        $this->require_privacy_policy           = isset($params[$this->require_privacy_policy_str]);
        $this->privacy_policy_title             = (isset($params[$this->privacy_policy_title_str]) && !empty($params[$this->privacy_policy_title_str])) ? trim(stripslashes($params[$this->privacy_policy_title_str])) : _x('This site collects names, emails and other user information. I consent to the terms set forth in the %Privacy Policy%.', 'ui', 'memberpress');
        $this->mail_send_from_name              = (isset($params[$this->mail_send_from_name_str])) ? trim(stripslashes($params[$this->mail_send_from_name_str])) : MeprUtils::blogname();
        $this->mail_send_from_email             = (isset($params[$this->mail_send_from_email_str])) ? trim(stripslashes($params[$this->mail_send_from_email_str])) : get_option('admin_email');
        $this->username_is_email                = isset($params[$this->username_is_email_str]);
        $this->require_fname_lname              = isset($params[$this->require_fname_lname_str]);
        $this->show_fname_lname                 = isset($params[$this->show_fname_lname_str]);

        // This happens on the activate screen't do this here
        // $this->mothership_license                 = stripslashes($params[$this->mothership_license_str]);
        $this->product_pages_slug                 = sanitize_title(stripslashes($params[$this->product_pages_slug_str]), 'register');
        $this->group_pages_slug                   = sanitize_title(stripslashes($params[$this->group_pages_slug_str]), 'plans');
        $this->admin_email_addresses              = $params[$this->admin_email_addresses_str];
        $this->unauthorized_message               = wp_kses_post(stripslashes($params[$this->unauthorized_message_str]));
        $this->unauth_show_excerpts               = isset($params[$this->unauth_show_excerpts_str]);
        $this->unauth_excerpt_type                = $params[$this->unauth_excerpt_type_str];
        $this->unauth_excerpt_size                = $params[$this->unauth_excerpt_size_str];
        $this->unauth_show_login                  = isset($params[$this->unauth_show_login_str]);
        $this->disable_grace_init_days            = isset($params[$this->disable_grace_init_days_str]);
        $this->authorize_seo_views                = isset($params[$this->authorize_seo_views_str]);
        $this->seo_unauthorized_noindex           = isset($params[$this->seo_unauthorized_noindex_str]);
        $this->paywall_enabled                    = isset($params[$this->paywall_enabled_str]);
        $this->paywall_num_free_views             = (isset($params[$this->paywall_num_free_views_str])) ? (int)$params[$this->paywall_num_free_views_str] : 1;
        $this->disable_summary_email              = isset($params[$this->disable_summary_email_str]);
        $this->redirect_on_unauthorized           = isset($params[$this->redirect_on_unauthorized_str]);
        $this->unauthorized_redirect_url          = trim(stripslashes($params[$this->unauthorized_redirect_url_str]));
        $this->redirect_non_singular              = isset($params[$this->redirect_non_singular_str]);
        $this->redirect_method                    = $params[$this->redirect_method_str];
        $this->allow_cancel_subs                  = isset($params[$this->allow_cancel_subs_str]);
        $this->allow_suspend_subs                 = isset($params[$this->allow_suspend_subs_str]);
        $this->disable_global_autoresponder_list  = isset($params[$this->disable_global_autoresponder_list_str]);
        $this->opt_in_checked_by_default          = isset($params[$this->opt_in_checked_by_default_str]);
        $this->global_styles                      = isset($params[$this->global_styles_str]);
        $this->include_email_privacy_link         = isset($params[$this->include_email_privacy_link_str]);

        // Signup fields stuff
        $this->username_is_email                  = isset($params[$this->username_is_email_str]);
        $this->require_fname_lname                = isset($params[$this->require_fname_lname_str]);
        $this->show_fname_lname                   = (isset($params[$this->show_fname_lname_str]) || isset($params[$this->require_fname_lname_str]));
        $this->show_fields_logged_in_purchases    = isset($params[$this->show_fields_logged_in_purchases_str]);

        // Always show and require address fields when tax calculations are enabled
        $this->show_address_fields                = (isset($params[$this->show_address_fields_str]) || isset($params[$this->require_address_fields_str]) || isset($params['mepr_calculate_taxes']));
        $this->require_address_fields             = (isset($params[$this->require_address_fields_str]) || isset($params['mepr_calculate_taxes']));

        // We now support address being required -- handle that here
        $this->address_fields                     = $this->update_address_fields_required();
        $this->custom_fields                      = $this->update_custom_fields($params);

        // design
        $this->design_logo_img                    = absint($params[$this->design_logo_img_str]);
        $this->design_primary_color               = sanitize_text_field($params[$this->design_primary_color_str]);
        $this->design_enable_checkout_template    = isset($params[$this->design_enable_checkout_template_str]);
        $this->design_enable_login_template       = isset($params[$this->design_enable_login_template_str]);
        $this->design_show_login_welcome_image    = isset($params[$this->design_show_login_welcome_image_str]);
        $this->design_login_welcome_img           = absint($params[$this->design_login_welcome_img_str]);
        $this->design_enable_account_template     = isset($params[$this->design_enable_account_template_str]);
        $this->design_show_account_welcome_image  = isset($params[$this->design_show_account_welcome_image_str]);
        $this->design_account_welcome_img         = absint($params[$this->design_account_welcome_img_str]);
        $this->design_enable_courses_template     = isset($params[$this->design_enable_courses_template_str]);
        $this->design_enable_thankyou_template    = isset($params[$this->design_enable_thankyou_template_str]);
        $this->design_show_thankyou_welcome_image = isset($params[$this->design_show_thankyou_welcome_image_str]);
        $this->design_thankyou_hide_invoice       = isset($params[$this->design_thankyou_hide_invoice_str]);
        $this->design_thankyou_invoice_message    = wp_kses_post(stripslashes($params[$this->design_thankyou_invoice_message_str]));
        $this->design_thankyou_welcome_img        = absint($params[$this->design_thankyou_welcome_img_str]);
        $this->design_enable_pricing_template     = isset($params[$this->design_enable_pricing_template_str]);
        $this->design_pricing_title               = sanitize_text_field($params[$this->design_pricing_title_str]);
        $this->design_pricing_cta_color           = sanitize_text_field($params[$this->design_pricing_cta_color_str]);
        $this->design_pricing_subheadline         = wp_kses_post(stripslashes($params[$this->design_pricing_subheadline_str]));
        $this->rl_enable_coaching_template        = isset($params[$this->rl_enable_coaching_template_str]);
        $this->design_show_checkout_price_terms   = isset($params[$this->design_show_checkout_price_terms_str]);
        $this->rl_enable_wp_footer                = sanitize_text_field($params[$this->rl_enable_wp_footer_str]);
    }

    public function update_address_fields_required()
    {
        $new_address_fields = [];

        foreach ($this->address_fields as $line) {
            if ($line->field_key != 'mepr-address-two') { // Don't set address 2 to required ever
                $line->required = $this->require_address_fields;
            }

            $new_address_fields[] = $line;
        }

        return $new_address_fields;
    }

    public function get_custom_field_slugs()
    {
        $slugs = [];

        if (empty($this->custom_fields)) {
            return $slugs;
        }

        foreach ($this->custom_fields as $row) {
            $slugs[] = $row->field_key;
        }

        return $slugs;
    }

    public function get_custom_field($field_key)
    {
        $custom_field = null;

        foreach ($this->custom_fields as $custom_field) {
            if ($custom_field->field_key == $field_key) {
                return $custom_field;
            }
        }

        return $custom_field;
    }

    public function update_custom_fields($params)
    {
        $fields = [];

        if (isset($params[$this->custom_fields_str]) && !empty($params[$this->custom_fields_str])) {
            $indexes = $params['mepr-custom-fields-index'];

            $used = [];

            foreach ($indexes as $i) {
                $name = isset($params[$this->custom_fields_str][$i]['name']) ? $params[$this->custom_fields_str][$i]['name'] : '';
                $slug = ($params[$this->custom_fields_str][$i]['slug'] == 'mepr_none') ? substr(MeprUtils::sanitize_string('mepr_' . $name), 0, 240) : substr($params[$this->custom_fields_str][$i]['slug'], 0, 240); // Need to check that this key doesn't already exist in usermeta table

                // Prevent duplicate slugs
                if (in_array($slug, $used)) {
                    do {
                        $slug_parts = explode('-', $slug);
                        if (is_array($slug_parts)) { // We may have a number appended already
                            $number = array_pop($slug_parts);
                            if (is_numeric($number)) { // Increment the existing number
                                  $append = (int) $number + 1;
                                  $last_index = count($slug_parts) - 1;
                                  $slug_parts[$last_index] .= "-{$append}";
                                  $slug = implode('-', $slug_parts);
                            } else { // Append 1
                                $slug .= '-1';
                            }
                        } else { // Append 1
                            $slug .= '-1';
                        }
                    } while (in_array($slug, $used));
                }
                $used[] = $slug;

                $type = $params[$this->custom_fields_str][$i]['type'];
                $default = isset($params[$this->custom_fields_str][$i]['default']) ? $params[$this->custom_fields_str][$i]['default'] : '';
                $signup = (isset($params[$this->custom_fields_str][$i]['signup']) || isset($params[$this->custom_fields_str][$i]['required']));
                $show_in_account = isset($params[$this->custom_fields_str][$i]['show_in_account']);
                $required = isset($params[$this->custom_fields_str][$i]['required']);
                $dropdown_ops = [];

                if (in_array($type, ['dropdown','multiselect','radios','checkboxes'])) {
                    $options = $params[$this->custom_fields_str][$i]['option'];
                    $values = $params[$this->custom_fields_str][$i]['value'];

                    foreach ($options as $key => $value) {
                        if (!empty($value)) {
                                // Due to WPML compat - we're no longer storing these as forced (object)'s - see $this->wpml_custom_fields()
                                $option_value = sanitize_title($values[$key], sanitize_title($options[$key]));
                                $option_value = MeprHooks::apply_filters('mepr-custom-field-option-value', $option_value, $values[$key], $options[$key]);

                                $dropdown_ops[] = [
                                    'option_name' => $options[$key],
                                    'option_value' => $option_value,
                                ];
                        }
                    }

                    if (empty($dropdown_ops)) {
                        $name = ''; // if no dropdown options were entered let's not save this line
                    }
                }

                if ($name != '') { // If no name was left let's not save this line
                    // Due to WPML compat - we're no longer storing these as forced (object)'s - see $this->wpml_custom_fields()
                    $fields[] = [
                        'field_key' => $slug,
                        'field_name' => $name,
                        'field_type' => $type,
                        'default_value' => $default,
                        'show_on_signup' => $signup,
                        'show_in_account' => $show_in_account,
                        'required' => $required,
                        'options' => $dropdown_ops,
                    ];
                }
            }
        }

        return $fields;
    }

    public function set_from_array($options = [], $post_array = false)
    {
        if ($post_array) {
            $this->update($post_array);
        } else { // Set values from array
            foreach ($options as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    public function store($validate = true)
    {
        $options = (array)$this;
        // Don't want to store any dynamic attributes
        unset($options['dynamic_attrs']);

        // This is where we store our dynamic_attrs
        $this->store_attrs();

        if ($validate) {
            $errors = $this->validate($_POST);

            if (empty($errors)) {
                update_option(MEPR_OPTIONS_SLUG, $options);
            }

            return $errors;
        }

        update_option(MEPR_OPTIONS_SLUG, $options);
    }

    public function biz_address_is_set()
    {
        $one      = $this->attr('biz_address1');
        // $two      = $this->attr('biz_address2');
        $city     = $this->attr('biz_city');
        $state    = $this->attr('biz_state');
        $country  = $this->attr('biz_country');
        $postcode = $this->attr('biz_postcode');

        return (!empty($country) && !empty($postcode) && !empty($state) && !empty($city) && !empty($one));
    }

    public function payment_method($id = 'default', $include_builtin_gateways = true, $force = false)
    {
        $pmt_methods = $this->payment_methods($include_builtin_gateways, $force);

        if ($id == 'default') {
            $keys = array_keys($pmt_methods);
            if (isset($keys[0])) {
                $id = $keys[0];
            }
        }

        if (isset($pmt_methods[$id])) {
            return $pmt_methods[$id];
        }

        return false;
    }

    public function payment_methods($include_builtin_gateways = true, $force = false)
    {
        static $pmt_methods;

        if (!isset($pmt_methods) || $force) {
            $pmt_methods = [];

            if (isset($this->integrations) and is_array($this->integrations)) {
                foreach ($this->integrations as $intg_id => $intg_array) {
                    try {
                        $pmt_methods[$intg_id] = MeprGatewayFactory::fetch($intg_array['gateway'], $intg_array);
                    } catch (Exception $e) {
                        // Just do nothing for now
                    }
                }
            }
        }

        $all_pmt_methods = $pmt_methods;
        if ($include_builtin_gateways) {
            $all_pmt_methods[MeprTransaction::$free_gateway_str] =
                new MeprBaseStaticGateway(
                    MeprTransaction::$free_gateway_str,
                    __('Free', 'memberpress'),
                    __('Free', 'memberpress')
                );

            $all_pmt_methods[MeprTransaction::$manual_gateway_str] =
                new MeprBaseStaticGateway(
                    MeprTransaction::$manual_gateway_str,
                    __('Manual', 'memberpress'),
                    __('Manual', 'memberpress')
                );
        }

        return $all_pmt_methods;
    }

    public function pm_count()
    {
        return count($this->integrations);
    }

    public function account_page_url($args = '')
    {
        $link = home_url();

        if (isset($this->account_page_id) && (int)$this->account_page_id > 0) {
            $link = MeprUtils::get_permalink($this->account_page_id);

            $link = MeprHooks::apply_filters('mepr-account-page-permalink', $link);

            if (!empty($args)) {
                $link .= MeprAppCtrl::get_param_delimiter_char($link) . $args;
            }
        }

        return $link;
    }

    public function login_page_url($args = '')
    {
        if (
            isset($this->login_page_id) and
            is_numeric($this->login_page_id) and
            (int)$this->login_page_id > 0
        ) {
            $link = MeprUtils::get_permalink($this->login_page_id);

            if (!empty($args)) {
                return $link . MeprAppCtrl::get_param_delimiter_char($link) . $args;
            } else {
                return $link;
            }
        }

        return home_url(); // default to the home url
    }

    public function thankyou_page_url($args = '')
    {
        $thank_you_page_id = 0;
        $args_array = wp_parse_args($args);
        $url = home_url();

        if (isset($args_array['membership_id'])) {
            $product = new MeprProduct($args_array['membership_id']);
            if ($product->thank_you_page_enabled && $product->thank_you_page_type == 'page') {
                // Returns the custom thank you page for the product
                $thank_you_page_id = $product->thank_you_page_id;
            } elseif (isset($this->thankyou_page_id)) {
                // Returns the default thank you page from the options
                $thank_you_page_id = $this->thankyou_page_id;
            }
        } elseif (isset($this->thankyou_page_id)) {
            // Returns the default thank you page from the options
            $thank_you_page_id = $this->thankyou_page_id;
        }

        if (
            isset($thank_you_page_id) &&
            is_numeric($thank_you_page_id) &&
            (int)$thank_you_page_id > 0
        ) {
            $thank_you_page_id = (int)$thank_you_page_id;
            $link = MeprUtils::get_permalink($thank_you_page_id);

            if (!empty($args)) {
                if (is_array($args)) {
                    $args = http_build_query($args);
                }
                $url = $link . MeprAppCtrl::get_param_delimiter_char($link) . $args;
            } else {
                $url = $link;
            }
        }

        return MeprHooks::apply_filters('mepr-thankyou-page-url', $url, $args_array);
    }

    public function forgot_password_url()
    {
        $mepr_options = MeprOptions::fetch();

        $login_page_id = (!empty($mepr_options->login_page_id) && $mepr_options->login_page_id > 0) ? $mepr_options->login_page_id : 0;

        if ($login_page_id) {
            $login_url = $mepr_options->login_page_url();
            $login_delim = MeprAppCtrl::get_param_delimiter_char($login_url);
            $forgot_password_url = "{$login_url}{$login_delim}action=forgot_password";
        } else {
            $login_url = home_url('/wp-login.php');
            $forgot_password_url = home_url('/wp-login.php?action=lostpassword');
        }

        return $forgot_password_url;
    }

    private function set_address_fields()
    {
        $country_codes = MeprUtils::countries();
        $state_codes = MeprUtils::states();
        $coptions = [];

        foreach ($country_codes as $copt => $cname) {
            $coptions[] = (object)[
                'option_name' => $cname,
                'option_value' => $copt,
            ];
        }

        $this->address_fields = [
            (object)[
                'field_key' => 'mepr-address-one',
                'field_name' => _x('Address Line 1', 'ui', 'memberpress'),
                'field_type' => 'text',
                'default_value' => '',
                'show_on_signup' => true,
                'required' => $this->require_address_fields,
            ],
            (object)[
                'field_key' => 'mepr-address-two',
                'field_name' => _x('Address Line 2', 'ui', 'memberpress'),
                'field_type' => 'text',
                'default_value' => '',
                'show_on_signup' => true,
                'required' => false,
            ],
            (object)[
                'field_key' => 'mepr-address-city',
                'field_name' => _x('City', 'ui', 'memberpress'),
                'field_type' => 'text',
                'default_value' => '',
                'show_on_signup' => true,
                'required' => $this->require_address_fields,
            ],
            (object)[
                'field_key' => 'mepr-address-country',
                'field_name' => _x('Country', 'ui', 'memberpress'),
                'field_type' => 'countries',
                'default_value' => '',
                'show_on_signup' => true,
                'required' => $this->require_address_fields,
            ],
            (object)[
                'field_key' => 'mepr-address-state',
                'field_name' => _x('State/Province', 'ui', 'memberpress'),
                'field_type' => 'states',
                'default_value' => '',
                'show_on_signup' => true,
                'required' => $this->require_address_fields,
            ],
            (object)[
                'field_key' => 'mepr-address-zip',
                'field_name' => _x('Zip/Postal Code', 'ui', 'memberpress'),
                'field_type' => 'text',
                'default_value' => '',
                'show_on_signup' => true,
                'required' => $this->require_address_fields,
            ],
        ];
    }

    /*****
     * Migrations ... should probably find a better place to
           put these but the model makes sense for now I suppose
     *****/
    public static function migrate_to_new_unauth_system()
    {
        $mepr_options = MeprOptions::fetch();

        if (!isset($mepr_options->unauthorized_page_id) || (int)$mepr_options->unauthorized_page_id <= 0) {
            return; // Short circuit ... only migrate if we need to
        }

        $page = get_post($mepr_options->unauthorized_page_id);

        if (!($page instanceof WP_Post)) {
            $mepr_options->unauthorized_page_id = 0; // Set the page to 0 so we don't end up here again
            $mepr_options->redirect_on_unauthorized = false;
            $mepr_options->store(false);
            return;
        }

        $content = stripslashes($page->post_content); // $page->post_content is raw, so let's strip slashes

        // It's either empty or there's something on this page - and it's not the unauth shortcode
        // so let's put the shortcode on this page, and setup the unauth message
        if (empty($content) || (!empty($content) && strstr($content, 'mepr-unauthorized-message') === false)) {
            $page->post_content = '[mepr-unauthorized-message]';
            $mepr_options->redirect_on_unauthorized = true;
            $mepr_options->unauthorized_redirect_url = MeprUtils::get_permalink($page->ID);
            $mepr_options->unauthorized_page_id = 0; // Set the page to 0 so we don't end up here again

            if (!empty($content)) { // Only change the unauth message if the user had one on this page already
                $mepr_options->unauthorized_message = $content;
            }

            $mepr_options->store(false);
            wp_update_post($page);

            return;
        }

        // If we made it here the user has already configured this shiz
        // (probably running a beta of 1.1.0 or something) so let's
        // just set the unauthorized_page_id to 0 to prevent getting here again
        $mepr_options->unauthorized_page_id = 0; // Set the page to 0 so we don't end up here again
        $mepr_options->store(false);
    }

    public function attr($attr)
    {
        return $this->get_attr($attr);
    }

    public function get_attr($attr)
    {
        $value = '';

        if (array_key_exists($attr, get_object_vars($this))) {
            $value = $this->$attr;
        } elseif (array_key_exists($attr, $this->dynamic_attrs)) {
            $a = $this->attr_config($attr);

            if (!empty($a)) {
                if (!isset($a['value'])) {
                    $value = get_option($this->attr_slug($attr), $a['default']);
                    $this->set_attr($this->attr_slug($attr), $value);
                } else {
                    $value = $a['value'];
                }
            }
        }

        $value = stripslashes($value); // single quotes etc causing escape chars to appear when output

        return MeprHooks::apply_filters('mepr-options-get-dynamic-attribute-' . $attr, $value, $this);
    }

    public function set_attr($attr, $value)
    {
        $value = MeprHooks::apply_filters('mepr-options-set-dynamic-attribute-' . $attr, $value, $this);

        if (array_key_exists($attr, get_object_vars($this))) {
            $this->$attr = $value;
        } elseif (array_key_exists($attr, $this->dynamic_attrs)) {
            $this->dynamic_attrs[$attr]['value'] = $value;
        }
    }

    private function set_attrs($attrs)
    {
        foreach ($attrs as $attr => $value) {
            if (array_key_exists($attr, $this->dynamic_attrs)) {
                $this->set_attr($attr, $value);
            }
        }
    }

    private function set_attrs_from_slugs($slugs)
    {
        // Build slug to attr lookup array
        $slugs_lookup = [];
        foreach ($this->dynamic_attrs as $attr => $info) {
            $slugs_lookup[$this->attr_slug($attr)] = $attr;
        }

        foreach ($slugs as $slug => $value) {
            if (array_key_exists($slug, $slugs_lookup)) {
                $attr = $slugs_lookup[$slug];
                $this->set_attr($attr, $value);
            }
        }
    }

    private function store_attr($attr)
    {
        $a = $this->attr_config($attr);
        if (!empty($a) && isset($a['value'])) {
            update_option($this->attr_slug($attr), $a['value']);
        }
    }

    private function store_attrs()
    {
        foreach ($this->dynamic_attrs as $attr => $config) {
            $this->store_attr($attr);
        }
    }

    private function attr_config($attr)
    {
        if (array_key_exists($attr, $this->dynamic_attrs)) {
            return $this->dynamic_attrs[$attr];
        }
        return '';
    }

    public function attr_slug($attr)
    {
        if (array_key_exists($attr, $this->dynamic_attrs)) {
            if (isset($this->dynamic_attrs['slug'])) {
                return $this->dynamic_attrs['slug'];
            } else {
                return "mepr_{$attr}";
            }
        }
        return '';
    }

    public function attr_default($attr)
    {
        if (array_key_exists($attr, $this->dynamic_attrs)) {
            return $this->dynamic_attrs[$attr]['default'];
        }
        return '';
    }

    public function attr_validations($attr)
    {
        if (array_key_exists($attr, $this->dynamic_attrs)) {
            return $this->dynamic_attrs[$attr]['validations'];
        }
        return '';
    }

    /**
     * TODO: Add these once we convert legacy fields to work with dynamic attributes
          public function __get($attr) {
        return $this->attr($attr);
      }
          public function __set($attr, $value) {
        return $this->set_attr($attr, $value);
      }
          public function __isset($attr) {
        if(array_key_exists($attr, $this->dynamic_attrs)) {
          return isset($this->dynamic_attrs[$attr]['value']);
        }
        else {
          return isset($this->$attr);
        }
      }
          public function __unset($attr) {
        if(array_key_exists($attr, $this->dynamic_attrs)) {
          unset($this->dynamic_attrs[$attr]['value']);
        }
        else {
          unset($this->$attr);
        }
      }
     */

    /**
     * We just return a JSON encoding of the
     * attributes in the model when we try
     * to get a string for the options.
     */
    public function __toString()
    {
        $str_array = (array)$this;

        // add dynamic attrs
        foreach ($this->dynamic_attrs as $attr => $config) {
            $str_array[$attr] = $this->get_attr($attr);
        }

        return json_encode($str_array);
    }
} //End class
