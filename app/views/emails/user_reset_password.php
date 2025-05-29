<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<html>
  <body>
    <p><?php echo sprintf(
      // Translators: %1$s: user login name, %2$s: blog name, %3$s: blog URL.
        _x('Someone requested to reset your password for %1$s on %2$s at %3$s', 'ui', 'memberpress'),
        $locals['user_login'],
        $locals['mepr_blogname'],
        $locals['mepr_blogurl']
    ); ?></p>
    <p><?php echo _x('To reset your password visit the following address, otherwise just ignore this email and nothing will happen.', 'ui', 'memberpress'); ?></p>
    <p><a href="<?php echo esc_url($locals['reset_password_link']); ?>"><?php echo esc_url($locals['reset_password_link']); ?></a></p>
  </body>
</html>
