<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

abstract class MeprBasePayPalGateway extends MeprBaseRealGateway
{
    /**
     * Validate PayPal IPN request
     *
     * @return boolean True if valid, false otherwise
     */
    public function validate_ipn()
    {
        // Set the command that is used to validate the message.
        $_POST['cmd'] = '_notify-validate';

        // We need to send the message back to PayPal just as we received it.
        $params = [
            'method'      => 'POST',
            'body'        => stripslashes_deep($_POST),
            'headers'     => ['connection' => 'close'],
            'httpversion' => 1.1,
            'sslverify'   => true,
            'user-agent'  => 'MemberPress/' . MEPR_VERSION,
            'timeout'     => 30,
        ];

        $this->email_status("POST ARRAY SENDING TO PAYPAL\n" . MeprUtils::object_to_string($params, true) . "\n", $this->settings->debug);

        if (!function_exists('wp_remote_post')) {
            require_once(ABSPATH . WPINC . '/http.php');
        }

        $resp = wp_remote_post($this->settings->url, $params);

        // Put the $_POST data back to how it was so we can pass it to the action.
        unset($_POST['cmd']);

        // If the response was valid, check to see if the request was valid.
        if (
            !is_wp_error($resp) &&
            $resp['response']['code'] >= 200 &&
            $resp['response']['code'] < 300 &&
            (strcmp($resp['body'], 'VERIFIED') == 0)
        ) {
            return true;
        }

        $this->email_status(
            "IPN Verification Just failed:\nIPN:\n" .
                        MeprUtils::object_to_string($_POST, true) .
                        "PayPal Response:\n" .
                        MeprUtils::object_to_string($resp),
            $this->settings->debug
        );
        return false;
    }

    /**
     * Format currency amount for PayPal (only used in PayPal Standard currently)
     *
     * @param float $amount The amount to format.
     *
     * @return string Formatted amount
     */
    public function format_currency($amount)
    {
        if (MeprUtils::is_zero_decimal_currency()) {
            $amount = MeprAppHelper::format_currency($amount, false, false);
            return str_replace([',','.'], ['',''], $amount); // Strip out all formatting.
        }

        return MeprUtils::format_float($amount);
    }

    /**
     * Process thank you URL redirect
     *
     * @param array           $query_params The query parameters.
     * @param MeprTransaction $txn          The transaction object.
     *
     * @return string The thank you URL
     */
    public function do_thankyou_url($query_params, $txn)
    {
        $mepr_options                   = MeprOptions::fetch();
        $query_params['transaction_id'] = $txn->id;
        return $mepr_options->thankyou_page_url(build_query($query_params));
    }
}
