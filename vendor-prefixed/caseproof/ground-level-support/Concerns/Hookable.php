<?php
/**
 * @license GPL-3.0
 *
 * Modified by Team Caseproof using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace MemberPress\GroundLevel\Support\Concerns;

use MemberPress\GroundLevel\Support\Models\Hook;

/**
 * Trait enabling classes to manage WordPress actions and filters.
 */
trait Hookable
{
    /**
     * An array of configured Hook objects.
     *
     * @var \MemberPress\GroundLevel\Support\Models\Hook[]
     */
    protected array $configuredHooks;

    /**
     * Determines whether or not the configured hooks have been added.
     *
     * @var boolean
     */
    protected bool $hooksAdded = false;

    /**
     * Returns an array of Hooks that should be added by the class.
     *
     * @return array
     */
    abstract protected function configureHooks(): array;

    /**
     * Registers all hooks defined by the class.
     *
     * @param boolean $force Whether or not to force the hooks to be added.
     */
    public function addHooks(bool $force = false): void
    {
        if (! $this->hooksAdded || $force) {
            foreach ($this->getHooks() as $hook) {
                $hook->add();
            }
            $this->hooksAdded = true;
        }
    }

    /**
     * Initializes a hook object.
     *
     * @param  \MemberPress\GroundLevel\Support\Models\Hook|array $hookOrHookArgs The hook args to initialize the hook with.
     *                                                                Or the hook itself, in which case it is returned
     *                                                                as it is.
     * @return \MemberPress\GroundLevel\Support\Models\Hook
     */
    public static function initHook($hookOrHookArgs): Hook
    {
        if (is_a($hookOrHookArgs, Hook::class)) {
            return $hookOrHookArgs;
        }
        return new Hook(...$hookOrHookArgs);
    }

    /**
     * Removes all hooks defined by the class.
     */
    public function removeHooks(): void
    {
        foreach ($this->getHooks() as $hook) {
            $hook->remove();
        }
        $this->hooksAdded = false;
    }

    /**
     * Retrieves a list of hooks defined by the class.
     *
     * @return \MemberPress\GroundLevel\Support\Models\Hook[]
     */
    public function getHooks(): array
    {
        if (! isset($this->configuredHooks)) {
            $this->configuredHooks = array_map(
                [__CLASS__, 'initHook'],
                $this->configureHooks()
            );
        }
        return $this->configuredHooks;
    }
}
