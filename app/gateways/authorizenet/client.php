<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );
}

class MeprArtificialAuthorizeNetProfileHttpClient {
  protected $is_test;
  protected $endpoint;
  protected $gatewayID;
  protected $login_name;
  protected $transaction_key;
  protected $cache = [];

  public function __construct( $is_test, $endpoint, $gatewayID, $login_name, $transaction_key ) {
    $this->is_test         = $is_test;
    $this->endpoint        = $endpoint;
    $this->endpoint        = $endpoint;
    $this->gatewayID       = $gatewayID;
    $this->login_name      = $login_name;
    $this->transaction_key = $transaction_key;
  }

  public function log( $data ) {
    if ( ! defined( 'WP_MEPR_DEBUG' ) ) {
      return;
    }

    file_put_contents( WP_CONTENT_DIR . '/authorize-net.log', print_r( $data, true ) . PHP_EOL, FILE_APPEND );
  }

  /**
   * @param MeprTransaction $txn
   *
   * @return mixed
   * @throws MeprException
   */
  public function refundTransaction( $txn ) {
    $product = $txn->product();

    if ( $product->is_one_time_payment() ) {
      $last4cc = $txn->get_meta( 'cc_last4', true );
    } else {
      $subscription = $txn->subscription();
      $last4cc      = $subscription->cc_last4;
    }

    $xml = '<createTransactionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
  <merchantAuthentication>
     <name>' . $this->login_name . '</name>
     <transactionKey>' . $this->transaction_key . '</transactionKey>
  </merchantAuthentication>
  <refId>' . $txn->trans_num . '-refund</refId>
  <transactionRequest>
    <transactionType>refundTransaction</transactionType>
    <amount>' . $txn->total . '</amount>
    <payment>
      <creditCard>
        <cardNumber>' . $last4cc . '</cardNumber>
        <expirationDate>XXXX</expirationDate>
      </creditCard>
    </payment>
    <refTransId>' . $txn->trans_num . '</refTransId>
  </transactionRequest>
</createTransactionRequest>';
    $this->log( $xml );
    $response = wp_remote_post( $this->endpoint, $this->prepareOptions( $xml ) );
    $response = wp_remote_retrieve_body( $response );
    $response = $this->parseAuthnetResponse( $response );
    $this->log( $response );

    if ( isset( $response['messages']['resultCode'] ) && $response['messages']['resultCode'] == 'Ok' ) {
      $trans_num = $response['transactionResponse']['transId'];

      return $trans_num;
    } else {
      throw new MeprException( __( 'Can not refund the payment. The transaction may not have been settled', 'memberpress' ) );
    }
  }

  /**
   * @param $authorize_net_customer
   * @param MeprTransaction $txn
   *
   * @throws Exception
   */
  public function chargeCustomer( $authorize_net_customer, $txn ) {
    $this->log( $authorize_net_customer );
    $xml = '<createTransactionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
    <merchantAuthentication>
     <name>' . $this->login_name . '</name>
     <transactionKey>' . $this->transaction_key . '</transactionKey>
    </merchantAuthentication>
    <refId>' . $txn->id . '</refId>
    <transactionRequest>
        <transactionType>authCaptureTransaction</transactionType>
        <amount>' . $txn->total . '</amount>
        <profile>
           <customerProfileId>' . $authorize_net_customer['customerProfileId'] . '</customerProfileId>
          <paymentProfile>
            <paymentProfileId>' . $authorize_net_customer["paymentProfiles"]["customerPaymentProfileId"] . '</paymentProfileId>
          </paymentProfile>
        </profile>
        <poNumber>' . $txn->id . '</poNumber>
        <customer>
            <id>' . $authorize_net_customer['customerProfileId'] . '</id>
        </customer>
        <customerIP>' . $_SERVER['REMOTE_ADDR'] . '</customerIP>
        <authorizationIndicatorType>
            <authorizationIndicator>final</authorizationIndicator>
        </authorizationIndicatorType>
    </transactionRequest>
</createTransactionRequest>';
    $this->log( $xml );
    $response = wp_remote_post( $this->endpoint, $this->prepareOptions( $xml ) );
    $response = wp_remote_retrieve_body( $response );
    $response = $this->parseAuthnetResponse( $response );
    $this->log( $response );

    if ( isset( $response['messages']['resultCode'] ) && $response['messages']['resultCode'] == 'Ok' ) {
      $trans_num = $response['transactionResponse']['transId'];
      $last4     = substr( $response['transactionResponse']['accountNumber'], - 4 );
      $txn->update_meta( 'cc_last4', $last4 );

      return $trans_num;
    } else {
      throw new MeprException( __( 'Can not complete the payment.', 'memberpress' ) );
    }
  }

  public function createCustomerPaymentProfile( $user, $authorizenet_customer, $dataValue, $dataDesc ) {
    $mode = $this->is_test ? "testMode" : 'liveMode';

    if (empty($dataValue) || empty($dataDesc)) {
      return null;
    }

    $address = [
      'line1'       => get_user_meta( $user->ID, 'mepr-address-one', true ),
      'line2'       => get_user_meta( $user->ID, 'mepr-address-two', true ),
      'city'        => get_user_meta( $user->ID, 'mepr-address-city', true ),
      'state'       => get_user_meta( $user->ID, 'mepr-address-state', true ),
      'country'     => get_user_meta( $user->ID, 'mepr-address-country', true ),
      'postal_code' => get_user_meta( $user->ID, 'mepr-address-zip', true )
    ];
    $xml     = '<createCustomerPaymentProfileRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
    <merchantAuthentication>
        <name>' . $this->login_name . '</name>
        <transactionKey>' . $this->transaction_key . '</transactionKey>
    </merchantAuthentication>
    <customerProfileId>' . $authorizenet_customer['customerProfileId'] . '</customerProfileId>
    <paymentProfile>
        <billTo>
          <firstName>' . $user->first_name . '</firstName>
          <lastName>' . $user->last_name . '</lastName>
          <company></company>
          <address>' . $address['line1'] . '</address>
          <city>' . $address['city'] . '</city>
          <state>' . $address['state'] . '</state>
          <zip>' . $address['postal_code'] . '</zip>
          <country>' . $address['country'] . '</country>
        </billTo>
        <payment>
          <opaqueData>
            <dataDescriptor>' . $dataDesc . '</dataDescriptor>
            <dataValue>' . $dataValue . '</dataValue>
          </opaqueData>
         </payment>
        <defaultPaymentProfile>true</defaultPaymentProfile>
    </paymentProfile>
    <validationMode>' . $mode . '</validationMode>
</createCustomerPaymentProfileRequest>';

    $cacheKey = md5(serialize($xml));

    if ( isset( $this->cache[ $cacheKey ] ) ) {
      return $this->cache[ $cacheKey ];
    }

    $response = wp_remote_post( $this->endpoint, $this->prepareOptions( $xml ) );
    $response = wp_remote_retrieve_body( $response );
    $response = $this->parseAuthnetResponse( $response );
    $this->log( $xml );
    $this->log( $response );

    if ( isset( $response['messages']['resultCode'] ) && $response['messages']['resultCode'] == 'Ok' ) {
      $this->cache[ $cacheKey ] = $response['customerPaymentProfileId'];

      return $response['customerPaymentProfileId'];
    } elseif ( isset( $response['messages']['message']['code'] ) && $response['messages']['message']['code'] == 'E00039' ) {
      $this->cache[ $cacheKey ] = null;
      return null;
    }
  }

  public function cancelSubscription( $subscription_id ) {
    $xml = '<ARBCancelSubscriptionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
    <merchantAuthentication>
        <name>' . $this->login_name . '</name>
        <transactionKey>' . $this->transaction_key . '</transactionKey>
    </merchantAuthentication>
    <refId>' . $subscription_id . '-cancel</refId>
    <subscriptionId>' . $subscription_id . '</subscriptionId>
</ARBCancelSubscriptionRequest>';

    $response = wp_remote_post( $this->endpoint, $this->prepareOptions( $xml ) );
    $response = wp_remote_retrieve_body( $response );
    $response = $this->parseAuthnetResponse( $response );
    $this->log( $xml );
    $this->log( $response );

    if ( isset( $response['messages']['resultCode'] ) && $response['messages']['resultCode'] == 'Ok' ) {
      return $subscription_id;
    } else {
      throw new MeprException( __( 'Can not cancel subscription', 'memberpress' ) );
    }
  }

  public function createSubscriptionFromCustomer( $authorizenet_customer, $txn, $sub ) {
    $this->log( 'Creating sub' );
    $this->log( $sub );
    if ( $sub->period_type == 'weeks' ) {
      $length = $sub->period * 7;
      $type   = 'days';
    } elseif ( $sub->period_type == 'years' ) {
      $length = $sub->period * 365;
      $type   = 'days';
    } else {
      $length = $sub->period;
      $type   = $sub->period_type;
    }

    $start_date = date( 'Y-m-d', strtotime( $sub->created_at ) );

    if ( empty( $sub->limit_cycles ) ) {
      $total_cycles = 9999;
    } else {
      $total_cycles = (int) $sub->limit_cycles_num;
    }

    if ( $sub->trial == 1 ) {
      $txn->set_subtotal( $sub->trial_amount );
      $txn->total = $sub->trial_total;
      $txn->expires_at      = MeprUtils::ts_to_mysql_date( time() + MeprUtils::days( $sub->trial_days ) );
      $this->log( $txn );
      $txn->store();

      if ( empty( (float) $txn->total ) ) {
        $txn_num = $txn->trans_num;
        $txn->txn_type = \MeprTransaction::$subscription_confirmation_str;
        $txn->status    = MeprTransaction::$confirmed_str;
        $txn->trans_num = $txn_num;
        $txn->store();
      } else {
        $txn_num = $this->chargeCustomer( $authorizenet_customer, $txn );

        if ( $txn_num ) {
          $txn->txn_type = \MeprTransaction::$payment_str;
          $txn->status    = MeprTransaction::$complete_str;
          $txn->trans_num = $txn_num;
          $txn->store();
        }
      }

      $start_date = date( 'Y-m-d', strtotime( $sub->created_at ) + MeprUtils::days( $sub->trial_days ) );
    }

    if ( defined( 'MERP_AUTHORIZENET_TESTING' ) ) {
      $length = 1;
    }

    if ( isset( $authorizenet_customer["paymentProfiles"]["customerPaymentProfileId"] ) ) {
      $payment_profile_id = $authorizenet_customer["paymentProfiles"]["customerPaymentProfileId"];
    } elseif ( isset( $authorizenet_customer["paymentProfiles"][0]["customerPaymentProfileId"] ) ) {
      $payment_profile_id = $authorizenet_customer["paymentProfiles"][0]["customerPaymentProfileId"];
    } else {
      $payment_profile_id = '';
    }

    $amount = $sub->total;

    $xml = '<ARBCreateSubscriptionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
  <merchantAuthentication>
   <name>' . $this->login_name . '</name>
   <transactionKey>' . $this->transaction_key . '</transactionKey>
  </merchantAuthentication>
  <refId>mpsub' . $sub->id . '-' . $txn->id . '</refId>
  <subscription>
    <name>' . $sub->product()->post_title . '</name>
    <paymentSchedule>
      <interval>
        <length>' . $length . '</length>
        <unit>' . $type . '</unit>
      </interval>
      <startDate>' . $start_date . '</startDate>
      <totalOccurrences>' . $total_cycles . '</totalOccurrences>
    </paymentSchedule>
    <amount>' . $amount . '</amount>
    <profile>
      <customerProfileId>' . $authorizenet_customer['customerProfileId'] . '</customerProfileId>
      <customerPaymentProfileId>' . $payment_profile_id . '</customerPaymentProfileId>
    </profile>
  </subscription>
</ARBCreateSubscriptionRequest>';

    $response = wp_remote_post( $this->endpoint, $this->prepareOptions( $xml ) );
    $response = wp_remote_retrieve_body( $response );
    $response = $this->parseAuthnetResponse( $response );
    $this->log( $xml );
    $this->log( $response );
    if ( isset( $response["subscriptionId"] ) ) {
      return $response["subscriptionId"];
    } else {
      $message_code = $response['messages']['message']['code'] ?? '';
      $message = $response['messages']['message']['text'] ?? '';

      if ($message_code == 'E00012') {
        throw new MeprException( __( 'You have subscribed to a membership which has the same pricing term. Subscription can not be created with Authorize', 'memberpress' ) );
      }

      throw new MeprException( __( $message, 'memberpress' ) );
    }

    return $response;
  }

  /**
   * @param $authorize_net_customer
   * @param MeprTransaction $txn
   *
   * @throws Exception
   */
  public function chargeCustomerCard( $authorize_net_customer, $txn, $dataDesc, $dataValue ) {
    $user    = $txn->user();
    $address = [
      'line1'       => get_user_meta( $user->ID, 'mepr-address-one', true ),
      'line2'       => get_user_meta( $user->ID, 'mepr-address-two', true ),
      'city'        => get_user_meta( $user->ID, 'mepr-address-city', true ),
      'state'       => get_user_meta( $user->ID, 'mepr-address-state', true ),
      'country'     => get_user_meta( $user->ID, 'mepr-address-country', true ),
      'postal_code' => get_user_meta( $user->ID, 'mepr-address-zip', true )
    ];
    $this->log( $authorize_net_customer );
    $xml = '<createTransactionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
    <merchantAuthentication>
     <name>' . $this->login_name . '</name>
     <transactionKey>' . $this->transaction_key . '</transactionKey>
    </merchantAuthentication>
    <refId>' . $txn->id . '</refId>
    <transactionRequest>
        <transactionType>authCaptureTransaction</transactionType>
        <amount>' . $txn->total . '</amount>
        <payment>
          <opaqueData>
            <dataDescriptor>' . $dataDesc . '</dataDescriptor>
            <dataValue>' . $dataValue . '</dataValue>
          </opaqueData>
         </payment>
        <poNumber>' . $txn->id . '</poNumber>
        <customer>
            <id>' . $authorize_net_customer['customerProfileId'] . '</id>
        </customer>
        <billTo>
          <firstName>' . $user->first_name . '</firstName>
          <lastName>' . $user->last_name . '</lastName>
          <company></company>
          <address>' . $address['line1'] . '</address>
          <city>' . $address['city'] . '</city>
          <state>' . $address['state'] . '</state>
          <zip>' . $address['postal_code'] . '</zip>
          <country>' . $address['country'] . '</country>
        </billTo>
        <customerIP>' . $_SERVER['REMOTE_ADDR'] . '</customerIP>
        <authorizationIndicatorType>
            <authorizationIndicator>final</authorizationIndicator>
        </authorizationIndicatorType>
    </transactionRequest>
</createTransactionRequest>';

    $response = wp_remote_post( $this->endpoint, $this->prepareOptions( $xml ) );
    $response = wp_remote_retrieve_body( $response );
    $response = $this->parseAuthnetResponse( $response );

    if ( isset( $response['messages']['resultCode'] ) && $response['messages']['resultCode'] == 'Ok' ) {
      $trans_num = $response['transactionResponse']['transId'];
      $last4     = substr( $response['transactionResponse']['accountNumber'], - 4 );
      $txn->update_meta( 'cc_last4', $last4 );

      return $trans_num;
    } else {
      throw new MeprException( __( 'Can not complete the payment', 'memberpress' ) );
    }
  }

  public function createCustomerProfile( $user, $dataValue, $dataDesc ) {
    $mode = $this->is_test ? "testMode" : 'liveMode';

    $address = [
      'line1'       => get_user_meta( $user->ID, 'mepr-address-one', true ),
      'line2'       => get_user_meta( $user->ID, 'mepr-address-two', true ),
      'city'        => get_user_meta( $user->ID, 'mepr-address-city', true ),
      'state'       => get_user_meta( $user->ID, 'mepr-address-state', true ),
      'country'     => get_user_meta( $user->ID, 'mepr-address-country', true ),
      'postal_code' => get_user_meta( $user->ID, 'mepr-address-zip', true )
    ];

    // First name and last name are required for recurring payment so if they are disabled
    // in MP we need a placeholder
    $first_name = empty($user->first_name) ? 'Customer' : $user->first_name;
    $last_name = empty($user->last_name) ? 'Customer' : $user->last_name;
    $xml = '<createCustomerProfileRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
   <merchantAuthentication>
     <name>' . $this->login_name . '</name>
     <transactionKey>' . $this->transaction_key . '</transactionKey>
    </merchantAuthentication>
   <profile>
     <merchantCustomerId>' . $user->ID . '</merchantCustomerId>
     <description>MemberPress Customer</description>
     <email>' . $user->user_email . '</email>
     <paymentProfiles>
       <customerType>individual</customerType>
        <billTo>
          <firstName>' . $first_name . '</firstName>
          <lastName>' . $last_name . '</lastName>
          <company></company>
          <address>' . $address['line1'] . '</address>
          <city>' . $address['city'] . '</city>
          <state>' . $address['state'] . '</state>
          <zip>' . $address['postal_code'] . '</zip>
          <country>' . $address['country'] . '</country>
        </billTo>
        <payment>
          <opaqueData>
            <dataDescriptor>' . $dataDesc . '</dataDescriptor>
            <dataValue>' . $dataValue . '</dataValue>
          </opaqueData>
         </payment>
      </paymentProfiles>
    </profile>
  <validationMode>' . $mode . '</validationMode>
  </createCustomerProfileRequest>';

    $response = wp_remote_post( $this->endpoint, $this->prepareOptions( $xml ) );
    $response = wp_remote_retrieve_body( $response );
    $response = $this->parseAuthnetResponse( $response );

    $this->log( $xml );
    $this->log( $response );

    if ( isset( $response['customerProfileId'] ) ) {
      update_user_meta( $user->ID, 'mepr_authorizenet_profile_id_' . $this->gatewayID, $response['customerProfileId'] );

      return $response;
    } else {
      if ( isset( $response["messages"]["message"]["code"] ) && $response["messages"]["message"]["code"] == "E00039" ) {
        throw new MeprGatewayException( __( 'Your email is already registered on the gateway. Please contact us.', 'memberpress' ) );
      }

      return null;
    }
  }

  protected function parseAuthnetResponse( $response, $object = false ) {
    $response = @simplexml_load_string( $response );

    if ( $object ) {
      return @json_decode( json_encode( (array) $response ), false );
    }

    return @json_decode( json_encode( (array) $response ), true );
  }

  /*
   * Alias to getTransactionDetails
   */
  public function get_transaction_details($transactionId) {
    return $this->getTransactionDetails($transactionId);
  }

  public function getTransactionDetails( $transactionId ) {
    $xml = '
<getTransactionDetailsRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
      <merchantAuthentication>
         <name>' . $this->login_name . '</name>
         <transactionKey>' . $this->transaction_key . '</transactionKey>
      </merchantAuthentication>
      <transId>' . $transactionId . '</transId>
</getTransactionDetailsRequest>';

    $response = wp_remote_post( $this->endpoint, $this->prepareOptions( $xml ) );
    $response = wp_remote_retrieve_body( $response );

    $data = $this->parseAuthnetResponse( $response, true );

    if ( isset( $data->transaction ) ) {
      return $data;
    } else {
      return null;
    }
  }

  public function getCustomerProfile( $userID ) {
    $meta = get_user_meta( $userID, 'mepr_authorizenet_profile_id_' . $this->gatewayID, true );

    if ( empty( $meta ) ) {
      return null;
    }

    $xml = '<getCustomerProfileRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
  <merchantAuthentication>
    <name>' . $this->login_name . '</name>
    <transactionKey>' . $this->transaction_key . '</transactionKey>
  </merchantAuthentication>
  <customerProfileId>' . $meta . '</customerProfileId>
  <includeIssuerInfo>true</includeIssuerInfo>
</getCustomerProfileRequest>';
    $cacheKey = md5(serialize($xml));

    if ( isset( $this->cache[ $cacheKey ] ) ) {
      return $this->cache[ $cacheKey ];
    }

    $response = wp_remote_post( $this->endpoint, $this->prepareOptions( $xml ) );
    $response = wp_remote_retrieve_body( $response );
    $this->log($xml);
    $this->log($response);

    $data = $this->parseAuthnetResponse( $response );

    if ( isset( $data['profile'] ) ) {
      $this->cache[ $cacheKey ] = $data['profile'];

      return $data['profile'];
    } else {
      return null;
    }
  }

  protected function prepareOptions( $args ) {
    //$body    = json_encode( $args );
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
