<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

class MeprMathCaptchaCtrl extends MeprBaseCtrl
{
    /**
     * Loads the hooks.
     */
    public function load_hooks(): void
    {
        add_action('after_setup_theme', [$this, 'enable']);
    }

    /**
     * Enables the math captcha functionality.
     *
     * This method is called after the theme setup, so that we can safely fetch the options.
     */
    public function enable(): void
    {
        $options = MeprOptions::fetch();

        if (is_plugin_active('memberpress-math-captcha/main.php')) {
            $options->enable_math_captcha = true;
            $options->store(false);

            deactivate_plugins('memberpress-math-captcha/main.php', true);
        }

        if ($options->enable_math_captcha) {
            add_action('mepr-checkout-before-coupon-field', [$this, 'render_field'], 12); // Higher priority to ensure it shows up below the strength meter.
            add_action('mepr-forgot-password-form', [$this, 'render_field']);
            add_action('mepr-login-form-before-submit', [$this, 'render_field']);
            add_filter('mepr-validate-signup', [$this, 'validate_answer']);
            add_filter('mepr-validate-forgot-password', [$this, 'validate_answer']);
            add_filter('mepr-validate-login', [$this, 'validate_answer']);
        }
    }

    /**
     * Generates, stores and returns a unique key.
     *
     * This key is used to create HMACs for the math captcha data.
     */
    public function get_unique_key(): string
    {
        $key = get_option('mepr_math_captcha_key', false);

        if (!$key || 64 !== strlen($key)) {
            // Generate a cryptographically secure random key.
            $key = bin2hex(random_bytes(32));
            update_option('mepr_math_captcha_key', $key);
        }

        return $key;
    }

    /**
     * Creates an HMAC-SHA256 hash of the answer for secure verification.
     *
     * @param integer $answer The answer to secure, which is the sum of two random numbers.
     *
     * @return string Base64 encoded HMAC-SHA256 of the answer with the unique key.
     */
    public function hash_data(int $answer): string
    {
        $key = $this->get_unique_key();

        // Use HMAC for secure message authentication.
        $hmac = hash_hmac('sha256', (string) $answer, $key, true);

        return base64_encode($hmac);
    }

    /**
     * Verifies if the provided answer matches the stored HMAC hash.
     *
     * @param integer $answer The user's answer to the math challenge.
     * @param string  $data   The stored data (Base64 encoded HMAC).
     *
     * @return boolean True if the answer matches the stored data, false otherwise.
     */
    public function verify_data(int $answer, string $data): bool
    {
        $expected = $this->hash_data($answer);

        // Use hash_equals to prevent timing attacks.
        return hash_equals($expected, $data);
    }

    /**
     * Generates a random number based on the specified size.
     *
     * @param string $size The size of the random number to generate ('small', 'medium', 'large'). Default is 'small'.
     *
     * @return integer The generated random number.
     */
    public function generate_random_number(string $size = 'small'): int
    {
        if ('large' === $size) {
            return random_int(16, 30);
        }

        if ('medium' === $size) {
            return random_int(6, 15);
        }

        return random_int(1, 5);
    }

    /**
     * Displays the math captcha field in the forms.
     */
    public function render_field(): void
    {
        if (MeprUtils::is_user_logged_in()) {
            return;
        }

        $num1 = $this->generate_random_number('medium');
        $num2 = $this->generate_random_number('small');
        $data = $this->hash_data($num1 + $num2);

        MeprView::render('shared/math_captcha', compact('num1', 'num2', 'data'));
    }

    /**
     * Validates the math captcha answer.
     *
     * @param array $errors The array of errors.
     *
     * @return array The updated array of errors, if any.
     */
    public function validate_answer(array $errors): array
    {
        if (MeprUtils::is_user_logged_in()) {
            return $errors;
        }

        if (empty($_POST['mepr_math_quiz'])) {
            $errors[] = __('You must fill out the Math Quiz correctly.', 'memberpress');
            return $errors;
        }

        if (empty($_POST['mepr_math_data'])) {
            $errors[] = __('You must fill out the Math Quiz correctly.', 'memberpress');
            return $errors;
        }

        $answer = (int) $_POST['mepr_math_quiz'];
        $data   = sanitize_text_field(wp_unslash($_POST['mepr_math_data']));

        if (!$this->verify_data($answer, $data)) {
            $errors[] = __('You must fill out the Math Quiz correctly.', 'memberpress');
        }

        return $errors;
    }
}
