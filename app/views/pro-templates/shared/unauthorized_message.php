<?php


// Has welcome image? Priority given to shortcode.
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

  <?php if (!MeprUtils::is_user_logged_in()) : ?>
    <div id="mepr-template-login" class="mepr-login-form-wrap">
      <?php if ($show_login) : ?>
        <?php echo $form; ?>
        <?php // MeprView::render('/login/form', get_defined_vars());
        ?>
      <?php elseif (is_singular()) : // Let's not show the annoying login link on non singular pages
      ?>
        <span class="mepr-login-link"><a href="<?php echo $mepr_options->login_page_url(); ?>"><?php echo MeprHooks::apply_filters('mepr-unauthorized-login-link-text', _x('Login', 'ui', 'memberpress')); ?></a></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>