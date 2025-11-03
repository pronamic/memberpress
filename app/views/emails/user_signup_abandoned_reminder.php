<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom:4px;"><?php echo esc_html_x('Please complete your signup', 'ui', 'memberpress'); ?></h1>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div id="receipt">
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(
        // Translators: %s: user first name.
        esc_html_x('Hi %s,', 'ui', 'memberpress'),
        '{$user_first_name}'
    ); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(
        // Translators: %1$s: product name, %2$s: blog name.
        esc_html_x('We just saw that you weren\'t able to complete your signup for %1$s on %2$s.', 'ui', 'memberpress'),
        '{$product_name}',
        '{$blog_name}'
    ); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(
        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
        esc_html_x('We\'d be really sad if you missed out so we just wanted to drop you a line to let you to know that it\'s easy to <strong>%1$scomplete your signup today%2$s</strong>.', 'ui', 'memberpress'),
        '<a href="{$subscr_renew_url}">',
        '</a>'
    ); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php echo esc_html_x('The {$blog_name} Team', 'ui', 'memberpress'); ?></div>
  </div>
</div>

