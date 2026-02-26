<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Insights;

use MemberPress\GroundLevel\Container\Concerns\Configurable;
use MemberPress\GroundLevel\Container\Container;
use MemberPress\GroundLevel\Container\Service as BaseService;
use MemberPress\GroundLevel\Container\Contracts\LoadableDependency;
use MemberPress\GroundLevel\Insights\Services\NetPromoterScore;
use MemberPress\GroundLevel\Insights\Services\RestApi;
use MemberPress\GroundLevel\InProductNotifications\Service as IPNService;
use MemberPress\GroundLevel\Container\Contracts\ConfiguresParameters;
use MemberPress\GroundLevel\InProductNotifications\Services\Store;
use MemberPress\GroundLevel\InProductNotifications\Services\View;

class Insights extends BaseService implements ConfiguresParameters, LoadableDependency
{
    use Configurable;

    /**
     * Container Parameter: The __FILE__ path for this service file.
     */
    public const FILE = 'GRDLVL.INSIGHTS.FILE';

    /**
     * Container Parameter: The grace period in days after installation to show the NPS notification.
     */
    public const GRACE_PERIOD_DAYS = 'GRDLVL.INSIGHTS.GRACE_PERIOD_DAYS';

    /**
     * Container Parameter: The prefix applied to various strings and IDs used by the service.
     */
    public const PREFIX = 'GRDLVL.INSIGHTS.PREFIX';

    /**
     * Container Parameter: The name of the product.
     */
    public const PRODUCT_NAME = 'GRDLVL.INSIGHTS.PRODUCT_NAME';

    /**
     * Container Parameter: The recurrence interval in days for the NPS notification.
     */
    public const RECURRENCE_DAYS = 'GRDLVL.INSIGHTS.RECURRENCE_DAYS';

    /**
     * Container Parameter: The REST API namespace.
     */
    public const REST_NAMESPACE = 'GRDLVL.INSIGHTS.REST_NAMESPACE';

    /**
     * Gets the default parameters for the service.
     *
     * @return array
     */
    public function getDefaultParameters(): array
    {
        return [
            self::PREFIX            => 'grdlvl_insights_',
            self::GRACE_PERIOD_DAYS => 14,
            self::RECURRENCE_DAYS   => 90,
            self::REST_NAMESPACE    => 'grdlvl/insights',
        ];
    }

    /**
     * Loads the dependencies for the service.
     *
     * @param  Container $container The container.
     * @return void
     */
    public function load(Container $container): void
    {
        $container->addParameter(
            self::FILE,
            __FILE__
        );

        $container->addService(
            NetPromoterScore::class,
            function (Container $container): NetPromoterScore {
                return new NetPromoterScore(
                    $container->get(Store::class),
                    $container->get(self::PREFIX),
                    $container->get(self::PRODUCT_NAME),
                    $container->get(self::GRACE_PERIOD_DAYS),
                    $container->get(self::RECURRENCE_DAYS),
                );
            },
            true
        );

        $container->addService(
            RestApi::class,
            function (Container $container): RestApi {
                return new RestApi(
                    $container->get(NetPromoterScore::class),
                    function () use ($container): View {
                        return $container->get(View::class);
                    },
                    $container->get(IPNService::PRODUCT_SLUG),
                    $container->get(self::REST_NAMESPACE),
                    $container->get(IPNService::USER_CAPABILITY),
                    $container->get(self::PREFIX),
                    __FILE__
                );
            },
            true
        );
    }
}
