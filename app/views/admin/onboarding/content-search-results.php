<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<?php if (count($posts)) : ?>
  <div id="mepr-wizard-choose-content-posts">
    <?php foreach ($posts as $content_post) : ?>
      <div class="mepr-wizard-choose-content-post" data-post="<?php echo esc_attr(wp_json_encode($content_post)); ?>">
        <input type="radio" id="mepr_wizard_choose_content_post-<?php echo esc_attr($content_post->ID); ?>" name="mepr_wizard_choose_content_post" value="<?php echo esc_attr($content_post->ID); ?>">
        <label for="mepr_wizard_choose_content_post-<?php echo esc_attr($content_post->ID); ?>">
          <img class="mepr-wizard-content-checked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/radio-checked.svg'); ?>" alt="">
          <img class="mepr-wizard-content-unchecked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/radio-unchecked.svg'); ?>" alt="">
          <span class="mepr-wizard-choose-content-name"><?php echo esc_html($content_post->post_title); ?></span>
          <span class="mepr-wizard-choose-content-type mepr-wizard-choose-content-type-<?php echo esc_attr($content_post->post_type); ?>"><?php $content_post->post_type === 'mpcs-course' ? esc_html_e('Course', 'memberpress') : esc_html_e('Specific Page', 'memberpress'); ?></span>
        </label>
      </div>
    <?php endforeach; ?>
  </div>
<?php else : ?>
  <p class="mepr-wizard-content-no-results"><?php esc_attr_e('No content found.', 'memberpress'); ?></p>
<?php endif; ?>
