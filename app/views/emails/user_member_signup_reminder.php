<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom:4px;">Thanks for signing up</h1>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div id="receipt">
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(
      // Translators: %s: user first name.
        esc_html_x('Hi %s,', 'ui', 'memberpress'),
        '{$user_first_name}'
    ); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(
        // Translators: %s: product name.
        esc_html_x('Thanks for registering for %s!', 'ui', 'memberpress'),
        '{$product_name}'
    ); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php echo esc_html_x('Cheers!', 'ui', 'memberpress'); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(
        // Translators: %s: blog name.
        esc_html_x('The %s Team', 'ui', 'memberpress'),
        '{$blog_name}'
    ); ?></div>
  </div>
</div>

