(function($) {
  __ = wp.i18n.__;

  function mpResetToggle( $button, show ) {
    $button
      .attr({
        'aria-label': show ? __( 'Show password' ) : __( 'Hide password' )
      })
      .find( '.text' )
        .text( show ? __( 'Show' ) : __( 'Hide' ) )
      .end()
      .find( '.dashicons' )
        .removeClass( show ? 'dashicons-hidden' : 'dashicons-visibility' )
        .addClass( show ? 'dashicons-visibility' : 'dashicons-hidden' );
  }

  $(document).ready( function() {
    $('body').on('click', 'button.mp-hide-pw', function () {
      var $button = $(this),
        $pass = $button.prev();

      if ( 'password' === $pass.attr( 'type' ) ) {
        $pass.attr( 'type', 'text' );
        mpResetToggle( $button, false );
      } else {
        $pass.attr( 'type', 'password' );
        mpResetToggle( $button, true );
      }
    });

    // Pro Template
    if( $('#mepro-login-hero').length ){
      if( $('#mepr_loginform').length ){
        $("#user_login, #user_pass").on("input", function(){
          if ( $('#user_login').val().length > 0 && $('#user_pass').val().length > 0) {
            $('#wp-submit').removeClass('disabled');
          }
        })
      }
    }
    // End Pro Template
  });
})(jQuery);
