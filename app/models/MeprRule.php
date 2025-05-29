<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprRule extends MeprCptModel
{
    /**
     * Meta key for rule type.
     *
     * @var string
     */
    public static $mepr_type_str              = '_mepr_rules_type';

    /**
     * Meta key for rule content.
     *
     * @var string
     */
    public static $mepr_content_str           = '_mepr_rules_content';

    /**
     * Meta key for whether rule content is a regular expression.
     *
     * @var string
     */
    public static $is_mepr_content_regexp_str = '_is_mepr_rules_content_regexp';

    /**
     * Meta key for whether drip content is enabled.
     *
     * @var string
     */
    public static $drip_enabled_str           = '_mepr_rules_drip_enabled';

    /**
     * Meta key for drip time amount.
     *
     * @var string
     */
    public static $drip_amount_str            = '_mepr_rules_drip_amount';

    /**
     * Meta key for drip time unit (days, weeks, etc).
     *
     * @var string
     */
    public static $drip_unit_str              = '_mepr_rules_drip_unit';

    /**
     * Meta key for drip trigger event.
     *
     * @var string
     */
    public static $drip_after_str             = '_mepr_rules_drip_after';

    /**
     * Meta key for fixed date drip trigger.
     *
     * @var string
     */
    public static $drip_after_fixed_str       = '_mepr_rules_drip_after_fixed';

    /**
     * Meta key for whether content expiration is enabled.
     *
     * @var string
     */
    public static $expires_enabled_str        = '_mepr_rules_expires_enabled';

    /**
     * Meta key for expiration time amount.
     *
     * @var string
     */
    public static $expires_amount_str         = '_mepr_rules_expires_amount';

    /**
     * Meta key for expiration time unit (days, weeks, etc).
     *
     * @var string
     */
    public static $expires_unit_str           = '_mepr_rules_expires_unit';

    /**
     * Meta key for expiration trigger event.
     *
     * @var string
     */
    public static $expires_after_str          = '_mepr_rules_expires_after';

    /**
     * Meta key for fixed date expiration trigger.
     *
     * @var string
     */
    public static $expires_after_fixed_str    = '_mepr_rules_expires_after_fixed';

    /**
     * Meta key for unauthorized excerpt type.
     *
     * @var string
     */
    public static $unauth_excerpt_type_str    = '_mepr_rules_unauth_excerpt_type';

    /**
     * Meta key for unauthorized excerpt size.
     *
     * @var string
     */
    public static $unauth_excerpt_size_str    = '_mepr_rules_unauth_excerpt_size';

    /**
     * Meta key for unauthorized message type.
     *
     * @var string
     */
    public static $unauth_message_type_str    = '_mepr_rules_unauth_message_type';

    /**
     * Meta key for unauthorized message content.
     *
     * @var string
     */
    public static $unauth_message_str         = '_mepr_rules_unath_message';

    /**
     * Meta key for unauthorized login form display setting.
     *
     * @var string
     */
    public static $unauth_login_str           = '_mepr_rules_unath_login';

    /**
     * Meta key for auto-generated title setting.
     *
     * @var string
     */
    public static $auto_gen_title_str         = '_mepr_auto_gen_title';

    /**
     * Nonce name for rule forms.
     *
     * @var string
     */
    public static $mepr_nonce_str            = 'mepr_rules_nonce';

    /**
     * Option name for the last database cleanup run timestamp.
     *
     * @var string
     */
    public static $last_run_str              = 'mepr_rules_db_cleanup_last_run';

    /**
     * Meta key for modern paywall display setting.
     *
     * @var string
     */
    public static $unauth_modern_paywall_str = '_mepr_rules_unath_modern_paywall';

    /**
     * Available time units for drip and expiration settings.
     *
     * @var array
     */
    public $drip_expire_units;

    /**
     * Available options for drip and expiration triggers.
     *
     * @var array
     */
    public $drip_expire_afters;

    /**
     * Available excerpt types for unauthorized content.
     *
     * @var array
     */
    public $unauth_excerpt_types;

    /**
     * Available message types for unauthorized content.
     *
     * @var array
     */
    public $unauth_message_types;

    /**
     * Available login display options for unauthorized content.
     *
     * @var array
     */
    public $unauth_login_types;

    /**
     * Custom post type name for rules.
     *
     * @var string
     */
    public static $cpt = 'memberpressrule';

    /**
     * Cache for all rule objects.
     *
     * @var array
     */
    public static $all_rules;

    /**
     * Get the available access types for rules.
     *
     * @return array
     */
    public static function mepr_access_types()
    {
        return [
            [
                'label' => __('Membership', 'memberpress'),
                'value' => 'membership',
            ],
            [
                'label' => __('Member', 'memberpress'),
                'value' => 'member',
            ],
            [
                'label' => __('Role', 'memberpress'),
                'value' => 'role',
            ],
            [
                'label' => __('Capability', 'memberpress'),
                'value' => 'capability',
            ],
        ];
    }

    /**
     * Get the available access operators for rules.
     *
     * @return array
     */
    public static function mepr_access_operators()
    {
        return [
            [
                'label' => __('Is', 'memberpress'),
                'value' => 'is',
            ],
        ];
    }

    /**
     * Constructor for the MeprRule class.
     *
     * @param mixed $obj The object to initialize the rule with.
     */
    public function __construct($obj = null)
    {
        $this->load_cpt(
            $obj,
            self::$cpt,
            [
                'mepr_type'              => 'all',
                'mepr_content'           => '',
                'is_mepr_content_regexp' => false,
                'drip_enabled'           => false,
                'drip_amount'            => 0,
                'drip_unit'              => 'days',
                'drip_after'             => 'registers',
                'drip_after_fixed'       => '',
                'expires_enabled'        => false,
                'expires_amount'         => 0,
                'expires_unit'           => 'days',
                'expires_after'          => 'registers',
                'expires_after_fixed'    => '',
                'unauth_excerpt_type'    => 'default',
                'unauth_excerpt_size'    => 100,
                'unauth_message_type'    => 'default',
                'unauth_message'         => '',
                'unauth_login'           => 'default',
                'unauth_modern_paywall'  => false,
                'auto_gen_title'         => true,
            ]
        );

        $this->drip_expire_units    = ['days','weeks','months','years'];
        $this->drip_expire_afters   = ['registers','fixed','rule-products'];
        $this->unauth_excerpt_types = ['default','hide','more','excerpt','custom'];
        $this->unauth_message_types = ['default','hide','custom'];
        $this->unauth_login_types   = ['default','show','hide'];
    }

    /**
     * Validate the rule properties.
     *
     * @return void
     */
    public function validate()
    {
        // $this->validate_is_array($this->emails, 'emails');
        $rule_types = array_keys(MeprRule::get_types());
        $this->validate_is_in_array($this->mepr_type, $rule_types, 'mepr_type');
        $this->validate_is_bool($this->is_mepr_content_regexp, 'is_mepr_content_regexp');

        $this->validate_is_bool($this->drip_enabled, 'drip_enabled');
        $this->validate_is_numeric($this->drip_amount, 0, null, 'drip_amount');
        $this->validate_is_in_array($this->drip_unit, $this->drip_expire_units, 'drip_unit');
        $this->validate_is_in_array($this->drip_after, $this->drip_expire_afters, 'drip_after');

        if ($this->drip_after == 'fixed') {
            $this->validate_is_date($this->drip_after_fixed, 'drip_after_fixed');
        }

        $this->validate_is_bool($this->expires_enabled, 'expires_enabled');
        $this->validate_is_numeric($this->expires_amount, 0, null, 'expires_amount');
        $this->validate_is_in_array($this->expires_unit, $this->drip_expire_units, 'expires_unit');
        $this->validate_is_in_array($this->expires_after, $this->drip_expire_afters, 'expires_after');

        if ($this->expires_after == 'fixed') {
            $this->validate_is_date($this->expires_after_fixed, 'expires_after_fixed');
        }

        $this->validate_is_in_array($this->unauth_excerpt_type, $this->unauth_excerpt_types, 'unauth_excerpt_type');
        $this->validate_is_numeric($this->unauth_excerpt_size, 0, null, 'unauth_excerpt_size');
        $this->validate_is_in_array($this->unauth_message_type, $this->unauth_message_types, 'unauth_message_type');

        // $this->validate_is_bool($this->unauth_message, 'unauth_message' => '',
        $this->validate_is_in_array($this->unauth_login, $this->unauth_login_types, 'unauth_login');

        $this->validate_is_bool($this->auto_gen_title, 'auto_gen_title');
        $this->validate_is_bool($this->unauth_modern_paywall, 'unauth_modern_paywall');
    }

    /**
     * Get the available rule types.
     *
     * @return array
     */
    public static function get_types()
    {
        global $wp_taxonomies, $wp_post_types;

        $mepr_options = MeprOptions::fetch();

        static $types;

        if (!isset($types) or empty($types)) {
            $types = [
                'all'  => [],
                'post' => [
                    'all_posts'   => __('All Posts', 'memberpress'),
                    'single_post' => __('A Single Post', 'memberpress'),
                    'category'    => __('Posts Categorized', 'memberpress'),
                    'tag'         => __('Posts Tagged', 'memberpress'),
                ],
                'page' => [
                    'all_pages'   => __('All Pages', 'memberpress'),
                    'single_page' => __('A Single Page', 'memberpress'),
                    'parent_page' => __('Child Pages of', 'memberpress'),
                ],
            ];

            $cpts = get_post_types([
                'public'   => true,
                '_builtin' => false,
            ], 'objects');
            unset($cpts['memberpressproduct']);

            $cpts = MeprHooks::apply_filters('mepr-rules-cpts', $cpts);

            foreach ($cpts as $type_name => $cpt) {
                $types[$type_name] = [
                    "all_{$type_name}"    => sprintf(
                        // Translators: %1$s: custom post type name.
                        __('All %1$s', 'memberpress'),
                        $cpt->labels->name
                    ),
                    "single_{$type_name}" => sprintf(
                        // Translators: %1$s: custom post type name.
                        __('A Single %1$s', 'memberpress'),
                        $cpt->labels->singular_name
                    ),
                ];
                if ($cpt->hierarchical) {
                    $types[$type_name]["parent_{$type_name}"] = sprintf(
                        // Translators: %1$s: custom post type name.
                        __('Child %1$s of', 'memberpress'),
                        $cpt->labels->name
                    );
                }
            }

            $txs = [
                'category' => $wp_taxonomies['category'],
                'post_tag' => $wp_taxonomies['post_tag'],
            ];

            $txs = array_merge($txs, get_taxonomies([
                'public'   => true,
                '_builtin' => false,
            ], 'objects'));

            $cpts['post'] = $wp_post_types['post'];
            $cpts['page'] = $wp_post_types['page'];

            foreach ($txs as $tax_name => $tx) {
                if ($tax_name == 'post_tag') {
                    $types['all']["all_tax_{$tax_name}"] = __('All Content Tagged', 'memberpress');
                } elseif ($tax_name == 'category') {
                    $types['all']["all_tax_{$tax_name}"] = __('All Content Categorized', 'memberpress');
                } else {
                    $types['all']["all_tax_{$tax_name}"] = sprintf(
                        // Translators: %1$s: tax name.
                        __('All Content with %1$s', 'memberpress'),
                        $tx->labels->singular_name
                    );
                }

                foreach ($tx->object_type as $cpt_slug) {
                    if ($cpt_slug == 'memberpressproduct') {
                        continue;
                    }

                    // If the CPT doesn't exist, then let's ignore this rule: https://secure.helpscout.net/conversation/81248048/6880/.
                    if (!isset($cpts[$cpt_slug])) {
                        continue;
                    }

                    $cpt = $cpts[$cpt_slug];

                    if ($tax_name == 'post_tag') {
                        if ($cpt_slug != 'post') { // Already setup for post.
                            $types[$cpt_slug]["tax_{$tax_name}||cpt_{$cpt_slug}"] = sprintf(
                                // Translators: %1$s: product name.
                                __('%1$s Tagged', 'memberpress'),
                                $cpt->labels->name
                            );
                        }
                    } elseif ($tax_name == 'category') {
                        if ($cpt_slug != 'post') { // Already setup for post.
                            $types[$cpt_slug]["tax_{$tax_name}||cpt_{$cpt_slug}"] = sprintf(
                                // Translators: %1$s: product name.
                                __('%1$s Categorized', 'memberpress'),
                                $cpt->labels->name
                            );
                        }
                    } else {
                        $types[$cpt_slug]["tax_{$tax_name}||cpt_{$cpt_slug}"] = sprintf(
                            // Translators: %1$s: product name, %2$s: tax name.
                            __('%1$s with %2$s', 'memberpress'),
                            $cpt->labels->name,
                            $tx->labels->singular_name
                        );
                    }
                }
            }

            $all_types = ['all' => __('All Content', 'memberpress')];
            foreach ($types as $type_array) {
                $all_types = array_merge($all_types, $type_array);
            }

            $all_types = MeprHooks::apply_filters('mepr-rule-types-before-partial', $all_types);

            $all_types = array_merge(
                $all_types,
                [
                    'partial' => __('Partial', 'memberpress'),
                    'custom'  => __('Custom URI', 'memberpress'),
                ]
            );

            $types = $all_types;
        }

        return $types;
    }

    /**
     * Get the public post types.
     *
     * @return array
     */
    public static function public_post_types()
    {
        $types = get_post_types(['public' => true]);
        unset($types['attachment']);
        unset($types[MeprProduct::$cpt]);
        return array_values($types);
    }

    /**
     * Get the contents array for a given rule type.
     *
     * @param  string $type The rule type.
     * @return array|false
     */
    public static function get_contents_array($type)
    {
        static $contents;

        if (!isset($contents)) {
            $contents = [];
        }

        if (isset($contents[$type])) {
            return $contents[$type];
        }

        if (preg_match('#^single_(.*?)$#', $type, $matches)) {
            $contents[$type] = self::get_single_array($matches[1]);
            return $contents[$type];
        } elseif (preg_match('#^parent_(.*?)$#', $type, $matches)) {
            $contents[$type] = self::get_parent_array($matches[1]);
            return $contents[$type];
        } elseif ($type == 'category') {
            $contents[$type] = self::get_category_array();
            return $contents[$type];
        } elseif ($type == 'tag') {
            $contents[$type] = self::get_tag_array();
            return $contents[$type];
        } elseif (preg_match('#^tax_(.*?)\|\|cpt_(.*?)$#', $type, $matches) || preg_match('#^all_tax_(.*?)$#', $type, $matches)) {
            $contents[$type] = self::get_tax_array($matches[1]);
            return $contents[$type];
        } elseif ($type == 'partial' || $type == 'custom') {
            $contents[$type] = false;
        }

        return MeprHooks::apply_filters('mepr-rule-contents-array', $contents, $type);
    }

    /**
     * Search for content based on rule type and search term.
     *
     * @param  string $type   The rule type.
     * @param  string $search The search term.
     * @return array|false
     */
    public static function search_content($type, $search = '')
    {
        if (preg_match('#^single_(.*?)$#', $type, $matches)) {
            return self::search_singles($matches[1], $search);
        } elseif (preg_match('#^parent_(.*?)$#', $type, $matches)) {
            return self::search_parents($matches[1], $search);
        } elseif ($type == 'category') {
            return self::search_categories($search);
        } elseif ($type == 'tag') {
            return self::search_tags($search);
        } elseif (
            preg_match('#^tax_(.*?)\|\|cpt_(.*?)$#', $type, $matches) or
            preg_match('#^all_tax_(.*?)$#', $type, $matches)
        ) {
            return self::search_taxs($matches[1], $search);
        }

        return MeprHooks::apply_filters('mepr-rule-search-content', false, $type, $search);
    }

    /**
     * Get content based on rule type and ID.
     *
     * @param  string  $type The rule type.
     * @param  integer $id   The content ID.
     * @return mixed
     */
    public static function get_content($type, $id)
    {
        if (preg_match('#^single_(.*?)$#', $type, $matches)) {
            return self::get_single($matches[1], $id);
        } elseif (preg_match('#^parent_(.*?)$#', $type, $matches)) {
            return self::get_parent($matches[1], $id);
        } elseif ($type == 'category') {
            return self::get_category($id);
        } elseif ($type == 'tag') {
            return self::get_tag($id);
        } elseif (
            preg_match('#^tax_(.*?)\|\|cpt_(.*?)$#', $type, $matches) or
            preg_match('#^all_tax_(.*?)$#', $type, $matches)
        ) {
            return self::get_tax($matches[1], $id);
        }

        return MeprHooks::apply_filters('mepr-rule-content', false, $type, $id);
    }

    /**
     * Check if a rule type has contents.
     *
     * @param  string $type The rule type.
     * @return boolean
     */
    public static function type_has_contents($type)
    {
        if (preg_match('#^single_(.*?)$#', $type, $matches)) {
            return self::singles_have_contents($matches[1]);
        } elseif (preg_match('#^parent_(.*?)$#', $type, $matches)) {
            return self::parents_have_contents($matches[1]);
        } elseif ($type == 'category') {
            return self::categories_have_contents();
        } elseif ($type == 'tag') {
            return self::tags_have_contents();
        } elseif (
            preg_match('#^tax_(.*?)\|\|cpt_(.*?)$#', $type, $matches) or
            preg_match('#^all_tax_(.*?)$#', $type, $matches)
        ) {
            return self::taxs_have_contents($matches[1]);
        }

        return MeprHooks::apply_filters('mepr-rule-has-content', false, $type);
    }

    /**
     * Check if singles of a given type have contents.
     *
     * @param  string $type The post type.
     * @return boolean
     */
    public static function singles_have_contents($type)
    {
        $counts = wp_count_posts($type);

        if (isset($counts->future) && (int)$counts->future > 0) {
            return ( ((int)$counts->future + (int)$counts->publish) > 0 );
        } else {
            return ( (int)$counts->publish > 0 );
        }
    }

    /**
     * Get an array of single posts for a given type.
     *
     * @param  string $type The post type.
     * @return array
     */
    public static function get_single_array($type)
    {
        global $wpdb;

        $lookup = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = '{$type}'", OBJECT_K);

        if (empty($lookup)) {
            return [];
        }

        foreach ($lookup as $id => $obj) {
            $lookup[$id] = stripslashes($obj->post_title);
        }

        return $lookup;
    }

    /**
     * Search for single posts based on type and search term.
     *
     * @param  string  $type   The post type.
     * @param  string  $search The search term.
     * @param  integer $limit  The maximum number of results to return.
     * @return array
     */
    public static function search_singles($type, $search = '', $limit = 25)
    {
        global $wpdb;
        $query = 'SELECT p.ID AS id, p.post_title AS label ' .
               "FROM {$wpdb->posts} AS p " .
              'WHERE p.post_type=%s ' .
                'AND (p.post_status=%s || p.post_status=%s) ';

        if (!empty($search)) {
            $query .= 'AND ( p.ID LIKE %s OR p.post_title LIKE %s ) ';
            $query .= "LIMIT {$limit}";
            $query  = $wpdb->prepare($query, $type, 'publish', 'future', "%{$search}%", "%{$search}%");
        } else {
            $query .= "LIMIT {$limit}";
            $query  = $wpdb->prepare($query, $type, 'publish', 'future');
        }

        $query = MeprHooks::apply_filters('mepr-search-singles-query', $query, $type, $search);

        return array_map(
            function ($i) {
                $i->slug = preg_replace('!' . preg_quote(home_url(), '!') . '!', '', MeprUtils::get_permalink($i->id));
                $i->desc = "ID: {$i->id} | Slug: {$i->slug}";
                return $i;
            },
            $wpdb->get_results($query)
        );
    }

    /**
     * Get a single post based on type and ID.
     *
     * @param  string  $type The post type.
     * @param  integer $id   The post ID.
     * @return object|false
     */
    public static function get_single($type, $id)
    {
        global $wpdb;
        $query = 'SELECT p.ID AS id, p.post_title AS label ' .
               "FROM {$wpdb->posts} AS p " .
              'WHERE p.post_type=%s ' .
                'AND (p.post_status=%s || p.post_status=%s) ' .
                'AND p.ID=%d ' .
              'LIMIT 1';

        $query = $wpdb->prepare($query, $type, 'publish', 'future', $id);

        $i = $wpdb->get_row($query);
        if ($i == false) {
            return false;
        }

        $i->slug = preg_replace('!' . preg_quote(home_url(), '!') . '!', '', MeprUtils::get_permalink($i->id));
        $i->desc = "ID: {$i->id} | Slug: {$i->slug}";

        return $i;
    }

    /**
     * Get the total number of published rules.
     *
     * @return integer
     */
    public static function count()
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            self::$cpt
        );

        return (int) $wpdb->get_var($query);
    }

    /**
     * Check if parents of a given type have contents.
     *
     * @param  string $type The post type.
     * @return boolean
     */
    public static function parents_have_contents($type = 'page')
    {
        return self::singles_have_contents($type);
    }

    /**
     * Get an array of parent posts for a given type.
     *
     * @param  string $type The post type.
     * @return array
     */
    public static function get_parent_array($type = 'page')
    {
        return self::get_single_array($type);
    }

    /**
     * Search for parent posts based on type and search term.
     *
     * @param  string  $type   The post type.
     * @param  string  $search The search term.
     * @param  integer $limit  The maximum number of results to return.
     * @return array
     */
    public static function search_parents($type = 'page', $search = '', $limit = 25)
    {
        return self::search_singles($type, $search, $limit);
    }

    /**
     * Get a parent post based on type and ID.
     *
     * @param  string  $type The post type.
     * @param  integer $id   The post ID.
     * @return object|false
     */
    public static function get_parent($type, $id)
    {
        return self::get_single($type, $id);
    }

    /**
     * Check if categories have contents.
     *
     * @return boolean
     */
    public static function categories_have_contents()
    {
        return ( wp_count_terms('category', ['hide_empty' => 0]) > 0 );
    }

    /**
     * Get an array of categories.
     *
     * @return array
     */
    public static function get_category_array()
    {
        $category_contents = get_categories(['hide_empty' => 0]);
        $contents          = [];

        foreach ($category_contents as $category) {
            $contents[$category->term_id] = $category->name;
        }

        return $contents;
    }

    /**
     * Search for terms based on taxonomy and search term.
     *
     * @param  string  $tax    The taxonomy.
     * @param  string  $search The search term.
     * @param  integer $limit  The maximum number of results to return.
     * @return array
     */
    public static function search_terms($tax, $search = '', $limit = 25)
    {
        global $wpdb;
        $query = 'SELECT t.term_id AS id, t.name AS label, t.slug AS slug ' .
               "FROM {$wpdb->terms} AS t " .
               "JOIN {$wpdb->term_taxonomy} AS tx " .
                 'ON t.term_id=tx.term_id ' .
                'AND tx.taxonomy=%s ';

        if (!empty($search)) {
            $query .= 'WHERE ( t.term_id LIKE %s OR t.name LIKE %s OR t.slug LIKE %s OR tx.description LIKE %s ) ';
            $query .= "LIMIT {$limit}";
            $s      = "%{$search}%";
            $query  = $wpdb->prepare($query, $tax, $s, $s, $s, $s);
        } else {
            $query .= "LIMIT {$limit}";
            $query  = $wpdb->prepare($query, $tax);
        }

        return array_map(
            function ($i) {
                $i->desc = "ID: {$i->id} | Slug: {$i->slug}";
                return $i;
            },
            $wpdb->get_results($query)
        );
    }

    /**
     * Get a term based on taxonomy and ID.
     *
     * @param  string  $tax The taxonomy.
     * @param  integer $id  The term ID.
     * @return object|false
     */
    public static function get_term($tax, $id)
    {
        global $wpdb;
        $query = 'SELECT t.term_id AS id, t.name AS label, t.slug AS slug ' .
               "FROM {$wpdb->terms} AS t " .
               "JOIN {$wpdb->term_taxonomy} AS tx " .
                 'ON t.term_id=tx.term_id ' .
                'AND tx.taxonomy=%s ' .
              'WHERE t.term_id=%d ' .
              'LIMIT 1';

        $query = $wpdb->prepare($query, $tax, $id);
        $i     = $wpdb->get_row($query);
        if ($i == false) {
            return false;
        }

        $i->desc = "ID: {$i->id} | Slug: {$i->slug}";

        return $i;
    }

    /**
     * Search for categories based on search term.
     *
     * @param  string  $search The search term.
     * @param  integer $limit  The maximum number of results to return.
     * @return array
     */
    public static function search_categories($search, $limit = 25)
    {
        return self::search_terms('category', $search, $limit);
    }

    /**
     * Get a category based on ID.
     *
     * @param  integer $id The category ID.
     * @return object|false
     */
    public static function get_category($id)
    {
        return self::get_term('category', $id);
    }

    /**
     * Check if tags have contents.
     *
     * @return boolean
     */
    public static function tags_have_contents()
    {
        return ( wp_count_terms('post_tag', ['get' => 'all']) > 0 );
    }

    /**
     * Get an array of tags.
     *
     * @return array
     */
    public static function get_tag_array()
    {
        $tag_contents = get_tags(['get' => 'all']);
        $contents     = [];

        foreach ($tag_contents as $tag) {
            $contents[$tag->term_id] = $tag->name;
        }

        return $contents;
    }

    /**
     * Search for tags based on search term.
     *
     * @param  string  $search The search term.
     * @param  integer $limit  The maximum number of results to return.
     * @return array
     */
    public static function search_tags($search, $limit = 25)
    {
        return self::search_terms('post_tag', $search, $limit);
    }

    /**
     * Get a tag based on ID.
     *
     * @param  integer $id The tag ID.
     * @return object|false
     */
    public static function get_tag($id)
    {
        return self::get_term('post_tag', $id);
    }

    /**
     * Check if taxonomies have contents.
     *
     * @param  string $tax The taxonomy.
     * @return boolean
     */
    public static function taxs_have_contents($tax)
    {
        return ( wp_count_terms($tax, ['get' => 'all']) > 0 );
    }

    /**
     * Get an array of terms for a given taxonomy.
     *
     * @param  string $tax The taxonomy.
     * @return array
     */
    public static function get_tax_array($tax)
    {
        $contents     = [];
        $tax_contents = get_terms($tax, ['get' => 'all']);

        if (!is_wp_error($tax_contents)) {
            foreach ($tax_contents as $tk => $t) {
                $contents[$t->term_id] = $t->name;
            }
        }

        return $contents;
    }

    /**
     * Search for terms in a taxonomy based on search term.
     *
     * @param  string  $tax    The taxonomy.
     * @param  string  $search The search term.
     * @param  integer $limit  The maximum number of results to return.
     * @return array
     */
    public static function search_taxs($tax, $search, $limit = 25)
    {
        return self::search_terms($tax, $search, $limit);
    }

    /**
     * Get a term in a taxonomy based on ID.
     *
     * @param  string  $tax The taxonomy.
     * @param  integer $id  The term ID.
     * @return object|false
     */
    public static function get_tax($tax, $id)
    {
        return self::get_term($tax, $id);
    }

    /**
     * Check if a post is an exception to a rule.
     * We just assume this will only be called on posts that are the correct type
     *
     * @param  WP_Post  $post       The post object.
     * @param  MeprRule $rule       The rule object.
     * @param  array    $exceptions The list of exceptions.
     * @return boolean
     */
    public static function is_exception_to_rule($post, $rule, $exceptions = [])
    {
        $rule_exceptions = explode(',', preg_replace('#\s#', '', $rule->mepr_content));
        $exceptions      = array_unique(array_merge($rule_exceptions, $exceptions));
        return in_array($post->ID, $exceptions);
    }

    // Make sure that we don't lock down the unauthorized URL if redirect on unauthorized is selected
    // public static function is_unauthorized_url() {
    // global $post;
    // $mepr_options = MeprOptions::fetch();
    // if($mepr_options->redirect_on_unauthorized) {
      // $current_url = MeprUtils::get_permalink($post->ID);
      // if(stristr($current_url, $mepr_options->unauthorized_redirect_url) !== false) {
        // return true;
      // }
    // }
    // return false;
    // }
    // TODO: Create a convenience function calling this in MeprProduct once it's in place.

    /**
     * Get the rules for a given context.
     *
     * @param  mixed $context The context to get rules for.
     * @return array
     */
    public static function get_rules($context)
    {
        $post_rules = [];

        if (!isset(self::$all_rules)) {
            $all_rule_posts = MeprCptModel::all('MeprRule');

            self::$all_rules = [];

            foreach ($all_rule_posts as $curr_post) {
                if ($curr_post->post_type == self::$cpt) {
                    self::$all_rules[] = new MeprRule($curr_post->ID);
                }
            }
        }

        foreach (self::$all_rules as $curr_rule) {
            if (isset($curr_rule->ID)) { // Occassionally some how this loop ends up with nulled out rules which causes issues. This check will prevent that from happening.
                if (is_a($context, 'WP_Post') && $curr_rule->mepr_type != 'custom') {
                    if ($curr_rule->mepr_type == 'all') {
                        // We're going to add this rule immediately if it's set to all and it's not an exception.
                        if (!self::is_exception_to_rule($context, $curr_rule)) {
                            $post_rules[] = $curr_rule;
                        }
                    } elseif (preg_match('#^all_tax_(.*?)$#', $curr_rule->mepr_type, $matches)) {
                        if (has_term($curr_rule->mepr_content, $matches[1], $context->ID)) {
                            $post_rules[] = $curr_rule;
                        }
                    } elseif (preg_match('#^all_(.*?)$#', $curr_rule->mepr_type, $matches)) {
                        if (
                            preg_match('#^' . preg_quote($context->post_type) . 's?$#', $matches[1]) &&
                            !self::is_exception_to_rule($context, $curr_rule)
                        ) {
                            $post_rules[] = $curr_rule;
                        }
                    } elseif (preg_match('#^single_(.*?)$#', $curr_rule->mepr_type, $matches)) {
                        if (
                            $context->post_type == $matches[1] &&
                            $context->ID == $curr_rule->mepr_content
                        ) {
                                      $post_rules[] = $curr_rule;
                        }
                    } elseif (preg_match('#^parent_(.*?)$#', $curr_rule->mepr_type, $matches)) {
                        if (
                            $context->post_type == $matches[1]

                            /*
                                && $context->post_parent == $curr_rule->mepr_content
                            */
                        ) {
                                          $ancestors = get_post_ancestors($context->ID);

                                          // Let's protect all lineage of the parent page.
                            if (in_array($curr_rule->mepr_content, $ancestors, false)) {
                                $post_rules[] = $curr_rule;
                            }
                        }
                    } elseif ($curr_rule->mepr_type == 'category') {
                        if (in_category($curr_rule->mepr_content, $context->ID)) {
                                        $post_rules[] = $curr_rule;
                        }
                    } elseif ($curr_rule->mepr_type == 'tag') {
                        if (has_tag($curr_rule->mepr_content, $context->ID)) {
                                        $post_rules[] = $curr_rule;
                        }
                    } elseif (preg_match('#^tax_(.*?)\|\|cpt_(.*?)$#', $curr_rule->mepr_type, $matches)) {
                        if (
                            $context->post_type == $matches[2] &&
                            has_term($curr_rule->mepr_content, $matches[1], $context->ID)
                        ) {
                                            $post_rules[] = $curr_rule;
                        }
                    }

                    $post_rules = MeprHooks::apply_filters('mepr-extend-post-rules', $post_rules, $curr_rule, $context);
                } elseif ($curr_rule->mepr_type == 'custom' && is_string($context)) {
                    $uri = empty($context) ? esc_url($_SERVER['REQUEST_URI']) : $context;
                    $uri = html_entity_decode($uri); // Needed to decode &#038; and other html entities.

                    if (
                        ($curr_rule->is_mepr_content_regexp && preg_match('~' . $curr_rule->mepr_content . '~i', $uri)) ||
                        (!$curr_rule->is_mepr_content_regexp && strpos($uri, $curr_rule->mepr_content) === 0)
                    ) {
                        $post_rules[] = $curr_rule;
                    }
                }
                $post_rules = MeprHooks::apply_filters('mepr-extend-rules', $post_rules, $curr_rule, $context);
            }
        }//end foreach

        return $post_rules;
    }

    /**
     * Get the access list for a post.
     * TODO: Move to MeprProduct once it's in place
     *
     * @param  WP_Post $post The post object.
     * @return array
     */
    public static function get_access_list($post) // Tested.
    {
        $access_array = [];
        $rules        = MeprRule::get_rules($post);

        foreach ($rules as $rule) {
            foreach ($rule->access_conditions() as $condition) {
                if (!isset($access_array[$condition->access_type])) {
                    $access_array[$condition->access_type] = [];
                }
                // Make sure they're unique.
                if (!in_array($condition->access_condition, $access_array[$condition->access_type])) {
                    array_push($access_array[$condition->access_type], $condition->access_condition);
                }
            }
        }

        return $access_array;
    }

    /**
     * Check if content is locked for a user.
     *
     * @param  WP_User $user    The user object.
     * @param  mixed   $context The context to check.
     * @return boolean
     */
    public static function is_locked_for_user($user, $context)
    {
        // The content is not locked regardless of whether or not
        // a user is logged in so let's just return here okay?
        $rules = MeprRule::get_rules($context);
        if (empty($rules)) {
            return false;
        }
        if (!isset($user->ID) || $user->ID == 0) {
            return true;
        }

        foreach ($rules as $rule) {
            if ($user->has_access_from_rule($rule->ID)) {
                if ($rule->has_dripped($user->ID)) {
                    if (!$rule->has_expired($user->ID)) {
                        return MeprHooks::apply_filters('mepr-content-locked-for-user', false, $rule, $context, $rules);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Check if a URI is locked.
     *
     * @param  string $uri The URI to check.
     * @return boolean
     */
    public static function is_uri_locked($uri)
    {
        $mepr_options = MeprOptions::fetch();
        $current_post = MeprUtils::get_current_post();

        static $is_locked;
        $md5_uri = (string)md5($uri); // Used as the key for the $is_locked array.

        if (!isset($is_locked) || !is_array($is_locked)) {
            $is_locked = [];
        }

        if (isset($is_locked) && !empty($is_locked) && isset($is_locked[$md5_uri]) && is_bool($is_locked[$md5_uri])) {
            return $is_locked[$md5_uri];
        }

        if (isset($_GET['action']) && $_GET['action'] == 'mepr_unauthorized' && $current_post !== false && $current_post->ID == $mepr_options->login_page_id) {
            $is_locked[$md5_uri] = false;
            return $is_locked[$md5_uri]; // Don't override the login page content duh!
        }

        if (MeprUtils::is_logged_in_and_an_admin()) {
            $is_locked[$md5_uri] = false;
            return $is_locked[$md5_uri]; // If user is an admin, let's not go on.
        }

        $rules = MeprRule::get_rules($uri);

        // The content is not locked regardless of whether or not
        // a user is logged in so let's just return here okay?
        if (empty($rules)) {
            $is_locked[$md5_uri] = false;
            return $is_locked[$md5_uri];
        }

        if (MeprUtils::is_user_logged_in()) {
            $user                = MeprUtils::get_currentuserinfo();
            $is_locked[$md5_uri] = self::is_locked_for_user($user, $uri);

            MeprHooks::do_action('mepr-user-unauthorized'); // This one will be called for all events where the user is blocked by a rule.
            MeprHooks::do_action('mepr-member-unauthorized', $user); // More specific (member means logged in user).
            MeprHooks::do_action('mepr-member-unauthorized-for-uri', $user, $uri); // Further specific-ness - yes I can make up words! (member means logged in user).

            return $is_locked[$md5_uri];
        } else {
            $is_locked[$md5_uri] = true;

            MeprHooks::do_action('mepr-user-unauthorized'); // This one will be called for all events where the user is blocked by a rule.
            MeprHooks::do_action('mepr-guest-unauthorized-for-uri', $uri); // Further specific-ness - yes I can make up words! (guest means NOT logged in user).

            return $is_locked[$md5_uri]; // If there are rules on this content and the user isn't logged in -- it's locked.
        }
    }

    /**
     * Check if a post is locked.
     * Move to MeprProduct once it's in place
     *
     * @param  WP_Post $post The post object.
     * @return boolean
     */
    public static function is_locked($post)
    {
        // Tested.
        $mepr_options = MeprOptions::fetch();
        static $is_locked;

        if (!isset($is_locked) || !is_array($is_locked)) {
            $is_locked = [];
        }

        if (isset($is_locked) && !empty($is_locked) && isset($is_locked[$post->ID]) && is_bool($is_locked[$post->ID])) {
            return $is_locked[$post->ID];
        }

        // If user is an admin, let's not go on.
        if (MeprUtils::is_logged_in_and_an_admin()) {
            $is_locked[$post->ID] = false;
            return $is_locked[$post->ID];
        }

        // Can't rule the login page lest we end up in an infinite loop.
        if ($post->ID == $mepr_options->login_page_id) {
            $is_locked[$post->ID] = false;
            return $is_locked[$post->ID];
        }

        $rules = MeprRule::get_rules($post);

        // The content is not locked regardless of wether or not
        // a user is logged in so let's just return here okay?
        if (empty($rules)) {
            $is_locked[$post->ID] = false;
            return $is_locked[$post->ID];
        }

        if (MeprUtils::is_user_logged_in()) {
            $user                 = MeprUtils::get_currentuserinfo();
            $is_locked[$post->ID] = self::is_locked_for_user($user, $post);

            MeprHooks::do_action('mepr-user-unauthorized'); // This one will be called for all events where the user is blocked by a rule.
            MeprHooks::do_action('mepr-member-unauthorized', $user); // More specific (member means logged in user).
            MeprHooks::do_action('mepr-member-unauthorized-for-content', $user, $post); // Further specific-ness - yes I can make up words! (member means logged in user).

            return $is_locked[$post->ID];
        } else {
            $is_locked[$post->ID] = true;

            MeprHooks::do_action('mepr-user-unauthorized'); // This one will be called for all events where the user is blocked by a rule.
            MeprHooks::do_action('mepr-guest-unauthorized-for-content', $post); // Further specific-ness - yes I can make up words! (guest means NOT logged in user).

            return $is_locked[$post->ID]; // If there are rules on this content and the user isn't logged in -- it's locked.
        }
    }

    /**
     * Get the custom unauthorized message from rule IDs.
     *
     * @param  array $rule_ids The rule IDs.
     * @return string
     */
    public static function get_custom_unauth_message_from_rule_ids($rule_ids)
    {
        $mepr_options   = MeprOptions::fetch();
        $unauth_message = '<div class="mepr_error">' . wpautop(do_shortcode($mepr_options->unauthorized_message)) . '</div>';

        if (empty($rule_ids)) {
            return $unauth_message;
        }

        foreach ($rule_ids as $rule_id) {
            $rule = new MeprRule($rule_id);

            // If more than one rules has a custom unauthorized message, the last will win here.
            if ($rule->unauth_message_type == 'custom' && !empty($rule->unauth_message)) {
                $unauth_message = '<div class="mepr_error">' . wpautop(do_shortcode($rule->unauth_message)) . '</div>';
            }
        }

        return $unauth_message;
    }

    /**
     * Check if a rule has dripped for a user.
     *
     * @param  integer|false $user_id The user ID.
     * @return boolean
     */
    public function has_dripped($user_id = false)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // If the drip is disabled, then let's kill this thing.
        if (!$this->drip_enabled) {
            return true;
        }

        if ($this->drip_after == 'registers') {
            $registered_ts = MeprUtils::db_date_to_ts(MeprUser::get_user_registration_date($user_id));

            return $this->has_time_passed($registered_ts, $this->drip_unit, $this->drip_amount);
        }

        if ($this->drip_after == 'fixed' && !empty($this->drip_after_fixed)) {
            $fixed_ts          = strtotime($this->drip_after_fixed);
            $has_dripped_fixed = $this->has_time_passed($fixed_ts, $this->drip_unit, $this->drip_amount, true);

            return MeprHooks::apply_filters('mepr-rule-has-dripped-fixed', $has_dripped_fixed, $this);
        }

        // Any product associated with this rule.
        if ($this->drip_after == 'rule-products') {
            $products = [];

            $mepr_access = $this->mepr_access;
            foreach ($mepr_access['membership'] as $prod_id) {
                $products[] = new MeprProduct($prod_id);
            }
        } else {
            $products = [new MeprProduct($this->drip_after)];
        }

        foreach ($products as $product) {
            // If the product doesn't exist, then let's ignore this rule.
            if (!isset($product->ID) || (int)$product->ID <= 0) {
                continue;
            }

            // Not logged in.
            if (!$user_id) {
                continue;
            }

            $purchased_ts = MeprUtils::db_date_to_ts(MeprUser::get_user_product_signup_date($user_id, $product->ID));

            // User hasn't purchased this membership.
            if (!$purchased_ts) {
                continue;
            }

            if ($this->has_time_passed($purchased_ts, $this->drip_unit, $this->drip_amount)) {
                return true;
            }
        }

        return false; // If we made it here the user doens't have access.
    }

    /**
     * Get the access conditions for a rule.
     *
     * @param  string $mgm The method to call.
     * @param  string $val The value to pass to the method.
     * @return array
     */
    public function mgm_mepr_access($mgm, $val = '')
    {
        $access_array = [];

        switch ($mgm) {
            case 'get':
                foreach ($this->access_conditions() as $condition) {
                    if (!isset($access_array[$condition->access_type])) {
                        $access_array[$condition->access_type] = [];
                    }
                    array_push($access_array[$condition->access_type], $condition->access_condition);
                }

                return $access_array;
            default:
                return [];
        }
    }

    /**
     * Check if a rule has expired for a user.
     *
     * @param  integer|false $user_id The user ID.
     * @return boolean
     */
    public function has_expired($user_id = false)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // If the expiration is disabled, then let's kill this thing.
        if (!$this->expires_enabled) {
            return false;
        }

        if ($this->expires_after == 'registers') {
            $registered_ts = MeprUtils::db_date_to_ts(MeprUser::get_user_registration_date($user_id));

            return $this->has_time_passed($registered_ts, $this->expires_unit, $this->expires_amount);
        }

        if ($this->expires_after == 'fixed' && !empty($this->expires_after_fixed)) {
            $fixed_ts = strtotime($this->expires_after_fixed);

            return $this->has_time_passed($fixed_ts, $this->expires_unit, $this->expires_amount, true);
        }

        // Any product associated with this rule.
        if ($this->expires_after == 'rule-products') {
            $products = [];

            $mepr_access = $this->mepr_access;
            foreach ($mepr_access['membership'] as $prod_id) {
                $products[] = new MeprProduct($prod_id);
            }
        } else {
            $products = [new MeprProduct($this->expires_after)];
        }

        foreach ($products as $product) {
            // If the expiration is disabled, then let's kill this thing.
            if (!isset($product->ID) || (int)$product->ID <= 0) {
                continue;
            }

            if (!$user_id) {
                continue;
            }

            $purchased_ts = MeprUtils::db_date_to_ts(MeprUser::get_user_product_signup_date($user_id, $product->ID));

            // User hasn't purchased this.
            if (!$purchased_ts) {
                continue;
            }

            if (!$this->has_time_passed($purchased_ts, $this->expires_unit, $this->expires_amount)) {
                return false;
            }
        }

        // If we made it here the user doesn't have access.
        return true;
    }

    /**
     * Check if a time has passed.
     * Should probably put this in Utils at some point
     *
     * @param  integer $ts       The timestamp.
     * @param  string  $unit     The unit of time.
     * @param  integer $amount   The amount of time.
     * @param  boolean $is_fixed Whether the time is fixed.
     * @return boolean
     */
    public function has_time_passed($ts, $unit, $amount, $is_fixed = false)
    {
        // Convert $ts to the start of the day, so drips/expirations don't come in at odd hours throughout the day.
        if (!$is_fixed) {
            // $datetime = gmdate('Y-m-d 00:00:01', $ts);
            // $ts = strtotime($datetime);
            $datetime = gmdate('Y-m-d H:i:s', $ts);
            $datetime = get_date_from_gmt($datetime, 'Y-m-d 00:00:01'); // Convert to local WP timezone.
            $ts       = (int) get_gmt_from_date($datetime, 'U'); // Now back to a unix timestamp.
        }

        switch ($unit) {
            case 'days':
                $days_ts = MeprUtils::days($amount);
                if ((time() - $ts) > $days_ts) {
                    return true;
                }
                break;
            case 'weeks':
                $weeks_ts = MeprUtils::weeks($amount);
                if ((time() - $ts) > $weeks_ts) {
                    return true;
                }
                break;
            case 'months':
                $months_ts = MeprUtils::months($amount, $ts);
                if ((time() - $ts) > $months_ts) {
                    return true;
                }
                break;
            case 'years':
                $years_ts = MeprUtils::years($amount, $ts);
                if ((time() - $ts) > $years_ts) {
                    return true;
                }
                break;
        }

        return false;
    }

    /**
     * Get the formatted accesses for a rule.
     *
     * @return array
     */
    public function get_formatted_accesses()
    {
        $formatted_array = [];

        foreach ($this->mepr_access as $access_key => $access_values) {
            if ($access_key == 'membership') {
                foreach ($access_values as $access) {
                    $product = get_post($access);
                    if ($product) {
                        $formatted_array[] = $product->post_title;
                    }
                }
            } else {
                $formatted_array = array_merge($formatted_array, $access_values);
            }
        }

        return $formatted_array;
    }

    /**
     * Get access conditions for a rule.
     *
     * @return array
     */
    public function access_conditions()
    {
        $mepr_db = new MeprDb();

        return $mepr_db->get_records($mepr_db->rule_access_conditions, ['rule_id' => $this->ID]);
    }

    /**
     * Delete access conditions for a rule.
     *
     * @return boolean
     */
    public function delete_access_conditions()
    {
        $mepr_db = new MeprDb();

        return $mepr_db->delete_records($mepr_db->rule_access_conditions, ['rule_id' => $this->ID]);
    }

    /**
     * Get the available time units.
     *
     * @return array
     */
    public static function get_time_units()
    {
        return [
            __('day(s)', 'memberpress')   => 'days',
            __('week(s)', 'memberpress')  => 'weeks',
            __('month(s)', 'memberpress') => 'months',
            __('year(s)', 'memberpress')  => 'years',
        ];
    }

    /**
     * Get the expiration description for a rule.
     *
     * @param  string $type       The expiration type.
     * @param  string $fixed_date The fixed date.
     * @return string
     */
    public static function get_expires_after($type, $fixed_date = null)
    {
        switch ($type) {
            case 'registers':
                return __('member registers', 'memberpress');
            break;
            case 'fixed':
                return MeprAppHelper::format_date($fixed_date);
            break;
            case 'rule-products':
                return __('member purchases any membership for this rule', 'memberpress');
            break;
            default:
                $product = new MeprProduct($type);
                return sprintf(
                    // Translators: %s: product name.
                    __('member purchases %s', 'memberpress'),
                    $product->post_title
                );
            break;
        }
    }

    /**
     * Store the rule metadata.
     *
     * @return void
     */
    public function store_meta()
    {
        update_post_meta($this->ID, self::$mepr_type_str, $this->mepr_type);
        update_post_meta($this->ID, self::$mepr_content_str, $this->mepr_content);
        update_post_meta($this->ID, self::$is_mepr_content_regexp_str, $this->is_mepr_content_regexp);

        update_post_meta($this->ID, self::$drip_enabled_str, $this->drip_enabled);
        update_post_meta($this->ID, self::$drip_amount_str, $this->drip_amount);
        update_post_meta($this->ID, self::$drip_unit_str, $this->drip_unit);
        update_post_meta($this->ID, self::$drip_after_fixed_str, $this->drip_after_fixed);
        update_post_meta($this->ID, self::$drip_after_str, $this->drip_after);
        update_post_meta($this->ID, self::$expires_enabled_str, $this->expires_enabled);
        update_post_meta($this->ID, self::$expires_amount_str, $this->expires_amount);
        update_post_meta($this->ID, self::$expires_unit_str, $this->expires_unit);
        update_post_meta($this->ID, self::$expires_after_str, $this->expires_after);
        update_post_meta($this->ID, self::$expires_after_fixed_str, $this->expires_after_fixed);
        update_post_meta($this->ID, self::$unauth_excerpt_type_str, $this->unauth_excerpt_type);
        update_post_meta($this->ID, self::$unauth_excerpt_size_str, $this->unauth_excerpt_size);
        update_post_meta($this->ID, self::$unauth_message_type_str, $this->unauth_message_type);
        update_post_meta($this->ID, self::$unauth_message_str, $this->unauth_message);
        update_post_meta($this->ID, self::$unauth_login_str, $this->unauth_login);
        update_post_meta($this->ID, self::$unauth_modern_paywall_str, $this->unauth_modern_paywall);
        update_post_meta($this->ID, self::$auto_gen_title_str, $this->auto_gen_title);
    }

    /**
     * Clean up the database by removing unused drafts.
     *
     * @return void
     */
    public static function cleanup_db() // DontTest.
    {
        global $wpdb;

        $date     = time();
        $last_run = get_option(self::$last_run_str, 0); // Prevents all this code from executing on every page load.

        if (($date - $last_run) > 86400) {
            update_option(self::$last_run_str, $date);
            $sq1     = "SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = '" . self::$cpt . "' AND
                      post_status = 'auto-draft'";
            $sq1_res = $wpdb->get_col($sq1);
            if (!empty($sq1_res)) {
                $post_ids = implode(',', $sq1_res);

                $q1 = "DELETE
                  FROM {$wpdb->postmeta}
                  WHERE post_id IN ({$post_ids})";

                $q2 = "DELETE
                  FROM {$wpdb->posts}
                  WHERE post_type = '" . self::$cpt . "' AND
                        post_status = 'auto-draft'";

                $wpdb->query($q1);
                $wpdb->query($q2);
            }
        }
    }

    /**
     * Get the directory where rule files will be stored.
     * This returns the directory where rule files will be stored
     * for use with the rewrite (via .htaccess) system.
     *
     * @param  boolean $escape Whether to escape the directory path.
     * @return string
     */
    public static function rewrite_rule_file_dir($escape = false)
    {
        $rule_file_path_array = wp_upload_dir();
        $rule_file_path       = $rule_file_path_array['basedir'];
        $rule_file_dir        = "{$rule_file_path}/mepr/rules";

        if (!is_dir($rule_file_dir)) { // Make sure it exists.
            @mkdir($rule_file_dir, 0777, true);
        }

        // Use forward slashes (works in windows too).
        $rule_file_dir = str_replace('\\', '/', $rule_file_dir);

        if ($escape) {
            $rule_file_dir = str_replace([' ', '(', ')'], ['\ ', '\(', '\)'], $rule_file_dir);
        }

        return $rule_file_dir;
    }

    // THESE TWO FUNCTIONS SHOULD PROBABLY BE DEPRECATED AT SOME POINT
    // IN FAVOR OF THE current_user_can('memberpress-authorized') SYSTEM BLAIR PUT IN PLACE INSTEAD
    // PHP Snippet wrapper (returns opposite of is_protected_by_rule().

    /**
     * Check if a user is allowed by a rule.
     *
     * @param  integer $rule_id The rule ID.
     * @return boolean
     */
    public static function is_allowed_by_rule($rule_id)
    {
        return !(self::is_protected_by_rule($rule_id));
    }

    /**
     * Check if a rule is protected by a rule.
     *
     * @param  integer $rule_id The rule ID.
     * @return boolean
     */
    public static function is_protected_by_rule($rule_id)
    {
        $current_post = MeprUtils::get_current_post();

        // Check if we've been given sanitary input, if not this snippet
        // is no good so let's return false here.
        if (!is_numeric($rule_id) || (int)$rule_id <= 0) {
            return false;
        }

        // Check if user is logged in.
        if (!MeprUtils::is_user_logged_in()) {
            return true;
        }

        // No sense loading the rule until we know the user is logged in.
        $rule = new MeprRule($rule_id);

        // If rule doesn't exist, has no memberships associated with it, or
        // we're an Admin let's return the full content.
        if (!isset($rule->ID) || (int)$rule->ID <= 0 || MeprUtils::is_mepr_admin()) {
            return false;
        }

        // Make sure this page/post/cpt is not in the "except" list of an all_* Rule
        // TODO -- really need to take the "except" list into consideration here using $current_post if it's set
        // Now we know the user is logged in and the rule is valid
        // let's see if they have access through memberships or members rule conditions.
        $user = MeprUtils::get_currentuserinfo();
        return (false === $user->has_access_from_rule($rule->ID));
    }

    /**
     * Get the global unauthorized settings.
     *
     * @return object
     */
    public static function get_global_unauth_settings()
    {
        $mepr_options = MeprOptions::fetch();

        return (object)[
            'excerpt_type'   => ($mepr_options->unauth_show_excerpts ? $mepr_options->unauth_excerpt_type : 'hide'),
            'excerpt_size'   => $mepr_options->unauth_excerpt_size,
            'excerpt'        => '',
            'message_type'   => 'custom',
            'message'        => $mepr_options->unauthorized_message,
            'unauth_login'   => $mepr_options->unauth_show_login,
            'show_login'     => ($mepr_options->unauth_show_login == 'show'),
            'modern_paywall' => false,
        ];
    }

    /**
     * Get the unauthorized settings for a post.
     *
     * @param  WP_Post $post The post object.
     * @return object
     */
    public static function get_post_unauth_settings($post)
    {
        // Get values.
        $unauth_message_type = get_post_meta($post->ID, '_mepr_unauthorized_message_type', true);
        $unauth_message      = get_post_meta($post->ID, '_mepr_unauthorized_message', true);
        $unauth_login        = get_post_meta($post->ID, '_mepr_unauth_login', true);
        $unauth_excerpt_type = get_post_meta($post->ID, '_mepr_unauth_excerpt_type', true);
        $unauth_excerpt_size = get_post_meta($post->ID, '_mepr_unauth_excerpt_size', true);

        // Get defaults.
        $unauth_message_type = (($unauth_message_type != '') ? $unauth_message_type : 'default');
        $unauth_message      = (($unauth_message != '') ? $unauth_message : '');
        $unauth_login        = (($unauth_login != '') ? $unauth_login : 'default');
        $unauth_excerpt_type = (($unauth_excerpt_type != '') ? $unauth_excerpt_type : 'default');
        $unauth_excerpt_size = (($unauth_excerpt_size != '') ? $unauth_excerpt_size : 100);

        return (object)compact(
            'unauth_message_type',
            'unauth_message',
            'unauth_login',
            'unauth_excerpt_type',
            'unauth_excerpt_size'
        );
    }

    /**
     * Get the unauthorized settings for a post based on rules.
     *
     * @param  WP_Post $post The post object.
     * @return object
     */
    public static function get_unauth_settings_for($post)
    {
        $mepr_options = MeprOptions::fetch();

        $unauth          = (object)[];
        $global_settings = self::get_global_unauth_settings();
        $post_settings   = self::get_post_unauth_settings($post);

        $rules = MeprRule::get_rules($post);

        // Don't allow these settings to work on the account page without a Rule being attached to it
        // If we're gonna return global settings, let's make sure they're all there and that they match what would've been returned in by the $unauth object below. Should probably fix $post_settings to use the same var names as well, but since we don't return $post_settings ever, we should be ok for now.
        if (empty($rules) && $post->ID != $mepr_options->account_page_id) {
            return $global_settings;
        }

        // TODO: Make this a bit more sophisticated? For now just pick the first rule.
        if (isset($rules[0])) {
            $rule = $rules[0];
        } else {
            $rule = new MeprRule();
        }

        // - Excerpts
        if ($post_settings->unauth_excerpt_type != 'default') {
            $unauth->excerpt_type = $post_settings->unauth_excerpt_type;
            $unauth->excerpt_size = (int) $post_settings->unauth_excerpt_size;
        } elseif ($rule->unauth_excerpt_type != 'default') {
            $unauth->excerpt_type = $rule->unauth_excerpt_type;
            $unauth->excerpt_size = (int) $rule->unauth_excerpt_size;
        } else {
            $unauth->excerpt_type = $global_settings->excerpt_type;
            $unauth->excerpt_size = (int) $global_settings->excerpt_size;
        }

        // Set the actual Excerpt based on the type & size.
        if ($unauth->excerpt_type == 'custom') {
            // Let's avoid recursion here people.
            if (MeprUser::manually_place_account_form($post)) {
                $content = preg_replace('/\[mepr[-_]account[-_]form\]/', '', $post->post_content);
            } else {
                $content = $post->post_content;
            }

            $unauth->excerpt = MeprUtils::format_content($content);

            if ($unauth->excerpt_size) { // If set to 0, return the whole post -- though why protect it all in this case?
                $unauth->excerpt = strip_tags($unauth->excerpt);
                // Mbstring?
                $unauth->excerpt = (extension_loaded('mbstring')) ? mb_substr($unauth->excerpt, 0, $unauth->excerpt_size) : substr($unauth->excerpt, 0, $unauth->excerpt_size);
                // Re-add <p>'s back in below to preserve some formatting at least.
                $unauth->excerpt = wpautop($unauth->excerpt . '...');
            }
        } elseif ($unauth->excerpt_type == 'more') { // Show till the more tag.
            $pos = (extension_loaded('mbstring')) ? mb_strpos($post->post_content, '<!--more') : strpos($post->post_content, '<!--more');

            if ($pos !== false) {
                // Mbstring library loaded?
                if (extension_loaded('mbstring')) {
                    $unauth->excerpt = force_balance_tags(mb_substr($post->post_content, 0, $pos));
                } else {
                    $unauth->excerpt = force_balance_tags(substr($post->post_content, 0, $pos));
                }

                $unauth->excerpt = MeprUtils::format_content($unauth->excerpt);
            } else { // No more tag?
                $unauth->excerpt = MeprUtils::format_content($post->post_excerpt);
            }
        } elseif ($unauth->excerpt_type == 'excerpt') {
            global $wp_filter;
            $content_filters          = $wp_filter['the_content'];
            $wp_filter['the_content'] = (class_exists('WP_Hook')) ? new WP_Hook() : []; // WP 4.7 adds WP_Hook class.

            $unauth->excerpt = wpautop(get_the_excerpt($post)); // This calls the_content so we need to remove the filters to prevent eternal loops.

            $wp_filter['the_content'] = $content_filters; // Restore the filters again.
        } else {
            $unauth->excerpt = '';
        }

        // Autoembed any videos in the excerpt.
        if (class_exists('WP_Embed')) {
            $embed           = new WP_Embed();
            $unauth->excerpt = $embed->autoembed($unauth->excerpt);
        }

        // - Messages
        if ($post_settings->unauth_message_type != 'default') {
            $unauth->message_type = $post_settings->unauth_message_type;
            $unauth->message      = $post_settings->unauth_message;
        } elseif ($rule->unauth_message_type != 'default') {
            $unauth->message_type = $rule->unauth_message_type;
            $unauth->message      = $rule->unauth_message;
        } else {
            $unauth->message_type = $global_settings->message_type;
            $unauth->message      = $global_settings->message;
        }

        if ($unauth->message_type == 'hide') {
            $unauth->message = ''; // Reset the message if it's not shown.
        } else {
            $unauth->message = wpautop($unauth->message);
        }

        // - Login Form
        if ($post_settings->unauth_login != 'default') {
            $unauth->show_login = ($post_settings->unauth_login == 'show');
        } elseif ($rule->unauth_login != 'default') {
            $unauth->show_login = ($rule->unauth_login == 'show');
        } else {
            $unauth->show_login = ($global_settings->unauth_login == 'show');
        }

        $unauth->excerpt        = MeprHooks::apply_filters('mepr-unauthorized-excerpt', $unauth->excerpt, $post, $unauth);
        $unauth->message        = MeprHooks::apply_filters('mepr-unauthorized-message', $unauth->message, $post, $unauth);
        $unauth->show_login     = MeprHooks::apply_filters('mepr-unauthorized-show-login', $unauth->show_login, $post, $unauth);
        $unauth->modern_paywall = MeprHooks::apply_filters('mepr-unauthorized-modern-paywall', (bool) $rule->unauth_modern_paywall, $post, $unauth, $rule);

        return $unauth;
    }

    /**
     * Get the matched content for a rule.
     *
     * @param  boolean $count  Whether to count the matched content.
     * @param  string  $type   The type of content to return.
     * @param  string  $order  The order of the content.
     * @param  string  $fields The fields to select.
     * @return mixed
     */
    public function get_matched_content($count = false, $type = 'objects', $order = 'p.post_date', $fields = 'p.*')
    {
        global $wpdb;

        if ($count) {
            $fields = 'COUNT(*)';
        } elseif ($type == 'ids') {
            $fields = 'p.ID';
        }

        if ($this->mepr_type != 'custom') {
            if ($this->mepr_type == 'all') {
                $query = "SELECT {$fields} FROM {$wpdb->posts} AS p " .
                  "WHERE (p.post_status='publish' || p.post_status='future')";

                if (!empty($this->mepr_content)) {
                    $query .= ' AND p.ID NOT IN (' . preg_replace('/ /', '', $this->mepr_content) . ')';
                }
            } elseif (preg_match('#^all_tax_(.*?)$#', $this->mepr_type, $matches)) {
                // Custom Taxonomies.
                $query = $wpdb->prepare(
                    "SELECT {$fields} FROM {$wpdb->posts} AS p " .
                                  "INNER JOIN {$wpdb->terms} AS t " .
                                     'ON t.slug=%s ' .
                                  "INNER JOIN {$wpdb->term_taxonomy} AS x " .
                                     'ON x.term_id=t.term_id ' .
                                    'AND x.taxonomy=%s ' .
                                  "INNER JOIN {$wpdb->term_relationships} AS r " .
                                     'ON r.object_id=p.ID ' .
                                    'AND r.term_taxonomy_id=x.term_taxonomy_id ' .
                                  "WHERE (p.post_status='publish'|| p.post_status='future')",
                    $this->mepr_content,
                    $matches[1]
                );
            } elseif (preg_match('#^all_(.*?)$#', $this->mepr_type, $matches)) {
                // Custom Post Types.
                $query = $wpdb->prepare(
                    "SELECT {$fields} FROM {$wpdb->posts} AS p " .
                                  "WHERE (p.post_status='publish' || p.post_status='future') " .
                                    'AND p.post_type=%s',
                    $matches[1]
                );

                if (!empty($this->mepr_content)) {
                    $query .= ' AND p.ID NOT IN (' . preg_replace('/ /', '', $this->mepr_content) . ')';
                }
            } elseif (preg_match('#^single_(.*?)$#', $this->mepr_type, $matches)) {
                // Custom Post Type.
                $query = $wpdb->prepare(
                    "SELECT {$fields} FROM {$wpdb->posts} AS p " .
                                  "WHERE (p.post_status='publish' || p.post_status='future') " .
                                    'AND p.ID=%d ' .
                                    'AND p.post_type=%s',
                    $this->mepr_content,
                    $matches[1]
                );
            } elseif (preg_match('#^parent_(.*?)$#', $this->mepr_type, $matches)) {
                // Custom Post Type.
                $query = $wpdb->prepare(
                    "SELECT {$fields} FROM {$wpdb->posts} AS p " .
                                  "WHERE (p.post_status='publish' || p.post_status='future') " .
                                    'AND p.post_parent=%s ' .
                                    'AND p.post_type=%s',
                    $this->mepr_content,
                    $matches[1]
                );
            } elseif ($this->mepr_type == 'category') {
                // Posts Categorized.
                $query = $wpdb->prepare(
                    "SELECT {$fields} FROM {$wpdb->posts} AS p " .
                                  "INNER JOIN {$wpdb->terms} AS t " .
                                     'ON t.slug=%s ' .
                                  "INNER JOIN {$wpdb->term_taxonomy} AS x " .
                                     'ON x.term_id=t.term_id ' .
                                    "AND x.taxonomy='category' " .
                                  "INNER JOIN {$wpdb->term_relationships} AS r " .
                                     'ON r.object_id=p.ID ' .
                                    'AND r.term_taxonomy_id=x.term_taxonomy_id ' .
                                  "WHERE (p.post_status='publish' || p.post_status='future') " .
                                    "AND p.post_type='post'",
                    $this->mepr_content
                );
            } elseif ($this->mepr_type == 'tag') {
                // Posts Tagged.
                $query = $wpdb->prepare(
                    "SELECT {$fields} FROM {$wpdb->posts} AS p " .
                                  "INNER JOIN {$wpdb->terms} AS t " .
                                     'ON t.slug=%s ' .
                                  "INNER JOIN {$wpdb->term_taxonomy} AS x " .
                                     'ON x.term_id=t.term_id ' .
                                    "AND x.taxonomy='post_tag' " .
                                  "INNER JOIN {$wpdb->term_relationships} AS r " .
                                     'ON r.object_id=p.ID ' .
                                    'AND r.term_taxonomy_id=x.term_taxonomy_id ' .
                                  "WHERE (p.post_status='publish' || p.post_status='future') " .
                                    "AND p.post_type='post'",
                    $this->mepr_content
                );
            } elseif (preg_match('#^tax_(.*?)\|\|cpt_(.*?)$#', $this->mepr_type, $matches)) {
                // Custom Taxonomies and Post Types.
                $query = $wpdb->prepare(
                    "SELECT {$fields} FROM {$wpdb->posts} AS p " .
                                  "INNER JOIN {$wpdb->terms} AS t " .
                                     'ON t.slug=%s ' .
                                  "INNER JOIN {$wpdb->term_taxonomy} AS x " .
                                     'ON x.term_id=t.term_id ' .
                                    'AND x.taxonomy=%s ' .
                                  "INNER JOIN {$wpdb->term_relationships} AS r " .
                                     'ON r.object_id=p.ID ' .
                                    'AND r.term_taxonomy_id=x.term_taxonomy_id ' .
                                  "WHERE (p.post_status='publish' || p.post_status='future') " .
                                    'AND p.post_type=%s',
                    $this->mepr_content,
                    $matches[1],
                    $matches[2]
                );
            }
        }

        if (!$count and !empty($order)) {
            $query .= " ORDER BY {$order}";
        }

        if ($type == 'sql') {
            return $query;
        } elseif ($count) {
            return $wpdb->get_var($query);
        } elseif ($type == 'ids') {
            return $wpdb->get_col($query);
        } else {
            return $wpdb->get_results($query);
        }
    }
}
