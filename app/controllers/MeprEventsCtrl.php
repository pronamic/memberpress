<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * This will pick up some of the hook based events, more in-depth
 * processing of certain events and event cleanup maintenance tasks.
 */
class MeprEventsCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for event handling.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('user_register', [$this, 'user_register']);
        add_action('delete_user', [$this, 'delete_user']);
        add_action('mepr-txn-expired', [$this, 'txn_expired'], 10, 2);
        add_filter('mepr_create_subscription', [$this, 'sub_created']);
    }

    /**
     * Record an event when a user registers.
     *
     * @param integer $user_id The ID of the registered user.
     *
     * @return void
     */
    public function user_register($user_id)
    {
        if (!empty($user_id)) {
            MeprEvent::record('member-added', (new MeprUser($user_id)));
        }
    }

    /**
     * Record an event when a user is deleted.
     *
     * @param integer $user_id The ID of the user to be deleted.
     *
     * @return void
     */
    public function delete_user($user_id)
    {
        if (!empty($user_id)) {
            // Since the 'delete_user' action fires just before the user is deleted
            // we should still have access to the full MeprUser object for them.
            MeprEvent::record('member-deleted', (new MeprUser($user_id)));
        }
    }

    /**
     * Handle the transaction expired event and send appropriate events.
     * Let's figure some stuff out from the txn-expired hook yo ... and send some proper events
     *
     * @param MeprTransaction $txn        The transaction object.
     * @param boolean         $sub_status The subscription status.
     *
     * @return void
     */
    public function txn_expired($txn, $sub_status)
    {
        // Assume the txn is expired (otherwise this action wouldn't fire)
        // Then ensure the subscription is expired before sending a sub expired event.
        if (
            !empty($txn) &&
            $txn instanceof MeprTransaction &&
            (int)$txn->subscription_id > 0
        ) {
            $sub = $txn->subscription();
            if ($sub && $sub->status == MeprSubscription::$cancelled_str && $sub->is_expired()) {
                MeprEvent::record('subscription-expired', $sub, $txn);
            }
        }
    }

    /**
     * Record an event when a subscription is created.
     *
     * @param integer $sub_id The ID of the created subscription.
     *
     * @return integer The subscription ID.
     */
    public function sub_created($sub_id)
    {
        $sub = new MeprSubscription($sub_id);
        MeprEvent::record('subscription-created', $sub);
        return $sub_id;
    }
}
