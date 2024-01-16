<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprGoogleCaptchaIntegration {
  public function __construct() {
    add_action('plugins_loaded', array($this, 'load_hooks'));
  }

  public function load_hooks() {
    if(!function_exists('gglcptch_is_recaptcha_required')) {
      return;
    }

    add_filter('mepr-validate-signup', array($this, 'remove_authenticate_action'));
    add_filter('mepr-validate-login', array($this, 'remove_authenticate_action'));
    add_filter('mepr-validate-forgot-password', array($this, 'remove_allow_password_reset_action'));
    add_filter('mepr-validate-reset-password', array($this, 'remove_authenticate_action'));
    add_filter('gglcptch_is_recaptcha_required', array($this, 'disable_recaptcha_pro_checks'), 10, 2);
  }

  public function remove_authenticate_action($errors) {
    remove_action('authenticate', 'gglcptch_login_check', 21);

    return $errors;
  }

  public function remove_allow_password_reset_action($errors) {
    // We need to remove this action or the reCAPTCHA is checked twice
    remove_action('allow_password_reset', 'gglcptch_lostpassword_check');

    return $errors;
  }

  public function disable_recaptcha_pro_checks($result, $form_slug) {
    if(in_array($form_slug, array('memberpress_login', 'memberpress_forgot_password', 'memberpress_checkout'), true)) {
      $result = false;
    }

    return $result;
  }
}

new MeprGoogleCaptchaIntegration;
