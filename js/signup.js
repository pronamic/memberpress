(function($) {
  $(document).ready(function() {
    $('body').on('click', '.mepr-signup-form .have-coupon-link', function(e) {
      e.preventDefault();
      $(this).hide();
      $('div.mepr_coupon_'+$(this).data("prdid")).show();
    });

    $('body').on('keydown', '.mepr-signup-form .mepr-coupon-code', function(e) {
      if(e.which === 13) {
        e.preventDefault();
        $(this).trigger('blur');
      }
    });

    // Store the default price string
    $('.mepr_price_cell').each(function () {
      $(this).data('default-price-string', $(this).text());
    });

    var meprValidateInput = function (obj, submitting) {
      $(obj).removeClass('invalid');

      var form = $(obj).closest('.mepr-signup-form');

      if ($(obj).attr('required') !== undefined) {
        var notBlank = mpValidateFieldNotBlank($(obj));
        mpToggleFieldValidation($(obj), notBlank);
      }

      // Validate actual email only if it's not empty otherwise let the required/un-required logic hold
      if ($(obj).attr('type')==='email' && $(obj).val().length > 0) {
        var validEmail = mpValidateEmail($(obj).val());
        mpToggleFieldValidation($(obj), validEmail);
      }

      // Validate the URL by using the browser validation functions
      if ($(obj).attr('type')==='url' && $(obj).val().length > 0) {
        var validURL = mpValidateUrl($(obj).val());
        mpToggleFieldValidation($(obj), validURL);
      }

      if ($(obj).hasClass('mepr-password-confirm')) {
        var confirmMatch = $(obj).val() === form.find('.mepr-password').val();
        mpToggleFieldValidation($(obj), confirmMatch);
      }

      if($(obj).hasClass('mepr-coupon-code') && !submitting) {
        var price_string = form.find('div.mepr_price_cell');

        if($(obj).val().match(/(\s|\S)/)) {
          $(obj).prev('.mp-form-label').find('.mepr-coupon-loader').fadeIn();

          var data = {
            action: 'mepr_validate_coupon',
            code: $(obj).val(),
            prd_id: $(obj).data("prdid"),
            coupon_nonce: MeprSignup.coupon_nonce
          };

          $.post(MeprI18n.ajaxurl, data, function(res) {
            $(obj).prev('.mp-form-label').find('.mepr-coupon-loader').hide();
            res = res.trim();
            mpToggleFieldValidation($(obj), (res.toString() == 'true'));

            if(res.toString() == 'true') {

              // Let's update price string
              meprUpdatePriceTerms(form);
            }
            else {
              form.find('.mepr-payment-methods-wrapper:hidden').show();
              form.find('input[name="mepr_payment_methods_hidden"]').remove();
              price_string.text(price_string.data('default-price-string'));

              // Invalid Coupon - update SPC Invoice
              meprUpdatePriceTerms(form);
            }

          });
        }
        else if($(obj).val().trim() === '') {
          if (form.find('.mepr-payment-methods-wrapper').is(':hidden')) {
            // Looks like we need to restore the payment methods
            form.find('.mepr-payment-methods-wrapper').show();
            form.find('input[name="mepr_payment_methods_hidden"]').remove();
          }

          price_string.text(price_string.data('default-price-string'));
          // Enpty Coupon - update SPC Invoice
          meprUpdatePriceTerms(form);
        }
      }

      $(obj).trigger('mepr-validate-input');
    };

    window.mepr_validate_input = meprValidateInput;

    var meprUpdatePriceTerms = function (form) {
      var price_string = form.find('div.mepr_price_cell');

      let settings = {
        url: MeprI18n.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          code: form.find('input[name="mepr_coupon_code"]').val(),
          prd_id: form.find('input[name="mepr_product_id"]').val(),
          'mepr-address-one': form.find('input[name="mepr-address-one"]').val(),
          'mepr-address-two': form.find('input[name="mepr-address-two"]').val(),
          'mepr-address-city': form.find('input[name="mepr-address-city"]').val(),
          'mepr-address-state': form.find('select[name="mepr-address-state"]').is(':visible') ? form.find('select[name="mepr-address-state"]').val() : form.find('input[name="mepr-address-state"]').val(),
          'mepr-address-country': form.find('select[name="mepr-address-country"]').val(),
          'mepr-address-zip': form.find('input[name="mepr-address-zip"]').val(),
          mepr_vat_number: form.find('input[name="mepr_vat_number"]').val(),
          mepr_vat_customer_type: form.find('input[name="mepr_vat_customer_type"]:checked').val(),
          coupon_nonce: MeprSignup.coupon_nonce,
          mpca_corporate_account_id: form.find('input[name="mpca_corporate_account_id"]').val()
        }
      }

      if(form.find('input[name="mpgft-signup-gift-checkbox"]').length > 0){
        settings.data.mpgft_gift_checkbox = form.find('input[name="mpgft-signup-gift-checkbox"]').is(':checked');
      }

      if( settings.data.mpgft_gift_checkbox !== undefined && settings.data.mpgft_gift_checkbox ) {
        form.find('.mepr-order-bumps').hide();
      } else {
        form.find('.mepr-order-bumps').show();
      }

      if(form.find('input[name="mepr_order_bumps[]"]:checked').length > 0 ){
        var mepr_order_bumps = [];
        if( settings.data.mpgft_gift_checkbox != undefined && settings.data.mpgft_gift_checkbox ) {
          var elem_click_trigger_needed = false;
          form.find('input[name="mepr_order_bumps[]"]:checked').each(function () {
            elem_click_trigger_needed = $(this);
            elem_click_trigger_needed.prop('checked',false).removeAttr('checked');
          });

          if( elem_click_trigger_needed ) {
            form.find('input[name="mepr_order_bumps[]"]:checked').trigger('click');
          }
        } else {
          form.find('input[name="mepr_order_bumps[]"]:checked').each(function () {
            mepr_order_bumps.push($(this).val());
          });
        }

        settings.data.mepr_order_bumps = mepr_order_bumps;
      }

      // Let's update terms
      settings.data.action = 'mepr_update_price_string';
      $.ajax(settings)
      .done(function (response) {
        if (response && typeof response == 'object' && response.status === 'success') {
          form.trigger('meprPriceStringUpdated', [response]);
          if(price_string.length) {
            var scroll_top = price_string.offset().top;
            price_string.html(response.price_string);
          }

          if(response.payment_required) {
            form.find('.mepr-payment-methods-wrapper').show();
            form.find('input[name="mepr_payment_methods_hidden"]').remove();
          } else {
            form.find('.mepr-payment-methods-wrapper').hide();
            form.append('<input type="hidden" name="mepr_payment_methods_hidden" value="1">');

            // Clear validation errors on fields now hidden
            form.find('.mepr-payment-methods-wrapper .mepr-form-input').each(function () {
              meprValidateInput(this);
            });
          }

          form.trigger('meprAfterPriceStringUpdated', [response]);
        }
      });

      // Let's update SPC Invoice
      if (MeprSignup.spc_enabled == '1'){
        settings.data.action = 'mepr_update_spc_invoice_table';

        // Show SPC invoice table loader
        form.find('.mepr-invoice-loader').fadeIn();

        $.ajax(settings)
        .done(function (response) {
          if (response && typeof response == 'object' && response.status === 'success') {
            $(form).find('.mepr-transaction-invoice-wrapper > div').replaceWith(response.invoice);
          }
          $(form).find('.mepr-invoice-loader').hide();
          $(form).find(".mepr-transaction-invoice-wrapper .mp_invoice").css({ opacity: 1 });
        });
      }
    }


    $('body').on('focus', '.mepr-form .mepr-form-input', function (e) {
      $(this).prev('.mp-form-label').find('.cc-error').hide();
      $(this).removeClass('invalid');
    });

    $('body').on('blur', '.mepr-form .mepr-form-input', function (e) {
      //Don't validate date fields here, wait til the push the submit button
      if(!$(this).hasClass('mepr-date-picker')) {
        meprValidateInput(this);
      }
    });

    // Specific to validating with the datepicker ui text field
    $('body').on('mepr-date-picker-closed', '.mepr-form .mepr-form-input.mepr-date-picker', function (e, date, inst) {
      meprValidateInput(this);
    });

    $('body').on('click', '.mepr-signup-form .mepr-submit', function (e) {
      // We want to control if/when the form is submitted
      e.preventDefault();

      var form = $(this).closest('.mepr-signup-form');
      var button = $(this);

      $.each(form.find('.mepr-form-input:visible'), function(i,obj) {
        meprValidateInput(obj, true);
      });

      if (0 < form.find('.invalid:visible').length) {
        form.find('.validation').addClass('failed');
      }
      else {
        var submittedTelInputs = document.querySelectorAll(".mepr-tel-input");
        for (var i = 0; i < submittedTelInputs.length; i++) {
          var iti = window.intlTelInputGlobals.getInstance(submittedTelInputs[i]);
          submittedTelInputs[i].value = iti.getNumber();
        }
        form.find('.validation').addClass('passed');
        this.disabled = true;
        form.find('.mepr-loading-gif').show();
        $(this).trigger('mepr-register-submit');
        form.submit();
      }
    });

    $('body').on('click', '.mepr-signup-form div[class^=mepr-payment-method] input.mepr-form-radio', function () {
      var form = $(this).closest('.mepr-signup-form');

      // Reset the transaction ID to prevent any issues after switching payment methods
      form.find('input[name="mepr_transaction_id"]').val('');

      var pmid = '.mp-pm-desc-' + $(this).val();
      var pmid_exists = (form.find(pmid).length > 0);

      form.find('.mepr-payment-method-desc-text').addClass('mepr-close');

      if(pmid_exists) {
        form.find(pmid).removeClass('mepr-close');
      }

      //If nothing has the mepr-close class, we still need to show this one's description
      var mepr_close_exists = (form.find('.mepr-payment-method-desc-text.mepr-close').length > 0);

      if(mepr_close_exists) {
        form.find('.mepr-payment-method-desc-text.mepr-close').slideUp({
          duration: 200,
          complete: function() {
            // Clear validation errors on fields now hidden
            form.find('.mepr-payment-methods-wrapper .mepr-form-input').each(function () {
              $('.mepr-form-input.invalid').removeClass('.invalid');
              $('.cc-error').hide();
              $('.mepr-form-has-errors').hide();
            });

            if(pmid_exists) {
              form.find(pmid).slideDown(200);
            }
          }
        });
      } else {
        // Clear validation errors on fields now hidden
        form.find('.mepr-payment-methods-wrapper .mepr-form-input').each(function () {
          $('.mepr-form-input.invalid').removeClass('.invalid');
          $('.cc-error').hide();
          $('.mepr-form-has-errors').hide();
        });

        if(pmid_exists) {
          form.find(pmid).slideDown(200);
        }
      }
    });

    // Update price string & invoice when certain inputs change value
    $("body").on("change",
      ".mepr-form .mepr-form-input, .mepr-form .mepr-form-radios-input, .mepr-form .mepr-select-field",
      function (e) {

        if($(this).attr('name') == 'mepr-address-zip' ||
          $(this).attr('name') == 'mepr-address-city' ||
          $(this).attr('name') == 'mepr-address-country' ||
          $(this).attr('name') == 'mepr-address-one' ||
          $(this).attr('name') == 'mepr-address-state' ||
          $(this).attr('name') == 'mepr_vat_customer_type' ||
          $(this).attr('name') == 'mepr_vat_number' ||
          $(this).attr('name') == 'mpgft-signup-gift-checkbox'
        ) {
          let form = $(this).closest(".mepr-signup-form");
          meprUpdatePriceTerms(form);
        }
      }
    );

    // Update price string & invoice when geolocation occurs
    $('body').on('mepr-geolocated', '.mepr-form .mepr-countries-dropdown', function () {
      meprUpdatePriceTerms($(this).closest('.mepr-signup-form'));
    });


    $(".mepr-replace-file").each(function(){
      $(this).closest('div').find('.mepr-file-uploader').hide();
    });
    $('body').on('click', '.mepr-replace-file', function (e) {
      $(this).closest('div').find('.mepr-file-uploader').toggle();
    });

    // Set the visibility of payment methods based on order bumps
    $('body').on('click', '.mepr-signup-form input[name="mepr_order_bumps[]"]', function () {
      var $input = $(this),
          $order_bump = $input.closest('.mepr-order-bump'),
          $order_bumps = $order_bump.closest('.mepr-order-bumps'),
          $form = $order_bumps.closest('.mepr-signup-form'),
          has_order_bump = false,
          has_sub = false,
          $to_hide = $(),
          $to_show = $(),
          $compatible_pm,
          $incompatible_pm;

      $order_bumps.find('input[name="mepr_order_bumps[]"]:checked').each(function () {
        has_order_bump = true;

        if($(this).closest('.mepr-order-bump').hasClass('mepr-sub')) {
          has_sub = true;
        }
      });

      $form.find('div[class^=mepr-payment-method] input.mepr-form-radio, input[type="hidden"][name="mepr_payment_method"]').each(function () {
        var $pm = $(this);

        if(has_order_bump) {
          if($pm.hasClass('mepr-can-order-bumps')) {
            if(has_sub) {
              if($pm.hasClass('mepr-can-multiple-subscriptions')) {
                $to_show = $to_show.add($pm);

                if(!$compatible_pm) {
                  $compatible_pm = $pm;
                }
              } else {
                $to_hide = $to_hide.add($pm);

                if($pm.prop('checked')) {
                  $incompatible_pm = $pm;
                }
              }
            } else {
              $to_show = $to_show.add($pm);

              if(!$compatible_pm) {
                $compatible_pm = $pm;
              }
            }
          } else {
            $to_hide = $to_hide.add($pm);

            if($pm.prop('checked')) {
              $incompatible_pm = $pm;
            }
          }
        } else {
          $to_show = $to_show.add($pm);
        }
      });

      if(!$to_show.length) {
        alert(MeprSignup.no_compatible_pms);
        return false;
      }

      if($incompatible_pm) {
        if($compatible_pm) {
          var compatible_pm_label = $compatible_pm.data('payment-method-type'),
              prompt = MeprSignup.switch_pm_prompt.replace('%s', compatible_pm_label);

          if($.magnificPopup) {
            var $switch_button = $('<div class="mepr-btn">').text(MeprSignup.switch_pm.replace('%s', compatible_pm_label)).on('click', function () {
              $compatible_pm.trigger('click');
              $input.trigger('click');
              $.magnificPopup.close();
            });

            var $cancel_button = $('<div class="mepr-btn mepr-btn-secondary">').text(MeprSignup.cancel).on('click', function () {
              $.magnificPopup.close();
            });

            var $popup_content = $('<div class="mepr-switch-pm-popup">').append(
              $('<img class="mepr-switch-pm-popup-icon">').attr('src', MeprSignup.warning_icon_url),
              $('<p>').text(prompt),
              $('<div class="mepr-switch-pm-popup-buttons">').append($switch_button, $cancel_button)
            );

            $.magnificPopup.open({
              mainClass: 'mepr-switch-pm-mfp',
              items: {
                src: $popup_content,
                type: 'inline'
              }
            });
          } else if(confirm(prompt)) {
            $compatible_pm.trigger('click');
            $input.trigger('click');
            return;
          }
        } else {
          alert(MeprSignup.no_compatible_pms);
        }

        return false;
      }

      $to_hide.closest('.mepr_payment_method, .mepr-payment-option-label').hide();
      $to_show.closest('.mepr_payment_method, .mepr-payment-option-label').show();

      $order_bump.toggleClass('mepr-order-bump-selected', this.checked);
    });

    // Hide subscription order bumps if no payment method supports multiple subscriptions
    $('.mepr-signup-form').each(function () {
      var $form = $(this),
          $order_bumps = $form.find('.mepr-order-bumps'),
          pm_supports_multiple_subs = false;

      if(!$order_bumps.length) {
        return;
      }

      $form.find('div[class^=mepr-payment-method] input.mepr-form-radio, input[type="hidden"][name="mepr_payment_method"]').each(function (index) {
        var $pm = $(this);

        if($pm.hasClass('mepr-can-multiple-subscriptions') && pm_supports_multiple_subs === false) {
          pm_supports_multiple_subs = index;
        }
      });

      if(pm_supports_multiple_subs === false) {
        $order_bumps.find('.mepr-order-bump.mepr-sub').hide();

        if(!$order_bumps.find('.mepr-order-bump:visible').length) {
          $order_bumps.hide();
        }
      } else if(pm_supports_multiple_subs > 0) {
        var $new_first_pm = $form.find('div[class^=mepr-payment-method] input.mepr-form-radio').eq(pm_supports_multiple_subs);

        $new_first_pm
          .closest('.mepr_payment_method, .mepr-payment-option-label')
          .insertBefore(
            $form.find('div[class^=mepr-payment-method] input.mepr-form-radio')
              .eq(0)
              .closest('.mepr_payment_method, .mepr-payment-option-label')
          );

        $new_first_pm.trigger('click');
      }

      // If gift checkbox is checked, hide and disable order bumps
      if($form.find('input[name="mpgft-signup-gift-checkbox"]').is(':checked')) {
        $order_bumps.hide().find('input[name="mepr_order_bumps[]"]').prop('checked', false);
      }
    });

    $("body").on("change",
      '.mepr-form input[name="mepr_order_bumps[]"]',
      function (e) {
        let form = $(this).closest(".mepr-signup-form");
        if( $('input[type="hidden"][name="mepr_process_signup_form"]').length ) {
          if( $('input[type="hidden"][name="mepr_process_signup_form"]').val() == 'Y' ) {
            return;
          }
        }
        meprUpdatePriceTerms(form);
      }
    );

  });
})(jQuery);
