<?php

/**
 * Has welcome image? Priority given to shortcode.
 */

if (isset($atts['show_welcome_image'])) {
    $has_welcome_image = filter_var($atts['show_welcome_image'], FILTER_VALIDATE_BOOLEAN);
} elseif (isset($mepr_options->design_login_welcome_img)) {
    $has_welcome_image = $mepr_options->design_login_welcome_img;
}

// Get welcome image? Priority given to shortcode.
if (isset($atts['welcome_image']) && $atts['welcome_image'] > 0) {
    $welcome_image = wp_get_attachment_url($atts['welcome_image']);
} elseif (isset($mepr_options->design_login_welcome_img)) {
    $welcome_image = wp_get_attachment_url($mepr_options->design_login_welcome_img);
}
?>

<div class="mp_wrapper alignwide" style="margin-top: 2em">

  <?php if (!empty($unauth->excerpt)) : ?>
    <div class="mepr-unauthorized-excerpt">
      <div class="mepr_pro_error" id="mepr_jump"  style="justify-content: center; padding: 2rem 0;">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          <line x1="12" y1="9" x2="12" y2="13" />
          <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
        <div class="mepr_pro_error_content">
          <?php echo $unauth->excerpt; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
  <?php if (!empty($unauth->message)) : ?>
    <div class="mepr-unauthorized-excerpt">
      <div class="mepr_pro_error" id="mepr_jump"  style="justify-content: center; padding: 2rem 0;">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          <line x1="12" y1="9" x2="12" y2="13" />
          <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
        <div class="mepr_pro_error_content">
          <?php echo $unauth->message; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!MeprUtils::is_user_logged_in()) : ?>
    <div id="mepr-template-login" class="mepr-login-form-wrap">
        <?php if ($show_login) : ?>
            <?php echo $form; ?>
        <?php elseif (is_singular()) : // Let's not show the annoying login link on non singular pages.
            ?>
        <span class="mepr-login-link"><a href="<?php echo $mepr_options->login_page_url(); ?>"><?php echo MeprHooks::apply_filters('mepr-unauthorized-login-link-text', _x('Login', 'ui', 'memberpress')); ?></a></span>
        <?php endif; ?>
    </div>
  <?php endif; ?>
</div>