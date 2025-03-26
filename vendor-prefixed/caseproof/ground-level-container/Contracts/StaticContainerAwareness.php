<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Container\Contracts;

use MemberPress\GroundLevel\Container\Container;

interface StaticContainerAwareness
{
    /**
     * Retrieves a container.
     *
     * @return Container
     */
    public static function getContainer(): Container;

    /**
     * Sets a container.
     *
     * @param Container $container The container.
     */
    public static function setContainer(Container $container): void;
}
