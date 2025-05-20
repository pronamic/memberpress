<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprUserStripeInvoiceEmail extends MeprBaseOptionsUserEmail
{
    /**
     * Set the default enabled, title, subject & body
     *
     * @param  array $args Email arguments.
     * @return void
     */
    public function set_defaults($args = [])
    {
        $this->title       = __('<b>Stripe Failed Payment</b> Notice', 'memberpress');
        $this->description = __('This email is sent to the user when a Stripe subscription payment of theirs fails, with a link to pay the outstanding invoice.', 'memberpress');
        $this->ui_order    = 10;

        $enabled = $use_template = $this->show_form = true;
        $subject = __('** Your Subscription Payment Failed', 'memberpress');
        $body    = $this->body_partial();

        $this->defaults  = compact('enabled', 'subject', 'body', 'use_template');
        $this->variables = array_merge(MeprSubscriptionsHelper::get_email_vars(), ['stripe_invoice_url']);
    }
}
