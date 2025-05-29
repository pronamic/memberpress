<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php $mepr_options = MeprOptions::fetch(); ?>
<p><?php printf(
    // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
    _x('The link you clicked has expired, please attempt to %1$sreset your password again%2$s.', 'ui', 'memberpress'),
    '<a href="' . $mepr_options->forgot_password_url() . '">',
    '</a>'
); ?></p>
