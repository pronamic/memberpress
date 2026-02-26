<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProactiveSupportEventsCtrl extends MeprBaseCtrl
{
    /**
     * Register event hooks for automatic resolution.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('update_option_mepr_onboarding_complete', [$this, 'handle_onboarding_complete'], 10, 2);
        add_action('update_option_' . MEPR_OPTIONS_SLUG, [$this, 'handle_options_updated'], 10, 2);
        add_action('save_post_' . MeprProduct::$cpt, [$this, 'handle_membership_saved'], 10, 3);
        add_action('save_post_' . MeprGroup::$cpt, [$this, 'handle_group_saved'], 10, 3);
        add_action('save_post_' . MeprRule::$cpt, [$this, 'handle_rule_saved'], 10, 3);
        add_action('mepr_txn_store', [$this, 'handle_transaction_stored'], 10, 2);
        add_action('mepr_signup', [$this, 'handle_member_signup'], 10, 1);
    }

    /**
     * Resolve failed onboarding when the onboarding option is updated.
     *
     * @param mixed $old_value Old value.
     * @param mixed $new_value New value.
     *
     * @return void
     */
    public function handle_onboarding_complete($old_value, $new_value): void
    {
        if ($new_value !== '1') {
            return;
        }

        MeprProactiveSupportHelper::maybe_resolve_trigger(
            MeprProactiveSupportHelper::TRIGGER_FAILED_ONBOARDING,
            __('Resolved: onboarding completed.', 'memberpress')
        );
    }

    /**
     * Resolve payment gateway trigger when integrations are added.
     *
     * @param mixed $old_value Old options.
     * @param mixed $new_value New options.
     *
     * @return void
     */
    public function handle_options_updated($old_value, $new_value): void
    {
        $old_integrations = is_array($old_value) ? ($old_value['integrations'] ?? []) : [];
        $new_integrations = is_array($new_value) ? ($new_value['integrations'] ?? []) : [];

        if (count($old_integrations) > 0 || count($new_integrations) === 0) {
            return;
        }

        MeprProactiveSupportHelper::maybe_resolve_trigger(
            MeprProactiveSupportHelper::TRIGGER_PAYMENT_GATEWAY_MISSING,
            __('Resolved: payment gateway configured.', 'memberpress')
        );
    }

    /**
     * Resolve the no memberships trigger on membership publish.
     *
     * Also sets the first_membership_at event timestamp to enable
     * time-based triggers (payment gateway, registration pages, content protection).
     *
     * @param integer $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param boolean $update  Whether this is an update.
     *
     * @return void
     */
    public function handle_membership_saved($post_id, $post, $update): void
    {
        if (!$this->should_process_post($post_id, $post)) {
            return;
        }

        // Set first membership timestamp immediately when membership is published.
        $timestamp = get_post_timestamp($post_id, 'gmt');
        if ($timestamp !== false) {
            MeprProactiveSupportHelper::maybe_store_event_time(
                MeprProactiveSupportHelper::EVENT_FIRST_MEMBERSHIP,
                $timestamp
            );
        }

        MeprProactiveSupportHelper::maybe_resolve_trigger(
            MeprProactiveSupportHelper::TRIGGER_NO_MEMBERSHIPS,
            __('Resolved: membership created.', 'memberpress')
        );
    }

    /**
     * Resolve no registration pages trigger on group publish.
     *
     * @param integer $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param boolean $update  Whether this is an update.
     *
     * @return void
     */
    public function handle_group_saved($post_id, $post, $update): void
    {
        if (!$this->should_process_post($post_id, $post)) {
            return;
        }

        MeprProactiveSupportHelper::maybe_resolve_trigger(
            MeprProactiveSupportHelper::TRIGGER_NO_REGISTRATION_PAGES,
            __('Resolved: registration pages created.', 'memberpress')
        );
    }

    /**
     * Resolve no content protection trigger on rule publish.
     *
     * @param integer $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param boolean $update  Whether this is an update.
     *
     * @return void
     */
    public function handle_rule_saved($post_id, $post, $update): void
    {
        if (!$this->should_process_post($post_id, $post)) {
            return;
        }

        MeprProactiveSupportHelper::maybe_resolve_trigger(
            MeprProactiveSupportHelper::TRIGGER_NO_CONTENT_PROTECTION,
            __('Resolved: content protection rules created.', 'memberpress')
        );
    }

    /**
     * Resolve no transactions trigger when a transaction is stored.
     *
     * @param MeprTransaction $transaction     Transaction object.
     * @param MeprTransaction $old_transaction Previous transaction.
     *
     * @return void
     */
    public function handle_transaction_stored($transaction, $old_transaction): void
    {
        if (!$transaction instanceof MeprTransaction) {
            return;
        }

        MeprProactiveSupportHelper::maybe_resolve_trigger(
            MeprProactiveSupportHelper::TRIGGER_NO_TRANSACTIONS,
            __('Resolved: transaction recorded.', 'memberpress')
        );
    }

    /**
     * Resolve inactive after setup trigger when a member signs up.
     *
     * @param MeprTransaction $transaction Transaction object.
     *
     * @return void
     */
    public function handle_member_signup($transaction): void
    {
        MeprProactiveSupportHelper::maybe_resolve_trigger(
            MeprProactiveSupportHelper::TRIGGER_INACTIVE_AFTER_SETUP,
            __('Resolved: member registration received.', 'memberpress')
        );
    }

    /**
     * Ensure the post is publishable and not an autosave.
     *
     * @param integer $post_id Post ID.
     * @param WP_Post $post    Post object.
     *
     * @return boolean
     */
    private function should_process_post($post_id, $post): bool
    {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return false;
        }

        if (!$post instanceof WP_Post) {
            return false;
        }

        return $post->post_status === 'publish';
    }
}
