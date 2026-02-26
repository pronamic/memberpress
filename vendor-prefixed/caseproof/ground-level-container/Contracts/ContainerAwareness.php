<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Container\Contracts;

use MemberPress\GroundLevel\Container\Container;

interface ContainerAwareness
{
    /**
     * Retrieves a container.
     *
     * @return \MemberPress\GroundLevel\Container\Container
     */
    public function getContainer(): Container;

    /**
     * Sets a container.
     *
     * @param  \MemberPress\GroundLevel\Container\Container $container The container.
     * @return \MemberPress\GroundLevel\Container\Contracts\ContainerAwareness
     */
    public function setContainer(Container $container): ContainerAwareness;
}
