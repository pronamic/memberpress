<?php
/**
 * View: admin/gateways/square/options.php
 *
 * @var MeprSquareGateway $gateway
 * @var MeprOptions $options
 */

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
$show_refresh = isset($_GET['display-keys']) || isset($_COOKIE['mepr_stripe_display_keys']);
?>
<div class="mepr-square-options-form">
    <div class="mepr-square-env-boxes">
        <div class="mepr-square-env-box mepr-gateway-env-production<?php echo !$gateway->settings->sandbox ? ' mepr-square-active' : ''; ?>">
            <div class="mepr-square-box-header">
                <?php
                if ($gateway->settings->production_connected && !$gateway->settings->sandbox) {
                    esc_html_e('Production (active)', 'memberpress');
                } else {
                    esc_html_e('Production', 'memberpress');
                }
                ?>
            </div>
            <div class="mepr-square-panel">
                <?php if ($gateway->settings->production_connected) : ?>
                    <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_connected]") ?>" value="1">

                    <div class="mepr-square-connected-status">
                        <span class="mepr-square-connected-badge">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Connected', 'memberpress'); ?>
                        </span>

                        <div class="mepr-square-actions">
                            <?php if ($show_refresh) : ?>
                                <a class="button button-secondary" href="<?php echo esc_url($gateway->refresh_credentials_url('production')); ?>">
                                    <?php esc_html_e('Refresh Credentials', 'memberpress'); ?>
                                </a>
                            <?php endif; ?>
                            <a class="button button-secondary mepr-square-disconnect" href="<?php echo esc_url($gateway->disconnect_url('production')); ?>">
                                <?php esc_html_e('Disconnect', 'memberpress'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="mepr-square-row">
                        <label for="<?php echo esc_attr(sanitize_key("$options->integrations_str[$gateway->id][production_location_id]")); ?>">
                            <?php esc_html_e('Business Location', 'memberpress'); ?>
                        </label>
                        <div class="mepr-square-field">
                            <?php echo $gateway->get_business_location_dropdown('production'); ?>
                        </div>
                    </div>
                <?php else : ?>
                    <?php if ($gateway->settings->saved) : ?>
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_location_id]") ?>" value="<?php echo esc_attr($gateway->settings->production_location_id); ?>">
                        <?php
                        try {
                            $connect_url = $gateway->connect_auth_url('production');
                            ?>
                            <div class="mepr-square-connect">
                                <div class="mepr-square-connect-info">
                                    <p><?php esc_html_e('Connect your Square account to start accepting payments.', 'memberpress'); ?></p>
                                </div>
                                <div class="mepr-square-connect-action">
                                    <a href="<?php echo esc_url($connect_url); ?>" class="button button-primary">
                                        <?php esc_html_e('Connect with Square', 'memberpress'); ?>
                                    </a>
                                </div>
                            </div>
                            <?php
                        } catch (Exception $e) {
                            printf(
                                '<div class="notice notice-error inline">%s</div>',
                                esc_html(
                                    sprintf('Error generating Square production connect URL: %s', $e->getMessage())
                                )
                            );
                        }
                        ?>
                    <?php else : ?>
                        <div class="mepr-square-connect">
                            <div class="mepr-square-connect-info">
                                <p><?php esc_html_e('Connect your Square account to start accepting payments.', 'memberpress'); ?></p>
                            </div>
                            <div class="mepr-square-connect-action">
                                <button type="button" class="button button-primary mepr-square-connect-new-gateway" data-environment="production">
                                    <?php esc_html_e('Connect with Square', 'memberpress'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="mepr-square-env-box mepr-gateway-env-sandbox<?php echo $gateway->settings->sandbox ? ' mepr-square-active' : ''; ?>">
            <div class="mepr-square-box-header">
                <?php
                if ($gateway->settings->sandbox_connected && $gateway->settings->sandbox) {
                    esc_html_e('Sandbox (active)', 'memberpress');
                } else {
                    esc_html_e('Sandbox', 'memberpress');
                }
                ?>
            </div>
            <div class="mepr-square-panel">
                <?php if ($gateway->settings->sandbox_connected) : ?>
                    <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_connected]") ?>" value="1">

                    <div class="mepr-square-connected-status">
                        <span class="mepr-square-connected-badge">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Connected', 'memberpress'); ?>
                        </span>

                        <div class="mepr-square-actions">
                            <?php if ($show_refresh) : ?>
                                <a class="button button-secondary" href="<?php echo esc_url($gateway->refresh_credentials_url('sandbox')); ?>">
                                    <?php esc_html_e('Refresh Credentials', 'memberpress'); ?>
                                </a>
                            <?php endif; ?>
                            <a class="button button-secondary mepr-square-disconnect" href="<?php echo esc_url($gateway->disconnect_url('sandbox')); ?>">
                                <?php esc_html_e('Disconnect', 'memberpress'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="mepr-square-row">
                        <label for="<?php echo esc_attr(sanitize_key("$options->integrations_str[$gateway->id][sandbox_location_id]")); ?>">
                            <?php esc_html_e('Business Location', 'memberpress'); ?>
                        </label>
                        <div class="mepr-square-field">
                            <?php echo $gateway->get_business_location_dropdown('sandbox'); ?>
                        </div>
                    </div>
                <?php else : ?>
                    <?php if ($gateway->settings->saved) : ?>
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_location_id]") ?>" value="<?php echo esc_attr($gateway->settings->sandbox_location_id); ?>">
                        <?php
                        try {
                            $connect_url = $gateway->connect_auth_url('sandbox');
                            ?>
                            <div class="mepr-square-connect">
                                <div class="mepr-square-connect-info">
                                    <p><?php esc_html_e('Connect to the Square Sandbox environment for testing.', 'memberpress'); ?></p>
                                </div>
                                <div class="mepr-square-connect-action">
                                    <a href="<?php echo esc_url($connect_url); ?>" class="button button-secondary">
                                        <?php esc_html_e('Connect with Square Sandbox', 'memberpress'); ?>
                                    </a>
                                </div>
                            </div>
                            <?php
                        } catch (Exception $e) {
                            printf(
                                '<div class="notice notice-error inline">%s</div>',
                                esc_html(
                                    sprintf('Error generating Square sandbox connect URL: %s', $e->getMessage())
                                )
                            );
                        }
                        ?>
                    <?php else : ?>
                        <div class="mepr-square-connect">
                            <div class="mepr-square-connect-info">
                                <p><?php esc_html_e('Connect to the Square Sandbox environment for testing.', 'memberpress'); ?></p>
                            </div>
                            <div class="mepr-square-connect-action">
                                <button type="button" class="button button-secondary mepr-square-connect-new-gateway" data-environment="sandbox">
                                    <?php esc_html_e('Connect with Square Sandbox', 'memberpress'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($gateway->settings->saved) : ?>
        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_merchant_id]") ?>" value="<?php echo esc_attr($gateway->settings->production_merchant_id); ?>">
        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_access_token]") ?>" value="<?php echo esc_attr($gateway->settings->production_access_token); ?>">
        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_expires_at]") ?>" value="<?php echo esc_attr($gateway->settings->production_expires_at); ?>">
        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_currency]") ?>" value="<?php echo esc_attr($gateway->settings->production_currency); ?>">
        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_country]") ?>" value="<?php echo esc_attr($gateway->settings->production_country); ?>">
        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_merchant_id]") ?>" value="<?php echo esc_attr($gateway->settings->sandbox_merchant_id); ?>">
        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_access_token]") ?>" value="<?php echo esc_attr($gateway->settings->sandbox_access_token); ?>">
        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_expires_at]") ?>" value="<?php echo esc_attr($gateway->settings->sandbox_expires_at); ?>">
        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_currency]") ?>" value="<?php echo esc_attr($gateway->settings->sandbox_currency); ?>">
        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_country]") ?>" value="<?php echo esc_attr($gateway->settings->sandbox_country); ?>">

        <div class="mepr-square-sandbox-toggle">
            <label for="<?php echo esc_attr(sanitize_key("$options->integrations_str[$gateway->id][sandbox]")); ?>">
                <input type="checkbox"
                       id="<?php echo esc_attr(sanitize_key("$options->integrations_str[$gateway->id][sandbox]")); ?>"
                       name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox]") ?>"
                       value="1"
                       <?php checked($gateway->settings->sandbox); ?> />
                <?php esc_html_e('Use Square Sandbox', 'memberpress'); ?>
            </label>
        </div>
    <?php endif; ?>
</div>
