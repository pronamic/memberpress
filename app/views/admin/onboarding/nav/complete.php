<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<?php
MeprView::render('/admin/onboarding/video', array(
  'youtube_video_id' => 'Oxv63nivZEw',
  'step' => '8',
));
?>
<div>
  <button type="button" id="mepr-wizard-finish-onboarding" class="mepr-wizard-button-blue"><span><?php esc_html_e('Finish', 'memberpress'); ?></span></button>
</div>
