<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

require_once(__DIR__ . '/../gateways/MeprAuthorizeAPI.php');
require_once(__DIR__ . '/../gateways/MeprAuthorizeWebhooks.php');

/**
 * Job to retry Authorize.net transactions.
 *
 * @property string $transaction_data JSON-encoded Authorize.net transaction data.
 * @property object $gateway_settings Gateway settings object.
 * @property boolean $payment_failed Whether this job is for a failed payment (true), or a successful one (false).
 */
class MeprAuthorizeRetryJob extends MeprBaseJob
{
    /**
     * Perform this job.
     *
     * @throws Exception When subscription data is missing or Authorize.net API request fails.
     */
    public function perform()
    {
        $last_transaction = json_decode($this->transaction_data);
        $authorize_api    = new MeprAuthorizeAPI((object)$this->gateway_settings);
        $auth_transaction = $authorize_api->get_transaction_details($last_transaction->transId);

        if (is_object($auth_transaction)) {
            if (!isset($auth_transaction->transaction->subscription)) {
                throw new Exception(__('No subscription data available', 'memberpress'));
            } else {
                $auth_webhook = new MeprAuthorizeWebhooks((object) $this->gateway_settings);

                if ($this->payment_failed) {
                    $auth_webhook->record_payment_failure($auth_transaction->transaction, false);
                } else {
                    $auth_webhook->record_subscription_payment($auth_transaction->transaction, false);
                }
            }
        } else {
            throw new Exception(__('There was a problem with the Authorize.net API request.', 'memberpress'));
        }
    }
}
