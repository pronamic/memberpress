<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprGoogleCaptchaIntegration
{
    /**
     * Constructor for the MeprGoogleCaptchaIntegration class.
     */
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'load_hooks']);
    }

    /**
     * Load the necessary hooks for Google Captcha integration.
     *
     * @return void
     */
    public function load_hooks()
    {
        if (!function_exists('gglcptch_is_recaptcha_required')) {
            return;
        }

        add_filter('mepr-validate-signup', [$this, 'remove_authenticate_action']);
        add_filter('mepr-validate-login', [$this, 'remove_authenticate_action']);
        add_filter('mepr-validate-forgot-password', [$this, 'remove_allow_password_reset_action']);
        add_filter('mepr-validate-reset-password', [$this, 'remove_authenticate_action']);
        add_filter('gglcptch_is_recaptcha_required', [$this, 'disable_recaptcha_pro_checks'], 10, 2);
    }

    /**
     * Remove the authenticate action to prevent reCAPTCHA from being checked twice.
     *
     * @param  array $errors The array of errors.
     * @return array The modified array of errors.
     */
    public function remove_authenticate_action($errors)
    {
        remove_action('authenticate', 'gglcptch_login_check', 21);

        return $errors;
    }

    /**
     * Remove the allow password reset action to prevent reCAPTCHA from being checked twice.
     *
     * @param  array $errors The array of errors.
     * @return array The modified array of errors.
     */
    public function remove_allow_password_reset_action($errors)
    {
        // We need to remove this action or the reCAPTCHA is checked twice.
        remove_action('allow_password_reset', 'gglcptch_lostpassword_check');

        return $errors;
    }

    /**
     * Disable reCAPTCHA checks for specific forms.
     *
     * @param  boolean $result    The current result of the reCAPTCHA check.
     * @param  string  $form_slug The slug of the form being checked.
     * @return boolean The modified result of the reCAPTCHA check.
     */
    public function disable_recaptcha_pro_checks($result, $form_slug)
    {
        if (in_array($form_slug, ['memberpress_login', 'memberpress_forgot_password', 'memberpress_checkout'], true)) {
            $result = false;
        }

        return $result;
    }
}

new MeprGoogleCaptchaIntegration();
