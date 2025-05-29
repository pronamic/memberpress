<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprPowerPressIntegration
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter('powerpress_admin_capabilities', [$this,'powerpress_caps']);
    }

    /**
     * Add MemberPress capabilities to PowerPress
     *
     * @param  array $caps Array of capabilities.
     * @return array Modified array of capabilities
     */
    public function powerpress_caps($caps)
    {
        $products = MeprCptModel::all('MeprProduct');
        $rules    = MeprCptModel::all('MeprRule');

        $caps['mepr-active'] = __('MemberPress Active Member', 'memberpress');

        // Add Dynamic MemberPress product capabilities into the mix.
        if (!empty($products)) {
            foreach ($products as $product) {
                $caps["mepr-membership-auth-{$product->ID}"] = sprintf(
                    // Translators: %s: product name.
                    __('MemberPress Membership: %s', 'memberpress'),
                    $product->post_title
                );
            }
        }

        // Add Dynamic MemberPress rule capabilities into the mix.
        if (!empty($rules)) {
            foreach ($rules as $rule) {
                $caps["mepr-rule-auth-{$rule->ID}"] = sprintf(
                    // Translators: %s: rule name.
                    __('MemberPress Rule: %s', 'memberpress'),
                    $rule->post_title
                );
            }
        }

        return $caps;
    }
}

new MeprPowerPressIntegration();
