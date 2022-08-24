<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<?php
MeprView::render('/admin/onboarding/video', array(
  'youtube_video_id' => 'M3ISOBtHXdA',
  'step' => '3',
));
?>
<div id="mepr-wizard-content-nav-skip">
  <button type="button" class="mepr-wizard-button-link mepr-wizard-go-to-step" data-step="6" data-context="skip"><span><?php esc_html_e('Skip', 'memberpress'); ?></span></button>
</div>
<div id="mepr-wizard-content-nav-continue" class="mepr-hidden">
  <button type="button" class="mepr-wizard-button-blue mepr-wizard-go-to-step" data-step="4" data-context="continue"><?php esc_html_e('Continue', 'memberpress'); ?></button>
</div>
