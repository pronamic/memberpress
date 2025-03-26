<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprAdminMemberSignupReminderEmail extends MeprBaseReminderEmail
{
    /**
     * Set the default enabled, title, subject & body
     *
     * @param  array $args The args.
     * @return void
     */
    public function set_defaults($args = [])
    {
        $mepr_options = MeprOptions::fetch();
        $this->to     = $mepr_options->admin_email_addresses;

        $this->title       = __('Member Signup Reminder Email to Admin', 'memberpress');
        $this->description = __('This email is sent to the admin when triggered for a user.', 'memberpress');
        $this->ui_order    = 1;

        $enabled = $use_template = $this->show_form = true;
        $subject = sprintf(__('** %1$s Reminder Sent to %2$s', 'memberpress'), '{$reminder_name}', '{$username}');
        $body    = $this->body_partial();

        $this->defaults  = compact('enabled', 'subject', 'body', 'use_template');
        $this->variables = array_unique(
            array_merge(
                MeprRemindersHelper::get_email_vars(),
                MeprTransactionsHelper::get_email_vars()
            )
        );

        $this->test_vars = [
            'reminder_id'               => 28,
            'reminder_trigger_length'   => 2,
            'reminder_trigger_interval' => 'days',
            'reminder_trigger_timing'   => 'after',
            'reminder_trigger_event'    => 'member-signup',
            'reminder_name'             => __('Member Signed Up', 'memberpress'),
            'reminder_description'      => __('Member Signed Up 2 days ago', 'memberpress'),
        ];
    }
}
