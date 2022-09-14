<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<?php
$youtube_video_hash = md5($youtube_video_id);
?>

<div class="mepr-wizard-onboarding-video-wrapper mepr-wizard-onboarding-video-<?php echo esc_attr($step); ?>" id="wrapper_<?php echo $youtube_video_hash; ?>" >
   <div  class="mepr-wizard-onboarding-video-expand" id="expand_<?php echo $youtube_video_hash; ?>" data-id="<?php echo $youtube_video_hash; ?>">
  <img src="<?php echo MEPR_IMAGES_URL . '/onboarding/expand.png'; ?>" class="mepr-animation-shaking" />
</div>
  <div class="mepr-video-wrapper" id="inner_<?php echo $youtube_video_hash; ?>">

    <div class='mepr-video-holder' id="holder_<?php echo $youtube_video_hash; ?>">
         <a href='#' class='mepr-video-play-button' id="mepr_play_<?php echo $youtube_video_hash; ?>" data-hash="<?php echo $youtube_video_hash; ?>"  data-holder-id="holder_<?php echo $youtube_video_hash; ?>" data-id='<?php echo esc_attr($youtube_video_id); ?>'></a>
    </div>
  </div>
  <div class="mepr-wizard-onboarding-video-collapse" data-id="<?php echo $youtube_video_hash; ?>" data-id="<?php echo $youtube_video_hash; ?>">
    <img src="<?php echo MEPR_IMAGES_URL . '/onboarding/collapse.png'; ?>" />
  </div>
</div>