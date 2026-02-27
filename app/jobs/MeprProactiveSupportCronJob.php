<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProactiveSupportCronJob
{
    private const COOLDOWN_SECONDS = 5 * DAY_IN_SECONDS;

    /**
     * Run proactive support checks for a batch of admins.
     *
     * @return void
     */
    public function run(): void
    {
        if (!MeprProactiveSupportHelper::is_enabled()) {
            return;
        }

        $recipients = MeprProactiveSupportHelper::get_admin_batch(50);

        if (empty($recipients)) {
            return;
        }

        foreach ($recipients as $recipient) {
            $this->process_recipient($recipient);
        }
    }

    /**
     * Process triggers for a single recipient.
     *
     * @param array $recipient Recipient data.
     *
     * @return void
     */
    private function process_recipient(array $recipient): void
    {
        $email = $recipient['email'] ?? '';
        if (empty($email)) {
            return;
        }

        $user = isset($recipient['user']) && $recipient['user'] instanceof WP_User ? $recipient['user'] : null;

        $can_send = true;

        if (MeprProactiveSupportHelper::is_globally_opted_out()) {
            MeprProactiveSupportLogger::log('skipped-global-optout', 'all', $email);
            $can_send = false;
        }

        if (MeprProactiveSupportHelper::is_email_opted_out($email)) {
            MeprProactiveSupportLogger::log('skipped-user-optout', 'all', $email);
            $can_send = false;
        }

        $cooldown_seconds = $this->cooldown_seconds();
        $last_sent_at     = MeprProactiveSupportRepository::last_sent_at_for_email($email);

        $now             = time();
        $cooldown_active = $this->is_cooldown_active($last_sent_at, $now, $cooldown_seconds);

        if ($cooldown_active) {
            MeprProactiveSupportLogger::log('skipped-cooldown', 'all', $email);
        }

        foreach (MeprProactiveSupportHelper::get_triggers() as $trigger_key => $trigger_data) {
            $evaluation = MeprProactiveSupportHelper::evaluate_trigger($trigger_key);

            if (!$evaluation['ready']) {
                continue;
            }

            $existing = MeprProactiveSupportRepository::latest_for_trigger($email, $trigger_key);

            // Skip triggers already handled (resolved, cancelled, or replied).
            if ($existing && ((int) $existing->reply_received === 1 || in_array($existing->status, ['resolved', 'cancelled'], true))) {
                continue;
            }

            if ($evaluation['met']) {
                if ($existing && $existing->status === 'sent') {
                    continue;
                }

                $record_id = $this->ensure_record_exists($email, $trigger_key, $evaluation['context'], $existing, $user);

                if ($record_id === false) {
                    continue;
                }

                if (!$can_send) {
                    $this->update_suppressed_record($record_id, $evaluation['context'], 'opt-out');
                    MeprProactiveSupportLogger::log('suppressed', $trigger_key, $email, 'Email suppressed due to opt-out preference');
                    continue;
                }

                if ($cooldown_active) {
                    $this->update_suppressed_record($record_id, $evaluation['context'], 'cooldown');
                    MeprProactiveSupportLogger::log('skipped-cooldown', $trigger_key, $email, 'Suppressed due to cooldown period');
                    continue;
                }

                $recipient_context = [
                    'email' => $email,
                    'name'  => $recipient['name'] ?? ($user instanceof WP_User ? ($user->display_name ?: $user->user_login) : $email),
                    'user'  => $user,
                ];

                if (MeprProactiveSupportMailer::send($trigger_key, $recipient_context, $evaluation['context'])) {
                    $sent_at = current_time('mysql', true);
                    MeprProactiveSupportRepository::update(
                        $record_id,
                        [
                            'status'        => 'sent',
                            'email_sent_at' => $sent_at,
                            'send_count'    => ($existing && isset($existing->send_count)) ? ((int) $existing->send_count + 1) : 1,
                        ]
                    );
                    MeprProactiveSupportLogger::log('sent', $trigger_key, $email);

                    $cooldown_active = true;
                }
            } else {
                if ($existing && in_array($existing->status, ['pending', 'suppressed'], true)) {
                    MeprProactiveSupportRepository::update($existing->id, ['status' => 'cancelled']);
                    MeprProactiveSupportLogger::log('cancelled', $trigger_key, $email);
                }
            }
        }
    }

    /**
     * Update a proactive support record when delivery is suppressed.
     *
     * @param integer $record_id Record ID.
     * @param array   $context   Email context.
     * @param string  $reason    Suppression reason.
     *
     * @return void
     */
    private function update_suppressed_record($record_id, array $context, $reason): void
    {
        $meta                      = $context;
        $meta['suppressed_reason'] = $reason;

        MeprProactiveSupportRepository::update(
            $record_id,
            [
                'status'        => 'suppressed',
                'email_sent_at' => null,
                'meta'          => wp_json_encode($meta),
            ]
        );
    }

    /**
     * Determine if cooldown period is active.
     *
     * @param string|null $last_sent_at     Last sent timestamp.
     * @param integer     $now_ts           Current timestamp (GMT).
     * @param integer     $cooldown_seconds Cooldown duration.
     *
     * @return boolean
     */
    private function is_cooldown_active($last_sent_at, $now_ts, $cooldown_seconds): bool
    {
        if (empty($last_sent_at)) {
            return false;
        }

        $last = strtotime($last_sent_at . ' UTC');
        if ($last === false) {
            return false;
        }

        return ($now_ts - $last) < $cooldown_seconds;
    }

    /**
     * Ensure a record exists before sending.
     *
     * @param string       $email    Admin email.
     * @param string       $trigger  Trigger key.
     * @param array        $context  Email context.
     * @param object|null  $existing Existing record.
     * @param WP_User|null $user     WP user object if available.
     *
     * @return integer|false
     */
    private function ensure_record_exists($email, $trigger, array $context, $existing, $user)
    {
        if ($existing && $existing->status === 'cancelled') {
            return false;
        }

        if ($existing && $existing->status !== 'sent') {
            MeprProactiveSupportRepository::update($existing->id, [
                'meta' => wp_json_encode($context),
            ]);
            return $existing->id;
        }

        $user_id = ($user instanceof WP_User) ? $user->ID : 0;

        return MeprProactiveSupportRepository::insert(
            $user_id,
            $email,
            $trigger,
            'pending',
            $context
        );
    }

    /**
     * Get the cooldown window in seconds.
     *
     * @return integer
     */
    private function cooldown_seconds(): int
    {
        $value = apply_filters('mepr_proactive_support_cooldown_seconds', self::COOLDOWN_SECONDS);
        $value = absint($value);

        return $value > 0 ? $value : self::COOLDOWN_SECONDS;
    }
}
