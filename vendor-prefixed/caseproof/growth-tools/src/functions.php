<?php
/**
 * @license GPL-3.0
 *
 * Modified by caseproof on 22-January-2024 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace MemberPress\Caseproof\GrowthTools;

/**
 * Returns the main instance of the plugin.
 *
 * @param  array $config Config data.
 * @return MemberPress\Caseproof\GrowthTools\Bootstrap
 */
function instance(array $config = []): App
{
    static $instance = null;

    if (is_null($instance)) {
        $instance = new App(new Config($config));
    }

    return $instance;
}
