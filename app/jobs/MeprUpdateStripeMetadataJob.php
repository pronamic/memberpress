<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprUpdateStripeMetadataJob extends MeprBaseJob
{
    /**
     * Update Stripe Metadata for a Transaction
     *
     * @throws Exception When transaction_id is not set.
     **/
    public function perform()
    {
        MeprUtils::debug_log('** Performing Update Stripe TXN Metadata');
        if (!isset($this->transaction_id) || empty($this->transaction_id)) {
            throw new Exception(sprintf(__('MeprUpdateStripeMetadataJob: transaction_id wasn\'t set.', 'memberpress'), $this->transaction_id));
        }

        $txn = new MeprTransaction($this->transaction_id);
        $prd = $txn->product();

        $args = MeprHooks::apply_filters('mepr_stripe_update_transaction_metadata_args', [
            'metadata' => [
                'platform'              => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
                'transaction_id'        => $txn->id,
                'site_url'              => get_site_url(),
                'memberpress_product'    => $prd->post_title,
                'memberpress_product_id' => $prd->ID,
            ],
        ], $txn);

        $this->gateway_settings = (object)$this->gateway_settings;
        $gateway                = new MeprStripeGateway();
        $gateway->load($this->gateway_settings);
        $gateway->send_stripe_request("charges/{$txn->trans_num}", $args);

        MeprUtils::debug_log("** Updated Stripe TXN: {$txn->trans_num} | {$txn->id}");
    }
}
