<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div id="mepro-login-hero">
  <div class="mepro-boxed">
    <div class="mepro-login-contents">
      <?php
        $reset_error = isset($_REQUEST['error']) ? $_REQUEST['error'] : '';

        if (!empty($reset_error)) {
            $errors[] = $reset_error;
            ?>
        <h3><?php _ex('Password could not be reset.', 'ui', 'memberpress'); ?></h3>
            <?php MeprView::render('/readylaunch/shared/errors', get_defined_vars()); ?>
        <div><?php _ex('Please contact us for further assistance.', 'ui', 'memberpress'); ?></div>
            <?php
        } else {
            ?>
        <div class="mp_wrapper mepr_password_reset_requested">
          <h3><?php _ex('Successfully requested password reset', 'ui', 'memberpress'); ?></h3>
          <p><?php _ex('If a matching account is found, you\'ll receive a password reset email soon. Click the link found in that email to reset your password.', 'ui', 'memberpress'); ?></p>
        </div>
        <?php } ?>


    </div>
  </div>
</div>