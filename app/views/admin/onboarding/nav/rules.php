<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<?php
MeprView::render('/admin/onboarding/video', array(
  'youtube_video_id' => 'H7gjIpm9byw',
  'step' => '5',
));
?>
<div id="mepr-wizard-rule-nav-skip">
  <button type="button" class="mepr-wizard-button-link mepr-wizard-go-to-step" data-step="6" data-context="skip"><span><?php esc_html_e('Skip', 'memberpress'); ?></span></button>
</div>
<div id="mepr-wizard-rule-nav-continue" class="mepr-hidden">
  <button type="button" class="mepr-wizard-button-blue mepr-wizard-go-to-step" data-step="6" data-context="continue"><?php esc_html_e('Continue', 'memberpress'); ?></button>
</div>