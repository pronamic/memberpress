class MeprPayPalVaultingForm {
    /**
     * Cache for PayPal SDK instances by namespace.
     * Prevents loading the same SDK configuration multiple times.
     * The namespace is generated from the config hash in PHP, so identical
     * configs share the same namespace and can reuse the SDK instance.
     *
     * @type {Object<string, Promise>}
     */
    static paypalSDKCache = {};

    /**
     * Promise for loading the Apple Pay SDK.
     * Load once and cache for all payment methods.
     *
     * @type {Promise|null}
     */
    static applePaySDKPromise = null;

    /**
     * Promise for loading the Google Pay SDK.
     * Load once and cache for all payment methods.
     *
     * @type {Promise|null}
     */
    static googlePaySDKPromise = null;

    /**
     * Check if the current browser supports Apple Pay.
     *
     * @return {boolean} True if browser supports Apple Pay.
     */
    static isApplePaySupported() {
        if (!window.ApplePaySession) {
            return false;
        }

        return ApplePaySession.canMakePayments();
    }

    /**
     * Check if the current browser supports Google Pay.
     *
     * @return {boolean} True if browser supports Google Pay.
     */
    static isGooglePaySupported() {
        return typeof google !== 'undefined' && google.payments && typeof google.payments.api !== 'undefined';
    }

    /**
     * Load the Apple Pay SDK if not already loaded.
     * Uses paypalLoadCustomScript from paypal-js to load the SDK.
     * Note: Browser compatibility should be checked before calling this method.
     *
     * @return {Promise} Promise that resolves when SDK is loaded.
     */
    static async loadApplePaySDK() {
        if (!MeprPayPalVaultingForm.applePaySDKPromise) {
            MeprPayPalVaultingForm.applePaySDKPromise = window.paypalLoadCustomScript({
                url: 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js',
            });
        }

        return MeprPayPalVaultingForm.applePaySDKPromise;
    }

    /**
     * Load the Google Pay SDK if not already loaded.
     * Uses paypalLoadCustomScript from paypal-js to load the SDK.
     *
     * @return {Promise} Promise that resolves when SDK is loaded.
     */
    static async loadGooglePaySDK() {
        if (!MeprPayPalVaultingForm.googlePaySDKPromise) {
            MeprPayPalVaultingForm.googlePaySDKPromise = window.paypalLoadCustomScript({
                url: 'https://pay.google.com/gp/p/js/pay.js',
            });
        }

        return MeprPayPalVaultingForm.googlePaySDKPromise;
    }

    /**
     * Creates an instance of the class managing PayPal Vaulting payment methods for the specified form.
     *
     * @param {HTMLFormElement} form The form element to be managed.
     * @return {void}
     */
    constructor(form) {
        this.form = form;
        this.type = form.classList.contains('mepr-signup-form') ? 'signup' : form.dataset.type;
        this.paymentMethods = [];
        this.submitting = false;
        this.initPaymentMethods();
    }

    /**
     * Initializes payment methods for the form by setting up PayPal payment elements.
     * The method retrieves eligible payment elements, loads the PayPal SDK with the appropriate
     * configuration for each element, and stores relevant data in the paymentMethods array.
     *
     * @return {void} This method does not return any value.
     */
    initPaymentMethods() {
        this.form.querySelectorAll('.mepr-paypal-vaulting-elements').forEach(async element => {
            try {
                const paymentMethodId = element.dataset.paymentMethodId;
                const baseConfig = JSON.parse(element.dataset.paypalConfig);
                const sdkOptions = JSON.parse(element.dataset.sdkOptions);
                const { intent, vault, amount, recurringPaymentRequest } = sdkOptions;

                // Build the complete SDK config with dynamic options.
                const completeConfig = this.buildCompleteConfig(baseConfig, intent, vault);
                const cacheKey = completeConfig.dataNamespace;

                // Load PayPal SDK with caching to avoid duplicate loads of identical configurations.
                if (!MeprPayPalVaultingForm.paypalSDKCache[cacheKey]) {
                    MeprPayPalVaultingForm.paypalSDKCache[cacheKey] = window.paypalLoadScript(completeConfig);
                }

                const paypal = await MeprPayPalVaultingForm.paypalSDKCache[cacheKey];

                // Load Apple Pay SDK once if enabled in any payment method config.
                const hasApplePay = baseConfig.components && baseConfig.components.includes('applepay');

                if (hasApplePay && MeprPayPalVaultingForm.isApplePaySupported() && !MeprPayPalVaultingForm.applePaySDKPromise) {
                    try {
                        await MeprPayPalVaultingForm.loadApplePaySDK();
                    } catch (e) {
                        console.error('[Apple Pay] Failed to load SDK:', e.message);
                    }
                }

                // Load Google Pay SDK once if enabled in any payment method config.
                const hasGooglePay = baseConfig.components && baseConfig.components.includes('googlepay');

                if (hasGooglePay && !MeprPayPalVaultingForm.googlePaySDKPromise) {
                    try {
                        await MeprPayPalVaultingForm.loadGooglePaySDK();
                    } catch (e) {
                        console.error('[Google Pay] Failed to load SDK:', e.message);
                    }
                }

                const paymentMethod = {
                    id: paymentMethodId,
                    element,
                    paypal,
                    baseConfig, // Store base config for rebuilding.
                    intent,
                    vault,
                    amount,
                    recurringPaymentRequest,
                };

                // Render buttons and card fields.
                await this.renderPaymentMethod(paymentMethod);

                // Only add to array after successful rendering.
                this.paymentMethods.push(paymentMethod);

                // Set initial Submit button visibility.
                if (this.type === 'signup') {
                    // For SPC, check if this is the selected payment method.
                    const selectedId = this.form.querySelector('input[name="mepr_payment_method"]:checked')?.value;
                    if (selectedId === paymentMethodId) {
                        this.handlePaymentMethodChanged(selectedId);
                    }
                } else {
                    // For standalone forms, hide the Submit button if there are no card fields.
                    if (!paymentMethod.cardFields) {
                        this.form.classList.add('mepr-paypal-vaulting-hide-submit');
                    }
                }
            } catch (e) {
                console.error('Failed to initialize PayPal payment method.', e);
            }
        });
    }

    /**
     * Build the complete SDK config by merging base config with SDK options.
     *
     * @param {Object} baseConfig Base PayPal SDK configuration (static params).
     * @param {string|undefined} intent Payment intent ('capture' or undefined).
     * @param {boolean|undefined} vault Whether vaulting is required.
     * @return {Object} Complete SDK configuration for loadScript.
     */
    buildCompleteConfig(baseConfig, intent, vault) {
        const config = { ...baseConfig };

        // Add intent if defined.
        if (intent) {
            config.intent = intent;
        }

        // Add the vault property if defined.
        if (vault === true) {
            config.vault = true;
        }

        // Update namespace to include dynamic config for proper caching.
        const vaultSuffix = vault ? 'v' : 'nv';
        const intentSuffix = intent ? 'i' : 'ni';
        config.dataNamespace = baseConfig.dataNamespace + '_' + vaultSuffix + '_' + intentSuffix;

        return config;
    }

    /**
     * Render buttons and card fields for a payment method.
     *
     * @param {Object} paymentMethod The payment method object.
     * @return {Promise<void>}
     */
    async renderPaymentMethod(paymentMethod) {
        const { element, paypal } = paymentMethod;

        // Find the container to render buttons.
        const buttonsElement = element.querySelector('.mepr-paypal-vaulting-buttons');

        if (buttonsElement && paypal.Buttons) {
            const buttonsConfig = {
                style: {
                    height: MeprPayPalVaultingGateway.button_height,
                },
                appSwitchWhenAvailable: true,  // Enable App Switch for eligible transactions.
                onClick: data => {
                    if (this.submitting) {
                        return;
                    }
                    paymentMethod.fundingSource = data.fundingSource;
                    this.submitting = true;
                    this.showLoadingState();
                },
                onError: err => this.onError(err),
                onCancel: data => this.onCancel(data)
            };

            // Choose the callback based on intent.
            if (paymentMethod.intent === 'capture') {
                buttonsConfig.createOrder = () => this.processPaymentForm();
                buttonsConfig.onApprove = data => this.completePayPalTransaction(data.orderID, 'payment', paymentMethod);
            } else {
                buttonsConfig.createVaultSetupToken = () => this.processPaymentForm();
                buttonsConfig.onApprove = data => this.completePayPalTransaction(data.vaultSetupToken, 'setup', paymentMethod);
            }

            const buttons = paypal.Buttons(buttonsConfig);

            if (buttons.isEligible()) {
                // Check if we're returning from App Switch.
                // PayPal's hasReturned() automatically detects this.
                if (paymentMethod.intent === 'capture' && buttons.hasReturned && buttons.hasReturned()) {
                    // App Switch always uses PayPal funding source.
                    paymentMethod.fundingSource = 'paypal';

                    // For signup forms, ensure this payment method is selected before resuming.
                    if (this.type === 'signup') {
                        const radioButton = this.form.querySelector(`input[name="mepr_payment_method"][value="${paymentMethod.id}"]`);
                        if (radioButton && !radioButton.checked) {
                            radioButton.checked = true;
                            // Trigger click event for jQuery handlers in signup.js.
                            radioButton.dispatchEvent(new MouseEvent('click', { bubbles: true }));
                        }
                    }

                    try {
                        await buttons.resume();
                    } catch (error) {
                        console.error('Failed to resume PayPal App Switch flow:', error);
                        this.setPayPalError(
                            paymentMethod.element,
                            'Unable to complete payment. The payment session may have expired. Please try again.'
                        );
                        this.allowResubmission();
                    }
                } else {
                    // Normal render flow.
                    await buttons.render(buttonsElement);
                }

                paymentMethod.buttons = buttons;  // Store for cleanup.

                // Render Apple Pay button (into same container) if enabled and browser supports it.
                // Note: Apple Pay only supports payments (intent=capture), not free trials (setup tokens only).
                const hasApplePay = paymentMethod.baseConfig.components && paymentMethod.baseConfig.components.includes('applepay');
                const supportsApplePay = hasApplePay && MeprPayPalVaultingForm.isApplePaySupported() && paymentMethod.intent === 'capture';

                if (supportsApplePay) {
                    await this.renderApplePayButton(paymentMethod, buttonsElement);
                }

                // Render Google Pay button (into same container) if enabled and supported.
                // Note: Google Pay only supports one-time payments (intent=capture).
                // Don't render the button when vault=true (subscriptions/recurring payments).
                const hasGooglePay = paymentMethod.baseConfig.components && paymentMethod.baseConfig.components.includes('googlepay');
                const supportsGooglePay = hasGooglePay &&
                                          paymentMethod.intent === 'capture' &&
                                          !paymentMethod.vault;

                if (supportsGooglePay) {
                    await this.renderGooglePayButton(paymentMethod, buttonsElement);
                }
            }
        }

        // Render card fields if enabled.
        const cardFieldsContainer = element.querySelector('.mepr-paypal-vaulting-card-fields');

        if (cardFieldsContainer && paypal.CardFields) {
            const cardFieldsConfig = {
                onError: err => this.onError(err),
                onCancel: () => this.onCancel(),
                style: MeprPayPalVaultingGateway.card_field_style,
            };

            // Choose the callback based on intent.
            if (paymentMethod.intent === 'capture') {
                cardFieldsConfig.createOrder = () => this.processPaymentForm();
                cardFieldsConfig.onApprove = data => this.completePayPalTransaction(data.orderID, 'payment', paymentMethod);
            } else {
                cardFieldsConfig.createVaultSetupToken = () => this.processPaymentForm();
                cardFieldsConfig.onApprove = data => this.completePayPalTransaction(data.vaultSetupToken, 'setup', paymentMethod);
            }

            const cardFields = paypal.CardFields(cardFieldsConfig);

            if (cardFields.isEligible()) {
                const numberField = cardFields.NumberField();
                const expiryField = cardFields.ExpiryField();
                const cvvField = cardFields.CVVField();

                const numberContainer = element.querySelector('.mepr-card-field-number');
                const expiryContainer = element.querySelector('.mepr-card-field-expiry');
                const cvvContainer = element.querySelector('.mepr-card-field-cvv');

                if (numberContainer) {
                    await numberField.render(numberContainer);
                }
                if (expiryContainer) {
                    await expiryField.render(expiryContainer);
                }
                if (cvvContainer) {
                    await cvvField.render(cvvContainer);
                }

                paymentMethod.cardFields = cardFields;
                paymentMethod.numberField = numberField;
                paymentMethod.expiryField = expiryField;
                paymentMethod.cvvField = cvvField;
            }
        }
    }

    /**
     * Render Apple Pay button into the container element for buttons.
     *
     * @param {Object} paymentMethod The payment method object.
     * @param {HTMLElement} buttonsElement The container element for buttons.
     * @return {Promise<void>}
     */
    async renderApplePayButton(paymentMethod, buttonsElement) {
        const { paypal, element } = paymentMethod;

        // Check if Apple Pay SDK is loaded at class level.
        if (!MeprPayPalVaultingForm.applePaySDKPromise) {
            return;
        }

        // Wait for SDK to be ready.
        try {
            await MeprPayPalVaultingForm.applePaySDKPromise;
        } catch (e) {
            return;
        }

        // Get the Apple Pay instance and check eligibility.
        const applePay = paypal.Applepay();
        const applePayConfig = await applePay.config();

        if (!applePayConfig.isEligible) {
            return;
        }

        // Create a native Apple Pay button element.
        const applePayButton = document.createElement('apple-pay-button');
        applePayButton.setAttribute('buttonstyle', 'black');
        applePayButton.setAttribute('type', 'plain');
        applePayButton.setAttribute('locale', 'en');

        // Set a consistent button height.
        applePayButton.style.setProperty('--apple-pay-button-height', MeprPayPalVaultingGateway.button_height + 'px');

        // Add the click handler for the Apple Pay session.
        applePayButton.addEventListener('click', async () => {
            if (this.submitting) {
                return;
            }

            // Validate the form before showing the Apple Pay sheet.
            if (!this.validateForm(element)) {
                return;
            }

            try {
                await this.handleApplePaySession(paymentMethod, applePay, applePayConfig);
            } catch (error) {
                console.error('[Apple Pay] Payment error:', error);
                this.setPayPalError(element, error.message || 'An error occurred with Apple Pay.');
                this.allowResubmission();
            }
        });

        // Append button to container.
        buttonsElement.appendChild(applePayButton);

        paymentMethod.applePayButton = applePayButton;
    }

    /**
     * Handle Apple Pay payment session.
     *
     * @param {Object} paymentMethod The payment method object.
     * @param {Object} applePay The PayPal Apple Pay instance.
     * @param {Object} applePayConfig The Apple Pay configuration.
     * @return {Promise<void>}
     */
    async handleApplePaySession(paymentMethod, applePay, applePayConfig) {
        paymentMethod.fundingSource = 'apple_pay';
        this.submitting = true;
        this.showLoadingState();

        // Create payment request for Apple Pay session.
        const paymentRequest = {
            countryCode: applePayConfig.countryCode,
            merchantCapabilities: applePayConfig.merchantCapabilities,
            supportedNetworks: applePayConfig.supportedNetworks,
            currencyCode: paymentMethod.baseConfig.currency,
            requiredBillingContactFields: ['postalAddress'],
            total: {
                label: MeprPayPalVaultingGateway.apple_pay_total_label,
                amount: paymentMethod.amount,
                type: 'final',
            },
        };

        // Determine the ApplePaySession version based on browser capability.
        // Version 14 enables recurringPaymentRequest for MPAN tokens.
        // Fall back to version 4 (DPAN) silently on older browsers.
        const supportsV14 = typeof ApplePaySession.supportsVersion === 'function'
            && ApplePaySession.supportsVersion(14);
        const sessionVersion = supportsV14 ? 14 : 4;

        // Inject recurringPaymentRequest when v14 is supported and config exists.
        if (supportsV14 && paymentMethod.recurringPaymentRequest) {
            // Clone to avoid mutating the stored config (user may cancel and retry).
            const request = {
                ...paymentMethod.recurringPaymentRequest,
                regularBilling: { ...paymentMethod.recurringPaymentRequest.regularBilling },
            };

            // Convert trialDays (integer from PHP helper) to a Date on regularBilling.
            if (request.trialDays && request.regularBilling) {
                const startDate = new Date();

                startDate.setDate(startDate.getDate() + request.trialDays);
                startDate.setHours(0, 0, 0, 0);

                request.regularBilling.recurringPaymentStartDate = startDate;
                delete request.trialDays;
            }

            paymentRequest.recurringPaymentRequest = request;
        }

        // Create an Apple Pay session (must be in user gesture handler).
        const session = new ApplePaySession(sessionVersion, paymentRequest);

        // Handle merchant validation.
        session.onvalidatemerchant = async (event) => {
            try {
                const validateResult = await applePay.validateMerchant({
                    validationUrl: event.validationURL,
                });
                session.completeMerchantValidation(validateResult.merchantSession);
            } catch (error) {
                console.error('[Apple Pay] Merchant validation failed:', error);
                session.abort();
                this.allowResubmission();
            }
        };

        // Handle payment authorization.
        session.onpaymentauthorized = async (event) => {
            try {
                // Create an order.
                const orderId = await this.processPaymentForm();

                // Confirm the order with PayPal.
                await applePay.confirmOrder({
                    orderId: orderId,
                    token: event.payment.token,
                    billingContact: event.payment.billingContact,
                });

                // Complete transaction (Apple Pay only supports capture intent).
                await this.completePayPalTransaction(orderId, 'payment', paymentMethod);

                session.completePayment(ApplePaySession.STATUS_SUCCESS);
            } catch (error) {
                console.error('[Apple Pay] Payment failed:', error);
                session.completePayment(ApplePaySession.STATUS_FAILURE);
                this.allowResubmission();
                throw error;
            }
        };

        // Handle cancellation.
        session.oncancel = () => {
            this.allowResubmission();
        };

        // Start the session immediately (synchronously in user gesture handler).
        session.begin();
    }

    /**
     * Render the Google Pay button into the container element for buttons.
     * Uses Google's native PaymentsClient API, similar to the Apple Pay pattern.
     *
     * @param {Object} paymentMethod The payment method object.
     * @param {HTMLElement} buttonsElement The container element for buttons.
     * @return {Promise<void>}
     */
    async renderGooglePayButton(paymentMethod, buttonsElement) {
        const { paypal } = paymentMethod;

        // Check if Google Pay SDK is loaded at class level.
        if (!MeprPayPalVaultingForm.googlePaySDKPromise) {
            return;
        }

        // Wait for SDK to be ready.
        try {
            await MeprPayPalVaultingForm.googlePaySDKPromise;
        } catch (e) {
            console.error('[Google Pay] Failed to load SDK:', e);
            return;
        }

        // Check if Google Pay is supported in the browser.
        if (!MeprPayPalVaultingForm.isGooglePaySupported()) {
            return;
        }

        // Get the Google Pay instance and configuration from PayPal.
        const googlePay = paypal.Googlepay();

        let googlePayConfig;
        try {
            googlePayConfig = await googlePay.config();
        } catch (e) {
            console.error('[Google Pay] Failed to get config:', e);
            return;
        }

        // Store references for later use.
        paymentMethod.googlePay = googlePay;
        paymentMethod.googlePayConfig = googlePayConfig;

        // Create Google PaymentsClient.
        const paymentsClient = new google.payments.api.PaymentsClient({
            environment: paymentMethod.baseConfig.environment === 'production' ? 'PRODUCTION' : 'TEST',
            paymentDataCallbacks: {
                onPaymentAuthorized: (paymentData) => this.onGooglePaymentAuthorized(paymentMethod, paymentData),
            },
        });

        // Store client for cleanup.
        paymentMethod.googlePaymentsClient = paymentsClient;

        // Check if ready to pay.
        const isReadyToPayRequest = {
            apiVersion: 2,
            apiVersionMinor: 0,
            allowedPaymentMethods: googlePayConfig.allowedPaymentMethods,
        };

        try {
            const response = await paymentsClient.isReadyToPay(isReadyToPayRequest);
            if (!response.result) {
                return;
            }
        } catch (e) {
            console.error('[Google Pay] isReadyToPay failed:', e);
            return;
        }

        // Create and render the Google Pay button.
        const button = paymentsClient.createButton({
            onClick: () => this.onGooglePayButtonClicked(paymentMethod),
            buttonColor: 'black',
            buttonType: 'plain',
            buttonSizeMode: 'fill',
        });

        // Set a consistent button height.
        button.style.height = MeprPayPalVaultingGateway.button_height + 'px';

        // Append button to container.
        buttonsElement.appendChild(button);

        // Store button reference for cleanup.
        paymentMethod.googlePayButton = button;
    }

    /**
     * Handle Google Pay button click.
     *
     * @param {Object} paymentMethod The payment method object.
     * @return {Promise<void>}
     */
    async onGooglePayButtonClicked(paymentMethod) {
        if (this.submitting) {
            return;
        }

        const { element, googlePaymentsClient, googlePayConfig } = paymentMethod;

        // Validate the form before showing the Google Pay sheet.
        if (!this.validateForm(element)) {
            return;
        }

        paymentMethod.fundingSource = 'google_pay';
        this.submitting = true;
        this.showLoadingState();

        // Build payment data request.
        const paymentDataRequest = {
            apiVersion: 2,
            apiVersionMinor: 0,
            allowedPaymentMethods: googlePayConfig.allowedPaymentMethods,
            merchantInfo: {
                ...googlePayConfig.merchantInfo,
                // Add merchantName if not already present (required for SCA compliance).
                merchantName: googlePayConfig.merchantInfo.merchantName || MeprPayPalVaultingGateway.google_pay_merchant_name,
            },
            transactionInfo: {
                countryCode: googlePayConfig.countryCode,
                currencyCode: paymentMethod.baseConfig.currency,
                totalPriceStatus: 'FINAL',
                totalPrice: paymentMethod.amount,
            },
            callbackIntents: ['PAYMENT_AUTHORIZATION'],
        };

        try {
            await googlePaymentsClient.loadPaymentData(paymentDataRequest);
        } catch (error) {
            // Only show an error if the user didn't cancel.
            if (error.statusCode !== 'CANCELED') {
                console.error('[Google Pay] Payment failed:', error);
                this.setPayPalError(element, error.message || 'An error occurred with Google Pay.');
            }

            this.allowResubmission();
        }
    }

    /**
     * Handle Google Pay payment authorization.
     * Called automatically by Google PaymentsClient when user authorizes payment.
     *
     * @param {Object} paymentMethod The payment method object.
     * @param {Object} paymentData The payment data from Google Pay.
     * @return {Promise<Object>} Transaction state result.
     */
    async onGooglePaymentAuthorized(paymentMethod, paymentData) {
        const { element, googlePay } = paymentMethod;

        try {
            // Create an order on the backend.
            const orderId = await this.processPaymentForm();

            // Confirm the order with PayPal using Google Pay payment method data.
            const confirmResult = await googlePay.confirmOrder({
                orderId: orderId,
                paymentMethodData: paymentData.paymentMethodData,
            });

            // Check if 3D Secure authentication is required.
            if (confirmResult.status === 'PAYER_ACTION_REQUIRED') {
                await googlePay.initiatePayerAction({ orderId: orderId });
            }

            // Complete the transaction.
            await this.completePayPalTransaction(orderId, 'payment', paymentMethod);

            return { transactionState: 'SUCCESS' };
        } catch (error) {
            console.error('[Google Pay] Payment authorization failed:', error);
            this.setPayPalError(element, error.message || 'An error occurred with Google Pay.');
            this.allowResubmission();
            return {
                transactionState: 'ERROR',
                error: {
                    intent: 'PAYMENT_AUTHORIZATION',
                    message: error.message || 'An error occurred with Google Pay.',
                },
            };
        }
    }

    /**
     * Process the payment form submission.
     * The backend determines whether to create an order or setup token based on payment amount.
     *
     * @return {Promise<string>} A promise that resolves to the order ID or setup token ID.
     */
    async processPaymentForm() {
        const selectedPaymentMethod = this.getSelectedPaymentMethod();

        if (!selectedPaymentMethod) {
            throw new Error('Payment method not found');
        }

        // Clear any previous errors.
        this.clearErrors(selectedPaymentMethod.element);

        const formData = new FormData(this.form);

        // Add action based on the form type.
        formData.append('action', 'mepr_process_' + this.type + '_form');
        formData.append('mepr_current_url', document.location.href);
        formData.append('mepr_paypal_payment_method', selectedPaymentMethod.fundingSource);

        // We don't want to hit our non-Ajax routes for processing the signup or payment forms.
        formData.delete('mepr_process_signup_form');
        formData.delete('mepr_process_payment_form');

        let response;
        try {
            response = await fetch(
                MeprPayPalVaultingGateway.ajax_url,
                {
                    method: 'POST',
                    body: formData
                }
            );
        } catch (error) {
            throw new Error(MeprPayPalVaultingGateway.request_failed);
        }

        let data;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error(MeprPayPalVaultingGateway.invalid_response);
        }

        if (data.success) {
            return data.data;
        }

        // Handle errors.
        if (data.data) {
            if (data.data.errors) {
                this.handleValidationErrors(data.data.errors);
                // Throw with an empty message because errors are already displayed by handleValidationErrors().
                // The onError callback will skip showing a duplicate error when the message is empty.
                throw new Error('');
            } else {
                throw new Error(data.data);
            }
        } else {
            throw new Error(MeprPayPalVaultingGateway.invalid_response);
        }
    }

    /**
     * Completes a PayPal transaction (either capture payment or confirm setup token).
     *
     * @param {string} id The PayPal order ID or setup token ID.
     * @param {string} type The transaction type: 'payment' or 'setup'.
     * @param {Object} paymentMethod The payment method object.
     * @return {Promise<void>}
     */
    async completePayPalTransaction(id, type, paymentMethod) {
        // Ensure the loading state is shown (should already be shown from onClick/handleSubmit).
        this.showLoadingState();

        const isPayment = type === 'payment';
        const idParam = isPayment ? 'mepr_paypal_order_id' : 'mepr_paypal_setup_token_id';
        const errorContext = isPayment ? 'Payment capture' : 'Setup token confirmation';

        try {
            const formData = new FormData();

            // For account updates, use a different action and include subscription data.
            if (this.type === 'update_account') {
                formData.append('action', 'mepr_paypal_vaulting_complete_account_update');
                formData.append('mepr_subscription_id', this.form.querySelector('[name="mepr_subscription_id"]').value);
                formData.append('_ajax_nonce', this.form.querySelector('[name="_ajax_nonce"]').value);
            } else {
                formData.append('action', 'mepr_paypal_vaulting_complete_transaction');
                formData.append('mepr_paypal_payment_method_id', paymentMethod.id);
                formData.append('mepr_paypal_transaction_type', type);
            }

            formData.append(idParam, id);
            formData.append('mepr_paypal_payment_method', paymentMethod.fundingSource);
            formData.append('mepr_current_url', document.location.href);

            const response = await fetch(
                MeprPayPalVaultingGateway.ajax_url,
                {
                    method: 'POST',
                    body: formData
                }
            );

            const data = await response.json();

            if (data.success) {
                // Redirect to the 'Thank You' page.
                window.location.href = data.data;
            } else {
                const errorMessage = data.data || `${errorContext} failed`;
                // Show error at form level.
                const hasErrors = this.form.querySelector('.mepr-form-has-errors');
                if (hasErrors) {
                    hasErrors.textContent = errorMessage;
                    hasErrors.style.display = 'inline';
                }
                this.allowResubmission();
                console.error(`${errorContext} failed:`, errorMessage);
            }
        } catch (e) {
            this.allowResubmission();
            console.error(`Error during ${errorContext.toLowerCase()}:`, e);
        }
    }

    /**
     * Destroy existing buttons and card fields.
     *
     * @param {Object} paymentMethod The payment method object.
     * @return {void}
     */
    destroyPaymentMethod(paymentMethod) {
        // Close buttons if they exist.
        if (paymentMethod.buttons && typeof paymentMethod.buttons.close === 'function') {
            try {
                paymentMethod.buttons.close();
            } catch (e) {
                console.warn('Error closing PayPal buttons:', e);
            }
        }

        // Remove the Apple Pay button if it exists.
        if (paymentMethod.applePayButton) {
            paymentMethod.applePayButton.remove();
            delete paymentMethod.applePayButton;
        }

        // Remove the Google Pay button if it exists.
        if (paymentMethod.googlePayButton) {
            paymentMethod.googlePayButton.remove();
            delete paymentMethod.googlePayButton;
        }

        // Clear Google Pay references.
        delete paymentMethod.googlePaymentsClient;
        delete paymentMethod.googlePay;
        delete paymentMethod.googlePayConfig;

        // Close individual card fields if they exist.
        if (paymentMethod.numberField && typeof paymentMethod.numberField.close === 'function') {
            try {
                paymentMethod.numberField.close();
            } catch (e) {
                console.warn('Error closing number field:', e);
            }
        }

        if (paymentMethod.expiryField && typeof paymentMethod.expiryField.close === 'function') {
            try {
                paymentMethod.expiryField.close();
            } catch (e) {
                console.warn('Error closing expiry field:', e);
            }
        }

        if (paymentMethod.cvvField && typeof paymentMethod.cvvField.close === 'function') {
            try {
                paymentMethod.cvvField.close();
            } catch (e) {
                console.warn('Error closing CVV field:', e);
            }
        }

        // Clear button container HTML.
        const buttonsElement = paymentMethod.element.querySelector('.mepr-paypal-vaulting-buttons');
        if (buttonsElement) {
            buttonsElement.innerHTML = '';
        }

        // Clear card field containers.
        ['mepr-card-field-number', 'mepr-card-field-expiry', 'mepr-card-field-cvv'].forEach(cls => {
            const container = paymentMethod.element.querySelector('.' + cls);
            if (container) {
                container.innerHTML = '';
            }
        });

        // Clear references.
        delete paymentMethod.buttons;
        delete paymentMethod.cardFields;
        delete paymentMethod.numberField;
        delete paymentMethod.expiryField;
        delete paymentMethod.cvvField;
    }

    /**
     * Rebuild the payment method with current properties.
     *
     * @param {Object} paymentMethod The payment method object.
     * @return {Promise<void>}
     */
    async rebuildPaymentMethod(paymentMethod) {
        // Destroy existing elements.
        this.destroyPaymentMethod(paymentMethod);

        // Build the complete config from current paymentMethod properties.
        const completeConfig = this.buildCompleteConfig(
            paymentMethod.baseConfig,
            paymentMethod.intent,
            paymentMethod.vault
        );
        const cacheKey = completeConfig.dataNamespace;

        // Load PayPal SDK (might be cached).
        if (!MeprPayPalVaultingForm.paypalSDKCache[cacheKey]) {
            MeprPayPalVaultingForm.paypalSDKCache[cacheKey] = window.paypalLoadScript(completeConfig);
        }

        paymentMethod.paypal = await MeprPayPalVaultingForm.paypalSDKCache[cacheKey];

        // Render with the current configuration.
        await this.renderPaymentMethod(paymentMethod);
    }

    /**
     * Clear all errors for the form and for a specific payment method element.
     *
     * @param {HTMLElement} element The payment method element.
     * @return {void}
     */
    clearErrors(element) {
        // Clear PayPal-specific errors for this payment method.
        this.setPayPalError(element, '');

        // Clear validation errors from labels.
        this.form.querySelectorAll('.mepr-validation-error').forEach(errorSpan => {
            errorSpan.remove();
        });

        // Clear top-level error messages.
        this.form.querySelectorAll('.mepr-top-error').forEach(errorDiv => {
            errorDiv.remove();
        });

        // Clear general form errors.
        const hasErrors = this.form.querySelector('.mepr-form-has-errors');
        if (hasErrors) {
            hasErrors.style.display = 'none';
        }
    }

    /**
     * Validate the form before processing payment.
     * Clears previous errors and validates all visible inputs.
     *
     * @param {HTMLElement} element The payment method element.
     * @return {boolean} True if form is valid, false otherwise.
     */
    validateForm(element) {
        // Clear any previous errors.
        this.clearErrors(element);

        // Validate all visible form inputs.
        if (typeof window.meprValidateInput === 'function') {
            this.form.querySelectorAll('.mepr-form-input').forEach(input => {
                if (this.isVisible(input)) {
                    window.meprValidateInput(input, true);
                }
            });

            // Check for validation errors.
            const invalidInputs = Array.from(this.form.querySelectorAll('.invalid')).filter(
                input => this.isVisible(input)
            );

            return invalidInputs.length === 0;
        }

        return true;
    }

    /**
     * Shows the loading state by disabling the Submit button and showing the loading indicator.
     *
     * @return {void}
     */
    showLoadingState() {
        const submit = this.form.querySelector('.mepr-submit');
        if (submit) {
            submit.disabled = true;
        }

        const loading = this.form.querySelector('.mepr-loading-gif');
        if (loading) {
            loading.style.display = 'inline';
        }
    }

    /**
     * Allows form resubmission by re-enabling the Submit button and hiding the loading indicator.
     *
     * @return {void}
     */
    allowResubmission() {
        this.submitting = false;

        const submit = this.form.querySelector('.mepr-submit');
        if (submit) {
            submit.disabled = false;
        }

        const loading = this.form.querySelector('.mepr-loading-gif');
        if (loading) {
            loading.style.display = 'none';
        }
    }

    /**
     * Handles PayPal errors.
     *
     * @param {Error} err The error object.
     * @return {void}
     */
    onError(err) {
        const selectedPaymentMethod = this.getSelectedPaymentMethod();

        // Only show an error message if one exists.
        // Validation errors are shown by handleValidationErrors() and throw with empty message.
        if (selectedPaymentMethod?.element && err?.message) {
            this.setPayPalError(selectedPaymentMethod.element, err.message);
        }

        this.allowResubmission();
    }

    /**
     * Handles PayPal order cancellation.
     *
     * @return {void}
     */
    onCancel() {
        this.allowResubmission();
    }

    /**
     * Handles form submission for PayPal Vaulting payments.
     *
     * Note: PayPal buttons are rendered in iframes and cannot be clicked programmatically.
     * Card fields can be submitted programmatically using the submit() method.
     *
     * @param {Event} e The form 'submit' event.
     * @return {void}
     */
    async handleSubmit(e) {
        const selectedPaymentMethod = this.getSelectedPaymentMethod();

        if (selectedPaymentMethod) {
            // For SPC, check if the payment methods wrapper is visible.
            if (this.type === 'signup') {
                const paymentMethodsWrapper = this.form.querySelector('.mepr-payment-methods-wrapper');

                if (paymentMethodsWrapper && !this.isVisible(paymentMethodsWrapper)) {
                    return;
                }
            }

            e.preventDefault();

            if (this.submitting) {
                return;
            }

            this.submitting = true;
            this.showLoadingState();

            // Clear any previous errors.
            this.clearErrors(selectedPaymentMethod.element);

            // If card fields are available and eligible, submit the card fields.
            if (selectedPaymentMethod.cardFields) {
                try {
                    const state = await selectedPaymentMethod.cardFields.getState();

                    // Submit only if the current state of the form is valid.
                    if (state.isFormValid) {
                        selectedPaymentMethod.fundingSource = 'card';
                        const billingAddress = this.getBillingAddress();

                        if (Object.keys(billingAddress).length > 0) {
                            await selectedPaymentMethod.cardFields.submit({ billingAddress });
                        } else {
                            await selectedPaymentMethod.cardFields.submit();
                        }
                    } else {
                        this.setPayPalError(
                            selectedPaymentMethod.element,
                            MeprPayPalVaultingGateway.card_fields_invalid
                        );
                        this.allowResubmission();
                    }
                } catch (error) {
                    // Extract user-friendly error message from PayPal error response.
                    const errorMessage = error?.data?.body?.details?.[0]?.description ||
                                        error?.data?.body?.message ||
                                        MeprPayPalVaultingGateway.card_payment_failed;

                    this.setPayPalError(selectedPaymentMethod.element, errorMessage);
                    this.allowResubmission();
                    console.error('Card field submission error:', error);
                }
            } else {
                // Show an error when card fields are not available.
                this.setPayPalError(
                    selectedPaymentMethod.element,
                    MeprPayPalVaultingGateway.use_paypal_buttons
                );
                this.allowResubmission();
            }
        }
    }

    /**
     * Sets the error message for the specified payment method element.
     *
     * @param {HTMLElement} element The payment method element containing the error div.
     * @param {string} error The error message to be displayed.
     * @return {void}
     */
    setPayPalError(element, error) {
        const errorsDiv = element.querySelector('.mepr-paypal-vaulting-errors');
        if (errorsDiv) {
            errorsDiv.textContent = error;
            errorsDiv.style.display = error ? 'block' : 'none';
        }
    }

    /**
     * Retrieves the selected payment method from the available payment methods.
     *
     * If it's the Single Page Checkout, it checks the selected payment method based on user input.
     * Otherwise, it returns the first payment method.
     *
     * @return {Object|undefined} The selected payment method object if found, or undefined if no method is selected or available.
     */
    getSelectedPaymentMethod() {
        if (this.type === 'signup') {
            const paymentMethodId = this.form.querySelector('input[name="mepr_payment_method"]:checked')?.value;
            return this.paymentMethods.find(({id}) => id === paymentMethodId);
        } else {
            return this.paymentMethods[0];
        }
    }

    /**
     * Handles payment method changes for Single Page Checkout.
     *
     * Toggles the Submit button visibility based on whether PayPal is selected
     * and whether card fields are enabled. If PayPal is selected without card fields,
     * the Submit button is hidden since users must use PayPal buttons instead.
     *
     * @param {string} selectedId The ID of the newly selected payment method.
     * @return {void}
     */
    handlePaymentMethodChanged(selectedId) {
        const selectedPaymentMethod = this.getSelectedPaymentMethod();

        // If PayPal is selected and no card fields, hide the Submit button.
        if (selectedPaymentMethod && selectedPaymentMethod.id === selectedId && !selectedPaymentMethod.cardFields) {
            this.form.classList.add('mepr-paypal-vaulting-hide-submit');
        } else {
            // Not PayPal, or PayPal with card fields - remove our class.
            this.form.classList.remove('mepr-paypal-vaulting-hide-submit');
        }
    }

    /**
     * Retrieves the billing address by mapping form fields to PayPal's billing address format.
     *
     * @return {Object} An object containing the billing address information if found,
     *                  or an empty object if no information is available.
     */
    getBillingAddress() {
        const billingAddress = {};
        const fieldMap = {
            addressLine1: 'mepr-address-one',
            addressLine2: 'mepr-address-two',
            adminArea2: 'mepr-address-city',
            adminArea1: 'mepr-address-state',
            countryCode: 'mepr-address-country',
            postalCode: 'mepr-address-zip'
        };

        const getAddressValue = (fieldName) => {
            // Handle both input and select elements.
            const field = this.form.querySelector(`input[name="${fieldName}"], select[name="${fieldName}"]`);
            let value = field ? field.value : MeprPayPalVaultingGateway.userinfo[fieldName];

            return typeof value === 'string' && value.length ? value : null;
        };

        Object.entries(fieldMap).forEach(([key, field]) => {
            const value = getAddressValue(field);

            if (value) {
                billingAddress[key] = value;
            }
        });

        // PayPal requires country_code if the billing address is provided.
        // Only return the billing address if a country is present.
        if (!billingAddress.countryCode) {
            return {};
        }

        return billingAddress;
    }

    /**
     * Determines if a given DOM element is visible within the document.
     *
     * @param {HTMLElement} element The DOM element to check for visibility.
     * @return {boolean} Returns true if the element is visible, otherwise false.
     */
    isVisible(element) {
        return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
    }

    /**
     * Handles and processes validation errors for a form.
     *
     * This function takes an object or array of validation errors, identifies the specific fields associated with those errors,
     * and displays error messages either next to the relevant form fields or at the top of the form for general errors.
     *
     * @param {Object|Array} errors An object or array where the keys correspond to form field names and the values are the error messages.
     */
    handleValidationErrors(errors) {
        const topErrors = [];

        for (const [key, error] of Object.entries(errors)) {
            const field = this.form.querySelector(`[name="${key}"]`);
            const label = field?.closest('.mp-form-row')?.querySelector('.mp-form-label');

            if (this.isInteger(key) || !label) {
                topErrors.push(error);
            } else {
                const errorSpan = document.createElement('span');
                errorSpan.className = 'mepr-validation-error';
                errorSpan.innerHTML = error;
                label.appendChild(errorSpan);
            }
        }

        if (topErrors.length) {
            const list = document.createElement('ul');
            const wrap = document.createElement('div');

            wrap.className = 'mepr-top-error mepr_error';

            for (const error of topErrors) {
                const listItem = document.createElement('li');
                listItem.innerHTML = MeprPayPalVaultingGateway.top_error.replace('%s', error);
                list.appendChild(listItem);
            }

            wrap.appendChild(list);
            this.form.prepend(wrap);
        }

        const hasErrors = this.form.querySelector('.mepr-form-has-errors');
        if (hasErrors) {
            hasErrors.style.display = 'inline';
        }
    }

    /**
     * Checks if the provided value is an integer.
     *
     * @param {any} value The value to be checked.
     * @return {boolean} Returns true if the value is an integer, otherwise false.
     */
    isInteger(value) {
        return Number.isInteger(Number(value)) && !isNaN(Number(value));
    }

    /**
     * Handles checkout state updates triggered by the meprAfterCheckoutStateUpdated event.
     *
     * @param {Event} e The event object.
     * @param {Object} response The response object from the checkout state update.
     * @return {void}
     */
    async handleCheckoutStateUpdated(e, response) {
        if (response.payment_required && response.paypal_sdk_options) {
            for (const [id, newOptions] of Object.entries(response.paypal_sdk_options)) {
                const paymentMethod = this.paymentMethods.find(pm => pm.id === id);

                if (!paymentMethod) {
                    continue;
                }

                // Check if SDK params changed (intent or vault) that require rebuilding.
                const configChanged =
                    paymentMethod.intent !== newOptions.intent ||
                    paymentMethod.vault !== newOptions.vault;

                // Update PayLater messaging if amount changed.
                if (paymentMethod.amount !== newOptions.amount) {
                    const messagesContainer = paymentMethod.element.querySelector('.mepr-paypal-paylater-message');
                    if (messagesContainer) {
                        messagesContainer.setAttribute('data-pp-amount', newOptions.amount);
                    }
                }

                // Always update properties from new options.
                paymentMethod.intent = newOptions.intent;
                paymentMethod.vault = newOptions.vault;
                paymentMethod.amount = newOptions.amount;
                paymentMethod.recurringPaymentRequest = newOptions.recurringPaymentRequest;

                if (configChanged) {
                    try {
                        await this.rebuildPaymentMethod(paymentMethod);
                    } catch (error) {
                        console.error('Error rebuilding PayPal payment method:', error);
                    }
                }
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', async function () {
    // Check if the PayPal loader is available.
    if (!window.paypalLoadScript) {
        throw new Error('PayPal loader script failed to load properly');
    }

    const forms = document.querySelectorAll('.mepr-signup-form, .mepr-paypal-vaulting-payment-form');

    forms.forEach(form => {
        const instance = new MeprPayPalVaultingForm(form);

        jQuery(form)
            .on('submit', e => instance.handleSubmit(e))
            .on('meprAfterCheckoutStateUpdated', (e, response) => instance.handleCheckoutStateUpdated(e, response))
            .on('meprPaymentMethodChanged', (e, selectedId) => instance.handlePaymentMethodChanged(selectedId));
    });
});
