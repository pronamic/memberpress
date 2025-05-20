<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprGroup extends MeprCptModel
{
    /**
     * Meta key for pricing page disabled setting.
     *
     * @var string
     */
    public static $pricing_page_disabled_str         = '_mepr_group_pricing_page_disabled';

    /**
     * Meta key for disabling change plan popup.
     *
     * @var string
     */
    public static $disable_change_plan_popup_str     = '_mepr_group_disable_change_plan_popup';

    /**
     * Meta key for upgrade path setting.
     *
     * @var string
     */
    public static $is_upgrade_path_str               = '_mepr_group_is_upgrade_path';

    /**
     * Meta key for upgrade path reset period.
     *
     * @var string
     */
    public static $upgrade_path_reset_period_str     = '_mepr_group_upgrade_path_reset_period';

    /**
     * Meta key for group theme.
     *
     * @var string
     */
    public static $group_theme_str                   = '_mepr_group_theme';

    /**
     * Meta key for page button class.
     *
     * @var string
     */
    public static $page_button_class_str             = '_mepr_page_button_class';

    /**
     * Meta key for highlighted button class.
     *
     * @var string
     */
    public static $page_button_highlighted_class_str = '_mepr_page_button_highlighted_class';

    /**
     * Meta key for disabled button class.
     *
     * @var string
     */
    public static $page_button_disabled_class_str    = '_mepr_page_button_disabled_class';

    /**
     * Meta key for products.
     *
     * @var string
     */
    public static $products_str                      = '_mepr_products';

    /**
     * Meta key for group page style options.
     *
     * @var string
     */
    public static $group_page_style_options_str      = '_mepr_group_page_style_options';

    /**
     * Option name for group page layout.
     *
     * @var string
     */
    public static $group_page_layout_str             = 'mepr-group-page-layout';

    /**
     * Option name for group page style.
     *
     * @var string
     */
    public static $group_page_style_str              = 'mepr-group-page-style';

    /**
     * Option name for group page button size.
     *
     * @var string
     */
    public static $group_page_button_size_str        = 'mepr-group-page-button-size';

    /**
     * Option name for group page bullet style.
     *
     * @var string
     */
    public static $group_page_bullet_style_str       = 'mepr-group-page-bullet-style';

    /**
     * Option name for group page font style.
     *
     * @var string
     */
    public static $group_page_font_style_str         = 'mepr-group-page-font-style';

    /**
     * Option name for group page font size.
     *
     * @var string
     */
    public static $group_page_font_size_str          = 'mepr-group-page-font-size';

    /**
     * Option name for group page button color.
     *
     * @var string
     */
    public static $group_page_button_color_str       = 'mepr-group-page-button-color';

    /**
     * Meta key for alternate group URL.
     *
     * @var string
     */
    public static $alternate_group_url_str           = '_mepr-alternate-group-url';

    /**
     * Meta key for using custom template.
     *
     * @var string
     */
    public static $use_custom_template_str           = '_mepr_use_custom_template';

    /**
     * Meta key for custom template.
     *
     * @var string
     */
    public static $custom_template_str               = '_mepr_custom_template';

    /**
     * Meta key for fallback membership.
     *
     * @var string
     */
    public static $fallback_membership_str           = '_mepr_fallback_membership';

    /**
     * Nonce string for group operations.
     *
     * @var string
     */
    public static $nonce_str    = 'mepr_groups_nonce';

    /**
     * Option name for database cleanup last run timestamp.
     *
     * @var string
     */
    public static $last_run_str = 'mepr_groups_db_cleanup_last_run';

    /**
     * Custom post type slug for groups.
     *
     * @var string
     */
    public static $cpt = 'memberpressgroup';

    /**
     * Default style options for the group.
     *
     * @var array
     */
    public $default_style_options;

    /**
     * Constructor for the MeprGroup class.
     *
     * @param mixed $obj Optional. The object to initialize the group with.
     */
    public function __construct($obj = null)
    {
        $this->default_style_options = [
            'layout'       => 'mepr-vertical',
            'style'        => 'mepr-gray',
            'button_size'  => 'mepr-medium',
            'bullet_style' => 'mepr-circles',
            'font_style'   => 'custom',
            'font_size'    => 'custom',
            'button_color' => 'mepr-button-gray',
        ];

        $this->load_cpt(
            $obj,
            self::$cpt,
            [
                'pricing_page_disabled'         => false,
                'disable_change_plan_popup'     => false,
                'is_upgrade_path'               => false,
                'upgrade_path_reset_period'     => false,
                'group_theme'                   => 'minimal_gray_horizontal.css',
                'fallback_membership'           => '',
                'page_button_class'             => '',
                'page_button_highlighted_class' => '',
                'page_button_disabled_class'    => '',
                'alternate_group_url'           => '',
                'group_page_style_options'      => $this->default_style_options,
                'use_custom_template'           => false,
                'custom_template'               => '',
            ]
        );

        // Ensure defaults get folded in.
        $this->group_page_style_options = array_merge(
            $this->default_style_options,
            $this->group_page_style_options
        );
    }

    /**
     * Validate the group's properties.
     *
     * @return void
     */
    public function validate()
    {
        $this->validate_is_bool($this->pricing_page_disabled, 'pricing_page_disabled');
        $this->validate_is_bool($this->disable_change_plan_popup, 'disable_change_plan_popup');

        $this->validate_is_bool($this->is_upgrade_path, 'is_upgrade_path');
        $this->validate_is_bool($this->upgrade_path_reset_period, 'upgrade_path_reset_period');

        $this->validate_is_in_array(
            $this->group_theme,
            self::group_themes(false, true),
            'group_theme'
        );

        if (!empty($this->alternate_group_url)) {
            $this->validate_is_url($this->alternate_group_url);
        }

        $this->validate_is_array($this->default_style_options);

        $this->validate_is_bool($this->use_custom_template, 'use_custom_template');

        if ($this->use_custom_template) {
            $this->validate_not_empty($this->custom_template);
        }

        // No need to validate these at this point
        // 'page_button_class' => '',
        // 'page_button_highlighted_class' => '',
        // 'page_button_disabled_class' => '',.
    }

    /**
     * Store the group's metadata in the database.
     *
     * @return void
     */
    public function store_meta()
    {
        $id = $this->ID;

        update_post_meta($id, self::$pricing_page_disabled_str, $this->pricing_page_disabled);
        update_post_meta($id, self::$disable_change_plan_popup_str, $this->disable_change_plan_popup);
        update_post_meta($id, self::$is_upgrade_path_str, $this->is_upgrade_path);
        update_post_meta($id, self::$upgrade_path_reset_period_str, $this->upgrade_path_reset_period);
        update_post_meta($id, self::$group_theme_str, $this->group_theme);
        update_post_meta($id, self::$fallback_membership_str, $this->fallback_membership);
        update_post_meta($id, self::$page_button_class_str, $this->page_button_class);
        update_post_meta($id, self::$page_button_highlighted_class_str, $this->page_button_highlighted_class);
        update_post_meta($id, self::$page_button_disabled_class_str, $this->page_button_disabled_class);
        update_post_meta($id, self::$group_page_style_options_str, $this->group_page_style_options);
        update_post_meta($id, self::$alternate_group_url_str, $this->alternate_group_url);
        update_post_meta($id, self::$use_custom_template_str, $this->use_custom_template);
        update_post_meta($id, self::$custom_template_str, $this->custom_template);

        if ($this->is_upgrade_path) {
            $products = $this->products();

            foreach ($products as $product) {
                if ((bool)$product->simultaneous_subscriptions) {
                    $product->simultaneous_subscriptions = false;
                    $product->save();
                }
            }
        }
    }

    // $return_type should be a string containing 'objects', 'ids', or 'titles'

    /**
     * Retrieves the products associated with the group.
     *
     * @param  string $return_type The type of return value ('objects', 'ids', or 'titles').
     * @return MeprProduct[] The products associated with the group.
     */
    public function products($return_type = 'objects')
    {
        global $wpdb;

        $query = "
      SELECT ID FROM {$wpdb->posts} AS p
        JOIN {$wpdb->postmeta} AS pm_group_id
          ON p.ID = pm_group_id.post_id
         AND pm_group_id.meta_key = %s
         AND pm_group_id.meta_value = %s
        JOIN {$wpdb->postmeta} AS pm_group_order
          ON p.ID = pm_group_order.post_id
         AND pm_group_order.meta_key = %s
       WHERE p.post_status = %s
       ORDER BY pm_group_order.meta_value * 1
    "; // * 1 = easy way to cast strings as numbers in SQL

        $query = $wpdb->prepare($query, MeprProduct::$group_id_str, $this->ID, MeprProduct::$group_order_str, 'publish');

        $res = $wpdb->get_col($query);

        $products = [];

        if (is_array($res)) {
            foreach ($res as $product_id) {
                $prd = new MeprProduct($product_id);

                if ($return_type == 'objects') {
                    $products[] = $prd;
                } elseif ($return_type == 'ids') {
                    $products[] = $prd->ID;
                } elseif ($return_type == 'titles') {
                    $products[] = $prd->post_title;
                }
            }
        }

        return $products;
    }

    /**
     * Returns products that can be bought
     *
     * @return array MeprProduct[]
     */
    public function buyable_products()
    {
        global $wpdb;
        $products = array_filter($this->products(), function ($p) {
            return $p->can_you_buy_me();
        });

        return (array) $products;
    }

    /**
     * Returns the product associated through fallback group.
     *
     * @return MeprProduct|false The fallback membership product or false if not found.
     */
    public function fallback_membership()
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "
      SELECT meta_value
      FROM {$wpdb->postmeta}
      WHERE post_id = %d
        AND meta_key = %s
      ",
            $this->ID,
            $this::$fallback_membership_str
        );

        $result = $wpdb->get_var($query);

        if ($result) {
            $product_id = (int)$result;
            return new MeprProduct($product_id);
        } else {
            return false;
        }
    }

    /**
     * Gets the transaction related to a lifetime membership in a group.
     * For use during upgrades from lifetime to subscriptions.
     *
     * @param integer $new_prd_id The ID of the new product.
     * @param integer $user_id    The ID of the user.
     *
     * @return MeprTransaction|false The transaction object or false if not found.
     */
    public function get_old_lifetime_txn($new_prd_id, $user_id)
    {
        $txn_id   = false;
        $grp_prds = $this->products('ids');
        $usr_txns = MeprTransaction::get_all_by_user_id($user_id, '', '', true);

        // Try and find the old txn and make sure it's not one belonging
        // to the membership the user just signed up for.
        foreach ($usr_txns as $txn) {
            if (in_array($txn->product_id, $grp_prds) && $txn->product_id != $new_prd_id) {
                $txn_id = $txn->id;
            }
        }

        if ($txn_id) {
            return new MeprTransaction($txn_id);
        } else {
            return false;
        }
    }

    /**
     * Clean up the database by removing auto-draft posts and their metadata.
     *
     * @return void
     */
    public static function cleanup_db()
    {
        global $wpdb;
        $date     = time();
        $last_run = get_option(self::$last_run_str, 0); // Prevents all this code from executing on every page load.

        if (($date - $last_run) > 86400) { // Runs at most once a day.
            update_option(self::$last_run_str, $date);
            $sq1     = "SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = '" . self::$cpt . "' AND
                      post_status = 'auto-draft'";
            $sq1_res = $wpdb->get_col($sq1);
            if (!empty($sq1_res)) {
                $post_ids = implode(',', $sq1_res);
                $q1       = "DELETE
                  FROM {$wpdb->postmeta}
                  WHERE post_id IN ({$post_ids})";
                $q2       = "DELETE
                  FROM {$wpdb->posts}
                  WHERE post_type = '" . self::$cpt . "' AND
                        post_status = 'auto-draft'";

                $wpdb->query($q1);
                $wpdb->query($q2);
            }
        }
    }

    /**
     * Get the page template for the group.
     *
     * @return string|null The path to the custom template or null if not used.
     */
    public function get_page_template()
    {
        if ($this->use_custom_template) {
            return locate_template($this->custom_template);
        }

        return null;
    }

    /*
     * Defines the template hierarchy search path for member core groups.
     * Currently unused method that would specify template file lookup order.
     *
        public static function template_search_path() {
            return array(
                'page_memberpressgroup.php',
                'single-memberpressgroup.php',
                'page.php',
                'custom_template.php',
                'index.php'
            );
        }
     */
    /**
     * Checks if price boxes should be manually appended.
     *
     * @return boolean True if price boxes should be manually appended, false otherwise.
     */
    public function manual_append_price_boxes()
    {
        return preg_match('~\[mepr-group-price-boxes~', $this->post_content);
    }

    /**
     * Determine if a post is a group page.
     *
     * @param WP_Post $post The post object to check.
     *
     * @return MeprGroup|false The group object if the post is a group page, false otherwise.
     */
    public static function is_group_page($post)
    {
        if (is_object($post)) {
            if (property_exists($post, 'post_type') && $post->post_type == MeprGroup::$cpt) {
                $grp = new MeprGroup($post->ID);
                return $grp;
            }

            if (preg_match('~\[mepr-group-price-boxes\s+group_id=[\"\\\'](\d+)[\"\\\']~', $post->post_content, $m) && isset($m[1])) {
                $grp = new MeprGroup($m[1]);
                return $grp;
            }
        }

        return false;
    }

    /**
     * Get the template for the group.
     *
     * @return string The template string for the group.
     */
    public function group_template()
    {
        if (
            $this->group_theme != 'custom'
        ) {
            $filename = self::find_group_theme($this->group_theme);
            if (false !== $filename) {
                $template_str = file_get_contents($filename);
                preg_match('~MP PLAN TEMPLATE:\s+(\S+)~', $template_str, $m);

                if (isset($m[1])) {
                    return $m[1];
                }
            }
        }


        return '';
    }

    /**
     * Get the paths to the group theme templates.
     *
     * @return array The paths to the group theme templates.
     */
    public static function group_theme_templates_paths()
    {
        return MeprHooks::apply_filters('mepr_group_theme_templates_paths', [MEPR_CSS_PATH . '/plan_templates']);
    }

    /**
     * Get the available group theme templates.
     *
     * @param boolean $full_paths Optional. Whether to return full paths. Default false.
     *
     * @return array The available group theme templates.
     */
    public static function group_theme_templates($full_paths = false)
    {
        $paths = self::group_theme_templates_paths();

        $templates = [];
        foreach ($paths as $path) {
            $templates = array_merge($templates, @glob("{$path}/*.css"));
        }

        if (!$full_paths) {
            // TODO: This could cause issues down the line because we're counting on the theme
            // base name being unique across all search paths for the group theme files.
            foreach ($templates as $i => $template) {
                $templates[$i] = basename($template);
            }
        }

        return $templates;
    }

    /**
     * Get the paths to the group themes.
     *
     * @return array The paths to the group themes.
     */
    public static function group_themes_paths()
    {
        return MeprHooks::apply_filters('mepr_group_themes_paths', [MEPR_CSS_PATH . '/plans']);
    }

    /**
     * Find the path to a specific group theme.
     *
     * @param string $theme The name of the theme to find.
     *
     * @return string|false The path to the theme or false if not found.
     */
    public static function find_group_theme($theme)
    {
        $paths = self::group_themes_paths();
        foreach ($paths as $path) {
            $filepath = $path . '/' . $theme;
            if (file_exists($filepath)) {
                return $filepath;
            }
        }
        return false;
    }

    /**
     * Get the available group themes.
     *
     * @param boolean $full_paths     Optional. Whether to return full paths. Default false.
     * @param boolean $include_custom Optional. Whether to include custom themes. Default false.
     *
     * @return array The available group themes.
     */
    public static function group_themes($full_paths = false, $include_custom = false)
    {
        $paths = self::group_themes_paths();

        $themes = [];
        foreach ($paths as $path) {
            $themes = array_merge($themes, @glob("{$path}/*.css"));
        }

        if (!$full_paths) {
            // TODO: This could cause issues down the line because we're counting on the theme
            // base name being unique across all search paths for the group theme files.
            foreach ($themes as $i => $theme) {
                $themes[$i] = basename($theme);
            }
        }

        if ($include_custom) {
            $themes[] = 'custom';
        }

        return $themes;
    }
}
