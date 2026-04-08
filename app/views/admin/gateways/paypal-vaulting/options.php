<?php
/**
 * View: admin/gateways/paypal-vaulting/options.php
 *
 * @var MeprPayPalVaultingGateway $gateway
 * @var MeprOptions $options
 */

defined('ABSPATH') || exit;
$display_keys = isset($_GET['display-keys']) || isset($_COOKIE['mepr_stripe_display_keys']);
?>
<div class="mepr-paypal-vaulting-options-form">
    <img class="mepr-paypal-vaulting-logo" src="<?php echo esc_url(MEPR_IMAGES_URL . '/paypal-logo.svg'); ?>" alt="PayPal">
    <div class="mepr-paypal-vaulting-env-boxes">
        <div class="mepr-paypal-vaulting-env-box mepr-gateway-env-production<?php echo !$gateway->settings->sandbox ? ' mepr-paypal-vaulting-active' : ''; ?>">
            <div class="mepr-paypal-vaulting-box-header">
                <?php
                if ($gateway->settings->production_connected && !$gateway->settings->sandbox) {
                    esc_html_e('Production (active)', 'memberpress');
                } else {
                    esc_html_e('Production', 'memberpress');
                }
                ?>
            </div>
            <div class="mepr-paypal-vaulting-panel">
                <?php if ($gateway->settings->production_connected) : ?>
                    <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_connected]"); ?>" value="1">

                    <div class="mepr-paypal-vaulting-connected-status">
                        <span class="mepr-paypal-vaulting-connected-badge">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Connected', 'memberpress'); ?>
                        </span>

                        <div class="mepr-paypal-vaulting-actions">
                            <?php if ($display_keys) : ?>
                                <a class="button button-secondary" href="<?php echo esc_url($gateway->refresh_credentials_url('production')); ?>">
                                    <?php esc_html_e('Refresh Credentials', 'memberpress'); ?>
                                </a>
                            <?php endif; ?>
                            <a class="button button-secondary mepr-paypal-vaulting-disconnect" href="<?php echo esc_url($gateway->disconnect_url('production')); ?>">
                                <?php esc_html_e('Disconnect', 'memberpress'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="mepr-paypal-vaulting-account-info">
                        <span class="mepr-paypal-vaulting-account-label"><?php esc_html_e('PayPal Account', 'memberpress'); ?></span>
                        <span class="mepr-paypal-vaulting-account-value"><?php echo esc_html($gateway->settings->production_primary_email); ?></span>
                    </div>
                <?php else : ?>
                    <?php if ($gateway->settings->saved) : ?>
                        <?php
                        try {
                            $connect_url = $gateway->connect_auth_url('production');
                            ?>
                            <div class="mepr-paypal-vaulting-connect">
                                <div class="mepr-paypal-vaulting-connect-info">
                                    <p><?php esc_html_e('Connect your PayPal account to start accepting payments.', 'memberpress'); ?></p>
                                </div>
                                <div class="mepr-paypal-vaulting-connect-action">
                                    <a href="<?php echo esc_url($connect_url); ?>" class="button button-primary">
                                        <?php esc_html_e('Connect with PayPal', 'memberpress'); ?>
                                    </a>
                                </div>
                            </div>
                            <?php
                        } catch (Exception $e) {
                            printf(
                                '<div class="notice notice-error inline">%s</div>',
                                esc_html(
                                    sprintf('Error generating PayPal production connect URL: %s', $e->getMessage())
                                )
                            );
                        }
                        ?>
                    <?php else : ?>
                        <div class="mepr-paypal-vaulting-connect">
                            <div class="mepr-paypal-vaulting-connect-info">
                                <p><?php esc_html_e('Connect your PayPal account to start accepting payments.', 'memberpress'); ?></p>
                            </div>
                            <div class="mepr-paypal-vaulting-connect-action">
                                <button type="button" class="button button-primary mepr-paypal-vaulting-connect-new-gateway" data-environment="production">
                                    <?php esc_html_e('Connect with PayPal', 'memberpress'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="mepr-paypal-vaulting-env-box mepr-gateway-env-sandbox<?php echo $gateway->settings->sandbox ? ' mepr-paypal-vaulting-active' : ''; ?>">
            <div class="mepr-paypal-vaulting-box-header">
                <?php
                if ($gateway->settings->sandbox_connected && $gateway->settings->sandbox) {
                    esc_html_e('Sandbox (active)', 'memberpress');
                } else {
                    esc_html_e('Sandbox', 'memberpress');
                }
                ?>
            </div>
            <div class="mepr-paypal-vaulting-panel">
                <?php if ($gateway->settings->sandbox_connected) : ?>
                    <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_connected]"); ?>" value="1">

                    <div class="mepr-paypal-vaulting-connected-status">
                        <span class="mepr-paypal-vaulting-connected-badge">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Connected', 'memberpress'); ?>
                        </span>

                        <div class="mepr-paypal-vaulting-actions">
                            <?php if ($display_keys) : ?>
                                <a class="button button-secondary" href="<?php echo esc_url($gateway->refresh_credentials_url('sandbox')); ?>">
                                    <?php esc_html_e('Refresh Credentials', 'memberpress'); ?>
                                </a>
                            <?php endif; ?>
                            <a class="button button-secondary mepr-paypal-vaulting-disconnect" href="<?php echo esc_url($gateway->disconnect_url('sandbox')); ?>">
                                <?php esc_html_e('Disconnect', 'memberpress'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="mepr-paypal-vaulting-account-info">
                        <span class="mepr-paypal-vaulting-account-label"><?php esc_html_e('PayPal Account', 'memberpress'); ?></span>
                        <span class="mepr-paypal-vaulting-account-value"><?php echo esc_html($gateway->settings->sandbox_primary_email); ?></span>
                    </div>
                <?php else : ?>
                    <?php if ($gateway->settings->saved) : ?>
                        <?php
                        try {
                            $connect_url = $gateway->connect_auth_url('sandbox');
                            ?>
                            <div class="mepr-paypal-vaulting-connect">
                                <div class="mepr-paypal-vaulting-connect-info">
                                    <p><?php esc_html_e('Connect to the PayPal Sandbox environment for testing.', 'memberpress'); ?></p>
                                </div>
                                <div class="mepr-paypal-vaulting-connect-action">
                                    <a href="<?php echo esc_url($connect_url); ?>" class="button button-secondary">
                                        <?php esc_html_e('Connect with PayPal Sandbox', 'memberpress'); ?>
                                    </a>
                                </div>
                            </div>
                            <?php
                        } catch (Exception $e) {
                            printf(
                                '<div class="notice notice-error inline">%s</div>',
                                esc_html(
                                    sprintf('Error generating PayPal sandbox connect URL: %s', $e->getMessage())
                                )
                            );
                        }
                        ?>
                    <?php else : ?>
                        <div class="mepr-paypal-vaulting-connect">
                            <div class="mepr-paypal-vaulting-connect-info">
                                <p><?php esc_html_e('Connect to the PayPal Sandbox environment for testing.', 'memberpress'); ?></p>
                            </div>
                            <div class="mepr-paypal-vaulting-connect-action">
                                <button type="button" class="button button-secondary mepr-paypal-vaulting-connect-new-gateway" data-environment="sandbox">
                                    <?php esc_html_e('Connect with PayPal Sandbox', 'memberpress'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($gateway->settings->saved) : ?>
        <div<?php echo !$display_keys ? ' class="mepr-hidden"' : ''; ?>>
            <table class="form-table">
                <tbody>
                    <?php if ($gateway->settings->production_connected) : ?>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Production Merchant ID:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_merchant_id]"); ?>" value="<?php echo esc_attr($gateway->settings->production_merchant_id); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Production Primary Email:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_primary_email]"); ?>" value="<?php echo esc_attr($gateway->settings->production_primary_email); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Production Primary Currency:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_primary_currency]"); ?>" value="<?php echo esc_attr($gateway->settings->production_primary_currency); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Production Country:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_country]"); ?>" value="<?php echo esc_attr($gateway->settings->production_country); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Production Public ID:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_public_id]"); ?>" value="<?php echo esc_attr($gateway->settings->production_public_id); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Production Secret Key:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_secret_key]"); ?>" value="<?php echo esc_attr($gateway->settings->production_secret_key); ?>" class="regular-text">
                            </td>
                        </tr>
                    <?php else : ?>
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_merchant_id]"); ?>" value="<?php echo esc_attr($gateway->settings->production_merchant_id); ?>">
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_primary_email]"); ?>" value="<?php echo esc_attr($gateway->settings->production_primary_email); ?>">
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_primary_currency]"); ?>" value="<?php echo esc_attr($gateway->settings->production_primary_currency); ?>">
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_country]"); ?>" value="<?php echo esc_attr($gateway->settings->production_country); ?>">
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_public_id]"); ?>" value="<?php echo esc_attr($gateway->settings->production_public_id); ?>">
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][production_secret_key]"); ?>" value="<?php echo esc_attr($gateway->settings->production_secret_key); ?>">
                    <?php endif; ?>

                    <?php if ($gateway->settings->sandbox_connected) : ?>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Sandbox Merchant ID:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_merchant_id]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_merchant_id); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Sandbox Primary Email:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_primary_email]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_primary_email); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Sandbox Primary Currency:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_primary_currency]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_primary_currency); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Sandbox Country:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_country]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_country); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Sandbox Public ID:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_public_id]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_public_id); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label><?php esc_html_e('Sandbox Secret Key:', 'memberpress'); ?></label></th>
                            <td>
                                <input type="text" readonly name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_secret_key]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_secret_key); ?>" class="regular-text">
                            </td>
                        </tr>
                    <?php else : ?>
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_merchant_id]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_merchant_id); ?>">
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_primary_email]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_primary_email); ?>">
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_primary_currency]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_primary_currency); ?>">
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_country]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_country); ?>">
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_public_id]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_public_id); ?>">
                        <input type="hidden" name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox_secret_key]"); ?>" value="<?php echo esc_attr($gateway->settings->sandbox_secret_key); ?>">
                    <?php endif; ?>

                    <tr valign="top">
                        <th scope="row"><label><?php esc_html_e('Webhook URL:', 'memberpress'); ?></label></th>
                        <td>
                            <?php MeprAppHelper::clipboard_input($gateway->notify_url('webhook')); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mepr-paypal-vaulting-sandbox-toggle">
            <label for="<?php echo esc_attr(sanitize_key("$options->integrations_str[$gateway->id][sandbox]")); ?>">
                <input type="checkbox"
                       id="<?php echo esc_attr(sanitize_key("$options->integrations_str[$gateway->id][sandbox]")); ?>"
                       name="<?php echo esc_attr("$options->integrations_str[$gateway->id][sandbox]"); ?>"
                       value="1"
                       <?php checked($gateway->settings->sandbox); ?> />
                <?php esc_html_e('Use PayPal Sandbox', 'memberpress'); ?>
            </label>
        </div>

        <?php
        MeprView::render('/admin/gateways/paypal-vaulting/payment-methods', [
            'gateway' => $gateway,
            'options' => $options,
        ]);
        ?>
    <?php endif; ?>
</div>
