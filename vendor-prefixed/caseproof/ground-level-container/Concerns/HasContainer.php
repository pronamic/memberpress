<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Container\Concerns;

use MemberPress\GroundLevel\Container\Container;
use MemberPress\GroundLevel\Container\Contracts\ContainerAwareness;

trait HasContainer
{
    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Retrieves a container.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Sets a container.
     *
     * @param  Container $container The container.
     * @return ContainerAwareness
     */
    public function setContainer(Container $container): ContainerAwareness
    {
        $this->container = $container;
        return $this;
    }
}
