(function($) {
  $(document).ready(function() {
    //Is the box already checked?
    if($("#mepr-associate-affiliate-enable").is(':checked')) {
      $('div#mepr-affiliate-search').show();
    }

    $('#mepr-associate-affiliate-enable').click(function() {
      $('div#mepr-affiliate-search').slideToggle();
    });

    //Suggest users
    $('.mepr_suggest_user').suggest(
      ajaxurl+'?action=mepr_user_search',
      {
        delay: 500,
        minchars: 2
      }
    );
  });
})(jQuery);
