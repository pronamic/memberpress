<?php
/**
 * @license GPL-3.0
 *
 * Modified by Team Caseproof using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace MemberPress\GroundLevel\Mothership\Api;

use MemberPress\GroundLevel\Mothership\Credentials;
use MemberPress\GroundLevel\Mothership\Api\Response;
use MemberPress\GroundLevel\Mothership\Api\PaginatedResponse;
use MemberPress\GroundLevel\Mothership\Service as MothershipService;
use MemberPress\GroundLevel\Container\Concerns\HasStaticContainer;
use MemberPress\GroundLevel\Container\Contracts\StaticContainerAwareness;

/**
 * Request class for the API. This returns the Response object.
 */
class Request implements StaticContainerAwareness
{
    use HasStaticContainer;

    /**
     * Perform a GET request.
     *
     * @param  string $endpoint The API endpoint to request.
     * @param  array  $params   The query parameters to add to the URL.
     * @return Response
     */
    public static function get(string $endpoint, array $params = []): Response
    {
        if (!empty($params)) {
            $endpoint = add_query_arg($params, $endpoint);
        }
        return self::makeRequest('GET', $endpoint);
    }

    /**
     * Perform a POST request.
     *
     * @param  string $endpoint The API endpoint to request.
     * @param  array  $body     The body of the request.
     * @return Response
     */
    public static function post(string $endpoint, array $body = []): Response
    {
        return self::makeRequest('POST', $endpoint, $body);
    }

    /**
     * Perform a PATCH request.
     *
     * @param  string $endpoint The API endpoint to request.
     * @param  array  $body     The body of the request.
     * @return Response
     */
    public static function patch(string $endpoint, array $body = []): Response
    {
        return self::makeRequest('PATCH', $endpoint, $body);
    }

    /**
     * Perform a PUT request.
     *
     * @param  string $endpoint The API endpoint to request.
     * @param  array  $body     The body of the request.
     * @return Response
     */
    public static function put(string $endpoint, array $body = []): Response
    {
        return self::makeRequest('PUT', $endpoint, $body);
    }

    /**
     * Perform a DELETE request.
     *
     * @param  string $endpoint The API endpoint to request.
     * @param  array  $body     The body of the request.
     * @return Response
     */
    public static function delete(string $endpoint, array $body = []): Response
    {
        return self::makeRequest('DELETE', $endpoint, $body);
    }

    /**
     * Make an HTTP request.
     *
     * @param  string $method   The HTTP method to use.
     * @param  string $endpoint The API endpoint to request.
     * @param  array  $body     The body of the request.
     * @return Response
     */
    private static function makeRequest(string $method, string $endpoint, array $body = []): Response
    {

        $url = self::getContainer()->get(MothershipService::class)->getApiBaseUrl() . ltrim($endpoint, '/');
        try {
            $args = [
                'method'  => $method,
                'headers' => self::getAuthHeaders(),
            ];

            if (!empty($body)) {
                $args['body']                    = wp_json_encode($body);
                $args['data_format']             = 'body';
                $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
                $args['headers']['Accept']       = 'application/json';
            }
        } catch (\Exception $e) {
            return new Response(null, $e->getMessage());
        }

        $response = wp_remote_request($url, $args);
        return self::handleResponse($response);
    }

    /**
     * Get authentication headers.
     *
     * @return array The authentication headers.
     */
    protected static function getAuthHeaders(): array
    {
        $headers = [];

        $licenseKey       = Credentials::getLicenseKey();
        $activationDomain = Credentials::getActivationDomain();
        if ($licenseKey && $activationDomain) {
            return [
                'Authorization' => 'Basic ' . base64_encode("$activationDomain:$licenseKey"),
            ];
        }

        $email    = Credentials::getEmail();
        $apiToken = Credentials::getApiToken();
        if ($email && $apiToken) {
            // Email/API Token authentication.
            if (!empty(self::getContainer()->get(MothershipService::class)->getProxyLicenseKey())) {
                $headers['X-Proxy-License-Key'] = self::getContainer()->get(MothershipService::class)->getProxyLicenseKey();
            }
            $headers['Authorization'] = 'Basic ' . base64_encode("$email:$apiToken");
        }
        return $headers;
    }

    /**
     * Handle the API response.
     *
     * @param  mixed $response The response from the API.
     * @return Response
     */
    protected static function handleResponse($response): Response
    {
        if (is_wp_error($response)) {
            return self::handleWpError($response);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        $responseCode = wp_remote_retrieve_response_code($response);

        if (!self::isSuccessfulResponse($responseCode, $data)) {
            return self::handleErrorResponse($data, $responseCode);
        }

        if (self::isPaginatedResponse($data)) {
            return new PaginatedResponse($data);
        }

        return new Response($data);
    }

    /**
     * Handle a WP_Error.
     *
     * @param  \WP_Error $response The response from the API.
     * @return Response
     */
    protected static function handleWpError($response): Response
    {
        $errorDetails = 'WP_Error : ';
        if (isset($response->errors)) {
            $index = 0;
            foreach ($response->errors as $key => $error) {
                $errorDetails .= sprintf(
                    '%d. %s ',
                    $index + 1,
                    implode(', ', $error)
                );
                ++$index;
            }
        }
        return new Response(null, $errorDetails);
    }

    /**
     * Check if the response is successful.
     *
     * @param  integer $responseCode The response code from the API.
     * @param  mixed   $data         The response data from the API.
     * @return boolean
     */
    protected static function isSuccessfulResponse(int $responseCode, $data): bool
    {
        return ($responseCode >= 200 && $responseCode <= 299) && !isset($data->errors);
    }

    /**
     * Handle an error response.
     *
     * @param  mixed   $data         The response data from the API.
     * @param  integer $responseCode The response code from the API.
     * @return Response
     */
    protected static function handleErrorResponse($data, int $responseCode): Response
    {
        // Build the error details in a numbered list if they exist.
        $errorDetails = '';
        if (isset($data->errors)) {
            $index = 0;
            foreach ($data->errors as $error) {
                $errorDetails .= sprintf(
                    '%d. %s ',
                    $index + 1,
                    implode(', ', $error)
                );
                ++$index;
            }
        }
        return new Response(
            null,
            $data->message ?? $responseCode,
            $responseCode,
            $data->errors ?? []
        );
    }

    /**
     * Check if the response is paginated.
     *
     * @param  mixed $data The response data from the API.
     * @return boolean
     */
    protected static function isPaginatedResponse($data): bool
    {
        return isset($data->links) && (isset($data->links->next) || isset($data->links->prev));
    }
}
