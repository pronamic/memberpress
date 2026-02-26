<?php

defined('ABSPATH') || exit;

/**
 * Brand controller for dashboard contextual resources (docs and blog links).
 *
 * Supplies MemberPress-specific help resources for the dashboard based on
 * the user's setup state. All resources point to MemberPress docs and blog.
 */
class MeprContextualResourcesBrandCtrl extends MeprBaseCtrl
{
    /**
     * Load the hooks.
     *
     * @return void
     */
    public function load_hooks(): void
    {
        add_filter('mepr_dashboard_contextual_resources', [$this, 'get_contextual_resources'], 10, 2);
    }

    /**
     * Return contextual resources based on setup state.
     *
     * Appends MemberPress-specific resources to any resources already provided by
     * other plugins or higher-priority callbacks; the combined list is then
     * trimmed to the context limit.
     *
     * @param array $resources Resources from earlier filter callbacks (may be empty).
     * @param array $context   Setup state: has_gateway, has_membership, has_rule,
     *                         has_reminder, is_launch_phase, limit.
     *
     * @return array Array of resource items with 'title', 'type', and 'url' keys.
     */
    public function get_contextual_resources(array $resources, array $context): array
    {
        $brand_resources = [];
        $limit           = isset($context['limit']) ? (int) $context['limit'] : 4;
        $has_gateway     = !empty($context['has_gateway']);
        $has_membership  = !empty($context['has_membership']);
        $has_rule        = !empty($context['has_rule']);
        $has_reminder    = !empty($context['has_reminder']);
        $is_launch_phase = !empty($context['is_launch_phase']);

        // Priority 1: Essential setup resources.
        if (!$has_gateway) {
            $brand_resources[] = [
                'title' => __('How to Connect Your Payment Gateway', 'memberpress'),
                'type'  => __('Setup Guide', 'memberpress'),
                'url'   => MeprUtils::get_link_url('docs_dash_payments'),
            ];
        }

        if (!$has_membership) {
            $brand_resources[] = [
                'title' => __('Creating Your First Membership', 'memberpress'),
                'type'  => __('Getting Started', 'memberpress'),
                'url'   => MeprUtils::get_link_url('docs_dash_memberships'),
            ];
        }

        if ($has_membership && !$has_rule) {
            $brand_resources[] = [
                'title' => __('Protecting Your Content with Rules', 'memberpress'),
                'type'  => __('Setup Guide', 'memberpress'),
                'url'   => MeprUtils::get_link_url('docs_dash_rules'),
            ];
        }

        if ($has_membership && !$has_reminder) {
            $brand_resources[] = [
                'title' => __('Reduce Churn with Automated Reminders', 'memberpress'),
                'type'  => __('Best Practice', 'memberpress'),
                'url'   => MeprUtils::get_link_url('docs_dash_reminders'),
            ];
        }

        // Priority 2: Phase-appropriate resources.
        if ($is_launch_phase) {
            $brand_resources[] = [
                'title' => __('Launch Checklist: Get Your First Sale', 'memberpress'),
                'type'  => __('Getting Started', 'memberpress'),
                'url'   => MeprUtils::get_link_url('docs_dash_getting_started'),
            ];
            $brand_resources[] = [
                'title' => __('Pricing Your Membership for Success', 'memberpress'),
                'type'  => __('Strategy', 'memberpress'),
                'url'   => MeprUtils::get_link_url('blog_dash_pricing'),
            ];
        } else {
            $brand_resources[] = [
                'title' => __('Grow Your Membership with Coupons', 'memberpress'),
                'type'  => __('Growth Tips', 'memberpress'),
                'url'   => MeprUtils::get_link_url('docs_dash_coupons'),
            ];
            $brand_resources[] = [
                'title' => __('Understanding Your Revenue Reports', 'memberpress'),
                'type'  => __('Analytics', 'memberpress'),
                'url'   => MeprUtils::get_link_url('docs_dash_reports'),
            ];
        }

        // Priority 3: General resources (fill remaining slots).
        $general = [
            [
                'title' => __('Memberships and Groups', 'memberpress'),
                'type'  => __('Documentation', 'memberpress'),
                'url'   => MeprUtils::get_link_url('docs_dash_groups'),
            ],
            [
                'title' => __('Customizing Your Registration Pages', 'memberpress'),
                'type'  => __('Customization', 'memberpress'),
                'url'   => MeprUtils::get_link_url('docs_dash_readylaunch'),
            ],
            [
                'title' => __('Setting Up Email Notifications', 'memberpress'),
                'type'  => __('Communication', 'memberpress'),
                'url'   => MeprUtils::get_link_url('docs_dash_emails'),
            ],
        ];

        foreach ($general as $resource) {
            if (count($brand_resources) >= $limit) {
                break;
            }
            $brand_resources[] = $resource;
        }

        return array_slice(array_merge($resources, $brand_resources), 0, $limit);
    }
}
