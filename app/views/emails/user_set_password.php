<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<html>
  <body>
    <p><?php echo sprintf(
        // Translators: %1$s: User first name.
        _x('Hi %1$s,', 'ui', 'memberpress'),
        $locals['first_name']
    ); ?></p>
    <p><?php echo sprintf(
        // Translators: %1$s: Username, %2$s: Blog name, %3$s: Blog URL.
        _x('You can create a new password for %1$s on %2$s at %3$s by clicking on the following link:', 'ui', 'memberpress'),
        $locals['user_login'],
        $locals['mepr_blogname'],
        $locals['mepr_blogurl']
    ); ?></p>
    <p><a href="<?php echo esc_url($locals['reset_password_link']); ?>"><?php echo esc_url($locals['reset_password_link']); ?></a></p>
  </body>
</html>
