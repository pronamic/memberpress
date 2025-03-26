<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprUserUpgradedSubEmail extends MeprBaseOptionsUserEmail
{
    /**
     * Set the default enabled, title, subject & body
     *
     * @param  array $args The args.
     * @return void
     */
    public function set_defaults($args = [])
    {
        $this->title       = __('<b>Upgraded Subscription</b> Notice', 'memberpress');
        $this->description = __('This email is sent to the user when they upgrade a subscription.', 'memberpress');
        $this->ui_order    = 3;

        $enabled = $use_template = $this->show_form = true;
        $subject = __('** You\'ve upgraded your subscription', 'memberpress');
        $body    = $this->body_partial();

        $this->defaults  = compact('enabled', 'subject', 'body', 'use_template');
        $this->variables = MeprSubscriptionsHelper::get_email_vars();
    }
}
