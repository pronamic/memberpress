<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprArtificialAuthorizeNetProfileHttpClient
{
    protected $is_test;
    protected $endpoint;
    protected $gatewayID;
    protected $login_name;
    protected $transaction_key;
    protected $cache = [];

    /**
     * Constructor for the MeprArtificialAuthorizeNetProfileHttpClient class.
     *
     * @param boolean $is_test         Whether the client is in test mode.
     * @param string  $endpoint        The API endpoint URL.
     * @param string  $gatewayID       The gateway ID.
     * @param string  $login_name      The login name for authentication.
     * @param string  $transaction_key The transaction key for authentication.
     */
    public function __construct($is_test, $endpoint, $gatewayID, $login_name, $transaction_key)
    {
        $this->is_test         = $is_test;
        $this->endpoint        = $endpoint;
        $this->gatewayID       = $gatewayID;
        $this->login_name      = $login_name;
        $this->transaction_key = $transaction_key;
    }

    /**
     * Logs data to a file if debugging is enabled.
     *
     * @param mixed $data The data to log.
     *
     * @return void
     */
    public function log($data)
    {
        if (! defined('WP_MEPR_DEBUG')) {
            return;
        }

        file_put_contents(WP_CONTENT_DIR . '/authorize-net.log', print_r($data, true) . PHP_EOL, FILE_APPEND);
    }

    /**
     * Processes a refund transaction.
     *
     * @param MeprTransaction $txn The transaction object.
     *
     * @return mixed The transaction number or an error.
     * @throws MeprException If the refund cannot be processed.
     */
    public function refundTransaction($txn)
    {
        $product = $txn->product();

        if ($product->is_one_time_payment()) {
            $last4cc = $txn->get_meta('cc_last4', true);
        } else {
            $subscription = $txn->subscription();
            $last4cc      = $subscription->cc_last4;
        }

        $xml = '<createTransactionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
  <merchantAuthentication>
     <name>' . esc_xml($this->login_name) . '</name>
     <transactionKey>' . esc_xml($this->transaction_key) . '</transactionKey>
  </merchantAuthentication>
  <refId>' . esc_xml($txn->trans_num) . '-refund</refId>
  <transactionRequest>
    <transactionType>refundTransaction</transactionType>
    <amount>' . esc_xml($txn->total) . '</amount>
    <payment>
      <creditCard>
        <cardNumber>' . esc_xml($last4cc) . '</cardNumber>
        <expirationDate>XXXX</expirationDate>
      </creditCard>
    </payment>
    <refTransId>' . esc_xml($txn->trans_num) . '</refTransId>
  </transactionRequest>
</createTransactionRequest>';

        $this->log($xml);
        $response = wp_remote_post($this->endpoint, $this->prepareOptions($xml));
        $response = wp_remote_retrieve_body($response);
        $response = $this->parseAuthnetResponse($response);
        $this->log($response);

        if (
            isset($response['messages']['resultCode'])
            && $response['messages']['resultCode'] == 'Ok'
        ) {
            $trans_num = $response['transactionResponse']['transId'];

            return $trans_num;
        } else {
            throw new MeprException(__('Can not refund the payment. The transaction may not have been settled', 'memberpress'));
        }
    }

    /**
     * Voids a transaction.
     *
     * @param MeprTransaction $txn The transaction object.
     *
     * @return mixed The transaction number or an error.
     * @throws MeprException If the transaction cannot be voided.
     */
    public function voidTransaction($txn)
    {
        $xml = '<createTransactionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
  <merchantAuthentication>
     <name>' . esc_xml($this->login_name) . '</name>
     <transactionKey>' . esc_xml($this->transaction_key) . '</transactionKey>
    </merchantAuthentication>
    <refId>v' . esc_xml($txn->id) . '</refId>
  <transactionRequest>
    <transactionType>voidTransaction</transactionType>
    <refTransId>' . esc_xml($txn->trans_num) . '</refTransId>
   </transactionRequest>
</createTransactionRequest>';

        $this->log($xml);
        $response = wp_remote_post($this->endpoint, $this->prepareOptions($xml));
        $response = wp_remote_retrieve_body($response);
        $response = $this->parseAuthnetResponse($response);
        $this->log($response);

        if (
            isset($response['messages']['resultCode'])
            && $response['messages']['resultCode'] == 'Ok'
            && $response['transactionResponse']['responseCode'] == 1
            && isset($response['transactionResponse']['transId'])
            && ! isset($response['transactionResponse']['errors'])
        ) {
            return $response['transactionResponse']['transId'];
        }

        $this->log('Could not complete the void transaction request');
    }

    /**
     * Charges a customer using the provided payment profile.
     *
     * @param array           $authorize_net_customer The Authorize.net customer profile data.
     * @param MeprTransaction $txn                    The transaction object.
     * @param boolean         $capture                Whether to capture the payment immediately.
     * @param string|null     $cvc_code               The CVC code for the card.
     *
     * @throws Exception If the charge cannot be processed.
     * @return string The transaction number.
     */
    public function chargeCustomer($authorize_net_customer, $txn, $capture = true, $cvc_code = null)
    {
        $this->log($authorize_net_customer);
        $paymentProfile = '';

        if (isset($authorize_net_customer['paymentProfiles']['customerPaymentProfileId'])) {
            $paymentProfile = $authorize_net_customer['paymentProfiles']['customerPaymentProfileId'];
        }

        if (isset($authorize_net_customer['newCustomerPaymentProfileId'])) {
            $paymentProfile = $authorize_net_customer['newCustomerPaymentProfileId'];
        }

        if (empty($paymentProfile)) {
            throw new MeprException(__('Profile does not have a payment source', 'memberpress'));
        }

        $xml = '<createTransactionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
    <merchantAuthentication>
     <name>' . esc_xml($this->login_name) . '</name>
     <transactionKey>' . esc_xml($this->transaction_key) . '</transactionKey>
    </merchantAuthentication>
    <refId>' . esc_xml($txn->id) . '</refId>
    <transactionRequest>
        <transactionType>authCaptureTransaction</transactionType>
        <amount>' . esc_xml($txn->total) . '</amount>
        <profile>
           <customerProfileId>' . esc_xml($authorize_net_customer['customerProfileId']) . '</customerProfileId>
          <paymentProfile>
            <paymentProfileId>' . esc_xml($paymentProfile) . '</paymentProfileId>'
            . ($cvc_code ? '<cardCode>' . esc_xml($cvc_code) . '</cardCode>' : '') .
          '</paymentProfile>
        </profile>
        <poNumber>' . esc_xml($txn->id) . '</poNumber>
        <customer>
            <id>' . esc_xml($authorize_net_customer['customerProfileId']) . '</id>
        </customer>
        <customerIP>' . esc_xml($_SERVER['REMOTE_ADDR']) . '</customerIP>
        <authorizationIndicatorType>
            <authorizationIndicator>' . ($capture ? 'final' : 'pre') . '</authorizationIndicator>
        </authorizationIndicatorType>
    </transactionRequest>
</createTransactionRequest>';

        $this->log($xml);
        $response = wp_remote_post($this->endpoint, $this->prepareOptions($xml));
        $response = wp_remote_retrieve_body($response);
        $response = $this->parseAuthnetResponse($response);
        $this->log($response);

        if (
            isset($response['messages']['resultCode'])
            && $response['messages']['resultCode'] == 'Ok'
            && $response['transactionResponse']['responseCode'] == 1
            && ! isset($response['transactionResponse']['errors'])
        ) {
            $trans_num = $response['transactionResponse']['transId'];
            $last4     = substr($response['transactionResponse']['accountNumber'], - 4);
            $txn->update_meta('cc_last4', $last4);

            return $trans_num;
        } else {
            if (isset($response['transactionResponse']['errors']['error']['errorText'])) {
                throw new MeprException(__($response['transactionResponse']['errors']['error']['errorText'], 'memberpress'));
            }
            throw new MeprException(__('Can not complete the payment.', 'memberpress'));
        }
    }

    /**
     * Creates a customer payment profile.
     *
     * @param WP_User $user                  The WordPress user object.
     * @param array   $authorizenet_customer The Authorize.net customer profile data.
     * @param string  $dataValue             The data value for the payment profile.
     * @param string  $dataDesc              The data descriptor for the payment profile.
     *
     * @return string|null The customer payment profile ID or null on failure.
     */
    public function createCustomerPaymentProfile($user, $authorizenet_customer, $dataValue, $dataDesc)
    {
        if (empty($dataValue) || empty($dataDesc)) {
            return null;
        }

        $address = [
            'line1'       => get_user_meta($user->ID, 'mepr-address-one', true),
            'line2'       => get_user_meta($user->ID, 'mepr-address-two', true),
            'city'        => get_user_meta($user->ID, 'mepr-address-city', true),
            'state'       => get_user_meta($user->ID, 'mepr-address-state', true),
            'country'     => get_user_meta($user->ID, 'mepr-address-country', true),
            'postal_code' => get_user_meta($user->ID, 'mepr-address-zip', true),
        ];
        $xml     = '<createCustomerPaymentProfileRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
    <merchantAuthentication>
        <name>' . esc_xml($this->login_name) . '</name>
        <transactionKey>' . esc_xml($this->transaction_key) . '</transactionKey>
    </merchantAuthentication>
    <customerProfileId>' . esc_xml($authorizenet_customer['customerProfileId']) . '</customerProfileId>
    <paymentProfile>
        <billTo>
          <firstName>' . esc_xml($user->first_name) . '</firstName>
          <lastName>' . esc_xml($user->last_name) . '</lastName>
          <company></company>
          <address>' . esc_xml($address['line1']) . '</address>
          <city>' . esc_xml($address['city']) . '</city>
          <state>' . esc_xml($address['state']) . '</state>
          <zip>' . esc_xml($address['postal_code']) . '</zip>
          <country>' . esc_xml($address['country']) . '</country>
        </billTo>
        <payment>
          <opaqueData>
            <dataDescriptor>' . esc_xml($dataDesc) . '</dataDescriptor>
            <dataValue>' . esc_xml($dataValue) . '</dataValue>
          </opaqueData>
         </payment>
        <defaultPaymentProfile>true</defaultPaymentProfile>
    </paymentProfile>
</createCustomerPaymentProfileRequest>';

        $cacheKey = md5(serialize($xml));

        if (isset($this->cache[ $cacheKey ])) {
            return $this->cache[ $cacheKey ];
        }

        $response = wp_remote_post($this->endpoint, $this->prepareOptions($xml));
        $response = wp_remote_retrieve_body($response);
        $response = $this->parseAuthnetResponse($response);
        $this->log($xml);
        $this->log($response);

        if (isset($response['messages']['resultCode']) && $response['messages']['resultCode'] == 'Ok') {
            $this->cache[ $cacheKey ] = $response['customerPaymentProfileId'];

            return $response['customerPaymentProfileId'];
        } elseif (isset($response['messages']['message']['code']) && $response['messages']['message']['code'] == 'E00039') {
            if ($response['messages']['message']['text'] == 'A duplicate customer payment profile already exists.') {
                $this->cache[ $cacheKey ] = $response['customerPaymentProfileId'];

                return $response['customerPaymentProfileId'];
            }

            $this->cache[ $cacheKey ] = null;
            return null;
        }
    }

    /**
     * Cancels a subscription.
     *
     * @param string $subscription_id The ID of the subscription to cancel.
     *
     * @return string The subscription ID.
     * @throws MeprException If the subscription cannot be canceled.
     */
    public function cancelSubscription($subscription_id)
    {
        $xml = '<ARBCancelSubscriptionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
    <merchantAuthentication>
        <name>' . esc_xml($this->login_name) . '</name>
        <transactionKey>' . esc_xml($this->transaction_key) . '</transactionKey>
    </merchantAuthentication>
    <refId>' . esc_xml($subscription_id) . '-cancel</refId>
    <subscriptionId>' . esc_xml($subscription_id) . '</subscriptionId>
</ARBCancelSubscriptionRequest>';

        $response = wp_remote_post($this->endpoint, $this->prepareOptions($xml));
        $response = wp_remote_retrieve_body($response);
        $response = $this->parseAuthnetResponse($response);
        $this->log($xml);
        $this->log($response);

        if (isset($response['messages']['resultCode']) && $response['messages']['resultCode'] == 'Ok') {
            return $subscription_id;
        } else {
            throw new MeprException(__('Can not cancel subscription', 'memberpress'));
        }
    }

    /**
     * Converts an array to XML.
     *
     * @param  SimpleXMLElement $simpleXml The SimpleXMLElement object.
     * @param  array            $array     The array to convert.
     * @return SimpleXMLElement The updated SimpleXMLElement object.
     */
    public function array2Xml($simpleXml, $array)
    {
        foreach ($array as $key => $item) {
            if (!is_array($item)) {
                $simpleXml->addChild($key, esc_xml($item));
            } else {
                $child = $simpleXml->addChild($key);
                $this->array2Xml($child, $item);
            }
        }
        return $simpleXml;
    }

    /**
     * Updates a subscription.
     *
     * @param  array $args The arguments for updating the subscription.
     * @return mixed The response from the API.
     * @throws MeprException If the subscription cannot be updated.
     */
    public function updateSubscription($args)
    {
        $xmlStr    = <<<XML
<ARBUpdateSubscriptionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
</ARBUpdateSubscriptionRequest>
XML;
        $simpleXml = @new SimpleXMLElement($xmlStr);
        $auth      = $simpleXml->addChild('merchantAuthentication');
        $auth->addChild('name', esc_xml($this->login_name));
        $auth->addChild('transactionKey', esc_xml($this->transaction_key));
        $this->array2Xml($simpleXml, $args);
        $xml      = $simpleXml->asXML();
        $response = wp_remote_post($this->endpoint, $this->prepareOptions($xml));
        $response = wp_remote_retrieve_body($response);
        $response = $this->parseAuthnetResponse($response);
        $this->log($xml);
        $this->log($response);

        if (isset($response['messages']['resultCode']) && $response['messages']['resultCode'] == 'Ok') {
            return $response;
        } else {
            throw new MeprException(__('Can not update subscription', 'memberpress'));
        }
    }

    /**
     * Creates a subscription from a customer profile.
     *
     * @param array            $authorizenet_customer The Authorize.net customer profile data.
     * @param MeprTransaction  $txn                   The transaction object.
     * @param MeprSubscription $sub                   The subscription object.
     *
     * @return string The subscription ID.
     * @throws MeprException If the subscription cannot be created.
     */
    public function createSubscriptionFromCustomer($authorizenet_customer, $txn, $sub)
    {
        $this->log('Creating sub');
        $this->log($sub);
        if ($sub->period_type == 'weeks') {
            $length = $sub->period * 7;
            $type   = 'days';
        } elseif ($sub->period_type == 'years') {
            $length = $sub->period * 365;
            $type   = 'days';
        } else {
            $length = $sub->period;
            $type   = $sub->period_type;
        }

        $start_date = date('Y-m-d', strtotime($sub->created_at));

        if (empty($sub->limit_cycles)) {
            $total_cycles = 9999;
        } else {
            $total_cycles = (int) $sub->limit_cycles_num;
        }

        if ($sub->trial == 1) {
            $txn->set_subtotal($sub->trial_amount);
            $txn->total      = $sub->trial_total;
            $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days));
            $this->log($txn);
            $txn->store();

            if (empty((float) $txn->total)) {
                $txn_num        = $txn->trans_num;
                $txn->txn_type  = \MeprTransaction::$subscription_confirmation_str;
                $txn->status    = MeprTransaction::$confirmed_str;
                $txn->trans_num = $txn_num;
                $txn->store();
            } else {
                $txn_num = $this->chargeCustomer($authorizenet_customer, $txn);

                if ($txn_num) {
                    $txn->txn_type  = \MeprTransaction::$payment_str;
                    $txn->status    = MeprTransaction::$complete_str;
                    $txn->trans_num = $txn_num;
                    $txn->store();
                }
            }

            $start_date = date('Y-m-d', strtotime($sub->created_at) + MeprUtils::days($sub->trial_days));
        }

        if (defined('MERP_AUTHORIZENET_TESTING')) {
            $length = 1;
        }

        if (isset($authorizenet_customer['paymentProfiles']['customerPaymentProfileId'])) {
            $payment_profile_id = $authorizenet_customer['paymentProfiles']['customerPaymentProfileId'];
        } elseif (isset($authorizenet_customer['paymentProfiles'][0]['customerPaymentProfileId'])) {
            $payment_profile_id = $authorizenet_customer['paymentProfiles'][0]['customerPaymentProfileId'];
        } else {
            $payment_profile_id = '';
        }

        $amount = $sub->total;

        $xml = '<ARBCreateSubscriptionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
  <merchantAuthentication>
   <name>' . esc_xml($this->login_name) . '</name>
   <transactionKey>' . esc_xml($this->transaction_key) . '</transactionKey>
  </merchantAuthentication>
  <refId>mpsub' . esc_xml($sub->id . '-' . $txn->id) . '</refId>
  <subscription>
    <name>' . esc_xml($sub->product()->post_title) . '</name>
    <paymentSchedule>
      <interval>
        <length>' . esc_xml($length) . '</length>
        <unit>' . esc_xml($type) . '</unit>
      </interval>
      <startDate>' . esc_xml($start_date) . '</startDate>
      <totalOccurrences>' . esc_xml($total_cycles) . '</totalOccurrences>
    </paymentSchedule>
    <amount>' . esc_xml($amount) . '</amount>
    <profile>
      <customerProfileId>' . esc_xml($authorizenet_customer['customerProfileId']) . '</customerProfileId>
      <customerPaymentProfileId>' . esc_xml($payment_profile_id) . '</customerPaymentProfileId>
    </profile>
  </subscription>
</ARBCreateSubscriptionRequest>';

        $response = wp_remote_post($this->endpoint, $this->prepareOptions($xml));
        $response = wp_remote_retrieve_body($response);
        $response = $this->parseAuthnetResponse($response);
        $this->log($xml);
        $this->log($response);
        if (isset($response['subscriptionId'])) {
            return $response['subscriptionId'];
        } else {
            $message_code = $response['messages']['message']['code'] ?? '';
            $message      = $response['messages']['message']['text'] ?? '';

            if ($message_code == 'E00012') {
                throw new MeprException(__('You have subscribed to a membership which has the same pricing term. Subscription can not be created with Authorize.net', 'memberpress'));
            }

            throw new MeprException(__($message, 'memberpress'));
        }

        return $response;
    }

    /**
     * Charges a customer using the provided card details.
     *
     * @param array           $authorize_net_customer The Authorize.net customer profile data.
     * @param MeprTransaction $txn                    The transaction object.
     * @param string          $dataDesc               The data descriptor for the card.
     * @param string          $dataValue              The data value for the card.
     *
     * @return string The transaction number.
     * @throws MeprException If the charge cannot be processed.
     */
    public function chargeCustomerCard($authorize_net_customer, $txn, $dataDesc, $dataValue)
    {
        $user    = $txn->user();
        $address = [
            'line1'       => get_user_meta($user->ID, 'mepr-address-one', true),
            'line2'       => get_user_meta($user->ID, 'mepr-address-two', true),
            'city'        => get_user_meta($user->ID, 'mepr-address-city', true),
            'state'       => get_user_meta($user->ID, 'mepr-address-state', true),
            'country'     => get_user_meta($user->ID, 'mepr-address-country', true),
            'postal_code' => get_user_meta($user->ID, 'mepr-address-zip', true),
        ];
        $this->log($authorize_net_customer);
        $xml = '<createTransactionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
    <merchantAuthentication>
     <name>' . esc_xml($this->login_name) . '</name>
     <transactionKey>' . esc_xml($this->transaction_key) . '</transactionKey>
    </merchantAuthentication>
    <refId>' . esc_xml($txn->id) . '</refId>
    <transactionRequest>
        <transactionType>authCaptureTransaction</transactionType>
        <amount>' . esc_xml($txn->total) . '</amount>
        <payment>
          <opaqueData>
            <dataDescriptor>' . esc_xml($dataDesc) . '</dataDescriptor>
            <dataValue>' . esc_xml($dataValue) . '</dataValue>
          </opaqueData>
         </payment>
        <poNumber>' . esc_xml($txn->id) . '</poNumber>
        <customer>
            <id>' . esc_xml($authorize_net_customer['customerProfileId']) . '</id>
        </customer>
        <billTo>
          <firstName>' . esc_xml($user->first_name) . '</firstName>
          <lastName>' . esc_xml($user->last_name) . '</lastName>
          <company></company>
          <address>' . esc_xml($address['line1']) . '</address>
          <city>' . esc_xml($address['city']) . '</city>
          <state>' . esc_xml($address['state']) . '</state>
          <zip>' . esc_xml($address['postal_code']) . '</zip>
          <country>' . esc_xml($address['country']) . '</country>
        </billTo>
        <customerIP>' . esc_xml($_SERVER['REMOTE_ADDR']) . '</customerIP>
        <authorizationIndicatorType>
            <authorizationIndicator>final</authorizationIndicator>
        </authorizationIndicatorType>
    </transactionRequest>
</createTransactionRequest>';

        $response = wp_remote_post($this->endpoint, $this->prepareOptions($xml));
        $response = wp_remote_retrieve_body($response);
        $response = $this->parseAuthnetResponse($response);

        if (
            isset($response['messages']['resultCode'])
            && $response['messages']['resultCode'] == 'Ok'
            && $response['transactionResponse']['responseCode'] == 1
            && ! isset($response['transactionResponse']['errors'])
        ) {
            $trans_num = $response['transactionResponse']['transId'];
            $last4     = substr($response['transactionResponse']['accountNumber'], - 4);
            $txn->update_meta('cc_last4', $last4);

            return $trans_num;
        } else {
            if (isset($response['transactionResponse']['errors']['error']['errorText'])) {
                throw new MeprException(__($response['transactionResponse']['errors']['error']['errorText'], 'memberpress'));
            }
            throw new MeprException(__('Can not complete the payment', 'memberpress'));
        }
    }

    /**
     * Creates a customer profile.
     *
     * @param WP_User $user      The WordPress user object.
     * @param string  $dataValue The data value for the customer profile.
     * @param string  $dataDesc  The data descriptor for the customer profile.
     *
     * @return array|null The response from the API or null on failure.
     */
    public function createCustomerProfile($user, $dataValue, $dataDesc)
    {
        $address = [
            'line1'       => get_user_meta($user->ID, 'mepr-address-one', true),
            'line2'       => get_user_meta($user->ID, 'mepr-address-two', true),
            'city'        => get_user_meta($user->ID, 'mepr-address-city', true),
            'state'       => get_user_meta($user->ID, 'mepr-address-state', true),
            'country'     => get_user_meta($user->ID, 'mepr-address-country', true),
            'postal_code' => get_user_meta($user->ID, 'mepr-address-zip', true),
        ];

        // First name and last name are required for recurring payment so if they are disabled
        // in MP we need a placeholder
        $first_name = empty($user->first_name) ? 'Customer' : $user->first_name;
        $last_name  = empty($user->last_name) ? 'Customer' : $user->last_name;
        $xml        = '<createCustomerProfileRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
   <merchantAuthentication>
     <name>' . esc_xml($this->login_name) . '</name>
     <transactionKey>' . esc_xml($this->transaction_key) . '</transactionKey>
    </merchantAuthentication>
   <profile>
     <merchantCustomerId>' . esc_xml($user->ID) . '</merchantCustomerId>
     <description>MemberPress Customer</description>
     <email>' . esc_xml($user->user_email) . '</email>
     <paymentProfiles>
       <customerType>individual</customerType>
        <billTo>
          <firstName>' . esc_xml($first_name) . '</firstName>
          <lastName>' . esc_xml($last_name) . '</lastName>
          <company></company>
          <address>' . esc_xml($address['line1']) . '</address>
          <city>' . esc_xml($address['city']) . '</city>
          <state>' . esc_xml($address['state']) . '</state>
          <zip>' . esc_xml($address['postal_code']) . '</zip>
          <country>' . esc_xml($address['country']) . '</country>
        </billTo>
        <payment>
          <opaqueData>
            <dataDescriptor>' . esc_xml($dataDesc) . '</dataDescriptor>
            <dataValue>' . esc_xml($dataValue) . '</dataValue>
          </opaqueData>
         </payment>
      </paymentProfiles>
    </profile>
  </createCustomerProfileRequest>';

        $response = wp_remote_post($this->endpoint, $this->prepareOptions($xml));
        $response = wp_remote_retrieve_body($response);
        $response = $this->parseAuthnetResponse($response);

        $this->log($xml);
        $this->log($response);

        if (isset($response['customerProfileId'])) {
            update_user_meta($user->ID, 'mepr_authorizenet_profile_id_' . $this->gatewayID, $response['customerProfileId']);

            return $response;
        } else {
            if (isset($response['messages']['message']['code']) && $response['messages']['message']['code'] == 'E00039') {
                throw new MeprGatewayException(__('Your email is already registered on the gateway. Please contact us.', 'memberpress'));
            }

            return null;
        }
    }

    /**
     * Parses the Authorize.net response.
     *
     * @param string  $response The XML response from the API.
     * @param boolean $object   Whether to return the response as an object.
     *
     * @return array|object The parsed response.
     */
    protected function parseAuthnetResponse($response, $object = false)
    {
        $response = @simplexml_load_string($response);

        if ($object) {
            return @json_decode(json_encode((array) $response), false);
        }

        return @json_decode(json_encode((array) $response), true);
    }

    /*
     * Alias to getTransactionDetails
     */
    public function get_transaction_details($transactionId)
    {
        return $this->getTransactionDetails($transactionId);
    }

    /**
     * Retrieves transaction details.
     *
     * @param string $transactionId The ID of the transaction.
     *
     * @return object|null The transaction details or null on failure.
     */
    public function getTransactionDetails($transactionId)
    {
        $xml = '
<getTransactionDetailsRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
      <merchantAuthentication>
         <name>' . esc_xml($this->login_name) . '</name>
         <transactionKey>' . esc_xml($this->transaction_key) . '</transactionKey>
      </merchantAuthentication>
      <transId>' . esc_xml($transactionId) . '</transId>
</getTransactionDetailsRequest>';

        $response = wp_remote_post($this->endpoint, $this->prepareOptions($xml));
        $response = wp_remote_retrieve_body($response);

        $data = $this->parseAuthnetResponse($response, true);

        if (isset($data->transaction)) {
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Retrieves a customer profile.
     *
     * @param integer $userID The ID of the WordPress user.
     *
     * @return array|null The customer profile data or null on failure.
     */
    public function getCustomerProfile($userID)
    {
        $meta = get_user_meta($userID, 'mepr_authorizenet_profile_id_' . $this->gatewayID, true);

        if (empty($meta)) {
            return null;
        }

        $xml = '<getCustomerProfileRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
  <merchantAuthentication>
    <name>' . esc_xml($this->login_name) . '</name>
    <transactionKey>' . esc_xml($this->transaction_key) . '</transactionKey>
  </merchantAuthentication>
  <customerProfileId>' . esc_xml($meta) . '</customerProfileId>
  <includeIssuerInfo>true</includeIssuerInfo>
</getCustomerProfileRequest>';

        $cacheKey = md5(serialize($xml));

        if (isset($this->cache[ $cacheKey ])) {
            return $this->cache[ $cacheKey ];
        }

        $response = wp_remote_post($this->endpoint, $this->prepareOptions($xml));
        $response = wp_remote_retrieve_body($response);
        $this->log($xml);
        $this->log($response);

        $data = $this->parseAuthnetResponse($response);

        if (isset($data['profile'])) {
            $this->cache[ $cacheKey ] = $data['profile'];

            return $data['profile'];
        } else {
            return null;
        }
    }

    /**
     * Prepares options for the HTTP request.
     *
     * @param string $args The XML string to send in the request body.
     *
     * @return array The prepared options for the request.
     */
    protected function prepareOptions($args)
    {
        $options = [
            'body'        => $args,
            'headers'     => [
                'Content-Type' => 'application/xml; charset=utf-8',
            ],
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => true,
            'data_format' => 'body',
        ];

        return $options;
    }
}
