class MeprSquareForm {
    /**
     * Creates an instance of the class managing Square payment methods for the specified form.
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
     * Initializes payment methods for the form by setting up Square payment card elements.
     * The method retrieves eligible payment elements, attaches the Square payment card object
     * to each element, and stores relevant data in the paymentMethods array.
     *
     * @return {void} This method does not return any value.
     */
    initPaymentMethods() {
        this.form.querySelectorAll('.mepr-square-card-element').forEach(async element => {
            try {
                const payments = window.Square.payments(element.dataset.applicationId, element.dataset.locationId),
                    card = await payments.card();

                await card.attach(element);

                let verificationDetails = null;

                try {
                    verificationDetails = JSON.parse(element.dataset.verificationDetails);
                } catch (e) {
                    console.error('Invalid JSON in data attribute:', e);
                }

                this.paymentMethods.push({
                    id: element.dataset.paymentMethodId,
                    element,
                    payments,
                    card,
                    verificationDetails
                });
            } catch (e) {
                console.error('Failed to initialize card.', e);
            }
        });
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
     * Handle checkout form submission.
     *
     * @param {jQuery.Event|Event} e
     */
    async handleSubmit(e) {
        let selectedPaymentMethod = this.getSelectedPaymentMethod();

        if (selectedPaymentMethod) {
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

            const hasErrors = this.form.querySelector('.mepr-form-has-errors');
            if (hasErrors) {
                hasErrors.style.display = 'none';
            }

            const submit = this.form.querySelector('.mepr-submit');
            if (submit) {
                submit.disabled = true;
            }

            const loading = this.form.querySelector('.mepr-loading-gif');
            if (loading) {
                loading.style.display = 'inline';
            }

            this.setSquareError(selectedPaymentMethod, '');

            let token;

            try {
                token = await this.tokenize(
                    selectedPaymentMethod.card,
                    this.getVerificationDetails(selectedPaymentMethod.verificationDetails)
                );
            } catch (e) {
                this.allowResubmission();
                console.error(e.message);
                return;
            }

            try {
                const formData = new FormData(this.form);

                formData.append('action', 'mepr_process_' + this.type + '_form');
                formData.append('mepr_current_url', document.location.href);
                formData.append('mepr_square_idempotency_key', window.crypto.randomUUID());
                formData.append('mepr_square_source_id', token);

                // We don't want to hit our non-Ajax routes for processing the signup or payment forms.
                formData.delete('mepr_process_signup_form');
                formData.delete('mepr_process_payment_form');

                const response = await fetch(
                    MeprSquareGateway.ajax_url,
                    {
                        method: 'POST',
                        body: formData
                    }
                );

                const data = await response.json();

                if (data.success) {
                    this.allowResubmission();
                    window.location.href = data.data;
                } else if (data.data.errors) {
                    this.handleValidationErrors(data.data.errors);
                } else {
                    this.handlePaymentError(selectedPaymentMethod, data.data);
                }
            } catch (e) {
                this.handlePaymentError(selectedPaymentMethod, MeprSquareGateway.request_failed);
                console.error(e.message);
            }
        }
    }

    /**
     * Tokenize the given payment method.
     *
     * @param   {object} paymentMethod The Square payment method.
     * @param   {object} verificationDetails The verification details object.
     * @returns {string} The tokenized payment method string.
     * @throws  {Error} If there was an error during tokenization.
     */
    async tokenize(paymentMethod, verificationDetails) {
        const tokenResult = await paymentMethod.tokenize(verificationDetails);

        if (tokenResult.status === 'OK') {
            return tokenResult.token;
        } else {
            let errorMessage = `Tokenization failed-status: ${tokenResult.status}`;

            if (tokenResult.errors) {
                errorMessage += ` and errors: ${JSON.stringify(
                    tokenResult.errors
                )}`;
            }

            throw new Error(errorMessage);
        }
    }

    /**
     * Adds billing contact information and required additional flags to the verification details.
     *
     * @param {Object} verificationDetails The initial verification details to process.
     * @return {Object} A new object containing merged verification details, billing contact data,
     *                   and additional flags indicating the method of entry.
     */
    getVerificationDetails = verificationDetails => ({
        ...verificationDetails,
        billingContact: this.getBillingContact(),
        customerInitiated: true,
        sellerKeyedIn: false,
    })

    /**
     * Retrieves the billing contact details by mapping specific form fields or user information
     * to a structured billing contact object.
     *
     * @return {Object} An object containing the billing contact information if found,
     *                  or an empty object if no information is available.
     */
    getBillingContact() {
        const billingContact = {},
            fieldMap = {
                email: 'user_email',
                givenName: 'user_first_name',
                familyName: 'user_last_name',
                addressLines: ['mepr-address-one', 'mepr-address-two'],
                city: 'mepr-address-city',
                countryCode: 'mepr-address-country',
                state: 'mepr-address-state',
                postalCode: 'mepr-address-zip'
            },
            getContactValue = (fieldName) => {
                if (Array.isArray(fieldName)) {
                    const values = [];

                    fieldName.forEach(fieldName => {
                        const field = this.form.querySelector(`input[name="${fieldName}"]`);
                        let value = field ? field.value : MeprSquareGateway.userinfo[fieldName];

                        if (typeof value === 'string' && value.length) {
                            values.push(value);
                        }
                    });

                    return values;
                }

                const field = this.form.querySelector(`input[name="${fieldName}"]`);
                let value = field ? field.value : MeprSquareGateway.userinfo[fieldName];

                return typeof value === 'string' && value.length ? value : null;
            };

        Object.entries(fieldMap).forEach(([key, field]) => {
            const value = getContactValue(field);

            if (value && value.length) {
                billingContact[key] = value;
            }
        });

        return billingContact;
    }

    /**
     * Update the verification details when checkout state changes.
     *
     * @param {Object} event The event object that triggered the state update.
     * @param {Object} response The server response containing checkout state data.
     * @param {boolean} response.payment_required Indicates if payment is required.
     * @param {Object} response.square_verification Contains verification details for payment methods.
     */
    handleCheckoutStateUpdated = (event, response) => {
        if (response.payment_required && response.square_verification) {
            Object.entries(response.square_verification).forEach(([key, value]) => {
                const paymentMethod = this.paymentMethods.find(paymentMethod => paymentMethod.id === key);

                if (paymentMethod) {
                    paymentMethod.verificationDetails = value;
                }
            });
        }
    }

    /**
     * Handles and processes validation errors for a form.
     *
     * This function takes an object or array of validation errors, identifies the specific fields associated with those errors,
     * and displays error messages either next to the relevant form fields or at the top of the form for general errors.
     * It also ensures resubmission is allowed and updates the error-related UI appropriately.
     *
     * @param {Object|Array} errors An object or array where the keys correspond to form field names and the values are the error messages.
     */
    handleValidationErrors = errors => {
        this.allowResubmission();

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
                listItem.innerHTML = MeprSquareGateway.top_error.replace('%s', error);
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
     * Handles the payment error by allowing resubmission of the form and updating the UI to display errors.
     *
     * @param {object} selectedPaymentMethod The selected payment method data.
     * @param {string} error The error message to be displayed in the form.
     * @return {void} This method does not return any value.
     */
    handlePaymentError(selectedPaymentMethod, error) {
        this.allowResubmission();
        this.setSquareError(selectedPaymentMethod, error);

        const hasErrors = this.form.querySelector('.mepr-form-has-errors');
        if (hasErrors) {
            hasErrors.style.display = 'inline';
        }
    }

    /**
     * Sets the error message for the specified payment method element.
     *
     * @param {Object} selectedPaymentMethod The payment method object containing the element for which the error is to be set.
     * @param {string} error The error message to be displayed.
     * @return {void}
     */
    setSquareError(selectedPaymentMethod, error) {
        if (selectedPaymentMethod.element) {
            const container = selectedPaymentMethod.element.closest('.mepr-square-elements');
            if (container) {
                const errors = container.querySelector('.mepr-square-errors');
                if (errors) {
                    errors.textContent = error;
                }
            }
        }
    }

    /**
     * Enables resubmission of a form by resetting the submission state.
     * This method sets the `submitting` flag to `false`, enables the submit button if it exists,
     * and hides the loading animation if present.
     *
     * @return {void} This method does not return a value.
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

        const errorsToRemove = this.form.querySelectorAll('.mepr-validation-error, .mepr-top-error');
        errorsToRemove.forEach(element => {
            element.remove();
        });
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
     * Checks if the provided value is an integer.
     *
     * @param {any} value The value to be checked.
     * @return {boolean} Returns true if the value is an integer, otherwise false.
     */
    isInteger(value) {
        return Number.isInteger(Number(value)) && !isNaN(Number(value));
    }
}

document.addEventListener('DOMContentLoaded', async function () {
    if (!window.Square) {
        throw new Error('Square.js failed to load properly');
    }

    const forms = document.querySelectorAll('.mepr-signup-form, .mepr-square-payment-form');

    forms.forEach(form => {
        const instance = new MeprSquareForm(form);

        jQuery(form)
            .on('submit', e => instance.handleSubmit(e))
            .on('meprAfterCheckoutStateUpdated', (e, response) => instance.handleCheckoutStateUpdated(e, response));
    });
});
