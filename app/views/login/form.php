<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div class="mp_wrapper mp_login_form">
  <?php if (MeprUtils::is_user_logged_in()) : ?>
        <?php if (!isset($_GET['mepr-unauth-page']) && (!isset($_GET['action']) || $_GET['action'] !== 'mepr_unauthorized')) : ?>
            <?php if (is_page($login_page_id) && isset($redirect_to) && !empty($redirect_to)) : ?>
        <script type="text/javascript">
          window.location.href="<?php echo esc_js(urldecode($redirect_to)); ?>";
        </script>
            <?php else : ?>
        <div class="mepr-already-logged-in">
                <?php printf(
                    // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                    esc_html_x('You\'re already logged in. %1$sLogout.%2$s', 'ui', 'memberpress'),
                    '<a href="' . esc_url(wp_logout_url(urldecode($redirect_to))) . '">',
                    '</a>'
                ); ?>
        </div>
            <?php endif; ?>
        <?php else : ?>
            <?php echo wp_kses_post($message); ?>
        <?php endif; ?>

  <?php else : ?>
      <?php echo wp_kses_post($message); ?>
      <?php MeprHooks::do_action('mepr_before_login_form', $atts ?? []); ?>
    <!-- mp-login-form-start --> <?php // DON'T GET RID OF THIS HTML COMMENT PLEASE IT'S USEFUL FOR SOME REGEX WE'RE DOING. ?>
    <form name="mepr_loginform" id="mepr_loginform" class="mepr-form" action="<?php echo esc_url($login_url); ?>" method="post">
      <?php // Nonce not necessary on this form seeing as the user isn't logged in yet. ?>
      <div class="mp-form-row mepr_username">
        <div class="mp-form-label">
          <?php $uname_or_email_str = MeprHooks::apply_filters('mepr_login_uname_or_email_str', _x('Username or E-mail', 'ui', 'memberpress')); ?>
          <?php $uname_str = MeprHooks::apply_filters('mepr_login_uname_str', _x('Username', 'ui', 'memberpress')); ?>
          <label for="user_login"><?php echo esc_html($mepr_options->username_is_email ? $uname_or_email_str : $uname_str); ?></label>
        </div>
        <input type="text" name="log" id="user_login" value="<?php echo (isset($_REQUEST['log']) ? esc_attr(sanitize_text_field(wp_unslash($_REQUEST['log']))) : ''); ?>" />
      </div>
      <div class="mp-form-row mepr_password">
        <div class="mp-form-label">
          <label for="user_pass"><?php echo esc_html_x('Password', 'ui', 'memberpress'); ?></label>
          <div class="mp-hide-pw">
            <input type="password" name="pwd" id="user_pass" value="" />
            <button type="button" class="button mp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e('Show password', 'memberpress'); ?>">
              <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
            </button>
          </div>
        </div>
      </div>
      <?php MeprHooks::do_action('mepr_login_form_before_submit'); ?>
      <div>
        <label><input name="rememberme" type="checkbox" id="rememberme" value="forever"<?php checked(isset($_REQUEST['rememberme'])); ?> /> <?php echo esc_html_x('Remember Me', 'ui', 'memberpress'); ?></label>
      </div>
      <div class="mp-spacer">&nbsp;</div>
      <div class="submit">
        <input type="submit" name="wp-submit" id="wp-submit" class="button-primary mepr-share-button " value="<?php echo esc_attr_x('Log In', 'ui', 'memberpress'); ?>" />
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
        <input type="hidden" name="mepr_process_login_form" value="true" />
        <input type="hidden" name="mepr_is_login_page" value="<?php echo ($is_login_page) ? 'true' : 'false'; ?>" />
      </div>
    </form>
    <div class="mp-spacer">&nbsp;</div>
    <div class="mepr-login-actions">
        <a
          href="<?php echo esc_url($forgot_password_url); ?>"
          title="<?php echo esc_attr_x('Click here to reset your password', 'ui', 'memberpress'); ?>"
        >
          <?php echo esc_html_x('Forgot Password', 'ui', 'memberpress'); ?>
        </a>
    </div>

      <?php MeprHooks::do_action('mepr_login_form_after_submit', $atts ?? []); ?>

    <!-- mp-login-form-end --> <?php // DON'T GET RID OF THIS HTML COMMENT PLEASE IT'S USEFUL FOR SOME REGEX WE'RE DOING. ?>

  <?php endif; ?>
</div>
