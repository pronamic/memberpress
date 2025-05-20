<?php

/**
 * Based on data from:
 *
 * - https://stripe.com/docs/payments/payment-methods/integration-options
 * - https://stripe.com/docs/api/subscriptions/create#create_subscription-payment_settings-payment_method_types
 */

return MeprHooks::apply_filters('mepr_stripe_payment_methods', [
    [
        'key'          => 'link',
        'name'         => 'Link (recommended)',
        'currencies'   => 'all',
        'capabilities' => ['payment_intents', 'setup_future_usage', 'setup_intents', 'subscriptions'],
    ],
    // Bank debits.
    [
        'key'          => 'us_bank_account',
        'name'         => 'ACH Direct Debit',
        'currencies'   => ['USD'],
        'capabilities' => ['payment_intents', 'setup_future_usage', 'setup_intents', 'subscriptions'],
        'async'        => true,
    ],
    [
        'key'          => 'bacs_debit',
        'name'         => 'Bacs Direct Debit',
        'currencies'   => ['GBP'],
        'capabilities' => ['payment_intents', 'setup_future_usage', 'subscriptions'],
        'async'        => true,
    ],
    [
        'key'          => 'au_becs_debit',
        'name'         => 'BECS Direct Debit',
        'currencies'   => ['AUD'],
        'capabilities' => ['payment_intents', 'setup_future_usage', 'setup_intents', 'subscriptions'],
        'async'        => true,
    ],
    [
        'key'          => 'sepa_debit',
        'name'         => 'SEPA Direct Debit',
        'currencies'   => ['EUR'],
        'capabilities' => ['payment_intents', 'setup_future_usage', 'setup_intents', 'subscriptions'],
        'async'        => true,
    ],
    // Bank redirects.
    [
        'key'          => 'bancontact',
        'name'         => 'Bancontact',
        'currencies'   => ['EUR'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'blik',
        'name'         => 'BLIK',
        'currencies'   => ['PLN'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'eps',
        'name'         => 'EPS',
        'currencies'   => ['EUR'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'fpx',
        'name'         => 'FPX',
        'currencies'   => ['MYR'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'giropay',
        'name'         => 'Giropay',
        'currencies'   => ['EUR'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'ideal',
        'name'         => 'iDEAL',
        'currencies'   => ['EUR'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'p24',
        'name'         => 'P24',
        'currencies'   => ['EUR', 'PLN'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'sofort',
        'name'         => 'Sofort',
        'currencies'   => ['EUR'],
        'capabilities' => ['payment_intents'],
        'async'        => true,
    ],
    [
        'key'          => 'twint',
        'name'         => 'TWINT',
        'currencies'   => ['CHF'],
        'capabilities' => ['payment_intents'],
    ],
    // Buy now, pay later.
    [
        'key'          => 'affirm',
        'name'         => 'Affirm',
        'currencies'   => ['USD'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'afterpay_clearpay',
        'name'         => 'Afterpay and Clearpay',
        'currencies'   => ['AUD', 'CAD', 'NZD', 'GBP', 'USD'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'klarna',
        'name'         => 'Klarna',
        'currencies'   => ['AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'NOK', 'NZD', 'PLN', 'SEK', 'USD'],
        'capabilities' => ['payment_intents'],
    ],
    // Real-time payments.
    [
        'key'          => 'paynow',
        'name'         => 'PayNow',
        'currencies'   => ['SGD'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'promptpay',
        'name'         => 'PromptPay',
        'currencies'   => ['THB'],
        'capabilities' => ['payment_intents'],
    ],
    // Vouchers.
    [
        'key'          => 'boleto',
        'name'         => 'Boleto',
        'currencies'   => ['BRL'],
        'capabilities' => ['payment_intents', 'setup_future_usage', 'setup_intents', 'subscriptions'],
        'async'        => true,
    ],
    [
        'key'          => 'konbini',
        'name'         => 'Konbini',
        'currencies'   => ['JPY'],
        'capabilities' => ['payment_intents'],
        'async'        => true,
    ],
    [
        'key'          => 'oxxo',
        'name'         => 'OXXO',
        'currencies'   => ['MXN'],
        'capabilities' => ['payment_intents'],
        'async'        => true,
    ],
    // Wallets.
    [
        'key'          => 'alipay',
        'name'         => 'Alipay',
        'currencies'   => ['AUD', 'CAD', 'CNY', 'EUR', 'GBP', 'HKD', 'JPY', 'MYR', 'NZD', 'SGD', 'USD'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'amazon_pay',
        'name'         => 'Amazon Pay',
        'currencies'   => ['USD'],
        'capabilities' => ['payment_intents', 'setup_future_usage', 'setup_intents', 'subscriptions'],
    ],
    [
        'key'          => 'cashapp',
        'name'         => 'Cash App Pay',
        'currencies'   => ['USD'],
        'capabilities' => ['payment_intents', 'setup_future_usage', 'setup_intents', 'subscriptions'],
    ],
    [
        'key'          => 'grabpay',
        'name'         => 'GrabPay',
        'currencies'   => ['MYR', 'SGD'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'mobilepay',
        'name'         => 'MobilePay',
        'currencies'   => ['DKK', 'EUR', 'NOK', 'SEK'],
        'capabilities' => ['payment_intents'],
    ],
    [
        'key'          => 'wechat_pay',
        'name'         => 'WeChat Pay',
        'currencies'   => ['CNY', 'AUD', 'CAD', 'EUR', 'GBP', 'HKD', 'JPY', 'SGD', 'USD', 'DKK', 'NOK', 'SEK', 'CHF'],
        'capabilities' => ['payment_intents'],
    ],
]);
