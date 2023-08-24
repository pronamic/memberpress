(function ($) {
  $(document).ready(function () {

    $('input[type=radio]').each(function (element) {
      $(this).parent('label').removeClass('checked');
      if ($(this).is(':checked')) {
        $(this).parent('label').addClass('checked');
      }
    })

    $('.mepr-form-radio').on('click', function () {
      $('.mepr-form-radio').each(function (element) {
        $(this).parent('label').removeClass('checked');
      })
      $(this).parent('label').addClass('checked');
    })
  });
})(jQuery);