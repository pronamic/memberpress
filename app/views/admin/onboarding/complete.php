<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<h2 class="mepr-wizard-finished"><?php esc_html_e('Congrats! You’re done.', 'memberpress'); ?></h2>
<div id="mepr-wizard-completed">
  <h2 class="mepr-wizard-step-title"><?php esc_html_e('Check out what you set up...', 'memberpress'); ?></h2>
  <div class="mepr-wizard-selected-content mepr-wizard-selected-content-full-scape">
    <div id="mepr-wizard-completed-step-urls"><?php echo MeprOnboardingHelper::get_completed_step_urls_html(); ?></div>
  </div>

  <h2 class="mepr-wizard-step-title"><?php esc_html_e('Your next step...', 'memberpress'); ?></h2>
  <div class="mepr-wizard-selected-content mepr-wizard-selected-content-full-scape">
    <div class="mepr-wizard-selected-content-column">
      <a href="https://memberpress.com/addons/memberpress-courses/">
        <div class="mepr-wizard-selected-content-image-box">
          <div class="mepr-wizard-selected-content-image-thumbnail">
            <img src="<?php echo MEPR_URL; ?>/images/onboarding/getting-started-with-memberpress-courses.jpg" alt="<?php esc_html_e('Getting Started with MemberPress Courses','memberpress'); ?>" />
          </div>
          <div class="mepr-wizard-selected-content-image-description">
             <a href="https://memberpress.com/addons/memberpress-courses/" target="_blank">
              <h4 class="mepr-image-title"><?php esc_html_e('Getting Started with MemberPress Courses','memberpress'); ?></h4>
              <p class="mepr-image-desc"><?php esc_html_e('MemberPress Courses is included with your subscription. Learn how you can start selling what you know today!','memberpress'); ?></p>
            </a>
          </div>
        </div>
    </div>
  </div>

  <h2 class="mepr-wizard-step-title"><?php esc_html_e('Make the most of your MemberPress site...', 'memberpress'); ?></h2>
  <div class="mepr-wizard-selected-content mepr-wizard-selected-content-full-scape">
    <div class="mepr-wizard-selected-content-column">
        <div class="mepr-wizard-selected-content-image-box">
          <div class="mepr-wizard-selected-content-image-thumbnail">
            <img src="<?php echo MEPR_URL; ?>/images/onboarding/memberpress-blog-screenshot.png" alt="<?php esc_html_e('MemberPress Blog','memberpress'); ?>" />
          </div>
          <div class="mepr-wizard-selected-content-image-description">
            <a href="https://memberpress.com/blog/" target="_blank">
              <h4 class="mepr-image-title"><?php esc_html_e('MemberPress Blog','memberpress'); ?></h4>
              <p class="mepr-image-desc"><?php esc_html_e('Sign up for tips, tricks, and industry updates from top membership, LMS, and online business experts and influencers.','memberpress'); ?></p>
            </a>
          </div>
        </div>
    </div>
  </div>
</div>
