<?php
/**
 * View: admin/gateways/paypal-vaulting/payment-methods.php
 *
 * @var MeprPayPalVaultingGateway $gateway
 * @var MeprOptions $options
 */

defined('ABSPATH') || exit;
?>
<div class="mepr-paypal-customize-payment-methods">
    <button type="button" class="button button-secondary mepr-customize-btn" data-modal-target="mepr-paypal-payment-methods-modal-<?php echo esc_attr($gateway->id); ?>">
        <?php esc_html_e('Customize Payment Methods', 'memberpress'); ?>
    </button>
    <div class="mepr_modal" id="mepr-paypal-payment-methods-modal-<?php echo esc_attr($gateway->id); ?>" role="dialog" aria-modal="true">
        <div class="mepr_modal__overlay"></div>
        <div class="mepr_modal__content_wrapper">
            <div class="mepr_modal__content">
                <div class="mepr_modal__box">
                    <button type="button" class="mepr_modal__close">&#x2715;</button>
                    <div>
                        <h3><?php esc_html_e('Customize Payment Methods', 'memberpress'); ?></h3>
                        <div class="mepr-paypal-payment-methods">
                            <p>
                                <?php esc_html_e('Enable or disable payment methods for your PayPal checkout. Some methods may require eligibility from PayPal.', 'memberpress'); ?>
                            </p>

                            <div class="mepr-paypal-section-header">
                                <?php esc_html_e('Enable Buttons (if eligible)', 'memberpress'); ?>
                            </div>

                            <div class="mepr-paypal-payment-method">
                                <label class="switch">
                                    <input type="checkbox"
                                        id="<?php echo esc_attr(sanitize_key("{$options->integrations_str}_{$gateway->id}_enable_paypal")); ?>"
                                        class="mepr-paypal-payment-method-checkbox mepr-paypal-primary-method"
                                        name="<?php echo esc_attr($options->integrations_str); ?>[<?php echo esc_attr($gateway->id); ?>][enable_paypal]"
                                        value="1"
                                        <?php checked($gateway->settings->enable_paypal); ?>>
                                    <span class="slider round"></span>
                                </label>
                                <label for="<?php echo esc_attr(sanitize_key("{$options->integrations_str}_{$gateway->id}_enable_paypal")); ?>">
                                    <?php esc_html_e('PayPal', 'memberpress'); ?>
                                </label>
                            </div>

                            <?php
                            $payment_methods = [
                                [
                                    'key'   => 'paylater',
                                    'label' => __('Pay Later', 'memberpress'),
                                ],
                                [
                                    'key'   => 'credit',
                                    'label' => __('Credit', 'memberpress'),
                                ],
                                [
                                    'key'   => 'venmo',
                                    'label' => __('Venmo', 'memberpress'),
                                ],
                                [
                                    'key'      => 'card',
                                    'label'    => __('Card', 'memberpress'),
                                    'class'    => 'mepr-paypal-card-button',
                                    'disabled' => !$gateway->settings->enable_paypal || $gateway->settings->enable_advanced_cards,
                                ],
                                [
                                    'key'   => 'apple_pay',
                                    'label' => __('Apple Pay', 'memberpress'),
                                ],
                                [
                                    'key'   => 'google_pay',
                                    'label' => __('Google Pay', 'memberpress'),
                                ],
                            ];

                            foreach ($payment_methods as $method) {
                                $setting_key = 'enable_' . $method['key'];
                                $input_id    = sanitize_key($options->integrations_str . '_' . $gateway->id . '_' . $setting_key);
                                $is_disabled = $method['disabled'] ?? !$gateway->settings->enable_paypal;
                                ?>
                                <div class="mepr-paypal-payment-method mepr-paypal-dependent-method">
                                    <label class="switch">
                                        <input type="checkbox"
                                            id="<?php echo esc_attr($input_id); ?>"
                                            class="mepr-paypal-payment-method-checkbox<?php echo !empty($method['class']) ? ' ' . esc_attr($method['class']) : ''; ?>"
                                            name="<?php echo esc_attr($options->integrations_str); ?>[<?php echo esc_attr($gateway->id); ?>][<?php echo esc_attr($setting_key); ?>]"
                                            value="1"
                                            <?php checked($gateway->settings->$setting_key); ?>
                                            <?php disabled($is_disabled); ?>>
                                        <span class="slider round"></span>
                                    </label>
                                    <label for="<?php echo esc_attr($input_id); ?>">
                                        <?php echo esc_html($method['label']); ?>
                                    </label>
                                </div>
                                <?php
                            }
                            ?>

                            <div class="mepr-paypal-section-header">
                                <?php esc_html_e('Accept Card Payments', 'memberpress'); ?>
                            </div>

                            <div class="mepr-paypal-payment-method">
                                <label class="switch">
                                    <input type="checkbox"
                                        id="<?php echo esc_attr(sanitize_key("{$options->integrations_str}_{$gateway->id}_enable_advanced_cards")); ?>"
                                        class="mepr-paypal-payment-method-checkbox mepr-paypal-card-fields"
                                        name="<?php echo esc_attr($options->integrations_str); ?>[<?php echo esc_attr($gateway->id); ?>][enable_advanced_cards]"
                                        value="1"
                                        <?php checked($gateway->settings->enable_advanced_cards); ?>
                                        <?php disabled($gateway->settings->enable_paypal && $gateway->settings->enable_card); ?>>
                                    <span class="slider round"></span>
                                </label>
                                <label for="<?php echo esc_attr(sanitize_key("{$options->integrations_str}_{$gateway->id}_enable_advanced_cards")); ?>">
                                    <?php esc_html_e('Card Fields', 'memberpress'); ?>
                                </label>
                            </div>

                        </div>
                        <div class="mepr-update-paypal-payment-methods">
                            <button class="mepr_modal__button button button-primary">
                                <?php esc_html_e('Update', 'memberpress'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
