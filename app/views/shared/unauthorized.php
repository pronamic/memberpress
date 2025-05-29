<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php $mepr_options = MeprOptions::fetch(); ?>
<p><?php printf(
    // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
    _x('You\'re unauthorized to view this page. Why don\'t you %1$s and try again%2$s.', 'ui', 'memberpress'),
    '<a href="' . $mepr_options->login_page_url() . '">',
    '</a>'
); ?></p>
