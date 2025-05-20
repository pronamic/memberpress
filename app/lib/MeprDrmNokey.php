<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprDrmNokey extends MeprBaseDrm
{
    /**
     * Constructor for the MeprDrmNokey class.
     */
    public function __construct()
    {
        parent::__construct();
        $this->event_name = MeprDrmHelper::NO_LICENSE_EVENT;
        add_action('mepr_drm_no_license_event', [$this, 'drm_event'], 10, 3);
    }

    /**
     * Runs the DRM no-key check functionality.
     *
     * @return void
     */
    public function run()
    {
        $event = MeprEvent::latest($this->event_name);

        if ($event) {
            $days = MeprDrmHelper::days_elapsed($event->created_at);
            if ($days >= 14 && $days <= 20) {
                $this->set_status(MeprDrmHelper::DRM_LOW);
            } elseif ($days >= 21 && $days <= 29) {
                $this->set_status(MeprDrmHelper::DRM_MEDIUM);
            } elseif ($days >= 30) {
                $this->set_status(MeprDrmHelper::DRM_LOCKED);
                if ($days >= 60) {
                    MeprHooks::do_action('mepr_drm_set_status_locked', MeprDrmHelper::DRM_LOCKED, $days, $this->event_name);
                }
            }
        }

        // DRM status detected.
        if ('' !== $this->drm_status) {
            do_action('mepr_drm_no_license_event', $event, $days, $this->drm_status);
        }
    }

    /**
     * Adds site health status check for DRM key.
     *
     * @param array $tests The existing tests array.
     *
     * @return array
     */
    public function site_health_status($tests)
    {

        $drm_status = MeprDrmHelper::get_status();

        if ($drm_status == '') {
            return $tests; // Bail.
        }

        $tests['direct']['memberpress_drm_no_key'] = [
            'label' => __('MemberPress - Licence Key Missing', 'memberpress'),
            'test'  => [$this, 'run_site_health_drm'],
        ];

        return $tests;
    }
}
