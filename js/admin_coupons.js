(function($) {
  var handleMembershipSpecific = function(hard) {
    var _off = !hard ? 'hide' : 'remove';

    if (!$('#_mepr_coupons_is_membership_specific').is(":checked") || !$('select.mepr-coupon-products-select').val()) {
      $('.mepr-coupons-form .mepr-membership-specific-box')[_off]();
      return;
    }

    var templateContent = $('#tmpl-mepr-membership-specific-box').html();
    if (templateContent) {
      var memberships = $('select.mepr-coupon-products-select').val();
      $.each(memberships, function(index, membership) {
        // Check if the membership specific box already exists.
        if ($(`#mepr_membership_specific_box_${membership}`).length > 0) {
          $(`#mepr_membership_specific_box_${membership}`).show();
          return;
        }

        var template = templateContent.replaceAll('{{membership_id}}', membership)
          .replaceAll('{{membership_name}}', $('select.mepr-coupon-products-select option[value="' + membership + '"]').text());

        // Fill data.
        var membershipSpecific = MeprCoupon.membership_specific[membership] ?? false;
          template = template.replaceAll('{{discount_type}}', membershipSpecific.discount_type)
            .replaceAll('{{discount_amount}}', membershipSpecific.discount_amount ?? 0)
            .replaceAll('{{first_payment_discount_amount}}', membershipSpecific.first_payment_discount_amount ?? 0)
            .replaceAll('{{trial_days}}', membershipSpecific.trial_days ?? 0)
            .replaceAll('{{trial_amount}}', membershipSpecific.trial_amount ?? 0);

        // Append element.
        $('.mepr-coupons-form').append(template);

        // Set initial select values.
        if (membershipSpecific) {
          fields = [
            'discount_type',
            'discount_mode',
            'first_payment_discount_type',
          ]
          $.each(fields, function(index, field) {
            var select = $('select[name="_mepr_coupons_membership_specific[' + membership + '][_mepr_coupons_' + field + ']"]');
            select.find('option[value="' + membershipSpecific[field] + '"]').prop('selected', true);
          });
        }

        // Init toggle boxes.
        mepr_toggle_boxes(`#mepr_membership_specific_box_${membership}`);
      });

      // Hide all the boxes which are not in the memberships array.
      $('.mepr-coupons-form .mepr-membership-specific-box').each(function() {
        if (!memberships.includes($(this).attr('id').replace('mepr_membership_specific_box_', ''))) {
          $(this).hide();
        }
      });
    }
  }

  $(document).ready(function() {
    //Date expiration
    if($('.should-expire').is(":checked")) {
      $('.mepr-coupon-expires').show();
    } else {
      $('.mepr-coupon-expires').hide();
    }
    $('.should-expire').click(function() {
      $('.mepr-coupon-expires').slideToggle('fast');
    });

    //Validate before allowing post to be saved
    $('#publish').click(function() {
      if(!$('select.mepr-coupon-products-select').val()) {
        alert(MeprCoupon.l10n.mepr_no_products_message); //Alerts the user that they must create memberships before they can save coupons
        return false;
      }
      handleMembershipSpecific(true);
    });

    //trial period
    if($('#_mepr_coupons_trial').is(":checked")) {
      $('.mepr-coupons-trial-hidden').show();
    } else {
      $('.mepr-coupons-trial-hidden').hide();
    }
    $('#_mepr_coupons_trial').click(function() {
      $('.mepr-coupons-trial-hidden').slideToggle('fast');
    });

    handleMembershipSpecific();
    // Handle membership specific on relevant inputs changes.
    $('select.mepr-coupon-products-select, #_mepr_coupons_is_membership_specific')
      .change(function() {
        handleMembershipSpecific();
      });
  });
})(jQuery);
