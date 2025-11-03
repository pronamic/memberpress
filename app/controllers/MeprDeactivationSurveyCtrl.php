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
        if (MeprHooks::apply_filters('mepr_deactivation_survey_skip', $this->is_dev_url())) {
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
        return MeprUtils::is_dev_url();
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
        return in_array(MeprUtils::get_current_screen_id(), ['plugins', 'plugins-network'], true);
    }
}
