<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprAdminFailedTxnEmail extends MeprBaseOptionsAdminEmail
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

        $this->title       = __('<b>Failed Transaction</b> Notice', 'memberpress');
        $this->description = __('This email is sent to you when a transaction fails.', 'memberpress');
        $this->ui_order    = 9;

        $enabled = $use_template = $this->show_form = true;
        $subject = __('** Transaction Failed', 'memberpress');
        $body    = $this->body_partial();

        $this->defaults  = compact('enabled', 'subject', 'body', 'use_template');
        $this->variables = MeprTransactionsHelper::get_email_vars();
    }
}
