<?php

use MemberPress\GroundLevel\InProductNotifications\Services\Store;

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprAppCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for various actions and filters.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('manage_posts_custom_column', 'MeprAppCtrl::custom_columns', 100, 2);
        add_action('manage_pages_custom_column', 'MeprAppCtrl::custom_columns', 100, 2);
        add_action('registered_post_type', 'MeprAppCtrl::setup_columns', 10, 2);
        add_filter('the_content', 'MeprAppCtrl::page_route', 100);
        add_action('wp_enqueue_scripts', 'MeprAppCtrl::load_scripts', 1);
        add_action('admin_enqueue_scripts', 'MeprAppCtrl::load_admin_scripts', 1);
        add_action('init', 'MeprAppCtrl::parse_standalone_request', 10);
        add_action('wp_dashboard_setup', 'MeprAppCtrl::add_dashboard_widgets');
        add_filter('custom_menu_order', '__return_true');
        add_filter('menu_order', 'MeprAppCtrl::admin_menu_order');
        add_filter('menu_order', 'MeprAppCtrl::admin_submenu_order');
        add_filter('submenu_file', 'MeprAppCtrl::set_up_hidden_pages');
        add_action('widgets_init', 'MeprAppCtrl::register_global_widget_area');
        add_action('widgets_init', 'MeprAccountLinksWidget::register_widget');
        add_action('widgets_init', 'MeprLoginWidget::register_widget');
        add_action('widgets_init', 'MeprSubscriptionsWidget::register_widget');
        add_action('add_meta_boxes', 'MeprAppCtrl::add_meta_boxes', 10, 2);
        add_action('save_post', 'MeprAppCtrl::save_meta_boxes');
        add_action('admin_notices', 'MeprAppCtrl::protected_notice');
        add_action('admin_notices', 'MeprAppCtrl::php_min_version_check');
        add_action('admin_notices', 'MeprAppCtrl::maybe_show_get_started_notice');
        add_action('wp_ajax_mepr_dismiss_notice', 'MeprAppCtrl::dismiss_notice');
        add_action('wp_ajax_mepr_dismiss_global_notice', 'MeprAppCtrl::dismiss_global_notice');
        add_action('wp_ajax_mepr_dismiss_daily_notice', 'MeprAppCtrl::dismiss_daily_notice');
        add_action('wp_ajax_mepr_dismiss_weekly_notice', 'MeprAppCtrl::dismiss_weekly_notice');
        add_action('wp_ajax_mepr_todays_date', 'MeprAppCtrl::todays_date');
        add_action('wp_ajax_mepr_close_about_notice', 'MeprAppCtrl::close_about_notice');
        add_action('admin_init', 'MeprAppCtrl::append_mp_privacy_policy');
        add_filter('embed_oembed_html', 'MeprAppCtrl::wrap_oembed_html', 99);
        add_action('in_admin_header', 'MeprAppCtrl::admin_header', 0);
        add_action('init', 'MeprAppCtrl::maybe_auto_log_in', 5);

        add_action('plugins_loaded', 'MeprAppCtrl::load_css');

        // Load language - must be done after plugins are loaded to work with PolyLang/WPML.
        add_action('after_setup_theme', 'MeprAppCtrl::load_language');
        add_action('init', [$this, 'load_translations']);

        add_filter('months_dropdown_results', [$this, 'cleanup_list_table_month_dropdown'], 10, 2);

        // Integrate with WP Debugging plugin - https://github.com/afragen/wp-debugging/issues/6.
        add_filter('wp_debugging_add_constants', 'MeprAppCtrl::integrate_wp_debugging');

        add_action('activated_plugin', 'MeprAppCtrl::activated_plugin');
    }

    /**
     * Renders the header for MemberPress admin pages.
     */
    public static function admin_header()
    {
        if (MeprUtils::is_memberpress_admin_page()) {
            MeprView::render('/admin/header/header');
        }
    }


    /**
     * Wraps the oembed HTML in a span with a random class.
     * Fix for Elementor page builder and our static the_content caching
     * Elementor runs the_content filter on each video embed, our the_content static caching
     * caused the same video to load for all instances of a video on a page as a result
     *
     * @param  string $cached_html The cached HTML.
     * @return string The wrapped HTML.
     */
    public static function wrap_oembed_html($cached_html)
    {
        $length = rand(1, 100); // Random length, this is the key to all of this.
        $class  = substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
        return '<span class="' . $class . '">' . $cached_html . '</span>';
    }

    /**
     * Add meta boxes to the post edit screen.
     *
     * @param string  $post_type The post type.
     * @param WP_Post $post      The post object.
     *
     * @return void
     */
    public static function add_meta_boxes($post_type, $post)
    {
        $mepr_options = MeprOptions::fetch();

        if (!isset($post->ID) || $post->ID == $mepr_options->login_page_id) {
            return;
        }

        $screens = array_merge(
            array_keys(get_post_types([
                'public'   => true,
                '_builtin' => false,
            ])),
            ['post', 'page']
        );

        // This meta box shouldn't appear on the new/edit membership screen.
        $pos = array_search(MeprProduct::$cpt, $screens);
        if (isset($screens[$pos])) {
            unset($screens[$pos]);
        }

        $rules = MeprRule::get_rules($post);

        foreach ($screens as $screen) {
            if (MeprGroup::$cpt == $screen) {
                add_meta_box(
                    'mepr_unauthorized_message',
                    __('MemberPress Unauthorized Access on the Group Pricing Page', 'memberpress'),
                    'MeprAppCtrl::unauthorized_meta_box',
                    $screen
                );
                if (!empty($rules)) {
                      add_meta_box(
                          'mepr_rules',
                          __('This Group Pricing Page is Protected', 'memberpress'),
                          'MeprAppCtrl::rules_meta_box',
                          $screen,
                          'normal',
                          'high'
                      );
                }
            } elseif (in_array($screen, ['post', 'page'])) {
                add_meta_box(
                    'mepr_unauthorized_message',
                    __('MemberPress Unauthorized Access', 'memberpress'),
                    'MeprAppCtrl::unauthorized_meta_box',
                    $screen
                );
                if (!empty($rules)) {
                    $obj = get_post_type_object($screen);
                    add_meta_box(
                        'mepr_rules',
                        sprintf(
                            // Translators: %s: custom post type name.
                            __('This %s is Protected', 'memberpress'),
                            $obj->labels->singular_name
                        ),
                        'MeprAppCtrl::rules_meta_box',
                        $screen,
                        'normal',
                        'high'
                    );
                }
            } else {
                $obj = get_post_type_object($screen);
                add_meta_box(
                    'mepr_unauthorized_message',
                    sprintf(
                        // Translators: %s: custom post type name.
                        __('MemberPress Unauthorized Access to this %s', 'memberpress'),
                        $obj->labels->singular_name
                    ),
                    'MeprAppCtrl::unauthorized_meta_box',
                    $screen
                );
                if (!empty($rules)) {
                    add_meta_box(
                        'mepr_rules',
                        sprintf(
                            // Translators: %s: custom post type name.
                            __('This %s is Protected', 'memberpress'),
                            $obj->labels->singular_name
                        ),
                        'MeprAppCtrl::rules_meta_box',
                        $screen,
                        'normal',
                        'high'
                    );
                }
            }
        }
    }

    /**
     * Display custom columns in the post list table.
     *
     * @param string  $column  The name of the column.
     * @param integer $post_id The ID of the post.
     *
     * @return void
     */
    public static function custom_columns($column, $post_id)
    {
        $post = get_post($post_id);
        if ($column == 'mepr-access') {
            $access_list = MeprRule::get_access_list($post);
            if (empty($access_list)) {
                ?><div class="mepr-active"><?php _e('Public', 'memberpress'); ?></div><?php
            } else {
                $display_access_list = [];
                foreach ($access_list as $access_key => $access_values) {
                    if ($access_key == 'membership') {
                        foreach ($access_values as $product_id) {
                            $product = new MeprProduct($product_id);
                            if (!is_null($product->ID)) {
                                $display_access_list[] = stripslashes($product->post_title);
                            }
                        }
                    } else {
                        $display_access_list = array_merge($display_access_list, $access_values);
                    }
                }
                ?>
        <div class="mepr-inactive">
                <?php echo implode(', ', $display_access_list); ?>
        </div>
                <?php
            }
        }
    }

    /**
     * Set up columns for custom post types.
     *
     * @param string $post_type The post type.
     * @param array  $args      The arguments for the post type.
     *
     * @return void
     */
    public static function setup_columns($post_type, $args)
    {
        add_filter('manage_posts_columns', 'MeprAppCtrl::columns');
        add_filter('manage_pages_columns', 'MeprAppCtrl::columns');
        add_filter("manage_edit-{$post_type}_columns", 'MeprAppCtrl::columns');
    }

    /**
     * Modify the columns displayed in the post list table.
     *
     * @param array $columns The existing columns.
     *
     * @return array The modified columns.
     */
    public static function columns($columns)
    {
        global $post_type, $post;

        $except = ['attachment', 'memberpressproduct'];
        $except = MeprHooks::apply_filters('mepr-hide-cpt-access-column', $except);

        if (isset($_GET['post_type']) || (isset($post_type) && !empty($post_type)) || (isset($post->post_type) && !empty($post->post_type))) {
            if (!empty($_GET['post_type'])) {
                $cpt = get_post_type_object($_GET['post_type']);
            } elseif (!empty($post_type)) {
                $cpt = get_post_type_object($post_type);
            } elseif (!empty($post->post_type)) { // Try individual post last.
                $cpt = get_post_type_object($post->post_type);
            } else {
                return $columns; // Just give up trying.
            }

            if (in_array($cpt->name, $except) || !$cpt->public) {
                return $columns;
            }
        }

        $ak = array_keys($columns);

        MeprUtils::array_splice_assoc(
            $columns,
            $ak[2],
            $ak[2],
            ['mepr-access' => __('Access', 'memberpress')]
        );

        return $columns;
    }

    /**
     * Render the rules meta box.
     *
     * @return void
     */
    public static function rules_meta_box()
    {
        global $post;

        $rules       = MeprRule::get_rules($post);
        $access_list = MeprRule::get_access_list($post);
        $product_ids = (isset($access_list['membership']) && !empty($access_list['membership'])) ? $access_list['membership'] : [];
        $members     = (isset($access_list['member']) && !empty($access_list['member'])) ? $access_list['member'] : [];

        MeprView::render('/admin/rules/rules_meta_box', get_defined_vars());
    }

    /**
     * Display a notice if the content is protected by MemberPress rules.
     *
     * @return void
     */
    public static function protected_notice()
    {
        global $post, $pagenow;

        $public_post_types = MeprRule::public_post_types();

        if (
            'post.php' != $pagenow or !isset($_REQUEST['action']) or
            $_REQUEST['action'] != 'edit' or !in_array($post->post_type, $public_post_types)
        ) {
            return;
        }

        $rules      = MeprRule::get_rules($post);
        $rule_count = count($rules);

        $message = '<strong>' .
               sprintf(
                   // Translators: %1$d: number of access rules.
                   _n(
                       'This Content is Protected by %1$d MemberPress Access Rule',
                       'This Content is Protected by %1$d MemberPress Access Rules',
                       $rule_count,
                       'memberpress'
                   ),
                   $rule_count
               ) .
               '</strong>' .
               ' &ndash; <a href="#mepr_post_rules">' . __('Click here to view', 'memberpress') . '</a>';

        if (!empty($rules)) {
            MeprView::render('/admin/errors', get_defined_vars());
        }
    }

    /**
     * Check the PHP version and display a notice if it is outdated.
     *
     * @return void
     */
    public static function php_min_version_check()
    {
        $current_php_version = phpversion();
        if (version_compare($current_php_version, MEPR_MIN_PHP_VERSION, '<')) {
            $message = sprintf(
                // Translators: %1$s: opening strong tag, %2$s: current PHP version, %3$s: closing strong tag, %4$s: minimum PHP version.
                esc_html__('%1$sMemberPress: Your PHP version (%2$s) is out of date!%3$s This version has reached official End Of Life and as such may expose your site to security vulnerabilities. Please contact your web hosting provider to update to %4$s or newer', 'memberpress'),
                '<strong>',
                $current_php_version,
                '</strong>',
                MEPR_MIN_PHP_VERSION
            );
            ?>
     <div class="notice notice-warning is-dismissible">
         <p><?php echo $message; ?></p>
     </div>
            <?php
        }
    }

    /**
     * Show a 'Get Started' notice if certain conditions are met.
     *
     * @return void
     */
    public static function maybe_show_get_started_notice()
    {
        $mepr_options = MeprOptions::fetch();

        // Only show to users who have access, and those who haven't already dismissed it.
        if (!MeprUtils::is_mepr_admin() || get_user_meta(get_current_user_id(), 'mepr_dismiss_notice_get_started')) {
            return;
        }

        $has_payment_method = count($mepr_options->integrations) > 0;
        $has_product        = MeprProduct::count() > 0;
        $has_rule           = MeprRule::count() > 0;

        // Don't show if a payment method, membership and rule already exist.
        if ($has_payment_method && $has_product && $has_rule) {
            return;
        }

        MeprView::render('/admin/get_started', compact('has_payment_method', 'has_product', 'has_rule'));
    }

    /**
     * Dismiss a specific admin notice via AJAX.
     *
     * @return void
     */
    public static function dismiss_notice()
    {
        if (check_ajax_referer('mepr_dismiss_notice', false, false) && isset($_POST['notice']) && is_string($_POST['notice'])) {
            $notice = sanitize_key($_POST['notice']);
            update_user_meta(get_current_user_id(), "mepr_dismiss_notice_{$notice}", true);
        }

        wp_send_json_success();
    }

    /**
     * Dismiss a global admin notice via AJAX.
     *
     * @return void
     */
    public static function dismiss_global_notice()
    {
        if (check_ajax_referer('mepr_dismiss_notice', false, false) && isset($_POST['notice']) && is_string($_POST['notice'])) {
            $notice = sanitize_key($_POST['notice']);
            update_option("mepr_dismiss_notice_{$notice}", true);
        }

        wp_send_json_success();
    }

    /**
     * Dismiss a daily admin notice via AJAX.
     *
     * @return void
     */
    public static function dismiss_daily_notice()
    {
        if (check_ajax_referer('mepr_dismiss_notice', false, false) && isset($_POST['notice']) && is_string($_POST['notice'])) {
            $notice = sanitize_key($_POST['notice']);
            set_transient("mepr_dismiss_notice_{$notice}", true, DAY_IN_SECONDS);
        }

        wp_send_json_success();
    }

    /**
     * Dismiss a weekly admin notice via AJAX.
     *
     * @return void
     */
    public static function dismiss_weekly_notice()
    {
        if (check_ajax_referer('mepr_dismiss_notice', false, false) && isset($_POST['notice']) && is_string($_POST['notice'])) {
            $notice = sanitize_key($_POST['notice']);
            set_transient("mepr_dismiss_notice_{$notice}", true, WEEK_IN_SECONDS);
        }

        wp_send_json_success();
    }

    /**
     * Render the unauthorized access meta box.
     *
     * @return void
     */
    public static function unauthorized_meta_box()
    {
        global $post;

        $mepr_options = MeprOptions::fetch();

        $_wpnonce = wp_create_nonce('mepr_unauthorized');

        $unauthorized_message_type = get_post_meta($post->ID, '_mepr_unauthorized_message_type', true);
        if (!$unauthorized_message_type) {
            $unauthorized_message_type = 'default';
        }

        $unauthorized_message = get_post_meta($post->ID, '_mepr_unauthorized_message', true);
        if (!$unauthorized_message) {
            $unauthorized_message = '';
        }

        $unauth_excerpt_type = get_post_meta($post->ID, '_mepr_unauth_excerpt_type', true);

        // Backwards compatibility here people.
        if ($unauthorized_message_type == 'excerpt') {
            $unauthorized_message_type = 'hide';
            if (empty($unauth_excerpt_type)) {
                $unauth_excerpt_type = 'show';
            }
        }

        if (empty($unauth_excerpt_type)) {
            $unauth_excerpt_type = 'default';
        }

        $unauth_excerpt_size = get_post_meta($post->ID, '_mepr_unauth_excerpt_size', true);

        if ($unauth_excerpt_size === '' or !is_numeric($unauth_excerpt_size)) {
            $unauth_excerpt_size = 100;
        }

        $unauth_login = get_post_meta($post->ID, '_mepr_unauth_login', true);

        if ($unauth_login == '') {
            // Backwards compatibility.
            $hide_login   = get_post_meta($post->ID, '_mepr_hide_login_form', true);
            $unauth_login = (empty($hide_login) ? 'default' : 'show');
        }

        MeprView::render('/admin/unauthorized_meta_box', get_defined_vars());
    }

    /**
     * Save the meta box data when a post is saved.
     *
     * @param integer $post_id The ID of the post being saved.
     *
     * @return integer The post ID.
     */
    public static function save_meta_boxes($post_id)
    {
        // Verify the Nonce First.
        if (!isset($_REQUEST['mepr_custom_unauthorized_nonce']) || !wp_verify_nonce($_REQUEST['mepr_custom_unauthorized_nonce'], 'mepr_unauthorized')) {
            return $post_id;
        }

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || defined('DOING_AJAX')) {
            return $post_id;
        }

        // First we need to check if the current user is authorized to do this action.
        if ('page' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }

        // If saving in a custom table, get post_ID.
        $post_ID = $_REQUEST['post_ID'];

        update_post_meta($post_ID, '_mepr_unauthorized_message_type', $_REQUEST['_mepr_unauthorized_message_type']);
        update_post_meta($post_ID, '_mepr_unauthorized_message', $_REQUEST['_mepr_unauthorized_message']);
        update_post_meta($post_ID, '_mepr_unauth_login', $_REQUEST['_mepr_unauth_login']);
        update_post_meta($post_ID, '_mepr_unauth_excerpt_type', $_REQUEST['_mepr_unauth_excerpt_type']);
        update_post_meta($post_ID, '_mepr_unauth_excerpt_size', $_REQUEST['_mepr_unauth_excerpt_size']);
    }

    /**
     * Set up the admin menus.
     *
     * @return void
     */
    public static function setup_menus()
    {
        add_action('admin_menu', 'MeprAppCtrl::menu');
        // Admin Menu Bar.
        add_action('admin_bar_menu', 'MeprAppCtrl::admin_bar_menu', 100);
    }

    /**
     * Add items to the WordPress admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar The WordPress admin bar object.
     *
     * @return void
     */
    public static function admin_bar_menu($wp_admin_bar)
    {
        $mepr_options = MeprOptions::fetch();

        if (! MeprUtils::is_mepr_admin()) {
            return;
        }

        if ($mepr_options->hide_admin_bar_menu) {
            return;
        }

        $notifications_count = MeprNotifications::has_access() ?
            count(
                MeprGrdLvlCtrl::getContainer()->get(Store::class)->fetch()->notifications(false, Store::FILTER_UNREAD)
            ) : 0;
        $notifications_icon  = '';
        if ($notifications_count) {
            ob_start(); ?>
            <span>
                <span class="mepr-admin-bar-notifications-count" aria-hidden="true">
                    <?php echo $notifications_count; ?>
                </span>
                <span class="screen-reader-text">
                    <?php sprintf(
                        // Translators: %1$d: number of unread messages.
                        __('%1$d unread message(s)', 'memberpress'),
                        $notifications_count
                    ); ?>
                </span>
            </span>
            <?php
            $notifications_icon = ob_get_clean();
        }

        $wp_admin_bar->add_menu([
            'id'    => 'mepr_admin_bar',
            'title' => __('MemberPress', 'memberpress') . $notifications_icon,
            'href'  => admin_url('admin.php?page=memberpress-options'),
        ]);

        if ($notifications_count) {
            $wp_admin_bar->add_node([
                'id'     => 'mepr_admin_bar_messages',
                'parent' => 'mepr_admin_bar',
                'title'  => __('Messages', 'memberpress') . $notifications_icon,
                'href'   => admin_url('admin.php?page=memberpress-options&show=notifications'),
            ]);
        }

        $wp_admin_bar->add_node([
            'id'     => 'mepr_admin_bar_reports',
            'parent' => 'mepr_admin_bar',
            'title'  => __('Reports', 'memberpress'),
            'href'   => admin_url('admin.php?page=memberpress-reports'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'mepr_admin_bar_settings',
            'parent' => 'mepr_admin_bar',
            'title'  => __('Settings', 'memberpress'),
            'href'   => admin_url('admin.php?page=memberpress-options'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'mepr_admin_bar_members',
            'parent' => 'mepr_admin_bar',
            'title'  => __('Members', 'memberpress'),
            'href'   => admin_url('admin.php?page=memberpress-members'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'mepr_admin_bar_coupons',
            'parent' => 'mepr_admin_bar',
            'title'  => __('Coupons', 'memberpress'),
            'href'   => admin_url('edit.php?post_type=memberpresscoupon'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'mepr_admin_bar_memberships',
            'parent' => 'mepr_admin_bar',
            'title'  => __('Memberships', 'memberpress'),
            'href'   => admin_url('edit.php?post_type=memberpressproduct'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'mepr_admin_bar_subs',
            'parent' => 'mepr_admin_bar',
            'title'  => __('Subscriptions', 'memberpress'),
            'href'   => admin_url('admin.php?page=memberpress-subscriptions'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'mepr_admin_bar_trans',
            'parent' => 'mepr_admin_bar',
            'title'  => __('Transactions', 'memberpress'),
            'href'   => admin_url('admin.php?page=memberpress-trans'),
        ]);
    }

    /**
     * Redirect to the MemberPress options page.
     *
     * @return void
     */
    public static function toplevel_menu_route()
    {
        ?>
    <script>
      window.location.href="<?php echo admin_url('admin.php?page=memberpress-options'); ?>";
    </script>
        <?php
    }

    /**
     * Redirect to the MemberPress members page.
     *
     * @return void
     */
    public static function toplevel_menu_drm_route()
    {
        ?>
    <script>
      window.location.href="<?php echo admin_url('admin.php?page=memberpress-members'); ?>";
    </script>
        <?php
    }

    /**
     * Set up the MemberPress menu.
     *
     * @return void
     */
    public static function menu()
    {
        if (MeprDrmHelper::is_locked()) {
            MeprAppCtrl::mepr_drm_menu();
        } else {
            MeprAppCtrl::mepr_menu();
        }
    }

    /**
     * Move our custom separator above our admin menu
     *
     * @param  array $menu_order Menu Order.
     * @return array Modified menu order
     */
    public static function admin_menu_order($menu_order)
    {
        if (!$menu_order) {
            return $menu_order;
        }

        // Initialize our custom order array.
        $new_menu_order = [];

        // Menu values.
        $first_sep    = 'separator1';
        $custom_menus = ['memberpress'];

        // Loop through menu order and do some rearranging.
        foreach ($menu_order as $item) {
            // Position MemberPress menus below Dashboard.
            if ($first_sep == $item) {
                // Add our custom menus.
                foreach ($custom_menus as $custom_menu) {
                    if (array_search($custom_menu, $menu_order)) {
                        $new_menu_order[] = $custom_menu;
                    }
                }

                // Add the dashboard separator.
                $new_menu_order[] = $first_sep;

                // Skip our menu items down below.
            } elseif (!in_array($item, $custom_menus)) {
                $new_menu_order[] = $item;
            }
        }

        // Return our custom order.
        return $new_menu_order;
    }

    /**
     * Organize the CPT's in our submenu
     *
     * @param  array $menu_order Menu Order.
     * @return array Modified menu order
     */
    public static function admin_submenu_order($menu_order)
    {
        global $submenu;

        static $run = false;

        // No sense in running this everytime the hook gets called.
        if ($run) {
            return $menu_order;
        }

        // Just return if there's no memberpress menu available for the current screen.
        if (!isset($submenu['memberpress'])) {
            return $menu_order;
        }

        $run       = true;
        $new_order = [];
        $i         = 5;

        foreach ($submenu['memberpress'] as $sub) {
            if ($sub[0] == __('Memberships', 'memberpress')) {
                $new_order[0] = $sub;
            } elseif ($sub[0] == __('Groups', 'memberpress')) {
                $new_order[1] = $sub;
            } elseif ($sub[0] == __('Rules', 'memberpress')) {
                $new_order[2] = $sub;
            } elseif ($sub[0] == __('Coupons', 'memberpress')) {
                $new_order[3] = $sub;
            } elseif (0 === strpos($sub[0], __('Courses', 'memberpress'))) {
                $new_order[4] = $sub;
            } else {
                $new_order[$i++] = $sub;
            }
        }

        ksort($new_order);

        $submenu['memberpress'] = $new_order;

        return $menu_order;
    }

    /**
     * Set up the pages that should not show on the menu.
     *
     * Removes the menu item and highlights the most closely related menu item instead.
     *
     * @param  string $submenu_file The submenu file.
     * @return string
     */
    public static function set_up_hidden_pages($submenu_file)
    {
        remove_submenu_page('memberpress', 'memberpress-lifetimes');
        remove_submenu_page('memberpress', 'memberpress-support');

        $id = MeprUtils::get_current_screen_id();

        if (!empty($id) && is_string($id)) {
            if (preg_match('/_page_memberpress-lifetimes$/', $id)) {
                $submenu_file = 'memberpress-subscriptions';
            } elseif (preg_match('/_page_memberpress-support$/', $id)) {
                $submenu_file = 'memberpress-options';
            }
        }

        return $submenu_file;
    }

    /**
     * Register global widget areas for ReadyLaunch templates.
     *
     * @return void
     */
    public static function register_global_widget_area()
    {
        if (
            MeprReadyLaunchCtrl::template_active('account') ||
            MeprReadyLaunchCtrl::template_active('checkout') ||
            MeprReadyLaunchCtrl::template_active('thankyou') ||
            MeprReadyLaunchCtrl::template_active('login') ||
            MeprReadyLaunchCtrl::template_active('pricing')
        ) {
            register_sidebar([
                'name'          => _x('ReadyLaunch™️ General Footer', 'ui', 'memberpress'),
                'description'   => __('Widgets in this area will be shown at the bottom of all ReadyLaunch pages.', 'memberpress'),
                'id'            => 'mepr_rl_global_footer',
                'before_widget' => '<div>',
                'after_widget'  => '</div>',
                'before_title'  => '<h2>',
                'after_title'   => '</h2>',
            ]);
        }

        if (MeprReadyLaunchCtrl::template_active('account')) {
            register_sidebar([
                'name'          => _x('ReadyLaunch™️ Account Footer', 'ui', 'memberpress'),
                'description'   => __('Widgets in this area will be shown at the bottom of ReadyLaunch Account page.', 'memberpress'),
                'id'            => 'mepr_rl_account_footer',
                'before_widget' => '<div>',
                'after_widget'  => '</div>',
                'before_title'  => '<h2>',
                'after_title'   => '</h2>',
            ]);
        }

        if (MeprReadyLaunchCtrl::template_active('login')) {
            register_sidebar([
                'name'          => _x('ReadyLaunch™️ Login Footer', 'ui', 'memberpress'),
                'description'   => __('Widgets in this area will be shown at the bottom of ReadyLaunch Login page.', 'memberpress'),
                'id'            => 'mepr_rl_login_footer',
                'before_widget' => '<div>',
                'after_widget'  => '</div>',
                'before_title'  => '<h2>',
                'after_title'   => '</h2>',
            ]);
        }

        if (MeprReadyLaunchCtrl::template_active('checkout')) {
            register_sidebar([
                'name'          => _x('ReadyLaunch™️ Registration Footer', 'ui', 'memberpress'),
                'description'   => __('Widgets in this area will be shown at the bottom of ReadyLaunch Registration pages.', 'memberpress'),
                'id'            => 'mepr_rl_registration_footer',
                'before_widget' => '<div>',
                'after_widget'  => '</div>',
                'before_title'  => '<h2>',
                'after_title'   => '</h2>',
            ]);
        }
    }

    /**
     * Routes for WordPress pages -- we're just replacing content here folks.
     *
     * @param  string $content The content.
     * @return string The content.
     */
    public static function page_route($content)
    {
        $current_post = MeprUtils::get_current_post();

        // This isn't a post? Just return the content then.
        if ($current_post === false) {
            return $content;
        }

        // WARNING the_content CAN be run more than once per page load
        // so this static var prevents stuff from happening twice
        // like cancelling a subscr or resuming etc...
        static $already_run    = [];
        static $new_content    = [];
        static $content_length = [];

        // Init this posts static values.
        if (!isset($new_content[$current_post->ID]) || empty($new_content[$current_post->ID])) {
            $already_run[$current_post->ID]    = false;
            $new_content[$current_post->ID]    = '';
            $content_length[$current_post->ID] = -1;
        }

        if ($already_run[$current_post->ID] && strlen($content) == $content_length[$current_post->ID]) {
            return $new_content[$current_post->ID];
        }

        $content_length[$current_post->ID] = strlen($content);
        $already_run[$current_post->ID]    = true;

        $mepr_options = MeprOptions::fetch();

        switch ($current_post->ID) {
            case $mepr_options->account_page_id:
                if (!MeprUser::manually_place_account_form($current_post)) {
                    try {
                        $account_ctrl = MeprCtrlFactory::fetch('account');
                        $content      = $account_ctrl->display_account_form($content);
                    } catch (Exception $e) {
                        ob_start();
                        ?>
            <div class="mepr_error"><?php _e('We can\'t display your account form right now. Please come back soon and try again.', 'memberpress'); ?></div>
                        <?php
                        $content = ob_get_clean();
                    }
                }
                break;
            case $mepr_options->login_page_id:
                ob_start();

                $action            = self::get_param('action');
                $manual_login_form = get_post_meta($current_post->ID, '_mepr_manual_login_form', true);
                try {
                    $login_ctrl = MeprCtrlFactory::fetch('login');

                    if ($action and $action == 'forgot_password') {
                        $login_ctrl->display_forgot_password_form();
                    } elseif ($action and $action == 'mepr_process_forgot_password') {
                        $login_ctrl->process_forgot_password_form();
                    } elseif ($action and $action == 'reset_password') {
                        $login_ctrl->display_reset_password_form(self::get_param('mkey', ''), self::get_param('u', ''));
                    } elseif ($action and $action === 'mepr_process_reset_password_form' && isset($_POST['errors'])  && !empty($_POST['errors'])) {
                        $login_ctrl->display_reset_password_form_errors($_POST['errors']);
                    } elseif (!$manual_login_form || ($manual_login_form && $action == 'mepr_unauthorized')) {
                        $message = '';

                        if ($action and $action == 'mepr_unauthorized') {
                            $resource       = isset($_REQUEST['redirect_to']) ? esc_url(urldecode($_REQUEST['redirect_to'])) : __('the requested resource.', 'memberpress');
                            $unauth_message = $mepr_options->unauthorized_message;

                            // Maybe override the message if a page id is set.
                            if (isset($_GET['mepr-unauth-page'])) {
                                $unauth_post    = get_post((int)$_GET['mepr-unauth-page']);
                                $unauth         = MeprRule::get_unauth_settings_for($unauth_post);
                                $unauth_message = $unauth->message;
                            }

                            $unauth_message = wpautop(MeprHooks::apply_filters('mepr-unauthorized-message', do_shortcode($unauth_message), $current_post));

                            $message = '<p id="mepr-unauthorized-for-resource">' . __('Unauthorized for', 'memberpress') . ': <span id="mepr-unauthorized-resource-url">' . $resource . '</span></p>' . $unauth_message;
                        }

                        $login_ctrl->display_login_form(false, false, $message);
                    }
                } catch (Exception $e) {
                    $login_actions = [
                        'forgot_password',
                        'mepr_process_forgot_password',
                        'reset_password',
                        'mepr_process_reset_password_form',
                        'mepr_unauthorized',
                    ];

                    if ($action && in_array($action, $login_actions)) {
                        ?>
            <div class="mepr_error"><?php _e('There was a problem with our system. Please come back soon and try again.', 'memberpress'); ?></div>
                        <?php
                    }
                }

                // Some crazy trickery here to prevent from having to completely rewrite a lot of crap
                // This is a fix for https://github.com/Caseproof/memberpress/issues/609.
                if (!$manual_login_form || ($action && $action == 'bpnoaccess')) { // BuddyPress fix.
                    $content .= ob_get_clean();
                } elseif ($action) {
                    $match_str = '#' . preg_quote('<!-- mp-login-form-start -->') . '.*' . preg_quote('<!-- mp-login-form-end -->') . '#s';
                    // The preg_quote below helps fix an issue with the math captcha add-on when using a shortcode for login.
                    $content = stripslashes(preg_replace($match_str, ob_get_clean(), preg_quote($content)));
                } else { // Do nothing really.
                    ob_end_clean();
                }
                break;
            case $mepr_options->thankyou_page_id:
                $message = MeprProductsCtrl::maybe_get_thank_you_page_message();

                // If a custom message is set, only show that message.
                if ($message != '') {
                    $content = $message;
                }
                break;
        }

        // See above notes.
        $new_content[$current_post->ID] = $content;
        return $new_content[$current_post->ID];
    }

    /**
     * Load scripts.
     *
     * @return void
     */
    public static function load_scripts()
    {
        global $post;

        $mepr_options = MeprOptions::fetch();

        $is_product_page = ( false !== ( $prd = MeprProduct::is_product_page($post) ) );
        $is_group_page   = ( false !== ( $grp = MeprGroup::is_group_page($post) ) );
        $is_login_page   = MeprUser::is_login_page($post);
        $is_account_page = MeprUser::is_account_page($post);
        $global_styles   = $mepr_options->global_styles;

        MeprHooks::do_action('mepr_enqueue_scripts', $is_product_page, $is_group_page, $is_account_page);

        // Yeah we enqueue this globally all the time so the login form will work on any page.
        wp_enqueue_style('mp-theme', MEPR_CSS_URL . '/ui/theme.css', null, MEPR_VERSION);

        if (($global_styles || $is_account_page) && ! has_block('memberpress/pro-account-tabs')) {
            wp_enqueue_style('mp-account-css', MEPR_CSS_URL . '/ui/account.css', null, MEPR_VERSION);
        }

        if (
            $global_styles ||
            $is_login_page ||
            has_shortcode(get_the_content(null, false, $post), 'mepr-login-form') ||
            is_active_widget(false, false, 'mepr_login_widget') ||
            (!$mepr_options->redirect_on_unauthorized && $mepr_options->unauth_show_login && (isset($post) && is_a($post, 'WP_Post') && MeprRule::is_locked($post) || MeprRule::is_uri_locked(esc_url($_SERVER['REQUEST_URI']))))
        ) {
            wp_enqueue_style('dashicons');
            wp_enqueue_style('mp-login-css', MEPR_CSS_URL . '/ui/login.css', null, MEPR_VERSION);

            wp_register_script('mepr-login-js', MEPR_JS_URL . '/login.js', ['jquery', 'underscore', 'wp-i18n'], MEPR_VERSION);

            wp_enqueue_script('mepr-login-i18n');
            wp_enqueue_script('mepr-login-js');
        }

        if ($global_styles || $is_product_page || $is_account_page) {
            wp_enqueue_style('mepr-jquery-ui-smoothness', MEPR_CSS_URL . '/vendor/jquery-ui/smoothness.min.css', [], '1.13.3');
            wp_enqueue_style('jquery-ui-timepicker-addon', MEPR_CSS_URL . '/vendor/jquery-ui-timepicker-addon.css', ['mepr-jquery-ui-smoothness'], MEPR_VERSION);

            $popup_ctrl = new MeprPopupCtrl();
            wp_enqueue_style('jquery-magnific-popup', $popup_ctrl->popup_css);
            wp_enqueue_script('jquery-magnific-popup', $popup_ctrl->popup_js, ['jquery']);

            $prereqs = MeprHooks::apply_filters('mepr-signup-styles', []);
            wp_enqueue_style('mp-signup', MEPR_CSS_URL . '/signup.css', $prereqs, MEPR_VERSION);

            wp_register_script('mepr-timepicker-js', MEPR_JS_URL . '/vendor/jquery-ui-timepicker-addon.js', ['jquery-ui-datepicker'], MEPR_VERSION);
            wp_register_script('mp-datepicker', MEPR_JS_URL . '/date_picker.js', ['mepr-timepicker-js'], MEPR_VERSION);

            $date_picker_frontend = [
                'translations' => self::get_datepicker_strings(),
                'timeFormat'   => (is_admin()) ? 'HH:mm:ss' : '',
                'dateFormat'   => MeprUtils::datepicker_format(get_option('date_format')),
                'showTime'     => (is_admin()) ? true : false,
            ];
            wp_localize_script('mp-datepicker', 'MeprDatePicker', $date_picker_frontend);

            wp_register_script('jquery.payment', MEPR_JS_URL . '/vendor/jquery.payment.js', [], MEPR_VERSION);
            wp_register_script('mp-validate', MEPR_JS_URL . '/validate.js', [], MEPR_VERSION);
            wp_register_script('mp-i18n', MEPR_JS_URL . '/i18n.js', [], MEPR_VERSION);

            $i18n                        = [
                'states'  => MeprUtils::states(),
                'ajaxurl' => admin_url('admin-ajax.php'),
            ];
            $i18n['please_select_state'] = __('-- Select State --', 'memberpress');
            wp_localize_script('mp-i18n', 'MeprI18n', $i18n);

            $prereqs = MeprHooks::apply_filters(
                'mepr-signup-scripts',
                ['jquery','jquery.payment','mp-validate','mp-i18n','mp-datepicker'],
                $is_product_page,
                $is_account_page
            );

            wp_enqueue_script('mp-signup', MEPR_JS_URL . '/signup.js', $prereqs, MEPR_VERSION);

            $local_data = [
                'coupon_nonce'                  => wp_create_nonce('mepr_coupons'),
                'spc_enabled'                   => ( $mepr_options->enable_spc || $mepr_options->design_enable_checkout_template ),
                'spc_invoice'                   => ( $mepr_options->enable_spc_invoice || $mepr_options->design_enable_checkout_template ),
                'no_compatible_pms'             => (
                    // Translators: %s: payment method name.
                    __('There are no payment methods available that can purchase this product, please contact the site administrator or purchase it separately.', 'memberpress')
                ),
                'switch_pm_prompt'              => (
                    // Translators: %s: payment method name.
                    __('It looks like your purchase requires %s. No problem! Just click below to switch.', 'memberpress')
                ),
                'switch_pm'                     => (
                    // Translators: %s: payment method name.
                    __('Switch to %s', 'memberpress')
                ),
                'cancel'                        => __('Cancel', 'memberpress'),
                'no_compatible_pms_ob_required' => __('Payment Gateway(s) do not support required order configuration.', 'memberpress'),
                'warning_icon_url'              => MEPR_IMAGES_URL . '/notice-icon-error.png',
            ];

            wp_localize_script('mp-signup', 'MeprSignup', $local_data);

            // For Show hide password.
            wp_enqueue_style('dashicons');
            wp_enqueue_style('mp-login-css', MEPR_CSS_URL . '/ui/login.css', null, MEPR_VERSION);

            wp_register_script('mepr-login-js', MEPR_JS_URL . '/login.js', ['jquery', 'underscore', 'wp-i18n'], MEPR_VERSION);

            wp_enqueue_script('mepr-login-i18n');
            wp_enqueue_script('mepr-login-js');
        }

        if ($global_styles || $is_group_page) {
            wp_enqueue_style('mp-plans-css', MEPR_CSS_URL . '/plans.min.css', [], MEPR_VERSION);
        }
    }

    /**
     * Get the datepicker strings for localization.
     *
     * @return array The datepicker strings.
     */
    public static function get_datepicker_strings()
    {
        return [
            'closeText'       => _x('Done', 'ui', 'memberpress'),
            'currentText'     => _x('Today', 'ui', 'memberpress'),
            'monthNamesShort' => [
                _x('Jan', 'ui', 'memberpress'),
                _x('Feb', 'ui', 'memberpress'),
                _x('Mar', 'ui', 'memberpress'),
                _x('Apr', 'ui', 'memberpress'),
                _x('May', 'ui', 'memberpress'),
                _x('Jun', 'ui', 'memberpress'),
                _x('Jul', 'ui', 'memberpress'),
                _x('Aug', 'ui', 'memberpress'),
                _x('Sep', 'ui', 'memberpress'),
                _x('Oct', 'ui', 'memberpress'),
                _x('Nov', 'ui', 'memberpress'),
                _x('Dec', 'ui', 'memberpress'),
            ],
            'dayNamesMin'     => [_x('Su', 'ui', 'memberpress'),_x('Mo', 'ui', 'memberpress'),_x('Tu', 'ui', 'memberpress'),_x('We', 'ui', 'memberpress'),_x('Th', 'ui', 'memberpress'),_x('Fr', 'ui', 'memberpress'),_x('Sa', 'ui', 'memberpress')],
        ];
    }

    /**
     * Enqueue scripts and styles for the admin area.
     *
     * @param string $hook The current admin page hook.
     *
     * @return void
     */
    public static function load_admin_scripts($hook)
    {
        global $wp_version;

        $popup_ctrl = new MeprPopupCtrl();
        wp_enqueue_style('jquery-magnific-popup', $popup_ctrl->popup_css);

        wp_register_style(
            'mepr-settings-table-css',
            MEPR_CSS_URL . '/settings_table.css',
            [],
            MEPR_VERSION
        );
        wp_enqueue_style(
            'mepr-admin-shared-css',
            MEPR_CSS_URL . '/admin-shared.css',
            ['wp-pointer','jquery-magnific-popup','mepr-settings-table-css'],
            MEPR_VERSION
        );
        wp_enqueue_style(
            'mepr-fontello-animation',
            MEPR_FONTS_URL . '/fontello/css/animation.css',
            [],
            MEPR_VERSION
        );
        wp_enqueue_style(
            'mepr-fontello-memberpress',
            MEPR_FONTS_URL . '/fontello/css/memberpress.css',
            ['mepr-fontello-animation'],
            MEPR_VERSION
        );

        wp_register_script('jquery-magnific-popup', $popup_ctrl->popup_js, ['jquery']);
        wp_enqueue_script('mepr-tooltip', MEPR_JS_URL . '/tooltip.js', ['jquery','wp-pointer','jquery-magnific-popup'], MEPR_VERSION);
        wp_localize_script('mepr-tooltip', 'MeprTooltip', [
            'show_about_notice' => self::show_about_notice(),
            'about_notice'      => self::about_notice(),
        ]);
        wp_register_script('mepr-settings-table-js', MEPR_JS_URL . '/settings_table.js', ['jquery'], MEPR_VERSION);
        wp_register_script('mepr-cookie-js', MEPR_JS_URL . '/vendor/js.cookie.min.js', [], '2.2.1');
        wp_enqueue_script('mepr-admin-shared-js', MEPR_JS_URL . '/admin_shared.js', ['jquery', 'jquery-magnific-popup', 'mepr-settings-table-js', 'mepr-cookie-js'], MEPR_VERSION);
        wp_localize_script('mepr-admin-shared-js', 'MeprAdminShared', [
            'ajax_url'                => admin_url('admin-ajax.php'),
            'dismiss_notice_nonce'    => wp_create_nonce('mepr_dismiss_notice'),
            'enable_stripe_tax_nonce' => wp_create_nonce('mepr_enable_stripe_tax'),
        ]);

        // Widget in the dashboard stuff.
        if ($hook == 'index.php') {
            $local_data = [
                'report_nonce' => wp_create_nonce('mepr_reports'),
            ];
            wp_enqueue_script('mepr-google-jsapi', 'https://www.gstatic.com/charts/loader.js', [], MEPR_VERSION);
            wp_enqueue_script('mepr-widgets-js', MEPR_JS_URL . '/admin_widgets.js', ['jquery', 'mepr-google-jsapi'], MEPR_VERSION, true);
            wp_localize_script('mepr-widgets-js', 'MeprWidgetData', $local_data);
            wp_enqueue_style('mepr-widgets-css', MEPR_CSS_URL . '/admin-widgets.css', [], MEPR_VERSION);
        }
    }

    /**
     * The tight way to process standalone requests dogg...
     *
     * @return void
     */
    public static function parse_standalone_request()
    {
        global $user_ID;

        $plugin     = (isset($_REQUEST['plugin'])) ? $_REQUEST['plugin'] : '';
        $action     = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : '';
        $controller = (isset($_REQUEST['controller'])) ? $_REQUEST['controller'] : '';

        $request_uri = $_SERVER['REQUEST_URI'];

        // Pretty Mepr Notifier ... prevents POST vars from being mangled.
        $notify_url_pattern = MeprUtils::gateway_notify_url_regex_pattern();
        if (MeprUtils::match_uri($notify_url_pattern, $request_uri, $m)) {
            $plugin          = 'mepr';
            $_REQUEST['pmt'] = $m[1];
            $action          = $m[2];
        }

        try {
            if (MeprUtils::is_post_request() && isset($_POST['mepr_process_signup_form'])) {
                if (
                    MeprUtils::is_user_logged_in() &&
                    isset($_POST['logged_in_purchase']) &&
                    $_POST['logged_in_purchase'] == 1
                ) {
                    check_admin_referer('logged_in_purchase', 'mepr_checkout_nonce');
                }

                $checkout_ctrl = MeprCtrlFactory::fetch('checkout');
                $checkout_ctrl->process_signup_form();
            } elseif (isset($_POST) && isset($_POST['mepr_process_payment_form'])) {
                $checkout_ctrl = MeprCtrlFactory::fetch('checkout');
                $checkout_ctrl->process_payment_form();
            } elseif ($action === 'checkout' && isset($_REQUEST['txn'])) {
                $_REQUEST['txn'] = MeprUtils::base36_decode($_REQUEST['txn']);

                // Back button fix.
                $txn = new MeprTransaction((int)$_REQUEST['txn']);
                if (strpos($txn->trans_num, 'mp-txn-') === false || $txn->status != MeprTransaction::$pending_str) {
                    $prd = new MeprProduct($txn->product_id);
                    MeprUtils::wp_redirect($prd->url());
                }

                $checkout_ctrl = MeprCtrlFactory::fetch('checkout');
                $checkout_ctrl->display_payment_page();
            } elseif (isset($_POST) && isset($_POST['mepr_process_login_form'])) {
                $login_ctrl = MeprCtrlFactory::fetch('login');
                $login_ctrl->process_login_form();
            } elseif (
                MeprUtils::is_post_request() && $plugin == 'mepr' && $action == 'updatepassword' &&
                isset($_POST['mepr-new-password']) && isset($_POST['mepr-confirm-password'])
            ) {
                check_admin_referer('update_password', 'mepr_account_nonce');
                $account_ctrl = MeprCtrlFactory::fetch('account');
                $account_ctrl->save_new_password($user_ID, $_POST['mepr-new-password'], $_POST['mepr-confirm-password']);
            } elseif (!empty($plugin) && $plugin == 'mepr' && !empty($controller) && !empty($action)) {
                self::standalone_route($controller, $action);
                exit;
            } elseif (
                !empty($plugin) && $plugin == 'mepr' && isset($_REQUEST['pmt']) &&
                !empty($_REQUEST['pmt']) && !empty($action)
            ) {
                $mepr_options = MeprOptions::fetch();
                $obj          = MeprHooks::apply_filters('mepr_gateway_notifier_obj', $mepr_options->payment_method($_REQUEST['pmt']), $action, $_REQUEST['pmt']);
                if ($obj && ( $obj instanceof MeprBaseRealGateway )) {
                    $notifiers = $obj->notifiers();
                    if (isset($notifiers[$action])) {
                        nocache_headers();
                        call_user_func([$obj,$notifiers[$action]]);
                        exit;
                    }
                }
            }
        } catch (Exception $e) { ?>
            <div class="mepr_error">
                <?php printf(
                // Translators: %s: error message.
                    __('There was a problem with our system: %s. Please come back soon and try again.', 'memberpress'),
                    $e->getMessage()
                ); ?>
            </div>
            <?php
            exit;
        }
    }

    /**
     * Routes for standalone / ajax requests.
     *
     * @param string $controller The controller to route to.
     * @param string $action     The action to perform.
     *
     * @return void
     */
    public static function standalone_route($controller, $action)
    {
        if ($controller == 'coupons') {
            if ($action == 'validate') {
                MeprCouponsCtrl::validate_coupon_ajax(MeprAppCtrl::get_param('mepr_coupon_code'), MeprAppCtrl::get_param('mpid'));
            }
        }
    }

    /**
     * Load the plugin's translated strings.
     *
     * For backwards-compatibility only. It is recommended to add the .mo file into the /wp-content/languages/plugins
     * directory instead.
     *
     * @return void
     */
    public static function load_language()
    {
        if (is_dir(WP_PLUGIN_DIR . '/mepr-i18n')) {
            load_plugin_textdomain('memberpress', false, 'mepr-i18n');
        }
    }

    /**
     * Load the plugin's translations.
     *
     * @return void
     */
    public static function load_translations()
    {
        if (MeprHooks::apply_filters('mepr-remove-traduttore', false)) {
            return;
        }
        // Load Traduttore.
        require_once(MEPR_I18N_PATH . '/namespace.php');

        \MemberPress\Traduttore_Registry\add_project(
            'plugin',
            'memberpress',
            'https://translate.memberpress.com/wp-content/uploads/api/translations/memberpress.json'
        );
    }

    /**
     * Utility function to grab the parameter whether it's a get or post
     *
     * @param  string $param   The parameter to get.
     * @param  string $default The default value.
     * @return string The parameter value.
     */
    public static function get_param($param, $default = '')
    {
        return (isset($_REQUEST[$param]) ? $_REQUEST[$param] : $default);
    }

    /**
     * Get the parameter delimiter character for a URL.
     *
     * @param  string $link The URL link.
     * @return string The delimiter character.
     */
    public static function get_param_delimiter_char($link)
    {
        return ((preg_match('#\?#', $link)) ? '&' : '?');
    }

    /**
     * Add dashboard widgets for MemberPress.
     *
     * @return void
     */
    public static function add_dashboard_widgets()
    {
        if (!MeprUtils::is_mepr_admin()) {
            return;
        }

        wp_add_dashboard_widget('mepr_weekly_stats_widget', 'MemberPress Weekly Stats', 'MeprAppCtrl::weekly_stats_widget');

        // Globalize the metaboxes array, this holds all the widgets for wp-admin.
        global $wp_meta_boxes;

        // Get the regular dashboard widgets array
        // (which has our new widget already but at the end).
        $normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];

        // Backup and delete our new dashbaord widget from the end of the array.
        $mepr_weekly_stats_widget_backup = ['mepr_weekly_stats_widget' => $normal_dashboard['mepr_weekly_stats_widget']];
        unset($normal_dashboard['mepr_weekly_stats_widget']);

        // Merge the two arrays together so our widget is at the beginning.
        $sorted_dashboard = array_merge($mepr_weekly_stats_widget_backup, $normal_dashboard);

        // Save the sorted array back into the original metaboxes.
        $wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
    }

    /**
     * Render the weekly stats widget on the dashboard.
     *
     * @return void
     */
    public static function weekly_stats_widget()
    {
        $mepr_options = MeprOptions::fetch();

        $status_collection = [
            MeprTransaction::$pending_str,
            MeprTransaction::$failed_str,
            MeprTransaction::$refunded_str,
            MeprTransaction::$complete_str,
        ];

        $start_date = new \DateTimeImmutable('-6 days', new \DateTimeZone('UTC'));
        $end_date   = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $status_data            = MeprReports::get_date_range_transactions_counts($status_collection, $start_date, $end_date);
        $pending_transactions   = $status_data[MeprTransaction::$pending_str];
        $failed_transactions    = $status_data[MeprTransaction::$failed_str];
        $refunded_transactions  = $status_data[MeprTransaction::$refunded_str];
        $completed_transactions = $status_data[MeprTransaction::$complete_str];

        $revenue = MeprReports::get_date_range_revenue($start_date, $end_date);
        $refunds = MeprReports::get_date_range_refunds($start_date, $end_date);

        if (empty($revenue) || is_null($revenue)) {
            $revenue = 0;
        }

        if (empty($refunds) || is_null($refunds)) {
            $refunds = 0;
        }

        MeprView::render('/admin/widgets/admin_stats_widget', get_defined_vars());
    }

    /**
     * Output today's date in a specific format.
     *
     * @return void
     */
    public static function todays_date()
    {
        if (isset($_REQUEST['datetime'])) {
            echo date_i18n('Y-m-d H:i:s', time(), true);
        } else {
            echo date_i18n('Y-m-d', time(), true);
        }

        die;
    }

    /**
     * Determine if the 'About' notice should be shown.
     *
     * @return boolean True if the notice should be shown, false otherwise.
     */
    public static function show_about_notice()
    {
        $last_shown_notice = get_option('mepr_about_notice_version');
        $version_str       = preg_replace('/\./', '-', MEPR_VERSION);
        return ( $last_shown_notice != MEPR_VERSION &&
             file_exists(MeprView::file("/admin/about/{$version_str}")) );
    }

    /**
     * Get the content for the 'About' notice.
     *
     * @return string The content of the notice.
     */
    public static function about_notice()
    {
        $version_str  = preg_replace('/\./', '-', MEPR_VERSION);
        $version_file = MeprView::file("/admin/about/{$version_str}");
        if (file_exists($version_file)) {
            ob_start();
            require_once($version_file);
            return ob_get_clean();
        }

        return '';
    }

    /**
     * Close the 'About' notice and update the version.
     *
     * @return void
     */
    public static function close_about_notice()
    {
        update_option('mepr_about_notice_version', MEPR_VERSION);
    }

    /**
     * Clean up the list view by removing certain views.
     *
     * @param array $views The existing views.
     *
     * @return array The modified views.
     */
    public static function cleanup_list_view($views)
    {
        if (isset($views['draft'])) {
            unset($views['draft']);
        }
        if (isset($views['publish'])) {
            unset($views['publish']);
        }
        return $views;
    }

    /**
     * Clean up the month dropdown in the list table.
     *
     * @param array  $months    The existing months.
     * @param string $post_type The post type.
     *
     * @return array The modified months.
     */
    public function cleanup_list_table_month_dropdown($months, $post_type)
    {
        $ours = [MeprProduct::$cpt, MeprRule::$cpt, MeprGroup::$cpt, MeprCoupon::$cpt];
        if (in_array($post_type, $ours)) {
            $months = [];
        }
        return $months;
    }

    /**
     * Load the CSS for the plugin.
     * TODO: We want to eliminate this when we get css compilation / compression in place
     *
     * @return void
     */
    public static function load_css()
    {
        // IF WE MOVE BACK TO admin-ajax.php method, then this conditional needs to go.
        if (
            !isset($_GET['plugin']) ||
            $_GET['plugin'] != 'mepr' ||
            !isset($_GET['action']) ||
            $_GET['action'] != 'mepr_load_css'
        ) {
            return;
        }

        header('Content-Type: text/css');
        header('Cache-Control: max-age=2629000, public'); // 1 month
        header('Expires: ' . gmdate('D, d M Y H:i:s', (int)(time() + 2629000)) . ' GMT'); // 1 month?

        $css = '';

        if (isset($_REQUEST['t']) && $_REQUEST['t'] == 'price_table') {
            $csskey    = 'mp-css-' . md5(MEPR_VERSION);
            $css_files = get_transient($csskey);

            // $css_files = false;
            if (!$css_files) {
                $css_files = [];

                // Enqueue plan templates.
                $css_files = array_merge($css_files, MeprGroup::group_theme_templates(true));

                // Enqueue plans.
                $css_files = array_merge($css_files, MeprGroup::group_themes(true));

                set_transient($csskey, $css_files, DAY_IN_SECONDS);
            }
        }

        if (isset($css_files) && !empty($css_files)) {
            $csskey = 'mp-load-css-' . md5(MEPR_VERSION) . '-' . md5(implode(',', $css_files));
            $css    = get_transient($csskey);

            if (!$css) {
                ob_start();

                foreach ($css_files as $f) {
                    if (file_exists($f)) {
                        echo file_get_contents($f);
                    }
                }

                $css = MeprUtils::compress_css(ob_get_clean());
                set_transient($csskey, $css, DAY_IN_SECONDS);
            }
        }

        exit($css);
    }

    /**
     * Append the MemberPress privacy policy to the WordPress privacy policy.
     *
     * @return void
     */
    public static function append_mp_privacy_policy()
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        ob_start();
        MeprView::render('/admin/privacy/privacy_policy', get_defined_vars());
        $privacy_policy = ob_get_clean();

        wp_add_privacy_policy_content('MemberPress', $privacy_policy);
    }

    /**
     * Integrate with the WP Debugging plugin by adding constants.
     *
     * @param array $user_defined_constants The user-defined constants.
     *
     * @return array The modified constants.
     */
    public static function integrate_wp_debugging($user_defined_constants)
    {
        if (!defined('WP_MEPR_DEBUG') || WP_MEPR_DEBUG === false) {
            // If raw is true, then value will be converted to boolean.
            $user_defined_constants['WP_MEPR_DEBUG'] = [
                'value' => 'true',
                'raw'   => true,
            ];
        }

        return $user_defined_constants;
    }

    /**
     * Render the admin support page.
     *
     * @return void
     */
    public static function render_admin_support()
    {
        MeprView::render('admin/support/view');
    }

    /**
     * Set up the MemberPress menu.
     *
     * @return void
     */
    public static function mepr_menu()
    {
        $capability = MeprUtils::get_mepr_admin_capability();
        $mbr_ctrl   = new MeprMembersCtrl();
        $txn_ctrl   = new MeprTransactionsCtrl();
        $sub_ctrl   = new MeprSubscriptionsCtrl();
        $icon_url   = file_exists(MEPR_BRAND_PATH . '/images/menu-icon.svg') ? MEPR_BRAND_URL . '/images/menu-icon.svg' : '';

        add_menu_page('MemberPress', 'MemberPress', $capability, 'memberpress', 'MeprAppCtrl::toplevel_menu_route', $icon_url, 775677);

        add_submenu_page('memberpress', __('Members', 'memberpress'), __('Members', 'memberpress'), $capability, 'memberpress-members', [$mbr_ctrl,'listing']);

        if (!get_option('mepr_disable_affiliates_menu_item')) {
            if (defined('ESAF_VERSION')) {
                add_submenu_page('memberpress', __('Affiliates', 'memberpress'), __('Affiliates', 'memberpress'), $capability, admin_url('admin.php?page=easy-affiliate'));
            } else {
                add_submenu_page('memberpress', __('Affiliates', 'memberpress'), __('Affiliates', 'memberpress'), $capability, 'memberpress-affiliates', 'MeprAddonsCtrl::affiliates');
            }
        }

        add_submenu_page('memberpress', __('Subscriptions', 'memberpress'), __('Subscriptions', 'memberpress'), $capability, 'memberpress-subscriptions', [$sub_ctrl, 'listing']);
        // Specifically for subscriptions listing.
        add_submenu_page('memberpress', __('Non-Recurring Subscriptions', 'memberpress'), __('Subscriptions', 'memberpress'), $capability, 'memberpress-lifetimes', [$sub_ctrl, 'listing']);
        add_submenu_page('memberpress', __('Transactions', 'memberpress'), __('Transactions', 'memberpress'), $capability, 'memberpress-trans', [$txn_ctrl, 'listing']);
        add_submenu_page('memberpress', __('Reports', 'memberpress'), __('Reports', 'memberpress'), $capability, 'memberpress-reports', 'MeprReportsCtrl::main');
        add_submenu_page('memberpress', __('Settings', 'memberpress'), __('Settings', 'memberpress') . MeprUtils::new_badge(), $capability, 'memberpress-options', 'MeprOptionsCtrl::route');
        add_submenu_page('memberpress', __('Onboarding', 'memberpress'), __('Onboarding', 'memberpress'), $capability, 'memberpress-onboarding', 'MeprOnboardingCtrl::route');
        add_submenu_page('memberpress', __('Account Login', 'memberpress'), __('Account Login', 'memberpress'), $capability, 'memberpress-account-login', 'MeprAccountLoginCtrl::route');
        add_submenu_page('memberpress', __('Add-ons', 'memberpress'), '<span style="color:#8CBD5A;">' . __('Add-ons', 'memberpress') . '</span>', $capability, 'memberpress-addons', 'MeprAddonsCtrl::route');

        if (!is_plugin_active('memberpress-courses/main.php')) {
            add_submenu_page('memberpress', __('MemberPress Courses', 'memberpress'), __('Courses', 'memberpress'), $capability, 'memberpress-courses', 'MeprCoursesCtrl::route');
        }

        if (!is_plugin_active('memberpress-coachkit/main.php') && class_exists('MeprCoachkitCtrl')) {
            add_submenu_page('memberpress', __('CoachKit™', 'memberpress'), __('CoachKit™', 'memberpress'), $capability, 'memberpress-coachkit', 'MeprCoachkitCtrl::route', 12);
        }

        add_submenu_page('memberpress', __('Support', 'memberpress'), __('Support', 'memberpress'), $capability, 'memberpress-support', 'MeprAppCtrl::render_admin_support');

        MeprHooks::do_action('mepr_menu');
    }

    /**
     * Set up the DRM menu for MemberPress.
     *
     * @return void
     */
    public static function mepr_drm_menu()
    {
        $capability = MeprUtils::get_mepr_admin_capability();
        $mbr_ctrl   = new MeprMembersCtrl();
        $icon_url   = file_exists(MEPR_BRAND_PATH . '/images/menu-icon.svg') ? MEPR_BRAND_URL . '/images/menu-icon.svg' : '';

        add_menu_page('MemberPress', 'MemberPress', $capability, 'memberpress-drm', 'MeprAppCtrl::toplevel_menu_drm_route', $icon_url, 775677);

        add_submenu_page('memberpress-drm', __('Members', 'memberpress'), __('Members', 'memberpress'), $capability, 'memberpress-members', [$mbr_ctrl,'listing_drm']);

        MeprHooks::do_action('mepr_drm_menu');
    }

    /**
     * Handle actions when the plugin is activated.
     *
     * @param string $plugin The plugin being activated.
     *
     * @return void
     */
    public static function activated_plugin($plugin)
    {
        if ($plugin != MEPR_PLUGIN_SLUG) {
            return;
        }

        if (!is_user_logged_in() || wp_doing_ajax() || !is_admin() || is_network_admin() || !MeprUtils::is_mepr_admin()) {
            return;
        }

        if (MeprUtils::is_post_request() && (isset($_POST['action']) || isset($_POST['action2']))) {
            return; // Don't redirect on bulk activation.
        }

        global $wpdb;

        wp_cache_flush();
        $wpdb->flush();

        $onboarded = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'mepr_onboarded'");

        if ($onboarded === null) {
            nocache_headers();
            wp_redirect(admin_url('admin.php?page=memberpress-onboarding'), 307);
            exit;
        }
    }

    /**
     * Attempt to automatically log in during signup if the username and password are correct for an existing account.
     */
    public static function maybe_auto_log_in()
    {
        if (!is_user_logged_in() && MeprHooks::apply_filters('mepr_maybe_auto_log_in', true)) {
            $mepr_options   = MeprOptions::fetch();
            $is_signup      = MeprUtils::is_post_request() && isset($_POST['mepr_process_signup_form']);
            $is_ajax_signup = wp_doing_ajax() && isset($_POST['action']) && $_POST['action'] == 'mepr_process_signup_form';
            $is_spc         = $mepr_options->enable_spc || $mepr_options->design_enable_checkout_template;

            if ($is_signup || ($is_spc && $is_ajax_signup)) {
                $email            = sanitize_email($_POST['user_email'] ?? '');
                $username         = $mepr_options->username_is_email ? $email : sanitize_user($_POST['user_login'] ?? '');
                $password         = $_POST['mepr_user_password'] ?? '';
                $password_confirm = $_POST['mepr_user_password_confirm'] ?? '';

                if ($email && $username && $password && $password_confirm && $password == $password_confirm) {
                    $user = get_user_by('email', $email);

                    if (!$user instanceof WP_User) {
                        $user = get_user_by('login', $username);
                    }

                    if ($user instanceof WP_User) {
                        try {
                            $logged_in_user = wp_signon([
                                'user_login'    => $user->user_login,
                                'user_password' => $password,
                            ]);

                            if ($logged_in_user instanceof WP_User) {
                                wp_set_current_user($logged_in_user->ID);
                            }
                        } catch (Exception $e) {
                            // Ignore.
                        }
                    }
                }
            }
        }
    }
}
