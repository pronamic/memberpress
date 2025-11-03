<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<ul id="mepr-logged-in-widget">
  <li><a href="<?php echo esc_url($account_url); ?>"><?php echo esc_html_x('Account', 'ui', 'memberpress'); ?></a></li>
  <li><a href="<?php echo esc_url($logout_url); ?>"><?php echo esc_html_x('Logout', 'ui', 'memberpress'); ?></a></li>
</ul>
