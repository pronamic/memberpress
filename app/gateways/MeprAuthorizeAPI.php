<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprAuthorizeAPI
{
    /**
     * The API endpoint for sandbox/test environment.
     *
     * @var string
     */
    public static $sandbox_api_endpoint = 'https://apitest.authorize.net/xml/v1/request.api';

    /**
     * The API endpoint for live/production environment.
     *
     * @var string
     */
    public static $live_api_endpoint    = 'https://api.authorize.net/xml/v1/request.api';

    /**
     * The API endpoint to use for requests.
     *
     * @var string
     */
    private $api_endpoint;

    /**
     * The Authorize.net API login name.
     *
     * @var string
     */
    private $login_name;

    /**
     * The Authorize.net API transaction key.
     *
     * @var string
     */
    private $transaction_key;

    /**
     * Whether to use test mode (sandbox) or not.
     *
     * @var boolean
     */
    private $test_mode;

    /**
     * Constructor for the MeprAuthorizeAPI class.
     *
     * @param array $settings The settings for the Authorize.net API.
     *
     * @return void
     */
    public function __construct($settings = [])
    {
        $this->login_name      = isset($settings->login_name) ? $settings->login_name : '';
        $this->transaction_key = isset($settings->transaction_key) ? $settings->transaction_key : '';
        $this->test_mode       = isset($settings->test_mode) && $settings->test_mode;
        if ($this->test_mode) {
            $this->api_endpoint = self::$sandbox_api_endpoint;
        } else {
            $this->api_endpoint = self::$live_api_endpoint;
        }
    }

    /**
     * Fetch transaction details from the Auth.net API
     *
     * @param  integer $id The transaction ID.
     * @return object|null JSON decoded transaction object. NULL on API error.
     */
    public function get_transaction_details($id)
    {
        return $this->send_request('getTransactionDetailsRequest', ['transId' => $id]);
    }

    /**
     * Send request to the Auth.net api
     *
     * @param  string $type The API request type.
     * @param  array  $args The API request arguments.
     * @return object|null JSON decoded transaction object. NULL on API error.
     */
    public function send_request($type, $args = [])
    {
        $post_body = json_encode(
            [
                $type => [
                    'merchantAuthentication' => [
                        'name'           => $this->login_name,
                        'transactionKey' => $this->transaction_key,
                    ],
                    'transId'                => $args['transId'],
                ],
            ]
        );

        $api_response_body = wp_remote_retrieve_body(wp_remote_post($this->api_endpoint, [
            'body'    => $post_body,
            'headers' => ['content-type' => 'application/json'],
        ]));
        // Authorize.net is sending some garbage at the beginning of the response body that is not valid JSON
        // Reference: https://community.developer.authorize.net/t5/Integration-and-Testing/JSON-issues/td-p/48851.
        $api_response_body = preg_replace('/^[^\{]*/', '', $api_response_body);
        $response_json     = json_decode($api_response_body);

        if ($response_json->messages->resultCode === 'Error') {
            foreach ($response_json->messages->message as $error) {
                MeprUtils::error_log('Authorize API Error ' . $error->code . '-' . $error->text);
            }
            return null;
        } else {
            return $response_json;
        }
    }
}
