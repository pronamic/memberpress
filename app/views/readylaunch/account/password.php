<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div class="mp_wrapper">
  <div id="mepro-login-hero">
    <?php MeprView::render('/readylaunch/shared/errors', get_defined_vars()); ?>

    <div class="mepro-boxed" style="margin-top: 2rem">
      <div class="mepro-login-contents">
        <form action="<?php echo esc_url(wp_parse_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? '')), PHP_URL_PATH)); ?>" class="mepr-newpassword-form mepr-form" method="post" novalidate>
          <input type="hidden" name="plugin" value="mepr" />
          <input type="hidden" name="action" value="updatepassword" />
          <?php wp_nonce_field('update_password', 'mepr_account_nonce'); ?>

          <div class="mp-form-row mepr_new_password">
            <label for="mepr-new-password"><?php echo esc_html_x('New Password', 'ui', 'memberpress'); ?></label>
            <div class="mp-hide-pw">
              <input type="password" name="mepr-new-password" id="mepr-new-password" class="mepr-form-input mepr-new-password" required />
              <button type="button" class="button mp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e('Show password', 'memberpress'); ?>">
                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <div class="mp-form-row mepr_confirm_password">
            <label for="mepr-confirm-password"><?php echo esc_html_x('Confirm New Password', 'ui', 'memberpress'); ?></label>
            <div class="mp-hide-pw">
              <input type="password" name="mepr-confirm-password" id="mepr-confirm-password" class="mepr-form-input mepr-new-password-confirm" required />
              <button type="button" class="button mp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e('Show password', 'memberpress'); ?>">
                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <?php MeprHooks::do_action('mepr_account_after_password_fields', $mepr_current_user); ?>

          <div class="mepr_spacer">&nbsp;</div>

          <input type="submit" name="new-password-submit" value="<?php echo esc_attr_x('Update Password', 'ui', 'memberpress'); ?>" class="mepr-submit" />
          <?php echo esc_html_x('or', 'ui', 'memberpress'); ?>
          <a href="<?php echo esc_url($mepr_options->account_page_url()); ?>"><?php echo esc_html_x('Cancel', 'ui', 'memberpress'); ?></a>
          <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php echo esc_attr_x('Loading...', 'ui', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />
          <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>
        </form>

      </div>
    </div>
  </div>

  <?php MeprHooks::do_action('mepr_account_password', $mepr_current_user); ?>
</div>
