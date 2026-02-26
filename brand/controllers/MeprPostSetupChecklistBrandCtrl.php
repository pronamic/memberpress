<?php

defined('ABSPATH') || exit;

/**
 * Brand controller for post-setup checklist optional steps.
 *
 * Adds MemberPress-specific optional steps (Courses, ClubDirectory™,
 * ClubCircles™, Digital Downloads) and addon post types for "core page"
 * detection via filters.
 */
class MeprPostSetupChecklistBrandCtrl extends MeprBaseCtrl
{
    /**
     * Load the hooks.
     *
     * @return void
     */
    public function load_hooks(): void
    {
        add_filter('mepr_post_setup_checklist_optional_steps', [$this, 'add_optional_steps'], 20);
        add_filter('mepr_post_setup_checklist_addon_post_types', [$this, 'get_addon_post_types'], 20);
    }

    /**
     * Return MemberPress addon post types for checklist "core page" detection.
     *
     * When the user is on one of these edit screens, the post-setup checklist
     * is considered to be on a "core" page and can be shown.
     *
     * @param array $post_types Addon post types from earlier filter callbacks (may be empty).
     *
     * @return array Addon post type slugs.
     */
    public function get_addon_post_types(array $post_types): array
    {
        return array_merge($post_types, [
            'mpcs-course',     // MemberPress Courses.
            'mpdl-file',       // MemberPress Downloads.
            'mpdir-profile',   // ClubSuite Profiles.
            'mpdir-directory', // ClubSuite Directories.
            'mp-circle',       // ClubSuite Circles.
        ]);
    }

    /**
     * Add MemberPress brand optional steps after the coupon step.
     *
     * @param array $steps Optional steps from core (and earlier filter callbacks).
     *
     * @return array Modified steps array with brand steps inserted.
     */
    public function add_optional_steps(array $steps): array
    {
        $brand_steps = [
            [
                'id'           => 'create_course',
                'title'        => __('Create a course', 'memberpress'),
                'description'  => __('Build your first course with MemberPress Courses.', 'memberpress'),
                'completed'    => MeprPostSetupChecklistBrandHelper::has_course(),
                'action_url'   => MeprPostSetupChecklistBrandHelper::get_courses_action_url(),
                'action_text'  => MeprPostSetupChecklistBrandHelper::get_courses_action_text(),
                'requires'     => 'memberpress-courses',
                'installed'    => MeprPostSetupChecklistBrandHelper::is_courses_addon_installed(),
                'active'       => MeprPostSetupChecklistBrandHelper::is_courses_addon_active(),
                'installable'  => MeprPostSetupChecklistHelper::is_addon_installable('memberpress-courses'),
                'plugin_file'  => 'memberpress-courses/main.php',
                'download_url' => MeprPostSetupChecklistHelper::get_addon_download_url('memberpress-courses'),
                'addon_type'   => 'addon',
            ],
            [
                'id'           => 'setup_directory',
                'title'        => __('Set up ClubDirectory™', 'memberpress'),
                'description'  => __('Create a member directory for your community.', 'memberpress'),
                'completed'    => MeprPostSetupChecklistBrandHelper::has_directory(),
                'action_url'   => MeprPostSetupChecklistBrandHelper::get_directory_action_url(),
                'action_text'  => MeprPostSetupChecklistBrandHelper::get_directory_action_text(),
                'requires'     => 'memberpress-directory',
                'installed'    => MeprPostSetupChecklistBrandHelper::is_directory_addon_installed(),
                'active'       => MeprPostSetupChecklistBrandHelper::is_directory_addon_active(),
                'installable'  => MeprPostSetupChecklistHelper::is_addon_installable('memberpress-directory'),
                'plugin_file'  => 'memberpress-directory/main.php',
                'download_url' => MeprPostSetupChecklistHelper::get_addon_download_url('memberpress-directory'),
                'addon_type'   => 'addon',
            ],
            [
                'id'           => 'setup_circles',
                'title'        => __('Set up ClubCircles™', 'memberpress'),
                'description'  => __('Create private community spaces for your members.', 'memberpress'),
                'completed'    => MeprPostSetupChecklistBrandHelper::has_circle(),
                'action_url'   => MeprPostSetupChecklistBrandHelper::get_circles_action_url(),
                'action_text'  => MeprPostSetupChecklistBrandHelper::get_circles_action_text(),
                'requires'     => 'memberpress-circles',
                'installed'    => MeprPostSetupChecklistBrandHelper::is_circles_addon_installed(),
                'active'       => MeprPostSetupChecklistBrandHelper::is_circles_addon_active(),
                'installable'  => MeprPostSetupChecklistHelper::is_addon_installable('memberpress-circles'),
                'plugin_file'  => 'memberpress-circles/main.php',
                'download_url' => MeprPostSetupChecklistHelper::get_addon_download_url('memberpress-circles'),
                'addon_type'   => 'addon',
            ],
            [
                'id'           => 'setup_downloads',
                'title'        => __('Set up Digital Downloads', 'memberpress'),
                'description'  => __('Protect and deliver downloadable files to your members.', 'memberpress'),
                'completed'    => MeprPostSetupChecklistBrandHelper::has_download(),
                'action_url'   => MeprPostSetupChecklistBrandHelper::get_downloads_action_url(),
                'action_text'  => MeprPostSetupChecklistBrandHelper::get_downloads_action_text(),
                'requires'     => 'memberpress-downloads',
                'installed'    => MeprPostSetupChecklistBrandHelper::is_downloads_addon_installed(),
                'active'       => MeprPostSetupChecklistBrandHelper::is_downloads_addon_active(),
                'installable'  => MeprPostSetupChecklistHelper::is_addon_installable('memberpress-downloads'),
                'plugin_file'  => 'memberpress-downloads/main.php',
                'download_url' => MeprPostSetupChecklistHelper::get_addon_download_url('memberpress-downloads'),
                'addon_type'   => 'addon',
            ],
        ];

        // Insert brand steps after the 'create_coupon' step.
        $keys     = array_column($steps, 'id');
        $index    = array_search('create_coupon', $keys, true);
        $insert_at = ($index === false) ? 1 : $index + 1;
        array_splice($steps, $insert_at, 0, $brand_steps);

        return $steps;
    }
}
