<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<?php $mepr_options = MeprOptions::fetch(); ?>
<div class="mp_wrapper alignwide">
  <p style="text-align: center;"><?php printf(
      // Translators: %s: Reset password URL.
      _x('The link you clicked has expired, please attempt to %s.', 'ui', 'memberpress'),
      '<a href="' . $mepr_options->forgot_password_url() . '">' . _x('reset your password again', 'ui', 'memberpress') . '</a>'
  ); ?></p>
</div>