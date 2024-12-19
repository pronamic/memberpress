jQuery(document).ready(function ($) {
  const body = $('body');

  $('.mepr-open-resume-confirm, .mepr-open-cancel-confirm').magnificPopup({
    type: 'inline',
    closeBtnInside: false
  });

  body.on('click', '.mepr-confirm-no', function() {
    $.magnificPopup.close();
  });

  body.on('click', '.mepr-confirm-yes', function(){
    location.href = $(this).data('url');
  });

  $('.mepr-open-upgrade-popup').magnificPopup({
    type: 'inline',
    closeBtnInside: false
  });

  body.on('click', '.mepr-upgrade-cancel', function() {
    $.magnificPopup.close();
  });

  body.on('click', '.mepr-upgrade-buy-now', function(){
    var id = $(this).data('id');
    var selector = 'select#mepr-upgrade-dropdown-' + id;
    location.href = $(selector).val();
  });

  var meprValidateAccountInput = function (obj) {
    $(obj).removeClass('invalid');

    if ($(obj).attr('required') !== undefined) {
      var notBlank = mpValidateFieldNotBlank($(obj));
      mpToggleFieldValidation($(obj), notBlank);
    }

    // Validate actual email only if it's not empty otherwise let the required/un-required logic hold
    if ($(obj).attr('type')==='email' && $(obj).val().length > 0) {
      var validEmail = mpValidateEmail($(obj).val());
      mpToggleFieldValidation($(obj), validEmail);
    }
  };

  body.on('click', '.mepr-account-form .mepr-submit', function (e) {
    e.preventDefault();
    var form = $(this).closest('.mepr-account-form');
    var submittedTelInputs = document.querySelectorAll(".mepr-tel-input");
    for (var i = 0; i < submittedTelInputs.length; i++) {
      var iti = window.intlTelInputGlobals.getInstance(submittedTelInputs[i]);
      submittedTelInputs[i].value = iti.getNumber();
    }

    // Loop through each field and validate if it's required.
    $.each(form.find('.mepr-form-input:visible'), function(i,obj) {
      meprValidateAccountInput(obj);
    });

    // Validation failed? Bailout.
    if (0 < form.find('.invalid').length) {
      return;
    }

    form.submit();
  });

});
