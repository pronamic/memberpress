<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprAdminNewSubEmail extends MeprBaseOptionsAdminEmail
{
    /**
     * Set the default enabled, title, subject & body
     *
     * @param  array $args Email arguments
     * @return void
     */
    public function set_defaults($args = [])
    {
        $mepr_options = MeprOptions::fetch();
        $this->to     = $mepr_options->admin_email_addresses;

        $this->title       = __('<b>New Recurring Subscription</b> Notice', 'memberpress');
        $this->description = __('This email is sent to you when a subscription is created.', 'memberpress');
        $this->ui_order    = 1;

        $enabled = $use_template = $this->show_form = true;
        $subject = __('** New Recurring Subscription: {$subscr_num}', 'memberpress');
        $body    = $this->body_partial();

        $this->defaults  = compact('enabled', 'subject', 'body', 'use_template');
        $this->variables = MeprSubscriptionsHelper::get_email_vars();
    }
}
