<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprTwoFactorIntegration
{
    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        add_action('template_redirect', [$this, 'enqueue_twofactor_scripts']);
        add_action('mepr_account_nav_content', [$this, 'add_two_factor_nav_content']);
        add_action('mepr_account_nav', [$this, 'add_two_factor_nav']);
        add_action('mepr_buddypress_integration_setup_menus', [$this, 'add_two_factor_nav_buddypress']);
        add_action('init', [$this, 'two_factor_totp_delete'], 11);
    }

    /**
     * Delete the usermeta for the secret key, then redirect to the account page page
     *
     * @return void
     */
    public function two_factor_totp_delete()
    {
        if (isset($_GET['two_factor_action']) && $_GET['two_factor_action'] == 'totp-delete') {
            $mepr_options = MeprOptions::fetch();
            $account_url  = $mepr_options->account_page_url();
            $delim        = MeprAppCtrl::get_param_delimiter_char($account_url);

            // Delete the usermeta for the secret key, then redirect to the account page page
            delete_user_meta(get_current_user_id(), Two_Factor_Totp::SECRET_META_KEY);
            MeprUtils::wp_redirect($account_url . $delim . 'action=2fa');
        }
    }

    /**
     * Enqueue the two factor scripts.
     *
     * @return void
     */
    public function enqueue_twofactor_scripts()
    {
        global $post;

        if (MeprUser::is_account_page($post)) {
            if (isset($_GET['action']) && $_GET['action'] == '2fa' && class_exists('Two_Factor_FIDO_U2F_Admin')) {
                wp_enqueue_script('wp-api');
                Two_Factor_FIDO_U2F_Admin::enqueue_assets('profile.php');
            }
        }
    }

    /**
     * Add the two factor nav to the buddypress menus.
     *
     * @param  string $main_slug The main slug.
     * @return void
     */
    public function add_two_factor_nav_buddypress($main_slug)
    {
        if (defined('TWO_FACTOR_DIR')) {
            global $bp;
            bp_core_new_subnav_item(
                [
                    'name'            => _x('2FA', 'ui', 'memberpress'),
                    'slug'            => 'mp-two-factor-auth',
                    'parent_url'      => $bp->loggedin_user->domain . $main_slug . '/',
                    'parent_slug'     => $main_slug,
                    'screen_function' => [$this, 'bp_twofactor_nav'],
                    'position'        => 20,
                    'user_has_access' => bp_is_my_profile(),
                    'site_admin_only' => false,
                    'item_css_id'     => 'mepr-bp-two-factor-auth',
                ]
            );
        }
    }

    /**
     * Add the two factor nav to the buddypress menus.
     *
     * @return void
     */
    public function bp_twofactor_nav()
    {
        add_action('bp_template_content', [$this, 'bp_twofactor_content']);

        // Enqueue the account page scripts here yo
        $acct_ctrl = new MeprAccountCtrl();
        $acct_ctrl->enqueue_scripts(true);

        bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
    }

    /**
     * Add the two factor nav to the account page.
     *
     * @return void
     */
    public function add_two_factor_nav()
    {
        if (defined('TWO_FACTOR_DIR')) { ?>
            <?php
            $mepr_options = MeprOptions::fetch();
            $account_url  = $mepr_options->account_page_url();
            $delim        = MeprAppCtrl::get_param_delimiter_char($account_url);
            ?>
            <span class="mepr-nav-item <?php MeprAccountHelper::active_nav('2fa'); ?>">
                <a
                    href="<?php echo MeprHooks::apply_filters('mepr-account-nav-2fa-link', $account_url . $delim . 'action=2fa'); ?>"
                    id="mepr-account-2fa"><?php echo MeprHooks::apply_filters('mepr-account-nav-2fa-label', _x('2FA', 'ui', 'memberpress')); ?></a>
            </span>
            <?php
        }
    }

    /**
     * Add the two factor nav to the account page.
     *
     * @param  string $action The action.
     * @return mixed
     */
    public function add_two_factor_nav_content($action = null)
    {
        if ($action !== '2fa') {
            return null;
        }

        if (defined('TWO_FACTOR_DIR')) {
            $user = wp_get_current_user();

            if ($user->exists()) {
                self::user_two_factor_options($user);
            }
        }
    }

    /**
     * Add the two factor nav to the account page.
     *
     * @return void
     */
    public function bp_twofactor_content()
    {
        if (defined('TWO_FACTOR_DIR')) {
            $user = wp_get_current_user();

            if ($user->exists()) {
                self::user_two_factor_options($user);
            }
        }
    }

    /**
     * Save the Two Factor options.
     *
     * @param integer $user_id User ID.
     */
    public static function user_two_factor_options_update($user_id)
    {
        if (isset($_POST['_nonce_user_two_factor_options'])) {
            if (!wp_verify_nonce($_POST['_nonce_user_two_factor_options'], 'user_two_factor_options')) {
                return;
            }

            if (
                !isset($_POST[Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY]) ||
                !is_array($_POST[Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY])
            ) {
                return;
            }

            if (!Two_Factor_Core::current_user_can_update_two_factor_options('save')) {
                return;
            }

            $providers          = self::get_providers();
            $enabled_providers  = $_POST[Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY];
            $existing_providers = Two_Factor_Core::get_enabled_providers_for_user($user_id);

            // Enable only the available providers.
            $enabled_providers = array_intersect($enabled_providers, array_keys($providers));
            update_user_meta($user_id, Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY, $enabled_providers);

            // Primary provider must be enabled.
            $new_provider = isset($_POST[Two_Factor_Core::PROVIDER_USER_META_KEY]) ? $_POST[Two_Factor_Core::PROVIDER_USER_META_KEY] : '';
            if (!empty($new_provider) && in_array($new_provider, $enabled_providers, true)) {
                update_user_meta($user_id, Two_Factor_Core::PROVIDER_USER_META_KEY, $new_provider);
            } else {
                delete_user_meta($user_id, Two_Factor_Core::PROVIDER_USER_META_KEY);
            }

            // Have we changed the two-factor settings for the current user? Alter their session metadata.
            if ($user_id === get_current_user_id()) {
                if ($enabled_providers && !$existing_providers && !Two_Factor_Core::is_current_user_session_two_factor()) {
                    // We've enabled two-factor from a non-two-factor session, set the key but not the provider, as no provider has been used yet.
                    Two_Factor_Core::update_current_user_session([
                        'two-factor-provider' => '',
                        'two-factor-login'    => time(),
                    ]);
                } elseif ($existing_providers && !$enabled_providers) {
                    // We've disabled two-factor, remove session metadata.
                    Two_Factor_Core::update_current_user_session([
                        'two-factor-provider' => null,
                        'two-factor-login'    => null,
                    ]);
                }
            }

            // Destroy other sessions if setup 2FA for the first time, or deactivated a provider
            if (
                // No providers, enabling one (or more)
                (!$existing_providers && $enabled_providers) ||
                // Has providers, and is disabling one (or more), but remaining with 2FA.
                ($existing_providers && $enabled_providers && array_diff($existing_providers, $enabled_providers))
            ) {
                if ($user_id === get_current_user_id()) {
                    // Keep the current session, destroy others sessions for this user.
                    wp_destroy_other_sessions();
                } else {
                    // Destroy all sessions for the user.
                    WP_Session_Tokens::get_instance($user_id)->destroy_all();
                }
            }

            printf(
                '<div class="mepr_updated">%s</div>',
                esc_html__('Two-Factor options updated.', 'memberpress')
            );
        }
    }

    /**
     * Display the Two Factor options form.
     *
     * @param WP_User $user
     */
    public static function user_two_factor_options(WP_User $user)
    {
        $mepr_options = MeprOptions::fetch();

        if (MeprUser::is_account_page(MeprUtils::get_current_post()) && $mepr_options->design_enable_account_template) {
            printf('<h1>%s</h1>', esc_html__('Two-Factor Authentication', 'memberpress'));
        }

        wp_enqueue_style('user-edit-2fa', plugins_url('user-edit.css', TWO_FACTOR_DIR . '/two-factor.php'), [], TWO_FACTOR_VERSION);

        // This is specific to the current session, not the displayed user.
        $show_2fa_options = Two_Factor_Core::current_user_can_update_two_factor_options();

        if (!$show_2fa_options) {
            $url = add_query_arg(
                'redirect_to',
                urlencode(wp_unslash($_SERVER['REQUEST_URI'])),
                Two_Factor_Core::get_user_two_factor_revalidate_url()
            );

            printf(
                // translators: %1$s: open link tag, %2$s: close link tag.
                esc_html__('To update your Two-Factor options, you must first %1$srevalidate your session%2$s.', 'memberpress'),
                sprintf('<a href="%s">', esc_url($url)),
                '</a>'
            );
            return;
        }

        if (MeprUtils::is_post_request()) {
            self::user_two_factor_options_update($user->ID);
        }

        $enabled_providers    = array_keys(Two_Factor_Core::get_available_providers_for_user($user));
        $primary_provider_key = self::get_primary_provider_key_selected_for_user($user);
        ?>
        <?php if (1 === count($enabled_providers)) : ?>
            <p>
                <?php esc_html_e('To prevent being locked out of your account, consider enabling a backup method like Recovery Codes in case you lose access to your primary authentication method.', 'memberpress'); ?>
            </p>
        <?php endif; ?>
        <form method="post" id="two-factor-options" class="mepr-two-factor-options">
            <?php wp_nonce_field('user_two_factor_options', '_nonce_user_two_factor_options', false); ?>
            <input type="hidden" name="<?php echo esc_attr(Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY); ?>[]" value="<?php // Dummy input so $_POST value is passed when no providers are enabled. ?>" />

            <table class="form-table two-factor-methods-table" role="presentation">
                <tbody>
                <?php foreach (self::get_providers() as $provider_key => $object) : ?>
                    <tr>
                        <th><?php echo esc_html($object->get_label()); ?></th>
                        <td>
                            <label class="two-factor-method-label">
                                <input id="enabled-<?php echo esc_attr($provider_key); ?>" type="checkbox"
                                       name="<?php echo esc_attr(Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY); ?>[]"
                                       value="<?php echo esc_attr($provider_key); ?>"
                                       <?php checked(in_array($provider_key, $enabled_providers, true)); ?> />
                                <?php echo esc_html(sprintf(__('Enable %s', 'memberpress'), $object->get_label())); ?>
                            </label>
                            <?php
                            /**
                             * Fires after user options are shown.
                             *
                             * Use the {@see 'two_factor_user_options_' . $provider_key } hook instead.
                             *
                             * @param      WP_User $user The user.
                             * @deprecated 0.7.0
                             */
                            do_action_deprecated('two-factor-user-options-' . $provider_key, [$user], '0.7.0', 'two_factor_user_options_' . $provider_key);
                            do_action('two_factor_user_options_' . $provider_key, $user);
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <hr />
            <table class="form-table two-factor-primary-method-table" role="presentation">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('Primary Method', 'memberpress') ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Two_Factor_Core::PROVIDER_USER_META_KEY); ?>">
                                <option value=""><?php echo esc_html(__('Default', 'memberpress')); ?></option>
                                <?php foreach (self::get_providers() as $provider_key => $object) : ?>
                                    <option
                                        value="<?php echo esc_attr($provider_key); ?>"
                                        <?php selected($provider_key, $primary_provider_key); ?>
                                        <?php disabled(!in_array($provider_key, $enabled_providers, true)); ?>
                                    >
                                        <?php echo esc_html($object->get_label()); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Select the primary method to use for two-factor authentication when signing into this site.', 'memberpress') ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button type="submit"><?php esc_html_e('Save Options', 'memberpress'); ?></button>
        </form>
        <?php
    }

    /**
     * Get the name of the primary provider selected by the user
     * and enabled for the user.
     *
     * @param  WP_User $user User instance.
     * @return string|null
     */
    private static function get_primary_provider_key_selected_for_user($user)
    {
        $primary_provider    = get_user_meta($user->ID, Two_Factor_Core::PROVIDER_USER_META_KEY, true);
        $available_providers = Two_Factor_Core::get_available_providers_for_user($user);

        if (!empty($primary_provider) && !empty($available_providers[$primary_provider])) {
            return $primary_provider;
        }

        return null;
    }

    /**
     * For each provider, include it and then instantiate it.
     *
     * @since 0.1-dev
     *
     * @return array
     */
    public static function get_providers()
    {
        $providers = Two_Factor_Core::get_providers();

        if (isset($providers['Two_Factor_FIDO_U2F'])) {
            // Remove this as it causes problem on frontend. The problem? it's using
            // WP_List_Table and this class doesn't fully work on frontpage
            unset($providers['Two_Factor_FIDO_U2F']);
        }

        return $providers;
    }
}

new MeprTwoFactorIntegration();
