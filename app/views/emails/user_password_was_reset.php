<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<p>
  <?php echo esc_html(empty($first_name) ? $username : $first_name); ?>,
  <br/>
  <?php echo sprintf(
    // Translators: %s: blog name.
      esc_html_x('Your password was successfully reset on %1$s!', 'ui', 'memberpress'),
      esc_html($mepr_blogname)
  ); ?>
</p>
<p>
  <?php echo sprintf(
    // Translators: %s: username.
      esc_html_x('Username: %1$s', 'ui', 'memberpress'),
      esc_html($username)
  ); ?>
  <br/>
  <?php echo esc_html_x('Password: *** Successfully Reset ***', 'ui', 'memberpress'); ?>
</p>
<p>
  <?php echo sprintf(
    // Translators: %s: login link.
      esc_html_x('You can now login here: %1$s', 'ui', 'memberpress'),
      esc_url($login_link)
  ); ?>
</p>
