<?php

use MemberPress\GroundLevel\Mothership\AbstractPluginConnection;

/**
 * Provides an interface for connecting the plugin to Mothership packages data
 */
class MeprMothershipPluginConnector extends AbstractPluginConnection
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->pluginId     = 'memberpress';
        $this->pluginPrefix = 'MEPR_';
    }

    /**
     * Gets the license activation status option.
     *
     * @return boolean The license activation status.
     */
    public function getLicenseActivationStatus(): bool
    {
        return MeprUpdateCtrl::is_activated();
    }

    /**
     * Updates the license activation status option.
     *
     * @param boolean $status The status to update.
     */
    public function updateLicenseActivationStatus(bool $status): void
    {
        update_option('mepr_activated', $status);
    }

    /**
     * Gets the license key option.
     *
     * @return string The license key.
     */
    public function getLicenseKey(): string
    {
        return MeprOptions::fetch()->mothership_license;
    }

    /**
     * Updates the license key option.
     *
     * @param string $licenseKey The license key to update.
     */
    public function updateLicenseKey(string $licenseKey): void
    {
        $opts                     = MeprOptions::fetch();
        $opts->mothership_license = $licenseKey;
        $opts->store(false);
    }

    /**
     * Gets the domain option.
     *
     * @return string The domain.
     */
    public function getDomain(): string
    {
        return MeprUtils::site_domain();
    }
}
