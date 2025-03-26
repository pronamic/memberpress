<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<?php
  $ld_migration_possible = MeprMigratorLearnDash::is_migration_possible();
  $courses_active        = MeprOnboardingHelper::is_courses_addon_applicable();
?>
<?php if ($ld_migration_possible) : ?>
  <div id="mepr-wizard-ld-migrator-success" class="mepr-hidden">
    <div class="mepr-wizard-success-notice">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/green-check.svg'); ?>" alt="">
      <span><?php esc_html_e('Migration complete! Migrated courses can be protected using the link below.', 'memberpress'); ?></span>
    </div>
  </div>
  <div id="mepr-wizard-ld-migrator" class="mepr-migrator<?php echo !$courses_active ? ' mepr-hidden' : ''; ?>">
    <h2 class="mepr-wizard-step-title"><?php esc_html_e('Migrate from LearnDash?', 'memberpress'); ?></h2>
    <p class="mepr-wizard-step-description"><?php esc_html_e("We'd usually ask you to select some content here, but we noticed that you have some LearnDash Courses that could be migrated to MemberPress Courses automatically. Would you like to migrate your existing course content to MemberPress Courses?", 'memberpress'); ?></p>
    <p class="mepr-migrator-settings mepr-form">
      <label class="switch">
        <input type="checkbox" id="mepr-migrator-learndash-user-progress" checked>
        <span class="slider round"></span>
      </label>
      <label for="mepr-migrator-learndash-user-progress">
        <?php esc_html_e('Migrate user progress and quiz attempts', 'memberpress'); ?>
      </label>
    </p>
    <div class="mepr-wizard-button-group">
      <button type="button" id="mepr-migrator-learndash-start" class="mepr-wizard-button-blue"><?php esc_html_e('Start Migration', 'memberpress'); ?></button>
      <button type="button" id="mepr-wizard-cancel-ld-migrator" class="mepr-wizard-button-link"><span><?php esc_html_e("No Thanks, I'll Choose Other Content", 'memberpress'); ?></span></button>
      <span id="mepr-migrator-learndash-please-wait" class="mepr-hidden"><?php esc_html_e('Please stay on this page until the migration completes', 'memberpress'); ?></span>
    </div>
  </div>
<?php endif; ?>

<div id="mepr-wizard-create-select-content"<?php echo $ld_migration_possible && $courses_active ? ' class="mepr-hidden"' : ''; ?>>
  <h2 class="mepr-wizard-step-title"><?php esc_html_e("Now let's get some content to protect", 'memberpress'); ?></h2>
  <p class="mepr-wizard-step-description"><?php esc_html_e("Here, you can create new content to protect. Or you can choose existing content on your site that you'd like to protect.", 'memberpress'); ?></p>
  <div class="mepr-wizard-button-group">
    <button type="button" id="mepr-wizard-create-new-content" class="mepr-wizard-button-blue"><?php esc_html_e('Create New Content', 'memberpress'); ?></button>
    <button type="button" id="mepr-wizard-choose-content" class="mepr-wizard-button-link"><span><?php esc_html_e('Choose Existing Content', 'memberpress'); ?></span></button>
  </div>
</div>

<div id="mepr-wizard-selected-content" class="mepr-hidden">
  <h2 class="mepr-wizard-step-title"><?php esc_html_e('Your Content', 'memberpress'); ?></h2>
  <div class="mepr-wizard-selected-content">
    <div>
      <div class="mepr-wizard-selected-content-heading"></div>
      <div class="mepr-wizard-selected-content-name"></div>
    </div>
    <div>
      <div class="mepr-wizard-selected-content-expand-menu" data-id="mepr-wizard-selected-content-menu">
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/expand-menu.svg'); ?>" alt="">
      </div>
      <div id="mepr-wizard-selected-content-menu" class="mepr-wizard-selected-content-menu mepr-hidden">
        <div id="mepr-wizard-selected-content-delete"><?php esc_html_e('Remove', 'memberpress'); ?></div>
      </div>
    </div>
  </div>
</div>
<div id="mepr-wizard-create-new-content-popup" class="mepr-wizard-popup mfp-hide"></div>

<div id="mepr-wizard-choose-content-popup" class="mepr-wizard-popup mfp-hide">
  <h2><?php esc_html_e('Choose Existing Content', 'memberpress'); ?></h2>
  <div class="mepr-wizard-popup-field">
    <input type="text" id="mepr-wizard-choose-content-search" placeholder="<?php esc_attr_e('Search...', 'memberpress'); ?>">
  </div>
  <div id="mepr-wizard-choose-content-results">
    <?php echo MeprOnboardingCtrl::get_content_search_results_html(); ?>
  </div>
  <div class="mepr-wizard-popup-button-row">
    <button type="button" id="mepr-wizard-choose-content-save" class="mepr-wizard-button-blue"><?php esc_html_e('Save', 'memberpress'); ?></button>
  </div>
</div>
