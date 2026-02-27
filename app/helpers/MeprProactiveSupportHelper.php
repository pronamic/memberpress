<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProactiveSupportHelper
{
    private const GLOBAL_OPT_OUT_OPTION       = 'mepr_proactive_support_opt_out_all';
    private const ADMIN_OPT_OUT_OPTION        = 'mepr_proactive_support_opted_out';
    private const LEGACY_ADMIN_OPT_OUT_OPTION = 'mepr-proactive-support-opted-out';
    private const EVENTS_OPTION               = 'mepr_proactive_support_events';
    private const BATCH_OFFSET_OPTION         = 'mepr_proactive_batch_offset';
    public const EVENT_ONBOARDING_COMPLETE    = 'onboarding_completed_at';
    public const EVENT_FIRST_MEMBERSHIP       = 'first_membership_at';
    public const EVENT_GATEWAY_CONFIGURED     = 'gateway_configured_at';
    public const EVENT_GO_LIVE                = 'go_live_at';

    public const TRIGGER_FAILED_ONBOARDING       = 'failed_onboarding';
    public const TRIGGER_NO_MEMBERSHIPS          = 'no_memberships';
    public const TRIGGER_PAYMENT_GATEWAY_MISSING = 'payment_gateway_missing';
    public const TRIGGER_NO_REGISTRATION_PAGES   = 'no_registration_pages';
    public const TRIGGER_NO_CONTENT_PROTECTION   = 'no_content_protection';
    public const TRIGGER_NO_TRANSACTIONS         = 'no_transactions';
    public const TRIGGER_INACTIVE_AFTER_SETUP    = 'inactive_after_setup';

    /**
     * Get the proactive support database table name.
     *
     * @return string
     */
    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'mepr_proactive_support';
    }

    /**
     * Return trigger configuration.
     *
     * @return array
     */
    public static function get_triggers(): array
    {
        return [
            self::TRIGGER_FAILED_ONBOARDING       => [
                'label'    => __('Failed Onboarding', 'memberpress'),
                'delay'    => DAY_IN_SECONDS * 3,
                'risk'     => __('Critical', 'memberpress'),
                'template' => 'failed-onboarding',
            ],
            self::TRIGGER_NO_MEMBERSHIPS          => [
                'label'    => __('No Memberships Created', 'memberpress'),
                'delay'    => DAY_IN_SECONDS * 7,
                'risk'     => __('High', 'memberpress'),
                'template' => 'no-memberships',
            ],
            self::TRIGGER_PAYMENT_GATEWAY_MISSING => [
                'label'    => __('Payment Gateway Missing', 'memberpress'),
                'delay'    => DAY_IN_SECONDS * 5,
                'risk'     => __('Critical', 'memberpress'),
                'template' => 'payment-gateway',
            ],
            self::TRIGGER_NO_REGISTRATION_PAGES   => [
                'label'    => __('No Registration/Pricing Pages', 'memberpress'),
                'delay'    => DAY_IN_SECONDS * 10,
                'risk'     => __('High', 'memberpress'),
                'template' => 'no-registration-pages',
            ],
            self::TRIGGER_NO_CONTENT_PROTECTION   => [
                'label'    => __('No Content Protection Rules', 'memberpress'),
                'delay'    => DAY_IN_SECONDS * 14,
                'risk'     => __('Medium', 'memberpress'),
                'template' => 'no-content-protection',
            ],
            self::TRIGGER_NO_TRANSACTIONS         => [
                'label'    => __('No Transactions Recorded', 'memberpress'),
                'delay'    => DAY_IN_SECONDS * 7,
                'risk'     => __('High', 'memberpress'),
                'template' => 'no-transactions',
            ],
            self::TRIGGER_INACTIVE_AFTER_SETUP    => [
                'label'    => __('Inactive After Setup', 'memberpress'),
                'delay'    => DAY_IN_SECONDS * 21,
                'risk'     => __('Medium', 'memberpress'),
                'template' => 'inactive-after-setup',
            ],
        ];
    }

    /**
     * Evaluate if a trigger condition has been met.
     *
     * @param  string $trigger Trigger key.
     * @return array
     */
    public static function evaluate_trigger($trigger): array
    {
        $now     = time();
        $results = [
            'met'     => false,
            'ready'   => false,
            'context' => self::base_context(),
        ];

        switch ($trigger) {
            case self::TRIGGER_FAILED_ONBOARDING:
                $installed_at = self::get_install_timestamp();
                $complete     = self::is_onboarding_complete();
                if ($installed_at === 0 || $complete) {
                    return $results;
                }
                $results['ready']              = ($now - $installed_at) >= self::get_triggers()[self::TRIGGER_FAILED_ONBOARDING]['delay'];
                $results['met']                = $results['ready'];
                $results['context']['cta_url'] = admin_url('admin.php?page=memberpress-onboarding');
                break;

            case self::TRIGGER_NO_MEMBERSHIPS:
                if (!self::is_onboarding_complete()) {
                    return $results;
                }
                $onboarded_at = self::get_event_time(self::EVENT_ONBOARDING_COMPLETE);
                if ($onboarded_at === 0) {
                    $onboarded_at = time();
                }
                $results['ready']              = ($now - $onboarded_at) >= self::get_triggers()[self::TRIGGER_NO_MEMBERSHIPS]['delay'];
                $results['met']                = $results['ready'] && self::membership_count() === 0;
                $results['context']['cta_url'] = admin_url('post-new.php?post_type=' . MeprProduct::$cpt);
                break;

            case self::TRIGGER_PAYMENT_GATEWAY_MISSING:
                $first_membership = self::get_first_membership_timestamp();
                if ($first_membership === 0) {
                    return $results;
                }
                $results['ready']              = ($now - $first_membership) >= self::get_triggers()[self::TRIGGER_PAYMENT_GATEWAY_MISSING]['delay'];
                $results['met']                = $results['ready'] && !self::has_payment_gateway();
                $results['context']['cta_url'] = admin_url('admin.php?page=memberpress-options#mepr-integration');
                break;

            case self::TRIGGER_NO_REGISTRATION_PAGES:
                $first_membership = self::get_first_membership_timestamp();
                if ($first_membership === 0) {
                    return $results;
                }
                $results['ready']              = ($now - $first_membership) >= self::get_triggers()[self::TRIGGER_NO_REGISTRATION_PAGES]['delay'];
                $results['met']                = $results['ready'] && self::group_count() === 0;
                $results['context']['cta_url'] = admin_url('edit.php?post_type=' . MeprGroup::$cpt);
                break;

            case self::TRIGGER_NO_CONTENT_PROTECTION:
                $first_membership = self::get_first_membership_timestamp();
                if ($first_membership === 0) {
                    return $results;
                }
                $results['ready']              = ($now - $first_membership) >= self::get_triggers()[self::TRIGGER_NO_CONTENT_PROTECTION]['delay'];
                $results['met']                = $results['ready'] && self::rule_count() === 0;
                $results['context']['cta_url'] = admin_url('edit.php?post_type=' . MeprRule::$cpt);
                break;

            case self::TRIGGER_NO_TRANSACTIONS:
                if (!self::has_payment_gateway()) {
                    return $results;
                }
                $gateway_at = self::get_event_time(self::EVENT_GATEWAY_CONFIGURED);
                if ($gateway_at === 0) {
                    $gateway_at = time();
                }
                $results['ready'] = ($now - $gateway_at) >= self::get_triggers()[self::TRIGGER_NO_TRANSACTIONS]['delay'];
                $results['met']   = $results['ready'] && (self::transaction_count() === 0);

                $kb_url = MeprUtils::get_brand_config_value(
                    'proactive_support_kb_test_transaction'
                );

                $results['context']['cta_url']  = $kb_url ?: admin_url('admin.php?page=memberpress-trans');
                $results['context']['cta_text'] = $kb_url
                    ? __('How to Create a Test Transaction', 'memberpress')
                    : __('Run a test transaction', 'memberpress');
                break;

            case self::TRIGGER_INACTIVE_AFTER_SETUP:
                $live_ready = self::is_live_ready();
                $go_live_at = self::get_event_time(self::EVENT_GO_LIVE);
                if (!$live_ready || $go_live_at === 0) {
                    return $results;
                }
                $results['ready']              = ($now - $go_live_at) >= self::get_triggers()[self::TRIGGER_INACTIVE_AFTER_SETUP]['delay'];
                $results['met']                = $results['ready'] && (self::member_signups_since($go_live_at) === 0);
                $results['context']['cta_url'] = admin_url('admin.php?page=memberpress-members');
                break;
        }

        if (!empty($results['context']['cta_url'])) {
            $results['context']['cta_url'] = self::add_tracking_to_url($results['context']['cta_url']);
        }

        return $results;
    }

    /**
     * Base context shared across templates.
     *
     * @return array
     */
    public static function base_context(): array
    {
        return [
            'site_name' => get_bloginfo('name'),
            'site_url'  => home_url(),
        ];
    }

    /**
     * Get UTM tracking parameters for proactive support emails.
     *
     * @return array
     */
    private static function tracking_params(): array
    {
        return [
            'utm_source'   => 'proactive-support',
            'utm_medium'   => 'email',
            'utm_campaign' => 'onboarding',
        ];
    }

    /**
     * Append tracking parameters to a URL.
     *
     * @param string $url URL to update.
     *
     * @return string
     */
    public static function add_tracking_to_url($url): string
    {
        $url = (string) $url;

        if ($url === '') {
            return $url;
        }

        return add_query_arg(self::tracking_params(), $url);
    }

    /**
     * Determine if proactive support is enabled for this install.
     *
     * Returns true only when the flag was explicitly set during initial activation,
     * which happens exclusively on fresh installs, never on upgrades.
     *
     * @return boolean
     */
    public static function is_enabled(): bool
    {
        $options = MeprOptions::fetch();
        return !empty($options->proactive_support_fresh_install);
    }

    /**
     * Get the install timestamp.
     *
     * @return integer
     */
    public static function get_install_timestamp(): int
    {
        $mepr_options = MeprOptions::fetch();
        return (int) ($mepr_options->activated_timestamp ?? 0);
    }

    /**
     * Check if onboarding is complete.
     *
     * @return boolean
     */
    public static function is_onboarding_complete(): bool
    {
        if (get_option('mepr_onboarding_complete') === '1') {
            self::maybe_store_event_time(self::EVENT_ONBOARDING_COMPLETE, time());
            return true;
        }

        return false;
    }

    /**
     * Determine if site has at least one payment gateway configured.
     *
     * @return boolean
     */
    public static function has_payment_gateway(): bool
    {
        $mepr_options = MeprOptions::fetch();
        $has_gateway  = (int) $mepr_options->pm_count() > 0;

        if ($has_gateway) {
            self::maybe_store_event_time(self::EVENT_GATEWAY_CONFIGURED, time());
        }

        return $has_gateway;
    }

    /**
     * Number of memberships.
     *
     * @return integer
     */
    public static function membership_count(): int
    {
        return (int) MeprProduct::count();
    }

    /**
     * Number of groups/pricing pages.
     *
     * @return integer
     */
    public static function group_count(): int
    {
        $counts = wp_count_posts(MeprGroup::$cpt);
        return (int) ($counts->publish ?? 0);
    }

    /**
     * Number of protection rules.
     *
     * @return integer
     */
    public static function rule_count(): int
    {
        $counts = wp_count_posts(MeprRule::$cpt);
        return (int) ($counts->publish ?? 0);
    }

    /**
     * Number of completed transactions.
     *
     * @return integer
     */
    public static function transaction_count(): int
    {
        global $wpdb;
        $table = MeprDb::fetch()->transactions;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$table} WHERE status = %s", MeprTransaction::$complete_str));
    }

    /**
     * Count members created since timestamp.
     *
     * @param  integer $timestamp Timestamp.
     * @return integer
     */
    public static function member_signups_since($timestamp): int
    {
        global $wpdb;
        $table = MeprDb::fetch()->members;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $wpdb->prepare("SELECT COUNT(id) FROM {$table} WHERE created_at >= %s", gmdate('Y-m-d H:i:s', $timestamp));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var($query);
    }

    /**
     * First membership timestamp.
     *
     * @return integer
     */
    public static function get_first_membership_timestamp(): int
    {
        $timestamp = self::get_event_time(self::EVENT_FIRST_MEMBERSHIP);
        if ($timestamp > 0) {
            return $timestamp;
        }

        $ids = get_posts([
            'post_type'   => MeprProduct::$cpt,
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'ASC',
            'numberposts' => 1,
            'fields'      => 'ids',
        ]);

        if (!empty($ids)) {
            $timestamp = get_post_timestamp($ids[0], 'gmt');
            self::maybe_store_event_time(self::EVENT_FIRST_MEMBERSHIP, $timestamp);
        }

        return (int) $timestamp;
    }

    /**
     * Determine if the site met go-live conditions.
     *
     * @return boolean
     */
    public static function is_live_ready(): bool
    {
        $ready = (
            self::membership_count() > 0 &&
            self::has_payment_gateway() &&
            self::group_count() > 0 &&
            self::rule_count() > 0
        );

        if ($ready) {
            self::maybe_store_event_time(self::EVENT_GO_LIVE, time());
        }

        return $ready;
    }

    /**
     * Fetch batched admin users.
     *
     * @param  integer $limit Limit per run.
     * @return array
     */
    public static function get_admin_batch($limit = 50): array
    {
        self::maybe_migrate_batch_offset();

        $emails = self::get_notification_emails();
        if (empty($emails)) {
            return [];
        }

        $options = MeprOptions::fetch();
        $offset  = (int) ($options->proactive_support_batch_offset ?? 0);

        if ($offset >= count($emails)) {
            $offset = 0;
        }

        $limit        = max(1, (int) $limit);
        $batch_emails = array_slice($emails, $offset, $limit);
        $next_offset  = ($offset + count($batch_emails)) >= count($emails) ? 0 : ($offset + $limit);

        $options                                 = MeprOptions::fetch(true);
        $options->proactive_support_batch_offset = $next_offset;
        $options->store(false);

        $recipients = [];
        foreach ($batch_emails as $email) {
            $user         = get_user_by('email', $email);
            $name         = $user instanceof WP_User ? ($user->display_name ?: $user->user_login) : $email;
            $recipients[] = [
                'email' => $email,
                'user'  => $user,
                'name'  => $name,
            ];
        }

        return $recipients;
    }

    /**
     * Retrieve notification email addresses from options.
     *
     * @return array
     */
    public static function get_notification_emails(): array
    {
        $options = MeprOptions::fetch();
        $raw     = (string) ($options->admin_email_addresses ?? '');
        if ($raw === '') {
            return [];
        }

        $normalized = str_replace(["\r\n", "\r", "\n", ';'], ',', $raw);
        $parts      = explode(',', $normalized);
        $emails     = [];

        foreach ($parts as $part) {
            $email = strtolower(sanitize_email(trim($part)));
            if (!empty($email)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Check if proactive support is globally opted out.
     *
     * @return boolean
     */
    public static function is_globally_opted_out(): bool
    {
        self::maybe_migrate_global_opt_out();
        $options = MeprOptions::fetch();
        return !empty($options->proactive_support_opt_out_all);
    }

    /**
     * Enable or disable the global opt-out.
     *
     * @param boolean $enabled Whether proactive emails are disabled globally.
     *
     * @return void
     */
    public static function set_global_opt_out($enabled): void
    {
        self::maybe_migrate_global_opt_out();
        $options                                = MeprOptions::fetch(true);
        $options->proactive_support_opt_out_all = (bool) $enabled;
        $options->store(false);
    }

    /**
     * Retrieve the list of opted-out admin emails.
     *
     * @return array
     */
    public static function get_admin_opt_out_emails(): array
    {
        self::maybe_migrate_admin_opt_out_option();

        $options = MeprOptions::fetch();
        $raw     = (string) ($options->proactive_support_opted_out_emails ?? '');
        if ($raw === '') {
            return [];
        }

        $lines  = preg_split("/\r\n|\r|\n/", $raw);
        $emails = [];

        foreach ((array) $lines as $line) {
            $email = strtolower(sanitize_email(trim((string) $line)));
            if (!empty($email)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Persist the list of opted-out admin emails.
     *
     * @param array $emails Email addresses.
     *
     * @return void
     */
    public static function set_admin_opt_out_emails(array $emails): void
    {
        $emails = array_values(array_unique(array_filter(array_map(static function ($email) {
            return strtolower(sanitize_email(trim((string) $email)));
        }, $emails))));

        $options                                     = MeprOptions::fetch(true);
        $options->proactive_support_opted_out_emails = implode("\n", $emails);
        $options->store(false);
    }

    /**
     * Determine if an email opted out individually.
     *
     * @param string $email Admin email.
     *
     * @return boolean
     */
    public static function is_email_opted_out($email): bool
    {
        $opted_out = array_map('strtolower', self::get_admin_opt_out_emails());

        return in_array(strtolower(sanitize_email($email)), $opted_out, true);
    }

    /**
     * Generate opt-out URL for an email address.
     *
     * @param string $email Email address.
     *
     * @return string
     */
    public static function get_opt_out_url_for_email($email): string
    {
        $email = strtolower(sanitize_email($email));
        if (empty($email)) {
            return admin_url('admin.php?page=memberpress-proactive-support');
        }

        $token = self::get_opt_out_token($email);
        if (empty($token)) {
            return admin_url('admin.php?page=memberpress-proactive-support');
        }

        return add_query_arg(
            [
                'action' => 'mepr_proactive_opt_out',
                'email'  => $email,
                'token'  => $token,
            ],
            admin_url('admin-post.php')
        );
    }

    /**
     * Generate a verification token for opt-out requests.
     *
     * @param string $email Email address.
     *
     * @return string
     */
    public static function get_opt_out_token($email): string
    {
        $email = strtolower(sanitize_email($email));
        if (empty($email)) {
            return '';
        }

        return substr(hash_hmac('sha256', $email, wp_salt('mepr_proactive_opt_out')), 0, 16);
    }

    /**
     * Retrieve stored event timestamp.
     *
     * @param  string $key Key.
     * @return integer
     */
    public static function get_event_time($key): int
    {
        self::maybe_migrate_events_option();

        $options = MeprOptions::fetch();
        $events  = $options->proactive_support_events ?? [];
        if (!is_array($events)) {
            $events = [];
        }
        return (int) ($events[$key] ?? 0);
    }

    /**
     * Store event timestamp when not already recorded or when earlier.
     *
     * @param string  $key       Key.
     * @param integer $timestamp Timestamp.
     *
     * @return void
     */
    public static function maybe_store_event_time($key, $timestamp): void
    {
        if (empty($timestamp)) {
            return;
        }

        self::maybe_migrate_events_option();

        $options = MeprOptions::fetch(true);
        $events  = $options->proactive_support_events ?? [];
        if (!is_array($events)) {
            $events = [];
        }

        if (!isset($events[$key]) || (int) $events[$key] === 0 || $timestamp < (int) $events[$key]) {
            $events[$key]                      = (int) $timestamp;
            $options->proactive_support_events = $events;
            $options->store(false);
        }
    }

    /**
     * Human friendly label for a trigger key.
     *
     * @param  string $trigger Trigger key.
     * @return string
     */
    public static function trigger_label($trigger): string
    {
        $triggers = self::get_triggers();
        return $triggers[$trigger]['label'] ?? $trigger;
    }

    /**
     * Determine email template path for trigger.
     *
     * @param  string $trigger Trigger key.
     * @return string
     */
    public static function template_for_trigger($trigger): string
    {
        $triggers = self::get_triggers();
        return $triggers[$trigger]['template'] ?? $trigger;
    }

    /**
     * Resolve a trigger when it is no longer met.
     *
     * @param string $trigger Trigger key.
     * @param string $message Log message.
     *
     * @return void
     */
    public static function maybe_resolve_trigger($trigger, $message): void
    {
        $evaluation = self::evaluate_trigger($trigger);
        if (!empty($evaluation['met'])) {
            return;
        }

        $emails = self::get_notification_emails();
        if (empty($emails)) {
            return;
        }

        foreach ($emails as $email) {
            self::resolve_trigger_for_email($trigger, $email, $message);
        }
    }

    /**
     * Resolve the latest record for a trigger/email.
     *
     * @param string $trigger Trigger key.
     * @param string $email   Admin email.
     * @param string $message Log message.
     *
     * @return void
     */
    private static function resolve_trigger_for_email($trigger, $email, $message): void
    {
        $record = MeprProactiveSupportRepository::latest_for_trigger($email, $trigger);
        if (!$record || in_array($record->status, ['resolved', 'cancelled'], true)) {
            return;
        }

        MeprProactiveSupportRepository::update(
            $record->id,
            [
                'status'      => 'resolved',
                'resolved_by' => 0,
                'resolved_at' => current_time('mysql', true),
            ]
        );

        MeprProactiveSupportLogger::log('resolved', $trigger, $email, $message);
    }

    /**
     * Migrate legacy global opt-out option into MeprOptions.
     *
     * @return void
     */
    private static function maybe_migrate_global_opt_out(): void
    {
        $legacy = get_option(self::GLOBAL_OPT_OUT_OPTION, null);
        if ($legacy === null) {
            return;
        }

        $options                                = MeprOptions::fetch(true);
        $options->proactive_support_opt_out_all = ($legacy === '1');
        $options->store(false);

        delete_option(self::GLOBAL_OPT_OUT_OPTION);
    }

    /**
     * Migrate legacy per-admin opt-out option into MeprOptions.
     *
     * @return void
     */
    private static function maybe_migrate_admin_opt_out_option(): void
    {
        $options  = MeprOptions::fetch(true);
        $existing = (string) ($options->proactive_support_opted_out_emails ?? '');
        if ($existing !== '') {
            delete_option(self::ADMIN_OPT_OUT_OPTION);
            delete_option(self::LEGACY_ADMIN_OPT_OUT_OPTION);
            return;
        }

        $raw = get_option(self::ADMIN_OPT_OUT_OPTION, null);
        if ($raw === null) {
            $raw = get_option(self::LEGACY_ADMIN_OPT_OUT_OPTION, null);
        }

        if ($raw === null) {
            return;
        }

        $options->proactive_support_opted_out_emails = (string) $raw;
        $options->store(false);

        delete_option(self::ADMIN_OPT_OUT_OPTION);
        delete_option(self::LEGACY_ADMIN_OPT_OUT_OPTION);
    }

    /**
     * Migrate legacy events option into MeprOptions.
     *
     * @return void
     */
    private static function maybe_migrate_events_option(): void
    {
        $legacy = get_option(self::EVENTS_OPTION, null);
        if ($legacy === null) {
            return;
        }

        $options                           = MeprOptions::fetch(true);
        $options->proactive_support_events = is_array($legacy) ? $legacy : [];
        $options->store(false);

        delete_option(self::EVENTS_OPTION);
    }

    /**
     * Migrate batch offset option into MeprOptions.
     *
     * @return void
     */
    private static function maybe_migrate_batch_offset(): void
    {
        $legacy = get_option(self::BATCH_OFFSET_OPTION, null);
        if ($legacy === null) {
            return;
        }

        $options                                 = MeprOptions::fetch(true);
        $options->proactive_support_batch_offset = (int) $legacy;
        $options->store(false);

        delete_option(self::BATCH_OFFSET_OPTION);
    }
}
