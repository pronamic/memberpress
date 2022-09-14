jQuery(function ($) {
  var $stripePaymentForm = $('#mepr-stripe-payment-form'),
      $cardElement = $('#card-element'),
      formData = new FormData($stripePaymentForm.get(0)),
      $loader = $stripePaymentForm.find('.mepr-stripe-payment-element-loading').show(),
      $linkElement = $stripePaymentForm.find('.mepr-stripe-link-element');

  formData.append('action', 'mepr_stripe_create_account_setup_intent');
  formData.append('subscription_id', $stripePaymentForm.data('sub-id'));

  $.ajax({
    type: 'POST',
    url: MeprStripeAccountForm.ajax_url,
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json'
  })
  .done(function (response) {
    if (response && typeof response.success === 'boolean') {
      if (response.success) {
        $loader.hide();

        var billingDetails = getBillingDetails(),
            stripe = Stripe(MeprStripeAccountForm.public_key, {
              locale: $cardElement.data('locale-code').toLowerCase(),
              apiVersion: MeprStripeAccountForm.api_version
            });

        var options = {
          clientSecret: response.data
        };

        if ($.isPlainObject(MeprStripeAccountForm.elements_appearance)) {
          options.appearance = MeprStripeAccountForm.elements_appearance;
        }

        var elements = stripe.elements(options);

        if ($linkElement.length) {
          var linkAuthenticationElement = elements.create('linkAuthentication', {
            defaultValues: {
              email: $linkElement.data('stripe-email')
            }
          });

          linkAuthenticationElement.mount($linkElement[0]);
        }

        var paymentElement = elements.create('payment', {
          defaultValues: {
            billingDetails: billingDetails
          },
          terms: {
            card: 'never'
          }
        });

        paymentElement.mount($cardElement[0]);

        $stripePaymentForm.on('submit', async function (e) {
          e.preventDefault();

          $stripePaymentForm.find('.mepr-form-has-errors').hide();
          $stripePaymentForm.find('.mepr-submit').prop('disabled', true);
          $stripePaymentForm.find('.mepr-loading-gif').show();

          const { error } = await stripe.confirmSetup({
            elements: elements,
            confirmParams: {
              return_url: MeprStripeAccountForm.return_url,
              payment_method_data: {
                billing_details: billingDetails
              }
            }
          });

          if (error) {
            handleError(error.message);
          }
        });
      } else {
        handleCreatePaymentElementError(response.data);
      }
    } else {
      handleCreatePaymentElementError('Invalid response');
    }
  })
  .fail(function () {
    handleCreatePaymentElementError('Request failed');
  });

  /**
   * Handle an error creating the payment element
   *
   * @param {string} message The error message to display
   */
  function handleCreatePaymentElementError(message) {
    $loader.hide();

    handleError(message);

    $stripePaymentForm.on('submit', function (e) {
      e.preventDefault();
      alert('Please refresh the page to try again');
    });
  }

  /**
   * Get the billing details object to pass to Stripe
   *
   * @return {object}
   */
  function getBillingDetails() {
    var name = [],
        keys = {
          line1: 'card-address-one',
          line2: 'card-address-two',
          city: 'card-address-city',
          country: 'card-address-country',
          state: 'card-address-state',
          postal_code: 'card-address-zip'
        },
        details = {
          address: {}
        };

    $.each(['card-first-name', 'card-last-name'], function (index, value) {
      var $field = $stripePaymentForm.find('input[name="' + value + '"]');

      if ($field.length) {
        var val = $field.val();

        if (typeof val == 'string' && val.length) {
          name.push(val);
        }
      }
    });

    if (name.length) {
      details.name = name.join(' ');
    }

    $.each(keys, function (key, value) {
      var $field = $stripePaymentForm.find('input[name="' + value + '"]');

      if ($field.length) {
        var val = $field.val();

        if (typeof val == 'string' && val.length) {
          details.address[key] = val;
        }
      }
    });

    return details;
  }

  /**
   * Handle an error
   *
   * @param {string} message The error message to display
   */
  function handleError(message) {
    // re-enable the submit button
    $stripePaymentForm.find('.mepr-submit').prop('disabled', false);
    $stripePaymentForm.find('.mepr-loading-gif').hide();
    $stripePaymentForm.find('.mepr-form-has-errors').show();

    // Inform the user that there was an error
    $('#card-errors').text(message);
  }
});
