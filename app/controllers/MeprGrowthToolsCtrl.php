<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprGrowthToolsCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks.
     *
     * @return void
     */
    public function load_hooks()
    {
        if (version_compare(phpversion(), '7.4', '>=') && class_exists('\MemberPress\Caseproof\GrowthTools\App')) {
            $config = new \MemberPress\Caseproof\GrowthTools\Config([
                'parentMenuSlug'   => 'memberpress',
                'instanceId'       => 'memberpress',
                'menuSlug'         => 'memberpress-growth-tools',
                'buttonCSSClasses' => ['mepr-wizard-button-blue'],
            ]);
            new \MemberPress\Caseproof\GrowthTools\App($config);
        }
    }
}
