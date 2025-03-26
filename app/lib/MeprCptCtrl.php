<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

abstract class MeprCptCtrl extends MeprBaseCtrl
{
    public $cpt;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        add_action('init', [$this, 'register_post_type'], 0);
        add_filter('post_updated_messages', [$this, 'post_updated_messages']);
        add_filter('bulk_post_updated_messages', [$this, 'bulk_post_updated_messages'], 10, 2);
        add_action('save_post', [$this, 'update_all_models_for_class_transient']);
        parent::__construct();
    }

    /**
     * Register the post type
     *
     * @return void
     */
    abstract public function register_post_type();

    /**
     * Update all models for class transient
     *
     * @param  integer $post_id The post ID
     * @return void
     */
    public function update_all_models_for_class_transient($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (wp_is_post_revision($post_id) !== false) {
            return; // Don't bother if it's a revision
        }

        if (!is_admin()) {
            return; // Don't run this on front-end stuff
        }

        $post = get_post($post_id);

        // Only do this for our own CPT's
        switch ($post->post_type) {
            case 'memberpresscoupon':
                $use_transient_cache = MeprHooks::apply_filters('mepr-cpt-all-use-transient-cache', true, $post->post_type, 'MeprCoupon');
                if (!$use_transient_cache) {
                    return;
                }
                MeprCptModel::all('MeprCoupon', true);
                break;
            case 'memberpressgroup':
                $use_transient_cache = MeprHooks::apply_filters('mepr-cpt-all-use-transient-cache', true, $post->post_type, 'MeprGroup');
                if (!$use_transient_cache) {
                    return;
                }
                MeprCptModel::all('MeprGroup', true);
                break;
            case 'memberpressproduct':
                $use_transient_cache = MeprHooks::apply_filters('mepr-cpt-all-use-transient-cache', true, $post->post_type, 'MeprProduct');
                if (!$use_transient_cache) {
                    return;
                }
                MeprCptModel::all('MeprProduct', true);
                break;
            case 'mp-reminder':
                $use_transient_cache = MeprHooks::apply_filters('mepr-cpt-all-use-transient-cache', true, $post->post_type, 'MeprReminder');
                if (!$use_transient_cache) {
                    return;
                }
                MeprCptModel::all('MeprReminder', true);
                break;
            case 'memberpressrule':
                $use_transient_cache = MeprHooks::apply_filters('mepr-cpt-all-use-transient-cache', true, $post->post_type, 'MeprRule');
                if (!$use_transient_cache) {
                    return;
                }
                MeprCptModel::all('MeprRule', true);
                break;
        }
    }

    /**
     * Used to ensure we don't see any references to 'post' or a link when.
     *
     * @param  array $messages The messages
     * @return array The modified messages
     */
    public function post_updated_messages($messages)
    {
        global $post, $post_ID;

        if (!isset($this->cpt) || !isset($this->cpt->config)) {
            return $messages;
        }

        $singular_name = $this->cpt->config['labels']['singular_name'];
        $slug          = $this->cpt->slug;
        $public        = $this->cpt->config['public'];

        $messages[$slug]    = [];
        $messages[$slug][0] = '';

        if ($public) {
            $messages[$slug][1] = sprintf(
                __('%1$s updated. <a href="%2$s">View %3$s</a>', 'memberpress'),
                $singular_name,
                esc_url(get_permalink($post_ID)),
                $singular_name
            );
        } else {
            $messages[$slug][1] = sprintf(__('%1$s updated.', 'memberpress'), $singular_name);
        }

        $messages[$slug][2] = __('Custom field updated.', 'memberpress');
        $messages[$slug][3] = __('Custom field deleted.', 'memberpress');
        $messages[$slug][4] = sprintf(__('%s updated.', 'memberpress'), $singular_name);
        $messages[$slug][5] = isset($_GET['revision']) ? sprintf(__('%1$s restored to revision from %2$s', 'memberpress'), $singular_name, wp_post_revision_title((int) $_GET['revision'], false)) : false;

        if ($public) {
            $messages[$slug][6] = sprintf(
                __('%1$s published. <a href="%2$s">View %3$s</a>', 'memberpress'),
                $singular_name,
                esc_url(get_permalink($post_ID)),
                $singular_name
            );
        } else {
            $messages[$slug][6] = sprintf(__('%1$s published.', 'memberpress'), $singular_name);
        }

        $messages[$slug][7] = sprintf(__('%s saved.', 'memberpress'), $singular_name);

        if ($public) {
            $messages[$slug][8]  = sprintf(
                __('%1$s submitted. <a target="_blank" href="%2$s">Preview %3$s</a>', 'memberpress'),
                $singular_name,
                esc_url(add_query_arg('preview', 'true', get_permalink($post_ID))),
                $singular_name
            );
            $messages[$slug][9]  = sprintf(
                __('%1$s scheduled for: <strong>%2$s</strong>. <a target="_blank" href="%3$s">Preview %4$s</a>', 'memberpress'),
                $singular_name,
                date_i18n('M j, Y @ G:i', strtotime($post->post_date), true),
                esc_url(get_permalink($post_ID)),
                $singular_name
            );
            $messages[$slug][10] = sprintf(
                __('%1$s draft updated. <a target="_blank" href="%2$s">Preview %3$s</a>', 'memberpress'),
                $singular_name,
                esc_url(add_query_arg('preview', 'true', get_permalink($post_ID))),
                $singular_name
            );
        } else {
            $messages[$slug][8]  = sprintf(__('%s submitted.', 'memberpress'), $singular_name);
            $messages[$slug][9]  = sprintf(
                __('%1$s scheduled for: <strong>%2$s</strong>.', 'memberpress'),
                $singular_name,
                date_i18n('M j, Y @ G:i', strtotime($post->post_date), true)
            );
            $messages[$slug][10] = sprintf(__('%s draft updated.', 'memberpress'), $singular_name);
        }

        return $messages;
    }

    /**
     * Modify the bulk update messages for the cpt associated with this controller
     *
     * @param  array $messages The messages
     * @param  array $counts   The counts
     * @return array The modified messages
     */
    public function bulk_post_updated_messages($messages, $counts)
    {
        global $post, $post_ID;

        if (!isset($this->cpt) || !isset($this->cpt->config)) {
            return $messages;
        }

        $singular_name = strtolower($this->cpt->config['labels']['singular_name']);
        $plural_name   = strtolower($this->cpt->config['labels']['name']);
        $slug          = $this->cpt->slug;
        $public        = $this->cpt->config['public'];

        $messages[$slug] = [
            'updated'   => _n(
                sprintf('%1$d %2$s updated.', $counts['updated'], $singular_name),
                sprintf('%1$d %2$s updated.', $counts['updated'], $plural_name),
                $counts['updated'],
                'memberpress'
            ),
            'locked'    => _n(
                sprintf('%1$d %2$s not updated, somebody is editing it.', $counts['locked'], $singular_name),
                sprintf('%1$d %2$s not updated, somebody is editing them.', $counts['locked'], $plural_name),
                $counts['locked'],
                'memberpress'
            ),
            'deleted'   => _n(
                sprintf('%1$d %2$s permanently deleted.', $counts['deleted'], $singular_name),
                sprintf('%1$d %2$s permanently deleted.', $counts['deleted'], $plural_name),
                $counts['deleted'],
                'memberpress'
            ),
            'trashed'   => _n(
                sprintf('%1$s %2$s moved to the Trash.', $counts['trashed'], $singular_name),
                sprintf('%1$s %2$s moved to the Trash.', $counts['trashed'], $plural_name),
                $counts['trashed'],
                'memberpress'
            ),
            'untrashed' => _n(
                sprintf('%1$s %2$s restored from the Trash.', $counts['untrashed'], $singular_name),
                sprintf('%1$s %2$s restored from the Trash.', $counts['untrashed'], $plural_name),
                $counts['untrashed'],
                'memberpress'
            ),
        ];

        return $messages;
    }
}
