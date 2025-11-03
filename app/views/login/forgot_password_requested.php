<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php
$reset_error = isset($_REQUEST['error']) ? sanitize_text_field(wp_unslash($_REQUEST['error'])) : '';

if (!empty($reset_error)) {
    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
    $errors[] = $reset_error;
    ?>
  <h3><?php echo esc_html_x('Password could not be reset.', 'ui', 'memberpress'); ?></h3>
    <?php MeprView::render('/shared/errors', get_defined_vars()); ?>
  <div><?php echo esc_html_x('Please contact us for further assistance.', 'ui', 'memberpress'); ?></div>
    <?php
} else {
    ?>
<div class="mp_wrapper mepr_password_reset_requested">
  <h3><?php echo esc_html_x('Successfully requested password reset', 'ui', 'memberpress'); ?></h3>
  <p><?php echo esc_html_x('If a matching account is found, you\'ll receive a password reset email soon. Click the link found in that email to reset your password.', 'ui', 'memberpress'); ?></p>
</div>
<?php } ?>
