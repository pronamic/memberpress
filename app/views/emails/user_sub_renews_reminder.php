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
        _x('Hi %1$s,', 'ui', 'memberpress'),
        '{$user_first_name}'
    ); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(
        // Translators: %1$s: Reminder description, %2$s: Subscription expiration date.
        _x('Just a friendly reminder that your %1$s on <strong>%2$s</strong>.', 'ui', 'memberpress'),
        '{$reminder_description}',
        '{$subscr_expires_at}'
    ); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(
        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
        _x('If this isn\'t correct you can update your %1$saccount%2$s.', 'ui', 'memberpress'),
        '<a href="{$account_url}">',
        '</a>'
    ); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php _ex('Cheers!', 'ui', 'memberpress'); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php _ex('The {$blog_name} Team', 'ui', 'memberpress'); ?></div>
  </div>
</div>

