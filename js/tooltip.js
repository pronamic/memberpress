(function($) {
  $(document).ready(function() {
    $('body').on('click', '.mepr-tooltip', function() {
      var tooltip_title = $(this).find('.mepr-data-title').html();
      var tooltip_info = $(this).find('.mepr-data-info').html();
      $(this).pointer({ 'content':  '<h3>' + tooltip_title + '</h3><p>' + tooltip_info + '</p>',
                        'position': {'edge':'left','align':'center'},
                        //'buttons': function() {
                        //  // intentionally left blank to eliminate 'dismiss' button
                        //}
                      })
      .pointer('open');
    });
  });
})(jQuery);
