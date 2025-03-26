<?php

use MemberPress\GroundLevel\Container\Concerns\HasStaticContainer;
use MemberPress\GroundLevel\Container\Container;
use MemberPress\GroundLevel\Container\Contracts\StaticContainerAwareness;
use MemberPress\GroundLevel\InProductNotifications\Service as IPNService;
use MemberPress\GroundLevel\Mothership\Service as MoshService;
use MemberPress\GroundLevel\Support\Concerns\Hookable;
use MemberPress\GroundLevel\Support\Models\Hook;

/**
 * Initializes a GroundLevel container and dependent services.
 */
class MeprGrdLvlCtrl extends MeprBaseCtrl implements StaticContainerAwareness
{
    use HasStaticContainer;
    use Hookable;

    /**
     * Returns an array of Hooks that should be added by the class.
     *
     * @return array
     */
    protected function configureHooks(): array
    {
        return [
            new Hook(Hook::TYPE_ACTION, 'init', __CLASS__ . '::init', 5),
        ];
    }

    /**
     * Loads the hooks for the controller.
     */
    public function load_hooks()
    {
        $this->addHooks();
    }

    /**
     * Initializes a GroundLevel container and dependent services.
     *
     * @param boolean $force_init If true, forces notifications to load even if notifications are disabled.
     *                            Used during database migrations when we cannot determine if the current
     *                            user has access to notifications.
     */
    public static function init(bool $force_init = false): void
    {
        /**
         * Currently we're loading a container, mothership, and ipn services in order
         * to power IPN functionality. We don't need the container or mothership
         * for anything other than IPN so we can skip the whole load if notifications
         * are disabled or unavailable for the user.
         *
         * Later we'll want to move this condition to be only around the {@see self::init_ipn()}
         * load method.
         */
        if (MeprNotifications::has_access() || $force_init) {
            self::setContainer(new Container());

            /**
             * Plugin bootstrap via GrdLvl package.
             *
             * @todo: Later we'll want to "properly" bootstrap a container via a
             */

            self::init_mothership();
            self::init_ipn();
        }
    }

    /**
     * Initializes and configures the IPN Service.
     */
    private static function init_ipn(): void
    {
        // Set IPN Service parameters.
        self::$container->addParameter(IPNService::PRODUCT_SLUG, MEPR_EDITION);
        self::$container->addParameter(IPNService::PREFIX, 'mepr');
        self::$container->addParameter(IPNService::MENU_SLUG, 'memberpress');
        self::$container->addParameter(
            IPNService::USER_CAPABILITY,
            MeprUtils::get_mepr_admin_capability()
        );
        self::$container->addParameter(
            IPNService::RENDER_HOOK,
            'mepr_admin_header_actions'
        );
        self::$container->addParameter(
            IPNService::THEME,
            [
                'primaryColor'       => '#2271b1',
                'primaryColorDarker' => '#0a4b78',
            ]
        );

        self::$container->addService(
            IPNService::class,
            static function (Container $container): IPNService {
                return new IPNService($container);
            },
            true
        );
    }

    /**
     * Initializes the Mothership Service.
     */
    private static function init_mothership(): void
    {
        self::$container->addService(
            MoshService::class,
            static function (Container $container): MoshService {
                return new MoshService(
                    $container,
                    new MeprMothershipPluginConnector()
                );
            },
            true
        );
    }
}
