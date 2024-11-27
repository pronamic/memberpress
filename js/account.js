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

  body.on('click', '.mepr-account-form .mepr-submit', function (e) {
    e.preventDefault();
    var form = $(this).closest('.mepr-account-form');
    var submittedTelInputs = document.querySelectorAll(".mepr-tel-input");
    for (var i = 0; i < submittedTelInputs.length; i++) {
      var iti = window.intlTelInputGlobals.getInstance(submittedTelInputs[i]);
      submittedTelInputs[i].value = iti.getNumber();
    }
    form.submit();
  });

});
