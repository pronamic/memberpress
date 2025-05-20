<?php

class MeprDeactivationSurveyCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks.
     *
     * @return void
     */
    public function load_hooks()
    {
        if (apply_filters('mepr_deactivation_survey_skip', $this->is_dev_url())) {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_footer', [$this, 'popup']);
    }

    /**
     * Checks if the current URL is a development URL.
     *
     * @return boolean
     */
    protected function is_dev_url()
    {
        $url          = network_site_url('/');
        $is_local_url = false;

        // Trim it up.
        $url = strtolower(trim($url));

        // Need to get the host...so let's add the scheme so we can use parse_url.
        if (false === strpos($url, 'http://') && false === strpos($url, 'https://')) {
            $url = 'http://' . $url;
        }
        $url_parts = parse_url($url);
        $host      = ! empty($url_parts['host']) ? $url_parts['host'] : false;
        if (! empty($url) && ! empty($host)) {
            if (false !== ip2long($host)) {
                if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $is_local_url = true;
                }
            } elseif ('localhost' === $host) {
                $is_local_url = true;
            }

            $tlds_to_check = ['.dev', '.local', ':8888'];
            foreach ($tlds_to_check as $tld) {
                if (false !== strpos($host, $tld)) {
                    $is_local_url = true;
                    continue;
                }
            }
            if (substr_count($host, '.') > 1) {
                $subdomains_to_check =  ['dev.', '*.staging.', 'beta.', 'test.'];
                foreach ($subdomains_to_check as $subdomain) {
                    $subdomain = str_replace('.', '(.)', $subdomain);
                    $subdomain = str_replace(['*', '(.)'], '(.*)', $subdomain);
                    if (preg_match('/^(' . $subdomain . ')/', $host)) {
                        $is_local_url = true;
                        continue;
                    }
                }
            }
        }

        return $is_local_url;
    }

    /**
     * Enqueue scripts/styles.
     *
     * @return void
     */
    public function enqueue()
    {
        if (!$this->is_plugin_page()) {
            return;
        }

        wp_enqueue_style('mepr-deactivation-survey', MEPR_CSS_URL . '/admin-deactivation-survey.css', [], MEPR_VERSION);
        wp_enqueue_script('mepr-deactivation-survey', MEPR_JS_URL . '/admin_deactivation_survey.js', ['jquery'], MEPR_VERSION, true);

        wp_localize_script('mepr-deactivation-survey', 'MeprDeactivationSurvey', [
            'slug'                 => MEPR_PLUGIN_SLUG,
            'pleaseSelectAnOption' => __('Please select an option', 'memberpress'),
            'siteUrl'              => site_url(),
            'apiUrl'               => 'https://hooks.zapier.com/hooks/catch/43914/otu86c9/silent/',
        ]);
    }

    /**
     * Renders the deactivation survey popup.
     *
     * @return void
     */
    public function popup()
    {
        if (!$this->is_plugin_page()) {
            return;
        }

        $plugin = MEPR_PLUGIN_NAME;

        $options = [
            1 => [
                'label' => __('I no longer need the plugin', 'memberpress'),
            ],
            2 => [
                'label'   => __('I\'m switching to a different plugin', 'memberpress'),
                'details' => __('Please share which plugin', 'memberpress'),
            ],
            3 => [
                'label' => __('I couldn\'t get the plugin to work', 'memberpress'),
            ],
            4 => [
                'label' => __('It\'s a temporary deactivation', 'memberpress'),
            ],
            5 => [
                'label'   => __('Other', 'memberpress'),
                'details' => __('Please share the reason', 'memberpress'),
            ],
        ];

        MeprView::render('/admin/popups/deactivation_survey', compact('plugin', 'options'));
    }

    /**
     * Checks if the current page is a plugin page.
     *
     * @return boolean
     */
    protected function is_plugin_page()
    {
        return in_array(MeprUtils::get_current_screen_id(), ['plugins', 'plugins-network']);
    }
}
