<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprDrmAppFee
{
    /**
     * Applies the application fee to the relevant transactions.
     *
     * @return void
     */
    public function do_app_fee()
    {
        MeprDrmHelper::enable_app_fee();

        $this->schedule_event_app_fee();
    }

    /**
     * Schedules an event to apply the application fee.
     *
     * @return void
     */
    private function schedule_event_app_fee()
    {
        if (! wp_next_scheduled('mepr_drm_app_fee_mapper', [false])) {
            wp_schedule_event(time(), 'mepr_drm_ten_minutes', 'mepr_drm_app_fee_mapper', [false]);
        }
    }

    /**
     * Reverses the application fee from transactions.
     *
     * @return void
     */
    public function undo_app_fee()
    {
        if (! MeprDrmHelper::is_app_fee_enabled()) {
            return;
        }

        MeprDrmHelper::disable_app_fee();

        $this->schedule_event_app_fee_reversal();
    }

    /**
     * Schedules an event to reverse the application fee.
     *
     * @return void
     */
    private function schedule_event_app_fee_reversal()
    {
        if (! wp_next_scheduled('mepr_drm_app_fee_reversal', [false])) {
            wp_schedule_event(time(), 'mepr_drm_ten_minutes', 'mepr_drm_app_fee_reversal', [false]);
        }
    }

    /**
     * Revises the application fee for transactions.
     *
     * @return void
     */
    public function do_app_fee_revision()
    {
        if (! wp_next_scheduled('mepr_drm_app_fee_revision', [false])) {
            wp_schedule_event(time(), 'daily', 'mepr_drm_app_fee_revision', [false]);
        }
    }

    /**
     * Initializes cron jobs for application fee processing.
     *
     * @return void
     */
    public function init_crons()
    {
        $this->schedule_event_app_fee();
        $this->do_app_fee_revision();
    }

    /**
     * Retrieves Stripe connected payment methods.
     *
     * @return array List of connected payment methods
     */
    private function get_stripe_connected_payment_methods()
    {
        $mepr_options = MeprOptions::fetch();
        $pmt_methods  = $mepr_options->payment_methods();
        $methods      = [];

        foreach ($pmt_methods as $key => $method) {
            if ($method instanceof MeprStripeGateway && MeprStripeGateway::is_stripe_connect($key)) {
                $methods[] = $key;
            }
        }

        return $methods;
    }

    /**
     * Retrieves all active subscriptions.
     *
     * @param  array   $params An array of parameters to filter the subscriptions.
     * @param  integer $limit  The maximum number of subscriptions to retrieve.
     * @param  boolean $count  A boolean indicating whether to return a count of subscriptions.
     * @return array|integer List of active subscriptions or count of subscriptions
     */
    public function get_all_active_subs($params = [], $limit = '30', $count = false)
    {
        global $wpdb;

        $payment_methods = $this->get_stripe_connected_payment_methods();
        if (empty($payment_methods) || !is_array($payment_methods)) {
            return -1; // Nothing to process.
        }

        $mepr_db = new MeprDb();

        $limit  = empty($limit) ? '' : " LIMIT {$limit}";
        $fields = $count ? 'COUNT(*)' : 'sub.gateway, sub.id, sub.subscr_id, sub.price';

        $sql = "
      SELECT {$fields}
        FROM {$mepr_db->subscriptions} AS sub
          JOIN {$mepr_db->transactions} AS t
            ON sub.id = t.subscription_id
    ";

        if (isset($params['mepr_app_fee_not_applied']) && true === $params['mepr_app_fee_not_applied']) {
            $sql .= "
        LEFT JOIN {$mepr_db->subscription_meta} AS sm
            ON sub.id = sm.subscription_id
            AND sm.meta_key = 'application_fee_percent'
      ";
        }

        if (
            ( isset($params['mepr_app_not_fee_version']) && true === $params['mepr_app_not_fee_version'] ) ||
            ( isset($params['mepr_app_fee_applied']) && true === $params['mepr_app_fee_applied'] )
        ) {
            $sql .= "
        JOIN {$mepr_db->subscription_meta} AS sm
            ON sub.id = sm.subscription_id
            AND sm.meta_key = 'application_fee_version'
      ";
        }

        $sql .= "
      WHERE t.status IN(%s,%s)
          AND sub.status = %s
          AND sub.gateway IN ('" . implode("','", $payment_methods) . "')
    ";

        $sql .= "
        AND t.expires_at > %s AND t.expires_at != '0000-00-00 00:00:00'
    ";

        if (isset($params['mepr_app_fee_not_applied']) && true === $params['mepr_app_fee_not_applied']) {
            $sql .= '
        AND sm.subscription_id is NULL
      ';
        }

        if (isset($params['mepr_app_not_fee_version']) && true === $params['mepr_app_not_fee_version']) {
            $sql .= '
        AND sm.meta_value != %s
      ';
        }

        $sql .= '
      ORDER BY t.expires_at ASC
    ';

        if (isset($params['mepr_app_not_fee_version']) && true === $params['mepr_app_not_fee_version']) {
            $sql = $wpdb->prepare($sql, MeprTransaction::$complete_str, MeprTransaction::$confirmed_str, MeprSubscription::$active_str, MeprUtils::db_now(), $params['drm_fee_api_version']);
        } else {
            $sql = $wpdb->prepare($sql, MeprTransaction::$complete_str, MeprTransaction::$confirmed_str, MeprSubscription::$active_str, MeprUtils::db_now());
        }

        $sql .= "
      {$limit}
    ";

        if ($count) {
            return $wpdb->get_var($sql);
        } else {
            return $wpdb->get_results($sql);
        }
    }

    /**
     * Processes the application fee for subscriptions.
     *
     * @param  array   $subscriptions      An array of subscription objects to process.
     * @param  string  $api_version        The version of the API to use.
     * @param  float   $current_percentage The current percentage of the application fee.
     * @param  boolean $delete_metadata    A boolean indicating whether to delete metadata.
     * @return integer|void Number of updated subscriptions
     */
    public function process_subscriptions_fee($subscriptions, $api_version, $current_percentage, $delete_metadata = false)
    {

        if (! is_array($subscriptions) || empty($subscriptions)) {
            return;
        }

        $mepr_db      = new MeprDb();
        $mepr_options = MeprOptions::fetch();

        $updated = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $pm            = $mepr_options->payment_method($subscription->gateway);
                $json_response = $pm->send_stripe_request('subscriptions/' . $subscription->subscr_id, [
                    'application_fee_percent' => $current_percentage,
                ], 'post');

                if (is_array($json_response) && ! empty($json_response)) {
                      $updated++;

                    if ($delete_metadata) {
                        $mepr_db->delete_metadata($mepr_db->subscription_meta, 'subscription_id', $subscription->id, 'application_fee_percent');
                        $mepr_db->delete_metadata($mepr_db->subscription_meta, 'subscription_id', $subscription->id, 'application_fee_version');
                        $mepr_db->update_metadata($mepr_db->subscription_meta, 'subscription_id', $subscription->id, 'application_fee_revoked', MeprUtils::db_now());
                    } else {
                        $mepr_db->update_metadata($mepr_db->subscription_meta, 'subscription_id', $subscription->id, 'application_fee_percent', $current_percentage);
                        $mepr_db->update_metadata($mepr_db->subscription_meta, 'subscription_id', $subscription->id, 'application_fee_version', $api_version);
                    }
                }
            } catch (Exception $ex) {
            }
        }

        return $updated;
    }
}
