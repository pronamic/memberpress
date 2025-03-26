<?php

if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprDrmInvalid extends MeprBaseDrm
{
    /**
     * Constructs the MeprDrmInvalid object.
     */
    public function __construct()
    {
        parent::__construct();
        $this->event_name = MeprDrmHelper::INVALID_LICENSE_EVENT;

        add_action('mepr_drm_invalid_license_event', [$this, 'drm_event'], 10, 3);
    }

    /**
     * Runs the MeprDrmInvalid object.
     *
     * @return void
     */
    public function run()
    {
        $event = MeprEvent::latest($this->event_name);

        if ($event) {
            $days = MeprDrmHelper::days_elapsed($event->created_at);
            if ($days >= 7 && $days <= 20) {
                $this->set_status(MeprDrmHelper::DRM_MEDIUM);
            } elseif ($days >= 21) {
                $this->set_status(MeprDrmHelper::DRM_LOCKED);
                if ($days >= 50) {
                    MeprHooks::do_action('mepr_drm_set_status_locked', MeprDrmHelper::DRM_LOCKED, $days, $this->event_name);
                }
            }
        }

        // DRM status detected.
        if ('' !== $this->drm_status) {
            do_action('mepr_drm_invalid_license_event', $event, $days, $this->drm_status);
        }
    }

    /**
     * Adds the site health status.
     *
     * @param  array $tests The tests.
     * @return array
     */
    public function site_health_status($tests)
    {

        $drm_status = MeprDrmHelper::get_status();

        if ($drm_status == '') {
            return $tests; // bail.
        }

        $tests['direct']['memberpress_drm_invalid_key'] = [
            'label' => __('MemberPress - Invalid License', 'memberpress'),
            'test'  => [$this, 'run_site_health_drm'],
        ];

        return $tests;
    }
}
