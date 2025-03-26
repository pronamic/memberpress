<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Mothership\Api\Request;

use MemberPress\GroundLevel\Mothership\Api\Request;
use MemberPress\GroundLevel\Mothership\Api\Response;

/**
 * This class is used to interact with the licenses API.
 *
 * @see https://licenses.caseproof.com/docs/api#licenses
 */
class Licenses
{
    /**
     * Create a new license.
     *
     * @param  array $licenseData The data to create the license.
     * @return Response
     */
    public static function create(array $licenseData): Response
    {
        return Request::post('licenses', $licenseData);
    }

    /**
     * Get all licenses.
     *
     * @return Response
     */
    public static function list(): Response
    {
        return Request::get('licenses');
    }

    /**
     * Get a license by license key.
     *
     * @param  string $licenseKey The license key.
     * @return Response
     */
    public static function get(string $licenseKey): Response
    {
        return Request::get('licenses/' . $licenseKey);
    }
}
