<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="mepr-wizard-create-rule">
  <h2 class="mepr-wizard-step-title"><?php esc_html_e("Let's create a Rule to protect your content", 'memberpress'); ?></h2>
  <p class="mepr-wizard-step-description"><?php esc_html_e('Rules are the magic behind MemberPress – giving you super specialized paywalling power with the click of a button.', 'memberpress'); ?></p>
  <p class="mepr-wizard-step-description"><?php esc_html_e("You can use Rules to protect pages, child pages, posts, custom post types, categories, tags... almost anything you can imagine. Here, we'll set your first Rule.", 'memberpress'); ?></p>
    <div class="mepr-wizard-button-group">
        <button type="button" id="mepr-wizard-create-new-rule" class="mepr-wizard-button-secondary"><?php esc_html_e('Create Rule', 'memberpress'); ?></button>
    </div>
 </div>


<div id="mepr-wizard-selected-rule" class="mepr-hidden">
  <h2 class="mepr-wizard-step-title"><?php esc_html_e('Your rules', 'memberpress'); ?></h2>
  <div class="mepr-wizard-selected-content mepr-wizard-selected-content-full-scape">
    <div class="mepr-wizard-selected-content-column">
      <div class="mepr-wizard-selected-content-heading" id="mepr-wizard-selected-content-heading"><?php esc_html_e('Course / Page','memberpress'); ?></div>
      <div class="mepr-wizard-selected-content-name" id="mepr-selected-rule-content-name"></div>
    </div>
    <hr>
    <div class="mepr-wizard-selected-content-column">
      <div class="mepr-wizard-selected-content-heading"><?php esc_html_e('Membership Name','memberpress'); ?></div>
      <div class="mepr-wizard-selected-content-name"  id="mepr-selected-rule-membership-name"></div>
    </div>
    <div class="mepr-wizard-selected-content-expand-menu" data-id="mepr-wizard-selected-rule-menu">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/expand-menu.svg'); ?>" alt="">
    </div>
    <div id="mepr-wizard-selected-rule-menu" class="mepr-wizard-selected-content-menu mepr-hidden">
      <div id="mepr-wizard-selected-rule-delete"><?php esc_html_e('Remove', 'memberpress'); ?></div>
    </div>
  </div>
</div>

<div id="mepr-wizard-create-new-rule-popup" class="mepr-wizard-popup mfp-hide">
  <h2><?php esc_html_e('Create Rule', 'memberpress'); ?></h2>
  <?php
  $rules_data = MeprOnboardingHelper::get_rules_step_data();
  ?>
  <div class="mepr-wizard-popup-field">
    <label for="mepr-wizard-create-rule-content"><?php echo esc_attr($rules_data['content_type']); ?></label>
    <input type="text" id="mepr-wizard-create-rule-content" value="<?php echo esc_attr($rules_data['content_title']); ?>" readonly />
  </div>

  <div class="mepr-wizard-popup-field">
    <label for="mepr-wizard-create-rule-membershipname"><?php esc_html_e('Membership Name', 'memberpress'); ?></label>
    <input type="text" id="mepr-wizard-create-rule-membershipname" value="<?php echo esc_attr($rules_data['membership_title']); ?>" readonly/>
  </div>

  <div class="mepr-wizard-popup-button-row">
    <button type="button" id="mepr-wizard-create-new-rule-save" class="mepr-wizard-button-blue"><?php esc_html_e('Save', 'memberpress'); ?></button>
    <a target='_blank' class="mepr-wizard-popuphelp" href='<?php echo admin_url('edit.php?post_type=memberpressrule'); ?>' id="mepr-wizard-create-content-course-help">
      <?php
        printf(
          /* translators: %1$s: open underline tag, %2$s: close underline tag */
          esc_html__('More advanced options are available in %1$sMemberPress > Rules%2$s', 'memberpress'),
          '<u>',
          '</u>'
        );
      ?>
    </a>
  </div>
</div>
