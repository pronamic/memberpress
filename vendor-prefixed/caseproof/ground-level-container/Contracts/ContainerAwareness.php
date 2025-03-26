<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Container\Contracts;

use MemberPress\GroundLevel\Container\Container;

interface ContainerAwareness
{
    /**
     * Retrieves a container.
     *
     * @return Container
     */
    public function getContainer(): Container;

    /**
     * Sets a container.
     *
     * @param  Container $container The container.
     * @return ContainerAwareness
     */
    public function setContainer(Container $container): ContainerAwareness;
}
