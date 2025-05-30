<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprUserSubRenewsReminderEmail extends MeprBaseReminderEmail
{
    /**
     * Set the default enabled, title, subject & body
     *
     * @param  array $args The args.
     * @return void
     */
    public function set_defaults($args = [])
    {
        $this->title       = __('Subscription Renews Reminder Email to User', 'memberpress');
        $this->description = __('This email is sent to the user when triggered.', 'memberpress');
        $this->ui_order    = 0;

        $enabled = $use_template = $this->show_form = true;
        $subject = sprintf(
            // Translators: %1$s: reminder description.
            __('** Your %1$s', 'memberpress'),
            '{$reminder_description}'
        );
        $body    = $this->body_partial();

        $this->defaults  = compact('enabled', 'subject', 'body', 'use_template');
        $this->variables = array_unique(
            array_merge(
                MeprRemindersHelper::get_email_vars(),
                MeprSubscriptionsHelper::get_email_vars(),
                MeprTransactionsHelper::get_email_vars()
            )
        );

        $this->test_vars = [
            'reminder_id'               => 28,
            'reminder_trigger_length'   => 2,
            'reminder_trigger_interval' => 'days',
            'reminder_trigger_timing'   => 'before',
            'reminder_trigger_event'    => 'sub-renews',
            'reminder_name'             => __('Subscription Renewing', 'memberpress'),
            'reminder_description'      => __('Subscription Renewing in 2 days', 'memberpress'),
        ];
    }
}
