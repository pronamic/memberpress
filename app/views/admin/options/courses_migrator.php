<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div class="page" id="migrator">
  <div class="page-title"><?php esc_html_e('Migrate to MemberPress Courses', 'memberpress'); ?></div>
  <?php
    $migrators = MeprMigratorHelper::get_usable_course_migrators();
    if ($migrators) : ?>
    <div class="mepr-migrators mepr-migrator-boxes">
          <?php if (in_array(MeprMigratorLearnDash::KEY, $migrators, true)) : ?>
        <div class="mepr-migrator mepr-migrator-box mepr-migrator-learndash">
          <h3><?php esc_html_e('LearnDash', 'memberpress'); ?></h3>
          <p class="mepr-migrator-description"><?php esc_html_e('Migrate courses, lessons and quizzes from LearnDash.', 'memberpress'); ?></p>
          <p class="mepr-migrator-settings"><label for="mepr-migrator-learndash-user-progress"><input type="checkbox" id="mepr-migrator-learndash-user-progress" checked> <?php esc_html_e('Migrate user progress and quiz attempts', 'memberpress'); ?></label></p>
          <p>
            <button type="button" id="mepr-migrator-learndash-start" class="button button-primary mepr-migrator-start"><?php esc_html_e('Start Migration', 'memberpress'); ?></button>
            <span id="mepr-migrator-learndash-please-wait" class="mepr-hidden"><?php esc_html_e('Please stay on this page', 'memberpress'); ?></span>
          </p>
        </div>
          <?php endif; ?>
    </div>
    <?php else : ?>
    <p><?php esc_html_e('No compatible migrations found.', 'memberpress'); ?></p>
    <?php endif;?>
</div>
