<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom:4px;">{$reminder_name}</h1>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div id="receipt">
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(
        // Translators: %1$s: User first name.
        esc_html_x('Hi %1$s,', 'ui', 'memberpress'),
        '{$user_first_name}'
    ); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(
        // Translators: %1$s: reminder description, %2$s: expiration date.
        esc_html_x('Just a friendly reminder that your %1$s on <strong>%2$s</strong>.', 'ui', 'memberpress'),
        '{$reminder_description}',
        '{$subscr_expires_at}'
    ); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(
        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
        esc_html_x('If this isn\'t correct you can update your %1$saccount%2$s.', 'ui', 'memberpress'),
        '<a href="{$account_url}">',
        '</a>'
    ); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php echo esc_html_x('Cheers!', 'ui', 'memberpress'); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php echo esc_html_x('The {$blog_name} Team', 'ui', 'memberpress'); ?></div>
  </div>
</div>
