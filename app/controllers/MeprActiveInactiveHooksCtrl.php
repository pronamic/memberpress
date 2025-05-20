<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Used for our autoresponder add-ons - So we can have the active/inactive logic all in one place
 *
 * @since 1.7.3
 */
class MeprActiveInactiveHooksCtrl extends MeprBaseCtrl
{
    /**
     * Loads the hooks.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('mepr-txn-store', [$this, 'handle_txn_store'], 99, 2);
        add_action('mepr-txn-expired', [$this, 'handle_txn_expired'], 11, 2);
        add_action('delete_user', [$this, 'handle_delete_user']);
    }

    /**
     * Handles the transaction store.
     *
     * @param  MeprTransaction $txn     The transaction.
     * @param  MeprTransaction $old_txn The old transaction.
     * @return void
     */
    public function handle_txn_store($txn, $old_txn)
    {
        // Already been here?
        if ($old_txn->status == $txn->status) {
            return;
        }

        // Allow third party plugins to stop the running of the method.
        if (MeprHooks::apply_filters('mepr-active-inactive-hooks-skip', false, $txn)) {
            return;
        }

        // Bail if no id's.
        if (!isset($txn->id) || $txn->id <= 0 || !isset($txn->user_id) || $txn->user_id <= 0) {
            return;
        }

        // Ignore "pending" txns.
        if (!isset($txn->status) || empty($txn->status) || $txn->status == MeprTransaction::$pending_str) {
            return;
        }

        $active_status = [MeprTransaction::$complete_str, MeprTransaction::$confirmed_str];
        $now           = time();
        $expires       = 0; // Lifetime.

        if (! empty($txn->expires_at) && $txn->expires_at != MeprUtils::db_lifetime()) {
            $expires = strtotime($txn->expires_at);
        }

        if (in_array($txn->status, $active_status)) {
            if ($expires === 0 || $expires >= $now) {
                MeprHooks::do_action('mepr-account-is-active', $txn);
            } else {
                MeprHooks::do_action('mepr-account-is-inactive', $txn);
            }
        } else {
            MeprHooks::do_action('mepr-account-is-inactive', $txn);
        }
    }

    /**
     * Handles the transaction expired.
     *
     * @param  MeprTransaction $txn        The transaction.
     * @param  boolean         $sub_status The subscription status.
     * @return void
     */
    public function handle_txn_expired($txn, $sub_status = false)
    {
        global $wpdb;

        // Part of an Enabled subscription, so let's bail.
        if ($sub_status == MeprSubscription::$active_str) {
            return;
        }

        // Allow third party plugins to stop the running of the method.
        if (MeprHooks::apply_filters('mepr-active-inactive-hooks-skip', false, $txn)) {
            return;
        }

        // Bail if no id's.
        if (!isset($txn->id) || $txn->id <= 0 || !isset($txn->user_id) || $txn->user_id <= 0) {
            return;
        }

        // Go directly to the database and maybe flush caches beforehand.
        if (MeprHooks::apply_filters('mepr-autoresponder-flush-caches', true)) {
            wp_cache_flush();
            $wpdb->flush();
        }

        $query = $wpdb->prepare(
            "SELECT count(*) FROM {$wpdb->prefix}mepr_transactions WHERE user_id = %d AND product_id = %d AND status IN (%s, %s) AND (expires_at >= %s OR expires_at = %s)",
            $txn->user_id,
            $txn->product_id,
            MeprTransaction::$complete_str,
            MeprTransaction::$confirmed_str,
            MeprUtils::db_now(),
            MeprUtils::db_lifetime()
        );

        $active_on_membership = $wpdb->get_var($query);

        if ($active_on_membership) {
            MeprHooks::do_action('mepr-account-is-active', $txn);
        } else {
            MeprHooks::do_action('mepr-account-is-inactive', $txn);
        }
    }

    /**
     * Handles the delete user.
     *
     * @param  integer $user_id The user ID.
     * @return void
     */
    public function handle_delete_user($user_id)
    {
        $user         = new MeprUser($user_id);
        $transactions = (array) $user->active_product_subscriptions('transactions', true, true);
        foreach ($transactions as $transaction) {
            do_action('mepr-account-is-inactive', $transaction);
        }
    }
}
