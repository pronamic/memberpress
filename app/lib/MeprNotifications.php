<?php

use MemberPress\GroundLevel\InProductNotifications\Services\Retriever as IPNRetrieverService;
use MemberPress\GroundLevel\InProductNotifications\Services\Store;
use MemberPress\GroundLevel\InProductNotifications\Services\View as IPNViewService;
use MemberPress\GroundLevel\Mothership\Api\Request\Products;

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * MeprNotifications.
 *
 * Class for logging in-plugin notifications.
 * Includes:
 *     Notifications from our remote feed
 *     Plugin-related notifications (e.g. recent sales performances)
 */
class MeprNotifications
{
    /**
     * Source of notifications content.
     *
     * @deprecated 1.11.36
     *
     * @var string
     */
    const SOURCE_URL = 'https://mbr.press/UttZvA';
    /**
     * Source URL arguments.
     *
     * @deprecated 1.11.36
     */
    const SOURCE_URL_ARGS = [];

    /**
     * Option value.
     *
     * @deprecated 1.11.36
     *
     * @var boolean|array
     */
    public $option = false;

    /**
     * Initialize class.
     *
     * @deprecated 1.11.36
     */
    public function init()
    {
        _deprecated_function(__METHOD__, '1.11.36');
    }

    /**
     * Register hooks.
     *
     * @deprecated 1.11.36
     */
    public function hooks()
    {
        _deprecated_function(__METHOD__, '1.11.36');
    }

    /**
     * Check if user has access and is enabled.
     *
     * @return boolean
     */
    public static function has_access()
    {
        $has_access = MeprUtils::is_mepr_admin() && ! get_option('mepr_hide_announcements');
        /**
         * Filters whether or not the user has access to notifications.
         *
         * @param boolean $has_access Whether or not the user has access to notifications.
         */
        return apply_filters('mepr_admin_notifications_has_access', $has_access);
    }

    /**
     * Get option value.
     *
     * @deprecated 1.11.36
     *
     * @param boolean $cache Reference property cache if available.
     *
     * @return void
     */
    public function get_option($cache = true)
    {
        _deprecated_function(__METHOD__, '1.11.36');
    }

    /**
     * Make sure the feed is fetched when needed.
     *
     * @deprecated 1.11.36 Use {@see \MemberPress\GroundLevel\InProductNotifications\Services\Retriever::schedule()} instead.
     *
     * @return void
     */
    public function schedule_fetch()
    {
        _deprecated_function(__METHOD__, '1.11.36');
        MeprGrdLvlCtrl::init(true);
        MeprGrdLvlCtrl::getContainer()->get(IPNRetrieverService::class)->schedule();
    }

    /**
     * Fetch notifications from remote feed.
     *
     * @deprecated 1.11.36 Use {@see \MemberPress\GroundLevel\Mothership\Api\Request\Products::getNotifications()} instead.
     *
     * @return array
     */
    public function fetch_feed()
    {
        _deprecated_function(__METHOD__, '1.11.36');
        $req = Products::getNotifications(MEPR_EDITION);
        return $req->isSuccess() ? $req->notifications : [];
    }

    /**
     * Verify notification data before it is saved.
     *
     * @deprecated 1.11.36
     *
     * @param array $notifications Array of notifications items to verify.
     *
     * @return void
     */
    public function verify($notifications)
    {
        _deprecated_function(__METHOD__, '1.11.36');
    }

    /**
     * Verify saved notification data for active notifications.
     *
     * @deprecated 1.11.36
     *
     * @param array $notifications Array of notifications items to verify.
     *
     * @return void
     */
    public function verify_active($notifications)
    {
        _deprecated_function(__METHOD__, '1.11.36');
    }

    /**
     * Get notification data.
     *
     * @deprecated 1.11.36
     *
     * @return void
     */
    public function get()
    {
        _deprecated_function(__METHOD__, '1.11.36');
    }


    /**
     * Improve format of the content of notifications before display. By default just runs wpautop.
     *
     * @deprecated 1.11.36
     *
     * @param array $notifications The notifications to be parsed.
     *
     * @return void
     */
    public function get_notifications_with_formatted_content($notifications)
    {
        _deprecated_function(__METHOD__, '1.11.36');
    }

    /**
     * Get notifications start time with human time difference
     *
     * @deprecated 1.11.36 With not replacement
     *
     * @param  array $notifications The notifications.
     * @return void
     */
    public function get_notifications_with_human_readeable_start_time($notifications)
    {
        _deprecated_function(__METHOD__, '1.11.36');
    }

    /**
     * Get notification count.
     *
     * @deprecated 1.11.36 Use {@see \MemberPress\GroundLevel\InProductNotifications\Services\Store::notifications()} instead.
     *
     * @return integer
     */
    public function get_count()
    {
        _deprecated_function(__METHOD__, '1.11.36');
        MeprGrdLvlCtrl::init(true);
        return count(
            MeprGrdLvlCtrl::getContainer()->get(Store::class)->fetch()->notifications()
        );
    }

    /**
     * Add an event notification. This is NOT for feed notifications.
     * Event notifications are for alerting the user to something internally (e.g. recent sales performances).
     *
     * @param array $notification Notification data.
     */
    public function add($notification)
    {
        if (empty($notification['id'])) {
            return;
        }

        MeprGrdLvlCtrl::init(true);

        /** @var \MemberPress\GroundLevel\InProductNotifications\Services\Store $store */ // phpcs:ignore
        $store = MeprGrdLvlCtrl::getContainer()->get(Store::class)->fetch();

        $btns = [];
        foreach ($notification['buttons'] as $type => $data) {
            $btns[] = sprintf(
                '<a class="btn btn--%1$s" href="%2$s" target="%3$s" rel="noopener">%4$s</a>',
                'main' === $type ? 'primary' : 'link',
                esc_url($data['url']),
                esc_attr($data['target'] ?? '_blank'),
                esc_html($data['text'])
            );
        }
        $store->add([
            'id'           => $notification['type'] . '_' . $notification['id'],
            'subject'      => $notification['title'],
            'content'      => $notification['content'] . '<p>' . implode(' ', $btns) . '</p>',
            'publishes_at' => date('Y-m-d H:i:s', $notification['saved'] ?? time()),
            'icon'         => sprintf(
                '<img alt="%1$s" src="%2$s" style="width: 100%%; height: auto;">',
                esc_attr__('Notification Icon', 'memberpress'),
                $notification['icon'] ?? MEPR_IMAGES_URL . '/alert-icon.png'
            ),
        ])->persist();
    }

    /**
     * Update notification data from feed.
     * This pulls the latest notifications from our remote feed.
     *
     * @deprecated 1.11.36 Use {@see \MemberPress\GroundLevel\InProductNotifications\Services\Retriever::performEvent()} instead.
     */
    public function update()
    {
        _deprecated_function(__METHOD__, '1.11.36');
        MeprGrdLvlCtrl::init(true);
        MeprGrdLvlCtrl::getContainer()->get(IPNRetrieverService::class)->performEvent();
    }

    /**
     * Admin area enqueues.
     *
     * @deprecated 1.11.36
     */
    public function enqueues()
    {
        _deprecated_function(__METHOD__, '1.11.36');
    }

    /**
     * Admin script for adding notification count to the MemberPress admin menu list item.
     *
     * @deprecated 1.11.36 Use {@see \MemberPress\GroundLevel\InProductNotifications\Services\View::appendCountToMainMenuItem()} instead.
     */
    public function admin_menu_append_count()
    {
        _deprecated_function(__METHOD__, '1.11.36');
        if (self::has_access()) {
            MeprGrdLvlCtrl::getContainer()->get(IPNViewService::class)->appendCountToMainMenuItem();
        }
    }

    /**
     * Output notifications in MemberPress admin area.
     *
     * @deprecated 1.11.36 Use {@see \MemberPress\GroundLevel\InProductNotifications\Services\View::render()} instead.
     */
    public function output()
    {
        _deprecated_function(__METHOD__, '1.11.36');
        if (self::has_access()) {
            MeprGrdLvlCtrl::getContainer()->get(IPNViewService::class)->render();
        }
    }

    /**
     * Dismiss notification(s) via AJAX.
     *
     * @deprecated 1.11.36 With no direct replacement.
     */
    public function dismiss()
    {
        _deprecated_function(__METHOD__, '1.11.36');
    }

    /**
     * Dismisses event notifications by type.
     *
     * @param string $type The event type, eg 'mepr-drm'.
     */
    public function dismiss_events($type)
    {
        MeprGrdLvlCtrl::init(true);
        /** @var \MemberPress\GroundLevel\InProductNotifications\Services\Store $store */ // phpcs:ignore
        $store   = MeprGrdLvlCtrl::getContainer()->get(Store::class)->fetch();
        $persist = false;
        foreach ($store->notifications(false) as $notification) {
            if (str_starts_with($notification->id, $type . '_event_')) {
                $store->delete($notification->id);
                $persist = true;
            }
        }

        if ($persist) {
            $store->persist();
        }
    }
}
