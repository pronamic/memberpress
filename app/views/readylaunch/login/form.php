<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
$show_welcome_image = isset($atts['show_welcome_image']) ? $atts['show_welcome_image'] : $mepr_options->design_show_login_welcome_image;
$welcome_image      = isset($atts['welcome_image']) ? $atts['welcome_image'] : wp_get_attachment_url($mepr_options->design_login_welcome_img);
$admin_view         = isset($atts['admin_view']) ? $atts['admin_view'] : false;
?>

<div id="mepro-login-hero" class="<?php echo $show_welcome_image ? 'with-sidebar' : ''; ?>">
  <div class="mepro-boxed">
    <div class="mepro-login-contents">


      <?php
        if (!empty($_REQUEST['mepr_process_login_form']) && !empty($_REQUEST['errors'])) {
            $errors = array_map('wp_kses_post', $_REQUEST['errors']);
            MeprView::render('/readylaunch/shared/errors', get_defined_vars());
        }


        if (isset($unauth->excerpt) && !empty($unauth->excerpt)) : ?>
        <div class="mepr-unauthorized-excerpt">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>

            <?php echo $unauth->excerpt; ?>
        </div>
        <?php endif; ?>
      <?php if (isset($unauth->message) && !empty($unauth->message)) : ?>
        <div class="mepr-unauthorized-message">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>

            <?php echo $unauth->message; ?>
        </div>
      <?php endif; ?>

      <div id="mepr-template-login" class="mp_wrapper mp_login_form">
        <?php if (MeprUtils::is_user_logged_in() && ! $admin_view) : ?>
            <?php if (!isset($_GET['mepr-unauth-page']) && (!isset($_GET['action']) || $_GET['action'] != 'mepr_unauthorized')) : ?>
                <?php if (is_page($login_page_id) && isset($redirect_to) && !empty($redirect_to)) : ?>
              <script type="text/javascript">
                window.location.href = "<?php echo urldecode($redirect_to); ?>";
              </script>
                <?php else : ?>
              <div class="mepr-already-logged-in">
                    <?php printf(
                        // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
                        _x('You\'re already logged in. %1$sLogout.%2$s', 'ui', 'memberpress'),
                        '<a href="' . wp_logout_url(urldecode($redirect_to)) . '">',
                        '</a>'
                    ); ?>
              </div>
                <?php endif; ?>
            <?php else : ?>
                <?php echo $message; ?>
            <?php endif; ?>

        <?php else : ?>
            <?php if (isset($_GET['action']) && $_GET['action'] == 'mepr_unauthorized') : ?>
            <div class="mepr-unauthorized-message">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                <line x1="12" y1="9" x2="12" y2="13" />
                <line x1="12" y1="17" x2="12.01" y2="17" />
              </svg>
              <div>
                <?php echo $message; ?>
              </div>
            </div>
            <?php else : ?>
                <?php echo $message; ?>
            <!-- mp-login-form-start -->
                <?php
                // DON'T GET RID OF THIS HTML COMMENT PLEASE IT'S USEFUL FOR SOME REGEX WE'RE DOING.
                ?>
            <?php endif ?>
            <?php MeprHooks::do_action('mepr-before-login-form', $atts ?? []); ?>
          <form name="mepr_loginform" id="mepr_loginform" class="mepro-form" action="<?php echo $login_url; ?>" method="post">

            <h1><?php echo _x('Login', 'ui', 'memberpress') ?></h1>


            <?php // Nonce not necessary on this form seeing as the user isn't logged in yet. ?>
            <div class="mp-form-row mepr_username">
              <div class="mp-form-label">
                <?php $uname_or_email_str = MeprHooks::apply_filters('mepr-login-uname-or-email-str', _x('Username or E-mail', 'ui', 'memberpress')); ?>
                <?php $uname_str = MeprHooks::apply_filters('mepr-login-uname-str', _x('Username', 'ui', 'memberpress')); ?>
                <label for="user_login" class="screen-reader-text"><?php echo ($mepr_options->username_is_email) ? $uname_or_email_str : $uname_str; ?></label>
              </div>
              <input type="text" name="log" placeholder="<?php _ex('Username (email)', 'ui', 'memberpress'); ?>" id="user_login" value="<?php echo (isset($_REQUEST['log']) ? esc_html(stripcslashes($_REQUEST['log'])) : ''); ?>" />
            </div>
            <div class="mp-form-row mepr_password">
              <div class="mp-form-label">
                <label for="user_pass" class="screen-reader-text"><?php _ex('Password', 'ui', 'memberpress'); ?></label>
                <div class="mp-hide-pw">
                  <input type="password" name="pwd" placeholder="<?php _ex('Password', 'ui', 'memberpress'); ?>" id="user_pass" value="" />
                  <button type="button" class="button link mp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e('Show password', 'memberpress'); ?>">
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                  </button>
                </div>
              </div>
            </div>
            <?php MeprHooks::do_action('mepr-login-form-before-submit'); ?>
            <div class="mp-form-row mepr_remember_me">
              <input name="rememberme" type="checkbox" id="rememberme" value="forever" <?php checked(isset($_REQUEST['rememberme'])); ?> />
              <label for="rememberme"><?php _ex('Remember Me', 'ui', 'memberpress'); ?></label>

            </div>
            <div class="mp-spacer">&nbsp;</div>
            <div class="submit">
              <input type="submit" name="wp-submit" id="wp-submit" class="button-primary mepr-share-button disabled" value="<?php _ex('Log In', 'ui', 'memberpress'); ?>" />
              <input type="hidden" name="redirect_to" value="<?php echo esc_html($redirect_to); ?>" />
              <input type="hidden" name="mepr_process_login_form" value="true" />
              <input type="hidden" name="mepr_is_login_page" value="<?php echo ($is_login_page) ? 'true' : 'false'; ?>" />
            </div>
          </form>
          <div class="mp-spacer">&nbsp;</div>
          <div class="mepr-login-actions">
            <?php _ex('Forgot Password?', 'ui', 'memberpress'); ?> <a href="<?php echo $forgot_password_url; ?>"><?php _ex('Click here', 'ui', 'memberpress'); ?></a>
          </div>
            <?php MeprHooks::do_action('mepr-login-form-after-submit', $atts ?? []); ?>
          <!-- mp-login-form-end -->
            <?php
            // DON'T GET RID OF THIS HTML COMMENT PLEASE IT'S USEFUL FOR SOME REGEX WE'RE DOING.
            ?>

        <?php endif; ?>
      </div>

    </div>
    <?php if ($show_welcome_image && $welcome_image) : ?>
      <figure class="mepro-login-hero-image">
        <img src="<?php echo esc_url($welcome_image); ?>" />
      </figure>
    <?php endif; ?>
  </div>
</div>
