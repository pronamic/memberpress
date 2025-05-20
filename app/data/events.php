<?php

return MeprHooks::apply_filters('mepr_events', [
    /**
     * Events for Members
     */
    'member-added'                         => (object) [
        'unique' => true,
    ],
    'member-signup-completed'              => (object) [
        'unique' => true,
    ],
    'member-account-updated'               => (object) [
        'unique' => false,
    ],
    'member-deleted'                       => (object) [
        'unique' => true,
    ],
    'login'                                => (object) [
        'unique' => false,
    ],

    /**
     * Events for Subscriptions
     */
    'subscription-created'                 => (object) [
        'unique' => true,
    ],
    'subscription-paused'                  => (object) [
        'unique' => false,
    ],
    'subscription-resumed'                 => (object) [
        'unique' => false,
    ],
    'subscription-stopped'                 => (object) [
        'unique' => true,
    ],
    'subscription-upgraded'                => (object) [
        'unique' => true,
    ],
    'subscription-downgraded'              => (object) [
        'unique' => true,
    ],
    'subscription-upgraded-to-one-time'    => (object) [
        'unique' => true,
    ],
    'subscription-upgraded-to-recurring'   => (object) [
        'unique' => true,
    ],
    'subscription-downgraded-to-one-time'  => (object) [
        'unique' => true,
    ],
    'subscription-downgraded-to-recurring' => (object) [
        'unique' => true,
    ],
    'subscription-expired'                 => (object) [
        'unique' => false,
    ],

    /**
     * Events for Transactions
     */
    'transaction-completed'                => (object) [
        'unique' => true,
    ],
    'transaction-refunded'                 => (object) [
        'unique' => true,
    ],
    'transaction-failed'                   => (object) [
        'unique' => true,
    ],
    'transaction-expired'                  => (object) [
        'unique' => true,
    ],
    'offline-payment-pending'              => (object) [
        'unique' => true,
    ],
    'offline-payment-complete'             => (object) [
        'unique' => true,
    ],
    'offline-payment-refunded'             => (object) [
        'unique' => true,
    ],
    // Recurring Transactions.
    'recurring-transaction-completed'      => (object) [
        'unique' => true,
    ],
    'renewal-transaction-completed'        => (object) [
        'unique' => true,
    ],
    'recurring-transaction-failed'         => (object) [
        'unique' => true,
    ],
    'recurring-transaction-expired'        => (object) [
        'unique' => true,
    ],
    // Non-Recurring Transactions.
    'non-recurring-transaction-completed'  => (object) [
        'unique' => true,
    ],
    'non-recurring-transaction-expired'    => (object) [
        'unique' => true,
    ],

    /**
     * Events from Reminders
     */
    // Note, uniqueness of Reminders is handled by the reminders routines
    // So all reminders should be classified as non-unique here.
    'after-member-signup-reminder'         => (object) [
        'unique' => false,
    ],
    'after-signup-abandoned-reminder'      => (object) [
        'unique' => false,
    ],
    'before-sub-expires-reminder'          => (object) [
        'unique' => false,
    ],
    'after-sub-expires-reminder'           => (object) [
        'unique' => false,
    ],
    'before-sub-renews-reminder'           => (object) [
        'unique' => false,
    ],
    'after-cc-expires-reminder'            => (object) [
        'unique' => false,
    ],
    'before-cc-expires-reminder'           => (object) [
        'unique' => false,
    ],
    'before-sub-trial-ends'                => (object) [
        'unique' => false,
    ],

    /**
     * Events for Corporate Accounts
     */
    'sub-account-added'                    => (object) [
        'unique' => false,
    ],
    'sub-account-removed'                  => (object) [
        'unique' => false,
    ],

    /**
     * Events for Courses
     */
    'mpca-course-started'                  => (object) [
        'unique' => false,
    ],
    'mpca-course-completed'                => (object) [
        'unique' => false,
    ],
    'mpca-lesson-started'                  => (object) [
        'unique' => false,
    ],
    'mpca-lesson-completed'                => (object) [
        'unique' => false,
    ],
    'mpca-quiz-attempt-completed'          => (object) [
        'unique' => false,
    ],
]);
