<?php

defined('ABSPATH') || exit;

/**
 * Brand controller for MemberPress-specific Next Best Action recommendations.
 *
 * Adds MemberPress-specific upsell and cross-sell actions to the dashboard
 * Next Best Action widget.
 */
class MeprNbaBrandCtrl extends MeprBaseCtrl
{
    /**
     * Member count threshold for Scale plan upsell.
     */
    private const SCALE_UPSELL_MEMBER_THRESHOLD = 100;

    /**
     * Load the hooks.
     *
     * @return void
     */
    public function load_hooks(): void
    {
        add_filter('mepr_nba_upsell_actions', [$this, 'add_upsell_actions'], 10, 2);
        add_filter('mepr_nba_cross_sell_actions', [$this, 'add_cross_sell_actions'], 10, 1);
        add_filter('mepr_nba_utm_source', [$this, 'filter_utm_source']);
    }

    /**
     * Add MemberPress-specific upsell actions.
     *
     * @param array   $actions      The upsell actions array.
     * @param integer $member_count The active member count.
     *
     * @return array The modified actions array.
     */
    public function add_upsell_actions(array $actions, int $member_count): array
    {
        // Scale plan upsell for users with 100+ members on Basic or Growth plans.
        if ($member_count >= self::SCALE_UPSELL_MEMBER_THRESHOLD && $this->is_basic_or_growth_license()) {
            $actions[] = [
                'id'          => 'upsell_scale_plan',
                'title'       => __('You\'re growing fast!', 'memberpress'),
                'description' => __('Upgrade to Scale to unlock a built-in affiliate program and let partners help grow your membership even faster.', 'memberpress'),
                'action_url'  => $this->get_utm_url('https://memberpress.com/ipob/upgrade-scale/', 'scale_upsell_dashboard'),
                'action_text' => __('Upgrade to Scale', 'memberpress'),
                'effort'      => __('Takes ~10 minutes', 'memberpress'),
                'type'        => 'upsell',
                'priority'    => 7,
                'applicable'  => true,
                'reach'       => 50,
                'impact'      => 2,
                'confidence'  => 85,
                'effort_score' => 2,
            ];
        }

        return $actions;
    }

    /**
     * Add MemberPress-specific cross-sell actions.
     *
     * @param array $actions The cross-sell actions array.
     *
     * @return array The modified actions array.
     */
    public function add_cross_sell_actions(array $actions): array
    {
        // PDF Invoice suggestion when Corporate Accounts is active.
        if (
            is_plugin_active('memberpress-corporate/main.php') &&
            !is_plugin_active('memberpress-pdf-invoice/main.php')
        ) {
            $actions[] = [
                'id'          => 'install_pdf_invoice',
                'title'       => __('Need B2B invoices?', 'memberpress'),
                'description' => __('Install the PDF Invoice add-on to provide professional invoices for your corporate members.', 'memberpress'),
                'action_url'  => admin_url('admin.php?page=memberpress-addons'),
                'action_text' => __('View Add-ons', 'memberpress'),
                'effort'      => __('Takes ~5 minutes', 'memberpress'),
                'type'        => 'cross_sell',
                'priority'    => 12,
                'applicable'  => true,
                'reach'       => 50,
                'impact'      => 2,
                'confidence'  => 90,
                'effort_score' => 1,
            ];
        }

        // Courses suggestion.
        if (
            MeprPostSetupChecklistHelper::has_membership() &&
            !MeprUtils::is_addon_active(MeprUtils::ADDON_COURSES) &&
            !MeprPostSetupChecklistBrandHelper::has_course()
        ) {
            $actions[] = [
                'id'          => 'install_courses',
                'title'       => __('Want to create online courses?', 'memberpress'),
                'description' => __('MemberPress Courses lets you build and sell courses directly within your membership site.', 'memberpress'),
                'action_url'  => admin_url('admin.php?page=memberpress-addons'),
                'action_text' => __('View Add-ons', 'memberpress'),
                'effort'      => __('Takes ~10 minutes', 'memberpress'),
                'type'        => 'cross_sell',
                'priority'    => 13,
                'applicable'  => true,
                'reach'       => 80,
                'impact'      => 3,
                'confidence'  => 100,
                'effort_score' => 1,
            ];
        }

        return $actions;
    }

    /**
     * Filter the UTM source to use 'memberpress'.
     *
     * @return string The filtered UTM source.
     */
    public function filter_utm_source(): string
    {
        return 'memberpress';
    }

    /**
     * Check if the current license is Basic or Growth edition.
     *
     * @return boolean True if Basic or Growth license.
     */
    private function is_basic_or_growth_license(): bool
    {
        if (!class_exists('MeprOnboardingHelper')) {
            return false;
        }

        $license_type = MeprOnboardingHelper::get_license_type();

        if (!$license_type) {
            return false;
        }

        // Check for Basic (memberpress-basic) or Growth (memberpress-growth) plans.
        $basic_or_growth_slugs = [
            'memberpress-basic',
            'memberpress-growth',
        ];

        return in_array($license_type, $basic_or_growth_slugs, true);
    }

    /**
     * Generate a UTM-tagged URL for tracking.
     *
     * @param string $url         The base URL.
     * @param string $campaign_id The campaign identifier.
     *
     * @return string The URL with UTM parameters.
     */
    private function get_utm_url(string $url, string $campaign_id): string
    {
        $utm_params = [
            'utm_source'   => 'memberpress',
            'utm_medium'   => 'dashboard',
            'utm_campaign' => $campaign_id,
        ];

        return add_query_arg($utm_params, $url);
    }
}
