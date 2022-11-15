<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<?php $mepr_options = MeprOptions::fetch(); ?>
<p><?php printf(_x('The link you clicked has expired, please attempt to %s.', 'ui', 'memberpress'), "<a href=\"" . $mepr_options->forgot_password_url() . "\">" . _x('reset your password again', 'ui', 'memberpress') . "</a>"); ?></p>
