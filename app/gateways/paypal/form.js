(function ($) {
    $(document).ready(function () {
        $('.mepr-signup-form, #mepr-paypal-payment-form').each(function () {
            new MeprPayPalForm(this);
        });
    });

    function MeprPayPalForm(form) {
        this.form = form;
        this.$form = $(form);
        this.initPayPalSmartButtons();
    }

    MeprPayPalForm.prototype.initPayPalSmartButtons = function () {
        var self = this;

        self.$form.find('.mepr-paypal-button-container').each(function (i, button_container) {
            button_container = $(button_container);
            var button_id = button_container.attr('id');
            var ajax_url = button_container.data('ajax-url');
            var webhook_url = button_container.data('webhook-url');
            var successURL = button_container.data('success-url');
            var isOneTimePayment = button_container.data('is-one-time') === 1;

            if (isOneTimePayment) {
                paypal.Buttons({
                    createOrder: function () {
                        var formData = new FormData(self.$form.get(0));

                        formData.delete('mepr_process_signup_form');
                        formData.delete('mepr_process_payment_form');

                        return fetch(ajax_url, {
                            method: 'post',
                            body: formData
                        }).then(function (res) {
                            console.log(res);
                            return res.json();
                        }).then(function (data) {
                            console.log(data);

                            if (typeof data.errors !== 'undefined') {
                                var errors = data.errors;
                                console.log(typeof errors);
                                errors = Object.values(errors);
                                errors = errors.join('.');
                                self.$form.find('.mepr-paypal-card-errors').html(errors);
                                return;
                            }

                            return data.id; // Use the key sent by your server's response, ex. 'id' or 'token'
                        }).catch(function (e) {
                            var errors = e.data.errors;
                            errors = errors.join('.');
                            self.$form.find('.mepr-paypal-card-errors').text(errors);
                        });
                    },
                    onApprove: function (data, actions) {
                        return fetch(webhook_url, {
                            method: 'post',
                            headers: {
                                'content-type': 'application/json'
                            },
                            body: JSON.stringify({
                                event_type: 'MEMBERPRESS_CAPTURE_ORDER',
                                order_id: data.orderID
                            })
                        }).then(function (res) {
                            return res.json();
                        }).then(function (captureData) {
                            if (captureData.error === 'INSTRUMENT_DECLINED') {
                                return actions.restart();
                            } else {
                                console.log(data);
                                successURL += '?token=' + data.orderID;
                                window.location.href = successURL;
                            }
                        }).catch(function () {
                            var errors = data.errors;
                            errors = errors.join('.');
                            self.$form.find('.mepr-paypal-card-errors').text(errors);
                        });
                    }
                }).render('#' + button_id);
            } else {
                paypal.Buttons({
                    createSubscription: function () {
                        var formData = new FormData(self.$form.get(0));

                        formData.delete('mepr_process_signup_form');
                        formData.delete('mepr_process_payment_form');

                        return fetch(ajax_url, {
                            method: 'post',
                            body: formData
                        }).then(function (res) {
                            return res.json();
                        }).then(function (data) {
                            if (typeof data.errors !== 'undefined') {
                                var errors = data.errors;
                                console.log(typeof errors);
                                errors = Object.values(errors);
                                errors = errors.join('.');
                                self.$form.find('.mepr-paypal-card-errors').html(errors);
                                return;
                            }
                            console.log(data);
                            return data.id; // Use the key sent by your server's response, ex. 'id' or 'token'
                        }).catch(function (e) {
                            var errors = e.errors;
                            errors = errors.join('.');
                            self.$form.find('.mepr-paypal-card-errors').text(errors);
                        });
                    },
                    onApprove: function (data) {
                        console.log(data);
                        successURL += '?subscription_id=' + data.subscriptionID + '&token=' + data.orderID;
                        window.location.href = successURL;
                    }
                }).render('#' + button_id);
            }
        });
    }
})(jQuery);
