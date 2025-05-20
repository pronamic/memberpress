<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

abstract class MeprBaseEmail
{
    // It's a requirement for base classes to define these.
    /**
     * The email title.
     *
     * @var string
     */
    public $title;
    /**
     * The email description.
     *
     * @var string
     */
    public $description;
    /**
     * The default email variables.
     *
     * @var array
     */
    public $defaults;
    /**
     * The email variables.
     *
     * @var array
     */
    public $variables;
    /**
     * The list of email recipients.
     *
     * @var array
     */
    public $to;
    /**
     * The email headers.
     *
     * @var array
     */
    public $headers;
    /**
     * Whether to show the email form.
     *
     * @var boolean
     */
    public $show_form;
    /**
     * The UI order.
     *
     * @var integer
     */
    public $ui_order;
    /**
     * The test vars.
     *
     * @var array
     */
    public $test_vars;

    /**
     * Constructor for the MeprBaseEmail class.
     *
     * @param array $args Optional arguments to initialize the email.
     */
    public function __construct($args = [])
    {
        $this->headers   = [];
        $this->defaults  = [];
        $this->variables = [];
        $this->test_vars = [];

        $this->set_defaults($args);
    }

    /**
     * Set the default enabled, title, subject, body & other variables.
     *
     * @param array $args Optional arguments to set defaults.
     *
     * @return void
     */
    abstract public function set_defaults($args = []);

    /**
     * Checks if the email is enabled.
     *
     * @return boolean True if enabled, false otherwise.
     */
    public function enabled()
    {
        return ($this->get_stored_field('enabled') != false);
    }

    /**
     * Checks if the email uses a template.
     *
     * @return boolean True if using a template, false otherwise.
     */
    public function use_template()
    {
        return ($this->get_stored_field('use_template') != false);
    }

    /**
     * Retrieves the email headers.
     *
     * @return array The email headers.
     */
    public function headers()
    {
        return $this->headers;
    }

    /**
     * Retrieves the email subject.
     *
     * @return string The email subject.
     */
    public function subject()
    {
        return $this->get_stored_field('subject');
    }

    /**
     * Retrieves the email body.
     *
     * @return string The email body.
     */
    public function body()
    {
        return $this->get_stored_field('body');
    }

    /**
     * Retrieves the default email subject.
     *
     * @return string The default email subject.
     */
    public function default_subject()
    {
        return $this->defaults['subject'];
    }

    /**
     * Retrieves the default email body.
     *
     * @return string The default email body.
     */
    public function default_body()
    {
        return $this->defaults['body'];
    }

    /**
     * Formats the email subject with given values.
     *
     * @param array  $values  The values to replace in the subject.
     * @param string $subject Optional subject to format.
     *
     * @return string The formatted subject.
     */
    public function formatted_subject($values = [], $subject = false)
    {
        if ($subject) {
            return $this->replace_variables($subject, $values);
        } else {
            return $this->replace_variables($this->subject(), $values);
        }
    }

    /**
     * Formats the email body with given values.
     *
     * @param array   $values       The values to replace in the body.
     * @param string  $type         The content type (e.g., 'html').
     * @param string  $body         Optional body to format.
     * @param boolean $use_template Whether to use a template.
     *
     * @return string The formatted body.
     */
    public function formatted_body($values = [], $type = 'html', $body = false, $use_template = null)
    {
        if ($body) {
            $body = $this->replace_variables($body, $values);
        } else {
            $body = $this->replace_variables($this->body(), $values);
        }

        $body .= $this->footer();

        if (is_null($use_template)) {
            $use_template = $this->use_template();
        }

        if ($type == 'html' && $use_template) {
            return MeprView::get_string('/emails/template', get_defined_vars());
        }

        if ($type == 'html') {
            return $body;
        }

        return MeprUtils::convert_to_plain_text($body);
    }

    /**
     * Sends the email with the given parameters.
     *
     * @param array   $values       The values to replace in the email.
     * @param string  $subject      Optional subject for the email.
     * @param string  $body         Optional body for the email.
     * @param boolean $use_template Whether to use a template.
     * @param string  $content_type The content type (e.g., 'html').
     *
     * @return void
     * @throws MeprEmailToException When no email recipient has been set.
     */
    public function send($values = [], $subject = false, $body = false, $use_template = null, $content_type = 'html')
    {
        // Used to filter parameters to be searched and replaced in the email subject & body.
        $values      = MeprHooks::apply_filters('mepr_email_send_params', $values, $this, $subject, $body);
        $body        = MeprHooks::apply_filters('mepr_email_send_body', $body, $this, $subject, $values);
        $subject     = MeprHooks::apply_filters('mepr_email_send_subject', $subject, $this, $body, $values);
        $attachments = MeprHooks::apply_filters('mepr_email_send_attachments', [], $this, $body, $values);

        $bkg_enabled =  MeprHooks::apply_filters('mepr_bkg_email_jobs_enabled', get_option('mp-bkg-email-jobs-enabled'));

        if (!$bkg_enabled || ( defined('DOING_CRON') && DOING_CRON )) {
            if (!isset($this->to) or empty($this->to)) {
                throw new MeprEmailToException(__('No email recipient has been set.', 'memberpress'));
            }

            add_action('phpmailer_init', [$this, 'mailer_init']);

            if ($content_type == 'html') {
                add_filter('wp_mail_content_type', [$this,'set_html_content_type']);
            }

            MeprUtils::wp_mail(
                $this->to,
                $this->formatted_subject($values, $subject),
                $this->formatted_body($values, $content_type, $body, $use_template),
                $this->headers(),
                $attachments
            );

            if ($content_type == 'html') {
                  remove_filter('wp_mail_content_type', [$this,'set_html_content_type']);
            }

            remove_action('phpmailer_init', [$this, 'mailer_init']);
            do_action('mepr_email_sent', $this, $values, $attachments);
        } else {
            $job               = new MeprEmailJob();
            $job->values       = $values;
            $job->subject      = $subject;
            $job->body         = $body;
            $job->class        = get_class($this);
            $job->to           = $this->to;
            $job->headers      = $this->headers;
            $job->use_template = $use_template;
            $job->content_type = $content_type;
            $job->enqueue();
        }
    }

    /**
     * Sets the HTML content type for the email.
     *
     * @param string $content_type The content type (default: 'text/html').
     *
     * @return string The content type.
     */
    public function set_html_content_type($content_type = 'text/html')
    {
        // UTF-8 is breaking internal WP checks
        // return 'text/html;charset="UTF-8"';.
        return 'text/html';
    }

    /**
     * Initializes the PHPMailer object for email sending.
     *
     * @param PHPMailer $phpmailer The PHPMailer object.
     *
     * @return void
     */
    public function mailer_init($phpmailer)
    {
        // Plain text
        // Decode body.
        $phpmailer->AltBody = wp_specialchars_decode($phpmailer->Body, ENT_QUOTES);
        $phpmailer->AltBody = MeprUtils::convert_to_plain_text($phpmailer->AltBody);

        // Replace variables in email.
        $phpmailer->AltBody = MeprHooks::apply_filters('mepr-email-plaintext-body', $phpmailer->AltBody);

        if ($phpmailer->ContentType == 'text/html') {
            // HTML
            // Replace variables in email.
            $phpmailer->Body = MeprHooks::apply_filters('mepr-email-html-body', $phpmailer->Body);
        }
    }

    /**
     * Sends the email if it is enabled.
     *
     * @param array  $values       The values to replace in the email.
     * @param string $content_type The content type (e.g., 'html').
     *
     * @return void
     */
    public function send_if_enabled($values = [], $content_type = 'html')
    {
        if ($this->enabled()) {
            $this->send($values, false, false, null, $content_type);
        }
    }

    /**
     * Displays the email form.
     *
     * @return void
     */
    public function display_form()
    {
        $email = $this;
        MeprView::render('/admin/emails/options', get_defined_vars());
    }

    /**
     * Retrieves the dashed name of the email class.
     *
     * @return string The dashed name.
     */
    public function dashed_name()
    {
        $classname = get_class($this);
        $tag       = preg_replace('/\B([A-Z])/', '-$1', $classname);
        return strtolower($tag);
    }

    /**
     * Retrieves the view name of the email class.
     *
     * @return string The view name.
     */
    public function view_name()
    {
        $classname = get_class($this);
        $view      = preg_replace('/^Mepr(.*)Email$/', '$1', $classname);
        $view      = preg_replace('/\B([A-Z])/', '_$1', $view);
        return strtolower($view);
    }

    /**
     * Replaces variables in the given text with values.
     *
     * @param string $text   The text to replace variables in.
     * @param array  $values The values to replace.
     *
     * @return string The text with replaced variables.
     */
    public function replace_variables($text, $values)
    {
        return MeprUtils::replace_vals($text, $values);
    }

    /**
     * Retrieves the body partial for the email.
     *
     * @param array $vars The variables to pass to the view.
     *
     * @return string The body partial.
     */
    public function body_partial($vars = [])
    {
        return MeprView::get_string('/emails/' . $this->view_name(), $vars);
    }

    /**
     * Retrieves the footer content for the email.
     *
     * @return string The footer content.
     */
    private function footer()
    {
        $links     = $this->footer_links();
        $links_str = join('&#124;', $links);
        ob_start();
        ?>
      <div id="footer" style="width: 680px; padding: 0px; margin: 0 auto; text-align: center;">
        <?php echo $links_str; ?>
      </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Retrieves the footer links for the email.
     *
     * @return array The footer links.
     */
    private function footer_links()
    {
        $mepr_options = MeprOptions::fetch();
        $links        = [];

        if ($mepr_options->include_email_privacy_link) {
            $privacy_policy_page_link = MeprAppHelper::privacy_policy_page_link();
            if ($privacy_policy_page_link !== false) {
                $links[] = '<a href="' . $privacy_policy_page_link . '">' . __('Privacy Policy', 'memberpress') . '</a>';
            }
        }

        return $links;
    }

    /**
     * Retrieves the field name for the email.
     *
     * @param string  $field The field name (default: 'enabled').
     * @param boolean $id    Optional ID for the field.
     *
     * @return string The field name.
     */
    abstract public function field_name($field = 'enabled', $id = false);

    /**
     * Retrieves the stored field value for the email.
     *
     * @param string $fieldname The field name.
     *
     * @return mixed The stored field value.
     */
    abstract public function get_stored_field($fieldname);
}
