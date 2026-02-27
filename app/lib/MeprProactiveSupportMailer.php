<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProactiveSupportMailer
{
    /**
     * Plain text body for the current email send.
     *
     * @var string
     */
    private static $plain_text_body = '';

    /**
     * Send a proactive support notification.
     *
     * @param string $trigger   Trigger key.
     * @param array  $recipient Recipient data.
     * @param array  $context   Context data.
     *
     * @return boolean
     */
    public static function send($trigger, array $recipient, array $context): bool
    {
        $template = MeprProactiveSupportHelper::template_for_trigger($trigger);
        $view     = "/emails/proactive-support/{$template}";

        $email = sanitize_email($recipient['email'] ?? '');
        if (empty($email)) {
            return false;
        }

        $user = isset($recipient['user']) && $recipient['user'] instanceof WP_User ? $recipient['user'] : null;
        $name = $recipient['name'] ?? ($user instanceof WP_User ? ($user->display_name ?: $user->user_login) : $email);

        $site_name = sanitize_text_field($context['site_name'] ?? get_bloginfo('name'));
        $site_url  = esc_url_raw($context['site_url'] ?? home_url());

        $vars = array_merge(
            $context,
            [
                'admin'        => $user,
                'admin_name'   => $name,
                'admin_email'  => $email,
                'site_name'    => $site_name,
                'site_url'     => $site_url,
                'settings_url' => admin_url('admin.php?page=memberpress-options'),
                'opt_out_url'  => MeprProactiveSupportHelper::add_tracking_to_url(
                    MeprProactiveSupportHelper::get_opt_out_url_for_email($email)
                ),
            ]
        );

        $body   = MeprView::get_string($view, $vars);
        $footer = MeprView::get_string('/emails/proactive-support/partials/footer', $vars);
        $body  .= $footer;
        if (empty($body)) {
            return false;
        }

        $subject = self::subject_for_trigger($trigger, $vars);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: MemberPress Support <support@memberpress.com>',
            'Reply-To: outreach@memberpress.com',
            'X-MemberPress-Trigger: ' . sanitize_key($trigger),
            'X-MemberPress-User-ID: ' . ($user instanceof WP_User ? $user->ID : 0),
            'X-MemberPress-Site-URL: ' . $site_url,
        ];

        self::$plain_text_body = self::build_plain_text_body($body);
        add_action('phpmailer_init', [__CLASS__, 'set_plain_text_alt_body'], 20);

        $sent = MeprUtils::wp_mail($email, $subject, $body, $headers);

        remove_action('phpmailer_init', [__CLASS__, 'set_plain_text_alt_body'], 20);
        self::$plain_text_body = '';

        return $sent;
    }

    /**
     * Generate subject line for trigger.
     *
     * @param string $trigger Trigger key.
     * @param array  $context Template context.
     *
     * @return string
     */
    private static function subject_for_trigger($trigger, array $context): string
    {
        $site_name = $context['site_name'] ?? get_bloginfo('name');
        $site_url  = $context['site_url'] ?? home_url();

        switch ($trigger) {
            case MeprProactiveSupportHelper::TRIGGER_FAILED_ONBOARDING:
                return sprintf(
                    // Translators: 1: site name, 2: site URL.
                    esc_html__('[Action Needed] Finish onboarding for %1$s (%2$s)', 'memberpress'),
                    $site_name,
                    $site_url
                );
            case MeprProactiveSupportHelper::TRIGGER_NO_MEMBERSHIPS:
                return sprintf(
                    // Translators: 1: site name, 2: site URL.
                    esc_html__('Create your first membership for %1$s (%2$s)', 'memberpress'),
                    $site_name,
                    $site_url
                );
            case MeprProactiveSupportHelper::TRIGGER_PAYMENT_GATEWAY_MISSING:
                return sprintf(
                    // Translators: 1: site name, 2: site URL.
                    esc_html__('Connect a gateway to start collecting payments on %1$s (%2$s)', 'memberpress'),
                    $site_name,
                    $site_url
                );
            case MeprProactiveSupportHelper::TRIGGER_NO_REGISTRATION_PAGES:
                return sprintf(
                    // Translators: 1: site name, 2: site URL.
                    esc_html__('Publish a pricing page for %1$s (%2$s)', 'memberpress'),
                    $site_name,
                    $site_url
                );
            case MeprProactiveSupportHelper::TRIGGER_NO_CONTENT_PROTECTION:
                return sprintf(
                    // Translators: 1: site name, 2: site URL.
                    esc_html__('Protect your content on %1$s (%2$s)', 'memberpress'),
                    $site_name,
                    $site_url
                );
            case MeprProactiveSupportHelper::TRIGGER_NO_TRANSACTIONS:
                return sprintf(
                    // Translators: 1: site name, 2: site URL.
                    esc_html__('Test a transaction for %1$s (%2$s)', 'memberpress'),
                    $site_name,
                    $site_url
                );
            case MeprProactiveSupportHelper::TRIGGER_INACTIVE_AFTER_SETUP:
                return sprintf(
                    // Translators: 1: site name, 2: site URL.
                    esc_html__('Check in on %1$s (%2$s)', 'memberpress'),
                    $site_name,
                    $site_url
                );
        }

        return sprintf(
            // Translators: 1: site name, 2: site URL.
            esc_html__('MemberPress proactive support for %1$s (%2$s)', 'memberpress'),
            $site_name,
            $site_url
        );
    }

    /**
     * Build a plain text version of the email body with URLs.
     *
     * @param string $html HTML email body.
     *
     * @return string
     */
    private static function build_plain_text_body($html): string
    {
        $html = (string) $html;

        $html = preg_replace_callback(
            '/<a\\s[^>]*href=["\\\']([^"\\\']+)["\\\'][^>]*>(.*?)<\\/a>/is',
            static function ($matches) {
                $url  = html_entity_decode($matches[1], ENT_QUOTES);
                $text = trim(wp_strip_all_tags($matches[2]));

                if ($text === '' || $text === $url) {
                    return $url;
                }

                return $text . ' (' . $url . ')';
            },
            $html
        );

        $text = MeprUtils::convert_to_plain_text($html);
        $text = preg_replace('/^[ \\t]+/m', '', $text);

        return $text;
    }

    /**
     * Set the plain text alternative body.
     *
     * @param PHPMailer $phpmailer PHPMailer instance.
     *
     * @return void
     */
    public static function set_plain_text_alt_body($phpmailer): void
    {
        if (empty(self::$plain_text_body)) {
            return;
        }

        $phpmailer->AltBody = self::$plain_text_body;
    }
}
