<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

remove_action('mod_rewrite_rules', 'MeprRulesCtrl::mod_rewrite_rules');

MeprUtils::flush_rewrite_rules();

// Remove wp-cron general purpose jobs.
$jobs = new MeprJobs();
$jobs->unschedule_events();

// Remove wp-cron Stripe Connect job.
$timestamp = wp_next_scheduled('mepr_stripe_connect_check_domain');
wp_unschedule_event($timestamp, 'mepr_stripe_connect_check_domain');

// Remove wp-cron transaction jobs.
MeprTransactionsCtrl::unschedule_events();

$reminders_controller = new MeprRemindersCtrl();
$reminders_controller->unschedule_reminders();
