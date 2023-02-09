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
    this.initPaymentMethods();
    this.maybeShowPlaceholder();
    this.$form.on('submit', $.proxy(this.handleSubmit, this));
    this.$form.find('input[name="mepr_payment_method"]').on('change', $.proxy(this.maybeCreatePaymentElement, this));
    this.$textFields.add(this.$form.find('input[name="mepr_coupon_code"]')).on('blur', $.proxy(this.maybeCreatePaymentElement, this));
    this.$textFields.on('keyup', $.proxy(this.handleTextFieldKeyUp, this));
    this.$selectFields.on('change', $.proxy(this.maybeCreatePaymentElement, this));
    this.$form.find('input[name="mpgft-signup-gift-checkbox"]').on('change', $.proxy(this.maybeCreatePaymentElement, this));
    this.textFieldKeyupTimeout = null;
  }

  /**
   * Initialize Stripe elements
   */
  MeprStripeForm.prototype.initPaymentMethods = function () {
    var self = this;

    self.$form.find('.mepr-stripe-card-element').each(function () {
      var $cardElement = $(this),
          $cardErrors = $cardElement.closest('.mepr-stripe-elements').find('.mepr-stripe-card-errors'),
          paymentMethodId = $cardElement.data('payment-method-id'),
          wrapperSelector = self.isSpc ? '.mepr-payment-method' : '.mp_payment_form_wrapper',
          $wrapper = $cardElement.closest(wrapperSelector),
          $loader = $wrapper.find('.mepr-stripe-payment-element-loading'),
          $linkElement = $wrapper.find('.mepr-stripe-link-element');

      $cardElement.closest('.mp-form-row').hide();

      self.paymentMethods.push({
        id: paymentMethodId,
        $cardElement: $cardElement,
        $cardErrors: $cardErrors,
        $loader: $loader,
        $wrapper: $wrapper,
        $linkElement: $linkElement,
        stripe: null,
        elements: null,
        linkAuthenticationElement: null,
        paymentElement: null,
        paymentElementComplete: false,
        customerId: null,
        customerIdHash: null,
        paymentFormData: null,
        paymentIntentId: null,
        paymentIntentIdHash: null,
        setupIntentId: null,
        setupIntentIdHash: null,
        subscriptionId: null,
        subscriptionIdHash: null,
        createPaymentElementTimeout: null,
        creatingPaymentElement: false,
        createPaymentElementOnReady: false
      });
    });

    self.maybeCreatePaymentElement();
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
   * Get the form data that, when changed, will cause the payment element to be recreated
   *
   * We only need to recreate the payment element when the price or tax amounts change, so we'll keep track of these
   * values so that we only recreate it when necessary.
   *
   * @param   {object} paymentMethod
   * @returns {string}
   */
  MeprStripeForm.prototype.getPaymentFormData = function (paymentMethod) {
    var $coupon = this.$form.find('input[name="mepr_coupon_code"]'),
        data = [];

    if ($coupon.length) {
      data.push($coupon.val());
    }

    if(MeprStripeGateway.taxes_enabled) {
      var billing_details = this.getBillingDetails(paymentMethod);

      $.each(billing_details.address, function (key, value) {
        data.push(value);
      });

      var $vatNumber = this.$form.find('input[name="mepr_vat_number"]');

      if ($vatNumber.length && this.$form.find('input[name="mepr_vat_customer_type"]:checked').val() === 'business') {
        data.push($vatNumber.val());
      }
    }

    var $giftCheckbox = this.$form.find('input[name="mpgft-signup-gift-checkbox"]');

    if($giftCheckbox.length && $giftCheckbox.is(':checked')) {
      data.push('gift');
    }

    return data.join('');
  };

  /**
   * Creates a new Payment element or recreates an existing one if necessary
   */
  MeprStripeForm.prototype.maybeCreatePaymentElement = function () {
    var self = this,
        paymentMethod = self.getSelectedPaymentMethod();

    if (paymentMethod && self.areRequiredFieldsValid() && (paymentMethod.paymentFormData === null || paymentMethod.paymentFormData !== self.getPaymentFormData(paymentMethod))) {
      self.$form.find('.mepr-submit').prop('disabled', true);

      clearTimeout(self.textFieldKeyupTimeout);
      clearTimeout(paymentMethod.createPaymentElementTimeout);

      paymentMethod.createPaymentElementTimeout = setTimeout(function () {
        self.createPaymentElement(paymentMethod);
        self.maybeShowPlaceholder('hide');
      }, 50);
    }
  };

  /**
   * Creates a new Payment element
   *
   * @param {object} paymentMethod
   */
  MeprStripeForm.prototype.createPaymentElement = function (paymentMethod) {
    if (paymentMethod.creatingPaymentElement) {
      paymentMethod.createPaymentElementOnReady = true;
      return;
    }

    paymentMethod.creatingPaymentElement = true;
    paymentMethod.$cardElement.closest('.mp-form-row').hide();
    paymentMethod.$cardErrors.html('');
    paymentMethod.$wrapper.find('.mepr-stripe-gateway-description').hide();
    paymentMethod.$loader.show();

    if (!paymentMethod.stripe) {
      paymentMethod.stripe = Stripe(paymentMethod.$cardElement.data('stripe-public-key'), {
        locale: paymentMethod.$cardElement.data('locale-code').toLowerCase(),
        apiVersion: MeprStripeGateway.api_version
      });
    }

    if (paymentMethod.paymentElement) {
      paymentMethod.paymentElement.destroy();
      paymentMethod.paymentElement = null;
    }

    if (paymentMethod.linkAuthenticationElement) {
      paymentMethod.linkAuthenticationElement.destroy();
      paymentMethod.linkAuthenticationElement = null;
    }

    paymentMethod.paymentFormData = this.getPaymentFormData(paymentMethod);

    var self = this,
        formData = new FormData(self.$form.get(0));

    formData.append('action', 'mepr_stripe_create_payment_client_secret');
    formData.append('mepr_payment_method', paymentMethod.id);

    if (paymentMethod.customerId) {
      formData.append('customer_id', paymentMethod.customerId);
    }

    if (paymentMethod.customerIdHash) {
      formData.append('customer_id_hash', paymentMethod.customerIdHash);
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
    .done(function (response) {
      if (response && typeof response.success === 'boolean') {
        if (response.success) {
          self.$form.find('.mepr-form-has-errors').hide();
          self.$form.find('.mepr-submit').prop('disabled', false);
          paymentMethod.$cardElement.closest('.mp-form-row').show();
          paymentMethod.$loader.hide();

          if (response.data.is_free_purchase) {
            paymentMethod.creatingPaymentElement = false;
            paymentMethod.$wrapper.find('.mepr-stripe-gateway-description').show();
            return;
          }

          var options = {
            clientSecret: response.data.client_secret
          };

          if ($.isPlainObject(MeprStripeGateway.elements_appearance)) {
            options.appearance = MeprStripeGateway.elements_appearance;
          }

          paymentMethod.elements = paymentMethod.stripe.elements(options);

          if (paymentMethod.$linkElement.length) {
            var $emailField = self.$form.find('input[name="user_email"]');

            paymentMethod.linkAuthenticationElement = paymentMethod.elements.create('linkAuthentication', {
              defaultValues: {
                email: $emailField.length ? $emailField.val() : paymentMethod.$linkElement.data('stripe-email')
              }
            });

            paymentMethod.linkAuthenticationElement.mount(paymentMethod.$linkElement[0]);
          }

          paymentMethod.paymentElement = paymentMethod.elements.create('payment', {
            defaultValues: {
              billingDetails: self.getBillingDetails(paymentMethod)
            },
            terms: {
              card: 'never'
            }
          });

          paymentMethod.paymentElementComplete = false;

          paymentMethod.paymentElement.on('ready', function () {
            paymentMethod.creatingPaymentElement = false;

            if (paymentMethod.createPaymentElementOnReady) {
              paymentMethod.createPaymentElementOnReady = false;
              self.createPaymentElement(paymentMethod);
            }
          });

          paymentMethod.paymentElement.on('change', function (event) {
            if (typeof event.complete === 'boolean') {
              paymentMethod.paymentElementComplete = event.complete;
            }
          });

          paymentMethod.paymentElement.on('loaderror', function (event) {
            paymentMethod.creatingPaymentElement = false;

            if(event.error) {
              self.createPaymentElementError(paymentMethod, event.error.message);
            }
          });

          paymentMethod.paymentElement.mount(paymentMethod.$cardElement[0]);

          if(response.data.customer_id) {
            paymentMethod.customerId = response.data.customer_id;
          }

          if(response.data.customer_id_hash) {
            paymentMethod.customerIdHash = response.data.customer_id_hash;
          }

          if(response.data.payment_intent_id) {
            paymentMethod.paymentIntentId = response.data.payment_intent_id;
          }

          if(response.data.payment_intent_id_hash) {
            paymentMethod.paymentIntentIdHash = response.data.payment_intent_id_hash;
          }

          if(response.data.setup_intent_id) {
            paymentMethod.setupIntentId = response.data.setup_intent_id;
          }

          if(response.data.setup_intent_id_hash) {
            paymentMethod.setupIntentIdHash = response.data.setup_intent_id_hash;
          }

          if(response.data.subscription_id) {
            paymentMethod.subscriptionId = response.data.subscription_id;
          }

          if(response.data.subscription_id_hash) {
            paymentMethod.subscriptionIdHash = response.data.subscription_id_hash;
          }
        } else {
          self.createPaymentElementError(paymentMethod, response.data);
        }
      } else {
        self.createPaymentElementError(paymentMethod, 'Invalid response');
      }
    })
    .fail(function () {
      self.createPaymentElementError(paymentMethod, 'Request failed');
    });
  };

  /**
   * Handle an error creating the payment element
   *
   * @param {object} paymentMethod
   * @param {string} message
   */
  MeprStripeForm.prototype.createPaymentElementError = function (paymentMethod, message) {
    this.$form.find('.mepr-submit').prop('disabled', false);
    paymentMethod.$cardElement.closest('.mp-form-row').show();
    paymentMethod.$loader.hide();
    paymentMethod.creatingPaymentElement = false;
    paymentMethod.paymentFormData = null;
    paymentMethod.$cardErrors.html(message || 'Failed to create Payment element');
  };

  /**
   * Disable the submit button temporarily while the payment element loads and
   * create the payment element 3 seconds after valid data has been entered.
   */
  MeprStripeForm.prototype.handleTextFieldKeyUp = function () {
    var paymentMethod = this.getSelectedPaymentMethod();

    if (paymentMethod && this.areRequiredFieldsValid() && (paymentMethod.paymentFormData === null || paymentMethod.paymentFormData !== this.getPaymentFormData(paymentMethod))) {
      this.$form.find('.mepr-submit').prop('disabled', true);
    } else {
      this.$form.find('.mepr-submit').prop('disabled', false);
    }

    clearTimeout(this.textFieldKeyupTimeout);

    this.textFieldKeyupTimeout = setTimeout($.proxy(this.maybeCreatePaymentElement, this), 3000);
  };

  /**
   * Handle the payment form submission
   *
   * @param {jQuery.Event} e
   */
  MeprStripeForm.prototype.handleSubmit = function (e) {
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

      if(self.selectedPaymentMethod.paymentFormData === null) {
        self.allowResubmission();
        self.maybeCreatePaymentElement();
        return;
      }

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

      self.confirmPayment(extraData);
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
      self.form.submit();
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
   * @param  {object} paymentMethod
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
        name = [],
        address = {},
        addressFieldsPresent = false,
        details = {
          address: {}
        };

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
   * Handle the response from our Ajax endpoint
   *
   * @param {object} response
   * @param {string} textStatus
   * @param {object} jqXHR
   */
  MeprStripeForm.prototype.handleServerResponse = function (response, textStatus, jqXHR) {
    if (response === null || typeof response != 'object') {
      this.handlePaymentError(MeprStripeGateway.invalid_response_error);
      this.debugCheckoutError({
        status: jqXHR.status,
        status_text: jqXHR.statusText,
        response_text: jqXHR.responseText,
        text_status: textStatus,
        error_thrown: 'Response was null or not an object'
      });
    } else {
      if (response.transaction_id) {
        this.$form.find('input[name="mepr_transaction_id"]').val(response.transaction_id);
      }

      if (response.errors) {
        this.handleValidationErrors(response.errors);
      } else if (response.error) {
        this.handlePaymentError(response.error);
      } else if (response.action && (response.action === 'confirmPayment' || response.action === 'confirmSetup')) {
        this.handleAction(response.action, response.return_url);
      } else if (!this.$form.hasClass('mepr-payment-submitted')) {
        this.$form.addClass('mepr-payment-submitted');
        this.form.submit();
      }
    }
  };

  /**
   * Handle any SCA actions, and confirms the payment or card setup
   *
   * @param {string} action
   * @param {string} returnUrl
   */
  MeprStripeForm.prototype.handleAction = async function (action, returnUrl) {
    var self = this,
        stripe = self.selectedPaymentMethod.stripe,
        elements = self.selectedPaymentMethod.elements,
        confirmParams = {
          return_url: returnUrl,
          payment_method_data: {
            billing_details: self.getBillingDetails(self.selectedPaymentMethod)
          }
        };

    const { error } = await stripe[action]({
      elements: elements,
      confirmParams: confirmParams
    });

    if(error) {
      self.handlePaymentError(error.message);
    }
  };

  /**
   * Create stripe checkout page session then redirect user o checkout.stripe.com
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
   * @param {object} [extraData] Additional data to send with the request
   */
  MeprStripeForm.prototype.confirmPayment = function (extraData) {
    var self = this,
        formData = new FormData(self.$form.get(0));

    formData.append('action', 'mepr_stripe_confirm_payment');
    formData.append('mepr_current_url', document.location.href);

    if (extraData) {
      $.each(extraData, function (key, value) {
        formData.append(key, value);
      });
    }

    if (self.selectedPaymentMethod.paymentIntentId) {
      formData.append('payment_intent_id', self.selectedPaymentMethod.paymentIntentId);
    }

    if (self.selectedPaymentMethod.paymentIntentIdHash) {
      formData.append('payment_intent_id_hash', self.selectedPaymentMethod.paymentIntentIdHash);
    }

    if (self.selectedPaymentMethod.setupIntentId) {
      formData.append('setup_intent_id', self.selectedPaymentMethod.setupIntentId);
    }

    if (self.selectedPaymentMethod.setupIntentIdHash) {
      formData.append('setup_intent_id_hash', self.selectedPaymentMethod.setupIntentIdHash);
    }

    if (self.selectedPaymentMethod.subscriptionId) {
      formData.append('subscription_id', self.selectedPaymentMethod.subscriptionId);
    }

    if (self.selectedPaymentMethod.subscriptionIdHash) {
      formData.append('subscription_id_hash', self.selectedPaymentMethod.subscriptionIdHash);
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
    .done($.proxy(self.handleServerResponse, self))
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

  /**
   * Adds a placeholder for Stripe Elements on SPC forms.
   *
   * @param {string} status
   */
  MeprStripeForm.prototype.maybeShowPlaceholder = function(status = 'show') {
    if (!this.isSpc) {
      return;
    }

    var $placeholderDiv = $('.mepr-stripe-form-placeholder');

    if(status == 'hide') {
      $placeholderDiv.hide();
      $placeholderDiv.html('');
    } else {
      if(MeprStripeGateway.address_fields_required) {
        var $placeholder = $('<p></p>').text(MeprStripeGateway.placeholder_text_email_address);
      } else {
        var $placeholder = $('<p></p>').text(MeprStripeGateway.placeholder_text_email);
      }

      $placeholderDiv.show();
      $placeholderDiv.html($placeholder);
    }
  }
})(jQuery);
