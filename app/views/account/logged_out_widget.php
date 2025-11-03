<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<ul id="mepr-logged-out-widget">
  <li><a href="<?php echo esc_url($login_url); ?>"><?php echo esc_html_x('Login', 'ui', 'memberpress'); ?></a></li>
</ul>
