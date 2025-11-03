<div id="mepro-login-hero">
  <div class="mepro-boxed">
  <div class="mepro-login-contents">
    <p><?php printf(
        // Translators: %s: login page URL.
        esc_html_x('You\'re unauthorized to view this page. Why don\'t you %s and try again.', 'ui', 'memberpress'),
        '<a href="' . esc_url($mepr_options->login_page_url()) . '">' . esc_html_x('Login', 'ui', 'memberpress') . '</a>'
    ); ?></p>
  </div>
  </div>
</div>
