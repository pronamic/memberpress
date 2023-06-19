jQuery(function ($) {
  let $stripePaymentForm = $('#mepr-stripe-payment-form'),
      $cardElement = $('#card-element'),
      billingDetails = getBillingDetails(),
      stripe = Stripe(MeprStripeAccountForm.public_key, {
        locale: $cardElement.data('locale-code').toLowerCase(),
        apiVersion: MeprStripeAccountForm.api_version
      }),
      elements = stripe.elements({
        mode: 'setup',
        currency: MeprStripeAccountForm.currency,
        paymentMethodTypes: MeprStripeAccountForm.payment_method_types,
        appearance: MeprStripeAccountForm.elements_appearance
      }),
      paymentElement = elements.create('payment', {
        defaultValues: {
          billingDetails: billingDetails
        },
        terms: MeprStripeAccountForm.payment_element_terms
      }),
      submitting = false;

  paymentElement.mount($cardElement[0]);

  paymentElement.on('loaderror', function (e) {
    if (e.error) {
      handleError(e.error.message);
    }
  });

  $stripePaymentForm.on('submit', async function (e) {
    e.preventDefault();

    if (submitting) {
      return;
    }

    submitting = true;

    const {error} = await elements.submit();

    if (error) {
      handleError(error.message);
      submitting = false;
      return;
    }

    $stripePaymentForm.find('.mepr-submit').prop('disabled', true);
    $stripePaymentForm.find('.mepr-loading-gif').show();

    const formData = new FormData($stripePaymentForm.get(0));

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
    .done(async function (response) {
      if (response && typeof response.success === 'boolean') {
        if (response.success) {
          const { error } = await stripe.confirmSetup({
            elements: elements,
            clientSecret: response.data,
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
        } else {
          handleError(response.data);
        }
      } else {
        handleError('Invalid response');
      }
    })
    .fail(function () {
      handleError('Request failed');
    })
    .always(function () {
      submitting = false;
    });
  });

  /**
   * Get the billing details object to pass to Stripe
   *
   * @return {object}
   */
  function getBillingDetails() {
    let email = $cardElement.data('user-email'),
        name = [],
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

    if (email && email.length) {
      details.email = email;
    }

    $.each(['card-first-name', 'card-last-name'], function (index, value) {
      let $field = $stripePaymentForm.find('input[name="' + value + '"]');

      if ($field.length) {
        let val = $field.val();

        if (typeof val == 'string' && val.length) {
          name.push(val);
        }
      }
    });

    if (name.length) {
      details.name = name.join(' ');
    }

    $.each(keys, function (key, value) {
      let $field = $stripePaymentForm.find('input[name="' + value + '"]');

      if ($field.length) {
        let val = $field.val();

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

    // Inform the user that there was an error
    $('#card-errors').text(message);
  }
});
