(function ($) {
    $(document).ready(function () {
        $('.mepr-signup-form, #mepr-paypal-payment-form').each(function () {
            new MeprPayPalForm(this);
        });
    });

    function MeprPayPalForm(form) {
        this.form = form;
        this.$form = $(form);
        this.isSpc = this.$form.hasClass('mepr-signup-form');
        this.paymentMethods = [];
        this.$orderBumps = this.$form.find('input[name="mepr_order_bumps[]"]');
        this.initPayPalSmartButtons();
        this.$form.find('input[name="mepr_payment_method"]').on('change', $.proxy(this.maybeCreateSmartButtons, this));
        this.$orderBumps.on('change', $.proxy(this.maybeCreateSmartButtons, this));
    }

    MeprPayPalForm.prototype.initPayPalSmartButtons = function () {
        var self = this;

        self.$form.find('.mepr-paypal-button-container').each(function (i, container) {
            var $container = $(container);

            self.paymentMethods.push({
                id: $container.data('payment-method-id'),
                $container: $container,
                successUrl: $container.data('success-url'),
                transactionId: 0,
                productIds: null,
                smartButtonMode: null,
                creatingSmartButtons: false,
                createSmartButtonsOnReady: false
            });
        });

        self.maybeCreateSmartButtons();
    };

    MeprPayPalForm.prototype.getSelectedPaymentMethod = function () {
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

    MeprPayPalForm.prototype.maybeCreateSmartButtons = function () {
        var paymentMethod = this.getSelectedPaymentMethod();

        if (paymentMethod && (paymentMethod.productIds === null || paymentMethod.productIds !== this.getProductIds())) {
            this.createSmartButtons(paymentMethod);
        }
    };

    MeprPayPalForm.prototype.getProductIds = function () {
        var data = [];

        this.$orderBumps.each(function (i, orderBump) {
            var $orderBump = $(orderBump);

            if($orderBump.is(':checked')) {
                data.push($orderBump.val());
            }
        });

        return data.join(',');
    };

    MeprPayPalForm.prototype.createSmartButtons = function (paymentMethod) {
        if (paymentMethod.creatingSmartButtons) {
            paymentMethod.createSmartButtonsOnReady = true;
            return;
        }

        paymentMethod.creatingSmartButtons = true;

        var self = this,
            formData = new FormData(self.form);

        formData.append('action', 'mepr_paypal_commerce_get_smart_button_mode');
        formData.delete('mepr_process_signup_form');
        formData.delete('mepr_process_payment_form');

        paymentMethod.productIds = self.getProductIds();

        fetch(MeprPayPalCommerceL10n.ajax_url, {
            method: 'post',
            body: formData,
        }).then(function (response) {
            return response.json();
        }).then(function (response) {
            if (response && typeof response.success === 'boolean') {
                if (response.success) {
                    if (response.data === paymentMethod.smartButtonMode) {
                        paymentMethod.creatingSmartButtons = false;

                        if (paymentMethod.createSmartButtonsOnReady) {
                            paymentMethod.createSmartButtonsOnReady = false;
                            self.createSmartButtons(paymentMethod);
                        }

                        return;
                    }

                    paymentMethod.smartButtonMode = response.data;

                    if (paymentMethod.buttons && paymentMethod.buttons.close) {
                        paymentMethod.buttons.close();
                    }

                    var config,
                        callback = function () {
                            var formData = new FormData(self.form);

                            formData.append('action', 'mepr_paypal_commerce_create_smart_button');
                            formData.delete('mepr_process_signup_form');
                            formData.delete('mepr_process_payment_form');

                            return fetch(MeprPayPalCommerceL10n.ajax_url, {
                                method: 'post',
                                body: formData
                            }).then(function (response) {
                                return response.json();
                            }).then(function (response) {
                                if (response && typeof response.success === 'boolean') {
                                    if (response.success) {
                                        paymentMethod.transactionId = response.data.txn_id;

                                        return response.data.id;
                                    } else if (response.data.errors) {
                                        throw new Error(Object.values(response.data.errors).join('.'))
                                    }
                                }

                                throw new Error('Invalid response');
                            }).catch(function (e) {
                                self.$form.find('.mepr-paypal-card-errors').html(e.message);
                                throw e;
                            });
                      };

                    if (response.data === 'subscription') {
                        config = {
                            createSubscription: callback,
                            onApprove: function (data) {
                                window.location.href = paymentMethod.successUrl + '?txn_id=' + paymentMethod.transactionId + '&subscription_id=' + data.subscriptionID + '&token=' + data.orderID;
                            }
                        }
                    } else {
                        config = {
                            createOrder: callback,
                            onApprove: function (data) {
                                window.location.href = paymentMethod.successUrl + '?txn_id=' + paymentMethod.transactionId + '&token=' + data.orderID;
                            }
                        }
                    }

                    config.onInit = function () {
                        paymentMethod.creatingSmartButtons = false;

                        if (paymentMethod.createSmartButtonsOnReady) {
                            paymentMethod.createSmartButtonsOnReady = false;
                            self.createSmartButtons(paymentMethod);
                        }
                    }

                    paymentMethod.buttons = paypal.Buttons(config);
                    paymentMethod.buttons.render(paymentMethod.$container.get(0));
                } else {
                    paymentMethod.creatingSmartButtons = false;
                    console.log(response.data);
                }
            } else {
                paymentMethod.creatingSmartButtons = false;
                console.log('Invalid response');
            }
        }).catch(function (e) {
            paymentMethod.creatingSmartButtons = false;
            console.log(e);
        });
    };
})(jQuery);
