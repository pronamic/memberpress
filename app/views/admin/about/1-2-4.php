<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<h3><?php _e('Welcome to MemberPress vs 1.2.4', 'memberpress'); ?></h3>
<p><?php _e('This release includes the following awesome features and fixes:', 'memberpress'); ?></p>
<p>
&nbsp;&bullet;&nbsp;<?php _e('Autoresponder integrations have been moved into Add-ons ...', 'memberpress'); ?><br/>
&nbsp;&bullet;&nbsp;<?php _e('We\'ve added 4 additional auto-responder integration Add-ons', 'memberpress'); ?><br/>
&nbsp;&bullet;&nbsp;<?php _e('We\'ve launched our new MemberPress Developer Tools Add-on', 'memberpress'); ?><br/>
&nbsp;&bullet;&nbsp;<?php _e('We\'ve added an add-on auto-installation page to MemberPress', 'memberpress'); ?><br/>
&nbsp;&bullet;&nbsp;<?php _e('We\'ve added a Spanish translation', 'memberpress'); ?><br/>
&nbsp;&bullet;&nbsp;<?php _e('And much more ...', 'memberpress'); ?>
</p>
<p><?php printf(
    // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag, %3$s: opening anchor tag, %4$s: closing anchor tag.
    __('For a full list of features you can visit our %1$schangelog%2$s or %3$sread the blog post about 1.2.4%2$s.', 'memberpress'),
    '<a href="https://memberpress.com/change-log/">',
    '</a>',
    '<a href="https://memberpress.com/1.2.4">'
); ?></p>

