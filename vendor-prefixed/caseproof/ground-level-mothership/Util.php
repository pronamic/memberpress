<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Mothership;

use MemberPress\GroundLevel\Container\Concerns\HasStaticContainer;
use MemberPress\GroundLevel\Container\Contracts\StaticContainerAwareness;
use MemberPress\GroundLevel\Support\Str;

class Util implements StaticContainerAwareness
{
    use HasStaticContainer;

    /**
     * Composes a constant name by combining a prefix and a name.
     *
     * The prefix is ensured to end with an underscore, and the resulting constant name
     * is converted to uppercase with hyphens, periods, and spaces replaced by underscores.
     *
     * @param  string $name The name to be appended to the prefix.
     * @return string The composed constant name.
     */
    public static function composeConstantName(string $name): string
    {
        $prefix = self::getContainer()->get(AbstractPluginConnection::class)->pluginPrefix;
        $prefix = '_' === substr($prefix, -1) ? $prefix : $prefix . '_';

        return Str::toConstantCase($prefix . $name);
    }
}
