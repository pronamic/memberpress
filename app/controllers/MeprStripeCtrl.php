<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprStripeCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for the Stripe payment processing.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('wp_ajax_mepr_stripe_create_account_setup_intent', [$this, 'create_account_setup_intent']);
        add_action('wp_ajax_nopriv_mepr_stripe_create_account_setup_intent', [$this, 'create_account_setup_intent']);
        add_action('wp_ajax_mepr_stripe_update_payment_method', [$this, 'update_payment_method']);
        add_action('wp_ajax_nopriv_mepr_stripe_update_payment_method', [$this, 'update_payment_method']);
        add_action('mepr-update-new-user-email', [$this, 'update_user_email']);
        add_action('mepr_stripe_record_sub_payment', [$this, 'update_charge_metadata']);
    }

    /**
     * Handle the Ajax request to create a SetupIntent for updating the payment method for a subscription
     */
    public function create_account_setup_intent()
    {
        $subscription_id = isset($_POST['subscription_id']) && is_numeric($_POST['subscription_id']) ? (int) $_POST['subscription_id'] : 0;

        if (empty($subscription_id)) {
            wp_send_json_error(__('Bad request', 'memberpress'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Sorry, you must be logged in to do this.', 'memberpress'));
        }

        if (!check_ajax_referer('mepr_process_update_account_form', '_mepr_nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }

        $sub = new MeprSubscription($subscription_id);

        if (!($sub instanceof MeprSubscription)) {
            wp_send_json_error(__('Subscription not found', 'memberpress'));
        }

        $usr = $sub->user();

        if ($usr->ID != get_current_user_id()) {
            wp_send_json_error(__('This subscription is for another user.', 'memberpress'));
        }

        $pm = $sub->payment_method();

        if (!($pm instanceof MeprStripeGateway)) {
            wp_send_json_error(__('Invalid payment gateway', 'memberpress'));
        }

        try {
            if (strpos($sub->subscr_id, 'sub_') === 0) {
                $subscription = $pm->retrieve_subscription($sub->subscr_id);
            } else {
                $subscription = $pm->get_customer_subscription($sub->subscr_id);
            }

            $setup_intent = $pm->create_update_setup_intent($subscription->customer);

            wp_send_json_success($setup_intent->client_secret);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle the request to update the payment method for a subscription
     */
    public function update_payment_method()
    {
        $mepr_options = MeprOptions::fetch();

        $subscription_id = isset($_GET['subscription_id']) && is_numeric($_GET['subscription_id']) ? (int) $_GET['subscription_id'] : 0;
        $setup_intent_id = isset($_GET['setup_intent']) ? sanitize_text_field(wp_unslash($_GET['setup_intent'])) : '';
        $nonce           = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';

        $return_url = add_query_arg([
            'action' => 'update',
            'sub'    => $subscription_id,
        ], $mepr_options->account_page_url());

        if (empty($subscription_id) || empty($setup_intent_id)) {
            MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('Bad request', 'memberpress'))], $return_url));
        }

        if (!is_user_logged_in()) {
            MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('Sorry, you must be logged in to do this.', 'memberpress'))], $return_url));
        }

        if (!wp_verify_nonce($nonce, 'mepr_process_update_account_form')) {
            MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('Security check failed.', 'memberpress'))], $return_url));
        }

        $sub = new MeprSubscription($subscription_id);

        if (!($sub->id > 0)) {
            MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('Subscription not found', 'memberpress'))], $return_url));
        }

        $usr = $sub->user();

        if ($usr->ID != get_current_user_id()) {
            MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('This subscription is for another user.', 'memberpress'))], $return_url));
        }

        $pm = $sub->payment_method();

        if (!($pm instanceof MeprStripeGateway)) {
            MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('Invalid payment gateway', 'memberpress'))], $return_url));
        }

        try {
            $setup_intent = $pm->retrieve_setup_intent($setup_intent_id);

            if ($setup_intent->status != 'succeeded') {
                MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode(__('The payment setup was unsuccessful, please try another payment method.', 'memberpress'))], $return_url));
            }

            $subscription = $pm->update_subscription_payment_method($sub, $usr, (object) $setup_intent->payment_method);

            if ($subscription->latest_invoice && $subscription->latest_invoice['status'] == 'open') {
                try {
                    $pm->retry_invoice_payment($subscription->latest_invoice['id']);
                } catch (Exception $e) {
                    // Ignore.
                }
            }

            MeprUtils::wp_redirect(add_query_arg(['message' => urlencode(__('Your account information was successfully updated.', 'memberpress'))], $return_url));
        } catch (Exception $e) {
            MeprUtils::wp_redirect(add_query_arg(['errors' => urlencode($e->getMessage())], $return_url));
        }
    }

    /**
     * Update the email address of the Stripe Customer when the customer changes email address on the Account page.
     *
     * @param MeprUser $user The user.
     */
    public function update_user_email($user)
    {
        $mepr_options = MeprOptions::fetch();

        foreach ($mepr_options->integrations as $integration) {
            if ($integration['gateway'] == 'MeprStripeGateway') {
                $payment_method = new MeprStripeGateway();
                $payment_method->load($integration);
                $stripe_customer_id = $user->get_stripe_customer_id($payment_method->get_meta_gateway_id());

                if (empty($stripe_customer_id)) {
                    // Continue on other instances of MeprStripeGateway.
                    continue;
                }

                $args = ['email' => $user->user_email];

                try {
                    $payment_method->send_stripe_request('customers/' . $stripe_customer_id, $args, 'post');
                } catch (Exception $exception) {
                    // Ignore exceptions.
                }
            }
        }
    }

    /**
     * Updates the metadata of a charge associated with the given transaction.
     *
     * @param MeprTransaction $txn The transaction for which the charge metadata needs to be updated.
     *
     * @return void
     */
    public function update_charge_metadata(MeprTransaction $txn)
    {
        $pm = $txn->payment_method();

        if ($pm instanceof MeprStripeGateway) {
            $pm->update_charge_metadata($txn);
        }
    }
}
