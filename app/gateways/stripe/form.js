(function ($) {
  $(document).ready(function() {
    $('.mepr-signup-form, #mepr-stripe-payment-form').each(function () {
      new MeprStripeForm(this);
    });
  });

  /**
   * The MemberPress Stripe form class
   *
   * @constructor
   * @param {HTMLFormElement} form
   */
  function MeprStripeForm (form) {
    this.form = form;
    this.$form = $(form);
    this.isSpc = this.$form.hasClass('mepr-signup-form');
    this.paymentMethods = [];
    this.selectedPaymentMethod = null;
    this.submitting = false;
    this.updateBillingDetailsTimeout = null;
    this.initPaymentMethods();
    this.$form.on('submit', $.proxy(this.handleSubmit, this));
    this.$form.on('meprAfterCheckoutStateUpdated', $.proxy(this.updateElements, this));
    this.$form.find('input[name="user_email"]').on('change', $.proxy(this.updateBillingDetails, this));
  }

  /**
   * Initialize Stripe elements
   */
  MeprStripeForm.prototype.initPaymentMethods = function () {
    var self = this,
        templateOutdated = false;

    self.$form.find('.mepr-stripe-card-element').each(function () {
      var $cardElement = $(this),
          $cardErrors = $cardElement.closest('.mepr-stripe-elements').find('.mepr-stripe-card-errors'),
          stripe = Stripe($cardElement.data('stripe-public-key'), {
            locale: $cardElement.data('locale-code').toLowerCase(),
            apiVersion: MeprStripeGateway.api_version
          }),
          paymentMethodId = $cardElement.data('payment-method-id'),
          elementsOptions = $cardElement.data('elements-options'),
          wrapperSelector = self.isSpc ? '.mepr-payment-method' : '.mp_payment_form_wrapper',
          $wrapper = $cardElement.closest(wrapperSelector),
          paymentMethod = {
            id: paymentMethodId,
            $cardElement: $cardElement,
            $cardErrors: $cardErrors,
            $wrapper: $wrapper,
            stripe: stripe,
            elements: null,
            paymentElement: null,
            paymentElementComplete: false
          };

      self.paymentMethods.push(paymentMethod);

      if (elementsOptions) {
        self.createElements(paymentMethod, elementsOptions);
      } else if (self.isSpc) {
        templateOutdated = true;
      }
    });

    if (templateOutdated) {
      self.$form.trigger('meprUpdateCheckoutState');
      console.log('Your MemberPress form template is out of date, please update it to the latest version.');
    }
  };

  /**
   * Create an instance of Elements and a Payment Element
   *
   * @param {object} paymentMethod
   * @param options
   */
  MeprStripeForm.prototype.createElements = function (paymentMethod, options) {
    try {
      var self = this

      options = $.extend({
        currency: MeprStripeGateway.currency,
        appearance: MeprStripeGateway.elements_appearance
      }, options);

      paymentMethod.elements = paymentMethod.stripe.elements(options);

      paymentMethod.paymentElement = paymentMethod.elements.create('payment', {
        defaultValues: {
          billingDetails: self.getBillingDetails(paymentMethod)
        },
        terms: MeprStripeGateway.payment_element_terms
      });

      paymentMethod.paymentElement.on('loaderror', function (event) {
        if (event.error) {
          paymentMethod.$cardErrors.html(event.error.message || 'Failed to create Payment element');
        }
      });

      paymentMethod.paymentElement.on('change', function (event) {
        if (typeof event.complete === 'boolean') {
          paymentMethod.paymentElementComplete = event.complete;
        }

        if (typeof event.value === 'object' && typeof event.value.type === 'string') {
          paymentMethod.paymentMethodType = event.value.type;
        } else {
          paymentMethod.paymentMethodType = null;
        }
      });

      paymentMethod.paymentElement.mount(paymentMethod.$cardElement[0]);
    } catch (e) {
      paymentMethod.$cardErrors.html(e.message);
      throw e;
    }
  };

  /**
   * Updates the Elements instance with new options
   *
   * @param {object} event
   * @param {object} response
   */
  MeprStripeForm.prototype.updateElements = function (event, response) {
    var self = this;

    if (response.payment_required && response.elements_options) {
      $.each(response.elements_options, function (paymentMethodId, options) {
        var paymentMethod = self.getPaymentMethodById(paymentMethodId);

        if (paymentMethod) {
          if (paymentMethod.elements) {
            paymentMethod.elements.update(options);
          } else {
            self.createElements(paymentMethod, options);
          }
        }
      });
    }

    self.updateBillingDetails();
  };

  /**
   * Update the billing details on each Payment Element in this form
   */
  MeprStripeForm.prototype.updateBillingDetails = function () {
    var self = this;

    clearTimeout(self.updateBillingDetailsTimeout);

    self.updateBillingDetailsTimeout = setTimeout(function () {
      for (var i = 0; i < self.paymentMethods.length; i++) {
        var paymentMethod = self.paymentMethods[i];

        if (paymentMethod.paymentElement) {
          paymentMethod.paymentElement.update({
            defaultValues: {
              billingDetails: self.getBillingDetails(paymentMethod)
            }
          });
        }
      }
    }, 50);
  };

  /**
   * Handle the payment form submission
   *
   * @param {jQuery.Event} e
   */
  MeprStripeForm.prototype.handleSubmit = async function (e) {
    var self = this;

    if (self.$form.find('.mepr-payment-methods-wrapper').is(':hidden')) {
      return;
    }

    self.selectedPaymentMethod = self.getSelectedPaymentMethod();

    if (self.selectedPaymentMethod) {
      e.preventDefault();

      if (!self.selectedPaymentMethod.paymentElementComplete) {
        self.selectedPaymentMethod.$cardErrors.text(MeprStripeGateway.payment_information_incomplete);
        self.allowResubmission();
        return;
      }

      if (self.submitting) {
        return;
      }

      self.submitting = true;
      self.$form.find('.mepr-form-has-errors').hide();
      self.$form.find('.mepr-submit').prop('disabled', true);
      self.$form.find('.mepr-loading-gif').show();

      self.selectedPaymentMethod.$cardErrors.html('');

      const { error } = await self.selectedPaymentMethod.elements.submit();

      if (error) {
        this.handlePaymentError(error.message);
        return;
      }

      self.processForm();
    }
  };

  /**
   * Get the data for a payment method by ID
   *
   * @param {string} paymentMethodId
   * @return {object|null}
   */
  MeprStripeForm.prototype.getPaymentMethodById = function (paymentMethodId) {
    for (var i = 0; i < this.paymentMethods.length; i++) {
      if (this.paymentMethods[i].id === paymentMethodId) {
        return this.paymentMethods[i];
      }
    }

    return null;
  };

  /**
   * Get the currently selected payment method data
   *
   * @return {object|null}
   */
  MeprStripeForm.prototype.getSelectedPaymentMethod = function () {
    if (this.isSpc) {
      return this.getPaymentMethodById(this.$form.find('input[name="mepr_payment_method"]:checked').val());
    } else {
      return this.paymentMethods.length ? this.paymentMethods[0] : null;
    }
  };

  /**
   * Get the billing details object to pass to Stripe
   *
   * @param  {object} paymentMethod The selected payment method.
   * @return {object}
   */
  MeprStripeForm.prototype.getBillingDetails = function (paymentMethod) {
    var self = this,
        keys = {
          line1: 'mepr-address-one',
          line2: 'mepr-address-two',
          city: 'mepr-address-city',
          country: 'mepr-address-country',
          state: 'mepr-address-state',
          postal_code: 'mepr-address-zip'
        },
        email = '',
        name = [],
        address = {},
        addressFieldsPresent = false,
        details = {
          address: {}
        };

    var $emailField = self.$form.find('input[name="user_email"]');

    if ($emailField.length) {
      email = $emailField.val();
    } else {
      email = paymentMethod.$cardElement.data('user-email');
    }

    if (email && email.length) {
      details.email = email;
    }

    $.each(['user_first_name', 'user_last_name'], function (index, value) {
      var $field = self.$form.find('input[name="' + value + '"]');

      if ($field.length) {
        var val = $field.val();

        if (typeof val == 'string' && val.length) {
          name.push(val);
        }
      }
    });

    if (name.length) {
      details.name = name.join(' ');
    } else {
      $.each(['card-first-name', 'card-last-name'], function (index, value) {
        var $field = paymentMethod.$wrapper.find('input[name="' + value + '"]');

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
    }

    $.each(keys, function (key, value) {
      var $field = self.$form.find('input[name="' + value + '"], select[name="' + value + '"]');

      if ($field.length) {
        var val = $field.val();

        if (typeof val == 'string' && val.length) {
          address[key] = val;
        }

        addressFieldsPresent = true;
      }
    });

    if (addressFieldsPresent) {
      details.address = address;
    } else {
      $.each(keys, function (key, value) {
        var cardAddressKey = value.replace('mepr-', 'card-'),
            $field = paymentMethod.$wrapper.find('input[name="' + cardAddressKey + '"]');

        if ($field.length) {
          var val = $field.val();

          if (typeof val == 'string' && val.length) {
            details.address[key] = val;
          }
        }
      });
    }

    return details;
  };

  /**
   * Allow the form to be submitted again
   */
  MeprStripeForm.prototype.allowResubmission = function () {
    this.submitting = false;
    this.$form.find('.mepr-submit').prop('disabled', false);
    this.$form.find('.mepr-loading-gif').hide();
    this.$form.find('.mepr-form-has-errors').show();
    this.$form.find('.mepr-validation-error, .mepr-top-error').remove();
  };

  /**
   * Handle form validation errors
   *
   * @param {array} errors The validation errors array
   */
  MeprStripeForm.prototype.handleValidationErrors = function (errors) {
    // Allow the form to be submitted again
    this.allowResubmission();

    var topErrors = [];

    for (var key in errors) {
      if (errors.hasOwnProperty(key)) {
        var $field = this.$form.find('[name="' + key + '"]').first(),
            $label = $field.closest('.mp-form-row').find('.mp-form-label');

        if ($.isNumeric(key) || !$label.length) {
          topErrors.push(errors[key]);
        } else {
          $label.append($('<span class="mepr-validation-error">').html(errors[key]));
        }
      }
    }

    if (topErrors.length) {
      var $list = $('<ul>'),
          $wrap = $('<div class="mepr-top-error mepr_error">');

      for (var i = 0; i < topErrors.length; i++) {
        $list.append($('<li>').html(MeprStripeGateway.top_error.replace('%s', topErrors[i])));
      }

      $wrap.append($list).prependTo(this.$form);
    }
  };

  /**
   * Handle an error with the payment
   *
   * @param {string} error The error message to display
   */
  MeprStripeForm.prototype.handlePaymentError = function (error) {
    // Allow the form to be submitted again
    this.allowResubmission();

    // Inform the user if there was an error
    this.selectedPaymentMethod.$cardErrors.html(error);
  };

  /**
   * Handle any SCA actions, and confirms the payment or card setup
   *
   * @param {string} action
   * @param {string} clientSecret
   * @param {string} returnUrl
   */
  MeprStripeForm.prototype.handleAction = async function (action, clientSecret, returnUrl) {
    var self = this,
        stripe = self.selectedPaymentMethod.stripe,
        elements = self.selectedPaymentMethod.elements,
        billingDetails = self.getBillingDetails(self.selectedPaymentMethod),
        confirmParams = {
          return_url: returnUrl,
          payment_method_data: {
            billing_details: billingDetails
          }
        };

    if (self.selectedPaymentMethod.paymentMethodType === 'affirm') {
      // Affirm seems to have issues if the billing address is provided
      delete confirmParams.payment_method_data;
    }

    if (self.selectedPaymentMethod.paymentMethodType === 'afterpay_clearpay') {
      // Afterpay/Clearpay require a shipping address
      confirmParams.shipping = {
        name: billingDetails.name || '',
        address: billingDetails.address
      };
    }

    const { error } = await stripe[action]({
      elements: elements,
      clientSecret: clientSecret,
      confirmParams: confirmParams
    });

    if (error) {
      self.handlePaymentError(error.message);
    }
  };

  /**
   * Process the form.
   */
  MeprStripeForm.prototype.processForm = function () {
    var self = this,
        formData = new FormData(self.form);

    formData.append('action', 'mepr_process_' + (self.isSpc ? 'signup' : 'payment') + '_form');

    // We don't want to hit our routes for processing the signup or payment forms
    formData.delete('mepr_process_signup_form');
    formData.delete('mepr_process_payment_form');

    $.ajax({
      type: 'POST',
      url: MeprStripeGateway.ajax_url,
      data: formData,
      dataType: 'json',
      cache: false,
      processData: false,
      contentType: false,
      headers: {
        'cache-control': 'no-cache'
      }
    })
    .done(function (response, textStatus, jqXHR) {
      if (response === null || typeof response !== 'object' || typeof response.success !== 'boolean') {
        self.handlePaymentError(MeprStripeGateway.invalid_response_error);
        self.debugCheckoutError({
          status: jqXHR.status,
          status_text: jqXHR.statusText,
          response_text: jqXHR.responseText,
          text_status: textStatus,
          error_thrown: 'Response was null or not an object'
        });
      } else {
        if (response.success) {
            self.handleAction(response.data.action, response.data.client_secret, response.data.return_url);
        } else {
            if (response.data.errors) {
                self.handleValidationErrors(response.data.errors);
            } else {
                self.handlePaymentError(response.data);
            }
        }
      }
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      if (jqXHR.status === 0) {
        // Don't send a debug email for errors with status 0
        self.handlePaymentError(MeprStripeGateway.error_please_try_again);
      } else {
        self.handlePaymentError(MeprStripeGateway.ajax_error);
        self.debugCheckoutError({
          status: jqXHR.status,
          status_text: jqXHR.statusText,
          response_text: jqXHR.responseText,
          text_status: textStatus,
          error_thrown: '' + errorThrown
        });
      }
    });
  };

  /**
   * Debug a critical checkout error
   *
   * @param {object} data
   */
  MeprStripeForm.prototype.debugCheckoutError = function (data) {
    data.transaction_id = this.$form.find('input[name="mepr_transaction_id"]').val();
    data.customer_email = this.$form.find('input[name="user_email"]').val();

    $.ajax({
      type: 'POST',
      url: MeprStripeGateway.ajax_url,
      dataType: 'json',
      data: {
        action: 'mepr_debug_checkout_error',
        data: JSON.stringify(data)
      }
    });
  };
})(jQuery);
