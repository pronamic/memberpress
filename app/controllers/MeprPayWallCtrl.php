<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Controlls search engine access to protected content and PayWall related stuff
 */
class MeprPayWallCtrl extends MeprBaseCtrl
{
    public static $cookie_name = 'mp3pi141592pw'; // CDN's and caching plugins/varnish etc should NOT cache any pages where this cookie is set

    /**
     * Load the hooks.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_filter('mepr-pre-run-rule-content', 'MeprPayWallCtrl::allow_search_engines_content', 15, 3);
        add_filter('mepr-pre-run-rule-redirection', 'MeprPayWallCtrl::allow_search_engines_redirection', 15, 3);
        add_action('wp_head', 'MeprPayWallCtrl::add_noarchive_to_wp_head');

        // Same hooks, different purpose and priority
        add_filter('mepr-pre-run-rule-content', 'MeprPayWallCtrl::paywall_allow_through_content', 10, 3);
        add_filter('mepr-pre-run-rule-redirection', 'MeprPayWallCtrl::paywall_allow_through_redirection', 10, 3);
        add_action('template_redirect', 'MeprPayWallCtrl::paywall_update_cookie');
    }

    /**
     * Do not allow certain posts to be freely viewed.
     *
     * @param  integer $post The post.
     * @return boolean
     */
    public static function is_excluded($post)
    {
        $excluded                = false;
        $excluded_category_slugs = MeprHooks::apply_filters('mepr-paywall-excluded-category-slugs', [], $post);
        $excluded_tag_slugs      = MeprHooks::apply_filters('mepr-paywall-excluded-tag-slugs', [], $post);
        $excluded_wp_posts       = MeprHooks::apply_filters('mepr-paywall-excluded-posts', [], $post);

        if (!empty($excluded_category_slugs) && in_category($excluded_category_slugs, $post)) {
            $excluded = true;
        }

        if (!empty($excluded_tag_slugs) && has_tag($excluded_tag_slugs, $post)) {
            $excluded = true;
        }

        if (!empty($excluded_wp_posts) && in_array($post->ID, $excluded_wp_posts)) {
            $excluded = true;
        }

        $excluded = MeprHooks::apply_filters('mepr-paywall-is-excluded', $excluded, $post);

        return $excluded;
    }

    /**
     * Tell search engines NOT to cache this page.
     *
     * @return void
     */
    public static function add_noarchive_to_wp_head()
    {
        $post         = MeprUtils::get_current_post();
        $mepr_options = MeprOptions::fetch();
        $uri          = $_SERVER['REQUEST_URI'];

        // Check the URI and post first to see if this is even a locked page
        // TODO:
        // Ugh should probably check for non-singular page types and make sure
        // none of them are protected here as well eventually
        if (!MeprRule::is_uri_locked($uri) && ($post === false || !MeprRule::is_locked($post))) {
            return;
        }

        if ($mepr_options->authorize_seo_views && self::verify_bot()) :
            ?>
        <!-- Added by MemberPress to prevent bots from caching protected pages -->
        <meta name="robots" content="noarchive" />
            <?php
        endif;

        if (get_option('blog_public') && !$mepr_options->authorize_seo_views && $mepr_options->seo_unauthorized_noindex) :
            ?>
        <!-- Added by MemberPress to prevent bots from indexing protected pages -->
        <meta name="robots" content="noindex,follow" />
            <?php
        endif;
    }

    /**
     * Update the cookie.
     *
     * @return void
     */
    public static function paywall_update_cookie()
    {
        $post = MeprUtils::get_current_post();

        // Do nothing if the member is logged in or this is excluded from the PayWall or this is an archive view
        if (MeprUtils::is_user_logged_in() || ($post !== false && self::is_excluded($post)) || !is_singular()) {
            return;
        }

        // If no post, or post is not protected let's bail
        if ($post === false || !MeprRule::is_locked($post)) {
            return;
        }

        $mepr_options = MeprOptions::fetch();

        if ($mepr_options->paywall_enabled && $mepr_options->paywall_num_free_views > 0) {
            $num_views = (isset($_COOKIE[self::$cookie_name]) && !empty($_COOKIE[self::$cookie_name])) ? $_COOKIE[self::$cookie_name] : 0;

            if ($num_views !== 0) {
                $num_views = base64_decode($num_views);
            }

            $cookie_time = MeprHooks::apply_filters('mepr-paywall-cookie-time', (time() + 60 * 60 * 24 * 30), $num_views);
            ;

            setcookie(self::$cookie_name, base64_encode(($num_views + 1)), $cookie_time, '/');
            $_COOKIE[self::$cookie_name] = base64_encode(($num_views + 1)); // Update the COOKIE global too for use later downstream
        }
    }

    /**
     * Allow through content.
     *
     * @param  boolean $protect The protect.
     * @param  integer $post    The post.
     * @param  string  $uri     The uri.
     * @return boolean
     */
    public static function paywall_allow_through_content($protect, $post, $uri)
    {
        if (self::paywall_allow_through()) {
            return false; // Need to return false to allow them through the blocks
        }

        return $protect;
    }

    /**
     * Allow through redirection.
     *
     * @param  boolean $protect The protect.
     * @param  string  $uri     The uri.
     * @param  string  $delim   The delim.
     * @return boolean
     */
    public static function paywall_allow_through_redirection($protect, $uri, $delim)
    {
        if (self::paywall_allow_through('uri')) {
            return false; // Need to return false to allow them through the blocks
        }

        return $protect;
    }

    /**
     * Allow through.
     *
     * @param  string $type The type.
     * @return boolean
     */
    public static function paywall_allow_through($type = 'content')
    {
        $post = MeprUtils::get_current_post();

        // Do nothing if the member is logged in, or if this is a bot (bots might be allowed through later down the chain)
        if (MeprUtils::is_user_logged_in() || self::verify_bot()) {
            return false;
        }

        // check if Post is excluded from the PayWall - if so do NOT allow them through
        if ($post !== false && self::is_excluded($post)) {
            return false;
        }

        $mepr_options = MeprOptions::fetch();

        if ($mepr_options->paywall_enabled && $mepr_options->paywall_num_free_views > 0) {
            $num_views = (isset($_COOKIE[self::$cookie_name]) && !empty($_COOKIE[self::$cookie_name])) ? $_COOKIE[self::$cookie_name] : 0;

            if ($num_views !== 0) {
                $num_views = base64_decode($num_views);
            }

            // There's a race condition happening here, so we need to add one to the uri's
            if ($type == 'uri') {
                $num_views += 1;
            }

            if ($num_views <= $mepr_options->paywall_num_free_views) {
                return true;
            }
        }
    }

    /**
     * Allow search engines content.
     *
     * @param  boolean $protect The protect.
     * @param  integer $post    The post.
     * @param  string  $uri     The uri.
     * @return boolean
     */
    public static function allow_search_engines_content($protect, $post, $uri)
    {
        if (self::allow_search_engines_through()) {
            return false; // Need to return false here to allow SE through the blocks
        }

        $hide_comments = $protect ? '__return_true' : '__return_false';
        add_filter('mepr-rule-comments', $hide_comments);

        return $protect;
    }

    /**
     * Allow search engines redirection.
     *
     * @param  boolean $protect The protect.
     * @param  string  $uri     The uri.
     * @param  string  $delim   The delim.
     * @return boolean
     */
    public static function allow_search_engines_redirection($protect, $uri, $delim)
    {
        if (self::allow_search_engines_through()) {
            return false; // Need to return false here to allow SE through the blocks
        }

        return $protect;
    }

    /**
     * Allow search engines through.
     *
     * @return boolean
     */
    public static function allow_search_engines_through()
    {
        $post = MeprUtils::get_current_post();

        // check if Post is excluded from the PayWall
        if ($post !== false && self::is_excluded($post)) {
            return false;
        }

        $mepr_options = MeprOptions::fetch();

        if ($mepr_options->authorize_seo_views) {
            return self::verify_bot();
        }

        return false;
    }

    /**
     * Verify bot.
     *
     * @return boolean
     */
    public static function verify_bot()
    {
        // If the user is logged in, then bail
        if (MeprUtils::is_user_logged_in()) {
            return false;
        }

        $agent = 'no-agent-found';
        if (isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        }

        $known_engines = ['google', 'bing', 'msn', 'yahoo', 'ask'];

        static $returned = null;

        if (!is_null($returned)) {
            return $returned;
        }

        foreach ($known_engines as $engine) {
            if (strpos($agent, $engine) !== false) {
                $ip_to_check = $_SERVER['REMOTE_ADDR'];

                // Lookup the host by this IP address
                $hostname = gethostbyaddr($ip_to_check);

                if ($engine == 'google' && !preg_match('#^.*\.googlebot\.com$#', $hostname)) {
                    break;
                }

                if (($engine == 'bing' || $engine == 'msn') && !preg_match('#^.*\.search\.msn\.com$#', $hostname)) {
                    break;
                }

                if ($engine == 'ask' && !preg_match('#^.*\.ask\.com$#', $hostname)) {
                    break;
                }

                // Even though yahoo is contracted with bingbot, they do still send out slurp to update some entries etc
                if (($engine == 'yahoo' || $engine == 'slurp') && !preg_match('#^.*\.crawl\.yahoo\.net$#', $hostname)) {
                    break;
                }

                if ($hostname !== false && $hostname != $ip_to_check) {
                    // Do the reverse lookup
                    $ip_to_verify = gethostbyname($hostname);

                    if ($ip_to_verify != $hostname && $ip_to_verify == $ip_to_check) {
                        $returned = true;
                        return $returned;
                    }
                }
            }
        }

        // Otherwise return false
        $returned = false;
        return $returned;
    }
}
