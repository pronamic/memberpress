<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<p>
  <!-- translators: In this string, %s is the user's username -->
  <?php
    printf(
      // Translators: %s: user's username.
        esc_html__('Password Lost and Changed for user %s', 'memberpress'),
        esc_html($username)
    ); ?>
</p>
