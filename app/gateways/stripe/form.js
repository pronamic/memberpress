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
    this.isStripeCheckoutPageMode = this.$form.find('input[name=mepr_stripe_checkout_page_mode]').val();
    this.$textFields = this.$form.find(
      'input[name="user_email"], input[name="mepr-address-one"], input[name="mepr-address-city"], ' +
      'input[name="mepr-address-zip"], input[name="mepr-address-state"], input[name="mepr_vat_number"]'
    );
    this.$selectFields = this.$form.find('select[data-fieldname="mepr-address-state"], select[name="mepr-address-country"]');
    this.$orderBumps = this.$form.find('input[name="mepr_order_bumps[]"]');
    this.initPaymentMethods();
    this.$form.on('submit', $.proxy(this.handleSubmit, this));
    this.$form.find('input[name="mepr_payment_method"], input[name="mepr_vat_customer_type"]').on('change', $.proxy(this.maybeUpdateElements, this));
    this.$textFields.add(this.$form.find('input[name="mepr_coupon_code"]')).on('blur', $.proxy(this.maybeUpdateElements, this));
    this.$selectFields.on('change', $.proxy(this.maybeUpdateElements, this));
    this.$orderBumps.on('change', $.proxy(this.maybeUpdateElements, this));
  }

  /**
   * Initialize Stripe elements
   */
  MeprStripeForm.prototype.initPaymentMethods = function () {
    var self = this;

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
            paymentElementComplete: false,
            paymentMethodType: null,
            paymentFormData: null,
            updateElementsTimeout: null,
            updatingElements: false,
            updateElementsAgain: false,
            billingDetailsJson: '',
            updateBillingDetailsTimeout: null
          };

      self.paymentMethods.push(paymentMethod);

      if (elementsOptions) {
        self.createElements(paymentMethod, elementsOptions);
      } else {
        self.updateElements(paymentMethod);
        console.log('Your MemberPress checkout form template is out of date, please update it to the latest version.');
      }
    });
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
   * Checks if the fields required to create the Payment element are valid
   *
   * @returns {boolean}
   */
  MeprStripeForm.prototype.areRequiredFieldsValid = function () {
    if (!this.isSpc) {
      return true;
    }

    var self = this,
        $fields = self.$textFields.add(self.$selectFields),
        hasInvalidField = false;

    $fields.each(function (index, field) {
      var $field = $(field);

      if(
        $field.is(':visible') && (
          ($field.attr('required') !== undefined && !mpValidateFieldNotBlank($field)) ||
          ($field.attr('type') === 'email' && $field.val().length > 0 && !mpValidateEmail($field.val()))
        )
      ) {
        hasInvalidField = true;
        return false;
      }
    });

    return !hasInvalidField;
  };

  /**
   * Get the form data that, when changed, will cause the payment element to be updated
   *
   * We only need to update the payment element when the price or tax amounts change, so we'll keep track of these
   * values so that we only update it when necessary.
   *
   * @param   {object} paymentMethod
   * @returns {string}
   */
  MeprStripeForm.prototype.getPaymentFormData = function (paymentMethod) {
    var $coupon = this.$form.find('input[name="mepr_coupon_code"]'),
        billingDetails = this.getBillingDetails(paymentMethod),
        $vatNumber = this.$form.find('input[name="mepr_vat_number"]'),
        $giftCheckbox = this.$form.find('input[name="mpgft-signup-gift-checkbox"]'),
        data = [];

    if ($coupon.length) {
      data.push($coupon.val());
    }

    $.each(billingDetails.address, function (key, value) {
      data.push(value);
    });

    if ($vatNumber.length && this.$form.find('input[name="mepr_vat_customer_type"]:checked').val() === 'business') {
      data.push($vatNumber.val());
    }

    if ($giftCheckbox.length && $giftCheckbox.is(':checked')) {
      data.push('gift');
    }

    this.$orderBumps.each(function (i, orderBump) {
      var $orderBump = $(orderBump);

      if ($orderBump.is(':checked')) {
        data.push($orderBump.val());
      }
    });

    return data.join('');
  };

  /**
   * Updates the Elements instance for the currently selected payment method if the form data has changed
   */
  MeprStripeForm.prototype.maybeUpdateElements = function () {
    var self = this,
        paymentMethod = self.getSelectedPaymentMethod();

    if (paymentMethod) {
      if (self.areRequiredFieldsValid() && (paymentMethod.paymentFormData === null || paymentMethod.paymentFormData !== self.getPaymentFormData(paymentMethod))) {
        clearTimeout(paymentMethod.updateElementsTimeout);

        paymentMethod.updateElementsTimeout = setTimeout(function () {
          self.updateElements(paymentMethod);
        }, 50);
      }

      if(paymentMethod.paymentElement && paymentMethod.billingDetailsJson !== self.getBillingDetailsJson(paymentMethod)) {
        clearTimeout(paymentMethod.updateBillingDetailsTimeout);

        paymentMethod.updateBillingDetailsTimeout = setTimeout(function () {
          var billingDetails = self.getBillingDetails(paymentMethod);

          paymentMethod.billingDetailsJson = JSON.stringify(billingDetails);

          paymentMethod.paymentElement.update({
            defaultValues: {
              billingDetails: billingDetails
            }
          });
        }, 50);
      }
    }
  };

  /**
   * Updates the Elements instance with new options
   *
   * @param {object} paymentMethod
   */
  MeprStripeForm.prototype.updateElements = function (paymentMethod) {
    if (paymentMethod.updatingElements) {
      // A call to update elements happened when we were already in the process of updating it.
      // Set this flag to update elements again when finished with the current update.
      paymentMethod.updateElementsAgain = true;
      return;
    }

    paymentMethod.updatingElements = true;
    paymentMethod.paymentFormData = this.getPaymentFormData(paymentMethod);

    var self = this,
        formData = new FormData(self.$form.get(0));

    formData.append('action', 'mepr_stripe_get_elements_options');
    formData.append('mepr_payment_method', paymentMethod.id);

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
    .done(function (response) {
      if (response && typeof response.success === 'boolean') {
        if (response.success) {
          if (response.data.payment_required === false) {
            paymentMethod.updatingElements = false;
            return;
          }

          if (paymentMethod.elements) {
            paymentMethod.elements.update(response.data);
          } else {
            self.createElements(paymentMethod, response.data);
          }

          paymentMethod.updatingElements = false;

          if (paymentMethod.updateElementsAgain) {
            paymentMethod.updateElementsAgain = false;
            self.updateElements(paymentMethod);
          }
        } else {
          self.updateElementsError(paymentMethod, response.data);
        }
      } else {
        self.updateElementsError(paymentMethod, 'Invalid response');
      }
    })
    .fail(function () {
      self.updateElementsError(paymentMethod, 'Request failed');
    });
  };

  /**
   * Handle an error updating the elements options
   *
   * @param {object} paymentMethod
   * @param {string} message
   */
  MeprStripeForm.prototype.updateElementsError = function (paymentMethod, message) {
    paymentMethod.updatingElements = false;
    paymentMethod.paymentFormData = null;
    paymentMethod.$cardErrors.html(message || 'Failed to update Elements options');
  };

  /**
   * Handle the payment form submission
   *
   * @param {jQuery.Event} e
   */
  MeprStripeForm.prototype.handleSubmit = function (e) {
    if (e.result === false) {
      return;
    }

    var self = this;

    e.preventDefault();

    if (self.submitting) {
      return;
    }

    self.submitting = true;

    if (self.$form.find('.mepr-payment-methods-wrapper').is(':hidden')) {
      self.form.submit();
      return;
    }

    self.$form.find('.mepr-form-has-errors').hide();
    self.$form.find('.mepr-submit').prop('disabled', true);
    self.$form.find('.mepr-loading-gif').show();

    self.selectedPaymentMethod = self.getSelectedPaymentMethod();

    if (self.selectedPaymentMethod) {
      self.selectedPaymentMethod.$cardErrors.html('');

      if (!self.selectedPaymentMethod.paymentElementComplete) {
        self.selectedPaymentMethod.$cardErrors.text(MeprStripeGateway.payment_information_incomplete);
        self.allowResubmission();
        return;
      }

      var $recaptcha = self.$form.find('[name="g-recaptcha-response"]'),
          extraData = {};

      if ($recaptcha.length) {
        extraData['g-recaptcha-response'] = $recaptcha.val();
      }

      self.confirmPayment(self.selectedPaymentMethod, extraData);
    } else {
      if (!self.isSpc && self.isStripeCheckoutPageMode == '1') {
        self.redirectToStripeCheckout();
        return;
      }

      const paymentMethodId = self.$form.find('input[name="mepr_payment_method"]:checked').data('payment-method-type');
      if (
          self.isStripeCheckoutPageMode == '1' && (
              paymentMethodId == 'Stripe' &&
              self.$form.find('[name=mepr_stripe_is_checkout]').val() == '1'
          )
      ) {
        self.redirectToStripeCheckout();

        return;
      }

      if (!self.$form.find('[data-merp-gateway-async]').is(':visible')) {
        self.form.submit();
      }
    }
  };

  /**
   * Get the currently selected payment method data
   *
   * @return {object|null}
   */
  MeprStripeForm.prototype.getSelectedPaymentMethod = function () {
    if (this.isSpc) {
      var paymentMethodId = this.$form.find('input[name="mepr_payment_method"]:checked').val();

      for (var i = 0; i < this.paymentMethods.length; i++) {
        if (this.paymentMethods[i].id === paymentMethodId) {
          return this.paymentMethods[i];
        }
      }

      return null;
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
   * Get the billing details as a JSON string
   *
   * @param  {object} paymentMethod The selected payment method.
   * @return {string}
   */
  MeprStripeForm.prototype.getBillingDetailsJson = function (paymentMethod) {
    return JSON.stringify(this.getBillingDetails(paymentMethod));
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

    if(error) {
      self.handlePaymentError(error.message);
    }
  };

  /**
   * Create stripe checkout page session then redirect user to checkout.stripe.com
   */
  MeprStripeForm.prototype.redirectToStripeCheckout = function() {
    var self = this,
        formData = new FormData(self.$form.get(0));

    formData.append('action', 'mepr_stripe_create_checkout_session');
    formData.append('mepr_current_url', document.location.href);

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
    .done(function(result) {
      if (typeof result.errors !== 'undefined') {
        self.$form.find('.mepr-stripe-checkout-errors').html('');
        for (var i = 0; i < Object.entries(result.errors).length; i++) {
          self.$form.find('.mepr-stripe-checkout-errors').
              append('<p>' + Object.entries(result.errors)[i][1] + '</p>');
        }
        self.allowResubmission();
      }

      if (typeof result.error !== 'undefined') {
        self.$form.find('.mepr-stripe-checkout-errors').html(result.error);
        self.allowResubmission();
      }

      if (typeof result.errors !== 'undefined' || typeof result.error !== 'undefined') {
        return;
      }

      var stripe = Stripe(result.public_key);
      return stripe.redirectToCheckout({ sessionId: result.id });
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
   * Confirm the payment with our Ajax endpoint
   *
   * @param {object} paymentMethod
   * @param {object} [extraData] Additional data to send with the request
   */
  MeprStripeForm.prototype.confirmPayment = async function (paymentMethod, extraData) {
    const { error } = await paymentMethod.elements.submit();

    if (error) {
      this.handlePaymentError(error.message);
      return;
    }

    var self = this,
        formData = new FormData(self.$form.get(0));

    formData.append('action', 'mepr_stripe_confirm_payment');
    formData.append('mepr_current_url', document.location.href);

    if (extraData) {
      $.each(extraData, function (key, value) {
        formData.append(key, value);
      });
    }

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
      if (response === null || typeof response != 'object') {
        self.handlePaymentError(MeprStripeGateway.invalid_response_error);
        self.debugCheckoutError({
          status: jqXHR.status,
          status_text: jqXHR.statusText,
          response_text: jqXHR.responseText,
          text_status: textStatus,
          error_thrown: 'Response was null or not an object'
        });
      } else {
        if (response.transaction_id) {
          self.$form.find('input[name="mepr_transaction_id"]').val(response.transaction_id);
        }

        if (response.errors) {
          self.handleValidationErrors(response.errors);
        } else if (response.error) {
          self.handlePaymentError(response.error);
        } else if (response.action && (response.action === 'confirmPayment' || response.action === 'confirmSetup')) {
          self.handleAction(response.action, response.client_secret, response.return_url);
        } else if (!self.$form.hasClass('mepr-payment-submitted')) {
          self.$form.addClass('mepr-payment-submitted');
          self.form.submit();
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
        action: 'mepr_stripe_debug_checkout_error',
        data: JSON.stringify(data)
      }
    });
  };
})(jQuery);
