<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

abstract class MeprBaseDrm
{
    /**
     * Constructor for the MeprBaseDrm class.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * The DRM event object.
     *
     * @var object|null
     */
    protected $event      = null;

    /**
     * The name of the DRM event.
     *
     * @var string
     */
    protected $event_name = '';

    /**
     * The current DRM status.
     *
     * @var string
     */
    protected $drm_status = '';

    /**
     * Checks if the DRM status is locked.
     *
     * @return boolean True if locked, false otherwise.
     */
    public function is_locked()
    {
        return MeprDrmHelper::is_locked($this->drm_status);
    }

    /**
     * Checks if the DRM status is medium.
     *
     * @return boolean True if medium, false otherwise.
     */
    public function is_medium()
    {
        return MeprDrmHelper::is_medium($this->drm_status);
    }

    /**
     * Checks if the DRM status is low.
     *
     * @return boolean True if low, false otherwise.
     */
    public function is_low()
    {
        return MeprDrmHelper::is_low($this->drm_status);
    }

    /**
     * Initializes the DRM status.
     *
     * @return void
     */
    protected function init()
    {
        $this->drm_status = '';
    }

    /**
     * Sets the DRM status.
     *
     * @param string $status The DRM status to set.
     *
     * @return void
     */
    protected function set_status($status)
    {
        $this->drm_status = $status;

        // Set global value.
        MeprDrmHelper::set_status($status);
    }

    /**
     * Creates a DRM event.
     *
     * @return void
     */
    public function create_event()
    {
        $drm  = new MeprDrm(1);
        $data = [
            'id' => 1,
        ];

        MeprEvent::record($this->event_name, $drm, $data);
    }

    /**
     * Updates a DRM event with new data.
     *
     * @param object $event The event to update.
     * @param mixed  $data  The data to update the event with.
     *
     * @return mixed The result of the update operation.
     */
    protected function update_event($event, $data)
    {
        if ($event->rec->id == 0) {
            return;
        }

        if (is_array($data) || is_object($data)) {
            $event->args = json_encode($data);
        }
        return $event->store();
    }

    /**
     * Runs the site health DRM check.
     *
     * @return array The result of the site health DRM check.
     */
    public function run_site_health_drm()
    {

        $drm_status = MeprDrmHelper::get_status();

        $vars = MeprDrmHelper::get_info($drm_status, $this->event_name, 'site_health');
        extract($vars);

        $result = [
            'label'       => $heading,
            'status'      => 'critical',
            'badge'       => [
                'label' => $label,
                'color' => $color,
            ],
            'description' => $message,
            'actions'     => sprintf(
                '<p><a href="%s" target="_blank" rel="noopener">%s <span class="screen-reader-text">%s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
                // Translators: Documentation explaining debugging in WordPress.
                esc_url($support_link),
                $help_message,
                // Translators: Accessibility text.
                __('(opens in a new tab)', 'memberpress')
            ),
            'test'        => 'run_site_health_drm',
        ];

        return $result;
    }

    /**
     * Creates a DRM event if none exists within the last 30 days.
     *
     * @return void
     */
    public function maybe_create_event()
    {

        // Check if wp_mepr_events has an entry within the last 30 days, if not, insert.
        $event = MeprEvent::latest_by_elapsed_days($this->event_name, 30);

        // Make sure we always have an event within the last 30 days.
        if (! $event) {
            $this->create_event();
        }
    }

    /**
     * Handles a DRM event.
     *
     * @param object  $event      The event to handle.
     * @param integer $days       The number of days since the event.
     * @param string  $drm_status The DRM status.
     *
     * @return void
     */
    public function drm_event($event, $days, $drm_status)
    {

        $this->event = $event;

        $event_data    = MeprDrmHelper::parse_event_args($event->args, true);
        $drm_event_key = MeprDrmHelper::get_status_key($drm_status);

        // Just make sure we run this once.
        if (! isset($event_data[ $drm_event_key ])) {
            // Send email.
            $this->send_email($drm_status);

            // Create in-plugin notification.
            $this->create_inplugin_notification($drm_status);

            // Mark event complete.
            $event_data[ $drm_event_key ] = MeprUtils::db_now();

            $this->update_event($event, $event_data);
        }

        add_action('admin_notices', [$this, 'admin_notices'], 11);
        add_filter('site_status_tests', [$this, 'site_health_status'], 11);

        if (MeprDrmHelper::is_locked()) {
            if ($this->is_mepr_page()) {
                add_action('admin_footer', [$this, 'admin_footer'], 20);
            }
            add_action('admin_body_class', [$this, 'admin_body_class'], 20);
        }
    }

    /**
     * Checks if the current page is a MemberPress page.
     *
     * @return boolean True if it is a MemberPress page, false otherwise.
     */
    private function is_mepr_page()
    {

        if (! isset($_GET['page'])) {
            return false;
        }

        if ($_GET['page'] == 'memberpress-members') {
            return true;
        }

        return false;
    }

    /**
     * Adds custom classes to the admin body tag.
     *
     * @param string $classes The current classes.
     *
     * @return string The modified classes.
     */
    public function admin_body_class($classes)
    {
        $classes .= ' mepr-locked';
        if ($this->is_mepr_page()) {
            $classes .= ' mepr-notice-modal-active';
        }
        return $classes;
    }

    /**
     * Outputs the admin footer content.
     *
     * @return void
     */
    public function admin_footer()
    {
        $view = MeprView::get_string('/admin/drm/modal');

        echo MeprHooks::apply_filters('mepr_drm_modal', $view);
    }

    /**
     * Displays admin notices related to DRM.
     *
     * @return void
     */
    public function admin_notices()
    {

        if (! $this->event || ! is_object($this->event)) {
            return;
        }

        if (MeprDrmHelper::is_locked() && $this->is_mepr_page()) {
            return;
        }

        $drm_status = MeprDrmHelper::get_status();

        if ('' !== $drm_status) {
            $drm_info                = MeprDrmHelper::get_info($drm_status, $this->event_name, 'admin_notices');
            $drm_info['notice_key']  = MeprDrmHelper::get_status_key($drm_status);
            $drm_info['notice_view'] = $drm_info['admin_notice_view'];
            $drm_info['event_name']  = $this->event_name;

            $notice_user_key = MeprDrmHelper::prepare_dismissable_notice_key($drm_info['notice_key']);
            $event_data      = MeprDrmHelper::parse_event_args($this->event->args);

            $is_dismissed = MeprDrmHelper::is_dismissed($event_data, $notice_user_key);
            if (! $is_dismissed) {
                echo'<style>.drm-mepr-activation-warning{display:none;}</style>';
                MeprView::render('/admin/drm/notices/notice', get_defined_vars());
            }
        }
    }

    /**
     * Sends an email notification based on DRM status.
     *
     * @param string $drm_status The DRM status.
     *
     * @return void
     */
    protected function send_email($drm_status)
    {
        $drm_info = MeprDrmHelper::get_info($drm_status, $this->event_name, 'email');
        if (empty($drm_info['heading'])) {
            return;
        }

        $subject = $drm_info['heading'];

        $message = MeprView::get_string('/admin/drm/email', get_defined_vars());

        $headers = [
            sprintf('Content-type: text/html; charset=%s', apply_filters('wp_mail_charset', get_bloginfo('charset'))),
        ];

        MeprUtils::wp_mail_to_admin($subject, $message, $headers);
    }

    /**
     * Creates an in-plugin notification based on DRM status.
     *
     * @param string $drm_status The DRM status.
     *
     * @return void
     */
    protected function create_inplugin_notification($drm_status)
    {

        $drm_info = MeprDrmHelper::get_info($drm_status, $this->event_name, 'inplugin');
        if (empty($drm_info['heading'])) {
            return;
        }

        $notifications = new MeprNotifications();
        $notifications->add(
            [
                'id'      => 'event_' . time(),
                'title'   => $drm_info['heading'],
                'content' => $drm_info['message'],
                'type'    => 'mepr-drm',
                'segment' => '',
                'saved'   => time(),
                'end'     => '',
                'icon'    => MEPR_IMAGES_URL . '/alert-icon.png',
                'buttons' => [
                    'main' => [
                        'text'   => 'Contact Us',
                        'url'    => $drm_info['support_link'],
                        'target' => '_blank',
                    ],
                ],
                'plans'   => [MEPR_EDITION],
            ]
        );
    }

    /**
     * Abstract method to run the DRM process.
     */
    abstract public function run();
}
