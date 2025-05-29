<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * This class handles the registrations and enqueues for MemberPress blocks
 */
class MeprBlocksCtrl extends MeprBaseCtrl
{
    /**
     * Register all class actions and filters.
     */
    public function load_hooks()
    {
        // Only load block stuff when Gutenberg is active (e.g. WordPress 5.0+).
        if (function_exists('register_block_type')) {
            add_action('init', [$this, 'register_block_types_serverside']);
            add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_block_scripts']);
            add_action('enqueue_block_assets', [$this, 'enqueue_block_scripts']);
            add_filter('mepr-is-product-page', [$this, 'signup_block_enqueues'], 10, 2);
            add_filter('mepr_is_account_page', [$this, 'account_block_enqueues'], 10, 2);
            add_filter('register_block_type_args', [$this, 'add_protection_attributes']);
            add_filter('render_block', [$this, 'block_content_protection'], 10, 2);
        }
    }

    /**
     * Render the frontend for the blocks on the server ("save" method must return null)
     *
     * @return void
     */
    public function register_block_types_serverside()
    {
        $mepr_options    = MeprOptions::fetch();
        $disabled_blocks = MeprHooks::apply_filters('mepr_disabled_blocks', []);

        // Membership signup form block.
        register_block_type(
            'memberpress/membership-signup',
            [
                'api_version'     => 2,
                'attributes'      => [
                    'membership' => [
                        'type' => 'string',
                    ],
                ],
                'render_callback' => [$this, 'render_membership_signup_block'],
            ]
        );

        // Account form block.
        register_block_type(
            'memberpress/account-form',
            [
                'attributes'      => [],
                'render_callback' => [$this, 'render_account_block'],
            ]
        );

        // Login form block.
        register_block_type(
            'memberpress/login-form',
            [
                'api_version'     => 2,
                'attributes'      => [
                    'use_redirect' => [
                        'type' => 'boolean',
                    ],
                ],
                'render_callback' => [$this, 'render_login_block'],
            ]
        );

        if (!in_array('protected-content', $disabled_blocks, true)) {
            // Protected content block.
            register_block_type(
                'memberpress/protected-content',
                [
                    'attributes'      => [
                        'rule'           => [
                            'type' => 'number',
                        ],
                        'ifallowed'      => [
                            'type' => 'string',
                        ],
                        'unauth'         => [
                            'type' => 'string',
                        ],
                        'unauth_message' => [
                            'type' => 'string',
                        ],
                    ],
                    'render_callback' => [$this, 'render_protected_content_block'],
                ]
            );
        }

        // Pro Login Form.
        register_block_type(
            'memberpress/pro-login-form',
            [
                'api_version'     => 2,
                'attributes'      => [
                    'show_welcome_image' => [
                        'type'    => 'boolean',
                        'default' => $mepr_options->design_show_login_welcome_image,
                    ],
                    'welcome_image'      => [
                        'type'    => 'string',
                        'default' => wp_get_attachment_url($mepr_options->design_login_welcome_img),
                    ],
                    'admin_view'         => [
                        'type' => 'boolean',
                    ],
                ],
                'render_callback' => [$this, 'render_pro_login_block'],
                'editor_style'    => 'mp-pro-login',
            ]
        );

        // Pricing Columns for Pro Template.
        register_block_type(
            'memberpress/pro-pricing-table',
            [
                'api_version'     => 2,
                'attributes'      => [
                    'show_title'             => [
                        'type' => 'boolean',
                    ],
                    'button_highlight_color' => [
                        'type'    => 'string',
                        'default' => '#EF1010',
                    ],
                    'group_id'               => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                ],
                'render_callback' => [$this, 'render_pro_pricing_block'],
                'editor_style'    => 'mp-pro-pricing',
            ]
        );

        // Accounts Tab.
        register_block_type(
            'memberpress/pro-account-tabs',
            [
                'api_version'     => 2,
                'attributes'      => [
                    'show_welcome_image' => [
                        'type'    => 'boolean',
                        'default' => $mepr_options->design_show_account_welcome_image,
                    ],
                    'welcome_image'      => [
                        'type'    => 'string',
                        'default' => wp_get_attachment_url($mepr_options->design_account_welcome_img),
                    ],
                ],
                'editor_style'    => 'mp-pro-account',
                'render_callback' => [$this, 'render_pro_account_block'],
            ]
        );

        // Checkout.
        register_block_type(
            'memberpress/checkout',
            [
                'api_version'     => 2,
                'attributes'      => [
                    'show_welcome_image' => [
                        'type' => 'boolean',
                    ],
                    'membership_id'      => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                ],
                'render_callback' => [$this, 'render_checkout_block'],
                'editor_style'    => 'mp-pro-checkout',
            ]
        );

        // Account Links.
        register_block_type(
            'memberpress/account-links',
            [
                'api_version'     => 2,
                'attributes'      => [],
                'render_callback' => [$this, 'render_account_links_block'],
            ]
        );

        // Subscriptions.
        register_block_type(
            'memberpress/subscriptions',
            [
                'api_version'     => 2,
                'attributes'      => [
                    'order_by'                 => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                    'order'                    => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                    'not_logged_in_message'    => [
                        'type'    => 'string',
                        'default' => __('You are not logged in.', 'memberpress'),
                    ],
                    'no_subscriptions_message' => [
                        'type'    => 'string',
                        'default' => __('You have no Subscriptions yet.', 'memberpress'),
                    ],
                    'top_description'          => [
                        'type' => 'string',
                    ],
                    'bottom_description'       => [
                        'type' => 'string',
                    ],
                    'use_access_url'           => [
                        'type' => 'boolean',
                    ],
                ],
                'render_callback' => [$this, 'render_subscriptions_block'],
            ]
        );

        // Accounts Info.
        register_block_type(
            'memberpress/account-info',
            [
                'api_version'     => 2,
                'attributes'      => [
                    'field' => [
                        'type'    => 'string',
                        'default' => 'full_name',
                    ],
                ],
                'render_callback' => [$this, 'render_account_info'],
            ]
        );
    }

    /**
     * Renders a membership's signup form
     *
     * @param array $props Properties/data from the block.
     *
     * @return string
     */
    public function render_membership_signup_block($props)
    {

        $membership_id = isset($props['membership']) ? (int) $props['membership'] : 0;

        if ($membership_id > 0) {
            ob_start();
            echo do_shortcode("[mepr-membership-registration-form id='{$membership_id}']");
            return ob_get_clean();
        }

        return _x('Uh oh, something went wrong. Not a valid Membership form.', 'ui', 'memberpress');
    }

    /**
     * Renders the MP account form
     *
     * @return string
     */
    public function render_account_block()
    {
        ob_start();
        echo do_shortcode('[mepr-account-form]');
        return ob_get_clean();
    }

    /**
     * Renders the MP login form
     *
     * @param array $props Properties/data from the block.
     *
     * @return string
     */
    public function render_login_block($props)
    {
        $shortcode = isset($props['use_redirect']) && true === $props['use_redirect'] ? "[mepr-login-form use_redirect='true']" : '[mepr-login-form]';
        ob_start();
        echo do_shortcode($shortcode);
        return ob_get_clean();
    }

    /**
     * Render the "dynamic" block
     *
     * @param array  $attributes Properties/data from the block.
     * @param string $content    Block content.
     *
     * @return string
     */
    public function render_protected_content_block($attributes, $content)
    {

        $attributes['ifallowed'] = ! empty($attributes['ifallowed']) ? $attributes['ifallowed'] : 'show';

        if (! isset($attributes['unauth_message']) || '' === $attributes['unauth_message']) {
            $attributes['unauth_message'] = __('You are unauthorized to view this content.', 'memberpress');
        }

        $content = MeprRulesCtrl::protect_shortcode_content($attributes, $content);

        return $content;
    }

    /**
     * Renders the MP login form
     *
     * @param array $atts The attributes.
     *
     * @return string
     */
    public function render_pro_login_block($atts)
    {
        wp_enqueue_style('mp-pro-login');

        $show_welcome_image = filter_var($atts['show_welcome_image'], FILTER_VALIDATE_BOOLEAN);

        $admin_view = isset($atts['admin_view']) ? filter_var($atts['admin_view'], FILTER_VALIDATE_BOOLEAN) : false;

        $welcome_image = isset($atts['welcome_image']) ?
        esc_url_raw($atts['welcome_image']) : '';

        $shortcode = "[mepr-pro-login-form
    show_welcome_image='$show_welcome_image'
    welcome_image='$welcome_image'
    admin_view='$admin_view']";

        ob_start();
        echo do_shortcode($shortcode);
        return ob_get_clean();
    }

    /**
     * Renders the MP login form
     *
     * @param array $atts The attributes.
     *
     * @return string
     */
    public function render_pro_pricing_block($atts)
    {
        wp_enqueue_style('mp-pro-pricing');

        $show_title = isset($atts['show_title']) &&
        filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN) ?
        true : false;

        $button_highlight_color = isset($atts['button_highlight_color']) ?
        sanitize_text_field($atts['button_highlight_color']) : '';

        $group_id = isset($atts['group_id']) ?
        absint($atts['group_id']) : '';

        $shortcode = "[mepr-pro-pricing-table
    show_title='$show_title'
    button_highlight_color='$button_highlight_color'
    group_id='$group_id']";

        ob_start();
        echo do_shortcode($shortcode);
        return ob_get_clean();
    }


    /**
     * Renders the MP login form
     *
     * @param array $atts The attributes.
     *
     * @return string
     */
    public function render_pro_account_block($atts)
    {
        wp_enqueue_style('mp-pro-account');

        $show_welcome_image = isset($atts['show_welcome_image']) &&
        filter_var($atts['show_welcome_image'], FILTER_VALIDATE_BOOLEAN) ?
        true : false;

        $welcome_image = isset($atts['welcome_image']) ?
        esc_url_raw($atts['welcome_image']) : '';

        $shortcode = "[mepr-pro-account-tabs
    show_welcome_image='$show_welcome_image'
    welcome_image='$welcome_image']";

        ob_start();
        echo do_shortcode($shortcode);
        return ob_get_clean();
    }

    /**
     * Renders the MP login form
     *
     * @param array $atts The attributes.
     *
     * @return string
     */
    public function render_checkout_block($atts)
    {
        wp_enqueue_style('mp-pro-checkout');

        $membership_id = isset($atts['membership_id']) ?
        absint($atts['membership_id']) : '';

        $shortcode = "[mepr-pro-checkout membership_id='$membership_id']";
        ob_start();
        echo do_shortcode($shortcode);
        return ob_get_clean();
    }

    /**
     * Renders the MP account links
     *
     * @param array $atts Properties/data from the block.
     *
     * @return string
     */
    public function render_account_links_block(array $atts)
    {
        ob_start();
        $mepr_options = MeprOptions::fetch();
        if (MeprUtils::is_user_logged_in()) {
            $account_url = $mepr_options->account_page_url();
            $logout_url  = MeprUtils::logout_url();
            MeprView::render('/account/logged_in_widget', get_defined_vars());
        } else {
            $login_url = MeprUtils::login_url();
            MeprView::render('/account/logged_out_widget', get_defined_vars());
        }
        return ob_get_clean();
    }

    /**
     * Renders the MP subscriptions
     *
     * @param array $atts Properties/data from the block.
     *
     * @return string
     */
    public function render_subscriptions_block(array $atts)
    {
        ob_start();
        $user         = MeprUtils::get_currentuserinfo();
        $mepr_options = MeprOptions::fetch();

        $order_by                 = isset($atts['order_by']) ?
        sanitize_text_field($atts['order_by']) : '';
        $order                    = isset($atts['order']) ?
        sanitize_text_field($atts['order']) : '';
        $not_logged_in_message    = isset($atts['not_logged_in_message']) ?
        sanitize_text_field($atts['not_logged_in_message']) : '';
        $no_subscriptions_message = isset($atts['no_subscriptions_message']) ?
        sanitize_text_field($atts['no_subscriptions_message']) : '';
        $top_desc                 = isset($atts['top_description']) ?
        sanitize_text_field($atts['top_description']) : '';
        $bottom_desc              = isset($atts['bottom_description']) ?
        sanitize_text_field($atts['bottom_description']) : '';
        $use_access_url           = isset($atts['use_access_url']) &&
        filter_var($atts['use_access_url'], FILTER_VALIDATE_BOOLEAN) ?
        true : false;

        MeprView::render('/account/subscriptions_widget', get_defined_vars());
        return ob_get_clean();
    }

    /**
     * Renders the MP account info
     *
     * @param array $props Properties/data from the block.
     *
     * @return string
     */
    public function render_account_info(array $props)
    {
        $shortcode = isset($props['field'])
        ? '[mepr-account-info field="' . sanitize_text_field($props['field']) . '"]'
        : '[mepr-account-info field="full_name"]';
        ob_start();
        echo '<p>' . do_shortcode($shortcode) . '</p>';
        return ob_get_clean();
    }

    /**
     * Enqueue the necessary scripts/styles in the editor
     *
     * @return void
     */
    public function enqueue_editor_block_scripts()
    {
        $asset_file = include MEPR_JS_PATH . '/build/blocks.asset.php';

        $dependencies = array_unique(
            array_merge(
                [
                    'wp-blocks',
                    'wp-i18n',
                    'wp-editor',
                ], // Legacy dependencies.
                (array) $asset_file['dependencies']
            )
        );

        wp_enqueue_script(
            'memberpress/blocks',
            MEPR_JS_URL . '/build/blocks.js',
            $dependencies,
            $asset_file['version'],
            true
        );

        $membership_options = [];
        $rule_options       = [];

        // Assemble MP Products into an options array.
        foreach (MeprCptModel::all('MeprProduct') as $membership) {
            $membership_options[] = [
                'label' => $membership->post_title,
                'value' => $membership->ID,
            ];
        }

        // Assemble MP Rules into an options array.
        foreach (MeprCptModel::all('MeprRule') as $rule) {
            $rule_options[] = [
                'label'    => $rule->post_title,
                'value'    => $rule->ID,
                'ruleLink' => get_edit_post_link($rule->ID, '&'),
            ];
        }

        // Assemble MP Groups into an options array.
        $groups = [];
        foreach (MeprCptModel::all('MeprGroup') as $group) {
            $groups[] = [
                'label' => $group->post_title,
                'value' => $group->ID,
            ];
        }

        // Assemble custom fields into an options array.
        $mepr_options  = MeprOptions::fetch();
        $custom_fields = [];
        if (!empty($mepr_options->custom_fields)) {
            foreach ($mepr_options->custom_fields as $field) {
                $custom_fields[] = [
                    'label' => $field->field_key,
                    'value' => $field->field_key,
                ];
            }
        }

        // Make the data available to the script.
        wp_localize_script(
            'memberpress/blocks',
            'memberpressBlocks',
            [
                'memberships'              => $membership_options,
                'rules'                    => $rule_options,
                'groups'                   => $groups,
                'custom_fields'            => $custom_fields,
                'redirect_url_setting_url' => menu_page_url('memberpress-options', false) . '#mepr-accounts',
                'disabled_blocks'          => MeprHooks::apply_filters('mepr_disabled_blocks', []),
                'block_protection'         => MeprHooks::apply_filters('mepr_block_protection_enabled', true),
                'block_protection_exclude' => MeprHooks::apply_filters(
                    'mepr_block_protection_exclude',
                    ['memberpress/protected-content']
                ),
            ]
        );

        wp_enqueue_style('mp-theme', MEPR_CSS_URL . '/ui/theme.css', null, MEPR_VERSION);
    }

    /**
     * Enqueue the necessary scripts / styles for each block
     *
     * @return void
     */
    public function enqueue_block_scripts()
    {

        // Register account scripts.
        wp_register_style('mp-pro-fonts', MEPR_CSS_URL . '/readylaunch/fonts.css', null, MEPR_VERSION);
        wp_register_style('mp-pro-login', MEPR_CSS_URL . '/readylaunch/login.css', null, MEPR_VERSION);
        wp_register_style('mp-pro-account', MEPR_CSS_URL . '/readylaunch/account.css', ['mp-pro-fonts', 'mp-pro-login'], MEPR_VERSION);

        // Register pricing scripts.
        wp_register_style('mp-pro-pricing', MEPR_CSS_URL . '/readylaunch/pricing.css', null, MEPR_VERSION);

        // Register checkout scripts.
        $prereqs = MeprHooks::apply_filters('mepr-signup-styles', []);
        wp_register_style('mp-signup', MEPR_CSS_URL . '/signup.css', $prereqs, MEPR_VERSION);
        wp_register_style('mp-pro-checkout', MEPR_CSS_URL . '/readylaunch/checkout.css', ['mp-signup'], MEPR_VERSION);
    }

    /**
     * Filter to add the necessary frontend enqueues for Membership Signup block
     *
     * @param mixed  $return MeprProduct object if scripts will be enqueued, else false.
     * @param object $post   WP_Post.
     *
     * @return boolean
     */
    public function signup_block_enqueues($return, $post)
    {

        if (! isset($post->post_content)) {
            return $return;
        }

        // We don't want to mess with enqueues on MemberPress products since the files are already properly enqueued there.
        if (! is_object($return) || ! is_a($return, 'MeprProduct')) {
            $membership = false;

            // Check that the signup form block is added.
            $match = preg_match('/(?:wp:memberpress\/membership-signup\s)(\{(?:[^{}]|(?R))*\})/', $post->post_content, $matches);

            if (1 === $match && isset($matches[1]) && isset(json_decode($matches[1], true)['membership'])) {
                $membership = new MeprProduct(json_decode($matches[1], true)['membership']);
            } elseif (
                preg_match(
                    '~(?:wp:memberpress\/checkout\s)+{\"membership_id\"\:[\"\\\'](\d+)[\"\\\']~',
                    $post->post_content,
                    $m
                )
            ) {
                $membership = new MeprProduct($m[1]);
            }

            // Valid membership.
            if (isset($membership->ID) && $membership->ID > 0) {
                $return = $membership; // Return the MeprProduct instead of just boolean true (backward compatibility).
            }
        }

        return $return;
    }

    /**
     * Filter to add the necessary frontend enqueues for the Account Form block
     *
     * @param boolean $return Whether the page is an "Account" page.
     * @param object  $post   WP_Post.
     *
     * @return boolean
     */
    public function account_block_enqueues($return, $post)
    {

        if (! isset($post->post_content)) {
            return $return;
        }

        // Post is an "Account" page if it has the Account Form block.
        if (has_block('memberpress/account-form', $post) || MeprAppHelper::block_template_has_block('account-form')) {
            $return = true;
        }

        return $return;
    }

    /**
     * Add protection attributes to blocks.
     *
     * @param  array $args Array of arguments for registering a block type.
     * @return array
     */
    public function add_protection_attributes($args)
    {
        $args['attributes']['mepr_protection_rule'] = [
            'type'    => 'number',
            'default' => 0,
        ];

        $args['attributes']['mepr_protection_ifallowed'] = [
            'type'    => 'string',
            'default' => 'show',
        ];

        $args['attributes']['mepr_protection_unauth'] = [
            'type'    => 'string',
            'default' => 'default',
        ];

        $args['attributes']['mepr_protection_unauth_message'] = [
            'type'    => 'string',
            'default' => '',
        ];

        return $args;
    }

    /**
     * Applies content protection to a given block based on defined rules and attributes.
     *
     * This method evaluates the protection rule assigned to a block and modifies its content accordingly.
     * If the user does not have the required permissions, it will handle unauthorized access by showing
     * a default or custom message, or applying the specified settings for unauthorized users.
     *
     * @param  string $block_content The original content of the block.
     * @param  array  $block         An associative array containing block attributes, including protection rules and settings.
     * @return string The modified block content if a rule is applied, or the original content if no rule is set.
     */
    public function block_content_protection($block_content, $block)
    {
        // Skip if no protection rule is set.
        if (empty($block['attrs']['mepr_protection_rule'])) {
            return $block_content;
        }

        $attributes = [
            'rule'      => $block['attrs']['mepr_protection_rule'],
            'ifallowed' => !empty($block['attrs']['mepr_protection_ifallowed']) ? $block['attrs']['mepr_protection_ifallowed'] : 'show',
            'unauth'    => !empty($block['attrs']['mepr_protection_unauth']) ? $block['attrs']['mepr_protection_unauth'] : 'hide',
        ];

        $rule = new MeprRule($attributes['rule']);
        if (!($rule->ID > 0)) {
            return '<div class="mepr_block_error">' . esc_html__('Invalid rule', 'memberpress') . '</div>';
        }

        $unauth_message = '';

        if ($attributes['unauth'] === 'default') {
            $global_settings = MeprRule::get_global_unauth_settings();
            $post            = MeprUtils::get_current_post();
            $post_settings   = $post instanceof WP_Post ? MeprRule::get_post_unauth_settings($post) : null;

            if (is_object($post_settings) && $post_settings->unauth_message_type != 'default') {
                $unauth_message = $post_settings->unauth_message;
            } elseif ($rule->unauth_message_type != 'default') {
                $unauth_message = $rule->unauth_message;
            } else {
                $unauth_message = $global_settings->message;
            }
        } elseif ($attributes['unauth'] === 'message' && !empty($block['attrs']['mepr_protection_unauth_message'])) {
            $unauth_message = $block['attrs']['mepr_protection_unauth_message'];
        }

        if (!empty($unauth_message)) {
            $attributes['unauth']         = 'message';
            $attributes['unauth_message'] = do_shortcode(wp_kses_post($unauth_message));
        }

        return MeprRulesCtrl::protect_shortcode_content($attributes, $block_content);
    }
}
