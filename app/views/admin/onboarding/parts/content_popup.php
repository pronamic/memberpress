<h2><?php esc_html_e('Create Content', 'memberpress'); ?></h2>
<?php
$courses_plugin_active = is_plugin_active('memberpress-courses/main.php');
?>
  <div class="mepr-wizard-create-content-type" <?php echo ! $courses_plugin_active  ? ' style="display:none;"' : ''; ?>>
    <div>
      <input type="radio" id="mepr_wizard_create_content_type-course" name="mepr_wizard_create_content_type" value="course"<?php echo $courses_plugin_active  ? ' checked' : ''; ?>>
      <label for="mepr_wizard_create_content_type-course">
        <span><?php esc_html_e('Course', 'memberpress'); ?></span>
        <span><?php esc_html_e("Choose this if you'd like to create and protect a new online course.", 'memberpress'); ?></span>
      </label>
    </div>
    <div>
      <input type="radio" id="mepr_wizard_create_content_type-page" name="mepr_wizard_create_content_type" value="page"<?php echo !$courses_plugin_active  ? ' checked' : ''; ?>>
      <label for="mepr_wizard_create_content_type-page">
        <span><?php esc_html_e('Specific Page', 'memberpress'); ?></span>
        <span><?php esc_html_e("Choose this if you'd like to create and protect a single page of content.", 'memberpress'); ?></span>
      </label>
    </div>
  </div>
  <div id="mepr-wizard-create-content-course-fields" class="<?php echo ! $courses_plugin_active  ? 'mepr-hidden' : ''; ?>">
    <div class="mepr-wizard-popup-field">
      <label for="mepr-wizard-create-content-course-name"><?php esc_html_e('Course Name', 'memberpress'); ?></label>
      <input type="text" id="mepr-wizard-create-content-course-name" placeholder="<?php esc_attr_e('Write course name', 'memberpress'); ?>">
    </div>
  </div>
  <div id="mepr-wizard-create-content-page-fields" class="<?php echo $courses_plugin_active  ? 'mepr-hidden' : ''; ?>">
    <div class="mepr-wizard-popup-field">
      <label for="mepr-wizard-create-content-page-name"><?php esc_html_e('Page Title', 'memberpress'); ?></label>
      <input type="text" id="mepr-wizard-create-content-page-name" placeholder="<?php esc_attr_e('Write page title', 'memberpress'); ?>">
    </div>
  </div>
  <div class="mepr-wizard-popup-button-row">
    <button type="button" id="mepr-wizard-create-new-content-save" class="mepr-wizard-button-blue"><?php esc_html_e('Save', 'memberpress'); ?></button>
    <a target='_blank' class="mepr-wizard-popuphelp" href='<?php echo admin_url('edit.php?post_type=mpcs-course'); ?>' id="mepr-wizard-create-content-course-help" <?php echo ! $courses_plugin_active  ? ' style="display:none;"' : ''; ?>>
      <?php
        printf(
          /* translators: %1$s: open underline tag, %2$s: close underline tag */
          esc_html__('More advanced options are available in %1$sMemberPress > Courses%2$s', 'memberpress'),
          '<u>',
          '</u>'
        );
      ?>
    </a>
  </div>