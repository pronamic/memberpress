<div id="mepro-login-hero">
  <div class="mepro-boxed">
  <div class="mepro-login-contents">
    <p><?php printf(
        // Translators: %s: login page URL.
        _x('You\'re unauthorized to view this page. Why don\'t you %s and try again.', 'ui', 'memberpress'),
        '<a href="' . $mepr_options->login_page_url() . '">' . _x('Login', 'ui', 'memberpress') . '</a>'
    ); ?></p>
  </div>
  </div>
</div>
