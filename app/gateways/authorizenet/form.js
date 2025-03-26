(function ($) {
    class MeprAuthorizeNetForm {
        constructor(form) {
            this.form = form;
            this.$form = $(form);
            this.$form.on('submit', $.proxy(this.handleSubmit, this));
            this.isSpc = this.$form.hasClass('mepr-signup-form');
        }

        handleSubmit(e) {
            let publicKey = this.getPublicKey();

            if (!publicKey) {
                return true;
            }

            this.$form.find('.mepr-loading-gif').show();

            if (this.$form.find('.mepr-payment-methods-wrapper').is(':hidden')) {
                this.form.submit();
                return;
            }

            const container = this.getSelectedWrapper();

            if (container.find('.dataValue').val().length > 0) {
                return true;
            }

            e.preventDefault();
            this.sendPaymentDataToAnet();
            return false;
        }

        /**
         * Returns the form fields in a pretty key/value hash
         *
         * @return {object}
         */
        getFormData() {
            var formData = new FormData(this.$form.get(0));
            return Array.from(formData.entries()).reduce(function (obj, item) {
                obj[item[0]] = item[1];
                return obj;
            }, {});
        }

        getPublicKey() {
            if (this.isSpc) {
                var paymentMethodId = this.$form.find('input[name="mepr_payment_method"]:checked').val();
                return this.$form.find('.mepr_payment_method-'+ paymentMethodId +' [data-authorizenet]').data('public-key');
            } else {
                return this.$form.find('[data-authorizenet]').data('public-key');
            }
        }

        getTestMode() {
            if (this.isSpc) {
                var paymentMethodId = this.$form.find('input[name="mepr_payment_method"]:checked').val();
                return this.$form.find('.mepr_payment_method-'+ paymentMethodId +' [data-authorizenet]').data('is-test');
            } else {
                return this.$form.find('[data-authorizenet]').data('is-test');
            }
        }

        allowResubmission() {
            this.$form.find('.mepr-submit').prop('disabled', false);
            this.$form.find('.mepr-loading-gif').hide();
            this.$form.find('.mepr-form-has-errors').show();
            this.$form.find('.mepr-validation-error, .mepr-top-error').remove();
        }

        getLoginID() {
            if (this.isSpc) {
                var paymentMethodId = this.$form.find('input[name="mepr_payment_method"]:checked').val();
                return this.$form.find('.mepr_payment_method-'+ paymentMethodId +' [data-authorizenet]').data('login-id');
            } else {
                return this.$form.find('[data-authorizenet]').data('login-id');
            }
        }

        getSelectedWrapper() {
            if (this.isSpc) {
                var paymentMethodId = this.$form.find('input[name="mepr_payment_method"]:checked').val();
                return this.$form.find('.mepr_payment_method-'+ paymentMethodId +' [data-authorizenet-fields]');
            } else {
                return this.$form.find('[data-authorizenet-fields]');
            }
        }

        sendPaymentDataToAnet() {
            var authData = {};
            var self = this;
            authData.clientKey = this.getPublicKey();
            authData.apiLoginID = this.getLoginID();

            var cardData = {};
            const container = this.getSelectedWrapper();
            cardData.cardNumber = container.find(".card-number").val().replace(/\s/g, '');
            cardData.month = container.find(".expiry").val().substring(0, 2).trim();
            cardData.year = container.find(".expiry").val().substring(2).trim();
            cardData.cardCode = container.find(".cvc").val();

            var secureData = {};
            secureData.authData = authData;
            secureData.cardData = cardData;

            if (this.getTestMode() == 'on') {
                self.loadScript('https://jstest.authorize.net/v1/Accept.js');
            } else {
                self.loadScript('https://js.authorize.net/v1/Accept.js');
            }
            setTimeout(function () {
                Accept.dispatchData(secureData, $.proxy(self.responseHandler, self));
            }, 1000);
        }
        loadScript(url) {
            var b = document.createElement("script");
            b.type = "text/javascript", b.src = url, (document.getElementsByTagName("head")[0] || document.documentElement).appendChild(b)
        }
        responseHandler(response) {
            var self = this;
            var container = self.getSelectedWrapper();
            function paymentFormUpdate(opaqueData, form) {
                console.log(form);
                console.log(opaqueData);
                const container = form.getSelectedWrapper();
                container.find(".dataDescriptor").val(opaqueData.dataDescriptor);
                container.find(".dataValue").val(opaqueData.dataValue);
                container.find(".card-number").val('');
                container.find(".expiry, .expiry-month, .expiry-year").val('');
                container.find(".cvc").attr('name', 'cvc-code');
                form.form.submit();
                /*

                // If using your own form to collect the sensitive data from the customer,
                // blank out the fields before submitting them to your server.
                document.getElementById("cardNumber").value = "";
                document.getElementById("expMonth").value = "";
                document.getElementById("expYear").value = "";
                document.getElementById("cardCode").value = "";
                document.getElementById("accountNumber").value = "";
                document.getElementById("routingNumber").value = "";
                document.getElementById("nameOnAccount").value = "";
                document.getElementById("accountType").value = "";*/

                //document.getElementById("paymentForm").submit();
            }

            container.find('.mepr-authorizenet-errors').html('');
            if (response.messages.resultCode === "Error") {
                var i = 0;
                while (i < response.messages.message.length) {
                    container.find('.mepr-authorizenet-errors').append("<p>" +  response.messages.message[i].text + "</p>");
                    this.allowResubmission();
                    i = i + 1;
                }
                container.find('.mepr-authorizenet-errors').show();
            } else {
                container.find('.mepr-authorizenet-errors').hide();
                paymentFormUpdate(response.opaqueData, self);
            }
        }
    }

    $(document).ready(function () {
        $('.mepr-signup-form, #mepr-authorizenet-payment-form').each(function () {
            new MeprAuthorizeNetForm(this);
        });
    });


})(jQuery);
