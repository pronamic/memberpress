<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprEmailsCtrl extends MeprBaseCtrl
{
    /**
     * Loads the hooks for the emails controller.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('wp_ajax_mepr_set_email_defaults', 'MeprEmailsCtrl::set_email_defaults');
        add_action('wp_ajax_mepr_send_test_email', 'MeprEmailsCtrl::send_test_email');
    }

    /**
     * Sets the email defaults.
     *
     * @return void
     */
    public static function set_email_defaults()
    {
        check_ajax_referer('set_email_defaults', 'set_email_defaults_nonce');

        if (!MeprUtils::is_mepr_admin()) {
            die(__('You do not have access.', 'memberpress'));
        }

        if (!isset($_POST['e'])) {
            die(__('Email couldn\'t be set to default', 'memberpress'));
        }

        if (!isset($_POST['a'])) {
            $_POST['a'] = [];
        }

        try {
            $email = MeprEmailFactory::fetch(wp_unslash($_POST['e']), 'MeprBaseEmail', $_POST['a']);
        } catch (Exception $e) {
            die(json_encode(['error' => $e->getMessage()]));
        }

        die(json_encode([
            'subject' => $email->default_subject(),
            'body'    => $email->default_body(),
        ]));
    }

    /**
     * Sends a test email.
     *
     * @return void
     */
    public static function send_test_email()
    {
        check_ajax_referer('send_test_email', 'send_test_email_nonce');

        $mepr_options = MeprOptions::fetch();

        if (!MeprUtils::is_mepr_admin()) {
            die(__('You do not have access to send a test email.', 'memberpress'));
        }

        if (!isset($_POST['e']) or !isset($_POST['s']) or !isset($_POST['b']) or !isset($_POST['t'])) {
            die(__('Can\'t send your email ... refresh the page and try it again.', 'memberpress'));
        }

        if (!isset($_POST['a'])) {
            $_POST['a'] = [];
        }

        try {
            $email = MeprEmailFactory::fetch(wp_unslash($_POST['e']), 'MeprBaseEmail', $_POST['a']);
        } catch (Exception $e) {
            die(json_encode(['error' => $e->getMessage()]));
        }

        $email->to = $mepr_options->admin_email_addresses;

        $amount     = preg_replace(
            '~\$~',
            '\\\$',
            sprintf(
                '%s' . MeprUtils::format_float(15.15),
                stripslashes($mepr_options->currency_symbol)
            )
        );
        $subtotal   = preg_replace(
            '~\$~',
            '\\\$',
            sprintf(
                '%s' . MeprUtils::format_float(15.00),
                stripslashes($mepr_options->currency_symbol)
            )
        );
        $tax_amount = preg_replace(
            '~\$~',
            '\\\$',
            sprintf(
                '%s' . MeprUtils::format_float(0.15),
                stripslashes($mepr_options->currency_symbol)
            )
        );

        $params = array_merge(
            [
                'user_id'                    => 481,
                'user_login'                 => 'johndoe',
                'username'                   => 'johndoe',
                'user_email'                 => 'johndoe@example.com',
                'user_first_name'            => __('John', 'memberpress'),
                'user_last_name'             => __('Doe', 'memberpress'),
                'user_full_name'             => __('John Doe', 'memberpress'),
                'user_address'               => '<br/>' .
                                             __('111 Cool Avenue', 'memberpress') . '<br/>' .
                                             __('New York, NY 10005', 'memberpress') . '<br/>' .
                                             __('United States', 'memberpress') . '<br/>',
                'usermeta:(.*)'              => __('User Meta Field: $1', 'memberpress'),
                'membership_type'            => __('Bronze Edition', 'memberpress'),
                'signup_url'                 => home_url(),
                'product_name'               => __('Bronze Edition', 'memberpress'),
                'invoice_num'                => 718,
                'trans_num'                  => '9i8h7g6f5e',
                'trans_date'                 => MeprAppHelper::format_date(gmdate('c', time())),
                'trans_expires_at'           => MeprAppHelper::format_date(gmdate('c', time() + MeprUtils::months(1))),
                'trans_gateway'              => __('Credit Card (Stripe)', 'memberpress'),
                'user_remote_addr'           => $_SERVER['REMOTE_ADDR'],
                'payment_amount'             => $amount,
                'subscr_num'                 => '1a2b3c4d5e',
                'subscr_date'                => MeprAppHelper::format_date(gmdate('c', time())),
                'subscr_next_billing_at'     => MeprAppHelper::format_date(gmdate('c', time() + MeprUtils::months(1))),
                'subscr_expires_at'          => MeprAppHelper::format_date(gmdate('c', time() + MeprUtils::months(1))),
                'subscr_gateway'             => __('Credit Card (Stripe)', 'memberpress'),
                'subscr_terms'               => sprintf(__('%s / month', 'memberpress'), $amount),
                'subscr_cc_num'              => MeprUtils::cc_num('6710'),
                'subscr_cc_month_exp'        => gmdate('m'),
                'subscr_cc_year_exp'         => (gmdate('Y') + 2),
                'subscr_update_url'          => $mepr_options->login_page_url(),
                'subscr_upgrade_url'         => $mepr_options->login_page_url(),
                'subscr_renew_url'           => $mepr_options->account_page_url() . '?action=subscriptions',
                'subscr_trial_end_date'      => MeprAppHelper::format_date(gmdate('c', time() + MeprUtils::months(1))),
                'subscr_next_billing_amount' => $amount,
                'reminder_id'                => 28,
                'reminder_trigger_length'    => 2,
                'reminder_trigger_interval'  => 'days',
                'reminder_trigger_timing'    => 'before',
                'reminder_trigger_event'     => 'sub-expires',
                'reminder_name'              => __('Subscription Expiring', 'memberpress'),
                'reminder_description'       => __('Subscription Expiring in 2 Days', 'memberpress'),
                'blog_name'                  => MeprUtils::blogname(),
                'payment_subtotal'           => $subtotal,
                'tax_rate'                   => '10%',
                'tax_amount'                 => $tax_amount,
                'tax_desc'                   => __('Tax', 'memberpress'),
                'business_name'              => $mepr_options->attr('biz_name'),
                'biz_name'                   => $mepr_options->attr('biz_name'),
                'biz_address1'               => $mepr_options->attr('biz_address1'),
                'biz_address2'               => $mepr_options->attr('biz_address2'),
                'biz_city'                   => $mepr_options->attr('biz_city'),
                'biz_state'                  => $mepr_options->attr('biz_state'),
                'biz_postcode'               => $mepr_options->attr('biz_postcode'),
                'biz_country'                => $mepr_options->attr('biz_country'),
                'login_page'                 => $mepr_options->login_page_url(),
                'account_url'                => $mepr_options->account_page_url(),
                'login_url'                  => $mepr_options->login_page_url(),
            ],
            $email->test_vars
        );

        $use_template = ( $_POST['t'] == 'true' );
        $email->send($params, sanitize_text_field(wp_unslash($_POST['s'])), wp_kses_post(wp_unslash($_POST['b'])), $use_template);

        die(json_encode(['message' => __('Your test email was successfully sent.', 'memberpress')]));
    }
}
