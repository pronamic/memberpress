<?php
/**
 * @license GPL-3.0
 *
 * Modified by Team Caseproof using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace MemberPress\GroundLevel\Mothership\Api\Request;

use MemberPress\GroundLevel\Mothership\Service;
use MemberPress\GroundLevel\Mothership\Api\Request;
use MemberPress\GroundLevel\Mothership\Api\Response;

/**
 * This class is used to interact with the license activations API.
 *
 * @see https://licenses.caseproof.com/docs/api#license-activations
 */
class LicenseActivations
{
    /**
     * Activates the license.
     *
     * @param  string $product    The Product to Activate.
     * @param  string $licenseKey The license key to activate.
     * @param  string $domain     The domain to activate the license on.
     * @return Response The response from the API.
     */
    public static function activate(string $product, string $licenseKey, string $domain): Response
    {
        $data     = compact('domain', 'product');
        $endpoint = 'licenses/' . $licenseKey . '/activate';
        $response = Request::post($endpoint, $data);

        return $response;
    }

    /**
     * Deactivate the license.
     *
     * @param  string $licenseKey The license key to deactivate.
     * @param  string $domain     The domain to deactivate the license on.
     * @return Response The response from the API.
     */
    public static function deactivate(string $licenseKey, string $domain): Response
    {
        $endpoint = 'licenses/' . $licenseKey . '/activations/' . $domain . '/deactivate';
        $response = Request::patch($endpoint, compact('domain'));
        return $response;
    }

    /**
     * Retrieve a license activation.
     *
     * @param  string $licenseKey The license key to retrieve the activation for.
     * @param  string $domain     The domain to retrieve the activation for.
     * @return Response The response from the API.
     */
    public static function retrieveLicenseActivation(string $licenseKey, string $domain): Response
    {
        $endpoint = 'licenses/' . $licenseKey . '/activations/' . $domain;
        $response = Request::get($endpoint);
        return $response;
    }

    /**
     * List all activations for a license.
     *
     * @param  string $licenseKey The license key to list activations for.
     * @return Response The response from the API.
     */
    public static function list(string $licenseKey): Response
    {
        $endpoint = 'licenses/' . $licenseKey . '/activations';
        $response = Request::get($endpoint);
        return $response;
    }
}
