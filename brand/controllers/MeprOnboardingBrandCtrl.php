<?php

defined('ABSPATH') || exit;

/**
 * Brand controller for onboarding.
 *
 * Registers the onboarding menu item, activation redirect, and proactive support / gateway filters
 * so that onboarding (lived in brand) integrates with the app.
 */
class MeprOnboardingBrandCtrl extends MeprBaseCtrl
{
    /**
     * Load the hooks.
     *
     * @return void
     */
    public function load_hooks(): void
    {
        add_action('mepr_menu_onboarding', [$this, 'add_onboarding_menu']);
        add_filter('mepr_activation_redirect_url', [$this, 'activation_redirect_url']);
        add_filter('mepr_onboarded_option', [$this, 'onboarded_option']);
        add_filter('mepr_onboarding_complete_option', [$this, 'onboarding_complete_option']);
        add_filter('mepr_onboarding_cta_url', [$this, 'onboarding_cta_url']);
        add_filter('mepr_is_onboarding_page', [$this, 'is_onboarding_page']);
        add_filter('mepr_onboarding_admin_page_slug', [$this, 'onboarding_admin_page_slug']);
        add_filter('mepr_onboarding_payment_gateway_redirect_url', [$this, 'onboarding_payment_gateway_redirect_url']);
        add_filter('mepr_onboarding_payment_gateway_option', [$this, 'onboarding_payment_gateway_option']);
        add_filter('mepr_onboarding_payment_step', [$this, 'onboarding_payment_step']);
        add_filter('mepr_migrator_skip_admin_page_slugs', [$this, 'migrator_skip_admin_page_slugs']);
    }

    /**
     * Add the Onboarding submenu when the onboarding controller exists.
     *
     * @param string $capability Required capability.
     *
     * @return void
     */
    public function add_onboarding_menu(string $capability): void
    {
        if (class_exists('MeprOnboardingCtrl')) {
            add_submenu_page(
                'memberpress',
                __('Onboarding', 'memberpress'),
                __('Onboarding', 'memberpress'),
                $capability,
                'memberpress-onboarding',
                'MeprOnboardingCtrl::route'
            );
        }
    }

    /**
     * Redirect to onboarding after activation when not yet onboarded.
     *
     * @param string $url Default redirect URL (empty).
     *
     * @return string
     */
    public function activation_redirect_url(string $url): string
    {
        return admin_url('admin.php?page=memberpress-onboarding');
    }

    /**
     * Option name for "onboarded" (visited onboarding) flag.
     *
     * @param string $option Default option name.
     *
     * @return string
     */
    public function onboarded_option(string $option): string
    {
        return 'mepr_onboarded';
    }

    /**
     * Option name for "onboarding complete" flag.
     *
     * @param string $option Default option name.
     *
     * @return string
     */
    public function onboarding_complete_option(string $option): string
    {
        return 'mepr_onboarding_complete';
    }

    /**
     * CTA URL for proactive support (e.g. failed onboarding email).
     *
     * @param string $url Default URL (empty).
     *
     * @return string
     */
    public function onboarding_cta_url(string $url): string
    {
        return admin_url('admin.php?page=memberpress-onboarding');
    }

    /**
     * Whether the current admin page is the onboarding page.
     *
     * @param boolean $is Whether it is the onboarding page.
     *
     * @return boolean
     */
    public function is_onboarding_page(bool $is): bool
    {
        return $is || (class_exists('MeprOnboardingCtrl') && MeprOnboardingCtrl::is_onboarding_page());
    }

    /**
     * Admin page slug for onboarding (for Stripe/PayPal return URLs).
     *
     * @param string $slug Default slug (empty).
     *
     * @return string
     */
    public function onboarding_admin_page_slug(string $slug): string
    {
        return 'memberpress-onboarding';
    }

    /**
     * Full redirect URL after payment gateway OAuth (Stripe Connect) during onboarding.
     *
     * @param string $url           Default URL (empty).
     * @param string $method_id     Payment method ID.
     * @param string $stripe_action Stripe action query arg (e.g. 'updated').
     *
     * @return string
     */
    public function onboarding_payment_gateway_redirect_url(string $url, string $method_id, string $stripe_action): string
    {
        return add_query_arg([
            'page'          => 'memberpress-onboarding',
            'step'          => '6',
            'stripe-action' => $stripe_action,
        ], admin_url('admin.php'));
    }

    /**
     * Option name for storing the payment gateway chosen during onboarding.
     *
     * @param string $option Default option name.
     *
     * @return string
     */
    public function onboarding_payment_gateway_option(string $option): string
    {
        return 'mepr_onboarding_payment_gateway';
    }

    /**
     * Onboarding step to redirect to after payment gateway OAuth (Stripe Connect).
     *
     * @param string $step Default step from app (e.g. '6').
     *
     * @return string
     */
    public function onboarding_payment_step(string $step): string
    {
        return '6';
    }

    /**
     * Admin page slugs to skip in migrator (onboarding, courses-options).
     *
     * @param array $slugs Default slugs.
     *
     * @return array
     */
    public function migrator_skip_admin_page_slugs(array $slugs): array
    {
        return array_merge($slugs, ['memberpress-onboarding', 'memberpress-courses-options']);
    }
}
