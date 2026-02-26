<?php

defined('ABSPATH') || exit;

/**
 * Brand controller for dashboard add-ons (Courses, Circles, Directory).
 *
 * Adds MemberPress-specific add-ons to the dashboard "Extend Membership Site" section.
 * Only adds add-ons that are not already installed/active.
 */
class MeprDashboardAddonsBrandCtrl extends MeprBaseCtrl
{
    /**
     * Load the hooks.
     *
     * @return void
     */
    public function load_hooks(): void
    {
        add_filter('mepr_dashboard_addons', [$this, 'add_dashboard_addons']);
    }

    /**
     * Add MemberPress add-ons to the dashboard add-ons list.
     * Only adds add-ons that are not already installed/active.
     *
     * @param array $addons Add-ons from app view (generic third-party only).
     *
     * @return array Add-ons with MemberPress add-ons prepended (only non-installed).
     */
    public function add_dashboard_addons(array $addons): array
    {
        $potential_addons = [
            [
                'check' => 'is_courses_addon_active',
                'data'  => [
                    'title'       => __('Courses', 'memberpress'),
                    'description' => __('Create and sell online courses with ease.', 'memberpress'),
                    'image'       => 'mm-courses.png',
                    'url'         => MeprUtils::get_link_url('addons_courses'),
                ],
            ],
            [
                'check' => 'is_circles_addon_active',
                'data'  => [
                    'title'       => __('Circles', 'memberpress'),
                    'description' => __('Build a thriving community with discussion groups.', 'memberpress'),
                    'image'       => 'club-suite.svg',
                    'url'         => MeprUtils::get_link_url('addons_circles'),
                ],
            ],
            [
                'check' => 'is_directory_addon_active',
                'data'  => [
                    'title'       => __('Directory', 'memberpress'),
                    'description' => __('Showcase your members with a searchable directory.', 'memberpress'),
                    'image'       => 'club-suite.svg',
                    'url'         => MeprUtils::get_link_url('addons_directory'),
                ],
            ],
        ];

        $brand_addons = [];
        foreach ($potential_addons as $addon) {
            if (!MeprPostSetupChecklistBrandHelper::{$addon['check']}()) {
                $brand_addons[] = $addon['data'];
            }
        }

        return array_merge($brand_addons, $addons);
    }
}
