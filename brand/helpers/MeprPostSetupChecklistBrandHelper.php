<?php

defined('ABSPATH') || exit;

/**
 * Brand helper for post-setup checklist optional steps (Courses, ClubDirectory™,
 * ClubCircles™, Digital Downloads).
 *
 * Provides MemberPress-specific detection and URLs for these addon steps.
 * Generic addon logic (is_addon_installable, get_addon_download_url) remains
 * in MeprPostSetupChecklistHelper.
 */
class MeprPostSetupChecklistBrandHelper
{
    /**
     * Check if the MemberPress Courses addon is installed.
     *
     * @return boolean True if installed.
     */
    public static function is_courses_addon_installed()
    {
        return is_dir(WP_PLUGIN_DIR . '/memberpress-courses');
    }

    /**
     * Check if the MemberPress Courses addon is active.
     *
     * @return boolean True if active.
     */
    public static function is_courses_addon_active()
    {
        return MeprUtils::is_addon_active(MeprUtils::ADDON_COURSES);
    }

    /**
     * Check if at least one course exists.
     *
     * @return boolean True if a course exists.
     */
    public static function has_course()
    {
        if (!self::is_courses_addon_active()) {
            return false;
        }

        if (!post_type_exists('mpcs-course')) {
            return false;
        }

        $counts = wp_count_posts('mpcs-course');

        return (int) ($counts->publish ?? 0) > 0;
    }

    /**
     * Get the action URL for courses step.
     *
     * @return string Action URL.
     */
    public static function get_courses_action_url()
    {
        if (self::is_courses_addon_active()) {
            return admin_url('post-new.php?post_type=mpcs-course');
        }

        return admin_url('admin.php?page=memberpress-addons');
    }

    /**
     * Get the action text for courses step.
     *
     * @return string Action text.
     */
    public static function get_courses_action_text()
    {
        if (self::is_courses_addon_active()) {
            return __('Create Course', 'memberpress');
        }

        if (self::is_courses_addon_installed()) {
            return __('Activate Add-on', 'memberpress');
        }

        return __('Install Add-on', 'memberpress');
    }

    /**
     * Check if the MemberPress Directory addon is installed.
     *
     * @return boolean True if installed.
     */
    public static function is_directory_addon_installed()
    {
        return is_dir(WP_PLUGIN_DIR . '/memberpress-directory');
    }

    /**
     * Check if the MemberPress Directory addon is active.
     *
     * @return boolean True if active.
     */
    public static function is_directory_addon_active()
    {
        return is_plugin_active('memberpress-directory/main.php');
    }

    /**
     * Check if at least one directory exists.
     *
     * @return boolean True if a directory exists.
     */
    public static function has_directory()
    {
        if (!self::is_directory_addon_active()) {
            return false;
        }

        if (!post_type_exists('mpdir-directory')) {
            return false;
        }

        $counts = wp_count_posts('mpdir-directory');

        return (int) ($counts->publish ?? 0) > 0;
    }

    /**
     * Get the action URL for directory step.
     *
     * @return string Action URL.
     */
    public static function get_directory_action_url()
    {
        if (self::is_directory_addon_active()) {
            return admin_url('edit.php?post_type=mpdir-directory');
        }

        if (!MeprPostSetupChecklistHelper::is_addon_installable('memberpress-directory')) {
            return MeprUtils::get_link_url('login_redirect_pricing');
        }

        return admin_url('admin.php?page=memberpress-addons');
    }

    /**
     * Get the action text for directory step.
     *
     * @return string Action text.
     */
    public static function get_directory_action_text()
    {
        if (self::is_directory_addon_active()) {
            return __('Manage Directory', 'memberpress');
        }

        if (self::is_directory_addon_installed()) {
            return __('Activate Add-on', 'memberpress');
        }

        if (!MeprPostSetupChecklistHelper::is_addon_installable('memberpress-directory')) {
            return __('Upgrade Plan', 'memberpress');
        }

        return __('Install Add-on', 'memberpress');
    }

    /**
     * Check if the MemberPress Circles addon is installed.
     *
     * @return boolean True if installed.
     */
    public static function is_circles_addon_installed()
    {
        return is_dir(WP_PLUGIN_DIR . '/memberpress-circles');
    }

    /**
     * Check if the MemberPress Circles addon is active.
     *
     * @return boolean True if active.
     */
    public static function is_circles_addon_active()
    {
        return is_plugin_active('memberpress-circles/main.php');
    }

    /**
     * Check if at least one circle exists.
     *
     * @return boolean True if a circle exists.
     */
    public static function has_circle()
    {
        if (!self::is_circles_addon_active()) {
            return false;
        }

        if (!post_type_exists('mp-circle')) {
            return false;
        }

        $counts = wp_count_posts('mp-circle');

        return (int) ($counts->publish ?? 0) > 0;
    }

    /**
     * Get the action URL for circles step.
     *
     * @return string Action URL.
     */
    public static function get_circles_action_url()
    {
        if (self::is_circles_addon_active()) {
            return admin_url('edit.php?post_type=mp-circle');
        }

        if (!MeprPostSetupChecklistHelper::is_addon_installable('memberpress-circles')) {
            return MeprUtils::get_link_url('login_redirect_pricing');
        }

        return admin_url('admin.php?page=memberpress-addons');
    }

    /**
     * Get the action text for circles step.
     *
     * @return string Action text.
     */
    public static function get_circles_action_text()
    {
        if (self::is_circles_addon_active()) {
            return __('Manage Circles', 'memberpress');
        }

        if (self::is_circles_addon_installed()) {
            return __('Activate Add-on', 'memberpress');
        }

        if (!MeprPostSetupChecklistHelper::is_addon_installable('memberpress-circles')) {
            return __('Upgrade Plan', 'memberpress');
        }

        return __('Install Add-on', 'memberpress');
    }

    /**
     * Check if the MemberPress Downloads addon is installed.
     *
     * @return boolean True if installed.
     */
    public static function is_downloads_addon_installed()
    {
        return is_dir(WP_PLUGIN_DIR . '/memberpress-downloads');
    }

    /**
     * Check if the MemberPress Downloads addon is active.
     *
     * @return boolean True if active.
     */
    public static function is_downloads_addon_active()
    {
        return is_plugin_active('memberpress-downloads/main.php');
    }

    /**
     * Check if at least one download file exists.
     *
     * @return boolean True if a download file exists.
     */
    public static function has_download()
    {
        if (!self::is_downloads_addon_active()) {
            return false;
        }

        if (!post_type_exists('mpdl-file')) {
            return false;
        }

        $counts = wp_count_posts('mpdl-file');

        return (int) ($counts->publish ?? 0) > 0;
    }

    /**
     * Get the action URL for downloads step.
     *
     * @return string Action URL.
     */
    public static function get_downloads_action_url()
    {
        if (self::is_downloads_addon_active()) {
            return admin_url('edit.php?post_type=mpdl-file');
        }

        if (!MeprPostSetupChecklistHelper::is_addon_installable('memberpress-downloads')) {
            return MeprUtils::get_link_url('login_redirect_pricing');
        }

        return admin_url('admin.php?page=memberpress-addons');
    }

    /**
     * Get the action text for downloads step.
     *
     * @return string Action text.
     */
    public static function get_downloads_action_text()
    {
        if (self::is_downloads_addon_active()) {
            return __('Manage Downloads', 'memberpress');
        }

        if (self::is_downloads_addon_installed()) {
            return __('Activate Add-on', 'memberpress');
        }

        if (!MeprPostSetupChecklistHelper::is_addon_installable('memberpress-downloads')) {
            return __('Upgrade Plan', 'memberpress');
        }

        return __('Install Add-on', 'memberpress');
    }
}
