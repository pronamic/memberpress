<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Container\Concerns;

use MemberPress\GroundLevel\Container\Container;

trait HasStaticContainer
{
    /**
     * The static container instance.
     *
     * @var \MemberPress\GroundLevel\Container\Container
     */
    protected static Container $container;

    /**
     * Retrieves a container.
     *
     * @return \MemberPress\GroundLevel\Container\Container
     */
    public static function getContainer(): Container
    {
        return static::$container;
    }

    /**
     * Sets a container.
     *
     * @param \MemberPress\GroundLevel\Container\Container $container The container.
     */
    public static function setContainer(Container $container): void
    {
        static::$container = $container;
    }
}
