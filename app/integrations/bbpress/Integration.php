<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Integration of bbPress into MemberPress
 */
class MeprBbPressIntegration
{
    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        // Used to hide forums & topics
        add_filter('bbp_get_forum_visibility', 'MeprBbPressIntegration::hide_forums', 11, 2);
        add_filter('bbp_get_hidden_forum_ids', 'MeprBbPressIntegration::hide_threads');
        // Do not redirect if in sidebar
        add_action('dynamic_sidebar_before', 'MeprBbPressIntegration::in_sidebar', 11, 2);
        add_action('dynamic_sidebar_after', 'MeprBbPressIntegration::out_sidebar', 11, 2);

        // We're only allowing blocking by forum
        add_filter('mepr-rules-cpts', 'MeprBbPressIntegration::filter_rules_cpts');

        add_action('mepr_account_nav', 'MeprBbPressIntegration::mepr_account_page_links');

        // Don't override bbPress the_content - this is needed when using the forum shortcodes
        add_filter('mepr-pre-run-rule-content', 'MeprBbPressIntegration::dont_block_the_content', 11, 3);
        add_filter('is_bbpress', 'MeprBbPressIntegration::dont_redirect_on_shortcode');

        // Hide the content of replies
        add_filter('bbp_get_reply_content', 'MeprBbPressIntegration::bbpress_rule_content', 999999, 2);
    }

    /**
     * BBPress rule content.
     *
     * @param  string  $content The content.
     * @param  integer $id      The id.
     * @return string
     */
    public static function bbpress_rule_content($content, $id)
    {
        // We only allow restriction on a per-forum basis currently
        // So let's get the current forum's id and check if it's protected
        $forum_id = bbp_get_reply_forum_id($id);

        if (!$forum_id) {
            return $content;
        }

        $post = get_post($forum_id);

        if (!isset($post) || !MeprRule::is_locked($post)) {
            return $content;
        }

        return apply_filters('mepr-bbpress-unauthorized-message', MeprRulesCtrl::unauthorized_message($post));
    }

    /**
     * Don't redirect on shortcode.
     *
     * @param  boolean $bool The bool.
     * @return boolean
     */
    public static function dont_redirect_on_shortcode($bool)
    {
        global $wp_query;

        if (empty($wp_query->queried_object->post_content)) {
            return $bool;
        }

        if (strpos($wp_query->queried_object->post_content, '[bbp-forum-index') !== false) {
            $_REQUEST['mepr_is_bbp_shortcode'] = true; // Set this so we can later check for it in hide_forums
        }

        return $bool;
    }

    /**
     * Don't block the content.
     *
     * @param  boolean $block        The block.
     * @param  integer $current_post The current post.
     * @param  string  $uri          The uri.
     * @return boolean
     */
    public static function dont_block_the_content($block, $current_post, $uri)
    {
        if (function_exists('is_bbpress') && is_bbpress()) {
            return false;
        }
        return $block;
    }

    /**
     * Mepr account page links.
     *
     * @param  object $user The user.
     * @return void
     */
    public static function mepr_account_page_links($user)
    {
        if (!class_exists('bbPress')) {
            return;
        }

        ?>
      <span class="mepr-nav-item mepr_bbpress_subscriptions">
        <a href="<?php echo bbp_user_profile_url(bbp_get_current_user_id()); ?>" id="mepr-account-bbpress-subscriptions"><?php _e('Forum Profile', 'memberpress'); ?></a>
      </span>
        <?php
    }

    /**
     * Hide threads.
     *
     * @param  array $ids The ids.
     * @return array
     */
    public static function hide_threads($ids)
    {
        global $wpdb;

        $all_forums = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'forum'");
        $call       = function_exists('debug_backtrace') ? debug_backtrace() : [];
        $to_hide    = [];

        if (!empty($all_forums)) {
            foreach ($all_forums as $forum) {
                $forum = get_post($forum->ID);

                if (MeprRule::is_locked($forum)) {
                    $to_hide[] = $forum->ID;
                }
            }
        }

        foreach ($call as $c) {
            // We only want to hide in indexes or searches for now
            if (
                $c['function'] == 'display_topic_index' ||
                $c['function'] == 'display_search'
            ) {
                $ids = array_merge($ids, $to_hide);
            }
        }

        return $ids;
    }

    /**
     * In sidebar.
     *
     * @param  integer $index The index.
     * @param  boolean $bool  The bool.
     * @return void
     */
    public static function in_sidebar($index, $bool)
    {
        $_REQUEST['mepr_bbpress_in_sidebar'] = true;
    }

    /**
     * Out sidebar.
     *
     * @param  integer $index The index.
     * @param  boolean $bool  The bool.
     * @return void
     */
    public static function out_sidebar($index, $bool)
    {
        $_REQUEST['mepr_bbpress_in_sidebar'] = false;
    }

    /**
     * Hide forums.
     * Used mostly for redirecting to the login or unauthorized page if the current forum is locked
     *
     * @param  string  $status   The status.
     * @param  integer $forum_id The forum id.
     * @return string
     */
    public static function hide_forums($status, $forum_id)
    {
        if (isset($_REQUEST['mepr_is_bbp_shortcode'])) {
            return $status;
        }
        if (isset($_REQUEST['mepr_bbpress_in_sidebar']) && $_REQUEST['mepr_bbpress_in_sidebar']) {
            return $status;
        }

        static $already_here;
        if (isset($already_here) && $already_here) {
            return $status;
        }
        $already_here = true;

        $mepr_options = MeprOptions::fetch();
        $forum        = get_post($forum_id);
        $uri          = urlencode(esc_url($_SERVER['REQUEST_URI']));

        $actual_forum_id = bbp_get_forum_id();
        $forum           = get_post($actual_forum_id);

        // Not a singular view, then let's bail
        if (!is_singular()) {
            return $status;
        }

        // Let moderators and keymasters see everything
        if (current_user_can('edit_others_topics')) {
            return $status;
        }

        if (!isset($forum)) {
            return $status;
        }

        if (MeprRule::is_locked($forum)) {
            if (!headers_sent()) {
                if ($mepr_options->redirect_on_unauthorized) {
                    $delim       = MeprAppCtrl::get_param_delimiter_char($mepr_options->unauthorized_redirect_url);
                    $redirect_to = "{$mepr_options->unauthorized_redirect_url}{$delim}mepr-unauth-page={$forum->ID}&redirect_to={$uri}";
                } else {
                    $redirect_to = $mepr_options->login_page_url("action=mepr_unauthorized&mepr-unauth-page={$forum->ID}&redirect_to=" . $uri);
                }

                $redirect_to = (MeprUtils::is_ssl()) ? str_replace('http:', 'https:', $redirect_to) : $redirect_to;
                MeprUtils::wp_redirect($redirect_to);
                exit;
            } else {
                $status = 'hidden';
            }
        }

        return $status;
    }

    /**
     * Filter rules cpts.
     *
     * @param  array $cpts The cpts.
     * @return array
     */
    public static function filter_rules_cpts($cpts)
    {
        unset($cpts['reply']);
        unset($cpts['topic']);

        return $cpts;
    }
}

new MeprBbPressIntegration();
